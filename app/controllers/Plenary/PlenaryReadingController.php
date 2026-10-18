<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/DocumentService.php';

/**
 * PlenaryReadingController
 *
 * Handles the full legislative reading lifecycle for documents that have been
 * saved with the "Under Processing" Plenary processing form:
 *
 *   First Reading  → Refer to Committee
 *                    OR Adopted under Suspended Rules → Second Reading queue
 *
 *   Committee Return → document appears in Second Reading (gated: only after
 *                      committee workflow returns it via RETURNED_TO_PLENARY)
 *
 *   Second Reading → Approved  → Finalized Documents
 *                  → Return/Refer to Committee (same gate applies on next return)
 *                  → Defer     → Second Reading Deferred (pending)
 *                  → Withdraw  → Withdrawn from Second Reading
 *
 *   Finalized Documents (Status List) → read-only list page
 *
 * ROUTES (from routes/web.php):
 *   GET  plenary/first-reading          firstReadingIndex()
 *   GET  plenary/first-reading/show     firstReadingShow()
 *   POST plenary/first-reading/store    firstReadingStore()
 *   GET  plenary/second-reading         secondReadingIndex()
 *   GET  plenary/second-reading/show    secondReadingShow()
 *   POST plenary/second-reading/store   secondReadingStore()
 *   GET  plenary/finalize/show          finalizeShow()   [redirect — finalized on 2nd reading]
 *   GET  plenary/status-list            statusList()
 *
 * AUTHORIZATION:
 *   RoleMiddleware restricts all plenary/* routes to ['Super Admin', 'Plenary'].
 *   Within each write action, ownership is verified against document_assignments:
 *     – First Reading write: the document must be ACCEPTED by this user.
 *     – Second Reading write: same.
 *   The reading pages themselves show the correct queue per status, so a user
 *   cannot skip steps by guessing a URL — the assignment + status guard prevents it.
 *
 * COMMITTEE GATE:
 *   A document referred to Committee at First Reading CANNOT enter the Second
 *   Reading queue until its `current_status_id` becomes the "Returned to Plenary"
 *   status (set by CommitteeReportsController::returnToPlenary()).  The Second
 *   Reading list query enforces this.  A direct POST to second-reading/store for
 *   a still-referred document is rejected server-side by verifying the status.
 *
 * DEFERRED DOCUMENTS:
 *   When a Second Reading is deferred, the document's status is set to
 *   "Second Reading Deferred" and its plenary_readings action is DEFERRED.
 *   An authorized Plenary user can call secondReadingReturnDeferred() via the
 *   POST endpoint plenary/second-reading/return-deferred to move it back to
 *   "Awaiting Second Reading" for re-processing.
 *   NOTE: There is no automated trigger — a Plenary user must explicitly mark
 *   the deferred document ready (see OPEN_QUESTION below).
 *
 * OPEN QUESTIONS (cannot be inferred from the codebase — must be answered by
 * the SP or system owner before these rules can be hardened):
 *
 *   1. Can committee selections be changed when a document is referred at
 *      First Reading vs. when it is referred again at Second Reading?
 *      CURRENT IMPLEMENTATION: allows changes at both steps.  The committees
 *      selected at First Reading are pre-populated but editable.
 *
 *   2. What constitutes "necessary updates" being complete for a deferred
 *      Second Reading document?  There is no automated check in the schema.
 *      CURRENT IMPLEMENTATION: an authorized Plenary user manually clicks
 *      "Return to Second Reading" from the deferred document's detail page,
 *      optionally uploading a compliance document.  The returning user is
 *      recorded in plenary_second_reading_details.returned_to_second_reading_by.
 *
 *   3. Proposed Number format — confirmed free-text (no authoritative format).
 *      The Approved Number is also free-text (document_approvals.approved_number).
 *
 * FINALIZED DOCUMENTS PAGE:
 *   The existing Admin > Archived Records page is for manually entered archival
 *   records (independent of the live document workflow).  The Plenary
 *   "Finalized Documents" list (Status List page) shows documents whose
 *   current_status_id = "Second Reading Approved", joining document_approvals
 *   for the approved number and date, and document_authors for authorship.
 *   Approved documents are also written to document_approvals (not archived_records)
 *   because they are still live tracked documents, not manually archived ones.
 */
class PlenaryReadingController
{
    protected PDO             $pdo;
    protected DocumentService $docService;

    private const MAX_RETRIES  = 3;
    private const RETRY_DELAY  = 100_000; // µs

    public function __construct()
    {
        $db               = new Database();
        $this->pdo        = $db->connect();
        $this->docService = new DocumentService();
    }

    // =========================================================================
    // FIRST READING — Queue list
    // =========================================================================

    public function firstReadingIndex(): void
    {
        $userId = auth_id();
        if ($userId === null) { flash_set('error', 'You must be logged in.'); redirect('login'); }

        $search  = trim($_GET['search'] ?? '');
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        // Status IDs we need for the queue
        $awaitingStatusId = $this->requireStatusId('Awaiting First Reading');
        $returnedStatusId = $this->requireStatusId('Returned to Plenary');

        // We also want to show documents whose plenary_readings indicates
        // first reading was NOT yet done.  The primary filter is:
        //   current_status_id IN (Awaiting First Reading)
        //   AND NOT EXISTS a plenary_readings row with reading_number=1
        $whereParams = [$awaitingStatusId];
        $whereExtra  = '';
        if ($search !== '') {
            $whereExtra   = " AND (d.tracking_number LIKE ? OR d.subject_matter LIKE ?)";
            $whereParams[] = "%{$search}%";
            $whereParams[] = "%{$search}%";
        }

        $countStmt = $this->pdo->prepare("
            SELECT COUNT(*) AS cnt
            FROM documents d
            WHERE d.current_status_id = ?
              AND NOT EXISTS (
                  SELECT 1 FROM plenary_readings pr
                  WHERE pr.document_id = d.id AND pr.reading_number = 1
              )
              {$whereExtra}
        ");
        $countStmt->execute($whereParams);
        $total      = (int) $countStmt->fetchColumn();
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page       = min($page, $totalPages);

        $listStmt = $this->pdo->prepare("
            SELECT
                d.id               AS document_id,
                d.tracking_number,
                d.subject_matter,
                d.date_received,
                d.current_phase,
                ds.name            AS status,
                ds.badge_color     AS status_badge_color,
                dt.name            AS document_type_name,
                dt.badge_color     AS document_type_badge_color,
                pdd.proposed_number,
                pdd.target_first_reading_date,
                pdd.session_type,
                -- accepted-by info from Plenary assignment
                da.accepted_by,
                ab.username        AS accepted_by_username,
                CONCAT_WS(' ', NULLIF(aui.first_name,''), NULLIF(aui.last_name,''))
                                   AS accepted_by_name
            FROM documents d
            LEFT JOIN document_statuses            ds  ON ds.id  = d.current_status_id
            LEFT JOIN document_types               dt  ON dt.id  = d.document_type_id
            LEFT JOIN plenary_document_details     pdd ON pdd.document_id = d.id
            LEFT JOIN (
                SELECT document_id, accepted_by
                FROM document_assignments
                WHERE phase = 'PLENARY' AND decision = 'ACCEPTED' AND completed_at IS NULL
                ORDER BY id DESC LIMIT 1
            ) da ON da.document_id = d.id
            LEFT JOIN user_accounts ab  ON ab.id  = da.accepted_by
            LEFT JOIN user_info     aui ON aui.user_account_id = da.accepted_by
            WHERE d.current_status_id = ?
              AND NOT EXISTS (
                  SELECT 1 FROM plenary_readings pr
                  WHERE pr.document_id = d.id AND pr.reading_number = 1
              )
              {$whereExtra}
            ORDER BY pdd.target_first_reading_date ASC, d.id ASC
            LIMIT ? OFFSET ?
        ");
        $listStmt->execute([...$whereParams, $perPage, $offset]);
        $documents = $listStmt->fetchAll();

        // Count for referred (in committee, awaiting return to become 2nd reading eligible)
        $referredCount = (int) $this->pdo->query("
            SELECT COUNT(*)
            FROM documents d
            WHERE d.current_status_id = (
                SELECT id FROM document_statuses WHERE name = 'Returned to Plenary' AND is_active=1 AND is_deleted=0 LIMIT 1
            )
            AND EXISTS (
                SELECT 1 FROM plenary_readings pr
                WHERE pr.document_id = d.id AND pr.reading_number = 1
                  AND pr.action = 'REFERRED_TO_COMMITTEE'
            )
            AND NOT EXISTS (
                SELECT 1 FROM plenary_readings pr2
                WHERE pr2.document_id = d.id AND pr2.reading_number = 2
            )
        ")->fetchColumn();

        $pageTitle = 'First Reading';
        $success = flash_get('success');
        $error   = flash_get('error');
        $errors  = flash_get('errors', []);
        require __DIR__ . '/../../../resources/views/plenary/first-reading/index.php';
    }

    // =========================================================================
    // FIRST READING — Detail / form
    // =========================================================================

    public function firstReadingShow(): void
    {
        $userId     = auth_id();
        $documentId = (int) ($_GET['id'] ?? 0);
        if ($userId === null) { flash_set('error', 'You must be logged in.'); redirect('login'); }
        if ($documentId <= 0) { flash_set('error', 'Invalid document ID.'); redirect('plenary/first-reading'); }

        $document        = $this->requireDocument($documentId);
        $plenaryDetails  = $this->fetchPlenaryDetails($documentId);
        $authors         = $this->fetchAuthors($documentId, 'AUTHOR');
        $coAuthors       = $this->fetchAuthors($documentId, 'CO_AUTHOR');
        $docCommittees   = $this->fetchDocumentCommittees($documentId);
        $committees      = $this->fetchCommittees();
        $events          = $this->fetchEvents($documentId);
        $existingReading = $this->fetchReading($documentId, 1);

        // Ownership: must be ACCEPTED by this user
        $canAct = $this->isOwnedByUser($documentId, $userId);

        // Guard: document must be "Awaiting First Reading" and no reading row yet
        $awaitingStatusId = $this->requireStatusId('Awaiting First Reading');
        if ((int) $document['current_status_id'] !== $awaitingStatusId || $existingReading) {
            flash_set('error', 'This document is not eligible for First Reading processing at this time.');
            redirect('plenary/first-reading');
        }

        $pageTitle = 'First Reading — ' . htmlspecialchars($document['tracking_number']);
        $success = flash_get('success');
        $error   = flash_get('error');
        $errors  = flash_get('errors', []);
        $old     = old_get();
        require __DIR__ . '/../../../resources/views/plenary/first-reading/show.php';
    }

    // =========================================================================
    // FIRST READING — Process POST
    // =========================================================================

    public function firstReadingStore(): void
    {
        $userId = auth_id();
        if ($userId === null) { flash_set('error', 'You must be logged in.'); redirect('login'); }

        $documentId   = (int) ($_POST['document_id'] ?? 0);
        $action       = trim($_POST['first_reading_action'] ?? '');
        $readingDate  = trim($_POST['reading_date'] ?? '');
        $remarks      = trim($_POST['remarks'] ?? '');

        if ($documentId <= 0) { flash_set('error', 'Invalid document ID.'); redirect('plenary/first-reading'); }

        $validActions = ['REFERRED_TO_COMMITTEE', 'ADOPTED_SUSPENDED_RULES'];
        if (!in_array($action, $validActions, true)) {
            flash_set('error', 'Invalid First Reading action.');
            redirect('plenary/first-reading/show?id=' . $documentId);
        }

        // Date validation
        $errors = [];
        if ($readingDate === '' || !$this->isValidDate($readingDate)) {
            $errors[] = 'A valid Reading Date is required.';
        }
        if ($action === 'REFERRED_TO_COMMITTEE') {
            $committeeIds = array_filter(array_map('intval', (array) ($_POST['committee_ids'] ?? [])));
            if (empty($committeeIds)) {
                $errors[] = 'At least one Committee must be selected for referral.';
            }
        }
        if (!empty($errors)) {
            flash_set('errors', $errors);
            old_set($_POST);
            redirect('plenary/first-reading/show?id=' . $documentId);
        }

        $this->runTransaction(function () use ($documentId, $userId, $action, $readingDate, $remarks) {
            // ── Ownership + eligibility guard ──────────────────────────────
            $this->requireOwnedByUser($documentId, $userId);
            $document = $this->requireDocument($documentId);

            $awaitingStatusId = $this->requireStatusId('Awaiting First Reading');
            if ((int) $document['current_status_id'] !== $awaitingStatusId) {
                throw new RuntimeException('This document is not in "Awaiting First Reading" status.');
            }

            // ── Prevent duplicate reading row ──────────────────────────────
            $dup = $this->pdo->prepare(
                "SELECT id FROM plenary_readings WHERE document_id = ? AND reading_number = 1 LIMIT 1"
            );
            $dup->execute([$documentId]);
            if ($dup->fetch()) {
                throw new RuntimeException('First Reading has already been recorded for this document.');
            }

            // ── Insert plenary_readings row ────────────────────────────────
            $this->pdo->prepare("
                INSERT INTO plenary_readings
                    (document_id, reading_number, action, reading_date, remarks, performed_by)
                VALUES (?, 1, ?, ?, ?, ?)
            ")->execute([$documentId, $action, $readingDate, $remarks ?: null, $userId]);
            $readingId = (int) $this->pdo->lastInsertId();

            if ($action === 'REFERRED_TO_COMMITTEE') {
                $this->doReferToCommitteeFirstReading($documentId, $userId, $document);
            } else {
                // ADOPTED_SUSPENDED_RULES → straight to Second Reading
                $this->doAdoptedSuspendedRules($documentId, $userId, $document);
            }

            // ── Document event ─────────────────────────────────────────────
            $toStatusId = (int) $document['current_status_id']; // will be updated in sub-call
            // Re-fetch after update
            $newStatus = $this->pdo->prepare(
                "SELECT current_status_id FROM documents WHERE id = ? LIMIT 1"
            );
            $newStatus->execute([$documentId]);
            $toStatusId = (int) ($newStatus->fetchColumn() ?: $toStatusId);

            $this->insertEvent($documentId, 'PLENARY_FIRST_READING_COMPLETED', $userId, $toStatusId, $remarks, [
                'action'       => $action,
                'reading_date' => $readingDate,
                'reading_id'   => $readingId,
            ]);

            audit_log('UPDATE', 'Document', (string) $documentId, null, [
                'action'       => 'plenary_first_reading',
                'first_action' => $action,
                'reading_date' => $readingDate,
            ], "Plenary First Reading ({$action}) recorded for document ID {$documentId}");
        });

        flash_set('success', 'First Reading recorded successfully.');
        redirect('plenary/first-reading');
    }

    // =========================================================================
    // SECOND READING — Queue list
    // =========================================================================

    public function secondReadingIndex(): void
    {
        $userId = auth_id();
        if ($userId === null) { flash_set('error', 'You must be logged in.'); redirect('login'); }

        $filter  = trim($_GET['filter'] ?? 'pending');
        if (!in_array($filter, ['pending', 'deferred', 'withdrawn', 'approved'], true)) {
            $filter = 'pending';
        }
        $search  = trim($_GET['search'] ?? '');
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        // Map filter to status name(s)
        $statusMap = [
            'pending'   => ['Awaiting Second Reading'],
            'deferred'  => ['Second Reading Deferred'],
            'withdrawn' => ['Withdrawn from Second Reading'],
            'approved'  => ['Second Reading Approved'],
        ];
        $statusNames = $statusMap[$filter];

        // Fetch the IDs dynamically
        $placeholders = implode(',', array_fill(0, count($statusNames), '?'));
        $statusRows   = $this->pdo->prepare("
            SELECT id, name FROM document_statuses
            WHERE name IN ({$placeholders}) AND is_active = 1 AND is_deleted = 0
        ");
        $statusRows->execute($statusNames);
        $statusIds = array_column($statusRows->fetchAll(), 'id');

        if (empty($statusIds)) {
            $documents = [];
            $total = 0;
            $totalPages = 1;
        } else {
            $idPlaceholders = implode(',', array_fill(0, count($statusIds), '?'));
            $whereParams = $statusIds;
            $whereExtra  = '';
            if ($search !== '') {
                $whereExtra   = " AND (d.tracking_number LIKE ? OR d.subject_matter LIKE ?)";
                $whereParams[] = "%{$search}%";
                $whereParams[] = "%{$search}%";
            }

            $countStmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM documents d
                WHERE d.current_status_id IN ({$idPlaceholders}) {$whereExtra}
            ");
            $countStmt->execute($whereParams);
            $total      = (int) $countStmt->fetchColumn();
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
            $page       = min($page, $totalPages);

            $listStmt = $this->pdo->prepare("
                SELECT
                    d.id               AS document_id,
                    d.tracking_number,
                    d.subject_matter,
                    d.date_received,
                    d.current_phase,
                    ds.name            AS status,
                    ds.badge_color     AS status_badge_color,
                    dt.name            AS document_type_name,
                    dt.badge_color     AS document_type_badge_color,
                    pdd.proposed_number,
                    pdd.session_type,
                    -- Latest second reading row (for deferred/withdrawn info)
                    pr2.action         AS second_reading_action,
                    pr2.reading_date   AS second_reading_date,
                    -- Approval info
                    psrd.approved_no,
                    psrd.date_finalized,
                    psrd.date_deferred,
                    psrd.date_withdrawn,
                    -- Committee referral history from first reading
                    (SELECT GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ')
                     FROM document_committees dc
                     INNER JOIN committees c ON c.id = dc.committee_id
                     WHERE dc.document_id = d.id) AS committee_names,
                    -- Accepted-by info
                    da_a.accepted_by,
                    ab.username        AS accepted_by_username,
                    CONCAT_WS(' ', NULLIF(aui.first_name,''), NULLIF(aui.last_name,''))
                                       AS accepted_by_name
                FROM documents d
                LEFT JOIN document_statuses        ds  ON ds.id  = d.current_status_id
                LEFT JOIN document_types           dt  ON dt.id  = d.document_type_id
                LEFT JOIN plenary_document_details pdd ON pdd.document_id = d.id
                LEFT JOIN plenary_readings         pr2 ON pr2.document_id = d.id AND pr2.reading_number = 2
                LEFT JOIN plenary_second_reading_details psrd ON psrd.reading_id = pr2.id
                LEFT JOIN (
                    SELECT document_id, accepted_by
                    FROM document_assignments
                    WHERE phase = 'PLENARY' AND decision = 'ACCEPTED' AND completed_at IS NULL
                    ORDER BY id DESC LIMIT 1
                ) da_a ON da_a.document_id = d.id
                LEFT JOIN user_accounts ab  ON ab.id  = da_a.accepted_by
                LEFT JOIN user_info     aui ON aui.user_account_id = da_a.accepted_by
                WHERE d.current_status_id IN ({$idPlaceholders}) {$whereExtra}
                ORDER BY d.id ASC
                LIMIT ? OFFSET ?
            ");
            $listStmt->execute([...$whereParams, $perPage, $offset]);
            $documents = $listStmt->fetchAll();
        }

        // Tab counts
        $tabCounts = $this->fetchSecondReadingTabCounts();

        $pageTitle = 'Second Reading';
        $currentFilter = $filter;
        $success = flash_get('success');
        $error   = flash_get('error');
        $errors  = flash_get('errors', []);
        require __DIR__ . '/../../../resources/views/plenary/second-reading/index.php';
    }

    // =========================================================================
    // SECOND READING — Detail / form
    // =========================================================================

    public function secondReadingShow(): void
    {
        $userId     = auth_id();
        $documentId = (int) ($_GET['id'] ?? 0);
        if ($userId === null) { flash_set('error', 'You must be logged in.'); redirect('login'); }
        if ($documentId <= 0) { flash_set('error', 'Invalid document ID.'); redirect('plenary/second-reading'); }

        $document       = $this->requireDocument($documentId);
        $plenaryDetails = $this->fetchPlenaryDetails($documentId);
        $authors        = $this->fetchAuthors($documentId, 'AUTHOR');
        $coAuthors      = $this->fetchAuthors($documentId, 'CO_AUTHOR');
        $docCommittees  = $this->fetchDocumentCommittees($documentId);
        $committees     = $this->fetchCommittees();
        $events         = $this->fetchEvents($documentId);
        $firstReading   = $this->fetchReading($documentId, 1);
        $secondReading  = $this->fetchReading($documentId, 2);
        $secondReadingDetails = $secondReading
            ? $this->fetchSecondReadingDetails((int) $secondReading['id'])
            : null;

        $canAct = $this->isOwnedByUser($documentId, $userId);

        // Eligible status: Awaiting Second Reading OR Second Reading Deferred
        $eligibleStatuses = ['Awaiting Second Reading', 'Second Reading Deferred'];
        $eligibleIds = array_map(
            fn($n) => $this->requireStatusId($n),
            $eligibleStatuses
        );
        $inEligibleStatus = in_array((int) $document['current_status_id'], $eligibleIds, true);

        // Deferred details
        $isDeferred = (int) $document['current_status_id'] === $this->requireStatusId('Second Reading Deferred');

        $pageTitle = 'Second Reading — ' . htmlspecialchars($document['tracking_number']);
        $success = flash_get('success');
        $error   = flash_get('error');
        $errors  = flash_get('errors', []);
        $old     = old_get();
        require __DIR__ . '/../../../resources/views/plenary/second-reading/show.php';
    }

    // =========================================================================
    // SECOND READING — Process POST
    // =========================================================================

    public function secondReadingStore(): void
    {
        $userId = auth_id();
        if ($userId === null) { flash_set('error', 'You must be logged in.'); redirect('login'); }

        $documentId = (int) ($_POST['document_id'] ?? 0);
        $action     = trim($_POST['second_reading_action'] ?? '');

        if ($documentId <= 0) { flash_set('error', 'Invalid document ID.'); redirect('plenary/second-reading'); }

        $validActions = ['APPROVED', 'REFERRED_TO_COMMITTEE_SECOND', 'DEFERRED', 'WITHDRAWN'];
        if (!in_array($action, $validActions, true)) {
            flash_set('error', 'Invalid Second Reading action.');
            redirect('plenary/second-reading/show?id=' . $documentId);
        }

        // Validate action-specific fields before opening transaction
        $errors = $this->validateSecondReadingPost($action);
        if (!empty($errors)) {
            flash_set('errors', $errors);
            old_set($_POST);
            redirect('plenary/second-reading/show?id=' . $documentId);
        }

        // Stage compliance file BEFORE transaction (file I/O outside DB locks)
        $stagedCompliance = null;
        if ($action === 'DEFERRED' && !empty($_FILES['compliance_file']['name'])) {
            $fileErrors = $this->validateSingleFile($_FILES['compliance_file']);
            if (!empty($fileErrors)) {
                flash_set('errors', $fileErrors);
                redirect('plenary/second-reading/show?id=' . $documentId);
            }
            $stagedCompliance = $this->stageSingleFile($_FILES['compliance_file'], $documentId);
        }

        $this->runTransaction(function () use ($documentId, $userId, $action, $stagedCompliance) {
            $this->requireOwnedByUser($documentId, $userId);
            $document = $this->requireDocument($documentId);

            // Status gate
            $awaiting  = $this->requireStatusId('Awaiting Second Reading');
            $deferred  = $this->requireStatusId('Second Reading Deferred');
            $curStatus = (int) $document['current_status_id'];

            if ($action === 'DEFERRED' && $curStatus === $deferred) {
                // Re-processing a previously deferred doc is allowed
            } elseif (!in_array($curStatus, [$awaiting, $deferred], true)) {
                throw new RuntimeException(
                    'This document is not eligible for Second Reading processing at this time.'
                );
            }

            // Prevent duplicate APPROVED or WITHDRAWN
            $existingApproved = $this->pdo->prepare("
                SELECT id FROM plenary_readings
                WHERE document_id = ? AND reading_number = 2 AND action IN ('APPROVED','WITHDRAWN')
                LIMIT 1
            ");
            $existingApproved->execute([$documentId]);
            if ($existingApproved->fetch() && in_array($action, ['APPROVED', 'WITHDRAWN'], true)) {
                throw new RuntimeException('A final Second Reading decision has already been recorded.');
            }

            $readingDate = trim($_POST['reading_date'] ?? '');
            $remarks     = trim($_POST['remarks']      ?? '');

            // Insert plenary_readings row
            $this->pdo->prepare("
                INSERT INTO plenary_readings
                    (document_id, reading_number, action, reading_date, remarks, performed_by)
                VALUES (?, 2, ?, ?, ?, ?)
            ")->execute([$documentId, $action, $readingDate, $remarks ?: null, $userId]);
            $readingId = (int) $this->pdo->lastInsertId();

            switch ($action) {
                case 'APPROVED':
                    $this->doSecondReadingApproved($documentId, $userId, $document, $readingId);
                    break;
                case 'REFERRED_TO_COMMITTEE_SECOND':
                    $this->doReferToCommitteeSecondReading($documentId, $userId, $document);
                    break;
                case 'DEFERRED':
                    $this->doSecondReadingDeferred($documentId, $userId, $readingId, $stagedCompliance);
                    break;
                case 'WITHDRAWN':
                    $this->doSecondReadingWithdrawn($documentId, $userId, $document, $readingId);
                    break;
            }

            // Re-fetch final status
            $newStatusId = (int) $this->pdo->query(
                "SELECT current_status_id FROM documents WHERE id = {$documentId} LIMIT 1"
            )->fetchColumn();

            $eventMap = [
                'APPROVED'                       => 'PLENARY_SECOND_READING_APPROVED',
                'REFERRED_TO_COMMITTEE_SECOND'   => 'PLENARY_SECOND_READING_REFERRED_TO_COMMITTEE',
                'DEFERRED'                       => 'PLENARY_SECOND_READING_DEFERRED',
                'WITHDRAWN'                      => 'PLENARY_SECOND_READING_WITHDRAWN',
            ];
            $this->insertEvent($documentId, $eventMap[$action], $userId, $newStatusId, $remarks, [
                'action'       => $action,
                'reading_date' => $readingDate,
                'reading_id'   => $readingId,
            ]);

            audit_log('UPDATE', 'Document', (string) $documentId, null, [
                'action'       => 'plenary_second_reading',
                'sr_action'    => $action,
                'reading_date' => $readingDate,
            ], "Plenary Second Reading ({$action}) recorded for document ID {$documentId}");
        });

        $labels = [
            'APPROVED'                     => 'Document approved on Second Reading and finalized.',
            'REFERRED_TO_COMMITTEE_SECOND' => 'Document referred to Committee from Second Reading.',
            'DEFERRED'                     => 'Second Reading deferred.',
            'WITHDRAWN'                    => 'Document withdrawn from Second Reading.',
        ];
        flash_set('success', $labels[$action] ?? 'Second Reading action recorded.');
        redirect('plenary/second-reading');
    }

    // =========================================================================
    // SECOND READING — Return deferred document back to queue
    // =========================================================================

    public function secondReadingReturnDeferred(): void
    {
        $userId = auth_id();
        if ($userId === null) { flash_set('error', 'You must be logged in.'); redirect('login'); }

        $documentId = (int) ($_POST['document_id'] ?? 0);
        if ($documentId <= 0) { flash_set('error', 'Invalid document ID.'); redirect('plenary/second-reading'); }

        // Stage compliance file if provided
        $stagedCompliance = null;
        if (!empty($_FILES['compliance_file']['name'])) {
            $fileErrors = $this->validateSingleFile($_FILES['compliance_file']);
            if (!empty($fileErrors)) {
                flash_set('errors', $fileErrors);
                redirect('plenary/second-reading/show?id=' . $documentId);
            }
            $stagedCompliance = $this->stageSingleFile($_FILES['compliance_file'], $documentId);
        }

        $this->runTransaction(function () use ($documentId, $userId, $stagedCompliance) {
            $document = $this->requireDocument($documentId);
            $deferredStatusId = $this->requireStatusId('Second Reading Deferred');

            if ((int) $document['current_status_id'] !== $deferredStatusId) {
                throw new RuntimeException('Only deferred documents can be returned to Second Reading.');
            }

            // Require ownership (any active Plenary ACCEPTED assignment)
            $this->requireOwnedByUser($documentId, $userId);

            $awaitingStatusId = $this->requireStatusId('Awaiting Second Reading');

            // Update document status
            $this->pdo->prepare("
                UPDATE documents SET current_status_id = ?, updated_by = ? WHERE id = ?
            ")->execute([$awaitingStatusId, $userId, $documentId]);

            // Update the plenary_second_reading_details row for the last deferred reading
            $latestDeferred = $this->pdo->prepare("
                SELECT psrd.id
                FROM plenary_second_reading_details psrd
                INNER JOIN plenary_readings pr ON pr.id = psrd.reading_id
                WHERE psrd.document_id = ? AND pr.action = 'DEFERRED'
                ORDER BY psrd.id DESC
                LIMIT 1
            ");
            $latestDeferred->execute([$documentId]);
            $psrdRow = $latestDeferred->fetch();

            if ($psrdRow) {
                // Update returned_to_second_reading fields
                $update = "UPDATE plenary_second_reading_details
                           SET returned_to_second_reading_by = ?,
                               returned_to_second_reading_at = NOW()";
                $updateParams = [$userId];

                if ($stagedCompliance !== null) {
                    $update .= ", compliance_file_name = ?,
                                  compliance_file_path = ?,
                                  compliance_mime_type = ?,
                                  compliance_file_size = ?,
                                  compliance_uploaded_by = ?,
                                  compliance_uploaded_at = NOW()";
                    $updateParams[] = $stagedCompliance['original_name'];
                    $updateParams[] = $stagedCompliance['relative_path'];
                    $updateParams[] = $stagedCompliance['mime_type'];
                    $updateParams[] = $stagedCompliance['file_size'];
                    $updateParams[] = $userId;
                }

                $update .= " WHERE id = ?";
                $updateParams[] = (int) $psrdRow['id'];
                $this->pdo->prepare($update)->execute($updateParams);
            }

            $this->insertEvent($documentId, 'PLENARY_FIRST_READING_COMPLETED', $userId, $awaitingStatusId,
                'Deferred document returned to Second Reading queue.', [
                'action' => 'RETURN_FROM_DEFERRED',
            ]);
        });

        flash_set('success', 'Document returned to Second Reading queue.');
        redirect('plenary/second-reading/show?id=' . $documentId);
    }

    // =========================================================================
    // STATUS LIST (Finalized Documents)
    // =========================================================================

    public function statusList(): void
    {
        $userId = auth_id();
        if ($userId === null) { flash_set('error', 'You must be logged in.'); redirect('login'); }

        $search  = trim($_GET['search'] ?? '');
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        $approvedStatusId = $this->requireStatusId('Second Reading Approved');

        $whereParams = [$approvedStatusId];
        $whereExtra  = '';
        if ($search !== '') {
            $whereExtra   = " AND (d.tracking_number LIKE ? OR d.subject_matter LIKE ? OR psrd.approved_no LIKE ?)";
            $whereParams[] = "%{$search}%";
            $whereParams[] = "%{$search}%";
            $whereParams[] = "%{$search}%";
        }

        $countStmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM documents d
            LEFT JOIN plenary_readings pr ON pr.document_id = d.id AND pr.reading_number = 2 AND pr.action = 'APPROVED'
            LEFT JOIN plenary_second_reading_details psrd ON psrd.reading_id = pr.id
            WHERE d.current_status_id = ? {$whereExtra}
        ");
        $countStmt->execute($whereParams);
        $total      = (int) $countStmt->fetchColumn();
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page       = min($page, $totalPages);

        $listStmt = $this->pdo->prepare("
            SELECT
                d.id               AS document_id,
                d.tracking_number,
                d.subject_matter,
                d.date_received,
                d.finalized_at,
                ds.name            AS status,
                ds.badge_color     AS status_badge_color,
                dt.name            AS document_type_name,
                dt.badge_color     AS document_type_badge_color,
                pdd.proposed_number,
                pdd.session_type,
                -- Approval data
                psrd.approved_no,
                psrd.date_finalized,
                psrd.approved_subject_matter,
                -- First reading
                pr1.reading_date   AS first_reading_date,
                pr1.action         AS first_reading_action,
                -- Second reading
                pr2.reading_date   AS second_reading_date,
                -- Authors (aggregated)
                GROUP_CONCAT(
                    DISTINCT CONCAT_WS(' ',
                        NULLIF(sm.first_name,''), NULLIF(sm.last_name,''))
                    ORDER BY da.sort_order ASC SEPARATOR ', '
                ) AS author_names,
                -- Committees
                GROUP_CONCAT(
                    DISTINCT c.name ORDER BY c.name SEPARATOR ', '
                ) AS committee_names
            FROM documents d
            LEFT JOIN document_statuses            ds  ON ds.id  = d.current_status_id
            LEFT JOIN document_types               dt  ON dt.id  = d.document_type_id
            LEFT JOIN plenary_document_details     pdd ON pdd.document_id = d.id
            LEFT JOIN plenary_readings             pr1 ON pr1.document_id = d.id AND pr1.reading_number = 1
            LEFT JOIN plenary_readings             pr2 ON pr2.document_id = d.id AND pr2.reading_number = 2 AND pr2.action = 'APPROVED'
            LEFT JOIN plenary_second_reading_details psrd ON psrd.reading_id = pr2.id
            LEFT JOIN document_authors             da  ON da.document_id = d.id AND da.author_role = 'AUTHOR'
            LEFT JOIN sp_members                   sm  ON sm.sp_member_id = da.sp_member_id
            LEFT JOIN document_committees          dc  ON dc.document_id = d.id
            LEFT JOIN committees                   c   ON c.id = dc.committee_id
            WHERE d.current_status_id = ? {$whereExtra}
            GROUP BY
                d.id, d.tracking_number, d.subject_matter, d.date_received, d.finalized_at,
                ds.name, ds.badge_color, dt.name, dt.badge_color,
                pdd.proposed_number, pdd.session_type,
                psrd.approved_no, psrd.date_finalized, psrd.approved_subject_matter,
                pr1.reading_date, pr1.action, pr2.reading_date
            ORDER BY psrd.date_finalized DESC, d.id DESC
            LIMIT ? OFFSET ?
        ");
        $listStmt->execute([...$whereParams, $perPage, $offset]);
        $documents = $listStmt->fetchAll();

        $pageTitle = 'Finalized Documents';
        $success = flash_get('success');
        $error   = flash_get('error');
        require __DIR__ . '/../../../resources/views/plenary/status-list/index.php';
    }

    // =========================================================================
    // finalizeShow / finalizeStore — redirect stubs (finalization happens
    // through secondReadingStore action=APPROVED)
    // =========================================================================

    public function finalizeShow(): void
    {
        $documentId = (int) ($_GET['id'] ?? 0);
        if ($documentId > 0) {
            redirect('plenary/second-reading/show?id=' . $documentId);
        }
        redirect('plenary/second-reading');
    }

    public function finalizeStore(): void
    {
        redirect('plenary/second-reading');
    }

    // =========================================================================
    // Private — First Reading sub-actions
    // =========================================================================

    private function doReferToCommitteeFirstReading(int $documentId, int $userId, array $document): void
    {
        $committeeIds = array_filter(array_map('intval', (array) ($_POST['committee_ids'] ?? [])));

        // Validate committees
        if (!empty($committeeIds)) {
            $pl = implode(',', array_fill(0, count($committeeIds), '?'));
            $valid = $this->pdo->prepare(
                "SELECT id FROM committees WHERE id IN ({$pl}) AND is_active=1 AND is_deleted=0"
            );
            $valid->execute(array_values($committeeIds));
            if (count($valid->fetchAll()) !== count($committeeIds)) {
                throw new RuntimeException('One or more selected committees are invalid.');
            }

            // Replace document_committees
            $this->pdo->prepare("DELETE FROM document_committees WHERE document_id = ?")
                      ->execute([$documentId]);
            $ins = $this->pdo->prepare(
                "INSERT INTO document_committees (document_id, committee_id, assigned_by) VALUES (?,?,?)"
            );
            foreach (array_values($committeeIds) as $cid) {
                $ins->execute([$documentId, $cid, $userId]);
            }
        }

        // Get Committee role
        $committeeRole = $this->pdo->query(
            "SELECT id FROM roles WHERE name = 'Committee' AND is_active=1 AND is_deleted=0 LIMIT 1"
        )->fetch();
        if (!$committeeRole) {
            throw new RuntimeException('Committee role not found. Please configure roles.');
        }
        $committeeRoleId = (int) $committeeRole['id'];

        // Resolve "Referred" status (used by committee workflow)
        $referredStatus = $this->pdo->query(
            "SELECT id FROM document_statuses WHERE name = 'Referred' AND is_active=1 AND is_deleted=0 LIMIT 1"
        )->fetch();
        // Fallback to any status with "Referred" in name
        if (!$referredStatus) {
            $referredStatus = $this->pdo->query(
                "SELECT id FROM document_statuses WHERE name LIKE '%Refer%' AND is_active=1 AND is_deleted=0 ORDER BY id ASC LIMIT 1"
            )->fetch();
        }
        if (!$referredStatus) {
            throw new RuntimeException('"Referred" document status not found. Please run pending migrations.');
        }
        $referredStatusId = (int) $referredStatus['id'];

        // Update document: phase = COMMITTEE, status = Referred
        $this->pdo->prepare("
            UPDATE documents
            SET current_phase     = 'COMMITTEE',
                current_status_id = ?,
                updated_by        = ?
            WHERE id = ?
        ")->execute([$referredStatusId, $userId, $documentId]);

        // New PENDING Committee assignment
        $this->pdo->prepare("
            INSERT INTO document_assignments
                (document_id, assigned_to_role_id, phase, assigned_by, decision, received_at)
            VALUES (?, ?, 'COMMITTEE', ?, 'PENDING', NOW())
        ")->execute([$documentId, $committeeRoleId, $userId]);

        // Route record
        $this->pdo->prepare("
            INSERT INTO document_routes (document_id, from_phase, to_phase, routed_by, routed_to_role_id, remarks)
            VALUES (?, 'PLENARY', 'COMMITTEE', ?, ?, ?)
        ")->execute([$documentId, $userId, $committeeRoleId,
            trim($_POST['remarks'] ?? '') ?: 'Referred to Committee after First Reading.']);

        $this->insertEvent($documentId, 'PLENARY_REFERRED_TO_COMMITTEE', $userId, $referredStatusId,
            trim($_POST['remarks'] ?? '') ?: null, [
            'committee_ids' => $committeeIds,
            'source'        => 'first_reading',
        ]);
    }

    private function doAdoptedSuspendedRules(int $documentId, int $userId, array $document): void
    {
        $awaitingSecondId = $this->requireStatusId('Awaiting Second Reading');

        $this->pdo->prepare("
            UPDATE documents
            SET current_status_id = ?,
                updated_by        = ?
            WHERE id = ?
        ")->execute([$awaitingSecondId, $userId, $documentId]);
    }

    // =========================================================================
    // Private — Second Reading sub-actions
    // =========================================================================

    private function doSecondReadingApproved(int $documentId, int $userId, array $document, int $readingId): void
    {
        $approvedNo      = trim($_POST['approved_no']             ?? '');
        $dateFinalized   = trim($_POST['date_finalized']          ?? '');
        $approvedSubject = trim($_POST['approved_subject_matter'] ?? '');

        if ($approvedNo === '') {
            throw new InvalidArgumentException('Approved Number is required for Second Reading approval.');
        }
        if ($dateFinalized === '' || !$this->isValidDate($dateFinalized)) {
            throw new InvalidArgumentException('A valid Date Finalized is required.');
        }
        if ($approvedSubject === '') {
            // Fall back to original subject_matter
            $approvedSubject = $document['subject_matter'];
        }

        // Check uniqueness of approved_no
        $dup = $this->pdo->prepare(
            "SELECT id FROM plenary_second_reading_details WHERE approved_no = ? AND document_id != ? LIMIT 1"
        );
        $dup->execute([$approvedNo, $documentId]);
        if ($dup->fetch()) {
            throw new RuntimeException('Approved Number "' . htmlspecialchars($approvedNo) . '" is already used by another document.');
        }

        $approvedStatusId = $this->requireStatusId('Second Reading Approved');

        // Update document
        $this->pdo->prepare("
            UPDATE documents
            SET current_status_id = ?,
                current_phase     = 'FINALIZED',
                finalized_at      = NOW(),
                updated_by        = ?
            WHERE id = ?
        ")->execute([$approvedStatusId, $userId, $documentId]);

        // Insert/update plenary_second_reading_details
        $this->pdo->prepare("
            INSERT INTO plenary_second_reading_details
                (reading_id, document_id, approved_no, date_finalized, approved_subject_matter, created_by, updated_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                approved_no             = VALUES(approved_no),
                date_finalized          = VALUES(date_finalized),
                approved_subject_matter = VALUES(approved_subject_matter),
                updated_by              = VALUES(updated_by)
        ")->execute([$readingId, $documentId, $approvedNo, $dateFinalized, $approvedSubject, $userId, $userId]);

        // Upsert document_approvals (existing table from migration 043)
        $this->pdo->prepare("
            INSERT INTO document_approvals (document_id, approved_number, date_approved, approved_by)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                approved_number = VALUES(approved_number),
                date_approved   = VALUES(date_approved),
                approved_by     = VALUES(approved_by)
        ")->execute([$documentId, $approvedNo, $dateFinalized, $userId]);

        $this->insertEvent($documentId, 'PLENARY_FINALIZED', $userId, $approvedStatusId, null, [
            'approved_no'      => $approvedNo,
            'date_finalized'   => $dateFinalized,
        ]);
    }

    private function doReferToCommitteeSecondReading(int $documentId, int $userId, array $document): void
    {
        $committeeIds = array_filter(array_map('intval', (array) ($_POST['committee_ids'] ?? [])));
        if (empty($committeeIds)) {
            throw new InvalidArgumentException('At least one Committee must be selected for referral.');
        }

        $pl = implode(',', array_fill(0, count($committeeIds), '?'));
        $valid = $this->pdo->prepare(
            "SELECT id FROM committees WHERE id IN ({$pl}) AND is_active=1 AND is_deleted=0"
        );
        $valid->execute(array_values($committeeIds));
        if (count($valid->fetchAll()) !== count($committeeIds)) {
            throw new RuntimeException('One or more selected committees are invalid.');
        }

        // Update document_committees
        $this->pdo->prepare("DELETE FROM document_committees WHERE document_id = ?")
                  ->execute([$documentId]);
        $ins = $this->pdo->prepare(
            "INSERT INTO document_committees (document_id, committee_id, assigned_by) VALUES (?,?,?)"
        );
        foreach (array_values($committeeIds) as $cid) {
            $ins->execute([$documentId, $cid, $userId]);
        }

        $committeeRole = $this->pdo->query(
            "SELECT id FROM roles WHERE name = 'Committee' AND is_active=1 AND is_deleted=0 LIMIT 1"
        )->fetch();
        if (!$committeeRole) {
            throw new RuntimeException('Committee role not found.');
        }
        $committeeRoleId = (int) $committeeRole['id'];

        $referredStatus = $this->pdo->query(
            "SELECT id FROM document_statuses WHERE name = 'Referred' AND is_active=1 AND is_deleted=0 LIMIT 1"
        )->fetch();
        if (!$referredStatus) {
            throw new RuntimeException('"Referred" document status not found.');
        }

        $this->pdo->prepare("
            UPDATE documents
            SET current_phase     = 'COMMITTEE',
                current_status_id = ?,
                updated_by        = ?
            WHERE id = ?
        ")->execute([(int) $referredStatus['id'], $userId, $documentId]);

        $this->pdo->prepare("
            INSERT INTO document_assignments
                (document_id, assigned_to_role_id, phase, assigned_by, decision, received_at)
            VALUES (?, ?, 'COMMITTEE', ?, 'PENDING', NOW())
        ")->execute([$documentId, $committeeRoleId, $userId]);

        $this->pdo->prepare("
            INSERT INTO document_routes (document_id, from_phase, to_phase, routed_by, routed_to_role_id, remarks)
            VALUES (?, 'PLENARY', 'COMMITTEE', ?, ?, ?)
        ")->execute([$documentId, $userId, $committeeRoleId,
            trim($_POST['remarks'] ?? '') ?: 'Referred to Committee from Second Reading.']);
    }

    private function doSecondReadingDeferred(int $documentId, int $userId, int $readingId, ?array $staged): void
    {
        $dateDeferred = trim($_POST['date_deferred'] ?? '');
        $remarks      = trim($_POST['remarks']       ?? '');

        if ($dateDeferred === '' || !$this->isValidDate($dateDeferred)) {
            throw new InvalidArgumentException('A valid Date Deferred is required.');
        }

        $deferredStatusId = $this->requireStatusId('Second Reading Deferred');

        $this->pdo->prepare("
            UPDATE documents SET current_status_id = ?, updated_by = ? WHERE id = ?
        ")->execute([$deferredStatusId, $userId, $documentId]);

        $this->pdo->prepare("
            INSERT INTO plenary_second_reading_details
                (reading_id, document_id, date_deferred, deferred_remarks, created_by, updated_by)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                date_deferred    = VALUES(date_deferred),
                deferred_remarks = VALUES(deferred_remarks),
                updated_by       = VALUES(updated_by)
        ")->execute([$readingId, $documentId, $dateDeferred, $remarks ?: null, $userId, $userId]);

        // Attach compliance file if provided
        if ($staged !== null) {
            $this->pdo->prepare("
                UPDATE plenary_second_reading_details
                SET compliance_file_name    = ?,
                    compliance_file_path    = ?,
                    compliance_mime_type    = ?,
                    compliance_file_size    = ?,
                    compliance_uploaded_by  = ?,
                    compliance_uploaded_at  = NOW()
                WHERE reading_id = ?
            ")->execute([
                $staged['original_name'],
                $staged['relative_path'],
                $staged['mime_type'],
                $staged['file_size'],
                $userId,
                $readingId,
            ]);
        }
    }

    private function doSecondReadingWithdrawn(int $documentId, int $userId, array $document, int $readingId): void
    {
        $dateWithdrawn = trim($_POST['date_withdrawn'] ?? '');
        $remark        = trim($_POST['remarks']        ?? '');

        if ($dateWithdrawn === '' || !$this->isValidDate($dateWithdrawn)) {
            throw new InvalidArgumentException('A valid Date Withdrawn is required.');
        }
        if ($remark === '') {
            throw new InvalidArgumentException('A remark is required when withdrawing a document.');
        }

        $withdrawnStatusId = $this->requireStatusId('Withdrawn from Second Reading');

        $this->pdo->prepare("
            UPDATE documents SET current_status_id = ?, updated_by = ? WHERE id = ?
        ")->execute([$withdrawnStatusId, $userId, $documentId]);

        $this->pdo->prepare("
            INSERT INTO plenary_second_reading_details
                (reading_id, document_id, date_withdrawn, withdrawal_remark, created_by, updated_by)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                date_withdrawn  = VALUES(date_withdrawn),
                withdrawal_remark = VALUES(withdrawal_remark),
                updated_by      = VALUES(updated_by)
        ")->execute([$readingId, $documentId, $dateWithdrawn, $remark, $userId, $userId]);
    }

    // =========================================================================
    // Private — Committee return gate
    // =========================================================================

    /**
     * After Committee returns a document (status = "Returned to Plenary"),
     * and it previously had a First Reading with REFERRED_TO_COMMITTEE action,
     * the document can enter the Second Reading queue.
     *
     * CommitteeReportsController::returnToPlenary() already sets the status to
     * "Returned to Plenary". We need an additional step to advance it to
     * "Awaiting Second Reading" so it appears in the queue.
     *
     * This is called via POST plenary/second-reading/advance-from-committee.
     */
    public function advanceFromCommittee(): void
    {
        $userId = auth_id();
        if ($userId === null) { flash_set('error', 'You must be logged in.'); redirect('login'); }

        $documentId = (int) ($_POST['document_id'] ?? 0);
        if ($documentId <= 0) { flash_set('error', 'Invalid document ID.'); redirect('plenary/first-reading'); }

        $this->runTransaction(function () use ($documentId, $userId) {
            $document = $this->requireDocument($documentId);
            $returnedStatusId = $this->requireStatusId('Returned to Plenary');

            if ((int) $document['current_status_id'] !== $returnedStatusId) {
                throw new RuntimeException('This document has not been returned from Committee yet.');
            }

            // Must have a First Reading referral
            $frRow = $this->pdo->prepare("
                SELECT id FROM plenary_readings
                WHERE document_id = ? AND reading_number = 1
                  AND action IN ('REFERRED_TO_COMMITTEE', 'REFERRED_TO_COMMITTEE_SECOND')
                LIMIT 1
            ");
            $frRow->execute([$documentId]);
            if (!$frRow->fetch()) {
                throw new RuntimeException('No Committee referral found for this document.');
            }

            $awaitingStatusId = $this->requireStatusId('Awaiting Second Reading');

            $this->pdo->prepare("
                UPDATE documents
                SET current_status_id = ?,
                    current_phase     = 'PLENARY',
                    updated_by        = ?
                WHERE id = ?
            ")->execute([$awaitingStatusId, $userId, $documentId]);

            $this->insertEvent($documentId, 'PLENARY_SECOND_READING_APPROVED', $userId, $awaitingStatusId,
                'Document advanced to Second Reading queue after Committee return.', [
                'action' => 'ADVANCE_FROM_COMMITTEE',
            ]);
        });

        flash_set('success', 'Document moved to Second Reading queue.');
        redirect('plenary/second-reading');
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function requireDocument(int $documentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT d.*,
                   ds.name        AS status_name,
                   ds.badge_color AS status_badge_color,
                   dt.name        AS document_type_name,
                   dt.badge_color AS document_type_badge_color
            FROM documents d
            LEFT JOIN document_statuses ds ON ds.id = d.current_status_id
            LEFT JOIN document_types    dt ON dt.id = d.document_type_id
            WHERE d.id = ?
            LIMIT 1
        ");
        $stmt->execute([$documentId]);
        $doc = $stmt->fetch();
        if (!$doc) {
            throw new RuntimeException('Document not found.');
        }
        return $doc;
    }

    private function requireStatusId(string $name): int
    {
        $row = $this->pdo->prepare(
            "SELECT id FROM document_statuses WHERE name = ? AND is_active=1 AND is_deleted=0 LIMIT 1"
        );
        $row->execute([$name]);
        $r = $row->fetch();
        if (!$r) {
            throw new RuntimeException("Document status '{$name}' not found. Please run pending migrations.");
        }
        return (int) $r['id'];
    }

    private function isOwnedByUser(int $documentId, int $userId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT id FROM document_assignments
            WHERE document_id         = ?
              AND phase               = 'PLENARY'
              AND decision            = 'ACCEPTED'
              AND completed_at        IS NULL
              AND (accepted_by = ? OR assigned_to_user_id = ?)
            LIMIT 1
        ");
        $stmt->execute([$documentId, $userId, $userId]);
        return (bool) $stmt->fetch();
    }

    private function requireOwnedByUser(int $documentId, int $userId): void
    {
        if (!$this->isOwnedByUser($documentId, $userId)) {
            throw new RuntimeException(
                'You do not have ownership of this document. Only the Plenary user who accepted it may perform this action.'
            );
        }
    }

    private function fetchPlenaryDetails(int $documentId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT pdd.*, ds.name AS plenary_status_name, ds.badge_color AS plenary_status_badge_color
            FROM plenary_document_details pdd
            LEFT JOIN document_statuses ds ON ds.id = pdd.status_id
            WHERE pdd.document_id = ?
            LIMIT 1
        ");
        $stmt->execute([$documentId]);
        return $stmt->fetch() ?: null;
    }

    private function fetchAuthors(int $documentId, string $role): array
    {
        $stmt = $this->pdo->prepare("
            SELECT da.*, sm.sp_member_id,
                   CONCAT_WS(' ', NULLIF(sm.first_name,''), NULLIF(sm.middle_name,''),
                                  NULLIF(sm.last_name,''), NULLIF(sm.suffix,'')) AS full_name
            FROM document_authors da
            INNER JOIN sp_members sm ON sm.sp_member_id = da.sp_member_id
            WHERE da.document_id = ? AND da.author_role = ?
            ORDER BY da.sort_order ASC
        ");
        $stmt->execute([$documentId, $role]);
        return $stmt->fetchAll();
    }

    private function fetchDocumentCommittees(int $documentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT dc.*, c.name AS committee_name
            FROM document_committees dc
            INNER JOIN committees c ON c.id = dc.committee_id
            WHERE dc.document_id = ?
            ORDER BY c.name ASC
        ");
        $stmt->execute([$documentId]);
        return $stmt->fetchAll();
    }

    private function fetchCommittees(): array
    {
        return $this->pdo->query(
            "SELECT id, name FROM committees WHERE is_active=1 AND is_deleted=0 ORDER BY name ASC"
        )->fetchAll();
    }

    private function fetchEvents(int $documentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT de.*, ua.username AS performed_by_username, ds.name AS to_status_name
            FROM document_events de
            LEFT JOIN user_accounts ua ON ua.id = de.performed_by
            LEFT JOIN document_statuses ds ON ds.id = de.to_status_id
            WHERE de.document_id = ?
            ORDER BY de.created_at DESC
        ");
        $stmt->execute([$documentId]);
        return $stmt->fetchAll();
    }

    private function fetchReading(int $documentId, int $readingNumber): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT pr.*,
                   ua.username AS performed_by_username,
                   CONCAT_WS(' ', NULLIF(ui.first_name,''), NULLIF(ui.last_name,'')) AS performed_by_name
            FROM plenary_readings pr
            LEFT JOIN user_accounts ua ON ua.id = pr.performed_by
            LEFT JOIN user_info     ui ON ui.user_account_id = pr.performed_by
            WHERE pr.document_id = ? AND pr.reading_number = ?
            ORDER BY pr.id DESC
            LIMIT 1
        ");
        $stmt->execute([$documentId, $readingNumber]);
        return $stmt->fetch() ?: null;
    }

    private function fetchSecondReadingDetails(int $readingId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM plenary_second_reading_details WHERE reading_id = ? LIMIT 1"
        );
        $stmt->execute([$readingId]);
        return $stmt->fetch() ?: null;
    }

    private function fetchSecondReadingTabCounts(): array
    {
        $counts = ['pending' => 0, 'deferred' => 0, 'withdrawn' => 0, 'approved' => 0];
        $map = [
            'pending'   => 'Awaiting Second Reading',
            'deferred'  => 'Second Reading Deferred',
            'withdrawn' => 'Withdrawn from Second Reading',
            'approved'  => 'Second Reading Approved',
        ];
        foreach ($map as $key => $name) {
            $row = $this->pdo->prepare("
                SELECT COUNT(*) FROM documents d
                INNER JOIN document_statuses ds ON ds.id = d.current_status_id
                WHERE ds.name = ? AND ds.is_active = 1 AND ds.is_deleted = 0
            ");
            $row->execute([$name]);
            $counts[$key] = (int) $row->fetchColumn();
        }
        return $counts;
    }

    private function insertEvent(int $documentId, string $eventType, int $userId, int $statusId, ?string $remarks, array $meta = []): void
    {
        $meta['ip_address'] = client_ip();
        $meta['user_agent'] = client_user_agent();

        $this->pdo->prepare("
            INSERT INTO document_events
                (document_id, event_type, phase, performed_by, to_status_id, remarks, metadata)
            VALUES (?, ?, 'PLENARY', ?, ?, ?, ?)
        ")->execute([
            $documentId,
            $eventType,
            $userId,
            $statusId,
            $remarks,
            json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function isValidDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        return checkdate(
            (int) substr($date, 5, 2),
            (int) substr($date, 8, 2),
            (int) substr($date, 0, 4)
        );
    }

    private function validateSecondReadingPost(string $action): array
    {
        $errors = [];
        $readingDate = trim($_POST['reading_date'] ?? '');

        if ($readingDate === '' || !$this->isValidDate($readingDate)) {
            $errors[] = 'A valid Reading Date is required.';
        }

        switch ($action) {
            case 'APPROVED':
                if (trim($_POST['approved_no'] ?? '') === '') {
                    $errors[] = 'Approved Number is required.';
                }
                $df = trim($_POST['date_finalized'] ?? '');
                if ($df === '' || !$this->isValidDate($df)) {
                    $errors[] = 'A valid Date Finalized is required.';
                }
                break;
            case 'REFERRED_TO_COMMITTEE_SECOND':
                $cids = array_filter(array_map('intval', (array) ($_POST['committee_ids'] ?? [])));
                if (empty($cids)) {
                    $errors[] = 'At least one Committee must be selected.';
                }
                break;
            case 'DEFERRED':
                $dd = trim($_POST['date_deferred'] ?? '');
                if ($dd === '' || !$this->isValidDate($dd)) {
                    $errors[] = 'A valid Date Deferred is required.';
                }
                break;
            case 'WITHDRAWN':
                $dw = trim($_POST['date_withdrawn'] ?? '');
                if ($dw === '' || !$this->isValidDate($dw)) {
                    $errors[] = 'A valid Date Withdrawn is required.';
                }
                if (trim($_POST['remarks'] ?? '') === '') {
                    $errors[] = 'A remark is required when withdrawing.';
                }
                break;
        }

        return $errors;
    }

    private function validateSingleFile(array $file): array
    {
        $errors = [];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload error.';
            return $errors;
        }
        $maxSize = 25 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            $errors[] = 'File exceeds the maximum size of 25 MB.';
        }
        $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            $errors[] = 'Invalid file type. Allowed: ' . implode(', ', $allowed) . '.';
        }
        return $errors;
    }

    private function stageSingleFile(array $file, int $documentId): array
    {
        $uploadDir = __DIR__ . '/../../../public/uploads/documents/' . $documentId . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $ext         = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $storedName  = 'compliance_' . $documentId . '_' . uniqid('', true) . '.' . $ext;
        $absPath     = $uploadDir . $storedName;
        $relPath     = 'uploads/documents/' . $documentId . '/' . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            throw new RuntimeException('Failed to upload compliance file.');
        }

        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $absPath);
        finfo_close($finfo);

        return [
            'abs_path'      => $absPath,
            'relative_path' => $relPath,
            'original_name' => basename($file['name']),
            'mime_type'     => $mimeType,
            'file_size'     => (int) filesize($absPath),
        ];
    }

    /**
     * Run a callable inside a PDO transaction with retry on deadlock/lock timeout.
     */
    private function runTransaction(callable $fn): void
    {
        $lastException = null;
        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $this->pdo->beginTransaction();
                $fn();
                $this->pdo->commit();
                return;
            } catch (InvalidArgumentException | RuntimeException $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                $lastException = $e;
                break; // business rule error — no retry
            } catch (PDOException $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                $errorCode = (int) ($e->errorInfo[1] ?? 0);
                if (in_array($errorCode, [1205, 1213], true) && $attempt < self::MAX_RETRIES) {
                    usleep(self::RETRY_DELAY);
                    continue;
                }
                $lastException = $e;
                break;
            } catch (Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                $lastException = $e;
                break;
            }
        }

        if ($lastException !== null) {
            system_log('ERROR', 'PlenaryReadingController transaction failed', [
                'error' => $lastException->getMessage(),
            ]);
            flash_set('error', $lastException->getMessage());
            // Re-read document_id from POST to redirect back
            $documentId = (int) ($_POST['document_id'] ?? 0);
            if ($documentId > 0) {
                redirect('plenary/first-reading/show?id=' . $documentId);
            }
            redirect('plenary/first-reading');
        }
    }
}

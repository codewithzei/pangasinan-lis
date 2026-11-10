<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/DocumentService.php';

/**
 * CommitteeCasesController
 *
 * Handles the Committee Cases sub-workflow for documents whose document type
 * is "Administrative Cases" or "Complaint".
 *
 * ROUTE MAP
 * ─────────────────────────────────────────────────────────────────────────────
 *  GET  committee/cases/create?document_id={id}   create()         — case form
 *  POST committee/cases/create                    store()          — persist case
 *  GET  committee/cases/show?id={case_id}         show()           — case details
 *  POST committee/cases/actions/store             storeAction()    — add timeline action
 *  POST committee/cases/outcome                   outcomeStore()   — set final outcome
 *  GET  committee/cases/for-report                forReport()      — Approved / For Report list
 *  GET  committee/cases/deferred                  deferred()       — Deferred list
 *  GET  committee/cases/withdrawn                 withdrawn()      — Withdrawn list
 *  GET  committee/cases/noted                     noted()          — Noted list
 *  GET  committee/cases/report?case_id={id}       caseReportShow() — Create Report form
 *  POST committee/cases/report                    caseReportStore()— Persist Report
 *
 * AUTHORIZATION
 * ─────────────────────────────────────────────────────────────────────────────
 *  • All routes: AuthMiddleware + RoleMiddleware (Committee / Super Admin).
 *  • create() / store(): The requesting user must be the Committee user who
 *    accepted the document assignment (accepted_by = auth_id()).
 *  • show() / storeAction(): The requesting user must be the Committee user
 *    who created the case record (assigned_by = auth_id()).
 *  • Re-checked server-side on every write; never trusts the client.
 *
 * DOCKET-NUMBER ENTRY
 * ─────────────────────────────────────────────────────────────────────────────
 *  The docket number is now entered manually by the committee user when
 *  creating a case (store()) or when completing pending case details
 *  (detailsStore()). The system validates:
 *    • Required (non-empty).
 *    • Max 100 characters.
 *    • Unique — checked against committee_cases.docket_number before INSERT
 *      and again by the UNIQUE KEY uq_docket_number at the database level.
 *  Duplicate-key violations (MySQL error 1062) produce a clear error message
 *  that is redisplayed on the form; the user is never silently given a
 *  different number. No automatic generation occurs anywhere in this controller.
 *
 *  Cases docketed via "Proceed to Cases" from the Committee Inbox
 *  (CommitteeInboxController::doProceedToCases()) are created with an empty
 *  string as the docket_number placeholder. The committee user must enter the
 *  docket number from the Case Details Required section of the show page
 *  (detailsStore()) before proceeding to Summary of Proceedings or Final Outcome.
 *
 * ELIGIBLE DOCUMENT TYPES
 * ─────────────────────────────────────────────────────────────────────────────
 *  Only documents whose document_types.name is one of:
 *    • 'Administrative Cases'
 *    • 'Complaint'
 *  may have a case record created. The check is enforced in both create() and
 *  store() so neither direct URL access nor a crafted POST can bypass it.
 */
class CommitteeCasesController
{
    protected PDO             $pdo;
    protected DocumentService $docService;

    /** Document type names that are eligible for the Cases workflow. */
    private const ELIGIBLE_TYPES = ['Administrative Cases', 'Complaint'];

    public function __construct()
    {
        $database         = new Database();
        $this->pdo        = $database->connect();
        $this->docService = new DocumentService();
    }

    // =========================================================================
    // 0. Cases list / index  GET committee/cases
    // =========================================================================

    public function index(): void
    {
        $userId = $this->requireAuth();
        $committeeRoleId = $this->requireCommitteeRoleId();

        // Filters
        $search = trim($_GET['search'] ?? '');

        // Pagination
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        // Base WHERE: only cases whose creating assignment belongs to this user
        // (the Committee member who accepted and docketed the document).
        // The column that stores the creator is `assigned_by` in committee_cases.
        $where  = ['cc.assigned_by = ?', 'cc.final_outcome IS NULL'];
        $params = [$userId];

        if ($search !== '') {
            $where[]  = '(cc.docket_number LIKE ? OR d.tracking_number LIKE ? OR d.subject_matter LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $whereClause = implode(' AND ', $where);

        // Total count for pagination
        $countStmt = $this->pdo->prepare("
            SELECT COUNT(*) AS total
            FROM committee_cases cc
            INNER JOIN documents d ON cc.document_id = d.id
            WHERE {$whereClause}
        ");
        $countStmt->execute($params);
        $total      = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        $totalPages = max(1, (int) ceil($total / $perPage));

        // Fetch page
        $listStmt = $this->pdo->prepare("
            SELECT
                cc.id,
                cc.docket_number,
                cc.date_assigned,
                cc.complainant_details,
                cc.complainant_municipality,
                cc.respondents,
                cc.nature_of_case,
                cc.created_at,
                d.id              AS document_id,
                d.tracking_number,
                d.subject_matter,
                d.source_name,
                dt.name           AS document_type_name,
                dt.badge_color    AS document_type_badge_color,
                ds.name           AS status,
                ds.badge_color    AS status_badge_color,
                (
                    -- A case handed over by Proceed to Cases is docketed with
                    -- blank identifying details (including docket_number) that
                    -- are entered from the Cases show page.
                    COALESCE(TRIM(cc.docket_number), '') = ''
                    OR TRIM(cc.nature_of_case) = ''
                    OR TRIM(cc.respondents) = ''
                )                 AS details_pending,
                (
                    SELECT COUNT(*)
                    FROM committee_case_actions cca
                    WHERE cca.case_id = cc.id
                ) AS action_count
            FROM committee_cases cc
            INNER JOIN documents         d  ON cc.document_id        = d.id
            LEFT  JOIN document_types    dt ON d.document_type_id    = dt.id
            LEFT  JOIN document_statuses ds ON d.current_status_id   = ds.id
            WHERE {$whereClause}
            ORDER BY cc.created_at DESC, cc.id DESC
            LIMIT ? OFFSET ?
        ");
        $listStmt->execute([...$params, $perPage, $offset]);
        $cases = $listStmt->fetchAll(PDO::FETCH_ASSOC);

        // Summary stats for this user
        $statsStmt = $this->pdo->prepare("
            SELECT COUNT(*) AS total_cases
            FROM committee_cases cc
            WHERE cc.assigned_by = ?
              AND cc.final_outcome IS NULL
        ");
        $statsStmt->execute([$userId]);
        $totalCases = (int) $statsStmt->fetch(PDO::FETCH_ASSOC)['total_cases'];

        // (The ?complete= query param was previously used to auto-open a modal.
        // It is now ignored — case details are entered from the show page.)

        $pageTitle = 'Committee Cases';
        require __DIR__ . '/../../../resources/views/committee/cases/index.php';
    }

    // =========================================================================
    // 1. Case creation form  GET committee/cases/create?document_id={id}
    // =========================================================================

    public function create(): void
    {
        $userId     = $this->requireAuth();
        $documentId = (int) ($_GET['document_id'] ?? 0);

        if ($documentId <= 0) {
            flash_set('error', 'Invalid document ID.');
            redirect('committee/inbox');
        }

        [$document, $assignment] = $this->requireEligibleOwnedDocument($documentId, $userId);

        // Guard: case must not exist yet.
        if ($this->caseExistsForDocument($documentId)) {
            $existing = $this->getCaseByDocumentId($documentId);
            flash_set('error', 'A case record already exists for this document.');
            redirect('committee/cases/show?id=' . (int) $existing['id']);
        }

        $old     = old_get();

        $pageTitle = 'Create Case — ' . htmlspecialchars($document['tracking_number']);
        require __DIR__ . '/../../../resources/views/committee/cases/create.php';
    }

    // =========================================================================
    // 2. Persist new case  POST committee/cases/create
    // =========================================================================

    public function store(): void
    {
        $userId     = $this->requireAuth();
        $documentId = (int) ($_POST['document_id'] ?? 0);

        if ($documentId <= 0) {
            flash_set('error', 'Invalid document ID.');
            redirect('committee/inbox');
        }

        [$document, $assignment] = $this->requireEligibleOwnedDocument($documentId, $userId);

        // Guard: duplicate case.
        if ($this->caseExistsForDocument($documentId)) {
            $existing = $this->getCaseByDocumentId($documentId);
            flash_set('error', 'A case record already exists for this document.');
            redirect('committee/cases/show?id=' . (int) $existing['id']);
        }

        // ── Collect + validate inputs ─────────────────────────────────────────
        $docketNumber            = trim($_POST['docket_number']            ?? '');
        $dateAssigned            = trim($_POST['date_assigned']            ?? '');
        $natureOfCase            = trim($_POST['nature_of_case']           ?? '');
        $complainantDetails      = trim($_POST['complainant_details']      ?? '');
        $complainantMunicipality = trim($_POST['complainant_municipality'] ?? '');
        $respondents             = trim($_POST['respondents']              ?? '');

        $errors = [];

        if ($docketNumber === '') {
            $errors[] = 'Docket number is required.';
        } elseif (mb_strlen($docketNumber) > 100) {
            $errors[] = 'Docket number must not exceed 100 characters.';
        }

        if ($dateAssigned === '') {
            $errors[] = 'Date assigned is required.';
        } elseif (!$this->isValidDate($dateAssigned)) {
            $errors[] = 'Date assigned is not a valid date.';
        }

        if ($natureOfCase === '') {
            $errors[] = 'Nature of case is required.';
        } elseif (mb_strlen($natureOfCase) > 10000) {
            $errors[] = 'Nature of case must not exceed 10 000 characters.';
        }

        if ($complainantDetails === '') {
            $errors[] = 'Complainant details are required.';
        } elseif (mb_strlen($complainantDetails) > 5000) {
            $errors[] = 'Complainant details must not exceed 5 000 characters.';
        }

        if (mb_strlen($complainantMunicipality) > 255) {
            $errors[] = 'Complainant municipality must not exceed 255 characters.';
        }

        if ($respondents === '') {
            $errors[] = 'Respondents are required.';
        } elseif (mb_strlen($respondents) > 5000) {
            $errors[] = 'Respondents must not exceed 5 000 characters.';
        }

        // Check for duplicate docket number only when the field itself is valid.
        if (empty($errors) || ($docketNumber !== '' && mb_strlen($docketNumber) <= 100)) {
            if ($docketNumber !== '') {
                $dupCheckStmt = $this->pdo->prepare(
                    "SELECT id FROM committee_cases WHERE docket_number = ? LIMIT 1"
                );
                $dupCheckStmt->execute([$docketNumber]);
                if ($dupCheckStmt->fetch()) {
                    $errors[] = "Docket number \"{$docketNumber}\" is already in use. Please enter a different docket number.";
                }
            }
        }

        if (!empty($errors)) {
            old_set($_POST);
            flash_set('errors', $errors);
            redirect('committee/cases/create?document_id=' . $documentId);
        }

        // ── Transactional case creation ───────────────────────────────────────
        $caseId = null;

        try {
            $this->pdo->beginTransaction();

            // Insert the case record. docket_year and docket_sequence are NULL
            // for manually-entered docket numbers (migration 079 made them
            // nullable). The UNIQUE constraint on docket_number still guards
            // against duplicates at the database level.
            $this->pdo->prepare("
                INSERT INTO committee_cases (
                    document_id,
                    docket_year,
                    docket_sequence,
                    docket_number,
                    date_assigned,
                    nature_of_case,
                    complainant_details,
                    complainant_municipality,
                    respondents,
                    assigned_by
                ) VALUES (?, NULL, NULL, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $documentId,
                $docketNumber,
                $dateAssigned,
                $natureOfCase,
                $complainantDetails,
                $complainantMunicipality ?: null,
                $respondents,
                $userId,
            ]);

            $caseId = (int) $this->pdo->lastInsertId();

            // 6. Advance the document out of the Committee Inbox.
            //    Docketing always moves a Complaint / Administrative Cases
            //    document into the Cases workflow, so its status becomes
            //    "Case Docketed". If that status row is unavailable (migration
            //    not applied yet) the current status is kept — the Committee
            //    Inbox still excludes the document because a committee_cases
            //    row now exists for it.
            $docketedStatusStmt = $this->pdo->prepare("
                SELECT id FROM document_statuses
                WHERE name = 'Case Docketed' AND is_active = 1 AND is_deleted = 0
                LIMIT 1
            ");
            $docketedStatusStmt->execute();
            $docketedStatusId = (int) $docketedStatusStmt->fetchColumn();

            $newStatusId = $docketedStatusId > 0
                ? $docketedStatusId
                : (int) ($document['current_status_id'] ?? 0);

            if ($newStatusId > 0) {
                $this->pdo->prepare("
                    UPDATE documents
                    SET current_status_id = ?, updated_by = ?
                    WHERE id = ?
                ")->execute([$newStatusId, $userId, $documentId]);
            }

            // 7. Workflow event.
            $this->pdo->prepare("
                INSERT INTO document_events (
                    document_id, event_type, phase, performed_by,
                    to_status_id, remarks, metadata
                ) VALUES (?, 'COMMITTEE_CASE_CREATED', 'COMMITTEE', ?, ?, ?, ?)
            ")->execute([
                $documentId,
                $userId,
                $newStatusId ?: null,
                "Case docketed: {$docketNumber}",
                json_encode([
                    'case_id'      => $caseId,
                    'docket_number'=> $docketNumber,
                    'date_assigned'=> $dateAssigned,
                    'ip_address'   => client_ip(),
                    'user_agent'   => client_user_agent(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            $this->pdo->commit();

        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            // Duplicate-key violation on docket_number or uq_document_id.
            $errorInfo = $e->errorInfo[1] ?? null;
            if ($errorInfo === 1062) {
                system_log('WARNING', 'Duplicate docket number or document_id on committee case insert', [
                    'document_id'  => $documentId,
                    'user_id'      => $userId,
                    'docket_number'=> $docketNumber,
                    'error'        => $e->getMessage(),
                ]);
                old_set($_POST);
                flash_set('errors', ["Docket number \"{$docketNumber}\" is already in use. Please enter a different docket number."]);
                redirect('committee/cases/create?document_id=' . $documentId);
            }
            system_log('ERROR', 'Committee case store failed (PDO)', [
                'document_id' => $documentId,
                'user_id'     => $userId,
                'error'       => $e->getMessage(),
            ]);
            flash_set('error', 'Database error while creating case: ' . $e->getMessage());
            redirect('committee/cases/create?document_id=' . $documentId);

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            system_log('ERROR', 'Committee case store failed', [
                'document_id' => $documentId,
                'user_id'     => $userId,
                'error'       => $e->getMessage(),
            ]);
            flash_set('error', 'Failed to create case: ' . $e->getMessage());
            redirect('committee/cases/create?document_id=' . $documentId);
        }

        // ── Post-commit: audit log ────────────────────────────────────────────
        $uStmt = $this->pdo->prepare("SELECT username FROM user_accounts WHERE id = ? LIMIT 1");
        $uStmt->execute([$userId]);
        $username = (string) ($uStmt->fetchColumn() ?: '');

        audit_log('CREATE', 'CommitteeCase', (string) $caseId, null, [
            'case_id'       => $caseId,
            'document_id'   => $documentId,
            'docket_number' => $docketNumber,
            'date_assigned' => $dateAssigned,
            'created_by'    => $userId,
            'created_by_username' => $username,
        ], "Committee case created: {$docketNumber} for document ID {$documentId} (user: {$username})");

        old_clear();
        flash_set('success', "Case docketed successfully. Docket No.: {$docketNumber}");
        redirect('committee/cases/show?id=' . $caseId);
    }

    // =========================================================================
    // 2b. Complete / update case details  POST committee/cases/details
    // =========================================================================

    /**
     * Save the case details for a case that was handed over by the Committee
     * Inbox "Proceed to Cases" action.
     *
     * "Proceed to Cases" dockets the case immediately (so the document leaves
     * the Inbox and appears on the Cases page) but leaves the identifying
     * details blank — including the docket number, which is entered here.
     * This action is the Cases page counterpart of store(): it records the
     * docket number, nature of case, complainant details, and respondents for
     * an existing case. It never creates a new case row.
     */
    public function detailsStore(): void
    {
        $userId = $this->requireAuth();
        $caseId = (int) ($_POST['case_id'] ?? 0);

        if ($caseId <= 0) {
            flash_set('error', 'Invalid case ID.');
            redirect('committee/cases');
        }

        $case = $this->loadCaseWithDocument($caseId);
        if ($case === null) {
            flash_set('error', 'Case not found.');
            redirect('committee/cases');
        }

        $this->requireCaseOwnership($case, $userId);

        // ── Collect + validate inputs ─────────────────────────────────────────
        // The "Proceed to Cases" inbox path inserts a blank docket_number.
        // The user must supply it here. If the case already has a non-empty
        // docket_number the user can also update it (correcting a typo in the
        // placeholder stored by the inbox action). Uniqueness is checked only
        // when the submitted value differs from the current stored value so
        // an unchanged round-trip does not report a spurious duplicate.
        $docketNumber            = trim($_POST['docket_number']            ?? '');
        $dateAssigned            = trim($_POST['date_assigned']            ?? '');
        $natureOfCase            = trim($_POST['nature_of_case']           ?? '');
        $complainantDetails      = trim($_POST['complainant_details']      ?? '');
        $complainantMunicipality = trim($_POST['complainant_municipality'] ?? '');
        $respondents             = trim($_POST['respondents']              ?? '');

        $existingDocketNumber = (string) ($case['docket_number'] ?? '');

        $errors = [];

        if ($docketNumber === '') {
            $errors[] = 'Docket number is required.';
        } elseif (mb_strlen($docketNumber) > 100) {
            $errors[] = 'Docket number must not exceed 100 characters.';
        }

        if ($dateAssigned === '') {
            $errors[] = 'Date assigned is required.';
        } elseif (!$this->isValidDate($dateAssigned)) {
            $errors[] = 'Date assigned is not a valid date.';
        }

        if ($natureOfCase === '') {
            $errors[] = 'Nature of case is required.';
        } elseif (mb_strlen($natureOfCase) > 10000) {
            $errors[] = 'Nature of case must not exceed 10 000 characters.';
        }

        if ($complainantDetails === '') {
            $errors[] = 'Complainant details are required.';
        } elseif (mb_strlen($complainantDetails) > 5000) {
            $errors[] = 'Complainant details must not exceed 5 000 characters.';
        }

        if (mb_strlen($complainantMunicipality) > 255) {
            $errors[] = 'Complainant municipality must not exceed 255 characters.';
        }

        if ($respondents === '') {
            $errors[] = 'Respondents are required.';
        } elseif (mb_strlen($respondents) > 5000) {
            $errors[] = 'Respondents must not exceed 5 000 characters.';
        }

        // Duplicate docket check — only when the value is non-empty, valid,
        // and differs from the currently stored value for this case.
        if ($docketNumber !== '' && mb_strlen($docketNumber) <= 100) {
            if ($docketNumber !== $existingDocketNumber) {
                $dupCheckStmt = $this->pdo->prepare(
                    "SELECT id FROM committee_cases WHERE docket_number = ? AND id != ? LIMIT 1"
                );
                $dupCheckStmt->execute([$docketNumber, $caseId]);
                if ($dupCheckStmt->fetch()) {
                    $errors[] = "Docket number \"{$docketNumber}\" is already in use. Please enter a different docket number.";
                }
            }
        }

        if (!empty($errors)) {
            old_set($_POST);
            flash_set('errors', $errors);
            redirect('committee/cases/show?id=' . $caseId);
        }

        // ── Persist ───────────────────────────────────────────────────────────
        try {
            $this->pdo->beginTransaction();

            $this->pdo->prepare("
                UPDATE committee_cases
                SET docket_number            = ?,
                    date_assigned            = ?,
                    nature_of_case           = ?,
                    complainant_details      = ?,
                    complainant_municipality = ?,
                    respondents              = ?
                WHERE id = ?
            ")->execute([
                $docketNumber,
                $dateAssigned,
                $natureOfCase,
                $complainantDetails,
                $complainantMunicipality ?: null,
                $respondents,
                $caseId,
            ]);

            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if (($e->errorInfo[1] ?? null) === 1062) {
                system_log('WARNING', 'Duplicate docket number on committee case details update', [
                    'case_id'      => $caseId,
                    'user_id'      => $userId,
                    'docket_number'=> $docketNumber,
                    'error'        => $e->getMessage(),
                ]);
                old_set($_POST);
                flash_set('errors', ["Docket number \"{$docketNumber}\" is already in use. Please enter a different docket number."]);
                redirect('committee/cases/show?id=' . $caseId);
            }
            system_log('ERROR', 'Committee case details update failed', [
                'case_id' => $caseId,
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            flash_set('error', 'Failed to save case details: ' . $e->getMessage());
            redirect('committee/cases/show?id=' . $caseId);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            system_log('ERROR', 'Committee case details update failed', [
                'case_id' => $caseId,
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            flash_set('error', 'Failed to save case details: ' . $e->getMessage());
            redirect('committee/cases/show?id=' . $caseId);
        }

        // ── Post-commit: audit log ────────────────────────────────────────────
        audit_log('UPDATE', 'CommitteeCase', (string) $caseId, null, [
            'case_id'               => $caseId,
            'document_id'           => (int) ($case['document_id'] ?? 0),
            'docket_number'         => $docketNumber,
            'previous_docket_number'=> $existingDocketNumber !== $docketNumber ? $existingDocketNumber : null,
            'date_assigned'         => $dateAssigned,
            'updated_by'            => $userId,
        ], "Committee case details saved for docket {$docketNumber} (user ID {$userId})");

        old_clear();
        flash_set('success', "Case details saved. Docket No.: {$docketNumber}");
        redirect('committee/cases/show?id=' . $caseId);
    }

    // =========================================================================
    // 3. Case detail / timeline  GET committee/cases/show?id={case_id}
    // =========================================================================

    public function show(): void
    {
        $userId = $this->requireAuth();
        $caseId = (int) ($_GET['id'] ?? 0);

        if ($caseId <= 0) {
            flash_set('error', 'Invalid case ID.');
            redirect('committee/inbox');
        }

        // Load the case with its linked document.
        $case = $this->loadCaseWithDocument($caseId);

        if (!$case) {
            flash_set('error', 'Case not found.');
            redirect('committee/inbox');
        }

        // Ownership check: only the user who created the case may view it.
        $this->requireCaseOwnership($case, $userId);

        // Timeline actions, ordered by action_date ASC then created_at ASC.
        $actionsStmt = $this->pdo->prepare("
            SELECT
                cca.*,
                ua.username AS created_by_username,
                CONCAT(
                    COALESCE(ui.first_name, ''),
                    ' ',
                    COALESCE(ui.last_name, '')
                ) AS created_by_name
            FROM committee_case_actions cca
            LEFT JOIN user_accounts ua ON cca.created_by = ua.id
            LEFT JOIN user_info     ui ON ua.id           = ui.user_account_id
            WHERE cca.case_id = ?
            ORDER BY cca.action_date ASC, cca.created_at ASC
        ");
        $actionsStmt->execute([$caseId]);
        $actions = $actionsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Attachments for each action, keyed by action_id.
        $attachmentsByAction = [];
        if (!empty($actions)) {
            $actionIds    = array_column($actions, 'id');
            $placeholders = implode(',', array_fill(0, count($actionIds), '?'));
            $attStmt      = $this->pdo->prepare("
                SELECT
                    ccaa.*,
                    ua.username AS uploaded_by_username
                FROM committee_case_action_attachments ccaa
                LEFT JOIN user_accounts ua ON ccaa.uploaded_by = ua.id
                WHERE ccaa.action_id IN ({$placeholders})
                ORDER BY ccaa.created_at ASC
            ");
            $attStmt->execute($actionIds);
            foreach ($attStmt->fetchAll(PDO::FETCH_ASSOC) as $att) {
                $attachmentsByAction[(int) $att['action_id']][] = $att;
            }
        }

        // ── Finalized-by user name ────────────────────────────────────────────
        $finalizedByName = null;
        if (!empty($case['finalized_by'])) {
            $fbStmt = $this->pdo->prepare("
                SELECT ua.username,
                       CONCAT(COALESCE(ui.first_name,''), ' ', COALESCE(ui.last_name,'')) AS full_name
                FROM user_accounts ua
                LEFT JOIN user_info ui ON ui.user_account_id = ua.id
                WHERE ua.id = ?
                LIMIT 1
            ");
            $fbStmt->execute([(int) $case['finalized_by']]);
            $fbRow = $fbStmt->fetch(PDO::FETCH_ASSOC);
            if ($fbRow) {
                $finalizedByName = trim($fbRow['full_name']) !== '' ? trim($fbRow['full_name']) : $fbRow['username'];
            }
        }

        // ── Linked committee report (created from this case) ──────────────────
        $linkedReport = null;
        $linkedReportStmt = $this->pdo->prepare("
            SELECT cr.id, cr.report_number, cr.report_type, cr.created_at,
                   cr.returned_to_plenary_at
            FROM committee_reports cr
            INNER JOIN committee_report_documents crd ON crd.committee_report_id = cr.id
            WHERE crd.document_id = ?
            ORDER BY cr.id ASC
            LIMIT 1
        ");
        $linkedReportStmt->execute([(int) $case['document_id']]);
        $linkedReport = $linkedReportStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $pageTitle = trim((string) ($case['docket_number'] ?? '')) !== ''
            ? 'Case Details — ' . htmlspecialchars($case['docket_number'])
            : 'Case Details Required';

        // ── Motion for Reconsideration data ───────────────────────────────────
        // Cases documents may have MFRs filed against them.  Load them so the
        // committee user can see and navigate to related Motions.
        $mfrLink        = null;
        $relatedMotions = [];
        $docTypeName    = $case['document_type_name'] ?? '';
        if ($docTypeName === DocumentService::mfrTypeName()) {
            $mfrLink = $this->docService->getMotionLink((int) $case['document_id']);
        } elseif (in_array($docTypeName, DocumentService::mfrEligibleReferenceTypeNames(), true)) {
            $relatedMotions = $this->docService->getRelatedMotions((int) $case['document_id']);
        }

        $success = flash_get('success');
        $error   = flash_get('error');
        $errors  = flash_get('errors', []);
        $old     = old_get();

        require __DIR__ . '/../../../resources/views/committee/cases/show.php';
    }

    // =========================================================================
    // 4. Add timeline action  POST committee/cases/actions/store
    // =========================================================================

    public function storeAction(): void
    {
        $userId = $this->requireAuth();
        $caseId = (int) ($_POST['case_id'] ?? 0);

        if ($caseId <= 0) {
            flash_set('error', 'Invalid case ID.');
            redirect('committee/inbox');
        }

        $case = $this->loadCaseWithDocument($caseId);
        if (!$case) {
            flash_set('error', 'Case not found.');
            redirect('committee/inbox');
        }

        $this->requireCaseOwnership($case, $userId);

        // ── Guard: case details must be complete before recording actions ─────
        if (trim((string) ($case['nature_of_case'] ?? '')) === ''
            || trim((string) ($case['respondents'] ?? '')) === ''
            || trim((string) ($case['docket_number'] ?? '')) === ''
        ) {
            flash_set('error', 'Save the required case details (docket number, nature of case, and respondents) before adding proceedings.');
            redirect('committee/cases/show?id=' . $caseId);
        }

        // ── Collect + validate inputs ─────────────────────────────────────────
        $actionType  = trim($_POST['action_type']  ?? '');
        $actionDate  = trim($_POST['action_date']  ?? '');
        $description = trim($_POST['description']  ?? '');
        $notes       = trim($_POST['notes']        ?? '');

        $errors = [];

        if ($actionType === '') {
            $errors[] = 'Action type is required.';
        } elseif (mb_strlen($actionType) > 150) {
            $errors[] = 'Action type must not exceed 150 characters.';
        }

        if ($actionDate === '') {
            $errors[] = 'Action date is required.';
        } elseif (!$this->isValidDate($actionDate)) {
            $errors[] = 'Action date is not a valid date.';
        }

        if ($description === '') {
            $errors[] = 'Description is required.';
        } elseif (mb_strlen($description) > 10000) {
            $errors[] = 'Description must not exceed 10 000 characters.';
        }

        if (mb_strlen($notes) > 10000) {
            $errors[] = 'Notes must not exceed 10 000 characters.';
        }

        // ── File validation (optional, multiple attachments) ─────────────────
        //
        // PHP normalises attachments[] into a transposed multi-file array:
        //   $_FILES['attachments']['name'][0..n], ['tmp_name'][0..n], etc.
        // We convert it to an indexed array of per-file arrays first, then
        // validate every entry, and stage ALL files before opening the
        // transaction so filesystem I/O never runs inside a DB lock window.

        $MAX_ATTACHMENTS = 10;
        $rawUploads      = $_FILES['attachments'] ?? [];

        // Build a normalised array: [ ['name'=>…,'tmp_name'=>…,'error'=>…,'size'=>…], … ]
        $uploadedFiles = [];
        if (!empty($rawUploads['name']) && is_array($rawUploads['name'])) {
            foreach ($rawUploads['name'] as $i => $name) {
                // Skip slots where nothing was submitted (UPLOAD_ERR_NO_FILE).
                if (($rawUploads['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $uploadedFiles[] = [
                    'name'     => $name,
                    'tmp_name' => $rawUploads['tmp_name'][$i] ?? '',
                    'error'    => $rawUploads['error'][$i]    ?? UPLOAD_ERR_NO_FILE,
                    'size'     => $rawUploads['size'][$i]     ?? 0,
                    'type'     => $rawUploads['type'][$i]     ?? '',
                ];
            }
        }

        if (count($uploadedFiles) > $MAX_ATTACHMENTS) {
            $errors[] = "You may attach a maximum of {$MAX_ATTACHMENTS} files per action.";
        }

        foreach ($uploadedFiles as $idx => $file) {
            $label      = '"' . basename($file['name']) . '"';
            $fileErrors = $this->validateSingleFile($file);
            foreach ($fileErrors as $fe) {
                $errors[] = "{$label}: {$fe}";
            }
        }

        if (!empty($errors)) {
            flash_set('errors', $errors);
            flash_set('error', 'Please correct the errors below.');
            redirect('committee/cases/show?id=' . $caseId);
        }

        // ── Stage ALL files BEFORE transaction ────────────────────────────────
        $stagedFiles = [];

        foreach ($uploadedFiles as $file) {
            try {
                $stagedFiles[] = $this->stageSingleFile($file, (int) $case['document_id']);
            } catch (RuntimeException $e) {
                // Roll back any files already staged.
                foreach ($stagedFiles as $sf) {
                    if (file_exists($sf['abs_path'])) {
                        @unlink($sf['abs_path']);
                    }
                }
                flash_set('error', 'File upload failed: ' . $e->getMessage());
                redirect('committee/cases/show?id=' . $caseId);
            }
        }

        // ── Transaction ───────────────────────────────────────────────────────
        $actionId = null;

        try {
            $this->pdo->beginTransaction();

            // Insert the timeline action.
            $this->pdo->prepare("
                INSERT INTO committee_case_actions (
                    case_id,
                    action_type,
                    action_date,
                    description,
                    notes,
                    created_by
                ) VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([
                $caseId,
                $actionType,
                $actionDate,
                $description,
                $notes ?: null,
                $userId,
            ]);

            $actionId = (int) $this->pdo->lastInsertId();

            // Insert one attachment row per staged file.
            $attStmt = $this->pdo->prepare("
                INSERT INTO committee_case_action_attachments (
                    action_id,
                    uploaded_by,
                    file_name,
                    stored_path,
                    mime_type,
                    file_size
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");

            foreach ($stagedFiles as $sf) {
                $attStmt->execute([
                    $actionId,
                    $userId,
                    $sf['original_name'],
                    $sf['relative_path'],
                    $sf['mime_type'],
                    $sf['file_size'],
                ]);
            }

            $this->pdo->commit();

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            // Clean up every staged file on DB failure.
            foreach ($stagedFiles as $sf) {
                if (file_exists($sf['abs_path'])) {
                    @unlink($sf['abs_path']);
                }
            }
            system_log('ERROR', 'Committee case action store failed', [
                'case_id' => $caseId,
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            flash_set('error', 'Failed to save action: ' . $e->getMessage());
            redirect('committee/cases/show?id=' . $caseId);
        }

        // ── Post-commit: audit log ────────────────────────────────────────────
        audit_log('CREATE', 'CommitteeCaseAction', (string) $actionId, null, [
            'case_id'        => $caseId,
            'action_id'      => $actionId,
            'action_type'    => $actionType,
            'action_date'    => $actionDate,
            'attachment_count' => count($stagedFiles),
        ], "Timeline action added to case ID {$caseId}: {$actionType} on {$actionDate}");

        flash_set('success', 'Action added to the case timeline.');
        redirect('committee/cases/show?id=' . $caseId);
    }

    // =========================================================================
    // 5. Set / update final outcome  POST committee/cases/outcome
    // =========================================================================

    public function outcomeStore(): void
    {
        $userId = $this->requireAuth();

        $caseId  = (int)   ($_POST['case_id']               ?? 0);
        $outcome = trim(   $_POST['final_outcome']           ?? '');
        $remarks = trim(   $_POST['final_outcome_remarks']   ?? '');

        if ($caseId <= 0) {
            flash_set('error', 'Invalid case ID.');
            redirect('committee/cases');
        }

        // ── Validate outcome value ────────────────────────────────────────────
        $validOutcomes = ['APPROVED', 'DEFERRED', 'WITHDRAWN', 'NOTED'];
        if (!in_array($outcome, $validOutcomes, true)) {
            flash_set('error', 'Please select a valid final outcome.');
            redirect('committee/cases/show?id=' . $caseId);
        }

        // ── Load case ─────────────────────────────────────────────────────────
        $case = $this->loadCaseWithDocument($caseId);
        if (!$case) {
            flash_set('error', 'Case not found.');
            redirect('committee/cases');
        }

        // ── Ownership: only the creating Committee user may finalize ──────────
        $this->requireCaseOwnership($case, $userId);

        // ── Guard: case details must be complete before setting an outcome ────
        if (trim((string) ($case['nature_of_case'] ?? '')) === ''
            || trim((string) ($case['respondents'] ?? '')) === ''
            || trim((string) ($case['docket_number'] ?? '')) === ''
        ) {
            flash_set('error', 'Save the required case details (docket number, nature of case, and respondents) before setting a final outcome.');
            redirect('committee/cases/show?id=' . $caseId);
        }

        $documentId = (int) $case['document_id'];

        // ── Eligible document type ────────────────────────────────────────────
        $docTypeName = $case['document_type_name'] ?? '';
        if (!in_array($docTypeName, self::ELIGIBLE_TYPES, true)) {
            flash_set('error', 'This document type is not eligible for the Cases workflow.');
            redirect('committee/cases/show?id=' . $caseId);
        }

        // NOTE: The previous "active accepted assignment" check was removed here.
        //
        // Rationale: "Proceed to Cases" (CommitteeInboxController::doProceedToCases)
        // marks the Committee inbox assignment COMPLETED before handing the document
        // to this workflow.  Requiring an active (completed_at IS NULL) ACCEPTED
        // assignment therefore always blocked the case owner from finalizing their
        // own case, producing the error "You do not have an active accepted
        // assignment for this document."
        //
        // Authorization is already fully provided by requireCaseOwnership() above:
        // it compares committee_cases.assigned_by against the authenticated user ID,
        // which was set to the user who performed "Proceed to Cases" — the only
        // person who may view, update, or finalize the case.  No additional
        // assignment re-check is necessary or appropriate at this stage.

        // ── Already finalized? Reject without update ──────────────────────────
        if (!empty($case['final_outcome'])) {
            flash_set('error', 'This case has already been finalized. To change the outcome, use a controlled update action (not yet implemented — contact your administrator).');
            redirect('committee/cases/show?id=' . $caseId);
        }

        // ── Require at least one timeline action ──────────────────────────────
        $actionCountStmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM committee_case_actions WHERE case_id = ?
        ");
        $actionCountStmt->execute([$caseId]);
        if ((int) $actionCountStmt->fetchColumn() === 0) {
            flash_set('error', 'You must add at least one timeline action before finalizing the case.');
            redirect('committee/cases/show?id=' . $caseId);
        }

        // ── Map outcome → document status + event type ────────────────────────
        $statusMap = [
            'APPROVED'  => 'Hearing Completed',
            'DEFERRED'  => 'Deferred',
            'WITHDRAWN' => 'Withdrawn',
            'NOTED'     => 'Noted',
        ];
        $eventMap = [
            'APPROVED'  => 'COMMITTEE_CASE_APPROVED',
            'DEFERRED'  => 'HEARING_DEFERRED',
            'WITHDRAWN' => 'HEARING_WITHDRAWN',
            'NOTED'     => 'COMMITTEE_CASE_NOTED',
        ];

        try {
            $targetStatus = $this->requireDocumentStatus($statusMap[$outcome]);
        } catch (RuntimeException $e) {
            flash_set('error', $e->getMessage());
            redirect('committee/cases/show?id=' . $caseId);
        }

        $eventType       = $eventMap[$outcome];
        $prevStatusId    = (int) $case['current_status_id'];

        // ── Resolve username ──────────────────────────────────────────────────
        $uStmt = $this->pdo->prepare("SELECT username FROM user_accounts WHERE id = ? LIMIT 1");
        $uStmt->execute([$userId]);
        $username = (string) ($uStmt->fetchColumn() ?: '');

        // ── Transaction ───────────────────────────────────────────────────────
        try {
            $this->pdo->beginTransaction();

            // 1. Update committee_cases
            $this->pdo->prepare("
                UPDATE committee_cases
                SET final_outcome         = ?,
                    final_outcome_remarks = ?,
                    finalized_by          = ?,
                    finalized_at          = NOW()
                WHERE id = ?
                  AND final_outcome IS NULL
            ")->execute([$outcome, $remarks ?: null, $userId, $caseId]);

            // 2. Update documents.current_status_id
            $this->pdo->prepare("
                UPDATE documents SET current_status_id = ?, updated_by = ? WHERE id = ?
            ")->execute([$targetStatus['id'], $userId, $documentId]);

            // 3. Insert document_events
            $this->pdo->prepare("
                INSERT INTO document_events
                    (document_id, event_type, phase, performed_by,
                     from_status_id, to_status_id, remarks, metadata)
                VALUES (?, ?, 'COMMITTEE', ?, ?, ?, ?, ?)
            ")->execute([
                $documentId,
                $eventType,
                $userId,
                $prevStatusId,
                $targetStatus['id'],
                $remarks ?: "Case final outcome: {$outcome}.",
                json_encode([
                    'case_id'          => $caseId,
                    'docket_number'    => $case['docket_number'],
                    'outcome'          => $outcome,
                    'finalized_by'     => $userId,
                    'finalized_by_username' => $username,
                    'ip_address'       => client_ip(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            $this->pdo->commit();

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            system_log('ERROR', 'CommitteeCasesController::outcomeStore exception', [
                'case_id' => $caseId,
                'outcome' => $outcome,
                'error'   => $e->getMessage(),
            ]);
            flash_set('error', 'A database error occurred while saving the outcome. Please try again.');
            redirect('committee/cases/show?id=' . $caseId);
        }

        // ── Post-commit: audit log ────────────────────────────────────────────
        audit_log('UPDATE', 'CommitteeCase', (string) $caseId, null, [
            'case_id'       => $caseId,
            'docket_number' => $case['docket_number'],
            'document_id'   => $documentId,
            'outcome'       => $outcome,
            'new_status'    => $targetStatus['name'],
            'finalized_by'  => $userId,
        ], "Case {$case['docket_number']} finalized: {$outcome} (user: {$username})");

        $successMessages = [
            'APPROVED'  => 'Case outcome set to Approved. The case is now available in For Report.',
            'DEFERRED'  => 'Case outcome set to Deferred.',
            'WITHDRAWN' => 'Case outcome set to Withdrawn.',
            'NOTED'     => 'Case outcome set to Noted.',
        ];

        flash_set('success', $successMessages[$outcome]);
        redirect('committee/cases/show?id=' . $caseId);
    }

    // =========================================================================
    // 6. For Report list  GET committee/cases/for-report
    //    Shows approved cases. Only cases finalized_by the current user.
    // =========================================================================

    public function forReport(): void
    {
        $userId = $this->requireAuth();
        $this->renderOutcomeList('APPROVED', 'for-report', 'For Report', $userId);
    }

    // =========================================================================
    // 7. Deferred list  GET committee/cases/deferred
    // =========================================================================

    public function deferred(): void
    {
        $userId = $this->requireAuth();
        $this->renderOutcomeList('DEFERRED', 'deferred', 'Deferred Cases', $userId);
    }

    // =========================================================================
    // 8. Withdrawn list  GET committee/cases/withdrawn
    // =========================================================================

    public function withdrawn(): void
    {
        $userId = $this->requireAuth();
        $this->renderOutcomeList('WITHDRAWN', 'withdrawn', 'Withdrawn Cases', $userId);
    }

    // =========================================================================
    // 9. Noted list  GET committee/cases/noted
    // =========================================================================

    public function noted(): void
    {
        $userId = $this->requireAuth();
        $this->renderOutcomeList('NOTED', 'noted', 'Noted Cases', $userId);
    }

    // =========================================================================
    // 10. Create Committee Report form  GET committee/cases/report?case_id={id}
    // =========================================================================

    public function caseReportShow(): void
    {
        $userId = $this->requireAuth();
        $caseId = (int) ($_GET['case_id'] ?? 0);

        if ($caseId <= 0) {
            flash_set('error', 'Invalid case ID.');
            redirect('committee/cases/for-report');
        }

        $case = $this->loadCaseWithDocument($caseId);
        if (!$case) {
            flash_set('error', 'Case not found.');
            redirect('committee/cases/for-report');
        }

        // Only the case creator may create the report.
        $this->requireCaseOwnership($case, $userId);

        // Case must be APPROVED.
        if (($case['final_outcome'] ?? '') !== 'APPROVED') {
            flash_set('error', 'Only Approved cases can have a Committee Report created.');
            redirect('committee/cases/show?id=' . $caseId);
        }

        $documentId = (int) $case['document_id'];

        // Guard: no existing report for this document.
        $dupStmt = $this->pdo->prepare("
            SELECT cr.id FROM committee_reports cr
            INNER JOIN committee_report_documents crd ON crd.committee_report_id = cr.id
            WHERE crd.document_id = ?
            LIMIT 1
        ");
        $dupStmt->execute([$documentId]);
        if ($dupStmt->fetch()) {
            flash_set('error', 'A Committee Report has already been created for this case.');
            redirect('committee/cases/show?id=' . $caseId);
        }

        // Include committees already assigned to the document even if inactive.
        $committeeStmt = $this->pdo->prepare("
            SELECT c.id, c.name
            FROM committees c
            WHERE (c.is_active = 1 AND c.is_deleted = 0)
               OR EXISTS (
                    SELECT 1 FROM document_committees dc
                    WHERE dc.document_id = ? AND dc.committee_id = c.id
               )
            ORDER BY c.name ASC
        ");
        $committeeStmt->execute([$documentId]);
        $allCommittees = $committeeStmt->fetchAll(PDO::FETCH_ASSOC);

        // Pre-select committees from document_committees if they exist.
        $acStmt = $this->pdo->prepare(
            "SELECT committee_id FROM document_committees WHERE document_id = ?"
        );
        $acStmt->execute([$documentId]);
        $assignedCommitteeIds = array_column($acStmt->fetchAll(PDO::FETCH_ASSOC), 'committee_id');

        $old     = old_get();

        $pageTitle = 'Create Committee Report — ' . htmlspecialchars($case['docket_number']);
        require __DIR__ . '/../../../resources/views/committee/cases/case-report.php';
    }

    // =========================================================================
    // 11. Persist Committee Report from case  POST committee/cases/report
    // =========================================================================

    public function caseReportStore(): void
    {
        $userId = $this->requireAuth();

        $caseId            = (int)   ($_POST['case_id']            ?? 0);
        $reportNumber      = trim(   $_POST['report_number']       ?? '');
        $summaryOfFindings = trim(   $_POST['summary_of_findings'] ?? '');
        $committeeIds      = array_filter(array_map('intval', (array) ($_POST['committee_ids'] ?? [])));

        if ($caseId <= 0) {
            flash_set('error', 'Invalid case ID.');
            redirect('committee/cases/for-report');
        }

        $case = $this->loadCaseWithDocument($caseId);
        if (!$case) {
            flash_set('error', 'Case not found.');
            redirect('committee/cases/for-report');
        }

        // Only the case creator may create the report.
        $this->requireCaseOwnership($case, $userId);

        // Case must be APPROVED.
        if (($case['final_outcome'] ?? '') !== 'APPROVED') {
            flash_set('error', 'Only Approved cases can have a Committee Report created.');
            redirect('committee/cases/show?id=' . $caseId);
        }

        $documentId = (int) $case['document_id'];

        // ── Validation ────────────────────────────────────────────────────────
        $errors = [];

        if ($reportNumber === '') {
            $errors[] = 'Committee Report Number is required.';
        } elseif (mb_strlen($reportNumber) > 50) {
            $errors[] = 'Report Number must not exceed 50 characters.';
        } else {
            $dupNum = $this->pdo->prepare(
                "SELECT id FROM committee_reports WHERE report_number = ? LIMIT 1"
            );
            $dupNum->execute([$reportNumber]);
            if ($dupNum->fetch()) {
                $errors[] = "Report Number \"{$reportNumber}\" already exists. Please use a unique number.";
            }
        }

        if ($summaryOfFindings === '') {
            $errors[] = 'Summary of Findings is required.';
        } elseif (mb_strlen($summaryOfFindings) > 10000) {
            $errors[] = 'Summary of Findings must not exceed 10,000 characters.';
        }

        if (empty($committeeIds)) {
            $errors[] = 'At least one committee in charge must be selected.';
        }

        // Validate file uploads (optional).
        $uploadedFiles = $_FILES['attachments'] ?? [];
        $hasFiles      = !empty($uploadedFiles['name'][0]);
        $stagedFiles   = [];

        if ($hasFiles) {
            $fileErrors = $this->docService->validateFileUploads($uploadedFiles, false);
            if (!empty($fileErrors)) {
                $errors = array_merge($errors, $fileErrors);
            }
        }

        if (!empty($errors)) {
            flash_set('errors', $errors);
            flash_set('error', 'Please correct the errors below.');
            old_set([
                'report_number'       => $reportNumber,
                'summary_of_findings' => $summaryOfFindings,
                'committee_ids'       => $_POST['committee_ids'] ?? [],
            ]);
            redirect('committee/cases/report?case_id=' . $caseId);
        }

        // ── Guard: no existing report for this document ───────────────────────
        $dupStmt = $this->pdo->prepare("
            SELECT cr.id FROM committee_reports cr
            INNER JOIN committee_report_documents crd ON crd.committee_report_id = cr.id
            WHERE crd.document_id = ?
            LIMIT 1
        ");
        $dupStmt->execute([$documentId]);
        if ($dupStmt->fetch()) {
            flash_set('error', 'A Committee Report has already been created for this case.');
            redirect('committee/cases/show?id=' . $caseId);
        }

        // ── Validate committee IDs ────────────────────────────────────────────
        $cPh  = implode(',', array_fill(0, count($committeeIds), '?'));
        $cChk = $this->pdo->prepare(
            "SELECT c.id FROM committees c
             WHERE c.id IN ({$cPh})
               AND (
                    (c.is_active = 1 AND c.is_deleted = 0)
                    OR EXISTS (
                        SELECT 1 FROM document_committees dc
                        WHERE dc.document_id = ?
                          AND dc.committee_id = c.id
                    )
               )"
        );
        $cChk->execute([...$committeeIds, $documentId]);
        $validCIds = array_column($cChk->fetchAll(PDO::FETCH_ASSOC), 'id');
        if (count($validCIds) !== count($committeeIds)) {
            flash_set('error', 'One or more selected committees are invalid.');
            redirect('committee/cases/report?case_id=' . $caseId);
        }

        // ── Derive report type ────────────────────────────────────────────────
        $reportType = count($validCIds) > 1 ? 'JOINT_COMMITTEE_REPORT' : 'COMMITTEE_REPORT';

        // ── Stage files BEFORE transaction ────────────────────────────────────
        if ($hasFiles) {
            try {
                $stagedFiles = $this->docService->stageFileUploads($uploadedFiles, $documentId);
            } catch (Throwable $e) {
                flash_set('error', 'File upload failed: ' . $e->getMessage());
                redirect('committee/cases/report?case_id=' . $caseId);
            }
        }

        // ── Look up required statuses ─────────────────────────────────────────
        try {
            $reportCreatedStatus  = $this->requireDocumentStatus('Committee Report Created');
            $hearingCompletedStatus = $this->requireDocumentStatus('Hearing Completed');
        } catch (RuntimeException $e) {
            flash_set('error', $e->getMessage());
            redirect('committee/cases/report?case_id=' . $caseId);
        }

        // ── Username ──────────────────────────────────────────────────────────
        $uStmt = $this->pdo->prepare("SELECT username FROM user_accounts WHERE id = ? LIMIT 1");
        $uStmt->execute([$userId]);
        $username = (string) ($uStmt->fetchColumn() ?: '');

        $pendingFileLogs = [];
        $reportId        = null;

        try {
            $this->pdo->beginTransaction();

            // 1. INSERT committee_reports (no hearing_id / agenda_id for cases)
            $this->pdo->prepare("
                INSERT INTO committee_reports
                    (report_type, report_number, summary_of_findings, created_by, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ")->execute([$reportType, $reportNumber, $summaryOfFindings, $userId]);
            $reportId = (int) $this->pdo->lastInsertId();

            // 2. Link document
            $this->pdo->prepare("
                INSERT IGNORE INTO committee_report_documents (committee_report_id, document_id)
                VALUES (?, ?)
            ")->execute([$reportId, $documentId]);

            // 3. Link committees
            $insRc = $this->pdo->prepare("
                INSERT IGNORE INTO committee_report_committees (committee_report_id, committee_id)
                VALUES (?, ?)
            ");
            foreach ($validCIds as $cId) {
                $insRc->execute([$reportId, $cId]);
            }

            // 4. Attachments (optional)
            if (!empty($stagedFiles)) {
                $pendingFileLogs = $this->docService->insertStagedAttachments(
                    $stagedFiles,
                    $documentId,
                    $userId,
                    'COMMITTEE',
                    'COMMITTEE_REPORT'
                );
            }

            // 5. Update document status → "Committee Report Created"
            $this->pdo->prepare("
                UPDATE documents SET current_status_id = ?, updated_by = ? WHERE id = ?
            ")->execute([$reportCreatedStatus['id'], $userId, $documentId]);

            // 6. Workflow event
            $this->pdo->prepare("
                INSERT INTO document_events
                    (document_id, event_type, phase, performed_by,
                     from_status_id, to_status_id, remarks, metadata)
                VALUES (?, 'COMMITTEE_REPORT_CREATED', 'COMMITTEE', ?, ?, ?, ?, ?)
            ")->execute([
                $documentId,
                $userId,
                $hearingCompletedStatus['id'],
                $reportCreatedStatus['id'],
                "Committee Report #{$reportNumber} created (from case {$case['docket_number']}).",
                json_encode([
                    'report_id'           => $reportId,
                    'report_type'         => $reportType,
                    'report_number'       => $reportNumber,
                    'case_id'             => $caseId,
                    'docket_number'       => $case['docket_number'],
                    'committee_ids'       => $validCIds,
                    'created_by'          => $userId,
                    'created_by_username' => $username,
                    'ip_address'          => client_ip(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            // 7. Route record
            $committeeRoleId = $this->requireCommitteeRoleId();
            $this->pdo->prepare("
                INSERT INTO document_routes
                    (document_id, from_phase, to_phase, routed_by, routed_to_role_id, remarks)
                VALUES (?, 'COMMITTEE', 'COMMITTEE', ?, ?, ?)
            ")->execute([
                $documentId,
                $userId,
                $committeeRoleId,
                "Committee Report #{$reportNumber} created from case {$case['docket_number']} ({$reportType}).",
            ]);

            $this->pdo->commit();

            if (!empty($pendingFileLogs)) {
                $this->docService->flushFileUploadLogs($pendingFileLogs);
            }

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            foreach ($stagedFiles as $s) {
                if (file_exists($s['abs_path'])) {
                    @unlink($s['abs_path']);
                }
            }
            system_log('ERROR', 'CommitteeCasesController::caseReportStore exception', [
                'error'   => $e->getMessage(),
                'case_id' => $caseId,
            ]);
            flash_set('error', 'A database error occurred while saving the Committee Report. Please try again.');
            redirect('committee/cases/report?case_id=' . $caseId);
        }

        old_clear();
        audit_log('CREATE', 'CommitteeReport', (string) $reportId, null, [
            'report_id'     => $reportId,
            'report_type'   => $reportType,
            'report_number' => $reportNumber,
            'case_id'       => $caseId,
            'docket_number' => $case['docket_number'],
            'document_id'   => $documentId,
            'committee_ids' => $validCIds,
            'created_by'    => $userId,
        ], "Committee Report #{$reportNumber} ({$reportType}) created from case {$case['docket_number']} by {$username}");

        flash_set('success', "Committee Report #{$reportNumber} created successfully.");
        redirect('committee/reports');
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Require a logged-in user and return their ID, or redirect to login.
     */
    private function requireAuth(): int
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }
        return $userId;
    }

    /**
     * Return the Committee role ID, or redirect to dashboard if missing.
     */
    private function requireCommitteeRoleId(): int
    {
        $stmt = $this->pdo->query(
            "SELECT id FROM roles WHERE name = 'Committee' AND is_active = 1 AND is_deleted = 0 LIMIT 1"
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            flash_set('error', 'Committee role not found. Please contact the system administrator.');
            redirect('dashboard');
        }
        return (int) $row['id'];
    }

    /**
     * Load a document and verify:
     *  1. The document exists.
     *  2. Its document type is eligible (Administrative Cases or Complaint).
     *  3. There is an ACCEPTED Committee assignment owned by $userId.
     *
     * Returns [$document, $assignment] on success, or redirects on failure.
     *
     * @return array{0: array, 1: array}
     */
    private function requireEligibleOwnedDocument(int $documentId, int $userId): array
    {
        $committeeRoleId = $this->requireCommitteeRoleId();

        // Load document with type name.
        $docStmt = $this->pdo->prepare("
            SELECT
                d.*,
                dt.name        AS document_type_name,
                dt.badge_color AS document_type_badge_color,
                ds.name        AS status,
                ds.badge_color AS status_badge_color,
                st.name        AS source_type,
                eo.name        AS external_office_name,
                eo.abbreviation AS external_office_abbr,
                h.name         AS hospital_name,
                m.name         AS municipality_name,
                m.type         AS municipality_type,
                m.id           AS municipality_id_val
            FROM documents d
            LEFT JOIN document_types    dt ON d.document_type_id   = dt.id
            LEFT JOIN document_statuses ds ON d.current_status_id  = ds.id
            LEFT JOIN source_types      st ON d.source_type_id     = st.id
            LEFT JOIN provincial_government_offices  eo ON d.external_office_id = eo.id
            LEFT JOIN hospitals          h ON d.hospital_id        = h.id
            LEFT JOIN municities         m ON d.municipality_id    = m.id
            WHERE d.id = ?
            LIMIT 1
        ");
        $docStmt->execute([$documentId]);
        $document = $docStmt->fetch(PDO::FETCH_ASSOC);

        if (!$document) {
            flash_set('error', 'Document not found.');
            redirect('committee/inbox');
        }

        // Eligibility check: document type must be in the allowed list.
        $docTypeName = $document['document_type_name'] ?? '';
        if (!in_array($docTypeName, self::ELIGIBLE_TYPES, true)) {
            flash_set('error', 'The Cases workflow is only available for Administrative Cases and Complaint documents.');
            redirect('committee/inbox/show?id=' . $documentId);
        }

        // Ownership check: must have an ACCEPTED assignment owned by this user.
        $assignStmt = $this->pdo->prepare("
            SELECT *
            FROM document_assignments
            WHERE document_id         = ?
              AND assigned_to_role_id = ?
              AND phase               = 'COMMITTEE'
              AND decision            = 'ACCEPTED'
              AND completed_at        IS NULL
              AND (accepted_by = ? OR assigned_to_user_id = ?)
            ORDER BY id ASC
            LIMIT 1
        ");
        $assignStmt->execute([$documentId, $committeeRoleId, $userId, $userId]);
        $assignment = $assignStmt->fetch(PDO::FETCH_ASSOC);

        if (!$assignment) {
            flash_set('error', 'You do not have an accepted assignment for this document, or the assignment has been completed.');
            redirect('committee/inbox');
        }

        return [$document, $assignment];
    }

    /**
     * Verify that the logged-in user is the one who created the case.
     * Redirects on failure.
     */
    private function requireCaseOwnership(array $case, int $userId): void
    {
        if ((int) ($case['assigned_by'] ?? 0) !== $userId) {
            flash_set('error', 'You do not have access to this case. Only the Committee user who created it may view or update it.');
            redirect('committee/inbox');
        }
    }

    /**
     * Check whether a committee_cases row already exists for $documentId.
     */
    private function caseExistsForDocument(int $documentId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM committee_cases WHERE document_id = ? LIMIT 1"
        );
        $stmt->execute([$documentId]);
        return (bool) $stmt->fetch();
    }

    /**
     * Return the committee_cases row for $documentId, or null.
     */
    private function getCaseByDocumentId(int $documentId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM committee_cases WHERE document_id = ? LIMIT 1"
        );
        $stmt->execute([$documentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Load a committee_cases row joined with its document, document type, and
     * the creator's username/name. Returns null if not found.
     */
    private function loadCaseWithDocument(int $caseId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                cc.*,
                d.tracking_number,
                d.subject_matter,
                d.date_received,
                d.time_received,
                d.source_name,
                d.source_type_id,
                d.current_status_id,
                dt.name        AS document_type_name,
                dt.badge_color AS document_type_badge_color,
                ds.name        AS status,
                ds.badge_color AS status_badge_color,
                st.name        AS source_type,
                eo.name        AS external_office_name,
                h.name         AS hospital_name,
                m.name         AS municipality_name,
                ua.username    AS created_by_username,
                CONCAT(
                    COALESCE(ui.first_name, ''),
                    ' ',
                    COALESCE(ui.last_name, '')
                )              AS created_by_name
            FROM committee_cases cc
            INNER JOIN documents          d  ON cc.document_id        = d.id
            LEFT  JOIN document_types     dt ON d.document_type_id    = dt.id
            LEFT  JOIN document_statuses  ds ON d.current_status_id   = ds.id
            LEFT  JOIN source_types       st ON d.source_type_id      = st.id
            LEFT  JOIN provincial_government_offices   eo ON d.external_office_id  = eo.id
            LEFT  JOIN hospitals           h ON d.hospital_id         = h.id
            LEFT  JOIN municities          m ON d.municipality_id     = m.id
            LEFT  JOIN user_accounts      ua ON cc.assigned_by        = ua.id
            LEFT  JOIN user_info          ui ON ua.id                 = ui.user_account_id
            WHERE cc.id = ?
            LIMIT 1
        ");
        $stmt->execute([$caseId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Return the formatted next docket number for the given year WITHOUT
     * allocating the sequence. Used only for display in the creation form.
     */
    private function previewNextDocketNumber(int $year): string
    {
        $stmt = $this->pdo->prepare(
            "SELECT last_sequence FROM committee_case_docket_sequences WHERE docket_year = ? LIMIT 1"
        );
        $stmt->execute([$year]);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        $next = $row ? ((int) $row['last_sequence']) + 1 : 1;
        return sprintf('DKT-%d-%04d', $year, $next);
    }

    /**
     * Validate a single uploaded file against the allowed-type and size rules.
     * Checks: upload error, file size, allowed extension, is_uploaded_file(),
     * and MIME type via finfo so a renamed file is rejected.
     *
     * @param  array $file  One entry from the normalised upload array.
     * @return string[]     Error messages; empty on success.
     */
    private function validateSingleFile(array $file): array
    {
        $errors = [];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE   => 'The file exceeds the server upload limit.',
                UPLOAD_ERR_FORM_SIZE  => 'The file exceeds the form size limit.',
                UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION  => 'A server extension blocked the upload.',
            ];
            $errors[] = $uploadErrors[$file['error']] ?? 'Unknown upload error.';
            return $errors;
        }

        $maxBytes = 25 * 1024 * 1024; // 25 MB
        if ($file['size'] > $maxBytes) {
            $errors[] = 'File must not exceed 25 MB.';
        }

        $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo(basename($file['name']), PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            $errors[] = "File type '.{$ext}' is not allowed. Allowed: " . implode(', ', $allowedExtensions) . '.';
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'Invalid file upload detected.';
            return $errors; // can't trust the path; stop here
        }

        // MIME-type check — rejects files that are merely renamed to an allowed extension.
        $allowedMimes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ];
        $finfo        = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if ($detectedMime === false || !in_array($detectedMime, $allowedMimes, true)) {
            $errors[] = "File MIME type '{$detectedMime}' is not permitted.";
        }

        return $errors;
    }

    /**
     * Move a single uploaded file to the document's upload directory.
     * Called BEFORE the transaction; filesystem I/O must not run inside a lock.
     *
     * @return array{abs_path: string, relative_path: string, original_name: string, mime_type: string, file_size: int}
     * @throws RuntimeException On directory creation or move failure.
     */
    private function stageSingleFile(array $file, int $documentId): array
    {
        $uploadDir = __DIR__ . '/../../../public/uploads/documents/' . $documentId . '/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            throw new RuntimeException('Failed to create upload directory.');
        }

        $originalName = basename($file['name']);
        $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $storedName   = 'case_' . $documentId . '_' . uniqid('', true) . '.' . $ext;
        $absPath      = $uploadDir . $storedName;
        $relativePath = 'uploads/documents/' . $documentId . '/' . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            throw new RuntimeException("Failed to move uploaded file: {$originalName}");
        }

        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $absPath);
        finfo_close($finfo);

        return [
            'abs_path'      => $absPath,
            'relative_path' => $relativePath,
            'original_name' => $originalName,
            'mime_type'     => $mimeType,
            'file_size'     => (int) filesize($absPath),
        ];
    }

    /**
     * Validate a date string as Y-m-d.
     */
    private function isValidDate(string $value): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $value);
        return $d !== false && $d->format('Y-m-d') === $value;
    }

    // =========================================================================
    // Helper — require a document_statuses row by name (throws on missing)
    // =========================================================================

    /**
     * Resolve a document_statuses row by name.
     * Throws RuntimeException if the row does not exist (migration not run).
     */
    private function requireDocumentStatus(string $name): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, name, badge_color
            FROM document_statuses
            WHERE name = ? AND is_active = 1 AND is_deleted = 0
            LIMIT 1
        ");
        $stmt->execute([$name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException(
                "Required document status \"{$name}\" not found. Please run pending migrations."
            );
        }
        return $row;
    }

    /**
     * Shared query + render for the four outcome-filtered list pages
     * (for-report / deferred / withdrawn / noted).
     *
     * Scoped to cases finalized by the current user so each Committee member
     * only sees their own resolved cases.
     */
    private function renderOutcomeList(
        string $outcome,
        string $viewSlug,
        string $pageTitle,
        int    $userId
    ): void {
        $search  = trim($_GET['search'] ?? '');
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        $where  = ['cc.assigned_by = ?', 'cc.final_outcome = ?'];
        $params = [$userId, $outcome];

        if ($search !== '') {
            $where[]  = '(cc.docket_number LIKE ? OR d.tracking_number LIKE ?
                          OR d.subject_matter LIKE ? OR cc.nature_of_case LIKE ?
                          OR cc.complainant_details LIKE ? OR cc.respondents LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $whereClause = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare("
            SELECT COUNT(*) AS total
            FROM committee_cases cc
            INNER JOIN documents d ON cc.document_id = d.id
            WHERE {$whereClause}
        ");
        $countStmt->execute($params);
        $total      = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        $totalPages = max(1, (int) ceil($total / $perPage));

        $listStmt = $this->pdo->prepare("
            SELECT
                cc.id,
                cc.docket_number,
                cc.nature_of_case,
                cc.complainant_details,
                cc.respondents,
                cc.date_assigned,
                cc.final_outcome,
                cc.finalized_at,
                cc.final_outcome_remarks,
                d.id              AS document_id,
                d.tracking_number,
                d.subject_matter,
                dt.name           AS document_type_name,
                dt.badge_color    AS document_type_badge_color,
                ds.name           AS status,
                ds.badge_color    AS status_badge_color,
                (
                    SELECT COUNT(*) FROM committee_reports cr2
                    INNER JOIN committee_report_documents crd2
                        ON crd2.committee_report_id = cr2.id
                    WHERE crd2.document_id = d.id
                ) AS report_count
            FROM committee_cases cc
            INNER JOIN documents         d  ON cc.document_id        = d.id
            LEFT  JOIN document_types    dt ON d.document_type_id    = dt.id
            LEFT  JOIN document_statuses ds ON d.current_status_id   = ds.id
            WHERE {$whereClause}
            ORDER BY cc.finalized_at DESC, cc.id DESC
            LIMIT ? OFFSET ?
        ");
        $listStmt->execute([...$params, $perPage, $offset]);
        $cases = $listStmt->fetchAll(PDO::FETCH_ASSOC);

        require __DIR__ . "/../../../resources/views/committee/cases/{$viewSlug}.php";
    }
}

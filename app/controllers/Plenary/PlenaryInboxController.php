<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/DocumentService.php';

/**
 * PlenaryInboxController
 *
 * Handles the Plenary inbox: listing pending assignments, viewing document
 * detail, claiming (Accept) a document, recording a pre-decision status with
 * remarks, and (when the status is "On Process") filling in Plenary
 * processing metadata (Proposed No., Subject Matter, Authors/Co-Authors,
 * Committees in Charge, Target First Reading Date, Session Type).
 *
 * OWNERSHIP MODEL (mirrors AdminInboxController / CommitteeInboxController,
 * established in migration 050):
 *
 *   - A new PLENARY assignment arrives as PENDING with accepted_by = NULL and
 *     assigned_to_user_id = NULL.  All active Plenary users see it in their
 *     inbox (role-level visibility).
 *   - When a user clicks Accept, an atomic UPDATE sets:
 *       decision            = 'ACCEPTED'
 *       accepted_at         = NOW()
 *       accepted_by         = <current user id>   (accountability)
 *       assigned_to_user_id = <current user id>   (ownership gate)
 *     A rowCount() = 0 result means another user beat us — throw a
 *     concurrency error.
 *   - After acceptance the document is visible ONLY to that user in the
 *     Accepted tab.  Other Plenary users no longer see it in the inbox.
 *   - The pre-decision form and the processing form both require ownership
 *     before any write.
 *
 * AUTHORIZATION:
 *   - RoleMiddleware restricts all plenary/* routes to ['Super Admin', 'Plenary'].
 *   - Within the controller every mutation additionally re-checks ownership
 *     via document_assignments so that no other user can act on a claimed
 *     document.
 *
 * PRE-DECISION STATUSES (seeded by migration 065):
 *   - Deferred
 *   - Lay on the Table
 *   - Noted
 *   - Remanded
 *   - On Process
 *   - Withdrawn
 *
 * Only "On Process" unlocks the full Plenary processing form (Proposed
 * No., Subject Matter override, Authors/Co-Authors, Committees, Target Date,
 * Session Type).
 *
 * OUTCOME TABS:
 *   Besides "Inbox" (pending) and "Accepted" (being processed), the inbox
 *   exposes one tab per non-processing pre-decision outcome:
 *     Deferred, Lay on the Table, Noted, Remanded, Withdrawn.
 *   "On Process" (formerly "Under Processing") is deliberately NOT a tab —
 *   those documents continue through the existing Accepted processing flow.
 *   Tab membership is driven by plenary_document_details.status_id and scoped
 *   to the signed-in user's accepted assignments (ownership preserved).
 *
 *   - Deferred        → standby; the owner must submit remarks + one or more
 *                       compliance files to resume processing (see
 *                       self::ACTION_SUBMIT_COMPLIANCE).
 *   - Lay on the Table→ non-terminal; the owner can still change the
 *                       pre-decision status and continue processing.
 *   - Noted / Remanded / Withdrawn
 *                     → terminal; no further Plenary processing actions.
 *
 * TERMINAL OUTCOMES:
 *   Once a document's pre-decision status is Noted, Remanded or Withdrawn the
 *   controller refuses every further Plenary mutation (pre_decision,
 *   save_processing, submit_compliance, return_to_admin) while retaining the
 *   document row, its assignments and its full history.
 *
 *
 * PROPOSED NUMBER FORMAT:
 *   No authoritative numbering format exists in the project schema
 *   (document_types has no prefix/format column).  The field is therefore
 *   free-text.  It is required only for document types OTHER than
 *   "Communication", "Administrative Case" / "Administrative Cases", and
 *   "Complaint" / "Complaints".  Format guidance is shown per document-type
 *   name in the view.  See PROPOSED_NUMBER_EXEMPT_TYPES below.
 *
 * NOTE ON ROUTES:
 *   The following routes from web.php are mapped to methods NOT yet fully
 *   implemented (they belong to downstream Plenary workflow phases beyond
 *   the scope of this inbox task):
 *     firstReadingIndex / firstReadingShow / firstReadingStore
 *     secondReadingIndex / secondReadingShow / secondReadingStore
 *     finalizeShow / finalizeStore
 *     statusList
 *   These stub methods redirect to plenary/inbox with a "not yet implemented"
 *   flash so the application does not 500 when routes are hit.
 */
class PlenaryInboxController
{
    protected PDO             $pdo;
    protected DocumentService $docService;

    /** Valid POST actions for process(). */
    private const VALID_ACTIONS = [
        'accept',
        'pre_decision',
        'save_processing',
        'submit_compliance',
        'return_to_admin',
    ];

    /** Action key that resumes a Deferred document back into processing. */
    public const ACTION_SUBMIT_COMPLIANCE = 'submit_compliance';

    /**
     * Status-based outcome tabs.
     *
     * key   = the ?view= slug used in the URL.
     * value = the exact document_statuses.name the tab filters on.
     *
     * "On Process" is intentionally absent: those documents continue through
     * the existing Accepted processing flow instead of getting their own tab.
     */
    private const OUTCOME_TABS = [
        'deferred'         => 'Deferred',
        'lay-on-the-table' => 'Lay on the Table',
        'noted'            => 'Noted',
        'remanded'         => 'Remanded',
        'withdrawn'        => 'Withdrawn',
    ];

    /**
     * Pre-decision outcomes that are terminal: once set, no further Plenary
     * processing action is permitted for the document.
     */
    private const TERMINAL_STATUSES = ['noted', 'remanded', 'withdrawn'];

    /**
     * Scalar-friendly SQL fragment listing the outcome status ids.
     * Used to keep outcome documents out of the generic Accepted list so the
     * inbox partitions cleanly by pre-decision status.
     */
    private const OUTCOME_STATUS_IDS_SQL = "
        SELECT id FROM document_statuses
        WHERE name IN ('Deferred','Lay on the Table','Noted','Remanded','Withdrawn')
          AND is_active  = 1
          AND is_deleted = 0
    ";

    /**
     * Document type names that are EXEMPT from the Proposed Number requirement.
     * Comparison is case-insensitive; partial word matching is intentional.
     */
    private const PROPOSED_NUMBER_EXEMPT_TYPES = [
        'communication',
        'administrative case',
        'complaint',
    ];

    /**
     * SQL condition that keeps the Accepted tab limited to documents that are
     * still awaiting Plenary processing.
     *
     * Once the Plenary processing details are saved the document moves to the
     * "Awaiting First Reading" status (and later gains a plenary_readings row),
     * so it must leave the Accepted list.  The ACCEPTED assignment row itself is
     * intentionally left untouched (completed_at stays NULL) because the First
     * Reading / Second Reading ownership checks in PlenaryReadingController
     * rely on it (see ::isOwnedByUser()).
     *
     * Additionally, documents that have moved to another phase (e.g., FINALIZED
     * or COMMITTEE) must not appear in the Accepted tab. The current_phase
     * check ensures this.
     *
     * Requires the query to join `documents d` and `document_assignments da`.
     * Used verbatim by both the Accepted list query and the accepted count so
     * the badge and the list can never drift apart.
     */
    private const ACCEPTED_STILL_PROCESSING = "
        d.current_status_id NOT IN (
            SELECT id FROM document_statuses
            WHERE name = 'Awaiting First Reading'
              AND is_active  = 1
              AND is_deleted = 0
        )
        AND NOT EXISTS (
            SELECT 1 FROM plenary_readings pr WHERE pr.document_id = da.document_id
        )
        AND d.current_phase = 'PLENARY'
    ";

    // =========================================================================
    // Constructor
    // =========================================================================

    public function __construct()
    {
        $database         = new Database();
        $this->pdo        = $database->connect();
        $this->docService = new DocumentService();
    }

    // =========================================================================
    // 1. Inbox list (GET plenary/inbox)
    // =========================================================================

    public function index(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        $plenaryRoleId = $this->requirePlenaryRoleId();

        $view = trim($_GET['view'] ?? 'inbox');
        $validViews = array_merge(['inbox', 'accepted'], array_keys(self::OUTCOME_TABS));
        if (!in_array($view, $validViews, true)) {
            $view = 'inbox';
        }
        $isOutcomeView = isset(self::OUTCOME_TABS[$view]);

        $search  = trim($_GET['search'] ?? '');
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        $where  = ['da.assigned_to_role_id = ?', "da.phase = 'PLENARY'"];
        $params = [$plenaryRoleId];

        if ($view === 'inbox') {
            $where[] = "da.decision = 'PENDING'";
            $where[] = 'da.completed_at IS NULL';
            $where[] = '(da.assigned_to_user_id IS NULL OR da.assigned_to_user_id = ?)';
            $where[] = 'da.accepted_by IS NULL';
            $params[] = $userId;
        } elseif ($isOutcomeView) {
            // Outcome tabs list the documents this user accepted whose current
            // pre-decision status matches the tab. Ownership is preserved: only
            // the accepting Plenary user sees them.
            $where[] = "da.decision = 'ACCEPTED'";
            $where[] = 'da.completed_at IS NULL';
            $where[] = '(da.accepted_by = ? OR da.assigned_to_user_id = ?)';
            $params[] = $userId;
            $params[] = $userId;
            $where[]  = 'pdd.status_id = (
                SELECT id FROM document_statuses
                WHERE name = ? AND is_active = 1 AND is_deleted = 0
                LIMIT 1
            )';
            $params[] = self::OUTCOME_TABS[$view];
        } else {
            $where[] = "da.decision = 'ACCEPTED'";
            $where[] = 'da.completed_at IS NULL';
            $where[] = '(da.accepted_by = ? OR da.assigned_to_user_id = ?)';
            $params[] = $userId;
            $params[] = $userId;
            // Documents that already moved on to First Reading (or beyond) are
            // no longer "being processed" in this inbox — hide them here while
            // leaving the ACCEPTED assignment open for ownership checks.
            $where[] = '(' . self::ACCEPTED_STILL_PROCESSING . ')';
            // Outcome documents live in their own tabs; keep them out of the
            // generic Accepted list so every document is listed exactly once.
            $where[] = '(pdd.status_id IS NULL OR pdd.status_id NOT IN (' . self::OUTCOME_STATUS_IDS_SQL . '))';
        }

        if ($search !== '') {
            $where[]  = '(d.tracking_number LIKE ? OR d.subject_matter LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $whereClause = implode(' AND ', $where);

        // Counts for the stats cards and every tab badge. They are derived from
        // the same ownership-scoped predicates as the list queries so the badges
        // and the lists can never drift apart.
        $tabCounts     = $this->fetchTabCounts($plenaryRoleId, $userId);
        $pendingCount  = $tabCounts['inbox'];
        $acceptedCount = $tabCounts['accepted'];

        // Total for current view
        // NOTE: plenary_document_details is LEFT-joined in every branch so that
        // the pdd.status_id predicates used by the outcome and accepted filters
        // are always resolvable, including on the inbox (PENDING) tab where the
        // join produces no match and is harmlessly unused.
        $countSql   = "
            SELECT COUNT(*) AS cnt
            FROM document_assignments da
            INNER JOIN documents d ON d.id = da.document_id
            LEFT  JOIN plenary_document_details pdd ON pdd.document_id = d.id
            WHERE {$whereClause}
        ";
        $countStmt  = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total      = (int) $countStmt->fetchColumn();
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page       = min($page, $totalPages);

        // Accepted-by info for every non-inbox view (Accepted + outcome tabs)
        $acceptedSelect = $view !== 'inbox'
            ? ", ab.username AS accepted_by_username,
               CONCAT_WS(' ',
                   NULLIF(aui.first_name, ''),
                   NULLIF(aui.last_name, '')
               ) AS accepted_by_name"
            : '';
        $acceptedJoin = $view !== 'inbox'
            ? "LEFT JOIN user_accounts ab  ON ab.id  = da.accepted_by
               LEFT JOIN user_info     aui ON aui.user_account_id = da.accepted_by"
            : '';

        $sql = "
            SELECT
                da.id              AS assignment_id,
                da.decision,
                da.accepted_at,
                d.id               AS document_id,
                d.tracking_number,
                d.subject_matter,
                d.date_received,
                d.time_received,
                d.current_phase,
                ds.name            AS status,
                ds.badge_color     AS status_badge_color,
                dt.name            AS document_type_name,
                dt.badge_color     AS document_type_badge_color
                {$acceptedSelect}
            FROM document_assignments da
            INNER JOIN documents        d   ON d.id  = da.document_id
            LEFT  JOIN plenary_document_details pdd ON pdd.document_id = d.id
            LEFT  JOIN document_statuses ds  ON ds.id = d.current_status_id
            LEFT  JOIN document_types    dt  ON dt.id = d.document_type_id
            {$acceptedJoin}
            WHERE {$whereClause}
            ORDER BY da.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $assignments = $stmt->fetchAll();

        $pageTitle   = 'Plenary Inbox';
        $currentView = $view;

        $success = flash_get('success');
        $error   = flash_get('error');
        $errors  = flash_get('errors', []);

        require __DIR__ . '/../../../resources/views/plenary/inbox/index.php';
    }

    // =========================================================================
    // 2. Document detail (GET plenary/inbox/show)
    // =========================================================================

    public function show(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        $documentId = (int) ($_GET['id'] ?? 0);
        if ($documentId <= 0) {
            flash_set('error', 'Invalid document ID.');
            redirect('plenary/inbox');
        }

        $plenaryRoleId = $this->requirePlenaryRoleId();

        // Load document with joins
        $docStmt = $this->pdo->prepare("
            SELECT
                d.*,
                ds.name        AS status_name,
                ds.badge_color AS status_badge_color,
                dt.name        AS document_type_name,
                dt.badge_color AS document_type_badge_color,
                st.name        AS source_type_name
            FROM documents d
            LEFT JOIN document_statuses ds ON ds.id = d.current_status_id
            LEFT JOIN document_types    dt ON dt.id = d.document_type_id
            LEFT JOIN source_types      st ON st.id = d.source_type_id
            WHERE d.id = ?
            LIMIT 1
        ");
        $docStmt->execute([$documentId]);
        $document = $docStmt->fetch();

        if (!$document) {
            flash_set('error', 'Document not found.');
            redirect('plenary/inbox');
        }

        // Load active assignment
        $assignStmt = $this->pdo->prepare("
            SELECT da.*, ua.username AS accepted_by_username
            FROM document_assignments da
            LEFT JOIN user_accounts ua ON ua.id = da.accepted_by
            WHERE da.document_id         = ?
              AND da.assigned_to_role_id = ?
              AND da.completed_at        IS NULL
            ORDER BY da.id DESC
            LIMIT 1
        ");
        $assignStmt->execute([$documentId, $plenaryRoleId]);
        $activeAssignment = $assignStmt->fetch() ?: false;

        // Ownership checks
        $isPendingAssignment    = false;
        $isOwnedAcceptedAssignment = false;

        if ($activeAssignment !== false) {
            $decision     = $activeAssignment['decision'] ?? '';
            $acceptedBy   = $activeAssignment['accepted_by']         ? (int) $activeAssignment['accepted_by']         : null;
            $assignedToId = $activeAssignment['assigned_to_user_id'] ? (int) $activeAssignment['assigned_to_user_id'] : null;

            if ($decision === 'PENDING'
                && $acceptedBy === null
                && ($assignedToId === null || $assignedToId === $userId)
            ) {
                $isPendingAssignment = true;
            }

            if ($decision === 'ACCEPTED'
                && ($acceptedBy === $userId || $assignedToId === $userId)
            ) {
                $isOwnedAcceptedAssignment = true;
            }

            // If another user owns the accepted assignment, block view
            if ($decision === 'ACCEPTED' && !$isOwnedAcceptedAssignment) {
                flash_set('error', 'This document is assigned to another Plenary user.');
                redirect('plenary/inbox');
            }
        }

        $canAccept  = $isPendingAssignment;
        $canProcess = $isOwnedAcceptedAssignment;

        // Plenary details (for pre-decision and processing data)
        $plenaryDetails = $this->fetchPlenaryDetails($documentId);

        // Authors and co-authors
        $authors    = $this->fetchAuthors($documentId, 'AUTHOR');
        $coAuthors  = $this->fetchAuthors($documentId, 'CO_AUTHOR');

        // Committees assigned via document_committees
        $docCommittees = $this->fetchDocumentCommittees($documentId);

        // SP Members and Committees for the form selects
        $spMembers  = $this->docService->getSpMembers();
        $committees = $this->fetchCommittees();

        // Pre-decision status options (must be the specific six statuses)
        $preDecisionStatuses = $this->fetchPreDecisionStatuses();

        // Attachments, routes, events, revisions
        $attachments     = $this->fetchAttachments($documentId);
        $routes          = $this->fetchRoutes($documentId);
        $events          = $this->fetchEvents($documentId);
        $revisions       = $this->fetchRevisions($documentId);
        $allAssignments  = $this->fetchAllAssignments($documentId);

        // Determine whether the document type exempts Proposed Number
        $documentTypeName       = strtolower(trim($document['document_type_name'] ?? ''));
        $proposedNumberExempt   = $this->isProposedNumberExempt($documentTypeName);

        // Communications use a reduced Plenary processing form (see the view).
        $isCommunication        = $this->isCommunicationType($documentTypeName);

        $pageTitle = 'Document Details — ' . htmlspecialchars($document['tracking_number']);

        $success = flash_get('success');
        $error   = flash_get('error');
        $errors  = flash_get('errors', []);

        require __DIR__ . '/../../../resources/views/plenary/inbox/show.php';
    }

    // =========================================================================
    // 3. Accept action (POST plenary/inbox/accept)
    // =========================================================================

    /**
     * Thin wrapper so the dedicated route plenary/inbox/accept maps to accept.
     * All logic is in the unified process() dispatcher.
     */
    public function accept(): void
    {
        $_POST['action'] = 'accept';
        $this->process();
    }

    // =========================================================================
    // 4. processPredecision (POST plenary/inbox/process)
    //    Handles pre_decision, save_processing, and return_to_admin actions.
    // =========================================================================

    public function processPredecision(): void
    {
        // action comes from the form hidden field; must be one of the
        // non-accept VALID_ACTIONS
        $this->process();
    }

    // =========================================================================
    // Internal unified dispatcher (called by accept() and processPredecision())
    // =========================================================================

    private function process(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        $documentId = (int) ($_POST['document_id'] ?? 0);
        $action     = trim($_POST['action']      ?? '');
        $remarks    = trim($_POST['remarks']     ?? '');

        if ($documentId <= 0) {
            flash_set('error', 'Invalid document ID.');
            redirect('plenary/inbox');
        }

        if (!in_array($action, self::VALID_ACTIONS, true)) {
            flash_set('error', 'Invalid action specified.');
            redirect('plenary/inbox/show?id=' . $documentId);
        }

        // ---------------------------------------------------------------------
        // Compliance submission: validate and stage the uploads BEFORE the DB
        // transaction so filesystem I/O stays outside the lock window. Existing
        // attachments and history are untouched — new files are appended.
        // ---------------------------------------------------------------------
        $stagedComplianceFiles = [];
        if ($action === self::ACTION_SUBMIT_COMPLIANCE) {
            $complianceFiles = $_FILES['compliance_files'] ?? ['name' => ['']];

            $uploadErrors = $this->docService->validateFileUploads($complianceFiles, true);
            if ($remarks === '') {
                $uploadErrors[] = 'Remarks are required when submitting compliance updates.';
            }

            if (!empty($uploadErrors)) {
                flash_set('errors', $uploadErrors);
                old_set($_POST);
                redirect('plenary/inbox/show?id=' . $documentId);
            }

            try {
                $stagedComplianceFiles = $this->docService->stageFileUploads($complianceFiles, $documentId);
            } catch (Throwable $stageErr) {
                system_log('ERROR', 'Plenary compliance file staging failed', [
                    'error'       => $stageErr->getMessage(),
                    'document_id' => $documentId,
                ]);
                flash_set('error', 'Failed to store compliance files: ' . $stageErr->getMessage());
                redirect('plenary/inbox/show?id=' . $documentId);
            }
        }

        $plenaryRoleId = $this->requirePlenaryRoleId();

        // Retry loop for transient DB lock errors
        $maxRetries    = 3;
        $retryDelay    = 100000; // 100 ms
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $this->pdo->beginTransaction();

                // Resolve assignment
                if ($action === 'accept') {
                    $assignStmt = $this->pdo->prepare("
                        SELECT *
                        FROM document_assignments
                        WHERE document_id         = ?
                          AND assigned_to_role_id = ?
                          AND decision            = 'PENDING'
                          AND completed_at        IS NULL
                          AND accepted_by         IS NULL
                          AND (assigned_to_user_id IS NULL OR assigned_to_user_id = ?)
                        ORDER BY id ASC
                        LIMIT 1
                    ");
                    $assignStmt->execute([$documentId, $plenaryRoleId, $userId]);
                } else {
                    $assignStmt = $this->pdo->prepare("
                        SELECT *
                        FROM document_assignments
                        WHERE document_id         = ?
                          AND assigned_to_role_id = ?
                          AND decision            = 'ACCEPTED'
                          AND completed_at        IS NULL
                          AND (accepted_by = ? OR assigned_to_user_id = ?)
                        ORDER BY id ASC
                        LIMIT 1
                    ");
                    $assignStmt->execute([$documentId, $plenaryRoleId, $userId, $userId]);
                }

                $assignment = $assignStmt->fetch();

                if (!$assignment) {
                    $this->pdo->rollBack();
                    if ($action === 'accept') {
                        flash_set('error', 'No pending Plenary assignment found. It may have already been accepted by another user.');
                    } else {
                        flash_set('error', 'You do not have ownership of this document. Only the Plenary user who accepted it may perform this action.');
                    }
                    redirect('plenary/inbox/show?id=' . $documentId);
                }

                $assignmentId = (int) $assignment['id'];

                $docStmt = $this->pdo->prepare("SELECT * FROM documents WHERE id = ? LIMIT 1");
                $docStmt->execute([$documentId]);
                $document = $docStmt->fetch();

                if (!$document) {
                    $this->pdo->rollBack();
                    flash_set('error', 'Document not found.');
                    redirect('plenary/inbox');
                }

                $currentStatusId = (int) $document['current_status_id'];

                // Dispatch
                $auditData = null;
                switch ($action) {
                    case 'accept':
                        $auditData = $this->doAccept($documentId, $assignmentId, $userId, $plenaryRoleId, $currentStatusId, $remarks);
                        break;

                    case 'pre_decision':
                        $auditData = $this->doPreDecision($documentId, $assignmentId, $document, $userId, $currentStatusId);
                        break;

                    case 'save_processing':
                        $auditData = $this->doSaveProcessing($documentId, $assignmentId, $document, $userId, $currentStatusId);
                        break;

                    case 'submit_compliance':
                        $auditData = $this->doSubmitCompliance(
                            $documentId,
                            $assignmentId,
                            $document,
                            $userId,
                            $currentStatusId,
                            $stagedComplianceFiles
                        );
                        break;

                    case 'return_to_admin':
                        $auditData = $this->doReturnToAdmin($documentId, $assignmentId, $assignment, $document, $userId, $currentStatusId, $remarks);
                        break;
                }

                $this->pdo->commit();

                // Flush deferred file-upload audit logs now that the transaction
                // has committed (never while a transaction is still open).
                if (!empty($auditData['pendingFileLogs'])) {
                    try {
                        $this->docService->flushFileUploadLogs($auditData['pendingFileLogs']);
                    } catch (Throwable $logErr) {
                        system_log('WARNING', 'Plenary compliance file-upload log flush failed', [
                            'error'       => $logErr->getMessage(),
                            'document_id' => $documentId,
                        ]);
                    }
                }

                // Post-commit audit log (separate static PDO connection)
                if ($auditData !== null) {
                    audit_log(
                        $auditData['log_action'],
                        'Document',
                        (string) $documentId,
                        $auditData['old_values'] ?? null,
                        $auditData['new_values'] ?? null,
                        $auditData['description'] ?? null
                    );
                }

                // Notifications (best-effort, non-fatal)
                if (isset($auditData['notifyAdminRoleId'])) {
                    try {
                        $this->docService->notifyRoleUsers(
                            $auditData['notifyAdminRoleId'],
                            $documentId,
                            $userId,
                            'DOCUMENT_RETURNED',
                            "Document Returned to Admin: {$document['tracking_number']}",
                            "Plenary has returned document {$document['tracking_number']} to Admin. Reason: {$remarks}",
                            BASE_URL . "/admin/inbox/show?id={$documentId}"
                        );
                    } catch (Throwable $notifyErr) {
                        system_log('WARNING', 'Plenary return-to-admin notification failed', [
                            'error'       => $notifyErr->getMessage(),
                            'document_id' => $documentId,
                        ]);
                    }
                }

                $actionLabel = $this->actionLabel($action);
                flash_set('success', "Document processed successfully: {$actionLabel}.");
                old_clear();

                if ($action === 'return_to_admin') {
                    redirect('plenary/inbox');
                } else {
                    redirect('plenary/inbox/show?id=' . $documentId);
                }

            } catch (RuntimeException $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                $lastException = $e;
                break; // RuntimeException = business rule violation; no retry

            } catch (PDOException $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                $errorCode = (int) ($e->errorInfo[1] ?? 0);
                if (in_array($errorCode, [1205, 1213], true) && $attempt < $maxRetries) {
                    usleep($retryDelay);
                    continue; // retry on deadlock / lock timeout
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
            $message = $lastException->getMessage();
            system_log('ERROR', 'Plenary process action failed', [
                'error'       => $message,
                'document_id' => $documentId,
                'action'      => $action,
                'user_id'     => $userId,
                'attempts'    => $attempt ?? 1,
            ]);
            flash_set('error', 'Action failed: ' . $message);
            redirect('plenary/inbox/show?id=' . $documentId);
        }
    }

    // =========================================================================
    // Private action implementations
    // =========================================================================

    /**
     * Atomically claim a PENDING assignment.
     */
    private function doAccept(
        int    $documentId,
        int    $assignmentId,
        int    $userId,
        int    $plenaryRoleId,
        int    $currentStatusId,
        string $remarks
    ): array {
        $stmt = $this->pdo->prepare("
            UPDATE document_assignments
            SET decision            = 'ACCEPTED',
                accepted_at         = NOW(),
                accepted_by         = ?,
                assigned_to_user_id = ?,
                remarks             = ?
            WHERE id                  = ?
              AND assigned_to_role_id = ?
              AND decision            = 'PENDING'
              AND completed_at        IS NULL
              AND accepted_by         IS NULL
              AND (assigned_to_user_id IS NULL OR assigned_to_user_id = ?)
        ");
        $stmt->execute([
            $userId,
            $userId,
            $remarks ?: null,
            $assignmentId,
            $plenaryRoleId,
            $userId,
        ]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException(
                'This document was already accepted by another Plenary user. Please refresh the inbox.'
            );
        }

        $uStmt = $this->pdo->prepare("SELECT username FROM user_accounts WHERE id = ? LIMIT 1");
        $uStmt->execute([$userId]);
        $username = (string) ($uStmt->fetchColumn() ?: '');

        $this->pdo->prepare("
            INSERT INTO document_events (
                document_id, event_type, phase, performed_by,
                to_status_id, remarks, metadata
            ) VALUES (?, 'PLENARY_ACCEPTED', 'PLENARY', ?, ?, ?, ?)
        ")->execute([
            $documentId,
            $userId,
            $currentStatusId,
            $remarks ?: 'Document accepted by Plenary.',
            json_encode([
                'accepted_by'          => $userId,
                'accepted_by_username' => $username,
                'ip_address'           => client_ip(),
                'user_agent'           => client_user_agent(),
            ]),
        ]);

        return [
            'log_action'  => 'UPDATE',
            'new_values'  => [
                'action'               => 'plenary_accepted',
                'assignment_id'        => $assignmentId,
                'document_id'          => $documentId,
                'accepted_by'          => $userId,
                'accepted_by_username' => $username,
                'accepted_at'          => date('Y-m-d H:i:s'),
            ],
            'description' => "Plenary accepted document ID {$documentId} (user: {$username})",
        ];
    }

    /**
     * Save pre-decision status + remarks.
     *
     * Upserts a plenary_document_details row (only status_id and remarks),
     * setting or updating both fields atomically.
     */
    private function doPreDecision(
        int   $documentId,
        int   $assignmentId,
        array $document,
        int   $userId,
        int   $currentStatusId
    ): array {
        $this->assertNotTerminal($documentId);

        $statusId = (int) ($_POST['pre_decision_status_id'] ?? 0);
        $remarks  = trim($_POST['remarks'] ?? '');

        // Validate
        if ($statusId <= 0) {
            throw new InvalidArgumentException('A pre-decision status is required.');
        }

        // Verify status is one of the allowed pre-decision statuses
        $validStatusStmt = $this->pdo->prepare("
            SELECT id, name FROM document_statuses
            WHERE id = ?
              AND name IN ('Deferred','Lay on the Table','Noted','Remanded','On Process','Withdrawn')
              AND is_active = 1
              AND is_deleted = 0
            LIMIT 1
        ");
        $validStatusStmt->execute([$statusId]);
        $statusRow = $validStatusStmt->fetch();

        if (!$statusRow) {
            throw new InvalidArgumentException('Invalid pre-decision status selected.');
        }

        // Upsert plenary_document_details (only status_id + remarks at this stage;
        // preserve any existing processing data)
        $existingRow = $this->pdo->prepare(
            "SELECT id FROM plenary_document_details WHERE document_id = ? LIMIT 1"
        );
        $existingRow->execute([$documentId]);
        $existing = $existingRow->fetch();

        if ($existing) {
            $this->pdo->prepare("
                UPDATE plenary_document_details
                SET status_id  = ?,
                    remarks    = ?,
                    updated_by = ?
                WHERE document_id = ?
            ")->execute([$statusId, $remarks ?: null, $userId, $documentId]);
        } else {
            $this->pdo->prepare("
                INSERT INTO plenary_document_details
                    (document_id, status_id, remarks, created_by, updated_by)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$documentId, $statusId, $remarks ?: null, $userId, $userId]);
        }

        // Synchronize shared document status with Plenary pre-decision status
        // This ensures other divisions and users see the current status
        $this->pdo->prepare("
            UPDATE documents
            SET current_status_id = ?,
                updated_by        = ?
            WHERE id = ?
        ")->execute([$statusId, $userId, $documentId]);

        // Insert document event
        $this->pdo->prepare("
            INSERT INTO document_events (
                document_id, event_type, phase, performed_by,
                to_status_id, remarks, metadata
            ) VALUES (?, 'PLENARY_PRE_DECISION', 'PLENARY', ?, ?, ?, ?)
        ")->execute([
            $documentId,
            $userId,
            $statusId,
            $remarks ?: null,
            json_encode([
                'pre_decision_status'    => $statusRow['name'],
                'pre_decision_status_id' => $statusId,
                'ip_address'             => client_ip(),
                'user_agent'             => client_user_agent(),
            ]),
        ]);

        return [
            'log_action'  => 'UPDATE',
            'new_values'  => [
                'action'          => 'plenary_pre_decision',
                'document_id'     => $documentId,
                'status_id'       => $statusId,
                'status_name'     => $statusRow['name'],
                'remarks'         => $remarks ?: null,
            ],
            'description' => "Plenary pre-decision saved for document ID {$documentId}: {$statusRow['name']}",
        ];
    }

    /**
     * Save the full Plenary processing form.
     *
     * Only allowed when the current pre-decision status is "On Process".
     * Validates all fields server-side, upserts plenary_document_details,
     * replaces document_authors and document_committees in the same transaction.
     */
    private function doSaveProcessing(
        int   $documentId,
        int   $assignmentId,
        array $document,
        int   $userId,
        int   $currentStatusId
    ): array {
        $this->assertNotTerminal($documentId);

        // ── 1. Verify pre-decision is "On Process" ───────────────────
        $pddStmt = $this->pdo->prepare("
            SELECT pdd.id, pdd.status_id, ds.name AS status_name
            FROM plenary_document_details pdd
            LEFT JOIN document_statuses ds ON ds.id = pdd.status_id
            WHERE pdd.document_id = ?
            LIMIT 1
        ");
        $pddStmt->execute([$documentId]);
        $pdd = $pddStmt->fetch();

        if (!$pdd || strtolower(trim($pdd['status_name'] ?? '')) !== 'on process') {
            throw new RuntimeException(
                'The Plenary processing form can only be saved when the pre-decision status is "On Process".'
            );
        }

        // ── 2. Collect and validate POST fields ───────────────────────────
        $subjectMatter      = trim($_POST['subject_matter']           ?? '');
        $proposedNumber     = trim($_POST['proposed_number']          ?? '');
        $targetDate         = trim($_POST['target_first_reading_date'] ?? '');
        $sessionType        = trim($_POST['session_type']             ?? '');
        $additionalDetails  = trim($_POST['additional_details']       ?? '');
        $authorIds          = array_filter(array_map('intval', (array) ($_POST['author_ids']     ?? [])));
        $coAuthorIds        = array_filter(array_map('intval', (array) ($_POST['co_author_ids']  ?? [])));
        $committeeIds       = array_filter(array_map('intval', (array) ($_POST['committee_ids']  ?? [])));

        $errors = [];

        if ($subjectMatter === '') {
            $errors[] = 'Subject Matter is required.';
        } elseif (mb_strlen($subjectMatter) > 5000) {
            $errors[] = 'Subject Matter must not exceed 5000 characters.';
        }

        // Proposed Number: required except for exempt document types
        $documentTypeName    = strtolower(trim($document['document_type_name'] ?? ''));

        // Re-fetch document_type_name inside transaction (document row was loaded before)
        $dtStmt = $this->pdo->prepare("
            SELECT dt.name
            FROM documents d
            LEFT JOIN document_types dt ON dt.id = d.document_type_id
            WHERE d.id = ?
            LIMIT 1
        ");
        $dtStmt->execute([$documentId]);
        $dtRow = $dtStmt->fetch();
        $documentTypeName = strtolower(trim($dtRow['name'] ?? ''));

        $proposedNumberExempt = $this->isProposedNumberExempt($documentTypeName);
        $isCommunication      = $this->isCommunicationType($documentTypeName);

        if (!$isCommunication && !$proposedNumberExempt && $proposedNumber === '') {
            $errors[] = 'Proposed Number is required for this document type.';
        }

        if (!$isCommunication && $proposedNumber !== '' && mb_strlen($proposedNumber) > 50) {
            $errors[] = 'Proposed Number must not exceed 50 characters.';
        }

        // Check uniqueness of proposed_number (exclude this document)
        if (!$isCommunication && $proposedNumber !== '') {
            $dupStmt = $this->pdo->prepare("
                SELECT document_id
                FROM plenary_document_details
                WHERE proposed_number = ?
                  AND document_id    != ?
                LIMIT 1
            ");
            $dupStmt->execute([$proposedNumber, $documentId]);
            if ($dupStmt->fetch()) {
                $errors[] = 'Proposed Number "' . htmlspecialchars($proposedNumber) . '" is already used by another document.';
            }
        }

        if ($targetDate !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate) || !checkdate(
                (int) substr($targetDate, 5, 2),
                (int) substr($targetDate, 8, 2),
                (int) substr($targetDate, 0, 4)
            )) {
                $errors[] = 'Target First Reading Date must be a valid date (YYYY-MM-DD).';
            }
        }

        $validSessionTypes = ['REGULAR', 'REGULAR_ONLINE', 'SPECIAL', 'SPECIAL_ONLINE'];
        if ($sessionType !== '' && !in_array($sessionType, $validSessionTypes, true)) {
            $errors[] = 'Invalid Session Type selected.';
        }

        if (!empty($errors)) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            flash_set('errors', $errors);
            old_set($_POST);
            redirect('plenary/inbox/show?id=' . $documentId);
        }

        // ── 3. Validate author/co-author/committee IDs ────────────────────
        // These fields are hidden for Communications, so they are neither
        // required nor validated for that document type.
        if (!$isCommunication) {
            // Validate author/co-author IDs exist in sp_members
            $allMemberIds = array_unique(array_merge(
                array_values($authorIds),
                array_values($coAuthorIds)
            ));
            if (!empty($allMemberIds)) {
                $placeholders = implode(',', array_fill(0, count($allMemberIds), '?'));
                $memberCheck  = $this->pdo->prepare("
                    SELECT sp_member_id FROM sp_members
                    WHERE sp_member_id IN ({$placeholders})
                      AND is_active = 1 AND is_deleted = 0
                ");
                $memberCheck->execute(array_values($allMemberIds));
                $validMemberIds = array_column($memberCheck->fetchAll(), 'sp_member_id');
                $invalidAuthors = array_diff(array_values($authorIds), $validMemberIds);
                $invalidCoAuthors = array_diff(array_values($coAuthorIds), $validMemberIds);
                if (!empty($invalidAuthors) || !empty($invalidCoAuthors)) {
                    if ($this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                    flash_set('error', 'One or more selected authors or co-authors are invalid.');
                    old_set($_POST);
                    redirect('plenary/inbox/show?id=' . $documentId);
                }
            }

            // Validate committee IDs
            if (!empty($committeeIds)) {
                $placeholders = implode(',', array_fill(0, count($committeeIds), '?'));
                $cmtCheck     = $this->pdo->prepare("
                    SELECT id FROM committees
                    WHERE id IN ({$placeholders})
                      AND is_active = 1 AND is_deleted = 0
                ");
                $cmtCheck->execute(array_values($committeeIds));
                $validCmtIds = array_column($cmtCheck->fetchAll(), 'id');
                if (count($validCmtIds) !== count($committeeIds)) {
                    if ($this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                    flash_set('error', 'One or more selected committees are invalid.');
                    old_set($_POST);
                    redirect('plenary/inbox/show?id=' . $documentId);
                }
            }
        }

        // ── 4. Upsert plenary_document_details ────────────────────────────
        // Use INSERT ... ON DUPLICATE KEY UPDATE to prevent concurrent duplicates.
        // document_id has a UNIQUE constraint.
        $this->pdo->prepare("
            INSERT INTO plenary_document_details
                (document_id, proposed_number, target_first_reading_date,
                 session_type, additional_details, remarks,
                 status_id, created_by, updated_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                proposed_number           = VALUES(proposed_number),
                target_first_reading_date = VALUES(target_first_reading_date),
                session_type              = VALUES(session_type),
                additional_details        = VALUES(additional_details),
                updated_by                = VALUES(updated_by)
        ")->execute([
            $documentId,
            // Communications hide Proposed Number / Session Type / Additional
            // Details, so those columns are left empty for that document type.
            $isCommunication ? null : ($proposedNumber    ?: null),
            $targetDate ?: null,
            $isCommunication ? null : ($sessionType       ?: null),
            $isCommunication ? null : ($additionalDetails ?: null),
            null,  // remarks preserved from pre-decision; not overwritten here
            $pdd['status_id'],  // keep the "On Process" status_id
            $userId,
            $userId,
        ]);

        // ── 4a. Communications: the Subject Matter field updates the title ─
        if ($isCommunication) {
            $this->pdo->prepare("
                UPDATE documents
                SET subject_matter = ?,
                    updated_by     = ?
                WHERE id = ?
            ")->execute([$subjectMatter, $userId, $documentId]);
        }

        // ── 4b. Advance document status to "Awaiting First Reading" ───────
        // This moves the document into the First Reading queue.
        $awaitingFrRow = $this->pdo->query("
            SELECT id FROM document_statuses
            WHERE name = 'Awaiting First Reading' AND is_active = 1 AND is_deleted = 0
            LIMIT 1
        ")->fetch();
        if ($awaitingFrRow) {
            $this->pdo->prepare("
                UPDATE documents
                SET current_status_id = ?,
                    updated_by        = ?
                WHERE id = ?
            ")->execute([(int) $awaitingFrRow['id'], $userId, $documentId]);
        }

        // ── 5 & 6. Replace authors and committees ─────────────────────────
        // Communications do not carry authors, co-authors or committees, so
        // their existing rows (if any) are preserved untouched.
        if (!$isCommunication) {
            // Replace authors (delete-and-reinsert for clean sort_order)
            $this->pdo->prepare(
                "DELETE FROM document_authors WHERE document_id = ?"
            )->execute([$documentId]);

            $insertAuthor = $this->pdo->prepare("
                INSERT INTO document_authors
                    (document_id, sp_member_id, author_role, sort_order)
                VALUES (?, ?, ?, ?)
            ");

            $sortOrder = 0;
            foreach (array_values($authorIds) as $memberId) {
                $insertAuthor->execute([$documentId, $memberId, 'AUTHOR', $sortOrder++]);
            }
            $sortOrder = 0;
            foreach (array_values($coAuthorIds) as $memberId) {
                $insertAuthor->execute([$documentId, $memberId, 'CO_AUTHOR', $sortOrder++]);
            }

            // Replace committees
            $this->pdo->prepare(
                "DELETE FROM document_committees WHERE document_id = ?"
            )->execute([$documentId]);

            if (!empty($committeeIds)) {
                $insertCmt = $this->pdo->prepare("
                    INSERT INTO document_committees (document_id, committee_id, assigned_by)
                    VALUES (?, ?, ?)
                ");
                foreach (array_values($committeeIds) as $cid) {
                    $insertCmt->execute([$documentId, $cid, $userId]);
                }
            }
        }

        // ── 7. Insert document event ───────────────────────────────────────
        $this->pdo->prepare("
            INSERT INTO document_events (
                document_id, event_type, phase, performed_by,
                to_status_id, remarks, metadata
            ) VALUES (?, 'PLENARY_PROCESSING_SAVED', 'PLENARY', ?, ?, ?, ?)
        ")->execute([
            $documentId,
            $userId,
            $currentStatusId,
            $additionalDetails ?: null,
            json_encode([
                'proposed_number'           => $proposedNumber ?: null,
                'target_first_reading_date' => $targetDate ?: null,
                'session_type'              => $sessionType ?: null,
                'author_count'              => count(array_values($authorIds)),
                'co_author_count'           => count(array_values($coAuthorIds)),
                'committee_count'           => count(array_values($committeeIds)),
                'ip_address'                => client_ip(),
                'user_agent'                => client_user_agent(),
            ]),
        ]);

        return [
            'log_action'  => 'UPDATE',
            'new_values'  => [
                'action'                    => 'plenary_processing_saved',
                'document_id'               => $documentId,
                'proposed_number'           => $proposedNumber ?: null,
                'target_first_reading_date' => $targetDate ?: null,
                'session_type'              => $sessionType ?: null,
                'author_count'              => count(array_values($authorIds)),
                'co_author_count'           => count(array_values($coAuthorIds)),
                'committee_count'           => count(array_values($committeeIds)),
            ],
            'description' => "Plenary processing data saved for document ID {$documentId}.",
        ];
    }

    /**
     * Submit compliance updates for a Deferred document and return it to
     * processing.
     *
     * A Deferred document is on standby: the owning Plenary user records the
     * compliance remarks and attaches one or more compliance files, after which
     * the document's pre-decision status is reset to "On Process" so the normal
     * processing flow resumes.  Existing attachments, remarks and the full
     * event history are preserved (new files are appended, never replaced).
     *
     * The uploaded files are staged on disk by process() before the transaction
     * opens; this method only writes the DB rows and the audit trail.
     *
     * @param  array[] $stagedFiles  Output of DocumentService::stageFileUploads().
     */
    private function doSubmitCompliance(
        int   $documentId,
        int   $assignmentId,
        array $document,
        int   $userId,
        int   $currentStatusId,
        array $stagedFiles
    ): array {
        // ── 1. Only a Deferred document may be resumed this way ────────────
        $currentStatusName = $this->currentPreDecisionStatusName($documentId);
        if ($currentStatusName === null || strtolower(trim($currentStatusName)) !== 'deferred') {
            throw new RuntimeException(
                'Compliance updates can only be submitted while the document is Deferred.'
            );
        }

        $remarks = trim($_POST['remarks'] ?? '');
        if ($remarks === '') {
            throw new InvalidArgumentException('Remarks are required when submitting compliance updates.');
        }

        if (empty($stagedFiles)) {
            throw new InvalidArgumentException('At least one compliance file is required.');
        }

        // ── 2. Resolve the "On Process" status ─────────────────────────────
        $onProcessRow = $this->pdo->query("
            SELECT id FROM document_statuses
            WHERE name = 'On Process' AND is_active = 1 AND is_deleted = 0
            LIMIT 1
        ")->fetch();
        if (!$onProcessRow) {
            throw new RuntimeException('The "On Process" status is not configured.');
        }
        $onProcessStatusId = (int) $onProcessRow['id'];

        // ── 3. Persist the compliance files (append to existing history) ───
        $pendingFileLogs = $this->docService->insertStagedAttachments(
            $stagedFiles,
            $documentId,
            $userId,
            'PLENARY',
            'COMPLIANCE_DOC'
        );

        // ── 4. Return the document to processing ──────────────────────────
        // Reset the pre-decision status to "On Process" and append the new
        // remarks so previously recorded remarks remain visible.
        $existingStmt = $this->pdo->prepare(
            "SELECT remarks FROM plenary_document_details WHERE document_id = ? LIMIT 1"
        );
        $existingStmt->execute([$documentId]);
        $existingRemarks = (string) ($existingStmt->fetchColumn() ?: '');

        $combinedRemarks = $existingRemarks !== ''
            ? $existingRemarks . "\n\n" . $remarks
            : $remarks;

        $this->pdo->prepare("
            UPDATE plenary_document_details
            SET status_id  = ?,
                remarks    = ?,
                updated_by = ?
            WHERE document_id = ?
        ")->execute([$onProcessStatusId, $combinedRemarks, $userId, $documentId]);

        // Synchronize shared document status with Plenary pre-decision status
        // This ensures other divisions and users see the current status
        $this->pdo->prepare("
            UPDATE documents
            SET current_status_id = ?,
                updated_by        = ?
            WHERE id = ?
        ")->execute([$onProcessStatusId, $userId, $documentId]);

        // ── 5. Insert document event (history) ────────────────────────────
        $this->pdo->prepare("
            INSERT INTO document_events (
                document_id, event_type, phase, performed_by,
                to_status_id, remarks, metadata
            ) VALUES (?, 'PLENARY_COMPLIANCE_SUBMITTED', 'PLENARY', ?, ?, ?, ?)
        ")->execute([
            $documentId,
            $userId,
            $onProcessStatusId,
            $remarks,
            json_encode([
                'from_status'      => 'Deferred',
                'to_status'        => 'On Process',
                'file_count'       => count($stagedFiles),
                'compliance_files' => array_column($stagedFiles, 'original_name'),
                'ip_address'       => client_ip(),
                'user_agent'       => client_user_agent(),
            ]),
        ]);

        return [
            'log_action'  => 'UPDATE',
            'new_values'  => [
                'action'           => 'plenary_compliance_submitted',
                'document_id'      => $documentId,
                'status_name'      => 'On Process',
                'remarks'          => $remarks,
                'file_count'       => count($stagedFiles),
                'compliance_files' => array_column($stagedFiles, 'original_name'),
            ],
            'description'     => "Plenary compliance submitted for document ID {$documentId}; returned to processing.",
            'pendingFileLogs' => $pendingFileLogs,
        ];
    }

    /**
     * Return the document to Admin.
     *
     * Marks the current Plenary assignment as COMPLETED (decline_reason = remarks),
     * creates a new PENDING Admin assignment, and updates the document phase.
     */
    private function doReturnToAdmin(
        int    $documentId,
        int    $assignmentId,
        array  $assignment,
        array  $document,
        int    $userId,
        int    $currentStatusId,
        string $remarks
    ): array {
        $this->assertNotTerminal($documentId);

        if ($remarks === '') {
            throw new InvalidArgumentException('A reason is required when returning to Admin.');
        }

        // Look up Admin role
        $adminRoleRow = $this->pdo->query("
            SELECT id FROM roles
            WHERE name = 'Admin' AND is_active = 1 AND is_deleted = 0 LIMIT 1
        ")->fetch();
        if (!$adminRoleRow) {
            throw new RuntimeException("Admin role not found. Please configure roles.");
        }
        $adminRoleId = (int) $adminRoleRow['id'];

        // Look up Pending status
        $pendingStatusRow = $this->pdo->query("
            SELECT id FROM document_statuses
            WHERE name = 'Pending' AND is_active = 1 AND is_deleted = 0 LIMIT 1
        ")->fetch();
        $pendingStatusId = $pendingStatusRow ? (int) $pendingStatusRow['id'] : $currentStatusId;

        // 1. Close the current Plenary assignment
        $this->pdo->prepare("
            UPDATE document_assignments
            SET decision        = 'COMPLETED',
                completed_at    = NOW(),
                decline_reason  = ?
            WHERE id = ?
        ")->execute([$remarks, $assignmentId]);

        // 2. Update document phase back to ADMIN
        $this->pdo->prepare("
            UPDATE documents
            SET current_phase         = 'ADMIN',
                current_status_id     = ?,
                current_owner_user_id = NULL,
                updated_by            = ?
            WHERE id = ?
        ")->execute([$pendingStatusId, $userId, $documentId]);

        // 3. Create a new PENDING Admin assignment
        $this->pdo->prepare("
            INSERT INTO document_assignments (
                document_id, assigned_to_role_id, phase,
                assigned_by, decision, received_at,
                accepted_by, assigned_to_user_id
            ) VALUES (?, ?, 'ADMIN', ?, 'PENDING', NOW(), NULL, NULL)
        ")->execute([$documentId, $adminRoleId, $userId]);

        // 4. Route record: PLENARY → ADMIN
        $this->pdo->prepare("
            INSERT INTO document_routes (
                document_id, from_phase, to_phase,
                routed_by, routed_to_role_id, remarks
            ) VALUES (?, 'PLENARY', 'ADMIN', ?, ?, ?)
        ")->execute([$documentId, $userId, $adminRoleId, $remarks]);

        // 5. Document event
        $this->pdo->prepare("
            INSERT INTO document_events (
                document_id, event_type, phase, performed_by,
                to_status_id, remarks, metadata
            ) VALUES (?, 'PLENARY_RETURNED_TO_ADMIN', 'PLENARY', ?, ?, ?, ?)
        ")->execute([
            $documentId,
            $userId,
            $pendingStatusId,
            $remarks,
            json_encode([
                'from_assignment_id' => $assignmentId,
                'ip_address'         => client_ip(),
                'user_agent'         => client_user_agent(),
            ]),
        ]);

        $uStmt = $this->pdo->prepare("SELECT username FROM user_accounts WHERE id = ? LIMIT 1");
        $uStmt->execute([$userId]);
        $username = (string) ($uStmt->fetchColumn() ?: '');

        return [
            'log_action'        => 'UPDATE',
            'new_values'        => [
                'action'      => 'plenary_returned_to_admin',
                'document_id' => $documentId,
                'returned_by' => $userId,
                'username'    => $username,
                'remarks'     => $remarks,
            ],
            'description'         => "Plenary returned document ID {$documentId} to Admin. Reason: {$remarks}",
            'notifyAdminRoleId' => $adminRoleId,
        ];
    }

    // =========================================================================
    // Stub methods for downstream Plenary phases (not in scope here)
    // =========================================================================

    // =========================================================================
    // Stub passthrough methods — actual implementations in PlenaryReadingController
    // (these remain so the class satisfies any direct references;
    //  routes now point to PlenaryReadingController)
    // =========================================================================

    public function firstReadingIndex(): void  { redirect('plenary/first-reading'); }
    public function firstReadingShow(): void   { redirect('plenary/first-reading'); }
    public function firstReadingStore(): void  { redirect('plenary/first-reading'); }
    public function secondReadingIndex(): void { redirect('plenary/second-reading'); }
    public function secondReadingShow(): void  { redirect('plenary/second-reading'); }
    public function secondReadingStore(): void { redirect('plenary/second-reading'); }
    public function finalizeShow(): void       { redirect('plenary/second-reading'); }
    public function finalizeStore(): void      { redirect('plenary/second-reading'); }
    public function statusList(): void         { redirect('plenary/status-list'); }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /** Require + return the Plenary role id; halt on misconfiguration. */
    private function requirePlenaryRoleId(): int
    {
        $row = $this->pdo->query("
            SELECT id FROM roles
            WHERE name = 'Plenary' AND is_active = 1 AND is_deleted = 0 LIMIT 1
        ")->fetch();

        if (!$row) {
            flash_set('error', 'Plenary role not found. Please contact your system administrator.');
            redirect('dashboard');
        }

        return (int) $row['id'];
    }

    /**
     * Return the badge count for every tab, keyed by view slug.
     *
     * Each count mirrors the corresponding list filter exactly (same ownership
     * scoping, same ACCEPTED_STILL_PROCESSING / outcome partitioning) so a tab
     * badge and its list can never drift apart.
     *
     * @return array<string,int> e.g. ['inbox' => 3, 'accepted' => 1, 'deferred' => 2, …]
     */
    private function fetchTabCounts(int $plenaryRoleId, int $userId): array
    {
        $pendingRow = $this->pdo->prepare("
            SELECT COUNT(*) AS cnt
            FROM document_assignments
            WHERE assigned_to_role_id = ?
              AND phase               = 'PLENARY'
              AND decision            = 'PENDING'
              AND completed_at        IS NULL
              AND accepted_by         IS NULL
              AND (assigned_to_user_id IS NULL OR assigned_to_user_id = ?)
        ");
        $pendingRow->execute([$plenaryRoleId, $userId]);

        $acceptedRow = $this->pdo->prepare("
            SELECT COUNT(*) AS cnt
            FROM document_assignments da
            INNER JOIN documents d ON d.id = da.document_id
            LEFT  JOIN plenary_document_details pdd ON pdd.document_id = d.id
            WHERE da.assigned_to_role_id = ?
              AND da.phase               = 'PLENARY'
              AND da.decision            = 'ACCEPTED'
              AND da.completed_at        IS NULL
              AND (da.accepted_by = ? OR da.assigned_to_user_id = ?)
              AND (" . self::ACCEPTED_STILL_PROCESSING . ")
              AND (pdd.status_id IS NULL OR pdd.status_id NOT IN (" . self::OUTCOME_STATUS_IDS_SQL . "))
        ");
        $acceptedRow->execute([$plenaryRoleId, $userId, $userId]);

        $counts = [
            'inbox'    => (int) $pendingRow->fetchColumn(),
            'accepted' => (int) $acceptedRow->fetchColumn(),
        ];

        $outcomeStmt = $this->pdo->prepare("
            SELECT COUNT(*) AS cnt
            FROM document_assignments da
            INNER JOIN documents d   ON d.id   = da.document_id
            INNER JOIN plenary_document_details pdd ON pdd.document_id = d.id
            INNER JOIN document_statuses pds ON pds.id = pdd.status_id
            WHERE da.assigned_to_role_id = ?
              AND da.phase               = 'PLENARY'
              AND da.decision            = 'ACCEPTED'
              AND da.completed_at        IS NULL
              AND (da.accepted_by = ? OR da.assigned_to_user_id = ?)
              AND pds.name      = ?
              AND pds.is_active = 1
              AND pds.is_deleted = 0
        ");

        foreach (self::OUTCOME_TABS as $slug => $statusName) {
            $outcomeStmt->execute([$plenaryRoleId, $userId, $userId, $statusName]);
            $counts[$slug] = (int) $outcomeStmt->fetchColumn();
        }

        return $counts;
    }

    /** Fetch the plenary_document_details row with joined status info. */
    private function fetchPlenaryDetails(int $documentId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                pdd.*,
                ds.name        AS plenary_status_name,
                ds.badge_color AS plenary_status_badge_color
            FROM plenary_document_details pdd
            LEFT JOIN document_statuses ds ON ds.id = pdd.status_id
            WHERE pdd.document_id = ?
            LIMIT 1
        ");
        $stmt->execute([$documentId]);
        return $stmt->fetch() ?: null;
    }

    /** Fetch authors or co-authors for a document. */
    private function fetchAuthors(int $documentId, string $role): array
    {
        $stmt = $this->pdo->prepare("
            SELECT da.*, sm.sp_member_id,
                   CONCAT_WS(' ',
                       NULLIF(sm.first_name, ''),
                       NULLIF(sm.middle_name, ''),
                       NULLIF(sm.last_name, ''),
                       NULLIF(sm.suffix, '')
                   ) AS full_name
            FROM document_authors da
            INNER JOIN sp_members sm ON sm.sp_member_id = da.sp_member_id
            WHERE da.document_id = ?
              AND da.author_role = ?
            ORDER BY da.sort_order ASC
        ");
        $stmt->execute([$documentId, $role]);
        return $stmt->fetchAll();
    }

    /** Fetch committees assigned to a document. */
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

    /** Fetch all active committees for the form select. */
    private function fetchCommittees(): array
    {
        return $this->pdo->query("
            SELECT id, name
            FROM committees
            WHERE is_active = 1 AND is_deleted = 0
            ORDER BY name ASC
        ")->fetchAll();
    }

    /**
     * Fetch the six allowed pre-decision statuses.
     * Only returns statuses that are active and in the permitted name list.
     */
    private function fetchPreDecisionStatuses(): array
    {
        return $this->pdo->query("
            SELECT id, name, badge_color
            FROM document_statuses
            WHERE name IN (
                'Deferred', 'Lay on the Table', 'Noted',
                'Remanded', 'On Process', 'Withdrawn'
            )
              AND is_active  = 1
              AND is_deleted = 0
            ORDER BY sort_order ASC, name ASC
        ")->fetchAll();
    }

    /** Fetch all attachments for a document. */
    private function fetchAttachments(int $documentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT da.*, ua.username AS uploaded_by_username
            FROM document_attachments da
            LEFT JOIN user_accounts ua ON ua.id = da.uploaded_by
            WHERE da.document_id = ?
            ORDER BY da.created_at DESC
        ");
        $stmt->execute([$documentId]);
        return $stmt->fetchAll();
    }

    /** Fetch routing history. */
    private function fetchRoutes(int $documentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT dr.*,
                   rb.username AS routed_by_username,
                   r.name      AS routed_to_role_name
            FROM document_routes dr
            LEFT JOIN user_accounts rb ON rb.id  = dr.routed_by
            LEFT JOIN roles         r  ON r.id   = dr.routed_to_role_id
            WHERE dr.document_id = ?
            ORDER BY dr.created_at DESC
        ");
        $stmt->execute([$documentId]);
        return $stmt->fetchAll();
    }

    /** Fetch event history. */
    private function fetchEvents(int $documentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT de.*,
                   ua.username AS performed_by_username,
                   ds.name     AS to_status_name
            FROM document_events de
            LEFT JOIN user_accounts  ua ON ua.id = de.performed_by
            LEFT JOIN document_statuses ds ON ds.id = de.to_status_id
            WHERE de.document_id = ?
            ORDER BY de.created_at DESC
        ");
        $stmt->execute([$documentId]);
        return $stmt->fetchAll();
    }

    /** Fetch revision history. */
    private function fetchRevisions(int $documentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT dr.*, ua.username AS changed_by_username
            FROM document_revisions dr
            LEFT JOIN user_accounts ua ON ua.id = dr.changed_by
            WHERE dr.document_id = ?
            ORDER BY dr.revision_number DESC
        ");
        $stmt->execute([$documentId]);
        return $stmt->fetchAll();
    }

    /** Fetch all assignment history for a document. */
    private function fetchAllAssignments(int $documentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT da.*,
                   ab.username  AS accepted_by_username,
                   r.name       AS role_name
            FROM document_assignments da
            LEFT JOIN user_accounts ab ON ab.id = da.accepted_by
            LEFT JOIN roles         r  ON r.id  = da.assigned_to_role_id
            WHERE da.document_id = ?
            ORDER BY da.id DESC
        ");
        $stmt->execute([$documentId]);
        return $stmt->fetchAll();
    }

    /**
     * Determine whether the given document type name is exempt from requiring
     * a Proposed Number.  Matching is substring + case-insensitive so that
     * "Communications" and "Administrative Cases" also match.
     */
    private function isProposedNumberExempt(string $documentTypeName): bool
    {
        foreach (self::PROPOSED_NUMBER_EXEMPT_TYPES as $exemptKeyword) {
            if (str_contains($documentTypeName, $exemptKeyword)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Determine whether the given document type name is a Communication.
     * Matching is substring + case-insensitive so that "Communications" also
     * matches.  Communications are processed through a reduced Plenary form
     * that only collects the Subject Matter (title) and the Target First
     * Reading Date.
     */
    private function isCommunicationType(string $documentTypeName): bool
    {
        return str_contains(strtolower(trim($documentTypeName)), 'communication');
    }

    /** Human-readable action label for flash messages. */
    private function actionLabel(string $action): string
    {
        return match ($action) {
            'accept'            => 'Document Accepted',
            'pre_decision'      => 'Pre-Decision Status Saved',
            'save_processing'   => 'Processing Details Saved',
            'submit_compliance' => 'Compliance Submitted — Returned to Processing',
            'return_to_admin'   => 'Returned to Admin',
            default             => ucfirst(str_replace('_', ' ', $action)),
        };
    }

    /**
     * Return the document's current pre-decision status name, or null when no
     * plenary_document_details row (or status) exists yet.
     */
    private function currentPreDecisionStatusName(int $documentId): ?string
    {
        $stmt = $this->pdo->prepare("
            SELECT ds.name
            FROM plenary_document_details pdd
            LEFT JOIN document_statuses ds ON ds.id = pdd.status_id
            WHERE pdd.document_id = ?
            LIMIT 1
        ");
        $stmt->execute([$documentId]);
        $name = $stmt->fetchColumn();

        return $name === false ? null : (string) $name;
    }

    /** Whether the given pre-decision status is a terminal Plenary outcome. */
    private function isTerminalStatus(?string $statusName): bool
    {
        if ($statusName === null) {
            return false;
        }

        return in_array(strtolower(trim($statusName)), self::TERMINAL_STATUSES, true);
    }

    /**
     * Guard: refuse any further Plenary mutation once the document carries a
     * terminal pre-decision outcome (Noted / Remanded / Withdrawn).
     */
    private function assertNotTerminal(int $documentId): void
    {
        $current = $this->currentPreDecisionStatusName($documentId);

        if ($this->isTerminalStatus($current)) {
            throw new RuntimeException(
                "This document has a terminal Plenary outcome ({$current}) and can no longer be processed."
            );
        }
    }
}

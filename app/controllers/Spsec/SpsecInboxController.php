<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/DocumentService.php';

/**
 * SpsecInboxController
 *
 * Handles the SP Secretary inbox: listing pending assignments, viewing
 * document detail, processing actions (Accept / Decline / Return to Admin),
 * and uploading additional attachments.
 *
 * OWNERSHIP MODEL (mirrors AdminInboxController, migration 050):
 *   - A new document assignment arrives as PENDING with accepted_by = NULL
 *     and assigned_to_user_id = NULL.  All active SP Secretary users see it
 *     in their inbox (role-level visibility).
 *   - When a user clicks Accept, the atomic UPDATE sets:
 *       decision            = 'ACCEPTED'
 *       accepted_at         = NOW()
 *       accepted_by         = <current user id>   (accountability)
 *       assigned_to_user_id = <current user id>   (ownership gate)
 *     The WHERE clause includes:
 *       AND accepted_by IS NULL
 *       AND (assigned_to_user_id IS NULL OR assigned_to_user_id = <current user id>)
 *     rowCount() = 0 means another user already claimed it — throw concurrency error.
 *   - After acceptance the document is visible ONLY to that user in the
 *     accepted view.  Other SP Secretary users no longer see it in the inbox.
 *   - For decline / return-to-admin the ownership is re-verified before any
 *     mutation so no other user can act on an already-claimed document.
 */
class SpsecInboxController
{
    protected PDO             $pdo;
    protected DocumentService $docService;

    /** Valid actions the POST handler will accept. */
    private const VALID_ACTIONS = [
        'accept',
        'return_to_admin',
    ];

    public function __construct()
    {
        $database         = new Database();
        $this->pdo        = $database->connect();
        $this->docService = new DocumentService();
    }

    // =========================================================================
    // 1. Inbox list
    // =========================================================================

    public function index(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        $spsecRoleId = $this->requireSpsecRoleId();

        // Determine which view: inbox (pending) or accepted
        $view = trim($_GET['view'] ?? 'inbox');
        if (!in_array($view, ['inbox', 'accepted'], true)) {
            $view = 'inbox';
        }

        // Filters
        $search = trim($_GET['search'] ?? '');

        // Pagination
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        // ── Build WHERE clause based on view ──────────────────────────────────
        //
        // PENDING (inbox):
        //   Show unclaimed (assigned_to_user_id IS NULL) documents to every
        //   SP Secretary user in the role, PLUS documents pre-assigned to the
        //   current user but not yet completed.
        //   Accepted documents (accepted_by IS NOT NULL) are excluded so that
        //   once one user claims the document it immediately disappears from
        //   every other user's inbox.
        //
        // ACCEPTED:
        //   Only show documents accepted by THIS user (accepted_by = currentUserId)
        //   or explicitly assigned to this user (assigned_to_user_id = currentUserId).
        //   Never expose another user's accepted work.
        // ─────────────────────────────────────────────────────────────────────

        $where  = ['da.assigned_to_role_id = ?', "da.phase = 'SP_SECRETARY'"];
        $params = [$spsecRoleId];

        if ($view === 'inbox') {
            $where[] = "da.decision = 'PENDING'";
            $where[] = 'da.completed_at IS NULL';
            $where[] = '(da.assigned_to_user_id IS NULL OR da.assigned_to_user_id = ?)';
            $where[] = 'da.accepted_by IS NULL';
            $params[] = $userId;
        } elseif ($view === 'accepted') {
            $where[] = "da.decision = 'ACCEPTED'";
            $where[] = '(da.accepted_by = ? OR da.assigned_to_user_id = ?)';
            $params[] = $userId;
            $params[] = $userId;
        }

        if ($search !== '') {
            $where[]  = '(d.tracking_number LIKE ? OR d.subject_matter LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $whereClause = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT da.document_id) AS total
            FROM document_assignments da
            INNER JOIN documents d ON da.document_id = d.id
            WHERE {$whereClause}
        ");
        $countStmt->execute($params);
        $total      = (int) $countStmt->fetch()['total'];
        $totalPages = max(1, (int) ceil($total / $perPage));

        $listStmt = $this->pdo->prepare("
            SELECT
                da.id                        AS assignment_id,
                da.received_at,
                da.accepted_at,
                da.decision,
                da.accepted_by,
                da.assigned_to_user_id,
                d.id                         AS document_id,
                d.tracking_number,
                d.subject_matter,
                d.document_type_id,
                dt.name                      AS document_type_name,
                dt.badge_color               AS document_type_badge_color,
                d.current_phase,
                d.date_received,
                d.time_received,
                ds.name                      AS status,
                ds.badge_color               AS status_badge_color,
                st.name                      AS source_type,
                COALESCE(eo.name, h.name, m.name, d.source_name, '—') AS source_display,
                accepted_user.username       AS accepted_by_username,
                CONCAT(
                    COALESCE(accepted_info.first_name, ''),
                    ' ',
                    COALESCE(accepted_info.last_name, '')
                )                            AS accepted_by_name
            FROM document_assignments da
            INNER JOIN documents         d             ON da.document_id           = d.id
            LEFT  JOIN document_statuses ds            ON d.current_status_id      = ds.id
            LEFT  JOIN document_types    dt            ON d.document_type_id       = dt.id
            LEFT  JOIN source_types      st            ON d.source_type_id         = st.id
            LEFT  JOIN external_offices  eo            ON d.external_office_id     = eo.id
            LEFT  JOIN hospitals          h            ON d.hospital_id            = h.id
            LEFT  JOIN municities         m            ON d.municipality_id        = m.id
            LEFT  JOIN user_accounts     accepted_user ON da.accepted_by           = accepted_user.id
            LEFT  JOIN user_info         accepted_info ON accepted_user.id         = accepted_info.user_account_id
            WHERE {$whereClause}
            ORDER BY da.received_at ASC, d.date_received ASC, d.id ASC
            LIMIT ? OFFSET ?
        ");
        $listStmt->execute([...$params, $perPage, $offset]);
        $assignments = $listStmt->fetchAll();

        $success = flash_get('success');
        $error   = flash_get('error');
        $errors  = flash_get('errors') ?? [];

        // ── Statistics cards (user-scoped) ────────────────────────────────────
        $statsStmt = $this->pdo->prepare("
            SELECT
                COUNT(DISTINCT CASE
                    WHEN da.decision      = 'PENDING'
                     AND da.completed_at IS NULL
                     AND da.accepted_by  IS NULL
                     AND (da.assigned_to_user_id IS NULL OR da.assigned_to_user_id = ?)
                    THEN da.document_id
                END) AS pending_count,
                COUNT(DISTINCT CASE
                    WHEN da.decision = 'ACCEPTED'
                     AND (da.accepted_by = ? OR da.assigned_to_user_id = ?)
                    THEN da.document_id
                END) AS accepted_count,
                COUNT(DISTINCT da.document_id) AS total_count
            FROM document_assignments da
            WHERE da.assigned_to_role_id = ?
              AND da.phase               = 'SP_SECRETARY'
        ");
        $statsStmt->execute([$userId, $userId, $userId, $spsecRoleId]);
        $stats = $statsStmt->fetch();

        $pendingCount  = (int) ($stats['pending_count']  ?? 0);
        $acceptedCount = (int) ($stats['accepted_count'] ?? 0);
        $totalCount    = (int) ($stats['total_count']    ?? 0);

        $pageTitle   = 'SP Secretary Inbox';
        $currentView = $view;
        require __DIR__ . '/../../../resources/views/spsec/inbox/index.php';
    }

    // =========================================================================
    // 2. Document detail / processing page
    // =========================================================================

    public function show(): void
    {
        $userId     = auth_id();
        $documentId = (int) ($_GET['id'] ?? 0);

        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }
        if ($documentId <= 0) {
            flash_set('error', 'Invalid document ID.');
            redirect('spsec/inbox');
        }

        $spsecRoleId = $this->requireSpsecRoleId();

        // Fetch document with joins
        $docStmt = $this->pdo->prepare("
            SELECT
                d.*,
                dt.name            AS document_type_name,
                dt.badge_color     AS document_type_badge_color,
                ds.name            AS status,
                ds.badge_color     AS status_badge_color,
                st.name            AS source_type,
                eo.name            AS external_office_name,
                eo.abbreviation    AS external_office_abbr,
                h.name             AS hospital_name,
                m.name             AS municipality_name,
                m.type             AS municipality_type,
                cc.name            AS communication_category_name,
                creator.username   AS created_by_username,
                updater.username   AS updated_by_username,
                owner.username     AS current_owner_username
            FROM documents d
            LEFT  JOIN document_types    dt ON d.document_type_id    = dt.id
            LEFT  JOIN document_statuses ds ON d.current_status_id   = ds.id
            LEFT  JOIN source_types      st ON d.source_type_id      = st.id
            LEFT  JOIN external_offices  eo ON d.external_office_id  = eo.id
            LEFT  JOIN hospitals          h ON d.hospital_id          = h.id
            LEFT  JOIN municities         m ON d.municipality_id      = m.id
            LEFT  JOIN communication_categories cc ON d.communication_category_id = cc.id
            LEFT  JOIN user_accounts   creator ON d.created_by       = creator.id
            LEFT  JOIN user_accounts   updater ON d.updated_by       = updater.id
            LEFT  JOIN user_accounts     owner ON d.current_owner_user_id = owner.id
            WHERE d.id = ?
            LIMIT 1
        ");
        $docStmt->execute([$documentId]);
        $document = $docStmt->fetch();

        if (!$document) {
            flash_set('error', 'Document not found.');
            redirect('spsec/inbox');
        }

        // ── Find the current SP Secretary assignment for this document ─────────
        $assignStmt = $this->pdo->prepare("
            SELECT *
            FROM document_assignments
            WHERE document_id         = ?
              AND assigned_to_role_id = ?
              AND phase               = 'SP_SECRETARY'
              AND completed_at        IS NULL
              AND (
                  -- PENDING and unclaimed (or pre-assigned to me)
                  (decision = 'PENDING'
                   AND accepted_by IS NULL
                   AND (assigned_to_user_id IS NULL OR assigned_to_user_id = ?))
                  OR
                  -- ACCEPTED and owned by me
                  (decision = 'ACCEPTED'
                   AND (accepted_by = ? OR assigned_to_user_id = ?))
              )
            ORDER BY id ASC
            LIMIT 1
        ");
        $assignStmt->execute([$documentId, $spsecRoleId, $userId, $userId, $userId]);
        $activeAssignment = $assignStmt->fetch();

        // ── Access control for ACCEPTED documents ──────────────────────────────
        if ($activeAssignment === false) {
            $otherOwnerStmt = $this->pdo->prepare("
                SELECT id FROM document_assignments
                WHERE document_id         = ?
                  AND assigned_to_role_id = ?
                  AND phase               = 'SP_SECRETARY'
                  AND decision            = 'ACCEPTED'
                  AND completed_at        IS NULL
                  AND accepted_by         IS NOT NULL
                  AND accepted_by        != ?
                LIMIT 1
            ");
            $otherOwnerStmt->execute([$documentId, $spsecRoleId, $userId]);
            if ($otherOwnerStmt->fetch()) {
                flash_set('error', 'This document is assigned to another SP Secretary user.');
                redirect('spsec/inbox');
            }
        }

        // All assignments for history panel
        $allAssignStmt = $this->pdo->prepare("
            SELECT
                da.*,
                r.name              AS role_name,
                ua.username         AS assigned_to_username,
                ab.username         AS assigned_by_username,
                accepted_user.username
                                    AS accepted_by_username,
                CONCAT(
                    COALESCE(accepted_info.first_name, ''),
                    ' ',
                    COALESCE(accepted_info.last_name, '')
                )                   AS accepted_by_name
            FROM document_assignments da
            LEFT JOIN roles          r             ON da.assigned_to_role_id = r.id
            LEFT JOIN user_accounts  ua            ON da.assigned_to_user_id = ua.id
            LEFT JOIN user_accounts  ab            ON da.assigned_by         = ab.id
            LEFT JOIN user_accounts  accepted_user ON da.accepted_by         = accepted_user.id
            LEFT JOIN user_info      accepted_info ON accepted_user.id       = accepted_info.user_account_id
            WHERE da.document_id = ?
            ORDER BY da.created_at ASC
        ");
        $allAssignStmt->execute([$documentId]);
        $allAssignments = $allAssignStmt->fetchAll();

        // Attachments
        $attStmt = $this->pdo->prepare("
            SELECT da.*, ua.username AS uploaded_by_username
            FROM document_attachments da
            LEFT JOIN user_accounts ua ON da.uploaded_by = ua.id
            WHERE da.document_id = ?
            ORDER BY da.created_at ASC
        ");
        $attStmt->execute([$documentId]);
        $attachments = $attStmt->fetchAll();

        // Route history
        $routeStmt = $this->pdo->prepare("
            SELECT
                dr.*,
                rb.username  AS routed_by_username,
                rr.name      AS routed_to_role_name,
                ro.name      AS routing_option_name
            FROM document_routes dr
            LEFT JOIN user_accounts rb  ON dr.routed_by          = rb.id
            LEFT JOIN roles         rr  ON dr.routed_to_role_id  = rr.id
            LEFT JOIN routing_options ro ON dr.routing_option_id = ro.id
            WHERE dr.document_id = ?
            ORDER BY dr.created_at ASC
        ");
        $routeStmt->execute([$documentId]);
        $routes = $routeStmt->fetchAll();

        // Workflow events
        $eventStmt = $this->pdo->prepare("
            SELECT de.*, ua.username AS performed_by_username
            FROM document_events de
            LEFT JOIN user_accounts ua ON de.performed_by = ua.id
            WHERE de.document_id = ?
            ORDER BY de.created_at ASC
        ");
        $eventStmt->execute([$documentId]);
        $events = $eventStmt->fetchAll();

        // Revision history
        $revStmt = $this->pdo->prepare("
            SELECT dr.*, ua.username AS changed_by_username
            FROM document_revisions dr
            LEFT JOIN user_accounts ua ON dr.changed_by = ua.id
            WHERE dr.document_id = ?
            ORDER BY dr.revision_number ASC
        ");
        $revStmt->execute([$documentId]);
        $revisions = $revStmt->fetchAll();

        $success = flash_get('success');
        $error   = flash_get('error');
        $errors  = flash_get('errors') ?? [];

        // ── Derive explicit state variables for the view ──────────────────────
        $assignmentDecision = $activeAssignment !== false
            ? ($activeAssignment['decision'] ?? null)
            : null;

        $isPendingAssignment = $assignmentDecision === 'PENDING'
            && empty($activeAssignment['accepted_by'])
            && (
                empty($activeAssignment['assigned_to_user_id'])
                || (int) $activeAssignment['assigned_to_user_id'] === $userId
            );

        $isOwnedAcceptedAssignment = $assignmentDecision === 'ACCEPTED'
            && (
                (int) ($activeAssignment['accepted_by']          ?? 0) === $userId
                || (int) ($activeAssignment['assigned_to_user_id'] ?? 0) === $userId
            );

        $canAccept  = $isPendingAssignment;
        $canProcess = $isOwnedAcceptedAssignment;

        $pageTitle = 'Document Details — ' . htmlspecialchars($document['tracking_number']);
        require __DIR__ . '/../../../resources/views/spsec/inbox/show.php';
    }

    // =========================================================================
    // 3. Process action (Accept / Return to Admin)
    // =========================================================================

    public function process(): void
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
            redirect('spsec/inbox');
        }

        if (!in_array($action, self::VALID_ACTIONS, true)) {
            flash_set('error', 'Invalid action specified.');
            redirect('spsec/inbox/show?id=' . $documentId);
        }

        $spsecRoleId = $this->requireSpsecRoleId();

        // Execute with retry for transient database errors
        $maxRetries = 3;
        $retryDelay = 100000; // 100 ms in microseconds
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $storedFilePaths  = [];
            $notificationData = null;

            try {
                $this->pdo->beginTransaction();

                // ── Resolve assignment based on action ─────────────────────────
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
                    $assignStmt->execute([$documentId, $spsecRoleId, $userId]);
                } else {
                    // All non-accept actions require ownership
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
                    $assignStmt->execute([$documentId, $spsecRoleId, $userId, $userId]);
                }

                $assignment = $assignStmt->fetch();

                if (!$assignment) {
                    $this->pdo->rollBack();
                    if ($action === 'accept') {
                        flash_set('error', 'No pending SP Secretary assignment found for this document. It may have already been accepted by another user.');
                    } else {
                        flash_set('error', 'You do not have ownership of this document or it has already been processed. Only the SP Secretary user who accepted the document may perform this action.');
                    }
                    redirect('spsec/inbox/show?id=' . $documentId);
                }

                $assignmentId = (int) $assignment['id'];

                $docStmt = $this->pdo->prepare("SELECT * FROM documents WHERE id = ? LIMIT 1");
                $docStmt->execute([$documentId]);
                $document = $docStmt->fetch();

                if (!$document) {
                    $this->pdo->rollBack();
                    flash_set('error', 'Document not found.');
                    redirect('spsec/inbox');
                }

                $currentStatusId = (int) $document['current_status_id'];

                // ── Dispatch action ───────────────────────────────────────────
                switch ($action) {
                    case 'accept':
                        $this->doAccept($documentId, $assignmentId, $userId, $spsecRoleId, $currentStatusId, $remarks);
                        break;

                    case 'return_to_admin':
                        $notificationData = $this->doReturnToAdmin($documentId, $assignmentId, $assignment, $document, $userId, $currentStatusId, $remarks);
                        break;
                }

                // ── Commit ────────────────────────────────────────────────────
                // audit_log() / system_log() calls MUST come AFTER commit —
                // they use a separate static PDO connection and must not run
                // while the main transaction is open.
                $this->pdo->commit();

                // ── Post-commit: audit log ────────────────────────────────────
                if ($action === 'accept') {
                    $uStmt = $this->pdo->prepare("SELECT username FROM user_accounts WHERE id = ? LIMIT 1");
                    $uStmt->execute([$userId]);
                    $acceptingUsername = (string) ($uStmt->fetchColumn() ?: '');

                    audit_log('UPDATE', 'Document', (string) $documentId, null, [
                        'action'               => 'spsec_accepted',
                        'assignment_id'        => $assignmentId,
                        'document_id'          => $documentId,
                        'accepted_by'          => $userId,
                        'accepted_by_username' => $acceptingUsername,
                        'accepted_at'          => date('Y-m-d H:i:s'),
                    ], "SP Secretary accepted document ID {$documentId} (user: {$acceptingUsername})");

                } elseif ($notificationData !== null && isset($notificationData['auditData'])) {
                    audit_log(
                        'UPDATE',
                        'Document',
                        (string) $notificationData['documentId'],
                        null,
                        $notificationData['auditData'],
                        $notificationData['auditDescription'] ?? null
                    );
                }

                // ── Post-commit: notifications ────────────────────────────────
                if ($notificationData !== null) {
                    try {
                        if (isset($notificationData['adminRoleId'])) {
                            // Returned to Admin — notify the Admin role
                            if ($notificationData['createdBy'] > 0) {
                                $this->docService->notifyUser(
                                    $notificationData['createdBy'],
                                    $notificationData['documentId'],
                                    $notificationData['userId'],
                                    'DOCUMENT_RETURNED',
                                    "Document Returned: {$notificationData['trackingNumber']}",
                                    "SP Secretary has returned document {$notificationData['trackingNumber']} to Admin. Reason: {$notificationData['remarks']}",
                                    BASE_URL . "/admin/inbox/show?id={$notificationData['documentId']}"
                                );
                            }
                            $this->docService->notifyRoleUsers(
                                $notificationData['adminRoleId'],
                                $notificationData['documentId'],
                                $notificationData['userId'],
                                'DOCUMENT_RETURNED',
                                "Document Returned to Admin: {$notificationData['trackingNumber']}",
                                "SP Secretary has returned document {$notificationData['trackingNumber']} to Admin. Reason: {$notificationData['remarks']}",
                                BASE_URL . "/admin/inbox/show?id={$notificationData['documentId']}"
                            );
                        }
                    } catch (Throwable $notifyError) {
                        system_log('WARNING', 'Notification delivery failed after successful SP Secretary document processing', [
                            'error'       => $notifyError->getMessage(),
                            'document_id' => $documentId,
                            'action'      => $action,
                            'user_id'     => $userId,
                        ]);
                    }
                }

                // ── Success ───────────────────────────────────────────────────
                $actionLabel = $this->actionLabel($action);
                flash_set('success', "Document processed successfully: {$actionLabel}.");
                old_clear();
                redirect('spsec/inbox');

            } catch (RuntimeException $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                foreach ($storedFilePaths as $p) {
                    if (file_exists($p)) @unlink($p);
                }
                system_log('WARNING', 'SP Secretary process action: runtime error', [
                    'error'       => $e->getMessage(),
                    'document_id' => $documentId,
                    'action'      => $action,
                    'user_id'     => $userId,
                ]);
                flash_set('error', $e->getMessage());
                redirect('spsec/inbox/show?id=' . $documentId);

            } catch (InvalidArgumentException $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                foreach ($storedFilePaths as $p) {
                    if (file_exists($p)) @unlink($p);
                }
                flash_set('error', $e->getMessage());
                redirect('spsec/inbox/show?id=' . $documentId);

            } catch (PDOException $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                foreach ($storedFilePaths as $p) {
                    if (file_exists($p)) @unlink($p);
                }

                $lastException = $e;
                $errorCode     = $e->getCode();
                $errorInfo     = $e->errorInfo[1] ?? null;

                $isTransient = (
                    $errorInfo === 1205 ||  // Lock wait timeout
                    $errorInfo === 1213 ||  // Deadlock
                    $errorCode === '40001'  // Serialization failure
                );

                if ($isTransient && $attempt < $maxRetries) {
                    system_log('WARNING', 'Transient database error, retrying', [
                        'attempt'     => $attempt,
                        'error_code'  => $errorCode,
                        'error_info'  => $errorInfo,
                        'document_id' => $documentId,
                        'action'      => $action,
                    ]);
                    usleep($retryDelay * $attempt);
                    continue;
                }
                break;

            } catch (Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                foreach ($storedFilePaths as $p) {
                    if (file_exists($p)) @unlink($p);
                }
                $lastException = $e;
                break;
            }
        }

        // All retries failed
        if ($lastException !== null) {
            $message = $lastException->getMessage();
            system_log('ERROR', 'SP Secretary process action failed', [
                'error'       => $message,
                'document_id' => $documentId,
                'action'      => $action,
                'user_id'     => $userId,
                'attempts'    => $attempt,
                'trace'       => $lastException->getTraceAsString(),
            ]);
            flash_set('error', 'Action failed: ' . $message);
            redirect('spsec/inbox/show?id=' . $documentId);
        }
    }

    // =========================================================================
    // 4. Upload additional attachments
    // =========================================================================

    public function upload(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        $documentId = (int) ($_POST['document_id'] ?? 0);
        if ($documentId <= 0) {
            flash_set('error', 'Invalid document ID.');
            redirect('spsec/inbox');
        }

        $spsecRoleId = $this->requireSpsecRoleId();

        // Verify document has an active SP Secretary assignment owned by this user
        $checkStmt = $this->pdo->prepare("
            SELECT da.id
            FROM document_assignments da
            WHERE da.document_id         = ?
              AND da.assigned_to_role_id = ?
              AND da.completed_at        IS NULL
              AND (
                  (da.decision = 'PENDING'
                   AND da.accepted_by IS NULL
                   AND (da.assigned_to_user_id IS NULL OR da.assigned_to_user_id = ?))
                  OR
                  (da.decision = 'ACCEPTED'
                   AND (da.accepted_by = ? OR da.assigned_to_user_id = ?))
              )
            LIMIT 1
        ");
        $checkStmt->execute([$documentId, $spsecRoleId, $userId, $userId, $userId]);
        if (!$checkStmt->fetch()) {
            flash_set('error', 'No active SP Secretary assignment found for this document, or you do not have ownership.');
            redirect('spsec/inbox/show?id=' . $documentId);
        }

        // Validate files
        $files = $_FILES['attachments'] ?? [];
        if (empty($files['name'][0])) {
            flash_set('error', 'No files were selected for upload.');
            redirect('spsec/inbox/show?id=' . $documentId);
        }

        $fileErrors = $this->docService->validateFileUploads($files, true);
        if (!empty($fileErrors)) {
            flash_set('errors', $fileErrors);
            redirect('spsec/inbox/show?id=' . $documentId);
        }

        $storedPaths = [];
        $this->pdo->beginTransaction();

        try {
            $storedPaths = $this->docService->processAdditionalAttachments($files, $documentId, $userId, 'SP_SECRETARY');

            $pendingFileLogs = $this->docService->takePendingFileLogs();

            $eventStmt = $this->pdo->prepare("
                INSERT INTO document_events (
                    document_id, event_type, phase, performed_by,
                    remarks, metadata
                ) VALUES (?, 'DOCUMENT_EDITED', 'SP_SECRETARY', ?, ?, ?)
            ");
            $eventStmt->execute([
                $documentId,
                $userId,
                'SP Secretary uploaded additional attachments.',
                json_encode(['file_count' => count($storedPaths), 'ip_address' => client_ip()]),
            ]);

            $this->pdo->commit();

            $this->docService->flushFileUploadLogs($pendingFileLogs);

            audit_log('UPDATE', 'Document', (string) $documentId, null, [
                'action'     => 'spsec_attachment_upload',
                'file_count' => count($storedPaths),
            ], 'SP Secretary uploaded additional attachments');

            flash_set('success', count($storedPaths) . ' file(s) uploaded successfully.');
            redirect('spsec/inbox/show?id=' . $documentId);

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            foreach ($storedPaths as $p) {
                if (file_exists($p)) @unlink($p);
            }
            system_log('ERROR', 'SP Secretary attachment upload failed', [
                'error'       => $e->getMessage(),
                'document_id' => $documentId,
                'user_id'     => $userId,
            ]);
            flash_set('error', 'Upload failed: ' . $e->getMessage());
            redirect('spsec/inbox/show?id=' . $documentId);
        }
    }

    // =========================================================================
    // Action implementations
    // =========================================================================

    /**
     * Atomically claim the document assignment for the current user.
     *
     * The single UPDATE acts as a compare-and-swap:
     *   WHERE id = ?
     *     AND assigned_to_role_id = ?
     *     AND decision = 'PENDING'
     *     AND completed_at IS NULL
     *     AND accepted_by IS NULL
     *     AND (assigned_to_user_id IS NULL OR assigned_to_user_id = ?)
     *
     * rowCount() = 0 → another user won the race; throw a user-friendly error.
     * rowCount() = 1 → this user now owns the document.
     *
     * NOTE: audit_log() is deliberately omitted here — runs inside an open
     * transaction. process() writes the audit entry after commit.
     */
    private function doAccept(
        int    $documentId,
        int    $assignmentId,
        int    $userId,
        int    $spsecRoleId,
        int    $currentStatusId,
        string $remarks
    ): void {
        $stmt = $this->pdo->prepare("
            UPDATE document_assignments
            SET decision            = 'ACCEPTED',
                accepted_at         = NOW(),
                accepted_by         = ?,
                assigned_to_user_id = ?,
                remarks             = ?
            WHERE id                = ?
              AND assigned_to_role_id = ?
              AND decision          = 'PENDING'
              AND completed_at      IS NULL
              AND accepted_by       IS NULL
              AND (assigned_to_user_id IS NULL OR assigned_to_user_id = ?)
        ");
        $stmt->execute([
            $userId,
            $userId,
            $remarks ?: null,
            $assignmentId,
            $spsecRoleId,
            $userId,
        ]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException(
                'This document was already accepted by another SP Secretary user. Please refresh the inbox.'
            );
        }

        $uStmt = $this->pdo->prepare("SELECT username FROM user_accounts WHERE id = ? LIMIT 1");
        $uStmt->execute([$userId]);
        $username = (string) ($uStmt->fetchColumn() ?: '');

        $this->pdo->prepare("
            INSERT INTO document_events (
                document_id, event_type, phase, performed_by,
                to_status_id, remarks, metadata
            ) VALUES (?, 'SP_SECRETARY_ACCEPTED', 'SP_SECRETARY', ?, ?, ?, ?)
        ")->execute([
            $documentId,
            $userId,
            $currentStatusId,
            $remarks ?: 'Document accepted by SP Secretary.',
            json_encode([
                'accepted_by'          => $userId,
                'accepted_by_username' => $username,
                'ip_address'           => client_ip(),
                'user_agent'           => client_user_agent(),
            ]),
        ]);
    }

    /**
     * Return the document to Admin with a required reason.
     *
     * Only the user who accepted the document may return it.
     * Ownership is verified in process() before dispatching here.
     *
     * Creates a fresh pending Admin assignment with:
     *   phase                = 'ADMIN'
     *   accepted_by          = NULL
     *   assigned_to_user_id  = NULL
     *
     * Preserves accepted_by and accepted_at in the completed SP Secretary row
     * so the history retains who originally accepted the document.
     *
     * @return array Notification data sent after commit.
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
        if ($remarks === '') {
            throw new InvalidArgumentException('A reason is required when returning to Admin.');
        }

        // 1. Snapshot revision before return
        $revNum = $this->docService->nextRevisionNumber($documentId);
        $this->pdo->prepare("
            INSERT INTO document_revisions (
                document_id, revision_number, changed_by, phase,
                subject_matter, document_type_id, date_received, time_received,
                source_type_id, source_snapshot, remarks, change_reason
            ) VALUES (?, ?, ?, 'SP_SECRETARY', ?, ?, ?, ?, ?, ?, ?, 'Document returned to Admin by SP Secretary — snapshot before return')
        ")->execute([
            $documentId,
            $revNum,
            $userId,
            $document['subject_matter'],
            $document['document_type_id'],
            $document['date_received'],
            $document['time_received'],
            $document['source_type_id'],
            json_encode([
                'external_office_id'    => $document['external_office_id'],
                'hospital_id'           => $document['hospital_id'],
                'municipality_id'       => $document['municipality_id'],
                'source_name'           => $document['source_name'],
                'source_contact_number' => $document['source_contact_number'],
                'source_address'        => $document['source_address'],
                'source_liaison_name'   => $document['source_liaison_name'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $document['remarks'],
            'Document returned to Admin by SP Secretary — snapshot before return',
        ]);

        // 2. Mark SP Secretary assignment DECLINED — accepted_by / accepted_at
        //    are preserved (NOT cleared) so history retains who had the document.
        $stmt = $this->pdo->prepare("
            UPDATE document_assignments
            SET decision       = 'DECLINED',
                declined_at    = NOW(),
                completed_at   = NOW(),
                decline_reason = ?,
                remarks        = ?
            WHERE id           = ?
              AND decision     = 'ACCEPTED'
              AND completed_at IS NULL
              AND (accepted_by = ? OR assigned_to_user_id = ?)
        ");
        $stmt->execute([$remarks, $remarks, $assignmentId, $userId, $userId]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException(
                'This assignment has already been processed or you no longer own it. Please refresh the page.'
            );
        }

        // 3. Resolve Admin role
        $adminRole = $this->pdo->query(
            "SELECT id FROM roles WHERE name = 'Admin' AND is_active = 1 AND is_deleted = 0 LIMIT 1"
        )->fetch();
        if (!$adminRole) {
            throw new RuntimeException('Admin role not found.');
        }
        $adminRoleId = (int) $adminRole['id'];

        // 4. New PENDING assignment for Admin — fresh slate, no previous owner
        $this->pdo->prepare("
            INSERT INTO document_assignments (
                document_id, assigned_to_role_id, phase,
                assigned_by, decision, received_at,
                accepted_by, assigned_to_user_id
            ) VALUES (?, ?, 'ADMIN', ?, 'PENDING', NOW(), NULL, NULL)
        ")->execute([$documentId, $adminRoleId, $userId]);

        // 5. Restore document phase / owner
        $this->pdo->prepare("
            UPDATE documents
            SET current_phase         = 'ADMIN',
                current_owner_user_id = NULL,
                updated_by            = ?
            WHERE id = ?
        ")->execute([$userId, $documentId]);

        // 6. Route record for the return
        $this->pdo->prepare("
            INSERT INTO document_routes (
                document_id, from_phase, to_phase,
                routed_by, routed_to_role_id, remarks
            ) VALUES (?, 'SP_SECRETARY', 'ADMIN', ?, ?, ?)
        ")->execute([
            $documentId,
            $userId,
            $adminRoleId,
            'Returned by SP Secretary to Admin: ' . $remarks,
        ]);

        // 7. SP_SECRETARY_REJECTED event
        $this->pdo->prepare("
            INSERT INTO document_events (
                document_id, event_type, phase, performed_by,
                to_status_id, remarks, metadata
            ) VALUES (?, 'SP_SECRETARY_REJECTED', 'SP_SECRETARY', ?, ?, ?, ?)
        ")->execute([
            $documentId,
            $userId,
            $currentStatusId,
            $remarks,
            json_encode(['ip_address' => client_ip()]),
        ]);

        // 8. SP_SECRETARY_RETURNED_TO_ADMIN event
        $this->pdo->prepare("
            INSERT INTO document_events (
                document_id, event_type, phase, performed_by,
                to_status_id, remarks, metadata
            ) VALUES (?, 'SP_SECRETARY_RETURNED_TO_ADMIN', 'ADMIN', ?, ?, ?, ?)
        ")->execute([
            $documentId,
            $userId,
            $currentStatusId,
            'Document returned to Admin for correction.',
            json_encode([
                'decline_reason' => $remarks,
                'ip_address'     => client_ip(),
            ]),
        ]);

        // audit_log() deliberately omitted (open transaction) — process() writes after commit.

        $createdBy = (int) $document['created_by'];
        return [
            'createdBy'       => $createdBy,
            'adminRoleId'     => $adminRoleId,
            'documentId'      => $documentId,
            'userId'          => $userId,
            'trackingNumber'  => $document['tracking_number'],
            'remarks'         => $remarks,
            'auditData'       => [
                'action'         => 'spsec_returned_to_admin',
                'assignment_id'  => $assignmentId,
                'document_id'    => $documentId,
                'accepted_by'    => $assignment['accepted_by'],
                'returned_by'    => $userId,
                'return_reason'  => mb_substr($remarks, 0, 200),
            ],
            'auditDescription' => "SP Secretary returned document ID {$documentId} to Admin",
        ];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** Require and return the SP Secretary role ID; redirect if not found. */
    private function requireSpsecRoleId(): int
    {
        $stmt = $this->pdo->query(
            "SELECT id FROM roles WHERE name = 'SP Secretary' AND is_active = 1 AND is_deleted = 0 LIMIT 1"
        );
        $row = $stmt->fetch();
        if (!$row) {
            flash_set('error', 'SP Secretary role not found. Please contact the system administrator.');
            redirect('dashboard');
        }
        return (int) $row['id'];
    }

    /** Human-readable label for an action key. */
    private function actionLabel(string $action): string
    {
        return match ($action) {
            'accept'          => 'Accepted',
            'return_to_admin' => 'Returned to Admin',
            default           => $action,
        };
    }
}

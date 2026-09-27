<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/DocumentService.php';

/**
 * CommitteeInboxController
 *
 * Handles the Committee inbox: listing pending assignments, viewing document
 * detail, processing actions (Accept / Return to Admin), and uploading
 * additional attachments.
 *
 * OWNERSHIP MODEL (mirrors SpsecInboxController, migration 050):
 *   - A new document assignment arrives as PENDING with accepted_by = NULL
 *     and assigned_to_user_id = NULL.  All active Committee users see it in
 *     their inbox (role-level visibility).
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
 *     accepted view.  Other Committee users no longer see it in the inbox.
 *   - For return-to-admin the ownership is re-verified before any mutation so
 *     no other user can act on an already-claimed document.
 *
 * AUTHORIZATION:
 *   - RoleMiddleware restricts all committee/* routes to ['Super Admin', 'Committee'].
 *   - Within the controller, every action additionally re-checks document
 *     ownership against the committee_role_id and accepted_by columns so that
 *     a Committee user cannot view or modify documents owned by another user.
 */
class CommitteeInboxController
{
    protected PDO             $pdo;
    protected DocumentService $docService;

    /** Valid actions the POST handler will accept. */
    private const VALID_ACTIONS = [
        'accept',
        'return_to_admin',
        'endorse',
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

        $committeeRoleId = $this->requireCommitteeRoleId();

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
        //   Committee user in the role, PLUS documents pre-assigned to the
        //   current user but not yet completed.
        //   Accepted documents (accepted_by IS NOT NULL) are excluded so that
        //   once one user claims the document it immediately disappears from
        //   every other user's inbox.
        //
        // ACCEPTED:
        //   Only show documents accepted by THIS user (accepted_by = userId)
        //   or explicitly assigned to this user (assigned_to_user_id = userId).
        //   Never expose another user's accepted work.
        // ─────────────────────────────────────────────────────────────────────

        $where  = ['da.assigned_to_role_id = ?', "da.phase = 'COMMITTEE'"];
        $params = [$committeeRoleId];

        if ($view === 'inbox') {
            $where[] = "da.decision = 'PENDING'";
            $where[] = 'da.completed_at IS NULL';
            $where[] = '(da.assigned_to_user_id IS NULL OR da.assigned_to_user_id = ?)';
            $where[] = 'da.accepted_by IS NULL';
            $params[] = $userId;
        } elseif ($view === 'accepted') {
            $where[] = "da.decision = 'ACCEPTED'";
            $where[] = 'da.completed_at IS NULL';
            $where[] = '(da.accepted_by = ? OR da.assigned_to_user_id = ?)';
            $params[] = $userId;
            $params[] = $userId;
            // Exclude documents that have already been processed into their
            // respective workflow (Cases or Communications).
            $where[] = "(
                (SELECT dt_inner.name FROM document_types dt_inner WHERE dt_inner.id = d.document_type_id LIMIT 1)
                    NOT IN ('Complaint', 'Administrative Cases', 'Communication')
                OR (
                    (SELECT dt_inner.name FROM document_types dt_inner WHERE dt_inner.id = d.document_type_id LIMIT 1)
                        IN ('Complaint', 'Administrative Cases')
                    AND NOT EXISTS (
                        SELECT 1 FROM committee_cases cc
                        WHERE cc.document_id = da.document_id
                    )
                )
                OR (
                    (SELECT dt_inner.name FROM document_types dt_inner WHERE dt_inner.id = d.document_type_id LIMIT 1)
                        = 'Communication'
                    AND NOT EXISTS (
                        SELECT 1 FROM committee_communications ccomm
                        WHERE ccomm.document_id = da.document_id
                    )
                )
            )";
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
                GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') AS committee_names,
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
            LEFT  JOIN document_committees dc          ON d.id                     = dc.document_id
            LEFT  JOIN committees         c            ON dc.committee_id          = c.id
            LEFT  JOIN user_accounts     accepted_user ON da.accepted_by           = accepted_user.id
            LEFT  JOIN user_info         accepted_info ON accepted_user.id         = accepted_info.user_account_id
            WHERE {$whereClause}
            GROUP BY
                da.id, da.received_at, da.accepted_at, da.decision,
                da.accepted_by, da.assigned_to_user_id,
                d.id, d.tracking_number, d.subject_matter, d.document_type_id,
                dt.name, dt.badge_color, d.current_phase, d.date_received, d.time_received,
                ds.name, ds.badge_color, st.name, eo.name, h.name, m.name, d.source_name,
                accepted_user.username, accepted_info.first_name, accepted_info.last_name
            ORDER BY da.received_at ASC, d.date_received ASC, d.id ASC
            LIMIT ? OFFSET ?
        ");
        $listStmt->execute([...$params, $perPage, $offset]);
        $assignments = $listStmt->fetchAll();

        $success = flash_get('success');
        $error   = flash_get('error');
        $errors  = flash_get('errors') ?? [];

        // ── Statistics cards (user-scoped) ────────────────────────────────────
        // The accepted_count mirrors the Accepted tab filter exactly:
        //   - decision = ACCEPTED, completed_at IS NULL, owned by this user
        //   - Complaints / Administrative Cases with an existing committee_cases
        //     record are excluded (already processed into Cases workflow).
        //   - Communications with an existing committee_communications record
        //     are excluded (already processed into Communications workflow).
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
                    WHEN da.decision     = 'ACCEPTED'
                     AND da.completed_at IS NULL
                     AND (da.accepted_by = ? OR da.assigned_to_user_id = ?)
                     AND (
                         (SELECT dt_s.name FROM document_types dt_s WHERE dt_s.id = d.document_type_id LIMIT 1)
                             NOT IN ('Complaint', 'Administrative Cases', 'Communication')
                         OR (
                             (SELECT dt_s.name FROM document_types dt_s WHERE dt_s.id = d.document_type_id LIMIT 1)
                                 IN ('Complaint', 'Administrative Cases')
                             AND NOT EXISTS (
                                 SELECT 1 FROM committee_cases cc
                                 WHERE cc.document_id = da.document_id
                             )
                         )
                         OR (
                             (SELECT dt_s.name FROM document_types dt_s WHERE dt_s.id = d.document_type_id LIMIT 1)
                                 = 'Communication'
                             AND NOT EXISTS (
                                 SELECT 1 FROM committee_communications ccomm
                                 WHERE ccomm.document_id = da.document_id
                             )
                         )
                     )
                    THEN da.document_id
                END) AS accepted_count,
                COUNT(DISTINCT da.document_id) AS total_count
            FROM document_assignments da
            INNER JOIN documents d ON da.document_id = d.id
            WHERE da.assigned_to_role_id = ?
              AND da.phase               = 'COMMITTEE'
        ");
        $statsStmt->execute([$userId, $userId, $userId, $committeeRoleId]);
        $stats = $statsStmt->fetch();

        $pendingCount  = (int) ($stats['pending_count']  ?? 0);
        $acceptedCount = (int) ($stats['accepted_count'] ?? 0);
        $totalCount    = (int) ($stats['total_count']    ?? 0);

        $pageTitle   = 'Committee Inbox';
        $currentView = $view;
        require __DIR__ . '/../../../resources/views/committee/inbox/index.php';
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
            redirect('committee/inbox');
        }

        $committeeRoleId = $this->requireCommitteeRoleId();

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
            redirect('committee/inbox');
        }

        // ── Find the current Committee assignment for this document ────────────
        $assignStmt = $this->pdo->prepare("
            SELECT *
            FROM document_assignments
            WHERE document_id         = ?
              AND assigned_to_role_id = ?
              AND phase               = 'COMMITTEE'
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
        $assignStmt->execute([$documentId, $committeeRoleId, $userId, $userId, $userId]);
        $activeAssignment = $assignStmt->fetch();

        // ── Access control for ACCEPTED documents ──────────────────────────────
        // If the document is ACCEPTED by a different user, deny access entirely.
        if ($activeAssignment === false) {
            $otherOwnerStmt = $this->pdo->prepare("
                SELECT id FROM document_assignments
                WHERE document_id         = ?
                  AND assigned_to_role_id = ?
                  AND phase               = 'COMMITTEE'
                  AND decision            = 'ACCEPTED'
                  AND completed_at        IS NULL
                  AND accepted_by         IS NOT NULL
                  AND accepted_by        != ?
                LIMIT 1
            ");
            $otherOwnerStmt->execute([$documentId, $committeeRoleId, $userId]);
            if ($otherOwnerStmt->fetch()) {
                flash_set('error', 'This document is assigned to another Committee user.');
                redirect('committee/inbox');
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

        // Named committee assignments for this document
        $committeeAssignStmt = $this->pdo->prepare("
            SELECT dc.*, c.name AS committee_name, c.description AS committee_description,
                   ab.username AS assigned_by_username
            FROM document_committees dc
            INNER JOIN committees    c  ON dc.committee_id = c.id
            LEFT  JOIN user_accounts ab ON dc.assigned_by  = ab.id
            WHERE dc.document_id = ?
            ORDER BY c.name ASC
        ");
        $committeeAssignStmt->execute([$documentId]);
        $committeeAssignments = $committeeAssignStmt->fetchAll();

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
                rb.username   AS routed_by_username,
                rr.name       AS routed_to_role_name,
                ro.name       AS routing_option_name
            FROM document_routes dr
            LEFT JOIN user_accounts  rb ON dr.routed_by          = rb.id
            LEFT JOIN roles          rr ON dr.routed_to_role_id  = rr.id
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

        // ── Committee Cases: check eligibility + existence for the button ─────
        // Eligible document types for the Cases workflow.
        $caseEligibleTypes = ['Administrative Cases', 'Complaint'];
        $isCaseEligibleType = in_array(
            $document['document_type_name'] ?? '',
            $caseEligibleTypes,
            true
        );

        // Check if a case record already exists for this document.
        $existingCase = null;
        if ($isCaseEligibleType) {
            $existingCaseStmt = $this->pdo->prepare(
                "SELECT id, docket_number FROM committee_cases WHERE document_id = ? LIMIT 1"
            );
            $existingCaseStmt->execute([$documentId]);
            $existingCase = $existingCaseStmt->fetch() ?: null;
        }

        // Show the "Proceed to Cases" button only when:
        //  1. Document type is eligible.
        //  2. This user owns the accepted assignment.
        //  3. No case record exists yet.
        $showProceedToCases = $isCaseEligibleType
            && $isOwnedAcceptedAssignment
            && $existingCase === null;

        // ── Committee Communications: check eligibility + existence ───────────
        // Eligible document type for the Communications workflow.
        $commEligibleType   = 'Communication';
        $isCommEligibleType = ($document['document_type_name'] ?? '') === $commEligibleType;

        // Check if a communication record already exists for this document.
        $existingComm = null;
        if ($isCommEligibleType) {
            $existingCommStmt = $this->pdo->prepare(
                "SELECT id, subject FROM committee_communications WHERE document_id = ? LIMIT 1"
            );
            $existingCommStmt->execute([$documentId]);
            $existingComm = $existingCommStmt->fetch() ?: null;
        }

        // Show "Proceed to Communications" button only when:
        //  1. Document type is "Communication".
        //  2. This user owns the accepted assignment.
        //  3. No communication record exists yet.
        $showProceedToComms = $isCommEligibleType
            && $isOwnedAcceptedAssignment
            && $existingComm === null;

        $pageTitle = 'Document Details — ' . htmlspecialchars($document['tracking_number']);
        require __DIR__ . '/../../../resources/views/committee/inbox/show.php';
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
            redirect('committee/inbox');
        }

        if (!in_array($action, self::VALID_ACTIONS, true)) {
            flash_set('error', 'Invalid action specified.');
            redirect('committee/inbox/show?id=' . $documentId);
        }

        $committeeRoleId = $this->requireCommitteeRoleId();

        // Execute with retry for transient database errors
        $maxRetries = 3;
        $retryDelay = 100000; // 100 ms in microseconds
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $storedFilePaths  = [];
            $notificationData = null;
            $endorseAuditData = null;

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
                    $assignStmt->execute([$documentId, $committeeRoleId, $userId]);
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
                    $assignStmt->execute([$documentId, $committeeRoleId, $userId, $userId]);
                }

                $assignment = $assignStmt->fetch();

                if (!$assignment) {
                    $this->pdo->rollBack();
                    if ($action === 'accept') {
                        flash_set('error', 'No pending Committee assignment found for this document. It may have already been accepted by another user.');
                    } else {
                        flash_set('error', 'You do not have ownership of this document or it has already been processed. Only the Committee user who accepted the document may perform this action.');
                    }
                    redirect('committee/inbox/show?id=' . $documentId);
                }

                $assignmentId = (int) $assignment['id'];

                $docStmt = $this->pdo->prepare("SELECT * FROM documents WHERE id = ? LIMIT 1");
                $docStmt->execute([$documentId]);
                $document = $docStmt->fetch();

                if (!$document) {
                    $this->pdo->rollBack();
                    flash_set('error', 'Document not found.');
                    redirect('committee/inbox');
                }

                $currentStatusId = (int) $document['current_status_id'];

                // ── Dispatch action ───────────────────────────────────────────
                switch ($action) {
                    case 'accept':
                        $this->doAccept($documentId, $assignmentId, $userId, $committeeRoleId, $currentStatusId, $remarks);
                        break;

                    case 'return_to_admin':
                        $notificationData = $this->doReturnToAdmin($documentId, $assignmentId, $assignment, $document, $userId, $currentStatusId, $remarks);
                        break;

                    case 'endorse':
                        $endorseAuditData = $this->doEndorseReferred($documentId, $assignmentId, $assignment, $document, $userId, $currentStatusId, $remarks);
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
                        'action'               => 'committee_accepted',
                        'assignment_id'        => $assignmentId,
                        'document_id'          => $documentId,
                        'accepted_by'          => $userId,
                        'accepted_by_username' => $acceptingUsername,
                        'accepted_at'          => date('Y-m-d H:i:s'),
                    ], "Committee accepted document ID {$documentId} (user: {$acceptingUsername})");

                } elseif ($notificationData !== null && isset($notificationData['auditData'])) {
                    audit_log(
                        'UPDATE',
                        'Document',
                        (string) $notificationData['documentId'],
                        null,
                        $notificationData['auditData'],
                        $notificationData['auditDescription'] ?? null
                    );
                } elseif ($action === 'endorse' && isset($endorseAuditData)) {
                    audit_log(
                        'UPDATE',
                        'Document',
                        (string) $documentId,
                        null,
                        $endorseAuditData['auditData'],
                        $endorseAuditData['auditDescription'] ?? null
                    );
                }

                // ── Post-commit: notifications ────────────────────────────────
                if ($notificationData !== null) {
                    try {
                        if (isset($notificationData['adminRoleId'])) {
                            // Returned to Admin — notify Admin role users
                            if ($notificationData['createdBy'] > 0) {
                                $this->docService->notifyUser(
                                    $notificationData['createdBy'],
                                    $notificationData['documentId'],
                                    $notificationData['userId'],
                                    'DOCUMENT_RETURNED',
                                    "Document Returned: {$notificationData['trackingNumber']}",
                                    "Committee has returned document {$notificationData['trackingNumber']} to Admin. Reason: {$notificationData['remarks']}",
                                    BASE_URL . "/admin/inbox/show?id={$notificationData['documentId']}"
                                );
                            }
                            $this->docService->notifyRoleUsers(
                                $notificationData['adminRoleId'],
                                $notificationData['documentId'],
                                $notificationData['userId'],
                                'DOCUMENT_RETURNED',
                                "Document Returned to Admin: {$notificationData['trackingNumber']}",
                                "Committee has returned document {$notificationData['trackingNumber']} to Admin. Reason: {$notificationData['remarks']}",
                                BASE_URL . "/admin/inbox/show?id={$notificationData['documentId']}"
                            );
                        }
                    } catch (Throwable $notifyError) {
                        system_log('WARNING', 'Notification delivery failed after successful Committee document processing', [
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
                if ($action === 'endorse') {
                    redirect('committee/referred');
                } else {
                    redirect('committee/inbox');
                }

            } catch (RuntimeException $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                foreach ($storedFilePaths as $p) {
                    if (file_exists($p)) @unlink($p);
                }
                system_log('WARNING', 'Committee process action: runtime error', [
                    'error'       => $e->getMessage(),
                    'document_id' => $documentId,
                    'action'      => $action,
                    'user_id'     => $userId,
                ]);
                flash_set('error', $e->getMessage());
                redirect('committee/inbox/show?id=' . $documentId);

            } catch (InvalidArgumentException $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                foreach ($storedFilePaths as $p) {
                    if (file_exists($p)) @unlink($p);
                }
                flash_set('error', $e->getMessage());
                redirect('committee/inbox/show?id=' . $documentId);

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
                    system_log('WARNING', 'Transient database error in Committee process, retrying', [
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
            system_log('ERROR', 'Committee process action failed', [
                'error'       => $message,
                'document_id' => $documentId,
                'action'      => $action,
                'user_id'     => $userId,
                'attempts'    => $attempt,
                'trace'       => $lastException->getTraceAsString(),
            ]);
            flash_set('error', 'Action failed: ' . $message);
            redirect('committee/inbox/show?id=' . $documentId);
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
            redirect('committee/inbox');
        }

        $committeeRoleId = $this->requireCommitteeRoleId();

        // Verify document has an active Committee assignment owned by this user
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
        $checkStmt->execute([$documentId, $committeeRoleId, $userId, $userId, $userId]);
        if (!$checkStmt->fetch()) {
            flash_set('error', 'No active Committee assignment found for this document, or you do not have ownership.');
            redirect('committee/inbox/show?id=' . $documentId);
        }

        $files = $_FILES['attachments'] ?? [];

        if (empty($files['name'][0])) {
            flash_set('error', 'No files were selected for upload.');
            redirect('committee/inbox/show?id=' . $documentId);
        }

        $validationErrors = $this->docService->validateFileUploads($files);
        if (!empty($validationErrors)) {
            flash_set('errors', $validationErrors);
            redirect('committee/inbox/show?id=' . $documentId);
        }

        try {
            $storedPaths = $this->docService->processAdditionalAttachments(
                $files,
                $documentId,
                $userId,
                'COMMITTEE'
            );

            $fileCount = count($storedPaths);
            flash_set('success', "{$fileCount} file(s) uploaded successfully.");

            audit_log('CREATE', 'Document', (string) $documentId, null, [
                'action'      => 'committee_attachment_upload',
                'document_id' => $documentId,
                'file_count'  => $fileCount,
            ], "Committee user uploaded {$fileCount} attachment(s) to document ID {$documentId}");

        } catch (Throwable $e) {
            system_log('ERROR', 'Committee attachment upload failed', [
                'error'       => $e->getMessage(),
                'document_id' => $documentId,
                'user_id'     => $userId,
            ]);
            flash_set('error', 'File upload failed: ' . $e->getMessage());
        }

        redirect('committee/inbox/show?id=' . $documentId);
    }

    // =========================================================================
    // Private action implementations
    // =========================================================================

    /**
     * Atomically claim a PENDING assignment for the current user.
     *
     * Uses a WHERE clause that checks accepted_by IS NULL so that only one
     * concurrent user can succeed — all others get rowCount() = 0 and receive
     * a concurrency error.
     *
     * NOTE: audit_log() is deliberately omitted here — runs inside an open
     * transaction. process() writes the audit entry after commit.
     */
    private function doAccept(
        int    $documentId,
        int    $assignmentId,
        int    $userId,
        int    $committeeRoleId,
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
            $committeeRoleId,
            $userId,
        ]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException(
                'This document was already accepted by another Committee user. Please refresh the inbox.'
            );
        }

        $uStmt = $this->pdo->prepare("SELECT username FROM user_accounts WHERE id = ? LIMIT 1");
        $uStmt->execute([$userId]);
        $username = (string) ($uStmt->fetchColumn() ?: '');

        $this->pdo->prepare("
            INSERT INTO document_events (
                document_id, event_type, phase, performed_by,
                to_status_id, remarks, metadata
            ) VALUES (?, 'COMMITTEE_ACCEPTED', 'COMMITTEE', ?, ?, ?, ?)
        ")->execute([
            $documentId,
            $userId,
            $currentStatusId,
            $remarks ?: 'Document accepted by Committee.',
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
     * Preserves accepted_by and accepted_at in the completed Committee row
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
            ) VALUES (?, ?, ?, 'COMMITTEE', ?, ?, ?, ?, ?, ?, ?, 'Document returned to Admin by Committee — snapshot before return')
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
            'Document returned to Admin by Committee — snapshot before return',
        ]);

        // 2. Mark Committee assignment DECLINED — accepted_by / accepted_at
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
            ) VALUES (?, 'COMMITTEE', 'ADMIN', ?, ?, ?)
        ")->execute([
            $documentId,
            $userId,
            $adminRoleId,
            'Returned by Committee to Admin: ' . $remarks,
        ]);

        // 7. COMMITTEE_RETURNED_TO_ADMIN event (requires migration 051)
        $this->pdo->prepare("
            INSERT INTO document_events (
                document_id, event_type, phase, performed_by,
                to_status_id, remarks, metadata
            ) VALUES (?, 'COMMITTEE_RETURNED_TO_ADMIN', 'ADMIN', ?, ?, ?, ?)
        ")->execute([
            $documentId,
            $userId,
            $currentStatusId,
            'Document returned to Admin for re-routing.',
            json_encode([
                'return_reason' => $remarks,
                'ip_address'    => client_ip(),
            ]),
        ]);

        // audit_log() deliberately omitted (open transaction) — process() writes after commit.

        $createdBy = (int) $document['created_by'];
        return [
            'createdBy'        => $createdBy,
            'adminRoleId'      => $adminRoleId,
            'documentId'       => $documentId,
            'userId'           => $userId,
            'trackingNumber'   => $document['tracking_number'],
            'remarks'          => $remarks,
            'auditData'        => [
                'action'        => 'committee_returned_to_admin',
                'assignment_id' => $assignmentId,
                'document_id'   => $documentId,
                'accepted_by'   => $assignment['accepted_by'],
                'returned_by'   => $userId,
                'return_reason' => mb_substr($remarks, 0, 200),
            ],
            'auditDescription' => "Committee returned document ID {$documentId} to Admin",
        ];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Proceed to Endorsement — marks the document as REFERRED.
     *
     * Only the user who accepted the assignment may call this action.
     * Ownership is re-verified inside process() before dispatch here.
     *
     * What this does inside the transaction:
     *   1. Guard against duplicate endorsement (committee_cycles with
     *      completed_at IS NOT NULL already exists for this document).
     *   2. Complete the current ACCEPTED Committee assignment
     *      (decision = COMPLETED, completed_at = NOW()).
     *   3. Update the document's current_status_id to 8 ('Referred').
     *      The current_phase stays 'COMMITTEE' — the document remains in the
     *      Committee phase but its status now reflects the endorsed state.
     *   4. Create (or complete) a committee_cycles row to record the cycle.
     *   5. Insert a COMMITTEE_ENDORSED_REFERRED document_event.
     *   6. Insert a document_routes record (COMMITTEE → COMMITTEE, same phase)
     *      for the audit trail.
     *
     * Returns an array of audit data written after commit by process().
     *
     * NOTE: audit_log() is deliberately omitted here — runs inside an open
     * transaction. process() writes the audit entry after commit.
     *
     * FUTURE PHASE HOOK: After this action succeeds the document will appear
     * in the Referred Documents list. Phase 2 will allow the Committee user
     * to endorse the referred document to one or more Opinion Offices.
     */
    private function doEndorseReferred(
        int    $documentId,
        int    $assignmentId,
        array  $assignment,
        array  $document,
        int    $userId,
        int    $currentStatusId,
        string $remarks
    ): array {
        if ($remarks === '') {
            throw new InvalidArgumentException('Remarks are required when proceeding to endorsement.');
        }

        // 1. Guard: reject if a completed endorsement cycle already exists
        $dupStmt = $this->pdo->prepare("
            SELECT id FROM committee_cycles
            WHERE document_id   = ?
              AND completed_at  IS NOT NULL
            LIMIT 1
        ");
        $dupStmt->execute([$documentId]);
        if ($dupStmt->fetch()) {
            throw new RuntimeException(
                'This document has already been endorsed (Referred). Duplicate endorsement is not allowed.'
            );
        }

        // 2. Complete the ACCEPTED assignment (owned by this user)
        $completeStmt = $this->pdo->prepare("
            UPDATE document_assignments
            SET decision     = 'COMPLETED',
                completed_at = NOW(),
                remarks      = ?
            WHERE id           = ?
              AND decision     = 'ACCEPTED'
              AND completed_at IS NULL
              AND (accepted_by = ? OR assigned_to_user_id = ?)
        ");
        $completeStmt->execute([$remarks, $assignmentId, $userId, $userId]);

        if ($completeStmt->rowCount() === 0) {
            throw new RuntimeException(
                'This assignment could not be completed. It may have been modified by another process. Please refresh the page.'
            );
        }

        // 3. Update document status to 8 = 'Referred'
        $this->pdo->prepare("
            UPDATE documents
            SET current_status_id = 8,
                updated_by        = ?
            WHERE id = ?
        ")->execute([$userId, $documentId]);

        // 4. Create the committee_cycles row
        //    cycle_number = MAX(cycle_number) + 1 for this document (or 1 if none)
        $cycleNumStmt = $this->pdo->prepare("
            SELECT COALESCE(MAX(cycle_number), 0) + 1 AS next_num
            FROM committee_cycles
            WHERE document_id = ?
        ");
        $cycleNumStmt->execute([$documentId]);
        $cycleNumber = (int) $cycleNumStmt->fetchColumn();

        $this->pdo->prepare("
            INSERT INTO committee_cycles (
                document_id, cycle_number,
                referred_by, accepted_by,
                decision, remarks,
                started_at, completed_at
            ) VALUES (?, ?, ?, ?, 'ACCEPTED', ?, NOW(), NOW())
        ")->execute([
            $documentId,
            $cycleNumber,
            $userId,   // referred_by — user who performed the endorsement
            $userId,   // accepted_by — same user (they accepted the assignment)
            $remarks,
        ]);

        // 5. COMMITTEE_ENDORSED_REFERRED workflow event
        $uStmt = $this->pdo->prepare("SELECT username FROM user_accounts WHERE id = ? LIMIT 1");
        $uStmt->execute([$userId]);
        $username = (string) ($uStmt->fetchColumn() ?: '');

        $this->pdo->prepare("
            INSERT INTO document_events (
                document_id, event_type, phase, performed_by,
                from_status_id, to_status_id, remarks, metadata
            ) VALUES (?, 'COMMITTEE_ENDORSED_REFERRED', 'COMMITTEE', ?, ?, 8, ?, ?)
        ")->execute([
            $documentId,
            $userId,
            $currentStatusId,
            $remarks,
            json_encode([
                'cycle_number'         => $cycleNumber,
                'endorsed_by'          => $userId,
                'endorsed_by_username' => $username,
                'assignment_id'        => $assignmentId,
                'ip_address'           => client_ip(),
                'user_agent'           => client_user_agent(),
                // FUTURE PHASE: opinion_office_ids will be populated here
                'for_opinion_offices'  => [],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        // 6. Route record — same phase transition (COMMITTEE → COMMITTEE)
        //    Represents the internal workflow step from inbox to referred.
        $committeeRoleId = (int) ($assignment['assigned_to_role_id'] ?? 0);
        $this->pdo->prepare("
            INSERT INTO document_routes (
                document_id, from_phase, to_phase,
                routed_by, routed_to_role_id, remarks
            ) VALUES (?, 'COMMITTEE', 'COMMITTEE', ?, ?, ?)
        ")->execute([
            $documentId,
            $userId,
            $committeeRoleId ?: null,
            'Endorsed as Referred by Committee: ' . $remarks,
        ]);

        // audit_log() deliberately omitted (open transaction) — process() writes after commit.

        return [
            'auditData' => [
                'action'         => 'committee_endorsed_referred',
                'assignment_id'  => $assignmentId,
                'document_id'    => $documentId,
                'cycle_number'   => $cycleNumber,
                'endorsed_by'    => $userId,
                'endorsed_by_username' => $username,
                'remarks'        => mb_substr($remarks, 0, 200),
                'status_changed_to' => 'Referred (id=8)',
            ],
            'auditDescription' => "Committee endorsed document ID {$documentId} as Referred (cycle {$cycleNumber}, user: {$username})",
        ];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** Require and return the Committee role ID; redirect if not found. */
    private function requireCommitteeRoleId(): int
    {
        $stmt = $this->pdo->query(
            "SELECT id FROM roles WHERE name = 'Committee' AND is_active = 1 AND is_deleted = 0 LIMIT 1"
        );
        $row = $stmt->fetch();
        if (!$row) {
            flash_set('error', 'Committee role not found. Please contact the system administrator.');
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
            'endorse'         => 'Proceeded to Endorsement (Referred)',
            default           => $action,
        };
    }
}

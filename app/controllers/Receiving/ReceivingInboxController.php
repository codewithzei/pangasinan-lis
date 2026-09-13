<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/DocumentService.php';

/**
 * ReceivingInboxController
 *
 * Handles the Receiving Inbox: listing documents returned by Admin,
 * viewing returned document details, editing documents, and routing
 * corrected documents back to Admin.
 */
class ReceivingInboxController
{
    protected PDO             $pdo;
    protected DocumentService $docService;

    public function __construct()
    {
        $database         = new Database();
        $this->pdo        = $database->connect();
        $this->docService = new DocumentService();
    }

    // =========================================================================
    // 1. Inbox list (Returned and Accepted tabs)
    // =========================================================================

    public function index(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        $receivingRoleId = $this->requireReceivingRoleId();

        // Determine which view: returned or accepted
        $view = trim($_GET['view'] ?? 'returned');
        if (!in_array($view, ['returned', 'accepted'], true)) {
            $view = 'returned';
        }

        // Filters
        $search = trim($_GET['search'] ?? '');

        // Pagination
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        // Build WHERE clause based on view
        $where  = ['da.assigned_to_role_id = ?', "da.phase = 'RECEIVING'"];
        $params = [$receivingRoleId];

        if ($view === 'returned') {
            // Returned: documents returned by Admin to Receiving Staff
            // These are PENDING Receiving assignments where Admin previously declined
            $where[] = "da.decision = 'PENDING'";
            $where[] = 'da.completed_at IS NULL';
            
            // Verify that Admin previously declined this document
            $where[] = "EXISTS (
                SELECT 1 FROM document_assignments da_admin
                WHERE da_admin.document_id = da.document_id
                  AND da_admin.phase = 'ADMIN'
                  AND da_admin.decision = 'DECLINED'
                  AND da_admin.declined_at IS NOT NULL
            )";
        } elseif ($view === 'accepted') {
            // Accepted: documents that Receiving Staff accepted/completed
            $where[] = "da.decision IN ('ACCEPTED', 'COMPLETED')";
            $where[] = 'da.completed_at IS NOT NULL';
            
            // Only show accepted returned documents (not initial submissions)
            $where[] = "EXISTS (
                SELECT 1 FROM document_assignments da_admin
                WHERE da_admin.document_id = da.document_id
                  AND da_admin.phase = 'ADMIN'
                  AND da_admin.decision = 'DECLINED'
            )";
        }

        if ($search !== '') {
            $where[]  = '(d.tracking_number LIKE ? OR d.subject_matter LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $whereClause = implode(' AND ', $where);

        // Count total records
        $countStmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT da.document_id) AS total
            FROM document_assignments da
            INNER JOIN documents d ON da.document_id = d.id
            WHERE {$whereClause}
        ");
        $countStmt->execute($params);
        $total      = (int) $countStmt->fetch()['total'];
        $totalPages = max(1, (int) ceil($total / $perPage));

        // Fetch assignments with related data
        if ($view === 'returned') {
            // For returned documents, include decline information
            // Use a deterministic subquery to get the latest declined Admin assignment per document
            $listStmt = $this->pdo->prepare("
                SELECT
                    da.id              AS assignment_id,
                    da.received_at,
                    da.decision,
                    d.id               AS document_id,
                    d.tracking_number,
                    d.subject_matter,
                    d.document_type_id,
                    dt.name            AS document_type_name,
                    dt.badge_color     AS document_type_badge_color,
                    d.current_phase,
                    d.date_received,
                    d.time_received,
                    ds.name            AS status,
                    ds.badge_color     AS status_badge_color,
                    st.name            AS source_type,
                    COALESCE(eo.name, h.name, m.name, d.source_name, '—') AS source_display,
                    da_admin.decline_reason,
                    da_admin.declined_at,
                    ua_declined.username AS declined_by_username,
                    CONCAT(ui_declined.first_name, ' ', ui_declined.last_name) AS declined_by_name
                FROM document_assignments da
                INNER JOIN documents         d  ON da.document_id      = d.id
                LEFT  JOIN document_statuses ds ON d.current_status_id = ds.id
                LEFT  JOIN document_types    dt ON d.document_type_id  = dt.id
                LEFT  JOIN source_types      st ON d.source_type_id    = st.id
                LEFT  JOIN external_offices  eo ON d.external_office_id = eo.id
                LEFT  JOIN hospitals          h ON d.hospital_id        = h.id
                LEFT  JOIN municities         m ON d.municipality_id    = m.id
                LEFT  JOIN (
                    -- Get the latest declined Admin assignment ID per document
                    SELECT da_latest.document_id, da_latest.id AS latest_id
                    FROM document_assignments da_latest
                    INNER JOIN (
                        SELECT document_id, MAX(id) AS max_id
                        FROM document_assignments
                        WHERE phase = 'ADMIN' AND decision = 'DECLINED' AND declined_at IS NOT NULL
                        GROUP BY document_id
                    ) da_max ON da_latest.document_id = da_max.document_id AND da_latest.id = da_max.max_id
                ) latest_decline ON latest_decline.document_id = da.document_id
                LEFT  JOIN document_assignments da_admin ON da_admin.id = latest_decline.latest_id
                LEFT  JOIN user_accounts ua_declined ON da_admin.assigned_by = ua_declined.id
                LEFT  JOIN user_info ui_declined ON ua_declined.id = ui_declined.user_account_id
                WHERE {$whereClause}
                ORDER BY da.received_at DESC, d.date_received DESC, d.id DESC
                LIMIT ? OFFSET ?
            ");
        } else {
            // For accepted documents
            $listStmt = $this->pdo->prepare("
                SELECT
                    da.id              AS assignment_id,
                    da.received_at,
                    da.completed_at,
                    da.decision,
                    d.id               AS document_id,
                    d.tracking_number,
                    d.subject_matter,
                    d.document_type_id,
                    dt.name            AS document_type_name,
                    dt.badge_color     AS document_type_badge_color,
                    d.current_phase,
                    d.date_received,
                    d.time_received,
                    ds.name            AS status,
                    ds.badge_color     AS status_badge_color,
                    st.name            AS source_type,
                    COALESCE(eo.name, h.name, m.name, d.source_name, '—') AS source_display,
                    ua_accepted.username AS accepted_by_username,
                    CONCAT(ui_accepted.first_name, ' ', ui_accepted.last_name) AS accepted_by_name
                FROM document_assignments da
                INNER JOIN documents         d  ON da.document_id      = d.id
                LEFT  JOIN document_statuses ds ON d.current_status_id = ds.id
                LEFT  JOIN document_types    dt ON d.document_type_id  = dt.id
                LEFT  JOIN source_types      st ON d.source_type_id    = st.id
                LEFT  JOIN external_offices  eo ON d.external_office_id = eo.id
                LEFT  JOIN hospitals          h ON d.hospital_id        = h.id
                LEFT  JOIN municities         m ON d.municipality_id    = m.id
                LEFT  JOIN user_accounts ua_accepted ON da.assigned_by = ua_accepted.id
                LEFT  JOIN user_info ui_accepted ON ua_accepted.id = ui_accepted.user_account_id
                WHERE {$whereClause}
                ORDER BY da.completed_at DESC, d.date_received DESC, d.id DESC
                LIMIT ? OFFSET ?
            ");
        }
        
        $listStmt->execute([...$params, $perPage, $offset]);
        $assignments = $listStmt->fetchAll();

        $success = flash_get('success');
        $error   = flash_get('error');
        $errors  = flash_get('errors') ?? [];

        // Statistics for the dashboard cards
        $statsStmt = $this->pdo->prepare("
            SELECT 
                COUNT(DISTINCT CASE 
                    WHEN da.decision = 'PENDING' 
                    AND da.completed_at IS NULL 
                    AND EXISTS (
                        SELECT 1 FROM document_assignments da2
                        WHERE da2.document_id = da.document_id
                          AND da2.phase = 'ADMIN'
                          AND da2.decision = 'DECLINED'
                    ) 
                    THEN da.document_id 
                END) AS returned_count,
                COUNT(DISTINCT CASE 
                    WHEN da.decision IN ('ACCEPTED', 'COMPLETED') 
                    AND da.completed_at IS NOT NULL
                    AND EXISTS (
                        SELECT 1 FROM document_assignments da2
                        WHERE da2.document_id = da.document_id
                          AND da2.phase = 'ADMIN'
                          AND da2.decision = 'DECLINED'
                    )
                    THEN da.document_id 
                END) AS accepted_count
            FROM document_assignments da
            WHERE da.assigned_to_role_id = ? AND da.phase = 'RECEIVING'
        ");
        $statsStmt->execute([$receivingRoleId]);
        $stats = $statsStmt->fetch();
        
        $returnedCount = (int) ($stats['returned_count'] ?? 0);
        $acceptedCount = (int) ($stats['accepted_count'] ?? 0);

        $pageTitle = 'Receiving Inbox';
        $currentView = $view;
        require __DIR__ . '/../../../resources/views/receiving/inbox/index.php';
    }

    // =========================================================================
    // 2. Document detail / edit page for returned documents
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
            redirect('receiving/inbox');
        }

        $receivingRoleId = $this->requireReceivingRoleId();

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
            redirect('receiving/inbox');
        }

        // Check if there's a pending Receiving assignment for this document
        $assignmentStmt = $this->pdo->prepare("
            SELECT id, decision, received_at
            FROM document_assignments
            WHERE document_id = ?
              AND assigned_to_role_id = ?
              AND phase = 'RECEIVING'
              AND decision = 'PENDING'
              AND completed_at IS NULL
            ORDER BY received_at DESC
            LIMIT 1
        ");
        $assignmentStmt->execute([$documentId, $receivingRoleId]);
        $assignment = $assignmentStmt->fetch();

        if (!$assignment) {
            flash_set('error', 'No pending Receiving assignment found for this document.');
            redirect('receiving/inbox');
        }

        // Fetch decline reason from Admin
        $declineStmt = $this->pdo->prepare("
            SELECT 
                da.decline_reason,
                da.declined_at,
                da.remarks,
                ua.username AS declined_by_username,
                CONCAT(ui.first_name, ' ', ui.last_name) AS declined_by_name
            FROM document_assignments da
            LEFT JOIN user_accounts ua ON da.assigned_by = ua.id
            LEFT JOIN user_info ui ON ua.id = ui.user_account_id
            WHERE da.document_id = ?
              AND da.phase = 'ADMIN'
              AND da.decision = 'DECLINED'
            ORDER BY da.declined_at DESC
            LIMIT 1
        ");
        $declineStmt->execute([$documentId]);
        $declineInfo = $declineStmt->fetch();

        // Fetch attachments
        $attachStmt = $this->pdo->prepare("
            SELECT * FROM document_attachments
            WHERE document_id = ?
            ORDER BY created_at DESC
        ");
        $attachStmt->execute([$documentId]);
        $attachments = $attachStmt->fetchAll();

        // Fetch document types for dropdown
        $docTypesStmt = $this->pdo->query("
            SELECT id, name, badge_color
            FROM document_types
            WHERE is_active = 1 AND is_deleted = 0
            ORDER BY name ASC
        ");
        $documentTypes = $docTypesStmt->fetchAll();

        // Fetch source types for dropdown
        $sourceTypesStmt = $this->pdo->query("
            SELECT id, name
            FROM source_types
            WHERE is_active = 1 AND is_deleted = 0
            ORDER BY name ASC
        ");
        $sourceTypes = $sourceTypesStmt->fetchAll();

        // Fetch external offices
        $externalOfficesStmt = $this->pdo->query("
            SELECT id, name, abbreviation
            FROM external_offices
            WHERE is_active = 1 AND is_deleted = 0
            ORDER BY name ASC
        ");
        $externalOffices = $externalOfficesStmt->fetchAll();

        // Fetch hospitals
        $hospitalsStmt = $this->pdo->query("
            SELECT id, name
            FROM hospitals
            WHERE is_active = 1 AND is_deleted = 0
            ORDER BY name ASC
        ");
        $hospitals = $hospitalsStmt->fetchAll();

        // Fetch municipalities
        $municipalitiesStmt = $this->pdo->query("
            SELECT id, name, type
            FROM municities
            WHERE is_active = 1 AND is_deleted = 0
            ORDER BY name ASC
        ");
        $municipalities = $municipalitiesStmt->fetchAll();

        // Fetch SP members
        $spMembersStmt = $this->pdo->query("
            SELECT sp_member_id, first_name, middle_name, last_name, suffix
            FROM sp_members
            WHERE is_active = 1 AND is_deleted = 0
            ORDER BY last_name, first_name
        ");
        $spMembers = $spMembersStmt->fetchAll();

        $success = flash_get('success');
        $error   = flash_get('error');
        $errors  = flash_get('errors') ?? [];
        $old     = old_get();

        $pageTitle = 'Edit Returned Document';
        require __DIR__ . '/../../../resources/views/receiving/inbox/show.php';
    }

    // =========================================================================
    // 3. Update document and route back to Admin
    // =========================================================================

    public function update(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        $documentId    = (int) ($_POST['document_id'] ?? 0);
        $assignmentId  = (int) ($_POST['assignment_id'] ?? 0);

        if ($documentId <= 0 || $assignmentId <= 0) {
            flash_set('error', 'Invalid document or assignment ID.');
            redirect('receiving/inbox');
        }

        $receivingRoleId = $this->requireReceivingRoleId();

        // Fetch the current document first so we can fall back to its
        // date/time when the browser submits an empty value (which happens
        // when the DB stores HH:MM:SS and the <input type="time"> rejects it).
        $existingDocStmt = $this->pdo->prepare("SELECT * FROM documents WHERE id = ? LIMIT 1");
        $existingDocStmt->execute([$documentId]);
        $existingDoc = $existingDocStmt->fetch();

        if (!$existingDoc) {
            flash_set('error', 'Document not found.');
            redirect('receiving/inbox');
        }

        // Collect raw input
        $rawDate = trim($_POST['date_received'] ?? '');
        $rawTime = trim($_POST['time_received'] ?? '');

        // Normalize submitted time: HH:MM:SS → HH:MM (browser may echo it back)
        if ($rawTime !== '' && strlen($rawTime) === 8 && substr_count($rawTime, ':') === 2) {
            $rawTime = substr($rawTime, 0, 5);
        }

        // Preserve existing DB values when the user left the fields unchanged
        // (an empty submission means the browser could not display the value).
        if ($rawDate === '' && !empty($existingDoc['date_received'])) {
            $rawDate = $existingDoc['date_received'];
        }
        if ($rawTime === '' && !empty($existingDoc['time_received'])) {
            // Normalize the stored value to HH:MM for validation and saving
            $rawTime = substr($existingDoc['time_received'], 0, 5);
        }

        // Collect and validate input
        $data = [
            'date_received'        => $rawDate,
            'time_received'        => $rawTime,
            'subject_matter'       => trim($_POST['subject_matter'] ?? ''),
            'document_type_id'     => (int)($_POST['document_type_id'] ?? 0),
            'source_type_id'       => (int)($_POST['source_type_id'] ?? 0),
            'remarks'              => trim($_POST['remarks'] ?? ''),
            'external_office_id'   => !empty($_POST['external_office_id']) ? (int)$_POST['external_office_id'] : null,
            'hospital_id'          => !empty($_POST['hospital_id']) ? (int)$_POST['hospital_id'] : null,
            'municipality_id'      => !empty($_POST['municipality_id']) ? (int)$_POST['municipality_id'] : null,
            'sp_member_id'         => !empty($_POST['sp_member_id']) ? (int)$_POST['sp_member_id'] : null,
            'source_name'          => trim($_POST['source_name'] ?? ''),
            'source_contact_number'=> trim($_POST['source_contact_number'] ?? ''),
            'source_address'       => trim($_POST['source_address'] ?? ''),
            'source_liaison_name'  => trim($_POST['source_liaison_name'] ?? ''),
        ];

        // -----------------------------------------------------------------------
        // Source-type field normalization (server-side enforcement)
        //
        // Resolve the selected source type name so we can null-out every field
        // that does not belong to the chosen type, regardless of what the POST
        // body contains.  This prevents stale IDs from a previous selection
        // reaching the UPDATE query even when the frontend failed to clear them.
        //
        // Rules:
        //   External Office → keep external_office_id
        //                      clear hospital_id, municipality_id, sp_member_id, source_name
        //   Hospital        → keep hospital_id
        //                      clear external_office_id, municipality_id, sp_member_id, source_name
        //   SP Member       → keep sp_member_id (resolved to source_name later in step 3)
        //                      clear external_office_id, hospital_id, municipality_id, source_name
        //   Client          → keep municipality_id, source_name
        //                      clear external_office_id, hospital_id, sp_member_id
        //   Agency          → keep source_name
        //                      clear external_office_id, hospital_id, municipality_id, sp_member_id
        //   (anything else) → clear all source-specific fields
        // -----------------------------------------------------------------------
        if ($data['source_type_id'] > 0) {
            $stStmt = $this->pdo->prepare(
                "SELECT name FROM source_types WHERE id = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1"
            );
            $stStmt->execute([$data['source_type_id']]);
            $sourceTypeName = (string) ($stStmt->fetchColumn() ?: '');
        } else {
            $sourceTypeName = '';
        }

        switch ($sourceTypeName) {
            case 'External Office':
                $data['hospital_id']      = null;
                $data['municipality_id']  = null;
                $data['sp_member_id']     = null;
                $data['source_name']      = '';
                break;

            case 'Hospital':
                $data['external_office_id'] = null;
                $data['municipality_id']    = null;
                $data['sp_member_id']       = null;
                $data['source_name']        = '';
                break;

            case 'SP Member':
                $data['external_office_id'] = null;
                $data['hospital_id']        = null;
                $data['municipality_id']    = null;
                // source_name is resolved from the SP member record in step 3;
                // clear the raw POST value so it cannot override the lookup.
                $data['source_name']        = '';
                break;

            case 'Client':
                $data['external_office_id'] = null;
                $data['hospital_id']        = null;
                $data['sp_member_id']       = null;
                // municipality_id and source_name are kept as-is
                break;

            case 'Agency':
                $data['external_office_id'] = null;
                $data['hospital_id']        = null;
                $data['municipality_id']    = null;
                $data['sp_member_id']       = null;
                // source_name is kept as-is
                break;

            default:
                // Unknown or empty source type — clear everything
                $data['external_office_id'] = null;
                $data['hospital_id']        = null;
                $data['municipality_id']    = null;
                $data['sp_member_id']       = null;
                $data['source_name']        = '';
                break;
        }

        old_set($_POST);

        // Validate document fields
        $errors = $this->validateUpdate($data);

        if (!empty($errors)) {
            flash_set('errors', $errors);
            redirect('receiving/inbox/show?id=' . $documentId);
        }

        // -----------------------------------------------------------------------
        // File upload validation (optional — attachments are not required)
        // Done before the transaction so we fail fast without touching the DB.
        // -----------------------------------------------------------------------
        $uploadedFiles = $_FILES['attachments'] ?? [];
        $hasFiles      = !empty($uploadedFiles['name'][0]);

        if ($hasFiles) {
            $fileErrors = $this->docService->validateFileUploads($uploadedFiles, false);
            if (!empty($fileErrors)) {
                flash_set('errors', $fileErrors);
                redirect('receiving/inbox/show?id=' . $documentId);
            }
        }

        // -----------------------------------------------------------------------
        // Stage files to disk BEFORE opening any DB transaction.
        //
        // move_uploaded_file() is a filesystem operation that can take tens of
        // milliseconds. Running it inside a transaction would hold row and
        // gap locks on documents.id for the entire I/O duration, causing
        // SQLSTATE[HY000] 1205 (lock wait timeout) on concurrent requests for
        // the same document.
        //
        // Strategy:
        //   1. Move files to their permanent path now (no transaction open).
        //   2. Open transaction → do all DB writes → commit.
        //   3. If DB fails and we retry, the staged files stay on disk and are
        //      re-used by the next attempt (no re-upload needed).
        //   4. If all retries fail, delete the staged files so nothing is orphaned.
        // -----------------------------------------------------------------------

        /** @var array[] $stagedFiles  Output of stageFileUploads() — one element per file. */
        $stagedFiles = [];

        if ($hasFiles) {
            try {
                $stagedFiles = $this->docService->stageFileUploads($uploadedFiles, $documentId);
            } catch (Throwable $stageErr) {
                system_log('ERROR', 'Receiving re-route: file staging failed', [
                    'error'       => $stageErr->getMessage(),
                    'document_id' => $documentId,
                    'user_id'     => $userId,
                ]);
                flash_set('error', 'File upload failed: ' . $stageErr->getMessage());
                redirect('receiving/inbox/show?id=' . $documentId);
            }
        }

        // -----------------------------------------------------------------------
        // Retry loop — retries only transient DB lock errors (1205, 1213, 40001).
        // Validation errors, missing assignments, and business-rule violations
        // are thrown as non-retryable exceptions and exit the loop immediately.
        // -----------------------------------------------------------------------
        $maxAttempts   = 3;
        $backoffMs     = [100, 200, 300]; // milliseconds before attempt 2, 3, (none after 3)
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {

            // Roll back any stale open transaction from a prior failed attempt.
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            try {
                $this->pdo->beginTransaction();

                // ═══════════════════════════════════════════════════════════
                // LOCK ORDER (must match AdminInboxController::process()):
                //
                //   1. document_assignments  — read (no lock; optimistic guard)
                //   2. documents             — read (plain SELECT)
                //   3. document_revisions    — INSERT
                //   4. document_attachments  — INSERT  (if files present)
                //   5. document_assignments  — UPDATE  (complete Receiving)
                //   6. document_assignments  — INSERT  (new Admin assignment)
                //   7. documents             — UPDATE  (fields + phase)
                //   8. document_routes       — INSERT
                //   9. document_events       — INSERT ×2
                //
                // Admin's process() order:
                //   1. document_assignments  — UPDATE/complete
                //   2. document_revisions    — INSERT
                //   3. document_assignments  — INSERT for target role
                //   4. documents             — UPDATE phase
                //   5. document_routes       — INSERT
                //   6. document_events       — INSERT
                //
                // Both workflows write to document_assignments BEFORE they
                // write to documents.  This consistent ordering eliminates
                // the previous lock-order inversion where Receiving locked
                // documents first and Admin locked document_assignments first.
                // ═══════════════════════════════════════════════════════════

                // ── 1. Verify Receiving assignment is still pending ──────────
                // Plain SELECT (no FOR UPDATE) — the WHERE clause on decision
                // = 'PENDING' / completed_at IS NULL acts as the optimistic-
                // lock guard.  The matching UPDATE in step 5 will return
                // rowCount() = 0 if a concurrent request races us.
                $assignmentStmt = $this->pdo->prepare("
                    SELECT id, decision, completed_at
                    FROM document_assignments
                    WHERE id                = ?
                      AND document_id       = ?
                      AND assigned_to_role_id = ?
                      AND phase             = 'RECEIVING'
                      AND decision          = 'PENDING'
                      AND completed_at      IS NULL
                    LIMIT 1
                ");
                $assignmentStmt->execute([$assignmentId, $documentId, $receivingRoleId]);
                $assignment = $assignmentStmt->fetch();

                if (!$assignment) {
                    // Non-retryable: another request already processed this.
                    throw new RuntimeException(
                        'This assignment has already been processed or is no longer pending.'
                    );
                }

                // ── 2. Reload Admin role (read-only, before any write) ───────
                $adminRole = $this->pdo->query("
                    SELECT id FROM roles
                    WHERE name = 'Admin' AND is_active = 1 AND is_deleted = 0
                    LIMIT 1
                ")->fetch();
                if (!$adminRole) {
                    throw new RuntimeException('Admin role not found. Please configure roles.');
                }
                $adminRoleId = (int) $adminRole['id'];

                // ── 3. Guard against duplicate Admin assignment (read-only) ──
                $existingAdmin = $this->pdo->prepare("
                    SELECT id FROM document_assignments
                    WHERE document_id         = ?
                      AND assigned_to_role_id = ?
                      AND phase               = 'ADMIN'
                      AND decision            = 'PENDING'
                      AND completed_at        IS NULL
                    LIMIT 1
                ");
                $existingAdmin->execute([$documentId, $adminRoleId]);
                if ($existingAdmin->fetch()) {
                    // Non-retryable: duplicate would violate business rules.
                    throw new RuntimeException(
                        'A pending Admin assignment already exists for this document. Cannot create duplicate.'
                    );
                }

                // ── 4. Reload document (plain read, after assignment checks) ─
                $docStmt = $this->pdo->prepare("SELECT * FROM documents WHERE id = ? LIMIT 1");
                $docStmt->execute([$documentId]);
                $document = $docStmt->fetch();

                if (!$document) {
                    throw new RuntimeException('Document not found.');
                }

                $currentStatusId = (int) $document['current_status_id'];

                // ── 5. Resolve SP Member source name ─────────────────────────
                $sourceName = $data['source_name'];
                if ($data['sp_member_id'] !== null) {
                    $spMemberStmt = $this->pdo->prepare("
                        SELECT first_name, middle_name, last_name, suffix
                        FROM sp_members
                        WHERE sp_member_id = ? AND is_active = 1 AND is_deleted = 0
                        LIMIT 1
                    ");
                    $spMemberStmt->execute([$data['sp_member_id']]);
                    $spMember = $spMemberStmt->fetch();
                    if ($spMember) {
                        $sourceName = trim(implode(' ', array_filter([
                            $spMember['first_name'],
                            $spMember['middle_name'],
                            $spMember['last_name'],
                            $spMember['suffix'],
                        ])));
                    }
                }

                // ── 6. Snapshot current state as a revision ──────────────────
                $revNum = $this->docService->nextRevisionNumber($documentId);
                $sourceSnapshot = [
                    'external_office_id'    => $document['external_office_id'],
                    'hospital_id'           => $document['hospital_id'],
                    'municipality_id'       => $document['municipality_id'],
                    'source_name'           => $document['source_name'],
                    'source_contact_number' => $document['source_contact_number'],
                    'source_address'        => $document['source_address'],
                    'source_liaison_name'   => $document['source_liaison_name'],
                ];

                $this->pdo->prepare("
                    INSERT INTO document_revisions (
                        document_id, revision_number, changed_by, phase,
                        subject_matter, document_type_id, date_received, time_received,
                        source_type_id, source_snapshot, remarks, change_reason
                    ) VALUES (?, ?, ?, 'RECEIVING', ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $documentId,
                    $revNum,
                    $userId,
                    $document['subject_matter'],
                    $document['document_type_id'],
                    $document['date_received'],
                    $document['time_received'],
                    $document['source_type_id'],
                    json_encode($sourceSnapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    $document['remarks'],
                    'Document edited by Receiving Staff after Admin return',
                ]);

                // ── 7. Insert attachment rows (files already on disk) ─────────
                // insertStagedAttachments() is pure SQL — no filesystem I/O,
                // no second-connection logging.  Pending log entries are
                // collected in memory and written after commit.
                $pendingFileLogs = [];
                if (!empty($stagedFiles)) {
                    $pendingFileLogs = $this->docService->insertStagedAttachments(
                        $stagedFiles,
                        $documentId,
                        $userId,
                        'RECEIVING',
                        'RECEIVING_FILE'
                    );
                }

                // ── 8. Complete Receiving assignment (optimistic lock guard) ──
                // Must come BEFORE the document UPDATE so both this workflow
                // and Admin's process() acquire the document_assignments write-
                // lock before the documents write-lock, eliminating the lock-
                // order inversion that caused the 1205 timeout.
                $completeStmt = $this->pdo->prepare("
                    UPDATE document_assignments
                    SET decision     = 'COMPLETED',
                        completed_at = NOW()
                    WHERE id           = ?
                      AND decision     = 'PENDING'
                      AND completed_at IS NULL
                ");
                $completeStmt->execute([$assignmentId]);

                if ($completeStmt->rowCount() === 0) {
                    // Non-retryable: race lost between the SELECT and the UPDATE.
                    throw new RuntimeException(
                        'This assignment has already been processed by another request. Please refresh the page.'
                    );
                }

                // ── 9. Create Admin assignment ────────────────────────────────
                // accepted_by and assigned_to_user_id are NULL: the re-routed
                // assignment is unclaimed so all Admin users see it and must
                // claim it fresh — the previous owner's ID must NOT be copied.
                $this->pdo->prepare("
                    INSERT INTO document_assignments (
                        document_id, assigned_to_role_id, phase,
                        assigned_by, decision, received_at,
                        accepted_by, assigned_to_user_id
                    ) VALUES (?, ?, 'ADMIN', ?, 'PENDING', NOW(), NULL, NULL)
                ")->execute([$documentId, $adminRoleId, $userId]);

                // ── 10. Update document fields + advance phase to ADMIN ───────
                // This is the LAST write to the documents row, consistent with
                // Admin's process() which also updates documents last.
                $this->pdo->prepare("
                    UPDATE documents
                    SET date_received         = ?,
                        time_received         = ?,
                        subject_matter        = ?,
                        document_type_id      = ?,
                        source_type_id        = ?,
                        external_office_id    = ?,
                        hospital_id           = ?,
                        municipality_id       = ?,
                        source_name           = ?,
                        source_contact_number = ?,
                        source_address        = ?,
                        source_liaison_name   = ?,
                        remarks               = ?,
                        current_phase         = 'ADMIN',
                        current_owner_user_id = NULL,
                        updated_by            = ?,
                        updated_at            = NOW()
                    WHERE id = ?
                ")->execute([
                    $data['date_received'],
                    $data['time_received'],
                    $data['subject_matter'],
                    $data['document_type_id'],
                    $data['source_type_id'],
                    $data['external_office_id'],
                    $data['hospital_id'],
                    $data['municipality_id'],
                    $sourceName ?: null,
                    $data['source_contact_number'] ?: null,
                    $data['source_address'] ?: null,
                    $data['source_liaison_name'] ?: null,
                    $data['remarks'] ?: null,
                    $userId,
                    $documentId,
                ]);

                // ── 11. Route record ──────────────────────────────────────────
                $this->pdo->prepare("
                    INSERT INTO document_routes (
                        document_id, from_phase, to_phase, routing_option_id,
                        routed_by, routed_to_role_id, remarks
                    ) VALUES (?, 'RECEIVING', 'ADMIN', NULL, ?, ?, ?)
                ")->execute([
                    $documentId,
                    $userId,
                    $adminRoleId,
                    'Document corrected and re-routed to Admin by Receiving Staff',
                ]);

                // ── 12. Workflow event: DOCUMENT_EDITED ───────────────────────
                $editedMetadata = ['ip_address' => client_ip()];
                if (!empty($stagedFiles)) {
                    $editedMetadata['file_count'] = count($stagedFiles);
                }

                $this->pdo->prepare("
                    INSERT INTO document_events (
                        document_id, event_type, phase, performed_by,
                        to_status_id, remarks, metadata
                    ) VALUES (?, 'DOCUMENT_EDITED', 'RECEIVING', ?, ?, ?, ?)
                ")->execute([
                    $documentId,
                    $userId,
                    $currentStatusId,
                    'Document edited and corrected by Receiving Staff',
                    json_encode($editedMetadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);

                // ── 13. Workflow event: ROUTED_TO_ADMIN ───────────────────────
                $this->pdo->prepare("
                    INSERT INTO document_events (
                        document_id, event_type, phase, performed_by,
                        to_status_id, remarks, metadata
                    ) VALUES (?, 'ROUTED_TO_ADMIN', 'ADMIN', ?, ?, ?, ?)
                ")->execute([
                    $documentId,
                    $userId,
                    $currentStatusId,
                    'Document re-routed to Admin after correction',
                    json_encode([
                        'routed_to_role_id' => $adminRoleId,
                        'ip_address'        => client_ip(),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);

                // ── COMMIT ────────────────────────────────────────────────────
                // All writes (revision, attachments, assignments, document,
                // route, events) are persisted atomically here.
                $this->pdo->commit();

                // ── Post-commit: file-upload logs, notifications, audit ───────
                // Nothing below this line runs inside a transaction.
                // Writing audit_logs/system_logs via _log_pdo() (a separate
                // static PDO connection) is safe only after the main transaction
                // has closed; doing it inside would compete for the same row
                // locks and cause SQLSTATE HY000 1205 timeouts.

                // Flush deferred file-upload audit entries (one per file).
                $this->docService->flushFileUploadLogs($pendingFileLogs);

                $this->notifyAdminUsers($documentId, $userId, $document['tracking_number'], $adminRoleId);

                audit_log('UPDATE', 'Document', (string) $documentId, null, [
                    'action'         => 'receiving_reroute_to_admin',
                    'subject_matter' => mb_substr($data['subject_matter'], 0, 100),
                    'file_count'     => count($stagedFiles),
                    'attempt'        => $attempt,
                ], "Receiving Staff re-routed document {$document['tracking_number']} to Admin");

                system_log('INFO', "Document re-routed to Admin after correction: {$document['tracking_number']}", [
                    'document_id' => $documentId,
                    'file_count'  => count($stagedFiles),
                    'user_id'     => $userId,
                    'attempt'     => $attempt,
                ]);

                old_clear();
                flash_set('success', "Document {$document['tracking_number']} successfully updated and routed to Admin.");
                redirect('receiving/inbox?view=accepted');

                // redirect() above exits; this return is unreachable but
                // satisfies static analysis tools.
                return; // @codeCoverageIgnore

            } catch (Throwable $e) {
                // Always roll back before deciding what to do next.
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                $lastException = $e;

                // ----------------------------------------------------------
                // Classify the exception: transient DB lock error vs. hard stop.
                //
                // Retryable codes:
                //   1205  – InnoDB lock wait timeout
                //   1213  – InnoDB deadlock
                // Retryable SQLSTATE:
                //   40001 – serialization failure (covers deadlock on some drivers)
                // ----------------------------------------------------------
                $isRetryable = false;

                if ($e instanceof PDOException) {
                    $sqlState  = (string) ($e->errorInfo[0] ?? '');
                    $errorCode = (int)    ($e->errorInfo[1] ?? 0);

                    $isRetryable = in_array($errorCode, [1205, 1213], true)
                                || $sqlState === '40001';
                }

                if (!$isRetryable) {
                    // Hard failure (validation error, missing row, duplicate
                    // assignment, etc.) — do not retry; break immediately.
                    break;
                }

                // Log the retryable failure and sleep before the next attempt.
                system_log('WARNING', 'Receiving re-route: transient DB lock, will retry', [
                    'document_id' => $documentId,
                    'user_id'     => $userId,
                    'attempt'     => $attempt,
                    'max_attempts'=> $maxAttempts,
                    'sqlstate'    => $e->errorInfo[0] ?? 'n/a',
                    'error_code'  => $e->errorInfo[1] ?? 'n/a',
                    'error'       => $e->getMessage(),
                ]);

                if ($attempt < $maxAttempts) {
                    // Short exponential-style backoff (100 ms, 200 ms, …)
                    usleep(($backoffMs[$attempt - 1] ?? 300) * 1000);
                }
            }
        } // end retry loop

        // -----------------------------------------------------------------------
        // All attempts exhausted (or a hard failure broke out of the loop).
        // Clean up any staged files so no orphans remain on disk.
        // -----------------------------------------------------------------------
        foreach ($stagedFiles as $s) {
            if (isset($s['abs_path']) && file_exists($s['abs_path'])) {
                @unlink($s['abs_path']);
            }
        }

        // Rich log for exhausted retries.
        system_log('ERROR', 'Receiving re-route to Admin failed after all attempts', [
            'document_id'    => $documentId,
            'user_id'        => $userId,
            'attempts'       => $attempt - 1,
            'sqlstate'       => $lastException instanceof PDOException
                                    ? ($lastException->errorInfo[0] ?? 'n/a') : 'n/a',
            'error_code'     => $lastException instanceof PDOException
                                    ? ($lastException->errorInfo[1] ?? 'n/a') : 'n/a',
            'error'          => $lastException ? $lastException->getMessage() : 'unknown',
            'files_staged'   => count($stagedFiles),
            'files_deleted'  => count($stagedFiles),
            'request_url'    => $_SERVER['REQUEST_URI'] ?? 'n/a',
        ]);

        flash_set('error', $lastException ? $lastException->getMessage() : 'An unexpected error occurred.');
        redirect('receiving/inbox/show?id=' . $documentId);
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

    protected function requireReceivingRoleId(): int
    {
        $stmt = $this->pdo->query("
            SELECT id FROM roles
            WHERE name = 'Receiving Staff' AND is_active = 1 AND is_deleted = 0
            LIMIT 1
        ");
        $role = $stmt->fetch();

        if (!$role) {
            flash_set('error', 'Receiving Staff role not found. Please contact the administrator.');
            redirect('dashboard');
        }

        return (int) $role['id'];
    }

    protected function validateUpdate(array $data): array
    {
        $errors = [];

        // Date and time validation.
        // By the time validateUpdate() is called, update() has already fallen
        // back to the existing DB value when the submitted field was empty, so
        // an empty string here means there is genuinely no value at all.
        if ($data['date_received'] === '') {
            $errors[] = 'Date received is required.';
        } elseif (!$this->isValidDate($data['date_received'])) {
            $errors[] = 'Invalid date format for date received.';
        }

        if ($data['time_received'] === '') {
            $errors[] = 'Time received is required.';
        } elseif (!$this->isValidTime($data['time_received'])) {
            $errors[] = 'Invalid time format for time received.';
        }

        // Subject matter validation
        if ($data['subject_matter'] === '') {
            $errors[] = 'Subject matter is required.';
        } elseif (mb_strlen($data['subject_matter']) > 5000) {
            $errors[] = 'Subject matter must not exceed 5000 characters.';
        }

        // Document type validation
        if ($data['document_type_id'] <= 0) {
            $errors[] = 'Document type is required.';
        } else {
            $stmt = $this->pdo->prepare("SELECT id FROM document_types WHERE id = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1");
            $stmt->execute([$data['document_type_id']]);
            if (!$stmt->fetch()) {
                $errors[] = 'Invalid or inactive document type selected.';
            }
        }

        // Source type
        if ($data['source_type_id'] <= 0) {
            $errors[] = 'Source type is required.';
        } else {
            $stmt = $this->pdo->prepare("SELECT id FROM source_types WHERE id = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1");
            $stmt->execute([$data['source_type_id']]);
            if (!$stmt->fetch()) {
                $errors[] = 'Invalid or inactive source type selected.';
            }
        }

        return $errors;
    }

    protected function isValidDate(string $date): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    protected function isValidTime(string $time): bool
    {
        // Accept HH:MM:SS (from the database) in addition to HH:MM (from the browser).
        // Both formats are valid inputs; we normalise to HH:MM before saving.
        if (strlen($time) === 8 && substr_count($time, ':') === 2) {
            $t = DateTime::createFromFormat('H:i:s', $time);
            return $t && $t->format('H:i:s') === $time;
        }
        $t = DateTime::createFromFormat('H:i', $time);
        return $t && $t->format('H:i') === $time;
    }

    // =========================================================================
    // 4. Delete a single attachment from a returned document
    // =========================================================================

    public function deleteAttachment(): void
    {
        $userId       = auth_id();
        $attachmentId = (int) ($_POST['attachment_id'] ?? 0);
        $documentId   = (int) ($_POST['document_id']   ?? 0);

        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }
        if ($attachmentId <= 0 || $documentId <= 0) {
            flash_set('error', 'Invalid attachment or document ID.');
            redirect('receiving/inbox');
        }

        $receivingRoleId = $this->requireReceivingRoleId();

        // Verify the document belongs to a pending Receiving assignment
        $assignmentStmt = $this->pdo->prepare("
            SELECT id FROM document_assignments
            WHERE document_id = ?
              AND assigned_to_role_id = ?
              AND phase = 'RECEIVING'
              AND decision = 'PENDING'
              AND completed_at IS NULL
            LIMIT 1
        ");
        $assignmentStmt->execute([$documentId, $receivingRoleId]);
        if (!$assignmentStmt->fetch()) {
            flash_set('error', 'No pending Receiving assignment found for this document.');
            redirect('receiving/inbox/show?id=' . $documentId);
        }

        // Fetch the attachment row — must belong to this document
        $attStmt = $this->pdo->prepare("
            SELECT id, stored_path, file_name
            FROM document_attachments
            WHERE id = ? AND document_id = ?
            LIMIT 1
        ");
        $attStmt->execute([$attachmentId, $documentId]);
        $attachment = $attStmt->fetch();

        if (!$attachment) {
            flash_set('error', 'Attachment not found or does not belong to this document.');
            redirect('receiving/inbox/show?id=' . $documentId);
        }

        // Delete the physical file
        $absPath = __DIR__ . '/../../../public/' . $attachment['stored_path'];
        if (file_exists($absPath)) {
            @unlink($absPath);
        }

        // Delete the database row
        $this->pdo->prepare("DELETE FROM document_attachments WHERE id = ?")
                  ->execute([$attachmentId]);

        // Audit trail
        audit_log('DELETE', 'DocumentAttachment', (string) $attachmentId, null, [
            'document_id' => $documentId,
            'file_name'   => $attachment['file_name'],
            'stored_path' => $attachment['stored_path'],
        ], "Receiving Staff deleted attachment {$attachment['file_name']} from document #{$documentId}");

        system_log('INFO', "Attachment deleted by Receiving Staff", [
            'attachment_id' => $attachmentId,
            'document_id'   => $documentId,
            'user_id'       => $userId,
        ]);

        flash_set('success', 'Attachment "' . $attachment['file_name'] . '" removed successfully.');
        redirect('receiving/inbox/show?id=' . $documentId);
    }

    // =========================================================================
    // 5. Upload additional attachments to a returned document
    // =========================================================================

    public function uploadAttachment(): void
    {
        $userId     = auth_id();
        $documentId = (int) ($_POST['document_id'] ?? 0);

        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }
        if ($documentId <= 0) {
            flash_set('error', 'Invalid document ID.');
            redirect('receiving/inbox');
        }

        $receivingRoleId = $this->requireReceivingRoleId();

        // Verify a pending Receiving assignment exists for this document
        $assignmentStmt = $this->pdo->prepare("
            SELECT id FROM document_assignments
            WHERE document_id = ?
              AND assigned_to_role_id = ?
              AND phase = 'RECEIVING'
              AND decision = 'PENDING'
              AND completed_at IS NULL
            LIMIT 1
        ");
        $assignmentStmt->execute([$documentId, $receivingRoleId]);
        if (!$assignmentStmt->fetch()) {
            flash_set('error', 'No pending Receiving assignment found for this document.');
            redirect('receiving/inbox/show?id=' . $documentId);
        }

        // Validate uploaded files
        $files = $_FILES['attachments'] ?? [];
        if (empty($files['name'][0])) {
            flash_set('error', 'No files were selected for upload.');
            redirect('receiving/inbox/show?id=' . $documentId);
        }

        $fileErrors = $this->docService->validateFileUploads($files, true);
        if (!empty($fileErrors)) {
            flash_set('errors', $fileErrors);
            redirect('receiving/inbox/show?id=' . $documentId);
        }

        $storedPaths = [];
        $this->pdo->beginTransaction();

        try {
            $storedPaths = $this->docService->processFileUploads(
                $files,
                $documentId,
                $userId,
                'RECEIVING',
                'RECEIVING_FILE'
            );

            // Capture pending log entries before commit (no DB writes here)
            $pendingFileLogs = $this->docService->takePendingFileLogs();

            // Record a workflow event for the upload
            $this->pdo->prepare("
                INSERT INTO document_events (
                    document_id, event_type, phase, performed_by,
                    remarks, metadata
                ) VALUES (?, 'DOCUMENT_EDITED', 'RECEIVING', ?, ?, ?)
            ")->execute([
                $documentId,
                $userId,
                'Receiving Staff uploaded additional attachments.',
                json_encode([
                    'file_count' => count($storedPaths),
                    'ip_address' => client_ip(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            $this->pdo->commit();

            // Post-commit logging — safe to write to audit_logs/system_logs
            // now that the main transaction is closed.
            $this->docService->flushFileUploadLogs($pendingFileLogs);

            audit_log('UPDATE', 'Document', (string) $documentId, null, [
                'action'     => 'receiving_attachment_upload',
                'file_count' => count($storedPaths),
            ], 'Receiving Staff uploaded additional attachments to document #' . $documentId);

            system_log('INFO', 'Receiving attachment upload completed', [
                'document_id' => $documentId,
                'file_count'  => count($storedPaths),
                'user_id'     => $userId,
            ]);

            flash_set('success', count($storedPaths) . ' file(s) uploaded successfully.');
            redirect('receiving/inbox/show?id=' . $documentId);

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            // Clean up any files that were already moved to disk
            foreach ($storedPaths as $p) {
                if (file_exists($p)) {
                    @unlink($p);
                }
            }
            system_log('ERROR', 'Receiving attachment upload failed', [
                'error'       => $e->getMessage(),
                'document_id' => $documentId,
                'user_id'     => $userId,
            ]);
            flash_set('error', 'Upload failed: ' . $e->getMessage());
            redirect('receiving/inbox/show?id=' . $documentId);
        }
    }

    protected function notifyAdminUsers(int $documentId, int $assignedBy, string $trackingNumber, int $adminRoleId): void
    {
        try {
            // Use DocumentService to notify all active Admin users.
            // notifyRoleUsers() uses the correct notifications schema
            // (recipient_user_id, sender_user_id, type, title, message, action_url)
            // and filters on status = 'active' which matches user_accounts.status.
            $this->docService->notifyRoleUsers(
                $adminRoleId,
                $documentId,
                $assignedBy,
                'DOCUMENT_ROUTED',
                "Document Re-routed: {$trackingNumber}",
                "Document {$trackingNumber} has been corrected and re-routed to Admin for review.",
                BASE_URL . "/admin/inbox/show?id={$documentId}"
            );
        } catch (Throwable $e) {
            // Log notification failure but don't fail the main operation
            system_log('WARNING', 'Failed to notify Admin users after document re-route', [
                'error'       => $e->getMessage(),
                'document_id' => $documentId,
            ]);
        }
    }
}

<?php

require_once __DIR__ . '/../../config/database.php';

/**
 * AdminCommunicationsController
 *
 * Handles the Admin Communications section — documents that were received
 * by Admin and marked as Noted (Communication type).
 *
 *   GET admin/communications      → index()
 *   GET admin/communications/show → show()
 */
class AdminCommunicationsController
{
    private PDO $pdo;

    public function __construct()
    {
        $database  = new Database();
        $this->pdo = $database->connect();
    }

    // =========================================================================
    // 1. GET admin/communications
    // =========================================================================

    public function index(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            redirect('login');
        }

        $this->requireAdminAccess();

        // Pagination
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        // Filters
        $search       = trim($_GET['search']   ?? '');
        $filterStatus = trim($_GET['status']   ?? '');
        $filterCat    = trim($_GET['category'] ?? '');

        $filterConditions = [];
        $filterParams     = [];

        // Base constraint: Communication documents noted via Admin.
        //
        // We use a derived table (latest_nr) to find the MAX(id) of all Noted
        // route records per document. This avoids non-aggregated columns in a
        // grouped query and is fully compatible with ONLY_FULL_GROUP_BY.
        //
        // from_phase = 'ADMIN' AND to_phase = 'ADMIN' AND routing_options.name = 'Noted'
        // is the exact pattern written by DocumentService::receiveDocument() when
        // Admin selects "Noted" during document intake.
        $baseJoinCount = "
            INNER JOIN (
                SELECT dr.document_id, MAX(dr.id) AS latest_route_id
                FROM   document_routes  dr
                INNER JOIN routing_options ro2
                    ON  ro2.id         = dr.routing_option_id
                    AND ro2.name       = 'Noted'
                    AND ro2.is_deleted = 0
                WHERE  dr.from_phase = 'ADMIN'
                  AND  dr.to_phase   = 'ADMIN'
                GROUP BY dr.document_id
            ) latest_nr
                ON  latest_nr.document_id = d.id
            INNER JOIN document_types dt2
                ON  dt2.id         = d.document_type_id
                AND dt2.name       = 'Communication'
                AND dt2.is_deleted = 0
        ";

        $baseJoinDetail = "
            INNER JOIN (
                SELECT dr.document_id, MAX(dr.id) AS latest_route_id
                FROM   document_routes  dr
                INNER JOIN routing_options ro2
                    ON  ro2.id         = dr.routing_option_id
                    AND ro2.name       = 'Noted'
                    AND ro2.is_deleted = 0
                WHERE  dr.from_phase = 'ADMIN'
                  AND  dr.to_phase   = 'ADMIN'
                GROUP BY dr.document_id
            ) latest_nr
                ON  latest_nr.document_id = d.id
            INNER JOIN document_routes nr
                ON  nr.id = latest_nr.latest_route_id
            INNER JOIN document_types dt2
                ON  dt2.id         = d.document_type_id
                AND dt2.name       = 'Communication'
                AND dt2.is_deleted = 0
        ";

        if ($search !== '') {
            $filterConditions[] = '(d.tracking_number LIKE ? OR d.subject_matter LIKE ?)';
            $filterParams[]     = "%{$search}%";
            $filterParams[]     = "%{$search}%";
        }

        if ($filterStatus !== '') {
            $filterConditions[] = 'ds.id = ?';
            $filterParams[]     = $filterStatus;
        }

        if ($filterCat !== '') {
            $filterConditions[] = 'cc.id = ?';
            $filterParams[]     = $filterCat;
        }

        $extraWhere = count($filterConditions) > 0
            ? 'AND ' . implode(' AND ', $filterConditions)
            : '';

        // Count query
        $countSql = "
            SELECT COUNT(DISTINCT d.id) AS total
            FROM documents d
            {$baseJoinCount}
            LEFT JOIN document_statuses        ds ON ds.id = d.current_status_id
            LEFT JOIN communication_categories cc ON cc.id = d.communication_category_id
            WHERE 1=1 {$extraWhere}
        ";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($filterParams);
        $totalRows  = (int) ($countStmt->fetch()['total'] ?? 0);
        $totalPages = max(1, (int) ceil($totalRows / $perPage));

        // Main list query
        $sql = "
            SELECT
                d.id,
                d.tracking_number,
                d.subject_matter,
                d.document_type_id,
                dt.name              AS document_type_name,
                dt.badge_color       AS document_type_badge_color,
                d.current_phase,
                d.date_received,
                d.time_received,
                d.communication_category_id,
                cc.name              AS communication_category_name,
                ds.id                AS status_id,
                ds.name              AS status,
                ds.badge_color       AS status_badge_color,
                nr.id                AS route_id,
                nr.created_at        AS noted_at,
                nr.remarks           AS route_remarks,
                nr.routed_by,
                rb.username          AS routed_by_username,
                CONCAT(COALESCE(rbi.first_name, ''), ' ', COALESCE(rbi.last_name, '')) AS routed_by_fullname
            FROM documents d
            {$baseJoinDetail}
            LEFT JOIN document_types           dt  ON dt.id  = d.document_type_id
            LEFT JOIN document_statuses        ds  ON ds.id  = d.current_status_id
            LEFT JOIN communication_categories cc  ON cc.id  = d.communication_category_id
            LEFT JOIN user_accounts            rb  ON rb.id  = nr.routed_by
            LEFT JOIN user_info                rbi ON rbi.user_account_id = rb.id
            WHERE 1=1 {$extraWhere}
            ORDER BY nr.created_at DESC, nr.id DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([...$filterParams, $perPage, $offset]);
        $documents = $stmt->fetchAll();

        // Dropdown filter options
        $documentStatuses = $this->pdo->query("
            SELECT id, name
            FROM document_statuses
            WHERE is_active = 1 AND is_deleted = 0
            ORDER BY name ASC
        ")->fetchAll();

        $communicationCategories = $this->pdo->query("
            SELECT id, name
            FROM communication_categories
            WHERE is_active = 1 AND is_deleted = 0
            ORDER BY name ASC
        ")->fetchAll();

        // Statistics — total noted communications (unfiltered)
        $totalNotedStmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT d.id) AS cnt
            FROM documents d
            INNER JOIN (
                SELECT dr.document_id
                FROM   document_routes  dr
                INNER JOIN routing_options ro2
                    ON  ro2.id         = dr.routing_option_id
                    AND ro2.name       = 'Noted'
                    AND ro2.is_deleted = 0
                WHERE  dr.from_phase = 'ADMIN'
                  AND  dr.to_phase   = 'ADMIN'
                GROUP BY dr.document_id
            ) noted_docs
                ON  noted_docs.document_id = d.id
            INNER JOIN document_types dt2
                ON  dt2.id         = d.document_type_id
                AND dt2.name       = 'Communication'
                AND dt2.is_deleted = 0
        ");
        $totalNotedStmt->execute();
        $totalNoted = (int) ($totalNotedStmt->fetch()['cnt'] ?? 0);

        $success = flash_get('success');
        $error   = flash_get('error');

        $pageTitle    = 'Communications';
        $pageSubtitle = 'Communication documents marked as Noted by Admin';

        require __DIR__ . '/../../../resources/views/admin/communications/index.php';
    }

    // =========================================================================
    // 2. GET admin/communications/show
    // =========================================================================

    public function show(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            redirect('login');
        }

        $this->requireAdminAccess();

        $documentId = (int) ($_GET['id'] ?? 0);
        if ($documentId <= 0) {
            flash_set('error', 'Invalid document ID.');
            redirect('admin/communications');
        }

        // Fetch full document row
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
                updater.username   AS updated_by_username
            FROM documents d
            LEFT JOIN document_types           dt      ON d.document_type_id          = dt.id
            LEFT JOIN document_statuses        ds      ON d.current_status_id         = ds.id
            LEFT JOIN source_types             st      ON d.source_type_id            = st.id
            LEFT JOIN external_offices         eo      ON d.external_office_id        = eo.id
            LEFT JOIN hospitals                h       ON d.hospital_id               = h.id
            LEFT JOIN municities               m       ON d.municipality_id           = m.id
            LEFT JOIN communication_categories cc      ON d.communication_category_id = cc.id
            LEFT JOIN user_accounts            creator ON d.created_by                = creator.id
            LEFT JOIN user_accounts            updater ON d.updated_by                = updater.id
            WHERE d.id = ?
            LIMIT 1
        ");
        $docStmt->execute([$documentId]);
        $document = $docStmt->fetch();

        if (!$document) {
            flash_set('error', 'Document not found.');
            redirect('admin/communications');
        }

        // Verify this is a valid Admin-Noted Communication
        $isNotedComm = $this->pdo->prepare("
            SELECT COUNT(*) AS cnt
            FROM documents d
            INNER JOIN document_types  dt ON d.document_type_id       = dt.id
                                         AND dt.name       = 'Communication'
                                         AND dt.is_deleted = 0
            INNER JOIN document_routes dr ON dr.document_id           = d.id
            INNER JOIN routing_options ro ON ro.id                    = dr.routing_option_id
                                         AND ro.name       = 'Noted'
                                         AND ro.is_deleted = 0
            WHERE d.id           = ?
              AND dr.from_phase  = 'ADMIN'
              AND dr.to_phase    = 'ADMIN'
        ");
        $isNotedComm->execute([$documentId]);
        if (!(int) ($isNotedComm->fetch()['cnt'] ?? 0)) {
            flash_set('error', 'This document is not a valid Admin Noted Communication.');
            redirect('admin/communications');
        }

        // Route history
        $routeStmt = $this->pdo->prepare("
            SELECT
                dr.*,
                rb.username  AS routed_by_username,
                CONCAT(COALESCE(rbi.first_name, ''), ' ', COALESCE(rbi.last_name, '')) AS routed_by_fullname,
                rr.name      AS routed_to_role_name,
                ro.name      AS routing_option_name
            FROM document_routes dr
            LEFT JOIN user_accounts   rb  ON dr.routed_by         = rb.id
            LEFT JOIN user_info       rbi ON rb.id                = rbi.user_account_id
            LEFT JOIN roles           rr  ON dr.routed_to_role_id = rr.id
            LEFT JOIN routing_options ro  ON dr.routing_option_id = ro.id
            WHERE dr.document_id = ?
            ORDER BY dr.created_at ASC
        ");
        $routeStmt->execute([$documentId]);
        $routes = $routeStmt->fetchAll();

        // Assignments
        $assignStmt = $this->pdo->prepare("
            SELECT
                da.*,
                r.name       AS role_name,
                ua.username  AS assigned_to_username,
                ab.username  AS assigned_by_username,
                acc.username AS accepted_by_username,
                CONCAT(COALESCE(acci.first_name, ''), ' ', COALESCE(acci.last_name, '')) AS accepted_by_fullname
            FROM document_assignments da
            LEFT JOIN roles         r    ON da.assigned_to_role_id = r.id
            LEFT JOIN user_accounts ua   ON da.assigned_to_user_id = ua.id
            LEFT JOIN user_accounts ab   ON da.assigned_by         = ab.id
            LEFT JOIN user_accounts acc  ON da.accepted_by         = acc.id
            LEFT JOIN user_info      acci ON acc.id                = acci.user_account_id
            WHERE da.document_id = ?
            ORDER BY da.created_at ASC
        ");
        $assignStmt->execute([$documentId]);
        $assignments = $assignStmt->fetchAll();

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

        $pageTitle    = 'Communication Details — ' . htmlspecialchars($document['tracking_number']);
        $pageSubtitle = 'Noted communication document details and history';

        require __DIR__ . '/../../../resources/views/admin/communications/show.php';
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function requireAdminAccess(): void
    {
        if (!is_role('Admin') && !is_role('Super Admin')) {
            flash_set('error', 'You do not have permission to access that page.');
            redirect('dashboard');
        }
    }

    private function formatPhaseName(string $phase): string
    {
        return [
            'RECEIVING'    => 'Receiving',
            'ADMIN'        => 'Admin',
            'SP_SECRETARY' => 'SP Secretary',
            'PLENARY'      => 'Plenary',
            'COMMITTEE'    => 'Committee',
            'FINALIZED'    => 'Finalized',
            'FILED'        => 'Filed',
        ][$phase] ?? $phase;
    }
}

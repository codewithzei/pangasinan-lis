<?php

require_once __DIR__ . '/../../config/database.php';

/**
 * SpsecRoutedDocumentController
 *
 * Dedicated controller for SP Secretary routed documents:
 *   GET spsec/routed      → index() (lists documents routed via SP Secretary workflow)
 *   GET spsec/routed/show → show()  (details of a routed document)
 */
class SpsecRoutedDocumentController
{
    protected PDO $pdo;

    public function __construct()
    {
        $database  = new Database();
        $this->pdo = $database->connect();
    }

    // =========================================================================
    // 1. GET spsec/routed
    // =========================================================================

    public function index(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            redirect('login');
        }

        $this->requireSpsecAccess();

        // Pagination
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        // Filters
        $search         = trim($_GET['search']         ?? '');
        $filterStatus   = trim($_GET['status']         ?? '');
        $filterPhase    = strtoupper(trim($_GET['current_phase'] ?? ''));
        $filterType     = trim($_GET['document_type']   ?? '');

        $documentPhases = [
            'RECEIVING' => 'Receiving',
            'ADMIN' => 'Admin',
            'SP_SECRETARY' => 'SP Secretary',
            'PLENARY' => 'Plenary',
            'COMMITTEE' => 'Committee',
            'FINALIZED' => 'Finalized',
            'FILED' => 'Filed',
        ];
        if (!array_key_exists($filterPhase, $documentPhases)) {
            $filterPhase = '';
        }

        $filterConditions = [];
        $filterParams     = [];

        if ($search !== '') {
            $filterConditions[] = '(d.tracking_number LIKE ? OR d.subject_matter LIKE ?)';
            $filterParams[]     = "%{$search}%";
            $filterParams[]     = "%{$search}%";
        }

        if ($filterStatus !== '') {
            $filterConditions[] = 'ds.id = ?';
            $filterParams[]     = $filterStatus;
        }

        if ($filterPhase !== '') {
            $filterConditions[] = 'd.current_phase = ?';
            $filterParams[]     = $filterPhase;
        }

        if ($filterType !== '' && ctype_digit($filterType)) {
            $filterConditions[] = 'd.document_type_id = ?';
            $filterParams[]     = (int) $filterType;
        } else {
            $filterType = '';
        }

        $extraWhere = count($filterConditions) > 0
            ? 'AND ' . implode(' AND ', $filterConditions)
            : '';

        // Count query
        $countSql = "
            SELECT COUNT(*) AS total
            FROM (
                SELECT dr.document_id, MAX(dr.id) AS latest_route_id
                FROM document_routes dr
                WHERE dr.from_phase = 'SP_SECRETARY'
                  AND dr.to_phase IN ('PLENARY', 'COMMITTEE')
                GROUP BY dr.document_id
            ) sub
            INNER JOIN documents         d  ON d.id  = sub.document_id
            INNER JOIN document_routes   lr ON lr.id = sub.latest_route_id
            LEFT  JOIN routing_options   ro ON ro.id = lr.routing_option_id
            LEFT  JOIN document_statuses ds ON ds.id = d.current_status_id
            WHERE 1=1 {$extraWhere}
        ";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($filterParams);
        $totalRows  = (int) ($countStmt->fetch()['total'] ?? 0);
        $totalPages = max(1, (int) ceil($totalRows / $perPage));

        // List query
        $sql = "
            SELECT
                d.id,
                EXISTS (
                    SELECT 1
                    FROM document_resubmission_links drl
                    WHERE drl.resubmitted_document_id = d.id
                ) AS is_resubmission,
                d.tracking_number,
                d.subject_matter,
                d.document_type_id,
                dt.name              AS document_type_name,
                dt.badge_color       AS document_type_badge_color,
                d.current_phase,
                d.date_received,
                d.time_received,
                ds.id                AS status_id,
                ds.name              AS status,
                ds.badge_color       AS status_badge_color,
                lr.id                AS route_id,
                lr.from_phase,
                lr.to_phase,
                lr.routed_by,
                lr.routed_to_role_id,
                lr.remarks           AS route_remarks,
                lr.created_at        AS routed_at,
                ro.id                AS routing_option_id,
                ro.name              AS routing_option_name,
                cc.name              AS communication_category_name,
                committee_summary.committee_name AS committee_name,
                rb.username          AS routed_by_username,
                CONCAT(COALESCE(rbi.first_name, ''), ' ', COALESCE(rbi.last_name, '')) AS routed_by_fullname,
                routed_to_role.name  AS routed_to_role_name
            FROM (
                SELECT dr.document_id, MAX(dr.id) AS latest_route_id
                FROM document_routes dr
                WHERE dr.from_phase = 'SP_SECRETARY'
                  AND dr.to_phase IN ('PLENARY', 'COMMITTEE')
                GROUP BY dr.document_id
            ) sub
            INNER JOIN documents         d              ON d.id  = sub.document_id
            INNER JOIN document_routes   lr             ON lr.id = sub.latest_route_id
            LEFT  JOIN routing_options   ro             ON ro.id = lr.routing_option_id
            LEFT  JOIN document_types    dt             ON dt.id = d.document_type_id
            LEFT  JOIN document_statuses ds             ON ds.id = d.current_status_id
            LEFT  JOIN communication_categories cc     ON cc.id = d.communication_category_id
            LEFT  JOIN (
                SELECT
                    dc.document_id,
                    GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') AS committee_name
                FROM document_committees dc
                INNER JOIN committees c ON c.id = dc.committee_id
                GROUP BY dc.document_id
            ) committee_summary ON committee_summary.document_id = d.id
            LEFT  JOIN user_accounts     rb             ON rb.id = lr.routed_by
            LEFT  JOIN user_info         rbi            ON rbi.user_account_id = rb.id
            LEFT  JOIN roles             routed_to_role ON routed_to_role.id = lr.routed_to_role_id
            WHERE 1=1 {$extraWhere}
            ORDER BY lr.created_at DESC, lr.id DESC
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

        $documentTypes = $this->pdo->query("
            SELECT id, name
            FROM document_types
            WHERE is_active = 1 AND is_deleted = 0
            ORDER BY name ASC
        ")->fetchAll();

        // Statistics
        $totalRoutedStmt = $this->pdo->query("
            SELECT COUNT(DISTINCT document_id) AS cnt
            FROM document_routes
            WHERE from_phase = 'SP_SECRETARY'
              AND to_phase IN ('PLENARY', 'COMMITTEE')
        ");
        $totalRouted = (int) ($totalRoutedStmt->fetch()['cnt'] ?? 0);

        $plenaryCountStmt = $this->pdo->query("
            SELECT COUNT(DISTINCT document_id) AS cnt
            FROM document_routes
            WHERE from_phase = 'SP_SECRETARY' AND to_phase = 'PLENARY'
        ");
        $plenaryCount = (int) ($plenaryCountStmt->fetch()['cnt'] ?? 0);

        $committeeCountStmt = $this->pdo->query("
            SELECT COUNT(DISTINCT document_id) AS cnt
            FROM document_routes
            WHERE from_phase = 'SP_SECRETARY' AND to_phase = 'COMMITTEE'
        ");
        $committeeCount = (int) ($committeeCountStmt->fetch()['cnt'] ?? 0);

        $pageTitle    = 'Routed Documents';
        $pageSubtitle = 'Documents routed through the SP Secretary workflow';

        require __DIR__ . '/../../../resources/views/spsec/routed/index.php';
    }

    // =========================================================================
    // 2. GET spsec/routed/show
    // =========================================================================

    public function show(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            redirect('login');
        }

        $this->requireSpsecAccess();

        $documentId = (int) ($_GET['id'] ?? 0);
        if ($documentId <= 0) {
            flash_set('error', 'Invalid document ID.');
            redirect('spsec/routed');
        }

        // Fetch document details
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
                comm.name          AS committee_name
            FROM documents d
            LEFT JOIN document_types          dt      ON d.document_type_id          = dt.id
            LEFT JOIN document_statuses       ds      ON d.current_status_id         = ds.id
            LEFT JOIN source_types            st      ON d.source_type_id            = st.id
            LEFT JOIN provincial_government_offices        eo      ON d.external_office_id        = eo.id
            LEFT JOIN hospitals               h       ON d.hospital_id               = h.id
            LEFT JOIN municities              m       ON d.municipality_id           = m.id
            LEFT JOIN communication_categories cc     ON d.communication_category_id = cc.id
            LEFT JOIN user_accounts           creator ON d.created_by                = creator.id
            LEFT JOIN user_accounts           updater ON d.updated_by                = updater.id
            LEFT JOIN document_committees     dc      ON dc.document_id              = d.id
            LEFT JOIN committees              comm    ON comm.id                     = dc.committee_id
            WHERE d.id = ?
            LIMIT 1
        ");
        $docStmt->execute([$documentId]);
        $document = $docStmt->fetch();

        if (!$document) {
            flash_set('error', 'Document not found.');
            redirect('spsec/routed');
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
            LEFT JOIN user_accounts rb  ON dr.routed_by          = rb.id
            LEFT JOIN user_info     rbi ON rb.id                 = rbi.user_account_id
            LEFT JOIN roles         rr  ON dr.routed_to_role_id  = rr.id
            LEFT JOIN routing_options ro ON dr.routing_option_id = ro.id
            WHERE dr.document_id = ?
            ORDER BY dr.created_at ASC
        ");
        $routeStmt->execute([$documentId]);
        $routes = $routeStmt->fetchAll();

        // Assignments
        $assignStmt = $this->pdo->prepare("
            SELECT
                da.*,
                r.name      AS role_name,
                ua.username AS assigned_to_username,
                ab.username AS assigned_by_username,
                acc.username AS accepted_by_username,
                CONCAT(COALESCE(acci.first_name, ''), ' ', COALESCE(acci.last_name, '')) AS accepted_by_fullname
            FROM document_assignments da
            LEFT JOIN roles         r    ON da.assigned_to_role_id = r.id
            LEFT JOIN user_accounts ua   ON da.assigned_to_user_id = ua.id
            LEFT JOIN user_accounts ab   ON da.assigned_by         = ab.id
            LEFT JOIN user_accounts acc  ON da.accepted_by         = acc.id
            LEFT JOIN user_info     acci ON acc.id                 = acci.user_account_id
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

        $pageTitle    = 'Routed Document Details — ' . htmlspecialchars($document['tracking_number']);
        $pageSubtitle = 'Detailed document information';

        // ── Motion for Reconsideration data ───────────────────────────────────
        require_once __DIR__ . '/../../services/DocumentService.php';
        $docService     = new DocumentService($this->pdo);
        $documentEvents = $docService->getDocumentEvents($documentId);
        $mfrLink        = null;
        $relatedMotions = [];
        $docTypeName    = $document['document_type_name'] ?? '';
        if ($docTypeName === DocumentService::mfrTypeName()) {
            $mfrLink = $docService->getMotionLink($documentId);
        } elseif (in_array($docTypeName, DocumentService::mfrEligibleReferenceTypeNames(), true)) {
            $relatedMotions = $docService->getRelatedMotions($documentId);
        }
        $resubmissionReference = $docService->getResubmissionReference($documentId);
        $relatedResubmissions = $docService->getRelatedResubmissions($documentId);

        require __DIR__ . '/../../../resources/views/spsec/routed/show.php';
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function requireSpsecAccess(): void
    {
        if (!is_role('SP Secretary') && !is_role('Super Admin')) {
            flash_set('error', 'You do not have permission to access that page.');
            redirect('dashboard');
        }
    }
}

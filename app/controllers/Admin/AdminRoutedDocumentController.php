<?php

require_once __DIR__ . '/../../config/database.php';

/**
 * AdminRoutedDocumentController
 *
 * Read-only tracking page that shows documents the currently-logged-in Admin
 * personally routed (document_routes.routed_by = auth_id()).
 *
 * This controller does NOT modify any document assignments, ownership,
 * routing records, or workflow state.
 */
class AdminRoutedDocumentController
{
    protected PDO $pdo;

    public function __construct()
    {
        $database = new Database();
        $this->pdo = $database->connect();
    }

    // -------------------------------------------------------------------------
    // INDEX  –  list of documents routed outward by the current Admin user
    //
    // A valid Admin outbound route satisfies ALL THREE conditions:
    //   dr.routed_by    = auth_id()
    //   dr.from_phase   = 'ADMIN'
    //   dr.to_phase  IN ('SP_SECRETARY', 'PLENARY', 'COMMITTEE')
    //
    // This intentionally excludes:
    //   - Routes from RECEIVING to ADMIN (inbound, not routed by Admin)
    //   - Documents merely assigned to or accepted by Admin (no route event)
    //   - Documents still in the Admin inbox (not yet forwarded)
    //   - Documents returned to Receiving (to_phase = 'RECEIVING')
    //   - Any route where to_phase = 'ADMIN' (inbound to Admin)
    //   - Routes performed by a different Admin user
    // -------------------------------------------------------------------------

    public function index(): void
    {
        $authUserId = auth_id();

        if (!$authUserId) {
            redirect('login');
        }

        // Pagination
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        // Filters
        $search         = trim($_GET['search']   ?? '');
        $filterStatus   = trim($_GET['status']   ?? '');
        $filterDivision = trim($_GET['division'] ?? '');

        // -----------------------------------------------------------------------
        // Strategy: one row per LATEST valid outbound route event by this Admin.
        // "Valid outbound" = routed_by = auth_id()
        //                  + from_phase = 'ADMIN'
        //                  + to_phase IN ('SP_SECRETARY', 'PLENARY', 'COMMITTEE')
        //
        // We pick MAX(dr.id) per document for the most-recent qualifying event,
        // then join documents for full detail columns.
        // -----------------------------------------------------------------------

        // Dynamic WHERE conditions applied on top of the outer join
        $filterConditions = [];
        $filterParams     = [];

        if ($search !== '') {
            $filterConditions[] = '(d.tracking_number LIKE ? OR d.subject_matter LIKE ?)';
            $filterParams[] = "%{$search}%";
            $filterParams[] = "%{$search}%";
        }

        if ($filterStatus !== '') {
            $filterConditions[] = 'ds.id = ?';
            $filterParams[] = $filterStatus;
        }

        if ($filterDivision !== '') {
            $filterConditions[] = 'd.current_phase = ?';
            $filterParams[] = $filterDivision;
        }

        $extraWhere = count($filterConditions)
            ? 'AND ' . implode(' AND ', $filterConditions)
            : '';

        // ------------------------------------------------------------------
        // Count query – pagination total
        // ------------------------------------------------------------------
        $countSql = "
            SELECT COUNT(*) AS total
            FROM (
                SELECT dr.document_id
                FROM document_routes dr
                WHERE dr.routed_by  = ?
                  AND dr.from_phase = 'ADMIN'
                  AND dr.to_phase   IN ('SP_SECRETARY', 'PLENARY', 'COMMITTEE')
                GROUP BY dr.document_id
            ) sub
            INNER JOIN documents         d  ON d.id  = sub.document_id
            LEFT  JOIN document_statuses ds ON ds.id = d.current_status_id
            WHERE 1=1 {$extraWhere}
        ";
        $countParams = [$authUserId, ...$filterParams];
        $countStmt   = $this->pdo->prepare($countSql);
        $countStmt->execute($countParams);
        $totalRows = (int)$countStmt->fetch()['total'];

        // ------------------------------------------------------------------
        // Main list query – latest valid outbound route columns per document
        // ------------------------------------------------------------------
        $sql = "
            SELECT
                d.id,
                d.tracking_number,
                d.subject_matter,
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
                routed_to_role.name  AS routed_to_role_name
            FROM (
                SELECT
                    dr.document_id,
                    MAX(dr.id) AS latest_route_id
                FROM document_routes dr
                WHERE dr.routed_by  = ?
                  AND dr.from_phase = 'ADMIN'
                  AND dr.to_phase   IN ('SP_SECRETARY', 'PLENARY', 'COMMITTEE')
                GROUP BY dr.document_id
            ) sub
            INNER JOIN documents         d              ON d.id  = sub.document_id
            INNER JOIN document_routes   lr             ON lr.id = sub.latest_route_id
            LEFT  JOIN document_types    dt             ON dt.id = d.document_type_id
            LEFT  JOIN document_statuses ds             ON ds.id = d.current_status_id
            LEFT  JOIN roles             routed_to_role ON routed_to_role.id = lr.routed_to_role_id
            WHERE 1=1 {$extraWhere}
            ORDER BY lr.created_at DESC, lr.id DESC
            LIMIT ? OFFSET ?
        ";

        $stmtParams = [$authUserId, ...$filterParams, $perPage, $offset];
        $stmt       = $this->pdo->prepare($sql);
        $stmt->execute($stmtParams);
        $documents  = $stmt->fetchAll();

        $totalPages = (int)ceil($totalRows / $perPage);

        // Filter option lists
        $documentStatuses = $this->pdo->query("
            SELECT id, name
            FROM document_statuses
            WHERE is_active = 1 AND is_deleted = 0
            ORDER BY name ASC
        ")->fetchAll();

        $divisions = [
            'RECEIVING'    => 'Receiving',
            'ADMIN'        => 'Admin',
            'SP_SECRETARY' => 'SP Secretary',
            'PLENARY'      => 'Plenary',
            'COMMITTEE'    => 'Committee',
            'FINALIZED'    => 'Finalized',
            'FILED'        => 'Filed',
        ];

        // ------------------------------------------------------------------
        // Statistics – all scoped to valid Admin outbound routes only
        // ------------------------------------------------------------------

        // Documents Routed: distinct documents this Admin forwarded outward
        $totalRoutedByMeStmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT document_id) AS cnt
            FROM document_routes
            WHERE routed_by  = ?
              AND from_phase = 'ADMIN'
              AND to_phase   IN ('SP_SECRETARY', 'PLENARY', 'COMMITTEE')
        ");
        $totalRoutedByMeStmt->execute([$authUserId]);
        $totalRoutedByMe = (int)($totalRoutedByMeStmt->fetch()['cnt'] ?? 0);

        // Still Active: those forwarded documents whose current phase is not
        // finalized or filed (checking phase, not status name, to avoid
        // dependence on status label wording)
        $activeStmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT dr.document_id) AS cnt
            FROM document_routes dr
            INNER JOIN documents d ON d.id = dr.document_id
            WHERE dr.routed_by  = ?
              AND dr.from_phase = 'ADMIN'
              AND dr.to_phase   IN ('SP_SECRETARY', 'PLENARY', 'COMMITTEE')
              AND d.current_phase NOT IN ('FINALIZED', 'FILED')
        ");
        $activeStmt->execute([$authUserId]);
        $activeDocuments = (int)($activeStmt->fetch()['cnt'] ?? 0);

        // Total Route Events: count of individual valid outbound route records
        $routeEventStmt = $this->pdo->prepare("
            SELECT COUNT(*) AS cnt
            FROM document_routes
            WHERE routed_by  = ?
              AND from_phase = 'ADMIN'
              AND to_phase   IN ('SP_SECRETARY', 'PLENARY', 'COMMITTEE')
        ");
        $routeEventStmt->execute([$authUserId]);
        $totalRouteEvents = (int)($routeEventStmt->fetch()['cnt'] ?? 0);

        $success = flash_get('success');
        $error   = flash_get('error');

        $pageTitle    = 'Routed Documents';
        $pageSubtitle = 'Documents you have personally routed through the system';

        require __DIR__ . '/../../../resources/views/admin/routed/index.php';
    }

    // -------------------------------------------------------------------------
    // SHOW  –  detail page for a single document
    // -------------------------------------------------------------------------

    public function show(): void
    {
        $authUserId = auth_id();

        if (!$authUserId) {
            redirect('login');
        }

        $documentId = (int)($_GET['id'] ?? 0);

        if ($documentId <= 0) {
            flash_set('error', 'Invalid document ID.');
            redirect('admin/routed');
        }

        // Authorization check: current Admin must have at least one valid
        // outbound route for this document (from_phase='ADMIN' and
        // to_phase IN ('SP_SECRETARY','PLENARY','COMMITTEE')).
        // Inbound routes (RECEIVING → ADMIN) must not grant access.
        $authCheckStmt = $this->pdo->prepare("
            SELECT COUNT(*) AS cnt
            FROM document_routes
            WHERE document_id = ?
              AND routed_by   = ?
              AND from_phase  = 'ADMIN'
              AND to_phase    IN ('SP_SECRETARY', 'PLENARY', 'COMMITTEE')
            LIMIT 1
        ");
        $authCheckStmt->execute([$documentId, $authUserId]);
        $isAuthorized = (int)($authCheckStmt->fetch()['cnt'] ?? 0) > 0;

        if (!$isAuthorized) {
            flash_set('error', 'You are not authorized to view this document.');
            redirect('admin/routed');
        }

        // Fetch full document details
        $sql = "
            SELECT
                d.*,
                dt.name              AS document_type_name,
                dt.badge_color       AS document_type_badge_color,
                ds.name              AS status,
                ds.badge_color       AS status_badge_color,
                st.name              AS source_type,
                eo.name              AS external_office_name,
                eo.abbreviation      AS external_office_abbr,
                h.name               AS hospital_name,
                m.name               AS municipality_name,
                m.type               AS municipality_type,
                creator.username     AS created_by_username,
                creator.email        AS created_by_email,
                updater.username     AS updated_by_username,
                updater.email        AS updated_by_email,
                owner.username       AS current_owner_username,
                owner.email          AS current_owner_email
            FROM documents d
            LEFT JOIN document_types     dt      ON dt.id  = d.document_type_id
            LEFT JOIN document_statuses  ds      ON ds.id  = d.current_status_id
            LEFT JOIN source_types       st      ON st.id  = d.source_type_id
            LEFT JOIN external_offices   eo      ON eo.id  = d.external_office_id
            LEFT JOIN hospitals          h       ON h.id   = d.hospital_id
            LEFT JOIN municities         m       ON m.id   = d.municipality_id
            LEFT JOIN user_accounts      creator ON creator.id = d.created_by
            LEFT JOIN user_accounts      updater ON updater.id = d.updated_by
            LEFT JOIN user_accounts      owner   ON owner.id   = d.current_owner_user_id
            WHERE d.id = ?
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$documentId]);
        $document = $stmt->fetch();

        if (!$document) {
            flash_set('error', 'Document not found.');
            redirect('admin/routed');
        }

        // Attachments
        $attachmentsStmt = $this->pdo->prepare("
            SELECT
                id,
                file_name,
                stored_path,
                mime_type,
                file_size,
                phase,
                attachment_type,
                created_at
            FROM document_attachments
            WHERE document_id = ?
            ORDER BY created_at ASC
        ");
        $attachmentsStmt->execute([$documentId]);
        $attachments = $attachmentsStmt->fetchAll();

        // Full routing history (all routes for this document, chronological)
        $routesStmt = $this->pdo->prepare("
            SELECT
                dr.id,
                dr.from_phase,
                dr.to_phase,
                dr.remarks,
                dr.created_at        AS routed_at,
                rb.username          AS routed_by_username,
                rr.name              AS routed_to_role_name,
                ro.name              AS routing_option_name
            FROM document_routes dr
            LEFT JOIN user_accounts rb ON rb.id = dr.routed_by
            LEFT JOIN roles         rr ON rr.id = dr.routed_to_role_id
            LEFT JOIN routing_options ro ON ro.id = dr.routing_option_id
            WHERE dr.document_id = ?
            ORDER BY dr.created_at ASC, dr.id ASC
        ");
        $routesStmt->execute([$documentId]);
        $routes = $routesStmt->fetchAll();

        // Checklist items
        $checklistStmt = $this->pdo->prepare("
            SELECT
                dci.id,
                dci.is_completed,
                dci.completed_at,
                c.name               AS checklist_name,
                c.description        AS checklist_description,
                cu.username          AS completed_by_username
            FROM document_checklist_items dci
            INNER JOIN checklists    c   ON c.id   = dci.checklist_id
            LEFT  JOIN user_accounts cu  ON cu.id  = dci.completed_by
            WHERE dci.document_id = ?
            ORDER BY c.name ASC
        ");
        $checklistStmt->execute([$documentId]);
        $checklistItems = $checklistStmt->fetchAll();

        $pageTitle    = 'Document Details';
        $pageSubtitle = $document['tracking_number'];

        require __DIR__ . '/../../../resources/views/admin/routed/show.php';
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    protected function formatPhaseName(string $phase): string
    {
        $phaseMap = [
            'RECEIVING'    => 'Receiving',
            'ADMIN'        => 'Admin',
            'SP_SECRETARY' => 'SP Secretary',
            'PLENARY'      => 'Plenary',
            'COMMITTEE'    => 'Committee',
            'FINALIZED'    => 'Finalized',
            'FILED'        => 'Filed',
        ];
        return $phaseMap[$phase] ?? $phase;
    }
}

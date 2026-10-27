<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/DocumentService.php';

/**
 * PlenaryCommController
 *
 * Handles the Plenary Communications page — communications that have been
 * processed through the SP Secretary First Reading and are now visible to Plenary.
 *
 * Includes:
 *   • Communications marked "Noted" (with their selected category).
 *   • Communications with adopted committee reports (decision = REFERRED, report adopted).
 *   • Communications approved for resolution (decision = APPROVED).
 *
 * ROUTE MAP
 * ─────────────────────────────────────────────────────────────────────────────
 *  GET  plenary/communications        index()  — tabbed list
 *  GET  plenary/communications/show   show()   — detail view
 *
 * AUTHORIZATION
 * ─────────────────────────────────────────────────────────────────────────────
 *  All routes: AuthMiddleware + RoleMiddleware (Plenary / Super Admin).
 */
class PlenaryCommController
{
    protected PDO $pdo;

    private const PER_PAGE = 20;

    public function __construct()
    {
        $db        = new Database();
        $this->pdo = $db->connect();
    }

    // =========================================================================
    // 1. Index — tabbed list
    // =========================================================================

    public function index(): void
    {
        $userId = auth_id();
        if ($userId === null) { flash_set('error', 'You must be logged in.'); redirect('login'); }

        $this->requirePlenaryAccess();

        $tab    = trim($_GET['tab']    ?? 'noted');
        $validTabs = ['noted'];
        if (!in_array($tab, $validTabs, true)) { $tab = 'noted'; }

        $search    = trim($_GET['search']   ?? '');
        $filterCat = trim($_GET['category'] ?? '');

        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $offset = ($page - 1) * self::PER_PAGE;

        // ── Base: Comm documents that have a Plenary Comm First Reading with
        //    NOTED decision (not finalized ones — those go to Finalized Docs).
        $notedStatusId = $this->getStatusId('Plenary Comm: Noted');

        $where  = ['d.current_status_id = ?'];
        $params = [$notedStatusId];

        if ($search !== '') {
            $where[]  = "(d.tracking_number LIKE ? OR d.subject_matter LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if ($filterCat !== '') {
            $where[]  = "pcfr.communication_category_id = ?";
            $params[] = $filterCat;
        }

        $whereClause = implode(' AND ', $where);

        $countSql = "
            SELECT COUNT(DISTINCT d.id)
            FROM documents d
            INNER JOIN document_types dt ON dt.id = d.document_type_id
                AND dt.name LIKE '%Communication%' AND dt.is_deleted = 0
            INNER JOIN plenary_comm_first_reading pcfr
                ON pcfr.document_id = d.id AND pcfr.decision = 'NOTED'
            LEFT JOIN document_statuses ds ON ds.id = d.current_status_id
            WHERE {$whereClause}
        ";
        $cntStmt = $this->pdo->prepare($countSql);
        $cntStmt->execute($params);
        $total      = (int) $cntStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page       = min($page, $totalPages);

        $listSql = "
            SELECT
                d.id,
                d.tracking_number,
                d.subject_matter,
                d.date_received,
                d.current_status_id,
                ds.name              AS status,
                ds.badge_color       AS status_badge_color,
                dt.name              AS document_type_name,
                dt.badge_color       AS document_type_badge_color,
                pcfr.id              AS first_reading_id,
                pcfr.decision,
                pcfr.reading_date,
                pcfr.communication_category_id,
                cc.name              AS communication_category_name
            FROM documents d
            INNER JOIN document_types dt ON dt.id = d.document_type_id
                AND dt.name LIKE '%Communication%' AND dt.is_deleted = 0
            INNER JOIN plenary_comm_first_reading pcfr
                ON pcfr.document_id = d.id AND pcfr.decision = 'NOTED'
            LEFT JOIN document_statuses  ds ON ds.id = d.current_status_id
            LEFT JOIN communication_categories cc ON cc.id = pcfr.communication_category_id
            WHERE {$whereClause}
            ORDER BY pcfr.reading_date DESC, d.id DESC
            LIMIT ? OFFSET ?
        ";
        $listStmt = $this->pdo->prepare($listSql);
        $listStmt->execute([...$params, self::PER_PAGE, $offset]);
        $communications = $listStmt->fetchAll();

        // Tab counts (noted only for now)
        $tabCounts = ['noted' => $total];

        // Filter options
        $categories = $this->pdo->query("
            SELECT id, name FROM communication_categories WHERE is_active = 1 AND is_deleted = 0 ORDER BY name ASC
        ")->fetchAll();

        $pageTitle = 'Plenary Communications';
        $success   = flash_get('success');
        $error     = flash_get('error');

        require __DIR__ . '/../../../resources/views/plenary/communications/index.php';
    }

    // =========================================================================
    // 2. Show
    // =========================================================================

    public function show(): void
    {
        $userId     = auth_id();
        $documentId = (int) ($_GET['id'] ?? 0);

        if ($userId === null) { flash_set('error', 'You must be logged in.'); redirect('login'); }
        if ($documentId <= 0) { flash_set('error', 'Invalid document ID.'); redirect('plenary/communications'); }

        $this->requirePlenaryAccess();

        // Fetch document
        $docStmt = $this->pdo->prepare("
            SELECT d.*,
                   dt.name        AS document_type_name,
                   dt.badge_color AS document_type_badge_color,
                   ds.name        AS status,
                   ds.badge_color AS status_badge_color
            FROM documents d
            INNER JOIN document_types dt ON dt.id = d.document_type_id
                AND dt.name LIKE '%Communication%' AND dt.is_deleted = 0
            LEFT  JOIN document_statuses ds ON ds.id = d.current_status_id
            WHERE d.id = ?
            LIMIT 1
        ");
        $docStmt->execute([$documentId]);
        $document = $docStmt->fetch();

        if (!$document) {
            flash_set('error', 'Communication document not found.');
            redirect('plenary/communications');
        }

        // Plenary Comm First Reading record
        $frStmt = $this->pdo->prepare("
            SELECT pcfr.*, cc.name AS communication_category_name, ua.username AS performed_by_username
            FROM plenary_comm_first_reading pcfr
            LEFT JOIN communication_categories cc ON cc.id = pcfr.communication_category_id
            LEFT JOIN user_accounts ua ON ua.id = pcfr.performed_by
            WHERE pcfr.document_id = ? AND pcfr.decision = 'NOTED'
            LIMIT 1
        ");
        $frStmt->execute([$documentId]);
        $firstReading = $frStmt->fetch();

        if (!$firstReading) {
            flash_set('error', 'No Plenary Noted record found for this communication.');
            redirect('plenary/communications');
        }

        // Route history
        $routeStmt = $this->pdo->prepare("
            SELECT dr.*, ro.name AS routing_option_name, ua.username AS routed_by_username
            FROM document_routes dr
            LEFT JOIN routing_options ro ON ro.id = dr.routing_option_id
            LEFT JOIN user_accounts   ua ON ua.id = dr.routed_by
            WHERE dr.document_id = ?
            ORDER BY dr.created_at ASC
        ");
        $routeStmt->execute([$documentId]);
        $routes = $routeStmt->fetchAll();

        $pageTitle = 'Communication — ' . htmlspecialchars($document['tracking_number'] ?? '');
        $success   = flash_get('success');
        $error     = flash_get('error');

        require __DIR__ . '/../../../resources/views/plenary/communications/show.php';
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function requirePlenaryAccess(): void
    {
        $role = auth_role();
        if (!in_array($role, ['Plenary', 'Super Admin'], true)) {
            flash_set('error', 'Access denied.');
            redirect(dashboard_route_for_role($role));
        }
    }

    private function getStatusId(string $name): int
    {
        $row = $this->pdo->prepare(
            "SELECT id FROM document_statuses WHERE name = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1"
        );
        $row->execute([$name]);
        $r = $row->fetch();
        return $r ? (int) $r['id'] : 0;
    }

    private function buildListQuery(string $tab, string $search, string $filterCat): array
    {
        $joins  = '';
        $where  = ['1=1'];
        $params = [];

        switch ($tab) {
            case 'noted':
                $where[] = "cfr.decision = 'NOTED'";
                break;
            case 'referred':
                // Referred AND committee report adopted
                $where[] = "cfr.decision = 'REFERRED'";
                $where[] = "EXISTS (
                    SELECT 1 FROM committee_report_documents crd2
                    INNER JOIN committee_reports cr2 ON cr2.id = crd2.committee_report_id
                        AND cr2.comm_adopted_at IS NOT NULL
                    WHERE crd2.document_id = d.id
                )";
                break;
            case 'approved':
                $where[] = "cfr.decision = 'APPROVED'";
                break;
            default: // 'all'
                // All decisions that have been processed
                break;
        }

        if ($search !== '') {
            $where[]  = "(d.tracking_number LIKE ? OR d.subject_matter LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if ($filterCat !== '') {
            $where[]  = "cfr.communication_category_id = ?";
            $params[] = $filterCat;
        }

        return [$joins, implode(' AND ', $where), $params];
    }

    private function getTabCounts(): array
    {
        $counts = ['noted' => 0, 'referred' => 0, 'approved' => 0, 'all' => 0];

        $rows = $this->pdo->query("
            SELECT
                cfr.decision,
                COUNT(DISTINCT d.id) AS cnt
            FROM documents d
            INNER JOIN document_types dt ON dt.id = d.document_type_id AND dt.name LIKE '%Communication%' AND dt.is_deleted = 0
            INNER JOIN comm_first_reading cfr ON cfr.document_id = d.id
            GROUP BY cfr.decision
        ")->fetchAll();

        $total = 0;
        foreach ($rows as $row) {
            $key = strtolower($row['decision']);
            if ($key === 'referred') {
                // Only count adopted ones
                $adoptedCnt = $this->pdo->query("
                    SELECT COUNT(DISTINCT d2.id)
                    FROM documents d2
                    INNER JOIN document_types dt2 ON dt2.id = d2.document_type_id AND dt2.name LIKE '%Communication%' AND dt2.is_deleted = 0
                    INNER JOIN comm_first_reading cfr2 ON cfr2.document_id = d2.id AND cfr2.decision = 'REFERRED'
                    WHERE EXISTS (
                        SELECT 1 FROM committee_report_documents crd2
                        INNER JOIN committee_reports cr2 ON cr2.id = crd2.committee_report_id AND cr2.comm_adopted_at IS NOT NULL
                        WHERE crd2.document_id = d2.id
                    )
                ")->fetchColumn();
                $counts['referred'] = (int) $adoptedCnt;
            } else {
                $counts[$key] = (int) $row['cnt'];
            }
            $total += (int) $row['cnt'];
        }
        $counts['all'] = $total;

        return $counts;
    }
}

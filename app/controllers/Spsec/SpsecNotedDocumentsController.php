<?php

require_once __DIR__ . '/../../config/database.php';

/**
 * Lists documents marked as Noted by the SP Secretary.
 */
class SpsecNotedDocumentsController
{
    private PDO $pdo;

    public function __construct()
    {
        $database  = new Database();
        $this->pdo = $database->connect();
    }

    public function index(): void
    {
        if (auth_id() === null) {
            redirect('login');
        }

        if (!is_role('SP Secretary')) {
            flash_set('error', 'You do not have permission to access that page.');
            redirect('dashboard');
        }

        $tabs = [
            'ordinances'            => 'Provincial Ordinances',
            'resolutions'           => 'Provincial Resolutions',
            'municipal_ordinances'  => 'Municipal/City Ordinances',
            'municipal_resolutions' => 'Municipal/City Resolutions',
            'cases'                 => 'Cases',
            'others'                => 'Others',
        ];
        $tab = trim((string) ($_GET['tab'] ?? 'ordinances'));
        if (!isset($tabs[$tab])) {
            $tab = 'ordinances';
        }

        $page         = max(1, (int) ($_GET['page'] ?? 1));
        $perPage      = 20;
        $offset       = ($page - 1) * $perPage;
        $search       = trim((string) ($_GET['search'] ?? ''));
        $filterStatus = trim((string) ($_GET['status'] ?? ''));

        $categoryExpression = "
            CASE
                WHEN LOWER(dt.name) LIKE '%communication%' THEN NULL
                WHEN LOWER(dt.name) LIKE 'provincial%ordinance%' THEN 'ordinances'
                WHEN LOWER(dt.name) LIKE 'provincial%resolution%' THEN 'resolutions'
                WHEN LOWER(dt.name) LIKE 'municipal%ordinance%'
                  OR LOWER(dt.name) LIKE 'city%ordinance%' THEN 'municipal_ordinances'
                WHEN LOWER(dt.name) LIKE 'municipal%resolution%'
                  OR LOWER(dt.name) LIKE 'city%resolution%' THEN 'municipal_resolutions'
                WHEN LOWER(dt.name) LIKE '%complaint%'
                  OR LOWER(dt.name) LIKE '%case%' THEN 'cases'
                ELSE 'others'
            END
        ";

        $notedRoutes = "
            INNER JOIN (
                SELECT dr.document_id, MAX(dr.id) AS latest_route_id
                FROM document_routes dr
                INNER JOIN routing_options ro
                    ON ro.id = dr.routing_option_id
                   AND ro.name = 'Noted'
                   AND ro.is_deleted = 0
                WHERE dr.from_phase = 'SP_SECRETARY'
                  AND dr.to_phase = 'SP_SECRETARY'
                GROUP BY dr.document_id
            ) latest_nr ON latest_nr.document_id = d.id
            INNER JOIN document_routes nr ON nr.id = latest_nr.latest_route_id
        ";

        $where  = ["({$categoryExpression}) = ?"];
        $params = [$tab];
        if ($search !== '') {
            $where[]  = '(d.tracking_number LIKE ? OR d.subject_matter LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        if ($filterStatus !== '' && ctype_digit($filterStatus)) {
            $where[]  = 'ds.id = ?';
            $params[] = (int) $filterStatus;
        } else {
            $filterStatus = '';
        }
        $whereSql = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT d.id)
            FROM documents d
            INNER JOIN document_types dt ON dt.id = d.document_type_id
                                        AND dt.is_deleted = 0
            {$notedRoutes}
            LEFT JOIN document_statuses ds ON ds.id = d.current_status_id
            WHERE {$whereSql}
        ");
        $countStmt->execute($params);
        $totalRows  = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($totalRows / $perPage));
        if ($page > $totalPages) {
            $page   = $totalPages;
            $offset = ($page - 1) * $perPage;
        }

        $listStmt = $this->pdo->prepare("
            SELECT
                d.id,
                d.tracking_number,
                d.subject_matter,
                d.date_received,
                dt.name AS document_type_name,
                dt.badge_color AS document_type_badge_color,
                ds.name AS status,
                ds.badge_color AS status_badge_color,
                nr.created_at AS noted_at,
                (
                    SELECT COUNT(*)
                    FROM document_attachments da
                    WHERE da.document_id = d.id
                ) AS attachment_count
            FROM documents d
            INNER JOIN document_types dt ON dt.id = d.document_type_id
                                        AND dt.is_deleted = 0
            {$notedRoutes}
            LEFT JOIN document_statuses ds ON ds.id = d.current_status_id
            WHERE {$whereSql}
            ORDER BY nr.created_at DESC, nr.id DESC
            LIMIT ? OFFSET ?
        ");
        $listStmt->execute([...$params, $perPage, $offset]);
        $documents = $listStmt->fetchAll();

        $countsStmt = $this->pdo->query("
            SELECT ({$categoryExpression}) AS category, COUNT(DISTINCT d.id) AS total
            FROM documents d
            INNER JOIN document_types dt ON dt.id = d.document_type_id
                                        AND dt.is_deleted = 0
            {$notedRoutes}
            GROUP BY category
        ");
        $tabCounts = array_fill_keys(array_keys($tabs), 0);
        foreach ($countsStmt->fetchAll() as $row) {
            if (isset($tabCounts[$row['category']])) {
                $tabCounts[$row['category']] = (int) $row['total'];
            }
        }

        $documentStatuses = $this->pdo->query("
            SELECT id, name
            FROM document_statuses
            WHERE is_active = 1 AND is_deleted = 0
            ORDER BY name ASC
        ")->fetchAll();

        $totalNoted     = array_sum($tabCounts);
        $pageTitle      = 'Noted Documents';
        $pageSubtitle   = 'Copies of documents marked as Noted by SP Secretary, organized by document type.';
        $notedBaseUrl   = BASE_URL . '/spsec/noted-documents';
        $documentViewUrl = BASE_URL . '/spsec/inbox/show?id=';

        ob_start();
        require __DIR__ . '/../../../resources/views/admin/noted-documents/index.php';
        $content = ob_get_clean();
        require __DIR__ . '/../../../resources/views/layouts/app.php';
    }
}

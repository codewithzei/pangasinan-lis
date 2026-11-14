<?php

require_once __DIR__ . '/../../config/database.php';

class PlenaryReportsController
{
    private PDO $pdo;

    private const PER_PAGE = 20;

    public function __construct()
    {
        $database = new Database();
        $this->pdo = $database->connect();
    }

    public function index(): void
    {
        $this->requirePlenaryAccess();

        $search = trim($_GET['search'] ?? '');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $offset = ($page - 1) * self::PER_PAGE;
        [$where, $params] = $this->buildSearchClause($search);

        $countStmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT pcra.committee_report_id)
            FROM plenary_committee_report_adoptions pcra
            INNER JOIN committee_reports cr ON cr.id = pcra.committee_report_id
            WHERE {$where}
        ");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * self::PER_PAGE;

        $listStmt = $this->pdo->prepare("
            SELECT
                cr.id AS report_id,
                cr.report_type,
                cr.report_number,
                cr.summary_of_findings,
                COUNT(pcra.id) AS adopted_document_count,
                SUM(pcra.decision = 'APPROVED') AS approved_count,
                SUM(pcra.decision = 'DISAPPROVED') AS disapproved_count,
                SUM(pcra.decision = 'ADOPTED') AS adopted_only_count,
                MAX(pcra.adopted_at) AS latest_adopted_at,
                (SELECT GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ')
                 FROM committee_report_committees crc
                 INNER JOIN committees c ON c.id = crc.committee_id
                 WHERE crc.committee_report_id = cr.id) AS committee_names
            FROM plenary_committee_report_adoptions pcra
            INNER JOIN committee_reports cr ON cr.id = pcra.committee_report_id
            WHERE {$where}
            GROUP BY cr.id, cr.report_type, cr.report_number, cr.summary_of_findings
            ORDER BY latest_adopted_at DESC, cr.id DESC
            LIMIT ? OFFSET ?
        ");
        $listStmt->execute([...$params, self::PER_PAGE, $offset]);
        $reports = $listStmt->fetchAll();

        $pageTitle = 'Plenary Reports';
        require __DIR__ . '/../../../resources/views/plenary/reports/index.php';
    }

    public function show(): void
    {
        $this->requirePlenaryAccess();

        $reportId = (int) ($_GET['id'] ?? 0);
        if ($reportId <= 0) {
            flash_set('error', 'Invalid Committee Report ID.');
            redirect('plenary/reports');
        }

        $reportStmt = $this->pdo->prepare("
            SELECT cr.*,
                   COALESCE(NULLIF(CONCAT_WS(' ', NULLIF(ui.first_name, ''), NULLIF(ui.last_name, '')), ''),
                            ua.username, '—') AS created_by_name,
                   COALESCE(NULLIF(CONCAT_WS(' ', NULLIF(returned_user.first_name, ''),
                                             NULLIF(returned_user.last_name, '')), ''),
                            returned_account.username, '—') AS returned_by_name
            FROM committee_reports cr
            LEFT JOIN user_accounts ua ON ua.id = cr.created_by
            LEFT JOIN user_info ui ON ui.user_account_id = ua.id
            LEFT JOIN user_accounts returned_account ON returned_account.id = cr.returned_to_plenary_by
            LEFT JOIN user_info returned_user ON returned_user.user_account_id = returned_account.id
            WHERE cr.id = ?
              AND EXISTS (
                  SELECT 1 FROM plenary_committee_report_adoptions pcra
                  WHERE pcra.committee_report_id = cr.id
              )
            LIMIT 1
        ");
        $reportStmt->execute([$reportId]);
        $report = $reportStmt->fetch();
        if (!$report) {
            flash_set('error', 'Adopted Committee Report not found.');
            redirect('plenary/reports');
        }

        $adoptionStmt = $this->pdo->prepare("
            SELECT pcra.decision, pcra.remarks, pcra.adopted_at,
                   d.id AS document_id, d.tracking_number, d.subject_matter,
                   d.date_received, d.current_phase,
                   dt.name AS document_type_name,
                   ds.name AS document_status,
                   pdd.proposed_number, pdd.session_type, pdd.target_first_reading_date,
                   (SELECT pr.reading_date
                    FROM plenary_readings pr
                    WHERE pr.document_id = d.id AND pr.reading_number = 1
                    ORDER BY pr.id DESC
                    LIMIT 1) AS first_reading_date,
                   (SELECT pr.remarks
                    FROM plenary_readings pr
                    WHERE pr.document_id = d.id AND pr.reading_number = 1
                    ORDER BY pr.id DESC
                    LIMIT 1) AS first_reading_remarks,
                   (SELECT ch.outcome
                    FROM committee_hearings ch
                    WHERE ch.document_id = d.id
                    ORDER BY ch.id DESC
                    LIMIT 1) AS committee_hearing_outcome,
                   (SELECT ch.performed_at
                    FROM committee_hearings ch
                    WHERE ch.document_id = d.id
                    ORDER BY ch.id DESC
                    LIMIT 1) AS committee_hearing_at,
                   (SELECT ch.remarks
                    FROM committee_hearings ch
                    WHERE ch.document_id = d.id
                    ORDER BY ch.id DESC
                    LIMIT 1) AS committee_hearing_remarks,
                   COALESCE(NULLIF(CONCAT_WS(' ', NULLIF(adopted_user.first_name, ''),
                                             NULLIF(adopted_user.last_name, '')), ''),
                            adopted_account.username, '—') AS adopted_by_name
            FROM plenary_committee_report_adoptions pcra
            INNER JOIN documents d ON d.id = pcra.document_id
            LEFT JOIN document_types dt ON dt.id = d.document_type_id
            LEFT JOIN document_statuses ds ON ds.id = d.current_status_id
            LEFT JOIN plenary_document_details pdd ON pdd.document_id = d.id
            LEFT JOIN user_accounts adopted_account ON adopted_account.id = pcra.adopted_by
            LEFT JOIN user_info adopted_user ON adopted_user.user_account_id = pcra.adopted_by
            WHERE pcra.committee_report_id = ?
            ORDER BY pcra.adopted_at DESC, d.tracking_number ASC
        ");
        $adoptionStmt->execute([$reportId]);
        $adoptions = $adoptionStmt->fetchAll();

        $attachmentStmt = $this->pdo->prepare("
            SELECT file_name, stored_path, mime_type, file_size, created_at
            FROM committee_report_attachments
            WHERE committee_report_id = ?
            ORDER BY created_at ASC, id ASC
        ");
        $attachmentStmt->execute([$reportId]);
        $reportAttachments = $attachmentStmt->fetchAll();

        $committeeStmt = $this->pdo->prepare("
            SELECT c.name
            FROM committee_report_committees crc
            INNER JOIN committees c ON c.id = crc.committee_id
            WHERE crc.committee_report_id = ?
            ORDER BY c.name ASC
        ");
        $committeeStmt->execute([$reportId]);
        $reportCommittees = $committeeStmt->fetchAll(PDO::FETCH_COLUMN);

        $pageTitle = 'Adopted Committee Report';
        require __DIR__ . '/../../../resources/views/plenary/reports/show.php';
    }

    private function buildSearchClause(string $search): array
    {
        if ($search === '') {
            return ['1 = 1', []];
        }

        $term = '%' . $search . '%';
        return [
            "(
                cr.report_number LIKE ?
                OR cr.summary_of_findings LIKE ?
                OR EXISTS (
                    SELECT 1
                    FROM plenary_committee_report_adoptions pcra_search
                    INNER JOIN documents d_search ON d_search.id = pcra_search.document_id
                    WHERE pcra_search.committee_report_id = cr.id
                      AND (d_search.tracking_number LIKE ? OR d_search.subject_matter LIKE ?)
                )
            )",
            [$term, $term, $term, $term],
        ];
    }

    private function requirePlenaryAccess(): void
    {
        if (auth_id() === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        if (!in_array(auth_role(), ['Plenary', 'Super Admin'], true)) {
            $role = auth_role();
            flash_set('error', 'Access denied.');
            redirect(dashboard_route_for_role($role));
        }
    }
}

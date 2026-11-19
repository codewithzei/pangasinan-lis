<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/DocumentService.php';

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

    public function documentShow(): void
    {
        $this->requirePlenaryAccess();

        $reportId = (int) ($_GET['report_id'] ?? 0);
        $documentId = (int) ($_GET['id'] ?? 0);
        if ($reportId <= 0 || $documentId <= 0) {
            flash_set('error', 'Invalid Committee Report document.');
            redirect('plenary/reports');
            return;
        }

        $membershipStmt = $this->pdo->prepare("
            SELECT 1
            FROM plenary_committee_report_adoptions
            WHERE committee_report_id = ? AND document_id = ?
            LIMIT 1
        ");
        $membershipStmt->execute([$reportId, $documentId]);
        if (!$membershipStmt->fetchColumn()) {
            flash_set('error', 'This document is not part of the selected adopted Committee Report.');
            redirect('plenary/reports/show?id=' . $reportId);
            return;
        }

        $documentStmt = $this->pdo->prepare("
            SELECT d.*,
                   dt.name AS document_type_name,
                   dt.badge_color AS document_type_badge_color,
                   ds.name AS status,
                   ds.badge_color AS status_badge_color,
                   st.name AS source_type,
                   eo.name AS external_office_name,
                   eo.abbreviation AS external_office_abbr,
                   h.name AS hospital_name,
                   m.name AS municipality_name,
                   m.type AS municipality_type,
                   cc.name AS communication_category_name,
                   creator.username AS created_by_username,
                   updater.username AS updated_by_username,
                   owner.username AS current_owner_username
            FROM documents d
            LEFT JOIN document_types dt ON dt.id = d.document_type_id
            LEFT JOIN document_statuses ds ON ds.id = d.current_status_id
            LEFT JOIN source_types st ON st.id = d.source_type_id
            LEFT JOIN provincial_government_offices eo ON eo.id = d.external_office_id
            LEFT JOIN hospitals h ON h.id = d.hospital_id
            LEFT JOIN municities m ON m.id = d.municipality_id
            LEFT JOIN communication_categories cc ON cc.id = d.communication_category_id
            LEFT JOIN user_accounts creator ON creator.id = d.created_by
            LEFT JOIN user_accounts updater ON updater.id = d.updated_by
            LEFT JOIN user_accounts owner ON owner.id = d.current_owner_user_id
            WHERE d.id = ?
            LIMIT 1
        ");
        $documentStmt->execute([$documentId]);
        $document = $documentStmt->fetch();
        if (!$document) {
            flash_set('error', 'Document not found.');
            redirect('plenary/reports/show?id=' . $reportId);
            return;
        }

        $plenaryDetailsStmt = $this->pdo->prepare("
            SELECT proposed_number, target_first_reading_date, session_type,
                   additional_details, authors_all, co_authors_all
            FROM plenary_document_details
            WHERE document_id = ?
            LIMIT 1
        ");
        $plenaryDetailsStmt->execute([$documentId]);
        $plenaryDetails = $plenaryDetailsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $plenaryAuthorsStmt = $this->pdo->prepare("
            SELECT da.author_role,
                   CONCAT_WS(' ', NULLIF(sm.first_name, ''), NULLIF(sm.middle_name, ''),
                                  NULLIF(sm.last_name, ''), NULLIF(sm.suffix, '')) AS full_name
            FROM document_authors da
            INNER JOIN sp_members sm ON sm.sp_member_id = da.sp_member_id
            WHERE da.document_id = ?
            ORDER BY da.author_role ASC, da.sort_order ASC, da.id ASC
        ");
        $plenaryAuthorsStmt->execute([$documentId]);
        $plenaryAuthors = $plenaryAuthorsStmt->fetchAll(PDO::FETCH_ASSOC);

        $plenaryFirstReadingStmt = $this->pdo->prepare("
            SELECT action AS decision, reading_date, remarks, performed_at, 'Legislative' AS reading_type
            FROM plenary_readings
            WHERE document_id = ? AND reading_number = 1
            UNION ALL
            SELECT decision, reading_date, remarks, performed_at, 'Communication' AS reading_type
            FROM plenary_comm_first_reading
            WHERE document_id = ?
            UNION ALL
            SELECT decision, reading_date, remarks, performed_at, 'Petitions / Cases' AS reading_type
            FROM plenary_cases_first_reading
            WHERE document_id = ?
            ORDER BY reading_date DESC, performed_at DESC
            LIMIT 1
        ");
        $plenaryFirstReadingStmt->execute([$documentId, $documentId, $documentId]);
        $plenaryFirstReading = $plenaryFirstReadingStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $attachmentStmt = $this->pdo->prepare("
            SELECT attachment_rows.*
            FROM (
                SELECT da.id, da.file_name, da.stored_path, da.mime_type, da.file_size,
                       da.phase, da.attachment_type, da.created_at,
                       ua.username AS uploaded_by_username,
                       NULL AS report_number
                FROM document_attachments da
                LEFT JOIN user_accounts ua ON ua.id = da.uploaded_by
                WHERE da.document_id = ?

                UNION ALL

                SELECT cra.id, cra.file_name, cra.stored_path, cra.mime_type, cra.file_size,
                       'COMMITTEE' AS phase, 'COMMITTEE_REPORT' AS attachment_type, cra.created_at,
                       ua.username AS uploaded_by_username,
                       cr.report_number
                FROM committee_report_attachments cra
                INNER JOIN committee_reports cr ON cr.id = cra.committee_report_id
                LEFT JOIN user_accounts ua ON ua.id = cra.uploaded_by
                WHERE cra.committee_report_id = ?
            ) attachment_rows
            ORDER BY attachment_rows.created_at ASC, attachment_rows.id ASC
        ");
        $attachmentStmt->execute([$documentId, $reportId]);
        $attachments = $attachmentStmt->fetchAll(PDO::FETCH_ASSOC);

        $routesStmt = $this->pdo->prepare("
            SELECT dr.id, dr.from_phase, dr.to_phase, dr.remarks,
                   dr.created_at AS routed_at,
                   routed_by_user.username AS routed_by_username,
                   routed_to_role.name AS routed_to_role_name,
                   ro.name AS routing_option_name
            FROM document_routes dr
            LEFT JOIN user_accounts routed_by_user ON dr.routed_by = routed_by_user.id
            LEFT JOIN roles routed_to_role ON dr.routed_to_role_id = routed_to_role.id
            LEFT JOIN routing_options ro ON dr.routing_option_id = ro.id
            WHERE dr.document_id = ?
            ORDER BY dr.created_at ASC, dr.id ASC
        ");
        $routesStmt->execute([$documentId]);
        $routes = $routesStmt->fetchAll(PDO::FETCH_ASSOC);

        $checklistStmt = $this->pdo->prepare("
            SELECT dci.id, dci.is_completed, dci.completed_at,
                   c.name AS checklist_name,
                   c.description AS checklist_description,
                   completed_by_user.username AS completed_by_username
            FROM document_checklist_items dci
            INNER JOIN checklists c ON dci.checklist_id = c.id
            LEFT JOIN user_accounts completed_by_user ON dci.completed_by = completed_by_user.id
            WHERE dci.document_id = ?
            ORDER BY c.name ASC
        ");
        $checklistStmt->execute([$documentId]);
        $checklistItems = $checklistStmt->fetchAll(PDO::FETCH_ASSOC);

        $documentEvents = (new DocumentService($this->pdo))->getDocumentEvents($documentId);
        $mfrLink = null;
        $relatedMotions = [];
        $showMfrRelationship = false;
        $detailBackUrl = BASE_URL . '/plenary/reports/show?id=' . $reportId;
        $detailBackLabel = 'Back to Plenary Report';
        $pageTitle = 'Document Details';
        $pageSubtitle = $document['tracking_number'];

        require __DIR__ . '/../../../resources/views/receiving/routed-documents/show.php';
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

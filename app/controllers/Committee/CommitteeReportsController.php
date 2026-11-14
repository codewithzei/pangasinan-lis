<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/DocumentService.php';

/**
 * CommitteeReportsController
 *
 * Manages the shared Committee Reports container and its individual Return to
 * Plenary actions for linked documents and files.
 *
 * ROUTE MAP
 * ─────────────────────────────────────────────────────────────────────────────
 *  GET  committee/reports                  index()          — paginated report list
 *  GET  committee/reports/show             show()           — report detail view
 *  POST committee/reports/return-to-plenary returnToPlenary() — return action
 *
 * FILTERS (query-string)
 *   ?search        Full-text search across report_number, tracking_number, subject_matter
 *   ?type          COMMITTEE_REPORT | JOINT_COMMITTEE_REPORT
 *   ?status        status name slug (URL-encoded)
 *   ?date_from     Y-m-d  created_at lower bound
 *   ?date_to       Y-m-d  created_at upper bound
 *   ?page          integer, default 1
 *
 * AUTHORIZATION
 *   All routes protected by AuthMiddleware + RoleMiddleware.
 *   returnToPlenary() validates report membership and prevents duplicate
 *   transmissions for each document or file.
 *
 * Each return transaction updates one report member. The shared parent stays
 * available, and its status reflects the progress of all its members.
 */
class CommitteeReportsController
{
    protected PDO             $pdo;
    protected DocumentService $docService;

    private const PER_PAGE = 20;

    public function __construct()
    {
        $database         = new Database();
        $this->pdo        = $database->connect();
        $this->docService = new DocumentService();
    }

    // =========================================================================
    // 1. Index — paginated, filterable report list
    // =========================================================================

    public function index(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        // ── Filter inputs ─────────────────────────────────────────────────────
        $search   = trim($_GET['search']    ?? '');
        $type     = trim($_GET['type']      ?? '');
        $status   = trim($_GET['status']    ?? '');
        $dateFrom = trim($_GET['date_from'] ?? '');
        $dateTo   = trim($_GET['date_to']   ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $offset   = ($page - 1) * self::PER_PAGE;

        // Sanitise type
        $validTypes = ['', 'COMMITTEE_REPORT', 'JOINT_COMMITTEE_REPORT'];
        if (!in_array($type, $validTypes, true)) {
            $type = '';
        }

        // ── Build WHERE ───────────────────────────────────────────────────────
        [$where, $params] = $this->buildFilterClause($search, $type, $status, $dateFrom, $dateTo);

        $reportProgressJoin = "
            LEFT JOIN (
                SELECT item_rows.committee_report_id,
                       COUNT(*) AS item_count,
                       SUM(item_rows.is_returned) AS returned_count,
                       SUM(item_rows.item_type = 'ATTACHMENT') + (
                           SELECT COUNT(*)
                           FROM committee_report_attachments cra
                           WHERE cra.committee_report_id = item_rows.committee_report_id
                       ) AS attachment_count,
                       CASE
                           WHEN SUM(item_rows.is_returned) = COUNT(*) THEN 'Returned to Plenary'
                           WHEN SUM(item_rows.is_returned) > 0 THEN 'Partially Returned'
                           ELSE 'Committee Report Created'
                       END AS overall_status,
                       CASE
                           WHEN SUM(item_rows.is_returned) = COUNT(*) THEN '#7C3AED'
                           WHEN SUM(item_rows.is_returned) > 0 THEN '#D97706'
                           ELSE '#059669'
                       END AS overall_status_badge_color
                FROM (
                    SELECT crd.committee_report_id,
                           'DOCUMENT' AS item_type,
                           d.id AS item_id,
                           CASE
                               WHEN ds.name = 'Returned to Plenary'
                                 OR EXISTS (
                                     SELECT 1 FROM document_events de
                                     WHERE de.document_id = d.id
                                       AND de.event_type = 'RETURNED_TO_PLENARY'
                                 ) THEN 1
                               ELSE 0
                           END AS is_returned
                    FROM committee_report_documents crd
                    INNER JOIN documents d ON d.id = crd.document_id
                    LEFT JOIN document_statuses ds ON ds.id = d.current_status_id

                    UNION ALL

                    SELECT crd.committee_report_id,
                           'ATTACHMENT' AS item_type,
                           da.id AS item_id,
                                                     CASE
                                                             WHEN ds.name = 'Returned to Plenary'
                                                                 OR crt.id IS NOT NULL
                                                                 OR EXISTS (
                                                                         SELECT 1 FROM document_events de
                                                                         WHERE de.document_id = d.id
                                                                             AND de.event_type = 'RETURNED_TO_PLENARY'
                                                                 ) THEN 1
                                                             ELSE 0
                                                     END AS is_returned
                    FROM committee_report_documents crd
                    INNER JOIN document_attachments da ON da.document_id = crd.document_id
                                        INNER JOIN documents d ON d.id = crd.document_id
                                        LEFT JOIN document_statuses ds ON ds.id = d.current_status_id
                    LEFT JOIN committee_report_attachment_transmittals crt
                        ON crt.committee_report_id = crd.committee_report_id
                       AND crt.attachment_id = da.id
                ) item_rows
                GROUP BY item_rows.committee_report_id
            ) rir ON rir.committee_report_id = cr.id
        ";

        // ── Count ─────────────────────────────────────────────────────────────
        $countSql = "
            SELECT COUNT(DISTINCT cr.id)
            FROM committee_reports cr
            LEFT JOIN committee_report_documents  crd ON crd.committee_report_id = cr.id
            LEFT JOIN documents                    d  ON d.id = crd.document_id
            LEFT JOIN document_statuses           ds  ON ds.id = d.current_status_id
            {$reportProgressJoin}
            WHERE 1=1 {$where}
        ";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total      = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        // ── List query ────────────────────────────────────────────────────────
        $listSql = "
            SELECT
                cr.id                               AS report_id,
                cr.report_type,
                cr.report_number,
                cr.summary_of_findings,
                cr.created_at,
                cr.returned_to_plenary_at,

                -- Creator
                COALESCE(
                    CONCAT(TRIM(COALESCE(ui.first_name,'')), ' ', TRIM(COALESCE(ui.last_name,''))),
                    ua.username,
                    '—'
                )                                   AS created_by_name,
                ua.username                         AS created_by_username,

                -- Committees (aggregated)
                GROUP_CONCAT(
                    DISTINCT c.name
                    ORDER BY c.name
                    SEPARATOR ', '
                )                                   AS committee_names,

                -- Primary document fields
                GROUP_CONCAT(DISTINCT d.tracking_number ORDER BY d.tracking_number SEPARATOR ', ')
                                                    AS tracking_number,
                GROUP_CONCAT(DISTINCT dt.name ORDER BY dt.name SEPARATOR ', ')
                                                    AS document_type_name,
                MAX(d.subject_matter)                AS subject_matter,
                COALESCE(rir.overall_status, 'Committee Report Created') AS status,
                COALESCE(rir.overall_status_badge_color, '#059669') AS status_badge_color,

                -- Hearing outcome
                ch.outcome                          AS hearing_outcome,

                -- Case fields (NULL when report came from the hearing workflow)
                MAX(cc.id)                          AS case_id,
                GROUP_CONCAT(DISTINCT cc.docket_number ORDER BY cc.docket_number SEPARATOR ', ')
                                                    AS case_docket_number,
                GROUP_CONCAT(DISTINCT cc.nature_of_case ORDER BY cc.nature_of_case SEPARATOR '; ')
                                                    AS case_nature,

                COALESCE(rir.attachment_count, 0)    AS attachment_count

            FROM committee_reports cr
            LEFT JOIN committee_report_documents  crd ON crd.committee_report_id = cr.id
            LEFT JOIN documents                    d  ON d.id  = crd.document_id
            LEFT JOIN document_types              dt  ON dt.id = d.document_type_id
            LEFT JOIN document_statuses           ds  ON ds.id = d.current_status_id
            LEFT JOIN committee_report_committees crc ON crc.committee_report_id = cr.id
            LEFT JOIN committees                   c  ON c.id  = crc.committee_id
            {$reportProgressJoin}
            LEFT JOIN user_accounts               ua  ON ua.id = cr.created_by
            LEFT JOIN user_info                   ui  ON ui.user_account_id = ua.id
            LEFT JOIN committee_hearings          ch  ON ch.id = cr.hearing_id
            LEFT JOIN committee_cases             cc  ON cc.document_id = d.id
            WHERE 1=1 {$where}
            GROUP BY
                cr.id, cr.report_type, cr.report_number, cr.summary_of_findings,
                cr.created_at, cr.returned_to_plenary_at,
                ui.first_name, ui.last_name, ua.username,
                rir.overall_status, rir.overall_status_badge_color, rir.attachment_count,
                ch.outcome
            ORDER BY cr.created_at DESC, cr.id DESC
            LIMIT ? OFFSET ?
        ";

        $listStmt = $this->pdo->prepare($listSql);
        $listStmt->execute([...$params, self::PER_PAGE, $offset]);
        $reports = $listStmt->fetchAll();

        $pageTitle = 'Committee Reports';
        require __DIR__ . '/../../../resources/views/committee/reports/index.php';
    }

    // =========================================================================
    // 2. Show — report detail (delegates to CommitteeHearingController logic)
    // =========================================================================

    public function show(): void
    {
        $userId   = auth_id();
        $reportId = (int) ($_GET['id'] ?? 0);

        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }
        if ($reportId <= 0) {
            flash_set('error', 'Invalid report ID.');
            redirect('committee/reports');
        }

        // Load report
        $rptStmt = $this->pdo->prepare("
            SELECT cr.*,
                   ua.username AS created_by_username,
                   CONCAT(COALESCE(ui.first_name,''), ' ', COALESCE(ui.last_name,''))
                               AS created_by_name
            FROM committee_reports cr
            LEFT JOIN user_accounts ua ON ua.id = cr.created_by
            LEFT JOIN user_info     ui ON ui.user_account_id = ua.id
            WHERE cr.id = ?
            LIMIT 1
        ");
        $rptStmt->execute([$reportId]);
        $report = $rptStmt->fetch();

        if (!$report) {
            flash_set('error', 'Committee Report not found.');
            redirect('committee/reports');
        }

        // Documents linked to this report
        $docsStmt = $this->pdo->prepare("
            SELECT d.id, d.tracking_number, d.subject_matter,
                   dt.name AS document_type_name, dt.badge_color AS document_type_badge_color,
                                     ds.name AS status, ds.badge_color AS status_badge_color,
                                     (SELECT MAX(de.created_at)
                                        FROM document_events de
                                        WHERE de.document_id = d.id
                                            AND de.event_type = 'RETURNED_TO_PLENARY') AS transmitted_at
            FROM committee_report_documents crd
            INNER JOIN documents          d  ON  d.id = crd.document_id
            LEFT  JOIN document_types    dt  ON dt.id = d.document_type_id
            LEFT  JOIN document_statuses ds  ON ds.id = d.current_status_id
            WHERE crd.committee_report_id = ?
            ORDER BY d.tracking_number ASC
        ");
        $docsStmt->execute([$reportId]);
        $reportDocuments = $docsStmt->fetchAll();

        foreach ($reportDocuments as &$reportDocument) {
            $reportDocument['is_returned'] = !empty($reportDocument['transmitted_at'])
                || $reportDocument['status'] === 'Returned to Plenary';
        }
        unset($reportDocument);

        // Committees linked to this report
        $commStmt = $this->pdo->prepare("
            SELECT c.id, c.name
            FROM committee_report_committees crc
            INNER JOIN committees c ON c.id = crc.committee_id
            WHERE crc.committee_report_id = ?
            ORDER BY c.name ASC
        ");
        $commStmt->execute([$reportId]);
        $reportCommittees = $commStmt->fetchAll();

        // Every file attached to any report member is part of the shared report.
        $attachmentsByDocument = [];
        if (!empty($reportDocuments)) {
            $documentIds = array_map('intval', array_column($reportDocuments, 'id'));
            $docPlaceholders = implode(',', array_fill(0, count($documentIds), '?'));
            $attStmt = $this->pdo->prepare("
                SELECT da.*, ua.username AS uploaded_by_username,
                       crt.returned_at AS transmitted_at,
                       COALESCE(NULLIF(CONCAT_WS(' ', NULLIF(ui.first_name, ''), NULLIF(ui.last_name, '')), ''),
                                returned_user.username) AS transmitted_by
                FROM document_attachments da
                LEFT JOIN user_accounts ua ON ua.id = da.uploaded_by
                LEFT JOIN committee_report_attachment_transmittals crt
                    ON crt.attachment_id = da.id AND crt.committee_report_id = ?
                LEFT JOIN user_accounts returned_user ON returned_user.id = crt.returned_by
                LEFT JOIN user_info ui ON ui.user_account_id = crt.returned_by
                WHERE da.document_id IN ({$docPlaceholders})
                ORDER BY da.document_id, da.created_at, da.id
            ");
            $attStmt->execute([$reportId, ...$documentIds]);
            foreach ($attStmt->fetchAll() as $attachment) {
                $attachmentsByDocument[(int) $attachment['document_id']][] = $attachment;
            }
        }

        $reportAttachmentsStmt = $this->pdo->prepare("
            SELECT cra.*, ua.username AS uploaded_by_username
            FROM committee_report_attachments cra
            LEFT JOIN user_accounts ua ON ua.id = cra.uploaded_by
            WHERE cra.committee_report_id = ?
            ORDER BY cra.created_at ASC, cra.id ASC
        ");
        $reportAttachmentsStmt->execute([$reportId]);
        $reportAttachments = $reportAttachmentsStmt->fetchAll();
        $reportAttachmentCount = count($reportAttachments);

        $itemCount = count($reportDocuments);
        $returnedCount = 0;
        foreach ($reportDocuments as $reportDocument) {
            $itemAttachments = $attachmentsByDocument[(int) $reportDocument['id']] ?? [];
            $itemCount += count($itemAttachments);
            if (!empty($reportDocument['is_returned'])) {
                $returnedCount += 1 + count($itemAttachments);
            } else {
                foreach ($itemAttachments as $attachment) {
                    $returnedCount += !empty($attachment['transmitted_at']) ? 1 : 0;
                }
            }
        }
        $reportStatus = $itemCount > 0 && $returnedCount === $itemCount
            ? 'Returned to Plenary'
            : ($returnedCount > 0 ? 'Partially Returned' : 'Committee Report Created');

        // Agenda and hearing
        $agenda  = null;
        $hearing = null;

        if (!empty($report['agenda_id'])) {
            $agStmt = $this->pdo->prepare("
                SELECT ag.*, GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') AS committee_names
                FROM agendas ag
                LEFT JOIN agenda_committees ac ON ac.agenda_id = ag.id
                LEFT JOIN committees         c ON c.id = ac.committee_id
                WHERE ag.id = ?
                GROUP BY ag.id
                LIMIT 1
            ");
            $agStmt->execute([$report['agenda_id']]);
            $agenda = $agStmt->fetch();
        }

        if (!empty($report['hearing_id'])) {
            $hStmt = $this->pdo->prepare("
                SELECT ch.*, ua.username AS performed_by_username,
                       CONCAT(COALESCE(ui.first_name,''),' ',COALESCE(ui.last_name,'')) AS performed_by_name
                FROM committee_hearings ch
                LEFT JOIN user_accounts ua ON ua.id = ch.performed_by
                LEFT JOIN user_info     ui ON ui.user_account_id = ua.id
                WHERE ch.id = ?
                LIMIT 1
            ");
            $hStmt->execute([$report['hearing_id']]);
            $hearing = $hStmt->fetch();
        }

        // ── Linked case (present when the report came from the Cases workflow) ─
        $linkedCase = null;
        if (!empty($reportDocuments)) {
            $primaryDocId = (int) $reportDocuments[0]['id'];
            $ccStmt = $this->pdo->prepare("
                SELECT cc.id, cc.docket_number, cc.nature_of_case,
                       cc.complainant_details, cc.respondents, cc.final_outcome
                FROM committee_cases cc
                WHERE cc.document_id = ?
                LIMIT 1
            ");
            $ccStmt->execute([$primaryDocId]);
            $linkedCase = $ccStmt->fetch() ?: null;
        }

        // Source route for breadcrumb back-link
        $fromReports = true;

        $pageTitle = 'Committee Report #' . htmlspecialchars($report['report_number'] ?? $reportId);
        require __DIR__ . '/../../../resources/views/committee/reports/show.php';
    }

    public function documentShow(): void
    {
        $userId = auth_id();
        $reportId = (int) ($_GET['report_id'] ?? 0);
        $documentId = (int) ($_GET['id'] ?? 0);

        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
            return;
        }

        $membershipStmt = $this->pdo->prepare("
            SELECT 1
            FROM committee_report_documents
            WHERE committee_report_id = ? AND document_id = ?
            LIMIT 1
        ");
        $membershipStmt->execute([$reportId, $documentId]);
        if (!$membershipStmt->fetchColumn()) {
            flash_set('error', 'This document is not part of the selected Committee Report.');
            redirect('committee/reports/show?id=' . $reportId);
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
            redirect('committee/reports/show?id=' . $reportId);
            return;
        }

        $attachmentStmt = $this->pdo->prepare("
            SELECT da.*, ua.username AS uploaded_by_username
            FROM document_attachments da
            LEFT JOIN user_accounts ua ON ua.id = da.uploaded_by
            WHERE da.document_id = ?
            ORDER BY da.created_at ASC, da.id ASC
        ");
        $attachmentStmt->execute([$documentId]);
        $attachments = $attachmentStmt->fetchAll();

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
        $routes = $routesStmt->fetchAll();

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
        $checklistItems = $checklistStmt->fetchAll();

        $documentEvents = $this->docService->getDocumentEvents($documentId);
        $mfrLink = null;
        $relatedMotions = [];
        $showMfrRelationship = false;
        $detailBackUrl = BASE_URL . '/committee/reports/show?id=' . $reportId;
        $detailBackLabel = 'Back to Committee Report';
        $pageTitle = 'Document Details';
        $pageSubtitle = $document['tracking_number'];

        require __DIR__ . '/../../../resources/views/receiving/routed-documents/show.php';
    }

    // =========================================================================
    // 3. Return to Plenary — POST action
    // =========================================================================

    public function returnToPlenary(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        $reportId = (int) ($_POST['report_id'] ?? 0);
        if ($reportId <= 0) {
            flash_set('error', 'Invalid request.');
            redirect('committee/reports');
        }

        $itemId = (int) ($_POST['item_id'] ?? 0);
        $itemType = 'DOCUMENT';
        if ($itemId <= 0) {
            flash_set('error', 'Select one document to return.');
            redirect('committee/reports/show?id=' . $reportId);
        }

        // ── Resolve required statuses ─────────────────────────────────────────
        $returnedStatus = null;
        try {
            $returnedStatus = $this->requireDocumentStatus('Returned to Plenary');
        } catch (RuntimeException $e) {
            flash_set('error', 'Required document status not found. Please run pending migrations.');
            redirect('committee/reports');
            return;
        }

        // ── Load report ───────────────────────────────────────────────────────
        $rptStmt = $this->pdo->prepare("
            SELECT cr.id, cr.report_number
            FROM committee_reports cr
            WHERE cr.id = ?
            LIMIT 1
        ");
        $rptStmt->execute([$reportId]);
        $report = $rptStmt->fetch();

        if (!$report) {
            flash_set('error', 'Committee Report not found.');
            redirect('committee/reports');
        }

        $reportNumber = $report['report_number'] ?? ('RPT-' . $reportId);

        // ── Resolve username ──────────────────────────────────────────────────
        $uStmt = $this->pdo->prepare("SELECT username FROM user_accounts WHERE id = ? LIMIT 1");
        $uStmt->execute([$userId]);
        $username = (string) ($uStmt->fetchColumn() ?: '');

        // ── Transaction: transmit exactly one member of the shared report ────
        $itemLabel = '';
        try {
            $this->pdo->beginTransaction();

            if ($itemType === 'DOCUMENT') {
                $docStmt = $this->pdo->prepare("
                    SELECT d.id, d.tracking_number, d.current_status_id,
                           ds.name AS status_name
                    FROM committee_report_documents crd
                    INNER JOIN documents d ON d.id = crd.document_id
                    LEFT JOIN document_statuses ds ON ds.id = d.current_status_id
                    WHERE crd.committee_report_id = ? AND d.id = ?
                    LIMIT 1 FOR UPDATE
                ");
                $docStmt->execute([$reportId, $itemId]);
                $document = $docStmt->fetch();
                if (!$document) {
                    throw new RuntimeException('The selected document is not part of this Committee Report.');
                }

                $eventCheck = $this->pdo->prepare("
                    SELECT COUNT(*) FROM document_events
                    WHERE document_id = ? AND event_type = 'RETURNED_TO_PLENARY'
                ");
                $eventCheck->execute([$itemId]);
                if ((int) $eventCheck->fetchColumn() > 0
                    || $document['status_name'] === 'Returned to Plenary') {
                    throw new RuntimeException('This document has already been returned to Plenary.');
                }

                $documentId = (int) $document['id'];
                $prevStatusId = (int) $document['current_status_id'];
                $prevStatus = $document['status_name'] ?? '';
                $itemLabel = $document['tracking_number'];
                $remarks = "Document {$itemLabel} from Committee Report #{$reportNumber} returned to Plenary.";

                $this->pdo->prepare("
                    INSERT INTO document_events
                        (document_id, event_type, phase, performed_by,
                         from_status_id, to_status_id, remarks, metadata)
                    VALUES (?, 'RETURNED_TO_PLENARY', 'COMMITTEE', ?, ?, ?, ?, ?)
                ")->execute([
                    $documentId,
                    $userId,
                    $prevStatusId ?: null,
                    $returnedStatus['id'],
                    $remarks,
                    json_encode([
                        'report_id' => $reportId,
                        'report_number' => $reportNumber,
                        'document_id' => $documentId,
                        'prev_status' => $prevStatus,
                        'new_status' => $returnedStatus['name'],
                        'returned_by' => $userId,
                        'returned_by_username' => $username,
                        'ip_address' => client_ip(),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);

                $plenaryRoleId = $this->requirePlenaryRoleId();
                $this->pdo->prepare("
                    UPDATE document_assignments
                    SET decision = 'DECLINED',
                        declined_at = NOW(),
                        completed_at = NOW(),
                        decline_reason = ?,
                        remarks = ?
                    WHERE document_id = ?
                      AND phase = 'COMMITTEE'
                      AND decision IN ('PENDING', 'ACCEPTED')
                      AND completed_at IS NULL
                ")->execute([$remarks, $remarks, $documentId]);

                $this->pdo->prepare("
                    INSERT INTO document_assignments (
                        document_id, assigned_to_role_id, phase, assigned_by,
                        decision, received_at, accepted_by, assigned_to_user_id
                    ) VALUES (?, ?, 'PLENARY', ?, 'PENDING', NOW(), NULL, NULL)
                ")->execute([$documentId, $plenaryRoleId, $userId]);

                $this->pdo->prepare("
                    UPDATE documents
                    SET current_phase = 'PLENARY',
                        current_status_id = ?,
                        current_owner_user_id = NULL,
                        updated_by = ?
                    WHERE id = ?
                ")->execute([$returnedStatus['id'], $userId, $documentId]);

                $this->pdo->prepare("
                    INSERT INTO document_routes
                        (document_id, from_phase, to_phase, routed_by, routed_to_role_id, remarks)
                    VALUES (?, 'COMMITTEE', 'PLENARY', ?, ?, ?)
                ")->execute([$documentId, $userId, $plenaryRoleId, $remarks]);
            }

            $this->pdo->commit();

            // ── Post-commit logging ───────────────────────────────────────────
            audit_log(
                'UPDATE',
                $itemType === 'DOCUMENT' ? 'Document' : 'CommitteeReportAttachment',
                (string) $itemId,
                null,
                [
                    'report_id'     => $reportId,
                    'report_number' => $reportNumber,
                    'item_type'     => $itemType,
                    'item_id'       => $itemId,
                    'item_label'    => $itemLabel,
                    'returned_by'   => $userId,
                ],
                "{$itemType} {$itemLabel} from Committee Report #{$reportNumber} returned to Plenary by {$username}."
            );
            system_log('INFO', 'Committee report item returned to Plenary', [
                'report_id'     => $reportId,
                'report_number' => $reportNumber,
                'item_type'     => $itemType,
                'item_id'       => $itemId,
                'returned_by'   => $userId,
            ]);

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            system_log('ERROR', 'CommitteeReportsController::returnToPlenary exception', [
                'error'     => $e->getMessage(),
                'report_id' => $reportId,
            ]);
            flash_set('error', $e instanceof RuntimeException ? $e->getMessage() : 'A database error occurred. Please try again.');
            redirect('committee/reports/show?id=' . $reportId);
        }

        flash_set('success', "{$itemLabel} has been returned to Plenary.");
        redirect('committee/reports/show?id=' . $reportId);
    }

    public function manageAttachments(): void
    {
        $userId = auth_id();
        $reportId = (int) ($_POST['report_id'] ?? 0);
        $action = trim($_POST['attachment_action'] ?? '');

        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
            return;
        }
        if ($reportId <= 0 || !in_array($action, ['add', 'replace', 'remove'], true)) {
            flash_set('error', 'Invalid Committee Report attachment request.');
            redirect('committee/reports');
            return;
        }

        $reportStmt = $this->pdo->prepare('SELECT id FROM committee_reports WHERE id = ? LIMIT 1');
        $reportStmt->execute([$reportId]);
        if (!$reportStmt->fetchColumn()) {
            flash_set('error', 'Committee Report not found.');
            redirect('committee/reports');
            return;
        }

        $uploadService = new DocumentService($this->pdo);
        $stagedFiles = [];
        $pendingFileLogs = [];
        $oldStoredPath = null;
        $attachmentId = (int) ($_POST['attachment_id'] ?? 0);
        $attachment = null;

        if (in_array($action, ['replace', 'remove'], true)) {
            $attachmentStmt = $this->pdo->prepare("
                SELECT id, file_name, stored_path
                FROM committee_report_attachments
                WHERE id = ? AND committee_report_id = ?
                LIMIT 1
            ");
            $attachmentStmt->execute([$attachmentId, $reportId]);
            $attachment = $attachmentStmt->fetch();
            if (!$attachment) {
                flash_set('error', 'Committee Report attachment not found.');
                redirect('committee/reports/show?id=' . $reportId);
                return;
            }
            $oldStoredPath = (string) $attachment['stored_path'];
        }

        if ($action === 'add') {
            $uploadedFiles = $_FILES['attachments'] ?? [];
            $errors = $uploadService->validateFileUploads($uploadedFiles, true);
            if ($errors !== []) {
                flash_set('errors', $errors);
                flash_set('error', 'Please select valid Committee Report attachment files.');
                redirect('committee/reports/show?id=' . $reportId);
                return;
            }
        } elseif ($action === 'replace') {
            $rawFile = $_FILES['replacement'] ?? [];
            $uploadedFiles = [
                'name' => [$rawFile['name'] ?? ''],
                'type' => [$rawFile['type'] ?? ''],
                'tmp_name' => [$rawFile['tmp_name'] ?? ''],
                'error' => [$rawFile['error'] ?? UPLOAD_ERR_NO_FILE],
                'size' => [$rawFile['size'] ?? 0],
            ];
            $errors = $uploadService->validateFileUploads($uploadedFiles, true);
            if ($errors !== []) {
                flash_set('errors', $errors);
                flash_set('error', 'Please select a valid replacement file.');
                redirect('committee/reports/show?id=' . $reportId);
                return;
            }
        }

        if (in_array($action, ['add', 'replace'], true)) {
            try {
                $stagedFiles = $uploadService->stageCommitteeReportFileUploads($uploadedFiles);
            } catch (Throwable $e) {
                flash_set('error', 'File upload failed: ' . $e->getMessage());
                redirect('committee/reports/show?id=' . $reportId);
                return;
            }
        }

        try {
            $this->pdo->beginTransaction();
            if ($action === 'add') {
                $pendingFileLogs = $uploadService->insertStagedCommitteeReportAttachments(
                    $stagedFiles,
                    $reportId,
                    $userId
                );
            } elseif ($action === 'replace') {
                $replacement = $stagedFiles[0];
                $updateStmt = $this->pdo->prepare("
                    UPDATE committee_report_attachments
                    SET uploaded_by = ?, file_name = ?, stored_path = ?, mime_type = ?,
                        file_size = ?, created_at = NOW()
                    WHERE id = ? AND committee_report_id = ?
                ");
                $updateStmt->execute([
                    $userId,
                    $replacement['original_name'],
                    $replacement['relative_path'],
                    $replacement['mime_type'],
                    $replacement['file_size'],
                    $attachmentId,
                    $reportId,
                ]);
                if ($updateStmt->rowCount() !== 1) {
                    throw new RuntimeException('The Committee Report attachment could not be replaced.');
                }
            } else {
                $deleteStmt = $this->pdo->prepare("
                    DELETE FROM committee_report_attachments
                    WHERE id = ? AND committee_report_id = ?
                ");
                $deleteStmt->execute([$attachmentId, $reportId]);
                if ($deleteStmt->rowCount() !== 1) {
                    throw new RuntimeException('The Committee Report attachment could not be removed.');
                }
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            foreach ($stagedFiles as $stagedFile) {
                if (file_exists($stagedFile['abs_path'])) {
                    @unlink($stagedFile['abs_path']);
                }
            }
            system_log('ERROR', 'CommitteeReportsController::manageAttachments exception', [
                'report_id' => $reportId,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
            flash_set('error', 'A database error occurred while updating Committee Report attachments.');
            redirect('committee/reports/show?id=' . $reportId);
            return;
        }

        if ($oldStoredPath !== null && in_array($action, ['replace', 'remove'], true)) {
            $oldAbsolutePath = __DIR__ . '/../../../public/' . ltrim($oldStoredPath, '/');
            if (is_file($oldAbsolutePath)) {
                @unlink($oldAbsolutePath);
            }
        }
        if ($pendingFileLogs !== []) {
            $uploadService->flushCommitteeReportFileUploadLogs($pendingFileLogs);
        }
        if ($action === 'replace') {
            $replacement = $stagedFiles[0];
            log_file_upload(
                $replacement['original_name'],
                $replacement['file_size'],
                $replacement['mime_type'],
                'CommitteeReport',
                (string) $reportId,
                $replacement['relative_path']
            );
        }

        $changedFileNames = array_column($stagedFiles, 'original_name');
        if ($attachment !== null && $action === 'remove') {
            $changedFileNames[] = $attachment['file_name'];
        }
        $actionLabel = match ($action) {
            'add' => 'added',
            'replace' => 'replaced',
            default => 'removed',
        };
        audit_log(
            'UPDATE',
            'CommitteeReport',
            (string) $reportId,
            null,
            [
                'attachment_action' => $action,
                'attachment_id' => $attachmentId ?: null,
                'file_names' => $changedFileNames,
            ],
            "Committee Report #{$reportId} attachments {$actionLabel} by user {$userId}."
        );
        system_log('INFO', 'Committee Report attachments changed', [
            'report_id' => $reportId,
            'action' => $action,
            'attachment_id' => $attachmentId ?: null,
            'file_names' => $changedFileNames,
            'performed_by' => $userId,
        ]);

        $message = match ($action) {
            'add' => 'Committee Report attachments added.',
            'replace' => 'Committee Report attachment replaced.',
            default => 'Committee Report attachment removed.',
        };
        flash_set('success', $message);
        redirect('committee/reports/show?id=' . $reportId);
    }

    // =========================================================================
    // Private helpers (mirrored from CommitteeHearingController)
    // =========================================================================

    /**
     * Build WHERE clause + param array from filter inputs.
     *
     * @return array{0: string, 1: array}
     */
    private function buildFilterClause(
        string $search,
        string $type,
        string $status,
        string $dateFrom,
        string $dateTo
    ): array {
        $where  = '';
        $params = [];

        if ($search !== '') {
            $where   .= " AND (cr.report_number LIKE ? OR d.tracking_number LIKE ? OR d.subject_matter LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if ($type !== '') {
            $where   .= ' AND cr.report_type = ?';
            $params[] = $type;
        }

        if ($status !== '') {
            $where   .= " AND COALESCE(rir.overall_status, 'Committee Report Created') = ?";
            $params[] = $status;
        }

        if ($dateFrom !== '') {
            $where   .= ' AND DATE(cr.created_at) >= ?';
            $params[] = $dateFrom;
        }

        if ($dateTo !== '') {
            $where   .= ' AND DATE(cr.created_at) <= ?';
            $params[] = $dateTo;
        }

        return [$where, $params];
    }

    /**
     * Resolve a document_status row by name.
     * Throws RuntimeException if not found.
     */
    private function requireDocumentStatus(string $name): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, name, badge_color
            FROM document_statuses
            WHERE name = ? AND is_active = 1 AND is_deleted = 0
            LIMIT 1
        ");
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException(
                "Document status '{$name}' not found. Run pending migrations to seed it."
            );
        }
        return $row;
    }

    /**
     * Return only the ID of a status (non-throwing; returns 0 on miss).
     */
    private function resolveStatusId(string $name): int
    {
        try {
            return (int) $this->requireDocumentStatus($name)['id'];
        } catch (RuntimeException $e) {
            system_log('ERROR', "CommitteeReportsController::resolveStatusId failed for '{$name}'", [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Resolve the Plenary role ID.
     * Throws RuntimeException if the role row is missing.
     */
    private function requirePlenaryRoleId(): int
    {
        $stmt = $this->pdo->query(
            "SELECT id FROM roles WHERE name = 'Plenary' AND is_active = 1 AND is_deleted = 0 LIMIT 1"
        );
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('Plenary role not found.');
        }
        return (int) $row['id'];
    }
}

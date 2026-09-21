<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/DocumentService.php';

/**
 * CommitteeReportsController
 *
 * Manages the Committee Reports listing page and the Return to Plenary action.
 * This is the step that follows a Committee or Joint Committee Report being
 * created (document status: "Committee Report Created").
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
 *   returnToPlenary() additionally validates report eligibility and prevents
 *   duplicate submissions.
 *
 * RETURN TO PLENARY TRANSACTION PATTERN
 *   1. Resolve "Returned to Plenary" status ID (throws if missing migration).
 *   2. Load the report row (must exist, status must be "Committee Report Created").
 *   3. Load primary document for the report.
 *   4. beginTransaction().
 *   5. UPDATE committee_reports  → returned_to_plenary_by, returned_to_plenary_at.
 *   6. UPDATE documents          → current_status_id = "Returned to Plenary".
 *   7. INSERT document_events    → type RETURNED_TO_PLENARY.
 *   8. INSERT document_routes    → from/to PLENARY, remarks.
 *   9. commit().
 *  10. audit_log() + system_log() after commit (separate PDO connection).
 *  11. Redirect back to committee/reports with success flash.
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

        // ── Count ─────────────────────────────────────────────────────────────
        $countSql = "
            SELECT COUNT(DISTINCT cr.id)
            FROM committee_reports cr
            LEFT JOIN committee_report_documents  crd ON crd.committee_report_id = cr.id
            LEFT JOIN documents                    d  ON d.id = crd.document_id
            LEFT JOIN document_statuses           ds  ON ds.id = d.current_status_id
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
                d.id                                AS document_id,
                d.tracking_number,
                d.subject_matter,
                dt.name                             AS document_type_name,
                dt.badge_color                      AS document_type_badge_color,
                ds.name                             AS status,
                ds.badge_color                      AS status_badge_color,

                -- Hearing outcome
                ch.outcome                          AS hearing_outcome,

                -- Attachment count
                (
                    SELECT COUNT(*)
                    FROM document_attachments da
                    WHERE da.document_id = d.id
                      AND da.attachment_type = 'COMMITTEE_REPORT'
                )                                   AS attachment_count

            FROM committee_reports cr
            LEFT JOIN committee_report_documents  crd ON crd.committee_report_id = cr.id
            LEFT JOIN documents                    d  ON d.id  = crd.document_id
            LEFT JOIN document_types              dt  ON dt.id = d.document_type_id
            LEFT JOIN document_statuses           ds  ON ds.id = d.current_status_id
            LEFT JOIN committee_report_committees crc ON crc.committee_report_id = cr.id
            LEFT JOIN committees                   c  ON c.id  = crc.committee_id
            LEFT JOIN user_accounts               ua  ON ua.id = cr.created_by
            LEFT JOIN user_info                   ui  ON ui.user_account_id = ua.id
            LEFT JOIN committee_hearings          ch  ON ch.id = cr.hearing_id
            WHERE 1=1 {$where}
            GROUP BY
                cr.id, cr.report_type, cr.report_number, cr.summary_of_findings,
                cr.created_at, cr.returned_to_plenary_at,
                ui.first_name, ui.last_name, ua.username,
                d.id, d.tracking_number, d.subject_matter,
                dt.name, dt.badge_color,
                ds.name, ds.badge_color,
                ch.outcome
            ORDER BY cr.created_at DESC, cr.id DESC
            LIMIT ? OFFSET ?
        ";

        $listStmt = $this->pdo->prepare($listSql);
        $listStmt->execute([...$params, self::PER_PAGE, $offset]);
        $reports = $listStmt->fetchAll();

        // ── Resolve "Committee Report Created" status ID for eligibility check ─
        $committeeReportCreatedId = $this->resolveStatusId('Committee Report Created');

        $success = flash_get('success');
        $error   = flash_get('error');

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
                   ds.name AS status, ds.badge_color AS status_badge_color
            FROM committee_report_documents crd
            INNER JOIN documents          d  ON  d.id = crd.document_id
            LEFT  JOIN document_types    dt  ON dt.id = d.document_type_id
            LEFT  JOIN document_statuses ds  ON ds.id = d.current_status_id
            WHERE crd.committee_report_id = ?
            ORDER BY d.tracking_number ASC
        ");
        $docsStmt->execute([$reportId]);
        $reportDocuments = $docsStmt->fetchAll();

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

        // Attachments (COMMITTEE_REPORT type)
        $attachments = [];
        if (!empty($reportDocuments)) {
            $primaryDocId = (int) $reportDocuments[0]['id'];
            $attStmt = $this->pdo->prepare("
                SELECT da.*, ua.username AS uploaded_by_username
                FROM document_attachments da
                LEFT JOIN user_accounts ua ON ua.id = da.uploaded_by
                WHERE da.document_id = ?
                  AND da.attachment_type = 'COMMITTEE_REPORT'
                ORDER BY da.created_at ASC
            ");
            $attStmt->execute([$primaryDocId]);
            $attachments = $attStmt->fetchAll();
        }

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

        // Resolve "Committee Report Created" status ID for button visibility
        $committeeReportCreatedId = $this->resolveStatusId('Committee Report Created');

        // Source route for breadcrumb back-link
        $fromReports = true;

        $success = flash_get('success');
        $error   = flash_get('error');

        $pageTitle = 'Committee Report #' . htmlspecialchars($report['report_number'] ?? $reportId);
        require __DIR__ . '/../../../resources/views/committee/reports/show.php';
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

        // ── Resolve required statuses ─────────────────────────────────────────
        try {
            $returnedStatus = $this->requireDocumentStatus('Returned to Plenary');
            $reportCreatedStatus = $this->requireDocumentStatus('Committee Report Created');
        } catch (RuntimeException $e) {
            flash_set('error', 'Required document status not found. Please run pending migrations.');
            redirect('committee/reports');
        }

        // ── Load report ───────────────────────────────────────────────────────
        $rptStmt = $this->pdo->prepare("
            SELECT cr.*,
                   ua.username AS created_by_username
            FROM committee_reports cr
            LEFT JOIN user_accounts ua ON ua.id = cr.created_by
            WHERE cr.id = ?
            LIMIT 1
        ");
        $rptStmt->execute([$reportId]);
        $report = $rptStmt->fetch();

        if (!$report) {
            flash_set('error', 'Committee Report not found.');
            redirect('committee/reports');
        }

        // ── Guard: already returned? ──────────────────────────────────────────
        if (!empty($report['returned_to_plenary_at'])) {
            flash_set('error', 'This report has already been returned to Plenary.');
            redirect('committee/reports');
        }

        // ── Load primary document and verify current status ───────────────────
        $docStmt = $this->pdo->prepare("
            SELECT d.id, d.tracking_number, d.subject_matter, d.current_status_id,
                   ds.name AS status_name
            FROM committee_report_documents crd
            INNER JOIN documents d ON d.id = crd.document_id
            LEFT  JOIN document_statuses ds ON ds.id = d.current_status_id
            WHERE crd.committee_report_id = ?
            ORDER BY crd.id ASC
            LIMIT 1
        ");
        $docStmt->execute([$reportId]);
        $document = $docStmt->fetch();

        if (!$document) {
            flash_set('error', 'No document linked to this report.');
            redirect('committee/reports');
        }

        // ── Guard: document must be "Committee Report Created" ────────────────
        if ((int) $document['current_status_id'] !== (int) $reportCreatedStatus['id']) {
            flash_set('error',
                'This report is not eligible for return to Plenary. ' .
                'Only reports with status "Committee Report Created" may be returned.'
            );
            redirect('committee/reports');
        }

        $documentId   = (int) $document['id'];
        $prevStatusId = (int) $document['current_status_id'];
        $prevStatus   = $document['status_name'];
        $reportNumber = $report['report_number'] ?? ('RPT-' . $reportId);

        // ── Resolve username ──────────────────────────────────────────────────
        $uStmt = $this->pdo->prepare("SELECT username FROM user_accounts WHERE id = ? LIMIT 1");
        $uStmt->execute([$userId]);
        $username = (string) ($uStmt->fetchColumn() ?: '');

        // ── Resolve Committee role ID ─────────────────────────────────────────
        try {
            $committeeRoleId = $this->requireCommitteeRoleId();
        } catch (RuntimeException $e) {
            flash_set('error', 'Committee role not found. Please contact your administrator.');
            redirect('committee/reports');
        }

        // ── Transaction ───────────────────────────────────────────────────────
        try {
            $this->pdo->beginTransaction();

            // 1. Mark report as returned
            $this->pdo->prepare("
                UPDATE committee_reports
                SET returned_to_plenary_by = ?,
                    returned_to_plenary_at = NOW(),
                    updated_at             = NOW()
                WHERE id = ?
            ")->execute([$userId, $reportId]);

            // 2. Update document status → "Returned to Plenary"
            $this->pdo->prepare("
                UPDATE documents
                SET current_status_id = ?,
                    updated_by        = ?
                WHERE id = ?
            ")->execute([$returnedStatus['id'], $userId, $documentId]);

            // 3. Workflow event
            $this->pdo->prepare("
                INSERT INTO document_events
                    (document_id, event_type, phase, performed_by,
                     from_status_id, to_status_id, remarks, metadata)
                VALUES (?, 'RETURNED_TO_PLENARY', 'COMMITTEE', ?, ?, ?, ?, ?)
            ")->execute([
                $documentId,
                $userId,
                $prevStatusId,
                $returnedStatus['id'],
                "Committee Report #{$reportNumber} returned to Plenary.",
                json_encode([
                    'report_id'             => $reportId,
                    'report_number'         => $reportNumber,
                    'document_id'           => $documentId,
                    'prev_status'           => $prevStatus,
                    'new_status'            => $returnedStatus['name'],
                    'returned_by'           => $userId,
                    'returned_by_username'  => $username,
                    'ip_address'            => client_ip(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            // 4. Route record — committee → plenary handoff
            $this->pdo->prepare("
                INSERT INTO document_routes
                    (document_id, from_phase, to_phase, routed_by, routed_to_role_id, remarks)
                VALUES (?, 'COMMITTEE', 'PLENARY', ?, ?, ?)
            ")->execute([
                $documentId,
                $userId,
                $committeeRoleId,
                "Committee Report #{$reportNumber} returned to Plenary.",
            ]);

            $this->pdo->commit();

            // ── Post-commit logging ───────────────────────────────────────────
            audit_log(
                'UPDATE',
                'CommitteeReport',
                (string) $reportId,
                ['status' => $prevStatus, 'returned_to_plenary_at' => null],
                [
                    'report_id'     => $reportId,
                    'report_number' => $reportNumber,
                    'document_id'   => $documentId,
                    'prev_status'   => $prevStatus,
                    'new_status'    => $returnedStatus['name'],
                    'returned_by'   => $userId,
                ],
                "Committee Report #{$reportNumber} returned to Plenary by {$username}."
            );
            system_log('INFO', 'Committee report returned to Plenary', [
                'report_id'     => $reportId,
                'report_number' => $reportNumber,
                'document_id'   => $documentId,
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
            flash_set('error', 'A database error occurred. Please try again.');
            redirect('committee/reports');
        }

        flash_set('success', "Committee Report #{$reportNumber} has been returned to Plenary.");
        redirect('committee/reports');
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
            $where   .= ' AND ds.name = ?';
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
     * Resolve the Committee role ID.
     * Throws RuntimeException if the role row is missing.
     */
    private function requireCommitteeRoleId(): int
    {
        $stmt = $this->pdo->query(
            "SELECT id FROM roles WHERE name = 'Committee' AND is_active = 1 AND is_deleted = 0 LIMIT 1"
        );
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('Committee role not found.');
        }
        return (int) $row['id'];
    }
}

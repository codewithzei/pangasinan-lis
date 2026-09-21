<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/DocumentService.php';

/**
 * CommitteeHearingController
 *
 * Manages the Committee Hearing workflow — the step that follows a successfully
 * scheduled Agenda.
 *
 * ROUTE MAP
 * ─────────────────────────────────────────────────────────────────────────────
 *  GET  committee/hearing                  index()       — tabbed hearing list
 *  GET  committee/hearing/show             show()        — detail + outcome form
 *  POST committee/hearing/store            store()       — save hearing outcome
 *  GET  committee/hearing/report           reportShow()  — committee report form
 *  POST committee/hearing/report           reportStore() — save committee report
 *  GET  committee/hearing/report/show      reportDetail()— view a saved report
 *
 * INDEX TABS
 *   ?tab=all        All hearings visible to this committee user (default)
 *   ?tab=scheduled  Documents with status "On Going" (awaiting outcome)
 *   ?tab=approved   Documents whose hearing outcome is APPROVED
 *   ?tab=deferred   Documents whose hearing outcome is DEFERRED
 *   ?tab=remanded   Documents whose hearing outcome is REMANDED
 *   ?tab=withdrawn  Documents whose hearing outcome is WITHDRAWN
 *
 * AUTHORIZATION
 *   RoleMiddleware already restricts every committee/* route to
 *   ['Super Admin', 'Committee'].  Each action additionally re-checks
 *   auth_id() so an expired session is never processed.
 *
 * TRANSACTION PATTERN
 *   - File staging before beginTransaction() (filesystem I/O outside lock window).
 *   - All DB mutations inside a single transaction.
 *   - audit_log() / system_log() called AFTER commit() (separate connection).
 *   - On Throwable: staged files are deleted, transaction rolled back.
 *
 * HEARING OUTCOMES
 *   APPROVED   → status: "Hearing Completed" → redirect to committee report form
 *   DEFERRED   → status: "Deferred"          → redirect to hearing list (deferred tab)
 *   REMANDED   → status: "Remanded"          → redirect to hearing list (remanded tab)
 *   WITHDRAWN  → status: "Withdrawn"         → redirect to hearing list (withdrawn tab)
 *
 * COMMITTEE REPORT
 *   - report_type auto-derived: 1 committee = COMMITTEE_REPORT, 2+ = JOINT_COMMITTEE_REPORT
 *   - report_number must be unique (UNIQUE index added in migration 055).
 *   - Attachments: drag-and-drop, multiple files, stored via DocumentService.
 *   - After saving: document status → "Committee Report Created".
 */
class CommitteeHearingController
{
    protected PDO             $pdo;
    protected DocumentService $docService;

    // Valid tab keys → display labels
    private const TABS = [
        'all'       => 'All Hearings',
        'scheduled' => 'Scheduled',
        'approved'  => 'Approved',
        'deferred'  => 'Deferred',
        'remanded'  => 'Remanded',
        'withdrawn' => 'Withdrawn',
    ];

    public function __construct()
    {
        $database         = new Database();
        $this->pdo        = $database->connect();
        $this->docService = new DocumentService();
    }

    // =========================================================================
    // 1. Index — tabbed hearing list
    // =========================================================================

    public function index(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        $tab     = $_GET['tab']    ?? 'all';
        $search  = trim($_GET['search'] ?? '');
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        if (!array_key_exists($tab, self::TABS)) {
            $tab = 'all';
        }

        // ── Status IDs ────────────────────────────────────────────────────────
        $onGoingId             = $this->resolveStatusId('On Going');
        $hearingCompletedId    = $this->resolveStatusId('Hearing Completed');
        $deferredId            = $this->resolveStatusId('Deferred');
        $remandedId            = $this->resolveStatusId('Remanded');
        $withdrawnId           = $this->resolveStatusId('Withdrawn');
        $committeeReportCreatedId = $this->resolveStatusId('Committee Report Created');

        // ── Tab counters ─────────────────────────────────────────────────────
        $counts = $this->countTabs(
            $onGoingId,
            $hearingCompletedId,
            $committeeReportCreatedId,
            $deferredId,
            $remandedId,
            $withdrawnId
        );

        // ── Search clause ─────────────────────────────────────────────────────
        [$searchWhere, $searchParams] = $this->buildSearchClause($search);

        // ── Main query ────────────────────────────────────────────────────────
        [$documents, $total] = $this->queryTab(
            $tab,
            $onGoingId,
            $hearingCompletedId,
            $committeeReportCreatedId,
            $deferredId,
            $remandedId,
            $withdrawnId,
            $searchWhere,
            $searchParams,
            $perPage,
            $offset
        );

        $totalPages = max(1, (int) ceil($total / $perPage));

        $success = flash_get('success');
        $error   = flash_get('error');

        $pageTitle = 'Committee Hearings';
        require __DIR__ . '/../../../resources/views/committee/hearing/index.php';
    }

    // =========================================================================
    // 2. Show — document detail + hearing outcome form
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
            redirect('committee/hearing');
        }

        // Load document
        $docStmt = $this->pdo->prepare("
            SELECT
                d.*,
                dt.name         AS document_type_name,
                dt.badge_color  AS document_type_badge_color,
                ds.name         AS status,
                ds.badge_color  AS status_badge_color,
                st.name         AS source_type,
                eo.name         AS external_office_name,
                h.name          AS hospital_name,
                mu.name         AS municipality_name
            FROM documents d
            LEFT JOIN document_types    dt ON dt.id = d.document_type_id
            LEFT JOIN document_statuses ds ON ds.id = d.current_status_id
            LEFT JOIN source_types      st ON st.id = d.source_type_id
            LEFT JOIN external_offices  eo ON eo.id = d.external_office_id
            LEFT JOIN hospitals          h ON  h.id = d.hospital_id
            LEFT JOIN municities        mu ON mu.id = d.municipality_id
            WHERE d.id = ?
            LIMIT 1
        ");
        $docStmt->execute([$documentId]);
        $document = $docStmt->fetch();

        if (!$document) {
            flash_set('error', 'Document not found.');
            redirect('committee/hearing');
        }

        // Must be in an actionable hearing status
        $onGoingId = $this->resolveStatusId('On Going');
        $isOnGoing = ((int) $document['current_status_id'] === $onGoingId);

        // Latest agenda for this document
        $agendaStmt = $this->pdo->prepare("
            SELECT ag.*
            FROM agendas ag
            WHERE ag.document_id = ?
            ORDER BY ag.id DESC
            LIMIT 1
        ");
        $agendaStmt->execute([$documentId]);
        $agenda = $agendaStmt->fetch();

        if (!$agenda) {
            flash_set('error', 'No agenda found for this document.');
            redirect('committee/hearing');
        }

        // Committees for this agenda
        $commStmt = $this->pdo->prepare("
            SELECT c.id, c.name
            FROM agenda_committees ac
            INNER JOIN committees c ON c.id = ac.committee_id
            WHERE ac.agenda_id = ?
            ORDER BY c.name ASC
        ");
        $commStmt->execute([$agenda['id']]);
        $agendaCommittees = $commStmt->fetchAll();

        // Chairpersons for this agenda
        $chairStmt = $this->pdo->prepare("
            SELECT sp.sp_member_id, sp.first_name, sp.last_name, sp.middle_name, sp.suffix
            FROM agenda_chairpersons ap
            INNER JOIN sp_members sp ON sp.sp_member_id = ap.sp_member_id
            WHERE ap.agenda_id = ?
            ORDER BY sp.last_name ASC, sp.first_name ASC
        ");
        $chairStmt->execute([$agenda['id']]);
        $agendaChairpersons = $chairStmt->fetchAll();

        // Attachments
        $attStmt = $this->pdo->prepare("
            SELECT da.*, ua.username AS uploaded_by_username
            FROM document_attachments da
            LEFT JOIN user_accounts ua ON ua.id = da.uploaded_by
            WHERE da.document_id = ?
            ORDER BY da.created_at ASC
        ");
        $attStmt->execute([$documentId]);
        $attachments = $attStmt->fetchAll();

        // Workflow events
        $evtStmt = $this->pdo->prepare("
            SELECT de.*, ua.username AS performed_by_username,
                   fs.name AS from_status_name, ts.name AS to_status_name
            FROM document_events de
            LEFT JOIN user_accounts  ua ON ua.id = de.performed_by
            LEFT JOIN document_statuses fs ON fs.id = de.from_status_id
            LEFT JOIN document_statuses ts ON ts.id = de.to_status_id
            WHERE de.document_id = ?
            ORDER BY de.created_at ASC
        ");
        $evtStmt->execute([$documentId]);
        $events = $evtStmt->fetchAll();

        // Existing hearing record (prevents re-submit if already recorded)
        $hearingStmt = $this->pdo->prepare("
            SELECT ch.*, ua.username AS performed_by_username
            FROM committee_hearings ch
            LEFT JOIN user_accounts ua ON ua.id = ch.performed_by
            WHERE ch.document_id = ? AND ch.agenda_id = ?
            ORDER BY ch.id DESC
            LIMIT 1
        ");
        $hearingStmt->execute([$documentId, $agenda['id']]);
        $existingHearing = $hearingStmt->fetch();

        // Existing committee report for APPROVED hearings
        $existingReport = null;
        if ($existingHearing && $existingHearing['outcome'] === 'APPROVED') {
            $rptStmt = $this->pdo->prepare(
                "SELECT id, report_number, report_type FROM committee_reports WHERE hearing_id = ? LIMIT 1"
            );
            $rptStmt->execute([$existingHearing['id']]);
            $existingReport = $rptStmt->fetch() ?: null;
        }

        $success = flash_get('success');
        $error   = flash_get('error');
        $errors  = flash_get('errors') ?? [];

        $pageTitle = 'Committee Hearing — ' . htmlspecialchars($document['tracking_number']);
        require __DIR__ . '/../../../resources/views/committee/hearing/show.php';
    }

    // =========================================================================
    // 3. Store — save hearing outcome
    // =========================================================================

    public function store(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        $documentId = (int) ($_POST['document_id'] ?? 0);
        $agendaId   = (int) ($_POST['agenda_id']   ?? 0);
        $outcome    = trim($_POST['outcome']        ?? '');
        $remarks    = trim($_POST['remarks']        ?? '');

        if ($documentId <= 0 || $agendaId <= 0) {
            flash_set('error', 'Invalid request.');
            redirect('committee/hearing');
        }

        // ── Validate outcome ──────────────────────────────────────────────────
        $validOutcomes = ['APPROVED', 'DEFERRED', 'REMANDED', 'WITHDRAWN'];
        if (!in_array($outcome, $validOutcomes, true)) {
            flash_set('errors', ['Please select a valid hearing outcome.']);
            flash_set('error', 'Please select a hearing outcome before submitting.');
            redirect('committee/hearing/show?id=' . $documentId);
        }

        // ── Verify document is still "On Going" ───────────────────────────────
        $onGoingId = $this->resolveStatusId('On Going');

        $docStmt = $this->pdo->prepare(
            "SELECT id, current_status_id, tracking_number FROM documents WHERE id = ? LIMIT 1"
        );
        $docStmt->execute([$documentId]);
        $document = $docStmt->fetch();

        if (!$document) {
            flash_set('error', 'Document not found.');
            redirect('committee/hearing');
        }
        if ((int) $document['current_status_id'] !== $onGoingId) {
            flash_set('error', 'This document is no longer in the On Going stage and cannot be processed again.');
            redirect('committee/hearing');
        }

        // ── Guard: no duplicate hearing record for same document+agenda ───────
        $dupStmt = $this->pdo->prepare(
            "SELECT id FROM committee_hearings WHERE document_id = ? AND agenda_id = ? LIMIT 1"
        );
        $dupStmt->execute([$documentId, $agendaId]);
        if ($dupStmt->fetch()) {
            flash_set('error', 'A hearing outcome has already been recorded for this agenda.');
            redirect('committee/hearing/show?id=' . $documentId);
        }

        // ── Determine target document status ──────────────────────────────────
        $statusMap = [
            'APPROVED'  => 'Hearing Completed',
            'DEFERRED'  => 'Deferred',
            'REMANDED'  => 'Remanded',
            'WITHDRAWN' => 'Withdrawn',
        ];
        $targetStatusName = $statusMap[$outcome];
        $targetStatus     = $this->requireDocumentStatus($targetStatusName);

        // ── Determine event_type ──────────────────────────────────────────────
        $eventTypeMap = [
            'APPROVED'  => 'HEARING_COMPLETED',
            'DEFERRED'  => 'HEARING_DEFERRED',
            'REMANDED'  => 'HEARING_REMANDED',
            'WITHDRAWN' => 'HEARING_WITHDRAWN',
        ];
        $eventType = $eventTypeMap[$outcome];

        // ── Transaction ───────────────────────────────────────────────────────
        $auditData = null;
        $hearingId = null;

        try {
            $this->pdo->beginTransaction();

            // 1. Username for metadata
            $uStmt = $this->pdo->prepare("SELECT username FROM user_accounts WHERE id = ? LIMIT 1");
            $uStmt->execute([$userId]);
            $username = (string) ($uStmt->fetchColumn() ?: '');

            // 2. Insert committee_hearings row
            $this->pdo->prepare("
                INSERT INTO committee_hearings
                    (document_id, agenda_id, outcome, remarks, performed_by, performed_at, created_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ")->execute([$documentId, $agendaId, $outcome, $remarks ?: null, $userId]);
            $hearingId = (int) $this->pdo->lastInsertId();

            // 3. Update document status
            $this->pdo->prepare(
                "UPDATE documents SET current_status_id = ?, updated_by = ? WHERE id = ?"
            )->execute([$targetStatus['id'], $userId, $documentId]);

            // 4. Workflow event
            $this->pdo->prepare("
                INSERT INTO document_events
                    (document_id, event_type, phase, performed_by,
                     from_status_id, to_status_id, remarks, metadata)
                VALUES (?, ?, 'COMMITTEE', ?, ?, ?, ?, ?)
            ")->execute([
                $documentId,
                $eventType,
                $userId,
                $document['current_status_id'],
                $targetStatus['id'],
                $remarks ?: "Hearing outcome: {$outcome}.",
                json_encode([
                    'hearing_id'            => $hearingId,
                    'agenda_id'             => $agendaId,
                    'outcome'               => $outcome,
                    'performed_by'          => $userId,
                    'performed_by_username' => $username,
                    'ip_address'            => client_ip(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            // 5. Route record
            $committeeRoleId = $this->requireCommitteeRoleId();
            $this->pdo->prepare("
                INSERT INTO document_routes
                    (document_id, from_phase, to_phase, routed_by, routed_to_role_id, remarks)
                VALUES (?, 'COMMITTEE', 'COMMITTEE', ?, ?, ?)
            ")->execute([
                $documentId,
                $userId,
                $committeeRoleId,
                "Hearing outcome: {$outcome}" . ($remarks ? " — {$remarks}" : ''),
            ]);

            $this->pdo->commit();

            $auditData = [
                'action'        => 'committee_hearing_outcome',
                'hearing_id'    => $hearingId,
                'document_id'   => $documentId,
                'agenda_id'     => $agendaId,
                'outcome'       => $outcome,
                'new_status'    => $targetStatusName,
                'new_status_id' => $targetStatus['id'],
                'performed_by'  => $userId,
            ];
            audit_log('UPDATE', 'Document', (string) $documentId, null, $auditData,
                "Hearing outcome '{$outcome}' recorded for document ID {$documentId} by {$username}");
            system_log('INFO', 'Committee hearing outcome saved', $auditData);

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            system_log('ERROR', 'CommitteeHearingController::store exception', [
                'error'       => $e->getMessage(),
                'document_id' => $documentId,
                'outcome'     => $outcome,
            ]);
            flash_set('error', 'A database error occurred while saving the hearing outcome. Please try again.');
            redirect('committee/hearing/show?id=' . $documentId);
        }

        old_clear();

        // APPROVED → go directly to committee report form
        if ($outcome === 'APPROVED') {
            flash_set('success', 'Hearing outcome recorded: Approved. Please complete the Committee Report.');
            redirect('committee/hearing/report?document_id=' . $documentId . '&hearing_id=' . $hearingId);
        }

        // Tab map for redirect
        $tabMap = [
            'DEFERRED'  => 'deferred',
            'REMANDED'  => 'remanded',
            'WITHDRAWN' => 'withdrawn',
        ];
        $redirectTab = $tabMap[$outcome] ?? 'all';

        flash_set('success', "Hearing outcome recorded: {$outcome}.");
        redirect('committee/hearing?tab=' . $redirectTab);
    }

    // =========================================================================
    // 4. Report Show — committee report form
    // =========================================================================

    public function reportShow(): void
    {
        $userId     = auth_id();
        $documentId = (int) ($_GET['document_id'] ?? 0);
        $hearingId  = (int) ($_GET['hearing_id']  ?? 0);

        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }
        if ($documentId <= 0 || $hearingId <= 0) {
            flash_set('error', 'Invalid request.');
            redirect('committee/hearing');
        }

        // Load hearing record (must be APPROVED)
        $hearingStmt = $this->pdo->prepare("
            SELECT ch.*, ag.id AS agenda_id, ag.agenda_date, ag.agenda_time, ag.venue
            FROM committee_hearings ch
            INNER JOIN agendas ag ON ag.id = ch.agenda_id
            WHERE ch.id = ? AND ch.document_id = ? AND ch.outcome = 'APPROVED'
            LIMIT 1
        ");
        $hearingStmt->execute([$hearingId, $documentId]);
        $hearing = $hearingStmt->fetch();

        if (!$hearing) {
            flash_set('error', 'Hearing record not found or outcome is not Approved.');
            redirect('committee/hearing');
        }

        // Guard: no existing report for this hearing
        $existingReport = $this->pdo->prepare(
            "SELECT id FROM committee_reports WHERE hearing_id = ? LIMIT 1"
        );
        $existingReport->execute([$hearingId]);
        if ($existingReport->fetch()) {
            flash_set('error', 'A Committee Report has already been created for this hearing.');
            redirect('committee/hearing?tab=approved');
        }

        // Load document
        $docStmt = $this->pdo->prepare("
            SELECT d.*, dt.name AS document_type_name, ds.name AS status
            FROM documents d
            LEFT JOIN document_types    dt ON dt.id = d.document_type_id
            LEFT JOIN document_statuses ds ON ds.id = d.current_status_id
            WHERE d.id = ?
            LIMIT 1
        ");
        $docStmt->execute([$documentId]);
        $document = $docStmt->fetch();

        if (!$document) {
            flash_set('error', 'Document not found.');
            redirect('committee/hearing');
        }

        // Committees from the agenda (preselect for report)
        $commStmt = $this->pdo->prepare("
            SELECT c.id, c.name
            FROM agenda_committees ac
            INNER JOIN committees c ON c.id = ac.committee_id
            WHERE ac.agenda_id = ?
            ORDER BY c.name ASC
        ");
        $commStmt->execute([$hearing['agenda_id']]);
        $agendaCommittees = $commStmt->fetchAll();

        // Report type is auto-derived from committee count
        $reportType = count($agendaCommittees) > 1
            ? 'JOINT_COMMITTEE_REPORT'
            : 'COMMITTEE_REPORT';

        $success = flash_get('success');
        $error   = flash_get('error');
        $errors  = flash_get('errors') ?? [];

        $pageTitle = 'Create Committee Report';
        require __DIR__ . '/../../../resources/views/committee/hearing/report.php';
    }

    // =========================================================================
    // 5. Report Store — save committee report + attachments
    // =========================================================================

    public function reportStore(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        $documentId        = (int) ($_POST['document_id']       ?? 0);
        $hearingId         = (int) ($_POST['hearing_id']        ?? 0);
        $agendaId          = (int) ($_POST['agenda_id']         ?? 0);
        $reportNumber      = trim($_POST['report_number']       ?? '');
        $summaryOfFindings = trim($_POST['summary_of_findings'] ?? '');
        $committeeIds      = array_filter(array_map('intval', (array) ($_POST['committee_ids'] ?? [])));

        if ($documentId <= 0 || $hearingId <= 0 || $agendaId <= 0) {
            flash_set('error', 'Invalid request.');
            redirect('committee/hearing');
        }

        // ── Validation ────────────────────────────────────────────────────────
        $errors = [];

        if ($reportNumber === '') {
            $errors[] = 'Committee Report Number is required.';
        } elseif (mb_strlen($reportNumber) > 50) {
            $errors[] = 'Committee Report Number must not exceed 50 characters.';
        } else {
            $dupNum = $this->pdo->prepare(
                "SELECT id FROM committee_reports WHERE report_number = ? LIMIT 1"
            );
            $dupNum->execute([$reportNumber]);
            if ($dupNum->fetch()) {
                $errors[] = "Committee Report Number \"{$reportNumber}\" already exists. Please use a unique number.";
            }
        }

        if ($summaryOfFindings === '') {
            $errors[] = 'Summary of Findings is required.';
        } elseif (mb_strlen($summaryOfFindings) > 10000) {
            $errors[] = 'Summary of Findings must not exceed 10,000 characters.';
        }

        if (empty($committeeIds)) {
            $errors[] = 'At least one committee in charge must be selected.';
        }

        // Validate file uploads (optional)
        $uploadedFiles = $_FILES['attachments'] ?? [];
        $hasFiles      = !empty($uploadedFiles['name'][0]);
        $stagedFiles   = [];

        if ($hasFiles) {
            $fileErrors = $this->docService->validateFileUploads($uploadedFiles, false);
            if (!empty($fileErrors)) {
                $errors = array_merge($errors, $fileErrors);
            }
        }

        if (!empty($errors)) {
            flash_set('errors', $errors);
            flash_set('error', 'Please correct the errors below.');
            old_set([
                'report_number'       => $reportNumber,
                'summary_of_findings' => $summaryOfFindings,
                'committee_ids'       => $_POST['committee_ids'] ?? [],
            ]);
            redirect('committee/hearing/report?document_id=' . $documentId . '&hearing_id=' . $hearingId);
        }

        // ── Guard: verify hearing APPROVED + no existing report ───────────────
        $hearingStmt = $this->pdo->prepare("
            SELECT ch.*, ag.id AS linked_agenda_id
            FROM committee_hearings ch
            INNER JOIN agendas ag ON ag.id = ch.agenda_id
            WHERE ch.id = ? AND ch.document_id = ? AND ch.outcome = 'APPROVED'
            LIMIT 1
        ");
        $hearingStmt->execute([$hearingId, $documentId]);
        $hearing = $hearingStmt->fetch();

        if (!$hearing) {
            flash_set('error', 'Hearing record not found or outcome is not Approved.');
            redirect('committee/hearing');
        }

        $dupReport = $this->pdo->prepare(
            "SELECT id FROM committee_reports WHERE hearing_id = ? LIMIT 1"
        );
        $dupReport->execute([$hearingId]);
        if ($dupReport->fetch()) {
            flash_set('error', 'A Committee Report has already been created for this hearing.');
            redirect('committee/hearing?tab=approved');
        }

        // ── Validate committee IDs ────────────────────────────────────────────
        $cPh  = implode(',', array_fill(0, count($committeeIds), '?'));
        $cChk = $this->pdo->prepare(
            "SELECT id FROM committees WHERE id IN ({$cPh}) AND is_active = 1 AND is_deleted = 0"
        );
        $cChk->execute($committeeIds);
        $validCIds = array_column($cChk->fetchAll(), 'id');
        if (count($validCIds) !== count($committeeIds)) {
            flash_set('error', 'One or more selected committees are invalid.');
            redirect('committee/hearing/report?document_id=' . $documentId . '&hearing_id=' . $hearingId);
        }

        // ── Derive report type ────────────────────────────────────────────────
        $reportType = count($validCIds) > 1
            ? 'JOINT_COMMITTEE_REPORT'
            : 'COMMITTEE_REPORT';

        // ── Stage files BEFORE transaction ────────────────────────────────────
        if ($hasFiles) {
            try {
                $stagedFiles = $this->docService->stageFileUploads($uploadedFiles, $documentId);
            } catch (Throwable $e) {
                flash_set('error', 'File upload failed: ' . $e->getMessage());
                redirect('committee/hearing/report?document_id=' . $documentId . '&hearing_id=' . $hearingId);
            }
        }

        // ── Look up "Committee Report Created" status ─────────────────────────
        $reportStatus = $this->requireDocumentStatus('Committee Report Created');

        // ── Transaction ───────────────────────────────────────────────────────
        $pendingFileLogs = [];
        $reportId        = null;

        try {
            $this->pdo->beginTransaction();

            // 1. Username
            $uStmt = $this->pdo->prepare("SELECT username FROM user_accounts WHERE id = ? LIMIT 1");
            $uStmt->execute([$userId]);
            $username = (string) ($uStmt->fetchColumn() ?: '');

            // 2. Insert committee_reports
            $this->pdo->prepare("
                INSERT INTO committee_reports
                    (report_type, report_number, summary_of_findings,
                     agenda_id, hearing_id, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ")->execute([
                $reportType,
                $reportNumber,
                $summaryOfFindings,
                $agendaId,
                $hearingId,
                $userId,
            ]);
            $reportId = (int) $this->pdo->lastInsertId();

            // 3. Link document
            $this->pdo->prepare("
                INSERT IGNORE INTO committee_report_documents (committee_report_id, document_id)
                VALUES (?, ?)
            ")->execute([$reportId, $documentId]);

            // 4. Link committees
            $insRc = $this->pdo->prepare("
                INSERT IGNORE INTO committee_report_committees (committee_report_id, committee_id)
                VALUES (?, ?)
            ");
            foreach ($validCIds as $cId) {
                $insRc->execute([$reportId, $cId]);
            }

            // 5. Attachments
            if (!empty($stagedFiles)) {
                $pendingFileLogs = $this->docService->insertStagedAttachments(
                    $stagedFiles,
                    $documentId,
                    $userId,
                    'COMMITTEE',
                    'COMMITTEE_REPORT'
                );
            }

            // 6. Update document status → "Committee Report Created"
            $this->pdo->prepare(
                "UPDATE documents SET current_status_id = ?, updated_by = ? WHERE id = ?"
            )->execute([$reportStatus['id'], $userId, $documentId]);

            // 7. Workflow event (from Hearing Completed → Committee Report Created)
            $hearingCompletedStatus = $this->requireDocumentStatus('Hearing Completed');
            $this->pdo->prepare("
                INSERT INTO document_events
                    (document_id, event_type, phase, performed_by,
                     from_status_id, to_status_id, remarks, metadata)
                VALUES (?, 'COMMITTEE_REPORT_CREATED', 'COMMITTEE', ?, ?, ?, ?, ?)
            ")->execute([
                $documentId,
                $userId,
                $hearingCompletedStatus['id'],
                $reportStatus['id'],
                "Committee Report #{$reportNumber} created.",
                json_encode([
                    'report_id'             => $reportId,
                    'report_type'           => $reportType,
                    'report_number'         => $reportNumber,
                    'hearing_id'            => $hearingId,
                    'agenda_id'             => $agendaId,
                    'committee_ids'         => $validCIds,
                    'created_by'            => $userId,
                    'created_by_username'   => $username,
                    'ip_address'            => client_ip(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            // 8. Route record
            $committeeRoleId = $this->requireCommitteeRoleId();
            $this->pdo->prepare("
                INSERT INTO document_routes
                    (document_id, from_phase, to_phase, routed_by, routed_to_role_id, remarks)
                VALUES (?, 'COMMITTEE', 'COMMITTEE', ?, ?, ?)
            ")->execute([
                $documentId,
                $userId,
                $committeeRoleId,
                "Committee Report #{$reportNumber} created ({$reportType}).",
            ]);

            $this->pdo->commit();

            if (!empty($pendingFileLogs)) {
                $this->docService->flushFileUploadLogs($pendingFileLogs);
            }

            $auditData = [
                'action'        => 'committee_report_created',
                'report_id'     => $reportId,
                'report_type'   => $reportType,
                'report_number' => $reportNumber,
                'document_id'   => $documentId,
                'hearing_id'    => $hearingId,
                'agenda_id'     => $agendaId,
                'committee_ids' => $validCIds,
                'created_by'    => $userId,
            ];
            audit_log('CREATE', 'CommitteeReport', (string) $reportId, null, $auditData,
                "Committee Report #{$reportNumber} ({$reportType}) created for document ID {$documentId} by {$username}");
            system_log('INFO', 'Committee report created', $auditData);

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            foreach ($stagedFiles as $s) {
                if (file_exists($s['abs_path'])) {
                    @unlink($s['abs_path']);
                }
            }
            system_log('ERROR', 'CommitteeHearingController::reportStore exception', [
                'error'       => $e->getMessage(),
                'document_id' => $documentId,
                'hearing_id'  => $hearingId,
            ]);
            flash_set('error', 'A database error occurred while saving the Committee Report. Please try again.');
            redirect('committee/hearing/report?document_id=' . $documentId . '&hearing_id=' . $hearingId);
        }

        old_clear();
        flash_set('success', "Committee Report #{$reportNumber} created successfully.");
        redirect('committee/reports');
    }

    // =========================================================================
    // 6. Report Detail — view a saved committee report
    // =========================================================================

    public function reportDetail(): void
    {
        $userId   = auth_id();
        $reportId = (int) ($_GET['id'] ?? 0);

        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }
        if ($reportId <= 0) {
            flash_set('error', 'Invalid report ID.');
            redirect('committee/hearing');
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
            redirect('committee/hearing');
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

        $success = flash_get('success');
        $error   = flash_get('error');

        $pageTitle = 'Committee Report #' . htmlspecialchars($report['report_number'] ?? $reportId);
        require __DIR__ . '/../../../resources/views/committee/hearing/report-show.php';
    }

    // =========================================================================
    // Private — tab queries
    // =========================================================================

    /**
     * Build the WHERE + params for a full-text search across tracking_number
     * and subject_matter.
     *
     * @return array{0: string, 1: array}
     */
    private function buildSearchClause(string $search): array
    {
        if ($search === '') {
            return ['', []];
        }
        return [
            ' AND (d.tracking_number LIKE ? OR d.subject_matter LIKE ?)',
            ["%{$search}%", "%{$search}%"],
        ];
    }

    /**
     * Fetch per-tab counts for the counter badges.
     * Each count is an integer; missing status IDs produce 0.
     *
     * @return array{all:int, scheduled:int, approved:int, deferred:int, remanded:int, withdrawn:int}
     */
    private function countTabs(
        int $onGoingId,
        int $hearingCompletedId,
        int $committeeReportCreatedId,
        int $deferredId,
        int $remandedId,
        int $withdrawnId
    ): array {
        // "all" = every document that has at least one agenda entry
        $allStmt = $this->pdo->query(
            "SELECT COUNT(DISTINCT d.id) FROM documents d
              INNER JOIN agendas ag ON ag.document_id = d.id"
        );
        $all = (int) $allStmt->fetchColumn();

        // "scheduled" = On Going with an agenda, no completed hearing yet
        $schStmt = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT d.id) FROM documents d
              INNER JOIN agendas ag ON ag.document_id = d.id
              LEFT  JOIN committee_hearings ch ON ch.document_id = d.id
             WHERE d.current_status_id = ? AND ch.id IS NULL"
        );
        $schStmt->execute([$onGoingId]);
        $scheduled = (int) $schStmt->fetchColumn();

        // "approved"  = outcome APPROVED in committee_hearings
        $appStmt = $this->pdo->query(
            "SELECT COUNT(DISTINCT document_id) FROM committee_hearings WHERE outcome = 'APPROVED'"
        );
        $approved = (int) $appStmt->fetchColumn();

        // "deferred" = outcome DEFERRED
        $defStmt = $this->pdo->query(
            "SELECT COUNT(DISTINCT document_id) FROM committee_hearings WHERE outcome = 'DEFERRED'"
        );
        $deferred = (int) $defStmt->fetchColumn();

        // "remanded" = outcome REMANDED
        $remStmt = $this->pdo->query(
            "SELECT COUNT(DISTINCT document_id) FROM committee_hearings WHERE outcome = 'REMANDED'"
        );
        $remanded = (int) $remStmt->fetchColumn();

        // "withdrawn" = outcome WITHDRAWN
        $witStmt = $this->pdo->query(
            "SELECT COUNT(DISTINCT document_id) FROM committee_hearings WHERE outcome = 'WITHDRAWN'"
        );
        $withdrawn = (int) $witStmt->fetchColumn();

        return compact('all', 'scheduled', 'approved', 'deferred', 'remanded', 'withdrawn');
    }

    /**
     * Execute the appropriate query for the selected tab.
     *
     * @return array{0: array, 1: int}  [$rows, $total]
     */
    private function queryTab(
        string $tab,
        int $onGoingId,
        int $hearingCompletedId,
        int $committeeReportCreatedId,
        int $deferredId,
        int $remandedId,
        int $withdrawnId,
        string $searchWhere,
        array  $searchParams,
        int    $perPage,
        int    $offset
    ): array {
        return match ($tab) {
            'scheduled' => $this->queryScheduledTab(
                $onGoingId, $searchWhere, $searchParams, $perPage, $offset
            ),
            'approved'  => $this->queryOutcomeTab(
                'APPROVED', $searchWhere, $searchParams, $perPage, $offset
            ),
            'deferred'  => $this->queryOutcomeTab(
                'DEFERRED', $searchWhere, $searchParams, $perPage, $offset
            ),
            'remanded'  => $this->queryOutcomeTab(
                'REMANDED', $searchWhere, $searchParams, $perPage, $offset
            ),
            'withdrawn' => $this->queryOutcomeTab(
                'WITHDRAWN', $searchWhere, $searchParams, $perPage, $offset
            ),
            default     => $this->queryAllTab(
                $searchWhere, $searchParams, $perPage, $offset
            ),
        };
    }

    /**
     * "All" tab: every document that has at least one agenda row.
     * No duplicate rows because we GROUP BY document — GROUP_CONCAT aggregates
     * committee names.
     */
    private function queryAllTab(
        string $searchWhere, array $searchParams, int $perPage, int $offset
    ): array {
        $countStmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT d.id)
            FROM documents d
            INNER JOIN agendas ag ON ag.document_id = d.id
            WHERE 1=1 {$searchWhere}
        ");
        $countStmt->execute($searchParams);
        $total = (int) $countStmt->fetchColumn();

        $listStmt = $this->pdo->prepare("
            SELECT
                d.id                            AS document_id,
                d.tracking_number,
                d.subject_matter,
                dt.name                         AS document_type_name,
                dt.badge_color                  AS document_type_badge_color,
                ds.name                         AS status,
                ds.badge_color                  AS status_badge_color,
                ag.id                           AS agenda_id,
                ag.agenda_date,
                ag.agenda_time,
                ag.venue,
                GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ')
                                                AS committee_names,
                ch.id                           AS hearing_id,
                ch.outcome                      AS hearing_outcome,
                ch.performed_at                 AS outcome_date,
                ua_ch.username                  AS outcome_by,
                cr.id                           AS report_id,
                cr.report_number,
                cr.report_type
            FROM documents d
            INNER JOIN agendas ag
                ON ag.document_id = d.id
                AND ag.id = (SELECT MAX(a2.id) FROM agendas a2 WHERE a2.document_id = d.id)
            LEFT  JOIN agenda_committees ac   ON ac.agenda_id   = ag.id
            LEFT  JOIN committees         c   ON  c.id          = ac.committee_id
            LEFT  JOIN document_types    dt   ON dt.id          = d.document_type_id
            LEFT  JOIN document_statuses ds   ON ds.id          = d.current_status_id
            LEFT  JOIN committee_hearings ch  ON ch.document_id = d.id
                AND ch.id = (SELECT MAX(ch2.id) FROM committee_hearings ch2 WHERE ch2.document_id = d.id)
            LEFT  JOIN user_accounts ua_ch    ON ua_ch.id       = ch.performed_by
            LEFT  JOIN committee_reports cr   ON cr.hearing_id  = ch.id
            WHERE 1=1 {$searchWhere}
            GROUP BY
                d.id, d.tracking_number, d.subject_matter,
                dt.name, dt.badge_color, ds.name, ds.badge_color,
                ag.id, ag.agenda_date, ag.agenda_time, ag.venue,
                ch.id, ch.outcome, ch.performed_at, ua_ch.username,
                cr.id, cr.report_number, cr.report_type
            ORDER BY ag.agenda_date DESC, d.id DESC
            LIMIT ? OFFSET ?
        ");
        $listStmt->execute([...$searchParams, $perPage, $offset]);
        return [$listStmt->fetchAll(), $total];
    }

    /**
     * "Scheduled" tab: documents On Going with an agenda but no hearing outcome yet.
     */
    private function queryScheduledTab(
        int $onGoingId, string $searchWhere, array $searchParams, int $perPage, int $offset
    ): array {
        $countStmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT d.id)
            FROM documents d
            INNER JOIN agendas ag ON ag.document_id = d.id
            LEFT  JOIN committee_hearings ch ON ch.document_id = d.id
            WHERE d.current_status_id = ? AND ch.id IS NULL
            {$searchWhere}
        ");
        $countStmt->execute([$onGoingId, ...$searchParams]);
        $total = (int) $countStmt->fetchColumn();

        $listStmt = $this->pdo->prepare("
            SELECT
                d.id                            AS document_id,
                d.tracking_number,
                d.subject_matter,
                dt.name                         AS document_type_name,
                dt.badge_color                  AS document_type_badge_color,
                ds.name                         AS status,
                ds.badge_color                  AS status_badge_color,
                ag.id                           AS agenda_id,
                ag.agenda_date,
                ag.agenda_time,
                ag.venue,
                GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ')
                                                AS committee_names,
                NULL                            AS hearing_outcome,
                NULL                            AS outcome_date,
                NULL                            AS outcome_by,
                NULL                            AS report_id,
                NULL                            AS report_number,
                NULL                            AS report_type
            FROM documents d
            INNER JOIN agendas ag
                ON ag.document_id = d.id
                AND ag.id = (SELECT MAX(a2.id) FROM agendas a2 WHERE a2.document_id = d.id)
            LEFT  JOIN agenda_committees ac ON ac.agenda_id = ag.id
            LEFT  JOIN committees         c ON  c.id        = ac.committee_id
            LEFT  JOIN document_types    dt ON dt.id        = d.document_type_id
            LEFT  JOIN document_statuses ds ON ds.id        = d.current_status_id
            LEFT  JOIN committee_hearings ch ON ch.document_id = d.id
            WHERE d.current_status_id = ? AND ch.id IS NULL
            {$searchWhere}
            GROUP BY
                d.id, d.tracking_number, d.subject_matter,
                dt.name, dt.badge_color, ds.name, ds.badge_color,
                ag.id, ag.agenda_date, ag.agenda_time, ag.venue
            ORDER BY ag.agenda_date ASC, ag.agenda_time ASC, d.id ASC
            LIMIT ? OFFSET ?
        ");
        $listStmt->execute([$onGoingId, ...$searchParams, $perPage, $offset]);
        return [$listStmt->fetchAll(), $total];
    }

    /**
     * Outcome tabs (Approved / Deferred / Remanded / Withdrawn):
     * Join committee_hearings on outcome = $outcome to avoid duplicates.
     * Uses DISTINCT on document_id with the latest hearing record per doc.
     */
    private function queryOutcomeTab(
        string $outcome, string $searchWhere, array $searchParams, int $perPage, int $offset
    ): array {
        $countStmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT d.id)
            FROM documents d
            INNER JOIN committee_hearings ch
                ON ch.document_id = d.id
                AND ch.outcome    = ?
                AND ch.id = (SELECT MAX(ch2.id) FROM committee_hearings ch2 WHERE ch2.document_id = d.id)
            INNER JOIN agendas ag ON ag.id = ch.agenda_id
            WHERE 1=1 {$searchWhere}
        ");
        $countStmt->execute([$outcome, ...$searchParams]);
        $total = (int) $countStmt->fetchColumn();

        $listStmt = $this->pdo->prepare("
            SELECT
                d.id                            AS document_id,
                d.tracking_number,
                d.subject_matter,
                dt.name                         AS document_type_name,
                dt.badge_color                  AS document_type_badge_color,
                ds.name                         AS status,
                ds.badge_color                  AS status_badge_color,
                ag.id                           AS agenda_id,
                ag.agenda_date,
                ag.agenda_time,
                ag.venue,
                GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ')
                                                AS committee_names,
                ch.id                           AS hearing_id,
                ch.outcome                      AS hearing_outcome,
                ch.performed_at                 AS outcome_date,
                ua_ch.username                  AS outcome_by,
                cr.id                           AS report_id,
                cr.report_number,
                cr.report_type
            FROM documents d
            INNER JOIN committee_hearings ch
                ON ch.document_id = d.id
                AND ch.outcome    = ?
                AND ch.id = (SELECT MAX(ch2.id) FROM committee_hearings ch2 WHERE ch2.document_id = d.id)
            INNER JOIN agendas ag
                ON ag.id = ch.agenda_id
            LEFT  JOIN agenda_committees ac   ON ac.agenda_id  = ag.id
            LEFT  JOIN committees         c   ON  c.id         = ac.committee_id
            LEFT  JOIN document_types    dt   ON dt.id         = d.document_type_id
            LEFT  JOIN document_statuses ds   ON ds.id         = d.current_status_id
            LEFT  JOIN user_accounts ua_ch    ON ua_ch.id      = ch.performed_by
            LEFT  JOIN committee_reports cr   ON cr.hearing_id = ch.id
            WHERE 1=1 {$searchWhere}
            GROUP BY
                d.id, d.tracking_number, d.subject_matter,
                dt.name, dt.badge_color, ds.name, ds.badge_color,
                ag.id, ag.agenda_date, ag.agenda_time, ag.venue,
                ch.id, ch.outcome, ch.performed_at, ua_ch.username,
                cr.id, cr.report_number, cr.report_type
            ORDER BY ch.performed_at DESC, d.id DESC
            LIMIT ? OFFSET ?
        ");
        $listStmt->execute([$outcome, ...$searchParams, $perPage, $offset]);
        return [$listStmt->fetchAll(), $total];
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Resolve a document_status row by name.
     * Throws RuntimeException if not found (signals missing migration/seed).
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
                "Document status '{$name}' not found. Run migration 055 to seed it."
            );
        }
        return $row;
    }

    /**
     * Return only the ID of a status (non-throwing; returns 0 on miss).
     * Used for WHERE clauses only — never for FK inserts.
     */
    private function resolveStatusId(string $name): int
    {
        try {
            return (int) $this->requireDocumentStatus($name)['id'];
        } catch (RuntimeException $e) {
            system_log('ERROR', "resolveStatusId failed for '{$name}'", ['error' => $e->getMessage()]);
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

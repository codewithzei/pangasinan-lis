<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/DocumentService.php';

/**
 * CommitteeCommunicationsController
 *
 * Handles the Communications sub-workflow for documents whose document type
 * is "Communication".
 *
 * ROUTE MAP
 * ─────────────────────────────────────────────────────────────────────────────
 *  GET  committee/communications/create?document_id={id}  create()       — form
 *  POST committee/communications/create                   store()        — persist
 *  GET  committee/communications/show?id={comm_id}        show()         — detail
 *  GET  committee/communications/agenda?id={comm_id}      agendaShow()   — agenda form
 *  POST committee/communications/agenda                   agendaStore()  — save agenda
 *  GET  committee/communications/hearing?id={comm_id}     hearingShow()  — hearing form
 *  POST committee/communications/hearing                  hearingStore() — save hearing
 *  GET  committee/communications/report?id={comm_id}      reportShow()   — report form
 *  POST committee/communications/report                   reportStore()  — save report
 *
 * AUTHORIZATION
 * ─────────────────────────────────────────────────────────────────────────────
 *  • All routes: AuthMiddleware + RoleMiddleware (Committee / Super Admin).
 *  • create() / store(): The user must have an ACCEPTED Committee assignment
 *    for the document. Checked via requireEligibleOwnedDocument().
 *  • All subsequent steps: verified via requireCommOwnership() — only the user
 *    who created the communication record (assigned_by) may progress it.
 *  • Server-side re-checks on every write. Client input never trusted.
 *
 * ELIGIBLE DOCUMENT TYPE
 * ─────────────────────────────────────────────────────────────────────────────
 *  Only documents with document_types.name = 'Communication' are eligible.
 *
 * WORKFLOW STEPS
 * ─────────────────────────────────────────────────────────────────────────────
 *  1. Create communication record   → status: "Communication In Progress"
 *  2. Schedule agenda               → reuses agendas / agenda_committees /
 *                                     agenda_chairpersons tables
 *                                     status: "On Going"
 *  3. Record hearing outcome        → reuses committee_hearings table
 *                                     status: "Hearing Completed" (if APPROVED)
 *  4. Create committee report       → reuses committee_reports /
 *                                     committee_report_documents /
 *                                     committee_report_committees tables
 *                                     status: "Committee Report Created"
 *  5. Return to Plenary             → handled by CommitteeReportsController
 *                                     (same as all other report types)
 *
 * DUPLICATE PREVENTION
 * ─────────────────────────────────────────────────────────────────────────────
 *  committee_communications.document_id has a UNIQUE constraint (migration 061)
 *  so the database will reject a second INSERT. The controller also checks in
 *  create() / store() so the user gets a friendly redirect rather than a DB error.
 */
class CommitteeCommunicationsController
{
    protected PDO             $pdo;
    protected DocumentService $docService;

    /** Document type name eligible for this workflow. */
    private const ELIGIBLE_TYPE = 'Communication';

    public function __construct()
    {
        $database         = new Database();
        $this->pdo        = $database->connect();
        $this->docService = new DocumentService();
    }

    // =========================================================================
    // 0. Index — listing page  GET committee/communications
    // =========================================================================

    public function index(): void
    {
        $userId = $this->requireAuth();

        // Filters
        $search = trim($_GET['search'] ?? '');

        // Pagination
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        // Only show communications created by the current user.
        $where  = ['cc.assigned_by = ?'];
        $params = [$userId];

        if ($search !== '') {
            $where[]  = '(d.tracking_number LIKE ? OR cc.subject LIKE ? OR cc.sender_details LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $whereClause = implode(' AND ', $where);

        // Total count for pagination
        $countStmt = $this->pdo->prepare("
            SELECT COUNT(*) AS total
            FROM committee_communications cc
            INNER JOIN documents d ON cc.document_id = d.id
            WHERE {$whereClause}
        ");
        $countStmt->execute($params);
        $total      = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        $totalPages = max(1, (int) ceil($total / $perPage));

        // Fetch page — include workflow progress indicators
        $listStmt = $this->pdo->prepare("
            SELECT
                cc.id,
                cc.document_id,
                cc.date_logged,
                cc.subject,
                cc.sender_details,
                cc.agenda_id,
                cc.hearing_id,
                cc.report_id,
                cc.created_at,
                d.tracking_number,
                d.subject_matter,
                dt.name           AS document_type_name,
                dt.badge_color    AS document_type_badge_color,
                ds.name           AS status,
                ds.badge_color    AS status_badge_color
            FROM committee_communications cc
            INNER JOIN documents         d  ON cc.document_id        = d.id
            LEFT  JOIN document_types    dt ON d.document_type_id    = dt.id
            LEFT  JOIN document_statuses ds ON d.current_status_id   = ds.id
            WHERE {$whereClause}
            ORDER BY cc.created_at DESC, cc.id DESC
            LIMIT ? OFFSET ?
        ");
        $listStmt->execute([...$params, $perPage, $offset]);
        $communications = $listStmt->fetchAll(PDO::FETCH_ASSOC);

        // Summary stat — total communications created by this user
        $statsStmt = $this->pdo->prepare("
            SELECT COUNT(*) AS total_communications
            FROM committee_communications
            WHERE assigned_by = ?
        ");
        $statsStmt->execute([$userId]);
        $totalCommunications = (int) $statsStmt->fetch(PDO::FETCH_ASSOC)['total_communications'];

        $success = flash_get('success');
        $error   = flash_get('error');
        $errors  = flash_get('errors') ?? [];

        $pageTitle = 'Committee Communications';
        require __DIR__ . '/../../../resources/views/committee/communications/index.php';
    }

    // =========================================================================
    // 1. Create — show form  GET committee/communications/create?document_id={id}
    // =========================================================================

    public function create(): void
    {
        $userId     = $this->requireAuth();
        $documentId = (int) ($_GET['document_id'] ?? 0);

        if ($documentId <= 0) {
            flash_set('error', 'Invalid document ID.');
            redirect('committee/inbox');
        }

        [$document, $assignment] = $this->requireEligibleOwnedDocument($documentId, $userId);

        // Guard: communication must not exist yet.
        if ($this->commExistsForDocument($documentId)) {
            $existing = $this->getCommByDocumentId($documentId);
            flash_set('error', 'A Communication record already exists for this document.');
            redirect('committee/communications/show?id=' . (int) $existing['id']);
        }

        $success = flash_get('success');
        $error   = flash_get('error');
        $errors  = flash_get('errors') ?? [];
        $old     = old_get();

        $pageTitle = 'Log Communication — ' . htmlspecialchars($document['tracking_number']);
        require __DIR__ . '/../../../resources/views/committee/communications/create.php';
    }

    // =========================================================================
    // 2. Store — persist new record  POST committee/communications/create
    // =========================================================================

    public function store(): void
    {
        $userId     = $this->requireAuth();
        $documentId = (int) ($_POST['document_id'] ?? 0);

        if ($documentId <= 0) {
            flash_set('error', 'Invalid document ID.');
            redirect('committee/inbox');
        }

        [$document, $assignment] = $this->requireEligibleOwnedDocument($documentId, $userId);

        // Guard: duplicate.
        if ($this->commExistsForDocument($documentId)) {
            $existing = $this->getCommByDocumentId($documentId);
            flash_set('error', 'A Communication record already exists for this document.');
            redirect('committee/communications/show?id=' . (int) $existing['id']);
        }

        // ── Collect + validate inputs ─────────────────────────────────────────
        $dateLogged    = trim($_POST['date_logged']    ?? '');
        $subject       = trim($_POST['subject']        ?? '');
        $senderDetails = trim($_POST['sender_details'] ?? '');
        $notes         = trim($_POST['notes']          ?? '');

        $errors = [];

        if ($dateLogged === '') {
            $errors[] = 'Date logged is required.';
        } elseif (!$this->isValidDate($dateLogged)) {
            $errors[] = 'Date logged is not a valid date.';
        }

        if ($subject === '') {
            $errors[] = 'Subject / summary is required.';
        } elseif (mb_strlen($subject) > 500) {
            $errors[] = 'Subject must not exceed 500 characters.';
        }

        if ($senderDetails === '') {
            $errors[] = 'Sender / originating party details are required.';
        } elseif (mb_strlen($senderDetails) > 5000) {
            $errors[] = 'Sender details must not exceed 5,000 characters.';
        }

        if (mb_strlen($notes) > 10000) {
            $errors[] = 'Notes must not exceed 10,000 characters.';
        }

        if (!empty($errors)) {
            old_set($_POST);
            flash_set('errors', $errors);
            flash_set('error', 'Please correct the errors below.');
            redirect('committee/communications/create?document_id=' . $documentId);
        }

        // ── Resolve required status ───────────────────────────────────────────
        try {
            $inProgressStatus = $this->requireDocumentStatus('Communication In Progress');
        } catch (RuntimeException $e) {
            flash_set('error', $e->getMessage());
            redirect('committee/communications/create?document_id=' . $documentId);
        }

        $commId = null;

        try {
            $this->pdo->beginTransaction();

            // 1. Insert the communication record.
            $this->pdo->prepare("
                INSERT INTO committee_communications (
                    document_id,
                    date_logged,
                    subject,
                    sender_details,
                    notes,
                    assigned_by
                ) VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([
                $documentId,
                $dateLogged,
                $subject,
                $senderDetails,
                $notes !== '' ? $notes : null,
                $userId,
            ]);
            $commId = (int) $this->pdo->lastInsertId();

            // 2. Update document status → "Communication In Progress".
            $this->pdo->prepare(
                "UPDATE documents SET current_status_id = ?, updated_by = ? WHERE id = ?"
            )->execute([$inProgressStatus['id'], $userId, $documentId]);

            // 3. Workflow event.
            $currentStatusId = (int) ($document['current_status_id'] ?? 0);
            $this->pdo->prepare("
                INSERT INTO document_events (
                    document_id, event_type, phase, performed_by,
                    from_status_id, to_status_id, remarks, metadata
                ) VALUES (?, 'COMMITTEE_COMMUNICATION_CREATED', 'COMMITTEE', ?, ?, ?, ?, ?)
            ")->execute([
                $documentId,
                $userId,
                $currentStatusId ?: null,
                $inProgressStatus['id'],
                "Communication record opened: {$subject}",
                json_encode([
                    'comm_id'     => $commId,
                    'date_logged' => $dateLogged,
                    'subject'     => mb_substr($subject, 0, 200),
                    'ip_address'  => client_ip(),
                    'user_agent'  => client_user_agent(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            $this->pdo->commit();

        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();

            if (($e->errorInfo[1] ?? null) === 1062) {
                // Duplicate document_id — race condition.
                $existing = $this->getCommByDocumentId($documentId);
                flash_set('error', 'A Communication record was just created by a concurrent request.');
                redirect('committee/communications/show?id=' . (int) ($existing['id'] ?? 0));
            }
            system_log('ERROR', 'CommitteeCommunicationsController::store PDO exception', [
                'document_id' => $documentId,
                'user_id'     => $userId,
                'error'       => $e->getMessage(),
            ]);
            flash_set('error', 'Database error while creating Communication record: ' . $e->getMessage());
            redirect('committee/communications/create?document_id=' . $documentId);

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            system_log('ERROR', 'CommitteeCommunicationsController::store exception', [
                'document_id' => $documentId,
                'user_id'     => $userId,
                'error'       => $e->getMessage(),
            ]);
            flash_set('error', 'Failed to create Communication record: ' . $e->getMessage());
            redirect('committee/communications/create?document_id=' . $documentId);
        }

        // ── Post-commit: audit log ────────────────────────────────────────────
        $uStmt = $this->pdo->prepare("SELECT username FROM user_accounts WHERE id = ? LIMIT 1");
        $uStmt->execute([$userId]);
        $username = (string) ($uStmt->fetchColumn() ?: '');

        audit_log('CREATE', 'CommitteeCommunication', (string) $commId, null, [
            'comm_id'       => $commId,
            'document_id'   => $documentId,
            'date_logged'   => $dateLogged,
            'subject'       => mb_substr($subject, 0, 200),
            'created_by'    => $userId,
            'created_by_username' => $username,
        ], "Communication record #{$commId} created for document ID {$documentId} (user: {$username})");

        old_clear();
        flash_set('success', 'Communication record created. You may now schedule it for a hearing agenda.');
        redirect('committee/communications/show?id=' . $commId);
    }

    // =========================================================================
    // 3. Show — detail page  GET committee/communications/show?id={comm_id}
    // =========================================================================

    public function show(): void
    {
        $userId = $this->requireAuth();
        $commId = (int) ($_GET['id'] ?? 0);

        if ($commId <= 0) {
            flash_set('error', 'Invalid communication ID.');
            redirect('committee/inbox');
        }

        $comm = $this->loadCommWithDocument($commId);
        if (!$comm) {
            flash_set('error', 'Communication record not found.');
            redirect('committee/inbox');
        }
        $this->requireCommOwnership($comm, $userId);

        $documentId = (int) $comm['document_id'];

        // Attachments for the source document
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
            LEFT JOIN user_accounts     ua ON ua.id = de.performed_by
            LEFT JOIN document_statuses fs ON fs.id = de.from_status_id
            LEFT JOIN document_statuses ts ON ts.id = de.to_status_id
            WHERE de.document_id = ?
            ORDER BY de.created_at ASC
        ");
        $evtStmt->execute([$documentId]);
        $events = $evtStmt->fetchAll();

        // Linked agenda (if any)
        $linkedAgenda = null;
        if (!empty($comm['agenda_id'])) {
            $agStmt = $this->pdo->prepare("
                SELECT ag.*,
                       GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') AS committee_names
                FROM agendas ag
                LEFT JOIN agenda_committees ac ON ac.agenda_id = ag.id
                LEFT JOIN committees         c ON c.id = ac.committee_id
                WHERE ag.id = ?
                GROUP BY ag.id
                LIMIT 1
            ");
            $agStmt->execute([(int) $comm['agenda_id']]);
            $linkedAgenda = $agStmt->fetch() ?: null;
        }

        // Linked hearing (if any)
        $linkedHearing = null;
        if (!empty($comm['hearing_id'])) {
            $hStmt = $this->pdo->prepare("
                SELECT ch.*, ua.username AS performed_by_username
                FROM committee_hearings ch
                LEFT JOIN user_accounts ua ON ua.id = ch.performed_by
                WHERE ch.id = ?
                LIMIT 1
            ");
            $hStmt->execute([(int) $comm['hearing_id']]);
            $linkedHearing = $hStmt->fetch() ?: null;
        }

        // Linked report (if any)
        $linkedReport = null;
        if (!empty($comm['report_id'])) {
            $rStmt = $this->pdo->prepare("
                SELECT id, report_number, report_type, returned_to_plenary_at
                FROM committee_reports
                WHERE id = ?
                LIMIT 1
            ");
            $rStmt->execute([(int) $comm['report_id']]);
            $linkedReport = $rStmt->fetch() ?: null;
        }

        $success = flash_get('success');
        $error   = flash_get('error');
        $errors  = flash_get('errors') ?? [];

        $pageTitle = 'Communication — ' . htmlspecialchars($comm['tracking_number'] ?? '');
        require __DIR__ . '/../../../resources/views/committee/communications/show.php';
    }

    // =========================================================================
    // 4. Agenda Show  GET committee/communications/agenda?id={comm_id}
    // =========================================================================

    public function agendaShow(): void
    {
        $userId = $this->requireAuth();
        $commId = (int) ($_GET['id'] ?? 0);

        if ($commId <= 0) {
            flash_set('error', 'Invalid communication ID.');
            redirect('committee/inbox');
        }

        $comm = $this->loadCommWithDocument($commId);
        if (!$comm) {
            flash_set('error', 'Communication record not found.');
            redirect('committee/inbox');
        }
        $this->requireCommOwnership($comm, $userId);

        // Guard: agenda already scheduled
        if (!empty($comm['agenda_id'])) {
            flash_set('error', 'An agenda has already been scheduled for this communication.');
            redirect('committee/communications/show?id=' . $commId);
        }

        $documentId = (int) $comm['document_id'];

        // All active committees
        $allCommittees = $this->pdo->query(
            "SELECT id, name FROM committees WHERE is_active = 1 AND is_deleted = 0 ORDER BY name ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        // Pre-select committees already linked to the document
        $acStmt = $this->pdo->prepare(
            "SELECT committee_id FROM document_committees WHERE document_id = ?"
        );
        $acStmt->execute([$documentId]);
        $assignedCommitteeIds = array_column($acStmt->fetchAll(PDO::FETCH_ASSOC), 'committee_id');

        // All active SP members for chairperson selection
        $spMembers = $this->pdo->query("
            SELECT sp_member_id, first_name, last_name, middle_name, suffix
            FROM sp_members
            WHERE is_active = 1 AND is_deleted = 0
            ORDER BY last_name ASC, first_name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $success = flash_get('success');
        $error   = flash_get('error');
        $errors  = flash_get('errors') ?? [];

        $pageTitle = 'Schedule Agenda — Communication';
        require __DIR__ . '/../../../resources/views/committee/communications/agenda.php';
    }

    // =========================================================================
    // 5. Agenda Store  POST committee/communications/agenda
    // =========================================================================

    public function agendaStore(): void
    {
        $userId = $this->requireAuth();
        $commId = (int) ($_POST['comm_id'] ?? 0);

        if ($commId <= 0) {
            flash_set('error', 'Invalid communication ID.');
            redirect('committee/inbox');
        }

        $comm = $this->loadCommWithDocument($commId);
        if (!$comm) {
            flash_set('error', 'Communication record not found.');
            redirect('committee/inbox');
        }
        $this->requireCommOwnership($comm, $userId);

        // Guard: already scheduled
        if (!empty($comm['agenda_id'])) {
            flash_set('error', 'An agenda has already been scheduled for this communication.');
            redirect('committee/communications/show?id=' . $commId);
        }

        $documentId = (int) $comm['document_id'];

        // ── Collect inputs ────────────────────────────────────────────────────
        $agendaNumber    = trim($_POST['agenda_number']    ?? '');
        $agendaType      = trim($_POST['agenda_type']      ?? '');
        $agendaDate      = trim($_POST['agenda_date']      ?? '');
        $agendaTime      = trim($_POST['agenda_time']      ?? '');
        $venue           = trim($_POST['venue']            ?? '');
        $committeeIds    = array_filter(array_map('intval', (array) ($_POST['committee_ids']    ?? [])));
        $chairpersonIds  = array_filter(array_map('intval', (array) ($_POST['chairperson_ids'] ?? [])));
        $remarksAgenda   = trim($_POST['remarks']          ?? '');

        // ── Validation ────────────────────────────────────────────────────────
        $errors = [];

        if ($agendaNumber === '') {
            $errors[] = 'Agenda number is required.';
        } elseif (mb_strlen($agendaNumber) > 50) {
            $errors[] = 'Agenda number must not exceed 50 characters.';
        }

        if ($agendaType === '') {
            $errors[] = 'Agenda type is required.';
        } elseif (mb_strlen($agendaType) > 100) {
            $errors[] = 'Agenda type must not exceed 100 characters.';
        }

        if ($agendaDate === '') {
            $errors[] = 'Agenda date is required.';
        } elseif (!$this->isValidDate($agendaDate)) {
            $errors[] = 'Agenda date is not a valid date.';
        }

        if ($agendaTime === '') {
            $errors[] = 'Agenda time is required.';
        }

        if ($venue === '') {
            $errors[] = 'Venue is required.';
        } elseif (mb_strlen($venue) > 255) {
            $errors[] = 'Venue must not exceed 255 characters.';
        }

        if (empty($committeeIds)) {
            $errors[] = 'At least one committee must be selected.';
        }

        if (!empty($errors)) {
            old_set($_POST);
            flash_set('errors', $errors);
            flash_set('error', 'Please correct the errors below.');
            redirect('committee/communications/agenda?id=' . $commId);
        }

        // ── Validate committee IDs ────────────────────────────────────────────
        $cPh  = implode(',', array_fill(0, count($committeeIds), '?'));
        $cChk = $this->pdo->prepare(
            "SELECT id FROM committees WHERE id IN ({$cPh}) AND is_active = 1 AND is_deleted = 0"
        );
        $cChk->execute($committeeIds);
        $validCIds = array_column($cChk->fetchAll(PDO::FETCH_ASSOC), 'id');

        if (count($validCIds) !== count($committeeIds)) {
            flash_set('error', 'One or more selected committees are invalid.');
            redirect('committee/communications/agenda?id=' . $commId);
        }

        // ── Validate chairperson IDs (optional) ───────────────────────────────
        $validChairIds = [];
        if (!empty($chairpersonIds)) {
            $spPh  = implode(',', array_fill(0, count($chairpersonIds), '?'));
            $spChk = $this->pdo->prepare(
                "SELECT sp_member_id FROM sp_members WHERE sp_member_id IN ({$spPh})
                 AND is_active = 1 AND is_deleted = 0"
            );
            $spChk->execute($chairpersonIds);
            $validChairIds = array_column($spChk->fetchAll(PDO::FETCH_ASSOC), 'sp_member_id');
        }

        // ── Resolve status ────────────────────────────────────────────────────
        try {
            $onGoingStatus = $this->requireDocumentStatus('On Going');
        } catch (RuntimeException $e) {
            flash_set('error', $e->getMessage());
            redirect('committee/communications/agenda?id=' . $commId);
        }

        $agendaId = null;

        try {
            $this->pdo->beginTransaction();

            // 1. Username
            $uStmt = $this->pdo->prepare("SELECT username FROM user_accounts WHERE id = ? LIMIT 1");
            $uStmt->execute([$userId]);
            $username = (string) ($uStmt->fetchColumn() ?: '');

            // 2. Insert agenda
            $this->pdo->prepare("
                INSERT INTO agendas (
                    document_id, agenda_number, agenda_type,
                    agenda_date, agenda_time, venue,
                    created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ")->execute([
                $documentId,
                $agendaNumber,
                $agendaType,
                $agendaDate,
                $agendaTime,
                $venue,
                $userId,
            ]);
            $agendaId = (int) $this->pdo->lastInsertId();

            // 3. Link committees to agenda
            $insAC = $this->pdo->prepare(
                "INSERT IGNORE INTO agenda_committees (agenda_id, committee_id) VALUES (?, ?)"
            );
            foreach ($validCIds as $cId) {
                $insAC->execute([$agendaId, $cId]);
            }

            // 4. Link chairpersons to agenda (optional)
            if (!empty($validChairIds)) {
                $insAP = $this->pdo->prepare(
                    "INSERT IGNORE INTO agenda_chairpersons (agenda_id, sp_member_id) VALUES (?, ?)"
                );
                foreach ($validChairIds as $spId) {
                    $insAP->execute([$agendaId, $spId]);
                }
            }

            // 5. Update committee_communications.agenda_id
            $this->pdo->prepare(
                "UPDATE committee_communications SET agenda_id = ?, updated_at = NOW() WHERE id = ?"
            )->execute([$agendaId, $commId]);

            // 6. Update document status → "On Going"
            $this->pdo->prepare(
                "UPDATE documents SET current_status_id = ?, updated_by = ? WHERE id = ?"
            )->execute([$onGoingStatus['id'], $userId, $documentId]);

            // 7. Workflow event
            $prevStatusId = (int) ($comm['current_status_id'] ?? 0);
            $this->pdo->prepare("
                INSERT INTO document_events (
                    document_id, event_type, phase, performed_by,
                    from_status_id, to_status_id, remarks, metadata
                ) VALUES (?, 'COMMITTEE_COMMUNICATION_AGENDA_SCHEDULED', 'COMMITTEE', ?, ?, ?, ?, ?)
            ")->execute([
                $documentId,
                $userId,
                $prevStatusId ?: null,
                $onGoingStatus['id'],
                "Agenda {$agendaNumber} scheduled for {$agendaDate}." . ($remarksAgenda ? " {$remarksAgenda}" : ''),
                json_encode([
                    'comm_id'      => $commId,
                    'agenda_id'    => $agendaId,
                    'agenda_number'=> $agendaNumber,
                    'agenda_type'  => $agendaType,
                    'agenda_date'  => $agendaDate,
                    'venue'        => $venue,
                    'committee_ids'=> $validCIds,
                    'ip_address'   => client_ip(),
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
                "Agenda {$agendaNumber} scheduled (Communication workflow).",
            ]);

            $this->pdo->commit();

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            system_log('ERROR', 'CommitteeCommunicationsController::agendaStore exception', [
                'comm_id'     => $commId,
                'document_id' => $documentId,
                'error'       => $e->getMessage(),
            ]);
            flash_set('error', 'Database error while scheduling agenda: ' . $e->getMessage());
            redirect('committee/communications/agenda?id=' . $commId);
        }

        audit_log('CREATE', 'Agenda', (string) $agendaId, null, [
            'comm_id'       => $commId,
            'agenda_id'     => $agendaId,
            'agenda_number' => $agendaNumber,
            'document_id'   => $documentId,
            'created_by'    => $userId,
        ], "Agenda #{$agendaNumber} scheduled for Communication #{$commId} (document ID {$documentId})");

        old_clear();
        flash_set('success', "Agenda {$agendaNumber} scheduled successfully. The hearing can now be recorded.");
        redirect('committee/communications/show?id=' . $commId);
    }

    // =========================================================================
    // 6. Hearing Show  GET committee/communications/hearing?id={comm_id}
    // =========================================================================

    public function hearingShow(): void
    {
        $userId = $this->requireAuth();
        $commId = (int) ($_GET['id'] ?? 0);

        if ($commId <= 0) {
            flash_set('error', 'Invalid communication ID.');
            redirect('committee/inbox');
        }

        $comm = $this->loadCommWithDocument($commId);
        if (!$comm) {
            flash_set('error', 'Communication record not found.');
            redirect('committee/inbox');
        }
        $this->requireCommOwnership($comm, $userId);

        // Must have an agenda scheduled first
        if (empty($comm['agenda_id'])) {
            flash_set('error', 'You must schedule an agenda before recording a hearing outcome.');
            redirect('committee/communications/show?id=' . $commId);
        }

        // Must not already have a hearing recorded
        if (!empty($comm['hearing_id'])) {
            flash_set('error', 'A hearing outcome has already been recorded for this communication.');
            redirect('committee/communications/show?id=' . $commId);
        }

        // Load agenda
        $agStmt = $this->pdo->prepare("
            SELECT ag.*,
                   GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') AS committee_names
            FROM agendas ag
            LEFT JOIN agenda_committees ac ON ac.agenda_id = ag.id
            LEFT JOIN committees         c ON c.id = ac.committee_id
            WHERE ag.id = ?
            GROUP BY ag.id
            LIMIT 1
        ");
        $agStmt->execute([(int) $comm['agenda_id']]);
        $agenda = $agStmt->fetch() ?: [];

        $success = flash_get('success');
        $error   = flash_get('error');
        $errors  = flash_get('errors') ?? [];

        $pageTitle = 'Record Hearing — Communication';
        require __DIR__ . '/../../../resources/views/committee/communications/hearing.php';
    }

    // =========================================================================
    // 7. Hearing Store  POST committee/communications/hearing
    // =========================================================================

    public function hearingStore(): void
    {
        $userId = $this->requireAuth();
        $commId = (int) ($_POST['comm_id'] ?? 0);

        if ($commId <= 0) {
            flash_set('error', 'Invalid communication ID.');
            redirect('committee/inbox');
        }

        $comm = $this->loadCommWithDocument($commId);
        if (!$comm) {
            flash_set('error', 'Communication record not found.');
            redirect('committee/inbox');
        }
        $this->requireCommOwnership($comm, $userId);

        if (empty($comm['agenda_id'])) {
            flash_set('error', 'No agenda scheduled for this communication.');
            redirect('committee/communications/show?id=' . $commId);
        }

        if (!empty($comm['hearing_id'])) {
            flash_set('error', 'A hearing outcome has already been recorded.');
            redirect('committee/communications/show?id=' . $commId);
        }

        $agendaId   = (int) $comm['agenda_id'];
        $documentId = (int) $comm['document_id'];

        $outcome = trim($_POST['outcome'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        $validOutcomes = ['APPROVED', 'DEFERRED', 'REMANDED', 'WITHDRAWN'];
        if (!in_array($outcome, $validOutcomes, true)) {
            flash_set('error', 'Please select a valid hearing outcome.');
            redirect('committee/communications/hearing?id=' . $commId);
        }

        // ── Status map ────────────────────────────────────────────────────────
        $statusMap = [
            'APPROVED'  => 'Hearing Completed',
            'DEFERRED'  => 'Deferred',
            'REMANDED'  => 'Remanded',
            'WITHDRAWN' => 'Withdrawn',
        ];

        try {
            $targetStatus = $this->requireDocumentStatus($statusMap[$outcome]);
        } catch (RuntimeException $e) {
            flash_set('error', $e->getMessage());
            redirect('committee/communications/hearing?id=' . $commId);
        }

        $hearingId = null;

        try {
            $this->pdo->beginTransaction();

            // 1. Username
            $uStmt = $this->pdo->prepare("SELECT username FROM user_accounts WHERE id = ? LIMIT 1");
            $uStmt->execute([$userId]);
            $username = (string) ($uStmt->fetchColumn() ?: '');

            // 2. Insert committee_hearings row (reuse existing table)
            $this->pdo->prepare("
                INSERT INTO committee_hearings
                    (document_id, agenda_id, outcome, remarks, performed_by, performed_at, created_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ")->execute([$documentId, $agendaId, $outcome, $remarks ?: null, $userId]);
            $hearingId = (int) $this->pdo->lastInsertId();

            // 3. Update committee_communications.hearing_id
            $this->pdo->prepare(
                "UPDATE committee_communications SET hearing_id = ?, updated_at = NOW() WHERE id = ?"
            )->execute([$hearingId, $commId]);

            // 4. Update document status
            $prevStatusId = (int) ($comm['current_status_id'] ?? 0);
            $this->pdo->prepare(
                "UPDATE documents SET current_status_id = ?, updated_by = ? WHERE id = ?"
            )->execute([$targetStatus['id'], $userId, $documentId]);

            // 5. Workflow event
            $this->pdo->prepare("
                INSERT INTO document_events (
                    document_id, event_type, phase, performed_by,
                    from_status_id, to_status_id, remarks, metadata
                ) VALUES (?, 'COMMITTEE_COMMUNICATION_HEARING_RECORDED', 'COMMITTEE', ?, ?, ?, ?, ?)
            ")->execute([
                $documentId,
                $userId,
                $prevStatusId ?: null,
                $targetStatus['id'],
                "Communication hearing outcome: {$outcome}." . ($remarks ? " {$remarks}" : ''),
                json_encode([
                    'comm_id'               => $commId,
                    'hearing_id'            => $hearingId,
                    'agenda_id'             => $agendaId,
                    'outcome'               => $outcome,
                    'performed_by'          => $userId,
                    'performed_by_username' => $username,
                    'ip_address'            => client_ip(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            // 6. Route record
            $committeeRoleId = $this->requireCommitteeRoleId();
            $this->pdo->prepare("
                INSERT INTO document_routes
                    (document_id, from_phase, to_phase, routed_by, routed_to_role_id, remarks)
                VALUES (?, 'COMMITTEE', 'COMMITTEE', ?, ?, ?)
            ")->execute([
                $documentId,
                $userId,
                $committeeRoleId,
                "Communication hearing outcome: {$outcome}" . ($remarks ? " — {$remarks}" : ''),
            ]);

            $this->pdo->commit();

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            system_log('ERROR', 'CommitteeCommunicationsController::hearingStore exception', [
                'comm_id'     => $commId,
                'document_id' => $documentId,
                'error'       => $e->getMessage(),
            ]);
            flash_set('error', 'Database error while recording hearing: ' . $e->getMessage());
            redirect('committee/communications/hearing?id=' . $commId);
        }

        audit_log('CREATE', 'CommitteeHearing', (string) $hearingId, null, [
            'comm_id'     => $commId,
            'hearing_id'  => $hearingId,
            'document_id' => $documentId,
            'outcome'     => $outcome,
            'performed_by'=> $userId,
        ], "Communication hearing outcome '{$outcome}' recorded for comm #{$commId} (document ID {$documentId})");

        old_clear();

        if ($outcome === 'APPROVED') {
            flash_set('success', 'Hearing outcome: Approved. Please create the Committee Report.');
            redirect('committee/communications/report?id=' . $commId);
        }

        flash_set('success', "Hearing outcome recorded: {$outcome}.");
        redirect('committee/communications/show?id=' . $commId);
    }

    // =========================================================================
    // 8. Report Show  GET committee/communications/report?id={comm_id}
    // =========================================================================

    public function reportShow(): void
    {
        $userId = $this->requireAuth();
        $commId = (int) ($_GET['id'] ?? 0);

        if ($commId <= 0) {
            flash_set('error', 'Invalid communication ID.');
            redirect('committee/inbox');
        }

        $comm = $this->loadCommWithDocument($commId);
        if (!$comm) {
            flash_set('error', 'Communication record not found.');
            redirect('committee/inbox');
        }
        $this->requireCommOwnership($comm, $userId);

        if (empty($comm['hearing_id'])) {
            flash_set('error', 'A hearing must be recorded before creating a Committee Report.');
            redirect('committee/communications/show?id=' . $commId);
        }

        // Load hearing — must be APPROVED
        $hearingStmt = $this->pdo->prepare(
            "SELECT * FROM committee_hearings WHERE id = ? LIMIT 1"
        );
        $hearingStmt->execute([(int) $comm['hearing_id']]);
        $hearing = $hearingStmt->fetch();

        if (!$hearing || $hearing['outcome'] !== 'APPROVED') {
            flash_set('error', 'Only Approved hearings can produce a Committee Report.');
            redirect('committee/communications/show?id=' . $commId);
        }

        // Guard: report already created
        if (!empty($comm['report_id'])) {
            flash_set('error', 'A Committee Report has already been created for this communication.');
            redirect('committee/reports/show?id=' . (int) $comm['report_id']);
        }

        $documentId = (int) $comm['document_id'];

        // All active committees shown as options; pre-select those linked to the agenda.
        $agendaCommittees = $this->pdo->query(
            "SELECT id, name FROM committees WHERE is_active = 1 AND is_deleted = 0 ORDER BY name ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        // IDs of committees already on this agenda (used for default pre-selection).
        $acStmt = $this->pdo->prepare(
            "SELECT committee_id FROM agenda_committees WHERE agenda_id = ?"
        );
        $acStmt->execute([(int) $comm['agenda_id']]);
        $agendaCommitteeIds = array_column($acStmt->fetchAll(PDO::FETCH_ASSOC), 'committee_id');

        $success = flash_get('success');
        $error   = flash_get('error');
        $errors  = flash_get('errors') ?? [];
        $old     = old_get();

        // On validation failure repopulate from old input; otherwise use agenda defaults.
        // $reportType is auto-derived by the view JS and re-verified server-side in reportStore().
        $effectiveCommitteeIds = !empty($old['committee_ids'])
            ? array_map('intval', (array) $old['committee_ids'])
            : $agendaCommitteeIds;

        $reportType = count($effectiveCommitteeIds) > 1
            ? 'JOINT_COMMITTEE_REPORT'
            : 'COMMITTEE_REPORT';

        $pageTitle = 'Create Committee Report — Communication';
        require __DIR__ . '/../../../resources/views/committee/communications/report.php';
    }

    // =========================================================================
    // 9. Report Store  POST committee/communications/report
    // =========================================================================

    public function reportStore(): void
    {
        $userId = $this->requireAuth();
        $commId = (int) ($_POST['comm_id'] ?? 0);

        if ($commId <= 0) {
            flash_set('error', 'Invalid communication ID.');
            redirect('committee/inbox');
        }

        $comm = $this->loadCommWithDocument($commId);
        if (!$comm) {
            flash_set('error', 'Communication record not found.');
            redirect('committee/inbox');
        }
        $this->requireCommOwnership($comm, $userId);

        if (empty($comm['hearing_id'])) {
            flash_set('error', 'No hearing record found for this communication.');
            redirect('committee/communications/show?id=' . $commId);
        }

        if (!empty($comm['report_id'])) {
            flash_set('error', 'A Committee Report has already been created for this communication.');
            redirect('committee/reports/show?id=' . (int) $comm['report_id']);
        }

        $documentId = (int) $comm['document_id'];
        $hearingId  = (int) $comm['hearing_id'];
        $agendaId   = (int) $comm['agenda_id'];

        // Verify hearing is APPROVED
        $hearingStmt = $this->pdo->prepare(
            "SELECT outcome FROM committee_hearings WHERE id = ? AND document_id = ? LIMIT 1"
        );
        $hearingStmt->execute([$hearingId, $documentId]);
        $hearingRow = $hearingStmt->fetch();

        if (!$hearingRow || $hearingRow['outcome'] !== 'APPROVED') {
            flash_set('error', 'Only Approved hearings can produce a Committee Report.');
            redirect('committee/communications/show?id=' . $commId);
        }

        // ── Collect inputs ────────────────────────────────────────────────────
        $reportNumber      = trim($_POST['report_number']       ?? '');
        $summaryOfFindings = trim($_POST['summary_of_findings'] ?? '');
        $committeeIds      = array_filter(array_map('intval', (array) ($_POST['committee_ids'] ?? [])));

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
                $errors[] = "Report Number \"{$reportNumber}\" already exists. Please use a unique number.";
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

        // File uploads (optional)
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
            redirect('committee/communications/report?id=' . $commId);
        }

        // ── Validate committee IDs ────────────────────────────────────────────
        $cPh  = implode(',', array_fill(0, count($committeeIds), '?'));
        $cChk = $this->pdo->prepare(
            "SELECT id FROM committees WHERE id IN ({$cPh}) AND is_active = 1 AND is_deleted = 0"
        );
        $cChk->execute($committeeIds);
        $validCIds = array_column($cChk->fetchAll(PDO::FETCH_ASSOC), 'id');

        if (count($validCIds) !== count($committeeIds)) {
            flash_set('error', 'One or more selected committees are invalid.');
            redirect('committee/communications/report?id=' . $commId);
        }

        $reportType = count($validCIds) > 1 ? 'JOINT_COMMITTEE_REPORT' : 'COMMITTEE_REPORT';

        // ── Stage files BEFORE transaction ────────────────────────────────────
        if ($hasFiles) {
            try {
                $stagedFiles = $this->docService->stageFileUploads($uploadedFiles, $documentId);
            } catch (Throwable $e) {
                flash_set('error', 'File upload failed: ' . $e->getMessage());
                redirect('committee/communications/report?id=' . $commId);
            }
        }

        // ── Status ────────────────────────────────────────────────────────────
        try {
            $reportStatus         = $this->requireDocumentStatus('Committee Report Created');
            $hearingCompletedStat = $this->requireDocumentStatus('Hearing Completed');
        } catch (RuntimeException $e) {
            flash_set('error', $e->getMessage());
            redirect('committee/communications/report?id=' . $commId);
        }

        $reportId        = null;
        $pendingFileLogs = [];

        try {
            $this->pdo->beginTransaction();

            // 1. Username
            $uStmt = $this->pdo->prepare("SELECT username FROM user_accounts WHERE id = ? LIMIT 1");
            $uStmt->execute([$userId]);
            $username = (string) ($uStmt->fetchColumn() ?: '');

            // 2. Insert committee_reports (reuse existing table)
            $this->pdo->prepare("
                INSERT INTO committee_reports
                    (report_type, report_number, summary_of_findings,
                     agenda_id, hearing_id, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ")->execute([
                $reportType,
                $reportNumber,
                $summaryOfFindings,
                $agendaId ?: null,
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

            // 5. Attachments (optional)
            if (!empty($stagedFiles)) {
                $pendingFileLogs = $this->docService->insertStagedAttachments(
                    $stagedFiles,
                    $documentId,
                    $userId,
                    'COMMITTEE',
                    'COMMITTEE_REPORT'
                );
            }

            // 6. Update committee_communications.report_id
            $this->pdo->prepare(
                "UPDATE committee_communications SET report_id = ?, updated_at = NOW() WHERE id = ?"
            )->execute([$reportId, $commId]);

            // 7. Update document status → "Committee Report Created"
            $this->pdo->prepare(
                "UPDATE documents SET current_status_id = ?, updated_by = ? WHERE id = ?"
            )->execute([$reportStatus['id'], $userId, $documentId]);

            // 8. Workflow event
            $this->pdo->prepare("
                INSERT INTO document_events (
                    document_id, event_type, phase, performed_by,
                    from_status_id, to_status_id, remarks, metadata
                ) VALUES (?, 'COMMITTEE_COMMUNICATION_REPORT_CREATED', 'COMMITTEE', ?, ?, ?, ?, ?)
            ")->execute([
                $documentId,
                $userId,
                $hearingCompletedStat['id'],
                $reportStatus['id'],
                "Committee Report #{$reportNumber} created (Communication workflow).",
                json_encode([
                    'comm_id'             => $commId,
                    'report_id'           => $reportId,
                    'report_type'         => $reportType,
                    'report_number'       => $reportNumber,
                    'hearing_id'          => $hearingId,
                    'agenda_id'           => $agendaId,
                    'committee_ids'       => $validCIds,
                    'created_by'          => $userId,
                    'created_by_username' => $username,
                    'ip_address'          => client_ip(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            // 9. Route record
            $committeeRoleId = $this->requireCommitteeRoleId();
            $this->pdo->prepare("
                INSERT INTO document_routes
                    (document_id, from_phase, to_phase, routed_by, routed_to_role_id, remarks)
                VALUES (?, 'COMMITTEE', 'COMMITTEE', ?, ?, ?)
            ")->execute([
                $documentId,
                $userId,
                $committeeRoleId,
                "Committee Report #{$reportNumber} created (Communication workflow, {$reportType}).",
            ]);

            $this->pdo->commit();

            if (!empty($pendingFileLogs)) {
                $this->docService->flushFileUploadLogs($pendingFileLogs);
            }

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            foreach ($stagedFiles as $s) {
                if (file_exists($s['abs_path'])) @unlink($s['abs_path']);
            }
            system_log('ERROR', 'CommitteeCommunicationsController::reportStore exception', [
                'comm_id'     => $commId,
                'document_id' => $documentId,
                'error'       => $e->getMessage(),
            ]);
            flash_set('error', 'Database error while creating the Committee Report. Please try again.');
            redirect('committee/communications/report?id=' . $commId);
        }

        old_clear();
        audit_log('CREATE', 'CommitteeReport', (string) $reportId, null, [
            'comm_id'       => $commId,
            'report_id'     => $reportId,
            'report_type'   => $reportType,
            'report_number' => $reportNumber,
            'document_id'   => $documentId,
            'committee_ids' => $validCIds,
            'created_by'    => $userId,
        ], "Committee Report #{$reportNumber} ({$reportType}) created for Communication #{$commId} by {$username}");

        flash_set('success', "Committee Report #{$reportNumber} created successfully. You may now return it to Plenary from the Reports page.");
        redirect('committee/reports/show?id=' . $reportId);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /** Require a logged-in user; redirect to login on failure. */
    private function requireAuth(): int
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }
        return $userId;
    }

    /** Require and return the Committee role ID; redirect if not found. */
    private function requireCommitteeRoleId(): int
    {
        $stmt = $this->pdo->query(
            "SELECT id FROM roles WHERE name = 'Committee' AND is_active = 1 AND is_deleted = 0 LIMIT 1"
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            flash_set('error', 'Committee role not found. Please contact your administrator.');
            redirect('dashboard');
        }
        return (int) $row['id'];
    }

    /**
     * Verify the document exists, is of type "Communication", and this user
     * holds an ACCEPTED Committee assignment for it.
     *
     * @return array{0: array, 1: array}  [$document, $assignment]
     */
    private function requireEligibleOwnedDocument(int $documentId, int $userId): array
    {
        $committeeRoleId = $this->requireCommitteeRoleId();

        $docStmt = $this->pdo->prepare("
            SELECT
                d.*,
                dt.name        AS document_type_name,
                dt.badge_color AS document_type_badge_color,
                ds.name        AS status,
                ds.badge_color AS status_badge_color,
                st.name        AS source_type,
                eo.name        AS external_office_name,
                eo.abbreviation AS external_office_abbr,
                h.name         AS hospital_name,
                m.name         AS municipality_name
            FROM documents d
            LEFT JOIN document_types    dt ON dt.id = d.document_type_id
            LEFT JOIN document_statuses ds ON ds.id = d.current_status_id
            LEFT JOIN source_types      st ON st.id = d.source_type_id
            LEFT JOIN external_offices  eo ON eo.id = d.external_office_id
            LEFT JOIN hospitals          h ON  h.id = d.hospital_id
            LEFT JOIN municities         m ON  m.id = d.municipality_id
            WHERE d.id = ?
            LIMIT 1
        ");
        $docStmt->execute([$documentId]);
        $document = $docStmt->fetch();

        if (!$document) {
            flash_set('error', 'Document not found.');
            redirect('committee/inbox');
        }

        if ($document['document_type_name'] !== self::ELIGIBLE_TYPE) {
            flash_set('error', 'This document type is not eligible for the Communications workflow.');
            redirect('committee/inbox/show?id=' . $documentId);
        }

        // Verify the user holds the ACCEPTED assignment.
        $assignStmt = $this->pdo->prepare("
            SELECT *
            FROM document_assignments
            WHERE document_id         = ?
              AND assigned_to_role_id = ?
              AND phase               = 'COMMITTEE'
              AND decision            = 'ACCEPTED'
              AND completed_at        IS NULL
              AND (accepted_by = ? OR assigned_to_user_id = ?)
            LIMIT 1
        ");
        $assignStmt->execute([$documentId, $committeeRoleId, $userId, $userId]);
        $assignment = $assignStmt->fetch();

        if (!$assignment) {
            flash_set('error', 'You do not have an active accepted assignment for this document.');
            redirect('committee/inbox/show?id=' . $documentId);
        }

        return [$document, $assignment];
    }

    /** Require that the current user is the one who created the comm record. */
    private function requireCommOwnership(array $comm, int $userId): void
    {
        if ((int) ($comm['assigned_by'] ?? 0) !== $userId) {
            flash_set('error', 'You do not have ownership of this Communication record.');
            redirect('committee/inbox');
        }
    }

    /** Load a communication record with all document and status joins. */
    private function loadCommWithDocument(int $commId): array|false
    {
        $stmt = $this->pdo->prepare("
            SELECT
                cc.*,
                d.tracking_number,
                d.subject_matter,
                d.current_status_id,
                d.date_received,
                d.time_received,
                d.source_name,
                dt.name        AS document_type_name,
                dt.badge_color AS document_type_badge_color,
                ds.name        AS status,
                ds.badge_color AS status_badge_color,
                st.name        AS source_type,
                eo.name        AS external_office_name,
                eo.abbreviation AS external_office_abbr,
                h.name         AS hospital_name,
                m.name         AS municipality_name
            FROM committee_communications cc
            INNER JOIN documents           d  ON d.id  = cc.document_id
            LEFT  JOIN document_types     dt  ON dt.id = d.document_type_id
            LEFT  JOIN document_statuses  ds  ON ds.id = d.current_status_id
            LEFT  JOIN source_types       st  ON st.id = d.source_type_id
            LEFT  JOIN external_offices   eo  ON eo.id = d.external_office_id
            LEFT  JOIN hospitals           h  ON  h.id = d.hospital_id
            LEFT  JOIN municities          m  ON  m.id = d.municipality_id
            WHERE cc.id = ?
            LIMIT 1
        ");
        $stmt->execute([$commId]);
        return $stmt->fetch() ?: false;
    }

    /** Returns true if a communication record already exists for the document. */
    private function commExistsForDocument(int $documentId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM committee_communications WHERE document_id = ? LIMIT 1"
        );
        $stmt->execute([$documentId]);
        return (bool) $stmt->fetch();
    }

    /** Returns the existing communication row (id + basic fields) for a document. */
    private function getCommByDocumentId(int $documentId): array|false
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, subject FROM committee_communications WHERE document_id = ? LIMIT 1"
        );
        $stmt->execute([$documentId]);
        return $stmt->fetch() ?: false;
    }

    /** Resolve a document_status row by name; throws RuntimeException if missing. */
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
                "Required document status \"{$name}\" not found. " .
                "Please run the latest migrations to seed it."
            );
        }
        return $row;
    }

    /** Validate a date string in Y-m-d format. */
    private function isValidDate(string $value): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $value);
        return $d !== false && $d->format('Y-m-d') === $value;
    }
}

<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/DocumentService.php';

/**
 * CommitteeReferredController
 *
 * Handles the full Committee Referred Documents workflow:
 *
 *   index()         GET  committee/referred          Tab switcher:
 *                                                     referred | for_opinion |
 *                                                     ready_for_agenda | withdrawn
 *   endorseShow()   GET  committee/referred/endorse  Select opinion offices
 *   endorseStore()  POST committee/referred/endorse  Persist endorsements
 *   opinionShow()   GET  committee/referred/opinion  Submit opinion for one office
 *   opinionStore()  POST committee/referred/opinion  Persist opinion + files
 *   resolveShow()   GET  committee/referred/resolve  Resolve cycle (agenda/withdraw)
 *   resolveStore()  POST committee/referred/resolve  Persist resolution
 *   agendaShow()    GET  committee/referred/agenda   Schedule agenda form
 *   agendaStore()   POST committee/referred/agenda   Persist agenda
 *
 * AUTHORIZATION: RoleMiddleware restricts all committee/* routes to
 * ['Super Admin', 'Committee']. Every action additionally re-checks
 * auth_id() so a logged-out session is never processed.
 *
 * TRANSACTION PATTERN: All mutations open a PDO transaction, do every DB
 * write inside it, commit, then call audit_log() / system_log() AFTER commit
 * (they use a separate static _log_pdo() connection and must not be called
 * while the main transaction is open).
 *
 * FILE UPLOADS: stageFileUploads() runs BEFORE the transaction (filesystem
 * I/O). insertStagedAttachments() runs INSIDE the transaction. On rollback
 * the staged files are deleted from disk.
 */
class CommitteeReferredController
{
    protected PDO             $pdo;
    protected DocumentService $docService;

    public function __construct()
    {
        $database         = new Database();
        $this->pdo        = $database->connect();
        $this->docService = new DocumentService();
    }

    // =========================================================================
    // 1. Index — tab switcher
    // =========================================================================

    public function index(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        $tab = trim($_GET['tab'] ?? 'referred');
        if (!in_array($tab, ['referred', 'for_opinion', 'ready_for_agenda', 'withdrawn'], true)) {
            $tab = 'referred';
        }

        $search  = trim($_GET['search'] ?? '');
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        $total      = 0;
        $totalPages = 1;
        $documents  = [];

        // ── Search clause helper ──────────────────────────────────────────────
        $searchWhere  = '';
        $searchParams = [];
        if ($search !== '') {
            $searchWhere  = ' AND (d.tracking_number LIKE ? OR d.subject_matter LIKE ?)';
            $searchParams = ["%{$search}%", "%{$search}%"];
        }

        switch ($tab) {

            // ── REFERRED ──────────────────────────────────────────────────────
            // Documents with a completed committee cycle that have NOT yet been
            // sent to any opinion office (no committee_cycle_endorsements row
            // for the latest cycle) AND have not been resolved.
            case 'referred':
                [$total, $totalPages, $documents] = $this->queryReferredTab(
                    $searchWhere, $searchParams, $perPage, $offset
                );
                break;

            // ── FOR OPINION ───────────────────────────────────────────────────
            // Documents that have at least one endorsement for the latest cycle
            // and have not yet been resolved (no committee_opinion_resolutions row).
            case 'for_opinion':
                [$total, $totalPages, $documents] = $this->queryForOpinionTab(
                    $searchWhere, $searchParams, $perPage, $offset
                );
                break;

            // ── READY FOR AGENDA ──────────────────────────────────────────────
            // Documents resolved as PROCEED_TO_AGENDA but no agenda row yet.
            case 'ready_for_agenda':
                [$total, $totalPages, $documents] = $this->queryReadyForAgendaTab(
                    $searchWhere, $searchParams, $perPage, $offset
                );
                break;

            // ── WITHDRAWN ─────────────────────────────────────────────────────
            // Documents resolved as WITHDRAW_DOCUMENT.
            case 'withdrawn':
                [$total, $totalPages, $documents] = $this->queryWithdrawnTab(
                    $searchWhere, $searchParams, $perPage, $offset
                );
                break;
        }

        // ── Tab badge counts ──────────────────────────────────────────────────
        $referredCount       = $this->countReferredTab();
        $forOpinionCount     = $this->countForOpinionTab();
        $readyForAgendaCount = $this->countReadyForAgendaTab();
        $withdrawnCount      = $this->countWithdrawnTab();

        $success = flash_get('success');
        $error   = flash_get('error');
        $errors  = flash_get('errors') ?? [];

        $pageTitle = 'Referred Documents';
        require __DIR__ . '/../../../resources/views/committee/referred/index.php';
    }

    // =========================================================================
    // 2. Endorse — show form
    // =========================================================================

    public function endorseShow(): void
    {
        $userId = auth_id();
        if ($userId === null) { flash_set('error', 'You must be logged in.'); redirect('login'); }

        $documentId = (int) ($_GET['id'] ?? 0);
        if ($documentId <= 0) { flash_set('error', 'Invalid document.'); redirect('committee/referred'); }

        // Load document + latest cycle; ensure it belongs to Referred workflow
        [$document, $cycle] = $this->requireReferredDocument($documentId);

        // Must not already have endorsements for this cycle
        $existing = $this->latestCycleEndorsements($cycle['id']);
        if (!empty($existing)) {
            flash_set('error', 'This document has already been endorsed to opinion offices. Use the For Opinion tab to manage submissions.');
            redirect('committee/referred?tab=for_opinion');
        }

        // Load active opinion offices
        $opinionOffices = $this->fetchOpinionOffices();

        $success = flash_get('success');
        $error   = flash_get('error');
        $errors  = flash_get('errors') ?? [];

        $pageTitle = 'Endorse to Opinion Offices';
        require __DIR__ . '/../../../resources/views/committee/referred/endorse.php';
    }

    // =========================================================================
    // 3. Endorse — store
    // =========================================================================

    public function endorseStore(): void
    {
        $userId = auth_id();
        if ($userId === null) { flash_set('error', 'You must be logged in.'); redirect('login'); }

        $documentId = (int) ($_POST['document_id'] ?? 0);
        $cycleId    = (int) ($_POST['cycle_id']    ?? 0);
        $remarks    = trim($_POST['remarks']        ?? '');
        $officeIds  = array_filter(array_map('intval', (array) ($_POST['office_ids'] ?? [])));

        if ($documentId <= 0 || $cycleId <= 0) {
            flash_set('error', 'Invalid request.');
            redirect('committee/referred');
        }

        // ── Server-side validation ────────────────────────────────────────────
        $errors = [];
        if (empty($officeIds)) {
            $errors[] = 'Please select at least one opinion office.';
        }
        if (!empty($errors)) {
            flash_set('errors', $errors);
            flash_set('error', 'Please correct the errors below.');
            old_set(['remarks' => $remarks]);
            redirect('committee/referred/endorse?id=' . $documentId);
        }

        // ── Verify document & cycle ───────────────────────────────────────────
        [$document, $cycle] = $this->requireReferredDocument($documentId, $cycleId);

        // ── Verify no prior endorsements on this cycle ────────────────────────
        $existing = $this->latestCycleEndorsements($cycleId);
        if (!empty($existing)) {
            flash_set('error', 'This document has already been endorsed to opinion offices.');
            redirect('committee/referred?tab=for_opinion');
        }

        // ── Validate office IDs exist in DB ───────────────────────────────────
        $placeholders = implode(',', array_fill(0, count($officeIds), '?'));
        $officeCheck  = $this->pdo->prepare(
            "SELECT id FROM opinion_offices WHERE id IN ({$placeholders}) AND is_active = 1 AND is_deleted = 0"
        );
        $officeCheck->execute($officeIds);
        $validOfficeIds = array_column($officeCheck->fetchAll(), 'id');
        $badIds = array_diff($officeIds, $validOfficeIds);
        if (!empty($badIds)) {
            flash_set('error', 'One or more selected offices are invalid.');
            redirect('committee/referred/endorse?id=' . $documentId);
        }

        // ── Look up "Opinion Requested" status ────────────────────────────────
        $opinionRequestedStatus = $this->requireDocumentStatus('Opinion Requested');

        // ── Transaction ───────────────────────────────────────────────────────
        $auditData = null;
        try {
            $this->pdo->beginTransaction();

            // 1. Insert one endorsement row per office (endorsement_number = 1)
            $insEndorse = $this->pdo->prepare("
                INSERT INTO committee_cycle_endorsements
                    (cycle_id, opinion_office_id, endorsement_number,
                     opinion_status_id, requested_at, remarks)
                VALUES (?, ?, 1, NULL, NOW(), ?)
            ");
            foreach ($validOfficeIds as $officeId) {
                $insEndorse->execute([$cycleId, $officeId, $remarks ?: null]);
            }

            // 2. Update document status to "Opinion Requested"
            $this->pdo->prepare("
                UPDATE documents SET current_status_id = ?, updated_by = ? WHERE id = ?
            ")->execute([$opinionRequestedStatus['id'], $userId, $documentId]);

            // 3. OPINION_REQUESTED workflow event
            $uStmt = $this->pdo->prepare("SELECT username FROM user_accounts WHERE id = ? LIMIT 1");
            $uStmt->execute([$userId]);
            $username = (string) ($uStmt->fetchColumn() ?: '');

            $this->pdo->prepare("
                INSERT INTO document_events
                    (document_id, event_type, phase, performed_by,
                     from_status_id, to_status_id, remarks, metadata)
                VALUES (?, 'OPINION_REQUESTED', 'COMMITTEE', ?, ?, ?, ?, ?)
            ")->execute([
                $documentId,
                $userId,
                $document['current_status_id'],
                $opinionRequestedStatus['id'],
                $remarks ?: 'Endorsed to opinion offices.',
                json_encode([
                    'cycle_id'             => $cycleId,
                    'opinion_office_ids'   => $validOfficeIds,
                    'endorsement_number'   => 1,
                    'endorsed_by'          => $userId,
                    'endorsed_by_username' => $username,
                    'ip_address'           => client_ip(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            // 4. Route record (COMMITTEE → COMMITTEE, For Opinion phase)
            $committeeRoleId = $this->requireCommitteeRoleId();
            $this->pdo->prepare("
                INSERT INTO document_routes
                    (document_id, from_phase, to_phase, routed_by, routed_to_role_id, remarks)
                VALUES (?, 'COMMITTEE', 'COMMITTEE', ?, ?, ?)
            ")->execute([
                $documentId,
                $userId,
                $committeeRoleId,
                'Endorsed to opinion offices (endorsement #1): ' . ($remarks ?: '—'),
            ]);

            $this->pdo->commit();

            // Post-commit audit
            $auditData = [
                'action'           => 'committee_opinion_endorsed',
                'document_id'      => $documentId,
                'cycle_id'         => $cycleId,
                'office_ids'       => $validOfficeIds,
                'endorsed_by'      => $userId,
                'endorsement_number' => 1,
            ];
            audit_log('UPDATE', 'Document', (string) $documentId, null, $auditData,
                "Committee endorsed document ID {$documentId} to " . count($validOfficeIds) . " opinion office(s) (user: {$username})");
            system_log('INFO', 'Committee opinion endorsement created', $auditData);

        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            system_log('ERROR', 'endorseStore PDOException', ['error' => $e->getMessage(), 'document_id' => $documentId]);
            flash_set('error', 'Database error. Please try again.');
            redirect('committee/referred/endorse?id=' . $documentId);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            system_log('ERROR', 'endorseStore exception', ['error' => $e->getMessage(), 'document_id' => $documentId]);
            flash_set('error', $e->getMessage());
            redirect('committee/referred/endorse?id=' . $documentId);
        }

        old_clear();
        flash_set('success', 'Document endorsed to opinion office(s) successfully.');
        redirect('committee/referred?tab=for_opinion');
    }

    // =========================================================================
    // 4. Opinion — show form
    // =========================================================================

    public function opinionShow(): void
    {
        $userId       = auth_id();
        if ($userId === null) { flash_set('error', 'You must be logged in.'); redirect('login'); }

        $endorsementId = (int) ($_GET['endorsement_id'] ?? 0);
        if ($endorsementId <= 0) { flash_set('error', 'Invalid endorsement.'); redirect('committee/referred?tab=for_opinion'); }

        $endorsement = $this->requireEndorsement($endorsementId);
        $cycle       = $this->requireCycle($endorsement['cycle_id']);
        $document    = $this->requireDocument($cycle['document_id']);

        // Check that this office/cycle has not already been submitted for this endorsement number
        $submStmt = $this->pdo->prepare("
            SELECT id FROM committee_endorsement_submissions WHERE endorsement_id = ? LIMIT 1
        ");
        $submStmt->execute([$endorsementId]);
        $alreadySubmitted = (bool) $submStmt->fetch();

        // Determine if second endorsement is available
        $canSecondEndorse = false;
        if ($alreadySubmitted) {
            // Check submission outcome
            $subData = $this->pdo->prepare("
                SELECT opinion_type FROM committee_endorsement_submissions
                WHERE endorsement_id = ? ORDER BY id DESC LIMIT 1
            ");
            $subData->execute([$endorsementId]);
            $sub = $subData->fetch();
            if ($sub && $sub['opinion_type'] === 'UNFAVORABLE' && (int) $endorsement['endorsement_number'] === 1) {
                // Check no second endorsement already exists
                $hasSecond = $this->pdo->prepare("
                    SELECT id FROM committee_cycle_endorsements
                    WHERE cycle_id = ? AND opinion_office_id = ? AND endorsement_number = 2 LIMIT 1
                ");
                $hasSecond->execute([$cycle['id'], $endorsement['opinion_office_id']]);
                $canSecondEndorse = !$hasSecond->fetch();
            }
        }

        $officeRow = $this->pdo->prepare("
            SELECT id, name, abbreviation FROM opinion_offices WHERE id = ? LIMIT 1
        ");
        $officeRow->execute([$endorsement['opinion_office_id']]);
        $office = $officeRow->fetch();

        $success = flash_get('success');
        $error   = flash_get('error');
        $errors  = flash_get('errors') ?? [];

        $pageTitle = 'Submit Opinion';
        require __DIR__ . '/../../../resources/views/committee/referred/opinion.php';
    }

    // =========================================================================
    // 5. Opinion — store
    // =========================================================================

    public function opinionStore(): void
    {
        $userId        = auth_id();
        if ($userId === null) { flash_set('error', 'You must be logged in.'); redirect('login'); }

        $endorsementId = (int) ($_POST['endorsement_id'] ?? 0);
        $opinionType   = trim($_POST['opinion_type']     ?? '');
        $remarks       = trim($_POST['remarks']          ?? '');

        if ($endorsementId <= 0) {
            flash_set('error', 'Invalid request.');
            redirect('committee/referred?tab=for_opinion');
        }

        // ── Server-side validation ────────────────────────────────────────────
        $errors = [];
        if (!in_array($opinionType, ['FAVORABLE', 'UNFAVORABLE'], true)) {
            $errors[] = 'Please select a valid opinion type (Favorable or Unfavorable).';
        }

        // Validate opinion file
        $opinionFiles     = $_FILES['opinion_file']     ?? [];
        $complianceFiles  = $_FILES['compliance_file']  ?? [];
        $hasOpinionFile   = !empty($opinionFiles['name'][0]) || !empty($opinionFiles['name']);
        $hasComplianceFile= !empty($complianceFiles['name'][0]) || !empty($complianceFiles['name']);

        // Normalise single-file $_FILES into multi-file format for reuse
        $opinionFileNorm    = $this->normaliseSingleFile($opinionFiles);
        $complianceFileNorm = $this->normaliseSingleFile($complianceFiles);

        if (!$hasOpinionFile) {
            $errors[] = 'An opinion file is required.';
        } else {
            $fileErrors = $this->docService->validateFileUploads($opinionFileNorm, true);
            $errors     = array_merge($errors, $fileErrors);
        }

        if ($opinionType === 'UNFAVORABLE') {
            if (!$hasComplianceFile) {
                $errors[] = 'A compliance file is required for an Unfavorable opinion.';
            } else {
                $compErrors = $this->docService->validateFileUploads($complianceFileNorm, true);
                $errors     = array_merge($errors, $compErrors);
            }
        }

        $endorsement = $this->requireEndorsement($endorsementId);
        $cycle       = $this->requireCycle($endorsement['cycle_id']);
        $documentId  = (int) $cycle['document_id'];

        // Prevent duplicate submission for same endorsement row
        $dupCheck = $this->pdo->prepare("
            SELECT id FROM committee_endorsement_submissions WHERE endorsement_id = ? LIMIT 1
        ");
        $dupCheck->execute([$endorsementId]);
        if ($dupCheck->fetch()) {
            flash_set('error', 'Opinion already submitted for this endorsement. To re-endorse, use the second endorsement option.');
            redirect('committee/referred?tab=for_opinion');
        }

        if (!empty($errors)) {
            flash_set('errors', $errors);
            flash_set('error', 'Please correct the errors below.');
            old_set(['opinion_type' => $opinionType, 'remarks' => $remarks]);
            redirect('committee/referred/opinion?endorsement_id=' . $endorsementId);
        }

        $document = $this->requireDocument($documentId);

        // ── Stage files before transaction ────────────────────────────────────
        $opinionStaged    = [];
        $complianceStaged = [];
        try {
            $opinionStaged = $this->docService->stageFileUploads($opinionFileNorm, $documentId);
            if ($opinionType === 'UNFAVORABLE') {
                $complianceStaged = $this->docService->stageFileUploads($complianceFileNorm, $documentId);
            }
        } catch (Throwable $e) {
            foreach ($opinionStaged as $s)    { if (file_exists($s['abs_path'])) @unlink($s['abs_path']); }
            foreach ($complianceStaged as $s) { if (file_exists($s['abs_path'])) @unlink($s['abs_path']); }
            flash_set('error', 'File upload failed: ' . $e->getMessage());
            redirect('committee/referred/opinion?endorsement_id=' . $endorsementId);
        }

        // ── Look up statuses ──────────────────────────────────────────────────
        $opinionStatusName  = ($opinionType === 'FAVORABLE') ? 'Favorable' : 'Unfavorable';
        $opinionStatus      = $this->requireOpinionStatus($opinionStatusName);

        $auditData = null;
        try {
            $this->pdo->beginTransaction();

            // 1. Insert opinion file attachment
            $opPendingLogs = $this->docService->insertStagedAttachments(
                $opinionStaged, $documentId, $userId, 'COMMITTEE', 'OPINION_FILE'
            );
            $opinionAttachmentId = (int) $this->pdo->lastInsertId();

            // 2. Insert compliance file attachment (UNFAVORABLE only)
            $compAttachmentId = null;
            $compPendingLogs  = [];
            if ($opinionType === 'UNFAVORABLE' && !empty($complianceStaged)) {
                $compPendingLogs  = $this->docService->insertStagedAttachments(
                    $complianceStaged, $documentId, $userId, 'COMMITTEE', 'COMPLIANCE_FILE'
                );
                $compAttachmentId = (int) $this->pdo->lastInsertId();
            }

            // 3. Insert submission record
            $this->pdo->prepare("
                INSERT INTO committee_endorsement_submissions
                    (endorsement_id, opinion_type, opinion_file_attachment_id,
                     compliance_file_attachment_id, submitted_by, submitted_at, remarks)
                VALUES (?, ?, ?, ?, ?, NOW(), ?)
            ")->execute([
                $endorsementId,
                $opinionType,
                $opinionAttachmentId,
                $compAttachmentId,
                $userId,
                $remarks ?: null,
            ]);

            // 4. Update endorsement opinion_status_id and submitted_at
            $this->pdo->prepare("
                UPDATE committee_cycle_endorsements
                SET opinion_status_id = ?, submitted_at = NOW()
                WHERE id = ?
            ")->execute([$opinionStatus['id'], $endorsementId]);

            // 5. Workflow event
            $uStmt = $this->pdo->prepare("SELECT username FROM user_accounts WHERE id = ? LIMIT 1");
            $uStmt->execute([$userId]);
            $username = (string) ($uStmt->fetchColumn() ?: '');

            $this->pdo->prepare("
                INSERT INTO document_events
                    (document_id, event_type, phase, performed_by,
                     from_status_id, to_status_id, remarks, metadata)
                VALUES (?, 'OPINION_REQUESTED', 'COMMITTEE', ?, ?, ?, ?, ?)
            ")->execute([
                $documentId,
                $userId,
                $document['current_status_id'],
                $document['current_status_id'],
                "Opinion submitted: {$opinionType}.",
                json_encode([
                    'endorsement_id'   => $endorsementId,
                    'cycle_id'         => $cycle['id'],
                    'opinion_type'     => $opinionType,
                    'submitted_by'     => $userId,
                    'submitted_by_username' => $username,
                    'ip_address'       => client_ip(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            $this->pdo->commit();

            // Post-commit: flush file logs
            $this->docService->flushFileUploadLogs(array_merge($opPendingLogs, $compPendingLogs));

            $auditData = [
                'action'         => 'committee_opinion_submitted',
                'endorsement_id' => $endorsementId,
                'document_id'    => $documentId,
                'opinion_type'   => $opinionType,
                'submitted_by'   => $userId,
            ];
            audit_log('UPDATE', 'Document', (string) $documentId, null, $auditData,
                "Opinion submitted for endorsement #{$endorsementId} ({$opinionType}) by user {$username}");
            system_log('INFO', 'Committee opinion submitted', $auditData);

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            foreach ($opinionStaged    as $s) { if (file_exists($s['abs_path'])) @unlink($s['abs_path']); }
            foreach ($complianceStaged as $s) { if (file_exists($s['abs_path'])) @unlink($s['abs_path']); }
            system_log('ERROR', 'opinionStore exception', ['error' => $e->getMessage(), 'endorsement_id' => $endorsementId]);
            flash_set('error', $e->getMessage());
            redirect('committee/referred/opinion?endorsement_id=' . $endorsementId);
        }

        old_clear();
        flash_set('success', "Opinion submitted successfully ({$opinionType}).");
        redirect('committee/referred?tab=for_opinion');
    }

    // =========================================================================
    // 6. Second endorsement — store
    //    Triggered from For Opinion tab when office is UNFAVORABLE on attempt 1.
    //    Uses endorseStore() logic but for endorsement_number = 2.
    // =========================================================================

    public function secondEndorseStore(): void
    {
        $userId        = auth_id();
        if ($userId === null) { flash_set('error', 'You must be logged in.'); redirect('login'); }

        $endorsementId = (int) ($_POST['endorsement_id'] ?? 0);
        $remarks       = trim($_POST['remarks']          ?? '');

        if ($endorsementId <= 0) {
            flash_set('error', 'Invalid request.');
            redirect('committee/referred?tab=for_opinion');
        }

        $firstEndorsement = $this->requireEndorsement($endorsementId);
        $cycle            = $this->requireCycle($firstEndorsement['cycle_id']);
        $documentId       = (int) $cycle['document_id'];

        // Validate: first endorsement must be UNFAVORABLE and endorsement_number = 1
        if ((int) $firstEndorsement['endorsement_number'] !== 1) {
            flash_set('error', 'Second endorsement can only be created from the first endorsement.');
            redirect('committee/referred?tab=for_opinion');
        }
        $subCheck = $this->pdo->prepare("
            SELECT opinion_type FROM committee_endorsement_submissions
            WHERE endorsement_id = ? ORDER BY id DESC LIMIT 1
        ");
        $subCheck->execute([$endorsementId]);
        $firstSub = $subCheck->fetch();
        if (!$firstSub || $firstSub['opinion_type'] !== 'UNFAVORABLE') {
            flash_set('error', 'Second endorsement is only allowed after an Unfavorable first opinion.');
            redirect('committee/referred?tab=for_opinion');
        }

        // Prevent duplicate second endorsement
        $dupStmt = $this->pdo->prepare("
            SELECT id FROM committee_cycle_endorsements
            WHERE cycle_id = ? AND opinion_office_id = ? AND endorsement_number = 2 LIMIT 1
        ");
        $dupStmt->execute([$cycle['id'], $firstEndorsement['opinion_office_id']]);
        if ($dupStmt->fetch()) {
            flash_set('error', 'A second endorsement already exists for this office and cycle.');
            redirect('committee/referred?tab=for_opinion');
        }

        // Check no submission yet on this new endorsement
        try {
            $this->pdo->beginTransaction();

            $this->pdo->prepare("
                INSERT INTO committee_cycle_endorsements
                    (cycle_id, opinion_office_id, endorsement_number,
                     opinion_status_id, requested_at, remarks)
                VALUES (?, ?, 2, NULL, NOW(), ?)
            ")->execute([$cycle['id'], $firstEndorsement['opinion_office_id'], $remarks ?: null]);

            $newEndorsementId = (int) $this->pdo->lastInsertId();

            // Workflow event
            $uStmt = $this->pdo->prepare("SELECT username FROM user_accounts WHERE id = ? LIMIT 1");
            $uStmt->execute([$userId]);
            $username = (string) ($uStmt->fetchColumn() ?: '');

            $docRow = $this->requireDocument($documentId);

            $this->pdo->prepare("
                INSERT INTO document_events
                    (document_id, event_type, phase, performed_by,
                     from_status_id, to_status_id, remarks, metadata)
                VALUES (?, 'OPINION_REQUESTED', 'COMMITTEE', ?, ?, ?, ?, ?)
            ")->execute([
                $documentId,
                $userId,
                $docRow['current_status_id'],
                $docRow['current_status_id'],
                'Second endorsement issued to opinion office.',
                json_encode([
                    'original_endorsement_id' => $endorsementId,
                    'new_endorsement_id'       => $newEndorsementId,
                    'cycle_id'                 => $cycle['id'],
                    'endorsement_number'       => 2,
                    'office_id'                => $firstEndorsement['opinion_office_id'],
                    'issued_by'                => $userId,
                    'issued_by_username'       => $username,
                    'ip_address'               => client_ip(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            $this->pdo->commit();

            audit_log('UPDATE', 'Document', (string) $documentId, null, [
                'action'            => 'committee_second_endorsement',
                'new_endorsement_id'=> $newEndorsementId,
                'original_endorsement_id' => $endorsementId,
                'office_id'         => $firstEndorsement['opinion_office_id'],
                'cycle_id'          => $cycle['id'],
            ], "Second endorsement issued for document ID {$documentId} by {$username}");

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            system_log('ERROR', 'secondEndorseStore exception', ['error' => $e->getMessage()]);
            flash_set('error', $e->getMessage());
            redirect('committee/referred?tab=for_opinion');
        }

        flash_set('success', 'Second endorsement issued. Opinion can now be submitted.');
        redirect('committee/referred/opinion?endorsement_id=' . $newEndorsementId);
    }

    // =========================================================================
    // 7. Resolve — show form
    // =========================================================================

    public function resolveShow(): void
    {
        $userId = auth_id();
        if ($userId === null) { flash_set('error', 'You must be logged in.'); redirect('login'); }

        $documentId = (int) ($_GET['id'] ?? 0);
        if ($documentId <= 0) { flash_set('error', 'Invalid document.'); redirect('committee/referred'); }

        [$document, $cycle] = $this->requireReferredDocument($documentId);

        // Check for existing resolution
        $resStmt = $this->pdo->prepare("
            SELECT * FROM committee_opinion_resolutions WHERE cycle_id = ? LIMIT 1
        ");
        $resStmt->execute([$cycle['id']]);
        $existingResolution = $resStmt->fetch();

        if ($existingResolution) {
            flash_set('error', 'This cycle has already been resolved.');
            redirect('committee/referred');
        }

        // Load endorsements for this cycle to show status summary
        $endorsements = $this->latestCycleEndorsements($cycle['id']);
        $submissions  = $this->loadSubmissionsForEndorsements($endorsements);

        $success = flash_get('success');
        $error   = flash_get('error');
        $errors  = flash_get('errors') ?? [];

        $pageTitle = 'Resolve Committee Cycle';
        require __DIR__ . '/../../../resources/views/committee/referred/resolve.php';
    }

    // =========================================================================
    // 8. Resolve — store
    // =========================================================================

    public function resolveStore(): void
    {
        $userId     = auth_id();
        if ($userId === null) { flash_set('error', 'You must be logged in.'); redirect('login'); }

        $documentId = (int) ($_POST['document_id'] ?? 0);
        $cycleId    = (int) ($_POST['cycle_id']    ?? 0);
        $resolution = trim($_POST['resolution']    ?? '');
        $remarks    = trim($_POST['remarks']        ?? '');

        if ($documentId <= 0 || $cycleId <= 0) {
            flash_set('error', 'Invalid request.');
            redirect('committee/referred');
        }

        $errors = [];
        if (!in_array($resolution, ['PROCEED_TO_AGENDA', 'WITHDRAW_DOCUMENT'], true)) {
            $errors[] = 'Please select a valid resolution.';
        }
        if (!empty($errors)) {
            flash_set('errors', $errors);
            flash_set('error', 'Please correct the errors below.');
            redirect('committee/referred/resolve?id=' . $documentId);
        }

        [$document, $cycle] = $this->requireReferredDocument($documentId, $cycleId);

        // Guard: no existing resolution
        $dupRes = $this->pdo->prepare("SELECT id FROM committee_opinion_resolutions WHERE cycle_id = ? LIMIT 1");
        $dupRes->execute([$cycleId]);
        if ($dupRes->fetch()) {
            flash_set('error', 'This cycle has already been resolved.');
            redirect('committee/referred');
        }

        // Look up target document status
        $statusName = ($resolution === 'WITHDRAW_DOCUMENT') ? 'Withdrawn' : 'Ready for Agenda';
        $targetStatus = $this->requireDocumentStatus($statusName);

        try {
            $this->pdo->beginTransaction();

            // 1. Insert resolution
            $this->pdo->prepare("
                INSERT INTO committee_opinion_resolutions
                    (cycle_id, resolution, resolved_by, remarks, resolved_at)
                VALUES (?, ?, ?, ?, NOW())
            ")->execute([$cycleId, $resolution, $userId, $remarks ?: null]);

            // 2. Update document status
            $this->pdo->prepare("
                UPDATE documents SET current_status_id = ?, updated_by = ? WHERE id = ?
            ")->execute([$targetStatus['id'], $userId, $documentId]);

            // 3. Workflow event
            $uStmt = $this->pdo->prepare("SELECT username FROM user_accounts WHERE id = ? LIMIT 1");
            $uStmt->execute([$userId]);
            $username = (string) ($uStmt->fetchColumn() ?: '');

            $eventType = ($resolution === 'WITHDRAW_DOCUMENT') ? 'OTHER' : 'AGENDA_SCHEDULED';

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
                $resolution === 'WITHDRAW_DOCUMENT'
                    ? 'Document withdrawn by Committee decision.'
                    : 'Committee resolved to proceed to agenda.',
                json_encode([
                    'cycle_id'    => $cycleId,
                    'resolution'  => $resolution,
                    'resolved_by' => $userId,
                    'resolved_by_username' => $username,
                    'remarks'     => $remarks,
                    'ip_address'  => client_ip(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            // 4. Route record
            $committeeRoleId = $this->requireCommitteeRoleId();
            $this->pdo->prepare("
                INSERT INTO document_routes
                    (document_id, from_phase, to_phase, routed_by, routed_to_role_id, remarks)
                VALUES (?, 'COMMITTEE', 'COMMITTEE', ?, ?, ?)
            ")->execute([
                $documentId,
                $userId,
                $committeeRoleId,
                'Resolution: ' . $resolution . ($remarks ? (' — ' . $remarks) : ''),
            ]);

            $this->pdo->commit();

            audit_log('UPDATE', 'Document', (string) $documentId, null, [
                'action'     => 'committee_cycle_resolved',
                'cycle_id'   => $cycleId,
                'resolution' => $resolution,
                'resolved_by'=> $userId,
                'remarks'    => mb_substr($remarks, 0, 200),
            ], "Committee cycle {$cycleId} resolved: {$resolution} (user: {$username})");
            system_log('INFO', 'Committee cycle resolved', ['cycle_id' => $cycleId, 'resolution' => $resolution]);

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            system_log('ERROR', 'resolveStore exception', ['error' => $e->getMessage()]);
            flash_set('error', $e->getMessage());
            redirect('committee/referred/resolve?id=' . $documentId);
        }

        old_clear();
        if ($resolution === 'WITHDRAW_DOCUMENT') {
            flash_set('success', 'Document withdrawn from the workflow.');
            redirect('committee/referred?tab=withdrawn');
        } else {
            flash_set('success', 'Resolved: Proceed to Agenda. Please schedule the agenda.');
            redirect('committee/referred/agenda?id=' . $documentId);
        }
    }

    // =========================================================================
    // 9. Agenda — show form
    // =========================================================================

    public function agendaShow(): void
    {
        $userId = auth_id();
        if ($userId === null) { flash_set('error', 'You must be logged in.'); redirect('login'); }

        $documentId = (int) ($_GET['id'] ?? 0);
        if ($documentId <= 0) { flash_set('error', 'Invalid document.'); redirect('committee/referred'); }

        [$document, $cycle] = $this->requireReferredDocument($documentId);

        // Must be resolved as PROCEED_TO_AGENDA
        $resRow = $this->pdo->prepare("
            SELECT * FROM committee_opinion_resolutions
            WHERE cycle_id = ? AND resolution = 'PROCEED_TO_AGENDA' LIMIT 1
        ");
        $resRow->execute([$cycle['id']]);
        $resolution = $resRow->fetch();
        if (!$resolution) {
            flash_set('error', 'This document has not been resolved as Proceed to Agenda.');
            redirect('committee/referred?tab=ready_for_agenda');
        }

        // Must not already have an agenda
        $existingAgenda = $this->pdo->prepare("
            SELECT id FROM agendas WHERE cycle_id = ? LIMIT 1
        ");
        $existingAgenda->execute([$cycle['id']]);
        if ($existingAgenda->fetch()) {
            flash_set('error', 'An agenda has already been scheduled for this cycle.');
            redirect('committee/referred?tab=ready_for_agenda');
        }

        // Load committees (preselect the one assigned by Plenary via document_committees)
        $allCommittees = $this->pdo->query(
            "SELECT id, name FROM committees WHERE is_active = 1 AND is_deleted = 0 ORDER BY name ASC"
        )->fetchAll();

        $acStmt = $this->pdo->prepare(
            "SELECT committee_id FROM document_committees WHERE document_id = ?"
        );
        $acStmt->execute([$documentId]);
        $assignedCommitteeIds = array_column($acStmt->fetchAll(), 'committee_id');

        // Load SP members
        $spMembers = $this->docService->getSpMembers();

        $success = flash_get('success');
        $error   = flash_get('error');
        $errors  = flash_get('errors') ?? [];

        $pageTitle = 'Schedule Agenda';
        require __DIR__ . '/../../../resources/views/committee/referred/agenda.php';
    }

    // =========================================================================
    // 10. Agenda — store
    // =========================================================================

    public function agendaStore(): void
    {
        $userId     = auth_id();
        if ($userId === null) { flash_set('error', 'You must be logged in.'); redirect('login'); }

        $documentId     = (int) ($_POST['document_id']    ?? 0);
        $cycleId        = (int) ($_POST['cycle_id']       ?? 0);
        $agendaNumber   = trim($_POST['agenda_number']    ?? '');
        $agendaType     = trim($_POST['agenda_type']      ?? '');
        $agendaDate     = trim($_POST['agenda_date']      ?? '');
        $agendaTime     = trim($_POST['agenda_time']      ?? '');
        $venue          = trim($_POST['venue']            ?? '');
        $remarks        = trim($_POST['remarks']          ?? '');
        $committeeIds   = array_filter(array_map('intval', (array) ($_POST['committee_ids']   ?? [])));
        $chairpersonIds = array_filter(array_map('intval', (array) ($_POST['chairperson_ids'] ?? [])));

        if ($documentId <= 0 || $cycleId <= 0) {
            flash_set('error', 'Invalid request.');
            redirect('committee/referred');
        }

        // ── Server-side validation ────────────────────────────────────────────
        $errors = [];
        if ($agendaDate === '') {
            $errors[] = 'Agenda date is required.';
        } elseif (!$this->isValidDate($agendaDate)) {
            $errors[] = 'Invalid agenda date format.';
        }
        if ($agendaTime === '') {
            $errors[] = 'Agenda time is required.';
        }
        if (empty($committeeIds)) {
            $errors[] = 'At least one committee must be selected.';
        }
        if (empty($chairpersonIds)) {
            $errors[] = 'At least one chairperson must be selected.';
        }

        if (!empty($errors)) {
            flash_set('errors', $errors);
            flash_set('error', 'Please correct the errors below.');
            old_set([
                'agenda_number'   => $agendaNumber,
                'agenda_type'     => $agendaType,
                'agenda_date'     => $agendaDate,
                'agenda_time'     => $agendaTime,
                'venue'           => $venue,
                'remarks'         => $remarks,
                'committee_ids'   => $_POST['committee_ids']   ?? [],
                'chairperson_ids' => $_POST['chairperson_ids'] ?? [],
            ]);
            redirect('committee/referred/agenda?id=' . $documentId);
        }

        [$document, $cycle] = $this->requireReferredDocument($documentId, $cycleId);

        // Guard: resolution must be PROCEED_TO_AGENDA
        $resRow = $this->pdo->prepare("
            SELECT id FROM committee_opinion_resolutions
            WHERE cycle_id = ? AND resolution = 'PROCEED_TO_AGENDA' LIMIT 1
        ");
        $resRow->execute([$cycleId]);
        if (!$resRow->fetch()) {
            flash_set('error', 'This document has not been resolved as Proceed to Agenda.');
            redirect('committee/referred?tab=ready_for_agenda');
        }

        // ── Resolve "On Going" status FIRST — fail fast before any DB writes ────
        // requireDocumentStatus() throws RuntimeException if the status is
        // missing (migration 055 not yet run) so any exception propagates
        // before we touch the agendas table.
        $onGoingStatus = $this->requireDocumentStatus('On Going');

        // Defensive assertion: the resolved status must actually be named
        // "On Going". If somehow a different row matched (shouldn't happen
        // after migration 055 ran correctly) we abort rather than silently
        // setting the wrong status.
        if ($onGoingStatus['name'] !== 'On Going') {
            flash_set('error', 'System configuration error: "On Going" document status could not be resolved. Please contact the system administrator.');
            system_log('ERROR', 'agendaStore: requireDocumentStatus returned wrong name', [
                'expected'   => 'On Going',
                'got_name'   => $onGoingStatus['name'],
                'got_id'     => $onGoingStatus['id'],
                'document_id'=> $documentId,
            ]);
            redirect('committee/referred/agenda?id=' . $documentId);
        }

        // Guard: no existing agenda
        $existingAgendaStmt = $this->pdo->prepare("SELECT id FROM agendas WHERE cycle_id = ? LIMIT 1");
        $existingAgendaStmt->execute([$cycleId]);
        if ($existingAgendaStmt->fetch()) {
            flash_set('error', 'An agenda has already been scheduled for this cycle.');
            redirect('committee/referred?tab=ready_for_agenda');
        }

        // ── Validate committee & chairperson IDs ──────────────────────────────
        if (!empty($committeeIds)) {
            $cPh  = implode(',', array_fill(0, count($committeeIds), '?'));
            $cChk = $this->pdo->prepare(
                "SELECT id FROM committees WHERE id IN ({$cPh}) AND is_active = 1 AND is_deleted = 0"
            );
            $cChk->execute($committeeIds);
            $validCIds = array_column($cChk->fetchAll(), 'id');
            if (count($validCIds) !== count($committeeIds)) {
                flash_set('error', 'One or more selected committees are invalid.');
                redirect('committee/referred/agenda?id=' . $documentId);
            }
        }
        if (!empty($chairpersonIds)) {
            $spPh  = implode(',', array_fill(0, count($chairpersonIds), '?'));
            $spChk = $this->pdo->prepare(
                "SELECT sp_member_id FROM sp_members WHERE sp_member_id IN ({$spPh}) AND is_active = 1 AND is_deleted = 0"
            );
            $spChk->execute($chairpersonIds);
            $validSpIds = array_column($spChk->fetchAll(), 'sp_member_id');
            if (count($validSpIds) !== count($chairpersonIds)) {
                flash_set('error', 'One or more selected chairpersons are invalid.');
                redirect('committee/referred/agenda?id=' . $documentId);
            }
        }

        try {
            $this->pdo->beginTransaction();

            // 1. Insert agenda
            $this->pdo->prepare("
                INSERT INTO agendas
                    (document_id, cycle_id, agenda_number, agenda_type,
                     agenda_date, agenda_time, venue, remarks, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ")->execute([
                $documentId,
                $cycleId,
                $agendaNumber ?: null,
                $agendaType   ?: null,
                $agendaDate,
                $agendaTime,
                $venue   ?: null,
                $remarks ?: null,
                $userId,
            ]);
            $agendaId = (int) $this->pdo->lastInsertId();

            // 2. Insert agenda_committees
            $insAc = $this->pdo->prepare("
                INSERT IGNORE INTO agenda_committees (agenda_id, committee_id) VALUES (?, ?)
            ");
            foreach ($committeeIds as $cId) {
                $insAc->execute([$agendaId, $cId]);
            }

            // 3. Insert agenda_chairpersons
            $insAp = $this->pdo->prepare("
                INSERT IGNORE INTO agenda_chairpersons (agenda_id, sp_member_id) VALUES (?, ?)
            ");
            foreach ($chairpersonIds as $spId) {
                $insAp->execute([$agendaId, $spId]);
            }

            // 4. Update document status → On Going
            $this->pdo->prepare("
                UPDATE documents SET current_status_id = ?, updated_by = ? WHERE id = ?
            ")->execute([$onGoingStatus['id'], $userId, $documentId]);

            // 5. Workflow event — record status transition to On Going
            $uStmt = $this->pdo->prepare("SELECT username FROM user_accounts WHERE id = ? LIMIT 1");
            $uStmt->execute([$userId]);
            $username = (string) ($uStmt->fetchColumn() ?: '');

            $this->pdo->prepare("
                INSERT INTO document_events
                    (document_id, event_type, phase, performed_by,
                     from_status_id, to_status_id, remarks, metadata)
                VALUES (?, 'AGENDA_SCHEDULED', 'COMMITTEE', ?, ?, ?, ?, ?)
            ")->execute([
                $documentId,
                $userId,
                $document['current_status_id'],
                $onGoingStatus['id'],
                'Agenda scheduled. Document status set to On Going.',
                json_encode([
                    'agenda_id'       => $agendaId,
                    'cycle_id'        => $cycleId,
                    'agenda_date'     => $agendaDate,
                    'agenda_time'     => $agendaTime,
                    'committee_ids'   => $committeeIds,
                    'chairperson_ids' => $chairpersonIds,
                    'scheduled_by'    => $userId,
                    'scheduled_by_username' => $username,
                    'ip_address'      => client_ip(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            $this->pdo->commit();

            audit_log('CREATE', 'Agenda', (string) $agendaId, null, [
                'document_id'      => $documentId,
                'cycle_id'         => $cycleId,
                'agenda_date'      => $agendaDate,
                'committee_ids'    => $committeeIds,
                'chairperson_ids'  => $chairpersonIds,
                'created_by'       => $userId,
                'new_status'       => 'On Going',
                'new_status_id'    => $onGoingStatus['id'],
            ], "Agenda scheduled for document ID {$documentId} by {$username}; status set to On Going");
            system_log('INFO', 'Agenda scheduled; document status → On Going', [
                'agenda_id'   => $agendaId,
                'document_id' => $documentId,
                'status_id'   => $onGoingStatus['id'],
            ]);

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            system_log('ERROR', 'agendaStore exception', ['error' => $e->getMessage()]);
            flash_set('error', $e->getMessage());
            redirect('committee/referred/agenda?id=' . $documentId);
        }

        old_clear();
        flash_set('success', 'Agenda scheduled successfully. Document status updated to On Going.');
        redirect('committee/referred?tab=ready_for_agenda');
    }

    // =========================================================================
    // Private — tab queries
    // =========================================================================

    /**
     * REFERRED tab: completed cycles with no endorsements for the latest cycle
     * AND no resolution. Documents still waiting to be sent to opinion offices.
     */
    private function queryReferredTab(string $searchWhere, array $searchParams, int $perPage, int $offset): array
    {
        $countStmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT d.id) AS total
            FROM documents d
            INNER JOIN (
                SELECT document_id, MAX(id) AS max_cycle_id
                FROM committee_cycles WHERE completed_at IS NOT NULL
                GROUP BY document_id
            ) lc ON lc.document_id = d.id
            INNER JOIN committee_cycles cc ON cc.id = lc.max_cycle_id
            -- No endorsements for this cycle yet
            LEFT JOIN committee_cycle_endorsements cce ON cce.cycle_id = cc.id
            -- No resolution for this cycle
            LEFT JOIN committee_opinion_resolutions cor ON cor.cycle_id = cc.id
            WHERE cce.id IS NULL AND cor.id IS NULL
            {$searchWhere}
        ");
        $countStmt->execute($searchParams);
        $total      = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $perPage));

        $listStmt = $this->pdo->prepare("
            SELECT
                d.id                          AS document_id,
                d.tracking_number,
                d.subject_matter,
                dt.name                       AS document_type_name,
                dt.badge_color                AS document_type_badge_color,
                ds.name                       AS status,
                ds.badge_color                AS status_badge_color,
                de.created_at                 AS endorsed_at,
                de.remarks                    AS endorsement_remarks,
                cc.id                         AS cycle_id,
                cc.cycle_number,
                cc.completed_at               AS cycle_completed_at,
                COALESCE(NULLIF(TRIM(dr.remarks), ''), NULLIF(TRIM(d.remarks), '')) AS sender_remarks,
                COALESCE(
                    NULLIF(TRIM(CONCAT(COALESCE(rbi.first_name,''),' ',COALESCE(rbi.last_name,''))), ''),
                    rb.username, '—'
                ) AS referred_by_name,
                rb.username AS referred_by_username
            FROM documents d
            INNER JOIN (
                SELECT document_id, MAX(id) AS max_event_id
                FROM document_events WHERE event_type = 'COMMITTEE_ENDORSED_REFERRED'
                GROUP BY document_id
            ) latest_de ON latest_de.document_id = d.id
            INNER JOIN document_events de ON de.id = latest_de.max_event_id
            INNER JOIN (
                SELECT document_id, MAX(id) AS max_cycle_id
                FROM committee_cycles WHERE completed_at IS NOT NULL
                GROUP BY document_id
            ) lc ON lc.document_id = d.id
            INNER JOIN committee_cycles cc ON cc.id = lc.max_cycle_id
            LEFT JOIN committee_cycle_endorsements cce ON cce.cycle_id = cc.id
            LEFT JOIN committee_opinion_resolutions cor ON cor.cycle_id = cc.id
            LEFT JOIN document_types    dt  ON dt.id  = d.document_type_id
            LEFT JOIN document_statuses ds  ON ds.id  = d.current_status_id
            LEFT JOIN document_routes   dr  ON dr.id  = (
                SELECT dr2.id FROM document_routes dr2
                WHERE dr2.document_id = d.id AND dr2.from_phase = 'ADMIN' AND dr2.to_phase = 'COMMITTEE'
                ORDER BY dr2.created_at DESC, dr2.id DESC LIMIT 1
            )
            LEFT JOIN user_accounts rb  ON rb.id  = dr.routed_by
            LEFT JOIN user_info     rbi ON rbi.user_account_id = rb.id
            WHERE cce.id IS NULL AND cor.id IS NULL
            {$searchWhere}
            ORDER BY de.created_at DESC, d.id DESC
            LIMIT ? OFFSET ?
        ");
        $listStmt->execute([...$searchParams, $perPage, $offset]);
        $documents = $listStmt->fetchAll();

        return [$total, $totalPages, $documents];
    }

    /**
     * FOR OPINION tab: documents with endorsements for the latest cycle,
     * no resolution yet.
     */
    private function queryForOpinionTab(string $searchWhere, array $searchParams, int $perPage, int $offset): array
    {
        $countStmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT d.id) AS total
            FROM documents d
            INNER JOIN (
                SELECT document_id, MAX(id) AS max_cycle_id
                FROM committee_cycles WHERE completed_at IS NOT NULL
                GROUP BY document_id
            ) lc ON lc.document_id = d.id
            INNER JOIN committee_cycles cc ON cc.id = lc.max_cycle_id
            INNER JOIN committee_cycle_endorsements cce ON cce.cycle_id = cc.id
            LEFT JOIN committee_opinion_resolutions cor ON cor.cycle_id = cc.id
            WHERE cor.id IS NULL
            {$searchWhere}
        ");
        $countStmt->execute($searchParams);
        $total      = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $perPage));

        $listStmt = $this->pdo->prepare("
            SELECT
                d.id                  AS document_id,
                d.tracking_number,
                d.subject_matter,
                dt.name               AS document_type_name,
                dt.badge_color        AS document_type_badge_color,
                ds.name               AS status,
                ds.badge_color        AS status_badge_color,
                cc.id                 AS cycle_id,
                cc.cycle_number,
                -- Count endorsements and submitted opinions for summary
                COUNT(DISTINCT cce.id) AS total_offices,
                SUM(CASE WHEN ces.id IS NOT NULL THEN 1 ELSE 0 END) AS submitted_count,
                SUM(CASE WHEN ces.opinion_type = 'FAVORABLE' THEN 1 ELSE 0 END) AS favorable_count,
                SUM(CASE WHEN ces.opinion_type = 'UNFAVORABLE' THEN 1 ELSE 0 END) AS unfavorable_count
            FROM documents d
            INNER JOIN (
                SELECT document_id, MAX(id) AS max_cycle_id
                FROM committee_cycles WHERE completed_at IS NOT NULL
                GROUP BY document_id
            ) lc ON lc.document_id = d.id
            INNER JOIN committee_cycles cc ON cc.id = lc.max_cycle_id
            INNER JOIN committee_cycle_endorsements cce ON cce.cycle_id = cc.id
            LEFT JOIN committee_endorsement_submissions ces ON ces.endorsement_id = cce.id
            LEFT JOIN committee_opinion_resolutions cor ON cor.cycle_id = cc.id
            LEFT JOIN document_types    dt ON dt.id = d.document_type_id
            LEFT JOIN document_statuses ds ON ds.id = d.current_status_id
            WHERE cor.id IS NULL
            {$searchWhere}
            GROUP BY d.id, d.tracking_number, d.subject_matter,
                     dt.name, dt.badge_color, ds.name, ds.badge_color,
                     cc.id, cc.cycle_number
            ORDER BY d.id DESC
            LIMIT ? OFFSET ?
        ");
        $listStmt->execute([...$searchParams, $perPage, $offset]);
        $documents = $listStmt->fetchAll();

        // For each document also load its per-office endorsement rows
        foreach ($documents as &$doc) {
            $doc['endorsements'] = $this->latestCycleEndorsementsWithOffice($doc['cycle_id']);
        }
        unset($doc);

        return [$total, $totalPages, $documents];
    }

    /**
     * READY FOR AGENDA tab: resolved as PROCEED_TO_AGENDA, no agenda yet.
     */
    private function queryReadyForAgendaTab(string $searchWhere, array $searchParams, int $perPage, int $offset): array
    {
        $countStmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT d.id) AS total
            FROM documents d
            INNER JOIN (
                SELECT document_id, MAX(id) AS max_cycle_id
                FROM committee_cycles WHERE completed_at IS NOT NULL
                GROUP BY document_id
            ) lc ON lc.document_id = d.id
            INNER JOIN committee_cycles cc ON cc.id = lc.max_cycle_id
            INNER JOIN committee_opinion_resolutions cor
                ON cor.cycle_id = cc.id AND cor.resolution = 'PROCEED_TO_AGENDA'
            LEFT JOIN agendas ag ON ag.cycle_id = cc.id
            WHERE ag.id IS NULL
            {$searchWhere}
        ");
        $countStmt->execute($searchParams);
        $total      = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $perPage));

        $listStmt = $this->pdo->prepare("
            SELECT
                d.id            AS document_id,
                d.tracking_number,
                d.subject_matter,
                dt.name         AS document_type_name,
                dt.badge_color  AS document_type_badge_color,
                ds.name         AS status,
                ds.badge_color  AS status_badge_color,
                cc.id           AS cycle_id,
                cc.cycle_number,
                cor.resolved_at,
                cor.remarks     AS resolution_remarks
            FROM documents d
            INNER JOIN (
                SELECT document_id, MAX(id) AS max_cycle_id
                FROM committee_cycles WHERE completed_at IS NOT NULL
                GROUP BY document_id
            ) lc ON lc.document_id = d.id
            INNER JOIN committee_cycles cc ON cc.id = lc.max_cycle_id
            INNER JOIN committee_opinion_resolutions cor
                ON cor.cycle_id = cc.id AND cor.resolution = 'PROCEED_TO_AGENDA'
            LEFT JOIN agendas ag ON ag.cycle_id = cc.id
            LEFT JOIN document_types    dt ON dt.id = d.document_type_id
            LEFT JOIN document_statuses ds ON ds.id = d.current_status_id
            WHERE ag.id IS NULL
            {$searchWhere}
            ORDER BY cor.resolved_at DESC, d.id DESC
            LIMIT ? OFFSET ?
        ");
        $listStmt->execute([...$searchParams, $perPage, $offset]);
        $documents = $listStmt->fetchAll();

        return [$total, $totalPages, $documents];
    }

    /**
     * WITHDRAWN tab: resolved as WITHDRAW_DOCUMENT.
     */
    private function queryWithdrawnTab(string $searchWhere, array $searchParams, int $perPage, int $offset): array
    {
        $countStmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT d.id) AS total
            FROM documents d
            INNER JOIN (
                SELECT document_id, MAX(id) AS max_cycle_id
                FROM committee_cycles WHERE completed_at IS NOT NULL
                GROUP BY document_id
            ) lc ON lc.document_id = d.id
            INNER JOIN committee_cycles cc ON cc.id = lc.max_cycle_id
            INNER JOIN committee_opinion_resolutions cor
                ON cor.cycle_id = cc.id AND cor.resolution = 'WITHDRAW_DOCUMENT'
            WHERE 1=1 {$searchWhere}
        ");
        $countStmt->execute($searchParams);
        $total      = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $perPage));

        $listStmt = $this->pdo->prepare("
            SELECT
                d.id            AS document_id,
                d.tracking_number,
                d.subject_matter,
                dt.name         AS document_type_name,
                dt.badge_color  AS document_type_badge_color,
                ds.name         AS status,
                ds.badge_color  AS status_badge_color,
                cc.id           AS cycle_id,
                cc.cycle_number,
                cor.resolved_at AS withdrawn_at,
                cor.remarks     AS withdrawal_remarks,
                COALESCE(
                    NULLIF(TRIM(CONCAT(COALESCE(ui.first_name,''),' ',COALESCE(ui.last_name,''))), ''),
                    ua.username, '—'
                ) AS withdrawn_by_name
            FROM documents d
            INNER JOIN (
                SELECT document_id, MAX(id) AS max_cycle_id
                FROM committee_cycles WHERE completed_at IS NOT NULL
                GROUP BY document_id
            ) lc ON lc.document_id = d.id
            INNER JOIN committee_cycles cc ON cc.id = lc.max_cycle_id
            INNER JOIN committee_opinion_resolutions cor
                ON cor.cycle_id = cc.id AND cor.resolution = 'WITHDRAW_DOCUMENT'
            LEFT JOIN document_types    dt  ON dt.id = d.document_type_id
            LEFT JOIN document_statuses ds  ON ds.id = d.current_status_id
            LEFT JOIN user_accounts     ua  ON ua.id = cor.resolved_by
            LEFT JOIN user_info         ui  ON ui.user_account_id = ua.id
            WHERE 1=1 {$searchWhere}
            ORDER BY cor.resolved_at DESC, d.id DESC
            LIMIT ? OFFSET ?
        ");
        $listStmt->execute([...$searchParams, $perPage, $offset]);
        $documents = $listStmt->fetchAll();

        return [$total, $totalPages, $documents];
    }

    // ── Tab badge counts ──────────────────────────────────────────────────────

    private function countReferredTab(): int
    {
        return (int) $this->pdo->query("
            SELECT COUNT(DISTINCT d.id)
            FROM documents d
            INNER JOIN (SELECT document_id, MAX(id) AS max_cycle_id FROM committee_cycles WHERE completed_at IS NOT NULL GROUP BY document_id) lc ON lc.document_id = d.id
            INNER JOIN committee_cycles cc ON cc.id = lc.max_cycle_id
            LEFT JOIN committee_cycle_endorsements cce ON cce.cycle_id = cc.id
            LEFT JOIN committee_opinion_resolutions cor ON cor.cycle_id = cc.id
            WHERE cce.id IS NULL AND cor.id IS NULL
        ")->fetchColumn();
    }

    private function countForOpinionTab(): int
    {
        return (int) $this->pdo->query("
            SELECT COUNT(DISTINCT d.id)
            FROM documents d
            INNER JOIN (SELECT document_id, MAX(id) AS max_cycle_id FROM committee_cycles WHERE completed_at IS NOT NULL GROUP BY document_id) lc ON lc.document_id = d.id
            INNER JOIN committee_cycles cc ON cc.id = lc.max_cycle_id
            INNER JOIN committee_cycle_endorsements cce ON cce.cycle_id = cc.id
            LEFT JOIN committee_opinion_resolutions cor ON cor.cycle_id = cc.id
            WHERE cor.id IS NULL
        ")->fetchColumn();
    }

    private function countReadyForAgendaTab(): int
    {
        return (int) $this->pdo->query("
            SELECT COUNT(DISTINCT d.id)
            FROM documents d
            INNER JOIN (SELECT document_id, MAX(id) AS max_cycle_id FROM committee_cycles WHERE completed_at IS NOT NULL GROUP BY document_id) lc ON lc.document_id = d.id
            INNER JOIN committee_cycles cc ON cc.id = lc.max_cycle_id
            INNER JOIN committee_opinion_resolutions cor ON cor.cycle_id = cc.id AND cor.resolution = 'PROCEED_TO_AGENDA'
            LEFT JOIN agendas ag ON ag.cycle_id = cc.id
            WHERE ag.id IS NULL
        ")->fetchColumn();
    }

    private function countWithdrawnTab(): int
    {
        return (int) $this->pdo->query("
            SELECT COUNT(DISTINCT d.id)
            FROM documents d
            INNER JOIN (SELECT document_id, MAX(id) AS max_cycle_id FROM committee_cycles WHERE completed_at IS NOT NULL GROUP BY document_id) lc ON lc.document_id = d.id
            INNER JOIN committee_cycles cc ON cc.id = lc.max_cycle_id
            INNER JOIN committee_opinion_resolutions cor ON cor.cycle_id = cc.id AND cor.resolution = 'WITHDRAW_DOCUMENT'
        ")->fetchColumn();
    }

    // =========================================================================
    // Private — data loaders / guards
    // =========================================================================

    /** Load document or redirect with error. */
    private function requireDocument(int $documentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT d.*, ds.name AS status, ds.badge_color AS status_badge_color
            FROM documents d
            LEFT JOIN document_statuses ds ON ds.id = d.current_status_id
            WHERE d.id = ? LIMIT 1
        ");
        $stmt->execute([$documentId]);
        $doc = $stmt->fetch();
        if (!$doc) {
            flash_set('error', 'Document not found.');
            redirect('committee/referred');
        }
        return $doc;
    }

    /**
     * Verify that $documentId has a completed committee cycle and that it
     * belongs to the Referred workflow (completed cycle exists).
     * Optionally verifies that $cycleId is the latest completed cycle.
     *
     * @return array{0: array, 1: array}  [$document, $cycle]
     */
    private function requireReferredDocument(int $documentId, int $cycleId = 0): array
    {
        $document = $this->requireDocument($documentId);

        // Load the latest completed cycle for this document
        $cycleStmt = $this->pdo->prepare("
            SELECT * FROM committee_cycles
            WHERE document_id = ? AND completed_at IS NOT NULL
            ORDER BY id DESC LIMIT 1
        ");
        $cycleStmt->execute([$documentId]);
        $cycle = $cycleStmt->fetch();

        if (!$cycle) {
            flash_set('error', 'No completed Committee cycle found for this document.');
            redirect('committee/referred');
        }

        // If a specific cycleId was provided, verify it matches the latest
        if ($cycleId > 0 && (int) $cycle['id'] !== $cycleId) {
            flash_set('error', 'The provided cycle ID does not match the latest completed cycle.');
            redirect('committee/referred');
        }

        return [$document, $cycle];
    }

    /** Load cycle or redirect. */
    private function requireCycle(int $cycleId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM committee_cycles WHERE id = ? LIMIT 1");
        $stmt->execute([$cycleId]);
        $cycle = $stmt->fetch();
        if (!$cycle) {
            flash_set('error', 'Committee cycle not found.');
            redirect('committee/referred');
        }
        return $cycle;
    }

    /** Load an endorsement row or redirect. */
    private function requireEndorsement(int $endorsementId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT cce.*, oo.name AS office_name, oo.abbreviation AS office_abbr,
                   os.name AS opinion_status_name
            FROM committee_cycle_endorsements cce
            LEFT JOIN opinion_offices oo ON oo.id = cce.opinion_office_id
            LEFT JOIN opinion_statuses os ON os.id = cce.opinion_status_id
            WHERE cce.id = ? LIMIT 1
        ");
        $stmt->execute([$endorsementId]);
        $row = $stmt->fetch();
        if (!$row) {
            flash_set('error', 'Endorsement not found.');
            redirect('committee/referred?tab=for_opinion');
        }
        return $row;
    }

    /** Load a document_statuses row by name or throw. */
    private function requireDocumentStatus(string $name): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, name, badge_color FROM document_statuses
            WHERE name = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1
        ");
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException(
                "Document status \"{$name}\" not found. Please run migration 054 to seed workflow statuses."
            );
        }
        return $row;
    }

    /** Load an opinion_statuses row by name or throw. */
    private function requireOpinionStatus(string $name): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, name FROM opinion_statuses WHERE name = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1
        ");
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException(
                "Opinion status \"{$name}\" not found. Please run migration 054 to seed opinion statuses."
            );
        }
        return $row;
    }

    /** Require and return the Committee role ID; redirect if not found. */
    private function requireCommitteeRoleId(): int
    {
        $stmt = $this->pdo->query(
            "SELECT id FROM roles WHERE name = 'Committee' AND is_active = 1 AND is_deleted = 0 LIMIT 1"
        );
        $row = $stmt->fetch();
        if (!$row) {
            flash_set('error', 'Committee role not found.');
            redirect('dashboard');
        }
        return (int) $row['id'];
    }

    /** Fetch all active opinion offices ordered by sort_order, name. */
    private function fetchOpinionOffices(): array
    {
        return $this->pdo->query("
            SELECT id, name, abbreviation
            FROM opinion_offices
            WHERE is_active = 1 AND is_deleted = 0
            ORDER BY sort_order ASC, name ASC
        ")->fetchAll();
    }

    /** All endorsements for a given cycle_id (no joins). */
    private function latestCycleEndorsements(int $cycleId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM committee_cycle_endorsements WHERE cycle_id = ?
        ");
        $stmt->execute([$cycleId]);
        return $stmt->fetchAll();
    }

    /** Endorsements for a cycle with office name + latest submission. */
    private function latestCycleEndorsementsWithOffice(int $cycleId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                cce.id              AS endorsement_id,
                cce.cycle_id,
                cce.opinion_office_id,
                cce.endorsement_number,
                cce.requested_at,
                cce.submitted_at,
                cce.remarks         AS endorsement_remarks,
                oo.name             AS office_name,
                oo.abbreviation     AS office_abbr,
                os.name             AS opinion_status_name,
                ces.opinion_type,
                ces.submitted_by,
                ces.submitted_at    AS submission_submitted_at,
                ces.remarks         AS submission_remarks
            FROM committee_cycle_endorsements cce
            LEFT JOIN opinion_offices oo ON oo.id = cce.opinion_office_id
            LEFT JOIN opinion_statuses os ON os.id = cce.opinion_status_id
            LEFT JOIN committee_endorsement_submissions ces ON ces.endorsement_id = cce.id
            WHERE cce.cycle_id = ?
            ORDER BY cce.opinion_office_id ASC, cce.endorsement_number ASC
        ");
        $stmt->execute([$cycleId]);
        return $stmt->fetchAll();
    }

    /** Load submission rows keyed by endorsement_id. */
    private function loadSubmissionsForEndorsements(array $endorsements): array
    {
        if (empty($endorsements)) return [];
        $ids   = array_column($endorsements, 'id');
        $ph    = implode(',', array_fill(0, count($ids), '?'));
        $stmt  = $this->pdo->prepare("
            SELECT * FROM committee_endorsement_submissions WHERE endorsement_id IN ({$ph})
        ");
        $stmt->execute($ids);
        $rows = $stmt->fetchAll();
        $map  = [];
        foreach ($rows as $r) {
            $map[$r['endorsement_id']] = $r;
        }
        return $map;
    }

    /**
     * Normalise a single-file $_FILES entry into a multi-file-style array.
     * The DocumentService's stageFileUploads() expects numeric sub-keys.
     */
    private function normaliseSingleFile(array $fileEntry): array
    {
        if (empty($fileEntry['name'])) {
            return ['name' => [], 'tmp_name' => [], 'error' => [], 'size' => [], 'type' => []];
        }
        // Already multi
        if (is_array($fileEntry['name'])) {
            return $fileEntry;
        }
        // Single — wrap in array
        return [
            'name'     => [$fileEntry['name']],
            'tmp_name' => [$fileEntry['tmp_name']],
            'error'    => [$fileEntry['error']],
            'size'     => [$fileEntry['size']],
            'type'     => [$fileEntry['type'] ?? ''],
        ];
    }

    private function isValidDate(string $date): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}

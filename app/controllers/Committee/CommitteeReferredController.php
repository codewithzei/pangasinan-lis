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

    public function __construct(?PDO $pdo = null, ?DocumentService $docService = null)
    {
        if ($pdo === null) {
            $database = new Database();
            $pdo = $database->connect();
        }

        $this->pdo        = $pdo;
        $this->docService = $docService ?? new DocumentService($pdo);
    }

    public function opinionStatusNameFor(string $opinionType, int $endorsementNumber): string
    {
        if ($endorsementNumber < 1) {
            throw new InvalidArgumentException('Endorsement number must be positive.');
        }
        if ($opinionType === 'FAVORABLE') {
            return 'Favorable';
        }
        if ($opinionType !== 'UNFAVORABLE' || $endorsementNumber === PHP_INT_MAX) {
            throw new InvalidArgumentException('Invalid opinion type or endorsement number.');
        }

        $nextNumber = $endorsementNumber + 1;
        $nextLabel = $nextNumber === 2 ? 'Second' : $this->endorsementOrdinal($nextNumber);
        return "For {$nextLabel} Endorsement";
    }

    public function endorsementOrdinal(int $number): string
    {
        if ($number < 1) {
            throw new InvalidArgumentException('Endorsement number must be positive.');
        }

        $lastTwoDigits = $number % 100;
        $suffix = ($lastTwoDigits >= 11 && $lastTwoDigits <= 13)
            ? 'th'
            : match ($number % 10) {
                1 => 'st',
                2 => 'nd',
                3 => 'rd',
                default => 'th',
            };

        return $number . $suffix;
    }

    /**
     * Summarize the latest endorsement for each office for the For Opinion tab.
     *
     * @return array{
     *     total:int, reviewed:int, cleared:int, awaiting_next:int, pending:int,
     *     offices:array<int, array<string, mixed>>
     * }
     */
    public function summarizeOpinionProgress(array $endorsements): array
    {
        $latestByOffice = [];
        $latestSubmittedByOffice = [];
        foreach ($endorsements as $endorsement) {
            $officeId = (int) ($endorsement['opinion_office_id'] ?? 0);
            if ($officeId <= 0) {
                continue;
            }

            $current = $latestByOffice[$officeId] ?? null;
            $number  = (int) ($endorsement['endorsement_number'] ?? 0);
            $currentNumber = (int) ($current['endorsement_number'] ?? 0);
            $endorsementId = (int) ($endorsement['endorsement_id'] ?? 0);
            $currentId     = (int) ($current['endorsement_id'] ?? 0);

            if ($current === null || $number > $currentNumber
                || ($number === $currentNumber && $endorsementId > $currentId)) {
                $latestByOffice[$officeId] = $endorsement;
            }

            if (!empty($endorsement['submission_id'])) {
                $submitted = $latestSubmittedByOffice[$officeId] ?? null;
                $submittedNumber = (int) ($submitted['endorsement_number'] ?? 0);
                $submittedId = (int) ($submitted['endorsement_id'] ?? 0);
                if ($submitted === null || $number > $submittedNumber
                    || ($number === $submittedNumber && $endorsementId > $submittedId)) {
                    $latestSubmittedByOffice[$officeId] = $endorsement;
                }
            }
        }

        $reviewed = 0;
        $cleared = 0;
        $awaitingNext = 0;
        $offices = [];
        $historyByOffice = [];

        foreach ($endorsements as $endorsement) {
            $officeId = (int) ($endorsement['opinion_office_id'] ?? 0);
            if ($officeId <= 0) {
                continue;
            }

            $opinionType = strtoupper(trim((string) ($endorsement['opinion_type'] ?? '')));
            $statusName = trim((string) ($endorsement['opinion_status_name'] ?? ''));
            if ($statusName === '' || $statusName === 'Pending') {
                $statusName = $opinionType !== ''
                    ? $this->opinionStatusNameFor(
                        $opinionType,
                        (int) ($endorsement['endorsement_number'] ?? 1)
                    )
                    : 'Pending';
            }

            $files = $endorsement['issue_files'] ?? ['opinion' => [], 'compliance' => []];
            $historyByOffice[$officeId][] = [
                'endorsement_number' => (int) ($endorsement['endorsement_number'] ?? 1),
                'opinion_type' => $opinionType,
                'status_name' => $statusName,
                'submitted_at' => $endorsement['submission_submitted_at'] ?? null,
                'submission_remarks' => trim((string) ($endorsement['submission_remarks'] ?? '')),
                'endorsement_remarks' => trim((string) ($endorsement['endorsement_remarks'] ?? '')),
                'files' => $files,
            ];
        }

        foreach ($latestByOffice as $officeId => $endorsement) {
            $hasSubmission = !empty($endorsement['submission_id']);
            $opinionType = strtoupper(trim((string) ($endorsement['opinion_type'] ?? '')));
            $statusName = trim((string) ($endorsement['opinion_status_name'] ?? ''));
            $endorsementNumber = (int) ($endorsement['endorsement_number'] ?? 1);
            $lastSubmitted = $latestSubmittedByOffice[$officeId] ?? null;
            $displayStatusName = $statusName;
            $displayOpinionType = $opinionType;

            if ($hasSubmission && ($displayStatusName === '' || $displayStatusName === 'Pending')) {
                $displayStatusName = $opinionType !== ''
                    ? $this->opinionStatusNameFor($opinionType, $endorsementNumber)
                    : $displayStatusName;
            } elseif (!$hasSubmission && $lastSubmitted !== null) {
                $displayStatusName = trim((string) ($lastSubmitted['opinion_status_name'] ?? ''));
                $displayOpinionType = strtoupper(trim((string) ($lastSubmitted['opinion_type'] ?? '')));

                if ($displayStatusName === '' || $displayStatusName === 'Pending') {
                    $lastSubmittedNumber = (int) ($lastSubmitted['endorsement_number'] ?? 1);
                    $displayStatusName = $displayOpinionType !== ''
                        ? $this->opinionStatusNameFor($displayOpinionType, $lastSubmittedNumber)
                        : $displayStatusName;
                }
            }
            $isCleared = $hasSubmission && $opinionType === 'FAVORABLE';
            $isAwaitingNext = $hasSubmission
                && $opinionType === 'UNFAVORABLE'
                && $displayStatusName === $this->opinionStatusNameFor('UNFAVORABLE', $endorsementNumber);

            $reviewed += $hasSubmission ? 1 : 0;
            $cleared += $isCleared ? 1 : 0;
            $awaitingNext += $isAwaitingNext ? 1 : 0;

            $offices[$officeId] = $endorsement + [
                'issue_history' => $historyByOffice[$officeId] ?? [],
                'cleared' => $isCleared,
                'reviewed' => $hasSubmission,
                'awaiting_next_endorsement' => $isAwaitingNext,
                'display_status_name' => $displayStatusName,
                'display_opinion_type' => $displayOpinionType,
                'awaiting_current_opinion' => !$hasSubmission && (int) ($endorsement['endorsement_id'] ?? 0) > 0,
                'last_submitted_endorsement_number' => $lastSubmitted !== null
                    ? (int) ($lastSubmitted['endorsement_number'] ?? 0)
                    : null,
                'pending_action' => !$hasSubmission
                    ? 'SUBMIT_OPINION'
                    : ($isAwaitingNext ? 'NEXT_ENDORSEMENT' : null),
            ];
        }

        $total = count($offices);
        return [
            'total' => $total,
            'reviewed' => $reviewed,
            'cleared' => $cleared,
            'awaiting_next' => $awaitingNext,
            'pending' => $total - $cleared,
            'offices' => $offices,
        ];
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

        $search         = trim($_GET['search']        ?? '');
        $filterDocType  = (int) ($_GET['doc_type']      ?? 0);
        $page           = max(1, (int) ($_GET['page']   ?? 1));
        $perPage        = 20;
        $offset         = ($page - 1) * $perPage;

        $total      = 0;
        $totalPages = 1;
        $documents  = [];

        // ── Search / filter clause helpers ────────────────────────────────────
        $filterConditions = [];
        $filterParams     = [];

        if ($search !== '') {
            $filterConditions[] = '(d.tracking_number LIKE ? OR d.subject_matter LIKE ?)';
            $filterParams[]     = "%{$search}%";
            $filterParams[]     = "%{$search}%";
        }

        // Document-type filter is applicable to every tab except for_opinion
        // (for_opinion has no doc-type column in its aggregate summary, but the
        // filter still applies because we JOIN document_types in every query).
        if ($filterDocType > 0) {
            $filterConditions[] = 'd.document_type_id = ?';
            $filterParams[]     = $filterDocType;
        }

        $filterWhere  = count($filterConditions) > 0
            ? ' AND ' . implode(' AND ', $filterConditions)
            : '';

        switch ($tab) {

            // ── REFERRED ──────────────────────────────────────────────────────
            // Documents with a completed committee cycle that have NOT yet been
            // sent to any opinion office (no committee_cycle_endorsements row
            // for the latest cycle) AND have not been resolved.
            case 'referred':
                [$total, $totalPages, $documents] = $this->queryReferredTab(
                    $filterWhere, $filterParams, $perPage, $offset
                );
                break;

            // ── FOR OPINION ───────────────────────────────────────────────────
            // Documents that have at least one endorsement for the latest cycle
            // and have not yet been resolved (no committee_opinion_resolutions row).
            case 'for_opinion':
                [$total, $totalPages, $documents] = $this->queryForOpinionTab(
                    $filterWhere, $filterParams, $perPage, $offset
                );
                break;

            // ── READY FOR AGENDA ──────────────────────────────────────────────
            // Documents resolved as PROCEED_TO_AGENDA but no agenda row yet.
            case 'ready_for_agenda':
                [$total, $totalPages, $documents] = $this->queryReadyForAgendaTab(
                    $filterWhere, $filterParams, $perPage, $offset
                );
                break;

            // ── WITHDRAWN ─────────────────────────────────────────────────────
            // Documents resolved as WITHDRAW_DOCUMENT.
            case 'withdrawn':
                [$total, $totalPages, $documents] = $this->queryWithdrawnTab(
                    $filterWhere, $filterParams, $perPage, $offset
                );
                break;
        }

        // ── Tab badge counts ──────────────────────────────────────────────────
        $referredCount       = $this->countReferredTab();
        $forOpinionCount     = $this->countForOpinionTab();
        $readyForAgendaCount = $this->countReadyForAgendaTab();
        $withdrawnCount      = $this->countWithdrawnTab();

        // ── Document-type dropdown (used by view filter) ─────────────────────
        // Only fetch types that actually appear in the referred workflow so
        // the dropdown stays relevant.  A simple lookup from document_types
        // is sufficient because the view will only show applicable options.
        $documentTypes = $this->pdo->query(
            "SELECT id, name FROM document_types
             WHERE is_active = 1 AND is_deleted = 0
             ORDER BY name ASC"
        )->fetchAll();

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
                $insEndorse->execute([$cycleId, $officeId, $remarks !== '' ? $remarks : null]);
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
                $remarks !== '' ? $remarks : 'Endorsed to opinion offices.',
                json_encode([
                    'cycle_id'             => $cycleId,
                    'opinion_office_ids'   => $validOfficeIds,
                    'endorsement_number'   => 1,
                    'remarks'              => $remarks,
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
                'Endorsed to opinion offices (endorsement #1): ' . ($remarks !== '' ? $remarks : '—'),
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
                'remarks'          => $remarks,
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

        // Load the latest submission so the recorded remarks and files remain visible.
        $submStmt = $this->pdo->prepare("
            SELECT * FROM committee_endorsement_submissions
            WHERE endorsement_id = ? ORDER BY id DESC LIMIT 1
        ");
        $submStmt->execute([$endorsementId]);
        $submission = $submStmt->fetch() ?: null;
        $alreadySubmitted = $submission !== null;
        $submittedFiles = ['opinion' => [], 'compliance' => []];
        $otherAttachments = [];

        if ($submission !== null) {
            $filesStmt = $this->pdo->prepare("
                SELECT da.id, da.file_name, da.stored_path, da.file_size, cesf.file_type
                FROM committee_endorsement_submission_files cesf
                INNER JOIN document_attachments da ON da.id = cesf.attachment_id
                WHERE cesf.submission_id = ?
                ORDER BY cesf.id
            ");
            $filesStmt->execute([$submission['id']]);
            foreach ($filesStmt->fetchAll() as $file) {
                $fileType = $file['file_type'] === 'COMPLIANCE' ? 'compliance' : 'opinion';
                $submittedFiles[$fileType][] = $file;
            }

            // Keep historical submissions readable if only their legacy FK columns exist.
            foreach ([
                'opinion' => 'opinion_file_attachment_id',
                'compliance' => 'compliance_file_attachment_id',
            ] as $fileType => $column) {
                $attachmentId = (int) ($submission[$column] ?? 0);
                $linkedIds = array_map('intval', array_column($submittedFiles[$fileType], 'id'));
                if ($attachmentId > 0 && !in_array($attachmentId, $linkedIds, true)) {
                    $legacyFileStmt = $this->pdo->prepare("
                        SELECT id, file_name, stored_path, file_size
                        FROM document_attachments WHERE id = ? AND document_id = ? LIMIT 1
                    ");
                    $legacyFileStmt->execute([$attachmentId, $document['id']]);
                    $legacyFile = $legacyFileStmt->fetch();
                    if ($legacyFile) {
                        $submittedFiles[$fileType][] = $legacyFile;
                    }
                }
            }

            $otherFilesStmt = $this->pdo->prepare("
                SELECT id, file_name, stored_path, file_size, attachment_type
                FROM document_attachments
                WHERE document_id = ?
                  AND attachment_type NOT IN ('OPINION_FILE', 'COMPLIANCE_FILE')
                ORDER BY id
            ");
            $otherFilesStmt->execute([$document['id']]);
            $otherAttachments = $otherFilesStmt->fetchAll();
        }

        // Any Unfavorable submission may be followed by another numbered endorsement.
        $canIssueNextEndorsement = false;
        $nextEndorsementExists = false;
        $nextEndorsementNumber = (int) $endorsement['endorsement_number'] + 1;
        if ($alreadySubmitted && $submission['opinion_type'] === 'UNFAVORABLE') {
            $expectedNextStatus = $this->opinionStatusNameFor(
                'UNFAVORABLE',
                (int) $endorsement['endorsement_number']
            );
            $statusIsAwaitingNext = ($endorsement['opinion_status_name'] ?? '') === $expectedNextStatus;
            $hasNext = $this->pdo->prepare("
                SELECT id FROM committee_cycle_endorsements
                WHERE cycle_id = ? AND opinion_office_id = ? AND endorsement_number = ? LIMIT 1
            ");
            $hasNext->execute([
                $cycle['id'],
                $endorsement['opinion_office_id'],
                $nextEndorsementNumber,
            ]);
            $nextEndorsementExists = (bool) $hasNext->fetch();
            $canIssueNextEndorsement = $statusIsAwaitingNext && !$nextEndorsementExists;
        }

        $officeRow = $this->pdo->prepare("
            SELECT id, name, abbreviation FROM opinion_offices WHERE id = ? LIMIT 1
        ");
        $officeRow->execute([$endorsement['opinion_office_id']]);
        $office = $officeRow->fetch();

        $pageTitle = 'Submit Opinion';
        require __DIR__ . '/../../../resources/views/committee/referred/opinion.php';
    }

    /** Show issue history, remarks, and submitted files for one opinion office. */
    public function opinionDetailsShow(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        $documentId = (int) ($_GET['id'] ?? 0);
        $officeId = (int) ($_GET['office_id'] ?? 0);
        if ($documentId <= 0 || $officeId <= 0) {
            flash_set('error', 'Invalid document or opinion office.');
            redirect('committee/referred?tab=for_opinion');
        }

        [$document, $cycle] = $this->requireReferredDocument($documentId);
        $endorsements = $this->latestCycleEndorsementsWithOffice((int) $cycle['id']);
        $endorsements = $this->loadOpinionIssueFiles($endorsements, $documentId);
        $progress = $this->summarizeOpinionProgress($endorsements);
        if (!isset($progress['offices'][$officeId])) {
            flash_set('error', 'The selected opinion office is not part of this Committee cycle.');
            redirect('committee/referred?tab=for_opinion');
        }
        $progress['offices'] = [$officeId => $progress['offices'][$officeId]];
        $progress['total'] = 1;
        $progress['cleared'] = !empty($progress['offices'][$officeId]['cleared']) ? 1 : 0;

        $pageTitle = 'Opinion Office Details';
        require __DIR__ . '/../../../resources/views/committee/referred/opinion-details.php';
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
        $opinionFiles     = $_FILES['opinion_files']    ?? $_FILES['opinion_file']    ?? [];
        $complianceFiles  = $_FILES['compliance_files'] ?? $_FILES['compliance_file'] ?? [];
        $hasOpinionFile   = !empty($opinionFiles['name'][0]) || !empty($opinionFiles['name']);
        $hasComplianceFile= !empty($complianceFiles['name'][0]) || !empty($complianceFiles['name']);

        // Normalise single-file $_FILES into multi-file format for reuse
        $opinionFileNorm    = $this->normaliseSingleFile($opinionFiles);
        $complianceFileNorm = $this->normaliseSingleFile($complianceFiles);

        if (!$hasOpinionFile) {
            $errors[] = 'At least one opinion file is required.';
        } else {
            $fileErrors = $this->docService->validateFileUploads($opinionFileNorm, true);
            $errors     = array_merge($errors, $fileErrors);
        }

        if ($opinionType === 'UNFAVORABLE') {
            if (!$hasComplianceFile) {
                $errors[] = 'At least one compliance file is required for an Unfavorable opinion.';
            } else {
                $compErrors = $this->docService->validateFileUploads($complianceFileNorm, true);
                $errors     = array_merge($errors, $compErrors);
            }
        } elseif ($hasComplianceFile) {
            $errors[] = 'Compliance files are only accepted when the opinion outcome is Unfavorable.';
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
            flash_set('error', 'Opinion already submitted for this endorsement. Issue the next endorsement to continue.');
            redirect('committee/referred?tab=for_opinion');
        }

        if (!empty($errors)) {
            flash_set('errors', $errors);
            flash_set('error', 'Please correct the errors below.');
            $namesForOld = static function (array $files): array {
                $names = $files['name'] ?? [];
                if (!is_array($names)) {
                    $names = [$names];
                }
                return array_map(
                    static fn($name): array => ['name' => (string) $name],
                    array_values(array_filter($names, static fn($name): bool => (string) $name !== ''))
                );
            };
            old_set([
                'opinion_type' => $opinionType,
                'remarks' => $remarks,
                'selected_files' => [
                    'opinion' => $namesForOld($opinionFiles),
                    'compliance' => $opinionType === 'UNFAVORABLE' ? $namesForOld($complianceFiles) : [],
                ],
            ]);
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

        $opinionStatusName  = $this->opinionStatusNameFor(
            $opinionType,
            (int) $endorsement['endorsement_number']
        );

        $auditData = null;
        try {
            $this->pdo->beginTransaction();
            $opinionStatus = $this->ensureOpinionStatus($opinionStatusName);

            // 1. Insert opinion file attachment
            $opPendingLogs = $this->docService->insertStagedAttachments(
                $opinionStaged, $documentId, $userId, 'COMMITTEE', 'OPINION_FILE'
            );
            $opinionAttachmentIds = array_map(
                static fn(array $log): int => (int) $log['attachment_id'],
                $opPendingLogs
            );
            $opinionAttachmentId = $opinionAttachmentIds[0] ?? 0;
            if ($opinionAttachmentId <= 0) {
                throw new RuntimeException('Opinion files could not be recorded.');
            }

            // 2. Insert compliance file attachment (UNFAVORABLE only)
            $compAttachmentId = null;
            $compPendingLogs  = [];
            if ($opinionType === 'UNFAVORABLE' && !empty($complianceStaged)) {
                $compPendingLogs  = $this->docService->insertStagedAttachments(
                    $complianceStaged, $documentId, $userId, 'COMMITTEE', 'COMPLIANCE_FILE'
                );
                $complianceAttachmentIds = array_map(
                    static fn(array $log): int => (int) $log['attachment_id'],
                    $compPendingLogs
                );
                $compAttachmentId = $complianceAttachmentIds[0] ?? null;
                if ($compAttachmentId === null) {
                    throw new RuntimeException('Compliance files could not be recorded.');
                }
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
                $remarks !== '' ? $remarks : null,
            ]);
            $submissionId = (int) $this->pdo->lastInsertId();

            $linkFile = $this->pdo->prepare("
                INSERT INTO committee_endorsement_submission_files
                    (submission_id, attachment_id, file_type)
                VALUES (?, ?, ?)
            ");
            foreach ($opinionAttachmentIds as $attachmentId) {
                $linkFile->execute([$submissionId, $attachmentId, 'OPINION']);
            }
            foreach ($complianceAttachmentIds ?? [] as $attachmentId) {
                $linkFile->execute([$submissionId, $attachmentId, 'COMPLIANCE']);
            }

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
                "Opinion submitted: {$opinionType}." . ($remarks !== '' ? "\nRemarks: {$remarks}" : ''),
                json_encode([
                    'submission_id'    => $submissionId,
                    'endorsement_id'   => $endorsementId,
                    'cycle_id'         => $cycle['id'],
                    'opinion_type'     => $opinionType,
                    'opinion_status'   => $opinionStatusName,
                    'remarks'          => $remarks,
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
                'remarks'        => $remarks,
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
        $successMessage = $opinionType === 'UNFAVORABLE'
            ? 'Opinion submitted. This office is now awaiting endorsement #'
                . ((int) $endorsement['endorsement_number'] + 1) . '.'
            : "Opinion submitted successfully ({$opinionType}).";
        flash_set('success', $successMessage);
        redirect('committee/referred?tab=for_opinion');
    }

    // =========================================================================
    // 6. Next endorsement — store
    //    Triggered from For Opinion after any Unfavorable submission.
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

        $sourceNumber = (int) $firstEndorsement['endorsement_number'];
        if ($sourceNumber < 1 || $sourceNumber >= PHP_INT_MAX) {
            flash_set('error', 'Unable to issue another endorsement because the endorsement number is invalid.');
            redirect('committee/referred?tab=for_opinion');
        }
        $nextNumber = $sourceNumber + 1;
        $subCheck = $this->pdo->prepare("
            SELECT opinion_type FROM committee_endorsement_submissions
            WHERE endorsement_id = ? ORDER BY id DESC LIMIT 1
        ");
        $subCheck->execute([$endorsementId]);
        $sourceSubmission = $subCheck->fetch();
        if (!$sourceSubmission || $sourceSubmission['opinion_type'] !== 'UNFAVORABLE') {
            flash_set('error', 'A next endorsement is only allowed after an Unfavorable opinion.');
            redirect('committee/referred?tab=for_opinion');
        }
        $expectedNextStatus = $this->opinionStatusNameFor('UNFAVORABLE', $sourceNumber);
        if (($firstEndorsement['opinion_status_name'] ?? '') !== $expectedNextStatus) {
            flash_set('error', 'This office is not awaiting another endorsement.');
            redirect('committee/referred?tab=for_opinion');
        }

        // Prevent duplicate next endorsement
        $dupStmt = $this->pdo->prepare("
            SELECT id FROM committee_cycle_endorsements
            WHERE cycle_id = ? AND opinion_office_id = ? AND endorsement_number = ? LIMIT 1
        ");
        $dupStmt->execute([$cycle['id'], $firstEndorsement['opinion_office_id'], $nextNumber]);
        if ($dupStmt->fetch()) {
            flash_set('error', "Endorsement #{$nextNumber} already exists for this office and cycle.");
            redirect('committee/referred?tab=for_opinion');
        }

        // Check no submission yet on this new endorsement
        try {
            $this->pdo->beginTransaction();

            $this->pdo->prepare("
                INSERT INTO committee_cycle_endorsements
                    (cycle_id, opinion_office_id, endorsement_number,
                     opinion_status_id, requested_at, remarks)
                VALUES (?, ?, ?, NULL, NOW(), ?)
            ")->execute([
                $cycle['id'],
                $firstEndorsement['opinion_office_id'],
                $nextNumber,
                $remarks !== '' ? $remarks : null,
            ]);

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
                "Endorsement #{$nextNumber} issued to opinion office."
                    . ($remarks !== '' ? "\nRemarks: {$remarks}" : ''),
                json_encode([
                    'original_endorsement_id' => $endorsementId,
                    'new_endorsement_id'       => $newEndorsementId,
                    'cycle_id'                 => $cycle['id'],
                    'endorsement_number'       => $nextNumber,
                    'office_id'                => $firstEndorsement['opinion_office_id'],
                    'remarks'                  => $remarks,
                    'issued_by'                => $userId,
                    'issued_by_username'       => $username,
                    'ip_address'               => client_ip(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            $this->pdo->commit();

            audit_log('UPDATE', 'Document', (string) $documentId, null, [
                'action'            => 'committee_next_opinion_endorsement',
                'new_endorsement_id'=> $newEndorsementId,
                'original_endorsement_id' => $endorsementId,
                'endorsement_number' => $nextNumber,
                'office_id'         => $firstEndorsement['opinion_office_id'],
                'cycle_id'          => $cycle['id'],
                'remarks'           => $remarks,
            ], "Endorsement #{$nextNumber} issued for document ID {$documentId} by {$username}");

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            system_log('ERROR', 'secondEndorseStore exception', ['error' => $e->getMessage()]);
            flash_set('error', $e->getMessage());
            redirect('committee/referred?tab=for_opinion');
        }

        flash_set('success', "Endorsement #{$nextNumber} issued. Opinion can now be submitted.");
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
        $endorsements = $this->latestCycleEndorsementsWithOffice((int) $cycle['id']);
        $submissions  = $this->loadSubmissionsForEndorsements($endorsements);

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
            ")->execute([$cycleId, $resolution, $userId, $remarks !== '' ? $remarks : null]);

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
                    ? 'Document withdrawn by Committee decision.' . ($remarks !== '' ? "\nRemarks: {$remarks}" : '')
                    : 'Committee resolved to proceed to agenda.' . ($remarks !== '' ? "\nRemarks: {$remarks}" : ''),
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
                'Resolution: ' . $resolution . ($remarks !== '' ? (' — ' . $remarks) : ''),
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
        $isResetReschedule = $this->isResetReadyForAgenda($documentId, (int) $cycle['id']);

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

        // An existing agenda is allowed only for a RESET record being rescheduled.
        $existingAgenda = $this->pdo->prepare("
            SELECT id FROM agendas WHERE cycle_id = ? LIMIT 1
        ");
        $existingAgenda->execute([$cycle['id']]);
        if ($existingAgenda->fetch() && !$isResetReschedule) {
            flash_set('error', 'An agenda has already been scheduled for this cycle.');
            redirect('committee/referred?tab=ready_for_agenda');
        }

        // Keep the Plenary-assigned committee selectable if it has since been deactivated.
        $committeeStmt = $this->pdo->prepare("
            SELECT c.id, c.name
            FROM committees c
            WHERE (c.is_active = 1 AND c.is_deleted = 0)
               OR EXISTS (
                    SELECT 1 FROM document_committees dc
                    WHERE dc.document_id = ? AND dc.committee_id = c.id
               )
            ORDER BY c.name ASC
        ");
        $committeeStmt->execute([$documentId]);
        $allCommittees = $committeeStmt->fetchAll();

        $acStmt = $this->pdo->prepare(
            "SELECT committee_id FROM document_committees WHERE document_id = ?"
        );
        $acStmt->execute([$documentId]);
        $assignedCommitteeIds = array_column($acStmt->fetchAll(), 'committee_id');

        // Load SP members
        $spMembers = $this->docService->getSpMembers();

        $pageTitle = $isResetReschedule ? 'Reschedule Agenda' : 'Schedule Agenda';
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
        $isResetReschedule = $this->isResetReadyForAgenda($documentId, (int) $cycle['id']);

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

        // Existing agenda history is retained; RESET permits a new agenda entry.
        $existingAgendaStmt = $this->pdo->prepare("SELECT id FROM agendas WHERE cycle_id = ? LIMIT 1");
        $existingAgendaStmt->execute([$cycleId]);
        if ($existingAgendaStmt->fetch() && !$isResetReschedule) {
            flash_set('error', 'An agenda has already been scheduled for this cycle.');
            redirect('committee/referred?tab=ready_for_agenda');
        }

        // ── Validate committee & chairperson IDs ──────────────────────────────
        if (!empty($committeeIds)) {
            $cPh  = implode(',', array_fill(0, count($committeeIds), '?'));
            $cChk = $this->pdo->prepare(
                "SELECT c.id FROM committees c
                 WHERE c.id IN ({$cPh})
                   AND (
                        (c.is_active = 1 AND c.is_deleted = 0)
                        OR EXISTS (
                            SELECT 1 FROM document_committees dc
                            WHERE dc.document_id = ?
                              AND dc.committee_id = c.id
                        )
                   )"
            );
            $cChk->execute([...$committeeIds, $documentId]);
            $validCIds = array_column($cChk->fetchAll(), 'id');
            if (count($validCIds) !== count($committeeIds)) {
                flash_set('error', 'One or more selected committees are invalid.');
                redirect('committee/referred/agenda?id=' . $documentId);
            }
        }
        if (!empty($chairpersonIds)) {
            $spPh  = implode(',', array_fill(0, count($chairpersonIds), '?'));
            $spChk = $this->pdo->prepare(
                "SELECT sm.sp_member_id
                 FROM sp_members sm
                 WHERE sm.sp_member_id IN ({$spPh})
                   AND sm.is_active = 1 AND sm.is_deleted = 0
                   AND EXISTS (
                       SELECT 1
                       FROM term_legislators tl
                       INNER JOIN terms t ON t.id = tl.term_id
                       WHERE tl.sp_member_id = sm.sp_member_id
                         AND t.is_active = 1 AND t.is_deleted = 0
                   )"
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
                $remarks !== '' ? $remarks : null,
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
                $isResetReschedule
                    ? 'Agenda rescheduled after reset. Document status set to On Going.'
                    : 'Agenda scheduled. Document status set to On Going.',
                json_encode([
                    'agenda_id'       => $agendaId,
                    'cycle_id'        => $cycleId,
                    'rescheduled_after_reset' => $isResetReschedule,
                    'agenda_date'     => $agendaDate,
                    'agenda_time'     => $agendaTime,
                    'remarks'         => $remarks,
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
            ], ($isResetReschedule ? 'Agenda rescheduled' : 'Agenda scheduled')
                . " for document ID {$documentId} by {$username}; status set to On Going");
            system_log('INFO', $isResetReschedule
                ? 'Agenda rescheduled after hearing reset; document status → On Going'
                : 'Agenda scheduled; document status → On Going', [
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
        if ($isResetReschedule) {
            flash_set('success', 'Agenda rescheduled successfully. Document status updated to On Going.');
            redirect('committee/hearing?tab=scheduled');
        }
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
    private function queryReferredTab(string $filterWhere, array $filterParams, int $perPage, int $offset): array
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
            {$filterWhere}
        ");
        $countStmt->execute($filterParams);
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
            {$filterWhere}
            ORDER BY de.created_at DESC, d.id DESC
            LIMIT ? OFFSET ?
        ");
        $listStmt->execute([...$filterParams, $perPage, $offset]);
        $documents = $listStmt->fetchAll();

        return [$total, $totalPages, $documents];
    }

    /**
     * FOR OPINION tab: documents with endorsements for the latest cycle,
     * no resolution yet.
     */
    private function queryForOpinionTab(string $filterWhere, array $filterParams, int $perPage, int $offset): array
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
            {$filterWhere}
        ");
        $countStmt->execute($filterParams);
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
            {$filterWhere}
            GROUP BY d.id, d.tracking_number, d.subject_matter,
                     dt.name, dt.badge_color, ds.name, ds.badge_color,
                     cc.id, cc.cycle_number
            ORDER BY d.id DESC
            LIMIT ? OFFSET ?
        ");
        $listStmt->execute([...$filterParams, $perPage, $offset]);
        $documents = $listStmt->fetchAll();

        // Build the per-office state consumed by the For Opinion view.
        foreach ($documents as &$doc) {
            $endorsements = $this->latestCycleEndorsementsWithOffice((int) $doc['cycle_id']);
            $doc['progress'] = $this->summarizeOpinionProgress($endorsements);
        }
        unset($doc);

        return [$total, $totalPages, $documents];
    }

    /**
     * READY FOR AGENDA tab: resolved as PROCEED_TO_AGENDA, no agenda yet.
     */
    private function queryReadyForAgendaTab(string $filterWhere, array $filterParams, int $perPage, int $offset): array
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
            {$filterWhere}
        ");
        $countStmt->execute($filterParams);
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
            {$filterWhere}
            ORDER BY cor.resolved_at DESC, d.id DESC
            LIMIT ? OFFSET ?
        ");
        $listStmt->execute([...$filterParams, $perPage, $offset]);
        $documents = $listStmt->fetchAll();

        return [$total, $totalPages, $documents];
    }

    /**
     * WITHDRAWN tab: resolved as WITHDRAW_DOCUMENT.
     */
    private function queryWithdrawnTab(string $filterWhere, array $filterParams, int $perPage, int $offset): array
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
            WHERE 1=1 {$filterWhere}
        ");
        $countStmt->execute($filterParams);
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
            WHERE 1=1 {$filterWhere}
            ORDER BY cor.resolved_at DESC, d.id DESC
            LIMIT ? OFFSET ?
        ");
        $listStmt->execute([...$filterParams, $perPage, $offset]);
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
            SELECT d.*,
                   dt.name AS document_type_name,
                   dt.badge_color AS document_type_badge_color,
                   ds.name AS status,
                   ds.badge_color AS status_badge_color
            FROM documents d
            LEFT JOIN document_types dt ON dt.id = d.document_type_id
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

    private function isResetReadyForAgenda(int $documentId, int $cycleId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM documents d
            INNER JOIN document_statuses ds ON ds.id = d.current_status_id
            INNER JOIN agendas ag
                ON ag.document_id = d.id
                AND ag.cycle_id = ?
                AND ag.id = (
                    SELECT MAX(ag2.id)
                    FROM agendas ag2
                    WHERE ag2.document_id = d.id
                )
            INNER JOIN committee_hearings ch
                ON ch.document_id = d.id
                AND ch.agenda_id = ag.id
                AND ch.id = (
                    SELECT MAX(ch2.id)
                    FROM committee_hearings ch2
                    WHERE ch2.agenda_id = ag.id
                )
                AND ch.outcome = 'RESET'
            WHERE d.id = ? AND ds.name = 'Ready for Agenda'
            LIMIT 1
        ");
        $stmt->execute([$cycleId, $documentId]);
        return $stmt->fetchColumn() !== false;
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

    /**
     * Resolve an opinion status and create its workflow label if this is a new
     * numbered follow-up state (for example, "For 3rd Endorsement").
     */
    private function ensureOpinionStatus(string $name): array
    {
        if ($name === 'Favorable') {
            return $this->requireOpinionStatus($name);
        }

        $description = "Unfavorable opinion submitted; awaiting {$name}.";

        $sortOrder = preg_match('/^For (\d+)(?:st|nd|rd|th) Endorsement$/', $name, $matches)
            ? 20 + min((int) $matches[1], 1000000)
            : 30;

        $this->pdo->prepare("
            INSERT INTO opinion_statuses
                (name, description, sort_order, is_active, is_deleted)
            VALUES (?, ?, ?, 1, 0)
            ON DUPLICATE KEY UPDATE
                id = LAST_INSERT_ID(id),
                description = VALUES(description),
                sort_order = VALUES(sort_order),
                is_active = 1,
                is_deleted = 0
        ")->execute([$name, $description, $sortOrder]);

        return $this->requireOpinionStatus($name);
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
                cce.id              AS id,
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
                ces.id              AS submission_id,
                ces.opinion_type,
                ces.opinion_file_attachment_id,
                ces.compliance_file_attachment_id,
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

    /** Load each issue's Opinion and Compliance files, including legacy file links. */
    private function loadOpinionIssueFiles(array $endorsements, int $documentId): array
    {
        $submissionIds = [];
        $legacyLinks = [];
        $attachmentIds = [];

        foreach ($endorsements as $endorsement) {
            $submissionId = (int) ($endorsement['submission_id'] ?? 0);
            if ($submissionId <= 0) {
                continue;
            }
            $submissionIds[] = $submissionId;

            foreach ([
                'opinion' => 'opinion_file_attachment_id',
                'compliance' => 'compliance_file_attachment_id',
            ] as $fileType => $column) {
                $attachmentId = (int) ($endorsement[$column] ?? 0);
                if ($attachmentId > 0) {
                    $legacyLinks[] = [
                        'submission_id' => $submissionId,
                        'attachment_id' => $attachmentId,
                        'file_type' => $fileType,
                    ];
                    $attachmentIds[] = $attachmentId;
                }
            }
        }

        $filesBySubmission = [];
        $submissionIds = array_values(array_unique($submissionIds));
        if ($submissionIds !== []) {
            $placeholders = implode(',', array_fill(0, count($submissionIds), '?'));
            $filesStmt = $this->pdo->prepare("
                SELECT cesf.submission_id, cesf.file_type,
                       da.id, da.file_name, da.stored_path, da.file_size
                FROM committee_endorsement_submission_files cesf
                INNER JOIN document_attachments da ON da.id = cesf.attachment_id
                WHERE cesf.submission_id IN ({$placeholders})
                ORDER BY cesf.id
            ");
            $filesStmt->execute($submissionIds);
            foreach ($filesStmt->fetchAll() as $file) {
                $fileType = strtolower((string) $file['file_type']);
                $filesBySubmission[(int) $file['submission_id']][$fileType][] = $file;
            }
        }

        $attachmentIds = array_values(array_unique($attachmentIds));
        if ($attachmentIds !== []) {
            $placeholders = implode(',', array_fill(0, count($attachmentIds), '?'));
            $legacyStmt = $this->pdo->prepare("
                SELECT id, file_name, stored_path, file_size
                FROM document_attachments
                WHERE id IN ({$placeholders}) AND document_id = ?
            ");
            $legacyStmt->execute([...$attachmentIds, $documentId]);
            $attachmentsById = [];
            foreach ($legacyStmt->fetchAll() as $file) {
                $attachmentsById[(int) $file['id']] = $file;
            }

            foreach ($legacyLinks as $link) {
                $file = $attachmentsById[$link['attachment_id']] ?? null;
                if ($file === null) {
                    continue;
                }
                $existingIds = array_map(
                    'intval',
                    array_column(
                        $filesBySubmission[$link['submission_id']][$link['file_type']] ?? [],
                        'id'
                    )
                );
                if (!in_array($link['attachment_id'], $existingIds, true)) {
                    $filesBySubmission[$link['submission_id']][$link['file_type']][] = $file;
                }
            }
        }

        foreach ($endorsements as &$endorsement) {
            $submissionId = (int) ($endorsement['submission_id'] ?? 0);
            $endorsement['issue_files'] = [
                'opinion' => $filesBySubmission[$submissionId]['opinion'] ?? [],
                'compliance' => $filesBySubmission[$submissionId]['compliance'] ?? [],
            ];
        }
        unset($endorsement);

        return $endorsements;
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

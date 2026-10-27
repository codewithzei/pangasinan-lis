<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/DocumentService.php';

/**
 * SpsecCommFirstReadingController
 *
 * Handles the SP Secretary Communications First Reading queue and the
 * First Reading decision form.
 *
 * ROUTE MAP
 * ─────────────────────────────────────────────────────────────────────────────
 *  GET  spsec/comm-first-reading          index()  — queue list
 *  GET  spsec/comm-first-reading/show     show()   — detail / decision form
 *  POST spsec/comm-first-reading/store    store()  — process decision
 *
 * DECISIONS
 * ─────────────────────────────────────────────────────────────────────────────
 *  • NOTED       — requires communication category; routes to Plenary Communications.
 *  • REFERRED    — requires 1+ committees; routes to Committee workflow.
 *  • APPROVED    — optional proposed_number, required resolution_number, 1+ files.
 *                  Routes to Plenary Communications (finalized state).
 *
 * AUTHORIZATION
 * ─────────────────────────────────────────────────────────────────────────────
 *  All routes: AuthMiddleware + RoleMiddleware (SP Secretary / Super Admin).
 *  Decision writes additionally verify the document is in "Comm: Awaiting First Reading".
 */
class SpsecCommFirstReadingController
{
    protected PDO             $pdo;
    protected DocumentService $docService;

    private const PER_PAGE   = 20;
    private const MAX_FILES  = 10;
    private const MAX_SIZE   = 25 * 1024 * 1024; // 25 MB
    private const ALLOWED_EXT  = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const ALLOWED_MIME = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    public function __construct()
    {
        $db               = new Database();
        $this->pdo        = $db->connect();
        $this->docService = new DocumentService();
    }

    // =========================================================================
    // 1. Index
    // =========================================================================

    public function index(): void
    {
        $userId = auth_id();
        if ($userId === null) { flash_set('error', 'You must be logged in.'); redirect('login'); }

        $this->requireSpsecAccess();

        $search = trim($_GET['search'] ?? '');
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $offset = ($page - 1) * self::PER_PAGE;

        $awaitingStatus = $this->requireStatus('Comm: Awaiting First Reading');

        $where  = ["d.current_status_id = ?"];
        $params = [$awaitingStatus['id']];

        if ($search !== '') {
            $where[]  = "(d.tracking_number LIKE ? OR d.subject_matter LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $whereStr = implode(' AND ', $where);

        $cntStmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM documents d
            INNER JOIN document_types dt ON dt.id = d.document_type_id AND dt.name LIKE '%Communication%' AND dt.is_deleted = 0
            WHERE {$whereStr}
        ");
        $cntStmt->execute($params);
        $total      = (int) $cntStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page       = min($page, $totalPages);

        $listStmt = $this->pdo->prepare("
            SELECT
                d.id,
                d.tracking_number,
                d.subject_matter,
                d.date_received,
                d.current_status_id,
                ds.name            AS status,
                ds.badge_color     AS status_badge_color,
                dt.name            AS document_type_name,
                dt.badge_color     AS document_type_badge_color,
                cii.sent_to_fr_at,
                su.username        AS sent_by_username
            FROM documents d
            INNER JOIN document_types dt  ON dt.id  = d.document_type_id AND dt.name LIKE '%Communication%' AND dt.is_deleted = 0
            LEFT  JOIN document_statuses ds  ON ds.id  = d.current_status_id
            LEFT  JOIN comm_inbox_items  cii ON cii.document_id = d.id
            LEFT  JOIN user_accounts     su  ON su.id = cii.sent_to_fr_by
            WHERE {$whereStr}
            ORDER BY cii.sent_to_fr_at ASC, d.id ASC
            LIMIT ? OFFSET ?
        ");
        $listStmt->execute([...$params, self::PER_PAGE, $offset]);
        $documents = $listStmt->fetchAll();

        $pageTitle = 'Communications — First Reading';
        $success   = flash_get('success');
        $error     = flash_get('error');
        $errors    = flash_get('errors', []);

        require __DIR__ . '/../../../resources/views/spsec/comm-first-reading/index.php';
    }

    // =========================================================================
    // 2. Show (decision form)
    // =========================================================================

    public function show(): void
    {
        $userId     = auth_id();
        $documentId = (int) ($_GET['id'] ?? 0);

        if ($userId === null) { flash_set('error', 'You must be logged in.'); redirect('login'); }
        if ($documentId <= 0) { flash_set('error', 'Invalid document ID.'); redirect('spsec/comm-first-reading'); }

        $this->requireSpsecAccess();

        $document = $this->fetchCommDocument($documentId);
        if (!$document) {
            flash_set('error', 'Communication document not found.');
            redirect('spsec/comm-first-reading');
        }

        $awaitingStatus = $this->requireStatus('Comm: Awaiting First Reading');
        if ((int) $document['current_status_id'] !== (int) $awaitingStatus['id']) {
            flash_set('error', 'This document is not in the First Reading queue.');
            redirect('spsec/comm-first-reading');
        }

        $inboxItem    = $this->fetchInboxItem($documentId);
        $firstReading = $this->fetchFirstReading($documentId);

        // Dropdown data
        $categories = $this->pdo->query("
            SELECT id, name FROM communication_categories WHERE is_active = 1 AND is_deleted = 0 ORDER BY name ASC
        ")->fetchAll();

        $committees = $this->pdo->query("
            SELECT id, name FROM committees WHERE is_active = 1 AND is_deleted = 0 ORDER BY name ASC
        ")->fetchAll();

        $pageTitle = 'First Reading — ' . htmlspecialchars($document['tracking_number'] ?? '');
        $success   = flash_get('success');
        $error     = flash_get('error');
        $errors    = flash_get('errors', []);
        $old       = old_get();

        require __DIR__ . '/../../../resources/views/spsec/comm-first-reading/show.php';
    }

    // =========================================================================
    // 3. Store (process decision)
    // =========================================================================

    public function store(): void
    {
        $userId = auth_id();
        if ($userId === null) { flash_set('error', 'You must be logged in.'); redirect('login'); }

        $this->requireSpsecAccess();

        $documentId   = (int) ($_POST['document_id']   ?? 0);
        $decision     = strtoupper(trim($_POST['decision'] ?? ''));
        $readingDate  = trim($_POST['reading_date']    ?? '');
        $remarks      = trim($_POST['remarks']         ?? '');

        if ($documentId <= 0) { flash_set('error', 'Invalid document ID.'); redirect('spsec/comm-first-reading'); }

        $validDecisions = ['NOTED', 'REFERRED', 'APPROVED'];
        if (!in_array($decision, $validDecisions, true)) {
            flash_set('error', 'Invalid decision selected.');
            redirect('spsec/comm-first-reading/show?id=' . $documentId);
        }

        // Decision-specific inputs
        $categoryId       = (int) ($_POST['communication_category_id'] ?? 0);
        $committeeIds     = array_filter(array_map('intval', (array) ($_POST['committee_ids'] ?? [])));
        $proposedNumber   = trim($_POST['proposed_number']   ?? '');
        $resolutionNumber = trim($_POST['resolution_number'] ?? '');

        // Validate
        $errors = [];
        if ($readingDate === '' || !$this->isValidDate($readingDate)) {
            $errors[] = 'A valid reading date is required.';
        }
        if ($decision === 'NOTED' && $categoryId <= 0) {
            $errors[] = 'A communication category is required for "Noted" decisions.';
        }
        if ($decision === 'REFERRED' && empty($committeeIds)) {
            $errors[] = 'At least one committee must be selected for referral.';
        }
        if ($decision === 'APPROVED') {
            if ($resolutionNumber === '') {
                $errors[] = 'A resolution number is required for "Approved for Resolution" decisions.';
            }
        }

        // Stage files for APPROVED
        $stagedFiles = [];
        if ($decision === 'APPROVED') {
            $uploadedFiles = $_FILES['resolution_files'] ?? ['name' => ['']];
            $fileErrors    = $this->validateMultipleFiles($uploadedFiles, true);
            $errors        = array_merge($errors, $fileErrors);

            if (empty($errors)) {
                try {
                    $stagedFiles = $this->stageResolutionFiles($uploadedFiles, $documentId);
                } catch (Throwable $e) {
                    system_log('ERROR', 'SpsecCommFirstReadingController::store file staging failed', [
                        'document_id' => $documentId,
                        'error'       => $e->getMessage(),
                    ]);
                    $errors[] = 'Failed to process uploaded files: ' . $e->getMessage();
                }
            }
        }

        if (!empty($errors)) {
            flash_set('errors', $errors);
            old_set($_POST);
            redirect('spsec/comm-first-reading/show?id=' . $documentId);
        }

        // Verify document is still in awaiting state (race-condition guard)
        $document = $this->fetchCommDocument($documentId);
        if (!$document) {
            flash_set('error', 'Document not found.');
            redirect('spsec/comm-first-reading');
        }

        $awaitingStatus = $this->requireStatus('Comm: Awaiting First Reading');
        if ((int) $document['current_status_id'] !== (int) $awaitingStatus['id']) {
            flash_set('error', 'This document is no longer in the First Reading queue.');
            redirect('spsec/comm-first-reading');
        }

        $inboxItem = $this->fetchInboxItem($documentId);
        if (!$inboxItem) {
            flash_set('error', 'Inbox record not found for this document.');
            redirect('spsec/comm-first-reading');
        }

        // Duplicate guard
        $dupCheck = $this->pdo->prepare("SELECT id FROM comm_first_reading WHERE document_id = ? LIMIT 1");
        $dupCheck->execute([$documentId]);
        if ($dupCheck->fetch()) {
            flash_set('error', 'A First Reading decision has already been recorded for this communication.');
            redirect('spsec/comm-first-reading');
        }

        // Determine target status by decision
        $statusMap = [
            'NOTED'    => 'Comm: Noted',
            'REFERRED' => 'Comm: Referred to Committee',
            'APPROVED' => 'Comm: Approved for Resolution',
        ];
        $eventMap = [
            'NOTED'    => 'COMM_FIRST_READING_NOTED',
            'REFERRED' => 'COMM_FIRST_READING_REFERRED',
            'APPROVED' => 'COMM_FIRST_READING_APPROVED',
        ];

        $targetStatus = $this->requireStatus($statusMap[$decision]);
        $prevStatusId = (int) ($document['current_status_id'] ?? 0);

        $firstReadingId = null;

        try {
            $this->pdo->beginTransaction();

            // 1. Insert comm_first_reading
            $this->pdo->prepare("
                INSERT INTO comm_first_reading
                    (document_id, inbox_item_id, decision, reading_date, remarks,
                     communication_category_id, proposed_number, resolution_number,
                     performed_by, performed_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ")->execute([
                $documentId,
                (int) $inboxItem['id'],
                $decision,
                $readingDate,
                $remarks ?: null,
                $decision === 'NOTED'    ? $categoryId : null,
                $decision === 'APPROVED' ? ($proposedNumber ?: null) : null,
                $decision === 'APPROVED' ? $resolutionNumber : null,
                $userId,
            ]);
            $firstReadingId = (int) $this->pdo->lastInsertId();

            // 2. Committee assignments for REFERRED
            if ($decision === 'REFERRED') {
                $insComm = $this->pdo->prepare("
                    INSERT IGNORE INTO comm_committee_assignments (first_reading_id, document_id, committee_id)
                    VALUES (?, ?, ?)
                ");
                foreach ($committeeIds as $cid) {
                    $insComm->execute([$firstReadingId, $documentId, $cid]);
                }

                // Also update document_committees table so the document appears
                // in the committee workflow pages
                $this->pdo->prepare("DELETE FROM document_committees WHERE document_id = ?")
                    ->execute([$documentId]);
                $insDC = $this->pdo->prepare("
                    INSERT IGNORE INTO document_committees (document_id, committee_id)
                    VALUES (?, ?)
                ");
                foreach ($committeeIds as $cid) {
                    $insDC->execute([$documentId, $cid]);
                }
            }

            // 3. For NOTED: update communication_category_id on the document
            if ($decision === 'NOTED' && $categoryId > 0) {
                $this->pdo->prepare("
                    UPDATE documents SET communication_category_id = ?, updated_by = ? WHERE id = ?
                ")->execute([$categoryId, $userId, $documentId]);
            }

            // 4. Update document status
            $this->pdo->prepare("
                UPDATE documents SET current_status_id = ?, updated_by = ? WHERE id = ?
            ")->execute([$targetStatus['id'], $userId, $documentId]);

            // 5. Document event
            $meta = [
                'decision'     => $decision,
                'reading_date' => $readingDate,
                'ip_address'   => client_ip(),
                'user_agent'   => client_user_agent(),
            ];
            if ($decision === 'NOTED')    { $meta['category_id'] = $categoryId; }
            if ($decision === 'REFERRED') { $meta['committee_ids'] = $committeeIds; }
            if ($decision === 'APPROVED') {
                $meta['resolution_number'] = $resolutionNumber;
                $meta['proposed_number']   = $proposedNumber;
            }

            $this->pdo->prepare("
                INSERT INTO document_events
                    (document_id, event_type, phase, performed_by, from_status_id, to_status_id, remarks, metadata)
                VALUES (?, ?, 'SP_SECRETARY', ?, ?, ?, ?, ?)
            ")->execute([
                $documentId,
                $eventMap[$decision],
                $userId,
                $prevStatusId ?: null,
                $targetStatus['id'],
                $remarks ?: "Comm First Reading: {$decision}",
                json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            // 6. Persist staged resolution files (APPROVED)
            if ($decision === 'APPROVED' && !empty($stagedFiles)) {
                $this->persistResolutionFiles($stagedFiles, $documentId, $firstReadingId, null, $userId);
            }

            $this->pdo->commit();

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            // Clean up staged files on failure
            foreach ($stagedFiles as $sf) {
                if (!empty($sf['tmp_stored']) && file_exists($sf['tmp_stored'])) {
                    @unlink($sf['tmp_stored']);
                }
            }
            system_log('ERROR', 'SpsecCommFirstReadingController::store failed', [
                'document_id' => $documentId,
                'decision'    => $decision,
                'user_id'     => $userId,
                'error'       => $e->getMessage(),
            ]);
            flash_set('error', 'Failed to record First Reading decision: ' . $e->getMessage());
            redirect('spsec/comm-first-reading/show?id=' . $documentId);
        }

        $uStmt = $this->pdo->prepare("SELECT username FROM user_accounts WHERE id = ? LIMIT 1");
        $uStmt->execute([$userId]);
        $username = (string) ($uStmt->fetchColumn() ?: '');

        audit_log('CREATE', 'CommFirstReading', (string) $firstReadingId, null, [
            'document_id'  => $documentId,
            'decision'     => $decision,
            'reading_date' => $readingDate,
            'performed_by' => $userId,
            'performed_by_username' => $username,
        ], "Comm First Reading ({$decision}) recorded for document #{$documentId} (user: {$username})");

        old_clear();
        flash_set('success', 'First Reading decision recorded successfully.');
        redirect('spsec/comm-first-reading');
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function requireSpsecAccess(): void
    {
        $role = auth_role();
        if (!in_array($role, ['SP Secretary', 'Super Admin'], true)) {
            flash_set('error', 'Access denied.');
            redirect(dashboard_route_for_role($role));
        }
    }

    private function requireStatus(string $name): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, name FROM document_statuses WHERE name = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1
        ");
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException("Required document status '{$name}' not found. Please run pending migrations.");
        }
        return $row;
    }

    private function isValidDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    private function fetchCommDocument(int $documentId): array|false
    {
        $stmt = $this->pdo->prepare("
            SELECT d.*,
                   dt.name        AS document_type_name,
                   dt.badge_color AS document_type_badge_color,
                   ds.name        AS status,
                   ds.badge_color AS status_badge_color
            FROM documents d
            INNER JOIN document_types dt ON dt.id = d.document_type_id AND dt.name LIKE '%Communication%' AND dt.is_deleted = 0
            LEFT JOIN document_statuses ds ON ds.id = d.current_status_id
            WHERE d.id = ?
            LIMIT 1
        ");
        $stmt->execute([$documentId]);
        return $stmt->fetch();
    }

    private function fetchInboxItem(int $documentId): array|false
    {
        $stmt = $this->pdo->prepare("SELECT * FROM comm_inbox_items WHERE document_id = ? LIMIT 1");
        $stmt->execute([$documentId]);
        return $stmt->fetch();
    }

    private function fetchFirstReading(int $documentId): array|false
    {
        $stmt = $this->pdo->prepare("
            SELECT cfr.*, cc.name AS communication_category_name, ua.username AS performed_by_username
            FROM comm_first_reading cfr
            LEFT JOIN communication_categories cc ON cc.id = cfr.communication_category_id
            LEFT JOIN user_accounts ua ON ua.id = cfr.performed_by
            WHERE cfr.document_id = ?
            LIMIT 1
        ");
        $stmt->execute([$documentId]);
        return $stmt->fetch();
    }

    private function validateMultipleFiles(array $files, bool $requireAtLeastOne = false): array
    {
        $errors = [];

        if ($requireAtLeastOne && empty($files['name'][0])) {
            $errors[] = 'At least one resolution file is required for Approved for Resolution decisions.';
            return $errors;
        }

        if (empty($files['name'][0])) {
            return $errors;
        }

        $count = count($files['name']);
        if ($count > self::MAX_FILES) {
            $errors[] = 'Maximum ' . self::MAX_FILES . ' files can be uploaded at once.';
        }

        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $errors[] = "Upload error for file: {$files['name'][$i]}";
                continue;
            }
            if ($files['size'][$i] > self::MAX_SIZE) {
                $errors[] = "File \"{$files['name'][$i]}\" exceeds the 25 MB limit.";
            }
            $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED_EXT, true)) {
                $errors[] = "File \"{$files['name'][$i]}\" has an unsupported extension.";
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $files['tmp_name'][$i]);
            finfo_close($finfo);
            if (!in_array($mime, self::ALLOWED_MIME, true)) {
                $errors[] = "File \"{$files['name'][$i]}\" has an unsupported MIME type ({$mime}).";
            }
        }

        return $errors;
    }

    private function stageResolutionFiles(array $files, int $documentId): array
    {
        $uploadDir = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/') . '/uploads/comm-resolutions/' . $documentId . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $staged = [];
        $count  = count($files['name']);

        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK || empty($files['name'][$i])) {
                continue;
            }

            // Block executable extensions
            $ext         = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            $safeName    = bin2hex(random_bytes(16)) . '.' . $ext;
            $destination = $uploadDir . $safeName;

            if (!move_uploaded_file($files['tmp_name'][$i], $destination)) {
                throw new RuntimeException("Failed to move uploaded file: {$files['name'][$i]}");
            }

            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $destination);
            finfo_close($finfo);

            $staged[] = [
                'original_filename' => $files['name'][$i],
                'stored_filename'   => $safeName,
                'file_path'         => '/uploads/comm-resolutions/' . $documentId . '/' . $safeName,
                'mime_type'         => $mimeType,
                'file_size'         => (int) filesize($destination),
                'tmp_stored'        => $destination,
            ];
        }

        return $staged;
    }

    private function persistResolutionFiles(
        array $staged,
        int $documentId,
        ?int $firstReadingId,
        ?int $reportId,
        int $userId
    ): void {
        $ins = $this->pdo->prepare("
            INSERT INTO comm_resolution_files
                (document_id, first_reading_id, committee_report_id,
                 original_filename, stored_filename, file_path, mime_type, file_size,
                 uploaded_by, uploaded_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        foreach ($staged as $sf) {
            $ins->execute([
                $documentId,
                $firstReadingId,
                $reportId,
                $sf['original_filename'],
                $sf['stored_filename'],
                $sf['file_path'],
                $sf['mime_type'],
                $sf['file_size'],
                $userId,
            ]);
        }
    }
}

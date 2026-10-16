<?php

require_once __DIR__ . '/../../config/database.php';

/**
 * ArchivedRecordsController
 *
 * CRUD management for archived legislative records (resolutions, ordinances, etc.).
 *
 * Routes handled:
 *   GET   admin/archived-records           → index()        list + search/filter
 *   GET   admin/archived-records/show      → show()         detail view
 *   POST  admin/archived-records/store     → store()        create new record
 *   POST  admin/archived-records/update    → update()       edit existing record
 *   POST  admin/archived-records/destroy   → destroy()      soft-delete
 *   POST  admin/archived-records/upload    → uploadAttachment()  add file attachment
 *   POST  admin/archived-records/attachment/delete → deleteAttachment() remove file
 */
class ArchivedRecordsController
{
    protected PDO $pdo;

    // Allowed upload MIME types
    private const ALLOWED_MIME_TYPES = [
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

    // Max single-file size: 25 MB
    private const MAX_FILE_SIZE = 26_214_400;

    public function __construct()
    {
        $database   = new Database();
        $this->pdo  = $database->connect();
    }

    // =========================================================================
    // 1. List (index)
    // =========================================================================

    public function index(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        // ── Filters ──────────────────────────────────────────────────────────
        $search      = trim($_GET['search']   ?? '');
        $filterType  = trim($_GET['type']     ?? '');
        $dateFrom    = trim($_GET['date_from'] ?? '');
        $dateTo      = trim($_GET['date_to']   ?? '');
        $isValidDate = static function (string $date): bool {
            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)) {
                return false;
            }
            return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
        };
        if ($dateFrom !== '' && !$isValidDate($dateFrom)) $dateFrom = '';
        if ($dateTo !== '' && !$isValidDate($dateTo)) $dateTo = '';

        // ── Pagination ───────────────────────────────────────────────────────
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        // ── Query ────────────────────────────────────────────────────────────
        $where  = ['ar.is_deleted = 0'];
        $params = [];

        if ($search !== '') {
            $where[]  = '(ar.approved_no LIKE ? OR ar.subject_matter LIKE ? OR ar.description LIKE ?
                OR ar.tags LIKE ?
                OR ar.custom_document_type_name LIKE ?
                OR EXISTS (SELECT 1 FROM document_types search_dt
                           WHERE search_dt.id = ar.document_type_id AND search_dt.name LIKE ?)
                OR EXISTS (
                    SELECT 1 FROM archived_records_authors ara
                    LEFT JOIN sp_members sm ON ara.sp_member_id = sm.sp_member_id
                    WHERE ara.archived_record_id = ar.id
                      AND (CONCAT_WS(\' \' , sm.first_name, sm.last_name) LIKE ?
                           OR ara.custom_author_name LIKE ?)
                )
                OR EXISTS (
                    SELECT 1 FROM archived_records_committees arc
                    LEFT JOIN committees c ON arc.committee_id = c.id
                    WHERE arc.archived_record_id = ar.id
                      AND (c.name LIKE ? OR arc.custom_committee_name LIKE ?)
                ))';
            $like     = "%{$search}%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($filterType !== '') {
            if (is_numeric($filterType)) {
                $where[]  = 'ar.document_type_id = ?';
                $params[] = (int) $filterType;
            } else {
                $where[]  = 'ar.custom_document_type_name LIKE ?';
                $params[] = "%{$filterType}%";
            }
        }

        if ($dateFrom !== '') {
            $where[]  = 'ar.date_approved >= ?';
            $params[] = $dateFrom;
        }

        if ($dateTo !== '') {
            $where[]  = 'ar.date_approved <= ?';
            $params[] = $dateTo;
        }

        $whereClause = implode(' AND ', $where);

        // Count
        $countStmt = $this->pdo->prepare("
            SELECT COUNT(*) AS total
            FROM archived_records ar
            WHERE {$whereClause}
        ");
        $countStmt->execute($params);
        $total      = (int) $countStmt->fetch()['total'];
        $totalPages = max(1, (int) ceil($total / $perPage));

        // List
        $listParams   = $params;
        $listParams[] = $perPage;
        $listParams[] = $offset;

        $listStmt = $this->pdo->prepare("
            SELECT
                ar.id,
                ar.approved_no,
                ar.date_approved,
                ar.subject_matter,
                ar.tags,
                ar.is_active,
                COALESCE(dt.name, ar.custom_document_type_name) AS document_type_name,
                dt.badge_color                                   AS document_type_badge_color,
                ar.document_type_id,
                (SELECT COUNT(*) FROM archived_records_attachments
                 WHERE archived_record_id = ar.id)               AS attachment_count,
                (SELECT GROUP_CONCAT(
                    COALESCE(CONCAT_WS(' ', sm.first_name, sm.last_name), ara.custom_author_name)
                    ORDER BY ara.sort_order SEPARATOR ', ')
                 FROM archived_records_authors ara
                 LEFT JOIN sp_members sm ON ara.sp_member_id = sm.sp_member_id
                 WHERE ara.archived_record_id = ar.id
                   AND ara.author_role = 'AUTHOR'
                )                                                AS authors_summary
            FROM archived_records ar
            LEFT JOIN document_types dt ON ar.document_type_id = dt.id
            WHERE {$whereClause}
            ORDER BY ar.date_approved DESC, ar.approved_no DESC
            LIMIT ? OFFSET ?
        ");
        $listStmt->execute($listParams);
        $records = $listStmt->fetchAll();

        // Sidebar counts
        $totalCount = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM archived_records WHERE is_deleted = 0"
        )->fetchColumn();

        // Document types for filter dropdown
        $documentTypes = $this->pdo->query(
            "SELECT id, name, badge_color FROM document_types WHERE is_active = 1 AND is_deleted = 0 ORDER BY name ASC"
        )->fetchAll();

        // SP members and committees for create modal
        $spMembers = $this->pdo->query(
            "SELECT sp_member_id, CONCAT_WS(' ', first_name, last_name) AS full_name, position
             FROM sp_members WHERE is_active = 1 AND is_deleted = 0 ORDER BY last_name ASC"
        )->fetchAll();

        $committees = $this->pdo->query(
            "SELECT id, name FROM committees WHERE is_active = 1 AND is_deleted = 0 ORDER BY name ASC"
        )->fetchAll();

        $pageTitle    = 'Archived Records';
        $pageSubtitle = 'Browse and manage officially archived legislative records';

        ob_start();
        require __DIR__ . '/../../../resources/views/admin/archived-records/index.php';
        $content = ob_get_clean();

        require __DIR__ . '/../../../resources/views/layouts/app.php';
    }

    // =========================================================================
    // 2. Detail (show)
    // =========================================================================

    public function show(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id <= 0) {
            flash_set('error', 'Invalid record ID.');
            redirect('admin/archived-records');
        }

        $stmt = $this->pdo->prepare("
            SELECT
                ar.*,
                COALESCE(dt.name, ar.custom_document_type_name) AS document_type_name,
                dt.badge_color AS document_type_badge_color,
                CONCAT_WS(' ', uc.first_name, uc.last_name) AS created_by_name,
                CONCAT_WS(' ', uu.first_name, uu.last_name) AS updated_by_name
            FROM archived_records ar
            LEFT JOIN document_types dt ON ar.document_type_id = dt.id
            LEFT JOIN user_info uc ON uc.user_account_id = ar.created_by
            LEFT JOIN user_info uu ON uu.user_account_id = ar.updated_by
            WHERE ar.id = ? AND ar.is_deleted = 0
        ");
        $stmt->execute([$id]);
        $record = $stmt->fetch();

        if (!$record) {
            flash_set('error', 'Record not found.');
            redirect('admin/archived-records');
        }

        // Authors
        $authorsStmt = $this->pdo->prepare("
            SELECT
                ara.id, ara.author_role, ara.sort_order,
                ara.sp_member_id, ara.custom_author_name,
                CONCAT_WS(' ', sm.first_name, sm.last_name) AS member_full_name,
                sm.position AS member_position
            FROM archived_records_authors ara
            LEFT JOIN sp_members sm ON ara.sp_member_id = sm.sp_member_id
            WHERE ara.archived_record_id = ?
            ORDER BY ara.author_role ASC, ara.sort_order ASC
        ");
        $authorsStmt->execute([$id]);
        $authors = $authorsStmt->fetchAll();

        // Committees
        $committeesStmt = $this->pdo->prepare("
            SELECT
                arc.id, arc.sort_order,
                arc.committee_id, arc.custom_committee_name,
                c.name AS committee_name
            FROM archived_records_committees arc
            LEFT JOIN committees c ON arc.committee_id = c.id
            WHERE arc.archived_record_id = ?
            ORDER BY arc.sort_order ASC
        ");
        $committeesStmt->execute([$id]);
        $committees = $committeesStmt->fetchAll();

        // Attachments
        $attachmentsStmt = $this->pdo->prepare("
            SELECT id, file_name, file_size, mime_type, file_path, uploaded_at
            FROM archived_records_attachments
            WHERE archived_record_id = ?
            ORDER BY uploaded_at ASC
        ");
        $attachmentsStmt->execute([$id]);
        $attachments = $attachmentsStmt->fetchAll();

        // For edit modal: all document types, sp members, committees
        $documentTypesStmt = $this->pdo->prepare(
            "SELECT id, name, badge_color FROM document_types
             WHERE (is_active = 1 AND is_deleted = 0)
                OR id = (SELECT document_type_id FROM archived_records WHERE id = ?)
             ORDER BY name ASC"
        );
        $documentTypesStmt->execute([$id]);
        $documentTypes = $documentTypesStmt->fetchAll();

        $spMembersStmt = $this->pdo->prepare(
            "SELECT sm.sp_member_id, CONCAT_WS(' ', sm.first_name, sm.last_name) AS full_name, sm.position
             FROM sp_members sm
             WHERE (sm.is_active = 1 AND sm.is_deleted = 0)
                OR EXISTS (SELECT 1 FROM archived_records_authors ara
                           WHERE ara.sp_member_id = sm.sp_member_id AND ara.archived_record_id = ?)
             ORDER BY sm.last_name ASC"
        );
        $spMembersStmt->execute([$id]);
        $spMembers = $spMembersStmt->fetchAll();

        $committeesStmt = $this->pdo->prepare(
            "SELECT c.id, c.name
             FROM committees c
             WHERE (c.is_active = 1 AND c.is_deleted = 0)
                OR EXISTS (SELECT 1 FROM archived_records_committees arc
                           WHERE arc.committee_id = c.id AND arc.archived_record_id = ?)
             ORDER BY c.name ASC"
        );
        $committeesStmt->execute([$id]);
        $allCommittees = $committeesStmt->fetchAll();

        $pageTitle    = $record['approved_no'] . ' — Archived Record';
        $pageSubtitle = $record['approved_no'];

        ob_start();
        require __DIR__ . '/../../../resources/views/admin/archived-records/show.php';
        $content = ob_get_clean();

        require __DIR__ . '/../../../resources/views/layouts/app.php';
    }

    // =========================================================================
    // 3. Store (create)
    // =========================================================================

    public function store(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        $data   = $this->collectFormData();
        $errors = $this->validateFormData($data);

        // Handle file uploads
        $uploadedFiles = $_FILES['attachments'] ?? [];
        $fileErrors    = $this->validateFileUploads($uploadedFiles);
        $errors        = array_merge($errors, $fileErrors);

        if (!empty($errors)) {
            flash_set('errors', $errors);
            redirect('admin/archived-records');
        }

        try {
            $this->pdo->beginTransaction();

            // Insert record
            $stmt = $this->pdo->prepare("
                INSERT INTO archived_records
                    (approved_no, date_approved, subject_matter, description, tags,
                     document_type_id, custom_document_type_name, created_by, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['approved_no'],
                $data['date_approved'],
                $data['subject_matter'],
                $data['description'] ?: null,
                $data['tags'] ?: null,
                $data['document_type_id'],
                $data['custom_document_type_name'],
                $userId,
                $userId,
            ]);
            $recordId = (int) $this->pdo->lastInsertId();

            // Authors
            $this->saveAuthors($recordId, $data['authors'] ?? []);

            // Committees
            $this->saveCommittees($recordId, $data['committees'] ?? [], $userId);

            // File attachments
            $this->processFileUploads($recordId, $uploadedFiles, $userId);

            $this->pdo->commit();

            audit_log('CREATE', 'archived_records', (string) $recordId, null, [
                'approved_no' => $data['approved_no'],
            ], null, $userId);

            flash_set('success', "Archived record {$data['approved_no']} has been created successfully.");
            redirect('admin/archived-records');

        } catch (Throwable $e) {
            $this->rollbackIfActive();
            system_log('ERROR', 'ArchivedRecords::store failed', [
                'error'   => $e->getMessage(),
                'user_id' => $userId,
            ]);
            flash_set('error', 'Failed to create the archived record. Please try again.');
            redirect('admin/archived-records');
        }
    }

    // =========================================================================
    // 4. Update
    // =========================================================================

    public function update(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id <= 0) {
            flash_set('error', 'Invalid record ID.');
            redirect('admin/archived-records');
        }

        // Verify record exists
        $existing = $this->pdo->prepare("SELECT id, approved_no FROM archived_records WHERE id = ? AND is_deleted = 0");
        $existing->execute([$id]);
        $record = $existing->fetch();
        if (!$record) {
            flash_set('error', 'Record not found.');
            redirect('admin/archived-records');
        }

        $data   = $this->collectFormData();
        $errors = $this->validateFormData($data, $id);

        if (!empty($errors)) {
            flash_set('errors', $errors);
            redirect("admin/archived-records/show?id={$id}");
        }

        $uploadedFiles = $_FILES['attachments'] ?? [];
        $fileErrors = $this->validateFileUploads($uploadedFiles);
        if (!empty($fileErrors)) {
            flash_set('errors', $fileErrors);
            redirect("admin/archived-records/show?id={$id}");
        }

        try {
            $this->pdo->beginTransaction();

            // Snapshot old data for audit
            $oldStmt = $this->pdo->prepare("SELECT * FROM archived_records WHERE id = ?");
            $oldStmt->execute([$id]);
            $oldData = $oldStmt->fetch();

            // Update core record
            $stmt = $this->pdo->prepare("
                UPDATE archived_records
                SET approved_no               = ?,
                    date_approved             = ?,
                    subject_matter            = ?,
                    description               = ?,
                    tags                      = ?,
                    document_type_id          = ?,
                    custom_document_type_name = ?,
                    updated_by                = ?,
                    updated_at                = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $data['approved_no'],
                $data['date_approved'],
                $data['subject_matter'],
                $data['description'] ?: null,
                $data['tags'] ?: null,
                $data['document_type_id'],
                $data['custom_document_type_name'],
                $userId,
                $id,
            ]);

            // Replace authors and committees
            $this->pdo->prepare("DELETE FROM archived_records_authors WHERE archived_record_id = ?")->execute([$id]);
            $this->pdo->prepare("DELETE FROM archived_records_committees WHERE archived_record_id = ?")->execute([$id]);
            $this->saveAuthors($id, $data['authors'] ?? []);
            $this->saveCommittees($id, $data['committees'] ?? [], $userId);

            // New file attachments
            if (!empty($uploadedFiles['name'][0])) {
                $this->processFileUploads($id, $uploadedFiles, $userId);
            }

            $this->pdo->commit();

            audit_log('UPDATE', 'archived_records', (string) $id, $oldData, $data, null, $userId);

            flash_set('success', "Archived record {$data['approved_no']} has been updated.");
            redirect("admin/archived-records/show?id={$id}");

        } catch (Throwable $e) {
            $this->rollbackIfActive();
            system_log('ERROR', 'ArchivedRecords::update failed', [
                'error'   => $e->getMessage(),
                'user_id' => $userId,
                'id'      => $id,
            ]);
            flash_set('error', 'Failed to update the record. Please try again.');
            redirect("admin/archived-records/show?id={$id}");
        }
    }

    // =========================================================================
    // 5. Destroy (soft delete)
    // =========================================================================

    public function destroy(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id <= 0) {
            flash_set('error', 'Invalid record ID.');
            redirect('admin/archived-records');
        }

        $stmt = $this->pdo->prepare("SELECT id, approved_no FROM archived_records WHERE id = ? AND is_deleted = 0");
        $stmt->execute([$id]);
        $record = $stmt->fetch();
        if (!$record) {
            flash_set('error', 'Record not found.');
            redirect('admin/archived-records');
        }

        try {
            $this->pdo->prepare(
                "UPDATE archived_records SET is_deleted = 1, updated_by = ?, updated_at = NOW() WHERE id = ?"
            )->execute([$userId, $id]);

            audit_log('DELETE', 'archived_records', (string) $id, ['approved_no' => $record['approved_no']], null, null, $userId);

            flash_set('success', "Archived record {$record['approved_no']} has been deleted.");
        } catch (Throwable $e) {
            system_log('ERROR', 'ArchivedRecords::destroy failed', [
                'error'   => $e->getMessage(),
                'user_id' => $userId,
                'id'      => $id,
            ]);
            flash_set('error', 'Failed to delete the record. Please try again.');
        }

        redirect('admin/archived-records');
    }

    // =========================================================================
    // 6. Upload attachment (from detail page)
    // =========================================================================

    public function uploadAttachment(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        $id = filter_var($_POST['record_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id <= 0) {
            flash_set('error', 'Invalid record ID.');
            redirect('admin/archived-records');
        }

        // Verify record
        $stmt = $this->pdo->prepare("SELECT id FROM archived_records WHERE id = ? AND is_deleted = 0");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            flash_set('error', 'Record not found.');
            redirect('admin/archived-records');
        }

        $uploadedFiles = $_FILES['attachments'] ?? [];
        $errors        = $this->validateFileUploads($uploadedFiles);
        if (!empty($errors)) {
            flash_set('errors', $errors);
            redirect("admin/archived-records/show?id={$id}");
        }

        try {
            $this->processFileUploads($id, $uploadedFiles, $userId);
            flash_set('success', 'Attachment(s) uploaded successfully.');
        } catch (Throwable $e) {
            system_log('ERROR', 'ArchivedRecords::uploadAttachment failed', [
                'error'   => $e->getMessage(),
                'user_id' => $userId,
                'id'      => $id,
            ]);
            flash_set('error', 'Failed to upload attachment(s). Please try again.');
        }

        redirect("admin/archived-records/show?id={$id}");
    }

    // =========================================================================
    // 7. Delete attachment
    // =========================================================================

    public function deleteAttachment(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        $attachmentId = filter_var($_POST['attachment_id'] ?? null, FILTER_VALIDATE_INT);
        $recordId     = filter_var($_POST['record_id']     ?? null, FILTER_VALIDATE_INT);

        if (!$attachmentId || !$recordId) {
            flash_set('error', 'Invalid request.');
            redirect('admin/archived-records');
        }

        // Fetch attachment
        $stmt = $this->pdo->prepare(
            "SELECT id, file_path, file_name, archived_record_id FROM archived_records_attachments WHERE id = ? AND archived_record_id = ?"
        );
        $stmt->execute([$attachmentId, $recordId]);
        $attachment = $stmt->fetch();

        if (!$attachment) {
            flash_set('error', 'Attachment not found.');
            redirect("admin/archived-records/show?id={$recordId}");
        }

        try {
            // Delete from DB first
            $this->pdo->prepare("DELETE FROM archived_records_attachments WHERE id = ?")->execute([$attachmentId]);

            // Delete physical file (best-effort)
            $physPath = STORAGE_PATH . DIRECTORY_SEPARATOR . $attachment['file_path'];
            if (file_exists($physPath)) {
                @unlink($physPath);
            }

            audit_log('DELETE', 'archived_records_attachments', (string) $attachmentId,
                ['file_name' => $attachment['file_name']], null, null, $userId);

            flash_set('success', "Attachment \"{$attachment['file_name']}\" has been removed.");
        } catch (Throwable $e) {
            system_log('ERROR', 'ArchivedRecords::deleteAttachment failed', [
                'error'   => $e->getMessage(),
                'user_id' => $userId,
            ]);
            flash_set('error', 'Failed to delete attachment. Please try again.');
        }

        redirect("admin/archived-records/show?id={$recordId}");
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /** Collect + sanitise POST form data */
    private function collectFormData(): array
    {
        $toText = static fn($value): string => is_scalar($value) ? trim((string) $value) : '';
        $rawDocTypeId = $toText($_POST['document_type_id'] ?? '');
        $parsedDocTypeId = $rawDocTypeId === '' ? null : filter_var($rawDocTypeId, FILTER_VALIDATE_INT);
        $docTypeId = $rawDocTypeId === '' ? null : ($parsedDocTypeId === false ? -1 : (int) $parsedDocTypeId);
        $customType = $toText($_POST['custom_document_type_name'] ?? '');

        // Normalise the mutually-exclusive pair
        if ($docTypeId > 0) {
            $customType = null;
        } elseif ($customType !== '') {
            $docTypeId = null;
        } else {
            $docTypeId  = null;
            $customType = null;
        }

        // Tags: normalise to comma-separated lowercase
        $rawTags = $toText($_POST['tags'] ?? '');
        $tags    = '';
        if ($rawTags !== '') {
            $tagArr  = array_filter(array_map(fn($t) => strtolower(trim($t)), explode(',', $rawTags)));
            $tags    = implode(',', array_unique($tagArr));
        }

        // Authors
        $authors = [];
        $authorSpIds      = (array) ($_POST['author_sp_member_id'] ?? []);
        $authorCustom     = (array) ($_POST['author_custom_name'] ?? []);
        $authorRoles      = (array) ($_POST['author_role'] ?? []);
        $authorCount = max(count($authorSpIds), count($authorCustom), count($authorRoles));
        for ($i = 0; $i < $authorCount; $i++) {
            $rawSpId = $toText($authorSpIds[$i] ?? '');
            $parsedSpId = $rawSpId === '' ? null : filter_var($rawSpId, FILTER_VALIDATE_INT);
            $spId = $rawSpId === '' ? null : ($parsedSpId === false ? -1 : (int) $parsedSpId);
            $customName = $toText($authorCustom[$i] ?? '');
            $cname = $customName !== '' ? $customName : null;
            $role  = in_array($authorRoles[$i] ?? 'AUTHOR', ['AUTHOR', 'CO_AUTHOR'], true)
                     ? $authorRoles[$i]
                     : 'AUTHOR';
            if ($spId !== null || $cname !== null) {
                $authors[] = ['sp_member_id' => $spId, 'custom_author_name' => $cname, 'role' => $role];
            }
        }

        // Committees
        // Custom committee names may be comma-separated — split each entry into
        // individual records so "Health, Environment" becomes two committee rows.
        $committees = [];
        $commIds    = (array) ($_POST['committee_id'] ?? []);
        $commCustom = (array) ($_POST['committee_custom_name'] ?? []);
        $commCount  = max(count($commIds), count($commCustom));
        for ($i = 0; $i < $commCount; $i++) {
            $rawCommitteeId = $toText($commIds[$i] ?? '');
            $parsedCommitteeId = $rawCommitteeId === '' ? null : filter_var($rawCommitteeId, FILTER_VALIDATE_INT);
            $cid = $rawCommitteeId === '' ? null : ($parsedCommitteeId === false ? -1 : (int) $parsedCommitteeId);
            $customName = $toText($commCustom[$i] ?? '');

            if ($cid !== null) {
                // Selected from list — store as-is
                $committees[] = ['committee_id' => $cid, 'custom_committee_name' => null];
            } elseif ($customName !== '') {
                // Split comma-separated custom names into individual entries
                $parts = array_filter(array_map('trim', explode(',', $customName)));
                foreach ($parts as $part) {
                    if ($part !== '') {
                        $committees[] = ['committee_id' => null, 'custom_committee_name' => $part];
                    }
                }
            }
        }

        return [
            'approved_no'               => $toText($_POST['approved_no'] ?? ''),
            'date_approved'             => $toText($_POST['date_approved'] ?? ''),
            'subject_matter'            => $toText($_POST['subject_matter'] ?? ''),
            'description'               => $toText($_POST['description'] ?? ''),
            'tags'                      => $tags,
            'document_type_id'          => $docTypeId,
            'custom_document_type_name' => $customType,
            'authors'                   => $authors,
            'committees'                => $committees,
        ];
    }

    /** Validate form data, return array of error strings */
    private function validateFormData(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        if ($data['approved_no'] === '') {
            $errors[] = 'Approved number is required.';
        } elseif (mb_strlen($data['approved_no']) > 100) {
            $errors[] = 'Approved number must be 100 characters or fewer.';
        } else {
            // Unique check
            $sql    = "SELECT id FROM archived_records WHERE approved_no = ? AND is_deleted = 0";
            $params = [$data['approved_no']];
            if ($excludeId !== null) {
                $sql    .= " AND id != ?";
                $params[] = $excludeId;
            }
            $check = $this->pdo->prepare($sql);
            $check->execute($params);
            if ($check->fetch()) {
                $errors[] = "Approved number \"{$data['approved_no']}\" already exists.";
            }
        }

        if ($data['date_approved'] === '') {
            $errors[] = 'Date approved is required.';
        } elseif (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $data['date_approved'], $dateParts)
            || !checkdate((int) $dateParts[2], (int) $dateParts[3], (int) $dateParts[1])) {
            $errors[] = 'Date approved must be in YYYY-MM-DD format.';
        }

        if ($data['subject_matter'] === '') {
            $errors[] = 'Subject matter is required.';
        }

        if ($data['document_type_id'] === null && ($data['custom_document_type_name'] === null || $data['custom_document_type_name'] === '')) {
            $errors[] = 'Document type is required. Select an existing type or enter a custom type name.';
        }

        if ($data['document_type_id'] !== null) {
            $typeSql = "SELECT id FROM document_types WHERE id = ? AND (is_active = 1 AND is_deleted = 0";
            $typeParams = [$data['document_type_id']];
            if ($excludeId !== null) {
                $typeSql .= " OR id = (SELECT document_type_id FROM archived_records WHERE id = ?)";
                $typeParams[] = $excludeId;
            }
            $typeSql .= ')';
            $typeCheck = $this->pdo->prepare($typeSql);
            $typeCheck->execute($typeParams);
            if (!$typeCheck->fetch()) {
                $errors[] = 'Selected document type is invalid.';
            }
        }
        if ($data['custom_document_type_name'] !== null && mb_strlen($data['custom_document_type_name']) > 200) {
            $errors[] = 'Custom document type names must be 200 characters or fewer.';
        }

        $seenPeople = [];
        $memberNames = [];
        foreach ($data['authors'] as $author) {
            $memberId = $author['sp_member_id'];
            $customName = $author['custom_author_name'];
            if ($memberId !== null && $customName !== null) {
                $errors[] = 'Choose either an existing author/sponsor or a custom name for each person, not both.';
                continue;
            }
            if ($memberId !== null) {
                if ($memberId <= 0) {
                    $errors[] = 'A selected author/sponsor is invalid.';
                    continue;
                }
                if (!array_key_exists($memberId, $memberNames)) {
                    $memberStmt = $this->pdo->prepare(
                        'SELECT CONCAT_WS(\' \' , first_name, last_name) FROM sp_members WHERE sp_member_id = ?'
                    );
                    $memberStmt->execute([$memberId]);
                    $memberNames[$memberId] = $memberStmt->fetchColumn() ?: null;
                }
                if ($memberNames[$memberId] === null) {
                    $errors[] = 'A selected author/sponsor no longer exists.';
                    continue;
                }
                $identity = 'member:' . $memberId;
                $normalizedName = mb_strtolower(preg_replace('/\s+/u', ' ', trim($memberNames[$memberId])));
            } else {
                if ($customName === null || $customName === '') {
                    continue;
                }
                if (mb_strlen($customName) > 200) {
                    $errors[] = 'Author and co-author names must be 200 characters or fewer.';
                    continue;
                }
                $normalizedName = mb_strtolower(preg_replace('/\s+/u', ' ', trim($customName)));
                $identity = 'custom:' . $normalizedName;
            }

            if ((isset($seenPeople[$identity]) && $seenPeople[$identity] !== $author['role'])
                || (isset($seenPeople['name:' . $normalizedName]) && $seenPeople['name:' . $normalizedName] !== $author['role'])) {
                $errors[] = 'The same person cannot be both an author/sponsor and a co-author/co-sponsor.';
            }
            $seenPeople[$identity] = $author['role'];
            $seenPeople['name:' . $normalizedName] = $author['role'];
        }

        foreach ($data['committees'] as $committee) {
            $committeeId = $committee['committee_id'];
            $customName = $committee['custom_committee_name'];
            if ($committeeId !== null && $customName !== null) {
                $errors[] = 'Choose either an existing committee or a custom name for each committee, not both.';
                continue;
            }
            if ($committeeId !== null) {
                $committeeCheck = $this->pdo->prepare('SELECT id FROM committees WHERE id = ?');
                $committeeCheck->execute([$committeeId]);
                if (!$committeeCheck->fetchColumn()) {
                    $errors[] = 'A selected committee is invalid.';
                }
            } elseif ($customName !== null && mb_strlen($customName) > 200) {
                $errors[] = 'Custom committee names must be 200 characters or fewer.';
            }
        }

        return $errors;
    }

    /** Roll back only when a transaction is still open. */
    private function rollbackIfActive(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /** Insert authors for a record */
    private function saveAuthors(int $recordId, array $authors): void
    {
        if (empty($authors)) {
            return;
        }
        $stmt = $this->pdo->prepare("
            INSERT INTO archived_records_authors
                (archived_record_id, sp_member_id, custom_author_name, author_role, sort_order)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($authors as $i => $author) {
            $stmt->execute([
                $recordId,
                $author['sp_member_id'],
                $author['custom_author_name'],
                $author['role'],
                $i,
            ]);
        }
    }

    /** Insert committees for a record */
    private function saveCommittees(int $recordId, array $committees, int $userId): void
    {
        if (empty($committees)) {
            return;
        }
        $stmt = $this->pdo->prepare("
            INSERT INTO archived_records_committees
                (archived_record_id, committee_id, custom_committee_name, assigned_by, sort_order)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($committees as $i => $comm) {
            $stmt->execute([
                $recordId,
                $comm['committee_id'],
                $comm['custom_committee_name'],
                $userId,
                $i,
            ]);
        }
    }

    /** Validate file uploads — returns array of error strings */
    private function validateFileUploads(array $files): array
    {
        $errors = [];

        if (empty($files['name']) || (is_array($files['name']) && empty(array_filter($files['name'])))) {
            return $errors; // No files uploaded — OK (attachments are optional)
        }

        $names    = (array) $files['name'];
        $sizes    = (array) $files['size'];
        $tmpNames = (array) $files['tmp_name'];
        $errors_  = (array) ($files['error'] ?? []);

        if (count($names) > 10) {
            return ['Maximum 10 files can be uploaded at once.'];
        }

        foreach ($names as $i => $name) {
            if ($name === '' || $name === null) {
                continue;
            }
            $uploadError = (int) ($errors_[$i] ?? UPLOAD_ERR_OK);
            if ($uploadError === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($uploadError !== UPLOAD_ERR_OK) {
                $errors[] = "File \"{$name}\" could not be uploaded (error code {$uploadError}).";
                continue;
            }
            $size = (int) ($sizes[$i] ?? 0);
            if ($size > self::MAX_FILE_SIZE) {
                $errors[] = "File \"{$name}\" exceeds the 25 MB size limit.";
            }
            $mime = mime_content_type($tmpNames[$i]);
            if (!in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
                $errors[] = "File \"{$name}\" has an unsupported type ({$mime}).";
            }
        }

        return $errors;
    }

    /** Move uploaded files to storage and insert DB rows */
    private function processFileUploads(int $recordId, array $files, int $userId): void
    {
        if (empty($files['name'])) {
            return;
        }

        $names    = (array) $files['name'];
        $sizes    = (array) $files['size'];
        $tmpNames = (array) $files['tmp_name'];
        $mimes    = (array) ($files['type'] ?? []);
        $errors_  = (array) ($files['error'] ?? []);

        $storageDir = STORAGE_PATH . DIRECTORY_SEPARATOR . 'archived_records' . DIRECTORY_SEPARATOR . $recordId;
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0775, true);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO archived_records_attachments
                (archived_record_id, file_name, file_size, mime_type, file_path, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($names as $i => $originalName) {
            if ($originalName === '' || ($errors_[$i] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                continue;
            }
            $safeExt  = pathinfo($originalName, PATHINFO_EXTENSION);
            $stored   = uniqid('ar_', true) . '.' . $safeExt;
            $destPath = $storageDir . DIRECTORY_SEPARATOR . $stored;
            if (!move_uploaded_file($tmpNames[$i], $destPath)) {
                throw new RuntimeException("Failed to move uploaded file: {$originalName}");
            }
            $mime     = mime_content_type($destPath);
            $relPath  = "archived_records/{$recordId}/{$stored}";
            $stmt->execute([
                $recordId,
                $originalName,
                (int) ($sizes[$i] ?? 0),
                $mime,
                $relPath,
                $userId,
            ]);
        }
    }
}

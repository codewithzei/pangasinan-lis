<?php

require_once __DIR__ . '/../../config/database.php';

class SpMemberController
{
    protected PDO $pdo;

    public function __construct()
    {
        $database = new Database();
        $this->pdo = $database->connect();
    }

    // -------------------------------------------------------------------------
    // index — paginated list with search + district filter
    // -------------------------------------------------------------------------
    public function index(): void
    {
        $search          = trim($_GET['search'] ?? '');
        $filterStatus    = $_GET['status'] ?? '';
        $filterDistrict  = (int)($_GET['district_id'] ?? 0);
        $filterPosition  = trim($_GET['position'] ?? '');
        $page            = max(1, (int)($_GET['page'] ?? 1));
        $perPage         = 10;

        $whereClauses = ['sm.is_deleted = 0'];
        $params       = [];

        if ($search !== '') {
            $whereClauses[] = '(sm.first_name LIKE ? OR sm.last_name LIKE ? OR sm.middle_name LIKE ? OR sm.position LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if ($filterStatus !== '') {
            $whereClauses[] = 'sm.is_active = ?';
            $params[] = $filterStatus === 'active' ? 1 : 0;
        }

        if ($filterDistrict > 0) {
            $whereClauses[] = 'sm.district_id = ?';
            $params[] = $filterDistrict;
        }

        if ($filterPosition !== '') {
            $whereClauses[] = 'sm.position = ?';
            $params[] = $filterPosition;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM sp_members sm WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalRows  = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $offset     = ($page - 1) * $perPage;

        $sql = "SELECT sm.*, d.name AS district_name
                FROM sp_members sm
                LEFT JOIN districts d ON sm.district_id = d.id
                WHERE {$whereSql}
                ORDER BY sm.sort_order ASC, sm.last_name ASC, sm.first_name ASC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $spMembers = $stmt->fetchAll();

        // Summary counts
        $totalSpMembers    = (int)$this->pdo->query("SELECT COUNT(*) FROM sp_members WHERE is_deleted = 0")->fetchColumn();
        $activeSpMembers   = (int)$this->pdo->query("SELECT COUNT(*) FROM sp_members WHERE is_deleted = 0 AND is_active = 1")->fetchColumn();
        $inactiveSpMembers = $totalSpMembers - $activeSpMembers;

        // District list for filter dropdown + create/edit modals
        $districts = $this->pdo->query(
            "SELECT id, name FROM districts WHERE is_deleted = 0 AND is_active = 1 ORDER BY sort_order ASC, name ASC"
        )->fetchAll();

        // Positions from the positions master table for the filter dropdown
        $positions = $this->pdo->query(
            "SELECT id, name FROM positions WHERE is_deleted = 0 AND is_active = 1 ORDER BY sort_order ASC, name ASC"
        )->fetchAll();

        $success = flash_get('success');
        $error   = flash_get('error');

        $pageTitle    = 'SP Members Management';
        $pageSubtitle = 'Manage Sangguniang Panlalawigan members and their district assignments';
        $accent       = 'primary';

        $viewDir = __DIR__ . '/../../../resources/views/master/sp-members';
        if (!is_dir($viewDir)) {
            mkdir($viewDir, 0777, true);
        }
        require $viewDir . '/index.php';
    }

    // -------------------------------------------------------------------------
    // store — create a new SP member
    // -------------------------------------------------------------------------
    public function store(): void
    {
        $userId = auth_id();

        $data = [
            'first_name'  => trim($_POST['first_name'] ?? ''),
            'middle_name' => trim($_POST['middle_name'] ?? '') ?: null,
            'last_name'   => trim($_POST['last_name'] ?? ''),
            'suffix'      => trim($_POST['suffix'] ?? '') ?: null,
            'position'    => trim($_POST['position'] ?? ''),
            'district_id' => ($_POST['district_id'] ?? '') !== '' ? (int)$_POST['district_id'] : null,
            'sort_order'  => (int)($_POST['sort_order'] ?? 0),
            'is_active'   => isset($_POST['is_active']) ? 1 : 0,
        ];

        $errors = $this->validateSpMember($data);

        // Handle photo upload
        $photoPath = null;
        if (!empty($_FILES['photo']['name'])) {
            $uploadResult = $this->handlePhotoUpload($_FILES['photo']);
            if ($uploadResult['error']) {
                $errors[] = $uploadResult['error'];
            } else {
                $photoPath = $uploadResult['path'];
            }
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            old_set($_POST);
            redirect('master/sp-members');
        }

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO sp_members
                    (first_name, middle_name, last_name, suffix, photo_path, position,
                     district_id, sort_order, is_active, created_by, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $data['first_name'],
                $data['middle_name'],
                $data['last_name'],
                $data['suffix'],
                $photoPath,
                $data['position'],
                $data['district_id'],
                $data['sort_order'],
                $data['is_active'],
                $userId,
                $userId,
            ]);
            $newId    = (int)$this->pdo->lastInsertId();
            $fullName = $this->buildFullName($data);

            audit_log('CREATE', 'SpMember', (string)$newId, null, $data, "Created SP member: {$fullName}");
            system_log('INFO', "SP member created: {$fullName} (ID: {$newId})");
            flash_set('success', "SP member \"{$fullName}\" created successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'SP member create failed', ['error' => $e->getMessage(), 'data' => $data]);
            flash_set('error', 'Failed to create SP member: ' . $e->getMessage());
            old_set($_POST);
        }

        redirect('master/sp-members');
    }

    // -------------------------------------------------------------------------
    // edit — fetch a single record as JSON (used by AJAX modal)
    // -------------------------------------------------------------------------
    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);

        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid SP member ID.']);
            exit;
        }

        $stmt = $this->pdo->prepare(
            "SELECT sm.*, d.name AS district_name
             FROM sp_members sm
             LEFT JOIN districts d ON sm.district_id = d.id
             WHERE sm.sp_member_id = ? AND sm.is_deleted = 0
             LIMIT 1"
        );
        $stmt->execute([$id]);
        $spMember = $stmt->fetch();

        if (!$spMember) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'SP member not found.']);
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $spMember]);
        exit;
    }

    // -------------------------------------------------------------------------
    // update — edit an existing SP member
    // -------------------------------------------------------------------------
    public function update(): void
    {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            flash_set('error', 'Invalid SP member ID.');
            redirect('master/sp-members');
        }

        $userId = auth_id();

        $data = [
            'first_name'  => trim($_POST['first_name'] ?? ''),
            'middle_name' => trim($_POST['middle_name'] ?? '') ?: null,
            'last_name'   => trim($_POST['last_name'] ?? ''),
            'suffix'      => trim($_POST['suffix'] ?? '') ?: null,
            'position'    => trim($_POST['position'] ?? ''),
            'district_id' => ($_POST['district_id'] ?? '') !== '' ? (int)$_POST['district_id'] : null,
            'sort_order'  => (int)($_POST['sort_order'] ?? 0),
            'is_active'   => isset($_POST['is_active']) ? 1 : 0,
        ];

        $errors = $this->validateSpMember($data);

        // Handle photo upload — keep existing photo if no new file uploaded
        $photoPath = trim($_POST['existing_photo_path'] ?? '') ?: null;
        if (!empty($_FILES['photo']['name'])) {
            $uploadResult = $this->handlePhotoUpload($_FILES['photo']);
            if ($uploadResult['error']) {
                $errors[] = $uploadResult['error'];
            } else {
                $photoPath = $uploadResult['path'];
            }
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            redirect('master/sp-members');
        }

        try {
            $oldStmt = $this->pdo->prepare(
                "SELECT * FROM sp_members WHERE sp_member_id = ? AND is_deleted = 0 LIMIT 1"
            );
            $oldStmt->execute([$id]);
            $oldRecord = $oldStmt->fetch();

            if (!$oldRecord) {
                flash_set('error', 'SP member not found.');
                redirect('master/sp-members');
            }

            $stmt = $this->pdo->prepare(
                "UPDATE sp_members
                 SET first_name = ?, middle_name = ?, last_name = ?, suffix = ?,
                     photo_path = ?, position = ?, district_id = ?,
                     sort_order = ?, is_active = ?, updated_by = ?
                 WHERE sp_member_id = ? AND is_deleted = 0"
            );
            $stmt->execute([
                $data['first_name'],
                $data['middle_name'],
                $data['last_name'],
                $data['suffix'],
                $photoPath,
                $data['position'],
                $data['district_id'],
                $data['sort_order'],
                $data['is_active'],
                $userId,
                $id,
            ]);

            $fullName = $this->buildFullName($data);
            audit_log('UPDATE', 'SpMember', (string)$id, $oldRecord ?: null, $data, "Updated SP member: {$fullName}");
            system_log('INFO', "SP member updated: {$fullName} (ID: {$id})");
            flash_set('success', "SP member \"{$fullName}\" updated successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'SP member update failed', ['error' => $e->getMessage(), 'sp_member_id' => $id, 'data' => $data]);
            flash_set('error', 'Failed to update SP member: ' . $e->getMessage());
        }

        redirect('master/sp-members');
    }

    // -------------------------------------------------------------------------
    // destroy — soft delete (returns JSON for AJAX)
    // -------------------------------------------------------------------------
    public function destroy(): void
    {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid SP member ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare(
            "SELECT sp_member_id, first_name, middle_name, last_name, suffix
             FROM sp_members WHERE sp_member_id = ? AND is_deleted = 0 LIMIT 1"
        );
        $stmt->execute([$id]);
        $spMember = $stmt->fetch();

        if (!$spMember) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'SP member not found.']);
            exit;
        }

        $oldStmt = $this->pdo->prepare("SELECT * FROM sp_members WHERE sp_member_id = ? AND is_deleted = 0 LIMIT 1");
        $oldStmt->execute([$id]);
        $oldRecord = $oldStmt->fetch();

        $stmt   = $this->pdo->prepare(
            "UPDATE sp_members SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?, updated_by = ? WHERE sp_member_id = ?"
        );
        $result = $stmt->execute([$userId, $userId, $id]);

        $fullName = $this->buildFullName($spMember);

        header('Content-Type: application/json');
        if ($result) {
            audit_log('DELETE', 'SpMember', (string)$id, $oldRecord ?: null, null, "Soft-deleted SP member: {$fullName}");
            system_log('INFO', "SP member deleted (soft): {$fullName} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "SP member \"{$fullName}\" deleted successfully."]);
        } else {
            system_log('WARNING', "Failed to delete SP member (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to delete SP member.']);
        }
        exit;
    }

    // -------------------------------------------------------------------------
    // toggleStatus — flip is_active (returns JSON for AJAX)
    // -------------------------------------------------------------------------
    public function toggleStatus(): void
    {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid SP member ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare(
            "SELECT sp_member_id, first_name, middle_name, last_name, suffix, is_active
             FROM sp_members WHERE sp_member_id = ? AND is_deleted = 0 LIMIT 1"
        );
        $stmt->execute([$id]);
        $spMember = $stmt->fetch();

        if (!$spMember) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'SP member not found.']);
            exit;
        }

        $newActive      = $spMember['is_active'] ? 0 : 1;
        $oldStatusLabel = $spMember['is_active'] ? 'Active' : 'Inactive';
        $newStatusLabel = $newActive ? 'Active' : 'Inactive';
        $action         = $newActive ? 'activated' : 'deactivated';

        $stmt   = $this->pdo->prepare(
            "UPDATE sp_members SET is_active = ?, updated_by = ? WHERE sp_member_id = ?"
        );
        $result = $stmt->execute([$newActive, $userId, $id]);

        $fullName = $this->buildFullName($spMember);

        header('Content-Type: application/json');
        if ($result) {
            audit_log(
                'UPDATE', 'SpMember', (string)$id,
                ['is_active' => $oldStatusLabel],
                ['is_active' => $newStatusLabel],
                "SP member \"{$fullName}\" {$action}"
            );
            system_log('INFO', "SP member status {$action}: {$fullName} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "SP member \"{$fullName}\" has been {$action}."]);
        } else {
            system_log('WARNING', "Failed to toggle SP member status (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
        }
        exit;
    }

    // -------------------------------------------------------------------------
    // handlePhotoUpload — validates and moves uploaded photo, returns path or error
    // -------------------------------------------------------------------------
    protected function handlePhotoUpload(array $file): array
    {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $maxSize      = 2 * 1024 * 1024; // 2 MB

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['path' => null, 'error' => 'Photo upload failed (error code: ' . $file['error'] . ').'];
        }

        if ($file['size'] > $maxSize) {
            return ['path' => null, 'error' => 'Photo must not exceed 2 MB.'];
        }

        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, $allowedTypes, true)) {
            return ['path' => null, 'error' => 'Only JPG, PNG, WebP, or GIF images are allowed.'];
        }

        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'spmember_' . uniqid('', true) . '.' . strtolower($ext);
        $uploadDir = __DIR__ . '/../../../public/uploads/sp-members/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $dest = $uploadDir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return ['path' => null, 'error' => 'Failed to save uploaded photo.'];
        }

        return ['path' => 'uploads/sp-members/' . $filename, 'error' => null];
    }

    // -------------------------------------------------------------------------
    // validateSpMember — shared validation for store and update
    // -------------------------------------------------------------------------
    protected function validateSpMember(array $data): array
    {
        $errors = [];

        if ($data['first_name'] === '') {
            $errors[] = 'First name is required.';
        } elseif (mb_strlen($data['first_name']) > 100) {
            $errors[] = 'First name must not exceed 100 characters.';
        }

        if ($data['last_name'] === '') {
            $errors[] = 'Last name is required.';
        } elseif (mb_strlen($data['last_name']) > 100) {
            $errors[] = 'Last name must not exceed 100 characters.';
        }

        if ($data['middle_name'] !== null && mb_strlen($data['middle_name']) > 100) {
            $errors[] = 'Middle name must not exceed 100 characters.';
        }

        if ($data['suffix'] !== null && mb_strlen($data['suffix']) > 20) {
            $errors[] = 'Suffix must not exceed 20 characters.';
        }

        if ($data['position'] === '') {
            $errors[] = 'Position is required.';
        } elseif (mb_strlen($data['position']) > 150) {
            $errors[] = 'Position must not exceed 150 characters.';
        }

        if ($data['district_id'] !== null && $data['district_id'] <= 0) {
            $errors[] = 'Invalid district selected.';
        }

        if ($data['sort_order'] < 0) {
            $errors[] = 'Sort order must be a non-negative integer.';
        }

        return $errors;
    }

    // -------------------------------------------------------------------------
    // buildFullName — helper to compose display name from name parts
    // -------------------------------------------------------------------------
    protected function buildFullName(array $data): string
    {
        $parts = array_filter([
            $data['first_name']  ?? null,
            $data['middle_name'] ?? null,
            $data['last_name']   ?? null,
            $data['suffix']      ?? null,
        ]);
        return implode(' ', $parts);
    }
}

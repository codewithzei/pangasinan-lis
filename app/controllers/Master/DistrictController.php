<?php

require_once __DIR__ . '/../../config/database.php';

class DistrictController
{
    protected PDO $pdo;

    public function __construct()
    {
        $database = new Database();
        $this->pdo = $database->connect();
    }

    public function index(): void
    {
        $search = trim($_GET['search'] ?? '');
        $filterStatus = $_GET['status'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;

        $whereClauses = ['d.is_deleted = 0'];
        $params = [];

        if ($search !== '') {
            $whereClauses[] = '(d.name LIKE ? OR d.description LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if ($filterStatus !== '') {
            $whereClauses[] = 'd.is_active = ?';
            $params[] = $filterStatus === 'active' ? 1 : 0;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM districts d WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT d.* FROM districts d
                WHERE {$whereSql}
                ORDER BY d.sort_order ASC, d.district_number ASC, d.name ASC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $districts = $stmt->fetchAll();

        $totalDistricts = (int)$this->pdo->query("SELECT COUNT(*) FROM districts WHERE is_deleted = 0")->fetchColumn();
        $activeDistricts = (int)$this->pdo->query("SELECT COUNT(*) FROM districts WHERE is_deleted = 0 AND is_active = 1")->fetchColumn();
        $inactiveDistricts = $totalDistricts - $activeDistricts;

        $success = flash_get('success');
        $error = flash_get('error');

        $pageTitle = 'Districts Management';
        $pageSubtitle = 'Manage Pangasinan legislative districts and their availability in dropdowns';
        $accent = 'primary';

        $viewDir = __DIR__ . '/../../../resources/views/master/districts';
        if (!is_dir($viewDir)) {
            mkdir($viewDir, 0777, true);
        }
        require $viewDir . '/index.php';
    }

    public function store(): void
    {
        $userId = auth_id();
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'district_number' => $_POST['district_number'] !== '' ? (int)$_POST['district_number'] : null,
            'description' => trim($_POST['description'] ?? ''),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $errors = $this->validateDistrict($data);

        $duplicateStmt = $this->pdo->prepare("SELECT id FROM districts WHERE name = ? AND is_deleted = 0 LIMIT 1");
        $duplicateStmt->execute([$data['name']]);
        if ($duplicateStmt->fetch()) {
            $errors[] = "A district with the name \"{$data['name']}\" already exists.";
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            old_set($_POST);
            redirect('master/districts');
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO districts (name, district_number, description, sort_order, is_active, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['name'],
                $data['district_number'],
                $data['description'] ?: null,
                $data['sort_order'],
                $data['is_active'],
                $userId,
                $userId,
            ]);
            $newId = (int)$this->pdo->lastInsertId();

            audit_log('CREATE', 'District', (string)$newId, null, $data, "Created district: {$data['name']}");
            system_log('INFO', "District created: {$data['name']} (ID: {$newId})");
            flash_set('success', "District \"{$data['name']}\" created successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'District create failed', ['error' => $e->getMessage(), 'data' => $data]);
            flash_set('error', 'Failed to create district: ' . $e->getMessage());
            old_set($_POST);
        }

        redirect('master/districts');
    }

    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid district ID.']);
            exit;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM districts WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $district = $stmt->fetch();

        if (!$district) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'District not found.']);
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $district]);
        exit;
    }

    public function update(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash_set('error', 'Invalid district ID.');
            redirect('master/districts');
        }

        $userId = auth_id();
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'district_number' => $_POST['district_number'] !== '' ? (int)$_POST['district_number'] : null,
            'description' => trim($_POST['description'] ?? ''),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $errors = $this->validateDistrict($data);

        $duplicateStmt = $this->pdo->prepare("SELECT id FROM districts WHERE name = ? AND is_deleted = 0 AND id != ? LIMIT 1");
        $duplicateStmt->execute([$data['name'], $id]);
        if ($duplicateStmt->fetch()) {
            $errors[] = "Another district with the name \"{$data['name']}\" already exists.";
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            redirect('master/districts');
        }

        try {
            $oldStmt = $this->pdo->prepare("SELECT * FROM districts WHERE id = ? AND is_deleted = 0 LIMIT 1");
            $oldStmt->execute([$id]);
            $oldRecord = $oldStmt->fetch();

            $stmt = $this->pdo->prepare("UPDATE districts SET name = ?, district_number = ?, description = ?, sort_order = ?, is_active = ?, updated_by = ? WHERE id = ? AND is_deleted = 0");
            $stmt->execute([
                $data['name'],
                $data['district_number'],
                $data['description'] ?: null,
                $data['sort_order'],
                $data['is_active'],
                $userId,
                $id,
            ]);

            audit_log('UPDATE', 'District', (string)$id, $oldRecord ?: null, $data, "Updated district: {$data['name']}");
            system_log('INFO', "District updated: {$data['name']} (ID: {$id})");
            flash_set('success', "District \"{$data['name']}\" updated successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'District update failed', ['error' => $e->getMessage(), 'district_id' => $id, 'data' => $data]);
            flash_set('error', 'Failed to update district: ' . $e->getMessage());
        }

        redirect('master/districts');
    }

    public function destroy(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid district ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name FROM districts WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $district = $stmt->fetch();

        if (!$district) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'District not found.']);
            exit;
        }

        $oldStmt = $this->pdo->prepare("SELECT * FROM districts WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $oldStmt->execute([$id]);
        $oldRecord = $oldStmt->fetch();

        $stmt = $this->pdo->prepare("UPDATE districts SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$userId, $userId, $id]);

        header('Content-Type: application/json');
        if ($result) {
            audit_log('DELETE', 'District', (string)$id, $oldRecord ?: null, null, "Soft-deleted district: {$district['name']}");
            system_log('INFO', "District deleted (soft): {$district['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "District \"{$district['name']}\" deleted successfully."]);
        } else {
            system_log('WARNING', "Failed to delete district (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to delete district.']);
        }
        exit;
    }

    public function toggleStatus(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid district ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name, is_active FROM districts WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $district = $stmt->fetch();

        if (!$district) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'District not found.']);
            exit;
        }

        $newActive = $district['is_active'] ? 0 : 1;
        $oldStatus = $district['is_active'] ? 'Active' : 'Inactive';
        $newStatusLabel = $newActive ? 'Active' : 'Inactive';
        $stmt = $this->pdo->prepare("UPDATE districts SET is_active = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$newActive, $userId, $id]);

        $action = $newActive ? 'activated' : 'deactivated';
        header('Content-Type: application/json');
        if ($result) {
            audit_log('UPDATE', 'District', (string)$id, ['is_active' => $oldStatus], ['is_active' => $newStatusLabel], "District \"{$district['name']}\" {$action}");
            system_log('INFO', "District status {$action}: {$district['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "District \"{$district['name']}\" has been {$action}."]);
        } else {
            system_log('WARNING', "Failed to toggle district status (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
        }
        exit;
    }

    protected function validateDistrict(array $data): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'District name is required.';
        } elseif (mb_strlen($data['name']) > 255) {
            $errors[] = 'District name must not exceed 255 characters.';
        }

        if ($data['district_number'] !== null && ($data['district_number'] < 1 || $data['district_number'] > 100)) {
            $errors[] = 'District number must be between 1 and 100, or left blank.';
        }

        if ($data['sort_order'] < 0) {
            $errors[] = 'Sort order must be a non-negative integer.';
        }

        return $errors;
    }
}

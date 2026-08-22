<?php

require_once __DIR__ . '/../../config/database.php';

class PositionController
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

        $whereClauses = ['p.is_deleted = 0'];
        $params = [];

        if ($search !== '') {
            $whereClauses[] = '(p.name LIKE ? OR p.abbreviation LIKE ? OR p.description LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if ($filterStatus !== '') {
            $whereClauses[] = 'p.is_active = ?';
            $params[] = $filterStatus === 'active' ? 1 : 0;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM positions p WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT p.* FROM positions p
                WHERE {$whereSql}
                ORDER BY p.sort_order ASC, p.name ASC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $positions = $stmt->fetchAll();

        $totalPositions = (int)$this->pdo->query("SELECT COUNT(*) FROM positions WHERE is_deleted = 0")->fetchColumn();
        $activePositions = (int)$this->pdo->query("SELECT COUNT(*) FROM positions WHERE is_deleted = 0 AND is_active = 1")->fetchColumn();
        $inactivePositions = $totalPositions - $activePositions;

        $success = flash_get('success');
        $error = flash_get('error');

        $pageTitle = 'Positions Management';
        $pageSubtitle = 'Manage Sanggunian position designations and their availability in dropdowns';
        $accent = 'primary';

        $viewDir = __DIR__ . '/../../../resources/views/master/positions';
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
            'abbreviation' => trim($_POST['abbreviation'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $errors = $this->validatePosition($data);

        $duplicateStmt = $this->pdo->prepare("SELECT id FROM positions WHERE name = ? AND is_deleted = 0 LIMIT 1");
        $duplicateStmt->execute([$data['name']]);
        if ($duplicateStmt->fetch()) {
            $errors[] = "A position with the name \"{$data['name']}\" already exists.";
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            old_set($_POST);
            redirect('master/positions');
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO positions (name, abbreviation, description, sort_order, is_active, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['name'],
                $data['abbreviation'] ?: null,
                $data['description'] ?: null,
                $data['sort_order'],
                $data['is_active'],
                $userId,
                $userId,
            ]);
            $newId = (int)$this->pdo->lastInsertId();

            audit_log('CREATE', 'Position', (string)$newId, null, $data, "Created position: {$data['name']}");
            system_log('INFO', "Position created: {$data['name']} (ID: {$newId})");
            flash_set('success', "Position \"{$data['name']}\" created successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'Position create failed', ['error' => $e->getMessage(), 'data' => $data]);
            flash_set('error', 'Failed to create position: ' . $e->getMessage());
            old_set($_POST);
        }

        redirect('master/positions');
    }

    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid position ID.']);
            exit;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM positions WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $position = $stmt->fetch();

        if (!$position) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Position not found.']);
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $position]);
        exit;
    }

    public function update(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash_set('error', 'Invalid position ID.');
            redirect('master/positions');
        }

        $userId = auth_id();
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'abbreviation' => trim($_POST['abbreviation'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $errors = $this->validatePosition($data);

        $duplicateStmt = $this->pdo->prepare("SELECT id FROM positions WHERE name = ? AND is_deleted = 0 AND id != ? LIMIT 1");
        $duplicateStmt->execute([$data['name'], $id]);
        if ($duplicateStmt->fetch()) {
            $errors[] = "Another position with the name \"{$data['name']}\" already exists.";
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            redirect('master/positions');
        }

        try {
            $oldStmt = $this->pdo->prepare("SELECT * FROM positions WHERE id = ? AND is_deleted = 0 LIMIT 1");
            $oldStmt->execute([$id]);
            $oldRecord = $oldStmt->fetch();

            $stmt = $this->pdo->prepare("UPDATE positions SET name = ?, abbreviation = ?, description = ?, sort_order = ?, is_active = ?, updated_by = ? WHERE id = ? AND is_deleted = 0");
            $stmt->execute([
                $data['name'],
                $data['abbreviation'] ?: null,
                $data['description'] ?: null,
                $data['sort_order'],
                $data['is_active'],
                $userId,
                $id,
            ]);

            audit_log('UPDATE', 'Position', (string)$id, $oldRecord ?: null, $data, "Updated position: {$data['name']}");
            system_log('INFO', "Position updated: {$data['name']} (ID: {$id})");
            flash_set('success', "Position \"{$data['name']}\" updated successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'Position update failed', ['error' => $e->getMessage(), 'position_id' => $id, 'data' => $data]);
            flash_set('error', 'Failed to update position: ' . $e->getMessage());
        }

        redirect('master/positions');
    }

    public function destroy(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid position ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name FROM positions WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $position = $stmt->fetch();

        if (!$position) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Position not found.']);
            exit;
        }

        $oldStmt = $this->pdo->prepare("SELECT * FROM positions WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $oldStmt->execute([$id]);
        $oldRecord = $oldStmt->fetch();

        $stmt = $this->pdo->prepare("UPDATE positions SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$userId, $userId, $id]);

        header('Content-Type: application/json');
        if ($result) {
            audit_log('DELETE', 'Position', (string)$id, $oldRecord ?: null, null, "Soft-deleted position: {$position['name']}");
            system_log('INFO', "Position deleted (soft): {$position['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "Position \"{$position['name']}\" deleted successfully."]);
        } else {
            system_log('WARNING', "Failed to delete position (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to delete position.']);
        }
        exit;
    }

    public function toggleStatus(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid position ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name, is_active FROM positions WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $position = $stmt->fetch();

        if (!$position) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Position not found.']);
            exit;
        }

        $newActive = $position['is_active'] ? 0 : 1;
        $oldStatus = $position['is_active'] ? 'Active' : 'Inactive';
        $newStatusLabel = $newActive ? 'Active' : 'Inactive';
        $stmt = $this->pdo->prepare("UPDATE positions SET is_active = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$newActive, $userId, $id]);

        $action = $newActive ? 'activated' : 'deactivated';
        header('Content-Type: application/json');
        if ($result) {
            audit_log('UPDATE', 'Position', (string)$id, ['is_active' => $oldStatus], ['is_active' => $newStatusLabel], "Position \"{$position['name']}\" {$action}");
            system_log('INFO', "Position status {$action}: {$position['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "Position \"{$position['name']}\" has been {$action}."]);
        } else {
            system_log('WARNING', "Failed to toggle position status (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
        }
        exit;
    }

    protected function validatePosition(array $data): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'Position name is required.';
        } elseif (mb_strlen($data['name']) > 255) {
            $errors[] = 'Position name must not exceed 255 characters.';
        }

        if ($data['abbreviation'] !== '' && mb_strlen($data['abbreviation']) > 50) {
            $errors[] = 'Abbreviation must not exceed 50 characters.';
        }

        if ($data['sort_order'] < 0) {
            $errors[] = 'Sort order must be a non-negative integer.';
        }

        return $errors;
    }
}

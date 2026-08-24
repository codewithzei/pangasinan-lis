<?php

require_once __DIR__ . '/../../config/database.php';

class RoutingOptionController
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

        $whereClauses = ['ro.is_deleted = 0'];
        $params = [];

        if ($search !== '') {
            $whereClauses[] = '(ro.name LIKE ? OR ro.description LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if ($filterStatus !== '') {
            $whereClauses[] = 'ro.is_active = ?';
            $params[] = $filterStatus === 'active' ? 1 : 0;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM routing_options ro WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT ro.* FROM routing_options ro
                WHERE {$whereSql}
                ORDER BY ro.sort_order ASC, ro.name ASC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $routingOptions = $stmt->fetchAll();

        $totalRoutingOptions = (int)$this->pdo->query("SELECT COUNT(*) FROM routing_options WHERE is_deleted = 0")->fetchColumn();
        $activeRoutingOptions = (int)$this->pdo->query("SELECT COUNT(*) FROM routing_options WHERE is_deleted = 0 AND is_active = 1")->fetchColumn();
        $inactiveRoutingOptions = $totalRoutingOptions - $activeRoutingOptions;

        $success = flash_get('success');
        $error = flash_get('error');

        $pageTitle = 'Routing Options Management';
        $pageSubtitle = 'Manage routing destinations and workflow stages for document movement';
        $accent = 'primary';

        $viewDir = __DIR__ . '/../../../resources/views/master/routing-options';
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
            'description' => trim($_POST['description'] ?? ''),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $errors = $this->validateRoutingOption($data);

        $duplicateStmt = $this->pdo->prepare("SELECT id FROM routing_options WHERE name = ? AND is_deleted = 0 LIMIT 1");
        $duplicateStmt->execute([$data['name']]);
        if ($duplicateStmt->fetch()) {
            $errors[] = "A routing option with the name \"{$data['name']}\" already exists.";
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            old_set($_POST);
            redirect('master/routing-options');
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO routing_options (name, description, sort_order, is_active, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['name'],
                $data['description'] ?: null,
                $data['sort_order'],
                $data['is_active'],
                $userId,
                $userId,
            ]);
            $newId = (int)$this->pdo->lastInsertId();

            audit_log('CREATE', 'RoutingOption', (string)$newId, null, $data, "Created routing option: {$data['name']}");
            system_log('INFO', "Routing option created: {$data['name']} (ID: {$newId})");
            flash_set('success', "Routing option \"{$data['name']}\" created successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'Routing option create failed', ['error' => $e->getMessage(), 'data' => $data]);
            flash_set('error', 'Failed to create routing option: ' . $e->getMessage());
            old_set($_POST);
        }

        redirect('master/routing-options');
    }

    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid routing option ID.']);
            exit;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM routing_options WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $routingOption = $stmt->fetch();

        if (!$routingOption) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Routing option not found.']);
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $routingOption]);
        exit;
    }

    public function update(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash_set('error', 'Invalid routing option ID.');
            redirect('master/routing-options');
        }

        $userId = auth_id();
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $errors = $this->validateRoutingOption($data);

        $duplicateStmt = $this->pdo->prepare("SELECT id FROM routing_options WHERE name = ? AND is_deleted = 0 AND id != ? LIMIT 1");
        $duplicateStmt->execute([$data['name'], $id]);
        if ($duplicateStmt->fetch()) {
            $errors[] = "Another routing option with the name \"{$data['name']}\" already exists.";
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            redirect('master/routing-options');
        }

        try {
            $oldStmt = $this->pdo->prepare("SELECT * FROM routing_options WHERE id = ? AND is_deleted = 0 LIMIT 1");
            $oldStmt->execute([$id]);
            $oldRecord = $oldStmt->fetch();

            $stmt = $this->pdo->prepare("UPDATE routing_options SET name = ?, description = ?, sort_order = ?, is_active = ?, updated_by = ? WHERE id = ? AND is_deleted = 0");
            $stmt->execute([
                $data['name'],
                $data['description'] ?: null,
                $data['sort_order'],
                $data['is_active'],
                $userId,
                $id,
            ]);

            audit_log('UPDATE', 'RoutingOption', (string)$id, $oldRecord ?: null, $data, "Updated routing option: {$data['name']}");
            system_log('INFO', "Routing option updated: {$data['name']} (ID: {$id})");
            flash_set('success', "Routing option \"{$data['name']}\" updated successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'Routing option update failed', ['error' => $e->getMessage(), 'routing_option_id' => $id, 'data' => $data]);
            flash_set('error', 'Failed to update routing option: ' . $e->getMessage());
        }

        redirect('master/routing-options');
    }

    public function destroy(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid routing option ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name FROM routing_options WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $routingOption = $stmt->fetch();

        if (!$routingOption) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Routing option not found.']);
            exit;
        }

        $oldStmt = $this->pdo->prepare("SELECT * FROM routing_options WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $oldStmt->execute([$id]);
        $oldRecord = $oldStmt->fetch();

        $stmt = $this->pdo->prepare("UPDATE routing_options SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$userId, $userId, $id]);

        header('Content-Type: application/json');
        if ($result) {
            audit_log('DELETE', 'RoutingOption', (string)$id, $oldRecord ?: null, null, "Soft-deleted routing option: {$routingOption['name']}");
            system_log('INFO', "Routing option deleted (soft): {$routingOption['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "Routing option \"{$routingOption['name']}\" deleted successfully."]);
        } else {
            system_log('WARNING', "Failed to delete routing option (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to delete routing option.']);
        }
        exit;
    }

    public function toggleStatus(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid routing option ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name, is_active FROM routing_options WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $routingOption = $stmt->fetch();

        if (!$routingOption) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Routing option not found.']);
            exit;
        }

        $newActive = $routingOption['is_active'] ? 0 : 1;
        $oldStatus = $routingOption['is_active'] ? 'Active' : 'Inactive';
        $newStatusLabel = $newActive ? 'Active' : 'Inactive';
        $stmt = $this->pdo->prepare("UPDATE routing_options SET is_active = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$newActive, $userId, $id]);

        $action = $newActive ? 'activated' : 'deactivated';
        header('Content-Type: application/json');
        if ($result) {
            audit_log('UPDATE', 'RoutingOption', (string)$id, ['is_active' => $oldStatus], ['is_active' => $newStatusLabel], "Routing option \"{$routingOption['name']}\" {$action}");
            system_log('INFO', "Routing option status {$action}: {$routingOption['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "Routing option \"{$routingOption['name']}\" has been {$action}."]);
        } else {
            system_log('WARNING', "Failed to toggle routing option status (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
        }
        exit;
    }

    protected function validateRoutingOption(array $data): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'Routing option name is required.';
        } elseif (mb_strlen($data['name']) > 50) {
            $errors[] = 'Routing option name must not exceed 50 characters.';
        }

        if (!empty($data['description']) && mb_strlen($data['description']) > 255) {
            $errors[] = 'Description must not exceed 255 characters.';
        }

        if ($data['sort_order'] < 0) {
            $errors[] = 'Sort order must be a non-negative integer.';
        }

        return $errors;
    }
}

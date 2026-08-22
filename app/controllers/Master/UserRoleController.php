<?php 

require_once __DIR__ . '/../../config/database.php';

class UserRoleController
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

        $whereClauses = ['r.is_deleted = 0'];
        $params = [];

        if ($search !== '') {
            $whereClauses[] = '(r.name LIKE ? OR r.description LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $whereSql = implode(' AND ', $whereClauses);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM roles r WHERE $whereSql");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT r.* FROM roles r
                WHERE {$whereSql}
                ORDER BY r.sort_order ASC, r.name ASC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $roles = $stmt->fetchAll();

        $totalRoles = (int)$this->pdo->query("SELECT COUNT(*) FROM roles WHERE is_deleted = 0")->fetchColumn();
        $activeRoles = (int)$this->pdo->query("SELECT COUNT(*) FROM roles WHERE is_deleted = 0 AND is_active = 1")->fetchColumn();
        $inactiveRoles = $totalRoles - $activeRoles;

        $success = flash_get('success');
        $error = flash_get('error');

        $pageTitle = 'User Roles Management';
        $pageSubtitle = 'Manage user roles and their permissions';
        $accent = 'primary';

        $viewDir = __DIR__ . '/../../../resources/views/master/user-roles';
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

        $errors = $this->validateRole($data);

        $duplicateStmt = $this->pdo->prepare("SELECT id FROM roles WHERE name = ? AND is_deleted = 0 LIMIT 1");
        $duplicateStmt->execute([$data['name']]);
        if ($duplicateStmt->fetch()) {
            $errors[] = "A role with the name \"{$data['name']}\" already exists.";
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            old_set($_POST);
            redirect('master/user-roles');
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO roles (name, description, sort_order, is_active, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['name'],
                $data['description'] ?: null,
                $data['sort_order'],
                $data['is_active'],
                $userId,
                $userId,
            ]);
            $newId = (int)$this->pdo->lastInsertId();

            audit_log('CREATE', 'Role', (string)$newId, null, $data, "Created role: {$data['name']}");
            system_log('INFO', "Role created: {$data['name']} (ID: {$newId})");
            flash_set('success', "Role \"{$data['name']}\" created successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'Role create failed', ['error' => $e->getMessage(), 'data' => $data]);
            flash_set('error', 'Failed to create role: ' . $e->getMessage());
            old_set($_POST);
        }

        redirect('master/user-roles');
    }

    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid role ID.']);
            exit;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM roles WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $role = $stmt->fetch();

        if (!$role) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Role not found.']);
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $role]);
        exit;
    }

    public function update(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash_set('error', 'Invalid role ID.');
            redirect('master/user-roles');
        }

        $userId = auth_id();
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $errors = $this->validateRole($data);

        $duplicateStmt = $this->pdo->prepare("SELECT id FROM roles WHERE name = ? AND is_deleted = 0 AND id != ? LIMIT 1");
        $duplicateStmt->execute([$data['name'], $id]);
        if ($duplicateStmt->fetch()) {
            $errors[] = "Another role with the name \"{$data['name']}\" already exists.";
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            redirect('master/user-roles');
        }

        try {
            $oldStmt = $this->pdo->prepare("SELECT * FROM roles WHERE id = ? AND is_deleted = 0 LIMIT 1");
            $oldStmt->execute([$id]);
            $oldRecord = $oldStmt->fetch();

            $stmt = $this->pdo->prepare("UPDATE roles SET name = ?, description = ?, sort_order = ?, is_active = ?, updated_by = ? WHERE id = ? AND is_deleted = 0");
            $stmt->execute([
                $data['name'],
                $data['description'] ?: null,
                $data['sort_order'],
                $data['is_active'],
                $userId,
                $id,
            ]);

            audit_log('UPDATE', 'Role', (string)$id, $oldRecord ?: null, $data, "Updated role: {$data['name']}");
            system_log('INFO', "Role updated: {$data['name']} (ID: {$id})");
            flash_set('success', "Role \"{$data['name']}\" updated successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'Role update failed', ['error' => $e->getMessage(), 'role_id' => $id, 'data' => $data]);
            flash_set('error', 'Failed to update role: ' . $e->getMessage());
        }

        redirect('master/user-roles');
    }

    public function destroy(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid role ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name FROM roles WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $role = $stmt->fetch();

        if (!$role) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Role not found.']);
            exit;
        }

        $oldStmt = $this->pdo->prepare("SELECT * FROM roles WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $oldStmt->execute([$id]);
        $oldRecord = $oldStmt->fetch();

        $stmt = $this->pdo->prepare("UPDATE roles SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$userId, $userId, $id]);

        header('Content-Type: application/json');
        if ($result) {
            audit_log('DELETE', 'Role', (string)$id, $oldRecord ?: null, null, "Soft-deleted role: {$role['name']}");
            system_log('INFO', "Role deleted (soft): {$role['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "Role \"{$role['name']}\" deleted successfully."]);
        } else {
            system_log('WARNING', "Failed to delete role (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to delete role.']);
        }
        exit;
    }

    public function toggleStatus(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid role ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name, is_active FROM roles WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $role = $stmt->fetch();

        if (!$role) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Role not found.']);
            exit;
        }

        $newActive = $role['is_active'] ? 0 : 1;
        $oldStatus = $role['is_active'] ? 'Active' : 'Inactive';
        $newStatusLabel = $newActive ? 'Active' : 'Inactive';
        $stmt = $this->pdo->prepare("UPDATE roles SET is_active = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$newActive, $userId, $id]);

        $action = $newActive ? 'activated' : 'deactivated';
        header('Content-Type: application/json');
        if ($result) {
            audit_log('UPDATE', 'Role', (string)$id, ['is_active' => $oldStatus], ['is_active' => $newStatusLabel], "Role \"{$role['name']}\" {$action}");
            system_log('INFO', "Role status {$action}: {$role['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "Role \"{$role['name']}\" has been {$action}."]);
        } else {
            system_log('WARNING', "Failed to toggle role status (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
        }
        exit;
    }

    protected function validateRole(array $data): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'Role name is required.';
        } elseif (mb_strlen($data['name']) > 255) {
            $errors[] = 'Role name must not exceed 255 characters.';
        }

        if ($data['description'] !== '' && mb_strlen($data['description']) > 255) {
            $errors[] = 'Description must not exceed 255 characters.';
        }

        if ($data['sort_order'] < 0) {
            $errors[] = 'Sort order must be a non-negative integer.';
        }

        return $errors;
    }
}


?>
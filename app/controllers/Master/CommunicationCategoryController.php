<?php

require_once __DIR__ . '/../../config/database.php';

class CommunicationCategoryController
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

        $whereClauses = ['cc.is_deleted = 0'];
        $params = [];

        if ($search !== '') {
            $whereClauses[] = '(cc.name LIKE ? OR cc.description LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if ($filterStatus !== '') {
            $whereClauses[] = 'cc.is_active = ?';
            $params[] = $filterStatus === 'active' ? 1 : 0;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM communication_categories cc WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT cc.* FROM communication_categories cc
                WHERE {$whereSql}
                ORDER BY cc.sort_order ASC, cc.name ASC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $communicationCategories = $stmt->fetchAll();

        $totalCommunicationCategories = (int)$this->pdo->query("SELECT COUNT(*) FROM communication_categories WHERE is_deleted = 0")->fetchColumn();
        $activeCommunicationCategories = (int)$this->pdo->query("SELECT COUNT(*) FROM communication_categories WHERE is_deleted = 0 AND is_active = 1")->fetchColumn();
        $inactiveCommunicationCategories = $totalCommunicationCategories - $activeCommunicationCategories;

        $success = flash_get('success');
        $error = flash_get('error');

        $pageTitle = 'Communication Categories Management';
        $pageSubtitle = 'Manage communication categories for document movement';
        $accent = 'primary';

        $viewDir = __DIR__ . '/../../../resources/views/master/communication-categories';
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

        $errors = $this->validateCommunicationCategory($data);

        $duplicateStmt = $this->pdo->prepare("SELECT id FROM communication_categories WHERE name = ? AND is_deleted = 0 LIMIT 1");
        $duplicateStmt->execute([$data['name']]);
        if ($duplicateStmt->fetch()) {
            $errors[] = "A communication category with the name \"{$data['name']}\" already exists.";
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            old_set($_POST);
            redirect('master/communication-categories');
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO communication_categories (name, description, sort_order, is_active, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['name'],
                $data['description'] ?: null,
                $data['sort_order'],
                $data['is_active'],
                $userId,
                $userId,
            ]);
            $newId = (int)$this->pdo->lastInsertId();

            audit_log('CREATE', 'CommunicationCategory', (string)$newId, null, $data, "Created communication category: {$data['name']}");
            system_log('INFO', "Communication category created: {$data['name']} (ID: {$newId})");
            flash_set('success', "Communication category \"{$data['name']}\" created successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'Communication category create failed', ['error' => $e->getMessage(), 'data' => $data]);
            flash_set('error', 'Failed to create communication category: ' . $e->getMessage());
            old_set($_POST);
        }

        redirect('master/communication-categories');
    }

    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid communication category ID.']);
            exit;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM communication_categories WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $communicationCategory = $stmt->fetch();

        if (!$communicationCategory) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Communication category not found.']);
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $communicationCategory]);
        exit;
    }

    public function update(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash_set('error', 'Invalid communication category ID.');
            redirect('master/communication-categories');
        }

        $userId = auth_id();
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $errors = $this->validateCommunicationCategory($data);

        $duplicateStmt = $this->pdo->prepare("SELECT id FROM communication_categories WHERE name = ? AND is_deleted = 0 AND id != ? LIMIT 1");
        $duplicateStmt->execute([$data['name'], $id]);
        if ($duplicateStmt->fetch()) {
            $errors[] = "Another communication category with the name \"{$data['name']}\" already exists.";
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            redirect('master/communication-categories');
        }

        try {
            $oldStmt = $this->pdo->prepare("SELECT * FROM communication_categories WHERE id = ? AND is_deleted = 0 LIMIT 1");
            $oldStmt->execute([$id]);
            $oldRecord = $oldStmt->fetch();

            $stmt = $this->pdo->prepare("UPDATE communication_categories SET name = ?, description = ?, sort_order = ?, is_active = ?, updated_by = ? WHERE id = ? AND is_deleted = 0");
            $stmt->execute([
                $data['name'],
                $data['description'] ?: null,
                $data['sort_order'],
                $data['is_active'],
                $userId,
                $id,
            ]);

            audit_log('UPDATE', 'CommunicationCategory', (string)$id, $oldRecord ?: null, $data, "Updated communication category: {$data['name']}");
            system_log('INFO', "Communication category updated: {$data['name']} (ID: {$id})");
            flash_set('success', "Communication category \"{$data['name']}\" updated successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'Communication category update failed', ['error' => $e->getMessage(), 'communication_category_id' => $id, 'data' => $data]);
            flash_set('error', 'Failed to update communication category: ' . $e->getMessage());
        }

        redirect('master/communication-categories');
    }

    public function destroy(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid communication category ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name FROM communication_categories WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $communicationCategory = $stmt->fetch();

        if (!$communicationCategory) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Communication category not found.']);
            exit;
        }

        $oldStmt = $this->pdo->prepare("SELECT * FROM communication_categories WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $oldStmt->execute([$id]);
        $oldRecord = $oldStmt->fetch();

        $stmt = $this->pdo->prepare("UPDATE communication_categories SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$userId, $userId, $id]);

        header('Content-Type: application/json');
        if ($result) {
            audit_log('DELETE', 'CommunicationCategory', (string)$id, $oldRecord ?: null, null, "Soft-deleted communication category: {$communicationCategory['name']}");
            system_log('INFO', "Communication category deleted (soft): {$communicationCategory['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "Communication category \"{$communicationCategory['name']}\" deleted successfully."]);
        } else {
            system_log('WARNING', "Failed to delete communication category (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to delete communication category.']);
        }
        exit;
    }

    public function toggleStatus(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid communication category ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name, is_active FROM communication_categories WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $communicationCategory = $stmt->fetch();

        if (!$communicationCategory) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Communication category not found.']);
            exit;
        }

        $newActive = $communicationCategory['is_active'] ? 0 : 1;
        $oldStatus = $communicationCategory['is_active'] ? 'Active' : 'Inactive';
        $newStatusLabel = $newActive ? 'Active' : 'Inactive';
        $stmt = $this->pdo->prepare("UPDATE communication_categories SET is_active = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$newActive, $userId, $id]);

        $action = $newActive ? 'activated' : 'deactivated';
        header('Content-Type: application/json');
        if ($result) {
            audit_log('UPDATE', 'CommunicationCategory', (string)$id, ['is_active' => $oldStatus], ['is_active' => $newStatusLabel], "Communication category \"{$communicationCategory['name']}\" {$action}");
            system_log('INFO', "Communication category status {$action}: {$communicationCategory['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "Communication category \"{$communicationCategory['name']}\" has been {$action}."]);
        } else {
            system_log('WARNING', "Failed to toggle communication category status (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
        }
        exit;
    }

    protected function validateCommunicationCategory(array $data): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'Communication category name is required.';
        } elseif (mb_strlen($data['name']) > 50) {
            $errors[] = 'Communication category name must not exceed 50 characters.';
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

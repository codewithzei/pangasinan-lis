<?php

require_once __DIR__ . '/../../config/database.php';

class SourceTypeController
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

        $whereClauses = ['st.is_deleted = 0'];
        $params = [];

        if ($search !== '') {
            $whereClauses[] = '(st.name LIKE ? OR st.description LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if ($filterStatus !== '') {
            $whereClauses[] = 'st.is_active = ?';
            $params[] = $filterStatus === 'active' ? 1 : 0;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM source_types st WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT st.* FROM source_types st
                WHERE {$whereSql}
                ORDER BY st.sort_order ASC, st.name ASC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $sourceTypes = $stmt->fetchAll();

        $totalSourceTypes = (int)$this->pdo->query("SELECT COUNT(*) FROM source_types WHERE is_deleted = 0")->fetchColumn();
        $activeSourceTypes = (int)$this->pdo->query("SELECT COUNT(*) FROM source_types WHERE is_deleted = 0 AND is_active = 1")->fetchColumn();
        $inactiveSourceTypes = $totalSourceTypes - $activeSourceTypes;

        $success = flash_get('success');
        $error = flash_get('error');

        $pageTitle = 'Source Types Management';
        $pageSubtitle = 'Manage source types for organizing your data';
        $accent = 'primary';

        $viewDir = __DIR__ . '/../../../resources/views/master/source-types';
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

        $errors = $this->validateSourceType($data);

        $duplicateStmt = $this->pdo->prepare("SELECT id FROM source_types WHERE name = ? AND is_deleted = 0 LIMIT 1");
        $duplicateStmt->execute([$data['name']]);
        if ($duplicateStmt->fetch()) {
            $errors[] = "A source type with the name \"{$data['name']}\" already exists.";
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            old_set($_POST);
            redirect('master/source-types');
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO source_types (name, description, sort_order, is_active, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['name'],
                $data['description'] ?: null,
                $data['sort_order'],
                $data['is_active'],
                $userId,
                $userId,
            ]);
            $newId = (int)$this->pdo->lastInsertId();

            audit_log('CREATE', 'SourceType', (string)$newId, null, $data, "Created source type: {$data['name']}");
            system_log('INFO', "Source type created: {$data['name']} (ID: {$newId})");
            flash_set('success', "Source type \"{$data['name']}\" created successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'Source type create failed', ['error' => $e->getMessage(), 'data' => $data]);
            flash_set('error', 'Failed to create source type: ' . $e->getMessage());
            old_set($_POST);
        }

        redirect('master/source-types');
    }

    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid source type ID.']);
            exit;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM source_types WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $sourceType = $stmt->fetch();

        if (!$sourceType) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Source type not found.']);
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $sourceType]);
        exit;
    }

    public function update(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash_set('error', 'Invalid source type ID.');
            redirect('master/source-types');
        }

        $userId = auth_id();
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $errors = $this->validateSourceType($data);

        $duplicateStmt = $this->pdo->prepare("SELECT id FROM source_types WHERE name = ? AND is_deleted = 0 AND id != ? LIMIT 1");
        $duplicateStmt->execute([$data['name'], $id]);
        if ($duplicateStmt->fetch()) {
            $errors[] = "Another source type with the name \"{$data['name']}\" already exists.";
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            redirect('master/source-types');
        }

        try {
            $oldStmt = $this->pdo->prepare("SELECT * FROM source_types WHERE id = ? AND is_deleted = 0 LIMIT 1");
            $oldStmt->execute([$id]);
            $oldRecord = $oldStmt->fetch();

            $stmt = $this->pdo->prepare("UPDATE source_types SET name = ?, description = ?, sort_order = ?, is_active = ?, updated_by = ? WHERE id = ? AND is_deleted = 0");
            $stmt->execute([
                $data['name'],
                $data['description'] ?: null,
                $data['sort_order'],
                $data['is_active'],
                $userId,
                $id,
            ]);

            audit_log('UPDATE', 'SourceType', (string)$id, $oldRecord ?: null, $data, "Updated source type: {$data['name']}");
            system_log('INFO', "Source type updated: {$data['name']} (ID: {$id})");
            flash_set('success', "Source type \"{$data['name']}\" updated successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'Source type update failed', ['error' => $e->getMessage(), 'source_type_id' => $id, 'data' => $data]);
            flash_set('error', 'Failed to update source type: ' . $e->getMessage());
        }

        redirect('master/source-types');
    }

    public function destroy(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid source type ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name FROM source_types WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $sourceType = $stmt->fetch();

        if (!$sourceType) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Source type not found.']);
            exit;
        }

        $oldStmt = $this->pdo->prepare("SELECT * FROM source_types WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $oldStmt->execute([$id]);
        $oldRecord = $oldStmt->fetch();

        $stmt = $this->pdo->prepare("UPDATE source_types SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$userId, $userId, $id]);

        header('Content-Type: application/json');
        if ($result) {
            audit_log('DELETE', 'SourceType', (string)$id, $oldRecord ?: null, null, "Soft-deleted source type: {$sourceType['name']}");
            system_log('INFO', "Source type deleted (soft): {$sourceType['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "Source type \"{$sourceType['name']}\" deleted successfully."]);
        } else {
            system_log('WARNING', "Failed to delete source type (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to delete source type.']);
        }
        exit;
    }

    public function toggleStatus(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid source type ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name, is_active FROM source_types WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $sourceType = $stmt->fetch();

        if (!$sourceType) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Source type not found.']);
            exit;
        }

        $newActive = $sourceType['is_active'] ? 0 : 1;
        $oldStatus = $sourceType['is_active'] ? 'Active' : 'Inactive';
        $newStatusLabel = $newActive ? 'Active' : 'Inactive';
        $stmt = $this->pdo->prepare("UPDATE source_types SET is_active = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$newActive, $userId, $id]);

        $action = $newActive ? 'activated' : 'deactivated';
        header('Content-Type: application/json');
        if ($result) {
            audit_log('UPDATE', 'SourceType', (string)$id, ['is_active' => $oldStatus], ['is_active' => $newStatusLabel], "Source type \"{$sourceType['name']}\" {$action}");
            system_log('INFO', "Source type status {$action}: {$sourceType['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "Source type \"{$sourceType['name']}\" has been {$action}."]);
        } else {
            system_log('WARNING', "Failed to toggle source type status (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
        }
        exit;
    }

    protected function validateSourceType(array $data): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'Source type name is required.';
        } elseif (mb_strlen($data['name']) > 50) {
            $errors[] = 'Source type name must not exceed 50 characters.';
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

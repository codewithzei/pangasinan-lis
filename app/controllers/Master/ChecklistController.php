<?php

require_once __DIR__ . '/../../config/database.php';

class ChecklistController
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

        $whereClauses = ['c.is_deleted = 0'];
        $params = [];

        if ($search !== '') {
            $whereClauses[] = '(c.name LIKE ? OR c.description LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if ($filterStatus !== '') {
            $whereClauses[] = 'c.is_active = ?';
            $params[] = $filterStatus === 'active' ? 1 : 0;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM checklists c WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT c.*,
            (SELECT COUNT(*) FROM checklist_document_types cdt WHERE cdt.checklist_id = c.id) AS document_type_count
            FROM checklists c
            WHERE {$whereSql}
            ORDER BY c.sort_order ASC, c.name ASC
            LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $checklists = $stmt->fetchAll();

        $totalChecklists = (int)$this->pdo->query("SELECT COUNT(*) FROM checklists WHERE is_deleted = 0")->fetchColumn();
        $activeChecklists = (int)$this->pdo->query("SELECT COUNT(*) FROM checklists WHERE is_deleted = 0 AND is_active = 1")->fetchColumn();
        $inactiveChecklists = $totalChecklists - $activeChecklists;

        $success = flash_get('success');
        $error = flash_get('error');

        $pageTitle = 'Checklist/Requirements Management';
        $pageSubtitle = 'Manage document checklist items and requirements for legislative processes';
        $accent = 'primary';

        $viewDir = __DIR__ . '/../../../resources/views/master/checklists';
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

        $errors = $this->validateChecklist($data);

        $duplicateStmt = $this->pdo->prepare("SELECT id FROM checklists WHERE name = ? AND is_deleted = 0 LIMIT 1");
        $duplicateStmt->execute([$data['name']]);
        if ($duplicateStmt->fetch()) {
            $errors[] = "A checklist item with the name \"{$data['name']}\" already exists.";
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            old_set($_POST);
            redirect('master/checklists');
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO checklists (name, description, sort_order, is_active, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['name'],
                $data['description'] ?: null,
                $data['sort_order'],
                $data['is_active'],
                $userId,
                $userId,
            ]);
            $newId = (int)$this->pdo->lastInsertId();

            audit_log('CREATE', 'Checklist', (string)$newId, null, $data, "Created checklist item: {$data['name']}");
            system_log('INFO', "Checklist item created: {$data['name']} (ID: {$newId})");
            flash_set('success', "Checklist item \"{$data['name']}\" created successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'Checklist create failed', ['error' => $e->getMessage(), 'data' => $data]);
            flash_set('error', 'Failed to create checklist item: ' . $e->getMessage());
            old_set($_POST);
        }

        redirect('master/checklists');
    }

    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid checklist ID.']);
            exit;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM checklists WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $checklist = $stmt->fetch();

        if (!$checklist) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Checklist item not found.']);
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $checklist]);
        exit;
    }

    public function update(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash_set('error', 'Invalid checklist ID.');
            redirect('master/checklists');
        }

        $userId = auth_id();
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $errors = $this->validateChecklist($data);

        $duplicateStmt = $this->pdo->prepare("SELECT id FROM checklists WHERE name = ? AND is_deleted = 0 AND id != ? LIMIT 1");
        $duplicateStmt->execute([$data['name'], $id]);
        if ($duplicateStmt->fetch()) {
            $errors[] = "Another checklist item with the name \"{$data['name']}\" already exists.";
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            redirect('master/checklists');
        }

        try {
            $oldStmt = $this->pdo->prepare("SELECT * FROM checklists WHERE id = ? AND is_deleted = 0 LIMIT 1");
            $oldStmt->execute([$id]);
            $oldRecord = $oldStmt->fetch();

            $stmt = $this->pdo->prepare("UPDATE checklists SET name = ?, description = ?, sort_order = ?, is_active = ?, updated_by = ? WHERE id = ? AND is_deleted = 0");
            $stmt->execute([
                $data['name'],
                $data['description'] ?: null,
                $data['sort_order'],
                $data['is_active'],
                $userId,
                $id,
            ]);

            audit_log('UPDATE', 'Checklist', (string)$id, $oldRecord ?: null, $data, "Updated checklist item: {$data['name']}");
            system_log('INFO', "Checklist item updated: {$data['name']} (ID: {$id})");
            flash_set('success', "Checklist item \"{$data['name']}\" updated successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'Checklist update failed', ['error' => $e->getMessage(), 'checklist_id' => $id, 'data' => $data]);
            flash_set('error', 'Failed to update checklist item: ' . $e->getMessage());
        }

        redirect('master/checklists');
    }

    public function destroy(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid checklist ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name FROM checklists WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $checklist = $stmt->fetch();

        if (!$checklist) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Checklist item not found.']);
            exit;
        }

        $oldStmt = $this->pdo->prepare("SELECT * FROM checklists WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $oldStmt->execute([$id]);
        $oldRecord = $oldStmt->fetch();

        $stmt = $this->pdo->prepare("UPDATE checklists SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$userId, $userId, $id]);

        header('Content-Type: application/json');
        if ($result) {
            audit_log('DELETE', 'Checklist', (string)$id, $oldRecord ?: null, null, "Soft-deleted checklist item: {$checklist['name']}");
            system_log('INFO', "Checklist item deleted (soft): {$checklist['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "Checklist item \"{$checklist['name']}\" deleted successfully."]);
        } else {
            system_log('WARNING', "Failed to delete checklist item (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to delete checklist item.']);
        }
        exit;
    }

    public function toggleStatus(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid checklist ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name, is_active FROM checklists WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $checklist = $stmt->fetch();

        if (!$checklist) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Checklist item not found.']);
            exit;
        }

        $newActive = $checklist['is_active'] ? 0 : 1;
        $oldStatus = $checklist['is_active'] ? 'Active' : 'Inactive';
        $newStatusLabel = $newActive ? 'Active' : 'Inactive';
        $stmt = $this->pdo->prepare("UPDATE checklists SET is_active = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$newActive, $userId, $id]);

        $action = $newActive ? 'activated' : 'deactivated';
        header('Content-Type: application/json');
        if ($result) {
            audit_log('UPDATE', 'Checklist', (string)$id, ['is_active' => $oldStatus], ['is_active' => $newStatusLabel], "Checklist item \"{$checklist['name']}\" {$action}");
            system_log('INFO', "Checklist item status {$action}: {$checklist['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "Checklist item \"{$checklist['name']}\" has been {$action}."]);
        } else {
            system_log('WARNING', "Failed to toggle checklist item status (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
        }
        exit;
    }

    public function show(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            flash_set('error', 'Invalid checklist ID.');
            redirect('master/checklists');
        }

        $checklistStmt = $this->pdo->prepare("SELECT c.*,
            (SELECT COUNT(*) FROM checklist_document_types cdt WHERE cdt.checklist_id = c.id) AS document_type_count
            FROM checklists c WHERE c.id = ? AND c.is_deleted = 0 LIMIT 1");
        $checklistStmt->execute([$id]);
        $checklist = $checklistStmt->fetch();

        if (!$checklist) {
            flash_set('error', 'Checklist item not found.');
            redirect('master/checklists');
        }

        $assignedStmt = $this->pdo->prepare("SELECT cdt.*, dt.name as document_type_name, dt.badge_color
            FROM checklist_document_types cdt
            INNER JOIN document_types dt ON dt.id = cdt.document_type_id
            WHERE cdt.checklist_id = ? AND dt.is_deleted = 0
            ORDER BY cdt.sort_order ASC, dt.name ASC");
        $assignedStmt->execute([$id]);
        $assignedDocumentTypes = $assignedStmt->fetchAll();

        $availableStmt = $this->pdo->prepare("SELECT dt.id, dt.name, dt.badge_color, dt.description
            FROM document_types dt
            WHERE dt.is_deleted = 0 AND dt.is_active = 1
            AND dt.id NOT IN (SELECT document_type_id FROM checklist_document_types WHERE checklist_id = ?)
            ORDER BY dt.sort_order ASC, dt.name ASC");
        $availableStmt->execute([$id]);
        $availableDocumentTypes = $availableStmt->fetchAll();

        $success = flash_get('success');
        $error = flash_get('error');

        $pageTitle = 'Checklist Details: ' . htmlspecialchars($checklist['name']);
        $pageSubtitle = $checklist['description'] ?? 'Manage document type assignments for this checklist item';
        $accent = 'primary';

        $viewDir = __DIR__ . '/../../../resources/views/master/checklists';
        require $viewDir . '/show.php';
    }

    protected function validateChecklist(array $data): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'Checklist name is required.';
        } elseif (mb_strlen($data['name']) > 255) {
            $errors[] = 'Checklist name must not exceed 255 characters.';
        }

        if ($data['sort_order'] < 0) {
            $errors[] = 'Sort order must be a non-negative integer.';
        }

        return $errors;
    }
}

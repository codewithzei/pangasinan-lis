<?php

require_once __DIR__ . '/../../config/database.php';

class CommitteeController
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

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM committees c WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT c.* FROM committees c
                WHERE {$whereSql}
                ORDER BY c.sort_order ASC, c.name ASC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $committees = $stmt->fetchAll();

        $totalCommittees = (int)$this->pdo->query("SELECT COUNT(*) FROM committees WHERE is_deleted = 0")->fetchColumn();
        $activeCommittees = (int)$this->pdo->query("SELECT COUNT(*) FROM committees WHERE is_deleted = 0 AND is_active = 1")->fetchColumn();
        $inactiveCommittees = $totalCommittees - $activeCommittees;

        $success = flash_get('success');
        $error = flash_get('error');

        $pageTitle = 'Committees Management';
        $pageSubtitle = 'Manage legislative committees and their availability in dropdowns';
        $accent = 'primary';

        $viewDir = __DIR__ . '/../../../resources/views/master/committees';
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

        $errors = $this->validateCommittee($data);

        $duplicateStmt = $this->pdo->prepare("SELECT id FROM committees WHERE name = ? AND is_deleted = 0 LIMIT 1");
        $duplicateStmt->execute([$data['name']]);
        if ($duplicateStmt->fetch()) {
            $errors[] = "A committee with the name \"{$data['name']}\" already exists.";
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            old_set($_POST);
            redirect('master/committees');
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO committees (name, description, sort_order, is_active, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['name'],
                $data['description'] ?: null,
                $data['sort_order'],
                $data['is_active'],
                $userId,
                $userId,
            ]);
            $newId = (int)$this->pdo->lastInsertId();

            audit_log('CREATE', 'Committee', (string)$newId, null, $data, "Created committee: {$data['name']}");
            system_log('INFO', "Committee created: {$data['name']} (ID: {$newId})");
            flash_set('success', "Committee \"{$data['name']}\" created successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'Committee create failed', ['error' => $e->getMessage(), 'data' => $data]);
            flash_set('error', 'Failed to create committee: ' . $e->getMessage());
            old_set($_POST);
        }

        redirect('master/committees');
    }

    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid committee ID.']);
            exit;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM committees WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $committee = $stmt->fetch();

        if (!$committee) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Committee not found.']);
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $committee]);
        exit;
    }

    public function update(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash_set('error', 'Invalid committee ID.');
            redirect('master/committees');
        }

        $userId = auth_id();
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $errors = $this->validateCommittee($data);

        $duplicateStmt = $this->pdo->prepare("SELECT id FROM committees WHERE name = ? AND is_deleted = 0 AND id != ? LIMIT 1");
        $duplicateStmt->execute([$data['name'], $id]);
        if ($duplicateStmt->fetch()) {
            $errors[] = "Another committee with the name \"{$data['name']}\" already exists.";
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            redirect('master/committees');
        }

        try {
            $oldStmt = $this->pdo->prepare("SELECT * FROM committees WHERE id = ? AND is_deleted = 0 LIMIT 1");
            $oldStmt->execute([$id]);
            $oldRecord = $oldStmt->fetch();

            $stmt = $this->pdo->prepare("UPDATE committees SET name = ?, description = ?, sort_order = ?, is_active = ?, updated_by = ? WHERE id = ? AND is_deleted = 0");
            $stmt->execute([
                $data['name'],
                $data['description'] ?: null,
                $data['sort_order'],
                $data['is_active'],
                $userId,
                $id,
            ]);

            audit_log('UPDATE', 'Committee', (string)$id, $oldRecord ?: null, $data, "Updated committee: {$data['name']}");
            system_log('INFO', "Committee updated: {$data['name']} (ID: {$id})");
            flash_set('success', "Committee \"{$data['name']}\" updated successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'Committee update failed', ['error' => $e->getMessage(), 'committee_id' => $id, 'data' => $data]);
            flash_set('error', 'Failed to update committee: ' . $e->getMessage());
        }

        redirect('master/committees');
    }

    public function destroy(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid committee ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name FROM committees WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $committee = $stmt->fetch();

        if (!$committee) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Committee not found.']);
            exit;
        }

        $oldStmt = $this->pdo->prepare("SELECT * FROM committees WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $oldStmt->execute([$id]);
        $oldRecord = $oldStmt->fetch();

        $stmt = $this->pdo->prepare("UPDATE committees SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$userId, $userId, $id]);

        header('Content-Type: application/json');
        if ($result) {
            audit_log('DELETE', 'Committee', (string)$id, $oldRecord ?: null, null, "Soft-deleted committee: {$committee['name']}");
            system_log('INFO', "Committee deleted (soft): {$committee['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "Committee \"{$committee['name']}\" deleted successfully."]);
        } else {
            system_log('WARNING', "Failed to delete committee (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to delete committee.']);
        }
        exit;
    }

    public function toggleStatus(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid committee ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name, is_active FROM committees WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $committee = $stmt->fetch();

        if (!$committee) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Committee not found.']);
            exit;
        }

        $newActive = $committee['is_active'] ? 0 : 1;
        $oldStatus = $committee['is_active'] ? 'Active' : 'Inactive';
        $newStatusLabel = $newActive ? 'Active' : 'Inactive';
        $stmt = $this->pdo->prepare("UPDATE committees SET is_active = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$newActive, $userId, $id]);

        $action = $newActive ? 'activated' : 'deactivated';
        header('Content-Type: application/json');
        if ($result) {
            audit_log('UPDATE', 'Committee', (string)$id, ['is_active' => $oldStatus], ['is_active' => $newStatusLabel], "Committee \"{$committee['name']}\" {$action}");
            system_log('INFO', "Committee status {$action}: {$committee['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "Committee \"{$committee['name']}\" has been {$action}."]);
        } else {
            system_log('WARNING', "Failed to toggle committee status (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
        }
        exit;
    }

    protected function validateCommittee(array $data): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'Committee name is required.';
        } elseif (mb_strlen($data['name']) > 50) {
            $errors[] = 'Committee name must not exceed 50 characters.';
        }

        if ($data['sort_order'] < 0) {
            $errors[] = 'Sort order must be a non-negative integer.';
        }

        return $errors;
    }
}

<?php

require_once __DIR__ . '/../../config/database.php';

class OpinionStatusController
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

        $whereClauses = ['os.is_deleted = 0'];
        $params = [];

        if ($search !== '') {
            $whereClauses[] = '(os.name LIKE ? OR os.description LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if ($filterStatus !== '') {
            $whereClauses[] = 'os.is_active = ?';
            $params[] = $filterStatus === 'active' ? 1 : 0;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM opinion_statuses os WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT os.* FROM opinion_statuses os
                WHERE {$whereSql}
                ORDER BY os.sort_order ASC, os.name ASC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $opinionStatuses = $stmt->fetchAll();

        $totalOpinionStatuses = (int)$this->pdo->query("SELECT COUNT(*) FROM opinion_statuses WHERE is_deleted = 0")->fetchColumn();
        $activeOpinionStatuses = (int)$this->pdo->query("SELECT COUNT(*) FROM opinion_statuses WHERE is_deleted = 0 AND is_active = 1")->fetchColumn();
        $inactiveOpinionStatuses = $totalOpinionStatuses - $activeOpinionStatuses;

        $success = flash_get('success');
        $error = flash_get('error');

        $pageTitle = 'Opinion Statuses Management';
        $pageSubtitle = 'Manage opinion statuses and badge colors used for visual tagging';
        $accent = 'primary';

        $viewDir = __DIR__ . '/../../../resources/views/master/opinion-statuses';
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
            'badge_color' => $this->normalizeColor($_POST['badge_color'] ?? ''),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $errors = $this->validateOpinionStatus($data);

        $duplicateStmt = $this->pdo->prepare("SELECT id FROM opinion_statuses WHERE name = ? AND is_deleted = 0 LIMIT 1");
        $duplicateStmt->execute([$data['name']]);
        if ($duplicateStmt->fetch()) {
            $errors[] = "A opinion status with the name \"{$data['name']}\" already exists.";
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            old_set($_POST);
            redirect('master/opinion-statuses');
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO opinion_statuses (name, description, badge_color, sort_order, is_active, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['name'],
                $data['description'] ?: null,
                $data['badge_color'],
                $data['sort_order'],
                $data['is_active'],
                $userId,
                $userId,
            ]);
            $newId = (int)$this->pdo->lastInsertId();

            audit_log('CREATE', 'OpinionStatus', (string)$newId, null, $data, "Created opinion status: {$data['name']}");
            system_log('INFO', "Opinion status created: {$data['name']} (ID: {$newId})");
            flash_set('success', "Opinion status \"{$data['name']}\" created successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'Opinion status create failed', ['error' => $e->getMessage(), 'data' => $data]);
            flash_set('error', 'Failed to create opinion status: ' . $e->getMessage());
            old_set($_POST);
        }

        redirect('master/opinion-statuses');
    }

    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid opinion status ID.']);
            exit;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM opinion_statuses WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $opinionStatus = $stmt->fetch();

        if (!$opinionStatus) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Opinion status not found.']);
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $opinionStatus]);
        exit;
    }

    public function update(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash_set('error', 'Invalid opinion status ID.');
            redirect('master/opinion-statuses');
        }

        $userId = auth_id();
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'badge_color' => $this->normalizeColor($_POST['badge_color'] ?? ''),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $errors = $this->validateOpinionStatus($data);

        $duplicateStmt = $this->pdo->prepare("SELECT id FROM opinion_statuses WHERE name = ? AND is_deleted = 0 AND id != ? LIMIT 1");
        $duplicateStmt->execute([$data['name'], $id]);
        if ($duplicateStmt->fetch()) {
            $errors[] = "Another opinion status with the name \"{$data['name']}\" already exists.";
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            redirect('master/opinion-statuses');
        }

        try {
            $oldStmt = $this->pdo->prepare("SELECT * FROM opinion_statuses WHERE id = ? AND is_deleted = 0 LIMIT 1");
            $oldStmt->execute([$id]);
            $oldRecord = $oldStmt->fetch();

            $stmt = $this->pdo->prepare("UPDATE opinion_statuses SET name = ?, description = ?, badge_color = ?, sort_order = ?, is_active = ?, updated_by = ? WHERE id = ? AND is_deleted = 0");
            $stmt->execute([
                $data['name'],
                $data['description'] ?: null,
                $data['badge_color'],
                $data['sort_order'],
                $data['is_active'],
                $userId,
                $id,
            ]);

            audit_log('UPDATE', 'OpinionStatus', (string)$id, $oldRecord ?: null, $data, "Updated opinion status: {$data['name']}");
            system_log('INFO', "Opinion status updated: {$data['name']} (ID: {$id})");
            flash_set('success', "Opinion status \"{$data['name']}\" updated successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'Opinion status update failed', ['error' => $e->getMessage(), 'opinion_status_id' => $id, 'data' => $data]);
            flash_set('error', 'Failed to update opinion status: ' . $e->getMessage());
        }

        redirect('master/opinion-statuses');
    }

    public function destroy(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid opinion status ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name FROM opinion_statuses WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $opinionStatus = $stmt->fetch();

        if (!$opinionStatus) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Opinion status not found.']);
            exit;
        }

        $oldStmt = $this->pdo->prepare("SELECT * FROM opinion_statuses WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $oldStmt->execute([$id]);
        $oldRecord = $oldStmt->fetch();

        $stmt = $this->pdo->prepare("UPDATE opinion_statuses SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$userId, $userId, $id]);

        header('Content-Type: application/json');
        if ($result) {
            audit_log('DELETE', 'OpinionStatus', (string)$id, $oldRecord ?: null, null, "Soft-deleted opinion status: {$opinionStatus['name']}");
            system_log('INFO', "Opinion status deleted (soft): {$opinionStatus['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "Opinion status \"{$opinionStatus['name']}\" deleted successfully."]);
        } else {
            system_log('WARNING', "Failed to delete opinion status (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to delete opinion status.']);
        }
        exit;
    }

    public function toggleStatus(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid opinion status ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name, is_active FROM opinion_statuses WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $opinionStatus = $stmt->fetch();

        if (!$opinionStatus) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Opinion status not found.']);
            exit;
        }

        $newActive = $opinionStatus['is_active'] ? 0 : 1;
        $oldStatus = $opinionStatus['is_active'] ? 'Active' : 'Inactive';
        $newStatusLabel = $newActive ? 'Active' : 'Inactive';
        $stmt = $this->pdo->prepare("UPDATE opinion_statuses SET is_active = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$newActive, $userId, $id]);

        $action = $newActive ? 'activated' : 'deactivated';
        header('Content-Type: application/json');
        if ($result) {
            audit_log('UPDATE', 'OpinionStatus', (string)$id, ['is_active' => $oldStatus], ['is_active' => $newStatusLabel], "Opinion status \"{$opinionStatus['name']}\" {$action}");
            system_log('INFO', "Opinion status status {$action}: {$opinionStatus['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "Opinion status \"{$opinionStatus['name']}\" has been {$action}."]);
        } else {
            system_log('WARNING', "Failed to toggle opinion status (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
        }
        exit;
    }

    protected function validateOpinionStatus(array $data): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'Opinion status name is required.';
        } elseif (mb_strlen($data['name']) > 255) {
            $errors[] = 'Opinion status name must not exceed 255 characters.';
        }

        if (!empty($data['description']) && mb_strlen($data['description']) > 255) {
            $errors[] = 'Description must not exceed 255 characters.';
        }

        if (!$this->isValidHexColor($data['badge_color'])) {
            $errors[] = 'Badge color must be a valid hex color code.';
        }

        if ($data['sort_order'] < 0) {
            $errors[] = 'Sort order must be a non-negative integer.';
        }

        return $errors;
    }

    protected function normalizeColor(?string $value): string
    {
        $value = trim((string)($value ?? ''));
        if ($value === '') {
            return '#2563EB';
        }

        if (preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $value)) {
            return strtoupper($value);
        }

        return '#2563EB';
    }

    protected function isValidHexColor(string $value): bool
    {
        return preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $value) === 1;
    }
}

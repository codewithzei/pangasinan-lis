<?php

require_once __DIR__ . '/../../config/database.php';

class DocumentStatusController
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

        $whereClauses = ['ds.is_deleted = 0'];
        $params = [];

        if ($search !== '') {
            $whereClauses[] = '(ds.name LIKE ? OR ds.description LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if ($filterStatus !== '') {
            $whereClauses[] = 'ds.is_active = ?';
            $params[] = $filterStatus === 'active' ? 1 : 0;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM document_statuses ds WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT ds.* FROM document_statuses ds
                WHERE {$whereSql}
                ORDER BY ds.sort_order ASC, ds.name ASC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $documentStatuses = $stmt->fetchAll();

        $totalDocumentStatuses = (int)$this->pdo->query("SELECT COUNT(*) FROM document_statuses WHERE is_deleted = 0")->fetchColumn();
        $activeDocumentStatuses = (int)$this->pdo->query("SELECT COUNT(*) FROM document_statuses WHERE is_deleted = 0 AND is_active = 1")->fetchColumn();
        $inactiveDocumentStatuses = $totalDocumentStatuses - $activeDocumentStatuses;

        $success = flash_get('success');
        $error = flash_get('error');

        $pageTitle = 'Document Statuses Management';
        $pageSubtitle = 'Manage document statuses and badge colors used for visual tagging';
        $accent = 'primary';

        $viewDir = __DIR__ . '/../../../resources/views/master/document-statuses';
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

        $errors = $this->validateDocumentStatus($data);

        $duplicateStmt = $this->pdo->prepare("SELECT id FROM document_statuses WHERE name = ? AND is_deleted = 0 LIMIT 1");
        $duplicateStmt->execute([$data['name']]);
        if ($duplicateStmt->fetch()) {
            $errors[] = "A document status with the name \"{$data['name']}\" already exists.";
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            old_set($_POST);
            redirect('master/document-statuses');
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO document_statuses (name, description, badge_color, sort_order, is_active, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
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

            audit_log('CREATE', 'DocumentStatus', (string)$newId, null, $data, "Created document status: {$data['name']}");
            system_log('INFO', "Document status created: {$data['name']} (ID: {$newId})");
            flash_set('success', "Document status \"{$data['name']}\" created successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'Document status create failed', ['error' => $e->getMessage(), 'data' => $data]);
            flash_set('error', 'Failed to create document status: ' . $e->getMessage());
            old_set($_POST);
        }

        redirect('master/document-statuses');
    }

    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid document status ID.']);
            exit;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM document_statuses WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $documentStatus = $stmt->fetch();

        if (!$documentStatus) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Document status not found.']);
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $documentStatus]);
        exit;
    }

    public function update(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash_set('error', 'Invalid document status ID.');
            redirect('master/document-statuses');
        }

        $userId = auth_id();
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'badge_color' => $this->normalizeColor($_POST['badge_color'] ?? ''),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $errors = $this->validateDocumentStatus($data);

        $duplicateStmt = $this->pdo->prepare("SELECT id FROM document_statuses WHERE name = ? AND is_deleted = 0 AND id != ? LIMIT 1");
        $duplicateStmt->execute([$data['name'], $id]);
        if ($duplicateStmt->fetch()) {
            $errors[] = "Another document status with the name \"{$data['name']}\" already exists.";
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            redirect('master/document-statuses');
        }

        try {
            $oldStmt = $this->pdo->prepare("SELECT * FROM document_statuses WHERE id = ? AND is_deleted = 0 LIMIT 1");
            $oldStmt->execute([$id]);
            $oldRecord = $oldStmt->fetch();

            $stmt = $this->pdo->prepare("UPDATE document_statuses SET name = ?, description = ?, badge_color = ?, sort_order = ?, is_active = ?, updated_by = ? WHERE id = ? AND is_deleted = 0");
            $stmt->execute([
                $data['name'],
                $data['description'] ?: null,
                $data['badge_color'],
                $data['sort_order'],
                $data['is_active'],
                $userId,
                $id,
            ]);

            audit_log('UPDATE', 'DocumentStatus', (string)$id, $oldRecord ?: null, $data, "Updated document status: {$data['name']}");
            system_log('INFO', "Document status updated: {$data['name']} (ID: {$id})");
            flash_set('success', "Document status \"{$data['name']}\" updated successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'Document status update failed', ['error' => $e->getMessage(), 'document_status_id' => $id, 'data' => $data]);
            flash_set('error', 'Failed to update document status: ' . $e->getMessage());
        }

        redirect('master/document-statuses');
    }

    public function destroy(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid document status ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name FROM document_statuses WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $documentStatus = $stmt->fetch();

        if (!$documentStatus) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Document status not found.']);
            exit;
        }

        $oldStmt = $this->pdo->prepare("SELECT * FROM document_statuses WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $oldStmt->execute([$id]);
        $oldRecord = $oldStmt->fetch();

        $stmt = $this->pdo->prepare("UPDATE document_statuses SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$userId, $userId, $id]);

        header('Content-Type: application/json');
        if ($result) {
            audit_log('DELETE', 'DocumentStatus', (string)$id, $oldRecord ?: null, null, "Soft-deleted document status: {$documentStatus['name']}");
            system_log('INFO', "Document status deleted (soft): {$documentStatus['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "Document status \"{$documentStatus['name']}\" deleted successfully."]);
        } else {
            system_log('WARNING', "Failed to delete document status (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to delete document status.']);
        }
        exit;
    }

    public function toggleStatus(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid document status ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name, is_active FROM document_statuses WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $documentStatus = $stmt->fetch();

        if (!$documentStatus) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Document status not found.']);
            exit;
        }

        $newActive = $documentStatus['is_active'] ? 0 : 1;
        $oldStatus = $documentStatus['is_active'] ? 'Active' : 'Inactive';
        $newStatusLabel = $newActive ? 'Active' : 'Inactive';
        $stmt = $this->pdo->prepare("UPDATE document_statuses SET is_active = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$newActive, $userId, $id]);

        $action = $newActive ? 'activated' : 'deactivated';
        header('Content-Type: application/json');
        if ($result) {
            audit_log('UPDATE', 'DocumentStatus', (string)$id, ['is_active' => $oldStatus], ['is_active' => $newStatusLabel], "Document status \"{$documentStatus['name']}\" {$action}");
            system_log('INFO', "Document status status {$action}: {$documentStatus['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "Document status \"{$documentStatus['name']}\" has been {$action}."]);
        } else {
            system_log('WARNING', "Failed to toggle document status (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
        }
        exit;
    }

    protected function validateDocumentStatus(array $data): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'Document status name is required.';
        } elseif (mb_strlen($data['name']) > 255) {
            $errors[] = 'Document status name must not exceed 255 characters.';
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

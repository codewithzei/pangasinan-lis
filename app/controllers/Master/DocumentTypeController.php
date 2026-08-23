<?php

require_once __DIR__ . '/../../config/database.php';

class DocumentTypeController
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

        $whereClauses = ['dt.is_deleted = 0'];
        $params = [];

        if ($search !== '') {
            $whereClauses[] = '(dt.name LIKE ? OR dt.description LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if ($filterStatus !== '') {
            $whereClauses[] = 'dt.is_active = ?';
            $params[] = $filterStatus === 'active' ? 1 : 0;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM document_types dt WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT dt.* FROM document_types dt
                WHERE {$whereSql}
                ORDER BY dt.sort_order ASC, dt.name ASC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $documentTypes = $stmt->fetchAll();

        $totalDocumentTypes = (int)$this->pdo->query("SELECT COUNT(*) FROM document_types WHERE is_deleted = 0")->fetchColumn();
        $activeDocumentTypes = (int)$this->pdo->query("SELECT COUNT(*) FROM document_types WHERE is_deleted = 0 AND is_active = 1")->fetchColumn();
        $inactiveDocumentTypes = $totalDocumentTypes - $activeDocumentTypes;

        $success = flash_get('success');
        $error = flash_get('error');

        $pageTitle = 'Document Types Management';
        $pageSubtitle = 'Manage document categories and the badge colors used for classification and visual tagging';
        $accent = 'primary';

        $viewDir = __DIR__ . '/../../../resources/views/master/document-types';
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

        $errors = $this->validateDocumentType($data);

        $duplicateStmt = $this->pdo->prepare("SELECT id FROM document_types WHERE name = ? AND is_deleted = 0 LIMIT 1");
        $duplicateStmt->execute([$data['name']]);
        if ($duplicateStmt->fetch()) {
            $errors[] = "A document type with the name \"{$data['name']}\" already exists.";
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            old_set($_POST);
            redirect('master/document-types');
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO document_types (name, description, badge_color, sort_order, is_active, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
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

            audit_log('CREATE', 'DocumentType', (string)$newId, null, $data, "Created document type: {$data['name']}");
            system_log('INFO', "Document type created: {$data['name']} (ID: {$newId})");
            flash_set('success', "Document type \"{$data['name']}\" created successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'Document type create failed', ['error' => $e->getMessage(), 'data' => $data]);
            flash_set('error', 'Failed to create document type: ' . $e->getMessage());
            old_set($_POST);
        }

        redirect('master/document-types');
    }

    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid document type ID.']);
            exit;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM document_types WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $documentType = $stmt->fetch();

        if (!$documentType) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Document type not found.']);
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $documentType]);
        exit;
    }

    public function update(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash_set('error', 'Invalid document type ID.');
            redirect('master/document-types');
        }

        $userId = auth_id();
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'badge_color' => $this->normalizeColor($_POST['badge_color'] ?? ''),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $errors = $this->validateDocumentType($data);

        $duplicateStmt = $this->pdo->prepare("SELECT id FROM document_types WHERE name = ? AND is_deleted = 0 AND id != ? LIMIT 1");
        $duplicateStmt->execute([$data['name'], $id]);
        if ($duplicateStmt->fetch()) {
            $errors[] = "Another document type with the name \"{$data['name']}\" already exists.";
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            redirect('master/document-types');
        }

        try {
            $oldStmt = $this->pdo->prepare("SELECT * FROM document_types WHERE id = ? AND is_deleted = 0 LIMIT 1");
            $oldStmt->execute([$id]);
            $oldRecord = $oldStmt->fetch();

            $stmt = $this->pdo->prepare("UPDATE document_types SET name = ?, description = ?, badge_color = ?, sort_order = ?, is_active = ?, updated_by = ? WHERE id = ? AND is_deleted = 0");
            $stmt->execute([
                $data['name'],
                $data['description'] ?: null,
                $data['badge_color'],
                $data['sort_order'],
                $data['is_active'],
                $userId,
                $id,
            ]);

            audit_log('UPDATE', 'DocumentType', (string)$id, $oldRecord ?: null, $data, "Updated document type: {$data['name']}");
            system_log('INFO', "Document type updated: {$data['name']} (ID: {$id})");
            flash_set('success', "Document type \"{$data['name']}\" updated successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'Document type update failed', ['error' => $e->getMessage(), 'document_type_id' => $id, 'data' => $data]);
            flash_set('error', 'Failed to update document type: ' . $e->getMessage());
        }

        redirect('master/document-types');
    }

    public function destroy(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid document type ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name FROM document_types WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $documentType = $stmt->fetch();

        if (!$documentType) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Document type not found.']);
            exit;
        }

        $oldStmt = $this->pdo->prepare("SELECT * FROM document_types WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $oldStmt->execute([$id]);
        $oldRecord = $oldStmt->fetch();

        $stmt = $this->pdo->prepare("UPDATE document_types SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$userId, $userId, $id]);

        header('Content-Type: application/json');
        if ($result) {
            audit_log('DELETE', 'DocumentType', (string)$id, $oldRecord ?: null, null, "Soft-deleted document type: {$documentType['name']}");
            system_log('INFO', "Document type deleted (soft): {$documentType['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "Document type \"{$documentType['name']}\" deleted successfully."]);
        } else {
            system_log('WARNING', "Failed to delete document type (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to delete document type.']);
        }
        exit;
    }

    public function toggleStatus(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid document type ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name, is_active FROM document_types WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $documentType = $stmt->fetch();

        if (!$documentType) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Document type not found.']);
            exit;
        }

        $newActive = $documentType['is_active'] ? 0 : 1;
        $oldStatus = $documentType['is_active'] ? 'Active' : 'Inactive';
        $newStatusLabel = $newActive ? 'Active' : 'Inactive';
        $stmt = $this->pdo->prepare("UPDATE document_types SET is_active = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$newActive, $userId, $id]);

        $action = $newActive ? 'activated' : 'deactivated';
        header('Content-Type: application/json');
        if ($result) {
            audit_log('UPDATE', 'DocumentType', (string)$id, ['is_active' => $oldStatus], ['is_active' => $newStatusLabel], "Document type \"{$documentType['name']}\" {$action}");
            system_log('INFO', "Document type status {$action}: {$documentType['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "Document type \"{$documentType['name']}\" has been {$action}."]);
        } else {
            system_log('WARNING', "Failed to toggle document type status (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
        }
        exit;
    }

    protected function validateDocumentType(array $data): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'Document type name is required.';
        } elseif (mb_strlen($data['name']) > 255) {
            $errors[] = 'Document type name must not exceed 255 characters.';
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

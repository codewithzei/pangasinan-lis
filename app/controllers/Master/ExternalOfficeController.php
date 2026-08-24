<?php

require_once __DIR__ . '/../../config/database.php';

class ExternalOfficeController
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

        $whereClauses = ['eo.is_deleted = 0'];
        $params = [];

        if ($search !== '') {
            $whereClauses[] = '(eo.name LIKE ? OR eo.abbreviation LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if ($filterStatus !== '') {
            $whereClauses[] = 'eo.is_active = ?';
            $params[] = $filterStatus === 'active' ? 1 : 0;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM external_offices eo WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT eo.* FROM external_offices eo
                WHERE {$whereSql}
                ORDER BY eo.sort_order ASC, eo.name ASC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $externalOffices = $stmt->fetchAll();

        $totalExternalOffices = (int)$this->pdo->query("SELECT COUNT(*) FROM external_offices WHERE is_deleted = 0")->fetchColumn();
        $activeExternalOffices = (int)$this->pdo->query("SELECT COUNT(*) FROM external_offices WHERE is_deleted = 0 AND is_active = 1")->fetchColumn();
        $inactiveExternalOffices = $totalExternalOffices - $activeExternalOffices;

        $success = flash_get('success');
        $error = flash_get('error');

        $pageTitle = 'External Offices Management';
        $pageSubtitle = 'Manage external offices and their availability in dropdowns';
        $accent = 'primary';

        $viewDir = __DIR__ . '/../../../resources/views/master/external-offices';
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
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $errors = $this->validateExternalOffice($data);

        $duplicateStmt = $this->pdo->prepare("SELECT id FROM external_offices WHERE name = ? AND is_deleted = 0 LIMIT 1");
        $duplicateStmt->execute([$data['name']]);
        if ($duplicateStmt->fetch()) {
            $errors[] = "An external office with the name \"{$data['name']}\" already exists.";
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            old_set($_POST);
            redirect('master/external-offices');
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO external_offices (name, abbreviation, sort_order, is_active, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['name'],
                $data['abbreviation'] ?: null,
                $data['sort_order'],
                $data['is_active'],
                $userId,
                $userId,
            ]);
            $newId = (int)$this->pdo->lastInsertId();

            audit_log('CREATE', 'ExternalOffice', (string)$newId, null, $data, "Created external office: {$data['name']}");
            system_log('INFO', "External office created: {$data['name']} (ID: {$newId})");
            flash_set('success', "External office \"{$data['name']}\" created successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'External office create failed', ['error' => $e->getMessage(), 'data' => $data]);
            flash_set('error', 'Failed to create external office: ' . $e->getMessage());
            old_set($_POST);
        }

        redirect('master/external-offices');
    }

    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid external office ID.']);
            exit;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM external_offices WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $externalOffice = $stmt->fetch();

        if (!$externalOffice) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'External office not found.']);
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $externalOffice]);
        exit;
    }

    public function update(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash_set('error', 'Invalid external office ID.');
            redirect('master/external-offices');
        }

        $userId = auth_id();
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'abbreviation' => trim($_POST['abbreviation'] ?? ''),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $errors = $this->validateExternalOffice($data);

        $duplicateStmt = $this->pdo->prepare("SELECT id FROM external_offices WHERE name = ? AND is_deleted = 0 AND id != ? LIMIT 1");
        $duplicateStmt->execute([$data['name'], $id]);
        if ($duplicateStmt->fetch()) {
            $errors[] = "Another external office with the name \"{$data['name']}\" already exists.";
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            redirect('master/external-offices');
        }

        try {
            $oldStmt = $this->pdo->prepare("SELECT * FROM external_offices WHERE id = ? AND is_deleted = 0 LIMIT 1");
            $oldStmt->execute([$id]);
            $oldRecord = $oldStmt->fetch();

            $stmt = $this->pdo->prepare("UPDATE external_offices SET name = ?, abbreviation = ?, sort_order = ?, is_active = ?, updated_by = ? WHERE id = ? AND is_deleted = 0");
            $stmt->execute([
                $data['name'],
                $data['abbreviation'] ?: null,
                $data['sort_order'],
                $data['is_active'],
                $userId,
                $id,
            ]);

            audit_log('UPDATE', 'ExternalOffice', (string)$id, $oldRecord ?: null, $data, "Updated external office: {$data['name']}");
            system_log('INFO', "External office updated: {$data['name']} (ID: {$id})");
            flash_set('success', "External office \"{$data['name']}\" updated successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'External office update failed', ['error' => $e->getMessage(), 'external_office_id' => $id, 'data' => $data]);
            flash_set('error', 'Failed to update external office: ' . $e->getMessage());
        }

        redirect('master/external-offices');
    }

    public function destroy(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid external office ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name FROM external_offices WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $externalOffice = $stmt->fetch();

        if (!$externalOffice) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'External office not found.']);
            exit;
        }

        $oldStmt = $this->pdo->prepare("SELECT * FROM external_offices WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $oldStmt->execute([$id]);
        $oldRecord = $oldStmt->fetch();

        $stmt = $this->pdo->prepare("UPDATE external_offices SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$userId, $userId, $id]);

        header('Content-Type: application/json');
        if ($result) {
            audit_log('DELETE', 'ExternalOffice', (string)$id, $oldRecord ?: null, null, "Soft-deleted external office: {$externalOffice['name']}");
            system_log('INFO', "External office deleted (soft): {$externalOffice['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "External office \"{$externalOffice['name']}\" deleted successfully."]);
        } else {
            system_log('WARNING', "Failed to delete external office (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to delete external office.']);
        }
        exit;
    }

    public function toggleStatus(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid external office ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name, is_active FROM external_offices WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $externalOffice = $stmt->fetch();

        if (!$externalOffice) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'External office not found.']);
            exit;
        }

        $newActive = $externalOffice['is_active'] ? 0 : 1;
        $oldStatus = $externalOffice['is_active'] ? 'Active' : 'Inactive';
        $newStatusLabel = $newActive ? 'Active' : 'Inactive';
        $stmt = $this->pdo->prepare("UPDATE external_offices SET is_active = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$newActive, $userId, $id]);

        $action = $newActive ? 'activated' : 'deactivated';
        header('Content-Type: application/json');
        if ($result) {
            audit_log('UPDATE', 'ExternalOffice', (string)$id, ['is_active' => $oldStatus], ['is_active' => $newStatusLabel], "External office \"{$externalOffice['name']}\" {$action}");
            system_log('INFO', "External office status {$action}: {$externalOffice['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "External office \"{$externalOffice['name']}\" has been {$action}."]);
        } else {
            system_log('WARNING', "Failed to toggle external office status (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
        }
        exit;
    }

    protected function validateExternalOffice(array $data): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'External office name is required.';
        } elseif (mb_strlen($data['name']) > 255) {
            $errors[] = 'External office name must not exceed 255 characters.';
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

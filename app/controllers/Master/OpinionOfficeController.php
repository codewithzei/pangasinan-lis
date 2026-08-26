<?php

require_once __DIR__ . '/../../config/database.php';

class OpinionOfficeController
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

        $whereClauses = ['oo.is_deleted = 0'];
        $params = [];

        if ($search !== '') {
            $whereClauses[] = '(oo.name LIKE ? OR oo.abbreviation LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if ($filterStatus !== '') {
            $whereClauses[] = 'oo.is_active = ?';
            $params[] = $filterStatus === 'active' ? 1 : 0;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM opinion_offices oo WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT oo.* FROM opinion_offices oo
                WHERE {$whereSql}
                ORDER BY oo.sort_order ASC, oo.name ASC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $opinionOffices = $stmt->fetchAll();

        $totalOpinionOffices = (int)$this->pdo->query("SELECT COUNT(*) FROM opinion_offices WHERE is_deleted = 0")->fetchColumn();
        $activeOpinionOffices = (int)$this->pdo->query("SELECT COUNT(*) FROM opinion_offices WHERE is_deleted = 0 AND is_active = 1")->fetchColumn();
        $inactiveOpinionOffices = $totalOpinionOffices - $activeOpinionOffices;

        $success = flash_get('success');
        $error = flash_get('error');

        $pageTitle = 'Opinion Offices Management';
        $pageSubtitle = 'Manage opinion offices and their availability in dropdowns';
        $accent = 'primary';

        $viewDir = __DIR__ . '/../../../resources/views/master/opinion-offices';
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

        $errors = $this->validateOpinionOffice($data);

        $duplicateStmt = $this->pdo->prepare("SELECT id FROM opinion_offices WHERE name = ? AND is_deleted = 0 LIMIT 1");
        $duplicateStmt->execute([$data['name']]);
        if ($duplicateStmt->fetch()) {
            $errors[] = "An opinion office with the name \"{$data['name']}\" already exists.";
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            old_set($_POST);
            redirect('master/opinion-offices');
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO opinion_offices (name, abbreviation, sort_order, is_active, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['name'],
                $data['abbreviation'] ?: null,
                $data['sort_order'],
                $data['is_active'],
                $userId,
                $userId,
            ]);
            $newId = (int)$this->pdo->lastInsertId();

            audit_log('CREATE', 'OpinionOffice', (string)$newId, null, $data, "Created opinion office: {$data['name']}");
            system_log('INFO', "Opinion office created: {$data['name']} (ID: {$newId})");
            flash_set('success', "Opinion office \"{$data['name']}\" created successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'Opinion office create failed', ['error' => $e->getMessage(), 'data' => $data]);
            flash_set('error', 'Failed to create opinion office: ' . $e->getMessage());
            old_set($_POST);
        }

        redirect('master/opinion-offices');
    }

    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid opinion office ID.']);
            exit;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM opinion_offices WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $opinionOffice = $stmt->fetch();

        if (!$opinionOffice) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Opinion office not found.']);
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $opinionOffice]);
        exit;
    }

    public function update(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash_set('error', 'Invalid opinion office ID.');
            redirect('master/opinion-offices');
        }

        $userId = auth_id();
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'abbreviation' => trim($_POST['abbreviation'] ?? ''),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $errors = $this->validateOpinionOffice($data);

        $duplicateStmt = $this->pdo->prepare("SELECT id FROM opinion_offices WHERE name = ? AND is_deleted = 0 AND id != ? LIMIT 1");
        $duplicateStmt->execute([$data['name'], $id]);
        if ($duplicateStmt->fetch()) {
            $errors[] = "Another opinion office with the name \"{$data['name']}\" already exists.";
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            redirect('master/opinion-offices');
        }

        try {
            $oldStmt = $this->pdo->prepare("SELECT * FROM opinion_offices WHERE id = ? AND is_deleted = 0 LIMIT 1");
            $oldStmt->execute([$id]);
            $oldRecord = $oldStmt->fetch();

            $stmt = $this->pdo->prepare("UPDATE opinion_offices SET name = ?, abbreviation = ?, sort_order = ?, is_active = ?, updated_by = ? WHERE id = ? AND is_deleted = 0");
            $stmt->execute([
                $data['name'],
                $data['abbreviation'] ?: null,
                $data['sort_order'],
                $data['is_active'],
                $userId,
                $id,
            ]);

            audit_log('UPDATE', 'OpinionOffice', (string)$id, $oldRecord ?: null, $data, "Updated opinion office: {$data['name']}");
            system_log('INFO', "Opinion office updated: {$data['name']} (ID: {$id})");
            flash_set('success', "Opinion office \"{$data['name']}\" updated successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'Opinion office update failed', ['error' => $e->getMessage(), 'opinion_office_id' => $id, 'data' => $data]);
            flash_set('error', 'Failed to update opinion office: ' . $e->getMessage());
        }

        redirect('master/opinion-offices');
    }

    public function destroy(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid opinion office ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name FROM opinion_offices WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $opinionOffice = $stmt->fetch();

        if (!$opinionOffice) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Opinion office not found.']);
            exit;
        }

        $oldStmt = $this->pdo->prepare("SELECT * FROM opinion_offices WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $oldStmt->execute([$id]);
        $oldRecord = $oldStmt->fetch();

        $stmt = $this->pdo->prepare("UPDATE opinion_offices SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$userId, $userId, $id]);

        header('Content-Type: application/json');
        if ($result) {
            audit_log('DELETE', 'OpinionOffice', (string)$id, $oldRecord ?: null, null, "Soft-deleted opinion office: {$opinionOffice['name']}");
            system_log('INFO', "Opinion office deleted (soft): {$opinionOffice['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "Opinion office \"{$opinionOffice['name']}\" deleted successfully."]);
        } else {
            system_log('WARNING', "Failed to delete opinion office (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to delete opinion office.']);
        }
        exit;
    }

    public function toggleStatus(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid opinion office ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name, is_active FROM opinion_offices WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $opinionOffice = $stmt->fetch();

        if (!$opinionOffice) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Opinion office not found.']);
            exit;
        }

        $newActive = $opinionOffice['is_active'] ? 0 : 1;
        $oldStatus = $opinionOffice['is_active'] ? 'Active' : 'Inactive';
        $newStatusLabel = $newActive ? 'Active' : 'Inactive';
        $stmt = $this->pdo->prepare("UPDATE opinion_offices SET is_active = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$newActive, $userId, $id]);

        $action = $newActive ? 'activated' : 'deactivated';
        header('Content-Type: application/json');
        if ($result) {
            audit_log('UPDATE', 'OpinionOffice', (string)$id, ['is_active' => $oldStatus], ['is_active' => $newStatusLabel], "Opinion office \"{$opinionOffice['name']}\" {$action}");
            system_log('INFO', "Opinion office status {$action}: {$opinionOffice['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "Opinion office \"{$opinionOffice['name']}\" has been {$action}."]);
        } else {
            system_log('WARNING', "Failed to toggle opinion office status (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
        }
        exit;
    }

    protected function validateOpinionOffice(array $data): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'Opinion office name is required.';
        } elseif (mb_strlen($data['name']) > 255) {
            $errors[] = 'Opinion office name must not exceed 255 characters.';
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

<?php

require_once __DIR__ . '/../../config/database.php';

class MuniCityController
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
        $districtFilter = $_GET['district_id'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;

        $whereClauses = ['m.is_deleted = 0'];
        $params = [];

        if ($search !== '') {
            $whereClauses[] = '(m.name LIKE ? OR m.type LIKE ? OR m.description LIKE ? OR d.name LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if ($filterStatus !== '') {
            $whereClauses[] = 'm.is_active = ?';
            $params[] = $filterStatus === 'active' ? 1 : 0;
        }

        if ($districtFilter !== '' && (int)$districtFilter > 0) {
            $whereClauses[] = 'm.district_id = ?';
            $params[] = (int)$districtFilter;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM municities m LEFT JOIN districts d ON d.id = m.district_id WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT m.*, d.name AS district_name
                FROM municities m
                LEFT JOIN districts d ON d.id = m.district_id
                WHERE {$whereSql}
                ORDER BY m.sort_order ASC, d.name ASC, m.name ASC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $municipalities = $stmt->fetchAll();

        $districtStmt = $this->pdo->query("SELECT id, name, district_number FROM districts WHERE is_deleted = 0 ORDER BY sort_order ASC, district_number ASC, name ASC");
        $districtOptions = $districtStmt->fetchAll();

        $totalMunicipalities = (int)$this->pdo->query("SELECT COUNT(*) FROM municities WHERE is_deleted = 0")->fetchColumn();
        $activeMunicipalities = (int)$this->pdo->query("SELECT COUNT(*) FROM municities WHERE is_deleted = 0 AND is_active = 1")->fetchColumn();
        $inactiveMunicipalities = $totalMunicipalities - $activeMunicipalities;

        $success = flash_get('success');
        $error = flash_get('error');
        $search = $search;
        $filterStatus = $filterStatus;
        $districtFilter = (string)($districtFilter ?? '');
        $page = $page;
        $totalPages = $totalPages;
        $totalRows = $totalRows;

        $pageTitle = 'Municipalities & Cities Management';
        $pageSubtitle = 'Manage Pangasinan municipalities and cities linked to their districts';
        $accent = 'primary';

        $viewCandidates = [
            __DIR__ . '/../../../resources/views/master/municipalities',
            __DIR__ . '/../../../resources/views/master/municities',
        ];

        $viewDir = null;
        foreach ($viewCandidates as $candidate) {
            if (is_dir($candidate)) {
                $viewDir = $candidate;
                break;
            }
        }

        if ($viewDir === null) {
            $viewDir = __DIR__ . '/../../../resources/views/master/municipalities';
            if (!is_dir($viewDir)) {
                mkdir($viewDir, 0777, true);
            }
        }

        require $viewDir . '/index.php';
    }

    public function store(): void
    {
        $userId = auth_id();
        $data = [
            'district_id' => (int)($_POST['district_id'] ?? 0),
            'name' => trim($_POST['name'] ?? ''),
            'type' => trim($_POST['type'] ?? 'Municipality'),
            'description' => trim($_POST['description'] ?? ''),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $errors = $this->validateMuniCity($data);

        if ($data['district_id'] <= 0) {
            $errors[] = 'A district must be selected.';
        } else {
            $districtStmt = $this->pdo->prepare("SELECT id FROM districts WHERE id = ? AND is_deleted = 0 LIMIT 1");
            $districtStmt->execute([$data['district_id']]);
            if (!$districtStmt->fetch()) {
                $errors[] = 'Selected district does not exist.';
            }
        }

        $duplicateStmt = $this->pdo->prepare("SELECT id FROM municities WHERE name = ? AND is_deleted = 0 LIMIT 1");
        $duplicateStmt->execute([$data['name']]);
        if ($duplicateStmt->fetch()) {
            $errors[] = "A municipality or city with the name \"{$data['name']}\" already exists.";
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            old_set($_POST);
            redirect('master/municipalities');
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO municities (district_id, name, type, description, sort_order, is_active, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['district_id'],
                $data['name'],
                $data['type'],
                $data['description'] ?: null,
                $data['sort_order'],
                $data['is_active'],
                $userId,
                $userId,
            ]);
            $newId = (int)$this->pdo->lastInsertId();

            audit_log('CREATE', 'Municipality/City', (string)$newId, null, $data, "Created municipality/city: {$data['name']}");
            system_log('INFO', "Municipality/City created: {$data['name']} (ID: {$newId})");
            flash_set('success', "Municipality/city \"{$data['name']}\" created successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'Municipality/City create failed', ['error' => $e->getMessage(), 'data' => $data]);
            flash_set('error', 'Failed to create municipality/city: ' . $e->getMessage());
            old_set($_POST);
        }

        redirect('master/municipalities');
    }

    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid municipality/city ID.']);
            exit;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM municities WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $municipality = $stmt->fetch();

        if (!$municipality) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Municipality/city not found.']);
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $municipality]);
        exit;
    }

    public function update(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash_set('error', 'Invalid municipality/city ID.');
            redirect('master/municipalities');
        }

        $userId = auth_id();
        $data = [
            'district_id' => (int)($_POST['district_id'] ?? 0),
            'name' => trim($_POST['name'] ?? ''),
            'type' => trim($_POST['type'] ?? 'Municipality'),
            'description' => trim($_POST['description'] ?? ''),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $errors = $this->validateMuniCity($data);

        if ($data['district_id'] <= 0) {
            $errors[] = 'A district must be selected.';
        } else {
            $districtStmt = $this->pdo->prepare("SELECT id FROM districts WHERE id = ? AND is_deleted = 0 LIMIT 1");
            $districtStmt->execute([$data['district_id']]);
            if (!$districtStmt->fetch()) {
                $errors[] = 'Selected district does not exist.';
            }
        }

        $duplicateStmt = $this->pdo->prepare("SELECT id FROM municities WHERE name = ? AND is_deleted = 0 AND id != ? LIMIT 1");
        $duplicateStmt->execute([$data['name'], $id]);
        if ($duplicateStmt->fetch()) {
            $errors[] = "Another municipality/city with the name \"{$data['name']}\" already exists.";
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            redirect('master/municipalities');
        }

        try {
            $oldStmt = $this->pdo->prepare("SELECT * FROM municities WHERE id = ? AND is_deleted = 0 LIMIT 1");
            $oldStmt->execute([$id]);
            $oldRecord = $oldStmt->fetch();

            $stmt = $this->pdo->prepare("UPDATE municities SET district_id = ?, name = ?, type = ?, description = ?, sort_order = ?, is_active = ?, updated_by = ? WHERE id = ? AND is_deleted = 0");
            $stmt->execute([
                $data['district_id'],
                $data['name'],
                $data['type'],
                $data['description'] ?: null,
                $data['sort_order'],
                $data['is_active'],
                $userId,
                $id,
            ]);

            audit_log('UPDATE', 'Municipality/City', (string)$id, $oldRecord ?: null, $data, "Updated municipality/city: {$data['name']}");
            system_log('INFO', "Municipality/City updated: {$data['name']} (ID: {$id})");
            flash_set('success', "Municipality/city \"{$data['name']}\" updated successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'Municipality/City update failed', ['error' => $e->getMessage(), 'municipality_id' => $id, 'data' => $data]);
            flash_set('error', 'Failed to update municipality/city: ' . $e->getMessage());
        }

        redirect('master/municipalities');
    }

    public function destroy(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid municipality/city ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name FROM municities WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $municipality = $stmt->fetch();

        if (!$municipality) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Municipality/city not found.']);
            exit;
        }

        $oldStmt = $this->pdo->prepare("SELECT * FROM municities WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $oldStmt->execute([$id]);
        $oldRecord = $oldStmt->fetch();

        $stmt = $this->pdo->prepare("UPDATE municities SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$userId, $userId, $id]);

        header('Content-Type: application/json');
        if ($result) {
            audit_log('DELETE', 'Municipality/City', (string)$id, $oldRecord ?: null, null, "Soft-deleted municipality/city: {$municipality['name']}");
            system_log('INFO', "Municipality/City deleted (soft): {$municipality['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "Municipality/city \"{$municipality['name']}\" deleted successfully."]);
        } else {
            system_log('WARNING', "Failed to delete municipality/city (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to delete municipality/city.']);
        }
        exit;
    }

    public function toggleStatus(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid municipality/city ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name, is_active FROM municities WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $municipality = $stmt->fetch();

        if (!$municipality) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Municipality/city not found.']);
            exit;
        }

        $newActive = $municipality['is_active'] ? 0 : 1;
        $oldStatus = $municipality['is_active'] ? 'Active' : 'Inactive';
        $newStatusLabel = $newActive ? 'Active' : 'Inactive';
        $stmt = $this->pdo->prepare("UPDATE municities SET is_active = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$newActive, $userId, $id]);

        $action = $newActive ? 'activated' : 'deactivated';
        header('Content-Type: application/json');
        if ($result) {
            audit_log('UPDATE', 'Municipality/City', (string)$id, ['is_active' => $oldStatus], ['is_active' => $newStatusLabel], "Municipality/City \"{$municipality['name']}\" {$action}");
            system_log('INFO', "Municipality/City status {$action}: {$municipality['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "Municipality/city \"{$municipality['name']}\" has been {$action}."]);
        } else {
            system_log('WARNING', "Failed to toggle municipality/city status (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
        }
        exit;
    }

    protected function validateMuniCity(array $data): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'Municipality/city name is required.';
        } elseif (mb_strlen($data['name']) > 255) {
            $errors[] = 'Municipality/city name must not exceed 255 characters.';
        }

        if ($data['type'] === '') {
            $errors[] = 'Type is required.';
        } elseif (mb_strlen($data['type']) > 20) {
            $errors[] = 'Type must not exceed 20 characters.';
        }

        if ($data['sort_order'] < 0) {
            $errors[] = 'Sort order must be a non-negative integer.';
        }

        return $errors;
    }
}

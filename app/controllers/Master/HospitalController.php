<?php

require_once __DIR__ . '/../../config/database.php';

class HospitalController
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

        $whereClauses = ['h.is_deleted = 0'];
        $params = [];

        if ($search !== '') {
            $whereClauses[] = 'h.name LIKE ?';
            $params[] = "%{$search}%";
        }

        if ($filterStatus !== '') {
            $whereClauses[] = 'h.is_active = ?';
            $params[] = $filterStatus === 'active' ? 1 : 0;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM hospitals h WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT h.* FROM hospitals h
                WHERE {$whereSql}
                ORDER BY h.sort_order ASC, h.name ASC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $hospitals = $stmt->fetchAll();

        $totalHospitals = (int)$this->pdo->query("SELECT COUNT(*) FROM hospitals WHERE is_deleted = 0")->fetchColumn();
        $activeHospitals = (int)$this->pdo->query("SELECT COUNT(*) FROM hospitals WHERE is_deleted = 0 AND is_active = 1")->fetchColumn();
        $inactiveHospitals = $totalHospitals - $activeHospitals;

        $success = flash_get('success');
        $error = flash_get('error');

        $pageTitle = 'Hospitals Management';
        $pageSubtitle = 'Manage recognized hospitals and their availability in the system';
        $accent = 'primary';

        $viewDir = __DIR__ . '/../../../resources/views/master/hospitals';
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
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $errors = $this->validateHospital($data);

        $duplicateStmt = $this->pdo->prepare("SELECT id FROM hospitals WHERE name = ? AND is_deleted = 0 LIMIT 1");
        $duplicateStmt->execute([$data['name']]);
        if ($duplicateStmt->fetch()) {
            $errors[] = "A hospital with the name \"{$data['name']}\" already exists.";
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            old_set($_POST);
            redirect('master/hospitals');
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO hospitals (name, sort_order, is_active, created_by, updated_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['name'],
                $data['sort_order'],
                $data['is_active'],
                $userId,
                $userId,
            ]);
            $newId = (int)$this->pdo->lastInsertId();

            audit_log('CREATE', 'Hospital', (string)$newId, null, $data, "Created hospital: {$data['name']}");
            system_log('INFO', "Hospital created: {$data['name']} (ID: {$newId})");
            flash_set('success', "Hospital \"{$data['name']}\" created successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'Hospital create failed', ['error' => $e->getMessage(), 'data' => $data]);
            flash_set('error', 'Failed to create hospital: ' . $e->getMessage());
            old_set($_POST);
        }

        redirect('master/hospitals');
    }

    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid hospital ID.']);
            exit;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM hospitals WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $hospital = $stmt->fetch();

        if (!$hospital) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Hospital not found.']);
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $hospital]);
        exit;
    }

    public function update(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash_set('error', 'Invalid hospital ID.');
            redirect('master/hospitals');
        }

        $userId = auth_id();
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $errors = $this->validateHospital($data);

        $duplicateStmt = $this->pdo->prepare("SELECT id FROM hospitals WHERE name = ? AND is_deleted = 0 AND id != ? LIMIT 1");
        $duplicateStmt->execute([$data['name'], $id]);
        if ($duplicateStmt->fetch()) {
            $errors[] = "Another hospital with the name \"{$data['name']}\" already exists.";
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            redirect('master/hospitals');
        }

        try {
            $oldStmt = $this->pdo->prepare("SELECT * FROM hospitals WHERE id = ? AND is_deleted = 0 LIMIT 1");
            $oldStmt->execute([$id]);
            $oldRecord = $oldStmt->fetch();

            $stmt = $this->pdo->prepare("UPDATE hospitals SET name = ?, sort_order = ?, is_active = ?, updated_by = ? WHERE id = ? AND is_deleted = 0");
            $stmt->execute([
                $data['name'],
                $data['sort_order'],
                $data['is_active'],
                $userId,
                $id,
            ]);

            audit_log('UPDATE', 'Hospital', (string)$id, $oldRecord ?: null, $data, "Updated hospital: {$data['name']}");
            system_log('INFO', "Hospital updated: {$data['name']} (ID: {$id})");
            flash_set('success', "Hospital \"{$data['name']}\" updated successfully.");
        } catch (\Exception $e) {
            system_log('ERROR', 'Hospital update failed', ['error' => $e->getMessage(), 'hospital_id' => $id, 'data' => $data]);
            flash_set('error', 'Failed to update hospital: ' . $e->getMessage());
        }

        redirect('master/hospitals');
    }

    public function destroy(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid hospital ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name FROM hospitals WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $hospital = $stmt->fetch();

        if (!$hospital) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Hospital not found.']);
            exit;
        }

        $oldStmt = $this->pdo->prepare("SELECT * FROM hospitals WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $oldStmt->execute([$id]);
        $oldRecord = $oldStmt->fetch();

        $stmt = $this->pdo->prepare("UPDATE hospitals SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$userId, $userId, $id]);

        header('Content-Type: application/json');
        if ($result) {
            audit_log('DELETE', 'Hospital', (string)$id, $oldRecord ?: null, null, "Soft-deleted hospital: {$hospital['name']}");
            system_log('INFO', "Hospital deleted (soft): {$hospital['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "Hospital \"{$hospital['name']}\" deleted successfully."]);
        } else {
            system_log('WARNING', "Failed to delete hospital (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to delete hospital.']);
        }
        exit;
    }

    public function toggleStatus(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid hospital ID.']);
            exit;
        }

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT name, is_active FROM hospitals WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$id]);
        $hospital = $stmt->fetch();

        if (!$hospital) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Hospital not found.']);
            exit;
        }

        $newActive = $hospital['is_active'] ? 0 : 1;
        $oldStatus = $hospital['is_active'] ? 'Active' : 'Inactive';
        $newStatusLabel = $newActive ? 'Active' : 'Inactive';
        $stmt = $this->pdo->prepare("UPDATE hospitals SET is_active = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$newActive, $userId, $id]);

        $action = $newActive ? 'activated' : 'deactivated';
        header('Content-Type: application/json');
        if ($result) {
            audit_log('UPDATE', 'Hospital', (string)$id, ['is_active' => $oldStatus], ['is_active' => $newStatusLabel], "Hospital \"{$hospital['name']}\" {$action}");
            system_log('INFO', "Hospital status {$action}: {$hospital['name']} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "Hospital \"{$hospital['name']}\" has been {$action}."]);
        } else {
            system_log('WARNING', "Failed to toggle hospital status (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
        }
        exit;
    }

    protected function validateHospital(array $data): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'Hospital name is required.';
        } elseif (mb_strlen($data['name']) > 255) {
            $errors[] = 'Hospital name must not exceed 255 characters.';
        }

        if ($data['sort_order'] < 0) {
            $errors[] = 'Sort order must be a non-negative integer.';
        }

        return $errors;
    }
}

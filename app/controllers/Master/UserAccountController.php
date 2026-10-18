<?php

require_once __DIR__ . '/../../config/database.php';

class UserAccountController
{
    protected PDO $pdo;

    private const STATUSES = ['active', 'deactivated', 'blocked'];

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

        $whereClauses = ['ua.is_deleted = 0'];
        $params = [];

        if ($search !== '') {
            $whereClauses[] = '(ua.username LIKE ? OR ua.email LIKE ?
                OR ui.first_name LIKE ? OR ui.last_name LIKE ?
                OR ui.middle_name LIKE ? OR r.name LIKE ?)';
            $like = "%{$search}%";
            array_push($params, $like, $like, $like, $like, $like, $like);
        }

        if ($filterStatus !== '' && in_array($filterStatus, self::STATUSES, true)) {
            $whereClauses[] = 'ua.status = ?';
            $params[] = $filterStatus;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $countStmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM user_accounts ua
            LEFT JOIN user_info ui ON ui.user_account_id = ua.id
            LEFT JOIN roles r ON r.id = ua.role_id
            WHERE {$whereSql}
        ");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT
                ua.id, ua.username, ua.email, ua.role_id, ua.status,
                ua.created_at, ua.updated_at,
                r.name AS role_name,
                ui.first_name, ui.middle_name, ui.last_name, ui.suffix, ui.contact_number
            FROM user_accounts ua
            LEFT JOIN user_info ui ON ui.user_account_id = ua.id
            LEFT JOIN roles r ON r.id = ua.role_id
            WHERE {$whereSql}
            ORDER BY ua.created_at DESC, ua.username ASC
            LIMIT {$perPage} OFFSET {$offset}
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $accounts = $stmt->fetchAll();

        $totalAccounts = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM user_accounts WHERE is_deleted = 0"
        )->fetchColumn();
        $activeAccounts = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM user_accounts WHERE is_deleted = 0 AND status = 'active'"
        )->fetchColumn();
        $deactivatedAccounts = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM user_accounts WHERE is_deleted = 0 AND status = 'deactivated'"
        )->fetchColumn();
        $blockedAccounts = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM user_accounts WHERE is_deleted = 0 AND status = 'blocked'"
        )->fetchColumn();

        $roles = $this->pdo->query(
            "SELECT id, name FROM roles WHERE is_deleted = 0 AND is_active = 1 ORDER BY sort_order ASC, name ASC"
        )->fetchAll();

        $currentUserId = auth_id();
        $success = flash_get('success');
        $error = flash_get('error');

        $pageTitle = 'User Accounts Management';
        $pageSubtitle = 'Manage system user accounts, credentials, and access status';
        $accent = 'primary';

        $viewDir = __DIR__ . '/../../../resources/views/master/users';
        if (!is_dir($viewDir)) {
            mkdir($viewDir, 0777, true);
        }
        require $viewDir . '/index.php';
    }

    public function store(): void
    {
        $userId = auth_id();
        $data = $this->collectInput(true);
        $errors = $this->validateAccount($data, true);

        $this->appendUniquenessErrors($errors, $data['username'], $data['email']);

        if (!$this->roleAssignable((int)$data['role_id'])) {
            $errors[] = 'Selected role is invalid or inactive.';
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            old_set($this->oldInputWithoutSecrets($_POST));
            redirect('master/users');
        }

        $this->pdo->beginTransaction();
        try {
            $insertAccount = $this->pdo->prepare("
                INSERT INTO user_accounts
                    (username, email, password_hash, role_id, status, failed_login_attempts, created_by, updated_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 0, ?, ?, NOW(), NOW())
            ");
            $insertAccount->execute([
                $data['username'],
                $data['email'],
                password_hash($data['password'], PASSWORD_BCRYPT),
                (int)$data['role_id'],
                $data['status'],
                $userId,
                $userId,
            ]);

            $accountId = (int)$this->pdo->lastInsertId();

            $insertInfo = $this->pdo->prepare("
                INSERT INTO user_info
                    (user_account_id, first_name, middle_name, last_name, suffix, contact_number, profile_path, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NULL, NOW(), NOW())
            ");
            $insertInfo->execute([
                $accountId,
                $data['first_name'],
                $data['middle_name'] ?: null,
                $data['last_name'],
                $data['suffix'] ?: null,
                $data['contact_number'] ?: null,
            ]);

            $this->pdo->commit();

            $auditData = [
                'username' => $data['username'],
                'email' => $data['email'],
                'role_id' => (int)$data['role_id'],
                'status' => $data['status'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
            ];
            audit_log('CREATE', 'User', (string)$accountId, null, $auditData, "Created user account: {$data['username']}");
            system_log('INFO', "User account created: {$data['username']} (ID: {$accountId})");
            flash_set('success', "Account \"{$data['username']}\" created successfully.");
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            system_log('ERROR', 'User account create failed', ['error' => $e->getMessage(), 'username' => $data['username'] ?? null]);
            flash_set('error', 'Failed to create account. Please try again.');
            old_set($this->oldInputWithoutSecrets($_POST));
        }

        redirect('master/users');
    }

    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonResponse(false, 'Invalid account ID.');
        }

        $stmt = $this->pdo->prepare("
            SELECT
                ua.id, ua.username, ua.email, ua.role_id, ua.status,
                ui.first_name, ui.middle_name, ui.last_name, ui.suffix, ui.contact_number
            FROM user_accounts ua
            LEFT JOIN user_info ui ON ui.user_account_id = ua.id
            WHERE ua.id = ? AND ua.is_deleted = 0
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $account = $stmt->fetch();

        if (!$account) {
            $this->jsonResponse(false, 'Account not found.');
        }

        $this->jsonResponse(true, null, $account);
    }

    public function update(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash_set('error', 'Invalid account ID.');
            redirect('master/users');
        }

        $userId = auth_id();
        $data = $this->collectInput(false);
        $errors = $this->validateAccount($data, false);

        $oldStmt = $this->pdo->prepare("
            SELECT ua.*, ui.first_name, ui.middle_name, ui.last_name, ui.suffix, ui.contact_number
            FROM user_accounts ua
            LEFT JOIN user_info ui ON ui.user_account_id = ua.id
            WHERE ua.id = ? AND ua.is_deleted = 0
            LIMIT 1
        ");
        $oldStmt->execute([$id]);
        $oldRecord = $oldStmt->fetch();

        if (!$oldRecord) {
            flash_set('error', 'Account not found.');
            redirect('master/users');
        }

        if ($userId !== null && (int)$id === (int)$userId && $data['status'] !== 'active') {
            $errors[] = 'You cannot deactivate or block your own signed-in account.';
        }

        if ($userId !== null && (int)$id === (int)$userId) {
            $currentRoleStmt = $this->pdo->prepare(
                "SELECT r.name FROM roles r WHERE r.id = ? LIMIT 1"
            );
            $currentRoleStmt->execute([(int)$oldRecord['role_id']]);
            $currentRoleName = (string)$currentRoleStmt->fetchColumn();
            $newRoleStmt = $this->pdo->prepare(
                "SELECT name FROM roles WHERE id = ? AND is_deleted = 0 LIMIT 1"
            );
            $newRoleStmt->execute([(int)$data['role_id']]);
            $newRoleName = (string)$newRoleStmt->fetchColumn();
            if (strcasecmp($currentRoleName, 'Super Admin') === 0 && strcasecmp($newRoleName, 'Super Admin') !== 0) {
                $errors[] = 'You cannot remove Super Admin access from your own signed-in account.';
            }
        }

        $this->appendUniquenessErrors($errors, $data['username'], $data['email'], $id);

        if (!$this->roleAssignable((int)$data['role_id'], (int)$oldRecord['role_id'])) {
            $errors[] = 'Selected role is invalid or inactive.';
        }

        if (!empty($errors)) {
            flash_set('error', implode('<br>', $errors));
            redirect('master/users');
        }

        $this->pdo->beginTransaction();
        try {
            if ($data['password'] !== '') {
                $stmt = $this->pdo->prepare("
                    UPDATE user_accounts
                    SET username = ?, email = ?, password_hash = ?, role_id = ?, status = ?,
                        password_changed_at = NOW(), updated_by = ?
                    WHERE id = ? AND is_deleted = 0
                ");
                $stmt->execute([
                    $data['username'],
                    $data['email'],
                    password_hash($data['password'], PASSWORD_BCRYPT),
                    (int)$data['role_id'],
                    $data['status'],
                    $userId,
                    $id,
                ]);
            } else {
                $stmt = $this->pdo->prepare("
                    UPDATE user_accounts
                    SET username = ?, email = ?, role_id = ?, status = ?, updated_by = ?
                    WHERE id = ? AND is_deleted = 0
                ");
                $stmt->execute([
                    $data['username'],
                    $data['email'],
                    (int)$data['role_id'],
                    $data['status'],
                    $userId,
                    $id,
                ]);
            }

            $infoCheck = $this->pdo->prepare("SELECT id FROM user_info WHERE user_account_id = ? LIMIT 1");
            $infoCheck->execute([$id]);
            if ($infoCheck->fetch()) {
                $infoStmt = $this->pdo->prepare("
                    UPDATE user_info
                    SET first_name = ?, middle_name = ?, last_name = ?, suffix = ?, contact_number = ?
                    WHERE user_account_id = ?
                ");
                $infoStmt->execute([
                    $data['first_name'],
                    $data['middle_name'] ?: null,
                    $data['last_name'],
                    $data['suffix'] ?: null,
                    $data['contact_number'] ?: null,
                    $id,
                ]);
            } else {
                $infoStmt = $this->pdo->prepare("
                    INSERT INTO user_info
                        (user_account_id, first_name, middle_name, last_name, suffix, contact_number, profile_path, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, NULL, NOW(), NOW())
                ");
                $infoStmt->execute([
                    $id,
                    $data['first_name'],
                    $data['middle_name'] ?: null,
                    $data['last_name'],
                    $data['suffix'] ?: null,
                    $data['contact_number'] ?: null,
                ]);
            }

            $this->pdo->commit();

            $newData = [
                'username' => $data['username'],
                'email' => $data['email'],
                'role_id' => (int)$data['role_id'],
                'status' => $data['status'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'password_changed' => $data['password'] !== '',
            ];

            if ($oldRecord['status'] !== $data['status']) {
                audit_log(
                    'UPDATE',
                    'User',
                    (string)$id,
                    ['status' => $oldRecord['status']],
                    ['status' => $data['status']],
                    "User \"{$data['username']}\" status changed from {$oldRecord['status']} to {$data['status']}"
                );
            }

            if ($data['password'] !== '') {
                log_password_change($id, true, 'Password reset from User Accounts management');
            }

            audit_log('UPDATE', 'User', (string)$id, $this->auditSafeAccount($oldRecord), $newData, "Updated user account: {$data['username']}");
            system_log('INFO', "User account updated: {$data['username']} (ID: {$id})");
            flash_set('success', "Account \"{$data['username']}\" updated successfully.");
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            system_log('ERROR', 'User account update failed', ['error' => $e->getMessage(), 'account_id' => $id]);
            flash_set('error', 'Failed to update account: ' . $e->getMessage());
        }

        redirect('master/users');
    }

    public function updateStatus(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? '');

        if ($id <= 0) {
            $this->jsonResponse(false, 'Invalid account ID.');
        }

        if (!in_array($status, self::STATUSES, true)) {
            $this->jsonResponse(false, 'Invalid status value.');
        }

        $userId = auth_id();

        if ($userId !== null && (int)$id === (int)$userId && $status !== 'active') {
            $this->jsonResponse(false, 'You cannot deactivate or block your own signed-in account.');
        }

        $stmt = $this->pdo->prepare(
            "SELECT id, username, status FROM user_accounts WHERE id = ? AND is_deleted = 0 LIMIT 1"
        );
        $stmt->execute([$id]);
        $account = $stmt->fetch();

        if (!$account) {
            $this->jsonResponse(false, 'Account not found.');
        }

        if ($account['status'] === $status) {
            $this->jsonResponse(true, "Account \"{$account['username']}\" is already {$status}.");
        }

        $update = $this->pdo->prepare(
            "UPDATE user_accounts SET status = ?, updated_by = ? WHERE id = ? AND is_deleted = 0"
        );
        $result = $update->execute([$status, $userId, $id]);

        if ($result) {
            audit_log(
                'UPDATE',
                'User',
                (string)$id,
                ['status' => $account['status']],
                ['status' => $status],
                "User \"{$account['username']}\" status changed from {$account['status']} to {$status}"
            );
            system_log('INFO', "User account status changed: {$account['username']} ({$account['status']} → {$status})");
            $this->jsonResponse(true, "Account \"{$account['username']}\" is now {$status}.");
        }

        system_log('WARNING', "Failed to update user account status (ID: {$id})");
        $this->jsonResponse(false, 'Failed to update status.');
    }

    public function destroy(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonResponse(false, 'Invalid account ID.');
        }

        $userId = auth_id();

        if ($userId !== null && (int)$id === (int)$userId) {
            $this->jsonResponse(false, 'You cannot delete your own signed-in account.');
        }

        $stmt = $this->pdo->prepare(
            "SELECT * FROM user_accounts WHERE id = ? AND is_deleted = 0 LIMIT 1"
        );
        $stmt->execute([$id]);
        $account = $stmt->fetch();

        if (!$account) {
            $this->jsonResponse(false, 'Account not found.');
        }

        $delete = $this->pdo->prepare("
            UPDATE user_accounts
            SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?, updated_by = ?
            WHERE id = ? AND is_deleted = 0
        ");
        $result = $delete->execute([$userId, $userId, $id]);

        if ($result) {
            audit_log(
                'DELETE',
                'User',
                (string)$id,
                $this->auditSafeAccount($account),
                null,
                "Soft-deleted user account: {$account['username']}"
            );
            system_log('INFO', "User account deleted (soft): {$account['username']} (ID: {$id})");
            $this->jsonResponse(true, "Account \"{$account['username']}\" deleted successfully.");
        }

        system_log('WARNING', "Failed to delete user account (ID: {$id})");
        $this->jsonResponse(false, 'Failed to delete account.');
    }

    protected function collectInput(bool $requirePassword): array
    {
        return [
            'username' => trim($_POST['username'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'password_confirm' => $_POST['password_confirm'] ?? '',
            'role_id' => (int)($_POST['role_id'] ?? 0),
            'status' => trim($_POST['status'] ?? 'active'),
            'first_name' => trim($_POST['first_name'] ?? ''),
            'middle_name' => trim($_POST['middle_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'suffix' => trim($_POST['suffix'] ?? ''),
            'contact_number' => trim($_POST['contact_number'] ?? ''),
            '_require_password' => $requirePassword,
        ];
    }

    protected function validateAccount(array $data, bool $requirePassword): array
    {
        $errors = [];

        if ($data['username'] === '') {
            $errors[] = 'Username is required.';
        } elseif (mb_strlen($data['username']) > 50) {
            $errors[] = 'Username must not exceed 50 characters.';
        }

        if ($data['email'] === '') {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format.';
        } elseif (mb_strlen($data['email']) > 100) {
            $errors[] = 'Email must not exceed 100 characters.';
        }

        if ($requirePassword || $data['password'] !== '') {
            if ($data['password'] === '') {
                $errors[] = 'Password is required.';
            } else {
                $policyError = validate_password_policy($data['password']);
                if ($policyError !== null) {
                    $errors[] = $policyError;
                }
            }
            if ($data['password'] !== $data['password_confirm']) {
                $errors[] = 'Passwords do not match.';
            }
        }

        if ((int)$data['role_id'] <= 0) {
            $errors[] = 'Role is required.';
        }

        if (!in_array($data['status'], self::STATUSES, true)) {
            $errors[] = 'Invalid account status.';
        }

        if ($data['first_name'] === '') {
            $errors[] = 'First name is required.';
        } elseif (mb_strlen($data['first_name']) > 50) {
            $errors[] = 'First name must not exceed 50 characters.';
        }

        if ($data['last_name'] === '') {
            $errors[] = 'Last name is required.';
        } elseif (mb_strlen($data['last_name']) > 50) {
            $errors[] = 'Last name must not exceed 50 characters.';
        }

        if ($data['middle_name'] !== '' && mb_strlen($data['middle_name']) > 50) {
            $errors[] = 'Middle name must not exceed 50 characters.';
        }

        if ($data['suffix'] !== '' && mb_strlen($data['suffix']) > 10) {
            $errors[] = 'Suffix must not exceed 10 characters.';
        }

        if ($data['contact_number'] !== '' && mb_strlen($data['contact_number']) > 20) {
            $errors[] = 'Contact number must not exceed 20 characters.';
        }

        return $errors;
    }

    protected function roleAssignable(int $roleId, ?int $currentRoleId = null): bool
    {
        if ($roleId <= 0) {
            return false;
        }

        if ($currentRoleId !== null && $roleId === $currentRoleId) {
            $stmt = $this->pdo->prepare(
                "SELECT id FROM roles WHERE id = ? AND is_deleted = 0 LIMIT 1"
            );
            $stmt->execute([$roleId]);
            return (bool)$stmt->fetch();
        }

        $stmt = $this->pdo->prepare(
            "SELECT id FROM roles WHERE id = ? AND is_deleted = 0 AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([$roleId]);
        return (bool)$stmt->fetch();
    }

    protected function appendUniquenessErrors(array &$errors, string $username, string $email, ?int $excludeId = null): void
    {
        if ($username !== '') {
            $sql = "SELECT id FROM user_accounts WHERE username = ?";
            $params = [$username];
            if ($excludeId !== null) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }
            $sql .= " LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->fetch()) {
                $errors[] = 'Username is already taken.';
            }
        }

        if ($email !== '') {
            $sql = "SELECT id FROM user_accounts WHERE email = ?";
            $params = [$email];
            if ($excludeId !== null) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }
            $sql .= " LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->fetch()) {
                $errors[] = 'Email is already in use.';
            }
        }
    }

    protected function oldInputWithoutSecrets(array $input): array
    {
        unset($input['password'], $input['password_confirm']);
        return $input;
    }

    protected function auditSafeAccount(array $row): array
    {
        unset($row['password_hash'], $row['password'], $row['password_confirm']);
        return $row;
    }

    protected function jsonResponse(bool $success, ?string $message = null, mixed $data = null): void
    {
        header('Content-Type: application/json');
        $payload = ['success' => $success];
        if ($message !== null) {
            $payload['message'] = $message;
        }
        if ($data !== null) {
            $payload['data'] = $data;
        }
        echo json_encode($payload);
        exit;
    }
}

<?php

require_once __DIR__ . '/../config/database.php';

class AuthController
{
    protected PDO $pdo;

    public function __construct()
    {
        $database = new Database();
        $this->pdo = $database->connect();
    }

    public function showLogin(): void
    {
        $pageTitle = 'Login';
        require __DIR__ . '/../../resources/views/auth/login.php';
    }

    public function showRegister(): void
    {
        $pageTitle = 'Register';
        require __DIR__ . '/../../resources/views/auth/register.php';
    }

    public function login(): void
    {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        old_set(['username' => $username]);

        if ($username === '' || $password === '') {
            flash_set('error', 'Username and password are required.');
            redirect('login');
        }

        $stmt = $this->pdo->prepare("
            SELECT 
                ua.id, ua.username, ua.email, ua.password_hash, ua.status, ua.role_id, ua.locked_at, ua.last_lockout_reason,
                r.name AS role_name,
                ui.first_name, ui.middle_name, ui.last_name, ui.suffix, ui.contact_number, ui.profile_path
            FROM user_accounts ua
            INNER JOIN roles r ON r.id = ua.role_id
            LEFT JOIN user_info ui ON ui.user_account_id = ua.id
            WHERE ua.username = ? OR ua.email = ?
            LIMIT 1
        ");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && check_login_lockout((int)$user['id'], $user['username'])) {
            $remainingSec = max(0, (strtotime($user['locked_at'] ?? 'now') + (LOGIN_LOCKOUT_MINUTES * 60)) - time());
            $waitMin = (int)ceil($remainingSec / 60);
            $reason = $user['last_lockout_reason'] ?? 'Too many failed login attempts.';
            system_log('WARNING', 'Blocked login attempt due to lockout', ['username' => $username, 'user_id' => (int)$user['id']]);
            flash_set('error', "Account is temporarily locked. {$reason} Please try again after {$waitMin} minute(s).");
            redirect('login');
        }

        if (!$user || !password_verify($password, $user['password_hash'])) {
            if ($user) {
                $update = $this->pdo->prepare("
                    UPDATE user_accounts 
                    SET failed_login_attempts = failed_login_attempts + 1 
                    WHERE id = ?
                ");
                $update->execute([$user['id']]);
                check_login_lockout((int)$user['id'], $user['username']);
            }
            system_log('WARNING', 'Failed login attempt', ['username' => $username]);
            flash_set('error', 'Invalid username or password.');
            redirect('login');
        }

        if ($user['status'] !== 'active') {
            flash_set('error', 'Your account is not active. Please contact the administrator.');
            redirect('login');
        }

        $resetAttempts = $this->pdo->prepare("
            UPDATE user_accounts SET failed_login_attempts = 0, locked_at = NULL, last_lockout_reason = NULL WHERE id = ?
        ");
        $resetAttempts->execute([$user['id']]);

        $fullName = trim(implode(' ', array_filter([
            $user['first_name'] ?? '',
            $user['suffix'] ?? '',
            $user['last_name'] ?? ''
        ])));

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role_id' => (int) $user['role_id'],
            'role_name' => $user['role_name'],
            'status' => $user['status'],
            'first_name' => $user['first_name'],
            'middle_name' => $user['middle_name'],
            'last_name' => $user['last_name'],
            'suffix' => $user['suffix'],
            'full_name' => $fullName !== '' ? $fullName : $user['username'],
            'contact_number' => $user['contact_number'],
            'profile_path' => $user['profile_path'],
        ];

        $_SESSION['_flash'] = [];

        $redirect = dashboard_route_for_role($user['role_name']);
        flash_set('success', "Welcome back, {$_SESSION['user']['full_name']}!");
        audit_log('LOGIN', 'User', (string)$user['id'], null, null, "User {$_SESSION['user']['full_name']} logged in via username '{$user['username']}'");
        system_log('INFO', "User logged in: {$user['username']}");
        redirect($redirect);
    }

    public function register(): void
    {
        $data = [
            'username' => trim($_POST['username'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'password_confirm' => $_POST['password_confirm'] ?? '',
            'first_name' => trim($_POST['first_name'] ?? ''),
            'middle_name' => trim($_POST['middle_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'suffix' => trim($_POST['suffix'] ?? ''),
            'contact_number' => trim($_POST['contact_number'] ?? ''),
        ];

        old_set($data);

        $errors = [];

        if ($data['username'] === '') $errors[] = 'Username is required.';
        if ($data['email'] === '') $errors[] = 'Email is required.';
        elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format.';
        if ($data['password'] === '') $errors[] = 'Password is required.';
        elseif (strlen($data['password']) < 6) $errors[] = 'Password must be at least 6 characters.';
        if ($data['password'] !== $data['password_confirm']) $errors[] = 'Passwords do not match.';
        if ($data['first_name'] === '') $errors[] = 'First name is required.';
        if ($data['last_name'] === '') $errors[] = 'Last name is required.';

        $checkStmt = $this->pdo->prepare("SELECT COUNT(*) FROM user_accounts WHERE username = ? OR email = ?");
        $checkStmt->execute([$data['username'], $data['email']]);
        if ($checkStmt->fetchColumn() > 0) {
            $errors[] = 'Username or email already exists.';
        }

        if (!empty($errors)) {
            flash_set('errors', $errors);
            redirect('register');
        }

        $this->pdo->beginTransaction();
        try {
            $defaultRoleStmt = $this->pdo->query("SELECT id FROM roles WHERE name = 'Receiving Staff' LIMIT 1");
            $defaultRole = $defaultRoleStmt->fetch();
            $roleId = $defaultRole ? (int) $defaultRole['id'] : 2;

            $insertAccount = $this->pdo->prepare("
                INSERT INTO user_accounts 
                    (username, email, password_hash, role_id, status, failed_login_attempts, created_at, updated_at)
                VALUES (?, ?, ?, ?, 'active', 0, NOW(), NOW())
            ");
            $insertAccount->execute([
                $data['username'],
                $data['email'],
                password_hash($data['password'], PASSWORD_BCRYPT),
                $roleId,
            ]);

            $accountId = (int) $this->pdo->lastInsertId();

            $insertInfo = $this->pdo->prepare("
                INSERT INTO user_info 
                    (user_account_id, first_name, middle_name, last_name, suffix, contact_number, profile_path, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NULL, NOW(), NOW())
            ");
            $insertInfo->execute([
                $accountId,
                $data['first_name'] ?: null,
                $data['middle_name'] ?: null,
                $data['last_name'] ?: null,
                $data['suffix'] ?: null,
                $data['contact_number'] ?: null,
            ]);

            $this->pdo->commit();

            audit_log('CREATE', 'User', (string)$accountId, null, [
                'username' => $data['username'],
                'email' => $data['email'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'role_id' => $roleId,
            ], "New user registered: {$data['username']} ({$data['first_name']} {$data['last_name']})");
            system_log('INFO', "New user registration: {$data['username']}");
            flash_set('success', 'Account created successfully. You can now sign in.');
            redirect('login');
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            system_log('ERROR', 'Registration failed', ['error' => $e->getMessage(), 'username' => $data['username'] ?? null]);
            flash_set('error', 'Registration failed. Please try again.');
            redirect('register');
        }
    }

    public function relogin(): void
    {
        $redirect = dashboard_route_for_role(auth_role());
        redirect($redirect);
    }

    public function logout(): void
    {
        $uid = auth_id();
        $uName = auth()['full_name'] ?? (auth()['username'] ?? 'User');
        if ($uid !== null) {
            audit_log('LOGOUT', 'User', (string)$uid, null, null, "User {$uName} signed out");
            system_log('INFO', "User logged out: {$uName}");
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
        flash_set('success', 'You have been signed out.');
        redirect('login');
    }
}

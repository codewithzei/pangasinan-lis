<?php

define('APP_NAME', 'Pangasinan Legislative Information System');
define('APP_SHORT_NAME', 'Pangasinan Legis+');

define('BASE_URL', '/pangasinan-lis');

define('PUBLIC_PATH', __DIR__ . '/../../public');
define('STORAGE_PATH', __DIR__ . '/../../storage');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function auth(): ?array
{
    if (!isset($_SESSION['user'])) {
        return null;
    }
    return $_SESSION['user'];
}

function auth_id(): ?int
{
    $user = auth();
    return $user ? (int) $user['id'] : null;
}

function auth_role(): ?string
{
    $user = auth();
    return $user ? ($user['role_name'] ?? null) : null;
}

function is_logged_in(): bool
{
    return auth() !== null;
}

function is_role(string $roleName): bool
{
    $role = auth_role();
    return $role !== null && strcasecmp($role, $roleName) === 0;
}

function redirect(string $path): void
{
    $url = BASE_URL . '/' . ltrim($path, '/');
    header('Location: ' . $url);
    exit;
}

function flash_set(string $key, mixed $value): void
{
    $_SESSION['_flash'][$key] = $value;
}

function flash_get(string $key, mixed $default = null): mixed
{
    $value = $_SESSION['_flash'][$key] ?? $default;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

function flash_has(string $key): bool
{
    return isset($_SESSION['_flash'][$key]);
}

function old(string $key, string $default = ''): string
{
    return htmlspecialchars($_SESSION['_old'][$key] ?? $default);
}

function old_set(array $data): void
{
    $_SESSION['_old'] = $data;
}

function old_clear(): void
{
    unset($_SESSION['_old']);
}

function dashboard_route_for_role(?string $roleName): string
{
    $map = [
        'Super Admin' => 'master/dashboard',
        'Receiving Staff' => 'receiving/dashboard',
        'Admin' => 'admin/dashboard',
        'SP Secretary' => 'spsec/dashboard',
        'Plenary' => 'plenary/dashboard',
        'Committee' => 'committee/dashboard',
    ];
    return $map[$roleName] ?? 'dashboard';
}

function client_ip(): ?string
{
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR',
    ];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = explode(',', $_SERVER[$header])[0];
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return null;
}

function client_user_agent(): ?string
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if ($ua === '') {
        return null;
    }
    return mb_substr($ua, 0, 500);
}

function _log_pdo(): ?PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    require_once __DIR__ . '/database.php';
    try {
        $db = new Database();
        $pdo = $db->connect();
        return $pdo;
    } catch (\Throwable $e) {
        return null;
    }
}

function audit_log(
    string $action,
    ?string $entity_type = null,
    ?string $entity_id = null,
    ?array $old_values = null,
    ?array $new_values = null,
    ?string $description = null,
    ?int $override_user_id = null
): void {
    $validActions = [
        'CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'VIEW',
        'EXPORT', 'IMPORT', 'ARCHIVE', 'RESTORE', 'APPROVE',
        'REJECT', 'SUBMIT', 'ROUTE', 'UNLOCK', 'PASSWORD_CHANGE',
        'LOCKOUT', 'OTHER'
    ];
    $action = strtoupper(trim($action));
    if (!in_array($action, $validActions, true)) {
        $action = 'OTHER';
    }

    $pdo = _log_pdo();
    if (!$pdo) {
        return;
    }

    $userId = $override_user_id !== null ? $override_user_id : auth_id();

    try {
        $stmt = $pdo->prepare("INSERT INTO audit_logs
            (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $userId,
            $action,
            $entity_type,
            $entity_id,
            $old_values !== null ? json_encode($old_values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            $new_values !== null ? json_encode($new_values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            client_ip(),
            client_user_agent(),
            $description,
        ]);
    } catch (\Throwable $e) {
        // fail silently
    }
}

function system_log(string $level, string $message, ?array $context = null): void
{
    $validLevels = ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];
    $level = strtoupper(trim($level));
    if (!in_array($level, $validLevels, true)) {
        $level = 'INFO';
    }

    $pdo = _log_pdo();
    if (!$pdo) {
        return;
    }

    try {
        $reqUrl = null;
        if (!empty($_SERVER['REQUEST_URI'])) {
            $reqUrl = mb_substr((string)$_SERVER['REQUEST_URI'], 0, 500);
        }
        $reqMethod = !empty($_SERVER['REQUEST_METHOD']) ? mb_substr(strtoupper((string)$_SERVER['REQUEST_METHOD']), 0, 10) : null;

        $stmt = $pdo->prepare("INSERT INTO system_logs
            (log_level, message, context, user_id, ip_address, user_agent, request_url, request_method, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $level,
            $message,
            $context !== null ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            auth_id(),
            client_ip(),
            client_user_agent(),
            $reqUrl,
            $reqMethod,
        ]);
    } catch (\Throwable $e) {
        // fail silently
    }
}

define('LOGIN_LOCKOUT_THRESHOLD', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

function check_login_lockout(int $accountId, ?string $username = null): bool
{
    $pdo = _log_pdo();
    if (!$pdo) return false;

    try {
        $stmt = $pdo->prepare("SELECT failed_login_attempts, locked_at, status FROM user_accounts WHERE id = ? LIMIT 1");
        $stmt->execute([$accountId]);
        $row = $stmt->fetch();
        if (!$row) return false;

        if ($row['locked_at']) {
            $lockoutEnd = strtotime($row['locked_at']) + (LOGIN_LOCKOUT_MINUTES * 60);
            if (time() < $lockoutEnd) {
                return true;
            }
            $reset = $pdo->prepare("UPDATE user_accounts SET failed_login_attempts = 0, locked_at = NULL, last_lockout_reason = NULL WHERE id = ?");
            $reset->execute([$accountId]);
            return false;
        }

        if ((int)$row['failed_login_attempts'] >= LOGIN_LOCKOUT_THRESHOLD) {
            $reason = 'Too many failed login attempts (' . LOGIN_LOCKOUT_THRESHOLD . ')';
            $lock = $pdo->prepare("UPDATE user_accounts SET locked_at = NOW(), last_lockout_reason = ? WHERE id = ?");
            $lock->execute([$reason, $accountId]);
            audit_log('LOCKOUT', 'User', (string)$accountId, null, null, "Account locked: {$reason}. Username: {$username}", $accountId);
            system_log('WARNING', 'Account locked due to failed login threshold', ['user_id' => $accountId, 'username' => $username, 'attempts' => $row['failed_login_attempts']]);
            return true;
        }
    } catch (\Throwable $e) {
        system_log('ERROR', 'check_login_lockout exception', ['error' => $e->getMessage()]);
    }
    return false;
}

function log_password_change(int $accountId, bool $byAdmin = false, ?string $reason = null): void
{
    $pdo = _log_pdo();
    if ($pdo) {
        try {
            $upd = $pdo->prepare("UPDATE user_accounts SET last_password_change = NOW() WHERE id = ?");
            $upd->execute([$accountId]);
        } catch (\Throwable $e) {}
    }

    $actor = $byAdmin ? 'Admin-initiated' : 'Self-service';
    $desc = "Password changed ({$actor})" . ($reason ? ": {$reason}" : '');
    audit_log('PASSWORD_CHANGE', 'User', (string)$accountId, null, ['reason' => $reason, 'by_admin' => $byAdmin], $desc);
    system_log('INFO', "Password changed for user ID {$accountId} ({$actor})", ['account_id' => $accountId, 'by_admin' => $byAdmin]);
}

function log_account_unlock(int $accountId, ?string $reason = null): void
{
    $pdo = _log_pdo();
    if ($pdo) {
        try {
            $reset = $pdo->prepare("UPDATE user_accounts SET failed_login_attempts = 0, locked_at = NULL, last_lockout_reason = NULL WHERE id = ?");
            $reset->execute([$accountId]);
        } catch (\Throwable $e) {}
    }

    $desc = "Account manually unlocked" . ($reason ? ": {$reason}" : '');
    audit_log('UNLOCK', 'User', (string)$accountId, null, ['reason' => $reason], $desc);
    system_log('NOTICE', "Account unlocked: {$accountId}", ['account_id' => $accountId, 'reason' => $reason]);
}

function log_file_upload(
    string $fileName,
    ?int $fileSize = null,
    ?string $mimeType = null,
    ?string $entityType = null,
    ?string $entityId = null,
    ?string $storagePath = null
): void {
    $ctx = [
        'file_name' => $fileName,
        'file_size' => $fileSize,
        'mime_type' => $mimeType,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'storage_path' => $storagePath,
    ];
    audit_log('CREATE', 'File', null, null, $ctx, "File uploaded: {$fileName}" . ($entityType ? " ({$entityType} #{$entityId})" : ''));
    system_log('INFO', "File uploaded: {$fileName}", $ctx);
}

function log_file_download(
    string $fileName,
    ?string $entityType = null,
    ?string $entityId = null,
    ?string $sourcePath = null
): void {
    $ctx = [
        'file_name' => $fileName,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'source_path' => $sourcePath,
    ];
    audit_log('VIEW', 'File', null, null, $ctx, "File downloaded: {$fileName}" . ($entityType ? " ({$entityType} #{$entityId})" : ''));
    system_log('INFO', "File downloaded: {$fileName}", $ctx);
}

function log_email_event(
    string $eventType,
    string $recipient,
    ?string $subject = null,
    ?bool $success = true,
    ?string $errorMessage = null,
    ?string $entityType = null,
    ?string $entityId = null
): void {
    $ctx = [
        'event' => $eventType,
        'recipient' => $recipient,
        'subject' => $subject,
        'success' => $success,
        'error' => $errorMessage,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
    ];
    $level = $success ? 'INFO' : 'ERROR';
    $desc = "Email {$eventType}: " . ($success ? 'sent' : 'FAILED') . " to {$recipient}" . ($subject ? " [{$subject}]" : '') . ($errorMessage ? " — {$errorMessage}" : '');
    system_log($level, $desc, $ctx);
    if (!$success || $eventType === 'PASSWORD_RESET' || $eventType === 'WELCOME' || $eventType === 'ACCOUNT_APPROVAL') {
        audit_log('OTHER', 'Notification', null, null, $ctx, $desc);
    }
}

function log_session_expiration(?int $accountId = null, ?string $username = null): void
{
    if ($accountId === null) {
        $accountId = auth_id();
    }
    $ctx = ['user_id' => $accountId, 'username' => $username ?? (auth()['username'] ?? null)];
    audit_log('LOGOUT', 'User', $accountId ? (string)$accountId : null, null, null, 'Session expired due to inactivity', $accountId);
    system_log('NOTICE', 'User session expired', $ctx);
}

function log_system_event(string $event, ?array $context = null): void
{
    $validEvents = ['STARTUP', 'SHUTDOWN', 'MAINTENANCE_START', 'MAINTENANCE_END', 'BACKUP_START', 'BACKUP_COMPLETE', 'RESTORE_START', 'RESTORE_COMPLETE', 'DB_MIGRATION'];
    $evt = strtoupper(trim($event));
    if (!in_array($evt, $validEvents, true)) {
        $evt = 'OTHER';
    }
    $ctx = is_array($context) ? $context : [];
    $ctx['event_type'] = $evt;
    system_log('NOTICE', "System event: {$evt}", $ctx);
    audit_log('OTHER', 'System', null, null, $ctx, "System {$evt} occurred");
}

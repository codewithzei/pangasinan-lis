<?php

require_once __DIR__ . '/../../config/database.php';

class DashboardController
{
    protected PDO $pdo;

    public function __construct()
    {
        $database = new Database();
        $this->pdo = $database->connect();
    }

    public function index(): void
    {
        $userCount = $this->pdo->query("SELECT COUNT(*) FROM user_accounts WHERE status = 'active' AND is_deleted = 0")->fetchColumn();
        $roleCount = $this->pdo->query("SELECT COUNT(*) FROM roles WHERE is_active = 1")->fetchColumn();

        $loginCount24h = (int)$this->pdo->query("
            SELECT COUNT(*) FROM audit_logs 
            WHERE action = 'LOGIN' AND created_at >= NOW() - INTERVAL 24 HOUR
        ")->fetchColumn();
        $lockedCount = (int)$this->pdo->query("
            SELECT COUNT(*) FROM user_accounts 
            WHERE locked_at IS NOT NULL AND locked_at >= NOW() - INTERVAL 24 HOUR
        ")->fetchColumn();

        $rolesStmt = $this->pdo->query("
            SELECT r.name, COUNT(ua.id) AS user_count
            FROM roles r
            LEFT JOIN user_accounts ua ON ua.role_id = r.id AND ua.status = 'active' AND ua.is_deleted = 0
            WHERE r.is_active = 1
            GROUP BY r.id, r.name
            ORDER BY r.id ASC
        ");
        $roleBreakdown = $rolesStmt->fetchAll();

        $recentUsersStmt = $this->pdo->query("
            SELECT 
                ua.id, ua.username, ua.email, ua.status, r.name AS role_name,
                ui.first_name, ui.last_name, ua.created_at
            FROM user_accounts ua
            INNER JOIN roles r ON r.id = ua.role_id
            LEFT JOIN user_info ui ON ui.user_account_id = ua.id
            WHERE ua.is_deleted = 0
            ORDER BY ua.created_at DESC
            LIMIT 5
        ");
        $recentUsers = $recentUsersStmt->fetchAll();

        $logStatsStmt = $this->pdo->query("
            SELECT
                (SELECT COUNT(*) FROM system_logs) AS total_sys,
                (SELECT COUNT(*) FROM system_logs WHERE log_level IN ('ERROR','CRITICAL','ALERT','EMERGENCY') AND created_at >= NOW() - INTERVAL 24 HOUR) AS error_sys_24h,
                (SELECT COUNT(*) FROM audit_logs) AS total_audit,
                (SELECT COUNT(*) FROM audit_logs WHERE created_at >= NOW() - INTERVAL 24 HOUR) AS audit_24h,
                (SELECT COUNT(*) FROM audit_logs WHERE action = 'LOCKOUT' AND created_at >= NOW() - INTERVAL 24 HOUR) AS lockouts_24h,
                (SELECT COUNT(*) FROM audit_logs WHERE action = 'PASSWORD_CHANGE' AND created_at >= NOW() - INTERVAL 7 DAY) AS pwd_changes_7d
        ");
        $logStats = $logStatsStmt->fetch() ?: [];

        $recentActivityStmt = $this->pdo->query("
            SELECT 
                a.id, a.action, a.entity_type, a.entity_id, a.description, a.ip_address, a.created_at,
                CONCAT(COALESCE(ui.first_name, ''), ' ', COALESCE(ui.last_name, '')) AS actor_name,
                ua.username AS actor_username
            FROM audit_logs a
            LEFT JOIN user_accounts ua ON ua.id = a.user_id
            LEFT JOIN user_info ui ON ui.user_account_id = ua.id
            ORDER BY a.created_at DESC
            LIMIT 8
        ");
        $recentActivity = $recentActivityStmt->fetchAll();

        $pageTitle = 'Super Admin Dashboard';
        $pageSubtitle = 'Master Control Panel';
        $accent = 'primary';
        $stats = [
            ['label' => 'Active Users', 'value' => $userCount, 'color' => 'blue', 'icon' => 'users'],
            ['label' => 'System Roles', 'value' => $roleCount, 'color' => 'indigo', 'icon' => 'shield'],
            ['label' => 'Recent Logins (24h)', 'value' => $loginCount24h, 'color' => 'emerald', 'icon' => 'activity'],
            ['label' => 'Locked Accounts', 'value' => $lockedCount, 'color' => 'rose', 'icon' => 'lock'],
        ];
        $logOverview = [
            ['label' => 'System Errors (24h)', 'value' => (int)($logStats['error_sys_24h'] ?? 0), 'color' => isset($logStats['error_sys_24h']) && (int)$logStats['error_sys_24h'] > 0 ? 'rose' : 'emerald'],
            ['label' => 'Audit Events (24h)', 'value' => (int)($logStats['audit_24h'] ?? 0), 'color' => 'sky'],
            ['label' => 'Account Lockouts (24h)', 'value' => (int)($logStats['lockouts_24h'] ?? 0), 'color' => (int)($logStats['lockouts_24h'] ?? 0) > 0 ? 'amber' : 'emerald'],
            ['label' => 'Password Changes (7d)', 'value' => (int)($logStats['pwd_changes_7d'] ?? 0), 'color' => 'violet'],
        ];
        $logTotals = [
            'system_logs' => (int)($logStats['total_sys'] ?? 0),
            'audit_logs' => (int)($logStats['total_audit'] ?? 0),
        ];

        require __DIR__ . '/../../../resources/views/master/dashboard.php';
    }
}

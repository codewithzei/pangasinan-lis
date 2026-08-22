<?php

require_once __DIR__ . '/../../config/database.php';

class SystemLogController
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
        $filterLevel = $_GET['level'] ?? '';
        $filterMethod = $_GET['method'] ?? '';
        $filterDateFrom = $_GET['date_from'] ?? '';
        $filterDateTo = $_GET['date_to'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 15;

        $whereClauses = ['1=1'];
        $params = [];

        if ($search !== '') {
            $whereClauses[] = '(sl.message LIKE ? OR sl.request_url LIKE ? OR sl.ip_address LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if ($filterLevel !== '') {
            $whereClauses[] = 'sl.log_level = ?';
            $params[] = $filterLevel;
        }

        if ($filterMethod !== '') {
            $whereClauses[] = 'sl.request_method = ?';
            $params[] = $filterMethod;
        }

        if ($filterDateFrom !== '') {
            $whereClauses[] = 'DATE(sl.created_at) >= ?';
            $params[] = $filterDateFrom;
        }

        if ($filterDateTo !== '') {
            $whereClauses[] = 'DATE(sl.created_at) <= ?';
            $params[] = $filterDateTo;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM system_logs sl WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT sl.*, ua.username as user_username, ui.first_name, ui.last_name
                FROM system_logs sl
                LEFT JOIN user_accounts ua ON sl.user_id = ua.id
                LEFT JOIN user_info ui ON sl.user_id = ui.user_account_id
                WHERE {$whereSql}
                ORDER BY sl.created_at DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll();

        $totalLogs = (int)$this->pdo->query("SELECT COUNT(*) FROM system_logs")->fetchColumn();
        $errorLogs = (int)$this->pdo->query("SELECT COUNT(*) FROM system_logs WHERE log_level IN ('ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY')")->fetchColumn();
        $warningLogs = (int)$this->pdo->query("SELECT COUNT(*) FROM system_logs WHERE log_level = 'WARNING'")->fetchColumn();
        $infoLogs = (int)$this->pdo->query("SELECT COUNT(*) FROM system_logs WHERE log_level = 'INFO'")->fetchColumn();

        $success = flash_get('success');
        $error = flash_get('error');

        $pageTitle = 'System Logs';
        $pageSubtitle = 'Monitor system events, errors, and request activity across the platform';
        $accent = 'purple';

        $viewDir = __DIR__ . '/../../../resources/views/master/system-logs';
        if (!is_dir($viewDir)) {
            mkdir($viewDir, 0777, true);
        }
        require $viewDir . '/index.php';
    }

    public function show(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid log ID.']);
            exit;
        }

        $stmt = $this->pdo->prepare("SELECT sl.*, ua.username as user_username, ui.first_name, ui.last_name
                                     FROM system_logs sl
                                     LEFT JOIN user_accounts ua ON sl.user_id = ua.id
                                     LEFT JOIN user_info ui ON sl.user_id = ui.user_account_id
                                     WHERE sl.id = ? LIMIT 1");
        $stmt->execute([$id]);
        $log = $stmt->fetch();

        if (!$log) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Log not found.']);
            exit;
        }

        if ($log['context']) {
            $decoded = json_decode($log['context'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $log['context'] = $decoded;
            }
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $log]);
        exit;
    }

    public function exportCsv(): void
    {
        $search = trim($_GET['search'] ?? '');
        $filterLevel = $_GET['level'] ?? '';
        $filterMethod = $_GET['method'] ?? '';
        $filterDateFrom = $_GET['date_from'] ?? '';
        $filterDateTo = $_GET['date_to'] ?? '';

        $whereClauses = ['1=1'];
        $params = [];

        if ($search !== '') {
            $whereClauses[] = '(sl.message LIKE ? OR sl.request_url LIKE ? OR sl.ip_address LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if ($filterLevel !== '') {
            $whereClauses[] = 'sl.log_level = ?';
            $params[] = $filterLevel;
        }

        if ($filterMethod !== '') {
            $whereClauses[] = 'sl.request_method = ?';
            $params[] = $filterMethod;
        }

        if ($filterDateFrom !== '') {
            $whereClauses[] = 'DATE(sl.created_at) >= ?';
            $params[] = $filterDateFrom;
        }

        if ($filterDateTo !== '') {
            $whereClauses[] = 'DATE(sl.created_at) <= ?';
            $params[] = $filterDateTo;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $sql = "SELECT sl.id, sl.log_level, sl.message, sl.context, sl.user_id, ui.first_name, ui.last_name,
                       sl.ip_address, sl.request_method, sl.request_url, sl.created_at
                FROM system_logs sl
                LEFT JOIN user_info ui ON sl.user_id = ui.user_account_id
                WHERE {$whereSql}
                ORDER BY sl.created_at DESC
                LIMIT 5000";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="system_logs_' . date('Ymd_His') . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Level', 'Message', 'User', 'IP Address', 'Method', 'URL', 'Timestamp']);

        foreach ($logs as $log) {
            $user = trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) ?: ($log['user_id'] ? 'User #' . $log['user_id'] : 'System');
            fputcsv($output, [
                $log['id'],
                $log['log_level'],
                strip_tags($log['message']),
                $user,
                $log['ip_address'],
                $log['request_method'],
                $log['request_url'],
                $log['created_at'],
            ]);
        }

        fclose($output);
        exit;
    }

    public function clearOld(): void
    {
        $days = (int)($_POST['days'] ?? 90);
        if ($days < 7) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Minimum retention is 7 days.']);
            exit;
        }

        try {
            $stmt = $this->pdo->prepare("DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $result = $stmt->execute([$days]);
            $deleted = $stmt->rowCount();

            header('Content-Type: application/json');
            if ($result) {
                echo json_encode(['success' => true, 'message' => "Purged {$deleted} system log entries older than {$days} days."]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to purge system logs.']);
            }
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }
}

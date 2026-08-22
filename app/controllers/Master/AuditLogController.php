<?php

require_once __DIR__ . '/../../config/database.php';

class AuditLogController
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
        $filterAction = $_GET['action'] ?? '';
        $filterEntity = $_GET['entity'] ?? '';
        $filterDateFrom = $_GET['date_from'] ?? '';
        $filterDateTo = $_GET['date_to'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 15;

        $whereClauses = ['1=1'];
        $params = [];

        if ($search !== '') {
            $whereClauses[] = '(al.description LIKE ? OR al.entity_type LIKE ? OR al.entity_id LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if ($filterAction !== '') {
            $whereClauses[] = 'al.action = ?';
            $params[] = $filterAction;
        }

        if ($filterEntity !== '') {
            $whereClauses[] = 'al.entity_type = ?';
            $params[] = $filterEntity;
        }

        if ($filterDateFrom !== '') {
            $whereClauses[] = 'DATE(al.created_at) >= ?';
            $params[] = $filterDateFrom;
        }

        if ($filterDateTo !== '') {
            $whereClauses[] = 'DATE(al.created_at) <= ?';
            $params[] = $filterDateTo;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM audit_logs al WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT al.*, ua.username as user_username, ui.first_name, ui.last_name
                FROM audit_logs al
                LEFT JOIN user_accounts ua ON al.user_id = ua.id
                LEFT JOIN user_info ui ON al.user_id = ui.user_account_id
                WHERE {$whereSql}
                ORDER BY al.created_at DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll();

        $totalLogs = (int)$this->pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
        $createLogs = (int)$this->pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'CREATE'")->fetchColumn();
        $updateLogs = (int)$this->pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'UPDATE'")->fetchColumn();
        $deleteLogs = (int)$this->pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'DELETE'")->fetchColumn();

        $stmtEntities = $this->pdo->query("SELECT DISTINCT entity_type FROM audit_logs WHERE entity_type IS NOT NULL AND entity_type != '' ORDER BY entity_type ASC LIMIT 50");
        $entityTypes = $stmtEntities->fetchAll(PDO::FETCH_COLUMN);

        $success = flash_get('success');
        $error = flash_get('error');

        $pageTitle = 'Audit Logs';
        $pageSubtitle = 'Track user actions, data changes, and accountability across the system';
        $accent = 'emerald';

        $viewDir = __DIR__ . '/../../../resources/views/master/audit-logs';
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

        $stmt = $this->pdo->prepare("SELECT al.*, ua.username as user_username, ui.first_name, ui.last_name
                                     FROM audit_logs al
                                     LEFT JOIN user_accounts ua ON al.user_id = ua.id
                                     LEFT JOIN user_info ui ON al.user_id = ui.user_account_id
                                     WHERE al.id = ? LIMIT 1");
        $stmt->execute([$id]);
        $log = $stmt->fetch();

        if (!$log) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Log not found.']);
            exit;
        }

        if ($log['old_values']) {
            $decoded = json_decode($log['old_values'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $log['old_values'] = $decoded;
            }
        }
        if ($log['new_values']) {
            $decoded = json_decode($log['new_values'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $log['new_values'] = $decoded;
            }
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $log]);
        exit;
    }

    public function exportCsv(): void
    {
        $search = trim($_GET['search'] ?? '');
        $filterAction = $_GET['action'] ?? '';
        $filterEntity = $_GET['entity'] ?? '';
        $filterDateFrom = $_GET['date_from'] ?? '';
        $filterDateTo = $_GET['date_to'] ?? '';

        $whereClauses = ['1=1'];
        $params = [];

        if ($search !== '') {
            $whereClauses[] = '(al.description LIKE ? OR al.entity_type LIKE ? OR al.entity_id LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if ($filterAction !== '') {
            $whereClauses[] = 'al.action = ?';
            $params[] = $filterAction;
        }

        if ($filterEntity !== '') {
            $whereClauses[] = 'al.entity_type = ?';
            $params[] = $filterEntity;
        }

        if ($filterDateFrom !== '') {
            $whereClauses[] = 'DATE(al.created_at) >= ?';
            $params[] = $filterDateFrom;
        }

        if ($filterDateTo !== '') {
            $whereClauses[] = 'DATE(al.created_at) <= ?';
            $params[] = $filterDateTo;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $sql = "SELECT al.id, al.action, al.entity_type, al.entity_id, al.description,
                       al.old_values, al.new_values, al.user_id, ui.first_name, ui.last_name,
                       al.ip_address, al.created_at
                FROM audit_logs al
                LEFT JOIN user_info ui ON al.user_id = ui.user_account_id
                WHERE {$whereSql}
                ORDER BY al.created_at DESC
                LIMIT 5000";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="audit_logs_' . date('Ymd_His') . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Action', 'Entity Type', 'Entity ID', 'Description', 'User', 'IP Address', 'Timestamp']);

        foreach ($logs as $log) {
            $user = trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) ?: ($log['user_id'] ? 'User #' . $log['user_id'] : 'System');
            fputcsv($output, [
                $log['id'],
                $log['action'],
                $log['entity_type'],
                $log['entity_id'],
                strip_tags($log['description']),
                $user,
                $log['ip_address'],
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
            $stmt = $this->pdo->prepare("DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $result = $stmt->execute([$days]);
            $deleted = $stmt->rowCount();

            header('Content-Type: application/json');
            if ($result) {
                echo json_encode(['success' => true, 'message' => "Purged {$deleted} audit log entries older than {$days} days."]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to purge audit logs.']);
            }
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }
}

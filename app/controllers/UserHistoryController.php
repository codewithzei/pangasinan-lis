<?php

require_once __DIR__ . '/../config/database.php';

class UserHistoryController
{
    private PDO $pdo;

    public function __construct()
    {
        $database = new Database();
        $this->pdo = $database->connect();
    }

    public function index(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            redirect('login');
        }

        $route = (string) ($GLOBALS['route'] ?? '');
        $historyRoutes = [
            'receiving/logs' => 'receiving/logs',
            'admin/logs' => 'admin/logs',
            'spsec/logs' => 'spsec/logs',
            'plenary/logs' => 'plenary/logs',
            'committee/logs' => 'committee/logs',
        ];
        if (!isset($historyRoutes[$route])) {
            redirect(dashboard_route_for_role(auth_role() ?? ''));
        }

        $historyRoute = $historyRoutes[$route];
        $search = trim((string) ($_GET['search'] ?? ''));
        $filterAction = strtoupper(trim((string) ($_GET['action'] ?? '')));
        $filterDateFrom = trim((string) ($_GET['date_from'] ?? ''));
        $filterDateTo = trim((string) ($_GET['date_to'] ?? ''));
        $filterError = null;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;

        if ($filterDateFrom !== '' && !$this->isValidDate($filterDateFrom)) {
            $filterDateFrom = '';
            $filterError = 'Enter a valid start date.';
        }
        if ($filterDateTo !== '' && !$this->isValidDate($filterDateTo)) {
            $filterDateTo = '';
            $filterError = 'Enter a valid end date.';
        }
        if ($filterDateFrom !== '' && $filterDateTo !== '' && $filterDateFrom > $filterDateTo) {
            $filterDateFrom = '';
            $filterDateTo = '';
            $filterError = 'The start date must be on or before the end date.';
        }

        $where = ['al.user_id = ?'];
        $params = [$userId];

        if ($search !== '') {
            $where[] = '(al.description LIKE ? OR al.entity_type LIKE ? OR al.entity_id LIKE ?)';
            $term = '%' . $search . '%';
            array_push($params, $term, $term, $term);
        }
        if ($filterAction !== '') {
            $where[] = 'al.action = ?';
            $params[] = $filterAction;
        }
        if ($filterDateFrom !== '') {
            $where[] = 'al.created_at >= ?';
            $params[] = $filterDateFrom . ' 00:00:00';
        }
        if ($filterDateTo !== '') {
            $where[] = 'al.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
            $params[] = $filterDateTo;
        }

        $whereSql = implode(' AND ', $where);
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM audit_logs al WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalRows = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($totalRows / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $logsStmt = $this->pdo->prepare("
            SELECT al.id, al.action, al.entity_type, al.entity_id, al.description, al.created_at
            FROM audit_logs al
            WHERE {$whereSql}
            ORDER BY al.created_at DESC, al.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $logsStmt->execute($params);
        $logs = $logsStmt->fetchAll();

        $relatedDocumentIds = [];
        $relatedUserIds = [];
        foreach ($logs as $log) {
            if (($log['entity_type'] ?? '') === 'Document' && ctype_digit((string) ($log['entity_id'] ?? ''))) {
                $relatedDocumentIds[] = (int) $log['entity_id'];
            }
            if (($log['entity_type'] ?? '') === 'User' && ctype_digit((string) ($log['entity_id'] ?? ''))) {
                $relatedUserIds[] = (int) $log['entity_id'];
            }
            if (preg_match('/\bDocument #(\d+)\b/', (string) ($log['description'] ?? ''), $matches)) {
                $relatedDocumentIds[] = (int) $matches[1];
            }
        }

        $relatedDocuments = [];
        $relatedDocumentIds = array_values(array_unique($relatedDocumentIds));
        if ($relatedDocumentIds !== []) {
            $placeholders = implode(',', array_fill(0, count($relatedDocumentIds), '?'));
            $documentsStmt = $this->pdo->prepare("
                SELECT id, tracking_number, subject_matter
                FROM documents
                WHERE id IN ({$placeholders})
            ");
            $documentsStmt->execute($relatedDocumentIds);
            foreach ($documentsStmt->fetchAll() as $document) {
                $relatedDocuments[(int) $document['id']] = $document;
            }
        }

        $relatedUsers = [];
        $relatedUserIds = array_values(array_unique($relatedUserIds));
        if ($relatedUserIds !== []) {
            $placeholders = implode(',', array_fill(0, count($relatedUserIds), '?'));
            $usersStmt = $this->pdo->prepare("
                SELECT ua.id, ua.username, ui.first_name, ui.last_name
                FROM user_accounts ua
                LEFT JOIN user_info ui ON ui.user_account_id = ua.id
                WHERE ua.id IN ({$placeholders})
            ");
            $usersStmt->execute($relatedUserIds);
            foreach ($usersStmt->fetchAll() as $user) {
                $relatedUsers[(int) $user['id']] = $user;
            }
        }

        foreach ($logs as &$log) {
            $documentId = null;
            if (($log['entity_type'] ?? '') === 'Document' && ctype_digit((string) ($log['entity_id'] ?? ''))) {
                $documentId = (int) $log['entity_id'];
            } elseif (preg_match('/\bDocument #(\d+)\b/', (string) ($log['description'] ?? ''), $matches)) {
                $documentId = (int) $matches[1];
            }
            $log['related_document'] = $documentId !== null ? ($relatedDocuments[$documentId] ?? null) : null;
            $relatedUserId = ($log['entity_type'] ?? '') === 'User' && ctype_digit((string) ($log['entity_id'] ?? ''))
                ? (int) $log['entity_id']
                : null;
            $log['related_user'] = $relatedUserId !== null ? ($relatedUsers[$relatedUserId] ?? null) : null;
            $log['related_file_name'] = null;
            if (($log['entity_type'] ?? '') === 'File'
                && preg_match('/^File (?:uploaded|downloaded):\s*(.+)$/', (string) ($log['description'] ?? ''), $matches)) {
                $log['related_file_name'] = preg_replace('/\s+\([A-Za-z][A-Za-z0-9_]* #\d+\)$/', '', $matches[1]);
            }
        }
        unset($log);

        $statsStmt = $this->pdo->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(action = 'CREATE') AS created,
                SUM(action = 'UPDATE') AS updated,
                (
                    SELECT COUNT(*)
                    FROM document_routes dr
                    WHERE dr.routed_by = ?
                ) AS routed
            FROM audit_logs
            WHERE user_id = ?
        ");
        $statsStmt->execute([$userId, $userId]);
        $stats = $statsStmt->fetch() ?: [];

        $actionsStmt = $this->pdo->prepare("
            SELECT DISTINCT action
            FROM audit_logs
            WHERE user_id = ?
            ORDER BY action
        ");
        $actionsStmt->execute([$userId]);
        $actions = $actionsStmt->fetchAll(PDO::FETCH_COLUMN);

        $pageTitle = 'My Logs & History';
        $pageSubtitle = 'Review the activity recorded for your account.';
        require __DIR__ . '/../../resources/views/user/history/index.php';
    }

    private function isValidDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();

        return $parsed !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $parsed->format('Y-m-d') === $date;
    }
}

<?php

require_once __DIR__ . '/../../config/database.php';

class ReceivingDashboardController
{
    protected PDO $pdo;

    public function __construct()
    {
        $database = new Database();
        $this->pdo = $database->connect();
    }

    public function index(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        $roleStmt = $this->pdo->query("
            SELECT id
            FROM roles
            WHERE name = 'Receiving Staff'
              AND is_active = 1
              AND is_deleted = 0
            LIMIT 1
        ");
        $receivingRole = $roleStmt->fetch();
        if (!$receivingRole) {
            throw new RuntimeException('Receiving Staff role is not configured.');
        }
        $receivingRoleId = (int) $receivingRole['id'];

        $statsStmt = $this->pdo->prepare("
            SELECT
                COUNT(CASE
                    WHEN d.created_at >= CURDATE()
                     AND d.created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                    THEN 1
                END) AS received_today,
                COUNT(CASE
                    WHEN d.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                     AND d.created_at < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
                    THEN 1
                END) AS received_this_month,
                COUNT(CASE
                    WHEN d.current_phase = 'ADMIN'
                     AND EXISTS (
                         SELECT 1
                         FROM document_assignments da_admin
                         WHERE da_admin.document_id = d.id
                           AND da_admin.phase = 'ADMIN'
                           AND da_admin.decision = 'PENDING'
                           AND da_admin.completed_at IS NULL
                     )
                    THEN 1
                END) AS awaiting_admin
            FROM documents d
            WHERE d.created_by = ?
        ");
        $statsStmt->execute([$userId]);
        $stats = $statsStmt->fetch() ?: [];

        $returnedStmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT da.document_id)
            FROM document_assignments da
            WHERE da.assigned_to_role_id = ?
              AND da.phase = 'RECEIVING'
              AND da.decision = 'PENDING'
              AND da.completed_at IS NULL
              AND EXISTS (
                  SELECT 1
                  FROM document_assignments da_admin
                  WHERE da_admin.document_id = da.document_id
                    AND da_admin.phase = 'ADMIN'
                    AND da_admin.decision = 'DECLINED'
                    AND da_admin.declined_at IS NOT NULL
              )
        ");
        $returnedStmt->execute([$receivingRoleId]);
        $returnedCount = (int) $returnedStmt->fetchColumn();

        $monthStmt = $this->pdo->prepare("
            SELECT DATE_FORMAT(d.created_at, '%Y-%m') AS month_key, COUNT(*) AS document_count
            FROM documents d
            WHERE d.created_by = ?
              AND d.created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
              AND d.created_at < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
            GROUP BY DATE_FORMAT(d.created_at, '%Y-%m')
            ORDER BY month_key ASC
        ");
        $monthStmt->execute([$userId]);
        $monthlyCounts = [];
        foreach ($monthStmt->fetchAll() as $row) {
            $monthlyCounts[$row['month_key']] = (int) $row['document_count'];
        }

        $monthTrend = [];
        $monthStart = new DateTimeImmutable('first day of this month');
        for ($offset = 5; $offset >= 0; $offset--) {
            $month = $monthStart->modify("-{$offset} months");
            $monthKey = $month->format('Y-m');
            $monthTrend[] = [
                'label' => $month->format('M'),
                'count' => $monthlyCounts[$monthKey] ?? 0,
            ];
        }

        $recentStmt = $this->pdo->prepare("
            SELECT
                d.id,
                d.tracking_number,
                d.subject_matter,
                d.date_received,
                d.created_at,
                d.current_phase,
                dt.name AS document_type_name,
                ds.name AS status
            FROM documents d
            LEFT JOIN document_types dt ON dt.id = d.document_type_id
            LEFT JOIN document_statuses ds ON ds.id = d.current_status_id
            WHERE d.created_by = ?
            ORDER BY d.created_at DESC, d.id DESC
            LIMIT 5
        ");
        $recentStmt->execute([$userId]);
        $recentDocuments = $recentStmt->fetchAll();

        $stats = [
            'received_today' => (int) ($stats['received_today'] ?? 0),
            'received_this_month' => (int) ($stats['received_this_month'] ?? 0),
            'awaiting_admin' => (int) ($stats['awaiting_admin'] ?? 0),
            'returned_to_receiving' => $returnedCount,
        ];
        $user = auth();
        $userName = $user['full_name'] ?? 'Receiving Staff';
        $pageTitle = 'Receiving Dashboard';

        require __DIR__ . '/../../../resources/views/receiving/dashboard.php';
    }
}

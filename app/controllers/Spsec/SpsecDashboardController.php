<?php

require_once __DIR__ . '/../../config/database.php';

class SpsecDashboardController
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

        $roleStmt = $this->pdo->query(
            "SELECT id FROM roles WHERE name = 'SP Secretary' AND is_active = 1 AND is_deleted = 0 LIMIT 1"
        );
        $role = $roleStmt->fetch();
        if (!$role) {
            flash_set('error', 'SP Secretary role not found. Please contact the system administrator.');
            redirect('dashboard');
        }
        $roleId = (int) $role['id'];

        $termStmt = $this->pdo->query("
            SELECT id, name, session_number, year, start_date, end_date, description
            FROM terms
            WHERE is_deleted = 0 AND is_active = 1
            ORDER BY start_date DESC
            LIMIT 1
        ");
        $currentTerm = $termStmt->fetch() ?: null;

        $statsStmt = $this->pdo->prepare("
            SELECT
                COUNT(DISTINCT CASE
                    WHEN da.decision = 'PENDING'
                     AND da.completed_at IS NULL
                     AND da.accepted_by IS NULL
                     AND (da.assigned_to_user_id IS NULL OR da.assigned_to_user_id = ?)
                    THEN da.document_id
                END) AS pending_count,
                COUNT(DISTINCT CASE
                    WHEN da.decision = 'ACCEPTED'
                     AND (da.accepted_by = ? OR da.assigned_to_user_id = ?)
                    THEN da.document_id
                END) AS accepted_count
            FROM document_assignments da
            WHERE da.assigned_to_role_id = ?
              AND da.phase = 'SP_SECRETARY'
        ");
        $statsStmt->execute([$userId, $userId, $userId, $roleId]);
        $stats = $statsStmt->fetch() ?: [];

        $routingStmt = $this->pdo->query("
            SELECT
                COUNT(DISTINCT CASE WHEN to_phase IN ('PLENARY', 'COMMITTEE') THEN document_id END) AS total_routed,
                COUNT(DISTINCT CASE WHEN to_phase = 'PLENARY' THEN document_id END) AS plenary_count,
                COUNT(DISTINCT CASE WHEN to_phase = 'COMMITTEE' THEN document_id END) AS committee_count,
                COUNT(DISTINCT CASE
                    WHEN to_phase IN ('PLENARY', 'COMMITTEE')
                     AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                     AND created_at < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
                    THEN document_id
                END) AS routed_this_month
            FROM document_routes
            WHERE from_phase = 'SP_SECRETARY'
        ");
        $routingStats = $routingStmt->fetch() ?: [];

        $recentStmt = $this->pdo->prepare("
            SELECT
                d.id,
                d.tracking_number,
                d.subject_matter,
                da.received_at,
                dt.name AS document_type_name,
                ds.name AS status
            FROM document_assignments da
            INNER JOIN documents d ON d.id = da.document_id
            LEFT JOIN document_types dt ON dt.id = d.document_type_id
            LEFT JOIN document_statuses ds ON ds.id = d.current_status_id
            WHERE da.assigned_to_role_id = ?
              AND da.phase = 'SP_SECRETARY'
              AND da.decision = 'PENDING'
              AND da.completed_at IS NULL
              AND da.accepted_by IS NULL
              AND (da.assigned_to_user_id IS NULL OR da.assigned_to_user_id = ?)
            ORDER BY da.received_at ASC, d.id ASC
            LIMIT 5
        ");
        $recentStmt->execute([$roleId, $userId]);
        $recentQueue = $recentStmt->fetchAll();

        $pageTitle = 'SP Secretary Dashboard';
        require __DIR__ . '/../../../resources/views/spsec/dashboard.php';
    }
}

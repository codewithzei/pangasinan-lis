<?php

require_once __DIR__ . '/../../config/database.php';

/**
 * AdminDashboardController
 *
 * Renders the Admin / Routing dashboard with live, user-scoped statistics.
 *
 * Counts respect ownership:
 *   - pending_count  = unclaimed docs for the Admin role that this user has
 *                      not yet been beaten to + docs pre-assigned to this user.
 *   - accepted_count = docs accepted by or assigned to THIS user only.
 *                      Never counts another Admin's claimed documents.
 *   - routed_today   = documents THIS user completed/routed today.
 */
class AdminDashboardController
{
    protected PDO $pdo;

    public function __construct()
    {
        $database  = new Database();
        $this->pdo = $database->connect();
    }

    public function index(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        // Resolve Admin role ID
        $roleStmt = $this->pdo->query(
            "SELECT id FROM roles WHERE name = 'Admin' AND is_active = 1 AND is_deleted = 0 LIMIT 1"
        );
        $adminRole = $roleStmt->fetch();
        if (!$adminRole) {
            // Graceful degradation — show zeros
            $stats = $this->emptyStats();
            $recentQueue = [];
            $this->render($stats, $recentQueue);
            return;
        }
        $adminRoleId = (int) $adminRole['id'];

        // ── User-scoped statistics ────────────────────────────────────────────
        //
        // pending_count:
        //   PENDING, unclaimed (accepted_by IS NULL), visible to this user
        //   (assigned_to_user_id IS NULL OR = currentUser)
        //
        // accepted_count (my in-progress):
        //   ACCEPTED + not completed + owned by me
        //
        // routed_today:
        //   Assignments THIS user completed today (COMPLETED/NOTED/DECLINED)
        //   These represent documents the user actively processed.
        //
        // All counts are scoped to phase = 'ADMIN'.
        // ─────────────────────────────────────────────────────────────────────
        $statsStmt = $this->pdo->prepare("
            SELECT
                COUNT(DISTINCT CASE
                    WHEN da.decision      = 'PENDING'
                     AND da.completed_at IS NULL
                     AND da.accepted_by  IS NULL
                     AND (da.assigned_to_user_id IS NULL OR da.assigned_to_user_id = ?)
                    THEN da.document_id
                END) AS pending_count,

                COUNT(DISTINCT CASE
                    WHEN da.decision      = 'ACCEPTED'
                     AND da.completed_at IS NULL
                     AND (da.accepted_by = ? OR da.assigned_to_user_id = ?)
                    THEN da.document_id
                END) AS accepted_count,

                COUNT(DISTINCT CASE
                    WHEN da.decision      IN ('COMPLETED', 'NOTED', 'DECLINED')
                     AND da.completed_at >= CURDATE()
                     AND (da.accepted_by = ? OR da.assigned_to_user_id = ?)
                    THEN da.document_id
                END) AS routed_today,

                COUNT(DISTINCT CASE
                    WHEN da.decision     IN ('COMPLETED', 'NOTED')
                     AND (da.accepted_by = ? OR da.assigned_to_user_id = ?)
                    THEN da.document_id
                END) AS total_processed
            FROM document_assignments da
            WHERE da.assigned_to_role_id = ?
              AND da.phase               = 'ADMIN'
        ");
        $statsStmt->execute([
            $userId,        // pending unclaimed filter
            $userId, $userId,  // accepted_count
            $userId, $userId,  // routed_today
            $userId, $userId,  // total_processed
            $adminRoleId,
        ]);
        $counts = $statsStmt->fetch() ?: [];

        $stats = [
            'pending_count'   => (int) ($counts['pending_count']   ?? 0),
            'accepted_count'  => (int) ($counts['accepted_count']  ?? 0),
            'routed_today'    => (int) ($counts['routed_today']    ?? 0),
            'total_processed' => (int) ($counts['total_processed'] ?? 0),
        ];

        // ── Recent pending queue (up to 5 most recent for the user) ───────────
        $queueStmt = $this->pdo->prepare("
            SELECT
                d.id               AS document_id,
                d.tracking_number,
                d.subject_matter,
                d.date_received,
                da.received_at,
                dt.name            AS document_type_name,
                dt.badge_color     AS document_type_badge_color,
                ds.name            AS status,
                ds.badge_color     AS status_badge_color
            FROM document_assignments da
            INNER JOIN documents         d  ON da.document_id      = d.id
            LEFT  JOIN document_types    dt ON d.document_type_id  = dt.id
            LEFT  JOIN document_statuses ds ON d.current_status_id = ds.id
            WHERE da.assigned_to_role_id = ?
              AND da.phase               = 'ADMIN'
              AND da.decision            = 'PENDING'
              AND da.completed_at        IS NULL
              AND da.accepted_by         IS NULL
              AND (da.assigned_to_user_id IS NULL OR da.assigned_to_user_id = ?)
            ORDER BY da.received_at ASC
            LIMIT 5
        ");
        $queueStmt->execute([$adminRoleId, $userId]);
        $recentQueue = $queueStmt->fetchAll();

        $pageTitle = 'Admin / Routing Dashboard';
        $this->render($stats, $recentQueue);
    }

    private function emptyStats(): array
    {
        return [
            'pending_count'   => 0,
            'accepted_count'  => 0,
            'routed_today'    => 0,
            'total_processed' => 0,
        ];
    }

    private function render(array $stats, array $recentQueue): void
    {
        require __DIR__ . '/../../../resources/views/admin/dashboard.php';
    }
}

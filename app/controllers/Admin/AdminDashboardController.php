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
            $this->render($stats, $recentQueue, null, $this->emptyLegislativeOverview());
            return;
        }
        $adminRoleId = (int) $adminRole['id'];

        $termStmt = $this->pdo->query("
            SELECT
                t.id,
                t.name,
                t.session_number,
                t.year,
                t.start_date,
                t.end_date,
                t.description,
                COUNT(tl.id) AS member_count,
                (
                    SELECT COUNT(*)
                    FROM committees c
                    WHERE c.is_active = 1
                      AND c.is_deleted = 0
                ) AS committee_count
            FROM terms t
            LEFT JOIN term_legislators tl ON tl.term_id = t.id
            WHERE t.is_deleted = 0
              AND t.is_active = 1
            GROUP BY t.id, t.name, t.session_number, t.year, t.start_date, t.end_date, t.description
            ORDER BY t.start_date DESC
            LIMIT 1
        ");
        $currentTerm = $termStmt->fetch() ?: null;

        $overviewStmt = $this->pdo->query("
            SELECT
                COUNT(*) AS total_documents,
                SUM(CASE WHEN current_phase NOT IN ('FINALIZED', 'FILED') THEN 1 ELSE 0 END) AS in_workflow,
                SUM(CASE WHEN current_phase IN ('FINALIZED', 'FILED') THEN 1 ELSE 0 END) AS completed_documents,
                SUM(CASE
                    WHEN date_received >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                     AND date_received < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
                    THEN 1 ELSE 0
                END) AS received_this_month
            FROM documents
        ");
        $overviewCounts = $overviewStmt->fetch() ?: [];

        $monthStmt = $this->pdo->query("
            SELECT DATE_FORMAT(date_received, '%Y-%m') AS month_key, COUNT(*) AS document_count
            FROM documents
            WHERE date_received >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
              AND date_received < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
            GROUP BY DATE_FORMAT(date_received, '%Y-%m')
            ORDER BY month_key ASC
        ");
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

        $phaseLabels = [
            'RECEIVING' => 'Receiving',
            'ADMIN' => 'Administration',
            'SP_SECRETARY' => 'SP Secretary',
            'PLENARY' => 'Plenary',
            'COMMITTEE' => 'Committees',
            'FINALIZED' => 'Finalized',
            'FILED' => 'Filed',
        ];
        $phaseStmt = $this->pdo->query("
            SELECT current_phase, COUNT(*) AS document_count
            FROM documents
            GROUP BY current_phase
        ");
        $phaseCounts = [];
        foreach ($phaseStmt->fetchAll() as $row) {
            $phaseCounts[$row['current_phase']] = (int) $row['document_count'];
        }

        $phaseBreakdown = [];
        foreach ($phaseLabels as $phase => $label) {
            $phaseBreakdown[] = [
                'label' => $label,
                'count' => $phaseCounts[$phase] ?? 0,
            ];
        }

        $legislativeOverview = [
            'total_documents' => (int) ($overviewCounts['total_documents'] ?? 0),
            'in_workflow' => (int) ($overviewCounts['in_workflow'] ?? 0),
            'completed_documents' => (int) ($overviewCounts['completed_documents'] ?? 0),
            'received_this_month' => (int) ($overviewCounts['received_this_month'] ?? 0),
            'month_trend' => $monthTrend,
            'phase_breakdown' => $phaseBreakdown,
        ];

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
        $this->render($stats, $recentQueue, $currentTerm, $legislativeOverview);
    }

    private function emptyLegislativeOverview(): array
    {
        return [
            'total_documents' => 0,
            'in_workflow' => 0,
            'completed_documents' => 0,
            'received_this_month' => 0,
            'month_trend' => [],
            'phase_breakdown' => [],
        ];
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

    private function render(array $stats, array $recentQueue, ?array $currentTerm, array $legislativeOverview): void
    {
        require __DIR__ . '/../../../resources/views/admin/dashboard.php';
    }
}

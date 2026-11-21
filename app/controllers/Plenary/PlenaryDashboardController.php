<?php

require_once __DIR__ . '/../../config/database.php';

class PlenaryDashboardController
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
            "SELECT id FROM roles WHERE name = 'Plenary' AND is_active = 1 AND is_deleted = 0 LIMIT 1"
        );
        $role = $roleStmt->fetch();
        if (!$role) {
            flash_set('error', 'Plenary role not found. Please contact the system administrator.');
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

        $statusStmt = $this->pdo->prepare("
            SELECT id, name
            FROM document_statuses
            WHERE name IN ('Awaiting First Reading', 'Awaiting Second Reading', 'Second Reading Approved')
              AND is_active = 1
              AND is_deleted = 0
        ");
        $statusStmt->execute();
        $statusIds = [];
        foreach ($statusStmt->fetchAll() as $row) {
            $statusIds[$row['name']] = (int) $row['id'];
        }

        $inboxStmt = $this->pdo->prepare("
            SELECT
                COUNT(DISTINCT CASE
                    WHEN da.decision = 'PENDING'
                     AND da.completed_at IS NULL
                     AND da.accepted_by IS NULL
                     AND (da.assigned_to_user_id IS NULL OR da.assigned_to_user_id = ?)
                     AND d.current_phase = 'PLENARY'
                     AND d.finalized_at IS NULL
                     AND d.current_status_id NOT IN (
                         SELECT id FROM document_statuses
                         WHERE name IN ('Approved', 'Plenary Comm: Finalized', 'Plenary Cases: Finalized')
                           AND is_active = 1
                           AND is_deleted = 0
                     )
                     AND NOT EXISTS (
                         SELECT 1
                         FROM document_assignments newer_da
                         WHERE newer_da.document_id = da.document_id
                           AND newer_da.id > da.id
                     )
                    THEN da.document_id
                END) AS pending_count,
                COUNT(DISTINCT CASE
                    WHEN da.decision = 'ACCEPTED'
                     AND da.completed_at IS NULL
                     AND (da.accepted_by = ? OR da.assigned_to_user_id = ?)
                     AND d.current_phase = 'PLENARY'
                     AND d.finalized_at IS NULL
                     AND d.current_status_id NOT IN (
                         SELECT id FROM document_statuses
                         WHERE name IN (
                             'Awaiting First Reading',
                             'Returned to Plenary',
                             'Approved',
                             'Plenary Comm: Finalized',
                             'Plenary Cases: Finalized',
                             'Plenary: Committee Report Adopted'
                         )
                           AND is_active = 1
                           AND is_deleted = 0
                     )
                     AND NOT EXISTS (
                         SELECT 1 FROM plenary_readings pr
                         WHERE pr.document_id = da.document_id
                     )
                     AND (
                         pdd.status_id IS NULL
                         OR pdd.status_id = (
                             SELECT id FROM document_statuses
                             WHERE name = 'For Encoding'
                               AND is_active = 1
                               AND is_deleted = 0
                             LIMIT 1
                         )
                     )
                    THEN da.document_id
                END) AS accepted_count
            FROM document_assignments da
            INNER JOIN documents d ON d.id = da.document_id
            LEFT JOIN plenary_document_details pdd ON pdd.document_id = d.id
            WHERE da.assigned_to_role_id = ?
              AND da.phase = 'PLENARY'
        ");
        $inboxStmt->execute([$userId, $userId, $userId, $roleId]);
        $inboxStats = $inboxStmt->fetch() ?: [];

        $firstReadingCount = 0;
        if (isset($statusIds['Awaiting First Reading'])) {
            $firstReadingStmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM documents d
                WHERE d.current_status_id = ?
                  AND NOT EXISTS (
                      SELECT 1 FROM plenary_readings pr
                      WHERE pr.document_id = d.id AND pr.reading_number = 1
                  )
                  AND NOT EXISTS (
                      SELECT 1 FROM plenary_comm_first_reading pcfr
                      WHERE pcfr.document_id = d.id
                  )
                  AND NOT EXISTS (
                      SELECT 1 FROM plenary_cases_first_reading pcfr2
                      WHERE pcfr2.document_id = d.id
                  )
            ");
            $firstReadingStmt->execute([$statusIds['Awaiting First Reading']]);
            $firstReadingCount = (int) $firstReadingStmt->fetchColumn();
        }

        $secondReadingCount = 0;
        if (isset($statusIds['Awaiting Second Reading'])) {
            $secondReadingStmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM documents
                WHERE current_status_id = ?
            ");
            $secondReadingStmt->execute([$statusIds['Awaiting Second Reading']]);
            $secondReadingCount = (int) $secondReadingStmt->fetchColumn();
        }

        $approvedCount = 0;
        if (isset($statusIds['Second Reading Approved'])) {
            $approvedStmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM documents
                WHERE current_status_id = ?
            ");
            $approvedStmt->execute([$statusIds['Second Reading Approved']]);
            $approvedCount = (int) $approvedStmt->fetchColumn();
        }

        $recentStmt = $this->pdo->prepare("
            SELECT
                d.id,
                d.tracking_number,
                d.subject_matter,
                d.date_received,
                dt.name AS document_type_name,
                ds.name AS status
            FROM document_assignments da
            INNER JOIN documents d ON d.id = da.document_id
            LEFT JOIN document_types dt ON dt.id = d.document_type_id
            LEFT JOIN document_statuses ds ON ds.id = d.current_status_id
            WHERE da.assigned_to_role_id = ?
              AND da.phase = 'PLENARY'
              AND da.decision = 'PENDING'
              AND da.completed_at IS NULL
              AND da.accepted_by IS NULL
              AND (da.assigned_to_user_id IS NULL OR da.assigned_to_user_id = ?)
            ORDER BY da.id DESC
            LIMIT 5
        ");
        $recentStmt->execute([$roleId, $userId]);
        $recentQueue = $recentStmt->fetchAll();

        $stats = [
            'pending_count' => (int) ($inboxStats['pending_count'] ?? 0),
            'accepted_count' => (int) ($inboxStats['accepted_count'] ?? 0),
            'first_reading_count' => $firstReadingCount,
            'second_reading_count' => $secondReadingCount,
            'approved_count' => $approvedCount,
        ];

        $pageTitle = 'Plenary Dashboard';
        require __DIR__ . '/../../../resources/views/plenary/dashboard.php';
    }
}

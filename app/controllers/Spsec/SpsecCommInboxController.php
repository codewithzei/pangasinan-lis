<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/DocumentService.php';

/**
 * SpsecCommInboxController
 *
 * Handles the SP Secretary Communications Inbox — the list of Communication
 * documents that have been received into the SP Secretary module (via spsec
 * inbox / direct intake) and are waiting to be processed through the
 * Communications Process Flow.
 *
 * ROUTE MAP
 * ─────────────────────────────────────────────────────────────────────────────
 *  GET  spsec/comm-inbox               index()          — list communications
 *  GET  spsec/comm-inbox/show          show()           — detail view
 *  POST spsec/comm-inbox/send-to-fr    sendToFirstReading() — send to First Reading
 *
 * VISIBILITY RULES
 * ─────────────────────────────────────────────────────────────────────────────
 *  Shows Communication-type documents that:
 *   • Have a document_assignment for the SP_SECRETARY phase (ACCEPTED),
 *     OR have already been through the comm workflow (have a comm_inbox_item).
 *   • Document type name must contain "Communication".
 *   • Excludes documents already finalized (Comm: Finalized status).
 *
 *  The inbox shows only: communication_date (date_received) and subject_matter.
 *  Author information is NOT displayed, per requirements.
 *
 * AUTHORIZATION
 * ─────────────────────────────────────────────────────────────────────────────
 *  All routes: AuthMiddleware + RoleMiddleware (SP Secretary / Super Admin).
 */
class SpsecCommInboxController
{
    protected PDO             $pdo;
    protected DocumentService $docService;

    private const PER_PAGE = 20;

    public function __construct()
    {
        $db               = new Database();
        $this->pdo        = $db->connect();
        $this->docService = new DocumentService();
    }

    // =========================================================================
    // 1. Index
    // =========================================================================

    public function index(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        $this->requireSpsecAccess();

        $search   = trim($_GET['search']   ?? '');
        $tab      = trim($_GET['tab']      ?? 'pending');
        if (!in_array($tab, ['pending', 'sent'], true)) {
            $tab = 'pending';
        }

        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $offset = ($page - 1) * self::PER_PAGE;

        // Build WHERE
        [$where, $params] = $this->buildListWhere($search, $tab);

        $countSql = "
            SELECT COUNT(DISTINCT d.id)
            FROM documents d
            INNER JOIN document_types dt ON dt.id = d.document_type_id
                AND dt.name LIKE '%Communication%'
                AND dt.is_deleted = 0
            LEFT JOIN comm_inbox_items cii ON cii.document_id = d.id
            WHERE {$where}
        ";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total      = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page       = min($page, $totalPages);

        $listSql = "
            SELECT
                d.id,
                d.tracking_number,
                d.subject_matter,
                d.date_received,
                d.current_status_id,
                ds.name            AS status,
                ds.badge_color     AS status_badge_color,
                dt.name            AS document_type_name,
                dt.badge_color     AS document_type_badge_color,
                cii.id             AS inbox_item_id,
                cii.sent_to_fr_at
            FROM documents d
            INNER JOIN document_types dt ON dt.id = d.document_type_id
                AND dt.name LIKE '%Communication%'
                AND dt.is_deleted = 0
            LEFT  JOIN document_statuses ds  ON ds.id = d.current_status_id
            LEFT  JOIN comm_inbox_items  cii ON cii.document_id = d.id
            WHERE {$where}
            ORDER BY d.date_received DESC, d.id DESC
            LIMIT ? OFFSET ?
        ";
        $listStmt = $this->pdo->prepare($listSql);
        $listStmt->execute([...$params, self::PER_PAGE, $offset]);
        $documents = $listStmt->fetchAll();

        // Tab counts
        [$wPending, $pPending] = $this->buildListWhere('', 'pending');
        [$wSent,    $pSent]    = $this->buildListWhere('', 'sent');

        $pendingCount = (int) $this->pdo->prepare("
            SELECT COUNT(DISTINCT d.id) FROM documents d
            INNER JOIN document_types dt ON dt.id = d.document_type_id AND dt.name LIKE '%Communication%' AND dt.is_deleted = 0
            LEFT JOIN comm_inbox_items cii ON cii.document_id = d.id
            WHERE {$wPending}
        ")->execute($pPending) ? $this->pdo->query("
            SELECT COUNT(DISTINCT d.id) FROM documents d
            INNER JOIN document_types dt ON dt.id = d.document_type_id AND dt.name LIKE '%Communication%' AND dt.is_deleted = 0
            LEFT JOIN comm_inbox_items cii ON cii.document_id = d.id
            WHERE {$wPending}
        ")->fetchColumn() : 0;

        // Re-execute properly
        $cntPending = $this->pdo->prepare("
            SELECT COUNT(DISTINCT d.id) FROM documents d
            INNER JOIN document_types dt ON dt.id = d.document_type_id AND dt.name LIKE '%Communication%' AND dt.is_deleted = 0
            LEFT JOIN comm_inbox_items cii ON cii.document_id = d.id
            WHERE {$wPending}
        ");
        $cntPending->execute($pPending);
        $pendingCount = (int) $cntPending->fetchColumn();

        $cntSent = $this->pdo->prepare("
            SELECT COUNT(DISTINCT d.id) FROM documents d
            INNER JOIN document_types dt ON dt.id = d.document_type_id AND dt.name LIKE '%Communication%' AND dt.is_deleted = 0
            LEFT JOIN comm_inbox_items cii ON cii.document_id = d.id
            WHERE {$wSent}
        ");
        $cntSent->execute($pSent);
        $sentCount = (int) $cntSent->fetchColumn();

        $pageTitle = 'Communications Inbox';
        $success   = flash_get('success');
        $error     = flash_get('error');

        require __DIR__ . '/../../../resources/views/spsec/comm-inbox/index.php';
    }

    // =========================================================================
    // 2. Show
    // =========================================================================

    public function show(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        $this->requireSpsecAccess();

        $documentId = (int) ($_GET['id'] ?? 0);
        if ($documentId <= 0) {
            flash_set('error', 'Invalid document ID.');
            redirect('spsec/comm-inbox');
        }

        $document = $this->fetchCommDocument($documentId);
        if (!$document) {
            flash_set('error', 'Communication document not found.');
            redirect('spsec/comm-inbox');
        }

        $inboxItem    = $this->fetchInboxItem($documentId);
        $firstReading = null;
        $committees   = [];
        $resFiles     = [];

        if ($inboxItem) {
            $firstReading = $this->fetchFirstReading($documentId);
            if ($firstReading) {
                $committees = $this->fetchCommitteeAssignments((int) $firstReading['id']);
                $resFiles   = $this->fetchResolutionFiles(null, (int) $firstReading['id']);
            }
        }

        // Route history (for context)
        $routes = $this->fetchRoutes($documentId);

        $pageTitle = 'Communication — ' . htmlspecialchars($document['tracking_number'] ?? '');
        $success   = flash_get('success');
        $error     = flash_get('error');

        require __DIR__ . '/../../../resources/views/spsec/comm-inbox/show.php';
    }

    // =========================================================================
    // 3. Send to First Reading (POST)
    // =========================================================================

    public function sendToFirstReading(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        $this->requireSpsecAccess();

        $documentId = (int) ($_POST['document_id'] ?? 0);
        if ($documentId <= 0) {
            flash_set('error', 'Invalid document ID.');
            redirect('spsec/comm-inbox');
        }

        $document = $this->fetchCommDocument($documentId);
        if (!$document) {
            flash_set('error', 'Communication document not found.');
            redirect('spsec/comm-inbox');
        }

        // Guard: already sent to First Reading?
        $existing = $this->fetchInboxItem($documentId);
        if ($existing && !empty($existing['sent_to_fr_at'])) {
            flash_set('error', 'This communication has already been sent to First Reading.');
            redirect('spsec/comm-inbox/show?id=' . $documentId);
        }

        // Resolve target status
        $targetStatus = $this->requireStatus('Comm: Awaiting First Reading');
        $prevStatusId = (int) ($document['current_status_id'] ?? 0);

        try {
            $this->pdo->beginTransaction();

            // Upsert comm_inbox_item
            $this->pdo->prepare("
                INSERT INTO comm_inbox_items (document_id, sent_to_fr_by, sent_to_fr_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    sent_to_fr_by = VALUES(sent_to_fr_by),
                    sent_to_fr_at = VALUES(sent_to_fr_at)
            ")->execute([$documentId, $userId]);

            // Update document status
            $this->pdo->prepare("
                UPDATE documents SET current_status_id = ?, updated_by = ? WHERE id = ?
            ")->execute([$targetStatus['id'], $userId, $documentId]);

            // Document event
            $this->pdo->prepare("
                INSERT INTO document_events
                    (document_id, event_type, phase, performed_by, from_status_id, to_status_id, remarks, metadata)
                VALUES (?, 'COMM_SENT_TO_FIRST_READING', 'SP_SECRETARY', ?, ?, ?, ?, ?)
            ")->execute([
                $documentId,
                $userId,
                $prevStatusId ?: null,
                $targetStatus['id'],
                'Communication sent to First Reading queue.',
                json_encode([
                    'ip_address' => client_ip(),
                    'user_agent' => client_user_agent(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            system_log('ERROR', 'SpsecCommInboxController::sendToFirstReading failed', [
                'document_id' => $documentId,
                'user_id'     => $userId,
                'error'       => $e->getMessage(),
            ]);
            flash_set('error', 'Failed to send communication to First Reading: ' . $e->getMessage());
            redirect('spsec/comm-inbox/show?id=' . $documentId);
        }

        $uStmt = $this->pdo->prepare("SELECT username FROM user_accounts WHERE id = ? LIMIT 1");
        $uStmt->execute([$userId]);
        $username = (string) ($uStmt->fetchColumn() ?: '');

        audit_log('UPDATE', 'CommInboxItem', (string) $documentId, null, [
            'action'      => 'sent_to_first_reading',
            'document_id' => $documentId,
            'performed_by' => $userId,
            'performed_by_username' => $username,
        ], "Communication #{$documentId} sent to First Reading (user: {$username})");

        flash_set('success', 'Communication sent to First Reading queue successfully.');
        redirect('spsec/comm-first-reading');
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function requireSpsecAccess(): void
    {
        $role = auth_role();
        if (!in_array($role, ['SP Secretary', 'Super Admin'], true)) {
            flash_set('error', 'Access denied.');
            redirect(dashboard_route_for_role($role));
        }
    }

    private function requireStatus(string $name): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, name FROM document_statuses WHERE name = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1
        ");
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException("Required document status '{$name}' not found. Please run pending migrations.");
        }
        return $row;
    }

    private function buildListWhere(string $search, string $tab): array
    {
        $finalizedStatusSql = "
            SELECT id FROM document_statuses
            WHERE name = 'Comm: Finalized' AND is_active = 1 AND is_deleted = 0 LIMIT 1
        ";

        $where  = [];
        $params = [];

        if ($tab === 'pending') {
            // Not yet sent to First Reading
            $where[]  = "cii.id IS NULL";
            // Must be a Communication document in the SP_SECRETARY phase or
            // have a pending/accepted SP_SECRETARY assignment
            $where[]  = "d.current_phase = 'SP_SECRETARY'";
            $where[]  = "d.current_status_id NOT IN ({$finalizedStatusSql})";
        } else {
            // Already sent to First Reading
            $where[]  = "cii.id IS NOT NULL";
            $where[]  = "cii.sent_to_fr_at IS NOT NULL";
        }

        if ($search !== '') {
            $where[]  = "(d.tracking_number LIKE ? OR d.subject_matter LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        return [implode(' AND ', $where), $params];
    }

    private function fetchCommDocument(int $documentId): array|false
    {
        $stmt = $this->pdo->prepare("
            SELECT
                d.*,
                dt.name        AS document_type_name,
                dt.badge_color AS document_type_badge_color,
                ds.name        AS status,
                ds.badge_color AS status_badge_color
            FROM documents d
            INNER JOIN document_types dt ON dt.id = d.document_type_id
                AND dt.name LIKE '%Communication%'
                AND dt.is_deleted = 0
            LEFT JOIN document_statuses ds ON ds.id = d.current_status_id
            WHERE d.id = ?
            LIMIT 1
        ");
        $stmt->execute([$documentId]);
        return $stmt->fetch();
    }

    private function fetchInboxItem(int $documentId): array|false
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM comm_inbox_items WHERE document_id = ? LIMIT 1"
        );
        $stmt->execute([$documentId]);
        return $stmt->fetch();
    }

    private function fetchFirstReading(int $documentId): array|false
    {
        $stmt = $this->pdo->prepare("
            SELECT cfr.*,
                   cc.name AS communication_category_name,
                   ua.username AS performed_by_username
            FROM comm_first_reading cfr
            LEFT JOIN communication_categories cc ON cc.id = cfr.communication_category_id
            LEFT JOIN user_accounts ua ON ua.id = cfr.performed_by
            WHERE cfr.document_id = ?
            LIMIT 1
        ");
        $stmt->execute([$documentId]);
        return $stmt->fetch();
    }

    private function fetchCommitteeAssignments(int $firstReadingId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT cca.*, c.name AS committee_name
            FROM comm_committee_assignments cca
            INNER JOIN committees c ON c.id = cca.committee_id
            WHERE cca.first_reading_id = ?
            ORDER BY c.name ASC
        ");
        $stmt->execute([$firstReadingId]);
        return $stmt->fetchAll();
    }

    private function fetchResolutionFiles(?int $reportId, ?int $firstReadingId): array
    {
        if ($firstReadingId !== null) {
            $stmt = $this->pdo->prepare("
                SELECT * FROM comm_resolution_files WHERE first_reading_id = ? ORDER BY uploaded_at ASC
            ");
            $stmt->execute([$firstReadingId]);
        } elseif ($reportId !== null) {
            $stmt = $this->pdo->prepare("
                SELECT * FROM comm_resolution_files WHERE committee_report_id = ? ORDER BY uploaded_at ASC
            ");
            $stmt->execute([$reportId]);
        } else {
            return [];
        }
        return $stmt->fetchAll();
    }

    private function fetchRoutes(int $documentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT dr.*, ro.name AS routing_option_name, ua.username AS routed_by_username
            FROM document_routes dr
            LEFT JOIN routing_options ro ON ro.id = dr.routing_option_id
            LEFT JOIN user_accounts   ua ON ua.id = dr.routed_by
            WHERE dr.document_id = ?
            ORDER BY dr.created_at ASC
        ");
        $stmt->execute([$documentId]);
        return $stmt->fetchAll();
    }
}

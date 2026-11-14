<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/DocumentService.php';

/**
 * PlenaryPetitionsController
 *
 * Handles the Plenary Petitions page — Complaints / Administrative Cases
 * documents that were marked "Noted" at Plenary First Reading.
 *
 * IMPORTANT: Only documents with status "Plenary Cases: Noted" appear here.
 * Documents that completed the Adopted Committee Report flow (status
 * "Plenary Cases: Finalized") appear in Finalized Documents → Petitions.
 *
 * ROUTE MAP
 * ─────────────────────────────────────────────────────────────────────────────
 *  GET  plenary/petitions        index()  — list of noted petitions
 *  GET  plenary/petitions/show   show()   — detail view
 *
 * AUTHORIZATION
 * ─────────────────────────────────────────────────────────────────────────────
 *  All routes: AuthMiddleware + RoleMiddleware (Plenary / Super Admin).
 */
class PlenaryPetitionsController
{
    protected PDO $pdo;

    private const PER_PAGE = 20;

    public function __construct()
    {
        $db        = new Database();
        $this->pdo = $db->connect();
    }

    // =========================================================================
    // 1. Index — list of noted petitions
    // =========================================================================

    public function index(): void
    {
        $userId = auth_id();
        if ($userId === null) { flash_set('error', 'You must be logged in.'); redirect('login'); }

        $this->requirePlenaryAccess();

        $search  = trim($_GET['search'] ?? '');
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $offset  = ($page - 1) * self::PER_PAGE;

        $notedStatusId = $this->getStatusId('Plenary Cases: Noted');

        $where  = ['d.current_status_id = ?'];
        $params = [$notedStatusId];

        if ($search !== '') {
            $where[]  = "(d.tracking_number LIKE ? OR d.subject_matter LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $whereClause = implode(' AND ', $where);

        $countSql = "
            SELECT COUNT(DISTINCT d.id)
            FROM documents d
            INNER JOIN plenary_cases_first_reading pcfr
                ON pcfr.document_id = d.id AND pcfr.decision = 'NOTED'
            WHERE {$whereClause}
        ";
        $cntStmt = $this->pdo->prepare($countSql);
        $cntStmt->execute($params);
        $total      = (int) $cntStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page       = min($page, $totalPages);

        $listSql = "
            SELECT
                d.id,
                d.tracking_number,
                d.subject_matter,
                d.date_received,
                d.current_status_id,
                ds.name              AS status,
                ds.badge_color       AS status_badge_color,
                dt.name              AS document_type_name,
                dt.badge_color       AS document_type_badge_color,
                pcfr.id              AS first_reading_id,
                pcfr.decision,
                pcfr.reading_date,
                pcfr.remarks,
                ua.username          AS performed_by_username
            FROM documents d
            INNER JOIN plenary_cases_first_reading pcfr
                ON pcfr.document_id = d.id AND pcfr.decision = 'NOTED'
            LEFT JOIN document_statuses  ds ON ds.id  = d.current_status_id
            LEFT JOIN document_types     dt ON dt.id  = d.document_type_id AND dt.is_deleted = 0
            LEFT JOIN user_accounts      ua ON ua.id  = pcfr.performed_by
            WHERE {$whereClause}
            ORDER BY pcfr.reading_date DESC, d.id DESC
            LIMIT ? OFFSET ?
        ";
        $listStmt = $this->pdo->prepare($listSql);
        $listStmt->execute([...$params, self::PER_PAGE, $offset]);
        $petitions = $listStmt->fetchAll();

        $tabCounts = ['noted' => $total];

        $pageTitle = 'Plenary Petitions';

        require __DIR__ . '/../../../resources/views/plenary/petitions/index.php';
    }

    // =========================================================================
    // 2. Show — detail view
    // =========================================================================

    public function show(): void
    {
        $userId     = auth_id();
        $documentId = (int) ($_GET['id'] ?? 0);

        if ($userId === null) { flash_set('error', 'You must be logged in.'); redirect('login'); }
        if ($documentId <= 0) { flash_set('error', 'Invalid document ID.'); redirect('plenary/petitions'); }

        $this->requirePlenaryAccess();

        // Fetch document
        $docStmt = $this->pdo->prepare("
            SELECT d.*,
                   dt.name        AS document_type_name,
                   dt.badge_color AS document_type_badge_color,
                   ds.name        AS status,
                   ds.badge_color AS status_badge_color
            FROM documents d
            LEFT JOIN document_types     dt ON dt.id = d.document_type_id AND dt.is_deleted = 0
            LEFT JOIN document_statuses  ds ON ds.id = d.current_status_id
            WHERE d.id = ?
            LIMIT 1
        ");
        $docStmt->execute([$documentId]);
        $document = $docStmt->fetch();

        if (!$document) {
            flash_set('error', 'Petition document not found.');
            redirect('plenary/petitions');
        }

        // Must have a NOTED first-reading record
        $frStmt = $this->pdo->prepare("
            SELECT pcfr.*, ua.username AS performed_by_username
            FROM plenary_cases_first_reading pcfr
            LEFT JOIN user_accounts ua ON ua.id = pcfr.performed_by
            WHERE pcfr.document_id = ? AND pcfr.decision = 'NOTED'
            LIMIT 1
        ");
        $frStmt->execute([$documentId]);
        $firstReading = $frStmt->fetch();

        if (!$firstReading) {
            flash_set('error', 'No Plenary Noted record found for this petition.');
            redirect('plenary/petitions');
        }

        // Route history
        $routeStmt = $this->pdo->prepare("
            SELECT dr.*, ro.name AS routing_option_name, ua.username AS routed_by_username
            FROM document_routes dr
            LEFT JOIN routing_options ro ON ro.id = dr.routing_option_id
            LEFT JOIN user_accounts   ua ON ua.id = dr.routed_by
            WHERE dr.document_id = ?
            ORDER BY dr.created_at ASC
        ");
        $routeStmt->execute([$documentId]);
        $routes = $routeStmt->fetchAll();

        $documentEvents = (new DocumentService($this->pdo))->getDocumentEvents($documentId);

        $pageTitle = 'Petition — ' . htmlspecialchars($document['tracking_number'] ?? '');

        // ── Motion for Reconsideration data ───────────────────────────────────
        // Petitions are Complaint / Administrative Cases docs.
        // Load any MFRs that have been filed against this case.
        $docService     = new DocumentService($this->pdo);
        $mfrLink        = null;
        $relatedMotions = [];
        $docTypeName    = $document['document_type_name'] ?? '';
        if ($docTypeName === DocumentService::mfrTypeName()) {
            $mfrLink = $docService->getMotionLink($documentId);
        } elseif (in_array($docTypeName, DocumentService::mfrEligibleReferenceTypeNames(), true)) {
            $relatedMotions = $docService->getRelatedMotions($documentId);
        }

        require __DIR__ . '/../../../resources/views/plenary/petitions/show.php';
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function requirePlenaryAccess(): void
    {
        $role = auth_role();
        if (!in_array($role, ['Plenary', 'Super Admin'], true)) {
            flash_set('error', 'Access denied.');
            redirect(dashboard_route_for_role($role));
        }
    }

    private function getStatusId(string $name): int
    {
        $row = $this->pdo->prepare(
            "SELECT id FROM document_statuses WHERE name = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1"
        );
        $row->execute([$name]);
        $r = $row->fetch();
        return $r ? (int) $r['id'] : 0;
    }
}

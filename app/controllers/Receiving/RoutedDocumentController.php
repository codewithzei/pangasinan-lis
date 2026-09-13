<?php

require_once __DIR__ . '/../../config/database.php';

class RoutedDocumentController
{
    protected PDO $pdo;

    public function __construct()
    {
        $database = new Database();
        $this->pdo = $database->connect();
    }

    /**
     * Display the routed documents list page
     */
    public function index(): void
    {
        // Pagination
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        // Filters
        $search = trim($_GET['search'] ?? '');
        $filterStatus = trim($_GET['status'] ?? '');
        $filterType = trim($_GET['type'] ?? '');
        $filterDivision = trim($_GET['division'] ?? '');

        // Base query
        $whereConditions = ['1=1'];
        $params = [];

        // Search filter (tracking number and subject matter)
        if ($search !== '') {
            $whereConditions[] = '(d.tracking_number LIKE ? OR d.subject_matter LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        // Status filter
        if ($filterStatus !== '') {
            $whereConditions[] = 'ds.id = ?';
            $params[] = $filterStatus;
        }

        // Division/Phase filter
        if ($filterDivision !== '') {
            $whereConditions[] = 'd.current_phase = ?';
            $params[] = $filterDivision;
        }

        $whereClause = implode(' AND ', $whereConditions);

        // Count total rows
        $countSql = "
            SELECT COUNT(*) as total
            FROM documents d
            INNER JOIN document_statuses ds ON d.current_status_id = ds.id
            WHERE {$whereClause}
        ";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetch()['total'];

        // Fetch documents
        $sql = "
            SELECT 
                d.id,
                d.tracking_number,
                d.subject_matter,
                d.document_type_id,
                dt.name as document_type_name,
                dt.badge_color as document_type_badge_color,
                d.current_phase,
                d.date_received,
                d.time_received,
                ds.id as status_id,
                ds.name as status,
                ds.badge_color as status_badge_color
            FROM documents d
            LEFT JOIN document_types dt ON d.document_type_id = dt.id
            LEFT JOIN document_statuses ds ON d.current_status_id = ds.id
            WHERE {$whereClause}
            ORDER BY d.date_received DESC, d.time_received DESC, d.id DESC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([...$params, $perPage, $offset]);
        $documents = $stmt->fetchAll();

        // Calculate pagination
        $totalPages = (int)ceil($totalRows / $perPage);

        // Load filter options - remove document types since we no longer filter by it
        $documentStatuses = $this->pdo->query("
            SELECT id, name 
            FROM document_statuses 
            WHERE is_active = 1 AND is_deleted = 0 
            ORDER BY name ASC
        ")->fetchAll();

        // Phase/Division options
        $divisions = [
            'RECEIVING' => 'Receiving',
            'ADMIN' => 'Admin',
            'SP_SECRETARY' => 'SP Secretary',
            'PLENARY' => 'Plenary',
            'COMMITTEE' => 'Committee',
            'FINALIZED' => 'Finalized',
            'FILED' => 'Filed',
        ];

        // Get statistics
        $totalDocuments = $this->pdo->query("SELECT COUNT(*) as count FROM documents")->fetch()['count'];
        $pendingDocuments = $this->pdo->query("
            SELECT COUNT(*) as count 
            FROM documents d
            INNER JOIN document_statuses ds ON d.current_status_id = ds.id
            WHERE ds.name = 'Pending'
        ")->fetch()['count'];
        $routedDocuments = $this->pdo->query("
            SELECT COUNT(*) as count 
            FROM documents d
            WHERE d.current_phase != 'RECEIVING'
        ")->fetch()['count'];

        $success = flash_get('success');
        $error = flash_get('error');

        $pageTitle = 'Routed Documents';
        $pageSubtitle = 'View and track all documents that have been routed through the system';

        require __DIR__ . '/../../../resources/views/receiving/routed-documents/index.php';
    }

    /**
     * Display document details
     */
    public function show(): void
    {
        $documentId = (int)($_GET['id'] ?? 0);

        if ($documentId <= 0) {
            flash_set('error', 'Invalid document ID.');
            redirect('receiving/routed-documents');
        }

        // Fetch document with all related data
        $sql = "
            SELECT 
                d.*,
                dt.name as document_type_name,
                dt.badge_color as document_type_badge_color,
                ds.name as status,
                ds.badge_color as status_badge_color,
                st.name as source_type,
                eo.name as external_office_name,
                eo.abbreviation as external_office_abbr,
                h.name as hospital_name,
                m.name as municipality_name,
                m.type as municipality_type,
                creator.username as created_by_username,
                creator.email as created_by_email,
                updater.username as updated_by_username,
                updater.email as updated_by_email,
                owner.username as current_owner_username,
                owner.email as current_owner_email
            FROM documents d
            LEFT JOIN document_types dt ON d.document_type_id = dt.id
            LEFT JOIN document_statuses ds ON d.current_status_id = ds.id
            LEFT JOIN source_types st ON d.source_type_id = st.id
            LEFT JOIN external_offices eo ON d.external_office_id = eo.id
            LEFT JOIN hospitals h ON d.hospital_id = h.id
            LEFT JOIN municities m ON d.municipality_id = m.id
            LEFT JOIN user_accounts creator ON d.created_by = creator.id
            LEFT JOIN user_accounts updater ON d.updated_by = updater.id
            LEFT JOIN user_accounts owner ON d.current_owner_user_id = owner.id
            WHERE d.id = ?
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$documentId]);
        $document = $stmt->fetch();

        if (!$document) {
            flash_set('error', 'Document not found.');
            redirect('receiving/routed-documents');
        }

        // Fetch document attachments
        $attachmentsSql = "
            SELECT 
                id,
                file_name,
                stored_path,
                mime_type,
                file_size,
                phase,
                attachment_type,
                created_at
            FROM document_attachments
            WHERE document_id = ?
            ORDER BY created_at ASC
        ";
        $attachmentsStmt = $this->pdo->prepare($attachmentsSql);
        $attachmentsStmt->execute([$documentId]);
        $attachments = $attachmentsStmt->fetchAll();

        // Fetch document routes/history
        $routesSql = "
            SELECT 
                dr.id,
                dr.from_phase,
                dr.to_phase,
                dr.remarks,
                dr.created_at as routed_at,
                routed_by_user.username as routed_by_username,
                routed_to_role.name as routed_to_role_name,
                ro.name as routing_option_name
            FROM document_routes dr
            LEFT JOIN user_accounts routed_by_user ON dr.routed_by = routed_by_user.id
            LEFT JOIN roles routed_to_role ON dr.routed_to_role_id = routed_to_role.id
            LEFT JOIN routing_options ro ON dr.routing_option_id = ro.id
            WHERE dr.document_id = ?
            ORDER BY dr.created_at ASC
        ";
        $routesStmt = $this->pdo->prepare($routesSql);
        $routesStmt->execute([$documentId]);
        $routes = $routesStmt->fetchAll();

        // Fetch checklist items
        $checklistSql = "
            SELECT 
                dci.id,
                dci.is_completed,
                dci.completed_at,
                c.name as checklist_name,
                c.description as checklist_description,
                completed_by_user.username as completed_by_username
            FROM document_checklist_items dci
            INNER JOIN checklists c ON dci.checklist_id = c.id
            LEFT JOIN user_accounts completed_by_user ON dci.completed_by = completed_by_user.id
            WHERE dci.document_id = ?
            ORDER BY c.name ASC
        ";
        $checklistStmt = $this->pdo->prepare($checklistSql);
        $checklistStmt->execute([$documentId]);
        $checklistItems = $checklistStmt->fetchAll();

        $pageTitle = 'Document Details';
        $pageSubtitle = $document['tracking_number'];

        require __DIR__ . '/../../../resources/views/receiving/routed-documents/show.php';
    }

    /**
     * Helper function to format phase names
     */
    protected function formatPhaseName(string $phase): string
    {
        $phaseMap = [
            'RECEIVING' => 'Receiving',
            'ADMIN' => 'Admin',
            'SP_SECRETARY' => 'SP Secretary',
            'PLENARY' => 'Plenary',
            'COMMITTEE' => 'Committee',
            'FINALIZED' => 'Finalized',
            'FILED' => 'Filed',
        ];

        return $phaseMap[$phase] ?? $phase;
    }
}

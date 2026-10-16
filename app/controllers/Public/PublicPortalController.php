<?php

require_once __DIR__ . '/../../config/database.php';

class PublicPortalController
{
    private PDO $pdo;

    public function __construct()
    {
        $db = new Database();
        $this->pdo = $db->connect();
    }

    /**
     * Portal home/landing page
     */
    public function index(): void
    {
        $pageTitle = 'Legislative Portal';
        
        // Get some statistics for the landing page
        $stats = $this->getPortalStats();
        
        ob_start();
        require __DIR__ . '/../../../resources/views/public/portal/index.php';
        $content = ob_get_clean();
        
        require __DIR__ . '/../../../resources/views/layouts/public.php';
    }

    /**
     * SP Members directory
     */
    public function members(): void
    {
        $pageTitle = 'SP Members';
        
        // Sanitize and validate input parameters
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $search = mb_substr($search, 0, 100); // Limit search length
        
        $districtId = isset($_GET['district']) ? filter_var($_GET['district'], FILTER_VALIDATE_INT) : '';
        $districtId = ($districtId !== false && $districtId > 0) ? $districtId : '';
        
        $position = isset($_GET['position']) ? trim($_GET['position']) : '';
        $position = mb_substr($position, 0, 150); // Match position field length
        
        // Build query for active, non-deleted members
        $sql = "SELECT 
                    sp.sp_member_id,
                    sp.first_name,
                    sp.middle_name,
                    sp.last_name,
                    sp.suffix,
                    sp.photo_path,
                    sp.position,
                    sp.sort_order,
                    d.name AS district_name,
                    d.id AS district_id
                FROM sp_members sp
                LEFT JOIN districts d ON sp.district_id = d.id
                WHERE sp.is_active = 1 
                  AND sp.is_deleted = 0";
        
        $params = [];
        
        // Apply search filter
        if (!empty($search)) {
            $sql .= " AND (
                sp.first_name LIKE ? OR 
                sp.middle_name LIKE ? OR 
                sp.last_name LIKE ? OR
                sp.position LIKE ?
            )";
            $searchParam = "%{$search}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        // Apply district filter
        if (!empty($districtId) && is_numeric($districtId)) {
            $sql .= " AND sp.district_id = ?";
            $params[] = (int)$districtId;
        }
        
        // Apply position filter
        if (!empty($position)) {
            $sql .= " AND sp.position LIKE ?";
            $params[] = "%{$position}%";
        }
        
        $sql .= " ORDER BY sp.sort_order ASC, sp.last_name ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $members = $stmt->fetchAll();
        
        // Get all active districts for filter dropdown
        $districtsStmt = $this->pdo->query("
            SELECT id, name 
            FROM districts 
            WHERE is_active = 1 AND is_deleted = 0 
            ORDER BY name ASC
        ");
        $districts = $districtsStmt->fetchAll();
        
        // Get unique positions for filter
        $positionsStmt = $this->pdo->query("
            SELECT DISTINCT position 
            FROM sp_members 
            WHERE is_active = 1 AND is_deleted = 0 
            ORDER BY position ASC
        ");
        $positions = $positionsStmt->fetchAll(PDO::FETCH_COLUMN);
        
        ob_start();
        require __DIR__ . '/../../../resources/views/public/portal/members.php';
        $content = ob_get_clean();
        
        require __DIR__ . '/../../../resources/views/layouts/public.php';
    }

    /**
     * Legislative documents/records listing
     */
    public function documents(): void
    {
        $pageTitle = 'Legislative Records';
        
        // Sanitize and validate input parameters
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $search = mb_substr($search, 0, 200); // Limit search length
        
        $typeId = isset($_GET['type']) ? filter_var($_GET['type'], FILTER_VALIDATE_INT) : '';
        $typeId = ($typeId !== false && $typeId > 0) ? $typeId : '';
        
        $year = isset($_GET['year']) ? filter_var($_GET['year'], FILTER_VALIDATE_INT) : '';
        // Validate year range (must be between 1900 and current year + 1)
        $currentYear = (int)date('Y');
        $year = ($year !== false && $year >= 1900 && $year <= $currentYear + 1) ? $year : '';
        
        // Validate and sanitize pagination
        $page = isset($_GET['page']) ? filter_var($_GET['page'], FILTER_VALIDATE_INT) : 1;
        $page = ($page !== false && $page > 0) ? $page : 1;
        $page = min($page, 10000); // Prevent extremely large page numbers
        
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        
        // Build query for archived records
        $sql = "SELECT 
                    ar.id,
                    ar.approved_no,
                    ar.date_approved,
                    ar.subject_matter,
                    ar.description,
                    ar.tags,
                    dt.name AS document_type_name,
                    dt.badge_color AS document_type_color
                FROM archived_records ar
                INNER JOIN document_types dt ON ar.document_type_id = dt.id
                WHERE 1=1";
        
        $params = [];
        
        // Apply search filter
        if (!empty($search)) {
            $sql .= " AND (
                ar.approved_no LIKE ? OR
                ar.subject_matter LIKE ? OR
                ar.description LIKE ? OR
                ar.tags LIKE ?
            )";
            $searchParam = "%{$search}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        // Apply document type filter
        if (!empty($typeId) && is_numeric($typeId)) {
            $sql .= " AND ar.document_type_id = ?";
            $params[] = (int)$typeId;
        }
        
        // Apply year filter
        if (!empty($year) && is_numeric($year)) {
            $sql .= " AND YEAR(ar.date_approved) = ?";
            $params[] = (int)$year;
        }
        
        // Get total count for pagination
        $countSql = "SELECT COUNT(*) " . substr($sql, strpos($sql, 'FROM'));
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalRecords = (int)$countStmt->fetchColumn();
        $totalPages = ceil($totalRecords / $perPage);
        
        // Add ordering and pagination
        $sql .= " ORDER BY ar.date_approved DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $documents = $stmt->fetchAll();
        
        // Get document types for filter
        $typesStmt = $this->pdo->query("
            SELECT id, name 
            FROM document_types 
            WHERE is_active = 1 AND is_deleted = 0 
            ORDER BY name ASC
        ");
        $documentTypes = $typesStmt->fetchAll();
        
        // Get available years
        $yearsStmt = $this->pdo->query("
            SELECT DISTINCT YEAR(date_approved) AS year 
            FROM archived_records 
            ORDER BY year DESC
        ");
        $years = $yearsStmt->fetchAll(PDO::FETCH_COLUMN);
        
        ob_start();
        require __DIR__ . '/../../../resources/views/public/portal/documents.php';
        $content = ob_get_clean();
        
        require __DIR__ . '/../../../resources/views/layouts/public.php';
    }

    /**
     * Document detail view
     */
    public function documentDetail(): void
    {
        // Validate ID parameter
        $id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
        
        if ($id === false || $id === null || $id <= 0) {
            http_response_code(404);
            die('Invalid document ID.');
        }
        
        // Get document details - ONLY from archived_records (public data)
        $stmt = $this->pdo->prepare("
            SELECT 
                ar.id,
                ar.approved_no,
                ar.date_approved,
                ar.subject_matter,
                ar.description,
                ar.tags,
                ar.created_at,
                dt.name AS document_type_name,
                dt.badge_color AS document_type_color
            FROM archived_records ar
            INNER JOIN document_types dt ON ar.document_type_id = dt.id
            WHERE ar.id = ?
        ");
        $stmt->execute([$id]);
        $document = $stmt->fetch();
        
        if (!$document) {
            http_response_code(404);
            die('Document not found.');
        }
        
        // Get authors - only public member info
        $authorsStmt = $this->pdo->prepare("
            SELECT 
                sp.first_name,
                sp.middle_name,
                sp.last_name,
                sp.suffix,
                sp.position
            FROM archived_records_authors ara
            INNER JOIN sp_members sp ON ara.sp_member_id = sp.sp_member_id
            WHERE ara.archived_record_id = ?
            ORDER BY ara.sort_order ASC
        ");
        $authorsStmt->execute([$id]);
        $authors = $authorsStmt->fetchAll();
        
        // Get committees - only active committees
        $committeesStmt = $this->pdo->prepare("
            SELECT 
                c.name,
                c.description
            FROM archived_records_committees arc
            INNER JOIN committees c ON arc.committee_id = c.id
            WHERE arc.archived_record_id = ?
              AND c.is_active = 1 
              AND c.is_deleted = 0
            ORDER BY arc.sort_order ASC
        ");
        $committeesStmt->execute([$id]);
        $committees = $committeesStmt->fetchAll();
        
        // Get attachments - all attachments for archived records are considered public
        $attachmentsStmt = $this->pdo->prepare("
            SELECT 
                id,
                file_name,
                file_size,
                mime_type,
                uploaded_at
            FROM archived_records_attachments
            WHERE archived_record_id = ?
            ORDER BY uploaded_at ASC
        ");
        $attachmentsStmt->execute([$id]);
        $attachments = $attachmentsStmt->fetchAll();
        
        $pageTitle = $document['subject_matter'];
        
        ob_start();
        require __DIR__ . '/../../../resources/views/public/portal/document_detail.php';
        $content = ob_get_clean();
        
        require __DIR__ . '/../../../resources/views/layouts/public.php';
    }

    /**
     * Committees listing
     */
    public function committees(): void
    {
        $pageTitle = 'Committees';
        
        // Get all active, non-deleted committees
        $stmt = $this->pdo->query("
            SELECT 
                id,
                name,
                description,
                sort_order
            FROM committees
            WHERE is_active = 1 AND is_deleted = 0
            ORDER BY sort_order ASC, name ASC
        ");
        $committees = $stmt->fetchAll();
        
        ob_start();
        require __DIR__ . '/../../../resources/views/public/portal/committees.php';
        $content = ob_get_clean();
        
        require __DIR__ . '/../../../resources/views/layouts/public.php';
    }

    /**
     * Download attachment (with security check)
     */
    public function downloadAttachment(): void
    {
        // Validate ID parameter
        $id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
        
        if ($id === false || $id === null || $id <= 0) {
            http_response_code(404);
            die('Invalid attachment ID.');
        }
        
        // Get attachment details - ONLY from archived_records_attachments (public files)
        $stmt = $this->pdo->prepare("
            SELECT 
                ara.file_name,
                ara.file_path,
                ara.mime_type,
                ara.file_size,
                ara.archived_record_id
            FROM archived_records_attachments ara
            WHERE ara.id = ?
        ");
        $stmt->execute([$id]);
        $attachment = $stmt->fetch();
        
        if (!$attachment) {
            http_response_code(404);
            die('Attachment not found.');
        }
        
        // Validate file path to prevent directory traversal
        $filePath = STORAGE_PATH . '/' . $attachment['file_path'];
        $realPath = realpath($filePath);
        $storagePath = realpath(STORAGE_PATH);
        
        // Ensure the file is within the storage directory
        if ($realPath === false || strpos($realPath, $storagePath) !== 0) {
            http_response_code(403);
            system_log('WARNING', 'Potential directory traversal attempt in public download', [
                'attachment_id' => $id,
                'file_path' => $attachment['file_path'],
                'ip_address' => client_ip()
            ]);
            die('Access denied.');
        }
        
        if (!file_exists($filePath)) {
            http_response_code(404);
            system_log('ERROR', 'Attachment file not found on disk', [
                'attachment_id' => $id,
                'file_path' => $attachment['file_path']
            ]);
            die('File not found.');
        }
        
        // Sanitize filename for Content-Disposition header
        $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $attachment['file_name']);
        
        // Log download (no user_id since this is public)
        log_file_download(
            $attachment['file_name'],
            'ArchivedRecordAttachment',
            (string)$id,
            $attachment['file_path']
        );
        
        // Send file with security headers
        header('Content-Type: ' . $attachment['mime_type']);
        header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
        header('Content-Length: ' . $attachment['file_size']);
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        readfile($filePath);
        exit;
    }

    /**
     * About page
     */
    public function about(): void
    {
        $pageTitle = 'About';
        
        ob_start();
        require __DIR__ . '/../../../resources/views/public/portal/about.php';
        $content = ob_get_clean();
        
        require __DIR__ . '/../../../resources/views/layouts/public.php';
    }

    /**
     * Contact page
     */
    public function contact(): void
    {
        $pageTitle = 'Contact Us';
        
        ob_start();
        require __DIR__ . '/../../../resources/views/public/portal/contact.php';
        $content = ob_get_clean();
        
        require __DIR__ . '/../../../resources/views/layouts/public.php';
    }

    /**
     * Privacy Policy page
     */
    public function privacy(): void
    {
        $pageTitle = 'Privacy Policy';
        
        ob_start();
        require __DIR__ . '/../../../resources/views/public/portal/privacy.php';
        $content = ob_get_clean();
        
        require __DIR__ . '/../../../resources/views/layouts/public.php';
    }

    /**
     * Terms of Use page
     */
    public function terms(): void
    {
        $pageTitle = 'Terms of Use';
        
        ob_start();
        require __DIR__ . '/../../../resources/views/public/portal/terms.php';
        $content = ob_get_clean();
        
        require __DIR__ . '/../../../resources/views/layouts/public.php';
    }

    /**
     * Accessibility Statement page
     */
    public function accessibility(): void
    {
        $pageTitle = 'Accessibility Statement';
        
        ob_start();
        require __DIR__ . '/../../../resources/views/public/portal/accessibility.php';
        $content = ob_get_clean();
        
        require __DIR__ . '/../../../resources/views/layouts/public.php';
    }

    /**
     * Disclaimer page
     */
    public function disclaimer(): void
    {
        $pageTitle = 'Disclaimer';
        
        ob_start();
        require __DIR__ . '/../../../resources/views/public/portal/disclaimer.php';
        $content = ob_get_clean();
        
        require __DIR__ . '/../../../resources/views/layouts/public.php';
    }

    /**
     * Get portal statistics for landing page
     */
    private function getPortalStats(): array
    {
        $stats = [];
        
        // Count active SP members
        $stmt = $this->pdo->query("
            SELECT COUNT(*) 
            FROM sp_members 
            WHERE is_active = 1 AND is_deleted = 0
        ");
        $stats['members_count'] = (int)$stmt->fetchColumn();
        
        // Count archived records
        $stmt = $this->pdo->query("
            SELECT COUNT(*) 
            FROM archived_records
        ");
        $stats['documents_count'] = (int)$stmt->fetchColumn();
        
        // Count active committees
        $stmt = $this->pdo->query("
            SELECT COUNT(*) 
            FROM committees 
            WHERE is_active = 1 AND is_deleted = 0
        ");
        $stats['committees_count'] = (int)$stmt->fetchColumn();
        
        return $stats;
    }
}

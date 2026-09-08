<?php

require_once __DIR__ . '/../../config/database.php';

class RouteDocumentController
{
    protected PDO $pdo;

    public function __construct()
    {
        $database = new Database();
        $this->pdo = $database->connect();
    }

    public function index(): void
    {
        // Load all active, non-deleted lookup data
        $documentTypes = $this->pdo->query("
            SELECT id, name, badge_color 
            FROM document_types 
            WHERE is_active = 1 AND is_deleted = 0 
            ORDER BY sort_order ASC, name ASC
        ")->fetchAll();

        $sourceTypes = $this->pdo->query("
            SELECT id, name 
            FROM source_types 
            WHERE is_active = 1 AND is_deleted = 0 
            ORDER BY sort_order ASC, name ASC
        ")->fetchAll();

        $externalOffices = $this->pdo->query("
            SELECT id, name, abbreviation 
            FROM external_offices 
            WHERE is_active = 1 AND is_deleted = 0 
            ORDER BY name ASC
        ")->fetchAll();

        $hospitals = $this->pdo->query("
            SELECT id, name 
            FROM hospitals 
            WHERE is_active = 1 AND is_deleted = 0 
            ORDER BY name ASC
        ")->fetchAll();

        $spMembers = $this->pdo->query("
            SELECT sp_member_id, first_name, middle_name, last_name, suffix, position, district_id 
            FROM sp_members 
            WHERE is_active = 1 AND is_deleted = 0 
            ORDER BY last_name ASC, first_name ASC
        ")->fetchAll();

        $municities = $this->pdo->query("
            SELECT id, name, type 
            FROM municities 
            WHERE is_active = 1 AND is_deleted = 0 
            ORDER BY name ASC
        ")->fetchAll();

        // Generate tracking number preview
        $currentYear = (int)date('Y');
        $trackingNumberPreview = $this->generateTrackingNumberPreview($currentYear);

        $success = flash_get('success');
        $error = flash_get('error');
        $errors = flash_get('errors');

        $pageTitle = 'Receive Document';
        $pageSubtitle = 'Receive and route incoming documents to Admin';

        $viewDir = __DIR__ . '/../../../resources/views/receiving/receive-document';
        if (!is_dir($viewDir)) {
            mkdir($viewDir, 0777, true);
        }
        require $viewDir . '/index.php';
    }

    public function submit(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in to submit documents.');
            redirect('receiving/receive-document');
        }

        // Collect and validate input
        $data = [
            'date_received' => trim($_POST['date_received'] ?? ''),
            'time_received' => trim($_POST['time_received'] ?? ''),
            'subject_matter' => trim($_POST['subject_matter'] ?? ''),
            'document_type_id' => (int)($_POST['document_type_id'] ?? 0),
            'source_type_id' => (int)($_POST['source_type_id'] ?? 0),
            'remarks' => trim($_POST['remarks'] ?? ''),
            
            // Source-specific fields
            'external_office_id' => !empty($_POST['external_office_id']) ? (int)$_POST['external_office_id'] : null,
            'hospital_id' => !empty($_POST['hospital_id']) ? (int)$_POST['hospital_id'] : null,
            'municipality_id' => !empty($_POST['municipality_id']) ? (int)$_POST['municipality_id'] : null,
            'sp_member_id' => !empty($_POST['sp_member_id']) ? (int)$_POST['sp_member_id'] : null,
            'source_name' => trim($_POST['source_name'] ?? ''),
            'source_contact_number' => trim($_POST['source_contact_number'] ?? ''),
            'source_address' => trim($_POST['source_address'] ?? ''),
            'source_liaison_name' => trim($_POST['source_liaison_name'] ?? ''),
            
            // Checklist
            'checklist_items' => $_POST['checklist_items'] ?? [],
        ];

        old_set($_POST);

        // Validate
        $errors = $this->validateSubmission($data);

        if (!empty($errors)) {
            flash_set('errors', $errors);
            redirect('receiving/receive-document');
        }

        // Validate file uploads
        if (empty($_FILES['attachments']) || empty($_FILES['attachments']['name'][0])) {
            flash_set('errors', ['At least one attachment is required.']);
            redirect('receiving/receive-document');
        }
        
        $uploadedFiles = $_FILES['attachments'];
        $fileErrors = $this->validateFileUploads($uploadedFiles);
        
        if (!empty($fileErrors)) {
            flash_set('errors', $fileErrors);
            redirect('receiving/receive-document');
        }

        // Begin transaction
        $this->pdo->beginTransaction();
        $uploadedFilePaths = [];

        try {
            // 1. Generate tracking number atomically
            $currentYear = (int)date('Y');
            $trackingInfo = $this->generateTrackingNumber($currentYear);
            $trackingNumber = $trackingInfo['tracking_number'];
            $trackingSequence = $trackingInfo['tracking_sequence'];

            // 2. Determine initial document status (Pending)
            $initialStatusStmt = $this->pdo->query("
                SELECT id FROM document_statuses 
                WHERE name = 'Pending' AND is_active = 1 AND is_deleted = 0 
                LIMIT 1
            ");
            $initialStatus = $initialStatusStmt->fetch();
            if (!$initialStatus) {
                throw new Exception('Initial document status not found. Please configure document statuses.');
            }
            $initialStatusId = (int)$initialStatus['id'];

            // 3. Get Admin role ID
            $adminRoleStmt = $this->pdo->query("
                SELECT id FROM roles 
                WHERE name = 'Admin' AND is_active = 1 AND is_deleted = 0 
                LIMIT 1
            ");
            $adminRole = $adminRoleStmt->fetch();
            if (!$adminRole) {
                throw new Exception('Admin role not found. Please configure roles.');
            }
            $adminRoleId = (int)$adminRole['id'];

            // 4. Resolve source name for SP Member if applicable
            $sourceName = $data['source_name'];
            if ($data['sp_member_id'] !== null) {
                $spMemberStmt = $this->pdo->prepare("
                    SELECT first_name, middle_name, last_name, suffix 
                    FROM sp_members 
                    WHERE sp_member_id = ? AND is_active = 1 AND is_deleted = 0 
                    LIMIT 1
                ");
                $spMemberStmt->execute([$data['sp_member_id']]);
                $spMember = $spMemberStmt->fetch();
                if ($spMember) {
                    $sourceName = trim(implode(' ', array_filter([
                        $spMember['first_name'],
                        $spMember['middle_name'],
                        $spMember['last_name'],
                        $spMember['suffix']
                    ])));
                }
            }

            // 5. Insert document
            $insertDocStmt = $this->pdo->prepare("
                INSERT INTO documents (
                    tracking_year, tracking_sequence, tracking_number,
                    date_received, time_received, subject_matter,
                    document_type_id, source_type_id,
                    external_office_id, hospital_id, municipality_id,
                    source_name, source_contact_number, source_address, source_liaison_name,
                    current_status_id, current_owner_user_id, current_phase,
                    remarks, created_by, updated_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 'ADMIN', ?, ?, ?)
            ");
            $insertDocStmt->execute([
                $currentYear,
                $trackingSequence,
                $trackingNumber,
                $data['date_received'],
                $data['time_received'],
                $data['subject_matter'],
                $data['document_type_id'],
                $data['source_type_id'],
                $data['external_office_id'],
                $data['hospital_id'],
                $data['municipality_id'],
                $sourceName ?: null,
                $data['source_contact_number'] ?: null,
                $data['source_address'] ?: null,
                $data['source_liaison_name'] ?: null,
                $initialStatusId,
                $data['remarks'] ?: null,
                $userId,
                $userId,
            ]);

            $documentId = (int)$this->pdo->lastInsertId();

            // 6. Insert document checklist items
            $checklistStmt = $this->pdo->prepare("
                SELECT c.id, cdt.is_required
                FROM checklists c
                INNER JOIN checklist_document_types cdt ON cdt.checklist_id = c.id
                WHERE cdt.document_type_id = ? AND c.is_active = 1 AND c.is_deleted = 0
                ORDER BY cdt.sort_order ASC
            ");
            $checklistStmt->execute([$data['document_type_id']]);
            $checklists = $checklistStmt->fetchAll();

            $insertChecklistItemStmt = $this->pdo->prepare("
                INSERT INTO document_checklist_items (document_id, checklist_id, is_completed, completed_by, completed_at)
                VALUES (?, ?, 0, NULL, NULL)
            ");

            foreach ($checklists as $checklist) {
                $insertChecklistItemStmt->execute([$documentId, $checklist['id']]);
            }

            // 7. Insert document revision (initial snapshot)
            $sourceSnapshot = [
                'external_office_id' => $data['external_office_id'],
                'hospital_id' => $data['hospital_id'],
                'municipality_id' => $data['municipality_id'],
                'sp_member_id' => $data['sp_member_id'],
                'source_name' => $sourceName,
                'source_contact_number' => $data['source_contact_number'],
                'source_address' => $data['source_address'],
                'source_liaison_name' => $data['source_liaison_name'],
            ];

            $insertRevisionStmt = $this->pdo->prepare("
                INSERT INTO document_revisions (
                    document_id, revision_number, changed_by, phase,
                    subject_matter, date_received, time_received,
                    document_type_id, source_type_id, source_snapshot,
                    remarks, change_reason
                ) VALUES (?, 1, ?, 'RECEIVING', ?, ?, ?, ?, ?, ?, ?, 'Initial document receipt')
            ");
            $insertRevisionStmt->execute([
                $documentId,
                $userId,
                $data['subject_matter'],
                $data['date_received'],
                $data['time_received'],
                $data['document_type_id'],
                $data['source_type_id'],
                json_encode($sourceSnapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $data['remarks'] ?: null,
            ]);

            // 8. Process file uploads
            if (!empty($uploadedFiles['name'][0])) {
                $uploadedFilePaths = $this->processFileUploads($uploadedFiles, $documentId, $userId);
            }

            // 9. Insert document route to Admin
            $insertRouteStmt = $this->pdo->prepare("
                INSERT INTO document_routes (
                    document_id, from_phase, to_phase, routing_option_id,
                    routed_by, routed_to_role_id, remarks
                ) VALUES (?, 'RECEIVING', 'ADMIN', NULL, ?, ?, ?)
            ");
            $insertRouteStmt->execute([
                $documentId,
                $userId,
                $adminRoleId,
                $data['remarks'] ?: null,
            ]);

            // 10. Insert Admin inbox assignment
            $insertAssignmentStmt = $this->pdo->prepare("
                INSERT INTO document_assignments (
                    document_id, assigned_to_role_id, phase, assigned_by, decision, received_at
                ) VALUES (?, ?, 'ADMIN', ?, 'PENDING', NOW())
            ");
            $insertAssignmentStmt->execute([$documentId, $adminRoleId, $userId]);

            // 11. Insert workflow events
            $insertEventStmt = $this->pdo->prepare("
                INSERT INTO document_events (
                    document_id, event_type, phase, performed_by,
                    to_status_id, remarks, metadata
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            // DOCUMENT_RECEIVED event
            $receiveMetadata = [
                'tracking_number' => $trackingNumber,
                'ip_address' => client_ip(),
                'user_agent' => client_user_agent(),
            ];
            $insertEventStmt->execute([
                $documentId,
                'DOCUMENT_RECEIVED',
                'RECEIVING',
                $userId,
                $initialStatusId,
                'Document received and logged',
                json_encode($receiveMetadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            // ROUTED_TO_ADMIN event
            $routeMetadata = [
                'routed_to_role_id' => $adminRoleId,
                'ip_address' => client_ip(),
            ];
            $insertEventStmt->execute([
                $documentId,
                'ROUTED_TO_ADMIN',
                'ADMIN',
                $userId,
                $initialStatusId,
                'Document routed to Admin for processing',
                json_encode($routeMetadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            // 12. Notify Admin users
            $this->notifyAdminUsers($documentId, $userId, $trackingNumber, $adminRoleId);

            // Commit transaction
            $this->pdo->commit();

            // Log audit and system events
            audit_log('CREATE', 'Document', (string)$documentId, null, [
                'tracking_number' => $trackingNumber,
                'document_type_id' => $data['document_type_id'],
                'source_type_id' => $data['source_type_id'],
                'subject_matter' => substr($data['subject_matter'], 0, 100),
            ], "Document received: {$trackingNumber}");

            system_log('INFO', "Document received and routed to Admin: {$trackingNumber}", [
                'document_id' => $documentId,
                'tracking_number' => $trackingNumber,
                'user_id' => $userId,
            ]);

            old_clear();
            flash_set('success', "Document {$trackingNumber} successfully received and routed to Admin.");
            redirect('receiving/receive-document');

        } catch (Throwable $e) {
            $this->pdo->rollBack();

            // Clean up uploaded files on failure
            foreach ($uploadedFilePaths as $filePath) {
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }

            system_log('ERROR', 'Document submission failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'trace' => $e->getTraceAsString(),
            ]);

            flash_set('error', 'Failed to submit document. Please try again.');
            redirect('receiving/receive-document');
        }
    }

    public function getChecklistsByDocumentType(): void
    {
        $documentTypeId = (int)($_GET['document_type_id'] ?? 0);

        if ($documentTypeId <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid document type ID.']);
            exit;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT c.id, c.name, c.description, cdt.is_required
                FROM checklists c
                INNER JOIN checklist_document_types cdt ON cdt.checklist_id = c.id
                WHERE cdt.document_type_id = ? AND c.is_active = 1 AND c.is_deleted = 0
                ORDER BY cdt.sort_order ASC, c.name ASC
            ");
            $stmt->execute([$documentTypeId]);
            $checklists = $stmt->fetchAll();

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $checklists]);
        } catch (Throwable $e) {
            system_log('ERROR', 'Failed to fetch checklists', ['error' => $e->getMessage(), 'document_type_id' => $documentTypeId]);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to load checklists.']);
        }
        exit;
    }

    protected function validateSubmission(array $data): array
    {
        $errors = [];

        // Date and time validation
        if ($data['date_received'] === '') {
            $errors[] = 'Date received is required.';
        } elseif (!$this->isValidDate($data['date_received'])) {
            $errors[] = 'Invalid date format for date received.';
        }

        if ($data['time_received'] === '') {
            $errors[] = 'Time received is required.';
        } elseif (!$this->isValidTime($data['time_received'])) {
            $errors[] = 'Invalid time format for time received.';
        }

        // Subject matter
        if ($data['subject_matter'] === '') {
            $errors[] = 'Subject matter is required.';
        } elseif (mb_strlen($data['subject_matter']) > 5000) {
            $errors[] = 'Subject matter must not exceed 5000 characters.';
        }

        // Document type
        if ($data['document_type_id'] <= 0) {
            $errors[] = 'Document type is required.';
        } else {
            $stmt = $this->pdo->prepare("SELECT id FROM document_types WHERE id = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1");
            $stmt->execute([$data['document_type_id']]);
            if (!$stmt->fetch()) {
                $errors[] = 'Invalid or inactive document type selected.';
            }
        }

        // Source type
        if ($data['source_type_id'] <= 0) {
            $errors[] = 'Source type is required.';
        } else {
            $stmt = $this->pdo->prepare("SELECT id, name FROM source_types WHERE id = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1");
            $stmt->execute([$data['source_type_id']]);
            $sourceType = $stmt->fetch();
            if (!$sourceType) {
                $errors[] = 'Invalid or inactive source type selected.';
            } else {
                // Validate source-specific fields
                $sourceTypeName = $sourceType['name'];
                $errors = array_merge($errors, $this->validateSourceFields($sourceTypeName, $data));
            }
        }

        return $errors;
    }

    protected function validateSourceFields(string $sourceTypeName, array $data): array
    {
        $errors = [];

        switch ($sourceTypeName) {
            case 'External Office':
                if ($data['external_office_id'] === null || $data['external_office_id'] <= 0) {
                    $errors[] = 'External office is required for this source type.';
                } else {
                    $stmt = $this->pdo->prepare("SELECT id FROM external_offices WHERE id = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1");
                    $stmt->execute([$data['external_office_id']]);
                    if (!$stmt->fetch()) {
                        $errors[] = 'Invalid external office selected.';
                    }
                }
                break;

            case 'Hospital':
                if ($data['hospital_id'] === null || $data['hospital_id'] <= 0) {
                    $errors[] = 'Hospital is required for this source type.';
                } else {
                    $stmt = $this->pdo->prepare("SELECT id FROM hospitals WHERE id = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1");
                    $stmt->execute([$data['hospital_id']]);
                    if (!$stmt->fetch()) {
                        $errors[] = 'Invalid hospital selected.';
                    }
                }
                break;

            case 'Agency':
                if ($data['source_name'] === '') {
                    $errors[] = 'Agency name is required for this source type.';
                }
                break;

            case 'SP Member':
                if ($data['sp_member_id'] === null || $data['sp_member_id'] <= 0) {
                    $errors[] = 'SP Member is required for this source type.';
                } else {
                    $stmt = $this->pdo->prepare("SELECT sp_member_id FROM sp_members WHERE sp_member_id = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1");
                    $stmt->execute([$data['sp_member_id']]);
                    if (!$stmt->fetch()) {
                        $errors[] = 'Invalid SP Member selected.';
                    }
                }
                break;

            case 'Client':
                if ($data['source_name'] === '') {
                    $errors[] = 'Client name is required for this source type.';
                }
                if ($data['municipality_id'] !== null && $data['municipality_id'] > 0) {
                    $stmt = $this->pdo->prepare("SELECT id FROM municities WHERE id = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1");
                    $stmt->execute([$data['municipality_id']]);
                    if (!$stmt->fetch()) {
                        $errors[] = 'Invalid municipality/city selected.';
                    }
                }
                break;
        }

        return $errors;
    }

    protected function validateFileUploads(array $files): array
    {
        $errors = [];
        $allowedMimeTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ];
        $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
        $maxFileSize = 25 * 1024 * 1024; // 25MB

        if (empty($files['name'][0])) {
            $errors[] = 'At least one attachment is required.';
            return $errors;
        }

        $fileCount = count($files['name']);
        if ($fileCount > 10) {
            $errors[] = 'Maximum 10 files can be uploaded at once.';
        }

        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $errors[] = "Upload error for file: {$files['name'][$i]}";
                continue;
            }

            if ($files['size'][$i] > $maxFileSize) {
                $errors[] = "File {$files['name'][$i]} exceeds maximum size of 25MB.";
            }

            $extension = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions, true)) {
                $errors[] = "File {$files['name'][$i]} has an invalid file type. Allowed: " . implode(', ', $allowedExtensions);
            }

            // Verify MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $files['tmp_name'][$i]);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedMimeTypes, true)) {
                $errors[] = "File {$files['name'][$i]} has an invalid MIME type.";
            }
        }

        return $errors;
    }

    protected function processFileUploads(array $files, int $documentId, int $userId): array
    {
        $uploadedPaths = [];
        $uploadDir = __DIR__ . '/../../../public/uploads/documents/' . $documentId . '/';

        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception('Failed to create upload directory.');
            }
        }

        $fileCount = count($files['name']);
        $insertAttachmentStmt = $this->pdo->prepare("
            INSERT INTO document_attachments (
                document_id, uploaded_by, phase, file_name, stored_path,
                mime_type, file_size, attachment_type
            ) VALUES (?, ?, 'RECEIVING', ?, ?, ?, ?, 'RECEIVING_FILE')
        ");

        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $originalName = basename($files['name'][$i]);
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $storedName = 'doc_' . $documentId . '_' . uniqid('', true) . '.' . $extension;
            $storedPath = $uploadDir . $storedName;
            $relativePath = 'uploads/documents/' . $documentId . '/' . $storedName;

            if (!move_uploaded_file($files['tmp_name'][$i], $storedPath)) {
                throw new Exception("Failed to move uploaded file: {$originalName}");
            }

            $uploadedPaths[] = $storedPath;

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $storedPath);
            finfo_close($finfo);

            $fileSize = filesize($storedPath);

            $insertAttachmentStmt->execute([
                $documentId,
                $userId,
                $originalName,
                $relativePath,
                $mimeType,
                $fileSize,
            ]);

            log_file_upload($originalName, $fileSize, $mimeType, 'Document', (string)$documentId, $relativePath);
        }

        return $uploadedPaths;
    }

    protected function notifyAdminUsers(int $documentId, int $senderUserId, string $trackingNumber, int $adminRoleId): void
    {
        // Get all active Admin users
        $stmt = $this->pdo->prepare("
            SELECT id, username, email
            FROM user_accounts
            WHERE role_id = ? AND status = 'active'
        ");
        $stmt->execute([$adminRoleId]);
        $adminUsers = $stmt->fetchAll();

        if (empty($adminUsers)) {
            return;
        }

        $insertNotificationStmt = $this->pdo->prepare("
            INSERT INTO notifications (
                document_id, recipient_user_id, sender_user_id, type,
                title, message, action_url, is_read
            ) VALUES (?, ?, ?, 'DOCUMENT_ASSIGNED', ?, ?, ?, 0)
        ");

        $title = "New Document Assigned: {$trackingNumber}";
        $message = "A new document ({$trackingNumber}) has been received and assigned to Admin for routing.";
        $actionUrl = "/admin/inbox?document_id={$documentId}";

        foreach ($adminUsers as $adminUser) {
            $insertNotificationStmt->execute([
                $documentId,
                $adminUser['id'],
                $senderUserId,
                $title,
                $message,
                $actionUrl,
            ]);
        }
    }

    protected function generateTrackingNumber(int $year): array
    {
        // Use row locking to prevent concurrent duplicate tracking numbers
        $this->pdo->exec("INSERT INTO document_tracking_sequences (tracking_year, last_sequence) 
                          VALUES ({$year}, 0) 
                          ON DUPLICATE KEY UPDATE last_sequence = last_sequence");

        $stmt = $this->pdo->prepare("
            SELECT last_sequence FROM document_tracking_sequences 
            WHERE tracking_year = ? 
            FOR UPDATE
        ");
        $stmt->execute([$year]);
        $row = $stmt->fetch();

        $nextSequence = ((int)$row['last_sequence']) + 1;

        $updateStmt = $this->pdo->prepare("
            UPDATE document_tracking_sequences 
            SET last_sequence = ? 
            WHERE tracking_year = ?
        ");
        $updateStmt->execute([$nextSequence, $year]);

        $trackingNumber = sprintf('TRK-%d-%05d', $year, $nextSequence);

        return [
            'tracking_year' => $year,
            'tracking_sequence' => $nextSequence,
            'tracking_number' => $trackingNumber,
        ];
    }

    protected function generateTrackingNumberPreview(int $year): string
    {
        $stmt = $this->pdo->prepare("
            SELECT last_sequence FROM document_tracking_sequences 
            WHERE tracking_year = ?
        ");
        $stmt->execute([$year]);
        $row = $stmt->fetch();

        $nextSequence = $row ? ((int)$row['last_sequence']) + 1 : 1;
        return sprintf('TRK-%d-%05d', $year, $nextSequence);
    }

    protected function isValidDate(string $date): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    protected function isValidTime(string $time): bool
    {
        $t = DateTime::createFromFormat('H:i', $time);
        return $t && $t->format('H:i') === $time;
    }
}

<?php

require_once __DIR__ . '/../config/database.php';

/**
 * DocumentService
 *
 * Centralises all shared document-receipt logic so that both the
 * Receiving workflow (RouteDocumentController) and the Admin direct-
 * receive workflow (AdminReceiveDocumentController) call the same
 * validated code path.
 *
 * Public surface deliberately kept narrow:
 *   - validateSubmission()           – field + DB validation
 *   - validateFileUploads()          – file-array validation
 *   - receiveDocument()              – full atomic transaction
 *   - processAdditionalAttachments() – attach extra files to an existing document
 */
class DocumentService
{
    protected PDO $pdo;

    public function __construct()
    {
        $database = new Database();
        $this->pdo = $database->connect();
    }

    // -------------------------------------------------------------------------
    // Validation helpers
    // -------------------------------------------------------------------------

    /**
     * Validate the fields common to every receive-document submission.
     *
     * @param  array $data  Sanitised POST fields.
     * @return string[]     Array of human-readable error messages (empty = valid).
     */
    public function validateSubmission(array $data): array
    {
        $errors = [];

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

        if ($data['subject_matter'] === '') {
            $errors[] = 'Subject matter is required.';
        } elseif (mb_strlen($data['subject_matter']) > 5000) {
            $errors[] = 'Subject matter must not exceed 5000 characters.';
        }

        if ($data['document_type_id'] <= 0) {
            $errors[] = 'Document type is required.';
        } else {
            $stmt = $this->pdo->prepare(
                "SELECT id FROM document_types
                  WHERE id = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1"
            );
            $stmt->execute([$data['document_type_id']]);
            if (!$stmt->fetch()) {
                $errors[] = 'Invalid or inactive document type selected.';
            }
        }

        if ($data['source_type_id'] <= 0) {
            $errors[] = 'Source type is required.';
        } else {
            $stmt = $this->pdo->prepare(
                "SELECT id, name FROM source_types
                  WHERE id = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1"
            );
            $stmt->execute([$data['source_type_id']]);
            $sourceType = $stmt->fetch();
            if (!$sourceType) {
                $errors[] = 'Invalid or inactive source type selected.';
            } else {
                $errors = array_merge(
                    $errors,
                    $this->validateSourceFields($sourceType['name'], $data)
                );
            }
        }

        return $errors;
    }

    /**
     * Validate source-type-specific fields.
     */
    public function validateSourceFields(string $sourceTypeName, array $data): array
    {
        $errors = [];

        switch ($sourceTypeName) {
            case 'External Office':
                if (empty($data['external_office_id'])) {
                    $errors[] = 'External office is required for this source type.';
                } else {
                    $stmt = $this->pdo->prepare(
                        "SELECT id FROM external_offices
                          WHERE id = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1"
                    );
                    $stmt->execute([$data['external_office_id']]);
                    if (!$stmt->fetch()) {
                        $errors[] = 'Invalid external office selected.';
                    }
                }
                break;

            case 'Hospital':
                if (empty($data['hospital_id'])) {
                    $errors[] = 'Hospital is required for this source type.';
                } else {
                    $stmt = $this->pdo->prepare(
                        "SELECT id FROM hospitals
                          WHERE id = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1"
                    );
                    $stmt->execute([$data['hospital_id']]);
                    if (!$stmt->fetch()) {
                        $errors[] = 'Invalid hospital selected.';
                    }
                }
                break;

            case 'Agency':
                if (empty($data['source_name'])) {
                    $errors[] = 'Agency name is required for this source type.';
                }
                break;

            case 'SP Member':
                if (empty($data['sp_member_id'])) {
                    $errors[] = 'SP Member is required for this source type.';
                } else {
                    $stmt = $this->pdo->prepare(
                        "SELECT sp_member_id FROM sp_members
                          WHERE sp_member_id = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1"
                    );
                    $stmt->execute([$data['sp_member_id']]);
                    if (!$stmt->fetch()) {
                        $errors[] = 'Invalid SP Member selected.';
                    }
                }
                break;

            case 'Client':
                if (empty($data['source_name'])) {
                    $errors[] = 'Client name is required for this source type.';
                }
                if (!empty($data['municipality_id'])) {
                    $stmt = $this->pdo->prepare(
                        "SELECT id FROM municities
                          WHERE id = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1"
                    );
                    $stmt->execute([$data['municipality_id']]);
                    if (!$stmt->fetch()) {
                        $errors[] = 'Invalid municipality selected.';
                    }
                }
                break;
        }

        return $errors;
    }

    /**
     * Validate a PHP file-upload array.
     *
     * @param  array $files          $_FILES['attachments']
     * @param  bool  $requireAtLeastOne  Set false when additional-upload is optional.
     * @return string[]
     */
    public function validateFileUploads(array $files, bool $requireAtLeastOne = true): array
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
        $maxFileSize       = 25 * 1024 * 1024; // 25 MB

        if ($requireAtLeastOne && empty($files['name'][0])) {
            $errors[] = 'At least one attachment is required.';
            return $errors;
        }

        if (empty($files['name'][0])) {
            return $errors; // nothing submitted, caller decides if that's ok
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
                $errors[] = "File \"{$files['name'][$i]}\" exceeds the maximum size of 25 MB.";
            }

            $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExtensions, true)) {
                $errors[] = "File \"{$files['name'][$i]}\" has an invalid extension. "
                          . 'Allowed: ' . implode(', ', $allowedExtensions) . '.';
            }

            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $files['tmp_name'][$i]);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedMimeTypes, true)) {
                $errors[] = "File \"{$files['name'][$i]}\" has an unrecognised MIME type ({$mimeType}).";
            }
        }

        return $errors;
    }

    // -------------------------------------------------------------------------
    // Receive document (full transaction)
    // -------------------------------------------------------------------------

    /**
     * Receive and persist a new document in one atomic transaction.
     *
     * Accepts pre-sanitised field data and a PHP file-upload array.
     * Returns the new document's ID and tracking number on success.
     *
     * @param  array  $data          Sanitised POST fields (same keys as validateSubmission).
     * @param  array  $uploadedFiles $_FILES['attachments']
     * @param  int    $userId        Authenticated user performing the action.
     * @param  string $initialPhase  'ADMIN' for Receiving → Admin, 'RECEIVING' is reserved.
     * @return array{document_id: int, tracking_number: string}
     * @throws Throwable             On any DB or filesystem failure (caller must catch).
     */
    public function receiveDocument(
        array  $data,
        array  $uploadedFiles,
        int    $userId,
        string $initialPhase = 'ADMIN'
    ): array {
        $this->pdo->beginTransaction();
        $storedFilePaths = [];
        $pendingFileLogs = [];

        try {
            $currentYear = (int) date('Y');

            // 1. Tracking number (row-locked for safety)
            $trackingInfo    = $this->generateTrackingNumber($currentYear);
            $trackingNumber  = $trackingInfo['tracking_number'];
            $trackingSeq     = $trackingInfo['tracking_sequence'];

            // 2. Initial status (Pending)
            $initialStatus = $this->pdo->query(
                "SELECT id FROM document_statuses
                  WHERE name = 'Pending' AND is_active = 1 AND is_deleted = 0 LIMIT 1"
            )->fetch();
            if (!$initialStatus) {
                throw new RuntimeException(
                    "Initial document status 'Pending' not found. Please configure document statuses."
                );
            }
            $statusId = (int) $initialStatus['id'];

            // 3. Admin role
            $adminRole = $this->pdo->query(
                "SELECT id FROM roles
                  WHERE name = 'Admin' AND is_active = 1 AND is_deleted = 0 LIMIT 1"
            )->fetch();
            if (!$adminRole) {
                throw new RuntimeException(
                    "Admin role not found. Please configure roles."
                );
            }
            $adminRoleId = (int) $adminRole['id'];

            // 4. Resolve source name for SP Member
            $sourceName = $data['source_name'];
            if (!empty($data['sp_member_id'])) {
                $spStmt = $this->pdo->prepare(
                    "SELECT first_name, middle_name, last_name, suffix
                       FROM sp_members
                      WHERE sp_member_id = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1"
                );
                $spStmt->execute([$data['sp_member_id']]);
                $spMember = $spStmt->fetch();
                if ($spMember) {
                    $sourceName = trim(implode(' ', array_filter([
                        $spMember['first_name'],
                        $spMember['middle_name'],
                        $spMember['last_name'],
                        $spMember['suffix'],
                    ])));
                }
            }

            // 5. Insert document
            $insertDoc = $this->pdo->prepare("
                INSERT INTO documents (
                    tracking_year, tracking_sequence, tracking_number,
                    date_received, time_received, subject_matter, document_type_id,
                    source_type_id,
                    external_office_id, hospital_id, municipality_id,
                    source_name, source_contact_number, source_address, source_liaison_name,
                    current_status_id, current_owner_user_id, current_phase,
                    remarks, created_by, updated_by
                ) VALUES (
                    ?, ?, ?,
                    ?, ?, ?, ?,
                    ?,
                    ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, NULL, ?,
                    ?, ?, ?
                )
            ");
            $insertDoc->execute([
                $currentYear,
                $trackingSeq,
                $trackingNumber,
                $data['date_received'],
                $data['time_received'],
                $data['subject_matter'],
                $data['document_type_id'],
                $data['source_type_id'],
                $data['external_office_id']   ?: null,
                $data['hospital_id']          ?: null,
                $data['municipality_id']      ?: null,
                $sourceName                   ?: null,
                $data['source_contact_number'] ?: null,
                $data['source_address']        ?: null,
                $data['source_liaison_name']   ?: null,
                $statusId,
                $initialPhase,
                $data['remarks']              ?: null,
                $userId,
                $userId,
            ]);
            $documentId = (int) $this->pdo->lastInsertId();

            // 6. Initial revision (revision 1)
            $sourceSnapshot = [
                'external_office_id'     => $data['external_office_id']    ?: null,
                'hospital_id'            => $data['hospital_id']           ?: null,
                'municipality_id'        => $data['municipality_id']       ?: null,
                'sp_member_id'           => $data['sp_member_id']          ?: null,
                'source_name'            => $sourceName,
                'source_contact_number'  => $data['source_contact_number'] ?: null,
                'source_address'         => $data['source_address']        ?: null,
                'source_liaison_name'    => $data['source_liaison_name']   ?: null,
            ];
            $insertRev = $this->pdo->prepare("
                INSERT INTO document_revisions (
                    document_id, revision_number, changed_by, phase,
                    subject_matter, document_type_id, date_received, time_received,
                    source_type_id, source_snapshot,
                    remarks, change_reason
                ) VALUES (?, 1, ?, 'RECEIVING', ?, ?, ?, ?, ?, ?, ?, 'Initial document receipt')
            ");
            $insertRev->execute([
                $documentId,
                $userId,
                $data['subject_matter'],
                $data['document_type_id'],
                $data['date_received'],
                $data['time_received'],
                $data['source_type_id'],
                json_encode($sourceSnapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $data['remarks'] ?: null,
            ]);

            // 7. File uploads (DB rows only — no logging inside transaction)
            if (!empty($uploadedFiles['name'][0])) {
                $storedFilePaths = $this->processFileUploads(
                    $uploadedFiles,
                    $documentId,
                    $userId,
                    'RECEIVING',
                    'RECEIVING_FILE'
                );
            }
            // Capture pending file-log entries BEFORE commit so we can flush
            // them after the transaction closes (flushFileUploadLogs is called
            // below, outside the try/catch transaction block).
            $pendingFileLogs = $this->takePendingFileLogs();

            // 8. Route record: RECEIVING → ADMIN
            $insertRoute = $this->pdo->prepare("
                INSERT INTO document_routes (
                    document_id, from_phase, to_phase, routing_option_id,
                    routed_by, routed_to_role_id, remarks
                ) VALUES (?, 'RECEIVING', 'ADMIN', NULL, ?, ?, ?)
            ");
            $insertRoute->execute([
                $documentId,
                $userId,
                $adminRoleId,
                $data['remarks'] ?: null,
            ]);

            // 9. Admin inbox assignment
            //    accepted_by and assigned_to_user_id are NULL: the assignment
            //    is unclaimed and visible to all Admin users until one of them
            //    clicks Accept (which atomically sets both columns).
            $insertAssign = $this->pdo->prepare("
                INSERT INTO document_assignments (
                    document_id, assigned_to_role_id, phase,
                    assigned_by, decision, received_at,
                    accepted_by, assigned_to_user_id
                ) VALUES (?, ?, 'ADMIN', ?, 'PENDING', NOW(), NULL, NULL)
            ");
            $insertAssign->execute([$documentId, $adminRoleId, $userId]);

            // 10. Save checklist items (if any were selected)
            if (!empty($data['checklist_items']) && is_array($data['checklist_items'])) {
                $this->saveDocumentChecklistItems($documentId, $data['checklist_items'], $userId);
            }

            // 11. Workflow events
            $insertEvent = $this->pdo->prepare("
                INSERT INTO document_events (
                    document_id, event_type, phase, performed_by,
                    to_status_id, remarks, metadata
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $baseMetadata = [
                'ip_address' => client_ip(),
                'user_agent' => client_user_agent(),
            ];

            $insertEvent->execute([
                $documentId,
                'DOCUMENT_RECEIVED',
                'RECEIVING',
                $userId,
                $statusId,
                'Document received and logged',
                json_encode(
                    array_merge($baseMetadata, ['tracking_number' => $trackingNumber]),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
            ]);

            $insertEvent->execute([
                $documentId,
                'ROUTED_TO_ADMIN',
                'ADMIN',
                $userId,
                $statusId,
                'Document routed to Admin for processing',
                json_encode(
                    array_merge($baseMetadata, ['routed_to_role_id' => $adminRoleId]),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
            ]);

            // ----------------------------------------------------------------
            // COMMIT — all document rows are now durable.
            // Notifications, file-upload logs, and audit entries are written
            // AFTER this point so they never run inside an open transaction
            // and cannot trigger lock-wait contention on a second connection.
            // ----------------------------------------------------------------
            $this->pdo->commit();

            // Post-commit: flush deferred file-upload logs (uses _log_pdo —
            // a separate connection — so it must never run inside the main tx).
            $this->flushFileUploadLogs($pendingFileLogs);

            // Post-commit: notify Admin users.
            // Notification failure must NOT roll back an already-committed
            // document, so we catch and log it as a warning instead.
            try {
                $this->notifyRoleUsers(
                    $adminRoleId,
                    $documentId,
                    $userId,
                    'DOCUMENT_ASSIGNED',
                    "New Document Assigned: {$trackingNumber}",
                    "A new document ({$trackingNumber}) has been received and assigned to Admin for routing.",
                    BASE_URL . "/admin/inbox/show?id={$documentId}"
                );
            } catch (Throwable $notifyEx) {
                system_log('WARNING', "Notification failed after document commit: {$notifyEx->getMessage()}", [
                    'document_id'    => $documentId,
                    'tracking_number' => $trackingNumber,
                    'error_code'     => $notifyEx->getCode(),
                    'user_id'        => $userId,
                ]);
            }

            // Post-commit audit logs (safe: no open transaction).
            audit_log('CREATE', 'Document', (string) $documentId, null, [
                'tracking_number'  => $trackingNumber,
                'source_type_id'   => $data['source_type_id'],
                'document_type_id' => $data['document_type_id'],
                'subject_matter'   => mb_substr($data['subject_matter'], 0, 100),
            ], "Document received: {$trackingNumber}");

            system_log('INFO', "Document received and routed to Admin: {$trackingNumber}", [
                'document_id'    => $documentId,
                'tracking_number' => $trackingNumber,
                'user_id'        => $userId,
            ]);

            return [
                'document_id'     => $documentId,
                'tracking_number' => $trackingNumber,
            ];

        } catch (Throwable $e) {
            // Guard: only roll back if a transaction is still open.
            // If commit() already succeeded and a post-commit step threw,
            // there is nothing to roll back — calling rollBack() on a closed
            // transaction would itself throw, masking the real error.
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            // Delete any files staged to disk so no orphans are left behind.
            foreach ($storedFilePaths as $path) {
                if (file_exists($path)) {
                    @unlink($path);
                }
            }

            // Remove the upload directory if it is now empty (was created by
            // stageFileUploads but the transaction was rolled back before the
            // document row was committed, leaving an orphan directory).
            if (!empty($storedFilePaths)) {
                $uploadDir = dirname(reset($storedFilePaths));
                if (is_dir($uploadDir) && count(scandir($uploadDir)) === 2) { // only . and ..
                    @rmdir($uploadDir);
                }
            }

            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Additional attachments for an existing document
    // -------------------------------------------------------------------------

    /**
     * Validate and attach additional files to an already-existing document.
     *
     * Must be called inside an active transaction managed by the caller.
     * After the transaction commits, the caller must retrieve pending file-upload
     * log entries with $this->docService->takePendingFileLogs() and pass them
     * to $this->docService->flushFileUploadLogs() to avoid writing to
     * audit_logs/system_logs while the transaction is still open.
     *
     * @param  array  $files      $_FILES['attachments']
     * @param  int    $documentId
     * @param  int    $userId
     * @param  string $phase      Current workflow phase.
     * @return string[]           Absolute stored file paths (for rollback cleanup).
     * @throws RuntimeException   On filesystem failure.
     */
    public function processAdditionalAttachments(
        array  $files,
        int    $documentId,
        int    $userId,
        string $phase = 'ADMIN'
    ): array {
        // 'ADMIN_FILE' is added to the document_attachments.attachment_type
        // ENUM by migration 048_add_admin_file_attachment_type.php.
        return $this->processFileUploads($files, $documentId, $userId, $phase, 'ADMIN_FILE');
    }

    // -------------------------------------------------------------------------
    // Lookup helpers (called by controllers to populate form dropdowns)
    // -------------------------------------------------------------------------

    public function getDocumentTypes(): array
    {
        return $this->pdo->query(
            "SELECT id, name, description, badge_color FROM document_types
              WHERE is_active = 1 AND is_deleted = 0 ORDER BY sort_order ASC, name ASC"
        )->fetchAll();
    }

    public function getSourceTypes(): array
    {
        return $this->pdo->query(
            "SELECT id, name FROM source_types
              WHERE is_active = 1 AND is_deleted = 0 ORDER BY sort_order ASC, name ASC"
        )->fetchAll();
    }

    public function getExternalOffices(): array
    {
        return $this->pdo->query(
            "SELECT id, name, abbreviation FROM external_offices
              WHERE is_active = 1 AND is_deleted = 0 ORDER BY name ASC"
        )->fetchAll();
    }

    public function getHospitals(): array
    {
        return $this->pdo->query(
            "SELECT id, name FROM hospitals
              WHERE is_active = 1 AND is_deleted = 0 ORDER BY name ASC"
        )->fetchAll();
    }

    public function getSpMembers(): array
    {
        return $this->pdo->query(
            "SELECT sp_member_id, first_name, middle_name, last_name, suffix, position, district_id
               FROM sp_members
              WHERE is_active = 1 AND is_deleted = 0 ORDER BY last_name ASC, first_name ASC"
        )->fetchAll();
    }

    public function getMunicities(): array
    {
        return $this->pdo->query(
            "SELECT id, name, type FROM municities
              WHERE is_active = 1 AND is_deleted = 0 ORDER BY name ASC"
        )->fetchAll();
    }

    public function getCommunicationCategories(): array
    {
        return $this->pdo->query(
            "SELECT id, name FROM communication_categories
              WHERE is_active = 1 AND is_deleted = 0 ORDER BY sort_order ASC, name ASC"
        )->fetchAll();
    }

    /**
     * Get all checklist items associated with a specific document type.
     *
     * @param  int $documentTypeId
     * @return array  Array of checklist items with their properties
     */
    public function getChecklistsByDocumentType(int $documentTypeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                c.id,
                c.name,
                c.description,
                cdt.is_required,
                cdt.sort_order,
                cdt.notes
            FROM checklists c
            INNER JOIN checklist_document_types cdt ON c.id = cdt.checklist_id
            WHERE cdt.document_type_id = ?
              AND c.is_active = 1
              AND c.is_deleted = 0
            ORDER BY cdt.sort_order ASC, c.sort_order ASC, c.name ASC
        ");
        $stmt->execute([$documentTypeId]);
        return $stmt->fetchAll();
    }

    /**
     * Save document checklist items.
     * Must be called inside an active transaction.
     *
     * @param  int   $documentId
     * @param  array $checklistItemIds  Array of checklist IDs that were selected
     * @param  int   $userId            User who is completing the checklist
     * @return void
     */
    public function saveDocumentChecklistItems(int $documentId, array $checklistItemIds, int $userId): void
    {
        if (empty($checklistItemIds)) {
            return;
        }

        $insert = $this->pdo->prepare("
            INSERT INTO document_checklist_items (
                document_id, checklist_id, is_completed, completed_by, completed_at
            ) VALUES (?, ?, 1, ?, NOW())
        ");

        foreach ($checklistItemIds as $checklistId) {
            $checklistId = (int) $checklistId;
            if ($checklistId > 0) {
                $insert->execute([$documentId, $checklistId, $userId]);
            }
        }
    }

    public function getTrackingNumberPreview(int $year): string
    {
        $stmt = $this->pdo->prepare(
            "SELECT last_sequence FROM document_tracking_sequences WHERE tracking_year = ?"
        );
        $stmt->execute([$year]);
        $row = $stmt->fetch();
        $next = $row ? ((int) $row['last_sequence']) + 1 : 1;
        return sprintf('TRK-%d-%05d', $year, $next);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Generate the next tracking number, row-locked to prevent duplicates.
     * Must be called inside an active transaction.
     */
    public function generateTrackingNumber(int $year): array
    {
        $this->pdo->exec(
            "INSERT INTO document_tracking_sequences (tracking_year, last_sequence)
             VALUES ({$year}, 0)
             ON DUPLICATE KEY UPDATE last_sequence = last_sequence"
        );

        $stmt = $this->pdo->prepare(
            "SELECT last_sequence FROM document_tracking_sequences
              WHERE tracking_year = ? FOR UPDATE"
        );
        $stmt->execute([$year]);
        $row = $stmt->fetch();

        $next = ((int) $row['last_sequence']) + 1;

        $upd = $this->pdo->prepare(
            "UPDATE document_tracking_sequences SET last_sequence = ? WHERE tracking_year = ?"
        );
        $upd->execute([$next, $year]);

        return [
            'tracking_year'     => $year,
            'tracking_sequence' => $next,
            'tracking_number'   => sprintf('TRK-%d-%05d', $year, $next),
        ];
    }

    /**
     * Move uploaded files to disk and insert document_attachments rows.
     *
     * stageFileUploads() runs BEFORE any transaction (filesystem I/O).
     * insertStagedAttachments() runs INSIDE an active transaction (DB only).
     *
     * Returns absolute stored paths for rollback cleanup.  Pending file-upload
     * log entries are discarded here; callers that need them should call
     * stageFileUploads() + insertStagedAttachments() directly and pass the
     * returned pending-log array to flushFileUploadLogs() after commit.
     *
     * @return string[]  Absolute stored paths (for rollback cleanup).
     */
    public function processFileUploads(
        array  $files,
        int    $documentId,
        int    $userId,
        string $phase,
        string $attachmentType
    ): array {
        $staged = $this->stageFileUploads($files, $documentId);
        // insertStagedAttachments now returns pending log entries.
        // Callers of processFileUploads are responsible for calling
        // flushFileUploadLogs() after their transaction commits.
        // We store them on the instance so the caller can retrieve them.
        $this->_pendingFileLogs = $this->insertStagedAttachments($staged, $documentId, $userId, $phase, $attachmentType);
        return array_column($staged, 'abs_path');
    }

    /**
     * Pending file-upload log entries queued by the last processFileUploads()
     * call.  Retrieve with takePendingFileLogs() after commit.
     */
    private array $_pendingFileLogs = [];

    /**
     * Return and clear any file-upload log entries queued by processFileUploads().
     * Call this after the transaction commits, then pass result to flushFileUploadLogs().
     *
     * @return array[]
     */
    public function takePendingFileLogs(): array
    {
        $logs = $this->_pendingFileLogs;
        $this->_pendingFileLogs = [];
        return $logs;
    }

    /**
     * STAGE ONLY — move uploaded files to disk without touching the database.
     *
     * Call this BEFORE opening a transaction to keep filesystem I/O outside
     * the lock window.  If the subsequent DB transaction fails, delete every
     * path returned in the 'abs_path' column.
     *
     * @param  array $_FILES-style upload array (name, tmp_name, error, size, …).
     * @param  int   $documentId  Used for the destination directory name.
     * @return array[] Each element: [
     *     'abs_path'      => string,  // absolute filesystem path
     *     'relative_path' => string,  // web-root-relative path for DB storage
     *     'original_name' => string,
     *     'mime_type'     => string,
     *     'file_size'     => int,
     * ]
     * @throws RuntimeException On mkdir or move failure.
     */
    public function stageFileUploads(array $files, int $documentId): array
    {
        $uploadDir = __DIR__ . '/../../public/uploads/documents/' . $documentId . '/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            throw new RuntimeException('Failed to create upload directory.');
        }

        $staged    = [];
        $fileCount = count($files['name']);

        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $originalName = basename($files['name'][$i]);
            $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $storedName   = 'doc_' . $documentId . '_' . uniqid('', true) . '.' . $ext;
            $absPath      = $uploadDir . $storedName;
            $relativePath = 'uploads/documents/' . $documentId . '/' . $storedName;

            if (!move_uploaded_file($files['tmp_name'][$i], $absPath)) {
                // Clean up any files already moved in this batch before throwing.
                foreach ($staged as $s) {
                    if (file_exists($s['abs_path'])) {
                        @unlink($s['abs_path']);
                    }
                }
                throw new RuntimeException("Failed to move uploaded file: {$originalName}");
            }

            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $absPath);
            finfo_close($finfo);

            $staged[] = [
                'abs_path'      => $absPath,
                'relative_path' => $relativePath,
                'original_name' => $originalName,
                'mime_type'     => $mimeType,
                'file_size'     => (int) filesize($absPath),
            ];
        }

        return $staged;
    }

    /**
     * DB ONLY — insert document_attachments rows for files already on disk.
     *
     * Must be called inside an active transaction.
     * Does NOT touch the filesystem and does NOT write to audit_logs or
     * system_logs.  Logging uses a separate PDO connection (_log_pdo) which,
     * when called while the main transaction holds row-locks on documents /
     * document_assignments / document_attachments, creates a second open
     * connection that competes for those same locks — causing SQLSTATE HY000
     * error 1205 (lock wait timeout) on concurrent requests.
     *
     * Callers MUST call flushFileUploadLogs() with the returned array after
     * the main transaction has been committed.
     *
     * @param  array[] $staged      Output of stageFileUploads().
     * @param  int     $documentId
     * @param  int     $userId
     * @param  string  $phase
     * @param  string  $attachmentType
     * @return array[] Collected file-upload log entries; pass to
     *                 flushFileUploadLogs() after commit.
     */
    public function insertStagedAttachments(
        array  $staged,
        int    $documentId,
        int    $userId,
        string $phase,
        string $attachmentType
    ): array {
        if (empty($staged)) {
            return [];
        }

        $insert = $this->pdo->prepare("
            INSERT INTO document_attachments (
                document_id, uploaded_by, phase, file_name, stored_path,
                mime_type, file_size, attachment_type
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        // Collect log data in memory; do NOT call log_file_upload() here.
        // Writing to audit_logs/system_logs via the _log_pdo() second
        // connection while the main transaction is open causes lock contention.
        $pendingLogs = [];

        foreach ($staged as $s) {
            $insert->execute([
                $documentId,
                $userId,
                $phase,
                $s['original_name'],
                $s['relative_path'],
                $s['mime_type'],
                $s['file_size'],
                $attachmentType,
            ]);

            $pendingLogs[] = [
                'file_name'    => $s['original_name'],
                'file_size'    => $s['file_size'],
                'mime_type'    => $s['mime_type'],
                'relative_path'=> $s['relative_path'],
                'document_id'  => $documentId,
            ];
        }

        return $pendingLogs;
    }

    /**
     * Write file-upload audit/system log entries that were deferred until
     * after the main workflow transaction committed.
     *
     * Call this immediately after $pdo->commit() — never while a transaction
     * is still open.
     *
     * @param  array[] $pendingLogs  Return value of insertStagedAttachments().
     */
    public function flushFileUploadLogs(array $pendingLogs): void
    {
        foreach ($pendingLogs as $entry) {
            log_file_upload(
                $entry['file_name'],
                $entry['file_size'],
                $entry['mime_type'],
                'Document',
                (string) $entry['document_id'],
                $entry['relative_path']
            );
        }
    }

    /**
     * Send a notification to every active user in a given role.
     */
    public function notifyRoleUsers(
        int    $roleId,
        int    $documentId,
        int    $senderUserId,
        string $type,
        string $title,
        string $message,
        string $actionUrl
    ): void {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM user_accounts WHERE role_id = ? AND status = 'active'"
        );
        $stmt->execute([$roleId]);
        $users = $stmt->fetchAll();

        if (empty($users)) {
            return;
        }

        $ins = $this->pdo->prepare("
            INSERT INTO notifications (
                document_id, recipient_user_id, sender_user_id, type,
                title, message, action_url, is_read
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 0)
        ");
        foreach ($users as $u) {
            $ins->execute([
                $documentId,
                $u['id'],
                $senderUserId,
                $type,
                $title,
                $message,
                $actionUrl,
            ]);
        }
    }

    /**
     * Send a notification to a specific user.
     */
    public function notifyUser(
        int    $recipientUserId,
        int    $documentId,
        int    $senderUserId,
        string $type,
        string $title,
        string $message,
        string $actionUrl
    ): void {
        $ins = $this->pdo->prepare("
            INSERT INTO notifications (
                document_id, recipient_user_id, sender_user_id, type,
                title, message, action_url, is_read
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 0)
        ");
        $ins->execute([
            $documentId,
            $recipientUserId,
            $senderUserId,
            $type,
            $title,
            $message,
            $actionUrl,
        ]);
    }

    /**
     * Get the next revision number for a document (MAX + 1).
     */
    public function nextRevisionNumber(int $documentId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(MAX(revision_number), 0) + 1 AS next_rev
               FROM document_revisions WHERE document_id = ?"
        );
        $stmt->execute([$documentId]);
        return (int) $stmt->fetch()['next_rev'];
    }

    // -------------------------------------------------------------------------
    // Small format / validation helpers
    // -------------------------------------------------------------------------

    public function isValidDate(string $date): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    public function isValidTime(string $time): bool
    {
        $t = DateTime::createFromFormat('H:i', $time);
        return $t && $t->format('H:i') === $time;
    }
}

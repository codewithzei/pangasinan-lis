<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/services/DocumentService.php';

const WORKFLOW_BATCH_MARKER = 'WF-BATCH-2026-11';
const WORKFLOW_BATCH_SAMPLE_ID = 109;

function workflowBatchFail(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}" . PHP_EOL);
    exit(1);
}

function workflowBatchId(PDO $pdo, string $table, string $name): int
{
    $allowedTables = ['roles', 'document_statuses', 'document_types', 'source_types', 'committees'];
    if (!in_array($table, $allowedTables, true)) {
        workflowBatchFail("Unexpected lookup table: {$table}");
    }

    $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE name = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1");
    $stmt->execute([$name]);
    $id = (int) $stmt->fetchColumn();
    if ($id <= 0) {
        workflowBatchFail("Required active {$table} row not found: {$name}");
    }
    return $id;
}

function workflowBatchUserId(PDO $pdo, string $roleName): int
{
    $stmt = $pdo->prepare("
        SELECT ua.id
        FROM user_accounts ua
        INNER JOIN roles r ON r.id = ua.role_id
        WHERE r.name = ? AND r.is_active = 1 AND r.is_deleted = 0
                    AND ua.status = 'active' AND ua.is_deleted = 0
        ORDER BY ua.id
        LIMIT 1
    ");
    $stmt->execute([$roleName]);
    $id = (int) $stmt->fetchColumn();
    if ($id <= 0) {
        workflowBatchFail("No active user account is available for role {$roleName}.");
    }
    return $id;
}

function workflowBatchEvent(
    PDO $pdo,
    int $documentId,
    string $eventType,
    string $phase,
    int $userId,
    ?int $fromStatusId,
    ?int $toStatusId,
    string $remarks,
    array $metadata = []
): void {
    $pdo->prepare("
        INSERT INTO document_events
            (document_id, event_type, phase, performed_by, from_status_id, to_status_id, remarks, metadata)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $documentId,
        $eventType,
        $phase,
        $userId,
        $fromStatusId,
        $toStatusId,
        $remarks,
        json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

function workflowBatchPdf(string $absolutePath, string $label): array
{
    $directory = dirname($absolutePath);
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        workflowBatchFail("Could not create fixture file directory: {$directory}");
    }

    $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $label);
    $stream = "BT /F1 14 Tf 50 740 Td ({$text}) Tj ET\n";
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
        '<< /Length ' . strlen($stream) . ">>\nstream\n{$stream}endstream",
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1) . " 0 obj\n{$object}\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 6\n0000000000 65535 f \n";
    for ($index = 1; $index <= 5; $index++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$index]);
    }
    $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

    if (file_put_contents($absolutePath, $pdf) === false) {
        workflowBatchFail("Could not write fixture PDF: {$absolutePath}");
    }

    return ['size' => strlen($pdf), 'mime' => 'application/pdf'];
}

function workflowBatchAttachment(
    PDO $pdo,
    int $documentId,
    int $userId,
    string $phase,
    string $attachmentType,
    string $fileName,
    string $relativePath,
    array $fileInfo
): int {
    $pdo->prepare("
        INSERT INTO document_attachments
            (document_id, uploaded_by, phase, file_name, stored_path, mime_type, file_size, attachment_type)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $documentId, $userId, $phase, $fileName, $relativePath,
        $fileInfo['mime'], $fileInfo['size'], $attachmentType,
    ]);
    return (int) $pdo->lastInsertId();
}

function workflowBatchCreateDocument(
    PDO $pdo,
    DocumentService $service,
    int $receivingUserId,
    int $sourceTypeId,
    int $documentTypeId,
    string $documentTypeName,
    int $index,
    ?int $referenceDocumentId = null
): array {
    $subject = WORKFLOW_BATCH_MARKER . ' - ' . $documentTypeName . ' sample ' . str_pad((string) $index, 2, '0', STR_PAD_LEFT);
    $data = [
        'date_received'          => date('Y-m-d'),
        'time_received'          => date('H:i:s'),
        'subject_matter'         => $subject,
        'document_type_id'       => $documentTypeId,
        'source_type_id'         => $sourceTypeId,
        'external_office_id'     => 0,
        'hospital_id'            => 0,
        'municipality_id'        => 0,
        'sp_member_id'           => 0,
        'source_name'            => WORKFLOW_BATCH_MARKER . ' Test Source',
        'source_contact_number'  => '',
        'source_address'         => '',
        'source_liaison_name'    => '',
        'remarks'                => WORKFLOW_BATCH_MARKER . ' Generated for local workflow testing.',
        'checklist_items'        => [],
        'referenced_document_id' => $referenceDocumentId ?? 0,
    ];

    $emptyUploads = ['name' => [], 'tmp_name' => [], 'error' => [], 'size' => [], 'type' => []];
    $received = $service->receiveDocument($data, $emptyUploads, $receivingUserId, 'ADMIN');
    $documentId = (int) $received['document_id'];

    $fileName = WORKFLOW_BATCH_MARKER . '-receiving-' . $documentId . '.pdf';
    $relativePath = 'uploads/documents/' . $documentId . '/' . $fileName;
    $fileInfo = workflowBatchPdf(PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath), $subject . ' receiving attachment');
    workflowBatchAttachment($pdo, $documentId, $receivingUserId, 'RECEIVING', 'RECEIVING_FILE', $fileName, $relativePath, $fileInfo);

    return ['id' => $documentId, 'subject' => $subject, 'tracking_number' => $received['tracking_number']];
}

function workflowBatchRouteToPlenary(PDO $pdo, int $documentId, int $adminUserId, int $plenaryRoleId): void
{
    $docStmt = $pdo->prepare('SELECT * FROM documents WHERE id = ? LIMIT 1');
    $docStmt->execute([$documentId]);
    $document = $docStmt->fetch();
    if (!$document || $document['current_phase'] !== 'ADMIN') {
        workflowBatchFail("Document {$documentId} is not in the Admin phase.");
    }

    $assignmentStmt = $pdo->prepare("
        SELECT id FROM document_assignments
        WHERE document_id = ? AND phase = 'ADMIN' AND decision = 'PENDING' AND completed_at IS NULL
        ORDER BY id DESC LIMIT 1
    ");
    $assignmentStmt->execute([$documentId]);
    $assignmentId = (int) $assignmentStmt->fetchColumn();
    if ($assignmentId <= 0) {
        workflowBatchFail("Document {$documentId} has no pending Admin assignment.");
    }

    $pdo->prepare("
        UPDATE document_assignments
        SET decision = 'ACCEPTED', accepted_at = NOW(), accepted_by = ?, assigned_to_user_id = ?
        WHERE id = ? AND decision = 'PENDING' AND completed_at IS NULL
    ")->execute([$adminUserId, $adminUserId, $assignmentId]);

    $statusId = (int) $document['current_status_id'];
    workflowBatchEvent($pdo, $documentId, 'ADMIN_ACCEPTED', 'ADMIN', $adminUserId, $statusId, $statusId,
        WORKFLOW_BATCH_MARKER . ' Admin accepted test document.');

    $revisionStmt = $pdo->prepare('SELECT COALESCE(MAX(revision_number), 0) + 1 FROM document_revisions WHERE document_id = ?');
    $revisionStmt->execute([$documentId]);
    $revisionNumber = (int) $revisionStmt->fetchColumn();
    $pdo->prepare("
        INSERT INTO document_revisions
            (document_id, revision_number, changed_by, phase, subject_matter, date_received, time_received,
             document_type_id, source_type_id, source_snapshot, remarks, change_reason)
        VALUES (?, ?, ?, 'ADMIN', ?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $documentId, $revisionNumber, $adminUserId, $document['subject_matter'],
        $document['date_received'], $document['time_received'], $document['document_type_id'],
        $document['source_type_id'], json_encode([
            'external_office_id' => $document['external_office_id'],
            'hospital_id' => $document['hospital_id'],
            'municipality_id' => $document['municipality_id'],
            'source_name' => $document['source_name'],
            'source_contact_number' => $document['source_contact_number'],
            'source_address' => $document['source_address'],
            'source_liaison_name' => $document['source_liaison_name'],
        ]),
        $document['remarks'], WORKFLOW_BATCH_MARKER . ' Admin routed to Plenary.',
    ]);

    $pdo->prepare("
        UPDATE document_assignments
        SET decision = 'COMPLETED', completed_at = NOW(), remarks = ?
        WHERE id = ? AND decision = 'ACCEPTED' AND completed_at IS NULL
    ")->execute([WORKFLOW_BATCH_MARKER . ' Routed to Plenary.', $assignmentId]);

    $pdo->prepare("
        INSERT INTO document_assignments
            (document_id, assigned_to_role_id, phase, assigned_by, decision, received_at)
        VALUES (?, ?, 'PLENARY', ?, 'PENDING', NOW())
    ")->execute([$documentId, $plenaryRoleId, $adminUserId]);

    $pdo->prepare("UPDATE documents SET current_phase = 'PLENARY', current_owner_user_id = NULL, updated_by = ? WHERE id = ?")
        ->execute([$adminUserId, $documentId]);
    $pdo->prepare("
        INSERT INTO document_routes (document_id, from_phase, to_phase, routed_by, routed_to_role_id, remarks)
        VALUES (?, 'ADMIN', 'PLENARY', ?, ?, ?)
    ")->execute([$documentId, $adminUserId, $plenaryRoleId, WORKFLOW_BATCH_MARKER . ' Admin to Plenary.']);
    workflowBatchEvent($pdo, $documentId, 'ROUTED_TO_PLENARY', 'PLENARY', $adminUserId, $statusId, $statusId,
        WORKFLOW_BATCH_MARKER . ' Routed from Admin to Plenary.');
}

function workflowBatchCreatePddAndProcess(
    PDO $pdo,
    int $documentId,
    int $plenaryUserId,
    int $onProcessId,
    int $awaitingFirstReadingId,
    int $documentTypeId,
    string $documentTypeName,
    int $index
): void {
    $assignmentStmt = $pdo->prepare("
        SELECT id FROM document_assignments
        WHERE document_id = ? AND phase = 'PLENARY' AND decision = 'PENDING' AND completed_at IS NULL
        ORDER BY id DESC LIMIT 1
    ");
    $assignmentStmt->execute([$documentId]);
    $assignmentId = (int) $assignmentStmt->fetchColumn();
    if ($assignmentId <= 0) {
        workflowBatchFail("Document {$documentId} has no pending Plenary assignment.");
    }

    $pdo->prepare("
        UPDATE document_assignments
        SET decision = 'ACCEPTED', accepted_at = NOW(), accepted_by = ?, assigned_to_user_id = ?
        WHERE id = ? AND decision = 'PENDING' AND completed_at IS NULL
    ")->execute([$plenaryUserId, $plenaryUserId, $assignmentId]);

    $preDecisionId = workflowBatchId($pdo, 'document_statuses', 'For Encoding');
    workflowBatchEvent($pdo, $documentId, 'PLENARY_ACCEPTED', 'PLENARY', $plenaryUserId, null, null,
        WORKFLOW_BATCH_MARKER . ' Plenary accepted test document.');

    $typeNameLower = strtolower($documentTypeName);
    $isCommunicationFlow = str_contains($typeNameLower, 'communication')
        || str_contains($typeNameLower, 'motion for reconsideration');
    $isCase = str_contains($typeNameLower, 'administrative case') || str_contains($typeNameLower, 'complaint');
    $proposedNumber = (!$isCommunicationFlow && !$isCase)
        ? WORKFLOW_BATCH_MARKER . '-' . $documentTypeId . '-' . $index
        : null;

    $pdo->prepare("
        INSERT INTO plenary_document_details
            (document_id, proposed_number, target_first_reading_date, session_type, status_id,
             additional_details, remarks, created_by, updated_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            proposed_number = VALUES(proposed_number),
            target_first_reading_date = VALUES(target_first_reading_date),
            session_type = VALUES(session_type),
            additional_details = VALUES(additional_details),
            remarks = VALUES(remarks), status_id = VALUES(status_id), updated_by = VALUES(updated_by)
    ")->execute([
        $documentId, $proposedNumber, date('Y-m-d', strtotime('+7 days')),
        $isCommunicationFlow || $isCase ? null : 'REGULAR', $preDecisionId,
        WORKFLOW_BATCH_MARKER . ' Plenary test processing record.',
        WORKFLOW_BATCH_MARKER . ' For Encoding.', $plenaryUserId, $plenaryUserId,
    ]);
    workflowBatchEvent($pdo, $documentId, 'PLENARY_PRE_DECISION', 'PLENARY', $plenaryUserId, null, $preDecisionId,
        WORKFLOW_BATCH_MARKER . ' Pre-decision set to For Encoding.', ['pre_decision_status' => 'For Encoding']);

    $pddStatusId = $isCase ? $awaitingFirstReadingId : $preDecisionId;
    if ($isCase) {
        $pdo->prepare("UPDATE plenary_document_details SET status_id = ?, updated_by = ? WHERE document_id = ?")
            ->execute([$awaitingFirstReadingId, $plenaryUserId, $documentId]);
    }
    $pdo->prepare("UPDATE documents SET current_status_id = ?, updated_by = ? WHERE id = ?")
        ->execute([$awaitingFirstReadingId, $plenaryUserId, $documentId]);
    workflowBatchEvent($pdo, $documentId, 'PLENARY_PROCESSING_SAVED', 'PLENARY', $plenaryUserId, $pddStatusId,
        $awaitingFirstReadingId, WORKFLOW_BATCH_MARKER . ' Plenary processing details saved.');
}

function workflowBatchFileDocument(PDO $pdo, int $documentId, int $adminUserId, int $filedStatusId): void
{
    $docStmt = $pdo->prepare('SELECT tracking_number, subject_matter, current_status_id, current_phase FROM documents WHERE id = ? LIMIT 1');
    $docStmt->execute([$documentId]);
    $document = $docStmt->fetch();
    if (!$document || $document['current_phase'] !== 'FINALIZED') {
        workflowBatchFail("Document {$documentId} was not finalized and cannot be filed.");
    }

    $fileName = WORKFLOW_BATCH_MARKER . '-filing-' . $documentId . '.pdf';
    $relativePath = 'uploads/documents/' . $documentId . '/' . $fileName;
    $fileInfo = workflowBatchPdf(PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath),
        $document['tracking_number'] . ' final filing attachment');
    $attachmentId = workflowBatchAttachment($pdo, $documentId, $adminUserId, 'FILED', 'FILING_FILE', $fileName, $relativePath, $fileInfo);

    $pdo->prepare("
        INSERT INTO document_filings (document_id, description, tags, is_public, filed_by)
        VALUES (?, ?, ?, 0, ?)
    ")->execute([
        $documentId,
        WORKFLOW_BATCH_MARKER . ' final filing for ' . $document['tracking_number'],
        json_encode([WORKFLOW_BATCH_MARKER, 'workflow-test']),
        $adminUserId,
    ]);
    $filingId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO document_filing_attachments (filing_id, attachment_id) VALUES (?, ?)')
        ->execute([$filingId, $attachmentId]);
    $effectiveFiledStatusId = $filedStatusId > 0 ? $filedStatusId : (int) $document['current_status_id'];
    $pdo->prepare("UPDATE documents SET current_phase = 'FILED', current_status_id = ?, filed_at = NOW(), updated_by = ? WHERE id = ?")
        ->execute([$effectiveFiledStatusId, $adminUserId, $documentId]);
    workflowBatchEvent($pdo, $documentId, 'DOCUMENT_FILED', 'FILED', $adminUserId,
        (int) $document['current_status_id'], $effectiveFiledStatusId,
        WORKFLOW_BATCH_MARKER . ' Document filed with test attachment.', ['filing_id' => $filingId]);
}

function workflowBatchCreateArchivedRecord(
    PDO $pdo,
    int $adminUserId,
    int $documentTypeId,
    int $index
): void {
    $approvedNo = WORKFLOW_BATCH_MARKER . '-ARCH-' . str_pad((string) $index, 3, '0', STR_PAD_LEFT);
    $subject = WORKFLOW_BATCH_MARKER . ' archived record sample ' . $index;
    $pdo->prepare("
        INSERT INTO archived_records
            (approved_no, date_approved, subject_matter, description, tags, document_type_id,
             custom_document_type_name, created_by, updated_by)
        VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?)
    ")->execute([
        $approvedNo, date('Y-m-d'), $subject,
        'Generated for local client workflow testing.', WORKFLOW_BATCH_MARKER . ',workflow-test',
        $documentTypeId, $adminUserId, $adminUserId,
    ]);
    $recordId = (int) $pdo->lastInsertId();

    $fileName = $approvedNo . '.pdf';
    $relativePath = 'archived_records/' . $fileName;
    $fileInfo = workflowBatchPdf(STORAGE_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath), $subject);
    $pdo->prepare("
        INSERT INTO archived_records_attachments
            (archived_record_id, file_name, file_size, mime_type, file_path, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([$recordId, $fileName, $fileInfo['size'], $fileInfo['mime'], $relativePath, $adminUserId]);
}

function workflowBatchReset(PDO $pdo): void
{
    $generatedIds = array_map('intval', $pdo->query(
        "SELECT id FROM documents WHERE subject_matter LIKE '" . WORKFLOW_BATCH_MARKER . "%'"
    )->fetchAll(PDO::FETCH_COLUMN));
    $allDocumentIds = array_values(array_unique(array_merge($generatedIds, [WORKFLOW_BATCH_SAMPLE_ID])));
    $sampleMarkerStmt = $pdo->prepare("
        SELECT COUNT(*) FROM document_events
        WHERE document_id = ? AND remarks LIKE ?
    ");
    $sampleMarkerStmt->execute([WORKFLOW_BATCH_SAMPLE_ID, WORKFLOW_BATCH_MARKER . '%']);
    $sampleChanged = (int) $sampleMarkerStmt->fetchColumn() > 0;
    $archiveIds = array_map('intval', $pdo->query(
        "SELECT id FROM archived_records WHERE approved_no LIKE '" . WORKFLOW_BATCH_MARKER . "-ARCH-%'"
    )->fetchAll(PDO::FETCH_COLUMN));

    if (empty($generatedIds) && empty($archiveIds) && !$sampleChanged) {
        echo 'No workflow batch records or Sample 101 changes found.' . PHP_EOL;
        return;
    }

    $docPlaceholders = implode(',', array_fill(0, count($allDocumentIds), '?'));
    $filePaths = [];
    if (!empty($generatedIds)) {
        $generatedPlaceholders = implode(',', array_fill(0, count($generatedIds), '?'));
        foreach ([
            ['document_attachments', 'stored_path'],
            ['plenary_comm_resolution_files', 'file_path'],
            ['plenary_cases_adoption_files', 'file_path'],
        ] as [$table, $column]) {
            $stmt = $pdo->prepare("SELECT {$column} FROM {$table} WHERE document_id IN ({$generatedPlaceholders})");
            $stmt->execute($generatedIds);
            array_push($filePaths, ...$stmt->fetchAll(PDO::FETCH_COLUMN));
        }
    }
    if ($sampleChanged) {
        $sampleFiles = $pdo->prepare("SELECT stored_path FROM document_attachments WHERE document_id = ? AND file_name LIKE ?");
        $sampleFiles->execute([WORKFLOW_BATCH_SAMPLE_ID, WORKFLOW_BATCH_MARKER . '%']);
        array_push($filePaths, ...$sampleFiles->fetchAll(PDO::FETCH_COLUMN));
    }

    $reportStmt = $pdo->prepare("SELECT committee_report_id FROM committee_report_documents WHERE document_id IN ({$docPlaceholders})");
    $reportStmt->execute($allDocumentIds);
    $reportIds = array_map('intval', $reportStmt->fetchAll(PDO::FETCH_COLUMN));
    $caseStmt = $pdo->prepare("SELECT id FROM committee_cases WHERE document_id IN ({$docPlaceholders})");
    $caseStmt->execute($allDocumentIds);
    $caseIds = array_map('intval', $caseStmt->fetchAll(PDO::FETCH_COLUMN));

    $archivePaths = [];
    if (!empty($archiveIds)) {
        $archivePlaceholders = implode(',', array_fill(0, count($archiveIds), '?'));
        $archiveFiles = $pdo->prepare("SELECT file_path FROM archived_records_attachments WHERE archived_record_id IN ({$archivePlaceholders})");
        $archiveFiles->execute($archiveIds);
        $archivePaths = $archiveFiles->fetchAll(PDO::FETCH_COLUMN);
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM motion_reconsideration_links WHERE mfr_document_id IN ({$docPlaceholders}) OR referenced_document_id IN ({$docPlaceholders})")
            ->execute(array_merge($allDocumentIds, $allDocumentIds));
        $pdo->prepare("DELETE FROM document_filing_attachments WHERE filing_id IN (SELECT id FROM document_filings WHERE document_id IN ({$docPlaceholders}))")
            ->execute($allDocumentIds);
        $pdo->prepare("DELETE FROM document_filings WHERE document_id IN ({$docPlaceholders})")
            ->execute($allDocumentIds);

        foreach ([
            'plenary_second_reading_details', 'plenary_readings',
            'plenary_comm_resolution_files', 'plenary_comm_adoptions', 'plenary_comm_first_reading',
            'plenary_cases_adoption_files', 'plenary_cases_adoptions', 'plenary_cases_first_reading',
            'committee_report_documents', 'document_approvals', 'document_authors', 'document_committees',
            'document_checklist_items', 'plenary_document_details',
        ] as $table) {
            $pdo->prepare("DELETE FROM {$table} WHERE document_id IN ({$docPlaceholders})")
                ->execute($allDocumentIds);
        }

        if (!empty($caseIds)) {
            $casePlaceholders = implode(',', array_fill(0, count($caseIds), '?'));
            $pdo->prepare("DELETE FROM committee_case_action_attachments WHERE action_id IN (SELECT id FROM committee_case_actions WHERE case_id IN ({$casePlaceholders}))")
                ->execute($caseIds);
            $pdo->prepare("DELETE FROM committee_case_actions WHERE case_id IN ({$casePlaceholders})")
                ->execute($caseIds);
            $pdo->prepare("DELETE FROM committee_cases WHERE id IN ({$casePlaceholders})")
                ->execute($caseIds);
        }
        if (!empty($reportIds)) {
            $reportPlaceholders = implode(',', array_fill(0, count($reportIds), '?'));
            $pdo->prepare("DELETE FROM committee_report_committees WHERE committee_report_id IN ({$reportPlaceholders})")
                ->execute($reportIds);
            $pdo->prepare("DELETE FROM committee_reports WHERE id IN ({$reportPlaceholders})")
                ->execute($reportIds);
        }

        if (!empty($generatedIds)) {
            $generatedPlaceholders = implode(',', array_fill(0, count($generatedIds), '?'));
            foreach ([
                'document_routes', 'document_revisions', 'document_events', 'document_assignments', 'document_attachments',
            ] as $table) {
                $pdo->prepare("DELETE FROM {$table} WHERE document_id IN ({$generatedPlaceholders})")
                    ->execute($generatedIds);
            }
            $pdo->prepare("DELETE FROM documents WHERE id IN ({$generatedPlaceholders})")
                ->execute($generatedIds);
        }

        if ($sampleChanged) {
            $pdo->prepare('DELETE FROM document_routes WHERE document_id = ? AND remarks LIKE ?')
                ->execute([WORKFLOW_BATCH_SAMPLE_ID, WORKFLOW_BATCH_MARKER . '%']);
            $pdo->prepare('DELETE FROM document_revisions WHERE document_id = ? AND change_reason LIKE ?')
                ->execute([WORKFLOW_BATCH_SAMPLE_ID, WORKFLOW_BATCH_MARKER . '%']);
            $pdo->prepare('DELETE FROM document_events WHERE document_id = ? AND remarks LIKE ?')
                ->execute([WORKFLOW_BATCH_SAMPLE_ID, WORKFLOW_BATCH_MARKER . '%']);
            $pdo->prepare('DELETE FROM document_attachments WHERE document_id = ? AND file_name LIKE ?')
                ->execute([WORKFLOW_BATCH_SAMPLE_ID, WORKFLOW_BATCH_MARKER . '%']);
            $pdo->prepare("DELETE FROM document_assignments WHERE document_id = ? AND phase = 'PLENARY'")
                ->execute([WORKFLOW_BATCH_SAMPLE_ID]);
            $pdo->prepare("
                UPDATE document_assignments
                SET decision = 'PENDING', accepted_at = NULL, accepted_by = NULL,
                    assigned_to_user_id = NULL, completed_at = NULL, remarks = NULL
                WHERE document_id = ? AND phase = 'ADMIN'
                ORDER BY id ASC LIMIT 1
            ")->execute([WORKFLOW_BATCH_SAMPLE_ID]);
            $pendingId = workflowBatchId($pdo, 'document_statuses', 'Pending');
            $pdo->prepare("
                UPDATE documents
                SET current_phase = 'ADMIN', current_status_id = ?, current_owner_user_id = NULL,
                    finalized_at = NULL, filed_at = NULL, updated_by = created_by
                WHERE id = ?
            ")->execute([$pendingId, WORKFLOW_BATCH_SAMPLE_ID]);
        }

        if (!empty($archiveIds)) {
            $archivePlaceholders = implode(',', array_fill(0, count($archiveIds), '?'));
            foreach (['archived_records_attachments', 'archived_records_authors', 'archived_records_committees'] as $table) {
                $pdo->prepare("DELETE FROM {$table} WHERE archived_record_id IN ({$archivePlaceholders})")
                    ->execute($archiveIds);
            }
            $pdo->prepare("DELETE FROM archived_records WHERE id IN ({$archivePlaceholders})")
                ->execute($archiveIds);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        workflowBatchFail('Batch reset failed without committing database changes: ' . $e->getMessage());
    }

    foreach (array_merge($filePaths, $archivePaths) as $relativePath) {
        if (str_starts_with($relativePath, 'uploads/documents/')) {
            $absolutePath = PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        } elseif (str_starts_with($relativePath, 'archived_records/')) {
            $absolutePath = STORAGE_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        } else {
            continue;
        }
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    echo 'Removed ' . count($generatedIds) . ' generated workflow documents and ' . count($archiveIds)
        . ' archived records. Sample 101 was restored to Pending/Admin; original receipt history was retained.' . PHP_EOL;
}

$pdo = (new Database())->connect();
$databaseName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if ($databaseName !== 'pangasinan_lis' || !in_array('--confirm-local-fixtures', $argv, true)) {
    workflowBatchFail('Refusing to run unless connected to pangasinan_lis and --confirm-local-fixtures is supplied.');
}
if (in_array('--reset-batch', $argv, true)) {
    workflowBatchReset($pdo);
    exit(0);
}
if (!in_array('--run', $argv, true)) {
    if (!in_array('--dry-run', $argv, true)) {
        workflowBatchFail('Usage: php tests/workflow_route_batch.php --dry-run|--run|--reset-batch --confirm-local-fixtures');
    }
}

$existingBatchCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM documents WHERE subject_matter LIKE '" . WORKFLOW_BATCH_MARKER . "%'
")->fetchColumn();
$existingArchiveCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM archived_records WHERE approved_no LIKE '" . WORKFLOW_BATCH_MARKER . "-ARCH-%'"
)->fetchColumn();
if ($existingBatchCount > 0 || $existingArchiveCount > 0) {
    workflowBatchFail('This fixture batch already exists; refusing to create duplicate documents or archived records.');
}

$sampleStmt = $pdo->prepare("
    SELECT d.id, d.document_type_id, d.current_phase, ds.name AS status_name
    FROM documents d
    LEFT JOIN document_statuses ds ON ds.id = d.current_status_id
    WHERE d.id = ? AND d.subject_matter = 'SAMPLE 101'
    LIMIT 1
");
$sampleStmt->execute([WORKFLOW_BATCH_SAMPLE_ID]);
$sample = $sampleStmt->fetch();
if (!$sample || (int) $sample['document_type_id'] !== 1 || $sample['current_phase'] !== 'ADMIN' || $sample['status_name'] !== 'Pending') {
    workflowBatchFail('Expected SAMPLE 101 (document 109, Provincial Tax Ordinance, Pending/Admin) was not found in its original state.');
}
if ((int) $pdo->query('SELECT COUNT(*) FROM documents')->fetchColumn() !== 1
    || (int) $pdo->query('SELECT COUNT(*) FROM archived_records')->fetchColumn() !== 0
) {
    workflowBatchFail('Database baseline changed; expected one document and zero archived records. No fixtures were created.');
}

$roleIds = [
    'Admin' => workflowBatchId($pdo, 'roles', 'Admin'),
    'Receiving Staff' => workflowBatchId($pdo, 'roles', 'Receiving Staff'),
    'Plenary' => workflowBatchId($pdo, 'roles', 'Plenary'),
    'Committee' => workflowBatchId($pdo, 'roles', 'Committee'),
];
$users = [
    'Admin' => workflowBatchUserId($pdo, 'Admin'),
    'Receiving Staff' => workflowBatchUserId($pdo, 'Receiving Staff'),
    'Plenary' => workflowBatchUserId($pdo, 'Plenary'),
    'Committee' => workflowBatchUserId($pdo, 'Committee'),
];
$typeRows = $pdo->query('SELECT id, name FROM document_types WHERE is_active = 1 AND is_deleted = 0 ORDER BY sort_order')->fetchAll();
if (count($typeRows) !== 14) {
    workflowBatchFail('Expected 14 active document types; found ' . count($typeRows) . '.');
}

$statuses = [];
foreach ([
    'Pending', 'For Encoding', 'Awaiting First Reading', 'Awaiting Second Reading',
    'Second Reading Approved', 'Plenary Comm: Finalized', 'Plenary Cases: Referred to Committee',
    'Plenary Cases: Finalized', 'Hearing Completed', 'Committee Report Created', 'Returned to Plenary',
] as $statusName) {
    $statuses[$statusName] = workflowBatchId($pdo, 'document_statuses', $statusName);
}
$filedStatusStmt = $pdo->query("SELECT id FROM document_statuses WHERE name = 'Filed' AND is_active = 1 AND is_deleted = 0 LIMIT 1");
$statuses['Filed'] = (int) $filedStatusStmt->fetchColumn();
$committeeId = workflowBatchId($pdo, 'committees', (string) $pdo->query(
    'SELECT name FROM committees WHERE is_active = 1 AND is_deleted = 0 ORDER BY id LIMIT 1'
)->fetchColumn());
$sourceTypeId = (int) $pdo->query('SELECT source_type_id FROM documents WHERE id = ' . WORKFLOW_BATCH_SAMPLE_ID)->fetchColumn();
if ($sourceTypeId <= 0) {
    workflowBatchFail('Sample document has no valid source type.');
}

if (in_array('--dry-run', $argv, true)) {
    echo "DRY RUN OK: database={$databaseName}, document_types=" . count($typeRows)
        . ', sample_id=' . WORKFLOW_BATCH_SAMPLE_ID . ', expected_filed=56, expected_archived=5' . PHP_EOL;
    echo 'All required roles, status rows, sample state, and empty-batch checks passed. No records or files were created.' . PHP_EOL;
    exit(0);
}

$service = new DocumentService($pdo);
$today = date('Y-m-d');
$filedDocumentIds = [];
$pendingCaseFilingIds = [];
$finalizedCaseIds = [];
$docketSequence = (int) $pdo->query('SELECT COALESCE(MAX(docket_sequence), 0) FROM committee_cases WHERE docket_year = ' . (int) date('Y'))->fetchColumn();
$batchIndexByType = [];

foreach ($typeRows as $typeRow) {
    $documentTypeId = (int) $typeRow['id'];
    $documentTypeName = (string) $typeRow['name'];
    $lowerTypeName = strtolower($documentTypeName);
    $isCase = str_contains($lowerTypeName, 'administrative case') || str_contains($lowerTypeName, 'complaint');
    $isCommunicationFlow = str_contains($lowerTypeName, 'communication') || str_contains($lowerTypeName, 'motion for reconsideration');

    for ($index = 1; $index <= 4; $index++) {
        if ($documentTypeId === 1 && $index === 1) {
            $documentId = WORKFLOW_BATCH_SAMPLE_ID;
            $trackingNumber = 'TRK-2026-00001';
        } else {
            $referenceDocumentId = null;
            if (str_contains($lowerTypeName, 'motion for reconsideration')) {
                if (empty($finalizedCaseIds)) {
                    workflowBatchFail('No finalized Complaint/Administrative Cases document is available for MFR references.');
                }
                $referenceDocumentId = $finalizedCaseIds[($index - 1) % count($finalizedCaseIds)];
            }
            $newDocument = workflowBatchCreateDocument(
                $pdo, $service, $users['Receiving Staff'], $sourceTypeId,
                $documentTypeId, $documentTypeName, $index, $referenceDocumentId
            );
            $documentId = $newDocument['id'];
            $trackingNumber = $newDocument['tracking_number'];
        }

        workflowBatchRouteToPlenary($pdo, $documentId, $users['Admin'], $roleIds['Plenary']);
        workflowBatchCreatePddAndProcess(
            $pdo, $documentId, $users['Plenary'], $statuses['For Encoding'],
            $statuses['Awaiting First Reading'], $documentTypeId, $documentTypeName, $index
        );

        if ($isCase) {
            $firstReadingStmt = $pdo->prepare("
                INSERT INTO plenary_cases_first_reading
                    (document_id, decision, reading_date, remarks, performed_by)
                VALUES (?, 'REFER_TO_COMMITTEE', ?, ?, ?)
            ");
            $firstReadingStmt->execute([
                $documentId, $today, WORKFLOW_BATCH_MARKER . ' Referred to Committee.', $users['Plenary'],
            ]);
            $firstReadingId = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO document_committees (document_id, committee_id, assigned_by) VALUES (?, ?, ?)')
                ->execute([$documentId, $committeeId, $users['Plenary']]);
            $pdo->prepare("UPDATE documents SET current_phase = 'COMMITTEE', current_status_id = ?, updated_by = ? WHERE id = ?")
                ->execute([$statuses['Plenary Cases: Referred to Committee'], $users['Plenary'], $documentId]);
            $pdo->prepare('UPDATE plenary_document_details SET status_id = ?, updated_by = ? WHERE document_id = ?')
                ->execute([$statuses['Plenary Cases: Referred to Committee'], $users['Plenary'], $documentId]);
            $pdo->prepare("
                INSERT INTO document_assignments
                    (document_id, assigned_to_role_id, phase, assigned_by, decision, received_at)
                VALUES (?, ?, 'COMMITTEE', ?, 'PENDING', NOW())
            ")->execute([$documentId, $roleIds['Committee'], $users['Plenary']]);
            $pdo->prepare("
                INSERT INTO document_routes (document_id, from_phase, to_phase, routed_by, routed_to_role_id, remarks)
                VALUES (?, 'PLENARY', 'COMMITTEE', ?, ?, ?)
            ")->execute([$documentId, $users['Plenary'], $roleIds['Committee'], WORKFLOW_BATCH_MARKER . ' Plenary to Committee.']);
            workflowBatchEvent($pdo, $documentId, 'PLENARY_CASES_FIRST_READING_REFERRED', 'COMMITTEE', $users['Plenary'],
                $statuses['Awaiting First Reading'], $statuses['Plenary Cases: Referred to Committee'],
                WORKFLOW_BATCH_MARKER . ' Case referred at First Reading.', ['first_reading_id' => $firstReadingId]);

            $docketSequence++;
            $docketNumber = sprintf('WF-%d-%05d', (int) date('Y'), $docketSequence);
            $pdo->prepare("
                INSERT INTO committee_cases
                    (document_id, docket_year, docket_sequence, docket_number, date_assigned,
                     nature_of_case, complainant_details, complainant_municipality, respondents, assigned_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, ?)
            ")->execute([
                $documentId, (int) date('Y'), $docketSequence, $docketNumber, $today,
                WORKFLOW_BATCH_MARKER . ' Committee case review.',
                WORKFLOW_BATCH_MARKER . ' Test complainant.',
                WORKFLOW_BATCH_MARKER . ' Test respondent.', $users['Committee'],
            ]);
            $caseId = (int) $pdo->lastInsertId();
            $pdo->prepare("
                INSERT INTO committee_case_actions (case_id, action_type, action_date, description, notes, created_by)
                VALUES (?, 'WORKFLOW TEST HEARING', ?, ?, ?, ?)
            ")->execute([
                $caseId, $today, WORKFLOW_BATCH_MARKER . ' Committee hearing completed.',
                WORKFLOW_BATCH_MARKER . ' Test action.', $users['Committee'],
            ]);
            $committeeAssignment = $pdo->prepare("
                SELECT id FROM document_assignments
                WHERE document_id = ? AND phase = 'COMMITTEE' AND decision = 'PENDING' AND completed_at IS NULL
                ORDER BY id DESC LIMIT 1
            ");
            $committeeAssignment->execute([$documentId]);
            $committeeAssignmentId = (int) $committeeAssignment->fetchColumn();
            $pdo->prepare("
                UPDATE document_assignments
                SET decision = 'COMPLETED', accepted_at = NOW(), accepted_by = ?,
                    assigned_to_user_id = ?, completed_at = NOW()
                WHERE id = ?
            ")->execute([$users['Committee'], $users['Committee'], $committeeAssignmentId]);
            $pdo->prepare("
                UPDATE committee_cases
                SET final_outcome = 'APPROVED', final_outcome_remarks = ?, finalized_by = ?, finalized_at = NOW()
                WHERE id = ?
            ")->execute([WORKFLOW_BATCH_MARKER . ' Committee approved the test case.', $users['Committee'], $caseId]);
            $pdo->prepare('UPDATE documents SET current_status_id = ?, updated_by = ? WHERE id = ?')
                ->execute([$statuses['Hearing Completed'], $users['Committee'], $documentId]);
            workflowBatchEvent($pdo, $documentId, 'COMMITTEE_CASE_APPROVED', 'COMMITTEE', $users['Committee'],
                $statuses['Plenary Cases: Referred to Committee'], $statuses['Hearing Completed'],
                WORKFLOW_BATCH_MARKER . ' Committee case outcome approved.', ['case_id' => $caseId]);

            $pdo->prepare("
                INSERT INTO committee_reports (report_type, report_number, summary_of_findings, created_by)
                VALUES ('COMMITTEE_REPORT', ?, ?, ?)
            ")->execute([
                $docketNumber, WORKFLOW_BATCH_MARKER . ' Committee report for ' . $trackingNumber, $users['Committee'],
            ]);
            $reportId = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO committee_report_documents (committee_report_id, document_id) VALUES (?, ?)')
                ->execute([$reportId, $documentId]);
            $pdo->prepare('INSERT INTO committee_report_committees (committee_report_id, committee_id) VALUES (?, ?)')
                ->execute([$reportId, $committeeId]);
            $pdo->prepare('UPDATE documents SET current_status_id = ?, updated_by = ? WHERE id = ?')
                ->execute([$statuses['Committee Report Created'], $users['Committee'], $documentId]);
            workflowBatchEvent($pdo, $documentId, 'COMMITTEE_REPORT_CREATED', 'COMMITTEE', $users['Committee'],
                $statuses['Hearing Completed'], $statuses['Committee Report Created'],
                WORKFLOW_BATCH_MARKER . ' Committee report created.', ['report_id' => $reportId]);

            $pdo->prepare('UPDATE committee_reports SET returned_to_plenary_by = ?, returned_to_plenary_at = NOW() WHERE id = ?')
                ->execute([$users['Committee'], $reportId]);
            $pdo->prepare('UPDATE documents SET current_status_id = ?, updated_by = ? WHERE id = ?')
                ->execute([$statuses['Returned to Plenary'], $users['Committee'], $documentId]);
            $pdo->prepare("
                INSERT INTO document_routes (document_id, from_phase, to_phase, routed_by, routed_to_role_id, remarks)
                VALUES (?, 'COMMITTEE', 'PLENARY', ?, ?, ?)
            ")->execute([$documentId, $users['Committee'], $roleIds['Plenary'], WORKFLOW_BATCH_MARKER . ' Committee returned report.']);
            workflowBatchEvent($pdo, $documentId, 'RETURNED_TO_PLENARY', 'COMMITTEE', $users['Committee'],
                $statuses['Committee Report Created'], $statuses['Returned to Plenary'],
                WORKFLOW_BATCH_MARKER . ' Committee report returned to Plenary.', ['report_id' => $reportId]);

            $pdo->prepare("
                INSERT INTO plenary_cases_adoptions
                    (document_id, first_reading_id, resolution_number, proposed_number, adopted_by, remarks)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([
                $documentId, $firstReadingId, $docketNumber,
                WORKFLOW_BATCH_MARKER . '-' . $documentTypeId . '-' . $index,
                $users['Plenary'], WORKFLOW_BATCH_MARKER . ' Adopted after Committee return.',
            ]);
            $pdo->prepare("UPDATE documents SET current_phase = 'FINALIZED', current_status_id = ?, finalized_at = NOW(), updated_by = ? WHERE id = ?")
                ->execute([$statuses['Plenary Cases: Finalized'], $users['Plenary'], $documentId]);
            $pdo->prepare('UPDATE plenary_document_details SET status_id = ?, updated_by = ? WHERE document_id = ?')
                ->execute([$statuses['Plenary Cases: Finalized'], $users['Plenary'], $documentId]);
            workflowBatchEvent($pdo, $documentId, 'PLENARY_CASES_ADOPTED', 'FINALIZED', $users['Plenary'],
                $statuses['Returned to Plenary'], $statuses['Plenary Cases: Finalized'],
                WORKFLOW_BATCH_MARKER . ' Plenary adopted the Committee report.');
            $finalizedCaseIds[] = $documentId;
        } elseif ($isCommunicationFlow) {
            $resolutionNumber = WORKFLOW_BATCH_MARKER . '-RES-' . $documentTypeId . '-' . $index;
            $pdo->prepare("
                INSERT INTO plenary_comm_first_reading
                    (document_id, decision, reading_date, remarks, resolution_number, performed_by, finalized_at, finalized_by)
                VALUES (?, 'APPROVED', ?, ?, ?, ?, NOW(), ?)
            ")->execute([
                $documentId, $today, WORKFLOW_BATCH_MARKER . ' Approved for resolution.',
                $resolutionNumber, $users['Plenary'], $users['Plenary'],
            ]);
            $firstReadingId = (int) $pdo->lastInsertId();
            $resolutionFileName = WORKFLOW_BATCH_MARKER . '-resolution-' . $documentId . '.pdf';
            $resolutionPath = 'uploads/documents/' . $documentId . '/' . $resolutionFileName;
            $resolutionInfo = workflowBatchPdf(PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $resolutionPath),
                $resolutionNumber . ' resolution attachment');
            $pdo->prepare("
                INSERT INTO plenary_comm_resolution_files
                    (document_id, first_reading_id, original_filename, stored_filename, file_path,
                     mime_type, file_size, uploaded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $documentId, $firstReadingId, $resolutionFileName, $resolutionFileName,
                $resolutionPath, $resolutionInfo['mime'], $resolutionInfo['size'], $users['Plenary'],
            ]);
            $pdo->prepare("UPDATE documents SET current_phase = 'FINALIZED', current_status_id = ?, finalized_at = NOW(), updated_by = ? WHERE id = ?")
                ->execute([$statuses['Plenary Comm: Finalized'], $users['Plenary'], $documentId]);
            $pdo->prepare('UPDATE plenary_document_details SET status_id = ?, updated_by = ? WHERE document_id = ?')
                ->execute([$statuses['Plenary Comm: Finalized'], $users['Plenary'], $documentId]);
            workflowBatchEvent($pdo, $documentId, 'PLENARY_COMM_FIRST_READING_APPROVED', 'FINALIZED', $users['Plenary'],
                $statuses['Awaiting First Reading'], $statuses['Plenary Comm: Finalized'],
                WORKFLOW_BATCH_MARKER . ' Communication/MFR approved at First Reading.',
                ['decision' => 'APPROVED', 'resolution_number' => $resolutionNumber, 'first_reading_id' => $firstReadingId]);
            workflowBatchEvent($pdo, $documentId, 'PLENARY_COMM_FINALIZED', 'FINALIZED', $users['Plenary'],
                $statuses['Awaiting First Reading'], $statuses['Plenary Comm: Finalized'],
                WORKFLOW_BATCH_MARKER . ' Communication/MFR finalized.');
        } else {
            $pdo->prepare("
                INSERT INTO plenary_readings (document_id, reading_number, action, reading_date, remarks, performed_by)
                VALUES (?, 1, 'ADOPTED_SUSPENDED_RULES', ?, ?, ?)
            ")->execute([$documentId, $today, WORKFLOW_BATCH_MARKER . ' Adopted under suspended rules.', $users['Plenary']]);
            $firstReadingId = (int) $pdo->lastInsertId();
            $pdo->prepare('UPDATE documents SET current_status_id = ?, updated_by = ? WHERE id = ?')
                ->execute([$statuses['Awaiting Second Reading'], $users['Plenary'], $documentId]);
            workflowBatchEvent($pdo, $documentId, 'PLENARY_FIRST_READING_COMPLETED', 'PLENARY', $users['Plenary'],
                $statuses['Awaiting First Reading'], $statuses['Awaiting Second Reading'],
                WORKFLOW_BATCH_MARKER . ' Adopted under suspended rules.',
                ['action' => 'ADOPTED_SUSPENDED_RULES', 'reading_id' => $firstReadingId]);

            $approvedNumber = WORKFLOW_BATCH_MARKER . '-APP-' . $documentTypeId . '-' . $index;
            $pdo->prepare("
                INSERT INTO plenary_readings (document_id, reading_number, action, reading_date, remarks, performed_by)
                VALUES (?, 2, 'APPROVED', ?, ?, ?)
            ")->execute([$documentId, $today, WORKFLOW_BATCH_MARKER . ' Approved on Second Reading.', $users['Plenary']]);
            $secondReadingId = (int) $pdo->lastInsertId();
            $pdo->prepare("UPDATE documents SET current_phase = 'FINALIZED', current_status_id = ?, finalized_at = NOW(), updated_by = ? WHERE id = ?")
                ->execute([$statuses['Second Reading Approved'], $users['Plenary'], $documentId]);
            $pdo->prepare("
                INSERT INTO plenary_second_reading_details
                    (reading_id, document_id, approved_no, date_finalized, approved_subject_matter, created_by, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $secondReadingId, $documentId, $approvedNumber, $today,
                $documentTypeName . ' - ' . $trackingNumber, $users['Plenary'], $users['Plenary'],
            ]);
            $pdo->prepare("
                INSERT INTO document_approvals (document_id, approved_number, date_approved, approved_by)
                VALUES (?, ?, ?, ?)
            ")->execute([$documentId, $approvedNumber, $today, $users['Plenary']]);
            workflowBatchEvent($pdo, $documentId, 'PLENARY_SECOND_READING_APPROVED', 'FINALIZED', $users['Plenary'],
                $statuses['Awaiting Second Reading'], $statuses['Second Reading Approved'],
                WORKFLOW_BATCH_MARKER . ' Approved on Second Reading.',
                ['approved_no' => $approvedNumber, 'reading_id' => $secondReadingId]);
            workflowBatchEvent($pdo, $documentId, 'PLENARY_FINALIZED', 'FINALIZED', $users['Plenary'],
                $statuses['Awaiting Second Reading'], $statuses['Second Reading Approved'],
                WORKFLOW_BATCH_MARKER . ' Legislative document finalized.');
        }

        if ($isCase) {
            $pendingCaseFilingIds[] = $documentId;
        } else {
            workflowBatchFileDocument($pdo, $documentId, $users['Admin'], $statuses['Filed']);
        }
        $filedDocumentIds[] = $documentId;
        $batchIndexByType[$documentTypeId] = ($batchIndexByType[$documentTypeId] ?? 0) + 1;
        echo "FILED: {$documentTypeName} | {$trackingNumber} | document_id={$documentId}" . PHP_EOL;
    }
}

foreach ($pendingCaseFilingIds as $documentId) {
    workflowBatchFileDocument($pdo, $documentId, $users['Admin'], $statuses['Filed']);
}

foreach (array_slice($typeRows, 0, 5) as $index => $typeRow) {
    workflowBatchCreateArchivedRecord($pdo, $users['Admin'], (int) $typeRow['id'], $index + 1);
}

$filedCount = (int) $pdo->query("SELECT COUNT(*) FROM documents WHERE current_phase = 'FILED'")->fetchColumn();
$archiveCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM archived_records WHERE approved_no LIKE '" . WORKFLOW_BATCH_MARKER . "-ARCH-%'"
)->fetchColumn();
$typeCounts = $pdo->query("
    SELECT dt.name, COUNT(d.id) AS filed_count
    FROM document_types dt
    LEFT JOIN documents d ON d.document_type_id = dt.id AND d.current_phase = 'FILED'
    GROUP BY dt.id, dt.name, dt.sort_order
    ORDER BY dt.sort_order
")->fetchAll();

if (count($filedDocumentIds) !== 56 || $filedCount !== 56 || $archiveCount !== 5) {
    workflowBatchFail("Final count check failed: this run filed " . count($filedDocumentIds)
        . ", database has {$filedCount} filed documents, and batch has {$archiveCount} archives.");
}
foreach ($typeCounts as $row) {
    if ((int) $row['filed_count'] !== 4) {
        workflowBatchFail("Expected exactly four Filed documents for {$row['name']}; found {$row['filed_count']}.");
    }
}

echo PHP_EOL . 'BATCH COMPLETE' . PHP_EOL;
echo 'Database: ' . $databaseName . PHP_EOL;
echo 'Filed workflow documents: ' . count($filedDocumentIds) . PHP_EOL;
echo 'Archived records: ' . $archiveCount . PHP_EOL;
echo 'Existing SAMPLE 101 was routed as one of the four Provincial Tax Ordinance documents.' . PHP_EOL;
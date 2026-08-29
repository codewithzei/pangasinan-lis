<?php

require_once __DIR__ . '/../../config/database.php';

class ChecklistDocumentTypeController
{
    protected PDO $pdo;

    public function __construct()
    {
        $database = new Database();
        $this->pdo = $database->connect();
    }

    public function assign(): void
    {
        $checklistId = (int)($_POST['checklist_id'] ?? 0);
        if ($checklistId <= 0) {
            flash_set('error', 'Invalid checklist ID.');
            redirect('master/checklists/show?id=' . $checklistId);
        }

        $documentTypeIds = $_POST['document_type_ids'] ?? [];

        if (empty($documentTypeIds)) {
            flash_set('error', 'Please select at least one document type to assign.');
            redirect('master/checklists/show?id=' . $checklistId);
        }

        $userId = auth_id();

        try {
            $this->pdo->beginTransaction();
            
            // Validate that all document type IDs exist and are active
            $placeholders = implode(',', array_fill(0, count($documentTypeIds), '?'));
            $validateStmt = $this->pdo->prepare("SELECT id FROM document_types WHERE id IN ({$placeholders}) AND is_deleted = 0 AND is_active = 1");
            $validateStmt->execute($documentTypeIds);
            $validIds = $validateStmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($validIds) !== count($documentTypeIds)) {
                $this->pdo->rollBack();
                flash_set('error', 'One or more selected document types are invalid or inactive.');
                redirect('master/checklists/show?id=' . $checklistId);
            }
            
            $assignStmt = $this->pdo->prepare("INSERT IGNORE INTO checklist_document_types (checklist_id, document_type_id, is_required, created_by, updated_by) VALUES (?, ?, 1, ?, ?)");
            $count = 0;
            foreach ($documentTypeIds as $dtId) {
                $dtId = (int)$dtId;
                if ($dtId > 0) {
                    $assignStmt->execute([$checklistId, $dtId, $userId, $userId]);
                    if ($assignStmt->rowCount() > 0) $count++;
                }
            }
            $this->pdo->commit();

            audit_log('UPDATE', 'Checklist', (string)$checklistId, null, ['assigned_count' => $count, 'document_type_ids' => $documentTypeIds], "Assigned {$count} document type(s) to checklist");
            system_log('INFO', "Assigned {$count} document types to checklist {$checklistId}");
            flash_set('success', "Assigned {$count} document type(s) successfully.");
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            system_log('ERROR', 'Checklist assign document types failed', ['error' => $e->getMessage(), 'checklist_id' => $checklistId]);
            flash_set('error', 'Failed to assign document types: ' . $e->getMessage());
        }

        redirect('master/checklists/show?id=' . $checklistId);
    }

    public function remove(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $checklistId = (int)($_POST['checklist_id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid assignment ID.']);
            exit;
        }

        $oldStmt = $this->pdo->prepare("SELECT cdt.*, dt.name as document_type_name 
            FROM checklist_document_types cdt 
            LEFT JOIN document_types dt ON dt.id = cdt.document_type_id 
            WHERE cdt.id = ? LIMIT 1");
        $oldStmt->execute([$id]);
        $assignment = $oldStmt->fetch();

        $stmt = $this->pdo->prepare("DELETE FROM checklist_document_types WHERE id = ?");
        $result = $stmt->execute([$id]);

        header('Content-Type: application/json');
        if ($result) {
            $documentTypeName = $assignment['document_type_name'] ?? 'Document Type';
            $cid = $assignment['checklist_id'] ?? $checklistId;
            audit_log('UPDATE', 'ChecklistDocumentType', (string)$id, $assignment ?: null, null, "Removed document type {$documentTypeName} from checklist assignment");
            if ($cid) {
                audit_log('UPDATE', 'Checklist', (string)$cid, null, ['removed_document_type' => $documentTypeName], "Removed document type {$documentTypeName} from checklist");
            }
            system_log('INFO', "Removed document type from checklist_document_types assignment ID: {$id}");
            echo json_encode(['success' => true, 'message' => 'Document type removed successfully.']);
        } else {
            system_log('WARNING', "Failed to remove document type assignment (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to remove document type.']);
        }
        exit;
    }

    public function updateRequired(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid assignment ID.']);
            exit;
        }

        // Get the new required status from the request (if provided)
        // If not provided, toggle the current status (backward compatibility)
        $newRequiredFromRequest = isset($_POST['is_required']) ? (int)$_POST['is_required'] : null;

        $userId = auth_id();

        $stmt = $this->pdo->prepare("SELECT cdt.is_required, dt.name as document_type_name 
            FROM checklist_document_types cdt
            LEFT JOIN document_types dt ON dt.id = cdt.document_type_id
            WHERE cdt.id = ? LIMIT 1");
        $stmt->execute([$id]);
        $assignment = $stmt->fetch();

        if (!$assignment) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Assignment not found.']);
            exit;
        }

        // Use the provided value, or toggle if not provided
        $newRequired = $newRequiredFromRequest !== null ? $newRequiredFromRequest : ($assignment['is_required'] ? 0 : 1);
        $oldStatus = $assignment['is_required'] ? 'Required' : 'Optional';
        $newStatusLabel = $newRequired ? 'Required' : 'Optional';
        
        $stmt = $this->pdo->prepare("UPDATE checklist_document_types SET is_required = ?, updated_by = ? WHERE id = ?");
        $result = $stmt->execute([$newRequired, $userId, $id]);

        $action = $newRequired ? 'marked as required' : 'marked as optional';
        header('Content-Type: application/json');
        if ($result) {
            $docTypeName = $assignment['document_type_name'] ?? 'Document Type';
            audit_log('UPDATE', 'ChecklistDocumentType', (string)$id, ['is_required' => $oldStatus], ['is_required' => $newStatusLabel], "Document type \"{$docTypeName}\" {$action}");
            system_log('INFO', "Document type assignment {$action} (ID: {$id})");
            echo json_encode(['success' => true, 'message' => "Document type has been {$action}."]);
        } else {
            system_log('WARNING', "Failed to update required status (ID: {$id})");
            echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
        }
        exit;
    }
}

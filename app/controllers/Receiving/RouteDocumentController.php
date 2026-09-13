<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/DocumentService.php';

/**
 * RouteDocumentController
 *
 * Handles the Receiving workflow:
 *   GET  receiving/receive-document          → index()
 *   POST receiving/receive-document/submit   → submit()
 *   GET  receiving/receive-document/get-checklists → getChecklistsByDocumentType()
 *
 * All document-persistence logic is delegated to DocumentService so that
 * no transaction, file-upload, or validation code is duplicated here.
 * This eliminates the MySQL lock-wait timeout (error 1205) that occurred
 * when the old processFileUploads() called log_file_upload() — which
 * writes through a second PDO connection — while the main document
 * transaction was still open.
 */
class RouteDocumentController
{
    protected DocumentService $docService;

    public function __construct()
    {
        $this->docService = new DocumentService();
    }

    // =========================================================================
    // GET  receiving/receive-document
    // =========================================================================

    public function index(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        $currentYear           = (int) date('Y');
        $trackingNumberPreview = $this->docService->getTrackingNumberPreview($currentYear);

        $documentTypes   = $this->docService->getDocumentTypes();
        $sourceTypes     = $this->docService->getSourceTypes();
        $externalOffices = $this->docService->getExternalOffices();
        $hospitals       = $this->docService->getHospitals();
        $spMembers       = $this->docService->getSpMembers();
        $municities      = $this->docService->getMunicities();

        $success = flash_get('success');
        $error   = flash_get('error');
        $errors  = flash_get('errors') ?? [];

        $pageTitle    = 'Receive Document';
        $pageSubtitle = 'Receive and route incoming documents to Admin';

        $viewDir = __DIR__ . '/../../../resources/views/receiving/receive-document';
        if (!is_dir($viewDir)) {
            mkdir($viewDir, 0777, true);
        }
        require $viewDir . '/index.php';
    }

    // =========================================================================
    // POST  receiving/receive-document/submit
    // =========================================================================

    public function submit(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in to submit documents.');
            redirect('receiving/receive-document');
        }

        // ------------------------------------------------------------------
        // 1. Collect and sanitise POST fields
        // ------------------------------------------------------------------
        $data = [
            'date_received'         => trim($_POST['date_received']         ?? ''),
            'time_received'         => trim($_POST['time_received']         ?? ''),
            'subject_matter'        => trim($_POST['subject_matter']        ?? ''),
            'document_type_id'      => (int) ($_POST['document_type_id']   ?? 0),
            'source_type_id'        => (int) ($_POST['source_type_id']     ?? 0),
            'remarks'               => trim($_POST['remarks']               ?? ''),
            'external_office_id'    => !empty($_POST['external_office_id'])  ? (int) $_POST['external_office_id']  : null,
            'hospital_id'           => !empty($_POST['hospital_id'])          ? (int) $_POST['hospital_id']          : null,
            'municipality_id'       => !empty($_POST['municipality_id'])      ? (int) $_POST['municipality_id']      : null,
            'sp_member_id'          => !empty($_POST['sp_member_id'])         ? (int) $_POST['sp_member_id']         : null,
            'source_name'           => trim($_POST['source_name']             ?? ''),
            'source_contact_number' => trim($_POST['source_contact_number']   ?? ''),
            'source_address'        => trim($_POST['source_address']          ?? ''),
            'source_liaison_name'   => trim($_POST['source_liaison_name']     ?? ''),
        ];

        // Include checklist items inside $data so DocumentService can persist them.
        if (!empty($_POST['checklist_items']) && is_array($_POST['checklist_items'])) {
            $data['checklist_items'] = array_map('intval', $_POST['checklist_items']);
        }

        // ------------------------------------------------------------------
        // 2. Preserve form values for error repopulation before any redirect
        // ------------------------------------------------------------------
        old_set($_POST);

        // ------------------------------------------------------------------
        // 3. Validate fields
        // ------------------------------------------------------------------
        $errors = $this->docService->validateSubmission($data);

        // ------------------------------------------------------------------
        // 4. Validate file uploads (at least one required)
        // ------------------------------------------------------------------
        $uploadedFiles = $_FILES['attachments'] ?? [];
        $fileErrors    = $this->docService->validateFileUploads($uploadedFiles, true);
        $errors        = array_merge($errors, $fileErrors);

        if (!empty($errors)) {
            flash_set('errors', $errors);
            redirect('receiving/receive-document');
        }

        // ------------------------------------------------------------------
        // 5. Delegate the full atomic transaction to DocumentService
        //
        //    DocumentService::receiveDocument() owns:
        //      - tracking number generation (row-locked)
        //      - document + revision insert
        //      - file staging (filesystem) + attachment rows (DB)
        //      - checklist item persistence
        //      - RECEIVING→ADMIN route record
        //      - Admin inbox assignment
        //      - DOCUMENT_RECEIVED / ROUTED_TO_ADMIN workflow events
        //      - commit
        //      - post-commit: file-upload log flush (separate PDO connection,
        //        must never run inside the main transaction)
        //      - post-commit: Admin user notifications
        //      - post-commit: audit_log + system_log
        //      - rollback + staged-file cleanup on any failure
        // ------------------------------------------------------------------
        try {
            $result = $this->docService->receiveDocument(
                $data,
                $uploadedFiles,
                $userId,
                'ADMIN'
            );

            old_clear();
            flash_set(
                'success',
                "Document {$result['tracking_number']} successfully received and routed to Admin."
            );
            redirect('receiving/receive-document');

        } catch (Throwable $e) {
            system_log('ERROR', 'Document submission failed', [
                'error'      => $e->getMessage(),
                'error_code' => $e->getCode(),
                'user_id'    => $userId,
                'trace'      => $e->getTraceAsString(),
            ]);

            flash_set('error', 'Failed to submit document. Please try again.');
            redirect('receiving/receive-document');
        }
    }

    // =========================================================================
    // GET  receiving/receive-document/get-checklists  (AJAX)
    // =========================================================================

    public function getChecklistsByDocumentType(): void
    {
        header('Content-Type: application/json');

        if (empty($_GET['document_type_id'])) {
            echo json_encode(['success' => false, 'checklists' => []]);
            exit;
        }

        $documentTypeId = (int) $_GET['document_type_id'];

        try {
            $checklists = $this->docService->getChecklistsByDocumentType($documentTypeId);
            echo json_encode(['success' => true, 'checklists' => $checklists]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'checklists' => [], 'error' => 'Failed to fetch checklists']);
        }
        exit;
    }
}

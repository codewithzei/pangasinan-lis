<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/DocumentService.php';

/**
 * AdminReceiveDocumentController
 *
 * Allows an Admin to receive and encode a document directly — identical
 * fields, validation rules, and database writes as the Receiving workflow,
 * but performed by the authenticated Admin user.
 *
 * All heavy lifting is delegated to DocumentService so no logic is
 * duplicated from RouteDocumentController.
 */
class AdminReceiveDocumentController
{
    protected DocumentService $docService;

    public function __construct()
    {
        $this->docService = new DocumentService();
    }

    // =========================================================================
    // GET  admin/receive-document
    // =========================================================================

    public function index(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        $currentYear          = (int) date('Y');
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

        $pageTitle    = 'Receive Document (Admin)';
        $pageSubtitle = 'Directly receive and encode an incoming document';

        require __DIR__ . '/../../../resources/views/admin/receive-document/index.php';
    }

    // =========================================================================
    // POST  admin/receive-document/submit
    // =========================================================================

    public function submit(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        // Collect and sanitise POST fields — identical set to RouteDocumentController
        $data = [
            'date_received'        => trim($_POST['date_received']        ?? ''),
            'time_received'        => trim($_POST['time_received']        ?? ''),
            'subject_matter'       => trim($_POST['subject_matter']       ?? ''),
            'document_type_id'     => (int) ($_POST['document_type_id']  ?? 0),
            'source_type_id'       => (int) ($_POST['source_type_id']    ?? 0),
            'remarks'              => trim($_POST['remarks']              ?? ''),
            'external_office_id'   => !empty($_POST['external_office_id']) ? (int) $_POST['external_office_id'] : null,
            'hospital_id'          => !empty($_POST['hospital_id'])         ? (int) $_POST['hospital_id']         : null,
            'municipality_id'      => !empty($_POST['municipality_id'])     ? (int) $_POST['municipality_id']     : null,
            'sp_member_id'         => !empty($_POST['sp_member_id'])        ? (int) $_POST['sp_member_id']        : null,
            'source_name'          => trim($_POST['source_name']            ?? ''),
            'source_contact_number'=> trim($_POST['source_contact_number']  ?? ''),
            'source_address'       => trim($_POST['source_address']         ?? ''),
            'source_liaison_name'  => trim($_POST['source_liaison_name']    ?? ''),
        ];

        // Collect checklist items (if any)
        if (!empty($_POST['checklist_items']) && is_array($_POST['checklist_items'])) {
            $data['checklist_items'] = array_map('intval', $_POST['checklist_items']);
        }

        // Preserve form values for repopulation on error
        old_set($_POST);

        // Validate fields (reuses the shared DocumentService rules)
        $errors = $this->docService->validateSubmission($data);

        // Validate attachments — same rules as Receiving (at least 1, max 10, 25 MB, allowed types)
        $uploadedFiles = $_FILES['attachments'] ?? [];
        $fileErrors    = $this->docService->validateFileUploads($uploadedFiles, true);
        $errors        = array_merge($errors, $fileErrors);

        if (!empty($errors)) {
            flash_set('errors', $errors);
            redirect('admin/receive-document');
        }

        try {
            $result = $this->docService->receiveDocument($data, $uploadedFiles, $userId, 'ADMIN');

            old_clear();
            flash_set(
                'success',
                "Document {$result['tracking_number']} successfully received and queued in the Admin Inbox."
            );
            redirect('admin/receive-document');

        } catch (Throwable $e) {
            system_log('ERROR', 'Admin direct receive failed', [
                'error'   => $e->getMessage(),
                'user_id' => $userId,
                'trace'   => $e->getTraceAsString(),
            ]);
            flash_set('error', 'Failed to submit document. Please try again.');
            redirect('admin/receive-document');
        }
    }

    // =========================================================================
    // GET  admin/receive-document/get-checklists  (AJAX helper)
    // =========================================================================

    public function getChecklistsByDocumentType(): void
    {
        header('Content-Type: application/json');

        if (!isset($_GET['document_type_id']) || empty($_GET['document_type_id'])) {
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

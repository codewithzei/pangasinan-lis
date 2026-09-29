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
        $routingOptions         = $this->docService->getRoutingOptions();
        $communicationCategories = $this->docService->getCommunicationCategories();

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

        // Routing option (optional — Admin may receive without routing)
        $routingOptionId       = (int) ($_POST['routing_option_id']        ?? 0);
        $communicationCategoryId = (int) ($_POST['communication_category_id'] ?? 0);

        // Preserve form values for repopulation on error
        old_set($_POST);

        // Validate fields (reuses the shared DocumentService rules)
        $errors = $this->docService->validateSubmission($data);

        // Validate routing option (optional, but if provided must be a valid active option id 3–6)
        if ($routingOptionId > 0) {
            $validOptions = array_column($this->docService->getRoutingOptions(), 'id');
            if (!in_array($routingOptionId, array_map('intval', $validOptions), true)) {
                $errors[] = 'Invalid routing option selected.';
            }
        }

        // If Noted (id=6) is selected, communication category is required
        if ($routingOptionId === 6) {
            if ($communicationCategoryId <= 0) {
                $errors[] = 'Communication Category is required when routing option is Noted.';
            } else {
                $validCategories = array_column($this->docService->getCommunicationCategories(), 'id');
                if (!in_array($communicationCategoryId, array_map('intval', $validCategories), true)) {
                    $errors[] = 'Invalid communication category selected.';
                }
            }

            // Verify document type is Communication
            $docType = $this->docService->getDocumentTypes();
            $selectedDocType = null;
            foreach ($docType as $dt) {
                if ((int) $dt['id'] === (int) $data['document_type_id']) {
                    $selectedDocType = $dt;
                    break;
                }
            }
            if (!$selectedDocType || $selectedDocType['name'] !== 'Communication') {
                $errors[] = 'The Noted routing option is only available for Communication documents.';
            }
        }

        // Validate attachments — same rules as Receiving (at least 1, max 10, 25 MB, allowed types)
        $uploadedFiles = $_FILES['attachments'] ?? [];
        $fileErrors    = $this->docService->validateFileUploads($uploadedFiles, true);
        $errors        = array_merge($errors, $fileErrors);

        if (!empty($errors)) {
            flash_set('errors', $errors);
            redirect('admin/receive-document');
        }

        try {
            $result = $this->docService->receiveDocument($data, $uploadedFiles, $userId, 'ADMIN', $routingOptionId, $communicationCategoryId);

            old_clear();
            $routingOptionNames = [3 => 'SP Secretary', 4 => 'Plenary', 5 => 'Committee'];
            if ($routingOptionId === 6) {
                flash_set(
                    'success',
                    "Document {$result['tracking_number']} successfully received and marked as Noted."
                );
            } elseif (isset($routingOptionNames[$routingOptionId])) {
                $destName = $routingOptionNames[$routingOptionId];
                flash_set(
                    'success',
                    "Document {$result['tracking_number']} successfully received and routed directly to {$destName}."
                );
            } else {
                flash_set(
                    'success',
                    "Document {$result['tracking_number']} successfully received and queued in the Admin Inbox."
                );
            }
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

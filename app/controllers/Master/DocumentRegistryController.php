<?php

require_once __DIR__ . '/../../config/database.php';

class DocumentRegistryController
{
    private const RELATED_REGISTRY_TABLES = [
        'plenary_document_details',
        'plenary_second_reading_details',
        'document_authors',
        'plenary_comm_first_reading',
        'plenary_comm_adoptions',
        'plenary_cases_adoptions',
        'committee_cycle_endorsements',
        'committee_endorsement_submissions',
        'agendas',
        'agenda_committees',
        'committee_reports',
        'committee_report_committees',
        'committee_cases',
    ];

    private const RELATED_EDITABLE_FIELDS = [
        'plenary_document_details' => [
            'proposed_number', 'target_first_reading_date', 'session_type',
            'additional_details',
        ],
        'plenary_second_reading_details' => [
            'approved_no', 'date_finalized', 'approved_subject_matter',
        ],
        'document_authors' => ['sp_member_id', 'author_role'],
        'plenary_comm_first_reading' => [
            'proposed_number', 'resolution_number', 'final_output_type', 'approved_subject_matter',
        ],
        'plenary_comm_adoptions' => [
            'proposed_number', 'resolution_number', 'final_output_type',
            'approved_subject_matter', 'adopted_at',
        ],
        'plenary_cases_adoptions' => [
            'proposed_number', 'resolution_number', 'final_output_type',
            'approved_subject_matter', 'adopted_at',
        ],
        'committee_cycle_endorsements' => ['opinion_office_id', 'remarks'],
        'committee_endorsement_submissions' => [
            'opinion_type', 'opinion_file_attachment_id',
            'compliance_file_attachment_id', 'remarks',
        ],
        'agendas' => ['agenda_number', 'agenda_type', 'agenda_date', 'agenda_time', 'venue'],
        'agenda_committees' => ['committee_id'],
        'committee_reports' => [
            'report_type', 'report_number', 'summary_of_findings',
            'comm_resolution_number', 'comm_proposed_number', 'comm_adopted_at',
        ],
        'committee_report_committees' => ['committee_id'],
        'committee_cases' => [
            'docket_number', 'nature_of_case', 'complainant_details',
            'complainant_municipality', 'respondents',
        ],
    ];

    private const RELATED_RECORD_QUERIES = [
        'plenary_document_details' => [
            'load' => 'SELECT * FROM plenary_document_details WHERE document_id = ? ORDER BY id',
            'owner' => 'SELECT id FROM plenary_document_details WHERE id = ? AND document_id = ?',
        ],
        'plenary_readings' => [
            'load' => 'SELECT * FROM plenary_readings WHERE document_id = ? ORDER BY reading_number, id',
            'owner' => 'SELECT id FROM plenary_readings WHERE id = ? AND document_id = ?',
        ],
        'plenary_second_reading_details' => [
            'load' => 'SELECT * FROM plenary_second_reading_details WHERE document_id = ? ORDER BY id',
            'owner' => 'SELECT id FROM plenary_second_reading_details WHERE id = ? AND document_id = ?',
        ],
        'document_authors' => [
            'load' => "SELECT da.*, TRIM(CONCAT_WS(' ', NULLIF(sm.first_name, ''), NULLIF(sm.middle_name, ''), NULLIF(sm.last_name, ''), NULLIF(sm.suffix, ''))) AS member_name FROM document_authors da LEFT JOIN sp_members sm ON sm.sp_member_id = da.sp_member_id WHERE da.document_id = ? ORDER BY da.author_role, da.sort_order, da.id",
            'owner' => 'SELECT id FROM document_authors WHERE id = ? AND document_id = ?',
        ],
        'document_committees' => [
            'load' => 'SELECT * FROM document_committees WHERE document_id = ? ORDER BY id',
            'owner' => 'SELECT id FROM document_committees WHERE id = ? AND document_id = ?',
        ],
        'comm_committee_assignments' => [
            'load' => 'SELECT * FROM comm_committee_assignments WHERE document_id = ? ORDER BY id',
            'owner' => 'SELECT id FROM comm_committee_assignments WHERE id = ? AND document_id = ?',
        ],
        'plenary_comm_first_reading' => [
            'load' => 'SELECT * FROM plenary_comm_first_reading WHERE document_id = ? ORDER BY id',
            'owner' => 'SELECT id FROM plenary_comm_first_reading WHERE id = ? AND document_id = ?',
        ],
        'plenary_cases_first_reading' => [
            'load' => 'SELECT * FROM plenary_cases_first_reading WHERE document_id = ? ORDER BY id',
            'owner' => 'SELECT id FROM plenary_cases_first_reading WHERE id = ? AND document_id = ?',
        ],
        'plenary_comm_adoptions' => [
            'load' => 'SELECT * FROM plenary_comm_adoptions WHERE document_id = ? ORDER BY id',
            'owner' => 'SELECT id FROM plenary_comm_adoptions WHERE id = ? AND document_id = ?',
        ],
        'plenary_cases_adoptions' => [
            'load' => 'SELECT * FROM plenary_cases_adoptions WHERE document_id = ? ORDER BY id',
            'owner' => 'SELECT id FROM plenary_cases_adoptions WHERE id = ? AND document_id = ?',
        ],
        'plenary_committee_report_adoptions' => [
            'load' => 'SELECT * FROM plenary_committee_report_adoptions WHERE document_id = ? ORDER BY id',
            'owner' => 'SELECT id FROM plenary_committee_report_adoptions WHERE id = ? AND document_id = ?',
        ],
        'committee_cycles' => [
            'load' => 'SELECT * FROM committee_cycles WHERE document_id = ? ORDER BY cycle_number, id',
            'owner' => 'SELECT id FROM committee_cycles WHERE id = ? AND document_id = ?',
        ],
        'committee_cycle_committees' => [
            'load' => 'SELECT x.* FROM committee_cycle_committees x INNER JOIN committee_cycles c ON c.id = x.cycle_id WHERE c.document_id = ? ORDER BY x.id',
            'owner' => 'SELECT x.id FROM committee_cycle_committees x INNER JOIN committee_cycles c ON c.id = x.cycle_id WHERE x.id = ? AND c.document_id = ?',
        ],
        'committee_cycle_endorsements' => [
            'load' => 'SELECT e.*, oo.name AS office_name, c.cycle_number FROM committee_cycle_endorsements e INNER JOIN committee_cycles c ON c.id = e.cycle_id LEFT JOIN opinion_offices oo ON oo.id = e.opinion_office_id WHERE c.document_id = ? ORDER BY c.cycle_number, e.endorsement_number, e.id',
            'owner' => 'SELECT e.id FROM committee_cycle_endorsements e INNER JOIN committee_cycles c ON c.id = e.cycle_id WHERE e.id = ? AND c.document_id = ?',
        ],
        'committee_endorsement_submissions' => [
            'load' => 'SELECT s.* FROM committee_endorsement_submissions s INNER JOIN committee_cycle_endorsements e ON e.id = s.endorsement_id INNER JOIN committee_cycles c ON c.id = e.cycle_id WHERE c.document_id = ? ORDER BY s.id',
            'owner' => 'SELECT s.id FROM committee_endorsement_submissions s INNER JOIN committee_cycle_endorsements e ON e.id = s.endorsement_id INNER JOIN committee_cycles c ON c.id = e.cycle_id WHERE s.id = ? AND c.document_id = ?',
        ],
        'committee_opinion_resolutions' => [
            'load' => 'SELECT r.* FROM committee_opinion_resolutions r INNER JOIN committee_cycles c ON c.id = r.cycle_id WHERE c.document_id = ? ORDER BY c.cycle_number, r.id',
            'owner' => 'SELECT r.id FROM committee_opinion_resolutions r INNER JOIN committee_cycles c ON c.id = r.cycle_id WHERE r.id = ? AND c.document_id = ?',
        ],
        'agendas' => [
            'load' => 'SELECT * FROM agendas WHERE document_id = ? ORDER BY agenda_date, agenda_time, id',
            'owner' => 'SELECT id FROM agendas WHERE id = ? AND document_id = ?',
        ],
        'agenda_committees' => [
            'load' => 'SELECT x.*, c.name AS committee_name FROM agenda_committees x INNER JOIN agendas a ON a.id = x.agenda_id LEFT JOIN committees c ON c.id = x.committee_id WHERE a.document_id = ? ORDER BY x.id',
            'owner' => 'SELECT x.id FROM agenda_committees x INNER JOIN agendas a ON a.id = x.agenda_id WHERE x.id = ? AND a.document_id = ?',
        ],
        'agenda_chairpersons' => [
            'load' => 'SELECT x.* FROM agenda_chairpersons x INNER JOIN agendas a ON a.id = x.agenda_id WHERE a.document_id = ? ORDER BY x.id',
            'owner' => 'SELECT x.id FROM agenda_chairpersons x INNER JOIN agendas a ON a.id = x.agenda_id WHERE x.id = ? AND a.document_id = ?',
        ],
        'committee_hearings' => [
            'load' => 'SELECT * FROM committee_hearings WHERE document_id = ? ORDER BY performed_at, id',
            'owner' => 'SELECT id FROM committee_hearings WHERE id = ? AND document_id = ?',
        ],
        'committee_reports' => [
            'load' => 'SELECT cr.* FROM committee_reports cr INNER JOIN committee_report_documents crd ON crd.committee_report_id = cr.id WHERE crd.document_id = ? ORDER BY cr.created_at, cr.id',
            'owner' => 'SELECT cr.id FROM committee_reports cr INNER JOIN committee_report_documents crd ON crd.committee_report_id = cr.id WHERE cr.id = ? AND crd.document_id = ?',
        ],
        'committee_report_committees' => [
            'load' => 'SELECT x.*, c.name AS committee_name FROM committee_report_committees x INNER JOIN committee_report_documents crd ON crd.committee_report_id = x.committee_report_id LEFT JOIN committees c ON c.id = x.committee_id WHERE crd.document_id = ? ORDER BY x.id',
            'owner' => 'SELECT x.id FROM committee_report_committees x INNER JOIN committee_report_documents crd ON crd.committee_report_id = x.committee_report_id WHERE x.id = ? AND crd.document_id = ?',
        ],
        'committee_report_attachments' => [
            'load' => 'SELECT cra.* FROM committee_report_attachments cra INNER JOIN committee_report_documents crd ON crd.committee_report_id = cra.committee_report_id WHERE crd.document_id = ? ORDER BY cra.created_at DESC, cra.id DESC',
            'owner' => 'SELECT cra.id FROM committee_report_attachments cra INNER JOIN committee_report_documents crd ON crd.committee_report_id = cra.committee_report_id WHERE cra.id = ? AND crd.document_id = ?',
        ],
        'committee_report_attachment_transmittals' => [
            'load' => 'SELECT t.* FROM committee_report_attachment_transmittals t INNER JOIN committee_report_documents crd ON crd.committee_report_id = t.committee_report_id WHERE crd.document_id = ? ORDER BY t.returned_at DESC, t.id DESC',
            'owner' => 'SELECT t.id FROM committee_report_attachment_transmittals t INNER JOIN committee_report_documents crd ON crd.committee_report_id = t.committee_report_id WHERE t.id = ? AND crd.document_id = ?',
        ],
        'document_attachments' => [
            'load' => 'SELECT * FROM document_attachments WHERE document_id = ? ORDER BY created_at DESC, id DESC',
            'owner' => 'SELECT id FROM document_attachments WHERE id = ? AND document_id = ?',
        ],
        'committee_cases' => [
            'load' => 'SELECT * FROM committee_cases WHERE document_id = ? ORDER BY id',
            'owner' => 'SELECT id FROM committee_cases WHERE id = ? AND document_id = ?',
        ],
        'committee_case_actions' => [
            'load' => 'SELECT a.* FROM committee_case_actions a INNER JOIN committee_cases c ON c.id = a.case_id WHERE c.document_id = ? ORDER BY a.action_date, a.id',
            'owner' => 'SELECT a.id FROM committee_case_actions a INNER JOIN committee_cases c ON c.id = a.case_id WHERE a.id = ? AND c.document_id = ?',
        ],
        'committee_communications' => [
            'load' => 'SELECT * FROM committee_communications WHERE document_id = ? ORDER BY id',
            'owner' => 'SELECT id FROM committee_communications WHERE id = ? AND document_id = ?',
        ],
    ];

    private PDO $pdo;

    public function __construct()
    {
        $database = new Database();
        $this->pdo = $database->connect();
    }

    public function index(): void
    {
        $search = trim($_GET['search'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $where = '';
        $params = [];

        if ($search !== '') {
            $where = "WHERE d.tracking_number LIKE ?
                OR d.subject_matter LIKE ?
                OR d.source_name LIKE ?
                OR dt.name LIKE ?";
            $like = '%' . $search . '%';
            $params = [$like, $like, $like, $like];
        }

        $count = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM documents d
            LEFT JOIN document_types dt ON dt.id = d.document_type_id
            {$where}
        ");
        $count->execute($params);
        $totalRows = (int)$count->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->prepare("
            SELECT d.id, d.tracking_number, d.subject_matter, d.date_received,
                   d.document_type_id, d.current_phase, dt.name AS document_type_name,
                   ds.name AS status_name, st.name AS source_type_name, d.source_name
            FROM documents d
            LEFT JOIN document_types dt ON dt.id = d.document_type_id
            LEFT JOIN document_statuses ds ON ds.id = d.current_status_id
            LEFT JOIN source_types st ON st.id = d.source_type_id
            {$where}
            ORDER BY d.created_at DESC, d.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $documents = $stmt->fetchAll();

        $totalDocuments = (int)$this->pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();
        $missingTypeCount = (int)$this->pdo->query("SELECT COUNT(*) FROM documents WHERE document_type_id IS NULL")->fetchColumn();

        $success = flash_get('success');
        $error = flash_get('error');
        $pageTitle = 'Document Registry';
        $pageSubtitle = 'Track and correct document details across the system.';

        require __DIR__ . '/../../../resources/views/master/documents/index.php';
    }

    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            flash_set('error', 'Invalid document ID.');
            redirect('master/documents');
        }

        $stmt = $this->pdo->prepare("
            SELECT d.*, dt.name AS document_type_name, ds.name AS status_name,
                   st.name AS source_type_name, pgo.name AS external_office_name,
                   h.name AS hospital_name, m.name AS municipality_name,
                   owner.username AS current_owner_username
            FROM documents d
            LEFT JOIN document_types dt ON dt.id = d.document_type_id
            LEFT JOIN document_statuses ds ON ds.id = d.current_status_id
            LEFT JOIN source_types st ON st.id = d.source_type_id
            LEFT JOIN provincial_government_offices pgo ON pgo.id = d.external_office_id
            LEFT JOIN hospitals h ON h.id = d.hospital_id
            LEFT JOIN municities m ON m.id = d.municipality_id
            LEFT JOIN user_accounts owner ON owner.id = d.current_owner_user_id
            WHERE d.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $document = $stmt->fetch();
        if (!$document) {
            flash_set('error', 'Document not found.');
            redirect('master/documents');
        }

        $documentTypes = $this->pdo->query("
            SELECT id, name, is_active FROM document_types
            WHERE is_deleted = 0 OR id = " . (int)$document['document_type_id'] . "
            ORDER BY sort_order, name
        ")->fetchAll();
        $sourceTypesStmt = $this->pdo->prepare("
            SELECT id, name FROM source_types
            WHERE (is_active = 1 AND is_deleted = 0) OR id = ?
            ORDER BY sort_order, name
        ");
        $sourceTypesStmt->execute([(int)$document['source_type_id']]);
        $sourceTypes = $sourceTypesStmt->fetchAll();

        $officesStmt = $this->pdo->prepare("
            SELECT id, name FROM provincial_government_offices
            WHERE (is_active = 1 AND is_deleted = 0) OR id = ?
            ORDER BY name
        ");
        $officesStmt->execute([(int)$document['external_office_id']]);
        $offices = $officesStmt->fetchAll();

        $hospitalsStmt = $this->pdo->prepare("
            SELECT id, name FROM hospitals
            WHERE (is_active = 1 AND is_deleted = 0) OR id = ?
            ORDER BY sort_order, name
        ");
        $hospitalsStmt->execute([(int)$document['hospital_id']]);
        $hospitals = $hospitalsStmt->fetchAll();

        $municipalitiesStmt = $this->pdo->prepare("
            SELECT id, name FROM municities
            WHERE (is_active = 1 AND is_deleted = 0) OR id = ?
            ORDER BY sort_order, name
        ");
        $municipalitiesStmt->execute([(int)$document['municipality_id']]);
        $municipalities = $municipalitiesStmt->fetchAll();

        $spMembersStmt = $this->pdo->prepare("
            SELECT sp_member_id, first_name, middle_name, last_name, suffix
            FROM sp_members
            WHERE (is_active = 1 AND is_deleted = 0)
               OR TRIM(CONCAT_WS(' ', NULLIF(first_name, ''), NULLIF(middle_name, ''),
                    NULLIF(last_name, ''), NULLIF(suffix, ''))) = ?
            ORDER BY last_name, first_name
        ");
        $spMembersStmt->execute([(string)($document['source_name'] ?? '')]);
        $spMembers = $spMembersStmt->fetchAll();

        $attachmentStmt = $this->pdo->prepare("
            SELECT id, file_name, attachment_type, phase, created_at
            FROM document_attachments
            WHERE document_id = ?
            ORDER BY created_at DESC, id DESC
        ");
        $attachmentStmt->execute([$id]);
        $attachments = $attachmentStmt->fetchAll();

        $attachmentDetails = $this->fetchDocumentRows("
            SELECT da.file_name, da.phase, da.attachment_type, da.mime_type,
                   da.file_size, da.created_at, ua.username AS uploaded_by_username
            FROM document_attachments da
            LEFT JOIN user_accounts ua ON ua.id = da.uploaded_by
            WHERE da.document_id = ?
            ORDER BY da.created_at DESC, da.id DESC
        ", $id);

        $plenaryDetails = $this->fetchDocumentRows("
            SELECT pdd.*, ds.name AS plenary_status_name
            FROM plenary_document_details pdd
            LEFT JOIN document_statuses ds ON ds.id = pdd.status_id
            WHERE pdd.document_id = ?
        ", $id);
        $documentAuthors = $this->fetchDocumentRows("
            SELECT da.author_role, da.sort_order, sm.sp_member_id,
                   sm.first_name, sm.middle_name, sm.last_name, sm.suffix
            FROM document_authors da
            INNER JOIN sp_members sm ON sm.sp_member_id = da.sp_member_id
            WHERE da.document_id = ?
            ORDER BY da.author_role, da.sort_order, sm.last_name, sm.first_name
        ", $id);
        $plenaryReadings = $this->fetchDocumentRows("
            SELECT pr.*, psrd.approved_no, psrd.date_finalized,
                   psrd.approved_subject_matter, psrd.date_deferred,
                   psrd.deferred_remarks, psrd.date_withdrawn,
                   psrd.withdrawal_remark, psrd.compliance_file_name,
                   psrd.compliance_file_path, psrd.compliance_mime_type,
                   psrd.compliance_file_size, psrd.compliance_uploaded_by,
                   psrd.compliance_uploaded_at, psrd.returned_to_second_reading_at
            FROM plenary_readings pr
            LEFT JOIN plenary_second_reading_details psrd ON psrd.reading_id = pr.id
            WHERE pr.document_id = ?
            ORDER BY pr.reading_number, pr.performed_at, pr.id
        ", $id);
        $plenaryCommunicationFirstReadings = $this->fetchDocumentRows("
            SELECT pcfr.*, cc.name AS communication_category_name
            FROM plenary_comm_first_reading pcfr
            LEFT JOIN communication_categories cc ON cc.id = pcfr.communication_category_id
            WHERE pcfr.document_id = ?
        ", $id);
        $plenaryCaseFirstReadings = $this->fetchDocumentRows("
            SELECT * FROM plenary_cases_first_reading WHERE document_id = ?
        ", $id);
        $communicationFirstReadings = $this->fetchDocumentRows("
            SELECT cfr.*, cii.sent_to_fr_at,
                   cat.name AS communication_category_name
            FROM comm_first_reading cfr
            LEFT JOIN comm_inbox_items cii ON cii.id = cfr.inbox_item_id
            LEFT JOIN communication_categories cat ON cat.id = cfr.communication_category_id
            WHERE cfr.document_id = ?
        ", $id);
        $communicationCommitteeAssignments = $this->fetchDocumentRows("
            SELECT c.name, cca.created_at
            FROM comm_committee_assignments cca
            INNER JOIN committees c ON c.id = cca.committee_id
            WHERE cca.document_id = ?
            ORDER BY c.name
        ", $id);
        $plenaryCommunicationAdoptions = $this->fetchDocumentRows("
            SELECT * FROM plenary_comm_adoptions WHERE document_id = ?
        ", $id);
        $plenaryCaseAdoptions = $this->fetchDocumentRows("
            SELECT * FROM plenary_cases_adoptions WHERE document_id = ?
        ", $id);
        $committeeReportAdoptions = $this->fetchDocumentRows("
            SELECT pcra.*, cr.report_number, cr.report_type
            FROM plenary_committee_report_adoptions pcra
            LEFT JOIN committee_reports cr ON cr.id = pcra.committee_report_id
            WHERE pcra.document_id = ?
        ", $id);
        $plenaryCommunicationFiles = $this->fetchDocumentRows("
            SELECT original_filename, mime_type, file_size, uploaded_at
            FROM plenary_comm_resolution_files WHERE document_id = ?
            ORDER BY uploaded_at DESC, id DESC
        ", $id);
        $communicationFiles = $this->fetchDocumentRows("
            SELECT original_filename, mime_type, file_size, uploaded_at
            FROM comm_resolution_files WHERE document_id = ?
            ORDER BY uploaded_at DESC, id DESC
        ", $id);
        $documentApprovals = $this->fetchDocumentRows("
            SELECT da.*, ui.first_name AS approved_first_name,
                   ui.last_name AS approved_last_name
            FROM document_approvals da
            LEFT JOIN user_info ui ON ui.user_account_id = da.approved_by
            WHERE da.document_id = ?
        ", $id);
        $secondReadingApprovalFiles = $this->fetchDocumentRows("
            SELECT original_filename, mime_type, file_size, uploaded_at
            FROM plenary_second_reading_approval_files
            WHERE document_id = ?
            ORDER BY uploaded_at DESC, id DESC
        ", $id);
        $plenaryCaseAdoptionFiles = $this->fetchDocumentRows("
            SELECT original_filename, mime_type, file_size, uploaded_at
            FROM plenary_cases_adoption_files
            WHERE document_id = ?
            ORDER BY uploaded_at DESC, id DESC
        ", $id);
        $motionReconsiderationLinks = $this->fetchDocumentRows("
            SELECT mrl.linked_at, original.tracking_number AS referenced_tracking_number,
                   original.subject_matter AS referenced_subject,
                   original_type.name AS referenced_document_type
            FROM motion_reconsideration_links mrl
            INNER JOIN documents original ON original.id = mrl.referenced_document_id
            LEFT JOIN document_types original_type ON original_type.id = original.document_type_id
            WHERE mrl.mfr_document_id = ?
        ", $id);
        $documentFilings = $this->fetchDocumentRows("
            SELECT df.*, ui.first_name AS filed_first_name, ui.last_name AS filed_last_name
            FROM document_filings df
            LEFT JOIN user_info ui ON ui.user_account_id = df.filed_by
            WHERE df.document_id = ?
        ", $id);
        $filingAttachments = $this->fetchDocumentRows("
            SELECT dfa.filing_id, da.file_name, da.attachment_type, da.phase,
                   da.mime_type, da.file_size, da.created_at
            FROM document_filing_attachments dfa
            INNER JOIN document_filings df ON df.id = dfa.filing_id
            INNER JOIN document_attachments da ON da.id = dfa.attachment_id
            WHERE df.document_id = ?
            ORDER BY da.created_at DESC, da.id DESC
        ", $id);

        $assignedCommittees = $this->fetchDocumentRows("
            SELECT c.name, dc.created_at
            FROM document_committees dc
            INNER JOIN committees c ON c.id = dc.committee_id
            WHERE dc.document_id = ?
            ORDER BY c.name
        ", $id);
        $committeeCycles = $this->fetchDocumentRows("
            SELECT cc.id, cc.cycle_number, cc.decision, cc.remarks, cc.started_at,
                   cc.accepted_at, cc.completed_at, ui.first_name, ui.last_name,
                   (SELECT GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ')
                    FROM committee_cycle_committees ccy
                    INNER JOIN committees c ON c.id = ccy.committee_id
                    WHERE ccy.cycle_id = cc.id) AS committees
            FROM committee_cycles cc
            LEFT JOIN user_info ui ON ui.user_account_id = cc.accepted_by
            WHERE cc.document_id = ?
            ORDER BY cc.cycle_number, cc.id
        ", $id);
        $cycleCommittees = $this->fetchDocumentRows("
            SELECT ccy.cycle_id, c.name
            FROM committee_cycle_committees ccy
            INNER JOIN committee_cycles cy ON cy.id = ccy.cycle_id
            INNER JOIN committees c ON c.id = ccy.committee_id
            WHERE cy.document_id = ?
            ORDER BY cy.cycle_number, c.name
        ", $id);
        $committeeEndorsements = $this->fetchDocumentRows("
            SELECT e.id, e.cycle_id, cy.cycle_number, e.endorsement_number,
                   e.requested_at, e.submitted_at, e.remarks,
                   oo.name AS office_name, os.name AS opinion_status,
                   es.opinion_type, es.submitted_at AS opinion_submitted_at,
                   es.remarks AS submission_remarks,
                   opinion_file.file_name AS opinion_file_name,
                   opinion_file.mime_type AS opinion_file_mime_type,
                   opinion_file.file_size AS opinion_file_size,
                   compliance_file.file_name AS compliance_file_name,
                   compliance_file.mime_type AS compliance_file_mime_type,
                   compliance_file.file_size AS compliance_file_size
            FROM committee_cycle_endorsements e
            INNER JOIN committee_cycles cy ON cy.id = e.cycle_id
            INNER JOIN opinion_offices oo ON oo.id = e.opinion_office_id
            LEFT JOIN opinion_statuses os ON os.id = e.opinion_status_id
            LEFT JOIN committee_endorsement_submissions es ON es.endorsement_id = e.id
            LEFT JOIN document_attachments opinion_file ON opinion_file.id = es.opinion_file_attachment_id
            LEFT JOIN document_attachments compliance_file ON compliance_file.id = es.compliance_file_attachment_id
            WHERE cy.document_id = ?
            ORDER BY cy.cycle_number, e.endorsement_number, e.id
        ", $id);
        $opinionResolutions = $this->fetchDocumentRows("
            SELECT r.*, cy.cycle_number
            FROM committee_opinion_resolutions r
            INNER JOIN committee_cycles cy ON cy.id = r.cycle_id
            WHERE cy.document_id = ?
            ORDER BY cy.cycle_number
        ", $id);
        $agendas = $this->fetchDocumentRows("
            SELECT a.*, cy.cycle_number,
                   (SELECT GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ')
                    FROM agenda_committees ac
                    INNER JOIN committees c ON c.id = ac.committee_id
                    WHERE ac.agenda_id = a.id) AS committees,
                   (SELECT GROUP_CONCAT(TRIM(CONCAT_WS(' ', sm.first_name,
                        sm.middle_name, sm.last_name, sm.suffix))
                        ORDER BY sm.last_name, sm.first_name SEPARATOR ', ')
                    FROM agenda_chairpersons ach
                    INNER JOIN sp_members sm ON sm.sp_member_id = ach.sp_member_id
                    WHERE ach.agenda_id = a.id) AS chairpersons
            FROM agendas a
            LEFT JOIN committee_cycles cy ON cy.id = a.cycle_id
            WHERE a.document_id = ?
            ORDER BY a.agenda_date, a.agenda_time, a.id
        ", $id);
        $agendaCommittees = $this->fetchDocumentRows("
            SELECT ac.agenda_id, c.name
            FROM agenda_committees ac
            INNER JOIN agendas a ON a.id = ac.agenda_id
            INNER JOIN committees c ON c.id = ac.committee_id
            WHERE a.document_id = ?
            ORDER BY ac.agenda_id, c.name
        ", $id);
        $agendaChairpersons = $this->fetchDocumentRows("
            SELECT ach.agenda_id, sm.first_name, sm.middle_name, sm.last_name, sm.suffix
            FROM agenda_chairpersons ach
            INNER JOIN agendas a ON a.id = ach.agenda_id
            INNER JOIN sp_members sm ON sm.sp_member_id = ach.sp_member_id
            WHERE a.document_id = ?
            ORDER BY ach.agenda_id, sm.last_name, sm.first_name
        ", $id);
        $committeeHearings = $this->fetchDocumentRows("
            SELECT ch.*, a.agenda_number, a.agenda_date, a.venue
            FROM committee_hearings ch
            LEFT JOIN agendas a ON a.id = ch.agenda_id
            WHERE ch.document_id = ?
            ORDER BY ch.performed_at, ch.id
        ", $id);
        $committeeReports = $this->fetchDocumentRows("
            SELECT crd.committee_report_id, cr.report_type, cr.report_number,
                   cr.summary_of_findings, cr.agenda_id, cr.hearing_id,
                   cr.created_at, cr.returned_to_plenary_at,
                   (SELECT GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ')
                    FROM committee_report_committees crc
                    INNER JOIN committees c ON c.id = crc.committee_id
                    WHERE crc.committee_report_id = cr.id) AS committees
            FROM committee_report_documents crd
            INNER JOIN committee_reports cr ON cr.id = crd.committee_report_id
            WHERE crd.document_id = ?
            ORDER BY cr.created_at, cr.id
        ", $id);
        $committeeReportCommittees = $this->fetchDocumentRows("
            SELECT crd.committee_report_id, c.name
            FROM committee_report_documents crd
            INNER JOIN committee_report_committees crc ON crc.committee_report_id = crd.committee_report_id
            INNER JOIN committees c ON c.id = crc.committee_id
            WHERE crd.document_id = ?
            ORDER BY crd.committee_report_id, c.name
        ", $id);
        $committeeReportAttachments = $this->fetchDocumentRows("
            SELECT cr.report_number, cra.file_name, cra.mime_type, cra.file_size,
                   cra.created_at, cra.source_phase, cra.source_transmittal_id,
                   cra.returned_at
            FROM committee_report_documents crd
            INNER JOIN committee_reports cr ON cr.id = crd.committee_report_id
            INNER JOIN committee_report_attachments cra ON cra.committee_report_id = crd.committee_report_id
            WHERE crd.document_id = ?
            ORDER BY cra.created_at DESC, cra.id DESC
        ", $id);
        $committeeCases = $this->fetchDocumentRows("
            SELECT * FROM committee_cases WHERE document_id = ?
        ", $id);
        $committeeCaseActions = $this->fetchDocumentRows("
            SELECT cca.*, ui.first_name, ui.last_name,
                   (SELECT GROUP_CONCAT(ccaa.file_name ORDER BY ccaa.id SEPARATOR ', ')
                    FROM committee_case_action_attachments ccaa
                    WHERE ccaa.action_id = cca.id) AS attachments
            FROM committee_case_actions cca
            INNER JOIN committee_cases cc ON cc.id = cca.case_id
            LEFT JOIN user_info ui ON ui.user_account_id = cca.created_by
            WHERE cc.document_id = ?
            ORDER BY cca.action_date, cca.id
        ", $id);
        $committeeCaseActionAttachments = $this->fetchDocumentRows("
            SELECT cca.id AS action_id, ccaa.file_name, ccaa.mime_type,
                   ccaa.file_size, ccaa.created_at
            FROM committee_case_action_attachments ccaa
            INNER JOIN committee_case_actions cca ON cca.id = ccaa.action_id
            INNER JOIN committee_cases cc ON cc.id = cca.case_id
            WHERE cc.document_id = ?
            ORDER BY ccaa.created_at DESC, ccaa.id DESC
        ", $id);
        $committeeCommunications = $this->fetchDocumentRows("
            SELECT cc.*, a.agenda_number, a.agenda_date, a.venue,
                   ch.outcome AS hearing_outcome, cr.report_number, cr.report_type
            FROM committee_communications cc
            LEFT JOIN agendas a ON a.id = cc.agenda_id
            LEFT JOIN committee_hearings ch ON ch.id = cc.hearing_id
            LEFT JOIN committee_reports cr ON cr.id = cc.report_id
            WHERE cc.document_id = ?
        ", $id);
        $relatedEditableRecords = $this->loadRelatedEditableRecords($id);
        $relatedFieldSchemas = $this->loadRelatedFieldSchemas();
        $documentAttachmentOptionsStmt = $this->pdo->prepare("
            SELECT id, CONCAT(file_name, ' · ', REPLACE(phase, '_', ' ')) AS name
            FROM document_attachments
            WHERE document_id = ?
            ORDER BY created_at DESC, id DESC
        ");
        $documentAttachmentOptionsStmt->execute([$id]);
        $documentAttachmentOptions = $documentAttachmentOptionsStmt->fetchAll();
        $relatedSelectOptions = [
            'committee_id' => $this->pdo->query("
                SELECT id, name FROM committees ORDER BY name
            ")->fetchAll(),
            'sp_member_id' => $this->pdo->query("
                SELECT sp_member_id AS id,
                       TRIM(CONCAT_WS(' ', NULLIF(first_name, ''), NULLIF(middle_name, ''),
                           NULLIF(last_name, ''), NULLIF(suffix, ''))) AS name
                FROM sp_members
                ORDER BY last_name, first_name
            ")->fetchAll(),
            'opinion_office_id' => $this->pdo->query("
                SELECT id, name FROM opinion_offices ORDER BY name
            ")->fetchAll(),
            'opinion_file_attachment_id' => $documentAttachmentOptions,
            'compliance_file_attachment_id' => $documentAttachmentOptions,
        ];
        $documentEvents = $this->fetchDocumentRows("
            SELECT de.event_type, de.phase, de.remarks, de.metadata, de.created_at,
                   ui.first_name, ui.last_name
            FROM document_events de
            LEFT JOIN user_info ui ON ui.user_account_id = de.performed_by
            WHERE de.document_id = ?
            ORDER BY de.created_at DESC, de.id DESC
        ", $id);
        $documentRoutes = $this->fetchDocumentRows("
            SELECT dr.from_phase, dr.to_phase, dr.remarks, dr.created_at,
                   ro.name AS routing_option_name, ui.first_name, ui.last_name
            FROM document_routes dr
            LEFT JOIN routing_options ro ON ro.id = dr.routing_option_id
            LEFT JOIN user_info ui ON ui.user_account_id = dr.routed_by
            WHERE dr.document_id = ?
            ORDER BY dr.created_at DESC, dr.id DESC
        ", $id);
        $documentAssignments = $this->fetchDocumentRows("
            SELECT da.phase, da.decision, da.received_at, da.accepted_at,
                   da.declined_at, da.completed_at, da.remarks,
                   ua.username AS assigned_to_username, r.name AS assigned_to_role
            FROM document_assignments da
            LEFT JOIN user_accounts ua ON ua.id = da.assigned_to_user_id
            LEFT JOIN roles r ON r.id = da.assigned_to_role_id
            WHERE da.document_id = ?
            ORDER BY da.created_at, da.id
        ", $id);
        $documentChecklists = $this->fetchDocumentRows("
            SELECT c.name, dci.is_completed, dci.completed_at, dci.remarks
            FROM document_checklist_items dci
            INNER JOIN checklists c ON c.id = dci.checklist_id
            WHERE dci.document_id = ?
            ORDER BY c.sort_order, c.name
        ", $id);

        $auditStmt = $this->pdo->prepare("
            SELECT al.created_at, al.old_values, al.new_values, al.description,
                   al.user_id, ui.first_name, ui.last_name
            FROM audit_logs al
            LEFT JOIN user_info ui ON ui.user_account_id = al.user_id
            WHERE al.entity_type = 'Document' AND al.entity_id = ?
            ORDER BY al.created_at DESC, al.id DESC
            LIMIT 20
        ");
        $auditStmt->execute([(string)$id]);
        $auditHistory = $auditStmt->fetchAll();

        $formData = array_merge($document, old_get());
        $documentRecordDetails = array_intersect_key($document, array_flip([
            'tracking_number', 'date_received', 'time_received', 'subject_matter',
            'document_type_name', 'source_type_name', 'external_office_name',
            'hospital_name', 'municipality_name', 'source_name',
            'source_contact_number', 'source_address', 'source_liaison_name',
            'remarks', 'current_status_id', 'status_name', 'current_phase',
            'current_owner_username', 'finalized_at', 'filed_at',
            'created_at', 'updated_at',
        ]));
        old_clear();
        $success = flash_get('success');
        $error = flash_get('error');
        $pageTitle = 'Correct Document Details';
        $pageSubtitle = 'Review the document record and save corrections. Changes are recorded in the audit history.';

        require __DIR__ . '/../../../resources/views/master/documents/edit.php';
    }

    public function update(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash_set('error', 'Invalid document ID.');
            redirect('master/documents');
        }

        $data = [
            'document_type_id' => (int)$this->postString('document_type_id'),
            'subject_matter' => $this->postString('subject_matter'),
            'date_received' => $this->postString('date_received'),
            'time_received' => $this->postString('time_received'),
            'source_type_id' => (int)$this->postString('source_type_id'),
            'external_office_id' => $this->nullableId($this->postString('external_office_id')),
            'hospital_id' => $this->nullableId($this->postString('hospital_id')),
            'municipality_id' => $this->nullableId($this->postString('municipality_id')),
            'source_name' => $this->postString('source_name'),
            'source_contact_number' => $this->postString('source_contact_number'),
            'source_address' => $this->postString('source_address'),
            'source_liaison_name' => $this->postString('source_liaison_name'),
            'remarks' => $this->postString('remarks'),
        ];

        $oldStmt = $this->pdo->prepare("SELECT * FROM documents WHERE id = ? LIMIT 1");
        $oldStmt->execute([$id]);
        $original = $oldStmt->fetch();
        if (!$original) {
            flash_set('error', 'Document not found.');
            redirect('master/documents');
        }

        $sourceTypeStmt = $this->pdo->prepare("
            SELECT name FROM source_types
            WHERE id = ? AND ((is_active = 1 AND is_deleted = 0) OR id = ?)
            LIMIT 1
        ");
        $sourceTypeStmt->execute([$data['source_type_id'], (int)$original['source_type_id']]);
        $sourceTypeName = (string)($sourceTypeStmt->fetchColumn() ?: '');

        $sourceData = [
            'external_office_id' => $data['external_office_id'],
            'hospital_id' => $data['hospital_id'],
            'municipality_id' => $data['municipality_id'],
            'sp_member_id' => $this->nullableId($this->postString('sp_member_id')),
            'source_name' => $data['source_name'],
            'source_contact_number' => $data['source_contact_number'],
            'source_address' => $data['source_address'],
            'source_liaison_name' => $data['source_liaison_name'],
        ];
        $sourceErrors = $this->normalizeAndValidateSource(
            $sourceTypeName,
            $sourceData,
            $original,
            $data['source_type_id'] === (int)$original['source_type_id']
        );
        $data = array_merge($data, $sourceData);

        $errors = $this->validate($data, $original);
        $errors = array_merge($sourceErrors, $errors);
        if ($errors !== []) {
            flash_set('error', implode(' ', $errors));
            old_set($_POST);
            redirect('master/documents/edit?id=' . $id);
        }

        $trackedFields = array_keys($data);
        $oldValues = array_intersect_key($original, array_flip($trackedFields));
        $newValues = $data;

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare("
                UPDATE documents
                SET document_type_id = ?, subject_matter = ?, date_received = ?,
                    time_received = ?, source_type_id = ?, external_office_id = ?,
                    hospital_id = ?, municipality_id = ?, source_name = ?,
                    source_contact_number = ?, source_address = ?,
                    source_liaison_name = ?, remarks = ?,
                    updated_by = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['document_type_id'],
                $data['subject_matter'],
                $data['date_received'],
                $data['time_received'],
                $data['source_type_id'],
                $data['external_office_id'],
                $data['hospital_id'],
                $data['municipality_id'],
                $data['source_name'] ?: null,
                $data['source_contact_number'] ?: null,
                $data['source_address'] ?: null,
                $data['source_liaison_name'] ?: null,
                $data['remarks'] ?: null,
                auth_id(),
                $id,
            ]);

            $audit = $this->pdo->prepare("
                INSERT INTO audit_logs
                    (user_id, action, entity_type, entity_id, old_values, new_values,
                     ip_address, user_agent, description, created_at)
                VALUES (?, 'UPDATE', 'Document', ?, ?, ?, ?, ?, ?, NOW())
            ");
            $audit->execute([
                auth_id(),
                (string)$id,
                json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                client_ip(),
                mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500) ?: null,
                'Super Admin corrected document ' . $original['tracking_number'],
            ]);
            $this->pdo->commit();
            flash_set('success', 'Document details updated. The correction was added to the audit history.');
            redirect('master/documents/edit?id=' . $id);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Document registry update failed: ' . $e->getMessage());
            flash_set('error', 'The document could not be updated. No changes were saved; please try again.');
            old_set($_POST);
            redirect('master/documents/edit?id=' . $id);
        } catch (JsonException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Document registry audit data could not be encoded: ' . $e->getMessage());
            flash_set('error', 'The document could not be updated because its audit record could not be created.');
            old_set($_POST);
            redirect('master/documents/edit?id=' . $id);
        }
    }

    public function updateRelated(): void
    {
        $documentId = (int)($_POST['document_id'] ?? 0);
        $table = $this->postString('table');
        $recordId = (int)($_POST['record_id'] ?? 0);
        $submittedFields = $_POST['fields'] ?? null;

        if (
            $documentId <= 0 ||
            $recordId <= 0 ||
            !in_array($table, self::RELATED_REGISTRY_TABLES, true) ||
            !isset(self::RELATED_EDITABLE_FIELDS[$table]) ||
            !is_array($submittedFields)
        ) {
            flash_set('error', 'Invalid related document details.');
            redirect('master/documents');
        }

        $ownerQuery = $this->pdo->prepare(self::RELATED_RECORD_QUERIES[$table]['owner']);
        $ownerQuery->execute([$recordId, $documentId]);
        if (!$ownerQuery->fetch()) {
            flash_set('error', 'The related record does not belong to this document.');
            redirect('master/documents/edit?id=' . $documentId);
        }

        $columnQuery = $this->pdo->query("SHOW COLUMNS FROM `{$table}`");
        $columns = [];
        foreach ($columnQuery->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $columns[$column['Field']] = $column;
        }

        $updates = [];
        $oldValues = [];
        $newValues = [];
        foreach (self::RELATED_EDITABLE_FIELDS[$table] as $field) {
            if (!array_key_exists($field, $submittedFields) || !isset($columns[$field])) {
                continue;
            }
            $rawValue = $submittedFields[$field];
            if (!is_string($rawValue) && !is_numeric($rawValue)) {
                flash_set('error', 'Invalid value submitted for ' . str_replace('_', ' ', $field) . '.');
                redirect('master/documents/edit?id=' . $documentId);
            }

            $value = trim((string)$rawValue);
            $column = $columns[$field];
            $rawType = (string)$column['Type'];
            $type = strtolower($rawType);
            if ($value === '' && $column['Null'] === 'YES') {
                $value = null;
            } elseif (preg_match('/^enum\((.*)\)$/i', $rawType, $enumMatch)) {
                preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $enumMatch[1], $enumValues);
                if (!in_array($value, $enumValues[1], true)) {
                    flash_set('error', 'Choose a valid value for ' . str_replace('_', ' ', $field) . '.');
                    redirect('master/documents/edit?id=' . $documentId);
                }
            } elseif (preg_match('/^(tinyint|smallint|mediumint|int|bigint|decimal|float|double)/', $type)) {
                $isInteger = preg_match('/^(tinyint|smallint|mediumint|int|bigint)/', $type) === 1;
                if (!is_numeric($value) || ($isInteger && !preg_match('/^-?\d+$/', $value))) {
                    flash_set('error', 'Enter a valid number for ' . str_replace('_', ' ', $field) . '.');
                    redirect('master/documents/edit?id=' . $documentId);
                }
            } elseif (
                ($type === 'date' || $type === 'time' || str_starts_with($type, 'datetime') || str_starts_with($type, 'timestamp'))
                && $value !== null
            ) {
                $format = $type === 'date' ? '!Y-m-d' : ($type === 'time' ? '!H:i' : '!Y-m-d\TH:i');
                $expected = $type === 'date' ? 'Y-m-d' : ($type === 'time' ? 'H:i' : 'Y-m-d\TH:i');
                $parsed = DateTime::createFromFormat($format, $value);
                if (!$parsed || $parsed->format($expected) !== $value) {
                    flash_set('error', 'Enter a valid date or time for ' . str_replace('_', ' ', $field) . '.');
                    redirect('master/documents/edit?id=' . $documentId);
                }
                if (str_starts_with($type, 'datetime') || str_starts_with($type, 'timestamp')) {
                    $value = str_replace('T', ' ', $value) . ':00';
                }
            }

            if ($value !== null && in_array($field, ['opinion_office_id', 'committee_id', 'sp_member_id'], true)) {
                $reference = match ($field) {
                    'opinion_office_id' => ['opinion_offices', 'id'],
                    'committee_id' => ['committees', 'id'],
                    default => ['sp_members', 'sp_member_id'],
                };
                $referenceQuery = $this->pdo->prepare(
                    "SELECT 1 FROM `{$reference[0]}` WHERE `{$reference[1]}` = ? LIMIT 1"
                );
                $referenceQuery->execute([(int)$value]);
                if (!$referenceQuery->fetchColumn()) {
                    flash_set('error', 'Choose a valid ' . str_replace('_', ' ', $field) . '.');
                    redirect('master/documents/edit?id=' . $documentId);
                }
            }

            if (
                $value !== null &&
                in_array($field, ['opinion_file_attachment_id', 'compliance_file_attachment_id'], true)
            ) {
                $attachmentQuery = $this->pdo->prepare("
                    SELECT 1 FROM document_attachments WHERE id = ? AND document_id = ? LIMIT 1
                ");
                $attachmentQuery->execute([(int)$value, $documentId]);
                if (!$attachmentQuery->fetchColumn()) {
                    flash_set('error', 'Choose an attachment already linked to this document.');
                    redirect('master/documents/edit?id=' . $documentId);
                }
            }

            $updates[$field] = $value;
        }

        if ($updates === []) {
            flash_set('error', 'No editable related details were submitted.');
            redirect('master/documents/edit?id=' . $documentId);
        }

        $currentQuery = $this->pdo->prepare("SELECT * FROM `{$table}` WHERE id = ? LIMIT 1");
        $currentQuery->execute([$recordId]);
        $current = $currentQuery->fetch();
        if (!$current) {
            flash_set('error', 'The related record could not be found.');
            redirect('master/documents/edit?id=' . $documentId);
        }
        foreach ($updates as $field => $value) {
            $oldValues[$field] = $current[$field];
            $newValues[$field] = $value;
        }

        try {
            $this->pdo->beginTransaction();
            $assignments = implode(', ', array_map(
                static fn(string $field): string => "`{$field}` = ?",
                array_keys($updates)
            ));
            $update = $this->pdo->prepare("UPDATE `{$table}` SET {$assignments} WHERE id = ?");
            $update->execute([...array_values($updates), $recordId]);

            $audit = $this->pdo->prepare("
                INSERT INTO audit_logs
                    (user_id, action, entity_type, entity_id, old_values, new_values,
                     ip_address, user_agent, description, created_at)
                VALUES (?, 'UPDATE', 'Document', ?, ?, ?, ?, ?, ?, NOW())
            ");
            $audit->execute([
                auth_id(),
                (string)$documentId,
                json_encode([$table . '#' . $recordId => $oldValues], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                json_encode([$table . '#' . $recordId => $newValues], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                client_ip(),
                mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500) ?: null,
                'Super Admin corrected ' . str_replace('_', ' ', $table) . ' for document #' . $documentId,
            ]);
            $this->pdo->commit();
            flash_set('success', 'Related Plenary or Committee details updated and recorded in the correction history.');
            redirect('master/documents/edit?id=' . $documentId);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Related document registry update failed: ' . $e->getMessage());
            flash_set('error', 'The related details could not be updated. No changes were saved; please check the values and try again.');
            redirect('master/documents/edit?id=' . $documentId);
        } catch (JsonException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Related document registry audit data could not be encoded: ' . $e->getMessage());
            flash_set('error', 'The related details could not be updated because the audit record could not be created.');
            redirect('master/documents/edit?id=' . $documentId);
        }
    }

    private function validate(array $data, array $original): array
    {
        $errors = [];

        $type = $this->pdo->prepare("
            SELECT id FROM document_types
            WHERE id = ? AND (is_deleted = 0 OR id = ?)
            LIMIT 1
        ");
        $type->execute([$data['document_type_id'], (int)$original['document_type_id']]);
        if (!$type->fetch()) {
            $errors[] = 'Select a valid document type.';
        }

        if ($data['subject_matter'] === '') {
            $errors[] = 'Subject matter is required.';
        }
        if (strlen($data['subject_matter']) > 65535) {
            $errors[] = 'Subject matter is too long.';
        }
        if (strlen($data['source_address']) > 65535 || strlen($data['remarks']) > 65535) {
            $errors[] = 'Source address and remarks must each be 65,535 bytes or fewer.';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date_received'])) {
            $errors[] = 'Enter a valid date received.';
        } else {
            [$year, $month, $day] = array_map('intval', explode('-', $data['date_received']));
            if (!checkdate($month, $day, $year)) {
                $errors[] = 'Enter a valid date received.';
            }
        }
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $data['time_received'])) {
            $errors[] = 'Enter a valid time received.';
        }
        foreach ([
            'source_name' => 255,
            'source_contact_number' => 50,
            'source_liaison_name' => 255,
        ] as $field => $maxLength) {
            if (mb_strlen($data[$field]) > $maxLength) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . " must be {$maxLength} characters or fewer.";
            }
        }

        return $errors;
    }

    private function fetchDocumentRows(string $sql, int $documentId): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$documentId]);
        return $stmt->fetchAll();
    }

    private function loadRelatedEditableRecords(int $documentId): array
    {
        $records = [];
        foreach (self::RELATED_REGISTRY_TABLES as $table) {
            $records[$table] = $this->fetchDocumentRows(self::RELATED_RECORD_QUERIES[$table]['load'], $documentId);
        }
        return $records;
    }

    private function loadRelatedFieldSchemas(): array
    {
        $schemas = [];
        foreach (self::RELATED_REGISTRY_TABLES as $table) {
            $fields = self::RELATED_EDITABLE_FIELDS[$table];
            $columnQuery = $this->pdo->query("SHOW COLUMNS FROM `{$table}`");
            $columns = [];
            foreach ($columnQuery->fetchAll(PDO::FETCH_ASSOC) as $column) {
                if (in_array($column['Field'], $fields, true)) {
                    $columns[$column['Field']] = $column;
                }
            }
            $schemas[$table] = $columns;
        }
        return $schemas;
    }

    private function normalizeAndValidateSource(
        string $sourceTypeName,
        array &$source,
        array $original,
        bool $sameSourceType
    ): array {
        $errors = [];
        $fields = [
            'external_office_id' => 'provincial_government_offices',
            'hospital_id' => 'hospitals',
            'municipality_id' => 'municities',
        ];

        $requiredField = match ($sourceTypeName) {
            'Provincial Government Offices' => 'external_office_id',
            'Hospital' => 'hospital_id',
            'Client' => 'municipality_id',
            default => null,
        };

        foreach ($fields as $field => $table) {
            if ($field !== $requiredField) {
                $source[$field] = null;
                continue;
            }

            if ($source[$field] === null || $source[$field] <= 0) {
                $errors[] = match ($field) {
                    'external_office_id' => 'Select a provincial government office for this source type.',
                    'hospital_id' => 'Select a hospital for this source type.',
                    default => 'Select a municipality or city for this source type.',
                };
                continue;
            }

            $sql = "SELECT id FROM {$table} WHERE id = ? AND is_active = 1 AND is_deleted = 0";
            $params = [$source[$field]];
            if ($sameSourceType && (int)$original[$field] === $source[$field]) {
                $sql = "SELECT id FROM {$table} WHERE id = ?";
            }
            $lookup = $this->pdo->prepare($sql . ' LIMIT 1');
            $lookup->execute($params);
            if (!$lookup->fetch()) {
                $errors[] = 'Select an active, valid source record.';
            }
        }

        switch ($sourceTypeName) {
            case 'Provincial Government Offices':
            case 'Hospital':
                $source['source_name'] = '';
                $source['sp_member_id'] = null;
                break;

            case 'SP Member':
                $source['source_name'] = '';
                $memberId = $source['sp_member_id'];
                if ($memberId === null || $memberId <= 0) {
                    $errors[] = 'Select an SP Member for this source type.';
                    break;
                }

                $memberSql = "
                    SELECT first_name, middle_name, last_name, suffix
                    FROM sp_members
                    WHERE sp_member_id = ? AND is_active = 1 AND is_deleted = 0
                ";
                if ($sameSourceType && trim((string)$original['source_name']) !== '') {
                    $memberSql = "
                        SELECT first_name, middle_name, last_name, suffix
                        FROM sp_members
                        WHERE sp_member_id = ? AND (
                            (is_active = 1 AND is_deleted = 0)
                            OR TRIM(CONCAT_WS(' ', NULLIF(first_name, ''), NULLIF(middle_name, ''),
                                NULLIF(last_name, ''), NULLIF(suffix, ''))) = ?
                        )
                    ";
                    $memberParams = [$memberId, (string)$original['source_name']];
                } else {
                    $memberParams = [$memberId];
                }
                $memberStmt = $this->pdo->prepare($memberSql . ' LIMIT 1');
                $memberStmt->execute($memberParams);
                $member = $memberStmt->fetch();
                if (!$member) {
                    $errors[] = 'Select an active, valid SP Member.';
                    break;
                }
                $source['source_name'] = trim(implode(' ', array_filter([
                    $member['first_name'],
                    $member['middle_name'],
                    $member['last_name'],
                    $member['suffix'],
                ])));
                break;

            case 'Agency':
                $source['sp_member_id'] = null;
                if ($source['source_name'] === '') {
                    $errors[] = 'Agency name is required for this source type.';
                }
                break;

            case 'Client':
                $source['sp_member_id'] = null;
                if ($source['source_name'] === '') {
                    $errors[] = 'Client name is required for this source type.';
                }
                break;

            default:
                $source['external_office_id'] = null;
                $source['hospital_id'] = null;
                $source['municipality_id'] = null;
                $source['sp_member_id'] = null;
                if ($sourceTypeName === '') {
                    $errors[] = 'Select a valid source type.';
                }
        }

        if (mb_strlen($source['source_name']) > 255) {
            $errors[] = 'Source name must be 255 characters or fewer.';
        }
        if (mb_strlen($source['source_contact_number']) > 50) {
            $errors[] = 'Contact number must be 50 characters or fewer.';
        }
        if (mb_strlen($source['source_liaison_name']) > 255) {
            $errors[] = 'Liaison name must be 255 characters or fewer.';
        }
        if (strlen($source['source_address']) > 65535) {
            $errors[] = 'Source address must be 65,535 bytes or fewer.';
        }

        return $errors;
    }

    private function nullableId(mixed $value): ?int
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        if (!ctype_digit($value) || (int)$value <= 0) {
            return -1;
        }
        return (int)$value;
    }

    private function postString(string $field): string
    {
        $value = $_POST[$field] ?? '';
        return is_string($value) || is_numeric($value) ? trim((string)$value) : '';
    }
}

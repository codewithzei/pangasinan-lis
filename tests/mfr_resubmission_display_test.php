<?php

declare(strict_types=1);

if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/pangasinan-lis');
}

function renderResubmissionPartial(array $variables): string
{
    extract($variables, EXTR_SKIP);
    ob_start();
    require __DIR__ . '/../resources/views/partials/resubmission-relationship.php';
    return (string) ob_get_clean();
}

function verifyResubmissionCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$referenceHtml = renderResubmissionPartial([
    'mfrDetailRoute' => 'receiving/routed-documents',
    'resubmissionReference' => [
        'ref_id' => 101,
        'ref_tracking_number' => 'TRK-ORIGINAL-101',
        'ref_subject_matter' => 'Original subject',
        'ref_proposed_number' => 'HB-101',
        'ref_document_type_name' => 'Bill',
        'ref_document_type_badge_color' => '#2563EB',
        'ref_status' => 'Remanded',
        'ref_status_badge_color' => '#B45309',
        'version_number' => 2,
    ],
]);
verifyResubmissionCheck(str_contains($referenceHtml, 'Resubmission'), 'Resubmitted document label is missing.');
verifyResubmissionCheck(str_contains($referenceHtml, 'TRK-ORIGINAL-101'), 'Linked document tracking number is missing.');
verifyResubmissionCheck(
    str_contains($referenceHtml, '/receiving/routed-documents/show?id=101'),
    'Linked document does not use the current detail route.'
);
verifyResubmissionCheck(str_contains($referenceHtml, 'Original subject'), 'Linked document subject matter is missing.');
verifyResubmissionCheck(str_contains($referenceHtml, 'Version 2'), 'Current submission version is missing.');

$relatedHtml = renderResubmissionPartial([
    'mfrDetailRoute' => 'admin/routed',
    'relatedResubmissions' => [[
        'resubmitted_document_id' => 101,
        'resubmitted_tracking_number' => 'TRK-ORIGINAL-101',
        'resubmitted_subject_matter' => 'Original subject',
        'resubmitted_document_type_name' => 'Bill',
        'resubmitted_status' => 'Remanded',
        'resubmitted_status_badge_color' => '#D97706',
        'version_number' => 1,
        'is_current' => 0,
    ], [
        'resubmitted_document_id' => 202,
        'resubmitted_tracking_number' => 'TRK-RESUB-202',
        'resubmitted_subject_matter' => 'First resubmission',
        'resubmitted_document_type_name' => 'Bill',
        'resubmitted_status' => 'Remanded',
        'resubmitted_status_badge_color' => '#D97706',
        'version_number' => 2,
        'is_current' => 0,
    ], [
        'resubmitted_document_id' => 303,
        'resubmitted_tracking_number' => 'TRK-RESUB-303',
        'resubmitted_subject_matter' => 'Current resubmission',
        'resubmitted_document_type_name' => 'Bill',
        'resubmitted_status' => 'Pending',
        'resubmitted_status_badge_color' => '#D97706',
        'version_number' => 3,
        'is_current' => 1,
    ]],
]);
verifyResubmissionCheck(str_contains($relatedHtml, 'Submission History'), 'Submission history section is missing.');
verifyResubmissionCheck(str_contains($relatedHtml, 'Original · Version 1'), 'Original submission version is missing.');
verifyResubmissionCheck(str_contains($relatedHtml, 'Version 2'), 'Intermediate resubmission version is missing.');
verifyResubmissionCheck(str_contains($relatedHtml, 'Version 3'), 'Current resubmission version is missing.');
verifyResubmissionCheck(str_contains($relatedHtml, 'Current resubmission'), 'Current version details are missing.');
verifyResubmissionCheck(str_contains($relatedHtml, '/admin/routed/show?id=202'), 'Related resubmission link is missing.');

$emptyHtml = renderResubmissionPartial([]);
verifyResubmissionCheck(trim($emptyHtml) === '', 'Partial should not render when there is no resubmission relationship.');

$detailViews = [
    'resources/views/admin/inbox/show.php',
    'resources/views/admin/routed/show.php',
    'resources/views/admin/communications/show.php',
    'resources/views/admin/filed-documents/show.php',
    'resources/views/admin/filed-documents/source-show.php',
    'resources/views/committee/cases/show.php',
    'resources/views/committee/communications/show.php',
    'resources/views/committee/hearing/show.php',
    'resources/views/committee/inbox/show.php',
    'resources/views/plenary/cases-first-reading/show.php',
    'resources/views/plenary/comm-first-reading/show.php',
    'resources/views/plenary/communications/show.php',
    'resources/views/plenary/first-reading/show.php',
    'resources/views/plenary/inbox/show.php',
    'resources/views/plenary/petitions/show.php',
    'resources/views/plenary/referred-to-committee/show.php',
    'resources/views/plenary/second-reading/show.php',
    'resources/views/plenary/status-list/show.php',
    'resources/views/receiving/inbox/show.php',
    'resources/views/receiving/routed-documents/show.php',
    'resources/views/spsec/comm-first-reading/show.php',
    'resources/views/spsec/comm-inbox/show.php',
    'resources/views/spsec/communications/show.php',
    'resources/views/spsec/inbox/show.php',
    'resources/views/spsec/routed/show.php',
];
foreach ($detailViews as $viewPath) {
    $view = file_get_contents(__DIR__ . '/../' . $viewPath);
    verifyResubmissionCheck(
        $view !== false && str_contains($view, 'partials/resubmission-relationship.php'),
        "Resubmission partial is missing from {$viewPath}."
    );
}

$detailControllers = [
    'app/controllers/Admin/AdminCommunicationsController.php',
    'app/controllers/Admin/AdminFiledDocumentsController.php',
    'app/controllers/Admin/AdminInboxController.php',
    'app/controllers/Admin/AdminRoutedDocumentController.php',
    'app/controllers/Committee/CommitteeCasesController.php',
    'app/controllers/Committee/CommitteeCommunicationsController.php',
    'app/controllers/Committee/CommitteeHearingController.php',
    'app/controllers/Committee/CommitteeInboxController.php',
    'app/controllers/Committee/CommitteeReferredController.php',
    'app/controllers/Committee/CommitteeReportsController.php',
    'app/controllers/Plenary/PlenaryCommController.php',
    'app/controllers/Plenary/PlenaryInboxController.php',
    'app/controllers/Plenary/PlenaryPetitionsController.php',
    'app/controllers/Plenary/PlenaryReadingController.php',
    'app/controllers/Plenary/PlenaryReportsController.php',
    'app/controllers/Receiving/ReceivingInboxController.php',
    'app/controllers/Receiving/RoutedDocumentController.php',
    'app/controllers/Spsec/SpsecCommFirstReadingController.php',
    'app/controllers/Spsec/SpsecCommInboxController.php',
    'app/controllers/Spsec/SpsecCommunicationsController.php',
    'app/controllers/Spsec/SpsecInboxController.php',
    'app/controllers/Spsec/SpsecRoutedDocumentController.php',
];
foreach ($detailControllers as $controllerPath) {
    $controller = file_get_contents(__DIR__ . '/../' . $controllerPath);
    verifyResubmissionCheck(
        $controller !== false
            && str_contains($controller, 'getResubmissionReference')
            && str_contains($controller, 'getRelatedResubmissions'),
        "Resubmission data is not loaded by {$controllerPath}."
    );
}

$routedDocumentViews = [
    'resources/views/admin/routed/index.php',
    'resources/views/receiving/routed-documents/index.php',
    'resources/views/spsec/routed/index.php',
];
foreach ($routedDocumentViews as $viewPath) {
    $view = file_get_contents(__DIR__ . '/../' . $viewPath);
    verifyResubmissionCheck(
        $view !== false && str_contains($view, "['is_resubmission']")
            && str_contains($view, '>Resubmission</span>'),
        "Routed documents table does not indicate resubmission in {$viewPath}."
    );
}

$documentService = file_get_contents(__DIR__ . '/../app/services/DocumentService.php');
verifyResubmissionCheck(
    $documentService !== false
        && str_contains($documentService, 'original_document_id')
        && str_contains($documentService, 'version_number')
        && str_contains($documentService, 'synchronizeResubmissionLegislativeDetails')
        && str_contains($documentService, 'inheritResubmissionLegislativeDetails'),
    'Document lineage and shared legislative data handling is missing.'
);

$eligibleQueryStart = strpos($documentService, 'public function getEligibleResubmissionDocuments');
$eligibleQueryEnd = strpos($documentService, 'public function getResubmissionLineage', $eligibleQueryStart);
$eligibleQuery = $eligibleQueryStart !== false && $eligibleQueryEnd !== false
    ? substr($documentService, $eligibleQueryStart, $eligibleQueryEnd - $eligibleQueryStart)
    : '';
verifyResubmissionCheck(
    str_contains($eligibleQuery, 'newer.referenced_document_id = d.id')
        && str_contains($eligibleQuery, 'd.document_type_id')
        && !str_contains($eligibleQuery, 'original_document_id')
        && !str_contains($eligibleQuery, 'version_number'),
    'Resubmission references query must work with the original parent-link schema.'
);

$receivingForm = file_get_contents(__DIR__ . '/../resources/views/receiving/receive-document/index.php');
$adminReceiveForm = file_get_contents(__DIR__ . '/../resources/views/admin/receive-document/index.php');
foreach ([
    'Receiving' => $receivingForm,
    'Admin direct receive' => $adminReceiveForm,
] as $formName => $formSource) {
    verifyResubmissionCheck(
        $formSource !== false
            && str_contains($formSource, 'resubmission_document_type_id')
            && str_contains($formSource, 'documentTypeSelect.disabled = true')
            && str_contains($formSource, 'document_type_id'),
        "{$formName} form does not lock the selected reference's document type."
    );
}

foreach ([
    'Receiving' => 'app/controllers/Receiving/RouteDocumentController.php',
    'Admin direct receive' => 'app/controllers/Admin/AdminReceiveDocumentController.php',
] as $controllerName => $controllerPath) {
    $controller = file_get_contents(__DIR__ . '/../' . $controllerPath);
    $referenceVariable = $controllerName === 'Receiving' ? '$referenceResult' : '$reference';
    $referenceTypeAssignment = strpos(
        $controller,
        '$data[\'document_type_id\'] = (int) ' . $referenceVariable . '[\'document\'][\'document_type_id\']'
    );
    $submissionValidation = strpos($controller, 'validateSubmission($data)');
    verifyResubmissionCheck(
        $controller !== false
            && $referenceTypeAssignment !== false
            && $submissionValidation !== false
            && $referenceTypeAssignment < $submissionValidation,
        "{$controllerName} must use the selected reference's document type before validating submission."
    );
}

$lineageMigration = file_get_contents(__DIR__ . '/../database/migrations/104_document_resubmission_lineage.php');
verifyResubmissionCheck(
    $lineageMigration !== false
        && str_contains($lineageMigration, 'original_document_id')
        && str_contains($lineageMigration, 'version_number')
        && str_contains($lineageMigration, 'uq_drl_original_version'),
    'Document lineage migration does not persist unique version numbers.'
);

echo "All resubmission checks passed." . PHP_EOL;
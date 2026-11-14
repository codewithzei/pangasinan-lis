<?php
/**
 * Structural regression checks for the shared Committee Report workflow.
 * Run with: php tests/committee_shared_report_transmittal_test.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/app/controllers/Committee/CommitteeReportsController.php');
$hearingController = file_get_contents($root . '/app/controllers/Committee/CommitteeHearingController.php');
$hearingIndex = file_get_contents($root . '/resources/views/committee/hearing/index.php');
$shareForm = file_get_contents($root . '/resources/views/committee/hearing/share-to-report.php');
$detailView = file_get_contents($root . '/resources/views/committee/reports/show.php');
$documentDetailView = file_get_contents($root . '/resources/views/receiving/routed-documents/show.php');
$listView = file_get_contents($root . '/resources/views/committee/reports/index.php');
$migration = file_get_contents($root . '/database/migrations/094_committee_report_attachment_transmittals.php');
$reportAttachmentsMigration = file_get_contents($root . '/database/migrations/095_create_committee_report_attachments.php');
$documentService = file_get_contents($root . '/app/services/DocumentService.php');
$plenaryInboxController = file_get_contents($root . '/app/controllers/Plenary/PlenaryInboxController.php');
$plenaryReadingController = file_get_contents($root . '/app/controllers/Plenary/PlenaryReadingController.php');
$plenaryInboxView = file_get_contents($root . '/resources/views/plenary/inbox/show.php');
$plenaryStatusView = file_get_contents($root . '/resources/views/plenary/status-list/show.php');

$start = strpos($controller, 'public function returnToPlenary(): void');
$end = strpos($controller, '// Private helpers (mirrored from CommitteeHearingController)', $start ?: 0);
$returnAction = $start !== false && $end !== false
    ? substr($controller, $start, $end - $start)
    : '';
$shareStart = strpos($hearingController, 'public function shareToReport(): void');
$shareEnd = strpos($hearingController, '// Private helpers', $shareStart ?: 0);
$shareAction = $shareStart !== false && $shareEnd !== false
    ? substr($hearingController, $shareStart, $shareEnd - $shareStart)
    : '';
$summaryPosition = strpos($shareForm, 'id="summary_of_findings"');
$dropZonePosition = strpos($shareForm, 'id="sharedReportDropZone"');

$checks = [
    'return action targets an individual document' => str_contains($returnAction, "\$_POST['item_id']")
        && str_contains($returnAction, "\$itemType = 'DOCUMENT';"),
    'documents must belong to the selected shared report' => str_contains($returnAction, 'crd.committee_report_id = ? AND d.id = ?'),
    'returned documents are routed into the Plenary inbox' => str_contains($returnAction, "SET current_phase = 'PLENARY'")
        && str_contains($returnAction, "VALUES (?, ?, 'PLENARY', ?, 'PENDING', NOW(), NULL, NULL)")
        && str_contains($returnAction, 'requirePlenaryRoleId()')
        && str_contains($returnAction, '$plenaryRoleId'),
    'attachments are loaded only from documents in the shared report' => str_contains($controller, 'WHERE da.document_id IN ({$docPlaceholders})'),
    'returning an item does not close the shared parent' => !str_contains($returnAction, 'UPDATE committee_reports'),
    'document status does not gate the return action' => !str_contains($returnAction, "requireDocumentStatus('Committee Report Created')"),
    'attachment transmissions have durable per-file storage' => str_contains($migration, 'UNIQUE KEY uq_crat_attachment (attachment_id)')
        && str_contains($migration, 'returned_at TIMESTAMP'),
    'shared report detail returns documents only' => str_contains($detailView, 'name="item_type" value="DOCUMENT"')
        && !str_contains($detailView, 'name="item_type" value="ATTACHMENT"')
        && !str_contains($detailView, 'Return this file to Plenary?'),
    'files inherit transmission from their parent document' => str_contains($detailView, '$fileReturned = !empty($doc[\'is_returned\'])'),
    'overall status includes partial progress' => str_contains($controller, 'Partially Returned')
        && str_contains($detailView, '$reportStatus'),
    'each report document links to report-scoped full details' => str_contains($controller, 'public function documentShow(): void')
        && str_contains($detailView, '/committee/reports/document-show?id=')
        && str_contains($controller, 'WHERE committee_report_id = ? AND document_id = ?')
        && str_contains($documentDetailView, 'Document Information'),
    'returned reports remain selectable as shared parents' => !str_contains($hearingController, 'WHERE cr.returned_to_plenary_at IS NULL'),
    'reports list no longer offers a report-wide return action' => !str_contains($listView, 'onclick="openReturnModal('),
    'hearing selection is available beyond the approved tab' => str_contains($hearingIndex, 'id="selectAllDocuments"')
        && !str_contains($hearingIndex, "if (\$tab === 'approved' && !empty(\$documents))"),
    'shared-report validation accepts documents with any hearing outcome' => !str_contains($shareAction, "ch.outcome    = 'APPROVED'"),
    'linking a document preserves its current status' => !str_contains($shareAction, 'UPDATE documents SET current_status_id')
        && str_contains($shareAction, 'document status unchanged'),
    'shared-report form accepts multiple attachment uploads' => str_contains($shareForm, 'enctype="multipart/form-data"')
        && str_contains($shareForm, 'name="attachments[]"')
        && str_contains($shareForm, 'multiple'),
    'drag-and-drop area follows Summary of Findings' => $summaryPosition !== false
        && $dropZonePosition !== false
        && $summaryPosition < $dropZonePosition,
    'drop zone handles dragged files and removable selections' => str_contains($shareForm, "addEventListener('drop'")
        && str_contains($shareForm, 'addEventListener(\'click\'')
        && str_contains($shareForm, 'selectedFiles.splice(index, 1)'),
    'uploads are validated and saved as report-owned files in both modes' => str_contains($shareAction, 'validateFileUploads($uploadedFiles, false)')
        && substr_count($shareAction, 'stageCommitteeReportFileUploads(') >= 2
        && substr_count($shareAction, 'insertStagedCommitteeReportAttachments(') >= 2
        && str_contains($reportAttachmentsMigration, 'committee_report_attachments')
        && str_contains($documentService, "uploads/committee-reports/")
        && str_contains($detailView, 'Committee Report Attachments')
        && str_contains($shareForm, 'separately from the selected documents'),
    'report-owned attachments can be added, replaced, and removed' => str_contains($controller, 'public function manageAttachments(): void')
        && str_contains($detailView, 'attachment_action" value="add"')
        && str_contains($detailView, 'attachment_action" value="replace"')
        && str_contains($detailView, 'attachment_action" value="remove"')
        && !str_contains($detailView, 'Save replacement')
        && str_contains($detailView, "this.form.requestSubmit()"),
    'report attachment management includes drag-and-drop previews' => str_contains($detailView, 'id="reportAttachmentDropZone"')
        && str_contains($detailView, "addEventListener('drop'")
        && str_contains($detailView, 'reportAttachmentPreviewList')
        && str_contains($detailView, 'id="reportAttachmentSaveButton" disabled')
        && str_contains($detailView, 'saveButton.disabled = selectedFiles.length === 0')
        && str_contains($detailView, 'Save'),
    'Remove appears before Preview for report attachments' => strpos($detailView, 'name="attachment_action" value="remove"') !== false
        && strpos($detailView, 'Preview') !== false
        && strpos($detailView, 'name="attachment_action" value="remove"') < strpos($detailView, 'Preview'),
    'Plenary loads and labels shared report attachments' => str_contains($plenaryInboxController, 'FROM committee_report_attachments cra')
        && str_contains($plenaryReadingController, 'FROM committee_report_attachments cra')
        && str_contains($plenaryInboxView, 'Committee Report #')
        && str_contains($plenaryStatusView, 'Committee Report #'),
    'share page displays each selected document status' => str_contains($shareForm, "\$doc['status'] ?? 'Status unavailable'"),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    $failed += $passed ? 0 : 1;
}

echo count($checks) . ' checks, ' . $failed . ' failure(s).' . PHP_EOL;
exit($failed === 0 ? 0 : 1);

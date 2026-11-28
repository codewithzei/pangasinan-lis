<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'database/migrations/097_plenary_committee_report_adoptions.php' => [
        "CREATE TABLE IF NOT EXISTS plenary_committee_report_adoptions",
        "ENUM('APPROVED', 'DISAPPROVED', 'ADOPTED')",
        'committee_report_id BIGINT UNSIGNED NOT NULL',
        'JSON_EXTRACT(return_event.metadata,',
    ],
    'app/controllers/Plenary/PlenaryReadingController.php' => [
        'public function adoptCommitteeReportStore(): void',
        "'APPROVED', 'DISAPPROVED'",
        "'Awaiting Second Reading'",
        "'Plenary: Committee Report Adopted'",
        "current_phase = 'PLENARY',",
        'finalized_at = NULL,',
        '$decision === \'APPROVED\' && ($isCommDocument || $isCasesDocument)',
        'FROM plenary_comm_first_reading',
        'FROM plenary_cases_first_reading',
        'private function findReturnedCommitteeReportId(int $documentId): int',
        "SELECT cc.final_outcome",
        "WHERE document_id = ? AND event_type = 'RETURNED_TO_PLENARY'",
        "private function recordAdoptedCommitteeReport(",
        'public function referredToCommitteeShow(): void',
        "['in_committee', 'returned', 'remanded', 'withdrawn', 'noted']",
        "\$outcomeStatusIds['remanded']",
        "\$outcomeStatusIds['withdrawn']",
        "\$outcomeStatusIds['noted']",
        "da2.phase = d.current_phase",
        "da3.phase = d.current_phase",
        "\$_GET['doc_type']",
        '$offset     = ($page - 1) * $perPage;',
        "AND d.document_type_id = ?",
        'SELECT id, name',
        'FROM document_events de',
        'FROM committee_hearings ch',
        'FROM committee_case_actions cca',
        "resources/views/plenary/referred-to-committee/show.php",
        "redirect('plenary/second-reading');",
        "redirect('plenary/reports');",
    ],
    'app/controllers/Plenary/PlenaryReportsController.php' => [
        'COUNT(DISTINCT pcra.committee_report_id)',
        'FROM plenary_committee_report_adoptions pcra',
        "WHERE pcra.committee_report_id = ?",
        'FROM committee_report_attachments',
        'committee_hearing_outcome',
        'first_reading_remarks',
        'returned_by_name',
        'public function documentShow(): void',
        'This document is not part of the selected adopted Committee Report.',
        'getResubmissionLineageDocumentIds($documentId)',
        'document_id IN ({$lineagePlaceholders})',
        "\$resubmissionDetailRoute = 'plenary/reports';",
        "\$resubmissionDetailAction = 'document-show';",
        "\$resubmissionDetailQuery = '&report_id=' . \$reportId;",
        'FROM document_routes dr',
        'FROM document_assignments da',
        'AS accepted_by_fullname',
        'getDocumentEvents($documentId)',
        "'COMMITTEE_REPORT' AS attachment_type",
        'WHERE cra.committee_report_id = ?',
        'ua.username AS uploaded_by_username',
        'FROM plenary_document_details',
        'FROM document_authors',
    ],
    'resources/views/receiving/routed-documents/show.php' => [
        '$assignments = $assignments ?? [];',
        "require __DIR__ . '/../../partials/assignment-history.php';",
        'sm:col-span-2',
    ],
    'resources/views/partials/resubmission-relationship.php' => [
        '$resubmissionDetailAction = $resubmissionDetailAction ?? \'show\';',
        '<?= htmlspecialchars($resubmissionDetailQuery) ?>',
    ],
    'resources/views/plenary/referred-to-committee/index.php' => [
        'data-decision="<?= $hearingApproved ? \'APPROVED\' : \'DISAPPROVED\' ?>"',
        'id="reportAdoptionConfirmationDecision"',
        'Confirm Committee Report Adoption',
        'Approve &amp; Proceed to 2nd Reading',
        'Adopt Report Only',
        'max-w-56 flex-col items-end gap-2',
        '<span>View details</span>',
        '<span>Adopt Committee Report</span>',
        '<span>Adopt Report Only</span>',
        'aria-label="<?= $hearingApproved ? \'Approve and proceed to Second Reading\' : \'Adopt report only\' ?> for',
        "function rtcBuildDetailUrl(",
        "'remanded' => 'Remanded'",
        "'withdrawn' => 'Withdrawn'",
        "'noted' => 'Noted'",
        "\$outcomeCounts['remanded']",
        "return BASE_URL . '/plenary/referred-to-committee/show?' . http_build_query(\$params);",
        'Hearing Status',
        'hidden overflow-x-auto xl:block',
        'space-y-4 border-t border-gray-100 px-4 py-4 sm:px-6 xl:hidden',
        'id="rtc-mobile-search"',
        'id="rtcMobileFilterSheet"',
        "require __DIR__ . '/row-actions.php';",
        'inline-flex max-w-full items-center justify-center gap-1.5 whitespace-normal rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-center text-xs font-medium text-gray-700',
        'aria-label="View details for <?= htmlspecialchars($row[\'tracking_number\'], ENT_QUOTES, \'UTF-8\') ?>"',
        'Showing 0 records',
        'name="doc_type"',
        'All document types',
        'text-sm font-medium leading-5',
        "rtcBuildPageUrl(\$tab, \$page + 1, \$search, \$documentTypeId)",
        '$row[\'committee_hearing_outcome\']',
        "'Pending'",
        "'APPROVED' => '#16A34A'",
        '$hearingApproved && !empty($row[\'is_plenary_cases_referred\'])',
        '$hearingApproved && !empty($row[\'is_plenary_comm_referred\'])',
        'openAdoptModal(',
        'openAdoptCasesModal(',
        "var dt = typeof DataTransfer === 'function' ? new DataTransfer() : null;",
        "var casesDt = typeof DataTransfer === 'function' ? new DataTransfer() : null;",
        'Use the file picker to attach files in this browser.',
    ],
    'resources/views/plenary/first-reading/index.php' => [
        'inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5',
        'inline-flex shrink-0 items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5',
        '<span>Process</span>',
    ],
    'resources/views/plenary/inbox/index.php' => [
        'inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5',
        'inline-flex shrink-0 items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5',
        '<span><?= $currentView === \'inbox\' ? \'Process\' : \'View\' ?></span>',
    ],
    'resources/views/plenary/reports/index.php' => [
        'Adopted Committee Reports',
        '/plenary/reports/show?id=',
        'role="status" aria-live="polite"',
        'role="alert"',
    ],
    'resources/views/plenary/second-reading/index.php' => [
        'role="status" aria-live="polite"',
        'role="alert"',
    ],
    'resources/views/plenary/reports/show.php' => [
        'Legislative Documents and Plenary Actions',
        'Committee Findings and Recommendations',
        'Official Committee Report Copy',
        'First Reading Referral',
        'Committee Hearing',
        'Plenary Remarks',
        'DISAPPROVED',
        '/public/',
        '/plenary/reports/document-show?id=',
        'View Full Document Details',
    ],
    'routes/web.php' => [
        "'plenary/referred-to-committee/show' => [",
        "'plenary/referred-to-committee/adopt-report' => [",
        "'plenary/reports' => [",
        "'plenary/reports/show' => [",
        "'plenary/reports/document-show' => [",
    ],
    'resources/views/layouts/sidebar.php' => [
        '<?= BASE_URL ?>/plenary/reports',
        'Plenary Reports',
        "in_array(\$currentRoute, ['plenary/cases-first-reading/show', 'plenary/comm-first-reading/show'], true)",
        "\$currentRoute = 'plenary/first-reading';",
    ],
    'resources/views/plenary/cases-first-reading/show.php' => [
        'name="reading_date"',
        'focus:ring-primary/20"',
    ],
    'resources/views/plenary/comm-first-reading/show.php' => [
        'name="reading_date"',
        'focus:ring-primary/20">',
        'Action History',
        'Assignment History',
        'data-attachment-preview',
        'space-y-3 sm:hidden',
        'hidden overflow-x-auto sm:block',
        'flex items-center justify-end gap-3 border-t border-gray-100 pt-4',
        "\$mfrDetailRoute = 'plenary/inbox';",
        "\$resubmissionDetailRoute = 'plenary/inbox';",
    ],
];

$detailViewPath = $root . '/resources/views/plenary/referred-to-committee/show.php';
$detailView = file_get_contents($detailViewPath);
$failed = 0;
if ($detailView === false
    || !str_contains($detailView, 'Legislative history')
    || !str_contains($detailView, 'Committee reports')
    || !str_contains($detailView, 'Document attachments')) {
    fwrite(STDERR, '[FAIL] Legislative document detail page is missing its full history sections.' . PHP_EOL);
    $failed++;
}

foreach ($checks as $relativePath => $needles) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $contents = file_get_contents($path);
    if ($contents === false) {
        fwrite(STDERR, "[FAIL] Could not read {$relativePath}" . PHP_EOL);
        $failed++;
        continue;
    }

    foreach ($needles as $needle) {
        if (!str_contains($contents, $needle)) {
            fwrite(STDERR, "[FAIL] {$relativePath} is missing: {$needle}" . PHP_EOL);
            $failed++;
        }
    }
}

$datePickerViews = [
    'resources/views/plenary/cases-first-reading/show.php',
    'resources/views/plenary/comm-first-reading/show.php',
];
foreach ($datePickerViews as $relativePath) {
    $contents = file_get_contents($root . '/' . $relativePath);
    $hasFullWidthDatePicker = $contents !== false
        && preg_match('/<input type="date" id="reading_date"[\s\S]*?class="([^"]*)"/', $contents, $dateInput) === 1
        && str_contains($dateInput[1], 'w-full')
        && !str_contains($dateInput[1], 'sm:w-52');
    if (!$hasFullWidthDatePicker) {
        fwrite(STDERR, "[FAIL] {$relativePath} reading date picker must remain full width at all breakpoints." . PHP_EOL);
        $failed++;
    }
}

$commFirstReadingView = file_get_contents($root . '/resources/views/plenary/comm-first-reading/show.php');
if ($commFirstReadingView === false
    || str_contains($commFirstReadingView, 'communication_category_id')
    || str_contains($commFirstReadingView, 'Communication Category')) {
    fwrite(STDERR, '[FAIL] Communication First Reading Noted decision must not request a Communication Category.' . PHP_EOL);
    $failed++;
}

$controller = file_get_contents($root . '/app/controllers/Plenary/PlenaryReadingController.php');
$commNotedStart = strpos((string) $controller, 'private function doCommFrNoted(');
$commNotedEnd = strpos((string) $controller, 'private function doCommFrReferToCommittee(', $commNotedStart ?: 0);
$commNotedHandler = $commNotedStart !== false && $commNotedEnd !== false
    ? substr((string) $controller, $commNotedStart, $commNotedEnd - $commNotedStart)
    : '';
if ($commNotedHandler === ''
    || str_contains($commNotedHandler, 'communication_category_id')
    || !str_contains($commNotedHandler, "VALUES (?, 'NOTED', ?, ?, ?, NOW(), ?)")) {
    fwrite(STDERR, '[FAIL] Communication First Reading Noted handler must store without a category.' . PHP_EOL);
    $failed++;
}

$sharedRecorderCalls = substr_count((string) $controller, '$this->recordAdoptedCommitteeReport(');
if ($sharedRecorderCalls < 2) {
    fwrite(STDERR, '[FAIL] Communication and cases adoption paths must also archive the Committee Report.' . PHP_EOL);
    $failed++;
}

$referredView = file_get_contents($root . '/resources/views/plenary/referred-to-committee/index.php');
$safeTrackingArguments = substr_count(
    (string) $referredView,
    "htmlspecialchars(json_encode((string) \$row['tracking_number']"
);
if ($safeTrackingArguments !== 2) {
    fwrite(STDERR, '[FAIL] Both adoption modal triggers must safely encode tracking numbers for JavaScript.' . PHP_EOL);
    $failed++;
}

if (str_contains((string) $referredView, "if (dt) { fileInput.value = ''; }")
    || str_contains((string) $referredView, "if (casesDt) { casesFileInput.value = ''; }")) {
    fwrite(STDERR, '[FAIL] Adoption file inputs must retain the selected files after adding them to the accumulator.' . PHP_EOL);
    $failed++;
}

if (str_contains((string) $referredView, '>Referred On</th>')) {
    fwrite(STDERR, '[FAIL] Referred On should not be a column on the Plenary referred-to-committee table.' . PHP_EOL);
    $failed++;
}
if (str_contains((string) $referredView, 'rtcDocumentDetailsModal')
    || str_contains((string) $referredView, 'data-rtc-details=')) {
    fwrite(STDERR, '[FAIL] Legislative document details should open as a separate page, not a modal.' . PHP_EOL);
    $failed++;
}

if ($failed > 0) {
    fwrite(STDERR, "{$failed} Plenary Committee Report archive check(s) failed." . PHP_EOL);
    exit(1);
}

echo "Plenary Committee Report archive checks passed." . PHP_EOL;

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
        'private function findReturnedCommitteeReportId(int $documentId): int',
        "WHERE document_id = ? AND event_type = 'RETURNED_TO_PLENARY'",
        "private function recordAdoptedCommitteeReport(",
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
    ],
    'resources/views/plenary/referred-to-committee/index.php' => [
        'data-decision="<?= $hearingApproved ? \'APPROVED\' : \'DISAPPROVED\' ?>"',
        'id="reportAdoptionConfirmationDecision"',
        'Confirm Committee Report Adoption',
        'Approve &amp; Proceed to 2nd Reading',
        'Adopt Report Only (Disapproved)',
        'openAdoptModal(',
        'openAdoptCasesModal(',
        "var dt = typeof DataTransfer === 'function' ? new DataTransfer() : null;",
        "var casesDt = typeof DataTransfer === 'function' ? new DataTransfer() : null;",
        'Use the file picker to attach files in this browser.',
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
    ],
    'routes/web.php' => [
        "'plenary/referred-to-committee/adopt-report' => [",
        "'plenary/reports' => [",
        "'plenary/reports/show' => [",
    ],
    'resources/views/layouts/sidebar.php' => [
        '<?= BASE_URL ?>/plenary/reports',
        'Plenary Reports',
    ],
];

$failed = 0;
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

$controller = file_get_contents($root . '/app/controllers/Plenary/PlenaryReadingController.php');
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

if ($failed > 0) {
    fwrite(STDERR, "{$failed} Plenary Committee Report archive check(s) failed." . PHP_EOL);
    exit(1);
}

echo "Plenary Committee Report archive checks passed." . PHP_EOL;

<?php

$document = $document ?? [];
$timeline = $timeline ?? [];
$hearings = $hearings ?? [];
$reports = $reports ?? [];
$committees = $committees ?? [];
$authors = $authors ?? [];
$reportAttachments = $reportAttachments ?? [];
$documentAttachments = $documentAttachments ?? [];
$adoptionFiles = $adoptionFiles ?? [];
$caseRecord = $caseRecord ?? null;
$caseActions = $caseActions ?? [];
$caseActionAttachments = $caseActionAttachments ?? [];
$secondReadingDetails = $secondReadingDetails ?? [];
$routes = $routes ?? [];
$checklistItems = $checklistItems ?? [];

// Reuse the existing routed-document viewing layout for consistent details.
$document['status'] = $document['document_status'] ?? $document['status'] ?? '—';
$document['status_badge_color'] = $document['status_badge_color'] ?? '#6B7280';
$document['document_type_badge_color'] = $document['document_type_badge_color'] ?? '#2563EB';
$document['source_type'] = $document['source_type_name'] ?? $document['source_type'] ?? '—';
$attachments = $documentAttachments;
$pageSubtitle = $document['tracking_number'] ?? 'Document Details';
$detailBackUrl = $backUrl ?? BASE_URL . '/plenary/referred-to-committee';
$detailBackLabel = 'Back to Referred to Committee';
$showMfrRelationship = false;

$documentEvents = array_map(static function (array $entry): array {
    return [
        'event_type' => $entry['record_type'] === 'event'
            ? ($entry['event_type'] ?? $entry['title'] ?? 'OTHER')
            : ($entry['title'] ?? 'WORKFLOW_ACTION'),
        'phase' => $entry['phase'] ?? null,
        'created_at' => $entry['created_at'] ?? null,
        'performed_by_name' => $entry['performed_by_name'] ?? null,
        'from_status_name' => $entry['from_status'] ?? null,
        'to_status_name' => $entry['to_status'] ?? null,
        'remarks' => $entry['remarks'] ?? null,
    ];
}, $timeline);

require __DIR__ . '/../../receiving/routed-documents/show.php';
return;

function rtcHistoryDate(?string $date, bool $withTime = true): string
{
    if (!$date) {
        return 'Date unavailable';
    }

    $timestamp = strtotime($date);
    return $timestamp ? date($withTime ? 'M j, Y g:i A' : 'M j, Y', $timestamp) : 'Date unavailable';
}

function rtcHistoryFileUrl(string $path): string
{
    return BASE_URL . '/public/' . ltrim($path, '/\\');
}

ob_start();
?>
<div class="detail-page space-y-6">
    <a href="<?= htmlspecialchars($backUrl ?? BASE_URL . '/plenary/referred-to-committee', ENT_QUOTES, 'UTF-8') ?>"
       class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-primary">
        <span aria-hidden="true">←</span> Back to Referred to Committee
    </a>

    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-7 sm:px-8">
            <p class="text-sm font-medium text-blue-100">PLENARY / LEGISLATIVE RECORD / DOCUMENT HISTORY</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                <?= htmlspecialchars($document['tracking_number'] ?? 'Legislative document') ?>
            </h1>
            <p class="mt-2 max-w-4xl whitespace-pre-wrap text-sm leading-6 text-blue-100">
                <?= htmlspecialchars($document['subject_matter'] ?? '—') ?>
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
                <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-white">
                    <?= htmlspecialchars($document['document_type_name'] ?? 'Document type unavailable') ?>
                </span>
                <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-white">
                    Current status: <?= htmlspecialchars($document['document_status'] ?? '—') ?>
                </span>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-56 w-56 rounded-full bg-white/10"></div>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Date received</p>
            <p class="mt-2 text-sm font-medium text-gray-900"><?= htmlspecialchars(rtcHistoryDate($document['date_received'] ?? null, false)) ?></p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Proposed number</p>
            <p class="mt-2 text-sm font-medium text-gray-900"><?= htmlspecialchars($document['proposed_number'] ?: 'Not assigned') ?></p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Committee(s)</p>
            <p class="mt-2 text-sm font-medium text-gray-900"><?= htmlspecialchars($committees !== [] ? implode(', ', $committees) : 'Not recorded') ?></p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Current phase</p>
            <p class="mt-2 text-sm font-medium text-gray-900"><?= htmlspecialchars($document['current_phase'] ?? '—') ?></p>
        </div>
    </section>

    <section class="rounded-2xl border border-gray-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-gray-900">Document and source information</h2>
        <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <?php foreach ([
                'Source type' => $document['source_type_name'] ?? null,
                'Source / originating office' => $document['source_name'] ?? null,
                'Source liaison' => $document['source_liaison_name'] ?? null,
                'Source contact number' => $document['source_contact_number'] ?? null,
                'Source address' => $document['source_address'] ?? null,
                'Received at' => !empty($document['time_received']) ? date('g:i A', strtotime($document['time_received'])) : null,
            ] as $label => $value): ?>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400"><?= htmlspecialchars($label) ?></dt>
                    <dd class="mt-1 whitespace-pre-wrap text-sm text-gray-800"><?= htmlspecialchars($value ?: '—') ?></dd>
                </div>
            <?php endforeach; ?>
            <div class="sm:col-span-2 xl:col-span-3">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Author(s) / proponent(s)</dt>
                <dd class="mt-1 text-sm text-gray-800">
                    <?php if ($authors === []): ?>
                        Not recorded
                    <?php else: ?>
                        <?= htmlspecialchars(implode(', ', array_map(
                            static fn(array $author): string => trim(($author['full_name'] ?: 'Unknown') . ' (' . ucwords(strtolower(str_replace('_', ' ', $author['author_role']))) . ')'),
                            $authors
                        ))) ?>
                    <?php endif; ?>
                </dd>
            </div>
        </dl>
    </section>

    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
        <div class="border-b border-gray-100 px-6 py-5">
            <h2 class="text-lg font-semibold text-gray-900">Legislative history</h2>
            <p class="mt-1 text-sm text-gray-500">Chronological record from document intake through committee and plenary actions.</p>
        </div>
        <?php if ($timeline === []): ?>
            <p class="px-6 py-10 text-center text-sm text-gray-500">No workflow history has been recorded for this document yet.</p>
        <?php else: ?>
            <ol class="divide-y divide-gray-100">
                <?php foreach ($timeline as $entry): ?>
                    <?php
                    $phase = (string) ($entry['phase'] ?? '');
                    $metadata = $entry['metadata'] ?? [];
                    ?>
                    <li class="relative px-6 py-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($entry['title'] ?? 'Workflow action') ?></h3>
                                    <?php if ($phase !== ''): ?>
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600"><?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $phase)))) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($entry['performed_by_name'])): ?>
                                    <p class="mt-1 text-xs text-gray-500">Recorded by <?= htmlspecialchars($entry['performed_by_name']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($entry['from_status']) || !empty($entry['to_status'])): ?>
                                    <p class="mt-2 text-xs text-gray-600">
                                        Status: <?= htmlspecialchars($entry['from_status'] ?: '—') ?>
                                        <span aria-hidden="true">→</span>
                                        <?= htmlspecialchars($entry['to_status'] ?: '—') ?>
                                    </p>
                                <?php endif; ?>
                                <?php if (!empty($entry['remarks'])): ?>
                                    <p class="mt-3 whitespace-pre-wrap text-sm leading-6 text-gray-700"><?= htmlspecialchars($entry['remarks']) ?></p>
                                <?php endif; ?>
                                <?php if ($metadata !== []): ?>
                                    <ul class="mt-3 space-y-1 text-xs text-gray-500">
                                        <?php foreach ($metadata as $key => $value): ?>
                                            <?php
                                            if (is_array($value) || is_object($value)) {
                                                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                            }
                                            $metadataLabel = is_string($key) ? ucwords(str_replace('_', ' ', $key)) . ': ' : '';
                                            ?>
                                            <li><?= htmlspecialchars($metadataLabel . (string) $value) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                            <time class="shrink-0 text-xs text-gray-500"><?= htmlspecialchars(rtcHistoryDate($entry['created_at'] ?? null)) ?></time>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

    <?php if ($caseRecord): ?>
        <section class="rounded-2xl border border-gray-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-gray-900">Committee case record</h2>
            <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <?php foreach ([
                    'Docket number' => $caseRecord['docket_number'] ?? null,
                    'Date assigned' => !empty($caseRecord['date_assigned']) ? rtcHistoryDate($caseRecord['date_assigned'], false) : null,
                    'Nature of case' => $caseRecord['nature_of_case'] ?? null,
                    'Complainant' => $caseRecord['complainant_details'] ?? null,
                    'Complainant municipality' => $caseRecord['complainant_municipality'] ?? null,
                    'Respondents' => $caseRecord['respondents'] ?? null,
                    'Assigned by' => $caseRecord['assigned_by_name'] ?? null,
                    'Final outcome' => $caseRecord['final_outcome'] ?? null,
                ] as $label => $value): ?>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400"><?= htmlspecialchars($label) ?></dt>
                        <dd class="mt-1 whitespace-pre-wrap text-sm text-gray-800"><?= htmlspecialchars($value ?: '—') ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
            <?php if ($caseActions !== []): ?>
                <div class="mt-6 border-t border-gray-100 pt-5">
                    <h3 class="text-sm font-semibold text-gray-900">Case proceedings</h3>
                    <ol class="mt-3 space-y-3">
                        <?php foreach ($caseActions as $action): ?>
                            <li class="rounded-xl bg-gray-50 p-4">
                                <div class="flex flex-wrap justify-between gap-2">
                                    <p class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($action['action_type']) ?></p>
                                    <time class="text-xs text-gray-500"><?= htmlspecialchars(rtcHistoryDate($action['action_date'], false)) ?></time>
                                </div>
                                <p class="mt-2 whitespace-pre-wrap text-sm text-gray-700"><?= htmlspecialchars($action['description']) ?></p>
                                <?php if (!empty($action['notes'])): ?>
                                    <p class="mt-2 whitespace-pre-wrap text-xs text-gray-500"><?= htmlspecialchars($action['notes']) ?></p>
                                <?php endif; ?>
                                <p class="mt-2 text-xs text-gray-400">Recorded by <?= htmlspecialchars($action['created_by_name']) ?></p>
                                <?php if (!empty($caseActionAttachments[(int) $action['id']])): ?>
                                    <ul class="mt-3 space-y-1">
                                        <?php foreach ($caseActionAttachments[(int) $action['id']] as $file): ?>
                                            <li>
                                                <a href="<?= htmlspecialchars(rtcHistoryFileUrl($file['stored_path'])) ?>" target="_blank" rel="noopener"
                                                   class="text-xs font-semibold text-primary hover:underline">
                                                    View attachment: <?= htmlspecialchars($file['file_name']) ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($reports !== []): ?>
        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-gray-900">Committee reports</h2>
            <?php foreach ($reports as $report): ?>
                <article class="rounded-2xl border border-gray-200 bg-white p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-gray-900">
                                <?= ($report['report_type'] ?? '') === 'JOINT_COMMITTEE_REPORT' ? 'Joint Committee Report' : 'Committee Report' ?>
                                <?= !empty($report['report_number']) ? ' · #' . htmlspecialchars($report['report_number']) : '' ?>
                            </h3>
                            <p class="mt-1 text-xs text-gray-500">
                                Prepared <?= htmlspecialchars(rtcHistoryDate($report['created_at'] ?? null)) ?>
                                · <?= htmlspecialchars($report['created_by_name'] ?? '—') ?>
                            </p>
                        </div>
                        <?php if (!empty($report['returned_to_plenary_at'])): ?>
                            <span class="rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold text-violet-800">
                                Returned <?= htmlspecialchars(rtcHistoryDate($report['returned_to_plenary_at'])) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($report['committee_names'])): ?>
                        <p class="mt-3 text-xs text-gray-500">Committee(s): <?= htmlspecialchars($report['committee_names']) ?></p>
                    <?php endif; ?>
                    <div class="mt-4 whitespace-pre-wrap text-sm leading-6 text-gray-700">
                        <?= htmlspecialchars($report['summary_of_findings'] ?: 'No findings or recommendations recorded.') ?>
                    </div>
                    <?php
                    $reportFiles = array_filter(
                        $reportAttachments,
                        static fn(array $file): bool => (int) $file['report_id'] === (int) $report['id']
                    );
                    ?>
                    <?php if ($reportFiles !== []): ?>
                        <ul class="mt-4 space-y-2 border-t border-gray-100 pt-4">
                            <?php foreach ($reportFiles as $file): ?>
                                <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                    <span class="text-gray-700">
                                        <?= htmlspecialchars($file['file_name']) ?>
                                        <span class="ml-1 text-xs text-gray-400"><?= number_format((int) $file['file_size'] / 1024, 1) ?> KB</span>
                                    </span>
                                    <a href="<?= htmlspecialchars(rtcHistoryFileUrl($file['stored_path'])) ?>" target="_blank" rel="noopener"
                                       class="text-xs font-semibold text-primary hover:underline">View report copy</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if ($secondReadingDetails !== []): ?>
        <section class="rounded-2xl border border-gray-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-gray-900">Second Reading disposition</h2>
            <div class="mt-4 space-y-4">
                <?php foreach ($secondReadingDetails as $detail): ?>
                    <dl class="grid grid-cols-1 gap-4 rounded-xl bg-gray-50 p-4 sm:grid-cols-2">
                        <?php foreach ([
                            'Approved number' => $detail['approved_no'] ?? null,
                            'Date finalized' => !empty($detail['date_finalized']) ? rtcHistoryDate($detail['date_finalized'], false) : null,
                            'Approved subject matter' => $detail['approved_subject_matter'] ?? null,
                            'Date deferred' => !empty($detail['date_deferred']) ? rtcHistoryDate($detail['date_deferred'], false) : null,
                            'Deferral remarks' => $detail['deferred_remarks'] ?? null,
                            'Date withdrawn' => !empty($detail['date_withdrawn']) ? rtcHistoryDate($detail['date_withdrawn'], false) : null,
                            'Withdrawal remarks' => $detail['withdrawal_remark'] ?? null,
                        ] as $label => $value): ?>
                            <?php if (!empty($value)): ?>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400"><?= htmlspecialchars($label) ?></dt>
                                    <dd class="mt-1 whitespace-pre-wrap text-sm text-gray-800"><?= htmlspecialchars((string) $value) ?></dd>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if (!empty($detail['compliance_file_path'])): ?>
                            <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Compliance attachment</dt>
                        <dd class="mt-1">
                            <a href="<?= htmlspecialchars(rtcHistoryFileUrl($detail['compliance_file_path'])) ?>" target="_blank" rel="noopener"
                               class="text-sm font-semibold text-primary hover:underline">
                                <?= htmlspecialchars($detail['compliance_file_name'] ?: 'Open compliance file') ?>
                            </a>
                        </dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($adoptionFiles !== []): ?>
        <section class="rounded-2xl border border-gray-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-gray-900">Plenary adoption and final output files</h2>
            <ul class="mt-4 divide-y divide-gray-100">
                <?php foreach ($adoptionFiles as $file): ?>
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div>
                            <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($file['file_name']) ?></p>
                            <p class="mt-1 text-xs text-gray-500">
                                <?= htmlspecialchars(rtcHistoryDate($file['created_at'] ?? null)) ?>
                                · <?= number_format((int) $file['file_size'] / 1024, 1) ?> KB
                            </p>
                        </div>
                        <a href="<?= htmlspecialchars(rtcHistoryFileUrl($file['stored_path'])) ?>" target="_blank" rel="noopener"
                           class="text-xs font-semibold text-primary hover:underline">Open final output</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <section class="rounded-2xl border border-gray-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-gray-900">Document attachments</h2>
        <?php if ($documentAttachments === []): ?>
            <p class="mt-3 text-sm text-gray-500">No document attachments have been recorded.</p>
        <?php else: ?>
            <ul class="mt-4 divide-y divide-gray-100">
                <?php foreach ($documentAttachments as $attachment): ?>
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div>
                            <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($attachment['file_name']) ?></p>
                            <p class="mt-1 text-xs text-gray-500">
                                <?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $attachment['phase'])))) ?>
                                · <?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $attachment['attachment_type'])))) ?>
                                · <?= htmlspecialchars(rtcHistoryDate($attachment['created_at'] ?? null)) ?>
                                · <?= number_format((int) $attachment['file_size'] / 1024, 1) ?> KB
                            </p>
                        </div>
                        <a href="<?= htmlspecialchars(rtcHistoryFileUrl($attachment['stored_path'])) ?>" target="_blank" rel="noopener"
                           class="text-xs font-semibold text-primary hover:underline">Open file</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
<?php
$content = ob_get_clean();
$pageModals = '';
require __DIR__ . '/../../layouts/app.php';

<?php

$report = $report ?? [];
$adoptions = $adoptions ?? [];
$reportAttachments = $reportAttachments ?? [];
$reportCommittees = $reportCommittees ?? [];
$reportType = ($report['report_type'] ?? '') === 'JOINT_COMMITTEE_REPORT'
    ? 'Joint Committee Report'
    : 'Committee Report';

$decisionLabels = [
    'APPROVED' => 'Approved · Proceeded to Second Reading',
    'DISAPPROVED' => 'Disapproved · Adopted as report copy only',
    'ADOPTED' => 'Adopted · Finalized without Second Reading',
];
$decisionClasses = [
    'APPROVED' => 'bg-emerald-100 text-emerald-800',
    'DISAPPROVED' => 'bg-amber-100 text-amber-800',
    'ADOPTED' => 'bg-blue-100 text-blue-800',
];
$sessionLabels = [
    'REGULAR' => 'Regular',
    'REGULAR_ONLINE' => 'Regular (Online)',
    'SPECIAL' => 'Special',
    'SPECIAL_ONLINE' => 'Special (Online)',
];
$approvedCount = count(array_filter($adoptions, static fn(array $item): bool => $item['decision'] === 'APPROVED'));
$disapprovedCount = count(array_filter($adoptions, static fn(array $item): bool => $item['decision'] === 'DISAPPROVED'));

ob_start();
?>
<div class="space-y-6">
    <a href="<?= BASE_URL ?>/plenary/reports"
       class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-primary">
        <span aria-hidden="true">←</span> Back to Plenary Reports
    </a>

    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-7 sm:px-8">
            <p class="text-sm font-medium text-blue-100">PLENARY / LEGISLATIVE RECORDS / ADOPTED COMMITTEE REPORT</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                <?= htmlspecialchars($reportType) ?>
                <?= !empty($report['report_number']) ? '#' . htmlspecialchars($report['report_number']) : ' #' . (int) $report['id'] ?>
            </h1>
            <p class="mt-2 text-sm text-blue-100">
                <?= count($adoptions) ?> legislative document<?= count($adoptions) === 1 ? '' : 's' ?>
                · <?= $approvedCount ?> proceeded to Second Reading
                · <?= $disapprovedCount ?> adopted as report copy only
            </p>
            <div class="pointer-events-none absolute -right-10 -top-20 h-56 w-56 rounded-full bg-white/10"></div>
        </div>
    </section>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <aside class="space-y-4">
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Legislative Report Information</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">Report Number</dt>
                        <dd class="text-right font-medium text-gray-900"><?= htmlspecialchars($report['report_number'] ?? '—') ?></dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">Report Type</dt>
                        <dd class="text-right font-medium text-gray-900"><?= htmlspecialchars($reportType) ?></dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">Prepared By</dt>
                        <dd class="text-right text-gray-900"><?= htmlspecialchars($report['created_by_name'] ?? '—') ?></dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">Prepared</dt>
                        <dd class="text-right text-gray-900">
                            <?= !empty($report['created_at']) ? htmlspecialchars(date('M j, Y g:i A', strtotime($report['created_at']))) : '—' ?>
                        </dd>
                    </div>
                    <?php if (!empty($report['returned_to_plenary_at'])): ?>
                        <div class="flex justify-between gap-3 border-t border-gray-100 pt-3">
                            <dt class="text-gray-500">Returned to Plenary</dt>
                            <dd class="text-right text-gray-900"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($report['returned_to_plenary_at']))) ?></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">Transmitted By</dt>
                            <dd class="text-right text-gray-900"><?= htmlspecialchars($report['returned_by_name'] ?? '—') ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($reportCommittees !== []): ?>
                        <div class="border-t border-gray-100 pt-3">
                            <dt class="text-gray-500">Reporting Committee<?= count($reportCommittees) === 1 ? '' : 's' ?></dt>
                            <dd class="mt-1 font-medium text-gray-900"><?= htmlspecialchars(implode(', ', $reportCommittees)) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </section>

            <section class="rounded-2xl border border-emerald-200 bg-white p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Official Committee Report Copy</h2>
                        <p class="mt-1 text-xs text-gray-500"><?= count($reportAttachments) ?> file<?= count($reportAttachments) === 1 ? '' : 's' ?> transmitted</p>
                    </div>
                </div>
                <?php if ($reportAttachments === []): ?>
                    <p class="mt-4 rounded-lg bg-gray-50 px-3 py-3 text-sm italic text-gray-500">No report copy is attached to this record.</p>
                <?php else: ?>
                    <ul class="mt-4 space-y-2">
                        <?php foreach ($reportAttachments as $attachment): ?>
                            <?php $attachmentUrl = BASE_URL . '/public/' . ltrim((string) $attachment['stored_path'], '/\\'); ?>
                            <li class="rounded-xl border border-emerald-100 bg-emerald-50/40 p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="break-words text-sm font-medium text-gray-800"><?= htmlspecialchars($attachment['file_name']) ?></p>
                                        <p class="mt-1 text-xs text-gray-500">
                                            <?= number_format((int) $attachment['file_size'] / 1024, 1) ?> KB
                                            · <?= !empty($attachment['created_at']) ? htmlspecialchars(date('M j, Y', strtotime($attachment['created_at']))) : 'Date unavailable' ?>
                                        </p>
                                    </div>
                                    <a href="<?= htmlspecialchars($attachmentUrl) ?>" target="_blank" rel="noopener"
                                       class="shrink-0 rounded-lg border border-emerald-200 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-50">
                                        View Copy
                                    </a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </aside>

        <div class="space-y-6 xl:col-span-2">
            <section class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="text-sm font-semibold text-gray-900">Committee Findings and Recommendations</h2>
                <?php if (!empty($report['summary_of_findings'])): ?>
                    <div class="mt-3 whitespace-pre-wrap text-sm leading-7 text-gray-700"><?= htmlspecialchars($report['summary_of_findings']) ?></div>
                <?php else: ?>
                    <p class="mt-3 text-sm italic text-gray-500">No findings or recommendations were recorded.</p>
                <?php endif; ?>
            </section>

            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
                <div class="border-b border-gray-100 px-5 py-4">
                    <h2 class="font-semibold text-gray-900">Legislative Documents and Plenary Actions</h2>
                    <p class="mt-1 text-xs text-gray-500">Referral, Committee hearing outcome, and Plenary disposition for each document covered by this report.</p>
                </div>

                <?php if ($adoptions === []): ?>
                    <p class="px-5 py-10 text-center text-sm text-gray-500">No legislative adoption records are linked to this Committee Report.</p>
                <?php else: ?>
                    <div class="divide-y divide-gray-100">
                        <?php foreach ($adoptions as $adoption): ?>
                            <?php
                                $decision = (string) ($adoption['decision'] ?? '');
                                $decisionClass = $decisionClasses[$decision] ?? 'bg-gray-100 text-gray-700';
                                $decisionLabel = $decisionLabels[$decision] ?? $decision;
                                $hearingOutcome = (string) ($adoption['committee_hearing_outcome'] ?? '');
                                $hearingClass = $hearingOutcome === 'APPROVED'
                                    ? 'bg-emerald-100 text-emerald-800'
                                    : ($hearingOutcome !== '' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600');
                            ?>
                            <article class="p-5">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="font-mono text-sm font-bold text-primary"><?= htmlspecialchars($adoption['tracking_number']) ?></p>
                                        <h3 class="mt-1 text-base font-semibold leading-6 text-gray-900"><?= htmlspecialchars($adoption['subject_matter'] ?? '—') ?></h3>
                                        <?php if (!empty($adoption['document_type_name'])): ?>
                                            <p class="mt-1 text-xs text-gray-500"><?= htmlspecialchars($adoption['document_type_name']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <span class="inline-flex w-fit shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold <?= $decisionClass ?>">
                                        <?= htmlspecialchars($decisionLabel) ?>
                                    </span>
                                </div>

                                <dl class="mt-4 grid grid-cols-1 gap-3 rounded-xl bg-gray-50 p-4 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Proposed Number</dt>
                                        <dd class="mt-1 font-medium text-gray-800"><?= htmlspecialchars($adoption['proposed_number'] ?: 'Not assigned') ?></dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Current Document Status</dt>
                                        <dd class="mt-1 font-medium text-gray-800"><?= htmlspecialchars($adoption['document_status'] ?? '—') ?></dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Received</dt>
                                        <dd class="mt-1 text-gray-700"><?= !empty($adoption['date_received']) ? htmlspecialchars(date('M j, Y', strtotime($adoption['date_received']))) : '—' ?></dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">First Reading Referral</dt>
                                        <dd class="mt-1 text-gray-700"><?= !empty($adoption['first_reading_date']) ? htmlspecialchars(date('M j, Y', strtotime($adoption['first_reading_date']))) : '—' ?></dd>
                                        <?php if (!empty($adoption['first_reading_remarks'])): ?>
                                            <p class="mt-1 whitespace-pre-wrap text-xs text-gray-500"><?= htmlspecialchars($adoption['first_reading_remarks']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Legislative Session</dt>
                                        <dd class="mt-1 text-gray-700"><?= htmlspecialchars($sessionLabels[$adoption['session_type'] ?? ''] ?? 'Not recorded') ?></dd>
                                        <?php if (!empty($adoption['target_first_reading_date'])): ?>
                                            <p class="mt-1 text-xs text-gray-500">Target First Reading: <?= htmlspecialchars(date('M j, Y', strtotime($adoption['target_first_reading_date']))) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Committee Hearing</dt>
                                        <dd class="mt-1">
                                            <?php if ($hearingOutcome !== ''): ?>
                                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold <?= $hearingClass ?>"><?= htmlspecialchars(ucfirst(strtolower($hearingOutcome))) ?></span>
                                                <?php if (!empty($adoption['committee_hearing_at'])): ?>
                                                    <span class="ml-1 text-xs text-gray-500"><?= htmlspecialchars(date('M j, Y', strtotime($adoption['committee_hearing_at']))) ?></span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-gray-500">No hearing outcome recorded</span>
                                            <?php endif; ?>
                                        </dd>
                                        <?php if (!empty($adoption['committee_hearing_remarks'])): ?>
                                            <p class="mt-1 whitespace-pre-wrap text-xs text-gray-500"><?= htmlspecialchars($adoption['committee_hearing_remarks']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </dl>

                                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-3 text-xs text-gray-500">
                                    <span>Plenary action by <strong class="font-medium text-gray-700"><?= htmlspecialchars($adoption['adopted_by_name'] ?? '—') ?></strong></span>
                                    <span><?= !empty($adoption['adopted_at']) ? htmlspecialchars(date('M j, Y g:i A', strtotime($adoption['adopted_at']))) : 'Date unavailable' ?></span>
                                </div>
                                <?php if (!empty($adoption['remarks'])): ?>
                                    <div class="mt-3 rounded-lg border border-blue-100 bg-blue-50/50 px-3 py-2">
                                        <p class="text-xs font-semibold text-blue-900">Plenary Remarks</p>
                                        <p class="mt-1 whitespace-pre-wrap text-sm text-gray-700"><?= htmlspecialchars($adoption['remarks']) ?></p>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

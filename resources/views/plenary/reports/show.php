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
$hearingOutcomeColors = [
    'APPROVED' => '#059669',
    'DEFERRED' => '#d97706',
    'REMANDED' => '#dc2626',
    'WITHDRAWN' => '#6b7280',
    'NOTED' => '#0891b2',
    'COMPLETED_TERMINATED' => '#475569',
    'RESET' => '#2563eb',
];
$hearingOutcomeLabels = [
    'COMPLETED_TERMINATED' => 'Completed/Terminated',
    'RESET' => 'Reset',
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
<div class="detail-page space-y-6">
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative z-10 flex items-center gap-3 px-5 py-6 sm:px-8 sm:py-7">
            <a href="<?= BASE_URL ?>/plenary/reports"
               class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 text-white transition hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
               aria-label="Back to Plenary Reports">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-blue-100">ADOPTED COMMITTEE REPORT</p>
                <h1 class="mt-1 break-words text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    <?= htmlspecialchars($reportType) ?>
                    <?= !empty($report['report_number']) ? '#' . htmlspecialchars($report['report_number']) : ' #' . (int) $report['id'] ?>
                </h1>
                <p class="mt-2 text-sm leading-6 text-blue-100">
                    <?= count($adoptions) ?> legislative document<?= count($adoptions) === 1 ? '' : 's' ?>
                    · <?= $approvedCount ?> proceeded to Second Reading
                    · <?= $disapprovedCount ?> adopted as report copy only
                </p>
            </div>
        </div>
        <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
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
                </dl>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900">Reporting Committee<?= count($reportCommittees) === 1 ? '' : 's' ?></h2>
                <?php if ($reportCommittees !== []): ?>
                    <ul class="mt-3 divide-y divide-gray-100">
                        <?php foreach ($reportCommittees as $committeeName): ?>
                            <li class="py-3 first:pt-0 last:pb-0">
                                <p class="break-words text-sm font-medium text-gray-900"><?= htmlspecialchars($committeeName, ENT_QUOTES, 'UTF-8') ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="mt-3 border-t border-gray-100 pt-3">
                        <p class="text-sm text-gray-500">No reporting committees are listed for this report.</p>
                    </div>
                <?php endif; ?>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Official Committee Report Copy</h2>
                        <p class="mt-1 text-xs text-gray-500">Transmitted report attachments</p>
                    </div>
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">
                        <?= count($reportAttachments) ?> file<?= count($reportAttachments) === 1 ? '' : 's' ?>
                    </span>
                </div>
                <?php if ($reportAttachments === []): ?>
                    <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-8 text-center">
                        <p class="text-sm font-medium text-gray-700">No report copy attached</p>
                        <p class="mt-1 text-xs text-gray-500">Transmitted report files will appear here.</p>
                    </div>
                <?php else: ?>
                    <ul class="space-y-2">
                        <?php foreach ($reportAttachments as $attachment): ?>
                            <?php
                            $attachmentName = (string) ($attachment['file_name'] ?? 'Committee report copy');
                            $attachmentUrl = BASE_URL . '/public/' . ltrim((string) ($attachment['stored_path'] ?? ''), '/\\');
                            ?>
                            <li class="flex flex-col gap-3 rounded-xl border border-gray-100 bg-gray-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex min-w-0 items-start gap-3 sm:items-center">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-primary">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="break-words text-sm font-medium text-gray-800 sm:truncate">
                                            <?= htmlspecialchars($attachmentName, ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                        <p class="mt-0.5 break-words text-xs leading-relaxed text-gray-500">
                                            <?= number_format((int) ($attachment['file_size'] ?? 0) / 1024, 1) ?> KB
                                            <?php if (!empty($attachment['created_at'])): ?>
                                                &middot; <?= htmlspecialchars(date('M j, Y g:i A', strtotime($attachment['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                <?php if (!empty($attachment['stored_path'])): ?>
                                    <div class="grid grid-cols-2 gap-2 sm:ml-4 sm:flex sm:shrink-0 sm:items-center">
                                        <a href="<?= htmlspecialchars($attachmentUrl, ENT_QUOTES, 'UTF-8') ?>"
                                           target="_blank" rel="noopener"
                                           data-attachment-preview
                                           data-preview-url="<?= htmlspecialchars($attachmentUrl, ENT_QUOTES, 'UTF-8') ?>"
                                           data-preview-name="<?= htmlspecialchars($attachmentName, ENT_QUOTES, 'UTF-8') ?>"
                                           data-download-url="<?= htmlspecialchars($attachmentUrl, ENT_QUOTES, 'UTF-8') ?>"
                                           class="inline-flex justify-center rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-white transition hover:bg-primary-hover sm:py-1.5">Preview</a>
                                        <a href="<?= htmlspecialchars($attachmentUrl, ENT_QUOTES, 'UTF-8') ?>" download
                                           class="inline-flex justify-center rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-600 transition hover:bg-gray-50 sm:py-1.5">Download</a>
                                    </div>
                                <?php endif; ?>
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
                    <div class="mt-3 whitespace-pre-wrap text-sm leading-7 text-gray-700"><?= escape_trimmed_text($report['summary_of_findings']) ?></div>
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
                                $hearingColor = $hearingOutcomeColors[$hearingOutcome] ?? '#6B7280';
                                $hearingLabel = $hearingOutcomeLabels[$hearingOutcome] ?? ucfirst(strtolower($hearingOutcome));
                                $documentStatusColor = $adoption['document_status_badge_color'] ?? '#6B7280';
                            ?>
                            <article class="p-5">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="font-mono text-sm font-bold text-primary"><?= htmlspecialchars($adoption['tracking_number']) ?></p>
                                        <h3 class="mt-1 text-base font-semibold leading-6 text-gray-900"><?= htmlspecialchars($adoption['subject_matter'] ?? '—') ?></h3>
                                    </div>
                                    <span class="inline-flex w-fit shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold <?= $decisionClass ?>">
                                        <?= htmlspecialchars($decisionLabel) ?>
                                    </span>
                                </div>

                                <dl class="mt-4 grid grid-cols-1 gap-3 rounded-xl bg-gray-50 p-4 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Document Type</dt>
                                        <dd class="mt-1">
                                            <?php if (!empty($adoption['document_type_name'])): ?>
                                                <span class="inline-flex w-fit rounded-full px-2 py-0.5 text-xs font-medium"
                                                      style="background-color: <?= htmlspecialchars($adoption['document_type_badge_color'] ?? '#2563EB') ?>14; color: <?= htmlspecialchars($adoption['document_type_badge_color'] ?? '#2563EB') ?>;">
                                                    <?= htmlspecialchars($adoption['document_type_name']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-gray-500">—</span>
                                            <?php endif; ?>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Proposed Number</dt>
                                        <dd class="mt-1 font-medium text-gray-800"><?= htmlspecialchars($adoption['proposed_number'] ?: 'Not assigned') ?></dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Current Document Status</dt>
                                        <dd class="mt-1">
                                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                                                  style="background-color: <?= htmlspecialchars($documentStatusColor) ?>14; color: <?= htmlspecialchars($documentStatusColor) ?>;">
                                                <span class="h-1.5 w-1.5 rounded-full" style="background-color: <?= htmlspecialchars($documentStatusColor) ?>;"></span>
                                                <?= htmlspecialchars($adoption['document_status'] ?? '—') ?>
                                            </span>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Received</dt>
                                        <dd class="mt-1 text-gray-700"><?= !empty($adoption['date_received']) ? htmlspecialchars(date('M j, Y', strtotime($adoption['date_received']))) : '—' ?></dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">First Reading Referral</dt>
                                        <dd class="mt-1 text-gray-700"><?= !empty($adoption['first_reading_date']) ? htmlspecialchars(date('M j, Y', strtotime($adoption['first_reading_date']))) : '—' ?></dd>
                                        <?php if (!empty($adoption['first_reading_remarks'])): ?>
                                            <p class="mt-1 whitespace-pre-wrap text-xs text-gray-500"><?= escape_trimmed_text($adoption['first_reading_remarks']) ?></p>
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
                                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                                                      style="background-color: <?= htmlspecialchars($hearingColor) ?>14; color: <?= htmlspecialchars($hearingColor) ?>;">
                                                    <span class="h-1.5 w-1.5 rounded-full" style="background-color: <?= htmlspecialchars($hearingColor) ?>;"></span>
                                                    <?= htmlspecialchars($hearingLabel) ?>
                                                </span>
                                                <?php if (!empty($adoption['committee_hearing_at'])): ?>
                                                    <span class="ml-1 text-xs text-gray-500"><?= htmlspecialchars(date('M j, Y', strtotime($adoption['committee_hearing_at']))) ?></span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-gray-500">No hearing outcome recorded</span>
                                            <?php endif; ?>
                                        </dd>
                                        <?php if (!empty($adoption['committee_hearing_remarks'])): ?>
                                            <p class="mt-1 whitespace-pre-wrap text-xs text-gray-500"><?= escape_trimmed_text($adoption['committee_hearing_remarks']) ?></p>
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
                                        <p class="mt-1 whitespace-pre-wrap text-sm text-gray-700"><?= escape_trimmed_text($adoption['remarks']) ?></p>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-4 flex justify-end">
                                    <a href="<?= BASE_URL ?>/plenary/reports/document-show?id=<?= (int) $adoption['document_id'] ?>&amp;report_id=<?= (int) $report['id'] ?>"
                                       class="inline-flex items-center gap-2 rounded-lg border border-primary/20 bg-white px-3 py-2 text-xs font-semibold text-primary transition hover:bg-blue-50">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        View Full Document Details
                                    </a>
                                </div>
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

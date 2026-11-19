<?php
/**
 * Committee — Opinion Office Issue History
 *
 * Variables supplied by CommitteeReferredController::opinionDetailsShow():
 *   $document array
 *   $cycle array
 *   $progress array
 */

$document = $document ?? [];
$cycle = $cycle ?? [];
$progress = $progress ?? [];
$plenaryDetails = $plenaryDetails ?? [];
$plenaryAuthors = $plenaryAuthors ?? [];
$plenaryFirstReading = $plenaryFirstReading ?? null;
$documentAttachments = $documentAttachments ?? [];
$offices = $progress['offices'] ?? [];
$totalOffices = (int) ($progress['total'] ?? 0);
$clearedOffices = (int) ($progress['cleared'] ?? 0);

ob_start();
?>

<div class="space-y-6">
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-7 sm:px-8">
            <div class="relative z-10">
                <p class="text-sm font-medium text-blue-100">COMMITTEE / REFERRED / FOR OPINION</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    Opinion Office Details
                </h1>
                <p class="mt-1 text-sm text-blue-100">
                    <?= htmlspecialchars((string) ($document['tracking_number'] ?? '')) ?>
                    &middot; Cycle #<?= (int) ($cycle['cycle_number'] ?? 0) ?>
                </p>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-56 w-56 rounded-full bg-white/10"></div>
        </div>
    </section>

    <nav class="flex items-center gap-2 text-sm text-gray-500" aria-label="Breadcrumb">
        <a href="<?= BASE_URL ?>/committee/referred?tab=for_opinion" class="hover:text-primary transition">
            For Opinion
        </a>
        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="font-medium text-gray-700">Opinion Office Details</span>
    </nav>

    <section class="space-y-4">
        <h2 class="text-lg font-semibold text-gray-900">Full Document Details</h2>
        <?php
        $pageSubtitle = $document['tracking_number'] ?? 'Document Details';
        $detailBackUrl = BASE_URL . '/committee/referred?tab=for_opinion';
        $detailBackLabel = 'Back to For Opinion';
        $showMfrRelationship = false;
        $renderAsPartial = true;
        require __DIR__ . '/document-show.php';
        ?>
    </section>
    <?php if ($offices === []): ?>
        <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center">
            <p class="text-sm font-semibold text-gray-700">No opinion office records found</p>
            <p class="mt-1 text-xs text-gray-500">The selected Committee cycle has no recorded endorsements.</p>
        </div>
    <?php else: ?>
        <div class="space-y-5">
            <?php foreach ($offices as $officeId => $office): ?>
                <?php
                $officeName = (string) ($office['office_name'] ?? 'Opinion Office');
                $issueHistory = $office['issue_history'] ?? [];
                $officeStatus = trim((string) ($office['display_status_name'] ?? '')) ?: 'Pending';
                $officeOutcome = strtoupper((string) ($office['display_opinion_type'] ?? ''));
                $statusClass = match(true) {
                    !empty($office['cleared']) => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                    $officeOutcome === 'UNFAVORABLE' => 'bg-red-50 text-red-700 ring-red-200',
                    str_starts_with($officeStatus, 'For ') => 'bg-amber-50 text-amber-700 ring-amber-200',
                    default => 'bg-gray-100 text-gray-600 ring-gray-200',
                };
                ?>
                <article id="office-<?= (int) $officeId ?>" class="scroll-mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <header class="flex flex-col gap-3 border-b border-gray-100 bg-gray-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Opinion Office</p>
                            <h2 class="mt-0.5 text-lg font-bold text-gray-900"><?= htmlspecialchars($officeName) ?></h2>
                        </div>
                        <span class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset <?= $statusClass ?>">
                            <?= htmlspecialchars($officeStatus) ?>
                        </span>
                    </header>

                    <div class="space-y-4 p-5 sm:p-6">
                        <?php if ($issueHistory === []): ?>
                            <p class="text-sm text-gray-500">No endorsement issues recorded for this office.</p>
                        <?php else: ?>
                            <?php foreach ($issueHistory as $issue): ?>
                                <?php
                                $issueType = strtoupper((string) ($issue['opinion_type'] ?? ''));
                                $issueLabel = $issueType !== '' ? ucfirst(strtolower($issueType)) : 'Pending';
                                $issueBadgeClass = match($issueType) {
                                    'FAVORABLE' => 'bg-emerald-50 text-emerald-700',
                                    'UNFAVORABLE' => 'bg-red-50 text-red-700',
                                    default => 'bg-amber-50 text-amber-700',
                                };
                                $files = $issue['files'] ?? [];
                                ?>
                                <section class="rounded-xl border border-gray-200 p-4 sm:p-5">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <h3 class="font-semibold text-gray-900">
                                                Issue #<?= (int) ($issue['endorsement_number'] ?? 1) ?>
                                            </h3>
                                            <?php if (!empty($issue['submitted_at'])): ?>
                                                <p class="mt-0.5 text-xs text-gray-500">
                                                    Submitted <?= htmlspecialchars((string) $issue['submitted_at']) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <span class="inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-semibold <?= $issueBadgeClass ?>">
                                            <?= htmlspecialchars($issueLabel) ?>
                                        </span>
                                    </div>

                                    <?php if (!empty($issue['status_name'])): ?>
                                        <div class="mt-4">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Issue status</p>
                                            <p class="mt-1 text-sm text-gray-700"><?= htmlspecialchars((string) $issue['status_name']) ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                                        <?php foreach ([
                                            'Opinion files' => $files['opinion'] ?? [],
                                            'Compliance files' => $files['compliance'] ?? [],
                                        ] as $fileLabel => $issueFiles): ?>
                                            <div class="rounded-lg bg-gray-50 p-3">
                                                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                                    <?= htmlspecialchars($fileLabel) ?> <span class="font-normal">(<?= count($issueFiles) ?>)</span>
                                                </h4>
                                                <?php if ($issueFiles === []): ?>
                                                    <p class="mt-2 text-sm text-gray-400">No files recorded.</p>
                                                <?php else: ?>
                                                    <ul class="mt-2 space-y-2">
                                                        <?php foreach ($issueFiles as $file): ?>
                                                            <li>
                                                                <?php
                                                                $fileName = (string) ($file['file_name'] ?? 'Attachment');
                                                                $fileUrl = BASE_URL . '/public/' . ltrim((string) ($file['stored_path'] ?? ''), '/');
                                                                ?>
                                                                <a href="<?= htmlspecialchars($fileUrl) ?>"
                                                                   data-attachment-preview
                                                                   data-preview-url="<?= htmlspecialchars($fileUrl) ?>"
                                                                   data-preview-name="<?= htmlspecialchars($fileName) ?>"
                                                                   class="flex items-center justify-between gap-3 rounded-md bg-white px-2.5 py-2 text-sm text-gray-700 ring-1 ring-gray-200 transition hover:ring-primary hover:text-primary">
                                                                    <span class="min-w-0 break-all font-medium"><?= htmlspecialchars($fileName) ?></span>
                                                                    <span class="shrink-0 text-xs font-semibold">Preview</span>
                                                                </a>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <?php if (!empty($issue['submission_remarks']) || !empty($issue['endorsement_remarks'])): ?>
                                        <div class="mt-4 grid gap-3 md:grid-cols-2">
                                            <?php if (!empty($issue['submission_remarks'])): ?>
                                                <div class="rounded-lg border border-blue-100 bg-blue-50 p-3">
                                                    <h4 class="text-xs font-semibold uppercase tracking-wide text-blue-700">Opinion remarks</h4>
                                                    <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-blue-900">
                                                        <?= htmlspecialchars((string) $issue['submission_remarks']) ?>
                                                    </p>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($issue['endorsement_remarks'])): ?>
                                                <div class="rounded-lg border border-violet-100 bg-violet-50 p-3">
                                                    <h4 class="text-xs font-semibold uppercase tracking-wide text-violet-700">Issue remarks</h4>
                                                    <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-violet-900">
                                                        <?= htmlspecialchars((string) $issue['endorsement_remarks']) ?>
                                                    </p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </section>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="flex justify-start">
        <a href="<?= BASE_URL ?>/committee/referred?tab=for_opinion"
           class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 18l-6-6 6-6"/>
            </svg>
            Back to For Opinion
        </a>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

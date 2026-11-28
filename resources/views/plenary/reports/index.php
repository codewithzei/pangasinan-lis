<?php

$reports = $reports ?? [];
$search = $search ?? '';
$reportType = $reportType ?? '';
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? 0;
$perPage = max(1, (int) ($perPage ?? 20));
$paginationQuery = [];
if ($search !== '') {
    $paginationQuery['search'] = $search;
}
if ($reportType !== '') {
    $paginationQuery['report_type'] = $reportType;
}
$reportPageUrl = static fn(int $pageNumber): string => BASE_URL . '/plenary/reports?' . http_build_query($paginationQuery + ['page' => $pageNumber]);
$firstPage = max(1, min((int) $page - 2, (int) $totalPages - 4));
$lastPage = min((int) $totalPages, $firstPage + 4);
$firstResult = $total > 0 ? (((int) $page - 1) * $perPage) + 1 : 0;
$lastResult = min((int) $total, (int) $page * $perPage);

ob_start();
?>
<div class="space-y-6">
    <?php if (!empty($success)): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status" aria-live="polite"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative z-10 flex flex-col gap-3 px-6 py-7 sm:px-8 sm:py-8">
            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-blue-100">Plenary · Records</p>
            <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">Plenary Reports</h1>
            <p class="max-w-2xl text-sm leading-6 text-blue-100">
                Legislative Committee Reports adopted by Plenary, including their findings, covered documents,
                committee actions, and adoption decisions.
            </p>
        </div>
        <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-gray-100 px-4 py-5 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Adopted Committee Reports</h2>
                <p class="mt-1 text-xs text-gray-500"><?= number_format((int) $total) ?> report<?= (int) $total === 1 ? '' : 's' ?></p>
            </div>
            <form method="GET" action="<?= BASE_URL ?>/plenary/reports" class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:flex lg:items-center">
                <input type="hidden" name="page" value="1">
                <label class="sr-only" for="reportsSearch">Search reports</label>
                <input id="reportsSearch" type="search" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Search report or document"
                       class="w-full min-w-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 lg:w-64">
                <label class="sr-only" for="reportsType">Filter by report type</label>
                <select id="reportsType" name="report_type"
                        class="w-full min-w-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 lg:w-56">
                    <option value="">All report types</option>
                    <option value="COMMITTEE_REPORT" <?= $reportType === 'COMMITTEE_REPORT' ? 'selected' : '' ?>>Committee Report</option>
                    <option value="JOINT_COMMITTEE_REPORT" <?= $reportType === 'JOINT_COMMITTEE_REPORT' ? 'selected' : '' ?>>Joint Committee Report</option>
                </select>
                <div class="grid grid-cols-2 gap-2 sm:col-span-2 lg:flex lg:shrink-0">
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                        Filter
                    </button>
                    <?php if ($search !== '' || $reportType !== ''): ?>
                        <a href="<?= BASE_URL ?>/plenary/reports" class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="hidden overflow-x-auto md:block">
            <table class="w-full min-w-[850px] text-left text-sm">
                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-5 py-3">Committee Report</th>
                        <th class="px-5 py-3 text-center">Documents</th>
                        <th class="px-5 py-3">Decision Summary</th>
                        <th class="px-5 py-3">Last Adopted</th>
                        <th class="px-5 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if ($reports === []): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-16 text-center">
                                <p class="font-medium text-gray-700">No adopted Committee Reports found.</p>
                                <p class="mt-1 text-xs text-gray-500"><?= $search !== '' || $reportType !== '' ? 'Try changing your search or report type filter.' : 'Adopted reports will be saved here for viewing.' ?></p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reports as $report): ?>
                            <?php
                            $typeLabel = ($report['report_type'] ?? '') === 'JOINT_COMMITTEE_REPORT'
                                ? 'Joint Committee Report'
                                : 'Committee Report';
                            $reportNumber = !empty($report['report_number'])
                                ? '#' . $report['report_number']
                                : 'Report #' . (int) $report['report_id'];
                            $summary = array_filter([
                                (int) ($report['approved_count'] ?? 0) > 0 ? 'Approved: ' . (int) $report['approved_count'] : null,
                                (int) ($report['disapproved_count'] ?? 0) > 0 ? 'Disapproved: ' . (int) $report['disapproved_count'] : null,
                                (int) ($report['adopted_only_count'] ?? 0) > 0 ? 'Adopted: ' . (int) $report['adopted_only_count'] : null,
                            ]);
                            ?>
                            <tr class="align-middle transition hover:bg-gray-50/70">
                                <td class="px-5 py-4">
                                    <p class="font-semibold text-gray-900"><?= htmlspecialchars($reportNumber, ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="mt-1 text-xs font-medium text-gray-500"><?= htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                </td>
                                <td class="px-5 py-4 text-center font-medium tabular-nums text-gray-800"><?= number_format((int) $report['adopted_document_count']) ?></td>
                                <td class="px-5 py-4 text-xs leading-5 text-gray-600"><?= $summary !== [] ? htmlspecialchars(implode(' · ', $summary), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                                <td class="whitespace-nowrap px-5 py-4 text-gray-600">
                                    <?= !empty($report['latest_adopted_at']) ? htmlspecialchars(date('M j, Y', strtotime($report['latest_adopted_at'])), ENT_QUOTES, 'UTF-8') : '—' ?>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    <a href="<?= BASE_URL ?>/plenary/reports/show?id=<?= (int) $report['report_id'] ?>"
                                       class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:border-primary hover:bg-blue-50 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                       title="View report" aria-label="View report <?= htmlspecialchars($reportNumber, ENT_QUOTES, 'UTF-8') ?>">
                                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        <span>View</span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="divide-y divide-gray-100 md:hidden">
            <?php if ($reports === []): ?>
                <div class="px-5 py-12 text-center">
                    <p class="text-sm font-medium text-gray-700">No adopted Committee Reports found.</p>
                    <p class="mt-1 text-xs text-gray-500"><?= $search !== '' || $reportType !== '' ? 'Try changing your search or report type filter.' : 'Adopted reports will be saved here for viewing.' ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($reports as $report): ?>
                    <?php
                    $typeLabel = ($report['report_type'] ?? '') === 'JOINT_COMMITTEE_REPORT'
                        ? 'Joint Committee Report'
                        : 'Committee Report';
                    $reportNumber = !empty($report['report_number'])
                        ? '#' . $report['report_number']
                        : 'Report #' . (int) $report['report_id'];
                    $summary = array_filter([
                        (int) ($report['approved_count'] ?? 0) > 0 ? 'Approved: ' . (int) $report['approved_count'] : null,
                        (int) ($report['disapproved_count'] ?? 0) > 0 ? 'Disapproved: ' . (int) $report['disapproved_count'] : null,
                        (int) ($report['adopted_only_count'] ?? 0) > 0 ? 'Adopted: ' . (int) $report['adopted_only_count'] : null,
                    ]);
                    ?>
                    <article class="p-4 sm:p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="break-words text-sm font-semibold text-gray-900"><?= htmlspecialchars($reportNumber, ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="mt-1 text-xs font-medium text-gray-500"><?= htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                            <span class="shrink-0 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                <?= number_format((int) $report['adopted_document_count']) ?> document<?= (int) $report['adopted_document_count'] === 1 ? '' : 's' ?>
                            </span>
                        </div>
                        <dl class="mt-4 grid grid-cols-1 gap-3 border-t border-gray-100 pt-3 sm:grid-cols-2">
                            <div>
                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Decision Summary</dt>
                                <dd class="mt-1 break-words text-xs leading-5 text-gray-700"><?= $summary !== [] ? htmlspecialchars(implode(' · ', $summary), ENT_QUOTES, 'UTF-8') : '—' ?></dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Last Adopted</dt>
                                <dd class="mt-1 text-xs text-gray-700"><?= !empty($report['latest_adopted_at']) ? htmlspecialchars(date('M j, Y', strtotime($report['latest_adopted_at'])), ENT_QUOTES, 'UTF-8') : '—' ?></dd>
                            </div>
                        </dl>
                        <div class="mt-4 flex justify-end border-t border-gray-100 pt-3">
                            <a href="<?= BASE_URL ?>/plenary/reports/show?id=<?= (int) $report['report_id'] ?>"
                               class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:border-primary hover:bg-blue-50 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                               title="View report" aria-label="View report <?= htmlspecialchars($reportNumber, ENT_QUOTES, 'UTF-8') ?>">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <span>View</span>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <nav class="flex flex-col gap-3 border-t border-gray-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6" aria-label="Pagination">
                <p class="text-xs text-gray-500">
                    Showing <span class="font-medium text-gray-700"><?= number_format($firstResult) ?>–<?= number_format($lastResult) ?></span>
                    of <span class="font-medium text-gray-700"><?= number_format((int) $total) ?></span> reports
                </p>
                <div class="flex flex-wrap items-center gap-1.5">
                    <?php if ($page > 1): ?>
                        <a href="<?= htmlspecialchars($reportPageUrl((int) $page - 1), ENT_QUOTES, 'UTF-8') ?>"
                           class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50">Previous</a>
                    <?php endif; ?>
                    <?php for ($pageNumber = $firstPage; $pageNumber <= $lastPage; $pageNumber++): ?>
                        <a href="<?= htmlspecialchars($reportPageUrl($pageNumber), ENT_QUOTES, 'UTF-8') ?>"
                           aria-current="<?= $pageNumber === (int) $page ? 'page' : 'false' ?>"
                           class="rounded-lg border px-3 py-2 text-xs font-medium transition <?= $pageNumber === (int) $page ? 'border-primary bg-primary text-white' : 'border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
                            <?= $pageNumber ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?= htmlspecialchars($reportPageUrl((int) $page + 1), ENT_QUOTES, 'UTF-8') ?>"
                           class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50">Next</a>
                    <?php endif; ?>
                </div>
        </nav>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

<?php

$reports = $reports ?? [];
$search = $search ?? '';
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? 0;

ob_start();
?>
<div class="space-y-6">
    <?php if (!empty($success)): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status" aria-live="polite"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <p class="text-sm font-medium text-blue-100">PLENARY / RECORDS</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">Plenary Reports</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-blue-100">
                Legislative Committee Reports adopted by Plenary, including their findings, covered documents,
                committee actions, and adoption decisions.
            </p>
        </div>
    </section>

    <section class="rounded-2xl border border-gray-200 bg-white">
        <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900">Adopted Committee Reports</h2>
                <p class="mt-1 text-xs text-gray-500"><?= number_format($total) ?> report<?= $total === 1 ? '' : 's' ?></p>
            </div>
            <form method="GET" action="<?= BASE_URL ?>/plenary/reports" class="flex gap-2">
                <input type="search" name="search" value="<?= htmlspecialchars($search) ?>"
                       placeholder="Search report or document"
                       class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 sm:w-64">
                <button type="submit" class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90">Search</button>
                <?php if ($search !== ''): ?>
                    <a href="<?= BASE_URL ?>/plenary/reports" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Committee Report</th>
                        <th class="px-6 py-3 font-medium">Adopted Documents</th>
                        <th class="px-6 py-3 font-medium">Decision Summary</th>
                        <th class="px-6 py-3 font-medium">Last Adopted</th>
                        <th class="px-6 py-3 text-right font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if ($reports === []): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-16 text-center">
                                <p class="font-medium text-gray-700">No adopted Committee Reports found.</p>
                                <p class="mt-1 text-xs text-gray-500">Adopted reports will be saved here for viewing.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reports as $report): ?>
                            <?php
                                $typeLabel = ($report['report_type'] ?? '') === 'JOINT_COMMITTEE_REPORT'
                                    ? 'Joint Committee Report'
                                    : 'Committee Report';
                                $summary = array_filter([
                                    (int) $report['approved_count'] > 0 ? 'Approved: ' . (int) $report['approved_count'] : null,
                                    (int) $report['disapproved_count'] > 0 ? 'Disapproved: ' . (int) $report['disapproved_count'] : null,
                                    (int) $report['adopted_only_count'] > 0 ? 'Adopted: ' . (int) $report['adopted_only_count'] : null,
                                ]);
                            ?>
                            <tr class="hover:bg-gray-50/60">
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-gray-900"><?= htmlspecialchars($typeLabel) ?></p>
                                    <p class="mt-1 text-xs text-gray-500">
                                        <?= !empty($report['report_number']) ? '#' . htmlspecialchars($report['report_number']) : 'Report #' . (int) $report['report_id'] ?>
                                    </p>
                                    <?php if (!empty($report['committee_names'])): ?>
                                        <p class="mt-1 text-xs font-medium text-indigo-700">
                                            <?= htmlspecialchars($report['committee_names']) ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if (!empty($report['summary_of_findings'])): ?>
                                        <p class="mt-2 max-w-lg line-clamp-2 text-xs text-gray-600"><?= htmlspecialchars($report['summary_of_findings']) ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-gray-700"><?= number_format((int) $report['adopted_document_count']) ?></td>
                                <td class="px-6 py-4 text-xs text-gray-600"><?= htmlspecialchars(implode(' · ', $summary)) ?></td>
                                <td class="px-6 py-4 text-gray-600">
                                    <?= htmlspecialchars(date('M j, Y', strtotime($report['latest_adopted_at']))) ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="<?= BASE_URL ?>/plenary/reports/show?id=<?= (int) $report['report_id'] ?>"
                                       class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                        View Report
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="flex items-center justify-between border-t border-gray-100 px-6 py-4">
                <span class="text-xs text-gray-500">Page <?= (int) $page ?> of <?= (int) $totalPages ?></span>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="<?= BASE_URL ?>/plenary/reports?page=<?= (int) ($page - 1) ?>&amp;search=<?= urlencode($search) ?>"
                           class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50">Previous</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?= BASE_URL ?>/plenary/reports?page=<?= (int) ($page + 1) ?>&amp;search=<?= urlencode($search) ?>"
                           class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

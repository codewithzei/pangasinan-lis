<?php
$tabLabels = [
    'ordinances'            => 'Provincial Ordinances',
    'resolutions'           => 'Provincial Resolutions',
    'municipal_ordinances'  => 'Municipal/City Ordinances',
    'municipal_resolutions' => 'Municipal/City Resolutions',
    'cases'                 => 'Cases',
    'others'                => 'Others',
];
?>

<div class="space-y-6">
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <p class="text-sm font-medium text-blue-100">DOCUMENT ARCHIVE</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                <?= htmlspecialchars($pageTitle) ?>
            </h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-blue-100">
                <?= htmlspecialchars($pageSubtitle) ?>
                Communications are kept in the separate Communications section.
            </p>
            <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-gray-500">All Noted Documents</p>
            <p class="mt-2 text-3xl font-bold text-emerald-700"><?= number_format($totalNoted) ?></p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-gray-500"><?= htmlspecialchars($tabLabels[$tab]) ?></p>
            <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($totalRows) ?></p>
            <p class="mt-1 text-xs text-gray-500">Matching this tab and the current filters</p>
        </div>
    </section>

    <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-1 overflow-x-auto" aria-label="Noted document types">
            <?php foreach ($tabLabels as $tabKey => $label): ?>
                <a href="<?= htmlspecialchars($notedBaseUrl) ?>?<?= http_build_query(['tab' => $tabKey]) ?>"
                   class="inline-flex shrink-0 items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium transition
                       focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500
                       <?= $tab === $tabKey
                           ? 'border-blue-600 text-blue-700'
                           : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' ?>"
                   <?= $tab === $tabKey ? 'aria-current="page"' : '' ?>>
                    <?= htmlspecialchars($label) ?>
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold
                                 <?= $tab === $tabKey ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' ?>">
                        <?= number_format($tabCounts[$tabKey] ?? 0) ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900"><?= htmlspecialchars($tabLabels[$tab]) ?></h2>
                <p class="mt-1 text-xs text-gray-500">
                    <?= number_format($totalRows) ?> record<?= $totalRows !== 1 ? 's' : '' ?> found
                </p>
            </div>
            <form method="GET" action="<?= htmlspecialchars($notedBaseUrl) ?>"
                  class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                <input type="search" name="search" value="<?= htmlspecialchars($search) ?>"
                       placeholder="Search tracking number or subject"
                       class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                <select name="status"
                        class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">All statuses</option>
                    <?php foreach ($documentStatuses as $statusOption): ?>
                        <option value="<?= (int) $statusOption['id'] ?>"
                            <?= $filterStatus === (string) $statusOption['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($statusOption['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit"
                        class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">
                    Filter
                </button>
                <?php if ($search !== '' || $filterStatus !== ''): ?>
                    <a href="<?= htmlspecialchars($notedBaseUrl) ?>?tab=<?= urlencode($tab) ?>"
                       class="rounded-xl border border-gray-200 px-4 py-2 text-center text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Tracking Number</th>
                        <th class="px-6 py-3 font-medium">Subject Matter</th>
                        <th class="px-6 py-3 font-medium">Document Type</th>
                        <th class="px-6 py-3 font-medium">Attachments</th>
                        <th class="px-6 py-3 font-medium">Date Noted</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 text-right font-medium">Copy</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (!$documents): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-16 text-center">
                                <p class="text-sm font-medium text-gray-700">No noted documents found in this tab.</p>
                                <p class="mt-1 text-xs text-gray-500">Documents marked as Noted will be stored here.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($documents as $doc): ?>
                            <?php $typeColor = $doc['document_type_badge_color'] ?: '#2563EB'; ?>
                            <?php $statusColor = $doc['status_badge_color'] ?: '#6B7280'; ?>
                            <tr class="hover:bg-gray-50/60">
                                <td class="whitespace-nowrap px-6 py-4 font-medium text-gray-900">
                                    <?= htmlspecialchars($doc['tracking_number']) ?>
                                    <div class="mt-1 text-xs font-normal text-gray-400">
                                        Received <?= htmlspecialchars(date('M j, Y', strtotime($doc['date_received']))) ?>
                                    </div>
                                </td>
                                <td class="max-w-sm px-6 py-4 text-gray-800">
                                    <span class="line-clamp-2"><?= htmlspecialchars($doc['subject_matter']) ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                          style="background-color: <?= htmlspecialchars($typeColor) ?>1a; color: <?= htmlspecialchars($typeColor) ?>">
                                        <?= htmlspecialchars($doc['document_type_name']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-600">
                                    <?= number_format((int) $doc['attachment_count']) ?>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-gray-600">
                                    <?= htmlspecialchars(date('M j, Y g:i A', strtotime($doc['noted_at']))) ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                          style="background-color: <?= htmlspecialchars($statusColor) ?>1a; color: <?= htmlspecialchars($statusColor) ?>">
                                        <?= htmlspecialchars($doc['status'] ?? '—') ?>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    <a href="<?= htmlspecialchars($documentViewUrl) ?><?= (int) $doc['id'] ?>"
                                       class="inline-flex items-center rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-primary hover:bg-blue-50">
                                        View Copy
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <?php
            $queryArgs = ['tab' => $tab];
            if ($search !== '') $queryArgs['search'] = $search;
            if ($filterStatus !== '') $queryArgs['status'] = $filterStatus;
            ?>
            <div class="flex items-center justify-between border-t border-gray-100 px-6 py-4">
                <p class="text-xs text-gray-500">Page <?= $page ?> of <?= $totalPages ?></p>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query($queryArgs + ['page' => $page - 1]) ?>"
                           class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Previous</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= http_build_query($queryArgs + ['page' => $page + 1]) ?>"
                           class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
</div>

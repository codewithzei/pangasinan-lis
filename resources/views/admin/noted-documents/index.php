<?php
$tabLabels = [
    'ordinances'            => 'Provincial Ordinances',
    'resolutions'           => 'Provincial Resolutions',
    'municipal_ordinances'  => 'Municipal/City Ordinances',
    'municipal_resolutions' => 'Municipal/City Resolutions',
    'cases'                 => 'Cases',
    'others'                => 'Others',
];
if (!empty($includeCommunications)) {
    $tabLabels['communications'] = 'Communications';
}
$supportsNotedReturn = strpos($documentViewUrl, '/admin/inbox/show?id=') !== false
    || strpos($documentViewUrl, '/spsec/inbox/show?id=') !== false;
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
                <?php if (empty($includeCommunications)): ?>
                    Communications are kept in the separate Communications section.
                <?php endif; ?>
            </p>
            <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
        </div>
    </section>

    <section>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">All Noted Documents</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($totalNoted) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-7 7h6m-6 4h6"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-emerald-50 px-2 py-1 font-medium text-emerald-700">Across all document types</span>
                </div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($tabLabels[$tab]) ?></p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($totalRows) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-primary">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M4 5a2 2 0 012-2h8l6 6v10a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm10-2v6h6m-12 4h8m-8 4h8"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-blue-50 px-2 py-1 font-medium text-primary">Matching current filters</span>
                </div>
            </div>
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
                  class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                <div class="relative w-full sm:w-64">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="search" name="search" value="<?= htmlspecialchars($search) ?>"
                           placeholder="Search tracking # or subject…"
                           class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm
                                  text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none
                                  focus:ring-2 focus:ring-primary/20 lg:w-64">
                </div>
                <select name="status"
                        class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800
                               focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 lg:w-48">
                    <option value="">All Statuses</option>
                    <?php foreach ($documentStatuses as $statusOption): ?>
                        <option value="<?= (int) $statusOption['id'] ?>"
                            <?= $filterStatus === (string) $statusOption['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($statusOption['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit"
                        class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium
                               text-gray-700 transition hover:bg-gray-50">
                    Filter
                </button>
                <?php if ($search !== '' || $filterStatus !== ''): ?>
                    <a href="<?= htmlspecialchars($notedBaseUrl) ?>?tab=<?= urlencode($tab) ?>"
                       class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-center text-sm font-medium
                              text-gray-700 transition hover:bg-gray-50">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="whitespace-nowrap px-6 py-3 font-medium">Tracking Number</th>
                        <th class="px-6 py-3 font-medium">Subject Matter</th>
                        <th class="whitespace-nowrap px-6 py-3 font-medium">Document Type</th>
                        <th class="whitespace-nowrap px-6 py-3 font-medium">Attachments</th>
                        <th class="whitespace-nowrap px-6 py-3 font-medium">Date Noted</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 text-right font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (!$documents): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a3 3 0 016 0"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">No noted documents found.</p>
                                <p class="mt-1 text-xs text-gray-500">
                                    <?= ($search !== '' || $filterStatus !== '')
                                        ? 'Try adjusting your filters or search criteria.'
                                        : 'Documents marked as Noted will appear here.' ?>
                                </p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($documents as $doc): ?>
                            <?php $typeColor = $doc['document_type_badge_color'] ?: '#2563EB'; ?>
                            <?php $statusColor = $doc['status_badge_color'] ?: '#6B7280'; ?>
                            <tr class="transition hover:bg-gray-50/50">
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($doc['tracking_number']) ?></div>
                                    <div class="mt-0.5 text-xs text-gray-500">
                                        Received: <?= htmlspecialchars(date('M j, Y', strtotime($doc['date_received']))) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="max-w-sm text-sm font-medium text-gray-900 line-clamp-2">
                                        <?= htmlspecialchars($doc['subject_matter']) ?>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                          style="background-color: <?= htmlspecialchars($typeColor) ?>1a; color: <?= htmlspecialchars($typeColor) ?>;">
                                        <?= htmlspecialchars($doc['document_type_name']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <?= number_format((int) $doc['attachment_count']) ?>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-gray-600">
                                    <div class="text-sm text-gray-900">
                                        <?= htmlspecialchars(date('M j, Y', strtotime($doc['noted_at']))) ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?= htmlspecialchars(date('g:i A', strtotime($doc['noted_at']))) ?>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                          style="background-color: <?= htmlspecialchars($statusColor) ?>1a; color: <?= htmlspecialchars($statusColor) ?>;">
                                        <?= htmlspecialchars($doc['status'] ?? '—') ?>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    <?php
                                    $viewUrl = $documentViewUrl . (int) $doc['id'];
                                    if ($supportsNotedReturn) {
                                        $viewQuery = [
                                            'from' => 'noted',
                                            'tab' => $tab,
                                            'page' => $page,
                                        ];
                                        if ($search !== '') $viewQuery['search'] = $search;
                                        if ($filterStatus !== '') $viewQuery['status'] = $filterStatus;
                                        $viewUrl .= '&' . http_build_query($viewQuery);
                                    }
                                    ?>
                                    <a href="<?= htmlspecialchars($viewUrl) ?>"
                                       class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200
                                              px-3 py-1.5 text-xs font-medium text-gray-700 transition
                                              hover:border-primary hover:bg-blue-50 hover:text-primary"
                                       title="View noted document">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7
                                                     -1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        View
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

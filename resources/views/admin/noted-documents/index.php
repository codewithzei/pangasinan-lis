<?php
$tabLabels = [
    'ordinances'            => 'Provincial Ordinances',
    'resolutions'           => 'Provincial Resolutions',
    'municipal_ordinances'  => 'Ordinances',
    'municipal_resolutions' => 'Resolutions',
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
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative z-10 flex flex-col gap-6 px-6 py-7 sm:px-8 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-blue-100">Document Archive · Noted</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    <?= htmlspecialchars($pageTitle) ?>
                </h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-blue-100">
                    <?= htmlspecialchars($pageSubtitle) ?>
                    <?php if (empty($includeCommunications)): ?>
                        Communications are kept in the separate Communications section.
                    <?php endif; ?>
                </p>
            </div>
            <div class="flex items-center gap-3 border-t border-white/15 pt-4 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
                <span class="flex h-2.5 w-2.5 rounded-full bg-emerald-300 ring-4 ring-emerald-300/20"></span>
                <div>
                    <p class="text-xs font-medium text-white">Noted documents</p>
                    <p class="mt-0.5 text-xs text-blue-100/80"><?= htmlspecialchars(date('l, F j, Y')) ?></p>
                </div>
            </div>
        </div>
        <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
    </section>

    <section>
        <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-primary">At a glance</p>
                <h2 class="mt-1 text-xl font-semibold text-gray-900">Noted document activity</h2>
            </div>
            <p class="text-xs text-gray-500">Noted documents across document types</p>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-x-0 top-0 h-1 bg-emerald-500"></span>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">All Noted Documents</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format($totalNoted) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-7 7h6m-6 4h6"/>
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-[11px] text-gray-400">Across all document types</p>
            </article>
            <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-x-0 top-0 h-1 bg-blue-500"></span>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($tabLabels[$tab]) ?></p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format($totalRows) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M4 5a2 2 0 012-2h8l6 6v10a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm10-2v6h6m-12 4h8m-8 4h8"/>
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-[11px] text-gray-400">Records matching the current filters</p>
            </article>
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

    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex min-w-0 flex-col gap-4 border-b border-gray-100 px-5 py-5 md:px-6 xl:flex-row xl:items-center xl:justify-between">
            <div class="shrink-0">
                <h2 class="font-semibold text-gray-900"><?= htmlspecialchars($tabLabels[$tab]) ?></h2>
                <p class="mt-1 text-xs text-gray-500">
                    <?= number_format($totalRows) ?> record<?= $totalRows !== 1 ? 's' : '' ?> found
                </p>
            </div>
            <form method="GET" action="<?= htmlspecialchars($notedBaseUrl) ?>"
                  class="hidden w-full min-w-0 items-center justify-end gap-2 xl:flex xl:flex-1">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                <div class="relative w-full min-w-0 flex-none" style="max-width: 320px;">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="search" name="search" value="<?= htmlspecialchars($search) ?>"
                           placeholder="Search tracking # or subject..."
                           class="block w-full min-w-0 rounded-lg border border-gray-200 bg-white py-2.5 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </div>
                <select name="status" aria-label="Filter by status"
                        class="w-40 min-w-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">All statuses</option>
                    <?php foreach ($documentStatuses as $statusOption): ?>
                        <option value="<?= (int) $statusOption['id'] ?>" <?= $filterStatus === (string) $statusOption['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($statusOption['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="shrink-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                    Filter
                </button>
                <?php if ($search !== '' || $filterStatus !== ''): ?>
                    <a href="<?= htmlspecialchars($notedBaseUrl) ?>?tab=<?= urlencode($tab) ?>"
                       class="shrink-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-center text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                        Clear
                    </a>
                <?php endif; ?>
            </form>

            <div class="flex min-w-0 items-center gap-2 xl:hidden">
                <form method="GET" action="<?= htmlspecialchars($notedBaseUrl) ?>" class="relative min-w-0 flex-1">
                    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="search" name="search" value="<?= htmlspecialchars($search) ?>"
                           placeholder="Search tracking # or subject..."
                           class="block w-full min-w-0 rounded-lg border border-gray-200 py-2.5 pl-9 pr-3 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </form>
                <button type="button" data-inbox-filter-target="#noted-documents-filter-sheet"
                        aria-label="Open filters" aria-haspopup="dialog"
                        class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M6 12h12m-9 8h6"/>
                    </svg>
                    <?php if ($filterStatus !== ''): ?>
                        <span class="absolute right-1 top-1 h-2 w-2 rounded-full bg-primary"></span>
                    <?php endif; ?>
                </button>
            </div>
        </div>

        <div id="noted-documents-filter-sheet" class="fixed inset-0 z-50 hidden xl:hidden">
            <button type="button" class="inbox-filter-backdrop absolute inset-0 bg-gray-950/40" aria-label="Close filters"></button>
            <form method="GET" action="<?= htmlspecialchars($notedBaseUrl) ?>"
                  class="absolute inset-x-0 bottom-0 rounded-t-2xl bg-white p-5 shadow-2xl">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900">Filters</h3>
                    <button type="button" class="inbox-filter-close text-sm text-gray-500">Close</button>
                </div>
                <label for="noted-documents-status-filter" class="mb-1 block text-xs font-medium text-gray-600">Status</label>
                <select id="noted-documents-status-filter" name="status"
                        class="mb-4 w-full rounded-lg border border-gray-200 px-3 py-3 text-sm">
                    <option value="">All statuses</option>
                    <?php foreach ($documentStatuses as $statusOption): ?>
                        <option value="<?= (int) $statusOption['id'] ?>" <?= $filterStatus === (string) $statusOption['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($statusOption['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 rounded-lg bg-primary px-4 py-3 text-sm font-semibold text-white">Apply filters</button>
                    <?php if ($search !== '' || $filterStatus !== ''): ?>
                        <a href="<?= htmlspecialchars($notedBaseUrl) ?>?tab=<?= urlencode($tab) ?>"
                           class="rounded-lg border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php require __DIR__ . '/../../partials/inbox-mobile-filter-script.php'; ?>

        <div class="hidden overflow-x-auto xl:block">
            <table class="w-full min-w-[1050px] text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="whitespace-nowrap px-6 py-3 font-bold">Tracking Number</th>
                        <th class="px-6 py-3 font-bold">Subject Matter</th>
                        <th class="whitespace-nowrap px-6 py-3 font-bold">Document Type</th>
                        <th class="whitespace-nowrap px-6 py-3 font-bold">Attachments</th>
                        <th class="whitespace-nowrap px-6 py-3 font-bold">Date Noted</th>
                        <th class="px-6 py-3 font-bold">Status</th>
                        <th class="px-6 py-3 text-right font-bold">Action</th>
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
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                                          style="background-color: <?= htmlspecialchars($statusColor) ?>14; color: <?= htmlspecialchars($statusColor) ?>;">
                                        <span class="h-1.5 w-1.5 rounded-full" style="background-color: <?= htmlspecialchars($statusColor) ?>;"></span>
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
                                       class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition hover:border-primary hover:bg-primary hover:text-white"
                                       title="View noted document" aria-label="View noted document">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7
                                                     -1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="divide-y divide-gray-100 xl:hidden">
            <?php if (!$documents): ?>
                <div class="px-5 py-12 text-center">
                    <p class="text-sm font-medium text-gray-700">No noted documents found.</p>
                    <p class="mt-1 text-xs text-gray-500">
                        <?= ($search !== '' || $filterStatus !== '')
                            ? 'Try adjusting your filters or search criteria.'
                            : 'Documents marked as Noted will appear here.' ?>
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($documents as $doc): ?>
                    <?php
                    $typeColor = $doc['document_type_badge_color'] ?: '#2563EB';
                    $statusColor = $doc['status_badge_color'] ?: '#6B7280';
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
                    <article class="px-5 py-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="break-words font-semibold text-gray-900">
                                    <?= htmlspecialchars($doc['tracking_number']) ?>
                                </p>
                                <p class="mt-1 text-xs text-gray-400">
                                    Received <?= htmlspecialchars(date('M j, Y', strtotime($doc['date_received']))) ?>
                                </p>
                            </div>
                            <a href="<?= htmlspecialchars($viewUrl) ?>"
                               class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition hover:border-primary hover:bg-primary hover:text-white"
                               title="View noted document" aria-label="View noted document">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7
                                             -1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </a>
                        </div>
                        <p class="mt-3 break-words text-sm font-medium text-gray-900">
                            <?= htmlspecialchars($doc['subject_matter']) ?>
                        </p>
                        <span class="mt-1.5 inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                              style="background-color: <?= htmlspecialchars($typeColor) ?>14; color: <?= htmlspecialchars($typeColor) ?>;">
                            <?= htmlspecialchars($doc['document_type_name']) ?>
                        </span>
                        <div class="mt-4 grid grid-cols-2 gap-3 border-t border-gray-100 pt-3">
                            <div>
                                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Attachments</p>
                                <p class="mt-1 text-sm text-gray-600"><?= number_format((int) $doc['attachment_count']) ?></p>
                            </div>
                            <div>
                                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Date Noted</p>
                                <p class="mt-1 text-sm text-gray-600"><?= htmlspecialchars(date('M j, Y', strtotime($doc['noted_at']))) ?></p>
                                <p class="text-xs text-gray-400"><?= htmlspecialchars(date('g:i A', strtotime($doc['noted_at']))) ?></p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Status</p>
                                <span class="mt-1 inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                                      style="background-color: <?= htmlspecialchars($statusColor) ?>14; color: <?= htmlspecialchars($statusColor) ?>;">
                                    <span class="h-1.5 w-1.5 rounded-full" style="background-color: <?= htmlspecialchars($statusColor) ?>;"></span>
                                    <?= htmlspecialchars($doc['status'] ?? '—') ?>
                                </span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php
        $queryArgs = ['tab' => $tab];
        if ($search !== '') $queryArgs['search'] = $search;
        if ($filterStatus !== '') $queryArgs['status'] = $filterStatus;
        $queryString = '&' . http_build_query($queryArgs);
        $startPage = max(1, $page - 2);
        $endPage = min($totalPages, $page + 2);
        ?>
        <div class="flex flex-col items-center justify-between gap-3 border-t border-gray-100 px-5 py-4 sm:flex-row md:px-6">
            <p class="text-xs text-gray-500">
                Showing page <span class="font-medium text-gray-700"><?= $page ?></span> of
                <span class="font-medium text-gray-700"><?= $totalPages ?></span>
                (<?= number_format($totalRows) ?> total records)
            </p>
            <div class="flex flex-wrap items-center justify-center gap-1">
                <?php if ($page > 1): ?>
                    <a href="<?= htmlspecialchars($notedBaseUrl . '?page=' . ($page - 1) . $queryString) ?>"
                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                        Prev
                    </a>
                <?php endif; ?>
                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <a href="<?= htmlspecialchars($notedBaseUrl . '?page=' . $i . $queryString) ?>"
                       class="rounded-lg border px-3 py-1.5 text-sm font-medium transition
                              <?= $i === $page
                                  ? 'border-primary bg-primary text-white'
                                  : 'border-gray-200 text-gray-700 hover:bg-gray-50' ?>"
                       <?= $i === $page ? 'aria-current="page"' : '' ?>>
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= htmlspecialchars($notedBaseUrl . '?page=' . ($page + 1) . $queryString) ?>"
                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                        Next
                    </a>
                <?php endif; ?>
                </div>
        </div>
    </section>
</div>

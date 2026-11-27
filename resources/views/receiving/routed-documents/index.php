<?php
ob_start();

$pageTitle = $pageTitle ?? 'Routed Documents';
$pageSubtitle = $pageSubtitle ?? '';
$totalDocuments = $totalDocuments ?? 0;
$pendingDocuments = $pendingDocuments ?? 0;
$routedDocuments = $routedDocuments ?? 0;
$totalRows = $totalRows ?? 0;
$search = $search ?? '';
$filterStatus = $filterStatus ?? '';
$filterType = $filterType ?? '';
$filterDivision = $filterDivision ?? '';
$filterTab = $filterTab ?? 'ongoing';
$tabCounts = $tabCounts ?? [];
$documents = $documents ?? [];
$documentStatuses = $documentStatuses ?? [];
$divisions = $divisions ?? [];
$totalPages = $totalPages ?? 1;
$page = $page ?? 1;

// Helper function to format phase names
function formatPhaseName(string $phase): string
{
    $phaseMap = [
        'RECEIVING' => 'Receiving',
        'ADMIN' => 'Admin',
        'SP_SECRETARY' => 'SP Secretary',
        'PLENARY' => 'Plenary',
        'COMMITTEE' => 'Committee',
        'FINALIZED' => 'Finalized',
        'FILED' => 'Filed',
    ];
    return $phaseMap[$phase] ?? $phase;
}
?>

<div class="space-y-6">

    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative z-10 flex flex-col gap-6 px-6 py-7 sm:px-8">
            <div class="max-w-3xl">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-blue-100">DOCUMENT TRACKING</p>
                    <h1 class="mt-2 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                        <?= htmlspecialchars($pageTitle) ?>
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-blue-100">
                        <?= htmlspecialchars($pageSubtitle) ?>
                    </p>
            </div>
        </div>
        <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
    </section>

    <section>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-x-0 top-0 h-1 bg-primary"></span>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-medium text-gray-500">Total Documents</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format($totalDocuments) ?></p>
                        <p class="mt-1 text-[11px] text-gray-400">All documents</p>
                    </div>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-primary">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </span>
                </div>
            </article>

            <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-x-0 top-0 h-1 bg-amber-500"></span>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-medium text-gray-500">Pending Documents</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format($pendingDocuments) ?></p>
                        <p class="mt-1 text-[11px] text-gray-400">Awaiting action</p>
                    </div>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </span>
                </div>
            </article>

            <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-x-0 top-0 h-1 bg-emerald-500"></span>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-medium text-gray-500">Routed Documents</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format($routedDocuments) ?></p>
                        <p class="mt-1 text-[11px] text-gray-400">In workflow</p>
                    </div>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </span>
                </div>
            </article>
        </div>
    </section>

    <nav aria-label="Routed document status" class="flex gap-2 overflow-x-auto border-b border-gray-200">
        <?php
        $routedTabs = ['ongoing' => 'On Going', 'completed' => 'Completed', 'withdrawn' => 'Withdrawn'];
        foreach ($routedTabs as $tabKey => $tabLabel):
            $tabQuery = ['tab' => $tabKey];
            if ($search !== '') $tabQuery['search'] = $search;
            if ($filterStatus !== '') $tabQuery['status'] = $filterStatus;
            if ($filterDivision !== '') $tabQuery['division'] = $filterDivision;
        ?>
            <a href="<?= BASE_URL ?>/receiving/routed-documents?<?= htmlspecialchars(http_build_query($tabQuery), ENT_QUOTES, 'UTF-8') ?>"
               aria-current="<?= $filterTab === $tabKey ? 'page' : 'false' ?>"
               class="shrink-0 border-b-2 px-4 py-3 text-sm font-semibold transition <?= $filterTab === $tabKey
                   ? 'border-primary text-primary'
                   : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' ?>">
                <?= htmlspecialchars($tabLabel) ?>
                <span class="ml-1 rounded-full px-2 py-0.5 text-xs <?= $filterTab === $tabKey ? 'bg-primary/10 text-primary' : 'bg-gray-100 text-gray-600' ?>">
                    <?= number_format((int) ($tabCounts[$tabKey] ?? 0)) ?>
                </span>
            </a>
        <?php endforeach; ?>
    </nav>

    <section id="receiving-routed-list" class="rounded-2xl border border-gray-200 bg-white shadow-sm">
        <?php
        $responsiveInboxId = 'receiving-routed-list';
        $responsiveInboxLabels = ['Tracking Number', 'Subject Matter / Document Type', 'Status', 'Division', 'Action'];
        require __DIR__ . '/../../partials/responsive-inbox-table.php';
        ?>
        <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 2xl:flex-row 2xl:items-center 2xl:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900">Documents List</h2>
                <p class="mt-1 text-xs text-gray-500">
                    <?= $totalRows ?> record<?= $totalRows !== 1 ? 's' : '' ?> found
                </p>
            </div>
            <form method="GET" action="<?= BASE_URL ?>/receiving/routed-documents" class="hidden w-full min-w-0 flex-wrap items-center justify-end gap-2 2xl:flex 2xl:flex-1">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($filterTab, ENT_QUOTES, 'UTF-8') ?>">
                <div class="relative min-w-0 2xl:w-64 2xl:flex-none">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search tracking # or subject..."
                        class="block w-full min-w-0 rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </div>
                <select name="status" class="block w-full min-w-0 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 2xl:w-auto 2xl:flex-none">
                    <option value="">All Status</option>
                    <?php foreach ($documentStatuses as $status): ?>
                        <option value="<?= $status['id'] ?>" <?= $filterStatus == $status['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($status['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="division" class="block w-full min-w-0 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 2xl:w-auto 2xl:flex-none">
                    <option value="">All Divisions</option>
                    <?php foreach ($divisions as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $filterDivision === $key ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="w-auto shrink-0 justify-self-end whitespace-nowrap rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                    Filter
                </button>
                <?php if ($search !== '' || $filterStatus !== '' || $filterDivision !== ''): ?>
                    <a href="<?= BASE_URL ?>/receiving/routed-documents?tab=<?= urlencode($filterTab) ?>"
                       class="inline-flex w-auto shrink-0 justify-self-end items-center justify-center whitespace-nowrap rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition text-center">
                        Clear
                    </a>
                <?php endif; ?>
            </form>

            <div class="flex min-w-0 items-center gap-2 2xl:hidden">
                <form method="GET" action="<?= BASE_URL ?>/receiving/routed-documents" class="relative min-w-0 flex-1">
                    <input type="hidden" name="tab" value="<?= htmlspecialchars($filterTab, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="status" value="<?= htmlspecialchars((string) $filterStatus, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="division" value="<?= htmlspecialchars($filterDivision, ENT_QUOTES, 'UTF-8') ?>">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search tracking # or subject..."
                        class="block w-full min-w-0 rounded-xl border border-gray-200 bg-white py-2.5 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </form>
                <button type="button" data-inbox-filter-target="#receiving-routed-filter-sheet" aria-label="Open filters" aria-haspopup="dialog"
                    class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 hover:bg-gray-50">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M6 12h12m-9 8h6"/></svg>
                    <?php if ($filterStatus !== '' || $filterDivision !== ''): ?><span class="absolute right-1 top-1 h-2 w-2 rounded-full bg-primary"></span><?php endif; ?>
                </button>
            </div>
        </div>

        <div id="receiving-routed-filter-sheet" class="fixed inset-0 z-50 hidden 2xl:hidden">
            <button type="button" class="inbox-filter-backdrop absolute inset-0 bg-gray-950/40" aria-label="Close filters"></button>
            <form method="GET" action="<?= BASE_URL ?>/receiving/routed-documents" role="dialog" aria-modal="true" aria-labelledby="receiving-routed-filter-title"
                class="absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto rounded-t-2xl bg-white p-5 shadow-2xl">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($filterTab, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                <div class="mb-5 flex items-center justify-between">
                    <h3 id="receiving-routed-filter-title" class="font-semibold text-gray-900">Filters</h3>
                    <button type="button" class="inbox-filter-close rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-100">Close</button>
                </div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700" for="receiving-routed-mobile-status">Status</label>
                <select id="receiving-routed-mobile-status" name="status" class="mb-4 block w-full min-w-0 rounded-xl border border-gray-200 bg-white px-3 py-3 text-sm text-gray-800">
                    <option value="">All Status</option>
                    <?php foreach ($documentStatuses as $status): ?>
                        <option value="<?= $status['id'] ?>" <?= $filterStatus == $status['id'] ? 'selected' : '' ?>><?= htmlspecialchars($status['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="mb-1.5 block text-sm font-medium text-gray-700" for="receiving-routed-mobile-division">Division</label>
                <select id="receiving-routed-mobile-division" name="division" class="mb-5 block w-full min-w-0 rounded-xl border border-gray-200 bg-white px-3 py-3 text-sm text-gray-800">
                    <option value="">All Divisions</option>
                    <?php foreach ($divisions as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= $filterDivision === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="grid <?= $search !== '' || $filterStatus !== '' || $filterDivision !== '' ? 'grid-cols-2' : 'grid-cols-1' ?> gap-3">
                    <button type="submit" class="w-full rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-white hover:opacity-90">Apply Filters</button>
                    <?php if ($search !== '' || $filterStatus !== '' || $filterDivision !== ''): ?>
                        <a href="<?= BASE_URL ?>/receiving/routed-documents?tab=<?= urlencode($filterTab) ?>" class="flex w-full items-center justify-center rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php require __DIR__ . '/../../partials/inbox-mobile-filter-script.php'; ?>
        <div class="overflow-x-auto">
            <table class="inbox-responsive-table w-full text-left text-sm">
                <thead class="border-b border-gray-100 bg-gray-50/70 text-[11px] uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-bold">Tracking Number</th>
                        <th class="px-6 py-3 font-bold">Subject Matter / Document Type</th>
                        <th class="px-6 py-3 font-bold">Status</th>
                        <th class="px-6 py-3 font-bold">Division</th>
                        <th class="px-6 py-3 text-right font-bold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($documents)): ?>
                        <tr class="inbox-empty-row">
                            <td colspan="5" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">No documents found.</p>
                                <p class="mt-1 text-xs text-gray-500">Try adjusting your filters or search criteria.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($documents as $doc): ?>
                            <?php 
                            $statusBadgeColor = !empty($doc['status_badge_color']) ? $doc['status_badge_color'] : '#6B7280';
                            ?>
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($doc['tracking_number']) ?></div>
                                    <div class="mt-0.5 text-xs text-gray-500">
                                        <?= htmlspecialchars(date('M j, Y', strtotime($doc['date_received']))) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="max-w-md">
                                        <div class="text-sm font-medium text-gray-900 line-clamp-2">
                                            <?= htmlspecialchars($doc['subject_matter']) ?>
                                        </div>
                                        <div class="mt-1">
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                                  style="background-color: <?= htmlspecialchars($doc['document_type_badge_color'] ?? '#2563EB') ?>1a;
                                                         color: <?= htmlspecialchars($doc['document_type_badge_color'] ?? '#2563EB') ?>;">
                                                <?= htmlspecialchars($doc['document_type_name']) ?>
                                            </span>
                                            <?php if (!empty($doc['is_resubmission'])): ?>
                                                <span class="ml-1 inline-flex rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700">Resubmission</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                                          style="background-color: <?= htmlspecialchars($statusBadgeColor) ?>1a; color: <?= htmlspecialchars($statusBadgeColor) ?>;">
                                        <span class="h-1.5 w-1.5 rounded-full" style="background-color: <?= htmlspecialchars($statusBadgeColor) ?>;"></span>
                                        <?= htmlspecialchars($doc['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-lg bg-gray-50 border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700">
                                        <?= htmlspecialchars(formatPhaseName($doc['current_phase'])) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="<?= BASE_URL ?>/receiving/routed-documents/show?id=<?= $doc['id'] ?>"
                                       class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition hover:border-primary hover:bg-primary hover:text-white"
                                       title="View Details" aria-label="View routed document details">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="flex flex-col items-center justify-between gap-3 border-t border-gray-100 px-6 py-4 sm:flex-row">
            <p class="text-xs text-gray-500">
                Showing page <span class="font-medium text-gray-700"><?= $page ?></span> of
                <span class="font-medium text-gray-700"><?= $totalPages ?></span>
                (<?= $totalRows ?> total records)
            </p>
            <div class="flex items-center gap-1">
                <?php
                $query = [];
                if ($search !== '') $query['search'] = $search;
                if ($filterStatus !== '') $query['status'] = $filterStatus;
                if ($filterDivision !== '') $query['division'] = $filterDivision;
                $query['tab'] = $filterTab;
                $queryString = !empty($query) ? '&' . http_build_query($query) : '';
                ?>
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $queryString ?>"
                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                        Prev
                    </a>
                <?php endif; ?>
                <?php
                $startPage = max(1, $page - 2);
                $endPage   = min($totalPages, $page + 2);
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                    <a href="?page=<?= $i ?><?= $queryString ?>"
                       class="rounded-lg border px-3 py-1.5 text-sm font-medium transition <?= $i === $page
                           ? 'border-primary bg-primary text-white'
                           : 'border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $queryString ?>"
                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                        Next
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';
?>

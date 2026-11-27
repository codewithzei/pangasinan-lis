<?php
ob_start();

$pageTitle               = $pageTitle               ?? 'Communications';
$pageSubtitle            = $pageSubtitle            ?? '';
$totalNoted              = $totalNoted              ?? 0;
$totalRows               = $totalRows               ?? 0;
$search                  = $search                  ?? '';
$filterStatus            = $filterStatus            ?? '';
$filterCat               = $filterCat               ?? '';
$documents               = $documents               ?? [];
$documentStatuses        = $documentStatuses        ?? [];
$communicationCategories = $communicationCategories ?? [];
$totalPages              = $totalPages              ?? 1;
$page                    = $page                    ?? 1;
?>

<div class="space-y-6">

    <!-- ── Header Banner ────────────────────────────────────────────────────── -->
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative z-10 flex flex-col gap-6 px-6 py-7 sm:px-8 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-blue-100">Admin Section · Communications</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    <?= htmlspecialchars($pageTitle) ?>
                </h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-blue-100">
                    <?= htmlspecialchars($pageSubtitle) ?>
                </p>
            </div>
            <div class="flex items-center gap-3 border-t border-white/15 pt-4 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
                <span class="flex h-2.5 w-2.5 rounded-full bg-emerald-300 ring-4 ring-emerald-300/20"></span>
                <div>
                    <p class="text-xs font-medium text-white">Noted communications</p>
                    <p class="mt-0.5 text-xs text-blue-100/80"><?= htmlspecialchars(date('l, F j, Y')) ?></p>
                </div>
            </div>
        </div>
        <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
    </section>

    <!-- ── Statistics ──────────────────────────────────────────────────────── -->
    <section>
        <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-primary">At a glance</p>
                <h2 class="mt-1 text-xl font-semibold text-gray-900">Communications activity</h2>
            </div>
            <p class="text-xs text-gray-500">Noted documents and available categories</p>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">

            <!-- Total Noted -->
            <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-x-0 top-0 h-1 bg-emerald-500"></span>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Total Communications</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format($totalNoted) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-[11px] text-gray-400">Documents marked as Noted by Admin</p>
            </article>

            <!-- Filtered count -->
            <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-x-0 top-0 h-1 bg-blue-500"></span>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Filtered Results</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format($totalRows) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13
                                     13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293
                                     6.707A1 1 0 013 6V4z"/>
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-[11px] text-gray-400">Records matching the current filters</p>
            </article>

            <!-- Categories -->
            <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-x-0 top-0 h-1 bg-teal-500"></span>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Categories Available</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900">
                            <?= number_format(count($communicationCategories)) ?>
                        </p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-teal-50 text-teal-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0
                                     010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0
                                     013 12V7a4 4 0 014-4z"/>
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-[11px] text-gray-400">Active communication categories</p>
            </article>

        </div>
    </section>

    <!-- ── Documents Table ─────────────────────────────────────────────────── -->
    <section id="admin-communications-list" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex min-w-0 flex-col gap-4 border-b border-gray-100 px-5 py-5 md:px-6 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900">Communications List</h2>
                <p class="mt-1 text-xs text-gray-500">
                    <?= number_format($totalRows) ?> record<?= $totalRows !== 1 ? 's' : '' ?> found
                </p>
            </div>
            <form method="GET" action="<?= BASE_URL ?>/admin/communications"
                  class="hidden w-full min-w-0 items-center justify-end gap-2 xl:flex xl:flex-1">
                <div class="relative w-full min-w-0 flex-none" style="max-width: 320px;">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="search" name="search" value="<?= htmlspecialchars($search) ?>"
                           placeholder="Search tracking # or subject..."
                           class="block w-full min-w-0 rounded-lg border border-gray-200 bg-white py-2.5 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </div>
                <select name="category" aria-label="Filter by communication category"
                        class="w-44 min-w-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">All categories</option>
                    <?php foreach ($communicationCategories as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>" <?= (string) $filterCat === (string) $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="status" aria-label="Filter by status"
                        class="w-40 min-w-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">All statuses</option>
                    <?php foreach ($documentStatuses as $statusOption): ?>
                        <option value="<?= (int) $statusOption['id'] ?>" <?= (string) $filterStatus === (string) $statusOption['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($statusOption['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="shrink-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                    Filter
                </button>
                <?php if ($search !== '' || $filterCat !== '' || $filterStatus !== ''): ?>
                    <a href="<?= BASE_URL ?>/admin/communications"
                       class="shrink-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition text-center">
                        Clear
                    </a>
                <?php endif; ?>
            </form>

            <div class="flex min-w-0 items-center gap-2 xl:hidden">
                <form method="GET" action="<?= BASE_URL ?>/admin/communications" class="relative min-w-0 flex-1">
                    <input type="hidden" name="category" value="<?= htmlspecialchars($filterCat) ?>">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="search" name="search" value="<?= htmlspecialchars($search) ?>"
                           placeholder="Search tracking # or subject..."
                           class="block w-full min-w-0 rounded-lg border border-gray-200 py-2.5 pl-9 pr-3 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </form>
                <button type="button" data-inbox-filter-target="#admin-communications-filter-sheet"
                        aria-label="Open filters"
                        class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M6 12h12m-9 8h6"/>
                    </svg>
                    <?php if ($filterCat !== '' || $filterStatus !== ''): ?>
                        <span class="absolute right-1 top-1 h-2 w-2 rounded-full bg-primary"></span>
                    <?php endif; ?>
                </button>
            </div>
        </div>

        <div id="admin-communications-filter-sheet" class="fixed inset-0 z-50 hidden xl:hidden">
            <button type="button" class="inbox-filter-backdrop absolute inset-0 bg-gray-950/40" aria-label="Close filters"></button>
            <form method="GET" action="<?= BASE_URL ?>/admin/communications"
                  class="absolute inset-x-0 bottom-0 rounded-t-2xl bg-white p-5 shadow-2xl">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900">Filters</h3>
                    <button type="button" class="inbox-filter-close text-sm text-gray-500">Close</button>
                </div>
                <label for="communications-category-filter" class="mb-1 block text-xs font-medium text-gray-600">Communication category</label>
                <select id="communications-category-filter" name="category"
                        class="mb-3 w-full rounded-lg border border-gray-200 px-3 py-3 text-sm">
                    <option value="">All categories</option>
                    <?php foreach ($communicationCategories as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>" <?= (string) $filterCat === (string) $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for="communications-status-filter" class="mb-1 block text-xs font-medium text-gray-600">Status</label>
                <select id="communications-status-filter" name="status"
                        class="mb-4 w-full rounded-lg border border-gray-200 px-3 py-3 text-sm">
                    <option value="">All statuses</option>
                    <?php foreach ($documentStatuses as $statusOption): ?>
                        <option value="<?= (int) $statusOption['id'] ?>" <?= (string) $filterStatus === (string) $statusOption['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($statusOption['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="w-full rounded-lg bg-primary px-4 py-3 text-sm font-semibold text-white">
                    Apply Filter
                </button>
                <?php if ($search !== '' || $filterCat !== '' || $filterStatus !== ''): ?>
                    <a href="<?= BASE_URL ?>/admin/communications"
                       class="mt-2 block w-full rounded-lg border border-gray-200 px-4 py-3 text-center text-sm font-medium text-gray-700">
                        Clear filters
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="hidden overflow-x-auto xl:block">
            <?php require __DIR__ . '/../../partials/inbox-mobile-filter-script.php'; ?>
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-100 bg-gray-50/70 text-[11px] uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-bold">Tracking Number</th>
                        <th class="px-6 py-3 font-bold">Subject Matter</th>
                        <th class="px-6 py-3 font-bold">Document Type</th>
                        <th class="px-6 py-3 font-bold">Communication Category</th>
                        <th class="px-6 py-3 font-bold">Date Noted</th>
                        <th class="px-6 py-3 font-bold">Current Status</th>
                        <th class="px-6 py-3 font-bold">Noted By</th>
                        <th class="px-6 py-3 text-right font-bold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($documents)): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center
                                            rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                              d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9
                                                 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512
                                                 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">No communications found.</p>
                                <p class="mt-1 text-xs text-gray-500">
                                    <?= ($search !== '' || $filterCat !== '' || $filterStatus !== '')
                                        ? 'Try adjusting your search criteria or clearing filters.'
                                        : 'Communication documents marked as Noted will appear here.' ?>
                                </p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($documents as $doc): ?>
                            <?php $statusColor = !empty($doc['status_badge_color'])
                                ? $doc['status_badge_color'] : '#6B7280'; ?>
                            <tr class="hover:bg-gray-50/50 transition">

                                <!-- Tracking number -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-900">
                                        <?= htmlspecialchars($doc['tracking_number']) ?>
                                    </div>
                                    <div class="mt-0.5 text-xs text-gray-400">
                                        Received: <?= htmlspecialchars(date('M j, Y', strtotime($doc['date_received']))) ?>
                                    </div>
                                </td>

                                <!-- Subject matter -->
                                <td class="px-6 py-4 max-w-xs">
                                    <div class="text-sm font-medium text-gray-900 line-clamp-2">
                                        <?= htmlspecialchars($doc['subject_matter']) ?>
                                    </div>
                                </td>

                                <!-- Document type -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                          style="background-color: <?= htmlspecialchars($doc['document_type_badge_color'] ?? '#2563EB') ?>1a;
                                                 color: <?= htmlspecialchars($doc['document_type_badge_color'] ?? '#2563EB') ?>;">
                                        <?= htmlspecialchars($doc['document_type_name'] ?? '—') ?>
                                    </span>
                                </td>

                                <!-- Communication category -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if (!empty($doc['communication_category_name'])): ?>
                                        <span class="inline-flex items-center gap-1 rounded-full bg-gray-50
                                                         px-2.5 py-0.5 text-xs font-medium text-gray-600">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0
                                                         010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0
                                                         013 12V7a4 4 0 014-4z"/>
                                            </svg>
                                            <?= htmlspecialchars($doc['communication_category_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Date noted -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars(date('M j, Y', strtotime($doc['noted_at']))) ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?= htmlspecialchars(date('g:i A', strtotime($doc['noted_at']))) ?>
                                    </div>
                                </td>

                                <!-- Status -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                                          style="background-color: <?= htmlspecialchars($statusColor) ?>14; color: <?= htmlspecialchars($statusColor) ?>;">
                                        <span class="h-1.5 w-1.5 rounded-full" style="background-color: <?= htmlspecialchars($statusColor) ?>;"></span>
                                        <?= htmlspecialchars($doc['status'] ?? '—') ?>
                                    </span>
                                </td>

                                <!-- Noted by -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($doc['routed_by_username'] ?? '—') ?>
                                    </div>
                                    <?php if (!empty(trim($doc['routed_by_fullname'] ?? ''))): ?>
                                        <div class="text-xs text-gray-400">
                                            <?= htmlspecialchars(trim($doc['routed_by_fullname'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Action -->
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <a href="<?= BASE_URL ?>/admin/communications/show?id=<?= (int) $doc['id'] ?>"
                                       class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition hover:border-primary hover:bg-primary hover:text-white"
                                       title="View details" aria-label="View communication details">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943
                                                     9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943
                                                     -9.542-7z"/>
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
            <?php if (empty($documents)): ?>
                <div class="px-5 py-12 text-center">
                    <p class="text-sm font-medium text-gray-700">No communications found.</p>
                    <p class="mt-1 text-xs text-gray-500">
                        <?= ($search !== '' || $filterCat !== '' || $filterStatus !== '')
                            ? 'Try adjusting your search or filters.'
                            : 'Communication documents marked as Noted will appear here.' ?>
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($documents as $doc): ?>
                    <?php $statusColor = !empty($doc['status_badge_color'])
                        ? $doc['status_badge_color'] : '#6B7280'; ?>
                    <article class="px-5 py-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="<?= BASE_URL ?>/admin/communications/show?id=<?= (int) $doc['id'] ?>"
                                   class="break-words font-semibold text-gray-900 hover:text-primary">
                                    <?= htmlspecialchars($doc['tracking_number']) ?>
                                </a>
                                <p class="mt-1 text-xs text-gray-400">
                                    Received <?= htmlspecialchars(date('M j, Y', strtotime($doc['date_received']))) ?>
                                </p>
                            </div>
                            <a href="<?= BASE_URL ?>/admin/communications/show?id=<?= (int) $doc['id'] ?>"
                               class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition hover:border-primary hover:bg-primary hover:text-white"
                               title="View details" aria-label="View communication details">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943
                                             9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943
                                             -9.542-7z"/>
                                </svg>
                            </a>
                        </div>
                        <p class="mt-3 break-words text-sm font-medium text-gray-900">
                            <?= htmlspecialchars($doc['subject_matter']) ?>
                        </p>
                        <span class="mt-1.5 inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                              style="background-color: <?= htmlspecialchars($doc['document_type_badge_color'] ?? '#2563EB') ?>14; color: <?= htmlspecialchars($doc['document_type_badge_color'] ?? '#2563EB') ?>;">
                            <?= htmlspecialchars($doc['document_type_name'] ?? '—') ?>
                        </span>
                        <div class="mt-4 grid grid-cols-1 gap-3 border-t border-gray-100 pt-3 sm:grid-cols-2">
                            <div class="min-w-0">
                                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Category</p>
                                <p class="mt-1 break-words text-sm text-gray-600">
                                    <?= htmlspecialchars($doc['communication_category_name'] ?? '—') ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Date Noted</p>
                                <p class="mt-1 text-sm text-gray-700">
                                    <?= htmlspecialchars(date('M j, Y', strtotime($doc['noted_at']))) ?>
                                    <span class="text-xs text-gray-400"><?= htmlspecialchars(date('g:i A', strtotime($doc['noted_at']))) ?></span>
                                </p>
                            </div>
                            <div>
                                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Current Status</p>
                                <span class="mt-1 inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                                      style="background-color: <?= htmlspecialchars($statusColor) ?>14; color: <?= htmlspecialchars($statusColor) ?>;">
                                    <span class="h-1.5 w-1.5 rounded-full" style="background-color: <?= htmlspecialchars($statusColor) ?>;"></span>
                                    <?= htmlspecialchars($doc['status'] ?? '—') ?>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Noted By</p>
                                <p class="mt-1 break-words text-sm font-medium text-gray-700">
                                    <?= htmlspecialchars($doc['routed_by_username'] ?? '—') ?>
                                </p>
                                <?php if (!empty(trim($doc['routed_by_fullname'] ?? ''))): ?>
                                    <p class="text-xs text-gray-400"><?= htmlspecialchars(trim($doc['routed_by_fullname'])) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="divide-y divide-gray-100 xl:hidden">
            <?php if (empty($documents)): ?>
                <div class="px-5 py-12 text-center">
                    <p class="text-sm font-medium text-gray-700">No communications found.</p>
                    <p class="mt-1 text-xs text-gray-500">
                        <?= ($search !== '' || $filterCat !== '' || $filterStatus !== '')
                            ? 'Try adjusting your search or filters.'
                            : 'Communication documents marked as Noted will appear here.' ?>
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($documents as $doc): ?>
                    <?php $statusColor = !empty($doc['status_badge_color'])
                        ? $doc['status_badge_color'] : '#6B7280'; ?>
                    <article class="px-5 py-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="<?= BASE_URL ?>/admin/communications/show?id=<?= (int) $doc['id'] ?>"
                                   class="break-words font-semibold text-gray-900 hover:text-primary">
                                    <?= htmlspecialchars($doc['tracking_number']) ?>
                                </a>
                                <p class="mt-1 text-xs text-gray-400">
                                    Received <?= htmlspecialchars(date('M j, Y', strtotime($doc['date_received']))) ?>
                                </p>
                            </div>
                            <a href="<?= BASE_URL ?>/admin/communications/show?id=<?= (int) $doc['id'] ?>"
                               class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition hover:border-primary hover:bg-primary hover:text-white"
                               title="View details" aria-label="View communication details">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943
                                             9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943
                                             -9.542-7z"/>
                                </svg>
                            </a>
                        </div>
                        <p class="mt-3 break-words text-sm font-medium text-gray-900">
                            <?= htmlspecialchars($doc['subject_matter']) ?>
                        </p>
                        <span class="mt-1.5 inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                              style="background-color: <?= htmlspecialchars($doc['document_type_badge_color'] ?? '#2563EB') ?>14; color: <?= htmlspecialchars($doc['document_type_badge_color'] ?? '#2563EB') ?>;">
                            <?= htmlspecialchars($doc['document_type_name'] ?? '—') ?>
                        </span>
                        <div class="mt-4 grid grid-cols-1 gap-3 border-t border-gray-100 pt-3 sm:grid-cols-2">
                            <div class="min-w-0">
                                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Category</p>
                                <p class="mt-1 break-words text-sm text-gray-600">
                                    <?= htmlspecialchars($doc['communication_category_name'] ?? '—') ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Date Noted</p>
                                <p class="mt-1 text-sm text-gray-700">
                                    <?= htmlspecialchars(date('M j, Y', strtotime($doc['noted_at']))) ?>
                                    <span class="text-xs text-gray-400"><?= htmlspecialchars(date('g:i A', strtotime($doc['noted_at']))) ?></span>
                                </p>
                            </div>
                            <div>
                                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Current Status</p>
                                <span class="mt-1 inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                                      style="background-color: <?= htmlspecialchars($statusColor) ?>14; color: <?= htmlspecialchars($statusColor) ?>;">
                                    <span class="h-1.5 w-1.5 rounded-full" style="background-color: <?= htmlspecialchars($statusColor) ?>;"></span>
                                    <?= htmlspecialchars($doc['status'] ?? '—') ?>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Noted By</p>
                                <p class="mt-1 break-words text-sm font-medium text-gray-700">
                                    <?= htmlspecialchars($doc['routed_by_username'] ?? '—') ?>
                                </p>
                                <?php if (!empty(trim($doc['routed_by_fullname'] ?? ''))): ?>
                                    <p class="text-xs text-gray-400"><?= htmlspecialchars(trim($doc['routed_by_fullname'])) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <div class="flex flex-col items-center justify-between gap-3 border-t border-gray-100
                    px-6 py-4 sm:flex-row">
            <p class="text-xs text-gray-500">
                Showing page <span class="font-medium text-gray-700"><?= $page ?></span> of
                <span class="font-medium text-gray-700"><?= $totalPages ?></span>
                (<?= $totalRows ?> total records)
            </p>
            <div class="flex items-center gap-1">
                <?php
                $qArgs = [];
                if ($search !== '')       $qArgs['search']   = $search;
                if ($filterCat !== '')    $qArgs['category'] = $filterCat;
                if ($filterStatus !== '') $qArgs['status']   = $filterStatus;
                $qs = !empty($qArgs) ? '&' . http_build_query($qArgs) : '';
                ?>
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $qs ?>"
                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium
                              text-gray-700 hover:bg-gray-50 transition">
                        Prev
                    </a>
                <?php endif; ?>
                <?php
                $startPage = max(1, $page - 2);
                $endPage   = min($totalPages, $page + 2);
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                    <a href="?page=<?= $i ?><?= $qs ?>"
                       class="rounded-lg border px-3 py-1.5 text-sm font-medium transition
                              <?= $i === $page
                                  ? 'border-primary bg-primary text-white'
                                  : 'border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $qs ?>"
                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium
                              text-gray-700 hover:bg-gray-50 transition">
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

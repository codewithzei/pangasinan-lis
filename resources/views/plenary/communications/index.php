<?php
/**
 * Plenary Communications — Noted Communications
 *
 * Shows Communication documents that were marked "Noted" at Plenary First
 * Reading.  Finalized Communications (APPROVED / adopted-report) are shown
 * on the Finalized Documents > Communications tab instead.
 *
 * Variables supplied by PlenaryCommController::index():
 *   $communications  array
 *   $page            int
 *   $totalPages      int
 *   $total           int
 *   $search          string
 *   $tab             string  ('noted')
 *   $tabCounts       array   ['noted' => int]
 *   $categories      array   communication_categories for filter
 *   $filterCat       string
 *   $success         string|null
 *   $error           string|null
 */

$communications = $communications ?? [];
$page           = $page           ?? 1;
$totalPages     = $totalPages     ?? 1;
$total          = $total          ?? 0;
$search         = $search         ?? '';
$tab            = $tab            ?? 'noted';
$tabCounts      = $tabCounts      ?? ['noted' => 0];
$categories     = $categories     ?? [];
$filterCat      = $filterCat      ?? '';

ob_start();
?>

<div class="space-y-6">

    <?php if (!empty($success ?? null)): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($error ?? null)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Header -->
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative z-10 flex flex-col gap-6 px-6 py-7 sm:px-8 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-blue-100">Plenary · Communications</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-white sm:text-3xl">Plenary Communications</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-blue-100">
                    Communications noted at Plenary First Reading.
                    Finalized Communications (approved or adopted) appear in
                    <a href="<?= BASE_URL ?>/plenary/status-list?tab=communications"
                       class="font-medium text-white underline underline-offset-2 hover:text-blue-100">
                        Finalized Documents → Communications
                    </a>.
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
    </section>

    <!-- At a glance -->
    <section>
        <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-primary">At a glance</p>
                <h2 class="mt-1 text-xl font-semibold text-gray-900">Communications activity</h2>
            </div>
            <p class="text-xs text-gray-500">Marked as Noted at Plenary First Reading</p>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-x-0 top-0 h-1 bg-emerald-500"></span>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Noted Communications</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format($tabCounts['noted'] ?? 0) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-[11px] text-gray-400">Documents marked as Noted by Plenary</p>
            </article>
            <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-x-0 top-0 h-1 bg-blue-500"></span>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Filtered Results</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format($total) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-[11px] text-gray-400">Records matching the current filters</p>
            </article>
            <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-x-0 top-0 h-1 bg-teal-500"></span>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Categories Available</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format(count($categories)) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-teal-50 text-teal-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-[11px] text-gray-400">Active communication categories</p>
            </article>
        </div>
    </section>

    <!-- List -->
    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex min-w-0 flex-col gap-4 border-b border-gray-100 px-5 py-5 md:px-6 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900">Communications List</h2>
                <p class="mt-1 text-xs text-gray-500"><?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?> found</p>
            </div>
            <form method="GET" action="<?= BASE_URL ?>/plenary/communications"
                  class="hidden w-full min-w-0 items-center justify-end gap-2 xl:flex xl:flex-1">
                <div class="relative w-full min-w-0 flex-none" style="max-width: 320px;">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="search" name="search" value="<?= htmlspecialchars($search) ?>"
                           placeholder="Search tracking # or subject..."
                           class="block w-full min-w-0 rounded-lg border border-gray-200 bg-white py-2.5 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </div>
                <?php if (!empty($categories)): ?>
                <select name="category" aria-label="Filter by communication category"
                        class="w-44 min-w-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>"
                            <?= ((string) $filterCat === (string) $cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <button type="submit"
                        class="shrink-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Filter</button>
                <?php if ($search !== '' || $filterCat !== ''): ?>
                    <a href="<?= BASE_URL ?>/plenary/communications"
                       class="shrink-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition text-center">Clear</a>
                <?php endif; ?>
            </form>

            <div class="flex min-w-0 items-center gap-2 xl:hidden">
                <form method="GET" action="<?= BASE_URL ?>/plenary/communications" class="relative min-w-0 flex-1">
                    <input type="hidden" name="category" value="<?= htmlspecialchars($filterCat) ?>">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="search" name="search" value="<?= htmlspecialchars($search) ?>"
                           placeholder="Search tracking # or subject..."
                           class="block w-full min-w-0 rounded-lg border border-gray-200 py-2.5 pl-9 pr-3 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </form>
                <button type="button" data-inbox-filter-target="#plenary-communications-filter-sheet"
                        aria-label="Open filters"
                        class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M6 12h12m-9 8h6"/>
                    </svg>
                    <?php if ($filterCat !== ''): ?>
                        <span class="absolute right-1 top-1 h-2 w-2 rounded-full bg-primary"></span>
                    <?php endif; ?>
                </button>
            </div>
        </div>

        <div id="plenary-communications-filter-sheet" class="fixed inset-0 z-50 hidden xl:hidden">
            <button type="button" class="inbox-filter-backdrop absolute inset-0 bg-gray-950/40" aria-label="Close filters"></button>
            <form method="GET" action="<?= BASE_URL ?>/plenary/communications"
                  class="absolute inset-x-0 bottom-0 rounded-t-2xl bg-white p-5 shadow-2xl">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900">Filters</h3>
                    <button type="button" class="inbox-filter-close text-sm text-gray-500">Close</button>
                </div>
                <?php if (!empty($categories)): ?>
                    <label for="plenary-communications-category" class="mb-1 block text-xs font-medium text-gray-600">Communication category</label>
                    <select id="plenary-communications-category" name="category"
                            class="mb-4 w-full rounded-lg border border-gray-200 px-3 py-3 text-sm">
                        <option value="">All categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int) $cat['id'] ?>" <?= (string) $filterCat === (string) $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <button type="submit" class="w-full rounded-lg bg-primary px-4 py-3 text-sm font-semibold text-white">
                    Apply Filter
                </button>
                <?php if ($search !== '' || $filterCat !== ''): ?>
                    <a href="<?= BASE_URL ?>/plenary/communications"
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
                        <th class="px-6 py-3 font-bold">Reading Date</th>
                        <th class="px-6 py-3 font-bold">Current Status</th>
                        <th class="px-6 py-3 text-right font-bold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100/90">
                    <?php if (empty($communications)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">No Noted Communications yet.</p>
                                <p class="mt-1 text-xs text-gray-500">Communications marked Noted at Plenary First Reading will appear here.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($communications as $row): ?>
                            <?php
                            $statusColor = !empty($row['status_badge_color']) ? $row['status_badge_color'] : '#6B7280';
                            ?>
                            <tr class="transition hover:bg-gray-50/70">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($row['tracking_number']) ?></div>
                                    <div class="mt-0.5 text-xs text-gray-400">Received: <?= htmlspecialchars(date('M j, Y', strtotime($row['date_received']))) ?></div>
                                </td>
                                <td class="max-w-xs px-6 py-4">
                                    <p class="line-clamp-2 text-sm font-medium text-gray-900"><?= htmlspecialchars($row['subject_matter']) ?></p>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                          style="background-color: <?= htmlspecialchars($row['document_type_badge_color'] ?? '#2563EB') ?>1a; color: <?= htmlspecialchars($row['document_type_badge_color'] ?? '#2563EB') ?>;">
                                        <?= htmlspecialchars($row['document_type_name'] ?? '—') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if (!empty($row['communication_category_name'])): ?>
                                        <span class="inline-flex items-center gap-1 rounded-full bg-gray-50 px-2.5 py-0.5 text-xs font-medium text-gray-600">
                                            <?= htmlspecialchars($row['communication_category_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700 whitespace-nowrap">
                                    <?= !empty($row['reading_date']) ? htmlspecialchars(date('M j, Y', strtotime($row['reading_date']))) : '<span class="text-xs italic text-gray-400">—</span>' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                                          style="background-color: <?= htmlspecialchars($statusColor) ?>14; color: <?= htmlspecialchars($statusColor) ?>;">
                                        <span class="h-1.5 w-1.5 rounded-full" style="background-color: <?= htmlspecialchars($statusColor) ?>;"></span>
                                        <?= htmlspecialchars($row['status'] ?? '—') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <a href="<?= BASE_URL ?>/plenary/communications/show?id=<?= (int) $row['id'] ?>"
                                       aria-label="View communication details"
                                       title="View details"
                                       class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition hover:border-primary hover:bg-primary hover:text-white">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

        <div class="divide-y divide-gray-100 xl:hidden">
            <?php if (empty($communications)): ?>
                <div class="px-5 py-12 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 text-gray-400">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <p class="mt-3 text-sm font-medium text-gray-700">No Noted Communications yet.</p>
                    <p class="mt-1 text-xs text-gray-500">Communications marked Noted at Plenary First Reading will appear here.</p>
                </div>
            <?php else: ?>
                <?php foreach ($communications as $row): ?>
                    <?php $statusColor = !empty($row['status_badge_color']) ? $row['status_badge_color'] : '#6B7280'; ?>
                    <article class="px-5 py-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="<?= BASE_URL ?>/plenary/communications/show?id=<?= (int) $row['id'] ?>"
                                   class="break-words font-semibold text-gray-900 hover:text-primary">
                                    <?= htmlspecialchars($row['tracking_number']) ?>
                                </a>
                                <p class="mt-1 text-xs text-gray-400">
                                    Received <?= htmlspecialchars(date('M j, Y', strtotime($row['date_received']))) ?>
                                </p>
                            </div>
                            <a href="<?= BASE_URL ?>/plenary/communications/show?id=<?= (int) $row['id'] ?>"
                               class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition hover:border-primary hover:bg-primary hover:text-white"
                               title="View details" aria-label="View communication details">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </a>
                        </div>
                        <p class="mt-3 break-words text-sm font-medium text-gray-900"><?= htmlspecialchars($row['subject_matter']) ?></p>
                        <span class="mt-1.5 inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                              style="background-color: <?= htmlspecialchars($row['document_type_badge_color'] ?? '#2563EB') ?>14; color: <?= htmlspecialchars($row['document_type_badge_color'] ?? '#2563EB') ?>;">
                            <?= htmlspecialchars($row['document_type_name'] ?? '—') ?>
                        </span>
                        <dl class="mt-4 grid grid-cols-1 gap-3 border-t border-gray-100 pt-3 sm:grid-cols-2">
                            <div class="min-w-0">
                                <dt class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Category</dt>
                                <dd class="mt-1 break-words text-sm text-gray-600"><?= htmlspecialchars($row['communication_category_name'] ?? '—') ?></dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Reading Date</dt>
                                <dd class="mt-1 text-sm text-gray-700">
                                    <?= !empty($row['reading_date'])
                                        ? htmlspecialchars(date('M j, Y', strtotime($row['reading_date'])))
                                        : '<span class="text-xs italic text-gray-400">—</span>' ?>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Current Status</dt>
                                <dd>
                                    <span class="mt-1 inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                                          style="background-color: <?= htmlspecialchars($statusColor) ?>14; color: <?= htmlspecialchars($statusColor) ?>;">
                                        <span class="h-1.5 w-1.5 rounded-full" style="background-color: <?= htmlspecialchars($statusColor) ?>;"></span>
                                        <?= htmlspecialchars($row['status'] ?? '—') ?>
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php
        $paginationQuery = [];
        if ($search !== '') {
            $paginationQuery['search'] = $search;
        }
        if ($filterCat !== '') {
            $paginationQuery['category'] = $filterCat;
        }
        $paginationQueryString = http_build_query($paginationQuery);
        $paginationSuffix = $paginationQueryString !== '' ? '&' . $paginationQueryString : '';
        ?>
        <div class="flex flex-col items-center justify-between gap-3 border-t border-gray-100 px-6 py-4 sm:flex-row">
            <p class="text-xs text-gray-500">
                Showing page <span class="font-medium text-gray-700"><?= $page ?></span> of
                <span class="font-medium text-gray-700"><?= $totalPages ?></span>
                (<?= number_format($total) ?> total records)
            </p>
            <nav class="flex items-center gap-1" aria-label="Pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $paginationSuffix ?>"
                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                        Prev
                    </a>
                <?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="?page=<?= $i ?><?= $paginationSuffix ?>"
                       <?= $i === $page ? 'aria-current="page"' : '' ?>
                       class="rounded-lg border px-3 py-1.5 text-sm font-medium transition <?= $i === $page
                           ? 'border-primary bg-primary text-white'
                           : 'border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $paginationSuffix ?>"
                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                        Next
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    </section>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

<?php
/**
 * Archived Records — List page
 *
 * Variables from ArchivedRecordsController::index():
 *   $records        array   Paginated list rows
 *   $total          int
 *   $totalCount     int     Total unfiltered records
 *   $totalPages     int
 *   $page           int
 *   $search         string
 *   $filterType     string
 *   $filterFrom     string   Start date filter
 *   $filterTo       string   End date filter
 *   $documentTypes  array   For filter dropdown
 *   $spMembers      array   For create modal
 *   $committees     array   For create modal
 *   $pageTitle      string
 */

$records       = $records       ?? [];
$pageTitle     = $pageTitle     ?? 'Archived Records';
$total         = $total         ?? 0;
$totalCount    = $totalCount    ?? 0;
$publishedCount = $publishedCount ?? 0;
$recordsWithAttachments = $recordsWithAttachments ?? 0;
$totalPages    = $totalPages    ?? 1;
$page          = $page          ?? 1;
$search        = $search        ?? '';
$filterType    = $filterType    ?? '';
$filterFrom    = $filterFrom    ?? '';
$filterTo      = $filterTo      ?? '';
$documentTypes = $documentTypes ?? [];
$spMembers     = $spMembers     ?? [];
$committees    = $committees    ?? [];

$hasFilter = ($search !== '' || $filterType !== '' || $filterFrom !== '' || $filterTo !== '');

// Build query-string helper for pagination links
function arQueryString(array $extra = []): string {
    global $search, $filterType, $filterFrom, $filterTo;
    $q = [];
    if ($search !== '')      $q['search'] = $search;
    if ($filterType !== '')  $q['type']   = $filterType;
    if ($filterFrom !== '')  $q['date_from'] = $filterFrom;
    if ($filterTo !== '')    $q['date_to']   = $filterTo;
    $q = array_merge($q, $extra);
    return $q ? ('?' . http_build_query($q)) : '';
}

ob_start();
?>

<div class="space-y-6">

    <!-- ── Page header ─────────────────────────────────────────────────────── -->
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative z-10 flex flex-col gap-6 px-6 py-7 sm:px-8 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-blue-100">Admin · Document Management</p>
                    <h1 class="mt-2 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                        <?= htmlspecialchars($pageTitle) ?>
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-blue-100">
                        <?= htmlspecialchars($pageSubtitle ?? '') ?>
                    </p>
                </div>
                <div class="flex flex-col items-stretch gap-4 border-t border-white/15 pt-4 lg:items-end lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
                    <button type="button" onclick="openCreateModal()"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-white/15 px-4 py-2.5 text-sm font-semibold
                                   text-white ring-1 ring-white/30 transition hover:bg-white/25">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        New Record
                    </button>
                    <div class="flex items-center gap-3">
                        <span class="flex h-2.5 w-2.5 shrink-0 rounded-full bg-emerald-400 ring-4 ring-emerald-300/25"></span>
                        <div>
                            <p class="text-xs font-medium text-white">Archive collection</p>
                            <p class="mt-0.5 text-xs text-blue-100/80"><?= htmlspecialchars(date('l, F j, Y')) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
    </section>

    <!-- ── Stats card ──────────────────────────────────────────────────────── -->
    <section>
        <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-primary">At a glance</p>
                <h2 class="mt-1 text-xl font-semibold text-gray-900">Archive activity</h2>
            </div>
            <p class="text-xs text-gray-500">Archived legislative records and current results</p>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-x-0 top-0 h-1 bg-blue-500"></span>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Total Records</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format($totalCount) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-primary">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-[11px] text-gray-400">Records in the archive</p>
            </article>
            <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-x-0 top-0 h-1 bg-emerald-500"></span>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Current Results</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format($total) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-[11px] text-gray-400"><?= $hasFilter ? 'Matching the selected filters' : 'All matching archive records' ?></p>
            </article>
            <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-x-0 top-0 h-1 bg-sky-500"></span>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Published to Portal</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format($publishedCount) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-sky-50 text-sky-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            <circle cx="12" cy="12" r="3" stroke-width="1.8"/>
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-[11px] text-gray-400">Records available on the public portal</p>
            </article>
            <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-x-0 top-0 h-1 bg-amber-500"></span>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Records with Attachments</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format($recordsWithAttachments) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-[11px] text-gray-400">Archived records with one or more files</p>
            </article>
        </div>
    </section>

    <!-- ── Table section ───────────────────────────────────────────────────── -->
    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">

        <!-- List heading and filters -->
        <div class="flex min-w-0 flex-col gap-4 border-b border-gray-100 px-5 py-5 md:px-6 xl:flex-row xl:items-center xl:justify-between">
            <div class="min-w-0">
                <h2 class="text-base font-semibold text-gray-900">Archived records</h2>
                <p class="mt-1 text-xs text-gray-500">
                    <?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?> found<?= $hasFilter ? ' · filtered' : '' ?>
                </p>
            </div>

            <form method="GET" action="<?= BASE_URL ?>/admin/archived-records"
                  class="flex min-w-0 items-center gap-2 xl:hidden">
                <input type="hidden" name="type" value="<?= htmlspecialchars((string) $filterType) ?>">
                <input type="hidden" name="date_from" value="<?= htmlspecialchars($filterFrom) ?>">
                <input type="hidden" name="date_to" value="<?= htmlspecialchars($filterTo) ?>">
                <div class="relative min-w-0 flex-1">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="search" name="search" value="<?= htmlspecialchars($search) ?>"
                           placeholder="Search archived records"
                           class="block w-full min-w-0 rounded-lg border border-gray-200 bg-white py-2.5 pl-9 pr-3 text-sm text-gray-800
                                  placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </div>
                <button type="button" data-inbox-filter-target="#archivedMobileFilter"
                        aria-label="Open filters" aria-haspopup="dialog"
                        class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 4h18M6 12h12m-9 8h6"/>
                    </svg>
                    <?php if ($filterType !== '' || $filterFrom !== '' || $filterTo !== ''): ?>
                        <span class="absolute right-1 top-1 h-2 w-2 rounded-full bg-primary"></span>
                    <?php endif; ?>
                </button>
            </form>

            <form method="GET" action="<?= BASE_URL ?>/admin/archived-records"
                  class="hidden w-full min-w-0 items-end justify-end gap-2 xl:flex xl:flex-1">

                <!-- Search -->
                <div class="w-full min-w-0 flex-none xl:flex-1" style="max-width: 320px;">
                    <label for="archivedDesktopSearch" class="mb-1 block text-[10px] font-medium text-gray-500">Search</label>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input id="archivedDesktopSearch" type="search" name="search" value="<?= htmlspecialchars($search) ?>"
                               placeholder="Search number, subject, authors..."
                               class="block w-full min-w-0 rounded-lg border border-gray-200 bg-white py-2.5 pl-9 pr-3 text-sm
                                      text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none
                                      focus:ring-2 focus:ring-primary/20">
                    </div>
                </div>

                <div class="shrink-0">
                    <label for="archivedDesktopType" class="mb-1 block text-[10px] font-medium text-gray-500">Document type</label>
                    <select id="archivedDesktopType" name="type"
                            class="w-40 min-w-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700
                                   focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <option value="">All Types</option>
                        <?php foreach ($documentTypes as $dt): ?>
                            <option value="<?= (int) $dt['id'] ?>"
                                <?= (string) $filterType === (string) $dt['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dt['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex shrink-0 items-end gap-2">
                        <div>
                            <label for="archivedDesktopDateFrom" class="mb-1 block text-[10px] font-medium text-gray-500">Date from</label>
                            <input id="archivedDesktopDateFrom" type="date" name="date_from" value="<?= htmlspecialchars($filterFrom) ?>"
                                   class="w-36 min-w-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700
                                          focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        </div>
                        <div>
                            <label for="archivedDesktopDateTo" class="mb-1 block text-[10px] font-medium text-gray-500">Date to</label>
                            <input id="archivedDesktopDateTo" type="date" name="date_to" value="<?= htmlspecialchars($filterTo) ?>"
                                   class="w-36 min-w-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700
                                          focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        </div>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <button type="submit"
                            class="shrink-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium text-gray-700
                                   transition hover:bg-gray-50">
                        Filter
                    </button>
                    <?php if ($hasFilter): ?>
                        <a href="<?= BASE_URL ?>/admin/archived-records"
                           class="shrink-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium
                                  text-gray-700 transition hover:bg-gray-50">
                            Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <div id="archivedMobileFilter" class="fixed inset-0 z-50 hidden xl:hidden">
                <div class="inbox-filter-backdrop absolute inset-0 bg-gray-900/50"></div>
                <div class="absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto rounded-t-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Filter records</h3>
                            <p class="mt-0.5 text-xs text-gray-500">Choose type or approval date range</p>
                        </div>
                        <button type="button" aria-label="Close filters"
                                class="inbox-filter-close flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <form method="GET" action="<?= BASE_URL ?>/admin/archived-records" class="space-y-4 p-5">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-600">Document Type</label>
                            <select name="type" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800">
                                <option value="">All Types</option>
                                <?php foreach ($documentTypes as $dt): ?>
                                    <option value="<?= (int) $dt['id'] ?>"
                                        <?= (string) $filterType === (string) $dt['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dt['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="archivedMobileDateFrom" class="mb-1.5 block text-xs font-medium text-gray-600">Date from</label>
                            <input id="archivedMobileDateFrom" type="date" name="date_from" value="<?= htmlspecialchars($filterFrom) ?>"
                                   class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800">
                        </div>
                        <div>
                            <label for="archivedMobileDateTo" class="mb-1.5 block text-xs font-medium text-gray-600">Date to</label>
                            <input id="archivedMobileDateTo" type="date" name="date_to" value="<?= htmlspecialchars($filterTo) ?>"
                                   class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800">
                        </div>
                        <div class="flex gap-2 pt-2">
                            <button type="submit" class="flex-1 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white">
                                Apply filters
                            </button>
                            <?php if ($hasFilter): ?>
                                <a href="<?= BASE_URL ?>/admin/archived-records"
                                   class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-600">
                                    Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            <?php require __DIR__ . '/../../partials/inbox-mobile-filter-script.php'; ?>
        </div>

        <!-- Table -->
        <div class="hidden overflow-hidden 2xl:block">
            <table class="w-full table-fixed text-left text-sm">
                <thead class="border-b border-gray-100 bg-gray-50">
                    <tr class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th class="w-[15%] px-4 py-4">Approved No.</th>
                        <th class="w-[30%] px-4 py-4">Subject &amp; Type</th>
                        <th class="w-[19%] px-4 py-4">Authors / Sponsors</th>
                        <th class="w-[14%] px-4 py-4">Tags</th>
                        <th class="w-[11%] px-4 py-4">Date Approved</th>
                        <th class="w-[11%] px-4 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                              d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">
                                    <?= $hasFilter ? 'No records match your filters.' : 'No archived records yet.' ?>
                                </p>
                                <?php if ($hasFilter): ?>
                                    <p class="mt-1 text-xs text-gray-500">Try adjusting or clearing your filters.</p>
                                <?php else: ?>
                                    <p class="mt-1 text-xs text-gray-500">Click "New Record" to add the first entry.</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $row): ?>
                            <?php
                            $badgeColor  = $row['document_type_badge_color'] ?? '#2563EB';
                            $tagList     = $row['tags'] ? array_filter(array_map('trim', explode(',', $row['tags']))) : [];
                            $authors     = $row['authors_summary'] ?? null;
                            $coauthors   = $row['coauthors_summary'] ?? null;
                            $attachCount = (int) ($row['attachment_count'] ?? 0);
                            ?>
                            <tr class="hover:bg-gray-50/60 transition-colors">

                                <!-- Approved No -->
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="font-semibold text-gray-900">
                                        <?= htmlspecialchars($row['approved_no']) ?>
                                    </div>
                                    <?php if ($attachCount > 0): ?>
                                        <div class="mt-1 flex items-center gap-1 text-xs text-gray-400">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                            </svg>
                                            <?= $attachCount ?> file<?= $attachCount !== 1 ? 's' : '' ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="mt-2">
                                        <?php if (!empty($row['is_public'])): ?>
                                            <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">
                                                Published to portal
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">
                                                Not published
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Subject & Type -->
                                <td class="px-6 py-4">
                                    <p class="max-w-xs leading-snug text-sm text-gray-800 line-clamp-2">
                                        <?= htmlspecialchars($row['subject_matter']) ?>
                                    </p>
                                    <span class="mt-1 inline-flex max-w-full items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                                          style="background-color: <?= htmlspecialchars($badgeColor) ?>18; color: <?= htmlspecialchars($badgeColor) ?>;">
                                        <?= htmlspecialchars($row['document_type_name'] ?? 'Unknown') ?>
                                    </span>
                                </td>

                                <!-- Authors / Sponsors -->
                                <td class="px-6 py-4">
                                    <?php if ($authors || $coauthors): ?>
                                        <div class="space-y-2">
                                            <?php foreach ([
                                                ['label' => 'Author', 'names' => $authors],
                                                ['label' => 'Co-author', 'names' => $coauthors],
                                            ] as $authorGroup): ?>
                                                <?php if ($authorGroup['names']): ?>
                                                    <div>
                                                        <p class="text-[10px] font-medium uppercase tracking-wide text-gray-400">
                                                            <?= $authorGroup['label'] ?>
                                                        </p>
                                                        <p class="mt-0.5 break-words text-xs leading-5 text-gray-700"
                                                           title="<?= htmlspecialchars($authorGroup['names']) ?>">
                                                            <?= htmlspecialchars($authorGroup['names']) ?>
                                                        </p>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400 italic">No authors</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Tags -->
                                <td class="px-6 py-4">
                                    <?php if (!empty($tagList)): ?>
                                        <div class="flex flex-wrap gap-1">
                                            <?php foreach (array_slice($tagList, 0, 3) as $tag): ?>
                                                <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">
                                                    <?= htmlspecialchars($tag) ?>
                                                </span>
                                            <?php endforeach; ?>
                                            <?php if (count($tagList) > 3): ?>
                                                <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-600">
                                                    +<?= count($tagList) - 3 ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400 italic">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Date Approved -->
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                    <?= htmlspecialchars(date('M j, Y', strtotime($row['date_approved']))) ?>
                                </td>

                                <!-- Actions -->
                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="<?= BASE_URL ?>/admin/archived-records/show?id=<?= (int) $row['id'] ?>"
                                           class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition hover:border-primary hover:bg-primary hover:text-white"
                                           title="View details" aria-label="View details">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </a>
                                        <button type="button"
                                                onclick="openDeleteModal(<?= (int) $row['id'] ?>, '<?= addslashes(htmlspecialchars($row['approved_no'])) ?>')"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition hover:border-red-300 hover:bg-red-50 hover:text-red-600"
                                                title="Delete record" aria-label="Delete record">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="space-y-3 p-3 2xl:hidden">
            <?php if (empty($records)): ?>
                <div class="rounded-xl border border-dashed border-gray-200 px-5 py-12 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 text-gray-400">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                        </svg>
                    </div>
                    <p class="mt-3 text-sm font-semibold text-gray-800">
                        <?= $hasFilter ? 'No records match your filters.' : 'No archived records yet.' ?>
                    </p>
                    <p class="mt-1 text-xs text-gray-500">
                        <?= $hasFilter ? 'Try adjusting or clearing your filters.' : 'Add a record using the New Record button above.' ?>
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($records as $row): ?>
                    <?php
                    $badgeColor = $row['document_type_badge_color'] ?? '#2563EB';
                    $tagList = $row['tags'] ? array_filter(array_map('trim', explode(',', $row['tags']))) : [];
                    $authors = $row['authors_summary'] ?? null;
                    $coauthors = $row['coauthors_summary'] ?? null;
                    $attachCount = (int) ($row['attachment_count'] ?? 0);
                    ?>
                    <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Approved No.</p>
                                <h3 class="mt-1 break-words text-sm font-bold text-gray-900">
                                    <?= htmlspecialchars($row['approved_no']) ?>
                                </h3>
                                <?php if ($attachCount > 0): ?>
                                    <p class="mt-1 text-xs text-gray-500"><?= $attachCount ?> file<?= $attachCount !== 1 ? 's' : '' ?></p>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($row['is_public'])): ?>
                                <span class="shrink-0 rounded-full bg-green-50 px-2.5 py-1 text-[10px] font-semibold text-green-700">
                                    Published
                                </span>
                            <?php else: ?>
                                <span class="shrink-0 rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-semibold text-amber-700">
                                    Not published
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="mt-3">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Subject &amp; Type</p>
                            <p class="mt-1 text-sm leading-5 text-gray-800">
                                <?= htmlspecialchars($row['subject_matter']) ?>
                            </p>
                            <span class="inline-flex max-w-full items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                                  style="background-color: <?= htmlspecialchars($badgeColor) ?>18; color: <?= htmlspecialchars($badgeColor) ?>;">
                                <?= htmlspecialchars($row['document_type_name'] ?? 'Unknown') ?>
                            </span>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1 text-xs text-gray-500">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                          d="M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z"/>
                                </svg>
                                <?= htmlspecialchars(date('M j, Y', strtotime($row['date_approved']))) ?>
                            </span>
                        </div>

                        <div class="mt-3 space-y-2 border-t border-gray-100 pt-3">
                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Authors / Sponsors</p>
                                <?php if ($authors || $coauthors): ?>
                                    <div class="mt-1 space-y-2">
                                        <?php foreach ([
                                            ['label' => 'Author', 'names' => $authors],
                                            ['label' => 'Co-author', 'names' => $coauthors],
                                        ] as $authorGroup): ?>
                                            <?php if ($authorGroup['names']): ?>
                                                <div>
                                                    <p class="text-[10px] font-medium uppercase tracking-wide text-gray-400"><?= $authorGroup['label'] ?></p>
                                                    <p class="mt-0.5 break-words text-xs leading-5 text-gray-700"><?= htmlspecialchars($authorGroup['names']) ?></p>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="mt-0.5 break-words text-xs text-gray-700"><span class="italic text-gray-400">No authors</span></p>
                                <?php endif; ?>
                            </div>
                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Tags</p>
                                <?php if (!empty($tagList)): ?>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        <?php foreach (array_slice($tagList, 0, 3) as $tag): ?>
                                            <span class="rounded-md bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700">
                                                <?= htmlspecialchars($tag) ?>
                                            </span>
                                        <?php endforeach; ?>
                                        <?php if (count($tagList) > 3): ?>
                                            <span class="rounded-md bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-600">
                                                +<?= count($tagList) - 3 ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="mt-0.5 text-xs italic text-gray-400">No tags</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mt-4 flex justify-end gap-2 border-t border-gray-100 pt-3">
                            <a href="<?= BASE_URL ?>/admin/archived-records/show?id=<?= (int) $row['id'] ?>"
                               class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition hover:border-primary hover:bg-primary hover:text-white"
                               title="View details" aria-label="View details">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </a>
                            <button type="button"
                                    onclick="openDeleteModal(<?= (int) $row['id'] ?>, '<?= addslashes(htmlspecialchars($row['approved_no'])) ?>')"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition hover:border-red-300 hover:bg-red-50 hover:text-red-600"
                                    title="Delete record" aria-label="Delete record">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <div class="flex flex-col items-center justify-between gap-3 border-t border-gray-100 px-6 py-4 sm:flex-row">
            <p class="text-xs text-gray-500">
                Showing page <span class="font-medium text-gray-700"><?= $page ?></span> of
                <span class="font-medium text-gray-700"><?= $totalPages ?></span>
                (<?= number_format($total) ?> total records)
            </p>
            <nav class="flex items-center gap-1" aria-label="Archived records pagination">
                <?php if ($page > 1): ?>
                    <a href="<?= BASE_URL ?>/admin/archived-records<?= arQueryString(['page' => $page - 1]) ?>"
                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                        Prev
                    </a>
                <?php endif; ?>
                <?php
                $startPage = max(1, $page - 2);
                $endPage   = min($totalPages, $page + 2);
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                    <a href="<?= BASE_URL ?>/admin/archived-records<?= arQueryString(['page' => $i]) ?>"
                       <?= $i === $page ? 'aria-current="page"' : '' ?>
                       class="rounded-lg border px-3 py-1.5 text-sm font-medium transition <?= $i === $page
                           ? 'border-primary bg-primary text-white'
                           : 'border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= BASE_URL ?>/admin/archived-records<?= arQueryString(['page' => $page + 1]) ?>"
                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                        Next
                    </a>
                <?php endif; ?>
            </nav>
        </div>

    </section>

</div>


<!-- ═══════════════════════════════════════════════════════════════════════════
     CREATE MODAL
     ═══════════════════════════════════════════════════════════════════════════ -->
<div id="createModal"
     class="fixed inset-0 z-[100] hidden items-center justify-center overflow-hidden"
     role="dialog" aria-modal="true" aria-labelledby="createModalTitle">
    <div id="createModalBackdrop"
         class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm opacity-0 transition-opacity duration-200"></div>
    <div id="createModalPanel"
         class="relative z-10 mx-auto flex max-h-[calc(100dvh_-_1rem)] w-[calc(100%_-_1rem)] max-w-4xl flex-col overflow-hidden rounded-2xl border
                border-gray-100 bg-white shadow-2xl transform scale-95 opacity-0 transition-all duration-200 sm:w-[calc(100%_-_2rem)] sm:max-h-[92vh]">

        <form method="POST" action="<?= BASE_URL ?>/admin/archived-records/store" enctype="multipart/form-data"
              class="flex min-h-0 flex-1 flex-col">
            <div data-archive-post></div>
            <!-- Header -->
            <div class="flex shrink-0 items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 sm:px-6 sm:py-5">
                <div class="min-w-0">
                    <h3 id="createModalTitle" class="text-lg font-semibold text-gray-900">New Archived Record</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Fill in the details below to create a new legislative record</p>
                </div>
                <button type="button" onclick="closeCreateModal()"
                        class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Body -->
            <div class="min-h-0 flex-1 space-y-5 overflow-y-auto overflow-x-hidden px-4 py-4 sm:px-6 sm:py-5">

                <!-- Row 1: Approved No + Date -->
                <div class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="create_approved_no" class="block text-xs font-semibold text-gray-700 mb-1.5">
                            Approved Number <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="create_approved_no" name="approved_no"
                               placeholder="e.g. Resolution No. 2024-001"
                               class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm text-gray-800
                                      placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                               required>
                    </div>
                    <div>
                        <label for="create_date_approved" class="block text-xs font-semibold text-gray-700 mb-1.5">
                            Date Approved <span class="text-red-500">*</span>
                        </label>
                        <input type="date" id="create_date_approved" name="date_approved"
                               class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm text-gray-800
                                      focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                               required>
                    </div>
                </div>

                <!-- Subject Matter -->
                <div>
                    <label for="create_subject_matter" class="block text-xs font-semibold text-gray-700 mb-1.5">
                        Subject Matter <span class="text-red-500">*</span>
                    </label>
                    <textarea id="create_subject_matter" name="subject_matter" rows="3"
                              placeholder="Official subject of this record…"
                              class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm text-gray-800
                                     placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 resize-y"
                              required></textarea>
                </div>

                <!-- Document Type -->
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">
                        Document Type <span class="text-red-500">*</span>
                    </label>
                    <div class="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <select name="document_type_id" id="create_doc_type_id"
                                    onchange="handleDocTypeChange('create')"
                                    class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm text-gray-800
                                           focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                                <option value="">— Select existing type —</option>
                                <?php foreach ($documentTypes as $dt): ?>
                                    <option value="<?= (int) $dt['id'] ?>"><?= htmlspecialchars($dt['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="mt-1 flex min-h-5 items-center justify-between gap-2">
                                <p class="text-xs text-gray-400">Select from list</p>
                                <button type="button" id="create_doc_type_remove" onclick="removeDocTypeSelection('create', 'dropdown')"
                                        class="hidden text-xs font-medium text-red-600 hover:text-red-700">
                                    Remove
                                </button>
                            </div>
                        </div>
                        <div>
                            <input type="text" name="custom_document_type_name" id="create_custom_type"
                                   placeholder="Enter custom type name"
                                   oninput="handleCustomTypeChange('create')"
                                   onchange="handleCustomTypeChange('create')"
                                   class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm text-gray-800
                                          placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                            <div class="mt-1 flex min-h-5 items-center justify-between gap-2">
                                <p class="text-xs text-gray-400">Or enter a custom name</p>
                                <button type="button" id="create_custom_type_remove" onclick="removeDocTypeSelection('create', 'custom')"
                                        class="hidden text-xs font-medium text-red-600 hover:text-red-700">
                                    Remove
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label for="create_description" class="block text-xs font-semibold text-gray-700 mb-1.5">Description</label>
                    <textarea id="create_description" name="description" rows="2"
                              placeholder="Optional additional description…"
                              class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm text-gray-800
                                     placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 resize-y"></textarea>
                </div>

                <!-- Tags -->
                <div>
                    <label for="create_tags" class="block text-xs font-semibold text-gray-700 mb-1.5">Tags</label>
                    <input type="text" id="create_tags" name="tags"
                           placeholder="budget, health, 2024 (comma-separated)"
                           class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm text-gray-800
                                  placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </div>

                <div class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3">
                    <label for="create_is_public" class="flex cursor-pointer items-start gap-3">
                        <input type="checkbox" id="create_is_public" name="is_public" value="1"
                               class="mt-0.5 h-4 w-4 flex-shrink-0 rounded border-blue-300 text-blue-600 focus:ring-blue-500">
                        <span>
                            <span class="block text-sm font-semibold text-blue-900">Publish this record to the public portal</span>
                            <span class="mt-0.5 block text-xs text-blue-800">
                                Leave unchecked to keep it private. Review the record and attachments before publishing.
                            </span>
                        </span>
                    </label>
                </div>

                <!-- Authors and co-authors -->
                <section class="rounded-xl border border-gray-200 p-4 sm:p-5">
                    <div class="mb-4">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900">Authors / Sponsors</h4>
                            <p class="mt-1 text-xs text-gray-500">Search and select one or more people. A person cannot be selected in both roles.</p>
                        </div>
                    </div>
                    <div class="grid min-w-0 grid-cols-1 gap-4 lg:grid-cols-2">
                        <?php foreach ([['AUTHOR', 'Authors / Sponsors'], ['CO_AUTHOR', 'Co-authors / Co-sponsors']] as [$roleValue, $roleLabel]): ?>
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-700"><?= $roleLabel ?></label>
                            <div class="archive-multiselect" data-list="members" data-role="<?= $roleValue ?>">
                                <div class="archive-multi-control">
                                    <div class="archive-multi-chips"></div>
                                    <input type="search" class="archive-multi-search" placeholder="Search <?= strtolower($roleLabel) ?>..."
                                           aria-label="Search <?= htmlspecialchars(strtolower($roleLabel)) ?>" autocomplete="off">
                                    <button type="button" class="archive-multi-toggle" aria-label="Show options">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                </div>
                                <div class="archive-multi-dropdown hidden" role="listbox" aria-multiselectable="true">
                                    <label class="archive-multi-select-all">
                                        <input type="checkbox" class="archive-multi-select-all-checkbox"
                                               aria-label="Select all matching people">
                                        <span>Select all matching options</span>
                                    </label>
                                    <?php foreach ($spMembers as $member): ?>
                                        <button type="button" class="archive-multi-option" data-id="<?= (int) $member['sp_member_id'] ?>">
                                            <?= htmlspecialchars($member['full_name']) ?><?= !empty($member['position']) ? ' — ' . htmlspecialchars($member['position']) : '' ?>
                                        </button>
                                    <?php endforeach; ?>
                                    <p class="archive-multi-empty hidden">No matching people found.</p>
                                </div>
                                <div class="mt-1 flex justify-end">
                                    <button type="button" class="archive-multi-clear hidden text-xs font-medium text-red-600 hover:text-red-700"
                                            onclick="clearArchiveMultiSelect(this)">
                                        Remove selection
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4">
                        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <p class="text-xs font-medium text-gray-700">Additional people</p>
                            <button type="button" onclick="addArchiveEntry('createCustomAuthorsList', 'customAuthorEntryTemplate')"
                                    class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-primary hover:bg-blue-50">
                                + Add person
                            </button>
                        </div>
                        <div id="createCustomAuthorsList" class="space-y-2"></div>
                    </div>
                </section>

                <!-- Committees -->
                <section class="rounded-xl border border-gray-200 p-4 sm:p-5">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900">Committees</h4>
                            <p class="mt-1 text-xs text-gray-500">Search and select one or more committees, or add a name.</p>
                        </div>
                        <button type="button" onclick="addArchiveEntry('createCustomCommitteesList', 'customCommitteeEntryTemplate')"
                                class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-primary hover:bg-blue-50">
                            + Add committee
                        </button>
                    </div>
                    <div class="archive-multiselect" data-list="committees">
                        <div class="archive-multi-control">
                            <div class="archive-multi-chips"></div>
                            <input type="search" class="archive-multi-search" placeholder="Search committees..."
                                   aria-label="Search committees" autocomplete="off">
                            <button type="button" class="archive-multi-toggle" aria-label="Show committee options">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                        </div>
                        <div class="archive-multi-dropdown hidden" role="listbox" aria-multiselectable="true">
                            <label class="archive-multi-select-all">
                                <input type="checkbox" class="archive-multi-select-all-checkbox"
                                       aria-label="Select all matching committees">
                                <span>Select all matching options</span>
                            </label>
                            <?php foreach ($committees as $committee): ?>
                                <button type="button" class="archive-multi-option" data-id="<?= (int) $committee['id'] ?>"><?= htmlspecialchars($committee['name']) ?></button>
                            <?php endforeach; ?>
                            <p class="archive-multi-empty hidden">No matching committees found.</p>
                        </div>
                        <div class="mt-1 flex justify-end">
                            <button type="button" class="archive-multi-clear hidden text-xs font-medium text-red-600 hover:text-red-700"
                                    onclick="clearArchiveMultiSelect(this)">
                                Remove selection
                            </button>
                        </div>
                    </div>
                    <div id="createCustomCommitteesList" class="mt-3 space-y-2"></div>
                </section>

                <!-- Attachments -->
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Attachments</label>
                    <label id="archiveAttachmentDropzone" for="archiveAttachmentInput" data-attachment-dropzone
                           class="flex h-32 w-full cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed
                                  border-gray-300 bg-gray-50 transition hover:bg-gray-100 focus-within:ring-2 focus-within:ring-primary/20">
                        <div class="flex flex-col items-center justify-center px-3 py-4 text-center">
                            <svg class="mb-2 h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <p class="mb-1 text-sm text-gray-600">
                                <span class="font-semibold text-gray-700">Click to upload</span> or drag and drop
                            </p>
                            <p class="text-xs text-gray-500">PDF, DOC, DOCX, XLS, XLSX, JPG, PNG (MAX. 25 MB each)</p>
                        <input id="archiveAttachmentInput" type="file" name="attachments[]" multiple
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp"
                                   class="hidden">
                        </div>
                    </label>
                    <p class="mt-1 text-xs text-gray-400">Maximum 10 files.</p>
                    <p id="archiveAttachmentError" class="mt-2 hidden text-xs font-medium text-red-600" role="alert"></p>
                    <ul id="archiveAttachmentList" class="mt-3 hidden space-y-2" aria-label="Selected attachments"></ul>
                </div>

            </div>

            <!-- Footer -->
            <div class="flex shrink-0 flex-col-reverse gap-2 border-t border-gray-100 bg-gray-50 px-4 py-3 sm:flex-row sm:justify-end sm:gap-3 sm:px-6 sm:py-4">
                <button type="button" onclick="closeCreateModal()"
                        class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium
                               text-gray-700 hover:bg-gray-50 transition sm:w-auto">
                    Cancel
                </button>
                <button type="submit"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2 text-sm font-semibold
                               text-white hover:bg-primary/90 transition sm:w-auto">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Create Record
                </button>
            </div>
        </form>
    </div>
</div>

<template id="customAuthorEntryTemplate">
    <div class="custom-author-entry grid min-w-0 grid-cols-1 gap-2 rounded-lg bg-gray-50 p-3 sm:grid-cols-[minmax(0,0.7fr)_minmax(0,1.3fr)_auto] sm:items-center">
        <select aria-label="Custom person role" class="custom-author-role w-full min-w-0 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
            <option value="AUTHOR">Author / Sponsor</option><option value="CO_AUTHOR">Co-author / Co-sponsor</option>
        </select>
        <input type="text" aria-label="Person name" maxlength="200" placeholder="Enter person name"
               class="custom-author-name w-full min-w-0 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
        <button type="button" onclick="removeArchiveEntry(this)" aria-label="Remove person" class="rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50">Remove</button>
    </div>
</template>
<template id="customCommitteeEntryTemplate">
    <div class="custom-committee-entry grid min-w-0 grid-cols-1 gap-2 rounded-lg bg-gray-50 p-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
        <input type="text" aria-label="Committee name" maxlength="200" placeholder="Enter committee name"
               class="custom-committee-name w-full min-w-0 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
        <button type="button" onclick="removeArchiveEntry(this)" aria-label="Remove committee" class="rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50">Remove</button>
    </div>
</template>


<!-- ═══════════════════════════════════════════════════════════════════════════
     DELETE CONFIRM MODAL
     ═══════════════════════════════════════════════════════════════════════════ -->
<div id="deleteModal"
     class="fixed inset-0 z-[100] hidden items-center justify-center"
     role="dialog" aria-modal="true">
    <div id="deleteModalBackdrop"
         class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm opacity-0 transition-opacity duration-200"></div>
    <div id="deleteModalPanel"
         class="relative z-10 mx-4 w-full max-w-sm rounded-2xl bg-white shadow-2xl border border-gray-100
                transform scale-95 opacity-0 transition-all duration-200">
        <form method="POST" action="<?= BASE_URL ?>/admin/archived-records/destroy">
            <input type="hidden" name="id" id="deleteRecordId">
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-red-100">
                        <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Delete Record</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            Are you sure you want to delete
                            <strong id="deleteRecordName" class="text-gray-900"></strong>?
                            This action cannot be undone.
                        </p>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4 rounded-b-2xl">
                <button type="button" onclick="closeDeleteModal()"
                        class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium
                               text-gray-700 hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit"
                        class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 transition">
                    Delete
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    .archive-multiselect { position: relative; min-width: 0; }
    .archive-multi-control { display: flex; min-width: 0; min-height: 2.75rem; flex-wrap: wrap; align-items: center; gap: .375rem; border: 1px solid #e5e7eb; border-radius: .75rem; background: #fff; padding: .375rem .5rem .375rem .75rem; }
    .archive-multi-control:focus-within { border-color: var(--color-primary, #1d4ed8); box-shadow: 0 0 0 3px rgba(29,78,216,.12); }
    .archive-multi-chips { display: contents; }
    .archive-multi-search { width: 100%; min-width: 0; flex: 1; border: 0; background: transparent; padding: .25rem 0; color: #1f2937; font-size: .875rem; outline: none; }
    .archive-multi-search::placeholder { color: #9ca3af; }
    .archive-multi-toggle { display: flex; height: 1.75rem; width: 1.75rem; flex-shrink: 0; align-items: center; justify-content: center; border: 0; border-radius: .5rem; background: transparent; color: #9ca3af; cursor: pointer; }
    .archive-multi-toggle:hover { background: #f3f4f6; color: #4b5563; }
    .archive-multi-dropdown { position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: 110; max-height: 15rem; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: .75rem; background: #fff; box-shadow: 0 10px 25px -5px rgba(0,0,0,.12), 0 4px 10px -3px rgba(0,0,0,.07); }
    .archive-multi-dropdown.hidden { display: none; }
    .archive-multi-select-all { position: sticky; top: 0; z-index: 1; display: flex; align-items: center; gap: .625rem; border-bottom: 1px solid #e5e7eb; background: #fff; padding: .625rem .75rem; color: #374151; font-size: .8125rem; font-weight: 600; cursor: pointer; }
    .archive-multi-select-all-checkbox { height: 1rem; width: 1rem; flex-shrink: 0; accent-color: #2563eb; }
    .archive-multi-option { display: flex; width: 100%; align-items: center; gap: .625rem; border: 0; background: #fff; padding: .625rem .75rem; text-align: left; color: #374151; font-size: .875rem; cursor: pointer; }
    .archive-multi-option::before { display: inline-flex; height: 1rem; width: 1rem; flex-shrink: 0; align-items: center; justify-content: center; border: 1px solid #d1d5db; border-radius: .25rem; background: #fff; color: #fff; content: ''; font-size: .75rem; line-height: 1; }
    .archive-multi-option[aria-selected="true"]::before { border-color: #2563eb; background: #2563eb; content: '✓'; }
    .archive-multi-option.hidden { display: none; }
    .archive-multi-option:hover, .archive-multi-option[aria-selected="true"] { background: #eff6ff; color: #1d4ed8; }
    .archive-multi-option[aria-selected="true"] { font-weight: 600; }
    .archive-multi-option:disabled { background: #f9fafb; color: #9ca3af; cursor: not-allowed; }
    .archive-multi-chip { display: inline-flex; max-width: 100%; align-items: center; gap: .25rem; border: 1px solid #bfdbfe; border-radius: 9999px; background: #eff6ff; padding: .2rem .35rem .2rem .625rem; color: #1d4ed8; font-size: .75rem; font-weight: 500; }
    .archive-multi-chip-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .archive-multi-chip-remove { display: flex; height: 1rem; width: 1rem; flex-shrink: 0; align-items: center; justify-content: center; border: 0; border-radius: 50%; background: transparent; color: #60a5fa; cursor: pointer; }
    .archive-multi-chip-remove:hover { background: #bfdbfe; color: #1d4ed8; }
    .archive-multi-empty { padding: .75rem; color: #9ca3af; text-align: center; font-size: .8125rem; }
</style>

<script>
// ── Generic modal open/close ─────────────────────────────────────────────────
function openModal(id) {
    const modal = document.getElementById(id);
    const backdrop = document.getElementById(id + 'Backdrop');
    const panel = document.getElementById(id + 'Panel');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    requestAnimationFrame(() => {
        backdrop.classList.remove('opacity-0');
        panel.classList.remove('scale-95', 'opacity-0');
        panel.classList.add('scale-100', 'opacity-100');
    });
    document.addEventListener('keydown', function esc(e) {
        if (e.key === 'Escape') { closeModal(id); document.removeEventListener('keydown', esc); }
    });
}

function closeModal(id) {
    const modal = document.getElementById(id);
    const backdrop = document.getElementById(id + 'Backdrop');
    const panel = document.getElementById(id + 'Panel');
    backdrop.classList.add('opacity-0');
    panel.classList.add('scale-95', 'opacity-0');
    panel.classList.remove('scale-100', 'opacity-100');
    setTimeout(() => { modal.classList.add('hidden'); modal.classList.remove('flex'); }, 200);
}

function openCreateModal()  { openModal('createModal'); }
function closeCreateModal() { closeModal('createModal'); }

function openDeleteModal(id, name) {
    document.getElementById('deleteRecordId').value = id;
    document.getElementById('deleteRecordName').textContent = name;
    openModal('deleteModal');
}
function closeDeleteModal() { closeModal('deleteModal'); }

function addArchiveEntry(containerId, templateId) {
    const fragment = document.getElementById(templateId).content.cloneNode(true);
    document.getElementById(containerId).appendChild(fragment);
}

function removeArchiveEntry(button) {
    const entry = button.closest('.custom-author-entry, .custom-committee-entry');
    if (entry) entry.remove();
}

function clearArchiveMultiSelect(button) {
    const widget = button.closest('.archive-multiselect');
    if (!widget) return;
    widget.archiveSelected.clear();
    renderArchiveMultiSelects();
}

const archiveMultiSelects = Array.from(document.querySelectorAll('#createModal .archive-multiselect'));

function archiveRoleHasMember(role, id) {
    return archiveMultiSelects.some((widget) =>
        widget.dataset.list === 'members' &&
        widget.dataset.role !== role &&
        widget.archiveSelected.has(id)
    );
}

function renderArchiveMultiSelects() {
    archiveMultiSelects.forEach((widget) => {
        const selected = widget.archiveSelected;
        const chips = widget.querySelector('.archive-multi-chips');
        const search = widget.querySelector('.archive-multi-search');
        const options = Array.from(widget.querySelectorAll('.archive-multi-option'));
        const selectAll = widget.querySelector('.archive-multi-select-all-checkbox');
        const query = search.value.trim().toLocaleLowerCase();
        widget.querySelector('.archive-multi-clear').classList.toggle('hidden', selected.size === 0);
        chips.replaceChildren();

        selected.forEach((id) => {
            const option = options.find((item) => item.dataset.id === id);
            if (!option) return;
            const chip = document.createElement('span');
            chip.className = 'archive-multi-chip';
            const label = document.createElement('span');
            label.className = 'archive-multi-chip-label';
            label.textContent = option.textContent.trim();
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'archive-multi-chip-remove';
            remove.setAttribute('aria-label', 'Remove ' + label.textContent);
            remove.textContent = '×';
            remove.addEventListener('click', () => {
                selected.delete(id);
                renderArchiveMultiSelects();
            });
            chip.append(label, remove);
            chips.appendChild(chip);
        });

        let visibleCount = 0;
        options.forEach((option) => {
            const id = option.dataset.id;
            const isVisible = option.textContent.trim().toLocaleLowerCase().includes(query);
            const isSelected = selected.has(id);
            const isTaken = widget.dataset.list === 'members' &&
                !isSelected && archiveRoleHasMember(widget.dataset.role, id);
            option.classList.toggle('hidden', !isVisible);
            option.disabled = isTaken;
            option.setAttribute('aria-selected', String(isSelected));
            if (isVisible && !isTaken) visibleCount++;
        });
        widget.querySelector('.archive-multi-empty').classList.toggle('hidden', visibleCount > 0);
        const availableOptions = options.filter((option) =>
            !option.classList.contains('hidden') && !option.disabled
        );
        const selectedCount = availableOptions.filter((option) => selected.has(option.dataset.id)).length;
        selectAll.checked = availableOptions.length > 0 && selectedCount === availableOptions.length;
        selectAll.indeterminate = selectedCount > 0 && selectedCount < availableOptions.length;
        selectAll.disabled = availableOptions.length === 0;
    });
}

archiveMultiSelects.forEach((widget) => {
    widget.archiveSelected = new Set();
    const dropdown = widget.querySelector('.archive-multi-dropdown');
    const search = widget.querySelector('.archive-multi-search');
    const toggle = widget.querySelector('.archive-multi-toggle');
    const selectAll = widget.querySelector('.archive-multi-select-all-checkbox');

    widget.querySelectorAll('.archive-multi-option').forEach((option) => {
        option.addEventListener('click', () => {
            if (widget.archiveSelected.has(option.dataset.id)) {
                widget.archiveSelected.delete(option.dataset.id);
            } else if (widget.dataset.list !== 'members' ||
                !archiveRoleHasMember(widget.dataset.role, option.dataset.id)) {
                widget.archiveSelected.add(option.dataset.id);
            }
            renderArchiveMultiSelects();
        });
    });

    selectAll.addEventListener('change', () => {
        widget.querySelectorAll('.archive-multi-option:not(.hidden):not(:disabled)').forEach((option) => {
            if (selectAll.checked) {
                widget.archiveSelected.add(option.dataset.id);
            } else {
                widget.archiveSelected.delete(option.dataset.id);
            }
        });
        renderArchiveMultiSelects();
    });

    search.addEventListener('focus', () => dropdown.classList.remove('hidden'));
    search.addEventListener('input', renderArchiveMultiSelects);
    search.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') dropdown.classList.add('hidden');
        if (event.key === 'Enter') {
            event.preventDefault();
            const firstOption = Array.from(widget.querySelectorAll('.archive-multi-option:not(.hidden):not(:disabled)'))[0];
            if (firstOption) firstOption.click();
        }
    });
    toggle.addEventListener('click', () => {
        dropdown.classList.toggle('hidden');
        if (!dropdown.classList.contains('hidden')) search.focus();
    });
});

document.addEventListener('click', (event) => {
    archiveMultiSelects.forEach((widget) => {
        if (!widget.contains(event.target)) widget.querySelector('.archive-multi-dropdown').classList.add('hidden');
    });
});

const archiveAttachmentDropzone = document.getElementById('archiveAttachmentDropzone');
const archiveAttachmentInput = document.getElementById('archiveAttachmentInput');
const archiveAttachmentList = document.getElementById('archiveAttachmentList');
const archiveAttachmentError = document.getElementById('archiveAttachmentError');
const archiveAttachments = [];
const archiveAllowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'webp'];

function renderArchiveAttachments() {
    archiveAttachmentList.replaceChildren();
    archiveAttachments.forEach((file, index) => {
        const item = document.createElement('li');
        item.className = 'flex min-w-0 items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3';

        const left = document.createElement('div');
        left.className = 'flex min-w-0 items-center gap-3';
        const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        icon.setAttribute('class', 'h-8 w-8 flex-shrink-0 text-blue-600');
        icon.setAttribute('fill', 'none');
        icon.setAttribute('stroke', 'currentColor');
        icon.setAttribute('viewBox', '0 0 24 24');
        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>';
        const details = document.createElement('div');
        details.className = 'min-w-0 flex-1';
        const name = document.createElement('p');
        name.className = 'truncate text-xs font-medium text-gray-800';
        name.textContent = file.name;
        name.title = file.name;
        const metadata = document.createElement('p');
        metadata.className = 'text-xs text-gray-500';
        metadata.textContent = (file.size / 1048576).toFixed(2) + ' MB';
        details.append(name, metadata);
        left.append(icon, details);

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'ml-2 flex-shrink-0 rounded-lg p-1.5 text-red-600 transition hover:bg-red-50';
        remove.innerHTML = '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';
        remove.setAttribute('aria-label', 'Remove ' + file.name);
        remove.addEventListener('click', () => {
            archiveAttachments.splice(index, 1);
            syncArchiveAttachments();
        });

        item.append(left, remove);
        archiveAttachmentList.appendChild(item);
    });
    archiveAttachmentList.classList.toggle('hidden', archiveAttachments.length === 0);
}

function syncArchiveAttachments() {
    const transfer = new DataTransfer();
    archiveAttachments.forEach((file) => transfer.items.add(file));
    archiveAttachmentInput.files = transfer.files;
    renderArchiveAttachments();
}

function addArchiveAttachments(files) {
    const errors = [];
    Array.from(files).forEach((file) => {
        const extension = file.name.includes('.') ? file.name.split('.').pop().toLowerCase() : '';
        if (!archiveAllowedExtensions.includes(extension)) {
            errors.push(file.name + ': unsupported file type.');
            return;
        }
        if (file.size > 26214400) {
            errors.push(file.name + ': exceeds the 25 MB size limit.');
            return;
        }
        if (archiveAttachments.some((existing) => existing.name === file.name && existing.size === file.size)) return;
        if (archiveAttachments.length >= 10) {
            errors.push('You can attach at most 10 files.');
            return;
        }
        archiveAttachments.push(file);
    });

    archiveAttachmentError.textContent = errors.join(' ');
    archiveAttachmentError.classList.toggle('hidden', errors.length === 0);
    syncArchiveAttachments();
}

archiveAttachmentInput.addEventListener('change', () => {
    const pickedFiles = Array.from(archiveAttachmentInput.files);
    archiveAttachmentInput.value = '';
    addArchiveAttachments(pickedFiles);
});
['dragenter', 'dragover'].forEach((eventName) => {
    archiveAttachmentDropzone.addEventListener(eventName, (event) => {
        event.preventDefault();
        event.stopPropagation();
        if (event.dataTransfer) event.dataTransfer.dropEffect = 'copy';
        archiveAttachmentDropzone.classList.add('border-primary', 'bg-blue-50');
    });
});
archiveAttachmentDropzone.addEventListener('dragleave', (event) => {
    event.preventDefault();
    event.stopPropagation();
    if (!event.relatedTarget || !archiveAttachmentDropzone.contains(event.relatedTarget)) {
        archiveAttachmentDropzone.classList.remove('border-primary', 'bg-blue-50');
    }
});
archiveAttachmentDropzone.addEventListener('drop', (event) => {
    event.preventDefault();
    event.stopPropagation();
    archiveAttachmentDropzone.classList.remove('border-primary', 'bg-blue-50');
    if (event.dataTransfer && event.dataTransfer.files.length) addArchiveAttachments(event.dataTransfer.files);
});

const archiveCreateForm = document.querySelector('#createModal form[action$="/store"]');
if (archiveCreateForm) {
    archiveCreateForm.addEventListener('submit', () => {
        const postFields = archiveCreateForm.querySelector('[data-archive-post]');
        postFields.replaceChildren();

        const addHidden = (name, value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            postFields.appendChild(input);
        };

        archiveMultiSelects.forEach((widget) => {
            widget.archiveSelected.forEach((id) => {
                if (widget.dataset.list === 'members') {
                    addHidden('author_role[]', widget.dataset.role);
                    addHidden('author_sp_member_id[]', id);
                    addHidden('author_custom_name[]', '');
                } else {
                    addHidden('committee_id[]', id);
                    addHidden('committee_custom_name[]', '');
                }
            });
        });

        archiveCreateForm.querySelectorAll('.custom-author-entry').forEach((entry) => {
            const name = entry.querySelector('.custom-author-name').value.trim();
            if (!name) return;
            addHidden('author_role[]', entry.querySelector('.custom-author-role').value);
            addHidden('author_sp_member_id[]', '');
            addHidden('author_custom_name[]', name);
        });

        archiveCreateForm.querySelectorAll('.custom-committee-entry').forEach((entry) => {
            const name = entry.querySelector('.custom-committee-name').value.trim();
            if (!name) return;
            addHidden('committee_id[]', '');
            addHidden('committee_custom_name[]', name);
        });
    });
}

// Close modal when clicking backdrop
['createModal', 'deleteModal'].forEach(function(id) {
    const modal = document.getElementById(id);
    if (modal) {
        document.getElementById(id + 'Backdrop').addEventListener('click', function() {
            closeModal(id);
        });
    }
});

// ── Mutually exclusive doc type selectors ────────────────────────────────────
function handleDocTypeChange(prefix) {
    const select = document.getElementById(prefix + '_doc_type_id');
    const custom = document.getElementById(prefix + '_custom_type');
    if (select && custom && select.value !== '') {
        custom.value = '';
        custom.disabled = true;
    } else if (custom) {
        custom.disabled = false;
    }
    updateDocTypeRemoveButtons(prefix);
}
function handleCustomTypeChange(prefix) {
    const select = document.getElementById(prefix + '_doc_type_id');
    const custom = document.getElementById(prefix + '_custom_type');
    if (custom && custom.value.trim() !== '') {
        if (select) { select.value = ''; select.disabled = true; }
    } else if (select) {
        select.disabled = false;
    }
    updateDocTypeRemoveButtons(prefix);
}
function removeDocTypeSelection(prefix, source) {
    const select = document.getElementById(prefix + '_doc_type_id');
    const custom = document.getElementById(prefix + '_custom_type');
    if (source === 'dropdown' && select) {
        select.value = '';
        select.disabled = false;
        if (custom) custom.disabled = false;
    } else if (source === 'custom' && custom) {
        custom.value = '';
        custom.disabled = false;
        if (select) select.disabled = false;
    }
    updateDocTypeRemoveButtons(prefix);
}
function updateDocTypeRemoveButtons(prefix) {
    const select = document.getElementById(prefix + '_doc_type_id');
    const custom = document.getElementById(prefix + '_custom_type');
    const dropdownRemove = document.getElementById(prefix + '_doc_type_remove');
    const customRemove = document.getElementById(prefix + '_custom_type_remove');
    if (dropdownRemove) dropdownRemove.classList.toggle('hidden', !select || select.value === '');
    if (customRemove) customRemove.classList.toggle('hidden', !custom || custom.value.trim() === '');
}
</script>

<?php
/**
 * Plenary Second Reading — queue list with filter tabs
 *
 * Variables supplied by PlenaryReadingController::secondReadingIndex():
 *   $documents      array
 *   $page           int
 *   $totalPages     int
 *   $total          int
 *   $search         string
 *   $documentTypeId int
 *   $documentTypes  array
 *   $currentFilter  string  'pending'|'deferred'|'withdrawn'|'approved'
 *   $tabCounts      array   ['pending'=>int, 'deferred'=>int, 'withdrawn'=>int, 'approved'=>int]
 *   $success        string|null
 *   $error          string|null
 *   $errors         array
 */

$documents     = $documents     ?? [];
$page          = $page          ?? 1;
$totalPages    = $totalPages    ?? 1;
$total         = $total         ?? 0;
$search        = $search        ?? '';
$documentTypeId = (int) ($documentTypeId ?? 0);
$documentTypes = $documentTypes ?? [];
$currentFilter = $currentFilter ?? 'pending';
$tabCounts     = $tabCounts     ?? [];
$errors        = $errors        ?? [];

$tabDefs = [
    'pending'   => ['label' => 'Awaiting',  'description' => 'Ready for Second Reading.'],
    'deferred'  => ['label' => 'Deferred',  'description' => 'Waiting for required updates.'],
    'withdrawn' => ['label' => 'Withdrawn', 'description' => 'Withdrawn during Second Reading.'],
    'approved'  => ['label' => 'Approved',  'description' => 'Approved and finalized.'],
];

$filterTitles = [
    'pending'   => ['title' => 'Awaiting Second Reading',       'sub' => 'Documents ready for Second Reading processing.'],
    'deferred'  => ['title' => 'Deferred Documents',             'sub' => 'Documents deferred at Second Reading, pending compliance updates.'],
    'withdrawn' => ['title' => 'Withdrawn Documents',            'sub' => 'Documents withdrawn during Second Reading.'],
    'approved'  => ['title' => 'Approved on Second Reading',     'sub' => 'Documents approved and finalized.'],
];
$currentFilter = isset($tabDefs[$currentFilter]) ? $currentFilter : 'pending';
$activeTitle = $filterTitles[$currentFilter];
$clearFiltersUrl = BASE_URL . '/plenary/second-reading?' . http_build_query(['filter' => $currentFilter]);
$paginationBaseQuery = ['filter' => $currentFilter];
if ($search !== '') {
    $paginationBaseQuery['search'] = $search;
}
if ($documentTypeId > 0) {
    $paginationBaseQuery['doc_type'] = $documentTypeId;
}

ob_start();
?>

<div class="space-y-6">

    <?php if (!empty($success ?? null)): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status" aria-live="polite"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($error ?? null)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Header -->
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative z-10 flex flex-col gap-6 px-6 py-7 sm:px-8 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-blue-100">Plenary · Legislative Readings</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-white sm:text-3xl">Second Reading</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-blue-100"><?= htmlspecialchars($activeTitle['sub']) ?></p>
            </div>
            <div class="flex items-center gap-3 border-t border-white/15 pt-4 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
                <?php $headerAccent = ['pending' => 'bg-amber-300 ring-amber-300/20', 'deferred' => 'bg-orange-300 ring-orange-300/20', 'withdrawn' => 'bg-gray-300 ring-gray-300/20', 'approved' => 'bg-emerald-300 ring-emerald-300/20'][$currentFilter]; ?>
                <span class="flex h-2.5 w-2.5 rounded-full ring-4 <?= $headerAccent ?>"></span>
                <div>
                    <p class="text-xs font-medium text-white"><?= htmlspecialchars($tabDefs[$currentFilter]['label']) ?> queue</p>
                    <p class="mt-0.5 text-xs text-blue-100/80"><?= htmlspecialchars(date('l, F j, Y')) ?></p>
                </div>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
        </div>
    </section>

    <!-- At a glance -->
    <section>
        <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-primary">At a glance</p>
                <h2 class="mt-1 text-xl font-semibold text-gray-900">Second Reading activity</h2>
            </div>
            <p class="text-xs text-gray-500">Documents by their current reading outcome</p>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <?php foreach ($tabDefs as $key => $def): ?>
                <?php
                $cardColors = [
                    'pending' => ['bar' => 'bg-amber-500', 'icon' => 'bg-amber-50 text-amber-600'],
                    'deferred' => ['bar' => 'bg-orange-500', 'icon' => 'bg-orange-50 text-orange-600'],
                    'withdrawn' => ['bar' => 'bg-gray-400', 'icon' => 'bg-gray-100 text-gray-600'],
                    'approved' => ['bar' => 'bg-emerald-500', 'icon' => 'bg-emerald-50 text-emerald-600'],
                ][$key];
                ?>
                <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <span class="absolute inset-x-0 top-0 h-1 <?= $cardColors['bar'] ?>"></span>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm text-gray-600"><?= htmlspecialchars($def['label']) ?></p>
                            <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format((int) ($tabCounts[$key] ?? 0)) ?></p>
                        </div>
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl <?= $cardColors['icon'] ?>">
                            <?php if ($key === 'pending'): ?>
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <?php elseif ($key === 'deferred'): ?>
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4m0 4h.01M10.3 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.7 3.86a2 2 0 00-3.4 0z"/></svg>
                            <?php elseif ($key === 'withdrawn'): ?>
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                            <?php else: ?>
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <?php endif; ?>
                        </div>
                    </div>
                    <p class="mt-3 text-xs leading-5 text-gray-500"><?= htmlspecialchars($def['description']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Tabs -->
    <section>
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex gap-2 overflow-x-auto" aria-label="Second Reading status">
                <?php foreach ($tabDefs as $key => $def): ?>
                    <?php $count = (int) ($tabCounts[$key] ?? 0); ?>
                    <?php $tabQuery = ['filter' => $key]; if ($search !== '') { $tabQuery['search'] = $search; } if ($documentTypeId > 0) { $tabQuery['doc_type'] = $documentTypeId; } ?>
                    <a href="<?= BASE_URL ?>/plenary/second-reading?<?= htmlspecialchars(http_build_query($tabQuery), ENT_QUOTES, 'UTF-8') ?>"
                       <?= $currentFilter === $key ? 'aria-current="page"' : '' ?>
                       class="<?= $currentFilter === $key ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' ?> inline-flex items-center gap-2 whitespace-nowrap border-b-2 px-3 py-3 text-sm font-medium transition">
                        <?= htmlspecialchars($def['label']) ?>
                        <span class="inline-flex min-w-6 items-center justify-center rounded-full px-2 py-0.5 text-xs font-semibold <?= $currentFilter === $key ? 'bg-primary/10 text-primary' : 'bg-gray-100 text-gray-600' ?>">
                            <?= number_format($count) ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
    </section>

    <!-- Document List -->
    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-gray-100 px-5 py-5 xl:flex-row xl:items-center xl:justify-between sm:px-6">
            <div class="min-w-0">
                <h2 class="font-semibold text-gray-900"><?= htmlspecialchars($activeTitle['title']) ?></h2>
                <p class="mt-1 text-xs text-gray-500"><?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?> found</p>
            </div>
            <form method="GET" action="<?= BASE_URL ?>/plenary/second-reading"
                  class="hidden min-w-0 flex-wrap items-center justify-end gap-2 xl:flex xl:flex-1">
                <input type="hidden" name="filter" value="<?= htmlspecialchars($currentFilter, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="page" value="1">
                <div class="relative min-w-0 xl:w-72">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="search" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search tracking # or subject..."
                           class="block w-full min-w-0 rounded-xl border border-gray-200 bg-white py-2.5 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </div>
                <label for="second-reading-desktop-doc-type" class="sr-only">Filter by document type</label>
                <select id="second-reading-desktop-doc-type" name="doc_type" class="w-52 max-w-full shrink-0 rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="0">All document types</option>
                    <?php foreach ($documentTypes as $documentType): ?>
                        <option value="<?= (int) $documentType['id'] ?>" <?= $documentTypeId === (int) $documentType['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($documentType['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="shrink-0 rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Filter</button>
                <?php if ($search !== '' || $documentTypeId > 0): ?>
                    <a href="<?= htmlspecialchars($clearFiltersUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Clear</a>
                <?php endif; ?>
            </form>
            <div class="flex min-w-0 items-center gap-2 xl:hidden">
                <form method="GET" action="<?= BASE_URL ?>/plenary/second-reading" class="relative min-w-0 flex-1">
                    <input type="hidden" name="filter" value="<?= htmlspecialchars($currentFilter, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="page" value="1">
                    <input type="hidden" name="doc_type" value="<?= $documentTypeId ?>">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="search" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search tracking # or subject..."
                           class="block w-full min-w-0 rounded-xl border border-gray-200 bg-white py-2.5 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </form>
                <button type="button" data-inbox-filter-target="#second-reading-filter-sheet" aria-label="Open filters" aria-haspopup="dialog"
                        class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 hover:bg-gray-50">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M6 12h12m-9 8h6"/></svg>
                    <?php if ($currentFilter !== 'pending' || $search !== '' || $documentTypeId > 0): ?><span class="absolute right-1 top-1 h-2 w-2 rounded-full bg-primary"></span><?php endif; ?>
                </button>
            </div>
        </div>

        <div id="second-reading-filter-sheet" class="fixed inset-0 z-50 hidden xl:hidden">
            <button type="button" class="inbox-filter-backdrop absolute inset-0 bg-gray-950/40" aria-label="Close filters"></button>
            <form method="GET" action="<?= BASE_URL ?>/plenary/second-reading" role="dialog" aria-modal="true" aria-labelledby="second-reading-filter-title"
                  class="absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto rounded-t-2xl bg-white p-5 shadow-2xl">
                <input type="hidden" name="page" value="1">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                <div class="mb-5 flex items-center justify-between">
                    <h3 id="second-reading-filter-title" class="font-semibold text-gray-900">Filters</h3>
                    <button type="button" class="inbox-filter-close rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-100">Close</button>
                </div>
                <label for="second-reading-mobile-doc-type" class="mb-1.5 block text-sm font-medium text-gray-700">Document type</label>
                <select id="second-reading-mobile-doc-type" name="doc_type" class="mb-5 block w-full min-w-0 rounded-xl border border-gray-200 bg-white px-3 py-3 text-sm text-gray-800">
                    <option value="0">All document types</option>
                    <?php foreach ($documentTypes as $documentType): ?>
                        <option value="<?= (int) $documentType['id'] ?>" <?= $documentTypeId === (int) $documentType['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($documentType['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="grid <?= $search !== '' || $documentTypeId > 0 ? 'grid-cols-2' : 'grid-cols-1' ?> gap-3">
                    <button type="submit" class="w-full rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-white hover:opacity-90">Apply Filters</button>
                    <?php if ($search !== '' || $documentTypeId > 0): ?>
                        <a href="<?= htmlspecialchars($clearFiltersUrl, ENT_QUOTES, 'UTF-8') ?>" class="flex w-full items-center justify-center rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50">Clear filters</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <?php require __DIR__ . '/../../partials/inbox-mobile-filter-script.php'; ?>

        <?php $columnCount = 4 + ($currentFilter === 'approved' ? 2 : (in_array($currentFilter, ['deferred', 'withdrawn'], true) ? 1 : 0)); ?>
        <div class="hidden overflow-x-auto xl:block">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="border-b border-gray-100 bg-gray-50/70 text-[11px] uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-5 py-3 font-bold">Tracking Number / Proposed No.</th>
                        <th class="px-5 py-3 font-bold">Subject Matter / Type</th>
                        <th class="px-5 py-3 font-bold">Committees</th>
                        <?php if ($currentFilter === 'approved'): ?>
                            <th class="px-5 py-3 font-bold">Approved No.</th>
                            <th class="px-5 py-3 font-bold">Date Approved</th>
                        <?php elseif ($currentFilter === 'deferred'): ?>
                            <th class="px-5 py-3 font-bold">Date Deferred</th>
                        <?php elseif ($currentFilter === 'withdrawn'): ?>
                            <th class="px-5 py-3 font-bold">Date Withdrawn</th>
                        <?php endif; ?>
                        <th class="px-5 py-3 text-right font-bold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($documents)): ?>
                        <tr>
                            <td colspan="<?= $columnCount ?>" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400" aria-hidden="true">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">No documents found.</p>
                                <p class="mt-1 text-xs text-gray-500"><?= $search !== '' ? 'Try another search term or clear your search.' : 'There are no documents in this category yet.' ?></p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($documents as $row): ?>
                            <?php $committeeNames = array_values(array_filter(array_map('trim', explode(',', (string) ($row['committee_names'] ?? ''))))); ?>
                            <tr class="transition hover:bg-gray-50/60">
                                <td class="px-5 py-4">
                                    <div class="font-semibold text-gray-900"><?= htmlspecialchars($row['tracking_number']) ?></div>
                                    <div class="mt-1 text-xs text-gray-500">
                                        <?php if (!empty($row['proposed_number'])): ?>
                                            <?= htmlspecialchars($row['proposed_number']) ?> ·
                                        <?php endif; ?>
                                        <?= htmlspecialchars(date('M j, Y', strtotime($row['date_received']))) ?>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="max-w-md">
                                        <p class="whitespace-pre-wrap break-words font-medium leading-5 text-gray-900"><?= htmlspecialchars($row['subject_matter']) ?></p>
                                        <?php if (!empty($row['document_type_name'])): ?>
                                            <span class="mt-1.5 inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                                  style="background-color: <?= htmlspecialchars($row['document_type_badge_color'] ?? '#2563EB') ?>14; color: <?= htmlspecialchars($row['document_type_badge_color'] ?? '#2563EB') ?>;">
                                                <?= htmlspecialchars($row['document_type_name']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <?php if (!empty($committeeNames)): ?>
                                        <div class="text-sm font-medium text-gray-800" title="<?= htmlspecialchars(implode(', ', $committeeNames), ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($committeeNames[0]) ?>
                                            <?php if (count($committeeNames) > 1): ?>
                                                <span class="ml-1 text-xs font-medium text-gray-500">+<?= count($committeeNames) - 1 ?> others</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs italic text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($currentFilter === 'approved'): ?>
                                    <td class="px-5 py-4 font-medium text-gray-800"><?= !empty($row['approved_no']) ? htmlspecialchars($row['approved_no']) : '<span class="text-xs text-gray-400">—</span>' ?></td>
                                    <td class="px-5 py-4 text-sm text-gray-600"><?= !empty($row['date_finalized']) ? htmlspecialchars(date('M j, Y', strtotime($row['date_finalized']))) : '—' ?></td>
                                <?php elseif ($currentFilter === 'deferred'): ?>
                                    <td class="px-5 py-4 text-sm text-gray-600"><?= !empty($row['date_deferred']) ? htmlspecialchars(date('M j, Y', strtotime($row['date_deferred']))) : '—' ?></td>
                                <?php elseif ($currentFilter === 'withdrawn'): ?>
                                    <td class="px-5 py-4 text-sm text-gray-600"><?= !empty($row['date_withdrawn']) ? htmlspecialchars(date('M j, Y', strtotime($row['date_withdrawn']))) : '—' ?></td>
                                <?php endif; ?>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    <?php $actionLabel = in_array($currentFilter, ['pending', 'deferred'], true) ? 'Process' : 'View'; ?>
                                    <a href="<?= BASE_URL ?>/plenary/second-reading/show?id=<?= (int) $row['document_id'] ?>"
                                       class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:border-primary hover:bg-blue-50 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                       title="<?= $actionLabel ?> document" aria-label="<?= $actionLabel ?> <?= htmlspecialchars($row['tracking_number'], ENT_QUOTES, 'UTF-8') ?>">
                                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <?php if ($actionLabel === 'Process'): ?><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/><?php else: ?><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><?php endif; ?>
                                        </svg>
                                        <span><?= $actionLabel ?></span>
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
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 text-gray-400" aria-hidden="true">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <p class="mt-3 text-sm font-medium text-gray-700">No documents found.</p>
                    <p class="mt-1 text-xs text-gray-500"><?= $search !== '' ? 'Try another search term or clear your search.' : 'There are no documents in this category yet.' ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($documents as $row): ?>
                    <?php
                    $actionLabel = in_array($currentFilter, ['pending', 'deferred'], true) ? 'Process' : 'View';
                    $committeeNames = array_values(array_filter(array_map('trim', explode(',', (string) ($row['committee_names'] ?? '')))));
                    $statusBadge = [
                        'pending' => 'bg-amber-50 text-amber-700',
                        'deferred' => 'bg-orange-50 text-orange-700',
                        'withdrawn' => 'bg-gray-100 text-gray-700',
                        'approved' => 'bg-emerald-50 text-emerald-700',
                    ][$currentFilter];
                    $statusDot = [
                        'pending' => 'bg-amber-500',
                        'deferred' => 'bg-orange-500',
                        'withdrawn' => 'bg-gray-500',
                        'approved' => 'bg-emerald-500',
                    ][$currentFilter];
                    ?>
                    <article class="px-5 py-5">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="break-words font-semibold text-gray-900"><?= htmlspecialchars($row['tracking_number']) ?></p>
                                <p class="mt-1 text-xs text-gray-500">
                                    <?php if (!empty($row['proposed_number'])): ?>
                                        <?= htmlspecialchars($row['proposed_number']) ?> ·
                                    <?php endif; ?>
                                    Received <?= htmlspecialchars(date('M j, Y', strtotime($row['date_received']))) ?>
                                </p>
                            </div>
                            <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold <?= $statusBadge ?>">
                                <span class="h-1.5 w-1.5 rounded-full <?= $statusDot ?>"></span>
                                <?= htmlspecialchars($row['status'] ?? $tabDefs[$currentFilter]['label']) ?>
                            </span>
                        </div>
                        <p class="mt-3 whitespace-pre-wrap break-words text-sm font-medium leading-5 text-gray-900"><?= htmlspecialchars($row['subject_matter']) ?></p>
                        <?php if (!empty($row['document_type_name'])): ?>
                            <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                  style="background-color: <?= htmlspecialchars($row['document_type_badge_color'] ?? '#2563EB') ?>14; color: <?= htmlspecialchars($row['document_type_badge_color'] ?? '#2563EB') ?>;">
                                <?= htmlspecialchars($row['document_type_name']) ?>
                            </span>
                        <?php endif; ?>
                        <div class="mt-4 grid grid-cols-1 gap-3 border-t border-gray-100 pt-3 sm:grid-cols-2">
                            <div>
                                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Committee(s)</p>
                                <?php if (!empty($committeeNames)): ?>
                                    <p class="mt-1 break-words text-sm font-medium text-gray-800" title="<?= htmlspecialchars(implode(', ', $committeeNames), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($committeeNames[0]) ?>
                                        <?php if (count($committeeNames) > 1): ?>
                                            <span class="ml-1 text-xs font-medium text-gray-500">+<?= count($committeeNames) - 1 ?> others</span>
                                        <?php endif; ?>
                                    </p>
                                <?php else: ?>
                                    <p class="mt-1 text-sm text-gray-500">—</p>
                                <?php endif; ?>
                            </div>
                            <?php if ($currentFilter === 'approved'): ?>
                                <div>
                                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Approved No.</p>
                                    <p class="mt-1 break-words text-sm font-medium text-gray-700"><?= !empty($row['approved_no']) ? htmlspecialchars($row['approved_no']) : '—' ?></p>
                                </div>
                                <div>
                                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Date Approved</p>
                                    <p class="mt-1 text-sm text-gray-700"><?= !empty($row['date_finalized']) ? htmlspecialchars(date('M j, Y', strtotime($row['date_finalized']))) : '—' ?></p>
                                </div>
                            <?php elseif ($currentFilter === 'deferred'): ?>
                                <div>
                                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Date Deferred</p>
                                    <p class="mt-1 text-sm text-gray-700"><?= !empty($row['date_deferred']) ? htmlspecialchars(date('M j, Y', strtotime($row['date_deferred']))) : '—' ?></p>
                                </div>
                            <?php elseif ($currentFilter === 'withdrawn'): ?>
                                <div>
                                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Date Withdrawn</p>
                                    <p class="mt-1 text-sm text-gray-700"><?= !empty($row['date_withdrawn']) ? htmlspecialchars(date('M j, Y', strtotime($row['date_withdrawn']))) : '—' ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="mt-4 flex justify-end border-t border-gray-100 pt-3">
                            <a href="<?= BASE_URL ?>/plenary/second-reading/show?id=<?= (int) $row['document_id'] ?>"
                               class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:border-primary hover:bg-blue-50 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                               title="<?= $actionLabel ?> document" aria-label="<?= $actionLabel ?> <?= htmlspecialchars($row['tracking_number'], ENT_QUOTES, 'UTF-8') ?>">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <?php if ($actionLabel === 'Process'): ?><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/><?php else: ?><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><?php endif; ?>
                                </svg>
                                <span><?= $actionLabel ?></span>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="flex flex-col items-center justify-between gap-3 border-t border-gray-100 px-5 py-4 sm:flex-row md:px-6">
            <p class="text-xs text-gray-500">
                Showing page <span class="font-medium text-gray-700"><?= $page ?></span> of
                <span class="font-medium text-gray-700"><?= $totalPages ?></span>
                (<?= number_format($total) ?> total records)
            </p>
            <nav class="flex flex-wrap items-center justify-center gap-1" aria-label="Second Reading pagination">
                <?php if ($page > 1): ?>
                    <a href="<?= BASE_URL ?>/plenary/second-reading?<?= htmlspecialchars(http_build_query(array_merge($paginationBaseQuery, ['page' => $page - 1])), ENT_QUOTES, 'UTF-8') ?>"
                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Prev</a>
                <?php endif; ?>
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                    <a href="<?= BASE_URL ?>/plenary/second-reading?<?= htmlspecialchars(http_build_query(array_merge($paginationBaseQuery, ['page' => $i])), ENT_QUOTES, 'UTF-8') ?>"
                       <?= $i === $page ? 'aria-current="page"' : '' ?>
                       class="rounded-lg border px-3 py-1.5 text-sm font-medium transition <?= $i === $page ? 'border-primary bg-primary text-white' : 'border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= BASE_URL ?>/plenary/second-reading?<?= htmlspecialchars(http_build_query(array_merge($paginationBaseQuery, ['page' => $page + 1])), ENT_QUOTES, 'UTF-8') ?>"
                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Next</a>
                <?php endif; ?>
            </nav>
        </div>
    </section>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

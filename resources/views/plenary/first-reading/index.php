<?php
/**
 * Plenary First Reading — queue list (with document-type tabs)
 *
 * Variables supplied by PlenaryReadingController::commFirstReadingIndex():
 *   $documents          array
 *   $page               int
 *   $totalPages         int
 *   $total              int
 *   $search             string
 *   $tab                string  active document-type tab ('petitions' is a legacy URL alias for 'cases')
 *   $tabCounts          array   totals for each document-type tab
 *   $success            string|null
 *   $error              string|null
 *   $errors             array
 */

$documents         = $documents         ?? [];
$page              = $page              ?? 1;
$totalPages        = $totalPages        ?? 1;
$total             = $total             ?? 0;
$search            = $search            ?? '';
$sessionType       = $sessionType       ?? '';
$tab               = $tab               ?? 'all';
$tabCounts         = $tabCounts         ?? [
    'all' => 0,
    'ordinance' => 0,
    'resolution' => 0,
    'municipal_ordinances' => 0,
    'municipal_resolutions' => 0,
    'cases' => 0,
    'others' => 0,
    'communication' => 0,
];
$errors            = $errors            ?? [];

ob_start();

function frTabUrl(string $targetTab, string $search = '', string $sessionType = ''): string {
    $qs = 'tab=' . $targetTab;
    if ($search !== '') { $qs .= '&search=' . urlencode($search); }
    if ($sessionType !== '') { $qs .= '&session_type=' . urlencode($sessionType); }
    return BASE_URL . '/plenary/first-reading?' . $qs;
}

function frPageUrl(string $tab, int $pageNum, string $search, string $sessionType = ''): string {
    $qs = 'tab=' . $tab . '&page=' . $pageNum;
    if ($search !== '') { $qs .= '&search=' . urlencode($search); }
    if ($sessionType !== '') { $qs .= '&session_type=' . urlencode($sessionType); }
    return BASE_URL . '/plenary/first-reading?' . $qs;
}

function frIsComm(array $row): bool {
    return stripos($row['document_type_name'] ?? '', 'communication') !== false;
}

function frIsCases(array $row): bool {
    $type = strtolower($row['document_type_name'] ?? '');
    return strpos($type, 'administrative case') !== false
        || strpos($type, 'complaint') !== false;
}

function frSessionLabel(string $type): string {
    return match ($type) {
        'REGULAR'        => 'Regular',
        'REGULAR_ONLINE' => 'Regular (Online)',
        'SPECIAL'        => 'Special',
        'SPECIAL_ONLINE' => 'Special (Online)',
        default          => $type ?: '—',
    };
}
?>

<div class="space-y-6">

    <?php if (!empty($success ?? null)): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($error ?? null)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc list-inside space-y-1"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative z-10 flex flex-col gap-6 px-6 py-7 sm:px-8 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-blue-100">PLENARY / LEGISLATIVE READINGS</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-white sm:text-3xl">First Reading</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-blue-100">Documents awaiting their First Reading session.</p>
            </div>
            <div class="flex items-center gap-3 border-t border-white/15 pt-4 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
                <span class="flex h-2.5 w-2.5 rounded-full bg-amber-300 ring-4 ring-amber-300/20"></span>
                <div>
                    <p class="text-xs font-medium text-white">First Reading queue</p>
                    <p class="mt-0.5 text-xs text-blue-100/80"><?= htmlspecialchars(date('l, F j, Y')) ?></p>
                </div>
            </div>
        </div>
        <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
    </section>

    <!-- Stats -->
    <section>
        <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-primary">At a glance</p>
                <h2 class="mt-1 text-xl font-semibold text-gray-900">Reading queue activity</h2>
            </div>
            <p class="text-xs text-gray-500">Awaiting documents and related referrals</p>
        </div>
        <?php
        $legislativeQueueCount = $tabCounts['ordinance']
            + $tabCounts['resolution']
            + $tabCounts['municipal_ordinances']
            + $tabCounts['municipal_resolutions'];
        ?>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-x-0 top-0 h-1 bg-amber-500"></span>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Awaiting First Reading</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($tabCounts['all']) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-x-0 top-0 h-1 bg-blue-500"></span>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Communications in Queue</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($tabCounts['communication']) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-x-0 top-0 h-1 bg-violet-500"></span>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm text-gray-500">Petitions in Queue</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($tabCounts['cases']) ?></p>
                    </div>
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7h8M8 11h8m-8 4h5m-8 5h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-x-0 top-0 h-1 bg-emerald-500"></span>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm text-gray-500">Ordinances &amp; Resolutions</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($legislativeQueueCount) ?></p>
                    </div>
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 3h7l5 5v13H5V3h2zm6 1v5h5M8 13h8m-8 4h8"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Tabs -->
    <section>
        <div class="border-b border-gray-200">
            <nav class="-mb-px hidden flex-nowrap gap-6 overflow-x-auto overscroll-x-contain xl:flex" aria-label="First reading tabs">
                <?php
                $tabs = [
                    'all'                   => ['label' => 'All', 'count' => $tabCounts['all']],
                    'ordinance'             => ['label' => 'Provincial Ordinances', 'count' => $tabCounts['ordinance']],
                    'resolution'            => ['label' => 'Provincial Resolutions', 'count' => $tabCounts['resolution']],
                    'municipal_ordinances'  => ['label' => 'Ordinances', 'count' => $tabCounts['municipal_ordinances']],
                    'municipal_resolutions' => ['label' => 'Resolutions', 'count' => $tabCounts['municipal_resolutions']],
                    'cases'                 => ['label' => 'Cases', 'count' => $tabCounts['cases']],
                    'communication'         => ['label' => 'Communications', 'count' => $tabCounts['communication']],
                    'others'                => ['label' => 'Others', 'count' => $tabCounts['others']],
                ];
                foreach ($tabs as $tabKey => $tabInfo):
                    $isActive = ($tab === $tabKey);
                ?>
                <a href="<?= frTabUrl($tabKey, $search, $sessionType) ?>"
                   class="<?= $isActive ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' ?>
                          shrink-0 whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition">
                    <?= htmlspecialchars($tabInfo['label']) ?>
                    <span class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold
                                 <?= $isActive ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700' ?>">
                        <?= number_format($tabInfo['count']) ?>
                    </span>
                </a>
                <?php endforeach; ?>
            </nav>
        </div>
    </section>

    <!-- Document List -->
    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">

        <!-- Search + filter bar -->
        <div class="flex min-w-0 flex-col gap-4 border-b border-gray-100 px-5 py-5 md:px-6 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900">First Reading Queue</h2>
                <p class="mt-1 text-xs text-gray-500"><?= $total ?> record<?= $total !== 1 ? 's' : '' ?> found</p>
            </div>

            <form method="GET" action="<?= BASE_URL ?>/plenary/first-reading" class="hidden w-full min-w-0 items-center justify-end gap-2 xl:flex xl:flex-1">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                <div class="relative w-full min-w-0 flex-none" style="max-width: 320px;">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search tracking # or subject..."
                           class="block w-full min-w-0 rounded-lg border border-gray-200 bg-white py-2.5 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </div>
                <select name="session_type" aria-label="Filter by session type"
                        class="shrink-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">All Session Types</option>
                    <?php foreach (['REGULAR', 'REGULAR_ONLINE', 'SPECIAL', 'SPECIAL_ONLINE'] as $sessionTypeOption): ?>
                        <option value="<?= htmlspecialchars($sessionTypeOption) ?>" <?= $sessionType === $sessionTypeOption ? 'selected' : '' ?>>
                            <?= htmlspecialchars(frSessionLabel($sessionTypeOption)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="shrink-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Filter</button>
                <?php if ($search !== '' || $sessionType !== ''): ?>
                    <a href="<?= frTabUrl($tab) ?>" class="shrink-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition text-center">Clear</a>
                <?php endif; ?>
            </form>

            <div class="flex min-w-0 items-center gap-2 xl:hidden">
                <form method="GET" action="<?= BASE_URL ?>/plenary/first-reading" class="relative min-w-0 flex-1">
                    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                    <input type="hidden" name="session_type" value="<?= htmlspecialchars($sessionType) ?>">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search tracking # or subject..."
                           class="block w-full min-w-0 rounded-lg border border-gray-200 py-2.5 pl-9 pr-3 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </form>
                <button type="button" data-inbox-filter-target="#first-reading-filter-sheet" aria-label="Open document filters"
                        class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M6 12h12m-9 8h6"/></svg>
                    <?php if ($tab !== 'all' || $sessionType !== ''): ?><span class="absolute right-1 top-1 h-2 w-2 rounded-full bg-primary"></span><?php endif; ?>
                </button>
            </div>
        </div>

        <div id="first-reading-filter-sheet" class="fixed inset-0 z-50 hidden xl:hidden">
            <button type="button" class="inbox-filter-backdrop absolute inset-0 bg-gray-950/40" aria-label="Close filters"></button>
            <form method="GET" action="<?= BASE_URL ?>/plenary/first-reading" class="absolute inset-x-0 bottom-0 rounded-t-2xl bg-white p-5 shadow-2xl">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                <div class="mb-4 flex items-center justify-between"><h3 class="font-semibold text-gray-900">Document Filter</h3><button type="button" class="inbox-filter-close text-sm text-gray-500">Close</button></div>
                <select name="tab" aria-label="Filter by document category" class="mb-3 w-full rounded-lg border border-gray-200 px-3 py-3 text-sm">
                    <option value="all" <?= $tab === 'all' ? 'selected' : '' ?>>All</option>
                    <?php foreach ($tabs as $tabKey => $tabInfo): ?>
                        <?php if ($tabKey === 'all') { continue; } ?>
                        <option value="<?= htmlspecialchars($tabKey) ?>" <?= $tab === $tabKey ? 'selected' : '' ?>><?= htmlspecialchars($tabInfo['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="session_type" aria-label="Filter by session type" class="mb-3 w-full rounded-lg border border-gray-200 px-3 py-3 text-sm">
                    <option value="">All Session Types</option>
                    <?php foreach (['REGULAR', 'REGULAR_ONLINE', 'SPECIAL', 'SPECIAL_ONLINE'] as $sessionTypeOption): ?>
                        <option value="<?= htmlspecialchars($sessionTypeOption) ?>" <?= $sessionType === $sessionTypeOption ? 'selected' : '' ?>>
                            <?= htmlspecialchars(frSessionLabel($sessionTypeOption)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="w-full rounded-lg bg-primary px-4 py-3 text-sm font-semibold text-white">Apply Filter</button>
                <?php if ($search !== '' || $tab !== 'all' || $sessionType !== ''): ?><a href="<?= BASE_URL ?>/plenary/first-reading" class="mt-2 block w-full rounded-lg border border-gray-200 px-4 py-3 text-center text-sm font-medium text-gray-700">Clear filters</a><?php endif; ?>
            </form>
        </div>

        <div class="hidden overflow-x-auto xl:block">
            <?php require __DIR__ . '/../../partials/inbox-mobile-filter-script.php'; ?>
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-100 bg-gray-50/70 text-[11px] uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-bold">Tracking Number</th>
                        <th class="px-6 py-3 font-bold">Subject Matter / Document Type</th>
                        <?php if ($tab !== 'communication'): ?>
                        <th class="px-6 py-3 font-bold">Proposed No.</th>
                        <th class="px-6 py-3 font-bold">Session Type</th>
                        <?php endif; ?>
                        <th class="px-6 py-3 font-bold">Target Date</th>
                        <th class="w-36 px-3 py-3 text-right font-bold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($documents)): ?>
                        <tr>
                            <td colspan="<?= ($tab !== 'communication') ? 6 : 4 ?>"
                                class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">No documents in this category.</p>
                                <p class="mt-1 text-xs text-gray-500">Documents will appear here once their Plenary processing details are saved.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($documents as $row):
                            $isComm   = frIsComm($row);
                            $isCases  = frIsCases($row);
                            if ($isComm) {
                                $processRoute = 'plenary/comm-first-reading/show?id='   . (int) $row['document_id'];
                            } elseif ($isCases) {
                                $processRoute = 'plenary/cases-first-reading/show?id='  . (int) $row['document_id'];
                            } else {
                                $processRoute = 'plenary/first-reading/show?id='        . (int) $row['document_id'];
                            }
                        ?>
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($row['tracking_number']) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars(date('M j, Y', strtotime($row['date_received']))) ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="max-w-xs">
                                        <p class="line-clamp-2 font-medium text-gray-900"><?= htmlspecialchars($row['subject_matter']) ?></p>
                                        <?php if (!empty($row['document_type_name'])): ?>
                                            <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                                  style="background-color: <?= htmlspecialchars($row['document_type_badge_color'] ?? '#2563EB') ?>1a;
                                                         color: <?= htmlspecialchars($row['document_type_badge_color'] ?? '#2563EB') ?>;">
                                                <?= htmlspecialchars($row['document_type_name']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <?php if ($tab !== 'communication'): ?>
                                <td class="px-6 py-4">
                                    <?= !empty($row['proposed_number'])
                                        ? '<span class="font-medium text-gray-800">' . htmlspecialchars($row['proposed_number']) . '</span>'
                                        : '<span class="text-xs italic text-gray-400">—</span>' ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if (!empty($row['session_type'])): ?>
                                        <span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">
                                            <?= htmlspecialchars(frSessionLabel($row['session_type'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs italic text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <?= !empty($row['target_first_reading_date'])
                                        ? htmlspecialchars(date('M j, Y', strtotime($row['target_first_reading_date'])))
                                        : '<span class="text-xs italic text-gray-400">Not set</span>' ?>
                                </td>
                                <td class="w-36 px-3 py-4 text-right">
                                    <div class="flex w-full justify-end">
                                    <a href="<?= BASE_URL ?>/<?= $processRoute ?>" aria-label="Process <?= htmlspecialchars($row['tracking_number']) ?>"
                                       title="Process document"
                                       class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:border-primary hover:bg-blue-50 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        <span>Process</span>
                                    </a>
                                    </div>
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
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <p class="mt-4 text-sm font-medium text-gray-700">No documents in this category.</p>
                    <p class="mt-1 text-xs text-gray-500">Documents will appear here once their Plenary processing details are saved.</p>
                </div>
            <?php else: ?>
                <?php foreach ($documents as $row):
                    $isComm = frIsComm($row);
                    $isCases = frIsCases($row);
                    if ($isComm) {
                        $processRoute = 'plenary/comm-first-reading/show?id=' . (int) $row['document_id'];
                    } elseif ($isCases) {
                        $processRoute = 'plenary/cases-first-reading/show?id=' . (int) $row['document_id'];
                    } else {
                        $processRoute = 'plenary/first-reading/show?id=' . (int) $row['document_id'];
                    }
                    $rowStatus = $row['status'] ?? 'Awaiting First Reading';
                    $rowStatusColor = $row['status_badge_color'] ?? '#2563EB';
                ?>
                    <article class="p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-bold text-gray-900"><?= htmlspecialchars($row['tracking_number']) ?></p>
                                <p class="mt-1 text-xs text-gray-500"><?= htmlspecialchars(date('M j, Y', strtotime($row['date_received']))) ?></p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <p class="text-sm font-medium leading-6 text-gray-900"><?= htmlspecialchars($row['subject_matter']) ?></p>
                            <?php if (!empty($row['document_type_name'])): ?>
                                <span class="mt-2 inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                      style="background-color: <?= htmlspecialchars($row['document_type_badge_color'] ?? '#2563EB') ?>1a; color: <?= htmlspecialchars($row['document_type_badge_color'] ?? '#2563EB') ?>;">
                                    <?= htmlspecialchars($row['document_type_name']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-gray-100 pt-4">
                            <div>
                                <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Status</dt>
                                <dd class="mt-1">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                                          style="background-color: <?= htmlspecialchars($rowStatusColor) ?>1a; color: <?= htmlspecialchars($rowStatusColor) ?>;">
                                        <span class="h-1.5 w-1.5 rounded-full" style="background-color: <?= htmlspecialchars($rowStatusColor) ?>;"></span>
                                        <?= htmlspecialchars($rowStatus) ?>
                                    </span>
                                </dd>
                            </div>
                            <?php if ($tab !== 'communication'): ?>
                                <div>
                                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Proposed No.</dt>
                                    <dd class="mt-1 text-sm font-medium text-gray-800"><?= !empty($row['proposed_number']) ? htmlspecialchars($row['proposed_number']) : '<span class="text-gray-400">—</span>' ?></dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Session Type</dt>
                                    <dd class="mt-1 text-sm text-gray-700"><?= !empty($row['session_type']) ? htmlspecialchars(frSessionLabel($row['session_type'])) : '<span class="text-gray-400">—</span>' ?></dd>
                                </div>
                            <?php endif; ?>
                            <div>
                                <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Target Date</dt>
                                <dd class="mt-1 text-sm text-gray-700"><?= !empty($row['target_first_reading_date']) ? htmlspecialchars(date('M j, Y', strtotime($row['target_first_reading_date']))) : '<span class="text-gray-400">Not set</span>' ?></dd>
                            </div>
                        </dl>
                        <div class="mt-4 flex justify-end border-t border-gray-100 pt-3">
                            <a href="<?= BASE_URL ?>/<?= $processRoute ?>" aria-label="Process <?= htmlspecialchars($row['tracking_number']) ?>"
                               title="Process document"
                               class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:border-primary hover:bg-blue-50 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <span>Process</span>
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
                <nav class="flex flex-wrap items-center justify-center gap-1" aria-label="First Reading pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?= frPageUrl($tab, $page - 1, $search, $sessionType) ?>"
                           class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Prev</a>
                    <?php endif; ?>
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                        <a href="<?= frPageUrl($tab, $i, $search, $sessionType) ?>"
                           <?= $i === $page ? 'aria-current="page"' : '' ?>
                           class="rounded-lg border px-3 py-1.5 text-sm font-medium transition
                                  <?= $i === $page ? 'border-primary bg-primary text-white' : 'border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?= frPageUrl($tab, $page + 1, $search, $sessionType) ?>"
                           class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Next</a>
                    <?php endif; ?>
                </nav>
            </div>
    </section>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

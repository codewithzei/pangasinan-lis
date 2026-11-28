<?php
/**
 * Plenary Referred to Committee — tracking list
 *
 * Tracks documents that were referred to a Committee during First Reading.
 * Returned legislative documents can adopt the Committee Report as Approved
 * (proceeding to Second Reading) or Disapproved (archive the report only).
 *
 * The status column reflects documents.current_status_id — the SAME shared
 * status used by every other division. No Plenary-only status is introduced.
 *
 * Variables supplied by PlenaryReadingController::referredToCommitteeIndex():
 *   $documents          array
 *   $page               int
 *   $totalPages         int
 *   $total              int
 *   $search             string
 *   $documentTypeId     int
 *   $documentTypes      array
 *   $tab                string  ('in_committee' | 'returned' | 'remanded' | 'withdrawn' | 'noted')
 *   $inCommitteeCount   int
 *   $returnedCount      int
 *   $outcomeCounts      array
 *   $success            string|null
 *   $error              string|null
 *   $errors             array
 */

$documents        = $documents        ?? [];
$page             = $page             ?? 1;
$totalPages       = $totalPages       ?? 1;
$total            = $total            ?? 0;
$search           = $search           ?? '';
$documentTypeId   = (int) ($documentTypeId ?? 0);
$documentTypes    = $documentTypes    ?? [];
$tab              = $tab              ?? 'in_committee';
$inCommitteeCount = $inCommitteeCount ?? 0;
$returnedCount    = $returnedCount    ?? 0;
$outcomeCounts    = $outcomeCounts    ?? ['remanded' => 0, 'withdrawn' => 0, 'noted' => 0];
$withdrawnNotedCount = (int) ($outcomeCounts['withdrawn'] ?? 0) + (int) ($outcomeCounts['noted'] ?? 0);
$errors           = $errors           ?? [];
$tabLabels = [
    'in_committee' => 'In Committee',
    'returned' => 'Returned — Awaiting 2nd Reading',
    'remanded' => 'Remanded',
    'withdrawn' => 'Withdrawn',
    'noted' => 'Noted',
];
$tabCounts = [
    'in_committee' => $inCommitteeCount,
    'returned' => $returnedCount,
    'remanded' => $outcomeCounts['remanded'] ?? 0,
    'withdrawn' => $outcomeCounts['withdrawn'] ?? 0,
    'noted' => $outcomeCounts['noted'] ?? 0,
];

ob_start();

function rtcSessionLabel(string $type): string {
    return match ($type) {
        'REGULAR'        => 'Regular',
        'REGULAR_ONLINE' => 'Regular (Online)',
        'SPECIAL'        => 'Special',
        'SPECIAL_ONLINE' => 'Special (Online)',
        default          => $type ?: '—',
    };
}

function rtcBuildTabUrl(string $targetTab, string $search, int $documentTypeId): string {
    $params = ['tab' => $targetTab];
    if ($search !== '') {
        $params['search'] = $search;
    }
    if ($documentTypeId > 0) {
        $params['doc_type'] = $documentTypeId;
    }
    return BASE_URL . '/plenary/referred-to-committee?' . http_build_query($params);
}

function rtcBuildPageUrl(string $tab, int $pageNum, string $search, int $documentTypeId): string {
    $params = ['tab' => $tab, 'page' => $pageNum];
    if ($search !== '') {
        $params['search'] = $search;
    }
    if ($documentTypeId > 0) {
        $params['doc_type'] = $documentTypeId;
    }
    return BASE_URL . '/plenary/referred-to-committee?' . http_build_query($params);
}

function rtcBuildDetailUrl(int $documentId, string $tab, int $pageNum, string $search, int $documentTypeId): string {
    $params = ['id' => $documentId, 'tab' => $tab, 'page' => $pageNum];
    if ($search !== '') {
        $params['search'] = $search;
    }
    if ($documentTypeId > 0) {
        $params['doc_type'] = $documentTypeId;
    }
    return BASE_URL . '/plenary/referred-to-committee/show?' . http_build_query($params);
}
?>

<div class="space-y-6">

    <?php if (!empty($success ?? null)): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status" aria-live="polite"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($error ?? null)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert"><?= htmlspecialchars($error) ?></div>
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
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-blue-100">Plenary · Legislative Readings</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-white sm:text-3xl">Referred to Committee</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-blue-100">Track documents referred to a Committee. Adopt returned Committee Reports as approved to proceed to Second Reading, or disapproved to save the report copy only.</p>
            </div>
            <div class="flex items-center gap-3 border-t border-white/15 pt-4 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
                <span class="flex h-2.5 w-2.5 rounded-full <?= $tab === 'returned' ? 'bg-violet-300 ring-4 ring-violet-300/20' : ($tab === 'in_committee' ? 'bg-amber-300 ring-4 ring-amber-300/20' : 'bg-blue-300 ring-4 ring-blue-300/20') ?>"></span>
                <div>
                    <p class="text-xs font-medium text-white"><?= htmlspecialchars($tabLabels[$tab] ?? 'Committee queue') ?></p>
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
                <h2 class="mt-1 text-xl font-semibold text-gray-900">Where documents stand</h2>
            </div>
            <p class="text-xs text-gray-500">A quick summary of referred documents</p>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-x-0 top-0 h-1 bg-amber-500"></span>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-700">With the Committee</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format($inCommitteeCount) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-xs leading-5 text-gray-500">Waiting for committee review or a report.</p>
            </article>
            <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-x-0 top-0 h-1 bg-violet-500"></span>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-700">Returned to Plenary</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format($returnedCount) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-xs leading-5 text-gray-500">Back from committee and ready for plenary review.</p>
            </article>
            <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-x-0 top-0 h-1 bg-blue-500"></span>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-700">Sent Back to Committee</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format((int) ($outcomeCounts['remanded'] ?? 0)) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 14l-4-4 4-4M5 10h9a5 5 0 010 10h-2"/>
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-xs leading-5 text-gray-500">Remanded for more committee work.</p>
            </article>
            <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-x-0 top-0 h-1 bg-gray-400"></span>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-700">Withdrawn or Noted</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format($withdrawnNotedCount) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 text-gray-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7h12M8 12h8m-8 5h5M4 7h.01M4 12h.01M4 17h.01"/>
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-xs leading-5 text-gray-500">Documents withdrawn or marked as noted.</p>
            </article>
        </div>
    </section>

    <!-- Tabs -->
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-1 overflow-x-auto" aria-label="Referred to Committee tabs">
            <?php foreach ($tabLabels as $tabKey => $tabLabel): ?>
                <a href="<?= htmlspecialchars(rtcBuildTabUrl($tabKey, $search, $documentTypeId)) ?>"
                   class="inline-flex items-center gap-2 whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium transition <?= $tab === $tabKey ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' ?>">
                    <?= htmlspecialchars($tabLabel) ?>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600 <?= $tab === $tabKey ? 'bg-primary/10 text-primary' : '' ?>"><?= number_format((int) $tabCounts[$tabKey]) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <!-- Document List -->
    <section class="rounded-2xl border border-gray-200 bg-white">
        <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="shrink-0">
                <h2 class="whitespace-nowrap text-sm font-semibold text-gray-900">
                    <?= htmlspecialchars($tabLabels[$tab] ?? 'In Committee') ?>
                </h2>
                <p class="mt-1 text-xs text-gray-500"><?= $total ?> record<?= $total !== 1 ? 's' : '' ?> found</p>
            </div>
            <form method="GET" action="<?= BASE_URL ?>/plenary/referred-to-committee" class="hidden flex-col gap-3 xl:flex xl:flex-row xl:items-center">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                <input type="hidden" name="page" value="1">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search tracking # or subject…"
                           class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 lg:w-64">
                </div>
                <select name="doc_type"
                        aria-label="Filter by document type"
                        class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="0">All document types</option>
                    <?php foreach ($documentTypes as $documentType): ?>
                        <option value="<?= (int) $documentType['id'] ?>"
                            <?= $documentTypeId === (int) $documentType['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($documentType['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Filter</button>
                <?php if ($search !== '' || $documentTypeId > 0): ?>
                    <a href="<?= htmlspecialchars(rtcBuildTabUrl($tab, '', 0)) ?>" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition text-center">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="flex min-w-0 items-center gap-2 border-b border-gray-100 px-4 py-4 xl:hidden">
            <form method="GET" action="<?= BASE_URL ?>/plenary/referred-to-committee" class="relative min-w-0 flex-1">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="page" value="1">
                <input type="hidden" name="doc_type" value="<?= (int) $documentTypeId ?>">
                <label for="rtc-mobile-search" class="sr-only">Search by tracking number or subject</label>
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input id="rtc-mobile-search" type="search" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search tracking # or subject..."
                       class="block w-full min-w-0 rounded-xl border border-gray-200 bg-white py-2.5 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
            </form>
            <button type="button" data-inbox-filter-target="#rtc-mobile-filter-sheet" aria-label="Open filters" aria-haspopup="dialog"
                    class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 hover:bg-gray-50">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M6 12h12m-9 8h6"/>
                </svg>
                <?php if ($documentTypeId > 0): ?><span class="absolute right-1 top-1 h-2 w-2 rounded-full bg-primary"></span><?php endif; ?>
            </button>
        </div>
        <div id="rtc-mobile-filter-sheet" class="fixed inset-0 z-50 hidden xl:hidden">
            <button type="button" class="inbox-filter-backdrop absolute inset-0 bg-gray-950/40" aria-label="Close filters"></button>
            <form method="GET" action="<?= BASE_URL ?>/plenary/referred-to-committee" role="dialog" aria-modal="true" aria-labelledby="rtc-mobile-filter-title"
                  class="absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto rounded-t-2xl bg-white p-5 shadow-2xl">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="page" value="1">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                <div class="mb-5 flex items-center justify-between">
                    <h3 id="rtc-mobile-filter-title" class="font-semibold text-gray-900">Filters</h3>
                    <button type="button" class="inbox-filter-close rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-100">Close</button>
                </div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700" for="rtc-mobile-doc-type">Document type</label>
                <select id="rtc-mobile-doc-type" name="doc_type" class="mb-5 block w-full min-w-0 rounded-xl border border-gray-200 bg-white px-3 py-3 text-sm text-gray-800">
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
                        <a href="<?= htmlspecialchars(rtcBuildTabUrl($tab, '', 0), ENT_QUOTES, 'UTF-8') ?>" class="flex w-full items-center justify-center rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <?php require __DIR__ . '/../../partials/inbox-mobile-filter-script.php'; ?>

        <div class="hidden overflow-x-auto xl:block">
            <table class="w-full table-fixed text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="w-[16%] px-4 py-3 font-medium">Tracking Number</th>
                        <th class="w-[32%] px-4 py-3 font-medium">Subject Matter</th>
                        <th class="w-[18%] px-4 py-3 font-medium">Committee(s)</th>
                        <th class="w-[14%] px-4 py-3 font-medium">Hearing Status</th>
                        <th class="w-[20%] px-4 py-3 font-medium text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($documents)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">
                                    No documents in the <?= htmlspecialchars($tabLabels[$tab] ?? 'In Committee') ?> queue.
                                </p>
                                <p class="mt-1 text-xs text-gray-500">Documents referred during First Reading remain here until the returned Committee Report is adopted.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($documents as $row): ?>
                            <?php
                                $typeColor    = $row['document_type_badge_color'] ?? '#2563EB';
                                $canAdvance   = !empty($row['can_advance']);
                                $committeeOutcome = strtoupper(trim((string) ($row['committee_hearing_outcome'] ?? '')));
                                $committeeOutcomeLabel = $committeeOutcome !== ''
                                    ? ucwords(strtolower(str_replace('_', ' ', $committeeOutcome)))
                                    : 'Pending';
                                $committeeOutcomeColors = [
                                    'APPROVED' => '#16A34A',
                                    'DEFERRED' => '#D97706',
                                    'REMANDED' => '#2563EB',
                                    'WITHDRAWN' => '#6B7280',
                                    'NOTED' => '#7C3AED',
                                    'TERMINATED' => '#DC2626',
                                ];
                                $committeeOutcomeColor = $committeeOutcomeColors[$committeeOutcome] ?? '#6B7280';
                                $detailUrl = rtcBuildDetailUrl((int) $row['document_id'], $tab, $page, $search, $documentTypeId);
                            ?>
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="px-4 py-4">
                                    <a href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-medium text-gray-900 hover:text-primary hover:underline"><?= htmlspecialchars($row['tracking_number']) ?></a>
                                    <div class="mt-0.5 text-xs text-gray-500">
                                        <?php if (!empty($row['proposed_number'])): ?>
                                            <?= htmlspecialchars($row['proposed_number']) ?> ·
                                        <?php endif; ?>
                                        <?= htmlspecialchars(date('M j, Y', strtotime($row['date_received']))) ?>
                                    </div>
                                    <?php if (!empty($row['accepted_by_username'])): ?>
                                        <div class="mt-1 text-xs text-gray-400">Accepted by <?= htmlspecialchars($row['accepted_by_username']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="max-w-2xl">
                                        <p class="whitespace-pre-wrap break-words text-sm font-medium leading-5 text-gray-900"><?= htmlspecialchars($row['subject_matter']) ?></p>
                                        <?php if (!empty($row['document_type_name'])): ?>
                                            <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                                  style="background-color: <?= htmlspecialchars($typeColor) ?>1a; color: <?= htmlspecialchars($typeColor) ?>;">
                                                <?= htmlspecialchars($row['document_type_name']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <?php
                                    $committeeNames = array_values(array_filter(array_map('trim', explode(',', (string) ($row['committee_names'] ?? '')))));
                                    $committeeCount = count($committeeNames);
                                ?>
                                <td class="px-4 py-4">
                                    <?php if ($committeeCount > 0): ?>
                                        <div class="text-sm font-medium text-gray-800" title="<?= htmlspecialchars(implode(', ', $committeeNames), ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($committeeNames[0]) ?>
                                            <?php if ($committeeCount > 1): ?>
                                                <span class="ml-1 text-xs font-medium text-gray-500">+<?= $committeeCount - 1 ?> others</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs italic text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                          style="background-color: <?= htmlspecialchars($committeeOutcomeColor) ?>1a; color: <?= htmlspecialchars($committeeOutcomeColor) ?>;">
                                        <?= htmlspecialchars($committeeOutcomeLabel) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="ml-auto flex w-full max-w-56 flex-col items-end gap-2">
                                    <a href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>"
                                       class="inline-flex max-w-full items-center justify-center gap-1.5 whitespace-normal rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-center text-xs font-medium text-gray-700 transition hover:border-primary hover:bg-blue-50 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                       aria-label="View details for <?= htmlspecialchars($row['tracking_number'], ENT_QUOTES, 'UTF-8') ?>"
                                       title="View details">
                                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5s8.268 2.943 9.542 7c-1.274 4.057-5.065 7-9.542 7s-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        <span>View details</span>
                                    </a>
                                    <?php if ($canAdvance): ?>
                                        <?php $hearingApproved = ($row['committee_hearing_outcome'] ?? '') === 'APPROVED'; ?>
                                        <?php if ($hearingApproved && !empty($row['is_plenary_cases_referred'])): ?>
                                            <!-- Cases/Petitions returned from Committee: Adopt Committee Report -->
                                            <button type="button"
                                                    onclick="openAdoptCasesModal(<?= (int) $row['document_id'] ?>, <?= htmlspecialchars(json_encode((string) $row['tracking_number'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode((string) ($row['subject_matter'] ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>)"
                                                    class="inline-flex max-w-full items-center justify-center gap-1.5 whitespace-normal rounded-lg bg-primary px-3 py-1.5 text-center text-xs font-medium text-white transition hover:bg-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                                    aria-label="Adopt Committee Report for <?= htmlspecialchars($row['tracking_number'], ENT_QUOTES, 'UTF-8') ?>"
                                                    title="Adopt Committee Report">
                                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                <span>Adopt Committee Report</span>
                                            </button>
                                        <?php elseif ($hearingApproved && !empty($row['is_plenary_comm_referred'])): ?>
                                            <!-- Communication returned from Committee: Adopt Committee Report -->
                                            <button type="button"
                                                    onclick="openAdoptModal(<?= (int) $row['document_id'] ?>, <?= htmlspecialchars(json_encode((string) $row['tracking_number'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode((string) ($row['subject_matter'] ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>, <?= !empty($row['is_mfr']) ? 'true' : 'false' ?>)"
                                                    class="inline-flex max-w-full items-center justify-center gap-1.5 whitespace-normal rounded-lg bg-primary px-3 py-1.5 text-center text-xs font-medium text-white transition hover:bg-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                                    aria-label="Adopt Committee Report for <?= htmlspecialchars($row['tracking_number'], ENT_QUOTES, 'UTF-8') ?>"
                                                    title="Adopt Committee Report">
                                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                <span>Adopt Committee Report</span>
                                            </button>
                                        <?php else: ?>
                                            <!-- Adoption follows the latest Committee hearing outcome. -->
                                            <button type="button"
                                                    class="open-report-adoption-confirmation inline-flex max-w-full items-center justify-center gap-1.5 whitespace-normal rounded-lg px-3 py-1.5 text-center text-xs font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 <?= $hearingApproved ? 'bg-violet-600 text-white hover:bg-violet-700' : 'border border-gray-300 bg-white text-gray-600 hover:bg-gray-50' ?>"
                                                    data-document-id="<?= (int) $row['document_id'] ?>"
                                                    data-decision="<?= $hearingApproved ? 'APPROVED' : 'DISAPPROVED' ?>"
                                                    data-tracking-number="<?= htmlspecialchars($row['tracking_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                    aria-label="<?= $hearingApproved ? 'Approve and proceed to Second Reading' : 'Adopt report only' ?> for <?= htmlspecialchars($row['tracking_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                    title="<?= $hearingApproved ? 'Approve and proceed to 2nd Reading' : 'Adopt Report Only' ?>">
                                                <?php if ($hearingApproved): ?>
                                                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                                    </svg>
                                                    <span>Approve &amp; Proceed to 2nd Reading</span>
                                                <?php else: ?>
                                                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                    <span>Adopt Report Only</span>
                                                <?php endif; ?>
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="space-y-4 border-t border-gray-100 px-4 py-4 sm:px-6 xl:hidden">
            <?php if (empty($documents)): ?>
                <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-10 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-white text-gray-400 shadow-sm">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                    <p class="mt-3 text-sm font-medium text-gray-700">No matching committee records</p>
                    <p class="mt-1 text-xs text-gray-500">Try changing your search or document type filter.</p>
                </div>
            <?php else: ?>
                <?php foreach ($documents as $row): ?>
                    <?php
                        $cardOutcome = strtoupper(trim((string) ($row['committee_hearing_outcome'] ?? '')));
                        $cardOutcomeLabel = $cardOutcome !== '' ? ucwords(strtolower(str_replace('_', ' ', $cardOutcome))) : 'Pending';
                        $cardOutcomeColors = ['APPROVED' => '#16A34A', 'DEFERRED' => '#D97706', 'REMANDED' => '#2563EB', 'WITHDRAWN' => '#6B7280', 'NOTED' => '#7C3AED', 'TERMINATED' => '#DC2626'];
                        $cardOutcomeColor = $cardOutcomeColors[$cardOutcome] ?? '#6B7280';
                        $cardCommittees = array_values(array_filter(array_map('trim', explode(',', (string) ($row['committee_names'] ?? '')))));
                        $detailUrl = rtcBuildDetailUrl((int) $row['document_id'], $tab, $page, $search, $documentTypeId);
                        $isMobile = true;
                    ?>
                    <article class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <a href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>" class="break-all text-sm font-semibold text-primary hover:underline"><?= htmlspecialchars($row['tracking_number']) ?></a>
                                <span class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold" style="background-color: <?= htmlspecialchars($cardOutcomeColor) ?>1a; color: <?= htmlspecialchars($cardOutcomeColor) ?>;"><?= htmlspecialchars($cardOutcomeLabel) ?></span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500"><?= htmlspecialchars(date('M j, Y', strtotime($row['date_received']))) ?>
                                <?php if (!empty($row['proposed_number'])): ?> · <?= htmlspecialchars($row['proposed_number']) ?><?php endif; ?>
                            </p>
                            <?php if (!empty($row['accepted_by_username'])): ?><p class="mt-1 text-xs text-gray-400">Accepted by <?= htmlspecialchars($row['accepted_by_username']) ?></p><?php endif; ?>
                        </div>
                        <div class="space-y-4 p-4">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Subject matter</p>
                                <p class="mt-1 whitespace-pre-wrap break-words text-sm leading-5 text-gray-800"><?= htmlspecialchars($row['subject_matter']) ?></p>
                            </div>
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Document type</p>
                                    <p class="mt-1 text-sm text-gray-700"><?= htmlspecialchars($row['document_type_name'] ?? '—') ?></p>
                                </div>
                                <div class="min-w-0 text-right">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Committee(s)</p>
                                    <p class="mt-1 break-words text-sm text-gray-700"><?= !empty($cardCommittees) ? htmlspecialchars(implode(', ', $cardCommittees)) : '—' ?></p>
                                </div>
                            </div>
                            <div class="border-t border-gray-100 pt-3">
                                <?php require __DIR__ . '/row-actions.php'; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="flex flex-col items-center justify-between gap-3 border-t border-gray-100 px-4 py-4 sm:flex-row sm:px-6">
                <p class="text-xs text-gray-500"><?= $total > 0 ? 'Showing ' . min((($page - 1) * 20) + 1, $total) . '–' . min($page * 20, $total) . ' of ' . number_format($total) . ' records' : 'Showing 0 records' ?></p>
                <div class="flex items-center gap-1">
                    <?php if ($page > 1): ?><a href="<?= htmlspecialchars(rtcBuildPageUrl($tab, $page - 1, $search, $documentTypeId)) ?>" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition" aria-label="Previous page">Prev</a><?php endif; ?>
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="<?= htmlspecialchars(rtcBuildPageUrl($tab, $i, $search, $documentTypeId)) ?>" class="rounded-lg border px-3 py-1.5 text-sm font-medium transition <?= $i === $page ? 'border-primary bg-primary text-white' : 'border-gray-200 text-gray-700 hover:bg-gray-50' ?>" <?= $i === $page ? 'aria-current="page"' : '' ?>><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?><a href="<?= htmlspecialchars(rtcBuildPageUrl($tab, $page + 1, $search, $documentTypeId)) ?>" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition" aria-label="Next page">Next</a><?php endif; ?>
                </div>
        </div>
    </section>
</div>

<!-- ======================================================================
     Adopt Committee Report Modal placeholder — the actual HTML is injected
     at the body level via $pageModals (see bottom of this file) so it sits
     outside <main> and cannot be clipped or z-index-trapped by any ancestor
     stacking context.  This keeps it consistent with the Sign Out modal.
     ====================================================================== -->

<?php
$content = ob_get_clean();

// ── Adopt Committee Report Modal ──────────────────────────────────────────────
// Injected at body level (outside <main>) via $pageModals in app.php so it
// shares the same stacking context as the Sign Out modal (z-[100], full
// viewport coverage, not trapped by the sidebar or main column).
//
// Structure mirrors the Sign Out modal exactly:
//   container  fixed inset-0 z-[100] hidden  (class-toggled by JS)
//   backdrop   absolute inset-0 backdrop-blur-sm  (animated opacity)
//   panel      relative z-10  (animated scale + opacity)
// ---------------------------------------------------------------------------
ob_start();
?>
<div id="reportAdoptionConfirmationModal"
     class="fixed inset-0 z-[100] hidden items-center justify-center"
     role="dialog"
     aria-modal="true"
     aria-labelledby="reportAdoptionConfirmationTitle"
     aria-describedby="reportAdoptionConfirmationMessage">
    <div id="reportAdoptionConfirmationBackdrop"
         class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm"></div>
    <div class="relative z-10 mx-4 w-full max-w-md rounded-2xl border border-gray-100 bg-white shadow-2xl">
        <div class="p-6">
            <div class="flex items-start gap-4">
                <div id="reportAdoptionConfirmationIcon"
                     class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-violet-100 text-violet-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <h2 id="reportAdoptionConfirmationTitle" class="text-lg font-semibold text-gray-900">
                        Confirm Committee Report Adoption
                    </h2>
                    <p id="reportAdoptionConfirmationMessage" class="mt-1 text-sm text-gray-600"></p>
                    <p id="reportAdoptionConfirmationTracking" class="mt-2 font-mono text-sm font-bold text-gray-800"></p>
                </div>
            </div>
        </div>
        <div class="flex items-center justify-end gap-3 rounded-b-2xl border-t border-gray-100 bg-gray-50 px-6 py-4">
            <button type="button" id="cancelReportAdoptionConfirmation"
                    class="inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Cancel
            </button>
            <form id="reportAdoptionConfirmationForm" method="POST" action="<?= BASE_URL ?>/plenary/referred-to-committee/adopt-report">
                <input type="hidden" name="document_id" id="reportAdoptionConfirmationDocumentId" value="">
                <input type="hidden" name="decision" id="reportAdoptionConfirmationDecision" value="">
                <button type="submit" id="confirmReportAdoptionConfirmation"
                        class="inline-flex items-center justify-center rounded-xl bg-violet-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">
                    Confirm
                </button>
            </form>
        </div>
    </div>
</div>

<div
    id="adopt-comm-modal"
    class="fixed inset-0 z-[100] hidden items-center justify-center"
    role="dialog"
    aria-modal="true"
    aria-labelledby="adopt-modal-title"
>
    <!-- Backdrop — separate element so it can be blurred without affecting the panel -->
    <div id="adopt-comm-backdrop"
         class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity opacity-0"></div>

    <!-- Panel -->
    <div id="adopt-comm-panel"
         class="relative z-10 w-full max-w-lg mx-4 max-h-[92vh] overflow-y-auto bg-white rounded-2xl shadow-2xl border border-gray-100
                transform scale-95 opacity-0 transition-all duration-200">

        <!-- Header -->
        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
            <h2 id="adopt-modal-title" class="text-base font-semibold text-gray-900">
                Adopted Committee Report
            </h2>
            <button type="button" id="adopt-close-btn"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition"
                    aria-label="Close">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Body -->
        <form method="POST" action="<?= BASE_URL ?>/plenary/referred-to-committee/adopt-comm"
              enctype="multipart/form-data" class="px-6 py-5 space-y-4" id="adopt-comm-form">
            <input type="hidden" name="document_id" id="adopt-document-id" value="">

            <p class="text-sm text-gray-600">
                Finalizing committee report adoption for:
                <span id="adopt-tracking-number" class="font-semibold text-gray-900"></span>
            </p>
            <p class="text-xs text-gray-500">The returned Committee Report copy will also be available on Plenary Reports.</p>

            <fieldset id="adopt-mfr-status-field" class="hidden rounded-2xl border border-amber-200 bg-amber-50/70 p-4">
                <legend class="px-2 text-sm font-bold text-amber-900">
                    Motion for Reconsideration Decision <span class="text-red-500" aria-hidden="true">*</span>
                </legend>
                <p class="mb-3 text-xs text-amber-700">Choose how Plenary will dispose of this Motion for Reconsideration.</p>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label data-mfr-card="GRANTED"
                           class="cursor-pointer rounded-xl border-2 border-gray-200 bg-white p-4 transition hover:border-emerald-300 hover:bg-emerald-50/40">
                        <span class="flex items-center gap-3">
                            <input type="radio" name="mfr_status" value="GRANTED" class="sr-only" onchange="updateMfrStatusCards(this)">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </span>
                            <span>
                                <span class="block text-sm font-bold text-gray-900">Granted</span>
                                <span class="mt-0.5 block text-xs text-gray-500">Approve the reconsideration</span>
                            </span>
                        </span>
                    </label>
                    <label data-mfr-card="DENIED"
                           class="cursor-pointer rounded-xl border-2 border-gray-200 bg-white p-4 transition hover:border-red-300 hover:bg-red-50/40">
                        <span class="flex items-center gap-3">
                            <input type="radio" name="mfr_status" value="DENIED" class="sr-only" onchange="updateMfrStatusCards(this)">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-700">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </span>
                            <span>
                                <span class="block text-sm font-bold text-gray-900">Denied</span>
                                <span class="mt-0.5 block text-xs text-gray-500">Reject the reconsideration</span>
                            </span>
                        </span>
                    </label>
                </div>
            </fieldset>

            <fieldset>
                <legend class="block text-sm font-medium text-gray-700 mb-2">
                    Final Output Type <span class="text-red-500" aria-hidden="true">*</span>
                </legend>
                <div class="flex flex-wrap gap-3">
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700">
                        <input type="radio" name="final_output_type" value="RESOLUTION" required checked class="h-4 w-4 accent-primary">
                        Resolution
                    </label>
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700">
                        <input type="radio" name="final_output_type" value="ORDINANCE" required class="h-4 w-4 accent-primary">
                        Ordinance
                    </label>
                </div>
                <p class="mt-1 text-xs text-gray-500">This classifies the finalized output and does not change the original document type.</p>
            </fieldset>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="adopt-resolution-number"
                           class="block text-sm font-medium text-gray-700 mb-1">
                        Resolution/Ordinance No. <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="adopt-resolution-number" name="resolution_number"
                           maxlength="100" required
                           placeholder="e.g. SP Res. No. 2026-001 or SP Ordinance No. 2026-001"
                           class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                                  text-gray-800 placeholder-gray-400 focus:border-primary
                                  focus:outline-none focus:ring-2 focus:ring-primary/20">
                </div>
                <div>
                    <label for="adopt-proposed-number"
                           class="block text-sm font-medium text-gray-700 mb-1">
                        Proposed Number
                        <span class="text-xs font-normal text-gray-400">(optional)</span>
                    </label>
                    <input type="text" id="adopt-proposed-number" name="proposed_number"
                           maxlength="100"
                           placeholder="e.g. SP Res. No. 2026-001"
                           class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                                  text-gray-800 placeholder-gray-400 focus:border-primary
                                  focus:outline-none focus:ring-2 focus:ring-primary/20">
                </div>
            </div>

            <div>
                <label for="adopt-approved-subject-matter" class="block text-sm font-medium text-gray-700 mb-1">
                    Subject Matter <span class="text-red-500" aria-hidden="true">*</span>
                </label>
                <textarea id="adopt-approved-subject-matter" name="approved_subject_matter" rows="3"
                          maxlength="5000" required
                          class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                                 text-gray-800 placeholder-gray-400 focus:border-primary
                                 focus:outline-none focus:ring-2 focus:ring-primary/20"></textarea>
            </div>

            <!-- ── Final Output Attachment — drag-and-drop upload area ── -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Final Output Attachment(s) <span class="text-red-500" aria-hidden="true">*</span>
                </label>

                <!-- Hidden real file input (accumulator) -->
                <input type="file" id="adopt-resolution-files" name="resolution_files[]"
                       multiple
                       required
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp"
                       class="sr-only"
                       aria-label="Final output attachment files"
                       tabindex="-1">

                <!-- Drop zone -->
                <div id="adopt-drop-zone"
                     role="button"
                     tabindex="0"
                     aria-label="Drop final output files here or click to browse"
                     class="relative flex h-32 w-full flex-col items-center justify-center gap-2
                            rounded-xl border-2 border-dashed border-gray-300 bg-gray-50
                            px-4 text-center transition hover:bg-gray-100
                            focus:outline-none focus:ring-2 focus:ring-primary/40
                            cursor-pointer">

                    <svg class="h-8 w-8 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>

                    <p class="mb-1 text-sm text-gray-600 pointer-events-none">
                        <span class="font-semibold">Click to upload</span> or drag and drop
                        <span class="text-primary underline-offset-2 hover:underline">browse</span>
                    </p>
                    <p class="text-xs text-gray-500 pointer-events-none">
                        PDF, Word, Excel, images (MAX. 25 MB each)
                    </p>

                    <div id="adopt-drag-overlay"
                         class="pointer-events-none absolute inset-0 hidden rounded-xl
                                border-2 border-primary bg-primary/10">
                    </div>
                </div>

                <ul id="adopt-file-list"
                    class="hidden mt-3 space-y-2"
                    aria-label="Selected final output files"
                    aria-live="polite">
                </ul>

                <p id="adopt-file-error" class="hidden mt-1 text-xs text-red-600" role="alert"></p>

                <p class="mt-1 text-xs text-gray-400">
                    Supported: PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, PNG, GIF, WEBP
                </p>
            </div>

            <div>
                <label for="adopt-remarks" class="block text-sm font-medium text-gray-700 mb-1">
                    Remarks <span class="text-xs font-normal text-gray-400">(optional)</span>
                </label>
                <textarea id="adopt-remarks" name="remarks" rows="2" maxlength="2000"
                          class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                                 text-gray-800 placeholder-gray-400 focus:border-primary
                                 focus:outline-none focus:ring-2 focus:ring-primary/20"
                          placeholder="Optional remarks…"></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                <button type="button" id="adopt-cancel-btn"
                        class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium
                               text-gray-700 hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit" id="adopt-submit-btn"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2 text-sm
                               font-semibold text-white hover:bg-emerald-700 transition
                               focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span id="adopt-submit-label">Finalize Communication</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    'use strict';

    // ── Modal open / close ────────────────────────────────────────────────────
    // Mirrors the Sign Out modal pattern exactly: separate backdrop + panel
    // elements driven by class toggling with a 200 ms CSS transition.

    var modal    = document.getElementById('adopt-comm-modal');
    var backdrop = document.getElementById('adopt-comm-backdrop');
    var panel    = document.getElementById('adopt-comm-panel');
    var adoptOpen = false;

    window.updateMfrStatusCards = function (radio) {
        document.querySelectorAll('[data-mfr-card]').forEach(function (card) {
            card.classList.remove('border-emerald-500', 'border-red-500', 'bg-emerald-50', 'bg-red-50', 'ring-2', 'ring-emerald-200', 'ring-red-200');
            card.classList.add('border-gray-200', 'bg-white');
        });
        var card = radio.closest('[data-mfr-card]');
        if (!card) return;
        card.classList.remove('border-gray-200', 'bg-white');
        if (radio.value === 'GRANTED') {
            card.classList.add('border-emerald-500', 'bg-emerald-50', 'ring-2', 'ring-emerald-200');
        } else {
            card.classList.add('border-red-500', 'bg-red-50', 'ring-2', 'ring-red-200');
        }
    };

    window.openAdoptModal = function (documentId, trackingNumber, subjectMatter, isMfr) {
        if (!modal || adoptOpen) return;

        var mfrField = document.getElementById('adopt-mfr-status-field');
        if (mfrField) {
            mfrField.classList.toggle('hidden', !isMfr);
            mfrField.querySelectorAll('input[name="mfr_status"]').forEach(function (input) {
                input.required = !!isMfr;
                input.checked = false;
            });
            document.querySelectorAll('[data-mfr-card]').forEach(function (card) {
                card.classList.remove('border-emerald-500', 'border-red-500', 'bg-emerald-50', 'bg-red-50', 'ring-2', 'ring-emerald-200', 'ring-red-200');
                card.classList.add('border-gray-200', 'bg-white');
            });
        }

        document.getElementById('adopt-modal-title').textContent = isMfr
            ? 'Adopt Motion for Reconsideration'
            : 'Adopted Committee Report';
        document.getElementById('adopt-submit-label').textContent = isMfr
            ? 'Finalize Motion for Reconsideration'
            : 'Finalize Communication';

        document.getElementById('adopt-document-id').value           = documentId;
        document.getElementById('adopt-tracking-number').textContent = trackingNumber;
        document.getElementById('adopt-resolution-number').value     = '';
        document.getElementById('adopt-proposed-number').value       = '';
        document.getElementById('adopt-approved-subject-matter').value = subjectMatter || '';
        document.getElementById('adopt-remarks').value               = '';
        adoptResetFiles();

        adoptOpen = true;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        requestAnimationFrame(function () {
            backdrop.classList.remove('opacity-0');
            panel.classList.remove('scale-95', 'opacity-0');
            panel.classList.add('scale-100', 'opacity-100');
        });
        document.getElementById('adopt-resolution-number').focus();
        document.addEventListener('keydown', handleAdoptEsc);
    };

    window.closeAdoptModal = function () {
        if (!modal || !adoptOpen) return;
        adoptOpen = false;
        backdrop.classList.add('opacity-0');
        panel.classList.remove('scale-100', 'opacity-100');
        panel.classList.add('scale-95', 'opacity-0');
        setTimeout(function () {
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }, 200);
        document.removeEventListener('keydown', handleAdoptEsc);
    };

    function handleAdoptEsc(e) {
        if (e.key === 'Escape') closeAdoptModal();
    }

    document.getElementById('adopt-comm-backdrop').addEventListener('click', closeAdoptModal);
    document.getElementById('adopt-close-btn').addEventListener('click', closeAdoptModal);
    document.getElementById('adopt-cancel-btn').addEventListener('click', closeAdoptModal);

    // ── Drag-and-drop / multi-file upload ─────────────────────────────────────

    var MAX_FILES    = 10;
    var MAX_SIZE_MB  = 25;
    var MAX_SIZE     = MAX_SIZE_MB * 1024 * 1024;
    var ALLOWED_EXTS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'webp'];

    var fileInput   = document.getElementById('adopt-resolution-files');
    var dropZone    = document.getElementById('adopt-drop-zone');
    var fileList    = document.getElementById('adopt-file-list');
    var fileError   = document.getElementById('adopt-file-error');
    var dragOverlay = document.getElementById('adopt-drag-overlay');

    // DataTransfer accumulates files across multiple browse/drop sessions.
    var dt = typeof DataTransfer === 'function' ? new DataTransfer() : null;

    function extOf(name) {
        return (name.split('.').pop() || '').toLowerCase();
    }

    function fmtSize(bytes) {
        if (bytes >= 1024 * 1024) { return (bytes / (1024 * 1024)).toFixed(1) + ' MB'; }
        return Math.round(bytes / 1024) + ' KB';
    }

    function showFileError(msg) {
        fileError.textContent = msg;
        fileError.classList.remove('hidden');
    }

    function clearFileError() {
        fileError.textContent = '';
        fileError.classList.add('hidden');
    }

    function renderFileList() {
        fileList.innerHTML = '';
        var files = dt ? dt.files : fileInput.files;
        if (files.length === 0) {
            fileList.classList.add('hidden');
            return;
        }
        fileList.classList.remove('hidden');

        Array.from(files).forEach(function (file, idx) {
            var li = document.createElement('li');
            li.className = 'flex items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm';
            li.dataset.fileIdx = idx;

            var left = document.createElement('div');
            left.className = 'flex items-center gap-2 min-w-0';

            var icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            icon.setAttribute('class', 'h-4 w-4 shrink-0 text-gray-400');
            icon.setAttribute('fill', 'none');
            icon.setAttribute('stroke', 'currentColor');
            icon.setAttribute('viewBox', '0 0 24 24');
            icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>';

            var nameSpan = document.createElement('span');
            nameSpan.className = 'truncate text-gray-800';
            nameSpan.textContent = file.name;

            var sizeSpan = document.createElement('span');
            sizeSpan.className = 'shrink-0 text-xs text-gray-400 ml-1';
            sizeSpan.textContent = fmtSize(file.size);

            left.appendChild(icon);
            left.appendChild(nameSpan);
            left.appendChild(sizeSpan);

            li.appendChild(left);
            if (dt) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'shrink-0 rounded-lg p-1 text-gray-400 hover:bg-red-50 hover:text-red-500 transition';
                btn.setAttribute('aria-label', 'Remove ' + file.name);
                btn.innerHTML = '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';
                btn.addEventListener('click', function () { removeAdoptFile(idx); });
                li.appendChild(btn);
            }
            fileList.appendChild(li);
        });
    }

    function removeAdoptFile(idx) {
        if (!dt) {
            fileInput.value = '';
            clearFileError();
            renderFileList();
            return;
        }
        var updated = new DataTransfer();
        Array.from(dt.files).forEach(function (f, i) {
            if (i !== idx) { updated.items.add(f); }
        });
        dt = updated;
        fileInput.files = dt.files;
        clearFileError();
        renderFileList();
    }

    function addAdoptFiles(incoming) {
        clearFileError();
        var candidates = Array.from(incoming);
        var existing   = dt ? Array.from(dt.files) : [];
        var existingKeys = new Set(existing.map(function (f) { return f.name + ':' + f.size; }));

        var rejected = [];
        var accepted = [];

        candidates.forEach(function (file) {
            var ext = extOf(file.name);
            var key = file.name + ':' + file.size;

            if (existingKeys.has(key)) { return; }

            if (!ALLOWED_EXTS.includes(ext)) {
                rejected.push('"' + file.name + '" — type not allowed');
                return;
            }
            if (file.size > MAX_SIZE) {
                rejected.push('"' + file.name + '" — exceeds ' + MAX_SIZE_MB + ' MB');
                return;
            }
            if (existing.length + accepted.length >= MAX_FILES) {
                rejected.push('"' + file.name + '" — maximum ' + MAX_FILES + ' files reached');
                return;
            }
            accepted.push(file);
        });

        if (dt) {
            accepted.forEach(function (f) {
                dt.items.add(f);
                existing.push(f);
            });
            fileInput.files = dt.files;
        }

        if (rejected.length > 0) {
            showFileError('Some files were not added: ' + rejected.join('; ') + '.');
        }

        renderFileList();
    }

    function adoptResetFiles() {
        if (dt) {
            dt = new DataTransfer();
            fileInput.files = dt.files;
        } else {
            fileInput.value = '';
        }
        renderFileList();
        clearFileError();
    }

    // Click to browse
    dropZone.addEventListener('click', function () {
        fileInput.click();
    });
    dropZone.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); }
    });

    // Native input change (browsed files)
    fileInput.addEventListener('change', function () {
        if (fileInput.files.length > 0) {
            addAdoptFiles(fileInput.files);
        }
    });

    // Drag events
    dropZone.addEventListener('dragover', function (e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
        dropZone.classList.add('border-primary', 'bg-primary/5');
        dragOverlay.classList.remove('hidden');
    });
    dropZone.addEventListener('dragleave', function (e) {
        if (!dropZone.contains(e.relatedTarget)) {
            dropZone.classList.remove('border-primary', 'bg-primary/5');
            dragOverlay.classList.add('hidden');
        }
    });
    dropZone.addEventListener('drop', function (e) {
        e.preventDefault();
        dropZone.classList.remove('border-primary', 'bg-primary/5');
        dragOverlay.classList.add('hidden');
        if (e.dataTransfer.files.length > 0) {
            if (dt) {
                addAdoptFiles(e.dataTransfer.files);
            } else {
                showFileError('Use the file picker to attach files in this browser.');
            }
        }
    });

}());
</script>

<!-- ======================================================================
     Adopt Cases Committee Report Modal (Petitions / Complaints / Admin Cases)
     Includes mandatory drag-and-drop attachment upload area.
     ====================================================================== -->
<div
    id="adopt-cases-modal"
    class="fixed inset-0 z-[100] hidden items-center justify-center"
    role="dialog"
    aria-modal="true"
    aria-labelledby="adopt-cases-modal-title"
>
    <div id="adopt-cases-backdrop"
         class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity opacity-0"></div>

    <!-- Panel — max-h + overflow-y-auto so tall content scrolls inside the modal -->
    <div id="adopt-cases-panel"
         class="relative z-10 w-full max-w-xl mx-4 bg-white rounded-2xl shadow-2xl border border-gray-100
                transform scale-95 opacity-0 transition-all duration-200
                flex flex-col max-h-[90vh]">

        <!-- Header -->
        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 shrink-0">
            <h2 id="adopt-cases-modal-title" class="text-base font-semibold text-gray-900">
                Adopted Committee Report
            </h2>
            <button type="button" id="adopt-cases-close-btn"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition"
                    aria-label="Close">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Scrollable body -->
        <form method="POST" action="<?= BASE_URL ?>/plenary/cases-first-reading/adopt-report"
              enctype="multipart/form-data"
              class="overflow-y-auto px-6 py-5 space-y-4" id="adopt-cases-form">
            <input type="hidden" name="document_id" id="adopt-cases-document-id" value="">

            <p class="text-sm text-gray-600">
                Finalizing committee report adoption for:
                <span id="adopt-cases-tracking-number" class="font-semibold text-gray-900"></span>
            </p>

            <div class="rounded-xl border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm text-indigo-800">
                Recording the committee report adoption will <strong>finalize</strong> this document
                and move it to <strong>Finalized Documents → Petitions</strong>. The returned report copy
                will also be available on <strong>Plenary Reports</strong>.
            </div>

            <fieldset>
                <legend class="block text-sm font-medium text-gray-700 mb-2">
                    Final Output Type <span class="text-red-500" aria-hidden="true">*</span>
                </legend>
                <div class="flex flex-wrap gap-3">
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700">
                        <input type="radio" name="final_output_type" value="RESOLUTION" required checked class="h-4 w-4 accent-primary">
                        Resolution
                    </label>
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700">
                        <input type="radio" name="final_output_type" value="ORDINANCE" required class="h-4 w-4 accent-primary">
                        Ordinance
                    </label>
                </div>
                <p class="mt-1 text-xs text-gray-500">This classifies the finalized output and does not change the original document type.</p>
            </fieldset>

            <!-- Resolution + Proposed Number -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="adopt-cases-resolution-number"
                           class="block text-sm font-medium text-gray-700 mb-1">
                        Resolution/Ordinance No. <span class="text-red-500" aria-hidden="true">*</span>
                    </label>
                    <input type="text" id="adopt-cases-resolution-number" name="resolution_number"
                           maxlength="100" required
                           placeholder="e.g. SP Res. No. 2026-001 or SP Ordinance No. 2026-001"
                           class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                                  text-gray-800 placeholder-gray-400 focus:border-primary
                                  focus:outline-none focus:ring-2 focus:ring-primary/20"
                           aria-required="true">
                </div>
                <div>
                    <label for="adopt-cases-proposed-number"
                           class="block text-sm font-medium text-gray-700 mb-1">
                        Proposed Number
                        <span class="text-xs font-normal text-gray-400">(optional)</span>
                    </label>
                    <input type="text" id="adopt-cases-proposed-number" name="proposed_number"
                           maxlength="100"
                           placeholder="e.g. SP Res. No. 2026-001"
                           class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                                  text-gray-800 placeholder-gray-400 focus:border-primary
                                  focus:outline-none focus:ring-2 focus:ring-primary/20">
                </div>
            </div>

            <div>
                <label for="adopt-cases-approved-subject-matter"
                       class="block text-sm font-medium text-gray-700 mb-1">
                    Subject Matter <span class="text-red-500" aria-hidden="true">*</span>
                </label>
                <textarea id="adopt-cases-approved-subject-matter" name="approved_subject_matter"
                          rows="3" maxlength="5000" required
                          class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                                 text-gray-800 placeholder-gray-400 focus:border-primary
                                 focus:outline-none focus:ring-2 focus:ring-primary/20"></textarea>
            </div>

            <!-- ── Required attachment upload ─────────────────────────────── -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Attachment(s) <span class="text-red-500" aria-hidden="true">*</span>
                </label>
                <p class="mb-2 text-xs text-gray-500">
                    Upload the signed committee report or supporting approval documents.
                </p>

                <!-- Hidden accumulator input -->
                <input type="file"
                       id="adopt-cases-files-input"
                       name="adoption_files[]"
                       multiple
                       required
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp"
                       class="sr-only"
                       aria-label="Committee report attachment files"
                       tabindex="-1">

                <!-- Drop zone -->
                <div id="adopt-cases-drop-zone"
                     role="button"
                     tabindex="0"
                     aria-label="Drop committee report files here or click to browse"
                     class="relative flex h-32 w-full flex-col items-center justify-center gap-2
                            rounded-xl border-2 border-dashed border-gray-300 bg-gray-50
                            px-4 text-center transition hover:bg-gray-100
                            focus:outline-none focus:ring-2 focus:ring-primary/40
                            cursor-pointer">

                    <svg class="h-8 w-8 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>

                    <p class="mb-1 text-sm text-gray-600 pointer-events-none">
                        <span class="font-semibold">Click to upload</span> or drag and drop
                        <span class="text-primary underline-offset-2 hover:underline">browse</span>
                    </p>
                    <p class="text-xs text-gray-500 pointer-events-none">
                        PDF, Word, Excel, images (MAX. 25 MB each)
                    </p>

                    <div id="adopt-cases-drag-overlay"
                         class="pointer-events-none absolute inset-0 hidden rounded-xl
                                border-2 border-primary bg-primary/10"></div>
                </div>

                <!-- Selected file list -->
                <ul id="adopt-cases-file-list"
                    class="hidden mt-3 space-y-2"
                    aria-label="Selected committee report files"
                    aria-live="polite"></ul>

                <!-- Validation error message -->
                <p id="adopt-cases-file-error"
                   class="hidden mt-1 text-xs text-red-600"
                   role="alert"></p>

                <p class="mt-1 text-xs text-gray-400">
                    Supported: PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, PNG, GIF, WEBP
                </p>
            </div>

            <!-- Remarks -->
            <div>
                <label for="adopt-cases-remarks"
                       class="block text-sm font-medium text-gray-700 mb-1">
                    Remarks <span class="text-xs font-normal text-gray-400">(optional)</span>
                </label>
                <textarea id="adopt-cases-remarks" name="remarks" rows="2" maxlength="2000"
                          class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                                 text-gray-800 placeholder-gray-400 focus:border-primary
                                 focus:outline-none focus:ring-2 focus:ring-primary/20"
                          placeholder="Optional remarks…"></textarea>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                <button type="button" id="adopt-cases-cancel-btn"
                        class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium
                               text-gray-700 hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit" id="adopt-cases-submit-btn"
                        class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2 text-sm
                               font-semibold text-white hover:bg-indigo-700 transition
                               focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Finalize Document
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    'use strict';

    // ── Modal open / close ────────────────────────────────────────────────────

    var casesModal    = document.getElementById('adopt-cases-modal');
    var casesBackdrop = document.getElementById('adopt-cases-backdrop');
    var casesPanel    = document.getElementById('adopt-cases-panel');
    var casesOpen     = false;

    window.openAdoptCasesModal = function (documentId, trackingNumber, subjectMatter) {
        if (!casesModal || casesOpen) { return; }

        document.getElementById('adopt-cases-document-id').value           = documentId;
        document.getElementById('adopt-cases-tracking-number').textContent = trackingNumber;
        document.getElementById('adopt-cases-resolution-number').value     = '';
        document.getElementById('adopt-cases-proposed-number').value       = '';
        document.getElementById('adopt-cases-approved-subject-matter').value = subjectMatter || '';
        document.getElementById('adopt-cases-remarks').value               = '';
        casesResetFiles();

        casesOpen = true;
        casesModal.classList.remove('hidden');
        casesModal.classList.add('flex');
        requestAnimationFrame(function () {
            casesBackdrop.classList.remove('opacity-0');
            casesPanel.classList.remove('scale-95', 'opacity-0');
            casesPanel.classList.add('scale-100', 'opacity-100');
        });
        document.getElementById('adopt-cases-resolution-number').focus();
        document.addEventListener('keydown', handleCasesEsc);
    };

    window.closeAdoptCasesModal = function () {
        if (!casesModal || !casesOpen) { return; }
        casesOpen = false;
        casesBackdrop.classList.add('opacity-0');
        casesPanel.classList.remove('scale-100', 'opacity-100');
        casesPanel.classList.add('scale-95', 'opacity-0');
        setTimeout(function () {
            casesModal.classList.remove('flex');
            casesModal.classList.add('hidden');
        }, 200);
        document.removeEventListener('keydown', handleCasesEsc);
    };

    function handleCasesEsc(e) {
        if (e.key === 'Escape') { closeAdoptCasesModal(); }
    }

    casesBackdrop.addEventListener('click', closeAdoptCasesModal);
    document.getElementById('adopt-cases-close-btn').addEventListener('click', closeAdoptCasesModal);
    document.getElementById('adopt-cases-cancel-btn').addEventListener('click', closeAdoptCasesModal);

    // ── Drag-and-drop / multi-file upload ─────────────────────────────────────

    var CASES_MAX_FILES    = 10;
    var CASES_MAX_SIZE_MB  = 25;
    var CASES_MAX_SIZE     = CASES_MAX_SIZE_MB * 1024 * 1024;
    var CASES_ALLOWED_EXTS = ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png','gif','webp'];

    var casesFileInput   = document.getElementById('adopt-cases-files-input');
    var casesDropZone    = document.getElementById('adopt-cases-drop-zone');
    var casesFileList    = document.getElementById('adopt-cases-file-list');
    var casesFileError   = document.getElementById('adopt-cases-file-error');
    var casesDragOverlay = document.getElementById('adopt-cases-drag-overlay');

    // DataTransfer accumulates files across multiple browse / drop sessions
    var casesDt = typeof DataTransfer === 'function' ? new DataTransfer() : null;

    function casesExtOf(name) {
        return (name.split('.').pop() || '').toLowerCase();
    }

    function casesFmtSize(bytes) {
        if (bytes >= 1024 * 1024) { return (bytes / (1024 * 1024)).toFixed(1) + ' MB'; }
        return Math.round(bytes / 1024) + ' KB';
    }

    function casesShowFileError(msg) {
        casesFileError.textContent = msg;
        casesFileError.classList.remove('hidden');
    }

    function casesClearFileError() {
        casesFileError.textContent = '';
        casesFileError.classList.add('hidden');
    }

    function casesRenderFileList() {
        casesFileList.innerHTML = '';
        var files = casesDt ? casesDt.files : casesFileInput.files;
        if (files.length === 0) {
            casesFileList.classList.add('hidden');
            return;
        }
        casesFileList.classList.remove('hidden');

        Array.from(files).forEach(function (file, idx) {
            var li = document.createElement('li');
            li.className = 'flex items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm';

            var left = document.createElement('div');
            left.className = 'flex items-center gap-2 min-w-0';

            var icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            icon.setAttribute('class', 'h-4 w-4 shrink-0 text-gray-400');
            icon.setAttribute('fill', 'none');
            icon.setAttribute('stroke', 'currentColor');
            icon.setAttribute('viewBox', '0 0 24 24');
            icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>';

            var nameSpan = document.createElement('span');
            nameSpan.className = 'truncate text-gray-800';
            nameSpan.textContent = file.name;

            var sizeSpan = document.createElement('span');
            sizeSpan.className = 'shrink-0 text-xs text-gray-400 ml-1';
            sizeSpan.textContent = casesFmtSize(file.size);

            left.appendChild(icon);
            left.appendChild(nameSpan);
            left.appendChild(sizeSpan);

            li.appendChild(left);
            if (casesDt) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'shrink-0 rounded-lg p-1 text-gray-400 hover:bg-red-50 hover:text-red-500 transition';
                btn.setAttribute('aria-label', 'Remove ' + file.name);
                btn.innerHTML = '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';
                btn.addEventListener('click', function () { casesRemoveFile(idx); });
                li.appendChild(btn);
            }
            casesFileList.appendChild(li);
        });
    }

    function casesRemoveFile(idx) {
        if (!casesDt) {
            casesFileInput.value = '';
            casesClearFileError();
            casesRenderFileList();
            return;
        }
        var updated = new DataTransfer();
        Array.from(casesDt.files).forEach(function (f, i) {
            if (i !== idx) { updated.items.add(f); }
        });
        casesDt = updated;
        casesFileInput.files = casesDt.files;
        casesClearFileError();
        casesRenderFileList();
    }

    function casesAddFiles(incoming) {
        casesClearFileError();
        var candidates   = Array.from(incoming);
        var existing     = casesDt ? Array.from(casesDt.files) : [];
        var existingKeys = new Set(existing.map(function (f) { return f.name + ':' + f.size; }));

        var rejected = [];
        var accepted = [];

        candidates.forEach(function (file) {
            var ext = casesExtOf(file.name);
            var key = file.name + ':' + file.size;

            if (existingKeys.has(key)) { return; } // duplicate — skip silently

            if (!CASES_ALLOWED_EXTS.includes(ext)) {
                rejected.push('"' + file.name + '" — type not allowed');
                return;
            }
            if (file.size > CASES_MAX_SIZE) {
                rejected.push('"' + file.name + '" — exceeds ' + CASES_MAX_SIZE_MB + ' MB');
                return;
            }
            if (existing.length + accepted.length >= CASES_MAX_FILES) {
                rejected.push('"' + file.name + '" — maximum ' + CASES_MAX_FILES + ' files reached');
                return;
            }
            accepted.push(file);
        });

        if (casesDt) {
            accepted.forEach(function (f) {
                casesDt.items.add(f);
                existing.push(f);
            });
            casesFileInput.files = casesDt.files;
        }

        if (rejected.length > 0) {
            casesShowFileError('Some files were not added: ' + rejected.join('; ') + '.');
        }

        casesRenderFileList();
    }

    function casesResetFiles() {
        if (casesDt) {
            casesDt = new DataTransfer();
            casesFileInput.files = casesDt.files;
        } else {
            casesFileInput.value = '';
        }
        casesRenderFileList();
        casesClearFileError();
    }

    // Click-to-browse
    casesDropZone.addEventListener('click', function () { casesFileInput.click(); });
    casesDropZone.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); casesFileInput.click(); }
    });

    // Native input change (browsed files)
    casesFileInput.addEventListener('change', function () {
        if (casesFileInput.files.length > 0) {
            casesAddFiles(casesFileInput.files);
        }
    });

    // Drag events
    casesDropZone.addEventListener('dragover', function (e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
        casesDropZone.classList.add('border-primary', 'bg-primary/5');
        casesDragOverlay.classList.remove('hidden');
    });
    casesDropZone.addEventListener('dragleave', function (e) {
        if (!casesDropZone.contains(e.relatedTarget)) {
            casesDropZone.classList.remove('border-primary', 'bg-primary/5');
            casesDragOverlay.classList.add('hidden');
        }
    });
    casesDropZone.addEventListener('drop', function (e) {
        e.preventDefault();
        casesDropZone.classList.remove('border-primary', 'bg-primary/5');
        casesDragOverlay.classList.add('hidden');
        if (e.dataTransfer.files.length > 0) {
            if (casesDt) {
                casesAddFiles(e.dataTransfer.files);
            } else {
                casesShowFileError('Use the file picker to attach files in this browser.');
            }
        }
    });

    // Form submit guard — only resolution number is required
    document.getElementById('adopt-cases-form').addEventListener('submit', function (e) {
        var resNum = document.getElementById('adopt-cases-resolution-number').value.trim();
        if (resNum === '') {
            e.preventDefault();
            document.getElementById('adopt-cases-resolution-number').focus();
        }
    });

}());
</script>
<script>
(function () {
    var modal = document.getElementById('reportAdoptionConfirmationModal');
    var backdrop = document.getElementById('reportAdoptionConfirmationBackdrop');
    var form = document.getElementById('reportAdoptionConfirmationForm');
    var documentIdInput = document.getElementById('reportAdoptionConfirmationDocumentId');
    var decisionInput = document.getElementById('reportAdoptionConfirmationDecision');
    var message = document.getElementById('reportAdoptionConfirmationMessage');
    var trackingNumber = document.getElementById('reportAdoptionConfirmationTracking');
    var confirmButton = document.getElementById('confirmReportAdoptionConfirmation');
    var cancelButton = document.getElementById('cancelReportAdoptionConfirmation');
    var activeButton = null;
    var submitted = false;

    function closeModal() {
        if (modal.classList.contains('hidden')) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        documentIdInput.value = '';
        decisionInput.value = '';
        if (activeButton) activeButton.focus();
        activeButton = null;
    }

    document.querySelectorAll('.open-report-adoption-confirmation').forEach(function (button) {
        button.addEventListener('click', function () {
            var decision = button.dataset.decision;
            activeButton = button;
            submitted = false;
            documentIdInput.value = button.dataset.documentId;
            decisionInput.value = decision;
            trackingNumber.textContent = button.dataset.trackingNumber;
            message.textContent = decision === 'APPROVED'
                ? 'This will adopt the Committee Report and move the document to Second Reading.'
                : 'This will adopt the Committee Report without moving the document to Second Reading.';
            confirmButton.textContent = decision === 'APPROVED' ? 'Approve & Proceed' : 'Adopt Report Only';
            confirmButton.classList.toggle('bg-violet-600', decision === 'APPROVED');
            confirmButton.classList.toggle('hover:bg-violet-700', decision === 'APPROVED');
            confirmButton.classList.toggle('bg-gray-700', decision !== 'APPROVED');
            confirmButton.classList.toggle('hover:bg-gray-800', decision !== 'APPROVED');
            confirmButton.disabled = false;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            cancelButton.focus();
        });
    });

    cancelButton.addEventListener('click', closeModal);
    backdrop.addEventListener('click', closeModal);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeModal();
    });
    form.addEventListener('submit', function (event) {
        if (submitted) {
            event.preventDefault();
            return;
        }
        submitted = true;
        confirmButton.disabled = true;
        confirmButton.textContent = 'Processing…';
    });
}());
</script>
<?php
$pageModals = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

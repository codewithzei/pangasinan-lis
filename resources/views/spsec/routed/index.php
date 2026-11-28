<?php
ob_start();

$pageTitle        = $pageTitle        ?? 'Routed Documents';
$pageSubtitle     = $pageSubtitle     ?? '';
$totalRouted      = $totalRouted      ?? 0;
$plenaryCount     = $plenaryCount     ?? 0;
$committeeCount   = $committeeCount   ?? 0;
$totalRows        = $totalRows        ?? 0;
$search           = $search           ?? '';
$filterStatus     = $filterStatus     ?? '';
$filterPhase      = $filterPhase      ?? '';
$filterType       = $filterType       ?? '';
$filterTab        = $filterTab        ?? 'ongoing';
$tabCounts        = $tabCounts        ?? [];
$documents        = $documents        ?? [];
$documentStatuses = $documentStatuses ?? [];
$documentPhases   = $documentPhases   ?? [];
$documentTypes    = $documentTypes    ?? [];
$totalPages       = $totalPages       ?? 1;
$page             = $page             ?? 1;

// Helper: format destination
function formatDestination(array $doc): string
{
    $toPhase = strtoupper($doc['to_phase'] ?? '');
    $roName  = $doc['routing_option_name'] ?? '';

    if ($toPhase === 'PLENARY') {
        return 'Plenary';
    }
    if ($toPhase === 'COMMITTEE') {
        if (!empty($doc['committee_name'])) {
            return 'Committee: ' . $doc['committee_name'];
        }
        return 'Committee';
    }
    if (strcasecmp($roName, 'Noted') === 0) {
        return 'Noted';
    }
    return $toPhase !== '' ? $toPhase : '—';
}

function formatCurrentLocation(array $doc): string
{
    $phase = strtoupper($doc['current_phase'] ?? '');
    $phaseName = [
        'RECEIVING' => 'Receiving',
        'ADMIN' => 'Admin',
        'SP_SECRETARY' => 'SP Secretary',
        'PLENARY' => 'Plenary',
        'COMMITTEE' => 'Committee',
        'FINALIZED' => 'Finalized',
        'FILED' => 'Filed',
    ][$phase] ?? ($doc['current_phase'] ?? '');

    if ($phase === 'COMMITTEE' && !empty($doc['committee_name'])) {
        return 'Committee: ' . $doc['committee_name'];
    }

    return $phaseName !== '' ? $phaseName : '—';
}
?>

<div class="space-y-6">

    <!-- ── Header Banner ────────────────────────────────────────────────────── -->
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative z-10 px-6 py-7 sm:px-8">
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

    <!-- ── Statistics Cards ────────────────────────────────────────────────── -->
    <section>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">

            <!-- Total Routed -->
            <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-x-0 top-0 h-1 bg-primary"></span>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-medium text-gray-500">Total Routed</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format($totalRouted) ?></p>
                        <p class="mt-1 text-[11px] text-gray-400">SP Secretary outbound</p>
                    </div>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-primary">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </span>
                </div>
            </article>

            <!-- Plenary Destinations -->
            <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-x-0 top-0 h-1 bg-violet-500"></span>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-medium text-gray-500">Routed to Plenary</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format($plenaryCount) ?></p>
                        <p class="mt-1 text-[11px] text-gray-400">For Session Floor</p>
                    </div>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                    </span>
                </div>
            </article>

            <!-- Committee Destinations -->
            <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-x-0 top-0 h-1 bg-amber-500"></span>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-medium text-gray-500">Routed to Committee</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format($committeeCount) ?></p>
                        <p class="mt-1 text-[11px] text-gray-400">In review</p>
                    </div>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m4-4a4 4 0 100-8 4 4 0 000 8z"/>
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
            if ($filterPhase !== '') $tabQuery['current_phase'] = $filterPhase;
            if ($filterType !== '') $tabQuery['document_type'] = $filterType;
        ?>
            <a href="<?= BASE_URL ?>/spsec/routed?<?= htmlspecialchars(http_build_query($tabQuery), ENT_QUOTES, 'UTF-8') ?>"
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

    <!-- ── Documents Table Section ─────────────────────────────────────────── -->
    <section id="spsec-routed-list" class="rounded-2xl border border-gray-200 bg-white shadow-sm">
        <?php
        $responsiveInboxId = 'spsec-routed-list';
        $responsiveInboxLabels = [
            'Tracking Number', 'Subject Matter', 'Routed To', 'Current Phase',
            'Date Routed', 'Current Status', 'Routed By', 'Action',
        ];
        require __DIR__ . '/../../partials/responsive-inbox-table.php';
        ?>
        <style>
            @media (max-width: 1279px) {
                #spsec-routed-list .inbox-responsive-table tbody > tr:not(.inbox-empty-row) > td:last-child {
                    position: static;
                    display: flex;
                    width: 100%;
                    margin-top: 0.25rem;
                    padding: 0.75rem 0 0;
                    justify-content: flex-end;
                    border-top: 1px solid #f3f4f6;
                }
            }
        </style>

        <!-- Table Filters -->
        <div class="flex min-w-0 flex-col gap-4 border-b border-gray-100 px-5 py-5 md:px-6 xl:flex-row xl:items-center xl:justify-between">
            <div class="shrink-0">
                <h2 class="whitespace-nowrap font-semibold text-gray-900">Routed Documents</h2>
                <p class="mt-1 text-xs text-gray-500">
                    <?= $totalRows ?> record<?= $totalRows !== 1 ? 's' : '' ?> found
                </p>
            </div>

            <form method="GET" action="<?= BASE_URL ?>/spsec/routed"
                  class="hidden w-full min-w-0 flex-wrap items-center justify-end gap-2 xl:flex xl:flex-1">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($filterTab, ENT_QUOTES, 'UTF-8') ?>">

                <!-- Search -->
                <div class="relative w-full min-w-0 xl:w-[360px] xl:flex-none">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                           placeholder="Search tracking # or subject…"
                           class="block w-full min-w-0 rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm
                                  text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none
                                  focus:ring-2 focus:ring-primary/20">
                </div>

                <!-- Document Status Filter -->
                <select name="status"
                        class="block w-full min-w-0 rounded-xl border border-gray-200 bg-white px-2.5 py-2 text-sm text-gray-800 xl:w-[200px] xl:flex-none
                               focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">All Document Statuses</option>
                    <?php foreach ($documentStatuses as $st): ?>
                        <option value="<?= (int) $st['id'] ?>"
                            <?= ($filterStatus == $st['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($st['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Current Phase Filter -->
                <select name="current_phase"
                        class="block w-full min-w-0 rounded-xl border border-gray-200 bg-white px-2.5 py-2 text-sm text-gray-800 xl:w-[200px] xl:flex-none
                               focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">All Current Phases</option>
                    <?php foreach ($documentPhases as $phaseValue => $phaseLabel): ?>
                        <option value="<?= htmlspecialchars($phaseValue) ?>"
                            <?= ($filterPhase === $phaseValue) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($phaseLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Document Type Filter -->
                <select name="document_type"
                        class="block w-full min-w-0 rounded-xl border border-gray-200 bg-white px-2.5 py-2 text-sm text-gray-800 xl:w-[200px] xl:flex-none
                               focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">All Document Types</option>
                    <?php foreach ($documentTypes as $type): ?>
                        <option value="<?= (int) $type['id'] ?>"
                            <?= ($filterType == $type['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($type['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit"
                        class="w-auto shrink-0 justify-self-end whitespace-nowrap rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium
                               text-gray-700 hover:bg-gray-50 transition">
                    Filter
                </button>

                <?php if ($search !== '' || $filterStatus !== '' || $filterPhase !== '' || $filterType !== ''): ?>
                    <a href="<?= BASE_URL ?>/spsec/routed?tab=<?= urlencode($filterTab) ?>"
                       class="inline-flex w-auto shrink-0 justify-self-end items-center justify-center whitespace-nowrap rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium
                              text-gray-700 hover:bg-gray-50 transition text-center">
                        Clear
                    </a>
                <?php endif; ?>
            </form>

            <div class="flex min-w-0 items-center gap-2 xl:hidden">
                <form method="GET" action="<?= BASE_URL ?>/spsec/routed" class="relative min-w-0 flex-1">
                    <input type="hidden" name="tab" value="<?= htmlspecialchars($filterTab, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="status" value="<?= htmlspecialchars((string) $filterStatus, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="current_phase" value="<?= htmlspecialchars($filterPhase, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="document_type" value="<?= htmlspecialchars((string) $filterType, ENT_QUOTES, 'UTF-8') ?>">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search tracking # or subject..."
                           class="block w-full min-w-0 rounded-xl border border-gray-200 bg-white py-2.5 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </form>
                <button type="button" data-inbox-filter-target="#spsec-routed-filter-sheet" aria-label="Open filters" aria-haspopup="dialog"
                        class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 hover:bg-gray-50">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M6 12h12m-9 8h6"/></svg>
                    <?php if ($filterStatus !== '' || $filterPhase !== '' || $filterType !== ''): ?><span class="absolute right-1 top-1 h-2 w-2 rounded-full bg-primary"></span><?php endif; ?>
                </button>
            </div>
        </div>

        <div id="spsec-routed-filter-sheet" class="fixed inset-0 z-50 hidden xl:hidden">
            <button type="button" class="inbox-filter-backdrop absolute inset-0 bg-gray-950/40" aria-label="Close filters"></button>
            <form method="GET" action="<?= BASE_URL ?>/spsec/routed" role="dialog" aria-modal="true" aria-labelledby="spsec-routed-filter-title"
                  class="absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto rounded-t-2xl bg-white p-5 shadow-2xl">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($filterTab, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                <div class="mb-5 flex items-center justify-between">
                    <h3 id="spsec-routed-filter-title" class="font-semibold text-gray-900">Filters</h3>
                    <button type="button" class="inbox-filter-close rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-100">Close</button>
                </div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700" for="spsec-routed-mobile-status">Document Status</label>
                <select id="spsec-routed-mobile-status" name="status" class="mb-4 block w-full min-w-0 rounded-xl border border-gray-200 bg-white px-3 py-3 text-sm text-gray-800">
                    <option value="">All Document Statuses</option>
                    <?php foreach ($documentStatuses as $st): ?>
                        <option value="<?= (int) $st['id'] ?>" <?= $filterStatus == $st['id'] ? 'selected' : '' ?>><?= htmlspecialchars($st['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="mb-1.5 block text-sm font-medium text-gray-700" for="spsec-routed-mobile-phase">Current Phase</label>
                <select id="spsec-routed-mobile-phase" name="current_phase" class="mb-4 block w-full min-w-0 rounded-xl border border-gray-200 bg-white px-3 py-3 text-sm text-gray-800">
                    <option value="">All Current Phases</option>
                    <?php foreach ($documentPhases as $phaseValue => $phaseLabel): ?>
                        <option value="<?= htmlspecialchars($phaseValue, ENT_QUOTES, 'UTF-8') ?>" <?= $filterPhase === $phaseValue ? 'selected' : '' ?>><?= htmlspecialchars($phaseLabel) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="mb-1.5 block text-sm font-medium text-gray-700" for="spsec-routed-mobile-type">Document Type</label>
                <select id="spsec-routed-mobile-type" name="document_type" class="mb-5 block w-full min-w-0 rounded-xl border border-gray-200 bg-white px-3 py-3 text-sm text-gray-800">
                    <option value="">All Document Types</option>
                    <?php foreach ($documentTypes as $type): ?>
                        <option value="<?= (int) $type['id'] ?>" <?= $filterType == $type['id'] ? 'selected' : '' ?>><?= htmlspecialchars($type['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="grid <?= $search !== '' || $filterStatus !== '' || $filterPhase !== '' || $filterType !== '' ? 'grid-cols-2' : 'grid-cols-1' ?> gap-3">
                    <button type="submit" class="w-full rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-white hover:opacity-90">Apply Filters</button>
                    <?php if ($search !== '' || $filterStatus !== '' || $filterPhase !== '' || $filterType !== ''): ?>
                        <a href="<?= BASE_URL ?>/spsec/routed?tab=<?= urlencode($filterTab) ?>" class="flex w-full items-center justify-center rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php require __DIR__ . '/../../partials/inbox-mobile-filter-script.php'; ?>
        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="inbox-responsive-table w-full text-left text-sm">
                <thead class="border-b border-gray-100 bg-gray-50/70 text-[11px] uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-bold">Tracking Number</th>
                        <th class="px-6 py-3 font-bold">Subject Matter</th>
                        <th class="px-6 py-3 font-bold">Routed To</th>
                        <th class="px-6 py-3 font-bold">Current Phase</th>
                        <th class="px-6 py-3 font-bold">Date Routed</th>
                        <th class="px-6 py-3 font-bold">Current Status</th>
                        <th class="px-6 py-3 font-bold">Routed By</th>
                        <th class="px-6 py-3 text-right font-bold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($documents)): ?>
                        <tr class="inbox-empty-row">
                            <td colspan="8" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">No routed documents found.</p>
                                <p class="mt-1 text-xs text-gray-500">
                                    <?= ($search !== '' || $filterStatus !== '' || $filterPhase !== '' || $filterType !== '')
                                        ? 'Try adjusting your search criteria or clearing filters.'
                                        : 'Documents you route to Plenary or Committee will appear here.' ?>
                                </p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($documents as $doc): ?>
                            <?php
                            $statusColor = !empty($doc['status_badge_color']) ? $doc['status_badge_color'] : '#6B7280';
                            $destination = formatDestination($doc);
                            $currentLocation = formatCurrentLocation($doc);
                            $subjectMatter = trim((string) ($doc['subject_matter'] ?? ''));
                            $subjectMatter = preg_replace('/\s+/u', ' ', $subjectMatter) ?? $subjectMatter;
                            ?>
                            <tr class="hover:bg-gray-50/50 transition">

                                <!-- Tracking number + date received -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-900">
                                        <?= htmlspecialchars($doc['tracking_number']) ?>
                                    </div>
                                    <div class="mt-0.5 text-xs text-gray-400">
                                        Received: <?= htmlspecialchars(date('M j, Y', strtotime($doc['date_received']))) ?>
                                    </div>
                                </td>

                                <!-- Subject matter -->
                                <td class="min-w-[24rem] px-6 py-4">
                                    <div class="break-words text-sm font-medium leading-relaxed text-gray-900">
                                        <?= escape_trimmed_text($subjectMatter !== '' ? $subjectMatter : '—') ?>
                                    </div>
                                    <span class="mt-2 inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                          style="background-color: <?= htmlspecialchars($doc['document_type_badge_color'] ?? '#2563EB') ?>1a;
                                                 color: <?= htmlspecialchars($doc['document_type_badge_color'] ?? '#2563EB') ?>;">
                                        <?= htmlspecialchars($doc['document_type_name'] ?? '—') ?>
                                    </span>
                                    <?php if (!empty($doc['is_resubmission'])): ?>
                                        <span class="mt-1 inline-flex rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700">Resubmission</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Routed to: Plenary or Committee -->
                                <td class="px-6 py-4">
                                    <?php if ($doc['to_phase'] === 'PLENARY'): ?>
                                        <span class="inline-flex max-w-full items-center gap-1 rounded-lg border border-violet-200 bg-violet-50 px-2.5 py-1 text-xs font-medium text-violet-700">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                            </svg>
                                            Plenary
                                        </span>
                                    <?php elseif ($doc['to_phase'] === 'COMMITTEE'): ?>
                                        <span class="inline-flex max-w-full items-center gap-1 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m4-4a4 4 0 100-8 4 4 0 000 8z"/>
                                            </svg>
                                            <?= htmlspecialchars(!empty($doc['committee_name']) ? $doc['committee_name'] : 'Committee') ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-500 font-medium">
                                            <?= htmlspecialchars($destination) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Current location -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center rounded-lg bg-gray-100 border border-gray-200 px-2.5 py-0.5 text-xs font-medium text-gray-800">
                                        <?= htmlspecialchars($currentLocation) ?>
                                    </span>
                                </td>

                                <!-- Date routed -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 font-medium">
                                        <?= htmlspecialchars(date('M j, Y', strtotime($doc['routed_at']))) ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?= htmlspecialchars(date('g:i A', strtotime($doc['routed_at']))) ?>
                                    </div>
                                </td>

                                <!-- Current status -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                                          style="background-color: <?= htmlspecialchars($statusColor) ?>1a;
                                                 color: <?= htmlspecialchars($statusColor) ?>;">
                                        <span class="h-1.5 w-1.5 rounded-full" style="background-color: <?= htmlspecialchars($statusColor) ?>;"></span>
                                        <?= htmlspecialchars($doc['status'] ?? '—') ?>
                                    </span>
                                </td>

                                <!-- Routed by -->
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

                                <!-- Action: Details link -->
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <a href="<?= BASE_URL ?>/spsec/routed/show?id=<?= (int)$doc['id'] ?>"
                                       class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:border-primary hover:bg-blue-50 hover:text-primary"
                                       title="View Details" aria-label="View routed document details">
                                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7
                                                     -1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        <span>View</span>
                                    </a>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ── Pagination ──────────────────────────────────────────────────────── -->
        <div class="flex flex-col items-center justify-between gap-3 border-t border-gray-100 px-6 py-4 sm:flex-row">
            <p class="text-xs text-gray-500">
                Showing page <span class="font-medium text-gray-700"><?= $page ?></span> of
                <span class="font-medium text-gray-700"><?= $totalPages ?></span>
                (<?= $totalRows ?> total records)
            </p>
            <div class="flex items-center gap-1">
                <?php
                $qArgs = [];
                if ($search !== '')       $qArgs['search']         = $search;
                if ($filterStatus !== '') $qArgs['status']         = $filterStatus;
                if ($filterPhase !== '')  $qArgs['current_phase']  = $filterPhase;
                if ($filterType !== '')   $qArgs['document_type']  = $filterType;
                $qArgs['tab'] = $filterTab;
                $qs = !empty($qArgs) ? '&' . http_build_query($qArgs) : '';
                ?>
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $qs ?>"
                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
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

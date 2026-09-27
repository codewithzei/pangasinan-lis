<?php
/**
 * Committee — Hearing Index (tabbed, table layout)
 *
 * Variables supplied by CommitteeHearingController::index():
 *   $tab         string   Active tab key: all|scheduled|approved|deferred|remanded|withdrawn
 *   $documents   array    Paginated hearing rows
 *   $total       int
 *   $totalPages  int
 *   $page        int
 *   $perPage     int
 *   $search      string
 *   $counts      array    {all, scheduled, approved, deferred, remanded, withdrawn}
 *   $success     string|null
 *   $error       string|null
 */

$tab        = $tab        ?? 'all';
$documents  = $documents  ?? [];
$total      = $total      ?? 0;
$totalPages = $totalPages ?? 1;
$page       = $page       ?? 1;
$perPage    = $perPage    ?? 20;
$search     = $search     ?? '';
$counts     = $counts     ?? ['all' => 0, 'scheduled' => 0, 'approved' => 0, 'deferred' => 0, 'remanded' => 0, 'withdrawn' => 0];
$success    = $success    ?? null;
$error      = $error      ?? null;

// Tab configuration: key => [label, inactive badge class]
$tabConfig = [
    'all'       => ['All Hearings', 'bg-gray-100 text-gray-600'],
    'scheduled' => ['Scheduled',    'bg-amber-100 text-amber-700'],
    'approved'  => ['Approved',     'bg-emerald-100 text-emerald-700'],
    'deferred'  => ['Deferred',     'bg-amber-100 text-amber-700'],
    'remanded'  => ['Remanded',     'bg-red-100 text-red-700'],
    'withdrawn' => ['Withdrawn',    'bg-gray-100 text-gray-600'],
];

// Outcome badge styles
$outcomeBadge = [
    'APPROVED'  => 'bg-emerald-100 text-emerald-800',
    'DEFERRED'  => 'bg-amber-100 text-amber-800',
    'REMANDED'  => 'bg-red-100 text-red-800',
    'WITHDRAWN' => 'bg-gray-100 text-gray-700',
];

/**
 * Build a pagination-preserving URL.
 * Omits `tab` when 'all', omits `search` when empty, omits `page` when 1.
 */
function hearingUrl(string $newTab = '', string $newSearch = '', int $newPage = 1): string
{
    $params = [];
    if ($newTab    !== '' && $newTab    !== 'all') $params['tab']    = $newTab;
    if ($newSearch !== '')                          $params['search'] = $newSearch;
    if ($newPage   > 1)                             $params['page']   = $newPage;
    $qs = $params ? ('?' . http_build_query($params)) : '';
    return BASE_URL . '/committee/hearing' . $qs;
}

// Count eligible (approved, no existing report) docs on this page — used by bulk toolbar
$eligibleCount = 0;
if ($tab === 'approved') {
    foreach ($documents as $d) {
        if (($d['hearing_outcome'] ?? null) === 'APPROVED' && empty($d['report_id'])) {
            $eligibleCount++;
        }
    }
}

ob_start();
?>

<div class="space-y-6">

    <!-- ── Page header ───────────────────────────────────────────────────── -->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-7 sm:px-8">
            <div class="relative z-10">
                <p class="text-sm font-medium text-blue-100">COMMITTEE / HEARINGS</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    Committee Hearings
                </h1>
                <p class="mt-1 text-sm text-blue-100">
                    Manage hearing outcomes for documents that have been scheduled for committee hearing.
                </p>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-56 w-56 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
        </div>
    </section>

    <!-- ── Flash messages ────────────────────────────────────────────────── -->
    <?php if ($success): ?>
        <div class="flex items-start gap-3 rounded-2xl border border-green-200 bg-green-50 p-4" role="alert">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <p class="text-sm font-medium text-green-800"><?= htmlspecialchars($success) ?></p>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4" role="alert">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            <div class="text-sm text-red-800">
                <p class="font-semibold"><?= htmlspecialchars($error) ?></p>
                <?php $flashErrors = flash_get('errors') ?? []; if (!empty($flashErrors)): ?>
                    <ul class="mt-1 list-disc pl-4 space-y-0.5">
                        <?php foreach ($flashErrors as $fe): ?>
                            <li><?= htmlspecialchars($fe) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ── Tab navigation ────────────────────────────────────────────────── -->
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-1 overflow-x-auto" aria-label="Hearing tabs">
            <?php foreach ($tabConfig as $key => [$label, $badgeClass]): ?>
                <a href="<?= hearingUrl($key, $search) ?>"
                   class="group inline-flex shrink-0 items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium
                          transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500
                          <?= $tab === $key
                              ? 'border-blue-600 text-blue-700'
                              : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' ?>"
                   <?= $tab === $key ? 'aria-current="page"' : '' ?>>
                    <?= htmlspecialchars($label) ?>
                    <?php if (($counts[$key] ?? 0) > 0): ?>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold
                                     <?= $tab === $key ? 'bg-blue-100 text-blue-700' : $badgeClass ?>">
                            <?= number_format($counts[$key]) ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <!-- ── Main list card ────────────────────────────────────────────────── -->
    <section class="rounded-2xl border border-gray-200 bg-white">

        <!-- Toolbar: title + record count + search -->
        <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900"><?= htmlspecialchars($tabConfig[$tab][0] ?? 'Hearings') ?></h2>
                <p class="mt-1 text-xs text-gray-500">
                    <?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?> found
                </p>
            </div>

            <form method="GET" action="<?= BASE_URL ?>/committee/hearing"
                  class="flex flex-col gap-3 sm:flex-row sm:items-center" role="search">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="search" id="hearingSearch"
                           value="<?= htmlspecialchars($search) ?>"
                           placeholder="Search tracking number or subject…"
                           aria-label="Search hearings"
                           class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm
                                  text-gray-800 placeholder-gray-400
                                  focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20
                                  sm:w-72">
                </div>
                <button type="submit"
                        class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium
                               text-gray-700 hover:bg-gray-50 transition">
                    Search
                </button>
                <?php if ($search !== ''): ?>
                    <a href="<?= hearingUrl($tab) ?>"
                       class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium
                              text-gray-700 hover:bg-gray-50 transition text-center"
                       aria-label="Clear search">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Approved-tab bulk-selection toolbar -->
        <?php if ($tab === 'approved' && !empty($documents)): ?>
            <div id="approvedToolbar"
                 class="flex flex-col gap-3 border-b border-emerald-100 bg-emerald-50 px-6 py-3
                        sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-emerald-800 select-none
                                  <?= $eligibleCount === 0 ? 'opacity-40 cursor-not-allowed' : '' ?>">
                        <input type="checkbox" id="selectAllApproved"
                               class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                               aria-label="Select all eligible approved documents on this page"
                               <?= $eligibleCount === 0 ? 'disabled' : '' ?>>
                        Select All Eligible
                        <?php if ($eligibleCount > 0): ?>
                            <span class="ml-1 rounded-full bg-emerald-200 px-1.5 py-0.5 text-xs font-semibold text-emerald-800">
                                <?= $eligibleCount ?>
                            </span>
                        <?php endif; ?>
                    </label>
                    <span id="selectionCounter"
                          class="rounded-full bg-emerald-600 px-2.5 py-0.5 text-xs font-semibold text-white hidden"
                          aria-live="polite">
                        0 selected
                    </span>
                </div>
                <a href="#" id="openSharePageBtn"
                   class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2
                          text-sm font-semibold text-white hover:bg-emerald-700 transition
                          focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500
                          opacity-50 pointer-events-none"
                   aria-disabled="true"
                   role="button">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                    </svg>
                    Share to Committee Report
                </a>
            </div>
        <?php endif; ?>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <?php if ($tab === 'approved'): ?>
                            <th class="px-4 py-3 font-medium w-10"></th>
                        <?php endif; ?>
                        <th class="px-6 py-3 font-medium">Document</th>
                        <th class="px-6 py-3 font-medium">Outcome / Status</th>
                        <th class="px-6 py-3 font-medium">Subject</th>
                        <th class="px-6 py-3 font-medium">Agenda</th>
                        <th class="px-6 py-3 font-medium">Committees</th>
                        <th class="px-6 py-3 font-medium">Recorded By</th>
                        <th class="px-6 py-3 font-medium">Report</th>
                        <th class="px-6 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">

                    <?php if (empty($documents)): ?>
                        <!-- Empty state -->
                        <tr>
                            <td colspan="<?= $tab === 'approved' ? 9 : 8 ?>"
                                class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2
                                                 M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">
                                    <?php if ($search !== ''): ?>
                                        No results match your search.
                                    <?php elseif ($tab === 'scheduled'): ?>
                                        No scheduled hearings.
                                    <?php elseif ($tab === 'approved'): ?>
                                        No approved hearings.
                                    <?php elseif ($tab === 'deferred'): ?>
                                        No deferred hearings.
                                    <?php elseif ($tab === 'remanded'): ?>
                                        No remanded hearings.
                                    <?php elseif ($tab === 'withdrawn'): ?>
                                        No withdrawn hearings.
                                    <?php else: ?>
                                        No hearings found.
                                    <?php endif; ?>
                                </p>
                                <p class="mt-1 text-xs text-gray-500">
                                    <?php if ($search !== ''): ?>
                                        Try a different search term or
                                        <a href="<?= hearingUrl($tab) ?>" class="text-blue-600 hover:underline">clear the search</a>.
                                    <?php elseif ($tab === 'scheduled'): ?>
                                        Documents will appear here once an agenda is scheduled and their status is <strong>On Going</strong>.
                                    <?php elseif ($tab === 'all'): ?>
                                        Documents with a scheduled agenda will appear here.
                                    <?php else: ?>
                                        No hearings with this outcome have been recorded yet.
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>

                    <?php else: ?>
                        <?php foreach ($documents as $doc): ?>
                            <?php
                            // ── Derived flags ─────────────────────────────────────────────
                            $outcomeLabel   = $doc['hearing_outcome'] ?? null;
                            $outcomeCls     = $outcomeBadge[$outcomeLabel] ?? 'bg-gray-100 text-gray-700';
                            $outcomeDisplay = $outcomeLabel ? ucfirst(strtolower($outcomeLabel)) : null;
                            $isScheduled    = ($outcomeLabel === null);
                            $isApproved     = ($outcomeLabel === 'APPROVED');
                            $hasReport      = $isApproved && !empty($doc['report_id']);
                            $isEligible     = $isApproved && empty($doc['report_id']);

                            // ── Formatted dates ───────────────────────────────────────────
                            $agendaDate = !empty($doc['agenda_date'])
                                ? date('M j, Y', strtotime($doc['agenda_date'])) : '—';
                            $agendaTime = !empty($doc['agenda_time'])
                                ? date('g:i A', strtotime($doc['agenda_time'])) : '';
                            $outcomeDate = !empty($doc['outcome_date'])
                                ? date('M j, Y', strtotime($doc['outcome_date'])) : '';
                            ?>
                            <tr class="hover:bg-gray-50/50 transition
                                       <?= $isApproved ? 'approved-doc-row' : '' ?>
                                       <?= $hasReport  ? 'opacity-75' : '' ?>"
                                <?php if ($isApproved): ?>
                                    data-doc-id="<?= (int) $doc['document_id'] ?>"
                                    data-tracking="<?= htmlspecialchars($doc['tracking_number'] ?? '') ?>"
                                    data-has-report="<?= $hasReport ? '1' : '0' ?>"
                                <?php endif; ?>>

                                <!-- ── Checkbox (approved tab only) ───────────────────── -->
                                <?php if ($tab === 'approved'): ?>
                                    <td class="px-4 py-4">
                                        <?php if ($isApproved): ?>
                                            <div title="<?= $hasReport ? 'Already assigned to a Committee Report' : '' ?>">
                                                <input type="checkbox"
                                                       class="doc-checkbox h-4 w-4 rounded border-gray-300 text-emerald-600
                                                              focus:ring-emerald-500
                                                              <?= $isEligible ? 'cursor-pointer' : 'cursor-not-allowed opacity-40' ?>"
                                                       value="<?= (int) $doc['document_id'] ?>"
                                                       <?= $hasReport ? 'disabled' : '' ?>
                                                       aria-label="Select document <?= htmlspecialchars($doc['tracking_number'] ?? '') ?>"
                                                       aria-disabled="<?= $hasReport ? 'true' : 'false' ?>">
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>

                                <!-- ── Document: tracking number + type badge ──────────── -->
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-1">
                                        <span class="font-mono text-sm font-bold text-blue-700">
                                            <?= htmlspecialchars($doc['tracking_number'] ?? '—') ?>
                                        </span>
                                        <?php if (!empty($doc['document_type_name'])): ?>
                                            <span class="inline-flex w-fit items-center rounded-full px-2 py-0.5
                                                         text-xs font-medium text-white"
                                                  style="background-color:<?= htmlspecialchars($doc['document_type_badge_color'] ?? '#6B7280') ?>">
                                                <?= htmlspecialchars($doc['document_type_name']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- ── Outcome / document status ──────────────────────── -->
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-1.5">
                                        <?php if ($isScheduled): ?>
                                            <span class="inline-flex w-fit items-center gap-1 rounded-full
                                                         bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700">
                                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse" aria-hidden="true"></span>
                                                Scheduled
                                            </span>
                                        <?php elseif ($outcomeDisplay): ?>
                                            <span class="inline-flex w-fit items-center rounded-full px-2.5 py-0.5
                                                         text-xs font-semibold <?= $outcomeCls ?>">
                                                <?= htmlspecialchars($outcomeDisplay) ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($doc['status'])): ?>
                                            <span class="inline-flex w-fit items-center rounded-full px-2 py-0.5
                                                         text-xs font-medium text-white"
                                                  style="background-color:<?= htmlspecialchars($doc['status_badge_color'] ?? '#6B7280') ?>">
                                                <?= htmlspecialchars($doc['status']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- ── Subject matter ─────────────────────────────────── -->
                                <td class="px-6 py-4">
                                    <p class="max-w-xs text-sm text-gray-800 line-clamp-2">
                                        <?= htmlspecialchars($doc['subject_matter'] ?? '—') ?>
                                    </p>
                                </td>

                                <!-- ── Agenda date / time / venue ─────────────────────── -->
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-0.5 text-xs text-gray-600 whitespace-nowrap">
                                        <span class="flex items-center gap-1">
                                            <svg class="h-3.5 w-3.5 shrink-0 text-gray-400" fill="none" stroke="currentColor"
                                                 viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                            <?= htmlspecialchars($agendaDate) ?>
                                        </span>
                                        <?php if ($agendaTime !== ''): ?>
                                            <span class="pl-4 text-gray-400"><?= htmlspecialchars($agendaTime) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($doc['venue'])): ?>
                                            <span class="flex items-center gap-1 text-gray-500 mt-0.5">
                                                <svg class="h-3.5 w-3.5 shrink-0 text-gray-400" fill="none" stroke="currentColor"
                                                     viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                </svg>
                                                <span class="line-clamp-1 max-w-[10rem]"><?= htmlspecialchars($doc['venue']) ?></span>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- ── Committee names ────────────────────────────────── -->
                                <td class="px-6 py-4">
                                    <?php if (!empty($doc['committee_names'])): ?>
                                        <span class="text-xs text-gray-600 line-clamp-2 max-w-[12rem]">
                                            <?= htmlspecialchars($doc['committee_names']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- ── Recorded by / outcome date ─────────────────────── -->
                                <td class="px-6 py-4">
                                    <?php if (!empty($doc['outcome_by']) || $outcomeDate !== ''): ?>
                                        <div class="flex flex-col gap-0.5 text-xs text-gray-500 whitespace-nowrap">
                                            <?php if (!empty($doc['outcome_by'])): ?>
                                                <span class="font-medium text-gray-700">
                                                    <?= htmlspecialchars($doc['outcome_by']) ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($outcomeDate !== ''): ?>
                                                <span><?= htmlspecialchars($outcomeDate) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- ── Committee report ───────────────────────────────── -->
                                <td class="px-6 py-4">
                                    <?php if ($hasReport && !empty($doc['report_number'])): ?>
                                        <div class="flex flex-col gap-1">
                                            <span class="inline-flex w-fit items-center gap-1 rounded-full
                                                         bg-emerald-50 border border-emerald-200 px-2 py-0.5
                                                         text-xs font-semibold text-emerald-700">
                                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                </svg>
                                                #<?= htmlspecialchars($doc['report_number']) ?>
                                            </span>
                                            <?php if (!empty($doc['report_type'])): ?>
                                                <span class="text-[10px] text-gray-400 leading-tight max-w-[9rem]">
                                                    <?= htmlspecialchars(str_replace('_', ' ', $doc['report_type'])) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif ($hasReport): ?>
                                        <span class="inline-flex w-fit items-center gap-1 rounded-full
                                                     bg-orange-50 border border-orange-200 px-2 py-0.5
                                                     text-xs font-semibold text-orange-700">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                            </svg>
                                            Assigned
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- ── Actions ────────────────────────────────────────── -->
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1 flex-wrap">

                                        <?php if ($isScheduled): ?>
                                            <!-- Record Outcome (primary CTA for scheduled) -->
                                            <a href="<?= BASE_URL ?>/committee/hearing/show?id=<?= (int) $doc['document_id'] ?>"
                                               class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-3 py-1.5
                                                      text-xs font-semibold text-white hover:bg-blue-700 transition
                                                      focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500
                                                      whitespace-nowrap">
                                                Record Outcome
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                </svg>
                                            </a>

                                        <?php elseif ($isApproved && empty($doc['report_id'])): ?>
                                            <!-- View + Create Report -->
                                            <a href="<?= BASE_URL ?>/committee/hearing/show?id=<?= (int) $doc['document_id'] ?>"
                                               class="rounded-lg border border-gray-200 p-1.5 text-gray-500
                                                      hover:border-blue-400 hover:bg-blue-50 hover:text-blue-600 transition"
                                               title="View Hearing">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </a>
                                            <?php if (!empty($doc['hearing_id'])): ?>
                                                <a href="<?= BASE_URL ?>/committee/hearing/report?document_id=<?= (int) $doc['document_id'] ?>&hearing_id=<?= (int) $doc['hearing_id'] ?>"
                                                   class="rounded-lg border border-gray-200 p-1.5 text-gray-500
                                                          hover:border-emerald-400 hover:bg-emerald-50 hover:text-emerald-600 transition"
                                                   title="Create Report">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                    </svg>
                                                </a>
                                            <?php endif; ?>

                                        <?php elseif ($isApproved && !empty($doc['report_id'])): ?>
                                            <!-- View Hearing + View Report -->
                                            <a href="<?= BASE_URL ?>/committee/hearing/show?id=<?= (int) $doc['document_id'] ?>"
                                               class="rounded-lg border border-gray-200 p-1.5 text-gray-500
                                                      hover:border-blue-400 hover:bg-blue-50 hover:text-blue-600 transition"
                                               title="View Hearing">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </a>
                                            <a href="<?= BASE_URL ?>/committee/hearing/report/show?id=<?= (int) $doc['report_id'] ?>"
                                               class="rounded-lg border border-emerald-200 p-1.5 text-emerald-600
                                                      hover:border-emerald-400 hover:bg-emerald-50 transition"
                                               title="View Report">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                </svg>
                                            </a>

                                        <?php else: ?>
                                            <!-- View Details (deferred / remanded / withdrawn) -->
                                            <a href="<?= BASE_URL ?>/committee/hearing/show?id=<?= (int) $doc['document_id'] ?>"
                                               class="rounded-lg border border-gray-200 p-1.5 text-gray-500
                                                      hover:border-blue-400 hover:bg-blue-50 hover:text-blue-600 transition"
                                               title="View Details">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </a>
                                        <?php endif; ?>

                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </tbody>
            </table>
        </div>

        <!-- ── Pagination ─────────────────────────────────────────────────── -->
        <?php if ($totalPages > 1): ?>
            <div class="flex flex-col items-center justify-between gap-3 border-t border-gray-100 px-6 py-4 sm:flex-row">
                <p class="text-xs text-gray-500">
                    Showing page
                    <span class="font-medium text-gray-700"><?= $page ?></span>
                    of
                    <span class="font-medium text-gray-700"><?= $totalPages ?></span>
                    (<?= number_format($total) ?> total records)
                </p>
                <nav class="flex items-center gap-1" aria-label="Pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?= hearingUrl($tab, $search, $page - 1) ?>"
                           class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium
                                  text-gray-700 hover:bg-gray-50 transition"
                           aria-label="Previous page">
                            Prev
                        </a>
                    <?php endif; ?>

                    <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                        <a href="<?= hearingUrl($tab, $search, $p) ?>"
                           class="rounded-lg border px-3 py-1.5 text-sm font-medium transition
                                  <?= $p === $page
                                      ? 'border-primary bg-primary text-white'
                                      : 'border-gray-200 text-gray-700 hover:bg-gray-50' ?>"
                           <?= $p === $page ? 'aria-current="page"' : '' ?>>
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="<?= hearingUrl($tab, $search, $page + 1) ?>"
                           class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium
                                  text-gray-700 hover:bg-gray-50 transition"
                           aria-label="Next page">
                            Next
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>

    </section>

</div>


<!-- ═══════════════════════════════════════════════════════════════════════════
     JAVASCRIPT — approved-tab multi-select + navigate to share page
     ═══════════════════════════════════════════════════════════════════════════ -->
<?php if ($tab === 'approved' && !empty($documents)): ?>
<script>
(function () {
    'use strict';

    const BASE_URL         = '<?= rtrim(BASE_URL, '/') ?>';
    const selectAllCb      = document.getElementById('selectAllApproved');
    const selectionCounter = document.getElementById('selectionCounter');
    const shareBtn         = document.getElementById('openSharePageBtn');

    /* ── Helpers ─────────────────────────────────────────────────────────── */

    function getDocCheckboxes() {
        return Array.from(document.querySelectorAll('.doc-checkbox'));
    }

    function getEligibleCheckboxes() {
        return getDocCheckboxes().filter(function (cb) { return !cb.disabled; });
    }

    function getCheckedCheckboxes() {
        return getDocCheckboxes().filter(function (cb) { return cb.checked && !cb.disabled; });
    }

    /* ── Update toolbar UI after any checkbox change ─────────────────────── */

    function updateSelectionUI() {
        var checked  = getCheckedCheckboxes();
        var eligible = getEligibleCheckboxes();
        var count    = checked.length;

        // Counter badge
        if (count > 0) {
            selectionCounter.textContent = count + ' document' + (count !== 1 ? 's' : '') + ' selected';
            selectionCounter.classList.remove('hidden');
        } else {
            selectionCounter.classList.add('hidden');
        }

        // Share button — enable and set href when at least one doc is checked
        if (count > 0) {
            var ids = checked.map(function (cb) {
                return 'document_ids%5B%5D=' + encodeURIComponent(cb.value);
            });
            var url = BASE_URL + '/committee/hearing/share-to-report?' + ids.join('&');
            shareBtn.setAttribute('href', url);
            shareBtn.removeAttribute('aria-disabled');
            shareBtn.classList.remove('opacity-50', 'pointer-events-none');
        } else {
            shareBtn.setAttribute('href', '#');
            shareBtn.setAttribute('aria-disabled', 'true');
            shareBtn.classList.add('opacity-50', 'pointer-events-none');
        }

        // Select-all indeterminate state
        var eligibleTotal   = eligible.length;
        var eligibleChecked = eligible.filter(function (cb) { return cb.checked; }).length;

        if (eligibleTotal === 0 || eligibleChecked === 0) {
            selectAllCb.checked       = false;
            selectAllCb.indeterminate = false;
        } else if (eligibleChecked === eligibleTotal) {
            selectAllCb.checked       = true;
            selectAllCb.indeterminate = false;
        } else {
            selectAllCb.checked       = false;
            selectAllCb.indeterminate = true;
        }

        // Highlight selected rows
        getDocCheckboxes().forEach(function (cb) {
            var row = cb.closest('.approved-doc-row');
            if (!row) return;
            if (cb.checked && !cb.disabled) {
                row.classList.add('bg-emerald-50', 'ring-1', 'ring-inset', 'ring-emerald-300');
            } else {
                row.classList.remove('bg-emerald-50', 'ring-1', 'ring-inset', 'ring-emerald-300');
            }
        });
    }

    /* ── Wire per-document checkboxes ────────────────────────────────────── */

    getDocCheckboxes().forEach(function (cb) {
        cb.addEventListener('change', updateSelectionUI);
    });

    /* ── Select All ──────────────────────────────────────────────────────── */

    if (selectAllCb) {
        selectAllCb.addEventListener('change', function () {
            getEligibleCheckboxes().forEach(function (cb) {
                cb.checked = selectAllCb.checked;
            });
            updateSelectionUI();
        });
    }

    /* ── Click anywhere on an approved eligible row to toggle checkbox ───── */

    document.querySelectorAll('.approved-doc-row').forEach(function (row) {
        row.addEventListener('click', function (e) {
            if (e.target.closest('a, button, input')) return;
            var cb = row.querySelector('.doc-checkbox');
            if (cb && !cb.disabled) {
                cb.checked = !cb.checked;
                updateSelectionUI();
            }
        });
        // Pointer cursor for eligible rows
        var cb = row.querySelector('.doc-checkbox');
        if (cb && !cb.disabled) {
            row.style.cursor = 'pointer';
        }
    });

    /* ── Prevent navigation when share button is aria-disabled ──────────── */

    if (shareBtn) {
        shareBtn.addEventListener('click', function (e) {
            if (this.getAttribute('aria-disabled') === 'true') {
                e.preventDefault();
            }
        });
    }

    // Apply initial state
    updateSelectionUI();

}());
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

<?php
/**
 * Committee — Hearing Index (tabbed)
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

// Tab configuration: key => [label, badge_class]
// NOTE: inner arrays are positionally indexed (0 = label, 1 = badge_class)
// so that the foreach [$label, $badgeClass] destructuring resolves correctly.
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

// Build a pagination-preserving URL
function hearingUrl(string $newTab = '', string $newSearch = '', int $newPage = 1): string
{
    $params = [];
    if ($newTab    !== '' && $newTab    !== 'all') $params['tab']    = $newTab;
    if ($newSearch !== '')                          $params['search'] = $newSearch;
    if ($newPage   > 1)                            $params['page']   = $newPage;
    $qs = $params ? ('?' . http_build_query($params)) : '';
    return BASE_URL . '/committee/hearing' . $qs;
}

ob_start();
?>

<div class="space-y-6">

    <!-- ── Page header ───────────────────────────────────────────────────── -->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-blue-700 via-blue-600 to-indigo-700 shadow-md">
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
            <!-- Decorative circle -->
            <div class="pointer-events-none absolute -right-10 -top-20 h-56 w-56 rounded-full bg-white/10"></div>
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
            <p class="text-sm font-medium text-red-800"><?= htmlspecialchars($error) ?></p>
        </div>
    <?php endif; ?>

    <!-- ── Counter summary row ───────────────────────────────────────────── -->
    <div class="grid grid-cols-3 gap-3 sm:grid-cols-6">
        <?php foreach ($tabConfig as $key => [$label, $badgeClass]): ?>
            <a href="<?= hearingUrl($key, $search) ?>"
               class="flex flex-col items-center justify-center rounded-2xl border px-3 py-4 text-center
                      transition hover:shadow-sm
                      <?= $tab === $key
                          ? 'border-blue-300 bg-blue-50 shadow-sm'
                          : 'border-gray-200 bg-white hover:border-gray-300' ?>">
                <span class="text-xl font-bold <?= $tab === $key ? 'text-blue-700' : 'text-gray-800' ?>">
                    <?= number_format($counts[$key] ?? 0) ?>
                </span>
                <span class="mt-0.5 text-xs font-medium <?= $tab === $key ? 'text-blue-600' : 'text-gray-500' ?>">
                    <?= htmlspecialchars($label) ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

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

    <!-- ── Search bar ────────────────────────────────────────────────────── -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" action="<?= BASE_URL ?>/committee/hearing"
              class="flex items-center gap-2 w-full sm:max-w-sm" role="search">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" name="search" id="hearingSearch"
                       value="<?= htmlspecialchars($search) ?>"
                       placeholder="Search tracking number or subject…"
                       aria-label="Search hearings"
                       class="block w-full rounded-xl border border-gray-200 py-2 pl-9 pr-3 text-sm
                              focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
            <button type="submit"
                    class="shrink-0 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white
                           hover:bg-blue-700 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                Search
            </button>
            <?php if ($search !== ''): ?>
                <a href="<?= hearingUrl($tab) ?>"
                   class="shrink-0 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium
                          text-gray-600 hover:bg-gray-50 transition"
                   aria-label="Clear search">
                    Clear
                </a>
            <?php endif; ?>
        </form>

        <p class="text-sm text-gray-500 shrink-0" aria-live="polite">
            <?= number_format($total) ?> hearing<?= $total !== 1 ? 's' : '' ?>
        </p>
    </div>

    <!-- ── Document list ─────────────────────────────────────────────────── -->
    <?php if (empty($documents)): ?>
        <!-- Empty state -->
        <div class="rounded-2xl border border-gray-200 bg-white p-12 text-center" role="status">
            <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <p class="mt-4 text-sm font-semibold text-gray-500">
                <?php if ($search !== ''): ?>
                    No results match your search
                <?php elseif ($tab === 'scheduled'): ?>
                    No scheduled hearings
                <?php elseif ($tab === 'approved'): ?>
                    No approved hearings
                <?php elseif ($tab === 'deferred'): ?>
                    No deferred hearings
                <?php elseif ($tab === 'remanded'): ?>
                    No remanded hearings
                <?php elseif ($tab === 'withdrawn'): ?>
                    No withdrawn hearings
                <?php else: ?>
                    No hearings found
                <?php endif; ?>
            </p>
            <p class="mt-1 text-xs text-gray-400">
                <?php if ($search !== ''): ?>
                    Try a different search term or
                    <a href="<?= hearingUrl($tab) ?>" class="text-blue-600 hover:underline">clear the search</a>.
                <?php elseif ($tab === 'scheduled'): ?>
                    Documents will appear here once an agenda is scheduled and their status is set to <strong>On Going</strong>.
                <?php elseif ($tab === 'all'): ?>
                    Documents with a scheduled agenda will appear here.
                <?php else: ?>
                    No hearings with this outcome have been recorded yet.
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <div class="space-y-3" role="list">
            <?php foreach ($documents as $doc): ?>
                <?php
                $agendaDate     = !empty($doc['agenda_date'])
                    ? date('M j, Y', strtotime($doc['agenda_date'])) : '—';
                $agendaTime     = !empty($doc['agenda_time'])
                    ? date('g:i A', strtotime($doc['agenda_time'])) : '';
                $outcomeLabel   = $doc['hearing_outcome'] ?? null;
                $outcomeCls     = $outcomeBadge[$outcomeLabel] ?? 'bg-gray-100 text-gray-700';
                $outcomeDisplay = $outcomeLabel
                    ? ucfirst(strtolower($outcomeLabel))
                    : null;
                $isScheduled    = ($outcomeLabel === null);
                $isApproved     = ($outcomeLabel === 'APPROVED');
                ?>
                <div class="group rounded-2xl border border-gray-200 bg-white p-5 transition
                            hover:border-blue-300 hover:shadow-sm"
                     role="listitem">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">

                        <!-- Left: document info -->
                        <div class="min-w-0 flex-1 space-y-2">

                            <!-- Tracking number + badges -->
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-sm font-bold text-blue-700">
                                    <?= htmlspecialchars($doc['tracking_number'] ?? '—') ?>
                                </span>

                                <?php if (!empty($doc['document_type_name'])): ?>
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs
                                                 font-medium text-white"
                                          style="background-color:<?= htmlspecialchars($doc['document_type_badge_color'] ?? '#6B7280') ?>">
                                        <?= htmlspecialchars($doc['document_type_name']) ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($isScheduled): ?>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-100
                                                 px-2 py-0.5 text-xs font-semibold text-amber-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500" aria-hidden="true"></span>
                                        Scheduled
                                    </span>
                                <?php elseif ($outcomeDisplay): ?>
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs
                                                 font-semibold <?= $outcomeCls ?>">
                                        <?= htmlspecialchars($outcomeDisplay) ?>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($doc['status'])): ?>
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs
                                                 font-medium text-white"
                                          style="background-color:<?= htmlspecialchars($doc['status_badge_color'] ?? '#6B7280') ?>">
                                        <?= htmlspecialchars($doc['status']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Subject matter -->
                            <p class="text-sm text-gray-800 line-clamp-2">
                                <?= htmlspecialchars($doc['subject_matter'] ?? '—') ?>
                            </p>

                            <!-- Meta row -->
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500">

                                <!-- Agenda date/time -->
                                <span class="flex items-center gap-1">
                                    <svg class="h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor"
                                         viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    <span><?= htmlspecialchars($agendaDate) ?>
                                        <?= $agendaTime ? '· ' . htmlspecialchars($agendaTime) : '' ?>
                                    </span>
                                </span>

                                <?php if (!empty($doc['venue'])): ?>
                                    <span class="flex items-center gap-1">
                                        <svg class="h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor"
                                             viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        <?= htmlspecialchars($doc['venue']) ?>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($doc['committee_names'])): ?>
                                    <span class="flex items-center gap-1">
                                        <svg class="h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor"
                                             viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        <?= htmlspecialchars($doc['committee_names']) ?>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($doc['outcome_date']) && $doc['outcome_by']): ?>
                                    <span class="flex items-center gap-1 text-gray-400">
                                        Acted by <?= htmlspecialchars($doc['outcome_by']) ?>
                                        on <?= htmlspecialchars(date('M j, Y', strtotime($doc['outcome_date']))) ?>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($doc['report_number'])): ?>
                                    <span class="flex items-center gap-1 text-emerald-600">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor"
                                             viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        Report #<?= htmlspecialchars($doc['report_number']) ?>
                                    </span>
                                <?php endif; ?>

                            </div>
                        </div>

                        <!-- Right: actions -->
                        <div class="flex shrink-0 flex-wrap items-center gap-2">

                            <?php if ($isScheduled): ?>
                                <!-- Scheduled: primary action = Record Hearing -->
                                <a href="<?= BASE_URL ?>/committee/hearing/show?id=<?= (int) $doc['document_id'] ?>"
                                   class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2
                                          text-sm font-semibold text-white hover:bg-blue-700 transition
                                          focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                                    Record Outcome
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>

                            <?php elseif ($isApproved && empty($doc['report_id'])): ?>
                                <!-- Approved but no report yet: Create Report -->
                                <a href="<?= BASE_URL ?>/committee/hearing/show?id=<?= (int) $doc['document_id'] ?>"
                                   class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200
                                          bg-white px-3 py-2 text-sm font-medium text-gray-600
                                          hover:bg-gray-50 transition">
                                    View
                                </a>
                                <?php if (!empty($doc['hearing_id'])): ?>
                                <a href="<?= BASE_URL ?>/committee/hearing/report?document_id=<?= (int) $doc['document_id'] ?>&hearing_id=<?= (int) $doc['hearing_id'] ?>"
                                   class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2
                                          text-sm font-semibold text-white hover:bg-emerald-700 transition
                                          focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    Create Report
                                </a>
                                <?php endif; ?>

                            <?php elseif ($isApproved && !empty($doc['report_id'])): ?>
                                <!-- Approved with report: View Report -->
                                <a href="<?= BASE_URL ?>/committee/hearing/show?id=<?= (int) $doc['document_id'] ?>"
                                   class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200
                                          bg-white px-3 py-2 text-sm font-medium text-gray-600
                                          hover:bg-gray-50 transition">
                                    View Hearing
                                </a>
                                <a href="<?= BASE_URL ?>/committee/hearing/report/show?id=<?= (int) $doc['report_id'] ?>"
                                   class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-50 border
                                          border-emerald-200 px-3 py-2 text-sm font-semibold text-emerald-700
                                          hover:bg-emerald-100 transition">
                                    View Report
                                </a>

                            <?php else: ?>
                                <!-- Deferred / Remanded / Withdrawn: View only -->
                                <a href="<?= BASE_URL ?>/committee/hearing/show?id=<?= (int) $doc['document_id'] ?>"
                                   class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200
                                          bg-white px-4 py-2 text-sm font-medium text-gray-600
                                          hover:bg-gray-50 transition">
                                    View Details
                                </a>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ── Pagination ─────────────────────────────────────────────────── -->
        <?php if ($totalPages > 1): ?>
            <nav class="flex items-center justify-between border-t border-gray-200 pt-4"
                 aria-label="Pagination">
                <p class="text-sm text-gray-500">
                    Page <?= $page ?> of <?= $totalPages ?>
                </p>
                <div class="flex items-center gap-1">
                    <?php if ($page > 1): ?>
                        <a href="<?= hearingUrl($tab, $search, $page - 1) ?>"
                           class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-600
                                  hover:bg-gray-50 transition"
                           aria-label="Previous page">
                            ← Prev
                        </a>
                    <?php endif; ?>

                    <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                        <a href="<?= hearingUrl($tab, $search, $p) ?>"
                           class="rounded-lg border px-3 py-1.5 text-sm transition
                                  <?= $p === $page
                                      ? 'border-blue-500 bg-blue-600 font-semibold text-white'
                                      : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50' ?>"
                           <?= $p === $page ? 'aria-current="page"' : '' ?>>
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="<?= hearingUrl($tab, $search, $page + 1) ?>"
                           class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-600
                                  hover:bg-gray-50 transition"
                           aria-label="Next page">
                            Next →
                        </a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    <?php endif; ?>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

<?php
/**
 * Admin / Routing Dashboard
 *
 * Variables supplied by AdminDashboardController::index():
 *   $stats        array  pending_count, accepted_count, routed_today, total_processed
 *   $recentQueue  array  Up to 5 most recent PENDING unclaimed documents
 *   $pageTitle    string
 */

$stats       = $stats       ?? ['pending_count' => 0, 'accepted_count' => 0, 'routed_today' => 0, 'total_processed' => 0];
$recentQueue = $recentQueue ?? [];
$currentTerm = $currentTerm ?? null;
$legislativeOverview = $legislativeOverview ?? [
    'total_documents' => 0,
    'in_workflow' => 0,
    'completed_documents' => 0,
    'received_this_month' => 0,
    'month_trend' => [],
    'phase_breakdown' => [],
];

$user     = auth();
$userName = $user['full_name'] ?? ($user['username'] ?? 'Admin Officer');
$monthTrend = $legislativeOverview['month_trend'];
$phaseBreakdown = $legislativeOverview['phase_breakdown'];
$trendMax = max(1, ...array_column($monthTrend, 'count'));
$phaseMax = max(1, ...array_column($phaseBreakdown, 'count'));
$termProgress = 0;
if ($currentTerm) {
    $termStart = strtotime($currentTerm['start_date']);
    $termEnd = strtotime($currentTerm['end_date']);
    $today = strtotime(date('Y-m-d'));
    if ($termStart !== false && $termEnd !== false && $termEnd > $termStart) {
        $termProgress = (int) max(0, min(100, round((($today - $termStart) / ($termEnd - $termStart)) * 100)));
    }
}

ob_start();
?>

<div class="space-y-6">

    <!-- Legislative identity ------------------------------------------------>
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-lg">
        <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
        <div class="relative z-10 flex flex-col gap-6 px-6 py-7 sm:px-8 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-blue-100">Pangasinan Provincial Legislature</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-white sm:text-3xl">Legislative Dashboard</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">
                    Good day, <?= htmlspecialchars($userName) ?>. A consolidated view of legislative measures, workflow, and your administration desk.
                </p>
            </div>
            <div class="flex items-center gap-3 border-t border-white/10 pt-4 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
                <span class="flex h-2.5 w-2.5 rounded-full bg-emerald-400 ring-4 ring-emerald-400/15"></span>
                <div>
                    <p class="text-xs font-medium text-white">System overview</p>
                    <p class="mt-0.5 text-xs text-slate-400"><?= htmlspecialchars(date('l, F j, Y')) ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- Legislative snapshot ------------------------------------------------>
    <section aria-labelledby="legislative-snapshot-title">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-primary">At a glance</p>
                <h2 id="legislative-snapshot-title" class="mt-1 text-xl font-semibold text-gray-900">Legislative activity</h2>
            </div>
            <p class="text-xs text-gray-500">All recorded legislative documents</p>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <?php
            $legislativeStats = [
                ['label' => 'Documents Registered', 'value' => $legislativeOverview['total_documents'], 'note' => 'All records in the system', 'tone' => 'blue'],
                ['label' => 'In Workflow', 'value' => $legislativeOverview['in_workflow'], 'note' => 'Currently moving through offices', 'tone' => 'indigo'],
                ['label' => 'Completed & Filed', 'value' => $legislativeOverview['completed_documents'], 'note' => 'Finalized or filed records', 'tone' => 'green'],
                ['label' => 'Received This Month', 'value' => $legislativeOverview['received_this_month'], 'note' => date('F Y'), 'tone' => 'sky'],
            ];
            $toneClasses = [
                'blue' => ['icon' => 'bg-blue-50 text-primary', 'mark' => 'bg-primary'],
                'indigo' => ['icon' => 'bg-indigo-50 text-indigo-600', 'mark' => 'bg-indigo-500'],
                'green' => ['icon' => 'bg-emerald-50 text-emerald-700', 'mark' => 'bg-emerald-600'],
                'sky' => ['icon' => 'bg-sky-50 text-sky-700', 'mark' => 'bg-sky-500'],
            ];
            foreach ($legislativeStats as $stat):
                $tone = $toneClasses[$stat['tone']];
            ?>
                <article class="relative overflow-hidden rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <span class="absolute inset-x-0 top-0 h-1 <?= $tone['mark'] ?>"></span>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-medium text-slate-500"><?= htmlspecialchars($stat['label']) ?></p>
                            <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format((int) $stat['value']) ?></p>
                            <p class="mt-1 text-[11px] text-slate-400"><?= htmlspecialchars($stat['note']) ?></p>
                        </div>
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg <?= $tone['icon'] ?>">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <?php if ($stat['tone'] === 'blue'): ?>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M8 5h8M8 9h8M8 13h5m-8 8h14a2 2 0 002-2V5a2 2 0 00-2-2H7L3 7v12a2 2 0 002 2z"/>
                                <?php elseif ($stat['tone'] === 'indigo'): ?>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                <?php elseif ($stat['tone'] === 'green'): ?>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                <?php else: ?>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z"/>
                                <?php endif; ?>
                            </svg>
                        </span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Current term -------------------------------------------------------->
    <section aria-labelledby="current-term-title" class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <div class="flex flex-col gap-5 p-5 sm:p-6 md:flex-row md:items-center md:justify-between">
            <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-primary">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 3l2.2 5.2L20 10l-4.3 3.4L17 19l-5-3-5 3 1.3-5.6L4 10l5.8-1.8L12 3z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-primary">Current Legislative Term</p>
                    <h2 id="current-term-title" class="mt-1 text-xl font-semibold text-gray-900">
                        <?= $currentTerm ? htmlspecialchars($currentTerm['name']) : 'No active legislative term' ?>
                    </h2>
                    <?php if ($currentTerm): ?>
                        <p class="mt-1 text-xs text-slate-600">
                            <?= htmlspecialchars(date('F j, Y', strtotime($currentTerm['start_date']))) ?>
                            <span class="px-1 text-slate-400">to</span>
                            <?= htmlspecialchars(date('F j, Y', strtotime($currentTerm['end_date']))) ?>
                        </p>
                    <?php else: ?>
                        <p class="mt-1 text-xs text-slate-500">An active term will appear here once configured.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($currentTerm): ?>
                <div class="grid grid-cols-3 gap-5 sm:min-w-72">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Session</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900"><?= number_format((int) $currentTerm['session_number']) ?></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">SP Members</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900"><?= number_format((int) $currentTerm['member_count']) ?></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Committees</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900"><?= number_format((int) $currentTerm['committee_count']) ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($currentTerm): ?>
            <div class="border-t border-gray-200 px-5 py-4 sm:px-6">
                <div class="mb-2 flex items-center justify-between text-[11px]">
                    <span class="font-medium text-slate-600">Term timeline</span>
                    <span class="font-semibold tabular-nums text-gray-900"><?= $termProgress ?>% elapsed</span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-gray-200" role="progressbar" aria-label="Legislative term timeline" aria-valuenow="<?= $termProgress ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="h-full rounded-full bg-primary transition-all" style="width: <?= $termProgress ?>%"></div>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <!-- Legislative charts -------------------------------------------------->
    <section class="grid grid-cols-1 gap-5 xl:grid-cols-2">
        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-primary">Document intake</p>
                    <h2 class="mt-1 text-lg font-semibold text-gray-900">Monthly registrations</h2>
                    <p class="mt-1 text-xs text-slate-500">Documents received over the last six months</p>
                </div>
                <span class="rounded-full bg-slate-50 px-2.5 py-1 text-[10px] font-medium text-slate-500">6 months</span>
            </div>
            <?php if (empty($monthTrend)): ?>
                <div class="mt-6 flex h-40 items-center justify-center rounded-lg bg-slate-50 text-xs text-slate-500">No monthly registration data available.</div>
            <?php else: ?>
                <div class="relative mt-6 h-44" role="img" aria-label="Monthly registrations: <?= htmlspecialchars(implode(', ', array_map(static fn($month) => $month['label'] . ' ' . $month['count'], $monthTrend))) ?>">
                    <div class="absolute inset-x-0 top-0 border-t border-dashed border-slate-200"></div>
                    <div class="absolute inset-x-0 top-1/3 border-t border-dashed border-slate-200"></div>
                    <div class="absolute inset-x-0 top-2/3 border-t border-dashed border-slate-200"></div>
                    <div class="absolute inset-0 flex items-end justify-around gap-2 px-2">
                        <?php foreach ($monthTrend as $index => $month):
                            $barHeight = $month['count'] > 0 ? max(5, (int) round(($month['count'] / $trendMax) * 68)) : 3;
                            $isCurrentMonth = $index === count($monthTrend) - 1;
                        ?>
                            <div class="z-10 flex h-full flex-1 flex-col items-center justify-end gap-2">
                                <span class="text-[10px] font-medium tabular-nums text-slate-600"><?= number_format((int) $month['count']) ?></span>
                                <div class="w-full max-w-10 rounded-t-md <?= $isCurrentMonth ? 'bg-primary' : 'bg-blue-200' ?> transition-all" style="height: <?= $barHeight ?>%" title="<?= htmlspecialchars($month['label']) ?>: <?= number_format((int) $month['count']) ?> documents"></div>
                                <span class="text-[10px] <?= $isCurrentMonth ? 'font-semibold text-gray-900' : 'text-slate-400' ?>"><?= htmlspecialchars($month['label']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </article>

        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-primary">Workflow distribution</p>
                <h2 class="mt-1 text-lg font-semibold text-gray-900">Documents by stage</h2>
                <p class="mt-1 text-xs text-slate-500">Current location of all registered documents</p>
            </div>
            <?php if (empty($phaseBreakdown)): ?>
                <div class="mt-6 flex h-40 items-center justify-center rounded-lg bg-slate-50 text-xs text-slate-500">No workflow data available.</div>
            <?php else: ?>
                <div class="mt-5 space-y-3" role="list" aria-label="Documents by workflow stage">
                    <?php foreach ($phaseBreakdown as $phase):
                        $phaseWidth = $phase['count'] > 0 ? max(3, (int) round(($phase['count'] / $phaseMax) * 100)) : 0;
                    ?>
                        <div role="listitem">
                            <div class="mb-1 flex items-center justify-between gap-3 text-xs">
                                <span class="font-medium text-slate-600"><?= htmlspecialchars($phase['label']) ?></span>
                                <span class="tabular-nums text-slate-500"><?= number_format((int) $phase['count']) ?></span>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full <?= in_array($phase['label'], ['Finalized', 'Filed'], true) ? 'bg-emerald-600' : 'bg-primary' ?>" style="width: <?= $phaseWidth ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    </section>

    <!-- Statistics cards (user-scoped) -------------------------------------->
    <section aria-labelledby="admin-desk-title">
        <div class="mb-4">
            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-primary">Your operations</p>
            <h2 id="admin-desk-title" class="mt-1 text-lg font-semibold text-gray-900">Administration desk</h2>
        </div>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">

        <!-- Pending in queue (unclaimed, available to me) -->
        <a href="<?= BASE_URL ?>/admin/inbox?view=inbox"
           class="rounded-2xl border border-gray-200 bg-white p-5 hover:border-blue-300 hover:shadow-sm transition block">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-gray-500">Pending (Unclaimed)</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($stats['pending_count']) ?></p>
                    <p class="mt-1 text-xs text-gray-400">Available to accept</p>
                </div>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </a>

        <!-- My accepted / in-progress -->
        <a href="<?= BASE_URL ?>/admin/inbox?view=accepted"
           class="rounded-2xl border border-gray-200 bg-white p-5 hover:border-blue-300 hover:shadow-sm transition block">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-gray-500">My Accepted</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($stats['accepted_count']) ?></p>
                    <p class="mt-1 text-xs text-gray-400">In your ownership</p>
                </div>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-primary">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </a>

        <!-- Routed today by me -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-gray-500">Routed Today</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($stats['routed_today']) ?></p>
                    <p class="mt-1 text-xs text-gray-400">By you today</p>
                </div>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Total processed by me (all time) -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Processed</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($stats['total_processed']) ?></p>
                    <p class="mt-1 text-xs text-gray-400">By you, all time</p>
                </div>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-sky-50 text-sky-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
            </div>
        </div>

    </div>
    </section>

    <!-- Main content: pending queue + route shortcuts ----------------------->
    <section class="grid grid-cols-1 gap-6 xl:grid-cols-5">

        <!-- Pending queue (up to 5 most recent) ----------------------------->
        <div class="rounded-2xl border border-gray-200 bg-white xl:col-span-3">
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5">
                <div>
                    <h2 class="font-semibold text-gray-900">Pending Queue</h2>
                    <p class="mt-1 text-xs text-gray-500">
                        Documents awaiting your acceptance — first to accept gets ownership
                    </p>
                </div>
                <a href="<?= BASE_URL ?>/admin/inbox?view=inbox"
                   class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 transition">
                    View All
                </a>
            </div>

            <?php if (empty($recentQueue)): ?>
                <div class="flex items-center justify-center px-6 py-10">
                    <div class="text-center">
                        <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                            <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-gray-700">Queue is empty</p>
                        <p class="mt-1 text-xs text-gray-400">Documents from Receiving will appear here.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-50">
                    <?php foreach ($recentQueue as $item): ?>
                        <a href="<?= BASE_URL ?>/admin/inbox/show?id=<?= (int) $item['document_id'] ?>"
                           class="flex items-start gap-4 px-6 py-4 hover:bg-gray-50 transition">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-primary">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-gray-800">
                                    <?= htmlspecialchars($item['tracking_number']) ?>
                                </p>
                                <p class="mt-0.5 truncate text-xs text-gray-500">
                                    <?= htmlspecialchars(mb_substr($item['subject_matter'], 0, 80)) ?>
                                </p>
                                <div class="mt-1 flex items-center gap-2">
                                    <?php if (!empty($item['document_type_name'])): ?>
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                              style="background-color: <?= htmlspecialchars($item['document_type_badge_color'] ?? '#2563EB') ?>1a;
                                                     color: <?= htmlspecialchars($item['document_type_badge_color'] ?? '#2563EB') ?>;">
                                            <?= htmlspecialchars($item['document_type_name']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="text-xs text-gray-400">
                                        <?= htmlspecialchars(date('M j, Y', strtotime($item['date_received']))) ?>
                                    </span>
                                </div>
                            </div>
                            <svg class="h-4 w-4 shrink-0 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php if ($stats['pending_count'] > count($recentQueue)): ?>
                    <div class="border-t border-gray-100 px-6 py-3 text-center">
                        <a href="<?= BASE_URL ?>/admin/inbox?view=inbox"
                           class="text-xs font-medium text-primary hover:underline">
                            View all <?= number_format($stats['pending_count']) ?> pending documents &rarr;
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Route shortcuts ------------------------------------------------->
        <div class="rounded-2xl border border-gray-200 bg-white xl:col-span-2">
            <div class="border-b border-gray-100 px-6 py-5">
                <h2 class="font-semibold text-gray-900">Admin Workbench</h2>
                <p class="mt-1 text-xs text-gray-500">Document intake, routing, and records</p>
            </div>
            <div class="grid grid-cols-1 gap-3 p-5 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                <a class="group flex items-start gap-3 rounded-xl border border-gray-200 p-3 transition hover:border-primary hover:bg-blue-50"
                   href="<?= BASE_URL ?>/admin/receive-document">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-primary transition group-hover:bg-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4v16m8-8H4"/>
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-800 group-hover:text-primary">Receive Document</p>
                        <p class="mt-0.5 text-xs leading-5 text-gray-500">Register a new legislative submission</p>
                    </div>
                </a>
                <a class="group flex items-start gap-3 rounded-xl border border-gray-200 p-3 transition hover:border-indigo-300 hover:bg-indigo-50"
                   href="<?= BASE_URL ?>/admin/routed">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 transition group-hover:bg-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-800 group-hover:text-indigo-700">Routed Documents</p>
                        <p class="mt-0.5 text-xs leading-5 text-gray-500">Track measures sent to legislative offices</p>
                    </div>
                </a>
                <a class="group flex items-start gap-3 rounded-xl border border-gray-200 p-3 transition hover:border-sky-300 hover:bg-sky-50"
                   href="<?= BASE_URL ?>/admin/communications">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-700 transition group-hover:bg-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-800 group-hover:text-sky-700">Communications</p>
                        <p class="mt-0.5 text-xs leading-5 text-gray-500">Review incoming official correspondence</p>
                    </div>
                </a>
                <a class="group flex items-start gap-3 rounded-xl border border-gray-200 p-3 transition hover:border-emerald-300 hover:bg-emerald-50"
                   href="<?= BASE_URL ?>/admin/noted-documents">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 transition group-hover:bg-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5h6m-6 4h6m-6 4h6m-6 4h4"/>
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-800 group-hover:text-emerald-700">Noted Documents</p>
                        <p class="mt-0.5 text-xs leading-5 text-gray-500">Access documents recorded for reference</p>
                    </div>
                </a>
            </div>
        </div>

    </section>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';

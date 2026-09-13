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

$user     = auth();
$userName = $user['full_name'] ?? ($user['username'] ?? 'Admin Officer');

ob_start();
?>

<div class="space-y-6">

    <!-- Page header ---------------------------------------------------------->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-blue-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10 max-w-3xl">
                <p class="text-sm font-medium text-blue-100">ADMINISTRATION &amp; ROUTING</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    Hi, <?= htmlspecialchars($userName) ?> &#9889;
                </h1>
                <p class="mt-2 max-w-xl text-sm leading-6 text-blue-100">
                    Review documents received from the Receiving desk, accept ownership, and forward to
                    SP Secretary, appropriate Committees, Plenary, or Records.
                </p>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
        </div>
    </section>

    <!-- Statistics cards (user-scoped) -------------------------------------->
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">

        <!-- Pending in queue (unclaimed, available to me) -->
        <a href="<?= BASE_URL ?>/admin/inbox?view=inbox"
           class="rounded-2xl border border-gray-200 bg-white p-5 hover:border-blue-300 hover:shadow-sm transition block">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-gray-500">Pending (Unclaimed)</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($stats['pending_count']) ?></p>
                    <p class="mt-1 text-xs text-gray-400">Available to accept</p>
                </div>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
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
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
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
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
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
                <h2 class="font-semibold text-gray-900">Quick Links</h2>
                <p class="mt-1 text-xs text-gray-500">Common workstations &amp; actions</p>
            </div>
            <div class="space-y-3 p-6">
                <a class="flex items-center gap-3 rounded-xl border border-gray-200 p-3 hover:border-blue-300 hover:bg-blue-50 transition"
                   href="<?= BASE_URL ?>/admin/inbox?view=inbox">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 text-primary">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-medium text-gray-800">My Inbox</p>
                        <p class="text-xs text-gray-500">Pending &amp; accepted documents</p>
                    </div>
                </a>
                <a class="flex items-center gap-3 rounded-xl border border-gray-200 p-3 hover:border-indigo-300 hover:bg-indigo-50 transition"
                   href="<?= BASE_URL ?>/spsec/dashboard">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600 text-xs font-bold">SP</span>
                    <div>
                        <p class="text-sm font-medium text-gray-800">SP Secretary</p>
                        <p class="text-xs text-gray-500">Schedule &amp; Agenda Prep</p>
                    </div>
                </a>
                <a class="flex items-center gap-3 rounded-xl border border-gray-200 p-3 hover:border-amber-300 hover:bg-amber-50 transition"
                   href="<?= BASE_URL ?>/committee/dashboard">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-100 text-amber-600 text-xs font-bold">C</span>
                    <div>
                        <p class="text-sm font-medium text-gray-800">Committees</p>
                        <p class="text-xs text-gray-500">Review &amp; Recommendations</p>
                    </div>
                </a>
                <a class="flex items-center gap-3 rounded-xl border border-gray-200 p-3 hover:border-violet-300 hover:bg-violet-50 transition"
                   href="<?= BASE_URL ?>/plenary/dashboard">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-violet-100 text-violet-600 text-xs font-bold">P</span>
                    <div>
                        <p class="text-sm font-medium text-gray-800">Plenary</p>
                        <p class="text-xs text-gray-500">Session Readings &amp; Voting</p>
                    </div>
                </a>
            </div>
        </div>

    </section>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';

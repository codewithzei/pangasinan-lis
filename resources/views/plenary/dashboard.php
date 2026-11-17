<?php
$user = auth();
$userName = $user['full_name'] ?? ($user['username'] ?? 'Plenary Staff');
$pageTitle = $pageTitle ?? 'Plenary Dashboard';
$stats = $stats ?? [];
$currentTerm = $currentTerm ?? null;
$recentQueue = $recentQueue ?? [];

$firstReadingCount = (int) ($stats['first_reading_count'] ?? 0);
$secondReadingCount = (int) ($stats['second_reading_count'] ?? 0);
$approvedCount = (int) ($stats['approved_count'] ?? 0);
$stageMax = max(1, $firstReadingCount, $secondReadingCount, $approvedCount);
$termProgress = null;
if ($currentTerm && !empty($currentTerm['start_date']) && !empty($currentTerm['end_date'])) {
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
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-lg">
        <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
        <div class="relative z-10 flex flex-col gap-6 px-6 py-7 sm:px-8 lg:flex-row lg:items-end lg:justify-between">
            <div class="flex items-start gap-4">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full border border-white/30 bg-white/5 text-blue-100">
                    <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.4" d="M3 21h18M4 18h16M5 18V9l7-5 7 5v9M9 10v5m6-5v5M2 9h20"/>
                    </svg>
                </div>
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-blue-100">Pangasinan Provincial Legislature</p>
                    <h1 class="mt-2 text-2xl font-bold tracking-tight text-white sm:text-3xl">Plenary Dashboard</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-blue-100">
                        Good day, <?= htmlspecialchars($userName) ?>. Monitor the Plenary inbox, manage legislative readings, and track measures approved on Second Reading.
                    </p>
                </div>
            </div>
            <div class="border-t border-white/10 pt-4 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
                <p class="text-xs font-medium text-white">Plenary workspace</p>
                <p class="mt-1 text-xs text-blue-100"><?= htmlspecialchars(date('l, F j, Y')) ?></p>
            </div>
        </div>
    </section>

    <section aria-labelledby="plenary-overview-title">
        <div class="mb-4">
            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-primary">At a glance</p>
            <h2 id="plenary-overview-title" class="mt-1 text-xl font-semibold text-gray-900">Legislative workload</h2>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <?php
            $overviewCards = [
                ['label' => 'Pending Inbox', 'value' => (int) ($stats['pending_count'] ?? 0), 'note' => 'Available for your review', 'icon' => 'bg-blue-50 text-primary'],
                ['label' => 'Accepted by You', 'value' => (int) ($stats['accepted_count'] ?? 0), 'note' => 'Currently being processed', 'icon' => 'bg-indigo-50 text-indigo-600'],
                ['label' => 'Awaiting First Reading', 'value' => $firstReadingCount, 'note' => 'Measures in the First Reading queue', 'icon' => 'bg-sky-50 text-sky-700'],
                ['label' => 'Awaiting Second Reading', 'value' => $secondReadingCount, 'note' => 'Measures ready for Second Reading', 'icon' => 'bg-slate-100 text-slate-700'],
            ];
            foreach ($overviewCards as $card):
            ?>
                <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-medium text-gray-500"><?= htmlspecialchars($card['label']) ?></p>
                            <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format($card['value']) ?></p>
                            <p class="mt-1 text-[11px] text-gray-400"><?= htmlspecialchars($card['note']) ?></p>
                        </div>
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg <?= $card['icon'] ?>">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M8 5h8M8 9h8M8 13h5m-8 8h14a2 2 0 002-2V5a2 2 0 00-2-2H7L3 7v12a2 2 0 002 2z"/>
                            </svg>
                        </span>
                    </div>
                    <span class="absolute inset-x-0 bottom-0 h-1 bg-primary/10"></span>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <article class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm lg:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-5 sm:px-6">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-primary">Action required</p>
                    <h2 class="mt-1 font-semibold text-gray-900">Pending Plenary Inbox</h2>
                    <p class="mt-1 text-xs text-gray-500">Unclaimed documents available to the Plenary team</p>
                </div>
                <a href="<?= BASE_URL ?>/plenary/inbox" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-600 transition hover:border-primary hover:text-primary">
                    Open inbox
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m-6-6l6 6-6 6"/>
                    </svg>
                </a>
            </div>
            <?php if (empty($recentQueue)): ?>
                <div class="px-6 py-10 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 text-gray-400">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 4h16v12H4zM8 20h8m-4-4v4"/>
                        </svg>
                    </div>
                    <p class="mt-3 text-sm font-medium text-gray-700">Your pending inbox is clear</p>
                    <p class="mt-1 text-xs text-gray-500">New documents assigned to the Plenary role will appear here.</p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-100">
                    <?php foreach ($recentQueue as $document): ?>
                        <a href="<?= BASE_URL ?>/plenary/inbox/show?id=<?= (int) $document['id'] ?>" class="flex items-center gap-4 px-5 py-4 transition hover:bg-gray-50 sm:px-6">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-primary">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-gray-800"><?= htmlspecialchars($document['tracking_number']) ?></span>
                                <span class="mt-0.5 block truncate text-xs text-gray-500"><?= htmlspecialchars($document['subject_matter']) ?></span>
                                <span class="mt-1 block text-[11px] text-gray-400">
                                    <?= htmlspecialchars($document['document_type_name'] ?? 'Document') ?>
                                    <?php if (!empty($document['date_received'])): ?>
                                        <span aria-hidden="true">·</span>
                                        Received <?= htmlspecialchars(date('M j, Y', strtotime($document['date_received']))) ?>
                                    <?php endif; ?>
                                </span>
                            </span>
                            <span class="hidden rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-medium text-amber-700 sm:inline-flex">Pending</span>
                            <svg class="h-4 w-4 shrink-0 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>

        <div class="space-y-6">
            <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-primary">Current Legislative Term</p>
                <?php if ($currentTerm): ?>
                    <h2 class="mt-2 text-lg font-semibold text-gray-900"><?= htmlspecialchars($currentTerm['name']) ?></h2>
                    <p class="mt-1 text-sm text-gray-500">
                        <?= htmlspecialchars(trim(($currentTerm['session_number'] ? 'Session ' . $currentTerm['session_number'] . ' · ' : '') . ($currentTerm['year'] ?? ''))) ?>
                    </p>
                    <?php if (!empty($currentTerm['start_date']) || !empty($currentTerm['end_date'])): ?>
                        <p class="mt-4 text-xs text-gray-500">
                            <?= !empty($currentTerm['start_date']) ? htmlspecialchars(date('M j, Y', strtotime($currentTerm['start_date']))) : 'Start date unavailable' ?>
                            <span class="px-1 text-gray-300">—</span>
                            <?= !empty($currentTerm['end_date']) ? htmlspecialchars(date('M j, Y', strtotime($currentTerm['end_date']))) : 'End date unavailable' ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($termProgress !== null): ?>
                        <div class="mt-4">
                            <div class="mb-1.5 flex items-center justify-between text-[11px]">
                                <span class="font-medium text-gray-500">Term timeline</span>
                                <span class="font-semibold tabular-nums text-primary"><?= $termProgress ?>%</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                                <div class="h-full rounded-full bg-primary transition-all" style="width: <?= $termProgress ?>%"></div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="mt-3 text-sm font-medium text-gray-700">No active legislative term</p>
                    <p class="mt-1 text-xs text-gray-500">An active term will appear here when one is configured.</p>
                <?php endif; ?>
            </article>

            <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-primary">Reading status</p>
                <h2 class="mt-1 font-semibold text-gray-900">Legislative measures</h2>
                <?php if ($firstReadingCount + $secondReadingCount + $approvedCount > 0): ?>
                    <div class="mt-5 space-y-4">
                        <?php foreach ([
                            ['label' => 'First Reading', 'count' => $firstReadingCount, 'color' => 'bg-primary'],
                            ['label' => 'Second Reading', 'count' => $secondReadingCount, 'color' => 'bg-indigo-500'],
                            ['label' => 'Approved', 'count' => $approvedCount, 'color' => 'bg-slate-500'],
                        ] as $stage): ?>
                            <div>
                                <div class="mb-1.5 flex items-center justify-between text-xs">
                                    <span class="font-medium text-gray-600"><?= htmlspecialchars($stage['label']) ?></span>
                                    <span class="tabular-nums text-gray-500"><?= number_format($stage['count']) ?></span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                                    <div class="h-full rounded-full <?= $stage['color'] ?>" style="width: <?= (int) round(($stage['count'] / $stageMax) * 100) ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="mt-4 text-sm text-gray-500">No measures are currently recorded in these reading stages.</p>
                <?php endif; ?>
            </article>
        </div>
    </section>

    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
        <div>
            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-primary">Plenary workbench</p>
            <h2 class="mt-1 font-semibold text-gray-900">Legislative actions</h2>
            <p class="mt-1 text-xs text-gray-500">Direct links to existing Plenary workflows</p>
        </div>
        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <?php
            $actions = [
                ['label' => 'Open Inbox', 'description' => 'Review and process assigned documents', 'url' => '/plenary/inbox'],
                ['label' => 'First Reading', 'description' => 'Process measures queued for first reading', 'url' => '/plenary/first-reading'],
                ['label' => 'Second Reading', 'description' => 'Review measures ready for second reading', 'url' => '/plenary/second-reading'],
                ['label' => 'Referred to Committee', 'description' => 'Review committee-referred measures', 'url' => '/plenary/referred-to-committee'],
            ];
            foreach ($actions as $action):
            ?>
                <a href="<?= BASE_URL . $action['url'] ?>" class="group flex items-start gap-3 rounded-xl border border-gray-200 p-3 transition hover:border-primary hover:bg-blue-50/50">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-primary transition group-hover:bg-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 12h14m-7-7l7 7-7 7"/>
                        </svg>
                    </span>
                    <span>
                        <span class="block text-sm font-medium text-gray-800 group-hover:text-primary"><?= htmlspecialchars($action['label']) ?></span>
                        <span class="mt-0.5 block text-xs text-gray-500"><?= htmlspecialchars($action['description']) ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';

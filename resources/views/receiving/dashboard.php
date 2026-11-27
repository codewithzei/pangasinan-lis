<?php
$userName = $userName ?? 'Receiving Staff';
$stats = $stats ?? [
    'received_today' => 0,
    'received_this_month' => 0,
    'awaiting_admin' => 0,
    'returned_to_receiving' => 0,
];
$monthTrend = $monthTrend ?? [];
$recentDocuments = $recentDocuments ?? [];
$trendMax = max(1, ...array_column($monthTrend, 'count'));

$phaseLabels = [
    'RECEIVING' => 'Receiving',
    'ADMIN' => 'Administration',
    'SP_SECRETARY' => 'SP Secretary',
    'PLENARY' => 'Plenary',
    'COMMITTEE' => 'Committee',
    'FINALIZED' => 'Finalized',
    'FILED' => 'Filed',
];

$pageTitle = 'Receiving Dashboard';
ob_start();
?>

<div class="space-y-6">

    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
        <div class="relative z-10 flex flex-col gap-6 px-6 py-7 sm:px-8 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-blue-100">Receiving Section</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-white sm:text-3xl">Receiving Dashboard</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-blue-100">
                    Welcome back, <?= htmlspecialchars($userName) ?>. Log incoming legislative documents, keep track of your submissions, and follow items returned for correction.
                </p>
            </div>
            <div class="flex items-center gap-3 border-t border-white/15 pt-4 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
                <span class="flex h-2.5 w-2.5 rounded-full bg-emerald-300 ring-4 ring-emerald-300/20"></span>
                <div>
                    <p class="text-xs font-medium text-white">Receiving operations</p>
                    <p class="mt-0.5 text-xs text-blue-100/80"><?= htmlspecialchars(date('l, F j, Y')) ?></p>
                </div>
            </div>
        </div>
    </section>

    <section aria-labelledby="receiving-overview-title">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-primary">At a glance</p>
                <h2 id="receiving-overview-title" class="mt-1 text-xl font-semibold text-gray-900">Receiving activity</h2>
            </div>
            <p class="text-xs text-gray-500">Your registered documents and shared return queue</p>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <?php
            $overviewCards = [
                [
                    'label' => 'Received Today',
                    'value' => $stats['received_today'],
                    'note' => 'Documents logged by you today',
                    'tone' => 'blue',
                    'icon' => 'M12 4v16m8-8H4',
                ],
                [
                    'label' => 'Awaiting Admin',
                    'value' => $stats['awaiting_admin'],
                    'note' => 'Your items pending Admin review',
                    'tone' => 'indigo',
                    'icon' => 'M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z',
                ],
                [
                    'label' => 'Returned to Receiving',
                    'value' => $stats['returned_to_receiving'],
                    'note' => 'Shared queue requiring correction',
                    'tone' => 'rose',
                    'icon' => 'M9 14l-4-4 4-4m-4 4h10a4 4 0 010 8h-1',
                ],
                [
                    'label' => 'Received This Month',
                    'value' => $stats['received_this_month'],
                    'note' => date('F Y') . ' · logged by you',
                    'tone' => 'sky',
                    'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
                ],
            ];
            $toneClasses = [
                'blue' => ['icon' => 'bg-blue-50 text-primary', 'mark' => 'bg-primary'],
                'indigo' => ['icon' => 'bg-indigo-50 text-indigo-600', 'mark' => 'bg-indigo-500'],
                'rose' => ['icon' => 'bg-rose-50 text-rose-600', 'mark' => 'bg-rose-500'],
                'sky' => ['icon' => 'bg-sky-50 text-sky-700', 'mark' => 'bg-sky-500'],
            ];
            foreach ($overviewCards as $card):
                $tone = $toneClasses[$card['tone']];
            ?>
                <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <span class="absolute inset-x-0 top-0 h-1 <?= $tone['mark'] ?>"></span>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-gray-500"><?= htmlspecialchars($card['label']) ?></p>
                            <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format((int) $card['value']) ?></p>
                            <p class="mt-1 text-[11px] text-gray-400"><?= htmlspecialchars($card['note']) ?></p>
                        </div>
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl <?= $tone['icon'] ?>">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="<?= $card['icon'] ?>"/>
                            </svg>
                        </span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-5 xl:grid-cols-5">
        <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6 xl:col-span-3">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-primary">Intake trend</p>
                    <h2 class="mt-1 text-lg font-semibold text-gray-900">Documents logged</h2>
                    <p class="mt-1 text-xs text-gray-500">Monthly submissions entered by you · last six months</p>
                </div>
                <span class="rounded-full bg-blue-50 px-2.5 py-1 text-[10px] font-medium text-primary">6 months</span>
            </div>
            <?php if (empty($monthTrend)): ?>
                <div class="mt-6 flex h-44 items-center justify-center rounded-lg bg-gray-50 text-xs text-gray-500">
                    No registration data available for this period.
                </div>
            <?php else: ?>
                <div class="relative mt-6 h-44" role="img" aria-label="Monthly documents logged: <?= htmlspecialchars(implode(', ', array_map(static fn($month) => $month['label'] . ' ' . $month['count'], $monthTrend))) ?>">
                    <div class="absolute inset-x-0 top-0 border-t border-dashed border-gray-200"></div>
                    <div class="absolute inset-x-0 top-1/3 border-t border-dashed border-gray-200"></div>
                    <div class="absolute inset-x-0 top-2/3 border-t border-dashed border-gray-200"></div>
                    <div class="absolute inset-0 flex items-end justify-around gap-2 px-2">
                        <?php foreach ($monthTrend as $index => $month):
                            $barHeight = $month['count'] > 0 ? max(5, (int) round(($month['count'] / $trendMax) * 68)) : 3;
                            $isCurrentMonth = $index === count($monthTrend) - 1;
                        ?>
                            <div class="z-10 flex h-full flex-1 flex-col items-center justify-end gap-2">
                                <span class="text-[10px] font-medium tabular-nums text-gray-600"><?= number_format((int) $month['count']) ?></span>
                                <div class="w-full max-w-10 rounded-t-md <?= $isCurrentMonth ? 'bg-primary' : 'bg-blue-200' ?> transition-all" style="height: <?= $barHeight ?>%" title="<?= htmlspecialchars($month['label']) ?>: <?= number_format((int) $month['count']) ?> documents"></div>
                                <span class="text-[10px] <?= $isCurrentMonth ? 'font-semibold text-gray-900' : 'text-gray-400' ?>"><?= htmlspecialchars($month['label']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </article>

        <article class="rounded-xl border border-gray-200 bg-white shadow-sm xl:col-span-2">
            <div class="border-b border-gray-100 px-6 py-5">
                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-primary">Receiving tools</p>
                <h2 class="mt-1 font-semibold text-gray-900">Receiving Workbench</h2>
                <p class="mt-1 text-xs text-gray-500">Common actions for document intake</p>
            </div>
            <div class="space-y-3 p-5">
                <a href="<?= BASE_URL ?>/receiving/receive-document" class="group flex items-start gap-3 rounded-xl border border-gray-200 p-3 transition hover:border-primary hover:bg-blue-50">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-primary transition group-hover:bg-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4v16m8-8H4"/>
                        </svg>
                    </span>
                    <span>
                        <span class="block text-sm font-medium text-gray-800 group-hover:text-primary">Receive Document</span>
                        <span class="mt-0.5 block text-xs text-gray-500">Register a submission and its attachments</span>
                    </span>
                </a>
                <a href="<?= BASE_URL ?>/receiving/inbox?view=returned" class="group flex items-start gap-3 rounded-xl border border-gray-200 p-3 transition hover:border-rose-300 hover:bg-rose-50">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-600 transition group-hover:bg-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 14l-4-4 4-4m-4 4h10a4 4 0 010 8h-1"/>
                        </svg>
                    </span>
                    <span>
                        <span class="block text-sm font-medium text-gray-800 group-hover:text-rose-700">Returned Documents</span>
                        <span class="mt-0.5 block text-xs text-gray-500">Review items sent back by Admin</span>
                    </span>
                </a>
                <a href="<?= BASE_URL ?>/receiving/routed-documents" class="group flex items-start gap-3 rounded-xl border border-gray-200 p-3 transition hover:border-indigo-300 hover:bg-indigo-50">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 transition group-hover:bg-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </span>
                    <span>
                        <span class="block text-sm font-medium text-gray-800 group-hover:text-indigo-700">Routed Documents</span>
                        <span class="mt-0.5 block text-xs text-gray-500">Track documents across legislative offices</span>
                    </span>
                </a>
            </div>
        </article>
    </section>

    <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-6 py-5">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-primary">Your intake history</p>
                <h2 class="mt-1 font-semibold text-gray-900">Recently Received Documents</h2>
                <p class="mt-1 text-xs text-gray-500">Latest documents registered under your account</p>
            </div>
            <a href="<?= BASE_URL ?>/receiving/routed-documents" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-600 transition hover:border-primary hover:text-primary">
                View routed documents
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m-6-6l6 6-6 6"/>
                </svg>
            </a>
        </div>
        <?php if (empty($recentDocuments)): ?>
            <div class="px-6 py-10 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100">
                    <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                    </svg>
                </div>
                <p class="mt-3 text-sm font-medium text-gray-700">No documents registered yet</p>
                <p class="mt-1 text-xs text-gray-500">Received documents will appear here after you log them.</p>
                <a href="<?= BASE_URL ?>/receiving/receive-document" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white transition hover:bg-blue-700">
                    <span aria-hidden="true">+</span> Receive a document
                </a>
            </div>
        <?php else: ?>
            <div class="divide-y divide-gray-100">
                <?php foreach ($recentDocuments as $document):
                    $phaseLabel = $phaseLabels[$document['current_phase']] ?? 'In progress';
                ?>
                    <a href="<?= BASE_URL ?>/receiving/routed-documents/show?id=<?= (int) $document['id'] ?>&return=dashboard" class="flex items-center gap-4 px-5 py-4 transition hover:bg-gray-50 sm:px-6">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-primary">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-gray-800"><?= htmlspecialchars($document['tracking_number']) ?></span>
                            <span class="mt-0.5 block truncate text-xs text-gray-500"><?= htmlspecialchars($document['subject_matter']) ?></span>
                            <span class="mt-1 flex flex-wrap items-center gap-2 text-[11px] text-gray-400">
                                <?php if (!empty($document['document_type_name'])): ?>
                                    <span><?= htmlspecialchars($document['document_type_name']) ?></span>
                                    <span aria-hidden="true">·</span>
                                <?php endif; ?>
                                <?= htmlspecialchars(date('M j, Y', strtotime($document['created_at']))) ?>
                            </span>
                        </span>
                        <span class="hidden rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-medium text-primary sm:inline-flex"><?= htmlspecialchars($phaseLabel) ?></span>
                        <svg class="h-4 w-4 shrink-0 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';

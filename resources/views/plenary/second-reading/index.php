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
$currentFilter = $currentFilter ?? 'pending';
$tabCounts     = $tabCounts     ?? [];
$errors        = $errors        ?? [];

$tabDefs = [
    'pending'   => ['label' => 'Awaiting',  'badge' => 'amber'],
    'deferred'  => ['label' => 'Deferred',  'badge' => 'red'],
    'withdrawn' => ['label' => 'Withdrawn', 'badge' => 'gray'],
    'approved'  => ['label' => 'Approved',  'badge' => 'emerald'],
];

$filterTitles = [
    'pending'   => ['title' => 'Awaiting Second Reading',       'sub' => 'Documents ready for Second Reading processing.'],
    'deferred'  => ['title' => 'Deferred Documents',             'sub' => 'Documents deferred at Second Reading, pending compliance updates.'],
    'withdrawn' => ['title' => 'Withdrawn Documents',            'sub' => 'Documents withdrawn during Second Reading.'],
    'approved'  => ['title' => 'Approved on Second Reading',     'sub' => 'Documents approved and finalized.'],
];

ob_start();
?>

<div class="space-y-6">

    <?php if (!empty($success ?? null)): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($error ?? null)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Header -->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10 max-w-3xl">
                <p class="text-sm font-medium text-blue-100">PLENARY / LEGISLATIVE READINGS</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">Second Reading</h1>
                <p class="mt-2 max-w-xl text-sm leading-6 text-blue-100"><?= htmlspecialchars($filterTitles[$currentFilter]['sub']) ?></p>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
        </div>
    </section>

    <!-- Tabs -->
    <section>
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex gap-6 overflow-x-auto">
                <?php foreach ($tabDefs as $key => $def): ?>
                    <?php $count = $tabCounts[$key] ?? 0; ?>
                    <a href="<?= BASE_URL ?>/plenary/second-reading?filter=<?= $key ?><?= $search ? '&search=' . urlencode($search) : '' ?>"
                       class="<?= $currentFilter === $key ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' ?> whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition">
                        <?= htmlspecialchars($def['label']) ?>
                        <?php if ($count > 0): ?>
                            <span class="ml-2 inline-flex items-center rounded-full bg-<?= $def['badge'] ?>-100 px-2 py-0.5 text-xs font-semibold text-<?= $def['badge'] ?>-800">
                                <?= $count ?>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
    </section>

    <!-- Document List -->
    <section class="rounded-2xl border border-gray-200 bg-white">
        <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900"><?= htmlspecialchars($filterTitles[$currentFilter]['title']) ?></h2>
                <p class="mt-1 text-xs text-gray-500"><?= $total ?> record<?= $total !== 1 ? 's' : '' ?> found</p>
            </div>
            <form method="GET" action="<?= BASE_URL ?>/plenary/second-reading" class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <input type="hidden" name="filter" value="<?= htmlspecialchars($currentFilter) ?>">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search tracking # or subject…"
                           class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 lg:w-64">
                </div>
                <button type="submit" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Filter</button>
                <?php if ($search !== ''): ?>
                    <a href="<?= BASE_URL ?>/plenary/second-reading?filter=<?= htmlspecialchars($currentFilter) ?>" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition text-center">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Tracking Number</th>
                        <th class="px-6 py-3 font-medium">Subject Matter / Type</th>
                        <th class="px-6 py-3 font-medium">Proposed No.</th>
                        <th class="px-6 py-3 font-medium">Committees</th>
                        <?php if ($currentFilter === 'approved'): ?>
                            <th class="px-6 py-3 font-medium">Approved No.</th>
                            <th class="px-6 py-3 font-medium">Date Finalized</th>
                        <?php elseif ($currentFilter === 'deferred'): ?>
                            <th class="px-6 py-3 font-medium">Date Deferred</th>
                        <?php elseif ($currentFilter === 'withdrawn'): ?>
                            <th class="px-6 py-3 font-medium">Date Withdrawn</th>
                        <?php endif; ?>
                        <th class="px-6 py-3 font-medium text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($documents)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">No documents in this category.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($documents as $row): ?>
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
                                                  style="background-color: <?= htmlspecialchars($row['document_type_badge_color'] ?? '#2563EB') ?>1a; color: <?= htmlspecialchars($row['document_type_badge_color'] ?? '#2563EB') ?>;">
                                                <?= htmlspecialchars($row['document_type_name']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if (!empty($row['proposed_number'])): ?>
                                        <span class="font-medium text-gray-800"><?= htmlspecialchars($row['proposed_number']) ?></span>
                                    <?php else: ?>
                                        <span class="text-xs italic text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if (!empty($row['committee_names'])): ?>
                                        <span class="text-xs text-gray-700"><?= htmlspecialchars($row['committee_names']) ?></span>
                                    <?php else: ?>
                                        <span class="text-xs italic text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($currentFilter === 'approved'): ?>
                                    <td class="px-6 py-4 font-medium text-primary">
                                        <?= !empty($row['approved_no']) ? htmlspecialchars($row['approved_no']) : '<span class="text-xs italic text-gray-400">—</span>' ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700">
                                        <?= !empty($row['date_finalized']) ? htmlspecialchars(date('M j, Y', strtotime($row['date_finalized']))) : '—' ?>
                                    </td>
                                <?php elseif ($currentFilter === 'deferred'): ?>
                                    <td class="px-6 py-4 text-sm text-gray-700">
                                        <?= !empty($row['date_deferred']) ? htmlspecialchars(date('M j, Y', strtotime($row['date_deferred']))) : '—' ?>
                                    </td>
                                <?php elseif ($currentFilter === 'withdrawn'): ?>
                                    <td class="px-6 py-4 text-sm text-gray-700">
                                        <?= !empty($row['date_withdrawn']) ? htmlspecialchars(date('M j, Y', strtotime($row['date_withdrawn']))) : '—' ?>
                                    </td>
                                <?php endif; ?>
                                <td class="px-6 py-4 text-right">
                                    <?php if (in_array($currentFilter, ['pending', 'deferred'], true)): ?>
                                        <a href="<?= BASE_URL ?>/plenary/second-reading/show?id=<?= (int) $row['document_id'] ?>"
                                           class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:border-primary hover:bg-blue-50 hover:text-primary transition">
                                            Process
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= BASE_URL ?>/plenary/second-reading/show?id=<?= (int) $row['document_id'] ?>"
                                           class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 transition">
                                            View
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="flex flex-col items-center justify-between gap-3 border-t border-gray-100 px-6 py-4 sm:flex-row">
                <p class="text-xs text-gray-500">Page <span class="font-medium"><?= $page ?></span> of <span class="font-medium"><?= $totalPages ?></span></p>
                <div class="flex items-center gap-1">
                    <?php $qs = '&filter=' . $currentFilter . ($search !== '' ? '&search=' . urlencode($search) : ''); ?>
                    <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?><?= $qs ?>" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Prev</a><?php endif; ?>
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?= $i ?><?= $qs ?>" class="rounded-lg border px-3 py-1.5 text-sm font-medium transition <?= $i === $page ? 'border-primary bg-primary text-white' : 'border-gray-200 text-gray-700 hover:bg-gray-50' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?><?= $qs ?>" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Next</a><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

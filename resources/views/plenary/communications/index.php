<?php
/**
 * Plenary Communications — Noted Communications
 *
 * Shows Communication documents that were marked "Noted" at Plenary First
 * Reading.  Finalized Communications (APPROVED / adopted-report) are shown
 * on the Finalized Documents > Communications tab instead.
 *
 * Variables supplied by PlenaryCommController::index():
 *   $communications  array
 *   $page            int
 *   $totalPages      int
 *   $total           int
 *   $search          string
 *   $tab             string  ('noted')
 *   $tabCounts       array   ['noted' => int]
 *   $categories      array   communication_categories for filter
 *   $filterCat       string
 *   $success         string|null
 *   $error           string|null
 */

$communications = $communications ?? [];
$page           = $page           ?? 1;
$totalPages     = $totalPages     ?? 1;
$total          = $total          ?? 0;
$search         = $search         ?? '';
$tab            = $tab            ?? 'noted';
$tabCounts      = $tabCounts      ?? ['noted' => 0];
$categories     = $categories     ?? [];
$filterCat      = $filterCat      ?? '';

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
                <p class="text-sm font-medium text-blue-100">PLENARY / COMMUNICATIONS</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">Plenary Communications</h1>
                <p class="mt-2 max-w-xl text-sm leading-6 text-blue-100">
                    Communications noted at Plenary First Reading.
                    Finalized Communications (approved or adopted) appear in
                    <a href="<?= BASE_URL ?>/plenary/status-list?tab=communications"
                       class="underline underline-offset-2 text-blue-200 hover:text-white">
                        Finalized Documents → Communications
                    </a>.
                </p>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
        </div>
    </section>

    <!-- Stats card -->
    <section>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 inline-flex items-center gap-4">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm text-gray-500">Noted Communications</p>
                <p class="text-2xl font-bold text-gray-900"><?= number_format($tabCounts['noted']) ?></p>
            </div>
        </div>
    </section>

    <!-- List -->
    <section class="rounded-2xl border border-gray-200 bg-white">
        <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900">Noted Communications</h2>
                <p class="mt-1 text-xs text-gray-500"><?= $total ?> record<?= $total !== 1 ? 's' : '' ?></p>
            </div>
            <form method="GET" action="<?= BASE_URL ?>/plenary/communications"
                  class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                           placeholder="Search tracking # or subject…"
                           class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm
                                  text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none
                                  focus:ring-2 focus:ring-primary/20 lg:w-60">
                </div>
                <?php if (!empty($categories)): ?>
                <select name="category"
                        class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700
                               focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>"
                            <?= ((string) $filterCat === (string) $cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <button type="submit"
                        class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium
                               text-gray-700 hover:bg-gray-50 transition">Filter</button>
                <?php if ($search !== '' || $filterCat !== ''): ?>
                    <a href="<?= BASE_URL ?>/plenary/communications"
                       class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium
                              text-gray-700 hover:bg-gray-50 transition text-center">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Tracking Number</th>
                        <th class="px-6 py-3 font-medium">Subject Matter</th>
                        <th class="px-6 py-3 font-medium">Category</th>
                        <th class="px-6 py-3 font-medium">Reading Date</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($communications)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">No Noted Communications yet.</p>
                                <p class="mt-1 text-xs text-gray-500">Communications marked Noted at Plenary First Reading will appear here.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($communications as $row): ?>
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($row['tracking_number']) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars(date('M j, Y', strtotime($row['date_received']))) ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="max-w-sm line-clamp-2 text-gray-800"><?= htmlspecialchars($row['subject_matter']) ?></p>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if (!empty($row['communication_category_name'])): ?>
                                        <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">
                                            <?= htmlspecialchars($row['communication_category_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs italic text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <?= !empty($row['reading_date'])
                                        ? htmlspecialchars(date('M j, Y', strtotime($row['reading_date'])))
                                        : '<span class="text-xs italic text-gray-400">—</span>' ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if (!empty($row['status'])): ?>
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                              style="background-color: <?= htmlspecialchars($row['status_badge_color'] ?? '#6B7280') ?>1a;
                                                     color: <?= htmlspecialchars($row['status_badge_color'] ?? '#6B7280') ?>;">
                                            <?= htmlspecialchars($row['status']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="<?= BASE_URL ?>/plenary/communications/show?id=<?= (int) $row['id'] ?>"
                                       class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200
                                              px-3 py-1.5 text-xs font-medium text-gray-700
                                              hover:border-primary hover:bg-blue-50 hover:text-primary transition">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="flex flex-col items-center justify-between gap-3 border-t border-gray-100 px-6 py-4 sm:flex-row">
                <p class="text-xs text-gray-500">
                    Showing page <span class="font-medium"><?= $page ?></span>
                    of <span class="font-medium"><?= $totalPages ?></span>
                    (<?= $total ?> total)
                </p>
                <div class="flex items-center gap-1">
                    <?php
                    $qs = '';
                    if ($search !== '')    { $qs .= '&search='   . urlencode($search); }
                    if ($filterCat !== '') { $qs .= '&category=' . urlencode($filterCat); }
                    ?>
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?><?= $qs ?>"
                           class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Prev</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?= $i ?><?= $qs ?>"
                           class="rounded-lg border px-3 py-1.5 text-sm font-medium transition
                                  <?= $i === $page ? 'border-primary bg-primary text-white' : 'border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?><?= $qs ?>"
                           class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

<?php
/**
 * Plenary First Reading — queue list
 *
 * Variables supplied by PlenaryReadingController::firstReadingIndex():
 *   $documents      array
 *   $page           int
 *   $totalPages     int
 *   $total          int
 *   $search         string
 *   $referredCount  int
 *   $success        string|null
 *   $error          string|null
 *   $errors         array
 */

$documents     = $documents     ?? [];
$page          = $page          ?? 1;
$totalPages    = $totalPages    ?? 1;
$total         = $total         ?? 0;
$search        = $search        ?? '';
$referredCount = $referredCount ?? 0;
$errors        = $errors        ?? [];

ob_start();

function frPhaseLabel(string $phase): string {
    return match ($phase) {
        'PLENARY'      => 'Plenary',
        'COMMITTEE'    => 'Committee',
        'FINALIZED'    => 'Finalized',
        default        => ucfirst(strtolower($phase)),
    };
}

function frSessionLabel(string $type): string {
    return match ($type) {
        'REGULAR'        => 'Regular',
        'REGULAR_ONLINE' => 'Regular (Online)',
        'SPECIAL'        => 'Special',
        'SPECIAL_ONLINE' => 'Special (Online)',
        default          => $type,
    };
}
?>

<div class="space-y-6">

    <?php if (!empty($success ?? null)): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($error ?? null)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc list-inside space-y-1"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10 max-w-3xl">
                <p class="text-sm font-medium text-blue-100">PLENARY / LEGISLATIVE READINGS</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">First Reading</h1>
                <p class="mt-2 max-w-xl text-sm leading-6 text-blue-100">Documents awaiting their First Reading session.</p>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
        </div>
    </section>

    <!-- Stats -->
    <section>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Awaiting First Reading</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($total) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4"><span class="rounded-full bg-amber-50 px-2 py-1 text-xs font-medium text-amber-600">Ready for First Reading</span></div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Referred to Committee (awaiting return)</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($referredCount) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4"><span class="rounded-full bg-violet-50 px-2 py-1 text-xs font-medium text-violet-600">In Committee</span></div>
            </div>
        </div>
    </section>

    <!-- Document List -->
    <section class="rounded-2xl border border-gray-200 bg-white">
        <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900">First Reading Queue</h2>
                <p class="mt-1 text-xs text-gray-500"><?= $total ?> record<?= $total !== 1 ? 's' : '' ?> found</p>
            </div>
            <form method="GET" action="<?= BASE_URL ?>/plenary/first-reading" class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search tracking # or subject…"
                           class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 lg:w-64">
                </div>
                <button type="submit" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Filter</button>
                <?php if ($search !== ''): ?>
                    <a href="<?= BASE_URL ?>/plenary/first-reading" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition text-center">Clear</a>
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
                        <th class="px-6 py-3 font-medium">Session Type</th>
                        <th class="px-6 py-3 font-medium">Target Date</th>
                        <th class="px-6 py-3 font-medium">Accepted By</th>
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
                                <p class="mt-4 text-sm font-medium text-gray-700">No documents awaiting First Reading.</p>
                                <p class="mt-1 text-xs text-gray-500">Documents will appear here once their Plenary processing details are saved.</p>
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
                                    <?php if (!empty($row['session_type'])): ?>
                                        <span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">
                                            <?= htmlspecialchars(frSessionLabel($row['session_type'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs italic text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <?php if (!empty($row['target_first_reading_date'])): ?>
                                        <?= htmlspecialchars(date('M j, Y', strtotime($row['target_first_reading_date']))) ?>
                                    <?php else: ?>
                                        <span class="text-xs italic text-gray-400">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <?php if (!empty($row['accepted_by_username'])): ?>
                                        <div><?= htmlspecialchars($row['accepted_by_username']) ?></div>
                                        <?php if (!empty(trim($row['accepted_by_name']))): ?>
                                            <div class="text-xs text-gray-400"><?= htmlspecialchars(trim($row['accepted_by_name'])) ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-xs italic text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="<?= BASE_URL ?>/plenary/first-reading/show?id=<?= (int) $row['document_id'] ?>"
                                       class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:border-primary hover:bg-blue-50 hover:text-primary transition">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        Process
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
                <p class="text-xs text-gray-500">Showing page <span class="font-medium"><?= $page ?></span> of <span class="font-medium"><?= $totalPages ?></span> (<?= $total ?> total)</p>
                <div class="flex items-center gap-1">
                    <?php $qs = $search !== '' ? '&search=' . urlencode($search) : ''; ?>
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

<?php
/**
 * Committee Cases — Noted
 *
 * Variables supplied by CommitteeCasesController::noted() via renderOutcomeList():
 *   $cases, $page, $totalPages, $total, $search, $pageTitle, $success, $error
 */

$cases      = $cases      ?? [];
$page       = $page       ?? 1;
$totalPages = $totalPages ?? 1;
$total      = $total      ?? 0;
$search     = $search     ?? '';
$success    = $success    ?? null;
$error      = $error      ?? null;

ob_start();
?>

<div class="space-y-6">

    <!-- Page header ---------------------------------------------------------->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10 max-w-3xl">
                <p class="text-sm font-medium text-blue-100">COMMITTEE / CASES / NOTED</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    Noted Cases
                </h1>
                <p class="mt-2 max-w-xl text-sm leading-6 text-blue-100">
                    Cases noted by the Committee.
                </p>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
        </div>
    </section>

    <!-- Navigation tabs -------------------------------------------------------->
    <?php require __DIR__ . '/_outcome_nav.php'; ?>

    <!-- Flash messages -------------------------------------------------------->
    <?php if ($success): ?>
        <div class="flex items-start gap-3 rounded-2xl border border-green-200 bg-green-50 p-4">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-green-100">
                <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <p class="mt-1.5 text-sm font-medium text-green-800"><?= htmlspecialchars($success) ?></p>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-red-100">
                <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </div>
            <p class="mt-1.5 text-sm font-medium text-red-800"><?= htmlspecialchars($error) ?></p>
        </div>
    <?php endif; ?>

    <!-- Table ----------------------------------------------------------------->
    <section class="rounded-2xl border border-gray-200 bg-white">

        <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5
                    lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900">Noted Cases</h2>
                <p class="mt-1 text-xs text-gray-500">
                    <?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?> found
                </p>
            </div>
            <form method="GET" action="<?= BASE_URL ?>/committee/cases/noted"
                  class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                           placeholder="Docket #, tracking #, subject, complainant…"
                           class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm
                                  placeholder-gray-400 focus:border-primary focus:outline-none
                                  focus:ring-2 focus:ring-primary/20 lg:w-72">
                </div>
                <button type="submit"
                        class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                               font-medium text-gray-700 hover:bg-gray-50 transition">
                    Filter
                </button>
                <?php if ($search !== ''): ?>
                    <a href="<?= BASE_URL ?>/committee/cases/noted"
                       class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                              font-medium text-gray-700 hover:bg-gray-50 transition text-center">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Docket #</th>
                        <th class="px-6 py-3 font-medium">Tracking #</th>
                        <th class="px-6 py-3 font-medium">Nature of Case</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Date Assigned</th>
                        <th class="px-6 py-3 font-medium">Finalized</th>
                        <th class="px-6 py-3 font-medium text-right">View</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($cases)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center
                                            rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0
                                                 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2
                                                 2 0 012 2"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-900">No noted cases</p>
                                <p class="mt-1 text-xs text-gray-500">
                                    Cases set to Noted will appear here.
                                </p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cases as $c): ?>
                            <?php
                            $statusBadge  = $c['status_badge_color'] ?? '#0891B2';
                            $dateAssigned = !empty($c['date_assigned'])
                                ? date('M d, Y', strtotime($c['date_assigned'])) : '—';
                            $finalizedAt  = !empty($c['finalized_at'])
                                ? date('M d, Y', strtotime($c['finalized_at'])) : '—';
                            ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-lg bg-cyan-50 px-2.5 py-1
                                                 text-xs font-semibold text-cyan-700 ring-1 ring-cyan-100">
                                        <?= htmlspecialchars($c['docket_number'] ?? '—') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-mono text-xs font-bold text-primary">
                                    <?= htmlspecialchars($c['tracking_number'] ?? '—') ?>
                                </td>
                                <td class="px-6 py-4 max-w-xs">
                                    <p class="truncate text-sm text-gray-700"
                                       title="<?= htmlspecialchars($c['nature_of_case'] ?? '') ?>">
                                        <?= htmlspecialchars(mb_substr($c['nature_of_case'] ?? '—', 0, 80)) ?>
                                    </p>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                          style="background-color:<?= htmlspecialchars($statusBadge) ?>1a;
                                                 color:<?= htmlspecialchars($statusBadge) ?>;">
                                        <?= htmlspecialchars($c['status'] ?? '—') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-500"><?= $dateAssigned ?></td>
                                <td class="px-6 py-4 text-xs text-gray-500"><?= $finalizedAt ?></td>
                                <td class="px-6 py-4 text-right">
                                    <a href="<?= BASE_URL ?>/committee/cases/show?id=<?= (int) $c['id'] ?>"
                                       class="inline-flex items-center gap-1 rounded-lg border border-gray-200
                                              bg-white px-2.5 py-1.5 text-xs font-medium text-gray-600
                                              hover:bg-gray-50 transition whitespace-nowrap">
                                        View Details
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
                    Page <span class="font-medium text-gray-700"><?= $page ?></span> of
                    <span class="font-medium text-gray-700"><?= $totalPages ?></span>
                    (<?= number_format($total) ?> total record<?= $total !== 1 ? 's' : '' ?>)
                </p>
                <nav class="flex items-center gap-1">
                    <?php if ($page > 1): ?>
                        <a href="<?= BASE_URL ?>/committee/cases/noted?page=<?= $page - 1 ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>"
                           class="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 transition">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </a>
                    <?php endif; ?>
                    <?php
                    $rangeStart = max(1, $page - 2);
                    $rangeEnd   = min($totalPages, $page + 2);
                    for ($p = $rangeStart; $p <= $rangeEnd; $p++):
                    ?>
                        <a href="<?= BASE_URL ?>/committee/cases/noted?page=<?= $p ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>"
                           class="flex h-8 w-8 items-center justify-center rounded-lg border text-xs font-medium transition
                                  <?= $p === $page
                                      ? 'border-primary bg-primary text-white'
                                      : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?= BASE_URL ?>/committee/cases/noted?page=<?= $page + 1 ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>"
                           class="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 transition">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>

    </section>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

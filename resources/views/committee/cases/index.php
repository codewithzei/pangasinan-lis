<?php
/**
 * Committee Cases — Index / List
 *
 * Variables supplied by CommitteeCasesController::index():
 *   $cases        array   committee_cases rows with document joins
 *   $page         int
 *   $totalPages   int
 *   $total        int
 *   $search       string
 *   $totalCases   int     total cases created by this user (unfiltered)
 *   $success      string|null
 *   $error        string|null
 *   $errors       array
 */

$cases      = $cases      ?? [];
$page       = $page       ?? 1;
$totalPages = $totalPages ?? 1;
$total      = $total      ?? 0;
$search     = $search     ?? '';
$totalCases = $totalCases ?? 0;
$success    = $success    ?? null;
$error      = $error      ?? null;
$errors     = $errors     ?? [];

ob_start();
?>

<div class="space-y-6">

    <!-- Page header ---------------------------------------------------------->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10 max-w-3xl">
                <p class="text-sm font-medium text-blue-100">COMMITTEE / CASES</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    Cases
                </h1>
                <p class="mt-2 max-w-xl text-sm leading-6 text-blue-100">
                    Administrative cases and complaints you have docketed for Committee review.
                </p>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
        </div>
    </section>

    <!-- Flash messages ------------------------------------------------------->
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

    <?php if (!empty($errors)): ?>
        <div class="rounded-2xl border border-red-200 bg-red-50 p-4">
            <p class="text-sm font-semibold text-red-800">Please correct the following errors:</p>
            <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-red-700">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Stats cards ---------------------------------------------------------->
    <section>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Total Cases</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($totalCases) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-blue-50 px-2 py-1 font-medium text-blue-600">Docketed by you</span>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Showing</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($total) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-50 text-gray-500">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-gray-100 px-2 py-1 font-medium text-gray-600">
                        <?= $search !== '' ? 'Filtered results' : 'All records' ?>
                    </span>
                </div>
            </div>
        </div>
    </section>

    <!-- Navigation tabs -------------------------------------------------------->
    <?php require __DIR__ . '/_outcome_nav.php'; ?>

    <!-- Cases table ---------------------------------------------------------->
    <section class="rounded-2xl border border-gray-200 bg-white">

        <!-- Table toolbar -->
        <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900">Cases List</h2>
                <p class="mt-1 text-xs text-gray-500">
                    <?= $total ?> record<?= $total !== 1 ? 's' : '' ?> found
                </p>
            </div>
            <form method="GET" action="<?= BASE_URL ?>/committee/cases"
                  class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                           placeholder="Search docket #, tracking # or subject…"
                           class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-800
                                  placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2
                                  focus:ring-primary/20 lg:w-72">
                </div>
                <button type="submit"
                        class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700
                               hover:bg-gray-50 transition">
                    Filter
                </button>
                <?php if ($search !== ''): ?>
                    <a href="<?= BASE_URL ?>/committee/cases"
                       class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700
                              hover:bg-gray-50 transition text-center">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Docket Number</th>
                        <th class="px-6 py-3 font-medium">Tracking Number</th>
                        <th class="px-6 py-3 font-medium">Subject Matter</th>
                        <th class="px-6 py-3 font-medium">Document Type</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Case Type</th>
                        <th class="px-6 py-3 font-medium">Actions</th>
                        <th class="px-6 py-3 font-medium">Date Docketed</th>
                        <th class="px-6 py-3 font-medium text-right">View</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($cases)): ?>
                        <tr>
                            <td colspan="9" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                              d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-900">No cases found</p>
                                <p class="mt-1 text-xs text-gray-500">
                                    <?= $search !== '' ? 'Try adjusting your search terms.' : 'Cases you docket will appear here.' ?>
                                </p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cases as $case): ?>
                            <?php
                                $docTypeBadge  = $case['document_type_badge_color'] ?? '#2563EB';
                                $statusBadge   = $case['status_badge_color']        ?? '#6B7280';
                                $docketDate    = $case['created_at'] ? date('M d, Y', strtotime($case['created_at'])) : '—';
                                $caseType      = htmlspecialchars($case['nature_of_case'] ?? '—');
                                $actionCount   = (int) ($case['action_count'] ?? 0);
                            ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 ring-1 ring-blue-100">
                                        <?= htmlspecialchars($case['docket_number'] ?? '—') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-medium text-gray-900">
                                    <?= htmlspecialchars($case['tracking_number'] ?? '—') ?>
                                </td>
                                <td class="px-6 py-4 max-w-xs">
                                    <p class="truncate text-gray-700" title="<?= htmlspecialchars($case['subject_matter'] ?? '') ?>">
                                        <?= htmlspecialchars($case['subject_matter'] ?? '—') ?>
                                    </p>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium text-white"
                                          style="background-color: <?= htmlspecialchars($docTypeBadge) ?>">
                                        <?= htmlspecialchars($case['document_type_name'] ?? '—') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium text-white"
                                          style="background-color: <?= htmlspecialchars($statusBadge) ?>">
                                        <?= htmlspecialchars($case['status'] ?? '—') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-600">
                                    <?= $caseType ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1 text-xs text-gray-500">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                        </svg>
                                        <?= $actionCount ?> action<?= $actionCount !== 1 ? 's' : '' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-500">
                                    <?= $docketDate ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="<?= BASE_URL ?>/committee/cases/show?id=<?= (int) $case['id'] ?>"
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

        <!-- Pagination -------------------------------------------------------->
        <?php if ($totalPages > 1): ?>
            <div class="flex items-center justify-between border-t border-gray-100 px-6 py-4">
                <p class="text-xs text-gray-500">
                    Page <?= $page ?> of <?= $totalPages ?>
                    &nbsp;·&nbsp; <?= $total ?> record<?= $total !== 1 ? 's' : '' ?>
                </p>
                <nav class="flex items-center gap-1">
                    <?php if ($page > 1): ?>
                        <a href="<?= BASE_URL ?>/committee/cases?page=<?= $page - 1 ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>"
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
                        <a href="<?= BASE_URL ?>/committee/cases?page=<?= $p ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>"
                           class="flex h-8 w-8 items-center justify-center rounded-lg border text-xs font-medium transition
                                  <?= $p === $page
                                      ? 'border-primary bg-primary text-white'
                                      : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="<?= BASE_URL ?>/committee/cases?page=<?= $page + 1 ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>"
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

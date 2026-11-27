<?php
ob_start();

$stats = $stats ?? [];
$actions = $actions ?? [];
$logs = $logs ?? [];
$search = $search ?? '';
$filterAction = $filterAction ?? '';
$filterDateFrom = $filterDateFrom ?? '';
$filterDateTo = $filterDateTo ?? '';
$filterError = $filterError ?? null;
$historyRoute = $historyRoute ?? 'receiving/logs';
$totalRows = $totalRows ?? 0;
$totalPages = $totalPages ?? 1;
$page = $page ?? 1;
$perPage = $perPage ?? 20;
$query = array_filter([
    'search' => $search,
    'action' => $filterAction,
    'date_from' => $filterDateFrom,
    'date_to' => $filterDateTo,
], static fn($value) => $value !== '');
$hasFilters = $query !== [];
$pageTitle = 'My Logs & History';
$showCreatedStat = !in_array($historyRoute, ['spsec/logs', 'plenary/logs', 'committee/logs'], true);

function historyActionClass(string $action): string
{
    return match (strtoupper($action)) {
        'CREATE' => 'bg-emerald-50 text-emerald-700',
        'UPDATE', 'ARCHIVE', 'RESTORE' => 'bg-amber-50 text-amber-700',
        'DELETE', 'LOCKOUT' => 'bg-red-50 text-red-700',
        'ROUTE', 'SUBMIT', 'APPROVE', 'REJECT' => 'bg-indigo-50 text-indigo-700',
        'LOGIN', 'EXPORT', 'IMPORT' => 'bg-blue-50 text-blue-700',
        default => 'bg-gray-100 text-gray-700',
    };
}
?>

<div class="space-y-6">
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative z-10 px-6 py-7 sm:px-8">
            <div class="max-w-3xl">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-blue-100">ACCOUNT ACTIVITY</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-white sm:text-3xl"><?= htmlspecialchars($pageTitle) ?></h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-blue-100"><?= htmlspecialchars($pageSubtitle) ?></p>
            </div>
        </div>
        <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
    </section>

    <section aria-label="Personal activity summary">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 <?= $showCreatedStat ? 'xl:grid-cols-4' : 'xl:grid-cols-3' ?>">
            <?php
            $historyStats = [
                ['label' => 'My Actions', 'value' => (int) ($stats['total'] ?? 0), 'note' => 'All recorded activity', 'tone' => 'blue'],
                ['label' => 'Updated', 'value' => (int) ($stats['updated'] ?? 0), 'note' => 'Records updated', 'tone' => 'amber'],
                ['label' => 'Routed', 'value' => (int) ($stats['routed'] ?? 0), 'note' => 'Routing actions', 'tone' => 'indigo'],
            ];
            if ($showCreatedStat) {
                array_splice($historyStats, 1, 0, [[
                    'label' => 'Created',
                    'value' => (int) ($stats['created'] ?? 0),
                    'note' => 'Records created',
                    'tone' => 'emerald',
                ]]);
            }
            $historyTones = [
                'blue' => 'bg-primary',
                'emerald' => 'bg-emerald-500',
                'amber' => 'bg-amber-500',
                'indigo' => 'bg-indigo-500',
            ];
            foreach ($historyStats as $stat):
            ?>
                <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <span class="absolute inset-x-0 top-0 h-1 <?= $historyTones[$stat['tone']] ?>"></span>
                    <p class="text-xs font-medium text-gray-500"><?= htmlspecialchars($stat['label']) ?></p>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format($stat['value']) ?></p>
                    <p class="mt-1 text-[11px] text-gray-400"><?= htmlspecialchars($stat['note']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section id="user-history-list" class="rounded-2xl border border-gray-200 bg-white shadow-sm">
        <?php
        $responsiveInboxId = 'user-history-list';
        $responsiveInboxLabels = ['Date / Time', 'Action', 'Activity', 'Record'];
        require __DIR__ . '/../../partials/responsive-inbox-table.php';
        ?>
        <style>
            @media (max-width: 1279px) {
                #user-history-list .inbox-responsive-table tbody > tr:not(.inbox-empty-row) > td:last-child {
                    position: static;
                    width: auto;
                    margin: 0;
                    padding: 0;
                    text-align: left;
                }

                #user-history-list .inbox-responsive-table tbody > tr:not(.inbox-empty-row) > td:last-child::before {
                    content: "Record";
                }
            }
        </style>
        <div class="flex flex-col gap-4 border-b border-gray-100 px-5 py-5 2xl:flex-row 2xl:items-center 2xl:justify-between sm:px-6">
            <div class="shrink-0">
                <h2 class="font-semibold text-gray-900">Activity History</h2>
                <p class="mt-1 text-xs text-gray-500"><?= number_format($totalRows) ?> record<?= $totalRows !== 1 ? 's' : '' ?> found</p>
            </div>
            <form method="GET" action="<?= BASE_URL ?>/<?= htmlspecialchars($historyRoute, ENT_QUOTES, 'UTF-8') ?>"
                  class="hidden w-full min-w-0 flex-wrap items-center justify-end gap-2 2xl:flex 2xl:flex-1">
                <input type="search" name="search" value="<?= htmlspecialchars($search) ?>"
                       placeholder="Search activity or record..."
                       class="block w-full min-w-0 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 2xl:w-56 2xl:flex-none">
                <select name="action" aria-label="Filter by action" class="block w-full min-w-0 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 2xl:w-auto 2xl:flex-none">
                    <option value="">All actions</option>
                    <?php foreach ($actions as $action): ?>
                        <option value="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>" <?= $filterAction === $action ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst(strtolower($action))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>"
                       aria-label="Start date" title="Start date"
                       class="block w-full min-w-0 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 2xl:w-36 2xl:flex-none">
                <input type="date" name="date_to" value="<?= htmlspecialchars($filterDateTo) ?>"
                       aria-label="End date" title="End date"
                       class="block w-full min-w-0 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 2xl:w-36 2xl:flex-none">
                <button type="submit" class="shrink-0 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Filter</button>
                <?php if ($hasFilters): ?>
                    <a href="<?= BASE_URL ?>/<?= htmlspecialchars($historyRoute, ENT_QUOTES, 'UTF-8') ?>"
                       class="inline-flex shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Clear</a>
                <?php endif; ?>
            </form>

            <div class="flex min-w-0 items-center gap-2 2xl:hidden">
                <form method="GET" action="<?= BASE_URL ?>/<?= htmlspecialchars($historyRoute, ENT_QUOTES, 'UTF-8') ?>" class="relative min-w-0 flex-1">
                    <input type="hidden" name="action" value="<?= htmlspecialchars($filterAction, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="date_from" value="<?= htmlspecialchars($filterDateFrom, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="date_to" value="<?= htmlspecialchars($filterDateTo, ENT_QUOTES, 'UTF-8') ?>">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search activity or record..."
                           class="block w-full min-w-0 rounded-xl border border-gray-200 bg-white py-2.5 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </form>
                <button type="button" data-inbox-filter-target="#user-history-filter-sheet" aria-label="Open filters" aria-haspopup="dialog"
                        class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 hover:bg-gray-50">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M6 12h12m-9 8h6"/></svg>
                    <?php if ($filterAction !== '' || $filterDateFrom !== '' || $filterDateTo !== ''): ?>
                        <span class="absolute right-1 top-1 h-2 w-2 rounded-full bg-primary"></span>
                    <?php endif; ?>
                </button>
            </div>

            <div id="user-history-filter-sheet" class="fixed inset-0 z-50 hidden 2xl:hidden">
                <button type="button" class="inbox-filter-backdrop absolute inset-0 bg-gray-950/40" aria-label="Close filters"></button>
                <form method="GET" action="<?= BASE_URL ?>/<?= htmlspecialchars($historyRoute, ENT_QUOTES, 'UTF-8') ?>"
                      role="dialog" aria-modal="true" aria-labelledby="user-history-filter-title"
                      class="absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto rounded-t-2xl bg-white p-5 shadow-2xl">
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-5 flex items-center justify-between">
                        <h3 id="user-history-filter-title" class="font-semibold text-gray-900">Filters</h3>
                        <button type="button" class="inbox-filter-close rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-100">Close</button>
                    </div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700" for="user-history-mobile-action">Action</label>
                    <select id="user-history-mobile-action" name="action" class="mb-4 block w-full min-w-0 rounded-xl border border-gray-200 bg-white px-3 py-3 text-sm text-gray-800">
                        <option value="">All actions</option>
                        <?php foreach ($actions as $action): ?>
                            <option value="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>" <?= $filterAction === $action ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucfirst(strtolower($action))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700" for="user-history-mobile-date-from">Start date</label>
                    <input id="user-history-mobile-date-from" type="date" name="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>"
                           class="mb-4 block w-full min-w-0 rounded-xl border border-gray-200 bg-white px-3 py-3 text-sm text-gray-800">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700" for="user-history-mobile-date-to">End date</label>
                    <input id="user-history-mobile-date-to" type="date" name="date_to" value="<?= htmlspecialchars($filterDateTo) ?>"
                           class="mb-5 block w-full min-w-0 rounded-xl border border-gray-200 bg-white px-3 py-3 text-sm text-gray-800">
                    <div class="grid <?= $hasFilters ? 'grid-cols-2' : 'grid-cols-1' ?> gap-3">
                        <button type="submit" class="w-full rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-white hover:opacity-90">Apply Filters</button>
                        <?php if ($hasFilters): ?>
                            <a href="<?= BASE_URL ?>/<?= htmlspecialchars($historyRoute, ENT_QUOTES, 'UTF-8') ?>"
                               class="flex w-full items-center justify-center rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <?php require __DIR__ . '/../../partials/inbox-mobile-filter-script.php'; ?>
        </div>
        <?php if ($filterError !== null): ?>
            <p class="border-b border-amber-100 bg-amber-50 px-5 py-3 text-sm text-amber-800" role="status"><?= htmlspecialchars($filterError) ?></p>
        <?php endif; ?>

        <div class="overflow-x-auto">
            <table class="inbox-responsive-table w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Date / Time</th>
                        <th class="px-6 py-3 font-medium">Action</th>
                        <th class="px-6 py-3 font-medium">Activity</th>
                        <th class="px-6 py-3 font-medium">Record</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if ($logs === []): ?>
                        <tr class="inbox-empty-row">
                            <td colspan="4" class="px-6 py-16 text-center">
                                <p class="text-sm font-medium text-gray-700">No activity found.</p>
                                <p class="mt-1 text-xs text-gray-500">Recorded actions for your account will appear here.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <?php
                            $timestamp = strtotime((string) ($log['created_at'] ?? ''));
                            $relatedDocument = $log['related_document'] ?? null;
                            $relatedUser = $log['related_user'] ?? null;
                            $relatedFileName = trim((string) ($log['related_file_name'] ?? ''));
                            $relatedLabel = $relatedFileName !== '' ? $relatedFileName : ($relatedDocument['tracking_number'] ?? '');
                            $relatedSubject = trim(implode(' · ', array_filter([
                                $relatedDocument['tracking_number'] ?? '',
                                $relatedDocument['subject_matter'] ?? '',
                            ])));
                            if ($relatedFileName === '' && $relatedDocument !== null) {
                                $relatedSubject = trim((string) ($relatedDocument['subject_matter'] ?? ''));
                            }
                            if ($relatedUser !== null) {
                                $relatedLabel = trim(implode(' ', array_filter([
                                    $relatedUser['first_name'] ?? '',
                                    $relatedUser['last_name'] ?? '',
                                ])));
                                if ($relatedLabel === '' && !empty($relatedUser['username'])) {
                                    $relatedLabel = '@' . $relatedUser['username'];
                                }
                                $relatedSubject = !empty($relatedUser['username']) && $relatedLabel !== '@' . $relatedUser['username']
                                    ? '@' . $relatedUser['username']
                                    : 'User account';
                            }
                            if ($relatedLabel === '') {
                                $relatedLabel = trim(implode(' · ', array_filter([
                                    $log['entity_type'] ?? '',
                                    !empty($log['entity_id']) ? '#' . $log['entity_id'] : '',
                                ])));
                            }
                            ?>
                            <tr class="transition hover:bg-gray-50/50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-900"><?= $timestamp !== false ? htmlspecialchars(date('M j, Y', $timestamp)) : '—' ?></div>
                                    <div class="mt-0.5 text-xs text-gray-500"><?= $timestamp !== false ? htmlspecialchars(date('g:i A', $timestamp)) : '' ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?= historyActionClass((string) $log['action']) ?>">
                                        <?= htmlspecialchars(ucfirst(strtolower((string) $log['action']))) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-700"><?= htmlspecialchars($log['description'] ?: 'No description recorded.') ?></td>
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-800"><?= htmlspecialchars($relatedLabel !== '' ? $relatedLabel : '—') ?></div>
                                    <?php if ($relatedSubject !== ''): ?>
                                        <div class="mt-0.5 max-w-sm text-xs text-gray-500"><?= htmlspecialchars($relatedSubject) ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="flex flex-col items-center justify-between gap-3 border-t border-gray-100 px-5 py-4 sm:flex-row sm:px-6">
            <?php
            $firstRow = $totalRows > 0 ? (($page - 1) * $perPage) + 1 : 0;
            $lastRow = min($page * $perPage, $totalRows);
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            ?>
            <p class="text-xs text-gray-500">
                Showing <span class="font-medium text-gray-700"><?= number_format($firstRow) ?>–<?= number_format($lastRow) ?></span>
                of <span class="font-medium text-gray-700"><?= number_format($totalRows) ?></span> records
            </p>
            <nav class="flex items-center gap-1" aria-label="Activity history pages">
                <?php if ($page > 1): ?>
                    <a href="?<?= htmlspecialchars(http_build_query($query + ['page' => $page - 1]), ENT_QUOTES, 'UTF-8') ?>"
                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Prev</a>
                <?php endif; ?>
                <?php for ($pageNumber = $startPage; $pageNumber <= $endPage; $pageNumber++): ?>
                    <a href="?<?= htmlspecialchars(http_build_query($query + ['page' => $pageNumber]), ENT_QUOTES, 'UTF-8') ?>"
                       aria-current="<?= $pageNumber === $page ? 'page' : 'false' ?>"
                       class="rounded-lg border px-3 py-1.5 text-sm font-medium transition <?= $pageNumber === $page
                           ? 'border-primary bg-primary text-white'
                           : 'border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
                        <?= number_format($pageNumber) ?>
                    </a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?<?= htmlspecialchars(http_build_query($query + ['page' => $page + 1]), ENT_QUOTES, 'UTF-8') ?>"
                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Next</a>
                <?php endif; ?>
            </nav>
        </div>
    </section>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';
?>

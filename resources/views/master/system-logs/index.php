<?php
ob_start();

$success = $success ?? null;
$error = $error ?? null;
$pageTitle = $pageTitle ?? 'System Logs';
$pageSubtitle = $pageSubtitle ?? '';
$totalLogs = $totalLogs ?? 0;
$errorLogs = $errorLogs ?? 0;
$warningLogs = $warningLogs ?? 0;
$infoLogs = $infoLogs ?? 0;
$totalRows = $totalRows ?? 0;
$search = $search ?? '';
$filterLevel = $filterLevel ?? '';
$filterMethod = $filterMethod ?? '';
$filterDateFrom = $filterDateFrom ?? '';
$filterDateTo = $filterDateTo ?? '';
$logs = $logs ?? [];
$totalPages = $totalPages ?? 1;
$page = $page ?? 1;
$accent = $accent ?? 'purple';

function levelBadgeClass(string $level): string {
    return match (strtoupper($level)) {
        'DEBUG' => 'bg-gray-100 text-gray-700',
        'INFO', 'NOTICE' => 'bg-blue-50 text-blue-700',
        'WARNING' => 'bg-amber-50 text-amber-700',
        'ERROR' => 'bg-red-50 text-red-700',
        'CRITICAL', 'ALERT', 'EMERGENCY' => 'bg-red-100 text-red-800 ring-1 ring-red-200',
        default => 'bg-gray-100 text-gray-700',
    };
}

function methodBadgeClass(string $method): string {
    return match (strtoupper($method)) {
        'GET' => 'bg-emerald-50 text-emerald-700',
        'POST' => 'bg-blue-50 text-blue-700',
        'PUT', 'PATCH' => 'bg-amber-50 text-amber-700',
        'DELETE' => 'bg-red-50 text-red-700',
        default => 'bg-gray-100 text-gray-700',
    };
}
?>

<div class="space-y-6">

    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-purple-700 via-violet-600 to-indigo-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-sm font-medium text-purple-100">SYSTEM MONITORING</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                        <?= htmlspecialchars($pageTitle) ?>
                    </h1>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-purple-100">
                        <?= htmlspecialchars($pageSubtitle) ?>
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <?php
                    $exportQuery = [];
                    if ($search !== '') $exportQuery['search'] = $search;
                    if ($filterLevel !== '') $exportQuery['level'] = $filterLevel;
                    if ($filterMethod !== '') $exportQuery['method'] = $filterMethod;
                    if ($filterDateFrom !== '') $exportQuery['date_from'] = $filterDateFrom;
                    if ($filterDateTo !== '') $exportQuery['date_to'] = $filterDateTo;
                    $exportUrl = BASE_URL . '/master/system-logs/export-csv' . (!empty($exportQuery) ? '?' . http_build_query($exportQuery) : '');
                    ?>
                    <a href="<?= $exportUrl ?>"
                        class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-purple-700 shadow-sm hover:bg-purple-50 transition">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Export CSV
                    </a>
                    <button type="button" onclick="openPurgeModal()"
                        class="inline-flex items-center gap-2 rounded-xl bg-red-500/20 ring-1 ring-white/20 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-red-500/30 transition">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Purge Old
                    </button>
                </div>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
        </div>
    </section>

    <section>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Total Logs</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($totalLogs) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-purple-50 text-purple-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-purple-50 px-2 py-1 font-medium text-purple-700">All entries</span>
                </div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Errors</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($errorLogs) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-red-50 text-red-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-red-50 px-2 py-1 font-medium text-red-700">ERROR / CRITICAL</span>
                </div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Warnings</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($warningLogs) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-amber-50 px-2 py-1 font-medium text-amber-700">WARNING level</span>
                </div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Info Events</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($infoLogs) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-blue-50 px-2 py-1 font-medium text-blue-700">INFO / NOTICE</span>
                </div>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-gray-200 bg-white">
        <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900">System Logs List</h2>
                <p class="mt-1 text-xs text-gray-500">
                    <?= $totalRows ?> record<?= $totalRows !== 1 ? 's' : '' ?> found
                </p>
            </div>
            <form method="GET" action="<?= BASE_URL ?>/master/system-logs" class="flex flex-col gap-3 lg:items-end">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="lg:col-span-2">
                        <div class="relative">
                            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search message, URL, IP..."
                                class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20">
                        </div>
                    </div>
                    <select name="level" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20">
                        <option value="">All Levels</option>
                        <option value="DEBUG" <?= $filterLevel === 'DEBUG' ? 'selected' : '' ?>>DEBUG</option>
                        <option value="INFO" <?= $filterLevel === 'INFO' ? 'selected' : '' ?>>INFO</option>
                        <option value="NOTICE" <?= $filterLevel === 'NOTICE' ? 'selected' : '' ?>>NOTICE</option>
                        <option value="WARNING" <?= $filterLevel === 'WARNING' ? 'selected' : '' ?>>WARNING</option>
                        <option value="ERROR" <?= $filterLevel === 'ERROR' ? 'selected' : '' ?>>ERROR</option>
                        <option value="CRITICAL" <?= $filterLevel === 'CRITICAL' ? 'selected' : '' ?>>CRITICAL</option>
                    </select>
                    <select name="method" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20">
                        <option value="">All Methods</option>
                        <option value="GET" <?= $filterMethod === 'GET' ? 'selected' : '' ?>>GET</option>
                        <option value="POST" <?= $filterMethod === 'POST' ? 'selected' : '' ?>>POST</option>
                        <option value="PUT" <?= $filterMethod === 'PUT' ? 'selected' : '' ?>>PUT</option>
                        <option value="DELETE" <?= $filterMethod === 'DELETE' ? 'selected' : '' ?>>DELETE</option>
                    </select>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div>
                        <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-gray-400">From</label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>"
                            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20 sm:w-40">
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-gray-400">To</label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($filterDateTo) ?>"
                            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20 sm:w-40">
                    </div>
                    <div class="flex items-center gap-2 pt-3 sm:pt-5">
                        <button type="submit" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                            Filter
                        </button>
                        <?php if ($search !== '' || $filterLevel !== '' || $filterMethod !== '' || $filterDateFrom !== '' || $filterDateTo !== ''): ?>
                            <a href="<?= BASE_URL ?>/master/system-logs"
                               class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition text-center">
                                Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Level</th>
                        <th class="px-6 py-3 font-medium">Message</th>
                        <th class="px-6 py-3 font-medium">User / IP</th>
                        <th class="px-6 py-3 font-medium">Request</th>
                        <th class="px-6 py-3 font-medium">Timestamp</th>
                        <th class="px-6 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">No system logs found.</p>
                                <p class="mt-1 text-xs text-gray-500">Logs will appear here as system events occur.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                        <tr class="hover:bg-gray-50/50 transition">
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-bold tracking-wide <?= levelBadgeClass($l['log_level']) ?>">
                                    <?= htmlspecialchars($l['log_level']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="max-w-md">
                                    <div class="font-medium text-gray-900 line-clamp-2"><?= htmlspecialchars($l['message']) ?></div>
                                    <?php if ($l['context']): ?>
                                        <div class="mt-1 text-[11px] text-gray-400 inline-flex items-center gap-1">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                                            </svg>
                                            Has context data
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <?php $fullName = trim(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? '')); ?>
                                <?php if ($fullName): ?>
                                    <div class="text-sm font-medium text-gray-800"><?= htmlspecialchars($fullName) ?></div>
                                    <?php if (!empty($l['user_username'])): ?>
                                        <div class="text-[11px] text-gray-400">@<?= htmlspecialchars($l['user_username']) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="text-sm text-gray-500"><?= $l['user_id'] ? 'User #' . $l['user_id'] : 'System' ?></div>
                                <?php endif; ?>
                                <?php if (!empty($l['ip_address'])): ?>
                                    <div class="mt-0.5 inline-flex items-center rounded-md bg-gray-100 px-1.5 py-0.5 text-[10px] font-mono text-gray-600">
                                        <?= htmlspecialchars($l['ip_address']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if (!empty($l['request_method'])): ?>
                                    <span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-bold tracking-wide <?= methodBadgeClass($l['request_method']) ?>">
                                        <?= htmlspecialchars($l['request_method']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($l['request_url'])): ?>
                                    <div class="mt-1 font-mono text-[11px] text-gray-500 max-w-xs truncate" title="<?= htmlspecialchars($l['request_url']) ?>">
                                        <?= htmlspecialchars($l['request_url']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-800"><?= date('M j, Y', strtotime($l['created_at'])) ?></div>
                                <div class="text-[11px] text-gray-400"><?= date('h:i A', strtotime($l['created_at'])) ?></div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button type="button" onclick="viewLog(<?= $l['id'] ?>)"
                                    class="rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:border-purple-400 hover:bg-purple-50 hover:text-purple-600 transition"
                                    title="View Details">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </button>
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
                Showing page <span class="font-medium text-gray-700"><?= $page ?></span> of
                <span class="font-medium text-gray-700"><?= $totalPages ?></span>
                (<?= $totalRows ?> total records)
            </p>
            <div class="flex items-center gap-1">
                <?php
                $query = [];
                if ($search !== '') $query['search'] = $search;
                if ($filterLevel !== '') $query['level'] = $filterLevel;
                if ($filterMethod !== '') $query['method'] = $filterMethod;
                if ($filterDateFrom !== '') $query['date_from'] = $filterDateFrom;
                if ($filterDateTo !== '') $query['date_to'] = $filterDateTo;
                $queryString = !empty($query) ? '&' . http_build_query($query) : '';
                ?>
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $queryString ?>"
                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                        Prev
                    </a>
                <?php endif; ?>
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                    <a href="?page=<?= $i ?><?= $queryString ?>"
                       class="rounded-lg border px-3 py-1.5 text-sm font-medium transition <?= $i === $page
                           ? 'border-purple-600 bg-purple-600 text-white'
                           : 'border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $queryString ?>"
                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                        Next
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </section>

</div>

<!-- Log Detail Modal -->
<div id="logDetailModal" class="fixed inset-0 z-[100] hidden items-center justify-center" role="dialog" aria-modal="true">
    <div id="logDetailModalBackdrop" class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity opacity-0"></div>
    <div id="logDetailModalPanel" class="relative z-10 w-full max-w-2xl mx-4 bg-white rounded-2xl shadow-2xl border border-gray-100 transform scale-95 opacity-0 transition-all duration-200 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">System Log Details</h3>
                <p id="logDetailSubtitle" class="mt-0.5 text-xs text-gray-500">—</p>
            </div>
            <button type="button" onclick="closeLogDetailModal()" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div id="logDetailContent" class="p-6 space-y-4">
            <div class="text-center py-8 text-gray-400">Loading...</div>
        </div>
        <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4 bg-gray-50 rounded-b-2xl">
            <button type="button" onclick="closeLogDetailModal()"
                class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 transition">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Purge Modal -->
<div id="purgeModal" class="fixed inset-0 z-[100] hidden items-center justify-center" role="dialog" aria-modal="true">
    <div id="purgeModalBackdrop" class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity opacity-0"></div>
    <div id="purgeModalPanel" class="relative z-10 w-full max-w-md mx-4 bg-white rounded-2xl shadow-2xl border border-gray-100 transform scale-95 opacity-0 transition-all duration-200">
        <div class="p-6">
            <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-red-100">
                    <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-gray-900">Purge Old System Logs</h3>
                    <p class="mt-1 text-sm text-gray-600">This permanently deletes logs older than the specified days. This action cannot be undone.</p>
                </div>
            </div>
            <div class="mt-5">
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">
                    Delete logs older than (days)
                </label>
                <input type="number" id="purgeDays" min="7" value="90"
                    class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:border-red-400 focus:outline-none focus:ring-2 focus:ring-red-400/20">
                <p class="mt-1 text-[11px] text-gray-500">Minimum 7 days retention required.</p>
            </div>
        </div>
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
            <button type="button" onclick="closePurgeModal()"
                class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 transition">
                Cancel
            </button>
            <button type="button" onclick="executePurge()"
                class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 transition shadow-sm">
                <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Purge Logs
            </button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="fixed top-4 right-4 z-[200] space-y-2 pointer-events-none"></div>

<script>
const BASE = '<?= BASE_URL ?>';

function levelBadgeClass(level) {
    const map = {
        'DEBUG': 'bg-gray-100 text-gray-700',
        'INFO': 'bg-blue-50 text-blue-700',
        'NOTICE': 'bg-blue-50 text-blue-700',
        'WARNING': 'bg-amber-50 text-amber-700',
        'ERROR': 'bg-red-50 text-red-700',
        'CRITICAL': 'bg-red-100 text-red-800 ring-1 ring-red-200',
        'ALERT': 'bg-red-100 text-red-800 ring-1 ring-red-200',
        'EMERGENCY': 'bg-red-100 text-red-800 ring-1 ring-red-200'
    };
    return map[level] || 'bg-gray-100 text-gray-700';
}

function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    const colors = {
        success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
        error: 'border-red-200 bg-red-50 text-red-800',
        info: 'border-blue-200 bg-blue-50 text-blue-800'
    };
    const icons = {
        success: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>',
        error: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>',
        info: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'
    };
    const iconColors = {
        success: 'bg-emerald-100 text-emerald-600',
        error: 'bg-red-100 text-red-600',
        info: 'bg-blue-100 text-blue-600'
    };
    toast.className = `pointer-events-auto max-w-sm rounded-xl border px-4 py-3 flex items-center gap-3 shadow-lg transform transition-all duration-300 translate-x-full ${colors[type]}`;
    toast.innerHTML = `
        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full ${iconColors[type]}">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">${icons[type]}</svg>
        </div>
        <p class="text-sm font-medium flex-1">${message}</p>
        <button type="button" onclick="this.parentElement.remove()" class="text-current opacity-60 hover:opacity-100">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    `;
    container.appendChild(toast);
    requestAnimationFrame(() => toast.classList.remove('translate-x-full'));
    setTimeout(() => {
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.remove(), 300);
    }, 4500);
}

let logDetailModalOpen = false;
function openLogDetailModal() {
    if (logDetailModalOpen) return;
    logDetailModalOpen = true;
    const m = document.getElementById('logDetailModal');
    const b = document.getElementById('logDetailModalBackdrop');
    const p = document.getElementById('logDetailModalPanel');
    m.classList.remove('hidden');
    m.classList.add('flex');
    requestAnimationFrame(() => {
        b.classList.remove('opacity-0');
        p.classList.remove('scale-95', 'opacity-0');
        p.classList.add('scale-100', 'opacity-100');
    });
}
function closeLogDetailModal() {
    if (!logDetailModalOpen) return;
    logDetailModalOpen = false;
    const m = document.getElementById('logDetailModal');
    const b = document.getElementById('logDetailModalBackdrop');
    const p = document.getElementById('logDetailModalPanel');
    b.classList.add('opacity-0');
    p.classList.remove('scale-100', 'opacity-100');
    p.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        m.classList.remove('flex');
        m.classList.add('hidden');
    }, 200);
}
document.getElementById('logDetailModalBackdrop')?.addEventListener('click', closeLogDetailModal);

async function viewLog(id) {
    document.getElementById('logDetailContent').innerHTML = '<div class="text-center py-8 text-gray-400">Loading...</div>';
    openLogDetailModal();
    try {
        const res = await fetch(`<?= BASE_URL ?>/master/system-logs/show?id=${id}`);
        const data = await res.json();
        if (!data.success) {
            document.getElementById('logDetailContent').innerHTML = `<div class="text-center py-8 text-red-500">${data.message}</div>`;
            return;
        }
        const l = data.data;
        const fullName = (l.first_name || l.last_name) ? `${l.first_name || ''} ${l.last_name || ''}`.trim() : (l.user_id ? `User #${l.user_id}` : 'System');
        document.getElementById('logDetailSubtitle').textContent = `Log #${l.id} — ${l.created_at}`;

        const contextHtml = l.context && Object.keys(l.context).length
            ? `<pre class="mt-2 rounded-lg bg-gray-900 p-4 text-[11px] leading-relaxed text-gray-100 overflow-x-auto">${JSON.stringify(l.context, null, 2)}</pre>`
            : '<span class="text-xs text-gray-400 italic">No context data</span>';

        document.getElementById('logDetailContent').innerHTML = `
            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center rounded-md px-2.5 py-1 text-[11px] font-bold tracking-wide ${levelBadgeClass(l.log_level)}">${l.log_level}</span>
                    ${l.request_method ? `<span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold tracking-wide bg-gray-100 text-gray-700">${l.request_method}</span>` : ''}
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <p class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Message</p>
                    <p class="text-sm text-gray-800 whitespace-pre-wrap">${l.message ? l.message.replace(/</g, '&lt;') : ''}</p>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-gray-400">User</p>
                        <p class="text-sm font-medium text-gray-800">${fullName}</p>
                        ${l.user_username ? `<p class="text-[11px] text-gray-400">@${l.user_username}</p>` : ''}
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-gray-400">IP Address</p>
                        <p class="text-sm font-mono text-gray-800">${l.ip_address || '—'}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4 sm:col-span-2">
                        <p class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Request URL</p>
                        <p class="text-sm font-mono text-gray-800 break-all">${l.request_url || '—'}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Timestamp</p>
                        <p class="text-sm text-gray-800">${l.created_at}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-gray-400">User Agent</p>
                        <p class="text-xs text-gray-600 max-w-xs truncate" title="${l.user_agent || ''}">${l.user_agent || '—'}</p>
                    </div>
                </div>
                <div>
                    <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Context Data</p>
                    ${contextHtml}
                </div>
            </div>
        `;
    } catch (e) {
        document.getElementById('logDetailContent').innerHTML = '<div class="text-center py-8 text-red-500">Failed to load log details.</div>';
    }
}

let purgeModalOpen = false;
function openPurgeModal() {
    if (purgeModalOpen) return;
    purgeModalOpen = true;
    document.getElementById('purgeDays').value = 90;
    const m = document.getElementById('purgeModal');
    const b = document.getElementById('purgeModalBackdrop');
    const p = document.getElementById('purgeModalPanel');
    m.classList.remove('hidden');
    m.classList.add('flex');
    requestAnimationFrame(() => {
        b.classList.remove('opacity-0');
        p.classList.remove('scale-95', 'opacity-0');
        p.classList.add('scale-100', 'opacity-100');
    });
}
function closePurgeModal() {
    if (!purgeModalOpen) return;
    purgeModalOpen = false;
    const m = document.getElementById('purgeModal');
    const b = document.getElementById('purgeModalBackdrop');
    const p = document.getElementById('purgeModalPanel');
    b.classList.add('opacity-0');
    p.classList.remove('scale-100', 'opacity-100');
    p.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        m.classList.remove('flex');
        m.classList.add('hidden');
    }, 200);
}
document.getElementById('purgeModalBackdrop')?.addEventListener('click', closePurgeModal);

async function executePurge() {
    const days = parseInt(document.getElementById('purgeDays').value);
    if (isNaN(days) || days < 7) {
        showToast('Retention must be at least 7 days.', 'error');
        return;
    }
    try {
        const fd = new FormData();
        fd.append('days', days);
        const res = await fetch('<?= BASE_URL ?>/master/system-logs/clear-old', { method: 'POST', body: fd });
        const data = await res.json();
        showToast(data.message, data.success ? 'success' : 'error');
        if (data.success) {
            closePurgeModal();
            setTimeout(() => location.reload(), 900);
        }
    } catch (e) { showToast('Network error.', 'error'); }
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        if (logDetailModalOpen) closeLogDetailModal();
        if (purgeModalOpen) closePurgeModal();
    }
});
<?php if ($success): ?>
showToast(<?= json_encode((string)$success) ?>, 'success');
<?php endif; ?>
<?php if ($error): ?>
showToast(<?= json_encode((string)$error) ?>, 'error');
<?php endif; ?>
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

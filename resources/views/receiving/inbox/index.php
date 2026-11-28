<?php
/**
 * Receiving Inbox — list of returned and accepted documents
 *
 * Variables supplied by ReceivingInboxController::index():
 *   $assignments      array   Rows from the DB (each row is one assignment)
 *   $page             int
 *   $totalPages       int
 *   $total            int
 *   $search           string
 *   $currentView      string  'returned' or 'accepted'
 *   $returnedCount    int     Count of returned documents
 *   $acceptedCount    int     Count of accepted documents
 *   $success          string|null
 *   $error            string|null
 *   $errors           array
 */

$assignments     = $assignments     ?? [];
$page            = $page            ?? 1;
$totalPages      = $totalPages      ?? 1;
$total           = $total           ?? 0;
$search          = $search          ?? '';
$currentView     = $currentView     ?? 'returned';
$returnedCount   = $returnedCount   ?? 0;
$acceptedCount   = $acceptedCount   ?? 0;

ob_start();

/** Map a phase constant to a readable label */
function inboxPhaseLabel(string $phase): string {
    return match ($phase) {
        'RECEIVING'    => 'Receiving',
        'ADMIN'        => 'Admin',
        'SP_SECRETARY' => 'SP Secretary',
        'PLENARY'      => 'Plenary',
        'COMMITTEE'    => 'Committee',
        'FINALIZED'    => 'Finalized',
        'FILED'        => 'Filed',
        default        => $phase,
    };
}

/** Format datetime for display */
function formatDateTime(?string $datetime): string {
    if (!$datetime) return '—';
    $dt = new DateTime($datetime);
    return $dt->format('M d, Y g:i A');
}

/** Format date only */
function formatDateOnly(?string $date): string {
    if (!$date) return '—';
    $dt = new DateTime($date);
    return $dt->format('M d, Y');
}

// Page title and subtitle based on current view
$viewTitles = [
    'returned'  => ['title' => 'Returned Documents', 'subtitle' => 'Documents returned by Admin awaiting correction and re-submission'],
    'accepted'  => ['title' => 'Accepted Documents', 'subtitle' => 'Documents corrected and re-routed to Admin'],
];
$pageTitle = $viewTitles[$currentView]['title'] ?? 'Receiving Inbox';
$pageSubtitle = $viewTitles[$currentView]['subtitle'] ?? '';
$documentTypeId = $documentTypeId ?? 0;
$documentTypes = $documentTypes ?? [];
$filterQuery = ['view' => $currentView];
if ($search !== '') {
    $filterQuery['search'] = $search;
}
if ($documentTypeId > 0) {
    $filterQuery['document_type_id'] = $documentTypeId;
}
$returnedQuery = $filterQuery;
$returnedQuery['view'] = 'returned';
$acceptedQuery = $filterQuery;
$acceptedQuery['view'] = 'accepted';
?>

<div class="space-y-6">

    <!-- Page header ---------------------------------------------------------->
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative z-10 flex flex-col gap-6 px-6 py-7 sm:px-8 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-blue-100">Receiving Section · Inbox</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-white sm:text-3xl"><?= htmlspecialchars($pageTitle) ?></h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-blue-100">
                    <?= htmlspecialchars($pageSubtitle) ?>
                </p>
            </div>
            <div class="flex items-center gap-3 border-t border-white/15 pt-4 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
                <span class="flex h-2.5 w-2.5 rounded-full <?= $currentView === 'returned' ? 'bg-rose-300 ring-4 ring-rose-300/20' : 'bg-emerald-300 ring-4 ring-emerald-300/20' ?>"></span>
                <div>
                    <p class="text-xs font-medium text-white"><?= $currentView === 'returned' ? 'Correction queue' : 'Processed queue' ?></p>
                    <p class="mt-0.5 text-xs text-blue-100/80"><?= htmlspecialchars(date('l, F j, Y')) ?></p>
                </div>
            </div>
        </div>
        <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
    </section>

    <!-- Statistics Cards ----------------------------------------------------->
    <section>
        <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-primary">At a glance</p>
                <h2 class="mt-1 text-xl font-semibold text-gray-900">Inbox activity</h2>
            </div>
            <p class="text-xs text-gray-500">Returned items and corrected submissions</p>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-x-0 top-0 h-1 bg-rose-500"></span>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Returned by Admin</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format($returnedCount) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-[11px] text-gray-400">Shared queue requiring correction</p>
            </article>

            <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-x-0 top-0 h-1 bg-emerald-500"></span>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Corrected & Re-routed</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format($acceptedCount) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-[11px] text-gray-400">Corrected and sent back to Admin</p>
            </article>
        </div>
    </section>

    <!-- Navigation Tabs ------------------------------------------------------>
    <section>
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex gap-6 overflow-x-auto" aria-label="Inbox views">
                <a href="<?= BASE_URL ?>/receiving/inbox?<?= htmlspecialchars(http_build_query($returnedQuery), ENT_QUOTES, 'UTF-8') ?>"
                   <?= $currentView === 'returned' ? 'aria-current="page"' : '' ?>
                   class="<?= $currentView === 'returned' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' ?> inline-flex items-center gap-2 whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition">
                    Returned
                    <?php if ($returnedCount > 0): ?>
                        <span class="inline-flex min-w-6 items-center justify-center rounded-full <?= $currentView === 'returned' ? 'bg-rose-100 text-rose-700' : 'bg-gray-100 text-gray-600' ?> px-2 py-0.5 text-xs font-semibold">
                            <?= $returnedCount ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="<?= BASE_URL ?>/receiving/inbox?<?= htmlspecialchars(http_build_query($acceptedQuery), ENT_QUOTES, 'UTF-8') ?>"
                   <?= $currentView === 'accepted' ? 'aria-current="page"' : '' ?>
                   class="<?= $currentView === 'accepted' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' ?> inline-flex items-center gap-2 whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition">
                    Accepted
                    <?php if ($acceptedCount > 0): ?>
                        <span class="inline-flex min-w-6 items-center justify-center rounded-full <?= $currentView === 'accepted' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' ?> px-2 py-0.5 text-xs font-semibold">
                            <?= $acceptedCount ?>
                        </span>
                    <?php endif; ?>
                </a>
            </nav>
        </div>
    </section>

    <!-- Document List Section -->
    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <style>
            @media (min-width: 1280px) {
                #inboxDesktopFilters { display: flex !important; }
                #inboxMobileControls, #inboxFilterSheet, #inboxMobileCards { display: none !important; }
                #inboxDesktopTable { display: block !important; }
            }
        </style>
        <div class="flex min-w-0 flex-col gap-4 border-b border-gray-100 px-5 py-5 md:px-6 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Documents List</h2>
                <p class="mt-1 text-xs text-gray-500"><?= $total ?> record<?= $total !== 1 ? 's' : '' ?> found</p>
            </div>
            <form id="inboxDesktopFilters" method="GET" action="<?= BASE_URL ?>/receiving/inbox" class="hidden w-full min-w-0 items-center justify-end gap-2 xl:flex xl:flex-1">
                <input type="hidden" name="view" value="<?= htmlspecialchars($currentView) ?>">
                <div class="relative w-full min-w-0 flex-none" style="max-width: 320px;">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search tracking # or subject..."
                        class="block w-full min-w-0 rounded-lg border border-gray-200 bg-white py-2.5 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </div>
                <select name="document_type_id" aria-label="Filter by document type" class="w-44 min-w-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">All Document Types</option>
                    <?php foreach ($documentTypes as $documentType): ?>
                        <option value="<?= (int) $documentType['id'] ?>" <?= (int) $documentTypeId === (int) $documentType['id'] ? 'selected' : '' ?>><?= htmlspecialchars($documentType['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="shrink-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Filter</button>
                <?php if ($search !== '' || $documentTypeId > 0): ?>
                    <a href="<?= BASE_URL ?>/receiving/inbox?view=<?= htmlspecialchars($currentView) ?>" class="shrink-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Clear</a>
                <?php endif; ?>
            </form>

            <div id="inboxMobileControls" class="flex min-w-0 items-center gap-2 xl:hidden">
                <div class="relative min-w-0 flex-1">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <form method="GET" action="<?= BASE_URL ?>/receiving/inbox">
                        <input type="hidden" name="view" value="<?= htmlspecialchars($currentView) ?>">
                        <input type="hidden" name="document_type_id" value="<?= (int) $documentTypeId ?>">
                        <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search tracking # or subject..." class="block w-full min-w-0 rounded-lg border border-gray-200 py-2.5 pl-9 pr-3 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    </form>
                </div>
                <button type="button" id="inboxFilterButton" aria-label="Open filters" class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M6 12h12m-9 8h6"/></svg>
                    <?php if ($documentTypeId > 0): ?><span class="absolute right-1 top-1 h-2 w-2 rounded-full bg-primary"></span><?php endif; ?>
                </button>
            </div>
        </div>

        <div id="inboxFilterSheet" class="fixed inset-0 z-50 hidden xl:hidden">
            <button type="button" id="inboxFilterBackdrop" class="absolute inset-0 bg-gray-950/40" aria-label="Close filters"></button>
            <form method="GET" action="<?= BASE_URL ?>/receiving/inbox" class="absolute inset-x-0 bottom-0 rounded-t-2xl bg-white p-5 shadow-2xl">
                <input type="hidden" name="view" value="<?= htmlspecialchars($currentView) ?>">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                <div class="mb-4 flex items-center justify-between"><h3 class="font-semibold text-gray-900">Filters</h3><button type="button" id="inboxFilterClose" class="text-sm text-gray-500">Close</button></div>
                <div class="space-y-3">
                    <select name="document_type_id" aria-label="Filter by document type" class="w-full rounded-lg border border-gray-200 px-3 py-3 text-sm"><option value="">All Document Types</option><?php foreach ($documentTypes as $documentType): ?><option value="<?= (int) $documentType['id'] ?>" <?= (int) $documentTypeId === (int) $documentType['id'] ? 'selected' : '' ?>><?= htmlspecialchars($documentType['name']) ?></option><?php endforeach; ?></select>
                    <button type="submit" class="w-full rounded-lg bg-primary px-4 py-3 text-sm font-semibold text-white">Apply Filters</button>
                </div>
            </form>
        </div>

        <div id="inboxDesktopTable" class="hidden overflow-x-auto xl:block">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-100 bg-gray-50/70 text-[11px] uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="w-[18%] px-6 py-3 font-bold">Tracking Number</th>
                        <th class="w-[34%] px-6 py-3 font-bold">Subject Matter / Document Type</th>
                        <th class="w-[16%] px-6 py-3 font-bold">Status</th>
                        <th class="w-[16%] px-6 py-3 font-bold"><?= $currentView === 'returned' ? 'Returned By' : 'Accepted By' ?></th>
                        <th class="w-[16%] px-6 py-3 text-right font-bold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($assignments)): ?>
                        <tr><td colspan="5" class="px-6 py-16 text-center"><p class="text-sm font-medium text-gray-700"><?= $currentView === 'returned' ? 'No returned documents found.' : 'No accepted returned documents found.' ?></p><p class="mt-1 text-xs text-gray-500">Try adjusting your search or filters.</p></td></tr>
                    <?php else: ?>
                        <?php foreach ($assignments as $assignment): ?>
                            <?php
                            $statusName = trim((string) ($assignment['status'] ?? ''));
                            $statusKey = strtolower($statusName);
                            if (str_contains($statusKey, 'pending') || str_contains($statusKey, 'return')) { $statusClass = 'bg-amber-50 text-amber-700'; $dotClass = 'bg-amber-500'; }
                            elseif (str_contains($statusKey, 'complete') || str_contains($statusKey, 'filed')) { $statusClass = 'bg-gray-100 text-gray-600'; $dotClass = 'bg-gray-400'; }
                            else { $statusClass = 'bg-emerald-50 text-emerald-700'; $dotClass = 'bg-emerald-500'; }
                            ?>
                            <tr class="transition hover:bg-blue-50/30">
                                <td class="px-6 py-4"><a href="<?= BASE_URL ?>/receiving/inbox/show?id=<?= $assignment['document_id'] ?>" class="font-semibold text-gray-900 hover:text-primary"><?= htmlspecialchars($assignment['tracking_number']) ?></a><div class="mt-1 text-xs text-gray-400"><?= !empty($assignment['date_received']) ? formatDateOnly($assignment['date_received']) : '—' ?></div></td>
                                <td class="px-6 py-4"><div class="max-w-md text-sm font-medium text-gray-900"><?= htmlspecialchars($assignment['subject_matter']) ?></div><span class="mt-1.5 inline-flex rounded-full px-2 py-0.5 text-xs font-medium" style="background-color: <?= htmlspecialchars($assignment['document_type_badge_color'] ?? '#2563EB') ?>14; color: <?= htmlspecialchars($assignment['document_type_badge_color'] ?? '#2563EB') ?>;"><?= htmlspecialchars($assignment['document_type_name'] ?? '—') ?></span></td>
                                <td class="px-6 py-4"><span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold <?= $statusClass ?>"><span class="h-1.5 w-1.5 rounded-full <?= $dotClass ?>"></span><?= htmlspecialchars($statusName ?: '—') ?></span></td>
                                <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars($currentView === 'returned' ? ($assignment['declined_by_name'] ?? $assignment['declined_by_username'] ?? '—') : ($assignment['accepted_by_name'] ?? $assignment['accepted_by_username'] ?? '—')) ?></td>
                                <td class="px-6 py-4 text-right">
                                    <a href="<?= BASE_URL ?>/receiving/inbox/show?id=<?= $assignment['document_id'] ?>"
                                       title="<?= $currentView === 'returned' ? 'View document' : 'Edit document' ?>"
                                       aria-label="<?= $currentView === 'returned' ? 'View document' : 'Edit document' ?>"
                                       class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:border-primary hover:bg-blue-50 hover:text-primary">
                                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        <span><?= $currentView === 'returned' ? 'View' : 'Edit' ?></span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div id="inboxMobileCards" class="divide-y divide-gray-100 xl:hidden">
            <?php if (empty($assignments)): ?>
                <div class="px-5 py-12 text-center"><p class="text-sm font-medium text-gray-700"><?= $currentView === 'returned' ? 'No returned documents found.' : 'No accepted returned documents found.' ?></p><p class="mt-1 text-xs text-gray-500">Try adjusting your search or filters.</p></div>
            <?php else: ?>
                <?php foreach ($assignments as $assignment): ?>
                    <?php
                    $statusName = trim((string) ($assignment['status'] ?? ''));
                    $statusKey = strtolower($statusName);
                    if (str_contains($statusKey, 'pending') || str_contains($statusKey, 'return')) { $statusClass = 'bg-amber-50 text-amber-700'; $dotClass = 'bg-amber-500'; }
                    elseif (str_contains($statusKey, 'complete') || str_contains($statusKey, 'filed')) { $statusClass = 'bg-gray-100 text-gray-600'; $dotClass = 'bg-gray-400'; }
                    else { $statusClass = 'bg-emerald-50 text-emerald-700'; $dotClass = 'bg-emerald-500'; }
                    ?>
                    <article class="px-5 py-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <a href="<?= BASE_URL ?>/receiving/inbox/show?id=<?= $assignment['document_id'] ?>" class="font-semibold text-gray-900"><?= htmlspecialchars($assignment['tracking_number']) ?></a>
                                <div class="mt-1 text-xs text-gray-400"><?= !empty($assignment['date_received']) ? formatDateOnly($assignment['date_received']) : '—' ?></div>
                            </div>
                        </div>
                        <p class="mt-3 text-sm font-medium text-gray-900"><?= htmlspecialchars($assignment['subject_matter']) ?></p>
                        <span class="mt-1.5 inline-flex rounded-full px-2 py-0.5 text-xs font-medium" style="background-color: <?= htmlspecialchars($assignment['document_type_badge_color'] ?? '#2563EB') ?>14; color: <?= htmlspecialchars($assignment['document_type_badge_color'] ?? '#2563EB') ?>;"><?= htmlspecialchars($assignment['document_type_name'] ?? '—') ?></span>
                        <div class="mt-3 flex items-center justify-between gap-3"><span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold <?= $statusClass ?>"><span class="h-1.5 w-1.5 rounded-full <?= $dotClass ?>"></span><?= htmlspecialchars($statusName ?: '—') ?></span><span class="text-sm text-gray-600"><?= htmlspecialchars($currentView === 'returned' ? ($assignment['declined_by_name'] ?? $assignment['declined_by_username'] ?? '—') : ($assignment['accepted_by_name'] ?? $assignment['accepted_by_username'] ?? '—')) ?></span></div>
                        <div class="mt-4 flex justify-end border-t border-gray-100 pt-3">
                            <a href="<?= BASE_URL ?>/receiving/inbox/show?id=<?= $assignment['document_id'] ?>"
                               title="<?= $currentView === 'returned' ? 'View document' : 'Edit document' ?>"
                               aria-label="<?= $currentView === 'returned' ? 'View document' : 'Edit document' ?>"
                               class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:border-primary hover:bg-blue-50 hover:text-primary">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <span><?= $currentView === 'returned' ? 'View' : 'Edit' ?></span>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -------------------------------------------------------->
        <div class="flex flex-col items-center justify-between gap-3 border-t border-gray-100 px-6 py-4 sm:flex-row">
            <p class="text-xs text-gray-500">
                Showing page <span class="font-medium text-gray-700"><?= $page ?></span> of
                <span class="font-medium text-gray-700"><?= $totalPages ?></span>
                (<?= $total ?> total records)
            </p>
            <div class="flex items-center gap-1">
                <?php
                $query = ['view' => $currentView];
                if ($search !== '') $query['search'] = $search;
                if ($documentTypeId > 0) $query['document_type_id'] = $documentTypeId;
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
                $endPage   = min($totalPages, $page + 2);
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                    <a href="?page=<?= $i ?><?= $queryString ?>"
                       class="rounded-lg border px-3 py-1.5 text-sm font-medium transition <?= $i === $page
                           ? 'border-primary bg-primary text-white'
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
    </section>

</div>

<script>
(function () {
    const sheet = document.getElementById('inboxFilterSheet');
    const openButton = document.getElementById('inboxFilterButton');
    const closeButton = document.getElementById('inboxFilterClose');
    const backdrop = document.getElementById('inboxFilterBackdrop');
    if (!sheet || !openButton) return;
    const openSheet = () => { sheet.classList.remove('hidden'); document.body.classList.add('overflow-hidden'); };
    const closeSheet = () => { sheet.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); };
    openButton.addEventListener('click', openSheet);
    closeButton?.addEventListener('click', closeSheet);
    backdrop?.addEventListener('click', closeSheet);
})();
</script>

<?php
$content = ob_get_clean();
$pageTitle = 'Receiving Inbox';
require __DIR__ . '/../../layouts/app.php';
?>

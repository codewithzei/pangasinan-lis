<?php
/**
 * Admin Inbox — list of pending document assignments
 *
 * Variables supplied by AdminInboxController::index():
 *   $assignments      array   Rows from the DB (each row is one assignment)
 *   $page             int
 *   $totalPages       int
 *   $total            int
 *   $search           string
 *   $currentView      string  'inbox' or 'accepted'
 *   $pendingCount     int     Count of pending documents
 *   $acceptedCount    int     Count of accepted documents
 *   $totalCount       int     Count of all Admin documents
 *   $success          string|null
 *   $error            string|null
 *   $errors           array
 */

$assignments     = $assignments     ?? [];
$page            = $page            ?? 1;
$totalPages      = $totalPages      ?? 1;
$total           = $total           ?? 0;
$search          = $search          ?? '';
$currentView     = $currentView     ?? 'inbox';
$pendingCount    = $pendingCount    ?? 0;
$acceptedCount   = $acceptedCount   ?? 0;
$totalCount      = $totalCount      ?? 0;
$documentTypeId = $documentTypeId ?? 0;
$documentTypes  = $documentTypes ?? [];
$typeFilterQuery = $documentTypeId > 0 ? '&document_type_id=' . (int) $documentTypeId : '';

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

// Page title and subtitle based on current view
$viewTitles = [
    'inbox'    => ['title' => 'Inbox', 'subtitle' => 'Pending documents awaiting Admin review and routing'],
    'accepted' => ['title' => 'Accepted Documents', 'subtitle' => 'Documents you have accepted and are currently processing'],
];
$pageTitle = $viewTitles[$currentView]['title'] ?? 'Admin Inbox';
$pageSubtitle = $viewTitles[$currentView]['subtitle'] ?? '';
?>

<div class="space-y-6">

    <!-- Page header ---------------------------------------------------------->
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative z-10 flex flex-col gap-6 px-6 py-7 sm:px-8 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-blue-100">Admin Section · Inbox</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-white sm:text-3xl"><?= htmlspecialchars($pageTitle) ?></h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-blue-100">
                    <?= htmlspecialchars($pageSubtitle) ?>
                </p>
            </div>
            <div class="flex items-center gap-3 border-t border-white/15 pt-4 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
                <span class="flex h-2.5 w-2.5 rounded-full <?= $currentView === 'inbox' ? 'bg-amber-300 ring-4 ring-amber-300/20' : 'bg-emerald-300 ring-4 ring-emerald-300/20' ?>"></span>
                <div>
                    <p class="text-xs font-medium text-white"><?= $currentView === 'inbox' ? 'Pending review queue' : 'Accepted documents' ?></p>
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
            <p class="text-xs text-gray-500">Pending items and accepted documents</p>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-x-0 top-0 h-1 bg-amber-500"></span>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Pending Inbox</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format($pendingCount) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-[11px] text-gray-400">Documents awaiting Admin review and routing</p>
            </article>

            <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <span class="absolute inset-x-0 top-0 h-1 bg-emerald-500"></span>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Accepted Documents</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format($acceptedCount) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-[11px] text-gray-400">Documents you have accepted for processing</p>
            </article>
        </div>
    </section>

    <!-- Navigation Tabs ------------------------------------------------------>
    <section>
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex gap-6">
                <a href="<?= BASE_URL ?>/admin/inbox?view=inbox<?= $search ? '&search=' . urlencode($search) : '' ?><?= $typeFilterQuery ?>"
                   class="<?= $currentView === 'inbox' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' ?> whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition">
                    Inbox
                    <?php if ($pendingCount > 0): ?>
                        <span class="ml-2 inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">
                            <?= $pendingCount ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="<?= BASE_URL ?>/admin/inbox?view=accepted<?= $search ? '&search=' . urlencode($search) : '' ?><?= $typeFilterQuery ?>"
                   class="<?= $currentView === 'accepted' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' ?> whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition">
                    Accepted
                    <?php if ($acceptedCount > 0): ?>
                        <span class="ml-2 inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">
                            <?= $acceptedCount ?>
                        </span>
                    <?php endif; ?>
                </a>
            </nav>
        </div>
    </section>

    <!-- Document List Section ----------------------------------------------->
    <section id="admin-inbox-list" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex min-w-0 flex-col gap-4 border-b border-gray-100 px-5 py-5 md:px-6 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900">Documents List</h2>
                <p class="mt-1 text-xs text-gray-500">
                    <?= $total ?> record<?= $total !== 1 ? 's' : '' ?> found
                </p>
            </div>
            <form id="admin-inbox-desktop-filters" method="GET" action="<?= BASE_URL ?>/admin/inbox" class="hidden w-full min-w-0 items-center justify-end gap-2 xl:flex xl:flex-1">
                <input type="hidden" name="view" value="<?= htmlspecialchars($currentView) ?>">
                <div class="relative w-full min-w-0 flex-none" style="max-width: 320px;">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search tracking # or subject..."
                        class="block w-full min-w-0 rounded-lg border border-gray-200 bg-white py-2.5 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </div>
                <select name="document_type_id" aria-label="Filter by document type"
                    class="w-44 min-w-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">All document types</option>
                    <?php foreach ($documentTypes as $documentType): ?>
                        <option value="<?= (int) $documentType['id'] ?>" <?= (int) $documentTypeId === (int) $documentType['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($documentType['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="shrink-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                    Filter
                </button>
                <?php if ($search !== '' || $documentTypeId > 0): ?>
                    <a href="<?= BASE_URL ?>/admin/inbox?view=<?= htmlspecialchars($currentView) ?>"
                       class="shrink-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition text-center">
                        Clear
                    </a>
                <?php endif; ?>
            </form>

            <div class="flex min-w-0 items-center gap-2 xl:hidden">
                <form method="GET" action="<?= BASE_URL ?>/admin/inbox" class="relative min-w-0 flex-1">
                    <input type="hidden" name="view" value="<?= htmlspecialchars($currentView) ?>">
                    <input type="hidden" name="document_type_id" value="<?= (int) $documentTypeId ?>">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search tracking # or subject..." class="block w-full min-w-0 rounded-lg border border-gray-200 py-2.5 pl-9 pr-3 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </form>
                <button type="button" data-inbox-filter-target="#admin-inbox-filter-sheet" aria-label="Open filters" class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M6 12h12m-9 8h6"/></svg>
                    <?php if ($documentTypeId > 0): ?><span class="absolute right-1 top-1 h-2 w-2 rounded-full bg-primary"></span><?php endif; ?>
                </button>
            </div>
        </div>

        <div id="admin-inbox-filter-sheet" class="fixed inset-0 z-50 hidden xl:hidden">
            <button type="button" class="inbox-filter-backdrop absolute inset-0 bg-gray-950/40" aria-label="Close filters"></button>
            <form method="GET" action="<?= BASE_URL ?>/admin/inbox" class="absolute inset-x-0 bottom-0 rounded-t-2xl bg-white p-5 shadow-2xl">
                <input type="hidden" name="view" value="<?= htmlspecialchars($currentView) ?>">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                <div class="mb-4 flex items-center justify-between"><h3 class="font-semibold text-gray-900">Filters</h3><button type="button" class="inbox-filter-close text-sm text-gray-500">Close</button></div>
                <select name="document_type_id" aria-label="Filter by document type" class="mb-3 w-full rounded-lg border border-gray-200 px-3 py-3 text-sm"><option value="">All document types</option><?php foreach ($documentTypes as $documentType): ?><option value="<?= (int) $documentType['id'] ?>" <?= (int) $documentTypeId === (int) $documentType['id'] ? 'selected' : '' ?>><?= htmlspecialchars($documentType['name']) ?></option><?php endforeach; ?></select>
                <button type="submit" class="w-full rounded-lg bg-primary px-4 py-3 text-sm font-semibold text-white">Apply Filter</button>
                <?php if ($search !== '' || $documentTypeId > 0): ?><a href="<?= BASE_URL ?>/admin/inbox?view=<?= urlencode($currentView) ?>" class="mt-2 block w-full rounded-lg border border-gray-200 px-4 py-3 text-center text-sm font-medium text-gray-700">Clear filters</a><?php endif; ?>
            </form>
        </div>

        <div class="hidden overflow-x-auto xl:block">
            <?php require __DIR__ . '/../../partials/inbox-mobile-filter-script.php'; ?>
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-100 bg-gray-50/70 text-[11px] uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-bold">Tracking Number</th>
                        <th class="px-6 py-3 font-bold">Subject Matter / Document Type</th>
                        <th class="px-6 py-3 font-bold">Status</th>
                        <th class="px-6 py-3 font-bold"><?= $currentView === 'inbox' ? 'Sent By' : 'Phase' ?></th>
                        <th class="px-6 py-3 font-bold">Date Received</th>
                        <?php if ($currentView !== 'inbox'): ?>
                            <th class="px-6 py-3 font-bold">Decision</th>
                            <th class="px-6 py-3 font-bold">Accepted By</th>
                            <th class="px-6 py-3 font-bold">Accepted At</th>
                        <?php endif; ?>
                        <th class="px-6 py-3 text-right font-bold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($assignments)): ?>
                        <tr class="inbox-empty-row">
                            <td colspan="<?= $currentView === 'inbox' ? '6' : '9' ?>" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">No documents found.</p>
                                <p class="mt-1 text-xs text-gray-500">
                                    <?php if ($currentView === 'inbox'): ?>
                                        All documents have been processed.
                                    <?php else: ?>
                                        No accepted documents yet.
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($assignments as $row): ?>
                            <?php 
                            $statusBadgeColor = !empty($row['status_badge_color']) ? $row['status_badge_color'] : '#6B7280';
                            $decisionBadge = '';
                            if ($currentView !== 'inbox') {
                                $decisionMap = [
                                    'PENDING' => ['label' => 'Pending', 'color' => '#F59E0B'],
                                    'ACCEPTED' => ['label' => 'Accepted', 'color' => '#10B981'],
                                    'DECLINED' => ['label' => 'Declined', 'color' => '#EF4444'],
                                    'NOTED' => ['label' => 'Noted', 'color' => '#3B82F6'],
                                ];
                                $decision = $row['decision'] ?? 'PENDING';
                                $decisionInfo = $decisionMap[$decision] ?? ['label' => $decision, 'color' => '#6B7280'];
                            }
                            ?>
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($row['tracking_number']) ?></div>
                                    <div class="mt-0.5 text-xs text-gray-500">
                                        <?= htmlspecialchars(date('M j, Y', strtotime($row['date_received']))) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="max-w-md">
                                        <div class="text-sm font-medium text-gray-900 whitespace-normal break-words">
                                            <?= htmlspecialchars($row['subject_matter']) ?>
                                        </div>
                                        <div class="mt-1">
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                                  style="background-color: <?= htmlspecialchars($row['document_type_badge_color'] ?? '#2563EB') ?>1a;
                                                         color: <?= htmlspecialchars($row['document_type_badge_color'] ?? '#2563EB') ?>;">
                                                <?= htmlspecialchars($row['document_type_name'] ?? 'Unspecified') ?>
                                            </span>
                                            <?php if (!empty($row['needs_resubmission'])): ?>
                                                <span class="ml-1 inline-flex rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-800">
                                                    Resubmission needed
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                                          style="background-color: <?= htmlspecialchars($statusBadgeColor) ?>1a; color: <?= htmlspecialchars($statusBadgeColor) ?>;">
                                        <span class="h-1.5 w-1.5 rounded-full" style="background-color: <?= htmlspecialchars($statusBadgeColor) ?>;"></span>
                                        <?= htmlspecialchars($row['status'] ?? 'Unknown') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($currentView === 'inbox'): ?>
                                        <span class="text-sm text-gray-700"><?= htmlspecialchars($row['sent_by_display'] ?? '—') ?></span>
                                    <?php else: ?>
                                        <span class="text-sm text-gray-600">
                                            <?= htmlspecialchars(inboxPhaseLabel($row['current_phase'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-gray-600">
                                    <?= htmlspecialchars(date('M j, Y', strtotime($row['date_received']))) ?>
                                    <span class="block text-xs text-gray-400">
                                        <?= htmlspecialchars(date('g:i A', strtotime($row['time_received']))) ?>
                                    </span>
                                </td>
                                <?php if ($currentView !== 'inbox'): ?>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                                              style="background-color: <?= htmlspecialchars($decisionInfo['color']) ?>14; color: <?= htmlspecialchars($decisionInfo['color']) ?>;">
                                            <?= htmlspecialchars($decisionInfo['label']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if (!empty($row['accepted_by_username'])): ?>
                                            <div class="text-sm font-medium text-gray-800">
                                                <?= htmlspecialchars($row['accepted_by_username']) ?>
                                            </div>
                                            <?php
                                            $fullName = trim($row['accepted_by_name'] ?? '');
                                            if ($fullName !== ''): ?>
                                                <div class="text-xs text-gray-400">
                                                    <?= htmlspecialchars($fullName) ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400 italic">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-xs text-gray-500">
                                        <?php if (!empty($row['accepted_at'])): ?>
                                            <?= htmlspecialchars(date('M j, Y', strtotime($row['accepted_at']))) ?>
                                            <span class="block text-gray-400">
                                                <?= htmlspecialchars(date('g:i A', strtotime($row['accepted_at']))) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td class="px-6 py-4 text-right">
                                    <a href="<?= BASE_URL ?>/admin/inbox/show?id=<?= (int) $row['document_id'] ?>"
                                       class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:border-primary hover:bg-blue-50 hover:text-primary"
                                       title="<?= $currentView === 'inbox' ? 'Process document' : 'View document' ?>"
                                       aria-label="<?= $currentView === 'inbox' ? 'Process document' : 'View document' ?>">
                                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        <span><?= $currentView === 'inbox' ? 'Process' : 'View' ?></span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="divide-y divide-gray-100 xl:hidden">
            <?php if (empty($assignments)): ?>
                <div class="px-5 py-12 text-center">
                    <p class="text-sm font-medium text-gray-700">No documents found.</p>
                    <p class="mt-1 text-xs text-gray-500"><?= $currentView === 'inbox' ? 'All documents have been processed.' : 'No accepted documents yet.' ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($assignments as $row): ?>
                    <?php
                    $statusBadgeColor = !empty($row['status_badge_color']) ? $row['status_badge_color'] : '#6B7280';
                    if ($currentView !== 'inbox') {
                        $decisionMap = [
                            'PENDING' => ['label' => 'Pending', 'color' => '#F59E0B'],
                            'ACCEPTED' => ['label' => 'Accepted', 'color' => '#10B981'],
                            'DECLINED' => ['label' => 'Declined', 'color' => '#EF4444'],
                            'NOTED' => ['label' => 'Noted', 'color' => '#3B82F6'],
                        ];
                        $decision = $row['decision'] ?? 'PENDING';
                        $decisionInfo = $decisionMap[$decision] ?? ['label' => $decision, 'color' => '#6B7280'];
                    }
                    ?>
                    <article class="px-5 py-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="<?= BASE_URL ?>/admin/inbox/show?id=<?= (int) $row['document_id'] ?>" class="break-words font-semibold text-gray-900 hover:text-primary">
                                    <?= htmlspecialchars($row['tracking_number']) ?>
                                </a>
                                <p class="mt-1 text-xs text-gray-400">
                                    Received <?= htmlspecialchars(date('M j, Y', strtotime($row['date_received']))) ?>
                                    · <?= htmlspecialchars(date('g:i A', strtotime($row['time_received']))) ?>
                                </p>
                            </div>
                        </div>
                        <p class="mt-3 break-words text-sm font-medium text-gray-900"><?= htmlspecialchars($row['subject_matter']) ?></p>
                        <div class="mt-1 flex flex-wrap gap-1.5">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                  style="background-color: <?= htmlspecialchars($row['document_type_badge_color'] ?? '#2563EB') ?>14; color: <?= htmlspecialchars($row['document_type_badge_color'] ?? '#2563EB') ?>;">
                                <?= htmlspecialchars($row['document_type_name'] ?? 'Unspecified') ?>
                            </span>
                            <?php if (!empty($row['needs_resubmission'])): ?>
                                <span class="inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">Resubmission needed</span>
                            <?php endif; ?>
                        </div>
                        <div class="mt-4 grid grid-cols-1 gap-3 border-t border-gray-100 pt-3 sm:grid-cols-2">
                            <div>
                                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Status</p>
                                <span class="mt-1 inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                                      style="background-color: <?= htmlspecialchars($statusBadgeColor) ?>14; color: <?= htmlspecialchars($statusBadgeColor) ?>;">
                                    <span class="h-1.5 w-1.5 rounded-full" style="background-color: <?= htmlspecialchars($statusBadgeColor) ?>;"></span>
                                    <?= htmlspecialchars($row['status'] ?? 'Unknown') ?>
                                </span>
                            </div>
                            <div>
                                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400"><?= $currentView === 'inbox' ? 'Sent By' : 'Phase' ?></p>
                                <p class="mt-1 break-words text-sm text-gray-700"><?= htmlspecialchars($currentView === 'inbox' ? ($row['sent_by_display'] ?? '—') : inboxPhaseLabel($row['current_phase'])) ?></p>
                            </div>
                            <?php if ($currentView !== 'inbox'): ?>
                                <div>
                                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Decision</p>
                                    <span class="mt-1 inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                                          style="background-color: <?= htmlspecialchars($decisionInfo['color']) ?>14; color: <?= htmlspecialchars($decisionInfo['color']) ?>;">
                                        <?= htmlspecialchars($decisionInfo['label']) ?>
                                    </span>
                                </div>
                                <div>
                                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Accepted By</p>
                                    <p class="mt-1 break-words text-sm font-medium text-gray-700"><?= htmlspecialchars($row['accepted_by_username'] ?? '—') ?></p>
                                    <?php if (!empty(trim($row['accepted_by_name'] ?? ''))): ?><p class="text-xs text-gray-400"><?= htmlspecialchars(trim($row['accepted_by_name'])) ?></p><?php endif; ?>
                                </div>
                                <div>
                                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Accepted At</p>
                                    <p class="mt-1 text-sm text-gray-700"><?= !empty($row['accepted_at']) ? htmlspecialchars(date('M j, Y g:i A', strtotime($row['accepted_at']))) : '—' ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="mt-4 flex justify-end border-t border-gray-100 pt-3">
                            <a href="<?= BASE_URL ?>/admin/inbox/show?id=<?= (int) $row['document_id'] ?>"
                               class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:border-primary hover:bg-blue-50 hover:text-primary"
                               title="<?= $currentView === 'inbox' ? 'Process document' : 'View document' ?>"
                               aria-label="<?= $currentView === 'inbox' ? 'Process document' : 'View document' ?>">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <span><?= $currentView === 'inbox' ? 'Process' : 'View' ?></span>
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

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

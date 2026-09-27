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
$success         = $success         ?? null;
$error           = $error           ?? null;
$errors          = $errors          ?? [];

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
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10 max-w-3xl">
                <p class="text-sm font-medium text-blue-100">ADMIN / ROUTING</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl"><?= htmlspecialchars($pageTitle) ?></h1>
                <p class="mt-2 max-w-xl text-sm leading-6 text-blue-100">
                    <?= htmlspecialchars($pageSubtitle) ?>
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

    <!-- Statistics Cards ----------------------------------------------------->
    <section>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Pending Inbox</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($pendingCount) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-amber-50 px-2 py-1 font-medium text-amber-600">Awaiting action</span>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Accepted Documents</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($acceptedCount) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-emerald-50 px-2 py-1 font-medium text-emerald-600">Your documents only</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Navigation Tabs ------------------------------------------------------>
    <section>
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex gap-6">
                <a href="<?= BASE_URL ?>/admin/inbox?view=inbox<?= $search ? '&search=' . urlencode($search) : '' ?>"
                   class="<?= $currentView === 'inbox' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' ?> whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition">
                    Inbox
                    <?php if ($pendingCount > 0): ?>
                        <span class="ml-2 inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">
                            <?= $pendingCount ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="<?= BASE_URL ?>/admin/inbox?view=accepted<?= $search ? '&search=' . urlencode($search) : '' ?>"
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
    <section class="rounded-2xl border border-gray-200 bg-white">
        <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900">Documents List</h2>
                <p class="mt-1 text-xs text-gray-500">
                    <?= $total ?> record<?= $total !== 1 ? 's' : '' ?> found
                </p>
            </div>
            <form method="GET" action="<?= BASE_URL ?>/admin/inbox" class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <input type="hidden" name="view" value="<?= htmlspecialchars($currentView) ?>">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search tracking # or subject..."
                        class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 lg:w-64">
                </div>
                <button type="submit" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                    Filter
                </button>
                <?php if ($search !== ''): ?>
                    <a href="<?= BASE_URL ?>/admin/inbox?view=<?= htmlspecialchars($currentView) ?>"
                       class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition text-center">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Tracking Number</th>
                        <th class="px-6 py-3 font-medium">Subject Matter / Document Type</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Phase</th>
                        <th class="px-6 py-3 font-medium">Date Received</th>
                        <?php if ($currentView !== 'inbox'): ?>
                            <th class="px-6 py-3 font-medium">Decision</th>
                            <th class="px-6 py-3 font-medium">Accepted By</th>
                            <th class="px-6 py-3 font-medium">Accepted At</th>
                        <?php endif; ?>
                        <th class="px-6 py-3 font-medium text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($assignments)): ?>
                        <tr>
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
                                        <div class="text-sm font-medium text-gray-900 line-clamp-2">
                                            <?= htmlspecialchars($row['subject_matter']) ?>
                                        </div>
                                        <div class="mt-1">
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                                  style="background-color: <?= htmlspecialchars($row['document_type_badge_color'] ?? '#2563EB') ?>1a;
                                                         color: <?= htmlspecialchars($row['document_type_badge_color'] ?? '#2563EB') ?>;">
                                                <?= htmlspecialchars($row['document_type_name'] ?? 'Unspecified') ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold shadow-sm" 
                                          style="background-color: <?= htmlspecialchars($statusBadgeColor) ?>; border-color: <?= htmlspecialchars($statusBadgeColor) ?>; color: white;">
                                        <?= htmlspecialchars($row['status'] ?? 'Unknown') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-lg bg-gray-50 border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700">
                                        <?= htmlspecialchars(inboxPhaseLabel($row['current_phase'])) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-600">
                                    <?= htmlspecialchars(date('M j, Y', strtotime($row['date_received']))) ?>
                                    <span class="block text-xs text-gray-400">
                                        <?= htmlspecialchars(date('g:i A', strtotime($row['time_received']))) ?>
                                    </span>
                                </td>
                                <?php if ($currentView !== 'inbox'): ?>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold shadow-sm" 
                                              style="background-color: <?= htmlspecialchars($decisionInfo['color']) ?>; border-color: <?= htmlspecialchars($decisionInfo['color']) ?>; color: white;">
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
                                       class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:border-primary hover:bg-blue-50 hover:text-primary transition"
                                       title="View Details">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        <?= $currentView === 'inbox' ? 'Process' : 'View Details' ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
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

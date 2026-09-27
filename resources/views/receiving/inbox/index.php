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
?>

<div class="space-y-6">

    <!-- Page header ---------------------------------------------------------->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10 max-w-3xl">
                <p class="text-sm font-medium text-blue-100">RECEIVING / INBOX</p>
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
                        <p class="text-sm text-gray-500">Returned by Admin</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($returnedCount) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-red-50 text-red-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-red-50 px-2 py-1 font-medium text-red-600">Needs correction</span>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Corrected & Re-routed</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($acceptedCount) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-emerald-50 px-2 py-1 font-medium text-emerald-600">Processed</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Navigation Tabs ------------------------------------------------------>
    <section>
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex gap-6">
                <a href="<?= BASE_URL ?>/receiving/inbox?view=returned<?= $search ? '&search=' . urlencode($search) : '' ?>"
                   class="<?= $currentView === 'returned' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' ?> whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition">
                    Returned
                    <?php if ($returnedCount > 0): ?>
                        <span class="ml-2 inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">
                            <?= $returnedCount ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="<?= BASE_URL ?>/receiving/inbox?view=accepted<?= $search ? '&search=' . urlencode($search) : '' ?>"
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
            <form method="GET" action="<?= BASE_URL ?>/receiving/inbox" class="flex flex-col gap-3 lg:flex-row lg:items-center">
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
                    <a href="<?= BASE_URL ?>/receiving/inbox?view=<?= htmlspecialchars($currentView) ?>"
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
                        <th class="px-6 py-3 font-medium">Subject Matter</th>
                        <th class="px-6 py-3 font-medium">Document Type</th>
                        <th class="px-6 py-3 font-medium">Current Status</th>
                        <th class="px-6 py-3 font-medium">Current Phase</th>
                        <?php if ($currentView === 'returned'): ?>
                            <th class="px-6 py-3 font-medium">Date Returned</th>
                            <th class="px-6 py-3 font-medium">Returned By</th>
                            <th class="px-6 py-3 font-medium">Return Reason</th>
                        <?php else: ?>
                            <th class="px-6 py-3 font-medium">Date Accepted</th>
                            <th class="px-6 py-3 font-medium">Accepted By</th>
                        <?php endif; ?>
                        <th class="px-6 py-3 font-medium text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($assignments)): ?>
                        <tr>
                            <td colspan="<?= $currentView === 'returned' ? '9' : '8' ?>" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">
                                    <?= $currentView === 'returned' ? 'No returned documents found.' : 'No accepted returned documents found.' ?>
                                </p>
                                <p class="mt-1 text-xs text-gray-500">
                                    <?= $currentView === 'returned' ? 'Documents returned by Admin will appear here.' : 'Documents you have corrected and re-routed will appear here.' ?>
                                </p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($assignments as $assignment): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <a href="<?= BASE_URL ?>/receiving/inbox/show?id=<?= $assignment['document_id'] ?>" 
                                       class="font-medium text-primary hover:underline">
                                        <?= htmlspecialchars($assignment['tracking_number']) ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="max-w-md">
                                        <p class="line-clamp-2 text-gray-900">
                                            <?= htmlspecialchars($assignment['subject_matter']) ?>
                                        </p>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($assignment['document_type_badge_color']): ?>
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium text-white"
                                              style="background-color: <?= htmlspecialchars($assignment['document_type_badge_color']) ?>;">
                                            <?= htmlspecialchars($assignment['document_type_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-700"><?= htmlspecialchars($assignment['document_type_name'] ?? '—') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($assignment['status_badge_color']): ?>
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium text-white"
                                              style="background-color: <?= htmlspecialchars($assignment['status_badge_color']) ?>;">
                                            <?= htmlspecialchars($assignment['status']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-700"><?= htmlspecialchars($assignment['status'] ?? '—') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">
                                        <?= htmlspecialchars(inboxPhaseLabel($assignment['current_phase'])) ?>
                                    </span>
                                </td>
                                <?php if ($currentView === 'returned'): ?>
                                    <td class="px-6 py-4 text-gray-700">
                                        <?= formatDateTime($assignment['declined_at'] ?? null) ?>
                                    </td>
                                    <td class="px-6 py-4 text-gray-700">
                                        <?= htmlspecialchars($assignment['declined_by_name'] ?? $assignment['declined_by_username'] ?? '—') ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="max-w-xs">
                                            <?php if (!empty($assignment['decline_reason'])): ?>
                                                <p class="line-clamp-2 text-sm text-red-700 font-medium">
                                                    <?= htmlspecialchars($assignment['decline_reason']) ?>
                                                </p>
                                            <?php else: ?>
                                                <span class="text-gray-500 text-sm">No reason provided</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php else: ?>
                                    <td class="px-6 py-4 text-gray-700">
                                        <?= formatDateTime($assignment['accepted_at'] ?? null) ?>
                                    </td>
                                    <td class="px-6 py-4 text-gray-700">
                                        <?= htmlspecialchars($assignment['accepted_by_name'] ?? $assignment['accepted_by_username'] ?? '—') ?>
                                    </td>
                                <?php endif; ?>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <?php if ($currentView === 'returned'): ?>
                                            <a href="<?= BASE_URL ?>/receiving/inbox/show?id=<?= $assignment['document_id'] ?>"
                                               class="rounded-lg bg-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 transition">
                                                View
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= BASE_URL ?>/receiving/inbox/show?id=<?= $assignment['document_id'] ?>"
                                               class="rounded-lg bg-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 transition">
                                                Edit
                                            </a>
                                        <?php endif; ?>
                                    </div>
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
$pageTitle = 'Receiving Inbox';
require __DIR__ . '/../../layouts/app.php';
?>

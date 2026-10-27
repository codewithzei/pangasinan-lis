<?php
/**
 * SP Secretary — Communications Inbox
 *
 * Variables:
 *   $documents      array
 *   $tab            string  'pending' | 'sent'
 *   $pendingCount   int
 *   $sentCount      int
 *   $search         string
 *   $page           int
 *   $totalPages     int
 *   $total          int
 *   $pageTitle      string
 *   $success        string|null
 *   $error          string|null
 */

$documents    = $documents    ?? [];
$tab          = $tab          ?? 'pending';
$pendingCount = $pendingCount ?? 0;
$sentCount    = $sentCount    ?? 0;
$search       = $search       ?? '';
$page         = $page         ?? 1;
$totalPages   = $totalPages   ?? 1;
$total        = $total        ?? 0;

ob_start();
?>

<div class="space-y-6">

    <?php if (!empty($success)): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Header -->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-sm font-medium text-blue-100">SP SECRETARY / COMMUNICATIONS</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">Communications Inbox</h1>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-blue-100">
                        Communication documents available for First Reading processing.
                    </p>
                </div>
                <div class="shrink-0">
                    <a href="<?= BASE_URL ?>/spsec/comm-first-reading"
                       class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-indigo-700 hover:bg-indigo-50 transition shadow-sm">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h10M7 12h10m-3 5H7"/>
                        </svg>
                        First Reading Queue
                    </a>
                </div>
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
                        <p class="text-sm text-gray-500">Pending (Not Yet Sent to FR)</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($pendingCount) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Sent to First Reading</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($sentCount) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Table -->
    <section class="rounded-2xl border border-gray-200 bg-white">

        <!-- Tabs + Search -->
        <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex gap-1 rounded-xl border border-gray-200 bg-gray-50 p-1">
                <a href="?tab=pending<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>"
                   class="rounded-lg px-4 py-1.5 text-sm font-medium transition <?= $tab === 'pending' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' ?>">
                    Pending
                    <?php if ($pendingCount > 0): ?>
                        <span class="ml-1.5 rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700"><?= $pendingCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="?tab=sent<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>"
                   class="rounded-lg px-4 py-1.5 text-sm font-medium transition <?= $tab === 'sent' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' ?>">
                    Sent to FR
                    <?php if ($sentCount > 0): ?>
                        <span class="ml-1.5 rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700"><?= $sentCount ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <form method="GET" action="<?= BASE_URL ?>/spsec/comm-inbox" class="flex items-center gap-3">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                           placeholder="Search tracking # or subject…"
                           class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 lg:w-64">
                </div>
                <button type="submit" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Filter</button>
                <?php if ($search !== ''): ?>
                    <a href="?tab=<?= htmlspecialchars($tab) ?>" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Communication Date</th>
                        <th class="px-6 py-3 font-medium">Subject Matter</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <?php if ($tab === 'sent'): ?>
                            <th class="px-6 py-3 font-medium">Sent to FR</th>
                        <?php endif; ?>
                        <th class="px-6 py-3 font-medium text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($documents)): ?>
                        <tr>
                            <td colspan="<?= $tab === 'sent' ? 5 : 4 ?>" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">No communications found.</p>
                                <p class="mt-1 text-xs text-gray-500">
                                    <?= $tab === 'pending' ? 'Communication documents in the SP Secretary queue will appear here.' : 'Communications sent to First Reading will appear here.' ?>
                                </p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($documents as $row): ?>
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars(date('F j, Y', strtotime($row['date_received']))) ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($row['tracking_number']) ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="max-w-sm">
                                        <p class="line-clamp-2 text-gray-900"><?= htmlspecialchars($row['subject_matter']) ?></p>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if (!empty($row['status'])): ?>
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                              style="background-color: <?= htmlspecialchars($row['status_badge_color'] ?? '#6B7280') ?>1a; color: <?= htmlspecialchars($row['status_badge_color'] ?? '#6B7280') ?>;">
                                            <?= htmlspecialchars($row['status']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs italic text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($tab === 'sent'): ?>
                                    <td class="px-6 py-4 text-xs text-gray-500">
                                        <?= !empty($row['sent_to_fr_at']) ? htmlspecialchars(date('M j, Y', strtotime($row['sent_to_fr_at']))) : '—' ?>
                                    </td>
                                <?php endif; ?>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="<?= BASE_URL ?>/spsec/comm-inbox/show?id=<?= (int) $row['id'] ?>"
                                           class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 transition">
                                            View
                                        </a>
                                        <?php if ($tab === 'pending' && empty($row['sent_to_fr_at'])): ?>
                                            <form method="POST" action="<?= BASE_URL ?>/spsec/comm-inbox/send-to-fr"
                                                  onsubmit="return confirm('Send this communication to the First Reading queue?')">
                                                <input type="hidden" name="document_id" value="<?= (int) $row['id'] ?>">
                                                <button type="submit"
                                                        class="rounded-lg bg-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-primary/90 transition">
                                                    Send to First Reading
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="flex flex-col items-center justify-between gap-3 border-t border-gray-100 px-6 py-4 sm:flex-row">
                <p class="text-xs text-gray-500">Page <span class="font-medium"><?= $page ?></span> of <span class="font-medium"><?= $totalPages ?></span></p>
                <div class="flex items-center gap-1">
                    <?php $qs = '?tab=' . urlencode($tab) . ($search !== '' ? '&search=' . urlencode($search) : ''); ?>
                    <?php if ($page > 1): ?><a href="<?= $qs ?>&page=<?= $page - 1 ?>" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Prev</a><?php endif; ?>
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="<?= $qs ?>&page=<?= $i ?>" class="rounded-lg border px-3 py-1.5 text-sm font-medium transition <?= $i === $page ? 'border-primary bg-primary text-white' : 'border-gray-200 text-gray-700 hover:bg-gray-50' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?><a href="<?= $qs ?>&page=<?= $page + 1 ?>" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Next</a><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </section>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

<?php
/**
 * Committee — Referred Documents
 *
 * Variables supplied by CommitteeReferredController::index():
 *   $documents      array        Referred document rows
 *   $tab            string       Active tab: 'referred' | 'for_opinion' | 'ready_for_agenda' | 'withdrawn'
 *   $page           int
 *   $totalPages     int
 *   $total          int
 *   $search         string
 *   $referredCount  int          Badge count for the Referred tab
 *   $success        string|null
 *   $error          string|null
 */

$documents     = $documents     ?? [];
$tab           = $tab           ?? 'referred';
$page          = $page          ?? 1;
$totalPages    = $totalPages    ?? 1;
$total         = $total         ?? 0;
$search        = $search        ?? '';
$referredCount = $referredCount ?? 0;
$success       = $success       ?? null;
$error         = $error         ?? null;

ob_start();

/** Map a phase constant to a readable label */
function committeeReferredPhaseLabel(string $phase): string
{
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

// ── Tab configuration ─────────────────────────────────────────────────────────
// Phase 1: only 'referred' is functional.  The other tabs are structural
// placeholders — they display an informational notice and zero results so no
// misleading data is ever shown.
$tabs = [
    'referred' => [
        'label'       => 'Referred',
        'icon'        => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'functional'  => true,
        'badge'       => $referredCount,
    ],
    'for_opinion' => [
        'label'       => 'For Opinion',
        'icon'        => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>',
        'functional'  => false,
        'badge'       => 0,
    ],
    'ready_for_agenda' => [
        'label'       => 'Ready for Agenda',
        'icon'        => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
        'functional'  => false,
        'badge'       => 0,
    ],
    'withdrawn' => [
        'label'       => 'Withdrawn',
        'icon'        => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>',
        'functional'  => false,
        'badge'       => 0,
    ],
];

/** Build a query string preserving all current params except the given key */
function referredQueryWith(array $overrides): string
{
    $params = array_merge(
        ['tab' => $_GET['tab'] ?? 'referred', 'search' => $_GET['search'] ?? '', 'page' => $_GET['page'] ?? 1],
        $overrides
    );
    // Strip empty values
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return http_build_query($params);
}
?>

<div class="space-y-6">

    <!-- Page header ---------------------------------------------------------->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-blue-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10 max-w-3xl">
                <p class="text-sm font-medium text-blue-100">COMMITTEE / LEGISLATIVE WORKFLOW</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    Referred Documents
                </h1>
                <p class="mt-2 max-w-xl text-sm leading-6 text-blue-100">
                    Documents endorsed by Committee as Referred, and their subsequent workflow status.
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

    <!-- Navigation Tabs -------------------------------------------------------->
    <section>
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex gap-6">
                <?php foreach ($tabs as $tabKey => $tabConfig): ?>
                    <?php
                    $isActive     = $tabKey === $tab;
                    $isFunctional = $tabConfig['functional'];
                    $tabUrl       = BASE_URL . '/committee/referred?' . referredQueryWith(['tab' => $tabKey, 'page' => 1]);
                    ?>
                    <a href="<?= htmlspecialchars($tabUrl) ?>"
                       class="<?= $isActive ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' ?> whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition">
                        <?= htmlspecialchars($tabConfig['label']) ?>
                        <?php if ($tabConfig['badge'] > 0): ?>
                            <span class="ml-2 inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700">
                                <?= $tabConfig['badge'] ?>
                            </span>
                        <?php elseif (!$isFunctional): ?>
                            <span class="ml-2 inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-400">
                                Soon
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
    </section>

    <!-- Document List Section ------------------------------------------------>
    <?php if (!$tabs[$tab]['functional']): ?>
        <!-- Coming-soon notice for non-functional tabs -->
        <section class="rounded-2xl border border-gray-200 bg-white">
            <div class="flex flex-col items-center justify-center gap-3 px-6 py-16 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100">
                    <svg class="h-7 w-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"
                              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <p class="text-base font-semibold text-gray-700">
                    <?= htmlspecialchars($tabs[$tab]['label']) ?> — Coming Soon
                </p>
                <p class="max-w-sm text-sm text-gray-400">
                    This workflow stage is not yet implemented. It will be available in a future phase.
                </p>
                <a href="<?= BASE_URL ?>/committee/referred"
                   class="mt-2 inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white
                          px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Referred
                </a>
            </div>
        </section>
    <?php else: ?>
    <section class="rounded-2xl border border-gray-200 bg-white">

        <!-- ── REFERRED tab content ────────────────────────────────────────── -->

        <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5
                    lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="font-semibold text-gray-900">Referred Documents</h2>
                    <p class="mt-1 text-xs text-gray-500">
                        <?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?> found
                    </p>
                </div>
                <form method="GET" action="<?= BASE_URL ?>/committee/referred"
                      class="flex flex-col gap-3 lg:flex-row lg:items-center">
                    <input type="hidden" name="tab" value="referred">
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                               placeholder="Search tracking # or subject…"
                               class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3
                                      text-sm text-gray-800 placeholder-gray-400 focus:border-primary
                                      focus:outline-none focus:ring-2 focus:ring-primary/20 lg:w-64">
                    </div>
                    <button type="submit"
                            class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium
                                   text-gray-700 hover:bg-gray-50 transition">
                        Filter
                    </button>
                    <?php if ($search !== ''): ?>
                        <a href="<?= BASE_URL ?>/committee/referred?tab=referred"
                           class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium
                                  text-gray-700 hover:bg-gray-50 transition text-center">
                            Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Table -------------------------------------------------------->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-6 py-3 font-medium">Tracking Number</th>
                            <th class="px-6 py-3 font-medium">Subject Matter / Document Type</th>
                            <th class="px-6 py-3 font-medium">Date Referred</th>
                            <th class="px-6 py-3 font-medium">Referred By</th>
                            <th class="px-6 py-3 font-medium">Remarks</th>
                            <th class="px-6 py-3 font-medium">Status</th>
                            <th class="px-6 py-3 font-medium text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($documents)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-16 text-center">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"
                                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                    <p class="mt-4 text-sm font-medium text-gray-700">No referred documents yet</p>
                                    <p class="mt-1 text-xs text-gray-500">
                                        <?php if ($search !== ''): ?>
                                            No documents match your search. Try a different keyword.
                                        <?php else: ?>
                                            Documents endorsed as Referred from the Committee inbox will appear here.
                                        <?php endif; ?>
                                    </p>
                                    <?php if ($search !== ''): ?>
                                        <a href="<?= BASE_URL ?>/committee/referred?tab=referred"
                                           class="mt-3 inline-flex items-center gap-1.5 rounded-xl border border-gray-200
                                                  bg-white px-4 py-2 text-sm font-medium text-gray-600
                                                  hover:bg-gray-50 transition">
                                            Clear search
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($documents as $doc): ?>
                                <tr class="hover:bg-gray-50/50 transition">

                                    <!-- Tracking number -->
                                    <td class="px-6 py-4">
                                        <a href="<?= BASE_URL ?>/committee/inbox/show?id=<?= (int) $doc['document_id'] ?>"
                                           class="font-medium text-primary hover:text-blue-800 hover:underline">
                                            <?= htmlspecialchars($doc['tracking_number']) ?>
                                        </a>
                                        <?php if (!empty($doc['cycle_number'])): ?>
                                            <span class="ml-1 inline-flex rounded-full bg-gray-100 px-1.5 py-0.5
                                                         text-[10px] font-medium text-gray-500">
                                                Cycle&nbsp;<?= (int) $doc['cycle_number'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Subject matter / Document type -->
                                    <td class="px-6 py-4">
                                        <div class="max-w-xs">
                                            <div class="text-sm font-medium text-gray-900 line-clamp-2">
                                                <?= htmlspecialchars($doc['subject_matter'] ?? '—') ?>
                                            </div>
                                            <?php if (!empty($doc['document_type_name'])): ?>
                                                <div class="mt-1">
                                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                                          style="background-color: <?= htmlspecialchars($doc['document_type_badge_color'] ?? '#2563EB') ?>1a;
                                                                 color: <?= htmlspecialchars($doc['document_type_badge_color'] ?? '#2563EB') ?>;">
                                                        <?= htmlspecialchars($doc['document_type_name']) ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- Date referred -->
                                    <td class="px-6 py-4 text-gray-600">
                                        <?php if (!empty($doc['endorsed_at'])): ?>
                                            <?= htmlspecialchars(date('M j, Y', strtotime($doc['endorsed_at']))) ?>
                                            <span class="block text-xs text-gray-400">
                                                <?= htmlspecialchars(date('g:i A', strtotime($doc['endorsed_at']))) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-400">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Referred by -->
                                    <td class="px-6 py-4">
                                        <?php
                                        $rbName     = trim($doc['referred_by_name']     ?? '');
                                        $rbUsername = trim($doc['referred_by_username'] ?? '');
                                        if ($rbName !== '' || $rbUsername !== ''):
                                        ?>
                                            <?php if ($rbName !== ''): ?>
                                                <div class="text-sm font-medium text-gray-800">
                                                    <?= htmlspecialchars($rbName) ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($rbUsername !== ''): ?>
                                                <div class="text-xs text-gray-400">
                                                    @<?= htmlspecialchars($rbUsername) ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400 italic">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Sender's remarks: remark from the inbound document_routes record
                                         that routed the document into the Committee phase.
                                         No fallback to endorsement_remarks (that is the Committee's own remark). -->
                                    <?php
                                        $displayRemarks = trim($doc['sender_remarks'] ?? '');
                                    ?>
                                    <td class="px-6 py-4">
                                        <?php if ($displayRemarks !== ''): ?>
                                            <p class="max-w-xs text-xs text-gray-600 italic line-clamp-2"
                                               title="<?= htmlspecialchars($displayRemarks) ?>">
                                                <?= htmlspecialchars($displayRemarks) ?>
                                            </p>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400 italic">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Current status -->
                                    <td class="px-6 py-4">
                                        <?php if (!empty($doc['status'])): ?>
                                            <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5
                                                         py-0.5 text-xs font-semibold shadow-sm"
                                                  style="background-color: <?= htmlspecialchars($doc['status_badge_color'] ?? '#6B7280') ?>;
                                                         border-color: <?= htmlspecialchars($doc['status_badge_color'] ?? '#6B7280') ?>;
                                                         color: white;">
                                                <?= htmlspecialchars($doc['status']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400 italic">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Action -->
                                    <td class="px-6 py-4 text-right">
                                        <button type="button"
                                                title="Endorse — not yet implemented"
                                                disabled
                                                class="inline-flex cursor-not-allowed items-center gap-1.5 rounded-lg
                                                       border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium
                                                       text-gray-400 opacity-60">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806
                                                         3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806
                                                         3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946
                                                         3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946
                                                         3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806
                                                         3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806
                                                         3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946
                                                         3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946
                                                         3.42 3.42 0 013.138-3.138z"/>
                                            </svg>
                                            Endorse
                                        </button>
                                    </td>

                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination --------------------------------------------------->
            <?php if ($totalPages > 1): ?>
                <div class="flex flex-col items-center justify-between gap-3 border-t border-gray-100
                            px-6 py-4 sm:flex-row">
                    <p class="text-xs text-gray-500">
                        Showing page
                        <span class="font-medium text-gray-700"><?= $page ?></span>
                        of
                        <span class="font-medium text-gray-700"><?= $totalPages ?></span>
                        (<?= number_format($total) ?> total records)
                    </p>
                    <div class="flex items-center gap-1">
                        <?php if ($page > 1): ?>
                            <a href="<?= BASE_URL ?>/committee/referred?<?= htmlspecialchars(referredQueryWith(['page' => $page - 1])) ?>"
                               class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium
                                      text-gray-700 hover:bg-gray-50 transition">
                                Prev
                            </a>
                        <?php endif; ?>

                        <?php
                        $rangeStart = max(1, $page - 2);
                        $rangeEnd   = min($totalPages, $page + 2);
                        for ($p = $rangeStart; $p <= $rangeEnd; $p++):
                        ?>
                            <a href="<?= BASE_URL ?>/committee/referred?<?= htmlspecialchars(referredQueryWith(['page' => $p])) ?>"
                               class="rounded-lg border px-3 py-1.5 text-sm font-medium transition
                                      <?= $p === $page
                                          ? 'border-primary bg-primary text-white'
                                          : 'border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
                                <?= $p ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="<?= BASE_URL ?>/committee/referred?<?= htmlspecialchars(referredQueryWith(['page' => $page + 1])) ?>"
                               class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium
                                      text-gray-700 hover:bg-gray-50 transition">
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?><!-- /functional tab -->

    </section><!-- /Document List Section -->

</div><!-- /space-y-6 -->

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

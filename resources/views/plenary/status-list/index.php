<?php
/**
 * Plenary Finalized Documents — Legislative and Communications tabs
 *
 * Variables supplied by PlenaryReadingController::statusList():
 *   $documents               array   (legislative tab)
 *   $communicationDocuments  array   (communications tab)
 *   $tab                     string  ('legislative' | 'communications')
 *   $tabCounts               array   ['legislative', 'communications']
 *   $page                    int
 *   $totalPages              int
 *   $total                   int
 *   $search                  string
 *   $success                 string|null
 *   $error                   string|null
 */

$documents              = $documents              ?? [];
$communicationDocuments = $communicationDocuments ?? [];
$tab                    = $tab                    ?? 'legislative';
$tabCounts              = $tabCounts              ?? ['legislative' => 0, 'communications' => 0];
$page                   = $page                   ?? 1;
$totalPages             = $totalPages             ?? 1;
$total                  = $total                  ?? 0;
$search                 = $search                 ?? '';

ob_start();

function slTabUrl(string $targetTab, string $search = ''): string {
    $qs = 'tab=' . $targetTab;
    if ($search !== '') { $qs .= '&search=' . urlencode($search); }
    return BASE_URL . '/plenary/status-list?' . $qs;
}

function slPageUrl(string $tab, int $pageNum, string $search): string {
    $qs = 'tab=' . $tab . '&page=' . $pageNum;
    if ($search !== '') { $qs .= '&search=' . urlencode($search); }
    return BASE_URL . '/plenary/status-list?' . $qs;
}

function slDecisionLabel(string $decision): string {
    return match ($decision) {
        'NOTED'              => 'Noted',
        'APPROVED'           => 'Approved for Resolution',
        'REFER_TO_COMMITTEE' => 'Referred to Committee',
        default              => $decision,
    };
}
?>

<div class="space-y-6">

    <?php if (!empty($success ?? null)): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($error ?? null)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Header -->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10 max-w-3xl">
                <p class="text-sm font-medium text-blue-100">PLENARY / LEGISLATIVE WORKFLOW</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">Finalized Documents</h1>
                <p class="mt-2 max-w-xl text-sm leading-6 text-blue-100">Finalized legislative documents and Communications.</p>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
        </div>
    </section>

    <!-- Stats -->
    <section>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 flex items-center gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Finalized (Legislative)</p>
                    <p class="text-2xl font-bold text-gray-900"><?= number_format($tabCounts['legislative']) ?></p>
                </div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 flex items-center gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Finalized (Communications)</p>
                    <p class="text-2xl font-bold text-gray-900"><?= number_format($tabCounts['communications']) ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- Tabs + List -->
    <section class="rounded-2xl border border-gray-200 bg-white">
        <!-- Tabs -->
        <div class="flex items-center gap-1 border-b border-gray-100 px-6 pt-4">
            <?php
            $tabs = [
                'legislative'    => ['label' => 'Legislative Documents', 'count' => $tabCounts['legislative']],
                'communications' => ['label' => 'Communications',        'count' => $tabCounts['communications']],
            ];
            foreach ($tabs as $tabKey => $tabInfo):
                $isActive = ($tab === $tabKey);
            ?>
            <a href="<?= slTabUrl($tabKey, $search) ?>"
               class="flex items-center gap-1.5 rounded-t-lg px-4 py-2.5 text-sm font-medium transition
                      <?= $isActive ? 'border-b-2 border-primary text-primary bg-blue-50/50' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50' ?>">
                <?= htmlspecialchars($tabInfo['label']) ?>
                <span class="rounded-full px-1.5 py-0.5 text-xs font-semibold
                             <?= $isActive ? 'bg-primary text-white' : 'bg-gray-100 text-gray-600' ?>">
                    <?= number_format($tabInfo['count']) ?>
                </span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Search bar -->
        <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900">
                    <?= $tab === 'communications' ? 'Finalized Communications' : 'Finalized Documents' ?>
                </h2>
                <p class="mt-1 text-xs text-gray-500"><?= $total ?> record<?= $total !== 1 ? 's' : '' ?></p>
            </div>
            <form method="GET" action="<?= BASE_URL ?>/plenary/status-list"
                  class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                           placeholder="Search tracking #, subject<?= $tab === 'legislative' ? ', approved no…' : ', resolution no…' ?>"
                           class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm
                                  text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none
                                  focus:ring-2 focus:ring-primary/20 lg:w-72">
                </div>
                <button type="submit"
                        class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium
                               text-gray-700 hover:bg-gray-50 transition">Filter</button>
                <?php if ($search !== ''): ?>
                    <a href="<?= slTabUrl($tab) ?>"
                       class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium
                              text-gray-700 hover:bg-gray-50 transition text-center">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($tab === 'communications'): ?>
        <!-- ── Communications Table ──────────────────────────────────────── -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Tracking Number</th>
                        <th class="px-6 py-3 font-medium">Subject Matter</th>
                        <th class="px-6 py-3 font-medium">Decision / Category</th>
                        <th class="px-6 py-3 font-medium">Committees</th>
                        <th class="px-6 py-3 font-medium">Proposed No.</th>
                        <th class="px-6 py-3 font-medium">Resolution No.</th>
                        <th class="px-6 py-3 font-medium">Finalized</th>
                        <th class="px-6 py-3 font-medium">Files</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($communicationDocuments)): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">No finalized Communications yet.</p>
                                <p class="mt-1 text-xs text-gray-500">Communications approved at First Reading or via adopted committee report will appear here.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($communicationDocuments as $row):
                            $frDecision    = $row['fr_decision'] ?? '';
                            $isAdopted     = !empty($row['adoption_id']);
                            $resNum        = $isAdopted ? ($row['adoption_resolution_number'] ?? '') : ($row['fr_resolution_number'] ?? '');
                            $propNum       = $isAdopted ? ($row['adoption_proposed_number']  ?? '') : ($row['fr_proposed_number']  ?? '');
                            $finalizedDate = $row['finalized_at'] ?? null;
                            $committeeNames = $row['committee_names'] ?? '';
                            $resFiles       = $row['resolution_files'] ?? [];
                        ?>
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($row['tracking_number']) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars(date('M j, Y', strtotime($row['date_received']))) ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="max-w-xs line-clamp-2 text-gray-800"><?= htmlspecialchars($row['subject_matter']) ?></p>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($isAdopted): ?>
                                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">Adopted Comm. Report</span>
                                    <?php elseif ($frDecision === 'APPROVED'): ?>
                                        <span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700">Approved for Resolution</span>
                                    <?php elseif ($frDecision === 'NOTED'): ?>
                                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">Noted</span>
                                        <?php if (!empty($row['communication_category_name'])): ?>
                                            <div class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($row['communication_category_name']) ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400"><?= htmlspecialchars(slDecisionLabel($frDecision)) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-700">
                                    <?= !empty($committeeNames)
                                        ? htmlspecialchars($committeeNames)
                                        : '<span class="italic text-gray-400">—</span>' ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?= !empty($propNum)
                                        ? '<span class="text-sm text-gray-800">' . htmlspecialchars($propNum) . '</span>'
                                        : '<span class="text-xs italic text-gray-400">—</span>' ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?= !empty($resNum)
                                        ? '<span class="font-semibold text-primary">' . htmlspecialchars($resNum) . '</span>'
                                        : '<span class="text-xs italic text-gray-400">—</span>' ?>
                                </td>
                                <td class="px-6 py-4 text-sm font-medium text-emerald-700">
                                    <?= !empty($finalizedDate)
                                        ? htmlspecialchars(date('M j, Y', strtotime($finalizedDate)))
                                        : '<span class="italic text-gray-400">—</span>' ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if (!empty($resFiles)): ?>
                                        <div class="space-y-1">
                                            <?php foreach ($resFiles as $rf): ?>
                                                <a href="<?= BASE_URL ?>/<?= htmlspecialchars($rf['file_path']) ?>"
                                                   target="_blank" rel="noopener noreferrer"
                                                   class="flex items-center gap-1 text-xs text-primary hover:underline">
                                                    <svg class="h-3 w-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                                    </svg>
                                                    <span class="truncate max-w-[120px]"><?= htmlspecialchars($rf['original_filename']) ?></span>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs italic text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php else: ?>
        <!-- ── Legislative Documents Table ──────────────────────────────── -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Tracking Number</th>
                        <th class="px-6 py-3 font-medium">Approved No.</th>
                        <th class="px-6 py-3 font-medium">Subject Matter</th>
                        <th class="px-6 py-3 font-medium">Type</th>
                        <th class="px-6 py-3 font-medium">Authors</th>
                        <th class="px-6 py-3 font-medium">Committees</th>
                        <th class="px-6 py-3 font-medium">1st Reading</th>
                        <th class="px-6 py-3 font-medium">2nd Reading</th>
                        <th class="px-6 py-3 font-medium">Date Finalized</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($documents)): ?>
                        <tr>
                            <td colspan="9" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">No finalized documents yet.</p>
                                <p class="mt-1 text-xs text-gray-500">Documents approved on Second Reading will appear here.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($documents as $row): ?>
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($row['tracking_number']) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars(date('M j, Y', strtotime($row['date_received']))) ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if (!empty($row['approved_no'])): ?>
                                        <span class="font-semibold text-primary"><?= htmlspecialchars($row['approved_no']) ?></span>
                                    <?php else: ?>
                                        <span class="text-xs italic text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="max-w-xs">
                                        <p class="line-clamp-2 text-gray-900">
                                            <?= htmlspecialchars($row['approved_subject_matter'] ?: $row['subject_matter']) ?>
                                        </p>
                                        <?php if (!empty($row['proposed_number'])): ?>
                                            <p class="text-xs text-gray-500 mt-0.5">Proposed: <?= htmlspecialchars($row['proposed_number']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if (!empty($row['document_type_name'])): ?>
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                              style="background-color: <?= htmlspecialchars($row['document_type_badge_color'] ?? '#2563EB') ?>1a;
                                                     color: <?= htmlspecialchars($row['document_type_badge_color'] ?? '#2563EB') ?>;">
                                            <?= htmlspecialchars($row['document_type_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs italic text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-700">
                                    <?= !empty($row['author_names']) ? htmlspecialchars($row['author_names']) : '<span class="italic text-gray-400">—</span>' ?>
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-700">
                                    <?= !empty($row['committee_names']) ? htmlspecialchars($row['committee_names']) : '<span class="italic text-gray-400">—</span>' ?>
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-700">
                                    <?php if (!empty($row['first_reading_date'])): ?>
                                        <?= htmlspecialchars(date('M j, Y', strtotime($row['first_reading_date']))) ?>
                                        <span class="block text-gray-400"><?= htmlspecialchars(str_replace('_', ' ', $row['first_reading_action'] ?? '')) ?></span>
                                    <?php else: ?>
                                        <span class="italic text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-700">
                                    <?= !empty($row['second_reading_date'])
                                        ? htmlspecialchars(date('M j, Y', strtotime($row['second_reading_date'])))
                                        : '<span class="italic text-gray-400">—</span>' ?>
                                </td>
                                <td class="px-6 py-4 text-sm font-medium text-emerald-700">
                                    <?= !empty($row['date_finalized'])
                                        ? htmlspecialchars(date('M j, Y', strtotime($row['date_finalized'])))
                                        : '<span class="italic text-gray-400">—</span>' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($totalPages > 1): ?>
            <div class="flex flex-col items-center justify-between gap-3 border-t border-gray-100 px-6 py-4 sm:flex-row">
                <p class="text-xs text-gray-500">
                    Page <span class="font-medium"><?= $page ?></span>
                    of <span class="font-medium"><?= $totalPages ?></span>
                </p>
                <div class="flex items-center gap-1">
                    <?php if ($page > 1): ?>
                        <a href="<?= slPageUrl($tab, $page - 1, $search) ?>"
                           class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Prev</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="<?= slPageUrl($tab, $i, $search) ?>"
                           class="rounded-lg border px-3 py-1.5 text-sm font-medium transition
                                  <?= $i === $page ? 'border-primary bg-primary text-white' : 'border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?= slPageUrl($tab, $page + 1, $search) ?>"
                           class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

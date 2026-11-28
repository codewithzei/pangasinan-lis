<?php
/**
 * Plenary Finalized Documents — Legislative, Communications, and Petitions tabs
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
$tabCounts['petitions'] = $tabCounts['petitions'] ?? 0;
$listTitle = $tab === 'communications'
    ? 'Finalized Communications'
    : ($tab === 'petitions' ? 'Finalized Petitions' : 'Finalized Documents');

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
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative z-10 flex flex-col gap-5 px-6 py-7 sm:px-8 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-blue-100">Plenary · Legislative Workflow</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-white sm:text-3xl">Finalized Documents</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-blue-100">Browse finalized legislative documents, communications, and petitions.</p>
            </div>
            <div class="flex items-center gap-3 border-t border-white/15 pt-4 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
                <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-emerald-300 ring-4 ring-emerald-300/20"></span>
                <div>
                    <p class="text-xs font-medium text-white">Finalized records</p>
                    <p class="mt-0.5 text-xs text-blue-100/80"><?= htmlspecialchars(date('l, F j, Y')) ?></p>
                </div>
            </div>
        </div>
        <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
    </section>

    <!-- At a glance -->
    <section>
        <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-primary">At a glance</p>
                <h2 class="mt-1 text-xl font-semibold text-gray-900">Finalized records</h2>
            </div>
            <p class="text-xs text-gray-500">Records by document category</p>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <?php foreach ([
                ['label' => 'Legislative Documents', 'count' => $tabCounts['legislative'], 'accent' => 'bg-emerald-500', 'icon' => 'text-emerald-600 bg-emerald-50'],
                ['label' => 'Communications', 'count' => $tabCounts['communications'], 'accent' => 'bg-blue-500', 'icon' => 'text-blue-600 bg-blue-50'],
                ['label' => 'Petitions', 'count' => $tabCounts['petitions'], 'accent' => 'bg-violet-500', 'icon' => 'text-violet-600 bg-violet-50'],
            ] as $stat): ?>
                <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <span class="absolute inset-x-0 top-0 h-1 <?= $stat['accent'] ?>"></span>
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm text-gray-500"><?= htmlspecialchars($stat['label']) ?></p>
                            <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?= number_format((int) $stat['count']) ?></p>
                        </div>
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl <?= $stat['icon'] ?>">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                            </svg>
                        </div>
                    </div>
                    <p class="mt-3 text-[11px] text-gray-400">Finalized records in this category</p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Tabs -->
    <section>
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex flex-wrap gap-6" aria-label="Finalized documents tabs">
                <?php
                $tabs = [
                    'legislative'    => ['label' => 'Legislative Documents', 'count' => $tabCounts['legislative']],
                    'communications' => ['label' => 'Communications',        'count' => $tabCounts['communications']],
                    'petitions'      => ['label' => 'Petitions',             'count' => $tabCounts['petitions'] ?? 0],
                ];
                foreach ($tabs as $tabKey => $tabInfo):
                    $isActive = ($tab === $tabKey);
                ?>
                <a href="<?= slTabUrl($tabKey, $search) ?>"
                   class="<?= $isActive ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' ?>
                          whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition">
                    <?= htmlspecialchars($tabInfo['label']) ?>
                    <span class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold
                                 <?= $isActive ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700' ?>">
                        <?= number_format($tabInfo['count']) ?>
                    </span>
                </a>
                <?php endforeach; ?>
            </nav>
        </div>
    </section>

    <!-- List -->
    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">

        <!-- Search bar -->
        <div class="flex min-w-0 flex-col gap-4 border-b border-gray-100 px-5 py-5 md:px-6 xl:flex-row xl:items-center xl:justify-between">
            <div class="shrink-0">
                <h2 class="text-base font-semibold text-gray-900"><?= htmlspecialchars($listTitle) ?></h2>
                <p class="mt-1 text-xs text-gray-500"><?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?> found</p>
            </div>
            <form method="GET" action="<?= BASE_URL ?>/plenary/status-list"
                  class="flex w-full min-w-0 flex-col gap-2 sm:flex-row sm:items-center xl:max-w-2xl xl:justify-end">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                <div class="relative min-w-0 flex-1">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="search" name="search" value="<?= htmlspecialchars($search) ?>"
                           placeholder="Search tracking #, subject, or document number"
                           class="block w-full min-w-0 rounded-lg border border-gray-200 bg-white py-2.5 pl-9 pr-3 text-sm
                                  text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none
                                  focus:ring-2 focus:ring-primary/20">
                </div>
                <button type="submit"
                        class="shrink-0 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white
                               transition hover:bg-indigo-700">Filter</button>
                <?php if ($search !== ''): ?>
                    <a href="<?= slTabUrl($tab) ?>"
                       class="shrink-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-center text-sm font-medium
                              text-gray-700 transition hover:bg-gray-50">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($tab === 'communications'): ?>
        <!-- ── Communications Table ──────────────────────────────────────── -->
        <div class="hidden overflow-x-auto 2xl:block">
            <table class="w-full min-w-[1320px] text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-bold">Tracking Number</th>
                        <th class="px-6 py-3 font-bold">Subject Matter</th>
                        <th class="px-6 py-3 font-bold">Decision / Category</th>
                        <th class="px-6 py-3 font-bold">Committees</th>
                        <th class="px-6 py-3 font-bold">Proposed No.</th>
                        <th class="px-6 py-3 font-bold">Resolution No.</th>
                        <th class="px-6 py-3 font-bold">Finalized</th>
                        <th class="px-6 py-3 font-bold">Files</th>
                        <th class="px-6 py-3 font-bold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($communicationDocuments)): ?>
                        <tr>
                            <td colspan="9" class="px-6 py-16 text-center">
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
                            $finalOutputType = $isAdopted
                                ? ($row['adoption_final_output_type'] ?? '')
                                : ($row['fr_final_output_type'] ?? '');
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
                                    <?php $approvedSubject = $row['adoption_approved_subject_matter'] ?: $row['fr_approved_subject_matter']; ?>
                                    <p class="max-w-xs line-clamp-2 text-gray-800">
                                        <?= htmlspecialchars($approvedSubject ?: $row['subject_matter']) ?>
                                    </p>
                                    <?php if (!empty($approvedSubject)
                                        && $approvedSubject !== $row['subject_matter']): ?>
                                        <p class="mt-1 max-w-xs line-clamp-2 text-xs text-gray-500">
                                            Original: <?= htmlspecialchars($row['subject_matter']) ?>
                                        </p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($isAdopted): ?>
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Adopted Comm. Report</span>
                                    <?php elseif ($frDecision === 'APPROVED'): ?>
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700"><span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>Approved for Resolution</span>
                                    <?php elseif ($frDecision === 'NOTED'): ?>
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Noted</span>
                                        <?php if (!empty($row['communication_category_name'])): ?>
                                            <div class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($row['communication_category_name']) ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600"><span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span><?= htmlspecialchars(slDecisionLabel($frDecision)) ?></span>
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
                                    <?php if ($finalOutputType !== ''): ?>
                                        <span class="mb-1 inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700">
                                            <?= htmlspecialchars(ucfirst(strtolower($finalOutputType))) ?>
                                        </span>
                                    <?php endif; ?>
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
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <a href="<?= BASE_URL ?>/plenary/status-list/show?id=<?= (int) $row['document_id'] ?>"
                                       class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white
                                              px-3 py-1.5 text-xs font-medium text-gray-700 hover:border-primary
                                              hover:bg-blue-50 hover:text-primary transition">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php elseif ($tab === 'petitions'): ?>
        <!-- ── Petitions Table (Complaints / Administrative Cases) ──────── -->
        <div class="hidden overflow-x-auto 2xl:block">
            <table class="w-full min-w-[1240px] text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-bold">Tracking Number</th>
                        <th class="px-6 py-3 font-bold">Subject Matter</th>
                        <th class="px-6 py-3 font-bold">Type</th>
                        <th class="px-6 py-3 font-bold">Decision</th>
                        <th class="px-6 py-3 font-bold">Committee(s)</th>
                        <th class="px-6 py-3 font-bold">Resolution No.</th>
                        <th class="px-6 py-3 font-bold">Date Approved</th>
                        <th class="px-6 py-3 font-bold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($petitionsDocuments ?? [])): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">No finalized Petitions yet.</p>
                                <p class="mt-1 text-xs text-gray-500">Petitions finalized via Adopted Committee Report will appear here. Noted petitions are on the <a href="<?= BASE_URL ?>/plenary/petitions" class="text-primary underline">Petitions page</a>.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($petitionsDocuments as $row):
                            $typeColor   = $row['document_type_badge_color'] ?? '#2563EB';
                            $statusColor = $row['status_badge_color'] ?? '#6B7280';
                            $frDecision  = $row['fr_decision'] ?? '';
                            $resolutionNo = !empty($row['adoption_resolution_number'])
                                ? $row['adoption_resolution_number'] : '';
                            $finalOutputType = $row['adoption_final_output_type'] ?? '';
                        ?>
                        <tr class="hover:bg-gray-50/50 transition">
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900"><?= htmlspecialchars($row['tracking_number']) ?></div>
                                <div class="text-xs text-gray-500">
                                    <?= htmlspecialchars(date('M j, Y', strtotime($row['date_received']))) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 max-w-xs">
                                <p class="line-clamp-2 font-medium text-gray-900"><?= htmlspecialchars($row['adoption_approved_subject_matter'] ?: $row['subject_matter']) ?></p>
                                <?php if (!empty($row['adoption_approved_subject_matter'])
                                    && $row['adoption_approved_subject_matter'] !== $row['subject_matter']): ?>
                                    <p class="mt-1 line-clamp-2 text-xs text-gray-500">Original: <?= htmlspecialchars($row['subject_matter']) ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if (!empty($row['document_type_name'])): ?>
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                          style="background-color:<?= htmlspecialchars($typeColor) ?>1a;color:<?= htmlspecialchars($typeColor) ?>;">
                                        <?= htmlspecialchars($row['document_type_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-xs italic text-gray-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                <?php if ($frDecision === 'REFER_TO_COMMITTEE'): ?>
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>Adopted Committee Report
                                    </span>
                                <?php elseif ($frDecision === 'NOTED'): ?>
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Noted
                                    </span>
                                <?php else: ?>
                                    <span class="text-xs italic text-gray-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                <?= !empty($row['committee_names'])
                                    ? '<span class="text-gray-800">' . htmlspecialchars($row['committee_names']) . '</span>'
                                    : '<span class="text-xs italic text-gray-400">—</span>' ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                <?php if ($finalOutputType !== ''): ?>
                                    <span class="mb-1 inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700">
                                        <?= htmlspecialchars(ucfirst(strtolower($finalOutputType))) ?>
                                    </span>
                                <?php endif; ?>
                                <?= $resolutionNo !== ''
                                    ? '<span class="font-medium">' . htmlspecialchars($resolutionNo) . '</span>'
                                    : '<span class="text-xs italic text-gray-400">—</span>' ?>
                                <?php if (!empty($row['adoption_proposed_number'])): ?>
                                    <div class="text-xs text-gray-400">Proposed: <?= htmlspecialchars($row['adoption_proposed_number']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                <?php if (!empty($row['finalized_at'])): ?>
                                    <?= htmlspecialchars(date('M j, Y', strtotime($row['finalized_at']))) ?>
                                <?php elseif (!empty($row['adopted_at'])): ?>
                                    <?= htmlspecialchars(date('M j, Y', strtotime($row['adopted_at']))) ?>
                                <?php else: ?>
                                    <span class="text-xs italic text-gray-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="<?= BASE_URL ?>/plenary/status-list/show?id=<?= (int) $row['document_id'] ?>"
                                   class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 transition">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <!-- ── Legislative Documents Table ──────────────────────────────── -->                <div class="hidden overflow-x-auto 2xl:block">
            <table class="w-full min-w-[1400px] text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-bold">Tracking Number</th>
                        <th class="px-6 py-3 font-bold">Approved No.</th>
                        <th class="px-6 py-3 font-bold">Subject Matter</th>
                        <th class="px-6 py-3 font-bold">Type</th>
                        <th class="px-6 py-3 font-bold">Authors</th>
                        <th class="px-6 py-3 font-bold">Committees</th>
                        <th class="px-6 py-3 font-bold">1st Reading</th>
                        <th class="px-6 py-3 font-bold">2nd Reading</th>
                        <th class="px-6 py-3 font-bold">Date Approved</th>
                        <th class="px-6 py-3 font-bold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($documents)): ?>
                        <tr>
                            <td colspan="10" class="px-6 py-16 text-center">
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
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <a href="<?= BASE_URL ?>/plenary/status-list/show?id=<?= (int) $row['document_id'] ?>"
                                       class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white
                                              px-3 py-1.5 text-xs font-medium text-gray-700 hover:border-primary
                                              hover:bg-blue-50 hover:text-primary transition">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="divide-y divide-gray-100 2xl:hidden">
            <?php if ($tab === 'communications'): ?>
                <?php if (empty($communicationDocuments)): ?>
                    <div class="px-5 py-12 text-center">
                        <p class="text-sm font-medium text-gray-700">No finalized Communications yet.</p>
                        <p class="mt-1 text-xs text-gray-500">Communications approved at First Reading or via adopted committee report will appear here.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($communicationDocuments as $row):
                        $frDecision = $row['fr_decision'] ?? '';
                        $isAdopted = !empty($row['adoption_id']);
                        $approvedSubject = $row['adoption_approved_subject_matter'] ?: $row['fr_approved_subject_matter'];
                        $resNum = $isAdopted ? ($row['adoption_resolution_number'] ?? '') : ($row['fr_resolution_number'] ?? '');
                        $finalOutputType = $isAdopted
                            ? ($row['adoption_final_output_type'] ?? '')
                            : ($row['fr_final_output_type'] ?? '');
                        $propNum = $isAdopted ? ($row['adoption_proposed_number'] ?? '') : ($row['fr_proposed_number'] ?? '');
                        $finalizedDate = $row['finalized_at'] ?? null;
                        $committeeNames = $row['committee_names'] ?? '';
                        $resFiles = $row['resolution_files'] ?? [];
                        if ($isAdopted) {
                            $decisionLabel = 'Adopted Comm. Report';
                            $decisionClass = 'bg-emerald-50 text-emerald-700';
                            $decisionDot = 'bg-emerald-500';
                        } elseif ($frDecision === 'APPROVED') {
                            $decisionLabel = 'Approved for Resolution';
                            $decisionClass = 'bg-blue-50 text-blue-700';
                            $decisionDot = 'bg-blue-500';
                        } elseif ($frDecision === 'NOTED') {
                            $decisionLabel = 'Noted';
                            $decisionClass = 'bg-emerald-50 text-emerald-700';
                            $decisionDot = 'bg-emerald-500';
                        } else {
                            $decisionLabel = slDecisionLabel($frDecision);
                            $decisionClass = 'bg-gray-100 text-gray-700';
                            $decisionDot = 'bg-gray-400';
                        }
                    ?>
                        <article class="p-5 sm:p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="break-words font-semibold text-gray-900"><?= htmlspecialchars($row['tracking_number']) ?></p>
                                    <p class="mt-1 text-xs text-gray-400"><?= htmlspecialchars(date('M j, Y', strtotime($row['date_received']))) ?></p>
                                </div>
                                <a href="<?= BASE_URL ?>/plenary/status-list/show?id=<?= (int) $row['document_id'] ?>"
                                   class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 transition hover:border-primary hover:bg-blue-50 hover:text-primary">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    View
                                </a>
                            </div>
                            <p class="mt-4 text-sm font-medium leading-6 text-gray-900"><?= htmlspecialchars($approvedSubject ?: $row['subject_matter']) ?></p>
                            <?php if (!empty($approvedSubject) && $approvedSubject !== $row['subject_matter']): ?>
                                <p class="mt-1 text-xs leading-5 text-gray-500">Original: <?= htmlspecialchars($row['subject_matter']) ?></p>
                            <?php endif; ?>
                            <dl class="mt-4 grid grid-cols-1 gap-x-4 gap-y-3 border-t border-gray-100 pt-4 sm:grid-cols-2">
                                <div>
                                    <dt class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Decision / Category</dt>
                                    <dd class="mt-1">
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold <?= $decisionClass ?>">
                                            <span class="h-1.5 w-1.5 rounded-full <?= $decisionDot ?>"></span><?= htmlspecialchars($decisionLabel ?: '—') ?>
                                        </span>
                                        <?php if (!empty($row['communication_category_name'])): ?>
                                            <span class="ml-1 text-xs text-gray-500"><?= htmlspecialchars($row['communication_category_name']) ?></span>
                                        <?php endif; ?>
                                    </dd>
                                </div>
                                <div><dt class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Committees</dt><dd class="mt-1 text-sm text-gray-700"><?= htmlspecialchars($committeeNames ?: '—') ?></dd></div>
                                <div><dt class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Proposed No.</dt><dd class="mt-1 text-sm text-gray-700"><?= htmlspecialchars($propNum ?: '—') ?></dd></div>
                                <div>
                                    <dt class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Resolution No.</dt>
                                    <dd class="mt-1 text-sm text-gray-700">
                                        <?php if ($finalOutputType !== ''): ?><span class="mr-1 inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700"><?= htmlspecialchars(ucfirst(strtolower($finalOutputType))) ?></span><?php endif; ?>
                                        <?= htmlspecialchars($resNum ?: '—') ?>
                                    </dd>
                                </div>
                                <div><dt class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Finalized</dt><dd class="mt-1 text-sm text-gray-700"><?= $finalizedDate ? htmlspecialchars(date('M j, Y', strtotime($finalizedDate))) : '—' ?></dd></div>
                                <div>
                                    <dt class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Files</dt>
                                    <dd class="mt-1">
                                        <?php if (!empty($resFiles)): ?>
                                            <div class="space-y-1">
                                                <?php foreach ($resFiles as $rf): ?>
                                                    <a href="<?= BASE_URL ?>/<?= htmlspecialchars($rf['file_path']) ?>" target="_blank" rel="noopener noreferrer" class="block break-all text-xs text-primary hover:underline"><?= htmlspecialchars($rf['original_filename']) ?></a>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-sm text-gray-500">—</span>
                                        <?php endif; ?>
                                    </dd>
                                </div>
                            </dl>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php elseif ($tab === 'petitions'): ?>
                <?php if (empty($petitionsDocuments ?? [])): ?>
                    <div class="px-5 py-12 text-center">
                        <p class="text-sm font-medium text-gray-700">No finalized Petitions yet.</p>
                        <p class="mt-1 text-xs text-gray-500">Petitions finalized via Adopted Committee Report will appear here. Noted petitions are on the <a href="<?= BASE_URL ?>/plenary/petitions" class="text-primary underline">Petitions page</a>.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($petitionsDocuments as $row):
                        $frDecision = $row['fr_decision'] ?? '';
                        $resolutionNo = $row['adoption_resolution_number'] ?? '';
                        $finalOutputType = $row['adoption_final_output_type'] ?? '';
                        $typeColor = $row['document_type_badge_color'] ?? '#2563EB';
                        if ($frDecision === 'REFER_TO_COMMITTEE') {
                            $decisionLabel = 'Adopted Committee Report';
                            $decisionClass = 'bg-indigo-50 text-indigo-700';
                            $decisionDot = 'bg-indigo-500';
                        } elseif ($frDecision === 'NOTED') {
                            $decisionLabel = 'Noted';
                            $decisionClass = 'bg-emerald-50 text-emerald-700';
                            $decisionDot = 'bg-emerald-500';
                        } else {
                            $decisionLabel = '—';
                            $decisionClass = 'bg-gray-100 text-gray-600';
                            $decisionDot = 'bg-gray-400';
                        }
                    ?>
                        <article class="p-5 sm:p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="break-words font-semibold text-gray-900"><?= htmlspecialchars($row['tracking_number']) ?></p>
                                    <p class="mt-1 text-xs text-gray-400"><?= htmlspecialchars(date('M j, Y', strtotime($row['date_received']))) ?></p>
                                </div>
                                <a href="<?= BASE_URL ?>/plenary/status-list/show?id=<?= (int) $row['document_id'] ?>"
                                   class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 transition hover:border-primary hover:bg-blue-50 hover:text-primary">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    View
                                </a>
                            </div>
                            <p class="mt-4 text-sm font-medium leading-6 text-gray-900"><?= htmlspecialchars($row['adoption_approved_subject_matter'] ?: $row['subject_matter']) ?></p>
                            <?php if (!empty($row['adoption_approved_subject_matter']) && $row['adoption_approved_subject_matter'] !== $row['subject_matter']): ?>
                                <p class="mt-1 text-xs leading-5 text-gray-500">Original: <?= htmlspecialchars($row['subject_matter']) ?></p>
                            <?php endif; ?>
                            <dl class="mt-4 grid grid-cols-1 gap-x-4 gap-y-3 border-t border-gray-100 pt-4 sm:grid-cols-2">
                                <div>
                                    <dt class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Type</dt>
                                    <dd class="mt-1"><span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium" style="background-color: <?= htmlspecialchars($typeColor) ?>14; color: <?= htmlspecialchars($typeColor) ?>;"><?= htmlspecialchars($row['document_type_name'] ?? '—') ?></span></dd>
                                </div>
                                <div>
                                    <dt class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Decision</dt>
                                    <dd class="mt-1"><span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold <?= $decisionClass ?>"><span class="h-1.5 w-1.5 rounded-full <?= $decisionDot ?>"></span><?= htmlspecialchars($decisionLabel) ?></span></dd>
                                </div>
                                <div><dt class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Committees</dt><dd class="mt-1 text-sm text-gray-700"><?= htmlspecialchars($row['committee_names'] ?: '—') ?></dd></div>
                                <div>
                                    <dt class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Resolution No.</dt>
                                    <dd class="mt-1 text-sm text-gray-700">
                                        <?php if ($finalOutputType !== ''): ?><span class="mr-1 inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700"><?= htmlspecialchars(ucfirst(strtolower($finalOutputType))) ?></span><?php endif; ?>
                                        <?= htmlspecialchars($resolutionNo ?: '—') ?>
                                        <?php if (!empty($row['adoption_proposed_number'])): ?><span class="mt-1 block text-xs text-gray-400">Proposed: <?= htmlspecialchars($row['adoption_proposed_number']) ?></span><?php endif; ?>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Date Approved</dt>
                                    <dd class="mt-1 text-sm text-gray-700">
                                        <?php if (!empty($row['finalized_at'])): ?>
                                            <?= htmlspecialchars(date('M j, Y', strtotime($row['finalized_at']))) ?>
                                        <?php elseif (!empty($row['adopted_at'])): ?>
                                            <?= htmlspecialchars(date('M j, Y', strtotime($row['adopted_at']))) ?>
                                        <?php else: ?>—<?php endif; ?>
                                    </dd>
                                </div>
                            </dl>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php else: ?>
                <?php if (empty($documents)): ?>
                    <div class="px-5 py-12 text-center">
                        <p class="text-sm font-medium text-gray-700">No finalized documents yet.</p>
                        <p class="mt-1 text-xs text-gray-500">Documents approved on Second Reading will appear here.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($documents as $row):
                        $typeColor = $row['document_type_badge_color'] ?? '#2563EB';
                    ?>
                        <article class="p-5 sm:p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="break-words font-semibold text-gray-900"><?= htmlspecialchars($row['tracking_number']) ?></p>
                                    <p class="mt-1 text-xs text-gray-400"><?= htmlspecialchars(date('M j, Y', strtotime($row['date_received']))) ?></p>
                                </div>
                                <a href="<?= BASE_URL ?>/plenary/status-list/show?id=<?= (int) $row['document_id'] ?>"
                                   class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 transition hover:border-primary hover:bg-blue-50 hover:text-primary">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    View
                                </a>
                            </div>
                            <p class="mt-4 text-sm font-medium leading-6 text-gray-900"><?= htmlspecialchars($row['approved_subject_matter'] ?: $row['subject_matter']) ?></p>
                            <dl class="mt-4 grid grid-cols-1 gap-x-4 gap-y-3 border-t border-gray-100 pt-4 sm:grid-cols-2">
                                <div>
                                    <dt class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Approved No.</dt>
                                    <dd class="mt-1 text-sm font-semibold text-primary"><?= htmlspecialchars($row['approved_no'] ?: '—') ?></dd>
                                </div>
                                <div>
                                    <dt class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Type</dt>
                                    <dd class="mt-1"><span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium" style="background-color: <?= htmlspecialchars($typeColor) ?>14; color: <?= htmlspecialchars($typeColor) ?>;"><?= htmlspecialchars($row['document_type_name'] ?? '—') ?></span></dd>
                                </div>
                                <div><dt class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Authors</dt><dd class="mt-1 text-sm text-gray-700"><?= htmlspecialchars($row['author_names'] ?: '—') ?></dd></div>
                                <div><dt class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Committees</dt><dd class="mt-1 text-sm text-gray-700"><?= htmlspecialchars($row['committee_names'] ?: '—') ?></dd></div>
                                <div>
                                    <dt class="text-[11px] font-medium uppercase tracking-wide text-gray-400">1st Reading</dt>
                                    <dd class="mt-1 text-sm text-gray-700">
                                        <?php if (!empty($row['first_reading_date'])): ?>
                                            <?= htmlspecialchars(date('M j, Y', strtotime($row['first_reading_date']))) ?>
                                            <span class="block text-xs text-gray-400"><?= htmlspecialchars(str_replace('_', ' ', $row['first_reading_action'] ?? '')) ?></span>
                                        <?php else: ?>—<?php endif; ?>
                                    </dd>
                                </div>
                                <div><dt class="text-[11px] font-medium uppercase tracking-wide text-gray-400">2nd Reading</dt><dd class="mt-1 text-sm text-gray-700"><?= !empty($row['second_reading_date']) ? htmlspecialchars(date('M j, Y', strtotime($row['second_reading_date']))) : '—' ?></dd></div>
                                <div><dt class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Date Approved</dt><dd class="mt-1 text-sm font-medium text-emerald-700"><?= !empty($row['date_finalized']) ? htmlspecialchars(date('M j, Y', strtotime($row['date_finalized']))) : '—' ?></dd></div>
                            </dl>
                            <?php if (!empty($row['proposed_number'])): ?>
                                <p class="mt-3 text-xs text-gray-500">Proposed: <?= htmlspecialchars($row['proposed_number']) ?></p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="flex flex-col items-center justify-between gap-3 border-t border-gray-100 px-6 py-4 sm:flex-row">
                <p class="text-xs text-gray-500">
                    Page <span class="font-medium"><?= $page ?></span>
                    of <span class="font-medium"><?= $totalPages ?></span>
                </p>
                <nav class="flex items-center gap-1" aria-label="Pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?= slPageUrl($tab, $page - 1, $search) ?>"
                           aria-label="Go to previous page"
                           class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Prev</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="<?= slPageUrl($tab, $i, $search) ?>"
                           <?= $i === $page ? 'aria-current="page"' : '' ?>
                           class="rounded-lg border px-3 py-1.5 text-sm font-medium transition
                                  <?= $i === $page ? 'border-primary bg-primary text-white' : 'border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?= slPageUrl($tab, $page + 1, $search) ?>"
                           aria-label="Go to next page"
                           class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Next</a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

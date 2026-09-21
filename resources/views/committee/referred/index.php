<?php
/**
 * Committee — Referred Documents (all tabs)
 *
 * Variables supplied by CommitteeReferredController::index():
 *   $documents           array         Rows for the active tab
 *   $tab                 string        'referred'|'for_opinion'|'ready_for_agenda'|'withdrawn'
 *   $page                int
 *   $totalPages          int
 *   $total               int
 *   $search              string
 *   $referredCount       int
 *   $forOpinionCount     int
 *   $readyForAgendaCount int
 *   $withdrawnCount      int
 *   $success             string|null
 *   $error               string|null
 *   $errors              array
 */

$documents           = $documents           ?? [];
$tab                 = $tab                 ?? 'referred';
$page                = $page                ?? 1;
$totalPages          = $totalPages          ?? 1;
$total               = $total               ?? 0;
$search              = $search              ?? '';
$referredCount       = $referredCount       ?? 0;
$forOpinionCount     = $forOpinionCount     ?? 0;
$readyForAgendaCount = $readyForAgendaCount ?? 0;
$withdrawnCount      = $withdrawnCount      ?? 0;
$success             = $success             ?? null;
$error               = $error               ?? null;
$errors              = $errors              ?? [];

ob_start();

// ── Helpers ───────────────────────────────────────────────────────────────────

/** Build query string preserving current params, merging $overrides. */
function referredQueryWith(array $overrides): string
{
    $base = ['tab' => $_GET['tab'] ?? 'referred', 'search' => $_GET['search'] ?? '', 'page' => $_GET['page'] ?? 1];
    $params = array_filter(array_merge($base, $overrides), fn($v) => $v !== '' && $v !== null);
    return http_build_query($params);
}

$tabs = [
    'referred' => [
        'label' => 'Referred',
        'badge' => $referredCount,
        'color' => 'blue',
    ],
    'for_opinion' => [
        'label' => 'For Opinion',
        'badge' => $forOpinionCount,
        'color' => 'violet',
    ],
    'ready_for_agenda' => [
        'label' => 'Ready for Agenda',
        'badge' => $readyForAgendaCount,
        'color' => 'emerald',
    ],
    'withdrawn' => [
        'label' => 'Withdrawn',
        'badge' => $withdrawnCount,
        'color' => 'gray',
    ],
];
?>

<div class="space-y-6">

    <!-- Page header -->
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

    <!-- Flash messages -->
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
            <div class="mt-1.5 text-sm text-red-800">
                <p class="font-medium"><?= htmlspecialchars($error) ?></p>
                <?php if (!empty($errors)): ?>
                    <ul class="mt-1 list-disc pl-4 space-y-0.5">
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tab navigation -->
    <section>
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex gap-1 overflow-x-auto">
                <?php foreach ($tabs as $tabKey => $tabCfg): ?>
                    <?php
                    $isActive  = $tabKey === $tab;
                    $tabUrl    = BASE_URL . '/committee/referred?' . referredQueryWith(['tab' => $tabKey, 'page' => 1]);
                    $activeClass = $isActive
                        ? 'border-primary text-primary font-semibold'
                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 font-medium';
                    ?>
                    <a href="<?= htmlspecialchars($tabUrl) ?>"
                       class="<?= $activeClass ?> whitespace-nowrap border-b-2 px-3 py-3 text-sm transition">
                        <?= htmlspecialchars($tabCfg['label']) ?>
                        <?php if ($tabCfg['badge'] > 0): ?>
                            <span class="ml-1.5 inline-flex items-center rounded-full
                                         <?= $isActive ? 'bg-primary text-white' : 'bg-gray-100 text-gray-600' ?>
                                         px-2 py-0.5 text-xs font-semibold">
                                <?= (int) $tabCfg['badge'] ?>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
    </section>

    <!-- Search bar (shared across all tabs) -->
    <form method="GET" action="<?= BASE_URL ?>/committee/referred"
          class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
        <div class="relative flex-1">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                   placeholder="Search tracking # or subject…"
                   class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm
                          text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none
                          focus:ring-2 focus:ring-primary/20">
        </div>
        <button type="submit"
                class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium
                       text-gray-700 hover:bg-gray-50 transition">
            Search
        </button>
        <?php if ($search !== ''): ?>
            <a href="<?= BASE_URL ?>/committee/referred?tab=<?= htmlspecialchars($tab) ?>"
               class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium
                      text-gray-700 hover:bg-gray-50 transition text-center">
                Clear
            </a>
        <?php endif; ?>
    </form>

    <!-- ── TAB: REFERRED ──────────────────────────────────────────────────── -->
    <?php if ($tab === 'referred'): ?>
    <section class="rounded-2xl border border-gray-200 bg-white">

        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h2 class="font-semibold text-gray-900">Referred Documents</h2>
                <p class="mt-0.5 text-xs text-gray-500"><?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?></p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Tracking #</th>
                        <th class="px-6 py-3 font-medium">Subject / Type</th>
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
                                <?php include __DIR__ . '/_empty.php'; ?>
                                <p class="mt-4 text-sm font-medium text-gray-700">No referred documents yet</p>
                                <p class="mt-1 text-xs text-gray-500">
                                    <?= $search !== '' ? 'No documents match your search.' : 'Documents endorsed as Referred from the Committee inbox will appear here.' ?>
                                </p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($documents as $doc): ?>
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="px-6 py-4">
                                    <a href="<?= BASE_URL ?>/committee/inbox/show?id=<?= (int) $doc['document_id'] ?>"
                                       class="font-medium text-primary hover:underline">
                                        <?= htmlspecialchars($doc['tracking_number']) ?>
                                    </a>
                                    <?php if (!empty($doc['cycle_number'])): ?>
                                        <span class="ml-1 inline-flex rounded-full bg-gray-100 px-1.5 py-0.5
                                                     text-[10px] font-medium text-gray-500">
                                            Cycle&nbsp;<?= (int) $doc['cycle_number'] ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="max-w-xs">
                                        <div class="text-sm font-medium text-gray-900 line-clamp-2">
                                            <?= htmlspecialchars($doc['subject_matter'] ?? '—') ?>
                                        </div>
                                        <?php if (!empty($doc['document_type_name'])): ?>
                                            <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                                  style="background-color:<?= htmlspecialchars($doc['document_type_badge_color'] ?? '#2563EB') ?>1a;
                                                         color:<?= htmlspecialchars($doc['document_type_badge_color'] ?? '#2563EB') ?>;">
                                                <?= htmlspecialchars($doc['document_type_name']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
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
                                <td class="px-6 py-4">
                                    <?php
                                    $rbName     = trim($doc['referred_by_name']     ?? '');
                                    $rbUsername = trim($doc['referred_by_username'] ?? '');
                                    ?>
                                    <?php if ($rbName !== '' || $rbUsername !== ''): ?>
                                        <?php if ($rbName !== ''): ?>
                                            <div class="text-sm font-medium text-gray-800"><?= htmlspecialchars($rbName) ?></div>
                                        <?php endif; ?>
                                        <?php if ($rbUsername !== ''): ?>
                                            <div class="text-xs text-gray-400">@<?= htmlspecialchars($rbUsername) ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400 italic">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php $rmk = trim($doc['sender_remarks'] ?? ''); ?>
                                    <?php if ($rmk !== ''): ?>
                                        <p class="max-w-xs text-xs text-gray-600 italic line-clamp-2"
                                           title="<?= htmlspecialchars($rmk) ?>">
                                            <?= htmlspecialchars($rmk) ?>
                                        </p>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400 italic">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if (!empty($doc['status'])): ?>
                                        <span class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold text-white shadow-sm"
                                              style="background-color:<?= htmlspecialchars($doc['status_badge_color'] ?? '#6B7280') ?>;
                                                     border-color:<?= htmlspecialchars($doc['status_badge_color'] ?? '#6B7280') ?>;">
                                            <?= htmlspecialchars($doc['status']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="<?= BASE_URL ?>/committee/referred/endorse?id=<?= (int) $doc['document_id'] ?>"
                                       class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200
                                              bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700
                                              hover:bg-indigo-100 transition">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <?php include __DIR__ . '/_pagination.php'; ?>
        <?php endif; ?>

    </section>

    <!-- ── TAB: FOR OPINION ──────────────────────────────────────────────── -->
    <?php elseif ($tab === 'for_opinion'): ?>
    <section class="rounded-2xl border border-gray-200 bg-white">

        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h2 class="font-semibold text-gray-900">For Opinion</h2>
                <p class="mt-0.5 text-xs text-gray-500"><?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?></p>
            </div>
        </div>

        <?php if (empty($documents)): ?>
            <div class="flex flex-col items-center justify-center gap-3 px-6 py-16 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"
                              d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                </div>
                <p class="text-sm font-medium text-gray-700">No documents in For Opinion</p>
                <p class="text-xs text-gray-400">
                    <?= $search !== '' ? 'No documents match your search.' : 'Documents endorsed to opinion offices will appear here.' ?>
                </p>
            </div>
        <?php else: ?>
            <div class="space-y-0 divide-y divide-gray-100">
                <?php foreach ($documents as $doc): ?>
                    <?php
                    $endorsements   = $doc['endorsements'] ?? [];
                    $totalOffices   = (int) ($doc['total_offices']   ?? 0);
                    $submittedCount = (int) ($doc['submitted_count'] ?? 0);
                    $favorable      = (int) ($doc['favorable_count'] ?? 0);
                    $unfavorable    = (int) ($doc['unfavorable_count'] ?? 0);
                    $allDone        = $submittedCount >= $totalOffices && $totalOffices > 0;
                    ?>
                    <div class="px-6 py-5 hover:bg-gray-50/40 transition">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">

                            <!-- Doc info -->
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <a href="<?= BASE_URL ?>/committee/inbox/show?id=<?= (int) $doc['document_id'] ?>"
                                       class="font-semibold text-primary hover:underline text-sm">
                                        <?= htmlspecialchars($doc['tracking_number']) ?>
                                    </a>
                                    <span class="inline-flex rounded-full bg-gray-100 px-1.5 py-0.5
                                                 text-[10px] font-medium text-gray-500">
                                        Cycle&nbsp;<?= (int) ($doc['cycle_number'] ?? 0) ?>
                                    </span>
                                    <?php if (!empty($doc['status'])): ?>
                                        <span class="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold text-white"
                                              style="background-color:<?= htmlspecialchars($doc['status_badge_color'] ?? '#6B7280') ?>;">
                                            <?= htmlspecialchars($doc['status']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <p class="mt-1 text-xs text-gray-700 line-clamp-2 max-w-2xl">
                                    <?= htmlspecialchars($doc['subject_matter'] ?? '—') ?>
                                </p>

                                <!-- Opinion office chips -->
                                <?php if (!empty($endorsements)): ?>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <?php
                                        // Group by office: show the highest endorsement number per office
                                        $officeMap = [];
                                        foreach ($endorsements as $end) {
                                            $oid = (int) $end['opinion_office_id'];
                                            $num = (int) ($end['endorsement_number'] ?? 1);
                                            if (!isset($officeMap[$oid]) || $num > (int) $officeMap[$oid]['endorsement_number']) {
                                                $officeMap[$oid] = $end;
                                            }
                                        }
                                        foreach ($officeMap as $end):
                                            $abbr   = trim($end['office_abbr'] ?? '');
                                            $oName  = $end['office_name'] ?? '—';
                                            $oLabel = $abbr !== '' ? $abbr : $oName;
                                            $type   = $end['opinion_type'] ?? null;
                                            $num    = (int) ($end['endorsement_number'] ?? 1);
                                            $endId  = (int) ($end['endorsement_id'] ?? 0);

                                            [$chipBg, $chipText] = match($type) {
                                                'FAVORABLE'   => ['bg-green-100', 'text-green-700'],
                                                'UNFAVORABLE' => ['bg-red-100',   'text-red-700'],
                                                default       => ['bg-violet-100', 'text-violet-700'],
                                            };
                                            $chipLabel = $type ? ucfirst(strtolower($type)) : 'Pending';
                                        ?>
                                            <div class="inline-flex items-center gap-1.5 rounded-full border
                                                        <?= $chipBg ?> px-2.5 py-1 text-xs font-medium <?= $chipText ?>"
                                                 title="<?= htmlspecialchars($oName) ?>">
                                                <span><?= htmlspecialchars($oLabel) ?></span>
                                                <?php if ($num === 2): ?>
                                                    <span class="rounded-full bg-amber-200 px-1 text-[9px] font-bold text-amber-800">#2</span>
                                                <?php endif; ?>
                                                <span class="opacity-70">·</span>
                                                <span><?= $chipLabel ?></span>
                                                <?php if (!$type && $endId > 0): ?>
                                                    <a href="<?= BASE_URL ?>/committee/referred/opinion?endorsement_id=<?= $endId ?>"
                                                       class="ml-1 underline hover:opacity-80">Submit</a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Action column -->
                            <div class="flex shrink-0 flex-wrap items-center gap-2">
                                <!-- Progress indicator -->
                                <span class="text-xs text-gray-500">
                                    <?= $submittedCount ?>/<?= $totalOffices ?> submitted
                                </span>

                                <!-- Resolve button (always available — Committee decides) -->
                                <a href="<?= BASE_URL ?>/committee/referred/resolve?id=<?= (int) $doc['document_id'] ?>"
                                   class="inline-flex items-center gap-1.5 rounded-lg border border-orange-200
                                          bg-orange-50 px-3 py-1.5 text-xs font-semibold text-orange-700
                                          hover:bg-orange-100 transition">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Resolve
                                </a>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($totalPages > 1): ?>
            <?php include __DIR__ . '/_pagination.php'; ?>
        <?php endif; ?>

    </section>

    <!-- ── TAB: READY FOR AGENDA ─────────────────────────────────────────── -->
    <?php elseif ($tab === 'ready_for_agenda'): ?>
    <section class="rounded-2xl border border-gray-200 bg-white">

        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h2 class="font-semibold text-gray-900">Ready for Agenda</h2>
                <p class="mt-0.5 text-xs text-gray-500"><?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?></p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Tracking #</th>
                        <th class="px-6 py-3 font-medium">Subject / Type</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Resolved At</th>
                        <th class="px-6 py-3 font-medium">Notes</th>
                        <th class="px-6 py-3 font-medium text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($documents)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"
                                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">No documents ready for agenda</p>
                                <p class="mt-1 text-xs text-gray-400">
                                    <?= $search !== '' ? 'No documents match your search.' : 'Documents resolved as Proceed to Agenda will appear here.' ?>
                                </p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($documents as $doc): ?>
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="px-6 py-4">
                                    <a href="<?= BASE_URL ?>/committee/inbox/show?id=<?= (int) $doc['document_id'] ?>"
                                       class="font-medium text-primary hover:underline">
                                        <?= htmlspecialchars($doc['tracking_number']) ?>
                                    </a>
                                    <span class="ml-1 inline-flex rounded-full bg-gray-100 px-1.5 py-0.5
                                                 text-[10px] font-medium text-gray-500">
                                        Cycle&nbsp;<?= (int) ($doc['cycle_number'] ?? 0) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="max-w-xs">
                                        <div class="text-sm font-medium text-gray-900 line-clamp-2">
                                            <?= htmlspecialchars($doc['subject_matter'] ?? '—') ?>
                                        </div>
                                        <?php if (!empty($doc['document_type_name'])): ?>
                                            <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                                  style="background-color:<?= htmlspecialchars($doc['document_type_badge_color'] ?? '#2563EB') ?>1a;
                                                         color:<?= htmlspecialchars($doc['document_type_badge_color'] ?? '#2563EB') ?>;">
                                                <?= htmlspecialchars($doc['document_type_name']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if (!empty($doc['status'])): ?>
                                        <span class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold text-white shadow-sm"
                                              style="background-color:<?= htmlspecialchars($doc['status_badge_color'] ?? '#059669') ?>;
                                                     border-color:<?= htmlspecialchars($doc['status_badge_color'] ?? '#059669') ?>;">
                                            <?= htmlspecialchars($doc['status']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-500">
                                    <?php if (!empty($doc['resolved_at'])): ?>
                                        <?= htmlspecialchars(date('M j, Y', strtotime($doc['resolved_at']))) ?>
                                        <span class="block text-gray-400">
                                            <?= htmlspecialchars(date('g:i A', strtotime($doc['resolved_at']))) ?>
                                        </span>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php $rn = trim($doc['resolution_remarks'] ?? ''); ?>
                                    <?php if ($rn !== ''): ?>
                                        <p class="max-w-xs text-xs text-gray-500 italic line-clamp-2"
                                           title="<?= htmlspecialchars($rn) ?>">
                                            <?= htmlspecialchars($rn) ?>
                                        </p>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="<?= BASE_URL ?>/committee/referred/agenda?id=<?= (int) $doc['document_id'] ?>"
                                       class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200
                                              bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700
                                              hover:bg-emerald-100 transition">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        Schedule Agenda
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <?php include __DIR__ . '/_pagination.php'; ?>
        <?php endif; ?>

    </section>

    <!-- ── TAB: WITHDRAWN ────────────────────────────────────────────────── -->
    <?php elseif ($tab === 'withdrawn'): ?>
    <section class="rounded-2xl border border-gray-200 bg-white">

        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h2 class="font-semibold text-gray-900">Withdrawn Documents</h2>
                <p class="mt-0.5 text-xs text-gray-500"><?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?></p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Tracking #</th>
                        <th class="px-6 py-3 font-medium">Subject / Type</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Withdrawn At</th>
                        <th class="px-6 py-3 font-medium">Withdrawn By</th>
                        <th class="px-6 py-3 font-medium">Reason</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($documents)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"
                                              d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">No withdrawn documents</p>
                                <p class="mt-1 text-xs text-gray-400">
                                    <?= $search !== '' ? 'No documents match your search.' : 'Documents withdrawn by Committee decision will appear here.' ?>
                                </p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($documents as $doc): ?>
                            <tr class="hover:bg-gray-50/50 transition opacity-80">
                                <td class="px-6 py-4">
                                    <a href="<?= BASE_URL ?>/committee/inbox/show?id=<?= (int) $doc['document_id'] ?>"
                                       class="font-medium text-gray-600 hover:text-primary hover:underline">
                                        <?= htmlspecialchars($doc['tracking_number']) ?>
                                    </a>
                                    <span class="ml-1 inline-flex rounded-full bg-gray-100 px-1.5 py-0.5
                                                 text-[10px] font-medium text-gray-400">
                                        Cycle&nbsp;<?= (int) ($doc['cycle_number'] ?? 0) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="max-w-xs">
                                        <div class="text-sm text-gray-700 line-clamp-2">
                                            <?= htmlspecialchars($doc['subject_matter'] ?? '—') ?>
                                        </div>
                                        <?php if (!empty($doc['document_type_name'])): ?>
                                            <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                                  style="background-color:<?= htmlspecialchars($doc['document_type_badge_color'] ?? '#6B7280') ?>1a;
                                                         color:<?= htmlspecialchars($doc['document_type_badge_color'] ?? '#6B7280') ?>;">
                                                <?= htmlspecialchars($doc['document_type_name']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if (!empty($doc['status'])): ?>
                                        <span class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold text-white"
                                              style="background-color:<?= htmlspecialchars($doc['status_badge_color'] ?? '#6B7280') ?>;
                                                     border-color:<?= htmlspecialchars($doc['status_badge_color'] ?? '#6B7280') ?>;">
                                            <?= htmlspecialchars($doc['status']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-500">
                                    <?php if (!empty($doc['withdrawn_at'])): ?>
                                        <?= htmlspecialchars(date('M j, Y', strtotime($doc['withdrawn_at']))) ?>
                                        <span class="block text-gray-400">
                                            <?= htmlspecialchars(date('g:i A', strtotime($doc['withdrawn_at']))) ?>
                                        </span>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <?= htmlspecialchars($doc['withdrawn_by_name'] ?? '—') ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php $wr = trim($doc['withdrawal_remarks'] ?? ''); ?>
                                    <?php if ($wr !== ''): ?>
                                        <p class="max-w-xs text-xs text-gray-500 italic line-clamp-2"
                                           title="<?= htmlspecialchars($wr) ?>">
                                            <?= htmlspecialchars($wr) ?>
                                        </p>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <?php include __DIR__ . '/_pagination.php'; ?>
        <?php endif; ?>

    </section>
    <?php endif; ?>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

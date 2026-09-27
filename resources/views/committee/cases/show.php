<?php
/**
 * Committee Cases — Case Details & Timeline
 *
 * Variables supplied by CommitteeCasesController::show():
 *   $case                 array   committee_cases row + joined document / creator fields
 *   $actions              array   committee_case_actions rows ordered by action_date ASC
 *   $attachmentsByAction  array   [ action_id => [ attachment rows ] ]
 *   $success              string|null
 *   $error                string|null
 *   $errors               array
 */

$case                = $case                ?? [];
$actions             = $actions             ?? [];
$attachmentsByAction = $attachmentsByAction ?? [];
$finalizedByName     = $finalizedByName     ?? null;
$linkedReport        = $linkedReport        ?? null;
$success             = $success             ?? null;
$error               = $error               ?? null;
$errors              = $errors              ?? [];

$caseId       = (int)   ($case['id']              ?? 0);
$docketNumber = (string)($case['docket_number']   ?? '');
$documentId   = (int)   ($case['document_id']     ?? 0);

// Respondents: split comma-separated string for display.
$respondentsRaw  = (string)($case['respondents'] ?? '');
$respondentsList = array_values(array_filter(
    array_map('trim', explode(',', $respondentsRaw))
));

/**
 * Map an action type string to a badge colour class.
 * Uses a deterministic hash so new action types always get a colour.
 */
function caseActionBadgeClass(string $actionType): string
{
    $map = [
        'subpoena issued'       => 'bg-red-100 text-red-700 border-red-200',
        'hearing conducted'     => 'bg-blue-100 text-blue-700 border-blue-200',
        'hearing scheduled'     => 'bg-indigo-100 text-indigo-700 border-indigo-200',
        'order issued'          => 'bg-orange-100 text-orange-700 border-orange-200',
        'resolution issued'     => 'bg-emerald-100 text-emerald-700 border-emerald-200',
        'decision rendered'     => 'bg-green-100 text-green-700 border-green-200',
        'motion filed'          => 'bg-yellow-100 text-yellow-700 border-yellow-200',
        'answer filed'          => 'bg-cyan-100 text-cyan-700 border-cyan-200',
        'case dismissed'        => 'bg-gray-100 text-gray-700 border-gray-200',
        'case closed'           => 'bg-gray-100 text-gray-700 border-gray-200',
        'continued'             => 'bg-purple-100 text-purple-700 border-purple-200',
        'postponed'             => 'bg-amber-100 text-amber-700 border-amber-200',
        'position paper filed'  => 'bg-teal-100 text-teal-700 border-teal-200',
        'complaint received'    => 'bg-pink-100 text-pink-700 border-pink-200',
        'notice served'         => 'bg-violet-100 text-violet-700 border-violet-200',
    ];

    $key = strtolower(trim($actionType));
    if (isset($map[$key])) {
        return $map[$key];
    }

    // Deterministic fallback palette.
    $palettes = [
        'bg-blue-100 text-blue-700 border-blue-200',
        'bg-emerald-100 text-emerald-700 border-emerald-200',
        'bg-purple-100 text-purple-700 border-purple-200',
        'bg-amber-100 text-amber-700 border-amber-200',
        'bg-rose-100 text-rose-700 border-rose-200',
        'bg-teal-100 text-teal-700 border-teal-200',
        'bg-indigo-100 text-indigo-700 border-indigo-200',
        'bg-orange-100 text-orange-700 border-orange-200',
    ];
    return $palettes[crc32($key) % count($palettes)];
}

ob_start();
?>

<div class="space-y-6">

    <!-- Back + header --------------------------------------------------------->
    <div class="flex items-center gap-4">
        <a href="<?= BASE_URL ?>/committee/cases"
           class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-gray-200
                  bg-white text-gray-500 hover:bg-gray-50 transition">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                Committee / Cases / Details
            </p>
            <h1 class="font-mono text-xl font-bold text-gray-900">
                <?= htmlspecialchars($docketNumber) ?>
            </h1>
        </div>
    </div>

    <!-- Flash messages -------------------------------------------------------->
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

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

        <!-- Left: case info + timeline (2/3) --------------------------------->
        <div class="space-y-6 xl:col-span-2">

            <!-- Case summary card -------------------------------------------->
            <div class="rounded-2xl border border-gray-200 bg-white p-6">
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-900">Case Information</h2>
                    <span class="font-mono text-sm font-bold text-purple-700">
                        <?= htmlspecialchars($docketNumber) ?>
                    </span>
                </div>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <!-- Tracking Number -->
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Tracking Number</dt>
                        <dd class="mt-1 font-mono text-sm font-bold text-primary">
                            <a href="<?= BASE_URL ?>/committee/inbox/show?id=<?= $documentId ?>"
                               class="hover:underline">
                                <?= htmlspecialchars($case['tracking_number'] ?? '') ?>
                            </a>
                        </dd>
                    </div>
                    <!-- Document Type -->
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Document Type</dt>
                        <dd class="mt-1">
                            <?php
                            $dtBadge = $case['document_type_badge_color'] ?? '#2563EB';
                            ?>
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                  style="background-color:<?= htmlspecialchars($dtBadge) ?>1a;
                                         color:<?= htmlspecialchars($dtBadge) ?>;">
                                <?= htmlspecialchars($case['document_type_name'] ?? '') ?>
                            </span>
                        </dd>
                    </div>
                    <!-- Date Assigned -->
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Date Assigned</dt>
                        <dd class="mt-1 text-sm text-gray-800">
                            <?= htmlspecialchars(
                                !empty($case['date_assigned'])
                                    ? date('F j, Y', strtotime($case['date_assigned']))
                                    : '—'
                            ) ?>
                        </dd>
                    </div>
                    <!-- Current Status -->
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Current Status</dt>
                        <dd class="mt-1">
                            <?php $sBadge = $case['status_badge_color'] ?? '#2563EB'; ?>
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                  style="background-color:<?= htmlspecialchars($sBadge) ?>1a;
                                         color:<?= htmlspecialchars($sBadge) ?>;">
                                <?= htmlspecialchars($case['status'] ?? '') ?>
                            </span>
                        </dd>
                    </div>
                    <!-- Subject Matter -->
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Subject Matter</dt>
                        <dd class="mt-1 text-sm text-gray-800 whitespace-pre-wrap">
                            <?= htmlspecialchars($case['subject_matter'] ?? '') ?>
                        </dd>
                    </div>
                    <!-- Nature of Case -->
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Nature of Case</dt>
                        <dd class="mt-1 text-sm text-gray-800 whitespace-pre-wrap">
                            <?= htmlspecialchars($case['nature_of_case'] ?? '') ?>
                        </dd>
                    </div>
                    <!-- Complainant Details -->
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Complainant Details</dt>
                        <dd class="mt-1 text-sm text-gray-800 whitespace-pre-wrap">
                            <?= htmlspecialchars($case['complainant_details'] ?? '') ?>
                        </dd>
                    </div>
                    <!-- Complainant Municipality -->
                    <?php if (!empty($case['complainant_municipality'])): ?>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">
                                Complainant Municipality / City
                            </dt>
                            <dd class="mt-1 text-sm text-gray-800">
                                <?= htmlspecialchars($case['complainant_municipality']) ?>
                            </dd>
                        </div>
                    <?php endif; ?>
                    <!-- Respondents -->
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Respondents</dt>
                        <dd class="mt-2">
                            <?php if (!empty($respondentsList)): ?>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($respondentsList as $r): ?>
                                        <span class="inline-flex items-center gap-1.5 rounded-xl border
                                                     border-gray-200 bg-gray-50 px-3 py-1 text-xs
                                                     font-medium text-gray-700">
                                            <svg class="h-3 w-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                            </svg>
                                            <?= htmlspecialchars($r) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <span class="text-sm text-gray-400">—</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <!-- Created by -->
                    <?php if (!empty($case['created_by_username'])): ?>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Docketed By</dt>
                            <dd class="mt-1 text-sm text-gray-800">
                                <?php
                                $creatorName = trim($case['created_by_name'] ?? '');
                                echo htmlspecialchars($creatorName !== '' ? $creatorName : $case['created_by_username']);
                                ?>
                                <span class="block text-xs text-gray-400">
                                    <?= htmlspecialchars(date('F j, Y', strtotime($case['created_at']))) ?>
                                </span>
                            </dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </div>

            <!-- Case Timeline ------------------------------------------------>
            <div class="rounded-2xl border border-gray-200 bg-white p-6" id="timelineSection">
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">
                            Summary of Proceedings
                        </h2>
                        <p class="mt-0.5 text-xs text-gray-400">
                            Case timeline — ordered chronologically
                        </p>
                    </div>
                    <!-- Add Action button -->
                    <button type="button" id="addActionBtn"
                            onclick="openActionModal()"
                            class="inline-flex items-center gap-2 rounded-xl bg-purple-600 px-4 py-2
                                   text-sm font-semibold text-white hover:bg-purple-700 transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 4v16m8-8H4"/>
                        </svg>
                        Add Action
                    </button>
                </div>

                <?php if (empty($actions)): ?>
                    <!-- Empty state -->
                    <div class="flex flex-col items-center justify-center py-12 text-center">
                        <div class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 mb-4">
                            <svg class="h-7 w-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2
                                         M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                        <p class="text-sm font-semibold text-gray-600">No actions recorded yet</p>
                        <p class="mt-1 text-xs text-gray-400">
                            Click <strong>Add Action</strong> above to begin building the case timeline.
                        </p>
                    </div>
                <?php else: ?>
                    <!-- Vertical timeline -->
                    <ol class="relative space-y-0" id="timelineList">
                        <?php foreach ($actions as $idx => $action): ?>
                            <?php
                            $actionId   = (int) $action['id'];
                            $badgeClass = caseActionBadgeClass($action['action_type']);
                            $atts       = $attachmentsByAction[$actionId] ?? [];
                            $isLast     = $idx === count($actions) - 1;
                            ?>
                            <li class="relative flex gap-4 <?= $isLast ? '' : 'pb-8' ?>">
                                <!-- Vertical connector line -->
                                <?php if (!$isLast): ?>
                                    <div class="absolute left-4 top-8 bottom-0 w-px bg-gray-200"></div>
                                <?php endif; ?>

                                <!-- Circle marker -->
                                <div class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center
                                            rounded-full border-2 border-white bg-purple-100 shadow-sm mt-0.5">
                                    <div class="h-2.5 w-2.5 rounded-full bg-purple-500"></div>
                                </div>

                                <!-- Content -->
                                <div class="flex-1 min-w-0">
                                    <!-- Action type badge + date -->
                                    <div class="flex flex-wrap items-center gap-2 mb-2">
                                        <span class="inline-flex items-center rounded-full border px-2.5 py-0.5
                                                     text-xs font-semibold <?= $badgeClass ?>">
                                            <?= htmlspecialchars($action['action_type']) ?>
                                        </span>
                                        <time class="text-xs text-gray-400 shrink-0">
                                            <?= htmlspecialchars(
                                                !empty($action['action_date'])
                                                    ? date('F j, Y', strtotime($action['action_date']))
                                                    : '—'
                                            ) ?>
                                        </time>
                                        <?php if (!empty($action['created_by_username'])): ?>
                                            <span class="text-xs text-gray-400">
                                                &middot; by <?= htmlspecialchars($action['created_by_username']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Description -->
                                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                                        <p class="text-sm text-gray-800 whitespace-pre-wrap">
                                            <?= htmlspecialchars($action['description']) ?>
                                        </p>

                                        <!-- Notes -->
                                        <?php if (!empty($action['notes'])): ?>
                                            <div class="mt-3 border-t border-gray-200 pt-3">
                                                <p class="text-xs font-semibold uppercase tracking-wide
                                                          text-gray-400 mb-1">
                                                    Notes
                                                </p>
                                                <p class="text-xs text-gray-600 whitespace-pre-wrap italic">
                                                    <?= htmlspecialchars($action['notes']) ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Attachments -->
                                        <?php if (!empty($atts)): ?>
                                            <div class="mt-3 border-t border-gray-200 pt-3 space-y-2">
                                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                                                    Attachment<?= count($atts) > 1 ? 's' : '' ?>
                                                </p>
                                                <?php foreach ($atts as $att): ?>
                                                    <div class="flex items-center justify-between rounded-lg
                                                                border border-gray-200 bg-white px-3 py-2">
                                                        <div class="flex items-center gap-2 min-w-0">
                                                            <div class="flex h-7 w-7 shrink-0 items-center justify-center
                                                                        rounded-lg bg-blue-50 text-primary">
                                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor"
                                                                     viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                          stroke-width="2"
                                                                          d="M7 21h10a2 2 0 002-2V9.414a1 1 0
                                                                             00-.293-.707l-5.414-5.414A1 1 0
                                                                             0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                                                </svg>
                                                            </div>
                                                            <div class="min-w-0">
                                                                <p class="truncate text-xs font-medium text-gray-800">
                                                                    <?= htmlspecialchars($att['file_name']) ?>
                                                                </p>
                                                                <p class="text-xs text-gray-400">
                                                                    <?= number_format($att['file_size'] / 1024, 1) ?> KB
                                                                    <?php if (!empty($att['uploaded_by_username'])): ?>
                                                                        &middot; <?= htmlspecialchars($att['uploaded_by_username']) ?>
                                                                    <?php endif; ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <a href="<?= BASE_URL ?>/public/<?= htmlspecialchars($att['stored_path']) ?>"
                                                           target="_blank"
                                                           class="ml-3 shrink-0 rounded-lg border border-gray-200 bg-white
                                                                  px-2.5 py-1 text-xs font-medium text-gray-600
                                                                  hover:bg-gray-50 transition">
                                                            Download
                                                        </a>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                    </div><!-- /content box -->
                                </div><!-- /flex-1 -->
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>

            </div><!-- /timeline card -->

            <!-- ═══════════════════════════════════════════════════════════════
                 FINAL OUTCOME SECTION
                 ══════════════════════════════════════════════════════════════ -->
            <?php
            $finalOutcome   = $case['final_outcome']         ?? null;
            $finalizedAt    = $case['finalized_at']          ?? null;
            $outcomeRemarks = $case['final_outcome_remarks'] ?? null;
            $hasOutcome     = !empty($finalOutcome);
            $actionCount    = count($actions);

            // Badge config per outcome
            $outcomeBadges = [
                'APPROVED'  => ['label' => 'Approved',  'bg' => 'bg-green-100',  'text' => 'text-green-700',  'border' => 'border-green-200',  'icon_color' => 'text-green-500'],
                'DEFERRED'  => ['label' => 'Deferred',  'bg' => 'bg-gray-100',   'text' => 'text-gray-700',   'border' => 'border-gray-200',   'icon_color' => 'text-gray-400'],
                'WITHDRAWN' => ['label' => 'Withdrawn', 'bg' => 'bg-amber-100',  'text' => 'text-amber-700',  'border' => 'border-amber-200',  'icon_color' => 'text-amber-500'],
                'NOTED'     => ['label' => 'Noted',     'bg' => 'bg-cyan-100',   'text' => 'text-cyan-700',   'border' => 'border-cyan-200',   'icon_color' => 'text-cyan-500'],
            ];
            $currentBadge = $hasOutcome ? ($outcomeBadges[$finalOutcome] ?? $outcomeBadges['NOTED']) : null;
            ?>

            <div class="rounded-2xl border border-gray-200 bg-white p-6" id="finalOutcomeSection">
                <div class="mb-5 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Final Outcome</h2>
                        <p class="mt-0.5 text-xs text-gray-400">
                            <?= $hasOutcome
                                ? 'This case has been finalized.'
                                : 'Select the outcome to close this case.' ?>
                        </p>
                    </div>
                    <?php if ($hasOutcome && $currentBadge): ?>
                        <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1
                                     text-xs font-semibold
                                     <?= $currentBadge['bg'] ?> <?= $currentBadge['text'] ?> <?= $currentBadge['border'] ?>">
                            <span class="h-1.5 w-1.5 rounded-full <?= $currentBadge['icon_color'] ?> bg-current"></span>
                            <?= $currentBadge['label'] ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($hasOutcome): ?>
                    <!-- ── Finalized state ─────────────────────────────────── -->
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Outcome</dt>
                            <dd class="mt-1">
                                <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1
                                             text-sm font-semibold
                                             <?= $currentBadge['bg'] ?> <?= $currentBadge['text'] ?> <?= $currentBadge['border'] ?>">
                                    <?= $currentBadge['label'] ?>
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Finalized On</dt>
                            <dd class="mt-1 text-sm text-gray-800">
                                <?= $finalizedAt
                                    ? htmlspecialchars(date('F j, Y g:i A', strtotime($finalizedAt)))
                                    : '—' ?>
                            </dd>
                        </div>
                        <?php if ($finalizedByName): ?>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Finalized By</dt>
                                <dd class="mt-1 text-sm text-gray-800">
                                    <?= htmlspecialchars($finalizedByName) ?>
                                </dd>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($outcomeRemarks)): ?>
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Remarks</dt>
                                <dd class="mt-1 text-sm text-gray-700 whitespace-pre-wrap">
                                    <?= htmlspecialchars($outcomeRemarks) ?>
                                </dd>
                            </div>
                        <?php endif; ?>
                    </dl>

                    <?php if ($finalOutcome === 'APPROVED'): ?>
                        <!-- ── Approved post-outcome actions ───────────────── -->
                        <div class="mt-6 border-t border-gray-100 pt-5 space-y-3">
                            <?php if ($linkedReport): ?>
                                <!-- Report already exists -->
                                <div class="flex items-center justify-between rounded-xl border border-green-200
                                            bg-green-50 px-4 py-3">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-green-600">
                                            Committee Report
                                        </p>
                                        <p class="mt-0.5 text-sm font-medium text-green-800">
                                            #<?= htmlspecialchars($linkedReport['report_number'] ?? '—') ?>
                                        </p>
                                        <?php if (!empty($linkedReport['returned_to_plenary_at'])): ?>
                                            <span class="mt-1 inline-flex items-center gap-1 rounded-full
                                                         bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-700">
                                                Returned to Plenary
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <a href="<?= BASE_URL ?>/committee/reports/show?id=<?= (int) $linkedReport['id'] ?>"
                                       class="inline-flex items-center gap-1.5 rounded-xl border border-green-300
                                              bg-white px-3 py-1.5 text-xs font-semibold text-green-700
                                              hover:bg-green-50 transition">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0
                                                     8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542
                                                     7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        View Report
                                    </a>
                                </div>
                            <?php else: ?>
                                <!-- No report yet — show Create button -->
                                <a href="<?= BASE_URL ?>/committee/cases/report?case_id=<?= $caseId ?>"
                                   class="inline-flex w-full items-center justify-center gap-2 rounded-xl
                                          bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white
                                          hover:bg-emerald-700 transition">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0
                                                 00.707-.293l5.414-5.414A1 1 0 0121 4.586V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    Create Committee Report
                                </a>
                                <p class="text-center text-xs text-gray-400">
                                    This case is approved and ready for a Committee Report.
                                </p>
                            <?php endif; ?>
                            <a href="<?= BASE_URL ?>/committee/cases/for-report"
                               class="inline-flex w-full items-center justify-center gap-2 rounded-xl
                                      border border-gray-200 bg-white px-4 py-2 text-sm font-medium
                                      text-gray-600 hover:bg-gray-50 transition">
                                ← Back to For Report
                            </a>
                        </div>

                    <?php elseif (in_array($finalOutcome, ['DEFERRED', 'WITHDRAWN', 'NOTED'])): ?>
                        <!-- ── Non-approved outcome links ──────────────────── -->
                        <div class="mt-5 border-t border-gray-100 pt-4">
                            <?php
                            $outcomeLinkMap = [
                                'DEFERRED'  => ['url' => 'committee/cases/deferred',  'label' => '← Back to Deferred'],
                                'WITHDRAWN' => ['url' => 'committee/cases/withdrawn', 'label' => '← Back to Withdrawn'],
                                'NOTED'     => ['url' => 'committee/cases/noted',     'label' => '← Back to Noted'],
                            ];
                            $ol = $outcomeLinkMap[$finalOutcome];
                            ?>
                            <a href="<?= BASE_URL ?>/<?= $ol['url'] ?>"
                               class="inline-flex w-full items-center justify-center gap-2 rounded-xl
                                      border border-gray-200 bg-white px-4 py-2 text-sm font-medium
                                      text-gray-600 hover:bg-gray-50 transition">
                                <?= $ol['label'] ?>
                            </a>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- ── Not yet finalized — show the form ──────────────── -->

                    <?php if ($actionCount === 0): ?>
                        <!-- Blocked: no timeline actions yet -->
                        <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 mb-4">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" fill="none"
                                 stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0
                                         2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464
                                         0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <p class="text-sm text-amber-800">
                                You must add at least one <strong>timeline action</strong> before
                                finalizing the case.
                            </p>
                        </div>
                    <?php endif; ?>

                    <form method="POST"
                          action="<?= BASE_URL ?>/committee/cases/outcome"
                          id="outcomeForm"
                          novalidate>
                        <input type="hidden" name="case_id" value="<?= $caseId ?>">

                        <div class="space-y-4">

                            <!-- Outcome select -->
                            <div>
                                <label for="final_outcome"
                                       class="block text-xs font-semibold uppercase tracking-wide
                                              text-gray-500 mb-1">
                                    Select Final Outcome <span class="text-red-500">*</span>
                                </label>
                                <select id="final_outcome"
                                        name="final_outcome"
                                        required
                                        class="block w-full rounded-xl border border-gray-300 bg-white
                                               px-3 py-2 text-sm focus:border-primary
                                               focus:ring-1 focus:ring-primary">
                                    <option value="">— Choose an outcome —</option>
                                    <option value="APPROVED">Approved — Hearing Completed → For Report</option>
                                    <option value="DEFERRED">Defer — Case sent to Deferred</option>
                                    <option value="WITHDRAWN">Withdraw — Case sent to Withdrawn</option>
                                    <option value="NOTED">Noted — Case sent to Noted</option>
                                </select>
                            </div>

                            <!-- Remarks (optional) -->
                            <div>
                                <label for="final_outcome_remarks"
                                       class="block text-xs font-semibold uppercase tracking-wide
                                              text-gray-500 mb-1">
                                    Remarks
                                    <span class="ml-1 font-normal text-gray-400
                                                 normal-case tracking-normal">(optional)</span>
                                </label>
                                <textarea id="final_outcome_remarks"
                                          name="final_outcome_remarks"
                                          rows="3"
                                          maxlength="5000"
                                          placeholder="Add any remarks or notes about this outcome…"
                                          class="block w-full rounded-xl border border-gray-300 px-3 py-2
                                                 text-sm focus:border-primary focus:ring-1
                                                 focus:ring-primary"></textarea>
                            </div>

                            <!-- Confirm button -->
                            <button type="button"
                                    id="confirmOutcomeBtn"
                                    <?= $actionCount === 0 ? 'disabled' : '' ?>
                                    class="inline-flex w-full items-center justify-center gap-2
                                           rounded-xl px-4 py-2.5 text-sm font-semibold text-white
                                           bg-purple-600 hover:bg-purple-700 transition
                                           disabled:opacity-40 disabled:cursor-not-allowed">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M5 13l4 4L19 7"/>
                                </svg>
                                Confirm Final Outcome
                            </button>

                        </div>
                    </form>
                <?php endif; ?>

            </div><!-- /final outcome section -->

        </div><!-- /left column -->

        <!-- Right: meta panel (1/3) ----------------------------------------->
        <div class="xl:col-span-1">
            <div class="sticky top-6 space-y-4">

                <!-- Docket info card ----------------------------------------->
                <div class="rounded-2xl border border-purple-200 bg-purple-50 p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-purple-600 mb-3">
                        Docket Information
                    </p>
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-xs text-purple-500">Docket Number</dt>
                            <dd class="font-mono text-base font-bold text-purple-900">
                                <?= htmlspecialchars($docketNumber) ?>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-purple-500">Year</dt>
                            <dd class="text-sm font-semibold text-purple-800">
                                <?= htmlspecialchars((string)($case['docket_year'] ?? '')) ?>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-purple-500">Sequence</dt>
                            <dd class="font-mono text-sm font-semibold text-purple-800">
                                <?= number_format((int)($case['docket_sequence'] ?? 0)) ?>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-purple-500">Date Assigned</dt>
                            <dd class="text-sm text-purple-800">
                                <?= htmlspecialchars(
                                    !empty($case['date_assigned'])
                                        ? date('F j, Y', strtotime($case['date_assigned']))
                                        : '—'
                                ) ?>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-purple-500">Actions on Timeline</dt>
                            <dd class="text-2xl font-bold text-purple-900">
                                <?= count($actions) ?>
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Source document card ------------------------------------->
                <div class="rounded-2xl border border-gray-200 bg-white p-5">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        Source Document
                    </p>
                    <dl class="space-y-2">
                        <div>
                            <dt class="text-xs text-gray-400">Tracking Number</dt>
                            <dd class="font-mono text-sm font-bold text-primary">
                                <a href="<?= BASE_URL ?>/committee/inbox/show?id=<?= $documentId ?>"
                                   class="hover:underline">
                                    <?= htmlspecialchars($case['tracking_number'] ?? '') ?>
                                </a>
                            </dd>
                        </div>
                        <?php if (!empty($case['source_type'])): ?>
                            <div>
                                <dt class="text-xs text-gray-400">Source Type</dt>
                                <dd class="text-sm text-gray-700">
                                    <?= htmlspecialchars($case['source_type']) ?>
                                </dd>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($case['external_office_name'])): ?>
                            <div>
                                <dt class="text-xs text-gray-400">External Office</dt>
                                <dd class="text-sm text-gray-700">
                                    <?= htmlspecialchars($case['external_office_name']) ?>
                                </dd>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($case['hospital_name'])): ?>
                            <div>
                                <dt class="text-xs text-gray-400">Hospital</dt>
                                <dd class="text-sm text-gray-700">
                                    <?= htmlspecialchars($case['hospital_name']) ?>
                                </dd>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($case['municipality_name'])): ?>
                            <div>
                                <dt class="text-xs text-gray-400">Municipality</dt>
                                <dd class="text-sm text-gray-700">
                                    <?= htmlspecialchars($case['municipality_name']) ?>
                                </dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                </div>

            </div>
        </div><!-- /right column -->

    </div><!-- /grid -->

</div><!-- /space-y-6 -->


<!-- ═══════════════════════════════════════════════════════════════════════════
     ADD ACTION MODAL
     ═══════════════════════════════════════════════════════════════════════ -->
<div id="actionModal"
     class="fixed inset-0 z-[200] hidden items-center justify-center p-4"
     role="dialog" aria-modal="true" aria-labelledby="actionModalTitle">

    <!-- Backdrop -->
    <div id="actionModalBackdrop"
         class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm opacity-0 transition-opacity duration-200"
         onclick="closeActionModal()"></div>

    <!-- Panel -->
    <div id="actionModalPanel"
         class="relative z-10 w-full max-w-xl bg-white rounded-2xl shadow-2xl border border-gray-100
                transform scale-95 opacity-0 transition-all duration-200 max-h-[90vh] overflow-y-auto">

        <!-- Modal header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h3 id="actionModalTitle" class="text-base font-semibold text-gray-900">
                    Add Case Action
                </h3>
                <p class="text-xs text-gray-400 mt-0.5">
                    Record a new event on the case timeline.
                </p>
            </div>
            <button type="button" onclick="closeActionModal()"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400
                           hover:bg-gray-100 hover:text-gray-600 transition">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Modal form -->
        <form method="POST" action="<?= BASE_URL ?>/committee/cases/actions/store"
              enctype="multipart/form-data"
              id="actionForm" novalidate>
            <input type="hidden" name="case_id" value="<?= $caseId ?>">

            <div class="px-6 py-5 space-y-4">

                <!-- Action Type -->
                <div>
                    <label for="modal_action_type"
                           class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                        Action Type <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           id="modal_action_type"
                           name="action_type"
                           maxlength="150"
                           required
                           placeholder="e.g. Subpoena Issued, Hearing Conducted, Order Issued…"
                           class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm
                                  focus:border-primary focus:ring-1 focus:ring-primary">
                </div>

                <!-- Action Date -->
                <div>
                    <label for="modal_action_date"
                           class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                        Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date"
                           id="modal_action_date"
                           name="action_date"
                           value="<?= date('Y-m-d') ?>"
                           required
                           class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm
                                  focus:border-primary focus:ring-1 focus:ring-primary">
                </div>

                <!-- Description -->
                <div>
                    <label for="modal_description"
                           class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                        Description <span class="text-red-500">*</span>
                    </label>
                    <textarea id="modal_description"
                              name="description"
                              rows="4"
                              required
                              placeholder="Describe what happened in this action…"
                              class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm
                                     focus:border-primary focus:ring-1 focus:ring-primary"></textarea>
                </div>

                <!-- Attachments (optional, multiple) -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                        Attachments
                        <span class="ml-1 font-normal text-gray-400 normal-case tracking-normal">(optional — up to 10 files, 25 MB each)</span>
                    </label>
                    <div class="flex items-center justify-center w-full">
                        <label for="modal_attachment"
                               class="flex flex-col items-center justify-center w-full h-24 border-2
                                      border-gray-300 border-dashed rounded-xl cursor-pointer bg-gray-50
                                      hover:bg-gray-100 transition"
                               id="attachDropZone">
                            <div class="flex flex-col items-center justify-center py-3" id="attachDropContent">
                                <svg class="w-6 h-6 mb-1 text-gray-400" fill="none" stroke="currentColor"
                                     viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9
                                             M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>
                                <p class="text-xs text-gray-500 text-center px-3" id="attachDropText">
                                    <span class="font-semibold text-gray-700">Click to upload</span>
                                    or drag and drop<br>
                                    PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, GIF, WEBP &mdash; max 25 MB each, up to 10 files
                                </p>
                            </div>
                            <input id="modal_attachment" name="attachments[]" type="file"
                                   class="hidden" multiple
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp">
                        </label>
                    </div>
                    <!-- File list preview -->
                    <ul id="attachFileList" class="mt-2 space-y-1.5"></ul>
                    <!-- Inline validation error -->
                    <p id="attachError" class="mt-1 hidden text-xs text-red-600"></p>
                </div>

                <!-- Notes (optional) -->
                <div>
                    <label for="modal_notes"
                           class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                        Notes
                        <span class="ml-1 font-normal text-gray-400 normal-case tracking-normal">(optional)</span>
                    </label>
                    <textarea id="modal_notes"
                              name="notes"
                              rows="3"
                              placeholder="Any additional notes or observations…"
                              class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm
                                     focus:border-primary focus:ring-1 focus:ring-primary"></textarea>
                </div>

            </div><!-- /px-6 py-5 -->

            <!-- Modal footer -->
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50
                        rounded-b-2xl">
                <button type="button" onclick="closeActionModal()"
                        class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm
                               font-medium text-gray-700 bg-white border border-gray-200
                               hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit" id="actionSubmitBtn"
                        class="inline-flex items-center justify-center gap-2 rounded-xl px-5 py-2
                               text-sm font-semibold text-white bg-purple-600 hover:bg-purple-700 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M5 13l4 4L19 7"/>
                    </svg>
                    Save Action
                </button>
            </div>

        </form>
    </div><!-- /panel -->
</div><!-- /modal -->


<script>
// ─── Modal open / close ────────────────────────────────────────────────────
function openActionModal() {
    const modal    = document.getElementById('actionModal');
    const backdrop = document.getElementById('actionModalBackdrop');
    const panel    = document.getElementById('actionModalPanel');

    modal.classList.remove('hidden');
    modal.classList.add('flex');

    // Trigger transition on next paint.
    requestAnimationFrame(function () {
        backdrop.classList.remove('opacity-0');
        backdrop.classList.add('opacity-100');
        panel.classList.remove('scale-95', 'opacity-0');
        panel.classList.add('scale-100', 'opacity-100');
    });

    document.getElementById('modal_action_type').focus();
}

function closeActionModal() {
    const modal    = document.getElementById('actionModal');
    const backdrop = document.getElementById('actionModalBackdrop');
    const panel    = document.getElementById('actionModalPanel');

    backdrop.classList.remove('opacity-100');
    backdrop.classList.add('opacity-0');
    panel.classList.remove('scale-100', 'opacity-100');
    panel.classList.add('scale-95', 'opacity-0');

    setTimeout(function () {
        modal.classList.remove('flex');
        modal.classList.add('hidden');
    }, 200);
}

// Close on Escape.
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeActionModal();
});

// ─── Multi-file input / drag-and-drop handling ────────────────────────────
(function () {
    const input    = document.getElementById('modal_attachment');
    const listEl   = document.getElementById('attachFileList');
    const errorEl  = document.getElementById('attachError');
    const dropZone = document.getElementById('attachDropZone');
    const dropText = document.getElementById('attachDropText');

    const MAX_FILES = 10;
    const MAX_MB    = 25;
    const ALLOWED   = ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png','gif','webp'];

    // Master list of validated File objects (across multiple picker sessions).
    var selectedFiles = [];

    function formatSize(bytes) {
        if (bytes >= 1024 * 1024) return (bytes / 1024 / 1024).toFixed(2) + ' MB';
        return (bytes / 1024).toFixed(1) + ' KB';
    }

    function updateDropText() {
        if (selectedFiles.length === 0) {
            dropText.innerHTML =
                '<span class="font-semibold text-gray-700">Click to upload</span> or drag and drop<br>' +
                'PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, GIF, WEBP &mdash; max 25 MB each, up to 10 files';
        } else {
            dropText.innerHTML =
                '<span class="font-semibold text-gray-700">' + selectedFiles.length +
                ' file' + (selectedFiles.length !== 1 ? 's' : '') + ' selected.</span>' +
                ' Click to add more.';
        }
    }

    function syncInputFiles() {
        // Keep the real <input> in sync so it submits the current selectedFiles list.
        try {
            var dt = new DataTransfer();
            selectedFiles.forEach(function (f) { dt.items.add(f); });
            input.files = dt.files;
        } catch (_) {
            // DataTransfer not available (old Safari) — form will still submit
            // the files the browser last assigned to the input.
        }
    }

    function renderList() {
        listEl.innerHTML = '';
        selectedFiles.forEach(function (file, idx) {
            var li = document.createElement('li');
            li.className =
                'flex items-center justify-between rounded-lg border border-gray-200 ' +
                'bg-gray-50 px-3 py-2';
            li.innerHTML =
                '<div class="flex items-center gap-2 min-w-0">' +
                    '<svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"' +
                              ' d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414' +
                              'A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>' +
                    '</svg>' +
                    '<span class="truncate text-xs font-medium text-gray-700">' + escHtml(file.name) + '</span>' +
                    '<span class="shrink-0 text-xs text-gray-400 ml-1">' + formatSize(file.size) + '</span>' +
                '</div>' +
                '<button type="button" data-idx="' + idx + '"' +
                        ' class="remove-file-btn ml-2 shrink-0 text-xs text-red-500 hover:text-red-700 font-medium">' +
                    'Remove' +
                '</button>';
            listEl.appendChild(li);
        });

        // Attach remove handlers.
        listEl.querySelectorAll('.remove-file-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var i = parseInt(this.getAttribute('data-idx'), 10);
                selectedFiles.splice(i, 1);
                syncInputFiles();
                renderList();
                updateDropText();
                errorEl.classList.add('hidden');
            });
        });

        updateDropText();
    }

    function escHtml(str) {
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function addFiles(fileList) {
        errorEl.classList.add('hidden');
        var errs = [];

        Array.from(fileList).forEach(function (file) {
            var ext = (file.name.split('.').pop() || '').toLowerCase();

            if (!ALLOWED.includes(ext)) {
                errs.push('\u201c' + file.name + '\u201d: file type .' + ext + ' is not allowed.');
                return;
            }
            if (file.size > MAX_MB * 1024 * 1024) {
                errs.push('\u201c' + file.name + '\u201d: exceeds the ' + MAX_MB + ' MB limit.');
                return;
            }
            // Avoid exact duplicate names (same name + size heuristic).
            var isDupe = selectedFiles.some(function (f) {
                return f.name === file.name && f.size === file.size;
            });
            if (!isDupe) {
                selectedFiles.push(file);
            }
        });

        if (selectedFiles.length > MAX_FILES) {
            errs.push('You may attach a maximum of ' + MAX_FILES + ' files. Extra files were not added.');
            selectedFiles = selectedFiles.slice(0, MAX_FILES);
        }

        if (errs.length) {
            errorEl.textContent = errs.join(' ');
            errorEl.classList.remove('hidden');
        }

        syncInputFiles();
        renderList();
    }

    input.addEventListener('change', function () {
        if (this.files.length) {
            addFiles(this.files);
            // Reset so the same file can be re-picked after removal.
            this.value = '';
        }
    });

    // Drag-and-drop on the label.
    ['dragenter', 'dragover'].forEach(function (ev) {
        dropZone.addEventListener(ev, function (e) {
            e.preventDefault();
            dropZone.classList.add('border-primary', 'bg-blue-50');
        });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        dropZone.addEventListener(ev, function (e) {
            e.preventDefault();
            dropZone.classList.remove('border-primary', 'bg-blue-50');
        });
    });
    dropZone.addEventListener('drop', function (e) {
        e.preventDefault();
        if (e.dataTransfer.files.length) {
            addFiles(e.dataTransfer.files);
        }
    });

    // Reset state when the modal is closed so a fresh open starts empty.
    document.getElementById('actionModal')
        .addEventListener('transitionend', function () {
            if (this.classList.contains('hidden')) {
                selectedFiles = [];
                listEl.innerHTML = '';
                errorEl.classList.add('hidden');
                input.value = '';
                updateDropText();
            }
        });
}());

// ─── Action form: prevent double-submit ───────────────────────────────────
document.getElementById('actionForm').addEventListener('submit', function () {
    const btn = document.getElementById('actionSubmitBtn');
    btn.disabled = true;
    btn.innerHTML =
        '<svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg"' +
        '     fill="none" viewBox="0 0 24 24">' +
        '  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
        '  <path class="opacity-75" fill="currentColor"' +
        '        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962' +
        '           7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>' +
        '</svg>' +
        '<span class="ml-2">Saving\u2026</span>';
});

// ─── Auto-open modal if there were validation errors on a prior submit ─────
<?php if (!empty($errors)): ?>
openActionModal();
<?php endif; ?>
</script>

<!-- ═══════════════════════════════════════════════════════════════════════════
     FINAL OUTCOME CONFIRMATION MODAL
     ═══════════════════════════════════════════════════════════════════════ -->
<?php if (empty($case['final_outcome'])): ?>
<div id="outcomeConfirmModal"
     class="fixed inset-0 z-[300] hidden items-center justify-center p-4"
     role="dialog" aria-modal="true" aria-labelledby="outcomeModalTitle">
    <!-- Backdrop -->
    <div id="outcomeModalBackdrop"
         class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm opacity-0 transition-opacity duration-200"
         onclick="closeOutcomeModal()"></div>
    <!-- Panel -->
    <div id="outcomeModalPanel"
         class="relative z-10 w-full max-w-md bg-white rounded-2xl shadow-2xl border border-gray-100
                transform scale-95 opacity-0 transition-all duration-200">
        <div class="p-6">
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-100 mb-4">
                <svg class="h-6 w-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0
                             2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464
                             0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <h3 id="outcomeModalTitle" class="text-base font-semibold text-gray-900">
                Confirm Final Outcome
            </h3>
            <p id="outcomeModalBody" class="mt-2 text-sm text-gray-600">
                You are about to finalize this case. This action will change the document workflow
                status and cannot be undone through normal operations.
            </p>
            <div class="mt-5 flex items-center justify-end gap-3">
                <button type="button" onclick="closeOutcomeModal()"
                        class="inline-flex items-center justify-center rounded-xl border border-gray-200
                               bg-white px-4 py-2 text-sm font-medium text-gray-700
                               hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="button" id="outcomeConfirmSubmit"
                        class="inline-flex items-center gap-2 rounded-xl bg-purple-600 px-5 py-2
                               text-sm font-semibold text-white hover:bg-purple-700 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Yes, Finalize
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const outcomeLabels = {
        'APPROVED':  'Approved — document status will be set to Hearing Completed.',
        'DEFERRED':  'Deferred — document status will be set to Deferred.',
        'WITHDRAWN': 'Withdrawn — document status will be set to Withdrawn.',
        'NOTED':     'Noted — document status will be set to Noted.',
    };

    function openOutcomeModal() {
        const modal    = document.getElementById('outcomeConfirmModal');
        const backdrop = document.getElementById('outcomeModalBackdrop');
        const panel    = document.getElementById('outcomeModalPanel');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        requestAnimationFrame(function () {
            backdrop.classList.remove('opacity-0');
            backdrop.classList.add('opacity-100');
            panel.classList.remove('scale-95', 'opacity-0');
            panel.classList.add('scale-100', 'opacity-100');
        });
    }

    window.closeOutcomeModal = function () {
        const modal    = document.getElementById('outcomeConfirmModal');
        const backdrop = document.getElementById('outcomeModalBackdrop');
        const panel    = document.getElementById('outcomeModalPanel');
        backdrop.classList.remove('opacity-100');
        backdrop.classList.add('opacity-0');
        panel.classList.remove('scale-100', 'opacity-100');
        panel.classList.add('scale-95', 'opacity-0');
        setTimeout(function () {
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }, 200);
    };

    const confirmBtn = document.getElementById('confirmOutcomeBtn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function () {
            const outcomeEl = document.getElementById('final_outcome');
            const selected  = outcomeEl ? outcomeEl.value : '';

            if (!selected) {
                outcomeEl.classList.add('border-red-400');
                outcomeEl.focus();
                return;
            }
            outcomeEl.classList.remove('border-red-400');

            const body = document.getElementById('outcomeModalBody');
            if (body) {
                body.textContent = outcomeLabels[selected] ||
                    'You are about to finalize this case. This action will change the document workflow status.';
            }
            openOutcomeModal();
        });
    }

    const submitBtn = document.getElementById('outcomeConfirmSubmit');
    if (submitBtn) {
        submitBtn.addEventListener('click', function () {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving…';
            const form = document.getElementById('outcomeForm');
            if (form) form.submit();
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeOutcomeModal();
    });
}());
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

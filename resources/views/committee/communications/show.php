<?php
/**
 * Committee Communications — Record Detail & Workflow Progress
 *
 * Variables supplied by CommitteeCommunicationsController::show():
 *   $comm          array         committee_communications row + document joins
 *   $attachments   array         document_attachments rows
 *   $events        array         document_events rows
 *   $linkedAgenda  array|null    agendas row (if scheduled)
 *   $linkedHearing array|null    committee_hearings row (if recorded)
 *   $linkedReport  array|null    committee_reports row (if created)
 *   $success       string|null
 *   $error         string|null
 *   $errors        array
 */

$comm          = $comm          ?? [];
$attachments   = $attachments   ?? [];
$events        = $events        ?? [];
$linkedAgenda  = $linkedAgenda  ?? null;
$linkedHearing = $linkedHearing ?? null;
$linkedReport  = $linkedReport  ?? null;
$success       = $success       ?? null;
$error         = $error         ?? null;
$errors        = $errors        ?? [];

$commId     = (int)    ($comm['id']          ?? 0);
$documentId = (int)    ($comm['document_id'] ?? 0);
$subject    = (string) ($comm['subject']     ?? '');

// Workflow step helper — which step are we currently on?
$hasAgenda  = !empty($comm['agenda_id']);
$hasHearing = !empty($comm['hearing_id']);
$hasReport  = !empty($comm['report_id']);

$hearingOutcome = $linkedHearing['outcome'] ?? null;
$hearingApproved = $hearingOutcome === 'APPROVED';

ob_start();
?>

<div class="space-y-6">

    <!-- Back + header --------------------------------------------------------->
    <div class="flex items-center gap-4">
        <a href="<?= BASE_URL ?>/committee/communications"
           class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-gray-200
                  bg-white text-gray-500 hover:bg-gray-50 transition">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                Committee / Communications / Details
            </p>
            <h1 class="text-xl font-bold text-gray-900 truncate max-w-lg" title="<?= htmlspecialchars($subject) ?>">
                <?= htmlspecialchars(mb_strimwidth($subject, 0, 80, '…')) ?>
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

        <!-- Left: record info + events (2/3) ---------------------------------->
        <div class="space-y-6 xl:col-span-2">

            <!-- Communication summary ---------------------------------------->
            <div class="rounded-2xl border border-gray-200 bg-white p-6">
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-900">Communication Information</h2>
                    <?php $sBadge = $comm['status_badge_color'] ?? '#0D9488'; ?>
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                          style="background-color:<?= htmlspecialchars($sBadge) ?>1a;
                                 color:<?= htmlspecialchars($sBadge) ?>;">
                        <?= htmlspecialchars($comm['status'] ?? '') ?>
                    </span>
                </div>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Tracking Number</dt>
                        <dd class="mt-1 font-mono text-sm font-bold text-primary">
                            <a href="<?= BASE_URL ?>/committee/inbox/show?id=<?= $documentId ?>"
                               class="hover:underline">
                                <?= htmlspecialchars($comm['tracking_number'] ?? '') ?>
                            </a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Document Type</dt>
                        <dd class="mt-1">
                            <?php $dtBadge = $comm['document_type_badge_color'] ?? '#0D9488'; ?>
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                  style="background-color:<?= htmlspecialchars($dtBadge) ?>1a;
                                         color:<?= htmlspecialchars($dtBadge) ?>;">
                                <?= htmlspecialchars($comm['document_type_name'] ?? '') ?>
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Date Logged</dt>
                        <dd class="mt-1 text-sm text-gray-800">
                            <?= htmlspecialchars(
                                !empty($comm['date_logged'])
                                    ? date('F j, Y', strtotime($comm['date_logged']))
                                    : '—'
                            ) ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Logged On</dt>
                        <dd class="mt-1 text-sm text-gray-800">
                            <?= htmlspecialchars(
                                !empty($comm['created_at'])
                                    ? date('F j, Y g:i A', strtotime($comm['created_at']))
                                    : '—'
                            ) ?>
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Subject / Summary</dt>
                        <dd class="mt-1 text-sm text-gray-800 whitespace-pre-wrap">
                            <?= htmlspecialchars($comm['subject'] ?? '') ?>
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Sender / Originating Party</dt>
                        <dd class="mt-1 text-sm text-gray-800 whitespace-pre-wrap">
                            <?= htmlspecialchars($comm['sender_details'] ?? '') ?>
                        </dd>
                    </div>
                    <?php if (!empty($comm['notes'])): ?>
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Notes</dt>
                            <dd class="mt-1 text-sm text-gray-700 whitespace-pre-wrap italic">
                                <?= htmlspecialchars($comm['notes']) ?>
                            </dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($comm['source_type'])): ?>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Source Type</dt>
                            <dd class="mt-1 text-sm text-gray-800"><?= htmlspecialchars($comm['source_type']) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($comm['external_office_name'])): ?>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">External Office</dt>
                            <dd class="mt-1 text-sm text-gray-800">
                                <?= htmlspecialchars($comm['external_office_name']) ?>
                                <?php if (!empty($comm['external_office_abbr'])): ?>
                                    <span class="text-gray-400">(<?= htmlspecialchars($comm['external_office_abbr']) ?>)</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </div>

            <!-- Agenda information (if scheduled) ---------------------------->
            <?php if ($linkedAgenda): ?>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6">
                    <div class="mb-4 flex items-center gap-2">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-emerald-100">
                            <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0
                                         00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <p class="text-sm font-semibold text-emerald-900">Agenda Scheduled</p>
                    </div>
                    <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2 text-sm">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-emerald-600">Agenda No.</dt>
                            <dd class="mt-0.5 font-semibold text-emerald-900">
                                <?= htmlspecialchars($linkedAgenda['agenda_number'] ?? '—') ?>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-emerald-600">Type</dt>
                            <dd class="mt-0.5 text-emerald-800">
                                <?= htmlspecialchars($linkedAgenda['agenda_type'] ?? '—') ?>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-emerald-600">Date</dt>
                            <dd class="mt-0.5 text-emerald-800">
                                <?= htmlspecialchars(
                                    !empty($linkedAgenda['agenda_date'])
                                        ? date('F j, Y', strtotime($linkedAgenda['agenda_date']))
                                        : '—'
                                ) ?>
                                <?php if (!empty($linkedAgenda['agenda_time'])): ?>
                                    &mdash; <?= htmlspecialchars(date('g:i A', strtotime($linkedAgenda['agenda_time']))) ?>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-emerald-600">Venue</dt>
                            <dd class="mt-0.5 text-emerald-800">
                                <?= htmlspecialchars($linkedAgenda['venue'] ?? '—') ?>
                            </dd>
                        </div>
                        <?php if (!empty($linkedAgenda['committee_names'])): ?>
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-medium uppercase tracking-wide text-emerald-600">Committees</dt>
                                <dd class="mt-0.5 text-emerald-800">
                                    <?= htmlspecialchars($linkedAgenda['committee_names']) ?>
                                </dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                </div>
            <?php endif; ?>

            <!-- Hearing information (if recorded) ---------------------------->
            <?php if ($linkedHearing): ?>
                <?php
                $outcomeColors = [
                    'APPROVED'  => ['bg' => 'bg-green-100',  'text' => 'text-green-800',  'border' => 'border-green-200'],
                    'DEFERRED'  => ['bg' => 'bg-amber-100',  'text' => 'text-amber-800',  'border' => 'border-amber-200'],
                    'REMANDED'  => ['bg' => 'bg-red-100',    'text' => 'text-red-800',    'border' => 'border-red-200'],
                    'WITHDRAWN' => ['bg' => 'bg-gray-100',   'text' => 'text-gray-800',   'border' => 'border-gray-200'],
                ];
                $oc = $outcomeColors[$hearingOutcome] ?? $outcomeColors['DEFERRED'];
                ?>
                <div class="rounded-2xl border border-blue-200 bg-blue-50 p-6">
                    <div class="mb-4 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-blue-100">
                                <svg class="h-4 w-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2
                                             13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9
                                             M7 16h6M7 8h6v4H7V8z"/>
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-blue-900">Hearing Outcome Recorded</p>
                        </div>
                        <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold
                                     <?= $oc['bg'] ?> <?= $oc['text'] ?> <?= $oc['border'] ?>">
                            <?= htmlspecialchars(ucfirst(strtolower($hearingOutcome ?? ''))) ?>
                        </span>
                    </div>
                    <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2 text-sm">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-blue-600">Outcome</dt>
                            <dd class="mt-0.5 font-semibold text-blue-900">
                                <?= htmlspecialchars(ucfirst(strtolower($hearingOutcome ?? ''))) ?>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-blue-600">Recorded By</dt>
                            <dd class="mt-0.5 text-blue-800">
                                <?= htmlspecialchars($linkedHearing['performed_by_username'] ?? '—') ?>
                            </dd>
                        </div>
                        <?php if (!empty($linkedHearing['remarks'])): ?>
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-medium uppercase tracking-wide text-blue-600">Remarks</dt>
                                <dd class="mt-0.5 text-blue-800 whitespace-pre-wrap text-sm">
                                    <?= htmlspecialchars($linkedHearing['remarks']) ?>
                                </dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                </div>
            <?php endif; ?>

            <!-- Document Attachments ----------------------------------------->
            <?php if (!empty($attachments)): ?>
                <div class="rounded-2xl border border-gray-200 bg-white p-6">
                    <h2 class="mb-4 text-base font-semibold text-gray-900">
                        Document Attachments
                        <span class="ml-2 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">
                            <?= count($attachments) ?>
                        </span>
                    </h2>
                    <div class="space-y-2">
                        <?php foreach ($attachments as $att): ?>
                            <div class="flex items-center justify-between rounded-xl border border-gray-200
                                        bg-gray-50 px-3 py-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-blue-50">
                                        <svg class="h-3.5 w-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1
                                                     0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-medium text-gray-800">
                                            <?= htmlspecialchars($att['file_name'] ?? '') ?>
                                        </p>
                                        <p class="text-xs text-gray-400">
                                            <?= number_format(($att['file_size'] ?? 0) / 1024, 1) ?> KB
                                            <?php if (!empty($att['uploaded_by_username'])): ?>
                                                &middot; <?= htmlspecialchars($att['uploaded_by_username']) ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                <a href="<?= BASE_URL ?>/public/<?= htmlspecialchars($att['stored_path'] ?? '') ?>"
                                   target="_blank"
                                   class="ml-3 shrink-0 rounded-lg border border-gray-200 bg-white px-2.5 py-1
                                          text-xs font-medium text-gray-600 hover:bg-gray-50 transition">
                                    Download
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Workflow Event History ---------------------------------------->
            <?php if (!empty($events)): ?>
                <div class="rounded-2xl border border-gray-200 bg-white p-6">
                    <h2 class="mb-5 text-base font-semibold text-gray-900">Workflow History</h2>
                    <ol class="relative space-y-4">
                        <?php foreach (array_reverse($events) as $evt): ?>
                            <li class="flex gap-4">
                                <div class="relative z-10 flex h-7 w-7 shrink-0 items-center justify-center
                                            rounded-full bg-teal-100 mt-0.5">
                                    <div class="h-2 w-2 rounded-full bg-teal-500"></div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2 mb-0.5">
                                        <span class="text-xs font-semibold text-gray-700">
                                            <?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $evt['event_type'] ?? '')))) ?>
                                        </span>
                                        <time class="text-xs text-gray-400">
                                            <?= htmlspecialchars(
                                                !empty($evt['created_at'])
                                                    ? date('M j, Y g:i A', strtotime($evt['created_at']))
                                                    : ''
                                            ) ?>
                                        </time>
                                        <?php if (!empty($evt['performed_by_username'])): ?>
                                            <span class="text-xs text-gray-400">
                                                &middot; <?= htmlspecialchars($evt['performed_by_username']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($evt['remarks'])): ?>
                                        <p class="text-xs text-gray-500 whitespace-pre-wrap">
                                            <?= htmlspecialchars($evt['remarks']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            <?php endif; ?>

        </div><!-- /left column -->

        <!-- Right: workflow action panel (1/3) -------------------------------->
        <div class="xl:col-span-1">
            <div class="sticky top-6 space-y-4">

                <!-- Workflow progress stepper --------------------------------->
                <div class="rounded-2xl border border-gray-200 bg-white p-5">
                    <p class="mb-4 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        Workflow Progress
                    </p>
                    <ol class="space-y-3">
                        <?php
                        $steps = [
                            ['label' => 'Communication Logged',  'done' => true],
                            ['label' => 'Agenda Scheduled',      'done' => $hasAgenda],
                            ['label' => 'Hearing Outcome',       'done' => $hasHearing],
                            ['label' => 'Committee Report',      'done' => $hasReport],
                            ['label' => 'Returned to Plenary',   'done' => !empty($linkedReport['returned_to_plenary_at'])],
                        ];
                        foreach ($steps as $i => $step):
                            $isDone = $step['done'];
                        ?>
                            <li class="flex items-center gap-3">
                                <?php if ($isDone): ?>
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center
                                                 rounded-full bg-teal-500 text-white">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                                  d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </span>
                                <?php else: ?>
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center
                                                 rounded-full border-2 border-gray-300 text-xs font-bold
                                                 text-gray-400">
                                        <?= $i + 1 ?>
                                    </span>
                                <?php endif; ?>
                                <span class="text-sm <?= $isDone ? 'text-gray-800 font-medium' : 'text-gray-400' ?>">
                                    <?= htmlspecialchars($step['label']) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>

                <!-- Next-step action card ------------------------------------->
                <?php if (!$hasAgenda): ?>
                    <!-- Step 2: Schedule Agenda -->
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-emerald-600">
                            Next Step
                        </p>
                        <p class="mb-4 text-sm text-emerald-800">
                            Schedule the committee hearing agenda for this communication.
                        </p>
                        <a href="<?= BASE_URL ?>/committee/communications/agenda?id=<?= $commId ?>"
                           class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600
                                  px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 transition">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0
                                         00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            Schedule Agenda
                        </a>
                    </div>

                <?php elseif ($hasAgenda && !$hasHearing): ?>
                    <!-- Step 3: Record Hearing -->
                    <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-blue-600">
                            Next Step
                        </p>
                        <p class="mb-4 text-sm text-blue-800">
                            Agenda is scheduled. Record the committee hearing outcome.
                        </p>
                        <a href="<?= BASE_URL ?>/committee/communications/hearing?id=<?= $commId ?>"
                           class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600
                                  px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 transition">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2
                                         13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9
                                         M7 16h6M7 8h6v4H7V8z"/>
                            </svg>
                            Record Hearing Outcome
                        </a>
                    </div>

                <?php elseif ($hasHearing && $hearingApproved && !$hasReport): ?>
                    <!-- Step 4: Create Committee Report -->
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-emerald-600">
                            Next Step
                        </p>
                        <p class="mb-4 text-sm text-emerald-800">
                            Hearing approved. Create the Committee Report.
                        </p>
                        <a href="<?= BASE_URL ?>/committee/communications/report?id=<?= $commId ?>"
                           class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600
                                  px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 transition">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0
                                         00.707-.293l5.414-5.414A1 1 0 0121 4.586V19a2 2 0 01-2 2z"/>
                            </svg>
                            Create Committee Report
                        </a>
                    </div>

                <?php elseif ($hasHearing && !$hearingApproved): ?>
                    <!-- Hearing not approved — terminal state -->
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">
                            Workflow Closed
                        </p>
                        <p class="text-sm text-gray-600">
                            Hearing outcome was
                            <span class="font-semibold">
                                <?= htmlspecialchars(ucfirst(strtolower($hearingOutcome ?? ''))) ?>
                            </span>.
                            No Committee Report is required.
                        </p>
                    </div>

                <?php elseif ($hasReport): ?>
                    <!-- Step 5: Report exists — show link + Return to Plenary -->
                    <div class="rounded-2xl border border-green-200 bg-green-50 p-5">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-green-600">
                            Committee Report Created
                        </p>
                        <p class="mb-1 text-sm font-semibold text-green-800">
                            #<?= htmlspecialchars($linkedReport['report_number'] ?? '—') ?>
                        </p>
                        <?php if (!empty($linkedReport['returned_to_plenary_at'])): ?>
                            <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-violet-100
                                         px-2 py-0.5 text-xs font-medium text-violet-700">
                                Returned to Plenary
                            </span>
                        <?php else: ?>
                            <p class="mt-2 mb-3 text-xs text-green-700">
                                Use the Reports page to return this report to Plenary.
                            </p>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>/committee/reports/show?id=<?= (int) ($linkedReport['id'] ?? 0) ?>"
                           class="mt-2 inline-flex w-full items-center justify-center gap-2 rounded-xl
                                  border border-green-300 bg-white px-4 py-2 text-sm font-semibold
                                  text-green-700 hover:bg-green-50 transition">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943
                                         9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            View Report
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Back to communications list --------------------------------->
                <a href="<?= BASE_URL ?>/committee/communications"
                   class="inline-flex w-full items-center justify-center gap-2 rounded-xl border
                          border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-600
                          hover:bg-gray-50 transition">
                    ← Back to Communications
                </a>

            </div>
        </div><!-- /right column -->

    </div><!-- /grid -->

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

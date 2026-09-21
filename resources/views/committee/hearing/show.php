<?php
/**
 * Committee — Hearing Detail + Outcome Form
 *
 * Variables supplied by CommitteeHearingController::show():
 *   $document            array   Full document row with type/status joins
 *   $agenda              array   Latest agenda row
 *   $agendaCommittees    array   [{id, name}] committees from agenda_committees
 *   $agendaChairpersons  array   [{sp_member_id, first_name, …}] from agenda_chairpersons
 *   $attachments         array   document_attachments rows
 *   $events              array   document_events rows
 *   $existingHearing     array|false  committee_hearings row (if already recorded)
 *   $existingReport      array|null   committee_reports row if report created for approved hearing
 *   $isOnGoing           bool    true when document is still in On Going stage
 *   $success             string|null
 *   $error               string|null
 *   $errors              array
 */

$document           = $document           ?? [];
$agenda             = $agenda             ?? [];
$agendaCommittees   = $agendaCommittees   ?? [];
$agendaChairpersons = $agendaChairpersons ?? [];
$attachments        = $attachments        ?? [];
$events             = $events             ?? [];
$existingHearing    = $existingHearing    ?? false;
$existingReport     = $existingReport     ?? null;
$isOnGoing          = $isOnGoing          ?? true;
$success            = $success            ?? null;
$error              = $error              ?? null;
$errors             = $errors             ?? [];

$documentId  = (int) ($document['id'] ?? 0);
$agendaId    = (int) ($agenda['id']   ?? 0);
$alreadyDone = ($existingHearing !== false);
// Show outcome form only when document is still On Going AND no hearing recorded yet
$canRecordOutcome = $isOnGoing && !$alreadyDone;

// Outcome labels and colours
$outcomeConfig = [
    'APPROVED' => [
        'label'      => 'Approved',
        'desc'       => 'Document approved. A Committee Report will be required.',
        'ring'       => 'ring-emerald-500',
        'bg'         => 'bg-emerald-50',
        'text'       => 'text-emerald-800',
        'icon_color' => 'text-emerald-500',
        'badge_bg'   => 'bg-emerald-100',
        'badge_text' => 'text-emerald-800',
        'icon'       => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>',
    ],
    'DEFERRED' => [
        'label'      => 'Deferred',
        'desc'       => 'Document deferred for further review at a later session.',
        'ring'       => 'ring-amber-500',
        'bg'         => 'bg-amber-50',
        'text'       => 'text-amber-800',
        'icon_color' => 'text-amber-500',
        'badge_bg'   => 'bg-amber-100',
        'badge_text' => 'text-amber-800',
        'icon'       => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
    ],
    'REMANDED' => [
        'label'      => 'Remanded',
        'desc'       => 'Document remanded back to the originating body.',
        'ring'       => 'ring-red-500',
        'bg'         => 'bg-red-50',
        'text'       => 'text-red-800',
        'icon_color' => 'text-red-500',
        'badge_bg'   => 'bg-red-100',
        'badge_text' => 'text-red-800',
        'icon'       => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>',
    ],
    'WITHDRAWN' => [
        'label'      => 'Withdrawn',
        'desc'       => 'Document withdrawn from the legislative process.',
        'ring'       => 'ring-gray-500',
        'bg'         => 'bg-gray-50',
        'text'       => 'text-gray-800',
        'icon_color' => 'text-gray-500',
        'badge_bg'   => 'bg-gray-100',
        'badge_text' => 'text-gray-700',
        'icon'       => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>',
    ],
];

ob_start();
?>

<div class="space-y-6">

    <!-- Page header -->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-blue-700 via-blue-600 to-indigo-700 shadow-md">
        <div class="relative px-6 py-7 sm:px-8">
            <div class="relative z-10">
                <p class="text-sm font-medium text-blue-100">COMMITTEE / HEARING / RECORD OUTCOME</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    Committee Hearing
                </h1>
                <p class="mt-1 font-mono text-sm text-blue-200">
                    <?= htmlspecialchars($document['tracking_number'] ?? '—') ?>
                </p>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-56 w-56 rounded-full bg-white/10"></div>
        </div>
    </section>

    <!-- Breadcrumb -->
    <nav class="flex items-center gap-2 text-sm text-gray-500">
        <a href="<?= BASE_URL ?>/committee/hearing" class="hover:text-primary transition">Committee Hearing</a>
        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="font-medium text-gray-700">Record Outcome</span>
    </nav>

    <!-- Flash messages -->
    <?php if ($success): ?>
        <div class="flex items-start gap-3 rounded-2xl border border-green-200 bg-green-50 p-4">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <p class="text-sm font-medium text-green-800"><?= htmlspecialchars($success) ?></p>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            <div class="text-sm text-red-800">
                <p class="font-semibold"><?= htmlspecialchars($error) ?></p>
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

    <!-- Already recorded banner -->
    <?php if ($alreadyDone): ?>
        <?php $cfg = $outcomeConfig[$existingHearing['outcome']] ?? null; ?>
        <div class="flex items-start gap-3 rounded-2xl border border-blue-200 bg-blue-50 p-4">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div class="text-sm text-blue-800">
                <p class="font-semibold">Hearing outcome already recorded</p>
                <p class="mt-0.5">
                    Outcome:
                    <?php if ($cfg): ?>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold
                                     <?= $cfg['badge_bg'] ?> <?= $cfg['badge_text'] ?>">
                            <?= htmlspecialchars($cfg['label']) ?>
                        </span>
                    <?php else: ?>
                        <strong><?= htmlspecialchars($existingHearing['outcome']) ?></strong>
                    <?php endif; ?>
                    &nbsp;·&nbsp;
                    Recorded by <?= htmlspecialchars($existingHearing['performed_by_username'] ?? '—') ?>
                    on <?= htmlspecialchars(date('M j, Y g:i A', strtotime($existingHearing['performed_at']))) ?>
                </p>
                <?php if ($existingHearing['outcome'] === 'APPROVED'): ?>
                    <?php if ($existingReport): ?>
                        <a href="<?= BASE_URL ?>/committee/hearing/report/show?id=<?= (int) $existingReport['id'] ?>"
                           class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 underline hover:text-emerald-900">
                            View Committee Report #<?= htmlspecialchars($existingReport['report_number']) ?> →
                        </a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/committee/hearing/report?document_id=<?= $documentId ?>&hearing_id=<?= (int) $existingHearing['id'] ?>"
                           class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-blue-700 underline hover:text-blue-900">
                            Create Committee Report →
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

        <!-- Left column: document + agenda summary -->
        <div class="xl:col-span-1 space-y-4">

            <!-- Document card -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Document</h2>
                <p class="font-mono font-bold text-blue-700 text-sm">
                    <?= htmlspecialchars($document['tracking_number'] ?? '—') ?>
                </p>
                <?php if (!empty($document['document_type_name'])): ?>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold text-white"
                          style="background-color:<?= htmlspecialchars($document['document_type_badge_color'] ?? '#6B7280') ?>">
                        <?= htmlspecialchars($document['document_type_name']) ?>
                    </span>
                <?php endif; ?>
                <p class="text-sm text-gray-700 line-clamp-4">
                    <?= htmlspecialchars($document['subject_matter'] ?? '—') ?>
                </p>
                <div class="flex items-center gap-2 pt-1">
                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5
                                 text-xs font-semibold text-amber-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                        <?= htmlspecialchars($document['status'] ?? 'On Going') ?>
                    </span>
                </div>
            </div>

            <!-- Agenda card -->
            <div class="rounded-2xl border border-blue-100 bg-blue-50 p-5 space-y-3">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-blue-500">Agenda</h2>
                <?php if (!empty($agenda['agenda_number'])): ?>
                    <p class="text-xs font-semibold text-blue-800">
                        #<?= htmlspecialchars($agenda['agenda_number']) ?>
                        <?= !empty($agenda['agenda_type']) ? '— ' . htmlspecialchars($agenda['agenda_type']) : '' ?>
                    </p>
                <?php endif; ?>
                <div class="space-y-1.5 text-xs text-blue-800">
                    <div class="flex items-center gap-2">
                        <svg class="h-3.5 w-3.5 shrink-0 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span>
                            <?= !empty($agenda['agenda_date'])
                                ? htmlspecialchars(date('F j, Y', strtotime($agenda['agenda_date'])))
                                : '—' ?>
                            <?= !empty($agenda['agenda_time'])
                                ? 'at ' . htmlspecialchars(date('g:i A', strtotime($agenda['agenda_time'])))
                                : '' ?>
                        </span>
                    </div>
                    <?php if (!empty($agenda['venue'])): ?>
                        <div class="flex items-center gap-2">
                            <svg class="h-3.5 w-3.5 shrink-0 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            </svg>
                            <span><?= htmlspecialchars($agenda['venue']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($agendaCommittees)): ?>
                    <div class="pt-1">
                        <p class="text-xs font-semibold text-blue-600 mb-1">Committee/ies in Charge</p>
                        <ul class="space-y-0.5">
                            <?php foreach ($agendaCommittees as $comm): ?>
                                <li class="text-xs text-blue-800">• <?= htmlspecialchars($comm['name']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($agendaChairpersons)): ?>
                    <div class="pt-1">
                        <p class="text-xs font-semibold text-blue-600 mb-1">Chairperson(s)</p>
                        <ul class="space-y-0.5">
                            <?php foreach ($agendaChairpersons as $sp): ?>
                                <?php $name = trim(implode(' ', array_filter([
                                    $sp['first_name'], $sp['middle_name'] ?? null,
                                    $sp['last_name'], $sp['suffix'] ?? null,
                                ]))); ?>
                                <li class="text-xs text-blue-800">• <?= htmlspecialchars($name) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Attachments -->
            <?php if (!empty($attachments)): ?>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                        Attachments (<?= count($attachments) ?>)
                    </h2>
                    <ul class="space-y-2">
                        <?php foreach ($attachments as $att): ?>
                            <li class="flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-medium text-gray-700">
                                        <?= htmlspecialchars($att['file_name']) ?>
                                    </p>
                                    <p class="text-xs text-gray-400">
                                        <?= number_format((int)($att['file_size'] ?? 0) / 1024, 1) ?> KB
                                    </p>
                                </div>
                                <a href="<?= BASE_URL ?>/public/<?= htmlspecialchars($att['stored_path']) ?>"
                                   target="_blank" rel="noopener"
                                   class="shrink-0 text-xs text-blue-600 hover:underline">View</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right column: outcome form OR already-done state -->
        <div class="xl:col-span-2 space-y-5">

            <?php if ($canRecordOutcome): ?>
            <!-- ── Outcome selection form ───────────────────────────────────── -->
            <div class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-base font-semibold text-gray-900">Record Hearing Outcome</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Select the outcome of this committee hearing. This action requires confirmation before submission.
                </p>

                <!-- Outcome cards (radio buttons) -->
                <div id="outcomeError" class="mt-4 hidden rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-medium text-red-700">
                    Please select a hearing outcome before submitting.
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2" id="outcomeCards">
                    <?php foreach ($outcomeConfig as $value => $cfg): ?>
                        <label class="outcome-card group relative flex cursor-pointer flex-col gap-2 rounded-2xl
                                      border-2 border-gray-200 p-4 transition hover:border-gray-300
                                      has-[:checked]:border-2 has-[:checked]:<?= $cfg['ring'] ?>
                                      has-[:checked]:<?= $cfg['bg'] ?>"
                               data-outcome="<?= $value ?>">
                            <input type="radio" name="outcome_select" value="<?= $value ?>"
                                   class="sr-only outcome-radio">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <svg class="h-5 w-5 <?= $cfg['icon_color'] ?>" fill="none"
                                         stroke="currentColor" viewBox="0 0 24 24">
                                        <?= $cfg['icon'] ?>
                                    </svg>
                                    <span class="text-sm font-semibold <?= $cfg['text'] ?>">
                                        <?= $cfg['label'] ?>
                                    </span>
                                </div>
                                <!-- Checkmark indicator -->
                                <div class="outcome-check hidden h-5 w-5 items-center justify-center
                                            rounded-full <?= str_replace('text-', 'bg-', $cfg['icon_color']) ?>">
                                    <svg class="h-3 w-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 leading-relaxed"><?= $cfg['desc'] ?></p>
                        </label>
                    <?php endforeach; ?>
                </div>

                <!-- Remarks -->
                <div class="mt-5">
                    <label for="hearingRemarks" class="block text-sm font-semibold text-gray-700 mb-1">
                        Remarks
                        <span class="font-normal text-gray-400">(optional)</span>
                    </label>
                    <textarea id="hearingRemarks" rows="3"
                              class="block w-full rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-800
                                     focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                              placeholder="Additional notes about this hearing outcome…"></textarea>
                </div>

                <!-- Submit -->
                <div class="mt-6 flex items-center justify-between gap-3">
                    <a href="<?= BASE_URL ?>/committee/hearing"
                       class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white
                              px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Back
                    </a>
                    <button type="button" id="openConfirmBtn"
                            class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5
                                   text-sm font-semibold text-white hover:bg-blue-700 transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Submit Outcome
                    </button>
                </div>
            </div>

            <?php else: ?>
            <!-- ── Already recorded / not actionable: show recorded outcome summary ─────────── -->
            <?php $cfg = $outcomeConfig[$existingHearing['outcome'] ?? ''] ?? null; ?>
            <div class="rounded-2xl border border-gray-200 bg-white p-6 space-y-4">
                <h2 class="text-base font-semibold text-gray-900">Recorded Outcome</h2>
                <?php if ($alreadyDone): ?>
                <div class="flex items-center gap-3">
                    <?php if ($cfg): ?>
                        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1
                                     text-sm font-semibold <?= $cfg['badge_bg'] ?> <?= $cfg['badge_text'] ?>">
                            <svg class="h-4 w-4 <?= $cfg['icon_color'] ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <?= $cfg['icon'] ?>
                            </svg>
                            <?= htmlspecialchars($cfg['label']) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($existingHearing['remarks'])): ?>
                    <p class="text-sm text-gray-600">
                        <span class="font-medium">Remarks:</span>
                        <?= htmlspecialchars($existingHearing['remarks']) ?>
                    </p>
                <?php endif; ?>
                <p class="text-xs text-gray-400">
                    Recorded by <?= htmlspecialchars($existingHearing['performed_by_username'] ?? '—') ?>
                    on <?= htmlspecialchars(date('F j, Y \a\t g:i A', strtotime($existingHearing['performed_at']))) ?>
                </p>

                <?php if ($existingHearing['outcome'] === 'APPROVED'): ?>
                    <?php if ($existingReport): ?>
                        <a href="<?= BASE_URL ?>/committee/hearing/report/show?id=<?= (int) $existingReport['id'] ?>"
                           class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5
                                  text-sm font-semibold text-white hover:bg-emerald-700 transition">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            View Committee Report #<?= htmlspecialchars($existingReport['report_number']) ?>
                        </a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/committee/hearing/report?document_id=<?= $documentId ?>&hearing_id=<?= (int) $existingHearing['id'] ?>"
                           class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5
                                  text-sm font-semibold text-white hover:bg-emerald-700 transition">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Create Committee Report
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
                <?php else: ?>
                    <!-- No outcome recorded and not On Going — view only -->
                    <p class="text-sm text-gray-500">
                        This document is currently in the
                        <span class="font-semibold"><?= htmlspecialchars($document['status'] ?? '—') ?></span>
                        stage. No hearing outcome has been recorded yet.
                    </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Workflow history -->
            <?php if (!empty($events)): ?>
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="mb-4 text-xs font-semibold uppercase tracking-wide text-gray-400">
                    Workflow History
                </h2>
                <ol class="relative border-l border-gray-200 space-y-5 pl-5">
                    <?php foreach ($events as $evt): ?>
                        <li class="relative">
                            <span class="absolute -left-[1.375rem] flex h-6 w-6 items-center justify-center
                                         rounded-full border border-gray-200 bg-white ring-4 ring-white">
                                <span class="h-2 w-2 rounded-full bg-blue-400"></span>
                            </span>
                            <div class="text-xs">
                                <p class="font-semibold text-gray-800">
                                    <?= htmlspecialchars(str_replace('_', ' ', $evt['event_type'])) ?>
                                    <span class="font-normal text-gray-400 ml-1">
                                        · <?= htmlspecialchars($evt['performed_by_username'] ?? '—') ?>
                                    </span>
                                </p>
                                <?php if (!empty($evt['from_status_name']) || !empty($evt['to_status_name'])): ?>
                                    <p class="text-gray-500 mt-0.5">
                                        <?= htmlspecialchars($evt['from_status_name'] ?? '—') ?>
                                        →
                                        <?= htmlspecialchars($evt['to_status_name'] ?? '—') ?>
                                    </p>
                                <?php endif; ?>
                                <?php if (!empty($evt['remarks'])): ?>
                                    <p class="mt-0.5 text-gray-500 italic">
                                        <?= htmlspecialchars(mb_substr($evt['remarks'], 0, 120)) ?>
                                    </p>
                                <?php endif; ?>
                                <p class="mt-0.5 text-gray-400">
                                    <?= htmlspecialchars(date('M j, Y g:i A', strtotime($evt['created_at']))) ?>
                                </p>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- ── Confirmation Modal ─────────────────────────────────────────────────── -->
<div id="confirmModal"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4"
     role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl">
        <div class="p-6">
            <div class="flex items-start gap-3">
                <div class="shrink-0 flex h-10 w-10 items-center justify-center rounded-full bg-blue-100">
                    <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 id="modalTitle" class="text-base font-semibold text-gray-900">
                        Confirm Hearing Outcome
                    </h3>
                    <p class="mt-1 text-sm text-gray-600">
                        You are about to record the hearing outcome as
                        <strong id="modalOutcomeLabel" class="text-gray-900">—</strong>.
                        This action will update the document status and cannot be reversed.
                    </p>
                </div>
            </div>

            <!-- Hidden real form (submitted on confirm) -->
            <form id="hearingForm" method="POST" action="<?= BASE_URL ?>/committee/hearing/store" class="mt-5">
                <input type="hidden" name="document_id" value="<?= $documentId ?>">
                <input type="hidden" name="agenda_id"   value="<?= $agendaId ?>">
                <input type="hidden" name="outcome"     id="modalOutcomeInput">
                <input type="hidden" name="remarks"     id="modalRemarksInput">

                <div class="flex items-center justify-end gap-3">
                    <button type="button" id="cancelConfirmBtn"
                            class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm
                                   font-medium text-gray-600 hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button type="submit" id="confirmSubmitBtn"
                            class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2
                                   text-sm font-semibold text-white hover:bg-blue-700 transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M5 13l4 4L19 7"/>
                        </svg>
                        Confirm &amp; Submit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const openBtn       = document.getElementById('openConfirmBtn');
    const cancelBtn     = document.getElementById('cancelConfirmBtn');
    const modal         = document.getElementById('confirmModal');
    const modalLabel    = document.getElementById('modalOutcomeLabel');
    const modalOutcome  = document.getElementById('modalOutcomeInput');
    const modalRemarks  = document.getElementById('modalRemarksInput');
    const remarksInput  = document.getElementById('hearingRemarks');
    const outcomeError  = document.getElementById('outcomeError');
    const confirmBtn    = document.getElementById('confirmSubmitBtn');

    const outcomeLabels = {
        'APPROVED':  'Approved',
        'DEFERRED':  'Deferred',
        'REMANDED':  'Remanded',
        'WITHDRAWN': 'Withdrawn',
    };

    // Track selected outcome via card clicks
    let selectedOutcome = null;

    document.querySelectorAll('.outcome-card').forEach(function (card) {
        card.addEventListener('click', function () {
            selectedOutcome = this.dataset.outcome;

            // Update radio
            this.querySelector('.outcome-radio').checked = true;

            // Visual: show checkmark on selected, hide on others
            document.querySelectorAll('.outcome-card').forEach(function (c) {
                const check = c.querySelector('.outcome-check');
                if (c === card) {
                    check.classList.remove('hidden');
                    check.classList.add('flex');
                } else {
                    check.classList.add('hidden');
                    check.classList.remove('flex');
                }
            });

            // Clear error
            if (outcomeError) outcomeError.classList.add('hidden');
        });
    });

    // Open modal
    if (openBtn) {
        openBtn.addEventListener('click', function () {
            if (!selectedOutcome) {
                if (outcomeError) outcomeError.classList.remove('hidden');
                document.getElementById('outcomeCards').scrollIntoView({ behavior: 'smooth', block: 'start' });
                return;
            }
            // Populate modal
            modalLabel.textContent  = outcomeLabels[selectedOutcome] || selectedOutcome;
            modalOutcome.value      = selectedOutcome;
            modalRemarks.value      = remarksInput ? remarksInput.value.trim() : '';

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        });
    }

    // Close modal
    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

    // Close on backdrop click
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });

    // Close on Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });

    // Prevent double-submit
    if (confirmBtn) {
        document.getElementById('hearingForm').addEventListener('submit', function () {
            confirmBtn.disabled = true;
            confirmBtn.innerHTML =
                '<svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">' +
                '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
                '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>' +
                '</svg><span class="ml-1">Submitting…</span>';
        });
    }
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

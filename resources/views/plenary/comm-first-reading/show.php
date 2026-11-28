<?php
/**
 * Plenary Communication First Reading — detail & decision form
 *
 * Variables supplied by PlenaryReadingController::commFirstReadingShow():
 *   $document        array
 *   $plenaryDetails  array|null
 *   $committees      array   all active committees for select
 *   $events         array
 *   $assignments    array
 *   $documentAttachments array
 *   $existingReading array|null  (should be null; gate enforced by controller)
 *   $canAct          bool
 *   $success         string|null
 *   $error           string|null
 *   $errors          array
 *   $old             array
 */

$document       = $document       ?? [];
$plenaryDetails = $plenaryDetails ?? null;
$committees     = $committees     ?? [];
$events         = $events         ?? [];
$success        = $success        ?? null;
$error          = $error          ?? null;
$errors         = $errors         ?? [];
$old            = $old            ?? [];
$canAct         = $canAct         ?? false;
$assignments    = $assignments    ?? [];
$documentAttachments = $documentAttachments ?? [];

$documentId     = (int) ($document['id']               ?? 0);
$trackingNumber = $document['tracking_number']          ?? '';
$subjectMatter  = $document['subject_matter']           ?? '';
$targetDate     = $plenaryDetails['target_first_reading_date'] ?? '';

ob_start();
?>

<div class="detail-page space-y-6">
    <!-- Back + title -->
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative z-10 flex items-center gap-3 px-5 py-6 sm:px-8 sm:py-7">
            <a href="<?= BASE_URL ?>/plenary/first-reading"
               class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 text-white
                      transition hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
               aria-label="Back to First Reading">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-blue-100">DOCUMENT DETAILS</p>
                <h1 class="mt-1 break-words text-2xl font-bold tracking-tight text-white sm:text-3xl"><?= htmlspecialchars($trackingNumber) ?></h1>
                <div class="mt-3 flex flex-wrap gap-2">
                    <?php if (!empty($resubmissionReference)): ?>
                        <span class="inline-flex rounded-full border border-white/20 bg-white/10 px-2.5 py-1 text-xs font-semibold text-white">Resubmission</span>
                    <?php endif; ?>
                    <?php if (!empty($relatedResubmissions)): ?>
                        <span class="inline-flex rounded-full border border-white/20 bg-white/10 px-2.5 py-1 text-xs font-semibold text-white">Submission history · <?= count($relatedResubmissions) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($mfrLink)): ?>
                        <span class="inline-flex rounded-full border border-white/20 bg-white/10 px-2.5 py-1 text-xs font-semibold text-white">Linked case</span>
                    <?php endif; ?>
                    <?php if (!empty($relatedMotions)): ?>
                        <span class="inline-flex rounded-full border border-white/20 bg-white/10 px-2.5 py-1 text-xs font-semibold text-white">Linked motions · <?= count($relatedMotions) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
    </section>

    <?php if (!empty($success ?? null)): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= htmlspecialchars((string) $success) ?></div>
    <?php endif; ?>
    <?php if (!empty($error ?? null)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= htmlspecialchars((string) $error) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc list-inside space-y-1"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <?php
    $resubmissionTopMargin = 'mt-0';
    $resubmissionDetailRoute = 'plenary/inbox';
    $mfrDetailRoute = 'plenary/inbox';
    require __DIR__ . '/../../partials/resubmission-relationship.php';
    unset($resubmissionTopMargin);
    $mfrRelationshipTopMargin = 'mt-0';
    require __DIR__ . '/../../partials/mfr-relationship.php';
    unset($mfrRelationshipTopMargin);
    ?>

    <div class="flex flex-col gap-6">

        <!-- Document information -->
        <div class="order-2 space-y-4">
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900">Document Information</h2>
                <div class="mt-4 grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Tracking Number</p>
                    <p class="mt-1 text-sm font-mono font-semibold text-gray-900"><?= htmlspecialchars($trackingNumber) ?></p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Subject Matter</p>
                    <p class="mt-1 whitespace-pre-wrap text-sm leading-6 text-gray-900"><?= htmlspecialchars($subjectMatter) ?></p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Date &amp; Time Received</p>
                    <p class="mt-1 text-sm text-gray-900">
                        <?= !empty($document['date_received']) ? htmlspecialchars(date('F j, Y', strtotime($document['date_received']))) : '—' ?>
                        · <?= !empty($document['time_received']) ? htmlspecialchars(date('g:i A', strtotime($document['time_received']))) : '—' ?>
                    </p>
                </div>
                <?php if (!empty($document['document_type_name'])): ?>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Document Type</p>
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                          style="background-color: <?= htmlspecialchars($document['document_type_badge_color'] ?? '#2563EB') ?>1a;
                                 color: <?= htmlspecialchars($document['document_type_badge_color'] ?? '#2563EB') ?>;">
                        <?= htmlspecialchars($document['document_type_name']) ?>
                    </span>
                </div>
                <?php endif; ?>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Current Status</p>
                    <?php $communicationStatusColor = $document['status_badge_color'] ?? '#6B7280'; ?>
                    <span class="mt-1 inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                          style="background-color: <?= htmlspecialchars($communicationStatusColor) ?>1a; color: <?= htmlspecialchars($communicationStatusColor) ?>;">
                        <span class="h-1.5 w-1.5 rounded-full" style="background-color: <?= htmlspecialchars($communicationStatusColor) ?>;"></span>
                        <?= htmlspecialchars($document['status'] ?? $document['status_name'] ?? 'Awaiting First Reading') ?>
                    </span>
                </div>
                <?php if (!empty($document['current_phase'])): ?>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Current Phase</p>
                        <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $document['current_phase'])))) ?></p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($targetDate)): ?>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Target First Reading Date</p>
                        <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars(date('F j, Y', strtotime($targetDate))) ?></p>
                    </div>
                <?php endif; ?>
                </div>
            </section>

            <?php require __DIR__ . '/../../partials/source-information.php'; ?>

            <div class="space-y-6">
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h2 class="text-base font-semibold text-gray-900">Attachments</h2>
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">
                            <?= count($documentAttachments) ?> file<?= count($documentAttachments) === 1 ? '' : 's' ?>
                        </span>
                    </div>
                    <?php if ($documentAttachments === []): ?>
                        <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-8 text-center">
                            <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-white text-gray-400 shadow-sm">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <p class="mt-3 text-sm font-medium text-gray-700">No attachments</p>
                            <p class="mt-1 text-xs text-gray-500">Uploaded documents will appear here.</p>
                        </div>
                    <?php else: ?>
                        <ul class="space-y-2">
                            <?php foreach ($documentAttachments as $attachment): ?>
                                <?php
                                $attachmentName = (string) ($attachment['file_name'] ?? 'Attachment');
                                $attachmentUrl = BASE_URL . '/public/' . ltrim((string) ($attachment['stored_path'] ?? ''), '/\\');
                                ?>
                                <li class="flex flex-col gap-3 rounded-xl border border-gray-100 bg-gray-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex min-w-0 items-start gap-3 sm:items-center">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-primary">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                            </svg>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="break-words text-sm font-medium text-gray-800 sm:truncate"><?= htmlspecialchars($attachmentName, ENT_QUOTES, 'UTF-8') ?></p>
                                            <p class="mt-0.5 break-words text-xs leading-relaxed text-gray-500">
                                                <?= number_format((int) ($attachment['file_size'] ?? 0) / 1024, 1) ?> KB
                                                <?php if (!empty($attachment['phase'])): ?>
                                                    &middot; <?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', (string) $attachment['phase']))), ENT_QUOTES, 'UTF-8') ?>
                                                <?php endif; ?>
                                                <?php if (!empty($attachment['created_at'])): ?>
                                                    &middot; <?= htmlspecialchars(date('M j, Y g:i A', strtotime($attachment['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                    <?php if (!empty($attachment['stored_path'])): ?>
                                        <div class="grid grid-cols-2 gap-2 sm:ml-4 sm:flex sm:shrink-0 sm:items-center">
                                            <a href="<?= htmlspecialchars($attachmentUrl, ENT_QUOTES, 'UTF-8') ?>"
                                               target="_blank" rel="noopener"
                                               data-attachment-preview
                                               data-preview-url="<?= htmlspecialchars($attachmentUrl, ENT_QUOTES, 'UTF-8') ?>"
                                               data-preview-name="<?= htmlspecialchars($attachmentName, ENT_QUOTES, 'UTF-8') ?>"
                                               data-download-url="<?= htmlspecialchars($attachmentUrl, ENT_QUOTES, 'UTF-8') ?>"
                                               class="inline-flex justify-center rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-white transition hover:bg-primary-hover sm:py-1.5">Preview</a>
                                            <a href="<?= htmlspecialchars($attachmentUrl, ENT_QUOTES, 'UTF-8') ?>" download
                                               class="inline-flex justify-center rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-600 transition hover:bg-gray-50 sm:py-1.5">Download</a>
                                        </div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <h2 class="mb-4 text-base font-semibold text-gray-900">Action History</h2>
                    <?php if (empty($events)): ?>
                        <p class="text-sm text-gray-400">No action history recorded.</p>
                    <?php else: ?>
                        <ol class="relative ml-3 space-y-5 border-l border-gray-200">
                            <?php foreach ($events as $ev): ?>
                                <?php
                                $eventType = (string) ($ev['event_type'] ?? 'OTHER');
                                $eventLabel = ucwords(strtolower(str_replace('_', ' ', $eventType)));
                                ?>
                                <li class="relative ml-6">
                                    <span class="absolute -left-[31px] flex h-6 w-6 items-center justify-center rounded-full bg-indigo-600 text-white ring-4 ring-white" aria-hidden="true">
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </span>
                                    <p class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($eventLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="text-xs text-gray-500">
                                        <?= htmlspecialchars($ev['phase'] ?? 'Plenary', ENT_QUOTES, 'UTF-8') ?>
                                        <?php if (!empty($ev['performed_by_username'])): ?>
                                            &middot; by <?= htmlspecialchars($ev['performed_by_username'], ENT_QUOTES, 'UTF-8') ?>
                                        <?php endif; ?>
                                        &middot; <?= !empty($ev['created_at']) ? htmlspecialchars(date('M j, Y g:i A', strtotime($ev['created_at'])), ENT_QUOTES, 'UTF-8') : 'Date unavailable' ?>
                                    </p>
                                    <?php if (!empty($ev['to_status_name'])): ?>
                                        <p class="mt-1 text-xs text-gray-500">Status: <?= htmlspecialchars($ev['to_status_name'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($ev['remarks'])): ?>
                                        <p class="mt-1 whitespace-pre-line text-xs italic text-gray-500"><?= htmlspecialchars($ev['remarks'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </section>

                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <h2 class="mb-4 text-base font-semibold text-gray-900">Assignment History</h2>
                    <?php if (empty($assignments)): ?>
                        <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-8 text-center">
                            <p class="text-sm font-medium text-gray-700">No assignments yet</p>
                            <p class="mt-1 text-xs text-gray-500">Assignment records will appear here.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3 sm:hidden">
                            <?php foreach ($assignments as $assignment): ?>
                                <?php
                                $decision = (string) ($assignment['decision'] ?? '');
                                $decisionClass = match ($decision) {
                                    'COMPLETED', 'NOTED' => 'bg-green-50 text-green-700',
                                    'ACCEPTED' => 'bg-blue-50 text-blue-700',
                                    'DECLINED', 'REJECTED' => 'bg-red-50 text-red-700',
                                    default => 'bg-amber-50 text-amber-700',
                                };
                                $phase = ucwords(strtolower(str_replace('_', ' ', (string) ($assignment['phase'] ?? ''))));
                                ?>
                                <article class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400"><?= htmlspecialchars($phase ?: 'Unspecified phase', ENT_QUOTES, 'UTF-8') ?></p>
                                            <p class="mt-1 break-words text-sm font-semibold text-gray-900"><?= htmlspecialchars($assignment['role_name'] ?? $assignment['assigned_to_username'] ?? '—', ENT_QUOTES, 'UTF-8') ?></p>
                                        </div>
                                        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold <?= $decisionClass ?>"><?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $decision ?: 'Pending'))), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 border-t border-gray-200 pt-3 text-xs">
                                        <div>
                                            <dt class="text-gray-400">Assigned At</dt>
                                            <dd class="mt-1 break-words font-medium text-gray-700"><?= !empty($assignment['created_at']) ? htmlspecialchars(date('M j, Y g:i A', strtotime($assignment['created_at'])), ENT_QUOTES, 'UTF-8') : '—' ?></dd>
                                        </div>
                                        <div>
                                            <dt class="text-gray-400">Accepted By</dt>
                                            <dd class="mt-1 break-words font-medium text-gray-700"><?= htmlspecialchars($assignment['accepted_by_username'] ?? '—', ENT_QUOTES, 'UTF-8') ?></dd>
                                        </div>
                                        <div class="col-span-2">
                                            <dt class="text-gray-400">Accepted At</dt>
                                            <dd class="mt-1 break-words font-medium text-gray-700"><?= !empty($assignment['accepted_at']) ? htmlspecialchars(date('M j, Y g:i A', strtotime($assignment['accepted_at'])), ENT_QUOTES, 'UTF-8') : '—' ?></dd>
                                        </div>
                                    </dl>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <div class="hidden overflow-x-auto sm:block">
                            <table class="w-full min-w-[640px] divide-y divide-gray-100 text-left text-sm">
                                <thead>
                                    <tr class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                                        <th class="pb-3 pr-4">Phase</th>
                                        <th class="pb-3 pr-4">Assigned To</th>
                                        <th class="pb-3 pr-4">Decision</th>
                                        <th class="pb-3 pr-4">Assigned At</th>
                                        <th class="pb-3 pr-4">Accepted By</th>
                                        <th class="pb-3">Accepted At</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <?php foreach ($assignments as $assignment): ?>
                                        <?php
                                        $decision = (string) ($assignment['decision'] ?? '');
                                        $decisionClass = match ($decision) {
                                            'COMPLETED', 'NOTED' => 'bg-green-50 text-green-700',
                                            'ACCEPTED' => 'bg-blue-50 text-blue-700',
                                            'DECLINED', 'REJECTED' => 'bg-red-50 text-red-700',
                                            default => 'bg-amber-50 text-amber-700',
                                        };
                                        ?>
                                        <tr class="text-gray-700">
                                            <td class="py-3 pr-4"><?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', (string) ($assignment['phase'] ?? '')))) ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="py-3 pr-4 font-medium"><?= htmlspecialchars($assignment['role_name'] ?? $assignment['assigned_to_username'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="py-3 pr-4"><span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold <?= $decisionClass ?>"><?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $decision ?: 'Pending'))), ENT_QUOTES, 'UTF-8') ?></span></td>
                                            <td class="whitespace-nowrap py-3 pr-4"><?= !empty($assignment['created_at']) ? htmlspecialchars(date('M j, Y g:i A', strtotime($assignment['created_at'])), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                                            <td class="py-3 pr-4"><?= htmlspecialchars($assignment['accepted_by_username'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="whitespace-nowrap py-3"><?= !empty($assignment['accepted_at']) ? htmlspecialchars(date('M j, Y g:i A', strtotime($assignment['accepted_at'])), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>

        <!-- Communication First Reading action -->
        <div class="order-1">
            <?php if ($canAct): ?>
            <div class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="mb-4 text-base font-semibold text-gray-900">First Reading Decision</h2>
                <p class="mb-5 text-sm text-gray-500 leading-5">
                    Select the Plenary's decision for this Communication at First Reading.
                </p>

                <form method="POST" action="<?= BASE_URL ?>/plenary/comm-first-reading/store"
                      class="space-y-6" enctype="multipart/form-data" id="comm-fr-form">
                    <input type="hidden" name="document_id" value="<?= $documentId ?>">

                    <!-- Reading Date -->
                    <div>
                        <label for="reading_date" class="block text-sm font-medium text-gray-700 mb-1">
                            Reading Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date" id="reading_date" name="reading_date" required
                               value="<?= htmlspecialchars($old['reading_date'] ?? date('Y-m-d')) ?>"
                               class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                                      text-gray-800 focus:border-primary focus:outline-none focus:ring-2
                                      focus:ring-primary/20">
                    </div>

                    <!-- Decision selector -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Decision <span class="text-red-500">*</span>
                        </label>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3" role="group" aria-label="First Reading Decision">
                            <?php
                            $selectedDecision = $old['comm_fr_decision'] ?? '';
                            $decisions = [
                                'NOTED'              => ['label' => 'Noted', 'color' => 'emerald', 'desc' => 'Noted; Communication appears on Plenary Communications page.'],
                                'APPROVED'           => ['label' => 'Approved for Resolution/Ordinance under Other Matters', 'color' => 'blue', 'desc' => 'Record the approved subject matter, number, final output type, and files.'],
                                'REFER_TO_COMMITTEE' => ['label' => 'Referred to Committee', 'color' => 'violet', 'desc' => 'Hand off to Committee using the existing referral workflow.'],
                            ];
                            foreach ($decisions as $val => $info):
                                $checked = ($selectedDecision === $val) ? 'checked' : '';
                                $colors  = [
                                    'emerald' => 'border-emerald-400 bg-emerald-50 ring-2 ring-emerald-400',
                                    'blue'    => 'border-blue-400 bg-blue-50 ring-2 ring-blue-400',
                                    'violet'  => 'border-violet-400 bg-violet-50 ring-2 ring-violet-400',
                                ];
                                $baseCard = 'cursor-pointer rounded-xl border-2 border-gray-200 p-4 transition hover:border-gray-300 hover:bg-gray-50';
                            ?>
                            <label class="<?= $baseCard ?>" data-decision-card="<?= $val ?>">
                                <input type="radio" name="comm_fr_decision" value="<?= $val ?>"
                                       class="sr-only" <?= $checked ?> required
                                       onchange="updateDecision(this.value)">
                                <div class="flex items-start gap-2">
                                    <div class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 border-gray-300"
                                         data-decision-radio="<?= $val ?>">
                                        <div class="h-2 w-2 rounded-full bg-current opacity-0 decision-dot"></div>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($info['label']) ?></p>
                                        <p class="mt-1 text-xs text-gray-500"><?= htmlspecialchars($info['desc']) ?></p>
                                    </div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- APPROVED: subject matter, Resolution/Ordinance Number, Proposed Number + Files -->
                    <div id="section-approved" class="hidden space-y-4">
                        <div>
                            <label for="approved_subject_matter" class="block text-sm font-medium text-gray-700 mb-1">
                                Subject Matter <span class="text-red-500">*</span>
                            </label>
                            <textarea id="approved_subject_matter" name="approved_subject_matter" rows="3"
                                      maxlength="5000"
                                      placeholder="Subject matter to be recorded for the approved resolution…"
                                      class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                                             text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none
                                             focus:ring-2 focus:ring-primary/20"><?= htmlspecialchars($old['approved_subject_matter'] ?? $subjectMatter) ?></textarea>
                            <p class="mt-1 text-xs text-gray-400">
                                The current subject matter is prefilled. Updating it here keeps the original document subject matter in the record.
                            </p>
                        </div>
                        <fieldset>
                            <legend class="block text-sm font-medium text-gray-700 mb-2">
                                Final Output Type <span class="text-red-500">*</span>
                            </legend>
                            <div class="flex flex-wrap gap-3">
                                <?php foreach (['RESOLUTION' => 'Resolution', 'ORDINANCE' => 'Ordinance'] as $value => $label): ?>
                                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700">
                                        <input type="radio" name="final_output_type" value="<?= $value ?>"
                                               <?= ($old['final_output_type'] ?? 'RESOLUTION') === $value ? 'checked' : '' ?>
                                               class="h-4 w-4 accent-primary">
                                        <?= htmlspecialchars($label) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">This classifies the finalized output and does not change the original document type.</p>
                        </fieldset>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="resolution_number" class="block text-sm font-medium text-gray-700 mb-1">
                                    Resolution/Ordinance No. <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="resolution_number" name="resolution_number"
                                       value="<?= htmlspecialchars($old['resolution_number'] ?? '') ?>"
                                       maxlength="100"
                                       placeholder="e.g. SP Res. No. 2026-001 or SP Ordinance No. 2026-001"
                                       class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                                              text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none
                                              focus:ring-2 focus:ring-primary/20">
                            </div>
                            <div>
                                <label for="proposed_number_approved" class="block text-sm font-medium text-gray-700 mb-1">
                                    Proposed Number
                                    <span class="text-xs font-normal text-gray-400">(optional)</span>
                                </label>
                                <input type="text" id="proposed_number_approved" name="proposed_number"
                                       value="<?= htmlspecialchars($old['proposed_number'] ?? '') ?>"
                                       maxlength="100"
                                       placeholder="e.g. SP Res. No. 2026-001"
                                       class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                                              text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none
                                              focus:ring-2 focus:ring-primary/20">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Final Output File(s) <span class="text-red-500">*</span>
                            </label>

                            <!-- Hidden real file input (accepts multiple) -->
                            <input type="file" id="resolution_files" name="resolution_files[]"
                                   multiple
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp"
                                   class="sr-only"
                                   aria-label="Final output files"
                                   tabindex="-1">

                            <!-- Drop zone -->
                            <div id="res-drop-zone"
                                 role="button"
                                 tabindex="0"
                                 aria-label="Drop final output files here or click to browse"
                                 class="relative flex h-32 w-full flex-col items-center justify-center gap-2
                                        rounded-xl border-2 border-dashed border-gray-300 bg-gray-50
                                        px-4 text-center transition hover:bg-gray-100
                                        focus:outline-none focus:ring-2 focus:ring-primary/40
                                        cursor-pointer">

                                <!-- Cloud-upload icon -->
                                <svg class="h-8 w-8 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>

                                <p class="mb-1 text-sm text-gray-600 pointer-events-none">
                                    <span class="font-semibold">Click to upload</span> or drag and drop
                                    <span class="text-primary underline-offset-2 hover:underline">browse</span>
                                </p>
                                <p class="text-xs text-gray-500 pointer-events-none">
                                    PDF, Word, Excel, images (MAX. 25 MB each)
                                </p>

                                <!-- Active-drag overlay -->
                                <div id="res-drag-overlay"
                                     class="pointer-events-none absolute inset-0 hidden rounded-xl
                                            border-2 border-primary bg-primary/10">
                                </div>
                            </div>

                            <!-- Selected-file list -->
                            <ul id="res-file-list"
                                class="hidden mt-3 space-y-2"
                                aria-label="Selected final output files"
                                aria-live="polite">
                            </ul>

                            <!-- Client-side error display -->
                            <p id="res-file-error" class="hidden mt-1 text-xs text-red-600" role="alert"></p>

                            <p class="mt-1 text-xs text-gray-400">
                                Supported: PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, PNG, GIF, WEBP
                            </p>
                        </div>
                    </div>

                    <!-- REFER_TO_COMMITTEE: Committee selection -->
                    <div id="section-refer" class="hidden space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Committees <span class="text-red-500">*</span>
                            </label>
                            <p class="mb-2 text-xs text-gray-500">Select one or more committees to refer this Communication to.</p>
                            <?php if (empty($committees)): ?>
                                <p class="text-xs text-red-500">No active committees found.</p>
                            <?php else: ?>
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3 max-h-60 overflow-y-auto rounded-xl border border-gray-200 bg-gray-50 p-3">
                                    <?php
                                    $selectedCids = isset($old['committee_ids'])
                                        ? array_map('intval', (array) $old['committee_ids'])
                                        : array_map('intval', array_column($docCommittees ?? [], 'committee_id'));
                                    foreach ($committees as $committee):
                                        $checked = in_array((int) $committee['id'], $selectedCids, true) ? 'checked' : '';
                                    ?>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="committee_ids[]"
                                               value="<?= (int) $committee['id'] ?>" <?= $checked ?>
                                               class="h-4 w-4 rounded border-gray-300 text-primary
                                                      focus:ring-primary/50">
                                        <span class="text-sm text-gray-700"><?= htmlspecialchars($committee['name']) ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Remarks -->
                    <div>
                        <label for="remarks" class="block text-sm font-medium text-gray-700 mb-1">
                            Remarks
                            <span class="text-xs font-normal text-gray-400">(optional)</span>
                        </label>
                        <textarea id="remarks" name="remarks" rows="3" maxlength="2000"
                                  class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                                         text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none
                                         focus:ring-2 focus:ring-primary/20"
                                  placeholder="Optional remarks or notes…"><?= htmlspecialchars($old['remarks'] ?? '') ?></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5
                                       text-sm font-semibold text-white hover:bg-primary/90 transition
                                       focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Record Action
                        </button>
                        <a href="<?= BASE_URL ?>/plenary/first-reading"
                           class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium
                                  text-gray-700 hover:bg-gray-50 transition">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6">
                <p class="text-sm font-medium text-amber-800">
                    You must accept this document in the Plenary Inbox before you can record a First Reading decision.
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    // ── Decision card toggling ────────────────────────────────────────────────

    var sections = {
        'APPROVED':           document.getElementById('section-approved'),
        'REFER_TO_COMMITTEE': document.getElementById('section-refer'),
    };
    var resolutionFileInput = document.getElementById('resolution_files');

    function updateDecision(value) {
        Object.keys(sections).forEach(function (key) {
            var el = sections[key];
            if (!el) { return; }
            if (key === value) {
                el.classList.remove('hidden');
            } else {
                el.classList.add('hidden');
            }
        });

        document.querySelectorAll('[data-decision-card]').forEach(function (card) {
            var isSelected = card.dataset.decisionCard === value;
            card.classList.toggle('border-primary', isSelected);
            card.classList.toggle('bg-primary/5', isSelected);
            card.classList.toggle('border-gray-200', !isSelected);
            var dot = card.querySelector('.decision-dot');
            if (dot) {
                dot.classList.toggle('opacity-100', isSelected);
                dot.classList.toggle('opacity-0', !isSelected);
            }
        });
        var approvedSubjectMatter = document.getElementById('approved_subject_matter');
        if (approvedSubjectMatter) {
            approvedSubjectMatter.required = value === 'APPROVED';
        }
        if (resolutionFileInput) {
            resolutionFileInput.required = value === 'APPROVED';
            resolutionFileInput.setAttribute('aria-required', value === 'APPROVED' ? 'true' : 'false');
        }
    }

    window.updateDecision = updateDecision;

    var selected = document.querySelector('input[name="comm_fr_decision"]:checked');
    if (selected) { updateDecision(selected.value); }

    // ── Drag-and-drop / multi-file upload ────────────────────────────────────

    var MAX_FILES    = 10;
    var MAX_SIZE_MB  = 25;
    var MAX_SIZE     = MAX_SIZE_MB * 1024 * 1024;
    var ALLOWED_EXTS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'webp'];

    var fileInput  = document.getElementById('resolution_files');
    var dropZone   = document.getElementById('res-drop-zone');
    var fileList   = document.getElementById('res-file-list');
    var fileError  = document.getElementById('res-file-error');
    var dragOverlay = document.getElementById('res-drag-overlay');

    // DataTransfer used as a mutable FileList to accumulate files across picks
    var dt = new DataTransfer();

    function extOf(name) {
        return (name.split('.').pop() || '').toLowerCase();
    }

    function fmtSize(bytes) {
        if (bytes >= 1024 * 1024) { return (bytes / (1024 * 1024)).toFixed(1) + ' MB'; }
        return Math.round(bytes / 1024) + ' KB';
    }

    function showError(msg) {
        fileError.textContent = msg;
        fileError.classList.remove('hidden');
    }

    function clearError() {
        fileError.textContent = '';
        fileError.classList.add('hidden');
    }

    function renderList() {
        fileList.innerHTML = '';
        if (dt.files.length === 0) {
            fileList.classList.add('hidden');
            return;
        }
        fileList.classList.remove('hidden');

        Array.from(dt.files).forEach(function (file, idx) {
            var li = document.createElement('li');
            li.className = 'flex items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm';
            li.dataset.fileIdx = idx;

            // Icon + name + size
            var left = document.createElement('div');
            left.className = 'flex items-center gap-2 min-w-0';

            var icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            icon.setAttribute('class', 'h-4 w-4 shrink-0 text-gray-400');
            icon.setAttribute('fill', 'none');
            icon.setAttribute('stroke', 'currentColor');
            icon.setAttribute('viewBox', '0 0 24 24');
            icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>';

            var nameSpan = document.createElement('span');
            nameSpan.className = 'truncate text-gray-800';
            nameSpan.textContent = file.name;

            var sizeSpan = document.createElement('span');
            sizeSpan.className = 'shrink-0 text-xs text-gray-400 ml-1';
            sizeSpan.textContent = fmtSize(file.size);

            left.appendChild(icon);
            left.appendChild(nameSpan);
            left.appendChild(sizeSpan);

            // Remove button
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'shrink-0 rounded-lg p-1 text-gray-400 hover:bg-red-50 hover:text-red-500 transition';
            btn.setAttribute('aria-label', 'Remove ' + file.name);
            btn.innerHTML = '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';
            btn.addEventListener('click', function () {
                removeFile(idx);
            });

            li.appendChild(left);
            li.appendChild(btn);
            fileList.appendChild(li);
        });
    }

    function removeFile(idx) {
        var updated = new DataTransfer();
        Array.from(dt.files).forEach(function (f, i) {
            if (i !== idx) { updated.items.add(f); }
        });
        dt = updated;
        fileInput.files = dt.files;
        clearError();
        renderList();
    }

    function addFiles(incoming) {
        clearError();
        var candidates = Array.from(incoming);
        var existing   = Array.from(dt.files);

        // De-duplicate by name+size
        var existingKeys = new Set(existing.map(function (f) { return f.name + ':' + f.size; }));

        var rejected = [];
        var accepted = [];

        candidates.forEach(function (file) {
            var ext = extOf(file.name);
            var key = file.name + ':' + file.size;

            if (existingKeys.has(key)) { return; } // already added, skip silently

            if (!ALLOWED_EXTS.includes(ext)) {
                rejected.push('"' + file.name + '" — type not allowed');
                return;
            }
            if (file.size > MAX_SIZE) {
                rejected.push('"' + file.name + '" — exceeds ' + MAX_SIZE_MB + ' MB');
                return;
            }
            if (existing.length + accepted.length >= MAX_FILES) {
                rejected.push('"' + file.name + '" — maximum ' + MAX_FILES + ' files reached');
                return;
            }
            accepted.push(file);
        });

        accepted.forEach(function (f) {
            dt.items.add(f);
            existing.push(f);
        });

        fileInput.files = dt.files;

        if (rejected.length > 0) {
            showError('Some files were not added: ' + rejected.join('; ') + '.');
        }

        renderList();
    }

    // Click to browse
    dropZone.addEventListener('click', function () {
        fileInput.click();
    });
    dropZone.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); }
    });

    // Native input change (browsed files)
    fileInput.addEventListener('change', function () {
        if (fileInput.files.length > 0) {
            addFiles(fileInput.files);
        }
    });

    // Drag events
    dropZone.addEventListener('dragover', function (e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
        dropZone.classList.add('border-primary', 'bg-primary/5');
        dragOverlay.classList.remove('hidden');
    });
    dropZone.addEventListener('dragleave', function (e) {
        if (!dropZone.contains(e.relatedTarget)) {
            dropZone.classList.remove('border-primary', 'bg-primary/5');
            dragOverlay.classList.add('hidden');
        }
    });
    dropZone.addEventListener('drop', function (e) {
        e.preventDefault();
        dropZone.classList.remove('border-primary', 'bg-primary/5');
        dragOverlay.classList.add('hidden');
        if (e.dataTransfer.files.length > 0) {
            addFiles(e.dataTransfer.files);
        }
    });

    // Prevent the form from submitting when no files have been selected for APPROVED
    document.getElementById('comm-fr-form').addEventListener('submit', function (e) {
        var decision = document.querySelector('input[name="comm_fr_decision"]:checked');
        // Keep the actual multipart form input synchronized with the accumulated file list.
        if (dt.files.length === 0 && fileInput.files.length > 0) {
            addFiles(fileInput.files);
        }
        fileInput.files = dt.files;

        if (decision && decision.value === 'APPROVED' && fileInput.files.length === 0) {
            e.preventDefault();
            showError('Please attach at least one final output file before submitting.');
            dropZone.focus();
        }
    });

}());
</script>



<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

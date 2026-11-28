<?php
/**
 * Plenary Cases (Complaints / Administrative Cases) First Reading — decision form
 *
 * Variables supplied by PlenaryReadingController::casesFirstReadingShow():
 *   $document        array
 *   $plenaryDetails  array|null
 *   $committees      array   all active committees for select
 *   $events          array
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
$errors         = $errors         ?? [];
$old            = $old            ?? [];
$canAct         = $canAct         ?? false;
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
        <a href="<?= BASE_URL ?>/plenary/first-reading?tab=petitions"
           class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 text-white
                  transition hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
           aria-label="Back to Petitions First Reading">
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
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
             role="status"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($error ?? null)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
             role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            <ul class="list-disc list-inside space-y-1">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php
    $resubmissionTopMargin = 'mt-0';
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
                          style="background-color:<?= htmlspecialchars($document['document_type_badge_color'] ?? '#2563EB') ?>1a;
                                 color:<?= htmlspecialchars($document['document_type_badge_color'] ?? '#2563EB') ?>;">
                        <?= htmlspecialchars($document['document_type_name']) ?>
                    </span>
                </div>
                <?php endif; ?>
                <?php if (!empty($targetDate)): ?>
                <div>
                    <p class="text-xs text-gray-400">Target First Reading Date</p>
                    <p class="text-sm text-gray-800"><?= htmlspecialchars(date('F j, Y', strtotime($targetDate))) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($document['status'] ?? $document['status_name'] ?? null)): ?>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Current Status</p>
                    <?php $bc = $document['status_badge_color'] ?? '#6B7280'; ?>
                    <span class="mt-1 inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                          style="background-color:<?= htmlspecialchars($bc) ?>1a; color:<?= htmlspecialchars($bc) ?>;">
                        <span class="h-1.5 w-1.5 rounded-full" style="background-color:<?= htmlspecialchars($bc) ?>;"></span>
                        <?= htmlspecialchars($document['status'] ?? $document['status_name'] ?? '') ?>
                    </span>
                </div>
                <?php endif; ?>
                <?php if (!empty($document['current_phase'])): ?>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Current Phase</p>
                    <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $document['current_phase'])))) ?></p>
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
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                          d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
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
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                            </svg>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="break-words text-sm font-medium text-gray-800 sm:truncate">
                                                <?= htmlspecialchars($attachmentName, ENT_QUOTES, 'UTF-8') ?>
                                            </p>
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
                                               class="inline-flex justify-center rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-white hover:bg-primary-hover transition sm:py-1.5">Preview</a>
                                            <a href="<?= htmlspecialchars($attachmentUrl, ENT_QUOTES, 'UTF-8') ?>" download
                                               class="inline-flex justify-center rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-600 hover:bg-gray-50 transition sm:py-1.5">Download</a>
                                        </div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <div class="space-y-6">
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
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold <?= $decisionClass ?>">
                                                <?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $decision ?: 'Pending'))), ENT_QUOTES, 'UTF-8') ?>
                                            </span>
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
                                                <td class="py-3 pr-4">
                                                    <span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold <?= $decisionClass ?>">
                                                        <?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $decision ?: 'Pending'))), ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                </td>
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
        </div>

        <!-- Petitions First Reading action -->
        <div class="order-1">
            <?php if ($canAct): ?>
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="mb-2 text-base font-semibold text-gray-900">First Reading Decision — Petitions</h2>
                <p class="mb-5 text-sm text-gray-500 leading-5">
                    Select the Plenary's decision for this
                    <?= htmlspecialchars($document['document_type_name'] ?? 'document') ?> at First Reading.
                </p>

                <form method="POST" action="<?= BASE_URL ?>/plenary/cases-first-reading/store"
                      class="space-y-6" id="cases-fr-form" novalidate>
                    <input type="hidden" name="document_id" value="<?= $documentId ?>">

                    <!-- Reading Date -->
                    <div>
                        <label for="reading_date" class="block text-sm font-medium text-gray-700 mb-1">
                            Reading Date <span class="text-red-500" aria-hidden="true">*</span>
                        </label>
                        <input type="date" id="reading_date" name="reading_date" required
                               value="<?= htmlspecialchars($old['reading_date'] ?? date('Y-m-d')) ?>"
                               class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                                      text-gray-800 focus:border-primary focus:outline-none focus:ring-2
                                      focus:ring-primary/20"
                               aria-required="true">
                    </div>

                    <!-- Decision selector -->
                    <fieldset>
                        <legend class="block text-sm font-medium text-gray-700 mb-2">
                            Decision <span class="text-red-500" aria-hidden="true">*</span>
                        </legend>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <?php
                            $selectedDecision = $old['cases_fr_decision'] ?? '';
                            $decisions = [
                                'REFER_TO_COMMITTEE' => [
                                    'label' => 'Referred to Committee',
                                    'color' => 'violet',
                                    'desc'  => 'Hand off to Committee. After the committee process completes, you will adopt the report to finalize.',
                                ],
                                'NOTED' => [
                                    'label' => 'Noted',
                                    'color' => 'emerald',
                                    'desc'  => 'Document is noted at Plenary. It will appear on the Petitions page.',
                                ],
                            ];
                            foreach ($decisions as $val => $info):
                                $isChecked = ($selectedDecision === $val);
                            ?>
                            <label class="cursor-pointer rounded-xl border-2 transition hover:border-gray-300 hover:bg-gray-50
                                          <?= $isChecked ? 'border-primary bg-primary/5' : 'border-gray-200' ?> p-4"
                                   data-decision-card="<?= $val ?>">
                                <input type="radio" name="cases_fr_decision" value="<?= $val ?>"
                                       class="sr-only" <?= $isChecked ? 'checked' : '' ?> required
                                       onchange="casesFrUpdateDecision(this.value)"
                                       aria-required="true">
                                <div class="flex items-start gap-2">
                                    <div class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2
                                                <?= $isChecked ? 'border-primary' : 'border-gray-300' ?>"
                                         data-decision-radio="<?= $val ?>" aria-hidden="true">
                                        <div class="h-2 w-2 rounded-full bg-primary <?= $isChecked ? 'opacity-100' : 'opacity-0' ?> decision-dot"></div>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($info['label']) ?></p>
                                        <p class="mt-1 text-xs text-gray-500"><?= htmlspecialchars($info['desc']) ?></p>
                                    </div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <!-- REFER_TO_COMMITTEE: Committee selection -->
                    <div id="section-refer"
                         class="<?= ($selectedDecision === 'REFER_TO_COMMITTEE') ? '' : 'hidden' ?> space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Committee Referral <span class="text-red-500" aria-hidden="true">*</span>
                            </label>
                            <p class="mb-2 text-xs text-gray-500">Select one or more committees to refer this document to.</p>
                            <?php if (empty($committees)): ?>
                                <p class="text-xs text-red-500">No active committees found.</p>
                            <?php else: ?>
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3 max-h-60 overflow-y-auto
                                            rounded-xl border border-gray-200 bg-gray-50 p-3"
                                     role="group" aria-label="Committees">
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
                                               class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary/50">
                                        <span class="text-sm text-gray-700"><?= htmlspecialchars($committee['name']) ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- NOTED: no extra inputs needed -->
                    <div id="section-noted"
                         class="<?= ($selectedDecision === 'NOTED') ? '' : 'hidden' ?> space-y-2">
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            The document will be marked as <strong>Noted</strong> and moved to the <strong>Petitions</strong> page.
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
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Record Action
                        </button>
                        <a href="<?= BASE_URL ?>/plenary/first-reading?tab=petitions"
                           class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium
                                  text-gray-700 hover:bg-gray-50 transition">
                            Cancel
                        </a>
                    </div>
                </form>
            </section>
            <?php else: ?>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm sm:p-6">
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

    function casesFrUpdateDecision(value) {
        var sections = {
            'REFER_TO_COMMITTEE': document.getElementById('section-refer'),
            'NOTED':              document.getElementById('section-noted'),
        };

        Object.keys(sections).forEach(function (key) {
            var el = sections[key];
            if (!el) { return; }
            el.classList.toggle('hidden', key !== value);
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
            var radio = card.querySelector('[data-decision-radio]');
            if (radio) {
                radio.classList.toggle('border-primary', isSelected);
                radio.classList.toggle('border-gray-300', !isSelected);
            }
        });
    }

    window.casesFrUpdateDecision = casesFrUpdateDecision;

    // Initialise from page state (handles back-button / old-value repopulation)
    var selected = document.querySelector('input[name="cases_fr_decision"]:checked');
    if (selected) { casesFrUpdateDecision(selected.value); }
}());
</script>



<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

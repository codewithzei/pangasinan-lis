<?php
/**
 * Plenary First Reading — detail & processing form
 *
 * Variables supplied by PlenaryReadingController::firstReadingShow():
 *   $document        array
 *   $plenaryDetails  array|null
 *   $authors         array
 *   $coAuthors       array
 *   $docCommittees   array   currently assigned committees
 *   $committees      array   all active committees for select
 *   $events          array
 *   $assignments     array   document assignment history
 *   $existingReading array|null  (should be null here; gate enforced by controller)
 *   $canAct          bool
 *   $success         string|null
 *   $error           string|null
 *   $errors          array
 *   $old             array
 */

$document       = $document       ?? [];
$plenaryDetails = $plenaryDetails ?? null;
$authors        = $authors        ?? [];
$coAuthors      = $coAuthors      ?? [];
$docCommittees  = $docCommittees  ?? [];
$committees     = $committees     ?? [];
$events         = $events         ?? [];
$assignments    = $assignments    ?? [];
$errors         = $errors         ?? [];
$old            = $old            ?? [];
$canAct         = $canAct         ?? false;
$documentAttachments = $documentAttachments ?? [];

$documentId     = (int) ($document['id']               ?? 0);
$trackingNumber = $document['tracking_number']          ?? '';
$subjectMatter  = $document['subject_matter']           ?? '';
$proposedNumber = $plenaryDetails['proposed_number']    ?? '';
$sessionType    = $plenaryDetails['session_type']       ?? '';
$targetDate     = $plenaryDetails['target_first_reading_date'] ?? '';

// Pre-populate committee ids from current doc committees
$existingCommitteeIds = array_column($docCommittees, 'committee_id');

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
                </div>
            </div>
        </div>
        <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
    </section>

    <?php if (!empty($success ?? null)): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($error ?? null)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc list-inside space-y-1"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <?php
    $resubmissionTopMargin = 'mt-0';
    require __DIR__ . '/../../partials/resubmission-relationship.php';
    unset($resubmissionTopMargin);
    $mfrDetailRoute = 'plenary/inbox';
    require __DIR__ . '/../../partials/mfr-relationship.php';
    ?>

    <?php if ($canAct): ?>
    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="mb-5 text-base font-semibold text-gray-900">First Reading Action</h2>

        <form method="POST" action="<?= BASE_URL ?>/plenary/first-reading/store" id="frForm">
            <input type="hidden" name="document_id" value="<?= $documentId ?>">

            <div class="space-y-5">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="reading_date">Reading Date <span class="text-red-500">*</span></label>
                    <input type="date" id="reading_date" name="reading_date"
                           value="<?= htmlspecialchars($old['reading_date'] ?? date('Y-m-d')) ?>"
                           class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                           required>
                </div>

                <div>
                    <p class="mb-2 block text-sm font-medium text-gray-700">First Reading Decision <span class="text-red-500">*</span></p>
                    <div class="space-y-3">
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-4 transition hover:bg-gray-50 has-[:checked]:border-primary has-[:checked]:bg-blue-50">
                            <input type="radio" name="first_reading_action" value="REFERRED_TO_COMMITTEE"
                                   <?= ($old['first_reading_action'] ?? '') === 'REFERRED_TO_COMMITTEE' ? 'checked' : '' ?>
                                   class="mt-0.5 h-4 w-4 accent-primary" id="action_refer">
                            <div>
                                <p class="text-sm font-medium text-gray-900">Referred to Committee</p>
                                <p class="mt-0.5 text-xs text-gray-500">Route this document to one or more Committees for review before Second Reading.</p>
                            </div>
                        </label>
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-4 transition hover:bg-gray-50 has-[:checked]:border-primary has-[:checked]:bg-blue-50">
                            <input type="radio" name="first_reading_action" value="ADOPTED_SUSPENDED_RULES"
                                   <?= ($old['first_reading_action'] ?? '') === 'ADOPTED_SUSPENDED_RULES' ? 'checked' : '' ?>
                                   class="mt-0.5 h-4 w-4 accent-primary" id="action_adopt">
                            <div>
                                <p class="text-sm font-medium text-gray-900">Approved for Second Reading under Suspended Rules</p>
                                <p class="mt-0.5 text-xs text-gray-500">Skip Committee referral and move directly to the Second Reading queue.</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div id="committeeSection" class="hidden">
                    <label class="mb-1 block text-sm font-medium text-gray-700">Select Committee(s) <span class="text-red-500">*</span></label>
                    <p class="mb-2 text-xs text-gray-500">Pre-populated from Plenary processing. You may adjust if needed.</p>
                    <div class="max-h-56 space-y-1 overflow-y-auto rounded-xl border border-gray-200 p-3">
                        <?php foreach ($committees as $c): ?>
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-gray-50">
                                <input type="checkbox" name="committee_ids[]" value="<?= (int) $c['id'] ?>"
                                       <?= in_array((int) $c['id'], array_map('intval', isset($old['committee_ids']) ? (array) $old['committee_ids'] : $existingCommitteeIds), true) ? 'checked' : '' ?>
                                       class="h-4 w-4 rounded accent-primary">
                                <span class="text-sm text-gray-800"><?= htmlspecialchars($c['name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="fr_remarks">Remarks (optional)</label>
                    <textarea id="fr_remarks" name="remarks" rows="3"
                              placeholder="Enter any remarks for this First Reading action…"
                              class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"><?= htmlspecialchars($old['remarks'] ?? '') ?></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <a href="<?= BASE_URL ?>/plenary/first-reading"
                       class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit"
                            class="rounded-xl bg-primary px-5 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                            id="frSubmitBtn">
                        Record Action
                    </button>
                </div>
            </div>
        </form>
    </section>
    <?php else: ?>
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
        <p class="text-sm font-medium text-amber-800">You are not the owner of this document. Only the Plenary user who accepted it may record the First Reading.</p>
    </div>
    <?php endif; ?>

    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-lg font-semibold text-gray-900">Document Information</h2>
        <div class="mt-4 grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <p class="text-xs font-medium text-gray-500">Tracking Number</p>
                <p class="mt-1 text-sm font-semibold text-gray-900"><?= htmlspecialchars($trackingNumber) ?></p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500">Date &amp; Time Received</p>
                <p class="mt-1 text-sm text-gray-900">
                    <?= !empty($document['date_received']) ? htmlspecialchars(date('F j, Y', strtotime($document['date_received']))) : '—' ?>
                    at <?= !empty($document['time_received']) ? htmlspecialchars(date('g:i A', strtotime($document['time_received']))) : '—' ?>
                </p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500">Document Type</p>
                <?php if (!empty($document['document_type_name'])): ?>
                    <span class="mt-1 inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                          style="background-color: <?= htmlspecialchars($document['document_type_badge_color'] ?? '#2563EB') ?>1a; color: <?= htmlspecialchars($document['document_type_badge_color'] ?? '#2563EB') ?>;">
                        <?= htmlspecialchars($document['document_type_name']) ?>
                    </span>
                <?php else: ?>
                    <p class="mt-1 text-sm text-gray-900">—</p>
                <?php endif; ?>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500">Current Status</p>
                <?php $firstReadingStatusColor = $document['status_badge_color'] ?? '#6B7280'; ?>
                <span class="mt-1 inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                      style="background-color: <?= htmlspecialchars($firstReadingStatusColor) ?>1a; color: <?= htmlspecialchars($firstReadingStatusColor) ?>;">
                    <span class="h-1.5 w-1.5 rounded-full" style="background-color: <?= htmlspecialchars($firstReadingStatusColor) ?>;"></span>
                    <?= htmlspecialchars($document['status'] ?? $document['status_name'] ?? 'Awaiting First Reading') ?>
                </span>
            </div>
            <?php if (!empty($document['current_phase'])): ?>
                <div>
                    <p class="text-xs font-medium text-gray-500">Current Division / Phase</p>
                    <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $document['current_phase'])))) ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($proposedNumber)): ?>
                <div>
                    <p class="text-xs font-medium text-gray-500">Proposed Number</p>
                    <p class="mt-1 text-sm font-medium text-primary"><?= htmlspecialchars($proposedNumber) ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($sessionType)): ?>
                <div>
                    <p class="text-xs font-medium text-gray-500">Session Type</p>
                    <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($sessionType) ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($targetDate)): ?>
                <div>
                    <p class="text-xs font-medium text-gray-500">Target First Reading Date</p>
                    <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars(date('F j, Y', strtotime($targetDate))) ?></p>
                </div>
            <?php endif; ?>
            <div class="sm:col-span-2 lg:col-span-3">
                <p class="text-xs font-medium text-gray-500">Subject Matter</p>
                <p class="mt-1 whitespace-pre-wrap text-sm leading-6 text-gray-900"><?= htmlspecialchars($subjectMatter) ?></p>
            </div>
        </div>
    </section>

    <?php require __DIR__ . '/../../partials/source-information.php'; ?>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <!-- Left: Supporting documents and committee details -->
        <div class="lg:col-span-1 space-y-4">
            <!-- Uploaded documents -->
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
                                           target="_blank"
                                           rel="noopener"
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

            <!-- Authors -->
            <section class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900">Authors &amp; Sponsors</h2>
                <?php if (!empty($authors) || !empty($coAuthors)): ?>
                    <div class="mt-5 space-y-5">
                        <?php foreach ([
                            [$authors, 'Authors / Sponsors'],
                            [$coAuthors, 'Co-authors / Co-sponsors'],
                        ] as [$authorGroup, $groupLabel]): ?>
                            <?php if (!empty($authorGroup)): ?>
                                <div>
                                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400"><?= $groupLabel ?></p>
                                    <div class="space-y-2">
                                        <?php if (!empty($authorGroup[0]['is_all_selected'])): ?>
                                            <div class="flex items-start gap-3 rounded-lg border border-gray-100 bg-gray-50 px-4 py-3">
                                                <span class="shrink-0 pt-0.5 text-xs font-semibold text-gray-500">1.</span>
                                                <p class="text-sm font-semibold text-gray-900">ALL</p>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach (array_values($authorGroup) as $rank => $author): ?>
                                                <div class="flex items-start gap-3 rounded-lg border border-gray-100 bg-gray-50 px-4 py-3">
                                                    <span class="shrink-0 pt-0.5 text-xs font-semibold text-gray-500"><?= $rank + 1 ?>.</span>
                                                    <p class="min-w-0 break-words text-sm font-semibold text-gray-900">
                                                        <?= htmlspecialchars($author['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                                    </p>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="mt-4 flex items-center gap-3 rounded-xl border border-dashed border-gray-200 bg-gray-50/50 p-4">
                        <svg class="h-5 w-5 shrink-0 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <p class="text-sm text-gray-400 italic">No authors have been assigned to this document.</p>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Committee referral -->
            <section class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900">Committee Referral</h2>
                <?php if (!empty($docCommittees)): ?>
                    <ul class="mt-3 divide-y divide-gray-100">
                        <?php foreach ($docCommittees as $dc): ?>
                            <li class="py-3 first:pt-0 last:pb-0">
                                <p class="break-words text-sm font-medium text-gray-900"><?= htmlspecialchars($dc['committee_name'] ?? 'Committee', ENT_QUOTES, 'UTF-8') ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="mt-3 border-t border-gray-100 pt-3">
                        <p class="text-sm text-gray-500">No committees have been assigned to this document.</p>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <!-- Right: Action history -->
        <div class="lg:col-span-2 space-y-4">
            <!-- Action history -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
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
                                <p class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($eventLabel) ?></p>
                                <p class="text-xs text-gray-500">
                                    <?= htmlspecialchars($ev['phase'] ?? 'Plenary') ?>
                                    <?php if (!empty($ev['performed_by_username'])): ?>
                                        &middot; by <?= htmlspecialchars($ev['performed_by_username']) ?>
                                    <?php endif; ?>
                                    &middot; <?= !empty($ev['created_at']) ? htmlspecialchars(date('M j, Y g:i A', strtotime($ev['created_at']))) : 'Date unavailable' ?>
                                </p>
                                <?php if (!empty($ev['to_status_name'])): ?>
                                    <p class="mt-1 text-xs text-gray-500">Status: <?= htmlspecialchars($ev['to_status_name']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($ev['remarks'])): ?>
                                    <p class="mt-1 whitespace-pre-line text-xs italic text-gray-500"><?= htmlspecialchars($ev['remarks']) ?></p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-6">
                <h2 class="mb-4 text-base font-semibold text-gray-900">Assignment History</h2>
                <?php if (empty($assignments)): ?>
                    <p class="text-sm text-gray-400">No assignments yet.</p>
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
                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400"><?= htmlspecialchars($phase, ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="mt-1 break-words text-sm font-semibold text-gray-800">
                                            <?= htmlspecialchars($assignment['role_name'] ?? $assignment['assigned_to_username'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                    </div>
                                    <span class="shrink-0 rounded-full px-2 py-1 text-xs font-semibold <?= $decisionClass ?>">
                                        <?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $decision ?: 'Pending'))), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>
                                <dl class="mt-3 grid grid-cols-2 gap-3 text-xs">
                                    <div>
                                        <dt class="text-gray-400">Accepted By</dt>
                                        <dd class="mt-0.5 break-words font-medium text-blue-700">
                                            <?= htmlspecialchars($assignment['accepted_by_username'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-gray-400">Assigned At</dt>
                                        <dd class="mt-0.5 break-words text-gray-600">
                                            <?= !empty($assignment['created_at']) ? htmlspecialchars(date('M j, Y', strtotime($assignment['created_at'])), ENT_QUOTES, 'UTF-8') : '—' ?>
                                        </dd>
                                    </div>
                                    <?php if (!empty($assignment['accepted_at'])): ?>
                                        <div class="col-span-2">
                                            <dt class="text-gray-400">Accepted At</dt>
                                            <dd class="mt-0.5 break-words text-gray-600">
                                                <?= htmlspecialchars(date('M j, Y g:i A', strtotime($assignment['accepted_at'])), ENT_QUOTES, 'UTF-8') ?>
                                            </dd>
                                        </div>
                                    <?php endif; ?>
                                </dl>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="hidden overflow-x-auto sm:block">
                        <table class="w-full divide-y divide-gray-100 text-left text-sm">
                            <thead>
                                <tr class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                                    <th class="pb-2 pr-3">Phase</th>
                                    <th class="pb-2 pr-3">Role</th>
                                    <th class="pb-2 pr-3">Decision</th>
                                    <th class="pb-2 pr-3">Accepted By</th>
                                    <th class="pb-2">Accepted At</th>
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
                                        <td class="py-2 pr-3">
                                            <?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', (string) ($assignment['phase'] ?? '')))), ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="py-2 pr-3">
                                            <?= htmlspecialchars($assignment['role_name'] ?? $assignment['assigned_to_username'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="py-2 pr-3">
                                            <span class="rounded-full px-2 py-1 text-xs font-semibold <?= $decisionClass ?>">
                                                <?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $decision ?: 'Pending'))), ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td class="py-2 pr-3">
                                            <?= htmlspecialchars($assignment['accepted_by_username'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="py-2">
                                            <?= !empty($assignment['accepted_at']) ? htmlspecialchars(date('M j, Y g:i A', strtotime($assignment['accepted_at'])), ENT_QUOTES, 'UTF-8') : '—' ?>
                                        </td>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('frForm');
    const committeeSection = document.getElementById('committeeSection');
    const actionRadios = form.querySelectorAll('input[name="first_reading_action"]');

    function toggleCommittees() {
        const selected = form.querySelector('input[name="first_reading_action"]:checked');
        if (selected && selected.value === 'REFERRED_TO_COMMITTEE') {
            committeeSection.classList.remove('hidden');
        } else {
            committeeSection.classList.add('hidden');
        }
    }

    actionRadios.forEach(r => r.addEventListener('change', toggleCommittees));
    toggleCommittees(); // on load (old values)
});
</script>


<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

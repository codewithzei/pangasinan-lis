<?php
/**
 * Plenary Second Reading — detail & processing form
 *
 * Variables supplied by PlenaryReadingController::secondReadingShow():
 *   $document              array
 *   $plenaryDetails        array|null
 *   $authors               array
 *   $coAuthors             array
 *   $docCommittees         array
 *   $committees            array (all active, for committee select)
 *   $events                array
 *   $firstReading          array|null
 *   $secondReading         array|null
 *   $secondReadingDetails  array|null
 *   $assignments           array
 *   $documentAttachments  array
 *   $canAct                bool
 *   $inEligibleStatus      bool
 *   $isDeferred            bool
 *   $success               string|null
 *   $error                 string|null
 *   $errors                array
 *   $old                   array
 */

$document             = $document             ?? [];
$plenaryDetails       = $plenaryDetails       ?? null;
$authors              = $authors              ?? [];
$coAuthors            = $coAuthors            ?? [];
$docCommittees        = $docCommittees        ?? [];
$committees           = $committees           ?? [];
$events               = $events               ?? [];
$firstReading         = $firstReading         ?? null;
$secondReading        = $secondReading        ?? null;
$secondReadingDetails = $secondReadingDetails ?? null;
$errors               = $errors               ?? [];
$old                  = $old                  ?? [];
$canAct               = $canAct               ?? false;
$inEligibleStatus     = $inEligibleStatus     ?? false;
$isDeferred           = $isDeferred           ?? false;
$documentAttachments  = $documentAttachments  ?? [];

$documentId     = (int) ($document['id']              ?? 0);
$trackingNumber = $document['tracking_number']         ?? '';
$subjectMatter  = $document['subject_matter']          ?? '';
$proposedNumber = $plenaryDetails['proposed_number']   ?? '';
$sessionType    = $plenaryDetails['session_type']      ?? '';

$existingCommitteeIds = array_map('intval', array_column($docCommittees, 'committee_id'));

// Has a final (non-deferred) second reading been recorded?
$hasFinalSecondReading = $secondReading && in_array($secondReading['action'], ['APPROVED', 'WITHDRAWN'], true);

ob_start();
?>

<div class="detail-page space-y-6">
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative z-10 flex items-center gap-3 px-5 py-6 sm:px-8 sm:py-7">
            <a href="<?= BASE_URL ?>/plenary/second-reading"
               class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 text-white transition hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
               aria-label="Back to Second Reading">
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

    <!-- Deferred: return to queue banner -->
    <?php if ($isDeferred && $canAct && !$hasFinalSecondReading): ?>
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
        <p class="text-sm font-semibold text-amber-800 mb-3">This document is currently deferred. Once compliance requirements are met, return it to the Second Reading queue.</p>
        <form method="POST" action="<?= BASE_URL ?>/plenary/second-reading/return-deferred" enctype="multipart/form-data">
            <input type="hidden" name="document_id" value="<?= $documentId ?>">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label class="block text-xs font-medium text-amber-900 mb-1" for="compliance_file_defer">Compliance Document (optional)</label>
                    <input type="file" id="compliance_file_defer" name="compliance_file"
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                           class="w-full rounded-xl border border-amber-300 bg-white px-3 py-2 text-sm text-gray-800 file:mr-3 file:rounded-lg file:border-0 file:bg-amber-600 file:px-3 file:py-1 file:text-xs file:font-medium file:text-white hover:file:bg-amber-700">
                </div>
                <button type="submit" class="rounded-xl bg-amber-600 px-5 py-2 text-sm font-semibold text-white hover:bg-amber-700 transition">
                    Return to Second Reading Queue
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <?php
    $resubmissionTopMargin = 'mt-0';
    require __DIR__ . '/../../partials/resubmission-relationship.php';
    unset($resubmissionTopMargin);
    $mfrDetailRoute = 'plenary/inbox';
    require __DIR__ . '/../../partials/mfr-relationship.php';
    ?>

    <?php
    $isReturnedFromCommittee = ($document['status_name'] ?? '') === 'Returned to Plenary' && $firstReading;
    ?>
    <?php if ($isReturnedFromCommittee && $canAct): ?>
    <div class="rounded-2xl border border-violet-200 bg-violet-50 p-5">
        <p class="mb-3 text-sm font-semibold text-violet-800">This document has been returned from Committee. Advance it to the Second Reading queue to begin processing.</p>
        <form method="POST" action="<?= BASE_URL ?>/plenary/second-reading/advance-from-committee">
            <input type="hidden" name="document_id" value="<?= $documentId ?>">
            <button type="submit" class="rounded-xl bg-violet-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-violet-700">
                Advance to Second Reading Queue
            </button>
        </form>
    </div>
    <?php endif; ?>

    <?php require __DIR__ . '/second-reading-action.php'; ?>

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
                <?php $secondReadingStatusColor = $document['status_badge_color'] ?? '#6B7280'; ?>
                <span class="mt-1 inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                      style="background-color: <?= htmlspecialchars($secondReadingStatusColor) ?>1a; color: <?= htmlspecialchars($secondReadingStatusColor) ?>;">
                    <span class="h-1.5 w-1.5 rounded-full" style="background-color: <?= htmlspecialchars($secondReadingStatusColor) ?>;"></span>
                    <?= htmlspecialchars($document['status'] ?? $document['status_name'] ?? 'Awaiting Second Reading') ?>
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
            <div class="sm:col-span-2 lg:col-span-3">
                <p class="text-xs font-medium text-gray-500">Subject Matter</p>
                <p class="mt-1 whitespace-pre-wrap text-sm leading-6 text-gray-900"><?= htmlspecialchars($subjectMatter) ?></p>
            </div>
        </div>
    </section>

    <?php require __DIR__ . '/../../partials/source-information.php'; ?>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <!-- Left column: Info panels -->
        <div class="lg:col-span-1 space-y-4">
            <!-- First Reading history -->
            <?php if ($firstReading): ?>
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-base font-semibold text-gray-900">First Reading</h2>
                <div class="mt-4 space-y-3">
                    <div>
                        <p class="text-xs font-medium text-gray-500">Date</p>
                        <p class="mt-1 text-sm text-gray-800"><?= htmlspecialchars(date('F j, Y', strtotime($firstReading['reading_date']))) ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Action</p>
                        <span class="mt-1 inline-flex rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">
                            <?= htmlspecialchars(str_replace('_', ' ', $firstReading['action'])) ?>
                        </span>
                    </div>
                    <?php if (!empty($firstReading['remarks'])): ?>
                        <div>
                            <p class="text-xs font-medium text-gray-500">Remarks</p>
                            <p class="mt-1 whitespace-pre-line text-sm text-gray-700"><?= htmlspecialchars($firstReading['remarks']) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-gray-900">Attachments</h2>
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">
                        <?= count($documentAttachments) ?> file<?= count($documentAttachments) === 1 ? '' : 's' ?>
                    </span>
                </div>
                <?php if ($documentAttachments === []): ?>
                    <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-8 text-center">
                        <p class="text-sm font-medium text-gray-700">No attachments</p>
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
                                <div class="min-w-0">
                                    <p class="break-words text-sm font-medium text-gray-800"><?= htmlspecialchars($attachmentName, ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="mt-0.5 break-words text-xs text-gray-500">
                                        <?= number_format((int) ($attachment['file_size'] ?? 0) / 1024, 1) ?> KB
                                        <?php if (!empty($attachment['phase'])): ?>
                                            &middot; <?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', (string) $attachment['phase']))), ENT_QUOTES, 'UTF-8') ?>
                                        <?php endif; ?>
                                        <?php if (!empty($attachment['created_at'])): ?>
                                            &middot; <?= htmlspecialchars(date('M j, Y g:i A', strtotime($attachment['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                                        <?php endif; ?>
                                    </p>
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
                        <p class="text-sm italic text-gray-400">No authors have been assigned to this document.</p>
                    </div>
                <?php endif; ?>
            </section>

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



            <!-- Existing 2nd reading result (finalized/withdrawn) -->
            <?php if ($hasFinalSecondReading && $secondReadingDetails): ?>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 space-y-2">
                <h2 class="text-sm font-semibold text-emerald-800 uppercase tracking-wide">Second Reading Result</h2>
                <?php if ($secondReading['action'] === 'APPROVED'): ?>
                    <?php if (!empty($secondReadingDetails['approved_no'])): ?>
                        <div><p class="text-xs text-emerald-700">Approved No.</p><p class="text-sm font-bold text-emerald-900"><?= htmlspecialchars($secondReadingDetails['approved_no']) ?></p></div>
                    <?php endif; ?>
                    <?php if (!empty($secondReadingDetails['date_finalized'])): ?>
                        <div><p class="text-xs text-emerald-700">Date Approved</p><p class="text-sm text-emerald-800"><?= htmlspecialchars(date('F j, Y', strtotime($secondReadingDetails['date_finalized']))) ?></p></div>
                    <?php endif; ?>
                    <?php if (!empty($secondReadingDetails['approval_files'])): ?>
                        <div>
                            <p class="mb-2 text-xs font-medium text-emerald-700">Approval Attachments</p>
                            <ul class="space-y-2">
                                <?php foreach ($secondReadingDetails['approval_files'] as $file): ?>
                                    <?php
                                    $approvalFilePath = preg_replace(
                                        '#^(?:public/)+#i',
                                        '',
                                        ltrim((string) ($file['file_path'] ?? ''), '/\\')
                                    );
                                    $approvalFileUrl = BASE_URL . '/public/' . $approvalFilePath;
                                    $approvalFileSize = (int) ($file['file_size'] ?? 0);
                                    $approvalFileSizeLabel = $approvalFileSize >= 1048576
                                        ? number_format($approvalFileSize / 1048576, 1) . ' MB'
                                        : number_format($approvalFileSize / 1024) . ' KB';
                                    ?>
                                    <li>
                                        <div class="flex items-center gap-3 rounded-xl border border-emerald-100 bg-white px-3 py-3 shadow-sm">
                                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                                          d="M7 3h7l5 5v11a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2zm7 0v5h5m-9 5h4m-4 4h6"/>
                                                </svg>
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm font-medium text-gray-800"
                                                   title="<?= htmlspecialchars($file['original_filename'] ?? '') ?>">
                                                    <?= htmlspecialchars($file['original_filename'] ?? 'Approval attachment') ?>
                                                </p>
                                                <p class="mt-0.5 text-xs text-gray-500">
                                                    <?= htmlspecialchars($approvalFileSizeLabel) ?>
                                                    <?php if (!empty($file['uploaded_at'])): ?>
                                                        · Uploaded <?= htmlspecialchars(date('M j, Y', strtotime($file['uploaded_at']))) ?>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                            <a href="<?= htmlspecialchars($approvalFileUrl, ENT_QUOTES, 'UTF-8') ?>"
                                               data-attachment-preview
                                               data-preview-url="<?= htmlspecialchars($approvalFileUrl, ENT_QUOTES, 'UTF-8') ?>"
                                               data-preview-name="<?= htmlspecialchars($file['original_filename'] ?? 'Approval attachment', ENT_QUOTES, 'UTF-8') ?>"
                                               class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800 transition hover:border-emerald-300 hover:bg-emerald-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
                                               aria-label="Preview <?= htmlspecialchars($file['original_filename'] ?? 'approval attachment', ENT_QUOTES, 'UTF-8') ?>">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                                          d="M2.25 12s3.5-6 9.75-6 9.75 6 9.75 6-3.5 6-9.75 6-9.75-6-9.75-6z"/>
                                                    <circle cx="12" cy="12" r="2.5" stroke-width="1.8"/>
                                                </svg>
                                                Preview
                                            </a>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                <?php elseif ($secondReading['action'] === 'WITHDRAWN'): ?>
                    <?php if (!empty($secondReadingDetails['date_withdrawn'])): ?>
                        <div><p class="text-xs text-emerald-700">Date Withdrawn</p><p class="text-sm text-emerald-800"><?= htmlspecialchars(date('F j, Y', strtotime($secondReadingDetails['date_withdrawn']))) ?></p></div>
                    <?php endif; ?>
                    <?php if (!empty($secondReadingDetails['withdrawal_remark'])): ?>
                        <div><p class="text-xs text-emerald-700">Remark</p><p class="text-sm text-emerald-800"><?= htmlspecialchars($secondReadingDetails['withdrawal_remark']) ?></p></div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right column: Action history -->
        <div class="lg:col-span-2 space-y-4">
            <!-- Event Timeline -->
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
            </section>            <section class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-6">
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
                                        <td class="py-2 pr-3"><?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', (string) ($assignment['phase'] ?? '')))), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="py-2 pr-3"><?= htmlspecialchars($assignment['role_name'] ?? $assignment['assigned_to_username'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="py-2 pr-3">
                                            <span class="rounded-full px-2 py-1 text-xs font-semibold <?= $decisionClass ?>">
                                                <?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $decision ?: 'Pending'))), ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td class="py-2 pr-3"><?= htmlspecialchars($assignment['accepted_by_username'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
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
    const form = document.getElementById('srForm');
    if (!form) return;

    const sections = {
        'APPROVED':                     document.getElementById('srApprovedSection'),
        'REFERRED_TO_COMMITTEE_SECOND': document.getElementById('srReferSection'),
        'DEFERRED':                     document.getElementById('srDeferredSection'),
        'WITHDRAWN':                    document.getElementById('srWithdrawnSection'),
    };

    function toggleSections() {
        const selected = form.querySelector('input[name="second_reading_action"]:checked');
        const val = selected ? selected.value : '';
        Object.entries(sections).forEach(([key, el]) => {
            if (!el) return;
            if (key === val) {
                el.classList.remove('hidden');
            } else {
                el.classList.add('hidden');
            }
        });
    }

    form.querySelectorAll('input[name="second_reading_action"]').forEach(r => r.addEventListener('change', toggleSections));
    toggleSections();
});
</script>


<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

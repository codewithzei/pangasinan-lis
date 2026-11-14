<?php
/**
 * Committee — Share to Committee Report (dedicated page)
 *
 * Variables supplied by CommitteeHearingController::shareToReportShow():
 *   $selectedDocuments    array    Hearing document rows with mixed statuses
 *                                  [{document_id, tracking_number, subject_matter,
 *                                    document_type_name, document_type_badge_color,
 *                                    status, status_badge_color,
 *                                    hearing_id, hearing_outcome, outcome_date}]
 *   $validatedIds         array    int[] of document_id values (canonical)
 *   $documentCommittees   array    [ document_id => [ ['id'=>.., 'name'=>..], ... ] ]
 *                                  Per-document committee mapping from agenda_committees.
 *   $availableCommittees  array    [{id, name}] unique committees across all selected docs
 *   $defaultCommIds       int[]    Committee IDs pre-selected on first page load
 *   $existingReports      array    [{id, report_number, report_type, created_at,
 *                                    committee_names, document_count}] eligible reports
 *   $oldMode              string   Repopulate: 'existing' | 'new'
 *   $oldExistingId        int      Repopulate: existing_report_id
 *   $oldReportNum         string   Repopulate: report_number
 *   $oldSummary           string   Repopulate: summary_of_findings
 *   $oldCommIds           int[]    Repopulate: committee_ids (or $defaultCommIds on first load)
 *   $success              string|null
 *   $error                string|null
 *   $errors               array
 */

$selectedDocuments   = $selectedDocuments   ?? [];
$validatedIds        = $validatedIds        ?? [];
$documentCommittees  = $documentCommittees  ?? [];
$availableCommittees = $availableCommittees ?? [];
$defaultCommIds      = $defaultCommIds      ?? [];
$existingReports     = $existingReports     ?? [];
$oldMode             = $oldMode             ?? 'existing';
$oldExistingId       = $oldExistingId       ?? 0;
$oldReportNum        = $oldReportNum        ?? '';
$oldSummary          = $oldSummary          ?? '';
$oldCommIds          = $oldCommIds          ?? [];

// Identify documents that have no committee assigned (no agenda_committees row found)
$docsWithoutCommittee = [];
foreach ($selectedDocuments as $doc) {
    $docId = (int) $doc['document_id'];
    if (empty($documentCommittees[$docId])) {
        $docsWithoutCommittee[] = $doc;
    }
}

$docCount    = count($selectedDocuments);
$isExisting  = ($oldMode !== 'new');

// Build the query-string to pass document IDs back to the GET page on nav
$docIdsQs = http_build_query(['document_ids' => $validatedIds]);

ob_start();
?>

<div class="space-y-6">

    <!-- ── Page header ───────────────────────────────────────────────────── -->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-7 sm:px-8">
            <div class="relative z-10">
                <p class="text-sm font-medium text-blue-100">COMMITTEE / HEARINGS / SHARE TO REPORT</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    Share to Committee Report
                </h1>
                <p class="mt-1 text-sm text-blue-100">
                    <?= $docCount === 1
                        ? '1 hearing document selected'
                        : htmlspecialchars($docCount) . ' hearing documents selected' ?>
                </p>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-56 w-56 rounded-full bg-white/10"></div>
        </div>
    </section>

    <!-- ── Breadcrumb ─────────────────────────────────────────────────────── -->
    <nav class="flex items-center gap-2 text-sm text-gray-500" aria-label="Breadcrumb">
        <a href="<?= BASE_URL ?>/committee/hearing"
           class="hover:text-emerald-700 transition">Committee Hearing</a>
        <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
          <a href="<?= BASE_URL ?>/committee/hearing?tab=all"
              class="hover:text-emerald-700 transition">All Hearings</a>
        <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="font-medium text-gray-700">Share to Committee Report</span>
    </nav>

    <!-- ── Main layout ───────────────────────────────────────────────────── -->
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

        <!-- ── Left column: selected documents summary ───────────────────── -->
        <div class="xl:col-span-1 space-y-4">

            <!-- Document count badge -->
            <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-5">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </span>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">
                            Selected Documents
                        </p>
                        <p class="text-lg font-bold text-emerald-800">
                            <?= $docCount ?> document<?= $docCount !== 1 ? 's' : '' ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Document list -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                    Documents to Share
                </h2>

                <ul class="divide-y divide-gray-100 max-h-96 overflow-y-auto -mx-1" role="list">
                    <?php foreach ($selectedDocuments as $doc): ?>
                        <?php
                        $outcomeDate = !empty($doc['outcome_date'])
                            ? date('M j, Y', strtotime($doc['outcome_date']))
                            : null;
                        ?>
                        <li class="flex flex-col gap-1 px-1 py-3" role="listitem">

                            <!-- Tracking number + type badge -->
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="font-mono text-sm font-bold text-emerald-700">
                                    <?= htmlspecialchars($doc['tracking_number'] ?? '—') ?>
                                </span>
                                <?php if (!empty($doc['document_type_name'])): ?>
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium text-white"
                                          style="background-color:<?= htmlspecialchars($doc['document_type_badge_color'] ?? '#6B7280') ?>">
                                        <?= htmlspecialchars($doc['document_type_name']) ?>
                                    </span>
                                <?php endif; ?>
                                <!-- Current document status -->
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold text-white"
                                      style="background-color:<?= htmlspecialchars($doc['status_badge_color'] ?? '#6B7280') ?>">
                                    <?= htmlspecialchars($doc['status'] ?? 'Status unavailable') ?>
                                </span>
                            </div>

                            <!-- Subject matter -->
                            <p class="text-xs text-gray-700 line-clamp-2 leading-relaxed">
                                <?= htmlspecialchars($doc['subject_matter'] ?? '—') ?>
                            </p>

                            <!-- Outcome date -->
                            <?php if ($outcomeDate): ?>
                                <p class="text-xs text-gray-400">
                                    <?= !empty($doc['hearing_outcome']) ? htmlspecialchars(ucfirst(strtolower($doc['hearing_outcome']))) : 'Hearing pending' ?>
                                    · <?= htmlspecialchars($outcomeDate) ?>
                                </p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Info notice -->
            <div class="rounded-2xl border border-amber-100 bg-amber-50 p-4">
                <div class="flex gap-2">
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-xs text-amber-800 leading-relaxed">
                        Each document keeps its current status. You can group documents with different statuses
                        in one shared Committee Report and return them to Plenary individually.
                    </p>
                </div>
            </div>

        </div><!-- /left column -->

        <!-- ── Right column: share form ──────────────────────────────────── -->
        <div class="xl:col-span-2">

            <form method="POST"
                  action="<?= BASE_URL ?>/committee/hearing/share-to-report"
                  id="shareToReportForm"
                enctype="multipart/form-data"
                  novalidate>

                <!-- Pass all document IDs as hidden fields -->
                <?php foreach ($validatedIds as $docId): ?>
                    <input type="hidden" name="document_ids[]" value="<?= (int) $docId ?>">
                <?php endforeach; ?>

                <div class="rounded-2xl border border-gray-200 bg-white p-6 space-y-6">

                    <div>
                        <h2 class="text-base font-semibold text-gray-900">
                            Share to Committee Report
                        </h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Add the selected documents to an existing report, or create a new one.
                            Fields marked <span class="text-red-500" aria-hidden="true">*</span> are required.
                        </p>
                    </div>

                    <!-- ── Mode toggle ──────────────────────────────────────── -->
                    <fieldset>
                        <legend class="block text-sm font-semibold text-gray-700 mb-3">
                            Share to which Committee Report?
                        </legend>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">

                            <!-- Existing report option -->
                            <label id="modeExistingLabel"
                                   class="flex cursor-pointer items-start gap-3 rounded-xl border-2 p-4
                                          transition hover:border-emerald-500
                                          <?= $isExisting ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200 bg-white' ?>">
                                <input type="radio" name="share_mode" value="existing"
                                       id="modeExisting"
                                       class="mt-0.5 h-4 w-4 text-emerald-600 focus:ring-emerald-500"
                                       <?= $isExisting ? 'checked' : '' ?>>
                                <div>
                                    <p class="text-sm font-semibold text-gray-800">Existing Report</p>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        Add documents to a report that already exists.
                                    </p>
                                </div>
                            </label>

                            <!-- New report option -->
                            <label id="modeNewLabel"
                                   class="flex cursor-pointer items-start gap-3 rounded-xl border-2 p-4
                                          transition hover:border-emerald-500
                                          <?= !$isExisting ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200 bg-white' ?>">
                                <input type="radio" name="share_mode" value="new"
                                       id="modeNew"
                                       class="mt-0.5 h-4 w-4 text-emerald-600 focus:ring-emerald-500"
                                       <?= !$isExisting ? 'checked' : '' ?>>
                                <div>
                                    <p class="text-sm font-semibold text-gray-800">New Report</p>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        Create a brand-new Committee Report.
                                    </p>
                                </div>
                            </label>

                        </div>
                    </fieldset>

                    <!-- ── Existing report panel ────────────────────────────── -->
                    <div id="existingReportPanel" class="<?= $isExisting ? '' : 'hidden' ?> space-y-2">
                        <label for="existing_report_id" class="block text-sm font-semibold text-gray-700">
                            Select Committee Report
                            <span class="text-red-500" aria-hidden="true">*</span>
                        </label>

                        <?php if (empty($existingReports)): ?>
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
                                No existing Committee Reports are available yet.
                                Use the <strong>New Report</strong> option instead.
                            </div>
                        <?php else: ?>
                            <select name="existing_report_id" id="existing_report_id"
                                    class="block w-full rounded-xl border border-gray-200 px-3 py-2 text-sm
                                           focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                                    aria-required="true">
                                <option value="">— Choose a report —</option>
                                <?php foreach ($existingReports as $rpt): ?>
                                    <?php
                                    $isSelected  = ((int) $rpt['id'] === $oldExistingId);
                                    $typeLabel   = $rpt['report_type'] === 'JOINT_COMMITTEE_REPORT' ? 'Joint CR' : 'CR';
                                    $committees  = !empty($rpt['committee_names']) ? ' — ' . $rpt['committee_names'] : '';
                                    $docCount    = (int) $rpt['document_count'];
                                    $docLabel    = $docCount > 0
                                        ? ' (' . $docCount . ' doc' . ($docCount !== 1 ? 's' : '') . ')'
                                        : '';
                                    ?>
                                    <option value="<?= (int) $rpt['id'] ?>"
                                            <?= $isSelected ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($typeLabel . ' #' . $rpt['report_number'] . $committees . $docLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <!-- ── New report panel ─────────────────────────────────── -->
                    <div id="newReportPanel" class="<?= !$isExisting ? '' : 'hidden' ?> space-y-5">

                        <!-- Committee Referral -->
                        <div>
                            <p class="block text-sm font-semibold text-gray-700 mb-1">
                                Committee Referral
                                <span class="text-red-500" aria-hidden="true">*</span>
                            </p>
                            <p class="text-xs text-gray-400 mb-2">
                                Pre-populated from the committee(s) assigned to each selected document's agenda.
                                Select at least one. Multiple committees produce a
                                <strong>Joint Committee Report</strong>.
                            </p>

                            <?php if (!empty($docsWithoutCommittee)): ?>
                                <!-- Warning: one or more documents have no committee on their agenda -->
                                <div class="mb-3 rounded-xl border border-amber-200 bg-amber-50 p-3 space-y-1"
                                     role="alert">
                                    <p class="text-xs font-semibold text-amber-800">
                                        The following document(s) have no committee assigned to their agenda.
                                        They will not contribute committees to this report:
                                    </p>
                                    <ul class="list-disc pl-4 space-y-0.5">
                                        <?php foreach ($docsWithoutCommittee as $missingDoc): ?>
                                            <li class="text-xs text-amber-700">
                                                <span class="font-mono font-semibold">
                                                    <?= htmlspecialchars($missingDoc['tracking_number'] ?? '—') ?>
                                                </span>
                                                <?php if (!empty($missingDoc['subject_matter'])): ?>
                                                    — <?= htmlspecialchars(mb_strimwidth($missingDoc['subject_matter'], 0, 80, '…')) ?>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <?php if (empty($availableCommittees)): ?>
                                <p class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700">
                                    No committees found for the selected documents.
                                    Ensure each document's agenda has at least one committee assigned.
                                </p>
                            <?php else: ?>
                                <div class="rounded-xl border border-gray-200 p-3 space-y-1.5 max-h-48 overflow-y-auto">
                                    <?php foreach ($availableCommittees as $comm): ?>
                                        <?php $isChecked = in_array((int) $comm['id'], $oldCommIds, true); ?>
                                        <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5
                                                      hover:bg-gray-50 transition text-sm text-gray-700">
                                            <input type="checkbox"
                                                   name="committee_ids[]"
                                                   value="<?= (int) $comm['id'] ?>"
                                                   class="share-committee-cb h-4 w-4 rounded border-gray-300
                                                          text-emerald-600 focus:ring-emerald-500"
                                                   <?= $isChecked ? 'checked' : '' ?>>
                                            <?= htmlspecialchars($comm['name']) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <!-- Live report-type indicator -->
                                <p id="reportTypeIndicator"
                                   class="mt-2 text-xs font-semibold text-emerald-700 hidden"
                                   aria-live="polite"></p>
                            <?php endif; ?>
                        </div>

                        <!-- Report Number -->
                        <div>
                            <label for="report_number" class="block text-sm font-semibold text-gray-700 mb-1">
                                Report Number
                                <span class="text-red-500" aria-hidden="true">*</span>
                            </label>
                            <input type="text"
                                   name="report_number"
                                   id="report_number"
                                   value="<?= htmlspecialchars($oldReportNum) ?>"
                                   class="block w-full rounded-xl border border-gray-200 px-3 py-2 text-sm
                                          focus:border-emerald-500 focus:outline-none focus:ring-2
                                          focus:ring-emerald-500/20"
                                   placeholder="e.g. CR-001-2026"
                                   maxlength="50"
                                   autocomplete="off">
                        </div>

                        <!-- Summary of Findings -->
                        <div>
                            <label for="summary_of_findings" class="block text-sm font-semibold text-gray-700 mb-1">
                                Summary of Findings
                                <span class="text-red-500" aria-hidden="true">*</span>
                            </label>
                            <textarea name="summary_of_findings"
                                      id="summary_of_findings"
                                      rows="6"
                                      class="block w-full rounded-xl border border-gray-200 px-3 py-2 text-sm
                                             text-gray-800 focus:border-emerald-500 focus:outline-none focus:ring-2
                                             focus:ring-emerald-500/20"
                                      placeholder="Summarize the committee's findings and recommendations…"
                                      maxlength="10000"><?= htmlspecialchars($oldSummary) ?></textarea>
                            <p class="mt-1 text-xs text-gray-400 text-right">
                                <span id="summaryCharCount"><?= mb_strlen($oldSummary) ?></span> / 10,000
                            </p>
                        </div>

                    </div><!-- /newReportPanel -->

                    <!-- ── Shared report attachments ───────────────────────── -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700">
                            Committee Report Attachments
                            <span class="font-normal text-gray-400">(optional)</span>
                        </label>
                        <div id="sharedReportDropZone"
                             class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2
                                    border-dashed border-gray-300 bg-gray-50 px-5 py-7 text-center transition
                                    hover:border-emerald-400 hover:bg-emerald-50/50"
                             role="button" tabindex="0" aria-label="Choose or drop report attachments">
                            <svg class="h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <p class="mt-2 text-sm font-medium text-gray-700">
                                Drop files here or
                                <button type="button" id="sharedReportBrowseBtn" class="text-emerald-700 underline hover:text-emerald-900">
                                    browse
                                </button>
                            </p>
                            <p class="mt-1 text-xs text-gray-400">
                                PDF, Word, Excel, or images · Up to 10 files · 25 MB each
                            </p>
                            <input type="file" name="attachments[]" id="sharedReportFileInput" multiple
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp" class="sr-only">
                        </div>
                        <div id="sharedReportFileErrors" class="hidden rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700" role="alert"></div>
                        <ul id="sharedReportFileList" class="space-y-2" aria-live="polite"></ul>
                        <p class="text-xs text-gray-400">
                            Uploaded files are stored as Committee Report attachments, separately from the selected documents.
                        </p>
                    </div>

                    <!-- ── Form actions ─────────────────────────────────────── -->
                    <div class="flex items-center justify-between gap-3 border-t border-gray-100 pt-5">

                        <a href="<?= BASE_URL ?>/committee/hearing?tab=all"
                           class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white
                                  px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition
                                  focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                            Back to All Hearings
                        </a>

                        <button type="submit"
                                id="submitShareBtn"
                                class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5
                                       text-sm font-semibold text-white hover:bg-emerald-700 transition
                                       focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500
                                       disabled:cursor-not-allowed disabled:opacity-50">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                            </svg>
                            Share to Report
                        </button>

                    </div>
                </div><!-- /card -->

            </form>
        </div><!-- /right column -->

    </div><!-- /grid -->
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    /* ── DOM refs ─────────────────────────────────────────────────────────── */
    const modeExisting      = document.getElementById('modeExisting');
    const modeNew           = document.getElementById('modeNew');
    const modeExistingLabel = document.getElementById('modeExistingLabel');
    const modeNewLabel      = document.getElementById('modeNewLabel');
    const existingPanel     = document.getElementById('existingReportPanel');
    const newPanel          = document.getElementById('newReportPanel');
    const typeIndicator     = document.getElementById('reportTypeIndicator');
    const summaryTextarea   = document.getElementById('summary_of_findings');
    const summaryCounter    = document.getElementById('summaryCharCount');
    const dropZone          = document.getElementById('sharedReportDropZone');
    const fileInput         = document.getElementById('sharedReportFileInput');
    const browseButton      = document.getElementById('sharedReportBrowseBtn');
    const fileList          = document.getElementById('sharedReportFileList');
    const fileErrors        = document.getElementById('sharedReportFileErrors');
    const submitBtn         = document.getElementById('submitShareBtn');
    const form              = document.getElementById('shareToReportForm');

    /* ── Mode toggle ─────────────────────────────────────────────────────── */
    function syncMode() {
        const isExisting = modeExisting && modeExisting.checked;

        if (existingPanel) {
            existingPanel.classList.toggle('hidden', !isExisting);
        }
        if (newPanel) {
            newPanel.classList.toggle('hidden', isExisting);
        }

        // Style the option cards
        if (modeExistingLabel) {
            modeExistingLabel.classList.toggle('border-emerald-500', isExisting);
            modeExistingLabel.classList.toggle('bg-emerald-50',      isExisting);
            modeExistingLabel.classList.toggle('border-gray-200',    !isExisting);
            modeExistingLabel.classList.toggle('bg-white',           !isExisting);
        }
        if (modeNewLabel) {
            modeNewLabel.classList.toggle('border-emerald-500', !isExisting);
            modeNewLabel.classList.toggle('bg-emerald-50',      !isExisting);
            modeNewLabel.classList.toggle('border-gray-200',    isExisting);
            modeNewLabel.classList.toggle('bg-white',           isExisting);
        }
    }

    if (modeExisting) modeExisting.addEventListener('change', syncMode);
    if (modeNew)      modeNew.addEventListener('change', syncMode);
    syncMode(); // apply initial state

    /* ── Committee checkboxes → live report-type indicator ───────────────── */
    function updateReportTypeIndicator() {
        if (!typeIndicator) return;
        const checked = document.querySelectorAll('.share-committee-cb:checked').length;
        if (checked === 0) {
            typeIndicator.textContent = '';
            typeIndicator.classList.add('hidden');
        } else if (checked === 1) {
            typeIndicator.textContent = '→ Committee Report';
            typeIndicator.classList.remove('hidden');
        } else {
            typeIndicator.textContent = '→ Joint Committee Report (' + checked + ' committees)';
            typeIndicator.classList.remove('hidden');
        }
    }

    document.querySelectorAll('.share-committee-cb').forEach(function (cb) {
        cb.addEventListener('change', updateReportTypeIndicator);
    });
    updateReportTypeIndicator(); // apply initial state (repopulated checkboxes)

    /* ── Summary character counter ───────────────────────────────────────── */
    if (summaryTextarea && summaryCounter) {
        summaryTextarea.addEventListener('input', function () {
            summaryCounter.textContent = this.value.length;
        });
    }

    /* ── Drag-and-drop multi-file upload ────────────────────────────────── */
    const allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
    const maxFileSize = 25 * 1024 * 1024;
    const maxFiles = 10;
    const selectedFiles = [];

    function syncFileInput() {
        const transfer = new DataTransfer();
        selectedFiles.forEach(function (file) { transfer.items.add(file); });
        fileInput.files = transfer.files;
    }

    function renderFiles() {
        fileList.replaceChildren();
        selectedFiles.forEach(function (file, index) {
            const item = document.createElement('li');
            item.className = 'flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2';

            const filename = document.createElement('span');
            filename.className = 'min-w-0 truncate text-sm text-gray-700';
            filename.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'shrink-0 rounded px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50';
            removeButton.textContent = 'Remove';
            removeButton.setAttribute('aria-label', 'Remove ' + file.name);
            removeButton.addEventListener('click', function () {
                selectedFiles.splice(index, 1);
                syncFileInput();
                renderFiles();
            });

            item.append(filename, removeButton);
            fileList.appendChild(item);
        });
    }

    function addFiles(files) {
        const errors = [];
        Array.from(files).forEach(function (file) {
            const extension = file.name.split('.').pop().toLowerCase();
            if (!allowedExtensions.includes(extension)) {
                errors.push(file.name + ': unsupported file type.');
                return;
            }
            if (file.size > maxFileSize) {
                errors.push(file.name + ': exceeds the 25 MB file limit.');
                return;
            }
            const duplicate = selectedFiles.some(function (selected) {
                return selected.name === file.name && selected.size === file.size;
            });
            if (duplicate) return;
            if (selectedFiles.length >= maxFiles) {
                errors.push('Maximum ' + maxFiles + ' files allowed.');
                return;
            }
            selectedFiles.push(file);
        });

        fileErrors.replaceChildren();
        if (errors.length) {
            fileErrors.textContent = errors.join(' ');
            fileErrors.classList.remove('hidden');
        } else {
            fileErrors.classList.add('hidden');
        }
        syncFileInput();
        renderFiles();
    }

    if (dropZone && fileInput && fileList && fileErrors) {
        dropZone.addEventListener('click', function (event) {
            if (event.target.closest('button')) return;
            fileInput.click();
        });
        dropZone.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                fileInput.click();
            }
        });
        browseButton.addEventListener('click', function (event) {
            event.stopPropagation();
            fileInput.click();
        });
        fileInput.addEventListener('change', function () {
            addFiles(this.files);
        });
        ['dragover', 'dragenter'].forEach(function (eventName) {
            dropZone.addEventListener(eventName, function (event) {
                event.preventDefault();
                dropZone.classList.add('border-emerald-500', 'bg-emerald-50');
            });
        });
        ['dragleave', 'drop'].forEach(function (eventName) {
            dropZone.addEventListener(eventName, function () {
                dropZone.classList.remove('border-emerald-500', 'bg-emerald-50');
            });
        });
        dropZone.addEventListener('drop', function (event) {
            event.preventDefault();
            if (event.dataTransfer) addFiles(event.dataTransfer.files);
        });
    }

    /* ── Client-side validation before submit ────────────────────────────── */
    if (form) {
        form.addEventListener('submit', function (e) {
            const isExisting = modeExisting && modeExisting.checked;
            let valid = true;

            if (isExisting) {
                const select = document.getElementById('existing_report_id');
                if (select && !select.value) {
                    e.preventDefault();
                    select.focus();
                    select.classList.add('border-red-400', 'ring-2', 'ring-red-400/20');
                    valid = false;
                    // Show a small inline hint
                    let hint = document.getElementById('existingSelectHint');
                    if (!hint) {
                        hint = document.createElement('p');
                        hint.id        = 'existingSelectHint';
                        hint.className = 'mt-1 text-xs font-medium text-red-600';
                        hint.textContent = 'Please select a Committee Report.';
                        select.parentNode.appendChild(hint);
                    }
                }
            } else {
                // "New" mode — committee, report number, summary
                const checkedComms = document.querySelectorAll('.share-committee-cb:checked').length;
                const reportNum    = document.getElementById('report_number');
                const summary      = document.getElementById('summary_of_findings');
                const msgs         = [];

                if (checkedComms === 0) msgs.push('At least one committee must be selected.');
                if (reportNum && !reportNum.value.trim()) msgs.push('Report Number is required.');
                if (summary && !summary.value.trim()) msgs.push('Summary of Findings is required.');

                if (msgs.length > 0) {
                    e.preventDefault();
                    valid = false;
                    // Show inline hints
                    msgs.forEach(function (msg) {
                        console.warn('[share-to-report] client validation:', msg);
                    });
                    if (checkedComms === 0) {
                        const commBox = document.querySelector('.share-committee-cb');
                        if (commBox) commBox.closest('.rounded-xl').classList.add('ring-2', 'ring-red-400/30');
                    }
                    if (reportNum && !reportNum.value.trim()) {
                        reportNum.classList.add('border-red-400');
                        reportNum.focus();
                    }
                    if (summary && !summary.value.trim()) {
                        summary.classList.add('border-red-400');
                    }
                }
            }

            if (valid && submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML =
                    '<svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">' +
                    '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
                    '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>' +
                    '</svg>' +
                    'Sharing…';
            }
        });
    }

    /* ── Clear validation highlights on user input ───────────────────────── */
    const existingSelect = document.getElementById('existing_report_id');
    if (existingSelect) {
        existingSelect.addEventListener('change', function () {
            this.classList.remove('border-red-400', 'ring-2', 'ring-red-400/20');
            const hint = document.getElementById('existingSelectHint');
            if (hint) hint.remove();
        });
    }

    const reportNumInput = document.getElementById('report_number');
    if (reportNumInput) {
        reportNumInput.addEventListener('input', function () {
            this.classList.remove('border-red-400');
        });
    }

    if (summaryTextarea) {
        summaryTextarea.addEventListener('input', function () {
            this.classList.remove('border-red-400');
        });
    }
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

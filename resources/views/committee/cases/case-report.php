<?php
/**
 * Committee Cases — Create Committee Report
 *
 * Variables supplied by CommitteeCasesController::caseReportShow():
 *   $case                  array   committee_cases row + document joins
 *   $allCommittees         array   [{id, name}] all active committees
 *   $assignedCommitteeIds  array   int[]  pre-selected from document_committees
 *   $success               string|null
 *   $error                 string|null
 *   $errors                array
 *   $old                   array   repopulation data from old_get()
 *   $pageTitle             string
 */

$case                 = $case                 ?? [];
$allCommittees        = $allCommittees        ?? [];
$assignedCommitteeIds = $assignedCommitteeIds ?? [];
$success              = $success              ?? null;
$error                = $error                ?? null;
$errors               = $errors               ?? [];
$old                  = $old                  ?? [];

$caseId      = (int) ($case['id']           ?? 0);
$documentId  = (int) ($case['document_id']  ?? 0);
$docketNumber = (string) ($case['docket_number'] ?? '');

// Repopulate from old input on validation failure
$oldCommitteeIds = !empty($old['committee_ids'])
    ? array_map('intval', (array) $old['committee_ids'])
    : $assignedCommitteeIds;

function caseReportOldVal(array $old, string $key, string $default = ''): string
{
    return htmlspecialchars((string) ($old[$key] ?? $default));
}

ob_start();
?>

<div class="space-y-6">

    <!-- Page header ---------------------------------------------------------->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-7 sm:px-8">
            <div class="relative z-10">
                <p class="text-sm font-medium text-blue-100">COMMITTEE / CASES / CREATE REPORT</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    Create Committee Report
                </h1>
                <p class="mt-1 font-mono text-sm text-blue-100">
                    <?= htmlspecialchars($docketNumber) ?>
                    &nbsp;·&nbsp;
                    <?= htmlspecialchars($case['tracking_number'] ?? '—') ?>
                </p>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-56 w-56 rounded-full bg-white/10"></div>
        </div>
    </section>

    <!-- Breadcrumb ----------------------------------------------------------->
    <nav class="flex items-center gap-2 text-sm text-gray-500" aria-label="Breadcrumb">
        <a href="<?= BASE_URL ?>/committee/cases/for-report"
           class="hover:text-primary transition">For Report</a>
        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <a href="<?= BASE_URL ?>/committee/cases/show?id=<?= $caseId ?>"
           class="hover:text-primary transition font-mono">
            <?= htmlspecialchars($docketNumber) ?>
        </a>
        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="font-medium text-gray-700">Create Committee Report</span>
    </nav>

    <!-- Flash messages ------------------------------------------------------->
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

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

        <!-- Left: case + document summary (1/3) ---------------------------->
        <div class="xl:col-span-1 space-y-4">

            <!-- Case summary -->
            <div class="rounded-2xl border border-purple-200 bg-purple-50 p-5 space-y-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-purple-600">Case</p>
                <p class="font-mono text-base font-bold text-purple-900">
                    <?= htmlspecialchars($docketNumber) ?>
                </p>
                <?php if (!empty($case['nature_of_case'])): ?>
                    <p class="text-xs text-purple-700 line-clamp-3">
                        <?= htmlspecialchars($case['nature_of_case']) ?>
                    </p>
                <?php endif; ?>
                <?php if (!empty($case['complainant_details'])): ?>
                    <div>
                        <p class="text-xs font-medium text-purple-500">Complainant</p>
                        <p class="text-xs text-purple-700">
                            <?= htmlspecialchars(mb_substr($case['complainant_details'], 0, 120)) ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Document summary -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Source Document</p>
                <p class="font-mono text-sm font-bold text-primary">
                    <?= htmlspecialchars($case['tracking_number'] ?? '—') ?>
                </p>
                <?php if (!empty($case['document_type_name'])): ?>
                    <?php $dtBadge = $case['document_type_badge_color'] ?? '#2563EB'; ?>
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                          style="background-color:<?= htmlspecialchars($dtBadge) ?>1a;
                                 color:<?= htmlspecialchars($dtBadge) ?>;">
                        <?= htmlspecialchars($case['document_type_name']) ?>
                    </span>
                <?php endif; ?>
                <p class="text-sm text-gray-700 line-clamp-3">
                    <?= htmlspecialchars($case['subject_matter'] ?? '—') ?>
                </p>
            </div>

            <!-- Outcome badge -->
            <div class="rounded-2xl border border-green-200 bg-green-50 p-5">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span class="text-xs font-semibold uppercase tracking-wide text-green-700">
                        Final Outcome: Approved
                    </span>
                </div>
                <?php if (!empty($case['finalized_at'])): ?>
                    <p class="mt-1 text-xs text-green-600">
                        <?= htmlspecialchars(date('F j, Y', strtotime($case['finalized_at']))) ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Report type notice (dynamic) -->
            <div class="rounded-2xl border border-gray-100 bg-gray-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Report Type</p>
                <p id="reportTypeLabel" class="text-sm font-bold text-gray-900">Committee Report</p>
                <p id="reportTypeDesc" class="mt-1 text-xs text-gray-500">
                    Automatically determined by the number of committees selected.
                </p>
            </div>

        </div>

        <!-- Right: report form (2/3) ---------------------------------------->
        <div class="xl:col-span-2">
            <form method="POST" action="<?= BASE_URL ?>/committee/cases/report"
                  id="reportForm" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="case_id" value="<?= $caseId ?>">

                <div class="rounded-2xl border border-gray-200 bg-white p-6 space-y-6">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Committee Report Details</h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Fields marked <span class="text-red-500">*</span> are required.
                        </p>
                    </div>

                    <!-- Committee in Charge --------------------------------->
                    <div>
                        <p class="block text-sm font-semibold text-gray-700 mb-1">
                            Committee in Charge <span class="text-red-500">*</span>
                        </p>
                        <p class="text-xs text-gray-400 mb-2">
                            Selecting multiple committees produces a
                            <strong>Joint Committee Report</strong>.
                        </p>
                        <p id="committeeError" class="mb-2 hidden text-xs font-medium text-red-600">
                            At least one committee must be selected.
                        </p>
                        <?php if (empty($allCommittees)): ?>
                            <p class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700">
                                No active committees found. Please contact the system administrator.
                            </p>
                        <?php else: ?>
                            <div class="max-h-52 overflow-y-auto rounded-xl border border-gray-200 p-3 space-y-1">
                                <?php foreach ($allCommittees as $comm): ?>
                                    <?php $isSelected = in_array((int) $comm['id'], array_map('intval', $oldCommitteeIds), true); ?>
                                    <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5
                                                  hover:bg-gray-50 transition text-sm text-gray-700">
                                        <input type="checkbox"
                                               name="committee_ids[]"
                                               value="<?= (int) $comm['id'] ?>"
                                               <?= $isSelected ? 'checked' : '' ?>
                                               class="committee-checkbox h-4 w-4 rounded border-gray-300
                                                      text-emerald-600 focus:ring-emerald-500">
                                        <?= htmlspecialchars($comm['name']) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Report Number -------------------------------------->
                    <div>
                        <label for="reportNumber"
                               class="block text-sm font-semibold text-gray-700 mb-1">
                            Report Number <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               id="reportNumber"
                               name="report_number"
                               value="<?= caseReportOldVal($old, 'report_number') ?>"
                               maxlength="50"
                               required
                               placeholder="e.g. CR-001-2026"
                               class="block w-full rounded-xl border border-gray-200 px-3 py-2 text-sm
                                      focus:border-emerald-500 focus:outline-none focus:ring-2
                                      focus:ring-emerald-500/20">
                        <p id="reportNumberError" class="mt-1 hidden text-xs font-medium text-red-600">
                            Report Number is required.
                        </p>
                    </div>

                    <!-- Summary of Findings -------------------------------->
                    <div>
                        <label for="summaryFindings"
                               class="block text-sm font-semibold text-gray-700 mb-1">
                            Summary of Findings <span class="text-red-500">*</span>
                        </label>
                        <textarea id="summaryFindings"
                                  name="summary_of_findings"
                                  rows="7"
                                  required
                                  maxlength="10000"
                                  placeholder="Summarize the committee's findings, recommendations, and conclusions…"
                                  class="block w-full rounded-xl border border-gray-200 px-3 py-2 text-sm
                                         text-gray-800 focus:border-emerald-500 focus:outline-none focus:ring-2
                                         focus:ring-emerald-500/20"><?= caseReportOldVal($old, 'summary_of_findings') ?></textarea>
                        <div class="mt-1 flex items-center justify-between">
                            <p id="summaryError" class="hidden text-xs font-medium text-red-600">
                                Summary of Findings is required.
                            </p>
                            <p class="ml-auto text-xs text-gray-400">
                                <span id="summaryCount">0</span> / 10,000
                            </p>
                        </div>
                    </div>

                    <!-- Attachments (optional) ----------------------------->
                    <div>
                        <p class="block text-sm font-semibold text-gray-700 mb-1">
                            Attachments
                            <span class="font-normal text-gray-400">
                                (optional — PDF, Word, Excel, Images; max 25 MB each)
                            </span>
                        </p>

                        <!-- Drop zone -->
                        <div id="dropZone"
                             class="relative flex flex-col items-center justify-center rounded-2xl
                                    border-2 border-dashed border-gray-300 bg-gray-50 px-6 py-10
                                    text-center transition hover:border-emerald-400
                                    hover:bg-emerald-50/50 cursor-pointer">
                            <svg class="mx-auto h-10 w-10 text-gray-400" fill="none"
                                 stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9
                                         M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <p class="mt-2 text-sm font-medium text-gray-700">
                                Drag &amp; drop files here, or
                                <button type="button" id="browseBtn"
                                        class="text-emerald-600 underline hover:text-emerald-800">
                                    browse
                                </button>
                            </p>
                            <p class="mt-1 text-xs text-gray-400">
                                Allowed: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, GIF, WEBP · Max 25 MB · Up to 10 files
                            </p>
                            <input type="file" name="attachments[]" id="fileInput"
                                   multiple
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp"
                                   class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
                        </div>

                        <!-- Error banner -->
                        <div id="fileErrorBanner"
                             class="mt-3 hidden rounded-xl border border-red-200 bg-red-50 p-3
                                    text-xs text-red-700"></div>

                        <!-- File preview list -->
                        <ul id="fileList" class="mt-3 space-y-2"></ul>
                    </div>

                    <!-- Form actions ---------------------------------------->
                    <div class="flex items-center justify-between gap-3 border-t border-gray-100 pt-5">
                        <a href="<?= BASE_URL ?>/committee/cases/show?id=<?= $caseId ?>"
                           class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200
                                  bg-white px-4 py-2 text-sm font-medium text-gray-600
                                  hover:bg-gray-50 transition">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 19l-7-7 7-7"/>
                            </svg>
                            Back to Case
                        </a>
                        <button type="submit" id="submitBtn"
                                class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-6 py-2.5
                                       text-sm font-semibold text-white hover:bg-emerald-700 transition
                                       disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1
                                         1 0 00.707-.293l5.414-5.414A1 1 0 0121 4.586V19a2 2 0 01-2 2z"/>
                            </svg>
                            Save Committee Report
                        </button>
                    </div>

                </div><!-- /card -->
            </form>
        </div>

    </div><!-- /grid -->

</div><!-- /space-y-6 -->

<script>
(function () {
    'use strict';

    // ── Report-type live indicator ─────────────────────────────────────────
    const checkboxes   = document.querySelectorAll('.committee-checkbox');
    const typeLabel    = document.getElementById('reportTypeLabel');
    const typeDesc     = document.getElementById('reportTypeDesc');
    const commErrEl    = document.getElementById('committeeError');

    function updateReportType() {
        const checked = document.querySelectorAll('.committee-checkbox:checked').length;
        if (typeLabel) {
            typeLabel.textContent = checked > 1
                ? 'Joint Committee Report'
                : 'Committee Report';
        }
        if (typeDesc) {
            typeDesc.textContent = checked > 1
                ? 'Multiple committees selected — this will be a Joint Committee Report.'
                : 'One committee selected — this will be a Committee Report.';
        }
    }

    checkboxes.forEach(function (cb) {
        cb.addEventListener('change', updateReportType);
    });
    updateReportType();

    // ── Character counter for summary ─────────────────────────────────────
    const summaryEl    = document.getElementById('summaryFindings');
    const countEl      = document.getElementById('summaryCount');
    if (summaryEl && countEl) {
        function updateCount() { countEl.textContent = summaryEl.value.length; }
        summaryEl.addEventListener('input', updateCount);
        updateCount();
    }

    // ── File upload handling ───────────────────────────────────────────────
    const dropZone    = document.getElementById('dropZone');
    const fileInput   = document.getElementById('fileInput');
    const browseBtn   = document.getElementById('browseBtn');
    const fileList    = document.getElementById('fileList');
    const errBanner   = document.getElementById('fileErrorBanner');
    const MAX_MB      = 25;
    const MAX_FILES   = 10;
    const ALLOWED_EXT = ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png','gif','webp'];

    let stagedFiles = [];

    browseBtn && browseBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        fileInput.click();
    });

    function showBannerError(msg) {
        if (!errBanner) return;
        errBanner.textContent = msg;
        errBanner.classList.remove('hidden');
        setTimeout(function () { errBanner.classList.add('hidden'); }, 6000);
    }

    function renderList() {
        if (!fileList) return;
        fileList.innerHTML = '';
        stagedFiles.forEach(function (f, i) {
            const li = document.createElement('li');
            li.className = 'flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 px-3 py-2';
            li.innerHTML =
                '<div class="flex items-center gap-2 min-w-0">' +
                '  <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                '    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>' +
                '  </svg>' +
                '  <span class="truncate text-xs font-medium text-gray-700">' + escHtml(f.name) + '</span>' +
                '  <span class="shrink-0 text-xs text-gray-400">(' + (f.size / 1024 / 1024).toFixed(2) + ' MB)</span>' +
                '</div>' +
                '<button type="button" data-idx="' + i + '" class="remove-file ml-3 text-xs font-medium text-red-500 hover:text-red-700 shrink-0">Remove</button>';
            fileList.appendChild(li);
        });

        fileList.querySelectorAll('.remove-file').forEach(function (btn) {
            btn.addEventListener('click', function () {
                stagedFiles.splice(parseInt(btn.dataset.idx, 10), 1);
                syncInput();
                renderList();
            });
        });
    }

    function escHtml(str) {
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function addFiles(newFiles) {
        for (let i = 0; i < newFiles.length; i++) {
            const f   = newFiles[i];
            const ext = (f.name.split('.').pop() || '').toLowerCase();

            if (!ALLOWED_EXT.includes(ext)) {
                showBannerError('File "' + f.name + '" is not allowed (.' + ext + ').');
                continue;
            }
            if (f.size > MAX_MB * 1024 * 1024) {
                showBannerError('File "' + f.name + '" exceeds the ' + MAX_MB + ' MB limit.');
                continue;
            }
            if (stagedFiles.length >= MAX_FILES) {
                showBannerError('Maximum ' + MAX_FILES + ' files allowed.');
                break;
            }
            stagedFiles.push(f);
        }
        syncInput();
        renderList();
    }

    function syncInput() {
        // Rebuild the FileList on the real input from stagedFiles.
        try {
            const dt = new DataTransfer();
            stagedFiles.forEach(function (f) { dt.items.add(f); });
            fileInput.files = dt.files;
        } catch (e) { /* Safari: noop — files submitted as-is */ }
    }

    fileInput && fileInput.addEventListener('change', function () {
        addFiles(this.files);
    });

    ['dragenter', 'dragover'].forEach(function (ev) {
        dropZone && dropZone.addEventListener(ev, function (e) {
            e.preventDefault();
            dropZone.classList.add('border-emerald-400', 'bg-emerald-50/50');
        });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        dropZone && dropZone.addEventListener(ev, function (e) {
            e.preventDefault();
            dropZone.classList.remove('border-emerald-400', 'bg-emerald-50/50');
        });
    });
    dropZone && dropZone.addEventListener('drop', function (e) {
        e.preventDefault();
        if (e.dataTransfer && e.dataTransfer.files.length) {
            addFiles(e.dataTransfer.files);
        }
    });

    // ── Form validation + double-submit prevention ─────────────────────────
    const form      = document.getElementById('reportForm');
    const submitBtn = document.getElementById('submitBtn');

    form && form.addEventListener('submit', function (e) {
        let valid = true;

        // Committee
        const checkedComms = document.querySelectorAll('.committee-checkbox:checked').length;
        if (commErrEl) {
            if (checkedComms === 0) {
                commErrEl.classList.remove('hidden');
                valid = false;
            } else {
                commErrEl.classList.add('hidden');
            }
        }

        // Report Number
        const rnEl    = document.getElementById('reportNumber');
        const rnErrEl = document.getElementById('reportNumberError');
        if (rnEl && rnErrEl) {
            if (!rnEl.value.trim()) {
                rnErrEl.classList.remove('hidden');
                rnEl.focus();
                valid = false;
            } else {
                rnErrEl.classList.add('hidden');
            }
        }

        // Summary
        const sfEl    = document.getElementById('summaryFindings');
        const sfErrEl = document.getElementById('summaryError');
        if (sfEl && sfErrEl) {
            if (!sfEl.value.trim()) {
                sfErrEl.classList.remove('hidden');
                if (valid) sfEl.focus();
                valid = false;
            } else {
                sfErrEl.classList.add('hidden');
            }
        }

        if (!valid) {
            e.preventDefault();
            return;
        }

        // Double-submit guard
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML =
                '<svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg"' +
                '     fill="none" viewBox="0 0 24 24">' +
                '  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
                '  <path class="opacity-75" fill="currentColor"' +
                '        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962' +
                '           7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>' +
                '</svg>' +
                '<span class="ml-2">Saving\u2026</span>';
        }
    });

}());
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

<?php
/**
 * Committee — Create Committee Report
 *
 * Variables supplied by CommitteeHearingController::reportShow():
 *   $document          array   Full document row
 *   $hearing           array   committee_hearings row
 *   $agendaCommittees  array   [{id, name}] committees from the agenda
 *   $reportType        string  'COMMITTEE_REPORT' | 'JOINT_COMMITTEE_REPORT' (auto-derived)
 *   $success           string|null
 *   $error             string|null
 *   $errors            array
 */

$document         = $document         ?? [];
$hearing          = $hearing          ?? [];
$agendaCommittees = $agendaCommittees ?? [];
$reportType       = $reportType       ?? 'COMMITTEE_REPORT';
$success          = $success          ?? null;
$error            = $error            ?? null;
$errors           = $errors           ?? [];

$documentId = (int) ($document['id']   ?? 0);
$hearingId  = (int) ($hearing['id']    ?? 0);
$agendaId   = (int) ($hearing['agenda_id'] ?? 0);

$isJoint      = $reportType === 'JOINT_COMMITTEE_REPORT';
$reportTypeLabel = $isJoint ? 'Joint Committee Report' : 'Committee Report';

// Repopulate from old input
$oldCommitteeIds = old_get()['committee_ids'] ?? array_column($agendaCommittees, 'id');

ob_start();
?>

<div class="space-y-6">

    <!-- Page header -->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-7 sm:px-8">
            <div class="relative z-10">
                <p class="text-sm font-medium text-blue-100">COMMITTEE / HEARING / COMMITTEE REPORT</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    Create <?= htmlspecialchars($reportTypeLabel) ?>
                </h1>
                <p class="mt-1 font-mono text-sm text-blue-100">
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
        <a href="<?= BASE_URL ?>/committee/hearing/show?id=<?= $documentId ?>" class="hover:text-primary transition">Record Outcome</a>
        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="font-medium text-gray-700">Committee Report</span>
    </nav>

    <!-- Flash messages -->
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

        <!-- Left: document + hearing summary -->
        <div class="xl:col-span-1 space-y-4">

            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Document</h2>
                <p class="font-mono font-bold text-emerald-700 text-sm">
                    <?= htmlspecialchars($document['tracking_number'] ?? '—') ?>
                </p>
                <?php if (!empty($document['document_type_name'])): ?>
                    <p class="text-xs text-gray-500"><?= htmlspecialchars($document['document_type_name']) ?></p>
                <?php endif; ?>
                <p class="text-sm text-gray-700 line-clamp-3">
                    <?= htmlspecialchars($document['subject_matter'] ?? '—') ?>
                </p>
            </div>

            <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-5 space-y-2">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M5 13l4 4L19 7"/>
                    </svg>
                    <span class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Hearing Approved</span>
                </div>
                <?php if (!empty($hearing['performed_at'])): ?>
                    <p class="text-xs text-emerald-700">
                        <?= htmlspecialchars(date('F j, Y', strtotime($hearing['performed_at']))) ?>
                    </p>
                <?php endif; ?>
                <?php if (!empty($hearing['remarks'])): ?>
                    <p class="text-xs text-emerald-700 italic"><?= htmlspecialchars($hearing['remarks']) ?></p>
                <?php endif; ?>
            </div>

            <!-- Auto-derived report type notice -->
            <div class="rounded-2xl border border-gray-100 bg-gray-50 p-4">
                <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">Report Type</p>
                <p class="text-sm font-bold text-gray-900"><?= htmlspecialchars($reportTypeLabel) ?></p>
                <p class="mt-1 text-xs text-gray-500">
                    Automatically determined from the number of committees in charge.
                    <?= $isJoint
                        ? 'Multiple committees selected — Joint Committee Report.'
                        : 'One committee selected — Committee Report.' ?>
                </p>
            </div>
        </div>

        <!-- Right: report form -->
        <div class="xl:col-span-2">
            <form method="POST" action="<?= BASE_URL ?>/committee/hearing/report"
                  id="reportForm" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="document_id" value="<?= $documentId ?>">
                <input type="hidden" name="hearing_id"  value="<?= $hearingId ?>">
                <input type="hidden" name="agenda_id"   value="<?= $agendaId ?>">
                <!-- Hidden: report type is derived server-side from committees count,
                     but we send it for convenience / confirmation display. -->
                <input type="hidden" name="report_type" id="reportTypeHidden" value="<?= htmlspecialchars($reportType) ?>">

                <div class="rounded-2xl border border-gray-200 bg-white p-6 space-y-6">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900"><?= htmlspecialchars($reportTypeLabel) ?> Details</h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Fields marked <span class="text-red-500">*</span> are required.
                        </p>
                    </div>

                    <!-- Committee in Charge -->
                    <div>
                        <p class="block text-sm font-semibold text-gray-700 mb-1">
                            Committee in Charge <span class="text-red-500">*</span>
                        </p>
                        <p class="text-xs text-gray-400 mb-2" id="committeeDesc">
                            Pre-populated from the agenda. Selecting multiple committees produces a
                            <strong>Joint Committee Report</strong>.
                        </p>
                        <p id="committeeError" class="mb-2 hidden text-xs font-medium text-red-600">
                            At least one committee must be selected.
                        </p>
                        <?php if (empty($agendaCommittees)): ?>
                            <p class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700">
                                No committees found for this agenda.
                            </p>
                        <?php else: ?>
                            <div class="rounded-xl border border-gray-200 p-3 space-y-2">
                                <?php foreach ($agendaCommittees as $comm): ?>
                                    <?php $isSelected = in_array((int) $comm['id'], array_map('intval', $oldCommitteeIds), true); ?>
                                    <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5
                                                  hover:bg-gray-50 transition text-sm text-gray-700">
                                        <input type="checkbox" name="committee_ids[]"
                                               value="<?= (int) $comm['id'] ?>"
                                               <?= $isSelected ? 'checked' : '' ?>
                                               class="committee-checkbox h-4 w-4 rounded border-gray-300
                                                      text-emerald-600 focus:ring-emerald-500">
                                        <?= htmlspecialchars($comm['name']) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <!-- Live report-type indicator -->
                        <p id="reportTypeIndicator"
                           class="mt-2 text-xs font-semibold text-emerald-700 hidden">
                        </p>
                    </div>

                    <!-- Report Number -->
                    <div>
                        <label for="reportNumber" class="block text-sm font-semibold text-gray-700 mb-1">
                            Report Number <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="report_number" id="reportNumber"
                               value="<?= old('report_number') ?>"
                               class="block w-full rounded-xl border border-gray-200 px-3 py-2 text-sm
                                      focus:border-emerald-500 focus:outline-none focus:ring-2
                                      focus:ring-emerald-500/20"
                               placeholder="e.g. CR-001-2026" maxlength="50" required>
                        <p id="reportNumberError" class="mt-1 hidden text-xs font-medium text-red-600">
                            Report Number is required.
                        </p>
                    </div>

                    <!-- Summary of Findings -->
                    <div>
                        <label for="summaryFindings" class="block text-sm font-semibold text-gray-700 mb-1">
                            Summary of Findings <span class="text-red-500">*</span>
                        </label>
                        <textarea name="summary_of_findings" id="summaryFindings" rows="6" required
                                  class="block w-full rounded-xl border border-gray-200 px-3 py-2 text-sm
                                         text-gray-800 focus:border-emerald-500 focus:outline-none focus:ring-2
                                         focus:ring-emerald-500/20"
                                  placeholder="Summarize the committee's findings, recommendations, and conclusions…"><?= old('summary_of_findings') ?></textarea>
                        <p id="summaryError" class="mt-1 hidden text-xs font-medium text-red-600">
                            Summary of Findings is required.
                        </p>
                    </div>

                    <!-- Attachments drag-and-drop -->
                    <div>
                        <p class="block text-sm font-semibold text-gray-700 mb-1">
                            Attachments
                            <span class="font-normal text-gray-400">(optional — PDF, Word, Excel, Images; max 25 MB each)</span>
                        </p>

                        <!-- Drop zone -->
                        <div id="dropZone"
                             class="relative flex flex-col items-center justify-center rounded-2xl border-2
                                    border-dashed border-gray-300 bg-gray-50 px-6 py-10 text-center
                                    transition hover:border-emerald-400 hover:bg-emerald-50/50 cursor-pointer">
                            <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <p class="mt-2 text-sm font-medium text-gray-700">
                                Drag &amp; drop files here, or
                                <button type="button" id="browseBtn"
                                        class="text-emerald-600 underline hover:text-emerald-800">browse</button>
                            </p>
                            <p class="mt-1 text-xs text-gray-400">
                                Allowed: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, GIF, WEBP · Max 25 MB per file · Up to 10 files
                            </p>
                            <!-- Invisible real input -->
                            <input type="file" name="attachments[]" id="fileInput"
                                   multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp"
                                   class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
                        </div>

                        <!-- File error banner -->
                        <div id="fileErrorBanner"
                             class="mt-3 hidden rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-700">
                        </div>

                        <!-- File preview list -->
                        <ul id="fileList" class="mt-3 space-y-2"></ul>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-between gap-3 border-t border-gray-100 pt-5">
                        <a href="<?= BASE_URL ?>/committee/hearing/show?id=<?= $documentId ?>"
                           class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white
                                  px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                            Back
                        </a>
                        <button type="submit" id="submitReportBtn"
                                class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5
                                       text-sm font-semibold text-white hover:bg-emerald-700 transition">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Submit Report
                        </button>
                    </div>
                </div>
            </form>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    /* ── Report type live indicator ─────────────────────────────────────── */
    const checkboxes      = document.querySelectorAll('.committee-checkbox');
    const typeIndicator   = document.getElementById('reportTypeIndicator');
    const reportTypeInput = document.getElementById('reportTypeHidden');

    function updateReportType() {
        const checked = document.querySelectorAll('.committee-checkbox:checked').length;
        let label = '';
        if (checked === 0) {
            label = '';
            if (typeIndicator) { typeIndicator.textContent = ''; typeIndicator.classList.add('hidden'); }
        } else if (checked === 1) {
            label = '→ Committee Report';
            if (reportTypeInput) reportTypeInput.value = 'COMMITTEE_REPORT';
        } else {
            label = '→ Joint Committee Report (' + checked + ' committees)';
            if (reportTypeInput) reportTypeInput.value = 'JOINT_COMMITTEE_REPORT';
        }
        if (typeIndicator && label) {
            typeIndicator.textContent = label;
            typeIndicator.classList.remove('hidden');
        }
    }

    checkboxes.forEach(function (cb) {
        cb.addEventListener('change', updateReportType);
    });
    updateReportType();

    /* ── Drag-and-drop file upload ──────────────────────────────────────── */
    const dropZone    = document.getElementById('dropZone');
    const fileInput   = document.getElementById('fileInput');
    const browseBtn   = document.getElementById('browseBtn');
    const fileList    = document.getElementById('fileList');
    const errorBanner = document.getElementById('fileErrorBanner');

    const ALLOWED_EXTS  = ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png','gif','webp'];
    const MAX_SIZE_BYTES = 25 * 1024 * 1024;
    const MAX_FILES      = 10;

    let selectedFiles = [];

    function formatBytes(b) {
        if (b < 1024) return b + ' B';
        if (b < 1024 * 1024) return (b / 1024).toFixed(1) + ' KB';
        return (b / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function getExt(name) {
        return name.split('.').pop().toLowerCase();
    }

    function validateFile(file) {
        const ext = getExt(file.name);
        if (!ALLOWED_EXTS.includes(ext)) {
            return '"' + file.name + '" has an unsupported file type.';
        }
        if (file.size > MAX_SIZE_BYTES) {
            return '"' + file.name + '" exceeds the 25 MB size limit.';
        }
        return null;
    }

    function renderFileList() {
        fileList.innerHTML = '';
        selectedFiles.forEach(function (file, idx) {
            const li = document.createElement('li');
            li.className = 'flex items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-4 py-2.5';
            li.innerHTML =
                '<div class="min-w-0 flex items-center gap-2">' +
                    '<svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" ' +
                              'd="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>' +
                    '</svg>' +
                    '<div class="min-w-0">' +
                        '<p class="truncate text-xs font-medium text-gray-800">' + file.name.replace(/</g,'&lt;') + '</p>' +
                        '<p class="text-xs text-gray-400">' + formatBytes(file.size) + '</p>' +
                    '</div>' +
                '</div>' +
                '<button type="button" data-idx="' + idx + '" ' +
                        'class="remove-file shrink-0 rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-500 transition">' +
                    '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>' +
                    '</svg>' +
                '</button>';
            fileList.appendChild(li);
        });

        // Wire remove buttons
        fileList.querySelectorAll('.remove-file').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const i = parseInt(this.dataset.idx, 10);
                selectedFiles.splice(i, 1);
                syncFileInput();
                renderFileList();
            });
        });
    }

    function showFileErrors(msgs) {
        if (!msgs || !msgs.length) {
            errorBanner.classList.add('hidden');
            return;
        }
        errorBanner.classList.remove('hidden');
        errorBanner.innerHTML = '<strong>Upload error:</strong> ' +
            msgs.map(function (m) { return m.replace(/</g, '&lt;'); }).join('<br>');
    }

    function syncFileInput() {
        // Rebuild DataTransfer so the real <input> reflects selectedFiles
        const dt = new DataTransfer();
        selectedFiles.forEach(function (f) { dt.items.add(f); });
        fileInput.files = dt.files;
    }

    function addFiles(newFiles) {
        const errs = [];
        Array.from(newFiles).forEach(function (file) {
            if (selectedFiles.length >= MAX_FILES) {
                errs.push('Maximum ' + MAX_FILES + ' files allowed.');
                return;
            }
            const err = validateFile(file);
            if (err) { errs.push(err); return; }
            // Deduplicate by name+size
            const dup = selectedFiles.some(function (f) { return f.name === file.name && f.size === file.size; });
            if (!dup) { selectedFiles.push(file); }
        });
        showFileErrors(errs);
        syncFileInput();
        renderFileList();
    }

    // Browse button triggers hidden input
    browseBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        fileInput.click();
    });

    // File input change
    fileInput.addEventListener('change', function () {
        addFiles(this.files);
        // Reset so same file can be re-added after removal
        this.value = '';
    });

    // Drag-over highlight
    ['dragover', 'dragenter'].forEach(function (ev) {
        dropZone.addEventListener(ev, function (e) {
            e.preventDefault();
            dropZone.classList.add('border-emerald-400', 'bg-emerald-50');
        });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        dropZone.addEventListener(ev, function () {
            dropZone.classList.remove('border-emerald-400', 'bg-emerald-50');
        });
    });

    dropZone.addEventListener('drop', function (e) {
        e.preventDefault();
        if (e.dataTransfer && e.dataTransfer.files) {
            addFiles(e.dataTransfer.files);
        }
    });

    /* ── Form validation ────────────────────────────────────────────────── */
    const form          = document.getElementById('reportForm');
    const reportNumIn   = document.getElementById('reportNumber');
    const summaryIn     = document.getElementById('summaryFindings');
    const submitBtn     = document.getElementById('submitReportBtn');
    const numErr        = document.getElementById('reportNumberError');
    const sumErr        = document.getElementById('summaryError');
    const commErr       = document.getElementById('committeeError');

    form.addEventListener('submit', function (e) {
        let valid = true;

        if (!reportNumIn.value.trim()) {
            e.preventDefault(); valid = false;
            numErr.classList.remove('hidden');
        } else {
            numErr.classList.add('hidden');
        }

        if (!summaryIn.value.trim()) {
            e.preventDefault(); valid = false;
            sumErr.classList.remove('hidden');
        } else {
            sumErr.classList.add('hidden');
        }

        if (document.querySelectorAll('.committee-checkbox:checked').length === 0) {
            e.preventDefault(); valid = false;
            commErr.classList.remove('hidden');
        } else {
            commErr.classList.add('hidden');
        }

        if (valid) {
            submitBtn.disabled = true;
            submitBtn.innerHTML =
                '<svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">' +
                '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
                '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>' +
                '</svg><span class="ml-2">Submitting…</span>';
        }
    });

    // Live error clearing
    if (reportNumIn && numErr) reportNumIn.addEventListener('input', function () { numErr.classList.add('hidden'); });
    if (summaryIn && sumErr)   summaryIn.addEventListener('input', function ()   { sumErr.classList.add('hidden'); });
    checkboxes.forEach(function (cb) {
        cb.addEventListener('change', function () { commErr.classList.add('hidden'); });
    });
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

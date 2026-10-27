<?php
/**
 * Committee — Submit Opinion for an Endorsement
 *
 * Variables supplied by CommitteeReferredController::opinionShow():
 *   $endorsement       array        The committee_cycle_endorsements row (with office + status join)
 *   $cycle             array        The committee_cycles row
 *   $document          array        The documents row (with status join)
 *   $office            array        opinion_offices row {id, name, abbreviation}
 *   $alreadySubmitted  bool         Whether a submission already exists for this endorsement
 *   $canSecondEndorse  bool         Whether a second endorsement can be issued
 *   $submittedFiles    array        ['opinion' => [...], 'compliance' => [...]] previously submitted
 *   $success           string|null
 *   $error             string|null
 *   $errors            array
 */

$endorsement      = $endorsement      ?? [];
$cycle            = $cycle            ?? [];
$document         = $document         ?? [];
$office           = $office           ?? [];
$alreadySubmitted = $alreadySubmitted ?? false;
$canSecondEndorse = $canSecondEndorse ?? false;
$submittedFiles   = $submittedFiles   ?? ['opinion' => [], 'compliance' => []];

// Files selected on a failed submission — browsers cannot restore file inputs,
// so we surface the names so the user knows what to re-attach.
$oldData     = function_exists('old_get') ? old_get() : [];
$oldSelected = $oldData['selected_files'] ?? ['opinion' => [], 'compliance' => []];

$endorsementId     = (int) ($endorsement['id']                ?? 0);
$documentId        = (int) ($cycle['document_id']             ?? 0);
$endorsementNumber = (int) ($endorsement['endorsement_number'] ?? 1);
// Endorsement #2 is the second (final) review: its Unfavorable outcome also
// requires Compliance documents, which is what moves the cycle to Resolve.
$isSecondReview    = $endorsementNumber > 1;

// The opinion_statuses row is the source of truth for the workflow state.
$opinionStatusName = trim((string) ($endorsement['opinion_status_name'] ?? ''));

$officeName  = $office['name']         ?? ($endorsement['office_name'] ?? '—');
$officeAbbr  = $office['abbreviation'] ?? ($endorsement['office_abbr'] ?? '');
$officeLabel = $officeAbbr !== '' ? $officeAbbr : $officeName;

ob_start();
?>

<div class="space-y-6">

    <!-- Page header -->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-7 sm:px-8">
            <div class="relative z-10">
                <p class="text-sm font-medium text-blue-100">COMMITTEE / REFERRED / FOR OPINION</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    Submit Opinion
                </h1>
                <p class="mt-1 text-sm text-blue-100">
                    Endorsement #<?= $endorsementNumber ?> &middot;
                    <?= htmlspecialchars($officeName) ?>
                </p>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-56 w-56 rounded-full bg-white/10"></div>
        </div>
    </section>

    <!-- Breadcrumb -->
    <nav class="flex items-center gap-2 text-sm text-gray-500">
        <a href="<?= BASE_URL ?>/committee/referred?tab=for_opinion" class="hover:text-primary transition">For Opinion</a>
        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="font-medium text-gray-700">Submit Opinion</span>
    </nav>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

        <!-- Left: context panel -->
        <div class="xl:col-span-1 space-y-4">

            <!-- Document card -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Document</h2>
                <p class="font-semibold text-primary text-sm"><?= htmlspecialchars($document['tracking_number'] ?? '—') ?></p>
                <p class="text-xs text-gray-700 line-clamp-3"><?= htmlspecialchars($document['subject_matter'] ?? '—') ?></p>
                <div>
                    <span class="text-xs text-gray-400">Cycle #</span>
                    <span class="text-xs font-medium text-gray-700"><?= (int) ($cycle['cycle_number'] ?? 0) ?></span>
                </div>
            </div>

            <!-- Office card -->
            <div class="rounded-2xl border border-violet-100 bg-violet-50 p-5 space-y-2">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-violet-500">Opinion Office</h2>
                <p class="text-sm font-semibold text-violet-800"><?= htmlspecialchars($officeName) ?></p>
                <?php if ($officeAbbr !== ''): ?>
                    <p class="text-xs text-violet-600">Abbreviation: <?= htmlspecialchars($officeAbbr) ?></p>
                <?php endif; ?>
                <p class="text-xs text-violet-600">Endorsement #<?= $endorsementNumber ?></p>
                <?php if ($opinionStatusName !== ''): ?>
                    <p class="text-xs text-violet-600">
                        Opinion status:
                        <span class="font-semibold text-violet-800">
                            <?= htmlspecialchars($opinionStatusName) ?>
                        </span>
                    </p>
                <?php endif; ?>
                <?php if ($endorsementNumber === 2): ?>
                    <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">
                        Second Endorsement
                    </span>
                <?php endif; ?>
            </div>

            <!-- Already submitted? -->
            <?php if ($alreadySubmitted): ?>
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    <p class="font-semibold">Opinion already submitted</p>
                    <p class="mt-1 text-xs">
                        <?php if ($opinionStatusName !== ''): ?>
                            Current status: <strong><?= htmlspecialchars($opinionStatusName) ?></strong>.
                        <?php endif; ?>
                        A submission has already been recorded for this endorsement.
                        <?php if ($canSecondEndorse): ?>
                            You may issue a second endorsement below.
                        <?php else: ?>
                            No further endorsements are permitted for this office and cycle.
                        <?php endif; ?>
                    </p>
                </div>

                <?php if ($canSecondEndorse): ?>
                    <!-- Second endorsement trigger -->
                    <div class="rounded-2xl border border-indigo-200 bg-white p-5">
                        <p class="text-sm font-semibold text-gray-800 mb-3">Issue Second Endorsement</p>
                        <p class="text-xs text-gray-500 mb-4">
                            The first opinion was Unfavorable. You may issue a second endorsement
                            to this office (endorsement #2). A third endorsement is not permitted.
                        </p>
                        <form method="POST" action="<?= BASE_URL ?>/committee/referred/second-endorse"
                              id="secondEndorseForm">
                            <input type="hidden" name="endorsement_id" value="<?= $endorsementId ?>">
                            <div class="mb-3">
                                <label for="sec_remarks" class="block text-xs font-semibold text-gray-600 mb-1">
                                    Remarks <span class="font-normal text-gray-400">(optional)</span>
                                </label>
                                <textarea name="remarks" id="sec_remarks" rows="2"
                                          class="block w-full rounded-xl border border-gray-200 px-3 py-2 text-sm
                                                 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                          placeholder="Optional remarks…"></textarea>
                            </div>
                            <button type="submit"
                                    class="w-full inline-flex items-center justify-center gap-2 rounded-xl
                                           bg-indigo-600 px-4 py-2 text-sm font-semibold text-white
                                           hover:bg-indigo-700 transition">
                                Issue Second Endorsement
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

        </div>

        <!-- Right: submit opinion form (disabled if already submitted) -->
        <div class="xl:col-span-2">
            <?php if ($alreadySubmitted): ?>
                <?php
                $subOpinion    = $submittedFiles['opinion']    ?? [];
                $subCompliance = $submittedFiles['compliance'] ?? [];
                ?>
                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-6">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gray-200">
                            <svg class="h-6 w-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-700">Opinion already recorded</p>
                            <p class="text-xs text-gray-400">
                                Submitted files are listed below and remain available for download.
                            </p>
                        </div>
                    </div>

                    <!-- Submitted Opinion files -->
                    <div class="mt-5">
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                            Opinion Documents (<?= count($subOpinion) ?>)
                        </h3>
                        <?php if (empty($subOpinion)): ?>
                            <p class="text-xs text-gray-400">No opinion files recorded.</p>
                        <?php else: ?>
                            <div class="space-y-1.5">
                                <?php foreach ($subOpinion as $f): ?>
                                    <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100
                                                bg-white px-3 py-2">
                                        <div class="min-w-0">
                                            <p class="truncate text-xs font-medium text-gray-800">
                                                <?= htmlspecialchars($f['file_name']) ?>
                                            </p>
                                            <p class="text-[11px] text-gray-400">
                                                <?= htmlspecialchars(number_format(((int) $f['file_size']) / 1024, 1)) ?> KB
                                            </p>
                                        </div>
                                        <a href="<?= BASE_URL ?>/public/<?= htmlspecialchars(ltrim($f['stored_path'], '/')) ?>"
                                           target="_blank"
                                           class="shrink-0 rounded-lg border border-gray-200 bg-white px-3 py-1.5
                                                  text-xs font-medium text-gray-600 hover:bg-gray-50 transition">
                                            Download
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Submitted Compliance files -->
                    <div class="mt-5">
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                            Compliance Documents (<?= count($subCompliance) ?>)
                        </h3>
                        <?php if (empty($subCompliance)): ?>
                            <p class="text-xs text-gray-400">No compliance files recorded.</p>
                        <?php else: ?>
                            <div class="space-y-1.5">
                                <?php foreach ($subCompliance as $f): ?>
                                    <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100
                                                bg-white px-3 py-2">
                                        <div class="min-w-0">
                                            <p class="truncate text-xs font-medium text-gray-800">
                                                <?= htmlspecialchars($f['file_name']) ?>
                                            </p>
                                            <p class="text-[11px] text-gray-400">
                                                <?= htmlspecialchars(number_format(((int) $f['file_size']) / 1024, 1)) ?> KB
                                            </p>
                                        </div>
                                        <a href="<?= BASE_URL ?>/public/<?= htmlspecialchars(ltrim($f['stored_path'], '/')) ?>"
                                           target="_blank"
                                           class="shrink-0 rounded-lg border border-gray-200 bg-white px-3 py-1.5
                                                  text-xs font-medium text-gray-600 hover:bg-gray-50 transition">
                                            Download
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <a href="<?= BASE_URL ?>/committee/referred?tab=for_opinion"
                       class="mt-6 inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white
                              px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                        Back to For Opinion
                    </a>
                </div>
            <?php else: ?>
                <form method="POST" action="<?= BASE_URL ?>/committee/referred/opinion"
                      enctype="multipart/form-data" id="opinionForm" novalidate>
                    <input type="hidden" name="endorsement_id" value="<?= $endorsementId ?>">

                    <div class="rounded-2xl border border-gray-200 bg-white p-6 space-y-6">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900">Opinion Details</h2>
                            <p class="mt-1 text-sm text-gray-500">
                                Provide the opinion type, upload the required file(s), and add optional remarks.
                            </p>
                        </div>

                        <?php if ($isSecondReview): ?>
                            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                                <p class="text-xs font-medium text-amber-800">
                                    This is the second review of this office's opinion. A
                                    <strong>Favorable</strong> opinion follows the usual favorable
                                    workflow. An <strong>Unfavorable</strong> opinion requires
                                    <strong>Compliance</strong> documents and moves the cycle to
                                    <strong>Resolve</strong> — another Compliance &rarr; Second Endorsement
                                    cycle is not permitted.
                                </p>
                            </div>
                        <?php endif; ?>

                        <!-- Opinion type -->
                        <div>
                            <p class="block text-sm font-semibold text-gray-700 mb-3">
                                Opinion Type <span class="text-red-500">*</span>
                            </p>
                            <div class="flex gap-4">
                                <?php
                                $oldType = old('opinion_type');
                                ?>
                                <label class="opinion-type-label flex-1 flex cursor-pointer items-center gap-3
                                              rounded-xl border border-gray-200 p-4 transition
                                              hover:border-green-400 hover:bg-green-50
                                              has-[:checked]:border-green-500 has-[:checked]:bg-green-50">
                                    <input type="radio" name="opinion_type" value="FAVORABLE"
                                           class="h-4 w-4 text-green-600 focus:ring-green-500 opinion-radio"
                                           <?= $oldType === 'FAVORABLE' ? 'checked' : '' ?>>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-800">Favorable</p>
                                        <p class="text-xs text-gray-500">No compliance file required.</p>
                                    </div>
                                </label>
                                <label class="opinion-type-label flex-1 flex cursor-pointer items-center gap-3
                                              rounded-xl border border-gray-200 p-4 transition
                                              hover:border-red-400 hover:bg-red-50
                                              has-[:checked]:border-red-500 has-[:checked]:bg-red-50">
                                    <input type="radio" name="opinion_type" value="UNFAVORABLE"
                                           class="h-4 w-4 text-red-600 focus:ring-red-500 opinion-radio"
                                           <?= $oldType === 'UNFAVORABLE' ? 'checked' : '' ?>>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-800">Unfavorable</p>
                                        <p class="text-xs text-gray-500">
                                            <?= $isSecondReview
                                                ? 'Compliance file required; moves the cycle to Resolve.'
                                                : 'Compliance file required.' ?>
                                        </p>
                                    </div>
                                </label>
                            </div>
                            <p id="opinionTypeError" class="mt-1.5 hidden text-xs font-medium text-red-600">
                                Please select an opinion type.
                            </p>
                        </div>

                        <!-- Opinion documents (multiple) -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">
                                Opinion Documents <span class="text-red-500">*</span>
                            </label>
                            <p class="text-xs text-gray-400 mb-2">
                                Upload one or more signed opinion documents (PDF, DOC, DOCX, XLS, XLSX, JPG, PNG —
                                max 25 MB each, up to 10 files).
                            </p>
                            <div id="opinionDropzone"
                                 class="relative flex cursor-pointer flex-col items-center justify-center rounded-xl
                                        border-2 border-dashed border-gray-300 bg-gray-50 px-4 py-7 text-center
                                        transition hover:bg-gray-100">
                                <input type="file" id="opinionFiles" name="opinion_files[]" multiple
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp" class="hidden">
                                <svg class="mb-2 h-8 w-8 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                          d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9
                                             M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>
                                <p class="text-xs text-gray-500">
                                    <span class="font-semibold text-gray-700">Click to upload</span>
                                    or drag and drop files here
                                </p>
                            </div>
                            <ul id="opinionFileList" class="mt-3 space-y-1.5"></ul>
                            <?php if (!empty($oldSelected['opinion'])): ?>
                                <div class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
                                    <p class="text-[11px] font-medium text-amber-700">
                                        Previously selected — please re-attach:
                                        <?= htmlspecialchars(implode(', ', array_column($oldSelected['opinion'], 'name'))) ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                            <p id="opinionFileError" class="mt-1.5 hidden text-xs font-medium text-red-600">
                                At least one opinion file is required.
                            </p>
                        </div>

                        <!-- Compliance documents (multiple) — shown only for an Unfavorable opinion -->
                        <div id="complianceSection" class="hidden">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">
                                Compliance Documents <span class="text-red-500">*</span>
                            </label>
                            <p class="text-xs text-gray-400 mb-2">
                                Upload one or more compliance documents (PDF, DOC, DOCX, XLS, XLSX, JPG, PNG —
                                max 25 MB each, up to 10 files). Required for an Unfavorable opinion.
                            </p>
                            <div id="complianceDropzone"
                                 class="relative flex cursor-pointer flex-col items-center justify-center rounded-xl
                                        border-2 border-dashed border-gray-300 bg-gray-50 px-4 py-7 text-center
                                        transition hover:bg-gray-100">
                                <input type="file" id="complianceFiles" name="compliance_files[]" multiple
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp" class="hidden">
                                <svg class="mb-2 h-8 w-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                          d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9
                                             M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>
                                <p class="text-xs text-gray-500">
                                    <span class="font-semibold text-gray-700">Click to upload</span>
                                    or drag and drop files here
                                </p>
                            </div>
                            <ul id="complianceFileList" class="mt-3 space-y-1.5"></ul>
                            <?php if (!empty($oldSelected['compliance'])): ?>
                                <div class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
                                    <p class="text-[11px] font-medium text-amber-700">
                                        Previously selected — please re-attach:
                                        <?= htmlspecialchars(implode(', ', array_column($oldSelected['compliance'], 'name'))) ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                            <p id="complianceFileError" class="mt-1.5 hidden text-xs font-medium text-red-600">
                                At least one compliance file is required for an Unfavorable opinion.
                            </p>
                        </div>

                        <!-- Remarks -->
                        <div>
                            <label for="opinionRemarks" class="block text-sm font-semibold text-gray-700 mb-1">
                                Remarks <span class="font-normal text-gray-400">(optional)</span>
                            </label>
                            <textarea name="remarks" id="opinionRemarks" rows="3"
                                      class="block w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                                             text-gray-800 focus:border-violet-500 focus:outline-none focus:ring-2
                                             focus:ring-violet-500/20"
                                      placeholder="Optional remarks about this opinion…"><?= old('remarks') ?></textarea>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center justify-between gap-3 border-t border-gray-100 pt-5">
                            <a href="<?= BASE_URL ?>/committee/referred?tab=for_opinion"
                               class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white
                                      px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                </svg>
                                Cancel
                            </a>
                            <button type="submit" id="submitOpinionBtn"
                                    class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-5 py-2.5
                                           text-sm font-semibold text-white hover:bg-violet-700 transition">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                </svg>
                                Submit Opinion
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form      = document.getElementById('opinionForm');
    const submitBtn = document.getElementById('submitOpinionBtn');
    const typeErr   = document.getElementById('opinionTypeError');

    // Nothing to wire up when the endorsement was already submitted.
    if (!form) return;

    const ALLOWED_EXT = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
    const MAX_BYTES   = 25 * 1024 * 1024; // 25 MB — mirrors DocumentService::validateFileUploads()
    const MAX_FILES   = 10;

    function fileExt(name) {
        const parts = String(name || '').split('.');
        return parts.length > 1 ? parts.pop().toLowerCase() : '';
    }

    function formatSize(bytes) {
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
        if (bytes >= 1024)    return (bytes / 1024).toFixed(1) + ' KB';
        return bytes + ' B';
    }

    /**
     * Wires a dropzone + its file input into a multi-file picker that keeps the
     * input's FileList in sync with the selected files (so removal actually
     * drops the file from the request payload).
     */
    function createPicker(opts) {
        const dropzone = document.getElementById(opts.dropzoneId);
        const input    = document.getElementById(opts.inputId);
        const list     = document.getElementById(opts.listId);
        const errorEl  = document.getElementById(opts.errorId);
        const selected = [];

        function render() {
            list.innerHTML = '';
            selected.forEach(function (file, index) {
                const li = document.createElement('li');
                li.className = 'flex items-center justify-between gap-2 rounded-lg border border-gray-200 ' +
                               'bg-white px-3 py-2';

                const left = document.createElement('div');
                left.className = 'flex min-w-0 items-center gap-2';

                const badge = document.createElement('span');
                badge.className = 'flex h-7 w-7 flex-none items-center justify-center rounded-md ' +
                                  'bg-indigo-50 text-[10px] font-bold uppercase text-indigo-600';
                badge.textContent = fileExt(file.name).slice(0, 4) || 'file';

                const meta = document.createElement('div');
                meta.className = 'min-w-0';

                const nameEl = document.createElement('p');
                nameEl.className = 'truncate text-xs font-medium text-gray-700';
                nameEl.textContent = file.name;
                nameEl.title = file.name;

                const sizeEl = document.createElement('p');
                sizeEl.className = 'text-[11px] text-gray-400';
                sizeEl.textContent = formatSize(file.size) + ' · ' + fileExt(file.name).toUpperCase();

                meta.appendChild(nameEl);
                meta.appendChild(sizeEl);
                left.appendChild(badge);
                left.appendChild(meta);

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'flex-none rounded-md px-2 py-1 text-xs font-medium text-red-600 ' +
                                      'transition hover:bg-red-50';
                removeBtn.textContent = 'Remove';
                removeBtn.addEventListener('click', function () {
                    selected.splice(index, 1);
                    sync();
                });

                li.appendChild(left);
                li.appendChild(removeBtn);
                list.appendChild(li);
            });

            list.classList.toggle('hidden', selected.length === 0);
        }

        function sync() {
            const dt = new DataTransfer();
            selected.forEach(function (file) { dt.items.add(file); });
            input.files = dt.files;
            render();
        }

        function setError(message) {
            if (!errorEl) return;
            if (message) {
                errorEl.textContent = message;
                errorEl.classList.remove('hidden');
            } else {
                errorEl.classList.add('hidden');
            }
        }

        function addFiles(incoming) {
            let message = '';
            Array.prototype.forEach.call(incoming, function (file) {
                if (filesLength() >= MAX_FILES) {
                    message = 'You can attach at most ' + MAX_FILES + ' files.';
                    return;
                }
                if (ALLOWED_EXT.indexOf(fileExt(file.name)) === -1) {
                    message = '\u201C' + file.name + '\u201D is not an allowed file type.';
                    return;
                }
                if (file.size > MAX_BYTES) {
                    message = '\u201C' + file.name + '\u201D exceeds the 25 MB size limit.';
                    return;
                }
                const duplicate = selected.some(function (f) {
                    return f.name === file.name && f.size === file.size;
                });
                if (duplicate) return;
                selected.push(file);
            });
            setError(message);
            sync();
        }

        function filesLength() {
            return selected.length;
        }

        input.addEventListener('change', function () {
            // Copy first — resetting the input clears its FileList.
            const picked = Array.prototype.slice.call(input.files);
            // Reset so the same file can be re-picked after removal.
            input.value = '';
            addFiles(picked);
        });

        dropzone.addEventListener('click', function () { input.click(); });

        ['dragenter', 'dragover'].forEach(function (evt) {
            dropzone.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.add('border-indigo-400', 'bg-indigo-50');
            });
        });

        ['dragleave', 'drop'].forEach(function (evt) {
            dropzone.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('border-indigo-400', 'bg-indigo-50');
            });
        });

        dropzone.addEventListener('drop', function (e) {
            const dropped = e.dataTransfer ? e.dataTransfer.files : null;
            if (dropped && dropped.length) {
                addFiles(dropped);
            }
        });

        return {
            count: function () { return selected.length; },
            setError: setError,
            clearError: function () { setError(''); }
        };
    }

    const opinionPicker = createPicker({
        dropzoneId: 'opinionDropzone',
        inputId:    'opinionFiles',
        listId:     'opinionFileList',
        errorId:    'opinionFileError'
    });

    const compliancePicker = createPicker({
        dropzoneId: 'complianceDropzone',
        inputId:    'complianceFiles',
        listId:     'complianceFileList',
        errorId:    'complianceFileError'
    });

    // --- Opinion type → compliance requirement -----------------------------
    // The Compliance upload area is offered for every Unfavorable outcome —
    // including the second (final) review, where the Compliance submission is
    // what hands the exhausted cycle over to Resolve. For any other outcome it
    // is hidden and its input disabled, so a hidden selection can never be
    // posted (the server rejects it anyway).
    const complianceSection = document.getElementById('complianceSection');
    const complianceInput   = document.getElementById('complianceFiles');

    function selectedType() {
        const checked = document.querySelector('.opinion-radio:checked');
        return checked ? checked.value : null;
    }

    function complianceOffered() {
        return selectedType() === 'UNFAVORABLE';
    }

    function toggleCompliance() {
        const offered = complianceOffered();

        if (complianceSection) complianceSection.classList.toggle('hidden', !offered);
        if (complianceInput)   complianceInput.disabled = !offered;

        if (!offered) compliancePicker.clearError();
    }

    document.querySelectorAll('.opinion-radio').forEach(function (radio) {
        radio.addEventListener('change', function () {
            toggleCompliance();
            if (typeErr) typeErr.classList.add('hidden');
        });
    });

    toggleCompliance(); // apply on load for old() repopulation

    // --- Client-side pre-flight (server re-validates every file) ------------
    form.addEventListener('submit', function (e) {
        let valid = true;

        if (!selectedType()) {
            valid = false;
            if (typeErr) typeErr.classList.remove('hidden');
        }

        if (opinionPicker.count() === 0) {
            valid = false;
            opinionPicker.setError('At least one opinion file is required.');
        }

        if (complianceOffered() && compliancePicker.count() === 0) {
            valid = false;
            compliancePicker.setError('At least one compliance file is required for an Unfavorable opinion.');
        }

        if (!valid) {
            e.preventDefault();
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML =
            '<svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">' +
            '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
            '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>' +
            '</svg><span class="ml-2">Submitting…</span>';
    });
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

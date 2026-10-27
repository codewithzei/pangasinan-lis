<?php
/**
 * Plenary Communication First Reading — detail & decision form
 *
 * Variables supplied by PlenaryReadingController::commFirstReadingShow():
 *   $document        array
 *   $plenaryDetails  array|null
 *   $committees      array   all active committees for select
 *   $categories      array   communication_categories for NOTED decision
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
$categories     = $categories     ?? [];
$events         = $events         ?? [];
$errors         = $errors         ?? [];
$old            = $old            ?? [];
$canAct         = $canAct         ?? false;

$documentId     = (int) ($document['id']               ?? 0);
$trackingNumber = $document['tracking_number']          ?? '';
$subjectMatter  = $document['subject_matter']           ?? '';
$targetDate     = $plenaryDetails['target_first_reading_date'] ?? '';

ob_start();
?>

<div class="space-y-6">

    <!-- Back + title -->
    <div class="flex items-center gap-4">
        <a href="<?= BASE_URL ?>/plenary/first-reading"
           class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Plenary / Communication First Reading</p>
            <h1 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($trackingNumber) ?></h1>
        </div>
    </div>

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

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <!-- Left: Document info -->
        <div class="lg:col-span-1 space-y-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Communication</h2>
                <div>
                    <p class="text-xs text-gray-400">Tracking Number</p>
                    <p class="text-sm font-mono font-medium text-gray-900"><?= htmlspecialchars($trackingNumber) ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Subject Matter</p>
                    <p class="text-sm text-gray-800 leading-5"><?= htmlspecialchars($subjectMatter) ?></p>
                </div>
                <?php if (!empty($document['document_type_name'])): ?>
                <div>
                    <p class="text-xs text-gray-400">Document Type</p>
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                          style="background-color: <?= htmlspecialchars($document['document_type_badge_color'] ?? '#2563EB') ?>1a;
                                 color: <?= htmlspecialchars($document['document_type_badge_color'] ?? '#2563EB') ?>;">
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
                <?php if (!empty($document['status'])): ?>
                <div>
                    <p class="text-xs text-gray-400">Status</p>
                    <?php $bc = $document['status_badge_color'] ?? '#6B7280'; ?>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold text-white"
                          style="background-color: <?= htmlspecialchars($bc) ?>;">
                        <?= htmlspecialchars($document['status'] ?? '') ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Event timeline -->
            <?php if (!empty($events)): ?>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">History</h2>
                <ol class="space-y-3">
                    <?php foreach (array_slice($events, 0, 8) as $ev): ?>
                    <li class="flex gap-3 text-xs">
                        <div class="mt-0.5 h-2 w-2 shrink-0 rounded-full bg-gray-300"></div>
                        <div>
                            <p class="font-medium text-gray-700"><?= htmlspecialchars($ev['event_type'] ?? '') ?></p>
                            <p class="text-gray-400"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($ev['created_at'] ?? 'now'))) ?></p>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right: Decision form -->
        <div class="lg:col-span-2">
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
                                      focus:ring-primary/20 sm:w-52">
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
                                'APPROVED'           => ['label' => 'Approved for Resolution under Other Matters', 'color' => 'blue', 'desc' => 'Requires Resolution Number and resolution files. Finalizes the Communication.'],
                                'REFER_TO_COMMITTEE' => ['label' => 'Refer to Committee', 'color' => 'violet', 'desc' => 'Hand off to Committee using the existing referral workflow.'],
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

                    <!-- NOTED: Communication Category -->
                    <div id="section-noted" class="hidden space-y-4">
                        <div>
                            <label for="communication_category_id" class="block text-sm font-medium text-gray-700 mb-1">
                                Communication Category <span class="text-red-500">*</span>
                            </label>
                            <select id="communication_category_id" name="communication_category_id"
                                    class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                                           text-gray-800 focus:border-primary focus:outline-none focus:ring-2
                                           focus:ring-primary/20 sm:w-72">
                                <option value="">— Select a category —</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int) $cat['id'] ?>"
                                        <?= ((int) ($old['communication_category_id'] ?? 0) === (int) $cat['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="mt-1 text-xs text-gray-400">Category for this Noted Communication.</p>
                        </div>
                    </div>

                    <!-- APPROVED: Resolution Number + Proposed Number + Files -->
                    <div id="section-approved" class="hidden space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="resolution_number" class="block text-sm font-medium text-gray-700 mb-1">
                                    Resolution Number <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="resolution_number" name="resolution_number"
                                       value="<?= htmlspecialchars($old['resolution_number'] ?? '') ?>"
                                       maxlength="100"
                                       placeholder="e.g. SP Res. No. 2026-001"
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
                                Resolution File(s) <span class="text-red-500">*</span>
                            </label>

                            <!-- Hidden real file input (accepts multiple) -->
                            <input type="file" id="resolution_files" name="resolution_files[]"
                                   multiple
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp"
                                   class="sr-only"
                                   aria-label="Resolution files"
                                   tabindex="-1">

                            <!-- Drop zone -->
                            <div id="res-drop-zone"
                                 role="button"
                                 tabindex="0"
                                 aria-label="Drop resolution files here or click to browse"
                                 class="relative flex flex-col items-center justify-center gap-2
                                        rounded-xl border-2 border-dashed border-gray-300 bg-gray-50
                                        px-6 py-8 text-center transition
                                        hover:border-primary hover:bg-primary/5
                                        focus:outline-none focus:ring-2 focus:ring-primary/40
                                        cursor-pointer">

                                <!-- Cloud-upload icon -->
                                <svg class="h-8 w-8 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>

                                <p class="text-sm font-medium text-gray-700 pointer-events-none">
                                    Drag &amp; drop files here, or
                                    <span class="text-primary underline-offset-2 hover:underline">browse</span>
                                </p>
                                <p class="text-xs text-gray-400 pointer-events-none">
                                    Up to 10 files · max 25 MB each · PDF, DOC, DOCX, XLS, XLSX, JPG, PNG
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
                                aria-label="Selected resolution files"
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
                                        : [];
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

                    <div class="flex items-center gap-3 border-t border-gray-100 pt-4">
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5
                                       text-sm font-semibold text-white hover:bg-primary/90 transition
                                       focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Record First Reading Decision
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
        'NOTED':              document.getElementById('section-noted'),
        'APPROVED':           document.getElementById('section-approved'),
        'REFER_TO_COMMITTEE': document.getElementById('section-refer'),
    };

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
            // Reset so the same file can be re-selected after removal
            fileInput.value = '';
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
        if (decision && decision.value === 'APPROVED' && dt.files.length === 0) {
            e.preventDefault();
            showError('Please attach at least one resolution file before submitting.');
            dropZone.focus();
        }
    });

}());
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

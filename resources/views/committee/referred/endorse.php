<?php
/**
 * Committee — Endorse Referred Document to Opinion Offices
 *
 * Variables supplied by CommitteeReferredController::endorseShow():
 *   $document       array        Full document row
 *   $cycle          array        Latest completed committee cycle
 *   $opinionOffices array        Active, non-deleted opinion offices
 *   $success        string|null
 *   $error          string|null
 *   $errors         array
 */

$document       = $document       ?? [];
$cycle          = $cycle          ?? [];
$opinionOffices = $opinionOffices ?? [];
$success        = $success        ?? null;
$error          = $error          ?? null;
$errors         = $errors         ?? [];

$documentId = (int) ($document['id'] ?? 0);
$cycleId    = (int) ($cycle['id']    ?? 0);

ob_start();
?>

<div class="space-y-6">

    <!-- Page header -->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-700 via-indigo-600 to-blue-700 shadow-md">
        <div class="relative px-6 py-7 sm:px-8">
            <div class="relative z-10">
                <p class="text-sm font-medium text-indigo-100">COMMITTEE / REFERRED DOCUMENTS / ENDORSE</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    Endorse to Opinion Offices
                </h1>
                <p class="mt-1 text-sm text-indigo-100">
                    Select one or more opinion offices to receive this referred document.
                </p>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-56 w-56 rounded-full bg-white/10"></div>
        </div>
    </section>

    <!-- Breadcrumb -->
    <nav class="flex items-center gap-2 text-sm text-gray-500">
        <a href="<?= BASE_URL ?>/committee/referred" class="hover:text-primary transition">Referred Documents</a>
        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="font-medium text-gray-700">Endorse</span>
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

        <!-- Left: document summary -->
        <div class="xl:col-span-1">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 space-y-4">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Document</h2>
                <div>
                    <p class="text-xs text-gray-400">Tracking Number</p>
                    <p class="mt-0.5 font-semibold text-primary"><?= htmlspecialchars($document['tracking_number'] ?? '—') ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Subject Matter</p>
                    <p class="mt-0.5 text-sm text-gray-800 line-clamp-4"><?= htmlspecialchars($document['subject_matter'] ?? '—') ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Document Type</p>
                    <p class="mt-0.5 text-sm text-gray-700"><?= htmlspecialchars($document['document_type_name'] ?? '—') ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Current Status</p>
                    <?php if (!empty($document['status'])): ?>
                        <span class="mt-1 inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold text-white"
                              style="background-color:<?= htmlspecialchars($document['status_badge_color'] ?? '#6B7280') ?>;">
                            <?= htmlspecialchars($document['status']) ?>
                        </span>
                    <?php else: ?>
                        <p class="text-sm text-gray-400">—</p>
                    <?php endif; ?>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Cycle</p>
                    <p class="mt-0.5 text-sm text-gray-700">Cycle #<?= (int) ($cycle['cycle_number'] ?? 0) ?></p>
                </div>
            </div>
        </div>

        <!-- Right: endorsement form -->
        <div class="xl:col-span-2">
            <form method="POST" action="<?= BASE_URL ?>/committee/referred/endorse"
                  id="endorseForm" novalidate>
                <input type="hidden" name="document_id" value="<?= $documentId ?>">
                <input type="hidden" name="cycle_id"    value="<?= $cycleId ?>">

                <div class="rounded-2xl border border-gray-200 bg-white p-6 space-y-6">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Select Opinion Offices</h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Check each office that should receive this document for review.
                            Hover over an abbreviation to see the full office name.
                        </p>
                    </div>

                    <?php if (empty($opinionOffices)): ?>
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                            No active opinion offices found. Please configure opinion offices in the master settings first.
                        </div>
                    <?php else: ?>
                        <!-- Office checklist -->
                        <div id="officeChecklistError" class="hidden rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                            Please select at least one opinion office.
                        </div>

                        <fieldset>
                            <legend class="sr-only">Opinion Offices</legend>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                                <?php foreach ($opinionOffices as $office): ?>
                                    <?php
                                    $abbr     = trim($office['abbreviation'] ?? '');
                                    $fullName = htmlspecialchars($office['name']);
                                    $label    = $abbr !== '' ? htmlspecialchars($abbr) : $fullName;
                                    $oldIds   = (array) (old_get()['office_ids'] ?? []);
                                    $checked  = in_array((string) $office['id'], $oldIds, true) ? 'checked' : '';
                                    ?>
                                    <label title="<?= $fullName ?>"
                                           class="office-label flex cursor-pointer items-center gap-2 rounded-xl border
                                                  border-gray-200 bg-white px-3 py-2.5 text-sm font-medium text-gray-700
                                                  transition hover:border-indigo-400 hover:bg-indigo-50
                                                  has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50
                                                  has-[:checked]:text-indigo-800">
                                        <input type="checkbox" name="office_ids[]"
                                               value="<?= (int) $office['id'] ?>"
                                               <?= $checked ?>
                                               class="h-4 w-4 rounded border-gray-300 text-indigo-600
                                                      focus:ring-indigo-500 office-checkbox">
                                        <span class="truncate"><?= $label ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>

                        <!-- Select all / clear controls -->
                        <div class="flex gap-3 text-xs">
                            <button type="button" id="selectAllBtn"
                                    class="font-medium text-indigo-600 hover:text-indigo-800 transition">
                                Select all
                            </button>
                            <span class="text-gray-300">|</span>
                            <button type="button" id="clearAllBtn"
                                    class="font-medium text-gray-500 hover:text-gray-700 transition">
                                Clear all
                            </button>
                            <span id="selectedCount" class="ml-auto text-gray-400">0 selected</span>
                        </div>
                    <?php endif; ?>

                    <!-- Remarks -->
                    <div>
                        <label for="remarks"
                               class="block text-sm font-semibold text-gray-700 mb-1">
                            Remarks
                            <span class="font-normal text-gray-400">(optional)</span>
                        </label>
                        <textarea name="remarks" id="remarks" rows="3"
                                  class="block w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                                         text-gray-800 focus:border-indigo-500 focus:outline-none focus:ring-2
                                         focus:ring-indigo-500/20"
                                  placeholder="Optional endorsement remarks…"><?= old('remarks') ?></textarea>
                    </div>

                    <!-- Action row -->
                    <div class="flex items-center justify-between gap-3 border-t border-gray-100 pt-5">
                        <a href="<?= BASE_URL ?>/committee/referred"
                           class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white
                                  px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                            Cancel
                        </a>
                        <?php if (!empty($opinionOffices)): ?>
                            <button type="submit" id="endorseBtn"
                                    class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5
                                           text-sm font-semibold text-white hover:bg-indigo-700 transition
                                           disabled:opacity-60 disabled:cursor-not-allowed">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806
                                             3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806
                                             3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946
                                             3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946
                                             3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806
                                             3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806
                                             3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946
                                             3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946
                                             3.42 3.42 0 013.138-3.138z"/>
                                </svg>
                                Endorse to Selected Offices
                            </button>
                        <?php endif; ?>
                    </div>

                </div><!-- /card -->
            </form>
        </div>

    </div><!-- /grid -->

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkboxes  = document.querySelectorAll('.office-checkbox');
    const selectAll   = document.getElementById('selectAllBtn');
    const clearAll    = document.getElementById('clearAllBtn');
    const countBadge  = document.getElementById('selectedCount');
    const errBox      = document.getElementById('officeChecklistError');
    const form        = document.getElementById('endorseForm');
    const endorseBtn  = document.getElementById('endorseBtn');

    function updateCount() {
        const n = document.querySelectorAll('.office-checkbox:checked').length;
        if (countBadge) countBadge.textContent = n + ' selected';
        if (errBox && n > 0) errBox.classList.add('hidden');
    }

    checkboxes.forEach(cb => cb.addEventListener('change', updateCount));
    updateCount();

    if (selectAll) {
        selectAll.addEventListener('click', function () {
            checkboxes.forEach(cb => { cb.checked = true; });
            updateCount();
        });
    }
    if (clearAll) {
        clearAll.addEventListener('click', function () {
            checkboxes.forEach(cb => { cb.checked = false; });
            updateCount();
        });
    }

    if (form && endorseBtn) {
        form.addEventListener('submit', function (e) {
            const checked = document.querySelectorAll('.office-checkbox:checked').length;
            if (checked === 0) {
                e.preventDefault();
                if (errBox) errBox.classList.remove('hidden');
                errBox && errBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                return;
            }
            endorseBtn.disabled = true;
            endorseBtn.innerHTML =
                '<svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">' +
                '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
                '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>' +
                '</svg><span class="ml-2">Endorsing…</span>';
        });
    }
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

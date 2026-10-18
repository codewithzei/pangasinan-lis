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

$documentId     = (int) ($document['id']              ?? 0);
$trackingNumber = $document['tracking_number']         ?? '';
$subjectMatter  = $document['subject_matter']          ?? '';
$proposedNumber = $plenaryDetails['proposed_number']   ?? '';

$existingCommitteeIds = array_map('intval', array_column($docCommittees, 'committee_id'));

// Has a final (non-deferred) second reading been recorded?
$hasFinalSecondReading = $secondReading && in_array($secondReading['action'], ['APPROVED', 'WITHDRAWN'], true);

ob_start();
?>

<div class="space-y-6">

    <div class="flex items-center gap-4">
        <a href="<?= BASE_URL ?>/plenary/second-reading"
           class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Plenary / Second Reading</p>
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

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <!-- Left column: Info panels -->
        <div class="lg:col-span-1 space-y-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Document Details</h2>
                <div><p class="text-xs text-gray-400">Tracking Number</p><p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($trackingNumber) ?></p></div>
                <div><p class="text-xs text-gray-400">Subject Matter</p><p class="text-sm text-gray-800"><?= htmlspecialchars($subjectMatter) ?></p></div>
                <?php if (!empty($document['document_type_name'])): ?>
                <div>
                    <p class="text-xs text-gray-400">Document Type</p>
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                          style="background-color: <?= htmlspecialchars($document['document_type_badge_color'] ?? '#2563EB') ?>1a; color: <?= htmlspecialchars($document['document_type_badge_color'] ?? '#2563EB') ?>;">
                        <?= htmlspecialchars($document['document_type_name']) ?>
                    </span>
                </div>
                <?php endif; ?>
                <?php if (!empty($proposedNumber)): ?>
                <div><p class="text-xs text-gray-400">Proposed No.</p><p class="text-sm font-medium text-primary"><?= htmlspecialchars($proposedNumber) ?></p></div>
                <?php endif; ?>
                <?php if (!empty($document['status_name'])): ?>
                <div>
                    <p class="text-xs text-gray-400">Current Status</p>
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold text-white"
                          style="background-color: <?= htmlspecialchars($document['status_badge_color'] ?? '#6B7280') ?>;">
                        <?= htmlspecialchars($document['status_name']) ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Authors -->
            <?php if (!empty($authors) || !empty($coAuthors)): ?>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Authorship</h2>
                <?php if (!empty($authors)): ?>
                <div><p class="text-xs text-gray-400 mb-1">Authors</p>
                    <ul class="space-y-1"><?php foreach ($authors as $a): ?><li class="text-sm text-gray-800"><?= htmlspecialchars($a['full_name']) ?></li><?php endforeach; ?></ul>
                </div><?php endif; ?>
                <?php if (!empty($coAuthors)): ?>
                <div><p class="text-xs text-gray-400 mb-1">Co-Authors</p>
                    <ul class="space-y-1"><?php foreach ($coAuthors as $a): ?><li class="text-sm text-gray-800"><?= htmlspecialchars($a['full_name']) ?></li><?php endforeach; ?></ul>
                </div><?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- First Reading history -->
            <?php if ($firstReading): ?>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-2">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">First Reading</h2>
                <div><p class="text-xs text-gray-400">Date</p><p class="text-sm text-gray-800"><?= htmlspecialchars(date('F j, Y', strtotime($firstReading['reading_date']))) ?></p></div>
                <div><p class="text-xs text-gray-400">Action</p>
                    <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">
                        <?= htmlspecialchars(str_replace('_', ' ', $firstReading['action'])) ?>
                    </span>
                </div>
                <?php if (!empty($firstReading['remarks'])): ?>
                <div><p class="text-xs text-gray-400">Remarks</p><p class="text-sm text-gray-700"><?= htmlspecialchars($firstReading['remarks']) ?></p></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Committees -->
            <?php if (!empty($docCommittees)): ?>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-2">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Committees in Charge</h2>
                <ul class="space-y-1"><?php foreach ($docCommittees as $dc): ?>
                    <li><span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700"><?= htmlspecialchars($dc['committee_name']) ?></span></li>
                <?php endforeach; ?></ul>
            </div>
            <?php endif; ?>

            <!-- Existing 2nd reading result (finalized/withdrawn) -->
            <?php if ($hasFinalSecondReading && $secondReadingDetails): ?>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 space-y-2">
                <h2 class="text-sm font-semibold text-emerald-800 uppercase tracking-wide">Second Reading Result</h2>
                <?php if ($secondReading['action'] === 'APPROVED'): ?>
                    <?php if (!empty($secondReadingDetails['approved_no'])): ?>
                        <div><p class="text-xs text-emerald-700">Approved No.</p><p class="text-sm font-bold text-emerald-900"><?= htmlspecialchars($secondReadingDetails['approved_no']) ?></p></div>
                    <?php endif; ?>
                    <?php if (!empty($secondReadingDetails['date_finalized'])): ?>
                        <div><p class="text-xs text-emerald-700">Date Finalized</p><p class="text-sm text-emerald-800"><?= htmlspecialchars(date('F j, Y', strtotime($secondReadingDetails['date_finalized']))) ?></p></div>
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

        <!-- Right column: Processing form -->
        <div class="lg:col-span-2 space-y-4">
            <?php if ($canAct && $inEligibleStatus && !$hasFinalSecondReading): ?>
            <div class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-5">Second Reading Action</h2>

                <form method="POST" action="<?= BASE_URL ?>/plenary/second-reading/store" enctype="multipart/form-data" id="srForm">
                    <input type="hidden" name="document_id" value="<?= $documentId ?>">

                    <div class="space-y-5">
                        <!-- Reading Date -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="sr_reading_date">Reading Date <span class="text-red-500">*</span></label>
                            <input type="date" id="sr_reading_date" name="reading_date"
                                   value="<?= htmlspecialchars($old['reading_date'] ?? date('Y-m-d')) ?>"
                                   class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                   required>
                        </div>

                        <!-- Action -->
                        <div>
                            <p class="block text-sm font-medium text-gray-700 mb-2">Second Reading Decision <span class="text-red-500">*</span></p>
                            <div class="space-y-3">
                                <?php
                                $srActions = [
                                    ['value' => 'APPROVED',                       'label' => 'Approved on Second Reading',                     'desc'  => 'Approve the document; enter the Approved Number and Date Finalized.'],
                                    ['value' => 'REFERRED_TO_COMMITTEE_SECOND',   'label' => 'Return / Refer to Committee',                     'desc'  => 'Refer back to Committee; document returns here after Committee workflow completes.'],
                                    ['value' => 'DEFERRED',                       'label' => 'Defer',                                           'desc'  => 'Defer the document; status becomes "Second Reading Deferred".'],
                                    ['value' => 'WITHDRAWN',                      'label' => 'Withdraw',                                        'desc'  => 'Withdraw the document from the Second Reading workflow.'],
                                ];
                                foreach ($srActions as $srA): ?>
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-4 hover:bg-gray-50 has-[:checked]:border-primary has-[:checked]:bg-blue-50 transition">
                                    <input type="radio" name="second_reading_action" value="<?= $srA['value'] ?>"
                                           <?= ($old['second_reading_action'] ?? '') === $srA['value'] ? 'checked' : '' ?>
                                           class="mt-0.5 h-4 w-4 accent-primary">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($srA['label']) ?></p>
                                        <p class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($srA['desc']) ?></p>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- APPROVED fields -->
                        <div id="srApprovedSection" class="hidden space-y-4 rounded-xl bg-gray-50 border border-gray-200 p-4">
                            <h3 class="text-sm font-semibold text-gray-700">Approval Details</h3>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1" for="approved_no">Approved Number <span class="text-red-500">*</span></label>
                                <input type="text" id="approved_no" name="approved_no"
                                       value="<?= htmlspecialchars($old['approved_no'] ?? '') ?>"
                                       placeholder="e.g. Resolution No. 001-2026"
                                       class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1" for="date_finalized">Date Finalized <span class="text-red-500">*</span></label>
                                <input type="date" id="date_finalized" name="date_finalized"
                                       value="<?= htmlspecialchars($old['date_finalized'] ?? '') ?>"
                                       class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1" for="approved_subject_matter">Subject Matter (Approved) — leave blank to use original</label>
                                <textarea id="approved_subject_matter" name="approved_subject_matter" rows="3"
                                          placeholder="Leave blank to use original subject matter…"
                                          class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"><?= htmlspecialchars($old['approved_subject_matter'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <!-- REFERRED TO COMMITTEE fields -->
                        <div id="srReferSection" class="hidden space-y-4 rounded-xl bg-gray-50 border border-gray-200 p-4">
                            <h3 class="text-sm font-semibold text-gray-700">Committee Selection <span class="text-red-500">*</span></h3>
                            <div class="max-h-52 overflow-y-auto space-y-1 rounded-xl border border-gray-200 bg-white p-3">
                                <?php foreach ($committees as $c): ?>
                                    <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-gray-50 cursor-pointer">
                                        <input type="checkbox" name="committee_ids[]" value="<?= (int) $c['id'] ?>"
                                               <?= in_array((int) $c['id'], array_map('intval', $old['committee_ids'] ?? $existingCommitteeIds), true) ? 'checked' : '' ?>
                                               class="h-4 w-4 accent-primary rounded">
                                        <span class="text-sm text-gray-800"><?= htmlspecialchars($c['name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- DEFERRED fields -->
                        <div id="srDeferredSection" class="hidden space-y-4 rounded-xl bg-gray-50 border border-gray-200 p-4">
                            <h3 class="text-sm font-semibold text-gray-700">Deferral Details</h3>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1" for="date_deferred">Date Deferred <span class="text-red-500">*</span></label>
                                <input type="date" id="date_deferred" name="date_deferred"
                                       value="<?= htmlspecialchars($old['date_deferred'] ?? '') ?>"
                                       class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1" for="compliance_file_new">Compliance Document (optional)</label>
                                <input type="file" id="compliance_file_new" name="compliance_file"
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                       class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 file:mr-3 file:rounded-lg file:border-0 file:bg-primary file:px-3 file:py-1 file:text-xs file:font-medium file:text-white hover:file:bg-blue-700">
                            </div>
                        </div>

                        <!-- WITHDRAWN fields -->
                        <div id="srWithdrawnSection" class="hidden space-y-4 rounded-xl bg-gray-50 border border-gray-200 p-4">
                            <h3 class="text-sm font-semibold text-gray-700">Withdrawal Details</h3>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1" for="date_withdrawn">Date Withdrawn <span class="text-red-500">*</span></label>
                                <input type="date" id="date_withdrawn" name="date_withdrawn"
                                       value="<?= htmlspecialchars($old['date_withdrawn'] ?? '') ?>"
                                       class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                            </div>
                        </div>

                        <!-- Common remarks -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="sr_remarks">Remarks</label>
                            <textarea id="sr_remarks" name="remarks" rows="3"
                                      placeholder="Enter remarks…"
                                      class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"><?= htmlspecialchars($old['remarks'] ?? '') ?></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-2">
                            <a href="<?= BASE_URL ?>/plenary/second-reading"
                               class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Cancel</a>
                            <button type="submit"
                                    class="rounded-xl bg-primary px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition">
                                Record Second Reading
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <?php elseif (!$canAct): ?>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                <p class="text-sm font-medium text-amber-800">You are not the owner of this document. Only the Plenary user who accepted it may process the Second Reading.</p>
            </div>
            <?php endif; ?>

            <!-- Committee-returned documents waiting for advance -->
            <?php
            $isReturnedFromCommittee = ($document['status_name'] ?? '') === 'Returned to Plenary' && $firstReading;
            ?>
            <?php if ($isReturnedFromCommittee && $canAct): ?>
            <div class="rounded-2xl border border-violet-200 bg-violet-50 p-5">
                <p class="text-sm font-semibold text-violet-800 mb-3">This document has been returned from Committee. Advance it to the Second Reading queue to begin processing.</p>
                <form method="POST" action="<?= BASE_URL ?>/plenary/second-reading/advance-from-committee">
                    <input type="hidden" name="document_id" value="<?= $documentId ?>">
                    <button type="submit" class="rounded-xl bg-violet-600 px-5 py-2 text-sm font-semibold text-white hover:bg-violet-700 transition">
                        Advance to Second Reading Queue
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <!-- Event Timeline -->
            <?php if (!empty($events)): ?>
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">Event History</h2>
                <ol class="relative border-l border-gray-200 ml-3 space-y-5">
                    <?php foreach ($events as $ev): ?>
                        <li class="ml-4">
                            <div class="absolute -left-1.5 mt-1.5 h-3 w-3 rounded-full border border-white bg-primary"></div>
                            <div class="flex flex-col">
                                <span class="text-xs font-semibold text-gray-700"><?= htmlspecialchars(str_replace('_', ' ', $ev['event_type'])) ?></span>
                                <?php if (!empty($ev['remarks'])): ?>
                                    <span class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($ev['remarks']) ?></span>
                                <?php endif; ?>
                                <span class="text-xs text-gray-400 mt-1">
                                    <?= htmlspecialchars(date('M j, Y g:i A', strtotime($ev['created_at']))) ?>
                                    <?php if (!empty($ev['performed_by_username'])): ?>by <?= htmlspecialchars($ev['performed_by_username']) ?><?php endif; ?>
                                </span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
            <?php endif; ?>
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

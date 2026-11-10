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

$documentId     = (int) ($document['id']               ?? 0);
$trackingNumber = $document['tracking_number']          ?? '';
$subjectMatter  = $document['subject_matter']           ?? '';
$targetDate     = $plenaryDetails['target_first_reading_date'] ?? '';

ob_start();
?>

<div class="space-y-6">

    <!-- Back + title -->
    <div class="flex items-center gap-4">
        <a href="<?= BASE_URL ?>/plenary/first-reading?tab=petitions"
           class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition"
           aria-label="Back to Petitions First Reading">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Plenary / Petitions First Reading</p>
            <h1 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($trackingNumber) ?></h1>
        </div>
    </div>

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

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <!-- Left: Document info -->
        <div class="lg:col-span-1 space-y-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Document</h2>
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
                <?php if (!empty($document['status'])): ?>
                <div>
                    <p class="text-xs text-gray-400">Status</p>
                    <?php $bc = $document['status_badge_color'] ?? '#6B7280'; ?>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold text-white"
                          style="background-color:<?= htmlspecialchars($bc) ?>;">
                        <?= htmlspecialchars($document['status'] ?? '') ?>
                    </span>
                </div>
                <?php endif; ?>
                <?php if (!empty($document['source_name'])): ?>
                <div>
                    <p class="text-xs text-gray-400">Source</p>
                    <p class="text-sm text-gray-800"><?= htmlspecialchars($document['source_name']) ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Event timeline -->
            <?php if (!empty($events)): ?>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">History</h2>
                <ol class="space-y-3" aria-label="Document history">
                    <?php foreach (array_slice($events, 0, 8) as $ev): ?>
                    <li class="flex gap-3 text-xs">
                        <div class="mt-0.5 h-2 w-2 shrink-0 rounded-full bg-gray-300" aria-hidden="true"></div>
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
                <h2 class="mb-4 text-base font-semibold text-gray-900">First Reading Decision — Petitions</h2>
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
                                      focus:ring-primary/20 sm:w-52"
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

                    <div class="flex items-center gap-3 border-t border-gray-100 pt-4">
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5
                                       text-sm font-semibold text-white hover:bg-primary/90 transition
                                       focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Record First Reading Action
                        </button>
                        <a href="<?= BASE_URL ?>/plenary/first-reading?tab=petitions"
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
// MFR relationship links — Cases First Reading shows related MFRs filed against this Case
$mfrDetailRoute = 'plenary/cases-first-reading';
require __DIR__ . '/../../partials/mfr-relationship.php';
?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

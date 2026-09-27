<?php
/**
 * Committee Communications — Record Hearing Outcome
 *
 * Variables supplied by CommitteeCommunicationsController::hearingShow():
 *   $comm    array   committee_communications row + document joins
 *   $agenda  array   the linked agendas row (with committee_names)
 *   $success string|null
 *   $error   string|null
 *   $errors  array
 */

$comm    = $comm    ?? [];
$agenda  = $agenda  ?? [];
$success = $success ?? null;
$error   = $error   ?? null;
$errors  = $errors  ?? [];

$commId     = (int)    ($comm['id']          ?? 0);
$documentId = (int)    ($comm['document_id'] ?? 0);

// Outcome options with display metadata
$outcomeOptions = [
    'APPROVED' => [
        'label'       => 'Approved',
        'description' => 'Hearing approved. A Committee Report will be required.',
        'ring'        => 'ring-emerald-500',
        'bg'          => 'bg-emerald-50',
        'text'        => 'text-emerald-800',
        'badge_bg'    => 'bg-emerald-100',
        'badge_text'  => 'text-emerald-800',
    ],
    'DEFERRED' => [
        'label'       => 'Deferred',
        'description' => 'Deferred for further review at a later session.',
        'ring'        => 'ring-amber-500',
        'bg'          => 'bg-amber-50',
        'text'        => 'text-amber-800',
        'badge_bg'    => 'bg-amber-100',
        'badge_text'  => 'text-amber-800',
    ],
    'REMANDED' => [
        'label'       => 'Remanded',
        'description' => 'Remanded back to the originating body.',
        'ring'        => 'ring-red-500',
        'bg'          => 'bg-red-50',
        'text'        => 'text-red-800',
        'badge_bg'    => 'bg-red-100',
        'badge_text'  => 'text-red-800',
    ],
    'WITHDRAWN' => [
        'label'       => 'Withdrawn',
        'description' => 'Withdrawn from the legislative process.',
        'ring'        => 'ring-gray-500',
        'bg'          => 'bg-gray-50',
        'text'        => 'text-gray-700',
        'badge_bg'    => 'bg-gray-100',
        'badge_text'  => 'text-gray-700',
    ],
];

ob_start();
?>

<div class="space-y-6">

    <!-- Back + header --------------------------------------------------------->
    <div class="flex items-center gap-4">
        <a href="<?= BASE_URL ?>/committee/communications/show?id=<?= $commId ?>"
           class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-gray-200
                  bg-white text-gray-500 hover:bg-gray-50 transition">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                Committee / Communications / Hearing
            </p>
            <h1 class="text-xl font-bold text-gray-900">Record Hearing Outcome</h1>
        </div>
    </div>

    <!-- Flash messages -------------------------------------------------------->
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

    <!-- Agenda summary ----------------------------------------------------->
    <?php if (!empty($agenda)): ?>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-emerald-600">Scheduled Agenda</p>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 text-sm">
                <div>
                    <p class="text-xs text-emerald-500 font-medium">Agenda No.</p>
                    <p class="font-semibold text-emerald-900"><?= htmlspecialchars($agenda['agenda_number'] ?? '—') ?></p>
                </div>
                <div>
                    <p class="text-xs text-emerald-500 font-medium">Type</p>
                    <p class="text-emerald-800"><?= htmlspecialchars(ucfirst(strtolower($agenda['agenda_type'] ?? ''))) ?></p>
                </div>
                <div>
                    <p class="text-xs text-emerald-500 font-medium">Date</p>
                    <p class="text-emerald-800">
                        <?= htmlspecialchars(
                            !empty($agenda['agenda_date'])
                                ? date('M j, Y', strtotime($agenda['agenda_date']))
                                : '—'
                        ) ?>
                    </p>
                </div>
                <div>
                    <p class="text-xs text-emerald-500 font-medium">Venue</p>
                    <p class="text-emerald-800 truncate"><?= htmlspecialchars($agenda['venue'] ?? '—') ?></p>
                </div>
                <?php if (!empty($agenda['committee_names'])): ?>
                    <div class="col-span-2 sm:col-span-4">
                        <p class="text-xs text-emerald-500 font-medium">Committees</p>
                        <p class="text-emerald-800"><?= htmlspecialchars($agenda['committee_names']) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Hearing form --------------------------------------------------------->
    <form method="POST" action="<?= BASE_URL ?>/committee/communications/hearing"
          id="hearingForm" novalidate>
        <input type="hidden" name="comm_id" value="<?= $commId ?>">

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

            <!-- Left: outcome selection + remarks (2/3) --------------------->
            <div class="space-y-6 xl:col-span-2">

                <!-- Outcome card ------------------------------------------->
                <div class="rounded-2xl border border-gray-200 bg-white p-6">
                    <h2 class="mb-1 text-base font-semibold text-gray-900">Hearing Outcome</h2>
                    <p class="mb-5 text-xs text-gray-400">Select the outcome of this committee hearing.</p>

                    <div class="space-y-3" id="outcomeOptions">
                        <?php foreach ($outcomeOptions as $value => $cfg): ?>
                            <label class="relative flex cursor-pointer items-start gap-4 rounded-xl border-2
                                          border-transparent bg-gray-50 p-4 transition
                                          hover:border-gray-300
                                          has-[:checked]:border-<?= explode('-', $cfg['ring'])[1] ?>-400
                                          has-[:checked]:<?= $cfg['bg'] ?>">
                                <input type="radio"
                                       name="outcome"
                                       value="<?= $value ?>"
                                       class="mt-1 h-4 w-4 text-teal-600 focus:ring-teal-500"
                                       required>
                                <div>
                                    <p class="text-sm font-semibold <?= $cfg['text'] ?>">
                                        <?= htmlspecialchars($cfg['label']) ?>
                                    </p>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        <?= htmlspecialchars($cfg['description']) ?>
                                    </p>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Remarks card ------------------------------------------->
                <div class="rounded-2xl border border-gray-200 bg-white p-6">
                    <h2 class="mb-1 text-base font-semibold text-gray-900">Remarks</h2>
                    <p class="mb-4 text-xs text-gray-400">Optional — add any notes about this hearing.</p>
                    <textarea id="remarks"
                              name="remarks"
                              rows="4"
                              maxlength="2000"
                              placeholder="Hearing notes, resolutions, or additional context…"
                              class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm
                                     focus:border-teal-500 focus:ring-1 focus:ring-teal-500"></textarea>
                </div>

                <!-- Submit ------------------------------------------------->
                <div class="flex items-center justify-end gap-3">
                    <a href="<?= BASE_URL ?>/committee/communications/show?id=<?= $commId ?>"
                       class="inline-flex items-center justify-center rounded-xl border border-gray-200
                              bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                        Cancel
                    </a>
                    <button type="button" id="confirmHearingBtn"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600
                                   px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Save Hearing Outcome
                    </button>
                </div>

            </div><!-- /left -->

            <!-- Right: info (1/3) ----------------------------------------->
            <div class="xl:col-span-1">
                <div class="sticky top-6 space-y-4">
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                        <div class="mb-2 flex items-center gap-2">
                            <svg class="h-5 w-5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0
                                         2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464
                                         0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <p class="text-sm font-semibold text-amber-800">This action is final</p>
                        </div>
                        <p class="text-xs text-amber-700 leading-relaxed">
                            Once saved, the hearing outcome cannot be changed. Only
                            <strong>Approved</strong> hearings proceed to a Committee Report.
                        </p>
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-white p-5">
                        <p class="mb-2 text-sm font-semibold text-gray-800">Outcome guide</p>
                        <dl class="space-y-2 text-xs text-gray-600">
                            <div><dt class="font-semibold text-gray-700">Approved</dt>
                                 <dd>Proceeds to Committee Report → Plenary.</dd></div>
                            <div><dt class="font-semibold text-gray-700">Deferred</dt>
                                 <dd>Held for a later session. Workflow closes here.</dd></div>
                            <div><dt class="font-semibold text-gray-700">Remanded</dt>
                                 <dd>Sent back to originating body.</dd></div>
                            <div><dt class="font-semibold text-gray-700">Withdrawn</dt>
                                 <dd>Removed from the process.</dd></div>
                        </dl>
                    </div>
                </div>
            </div>

        </div><!-- /grid -->

    </form>

    <!-- Confirmation modal -------------------------------------------------->
    <div id="hearingConfirmModal"
         class="fixed inset-0 z-[200] hidden items-center justify-center"
         role="dialog" aria-modal="true" aria-labelledby="hearingConfirmTitle">
        <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" id="hearingModalBackdrop"></div>
        <div class="relative z-10 w-full max-w-md mx-4 bg-white rounded-2xl shadow-2xl border border-gray-100">
            <div class="p-6">
                <h3 id="hearingConfirmTitle" class="text-base font-semibold text-gray-900 mb-2">
                    Confirm Hearing Outcome
                </h3>
                <p id="hearingConfirmBody" class="text-sm text-gray-600"></p>
            </div>
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button type="button" id="hearingCancelBtn"
                        class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm
                               font-medium text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="button" id="hearingOkBtn"
                        class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm
                               font-medium text-white bg-blue-600 hover:bg-blue-700 transition">
                    Confirm &amp; Save
                </button>
            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form          = document.getElementById('hearingForm');
    const confirmBtn    = document.getElementById('confirmHearingBtn');
    const modal         = document.getElementById('hearingConfirmModal');
    const backdrop      = document.getElementById('hearingModalBackdrop');
    const modalBody     = document.getElementById('hearingConfirmBody');
    const okBtn         = document.getElementById('hearingOkBtn');
    const cancelBtn     = document.getElementById('hearingCancelBtn');

    const outcomeLabels = {
        APPROVED:  'Save hearing outcome as Approved. A Committee Report will be required.',
        DEFERRED:  'Save hearing outcome as Deferred. The workflow will close after this.',
        REMANDED:  'Save hearing outcome as Remanded. The workflow will close after this.',
        WITHDRAWN: 'Save hearing outcome as Withdrawn. The workflow will close after this.',
    };

    function openModal() {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    confirmBtn.addEventListener('click', function () {
        const selected = form.querySelector('input[name="outcome"]:checked');
        if (!selected) {
            alert('Please select a hearing outcome before saving.');
            return;
        }
        modalBody.textContent = outcomeLabels[selected.value] || 'Confirm this hearing outcome?';
        openModal();
    });

    backdrop.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);

    let submitting = false;
    okBtn.addEventListener('click', function () {
        if (submitting) return;
        submitting = true;
        okBtn.disabled = true;
        okBtn.textContent = 'Saving\u2026';
        form.submit();
    });
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

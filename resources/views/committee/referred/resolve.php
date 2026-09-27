<?php
/**
 * Committee — Resolve Committee Cycle
 *
 * Variables supplied by CommitteeReferredController::resolveShow():
 *   $document      array        Full document row
 *   $cycle         array        Latest completed committee cycle
 *   $endorsements  array        All endorsements for this cycle (with office + submission)
 *   $submissions   array        Submission rows keyed by endorsement_id
 *   $success       string|null
 *   $error         string|null
 *   $errors        array
 */

$document     = $document     ?? [];
$cycle        = $cycle        ?? [];
$endorsements = $endorsements ?? [];
$submissions  = $submissions  ?? [];
$success      = $success      ?? null;
$error        = $error        ?? null;
$errors       = $errors       ?? [];

$documentId = (int) ($document['id'] ?? 0);
$cycleId    = (int) ($cycle['id']    ?? 0);

// Summarise opinion outcomes
$totalOffices   = count(array_unique(array_column($endorsements, 'opinion_office_id')));
$favorable      = 0;
$unfavorable    = 0;
$pending        = 0;
foreach ($endorsements as $end) {
    $sub = $submissions[$end['endorsement_id'] ?? $end['id']] ?? null;
    if (!$sub) { $pending++; continue; }
    if ($sub['opinion_type'] === 'FAVORABLE')   $favorable++;
    elseif ($sub['opinion_type'] === 'UNFAVORABLE') $unfavorable++;
}

ob_start();
?>

<div class="space-y-6">

    <!-- Page header -->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-7 sm:px-8">
            <div class="relative z-10">
                <p class="text-sm font-medium text-blue-100">COMMITTEE / REFERRED / RESOLVE CYCLE</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    Resolve Committee Cycle
                </h1>
                <p class="mt-1 text-sm text-blue-100">
                    Choose how to proceed: schedule an agenda or withdraw the document.
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
        <span class="font-medium text-gray-700">Resolve</span>
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

        <!-- Left: summary -->
        <div class="xl:col-span-1 space-y-4">

            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Document</h2>
                <p class="font-semibold text-primary text-sm"><?= htmlspecialchars($document['tracking_number'] ?? '—') ?></p>
                <p class="text-xs text-gray-700 line-clamp-3"><?= htmlspecialchars($document['subject_matter'] ?? '—') ?></p>
                <div class="flex items-center gap-2 text-xs text-gray-500">
                    <span>Cycle #<?= (int) ($cycle['cycle_number'] ?? 0) ?></span>
                </div>
            </div>

            <!-- Opinion summary -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-3">Opinion Summary</h2>
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Total Offices</span>
                        <span class="font-semibold text-gray-800"><?= $totalOffices ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-green-600">Favorable</span>
                        <span class="font-semibold text-green-700"><?= $favorable ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-red-500">Unfavorable</span>
                        <span class="font-semibold text-red-600"><?= $unfavorable ?></span>
                    </div>
                    <?php if ($pending > 0): ?>
                        <div class="flex justify-between text-sm">
                            <span class="text-amber-500">Pending Submission</span>
                            <span class="font-semibold text-amber-600"><?= $pending ?></span>
                        </div>
                        <p class="text-xs text-amber-600 bg-amber-50 rounded-lg p-2 mt-2">
                            Some offices have not yet submitted their opinion. You may still resolve the cycle.
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Endorsement list -->
            <?php if (!empty($endorsements)): ?>
                <div class="rounded-2xl border border-gray-200 bg-white p-5">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-3">Office Status</h2>
                    <div class="space-y-2">
                        <?php foreach ($endorsements as $end): ?>
                            <?php
                            $sub     = $submissions[$end['endorsement_id'] ?? $end['id']] ?? null;
                            $type    = $sub['opinion_type'] ?? null;
                            $endNum  = (int) ($end['endorsement_number'] ?? 1);
                            $abbr    = $end['office_abbr'] ?? '';
                            $oName   = $end['office_name'] ?? '—';
                            $oLabel  = $abbr !== '' ? $abbr : $oName;
                            $badgeClass = match($type) {
                                'FAVORABLE'   => 'bg-green-100 text-green-700',
                                'UNFAVORABLE' => 'bg-red-100 text-red-700',
                                default       => 'bg-gray-100 text-gray-500',
                            };
                            $badgeLabel = $type ? ucfirst(strtolower($type)) : 'Pending';
                            ?>
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-medium text-gray-700 truncate max-w-[120px]"
                                      title="<?= htmlspecialchars($oName) ?>">
                                    <?= htmlspecialchars($oLabel) ?>
                                    <?php if ($endNum === 2): ?>
                                        <span class="text-[10px] text-amber-500">#2</span>
                                    <?php endif; ?>
                                </span>
                                <span class="inline-flex shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold <?= $badgeClass ?>">
                                    <?= $badgeLabel ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>

        <!-- Right: resolution form -->
        <div class="xl:col-span-2">
            <form method="POST" action="<?= BASE_URL ?>/committee/referred/resolve"
                  id="resolveForm" novalidate>
                <input type="hidden" name="document_id" value="<?= $documentId ?>">
                <input type="hidden" name="cycle_id"    value="<?= $cycleId ?>">

                <div class="rounded-2xl border border-gray-200 bg-white p-6 space-y-6">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Choose Resolution</h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Select how the Committee wishes to proceed with this document.
                            This decision is final for the current cycle.
                        </p>
                    </div>

                    <!-- Resolution choices -->
                    <div id="resolutionError" class="hidden rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                        Please select a resolution.
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                        <!-- Proceed to Agenda -->
                        <label class="resolution-label flex cursor-pointer flex-col gap-3 rounded-2xl border-2
                                      border-gray-200 p-5 transition hover:border-green-400 hover:bg-green-50
                                      has-[:checked]:border-green-500 has-[:checked]:bg-green-50">
                            <div class="flex items-center gap-3">
                                <input type="radio" name="resolution" value="PROCEED_TO_AGENDA"
                                       class="h-4 w-4 text-green-600 focus:ring-green-500 resolution-radio">
                                <span class="text-sm font-semibold text-gray-800">Proceed to Agenda</span>
                            </div>
                            <p class="text-xs text-gray-500 pl-7">
                                Schedule a committee agenda for this document. Even if some opinions were
                                Unfavorable, the Committee may choose to proceed.
                            </p>
                            <span class="ml-7 inline-flex self-start rounded-full bg-green-100 px-2 py-0.5
                                         text-xs font-semibold text-green-700">
                                → Ready for Agenda
                            </span>
                        </label>

                        <!-- Withdraw Document -->
                        <label class="resolution-label flex cursor-pointer flex-col gap-3 rounded-2xl border-2
                                      border-gray-200 p-5 transition hover:border-red-400 hover:bg-red-50
                                      has-[:checked]:border-red-500 has-[:checked]:bg-red-50">
                            <div class="flex items-center gap-3">
                                <input type="radio" name="resolution" value="WITHDRAW_DOCUMENT"
                                       class="h-4 w-4 text-red-600 focus:ring-red-500 resolution-radio">
                                <span class="text-sm font-semibold text-gray-800">Withdraw Document</span>
                            </div>
                            <p class="text-xs text-gray-500 pl-7">
                                Remove this document from the active workflow. Withdrawn documents
                                are preserved for history but cannot be re-scheduled.
                            </p>
                            <span class="ml-7 inline-flex self-start rounded-full bg-gray-100 px-2 py-0.5
                                         text-xs font-semibold text-gray-600">
                                → Withdrawn
                            </span>
                        </label>

                    </div>

                    <!-- Remarks -->
                    <div>
                        <label for="resolveRemarks" class="block text-sm font-semibold text-gray-700 mb-1">
                            Remarks / Reason
                            <span class="font-normal text-gray-400">(optional)</span>
                        </label>
                        <textarea name="remarks" id="resolveRemarks" rows="3"
                                  class="block w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                                         text-gray-800 focus:border-orange-500 focus:outline-none focus:ring-2
                                         focus:ring-orange-500/20"
                                  placeholder="Optional resolution notes…"><?= old('remarks') ?></textarea>
                    </div>

                    <!-- Warning for withdrawal -->
                    <div id="withdrawWarning" class="hidden rounded-xl border border-red-200 bg-red-50 p-4">
                        <div class="flex items-start gap-2">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <p class="text-xs font-medium text-red-700">
                                Withdrawing this document will remove it from all active workflow views.
                                This action cannot be undone for the current cycle.
                            </p>
                        </div>
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
                        <button type="submit" id="resolveBtn"
                                class="inline-flex items-center gap-2 rounded-xl bg-orange-600 px-5 py-2.5
                                       text-sm font-semibold text-white hover:bg-orange-700 transition">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M5 13l4 4L19 7"/>
                            </svg>
                            <span id="resolveBtnLabel">Confirm Resolution</span>
                        </button>
                    </div>

                </div>

            </form>
        </div>

    </div>
</div>

<!-- Confirmation modal -->
<div id="confirmModal"
     class="fixed inset-0 z-[200] hidden items-center justify-center"
     role="dialog" aria-modal="true" aria-labelledby="confirmModalTitle">
    <div id="confirmBackdrop"
         class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity opacity-0"></div>
    <div id="confirmPanel"
         class="relative z-10 w-full max-w-md mx-4 bg-white rounded-2xl shadow-2xl border border-gray-100
                transform scale-95 opacity-0 transition-all duration-200">
        <div class="p-6">
            <h3 id="confirmModalTitle" class="text-base font-semibold text-gray-900 mb-2">Confirm Resolution</h3>
            <p id="confirmModalBody" class="text-sm text-gray-600"></p>
        </div>
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
            <button type="button" id="confirmCancelBtn"
                    class="rounded-xl px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200
                           hover:bg-gray-50 transition">
                Cancel
            </button>
            <button type="button" id="confirmOkBtn"
                    class="rounded-xl px-4 py-2 text-sm font-medium text-white bg-orange-600 hover:bg-orange-700 transition">
                Confirm
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const radios          = document.querySelectorAll('.resolution-radio');
    const resolutionError = document.getElementById('resolutionError');
    const withdrawWarning = document.getElementById('withdrawWarning');
    const resolveBtnLabel = document.getElementById('resolveBtnLabel');
    const form            = document.getElementById('resolveForm');
    const resolveBtn      = document.getElementById('resolveBtn');
    const modal           = document.getElementById('confirmModal');
    const backdrop        = document.getElementById('confirmBackdrop');
    const panel           = document.getElementById('confirmPanel');
    const modalBody       = document.getElementById('confirmModalBody');
    const cancelBtn       = document.getElementById('confirmCancelBtn');
    const okBtn           = document.getElementById('confirmOkBtn');

    function getSelectedResolution() {
        const r = document.querySelector('.resolution-radio:checked');
        return r ? r.value : null;
    }

    function onResolutionChange() {
        const res = getSelectedResolution();
        if (resolutionError) resolutionError.classList.add('hidden');
        if (withdrawWarning) {
            withdrawWarning.classList.toggle('hidden', res !== 'WITHDRAW_DOCUMENT');
        }
        if (resolveBtnLabel) {
            resolveBtnLabel.textContent = res === 'WITHDRAW_DOCUMENT'
                ? 'Withdraw Document'
                : 'Proceed to Agenda';
        }
        if (resolveBtn) {
            resolveBtn.className = resolveBtn.className.replace(/bg-\w+-600|hover:bg-\w+-700/g, '');
            if (res === 'WITHDRAW_DOCUMENT') {
                resolveBtn.classList.add('bg-red-600', 'hover:bg-red-700');
            } else {
                resolveBtn.classList.add('bg-green-600', 'hover:bg-green-700');
            }
        }
    }

    radios.forEach(r => r.addEventListener('change', onResolutionChange));
    onResolutionChange();

    function openModal() {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        requestAnimationFrame(() => {
            backdrop.classList.remove('opacity-0');
            panel.classList.remove('scale-95', 'opacity-0');
            panel.classList.add('scale-100', 'opacity-100');
        });
    }

    function closeModal() {
        backdrop.classList.add('opacity-0');
        panel.classList.add('scale-95', 'opacity-0');
        panel.classList.remove('scale-100', 'opacity-100');
        setTimeout(() => { modal.classList.add('hidden'); modal.classList.remove('flex'); }, 200);
    }

    if (resolveBtn && form) {
        resolveBtn.addEventListener('click', function (e) {
            e.preventDefault();
            const res = getSelectedResolution();
            if (!res) {
                if (resolutionError) resolutionError.classList.remove('hidden');
                resolutionError && resolutionError.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                return;
            }
            const msg = res === 'WITHDRAW_DOCUMENT'
                ? 'Withdraw this document from the workflow? This action cannot be undone for the current cycle.'
                : 'Proceed to Agenda? This will mark the document as Ready for Agenda and allow you to schedule it.';
            if (modalBody) modalBody.textContent = msg;
            openModal();
        });
    }

    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    if (backdrop)  backdrop.addEventListener('click', closeModal);

    let submitting = false;
    if (okBtn && form) {
        okBtn.addEventListener('click', function () {
            if (submitting) return;
            submitting = true;
            okBtn.disabled = true;
            okBtn.innerHTML =
                '<svg class="animate-spin h-4 w-4 text-white mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">' +
                '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
                '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>' +
                '</svg>Processing…';
            form.submit();
        });
    }
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

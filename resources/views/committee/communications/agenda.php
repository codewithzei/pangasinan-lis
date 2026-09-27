<?php
/**
 * Committee Communications — Schedule Agenda
 *
 * Variables supplied by CommitteeCommunicationsController::agendaShow():
 *   $comm                  array   committee_communications row + document joins
 *   $allCommittees         array   [{id, name}] all active committees
 *   $assignedCommitteeIds  array   int[] pre-selected from document_committees
 *   $spMembers             array   all active SP members
 *   $success               string|null
 *   $error                 string|null
 *   $errors                array
 */

$comm                 = $comm                 ?? [];
$allCommittees        = $allCommittees        ?? [];
$assignedCommitteeIds = $assignedCommitteeIds ?? [];
$spMembers            = $spMembers            ?? [];
$success              = $success              ?? null;
$error                = $error                ?? null;
$errors               = $errors               ?? [];

$commId     = (int)    ($comm['id']          ?? 0);
$documentId = (int)    ($comm['document_id'] ?? 0);

$oldCommitteeIds   = old_get()['committee_ids']   ?? $assignedCommitteeIds;
$oldChairpersonIds = old_get()['chairperson_ids'] ?? [];

function agendaOld(string $key, string $default = ''): string
{
    $old = old_get();
    return htmlspecialchars((string) ($old[$key] ?? $default));
}

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
                Committee / Communications / Schedule Agenda
            </p>
            <h1 class="text-xl font-bold text-gray-900">Schedule Agenda</h1>
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
    <?php elseif (!empty($errors)): ?>
        <div class="rounded-2xl border border-red-200 bg-red-50 p-4">
            <p class="text-sm font-semibold text-red-800">Please correct the following errors:</p>
            <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-red-700">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Source document summary (compact) ------------------------------------>
    <div class="rounded-2xl border border-teal-200 bg-teal-50 p-4">
        <div class="flex items-start gap-3">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-teal-100">
                <svg class="h-5 w-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0
                             002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-teal-600">Communication</p>
                <p class="text-sm font-medium text-teal-900 truncate">
                    <?= htmlspecialchars(mb_strimwidth($comm['subject'] ?? '', 0, 80, '…')) ?>
                </p>
                <p class="text-xs text-teal-600 mt-0.5">
                    <?= htmlspecialchars($comm['tracking_number'] ?? '') ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Agenda form ---------------------------------------------------------->
    <form method="POST" action="<?= BASE_URL ?>/committee/communications/agenda"
          id="agendaForm" novalidate>
        <input type="hidden" name="comm_id" value="<?= $commId ?>">

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

            <!-- Left: form fields (2/3) ------------------------------------->
            <div class="space-y-6 xl:col-span-2">

                <!-- Agenda details card -------------------------------------->
                <div class="rounded-2xl border border-gray-200 bg-white p-6 space-y-5">
                    <h2 class="text-base font-semibold text-gray-900">Agenda Details</h2>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

                        <!-- Agenda Number ----------------------------------->
                        <div>
                            <label for="agenda_number"
                                   class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                                Agenda Number <span class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                   id="agenda_number"
                                   name="agenda_number"
                                   value="<?= agendaOld('agenda_number') ?>"
                                   maxlength="50"
                                   required
                                   placeholder="e.g. AG-2026-001"
                                   class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm
                                          focus:border-teal-500 focus:ring-1 focus:ring-teal-500">
                        </div>

                        <!-- Agenda Type -------------------------------------->
                        <div>
                            <label for="agenda_type"
                                   class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                                Agenda Type <span class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                   id="agenda_type"
                                   name="agenda_type"
                                   value="<?= agendaOld('agenda_type') ?>"
                                   maxlength="100"
                                   required
                                   placeholder="e.g. Regular, Special, Public Hearing"
                                   class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm
                                          focus:border-teal-500 focus:ring-1 focus:ring-teal-500">
                        </div>

                        <!-- Date -------------------------------------------->
                        <div>
                            <label for="agenda_date"
                                   class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                                Date <span class="text-red-500">*</span>
                            </label>
                            <input type="date"
                                   id="agenda_date"
                                   name="agenda_date"
                                   value="<?= agendaOld('agenda_date') ?>"
                                   required
                                   class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm
                                          focus:border-teal-500 focus:ring-1 focus:ring-teal-500">
                        </div>

                        <!-- Time -------------------------------------------->
                        <div>
                            <label for="agenda_time"
                                   class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                                Time <span class="text-red-500">*</span>
                            </label>
                            <input type="time"
                                   id="agenda_time"
                                   name="agenda_time"
                                   value="<?= agendaOld('agenda_time') ?>"
                                   required
                                   class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm
                                          focus:border-teal-500 focus:ring-1 focus:ring-teal-500">
                        </div>

                        <!-- Venue ------------------------------------------->
                        <div class="sm:col-span-2">
                            <label for="venue"
                                   class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                                Venue <span class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                   id="venue"
                                   name="venue"
                                   value="<?= agendaOld('venue') ?>"
                                   maxlength="255"
                                   required
                                   placeholder="e.g. Committee Room 1, SP Building"
                                   class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm
                                          focus:border-teal-500 focus:ring-1 focus:ring-teal-500">
                        </div>

                        <!-- Remarks (optional) ------------------------------>
                        <div class="sm:col-span-2">
                            <label for="remarks"
                                   class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                                Remarks
                                <span class="ml-1 font-normal text-gray-400 normal-case tracking-normal">(optional)</span>
                            </label>
                            <textarea id="remarks"
                                      name="remarks"
                                      rows="3"
                                      maxlength="1000"
                                      placeholder="Any additional notes about this agenda…"
                                      class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm
                                             focus:border-teal-500 focus:ring-1 focus:ring-teal-500"><?= agendaOld('remarks') ?></textarea>
                        </div>

                    </div>
                </div>

                <!-- Committees card ----------------------------------------->
                <div class="rounded-2xl border border-gray-200 bg-white p-6">
                    <h2 class="mb-1 text-base font-semibold text-gray-900">Committees in Charge</h2>
                    <p class="mb-4 text-xs text-gray-400">Select at least one committee.</p>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <?php foreach ($allCommittees as $committee): ?>
                            <?php
                            $cId       = (int) $committee['id'];
                            $checked   = in_array($cId, array_map('intval', (array) $oldCommitteeIds), true);
                            ?>
                            <label class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50
                                          p-3 cursor-pointer hover:bg-teal-50 hover:border-teal-300 transition
                                          has-[:checked]:bg-teal-50 has-[:checked]:border-teal-400">
                                <input type="checkbox"
                                       name="committee_ids[]"
                                       value="<?= $cId ?>"
                                       <?= $checked ? 'checked' : '' ?>
                                       class="h-4 w-4 rounded text-teal-600 focus:ring-teal-500">
                                <span class="text-sm font-medium text-gray-800">
                                    <?= htmlspecialchars($committee['name']) ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                        <?php if (empty($allCommittees)): ?>
                            <p class="col-span-2 text-sm text-gray-400">No active committees found.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Chairpersons card (optional) ---------------------------->
                <?php if (!empty($spMembers)): ?>
                    <div class="rounded-2xl border border-gray-200 bg-white p-6">
                        <h2 class="mb-1 text-base font-semibold text-gray-900">Chairpersons</h2>
                        <p class="mb-4 text-xs text-gray-400">Optional. Select the presiding SP members.</p>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <?php foreach ($spMembers as $sp): ?>
                                <?php
                                $spId    = (int) $sp['sp_member_id'];
                                $checked = in_array($spId, array_map('intval', (array) $oldChairpersonIds), true);
                                $fullName = trim(
                                    ($sp['last_name'] ?? '') . ', ' .
                                    ($sp['first_name'] ?? '') .
                                    (!empty($sp['middle_name']) ? ' ' . $sp['middle_name'] : '') .
                                    (!empty($sp['suffix']) ? ', ' . $sp['suffix'] : '')
                                );
                                ?>
                                <label class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50
                                              p-3 cursor-pointer hover:bg-teal-50 hover:border-teal-300 transition
                                              has-[:checked]:bg-teal-50 has-[:checked]:border-teal-400">
                                    <input type="checkbox"
                                           name="chairperson_ids[]"
                                           value="<?= $spId ?>"
                                           <?= $checked ? 'checked' : '' ?>
                                           class="h-4 w-4 rounded text-teal-600 focus:ring-teal-500">
                                    <span class="text-sm font-medium text-gray-800">
                                        <?= htmlspecialchars($fullName) ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Submit --------------------------------------------------->
                <div class="flex items-center justify-end gap-3">
                    <a href="<?= BASE_URL ?>/committee/communications/show?id=<?= $commId ?>"
                       class="inline-flex items-center justify-center rounded-xl border border-gray-200
                              bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                        Cancel
                    </a>
                    <button type="submit" id="submitBtn"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-teal-600
                                   px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-700 transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0
                                     00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        Schedule Agenda
                    </button>
                </div>

            </div><!-- /left -->

            <!-- Right: context panel (1/3) ---------------------------------->
            <div class="xl:col-span-1">
                <div class="sticky top-6 space-y-4">
                    <div class="rounded-2xl border border-gray-200 bg-white p-5">
                        <p class="mb-3 text-sm font-semibold text-gray-800">After scheduling…</p>
                        <p class="text-xs text-gray-500 leading-relaxed">
                            The document status will change to <strong>On Going</strong>.
                            You can then record the hearing outcome from the communication detail page.
                        </p>
                    </div>
                </div>
            </div>

        </div><!-- /grid -->

    </form>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form      = document.getElementById('agendaForm');
    const submitBtn = document.getElementById('submitBtn');
    if (form && submitBtn) {
        form.addEventListener('submit', function () {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span>Scheduling\u2026</span>';
        });
    }
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

<?php
/**
 * Committee — Schedule Agenda
 *
 * Variables supplied by CommitteeReferredController::agendaShow():
 *   $document              array   Full document row
 *   $cycle                 array   Latest completed committee cycle
 *   $resolution            array   committee_opinion_resolutions row
 *   $allCommittees         array   All active committees
 *   $assignedCommitteeIds  array   Committee IDs from document_committees (preselect)
 *   $spMembers             array   All active SP members
 *   $success               string|null
 *   $error                 string|null
 *   $errors                array
 */

$document             = $document             ?? [];
$cycle                = $cycle                ?? [];
$resolution           = $resolution           ?? [];
$allCommittees        = $allCommittees        ?? [];
$assignedCommitteeIds = $assignedCommitteeIds ?? [];
$spMembers            = $spMembers            ?? [];
$success              = $success              ?? null;
$error                = $error                ?? null;
$errors               = $errors               ?? [];

$documentId = (int) ($document['id'] ?? 0);
$cycleId    = (int) ($cycle['id']    ?? 0);

// Old input helper for repopulation
$oldCommitteeIds   = old_get()['committee_ids']   ?? $assignedCommitteeIds;
$oldChairpersonIds = old_get()['chairperson_ids'] ?? [];

ob_start();
?>

<div class="space-y-6">

    <!-- Page header -->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-700 via-emerald-600 to-teal-700 shadow-md">
        <div class="relative px-6 py-7 sm:px-8">
            <div class="relative z-10">
                <p class="text-sm font-medium text-emerald-100">COMMITTEE / REFERRED / SCHEDULE AGENDA</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    Schedule Agenda
                </h1>
                <p class="mt-1 text-sm text-emerald-100">
                    Set the date, time, venue, and participants for the committee hearing.
                </p>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-56 w-56 rounded-full bg-white/10"></div>
        </div>
    </section>

    <!-- Breadcrumb -->
    <nav class="flex items-center gap-2 text-sm text-gray-500">
        <a href="<?= BASE_URL ?>/committee/referred?tab=ready_for_agenda" class="hover:text-primary transition">Ready for Agenda</a>
        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="font-medium text-gray-700">Schedule Agenda</span>
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
        <div class="xl:col-span-1 space-y-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Document</h2>
                <p class="font-semibold text-primary text-sm"><?= htmlspecialchars($document['tracking_number'] ?? '—') ?></p>
                <p class="text-xs text-gray-700 line-clamp-4"><?= htmlspecialchars($document['subject_matter'] ?? '—') ?></p>
                <?php if (!empty($document['document_type_name'])): ?>
                    <p class="text-xs text-gray-500"><?= htmlspecialchars($document['document_type_name']) ?></p>
                <?php endif; ?>
                <div class="flex items-center gap-2 text-xs text-gray-500">
                    <span>Cycle #<?= (int) ($cycle['cycle_number'] ?? 0) ?></span>
                </div>
            </div>

            <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-5">
                <div class="flex items-center gap-2 mb-2">
                    <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="text-xs font-semibold text-emerald-700 uppercase tracking-wide">Resolution</span>
                </div>
                <p class="text-xs text-emerald-800 font-medium">Proceed to Agenda</p>
                <?php if (!empty($resolution['resolved_at'])): ?>
                    <p class="text-xs text-emerald-600 mt-0.5">
                        <?= htmlspecialchars(date('M j, Y', strtotime($resolution['resolved_at']))) ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: agenda form -->
        <div class="xl:col-span-2">
            <form method="POST" action="<?= BASE_URL ?>/committee/referred/agenda"
                  id="agendaForm" novalidate>
                <input type="hidden" name="document_id" value="<?= $documentId ?>">
                <input type="hidden" name="cycle_id"    value="<?= $cycleId ?>">

                <div class="rounded-2xl border border-gray-200 bg-white p-6 space-y-6">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Agenda Details</h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Fields marked <span class="text-red-500">*</span> are required.
                        </p>
                    </div>

                    <!-- Agenda Number + Type -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="agendaNumber" class="block text-sm font-semibold text-gray-700 mb-1">
                                Agenda Number
                                <span class="font-normal text-gray-400">(optional)</span>
                            </label>
                            <input type="text" name="agenda_number" id="agendaNumber"
                                   value="<?= old('agenda_number') ?>"
                                   class="block w-full rounded-xl border border-gray-200 px-3 py-2 text-sm
                                          focus:border-emerald-500 focus:outline-none focus:ring-2
                                          focus:ring-emerald-500/20"
                                   placeholder="e.g. 001-2026">
                        </div>
                        <div>
                            <label for="agendaType" class="block text-sm font-semibold text-gray-700 mb-1">
                                Agenda Type
                                <span class="font-normal text-gray-400">(optional)</span>
                            </label>
                            <input type="text" name="agenda_type" id="agendaType"
                                   value="<?= old('agenda_type') ?>"
                                   class="block w-full rounded-xl border border-gray-200 px-3 py-2 text-sm
                                          focus:border-emerald-500 focus:outline-none focus:ring-2
                                          focus:ring-emerald-500/20"
                                   placeholder="e.g. Public Hearing">
                        </div>
                    </div>

                    <!-- Date + Time -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="agendaDate" class="block text-sm font-semibold text-gray-700 mb-1">
                                Date <span class="text-red-500">*</span>
                            </label>
                            <input type="date" name="agenda_date" id="agendaDate"
                                   value="<?= old('agenda_date') ?>"
                                   class="block w-full rounded-xl border border-gray-200 px-3 py-2 text-sm
                                          focus:border-emerald-500 focus:outline-none focus:ring-2
                                          focus:ring-emerald-500/20" required>
                            <p id="dateError" class="mt-1 hidden text-xs font-medium text-red-600">Date is required.</p>
                        </div>
                        <div>
                            <label for="agendaTime" class="block text-sm font-semibold text-gray-700 mb-1">
                                Time <span class="text-red-500">*</span>
                            </label>
                            <input type="time" name="agenda_time" id="agendaTime"
                                   value="<?= old('agenda_time') ?>"
                                   class="block w-full rounded-xl border border-gray-200 px-3 py-2 text-sm
                                          focus:border-emerald-500 focus:outline-none focus:ring-2
                                          focus:ring-emerald-500/20" required>
                            <p id="timeError" class="mt-1 hidden text-xs font-medium text-red-600">Time is required.</p>
                        </div>
                    </div>

                    <!-- Venue -->
                    <div>
                        <label for="agendaVenue" class="block text-sm font-semibold text-gray-700 mb-1">
                            Venue
                            <span class="font-normal text-gray-400">(optional)</span>
                        </label>
                        <input type="text" name="venue" id="agendaVenue"
                               value="<?= old('venue') ?>"
                               class="block w-full rounded-xl border border-gray-200 px-3 py-2 text-sm
                                      focus:border-emerald-500 focus:outline-none focus:ring-2
                                      focus:ring-emerald-500/20"
                               placeholder="e.g. Session Hall, SP Building">
                    </div>

                    <!-- Committees multiselect -->
                    <div>
                        <p class="block text-sm font-semibold text-gray-700 mb-1">
                            Committee(s) <span class="text-red-500">*</span>
                        </p>
                        <p class="text-xs text-gray-400 mb-2">
                            The committee assigned by Plenary is pre-selected. You may add additional committees.
                        </p>
                        <p id="committeeError" class="mb-2 hidden text-xs font-medium text-red-600">
                            At least one committee must be selected.
                        </p>
                        <?php if (empty($allCommittees)): ?>
                            <p class="text-sm text-amber-700 bg-amber-50 rounded-xl p-3">No active committees found.</p>
                        <?php else: ?>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 max-h-48 overflow-y-auto
                                        rounded-xl border border-gray-200 p-3">
                                <?php foreach ($allCommittees as $comm): ?>
                                    <?php
                                    $isPreselected = in_array((int) $comm['id'], array_map('intval', $oldCommitteeIds), true);
                                    ?>
                                    <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5
                                                  hover:bg-gray-50 transition text-sm text-gray-700 committee-label">
                                        <input type="checkbox" name="committee_ids[]"
                                               value="<?= (int) $comm['id'] ?>"
                                               <?= $isPreselected ? 'checked' : '' ?>
                                               class="h-4 w-4 rounded border-gray-300 text-emerald-600
                                                      focus:ring-emerald-500 committee-checkbox">
                                        <?= htmlspecialchars($comm['name']) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Chairpersons multiselect -->
                    <div>
                        <p class="block text-sm font-semibold text-gray-700 mb-1">
                            Chairperson(s) <span class="text-red-500">*</span>
                        </p>
                        <p class="text-xs text-gray-400 mb-2">
                            Select one or more SP members to serve as chairperson(s).
                        </p>
                        <p id="chairpersonError" class="mb-2 hidden text-xs font-medium text-red-600">
                            At least one chairperson must be selected.
                        </p>
                        <?php if (empty($spMembers)): ?>
                            <p class="text-sm text-amber-700 bg-amber-50 rounded-xl p-3">No active SP members found.</p>
                        <?php else: ?>
                            <!-- Search filter -->
                            <div class="relative mb-2">
                                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                <input type="text" id="spSearch" placeholder="Filter SP members…"
                                       class="block w-full rounded-xl border border-gray-200 py-2 pl-9 pr-3 text-sm
                                              focus:border-emerald-500 focus:outline-none focus:ring-2
                                              focus:ring-emerald-500/20">
                            </div>
                            <div id="spMemberList"
                                 class="grid grid-cols-1 gap-1 sm:grid-cols-2 max-h-52 overflow-y-auto
                                        rounded-xl border border-gray-200 p-3">
                                <?php foreach ($spMembers as $sp): ?>
                                    <?php
                                    $spId         = (int) $sp['sp_member_id'];
                                    $fullName     = trim(implode(' ', array_filter([
                                        $sp['first_name'], $sp['middle_name'], $sp['last_name'], $sp['suffix']
                                    ])));
                                    $isPreselected = in_array($spId, array_map('intval', $oldChairpersonIds), true);
                                    ?>
                                    <label class="sp-member-label flex cursor-pointer items-center gap-2 rounded-lg
                                                  px-2 py-1.5 hover:bg-gray-50 transition text-sm text-gray-700">
                                        <input type="checkbox" name="chairperson_ids[]"
                                               value="<?= $spId ?>"
                                               <?= $isPreselected ? 'checked' : '' ?>
                                               class="h-4 w-4 rounded border-gray-300 text-emerald-600
                                                      focus:ring-emerald-500 chairperson-checkbox">
                                        <span class="sp-name truncate"><?= htmlspecialchars($fullName) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Remarks / Notes -->
                    <div>
                        <label for="agendaRemarks" class="block text-sm font-semibold text-gray-700 mb-1">
                            Remarks / Notes
                            <span class="font-normal text-gray-400">(optional)</span>
                        </label>
                        <textarea name="remarks" id="agendaRemarks" rows="3"
                                  class="block w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                                         text-gray-800 focus:border-emerald-500 focus:outline-none focus:ring-2
                                         focus:ring-emerald-500/20"
                                  placeholder="Optional agenda notes or instructions…"><?= old('remarks') ?></textarea>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-between gap-3 border-t border-gray-100 pt-5">
                        <a href="<?= BASE_URL ?>/committee/referred?tab=ready_for_agenda"
                           class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white
                                  px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                            Cancel
                        </a>
                        <button type="submit" id="saveAgendaBtn"
                                class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5
                                       text-sm font-semibold text-white hover:bg-emerald-700 transition">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            Save Agenda
                        </button>
                    </div>

                </div>
            </form>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form           = document.getElementById('agendaForm');
    const saveBtn        = document.getElementById('saveAgendaBtn');
    const dateInput      = document.getElementById('agendaDate');
    const timeInput      = document.getElementById('agendaTime');
    const dateErr        = document.getElementById('dateError');
    const timeErr        = document.getElementById('timeError');
    const committeeErr   = document.getElementById('committeeError');
    const chairpersonErr = document.getElementById('chairpersonError');
    const spSearch       = document.getElementById('spSearch');
    const spList         = document.getElementById('spMemberList');

    // SP member live filter
    if (spSearch && spList) {
        spSearch.addEventListener('input', function () {
            const q = this.value.toLowerCase();
            spList.querySelectorAll('.sp-member-label').forEach(function (lbl) {
                const name = (lbl.querySelector('.sp-name') || {}).textContent || '';
                lbl.style.display = name.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }

    // Form validation
    if (form && saveBtn) {
        form.addEventListener('submit', function (e) {
            let valid = true;

            if (!dateInput || !dateInput.value) {
                e.preventDefault(); valid = false;
                if (dateErr) dateErr.classList.remove('hidden');
            }
            if (!timeInput || !timeInput.value) {
                e.preventDefault(); valid = false;
                if (timeErr) timeErr.classList.remove('hidden');
            }
            const checkedCommittees  = document.querySelectorAll('.committee-checkbox:checked').length;
            const checkedChairpersons= document.querySelectorAll('.chairperson-checkbox:checked').length;

            if (checkedCommittees === 0) {
                e.preventDefault(); valid = false;
                if (committeeErr) committeeErr.classList.remove('hidden');
            }
            if (checkedChairpersons === 0) {
                e.preventDefault(); valid = false;
                if (chairpersonErr) chairpersonErr.classList.remove('hidden');
            }

            if (valid) {
                saveBtn.disabled = true;
                saveBtn.innerHTML =
                    '<svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">' +
                    '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
                    '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>' +
                    '</svg><span class="ml-2">Saving…</span>';
            }
        });
    }

    // Clear error on interaction
    if (dateInput && dateErr)  dateInput.addEventListener('change', () => dateErr.classList.add('hidden'));
    if (timeInput && timeErr)  timeInput.addEventListener('change', () => timeErr.classList.add('hidden'));

    document.querySelectorAll('.committee-checkbox').forEach(cb =>
        cb.addEventListener('change', () => committeeErr && committeeErr.classList.add('hidden'))
    );
    document.querySelectorAll('.chairperson-checkbox').forEach(cb =>
        cb.addEventListener('change', () => chairpersonErr && chairpersonErr.classList.add('hidden'))
    );
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

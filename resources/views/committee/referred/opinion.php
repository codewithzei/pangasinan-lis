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
$success          = $success          ?? null;
$error            = $error            ?? null;
$errors           = $errors           ?? [];

$endorsementId     = (int) ($endorsement['id']                ?? 0);
$documentId        = (int) ($cycle['document_id']             ?? 0);
$endorsementNumber = (int) ($endorsement['endorsement_number'] ?? 1);

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

    <!-- Flash messages -->
    <?php if ($success): ?>
        <div class="flex items-start gap-3 rounded-2xl border border-green-200 bg-green-50 p-4">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <p class="text-sm font-medium text-green-800"><?= htmlspecialchars($success) ?></p>
        </div>
    <?php endif; ?>
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
                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-8 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-200">
                        <svg class="h-7 w-7 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <p class="mt-3 text-sm font-semibold text-gray-700">Opinion already recorded</p>
                    <p class="mt-1 text-xs text-gray-400">
                        The form below is not available because a submission already exists for this endorsement.
                    </p>
                    <a href="<?= BASE_URL ?>/committee/referred?tab=for_opinion"
                       class="mt-4 inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white
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
                                        <p class="text-xs text-gray-500">Compliance file required.</p>
                                    </div>
                                </label>
                            </div>
                            <p id="opinionTypeError" class="mt-1.5 hidden text-xs font-medium text-red-600">
                                Please select an opinion type.
                            </p>
                        </div>

                        <!-- Opinion file -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">
                                Opinion File <span class="text-red-500">*</span>
                            </label>
                            <p class="text-xs text-gray-400 mb-2">
                                Upload the signed opinion document (PDF, DOC, DOCX, etc., max 25 MB).
                            </p>
                            <input type="file" name="opinion_file" id="opinionFile"
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                   class="block w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm
                                          text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50
                                          file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-indigo-700
                                          hover:file:bg-indigo-100">
                            <p id="opinionFileError" class="mt-1.5 hidden text-xs font-medium text-red-600">
                                An opinion file is required.
                            </p>
                        </div>

                        <!-- Compliance file (shown/required for UNFAVORABLE) -->
                        <div id="complianceSection" class="hidden">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">
                                Compliance File <span class="text-red-500">*</span>
                                <span class="ml-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">
                                    Required for Unfavorable
                                </span>
                            </label>
                            <p class="text-xs text-gray-400 mb-2">
                                Upload the compliance document (PDF, DOC, DOCX, etc., max 25 MB).
                            </p>
                            <input type="file" name="compliance_file" id="complianceFile"
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                   class="block w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm
                                          text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-red-50
                                          file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-red-700
                                          hover:file:bg-red-100">
                            <p id="complianceFileError" class="mt-1.5 hidden text-xs font-medium text-red-600">
                                A compliance file is required for an Unfavorable opinion.
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
    const radios          = document.querySelectorAll('.opinion-radio');
    const complianceSection = document.getElementById('complianceSection');
    const complianceFile  = document.getElementById('complianceFile');
    const form            = document.getElementById('opinionForm');
    const submitBtn       = document.getElementById('submitOpinionBtn');
    const opinionTypeErr  = document.getElementById('opinionTypeError');
    const opinionFileErr  = document.getElementById('opinionFileError');
    const complianceErr   = document.getElementById('complianceFileError');
    const opinionFile     = document.getElementById('opinionFile');

    function getSelectedType() {
        const checked = document.querySelector('.opinion-radio:checked');
        return checked ? checked.value : null;
    }

    function toggleCompliance() {
        const type = getSelectedType();
        if (!complianceSection) return;
        if (type === 'UNFAVORABLE') {
            complianceSection.classList.remove('hidden');
            if (complianceFile) complianceFile.setAttribute('required', 'required');
        } else {
            complianceSection.classList.add('hidden');
            if (complianceFile) complianceFile.removeAttribute('required');
        }
    }

    radios.forEach(r => r.addEventListener('change', function () {
        toggleCompliance();
        if (opinionTypeErr) opinionTypeErr.classList.add('hidden');
    }));

    toggleCompliance(); // apply on load for old() repopulation

    if (form && submitBtn) {
        form.addEventListener('submit', function (e) {
            let valid = true;

            if (!getSelectedType()) {
                e.preventDefault();
                valid = false;
                if (opinionTypeErr) opinionTypeErr.classList.remove('hidden');
            }
            if (opinionFile && opinionFile.files.length === 0) {
                e.preventDefault();
                valid = false;
                if (opinionFileErr) opinionFileErr.classList.remove('hidden');
            }
            if (getSelectedType() === 'UNFAVORABLE' && complianceFile && complianceFile.files.length === 0) {
                e.preventDefault();
                valid = false;
                if (complianceErr) complianceErr.classList.remove('hidden');
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
    }

    // Clear errors on interaction
    if (opinionFile) opinionFile.addEventListener('change', function () {
        if (opinionFileErr) opinionFileErr.classList.add('hidden');
    });
    if (complianceFile) complianceFile.addEventListener('change', function () {
        if (complianceErr) complianceErr.classList.add('hidden');
    });
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

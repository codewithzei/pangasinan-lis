<?php
/**
 * Committee Cases — Create / Docket Form
 *
 * Variables supplied by CommitteeCasesController::create():
 *   $document        array   Full document row with joins
 *   $assignment      array   The ACCEPTED Committee assignment row
 *   $docketPreview   string  Next DKT-YYYY-NNNN (informational, not committed)
 *   $success         string|null
 *   $error           string|null
 *   $errors          array
 *   $old             array   Repopulation values from $_SESSION['_old']
 */

$document      = $document      ?? [];
$assignment    = $assignment    ?? [];
$docketPreview = $docketPreview ?? '';
$success       = $success       ?? null;
$error         = $error         ?? null;
$errors        = $errors        ?? [];
$old           = $old           ?? [];

$documentId     = (int)  ($document['id']              ?? 0);
$trackingNumber = (string)($document['tracking_number'] ?? '');
$subjectMatter  = (string)($document['subject_matter']  ?? '');
$docTypeName    = (string)($document['document_type_name']       ?? '');
$docTypeBadge   = (string)($document['document_type_badge_color'] ?? '#2563EB');
$sourceType     = (string)($document['source_type']     ?? '');
$municipalityName = (string)($document['municipality_name'] ?? '');

// Pre-fill complainant details from source information on the document.
// For citizen / non-entity sources, fall back to source_name.
$prefillComplainant = '';
if (!empty($document['source_name'])) {
    $prefillComplainant = $document['source_name'];
}

// Pre-fill municipality — only if a municipality was already stored.
$prefillMunicipality = $municipalityName;

// old() repopulation helpers (already htmlspecialchars'd by old() helper).
function casesCreateOld(array $old, string $key, string $fallback = ''): string
{
    if (isset($old[$key])) {
        return htmlspecialchars($old[$key]);
    }
    return htmlspecialchars($fallback);
}

ob_start();
?>

<div class="space-y-6">

    <!-- Back + header --------------------------------------------------------->
    <div class="flex items-center gap-4">
        <a href="<?= BASE_URL ?>/committee/inbox/show?id=<?= $documentId ?>"
           class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-gray-200
                  bg-white text-gray-500 hover:bg-gray-50 transition">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                Committee / Cases / New Case
            </p>
            <h1 class="text-xl font-bold text-gray-900">Docket a New Case</h1>
        </div>
    </div>

    <!-- Flash messages -------------------------------------------------------->
    <?php if ($error): ?>
        <div class="flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-red-100">
                <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </div>
            <p class="mt-1.5 text-sm font-medium text-red-800"><?= htmlspecialchars($error) ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="rounded-2xl border border-red-200 bg-red-50 p-4">
            <p class="text-sm font-semibold text-red-800">Please correct the following errors:</p>
            <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-red-700">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

        <!-- Left: form (2/3) ------------------------------------------------->
        <div class="space-y-6 xl:col-span-2">

            <!-- Document summary card ---------------------------------------->
            <div class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-400">
                    Source Document
                </h2>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Tracking Number</dt>
                        <dd class="mt-1 font-mono text-sm font-bold text-primary">
                            <?= htmlspecialchars($trackingNumber) ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Document Type</dt>
                        <dd class="mt-1">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                  style="background-color:<?= htmlspecialchars($docTypeBadge) ?>1a;
                                         color:<?= htmlspecialchars($docTypeBadge) ?>;">
                                <?= htmlspecialchars($docTypeName) ?>
                            </span>
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Subject Matter</dt>
                        <dd class="mt-1 text-sm text-gray-800 whitespace-pre-wrap">
                            <?= htmlspecialchars($subjectMatter) ?>
                        </dd>
                    </div>
                    <?php if ($sourceType !== ''): ?>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Source Type</dt>
                            <dd class="mt-1 text-sm text-gray-800"><?= htmlspecialchars($sourceType) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($document['external_office_name'])): ?>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">External Office</dt>
                            <dd class="mt-1 text-sm text-gray-800">
                                <?= htmlspecialchars($document['external_office_name']) ?>
                                <?php if (!empty($document['external_office_abbr'])): ?>
                                    <span class="text-gray-400">(<?= htmlspecialchars($document['external_office_abbr']) ?>)</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($document['hospital_name'])): ?>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Hospital</dt>
                            <dd class="mt-1 text-sm text-gray-800"><?= htmlspecialchars($document['hospital_name']) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($municipalityName !== ''): ?>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Municipality / City</dt>
                            <dd class="mt-1 text-sm text-gray-800"><?= htmlspecialchars($municipalityName) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </div>

            <!-- Case form ---------------------------------------------------->
            <form method="POST" action="<?= BASE_URL ?>/committee/cases/create"
                  id="caseCreateForm" novalidate>
                <input type="hidden" name="document_id" value="<?= $documentId ?>">

                <div class="rounded-2xl border border-gray-200 bg-white p-6 space-y-5">
                    <h2 class="text-base font-semibold text-gray-900">Case Details</h2>

                    <!-- Docket No. (read-only preview) ----------------------->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                            Docket No.
                            <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-xs font-normal
                                         text-gray-500 normal-case tracking-normal">
                                Auto-generated
                            </span>
                        </label>
                        <div class="flex items-center gap-3">
                            <input type="text"
                                   value="<?= htmlspecialchars($docketPreview) ?>"
                                   readonly
                                   class="flex-1 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2
                                          font-mono text-sm font-bold text-gray-700 cursor-not-allowed select-all">
                            <span class="shrink-0 rounded-full bg-amber-100 px-2.5 py-1 text-xs
                                         font-medium text-amber-700">
                                Preview — final number assigned on submit
                            </span>
                        </div>
                        <p class="mt-1.5 text-xs text-gray-400">
                            The docket number is generated automatically by the server. Under high load it may
                            differ slightly from this preview, but will always follow the DKT-YYYY-NNNN format.
                        </p>
                    </div>

                    <!-- Date Assigned ---------------------------------------->
                    <div>
                        <label for="date_assigned"
                               class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                            Date Assigned <span class="text-red-500">*</span>
                        </label>
                        <input type="date"
                               id="date_assigned"
                               name="date_assigned"
                               value="<?= casesCreateOld($old, 'date_assigned', date('Y-m-d')) ?>"
                               required
                               class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm
                                      focus:border-primary focus:ring-1 focus:ring-primary">
                    </div>

                    <!-- Nature of Case ---------------------------------------->
                    <div>
                        <label for="nature_of_case"
                               class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                            Nature of Case <span class="text-red-500">*</span>
                        </label>
                        <textarea id="nature_of_case"
                                  name="nature_of_case"
                                  rows="4"
                                  required
                                  placeholder="Describe the nature of this case…"
                                  class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm
                                         focus:border-primary focus:ring-1 focus:ring-primary"><?= casesCreateOld($old, 'nature_of_case') ?></textarea>
                    </div>

                    <!-- Complainant Details ----------------------------------->
                    <div>
                        <label for="complainant_details"
                               class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                            Complainant Details <span class="text-red-500">*</span>
                        </label>
                        <textarea id="complainant_details"
                                  name="complainant_details"
                                  rows="3"
                                  required
                                  placeholder="Full name and details of the complainant…"
                                  class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm
                                         focus:border-primary focus:ring-1 focus:ring-primary"><?= casesCreateOld($old, 'complainant_details', $prefillComplainant) ?></textarea>
                        <?php if ($prefillComplainant !== '' && !isset($old['complainant_details'])): ?>
                            <p class="mt-1 text-xs text-gray-400">
                                Pre-filled from source information. Edit as needed.
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Complainant Municipality ------------------------------>
                    <div>
                        <label for="complainant_municipality"
                               class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                            Complainant Municipality / City
                            <span class="ml-1 font-normal text-gray-400 normal-case tracking-normal">(optional)</span>
                        </label>
                        <?php if ($prefillMunicipality !== '' && !isset($old['complainant_municipality'])): ?>
                            <!-- Municipality already stored on document — show it pre-filled -->
                            <input type="text"
                                   id="complainant_municipality"
                                   name="complainant_municipality"
                                   value="<?= htmlspecialchars($prefillMunicipality) ?>"
                                   maxlength="255"
                                   placeholder="Municipality or city"
                                   class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm
                                          focus:border-primary focus:ring-1 focus:ring-primary">
                            <p class="mt-1 text-xs text-gray-400">
                                Pre-filled from the document's municipality. Edit if different.
                            </p>
                        <?php else: ?>
                            <input type="text"
                                   id="complainant_municipality"
                                   name="complainant_municipality"
                                   value="<?= casesCreateOld($old, 'complainant_municipality') ?>"
                                   maxlength="255"
                                   placeholder="Municipality or city (if applicable)"
                                   class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm
                                          focus:border-primary focus:ring-1 focus:ring-primary">
                        <?php endif; ?>
                    </div>

                    <!-- Respondents ------------------------------------------>
                    <div>
                        <label for="respondents"
                               class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                            Respondents <span class="text-red-500">*</span>
                        </label>
                        <textarea id="respondents"
                                  name="respondents"
                                  rows="3"
                                  required
                                  placeholder="Full name(s) of respondent(s). For multiple respondents, separate with commas."
                                  class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm
                                         focus:border-primary focus:ring-1 focus:ring-primary"><?= casesCreateOld($old, 'respondents') ?></textarea>
                        <p class="mt-1 text-xs text-gray-400">
                            For multiple respondents, separate names with commas — e.g.,
                            <em>Juan dela Cruz, Maria Santos</em>.
                        </p>
                    </div>

                    <!-- Submit ------------------------------------------------>
                    <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-5">
                        <a href="<?= BASE_URL ?>/committee/inbox/show?id=<?= $documentId ?>"
                           class="inline-flex items-center justify-center rounded-xl border border-gray-200
                                  bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                            Cancel
                        </a>
                        <button type="submit" id="submitBtn"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-purple-600
                                       px-5 py-2 text-sm font-semibold text-white hover:bg-purple-700 transition">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Create Case &amp; Assign Docket
                        </button>
                    </div>

                </div>
            </form>

        </div><!-- /left column -->

        <!-- Right: guidelines (1/3) ------------------------------------------>
        <div class="xl:col-span-1">
            <div class="sticky top-6 space-y-4">

                <!-- Docket info card ----------------------------------------->
                <div class="rounded-2xl border border-purple-200 bg-purple-50 p-5">
                    <div class="mb-3 flex items-center gap-2">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-purple-100">
                            <svg class="h-4 w-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                            </svg>
                        </div>
                        <p class="text-sm font-semibold text-purple-900">Docket Number</p>
                    </div>
                    <p class="text-xs text-purple-700 leading-relaxed">
                        The docket number is assigned automatically by the server using a
                        locked per-year sequence. The preview above is informational — the
                        actual number is locked in at the moment of submission.
                    </p>
                    <p class="mt-2 text-xs font-semibold text-purple-800">
                        Format: <span class="font-mono">DKT-YYYY-NNNN</span>
                    </p>
                </div>

                <!-- Respondents tip card ------------------------------------->
                <div class="rounded-2xl border border-gray-200 bg-white p-5">
                    <div class="mb-2 flex items-center gap-2">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-50">
                            <svg class="h-4 w-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <p class="text-sm font-semibold text-gray-800">Multiple Respondents</p>
                    </div>
                    <p class="text-xs text-gray-500 leading-relaxed">
                        Enter all respondents in the Respondents field, separated by commas.
                        Each name will be displayed individually on the case details page.
                    </p>
                </div>

                <!-- What happens next card ------------------------------------>
                <div class="rounded-2xl border border-gray-200 bg-white p-5">
                    <p class="mb-3 text-sm font-semibold text-gray-800">What happens next?</p>
                    <ol class="space-y-2">
                        <?php foreach ([
                            'A docket number is generated and locked.',
                            'You are redirected to the case details page.',
                            'Use Add Action to build the case timeline.',
                            'Attach supporting documents to each action.',
                        ] as $i => $step): ?>
                            <li class="flex items-start gap-2.5 text-xs text-gray-500">
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full
                                             bg-gray-100 text-xs font-bold text-gray-600">
                                    <?= $i + 1 ?>
                                </span>
                                <?= htmlspecialchars($step) ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>

            </div>
        </div><!-- /right column -->

    </div><!-- /grid -->

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form      = document.getElementById('caseCreateForm');
    const submitBtn = document.getElementById('submitBtn');

    if (form && submitBtn) {
        form.addEventListener('submit', function (e) {
            // Basic client-side required-field check before disabling button.
            const required = form.querySelectorAll('[required]');
            let ok = true;
            required.forEach(function (el) {
                if (!el.value.trim()) ok = false;
            });
            if (!ok) return; // Let browser native validation take over.

            submitBtn.disabled = true;
            submitBtn.innerHTML =
                '<svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg"' +
                '     fill="none" viewBox="0 0 24 24">' +
                '  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
                '  <path class="opacity-75" fill="currentColor"' +
                '        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962' +
                '           0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>' +
                '</svg>' +
                '<span class="ml-2">Creating Case\u2026</span>';
        });
    }
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

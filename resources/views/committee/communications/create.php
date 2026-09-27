<?php
/**
 * Committee Communications — Create / Log Form
 *
 * Variables supplied by CommitteeCommunicationsController::create():
 *   $document      array   Full document row with joins
 *   $assignment    array   The ACCEPTED Committee assignment row
 *   $success       string|null
 *   $error         string|null
 *   $errors        array
 *   $old           array   Repopulation values from $_SESSION['_old']
 */

$document   = $document   ?? [];
$assignment = $assignment ?? [];
$success    = $success    ?? null;
$error      = $error      ?? null;
$errors     = $errors     ?? [];
$old        = $old        ?? [];

$documentId     = (int)    ($document['id']               ?? 0);
$trackingNumber = (string) ($document['tracking_number']  ?? '');
$subjectMatter  = (string) ($document['subject_matter']   ?? '');
$docTypeName    = (string) ($document['document_type_name']        ?? '');
$docTypeBadge   = (string) ($document['document_type_badge_color'] ?? '#0D9488');
$sourceType     = (string) ($document['source_type']      ?? '');
$municipalityName = (string) ($document['municipality_name'] ?? '');

// Pre-fill sender from source information already on the document.
$prefillSender = '';
if (!empty($document['source_name'])) {
    $prefillSender = $document['source_name'];
} elseif (!empty($document['external_office_name'])) {
    $prefillSender = $document['external_office_name'];
} elseif (!empty($document['hospital_name'])) {
    $prefillSender = $document['hospital_name'];
}

function commCreateOld(array $old, string $key, string $fallback = ''): string
{
    return htmlspecialchars((string) ($old[$key] ?? $fallback));
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
                Committee / Communications / New Record
            </p>
            <h1 class="text-xl font-bold text-gray-900">Log Communication</h1>
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

            <!-- Source document summary card ---------------------------------->
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

            <!-- Communication form ------------------------------------------->
            <form method="POST" action="<?= BASE_URL ?>/committee/communications/create"
                  id="commCreateForm" novalidate>
                <input type="hidden" name="document_id" value="<?= $documentId ?>">

                <div class="rounded-2xl border border-gray-200 bg-white p-6 space-y-5">
                    <h2 class="text-base font-semibold text-gray-900">Communication Details</h2>

                    <!-- Date Logged ------------------------------------------->
                    <div>
                        <label for="date_logged"
                               class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                            Date Logged <span class="text-red-500">*</span>
                        </label>
                        <input type="date"
                               id="date_logged"
                               name="date_logged"
                               value="<?= commCreateOld($old, 'date_logged', date('Y-m-d')) ?>"
                               required
                               class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm
                                      focus:border-teal-500 focus:ring-1 focus:ring-teal-500">
                    </div>

                    <!-- Subject ------------------------------------------------>
                    <div>
                        <label for="subject"
                               class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                            Subject / Summary <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               id="subject"
                               name="subject"
                               value="<?= commCreateOld($old, 'subject', $subjectMatter) ?>"
                               maxlength="500"
                               required
                               placeholder="Brief subject or summary of this communication…"
                               class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm
                                      focus:border-teal-500 focus:ring-1 focus:ring-teal-500">
                        <?php if ($subjectMatter !== '' && !isset($old['subject'])): ?>
                            <p class="mt-1 text-xs text-gray-400">
                                Pre-filled from the document's subject matter. Edit as needed.
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Sender / Originating Party ---------------------------->
                    <div>
                        <label for="sender_details"
                               class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                            Sender / Originating Party <span class="text-red-500">*</span>
                        </label>
                        <textarea id="sender_details"
                                  name="sender_details"
                                  rows="3"
                                  required
                                  maxlength="5000"
                                  placeholder="Name and details of the sender or originating party…"
                                  class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm
                                         focus:border-teal-500 focus:ring-1 focus:ring-teal-500"><?= commCreateOld($old, 'sender_details', $prefillSender) ?></textarea>
                        <?php if ($prefillSender !== '' && !isset($old['sender_details'])): ?>
                            <p class="mt-1 text-xs text-gray-400">
                                Pre-filled from source information. Edit if needed.
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Notes ------------------------------------------------->
                    <div>
                        <label for="notes"
                               class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                            Notes
                            <span class="ml-1 font-normal text-gray-400 normal-case tracking-normal">(optional)</span>
                        </label>
                        <textarea id="notes"
                                  name="notes"
                                  rows="4"
                                  maxlength="10000"
                                  placeholder="Any additional context or notes about this communication…"
                                  class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm
                                         focus:border-teal-500 focus:ring-1 focus:ring-teal-500"><?= commCreateOld($old, 'notes') ?></textarea>
                    </div>

                    <!-- Submit ------------------------------------------------>
                    <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-5">
                        <a href="<?= BASE_URL ?>/committee/inbox/show?id=<?= $documentId ?>"
                           class="inline-flex items-center justify-center rounded-xl border border-gray-200
                                  bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                            Cancel
                        </a>
                        <button type="submit" id="submitBtn"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-teal-600
                                       px-5 py-2 text-sm font-semibold text-white hover:bg-teal-700 transition">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Create Communication Record
                        </button>
                    </div>

                </div>
            </form>

        </div><!-- /left column -->

        <!-- Right: info panel (1/3) ------------------------------------------>
        <div class="xl:col-span-1">
            <div class="sticky top-6 space-y-4">

                <!-- Workflow overview ----------------------------------------->
                <div class="rounded-2xl border border-teal-200 bg-teal-50 p-5">
                    <div class="mb-3 flex items-center gap-2">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-teal-100">
                            <svg class="h-4 w-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0
                                         002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <p class="text-sm font-semibold text-teal-900">Communications Workflow</p>
                    </div>
                    <p class="text-xs text-teal-700 leading-relaxed">
                        Creating this record opens the Communications workflow for this document.
                        You can then schedule an agenda, record the hearing outcome, and produce
                        a Committee Report before returning to Plenary.
                    </p>
                </div>

                <!-- What happens next ----------------------------------------->
                <div class="rounded-2xl border border-gray-200 bg-white p-5">
                    <p class="mb-3 text-sm font-semibold text-gray-800">What happens next?</p>
                    <ol class="space-y-2">
                        <?php foreach ([
                            'Communication record is created and document status updates.',
                            'Schedule an agenda with date, time, and venue.',
                            'Record the committee hearing outcome.',
                            'Create the Committee Report.',
                            'Return to Plenary from the Reports page.',
                        ] as $i => $step): ?>
                            <li class="flex items-start gap-2.5 text-xs text-gray-500">
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full
                                             bg-teal-100 text-xs font-bold text-teal-700">
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
    const form      = document.getElementById('commCreateForm');
    const submitBtn = document.getElementById('submitBtn');

    if (form && submitBtn) {
        form.addEventListener('submit', function () {
            const required = form.querySelectorAll('[required]');
            let ok = true;
            required.forEach(function (el) { if (!el.value.trim()) ok = false; });
            if (!ok) return;

            submitBtn.disabled = true;
            submitBtn.innerHTML =
                '<svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg"' +
                '     fill="none" viewBox="0 0 24 24">' +
                '  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
                '  <path class="opacity-75" fill="currentColor"' +
                '        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962' +
                '           0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>' +
                '</svg>' +
                '<span class="ml-2">Creating Record\u2026</span>';
        });
    }
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

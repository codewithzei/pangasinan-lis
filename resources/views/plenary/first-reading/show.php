<?php
/**
 * Plenary First Reading — detail & processing form
 *
 * Variables supplied by PlenaryReadingController::firstReadingShow():
 *   $document        array
 *   $plenaryDetails  array|null
 *   $authors         array
 *   $coAuthors       array
 *   $docCommittees   array   currently assigned committees
 *   $committees      array   all active committees for select
 *   $events          array
 *   $existingReading array|null  (should be null here; gate enforced by controller)
 *   $canAct          bool
 *   $success         string|null
 *   $error           string|null
 *   $errors          array
 *   $old             array
 */

$document       = $document       ?? [];
$plenaryDetails = $plenaryDetails ?? null;
$authors        = $authors        ?? [];
$coAuthors      = $coAuthors      ?? [];
$docCommittees  = $docCommittees  ?? [];
$committees     = $committees     ?? [];
$events         = $events         ?? [];
$errors         = $errors         ?? [];
$old            = $old            ?? [];
$canAct         = $canAct         ?? false;

$documentId     = (int) ($document['id']               ?? 0);
$trackingNumber = $document['tracking_number']          ?? '';
$subjectMatter  = $document['subject_matter']           ?? '';
$proposedNumber = $plenaryDetails['proposed_number']    ?? '';
$sessionType    = $plenaryDetails['session_type']       ?? '';
$targetDate     = $plenaryDetails['target_first_reading_date'] ?? '';

// Pre-populate committee ids from current doc committees
$existingCommitteeIds = array_column($docCommittees, 'committee_id');

ob_start();
?>

<div class="space-y-6">

    <!-- Back + title -->
    <div class="flex items-center gap-4">
        <a href="<?= BASE_URL ?>/plenary/first-reading"
           class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Plenary / First Reading</p>
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

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <!-- Left: Document info -->
        <div class="lg:col-span-1 space-y-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Document Details</h2>
                <div>
                    <p class="text-xs text-gray-400">Tracking Number</p>
                    <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($trackingNumber) ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Subject Matter</p>
                    <p class="text-sm text-gray-800"><?= htmlspecialchars($subjectMatter) ?></p>
                </div>
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
                <div>
                    <p class="text-xs text-gray-400">Proposed No.</p>
                    <p class="text-sm font-medium text-primary"><?= htmlspecialchars($proposedNumber) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($sessionType)): ?>
                <div>
                    <p class="text-xs text-gray-400">Session Type</p>
                    <p class="text-sm text-gray-800"><?= htmlspecialchars($sessionType) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($targetDate)): ?>
                <div>
                    <p class="text-xs text-gray-400">Target First Reading Date</p>
                    <p class="text-sm text-gray-800"><?= htmlspecialchars(date('F j, Y', strtotime($targetDate))) ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Authors -->
            <?php if (!empty($authors) || !empty($coAuthors)): ?>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Authorship</h2>
                <?php if (!empty($authors)): ?>
                <div>
                    <p class="text-xs text-gray-400 mb-1">Authors</p>
                    <ul class="space-y-1">
                        <?php foreach ($authors as $a): ?>
                            <li class="text-sm text-gray-800"><?= htmlspecialchars($a['full_name']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                <?php if (!empty($coAuthors)): ?>
                <div>
                    <p class="text-xs text-gray-400 mb-1">Co-Authors</p>
                    <ul class="space-y-1">
                        <?php foreach ($coAuthors as $a): ?>
                            <li class="text-sm text-gray-800"><?= htmlspecialchars($a['full_name']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Current committees -->
            <?php if (!empty($docCommittees)): ?>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Committees in Charge</h2>
                <ul class="space-y-1">
                    <?php foreach ($docCommittees as $dc): ?>
                        <li class="text-sm text-gray-800">
                            <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700"><?= htmlspecialchars($dc['committee_name']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right: First Reading form -->
        <div class="lg:col-span-2 space-y-4">
            <?php if ($canAct): ?>
            <div class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-5">First Reading Action</h2>

                <form method="POST" action="<?= BASE_URL ?>/plenary/first-reading/store" id="frForm">
                    <input type="hidden" name="document_id" value="<?= $documentId ?>">

                    <div class="space-y-5">
                        <!-- Reading Date -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="reading_date">Reading Date <span class="text-red-500">*</span></label>
                            <input type="date" id="reading_date" name="reading_date"
                                   value="<?= htmlspecialchars($old['reading_date'] ?? date('Y-m-d')) ?>"
                                   class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                   required>
                        </div>

                        <!-- Action selection -->
                        <div>
                            <p class="block text-sm font-medium text-gray-700 mb-2">First Reading Decision <span class="text-red-500">*</span></p>
                            <div class="space-y-3">
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-4 hover:bg-gray-50 has-[:checked]:border-primary has-[:checked]:bg-blue-50 transition">
                                    <input type="radio" name="first_reading_action" value="REFERRED_TO_COMMITTEE"
                                           <?= ($old['first_reading_action'] ?? '') === 'REFERRED_TO_COMMITTEE' ? 'checked' : '' ?>
                                           class="mt-0.5 h-4 w-4 accent-primary" id="action_refer">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">Refer to Committee</p>
                                        <p class="text-xs text-gray-500 mt-0.5">Route this document to one or more Committees for review before Second Reading.</p>
                                    </div>
                                </label>
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-4 hover:bg-gray-50 has-[:checked]:border-primary has-[:checked]:bg-blue-50 transition">
                                    <input type="radio" name="first_reading_action" value="ADOPTED_SUSPENDED_RULES"
                                           <?= ($old['first_reading_action'] ?? '') === 'ADOPTED_SUSPENDED_RULES' ? 'checked' : '' ?>
                                           class="mt-0.5 h-4 w-4 accent-primary" id="action_adopt">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">Adopted and Approved for Second Reading under Suspended Rules</p>
                                        <p class="text-xs text-gray-500 mt-0.5">Skip Committee referral and move directly to the Second Reading queue.</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Committee selection (shown only when Refer is selected) -->
                        <div id="committeeSection" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Select Committee(s) <span class="text-red-500">*</span></label>
                            <p class="text-xs text-gray-500 mb-2">Pre-populated from Plenary processing. You may adjust if needed.</p>
                            <div class="max-h-56 overflow-y-auto space-y-1 rounded-xl border border-gray-200 p-3">
                                <?php foreach ($committees as $c): ?>
                                    <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-gray-50 cursor-pointer">
                                        <input type="checkbox" name="committee_ids[]" value="<?= (int) $c['id'] ?>"
                                               <?= in_array((int) $c['id'], array_map('intval', $existingCommitteeIds ?: ($old['committee_ids'] ?? [])), true) ? 'checked' : '' ?>
                                               class="h-4 w-4 accent-primary rounded">
                                        <span class="text-sm text-gray-800"><?= htmlspecialchars($c['name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Remarks -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="fr_remarks">Remarks (optional)</label>
                            <textarea id="fr_remarks" name="remarks" rows="3"
                                      placeholder="Enter any remarks for this First Reading action…"
                                      class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"><?= htmlspecialchars($old['remarks'] ?? '') ?></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-2">
                            <a href="<?= BASE_URL ?>/plenary/first-reading"
                               class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                                Cancel
                            </a>
                            <button type="submit"
                                    class="rounded-xl bg-primary px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition"
                                    id="frSubmitBtn">
                                Record First Reading
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                <p class="text-sm font-medium text-amber-800">You are not the owner of this document. Only the Plenary user who accepted it may record the First Reading.</p>
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
                                    <?php if (!empty($ev['performed_by_username'])): ?>
                                        by <?= htmlspecialchars($ev['performed_by_username']) ?>
                                    <?php endif; ?>
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
    const form = document.getElementById('frForm');
    const committeeSection = document.getElementById('committeeSection');
    const actionRadios = form.querySelectorAll('input[name="first_reading_action"]');

    function toggleCommittees() {
        const selected = form.querySelector('input[name="first_reading_action"]:checked');
        if (selected && selected.value === 'REFERRED_TO_COMMITTEE') {
            committeeSection.classList.remove('hidden');
        } else {
            committeeSection.classList.add('hidden');
        }
    }

    actionRadios.forEach(r => r.addEventListener('change', toggleCommittees));
    toggleCommittees(); // on load (old values)
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

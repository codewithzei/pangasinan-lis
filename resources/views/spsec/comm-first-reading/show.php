<?php
/**
 * SP Secretary — Communications First Reading — Decision form
 *
 * Variables:
 *   $document       array
 *   $inboxItem      array|false
 *   $firstReading   array|false
 *   $categories     array   communication_categories
 *   $committees     array   all active committees
 *   $pageTitle      string
 *   $success        string|null
 *   $error          string|null
 *   $errors         array
 *   $old            array
 */

$document     = $document     ?? [];
$inboxItem    = $inboxItem    ?? false;
$firstReading = $firstReading ?? false;
$categories   = $categories   ?? [];
$committees   = $committees   ?? [];
$errors       = $errors       ?? [];
$old          = $old          ?? [];

$documentId     = (int) ($document['id'] ?? 0);
$trackingNumber = $document['tracking_number'] ?? '';
$alreadyDone    = !empty($firstReading);

ob_start();
?>

<div class="space-y-6">

    <?php if (!empty($success)): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc list-inside space-y-1"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <!-- Back + title -->
    <div class="flex items-center gap-4">
        <a href="<?= BASE_URL ?>/spsec/comm-first-reading"
           class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">SP Secretary / Comm First Reading</p>
            <h1 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($trackingNumber) ?></h1>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <!-- Left: Document info -->
        <div class="lg:col-span-1 space-y-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">Communication Details</h2>
                <div>
                    <p class="text-xs text-gray-400">Communication Date</p>
                    <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars(date('F j, Y', strtotime($document['date_received']))) ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Subject Matter</p>
                    <p class="text-sm text-gray-800"><?= htmlspecialchars($document['subject_matter'] ?? '') ?></p>
                </div>
                <?php if (!empty($document['status'])): ?>
                <div>
                    <p class="text-xs text-gray-400">Status</p>
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                          style="background-color: <?= htmlspecialchars($document['status_badge_color'] ?? '#F59E0B') ?>1a; color: <?= htmlspecialchars($document['status_badge_color'] ?? '#F59E0B') ?>;">
                        <?= htmlspecialchars($document['status']) ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Form or result -->
        <div class="lg:col-span-2 space-y-4">

            <?php if ($alreadyDone): ?>
                <!-- Already decided — show result -->
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 space-y-3">
                    <h2 class="text-base font-semibold text-emerald-900">First Reading Decision Recorded</h2>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-xs text-emerald-600">Decision</p>
                            <p class="text-sm font-bold text-emerald-900"><?= htmlspecialchars($firstReading['decision']) ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-emerald-600">Reading Date</p>
                            <p class="text-sm text-emerald-800"><?= htmlspecialchars(date('F j, Y', strtotime($firstReading['reading_date']))) ?></p>
                        </div>
                        <?php if (!empty($firstReading['communication_category_name'])): ?>
                        <div class="col-span-2">
                            <p class="text-xs text-emerald-600">Communication Category</p>
                            <p class="text-sm text-emerald-800"><?= htmlspecialchars($firstReading['communication_category_name']) ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($firstReading['resolution_number'])): ?>
                        <div>
                            <p class="text-xs text-emerald-600">Resolution Number</p>
                            <p class="text-sm font-semibold text-emerald-900"><?= htmlspecialchars($firstReading['resolution_number']) ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($firstReading['proposed_number'])): ?>
                        <div>
                            <p class="text-xs text-emerald-600">Proposed Number</p>
                            <p class="text-sm text-emerald-800"><?= htmlspecialchars($firstReading['proposed_number']) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>

            <!-- Decision form -->
            <div class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-5">Record First Reading Decision</h2>

                <form method="POST" action="<?= BASE_URL ?>/spsec/comm-first-reading/store"
                      enctype="multipart/form-data" id="frDecisionForm">
                    <input type="hidden" name="document_id" value="<?= $documentId ?>">

                    <div class="space-y-5">

                        <!-- Reading date -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="reading_date">
                                Reading Date <span class="text-red-500">*</span>
                            </label>
                            <input type="date" id="reading_date" name="reading_date"
                                   value="<?= htmlspecialchars($old['reading_date'] ?? date('Y-m-d')) ?>"
                                   class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                   required>
                        </div>

                        <!-- Decision radio buttons -->
                        <div>
                            <p class="block text-sm font-medium text-gray-700 mb-2">
                                First Reading Decision <span class="text-red-500">*</span>
                            </p>
                            <div class="space-y-3">

                                <!-- NOTED -->
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-4 hover:bg-gray-50 has-[:checked]:border-emerald-400 has-[:checked]:bg-emerald-50 transition">
                                    <input type="radio" name="decision" value="NOTED" id="dec_noted"
                                           <?= ($old['decision'] ?? '') === 'NOTED' ? 'checked' : '' ?>
                                           class="mt-0.5 h-4 w-4 accent-emerald-600">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">Noted</p>
                                        <p class="text-xs text-gray-500 mt-0.5">Communication is noted. Requires a communication category. Will appear on the Plenary Communications page.</p>
                                    </div>
                                </label>

                                <!-- REFERRED -->
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-4 hover:bg-gray-50 has-[:checked]:border-violet-400 has-[:checked]:bg-violet-50 transition">
                                    <input type="radio" name="decision" value="REFERRED" id="dec_referred"
                                           <?= ($old['decision'] ?? '') === 'REFERRED' ? 'checked' : '' ?>
                                           class="mt-0.5 h-4 w-4 accent-violet-600">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">Referred</p>
                                        <p class="text-xs text-gray-500 mt-0.5">Refer to one or more committees for review. The communication enters the existing committee workflow.</p>
                                    </div>
                                </label>

                                <!-- APPROVED -->
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-4 hover:bg-gray-50 has-[:checked]:border-blue-400 has-[:checked]:bg-blue-50 transition">
                                    <input type="radio" name="decision" value="APPROVED" id="dec_approved"
                                           <?= ($old['decision'] ?? '') === 'APPROVED' ? 'checked' : '' ?>
                                           class="mt-0.5 h-4 w-4 accent-blue-600">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">Approved for Resolution under Other Matters</p>
                                        <p class="text-xs text-gray-500 mt-0.5">Requires a resolution number. Optional proposed number. One or more resolution files required.</p>
                                    </div>
                                </label>

                            </div>
                        </div>

                        <!-- NOTED — category -->
                        <div id="section_noted" class="hidden space-y-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                            <div>
                                <label class="block text-sm font-medium text-emerald-800 mb-1" for="communication_category_id">
                                    Communication Category <span class="text-red-500">*</span>
                                </label>
                                <select id="communication_category_id" name="communication_category_id"
                                        class="w-full rounded-xl border border-emerald-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200">
                                    <option value="">— Select Category —</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= (int) $cat['id'] ?>"
                                            <?= ((int) ($old['communication_category_id'] ?? 0)) === (int) $cat['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- REFERRED — committees -->
                        <div id="section_referred" class="hidden space-y-4 rounded-xl border border-violet-200 bg-violet-50 p-4">
                            <p class="text-sm font-medium text-violet-800">Select Committees <span class="text-red-500">*</span></p>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <?php
                                $oldCommitteeIds = array_map('intval', (array) ($old['committee_ids'] ?? []));
                                foreach ($committees as $c):
                                ?>
                                    <label class="flex items-center gap-2 rounded-xl border border-violet-100 bg-white px-3 py-2 cursor-pointer hover:bg-violet-50 transition">
                                        <input type="checkbox" name="committee_ids[]" value="<?= (int) $c['id'] ?>"
                                               <?= in_array((int) $c['id'], $oldCommitteeIds, true) ? 'checked' : '' ?>
                                               class="h-4 w-4 rounded accent-violet-600">
                                        <span class="text-sm text-gray-800"><?= htmlspecialchars($c['name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- APPROVED — resolution fields + file upload -->
                        <div id="section_approved" class="hidden space-y-4 rounded-xl border border-blue-200 bg-blue-50 p-4">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-blue-800 mb-1" for="proposed_number">
                                        Proposed Number <span class="text-xs text-blue-500">(optional)</span>
                                    </label>
                                    <input type="text" id="proposed_number" name="proposed_number"
                                           value="<?= htmlspecialchars($old['proposed_number'] ?? '') ?>"
                                           placeholder="e.g. Res. No. 001-2026"
                                           class="w-full rounded-xl border border-blue-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-blue-800 mb-1" for="resolution_number">
                                        Resolution Number <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" id="resolution_number" name="resolution_number"
                                           value="<?= htmlspecialchars($old['resolution_number'] ?? '') ?>"
                                           placeholder="e.g. Res. No. 001-2026"
                                           class="w-full rounded-xl border border-blue-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-blue-800 mb-1">
                                    Resolution Files <span class="text-red-500">*</span>
                                    <span class="text-xs font-normal text-blue-500 ml-1">(PDF, DOCX, XLS, images — max 25 MB each, up to 10 files)</span>
                                </label>
                                <input type="file" name="resolution_files[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp"
                                       class="block w-full text-sm text-gray-700 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-100 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-blue-700 hover:file:bg-blue-200 transition">
                            </div>
                        </div>

                        <!-- Remarks -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="remarks">Remarks</label>
                            <textarea id="remarks" name="remarks" rows="3"
                                      placeholder="Optional remarks…"
                                      class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"><?= htmlspecialchars($old['remarks'] ?? '') ?></textarea>
                        </div>

                        <!-- Submit -->
                        <div class="flex items-center gap-3 pt-2">
                            <button type="submit"
                                    onclick="return confirm('Record this First Reading decision? This action cannot be undone.')"
                                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-2.5 text-sm font-semibold text-white hover:bg-primary/90 transition">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Record Decision
                            </button>
                            <a href="<?= BASE_URL ?>/spsec/comm-first-reading"
                               class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                                Cancel
                            </a>
                        </div>

                    </div>
                </form>
            </div>

            <?php endif; ?>

        </div>
    </div>

</div>

<script>
(function () {
    function syncSections() {
        var checked = document.querySelector('input[name="decision"]:checked');
        var val = checked ? checked.value : '';
        document.getElementById('section_noted').classList.toggle('hidden', val !== 'NOTED');
        document.getElementById('section_referred').classList.toggle('hidden', val !== 'REFERRED');
        document.getElementById('section_approved').classList.toggle('hidden', val !== 'APPROVED');
    }

    document.querySelectorAll('input[name="decision"]').forEach(function (r) {
        r.addEventListener('change', syncSections);
    });

    // Run once on page load to restore state if old_ values are set
    syncSections();
}());
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

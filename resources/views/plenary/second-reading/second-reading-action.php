            <?php if ($canAct && $inEligibleStatus && !$hasFinalSecondReading): ?>
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="mb-5 text-base font-semibold text-gray-900">Second Reading Action</h2>

                <form method="POST" action="<?= BASE_URL ?>/plenary/second-reading/store" enctype="multipart/form-data" id="srForm">
                    <input type="hidden" name="document_id" value="<?= $documentId ?>">

                    <div class="space-y-5">
                        <!-- Reading Date -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="sr_reading_date">Reading Date <span class="text-red-500">*</span></label>
                            <input type="date" id="sr_reading_date" name="reading_date"
                                   value="<?= htmlspecialchars($old['reading_date'] ?? date('Y-m-d')) ?>"
                                   class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                   required>
                        </div>

                        <!-- Action -->
                        <div>
                            <p class="block text-sm font-medium text-gray-700 mb-2">Second Reading Decision <span class="text-red-500">*</span></p>
                            <div class="space-y-3">
                                <?php
                                $srActions = [
                                    ['value' => 'APPROVED',                       'label' => 'Approved on Second Reading',                     'desc'  => 'Approve the document; enter the Resolution/Ordinance No. and Date Approved.'],
                                    ['value' => 'REFERRED_TO_COMMITTEE_SECOND',   'label' => 'Referred back to Committee',                       'desc'  => 'Return the document to Committee; it will come back here after the Committee workflow completes.'],
                                    ['value' => 'DEFERRED',                       'label' => 'Defer',                                           'desc'  => 'Defer the document; status becomes "Second Reading Deferred".'],
                                    ['value' => 'WITHDRAWN',                      'label' => 'Withdraw',                                        'desc'  => 'Withdraw the document from the Second Reading workflow.'],
                                ];
                                foreach ($srActions as $srA): ?>
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-4 hover:bg-gray-50 has-[:checked]:border-primary has-[:checked]:bg-blue-50 transition">
                                    <input type="radio" name="second_reading_action" value="<?= $srA['value'] ?>"
                                           <?= ($old['second_reading_action'] ?? '') === $srA['value'] ? 'checked' : '' ?>
                                           class="mt-0.5 h-4 w-4 accent-primary">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($srA['label']) ?></p>
                                        <p class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($srA['desc']) ?></p>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- APPROVED fields -->
                        <div id="srApprovedSection" class="hidden space-y-4 rounded-xl bg-gray-50 border border-gray-200 p-4">
                            <h3 class="text-sm font-semibold text-gray-700">Approval Details</h3>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1" for="approved_no">Resolution/Ordinance No. <span class="text-red-500">*</span></label>
                                <input type="text" id="approved_no" name="approved_no"
                                       value="<?= htmlspecialchars($old['approved_no'] ?? '') ?>"
                                       placeholder="e.g. SP Res. No. 001-2026 or SP Ordinance No. 001-2026"
                                       class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1" for="date_finalized">Date Approved <span class="text-red-500">*</span></label>
                                <input type="date" id="date_finalized" name="date_finalized"
                                       value="<?= htmlspecialchars($old['date_finalized'] ?? '') ?>"
                                       class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1" for="approved_subject_matter">Subject Matter (Approved)</label>
                                <textarea id="approved_subject_matter" name="approved_subject_matter" rows="3"
                                          placeholder="Enter the approved subject matter…"
                                          class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"><?= htmlspecialchars($old['approved_subject_matter'] ?? $document['subject_matter'] ?? '') ?></textarea>
                            </div>
                            <div data-approval-file-upload data-required-when-radio="second_reading_action:APPROVED">
                                <label class="mb-1 block text-xs font-medium text-gray-600">
                                    Approval Attachments <span class="text-red-500">*</span>
                                </label>
                                <input type="file" name="approval_files[]" multiple
                                       data-approval-file-input
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp"
                                       class="sr-only">
                                <div data-approval-file-zone role="button" tabindex="0"
                                     class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-white px-5 py-5 text-center hover:border-primary hover:bg-primary/5 focus:outline-none focus:ring-2 focus:ring-primary/40">
                                    <p class="text-sm font-medium text-gray-700">Drag &amp; drop files here, or browse</p>
                                    <p class="mt-1 text-xs text-gray-400">Required · up to 10 files · max 25 MB each</p>
                                </div>
                                <ul data-approval-file-list class="mt-2 hidden space-y-2" aria-live="polite"></ul>
                                <p data-approval-file-error class="mt-1 hidden text-xs text-red-600" role="alert"></p>
                            </div>
                        </div>

                        <!-- REFERRED TO COMMITTEE fields -->
                        <div id="srReferSection" class="hidden space-y-4 rounded-xl bg-gray-50 border border-gray-200 p-4">
                            <h3 class="text-sm font-semibold text-gray-700">Committee Selection <span class="text-red-500">*</span></h3>
                            <div class="max-h-52 overflow-y-auto space-y-1 rounded-xl border border-gray-200 bg-white p-3">
                                <?php foreach ($committees as $c): ?>
                                    <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-gray-50 cursor-pointer">
                                        <input type="checkbox" name="committee_ids[]" value="<?= (int) $c['id'] ?>"
                                               <?= in_array((int) $c['id'], array_map('intval', $old['committee_ids'] ?? $existingCommitteeIds), true) ? 'checked' : '' ?>
                                               class="h-4 w-4 accent-primary rounded">
                                        <span class="text-sm text-gray-800"><?= htmlspecialchars($c['name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- DEFERRED fields -->
                        <div id="srDeferredSection" class="hidden space-y-4 rounded-xl bg-gray-50 border border-gray-200 p-4">
                            <h3 class="text-sm font-semibold text-gray-700">Deferral Details</h3>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1" for="date_deferred">Date Deferred <span class="text-red-500">*</span></label>
                                <input type="date" id="date_deferred" name="date_deferred"
                                       value="<?= htmlspecialchars($old['date_deferred'] ?? '') ?>"
                                       class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1" for="compliance_file_new">Compliance Document (optional)</label>
                                <input type="file" id="compliance_file_new" name="compliance_file"
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                       class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 file:mr-3 file:rounded-lg file:border-0 file:bg-primary file:px-3 file:py-1 file:text-xs file:font-medium file:text-white hover:file:bg-blue-700">
                            </div>
                        </div>

                        <!-- WITHDRAWN fields -->
                        <div id="srWithdrawnSection" class="hidden space-y-4 rounded-xl bg-gray-50 border border-gray-200 p-4">
                            <h3 class="text-sm font-semibold text-gray-700">Withdrawal Details</h3>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1" for="date_withdrawn">Date Withdrawn <span class="text-red-500">*</span></label>
                                <input type="date" id="date_withdrawn" name="date_withdrawn"
                                       value="<?= htmlspecialchars($old['date_withdrawn'] ?? '') ?>"
                                       class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                            </div>
                        </div>

                        <!-- Common remarks -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="sr_remarks">Remarks</label>
                            <textarea id="sr_remarks" name="remarks" rows="3"
                                      placeholder="Enter remarks…"
                                      class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"><?= htmlspecialchars($old['remarks'] ?? '') ?></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-2">
                            <a href="<?= BASE_URL ?>/plenary/second-reading"
                               class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Cancel</a>
                            <button type="submit"
                                    class="rounded-xl bg-primary px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition">
                                Record Action
                            </button>
                        </div>
                    </div>
                </form>
            </section>
            <?php elseif (!$canAct): ?>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                <p class="text-sm font-medium text-amber-800">You are not the owner of this document. Only the Plenary user who accepted it may process the Second Reading.</p>
            </div>
            <?php endif; ?>

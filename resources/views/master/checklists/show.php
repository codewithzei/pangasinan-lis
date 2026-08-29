<?php
ob_start();

$success = $success ?? null;
$error = $error ?? null;
$checklist = $checklist ?? null;
$availableDocumentTypes = $availableDocumentTypes ?? [];
$assignedDocumentTypes = $assignedDocumentTypes ?? [];
$pageTitle = $pageTitle ?? 'Checklist Details';
$pageSubtitle = $pageSubtitle ?? '';
$accent = $accent ?? 'primary';
?>

<div class="space-y-6">

    <nav class="flex items-center gap-2 text-xs font-medium text-gray-500">
        <a href="<?= BASE_URL ?>/master/checklists" class="hover:text-primary transition">
            Checklists
        </a>
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="text-gray-800">Checklist Details</span>
    </nav>

    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
        <div class="relative px-6 py-6 sm:px-8 border-b border-gray-100">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl <?= !empty($checklist['is_active']) ? 'bg-emerald-100 text-emerald-600' : 'bg-gray-100 text-gray-500' ?>">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($checklist['name'] ?? '') ?></h1>
                            <?php if (!empty($checklist['is_active'])): ?>
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-200">
                                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span> Active
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 border border-gray-200">
                                    Inactive
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($checklist['description'])): ?>
                            <p class="mt-1 text-sm text-gray-500"><?= htmlspecialchars($checklist['description'] ?? '') ?></p>
                        <?php endif; ?>
                        <div class="mt-3 flex flex-wrap gap-4 text-xs text-gray-500">
                            <div class="flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                                </svg>
                                Sort Order: #<?= (int)($checklist['sort_order'] ?? 0) ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="<?= BASE_URL ?>/master/checklists"
                       class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Back
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-px bg-gray-100 sm:grid-cols-2">
            <div class="bg-white px-6 py-4 text-center">
                <div class="text-xs font-medium text-gray-500">Assigned Document Types</div>
                <div class="mt-1 text-2xl font-bold text-gray-900"><?= (int)($checklist['document_type_count'] ?? 0) ?></div>
            </div>
            <div class="bg-white px-6 py-4 text-center">
                <div class="text-xs font-medium text-blue-600">Available to Assign</div>
                <div class="mt-1 text-2xl font-bold text-gray-900"><?= count($availableDocumentTypes) ?></div>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-3">

        <div class="rounded-2xl border border-gray-200 bg-white xl:col-span-1 h-fit">
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5">
                <div>
                    <h2 class="font-semibold text-gray-900">Assign Document Types</h2>
                    <p class="mt-1 text-xs text-gray-500">Add document types to this checklist</p>
                </div>
            </div>
            <form method="POST" action="<?= BASE_URL ?>/master/checklists/assign-document-types" class="p-6 space-y-4">
                <input type="hidden" name="checklist_id" value="<?= (int)($checklist['id'] ?? 0) ?>">
                
                <div>
                    <div class="mb-1.5 flex items-center justify-between">
                        <label class="block text-xs font-semibold text-gray-700">Search Document Types</label>
                        <button type="button" id="clearSearch" class="text-xs text-gray-400 hover:text-gray-600 transition hidden">
                            Clear
                        </button>
                    </div>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" id="documentTypeSearch" placeholder="Search by name or description..."
                            class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    </div>
                </div>

                <div>
                    <div class="mb-1.5 flex items-center justify-between">
                        <label class="block text-xs font-semibold text-gray-700">Document Types</label>
                        <label class="flex items-center gap-1.5 text-[11px] text-gray-500">
                            <input type="checkbox" id="selectAllDocumentTypes" class="h-3.5 w-3.5 rounded border-gray-300 text-primary focus:ring-primary">
                            <span id="selectAllLabel">Select all (<span id="visibleCount"><?= count($availableDocumentTypes) ?></span>)</span>
                        </label>
                    </div>
                    <div class="max-h-96 overflow-y-auto rounded-xl border border-gray-200 bg-gray-50 p-2 space-y-1" id="documentTypesList">
                        <?php if (empty($availableDocumentTypes)): ?>
                            <div class="py-8 text-center text-xs text-gray-400" id="emptyState">
                                All active document types are already assigned to this checklist.
                            </div>
                        <?php else: ?>
                            <div class="hidden py-8 text-center text-xs text-gray-400" id="noResultsState">
                                <svg class="mx-auto h-8 w-8 mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                No document types match your search.
                            </div>
                            <?php foreach ($availableDocumentTypes as $dt):
                                $dtId = (int)($dt['id'] ?? 0);
                                $name = htmlspecialchars($dt['name'] ?? '');
                                $desc = htmlspecialchars($dt['description'] ?? '');
                                $badgeColor = htmlspecialchars($dt['badge_color'] ?? '#2563EB');
                                $searchData = strtolower($name . ' ' . $desc);
                            ?>
                            <label class="document-type-item flex items-center gap-2.5 rounded-lg p-2 hover:bg-white cursor-pointer transition" data-search="<?= htmlspecialchars($searchData) ?>">
                                <input type="checkbox" name="document_type_ids[]" value="<?= $dtId ?>"
                                    class="document-type-checkbox h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary">
                                <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full" style="background-color: <?= $badgeColor ?>;">
                                    <svg class="h-3.5 w-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium text-gray-800"><?= $name ?></div>
                                    <?php if ($desc): ?>
                                        <div class="truncate text-[11px] text-gray-500"><?= $desc ?></div>
                                    <?php endif; ?>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="submit" id="assignButton"
                    <?= empty($availableDocumentTypes) ? 'disabled' : '' ?>
                    class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 transition shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Assign to Checklist
                </button>
            </form>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white xl:col-span-2">
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5">
                <div>
                    <h2 class="font-semibold text-gray-900">Assigned Document Types</h2>
                    <p class="mt-1 text-xs text-gray-500">
                        <?= count($assignedDocumentTypes) ?> document type<?= count($assignedDocumentTypes) !== 1 ? 's' : '' ?> assigned
                    </p>
                </div>
            </div>
            <div class="p-6">
                <?php if (empty($assignedDocumentTypes)): ?>
                    <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-12 text-center">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                            <svg class="h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <p class="mt-4 text-sm font-medium text-gray-700">No document types assigned yet</p>
                        <p class="mt-1 text-xs text-gray-500">Use the form on the left to assign document types to this checklist item.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($assignedDocumentTypes as $assignment): ?>
                            <?php
                            $badgeColor = htmlspecialchars($assignment['badge_color'] ?? '#2563EB');
                            $isRequired = (int)($assignment['is_required'] ?? 1);
                            ?>
                            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 hover:border-gray-300 transition">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full" style="background-color: <?= $badgeColor ?>;">
                                        <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <div class="truncate font-medium text-gray-900"><?= htmlspecialchars($assignment['document_type_name']) ?></div>
                                            <?php if ($isRequired): ?>
                                                <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 border border-amber-200">
                                                    <svg class="h-2.5 w-2.5" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                    </svg>
                                                    Required
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex shrink-0 items-center rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600 border border-gray-200">
                                                    Optional
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1 ml-3">
                                    <button type="button" onclick="toggleRequired(<?= $assignment['id'] ?>, '<?= htmlspecialchars($assignment['document_type_name']) ?>', <?= $isRequired ?>)"
                                        class="rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:border-amber-400 hover:bg-amber-50 hover:text-amber-600 transition"
                                        title="<?= $isRequired ? 'Mark as Optional' : 'Mark as Required' ?>">
                                        <?php if ($isRequired): ?>
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                        <?php else: ?>
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11.5V14m0-2.5v-6a1.5 1.5 0 113 0m-3 6a1.5 1.5 0 00-3 0v2a7.5 7.5 0 0015 0v-5a1.5 1.5 0 00-3 0m-6-3V11m0-5.5v-1a1.5 1.5 0 013 0v1m0 0V11m0-5.5a1.5 1.5 0 013 0v3m0 0V11"/>
                                        </svg>
                                        <?php endif; ?>
                                    </button>
                                    <button type="button" onclick="removeDocumentType(<?= $assignment['id'] ?>, '<?= htmlspecialchars($assignment['document_type_name']) ?>')"
                                        class="rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:border-red-400 hover:bg-red-50 hover:text-red-600 transition"
                                        title="Remove">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </section>

</div>

<!-- ================================================================ -->
<!-- STATUS TOGGLE MODAL                                              -->
<!-- ================================================================ -->
<div id="statusModal" class="fixed inset-0 z-[100] hidden items-center justify-center" role="dialog" aria-modal="true">
    <div id="statusModalBackdrop" class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity opacity-0"></div>
    <div id="statusModalPanel" class="relative z-10 w-full max-w-md mx-4 bg-white rounded-2xl shadow-2xl border border-gray-100 transform scale-95 opacity-0 transition-all duration-200">
        <div class="p-6">
            <div class="flex items-start gap-4">
                <div id="statusIconBox" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-amber-100">
                    <svg id="statusIcon" class="h-6 w-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 id="statusModalTitle" class="text-lg font-semibold text-gray-900">Update Requirement Status</h3>
                    <p id="statusModalMessage" class="mt-1 text-sm text-gray-600"></p>
                    <div class="mt-4 space-y-2">
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 cursor-pointer hover:bg-gray-50 transition">
                            <input type="radio" name="status_choice" value="1" class="h-4 w-4 border-gray-300 text-primary focus:ring-primary">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700 border border-amber-200">
                                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    Required
                                </span>
                                <span class="text-sm text-gray-700">Must be provided</span>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 cursor-pointer hover:bg-gray-50 transition">
                            <input type="radio" name="status_choice" value="0" class="h-4 w-4 border-gray-300 text-primary focus:ring-primary">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 border border-gray-200">
                                    Optional
                                </span>
                                <span class="text-sm text-gray-700">Can be omitted</span>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
            <button type="button" onclick="closeStatusModal()" id="statusCancelBtn"
                class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 transition">
                Cancel
            </button>
            <button type="button" onclick="executeStatusUpdate()" id="statusConfirmBtn"
                class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-white bg-primary hover:bg-blue-700 transition shadow-sm">
                Update Status
            </button>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- CONFIRMATION MODAL (Remove Document Type)                        -->
<!-- ================================================================ -->
<div id="confirmModal" class="fixed inset-0 z-[100] hidden items-center justify-center" role="dialog" aria-modal="true">
    <div id="confirmModalBackdrop" class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity opacity-0"></div>
    <div id="confirmModalPanel" class="relative z-10 w-full max-w-sm mx-4 bg-white rounded-2xl shadow-2xl border border-gray-100 transform scale-95 opacity-0 transition-all duration-200">
        <div class="p-6">
            <div class="flex items-start gap-4">
                <div id="confirmIconBox" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-red-100">
                    <svg id="confirmIcon" class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 id="confirmTitle" class="text-lg font-semibold text-gray-900">Confirm Action</h3>
                    <p id="confirmMessage" class="mt-1 text-sm text-gray-600">Are you sure?</p>
                </div>
            </div>
        </div>
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
            <button type="button" onclick="closeConfirmModal()" id="confirmCancelBtn"
                class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 transition">
                Cancel
            </button>
            <button type="button" onclick="executeConfirmAction()" id="confirmProceedBtn"
                class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 transition shadow-sm">
                Remove
            </button>
        </div>
    </div>
</div>

<div id="toastContainer" class="fixed top-4 right-4 z-[200] space-y-2 pointer-events-none"></div>

<!-- ================================================================ -->
<!-- JAVASCRIPT                                                        -->
<!-- ================================================================ -->
<script>
const BASE = '<?= BASE_URL ?>';
const checklistId = <?= (int)($checklist['id'] ?? 0) ?>;

/* ------------------------------------------------------------------ */
/* Toast                                                                */
/* ------------------------------------------------------------------ */
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    const colors = {
        success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
        error: 'border-red-200 bg-red-50 text-red-800',
        info: 'border-blue-200 bg-blue-50 text-blue-800'
    };
    const icons = {
        success: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>',
        error: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>',
        info: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'
    };
    const iconColors = {
        success: 'bg-emerald-100 text-emerald-600',
        error: 'bg-red-100 text-red-600',
        info: 'bg-blue-100 text-blue-600'
    };
    toast.className = `pointer-events-auto max-w-sm rounded-xl border px-4 py-3 flex items-center gap-3 shadow-lg transform transition-all duration-300 translate-x-full ${colors[type]}`;
    toast.innerHTML = `
        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full ${iconColors[type]}">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">${icons[type]}</svg>
        </div>
        <p class="text-sm font-medium flex-1">${message}</p>
        <button type="button" onclick="this.parentElement.remove()" class="text-current opacity-60 hover:opacity-100">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    `;
    container.appendChild(toast);
    requestAnimationFrame(() => toast.classList.remove('translate-x-full'));
    setTimeout(() => {
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.remove(), 300);
    }, 2800);
}

// Search functionality
const searchInput = document.getElementById('documentTypeSearch');
const clearSearchBtn = document.getElementById('clearSearch');
const documentTypeItems = document.querySelectorAll('.document-type-item');
const noResultsState = document.getElementById('noResultsState');
const visibleCountSpan = document.getElementById('visibleCount');

searchInput?.addEventListener('input', function() {
    const query = this.value.toLowerCase().trim();
    let visibleCount = 0;

    documentTypeItems.forEach(item => {
        const searchData = item.dataset.search || '';
        if (query === '' || searchData.includes(query)) {
            item.classList.remove('hidden');
            visibleCount++;
        } else {
            item.classList.add('hidden');
        }
    });

    if (visibleCountSpan) visibleCountSpan.textContent = visibleCount;
    
    if (query !== '') {
        clearSearchBtn?.classList.remove('hidden');
    } else {
        clearSearchBtn?.classList.add('hidden');
    }

    if (noResultsState) {
        if (visibleCount === 0 && documentTypeItems.length > 0) {
            noResultsState.classList.remove('hidden');
        } else {
            noResultsState.classList.add('hidden');
        }
    }
});

clearSearchBtn?.addEventListener('click', function() {
    searchInput.value = '';
    searchInput.dispatchEvent(new Event('input'));
    searchInput.focus();
});

// Select all functionality
const selectAllCheckbox = document.getElementById('selectAllDocumentTypes');
const documentTypeCheckboxes = document.querySelectorAll('.document-type-checkbox');

selectAllCheckbox?.addEventListener('change', function() {
    const visibleCheckboxes = Array.from(documentTypeCheckboxes).filter(cb => !cb.closest('.document-type-item').classList.contains('hidden'));
    visibleCheckboxes.forEach(cb => cb.checked = this.checked);
});

documentTypeCheckboxes.forEach(cb => {
    cb.addEventListener('change', function() {
        const visibleCheckboxes = Array.from(documentTypeCheckboxes).filter(cb => !cb.closest('.document-type-item').classList.contains('hidden'));
        const allChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.every(cb => cb.checked);
        if (selectAllCheckbox) selectAllCheckbox.checked = allChecked;
    });
});

/* ------------------------------------------------------------------ */
/* Status Modal                                                         */
/* ------------------------------------------------------------------ */
let statusModalOpen = false;
let pendingStatusUpdate = null;

function openStatusModal(id, name, currentRequired) {
    if (statusModalOpen) return;
    
    const currentStatus = currentRequired ? 1 : 0;
    const currentLabel = currentRequired ? 'Required' : 'Optional';
    
    document.getElementById('statusModalTitle').textContent = 'Update Requirement Status';
    document.getElementById('statusModalMessage').innerHTML = `
        <strong class="font-semibold text-gray-900">${name}</strong><br>
        <span class="text-gray-500">Current status: <span class="font-medium">${currentLabel}</span></span>
    `;
    
    // Set radio button selection to show CURRENT status
    const radios = document.querySelectorAll('input[name="status_choice"]');
    radios.forEach(radio => {
        radio.checked = (parseInt(radio.value) === currentStatus);
    });
    
    pendingStatusUpdate = { id, name, currentRequired };
    statusModalOpen = true;
    
    const m = document.getElementById('statusModal');
    const b = document.getElementById('statusModalBackdrop');
    const p = document.getElementById('statusModalPanel');
    m.classList.remove('hidden');
    m.classList.add('flex');
    requestAnimationFrame(() => {
        b.classList.remove('opacity-0');
        p.classList.remove('scale-95', 'opacity-0');
        p.classList.add('scale-100', 'opacity-100');
    });
}

function closeStatusModal() {
    if (!statusModalOpen) return;
    statusModalOpen = false;
    pendingStatusUpdate = null;
    
    const m = document.getElementById('statusModal');
    const b = document.getElementById('statusModalBackdrop');
    const p = document.getElementById('statusModalPanel');
    b.classList.add('opacity-0');
    p.classList.remove('scale-100', 'opacity-100');
    p.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        m.classList.remove('flex');
        m.classList.add('hidden');
    }, 200);
}

async function executeStatusUpdate() {
    if (!pendingStatusUpdate) return;
    
    const { id } = pendingStatusUpdate;
    
    // Get the selected radio button value
    const selectedRadio = document.querySelector('input[name="status_choice"]:checked');
    if (!selectedRadio) {
        showToast('Please select a status.', 'error');
        return;
    }
    
    const newStatus = parseInt(selectedRadio.value);
    closeStatusModal();
    
    try {
        const formData = new FormData();
        formData.append('id', id);
        formData.append('is_required', newStatus);
        const response = await fetch(`${BASE}/master/checklists/update-required`, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        if (result.success) {
            showToast(result.message || 'Status updated successfully.', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(result.message || 'Failed to update status.', 'error');
        }
    } catch (error) {
        showToast('An error occurred while updating status.', 'error');
    }
}

document.getElementById('statusModalBackdrop')?.addEventListener('click', closeStatusModal);

/* ------------------------------------------------------------------ */
/* Confirmation Modal                                                   */
/* ------------------------------------------------------------------ */
let confirmModalOpen = false;
let pendingAction = null;

function openConfirmModal({ title, message, confirmText = 'Confirm', cancelText = 'Cancel', type = 'danger', onConfirm }) {
    if (confirmModalOpen) return;
    
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMessage').innerHTML = message;
    
    const proceedBtn = document.getElementById('confirmProceedBtn');
    const cancelBtn = document.getElementById('confirmCancelBtn');
    const iconBox = document.getElementById('confirmIconBox');
    const icon = document.getElementById('confirmIcon');
    
    if (type === 'danger') {
        proceedBtn.className = 'inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 transition shadow-sm';
        iconBox.className = 'flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-red-100';
        icon.className = 'h-6 w-6 text-red-600';
        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>';
    } else {
        proceedBtn.className = 'inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-white bg-primary hover:bg-blue-700 transition shadow-sm';
        iconBox.className = 'flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-blue-100';
        icon.className = 'h-6 w-6 text-blue-600';
        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>';
    }
    
    proceedBtn.textContent = confirmText;
    cancelBtn.textContent = cancelText;
    pendingAction = onConfirm;
    confirmModalOpen = true;
    
    const m = document.getElementById('confirmModal');
    const b = document.getElementById('confirmModalBackdrop');
    const p = document.getElementById('confirmModalPanel');
    m.classList.remove('hidden');
    m.classList.add('flex');
    requestAnimationFrame(() => {
        b.classList.remove('opacity-0');
        p.classList.remove('scale-95', 'opacity-0');
        p.classList.add('scale-100', 'opacity-100');
    });
}

function closeConfirmModal() {
    if (!confirmModalOpen) return;
    confirmModalOpen = false;
    pendingAction = null;
    
    const m = document.getElementById('confirmModal');
    const b = document.getElementById('confirmModalBackdrop');
    const p = document.getElementById('confirmModalPanel');
    b.classList.add('opacity-0');
    p.classList.remove('scale-100', 'opacity-100');
    p.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        m.classList.remove('flex');
        m.classList.add('hidden');
    }, 200);
}

function executeConfirmAction() {
    const action = pendingAction;
    closeConfirmModal();
    if (typeof action === 'function') {
        action();
    }
}

document.getElementById('confirmModalBackdrop')?.addEventListener('click', closeConfirmModal);

/* ------------------------------------------------------------------ */
/* Document Type Actions                                                */
/* ------------------------------------------------------------------ */
function toggleRequired(id, name, currentRequired) {
    openStatusModal(id, name, currentRequired);
}

async function removeDocumentType(id, name) {
    openConfirmModal({
        title: 'Remove Document Type',
        message: `Are you sure you want to remove <strong class="font-semibold text-gray-900">${name}</strong> from this checklist?<br><br><span class="text-gray-500">This will not delete the document type, only unassign it from this checklist. You can add it back later if needed.</span>`,
        confirmText: 'Remove',
        cancelText: 'Cancel',
        type: 'danger',
        onConfirm: async () => {
            try {
                const formData = new FormData();
                formData.append('id', id);
                formData.append('checklist_id', checklistId);
                const response = await fetch(`${BASE}/master/checklists/remove-document-type`, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message || 'Document type removed successfully.', 'success');
                    setTimeout(() => location.reload(), 800);
                } else {
                    showToast(result.message || 'Failed to remove document type.', 'error');
                }
            } catch (error) {
                showToast('An error occurred while removing document type.', 'error');
            }
        }
    });
}

<?php if ($success): ?>
showToast(<?= json_encode($success) ?>, 'success');
<?php endif; ?>

<?php if ($error): ?>
showToast(<?= json_encode($error) ?>, 'error');
<?php endif; ?>
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';
?>

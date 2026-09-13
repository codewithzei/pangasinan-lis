<?php
/**
 * Admin — Receive Document (direct intake)
 *
 * Reuses the same fields and client-side behaviour as
 * resources/views/receiving/receive-document/index.php.
 * Only the hero banner, form action URL, and cancel link differ.
 *
 * Variables supplied by AdminReceiveDocumentController::index():
 *   $trackingNumberPreview  string
 *   $sourceTypes            array
 *   $externalOffices        array
 *   $hospitals              array
 *   $spMembers              array
 *   $municities             array
 *   $success                string|null
 *   $error                  string|null
 *   $errors                 array
 */

$trackingNumberPreview = $trackingNumberPreview ?? '';
$documentTypes         = $documentTypes         ?? [];
$sourceTypes           = $sourceTypes           ?? [];
$externalOffices       = $externalOffices       ?? [];
$hospitals             = $hospitals             ?? [];
$spMembers             = $spMembers             ?? [];
$municities            = $municities            ?? [];
$success               = $success               ?? null;
$error                 = $error                 ?? null;
$errors                = $errors                ?? [];

ob_start();
?>

<div class="space-y-6">

    <!-- Page Header ---------------------------------------------------------->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-700 via-emerald-600 to-teal-600 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10">
                <p class="text-sm font-medium text-emerald-100">ADMIN / DIRECT INTAKE</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    Receive Document
                </h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-emerald-100">
                    Directly receive and encode an incoming document when Receiving staff are unavailable.
                    The same validation rules and document workflow apply.
                </p>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
        </div>
    </section>

    <!-- Flash Messages ------------------------------------------------------->
    <?php if ($success): ?>
        <div class="rounded-2xl border border-green-200 bg-green-50 p-4">
            <div class="flex items-start">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-green-100">
                    <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <div class="ml-3 flex-1">
                    <p class="text-sm font-medium text-green-800"><?= htmlspecialchars($success) ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="rounded-2xl border border-red-200 bg-red-50 p-4">
            <div class="flex items-start">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-100">
                    <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </div>
                <div class="ml-3 flex-1">
                    <p class="text-sm font-medium text-red-800"><?= htmlspecialchars($error) ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="rounded-2xl border border-red-200 bg-red-50 p-4">
            <div class="flex items-start">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-100">
                    <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="ml-3 flex-1">
                    <p class="text-sm font-semibold text-red-800">Please correct the following errors:</p>
                    <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-red-700">
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Form ----------------------------------------------------------------->
    <form method="POST" action="<?= BASE_URL ?>/admin/receive-document/submit"
          enctype="multipart/form-data" id="documentForm">

        <div class="space-y-6">

            <!-- Tracking Number Preview ------------------------------------->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-900">Tracking Number</h2>
                <p class="mt-1 text-sm text-gray-500">Automatically generated tracking number for this document</p>
                <div class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 border border-emerald-200">
                    <p class="text-xs font-medium text-emerald-700 uppercase tracking-wide">Tracking Number</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-900" id="trackingNumberPreview">
                        <?= htmlspecialchars($trackingNumberPreview) ?>
                    </p>
                </div>
            </section>

            <!-- Receipt Information ----------------------------------------->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-900">Receipt Information</h2>
                <p class="mt-1 text-sm text-gray-500">Date and time the document was received</p>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="date_received" class="block text-sm font-medium text-gray-700">
                            Date Received <span class="text-red-500">*</span>
                        </label>
                        <input type="date" name="date_received" id="date_received" required
                               value="<?= old('date_received', date('Y-m-d')) ?>"
                               class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary">
                    </div>
                    <div>
                        <label for="time_received" class="block text-sm font-medium text-gray-700">
                            Time Received <span class="text-red-500">*</span>
                        </label>
                        <input type="time" name="time_received" id="time_received" required
                               value="<?= old('time_received', date('H:i')) ?>"
                               class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary">
                    </div>
                </div>
            </section>

            <!-- Document Details -------------------------------------------->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-900">Document Details</h2>
                <p class="mt-1 text-sm text-gray-500">Basic information about the document</p>

                <div class="mt-4 space-y-4">
                    <div>
                        <label for="subject_matter" class="block text-sm font-medium text-gray-700">
                            Subject Matter <span class="text-red-500">*</span>
                        </label>
                        <textarea name="subject_matter" id="subject_matter" rows="4" required
                                  class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                                  placeholder="Enter the subject matter or title of the document (e.g., Request for Infrastructure Budget Allocation)"><?= htmlspecialchars(old('subject_matter') ?? '') ?></textarea>
                        <p class="mt-1 text-xs text-gray-500">Brief description or title of the document</p>
                    </div>

                    <div>
                        <label for="document_type_id" class="block text-sm font-medium text-gray-700">
                            Document Type <span class="text-red-500">*</span>
                        </label>
                        <select name="document_type_id" id="document_type_id" required
                                class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary">
                            <option value="">-- Select Document Type --</option>
                            <?php foreach ($documentTypes as $type): ?>
                                <option value="<?= (int) $type['id'] ?>"
                                        <?= old('document_type_id') == $type['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Select the type of document</p>
                    </div>
                </div>
            </section>

            <!-- Document Checklist ------------------------------------------>
            <section id="checklistSection" class="rounded-2xl border border-gray-200 bg-white p-6 hidden">
                <h2 class="text-lg font-semibold text-gray-900">Document Checklist</h2>
                <p class="mt-1 text-sm text-gray-500">Select all applicable checklist items for this document type</p>
                
                <div id="checklistLoading" class="mt-4 flex items-center justify-center py-8">
                    <svg class="animate-spin h-8 w-8 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="ml-3 text-sm text-gray-600">Loading checklist items...</span>
                </div>

                <div id="checklistItems" class="mt-4 hidden checklist-grid"></div>
                
                <style>
                    .checklist-grid {
                        display: grid;
                        grid-template-columns: repeat(2, 1fr);
                        gap: 8px 24px;
                    }
                    
                    @media (max-width: 768px) {
                        .checklist-grid {
                            grid-template-columns: 1fr;
                        }
                    }
                </style>

                <div id="checklistEmpty" class="mt-4 rounded-lg bg-gray-50 border border-gray-200 p-4 text-center hidden">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    <p class="mt-2 text-sm font-medium text-gray-700">No checklist items available</p>
                    <p class="mt-1 text-xs text-gray-500">This document type does not have any associated checklist items.</p>
                </div>
            </section>

            <!-- Source Information ------------------------------------------>
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-900">Source Information</h2>
                <p class="mt-1 text-sm text-gray-500">Details about the document source</p>

                <div class="mt-4 space-y-4">
                    <div>
                        <label for="source_type_id" class="block text-sm font-medium text-gray-700">
                            Source Type <span class="text-red-500">*</span>
                        </label>
                        <select name="source_type_id" id="source_type_id" required
                                class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary">
                            <option value="">-- Select Source Type --</option>
                            <?php foreach ($sourceTypes as $type): ?>
                                <option value="<?= (int) $type['id'] ?>"
                                        data-source-name="<?= htmlspecialchars($type['name']) ?>"
                                        <?= old('source_type_id') == $type['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- External Office Fields -->
                    <div id="externalOfficeFields" class="hidden space-y-4">
                        <div>
                            <label for="external_office_id" class="block text-sm font-medium text-gray-700">
                                External Office <span class="text-red-500">*</span>
                            </label>
                            <select name="external_office_id" id="external_office_id"
                                    class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary">
                                <option value="">-- Select External Office --</option>
                                <?php foreach ($externalOffices as $office): ?>
                                    <option value="<?= (int) $office['id'] ?>"
                                            <?= old('external_office_id') == $office['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($office['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Hospital Fields -->
                    <div id="hospitalFields" class="hidden space-y-4">
                        <div>
                            <label for="hospital_id" class="block text-sm font-medium text-gray-700">
                                Hospital <span class="text-red-500">*</span>
                            </label>
                            <select name="hospital_id" id="hospital_id"
                                    class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary">
                                <option value="">-- Select Hospital --</option>
                                <?php foreach ($hospitals as $hospital): ?>
                                    <option value="<?= (int) $hospital['id'] ?>"
                                            <?= old('hospital_id') == $hospital['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($hospital['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- SP Member Fields -->
                    <div id="spMemberFields" class="hidden space-y-4">
                        <div>
                            <label for="sp_member_id" class="block text-sm font-medium text-gray-700">
                                SP Member <span class="text-red-500">*</span>
                            </label>
                            <select name="sp_member_id" id="sp_member_id"
                                    class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary">
                                <option value="">-- Select SP Member --</option>
                                <?php foreach ($spMembers as $member):
                                    $fullName = trim(implode(' ', array_filter([
                                        $member['first_name']  ?? '',
                                        $member['middle_name'] ?? '',
                                        $member['last_name']   ?? '',
                                        $member['suffix']      ?? '',
                                    ])));
                                ?>
                                    <option value="<?= (int) $member['sp_member_id'] ?>"
                                            <?= old('sp_member_id') == $member['sp_member_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($fullName) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Agency Name Field -->
                    <div id="agencyNameField" class="hidden">
                        <label for="source_name_agency" class="block text-sm font-medium text-gray-700">
                            Agency Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="source_name" id="source_name_agency" disabled
                               value="<?= htmlspecialchars(old('source_name') ?? '') ?>"
                               class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                               placeholder="Enter agency name">
                    </div>

                    <!-- Client Name Field -->
                    <div id="clientNameField" class="hidden">
                        <label for="source_name_client" class="block text-sm font-medium text-gray-700">
                            Client Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="source_name" id="source_name_client" disabled
                               value="<?= htmlspecialchars(old('source_name') ?? '') ?>"
                               class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                               placeholder="Enter client name">
                    </div>

                    <!-- Municipality Field (for Client) -->
                    <div id="municipalityField" class="hidden">
                        <label for="municipality_id" class="block text-sm font-medium text-gray-700">
                            Municipality / City
                        </label>
                        <select name="municipality_id" id="municipality_id"
                                class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary">
                            <option value="">-- Select Municipality/City --</option>
                            <?php foreach ($municities as $muni): ?>
                                <option value="<?= (int) $muni['id'] ?>"
                                        <?= old('municipality_id') == $muni['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($muni['name']) ?> <?= $muni['type'] === 'City' ? '(City)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Common Contact Fields -->
                    <div id="contactFields" class="hidden space-y-4">
                        <div>
                            <label for="source_contact_number" class="block text-sm font-medium text-gray-700">
                                Contact Number
                            </label>
                            <input type="text" name="source_contact_number" id="source_contact_number"
                                   value="<?= htmlspecialchars(old('source_contact_number') ?? '') ?>"
                                   class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                                   placeholder="Enter contact number">
                        </div>
                        <div>
                            <label for="source_address" class="block text-sm font-medium text-gray-700">Address</label>
                            <textarea name="source_address" id="source_address" rows="2"
                                      class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                                      placeholder="Enter address"><?= htmlspecialchars(old('source_address') ?? '') ?></textarea>
                        </div>
                        <div>
                            <label for="source_liaison_name" class="block text-sm font-medium text-gray-700">
                                Liaison / Contact Person
                            </label>
                            <input type="text" name="source_liaison_name" id="source_liaison_name"
                                   value="<?= htmlspecialchars(old('source_liaison_name') ?? '') ?>"
                                   class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                                   placeholder="Enter liaison or contact person name">
                        </div>
                    </div>

                </div>
            </section>

            <!-- Attachments -------------------------------------------------->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-900">Attachments <span class="text-red-500">*</span></h2>
                <p class="mt-1 text-sm text-gray-500">
                    Upload document files (PDF, DOC, DOCX, XLS, XLSX, images). Maximum 10 files, 25 MB each.
                </p>

                <div class="mt-4">
                    <div class="flex items-center justify-center w-full">
                        <label for="attachments"
                               class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-xl cursor-pointer bg-gray-50 hover:bg-gray-100">
                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                <svg class="w-8 h-8 mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>
                                <p class="mb-1 text-sm text-gray-600">
                                    <span class="font-semibold">Click to upload</span> or drag and drop
                                </p>
                                <p class="text-xs text-gray-500">PDF, DOC, DOCX, XLS, XLSX, JPG, PNG (MAX. 25 MB each)</p>
                            </div>
                            <input id="attachments" name="attachments[]" type="file" multiple required
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp"
                                   class="hidden"/>
                        </label>
                    </div>
                    <div id="fileList" class="mt-4 space-y-2"></div>
                </div>
            </section>

            <!-- Remarks ----------------------------------------------------->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-900">Remarks / Notes</h2>
                <p class="mt-1 text-sm text-gray-500">Optional notes or remarks about this document</p>

                <div class="mt-4">
                    <textarea name="remarks" id="remarks" rows="3"
                              class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                              placeholder="Enter any additional remarks or notes…"><?= htmlspecialchars(old('remarks') ?? '') ?></textarea>
                </div>
            </section>

            <!-- Submit -------------------------------------------------------->
            <section class="flex items-center justify-end gap-3">
                <a href="<?= BASE_URL ?>/admin/inbox"
                   class="inline-flex items-center justify-center rounded-xl px-6 py-3 text-sm font-medium text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 focus:outline-none transition">
                    Cancel
                </a>
                <button type="submit" id="submitBtn"
                        class="inline-flex items-center justify-center rounded-xl px-6 py-3 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none transition">
                    <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Submit Document
                </button>
            </section>

        </div>
    </form>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const sourceTypeSelect = document.getElementById('source_type_id');
    const documentTypeSelect = document.getElementById('document_type_id');
    const attachmentsInput = document.getElementById('attachments');
    const fileList         = document.getElementById('fileList');
    const submitBtn        = document.getElementById('submitBtn');
    const form             = document.getElementById('documentForm');

    if (!sourceTypeSelect || !documentTypeSelect || !attachmentsInput || !fileList || !submitBtn || !form) return;

    // Document type change handler — Load checklists
    documentTypeSelect.addEventListener('change', function() {
        const documentTypeId = this.value;
        const checklistSection = document.getElementById('checklistSection');
        const checklistLoading = document.getElementById('checklistLoading');
        const checklistItems = document.getElementById('checklistItems');
        const checklistEmpty = document.getElementById('checklistEmpty');

        if (!documentTypeId) {
            checklistSection.classList.add('hidden');
            return;
        }

        // Show loading state
        checklistSection.classList.remove('hidden');
        checklistLoading.classList.remove('hidden');
        checklistItems.classList.add('hidden');
        checklistEmpty.classList.add('hidden');
        checklistItems.innerHTML = '';

        // Fetch checklists via AJAX
        fetch('<?= BASE_URL ?>/admin/receive-document/get-checklists?document_type_id=' + documentTypeId)
            .then(response => response.json())
            .then(data => {
                checklistLoading.classList.add('hidden');

                if (data.success && data.checklists && data.checklists.length > 0) {
                    // Display checklist items
                    checklistItems.classList.remove('hidden');
                    data.checklists.forEach(function(checklist) {
                        const checklistItem = document.createElement('div');
                        checklistItem.className = 'flex items-start space-x-3 p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition';
                        
                        const isRequired = checklist.is_required == 1;
                        const requiredBadge = isRequired ? '<span class="ml-2 inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">Required</span>' : '';
                        
                        checklistItem.innerHTML = `
                            <div class="flex items-center h-5">
                                <input type="checkbox" 
                                       name="checklist_items[]" 
                                       value="${checklist.id}" 
                                       id="checklist_${checklist.id}"
                                       class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary checklist-item ${isRequired ? 'required-checklist' : ''}"
                                       ${isRequired ? 'data-required="true"' : ''}>
                            </div>
                            <div class="flex-1 min-w-0">
                                <label for="checklist_${checklist.id}" class="text-sm font-medium text-gray-900 cursor-pointer flex items-center">
                                    ${escapeHtml(checklist.name)}
                                    ${requiredBadge}
                                </label>
                                ${checklist.description ? `<p class="mt-1 text-xs text-gray-500">${escapeHtml(checklist.description)}</p>` : ''}
                                ${checklist.notes ? `<p class="mt-1 text-xs text-blue-600"><strong>Note:</strong> ${escapeHtml(checklist.notes)}</p>` : ''}
                            </div>
                        `;
                        checklistItems.appendChild(checklistItem);
                    });
                } else {
                    // Show empty state
                    checklistEmpty.classList.remove('hidden');
                }
            })
            .catch(error => {
                console.error('Error loading checklists:', error);
                checklistLoading.classList.add('hidden');
                checklistEmpty.classList.remove('hidden');
            });
    });

    // Source type change handler — identical behaviour to Receiving form
    sourceTypeSelect.addEventListener('change', function () {
        const sourceName = this.options[this.selectedIndex]?.getAttribute('data-source-name') ?? '';

        document.getElementById('externalOfficeFields').classList.add('hidden');
        document.getElementById('hospitalFields').classList.add('hidden');
        document.getElementById('spMemberFields').classList.add('hidden');
        document.getElementById('agencyNameField').classList.add('hidden');
        document.getElementById('clientNameField').classList.add('hidden');
        document.getElementById('municipalityField').classList.add('hidden');
        document.getElementById('contactFields').classList.add('hidden');

        const extOffice    = document.getElementById('external_office_id');
        const hospEl       = document.getElementById('hospital_id');
        const spEl         = document.getElementById('sp_member_id');
        const agencyEl     = document.getElementById('source_name_agency');
        const clientEl     = document.getElementById('source_name_client');

        [extOffice, hospEl, spEl].forEach(el => { if (el) el.removeAttribute('required'); });
        [agencyEl, clientEl].forEach(el => {
            if (el) { el.removeAttribute('required'); el.disabled = true; }
        });

        switch (sourceName) {
            case 'External Office':
                document.getElementById('externalOfficeFields').classList.remove('hidden');
                document.getElementById('contactFields').classList.remove('hidden');
                if (extOffice) extOffice.setAttribute('required', 'required');
                break;
            case 'Hospital':
                document.getElementById('hospitalFields').classList.remove('hidden');
                document.getElementById('contactFields').classList.remove('hidden');
                if (hospEl) hospEl.setAttribute('required', 'required');
                break;
            case 'SP Member':
                document.getElementById('spMemberFields').classList.remove('hidden');
                document.getElementById('contactFields').classList.remove('hidden');
                if (spEl) spEl.setAttribute('required', 'required');
                break;
            case 'Agency':
                document.getElementById('agencyNameField').classList.remove('hidden');
                document.getElementById('contactFields').classList.remove('hidden');
                if (agencyEl) { agencyEl.setAttribute('required', 'required'); agencyEl.disabled = false; }
                break;
            case 'Client':
                document.getElementById('clientNameField').classList.remove('hidden');
                document.getElementById('municipalityField').classList.remove('hidden');
                document.getElementById('contactFields').classList.remove('hidden');
                if (clientEl) { clientEl.setAttribute('required', 'required'); clientEl.disabled = false; }
                break;
        }
    });

    // File list with remove capability
    const selected = new DataTransfer();

    attachmentsInput.addEventListener('change', function () {
        Array.from(this.files).forEach(f => selected.items.add(f));
        this.files = selected.files;
        updateFileList();
    });

    function updateFileList() {
        fileList.innerHTML = '';
        if (selected.files.length === 0) return;
        Array.from(selected.files).forEach((file, index) => {
            const sz = (file.size / 1024 / 1024).toFixed(2);
            const item = document.createElement('div');
            item.className = 'flex items-center justify-between p-3 border border-gray-200 rounded-lg bg-gray-50';
            item.innerHTML = `
                <div class="flex items-center space-x-3">
                    <svg class="h-8 w-8 text-blue-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">${escapeHtml(file.name)}</p>
                        <p class="text-xs text-gray-500">${sz} MB</p>
                    </div>
                </div>
                <button type="button" onclick="removeFile(${index})"
                        class="ml-2 flex-shrink-0 p-1.5 text-red-600 hover:bg-red-50 rounded-lg transition">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            `;
            fileList.appendChild(item);
        });
    }

    window.removeFile = function (index) {
        const dt = new DataTransfer();
        Array.from(selected.files).forEach((f, i) => { if (i !== index) dt.items.add(f); });
        selected.items.clear();
        Array.from(dt.files).forEach(f => selected.items.add(f));
        attachmentsInput.files = selected.files;
        updateFileList();
    };

    // Submit button spinner with checklist validation
    form.addEventListener('submit', function (e) {
        // Validate required checklist items
        const requiredChecklists = document.querySelectorAll('.required-checklist');
        const uncheckedRequired = [];
        
        requiredChecklists.forEach(function(checkbox) {
            if (!checkbox.checked) {
                const label = document.querySelector(`label[for="${checkbox.id}"]`);
                const checklistName = label ? label.textContent.trim() : 'Unknown checklist';
                uncheckedRequired.push(checklistName);
            }
        });

        if (uncheckedRequired.length > 0) {
            e.preventDefault();
            alert('Please complete all required checklist items:\n\n' + uncheckedRequired.join('\n'));
            return false;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = `
            <svg class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg"
                 fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor"
                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
            </svg>
            Submitting…
        `;
    });

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        return String(text).replace(/[&<>"']/g, m => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        })[m]);
    }

    // Repopulate source type on page load after validation error
    if (sourceTypeSelect.value) {
        sourceTypeSelect.dispatchEvent(new Event('change'));
    }
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

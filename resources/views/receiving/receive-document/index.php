<?php
// Safe variable defaults to prevent undefined warnings
$user = auth();
$pageTitle = isset($pageTitle) ? $pageTitle : 'Route Document';
$trackingNumberPreview = isset($trackingNumberPreview) ? $trackingNumberPreview : '';
$documentTypes = (isset($documentTypes) && is_array($documentTypes)) ? $documentTypes : [];
$sourceTypes = (isset($sourceTypes) && is_array($sourceTypes)) ? $sourceTypes : [];
$externalOffices = (isset($externalOffices) && is_array($externalOffices)) ? $externalOffices : [];
$hospitals = (isset($hospitals) && is_array($hospitals)) ? $hospitals : [];
$spMembers = (isset($spMembers) && is_array($spMembers)) ? $spMembers : [];
$municities = (isset($municities) && is_array($municities)) ? $municities : [];

ob_start();
?>

<div class="space-y-6">

    <!-- Page Header -->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10">
                <p class="text-sm font-medium text-blue-100">RECEIVING SECTION</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    Receive Document
                </h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-blue-100">
                    Receive and log incoming documents, assign tracking numbers, and route to Admin for processing.
                </p>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
        </div>
    </section>

    <!-- Form -->
    <form method="POST" action="<?= BASE_URL ?>/receiving/receive-document/submit" enctype="multipart/form-data" id="documentForm">
        
        <div class="space-y-6">

            <!-- Tracking Number Preview -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-900">Tracking Number</h2>
                <p class="mt-1 text-sm text-gray-500">Automatically generated tracking number for this document</p>
                <div class="mt-4 rounded-xl bg-blue-50 px-4 py-3 border border-blue-200">
                    <p class="text-xs font-medium text-blue-700 uppercase tracking-wide">Tracking Number</p>
                    <p class="mt-1 text-2xl font-bold text-blue-900" id="trackingNumberPreview"><?= htmlspecialchars($trackingNumberPreview) ?></p>
                </div>
            </section>

            <!-- Receipt Information -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-900">Receipt Information</h2>
                <p class="mt-1 text-sm text-gray-500">Date and time the document was received</p>
                
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="date_received" class="block text-sm font-medium text-gray-700">Date Received <span class="text-red-500">*</span></label>
                        <input type="date" name="date_received" id="date_received" required
                               value="<?= old('date_received', date('Y-m-d')) ?>"
                               class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary">
                    </div>
                    
                    <div>
                        <label for="time_received" class="block text-sm font-medium text-gray-700">Time Received <span class="text-red-500">*</span></label>
                        <input type="time" name="time_received" id="time_received" required
                               value="<?= old('time_received', date('H:i')) ?>"
                               class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary">
                    </div>
                </div>
            </section>

            <!-- Document Details -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-900">Document Details</h2>
                <p class="mt-1 text-sm text-gray-500">Basic information about the document</p>
                
                <div class="mt-4 space-y-4">
                    <div>
                        <label for="subject_matter" class="block text-sm font-medium text-gray-700">Subject Matter <span class="text-red-500">*</span></label>
                        <textarea name="subject_matter" id="subject_matter" rows="4" required
                                  class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                                  placeholder="Enter the subject matter or title of the document (e.g., Request for Infrastructure Budget Allocation)"><?= htmlspecialchars(old('subject_matter') ?? '') ?></textarea>
                        <p class="mt-1 text-xs text-gray-500">Brief description or title of the document</p>
                    </div>

                    <div>
                        <label for="document_type_id" class="block text-sm font-medium text-gray-700">Document Type <span class="text-red-500">*</span></label>
                        <select name="document_type_id" id="document_type_id" required
                                class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary">
                            <option value="">-- Select Document Type --</option>
                            <?php foreach ($documentTypes as $type): ?>
                                <option value="<?= $type['id'] ?? '' ?>" title="<?= htmlspecialchars($type['name'] ?? '') ?>" <?= old('document_type_id') == ($type['id'] ?? '') ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type['name'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p id="documentTypeFullLabel" class="mt-1 hidden break-words text-sm text-gray-700" aria-live="polite"></p>
                        <script>
                            (function () {
                                const select = document.getElementById('document_type_id');
                                const fullLabel = document.getElementById('documentTypeFullLabel');
                                if (!select || !fullLabel) return;

                                const updateDocumentTypeLabel = function () {
                                    const option = select.selectedOptions[0];
                                    const label = option && option.value ? option.textContent.trim() : '';
                                    fullLabel.textContent = label;
                                    fullLabel.classList.toggle('hidden', label === '');
                                    select.title = label;
                                };

                                select.addEventListener('change', updateDocumentTypeLabel);
                                updateDocumentTypeLabel();
                            }());
                        </script>
                        <p class="mt-1 text-xs text-gray-500">Select the type of document</p>
                    </div>
                </div>
            </section>

            <!-- Motion for Reconsideration — Reference Case -->
            <?php
            // Compute the MFR type IDs for the data attribute used by JS.
            $mfrTypeIdList = isset($mfrTypeNames) ? implode(',', $mfrTypeNames) : '';
            ?>
            <section id="mfrReferenceSection" class="rounded-2xl border border-amber-200 bg-amber-50 p-6 hidden">
                <h2 class="text-lg font-semibold text-gray-900">
                    Reference Case
                    <span class="ml-2 inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Required for Motion for Reconsideration</span>
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    A Motion for Reconsideration must reference the original Complaint or Administrative Case that has been finalized by Plenary.
                    Search by tracking number, docket number, or subject matter.
                </p>

                <!-- Hidden field that holds the selected case ID for form submission -->
                <input type="hidden" name="referenced_document_id" id="referenced_document_id" value="<?= htmlspecialchars(old('referenced_document_id', '')) ?>">

                <div class="mt-4 space-y-3">

                    <!-- Search input -->
                    <div class="relative">
                        <label for="mfrCaseSearch" class="block text-sm font-medium text-gray-700">
                            Search original case <span class="text-red-500" id="mfrSearchRequired">*</span>
                        </label>
                        <div class="relative mt-1">
                            <input type="text" id="mfrCaseSearch" autocomplete="off"
                                   placeholder="Type tracking number, docket, or subject…"
                                   class="block w-full rounded-lg border border-gray-300 py-2 pl-9 pr-3 text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                   aria-label="Search reference case" aria-autocomplete="list" aria-controls="mfrDropdown">
                            <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                            </svg>
                            <!-- Spinner shown during search -->
                            <svg id="mfrSearchSpinner" class="absolute right-3 top-2.5 h-4 w-4 text-amber-500 animate-spin hidden" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                        <!-- Search results dropdown -->
                        <ul id="mfrDropdown"
                            class="absolute z-20 mt-1 hidden w-full overflow-auto rounded-xl border border-amber-200 bg-white shadow-lg max-h-72 text-sm"
                            role="listbox">
                        </ul>
                    </div>

                    <p class="text-xs text-gray-500">
                        Only finalized Complaint and Administrative Case documents are listed.
                    </p>

                    <!-- Preview of the currently selected case -->
                    <div id="mfrCasePreview" class="hidden rounded-xl border border-amber-200 bg-white p-4 text-sm space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-gray-800" id="mfrPreviewTracking"></p>
                                <p class="text-gray-600 text-xs mt-0.5" id="mfrPreviewSubject"></p>
                            </div>
                            <button type="button" id="mfrClearBtn"
                                    class="flex-shrink-0 rounded-lg p-1 text-gray-400 hover:text-red-500 hover:bg-red-50 transition"
                                    title="Clear selection" aria-label="Clear selected case">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700" id="mfrPreviewType"></span>
                            <span class="inline-flex items-center rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700" id="mfrPreviewStatus"></span>
                        </div>
                        <div id="mfrPreviewDocket" class="hidden text-xs text-gray-500">
                            Docket: <span class="font-medium text-gray-700" id="mfrPreviewDocketNum"></span>
                        </div>
                    </div>

                    <!-- No results notice -->
                    <div id="mfrNoResults" class="hidden rounded-lg border border-gray-200 bg-gray-50 p-3 text-center text-xs text-gray-500">
                        No finalized cases match your search. Try a different tracking number, docket, or subject.
                    </div>

                </div>
            </section>

            <!-- Store MFR type IDs and AJAX URL for JS -->
            <script>
                window.__mfrTypeIds   = [<?= $mfrTypeIdList ?>];
                window.__mfrSearchUrl = '<?= BASE_URL ?>/receiving/receive-document/get-eligible-cases';
            </script>

            <!-- Document Checklist -->
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

            <!-- Source Information -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-900">Source Information</h2>
                <p class="mt-1 text-sm text-gray-500">Details about the document source</p>
                
                <div class="mt-4 space-y-4">
                    <div>
                        <label for="source_type_id" class="block text-sm font-medium text-gray-700">Source Type <span class="text-red-500">*</span></label>
                        <select name="source_type_id" id="source_type_id" required
                                class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary">
                            <option value="">-- Select Source Type --</option>
                            <?php foreach ($sourceTypes as $type): ?>
                                <option value="<?= $type['id'] ?? '' ?>" data-source-name="<?= htmlspecialchars($type['name'] ?? '') ?>" <?= old('source_type_id') == ($type['id'] ?? '') ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type['name'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Provincial Government Offices Fields -->
                    <div id="externalOfficeFields" class="hidden space-y-4">
                        <div>
                            <label for="external_office_id" class="block text-sm font-medium text-gray-700">Provincial Government Offices <span class="text-red-500">*</span></label>
                            <select name="external_office_id" id="external_office_id"
                                    class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary">
                                <option value="">-- Select Provincial Government Offices --</option>
                                <?php foreach ($externalOffices as $office): ?>
                                    <option value="<?= $office['id'] ?? '' ?>" 
                                            <?= old('external_office_id') == ($office['id'] ?? '') ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($office['name'] ?? '') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Hospital Fields -->
                    <div id="hospitalFields" class="hidden space-y-4">
                        <div>
                            <label for="hospital_id" class="block text-sm font-medium text-gray-700">Hospital <span class="text-red-500">*</span></label>
                            <select name="hospital_id" id="hospital_id"
                                    class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary">
                                <option value="">-- Select Hospital --</option>
                                <?php foreach ($hospitals as $hospital): ?>
                                    <option value="<?= $hospital['id'] ?? '' ?>" 
                                            <?= old('hospital_id') == ($hospital['id'] ?? '') ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($hospital['name'] ?? '') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- SP Member Fields -->
                    <div id="spMemberFields" class="hidden space-y-4">
                        <div>
                            <label for="sp_member_id" class="block text-sm font-medium text-gray-700">SP Member <span class="text-red-500">*</span></label>
                            <select name="sp_member_id" id="sp_member_id"
                                    class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary">
                                <option value="">-- Select SP Member --</option>
                                <?php foreach ($spMembers as $member): 
                                    $fullName = trim(implode(' ', array_filter([
                                        $member['first_name'] ?? '',
                                        $member['middle_name'] ?? '',
                                        $member['last_name'] ?? '',
                                        $member['suffix'] ?? ''
                                    ])));
                                ?>
                                    <option value="<?= $member['sp_member_id'] ?? '' ?>" <?= old('sp_member_id') == ($member['sp_member_id'] ?? '') ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($fullName) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Agency Name Field -->
                    <div id="agencyNameField" class="hidden">
                        <label for="source_name_agency" class="block text-sm font-medium text-gray-700">Agency Name <span class="text-red-500">*</span></label>
                        <input type="text" name="source_name" id="source_name_agency" disabled
                               value="<?= htmlspecialchars(old('source_name') ?? '') ?>"
                               class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                               placeholder="Enter agency name">
                    </div>

                    <!-- Client Name Field -->
                    <div id="clientNameField" class="hidden">
                        <label for="source_name_client" class="block text-sm font-medium text-gray-700">Client Name <span class="text-red-500">*</span></label>
                        <input type="text" name="source_name" id="source_name_client" disabled
                               value="<?= htmlspecialchars(old('source_name') ?? '') ?>"
                               class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                               placeholder="Enter client name">
                    </div>

                    <!-- Municipality Field (for Client) -->
                    <div id="municipalityField" class="hidden">
                        <label for="municipality_id" class="block text-sm font-medium text-gray-700">
                            Municipality / City <span class="text-red-500">*</span>
                        </label>
                        <select name="municipality_id" id="municipality_id"
                                class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary">
                            <option value="">-- Select Municipality/City --</option>
                            <?php foreach ($municities as $muni): ?>
                                <option value="<?= $muni['id'] ?? '' ?>" <?= old('municipality_id') == ($muni['id'] ?? '') ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($muni['name'] ?? '') ?> <?= ($muni['type'] ?? '') === 'City' ? '(City)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Common Contact Fields -->
                    <div id="contactFields" class="hidden space-y-4">
                        <div>
                            <label for="source_contact_number" class="block text-sm font-medium text-gray-700">Contact Number</label>
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
                            <label for="source_liaison_name" class="block text-sm font-medium text-gray-700">Liaison / Contact Person</label>
                            <input type="text" name="source_liaison_name" id="source_liaison_name"
                                   value="<?= htmlspecialchars(old('source_liaison_name') ?? '') ?>"
                                   class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                                   placeholder="Enter liaison or contact person name">
                        </div>
                    </div>
                </div>
            </section>

            <!-- Attachments -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-900">Attachments <span class="text-red-500">*</span></h2>
                <p class="mt-1 text-sm text-gray-500">Upload document files (PDF, DOC, DOCX, XLS, XLSX, images). Maximum 10 files, 25MB each.</p>
                
                <div class="mt-4">
                    <div class="flex items-center justify-center w-full">
                        <label for="attachments" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-xl cursor-pointer bg-gray-50 hover:bg-gray-100">
                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                <svg class="w-8 h-8 mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                <p class="mb-1 text-sm text-gray-600"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                                <p class="text-xs text-gray-500">PDF, DOC, DOCX, XLS, XLSX, JPG, PNG (MAX. 25MB each)</p>
                            </div>
                            <input id="attachments" name="attachments[]" type="file" multiple required accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp" class="hidden" />
                        </label>
                    </div>

                    <div id="fileList" class="mt-4 space-y-2"></div>
                </div>
            </section>

            <!-- Remarks -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-900">Remarks / Notes</h2>
                <p class="mt-1 text-sm text-gray-500">Optional notes or remarks about this document</p>
                
                <div class="mt-4">
                    <textarea name="remarks" id="remarks" rows="3"
                              class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                              placeholder="Enter any additional remarks or notes..."><?= htmlspecialchars(old('remarks') ?? '') ?></textarea>
                </div>
            </section>

            <!-- Submit Button -->
            <section class="flex items-center justify-end gap-3">
                <a href="<?= BASE_URL ?>/receiving/dashboard" 
                   class="inline-flex items-center justify-center rounded-xl px-6 py-3 text-sm font-medium text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-300 transition">
                    Cancel
                </a>
                <button type="button" id="printTrackingSlip"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-primary px-6 py-3 text-sm font-medium text-primary hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"/>
                    </svg>
                    Receipt / Print
                </button>
                <button type="submit" id="submitBtn"
                        class="inline-flex items-center justify-center rounded-xl px-6 py-3 text-sm font-medium text-white bg-primary hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition">
                    <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Submit Document
                </button>
            </section>

        </div>
    </form>

    <?php $receiptDefaultDestination = 'ADMIN'; ?>
    <?php require __DIR__ . '/../../partials/tracking-slip.php'; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sourceTypeSelect = document.getElementById('source_type_id');
    const documentTypeSelect = document.getElementById('document_type_id');
    const attachmentsInput = document.getElementById('attachments');
    const fileList = document.getElementById('fileList');
    const submitBtn = document.getElementById('submitBtn');
    const form = document.getElementById('documentForm');

    // Null checks for critical elements
    if (!sourceTypeSelect || !documentTypeSelect || !attachmentsInput || !fileList || 
        !submitBtn || !form) {
        console.error('Required DOM elements not found');
        return;
    }

    // Document type change handler - Load checklists
    documentTypeSelect.addEventListener('change', function() {
        const documentTypeId = this.value;
        const checklistSection = document.getElementById('checklistSection');
        const checklistLoading = document.getElementById('checklistLoading');
        const checklistItems = document.getElementById('checklistItems');
        const checklistEmpty = document.getElementById('checklistEmpty');

        if (!documentTypeId) {
            checklistSection.classList.add('hidden');
            hideMfrSection();
            return;
        }

        // Show / hide MFR reference section
        const mfrTypeIds = window.__mfrTypeIds || [];
        if (mfrTypeIds.includes(parseInt(documentTypeId, 10))) {
            showMfrSection();
        } else {
            hideMfrSection();
        }

        // Show loading state
        checklistSection.classList.remove('hidden');
        checklistLoading.classList.remove('hidden');
        checklistItems.classList.add('hidden');
        checklistEmpty.classList.add('hidden');
        checklistItems.innerHTML = '';

        // Fetch checklists via AJAX
        fetch('<?= BASE_URL ?>/receiving/receive-document/get-checklists?document_type_id=' + documentTypeId)
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

    // Source type change handler
    sourceTypeSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const sourceName = selectedOption.getAttribute('data-source-name');
        
        // Hide all source-specific fields
        document.getElementById('externalOfficeFields').classList.add('hidden');
        document.getElementById('hospitalFields').classList.add('hidden');
        document.getElementById('spMemberFields').classList.add('hidden');
        document.getElementById('agencyNameField').classList.add('hidden');
        document.getElementById('clientNameField').classList.add('hidden');
        document.getElementById('municipalityField').classList.add('hidden');
        document.getElementById('contactFields').classList.add('hidden');

        // Clear and disable all source-specific required attributes
        document.getElementById('external_office_id').removeAttribute('required');
        document.getElementById('hospital_id').removeAttribute('required');
        document.getElementById('sp_member_id').removeAttribute('required');
        document.getElementById('source_name_agency').removeAttribute('required');
        document.getElementById('source_name_agency').disabled = true;
        document.getElementById('source_name_client').removeAttribute('required');
        document.getElementById('source_name_client').disabled = true;
        document.getElementById('municipality_id').removeAttribute('required');

        // Show relevant fields based on source type
        if (sourceName === 'Provincial Government Offices') {
            document.getElementById('externalOfficeFields').classList.remove('hidden');
            document.getElementById('contactFields').classList.remove('hidden');
            document.getElementById('external_office_id').setAttribute('required', 'required');
        } else if (sourceName === 'Hospital') {
            document.getElementById('hospitalFields').classList.remove('hidden');
            document.getElementById('contactFields').classList.remove('hidden');
            document.getElementById('hospital_id').setAttribute('required', 'required');
        } else if (sourceName === 'SP Member') {
            document.getElementById('spMemberFields').classList.remove('hidden');
            document.getElementById('contactFields').classList.remove('hidden');
            document.getElementById('sp_member_id').setAttribute('required', 'required');
        } else if (sourceName === 'Agency') {
            document.getElementById('agencyNameField').classList.remove('hidden');
            document.getElementById('contactFields').classList.remove('hidden');
            document.getElementById('source_name_agency').setAttribute('required', 'required');
            document.getElementById('source_name_agency').disabled = false;
        } else if (sourceName === 'Client') {
            document.getElementById('clientNameField').classList.remove('hidden');
            document.getElementById('municipalityField').classList.remove('hidden');
            document.getElementById('contactFields').classList.remove('hidden');
            document.getElementById('source_name_client').setAttribute('required', 'required');
            document.getElementById('source_name_client').disabled = false;
            document.getElementById('municipality_id').setAttribute('required', 'required');
        }
    });

    // File upload display
    const selectedFiles = new DataTransfer();
    
    attachmentsInput.addEventListener('change', function() {
        Array.from(this.files).forEach(file => {
            selectedFiles.items.add(file);
        });
        
        updateFileList();
        this.files = selectedFiles.files;
    });

    function updateFileList() {
        fileList.innerHTML = '';
        
        if (selectedFiles.files.length === 0) {
            return;
        }

        Array.from(selectedFiles.files).forEach((file, index) => {
            const fileSize = (file.size / 1024 / 1024).toFixed(2);
            const fileItem = document.createElement('div');
            fileItem.className = 'flex items-center justify-between p-3 border border-gray-200 rounded-lg bg-gray-50';
            fileItem.innerHTML = `
                <div class="flex items-center space-x-3">
                    <svg class="h-8 w-8 text-blue-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                    </svg>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">${escapeHtml(file.name)}</p>
                        <p class="text-xs text-gray-500">${fileSize} MB</p>
                    </div>
                </div>
                <button type="button" onclick="removeFile(${index})" class="ml-2 flex-shrink-0 p-1.5 text-red-600 hover:bg-red-50 rounded-lg transition">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            `;
            fileList.appendChild(fileItem);
        });
    }

    window.removeFile = function(index) {
        const dt = new DataTransfer();
        Array.from(selectedFiles.files).forEach((file, i) => {
            if (i !== index) {
                dt.items.add(file);
            }
        });
        
        selectedFiles.items.clear();
        Array.from(dt.files).forEach(file => {
            selectedFiles.items.add(file);
        });
        
        attachmentsInput.files = selectedFiles.files;
        updateFileList();
    };

    // Form submission with checklist validation
    form.addEventListener('submit', function(e) {
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

        // Validate MFR reference field when the section is visible
        const mfrSection    = document.getElementById('mfrReferenceSection');
        const mfrHiddenInput = document.getElementById('referenced_document_id');
        if (mfrSection && !mfrSection.classList.contains('hidden')) {
            if (!mfrHiddenInput || !mfrHiddenInput.value) {
                e.preventDefault();
                const searchInput = document.getElementById('mfrCaseSearch');
                if (searchInput) {
                    searchInput.classList.add('border-red-400', 'ring-1', 'ring-red-400');
                    searchInput.focus();
                }
                alert('Please search for and select the original Complaint or Administrative Case before submitting.');
                return false;
            }
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = `
            <svg class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Submitting...
        `;
    });

    // Helper function to escape HTML
    function escapeHtml(text) {
        if (text === null || text === undefined) {
            return '';
        }
        text = String(text); // Convert to string
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    // Trigger source type change on page load if old value exists
    if (sourceTypeSelect.value) {
        sourceTypeSelect.dispatchEvent(new Event('change'));
    }

    // =========================================================================
    // Motion for Reconsideration — live search helpers
    // =========================================================================

    /**
     * Show the MFR reference section and wire up the search field.
     * ajaxUrl is window.__mfrSearchUrl (set in the PHP section above).
     */
    function showMfrSection() {
        const section = document.getElementById('mfrReferenceSection');
        if (!section) return;
        section.classList.remove('hidden');

        // Restore a previously selected value after a validation error
        const oldVal = <?= json_encode(old('referenced_document_id', '')) ?>;
        if (oldVal) {
            // Fetch the single case by ID so the preview can be populated
            fetch(window.__mfrSearchUrl + '?q=' + encodeURIComponent(oldVal))
                .then(r => r.json())
                .then(data => {
                    if (data.success && Array.isArray(data.cases)) {
                        const match = data.cases.find(c => String(c.id) === String(oldVal));
                        if (match) selectMfrCase(match);
                    }
                })
                .catch(() => {});
        }
    }

    function hideMfrSection() {
        const section = document.getElementById('mfrReferenceSection');
        if (!section) return;
        section.classList.add('hidden');
        clearMfrSelection();
    }

    /** Clear the hidden field, search input, and preview card */
    function clearMfrSelection() {
        const hiddenInput = document.getElementById('referenced_document_id');
        const searchInput = document.getElementById('mfrCaseSearch');
        const preview     = document.getElementById('mfrCasePreview');
        const noResults   = document.getElementById('mfrNoResults');
        const dropdown    = document.getElementById('mfrDropdown');
        if (hiddenInput) { hiddenInput.value = ''; }
        if (searchInput) { searchInput.value = ''; }
        if (preview)     { preview.classList.add('hidden'); }
        if (noResults)   { noResults.classList.add('hidden'); }
        if (dropdown)    { dropdown.classList.add('hidden'); dropdown.innerHTML = ''; }
    }

    /** Populate the preview card and set the hidden input value */
    function selectMfrCase(c) {
        const hiddenInput = document.getElementById('referenced_document_id');
        const searchInput = document.getElementById('mfrCaseSearch');
        const preview     = document.getElementById('mfrCasePreview');
        const dropdown    = document.getElementById('mfrDropdown');

        if (hiddenInput) hiddenInput.value = c.id;
        if (searchInput) searchInput.value = c.tracking_number || '';
        if (dropdown)    { dropdown.classList.add('hidden'); dropdown.innerHTML = ''; }

        document.getElementById('mfrPreviewTracking').textContent = c.tracking_number  || '';
        document.getElementById('mfrPreviewSubject').textContent  = c.subject_matter   || '';
        document.getElementById('mfrPreviewType').textContent     = c.document_type_name || '';
        document.getElementById('mfrPreviewStatus').textContent   = c.status_name       || '';

        const docketEl  = document.getElementById('mfrPreviewDocket');
        const docketNum = document.getElementById('mfrPreviewDocketNum');
        if (c.docket_number) {
            docketNum.textContent = c.docket_number;
            docketEl.classList.remove('hidden');
        } else {
            docketEl.classList.add('hidden');
        }
        if (preview) preview.classList.remove('hidden');
    }

    // Wire up the search input once the DOM is ready
    (function initMfrSearch() {
        const searchInput = document.getElementById('mfrCaseSearch');
        const dropdown    = document.getElementById('mfrDropdown');
        const spinner     = document.getElementById('mfrSearchSpinner');
        const noResults   = document.getElementById('mfrNoResults');
        const clearBtn    = document.getElementById('mfrClearBtn');

        if (!searchInput || !dropdown) return;

        let _debounceTimer = null;

        searchInput.addEventListener('input', function () {
            const q = this.value.trim();
            clearTimeout(_debounceTimer);
            noResults.classList.add('hidden');
            dropdown.classList.add('hidden');
            dropdown.innerHTML = '';

            if (q.length < 2) return; // require at least 2 chars

            _debounceTimer = setTimeout(() => {
                spinner.classList.remove('hidden');
                fetch(window.__mfrSearchUrl + '?q=' + encodeURIComponent(q))
                    .then(r => r.json())
                    .then(data => {
                        spinner.classList.add('hidden');
                        const cases = (data.success && Array.isArray(data.cases)) ? data.cases : [];
                        if (cases.length === 0) {
                            noResults.classList.remove('hidden');
                            return;
                        }
                        // Render dropdown items
                        cases.forEach(c => {
                            const li = document.createElement('li');
                            li.setAttribute('role', 'option');
                            li.className = 'cursor-pointer px-4 py-3 hover:bg-amber-50 border-b border-gray-100 last:border-0';
                            const docketBit = c.docket_number
                                ? `<span class="ml-2 text-gray-400">DKT: ${escapeHtml(c.docket_number)}</span>` : '';
                            li.innerHTML = `
                                <p class="font-semibold text-gray-800 text-xs">${escapeHtml(c.tracking_number || '')}${docketBit}</p>
                                <p class="text-gray-500 text-xs mt-0.5 truncate">${escapeHtml((c.subject_matter || '').substring(0, 100))}</p>
                                <span class="inline-block mt-0.5 rounded-full bg-blue-50 px-2 py-0.5 text-xs text-blue-700">${escapeHtml(c.document_type_name || '')}</span>
                            `;
                            li.addEventListener('mousedown', function (e) {
                                e.preventDefault(); // keep focus on input
                                selectMfrCase(c);
                                noResults.classList.add('hidden');
                            });
                            dropdown.appendChild(li);
                        });
                        dropdown.classList.remove('hidden');
                    })
                    .catch(() => { spinner.classList.add('hidden'); });
            }, 280);
        });

        // Hide dropdown on blur (use mousedown+preventDefault above to avoid premature close)
        searchInput.addEventListener('blur', function () {
            setTimeout(() => {
                dropdown.classList.add('hidden');
                dropdown.innerHTML = '';
            }, 200);
        });

        // Clear button
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                clearMfrSelection();
                searchInput.focus();
            });
        }

        // Keyboard navigation in the dropdown
        searchInput.addEventListener('keydown', function (e) {
            const items = dropdown.querySelectorAll('li');
            if (!items.length) return;
            const focused = dropdown.querySelector('li.bg-amber-100');
            let idx = -1;
            items.forEach((el, i) => { if (el === focused) idx = i; });

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (focused) focused.classList.remove('bg-amber-100');
                const next = items[idx + 1] || items[0];
                next.classList.add('bg-amber-100');
                next.scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (focused) focused.classList.remove('bg-amber-100');
                const prev = items[idx - 1] || items[items.length - 1];
                prev.classList.add('bg-amber-100');
                prev.scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter' && focused) {
                e.preventDefault();
                focused.dispatchEvent(new Event('mousedown'));
            } else if (e.key === 'Escape') {
                dropdown.classList.add('hidden');
                dropdown.innerHTML = '';
            }
        });
    })();

    // Trigger document type change on page load if old value exists (error repopulation)
    if (documentTypeSelect.value) {
        documentTypeSelect.dispatchEvent(new Event('change'));
    }
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';
?>

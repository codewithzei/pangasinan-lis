<?php
/**
 * Receiving Inbox — Returned Document Edit View
 *
 * Variables supplied by ReceivingInboxController::show():
 *   $document           array   Document record with all joined data
 *   $assignment         array   Current Receiving assignment
 *   $declineInfo        array   Admin decline information (decline_reason, declined_at, declined_by)
 *   $attachments        array   Document attachments
 *   $documentTypes      array   Available document types for dropdown
 *   $sourceTypes        array   Available source types for dropdown
 *   $externalOffices    array   Available external offices
 *   $hospitals          array   Available hospitals
 *   $municipalities     array   Available municipalities
 *   $spMembers          array   Available SP members
 *   $success            string|null
 *   $error              string|null
 *   $errors             array
 *   $old                array   Old form input for validation errors
 */

$document        = $document        ?? [];
$assignment      = $assignment      ?? [];
$declineInfo     = $declineInfo     ?? [];
$attachments     = $attachments     ?? [];
$documentTypes   = $documentTypes   ?? [];
$sourceTypes     = $sourceTypes     ?? [];
$externalOffices = $externalOffices ?? [];
$hospitals       = $hospitals       ?? [];
$municipalities  = $municipalities  ?? [];
$spMembers       = $spMembers       ?? [];
$success         = $success         ?? null;
$error           = $error           ?? null;
$errors          = $errors          ?? [];
$old             = $old             ?? [];

ob_start();

/** Format datetime for display */
function formatDateTime(?string $datetime): string {
    if (!$datetime) return '—';
    $dt = new DateTime($datetime);
    return $dt->format('M d, Y g:i A');
}

/** Return old input value when available, otherwise fall back to the document field */
function oldOrDoc(string $key, array $old, array $document, $default = '') {
    return $old[$key] ?? $document[$key] ?? $default;
}

/**
 * Normalize a TIME value from the database (HH:MM:SS) to the HH:MM format
 * required by <input type="time">. Already-valid HH:MM values pass through
 * unchanged. Returns an empty string for null / empty input.
 */
function normalizeTimeForInput(?string $time): string {
    if ($time === null || $time === '') {
        return '';
    }
    // Truncate seconds portion if present (HH:MM:SS → HH:MM)
    return substr($time, 0, 5);
}
?>

<div class="space-y-6">

    <!-- Page Header -->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10">
                <p class="text-sm font-medium text-blue-100">RECEIVING / INBOX / EDIT DOCUMENT</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    Edit Returned Document
                </h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-blue-100">
                    Correct the document details and re-submit to Admin for processing.
                </p>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
        </div>
    </section>

    <!-- Flash Messages -->
    <?php if (isset($success) && $success): ?>
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

    <?php if (isset($error) && $error): ?>
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

    <?php if (isset($errors) && !empty($errors)): ?>
        <div class="rounded-2xl border border-red-200 bg-red-50 p-4">
            <div class="flex items-start">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-100">
                    <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
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

    <!-- Return Reason Alert -->
    <?php if (!empty($declineInfo['decline_reason'])): ?>
        <div class="rounded-2xl border border-red-300 bg-red-50 p-5">
            <div class="flex items-start gap-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-100">
                    <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-sm font-semibold text-red-900">Document Returned by Admin</h3>
                    <p class="mt-1 text-sm text-red-800">
                        <span class="font-medium">Reason:</span>
                        <?= htmlspecialchars($declineInfo['decline_reason']) ?>
                    </p>
                    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-red-700">
                        <span>
                            <span class="font-medium">Returned by:</span>
                            <?= htmlspecialchars($declineInfo['declined_by_name'] ?? $declineInfo['declined_by_username'] ?? 'Unknown') ?>
                        </span>
                        <span>
                            <span class="font-medium">Date:</span>
                            <?= formatDateTime($declineInfo['declined_at'] ?? null) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Document Info Card -->
    <section class="rounded-2xl border border-gray-200 bg-white p-6">
        <div class="flex items-center justify-between border-b border-gray-100 pb-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Document Information</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Tracking Number:
                    <span class="font-medium text-primary"><?= htmlspecialchars($document['tracking_number'] ?? '—') ?></span>
                </p>
            </div>
            <a href="<?= BASE_URL ?>/receiving/inbox"
               class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                ← Back to Inbox
            </a>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <p class="text-xs font-medium text-gray-500">Current Status</p>
                <?php if (!empty($document['status_badge_color'])): ?>
                    <span class="mt-1 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium text-white"
                          style="background-color: <?= htmlspecialchars($document['status_badge_color']) ?>;">
                        <?= htmlspecialchars($document['status'] ?? '') ?>
                    </span>
                <?php else: ?>
                    <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($document['status'] ?? '—') ?></p>
                <?php endif; ?>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500">Current Phase</p>
                <span class="mt-1 inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">
                    <?= htmlspecialchars($document['current_phase'] ?? '—') ?>
                </span>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500">Date Received</p>
                <p class="mt-1 text-sm text-gray-900">
                    <?= htmlspecialchars($document['date_received'] ?? '—') ?>
                    <?php if (!empty($document['time_received'])): ?>
                        at <?= htmlspecialchars($document['time_received']) ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </section>

    <!--
        editDocumentForm — covers Receipt Info, Document Details, Source Info,
        Attachments, Remarks, and the Submit button. Declared here (enctype is
        required for file uploads) and closed immediately; all field elements
        reference it via form="editDocumentForm". No nested forms.
    -->
    <form method="POST" action="<?= BASE_URL ?>/receiving/inbox/update"
          enctype="multipart/form-data"
          id="editDocumentForm">
        <input type="hidden" name="document_id"   value="<?= htmlspecialchars($document['id'] ?? '') ?>">
        <input type="hidden" name="assignment_id" value="<?= htmlspecialchars($assignment['id'] ?? '') ?>">
    </form>

    <div class="space-y-6">

            <!-- Receipt Information -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-900">Receipt Information</h2>
                <p class="mt-1 text-sm text-gray-500">Date and time the document was received</p>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <!-- Date Received -->
                    <div>
                        <label for="date_received" class="block text-sm font-medium text-gray-700">
                            Date Received <span class="text-red-500">*</span>
                        </label>
                        <input type="date" name="date_received" id="date_received" required
                               form="editDocumentForm"
                               value="<?= htmlspecialchars(oldOrDoc('date_received', $old, $document)) ?>"
                               class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary">
                    </div>

                    <!-- Time Received -->
                    <div>
                        <label for="time_received" class="block text-sm font-medium text-gray-700">
                            Time Received <span class="text-red-500">*</span>
                        </label>
                        <?php
                            // Normalize DB value (HH:MM:SS) to HH:MM for <input type="time">.
                            // When re-displaying after a validation error, $old already holds
                            // the browser-submitted HH:MM value, so it passes through as-is.
                            $timeInputValue = isset($old['time_received']) && $old['time_received'] !== ''
                                ? $old['time_received']
                                : normalizeTimeForInput($document['time_received'] ?? '');
                        ?>
                        <input type="time" name="time_received" id="time_received" required
                               form="editDocumentForm"
                               value="<?= htmlspecialchars($timeInputValue) ?>"
                               class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary">
                    </div>
                </div>
            </section>

            <!-- Document Details -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-900">Document Details</h2>
                <p class="mt-1 text-sm text-gray-500">Basic information about the document</p>

                <div class="mt-4 space-y-4">
                    <!-- Subject Matter -->
                    <div>
                        <label for="subject_matter" class="block text-sm font-medium text-gray-700">
                            Subject Matter <span class="text-red-500">*</span>
                        </label>
                        <textarea name="subject_matter" id="subject_matter" rows="4" required
                                  form="editDocumentForm"
                                  class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                                  placeholder="Enter the subject matter or title of the document"><?= htmlspecialchars(oldOrDoc('subject_matter', $old, $document)) ?></textarea>
                        <p class="mt-1 text-xs text-gray-500">Brief description or title of the document. Maximum 5000 characters.</p>
                    </div>

                    <!-- Document Type -->
                    <div>
                        <label for="document_type_id" class="block text-sm font-medium text-gray-700">
                            Document Type <span class="text-red-500">*</span>
                        </label>
                        <select name="document_type_id" id="document_type_id" required
                                form="editDocumentForm"
                                class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary">
                            <option value="">-- Select Document Type --</option>
                            <?php foreach ($documentTypes as $type): ?>
                                <option value="<?= htmlspecialchars($type['id'] ?? '') ?>"
                                        <?= oldOrDoc('document_type_id', $old, $document) == ($type['id'] ?? '') ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type['name'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Select the type of document</p>
                    </div>
                </div>
            </section>

            <!-- Source Information -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-900">Source Information</h2>
                <p class="mt-1 text-sm text-gray-500">Details about the document source</p>

                <div class="mt-4 space-y-4">
                    <!-- Source Type -->
                    <div>
                        <label for="source_type_id" class="block text-sm font-medium text-gray-700">
                            Source Type <span class="text-red-500">*</span>
                        </label>
                        <select name="source_type_id" id="source_type_id" required
                                form="editDocumentForm"
                                class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary">
                            <option value="">-- Select Source Type --</option>
                            <?php foreach ($sourceTypes as $type): ?>
                                <option value="<?= htmlspecialchars($type['id'] ?? '') ?>"
                                        data-source-name="<?= htmlspecialchars($type['name'] ?? '') ?>"
                                        <?= oldOrDoc('source_type_id', $old, $document) == ($type['id'] ?? '') ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type['name'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- External Office (shown for "External Office" source type) -->
                    <div id="externalOfficeFields" class="hidden space-y-4">
                        <div>
                            <label for="external_office_id" class="block text-sm font-medium text-gray-700">
                                External Office
                            </label>
                            <select name="external_office_id" id="external_office_id"
                                    form="editDocumentForm"
                                    class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary">
                                <option value="">-- Select External Office --</option>
                                <?php foreach ($externalOffices as $office): ?>
                                    <option value="<?= htmlspecialchars($office['id'] ?? '') ?>"
                                            <?= oldOrDoc('external_office_id', $old, $document) == ($office['id'] ?? '') ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($office['name'] ?? '') ?>
                                        <?= !empty($office['abbreviation']) ? ' (' . htmlspecialchars($office['abbreviation']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Hospital (shown for "Hospital" source type) -->
                    <div id="hospitalFields" class="hidden space-y-4">
                        <div>
                            <label for="hospital_id" class="block text-sm font-medium text-gray-700">
                                Hospital
                            </label>
                            <select name="hospital_id" id="hospital_id"
                                    form="editDocumentForm"
                                    class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary">
                                <option value="">-- Select Hospital --</option>
                                <?php foreach ($hospitals as $hospital): ?>
                                    <option value="<?= htmlspecialchars($hospital['id'] ?? '') ?>"
                                            <?= oldOrDoc('hospital_id', $old, $document) == ($hospital['id'] ?? '') ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($hospital['name'] ?? '') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- SP Member (shown for "SP Member" source type) -->
                    <div id="spMemberFields" class="hidden space-y-4">
                        <div>
                            <label for="sp_member_id" class="block text-sm font-medium text-gray-700">
                                SP Member
                            </label>
                            <select name="sp_member_id" id="sp_member_id"
                                    form="editDocumentForm"
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
                                    <option value="<?= htmlspecialchars($member['sp_member_id'] ?? '') ?>"
                                            <?= oldOrDoc('sp_member_id', $old, $document) == ($member['sp_member_id'] ?? '') ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($fullName) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Agency Name (shown for "Agency" source type) -->
                    <div id="agencyNameField" class="hidden">
                        <label for="source_name_agency" class="block text-sm font-medium text-gray-700">
                            Agency Name
                        </label>
                        <input type="text" name="source_name" id="source_name_agency" disabled
                               form="editDocumentForm"
                               value="<?= htmlspecialchars(oldOrDoc('source_name', $old, $document)) ?>"
                               class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                               placeholder="Enter agency name">
                    </div>

                    <!-- Client Name (shown for "Client" source type) -->
                    <div id="clientNameField" class="hidden">
                        <label for="source_name_client" class="block text-sm font-medium text-gray-700">
                            Client Name
                        </label>
                        <input type="text" name="source_name" id="source_name_client" disabled
                               form="editDocumentForm"
                               value="<?= htmlspecialchars(oldOrDoc('source_name', $old, $document)) ?>"
                               class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                               placeholder="Enter client name">
                    </div>

                    <!-- Municipality / City (shown for "Client" source type) -->
                    <div id="municipalityField" class="hidden">
                        <label for="municipality_id" class="block text-sm font-medium text-gray-700">
                            Municipality / City
                        </label>
                        <select name="municipality_id" id="municipality_id"
                                form="editDocumentForm"
                                class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary">
                            <option value="">-- Select Municipality/City --</option>
                            <?php foreach ($municipalities as $muni): ?>
                                <option value="<?= htmlspecialchars($muni['id'] ?? '') ?>"
                                        <?= oldOrDoc('municipality_id', $old, $document) == ($muni['id'] ?? '') ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($muni['name'] ?? '') ?>
                                    <?= ($muni['type'] ?? '') === 'City' ? ' (City)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Contact Fields (shown for all source types except none) -->
                    <div id="contactFields" class="hidden space-y-4">
                        <div>
                            <label for="source_contact_number" class="block text-sm font-medium text-gray-700">
                                Contact Number
                            </label>
                            <input type="text" name="source_contact_number" id="source_contact_number"
                                   form="editDocumentForm"
                                   value="<?= htmlspecialchars(oldOrDoc('source_contact_number', $old, $document)) ?>"
                                   class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                                   placeholder="Enter contact number">
                        </div>

                        <div>
                            <label for="source_address" class="block text-sm font-medium text-gray-700">
                                Address
                            </label>
                            <textarea name="source_address" id="source_address" rows="2"
                                      form="editDocumentForm"
                                      class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                                      placeholder="Enter address"><?= htmlspecialchars(oldOrDoc('source_address', $old, $document)) ?></textarea>
                        </div>

                        <div>
                            <label for="source_liaison_name" class="block text-sm font-medium text-gray-700">
                                Liaison / Contact Person
                            </label>
                            <input type="text" name="source_liaison_name" id="source_liaison_name"
                                   form="editDocumentForm"
                                   value="<?= htmlspecialchars(oldOrDoc('source_liaison_name', $old, $document)) ?>"
                                   class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                                   placeholder="Enter liaison or contact person name">
                        </div>
                    </div>

                </div>
            </section>

        <!-- Attachments — file input belongs to editDocumentForm via form= attribute.
             Files are submitted automatically when the user clicks "Save & Route to Admin".
             No separate upload form or upload button.
             ===================================================================== -->
    <section class="rounded-2xl border border-gray-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-gray-900">Attachments</h2>
        <p class="mt-1 text-sm text-gray-500">Upload additional files (PDF, DOC, DOCX, XLS, XLSX, images). Maximum 10 files, 25MB each. Attachments are optional — the document can be routed without selecting new files.</p>

            <div class="mt-4">
                <div class="flex items-center justify-center w-full">
                    <label for="attachmentFileInput"
                           class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-xl cursor-pointer bg-gray-50 hover:bg-gray-100">
                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                            <svg class="w-8 h-8 mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <p class="mb-1 text-sm text-gray-600"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                            <p class="text-xs text-gray-500">PDF, DOC, DOCX, XLS, XLSX, JPG, PNG (MAX. 25MB each)</p>
                        </div>
                        <!-- form= links this input to editDocumentForm so it submits with Save & Route to Admin -->
                        <input id="attachmentFileInput" name="attachments[]" type="file" multiple
                               form="editDocumentForm"
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp"
                               class="hidden">
                    </label>
                </div>

                <div id="attachmentFileList" class="mt-4 space-y-2"></div>
            </div>

        <!-- Existing attachments list -->
        <?php if (!empty($attachments)): ?>
            <div class="mt-5 border-t border-gray-100 pt-5">
                <p class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-500">Attached Files</p>
                <div class="space-y-2">
                    <?php foreach ($attachments as $attachment): ?>
                        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <svg class="h-5 w-5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                </svg>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($attachment['file_name'] ?? 'Attachment', ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        Uploaded <?= formatDateTime($attachment['created_at'] ?? null) ?>
                                    </p>
                                </div>
                            </div>
                            <div class="ml-3 flex shrink-0 items-center gap-2">
                                <?php if (!empty($attachment['stored_path'])): ?>
                                    <a href="<?= BASE_URL ?>/public/<?= htmlspecialchars($attachment['stored_path'], ENT_QUOTES, 'UTF-8') ?>"
                                       target="_blank"
                                       class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200 transition">
                                        View
                                    </a>
                                <?php endif; ?>
                                <!-- Hidden delete form — submitted by JS after modal confirmation -->
                                <form id="deleteAttachmentForm-<?= (int) ($attachment['id'] ?? 0) ?>"
                                      method="POST"
                                      action="<?= BASE_URL ?>/receiving/inbox/attachment/delete">
                                    <input type="hidden" name="attachment_id" value="<?= (int) ($attachment['id'] ?? 0) ?>">
                                    <input type="hidden" name="document_id"   value="<?= htmlspecialchars($document['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                </form>
                                <button type="button"
                                        class="remove-attachment-btn rounded-lg bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100 transition"
                                        data-form-id="deleteAttachmentForm-<?= (int) ($attachment['id'] ?? 0) ?>"
                                        data-file-name="<?= htmlspecialchars($attachment['file_name'] ?? 'this file', ENT_QUOTES, 'UTF-8') ?>">
                                    Remove
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <!-- =====================================================================
         Remarks / Notes + Submit — fields belong to editDocumentForm via the
         form="editDocumentForm" attribute. No nesting, no duplicate form tags.
         ===================================================================== -->

        <!-- Remarks / Notes -->
        <section class="rounded-2xl border border-gray-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-gray-900">Remarks / Notes</h2>
            <p class="mt-1 text-sm text-gray-500">Optional notes or remarks about this document</p>

            <div class="mt-4">
                <textarea name="remarks" id="remarks" rows="3"
                          form="editDocumentForm"
                          class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                          placeholder="Enter any additional remarks or notes..."><?= htmlspecialchars(oldOrDoc('remarks', $old, $document)) ?></textarea>
            </div>
        </section>

        <!-- Submit -->
        <section class="flex items-center justify-end gap-3">
            <a href="<?= BASE_URL ?>/receiving/inbox"
               class="inline-flex items-center justify-center rounded-xl px-6 py-3 text-sm font-medium text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-300 transition">
                Cancel
            </a>
            <!-- Triggers the process confirmation modal; actual submit happens on Confirm -->
            <button type="button" id="submitTriggerBtn"
                    class="inline-flex items-center justify-center rounded-xl px-6 py-3 text-sm font-medium text-white bg-primary hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition">
                <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Save &amp; Route to Admin
            </button>
        </section>

    </div>

    <!-- ═══════════════════════════════════════════════════════════════════
         Process confirmation modal — Save & Route to Admin
         Direct child of the top-level wrapper so `position:fixed` covers
         the full viewport without being clipped by any ancestor.
    ═══════════════════════════════════════════════════════════════════ -->
    <div id="processConfirmModal"
         class="fixed inset-0 z-[9999] hidden items-center justify-center"
         role="dialog"
         aria-modal="true"
         aria-labelledby="processConfirmTitle"
         aria-describedby="processConfirmBody">

        <div id="processConfirmBackdrop"
             class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity duration-200 opacity-0"></div>

        <div id="processConfirmPanel"
             class="relative z-10 w-full max-w-md mx-4 bg-white rounded-2xl shadow-2xl
                    border border-gray-100 transition-all duration-200 opacity-0"
             style="transform:scale(0.95)">
            <div class="p-6">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-blue-50">
                    <svg class="h-6 w-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 id="processConfirmTitle"
                    class="text-base font-semibold text-gray-900 mb-2">Save &amp; Route to Admin?</h3>
                <p id="processConfirmBody" class="text-sm text-gray-600">
                    This will save the corrected document details and route it back to Admin for processing.
                    You will not be able to edit it again unless Admin returns it.
                </p>
            </div>
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100
                        bg-gray-50 rounded-b-2xl">
                <button type="button" id="processConfirmCancelBtn"
                        class="inline-flex items-center justify-center rounded-xl px-4 py-2
                               text-sm font-medium text-gray-700 bg-white border border-gray-200
                               hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="button" id="processConfirmOkBtn"
                        class="inline-flex items-center justify-center rounded-xl px-4 py-2
                               text-sm font-medium text-white bg-primary hover:bg-blue-700 transition">
                    Yes, Route to Admin
                </button>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════
         Attachment removal confirmation modal
    ═══════════════════════════════════════════════════════════════════ -->
    <div id="removeAttachmentModal"
         class="fixed inset-0 z-[9999] hidden items-center justify-center"
         role="dialog"
         aria-modal="true"
         aria-labelledby="removeAttachmentTitle"
         aria-describedby="removeAttachmentBody">

        <div id="removeAttachmentBackdrop"
             class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity duration-200 opacity-0"></div>

        <div id="removeAttachmentPanel"
             class="relative z-10 w-full max-w-md mx-4 bg-white rounded-2xl shadow-2xl
                    border border-gray-100 transition-all duration-200 opacity-0"
             style="transform:scale(0.95)">
            <div class="p-6">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-50">
                    <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </div>
                <h3 id="removeAttachmentTitle"
                    class="text-base font-semibold text-gray-900 mb-2">Remove Attachment?</h3>
                <p id="removeAttachmentBody" class="text-sm text-gray-600">
                    This attachment will be permanently removed and cannot be recovered.
                </p>
            </div>
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100
                        bg-gray-50 rounded-b-2xl">
                <button type="button" id="removeAttachmentCancelBtn"
                        class="inline-flex items-center justify-center rounded-xl px-4 py-2
                               text-sm font-medium text-gray-700 bg-white border border-gray-200
                               hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="button" id="removeAttachmentOkBtn"
                        class="inline-flex items-center justify-center rounded-xl px-4 py-2
                               text-sm font-medium text-white bg-red-600 hover:bg-red-700 transition">
                    Remove
                </button>
            </div>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const sourceTypeSelect = document.getElementById('source_type_id');
    const submitTriggerBtn = document.getElementById('submitTriggerBtn');
    const mainForm         = document.getElementById('editDocumentForm');

    if (!sourceTypeSelect || !submitTriggerBtn || !mainForm) {
        console.error('Required DOM elements not found');
        return;
    }

    // ------------------------------------------------------------------
    // Source-type field visibility + value cleanup
    // ------------------------------------------------------------------

    // References to every source-specific field element
    const externalOfficeSelect = document.getElementById('external_office_id');
    const hospitalSelect       = document.getElementById('hospital_id');
    const spMemberSelect       = document.getElementById('sp_member_id');
    const municipalitySelect   = document.getElementById('municipality_id');
    const agencyInput          = document.getElementById('source_name_agency');
    const clientInput          = document.getElementById('source_name_client');

    /**
     * Disable a <select> and, when clearValue is true, also reset it to the
     * blank placeholder.  Disabling prevents the field from being submitted,
     * which is the server-side defence-in-depth backstop.
     */
    function disableSelect(selectEl, clearValue) {
        if (!selectEl) return;
        if (clearValue) selectEl.value = '';
        selectEl.disabled = true;
    }

    /**
     * Re-enable a <select> so its value is included in the form submission.
     */
    function enableSelect(selectEl) {
        if (!selectEl) return;
        selectEl.disabled = false;
    }

    function applySourceType(isUserChange) {
        const selectedOption = sourceTypeSelect.options[sourceTypeSelect.selectedIndex];
        const sourceName     = selectedOption ? selectedOption.getAttribute('data-source-name') : '';

        // ── Hide every conditional section ──────────────────────────────
        document.getElementById('externalOfficeFields').classList.add('hidden');
        document.getElementById('hospitalFields').classList.add('hidden');
        document.getElementById('spMemberFields').classList.add('hidden');
        document.getElementById('agencyNameField').classList.add('hidden');
        document.getElementById('clientNameField').classList.add('hidden');
        document.getElementById('municipalityField').classList.add('hidden');
        document.getElementById('contactFields').classList.add('hidden');

        // ── Disable (and clear on user change) every source-specific field
        // On page load (isUserChange=false) we only disable inactive fields
        // so existing valid values are preserved in the visible/active field.
        // On a user-initiated change we clear AND disable all of them first,
        // then re-enable only the one that belongs to the new source type.
        disableSelect(externalOfficeSelect, isUserChange);
        disableSelect(hospitalSelect,       isUserChange);
        disableSelect(spMemberSelect,       isUserChange);
        disableSelect(municipalitySelect,   isUserChange);

        agencyInput.removeAttribute('required');
        agencyInput.disabled = true;
        clientInput.removeAttribute('required');
        clientInput.disabled = true;

        // On a user-initiated type switch, also wipe the text source_name
        // inputs so an old Agency/Client name is not silently re-submitted.
        if (isUserChange) {
            agencyInput.value = '';
            clientInput.value = '';
        }

        if (!sourceName) return;

        document.getElementById('contactFields').classList.remove('hidden');

        if (sourceName === 'External Office') {
            document.getElementById('externalOfficeFields').classList.remove('hidden');
            enableSelect(externalOfficeSelect);

        } else if (sourceName === 'Hospital') {
            document.getElementById('hospitalFields').classList.remove('hidden');
            enableSelect(hospitalSelect);

        } else if (sourceName === 'SP Member') {
            document.getElementById('spMemberFields').classList.remove('hidden');
            enableSelect(spMemberSelect);

        } else if (sourceName === 'Agency') {
            document.getElementById('agencyNameField').classList.remove('hidden');
            agencyInput.disabled = false;

        } else if (sourceName === 'Client') {
            document.getElementById('clientNameField').classList.remove('hidden');
            document.getElementById('municipalityField').classList.remove('hidden');
            clientInput.disabled = false;
            enableSelect(municipalitySelect);
        }
    }

    // isUserChange=true  → clear stale values + disable inactive fields
    // isUserChange=false → disable inactive fields only (preserve existing values on load)
    sourceTypeSelect.addEventListener('change', function () { applySourceType(true); });
    if (sourceTypeSelect.value) applySourceType(false);

    // ------------------------------------------------------------------
    // File staging — files are submitted with editDocumentForm
    // (attachmentFileInput already carries form="editDocumentForm")
    // ------------------------------------------------------------------
    const attachmentInput    = document.getElementById('attachmentFileInput');
    const attachmentFileList = document.getElementById('attachmentFileList');

    if (attachmentInput && attachmentFileList) {

        // DataTransfer accumulates staged files across multiple picker opens
        const stagedFiles = new DataTransfer();

        attachmentInput.addEventListener('change', function () {
            Array.from(this.files).forEach(function (file) {
                stagedFiles.items.add(file);
            });
            renderAttachmentFileList();
            // Keep the real input in sync so it submits with mainForm
            attachmentInput.files = stagedFiles.files;
        });

        function renderAttachmentFileList() {
            attachmentFileList.innerHTML = '';

            if (stagedFiles.files.length === 0) {
                return;
            }

            Array.from(stagedFiles.files).forEach(function (file, index) {
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
                    <button type="button" onclick="removeAttachmentFile(${index})"
                            class="ml-2 flex-shrink-0 p-1.5 text-red-600 hover:bg-red-50 rounded-lg transition"
                            title="Remove file">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>`;
                attachmentFileList.appendChild(fileItem);
            });
        }

        // Global so the inline onclick can reach it
        window.removeAttachmentFile = function (index) {
            const dt = new DataTransfer();
            Array.from(stagedFiles.files).forEach(function (file, i) {
                if (i !== index) dt.items.add(file);
            });
            stagedFiles.items.clear();
            Array.from(dt.files).forEach(function (file) { stagedFiles.items.add(file); });
            // Keep the real input in sync
            attachmentInput.files = stagedFiles.files;
            renderAttachmentFileList();
        };

        function escapeHtml(str) {
            return String(str).replace(/[&<>"']/g, function (m) {
                return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m];
            });
        }

        // Before mainForm submits, ensure the file input reflects the staged files
        mainForm.addEventListener('submit', function () {
            attachmentInput.files = stagedFiles.files;
        });
    }

    // ------------------------------------------------------------------
    // Process confirmation modal — Save & Route to Admin
    // ------------------------------------------------------------------
    const processConfirmModal      = document.getElementById('processConfirmModal');
    const processConfirmBackdrop   = document.getElementById('processConfirmBackdrop');
    const processConfirmPanel      = document.getElementById('processConfirmPanel');
    const processConfirmOkBtn      = document.getElementById('processConfirmOkBtn');
    const processConfirmCancelBtn  = document.getElementById('processConfirmCancelBtn');

    let processSubmitting = false;

    // Clicking the trigger button opens the modal (does NOT submit)
    submitTriggerBtn.addEventListener('click', function () {
        openProcessModal();
    });

    if (processConfirmOkBtn) {
        processConfirmOkBtn.addEventListener('click', function () {
            if (processSubmitting) return;
            processSubmitting = true;

            // Disable + show spinner on both the modal button and the trigger
            processConfirmOkBtn.disabled = true;
            processConfirmOkBtn.innerHTML =
                '<svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">' +
                '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
                '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>' +
                '</svg><span class="ml-2">Routing\u2026</span>';
            submitTriggerBtn.disabled = true;

            mainForm.submit();
        });
    }

    if (processConfirmCancelBtn) {
        processConfirmCancelBtn.addEventListener('click', closeProcessModal);
    }
    if (processConfirmBackdrop) {
        processConfirmBackdrop.addEventListener('click', function () {
            if (!processSubmitting) closeProcessModal();
        });
    }

    function openProcessModal() {
        processConfirmModal.classList.remove('hidden');
        processConfirmModal.classList.add('flex');
        document.body.style.overflow = 'hidden';
        requestAnimationFrame(function () {
            processConfirmBackdrop.style.opacity = '1';
            processConfirmPanel.style.opacity    = '1';
            processConfirmPanel.style.transform  = 'scale(1)';
            if (processConfirmCancelBtn) processConfirmCancelBtn.focus();
        });
    }

    function closeProcessModal() {
        if (processSubmitting) return;
        processConfirmBackdrop.style.opacity = '0';
        processConfirmPanel.style.opacity    = '0';
        processConfirmPanel.style.transform  = 'scale(0.95)';
        setTimeout(function () {
            processConfirmModal.classList.add('hidden');
            processConfirmModal.classList.remove('flex');
            document.body.style.overflow = '';
        }, 200);
    }

    // ------------------------------------------------------------------
    // Attachment removal confirmation modal
    // ------------------------------------------------------------------
    const removeAttachmentModal      = document.getElementById('removeAttachmentModal');
    const removeAttachmentBackdrop   = document.getElementById('removeAttachmentBackdrop');
    const removeAttachmentPanel      = document.getElementById('removeAttachmentPanel');
    const removeAttachmentBody       = document.getElementById('removeAttachmentBody');
    const removeAttachmentOkBtn      = document.getElementById('removeAttachmentOkBtn');
    const removeAttachmentCancelBtn  = document.getElementById('removeAttachmentCancelBtn');

    let pendingDeleteFormId  = null;
    let deleteSubmitting     = false;

    document.querySelectorAll('.remove-attachment-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            pendingDeleteFormId = this.dataset.formId;
            const fileName      = this.dataset.fileName || 'this file';
            if (removeAttachmentBody) {
                removeAttachmentBody.textContent =
                    '\u201c' + fileName + '\u201d will be permanently removed and cannot be recovered.';
            }
            openRemoveModal();
        });
    });

    if (removeAttachmentOkBtn) {
        removeAttachmentOkBtn.addEventListener('click', function () {
            if (deleteSubmitting || !pendingDeleteFormId) return;
            const form = document.getElementById(pendingDeleteFormId);
            if (!form) return;

            deleteSubmitting = true;
            removeAttachmentOkBtn.disabled = true;
            removeAttachmentOkBtn.innerHTML =
                '<svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">' +
                '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
                '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>' +
                '</svg><span class="ml-2">Removing\u2026</span>';

            form.submit();
        });
    }

    if (removeAttachmentCancelBtn) {
        removeAttachmentCancelBtn.addEventListener('click', closeRemoveModal);
    }
    if (removeAttachmentBackdrop) {
        removeAttachmentBackdrop.addEventListener('click', function () {
            if (!deleteSubmitting) closeRemoveModal();
        });
    }

    function openRemoveModal() {
        deleteSubmitting = false;
        removeAttachmentModal.classList.remove('hidden');
        removeAttachmentModal.classList.add('flex');
        document.body.style.overflow = 'hidden';
        requestAnimationFrame(function () {
            removeAttachmentBackdrop.style.opacity = '1';
            removeAttachmentPanel.style.opacity    = '1';
            removeAttachmentPanel.style.transform  = 'scale(1)';
            if (removeAttachmentCancelBtn) removeAttachmentCancelBtn.focus();
        });
    }

    function closeRemoveModal() {
        if (deleteSubmitting) return;
        removeAttachmentBackdrop.style.opacity = '0';
        removeAttachmentPanel.style.opacity    = '0';
        removeAttachmentPanel.style.transform  = 'scale(0.95)';
        setTimeout(function () {
            removeAttachmentModal.classList.add('hidden');
            removeAttachmentModal.classList.remove('flex');
            document.body.style.overflow = '';
            pendingDeleteFormId = null;
        }, 200);
    }

    // Escape closes whichever modal is open (if not submitting)
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (removeAttachmentModal && !removeAttachmentModal.classList.contains('hidden') && !deleteSubmitting) {
            closeRemoveModal();
        } else if (processConfirmModal && !processConfirmModal.classList.contains('hidden') && !processSubmitting) {
            closeProcessModal();
        }
    });
});
</script>

<?php
$content   = ob_get_clean();
$pageTitle = 'Edit Returned Document';
require __DIR__ . '/../../layouts/app.php';
?>

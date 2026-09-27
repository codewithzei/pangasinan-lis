<?php
/**
 * SP Secretary Inbox — Document detail & processing
 *
 * Variables supplied by SpsecInboxController::show():
 *   $document            array        Full document row with all joins
 *   $activeAssignment    array|false  The open assignment (false = none)
 *   $allAssignments      array
 *   $attachments         array
 *   $routes              array
 *   $events              array
 *   $revisions           array
 *   $success             string|null
 *   $error               string|null
 *   $errors              array
 *   $canAccept           bool
 *   $canProcess          bool
 */

$document         = $document         ?? [];
$activeAssignment = $activeAssignment ?? false;
$allAssignments   = $allAssignments   ?? [];
$attachments      = $attachments      ?? [];
$routes           = $routes           ?? [];
$events           = $events           ?? [];
$revisions        = $revisions        ?? [];
$success          = $success          ?? null;
$error            = $error            ?? null;
$errors           = $errors           ?? [];
$commCategories   = $commCategories   ?? [];
$committees       = $committees       ?? [];
$isCommunication  = $isCommunication  ?? false;

$documentId     = (int) ($document['id'] ?? 0);
$trackingNumber = $document['tracking_number'] ?? '';

// ── Assignment state ──────────────────────────────────────────────────────────
$assignmentDecision = $activeAssignment !== false
    ? ($activeAssignment['decision'] ?? null)
    : null;

if (!isset($canAccept)) {
    $canAccept = $assignmentDecision === 'PENDING'
        && empty($activeAssignment['accepted_by'])
        && (
            empty($activeAssignment['assigned_to_user_id'])
            || (int) $activeAssignment['assigned_to_user_id'] === (int) ($currentUserId ?? auth_id() ?? 0)
        );
}
if (!isset($canProcess)) {
    $canProcess = false;
}

function spsecShowPhase(string $phase): string
{
    return match ($phase) {
        'RECEIVING'    => 'Receiving',
        'ADMIN'        => 'Admin',
        'SP_SECRETARY' => 'SP Secretary',
        'PLENARY'      => 'Plenary',
        'COMMITTEE'    => 'Committee',
        'FINALIZED'    => 'Finalized',
        'FILED'        => 'Filed',
        default        => $phase,
    };
}

function spsecShowDecision(string $d): string
{
    return match ($d) {
        'PENDING'   => 'Pending',
        'ACCEPTED'  => 'Accepted',
        'DECLINED'  => 'Declined',
        'NOTED'     => 'Noted',
        'REJECTED'  => 'Rejected',
        'COMPLETED' => 'Completed',
        default     => $d,
    };
}

function spsecShowEvent(string $et): string
{
    return match ($et) {
        'DOCUMENT_RECEIVED'              => 'Document Received',
        'ROUTED_TO_ADMIN'                => 'Routed to Admin',
        'ADMIN_ACCEPTED'                 => 'Admin Accepted',
        'ADMIN_DECLINED'                 => 'Admin Declined',
        'ADMIN_RETURNED_TO_RECEIVING'    => 'Returned to Receiving',
        'ROUTED_TO_SP_SECRETARY'         => 'Routed to SP Secretary',
        'SP_SECRETARY_ACCEPTED'          => 'SP Secretary Accepted',
        'SP_SECRETARY_REJECTED'          => 'SP Secretary Rejected',
        'SP_SECRETARY_RETURNED_TO_ADMIN' => 'Returned to Admin',
        'ROUTED_TO_PLENARY'              => 'Routed to Plenary',
        'ROUTED_TO_COMMITTEE'            => 'Routed to Committee',
        'DOCUMENT_NOTED'                 => 'Marked as Noted',
        'DOCUMENT_EDITED'                => 'Document Edited',
        'SUBJECT_MATTER_CHANGED'         => 'Subject Matter Changed',
        'REFERRED_TO_COMMITTEE'          => 'Referred to Committee',
        'OPINION_REQUESTED'              => 'Opinion Requested',
        'AGENDA_SCHEDULED'               => 'Agenda Scheduled',
        'HEARING_COMPLETED'              => 'Hearing Completed',
        'RETURNED_TO_PLENARY'            => 'Returned to Plenary',
        'SECOND_READING_APPROVED'        => 'Second Reading Approved',
        'DOCUMENT_FINALIZED'             => 'Document Finalized',
        'DOCUMENT_FILED'                 => 'Document Filed',
        default                          => $et,
    };
}

ob_start();
?>

<div class="space-y-6">

    <!-- Back + Page header --------------------------------------------------->
    <div class="flex items-center gap-4">
        <a href="<?= BASE_URL ?>/spsec/inbox"
           class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">SP Secretary Inbox</p>
            <h1 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($trackingNumber) ?></h1>
        </div>
    </div>

    <!-- Flash messages ------------------------------------------------------->
    <?php if ($success): ?>
        <div class="flex items-start gap-3 rounded-2xl border border-green-200 bg-green-50 p-4">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-green-100">
                <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <p class="mt-1.5 text-sm font-medium text-green-800"><?= htmlspecialchars($success) ?></p>
        </div>
    <?php endif; ?>

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

    <!-- Main 2-column layout ------------------------------------------------->
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

        <!-- Left column (2/3): document info + history ----------------------->
        <div class="space-y-6 xl:col-span-2">

            <!-- Document summary -------------------------------------------->
            <div class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="mb-4 text-base font-semibold text-gray-900">Document Summary</h2>

                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Tracking Number</dt>
                        <dd class="mt-1 font-mono text-sm font-bold text-primary"><?= htmlspecialchars($trackingNumber) ?></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Current Status</dt>
                        <dd class="mt-1">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                  style="background-color: <?= htmlspecialchars($document['status_badge_color'] ?? '#2563EB') ?>1a;
                                         color: <?= htmlspecialchars($document['status_badge_color'] ?? '#2563EB') ?>;">
                                <?= htmlspecialchars($document['status'] ?? '') ?>
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Current Phase</dt>
                        <dd class="mt-1 text-sm text-gray-800"><?= htmlspecialchars(spsecShowPhase($document['current_phase'] ?? 'SP_SECRETARY')) ?></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Date / Time Received</dt>
                        <dd class="mt-1 text-sm text-gray-800">
                            <?= htmlspecialchars(date('F j, Y', strtotime($document['date_received']))) ?>
                            at <?= htmlspecialchars(date('g:i A', strtotime($document['time_received']))) ?>
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Subject Matter</dt>
                        <dd class="mt-1 text-sm text-gray-800 whitespace-pre-wrap"><?= htmlspecialchars($document['subject_matter'] ?? '') ?></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Document Type</dt>
                        <dd class="mt-1">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                  style="background-color: <?= htmlspecialchars($document['document_type_badge_color'] ?? '#2563EB') ?>1a;
                                         color: <?= htmlspecialchars($document['document_type_badge_color'] ?? '#2563EB') ?>;">
                                <?= htmlspecialchars($document['document_type_name'] ?? '') ?>
                            </span>
                        </dd>
                    </div>
                    <?php if (!empty($document['communication_category_name'])): ?>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Communication Category</dt>
                            <dd class="mt-1 text-sm text-gray-800"><?= htmlspecialchars($document['communication_category_name']) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($document['remarks'])): ?>
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Remarks</dt>
                            <dd class="mt-1 text-sm text-gray-800 whitespace-pre-wrap"><?= htmlspecialchars($document['remarks']) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </div>

            <!-- Accountability / Ownership ------------------------------------>
            <?php
            $accountabilityAssignment = null;
            foreach ($allAssignments as $asgn) {
                if ($asgn['phase'] === 'SP_SECRETARY' && !empty($asgn['accepted_by'])) {
                    $accountabilityAssignment = $asgn;
                }
            }
            ?>
            <?php if ($accountabilityAssignment !== null): ?>
                <div class="rounded-2xl border border-blue-100 bg-blue-50 p-6">
                    <h2 class="mb-4 text-base font-semibold text-blue-900">Accountability</h2>
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-blue-500">Accepted By</dt>
                            <dd class="mt-1 text-sm font-semibold text-blue-900">
                                <?= htmlspecialchars($accountabilityAssignment['accepted_by_username'] ?? '—') ?>
                            </dd>
                            <?php
                            $acceptedFullName = trim($accountabilityAssignment['accepted_by_name'] ?? '');
                            if ($acceptedFullName !== ''): ?>
                                <dd class="text-xs text-blue-600"><?= htmlspecialchars($acceptedFullName) ?></dd>
                            <?php endif; ?>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-blue-500">Accepted At</dt>
                            <dd class="mt-1 text-sm text-blue-900">
                                <?php if (!empty($accountabilityAssignment['accepted_at'])): ?>
                                    <?= htmlspecialchars(date('F j, Y', strtotime($accountabilityAssignment['accepted_at']))) ?>
                                    <span class="block text-xs text-blue-600">
                                        <?= htmlspecialchars(date('g:i A', strtotime($accountabilityAssignment['accepted_at']))) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-blue-400">—</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-blue-500">Assignment Decision</dt>
                            <dd class="mt-1">
                                <?php
                                $dColor = match ($accountabilityAssignment['decision']) {
                                    'ACCEPTED'  => 'bg-blue-100 text-blue-700',
                                    'COMPLETED' => 'bg-green-100 text-green-700',
                                    'DECLINED'  => 'bg-red-100 text-red-700',
                                    default     => 'bg-gray-100 text-gray-700',
                                };
                                ?>
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $dColor ?>">
                                    <?= htmlspecialchars(spsecShowDecision($accountabilityAssignment['decision'])) ?>
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-blue-500">Current Owner</dt>
                            <dd class="mt-1 text-sm text-blue-900">
                                <?= htmlspecialchars($accountabilityAssignment['accepted_by_username'] ?? '—') ?>
                            </dd>
                        </div>
                    </dl>
                </div>
            <?php endif; ?>

            <!-- Source information ------------------------------------------>
            <div class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="mb-4 text-base font-semibold text-gray-900">Source Information</h2>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Source Type</dt>
                        <dd class="mt-1 text-sm text-gray-800"><?= htmlspecialchars($document['source_type'] ?? '') ?></dd>
                    </div>
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
                    <?php if (!empty($document['source_name'])): ?>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Source Name</dt>
                            <dd class="mt-1 text-sm text-gray-800"><?= htmlspecialchars($document['source_name']) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($document['municipality_name'])): ?>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Municipality / City</dt>
                            <dd class="mt-1 text-sm text-gray-800">
                                <?= htmlspecialchars($document['municipality_name']) ?>
                                <?php if (!empty($document['municipality_type']) && $document['municipality_type'] === 'City'): ?>
                                    <span class="text-gray-400">(City)</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($document['source_contact_number'])): ?>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Contact Number</dt>
                            <dd class="mt-1 text-sm text-gray-800"><?= htmlspecialchars($document['source_contact_number']) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($document['source_liaison_name'])): ?>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Liaison / Contact Person</dt>
                            <dd class="mt-1 text-sm text-gray-800"><?= htmlspecialchars($document['source_liaison_name']) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($document['source_address'])): ?>
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Address</dt>
                            <dd class="mt-1 text-sm text-gray-800 whitespace-pre-wrap"><?= htmlspecialchars($document['source_address']) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </div>

            <!-- Attachments -------------------------------------------------->
            <div class="rounded-2xl border border-gray-200 bg-white p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-900">
                        Attachments
                        <span class="ml-1.5 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                            <?= count($attachments) ?>
                        </span>
                    </h2>
                </div>

                <?php if (empty($attachments)): ?>
                    <p class="text-sm text-gray-400">No attachments.</p>
                <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach ($attachments as $att): ?>
                            <div class="flex items-center justify-between rounded-xl border border-gray-100 bg-gray-50 px-4 py-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-primary">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-gray-800">
                                            <?= htmlspecialchars($att['file_name']) ?>
                                        </p>
                                        <p class="text-xs text-gray-400">
                                            <?= htmlspecialchars(number_format($att['file_size'] / 1024, 1)) ?> KB
                                            &middot; <?= htmlspecialchars($att['phase']) ?>
                                            <?php if (!empty($att['uploaded_by_username'])): ?>
                                                &middot; by <?= htmlspecialchars($att['uploaded_by_username']) ?>
                                            <?php endif; ?>
                                            &middot; <?= htmlspecialchars(date('M j, Y g:i A', strtotime($att['created_at']))) ?>
                                        </p>
                                    </div>
                                </div>
                                <a href="<?= BASE_URL ?>/public/<?= htmlspecialchars($att['stored_path']) ?>"
                                   target="_blank"
                                   class="ml-4 shrink-0 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 transition">
                                    Download
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Additional attachment upload ----------------------------->
                <?php if ($canProcess): ?>
                    <div class="mt-6 border-t border-gray-100 pt-5">
                        <p class="mb-3 text-sm font-semibold text-gray-700">Upload Additional Files</p>
                        <form method="POST" action="<?= BASE_URL ?>/spsec/inbox/upload"
                              enctype="multipart/form-data" id="uploadForm">
                            <input type="hidden" name="document_id" value="<?= $documentId ?>">
                            <div class="flex items-center justify-center w-full">
                                <label for="extra_attachments"
                                       class="flex flex-col items-center justify-center w-full h-28 border-2 border-gray-300 border-dashed rounded-xl cursor-pointer bg-gray-50 hover:bg-gray-100">
                                    <div class="flex flex-col items-center justify-center py-4">
                                        <svg class="w-7 h-7 mb-1.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                        </svg>
                                        <p class="text-xs text-gray-500 text-center px-4">
                                            <span class="font-semibold text-gray-700">Click to upload</span> or drag and drop<br>
                                            PDF, DOC, DOCX, XLS, XLSX, JPG, PNG (max 25 MB each, up to 10 files)
                                        </p>
                                    </div>
                                    <input id="extra_attachments" name="attachments[]" type="file"
                                           multiple class="hidden"
                                           accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp">
                                </label>
                            </div>
                            <div id="extraFileList" class="mt-3 space-y-1.5"></div>
                            <div class="mt-3 flex justify-end">
                                <button type="submit" id="uploadBtn"
                                        class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                    </svg>
                                    Upload Files
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Assignment history ------------------------------------------->
            <div class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="mb-4 text-base font-semibold text-gray-900">Assignment History</h2>
                <?php if (empty($allAssignments)): ?>
                    <p class="text-sm text-gray-400">No assignments yet.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm divide-y divide-gray-100">
                            <thead>
                                <tr class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                                    <th class="pb-2 text-left">Phase</th>
                                    <th class="pb-2 text-left">Assigned To</th>
                                    <th class="pb-2 text-left">By</th>
                                    <th class="pb-2 text-left">Accepted By</th>
                                    <th class="pb-2 text-left">Decision</th>
                                    <th class="pb-2 text-left">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php foreach ($allAssignments as $asgn): ?>
                                    <tr class="text-gray-700">
                                        <td class="py-2"><?= htmlspecialchars(spsecShowPhase($asgn['phase'])) ?></td>
                                        <td class="py-2">
                                            <?php if (!empty($asgn['role_name'])): ?>
                                                <?= htmlspecialchars($asgn['role_name']) ?>
                                            <?php elseif (!empty($asgn['assigned_to_username'])): ?>
                                                <?= htmlspecialchars($asgn['assigned_to_username']) ?>
                                            <?php else: ?>
                                                <span class="text-gray-400">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-2 text-gray-500">
                                            <?= !empty($asgn['assigned_by_username']) ? htmlspecialchars($asgn['assigned_by_username']) : '—' ?>
                                        </td>
                                        <td class="py-2">
                                            <?php if (!empty($asgn['accepted_by_username'])): ?>
                                                <span class="font-medium text-blue-700">
                                                    <?= htmlspecialchars($asgn['accepted_by_username']) ?>
                                                </span>
                                                <?php
                                                $abName = trim($asgn['accepted_by_name'] ?? '');
                                                if ($abName !== ''): ?>
                                                    <span class="block text-xs text-gray-400">
                                                        <?= htmlspecialchars($abName) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($asgn['accepted_at'])): ?>
                                                    <span class="block text-xs text-gray-400">
                                                        <?= htmlspecialchars(date('M j, Y g:i A', strtotime($asgn['accepted_at']))) ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-2">
                                            <?php
                                            $dClass = match ($asgn['decision']) {
                                                'COMPLETED'            => 'bg-green-50 text-green-700',
                                                'ACCEPTED'             => 'bg-blue-50 text-blue-700',
                                                'DECLINED', 'REJECTED' => 'bg-red-50 text-red-700',
                                                default                => 'bg-amber-50 text-amber-700',
                                            };
                                            ?>
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold <?= $dClass ?>">
                                                <?= htmlspecialchars(spsecShowDecision($asgn['decision'])) ?>
                                            </span>
                                        </td>
                                        <td class="py-2 text-xs text-gray-400">
                                            <?= htmlspecialchars(date('M j, Y', strtotime($asgn['created_at']))) ?>
                                        </td>
                                    </tr>
                                    <?php if (!empty($asgn['decline_reason'])): ?>
                                        <tr>
                                            <td colspan="6" class="pb-2 pl-4">
                                                <span class="text-xs text-red-600 italic">
                                                    Return reason: <?= htmlspecialchars($asgn['decline_reason']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Route history ------------------------------------------------>
            <div class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="mb-4 text-base font-semibold text-gray-900">Routing History</h2>
                <?php if (empty($routes)): ?>
                    <p class="text-sm text-gray-400">No routes recorded.</p>
                <?php else: ?>
                    <ol class="relative border-l border-gray-200 ml-3 space-y-5">
                        <?php foreach ($routes as $rt): ?>
                            <li class="ml-6">
                                <span class="absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full bg-primary text-white ring-4 ring-white">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                    </svg>
                                </span>
                                <p class="text-sm font-semibold text-gray-800">
                                    <?= htmlspecialchars(spsecShowPhase($rt['from_phase'] ?? '')) ?>
                                    &rarr;
                                    <?= htmlspecialchars(spsecShowPhase($rt['to_phase'])) ?>
                                </p>
                                <p class="text-xs text-gray-400">
                                    <?php if (!empty($rt['routed_to_role_name'])): ?>
                                        To: <?= htmlspecialchars($rt['routed_to_role_name']) ?> &middot;
                                    <?php endif; ?>
                                    <?php if (!empty($rt['routed_by_username'])): ?>
                                        By: <?= htmlspecialchars($rt['routed_by_username']) ?> &middot;
                                    <?php endif; ?>
                                    <?= htmlspecialchars(date('M j, Y g:i A', strtotime($rt['created_at']))) ?>
                                </p>
                                <?php if (!empty($rt['remarks'])): ?>
                                    <p class="mt-1 text-xs text-gray-500 italic"><?= htmlspecialchars($rt['remarks']) ?></p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>

            <!-- Workflow events ---------------------------------------------->
            <div class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="mb-4 text-base font-semibold text-gray-900">Workflow Events</h2>
                <?php if (empty($events)): ?>
                    <p class="text-sm text-gray-400">No events recorded.</p>
                <?php else: ?>
                    <ol class="relative border-l border-gray-200 ml-3 space-y-5">
                        <?php foreach ($events as $ev): ?>
                            <li class="ml-6">
                                <span class="absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full bg-indigo-600 text-white ring-4 ring-white">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </span>
                                <p class="text-sm font-semibold text-gray-800"><?= htmlspecialchars(spsecShowEvent($ev['event_type'])) ?></p>
                                <p class="text-xs text-gray-400">
                                    <?= htmlspecialchars(spsecShowPhase($ev['phase'])) ?>
                                    <?php if (!empty($ev['performed_by_username'])): ?>
                                        &middot; by <?= htmlspecialchars($ev['performed_by_username']) ?>
                                    <?php endif; ?>
                                    &middot; <?= htmlspecialchars(date('M j, Y g:i A', strtotime($ev['created_at']))) ?>
                                </p>
                                <?php if (!empty($ev['remarks'])): ?>
                                    <p class="mt-1 text-xs text-gray-500 italic"><?= htmlspecialchars($ev['remarks']) ?></p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>

            <!-- Revision history --------------------------------------------->
            <div class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="mb-4 text-base font-semibold text-gray-900">Revision History</h2>
                <?php if (empty($revisions)): ?>
                    <p class="text-sm text-gray-400">No revisions recorded.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm divide-y divide-gray-100">
                            <thead>
                                <tr class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                                    <th class="pb-2 text-left">Rev</th>
                                    <th class="pb-2 text-left">Phase</th>
                                    <th class="pb-2 text-left">Changed By</th>
                                    <th class="pb-2 text-left">Reason</th>
                                    <th class="pb-2 text-left">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php foreach ($revisions as $rev): ?>
                                    <tr class="text-gray-700">
                                        <td class="py-2 font-mono text-xs">#<?= (int) $rev['revision_number'] ?></td>
                                        <td class="py-2"><?= htmlspecialchars(spsecShowPhase($rev['phase'])) ?></td>
                                        <td class="py-2 text-gray-500">
                                            <?= !empty($rev['changed_by_username']) ? htmlspecialchars($rev['changed_by_username']) : '—' ?>
                                        </td>
                                        <td class="py-2 text-xs text-gray-500">
                                            <?= htmlspecialchars($rev['change_reason'] ?? '—') ?>
                                        </td>
                                        <td class="py-2 text-xs text-gray-400">
                                            <?= htmlspecialchars(date('M j, Y', strtotime($rev['created_at']))) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div><!-- /left column -->

        <!-- Right column (1/3): processing panel ---------------------------->
        <div class="xl:col-span-1">
            <div class="sticky top-6 space-y-4">

                <!-- ═══════════════════════════════════════════════════════════
                     SECTION A — PENDING: Accept button
                     Only shown when $canAccept is true (PENDING, unclaimed).
                ════════════════════════════════════════════════════════════ -->
                <?php if ($canAccept): ?>
                    <div class="rounded-2xl border border-blue-200 bg-blue-50 p-6">
                        <div class="mb-4">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Pending — available to claim
                            </span>
                            <p class="mt-2 text-xs text-blue-700">
                                Accept this document to take ownership and begin processing.
                            </p>
                        </div>

                        <form method="POST" action="<?= BASE_URL ?>/spsec/inbox/process" id="acceptForm" novalidate>
                            <input type="hidden" name="document_id" value="<?= $documentId ?>">
                            <input type="hidden" name="action"      value="accept">

                            <div class="mb-4">
                                <label for="acceptRemarks" class="block text-xs font-semibold uppercase tracking-wide text-blue-600 mb-1">
                                    Remarks / Notes <span class="font-normal text-blue-400">(optional)</span>
                                </label>
                                <textarea name="remarks" id="acceptRemarks" rows="3"
                                          class="block w-full rounded-xl border border-blue-200 bg-white px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                                          placeholder="Optional remarks…"><?= htmlspecialchars(old('remarks') ?? '') ?></textarea>
                            </div>

                            <button type="submit" id="acceptBtn"
                                    class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 transition">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Accept Document
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- ═══════════════════════════════════════════════════════════
                     SECTION B — ACCEPTED (owned by me): full routing panel
                ════════════════════════════════════════════════════════════ -->
                <?php if ($canProcess): ?>
                    <div class="rounded-2xl border border-gray-200 bg-white p-6" id="process-panel">
                        <div class="mb-5 flex items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Accepted
                            </span>
                        </div>
                        <h2 class="mb-1 text-base font-semibold text-gray-900">Process Document</h2>
                        <p class="mb-5 text-xs text-gray-400">Choose an action for this document.</p>

                        <form method="POST" action="<?= BASE_URL ?>/spsec/inbox/process" id="processForm" novalidate>
                            <input type="hidden" name="document_id" value="<?= $documentId ?>">
                            <input type="hidden" name="action"      id="actionInput" value="">

                            <!-- Remarks / Notes ─────────────────────────── -->
                            <div class="mb-4">
                                <label for="remarks" class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                                    Remarks / Notes
                                    <span id="remarksRequiredHint" class="text-red-500 hidden">*</span>
                                </label>
                                <textarea name="remarks" id="remarks" rows="3"
                                          class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary"
                                          placeholder="Enter remarks (optional unless returning to Admin)…"><?= htmlspecialchars(old('remarks') ?? '') ?></textarea>
                                <p id="remarksHelp" class="mt-1 text-xs text-gray-400 hidden">
                                    A reason is required when returning to Admin.
                                </p>
                            </div>

                            <!-- ── Route to Plenary ─────────────────────── -->
                            <div class="mb-2" id="route-plenary">
                                <button type="button" data-action="route_plenary"
                                        class="action-btn w-full inline-flex items-center justify-center gap-2 rounded-xl border border-violet-300 bg-violet-50 px-4 py-2.5 text-sm font-semibold text-violet-700 hover:bg-violet-100 transition">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                    </svg>
                                    Route to Plenary
                                </button>
                            </div>

                            <!-- ── Route to Committee ───────────────────── -->
                            <div class="mb-2" id="route-committee">
                                <button type="button" data-action="route_committee"
                                        class="action-btn w-full inline-flex items-center justify-center gap-2 rounded-xl border border-amber-300 bg-amber-50 px-4 py-2.5 text-sm font-semibold text-amber-700 hover:bg-amber-100 transition">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m4-4a4 4 0 100-8 4 4 0 000 8z"/>
                                    </svg>
                                    Route to Committee
                                </button>
                                <!-- Committee selector (hidden until action selected) -->
                                <div id="committeeSelectWrap" class="mt-2 hidden">
                                    <label for="committee_id" class="block text-xs font-semibold text-gray-600 mb-1">
                                        Select Committee <span class="text-red-500">*</span>
                                    </label>
                                    <select name="committee_id" id="committee_id"
                                            class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary">
                                        <option value="">— Select a committee —</option>
                                        <?php foreach ($committees ?? [] as $cmte): ?>
                                            <option value="<?= (int) $cmte['id'] ?>"
                                                <?= (old('committee_id') == $cmte['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cmte['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- ── Mark as Noted (Communication only) ───── -->
                            <?php if ($isCommunication): ?>
                            <div class="mb-2" id="noted">
                                <button type="button" data-action="noted"
                                        class="action-btn w-full inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-100 transition">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Mark as Noted
                                </button>
                                <!-- Communication category selector (hidden until Noted selected) -->
                                <div id="categorySelectWrap" class="mt-2 hidden">
                                    <label for="communication_category_id" class="block text-xs font-semibold text-gray-600 mb-1">
                                        Communication Category <span class="text-red-500">*</span>
                                    </label>
                                    <select name="communication_category_id" id="communication_category_id"
                                            class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary">
                                        <option value="">— Select a category —</option>
                                        <?php foreach ($commCategories ?? [] as $cat): ?>
                                            <option value="<?= (int) $cat['id'] ?>"
                                                <?= (old('communication_category_id') == $cat['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- ── Return to Admin ───────────────────────── -->
                            <div class="mt-3 pt-3 border-t border-gray-100">
                                <button type="button" data-action="return_to_admin"
                                        class="action-btn w-full inline-flex items-center justify-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-100 transition">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Return to Admin
                                </button>
                            </div>

                        </form>

                    </div><!-- /processing card -->
                <?php endif; ?>

                <!-- ═══════════════════════════════════════════════════════════
                     SECTION C — No action available
                ════════════════════════════════════════════════════════════ -->
                <?php if (!$canAccept && !$canProcess): ?>
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-6 text-center">
                        <div class="mb-3 flex justify-center">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-200">
                                <svg class="h-6 w-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                        </div>
                        <?php if ($assignmentDecision === 'ACCEPTED'): ?>
                            <p class="text-sm font-semibold text-gray-700">Read Only</p>
                            <p class="mt-1 text-xs text-gray-400">
                                This document is assigned to another SP Secretary user.
                            </p>
                        <?php else: ?>
                            <p class="text-sm font-semibold text-gray-700">No Action Available</p>
                            <p class="mt-1 text-xs text-gray-400">
                                This document has no pending SP Secretary assignment available to you.<br>
                                It may have been accepted by another SP Secretary user or already processed.
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div><!-- /sticky -->
        </div><!-- /right column -->

    </div><!-- /grid -->

    <!-- ═══════════════════════════════════════════════════════════════════
         Process confirmation modal
         Rendered as a direct child of the top-level wrapper (outside every
         sticky / transformed / overflow ancestor) so that `position:fixed`
         covers the full viewport without clipping.
    ═══════════════════════════════════════════════════════════════════ -->
    <div id="confirmModal"
         class="fixed inset-0 z-[9999] hidden items-center justify-center"
         role="dialog"
         aria-modal="true"
         aria-labelledby="confirmModalTitle"
         aria-describedby="confirmModalBody">

        <!-- Full-viewport dimmed + blurred backdrop -->
        <div id="confirmModalBackdrop"
             class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity duration-200 opacity-0"></div>

        <!-- Dialog panel -->
        <div id="confirmModalPanel"
             class="relative z-10 w-full max-w-md mx-4 bg-white rounded-2xl shadow-2xl
                    border border-gray-100 transition-all duration-200 opacity-0"
             style="transform:scale(0.95)">
            <div class="p-6">
                <h3 id="confirmModalTitle"
                    class="text-base font-semibold text-gray-900 mb-2">Confirm Action</h3>
                <p id="confirmModalBody" class="text-sm text-gray-600"></p>
            </div>
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100
                        bg-gray-50 rounded-b-2xl">
                <button type="button" id="confirmCancelBtn"
                        class="inline-flex items-center justify-center rounded-xl px-4 py-2
                               text-sm font-medium text-gray-700 bg-white border border-gray-200
                               hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="button" id="confirmOkBtn"
                        class="inline-flex items-center justify-center rounded-xl px-4 py-2
                               text-sm font-medium text-white bg-primary hover:bg-blue-700 transition">
                    Confirm
                </button>
            </div>
        </div>
    </div>

</div><!-- /space-y-6 -->

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── File upload preview ──────────────────────────────────────────────────
    const extraInput = document.getElementById('extra_attachments');
    const extraList  = document.getElementById('extraFileList');

    if (extraInput && extraList) {
        const selected = new DataTransfer();

        extraInput.addEventListener('change', function () {
            Array.from(this.files).forEach(f => selected.items.add(f));
            this.files = selected.files;
            renderExtraList();
        });

        function renderExtraList() {
            extraList.innerHTML = '';
            Array.from(selected.files).forEach((f, i) => {
                const sz  = (f.size / 1024 / 1024).toFixed(2);
                const div = document.createElement('div');
                div.className = 'flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs';
                div.innerHTML = `
                    <span class="truncate text-gray-700 max-w-[200px]">${escapeHtml(f.name)}</span>
                    <span class="ml-2 shrink-0 text-gray-400">${sz} MB</span>
                    <button type="button" onclick="removeExtra(${i})"
                            class="ml-3 shrink-0 text-red-500 hover:text-red-700">&#x2715;</button>
                `;
                extraList.appendChild(div);
            });
        }

        window.removeExtra = function (index) {
            const dt = new DataTransfer();
            Array.from(selected.files).forEach((f, i) => { if (i !== index) dt.items.add(f); });
            selected.items.clear();
            Array.from(dt.files).forEach(f => selected.items.add(f));
            extraInput.files = selected.files;
            renderExtraList();
        };
    }

    // ── Accept form: prevent double-submit ──────────────────────────────────
    const acceptForm = document.getElementById('acceptForm');
    const acceptBtn  = document.getElementById('acceptBtn');
    if (acceptForm && acceptBtn) {
        acceptForm.addEventListener('submit', function () {
            acceptBtn.disabled = true;
            acceptBtn.innerHTML = '<svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> <span class="ml-2">Accepting\u2026</span>';
        });
    }

    // ── Process form / confirmation modal ───────────────────────────────────
    const actionInput          = document.getElementById('actionInput');
    const remarksEl            = document.getElementById('remarks');
    const remarksHint          = document.getElementById('remarksRequiredHint');
    const remarksHelp          = document.getElementById('remarksHelp');
    const processForm          = document.getElementById('processForm');
    const confirmModal         = document.getElementById('confirmModal');
    const confirmBackdrop      = document.getElementById('confirmModalBackdrop');
    const confirmPanel         = document.getElementById('confirmModalPanel');
    const confirmBody          = document.getElementById('confirmModalBody');
    const confirmOkBtn         = document.getElementById('confirmOkBtn');
    const confirmCancelBtn     = document.getElementById('confirmCancelBtn');
    const committeeSelectWrap  = document.getElementById('committeeSelectWrap');
    const committeeSelect      = document.getElementById('committee_id');
    const categorySelectWrap   = document.getElementById('categorySelectWrap');
    const categorySelect       = document.getElementById('communication_category_id');

    if (!processForm) return;

    const actionLabels = {
        'route_plenary':   'Route this document to Plenary?',
        'route_committee': 'Route this document to the selected Committee?',
        'noted':           'Mark this Communication document as Noted with the selected category?',
        'return_to_admin': 'Return this document to Admin? A return reason is required.',
    };

    // Actions that require the committee selector to be visible
    const needsCommittee = ['route_committee'];
    // Actions that require the category selector to be visible
    const needsCategory  = ['noted'];
    // Actions where remarks is mandatory
    const requiresRemarks = ['return_to_admin'];

    let pendingAction = null;

    // Hide/show sub-fields based on which action button was clicked
    function updateSubFields(action) {
        // Committee selector
        if (committeeSelectWrap) {
            if (needsCommittee.includes(action)) {
                committeeSelectWrap.classList.remove('hidden');
            } else {
                committeeSelectWrap.classList.add('hidden');
                if (committeeSelect) committeeSelect.value = '';
            }
        }
        // Category selector
        if (categorySelectWrap) {
            if (needsCategory.includes(action)) {
                categorySelectWrap.classList.remove('hidden');
            } else {
                categorySelectWrap.classList.add('hidden');
                if (categorySelect) categorySelect.value = '';
            }
        }
        // Remarks required hint
        if (requiresRemarks.includes(action)) {
            if (remarksHint) remarksHint.classList.remove('hidden');
            if (remarksHelp) remarksHelp.classList.remove('hidden');
        } else {
            if (remarksHint) remarksHint.classList.add('hidden');
            if (remarksHelp) remarksHelp.classList.add('hidden');
        }
    }

    document.querySelectorAll('.action-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            pendingAction = this.dataset.action;
            updateSubFields(pendingAction);

            // Client-side validation before showing modal
            if (requiresRemarks.includes(pendingAction) && remarksEl && remarksEl.value.trim() === '') {
                remarksEl.classList.add('border-red-400');
                if (remarksHelp) remarksHelp.classList.remove('hidden');
                if (remarksHint) remarksHint.classList.remove('hidden');
                remarksEl.focus();
                return;
            }

            if (needsCommittee.includes(pendingAction) && committeeSelect && committeeSelect.value === '') {
                committeeSelect.classList.add('border-red-400');
                committeeSelect.focus();
                return;
            }

            if (needsCategory.includes(pendingAction) && categorySelect && categorySelect.value === '') {
                categorySelect.classList.add('border-red-400');
                categorySelect.focus();
                return;
            }

            confirmBody.textContent = actionLabels[pendingAction] || 'Proceed with this action?';
            openModal();
        });
    });

    if (remarksEl) {
        remarksEl.addEventListener('input', function () {
            this.classList.remove('border-red-400');
        });
    }
    if (committeeSelect) {
        committeeSelect.addEventListener('change', function () {
            this.classList.remove('border-red-400');
        });
    }
    if (categorySelect) {
        categorySelect.addEventListener('change', function () {
            this.classList.remove('border-red-400');
        });
    }

    // Confirm OK → submit form
    let isSubmitting = false;
    if (confirmOkBtn) {
        confirmOkBtn.addEventListener('click', function () {
            if (!pendingAction || isSubmitting) return;

            // Re-validate before submit
            if (requiresRemarks.includes(pendingAction) && remarksEl && remarksEl.value.trim() === '') {
                closeModal();
                remarksEl.classList.add('border-red-400');
                remarksEl.focus();
                return;
            }
            if (needsCommittee.includes(pendingAction) && committeeSelect && committeeSelect.value === '') {
                closeModal();
                committeeSelect.classList.add('border-red-400');
                committeeSelect.focus();
                return;
            }
            if (needsCategory.includes(pendingAction) && categorySelect && categorySelect.value === '') {
                closeModal();
                categorySelect.classList.add('border-red-400');
                categorySelect.focus();
                return;
            }

            isSubmitting = true;
            confirmOkBtn.disabled = true;
            confirmOkBtn.innerHTML = '<svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> <span class="ml-2">Processing\u2026</span>';

            if (actionInput) actionInput.value = pendingAction;
            closeModal();
            processForm.submit();
        });
    }

    if (confirmCancelBtn) {
        confirmCancelBtn.addEventListener('click', closeModal);
    }

    // Backdrop click closes only when not yet submitting
    if (confirmBackdrop) {
        confirmBackdrop.addEventListener('click', function () {
            if (!isSubmitting) closeModal();
        });
    }

    // Escape key closes only when not yet submitting
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !confirmModal.classList.contains('hidden') && !isSubmitting) {
            closeModal();
        }
    });

    function openModal() {
        confirmModal.classList.remove('hidden');
        confirmModal.classList.add('flex');
        document.body.style.overflow = 'hidden';   // prevent background scroll
        requestAnimationFrame(() => {
            confirmBackdrop.style.opacity = '1';
            confirmPanel.style.opacity    = '1';
            confirmPanel.style.transform  = 'scale(1)';
            if (confirmCancelBtn) confirmCancelBtn.focus();
        });
    }

    function closeModal() {
        if (isSubmitting) return;
        confirmBackdrop.style.opacity = '0';
        confirmPanel.style.opacity    = '0';
        confirmPanel.style.transform  = 'scale(0.95)';
        setTimeout(() => {
            confirmModal.classList.add('hidden');
            confirmModal.classList.remove('flex');
            document.body.style.overflow = '';     // restore scroll
        }, 200);
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        return String(text).replace(/[&<>"']/g, m => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        })[m]);
    }

    // ── Auto-open sub-fields if URL has an anchor matching an action ─────────
    const hash = window.location.hash;
    if (hash === '#route-committee') {
        const btn = document.querySelector('.action-btn[data-action="route_committee"]');
        if (btn) { updateSubFields('route_committee'); btn.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
    } else if (hash === '#noted') {
        const btn = document.querySelector('.action-btn[data-action="noted"]');
        if (btn) { updateSubFields('noted'); btn.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
    }
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

<?php
/**
 * Plenary Inbox — Document detail, Accept, Pre-decision, and Processing forms
 *
 * Variables supplied by PlenaryInboxController::show():
 *   $document              array        Full document row with joins
 *   $activeAssignment      array|false  Current PENDING/ACCEPTED Plenary assignment
 *   $allAssignments        array
 *   $plenaryDetails        array|null   plenary_document_details row (with joined status)
 *   $authors               array        AUTHOR rows with full_name
 *   $coAuthors             array        CO_AUTHOR rows with full_name
 *   $docCommittees         array        document_committees rows with committee_name
 *   $spMembers             array        All active SP Members for selects
 *   $committees            array        All active Committees for selects
 *   $preDecisionStatuses   array        Allowed pre-decision statuses
 *   $attachments           array
 *   $routes                array
 *   $events                array
 *   $revisions             array
 *   $canAccept             bool
 *   $canProcess            bool
 *   $proposedNumberExempt  bool
 *   $isCommunication       bool         True when the document type is a Communication
 *   $success               string|null
 *   $error                 string|null
 *   $errors                array
 */

$document             = $document             ?? [];
$activeAssignment     = $activeAssignment     ?? false;
$allAssignments       = $allAssignments       ?? [];
$plenaryDetails       = $plenaryDetails       ?? null;
$authors              = $authors              ?? [];
$coAuthors            = $coAuthors            ?? [];
$docCommittees        = $docCommittees        ?? [];
$spMembers            = $spMembers            ?? [];
$committees           = $committees           ?? [];
$preDecisionStatuses  = $preDecisionStatuses  ?? [];
$attachments          = $attachments          ?? [];
$routes               = $routes               ?? [];
$events               = $events               ?? [];
$revisions            = $revisions            ?? [];
$errors               = $errors               ?? [];

$documentId            = (int) ($document['id'] ?? 0);
$trackingNumber        = $document['tracking_number'] ?? '';
$proposedNumberExempt  = $proposedNumberExempt ?? false;
$isCommunication       = $isCommunication ?? false;

if (!isset($canAccept))  { $canAccept  = false; }
if (!isset($canProcess)) { $canProcess = false; }

// Determine if pre-decision is "On Process" to show the processing form
// Use plenary_details for internal workflow logic, but display shared status
$currentPreDecisionStatus = strtolower(trim($plenaryDetails['plenary_status_name'] ?? ''));
$isOnProcess = ($currentPreDecisionStatus === 'on process');

// Outcome-specific flags (status-based tabs)
$isDeferred        = ($currentPreDecisionStatus === 'deferred');
$isLayOnTheTable   = ($currentPreDecisionStatus === 'lay on the table');
$isTerminalOutcome = in_array($currentPreDecisionStatus, ['noted', 'remanded', 'withdrawn'], true);

// Use shared document status for display (now synchronized with Plenary status)
$displayStatusName = $document['status_name'] ?? '';
$displayStatusBadgeColor = $document['status_badge_color'] ?? '#6B7280';

// Terminal outcomes stop every further Plenary action; only the read-only
// history remains. Non-terminal outcomes keep the normal forms available.
$canEditPreDecision   = $canProcess && !$isTerminalOutcome;
$canSaveProcessing    = $canProcess && $isOnProcess && !$isTerminalOutcome;
$canSubmitCompliance  = $canProcess && $isDeferred && !$isTerminalOutcome;
$canReturnToAdmin     = $canProcess && !$isTerminalOutcome;

// Session type labels
$sessionTypeLabels = [
    'REGULAR'        => 'Regular',
    'REGULAR_ONLINE' => 'Regular (Online)',
    'SPECIAL'        => 'Special',
    'SPECIAL_ONLINE' => 'Special (Online)',
];

function plenaryShowPhase(string $phase): string
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

function plenaryShowDecision(string $d): string
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

function plenaryShowEvent(string $et): string
{
    return match ($et) {
        'DOCUMENT_RECEIVED'           => 'Document Received',
        'ROUTED_TO_ADMIN'             => 'Routed to Admin',
        'ADMIN_ACCEPTED'              => 'Admin Accepted',
        'ADMIN_DECLINED'              => 'Admin Declined',
        'ADMIN_RETURNED_TO_RECEIVING' => 'Returned to Receiving',
        'ROUTED_TO_SP_SECRETARY'      => 'Routed to SP Secretary',
        'ROUTED_TO_PLENARY'           => 'Routed to Plenary',
        'ROUTED_TO_COMMITTEE'         => 'Routed to Committee',
        'DOCUMENT_NOTED'              => 'Marked as Noted',
        'DOCUMENT_EDITED'             => 'Document Edited',
        'SP_SECRETARY_ACCEPTED'       => 'SP Secretary Accepted',
        'SP_SECRETARY_RETURNED_TO_ADMIN' => 'SP Secretary Returned to Admin',
        'COMMITTEE_ACCEPTED'          => 'Committee Accepted',
        'COMMITTEE_RETURNED_TO_ADMIN' => 'Returned to Admin by Committee',
        'PLENARY_ACCEPTED'            => 'Plenary Accepted',
        'PLENARY_PRE_DECISION'        => 'Pre-Decision Status Saved',
        'PLENARY_PROCESSING_SAVED'    => 'Plenary Processing Data Saved',
        'PLENARY_RETURNED_TO_ADMIN'   => 'Plenary Returned to Admin',
        'RETURNED_TO_PLENARY'         => 'Returned to Plenary',
        'SECOND_READING_APPROVED'     => 'Second Reading Approved',
        'DOCUMENT_FINALIZED'          => 'Document Finalized',
        'DOCUMENT_FILED'              => 'Document Filed',
        default                       => $et,
    };
}

// Old values for form repopulation after validation failure
$old = old_get();

ob_start();
?>

<div class="space-y-6">

    <!-- Back + Page header --------------------------------------------------->
    <div class="flex items-center gap-4">
        <a href="<?= BASE_URL ?>/plenary/inbox"
           class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-gray-200
                  bg-white text-gray-500 hover:bg-gray-50 transition">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Plenary Inbox</p>
            <h1 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($trackingNumber) ?></h1>
        </div>
    </div>

    <!-- Flash messages -------------------------------------------------------->
    <?php if (!empty($success ?? null)): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($error ?? null)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p class="font-semibold mb-1">Please fix the following errors:</p>
            <ul class="list-disc list-inside space-y-1">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- ─────────────────────────────────────────────────────────────────────── -->
    <!-- Section 1: Document Information                                        -->
    <!-- ─────────────────────────────────────────────────────────────────────── -->
    <section class="rounded-2xl border border-gray-200 bg-white p-6">
        <h2 class="mb-4 text-base font-semibold text-gray-900">Document Information</h2>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Tracking Number</dt>
                <dd class="mt-1 font-mono text-sm font-medium text-gray-900">
                    <?= htmlspecialchars($trackingNumber) ?>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Document Type</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    <?php if (!empty($document['document_type_name'])): ?>
                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                              style="background-color: <?= htmlspecialchars($document['document_type_badge_color'] ?? '#2563EB') ?>1a;
                                     color: <?= htmlspecialchars($document['document_type_badge_color'] ?? '#2563EB') ?>;">
                            <?= htmlspecialchars($document['document_type_name']) ?>
                        </span>
                    <?php else: ?>
                        <span class="italic text-gray-400">—</span>
                    <?php endif; ?>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Status</dt>
                <dd class="mt-1">
                    <?php $bc = $document['status_badge_color'] ?? '#6B7280'; ?>
                    <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold"
                          style="background-color: <?= htmlspecialchars($bc) ?>;
                                 border-color: <?= htmlspecialchars($bc) ?>;
                                 color: white;">
                        <?= htmlspecialchars($document['status_name'] ?? 'Unknown') ?>
                    </span>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Date Received</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    <?= htmlspecialchars(date('F j, Y', strtotime($document['date_received']))) ?>
                    <span class="text-xs text-gray-500">
                        at <?= htmlspecialchars(date('g:i A', strtotime($document['time_received']))) ?>
                    </span>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Current Phase</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    <?= htmlspecialchars(plenaryShowPhase($document['current_phase'] ?? '')) ?>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Source Type</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    <?= htmlspecialchars($document['source_type_name'] ?? '—') ?>
                </dd>
            </div>
            <div class="sm:col-span-2 lg:col-span-3">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Subject Matter</dt>
                <dd class="mt-1 text-sm text-gray-900 whitespace-pre-wrap leading-6">
                    <?= htmlspecialchars($document['subject_matter'] ?? '—') ?>
                </dd>
            </div>
            <?php if (!empty($document['source_name'])): ?>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Source</dt>
                    <dd class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($document['source_name']) ?></dd>
                </div>
            <?php endif; ?>
            <?php if (!empty($document['remarks'])): ?>
                <div class="sm:col-span-2 lg:col-span-3">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Remarks</dt>
                    <dd class="mt-1 text-sm text-gray-900 whitespace-pre-wrap leading-6">
                        <?= htmlspecialchars($document['remarks']) ?>
                    </dd>
                </div>
            <?php endif; ?>
        </dl>
    </section>

    <!-- ─────────────────────────────────────────────────────────────────────── -->
    <!-- Terminal outcome notice (Noted / Remanded / Withdrawn)                 -->
    <!-- ─────────────────────────────────────────────────────────────────────── -->
    <?php if ($isTerminalOutcome): ?>
        <section class="rounded-2xl border border-gray-300 bg-gray-100 p-6">
            <div class="flex items-start gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-200">
                    <svg class="h-5 w-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </span>
                <div>
                    <h2 class="text-base font-semibold text-gray-800">
                        Status: <?= htmlspecialchars($displayStatusName) ?>
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">
                        This outcome is final. No further Plenary processing actions can be taken. The document
                        record, its files and its full history are retained below.
                    </p>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- ─────────────────────────────────────────────────────────────────────── -->
    <!-- Section 2: Accept Button (shown only when document is claimable)       -->
    <!-- ─────────────────────────────────────────────────────────────────────── -->
    <?php if ($canAccept): ?>
        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-amber-900">Accept This Document</h2>
                    <p class="mt-1 text-sm text-amber-700">
                        Accepting claims this document for your exclusive processing. Other Plenary users
                        will no longer see it in their inbox.
                    </p>
                </div>
                <form method="POST" action="<?= BASE_URL ?>/plenary/inbox/accept" class="shrink-0">
                    <input type="hidden" name="document_id" value="<?= $documentId ?>">
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-4 py-2.5
                                   text-sm font-semibold text-white hover:bg-amber-700 transition
                                   focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Accept Document
                    </button>
                </form>
            </div>
        </section>
    <?php endif; ?>

    <!-- ─────────────────────────────────────────────────────────────────────── -->
    <!-- Section 3: Pre-Decision Form (only for the owning user after accept)   -->
    <!-- ─────────────────────────────────────────────────────────────────────── -->
    <?php if ($canEditPreDecision): ?>
        <section class="rounded-2xl border border-indigo-200 bg-white p-6">
            <div class="mb-4 flex items-center gap-2">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-100">
                    <svg class="h-4 w-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </span>
                <h2 class="text-base font-semibold text-indigo-900">Pre-Decision Status</h2>
            </div>

            <?php if ($plenaryDetails): ?>
                <!-- Current status summary (shared document status, synchronized with Plenary) -->
                <div class="mb-4 rounded-xl border border-indigo-100 bg-indigo-50 p-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-xs font-semibold uppercase tracking-wide text-indigo-500">Current Status:</span>
                        <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold"
                              style="background-color: <?= htmlspecialchars($displayStatusBadgeColor) ?>1a;
                                     color: <?= htmlspecialchars($displayStatusBadgeColor) ?>;
                                     border-color: <?= htmlspecialchars($displayStatusBadgeColor) ?>40;">
                            <?= htmlspecialchars($displayStatusName) ?>
                        </span>
                        <?php if (!empty($plenaryDetails['remarks'])): ?>
                            <span class="text-xs text-indigo-600 italic">
                                "<?= htmlspecialchars(mb_strimwidth($plenaryDetails['remarks'], 0, 80, '…')) ?>"
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= BASE_URL ?>/plenary/inbox/process" class="space-y-4">
                <input type="hidden" name="document_id" value="<?= $documentId ?>">
                <input type="hidden" name="action"      value="pre_decision">

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <!-- Pre-decision status -->
                    <div>
                        <label for="pre_decision_status_id"
                               class="block text-sm font-medium text-gray-700 mb-1">
                            Status <span class="text-red-500">*</span>
                        </label>
                        <select id="pre_decision_status_id" name="pre_decision_status_id" required
                                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                                       text-gray-800 focus:border-primary focus:outline-none focus:ring-2
                                       focus:ring-primary/20">
                            <option value="">— Select status —</option>
                            <?php foreach ($preDecisionStatuses as $ps): ?>
                                <option value="<?= (int) $ps['id'] ?>"
                                    <?= (int) ($old['pre_decision_status_id'] ?? ($plenaryDetails['status_id'] ?? 0)) === (int) $ps['id']
                                        ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ps['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($isDeferred): ?>
                            <p class="mt-1 text-xs text-amber-600">
                                You may also submit compliance updates below to resume processing.
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Remarks -->
                    <div>
                        <label for="predecision_remarks"
                               class="block text-sm font-medium text-gray-700 mb-1">Remarks</label>
                        <textarea id="predecision_remarks" name="remarks" rows="2"
                                  class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                                         text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none
                                         focus:ring-2 focus:ring-primary/20"
                                  placeholder="Optional remarks for this pre-decision status…"><?= htmlspecialchars(
                            $old['remarks'] ?? ($plenaryDetails['remarks'] ?? '')
                        ) ?></textarea>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5
                                   text-sm font-semibold text-white hover:bg-indigo-700 transition
                                   focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M5 13l4 4L19 7"/>
                        </svg>
                        Save Pre-Decision
                    </button>
                </div>
            </form>
        </section>

        <!-- ─────────────────────────────────────────────────────────────────── -->
        <!-- Section 3b: Compliance Submission (Deferred → resume processing)   -->
        <!-- ─────────────────────────────────────────────────────────────────── -->
        <?php if ($canSubmitCompliance): ?>
            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-6">
                <div class="mb-4 flex items-start gap-2">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-100">
                        <svg class="h-4 w-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </span>
                    <div>
                        <h2 class="text-base font-semibold text-amber-900">Submit Compliance Updates</h2>
                        <p class="mt-1 text-xs text-amber-700">
                            This document is <strong>Deferred</strong> and is on standby. Record your compliance
                            remarks and attach one or more compliance files to return it to processing. Existing
                            files and remarks are kept in the document history.
                        </p>
                    </div>
                </div>

                <form method="POST" action="<?= BASE_URL ?>/plenary/inbox/process"
                      enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="document_id" value="<?= $documentId ?>">
                    <input type="hidden" name="action"      value="submit_compliance">

                    <div>
                        <label for="compliance_remarks" class="block text-sm font-medium text-gray-700 mb-1">
                            Compliance Remarks <span class="text-red-500">*</span>
                        </label>
                        <textarea id="compliance_remarks" name="remarks" rows="3" required
                                  class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                                         text-gray-800 placeholder-gray-400 focus:border-amber-400 focus:outline-none
                                         focus:ring-2 focus:ring-amber-400/20"
                                  placeholder="Describe the compliance action taken…"><?= htmlspecialchars(
                            $old['remarks'] ?? ''
                        ) ?></textarea>
                    </div>

                    <div>
                        <label for="compliance_files" class="block text-sm font-medium text-gray-700 mb-1">
                            Compliance Files <span class="text-red-500">*</span>
                        </label>
                        <input type="file" id="compliance_files" name="compliance_files[]" multiple required
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp"
                               class="block w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                                      text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-amber-100
                                      file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-amber-700
                                      hover:file:bg-amber-200 focus:border-amber-400 focus:outline-none
                                      focus:ring-2 focus:ring-amber-400/20">
                        <p class="mt-1 text-xs text-gray-400">
                            Attach one or more files (maximum 10, up to 25&nbsp;MB each).
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-4 py-2.5
                                       text-sm font-semibold text-white hover:bg-amber-700 transition
                                       focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M5 13l4 4L19 7"/>
                            </svg>
                            Submit Compliance &amp; Resume Processing
                        </button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <!-- ─────────────────────────────────────────────────────────────────── -->
        <!-- Section 4: Plenary Processing Form (On Process only)         -->
        <!-- ─────────────────────────────────────────────────────────────────── -->
        <?php if ($canSaveProcessing): ?>
            <section class="rounded-2xl border border-violet-200 bg-white p-6">
                <div class="mb-4 flex items-center gap-2">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-violet-100">
                        <svg class="h-4 w-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </span>
                    <div>
                        <h2 class="text-base font-semibold text-violet-900">Plenary Processing Details</h2>
                        <p class="text-xs text-violet-600">Available because status is "On Process"</p>
                    </div>
                </div>

                <?php
                // Current proposed number, dates, etc. for pre-population
                $currentProposedNum  = $old['proposed_number']          ?? ($plenaryDetails['proposed_number']          ?? '');
                $currentSubject      = $old['subject_matter']            ?? $document['subject_matter'];
                $currentTargetDate   = $old['target_first_reading_date'] ?? ($plenaryDetails['target_first_reading_date'] ?? '');
                $currentSessionType  = $old['session_type']              ?? ($plenaryDetails['session_type']             ?? '');
                $currentAddlDetails  = $old['additional_details']        ?? ($plenaryDetails['additional_details']       ?? '');
                $currentAuthorIds    = isset($old['author_ids'])    ? array_map('intval', (array)$old['author_ids'])    : array_column($authors,   'sp_member_id');
                $currentCoAuthorIds  = isset($old['co_author_ids']) ? array_map('intval', (array)$old['co_author_ids']) : array_column($coAuthors, 'sp_member_id');
                $currentCmtIds       = isset($old['committee_ids']) ? array_map('intval', (array)$old['committee_ids']) : array_column($docCommittees, 'committee_id');

                // Normalise option lists for the searchable multi-select widgets.
                $spMembersForJs = array_map(static function ($m) {
                    return [
                        'sp_member_id' => (int) ($m['sp_member_id'] ?? 0),
                        'full_name'    => trim(implode(' ', array_filter([
                            $m['first_name']  ?? '',
                            $m['middle_name'] ?? '',
                            $m['last_name']   ?? '',
                            $m['suffix']      ?? '',
                        ]))),
                        'position'     => $m['position'] ?? '',
                    ];
                }, $spMembers);

                $committeesForJs = array_map(static function ($c) {
                    return ['id' => (int) ($c['id'] ?? 0), 'name' => $c['name'] ?? ''];
                }, $committees);

                $spMembersJson  = json_encode(array_values($spMembersForJs),  JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                $committeesJson = json_encode(array_values($committeesForJs), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                ?>

                <form method="POST" action="<?= BASE_URL ?>/plenary/inbox/process" class="space-y-6"
                      data-ms-processing-form>
                    <input type="hidden" name="document_id" value="<?= $documentId ?>">
                    <input type="hidden" name="action"      value="save_processing">
                    <!-- Selections made in the searchable dropdowns are serialized here on submit. -->
                    <div data-ms-post hidden></div>

                    <!-- Subject Matter -->
                    <div>
                        <label for="subject_matter" class="block text-sm font-medium text-gray-700 mb-1">
                            Subject Matter <span class="text-red-500">*</span>
                        </label>
                        <textarea id="subject_matter" name="subject_matter" rows="3" required
                                  maxlength="5000"
                                  class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                                         text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none
                                         focus:ring-2 focus:ring-primary/20"
                                  placeholder="Subject matter of the document…"><?= htmlspecialchars($currentSubject) ?></textarea>
                        <p class="mt-1 text-xs text-gray-400">
                            <?php if ($isCommunication): ?>
                                This updates the communication's title.
                            <?php else: ?>
                                Preserve or update the subject matter as needed for the plenary record.
                            <?php endif; ?>
                        </p>
                    </div>

                    <?php if (!$isCommunication): ?>
                    <!-- Proposed Number -->
                    <div>
                        <label for="proposed_number" class="block text-sm font-medium text-gray-700 mb-1">
                            Proposed No.
                            <?php if (!$proposedNumberExempt): ?>
                                <span class="text-red-500">*</span>
                            <?php else: ?>
                                <span class="text-xs font-normal text-gray-400">
                                    (not required for <?= htmlspecialchars($document['document_type_name'] ?? 'this type') ?>)
                                </span>
                            <?php endif; ?>
                        </label>
                        <input type="text" id="proposed_number" name="proposed_number"
                               value="<?= htmlspecialchars($currentProposedNum) ?>"
                               maxlength="50"
                               <?= !$proposedNumberExempt ? 'required' : '' ?>
                               placeholder="e.g. SB-2026-001"
                               class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                                      text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none
                                      focus:ring-2 focus:ring-primary/20">
                        <p class="mt-1 text-xs text-gray-400">
                            <strong class="text-amber-600">Note:</strong>
                            No standard numbering format has been configured for this system yet.
                            Please enter the proposed number in whatever format your office uses.
                            Must be unique across all Plenary documents.
                        </p>
                    </div>

                    <!-- Authors and Co-Authors -->
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <!-- Authors -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Authors
                                <span class="text-xs font-normal text-gray-400">(select one or more)</span>
                            </label>
                            <p class="mb-2 text-xs text-gray-500">Search and select multiple SP members.</p>
                            <div class="ar-multi-select" data-list-key="spMembers" data-role="AUTHOR"
                                 data-selected="<?= htmlspecialchars(json_encode(array_values($currentAuthorIds)), ENT_QUOTES, 'UTF-8') ?>">
                                <div class="ar-multi-control">
                                    <div class="ar-multi-chips"></div>
                                    <input type="text" class="ar-multi-filter"
                                           placeholder="Search authors…"
                                           aria-label="Search authors" role="combobox"
                                           aria-expanded="false" aria-autocomplete="list" autocomplete="off">
                                    <button type="button" class="ar-multi-toggle" aria-label="Show author options">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                </div>
                                <div class="ar-multi-dropdown hidden" role="listbox" aria-multiselectable="true"><div class="ar-multi-options"></div></div>
                                <div class="ar-multi-post-fields hidden"></div>
                            </div>
                        </div>

                        <!-- Co-Authors -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Co-Authors
                                <span class="text-xs font-normal text-gray-400">(select zero or more)</span>
                            </label>
                            <p class="mb-2 text-xs text-gray-500">
                                Search and select multiple SP members. A member already set as an Author cannot be chosen here.
                            </p>
                            <div class="ar-multi-select" data-list-key="spMembers" data-role="CO_AUTHOR"
                                 data-selected="<?= htmlspecialchars(json_encode(array_values($currentCoAuthorIds)), ENT_QUOTES, 'UTF-8') ?>">
                                <div class="ar-multi-control">
                                    <div class="ar-multi-chips"></div>
                                    <input type="text" class="ar-multi-filter"
                                           placeholder="Search co-authors…"
                                           aria-label="Search co-authors" role="combobox"
                                           aria-expanded="false" aria-autocomplete="list" autocomplete="off">
                                    <button type="button" class="ar-multi-toggle" aria-label="Show co-author options">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                </div>
                                <div class="ar-multi-dropdown hidden" role="listbox" aria-multiselectable="true"><div class="ar-multi-options"></div></div>
                                <div class="ar-multi-post-fields hidden"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Committees in Charge -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Committees in Charge
                            <span class="text-xs font-normal text-gray-400">(select one or more)</span>
                        </label>
                        <p class="mb-2 text-xs text-gray-500">Search and select multiple committees.</p>
                        <div class="ar-multi-select" data-list-key="committees"
                             data-selected="<?= htmlspecialchars(json_encode(array_values($currentCmtIds)), ENT_QUOTES, 'UTF-8') ?>">
                            <div class="ar-multi-control">
                                <div class="ar-multi-chips"></div>
                                <input type="text" class="ar-multi-filter"
                                       placeholder="Search and select committees…"
                                       aria-label="Search committees" role="combobox"
                                       aria-expanded="false" aria-autocomplete="list" autocomplete="off">
                                <button type="button" class="ar-multi-toggle" aria-label="Show committee options">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                            </div>
                            <div class="ar-multi-dropdown hidden" role="listbox" aria-multiselectable="true"><div class="ar-multi-options"></div></div>
                            <div class="ar-multi-post-fields hidden"></div>
                        </div>
                    </div>
                    <?php endif; // !$isCommunication (Proposed Number + authors + committees) ?>

                    <!-- Target First Reading Date + Session Type -->
                    <div class="grid grid-cols-1 gap-4 <?= $isCommunication ? '' : 'sm:grid-cols-2' ?>">
                        <div>
                            <label for="target_first_reading_date"
                                   class="block text-sm font-medium text-gray-700 mb-1">
                                Target First Reading Date
                            </label>
                            <input type="date" id="target_first_reading_date"
                                   name="target_first_reading_date"
                                   value="<?= htmlspecialchars($currentTargetDate) ?>"
                                   class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2
                                          text-sm text-gray-800 focus:border-primary focus:outline-none
                                          focus:ring-2 focus:ring-primary/20">
                        </div>

                        <?php if (!$isCommunication): ?>
                        <div>
                            <label for="session_type"
                                   class="block text-sm font-medium text-gray-700 mb-1">
                                Session Type
                            </label>
                            <select id="session_type" name="session_type"
                                    class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2
                                           text-sm text-gray-800 focus:border-primary focus:outline-none
                                           focus:ring-2 focus:ring-primary/20">
                                <option value="">— Select session type —</option>
                                <?php foreach ($sessionTypeLabels as $val => $label): ?>
                                    <option value="<?= htmlspecialchars($val) ?>"
                                        <?= $currentSessionType === $val ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Additional Details -->
                    <?php if (!$isCommunication): ?>
                    <div>
                        <label for="additional_details" class="block text-sm font-medium text-gray-700 mb-1">
                            Additional Details
                        </label>
                        <textarea id="additional_details" name="additional_details" rows="3"
                                  class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2
                                         text-sm text-gray-800 placeholder-gray-400 focus:border-primary
                                         focus:outline-none focus:ring-2 focus:ring-primary/20"
                                  placeholder="Any additional notes or details…"><?= htmlspecialchars($currentAddlDetails) ?></textarea>
                    </div>
                    <?php endif; ?>

                    <div class="flex items-center gap-3 border-t border-gray-100 pt-4">
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-5 py-2.5
                                       text-sm font-semibold text-white hover:bg-violet-700 transition
                                       focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                            </svg>
                            Save Processing Details
                        </button>
                    </div>
                </form>

                <style>
                /* Searchable multi-select — mirrors the archived-records pattern. */
                .ar-multi-select { position:relative; }
                .ar-multi-control { display:flex; min-height:2.75rem; flex-wrap:wrap; align-items:center; gap:0.375rem; border:1px solid #e5e7eb; border-radius:0.75rem; background:#fff; padding:0.375rem 0.5rem 0.375rem 0.75rem; }
                .ar-multi-control:focus-within { border-color:var(--color-primary,#1d4ed8); box-shadow:0 0 0 3px rgba(29,78,216,.12); }
                .ar-multi-chips { display:contents; }
                .ar-multi-filter { min-width:10rem; flex:1; border:0; background:transparent; padding:0.25rem 0; color:#1f2937; font-size:0.875rem; outline:none; }
                .ar-multi-filter::placeholder { color:#9ca3af; }
                .ar-multi-toggle { display:flex; height:1.75rem; width:1.75rem; flex-shrink:0; align-items:center; justify-content:center; border:0; border-radius:0.5rem; background:transparent; color:#9ca3af; cursor:pointer; }
                .ar-multi-toggle:hover { background:#f3f4f6; color:#4b5563; }
                .ar-multi-dropdown { position:absolute; top:calc(100% + 4px); left:0; right:0; z-index:210; max-height:15rem; overflow-y:auto; border:1px solid #e5e7eb; border-radius:0.75rem; background:#fff; box-shadow:0 10px 25px -5px rgba(0,0,0,.12),0 4px 10px -3px rgba(0,0,0,.07); }
                .ar-multi-dropdown.hidden { display:none; }
                .ar-multi-option { display:flex; width:100%; align-items:center; gap:0.625rem; border:0; background:#fff; padding:0.625rem 0.75rem; text-align:left; color:#374151; font-size:0.875rem; cursor:pointer; }
                .ar-multi-option:hover,.ar-multi-option.ar-option-focused { background:#eff6ff; color:#1d4ed8; }
                .ar-multi-option[aria-selected="true"] { background:#eff6ff; color:#1d4ed8; font-weight:600; }
                .ar-multi-option.ar-option-disabled,.ar-multi-option.ar-option-disabled:hover { background:#f9fafb; color:#9ca3af; cursor:not-allowed; }
                .ar-multi-option-check { display:flex; height:1rem; width:1rem; flex-shrink:0; align-items:center; justify-content:center; border:1px solid #d1d5db; border-radius:0.25rem; }
                .ar-multi-option[aria-selected="true"] .ar-multi-option-check { border-color:#1d4ed8; background:#1d4ed8; color:#fff; }
                .ar-multi-chip { display:inline-flex; max-width:100%; align-items:center; gap:0.25rem; border:1px solid #bfdbfe; border-radius:9999px; background:#eff6ff; padding:0.2rem 0.35rem 0.2rem 0.625rem; color:#1d4ed8; font-size:0.75rem; font-weight:500; }
                .ar-multi-chip-label { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
                .ar-multi-chip-remove { display:flex; height:1rem; width:1rem; flex-shrink:0; align-items:center; justify-content:center; border:0; border-radius:50%; background:transparent; color:#60a5fa; cursor:pointer; }
                .ar-multi-chip-remove:hover { background:#bfdbfe; color:#1d4ed8; }
                .ar-multi-empty { padding:0.75rem; color:#9ca3af; text-align:center; font-size:0.8125rem; }
                </style>

                <script>
                // Searchable multi-select for Authors, Co-Authors and Committees.
                // Selections are serialized into the original POST field names on submit;
                // SP members already chosen as Authors are disabled in the Co-Authors list.
                (function () {
                'use strict';

                var DATA = {
                    spMembers:  <?= $spMembersJson ?>,
                    committees: <?= $committeesJson ?>
                };

                var widgets = [];

                function initMultiSelect(widget) {
                    if (widget.dataset.msInited) { return; }
                    widget.dataset.msInited = '1';

                    var listKey   = widget.dataset.listKey;
                    var idField   = listKey === 'spMembers' ? 'sp_member_id' : 'id';
                    var nameField = listKey === 'spMembers' ? 'full_name'    : 'name';
                    var items     = DATA[listKey] || [];
                    var role      = widget.dataset.role || '';
                    var form      = widget.closest('form');

                    var chips    = widget.querySelector('.ar-multi-chips');
                    var filter   = widget.querySelector('.ar-multi-filter');
                    var toggle   = widget.querySelector('.ar-multi-toggle');
                    var dropdown = widget.querySelector('.ar-multi-dropdown');
                    var options  = widget.querySelector('.ar-multi-options');

                    var selected   = new Set(JSON.parse(widget.dataset.selected || '[]').map(String));
                    var focusedIdx = -1;

                    function saveSelection() {
                        widget.dataset.selected = JSON.stringify(Array.from(selected));
                    }

                    // A member chosen in the opposite role (AUTHOR vs CO_AUTHOR) is locked out.
                    function assignedToOtherRole(id) {
                        if (listKey !== 'spMembers' || !role || !form) { return false; }
                        return Array.prototype.slice
                            .call(form.querySelectorAll('.ar-multi-select[data-list-key="spMembers"]'))
                            .some(function (other) {
                                return other !== widget &&
                                    other.dataset.role !== role &&
                                    JSON.parse(other.dataset.selected || '[]').map(String).indexOf(String(id)) !== -1;
                            });
                    }

                    // Keep the sibling role's option list in sync after add/remove.
                    function refreshSiblings() {
                        widgets.forEach(function (other) {
                            if (other !== widget && other.dataset.listKey === 'spMembers' && other.__renderOptions) {
                                other.__renderOptions(other.querySelector('.ar-multi-filter').value);
                            }
                        });
                    }

                    function renderChips() {
                        chips.replaceChildren();
                        selected.forEach(function (id) {
                            var item = items.filter(function (o) { return String(o[idField]) === id; })[0];
                            if (!item) { return; }
                            var chip = document.createElement('span');
                            chip.className = 'ar-multi-chip';
                            var label = document.createElement('span');
                            label.className = 'ar-multi-chip-label';
                            label.textContent = String(item[nameField] || '');
                            var remove = document.createElement('button');
                            remove.type = 'button';
                            remove.className = 'ar-multi-chip-remove';
                            remove.setAttribute('aria-label', 'Remove ' + String(item[nameField] || 'selection'));
                            remove.textContent = '\u00d7';
                            remove.addEventListener('click', function (event) {
                                event.stopPropagation();
                                selected.delete(id);
                                saveSelection();
                                renderChips();
                                renderOptions(filter.value);
                                refreshSiblings();
                                filter.focus();
                            });
                            chip.append(label, remove);
                            chips.appendChild(chip);
                        });
                    }

                    function renderOptions(query) {
                        var q = String(query || '').trim().toLocaleLowerCase();
                        options.replaceChildren();
                        focusedIdx = -1;
                        var matching = items.filter(function (item) {
                            return String(item[nameField] || '').toLocaleLowerCase().indexOf(q) !== -1;
                        });
                        if (!matching.length) {
                            var empty = document.createElement('div');
                            empty.className = 'ar-multi-empty';
                            empty.textContent = q ? 'No matching options' : 'No options available';
                            options.appendChild(empty);
                            return;
                        }
                        matching.forEach(function (item) {
                            var id         = String(item[idField]);
                            var isSelected = selected.has(id);
                            var isBlocked  = assignedToOtherRole(id) && !isSelected;
                            var option = document.createElement('button');
                            option.type = 'button';
                            option.className = 'ar-multi-option' + (isBlocked ? ' ar-option-disabled' : '');
                            option.setAttribute('role', 'option');
                            option.setAttribute('aria-selected', String(isSelected));
                            option.disabled = isBlocked;
                            var check = document.createElement('span');
                            check.className = 'ar-multi-option-check';
                            if (isSelected) { check.textContent = '\u2713'; }
                            var label = document.createElement('span');
                            label.textContent = String(item[nameField] || '') +
                                (listKey === 'spMembers' && item.position ? ' \u2014 ' + item.position : '');
                            option.append(check, label);
                            option.addEventListener('click', function (event) {
                                event.stopPropagation();
                                if (isBlocked || assignedToOtherRole(id)) { return; }
                                if (selected.has(id)) { selected.delete(id); } else { selected.add(id); }
                                saveSelection();
                                renderChips();
                                renderOptions(filter.value);
                                refreshSiblings();
                                filter.focus();
                            });
                            options.appendChild(option);
                        });
                    }

                    function focusOption(delta) {
                        var opts = Array.prototype.slice.call(
                            options.querySelectorAll('.ar-multi-option:not(:disabled)')
                        );
                        if (!opts.length) { return; }
                        if (focusedIdx >= 0 && opts[focusedIdx]) {
                            opts[focusedIdx].classList.remove('ar-option-focused');
                        }
                        focusedIdx += delta;
                        if (focusedIdx < 0) { focusedIdx = opts.length - 1; }
                        if (focusedIdx >= opts.length) { focusedIdx = 0; }
                        opts[focusedIdx].classList.add('ar-option-focused');
                        opts[focusedIdx].scrollIntoView({ block: 'nearest' });
                    }

                    function openDropdown() {
                        dropdown.classList.remove('hidden');
                        filter.setAttribute('aria-expanded', 'true');
                        renderOptions(filter.value);
                    }
                    function closeDropdown() {
                        dropdown.classList.add('hidden');
                        filter.setAttribute('aria-expanded', 'false');
                    }

                    filter.addEventListener('focus', openDropdown);
                    filter.addEventListener('input', function () { renderOptions(filter.value); });
                    filter.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape') { closeDropdown(); return; }
                        if (event.key === 'ArrowDown') { event.preventDefault(); focusOption(1); return; }
                        if (event.key === 'ArrowUp')   { event.preventDefault(); focusOption(-1); return; }
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            var target = focusedIdx >= 0
                                ? options.querySelectorAll('.ar-multi-option:not(:disabled)')[focusedIdx]
                                : options.querySelector('.ar-multi-option:not(:disabled)');
                            if (target) { target.click(); }
                            return;
                        }
                        if (event.key === 'Backspace' && !filter.value && selected.size) {
                            selected.delete(Array.from(selected).pop());
                            saveSelection(); renderChips(); renderOptions(''); refreshSiblings();
                        }
                    });

                    toggle.addEventListener('click', function (event) {
                        event.stopPropagation();
                        if (dropdown.classList.contains('hidden')) { filter.focus(); openDropdown(); }
                        else { closeDropdown(); }
                    });
                    widget.querySelector('.ar-multi-control').addEventListener('click', function (event) {
                        if (event.target !== toggle && !event.target.closest('.ar-multi-chip-remove')) {
                            filter.focus();
                        }
                    });

                    widget.__renderOptions = renderOptions;
                    renderChips();
                    renderOptions('');
                }

                // Close any open dropdown when clicking outside of it.
                document.addEventListener('click', function (event) {
                    document.querySelectorAll('.ar-multi-select .ar-multi-dropdown:not(.hidden)').forEach(function (dropdown) {
                        var widget = dropdown.closest('.ar-multi-select');
                        if (!widget.contains(event.target)) {
                            dropdown.classList.add('hidden');
                            var f = widget.querySelector('.ar-multi-filter');
                            if (f) { f.setAttribute('aria-expanded', 'false'); }
                        }
                    });
                });

                // Emit hidden inputs using the field names the backend expects.
                function serializeMultiSelects(form) {
                    var post = form.querySelector('[data-ms-post]');
                    if (!post) { return; }
                    post.replaceChildren();
                    form.querySelectorAll('.ar-multi-select').forEach(function (widget) {
                        var name = widget.dataset.listKey === 'committees'
                            ? 'committee_ids[]'
                            : (widget.dataset.role === 'CO_AUTHOR' ? 'co_author_ids[]' : 'author_ids[]');
                        JSON.parse(widget.dataset.selected || '[]').map(String).forEach(function (id) {
                            var input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = name;
                            input.value = id;
                            post.appendChild(input);
                        });
                    });
                }

                var form = document.querySelector('[data-ms-processing-form]');
                if (form) {
                    form.querySelectorAll('.ar-multi-select').forEach(function (widget) {
                        widgets.push(widget);
                        initMultiSelect(widget);
                    });
                    form.addEventListener('submit', function () { serializeMultiSelects(form); });
                }
                })();
                </script>
            </section>
        <?php else: ?>
            <!-- Processing form is locked — show current data read-only -->
            <?php if ($plenaryDetails): ?>
                <section class="rounded-2xl border border-gray-200 bg-gray-50 p-6">
                    <div class="mb-3 flex items-center gap-2">
                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        <p class="text-sm text-gray-500 italic">
                            Plenary processing form is available only when the pre-decision status is
                            <strong>"On Process"</strong>.
                        </p>
                    </div>
                    <?php require __DIR__ . '/../../partials/plenary-details.php'; ?>
                </section>
            <?php endif; ?>
        <?php endif; ?>

        <!-- ─────────────────────────────────────────────────────────────────── -->
        <!-- Section 5: Return to Admin                                         -->
        <!-- ─────────────────────────────────────────────────────────────────── -->
        <section class="rounded-2xl border border-red-100 bg-red-50 p-6">
            <h2 class="mb-3 text-base font-semibold text-red-900">Return to Admin</h2>
            <p class="mb-4 text-sm text-red-700">
                This will return the document to the Admin inbox. A reason is required.
                All processing data entered above will be preserved.
            </p>
            <form method="POST" action="<?= BASE_URL ?>/plenary/inbox/process" class="space-y-3">
                <input type="hidden" name="document_id" value="<?= $documentId ?>">
                <input type="hidden" name="action"      value="return_to_admin">
                <div>
                    <label for="return_remarks" class="block text-sm font-medium text-red-800 mb-1">
                        Reason <span class="text-red-500">*</span>
                    </label>
                    <textarea id="return_remarks" name="remarks" rows="2" required
                              class="w-full rounded-xl border border-red-200 bg-white px-3 py-2 text-sm
                                     text-gray-800 placeholder-gray-400 focus:border-red-400 focus:outline-none
                                     focus:ring-2 focus:ring-red-400/20"
                              placeholder="Reason for returning to Admin…"></textarea>
                </div>
                <button type="submit"
                        onclick="return confirm('Return this document to Admin? This action cannot be undone.')"
                        class="inline-flex items-center gap-2 rounded-xl border border-red-300 bg-white
                               px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50 transition
                               focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                    </svg>
                    Return to Admin
                </button>
            </form>
        </section>

    <?php endif; /* end $canEditPreDecision ($canProcess && !terminal) */ ?>

    <!-- ─────────────────────────────────────────────────────────────────── -->
    <!-- Section 6: Saved Plenary Details (read-only, shown to all owners)  -->
    <!-- ─────────────────────────────────────────────────────────────────── -->
    <?php if ((!$canProcess || $isTerminalOutcome) && $plenaryDetails): ?>
        <section class="rounded-2xl border border-indigo-100 bg-indigo-50 p-6">
            <h2 class="mb-4 text-base font-semibold text-indigo-900">Plenary Details (Saved)</h2>
            <?php require __DIR__ . '/../../partials/plenary-details.php'; ?>

            <!-- Authors / Co-Authors (read-only) -->
            <?php if (!empty($authors) || !empty($coAuthors)): ?>
                <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <?php if (!empty($authors)): ?>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Authors</dt>
                            <dd class="mt-2 space-y-1">
                                <?php foreach ($authors as $a): ?>
                                    <div class="text-sm text-indigo-900"><?= htmlspecialchars($a['full_name'] ?? '') ?></div>
                                <?php endforeach; ?>
                            </dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($coAuthors)): ?>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Co-Authors</dt>
                            <dd class="mt-2 space-y-1">
                                <?php foreach ($coAuthors as $a): ?>
                                    <div class="text-sm text-indigo-900"><?= htmlspecialchars($a['full_name'] ?? '') ?></div>
                                <?php endforeach; ?>
                            </dd>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Committees (read-only) -->
            <?php if (!empty($docCommittees)): ?>
                <div class="mt-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-indigo-400">Committees in Charge</dt>
                    <dd class="mt-2 flex flex-wrap gap-2">
                        <?php foreach ($docCommittees as $dc): ?>
                            <span class="inline-flex items-center rounded-lg bg-indigo-100 px-2.5 py-1
                                         text-xs font-medium text-indigo-700">
                                <?= htmlspecialchars($dc['committee_name'] ?? '') ?>
                            </span>
                        <?php endforeach; ?>
                    </dd>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <!-- ─────────────────────────────────────────────────────────────────── -->
    <!-- Section 7: Attachments                                             -->
    <!-- ─────────────────────────────────────────────────────────────────── -->
    <?php if (!empty($attachments)): ?>
        <section class="rounded-2xl border border-gray-200 bg-white p-6">
            <h2 class="mb-4 text-base font-semibold text-gray-900">
                Attachments
                <span class="ml-1 text-xs font-normal text-gray-400">(<?= count($attachments) ?>)</span>
            </h2>
            <ul class="divide-y divide-gray-100">
                <?php foreach ($attachments as $att): ?>
                    <li class="flex items-center gap-3 py-3">
                        <svg class="h-5 w-5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                        </svg>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-800 truncate">
                                <?= htmlspecialchars($att['file_name'] ?? '') ?>
                            </div>
                            <div class="text-xs text-gray-400">
                                <?= htmlspecialchars($att['uploaded_by_username'] ?? '') ?>
                                — <?= htmlspecialchars(date('M j, Y g:i A', strtotime($att['created_at']))) ?>
                                <?php if (!empty($att['attachment_type'])): ?>
                                    — <span class="font-medium"><?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $att['attachment_type'])))) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($att['stored_path'])): ?>
                            <a href="<?= BASE_URL ?>/public/<?= htmlspecialchars(ltrim($att['stored_path'], '/')) ?>"
                               target="_blank" rel="noopener"
                               class="shrink-0 rounded-lg border border-gray-200 px-2.5 py-1 text-xs
                                      font-medium text-gray-600 hover:bg-gray-50 transition">
                                Download
                            </a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <!-- ─────────────────────────────────────────────────────────────────── -->
    <!-- Section 8: History (Events / Routes / Assignments)                 -->
    <!-- ─────────────────────────────────────────────────────────────────── -->
    <?php if (!empty($events) || !empty($routes)): ?>
        <section class="rounded-2xl border border-gray-200 bg-white p-6">
            <h2 class="mb-4 text-base font-semibold text-gray-900">Activity History</h2>
            <ol class="space-y-3">
                <?php foreach ($events as $ev): ?>
                    <li class="flex gap-3">
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full
                                    bg-indigo-100 text-indigo-600 text-xs font-bold">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-800">
                                <?= htmlspecialchars(plenaryShowEvent($ev['event_type'] ?? '')) ?>
                                <?php if (!empty($ev['to_status_name'])): ?>
                                    <span class="text-xs font-normal text-gray-500">
                                        → <?= htmlspecialchars($ev['to_status_name']) ?>
                                    </span>
                                <?php endif; ?>
                            </p>
                            <p class="text-xs text-gray-400">
                                <?= htmlspecialchars($ev['performed_by_username'] ?? '') ?>
                                — <?= htmlspecialchars(date('M j, Y g:i A', strtotime($ev['created_at']))) ?>
                            </p>
                            <?php if (!empty($ev['remarks'])): ?>
                                <p class="mt-0.5 text-xs text-gray-500 italic">
                                    "<?= htmlspecialchars(mb_strimwidth($ev['remarks'], 0, 120, '…')) ?>"
                                </p>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    <?php endif; ?>

    <!-- Assignment History -->
    <?php if (!empty($allAssignments)): ?>
        <section class="rounded-2xl border border-gray-200 bg-white p-6">
            <h2 class="mb-4 text-base font-semibold text-gray-900">Assignment History</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 text-gray-500 uppercase">
                        <tr>
                            <th class="px-4 py-2 font-medium">Phase</th>
                            <th class="px-4 py-2 font-medium">Role</th>
                            <th class="px-4 py-2 font-medium">Decision</th>
                            <th class="px-4 py-2 font-medium">Accepted By</th>
                            <th class="px-4 py-2 font-medium">Accepted At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($allAssignments as $asgn): ?>
                            <tr>
                                <td class="px-4 py-2"><?= htmlspecialchars(plenaryShowPhase($asgn['phase'] ?? '')) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($asgn['role_name'] ?? '—') ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars(plenaryShowDecision($asgn['decision'] ?? '')) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($asgn['accepted_by_username'] ?? '—') ?></td>
                                <td class="px-4 py-2">
                                    <?= !empty($asgn['accepted_at'])
                                        ? htmlspecialchars(date('M j, Y g:i A', strtotime($asgn['accepted_at'])))
                                        : '—' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

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

if (!isset($canAccept))  { $canAccept  = false; }
if (!isset($canProcess)) { $canProcess = false; }

// Determine if pre-decision is "Under Processing" to show the processing form
$currentPreDecisionStatus = strtolower(trim($plenaryDetails['plenary_status_name'] ?? ''));
$isUnderProcessing = ($currentPreDecisionStatus === 'under processing');

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
    <?php if ($canProcess): ?>
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
                <!-- Current pre-decision status summary -->
                <div class="mb-4 rounded-xl border border-indigo-100 bg-indigo-50 p-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-xs font-semibold uppercase tracking-wide text-indigo-500">Current Status:</span>
                        <?php $plBadge = $plenaryDetails['plenary_status_badge_color'] ?? '#6B7280'; ?>
                        <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold"
                              style="background-color: <?= htmlspecialchars($plBadge) ?>1a;
                                     color: <?= htmlspecialchars($plBadge) ?>;
                                     border-color: <?= htmlspecialchars($plBadge) ?>40;">
                            <?= htmlspecialchars($plenaryDetails['plenary_status_name'] ?? '') ?>
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
                               class="block text-sm font-medium text-gray-700">
                            Status <span class="text-red-500">*</span>
                        </label>
                        <select id="pre_decision_status_id" name="pre_decision_status_id" required
                                class="mt-1.5 w-full rounded-xl border border-gray-200 bg-white py-2 px-3 text-sm
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
                    </div>

                    <!-- Remarks -->
                    <div>
                        <label for="predecision_remarks"
                               class="block text-sm font-medium text-gray-700">Remarks</label>
                        <textarea id="predecision_remarks" name="remarks" rows="2"
                                  class="mt-1.5 w-full rounded-xl border border-gray-200 bg-white py-2 px-3 text-sm
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
        <!-- Section 4: Plenary Processing Form (Under Processing only)         -->
        <!-- ─────────────────────────────────────────────────────────────────── -->
        <?php if ($isUnderProcessing): ?>
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
                        <p class="text-xs text-violet-600">Available because status is "Under Processing"</p>
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
                ?>

                <form method="POST" action="<?= BASE_URL ?>/plenary/inbox/process" class="space-y-6">
                    <input type="hidden" name="document_id" value="<?= $documentId ?>">
                    <input type="hidden" name="action"      value="save_processing">

                    <!-- Subject Matter -->
                    <div>
                        <label for="subject_matter" class="block text-sm font-medium text-gray-700">
                            Subject Matter <span class="text-red-500">*</span>
                        </label>
                        <textarea id="subject_matter" name="subject_matter" rows="3" required
                                  maxlength="5000"
                                  class="mt-1.5 w-full rounded-xl border border-gray-200 bg-white py-2 px-3 text-sm
                                         text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none
                                         focus:ring-2 focus:ring-primary/20"
                                  placeholder="Subject matter of the document…"><?= htmlspecialchars($currentSubject) ?></textarea>
                        <p class="mt-1 text-xs text-gray-400">
                            Preserve or update the subject matter as needed for the plenary record.
                        </p>
                    </div>

                    <!-- Proposed Number -->
                    <div>
                        <label for="proposed_number" class="block text-sm font-medium text-gray-700">
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
                               class="mt-1.5 w-full rounded-xl border border-gray-200 bg-white py-2 px-3 text-sm
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
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Authors
                                <span class="text-xs font-normal text-gray-400">(select one or more)</span>
                            </label>
                            <div class="max-h-48 overflow-y-auto rounded-xl border border-gray-200 bg-white p-2">
                                <?php if (empty($spMembers)): ?>
                                    <p class="p-2 text-xs text-gray-400 italic">No active SP Members found.</p>
                                <?php else: ?>
                                    <?php foreach ($spMembers as $member): ?>
                                        <?php
                                        $memId   = (int) $member['sp_member_id'];
                                        $memName = trim(implode(' ', array_filter([
                                            $member['first_name']  ?? '',
                                            $member['middle_name'] ?? '',
                                            $member['last_name']   ?? '',
                                            $member['suffix']      ?? '',
                                        ])));
                                        ?>
                                        <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm
                                                      cursor-pointer hover:bg-gray-50">
                                            <input type="checkbox" name="author_ids[]"
                                                   value="<?= $memId ?>"
                                                   <?= in_array($memId, $currentAuthorIds, true) ? 'checked' : '' ?>
                                                   class="h-4 w-4 rounded border-gray-300 text-primary
                                                          focus:ring-primary">
                                            <span class="text-gray-800"><?= htmlspecialchars($memName) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Co-Authors -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Co-Authors
                                <span class="text-xs font-normal text-gray-400">(select zero or more)</span>
                            </label>
                            <div class="max-h-48 overflow-y-auto rounded-xl border border-gray-200 bg-white p-2">
                                <?php if (empty($spMembers)): ?>
                                    <p class="p-2 text-xs text-gray-400 italic">No active SP Members found.</p>
                                <?php else: ?>
                                    <?php foreach ($spMembers as $member): ?>
                                        <?php
                                        $memId   = (int) $member['sp_member_id'];
                                        $memName = trim(implode(' ', array_filter([
                                            $member['first_name']  ?? '',
                                            $member['middle_name'] ?? '',
                                            $member['last_name']   ?? '',
                                            $member['suffix']      ?? '',
                                        ])));
                                        ?>
                                        <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm
                                                      cursor-pointer hover:bg-gray-50">
                                            <input type="checkbox" name="co_author_ids[]"
                                                   value="<?= $memId ?>"
                                                   <?= in_array($memId, $currentCoAuthorIds, true) ? 'checked' : '' ?>
                                                   class="h-4 w-4 rounded border-gray-300 text-primary
                                                          focus:ring-primary">
                                            <span class="text-gray-800"><?= htmlspecialchars($memName) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Committees in Charge -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Committees in Charge
                            <span class="text-xs font-normal text-gray-400">(select one or more)</span>
                        </label>
                        <div class="grid grid-cols-1 gap-1 rounded-xl border border-gray-200 bg-white p-3
                                    max-h-40 overflow-y-auto sm:grid-cols-2">
                            <?php if (empty($committees)): ?>
                                <p class="p-2 text-xs text-gray-400 italic">No active Committees found.</p>
                            <?php else: ?>
                                <?php foreach ($committees as $cmt): ?>
                                    <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm
                                                  cursor-pointer hover:bg-gray-50">
                                        <input type="checkbox" name="committee_ids[]"
                                               value="<?= (int) $cmt['id'] ?>"
                                               <?= in_array((int)$cmt['id'], $currentCmtIds, true) ? 'checked' : '' ?>
                                               class="h-4 w-4 rounded border-gray-300 text-primary
                                                      focus:ring-primary">
                                        <span class="text-gray-800"><?= htmlspecialchars($cmt['name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Target First Reading Date + Session Type -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="target_first_reading_date"
                                   class="block text-sm font-medium text-gray-700">
                                Target First Reading Date
                            </label>
                            <input type="date" id="target_first_reading_date"
                                   name="target_first_reading_date"
                                   value="<?= htmlspecialchars($currentTargetDate) ?>"
                                   class="mt-1.5 w-full rounded-xl border border-gray-200 bg-white py-2 px-3
                                          text-sm text-gray-800 focus:border-primary focus:outline-none
                                          focus:ring-2 focus:ring-primary/20">
                        </div>

                        <div>
                            <label for="session_type"
                                   class="block text-sm font-medium text-gray-700">
                                Session Type
                            </label>
                            <select id="session_type" name="session_type"
                                    class="mt-1.5 w-full rounded-xl border border-gray-200 bg-white py-2 px-3
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
                    </div>

                    <!-- Additional Details -->
                    <div>
                        <label for="additional_details" class="block text-sm font-medium text-gray-700">
                            Additional Details
                        </label>
                        <textarea id="additional_details" name="additional_details" rows="3"
                                  class="mt-1.5 w-full rounded-xl border border-gray-200 bg-white py-2 px-3
                                         text-sm text-gray-800 placeholder-gray-400 focus:border-primary
                                         focus:outline-none focus:ring-2 focus:ring-primary/20"
                                  placeholder="Any additional notes or details…"><?= htmlspecialchars($currentAddlDetails) ?></textarea>
                    </div>

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
                            <strong>"Under Processing"</strong>.
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
                    <label for="return_remarks" class="block text-sm font-medium text-red-800">
                        Reason <span class="text-red-500">*</span>
                    </label>
                    <textarea id="return_remarks" name="remarks" rows="2" required
                              class="mt-1.5 w-full rounded-xl border border-red-200 bg-white py-2 px-3 text-sm
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

    <?php endif; /* end $canProcess */ ?>

    <!-- ─────────────────────────────────────────────────────────────────── -->
    <!-- Section 6: Saved Plenary Details (read-only, shown to all owners)  -->
    <!-- ─────────────────────────────────────────────────────────────────── -->
    <?php if (!$canProcess && $plenaryDetails): ?>
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
                                <?= htmlspecialchars($att['original_filename'] ?? $att['file_path'] ?? '') ?>
                            </div>
                            <div class="text-xs text-gray-400">
                                <?= htmlspecialchars($att['uploaded_by_username'] ?? '') ?>
                                — <?= htmlspecialchars(date('M j, Y g:i A', strtotime($att['created_at']))) ?>
                            </div>
                        </div>
                        <?php if (!empty($att['file_path'])): ?>
                            <a href="<?= BASE_URL ?>/<?= htmlspecialchars(ltrim($att['file_path'], '/')) ?>"
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

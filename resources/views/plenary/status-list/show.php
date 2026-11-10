<?php
/**
 * Plenary Finalized Documents — read-only full-history detail view
 *
 * Variables supplied by PlenaryReadingController::statusListShow():
 *   $document             array        Document row + status/type joins
 *   $backTab              string       'legislative' | 'communications'
 *   $plenaryDetails       array|null
 *   $source               array        Source information (joined lookup names)
 *   $authors              array
 *   $coAuthors            array
 *   $docCommittees        array
 *   $events               array        document_events, chronological ASC
 *   $assignments          array        document_assignments, chronological ASC
 *   $attachments          array
 *   $firstReading         array|null
 *   $secondReading        array|null
 *   $secondReadingDetails array|null
 *   $commFirstReading     array|null
 *   $commAdoption         array|null
 *   $resolutionFiles      array
 *   $casesFirstReading    array|null   Petition First Reading row (Petitions tab only)
 *   $casesAdoption        array|null   Adopted Committee Report row (Petitions tab only)
 *   $casesAdoptionFiles   array        Attachment files for adopted committee report
 *   $committeeCase            array|null   Committee case record linked by document_id (Petitions tab only)
 *   $committeeCaseProceedings array        Timeline proceedings from committee_case_actions
 *   $isPetition           bool         true when the document is on the Petitions tab
 *   $pageTitle            string
 *   $success, $error      string|null
 */

$document             = $document             ?? [];
$backTab              = $backTab              ?? 'legislative';
$plenaryDetails       = $plenaryDetails       ?? null;
$source               = $source               ?? [];
$authors              = $authors              ?? [];
$coAuthors            = $coAuthors            ?? [];
$docCommittees        = $docCommittees        ?? [];
$events               = $events               ?? [];
$assignments          = $assignments          ?? [];
$attachments          = $attachments          ?? [];
$firstReading         = $firstReading         ?? null;
$secondReading        = $secondReading        ?? null;
$secondReadingDetails = $secondReadingDetails ?? null;
$commFirstReading     = $commFirstReading     ?? null;
$commAdoption         = $commAdoption         ?? null;
$resolutionFiles      = $resolutionFiles      ?? [];
$casesFirstReading    = $casesFirstReading    ?? null;
$casesAdoption        = $casesAdoption        ?? null;
$casesAdoptionFiles   = $casesAdoptionFiles   ?? [];
$committeeCase            = $committeeCase            ?? null;
$committeeCaseProceedings = $committeeCaseProceedings ?? [];

$isCommTab    = ($backTab === 'communications');
$isPetition   = ($backTab === 'petitions');

function slShowDate(?string $d, string $fmt = 'M j, Y'): string {
    if (empty($d) || str_starts_with((string) $d, '0000')) { return '—'; }
    $ts = strtotime((string) $d);
    return $ts ? date($fmt, $ts) : '—';
}

function slShowDateTime(?string $d): string {
    return slShowDate($d, 'M j, Y g:i A');
}

function slShowTimelineDateTime(?string $d): string {
    if ($d !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
        return slShowDate($d);
    }
    return slShowDateTime($d);
}

function slShowPhase(?string $phase): string {
    return match ($phase) {
        'RECEIVING'    => 'Receiving',
        'ADMIN'        => 'Admin',
        'SP_SECRETARY' => 'SP Secretary',
        'PLENARY'      => 'Plenary',
        'COMMITTEE'    => 'Committee',
        'FINALIZED'    => 'Finalized',
        'FILED'        => 'Filed',
        default        => ($phase !== '' && $phase !== null) ? $phase : '—',
    };
}

function slShowDecision(?string $d): string {
    return match ($d) {
        'PENDING'   => 'Pending',
        'ACCEPTED'  => 'Accepted',
        'DECLINED'  => 'Declined',
        'NOTED'     => 'Noted',
        'REJECTED'  => 'Rejected',
        'COMPLETED' => 'Completed',
        default     => ($d !== '' && $d !== null) ? $d : '—',
    };
}

function slShowEventLabel(?string $et): string {
    return match ($et) {
        'DOCUMENT_RECEIVED'               => 'Document Received',
        'DOCUMENT_EDITED'                 => 'Document Details Updated',
        'ROUTED_TO_ADMIN'                 => 'Routed to Admin',
        'ADMIN_ACCEPTED'                  => 'Admin Accepted',
        'ADMIN_DECLINED'                  => 'Admin Declined',
        'ADMIN_RETURNED_TO_RECEIVING'     => 'Returned to Receiving',
        'ROUTED_TO_SP_SECRETARY'          => 'Routed to SP Secretary',
        'ROUTED_TO_PLENARY'               => 'Routed to Plenary',
        'ROUTED_TO_COMMITTEE'             => 'Routed to Committee',
        'DOCUMENT_NOTED'                  => 'Marked as Noted',
        'SP_SECRETARY_ACCEPTED'           => 'SP Secretary Accepted',
        'SP_SECRETARY_REJECTED'           => 'SP Secretary Rejected',
        'SP_SECRETARY_RETURNED_TO_ADMIN'  => 'SP Secretary Returned to Admin',
        'COMM_SENT_TO_FIRST_READING'      => 'Communication Sent to First Reading',
        'COMMITTEE_ACCEPTED'              => 'Committee Accepted',
        'COMMITTEE_RETURNED_TO_ADMIN'     => 'Returned to Admin by Committee',
        'COMMITTEE_RETURNED_TO_ORIGIN'    => 'Returned to Origin by Committee',
        'COMMITTEE_REPORT_CREATED'        => 'Committee Report Created',
        'COMMITTEE_ENDORSED_REFERRED'     => 'Committee Referred the Document',
        'COMMITTEE_COMMUNICATION_CREATED' => 'Committee Communication Created',
        'COMMITTEE_COMMUNICATION_AGENDA_SCHEDULED' => 'Committee Communication Agenda Scheduled',
        'COMMITTEE_COMMUNICATION_HEARING_RECORDED' => 'Committee Communication Hearing Recorded',
        'COMMITTEE_COMMUNICATION_REPORT_CREATED' => 'Committee Communication Report Created',
        'COMMITTEE_CASE_CREATED'          => 'Committee Case Created',
        'OPINION_REQUESTED'               => 'Opinion Requested',
        'AGENDA_SCHEDULED'                => 'Committee Agenda Scheduled',
        'PLENARY_ACCEPTED'                => 'Plenary Accepted',
        'PLENARY_PRE_DECISION'            => 'Pre-Decision Status Saved',
        'PLENARY_PROCESSING_SAVED'        => 'Plenary Processing Data Saved',
        'PLENARY_COMPLIANCE_SUBMITTED'    => 'Plenary Compliance Submitted',
        'PLENARY_RETURNED_TO_ADMIN'       => 'Plenary Returned to Admin',
        'RETURNED_TO_PLENARY'             => 'Returned to Plenary',
        'SECOND_READING_APPROVED'         => 'Second Reading Approved',
        'DOCUMENT_FINALIZED'              => 'Document Finalized',
        'DOCUMENT_FILED'                  => 'Document Filed',
        'PLENARY_FIRST_READING_COMPLETED' => 'First Reading Completed',
        'PLENARY_REFERRED_TO_COMMITTEE'   => 'Referred to Committee',
        'PLENARY_SECOND_READING_APPROVED' => 'Second Reading Approved',
        'PLENARY_SECOND_READING_DEFERRED' => 'Second Reading Deferred',
        'PLENARY_SECOND_READING_WITHDRAWN' => 'Second Reading Withdrawn',
        'PLENARY_SECOND_READING_REFERRED_TO_COMMITTEE' => 'Referred to Committee (Second Reading)',
        'PLENARY_FINALIZED'               => 'Finalized',
        'PLENARY_CASES_FIRST_READING_NOTED' => 'Petition Noted at First Reading',
        'PLENARY_CASES_FIRST_READING_REFERRED' => 'Petition Referred to Committee',
        'PLENARY_CASES_ADOPTED'           => 'Committee Report Adopted (Petition)',
        'PLENARY_CASES_FINALIZED'         => 'Petition Finalized',
        'PLENARY_COMM_FIRST_READING_NOTED'    => 'Communication Noted (First Reading)',
        'PLENARY_COMM_FIRST_READING_REFERRED' => 'Communication Referred to Committee',
        'PLENARY_COMM_FIRST_READING_APPROVED' => 'Communication Approved for Resolution',
        'PLENARY_COMM_ADOPTED'            => 'Committee Report Adopted',
        'PLENARY_COMM_FINALIZED'          => 'Communication Finalized',
        default                           => ($et !== '' && $et !== null) ? $et : 'Activity',
    };
}

function slShowReadingAction(?string $a): string {
    return match ($a) {
        'REFERRED_TO_COMMITTEE'        => 'Referred to Committee',
        'ADOPTED_SUSPENDED_RULES'      => 'Adopted under Suspended Rules',
        'APPROVED'                     => 'Approved',
        'DEFERRED'                     => 'Deferred',
        'WITHDRAWN'                    => 'Withdrawn',
        'REFERRED_TO_COMMITTEE_SECOND' => 'Referred to Committee',
        default                        => ($a !== '' && $a !== null) ? str_replace('_', ' ', $a) : '—',
    };
}

function slShowCommDecision(?string $d): string {
    return match ($d) {
        'REFER_TO_COMMITTEE' => 'Referred to Committee',
        'APPROVED'           => 'Approved for Resolution',
        'NOTED'              => 'Noted',
        default              => ($d !== '' && $d !== null) ? $d : '—',
    };
}

// ── Build one chronological timeline from workflow events and milestones ─────
$timeline = [];
$acceptedEventPhases = [];
$firstReadingEventId = null;
$secondReadingEventId = null;
$hasSecondReadingApprovalEvent = false;
$hasCommFirstReadingEvent = false;
$hasCasesFirstReadingEvent = false;
$hasCommAdoptionEvent = false;
$hasCasesAdoptionEvent = false;
foreach ($events as $ev) {
    $metadata = json_decode((string) ($ev['metadata'] ?? ''), true);
    $metadata = is_array($metadata) ? $metadata : [];
    $eventType = (string) ($ev['event_type'] ?? '');
    $toStatus = (string) ($ev['to_status_name'] ?? '');

    $hasCommFirstReadingEvent = $hasCommFirstReadingEvent
        || str_starts_with($eventType, 'PLENARY_COMM_FIRST_READING_');
    $hasCasesFirstReadingEvent = $hasCasesFirstReadingEvent
        || str_starts_with($eventType, 'PLENARY_CASES_FIRST_READING_');
    $hasCommAdoptionEvent = $hasCommAdoptionEvent || $eventType === 'PLENARY_COMM_ADOPTED';
    $hasCasesAdoptionEvent = $hasCasesAdoptionEvent || $eventType === 'PLENARY_CASES_ADOPTED';

    if ($firstReading && $eventType === 'PLENARY_FIRST_READING_COMPLETED'
        && (int) ($metadata['reading_id'] ?? 0) === (int) ($firstReading['id'] ?? 0)) {
        $firstReadingEventId = (int) ($ev['id'] ?? 0);
    }
    if ($secondReading && in_array($eventType, [
        'PLENARY_SECOND_READING_APPROVED',
        'PLENARY_SECOND_READING_REFERRED_TO_COMMITTEE',
        'PLENARY_SECOND_READING_DEFERRED',
        'PLENARY_SECOND_READING_WITHDRAWN',
        'SECOND_READING_APPROVED',
    ], true)
        && (int) ($metadata['reading_id'] ?? 0) === (int) ($secondReading['id'] ?? 0)) {
        $secondReadingEventId = (int) ($ev['id'] ?? 0);
    }
    if (in_array($eventType, ['PLENARY_SECOND_READING_APPROVED', 'SECOND_READING_APPROVED'], true)
        && $toStatus === 'Second Reading Approved') {
        $hasSecondReadingApprovalEvent = true;
    }
}
foreach ($events as $ev) {
    $eventType = $ev['event_type'] ?? '';
    $eventId = (int) ($ev['id'] ?? 0);
    $toStatus = (string) ($ev['to_status_name'] ?? '');

    if (in_array($eventType, ['PLENARY_FINALIZED', 'DOCUMENT_FINALIZED'], true)
        && $toStatus === 'Second Reading Approved'
        && $hasSecondReadingApprovalEvent) {
        continue;
    }

    $eventTitle = slShowEventLabel($eventType);
    $eventStatus = $toStatus;
    if ($eventType === 'PLENARY_FIRST_READING_COMPLETED' && $firstReading
        && $eventId === $firstReadingEventId) {
        $eventTitle = 'First Reading — ' . slShowReadingAction($firstReading['action'] ?? '');
    } elseif ($eventType === 'PLENARY_SECOND_READING_APPROVED' && $toStatus === 'Awaiting Second Reading') {
        $eventTitle = 'Advanced to Second Reading';
    } elseif (in_array($eventType, ['PLENARY_SECOND_READING_APPROVED', 'SECOND_READING_APPROVED'], true)
        && $toStatus === 'Second Reading Approved' && $secondReading) {
        $eventTitle = 'Second Reading — ' . slShowReadingAction($secondReading['action'] ?? 'APPROVED');
        $eventStatus = '';
    } elseif ($eventType === 'PLENARY_FINALIZED' && $toStatus === 'Second Reading Approved') {
        $eventTitle = 'Second Reading — ' . slShowReadingAction($secondReading['action'] ?? 'APPROVED');
        $eventStatus = '';
    }
    $timeline[] = [
        'ts'     => $ev['created_at'] ?? '',
        'title'  => $eventTitle,
        'status' => $eventStatus,
        'phase'  => slShowPhase($ev['phase'] ?? ''),
        'actor'  => $ev['performed_by_username'] ?? '',
        'detail' => $ev['remarks'] ?? '',
        'tone'   => 'indigo',
    ];
    if (strpos(strtoupper((string) ($ev['event_type'] ?? '')), 'ACCEPTED') !== false) {
        $acceptedEventPhases[(string) ($ev['phase'] ?? '')] = true;
    }
}
foreach ($assignments as $assignment) {
    $phase = (string) ($assignment['phase'] ?? '');
    $role = (string) ($assignment['role_name'] ?? slShowPhase($phase));
    if (!empty($assignment['received_at'])) {
        $timeline[] = [
            'ts'     => $assignment['received_at'],
            'title'  => 'Received by ' . $role,
            'status' => '',
            'phase'  => slShowPhase($phase),
            'actor'  => '',
            'detail' => '',
            'tone'   => 'indigo',
        ];
    }
    if (!empty($assignment['accepted_at']) && empty($acceptedEventPhases[$phase])) {
        $timeline[] = [
            'ts'     => $assignment['accepted_at'],
            'title'  => 'Accepted by ' . $role,
            'status' => '',
            'phase'  => slShowPhase($phase),
            'actor'  => $assignment['accepted_by_username'] ?? '',
            'detail' => $assignment['remarks'] ?? '',
            'tone'   => 'indigo',
        ];
        $acceptedEventPhases[$phase] = true;
    }
}
if (!empty($document['date_received']) && !array_filter(
    $events,
    static fn(array $event): bool => ($event['event_type'] ?? '') === 'DOCUMENT_RECEIVED'
)) {
    $timeline[] = [
        'ts'     => $document['date_received'],
        'title'  => 'Document Received',
        'status' => '',
        'phase'  => 'Receiving',
        'actor'  => '',
        'detail' => '',
        'tone'   => 'indigo',
    ];
}
if ($firstReading && $firstReadingEventId === null) {
    $timeline[] = [
        'ts'     => $firstReading['reading_date'] ?? '',
        'title'  => 'First Reading — ' . slShowReadingAction($firstReading['action'] ?? ''),
        'status' => '', 'phase' => 'Plenary',
        'actor'  => $firstReading['performed_by_username'] ?? ($firstReading['performed_by_name'] ?? ''),
        'detail' => $firstReading['remarks'] ?? '', 'tone' => 'blue',
    ];
}
if ($commFirstReading && !$hasCommFirstReadingEvent) {
    $timeline[] = [
        'ts'     => $commFirstReading['reading_date'] ?? '',
        'title'  => 'Plenary First Reading — ' . slShowCommDecision($commFirstReading['decision'] ?? ''),
        'status' => '', 'phase' => 'Plenary',
        'actor'  => $commFirstReading['performed_by_username'] ?? '',
        'detail' => $commFirstReading['remarks'] ?? '', 'tone' => 'blue',
    ];
}
if ($secondReading && $secondReadingEventId === null && !$hasSecondReadingApprovalEvent) {
    $timeline[] = [
        'ts'     => $secondReading['reading_date'] ?? '',
        'title'  => 'Second Reading — ' . slShowReadingAction($secondReading['action'] ?? ''),
        'status' => '', 'phase' => 'Plenary',
        'actor'  => $secondReading['performed_by_username'] ?? ($secondReading['performed_by_name'] ?? ''),
        'detail' => $secondReading['remarks'] ?? '', 'tone' => 'blue',
    ];
}
if ($commAdoption && !$hasCommAdoptionEvent) {
    $timeline[] = [
        'ts'     => $commAdoption['adopted_at'] ?? '',
        'title'  => 'Committee Report Adopted',
        'status' => '', 'phase' => 'Plenary',
        'actor'  => $commAdoption['adopted_by_username'] ?? '',
        'detail' => $commAdoption['remarks'] ?? '', 'tone' => 'emerald',
    ];
}
if ($casesFirstReading && !$hasCasesFirstReadingEvent) {
    $casesFrDecision = $casesFirstReading['decision'] ?? '';
    $casesFrLabel    = match ($casesFrDecision) {
        'REFER_TO_COMMITTEE' => 'Referred to Committee',
        'NOTED'              => 'Noted',
        default              => $casesFrDecision,
    };
    $timeline[] = [
        'ts'     => $casesFirstReading['reading_date'] ?? '',
        'title'  => 'Plenary First Reading — ' . $casesFrLabel,
        'status' => '', 'phase' => 'Plenary',
        'actor'  => $casesFirstReading['performed_by_username'] ?? '',
        'detail' => $casesFirstReading['remarks'] ?? '', 'tone' => 'blue',
    ];
}
if ($casesAdoption && !$hasCasesAdoptionEvent) {
    $timeline[] = [
        'ts'     => $casesAdoption['adopted_at'] ?? '',
        'title'  => 'Adopted Committee Report (Petition)',
        'status' => '', 'phase' => 'Plenary',
        'actor'  => $casesAdoption['adopted_by_username'] ?? '',
        'detail' => $casesAdoption['remarks'] ?? '', 'tone' => 'emerald',
    ];
}
usort($timeline, static fn($a, $b) => strcmp((string) $a['ts'], (string) $b['ts']));

ob_start();
?>

<div class="space-y-6">

    <?php if (!empty($success)): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Back + header -->
    <div class="flex items-center gap-4">
        <a href="<?= BASE_URL ?>/plenary/status-list?tab=<?= htmlspecialchars($backTab) ?>"
           class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-gray-200
                  bg-white text-gray-500 hover:bg-gray-50 transition">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Finalized Documents</p>
            <h1 class="truncate text-xl font-bold text-gray-900"><?= htmlspecialchars($document['tracking_number'] ?? '') ?></h1>
        </div>
        <div class="ml-auto">
            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold"
                  style="background-color: <?= htmlspecialchars($document['status_badge_color'] ?? '#6B7280') ?>1a;
                         color: <?= htmlspecialchars($document['status_badge_color'] ?? '#6B7280') ?>;">
                <?= htmlspecialchars($document['status_name'] ?? 'Finalized') ?>
            </span>
        </div>
    </div>

    <!-- Document details -->
    <section class="rounded-2xl border border-gray-200 bg-white p-6">
        <h2 class="mb-4 text-base font-semibold text-gray-900">Document Details</h2>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Tracking Number</dt>
                <dd class="mt-1 text-sm font-medium text-gray-900"><?= htmlspecialchars($document['tracking_number'] ?? '—') ?></dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Document Type</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    <?php if (!empty($document['document_type_name'])): ?>
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
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
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Date Received</dt>
                <dd class="mt-1 text-sm text-gray-900"><?= slShowDate($document['date_received'] ?? null) ?></dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Current Phase</dt>
                <dd class="mt-1 text-sm text-gray-900"><?= htmlspecialchars(slShowPhase($document['current_phase'] ?? '')) ?></dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Finalized At</dt>
                <dd class="mt-1 text-sm font-medium text-emerald-700"><?= slShowDateTime($document['finalized_at'] ?? null) ?></dd>
            </div>
            <?php if ($plenaryDetails): ?>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Proposed Number</dt>
                    <dd class="mt-1 font-mono text-sm text-gray-900">
                        <?= !empty($plenaryDetails['proposed_number'])
                            ? htmlspecialchars($plenaryDetails['proposed_number'])
                            : '<span class="font-sans italic text-gray-400">—</span>' ?>
                    </dd>
                </div>
            <?php endif; ?>
        </dl>
        <?php if (!empty($document['subject_matter'])): ?>
            <div class="mt-5 border-t border-gray-100 pt-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                    <?= $isCommTab && !empty($commFirstReading['approved_subject_matter'])
                        && $commFirstReading['approved_subject_matter'] !== $document['subject_matter']
                        ? 'Original Subject Matter'
                        : 'Subject Matter' ?>
                </dt>
                <dd class="mt-1 whitespace-pre-line text-sm text-gray-800"><?= htmlspecialchars($document['subject_matter']) ?></dd>
            </div>
        <?php endif; ?>
        <?php if (!empty($document['remarks'])): ?>
            <div class="mt-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Remarks</dt>
                <dd class="mt-1 whitespace-pre-line text-sm text-gray-700"><?= htmlspecialchars($document['remarks']) ?></dd>
            </div>
        <?php endif; ?>
    </section>

    <!-- Source information -->
    <?php if (!empty(array_filter($source))) : ?>
        <section class="rounded-2xl border border-gray-200 bg-white p-6">
            <h2 class="mb-4 text-base font-semibold text-gray-900">Source Information</h2>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Source Type</dt>
                    <dd class="mt-1 text-sm text-gray-900"><?= !empty($source['source_type_name']) ? htmlspecialchars($source['source_type_name']) : '<span class="italic text-gray-400">—</span>' ?></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Provincial Government Offices</dt>
                    <dd class="mt-1 text-sm text-gray-900"><?= !empty($source['external_office_name']) ? htmlspecialchars($source['external_office_name']) : '<span class="italic text-gray-400">—</span>' ?></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Hospital</dt>
                    <dd class="mt-1 text-sm text-gray-900"><?= !empty($source['hospital_name']) ? htmlspecialchars($source['hospital_name']) : '<span class="italic text-gray-400">—</span>' ?></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Municipality / City</dt>
                    <dd class="mt-1 text-sm text-gray-900"><?= !empty($source['municity_name']) ? htmlspecialchars($source['municity_name']) : '<span class="italic text-gray-400">—</span>' ?></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Source Name</dt>
                    <dd class="mt-1 text-sm text-gray-900"><?= !empty($source['source_name']) ? htmlspecialchars($source['source_name']) : '<span class="italic text-gray-400">—</span>' ?></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Contact Number</dt>
                    <dd class="mt-1 text-sm text-gray-900"><?= !empty($source['source_contact_number']) ? htmlspecialchars($source['source_contact_number']) : '<span class="italic text-gray-400">—</span>' ?></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Liaison</dt>
                    <dd class="mt-1 text-sm text-gray-900"><?= !empty($source['source_liaison_name']) ? htmlspecialchars($source['source_liaison_name']) : '<span class="italic text-gray-400">—</span>' ?></dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Address</dt>
                    <dd class="mt-1 text-sm text-gray-900"><?= !empty($source['source_address']) ? htmlspecialchars($source['source_address']) : '<span class="italic text-gray-400">—</span>' ?></dd>
                </div>
            </dl>
        </section>
    <?php endif; ?>

    <!-- Authors / Committees -->
    <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-gray-200 bg-white p-6">
            <h2 class="mb-4 text-base font-semibold text-gray-900">Authors</h2>
            <?php if (empty($authors) && empty($coAuthors)): ?>
                <p class="text-sm italic text-gray-400">No authors recorded.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php if (!empty($authors)): ?>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Author(s)</p>
                            <?php if (!empty($authors[0]['is_all_selected'])): ?>
                                <p class="mt-1 text-sm text-gray-800">ALL</p>
                            <?php else: ?>
                                <ol class="mt-1 list-decimal space-y-1 pl-5">
                                    <?php foreach ($authors as $a): ?>
                                        <li class="text-sm text-gray-800"><?= htmlspecialchars($a['full_name'] ?? '') ?></li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($coAuthors)): ?>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Co-Author(s)</p>
                            <?php if (!empty($coAuthors[0]['is_all_selected'])): ?>
                                <p class="mt-1 text-sm text-gray-800">ALL</p>
                            <?php else: ?>
                                <ol class="mt-1 list-decimal space-y-1 pl-5">
                                    <?php foreach ($coAuthors as $a): ?>
                                        <li class="text-sm text-gray-800"><?= htmlspecialchars($a['full_name'] ?? '') ?></li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-6">
            <h2 class="mb-4 text-base font-semibold text-gray-900">Referred Committees</h2>
            <?php if (empty($docCommittees)): ?>
                <p class="text-sm italic text-gray-400">No committees involved.</p>
            <?php else: ?>
                <ul class="space-y-2">
                    <?php foreach ($docCommittees as $c): ?>
                        <li class="flex items-center gap-2 text-sm text-gray-800">
                            <span class="h-1.5 w-1.5 rounded-full bg-primary"></span>
                            <?= htmlspecialchars($c['committee_name'] ?? '') ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>

    <!-- Legislative readings -->
    <?php if ($firstReading || $secondReading || $secondReadingDetails): ?>
        <section class="rounded-2xl border border-gray-200 bg-white p-6">
            <h2 class="mb-4 text-base font-semibold text-gray-900">Reading Details</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-gray-100 bg-gray-50/60 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">First Reading</p>
                    <?php if ($firstReading): ?>
                        <p class="mt-1 text-sm font-medium text-gray-900"><?= htmlspecialchars(slShowReadingAction($firstReading['action'] ?? '')) ?></p>
                        <p class="mt-0.5 text-xs text-gray-500"><?= slShowDate($firstReading['reading_date'] ?? null) ?></p>
                        <?php if (!empty($firstReading['remarks'])): ?>
                            <p class="mt-1 text-xs italic text-gray-500">"<?= htmlspecialchars($firstReading['remarks']) ?>"</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="mt-1 text-sm italic text-gray-400">No record.</p>
                    <?php endif; ?>
                </div>
                <div class="rounded-xl border border-gray-100 bg-gray-50/60 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Second Reading</p>
                    <?php if ($secondReading): ?>
                        <p class="mt-1 text-sm font-medium text-gray-900"><?= htmlspecialchars(slShowReadingAction($secondReading['action'] ?? '')) ?></p>
                        <p class="mt-0.5 text-xs text-gray-500"><?= slShowDate($secondReading['reading_date'] ?? null) ?></p>
                        <?php if ($secondReadingDetails): ?>
                            <dl class="mt-2 space-y-1 text-xs text-gray-600">
                                <?php if (!empty($secondReadingDetails['approved_no'])): ?>
                                    <div><span class="text-gray-400">Approved No:</span> <span class="font-semibold text-primary"><?= htmlspecialchars($secondReadingDetails['approved_no']) ?></span></div>
                                <?php endif; ?>
                                <?php if (!empty($secondReadingDetails['date_finalized'])): ?>
                                    <div><span class="text-gray-400">Date Approved:</span> <?= slShowDate($secondReadingDetails['date_finalized']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($secondReadingDetails['approved_subject_matter'])): ?>
                                    <div><span class="text-gray-400">Approved Subject:</span> <?= htmlspecialchars($secondReadingDetails['approved_subject_matter']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($secondReadingDetails['approval_files'])): ?>
                                    <div class="pt-1">
                                        <span class="text-gray-400">Approval Attachments:</span>
                                        <ul class="mt-1 space-y-1">
                                            <?php foreach ($secondReadingDetails['approval_files'] as $file): ?>
                                                <li>
                                                    <a href="<?= BASE_URL ?>/<?= htmlspecialchars(ltrim($file['file_path'], '/')) ?>"
                                                       target="_blank" rel="noopener noreferrer"
                                                       class="text-primary underline">
                                                        <?= htmlspecialchars($file['original_filename']) ?>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($secondReadingDetails['date_deferred'])): ?>
                                    <div><span class="text-gray-400">Deferred:</span> <?= slShowDate($secondReadingDetails['date_deferred']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($secondReadingDetails['date_withdrawn'])): ?>
                                    <div><span class="text-gray-400">Withdrawn:</span> <?= slShowDate($secondReadingDetails['date_withdrawn']) ?></div>
                                <?php endif; ?>
                            </dl>
                        <?php endif; ?>
                        <?php if (!empty($secondReading['remarks'])): ?>
                            <p class="mt-1 text-xs italic text-gray-500">"<?= htmlspecialchars($secondReading['remarks']) ?>"</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="mt-1 text-sm italic text-gray-400">No record.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- Communications processing -->
    <?php if ($isCommTab && ($commFirstReading || $commAdoption || !empty($resolutionFiles))): ?>
        <section class="rounded-2xl border border-gray-200 bg-white p-6">
            <h2 class="mb-4 text-base font-semibold text-gray-900">Communication Processing</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-gray-100 bg-gray-50/60 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Plenary First Reading</p>
                    <?php if ($commFirstReading): ?>
                        <p class="mt-1 text-sm font-medium text-gray-900"><?= htmlspecialchars(slShowCommDecision($commFirstReading['decision'] ?? '')) ?></p>
                        <p class="mt-0.5 text-xs text-gray-500"><?= slShowDate($commFirstReading['reading_date'] ?? null) ?></p>
                        <dl class="mt-2 space-y-1 text-xs text-gray-600">
                            <?php if (!empty($commFirstReading['communication_category_name'])): ?>
                                <div><span class="text-gray-400">Category:</span> <?= htmlspecialchars($commFirstReading['communication_category_name']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($commFirstReading['approved_subject_matter'])): ?>
                                <div>
                                    <span class="text-gray-400">Approved Subject Matter:</span>
                                    <p class="mt-0.5 whitespace-pre-line text-gray-700"><?= htmlspecialchars($commFirstReading['approved_subject_matter']) ?></p>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($commFirstReading['resolution_number'])): ?>
                                <div><span class="text-gray-400">Resolution No:</span> <span class="font-semibold text-primary"><?= htmlspecialchars($commFirstReading['resolution_number']) ?></span></div>
                            <?php endif; ?>
                            <?php if (!empty($commFirstReading['proposed_number'])): ?>
                                <div><span class="text-gray-400">Proposed No:</span> <?= htmlspecialchars($commFirstReading['proposed_number']) ?></div>
                            <?php endif; ?>
                        </dl>
                        <?php if (!empty($commFirstReading['remarks'])): ?>
                            <p class="mt-1 text-xs italic text-gray-500">"<?= htmlspecialchars($commFirstReading['remarks']) ?>"</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="mt-1 text-sm italic text-gray-400">No record.</p>
                    <?php endif; ?>
                </div>
                <div class="rounded-xl border border-gray-100 bg-gray-50/60 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Adopted Committee Report</p>
                    <?php if ($commAdoption): ?>
                        <p class="mt-0.5 text-xs text-gray-500"><?= slShowDateTime($commAdoption['adopted_at'] ?? null) ?></p>
                        <dl class="mt-2 space-y-1 text-xs text-gray-600">
                            <div><span class="text-gray-400">Resolution No:</span> <span class="font-semibold text-primary"><?= htmlspecialchars($commAdoption['resolution_number'] ?? '—') ?></span></div>
                            <?php if (!empty($commAdoption['proposed_number'])): ?>
                                <div><span class="text-gray-400">Proposed No:</span> <?= htmlspecialchars($commAdoption['proposed_number']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($commAdoption['approved_subject_matter'])): ?>
                                <div class="sm:col-span-2">
                                    <span class="text-gray-400">Approved Subject Matter:</span>
                                    <p class="mt-0.5 whitespace-pre-line text-gray-700"><?= htmlspecialchars($commAdoption['approved_subject_matter']) ?></p>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($commAdoption['adopted_by_username'])): ?>
                                <div><span class="text-gray-400">Adopted By:</span> <?= htmlspecialchars($commAdoption['adopted_by_username']) ?></div>
                            <?php endif; ?>
                        </dl>
                        <?php if (!empty($commAdoption['remarks'])): ?>
                            <p class="mt-1 text-xs italic text-gray-500">"<?= htmlspecialchars($commAdoption['remarks']) ?>"</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="mt-1 text-sm italic text-gray-400">Not applicable.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty($resolutionFiles)): ?>
                <div class="mt-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Resolution Files</p>
                    <ul class="mt-2 divide-y divide-gray-100">
                        <?php foreach ($resolutionFiles as $rf): ?>
                            <li class="flex items-center gap-3 py-2">
                                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                </svg>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm text-gray-800"><?= htmlspecialchars($rf['original_filename'] ?? '') ?></div>
                                    <div class="text-xs text-gray-400"><?= slShowDateTime($rf['uploaded_at'] ?? null) ?></div>
                                </div>
                                <?php if (!empty($rf['file_path'])): ?>
                                    <a href="<?= BASE_URL ?>/<?= htmlspecialchars(ltrim($rf['file_path'], '/')) ?>"
                                       target="_blank" rel="noopener noreferrer"
                                       class="shrink-0 rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50 transition">View</a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <!-- Petitions processing (Adopted Committee Report flow) -->
    <?php if ($isPetition && ($casesFirstReading || $casesAdoption || !empty($casesAdoptionFiles))): ?>
        <section class="rounded-2xl border border-gray-200 bg-white p-6">
            <h2 class="mb-4 text-base font-semibold text-gray-900">Petitions Processing</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <!-- First Reading -->
                <div class="rounded-xl border border-gray-100 bg-gray-50/60 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Plenary First Reading</p>
                    <?php if ($casesFirstReading): ?>
                        <?php
                            $frDec = $casesFirstReading['decision'] ?? '';
                            $frDecLabel = match ($frDec) {
                                'REFER_TO_COMMITTEE' => 'Referred to Committee',
                                'NOTED'              => 'Noted',
                                default              => $frDec ?: '—',
                            };
                        ?>
                        <p class="mt-1 text-sm font-medium text-gray-900"><?= htmlspecialchars($frDecLabel) ?></p>
                        <p class="mt-0.5 text-xs text-gray-500"><?= slShowDate($casesFirstReading['reading_date'] ?? null) ?></p>
                        <?php if (!empty($casesFirstReading['performed_by_username'])): ?>
                            <p class="mt-0.5 text-xs text-gray-500">By: <?= htmlspecialchars($casesFirstReading['performed_by_username']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($casesFirstReading['remarks'])): ?>
                            <p class="mt-1 text-xs italic text-gray-500">"<?= htmlspecialchars($casesFirstReading['remarks']) ?>"</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="mt-1 text-sm italic text-gray-400">No record.</p>
                    <?php endif; ?>
                </div>
                <!-- Adopted Committee Report -->
                <div class="rounded-xl border border-gray-100 bg-gray-50/60 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Adopted Committee Report</p>
                    <?php if ($casesAdoption): ?>
                        <p class="mt-0.5 text-xs text-gray-500"><?= slShowDateTime($casesAdoption['adopted_at'] ?? null) ?></p>
                        <dl class="mt-2 space-y-1 text-xs text-gray-600">
                            <div>
                                <span class="text-gray-400">Resolution No:</span>
                                <span class="font-semibold text-primary"><?= htmlspecialchars($casesAdoption['resolution_number'] ?? '—') ?></span>
                            </div>
                            <?php if (!empty($casesAdoption['proposed_number'])): ?>
                                <div>
                                    <span class="text-gray-400">Proposed No:</span>
                                    <?= htmlspecialchars($casesAdoption['proposed_number']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($casesAdoption['approved_subject_matter'])): ?>
                                <div>
                                    <span class="text-gray-400">Approved Subject Matter:</span>
                                    <p class="mt-0.5 whitespace-pre-line text-gray-700"><?= htmlspecialchars($casesAdoption['approved_subject_matter']) ?></p>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($casesAdoption['adopted_by_username'])): ?>
                                <div>
                                    <span class="text-gray-400">Adopted By:</span>
                                    <?= htmlspecialchars($casesAdoption['adopted_by_username']) ?>
                                </div>
                            <?php endif; ?>
                        </dl>
                        <?php if (!empty($casesAdoption['remarks'])): ?>
                            <p class="mt-1 text-xs italic text-gray-500">"<?= htmlspecialchars($casesAdoption['remarks']) ?>"</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="mt-1 text-sm italic text-gray-400">Not applicable.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty($casesAdoptionFiles)): ?>
                <div class="mt-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                        Committee Report Attachments
                        <span class="ml-1 font-normal text-gray-400">(<?= count($casesAdoptionFiles) ?>)</span>
                    </p>
                    <ul class="mt-2 divide-y divide-gray-100">
                        <?php foreach ($casesAdoptionFiles as $af): ?>
                            <li class="flex items-center gap-3 py-2">
                                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                </svg>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm text-gray-800"><?= htmlspecialchars($af['original_filename'] ?? '') ?></div>
                                    <div class="text-xs text-gray-400"><?= slShowDateTime($af['uploaded_at'] ?? null) ?></div>
                                </div>
                                <?php if (!empty($af['file_path'])): ?>
                                    <a href="<?= BASE_URL ?>/<?= htmlspecialchars(ltrim($af['file_path'], '/')) ?>"
                                       target="_blank" rel="noopener noreferrer"
                                       class="shrink-0 rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50 transition">View</a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <!-- Committee Case Details (Petitions tab only) -->
    <?php if ($isPetition && $committeeCase): ?>
        <section class="rounded-2xl border border-indigo-100 bg-white p-6">
            <h2 class="mb-4 text-base font-semibold text-gray-900">Committee Case Details</h2>

            <!-- Core case identifiers -->
            <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Docket Number</dt>
                    <dd class="mt-1 font-mono text-sm font-semibold text-indigo-700">
                        <?= !empty($committeeCase['docket_number'])
                            ? htmlspecialchars($committeeCase['docket_number'])
                            : '<span class="font-sans font-normal italic text-gray-400">Not assigned</span>' ?>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Date Assigned</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        <?= !empty($committeeCase['date_assigned'])
                            ? htmlspecialchars(date('F j, Y', strtotime($committeeCase['date_assigned'])))
                            : '<span class="italic text-gray-400">—</span>' ?>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Municipality / City</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        <?= !empty($committeeCase['complainant_municipality'])
                            ? htmlspecialchars($committeeCase['complainant_municipality'])
                            : '<span class="italic text-gray-400">—</span>' ?>
                    </dd>
                </div>
                <?php if (!empty($committeeCase['final_outcome'])): ?>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Final Outcome</dt>
                    <dd class="mt-1">
                        <?php
                            $outcomeColor = match ($committeeCase['final_outcome']) {
                                'APPROVED'  => ['bg' => '#059669', 'label' => 'Approved'],
                                'DEFERRED'  => ['bg' => '#D97706', 'label' => 'Deferred'],
                                'WITHDRAWN' => ['bg' => '#6B7280', 'label' => 'Withdrawn'],
                                'NOTED'     => ['bg' => '#0891B2', 'label' => 'Noted'],
                                default     => ['bg' => '#6B7280', 'label' => htmlspecialchars($committeeCase['final_outcome'])],
                            };
                        ?>
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                              style="background-color: <?= $outcomeColor['bg'] ?>1a; color: <?= $outcomeColor['bg'] ?>;">
                            <?= $outcomeColor['label'] ?>
                        </span>
                    </dd>
                </div>
                <?php endif; ?>
                <?php if (!empty($committeeCase['finalized_at'])): ?>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Case Finalized At</dt>
                    <dd class="mt-1 text-sm text-gray-900"><?= slShowDateTime($committeeCase['finalized_at']) ?></dd>
                </div>
                <?php endif; ?>
                <?php if (!empty($committeeCase['finalized_by_username'])): ?>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Finalized By</dt>
                    <dd class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($committeeCase['finalized_by_username']) ?></dd>
                </div>
                <?php endif; ?>
            </dl>

            <!-- Nature of Case -->
            <?php if (!empty($committeeCase['nature_of_case'])): ?>
            <div class="mt-5 border-t border-gray-100 pt-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Nature of Case</dt>
                <dd class="mt-1 whitespace-pre-line text-sm text-gray-800"><?= htmlspecialchars($committeeCase['nature_of_case']) ?></dd>
            </div>
            <?php endif; ?>

            <!-- Complainant Details -->
            <?php if (!empty($committeeCase['complainant_details'])): ?>
            <div class="mt-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Complainant</dt>
                <dd class="mt-1 whitespace-pre-line text-sm text-gray-800"><?= htmlspecialchars($committeeCase['complainant_details']) ?></dd>
            </div>
            <?php endif; ?>

            <!-- Respondents -->
            <?php if (!empty($committeeCase['respondents'])): ?>
            <div class="mt-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Respondents</dt>
                <dd class="mt-1 whitespace-pre-line text-sm text-gray-800"><?= htmlspecialchars($committeeCase['respondents']) ?></dd>
            </div>
            <?php endif; ?>

            <!-- Final Outcome Remarks -->
            <?php if (!empty($committeeCase['final_outcome_remarks'])): ?>
            <div class="mt-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Outcome Remarks</dt>
                <dd class="mt-1 whitespace-pre-line text-sm italic text-gray-600"><?= htmlspecialchars($committeeCase['final_outcome_remarks']) ?></dd>
            </div>
            <?php endif; ?>

            <!-- Proceedings / Timeline Actions -->
            <?php if (!empty($committeeCaseProceedings)): ?>
            <div class="mt-5 border-t border-gray-100 pt-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                    Proceedings
                    <span class="ml-1 font-normal text-gray-400">(<?= count($committeeCaseProceedings) ?> entr<?= count($committeeCaseProceedings) !== 1 ? 'ies' : 'y' ?>)</span>
                </p>
                <ol class="relative mt-3 space-y-4 border-l border-indigo-100 pl-6">
                    <?php foreach ($committeeCaseProceedings as $proc): ?>
                        <li class="relative">
                            <span class="absolute -left-[29px] top-1 h-3 w-3 rounded-full border-2 border-white bg-indigo-400"></span>
                            <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($proc['action_type'] ?? '—') ?></p>
                            <p class="text-xs text-gray-400">
                                <?= slShowDate($proc['action_date'] ?? null) ?>
                                <?php if (!empty($proc['created_by_username'])): ?> — <?= htmlspecialchars($proc['created_by_username']) ?><?php endif; ?>
                                <?php if ((int) ($proc['attachment_count'] ?? 0) > 0): ?>
                                    <span class="ml-2 inline-flex items-center rounded-full bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500">
                                        <?= (int) $proc['attachment_count'] ?> file<?= (int) $proc['attachment_count'] !== 1 ? 's' : '' ?>
                                    </span>
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($proc['description'])): ?>
                                <p class="mt-0.5 text-sm text-gray-700"><?= htmlspecialchars($proc['description']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($proc['notes'])): ?>
                                <p class="mt-0.5 text-xs italic text-gray-500"><?= htmlspecialchars($proc['notes']) ?></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <!-- Chronological history -->
    <section class="rounded-2xl border border-gray-200 bg-white p-6">
        <h2 class="mb-4 text-base font-semibold text-gray-900">
            Document Timeline
            <span class="ml-1 text-xs font-normal text-gray-400">(<?= count($timeline) ?> event<?= count($timeline) !== 1 ? 's' : '' ?>)</span>
        </h2>
        <p class="-mt-2 mb-4 text-xs text-gray-500">Chronological record from document receipt through approval and finalization.</p>
        <?php if (empty($timeline)): ?>
            <p class="text-sm italic text-gray-400">No history recorded for this document.</p>
        <?php else: ?>
            <ol class="relative space-y-5 border-l border-gray-200 pl-6">
                <?php foreach ($timeline as $item): ?>
                    <li class="relative">
                        <span class="absolute -left-[31px] top-1 h-3 w-3 rounded-full border-2 border-white
                                     <?= $item['tone'] === 'emerald' ? 'bg-emerald-500' : ($item['tone'] === 'blue' ? 'bg-blue-500' : 'bg-indigo-500') ?>"></span>
                        <p class="text-sm font-medium text-gray-800">
                            <?= htmlspecialchars($item['title']) ?>
                            <?php if (!empty($item['status'])): ?>
                                <span class="text-xs font-normal text-gray-500">→ <?= htmlspecialchars($item['status']) ?></span>
                            <?php endif; ?>
                        </p>
                        <p class="text-xs text-gray-400">
                            <?= slShowTimelineDateTime($item['ts'] ?: null) ?>
                            <?php if (!empty($item['actor'])): ?> — <?= htmlspecialchars($item['actor']) ?><?php endif; ?>
                            <?php if (!empty($item['phase'])): ?> — <?= htmlspecialchars($item['phase']) ?><?php endif; ?>
                        </p>
                        <?php if (!empty($item['detail'])): ?>
                            <p class="mt-0.5 text-xs italic text-gray-500">"<?= htmlspecialchars($item['detail']) ?>"</p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

    <!-- Assignment history -->
    <?php if (!empty($assignments)): ?>
        <section class="rounded-2xl border border-gray-200 bg-white p-6">
            <h2 class="mb-4 text-base font-semibold text-gray-900">Assignment History</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-2 font-medium">Phase</th>
                            <th class="px-4 py-2 font-medium">Role</th>
                            <th class="px-4 py-2 font-medium">Decision</th>
                            <th class="px-4 py-2 font-medium">Accepted By</th>
                            <th class="px-4 py-2 font-medium">Accepted At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($assignments as $asgn): ?>
                            <tr>
                                <td class="px-4 py-2"><?= htmlspecialchars(slShowPhase($asgn['phase'] ?? '')) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($asgn['role_name'] ?? '—') ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars(slShowDecision($asgn['decision'] ?? null)) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($asgn['accepted_by_username'] ?? '—') ?></td>
                                <td class="px-4 py-2"><?= slShowDateTime($asgn['accepted_at'] ?? null) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <!-- Attachments -->
    <?php if (!empty($attachments)): ?>
        <section class="rounded-2xl border border-gray-200 bg-white p-6">
            <h2 class="mb-4 text-base font-semibold text-gray-900">
                Attachments <span class="ml-1 text-xs font-normal text-gray-400">(<?= count($attachments) ?>)</span>
            </h2>
            <ul class="divide-y divide-gray-100">
                <?php foreach ($attachments as $att): ?>
                    <li class="flex items-center gap-3 py-3">
                        <svg class="h-5 w-5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                        </svg>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium text-gray-800"><?= htmlspecialchars($att['file_name'] ?? '') ?></div>
                            <div class="text-xs text-gray-400">
                                <?= htmlspecialchars($att['uploaded_by_username'] ?? '') ?>
                                — <?= slShowDateTime($att['created_at'] ?? null) ?>
                                <?php if (!empty($att['attachment_type'])): ?>
                                    — <span class="font-medium"><?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $att['attachment_type'])))) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($att['stored_path'])): ?>
                            <a href="<?= BASE_URL ?>/public/<?= htmlspecialchars(ltrim($att['stored_path'], '/')) ?>"
                               target="_blank" rel="noopener"
                               class="shrink-0 rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50 transition">Download</a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

</div>

<?php
// MFR relationship links — Finalized Document detail (status-list)
$mfrDetailRoute = 'plenary/status-list';
require __DIR__ . '/../../partials/mfr-relationship.php';
?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

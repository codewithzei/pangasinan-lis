<?php
ob_start();

$document    = $document    ?? [];
$attachments = $attachments ?? [];
$routes      = $routes      ?? [];
$assignments = $assignments ?? [];
$events      = $events      ?? [];
$pageTitle   = $pageTitle   ?? 'Communication Details';
$pageSubtitle = $pageSubtitle ?? '';

// ── Helpers ───────────────────────────────────────────────────────────────────

if (!function_exists('admin_comm_formatPhaseName')) {
    function admin_comm_formatPhaseName(string $phase): string
    {
        return [
            'RECEIVING'    => 'Receiving',
            'ADMIN'        => 'Admin',
            'SP_SECRETARY' => 'SP Secretary',
            'PLENARY'      => 'Plenary',
            'COMMITTEE'    => 'Committee',
            'FINALIZED'    => 'Finalized',
            'FILED'        => 'Filed',
        ][$phase] ?? $phase;
    }
}

if (!function_exists('admin_comm_formatFileSize')) {
    function admin_comm_formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024)    return number_format($bytes / 1024, 2) . ' KB';
        return $bytes . ' bytes';
    }
}

if (!function_exists('admin_comm_decisionBadge')) {
    function admin_comm_decisionBadge(string $decision): string
    {
        return match (strtoupper($decision)) {
            'PENDING'   => 'bg-yellow-50 text-yellow-700 border-yellow-200',
            'ACCEPTED'  => 'bg-blue-50 text-blue-700 border-blue-200',
            'COMPLETED' => 'bg-green-50 text-green-700 border-green-200',
            'NOTED'     => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'DECLINED'  => 'bg-red-50 text-red-700 border-red-200',
            'REJECTED'  => 'bg-red-50 text-red-700 border-red-200',
            default     => 'bg-gray-50 text-gray-700 border-gray-200',
        };
    }
}

$statusBadgeColor = !empty($document['status_badge_color'])
    ? $document['status_badge_color'] : '#6B7280';
?>

<div class="space-y-6">

    <!-- ── Header ────────────────────────────────────────────────────────────── -->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary
                    to-indigo-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <a href="<?= BASE_URL ?>/admin/communications"
                           class="flex h-10 w-10 items-center justify-center rounded-xl
                                  bg-white/10 text-white hover:bg-white/20 transition">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 19l-7-7 7-7"/>
                            </svg>
                        </a>
                        <div>
                            <p class="text-sm font-medium text-blue-100">DOCUMENT DETAILS</p>
                            <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                                <?= htmlspecialchars($document['tracking_number'] ?? '') ?>
                            </h1>
                            <p class="mt-1 text-sm text-blue-100">
                                <?= htmlspecialchars($pageSubtitle) ?>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <span class="inline-flex items-center rounded-xl bg-white/10 px-3 py-1.5
                                     text-sm font-medium text-white backdrop-blur-sm">
                            <?= htmlspecialchars(admin_comm_formatPhaseName($document['current_phase'] ?? '')) ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64
                        rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72
                        rounded-full bg-white/5"></div>
        </div>
    </section>

    <!-- ── Main layout: 2-col on lg ──────────────────────────────────────────── -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <!-- ── Left / main column ─────────────────────────────────────────────── -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Document Information ─────────────────────────────────────────── -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">Document Information</h2>

                <div class="mt-4 space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-medium text-gray-500">Tracking Number</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900">
                                <?= htmlspecialchars($document['tracking_number'] ?? '—') ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500">Date &amp; Time Received</p>
                            <p class="mt-1 text-sm text-gray-900">
                                <?= htmlspecialchars(date('F j, Y', strtotime($document['date_received']))) ?>
                                at
                                <?= htmlspecialchars(date('g:i A', strtotime($document['time_received']))) ?>
                            </p>
                        </div>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-gray-500">Subject Matter</p>
                        <p class="mt-1 text-sm text-gray-900 whitespace-pre-wrap leading-relaxed">
                            <?= htmlspecialchars($document['subject_matter'] ?? '—') ?>
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-medium text-gray-500">Document Type</p>
                            <span class="mt-1 inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                  style="background-color: <?= htmlspecialchars($document['document_type_badge_color'] ?? '#2563EB') ?>1a;
                                         color: <?= htmlspecialchars($document['document_type_badge_color'] ?? '#2563EB') ?>;">
                                <?= htmlspecialchars($document['document_type_name'] ?? '—') ?>
                            </span>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500">Current Status</p>
                            <span class="mt-1 inline-flex items-center gap-1.5 rounded-full border
                                         px-2.5 py-0.5 text-xs font-semibold shadow-sm"
                                  style="background-color: <?= htmlspecialchars($statusBadgeColor) ?>;
                                         border-color: <?= htmlspecialchars($statusBadgeColor) ?>;
                                         color: white;">
                                <?= htmlspecialchars($document['status'] ?? '—') ?>
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-medium text-gray-500">Current Phase</p>
                            <span class="mt-1 inline-flex items-center rounded-lg bg-gray-50
                                         border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700">
                                <?= htmlspecialchars(admin_comm_formatPhaseName($document['current_phase'] ?? '')) ?>
                            </span>
                        </div>
                        <?php if (!empty($document['committee_name'])): ?>
                        <div>
                            <p class="text-xs font-medium text-gray-500">Assigned Committee</p>
                            <span class="mt-1 inline-flex items-center gap-1 rounded-lg bg-amber-50
                                         border border-amber-200 px-2.5 py-1 text-xs font-medium text-amber-700">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0
                                             014-4h1m4-4a4 4 0 100-8 4 4 0 000 8z"/>
                                </svg>
                                <?= htmlspecialchars($document['committee_name']) ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($document['communication_category_name'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Communication Category</p>
                        <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-emerald-50
                                     border border-emerald-200 px-2.5 py-0.5 text-xs font-medium
                                     text-emerald-700">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0
                                         010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0
                                         013 12V7a4 4 0 014-4z"/>
                            </svg>
                            <?= htmlspecialchars($document['communication_category_name']) ?>
                        </span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($document['remarks'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Remarks / Notes</p>
                        <p class="mt-1 text-sm text-gray-900 whitespace-pre-wrap leading-relaxed">
                            <?= htmlspecialchars($document['remarks']) ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Source Information ───────────────────────────────────────────── -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">Source Information</h2>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-medium text-gray-500">Source Type</p>
                        <p class="mt-1 text-sm text-gray-900">
                            <?= htmlspecialchars($document['source_type'] ?? '—') ?>
                        </p>
                    </div>

                    <?php if (!empty($document['external_office_name'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">External Office</p>
                        <p class="mt-1 text-sm text-gray-900">
                            <?= htmlspecialchars($document['external_office_name']) ?>
                            <?php if (!empty($document['external_office_abbr'])): ?>
                                <span class="text-gray-500">
                                    (<?= htmlspecialchars($document['external_office_abbr']) ?>)
                                </span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($document['hospital_name'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Hospital</p>
                        <p class="mt-1 text-sm text-gray-900">
                            <?= htmlspecialchars($document['hospital_name']) ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($document['municipality_name'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Municipality / City</p>
                        <p class="mt-1 text-sm text-gray-900">
                            <?= htmlspecialchars($document['municipality_name']) ?>
                            <?= ($document['municipality_type'] === 'City')
                                ? '<span class="text-gray-400">(City)</span>' : '' ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($document['source_name'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Source Name</p>
                        <p class="mt-1 text-sm text-gray-900">
                            <?= htmlspecialchars($document['source_name']) ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($document['source_contact_number'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Contact Number</p>
                        <p class="mt-1 text-sm text-gray-900">
                            <?= htmlspecialchars($document['source_contact_number']) ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($document['source_address'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Address</p>
                        <p class="mt-1 text-sm text-gray-900">
                            <?= htmlspecialchars($document['source_address']) ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($document['source_liaison_name'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Liaison Name</p>
                        <p class="mt-1 text-sm text-gray-900">
                            <?= htmlspecialchars($document['source_liaison_name']) ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Routing History ──────────────────────────────────────────────── -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">Routing History</h2>

                <?php if (empty($routes)): ?>
                    <div class="mt-4 py-8 text-center">
                        <p class="text-sm text-gray-500">No routing records found.</p>
                    </div>
                <?php else: ?>
                    <div class="mt-4 flow-root">
                        <ul role="list" class="-mb-8">
                            <?php foreach ($routes as $idx => $route): ?>
                                <li>
                                    <div class="relative pb-8">
                                        <?php if ($idx < count($routes) - 1): ?>
                                            <span class="absolute left-4 top-4 -ml-px h-full w-0.5
                                                         bg-gray-200" aria-hidden="true"></span>
                                        <?php endif; ?>
                                        <div class="relative flex space-x-3">
                                            <div>
                                                <span class="flex h-8 w-8 items-center justify-center
                                                             rounded-full bg-primary/10 ring-8 ring-white">
                                                    <svg class="h-4 w-4 text-primary" fill="none"
                                                         stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                              stroke-width="2"
                                                              d="M9 5l7 7-7 7"/>
                                                    </svg>
                                                </span>
                                            </div>
                                            <div class="flex min-w-0 flex-1 justify-between
                                                        space-x-4 pt-1.5">
                                                <div>
                                                    <p class="text-sm text-gray-900">
                                                        Routed from
                                                        <span class="font-semibold">
                                                            <?= htmlspecialchars(admin_comm_formatPhaseName($route['from_phase'] ?? '')) ?>
                                                        </span>
                                                        to
                                                        <span class="font-semibold">
                                                            <?= htmlspecialchars(admin_comm_formatPhaseName($route['to_phase'])) ?>
                                                        </span>
                                                        <?php if (!empty($route['routed_to_role_name'])): ?>
                                                            <span class="text-gray-500">
                                                                (<?= htmlspecialchars($route['routed_to_role_name']) ?>)
                                                            </span>
                                                        <?php endif; ?>
                                                    </p>
                                                    <?php if (!empty($route['routing_option_name'])): ?>
                                                        <p class="mt-0.5 text-xs text-gray-500">
                                                            Option:
                                                            <span class="font-medium text-gray-700">
                                                                <?= htmlspecialchars($route['routing_option_name']) ?>
                                                            </span>
                                                        </p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($route['routed_by_username'])): ?>
                                                        <p class="mt-0.5 text-xs text-gray-500">
                                                            By:
                                                            <span class="font-medium text-gray-700">
                                                                <?= htmlspecialchars($route['routed_by_username']) ?>
                                                            </span>
                                                            <?php if (!empty(trim($route['routed_by_fullname'] ?? ''))): ?>
                                                                — <?= htmlspecialchars(trim($route['routed_by_fullname'])) ?>
                                                            <?php endif; ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($route['remarks'])): ?>
                                                        <p class="mt-1 text-xs text-gray-600 italic">
                                                            "<?= htmlspecialchars($route['remarks']) ?>"
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="whitespace-nowrap text-right text-xs text-gray-500">
                                                    <?= htmlspecialchars(date('M j, Y', strtotime($route['created_at']))) ?>
                                                    <br>
                                                    <?= htmlspecialchars(date('g:i A', strtotime($route['created_at']))) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Assignment History ───────────────────────────────────────────── -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">Assignment History</h2>

                <?php if (empty($assignments)): ?>
                    <div class="mt-4 py-8 text-center">
                        <p class="text-sm text-gray-500">No assignment records found.</p>
                    </div>
                <?php else: ?>
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Role</th>
                                    <th class="px-4 py-3 font-medium">Phase</th>
                                    <th class="px-4 py-3 font-medium">Decision</th>
                                    <th class="px-4 py-3 font-medium">Accepted By</th>
                                    <th class="px-4 py-3 font-medium">Received</th>
                                    <th class="px-4 py-3 font-medium">Completed</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($assignments as $asgn): ?>
                                    <tr class="hover:bg-gray-50/50">
                                        <td class="px-4 py-3 text-xs font-medium text-gray-700">
                                            <?= htmlspecialchars($asgn['role_name'] ?? '—') ?>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-600">
                                            <?= htmlspecialchars(admin_comm_formatPhaseName($asgn['phase'] ?? '')) ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex rounded-full border px-2 py-0.5
                                                         text-xs font-semibold
                                                         <?= admin_comm_decisionBadge($asgn['decision'] ?? '') ?>">
                                                <?= htmlspecialchars(ucfirst(strtolower($asgn['decision'] ?? '—'))) ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-700">
                                            <?php if (!empty($asgn['accepted_by_username'])): ?>
                                                <div class="font-medium">
                                                    <?= htmlspecialchars($asgn['accepted_by_username']) ?>
                                                </div>
                                                <?php if (!empty(trim($asgn['accepted_by_fullname'] ?? ''))): ?>
                                                    <div class="text-gray-400">
                                                        <?= htmlspecialchars(trim($asgn['accepted_by_fullname'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-gray-400">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                            <?= !empty($asgn['received_at'])
                                                ? htmlspecialchars(date('M j, Y g:i A', strtotime($asgn['received_at'])))
                                                : '—' ?>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                            <?= !empty($asgn['completed_at'])
                                                ? htmlspecialchars(date('M j, Y g:i A', strtotime($asgn['completed_at'])))
                                                : '—' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Workflow Events ──────────────────────────────────────────────── -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">Workflow Events</h2>

                <?php if (empty($events)): ?>
                    <div class="mt-4 py-8 text-center">
                        <p class="text-sm text-gray-500">No events recorded.</p>
                    </div>
                <?php else: ?>
                    <div class="mt-4 flow-root">
                        <ul role="list" class="-mb-8">
                            <?php foreach ($events as $idx => $evt): ?>
                                <li>
                                    <div class="relative pb-8">
                                        <?php if ($idx < count($events) - 1): ?>
                                            <span class="absolute left-4 top-4 -ml-px h-full w-0.5
                                                         bg-gray-200" aria-hidden="true"></span>
                                        <?php endif; ?>
                                        <div class="relative flex space-x-3">
                                            <div>
                                                <span class="flex h-8 w-8 items-center justify-center
                                                             rounded-full bg-gray-100 ring-8 ring-white">
                                                    <svg class="h-4 w-4 text-gray-500" fill="none"
                                                         stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                              stroke-width="2"
                                                              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                </span>
                                            </div>
                                            <div class="flex min-w-0 flex-1 justify-between
                                                        space-x-4 pt-1.5">
                                                <div>
                                                    <p class="text-sm font-medium text-gray-900">
                                                        <?= htmlspecialchars(ucwords(strtolower(
                                                            str_replace('_', ' ', $evt['event_type'] ?? '')
                                                        ))) ?>
                                                    </p>
                                                    <p class="text-xs text-gray-500">
                                                        Phase: <?= htmlspecialchars(admin_comm_formatPhaseName($evt['phase'] ?? '')) ?>
                                                        <?php if (!empty($evt['performed_by_username'])): ?>
                                                            · By: <?= htmlspecialchars($evt['performed_by_username']) ?>
                                                        <?php endif; ?>
                                                    </p>
                                                    <?php if (!empty($evt['remarks'])): ?>
                                                        <p class="mt-0.5 text-xs text-gray-600 italic">
                                                            "<?= htmlspecialchars($evt['remarks']) ?>"
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="whitespace-nowrap text-right text-xs text-gray-500">
                                                    <?= htmlspecialchars(date('M j, Y', strtotime($evt['created_at']))) ?>
                                                    <br>
                                                    <?= htmlspecialchars(date('g:i A', strtotime($evt['created_at']))) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Attachments ──────────────────────────────────────────────────── -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">Attachments</h2>

                <?php if (empty($attachments)): ?>
                    <div class="mt-4 py-8 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor"
                             viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0
                                     01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <p class="mt-2 text-sm text-gray-500">No attachments found.</p>
                    </div>
                <?php else: ?>
                    <div class="mt-4 space-y-2">
                        <?php foreach ($attachments as $attachment): ?>
                            <div class="flex items-center justify-between rounded-xl border
                                        border-gray-200 bg-gray-50 p-3">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center
                                                rounded-lg bg-blue-100 text-primary">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor"
                                             viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  stroke-width="2"
                                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0
                                                     012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1
                                                     0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($attachment['file_name']) ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <?= admin_comm_formatFileSize((int) ($attachment['file_size'] ?? 0)) ?>
                                            &bull;
                                            <?= htmlspecialchars(admin_comm_formatPhaseName($attachment['phase'] ?? '')) ?>
                                            &bull;
                                            <?= htmlspecialchars(date('M j, Y', strtotime($attachment['created_at']))) ?>
                                            <?php if (!empty($attachment['uploaded_by_username'])): ?>
                                                &bull; <?= htmlspecialchars($attachment['uploaded_by_username']) ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                <a href="<?= BASE_URL ?>/<?= htmlspecialchars($attachment['stored_path']) ?>"
                                   target="_blank" rel="noopener"
                                   class="ml-3 shrink-0 rounded-lg border border-gray-200 bg-white
                                          px-3 py-1.5 text-xs font-medium text-gray-700
                                          hover:bg-gray-50 hover:border-primary hover:text-primary
                                          transition">
                                    Download
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

        </div><!-- /main column -->

        <!-- ── Right / sidebar ───────────────────────────────────────────────── -->
        <div class="space-y-6">

            <!-- Quick Summary ─────────────────────────────────────────────────── -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">Summary</h2>

                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex items-start justify-between gap-2">
                        <span class="text-xs text-gray-500 shrink-0 pt-0.5">Tracking #</span>
                        <span class="font-semibold text-gray-900 text-right break-all">
                            <?= htmlspecialchars($document['tracking_number'] ?? '—') ?>
                        </span>
                    </div>
                    <div class="flex items-start justify-between gap-2">
                        <span class="text-xs text-gray-500 shrink-0 pt-0.5">Type</span>
                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                              style="background-color: <?= htmlspecialchars($document['document_type_badge_color'] ?? '#2563EB') ?>1a;
                                     color: <?= htmlspecialchars($document['document_type_badge_color'] ?? '#2563EB') ?>;">
                            <?= htmlspecialchars($document['document_type_name'] ?? '—') ?>
                        </span>
                    </div>
                    <div class="flex items-start justify-between gap-2">
                        <span class="text-xs text-gray-500 shrink-0 pt-0.5">Status</span>
                        <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold"
                              style="background-color: <?= htmlspecialchars($statusBadgeColor) ?>;
                                     border-color: <?= htmlspecialchars($statusBadgeColor) ?>;
                                     color: white;">
                            <?= htmlspecialchars($document['status'] ?? '—') ?>
                        </span>
                    </div>
                    <div class="flex items-start justify-between gap-2">
                        <span class="text-xs text-gray-500 shrink-0 pt-0.5">Phase</span>
                        <span class="text-xs font-medium text-gray-700">
                            <?= htmlspecialchars(admin_comm_formatPhaseName($document['current_phase'] ?? '')) ?>
                        </span>
                    </div>
                    <?php if (!empty($document['communication_category_name'])): ?>
                    <div class="flex items-start justify-between gap-2">
                        <span class="text-xs text-gray-500 shrink-0 pt-0.5">Comm. Category</span>
                        <span class="text-xs font-medium text-emerald-700 text-right">
                            <?= htmlspecialchars($document['communication_category_name']) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="flex items-start justify-between gap-2">
                        <span class="text-xs text-gray-500 shrink-0 pt-0.5">Received</span>
                        <span class="text-xs text-gray-700 text-right">
                            <?= htmlspecialchars(date('M j, Y', strtotime($document['date_received']))) ?>
                        </span>
                    </div>
                </div>

                <div class="mt-4 border-t border-gray-100 pt-4">
                    <a href="<?= BASE_URL ?>/admin/communications"
                       class="flex w-full items-center justify-center gap-2 rounded-xl border
                              border-gray-200 bg-gray-50 px-4 py-2.5 text-sm font-medium
                              text-gray-700 hover:bg-gray-100 hover:border-gray-300 transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 19l-7-7 7-7"/>
                        </svg>
                        Back to Communications
                    </a>
                </div>
            </section>

            <!-- Metadata ─────────────────────────────────────────────────────── -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">Metadata</h2>

                <div class="mt-4 space-y-3">
                    <div>
                        <p class="text-xs font-medium text-gray-500">Created</p>
                        <p class="mt-1 text-xs text-gray-900">
                            <?= htmlspecialchars(date('F j, Y g:i A', strtotime($document['created_at']))) ?>
                            <?php if (!empty($document['created_by_username'])): ?>
                                <br>by
                                <span class="font-medium">
                                    <?= htmlspecialchars($document['created_by_username']) ?>
                                </span>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-gray-500">Last Updated</p>
                        <p class="mt-1 text-xs text-gray-900">
                            <?= htmlspecialchars(date('F j, Y g:i A', strtotime($document['updated_at']))) ?>
                            <?php if (!empty($document['updated_by_username'])): ?>
                                <br>by
                                <span class="font-medium">
                                    <?= htmlspecialchars($document['updated_by_username']) ?>
                                </span>
                            <?php endif; ?>
                        </p>
                    </div>

                    <?php if (!empty($document['finalized_at'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Finalized</p>
                        <p class="mt-1 text-xs text-gray-900">
                            <?= htmlspecialchars(date('F j, Y g:i A', strtotime($document['finalized_at']))) ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($document['filed_at'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Filed</p>
                        <p class="mt-1 text-xs text-gray-900">
                            <?= htmlspecialchars(date('F j, Y g:i A', strtotime($document['filed_at']))) ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <div>
                        <p class="text-xs font-medium text-gray-500">Route Count</p>
                        <p class="mt-1 text-xs font-semibold text-gray-900">
                            <?= count($routes) ?> event<?= count($routes) !== 1 ? 's' : '' ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Attachment Count</p>
                        <p class="mt-1 text-xs font-semibold text-gray-900">
                            <?= count($attachments) ?> file<?= count($attachments) !== 1 ? 's' : '' ?>
                        </p>
                    </div>
                </div>
            </section>

        </div><!-- /sidebar -->

    </div><!-- /grid -->

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

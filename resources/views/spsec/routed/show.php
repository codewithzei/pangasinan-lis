<?php
ob_start();

$document    = $document    ?? [];
$authors     = $authors     ?? [];
$committees  = $committees  ?? [];
$attachments    = $attachments    ?? [];
$routes         = $routes         ?? [];
$assignments    = $assignments    ?? [];
$pageTitle      = $pageTitle      ?? 'Routed Document Details';
$pageSubtitle = $pageSubtitle ?? '';

// ── Helpers ──────────────────────────────────────────────────────────────────

if (!function_exists('spsec_formatPhaseName')) {
    function spsec_formatPhaseName(string $phase): string
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

if (!function_exists('spsec_formatFileSize')) {
    function spsec_formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024)    return number_format($bytes / 1024, 2) . ' KB';
        return $bytes . ' bytes';
    }
}

if (!function_exists('spsec_decisionBadge')) {
    function spsec_decisionBadge(string $decision): string
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

$statusBadgeColor = !empty($document['status_badge_color']) ? $document['status_badge_color'] : '#6B7280';
?>

<div class="detail-page space-y-6">



    <!-- ── Header ────────────────────────────────────────────────────────────── -->
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative z-10 flex flex-col gap-5 px-5 py-6 sm:flex-row sm:items-center sm:px-8 sm:py-7">
            <div class="flex min-w-0 flex-1 items-center gap-3">
                <a href="<?= BASE_URL ?>/spsec/routed"
                   aria-label="Back to Routed Documents"
                   class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 text-white
                          transition hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-blue-100">DOCUMENT DETAILS</p>
                    <h1 class="mt-1 break-words text-2xl font-bold tracking-tight text-white sm:text-3xl">
                        <?= htmlspecialchars($document['tracking_number'] ?? '') ?>
                    </h1>
                    <p class="mt-1 text-sm text-blue-100">
                        <?= htmlspecialchars($pageSubtitle) ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
    </section>

    <?php
    // MFR relationship links (Referenced Original Case / Related Motions)
    $mfrDetailRoute = 'spsec/inbox';
    $resubmissionTopMargin = 'mt-0';
    require __DIR__ . '/../../partials/resubmission-relationship.php';
    unset($resubmissionTopMargin);
    require __DIR__ . '/../../partials/mfr-relationship.php';
    ?>

    <!-- ── Main layout: 2-col on lg ──────────────────────────────────────────── -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <!-- ── Left / main column ─────────────────────────────────────────────── -->
        <div class="lg:col-span-2 flex flex-col gap-6">

            <!-- Document Information ──────────────────────────────────────────── -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-900">Document Information</h2>

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
                        <p class="mt-1 text-sm text-gray-900 whitespace-pre-wrap leading-relaxed"><?= escape_trimmed_text($document['subject_matter'] ?? '—') ?></p>
                    </div>

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
                        <span class="mt-1 inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold"
                              style="background-color: <?= htmlspecialchars($statusBadgeColor) ?>1a;
                                     color: <?= htmlspecialchars($statusBadgeColor) ?>;">
                            <span class="h-1.5 w-1.5 rounded-full" style="background-color: <?= htmlspecialchars($statusBadgeColor) ?>;"></span>
                            <?= htmlspecialchars($document['status'] ?? '—') ?>
                        </span>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-gray-500">Current Division / Phase</p>
                        <span class="mt-1 inline-flex items-center rounded-lg border border-gray-200 bg-gray-50
                                     px-2.5 py-1 text-xs font-medium text-gray-700">
                            <?= htmlspecialchars(spsec_formatPhaseName($document['current_phase'] ?? '')) ?>
                        </span>
                    </div>

                    <?php if (!empty($document['communication_category_name'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Communication Category</p>
                        <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-emerald-50 border
                                     border-emerald-200 px-2.5 py-0.5 text-xs font-medium text-emerald-700">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                            </svg>
                            <?= htmlspecialchars($document['communication_category_name']) ?>
                        </span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($document['remarks'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Remarks / Notes</p>
                        <p class="mt-1 text-sm text-gray-900 whitespace-pre-wrap leading-relaxed"><?= escape_trimmed_text($document['remarks']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Source Information ────────────────────────────────────────────── -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-900">Source Information</h2>

                <div class="mt-4 space-y-4">
                    <div>
                        <p class="text-xs font-medium text-gray-500">Source Type</p>
                        <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($document['source_type'] ?? '—') ?></p>
                    </div>

                    <?php if (!empty($document['external_office_name'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Provincial Government Offices</p>
                        <p class="mt-1 text-sm text-gray-900">
                            <?= htmlspecialchars($document['external_office_name']) ?>
                            <?php if (!empty($document['external_office_abbr'])): ?>
                                (<?= htmlspecialchars($document['external_office_abbr']) ?>)
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($document['hospital_name'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Hospital</p>
                        <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($document['hospital_name']) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($document['municipality_name'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Municipality / City</p>
                        <p class="mt-1 text-sm text-gray-900">
                            <?= htmlspecialchars($document['municipality_name']) ?>
                            <?= ($document['municipality_type'] === 'City') ? '(City)' : '' ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($document['source_name'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Source Name</p>
                        <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($document['source_name']) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($document['source_contact_number'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Contact Number</p>
                        <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($document['source_contact_number']) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($document['source_address'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Address</p>
                        <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($document['source_address']) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($document['source_liaison_name'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Liaison / Contact Person</p>
                        <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($document['source_liaison_name']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Authors -->
            <section class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900">Authors &amp; Sponsors</h2>
                <?php if (!empty($authors)): ?>
                    <div class="mt-5 space-y-5">
                        <?php foreach ([
                            ['AUTHOR', 'Authors / Sponsors', 'Author'],
                            ['CO_AUTHOR', 'Co-authors / Co-sponsors', 'Co-Author'],
                        ] as [$role, $groupLabel, $roleLabel]): ?>
                            <?php
                            $roleAuthors = array_values(array_filter(
                                $authors,
                                static fn(array $author): bool => $author['author_role'] === $role
                            ));
                            ?>
                            <?php if (!empty($roleAuthors)): ?>
                                <div>
                                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">
                                        <?= htmlspecialchars($groupLabel) ?>
                                    </p>
                                    <div class="space-y-2">
                                        <?php if (!empty($roleAuthors[0]['is_all_selected'])): ?>
                                            <div class="flex items-center gap-3 rounded-lg border border-gray-100 bg-gray-50 px-4 py-3">
                                                <div class="min-w-0">
                                                    <p class="text-sm font-semibold text-gray-900">All</p>
                                                </div>
                                                <span class="ml-auto inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-semibold <?= $role === 'CO_AUTHOR' ? 'bg-purple-50 text-purple-700' : 'bg-blue-50 text-blue-700' ?>">
                                                    <?= htmlspecialchars($roleLabel) ?>
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($roleAuthors as $rank => $author): ?>
                                                <div class="flex items-start gap-3 rounded-lg border border-gray-100 bg-gray-50 px-4 py-3">
                                                    <span class="shrink-0 pt-0.5 text-xs font-semibold text-gray-500"><?= $rank + 1 ?>.</span>
                                                    <div class="min-w-0">
                                                        <p class="truncate text-sm font-semibold text-gray-900">
                                                            <?= htmlspecialchars($author['full_name']) ?>
                                                        </p>
                                                        <?php if (!empty($author['member_position'])): ?>
                                                            <p class="text-xs text-gray-500"><?= htmlspecialchars($author['member_position']) ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="mt-4 flex items-center gap-3 rounded-xl border border-dashed border-gray-200 bg-gray-50/50 p-4">
                        <svg class="h-5 w-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <p class="text-sm text-gray-400 italic">No authors have been assigned to this document.</p>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Committees -->
            <section class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900">Committees</h2>
                <?php if (!empty($committees)): ?>
                    <ul class="mt-3 divide-y divide-gray-100">
                        <?php foreach ($committees as $committee): ?>
                            <li class="py-3 first:pt-0 last:pb-0">
                                <p class="break-words text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($committee['committee_name'] ?? '—') ?>
                                </p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="mt-3 border-t border-gray-100 pt-3">
                        <p class="text-sm text-gray-500">No committees have been assigned to this document.</p>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Assignments History ───────────────────────────────────────────── -->
            <section class="order-2 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-base font-semibold text-gray-900">Assignment History</h2>

                <?php if (empty($assignments)): ?>
                    <p class="text-sm text-gray-400">No assignment records found.</p>
                <?php else: ?>
                    <div class="space-y-3 sm:hidden">
                        <?php foreach ($assignments as $asgn): ?>
                            <article class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                                            <?= htmlspecialchars(spsec_formatPhaseName($asgn['phase'] ?? '')) ?>
                                        </p>
                                        <p class="mt-1 break-words text-sm font-semibold text-gray-800">
                                            <?= htmlspecialchars($asgn['role_name'] ?? $asgn['assigned_to_username'] ?? '—') ?>
                                        </p>
                                    </div>
                                    <span class="inline-flex shrink-0 rounded-full border px-2 py-0.5 text-xs font-semibold
                                                 <?= spsec_decisionBadge($asgn['decision'] ?? '') ?>">
                                        <?= htmlspecialchars(ucfirst(strtolower($asgn['decision'] ?? '—'))) ?>
                                    </span>
                                </div>
                                <dl class="mt-3 grid grid-cols-2 gap-3 text-xs">
                                    <div>
                                        <dt class="text-gray-400">Accepted By</dt>
                                        <dd class="mt-0.5 break-words font-medium text-gray-700">
                                            <?= htmlspecialchars($asgn['accepted_by_username'] ?? '—') ?>
                                            <?php if (!empty(trim($asgn['accepted_by_fullname'] ?? ''))): ?>
                                                <span class="block font-normal text-gray-400"><?= htmlspecialchars(trim($asgn['accepted_by_fullname'])) ?></span>
                                            <?php endif; ?>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-gray-400">Received</dt>
                                        <dd class="mt-0.5 break-words text-gray-600">
                                            <?= !empty($asgn['received_at'])
                                                ? htmlspecialchars(date('M j, Y g:i A', strtotime($asgn['received_at'])))
                                                : '—' ?>
                                        </dd>
                                    </div>
                                    <div class="col-span-2">
                                        <dt class="text-gray-400">Completed</dt>
                                        <dd class="mt-0.5 break-words text-gray-600">
                                            <?= !empty($asgn['completed_at'])
                                                ? htmlspecialchars(date('M j, Y g:i A', strtotime($asgn['completed_at'])))
                                                : '—' ?>
                                        </dd>
                                    </div>
                                </dl>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-4 hidden overflow-x-auto sm:block">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-gray-100 bg-gray-50/70 text-[11px] uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3 font-bold">Role</th>
                                    <th class="px-4 py-3 font-bold">Phase</th>
                                    <th class="px-4 py-3 font-bold">Decision</th>
                                    <th class="px-4 py-3 font-bold">Accepted By</th>
                                    <th class="px-4 py-3 font-bold">Received</th>
                                    <th class="px-4 py-3 font-bold">Completed</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($assignments as $asgn): ?>
                                    <tr class="hover:bg-gray-50/50">
                                        <td class="px-4 py-3 text-xs font-medium text-gray-700">
                                            <?= htmlspecialchars($asgn['role_name'] ?? $asgn['assigned_to_username'] ?? '—') ?>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-600">
                                            <?= htmlspecialchars(spsec_formatPhaseName($asgn['phase'] ?? '')) ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold
                                                         <?= spsec_decisionBadge($asgn['decision'] ?? '') ?>">
                                                <?= htmlspecialchars(ucfirst(strtolower($asgn['decision'] ?? '—'))) ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-700">
                                            <?php if (!empty($asgn['accepted_by_username'])): ?>
                                                <div class="font-medium"><?= htmlspecialchars($asgn['accepted_by_username']) ?></div>
                                                <?php if (!empty(trim($asgn['accepted_by_fullname'] ?? ''))): ?>
                                                    <div class="text-gray-400"><?= htmlspecialchars(trim($asgn['accepted_by_fullname'])) ?></div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-gray-400">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                            <?= !empty($asgn['received_at']) ? htmlspecialchars(date('M j, Y g:i A', strtotime($asgn['received_at']))) : '—' ?>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                            <?= !empty($asgn['completed_at']) ? htmlspecialchars(date('M j, Y g:i A', strtotime($asgn['completed_at']))) : '—' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <div class="order-3">
                <?php require __DIR__ . '/../../partials/document-action-timeline.php'; ?>
            </div>

            <!-- Attachments ──────────────────────────────────────────────────── -->
            <section class="order-1 rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-900">Attachments</h2>

                <?php if (empty($attachments)): ?>
                    <div class="mt-4 py-8 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293
                                     l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <p class="mt-2 text-sm text-gray-500">No attachments found.</p>
                    </div>
                <?php else: ?>
                    <div class="mt-4 space-y-2">
                        <?php foreach ($attachments as $attachment): ?>
                            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 p-3">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg
                                                bg-blue-100 text-primary">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586
                                                     a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($attachment['file_name']) ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <?= spsec_formatFileSize((int)($attachment['file_size'] ?? 0)) ?>
                                            &bull;
                                            <?= htmlspecialchars(spsec_formatPhaseName($attachment['phase'] ?? '')) ?>
                                            &bull;
                                            <?= htmlspecialchars(date('M j, Y', strtotime($attachment['created_at']))) ?>
                                            <?php if (!empty($attachment['uploaded_by_username'])): ?>
                                                &bull; <?= htmlspecialchars($attachment['uploaded_by_username']) ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                <a href="<?= BASE_URL ?>/public/<?= htmlspecialchars(
                                    preg_replace('#^(?:public/)+#i', '', ltrim((string) $attachment['stored_path'], '/\\')) ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                   target="_blank"
                                   class="ml-3 shrink-0 rounded-lg border border-gray-200 bg-white px-3 py-1.5
                                          text-xs font-medium text-gray-700 hover:bg-gray-50 hover:border-primary
                                          hover:text-primary transition">
                                    Download
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

        </div><!-- /main column -->

        <!-- ── Right / sidebar column ─────────────────────────────────────────── -->
        <div class="space-y-6">

            <!-- Checklist -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-900">Checklist</h2>

                <?php if (empty($checklistItems)): ?>
                    <div class="mt-4 py-6 text-center">
                        <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2
                                     M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                        <p class="mt-2 text-xs text-gray-500">No checklist items</p>
                    </div>
                <?php else: ?>
                    <div class="mt-4 space-y-2">
                        <?php foreach ($checklistItems as $item): ?>
                            <div class="flex items-start gap-2 rounded-lg border border-gray-200 bg-gray-50 p-3">
                                <div class="flex h-5 items-center">
                                    <?php if ($item['is_completed']): ?>
                                        <svg class="h-5 w-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414
                                                     L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                  clip-rule="evenodd"/>
                                        </svg>
                                    <?php else: ?>
                                        <svg class="h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm0-2a6 6 0 100-12 6 6 0 000 12z"
                                                  clip-rule="evenodd"/>
                                        </svg>
                                    <?php endif; ?>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($item['checklist_name']) ?>
                                    </p>
                                    <?php if (!empty($item['checklist_description'])): ?>
                                        <p class="mt-0.5 text-xs text-gray-500">
                                            <?= htmlspecialchars($item['checklist_description']) ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if ($item['is_completed'] && !empty($item['completed_at'])): ?>
                                        <p class="mt-1 text-xs text-green-600">
                                            Completed on <?= htmlspecialchars(date('M j, Y', strtotime($item['completed_at']))) ?>
                                            <?php if (!empty($item['completed_by_username'])): ?>
                                                by <?= htmlspecialchars($item['completed_by_username']) ?>
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Metadata ─────────────────────────────────────────────────────── -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-900">Metadata</h2>

                <div class="mt-4 space-y-3">
                    <div>
                        <p class="text-xs font-medium text-gray-500">Created</p>
                        <p class="mt-1 text-xs text-gray-900">
                            <?= htmlspecialchars(date('F j, Y g:i A', strtotime($document['created_at']))) ?>
                            <?php if (!empty($document['created_by_username'])): ?>
                                <br>by <span class="font-medium"><?= htmlspecialchars($document['created_by_username']) ?></span>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-gray-500">Last Updated</p>
                        <p class="mt-1 text-xs text-gray-900">
                            <?= htmlspecialchars(date('F j, Y g:i A', strtotime($document['updated_at']))) ?>
                            <?php if (!empty($document['updated_by_username'])): ?>
                                <br>by <span class="font-medium"><?= htmlspecialchars($document['updated_by_username']) ?></span>
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
                        <p class="text-xs font-medium text-gray-500">Public Access</p>
                        <p class="mt-1 text-xs text-gray-900">
                            <?= !empty($document['is_public']) ? 'Yes' : 'No' ?>
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-gray-500">Route Count</p>
                        <p class="mt-1 text-xs font-semibold text-gray-900"><?= count($routes) ?> event<?= count($routes) !== 1 ? 's' : '' ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Attachment Count</p>
                        <p class="mt-1 text-xs font-semibold text-gray-900"><?= count($attachments) ?> file<?= count($attachments) !== 1 ? 's' : '' ?></p>
                    </div>
                </div>
            </section>

        </div><!-- /sidebar column -->

    </div><!-- /grid -->

</div>



<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

<?php
ob_start();

$document       = $document       ?? [];
$attachments    = $attachments    ?? [];
$routes         = $routes         ?? [];
$checklistItems = $checklistItems ?? [];
$pageTitle      = $pageTitle      ?? 'Document Details';
$pageSubtitle   = $pageSubtitle   ?? '';

// Helper: format phase keys to human-readable labels
function formatPhaseName(string $phase): string
{
    $phaseMap = [
        'RECEIVING'    => 'Receiving',
        'ADMIN'        => 'Admin',
        'SP_SECRETARY' => 'SP Secretary',
        'PLENARY'      => 'Plenary',
        'COMMITTEE'    => 'Committee',
        'FINALIZED'    => 'Finalized',
        'FILED'        => 'Filed',
    ];
    return $phaseMap[$phase] ?? $phase;
}

// Helper: human-readable file size
function formatFileSize(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}

$statusBadgeColor = !empty($document['status_badge_color']) ? $document['status_badge_color'] : '#6B7280';
?>

<div class="space-y-6">

    <!-- ── Page header ─────────────────────────────────────────────────────── -->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-blue-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10">
                <div class="flex items-center gap-3">
                    <a href="<?= BASE_URL ?>/admin/routed"
                       class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 text-white
                              hover:bg-white/20 transition">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <div>
                        <p class="text-sm font-medium text-blue-100">DOCUMENT DETAILS</p>
                        <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                            <?= htmlspecialchars($pageSubtitle) ?>
                        </h1>
                    </div>
                </div>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
        </div>
    </section>

    <!-- ── Two-column layout ───────────────────────────────────────────────── -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <!-- ── Main column ─────────────────────────────────────────────────── -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Document Information -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-900">Document Information</h2>

                <div class="mt-4 space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-medium text-gray-500">Tracking Number</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900">
                                <?= htmlspecialchars($document['tracking_number']) ?>
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
                        <p class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">
                            <?= htmlspecialchars($document['subject_matter']) ?>
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-gray-500">Document Type</p>
                        <span class="mt-1 inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                              style="background-color: <?= htmlspecialchars($document['document_type_badge_color'] ?? '#2563EB') ?>1a;
                                     color: <?= htmlspecialchars($document['document_type_badge_color'] ?? '#2563EB') ?>;">
                            <?= htmlspecialchars($document['document_type_name']) ?>
                        </span>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-gray-500">Current Status</p>
                        <span class="mt-1 inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5
                                     text-xs font-semibold shadow-sm"
                              style="background-color: <?= htmlspecialchars($statusBadgeColor) ?>;
                                     border-color: <?= htmlspecialchars($statusBadgeColor) ?>;
                                     color: white;">
                            <?= htmlspecialchars($document['status']) ?>
                        </span>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-gray-500">Current Division / Phase</p>
                        <span class="mt-1 inline-flex items-center rounded-lg bg-gray-50 border border-gray-200
                                     px-2.5 py-1 text-xs font-medium text-gray-700">
                            <?= htmlspecialchars(formatPhaseName($document['current_phase'])) ?>
                        </span>
                    </div>

                    <?php if (!empty($document['remarks'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Remarks / Notes</p>
                        <p class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">
                            <?= htmlspecialchars($document['remarks']) ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Source Information -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-900">Source Information</h2>

                <div class="mt-4 space-y-4">
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

            <!-- Attachments -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-900">Attachments</h2>

                <?php if (empty($attachments)): ?>
                    <div class="mt-4 py-8 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293
                                     l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <p class="mt-2 text-sm text-gray-500">No attachments found</p>
                    </div>
                <?php else: ?>
                    <div class="mt-4 space-y-2">
                        <?php foreach ($attachments as $attachment): ?>
                            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 p-3">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-primary">
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
                                            <?= formatFileSize((int)$attachment['file_size']) ?>
                                            &bull;
                                            <?= htmlspecialchars(formatPhaseName($attachment['phase'])) ?>
                                            &bull;
                                            <?= htmlspecialchars(date('M j, Y', strtotime($attachment['created_at']))) ?>
                                        </p>
                                    </div>
                                </div>
                                <a href="<?= BASE_URL ?>/<?= htmlspecialchars($attachment['stored_path']) ?>"
                                   target="_blank"
                                   class="ml-3 rounded-lg border border-gray-200 bg-white px-3 py-1.5
                                          text-xs font-medium text-gray-700 hover:bg-gray-50 transition">
                                    Download
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Routing History -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-900">Routing History</h2>

                <?php if (empty($routes)): ?>
                    <div class="mt-4 py-8 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="mt-2 text-sm text-gray-500">No routing history available</p>
                    </div>
                <?php else: ?>
                    <div class="mt-4 flow-root">
                        <ul role="list" class="-mb-8">
                            <?php foreach ($routes as $index => $route): ?>
                                <li>
                                    <div class="relative pb-8">
                                        <?php if ($index < count($routes) - 1): ?>
                                            <span class="absolute left-4 top-4 -ml-px h-full w-0.5 bg-gray-200"
                                                  aria-hidden="true"></span>
                                        <?php endif; ?>
                                        <div class="relative flex space-x-3">
                                            <div>
                                                <span class="flex h-8 w-8 items-center justify-center rounded-full
                                                             bg-blue-100 ring-8 ring-white">
                                                    <svg class="h-4 w-4 text-primary" fill="none"
                                                         stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                              stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                                    </svg>
                                                </span>
                                            </div>
                                            <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                                <div>
                                                    <p class="text-sm text-gray-900">
                                                        Routed from
                                                        <span class="font-semibold">
                                                            <?= htmlspecialchars(formatPhaseName($route['from_phase'])) ?>
                                                        </span>
                                                        to
                                                        <span class="font-semibold">
                                                            <?= htmlspecialchars(formatPhaseName($route['to_phase'])) ?>
                                                        </span>
                                                        <?php if (!empty($route['routed_to_role_name'])): ?>
                                                            <span class="text-gray-500">
                                                                (<?= htmlspecialchars($route['routed_to_role_name']) ?>)
                                                            </span>
                                                        <?php endif; ?>
                                                    </p>
                                                    <?php if (!empty($route['routed_by_username'])): ?>
                                                        <p class="mt-0.5 text-xs text-gray-500">
                                                            By: <?= htmlspecialchars($route['routed_by_username']) ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($route['routing_option_name'])): ?>
                                                        <p class="mt-0.5 text-xs text-gray-500">
                                                            Option: <?= htmlspecialchars($route['routing_option_name']) ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($route['remarks'])): ?>
                                                        <p class="mt-1 text-xs text-gray-600">
                                                            <?= htmlspecialchars($route['remarks']) ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="whitespace-nowrap text-right text-xs text-gray-500">
                                                    <?= htmlspecialchars(date('M j, Y', strtotime($route['routed_at']))) ?>
                                                    <br>
                                                    <?= htmlspecialchars(date('g:i A', strtotime($route['routed_at']))) ?>
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

        </div>

        <!-- ── Sidebar column ──────────────────────────────────────────────── -->
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
                                <div class="flex-1">
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

            <!-- Document Metadata -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-900">Metadata</h2>

                <div class="mt-4 space-y-3">

                    <div>
                        <p class="text-xs font-medium text-gray-500">Created</p>
                        <p class="mt-1 text-xs text-gray-900">
                            <?= htmlspecialchars(date('F j, Y g:i A', strtotime($document['created_at']))) ?>
                            <?php if (!empty($document['created_by_username'])): ?>
                                <br>by <?= htmlspecialchars($document['created_by_username']) ?>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-gray-500">Last Updated</p>
                        <p class="mt-1 text-xs text-gray-900">
                            <?= htmlspecialchars(date('F j, Y g:i A', strtotime($document['updated_at']))) ?>
                            <?php if (!empty($document['updated_by_username'])): ?>
                                <br>by <?= htmlspecialchars($document['updated_by_username']) ?>
                            <?php endif; ?>
                        </p>
                    </div>

                    <?php if (!empty($document['current_owner_username'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Current Owner</p>
                        <p class="mt-1 text-xs text-gray-900">
                            <?= htmlspecialchars($document['current_owner_username']) ?>
                        </p>
                    </div>
                    <?php endif; ?>

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

                </div>
            </section>

        </div><!-- /sidebar column -->

    </div><!-- /grid -->

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';
?>

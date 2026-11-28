<?php
ob_start();

$document = $document ?? [];
$authors = $authors ?? [];
$committees = $committees ?? [];
$showAssignmentDetails = $showAssignmentDetails ?? false;
$attachments = $attachments ?? [];
$routes = $routes ?? [];
$checklistItems = $checklistItems ?? [];
$pageTitle = $pageTitle ?? 'Document Details';
$pageSubtitle = $pageSubtitle ?? '';
$detailBackUrl = $detailBackUrl ?? BASE_URL . '/receiving/routed-documents';
$detailBackLabel = $detailBackLabel ?? 'Back to Routed Documents';
$showMfrRelationship = $showMfrRelationship ?? true;
$renderAsPartial = $renderAsPartial ?? false;

// Helper function to format phase names
function formatPhaseName(string $phase): string
{
    $phaseMap = [
        'RECEIVING' => 'Receiving',
        'ADMIN' => 'Admin',
        'SP_SECRETARY' => 'SP Secretary',
        'PLENARY' => 'Plenary',
        'COMMITTEE' => 'Committee',
        'FINALIZED' => 'Finalized',
        'FILED' => 'Filed',
    ];
    return $phaseMap[$phase] ?? $phase;
}

// Helper function to format file size
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

<div class="detail-page space-y-6">

    <?php if (!$renderAsPartial): ?>
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10">
                <div class="flex items-center gap-3">
                        <a href="<?= htmlspecialchars($detailBackUrl) ?>"
                              aria-label="<?= htmlspecialchars($detailBackLabel) ?>"
                       class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 text-white hover:bg-white/20 transition">
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
    <?php endif; ?>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Document Information -->
            <?php
            $mfrDetailRoute = 'receiving/routed-documents';
            $resubmissionTopMargin = 'mt-0';
            require __DIR__ . '/../../partials/resubmission-relationship.php';
            unset($resubmissionTopMargin);
            ?>
            <?php if ($showMfrRelationship): ?>
                <?php
                // MFR relationship sections: delegated to shared partial.
                $mfrDetailRoute = 'receiving/routed-documents';
                require __DIR__ . '/../../partials/mfr-relationship.php';
                ?>
            <?php endif; ?>

            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-900">Document Information</h2>
                
                <div class="mt-4 space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-medium text-gray-500">Tracking Number</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900"><?= htmlspecialchars($document['tracking_number']) ?></p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500">Date & Time Received</p>
                            <p class="mt-1 text-sm text-gray-900">
                                <?= htmlspecialchars(date('F j, Y', strtotime($document['date_received']))) ?> at 
                                <?= htmlspecialchars(date('g:i A', strtotime($document['time_received']))) ?>
                            </p>
                        </div>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-gray-500">Subject Matter</p>
                        <p class="mt-1 text-sm text-gray-900 whitespace-pre-wrap"><?= escape_trimmed_text($document['subject_matter']) ?></p>
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
                        <span class="mt-1 inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold"
                              style="background-color: <?= htmlspecialchars($statusBadgeColor) ?>1a; color: <?= htmlspecialchars($statusBadgeColor) ?>;">
                            <span class="h-1.5 w-1.5 rounded-full" style="background-color: <?= htmlspecialchars($statusBadgeColor) ?>;"></span>
                            <?= htmlspecialchars($document['status']) ?>
                        </span>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-gray-500">Current Division / Phase</p>
                        <span class="mt-1 inline-flex items-center rounded-lg bg-gray-50 border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700">
                            <?= htmlspecialchars(formatPhaseName($document['current_phase'])) ?>
                        </span>
                    </div>

                    <?php if (!empty($document['remarks'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Remarks / Notes</p>
                        <p class="mt-1 text-sm text-gray-900 whitespace-pre-wrap"><?= escape_trimmed_text($document['remarks']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($showAssignmentDetails): ?>
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
            <?php endif; ?>

            <?php if (!empty($plenaryDetails) || !empty($plenaryAuthors) || !empty($plenaryFirstReading)): ?>
                <section class="rounded-2xl border border-violet-100 bg-white p-6">
                    <h2 class="text-lg font-semibold text-gray-900">Plenary First Reading Details</h2>
                    <p class="mt-1 text-sm text-gray-500">Information recorded in Plenary before Committee referral.</p>
                    <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <?php foreach ([
                            'Proposed Number' => $plenaryDetails['proposed_number'] ?? null,
                            'Target First Reading Date' => !empty($plenaryDetails['target_first_reading_date'])
                                ? date('F j, Y', strtotime((string) $plenaryDetails['target_first_reading_date']))
                                : null,
                            'Session Type' => !empty($plenaryDetails['session_type'])
                                ? ucwords(strtolower(str_replace('_', ' ', (string) $plenaryDetails['session_type'])))
                                : null,
                        ] as $label => $value): ?>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500"><?= htmlspecialchars($label) ?></dt>
                                <dd class="mt-1 text-sm text-gray-900"><?= htmlspecialchars((string) ($value ?: '—')) ?></dd>
                            </div>
                        <?php endforeach; ?>
                        <?php foreach (['AUTHOR' => 'Author(s)', 'CO_AUTHOR' => 'Co-Author(s)'] as $role => $label): ?>
                            <?php
                            $authorNames = array_values(array_filter(array_map(
                                static fn(array $author): string => trim((string) ($author['full_name'] ?? '')),
                                array_filter($plenaryAuthors ?? [], static fn(array $author): bool => ($author['author_role'] ?? '') === $role)
                            )));
                            $allAuthors = $role === 'AUTHOR'
                                ? !empty($plenaryDetails['authors_all'])
                                : !empty($plenaryDetails['co_authors_all']);
                            ?>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500"><?= $label ?></dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    <?= $allAuthors ? 'ALL' : htmlspecialchars($authorNames !== [] ? implode(', ', $authorNames) : '—') ?>
                                </dd>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!empty($plenaryDetails['additional_details'])): ?>
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Additional Plenary Details</dt>
                                <dd class="mt-1 whitespace-pre-wrap text-sm text-gray-900"><?= htmlspecialchars((string) $plenaryDetails['additional_details']) ?></dd>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($plenaryFirstReading)): ?>
                            <?php foreach ([
                                'First Reading Type' => $plenaryFirstReading['reading_type'] ?? null,
                                'First Reading Date' => !empty($plenaryFirstReading['reading_date'])
                                    ? date('F j, Y', strtotime((string) $plenaryFirstReading['reading_date']))
                                    : null,
                                'Plenary Decision' => $plenaryFirstReading['decision'] ?? null,
                                'First Reading Remarks' => $plenaryFirstReading['remarks'] ?? null,
                            ] as $label => $value): ?>
                                <?php if (!empty($value)): ?>
                                    <div class="<?= $label === 'First Reading Remarks' ? 'sm:col-span-2' : '' ?>">
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500"><?= htmlspecialchars($label) ?></dt>
                                        <dd class="mt-1 whitespace-pre-wrap text-sm text-gray-900"><?= htmlspecialchars((string) $value) ?></dd>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </dl>
                </section>
            <?php endif; ?>

            <!-- Source Information -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-900">Source Information</h2>
                
                <div class="mt-4 space-y-4">
                    <div>
                        <p class="text-xs font-medium text-gray-500">Source Type</p>
                        <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($document['source_type']) ?></p>
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
                            <?= $document['municipality_type'] === 'City' ? '(City)' : '' ?>
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
                    <div class="mt-4 text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <p class="mt-2 text-sm text-gray-500">No attachments found</p>
                    </div>
                <?php else: ?>
                    <div class="mt-4 space-y-2">
                        <?php foreach ($attachments as $attachment):
                            $attachmentName = (string) ($attachment['file_name'] ?? 'Attachment');
                            $attachmentUrl = BASE_URL . '/public/' . (
                                preg_replace('#^(?:public/)+#i', '', ltrim((string) ($attachment['stored_path'] ?? ''), '/\\')) ?? ''
                            );
                        ?>
                            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 p-3">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-primary">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-gray-900 truncate"><?= htmlspecialchars($attachmentName) ?></p>
                                        <p class="text-xs text-gray-500">
                                            <?= formatFileSize($attachment['file_size']) ?> •
                                            <?= htmlspecialchars(formatPhaseName($attachment['phase'])) ?>
                                            <?php if (!empty($attachment['attachment_type'])): ?>
                                                • <?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $attachment['attachment_type'])))) ?>
                                            <?php endif; ?>
                                            •
                                            <?= htmlspecialchars(date('M j, Y', strtotime($attachment['created_at']))) ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="ml-3 flex shrink-0 items-center gap-2">
                                    <a href="<?= htmlspecialchars($attachmentUrl, ENT_QUOTES, 'UTF-8') ?>"
                                       data-attachment-preview
                                       data-preview-url="<?= htmlspecialchars($attachmentUrl, ENT_QUOTES, 'UTF-8') ?>"
                                       data-preview-name="<?= htmlspecialchars($attachmentName, ENT_QUOTES, 'UTF-8') ?>"
                                       data-download-url="<?= htmlspecialchars($attachmentUrl, ENT_QUOTES, 'UTF-8') ?>"
                                       class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-hover transition">
                                        Preview
                                    </a>
                                    <a href="<?= htmlspecialchars($attachmentUrl, ENT_QUOTES, 'UTF-8') ?>"
                                       download
                                       class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 transition">
                                        Download
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <?php require __DIR__ . '/../../partials/document-action-timeline.php'; ?>

            <?php require __DIR__ . '/../../partials/assignment-history.php'; ?>

        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            
            <!-- Checklist -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-900">Checklist</h2>
                
                <?php if (empty($checklistItems)): ?>
                    <div class="mt-4 text-center py-6">
                        <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
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
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                    <?php else: ?>
                                        <svg class="h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm0-2a6 6 0 100-12 6 6 0 000 12z" clip-rule="evenodd"/>
                                        </svg>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($item['checklist_name']) ?></p>
                                    <?php if (!empty($item['checklist_description'])): ?>
                                        <p class="mt-0.5 text-xs text-gray-500"><?= htmlspecialchars($item['checklist_description']) ?></p>
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
                            <?php if (!empty($document['updated_at'])): ?>
                                <?= htmlspecialchars(date('F j, Y g:i A', strtotime($document['updated_at']))) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                            <?php if (!empty($document['updated_by_username'])): ?>
                                <br>by <?= htmlspecialchars($document['updated_by_username']) ?>
                            <?php endif; ?>
                        </p>
                    </div>

                    <?php if (!empty($document['current_owner_username'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Current Owner</p>
                        <p class="mt-1 text-xs text-gray-900"><?= htmlspecialchars($document['current_owner_username']) ?></p>
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

                </div>
            </section>

        </div>

    </div>

</div>

<?php
$content = ob_get_clean();
if ($renderAsPartial) {
    echo $content;
} else {
    require __DIR__ . '/../../layouts/app.php';
}
?>
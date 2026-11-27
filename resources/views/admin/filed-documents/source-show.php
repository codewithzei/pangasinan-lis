<?php
/**
 * Admin → Filed Documents → Source Show
 *
 * Read-only detail view for a finalized document that has NOT yet been filed.
 * Accessed from the "To Be Filed" queue via the View button.
 *
 * Rendered via AdminFiledDocumentsController::viewSource()
 * Layout is applied by the controller (ob_start / ob_get_clean pattern).
 *
 * Available variables:
 *   $document        array  Full document row (includes approval cols, source cols, type/status)
 *   $authors         array  Authors/sponsors from document_authors + sp_members
 *   $committees     array  Committees assigned to this document
 *   $allAttachments  array  All document_attachments rows for this document
 *   $pageTitle       string
 *   $pageSubtitle    string (tracking number)
 */
$document       = $document       ?? [];
$authors        = $authors        ?? [];
$committees     = $committees     ?? [];
$allAttachments = $allAttachments ?? [];
$routes         = $routes         ?? [];
$checklistItems = $checklistItems ?? [];
$documentEvents = $documentEvents ?? [];
$pageTitle      = $pageTitle      ?? 'Finalized Document Details';
$pageSubtitle   = $pageSubtitle   ?? '';

$statusBadgeColor = !empty($document['status_badge_color']) ? $document['status_badge_color'] : '#6B7280';
$typeColor        = !empty($document['document_type_badge_color']) ? $document['document_type_badge_color'] : '#2563EB';

function _ss_formatBytes(int $bytes): string
{
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)    return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}

function _ss_phaseLabel(string $phase): string
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

function _ss_typeLabel(string $type): string
{
    return [
        'RECEIVING_FILE'   => 'Receiving File',
        'ADMIN_FILE'       => 'Admin File',
        'SPSEC_FILE'       => 'SP Secretary File',
        'COMMITTEE_FILE'   => 'Committee File',
        'OPINION_FILE'     => 'Opinion File',
        'COMPLIANCE_FILE'  => 'Compliance File',
        'COMMITTEE_REPORT' => 'Committee Report',
        'APPROVAL_FILE'    => 'Approval File',
        'FILING_FILE'      => 'Filing File',
        'OTHER'            => 'Other',
    ][$type] ?? $type;
}
?>

<div class="detail-page space-y-6">

    <!-- ── Page header ──────────────────────────────────────────────────── -->
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative z-10 flex flex-col gap-5 px-5 py-6 sm:flex-row sm:items-center sm:px-8 sm:py-7">
                    <a href="<?= BASE_URL ?>/admin/filed"
                       class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 text-white
                              transition hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                       aria-label="Back to Filed Documents">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-blue-100">Finalized · Awaiting filing</p>
                        <h1 class="mt-1 break-words text-2xl font-bold tracking-tight text-white sm:text-3xl">
                            <?= htmlspecialchars($pageSubtitle) ?>
                        </h1>
                    </div>
        </div>
        <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
    </section>

    <!-- ── Not filed yet notice ─────────────────────────────────────────── -->
    <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div class="flex-1">
            <p class="text-sm font-medium text-amber-900">This document has not been filed yet.</p>
            <p class="mt-0.5 text-xs text-amber-800">
                It is finalized and waiting to be filed. Go back to the
                <a href="<?= BASE_URL ?>/admin/filed" class="font-semibold underline underline-offset-2 hover:text-amber-950">
                    Filed Documents
                </a>
                page and use the <strong>File</strong> button to file it.
            </p>
        </div>
    </div>

    <!-- ── Two-column layout ────────────────────────────────────────────── -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <!-- ── Main column ──────────────────────────────────────────────── -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Document Information -->
            <?php
            $resubmissionTopMargin = 'mt-0';
            require __DIR__ . '/../../partials/resubmission-relationship.php';
            unset($resubmissionTopMargin);
            $mfrDetailRoute = 'admin/routed';
            require __DIR__ . '/../../partials/mfr-relationship.php';
            ?>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900">Document Information</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">

                    <div>
                        <p class="text-xs font-medium text-gray-500">Tracking Number</p>
                        <p class="mt-1 text-sm font-semibold text-gray-900">
                            <?= htmlspecialchars($document['tracking_number']) ?>
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-gray-500">Approved Number</p>
                        <p class="mt-1 text-sm font-semibold text-gray-900">
                            <?= !empty($document['approved_number']) ? htmlspecialchars($document['approved_number']) : '—' ?>
                        </p>
                    </div>

                    <div class="sm:col-span-2">
                        <p class="text-xs font-medium text-gray-500">Subject Matter</p>
                        <p class="mt-1 text-sm text-gray-900 whitespace-pre-wrap"><?= escape_trimmed_text($document['subject_matter']) ?></p>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-gray-500">Document Type</p>
                        <span class="mt-1 inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                              style="background-color:<?= htmlspecialchars($typeColor) ?>14; color:<?= htmlspecialchars($typeColor) ?>;">
                            <?= htmlspecialchars($document['document_type_name'] ?? '—') ?>
                        </span>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-gray-500">Current Status</p>
                        <span class="mt-1 inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                              style="background-color:<?= htmlspecialchars($statusBadgeColor) ?>14; color:<?= htmlspecialchars($statusBadgeColor) ?>;">
                            <span class="h-1.5 w-1.5 rounded-full" style="background-color:<?= htmlspecialchars($statusBadgeColor) ?>;"></span>
                            <?= htmlspecialchars($document['status'] ?? '—') ?>
                        </span>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-gray-500">Date &amp; Time Received</p>
                        <p class="mt-1 text-sm text-gray-900">
                            <?= htmlspecialchars(date('F j, Y', strtotime($document['date_received']))) ?>
                            <?php if (!empty($document['time_received'])): ?>
                                at <?= htmlspecialchars(date('g:i A', strtotime($document['time_received']))) ?>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-gray-500">Current Division / Phase</p>
                        <span class="mt-1 inline-flex items-center rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-700">
                            <?= htmlspecialchars(_ss_phaseLabel($document['current_phase'] ?? '')) ?>
                        </span>
                    </div>

                    <?php if (!empty($document['date_approved'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Date Approved</p>
                        <p class="mt-1 text-sm text-gray-900">
                            <?= htmlspecialchars(date('F j, Y', strtotime($document['date_approved']))) ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($document['finalized_at'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Finalized At</p>
                        <p class="mt-1 text-sm text-gray-900">
                            <?= htmlspecialchars(date('F j, Y g:i A', strtotime($document['finalized_at']))) ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($document['remarks'])): ?>
                    <div class="sm:col-span-2">
                        <p class="text-xs font-medium text-gray-500">Remarks</p>
                        <p class="mt-1 text-sm text-gray-900 whitespace-pre-wrap"><?= escape_trimmed_text($document['remarks']) ?></p>
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

            <!-- Attachments -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-gray-900">Attachments</h2>

                <?php if (empty($allAttachments)): ?>
                    <div class="mt-4 py-8 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293
                                     l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <p class="mt-2 text-sm text-gray-500">No attachments found</p>
                    </div>
                <?php else: ?>
                    <div class="mt-4 space-y-2">
                        <?php foreach ($allAttachments as $att): ?>
                            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 p-3">
                                <div class="flex min-w-0 flex-1 items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-primary">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586
                                                     a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($att['file_name']) ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <?= htmlspecialchars(_ss_phaseLabel($att['phase'])) ?>
                                            &bull;
                                            <?= htmlspecialchars(_ss_typeLabel($att['attachment_type'])) ?>
                                            &bull;
                                            <?= htmlspecialchars(_ss_formatBytes((int) $att['file_size'])) ?>
                                            &bull;
                                            <?= htmlspecialchars(date('M j, Y', strtotime($att['created_at']))) ?>
                                        </p>
                                    </div>
                                </div>
                                <a href="<?= BASE_URL ?>/admin/filed/source-attachment?attachment_id=<?= (int) $att['id'] ?>&amp;document_id=<?= (int) $document['id'] ?>"
                                   data-attachment-preview
                                   data-preview-url="<?= BASE_URL ?>/admin/filed/source-attachment?attachment_id=<?= (int) $att['id'] ?>&amp;document_id=<?= (int) $document['id'] ?>&amp;mode=raw"
                                   data-download-url="<?= BASE_URL ?>/admin/filed/source-attachment?attachment_id=<?= (int) $att['id'] ?>&amp;document_id=<?= (int) $document['id'] ?>&amp;mode=download"
                                   data-preview-name="<?= htmlspecialchars($att['file_name']) ?>"
                                   target="_blank" rel="noopener"
                                   class="ml-3 shrink-0 rounded-lg border border-gray-200 bg-white px-3 py-1.5
                                          text-xs font-medium text-gray-700 transition hover:bg-gray-50"
                                   title="View attachment" aria-label="View attachment">
                                    View
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <?php require __DIR__ . '/../../partials/document-action-timeline.php'; ?>

            <?php require __DIR__ . '/../../partials/assignment-history.php'; ?>

        </div><!-- /main col -->

        <!-- ── Sidebar column ───────────────────────────────────────────── -->
        <div class="space-y-6">

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

                    <?php if (($document['source_type'] ?? '') === 'Provincial Government Offices'): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Provincial Government Offices</p>
                        <p class="mt-1 text-sm text-gray-900">
                            <?= htmlspecialchars($document['external_office_name'] ?? '—') ?>
                            <?php if (!empty($document['external_office_abbr'])): ?>
                                (<?= htmlspecialchars($document['external_office_abbr']) ?>)
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <?php if (($document['source_type'] ?? '') === 'Hospital'): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Hospital</p>
                        <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($document['hospital_name'] ?? '—') ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if (($document['source_type'] ?? '') === 'Client'): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Municipality / City</p>
                        <p class="mt-1 text-sm text-gray-900">
                            <?= htmlspecialchars($document['municipality_name'] ?? '—') ?>
                            <?php if (!empty($document['municipality_type'])): ?>
                                (<?= htmlspecialchars($document['municipality_type']) ?>)
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php if (!empty($document['district_name'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">District</p>
                        <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($document['district_name']) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>

                    <?php if (in_array($document['source_type'] ?? '', ['SP Member', 'Agency', 'Client'], true)): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">
                            <?= ($document['source_type'] ?? '') === 'Agency'
                                ? 'Agency Name'
                                : ((($document['source_type'] ?? '') === 'Client') ? 'Client Name' : 'SP Member') ?>
                        </p>
                        <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($document['source_name'] ?? '—') ?></p>
                    </div>
                    <?php endif; ?>

                    <div>
                        <p class="text-xs font-medium text-gray-500">Contact Number</p>
                        <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($document['source_contact_number'] ?: '—') ?></p>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-gray-500">Address</p>
                        <p class="mt-1 whitespace-pre-wrap text-sm text-gray-900"><?= htmlspecialchars($document['source_address'] ?: '—') ?></p>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-gray-500">Liaison / Contact Person</p>
                        <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($document['source_liaison_name'] ?: '—') ?></p>
                    </div>
                </div>
            </section>


            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900">Checklist</h2>
                <?php if (empty($checklistItems)): ?>
                    <div class="mt-4 py-6 text-center">
                        <p class="text-xs text-gray-500">No checklist items</p>
                    </div>
                <?php else: ?>
                    <div class="mt-4 space-y-2">
                        <?php foreach ($checklistItems as $item): ?>
                            <div class="flex items-start gap-2 rounded-lg border border-gray-200 bg-gray-50 p-3">
                                <div class="flex h-5 items-center">
                                    <?php if ($item['is_completed']): ?>
                                        <svg class="h-5 w-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                    <?php else: ?>
                                        <svg class="h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm0-2a6 6 0 100-12 6 6 0 000 12z" clip-rule="evenodd"/>
                                        </svg>
                                    <?php endif; ?>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($item['checklist_name']) ?></p>
                                    <?php if (!empty($item['checklist_description'])): ?>
                                        <p class="mt-0.5 text-xs text-gray-500"><?= htmlspecialchars($item['checklist_description']) ?></p>
                                    <?php endif; ?>
                                    <?php if ($item['is_completed'] && !empty($item['completed_at'])): ?>
                                        <p class="mt-1 text-xs text-emerald-600">
                                            Completed <?= htmlspecialchars(date('M j, Y', strtotime($item['completed_at']))) ?>
                                            <?php if (!empty($item['completed_by_username'])): ?>by <?= htmlspecialchars($item['completed_by_username']) ?><?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900">Metadata</h2>
                <div class="mt-4 space-y-3">
                    <?php foreach ([
                        'Created' => ['date' => $document['created_at'] ?? null, 'user' => $document['created_by_username'] ?? null],
                        'Last Updated' => ['date' => $document['updated_at'] ?? null, 'user' => $document['updated_by_username'] ?? null],
                    ] as $label => $metadata): ?>
                        <?php if (!empty($metadata['date'])): ?>
                            <div>
                                <p class="text-xs font-medium text-gray-500"><?= htmlspecialchars($label) ?></p>
                                <p class="mt-1 text-xs text-gray-900">
                                    <?= htmlspecialchars(date('F j, Y g:i A', strtotime($metadata['date']))) ?>
                                    <?php if (!empty($metadata['user'])): ?><br>by <?= htmlspecialchars($metadata['user']) ?><?php endif; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (!empty($document['current_owner_username'])): ?>
                        <div>
                            <p class="text-xs font-medium text-gray-500">Current Owner</p>
                            <p class="mt-1 text-xs text-gray-900"><?= htmlspecialchars($document['current_owner_username']) ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($document['finalized_at'])): ?>
                        <div>
                            <p class="text-xs font-medium text-gray-500">Finalized</p>
                            <p class="mt-1 text-xs text-gray-900"><?= htmlspecialchars(date('F j, Y g:i A', strtotime($document['finalized_at']))) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

        </div><!-- /sidebar col -->
    </div>

</div>

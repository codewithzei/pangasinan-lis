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
 *   $allAttachments  array  All document_attachments rows for this document
 *   $pageTitle       string
 *   $pageSubtitle    string (tracking number)
 */
$document       = $document       ?? [];
$authors        = $authors        ?? [];
$allAttachments = $allAttachments ?? [];
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

<div class="space-y-6">

    <!-- ── Page header ──────────────────────────────────────────────────── -->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10">
                <div class="flex items-center gap-3">
                    <a href="<?= BASE_URL ?>/admin/filed"
                       class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 text-white
                              hover:bg-white/20 transition"
                       aria-label="Back to Filed Documents">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-medium text-blue-100">FINALIZED — AWAITING FILING</p>
                        </div>
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

    <!-- ── Not filed yet notice ─────────────────────────────────────────── -->
    <div class="flex items-start gap-3 rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div class="flex-1">
            <p class="text-sm font-medium text-blue-900">This document has not been filed yet.</p>
            <p class="mt-0.5 text-xs text-blue-700">
                It is finalized and waiting to be filed. Go back to the
                <a href="<?= BASE_URL ?>/admin/filed" class="font-semibold underline underline-offset-2 hover:text-blue-900">
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
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-base font-semibold text-gray-900">Document Information</h2>
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
                        <p class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">
                            <?= htmlspecialchars($document['subject_matter']) ?>
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-gray-500">Document Type</p>
                        <span class="mt-1 inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                              style="background-color:<?= htmlspecialchars($typeColor) ?>1a; color:<?= htmlspecialchars($typeColor) ?>;">
                            <?= htmlspecialchars($document['document_type_name'] ?? '—') ?>
                        </span>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-gray-500">Current Status</p>
                        <span class="mt-1 inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold"
                              style="background-color:<?= htmlspecialchars($statusBadgeColor) ?>; border-color:<?= htmlspecialchars($statusBadgeColor) ?>; color:white;">
                            <?= htmlspecialchars($document['status'] ?? '—') ?>
                        </span>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-gray-500">Date Received</p>
                        <p class="mt-1 text-sm text-gray-900">
                            <?= htmlspecialchars(date('F j, Y', strtotime($document['date_received']))) ?>
                        </p>
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
                        <p class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">
                            <?= htmlspecialchars($document['remarks']) ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Authors / Sponsors -->
            <?php if (!empty($authors)): ?>
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-base font-semibold text-gray-900">Authors &amp; Sponsors</h2>
                <div class="mt-4 divide-y divide-gray-100">
                    <?php foreach (['AUTHOR', 'CO_AUTHOR'] as $role): ?>
                        <?php
                        $roleAuthors = array_values(array_filter(
                            $authors,
                            static fn(array $author): bool => $author['author_role'] === $role
                        ));
                        ?>
                        <?php if (!empty($roleAuthors)): ?>
                            <?php if (!empty($roleAuthors[0]['is_all_selected'])): ?>
                                <div class="flex items-center justify-between py-2.5">
                                    <p class="text-sm text-gray-900">ALL</p>
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold <?= $role === 'CO_AUTHOR' ? 'bg-purple-50 text-purple-700' : 'bg-blue-50 text-blue-700' ?>">
                                        <?= $role === 'CO_AUTHOR' ? 'Co-Author' : 'Author' ?>
                                    </span>
                                </div>
                            <?php else: ?>
                                <ol class="list-decimal space-y-1 py-2.5 pl-5">
                                    <?php foreach ($roleAuthors as $author): ?>
                                        <li class="flex items-center justify-between">
                                            <p class="text-sm text-gray-900"><?= htmlspecialchars($author['full_name']) ?></p>
                                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold <?= $role === 'CO_AUTHOR' ? 'bg-purple-50 text-purple-700' : 'bg-blue-50 text-blue-700' ?>">
                                                <?= $role === 'CO_AUTHOR' ? 'Co-Author' : 'Author' ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- Attachments -->
            <?php if (!empty($allAttachments)): ?>
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-base font-semibold text-gray-900">Document Attachments</h2>
                <p class="mt-1 text-xs text-gray-500">All files uploaded across every workflow phase.</p>
                <ul class="mt-4 divide-y divide-gray-100">
                    <?php foreach ($allAttachments as $att): ?>
                        <li class="flex items-center justify-between gap-4 py-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-400">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm text-gray-800">
                                        <?= htmlspecialchars($att['file_name']) ?>
                                    </p>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="text-[11px] text-gray-400">
                                            <?= htmlspecialchars(_ss_phaseLabel($att['phase'])) ?>
                                        </span>
                                        <span class="text-gray-300">·</span>
                                        <span class="text-[11px] text-gray-400">
                                            <?= htmlspecialchars(_ss_typeLabel($att['attachment_type'])) ?>
                                        </span>
                                        <span class="text-gray-300">·</span>
                                        <span class="text-[11px] text-gray-400">
                                            <?= htmlspecialchars(_ss_formatBytes((int)$att['file_size'])) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <a href="<?= BASE_URL ?>/admin/filed/source-attachment?attachment_id=<?= (int) $att['id'] ?>&amp;document_id=<?= (int) $document['id'] ?>"
                               data-attachment-preview
                               data-preview-url="<?= BASE_URL ?>/admin/filed/source-attachment?attachment_id=<?= (int) $att['id'] ?>&amp;document_id=<?= (int) $document['id'] ?>&amp;mode=raw"
                               data-download-url="<?= BASE_URL ?>/admin/filed/source-attachment?attachment_id=<?= (int) $att['id'] ?>&amp;document_id=<?= (int) $document['id'] ?>&amp;mode=download"
                               data-preview-name="<?= htmlspecialchars($att['file_name']) ?>"
                               target="_blank" rel="noopener"
                               class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-blue-200
                                      bg-blue-50 px-3 py-1.5 text-xs font-medium text-primary
                                      hover:bg-blue-100 transition"
                               title="View attachment">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M2.25 12s3.5-6.75 9.75-6.75S21.75 12 21.75 12s-3.5 6.75-9.75 6.75S2.25 12 2.25 12z"/>
                                    <circle cx="12" cy="12" r="3" stroke-width="2"/>
                                </svg>
                                View
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>

        </div><!-- /main col -->

        <!-- ── Sidebar column ───────────────────────────────────────────── -->
        <div class="space-y-6">

            <!-- Source Information -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-base font-semibold text-gray-900">Source Information</h2>
                <div class="mt-4 space-y-3">

                    <div>
                        <p class="text-xs font-medium text-gray-500">Source Type</p>
                        <p class="mt-1 text-sm text-gray-900">
                            <?= htmlspecialchars($document['source_type'] ?? '—') ?>
                        </p>
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
                        </p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($document['source_name'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Source Name</p>
                        <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($document['source_name']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Phase / Workflow -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-base font-semibold text-gray-900">Workflow Phase</h2>
                <div class="mt-3">
                    <span class="inline-flex items-center rounded-lg bg-gray-50 border border-gray-200
                                 px-3 py-1.5 text-sm font-medium text-gray-700">
                        <?= htmlspecialchars(_ss_phaseLabel($document['current_phase'])) ?>
                    </span>
                </div>
            </section>

            <!-- Audit -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-base font-semibold text-gray-900">Audit</h2>
                <div class="mt-4 space-y-3">
                    <?php if (!empty($document['created_by_username'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Created By</p>
                        <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($document['created_by_username']) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($document['created_at'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Created At</p>
                        <p class="mt-1 text-sm text-gray-900">
                            <?= htmlspecialchars(date('M j, Y g:i A', strtotime($document['created_at']))) ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

        </div><!-- /sidebar col -->
    </div>

</div>

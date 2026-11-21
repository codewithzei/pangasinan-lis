<?php
// Variables injected by AdminFiledDocumentsController::show()
$document          = $document          ?? [];
$authors           = $authors           ?? [];
$filingAttachments = $filingAttachments ?? [];
$allAttachments    = $allAttachments    ?? [];
$pageTitle         = $pageTitle         ?? 'Filed Document Details';
$pageSubtitle      = $pageSubtitle      ?? '';

$statusBadgeColor = !empty($document['status_badge_color']) ? $document['status_badge_color'] : '#6B7280';
$typeColor        = !empty($document['document_type_badge_color']) ? $document['document_type_badge_color'] : '#2563EB';
$tags             = $document['tags'] ?? [];

function _formatBytes(int $bytes): string
{
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)    return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}

function _phaseLabel(string $phase): string
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

function _typeLabel(string $type): string
{
    return [
        'RECEIVING_FILE'  => 'Receiving File',
        'ADMIN_FILE'      => 'Admin File',
        'SPSEC_FILE'      => 'SP Secretary File',
        'COMMITTEE_FILE'  => 'Committee File',
        'OPINION_FILE'    => 'Opinion File',
        'COMPLIANCE_FILE' => 'Compliance File',
        'COMMITTEE_REPORT'=> 'Committee Report',
        'APPROVAL_FILE'   => 'Approval File',
        'FILING_FILE'     => 'Filing File',
        'OTHER'           => 'Other',
    ][$type] ?? $type;
}
?>

<div class="detail-page space-y-6">



    <!-- ── Page header ──────────────────────────────────────────────────────── -->
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
                        <p class="text-sm font-medium text-blue-100">FILED DOCUMENT</p>
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

    <!-- ── Two-column layout ────────────────────────────────────────────────── -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <!-- ── Main column ──────────────────────────────────────────────────── -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Document Information -->
            <?php $resubmissionTopMargin = 'mt-0'; ?>
            <?php require __DIR__ . '/../../partials/resubmission-relationship.php'; ?>
            <?php unset($resubmissionTopMargin); ?>

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
                        <p class="mt-1 text-sm text-gray-900 whitespace-pre-wrap"><?= escape_trimmed_text($document['subject_matter']) ?></p>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-gray-500">Document Type</p>
                        <span class="mt-1 inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                              style="background-color:<?= htmlspecialchars($typeColor) ?>1a; color:<?= htmlspecialchars($typeColor) ?>;">
                            <?= htmlspecialchars($document['document_type_name'] ?? '—') ?>
                        </span>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-gray-500">Status</p>
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

                    <?php if (!empty($document['filed_at'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Filed At</p>
                        <p class="mt-1 text-sm text-gray-900">
                            <?= htmlspecialchars(date('F j, Y g:i A', strtotime($document['filed_at']))) ?>
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

            <!-- Source Information -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-base font-semibold text-gray-900">Source Information</h2>
                <div class="mt-4 space-y-3">
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

            <!-- Filing Attachments -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-base font-semibold text-gray-900">Final Scanned Attachments</h2>
                <?php if (empty($filingAttachments)): ?>
                    <p class="mt-4 text-sm text-gray-500">No filing attachments on record.</p>
                <?php else: ?>
                    <ul class="mt-4 divide-y divide-gray-100">
                        <?php foreach ($filingAttachments as $att): ?>
                            <li class="flex items-center justify-between py-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <!-- File type icon -->
                                    <?php
                                    $ext = strtolower(pathinfo($att['file_name'], PATHINFO_EXTENSION));
                                    $iconClass = match(true) {
                                        $ext === 'pdf'                => 'text-red-400',
                                        in_array($ext, ['doc','docx'])=> 'text-blue-400',
                                        in_array($ext, ['xls','xlsx'])=> 'text-emerald-400',
                                        in_array($ext, ['jpg','jpeg','png','gif','webp']) => 'text-violet-400',
                                        default                       => 'text-gray-400',
                                    };
                                    ?>
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 <?= $iconClass ?>">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($att['file_name']) ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <?= htmlspecialchars(_formatBytes((int)$att['file_size'])) ?>
                                            · <?= htmlspecialchars(date('M j, Y', strtotime($att['created_at']))) ?>
                                        </p>
                                    </div>
                                </div>
                                <a href="<?= BASE_URL ?>/admin/filed/download?attachment_id=<?= (int)$att['id'] ?>&document_id=<?= (int)$document['id'] ?>"
                                   data-attachment-preview
                                   data-preview-url="<?= BASE_URL ?>/admin/filed/download?attachment_id=<?= (int)$att['id'] ?>&amp;document_id=<?= (int)$document['id'] ?>&amp;mode=inline"
                                   data-preview-name="<?= htmlspecialchars($att['file_name']) ?>"
                                   class="ml-4 shrink-0 inline-flex items-center gap-1.5 rounded-lg border border-gray-200
                                          px-3 py-1.5 text-xs font-medium text-gray-700
                                          hover:border-emerald-400 hover:bg-emerald-50 hover:text-emerald-700 transition"
                                   title="Download">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                    Download
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <!-- All Document Attachments (full history) -->
            <?php if (!empty($allAttachments)): ?>
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">All Document Attachments</h2>
                        <p class="mt-1 text-xs text-gray-500">Files uploaded across every workflow phase for this document.</p>
                    </div>
                    <span class="inline-flex w-fit items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">
                        <?= number_format(count($allAttachments)) ?> file<?= count($allAttachments) !== 1 ? 's' : '' ?>
                    </span>
                </div>
                <ul class="mt-4 space-y-3">
                    <?php foreach ($allAttachments as $att): ?>
                        <?php
                        $attachmentExtension = strtolower(pathinfo($att['file_name'], PATHINFO_EXTENSION));
                        $attachmentIconClass = match (true) {
                            $attachmentExtension === 'pdf' => 'bg-red-50 text-red-500',
                            in_array($attachmentExtension, ['doc', 'docx'], true) => 'bg-blue-50 text-blue-500',
                            in_array($attachmentExtension, ['xls', 'xlsx'], true) => 'bg-emerald-50 text-emerald-600',
                            in_array($attachmentExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) => 'bg-violet-50 text-violet-500',
                            default => 'bg-gray-100 text-gray-500',
                        };
                        ?>
                        <li class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-3 transition
                                   hover:border-gray-300 hover:bg-gray-50/50 sm:flex-row sm:items-center sm:justify-between sm:p-4">
                            <div class="flex min-w-0 items-start gap-3 sm:items-center">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl <?= $attachmentIconClass ?>">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="break-all text-sm font-medium leading-5 text-gray-900 sm:break-words">
                                        <?= htmlspecialchars($att['file_name']) ?>
                                    </p>
                                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                        <span class="inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700">
                                            <?= htmlspecialchars(_phaseLabel($att['phase'])) ?>
                                        </span>
                                        <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600">
                                            <?= htmlspecialchars(_typeLabel($att['attachment_type'])) ?>
                                        </span>
                                        <span class="text-[11px] text-gray-500">
                                            <?= htmlspecialchars(_formatBytes((int)$att['file_size'])) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <a href="<?= BASE_URL ?>/admin/filed/download?attachment_id=<?= (int)$att['id'] ?>&document_id=<?= (int)$document['id'] ?>"
                               data-attachment-preview
                               data-preview-url="<?= BASE_URL ?>/admin/filed/download?attachment_id=<?= (int)$att['id'] ?>&amp;document_id=<?= (int)$document['id'] ?>&amp;mode=inline"
                               data-preview-name="<?= htmlspecialchars($att['file_name']) ?>"
                               class="inline-flex w-full shrink-0 items-center justify-center gap-1.5 rounded-lg border border-gray-200
                                      bg-white px-3 py-2 text-xs font-medium text-gray-700 transition
                                      hover:border-emerald-400 hover:bg-emerald-50 hover:text-emerald-700 sm:ml-4 sm:w-auto"
                               title="Download">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                Download
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>

            <?php require __DIR__ . '/../../partials/document-action-timeline.php'; ?>

        </div><!-- /main col -->

        <!-- ── Sidebar column ───────────────────────────────────────────────── -->
        <div class="space-y-6">

            <!-- Filing Details -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-base font-semibold text-gray-900">Filing Details</h2>

                <div class="mt-4 space-y-4">

                    <!-- Public / private badge -->
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-medium text-gray-500">Visibility</p>
                        <?php if (!empty($document['filing_is_public'])): ?>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"/>
                                </svg>
                                Public
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                Private
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($document['filing_description'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Filing Description</p>
                        <p class="mt-1 text-sm text-gray-900 whitespace-pre-wrap"><?= escape_trimmed_text($document['filing_description']) ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Tags -->
                    <?php if (!empty($tags)): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Tags</p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <?php foreach ($tags as $tag): ?>
                                <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">
                                    <?= htmlspecialchars($tag) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($document['filed_by_username'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Filed By</p>
                        <p class="mt-1 text-sm text-gray-900">
                            <?= htmlspecialchars($document['filed_by_username']) ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($document['filing_filed_at'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Filed On</p>
                        <p class="mt-1 text-sm text-gray-900">
                            <?= htmlspecialchars(date('F j, Y g:i A', strtotime($document['filing_filed_at']))) ?>
                        </p>
                    </div>
                    <?php endif; ?>

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

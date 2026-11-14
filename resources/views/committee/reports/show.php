<?php
/**
 * Committee — Committee Report Detail (from Reports page)
 *
 * Variables supplied by CommitteeReportsController::show():
 *   $report                    array       committee_reports row + creator info
 *   $reportDocuments           array       [{tracking_number, subject_matter, status, is_returned, …}]
 *   $reportCommittees          array       [{id, name}]
 *   $attachmentsByDocument     array       document_id => document_attachments rows
 *   $reportStatus              string      Overall shared-report progress
 *   $agenda                    array|null
 *   $hearing                   array|null
 *   $fromReports               bool        true = came from /committee/reports
 *   $success                   string|null
 *   $error                     string|null
 */

$report                   = $report                   ?? [];
$reportDocuments          = $reportDocuments          ?? [];
$reportCommittees         = $reportCommittees         ?? [];
$attachmentsByDocument    = $attachmentsByDocument    ?? [];
$reportAttachments        = $reportAttachments        ?? [];
$reportAttachmentCount    = $reportAttachmentCount    ?? count($reportAttachments);
$reportStatus             = $reportStatus             ?? 'Committee Report Created';
$agenda                   = $agenda                   ?? null;
$hearing                  = $hearing                  ?? null;
$fromReports              = $fromReports              ?? true;

$reportId   = (int) ($report['id'] ?? 0);
$isJoint    = ($report['report_type'] ?? '') === 'JOINT_COMMITTEE_REPORT';
$typeLabel  = $isJoint ? 'Joint Committee Report' : 'Committee Report';

$attachmentCount = array_sum(array_map('count', $attachmentsByDocument));
$returnedItemCount = 0;
foreach ($reportDocuments as $reportDocument) {
    $documentAttachments = $attachmentsByDocument[(int) $reportDocument['id']] ?? [];
    if (!empty($reportDocument['is_returned'])) {
        $returnedItemCount += 1 + count($documentAttachments);
    } else {
        $returnedItemCount += count(array_filter($documentAttachments, static fn(array $attachment): bool => !empty($attachment['transmitted_at'])));
    }
}
$totalItemCount = count($reportDocuments) + $attachmentCount;
$reportStatusClass = match ($reportStatus) {
    'Returned to Plenary' => 'bg-violet-100 text-violet-800',
    'Partially Returned' => 'bg-amber-100 text-amber-800',
    default => 'bg-emerald-100 text-emerald-800',
};

ob_start();
?>

<div class="space-y-6">

    <!-- ── Page header ─────────────────────────────────────────────────────── -->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-7 sm:px-8">
            <div class="relative z-10">
                <p class="text-sm font-medium text-blue-100">
                    COMMITTEE / COMMITTEE REPORTS / <?= strtoupper($typeLabel) ?>
                </p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    <?= htmlspecialchars($typeLabel) ?>
                    <?php if (!empty($report['report_number'])): ?>
                        <span class="ml-2 text-blue-100">#<?= htmlspecialchars($report['report_number']) ?></span>
                    <?php endif; ?>
                </h1>
                <p class="mt-1 text-sm text-blue-100">
                    Created <?= !empty($report['created_at'])
                        ? htmlspecialchars(date('F j, Y', strtotime($report['created_at'])))
                        : '—' ?>
                    by <?= htmlspecialchars($report['created_by_name'] ?? $report['created_by_username'] ?? '—') ?>
                </p>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-56 w-56 rounded-full bg-white/10"></div>
        </div>
    </section>

    <!-- ── Breadcrumb ───────────────────────────────────────────────────────── -->
    <nav class="flex items-center gap-2 text-sm text-gray-500">
        <a href="<?= BASE_URL ?>/committee/reports" class="hover:text-primary transition">Committee Reports</a>
        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="font-medium text-gray-700">
            <?= htmlspecialchars($typeLabel) ?>
            <?= !empty($report['report_number']) ? '#' . htmlspecialchars($report['report_number']) : '' ?>
        </span>
    </nav>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

        <!-- ── Left: metadata sidebar ──────────────────────────────────────── -->
        <div class="xl:col-span-1 space-y-4">

            <!-- Report metadata -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Report Info</h2>

                <div class="space-y-2 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-gray-500">Report Number</span>
                        <span class="font-semibold text-gray-900">
                            <?= htmlspecialchars($report['report_number'] ?? '—') ?>
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-gray-500">Type</span>
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
                                     <?= $isJoint ? 'bg-purple-100 text-purple-800' : 'bg-emerald-100 text-emerald-800' ?>">
                            <?= htmlspecialchars($typeLabel) ?>
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-gray-500">Created</span>
                        <span class="text-gray-900 text-xs">
                            <?= !empty($report['created_at'])
                                ? htmlspecialchars(date('M j, Y g:i A', strtotime($report['created_at'])))
                                : '—' ?>
                        </span>
                    </div>
                    <div class="flex items-start justify-between gap-2">
                        <span class="text-gray-500 shrink-0">Created By</span>
                        <span class="text-gray-900 text-xs text-right">
                            <?= htmlspecialchars($report['created_by_name'] ?? $report['created_by_username'] ?? '—') ?>
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-2 pt-1 border-t border-gray-100">
                        <span class="text-gray-500">Overall Status</span>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold <?= $reportStatusClass ?>">
                            <?= htmlspecialchars($reportStatus) ?>
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-gray-500">Items Returned</span>
                        <span class="text-xs font-medium text-gray-800"><?= $returnedItemCount ?> / <?= $totalItemCount ?></span>
                    </div>
                </div>
            </div>

            <!-- Committee referral -->
            <?php if (!empty($reportCommittees)): ?>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-2">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                    Committee Referral
                </h2>
                <ul class="space-y-1">
                    <?php foreach ($reportCommittees as $comm): ?>
                        <li class="flex items-center gap-2 text-sm text-gray-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                            <?= htmlspecialchars($comm['name']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Agenda info -->
            <?php if ($agenda): ?>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-2">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Agenda</h2>
                <?php if (!empty($agenda['agenda_number'])): ?>
                    <p class="text-xs font-semibold text-gray-700">
                        #<?= htmlspecialchars($agenda['agenda_number']) ?>
                    </p>
                <?php endif; ?>
                <?php if (!empty($agenda['agenda_date'])): ?>
                    <p class="text-xs text-gray-600">
                        <?= htmlspecialchars(date('F j, Y', strtotime($agenda['agenda_date']))) ?>
                        <?= !empty($agenda['agenda_time'])
                            ? 'at ' . htmlspecialchars(date('g:i A', strtotime($agenda['agenda_time'])))
                            : '' ?>
                    </p>
                <?php endif; ?>
                <?php if (!empty($agenda['committee_names'])): ?>
                    <p class="text-xs text-gray-500"><?= htmlspecialchars($agenda['committee_names']) ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>

        <!-- ── Right: main content ─────────────────────────────────────────── -->
        <div class="xl:col-span-2 space-y-5">

            <!-- Documents covered -->
            <?php if (!empty($reportDocuments)): ?>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
                <h2 class="text-sm font-semibold text-gray-900">
                    Document<?= count($reportDocuments) > 1 ? 's' : '' ?> Covered
                    <span class="ml-1 text-xs font-normal text-gray-400">(<?= count($reportDocuments) ?>)</span>
                </h2>
                <ul class="space-y-3">
                    <?php foreach ($reportDocuments as $doc): ?>
                        <?php $documentDetailUrl = BASE_URL . '/committee/reports/document-show?id=' . (int) $doc['id'] . '&report_id=' . $reportId; ?>
                        <li class="flex flex-col gap-3 rounded-xl border border-gray-100 bg-gray-50 p-3">
                            <div class="flex items-start gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-mono text-xs font-bold text-blue-700">
                                        <?= htmlspecialchars($doc['tracking_number']) ?>
                                    </span>
                                    <?php if (!empty($doc['document_type_name'])): ?>
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5
                                                     text-xs font-medium text-white"
                                              style="background-color:<?= htmlspecialchars($doc['document_type_badge_color'] ?? '#6B7280') ?>">
                                            <?= htmlspecialchars($doc['document_type_name']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($doc['status'])): ?>
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5
                                                     text-xs font-medium text-white"
                                              style="background-color:<?= htmlspecialchars($doc['status_badge_color'] ?? '#6B7280') ?>">
                                            <?= htmlspecialchars($doc['status']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <p class="mt-1 text-xs text-gray-700 line-clamp-2">
                                    <?= htmlspecialchars($doc['subject_matter'] ?? '—') ?>
                                </p>
                            </div>
                                <div class="flex shrink-0 flex-col items-stretch gap-2">
                                    <a href="<?= htmlspecialchars($documentDetailUrl) ?>"
                                       class="inline-flex w-40 items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700 transition"
                                       title="View document details">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        View Details
                                    </a>
                                    <?php if (!empty($doc['is_returned'])): ?>
                                        <span class="inline-flex items-center rounded-full bg-violet-100 px-2 py-1 text-xs font-medium text-violet-800">
                                            Returned <?= !empty($doc['transmitted_at']) ? htmlspecialchars(date('M j, Y g:i A', strtotime($doc['transmitted_at']))) : '' ?>
                                        </span>
                                    <?php else: ?>
                                        <button type="button"
                                                class="return-document-button inline-flex w-40 items-center justify-center gap-1 rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-violet-700"
                                                data-document-id="<?= (int) $doc['id'] ?>"
                                                data-document-tracking="<?= htmlspecialchars($doc['tracking_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                            Return to Plenary
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!empty($doc['is_returned']) && !empty($doc['transmitted_at'])): ?>
                                <p class="text-xs text-violet-700">Transmitted <?= htmlspecialchars(date('M j, Y g:i A', strtotime($doc['transmitted_at']))) ?></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Summary of Findings -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
                <h2 class="text-sm font-semibold text-gray-900">Summary of Findings</h2>
                <?php if (!empty($report['summary_of_findings'])): ?>
                    <div class="prose prose-sm max-w-none text-gray-700 whitespace-pre-wrap text-sm leading-relaxed">
                        <?= htmlspecialchars($report['summary_of_findings']) ?>
                    </div>
                <?php else: ?>
                    <p class="text-sm text-gray-400 italic">No summary provided.</p>
                <?php endif; ?>
            </div>

            <!-- Attachments belonging to individual documents -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
                <h2 class="text-sm font-semibold text-gray-900">
                    Document Attachments
                    <span class="ml-1 text-xs font-normal text-gray-400">(<?= $attachmentCount ?>)</span>
                </h2>
                <?php if ($attachmentCount === 0): ?>
                    <p class="text-sm text-gray-400 italic">No document-level attachments.</p>
                <?php else: ?>
                    <ul class="space-y-2">
                        <?php foreach ($reportDocuments as $doc): ?>
                        <?php foreach ($attachmentsByDocument[(int) $doc['id']] ?? [] as $att): ?>
                            <?php
                            $fileReturned = !empty($doc['is_returned']) || !empty($att['transmitted_at']);
                            $fileReturnedAt = $att['transmitted_at'] ?? $doc['transmitted_at'] ?? null;
                            ?>
                            <li class="flex items-center justify-between gap-3 rounded-xl border border-gray-100
                                        bg-gray-50 px-4 py-3">
                                <div class="flex items-center gap-2 min-w-0">
                                    <svg class="h-5 w-5 shrink-0 text-gray-400" fill="none"
                                         stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                    </svg>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-gray-800">
                                            <?= htmlspecialchars($att['file_name']) ?>
                                        </p>
                                        <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-medium
                                                     <?= $fileReturned ? 'bg-violet-100 text-violet-800' : 'bg-amber-100 text-amber-800' ?>">
                                            <?= $fileReturned ? 'Returned with document' : 'In Committee Report' ?>
                                        </span>
                                        <p class="text-xs text-gray-400">
                                            <?= number_format((int)($att['file_size'] ?? 0) / 1024, 1) ?> KB
                                            · <?= htmlspecialchars(date('M j, Y', strtotime($att['created_at']))) ?>
                                            · <?= htmlspecialchars($att['uploaded_by_username'] ?? '—') ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <?= htmlspecialchars($doc['tracking_number']) ?>
                                            · <?= htmlspecialchars($att['attachment_type']) ?>
                                        </p>
                                        <?php if ($fileReturned && !empty($fileReturnedAt)): ?>
                                            <p class="text-xs font-medium text-violet-700">
                                                Returned <?= htmlspecialchars(date('M j, Y g:i A', strtotime($fileReturnedAt))) ?>
                                                <?= !empty($att['transmitted_by']) ? '· ' . htmlspecialchars($att['transmitted_by']) : '' ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <a href="<?= BASE_URL ?>/public/<?= htmlspecialchars($att['stored_path']) ?>"
                                       target="_blank" rel="noopener"
                                       class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 transition">
                                        Download
                                    </a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <!-- Files attached directly to the shared report -->
            <div class="rounded-2xl border border-emerald-200 bg-white p-5 space-y-3">
                <h2 class="text-sm font-semibold text-gray-900">
                    Committee Report Attachments
                    <span class="ml-1 text-xs font-normal text-gray-400">(<?= (int) $reportAttachmentCount ?>)</span>
                </h2>
                <?php if (empty($reportAttachments)): ?>
                    <p class="text-sm text-gray-400 italic">No Committee Report attachments.</p>
                <?php else: ?>
                    <ul class="space-y-2">
                        <?php foreach ($reportAttachments as $attachment): ?>
                            <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-100 bg-emerald-50/40 px-4 py-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <svg class="h-5 w-5 shrink-0 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                    </svg>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-gray-800"><?= htmlspecialchars($attachment['file_name']) ?></p>
                                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 font-medium text-emerald-800">Committee Report Attachment</span>
                                            <span><?= number_format((int) $attachment['file_size'] / 1024, 1) ?> KB</span>
                                            <span><?= htmlspecialchars(date('M j, Y', strtotime($attachment['created_at']))) ?></span>
                                            <span><?= htmlspecialchars($attachment['uploaded_by_username'] ?? '—') ?></span>
                                        </div>
                                    </div>
                                </div>
                                <form method="POST" action="<?= BASE_URL ?>/committee/reports/attachments"
                                      onsubmit="return confirm('Remove this Committee Report attachment?')">
                                    <input type="hidden" name="report_id" value="<?= $reportId ?>">
                                    <input type="hidden" name="attachment_action" value="remove">
                                    <input type="hidden" name="attachment_id" value="<?= (int) $attachment['id'] ?>">
                                    <button type="submit" class="rounded-lg border border-red-200 bg-white px-2.5 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">
                                        Remove
                                    </button>
                                </form>
                                <form method="POST" action="<?= BASE_URL ?>/committee/reports/attachments"
                                      enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                                    <input type="hidden" name="report_id" value="<?= $reportId ?>">
                                    <input type="hidden" name="attachment_action" value="replace">
                                    <input type="hidden" name="attachment_id" value="<?= (int) $attachment['id'] ?>">
                                    <label class="cursor-pointer rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">
                                        Replace file
                                        <input type="file" name="replacement" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp" required class="sr-only report-attachment-replacement-input">
                                    </label>
                                </form>
                                <a href="<?= BASE_URL ?>/public/<?= htmlspecialchars($attachment['stored_path']) ?>"
                                   target="_blank" rel="noopener"
                                   class="inline-flex shrink-0 items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 transition">
                                    Preview
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/committee/reports/attachments"
                      enctype="multipart/form-data" class="border-t border-gray-100 pt-4">
                    <input type="hidden" name="report_id" value="<?= $reportId ?>">
                    <input type="hidden" name="attachment_action" value="add">
                    <label class="block text-xs font-semibold text-gray-700">
                        Add latest signed or supporting files
                    </label>
                    <div id="reportAttachmentDropZone"
                         class="mt-2 flex cursor-pointer flex-col items-center justify-center rounded-xl border-2
                                border-dashed border-gray-300 bg-gray-50 px-5 py-6 text-center transition
                                hover:border-emerald-400 hover:bg-emerald-50/50"
                         role="button" tabindex="0" aria-label="Choose or drop Committee Report attachments">
                        <svg class="h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <p class="mt-2 text-sm font-medium text-gray-700">
                            Drop files here or
                            <button type="button" id="reportAttachmentBrowse" class="text-emerald-800 underline hover:text-emerald-900">browse</button>
                        </p>
                        <p class="mt-1 text-xs text-gray-400">PDF, Word, Excel, or images · Up to 10 files · 25 MB each</p>
                        <input type="file" name="attachments[]" id="reportAttachmentInput" multiple
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp" class="sr-only">
                    </div>
                    <div id="reportAttachmentErrors" class="mt-2 hidden rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700" role="alert"></div>
                    <ul id="reportAttachmentPreviewList" class="mt-2 space-y-2" aria-live="polite"></ul>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                        <p class="text-xs text-gray-400">Files belong to this Committee Report, not an individual document.</p>
                        <button type="submit" id="reportAttachmentSaveButton" disabled class="rounded-lg bg-emerald-700 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50">
                            Save
                        </button>
                    </div>
                </form>
            </div>

            <!-- ── Action bar ──────────────────────────────────────────────── -->
            <div class="flex flex-wrap items-center gap-3">
                <a href="<?= BASE_URL ?>/committee/reports"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white
                          px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Reports
                </a>

            </div>

        </div>
    </div>
</div>

<div id="returnDocumentModal"
     class="fixed inset-0 z-[100] hidden items-center justify-center"
     role="dialog"
     aria-modal="true"
     aria-labelledby="returnDocumentModalTitle">
    <div class="return-document-modal-backdrop absolute inset-0 bg-gray-900/50 backdrop-blur-sm"></div>
    <div class="relative z-10 mx-4 w-full max-w-md rounded-2xl border border-gray-100 bg-white shadow-2xl">
        <div class="p-6">
            <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-violet-100">
                    <svg class="h-6 w-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <h2 id="returnDocumentModalTitle" class="text-lg font-semibold text-gray-900">
                        Return to Plenary
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Return this document to Plenary?
                    </p>
                    <p id="returnDocumentTracking" class="mt-2 font-mono text-sm font-bold text-blue-700"></p>
                </div>
            </div>
        </div>
        <div class="flex items-center justify-end gap-3 rounded-b-2xl border-t border-gray-100 bg-gray-50 px-6 py-4">
            <button type="button" id="cancelReturnDocument"
                    class="inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Cancel
            </button>
            <form id="returnDocumentForm" method="POST" action="<?= BASE_URL ?>/committee/reports/return-to-plenary">
                <input type="hidden" name="report_id" value="<?= $reportId ?>">
                <input type="hidden" name="item_type" value="DOCUMENT">
                <input type="hidden" name="item_id" id="returnDocumentId" value="">
                <button type="submit" id="confirmReturnDocument"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-violet-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">
                    Yes, Return to Plenary
                </button>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('returnDocumentModal');
    var form = document.getElementById('returnDocumentForm');
    var itemIdInput = document.getElementById('returnDocumentId');
    var trackingNumber = document.getElementById('returnDocumentTracking');
    var confirmButton = document.getElementById('confirmReturnDocument');
    var cancelButton = document.getElementById('cancelReturnDocument');
    var activeButton = null;
    var submitted = false;

    function closeModal() {
        if (modal.classList.contains('hidden')) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        itemIdInput.value = '';
        if (activeButton) activeButton.focus();
        activeButton = null;
    }

    document.querySelectorAll('.return-document-button').forEach(function (button) {
        button.addEventListener('click', function () {
            activeButton = button;
            submitted = false;
            itemIdInput.value = button.dataset.documentId;
            trackingNumber.textContent = button.dataset.documentTracking;
            confirmButton.disabled = false;
            confirmButton.textContent = 'Yes, Return to Plenary';
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            cancelButton.focus();
        });
    });

    cancelButton.addEventListener('click', closeModal);
    modal.querySelector('.return-document-modal-backdrop').addEventListener('click', closeModal);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeModal();
    });
    form.addEventListener('submit', function (event) {
        if (submitted) {
            event.preventDefault();
            return;
        }
        submitted = true;
        confirmButton.disabled = true;
        confirmButton.textContent = 'Returning…';
    });
}());
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const dropZone = document.getElementById('reportAttachmentDropZone');
    const fileInput = document.getElementById('reportAttachmentInput');
    const browseButton = document.getElementById('reportAttachmentBrowse');
    const previewList = document.getElementById('reportAttachmentPreviewList');
    const errorBox = document.getElementById('reportAttachmentErrors');
    const saveButton = document.getElementById('reportAttachmentSaveButton');
    const uploadForm = fileInput ? fileInput.form : null;
    if (!dropZone || !fileInput || !previewList || !errorBox || !saveButton || !uploadForm) return;

    document.querySelectorAll('.report-attachment-replacement-input').forEach(function (replacementInput) {
        replacementInput.addEventListener('change', function () {
            if (this.files && this.files.length > 0) this.form.requestSubmit();
        });
    });

    const allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
    const maxFileSize = 25 * 1024 * 1024;
    const selectedFiles = [];

    function syncInputFiles() {
        const transfer = new DataTransfer();
        selectedFiles.forEach(function (file) { transfer.items.add(file); });
        fileInput.files = transfer.files;
    }

    function renderFiles() {
        previewList.replaceChildren();
        saveButton.disabled = selectedFiles.length === 0;
        selectedFiles.forEach(function (file, index) {
            const row = document.createElement('li');
            row.className = 'flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2';
            const name = document.createElement('span');
            name.className = 'min-w-0 truncate text-sm text-gray-700';
            name.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'shrink-0 rounded px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50';
            remove.textContent = 'Remove';
            remove.setAttribute('aria-label', 'Remove ' + file.name);
            remove.addEventListener('click', function () {
                selectedFiles.splice(index, 1);
                syncInputFiles();
                renderFiles();
            });
            row.append(name, remove);
            previewList.appendChild(row);
        });
    }

    function addFiles(files) {
        const errors = [];
        Array.from(files).forEach(function (file) {
            const extension = file.name.split('.').pop().toLowerCase();
            if (!allowedExtensions.includes(extension)) {
                errors.push(file.name + ': unsupported file type.');
                return;
            }
            if (file.size > maxFileSize) {
                errors.push(file.name + ': exceeds the 25 MB file limit.');
                return;
            }
            if (selectedFiles.some(function (selected) { return selected.name === file.name && selected.size === file.size; })) return;
            if (selectedFiles.length >= 10) {
                errors.push('Maximum 10 files allowed.');
                return;
            }
            selectedFiles.push(file);
        });
        errorBox.textContent = errors.join(' ');
        errorBox.classList.toggle('hidden', errors.length === 0);
        syncInputFiles();
        renderFiles();
    }

    dropZone.addEventListener('click', function (event) {
        if (!event.target.closest('button')) fileInput.click();
    });
    dropZone.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            fileInput.click();
        }
    });
    browseButton.addEventListener('click', function (event) {
        event.stopPropagation();
        fileInput.click();
    });
    fileInput.addEventListener('change', function () { addFiles(this.files); });
    ['dragover', 'dragenter'].forEach(function (eventName) {
        dropZone.addEventListener(eventName, function (event) {
            event.preventDefault();
            dropZone.classList.add('border-emerald-500', 'bg-emerald-50');
        });
    });
    ['dragleave', 'drop'].forEach(function (eventName) {
        dropZone.addEventListener(eventName, function () {
            dropZone.classList.remove('border-emerald-500', 'bg-emerald-50');
        });
    });
    dropZone.addEventListener('drop', function (event) {
        event.preventDefault();
        if (event.dataTransfer) addFiles(event.dataTransfer.files);
    });
    uploadForm.addEventListener('submit', function (event) {
        if (selectedFiles.length === 0) {
            event.preventDefault();
            errorBox.textContent = 'Select at least one attachment to upload.';
            errorBox.classList.remove('hidden');
        }
    });
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

<?php
/**
 * Committee — Committee Report Detail (from Reports page)
 *
 * Variables supplied by CommitteeReportsController::show():
 *   $report                    array       committee_reports row + creator info
 *   $reportDocuments           array       [{tracking_number, subject_matter, …}]
 *   $reportCommittees          array       [{id, name}]
 *   $attachments               array       document_attachments rows (COMMITTEE_REPORT)
 *   $agenda                    array|null
 *   $hearing                   array|null
 *   $committeeReportCreatedId  int         status ID for eligibility guard
 *   $fromReports               bool        true = came from /committee/reports
 *   $success                   string|null
 *   $error                     string|null
 */

$report                   = $report                   ?? [];
$reportDocuments          = $reportDocuments          ?? [];
$reportCommittees         = $reportCommittees         ?? [];
$attachments              = $attachments              ?? [];
$agenda                   = $agenda                   ?? null;
$hearing                  = $hearing                  ?? null;
$committeeReportCreatedId = $committeeReportCreatedId ?? 0;
$fromReports              = $fromReports              ?? true;
$success                  = $success                  ?? null;
$error                    = $error                    ?? null;

$reportId   = (int) ($report['id'] ?? 0);
$isJoint    = ($report['report_type'] ?? '') === 'JOINT_COMMITTEE_REPORT';
$typeLabel  = $isJoint ? 'Joint Committee Report' : 'Committee Report';

// Eligibility: document must be "Committee Report Created" and not yet returned
$primaryStatus   = !empty($reportDocuments[0]['status']) ? $reportDocuments[0]['status'] : '';
$alreadyReturned = !empty($report['returned_to_plenary_at']);
$canReturn       = !$alreadyReturned && $primaryStatus === 'Committee Report Created';

ob_start();
?>

<div class="space-y-6">

    <!-- ── Page header ─────────────────────────────────────────────────────── -->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-700 via-emerald-600 to-teal-700 shadow-md">
        <div class="relative px-6 py-7 sm:px-8">
            <div class="relative z-10">
                <p class="text-sm font-medium text-emerald-100">
                    COMMITTEE / COMMITTEE REPORTS / <?= strtoupper($typeLabel) ?>
                </p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    <?= htmlspecialchars($typeLabel) ?>
                    <?php if (!empty($report['report_number'])): ?>
                        <span class="ml-2 text-emerald-200">#<?= htmlspecialchars($report['report_number']) ?></span>
                    <?php endif; ?>
                </h1>
                <p class="mt-1 text-sm text-emerald-200">
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

    <!-- ── Flash messages ──────────────────────────────────────────────────── -->
    <?php if ($success): ?>
        <div class="flex items-start gap-3 rounded-2xl border border-green-200 bg-green-50 p-4">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <p class="text-sm font-medium text-green-800"><?= htmlspecialchars($success) ?></p>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            <p class="text-sm font-medium text-red-800"><?= htmlspecialchars($error) ?></p>
        </div>
    <?php endif; ?>

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
                    <!-- Current status of the primary document -->
                    <?php if (!empty($primaryStatus)): ?>
                        <div class="flex items-center justify-between gap-2 pt-1 border-t border-gray-100">
                            <span class="text-gray-500">Doc. Status</span>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium text-white"
                                  style="background-color:<?= htmlspecialchars($reportDocuments[0]['status_badge_color'] ?? '#6B7280') ?>">
                                <?= htmlspecialchars($primaryStatus) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    <!-- Returned to Plenary info -->
                    <?php if ($alreadyReturned): ?>
                        <div class="flex items-center justify-between gap-2 pt-1 border-t border-gray-100">
                            <span class="text-gray-500 shrink-0">Returned</span>
                            <span class="text-xs text-violet-700 font-semibold">
                                <?= htmlspecialchars(date('M j, Y', strtotime($report['returned_to_plenary_at']))) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Committees in charge -->
            <?php if (!empty($reportCommittees)): ?>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-2">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                    Committee<?= count($reportCommittees) > 1 ? 's' : '' ?> in Charge
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

            <!-- Hearing info -->
            <?php if ($hearing): ?>
            <div class="rounded-2xl border border-blue-100 bg-blue-50 p-5 space-y-2">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-blue-500">Hearing Record</h2>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5
                                 text-xs font-semibold text-emerald-800">
                        <?= htmlspecialchars($hearing['outcome'] ?? '—') ?>
                    </span>
                </div>
                <?php if (!empty($hearing['performed_at'])): ?>
                    <p class="text-xs text-blue-700">
                        <?= htmlspecialchars(date('F j, Y', strtotime($hearing['performed_at']))) ?>
                    </p>
                <?php endif; ?>
                <?php if (!empty($hearing['performed_by_name']) || !empty($hearing['performed_by_username'])): ?>
                    <p class="text-xs text-blue-700">
                        By <?= htmlspecialchars($hearing['performed_by_name'] ?: $hearing['performed_by_username']) ?>
                    </p>
                <?php endif; ?>
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
                        <li class="flex items-start gap-3 rounded-xl border border-gray-100 bg-gray-50 p-3">
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

            <!-- Attachments -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
                <h2 class="text-sm font-semibold text-gray-900">
                    Attachments
                    <span class="ml-1 text-xs font-normal text-gray-400">(<?= count($attachments) ?>)</span>
                </h2>
                <?php if (empty($attachments)): ?>
                    <p class="text-sm text-gray-400 italic">No attachments for this report.</p>
                <?php else: ?>
                    <ul class="space-y-2">
                        <?php foreach ($attachments as $att): ?>
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
                                        <p class="text-xs text-gray-400">
                                            <?= number_format((int)($att['file_size'] ?? 0) / 1024, 1) ?> KB
                                            · <?= htmlspecialchars(date('M j, Y', strtotime($att['created_at']))) ?>
                                            · <?= htmlspecialchars($att['uploaded_by_username'] ?? '—') ?>
                                        </p>
                                    </div>
                                </div>
                                <a href="<?= BASE_URL ?>/public/<?= htmlspecialchars($att['stored_path']) ?>"
                                   target="_blank" rel="noopener"
                                   class="shrink-0 inline-flex items-center gap-1 rounded-lg border border-gray-200
                                          bg-white px-3 py-1.5 text-xs font-medium text-gray-600
                                          hover:bg-gray-50 transition">
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

                <?php if ($canReturn): ?>
                    <button type="button"
                            onclick="openReturnModal()"
                            class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-5 py-2
                                   text-sm font-semibold text-white hover:bg-violet-700 transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                        </svg>
                        Return to Plenary
                    </button>
                <?php elseif ($alreadyReturned): ?>
                    <span class="inline-flex items-center gap-2 rounded-xl border border-violet-200
                                 bg-violet-50 px-5 py-2 text-sm font-medium text-violet-600 cursor-default">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Returned to Plenary
                        <?php if (!empty($report['returned_to_plenary_at'])): ?>
                            <span class="text-xs text-violet-400">
                                · <?= htmlspecialchars(date('M j, Y', strtotime($report['returned_to_plenary_at']))) ?>
                            </span>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<?php if ($canReturn): ?>
<!-- ── Return to Plenary confirmation modal ────────────────────────────────── -->
<div id="returnModal"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4"
     role="dialog" aria-modal="true" aria-labelledby="returnModalTitle">
    <div class="w-full max-w-md rounded-2xl bg-white shadow-xl">
        <div class="p-6">
            <div class="flex items-start gap-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-violet-100">
                    <svg class="h-5 w-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h2 id="returnModalTitle" class="text-base font-semibold text-gray-900">
                        Return to Plenary
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Are you sure you want to return this document to Plenary?
                    </p>
                    <?php if (!empty($report['report_number'])): ?>
                        <p class="mt-2 font-mono text-sm font-bold text-emerald-700">
                            Report #<?= htmlspecialchars($report['report_number']) ?>
                        </p>
                    <?php endif; ?>
                    <p class="mt-2 text-xs text-gray-400">
                        This will update the document status to
                        <strong class="text-violet-700">Returned to Plenary</strong>
                        and cannot be undone from this page.
                    </p>
                </div>
            </div>
        </div>
        <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
            <button type="button"
                    onclick="closeReturnModal()"
                    id="returnCancelBtn"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white
                           px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                Cancel
            </button>
            <form id="returnForm" method="POST"
                  action="<?= BASE_URL ?>/committee/reports/return-to-plenary">
                <input type="hidden" name="report_id" value="<?= $reportId ?>">
                <button type="submit"
                        id="returnConfirmBtn"
                        class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-5 py-2
                               text-sm font-semibold text-white hover:bg-violet-700 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                    </svg>
                    Yes, Return to Plenary
                </button>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var modal      = document.getElementById('returnModal');
    var confirmBtn = document.getElementById('returnConfirmBtn');
    var cancelBtn  = document.getElementById('returnCancelBtn');
    var submitted  = false;

    window.openReturnModal = function () {
        if (!modal) return;
        submitted = false;
        confirmBtn.disabled = false;
        confirmBtn.innerHTML =
            '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"' +
            ' d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>' +
            ' Yes, Return to Plenary';
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        cancelBtn.focus();
    };

    window.closeReturnModal = function () {
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    };

    document.getElementById('returnForm').addEventListener('submit', function (e) {
        if (submitted) { e.preventDefault(); return; }
        submitted = true;
        confirmBtn.disabled = true;
        confirmBtn.innerHTML =
            '<svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg"' +
            ' fill="none" viewBox="0 0 24 24">' +
            '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
            '<path class="opacity-75" fill="currentColor"' +
            ' d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>' +
            '<span class="ml-2">Returning…</span>';
    });

    modal.addEventListener('click', function (e) {
        if (e.target === modal) { window.closeReturnModal(); }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            window.closeReturnModal();
        }
    });
}());
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

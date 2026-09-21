<?php
/**
 * Committee — Committee Reports List
 *
 * Variables supplied by CommitteeReportsController::index():
 *   $reports                   array   Paginated report rows
 *   $total                     int     Total matching reports
 *   $page                      int     Current page
 *   $totalPages                int     Total pages
 *   $search                    string  Active search string
 *   $type                      string  Active type filter
 *   $status                    string  Active status filter
 *   $dateFrom                  string  Active date-from filter
 *   $dateTo                    string  Active date-to filter
 *   $committeeReportCreatedId  int     Status ID for eligibility check
 *   $success                   string|null
 *   $error                     string|null
 */

$reports                  = $reports                  ?? [];
$total                    = $total                    ?? 0;
$page                     = $page                     ?? 1;
$totalPages               = $totalPages               ?? 1;
$search                   = $search                   ?? '';
$type                     = $type                     ?? '';
$status                   = $status                   ?? '';
$dateFrom                 = $dateFrom                 ?? '';
$dateTo                   = $dateTo                   ?? '';
$committeeReportCreatedId = $committeeReportCreatedId ?? 0;
$success                  = $success                  ?? null;
$error                    = $error                    ?? null;

// Helper: preserve all current filters when building pagination/sort links
function reportsQueryWith(array $overrides = []): string
{
    $base = [
        'search'    => $_GET['search']    ?? '',
        'type'      => $_GET['type']      ?? '',
        'status'    => $_GET['status']    ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to'   => $_GET['date_to']   ?? '',
        'page'      => $_GET['page']      ?? '1',
    ];
    $merged = array_merge($base, $overrides);
    $clean  = array_filter($merged, fn($v) => $v !== '' && $v !== '1' || in_array($v, ['page'], false));
    return http_build_query(array_filter($merged, fn($v) => $v !== ''));
}

ob_start();
?>

<div class="space-y-6">

    <!-- ── Page header ─────────────────────────────────────────────────────── -->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-700 via-emerald-600 to-teal-700 shadow-md">
        <div class="relative px-6 py-7 sm:px-8">
            <div class="relative z-10">
                <p class="text-sm font-medium text-emerald-100">COMMITTEE / COMMITTEE REPORTS</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    Committee Reports
                </h1>
                <p class="mt-1 text-sm text-emerald-200 max-w-xl">
                    Approved hearing reports awaiting plenary action. Use the
                    <strong class="font-semibold text-white">Return to Plenary</strong>
                    action to forward a completed report.
                </p>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-56 w-56 rounded-full bg-white/10"></div>
        </div>
    </section>

    <!-- ── Breadcrumb ───────────────────────────────────────────────────────── -->
    <nav class="flex items-center gap-2 text-sm text-gray-500">
        <a href="<?= BASE_URL ?>/committee/hearing" class="hover:text-primary transition">Committee</a>
        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="font-medium text-gray-700">Committee Reports</span>
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

    <!-- ── Filters ─────────────────────────────────────────────────────────── -->
    <form method="GET" action="<?= BASE_URL ?>/committee/reports"
          class="rounded-2xl border border-gray-200 bg-white p-5">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">

            <!-- Search -->
            <div class="xl:col-span-2">
                <label class="block text-xs font-semibold text-gray-500 mb-1">Search</label>
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                    </svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                           placeholder="Report no., tracking no., subject…"
                           class="block w-full rounded-xl border border-gray-200 pl-9 pr-3 py-2 text-sm
                                  focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                </div>
            </div>

            <!-- Report type -->
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">Report Type</label>
                <select name="type"
                        class="block w-full rounded-xl border border-gray-200 px-3 py-2 text-sm
                               focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                    <option value="">All Types</option>
                    <option value="COMMITTEE_REPORT"
                        <?= $type === 'COMMITTEE_REPORT' ? 'selected' : '' ?>>
                        Committee Report
                    </option>
                    <option value="JOINT_COMMITTEE_REPORT"
                        <?= $type === 'JOINT_COMMITTEE_REPORT' ? 'selected' : '' ?>>
                        Joint Committee Report
                    </option>
                </select>
            </div>

            <!-- Status -->
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">Status</label>
                <select name="status"
                        class="block w-full rounded-xl border border-gray-200 px-3 py-2 text-sm
                               focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                    <option value="">All Statuses</option>
                    <option value="Committee Report Created"
                        <?= $status === 'Committee Report Created' ? 'selected' : '' ?>>
                        Committee Report Created
                    </option>
                    <option value="Returned to Plenary"
                        <?= $status === 'Returned to Plenary' ? 'selected' : '' ?>>
                        Returned to Plenary
                    </option>
                </select>
            </div>

            <!-- Date from -->
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">Created From</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>"
                       class="block w-full rounded-xl border border-gray-200 px-3 py-2 text-sm
                              focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
            </div>

            <!-- Date to -->
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">Created To</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>"
                       class="block w-full rounded-xl border border-gray-200 px-3 py-2 text-sm
                              focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
            </div>

        </div>

        <div class="mt-3 flex items-center gap-3">
            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2
                           text-sm font-semibold text-white hover:bg-emerald-700 transition">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 4a1 1 0 011-1h16a1 1 0 010 2H4a1 1 0 01-1-1zm3 4a1 1 0 011-1h10a1 1 0 010 2H7a1 1 0 01-1-1zm3 4a1 1 0 011-1h4a1 1 0 010 2h-4a1 1 0 01-1-1z"/>
                </svg>
                Filter
            </button>
            <?php if ($search !== '' || $type !== '' || $status !== '' || $dateFrom !== '' || $dateTo !== ''): ?>
                <a href="<?= BASE_URL ?>/committee/reports"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white
                          px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Clear
                </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- ── Results summary ─────────────────────────────────────────────────── -->
    <div class="flex items-center justify-between gap-3">
        <p class="text-sm text-gray-500">
            <?php if ($total === 0): ?>
                No reports found.
            <?php elseif ($total === 1): ?>
                1 report found.
            <?php else: ?>
                <?= number_format($total) ?> reports found.
                <?php if ($totalPages > 1): ?>
                    Page <?= $page ?> of <?= $totalPages ?>.
                <?php endif; ?>
            <?php endif; ?>
        </p>
    </div>

    <!-- ── Report table (desktop) ──────────────────────────────────────────── -->
    <?php if (empty($reports)): ?>
        <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="mt-3 text-sm font-medium text-gray-500">No committee reports yet.</p>
            <p class="mt-1 text-xs text-gray-400">
                Reports appear here once a committee hearing outcome is approved and a report is filed.
            </p>
        </div>
    <?php else: ?>

        <!-- Desktop table -->
        <div class="hidden md:block rounded-2xl border border-gray-200 bg-white overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="px-4 py-3 text-left whitespace-nowrap">Report No.</th>
                            <th class="px-4 py-3 text-left whitespace-nowrap">Type</th>
                            <th class="px-4 py-3 text-left whitespace-nowrap">Committee</th>
                            <th class="px-4 py-3 text-left whitespace-nowrap">Tracking No.</th>
                            <th class="px-4 py-3 text-left">Subject Matter</th>
                            <th class="px-4 py-3 text-left whitespace-nowrap">Outcome</th>
                            <th class="px-4 py-3 text-left whitespace-nowrap">Created</th>
                            <th class="px-4 py-3 text-left whitespace-nowrap">Created By</th>
                            <th class="px-4 py-3 text-left whitespace-nowrap">Status</th>
                            <th class="px-4 py-3 text-left whitespace-nowrap">Att.</th>
                            <th class="px-4 py-3 text-left whitespace-nowrap">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($reports as $rpt): ?>
                            <?php
                            $isJoint          = $rpt['report_type'] === 'JOINT_COMMITTEE_REPORT';
                            $typeLabel        = $isJoint ? 'Joint Committee Report' : 'Committee Report';
                            $isReturnable     = ($committeeReportCreatedId > 0)
                                                && ((int) $rpt['status'] === $committeeReportCreatedId
                                                    || $rpt['status'] === 'Committee Report Created');
                            // More reliable check using status name
                            $isReturnable     = empty($rpt['returned_to_plenary_at'])
                                                && $rpt['status'] === 'Committee Report Created';
                            $reportIdVal      = (int) $rpt['report_id'];
                            ?>
                            <tr class="hover:bg-gray-50 transition">
                                <!-- Report number -->
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="font-mono text-sm font-bold text-emerald-700">
                                        <?= htmlspecialchars($rpt['report_number'] ?? '—') ?>
                                    </span>
                                </td>
                                <!-- Type badge -->
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
                                                 <?= $isJoint ? 'bg-purple-100 text-purple-800' : 'bg-emerald-100 text-emerald-800' ?>">
                                        <?= $isJoint ? 'Joint' : 'Committee' ?>
                                    </span>
                                </td>
                                <!-- Committee -->
                                <td class="px-4 py-3 max-w-[160px]">
                                    <span class="text-xs text-gray-700 line-clamp-2">
                                        <?= htmlspecialchars($rpt['committee_names'] ?? '—') ?>
                                    </span>
                                </td>
                                <!-- Tracking number -->
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="font-mono text-xs font-semibold text-blue-700">
                                        <?= htmlspecialchars($rpt['tracking_number'] ?? '—') ?>
                                    </span>
                                </td>
                                <!-- Subject matter -->
                                <td class="px-4 py-3 max-w-[220px]">
                                    <p class="text-xs text-gray-700 line-clamp-2">
                                        <?= htmlspecialchars($rpt['subject_matter'] ?? '—') ?>
                                    </p>
                                </td>
                                <!-- Hearing outcome -->
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <?php if (!empty($rpt['hearing_outcome'])): ?>
                                        <span class="inline-flex items-center rounded-full bg-emerald-100
                                                     px-2 py-0.5 text-xs font-semibold text-emerald-800">
                                            <?= htmlspecialchars($rpt['hearing_outcome']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs">—</span>
                                    <?php endif; ?>
                                </td>
                                <!-- Created date -->
                                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-600">
                                    <?= !empty($rpt['created_at'])
                                        ? htmlspecialchars(date('M j, Y', strtotime($rpt['created_at'])))
                                        : '—' ?>
                                </td>
                                <!-- Created by -->
                                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-600">
                                    <?= htmlspecialchars($rpt['created_by_name'] ?? $rpt['created_by_username'] ?? '—') ?>
                                </td>
                                <!-- Document status -->
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <?php if (!empty($rpt['status'])): ?>
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5
                                                     text-xs font-medium text-white"
                                              style="background-color:<?= htmlspecialchars($rpt['status_badge_color'] ?? '#6B7280') ?>">
                                            <?= htmlspecialchars($rpt['status']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs">—</span>
                                    <?php endif; ?>
                                </td>
                                <!-- Attachment count -->
                                <td class="px-4 py-3 whitespace-nowrap text-center text-xs text-gray-600">
                                    <?= (int) ($rpt['attachment_count'] ?? 0) ?>
                                </td>
                                <!-- Actions -->
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <!-- View -->
                                        <a href="<?= BASE_URL ?>/committee/reports/show?id=<?= $reportIdVal ?>"
                                           class="inline-flex items-center gap-1 rounded-lg border border-gray-200
                                                  bg-white px-2.5 py-1.5 text-xs font-medium text-gray-600
                                                  hover:bg-gray-50 transition"
                                           title="View Report">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            View
                                        </a>
                                        <!-- Return to Plenary -->
                                        <?php if ($isReturnable): ?>
                                            <button type="button"
                                                    onclick="openReturnModal(<?= $reportIdVal ?>, '<?= htmlspecialchars(addslashes($rpt['report_number'] ?? ''), ENT_QUOTES) ?>')"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-violet-200
                                                           bg-violet-50 px-2.5 py-1.5 text-xs font-medium text-violet-700
                                                           hover:bg-violet-100 transition"
                                                    title="Return to Plenary">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                                </svg>
                                                Return
                                            </button>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5
                                                         text-xs font-medium text-gray-300 cursor-not-allowed"
                                                  title="<?= !empty($rpt['returned_to_plenary_at']) ? 'Already returned to Plenary' : 'Not eligible' ?>">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M5 13l4 4L19 7"/>
                                                </svg>
                                                Returned
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mobile cards -->
        <div class="md:hidden space-y-4">
            <?php foreach ($reports as $rpt): ?>
                <?php
                $isJoint      = $rpt['report_type'] === 'JOINT_COMMITTEE_REPORT';
                $typeLabel    = $isJoint ? 'Joint Committee Report' : 'Committee Report';
                $isReturnable = empty($rpt['returned_to_plenary_at'])
                                && $rpt['status'] === 'Committee Report Created';
                $reportIdVal  = (int) $rpt['report_id'];
                ?>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-4">
                    <!-- Header row -->
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-mono font-bold text-emerald-700">
                                <?= htmlspecialchars($rpt['report_number'] ?? '—') ?>
                            </p>
                            <span class="mt-1 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold
                                         <?= $isJoint ? 'bg-purple-100 text-purple-800' : 'bg-emerald-100 text-emerald-800' ?>">
                                <?= htmlspecialchars($typeLabel) ?>
                            </span>
                        </div>
                        <?php if (!empty($rpt['status'])): ?>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium text-white shrink-0"
                                  style="background-color:<?= htmlspecialchars($rpt['status_badge_color'] ?? '#6B7280') ?>">
                                <?= htmlspecialchars($rpt['status']) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Details -->
                    <div class="space-y-1.5 text-sm">
                        <?php if (!empty($rpt['committee_names'])): ?>
                            <div class="flex gap-2">
                                <span class="shrink-0 text-xs text-gray-400 w-24">Committee</span>
                                <span class="text-xs text-gray-700"><?= htmlspecialchars($rpt['committee_names']) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="flex gap-2">
                            <span class="shrink-0 text-xs text-gray-400 w-24">Tracking No.</span>
                            <span class="font-mono text-xs font-semibold text-blue-700">
                                <?= htmlspecialchars($rpt['tracking_number'] ?? '—') ?>
                            </span>
                        </div>
                        <?php if (!empty($rpt['subject_matter'])): ?>
                            <div class="flex gap-2">
                                <span class="shrink-0 text-xs text-gray-400 w-24">Subject</span>
                                <span class="text-xs text-gray-700 line-clamp-2"><?= htmlspecialchars($rpt['subject_matter']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($rpt['hearing_outcome'])): ?>
                            <div class="flex gap-2 items-center">
                                <span class="shrink-0 text-xs text-gray-400 w-24">Outcome</span>
                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5
                                             text-xs font-semibold text-emerald-800">
                                    <?= htmlspecialchars($rpt['hearing_outcome']) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <div class="flex gap-2">
                            <span class="shrink-0 text-xs text-gray-400 w-24">Created</span>
                            <span class="text-xs text-gray-700">
                                <?= !empty($rpt['created_at'])
                                    ? htmlspecialchars(date('M j, Y', strtotime($rpt['created_at'])))
                                    : '—' ?>
                                · <?= htmlspecialchars($rpt['created_by_name'] ?? $rpt['created_by_username'] ?? '—') ?>
                            </span>
                        </div>
                        <div class="flex gap-2">
                            <span class="shrink-0 text-xs text-gray-400 w-24">Attachments</span>
                            <span class="text-xs text-gray-700"><?= (int) ($rpt['attachment_count'] ?? 0) ?></span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2 pt-1 border-t border-gray-100">
                        <a href="<?= BASE_URL ?>/committee/reports/show?id=<?= $reportIdVal ?>"
                           class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl
                                  border border-gray-200 bg-white px-3 py-2 text-xs font-medium
                                  text-gray-600 hover:bg-gray-50 transition">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            View Report
                        </a>
                        <?php if ($isReturnable): ?>
                            <button type="button"
                                    onclick="openReturnModal(<?= $reportIdVal ?>, '<?= htmlspecialchars(addslashes($rpt['report_number'] ?? ''), ENT_QUOTES) ?>')"
                                    class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl
                                           border border-violet-200 bg-violet-50 px-3 py-2 text-xs font-medium
                                           text-violet-700 hover:bg-violet-100 transition">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                </svg>
                                Return to Plenary
                            </button>
                        <?php else: ?>
                            <span class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl
                                         border border-gray-100 bg-gray-50 px-3 py-2 text-xs font-medium
                                         text-gray-300 cursor-not-allowed">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Returned
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ── Pagination ───────────────────────────────────────────────────── -->
        <?php if ($totalPages > 1): ?>
            <nav class="flex items-center justify-center gap-1" aria-label="Pagination">
                <?php
                $baseQuery = http_build_query(array_filter([
                    'search'    => $search,
                    'type'      => $type,
                    'status'    => $status,
                    'date_from' => $dateFrom,
                    'date_to'   => $dateTo,
                ]));
                $baseQuery = $baseQuery ? $baseQuery . '&' : '';

                $prevPage = max(1, $page - 1);
                $nextPage = min($totalPages, $page + 1);
                ?>
                <!-- Prev -->
                <a href="<?= BASE_URL ?>/committee/reports?<?= $baseQuery ?>page=<?= $prevPage ?>"
                   class="inline-flex items-center gap-1 rounded-xl border border-gray-200 bg-white
                          px-3 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 transition
                          <?= $page <= 1 ? 'pointer-events-none opacity-40' : '' ?>">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Prev
                </a>

                <?php
                // Show up to 7 page buttons around current page
                $range = 3;
                $start = max(1, $page - $range);
                $end   = min($totalPages, $page + $range);
                ?>

                <?php if ($start > 1): ?>
                    <a href="<?= BASE_URL ?>/committee/reports?<?= $baseQuery ?>page=1"
                       class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200
                              bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 transition">1</a>
                    <?php if ($start > 2): ?>
                        <span class="px-1 text-gray-400">…</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($p = $start; $p <= $end; $p++): ?>
                    <a href="<?= BASE_URL ?>/committee/reports?<?= $baseQuery ?>page=<?= $p ?>"
                       class="inline-flex h-9 w-9 items-center justify-center rounded-xl border text-sm
                              font-medium transition
                              <?= $p === $page
                                  ? 'border-emerald-500 bg-emerald-600 text-white'
                                  : 'border-gray-200 bg-white text-gray-500 hover:bg-gray-50' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>

                <?php if ($end < $totalPages): ?>
                    <?php if ($end < $totalPages - 1): ?>
                        <span class="px-1 text-gray-400">…</span>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/committee/reports?<?= $baseQuery ?>page=<?= $totalPages ?>"
                       class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200
                              bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 transition">
                        <?= $totalPages ?>
                    </a>
                <?php endif; ?>

                <!-- Next -->
                <a href="<?= BASE_URL ?>/committee/reports?<?= $baseQuery ?>page=<?= $nextPage ?>"
                   class="inline-flex items-center gap-1 rounded-xl border border-gray-200 bg-white
                          px-3 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 transition
                          <?= $page >= $totalPages ? 'pointer-events-none opacity-40' : '' ?>">
                    Next
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </nav>
        <?php endif; ?>

    <?php endif; ?>

</div>

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
                    <p id="returnModalReportNum" class="mt-2 font-mono text-sm font-bold text-emerald-700"></p>
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
                <input type="hidden" name="report_id" id="returnReportIdInput" value="">
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
    var modal         = document.getElementById('returnModal');
    var reportIdInput = document.getElementById('returnReportIdInput');
    var reportNumEl   = document.getElementById('returnModalReportNum');
    var confirmBtn    = document.getElementById('returnConfirmBtn');
    var cancelBtn     = document.getElementById('returnCancelBtn');
    var submitted     = false;

    window.openReturnModal = function (reportId, reportNumber) {
        if (!modal) return;
        reportIdInput.value = reportId;
        reportNumEl.textContent = reportNumber ? 'Report #' + reportNumber : '';
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
        reportIdInput.value = '';
    };

    // Prevent duplicate submission
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

    // Close modal on backdrop click
    modal.addEventListener('click', function (e) {
        if (e.target === modal) { window.closeReturnModal(); }
    });

    // Close modal on Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            window.closeReturnModal();
        }
    });
}());
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

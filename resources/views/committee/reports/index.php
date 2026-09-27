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

ob_start();
?>

<div class="space-y-6">

    <!-- ── Page header ─────────────────────────────────────────────────────── -->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10">
                <p class="text-sm font-medium text-blue-100">COMMITTEE / COMMITTEE REPORTS</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    Committee Reports
                </h1>
                <p class="mt-2 max-w-xl text-sm leading-6 text-blue-100">
                    Approved hearing reports awaiting plenary action. Use the
                    <strong class="font-semibold text-white">Return to Plenary</strong>
                    action to forward a completed report.
                </p>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
        </div>
    </section>

    <!-- ── Breadcrumb ───────────────────────────────────────────────────────── -->
    <nav class="flex items-center gap-2 text-sm text-gray-500" aria-label="Breadcrumb">
        <a href="<?= BASE_URL ?>/committee/hearing" class="hover:text-primary transition">Committee</a>
        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="font-medium text-gray-700">Committee Reports</span>
    </nav>

    <!-- ── Flash messages ──────────────────────────────────────────────────── -->
    <?php if ($success): ?>
        <div class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4" role="alert">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <p class="text-sm font-medium text-emerald-800"><?= htmlspecialchars($success) ?></p>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4" role="alert">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            <p class="text-sm font-medium text-red-800"><?= htmlspecialchars($error) ?></p>
        </div>
    <?php endif; ?>

    <!-- ── List card (desktop md+) ─────────────────────────────────────────── -->
    <section class="hidden md:block rounded-2xl border border-gray-200 bg-white">

        <!-- Toolbar -->
        <div class="border-b border-gray-100 px-6 py-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">

                <!-- Title + record count -->
                <div class="shrink-0">
                    <h2 class="font-semibold text-gray-900">Committee Reports List</h2>
                    <p class="mt-1 text-xs text-gray-500">
                        <?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?> found
                    </p>
                </div>

                <!-- Filter form -->
                <form method="GET" action="<?= BASE_URL ?>/committee/reports"
                      class="flex flex-col gap-3 xl:flex-row xl:items-end xl:flex-wrap">

                    <!-- Search -->
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                               placeholder="Report no., tracking no., subject…"
                               class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm
                                      text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none
                                      focus:ring-2 focus:ring-primary/20 xl:w-64">
                    </div>

                    <!-- Report type -->
                    <select name="type"
                            class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800
                                   focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
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

                    <!-- Status -->
                    <select name="status"
                            class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800
                                   focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
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

                    <!-- Date from -->
                    <div class="flex items-center gap-2">
                        <span class="shrink-0 text-xs text-gray-500">From</span>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>"
                               class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800
                                      focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    </div>

                    <!-- Date to -->
                    <div class="flex items-center gap-2">
                        <span class="shrink-0 text-xs text-gray-500">To</span>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>"
                               class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800
                                      focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    </div>

                    <!-- Filter + Clear buttons -->
                    <div class="flex items-center gap-2">
                        <button type="submit"
                                class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium
                                       text-gray-700 hover:bg-gray-50 transition">
                            Filter
                        </button>
                        <?php if ($search !== '' || $type !== '' || $status !== '' || $dateFrom !== '' || $dateTo !== ''): ?>
                            <a href="<?= BASE_URL ?>/committee/reports"
                               class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium
                                      text-gray-700 hover:bg-gray-50 transition text-center">
                                Clear
                            </a>
                        <?php endif; ?>
                    </div>

                </form>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-medium whitespace-nowrap">Report No.</th>
                        <th class="px-6 py-3 font-medium whitespace-nowrap">Type</th>
                        <th class="px-6 py-3 font-medium whitespace-nowrap">Committee</th>
                        <th class="px-6 py-3 font-medium whitespace-nowrap">Tracking No.</th>
                        <th class="px-6 py-3 font-medium">Subject Matter</th>
                        <th class="px-6 py-3 font-medium whitespace-nowrap">Outcome</th>
                        <th class="px-6 py-3 font-medium whitespace-nowrap">Created</th>
                        <th class="px-6 py-3 font-medium whitespace-nowrap">Created By</th>
                        <th class="px-6 py-3 font-medium whitespace-nowrap">Status</th>
                        <th class="px-6 py-3 font-medium whitespace-nowrap text-center">Att.</th>
                        <th class="px-6 py-3 font-medium whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($reports)): ?>
                        <tr>
                            <td colspan="11" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">No committee reports found.</p>
                                <p class="mt-1 text-xs text-gray-500">
                                    Reports appear here once a committee hearing outcome is approved and a report is filed.
                                </p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reports as $rpt): ?>
                            <?php
                            $isJoint      = $rpt['report_type'] === 'JOINT_COMMITTEE_REPORT';
                            $isReturnable = empty($rpt['returned_to_plenary_at'])
                                            && $rpt['status'] === 'Committee Report Created';
                            $reportIdVal  = (int) $rpt['report_id'];
                            ?>
                            <tr class="hover:bg-gray-50/50 transition">

                                <!-- Report number -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="font-mono text-sm font-bold text-emerald-700">
                                        <?= htmlspecialchars($rpt['report_number'] ?? '—') ?>
                                    </span>
                                </td>

                                <!-- Type badge -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($isJoint): ?>
                                        <span class="inline-flex items-center rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-semibold text-purple-800">
                                            Joint
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                                            Committee
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Committee name(s) -->
                                <td class="px-6 py-4 max-w-[160px]">
                                    <span class="text-xs text-gray-700 line-clamp-2">
                                        <?= htmlspecialchars($rpt['committee_names'] ?? '—') ?>
                                    </span>
                                </td>

                                <!-- Tracking number -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="font-mono text-xs font-semibold text-blue-700">
                                        <?= htmlspecialchars($rpt['tracking_number'] ?? '—') ?>
                                    </span>
                                </td>

                                <!-- Subject matter -->
                                <td class="px-6 py-4 max-w-[220px]">
                                    <p class="text-xs text-gray-700 line-clamp-2">
                                        <?= htmlspecialchars($rpt['subject_matter'] ?? '—') ?>
                                    </p>
                                </td>

                                <!-- Hearing outcome -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if (!empty($rpt['hearing_outcome'])): ?>
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                                            <?= htmlspecialchars($rpt['hearing_outcome']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Created date -->
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-600">
                                    <?= !empty($rpt['created_at'])
                                        ? htmlspecialchars(date('M j, Y', strtotime($rpt['created_at'])))
                                        : '—' ?>
                                </td>

                                <!-- Created by -->
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-600">
                                    <?= htmlspecialchars($rpt['created_by_name'] ?? $rpt['created_by_username'] ?? '—') ?>
                                </td>

                                <!-- Document status badge -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if (!empty($rpt['status'])): ?>
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium text-white"
                                              style="background-color:<?= htmlspecialchars($rpt['status_badge_color'] ?? '#6B7280') ?>">
                                            <?= htmlspecialchars($rpt['status']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Attachment count -->
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="inline-flex items-center rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-600">
                                        <?= (int) ($rpt['attachment_count'] ?? 0) ?>
                                    </span>
                                </td>

                                <!-- Actions -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-1">

                                        <!-- View Report -->
                                        <a href="<?= BASE_URL ?>/committee/reports/show?id=<?= $reportIdVal ?>"
                                           class="rounded-lg border border-gray-200 p-1.5 text-gray-500
                                                  hover:border-primary hover:bg-blue-50 hover:text-primary transition"
                                           title="View Report">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </a>

                                        <!-- Return to Plenary -->
                                        <?php if ($isReturnable): ?>
                                            <button type="button"
                                                    onclick="openReturnModal(<?= $reportIdVal ?>, '<?= htmlspecialchars(addslashes($rpt['report_number'] ?? ''), ENT_QUOTES) ?>')"
                                                    class="rounded-lg border border-violet-200 p-1.5 text-violet-600
                                                           hover:border-violet-400 hover:bg-violet-50 transition"
                                                    title="Return to Plenary">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                                </svg>
                                            </button>
                                        <?php else: ?>
                                            <span class="rounded-lg border border-gray-100 p-1.5 text-gray-300 cursor-not-allowed"
                                                  title="<?= !empty($rpt['returned_to_plenary_at']) ? 'Already returned to Plenary' : 'Not eligible' ?>">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M5 13l4 4L19 7"/>
                                                </svg>
                                            </span>
                                        <?php endif; ?>

                                    </div>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <?php
            $pq = [];
            if ($search   !== '') $pq['search']    = $search;
            if ($type     !== '') $pq['type']       = $type;
            if ($status   !== '') $pq['status']     = $status;
            if ($dateFrom !== '') $pq['date_from']  = $dateFrom;
            if ($dateTo   !== '') $pq['date_to']    = $dateTo;
            $queryString = !empty($pq) ? '&' . http_build_query($pq) : '';
            ?>
            <div class="flex flex-col items-center justify-between gap-3 border-t border-gray-100 px-6 py-4 sm:flex-row">
                <p class="text-xs text-gray-500">
                    Showing page <span class="font-medium text-gray-700"><?= $page ?></span> of
                    <span class="font-medium text-gray-700"><?= $totalPages ?></span>
                    (<?= number_format($total) ?> total records)
                </p>
                <div class="flex items-center gap-1">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?><?= $queryString ?>"
                           class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium
                                  text-gray-700 hover:bg-gray-50 transition">
                            Prev
                        </a>
                    <?php endif; ?>

                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage   = min($totalPages, $page + 2);
                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                        <a href="?page=<?= $i ?><?= $queryString ?>"
                           class="rounded-lg border px-3 py-1.5 text-sm font-medium transition
                                  <?= $i === $page
                                      ? 'border-primary bg-primary text-white'
                                      : 'border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?><?= $queryString ?>"
                           class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium
                                  text-gray-700 hover:bg-gray-50 transition">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </section>

    <!-- ── Mobile filter form (hidden on md+) ──────────────────────────────── -->
    <form method="GET" action="<?= BASE_URL ?>/committee/reports"
          class="md:hidden rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
        <div class="relative">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                   placeholder="Report no., tracking no., subject…"
                   class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm
                          text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none
                          focus:ring-2 focus:ring-primary/20">
        </div>
        <div class="grid grid-cols-2 gap-3">
            <select name="type"
                    class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800
                           focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                <option value="">All Types</option>
                <option value="COMMITTEE_REPORT"
                    <?= $type === 'COMMITTEE_REPORT' ? 'selected' : '' ?>>Committee Report</option>
                <option value="JOINT_COMMITTEE_REPORT"
                    <?= $type === 'JOINT_COMMITTEE_REPORT' ? 'selected' : '' ?>>Joint Committee Report</option>
            </select>
            <select name="status"
                    class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800
                           focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                <option value="">All Statuses</option>
                <option value="Committee Report Created"
                    <?= $status === 'Committee Report Created' ? 'selected' : '' ?>>Committee Report Created</option>
                <option value="Returned to Plenary"
                    <?= $status === 'Returned to Plenary' ? 'selected' : '' ?>>Returned to Plenary</option>
            </select>
            <div class="flex items-center gap-1.5">
                <span class="text-xs text-gray-500 shrink-0">From</span>
                <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>"
                       class="w-full rounded-xl border border-gray-200 bg-white px-2 py-2 text-xs text-gray-800
                              focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
            </div>
            <div class="flex items-center gap-1.5">
                <span class="text-xs text-gray-500 shrink-0">To</span>
                <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>"
                       class="w-full rounded-xl border border-gray-200 bg-white px-2 py-2 text-xs text-gray-800
                              focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button type="submit"
                    class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium
                           text-gray-700 hover:bg-gray-50 transition">
                Filter
            </button>
            <?php if ($search !== '' || $type !== '' || $status !== '' || $dateFrom !== '' || $dateTo !== ''): ?>
                <a href="<?= BASE_URL ?>/committee/reports"
                   class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium
                          text-gray-700 hover:bg-gray-50 transition text-center">
                    Clear
                </a>
            <?php endif; ?>
        </div>
        <p class="text-xs text-gray-500">
            <?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?> found
        </p>
    </form>

    <!-- ── Mobile cards (hidden on md+) ──────────────────────────────────────── -->
    <?php if (!empty($reports)): ?>
    <div class="md:hidden space-y-3" id="mobileReportCards">
    <?php foreach ($reports as $rpt): ?>
        <?php
        $isJoint      = $rpt['report_type'] === 'JOINT_COMMITTEE_REPORT';
        $isReturnable = empty($rpt['returned_to_plenary_at'])
                        && $rpt['status'] === 'Committee Report Created';
        $reportIdVal  = (int) $rpt['report_id'];
        ?>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-4">

            <!-- Header row: report number + type badge / status badge -->
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="font-mono font-bold text-emerald-700">
                        <?= htmlspecialchars($rpt['report_number'] ?? '—') ?>
                    </p>
                    <?php if ($isJoint): ?>
                        <span class="mt-1 inline-flex items-center rounded-full bg-purple-100 px-2 py-0.5 text-xs font-semibold text-purple-800">
                            Joint Committee Report
                        </span>
                    <?php else: ?>
                        <span class="mt-1 inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">
                            Committee Report
                        </span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($rpt['status'])): ?>
                    <span class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-xs font-medium text-white"
                          style="background-color:<?= htmlspecialchars($rpt['status_badge_color'] ?? '#6B7280') ?>">
                        <?= htmlspecialchars($rpt['status']) ?>
                    </span>
                <?php endif; ?>
            </div>

            <!-- Detail rows -->
            <dl class="space-y-1.5">
                <?php if (!empty($rpt['committee_names'])): ?>
                    <div class="flex gap-2">
                        <dt class="w-24 shrink-0 text-xs text-gray-400">Committee</dt>
                        <dd class="text-xs text-gray-700"><?= htmlspecialchars($rpt['committee_names']) ?></dd>
                    </div>
                <?php endif; ?>
                <div class="flex gap-2">
                    <dt class="w-24 shrink-0 text-xs text-gray-400">Tracking No.</dt>
                    <dd class="font-mono text-xs font-semibold text-blue-700">
                        <?= htmlspecialchars($rpt['tracking_number'] ?? '—') ?>
                    </dd>
                </div>
                <?php if (!empty($rpt['subject_matter'])): ?>
                    <div class="flex gap-2">
                        <dt class="w-24 shrink-0 text-xs text-gray-400">Subject</dt>
                        <dd class="text-xs text-gray-700 line-clamp-2"><?= htmlspecialchars($rpt['subject_matter']) ?></dd>
                    </div>
                <?php endif; ?>
                <?php if (!empty($rpt['hearing_outcome'])): ?>
                    <div class="flex items-center gap-2">
                        <dt class="w-24 shrink-0 text-xs text-gray-400">Outcome</dt>
                        <dd>
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">
                                <?= htmlspecialchars($rpt['hearing_outcome']) ?>
                            </span>
                        </dd>
                    </div>
                <?php endif; ?>
                <div class="flex gap-2">
                    <dt class="w-24 shrink-0 text-xs text-gray-400">Created</dt>
                    <dd class="text-xs text-gray-700">
                        <?= !empty($rpt['created_at'])
                            ? htmlspecialchars(date('M j, Y', strtotime($rpt['created_at'])))
                            : '—' ?>
                        · <?= htmlspecialchars($rpt['created_by_name'] ?? $rpt['created_by_username'] ?? '—') ?>
                    </dd>
                </div>
                <div class="flex gap-2">
                    <dt class="w-24 shrink-0 text-xs text-gray-400">Attachments</dt>
                    <dd class="text-xs text-gray-700"><?= (int) ($rpt['attachment_count'] ?? 0) ?></dd>
                </div>
            </dl>

            <!-- Actions -->
            <div class="flex items-center gap-2 border-t border-gray-100 pt-3">
                <a href="<?= BASE_URL ?>/committee/reports/show?id=<?= $reportIdVal ?>"
                   class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl border border-gray-200
                          bg-white px-3 py-2 text-xs font-medium text-gray-600 hover:bg-gray-50 transition">
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
    <?php endif; ?>

    <!-- ── Mobile pagination (hidden on md+) ─────────────────────────────────── -->
    <?php if ($totalPages > 1): ?>
        <?php
        $mpq = [];
        if ($search   !== '') $mpq['search']    = $search;
        if ($type     !== '') $mpq['type']       = $type;
        if ($status   !== '') $mpq['status']     = $status;
        if ($dateFrom !== '') $mpq['date_from']  = $dateFrom;
        if ($dateTo   !== '') $mpq['date_to']    = $dateTo;
        $mQueryString = !empty($mpq) ? '&' . http_build_query($mpq) : '';
        ?>
        <div class="md:hidden flex flex-col items-center justify-between gap-3 rounded-2xl border border-gray-200
                    bg-white px-6 py-4 sm:flex-row">
            <p class="text-xs text-gray-500">
                Page <span class="font-medium text-gray-700"><?= $page ?></span> of
                <span class="font-medium text-gray-700"><?= $totalPages ?></span>
            </p>
            <div class="flex items-center gap-1">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $mQueryString ?>"
                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium
                              text-gray-700 hover:bg-gray-50 transition">Prev</a>
                <?php endif; ?>
                <?php
                $mStart = max(1, $page - 2);
                $mEnd   = min($totalPages, $page + 2);
                for ($i = $mStart; $i <= $mEnd; $i++):
                ?>
                    <a href="?page=<?= $i ?><?= $mQueryString ?>"
                       class="rounded-lg border px-3 py-1.5 text-sm font-medium transition
                              <?= $i === $page ? 'border-primary bg-primary text-white' : 'border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $mQueryString ?>"
                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium
                              text-gray-700 hover:bg-gray-50 transition">Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- ── Return to Plenary confirmation modal ────────────────────────────────── -->
<div id="returnModal"
     class="fixed inset-0 z-[100] hidden items-center justify-center"
     role="dialog"
     aria-modal="true"
     aria-labelledby="returnModalTitle">
    <div id="returnModalBackdrop"
         class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity opacity-0"></div>
    <div id="returnModalPanel"
         class="relative z-10 w-full max-w-md mx-4 bg-white rounded-2xl shadow-2xl border border-gray-100
                transform scale-95 opacity-0 transition-all duration-200">
        <div class="p-6">
            <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-violet-100">
                    <svg class="h-6 w-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h2 id="returnModalTitle" class="text-lg font-semibold text-gray-900">
                        Return to Plenary
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Are you sure you want to return this document to Plenary?
                    </p>
                    <p id="returnModalReportNum" class="mt-2 font-mono text-sm font-bold text-emerald-700"></p>
                    <p class="mt-2 text-xs text-gray-500">
                        This will update the document status to
                        <strong class="text-violet-700">Returned to Plenary</strong>
                        and cannot be undone from this page.
                    </p>
                </div>
            </div>
        </div>
        <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4 bg-gray-50 rounded-b-2xl">
            <button type="button"
                    onclick="closeReturnModal()"
                    id="returnCancelBtn"
                    class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-medium
                           text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 transition">
                Cancel
            </button>
            <form id="returnForm" method="POST"
                  action="<?= BASE_URL ?>/committee/reports/return-to-plenary"
                  class="contents">
                <input type="hidden" name="report_id" id="returnReportIdInput" value="">
                <button type="submit"
                        id="returnConfirmBtn"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-violet-600 px-5 py-2
                               text-sm font-semibold text-white hover:bg-violet-700 transition shadow-sm">
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
    var backdrop      = document.getElementById('returnModalBackdrop');
    var panel         = document.getElementById('returnModalPanel');
    var reportIdInput = document.getElementById('returnReportIdInput');
    var reportNumEl   = document.getElementById('returnModalReportNum');
    var confirmBtn    = document.getElementById('returnConfirmBtn');
    var cancelBtn     = document.getElementById('returnCancelBtn');
    var submitted     = false;
    var isOpen        = false;

    function showModal() {
        if (isOpen) return;
        isOpen = true;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        requestAnimationFrame(function () {
            backdrop.style.opacity = '1';
            panel.classList.remove('scale-95', 'opacity-0');
            panel.classList.add('scale-100', 'opacity-100');
        });
        cancelBtn.focus();
    }

    function hideModal() {
        if (!isOpen) return;
        isOpen = false;
        backdrop.style.opacity = '0';
        panel.classList.remove('scale-100', 'opacity-100');
        panel.classList.add('scale-95', 'opacity-0');
        setTimeout(function () {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            reportIdInput.value = '';
        }, 200);
    }

    window.openReturnModal = function (reportId, reportNumber) {
        if (!modal) return;
        submitted = false;
        confirmBtn.disabled = false;
        confirmBtn.innerHTML =
            '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"' +
            ' d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>' +
            ' Yes, Return to Plenary';
        reportIdInput.value = reportId;
        reportNumEl.textContent = reportNumber ? 'Report #' + reportNumber : '';
        showModal();
    };

    window.closeReturnModal = function () {
        hideModal();
    };

    // Prevent duplicate submission; show spinner on submit
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

    // Backdrop click closes
    modal.addEventListener('click', function (e) {
        if (e.target === modal || e.target === backdrop) { hideModal(); }
    });

    // Escape key closes
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isOpen) { hideModal(); }
    });

    // Hide desktop table on mobile, show cards; hide cards on desktop
    // (handled purely via Tailwind responsive classes on the elements themselves)
}());
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

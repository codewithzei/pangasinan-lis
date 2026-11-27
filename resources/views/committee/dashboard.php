<?php
/**
 * Committee Dashboard
 *
 * Displays live statistics and a recent-inbox preview for the logged-in
 * Committee user.  All counts are scoped to the Committee role and, where
 * ownership matters, to the current authenticated user.
 */

require_once __DIR__ . '/../../../app/config/database.php';

$user     = auth();
$userId   = auth_id();
$userName = $user['full_name'] ?? ($user['first_name'] ?? 'Committee Staff');

$pageTitle = 'Committee Dashboard';

// ── Live statistics ───────────────────────────────────────────────────────────
$forReviewCount  = 0;
$acceptedCount   = 0;
$returnedCount   = 0;
$avgReviewDays   = 0;
$recentDocuments = [];
$currentTerm     = null;

try {
    $database = new Database();
    $pdo      = $database->connect();

    $termStmt = $pdo->query("
        SELECT name, session_number, year, start_date, end_date
        FROM terms
        WHERE is_active = 1 AND is_deleted = 0
        ORDER BY start_date DESC
        LIMIT 1
    ");
    $currentTerm = $termStmt->fetch() ?: null;

    // Resolve Committee role ID
    $roleStmt = $pdo->query(
        "SELECT id FROM roles WHERE name = 'Committee' AND is_active = 1 AND is_deleted = 0 LIMIT 1"
    );
    $committeeRole   = $roleStmt->fetch();
    $committeeRoleId = $committeeRole ? (int) $committeeRole['id'] : 0;

    if ($committeeRoleId > 0) {

        // ── Pending inbox count (unclaimed + pre-assigned to me, not yet accepted)
        $pendingStmt = $pdo->prepare("
            SELECT COUNT(DISTINCT da.document_id) AS cnt
            FROM document_assignments da
            WHERE da.assigned_to_role_id = ?
              AND da.phase               = 'COMMITTEE'
              AND da.decision            = 'PENDING'
              AND da.completed_at        IS NULL
              AND da.accepted_by         IS NULL
              AND (da.assigned_to_user_id IS NULL OR da.assigned_to_user_id = ?)
        ");
        $pendingStmt->execute([$committeeRoleId, $userId]);
        $forReviewCount = (int) ($pendingStmt->fetch()['cnt'] ?? 0);

        // ── Accepted count (documents currently owned by this user)
        $acceptedStmt = $pdo->prepare("
            SELECT COUNT(DISTINCT da.document_id) AS cnt
            FROM document_assignments da
            WHERE da.assigned_to_role_id = ?
              AND da.phase               = 'COMMITTEE'
              AND da.decision            = 'ACCEPTED'
              AND da.completed_at        IS NULL
              AND (da.accepted_by = ? OR da.assigned_to_user_id = ?)
        ");
        $acceptedStmt->execute([$committeeRoleId, $userId, $userId]);
        $acceptedCount = (int) ($acceptedStmt->fetch()['cnt'] ?? 0);

        // ── Returned-to-admin count (documents this user returned, role-wide)
        $returnedStmt = $pdo->prepare("
            SELECT COUNT(DISTINCT da.document_id) AS cnt
            FROM document_assignments da
            WHERE da.assigned_to_role_id = ?
              AND da.phase               = 'COMMITTEE'
              AND da.decision            = 'DECLINED'
              AND da.completed_at        IS NOT NULL
        ");
        $returnedStmt->execute([$committeeRoleId]);
        $returnedCount = (int) ($returnedStmt->fetch()['cnt'] ?? 0);

        // ── Average review time in days (completed COMMITTEE assignments)
        $avgStmt = $pdo->prepare("
            SELECT ROUND(AVG(TIMESTAMPDIFF(HOUR, da.received_at, da.completed_at) / 24.0), 1) AS avg_days
            FROM document_assignments da
            WHERE da.assigned_to_role_id = ?
              AND da.phase               = 'COMMITTEE'
              AND da.completed_at        IS NOT NULL
              AND da.received_at         IS NOT NULL
        ");
        $avgStmt->execute([$committeeRoleId]);
        $avgRow        = $avgStmt->fetch();
        $avgReviewDays = $avgRow['avg_days'] !== null ? (float) $avgRow['avg_days'] : 0;

        // ── Recent inbox documents (up to 5, pending + accepted for this user)
        $recentStmt = $pdo->prepare("
            SELECT
                da.id                  AS assignment_id,
                da.decision,
                da.accepted_at,
                d.id                   AS document_id,
                d.tracking_number,
                d.subject_matter,
                d.date_received,
                dt.name                AS document_type_name,
                dt.badge_color         AS document_type_badge_color,
                ds.name                AS status,
                ds.badge_color         AS status_badge_color,
                GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') AS committee_names
            FROM document_assignments da
            INNER JOIN documents         d  ON da.document_id       = d.id
            LEFT  JOIN document_types    dt ON d.document_type_id   = dt.id
            LEFT  JOIN document_statuses ds ON d.current_status_id  = ds.id
            LEFT  JOIN document_committees dc ON d.id               = dc.document_id
            LEFT  JOIN committees          c  ON dc.committee_id    = c.id
            WHERE da.assigned_to_role_id = ?
              AND da.phase               = 'COMMITTEE'
              AND da.completed_at        IS NULL
              AND (
                  (da.decision = 'PENDING'
                   AND da.accepted_by IS NULL
                   AND (da.assigned_to_user_id IS NULL OR da.assigned_to_user_id = ?))
                  OR
                  (da.decision = 'ACCEPTED'
                   AND (da.accepted_by = ? OR da.assigned_to_user_id = ?))
              )
            GROUP BY
                da.id, da.decision, da.accepted_at,
                d.id, d.tracking_number, d.subject_matter, d.date_received,
                dt.name, dt.badge_color, ds.name, ds.badge_color
            ORDER BY da.received_at ASC, d.date_received ASC
            LIMIT 5
        ");
        $recentStmt->execute([$committeeRoleId, $userId, $userId, $userId]);
        $recentDocuments = $recentStmt->fetchAll();
    }
} catch (Throwable $e) {
    // Dashboard counts degrade gracefully — values stay at 0
    system_log('WARNING', 'Committee dashboard stats query failed', [
        'error'   => $e->getMessage(),
        'user_id' => $userId,
    ]);
}

$termProgress = null;
if ($currentTerm && !empty($currentTerm['start_date']) && !empty($currentTerm['end_date'])) {
    $termStart = strtotime($currentTerm['start_date']);
    $termEnd = strtotime($currentTerm['end_date']);
    $today = strtotime(date('Y-m-d'));
    if ($termStart !== false && $termEnd !== false && $termEnd > $termStart) {
        $termProgress = (int) max(0, min(100, round((($today - $termStart) / ($termEnd - $termStart)) * 100)));
    }
}
$workloadTotal = $forReviewCount + $acceptedCount;
$workloadMax = max(1, $forReviewCount, $acceptedCount, $returnedCount);

ob_start();
?>

<div class="space-y-6">

    <!-- Page header ---------------------------------------------------------->
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-lg">
        <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
        <div class="relative z-10 flex flex-col gap-6 px-6 py-7 sm:px-8 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-blue-100">Pangasinan Provincial Legislature</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-white sm:text-3xl">Committee Dashboard</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-blue-100">
                    Good day, <?= htmlspecialchars($userName) ?>. Review referred measures, coordinate opinions and hearings, and prepare committee reports for Plenary.
                </p>
            </div>
            <div class="border-t border-white/10 pt-4 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
                <p class="text-xs font-medium text-white">Committee workspace</p>
                <p class="mt-1 text-xs text-blue-100"><?= htmlspecialchars(date('l, F j, Y')) ?></p>
            </div>
        </div>
    </section>

    <!-- Statistics cards ----------------------------------------------------->
    <section aria-labelledby="committee-overview-title">
        <div class="mb-4">
            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-primary">At a glance</p>
            <h2 id="committee-overview-title" class="mt-1 text-xl font-semibold text-gray-900">Committee workload</h2>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">

        <!-- For Review (pending inbox) -->
        <a href="<?= BASE_URL ?>/committee/inbox?view=inbox"
           class="group relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-primary hover:shadow-md">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500">For Review</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900">
                        <?= number_format($forReviewCount) ?>
                    </p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-primary transition">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="text-[11px] text-gray-400">Pending documents awaiting your action</span>
            </div>
        </a>

        <!-- Accepted / In Progress -->
        <a href="<?= BASE_URL ?>/committee/inbox?view=accepted"
           class="group relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-primary hover:shadow-md">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500">Accepted by You</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900">
                        <?= number_format($acceptedCount) ?>
                    </p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 transition">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="text-[11px] text-gray-400">Documents currently owned by you</span>
            </div>
        </a>

        <!-- Returned to Admin -->
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500">Returned to Admin</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900">
                        <?= number_format($returnedCount) ?>
                    </p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="text-[11px] text-gray-400">Documents returned role-wide</span>
            </div>
        </div>

        <!-- Average Review Days -->
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500">Avg. Review Days</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900">
                        <?= $avgReviewDays > 0 ? number_format($avgReviewDays, 1) : '—' ?>
                    </p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-sky-50 text-sky-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="text-[11px] text-gray-400">Based on completed assignments</span>
            </div>
        </div>

        </div>
    </section>

    <section class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-primary">Current Legislative Term</p>
            <?php if ($currentTerm): ?>
                <h2 class="mt-2 text-lg font-semibold text-gray-900"><?= htmlspecialchars($currentTerm['name']) ?></h2>
                <p class="mt-1 text-sm text-gray-500">
                    <?= htmlspecialchars(trim(($currentTerm['session_number'] ? 'Session ' . $currentTerm['session_number'] . ' · ' : '') . ($currentTerm['year'] ?? ''))) ?>
                </p>
                <p class="mt-4 text-xs text-gray-500">
                    <?= !empty($currentTerm['start_date']) ? htmlspecialchars(date('M j, Y', strtotime($currentTerm['start_date']))) : 'Start date unavailable' ?>
                    <span class="px-1 text-gray-300">—</span>
                    <?= !empty($currentTerm['end_date']) ? htmlspecialchars(date('M j, Y', strtotime($currentTerm['end_date']))) : 'End date unavailable' ?>
                </p>
                <?php if ($termProgress !== null): ?>
                    <div class="mt-4">
                        <div class="mb-1.5 flex items-center justify-between text-[11px]">
                            <span class="font-medium text-gray-500">Term timeline</span>
                            <span class="font-semibold tabular-nums text-primary"><?= $termProgress ?>%</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-primary transition-all" style="width: <?= $termProgress ?>%"></div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p class="mt-3 text-sm font-medium text-gray-700">No active legislative term</p>
                <p class="mt-1 text-xs text-gray-500">An active term will appear here when one is configured.</p>
            <?php endif; ?>
        </article>

        <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6 lg:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-primary">Assignment status</p>
                    <h2 class="mt-1 font-semibold text-gray-900">Committee workflow snapshot</h2>
                    <p class="mt-1 text-xs text-gray-500">Current and completed role-level assignment counts</p>
                </div>
                <span class="rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-semibold text-primary">
                    <?= number_format($workloadTotal) ?> active
                </span>
            </div>
            <div class="mt-5 space-y-4">
                <?php foreach ([
                    ['label' => 'For Review', 'count' => $forReviewCount, 'color' => 'bg-primary'],
                    ['label' => 'Accepted by You', 'count' => $acceptedCount, 'color' => 'bg-indigo-500'],
                    ['label' => 'Returned to Admin', 'count' => $returnedCount, 'color' => 'bg-slate-500'],
                ] as $stage): ?>
                    <div>
                        <div class="mb-1.5 flex items-center justify-between text-xs">
                            <span class="font-medium text-gray-600"><?= htmlspecialchars($stage['label']) ?></span>
                            <span class="tabular-nums text-gray-500"><?= number_format($stage['count']) ?></span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full <?= $stage['color'] ?>" style="width: <?= (int) round(($stage['count'] / $workloadMax) * 100) ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>
    </section>

    <!-- Main content grid: recent inbox + committee workbench ---------------->
    <section class="grid grid-cols-1 gap-6 lg:grid-cols-5">

        <!-- Recent Committee Inbox (3/5 columns) ---------------------------->
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm lg:col-span-3">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-5 sm:px-6">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-primary">Active assignments</p>
                    <h2 class="mt-1 font-semibold text-gray-900">Recent Committee Inbox</h2>
                    <p class="mt-1 text-xs text-gray-500">Pending items and documents accepted by you</p>
                </div>
                <a href="<?= BASE_URL ?>/committee/inbox"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2
                          text-xs font-medium text-gray-600 hover:border-primary hover:bg-blue-50
                          hover:text-primary transition">
                    View All
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>

            <?php if (empty($recentDocuments)): ?>
                <div class="px-6 py-10 text-center">
                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                        <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-gray-700">No documents pending.</p>
                    <p class="mt-1 text-xs text-gray-400">
                        Documents referred to Committee will appear here.
                    </p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-100">
                    <?php foreach ($recentDocuments as $doc): ?>
                        <?php
                        $isPending        = ($doc['decision'] === 'PENDING');
                        $statusBadgeColor = !empty($doc['status_badge_color'])
                            ? $doc['status_badge_color']
                            : '#6B7280';
                        ?>
                        <a href="<?= BASE_URL ?>/committee/inbox/show?id=<?= (int) $doc['document_id'] ?>"
                           class="flex items-start gap-4 px-6 py-4 hover:bg-gray-50/60 transition">
                            <!-- Decision dot -->
                            <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg
                                        <?= $isPending ? 'bg-amber-50 text-amber-500' : 'bg-blue-50 text-blue-500' ?>">
                                <?php if ($isPending): ?>
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                <?php else: ?>
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                <?php endif; ?>
                            </div>

                            <!-- Content -->
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-mono text-xs font-semibold text-primary">
                                        <?= htmlspecialchars($doc['tracking_number']) ?>
                                    </span>
                                    <span class="inline-flex shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                          style="background-color: <?= htmlspecialchars($statusBadgeColor) ?>;
                                                 color: white;">
                                        <?= htmlspecialchars($doc['status'] ?? '—') ?>
                                    </span>
                                </div>
                                <p class="mt-0.5 line-clamp-1 text-sm text-gray-700">
                                    <?= htmlspecialchars($doc['subject_matter']) ?>
                                </p>
                                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <?php if (!empty($doc['document_type_name'])): ?>
                                        <span class="inline-flex rounded-full px-1.5 py-0.5 text-[10px] font-medium"
                                              style="background-color: <?= htmlspecialchars($doc['document_type_badge_color'] ?? '#2563EB') ?>1a;
                                                     color: <?= htmlspecialchars($doc['document_type_badge_color'] ?? '#2563EB') ?>;">
                                            <?= htmlspecialchars($doc['document_type_name']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($doc['committee_names'])): ?>
                                        <span class="text-[10px] text-gray-400">
                                            <?= htmlspecialchars($doc['committee_names']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="text-[10px] text-gray-400">
                                        <?= htmlspecialchars(date('M j, Y', strtotime($doc['date_received']))) ?>
                                    </span>
                                    <span class="inline-flex rounded-full px-1.5 py-0.5 text-[10px] font-medium
                                                 <?= $isPending ? 'bg-amber-50 text-amber-600' : 'bg-blue-50 text-blue-600' ?>">
                                        <?= $isPending ? 'Pending' : 'Accepted' ?>
                                    </span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php if ($forReviewCount + $acceptedCount > count($recentDocuments)): ?>
                    <div class="border-t border-gray-100 px-6 py-3 text-center">
                        <a href="<?= BASE_URL ?>/committee/inbox"
                           class="text-xs font-medium text-primary hover:underline">
                            View all <?= number_format($forReviewCount + $acceptedCount) ?> documents →
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Quick actions sidebar (2/5 columns) ---------------------------->
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm lg:col-span-2">
            <div class="border-b border-gray-100 px-5 py-5 sm:px-6">
                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-primary">Committee workbench</p>
                <h2 class="mt-1 font-semibold text-gray-900">Frequently used actions</h2>
                <p class="mt-1 text-xs text-gray-500">Direct links to existing committee workflows</p>
            </div>
            <div class="space-y-2 p-5 sm:p-6">

                <!-- Go to Inbox -->
                <a href="<?= BASE_URL ?>/committee/inbox"
                   class="group flex w-full items-center gap-3 rounded-xl border border-gray-200 p-3 transition hover:border-primary hover:bg-blue-50/50">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-primary transition group-hover:bg-white">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-medium text-gray-800 group-hover:text-primary">Open Inbox</p>
                        <p class="text-xs text-gray-500">
                            <?php if ($forReviewCount > 0): ?>
                                <?= $forReviewCount ?> document<?= $forReviewCount !== 1 ? 's' : '' ?> pending
                            <?php else: ?>
                                No pending documents
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php if ($forReviewCount > 0): ?>
                        <span class="ml-auto rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">
                            <?= $forReviewCount ?>
                        </span>
                    <?php endif; ?>
                </a>

                <!-- Accepted documents -->
                <a href="<?= BASE_URL ?>/committee/inbox?view=accepted"
                   class="group flex w-full items-center gap-3 rounded-xl border border-gray-200 p-3 transition hover:border-indigo-300 hover:bg-indigo-50/50">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 transition group-hover:bg-white">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-medium text-gray-800 group-hover:text-indigo-700">Accepted Documents</p>
                        <p class="text-xs text-gray-500">
                            <?= $acceptedCount ?> document<?= $acceptedCount !== 1 ? 's' : '' ?> in progress
                        </p>
                    </div>
                </a>

                <a href="<?= BASE_URL ?>/committee/referred"
                   class="group flex w-full items-center gap-3 rounded-xl border border-gray-200 p-3 transition hover:border-sky-300 hover:bg-sky-50/50">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-700 transition group-hover:bg-white">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5h6m-6 4h6m-6 4h6m-6 4h4"/>
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-medium text-gray-800 group-hover:text-sky-700">Referred Documents</p>
                        <p class="text-xs text-gray-500">Manage opinions, agenda, and referrals</p>
                    </div>
                </a>

                <a href="<?= BASE_URL ?>/committee/hearing"
                   class="group flex w-full items-center gap-3 rounded-xl border border-gray-200 p-3 transition hover:border-slate-300 hover:bg-slate-50">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700 transition group-hover:bg-white">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m14 13-8.381 8.38a1 1 0 0 1-3.001-3l8.384-8.381M16 16l6-6m-.5.5-8-8M8 8l6-6m-5.5 5.5 8 8"/>
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-medium text-gray-800 group-hover:text-slate-700">Hearings</p>
                        <p class="text-xs text-gray-500">Review scheduled hearings and outcomes</p>
                    </div>
                </a>

                <a href="<?= BASE_URL ?>/committee/reports"
                   class="group flex w-full items-center gap-3 rounded-xl border border-gray-200 p-3 transition hover:border-blue-300 hover:bg-blue-50/50">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-primary transition group-hover:bg-white">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 22a2 2 0 01-2-2V4a2 2 0 012-2h8l6 6v12a2 2 0 01-2 2zM14 2v6h6M8 13h8m-8 4h8"/>
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-medium text-gray-800 group-hover:text-primary">Committee Reports</p>
                        <p class="text-xs text-gray-500">Prepare and track reports sent to Plenary</p>
                    </div>
                </a>

            </div>
        </div>

    </section>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';

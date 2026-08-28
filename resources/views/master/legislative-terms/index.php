<?php
ob_start();

$success = $success ?? null;
$error = $error ?? null;
$pageTitle = $pageTitle ?? 'Legislative Terms Management';
$pageSubtitle = $pageSubtitle ?? '';
$activeTerm = $activeTerm ?? null;
$totalTerms = $totalTerms ?? 0;
$totalLegislators = $totalLegislators ?? 0;
$years = $years ?? [];
$totalRows = $totalRows ?? 0;
$search = $search ?? '';
$filterYear = $filterYear ?? '';
$filterStatus = $filterStatus ?? '';
$terms = $terms ?? [];
$totalPages = $totalPages ?? 1;
$page = $page ?? 1;
$accent = $accent ?? 'primary';
?>

<div class="space-y-6">

    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-blue-700 via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-sm font-medium text-blue-100">LEGISLATIVE TERM MANAGEMENT</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                        <?= htmlspecialchars($pageTitle) ?>
                    </h1>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-blue-100">
                        <?= htmlspecialchars($pageSubtitle) ?>
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" onclick="openTermModal()"
                        class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary shadow-sm hover:bg-blue-50 transition">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        New Term
                    </button>
                    <a href="<?= BASE_URL ?>/master/legislative-terms/export-csv"
                        class="inline-flex items-center gap-2 rounded-xl bg-white/10 border border-white/20 px-4 py-2.5 text-sm font-semibold text-white hover:bg-white/20 transition">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Export CSV
                    </a>
                </div>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
        </div>
    </section>

    <?php if ($activeTerm): ?>
    <?php
        $endDate = strtotime($activeTerm['end_date']);
        $today = time();
        $daysLeft = (int)ceil(($endDate - $today) / (60 * 60 * 24));
        $startDate = strtotime($activeTerm['start_date']);
        $totalDays = (int)(($endDate - $startDate) / (60 * 60 * 24));
        $elapsedDays = (int)(($today - $startDate) / (60 * 60 * 24));
        $progress = max(0, min(100, $elapsedDays > 0 ? ($elapsedDays / $totalDays) * 100 : 0));
    ?>
    <section class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-white p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-start gap-4">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-emerald-100">
                    <svg class="h-7 w-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span>
                            CURRENTLY ACTIVE
                        </span>
                    </div>
                    <h3 class="mt-1 text-lg font-bold text-gray-900"><?= htmlspecialchars($activeTerm['name']) ?></h3>
                    <p class="mt-0.5 text-sm text-gray-500">
                        <?= date('F j, Y', strtotime($activeTerm['start_date'])) ?> — <?= date('F j, Y', strtotime($activeTerm['end_date'])) ?>
                        <?php if ($activeTerm['description']): ?>
                            &nbsp;·&nbsp; <?= htmlspecialchars($activeTerm['description']) ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-4">
                <div class="text-center">
                    <div class="text-2xl font-bold <?= $daysLeft < 30 ? 'text-amber-600' : 'text-primary' ?>">
                        <?= number_format($daysLeft) ?>
                    </div>
                    <div class="text-xs font-medium text-gray-500">Days Remaining</div>
                </div>
                <div class="h-12 w-px bg-gray-200 hidden sm:block"></div>
                <div class="min-w-[200px] flex-1 max-w-sm">
                    <div class="mb-1 flex items-center justify-between text-xs text-gray-500">
                        <span>Term Progress</span>
                        <span class="font-medium"><?= number_format($progress, 1) ?>%</span>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200">
                        <div class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-primary transition-all"
                             style="width: <?= $progress ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Total Terms</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($totalTerms) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-primary">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-blue-50 px-2 py-1 font-medium text-primary">All Sessions</span>
                </div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Active Terms</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= $activeTerm ? '1' : '0' ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-emerald-50 px-2 py-1 font-medium text-emerald-600">
                        <?= $activeTerm ? 'Live Session' : 'No Active Term' ?>
                    </span>
                </div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Unique SP Members</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($totalLegislators) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-indigo-50 px-2 py-1 font-medium text-indigo-600">Across All Terms</span>
                </div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Years Covered</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= count($years) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-amber-50 px-2 py-1 font-medium text-amber-600">
                        <?= !empty($years) ? implode(', ', array_column($years, 'year')) : 'No data' ?>
                    </span>
                </div>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-gray-200 bg-white">
        <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900">Legislative Terms</h2>
                <p class="mt-1 text-xs text-gray-500">
                    <?= $totalRows ?> record<?= $totalRows !== 1 ? 's' : '' ?> found
                </p>
            </div>
            <form method="GET" action="<?= BASE_URL ?>/master/legislative-terms" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, description..."
                        class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 sm:w-64">
                </div>
                <select name="year" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">All Years</option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?= $y['year'] ?>" <?= $filterYear == $y['year'] ? 'selected' : '' ?>>
                            <?= $y['year'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="status" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">All Status</option>
                    <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
                <button type="submit" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                    Filter
                </button>
                <?php if ($search !== '' || $filterYear !== '' || $filterStatus !== ''): ?>
                    <a href="<?= BASE_URL ?>/master/legislative-terms"
                       class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition text-center">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Term</th>
                        <th class="px-6 py-3 font-medium">Session</th>
                        <th class="px-6 py-3 font-medium">Year</th>
                        <th class="px-6 py-3 font-medium">Date Range</th>
                        <th class="px-6 py-3 font-medium">Members</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($terms)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">No legislative terms found.</p>
                                <p class="mt-1 text-xs text-gray-500">Create your first term to begin tracking sessions.</p>
                                <button type="button" onclick="openTermModal()"
                                    class="mt-5 inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Create Term
                                </button>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($terms as $t): ?>
                        <tr class="hover:bg-gray-50/50 transition">
                            <td class="px-6 py-4">
                                <div>
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($t['name']) ?></div>
                                    <?php if ($t['description']): ?>
                                        <div class="mt-0.5 text-xs text-gray-500 line-clamp-1"><?= htmlspecialchars($t['description']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">
                                    #<?= $t['session_number'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700 font-medium"><?= $t['year'] ?></td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-700">
                                    <?= date('M j, Y', strtotime($t['start_date'])) ?>
                                </div>
                                <div class="text-xs text-gray-400">
                                    to <?= date('M j, Y', strtotime($t['end_date'])) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ((int)$t['member_count'] > 0): ?>
                                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                        </svg>
                                        <?= (int)$t['member_count'] ?> SP Member<?= (int)$t['member_count'] !== 1 ? 's' : '' ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">No members</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($t['is_active']): ?>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span> Active
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">
                                        Inactive
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="<?= BASE_URL ?>/master/legislative-terms/show?id=<?= $t['id'] ?>"
                                       class="rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:border-primary hover:bg-blue-50 hover:text-primary transition"
                                       title="View Details">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>
                                    <button type="button" onclick="editTerm(<?= $t['id'] ?>)"
                                        class="rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:border-indigo-400 hover:bg-indigo-50 hover:text-indigo-600 transition"
                                        title="Edit">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    <button type="button" onclick="toggleActive(<?= $t['id'] ?>)"
                                        class="rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:border-emerald-400 hover:bg-emerald-50 hover:text-emerald-600 transition"
                                        title="<?= $t['is_active'] ? 'Set Inactive' : 'Set Active' ?>">
                                        <?php if ($t['is_active']): ?>
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                        </svg>
                                        <?php else: ?>
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                        </svg>
                                        <?php endif; ?>
                                    </button>
                                    <button type="button" onclick="cloneTerm(<?= $t['id'] ?>)"
                                        class="rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:border-amber-400 hover:bg-amber-50 hover:text-amber-600 transition"
                                        title="Clone Term">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                    </button>
                                    <?php if (!$t['is_active']): ?>
                                    <button type="button" onclick="deleteTerm(<?= $t['id'] ?>, '<?= htmlspecialchars($t['name']) ?>')"
                                        class="rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:border-red-400 hover:bg-red-50 hover:text-red-600 transition"
                                        title="Delete">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="flex flex-col items-center justify-between gap-3 border-t border-gray-100 px-6 py-4 sm:flex-row">
            <p class="text-xs text-gray-500">
                Showing page <span class="font-medium text-gray-700"><?= $page ?></span> of
                <span class="font-medium text-gray-700"><?= $totalPages ?></span>
                (<?= $totalRows ?> total records)
            </p>
            <div class="flex items-center gap-1">
                <?php
                $query = [];
                if ($search !== '') $query['search'] = $search;
                if ($filterYear !== '') $query['year'] = $filterYear;
                if ($filterStatus !== '') $query['status'] = $filterStatus;
                $queryString = !empty($query) ? '&' . http_build_query($query) : '';
                ?>
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $queryString ?>"
                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                        Prev
                    </a>
                <?php endif; ?>
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                    <a href="?page=<?= $i ?><?= $queryString ?>"
                       class="rounded-lg border px-3 py-1.5 text-sm font-medium transition <?= $i === $page
                           ? 'border-primary bg-primary text-white'
                           : 'border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $queryString ?>"
                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                        Next
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </section>

</div>

<!-- Term Create/Edit Modal -->
<div id="termModal" class="fixed inset-0 z-[100] hidden items-center justify-center" role="dialog" aria-modal="true">
    <div id="termModalBackdrop" class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity opacity-0"></div>
    <div id="termModalPanel" class="relative z-10 w-full max-w-2xl mx-4 bg-white rounded-2xl shadow-2xl border border-gray-100 transform scale-95 opacity-0 transition-all duration-200 max-h-[90vh] overflow-y-auto">
        <form id="termForm" method="POST" action="<?= BASE_URL ?>/master/legislative-terms/store">
            <input type="hidden" name="id" id="termId" value="">
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5">
                <div>
                    <h3 id="termModalTitle" class="text-lg font-semibold text-gray-900">Create New Term</h3>
                    <p class="mt-0.5 text-xs text-gray-500">Fill in the legislative session details below.</p>
                </div>
                <button type="button" onclick="closeTermModal()" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="p-6 space-y-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">
                            Session Number <span class="text-red-500">*</span>
                        </label>
                        <input type="number" name="session_number" id="sessionNumber" min="1" value="<?= old('session_number', '1') ?>"
                            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                            placeholder="e.g. 19" required>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">
                            Year <span class="text-red-500">*</span>
                        </label>
                        <input type="number" name="year" id="termYear" min="1900" max="2999" value="<?= old('year', date('Y')) ?>"
                            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                            placeholder="e.g. 2024" required>
                    </div>
                </div>
                <div>
                    <div class="flex items-center justify-between">
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">
                            Term Name <span class="text-red-500">*</span>
                        </label>
                        <button type="button" onclick="generateSuggestedName()"
                            class="text-[11px] font-medium text-primary hover:text-blue-700">
                            Auto-generate from session &amp; year
                        </button>
                    </div>
                    <input type="text" name="name" id="termName" value="<?= old('name') ?>"
                        class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                        placeholder="e.g. 19th Congress - 2024 Regular Session" required>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">
                            Start Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date" name="start_date" id="startDate" value="<?= old('start_date') ?>"
                            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                            required>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">
                            End Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date" name="end_date" id="endDate" value="<?= old('end_date') ?>"
                            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                            required>
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Description / Remarks</label>
                    <textarea name="description" id="termDescription" rows="3"
                        class="w-full resize-none rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                        placeholder="Optional description for this legislative session..."><?= old('description') ?></textarea>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <input type="checkbox" name="is_active" id="isActive" value="1"
                        class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary">
                    <div>
                        <label for="isActive" class="cursor-pointer text-sm font-medium text-gray-800">Set as active term</label>
                        <p class="text-[11px] text-gray-500 mt-0.5">Only one term can be active at a time. If checked, the current active term will be deactivated.</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4 bg-gray-50 rounded-b-2xl">
                <button type="button" onclick="closeTermModal()"
                    class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit" id="termSubmitBtn"
                    class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-white bg-primary hover:bg-blue-700 transition shadow-sm">
                    <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Save
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Confirmation Modal (for delete/set active) -->
<div id="confirmModal" class="fixed inset-0 z-[100] hidden items-center justify-center" role="dialog" aria-modal="true">
    <div id="confirmModalBackdrop" class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity opacity-0"></div>
    <div id="confirmModalPanel" class="relative z-10 w-full max-w-sm mx-4 bg-white rounded-2xl shadow-2xl border border-gray-100 transform scale-95 opacity-0 transition-all duration-200">
        <div class="p-6">
            <div class="flex items-start gap-4">
                <div id="confirmIconBox" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-red-100">
                    <svg id="confirmIcon" class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 id="confirmTitle" class="text-lg font-semibold text-gray-900">Confirm Action</h3>
                    <p id="confirmMessage" class="mt-1 text-sm text-gray-600">Are you sure?</p>
                </div>
            </div>
        </div>
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
            <button type="button" onclick="closeConfirmModal()" id="confirmCancelBtn"
                class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 transition">
                Cancel
            </button>
            <button type="button" onclick="executeConfirmAction()" id="confirmProceedBtn"
                class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 transition shadow-sm">
                Confirm
            </button>
        </div>
    </div>
</div>

<!-- Toast Notification Container -->
<div id="toastContainer" class="fixed top-4 right-4 z-[200] space-y-2 pointer-events-none"></div>

<script>
const BASE = '<?= BASE_URL ?>';

function setFieldError(fieldId, message) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    field.classList.remove('border-gray-200', 'focus:ring-primary/20', 'focus:border-primary');
    field.classList.add('border-red-400', 'focus:ring-red-400/20', 'focus:border-red-500');
    let errEl = field.parentElement.querySelector('.field-error');
    if (!errEl) {
        errEl = document.createElement('p');
        errEl.className = 'field-error mt-1 text-xs text-red-600 font-medium';
        field.parentElement.appendChild(errEl);
    }
    errEl.textContent = message;
}

function clearFieldError(fieldId) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    field.classList.remove('border-red-400', 'focus:ring-red-400/20', 'focus:border-red-500');
    field.classList.add('border-gray-200', 'focus:ring-primary/20', 'focus:border-primary');
    const errEl = field.parentElement.querySelector('.field-error');
    if (errEl) errEl.remove();
}

function validateTermForm() {
    let valid = true;

    const sessionNum = document.getElementById('sessionNumber');
    if (sessionNum.value === '' || parseInt(sessionNum.value) <= 0) {
        setFieldError('sessionNumber', 'Session number must be a positive integer.');
        valid = false;
    } else clearFieldError('sessionNumber');

    const yr = document.getElementById('termYear');
    const yrVal = parseInt(yr.value);
    if (yr.value === '' || yrVal < 1900 || yrVal > 2999) {
        setFieldError('termYear', 'Year must be a valid 4-digit year.');
        valid = false;
    } else clearFieldError('termYear');

    const name = document.getElementById('termName');
    if (name.value.trim() === '') {
        setFieldError('termName', 'Term name is required.');
        valid = false;
    } else clearFieldError('termName');

    const sd = document.getElementById('startDate');
    const ed = document.getElementById('endDate');
    if (sd.value === '') {
        setFieldError('startDate', 'Start date is required.');
        valid = false;
    } else clearFieldError('startDate');

    if (ed.value === '') {
        setFieldError('endDate', 'End date is required.');
        valid = false;
    } else if (sd.value !== '' && new Date(ed.value) <= new Date(sd.value)) {
        setFieldError('endDate', 'End date must be after start date.');
        valid = false;
    } else clearFieldError('endDate');

    return valid;
}

function attachTermValidation() {
    const fields = ['sessionNumber', 'termYear', 'termName', 'startDate', 'endDate'];
    fields.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', validateTermForm);
        el.addEventListener('blur', validateTermForm);
        el.addEventListener('change', validateTermForm);
    });
    document.getElementById('termForm')?.addEventListener('submit', (e) => {
        if (!validateTermForm()) {
            e.preventDefault();
            showToast('Please fix the errors in the form.', 'error');
            const firstErr = document.querySelector('.border-red-400');
            if (firstErr) firstErr.focus();
        }
    });
}

function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    const colors = {
        success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
        error: 'border-red-200 bg-red-50 text-red-800',
        info: 'border-blue-200 bg-blue-50 text-blue-800'
    };
    const icons = {
        success: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>',
        error: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>',
        info: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'
    };
    const iconColors = {
        success: 'bg-emerald-100 text-emerald-600',
        error: 'bg-red-100 text-red-600',
        info: 'bg-blue-100 text-blue-600'
    };
    toast.className = `pointer-events-auto max-w-sm rounded-xl border px-4 py-3 flex items-center gap-3 shadow-lg transform transition-all duration-300 translate-x-full ${colors[type]}`;
    toast.innerHTML = `
        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full ${iconColors[type]}">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">${icons[type]}</svg>
        </div>
        <p class="text-sm font-medium flex-1">${message}</p>
        <button type="button" onclick="this.parentElement.remove()" class="text-current opacity-60 hover:opacity-100">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    `;
    container.appendChild(toast);
    requestAnimationFrame(() => toast.classList.remove('translate-x-full'));
    setTimeout(() => {
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.remove(), 300);
    }, 4500);
}

// Term Modal
let termModalOpen = false;
function resetTermForm() {
    const form = document.getElementById('termForm');
    form.reset();
    document.getElementById('termId').value = '';
    document.getElementById('sessionNumber').value = '1';
    document.getElementById('termYear').value = '<?= date('Y') ?>';
    document.getElementById('termName').value = '';
    document.getElementById('startDate').value = '';
    document.getElementById('endDate').value = '';
    document.getElementById('termDescription').value = '';
    document.getElementById('isActive').checked = false;
    form.action = '<?= BASE_URL ?>/master/legislative-terms/store';
    document.getElementById('termModalTitle').textContent = 'Create New Term';
    document.getElementById('termSubmitBtn').innerHTML = `
        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        Save
    `;
    ['sessionNumber', 'termYear', 'termName', 'startDate', 'endDate'].forEach(clearFieldError);
    attachTermValidation();
}
function openTermModal() {
    if (termModalOpen) return;
    resetTermForm();
    termModalOpen = true;
    const m = document.getElementById('termModal');
    const b = document.getElementById('termModalBackdrop');
    const p = document.getElementById('termModalPanel');
    m.classList.remove('hidden');
    m.classList.add('flex');
    requestAnimationFrame(() => {
        b.classList.remove('opacity-0');
        p.classList.remove('scale-95', 'opacity-0');
        p.classList.add('scale-100', 'opacity-100');
    });
}
function closeTermModal() {
    if (!termModalOpen) return;
    termModalOpen = false;
    const m = document.getElementById('termModal');
    const b = document.getElementById('termModalBackdrop');
    const p = document.getElementById('termModalPanel');
    b.classList.add('opacity-0');
    p.classList.remove('scale-100', 'opacity-100');
    p.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        m.classList.remove('flex');
        m.classList.add('hidden');
    }, 200);
}
document.getElementById('termModalBackdrop')?.addEventListener('click', closeTermModal);
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        if (termModalOpen) closeTermModal();
        if (confirmModalOpen) closeConfirmModal();
    }
});

async function generateSuggestedName() {
    const sn = document.getElementById('sessionNumber').value;
    const yr = document.getElementById('termYear').value;
    try {
        const res = await fetch(`<?= BASE_URL ?>/master/legislative-terms/generate-name?session_number=${sn}&year=${yr}`);
        const data = await res.json();
        if (data.success) {
            document.getElementById('termName').value = data.name;
        }
    } catch (e) {}
}

document.getElementById('sessionNumber').addEventListener('change', () => {
    if (document.getElementById('termName').value === '') generateSuggestedName();
});
document.getElementById('termYear').addEventListener('change', () => {
    if (document.getElementById('termName').value === '') generateSuggestedName();
});

async function editTerm(id) {
    try {
        const res = await fetch(`<?= BASE_URL ?>/master/legislative-terms/edit?id=${id}`);
        const data = await res.json();
        if (!data.success) {
            showToast(data.message || 'Term not found.', 'error');
            return;
        }
        const t = data.data;
        document.getElementById('termId').value = t.id;
        document.getElementById('sessionNumber').value = t.session_number;
        document.getElementById('termYear').value = t.year;
        document.getElementById('termName').value = t.name;
        document.getElementById('startDate').value = t.start_date;
        document.getElementById('endDate').value = t.end_date;
        document.getElementById('termDescription').value = t.description || '';
        document.getElementById('isActive').checked = !!t.is_active;
        document.getElementById('termForm').action = '<?= BASE_URL ?>/master/legislative-terms/update';
        document.getElementById('termModalTitle').textContent = 'Edit Legislative Term';
        document.getElementById('termSubmitBtn').innerHTML = `
            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            Update
        `;
        termModalOpen = true;
        const m = document.getElementById('termModal');
        const b = document.getElementById('termModalBackdrop');
        const p = document.getElementById('termModalPanel');
        m.classList.remove('hidden');
        m.classList.add('flex');
        requestAnimationFrame(() => {
            b.classList.remove('opacity-0');
            p.classList.remove('scale-95', 'opacity-0');
            p.classList.add('scale-100', 'opacity-100');
        });
    } catch (e) {
        showToast('Failed to load term data.', 'error');
    }
}

// Confirm Modal
let confirmModalOpen = false;
let pendingAction = null;
function openConfirmModal({ title, message, confirmText = 'Confirm', cancelText = 'Cancel', type = 'danger', onConfirm }) {
    if (confirmModalOpen) return;
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMessage').innerHTML = message;
    const proceedBtn = document.getElementById('confirmProceedBtn');
    const cancelBtn = document.getElementById('confirmCancelBtn');
    const iconBox = document.getElementById('confirmIconBox');
    const icon = document.getElementById('confirmIcon');
    if (type === 'danger') {
        proceedBtn.className = 'inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 transition shadow-sm';
        iconBox.className = 'flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-red-100';
        icon.className = 'h-6 w-6 text-red-600';
        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>';
    } else {
        proceedBtn.className = 'inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-white bg-primary hover:bg-blue-700 transition shadow-sm';
        iconBox.className = 'flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-blue-100';
        icon.className = 'h-6 w-6 text-blue-600';
        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>';
    }
    proceedBtn.textContent = confirmText;
    cancelBtn.textContent = cancelText;
    pendingAction = onConfirm;
    confirmModalOpen = true;
    const m = document.getElementById('confirmModal');
    const b = document.getElementById('confirmModalBackdrop');
    const p = document.getElementById('confirmModalPanel');
    m.classList.remove('hidden');
    m.classList.add('flex');
    requestAnimationFrame(() => {
        b.classList.remove('opacity-0');
        p.classList.remove('scale-95', 'opacity-0');
        p.classList.add('scale-100', 'opacity-100');
    });
}
function closeConfirmModal() {
    if (!confirmModalOpen) return;
    confirmModalOpen = false;
    pendingAction = null;
    const m = document.getElementById('confirmModal');
    const b = document.getElementById('confirmModalBackdrop');
    const p = document.getElementById('confirmModalPanel');
    b.classList.add('opacity-0');
    p.classList.remove('scale-100', 'opacity-100');
    p.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        m.classList.remove('flex');
        m.classList.add('hidden');
    }, 200);
}
function executeConfirmAction() {
    const fn = pendingAction;
    closeConfirmModal();
    if (typeof fn === 'function') fn();
}
document.getElementById('confirmModalBackdrop')?.addEventListener('click', closeConfirmModal);

async function deleteTerm(id, name) {
    openConfirmModal({
        title: 'Delete Term',
        message: `Are you sure you want to delete <strong class="text-gray-800">${name}</strong>?<br><span class="text-xs text-gray-500">This action soft-deletes the record and cannot be undone.</span>`,
        confirmText: 'Delete',
        type: 'danger',
        onConfirm: async () => {
            try {
                const fd = new FormData();
                fd.append('id', id);
                const res = await fetch('<?= BASE_URL ?>/master/legislative-terms/destroy', { method: 'POST', body: fd });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) setTimeout(() => location.reload(), 900);
            } catch (e) { showToast('Network error.', 'error'); }
        }
    });
}

async function toggleActive(id) {
    try {
        const fd = new FormData();
        fd.append('id', id);
        const res = await fetch('<?= BASE_URL ?>/master/legislative-terms/set-active', { method: 'POST', body: fd });
        const data = await res.json();
        showToast(data.message, data.success ? 'success' : 'error');
        if (data.success) setTimeout(() => location.reload(), 800);
    } catch (e) { showToast('Network error.', 'error'); }
}

async function cloneTerm(id) {
    openConfirmModal({
        title: 'Clone Term',
        message: 'This will create a new term based on the current one, incrementing session number, year, and dates by one.<br><strong class="text-xs text-gray-500">Assigned members will also be copied.</strong>',
        confirmText: 'Clone',
        type: 'info',
        onConfirm: async () => {
            try {
                const fd = new FormData();
                fd.append('id', id);
                const res = await fetch('<?= BASE_URL ?>/master/legislative-terms/clone', { method: 'POST', body: fd });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) setTimeout(() => location.reload(), 1000);
            } catch (e) { showToast('Network error.', 'error'); }
        }
    });
}
<?php if ($success): ?>
showToast(<?= json_encode((string)$success) ?>, 'success');
<?php endif; ?>
<?php if ($error): ?>
showToast(<?= json_encode((string)$error) ?>, 'error');
<?php endif; ?>
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

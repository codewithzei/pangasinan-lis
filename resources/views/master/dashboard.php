<?php

$user = auth();
$userName = $user['full_name'] ?? 'System Administrator';

$pageTitle = $pageTitle ?? 'Super Admin Dashboard';
$pageSubtitle = $pageSubtitle ?? 'Master Control Panel';
$stats = $stats ?? [];
$roleBreakdown = $roleBreakdown ?? [];
$recentUsers = $recentUsers ?? [];
$logOverview = $logOverview ?? [];
$logTotals = $logTotals ?? ['system_logs' => 0, 'audit_logs' => 0];
$recentActivity = $recentActivity ?? [];

ob_start();
?>

<div class="space-y-6">

    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10 max-w-3xl">
                <p class="text-sm font-medium text-blue-100">MASTER CONTROL PANEL</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    Hello, <?= htmlspecialchars($userName) ?>
                </h1>
                <p class="mt-2 max-w-xl text-sm leading-6 text-blue-100">
                    Manage system users, monitor all legislative workflows, review audit trails, and control
                    application configuration from the master console.
                </p>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
        </div>
    </section>

    <section>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h2 class="text-base font-semibold text-gray-900">System Overview</h2>
                <p class="text-xs text-gray-500">Platform-wide statistics and health indicators</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <?php
            $colorMap = [
                'blue'    => 'bg-blue-50 text-primary',
                'indigo'  => 'bg-indigo-50 text-indigo-600',
                'emerald' => 'bg-emerald-50 text-emerald-600',
                'amber'   => 'bg-amber-50 text-amber-600',
                'rose'    => 'bg-rose-50 text-rose-600',
                'sky'     => 'bg-sky-50 text-sky-600',
                'violet'  => 'bg-violet-50 text-violet-600',
            ];
            if (empty($stats)) {
                $stats = [
                    ['label' => 'Active Users', 'value' => 0, 'color' => 'blue'],
                    ['label' => 'System Roles', 'value' => 0, 'color' => 'indigo'],
                    ['label' => 'Recent Logins', 'value' => 0, 'color' => 'emerald'],
                    ['label' => 'Pending Actions', 'value' => 0, 'color' => 'amber'],
                ];
            }
            foreach ($stats as $stat):
                $c = $colorMap[$stat['color']] ?? 'bg-gray-100 text-gray-600';
            ?>
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($stat['label']) ?></p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= htmlspecialchars($stat['value']) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl <?= $c ?>">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-emerald-50 px-2 py-1 font-medium text-emerald-600">Live</span>
                    <span class="text-gray-400">As of today</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Audit &amp; Monitoring</h2>
                <p class="text-xs text-gray-500">
                    Total System Logs: <span class="font-semibold text-gray-700"><?= (int)$logTotals['system_logs'] ?></span> ·
                    Total Audit Logs: <span class="font-semibold text-gray-700"><?= (int)$logTotals['audit_logs'] ?></span>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="<?= BASE_URL ?>/master/system-logs" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:border-primary hover:text-primary">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/></svg>
                    System Logs
                </a>
                <a href="<?= BASE_URL ?>/master/audit-logs" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:border-primary hover:text-primary">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Audit Logs
                </a>
            </div>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <?php
            if (empty($logOverview)) {
                $logOverview = [
                    ['label' => 'System Errors (24h)', 'value' => 0, 'color' => 'emerald'],
                    ['label' => 'Audit Events (24h)', 'value' => 0, 'color' => 'sky'],
                    ['label' => 'Account Lockouts (24h)', 'value' => 0, 'color' => 'emerald'],
                    ['label' => 'Password Changes (7d)', 'value' => 0, 'color' => 'violet'],
                ];
            }
            foreach ($logOverview as $stat):
                $c = $colorMap[$stat['color']] ?? 'bg-gray-100 text-gray-600';
                $pulse = false;
                if (str_contains($stat['label'], 'Errors') && (int)$stat['value'] > 0) $pulse = true;
                if (str_contains($stat['label'], 'Lockouts') && (int)$stat['value'] > 0) $pulse = true;
            ?>
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($stat['label']) ?></p>
                        <div class="mt-2 flex items-center gap-2">
                            <p class="text-3xl font-bold text-gray-900"><?= htmlspecialchars($stat['value']) ?></p>
                            <?php if ($pulse): ?>
                                <span class="inline-flex h-2.5 w-2.5 relative">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-rose-500"></span>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl <?= $c ?>">
                        <?php if (str_contains($stat['label'], 'Errors')): ?>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <?php elseif (str_contains($stat['label'], 'Lockouts')): ?>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        <?php elseif (str_contains($stat['label'], 'Password')): ?>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                        <?php else: ?>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-3">

        <div class="rounded-2xl border border-gray-200 bg-white xl:col-span-2">
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-5">
                <div>
                    <h2 class="font-semibold text-gray-900">Users by Role</h2>
                    <p class="mt-1 text-xs text-gray-500">Account distribution across access levels</p>
                </div>
            </div>
            <div class="space-y-4 p-6">
                <?php if (empty($roleBreakdown)): ?>
                    <div class="py-8 text-center">
                        <p class="text-sm text-gray-500">Run the migration and seeders to populate role counts.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($roleBreakdown as $row):
                        $max = 1;
                        foreach ($roleBreakdown as $r) { $max = max($max, (int)$r['user_count']); }
                        $pct = $max > 0 ? round(((int)$row['user_count'] / $max) * 100, 0) : 0;
                    ?>
                    <div>
                        <div class="mb-2 flex items-center justify-between text-sm">
                            <span class="font-medium text-gray-700"><?= htmlspecialchars($row['name']) ?></span>
                            <span class="text-gray-500"><?= (int)$row['user_count'] ?> user(s)</span>
                        </div>
                        <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-primary" style="width: <?= $pct ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white">
            <div class="border-b border-gray-200 px-6 py-5">
                <h2 class="font-semibold text-gray-900">Quick Actions</h2>
                <p class="mt-1 text-xs text-gray-500">Common master admin tasks</p>
            </div>
            <div class="grid grid-cols-2 gap-3 p-6">
                <a href="<?= BASE_URL ?>/users" class="flex flex-col items-center justify-center gap-2 rounded-xl border border-gray-200 bg-gray-50 p-4 text-center transition hover:border-primary hover:bg-blue-50 hover:text-primary">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                    </svg>
                    <span class="text-xs font-medium">Add User</span>
                </a>
                <a href="<?= BASE_URL ?>/data-management" class="flex flex-col items-center justify-center gap-2 rounded-xl border border-gray-200 bg-gray-50 p-4 text-center transition hover:border-indigo-400 hover:bg-indigo-50 hover:text-indigo-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
                    </svg>
                    <span class="text-xs font-medium">Reference Data</span>
                </a>
                <a href="<?= BASE_URL ?>/receiving/dashboard" class="flex flex-col items-center justify-center gap-2 rounded-xl border border-gray-200 bg-gray-50 p-4 text-center transition hover:border-emerald-400 hover:bg-emerald-50 hover:text-emerald-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 4h16v16H4zM8 9h8M8 13h6"/>
                    </svg>
                    <span class="text-xs font-medium">Receiving Desk</span>
                </a>
                <a href="<?= BASE_URL ?>/records" class="flex flex-col items-center justify-center gap-2 rounded-xl border border-gray-200 bg-gray-50 p-4 text-center transition hover:border-amber-400 hover:bg-amber-50 hover:text-amber-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span class="text-xs font-medium">Records</span>
                </a>
            </div>
        </div>

    </section>

    <section class="rounded-2xl border border-gray-200 bg-white">
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-5">
            <div>
                <h2 class="font-semibold text-gray-900">Recent Audit Activity</h2>
                <p class="mt-1 text-xs text-gray-500">Latest user actions and system events across the platform</p>
            </div>
            <a href="<?= BASE_URL ?>/master/audit-logs" class="text-xs font-medium text-primary hover:text-primary-dark">View audit log</a>
        </div>
        <div class="overflow-x-auto">
            <?php if (empty($recentActivity)): ?>
                <div class="py-10 text-center text-sm text-gray-500">No recent audit activity yet. Log in, create or modify records to see events appear here.</div>
            <?php else: ?>
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">When</th>
                        <th class="px-6 py-3 font-medium">Actor</th>
                        <th class="px-6 py-3 font-medium">Action</th>
                        <th class="px-6 py-3 font-medium">Target</th>
                        <th class="px-6 py-3 font-medium">IP</th>
                        <th class="px-6 py-3 font-medium">Description</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php
                    $actionColors = [
                        'CREATE'          => 'bg-emerald-50 text-emerald-700',
                        'UPDATE'          => 'bg-blue-50 text-blue-700',
                        'DELETE'          => 'bg-rose-50 text-rose-700',
                        'LOGIN'           => 'bg-emerald-50 text-emerald-700',
                        'LOGOUT'          => 'bg-gray-50 text-gray-700',
                        'PASSWORD_CHANGE' => 'bg-violet-50 text-violet-700',
                        'LOCKOUT'         => 'bg-amber-50 text-amber-700',
                        'UNLOCK'          => 'bg-emerald-50 text-emerald-700',
                        'APPROVE'         => 'bg-emerald-50 text-emerald-700',
                        'REJECT'          => 'bg-rose-50 text-rose-700',
                        'SUBMIT'          => 'bg-sky-50 text-sky-700',
                        'ROUTE'           => 'bg-indigo-50 text-indigo-700',
                        'EXPORT'          => 'bg-amber-50 text-amber-700',
                    ];
                    foreach ($recentActivity as $row):
                        $actorName = trim($row['actor_name'] ?? '') !== '' ? trim($row['actor_name']) : ($row['actor_username'] ?? 'System');
                        $cls = $actionColors[$row['action']] ?? 'bg-gray-50 text-gray-700';
                    ?>
                    <tr>
                        <td class="px-6 py-4 text-xs text-gray-500 whitespace-nowrap"><?= htmlspecialchars($row['created_at'] ?? '—') ?></td>
                        <td class="px-6 py-4 font-medium text-gray-800"><?= htmlspecialchars($actorName) ?></td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold <?= $cls ?>">
                                <?= htmlspecialchars($row['action']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-xs text-gray-600 whitespace-nowrap">
                            <?= htmlspecialchars($row['entity_type'] ?? '—') ?>
                            <?= !empty($row['entity_id']) ? ' <span class="text-gray-400">#' . htmlspecialchars($row['entity_id']) . '</span>' : '' ?>
                        </td>
                        <td class="px-6 py-4 text-xs text-gray-500 font-mono"><?= htmlspecialchars($row['ip_address'] ?? '—') ?></td>
                        <td class="px-6 py-4 text-xs text-gray-600 max-w-xs truncate"><?= htmlspecialchars($row['description'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </section>

    <section class="rounded-2xl border border-gray-200 bg-white">
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-5">
            <div>
                <h2 class="font-semibold text-gray-900">Recently Created Accounts</h2>
                <p class="mt-1 text-xs text-gray-500">Latest users registered on the platform</p>
            </div>
            <a href="<?= BASE_URL ?>/users" class="text-xs font-medium text-primary hover:text-primary-dark">View all users</a>
        </div>
        <div class="overflow-x-auto">
            <?php if (empty($recentUsers)): ?>
                <div class="py-10 text-center text-sm text-gray-500">No recent accounts. Run seeders or register users.</div>
            <?php else: ?>
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Name</th>
                        <th class="px-6 py-3 font-medium">Username</th>
                        <th class="px-6 py-3 font-medium">Role</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($recentUsers as $u):
                        $full = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: $u['username'];
                    ?>
                    <tr>
                        <td class="px-6 py-4 font-medium text-gray-800"><?= htmlspecialchars($full) ?></td>
                        <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($u['username']) ?></td>
                        <td class="px-6 py-4">
                            <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700"><?= htmlspecialchars($u['role_name']) ?></span>
                        </td>
                        <td class="px-6 py-4">
                            <?php if ($u['status'] === 'active'): ?>
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Active
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">
                                    <?= htmlspecialchars(ucfirst($u['status'])) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-xs text-gray-500"><?= htmlspecialchars($u['created_at'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </section>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';

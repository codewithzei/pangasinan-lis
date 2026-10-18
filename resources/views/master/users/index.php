<?php
ob_start();

$success = $success ?? null;
$error = $error ?? null;
$pageTitle = $pageTitle ?? 'User Accounts Management';
$pageSubtitle = $pageSubtitle ?? '';
$totalAccounts = $totalAccounts ?? 0;
$activeAccounts = $activeAccounts ?? 0;
$deactivatedAccounts = $deactivatedAccounts ?? 0;
$blockedAccounts = $blockedAccounts ?? 0;
$totalRows = $totalRows ?? 0;
$search = $search ?? '';
$filterStatus = $filterStatus ?? '';
$accounts = $accounts ?? [];
$roles = $roles ?? [];
$totalPages = $totalPages ?? 1;
$page = $page ?? 1;
$currentUserId = $currentUserId ?? null;
$accent = $accent ?? 'primary';

function formatAccountName(array $a): string
{
    $parts = array_filter([
        $a['first_name'] ?? '',
        $a['middle_name'] ?? '',
        $a['last_name'] ?? '',
        $a['suffix'] ?? '',
    ], static fn($v) => $v !== null && $v !== '');
    $name = trim(implode(' ', $parts));
    return $name !== '' ? $name : ($a['username'] ?? '—');
}

function statusBadgeClass(string $status): string
{
    return match ($status) {
        'active' => 'bg-emerald-50 text-emerald-700',
        'deactivated' => 'bg-amber-50 text-amber-700',
        'blocked' => 'bg-red-50 text-red-700',
        default => 'bg-gray-100 text-gray-600',
    };
}
?>

<div class="space-y-6">

    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-sm font-medium text-blue-100">USER ACCOUNTS DATA</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                        <?= htmlspecialchars($pageTitle) ?>
                    </h1>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-blue-100">
                        <?= htmlspecialchars($pageSubtitle) ?>
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" onclick="openAccountModal()"
                        class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary shadow-sm hover:bg-blue-50 transition">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        New Account
                    </button>
                </div>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
        </div>
    </section>

    <section>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Total Accounts</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($totalAccounts) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-blue-50 px-2 py-1 font-medium text-blue-600">All accounts</span>
                </div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Active</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($activeAccounts) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-emerald-50 px-2 py-1 font-medium text-emerald-600">Can sign in</span>
                </div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Deactivated</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($deactivatedAccounts) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-amber-50 px-2 py-1 font-medium text-amber-700">Login disabled</span>
                </div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Blocked</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($blockedAccounts) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-red-50 text-red-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-red-50 px-2 py-1 font-medium text-red-700">Access denied</span>
                </div>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-gray-200 bg-white">
        <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900">Accounts List</h2>
                <p class="mt-1 text-xs text-gray-500">
                    <?= $totalRows ?> record<?= $totalRows !== 1 ? 's' : '' ?> found
                </p>
            </div>
            <form method="GET" action="<?= BASE_URL ?>/master/users" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, username, email..."
                        class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 sm:w-64">
                </div>
                <select name="status" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">All Status</option>
                    <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="deactivated" <?= $filterStatus === 'deactivated' ? 'selected' : '' ?>>Deactivated</option>
                    <option value="blocked" <?= $filterStatus === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                </select>
                <button type="submit" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                    Filter
                </button>
                <?php if ($search !== '' || $filterStatus !== ''): ?>
                    <a href="<?= BASE_URL ?>/master/users"
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
                        <th class="px-6 py-3 font-medium">Name</th>
                        <th class="px-6 py-3 font-medium">Username</th>
                        <th class="px-6 py-3 font-medium">Email</th>
                        <th class="px-6 py-3 font-medium">Role</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($accounts)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">No accounts found.</p>
                                <p class="mt-1 text-xs text-gray-500">Create your first user account to populate the list.</p>
                                <button type="button" onclick="openAccountModal()"
                                    class="mt-5 inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Create Account
                                </button>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($accounts as $a): ?>
                        <?php
                            $isSelf = $currentUserId !== null && (int)$a['id'] === (int)$currentUserId;
                            $displayName = formatAccountName($a);
                            $status = $a['status'] ?? 'active';
                        ?>
                        <tr class="hover:bg-gray-50/50 transition">
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900">
                                    <?= htmlspecialchars($displayName) ?>
                                    <?php if ($isSelf): ?>
                                        <span class="ml-1 rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-blue-600">You</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($a['contact_number'])): ?>
                                    <div class="mt-0.5 text-xs text-gray-500"><?= htmlspecialchars($a['contact_number']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-gray-700"><?= htmlspecialchars($a['username']) ?></td>
                            <td class="px-6 py-4 text-gray-700"><?= htmlspecialchars($a['email']) ?></td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center rounded-lg bg-gray-50 border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-600">
                                    <?= htmlspecialchars($a['role_name'] ?? '—') ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold <?= statusBadgeClass($status) ?>">
                                    <?php if ($status === 'active'): ?>
                                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span>
                                    <?php endif; ?>
                                    <?= htmlspecialchars(ucfirst($status)) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" onclick="editAccount(<?= (int)$a['id'] ?>)"
                                        class="rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:border-blue-400 hover:bg-blue-50 hover:text-blue-600 transition"
                                        title="Edit">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    <button type="button"
                                        onclick="openStatusModal(<?= (int)$a['id'] ?>, '<?= htmlspecialchars($a['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($status, ENT_QUOTES) ?>', <?= $isSelf ? 'true' : 'false' ?>)"
                                        class="rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:border-emerald-400 hover:bg-emerald-50 hover:text-emerald-600 transition"
                                        title="Change Status">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                        </svg>
                                    </button>
                                    <button type="button"
                                        onclick="deleteAccount(<?= (int)$a['id'] ?>, '<?= htmlspecialchars($a['username'], ENT_QUOTES) ?>', <?= $isSelf ? 'true' : 'false' ?>)"
                                        class="rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:border-red-400 hover:bg-red-50 hover:text-red-600 transition <?= $isSelf ? 'opacity-40 cursor-not-allowed' : '' ?>"
                                        title="<?= $isSelf ? 'Cannot delete your own account' : 'Delete' ?>"
                                        <?= $isSelf ? 'disabled' : '' ?>>
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
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

<!-- User Account Create/Edit Modal -->
<div id="userAccountModal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-gray-900/50 backdrop-blur-sm transition-opacity opacity-0" role="dialog" aria-modal="true">
    <div id="userAccountModalPanel" class="relative w-full max-w-2xl mx-4 bg-white rounded-2xl shadow-2xl border border-gray-100 transform scale-95 opacity-0 transition-all duration-200 max-h-[90vh] overflow-y-auto">
        <form id="userAccountForm" method="POST" action="<?= BASE_URL ?>/master/users/store">
            <input type="hidden" name="id" id="userAccountId" value="">
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5 sticky top-0 bg-white z-10">
                <div>
                    <h3 id="userAccountModalTitle" class="text-lg font-semibold text-gray-900">Create New User Account</h3>
                    <p class="mt-0.5 text-xs text-gray-500">Fill in account credentials and personal information.</p>
                </div>
                <button type="button" onclick="closeAccountModal()" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="p-6 space-y-6">
                <div>
                    <h4 class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Personal Information</h4>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">First Name <span class="text-red-500">*</span></label>
                            <input type="text" name="first_name" id="firstName" value="<?= old('first_name') ?>" maxlength="50"
                                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                placeholder="Juan" required>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Middle Name</label>
                            <input type="text" name="middle_name" id="middleName" value="<?= old('middle_name') ?>" maxlength="50"
                                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                placeholder="Dela">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Last Name <span class="text-red-500">*</span></label>
                            <input type="text" name="last_name" id="lastName" value="<?= old('last_name') ?>" maxlength="50"
                                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                placeholder="Cruz" required>
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Suffix</label>
                            <input type="text" name="suffix" id="suffix" value="<?= old('suffix') ?>" maxlength="10"
                                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                placeholder="Jr., III">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Contact Number</label>
                            <input type="tel" name="contact_number" id="contactNumber" value="<?= old('contact_number') ?>" maxlength="20"
                                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                placeholder="09XXXXXXXXX">
                        </div>
                    </div>
                </div>

                <div class="h-px bg-gray-100"></div>

                <div>
                    <h4 class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Account Credentials</h4>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Username <span class="text-red-500">*</span></label>
                            <input type="text" name="username" id="username" value="<?= old('username') ?>" maxlength="50"
                                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                placeholder="jcruz" required>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Email <span class="text-red-500">*</span></label>
                            <input type="email" name="email" id="email" value="<?= old('email') ?>" maxlength="100"
                                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                placeholder="juan.cruz@example.com" required>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">
                                Password <span id="passwordRequiredMark" class="text-red-500">*</span>
                            </label>
                            <input type="password" name="password" id="password" minlength="12"
                                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                placeholder="Min. 12 characters" autocomplete="new-password">
                            <p class="mt-1 text-[11px] text-gray-500">At least 12 characters, with uppercase, lowercase, a number, and a special character.</p>
                            <p id="passwordHint" class="mt-1 text-[11px] text-gray-500 hidden">Leave blank to keep the current password.</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">
                                Confirm Password <span id="passwordConfirmRequiredMark" class="text-red-500">*</span>
                            </label>
                            <input type="password" name="password_confirm" id="passwordConfirm" minlength="12"
                                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                placeholder="Re-enter password" autocomplete="new-password">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Role <span class="text-red-500">*</span></label>
                            <select name="role_id" id="roleId" required
                                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                                <option value="">Select role...</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= (int)$role['id'] ?>" <?= (string)old('role_id') === (string)$role['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($role['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Status <span class="text-red-500">*</span></label>
                            <select name="status" id="accountStatus" required
                                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                                <option value="active" <?= old('status', 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="deactivated" <?= old('status') === 'deactivated' ? 'selected' : '' ?>>Deactivated</option>
                                <option value="blocked" <?= old('status') === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                            </select>
                            <p class="mt-1 text-[11px] text-gray-500">Only active accounts can sign in.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4 bg-gray-50 rounded-b-2xl sticky bottom-0">
                <button type="button" onclick="closeAccountModal()"
                    class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit" id="userAccountSubmitBtn"
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

<!-- Status Change Modal -->
<div id="statusModal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-gray-900/50 backdrop-blur-sm transition-opacity opacity-0" role="dialog" aria-modal="true">
    <div id="statusModalPanel" class="relative w-full max-w-sm mx-4 bg-white rounded-2xl shadow-2xl border border-gray-100 transform scale-95 opacity-0 transition-all duration-200">
        <div class="p-6 space-y-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Change Account Status</h3>
                <p id="statusModalMessage" class="mt-1 text-sm text-gray-600">Select a new status for this account.</p>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Status</label>
                <select id="statusModalSelect"
                    class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="active">Active</option>
                    <option value="deactivated">Deactivated</option>
                    <option value="blocked">Blocked</option>
                </select>
            </div>
            <input type="hidden" id="statusModalAccountId" value="">
        </div>
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
            <button type="button" onclick="closeStatusModal()"
                class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 transition">
                Cancel
            </button>
            <button type="button" onclick="submitStatusChange()"
                class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-white bg-primary hover:bg-blue-700 transition shadow-sm">
                Update Status
            </button>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirmModal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-gray-900/50 backdrop-blur-sm transition-opacity opacity-0" role="dialog" aria-modal="true">
    <div id="confirmModalPanel" class="relative w-full max-w-sm mx-4 bg-white rounded-2xl shadow-2xl border border-gray-100 transform scale-95 opacity-0 transition-all duration-200">
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

<div id="toastContainer" class="fixed top-4 right-4 z-[200] space-y-2 pointer-events-none"></div>

<script>
const BASE = '<?= BASE_URL ?>';
const CURRENT_USER_ID = <?= $currentUserId !== null ? (int)$currentUserId : 'null' ?>;
let isEditMode = false;

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

function validateAccountForm() {
    let valid = true;
    const requiredText = [
        ['firstName', 'First name is required.'],
        ['lastName', 'Last name is required.'],
        ['username', 'Username is required.'],
        ['email', 'Email is required.'],
    ];

    requiredText.forEach(([id, msg]) => {
        const el = document.getElementById(id);
        if (!el || el.value.trim() === '') {
            setFieldError(id, msg);
            valid = false;
        } else {
            clearFieldError(id);
        }
    });

    const email = document.getElementById('email');
    if (email && email.value.trim() !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
        setFieldError('email', 'Invalid email format.');
        valid = false;
    }

    const roleId = document.getElementById('roleId');
    if (!roleId || roleId.value === '') {
        setFieldError('roleId', 'Role is required.');
        valid = false;
    } else clearFieldError('roleId');

    const password = document.getElementById('password');
    const passwordConfirm = document.getElementById('passwordConfirm');
    const pwd = password ? password.value : '';
    const pwdConfirm = passwordConfirm ? passwordConfirm.value : '';

    if (!isEditMode || pwd !== '') {
        if (pwd === '') {
            setFieldError('password', 'Password is required.');
            valid = false;
        } else if (pwd.length < 12 || !/[A-Z]/.test(pwd) || !/[a-z]/.test(pwd) || !/[0-9]/.test(pwd) || !/[^A-Za-z0-9]/.test(pwd)) {
            setFieldError('password', 'Password must be at least 12 characters and include uppercase and lowercase letters, a number, and a special character.');
            valid = false;
        } else clearFieldError('password');

        if (pwd !== pwdConfirm) {
            setFieldError('passwordConfirm', 'Passwords do not match.');
            valid = false;
        } else if (pwd !== '') {
            clearFieldError('passwordConfirm');
        }
    } else {
        clearFieldError('password');
        clearFieldError('passwordConfirm');
    }

    return valid;
}

function attachAccountValidation() {
    ['firstName', 'lastName', 'username', 'email', 'password', 'passwordConfirm', 'roleId'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', validateAccountForm);
        el.addEventListener('blur', validateAccountForm);
        el.addEventListener('change', validateAccountForm);
    });
    document.getElementById('userAccountForm')?.addEventListener('submit', (e) => {
        if (!validateAccountForm()) {
            e.preventDefault();
            showToast('Please fix the errors in the form.', 'error');
            const firstErr = document.querySelector('#userAccountForm .border-red-400');
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

let userAccountModalOpen = false;
function setPasswordRequired(required) {
    document.getElementById('passwordRequiredMark').classList.toggle('hidden', !required);
    document.getElementById('passwordConfirmRequiredMark').classList.toggle('hidden', !required);
    document.getElementById('passwordHint').classList.toggle('hidden', required);
    document.getElementById('password').required = required;
    document.getElementById('passwordConfirm').required = required;
}

function resetAccountForm() {
    const form = document.getElementById('userAccountForm');
    form.reset();
    isEditMode = false;
    document.getElementById('userAccountId').value = '';
    document.getElementById('accountStatus').value = 'active';
    form.action = '<?= BASE_URL ?>/master/users/store';
    document.getElementById('userAccountModalTitle').textContent = 'Create New User Account';
    document.getElementById('userAccountSubmitBtn').innerHTML = `
        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        Save
    `;
    setPasswordRequired(true);
    Array.from(document.getElementById('accountStatus').options).forEach((opt) => { opt.disabled = false; });
    ['firstName', 'lastName', 'username', 'email', 'password', 'passwordConfirm', 'roleId'].forEach(clearFieldError);
    attachAccountValidation();
}

function openAccountModal() {
    if (userAccountModalOpen) return;
    resetAccountForm();
    userAccountModalOpen = true;
    const m = document.getElementById('userAccountModal');
    const p = document.getElementById('userAccountModalPanel');
    m.classList.remove('hidden');
    m.classList.add('flex');
    requestAnimationFrame(() => {
        m.style.opacity = '1';
        p.classList.remove('scale-95', 'opacity-0');
        p.classList.add('scale-100', 'opacity-100');
    });
    setTimeout(() => document.getElementById('firstName').focus(), 220);
}

function closeAccountModal() {
    if (!userAccountModalOpen) return;
    userAccountModalOpen = false;
    const m = document.getElementById('userAccountModal');
    const p = document.getElementById('userAccountModalPanel');
    m.style.opacity = '0';
    p.classList.remove('scale-100', 'opacity-100');
    p.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        m.classList.remove('flex');
        m.classList.add('hidden');
    }, 200);
}

document.getElementById('userAccountModal')?.addEventListener('click', function(e) {
    if (e.target === document.getElementById('userAccountModal')) closeAccountModal();
});

async function editAccount(id) {
    try {
        const res = await fetch(`<?= BASE_URL ?>/master/users/edit?id=${id}`);
        const data = await res.json();
        if (!data.success) {
            showToast(data.message || 'Account not found.', 'error');
            return;
        }
        const a = data.data;
        isEditMode = true;
        document.getElementById('userAccountId').value = a.id;
        document.getElementById('firstName').value = a.first_name || '';
        document.getElementById('middleName').value = a.middle_name || '';
        document.getElementById('lastName').value = a.last_name || '';
        document.getElementById('suffix').value = a.suffix || '';
        document.getElementById('contactNumber').value = a.contact_number || '';
        document.getElementById('username').value = a.username || '';
        document.getElementById('email').value = a.email || '';
        document.getElementById('password').value = '';
        document.getElementById('passwordConfirm').value = '';
        const roleSelect = document.getElementById('roleId');
        roleSelect.value = a.role_id || '';
        if (a.role_id && String(roleSelect.value) !== String(a.role_id)) {
            showToast('This account currently has an inactive role. Assign an active role.', 'info');
        }
        const statusSelect = document.getElementById('accountStatus');
        statusSelect.value = a.status || 'active';
        const editingSelf = CURRENT_USER_ID !== null && Number(a.id) === Number(CURRENT_USER_ID);
        Array.from(statusSelect.options).forEach((opt) => {
            opt.disabled = editingSelf && opt.value !== 'active';
        });
        document.getElementById('userAccountForm').action = '<?= BASE_URL ?>/master/users/update';
        document.getElementById('userAccountModalTitle').textContent = 'Edit User Account';
        document.getElementById('userAccountSubmitBtn').innerHTML = `
            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            Update
        `;
        setPasswordRequired(false);
        userAccountModalOpen = true;
        const m = document.getElementById('userAccountModal');
        const pn = document.getElementById('userAccountModalPanel');
        m.classList.remove('hidden');
        m.classList.add('flex');
        requestAnimationFrame(() => {
            m.style.opacity = '1';
            pn.classList.remove('scale-95', 'opacity-0');
            pn.classList.add('scale-100', 'opacity-100');
        });
    } catch (e) {
        showToast('Failed to load account data.', 'error');
    }
}

let statusModalOpen = false;
let statusModalIsSelf = false;
function openStatusModal(id, username, currentStatus, isSelf) {
    statusModalIsSelf = !!isSelf;
    document.getElementById('statusModalAccountId').value = id;
    document.getElementById('statusModalSelect').value = currentStatus;
    document.getElementById('statusModalMessage').innerHTML =
        `Update status for <strong class="text-gray-800">${username}</strong>. Only active accounts can sign in.`;
    statusModalOpen = true;
    const m = document.getElementById('statusModal');
    const p = document.getElementById('statusModalPanel');
    m.classList.remove('hidden');
    m.classList.add('flex');
    requestAnimationFrame(() => {
        m.style.opacity = '1';
        p.classList.remove('scale-95', 'opacity-0');
        p.classList.add('scale-100', 'opacity-100');
    });
}

function closeStatusModal() {
    if (!statusModalOpen) return;
    statusModalOpen = false;
    const m = document.getElementById('statusModal');
    const p = document.getElementById('statusModalPanel');
    m.style.opacity = '0';
    p.classList.remove('scale-100', 'opacity-100');
    p.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        m.classList.remove('flex');
        m.classList.add('hidden');
    }, 200);
}

async function submitStatusChange() {
    const id = document.getElementById('statusModalAccountId').value;
    const status = document.getElementById('statusModalSelect').value;
    if (statusModalIsSelf && status !== 'active') {
        showToast('You cannot deactivate or block your own signed-in account.', 'error');
        return;
    }
    try {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('status', status);
        const res = await fetch('<?= BASE_URL ?>/master/users/update-status', { method: 'POST', body: fd });
        const data = await res.json();
        showToast(data.message, data.success ? 'success' : 'error');
        if (data.success) {
            closeStatusModal();
            setTimeout(() => location.reload(), 800);
        }
    } catch (e) {
        showToast('Network error.', 'error');
    }
}

document.getElementById('statusModal')?.addEventListener('click', function(e) {
    if (e.target === document.getElementById('statusModal')) closeStatusModal();
});

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
    const p = document.getElementById('confirmModalPanel');
    m.classList.remove('hidden');
    m.classList.add('flex');
    requestAnimationFrame(() => {
        m.style.opacity = '1';
        p.classList.remove('scale-95', 'opacity-0');
        p.classList.add('scale-100', 'opacity-100');
    });
}

function closeConfirmModal() {
    if (!confirmModalOpen) return;
    confirmModalOpen = false;
    pendingAction = null;
    const m = document.getElementById('confirmModal');
    const p = document.getElementById('confirmModalPanel');
    m.style.opacity = '0';
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

document.getElementById('confirmModal')?.addEventListener('click', function(e) {
    if (e.target === document.getElementById('confirmModal')) closeConfirmModal();
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        if (userAccountModalOpen) closeAccountModal();
        if (statusModalOpen) closeStatusModal();
        if (confirmModalOpen) closeConfirmModal();
    }
});

async function deleteAccount(id, username, isSelf) {
    if (isSelf) {
        showToast('You cannot delete your own signed-in account.', 'error');
        return;
    }
    openConfirmModal({
        title: 'Delete Account',
        message: `Are you sure you want to delete <strong class="text-gray-800">${username}</strong>?<br><span class="text-xs text-gray-500">This soft-deletes the account and removes it from the active list.</span>`,
        confirmText: 'Delete',
        type: 'danger',
        onConfirm: async () => {
            try {
                const fd = new FormData();
                fd.append('id', id);
                const res = await fetch('<?= BASE_URL ?>/master/users/destroy', { method: 'POST', body: fd });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) setTimeout(() => location.reload(), 900);
            } catch (e) {
                showToast('Network error.', 'error');
            }
        }
    });
}

attachAccountValidation();

<?php if ($success): ?>
showToast(<?= json_encode(strip_tags((string)$success)) ?>, 'success');
<?php endif; ?>
<?php if ($error): ?>
showToast(<?= json_encode(strip_tags(str_replace('<br>', ' ', (string)$error))) ?>, 'error');
<?php endif; ?>
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

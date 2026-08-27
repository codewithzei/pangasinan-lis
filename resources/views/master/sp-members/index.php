<?php
ob_start();

$success           = $success           ?? null;
$error             = $error             ?? null;
$pageTitle         = $pageTitle         ?? 'SP Members Management';
$pageSubtitle      = $pageSubtitle      ?? '';
$totalSpMembers    = $totalSpMembers    ?? 0;
$activeSpMembers   = $activeSpMembers   ?? 0;
$inactiveSpMembers = $inactiveSpMembers ?? 0;
$totalRows         = $totalRows         ?? 0;
$search            = $search            ?? '';
$filterStatus      = $filterStatus      ?? '';
$filterDistrict    = $filterDistrict    ?? 0;
$filterPosition    = $filterPosition    ?? '';
$spMembers         = $spMembers         ?? [];
$districts         = $districts         ?? [];
$positions         = $positions         ?? [];
$totalPages        = $totalPages        ?? 1;
$page              = $page              ?? 1;
$accent            = $accent            ?? 'primary';
?>

<div class="space-y-6">

    <!-- HERO -->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-blue-800 via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-sm font-medium text-blue-100">SP MEMBERS MASTER DATA</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                        <?= htmlspecialchars($pageTitle) ?>
                    </h1>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-blue-100">
                        <?= htmlspecialchars($pageSubtitle) ?>
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" onclick="openSpMemberModal()"
                        class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary shadow-sm hover:bg-blue-50 transition">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        New SP Member
                    </button>
                </div>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
        </div>
    </section>

    <!-- STATS CARDS -->
    <section>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Total SP Members</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($totalSpMembers) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/></svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-blue-50 px-2 py-1 font-medium text-primary">All members</span>
                </div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Active Members</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($activeSpMembers) ?></p>
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
                        <span class="inline-block h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500 mr-1"></span>Currently serving
                    </span>
                </div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Inactive Members</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($inactiveSpMembers) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 text-gray-500">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-gray-100 px-2 py-1 font-medium text-gray-600">Hidden from selection</span>
                </div>
            </div>
        </div>
    </section>

    <!-- TABLE SECTION -->
    <section class="rounded-2xl border border-gray-200 bg-white">
        <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900">SP Members List</h2>
                <p class="mt-1 text-xs text-gray-500">
                    <?= $totalRows ?> record<?= $totalRows !== 1 ? 's' : '' ?> found
                </p>
            </div>
            <form method="GET" action="<?= BASE_URL ?>/master/sp-members" class="flex flex-wrap items-center gap-3">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                           placeholder="Search by name..."
                           class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 sm:w-48">
                </div>
                <select name="status" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">All Status</option>
                    <option value="active"   <?= $filterStatus === 'active'   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
                <select name="district_id" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">All Districts</option>
                    <?php foreach ($districts as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= (int)$filterDistrict === (int)$d['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="position" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">All Positions</option>
                    <?php foreach ($positions as $pos): ?>
                        <option value="<?= htmlspecialchars($pos['name']) ?>" <?= $filterPosition === $pos['name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pos['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                    Filter
                </button>
                <?php if ($search !== '' || $filterStatus !== '' || $filterDistrict > 0 || $filterPosition !== ''): ?>
                    <a href="<?= BASE_URL ?>/master/sp-members"
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
                        <th class="px-6 py-3 font-medium">Member</th>
                        <th class="px-6 py-3 font-medium">Position</th>
                        <th class="px-6 py-3 font-medium">District</th>
                        <th class="px-6 py-3 font-medium">Sort</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($spMembers)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/></svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">No SP members found.</p>
                                <p class="mt-1 text-xs text-gray-500">Add your first SP member to get started.</p>
                                <button type="button" onclick="openSpMemberModal()"
                                    class="mt-5 inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Add SP Member
                                </button>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($spMembers as $sm): ?>
                        <?php
                            $nameParts  = array_filter([$sm['first_name'], $sm['middle_name'] ?? null, $sm['last_name']]);
                            $fullName   = implode(' ', $nameParts);
                            $suffix     = $sm['suffix'] ?? null;
                            $initials   = mb_strtoupper(mb_substr($sm['first_name'], 0, 1) . mb_substr($sm['last_name'], 0, 1));
                            $hasPhoto   = !empty($sm['photo_path']);
                            $safeDisplay = htmlspecialchars($fullName . ($suffix ? ' ' . $suffix : ''));
                        ?>
                        <tr class="hover:bg-gray-50/50 transition">
                            <!-- Member -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <?php if ($hasPhoto): ?>
                                        <img src="<?= BASE_URL ?>/public/<?= htmlspecialchars($sm['photo_path']) ?>"
                                             alt="<?= htmlspecialchars($fullName) ?>"
                                             class="h-9 w-9 shrink-0 rounded-full object-cover ring-1 ring-gray-200">
                                    <?php else: ?>
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary font-semibold text-sm select-none">
                                            <?= $initials ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="font-medium text-gray-900">
                                        <?= htmlspecialchars($fullName) ?>
                                        <?php if ($suffix): ?>
                                            <span class="ml-1 inline-flex items-center rounded-md bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold text-gray-600">
                                                <?= htmlspecialchars($suffix) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <!-- Position -->
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center rounded-md bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700">
                                    <?= htmlspecialchars($sm['position']) ?>
                                </span>
                            </td>
                            <!-- District -->
                            <td class="px-6 py-4">
                                <?php if (!empty($sm['district_name'])): ?>
                                    <span class="inline-flex items-center rounded-lg bg-emerald-50 border border-emerald-200 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                        <?= htmlspecialchars($sm['district_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">—</span>
                                <?php endif; ?>
                            </td>
                            <!-- Sort -->
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center rounded-lg bg-gray-50 border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-600">
                                    #<?= (int)$sm['sort_order'] ?>
                                </span>
                            </td>
                            <!-- Status -->
                            <td class="px-6 py-4">
                                <?php if ($sm['is_active']): ?>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span> Active
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">
                                        Inactive
                                    </span>
                                <?php endif; ?>
                            </td>
                            <!-- Actions -->
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" onclick="editSpMember(<?= (int)$sm['sp_member_id'] ?>)"
                                        class="rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:border-blue-400 hover:bg-blue-50 hover:text-blue-600 transition"
                                        title="Edit">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    <button type="button" onclick="toggleStatus(<?= (int)$sm['sp_member_id'] ?>, '<?= addslashes($safeDisplay) ?>')"
                                        class="rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:border-emerald-400 hover:bg-emerald-50 hover:text-emerald-600 transition"
                                        title="<?= $sm['is_active'] ? 'Set Inactive' : 'Set Active' ?>">
                                        <?php if ($sm['is_active']): ?>
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
                                    <button type="button" onclick="deleteSpMember(<?= (int)$sm['sp_member_id'] ?>, '<?= addslashes($safeDisplay) ?>')"
                                        class="rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:border-red-400 hover:bg-red-50 hover:text-red-600 transition"
                                        title="Delete">
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

        <!-- PAGINATION -->
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
                if ($search !== '')         $query['search']      = $search;
                if ($filterStatus !== '')   $query['status']      = $filterStatus;
                if ($filterDistrict > 0)    $query['district_id'] = $filterDistrict;
                if ($filterPosition !== '') $query['position']    = $filterPosition;
                $queryString = !empty($query) ? '&' . http_build_query($query) : '';
                ?>
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $queryString ?>"
                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Prev</a>
                <?php endif; ?>
                <?php
                $startPage = max(1, $page - 2);
                $endPage   = min($totalPages, $page + 2);
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
                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Next</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </section>

</div>

<!-- ================================================================ -->
<!-- SP MEMBER CREATE / EDIT MODAL                                     -->
<!-- ================================================================ -->
<div id="spMemberModal" class="fixed inset-0 z-[100] hidden items-center justify-center" role="dialog" aria-modal="true">
    <div id="spMemberModalBackdrop" class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity opacity-0"></div>
    <div id="spMemberModalPanel"
         class="relative z-10 w-full max-w-2xl mx-4 bg-white rounded-2xl shadow-2xl border border-gray-100 transform scale-95 opacity-0 transition-all duration-200 max-h-[90vh] overflow-y-auto">

        <form id="spMemberForm" method="POST" action="<?= BASE_URL ?>/master/sp-members/store" enctype="multipart/form-data">
            <input type="hidden" name="id"                  id="spMemberId"          value="">
            <input type="hidden" name="existing_photo_path" id="smExistingPhotoPath" value="">

            <!-- Modal header -->
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5">
                <div>
                    <h3 id="spMemberModalTitle" class="text-lg font-semibold text-gray-900">Create New SP Member</h3>
                    <p class="mt-0.5 text-xs text-gray-500">Fill in the member details below.</p>
                </div>
                <button type="button" onclick="closeSpMemberModal()"
                        class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Modal body -->
            <div class="p-6 space-y-5">

                <!-- Photo upload -->
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Photo</label>
                    <div class="flex items-center gap-4">
                        <div id="smPhotoPreviewWrap"
                             class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary font-bold text-lg overflow-hidden ring-2 ring-gray-200">
                            <span id="smPhotoInitials"></span>
                            <img id="smPhotoPreviewImg" src="" alt="Preview" class="hidden h-full w-full object-cover">
                        </div>
                        <div class="flex-1">
                            <label for="smPhoto"
                                   class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                                <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                Choose Photo
                            </label>
                            <input type="file" name="photo" id="smPhoto"
                                   accept="image/jpeg,image/png,image/webp,image/gif"
                                   class="sr-only" onchange="previewPhoto(this)">
                            <p class="mt-1.5 text-[11px] text-gray-500">JPG, PNG, WebP or GIF · max 2 MB · optional</p>
                            <p id="smPhotoFileName" class="mt-0.5 text-[11px] text-primary font-medium hidden"></p>
                        </div>
                    </div>
                </div>

                <!-- First / Middle / Last name -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-6">
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">
                            First Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="first_name" id="smFirstName"
                               value="<?= old('first_name') ?>" placeholder="e.g. Juan"
                               class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                               required>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Middle Name</label>
                        <input type="text" name="middle_name" id="smMiddleName"
                               value="<?= old('middle_name') ?>" placeholder="e.g. Santos"
                               class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">
                            Last Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="last_name" id="smLastName"
                               value="<?= old('last_name') ?>" placeholder="e.g. Dela Cruz"
                               class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                               required>
                    </div>
                </div>

                <!-- Suffix + Position (dropdown) -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div class="sm:col-span-1">
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Suffix</label>
                        <input type="text" name="suffix" id="smSuffix"
                               value="<?= old('suffix') ?>" placeholder="Jr., Sr., III"
                               class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    </div>
                    <div class="sm:col-span-3">
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">
                            Position <span class="text-red-500">*</span>
                        </label>
                        <select name="position" id="smPosition"
                                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                required>
                            <option value="">— Select Position —</option>
                            <?php foreach ($positions as $pos): ?>
                                <option value="<?= htmlspecialchars($pos['name']) ?>"
                                        <?= old('position') === $pos['name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pos['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- District + Sort Order -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">District</label>
                        <select name="district_id" id="smDistrictId"
                                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                            <option value="">— No District —</option>
                            <?php foreach ($districts as $d): ?>
                                <option value="<?= (int)$d['id'] ?>">
                                    <?= htmlspecialchars($d['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="mt-1 text-[11px] text-gray-500">Optional — leave blank if not district-specific.</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Sort Order</label>
                        <input type="number" name="sort_order" id="smSortOrder"
                               min="0" value="<?= old('sort_order', '0') ?>"
                               class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <p class="mt-1 text-[11px] text-gray-500">Members with lower values appear first.</p>
                    </div>
                </div>

                <!-- Active toggle -->
                <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <input type="checkbox" name="is_active" id="smIsActive" value="1" checked
                           class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary">
                    <div>
                        <label for="smIsActive" class="cursor-pointer text-sm font-medium text-gray-800">Set as active</label>
                        <p class="text-[11px] text-gray-500 mt-0.5">Active members appear in assignment dropdowns.</p>
                    </div>
                </div>

            </div>

            <!-- Modal footer -->
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4 bg-gray-50 rounded-b-2xl">
                <button type="button" onclick="closeSpMemberModal()"
                        class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit" id="spMemberSubmitBtn"
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

<!-- ================================================================ -->
<!-- CONFIRMATION MODAL                                                -->
<!-- ================================================================ -->
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

<!-- Toast container -->
<div id="toastContainer" class="fixed top-4 right-4 z-[200] space-y-2 pointer-events-none"></div>

<!-- ================================================================ -->
<!-- JAVASCRIPT                                                        -->
<!-- ================================================================ -->
<script>
const BASE = '<?= BASE_URL ?>';

/* ------------------------------------------------------------------ */
/* Toast                                                                */
/* ------------------------------------------------------------------ */
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const colors    = { success: 'border-emerald-200 bg-emerald-50 text-emerald-800', error: 'border-red-200 bg-red-50 text-red-800', info: 'border-blue-200 bg-blue-50 text-blue-800' };
    const icons     = { success: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>', error: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>', info: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>' };
    const iColors   = { success: 'bg-emerald-100 text-emerald-600', error: 'bg-red-100 text-red-600', info: 'bg-blue-100 text-blue-600' };
    const t = type in colors ? type : 'info';
    const toast = document.createElement('div');
    toast.className = `pointer-events-auto max-w-sm rounded-xl border px-4 py-3 flex items-center gap-3 shadow-lg transform transition-all duration-300 translate-x-full ${colors[t]}`;
    toast.innerHTML = `<div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full ${iColors[t]}"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">${icons[t]}</svg></div><p class="text-sm font-medium flex-1">${message}</p><button type="button" onclick="this.parentElement.remove()" class="text-current opacity-60 hover:opacity-100"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>`;
    container.appendChild(toast);
    requestAnimationFrame(() => toast.classList.remove('translate-x-full'));
    setTimeout(() => { toast.classList.add('translate-x-full'); setTimeout(() => toast.remove(), 300); }, 4500);
}

/* ------------------------------------------------------------------ */
/* Photo preview and initials update                                    */
/* ------------------------------------------------------------------ */
function updatePhotoInitials() {
    const firstName = document.getElementById('smFirstName').value.trim();
    const lastName  = document.getElementById('smLastName').value.trim();
    const initials  = document.getElementById('smPhotoInitials');
    const img       = document.getElementById('smPhotoPreviewImg');
    
    // Only show initials if no photo is uploaded
    if (img.classList.contains('hidden')) {
        if (firstName && lastName) {
            // Both names: use first letter of each
            initials.textContent = (firstName[0] + lastName[0]).toUpperCase();
        } else if (firstName) {
            // Only first name: use first letter only
            initials.textContent = firstName[0].toUpperCase();
        } else {
            // No names: show empty
            initials.textContent = '';
        }
    }
}

function previewPhoto(input) {
    const file = input.files[0];
    if (!file) return;
    document.getElementById('smPhotoFileName').textContent = file.name;
    document.getElementById('smPhotoFileName').classList.remove('hidden');
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('smPhotoPreviewImg').src = e.target.result;
        document.getElementById('smPhotoPreviewImg').classList.remove('hidden');
        document.getElementById('smPhotoInitials').classList.add('hidden');
    };
    reader.readAsDataURL(file);
}

/* ------------------------------------------------------------------ */
/* Field validation helpers                                             */
/* ------------------------------------------------------------------ */
function setFieldError(fieldId, message) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    field.classList.remove('border-gray-200', 'focus:ring-primary/20', 'focus:border-primary');
    field.classList.add('border-red-400', 'focus:ring-red-400/20', 'focus:border-red-500');
    let errEl = field.parentElement.querySelector('.field-error');
    if (!errEl) { errEl = document.createElement('p'); errEl.className = 'field-error mt-1 text-xs text-red-600 font-medium'; field.parentElement.appendChild(errEl); }
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

function validateSpMemberForm() {
    let valid = true;
    const fn = document.getElementById('smFirstName');
    if (!fn.value.trim()) { setFieldError('smFirstName', 'First name is required.'); valid = false; }
    else clearFieldError('smFirstName');

    const ln = document.getElementById('smLastName');
    if (!ln.value.trim()) { setFieldError('smLastName', 'Last name is required.'); valid = false; }
    else clearFieldError('smLastName');

    const pos = document.getElementById('smPosition');
    if (!pos.value) { setFieldError('smPosition', 'Position is required.'); valid = false; }
    else clearFieldError('smPosition');

    const sort = document.getElementById('smSortOrder');
    const sv = parseInt(sort.value);
    if (sort.value !== '' && (isNaN(sv) || sv < 0)) { setFieldError('smSortOrder', 'Sort order must be a non-negative number.'); valid = false; }
    else clearFieldError('smSortOrder');

    return valid;
}

function attachSpMemberValidation() {
    ['smFirstName', 'smLastName', 'smPosition', 'smSortOrder'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input',  validateSpMemberForm);
        el.addEventListener('blur',   validateSpMemberForm);
        el.addEventListener('change', validateSpMemberForm);
    });
    // Attach initials update to name fields
    ['smFirstName', 'smLastName'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', updatePhotoInitials);
    });
    document.getElementById('spMemberForm')?.addEventListener('submit', e => {
        if (!validateSpMemberForm()) {
            e.preventDefault();
            showToast('Please fix the errors in the form.', 'error');
            const firstErr = document.querySelector('#spMemberModalPanel .border-red-400');
            if (firstErr) firstErr.focus();
        }
    });
}

/* ------------------------------------------------------------------ */
/* SP Member Modal — open / close / reset                              */
/* ------------------------------------------------------------------ */
let spMemberModalOpen = false;

function resetSpMemberForm() {
    document.getElementById('spMemberForm').reset();
    document.getElementById('spMemberId').value          = '';
    document.getElementById('smExistingPhotoPath').value = '';
    document.getElementById('smFirstName').value         = '';
    document.getElementById('smMiddleName').value        = '';
    document.getElementById('smLastName').value          = '';
    document.getElementById('smSuffix').value            = '';
    document.getElementById('smPosition').value          = '';
    document.getElementById('smDistrictId').value        = '';
    document.getElementById('smSortOrder').value         = '0';
    document.getElementById('smIsActive').checked        = true;
    // Reset photo preview
    document.getElementById('smPhotoPreviewImg').src = '';
    document.getElementById('smPhotoPreviewImg').classList.add('hidden');
    document.getElementById('smPhotoInitials').classList.remove('hidden');
    document.getElementById('smPhotoInitials').textContent = '';
    document.getElementById('smPhotoFileName').classList.add('hidden');
    document.getElementById('smPhotoFileName').textContent = '';
    document.getElementById('spMemberForm').action = `${BASE}/master/sp-members/store`;
    document.getElementById('spMemberModalTitle').textContent = 'Create New SP Member';
    document.getElementById('spMemberSubmitBtn').innerHTML = `
        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>Save`;
    ['smFirstName', 'smLastName', 'smPosition', 'smSortOrder'].forEach(clearFieldError);
    attachSpMemberValidation();
}

function openSpMemberModal() {
    if (spMemberModalOpen) return;
    resetSpMemberForm();
    spMemberModalOpen = true;
    const m = document.getElementById('spMemberModal');
    const b = document.getElementById('spMemberModalBackdrop');
    const p = document.getElementById('spMemberModalPanel');
    m.classList.remove('hidden'); m.classList.add('flex');
    requestAnimationFrame(() => {
        b.classList.remove('opacity-0');
        p.classList.remove('scale-95', 'opacity-0');
        p.classList.add('scale-100', 'opacity-100');
    });
    setTimeout(() => {
        document.getElementById('smFirstName').focus();
        updatePhotoInitials(); // Initialize initials display
    }, 220);
}

function closeSpMemberModal() {
    if (!spMemberModalOpen) return;
    spMemberModalOpen = false;
    const m = document.getElementById('spMemberModal');
    const b = document.getElementById('spMemberModalBackdrop');
    const p = document.getElementById('spMemberModalPanel');
    b.classList.add('opacity-0');
    p.classList.remove('scale-100', 'opacity-100');
    p.classList.add('scale-95', 'opacity-0');
    setTimeout(() => { m.classList.remove('flex'); m.classList.add('hidden'); }, 200);
}

document.getElementById('spMemberModalBackdrop')?.addEventListener('click', closeSpMemberModal);
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        if (spMemberModalOpen) closeSpMemberModal();
        if (confirmModalOpen)  closeConfirmModal();
    }
});

/* ------------------------------------------------------------------ */
/* Edit SP Member — AJAX populate (canonical pattern: set data first,  */
/* then open modal directly without calling resetSpMemberForm)         */
/* ------------------------------------------------------------------ */
async function editSpMember(id) {
    try {
        const res  = await fetch(`${BASE}/master/sp-members/edit?id=${id}`);
        const data = await res.json();
        if (!data.success) {
            showToast(data.message || 'SP member not found.', 'error');
            return;
        }
        const d = data.data;

        // Populate all fields
        document.getElementById('spMemberId').value          = d.sp_member_id;
        document.getElementById('smExistingPhotoPath').value = d.photo_path  ?? '';
        document.getElementById('smFirstName').value         = d.first_name  ?? '';
        document.getElementById('smMiddleName').value        = d.middle_name ?? '';
        document.getElementById('smLastName').value          = d.last_name   ?? '';
        document.getElementById('smSuffix').value            = d.suffix      ?? '';
        document.getElementById('smPosition').value          = d.position    ?? '';
        document.getElementById('smDistrictId').value        = d.district_id ?? '';
        document.getElementById('smSortOrder').value         = d.sort_order  ?? 0;
        document.getElementById('smIsActive').checked        = !!parseInt(d.is_active);

        // Photo preview
        const img  = document.getElementById('smPhotoPreviewImg');
        const init = document.getElementById('smPhotoInitials');
        if (d.photo_path) {
            img.src = `${BASE}/public/${d.photo_path}`;
            img.classList.remove('hidden');
            init.classList.add('hidden');
        } else {
            img.src = '';
            img.classList.add('hidden');
            init.classList.remove('hidden');
            init.textContent = ((d.first_name[0] || '') + (d.last_name[0] || '')).toUpperCase();
        }
        document.getElementById('smPhotoFileName').classList.add('hidden');

        // Swap form to update mode
        document.getElementById('spMemberForm').action = `${BASE}/master/sp-members/update`;
        document.getElementById('spMemberModalTitle').textContent = 'Edit SP Member';
        document.getElementById('spMemberSubmitBtn').innerHTML = `
            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>Update`;

        // Attach validation and initials update listeners
        attachSpMemberValidation();

        // Open modal (do NOT call resetSpMemberForm — data is already set)
        spMemberModalOpen = true;
        const m = document.getElementById('spMemberModal');
        const b = document.getElementById('spMemberModalBackdrop');
        const pn = document.getElementById('spMemberModalPanel');
        m.classList.remove('hidden'); m.classList.add('flex');
        requestAnimationFrame(() => {
            b.classList.remove('opacity-0');
            pn.classList.remove('scale-95', 'opacity-0');
            pn.classList.add('scale-100', 'opacity-100');
        });
    } catch (e) {
        showToast('Failed to load SP member data.', 'error');
    }
}

/* ------------------------------------------------------------------ */
/* Confirm Modal — canonical pattern from districts/positions          */
/* ------------------------------------------------------------------ */
let confirmModalOpen = false;
let pendingAction    = null;

function openConfirmModal({ title, message, confirmText = 'Confirm', cancelText = 'Cancel', type = 'danger', onConfirm }) {
    if (confirmModalOpen) return;
    document.getElementById('confirmTitle').textContent   = title;
    document.getElementById('confirmMessage').innerHTML   = message;
    const proceedBtn = document.getElementById('confirmProceedBtn');
    const cancelBtn  = document.getElementById('confirmCancelBtn');
    const iconBox    = document.getElementById('confirmIconBox');
    const icon       = document.getElementById('confirmIcon');
    if (type === 'danger') {
        proceedBtn.className = 'inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 transition shadow-sm';
        iconBox.className    = 'flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-red-100';
        icon.className       = 'h-6 w-6 text-red-600';
        icon.innerHTML       = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>';
    } else {
        proceedBtn.className = 'inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-white bg-primary hover:bg-blue-700 transition shadow-sm';
        iconBox.className    = 'flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-blue-100';
        icon.className       = 'h-6 w-6 text-blue-600';
        icon.innerHTML       = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>';
    }
    proceedBtn.textContent = confirmText;
    cancelBtn.textContent  = cancelText;
    pendingAction = onConfirm;
    confirmModalOpen = true;
    const m = document.getElementById('confirmModal');
    const b = document.getElementById('confirmModalBackdrop');
    const p = document.getElementById('confirmModalPanel');
    m.classList.remove('hidden'); m.classList.add('flex');
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
    setTimeout(() => { m.classList.remove('flex'); m.classList.add('hidden'); }, 200);
}

function executeConfirmAction() {
    const action = pendingAction;
    closeConfirmModal();
    if (typeof action === 'function') {
        action();
    }
}

document.getElementById('confirmModalBackdrop')?.addEventListener('click', closeConfirmModal);

/* ------------------------------------------------------------------ */
/* Toggle Status                                                        */
/* ------------------------------------------------------------------ */
function toggleStatus(id, name) {
    openConfirmModal({
        title:       'Toggle Status',
        message:     `This will toggle the active status of <strong class="text-gray-800">${name}</strong>.<br><span class="text-xs text-gray-500">Inactive members are hidden from selection dropdowns.</span>`,
        confirmText: 'Proceed',
        cancelText:  'Cancel',
        type:        'info',
        onConfirm:   async () => {
            try {
                const fd = new FormData();
                fd.append('id', id);
                const res  = await fetch(`${BASE}/master/sp-members/toggle-status`, { method: 'POST', body: fd });
                const json = await res.json();
                showToast(json.message || 'Status updated.', json.success ? 'success' : 'error');
                if (json.success) setTimeout(() => location.reload(), 800);
            } catch {
                showToast('An error occurred while updating status.', 'error');
            }
        },
    });
}

/* ------------------------------------------------------------------ */
/* Delete                                                               */
/* ------------------------------------------------------------------ */
function deleteSpMember(id, name) {
    openConfirmModal({
        title:       'Delete SP Member',
        message:     `Are you sure you want to delete <strong class="text-gray-800">${name}</strong>?<br><span class="text-xs text-gray-500">This action soft-deletes the record and hides it from dropdowns.</span>`,
        confirmText: 'Delete',
        cancelText:  'Cancel',
        type:        'danger',
        onConfirm:   async () => {
            try {
                const fd = new FormData();
                fd.append('id', id);
                const res  = await fetch(`${BASE}/master/sp-members/destroy`, { method: 'POST', body: fd });
                const json = await res.json();
                showToast(json.message || 'Member deleted.', json.success ? 'success' : 'error');
                if (json.success) setTimeout(() => location.reload(), 900);
            } catch {
                showToast('An error occurred while deleting the member.', 'error');
            }
        },
    });
}

/* ------------------------------------------------------------------ */
/* Flash boot                                                           */
/* ------------------------------------------------------------------ */
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
?>

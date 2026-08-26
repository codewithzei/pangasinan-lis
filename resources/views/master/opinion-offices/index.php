<?php
ob_start();

$success = $success ?? null;
$error = $error ?? null;
$pageTitle = $pageTitle ?? 'Opinion Offices Management';
$pageSubtitle = $pageSubtitle ?? '';
$totalOpinionOffices = $totalOpinionOffices ?? 0;
$activeOpinionOffices = $activeOpinionOffices ?? 0;
$inactiveOpinionOffices = $inactiveOpinionOffices ?? 0;
$totalRows = $totalRows ?? 0;
$search = $search ?? '';
$filterStatus = $filterStatus ?? '';
$opinionOffices = $opinionOffices ?? [];
$totalPages = $totalPages ?? 1;
$page = $page ?? 1;
$accent = $accent ?? 'primary';
?>

<div class="space-y-6">

    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-700 via-primary to-blue-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-sm font-medium text-blue-100">EXTERNAL OFFICES MASTER DATA</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                        <?= htmlspecialchars($pageTitle) ?>
                    </h1>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-blue-100">
                        <?= htmlspecialchars($pageSubtitle) ?>
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" onclick="openOpinionOfficeModal()"
                        class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary shadow-sm hover:bg-blue-50 transition">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        New Opinion Office
                    </button>
                </div>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
        </div>
    </section>

    <section>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Total Opinion Offices</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($totalOpinionOffices) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-primary">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-blue-50 px-2 py-1 font-medium text-primary">All designations</span>
                </div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Active Opinion Offices</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($activeOpinionOffices) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-emerald-50 px-2 py-1 font-medium text-emerald-600">Available in dropdowns</span>
                </div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Inactive Opinion Offices</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($inactiveOpinionOffices) ?></p>
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

    <section class="rounded-2xl border border-gray-200 bg-white">
        <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900">Opinion Offices List</h2>
                <p class="mt-1 text-xs text-gray-500">
                    <?= $totalRows ?> record<?= $totalRows !== 1 ? 's' : '' ?> found
                </p>
            </div>
            <form method="GET" action="<?= BASE_URL ?>/master/opinion-offices" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, abbreviation..."
                        class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 sm:w-64">
                </div>
                <select name="status" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">All Status</option>
                    <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
                <button type="submit" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                    Filter
                </button>
                <?php if ($search !== '' || $filterStatus !== ''): ?>
                    <a href="<?= BASE_URL ?>/master/opinion-offices"
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
                        <th class="px-6 py-3 font-medium">Opinion Office</th>
                        <th class="px-6 py-3 font-medium">Abbreviation</th>
                        <th class="px-6 py-3 font-medium">Sort</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($opinionOffices)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">No opinion offices found.</p>
                                <p class="mt-1 text-xs text-gray-500">Create your first opinion office to populate dropdowns.</p>
                                <button type="button" onclick="openOpinionOfficeModal()"
                                    class="mt-5 inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Create Opinion Office
                                </button>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($opinionOffices as $oo): ?>
                        <tr class="hover:bg-gray-50/50 transition">
                            <td class="px-6 py-4">
                                <div>
                                    <div class="font-medium text-gray-900">
                                        <?= htmlspecialchars($oo['name']) ?>
                                        <?php if (!empty($oo['abbreviation'])): ?>
                                            <span class="ml-2 inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-700">
                                                <?= htmlspecialchars($oo['abbreviation']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <?php if (!empty($oo['abbreviation'])): ?>
                                    <span class="inline-flex items-center rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">
                                        <?= htmlspecialchars($oo['abbreviation']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center rounded-lg bg-gray-50 border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-600">
                                    #<?= (int)$oo['sort_order'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($oo['is_active']): ?>
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
                                    <button type="button" onclick="editOpinionOffice(<?= $oo['id'] ?>)"
                                        class="rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:border-indigo-400 hover:bg-indigo-50 hover:text-indigo-600 transition"
                                        title="Edit">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    <button type="button" onclick="toggleStatus(<?= $oo['id'] ?>, '<?= htmlspecialchars($oo['name']) ?>')"
                                        class="rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:border-emerald-400 hover:bg-emerald-50 hover:text-emerald-600 transition"
                                        title="<?= $oo['is_active'] ? 'Set Inactive' : 'Set Active' ?>">
                                        <?php if ($oo['is_active']): ?>
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
                                    <button type="button" onclick="deleteOpinionOffice(<?= $oo['id'] ?>, '<?= htmlspecialchars($oo['name']) ?>')"
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

<!-- Opinion Office Create/Edit Modal -->
<div id="opinionOfficeModal" class="fixed inset-0 z-[100] hidden items-center justify-center" role="dialog" aria-modal="true">
    <div id="opinionOfficeModalBackdrop" class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity opacity-0"></div>
    <div id="opinionOfficeModalPanel" class="relative z-10 w-full max-w-xl mx-4 bg-white rounded-2xl shadow-2xl border border-gray-100 transform scale-95 opacity-0 transition-all duration-200 max-h-[90vh] overflow-y-auto">
        <form id="opinionOfficeForm" method="POST" action="<?= BASE_URL ?>/master/opinion-offices/store">
            <input type="hidden" name="id" id="opinionOfficeId" value="">
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5">
                <div>
                    <h3 id="opinionOfficeModalTitle" class="text-lg font-semibold text-gray-900">Create New Opinion Office</h3>
                    <p class="mt-0.5 text-xs text-gray-500">Fill in the opinion office details below.</p>
                </div>
                <button type="button" onclick="closeOpinionOfficeModal()" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="p-6 space-y-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">
                            Opinion Office Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="name" id="opinionOfficeName" value="<?= old('name') ?>"
                            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                            placeholder="e.g. Civil Service Commission" required>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">
                            Abbreviation
                        </label>
                        <input type="text" name="abbreviation" id="opinionOfficeAbbreviation" value="<?= old('abbreviation') ?>"
                            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                            placeholder="e.g. CSC">
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">
                            Sort Order
                        </label>
                        <input type="number" name="sort_order" id="sortOrder" min="0" value="<?= old('sort_order', '0') ?>"
                            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                            placeholder="0">
                        <p class="mt-1 text-[11px] text-gray-500">Opinion offices with lower values appear first in dropdowns.</p>
                    </div>
                    <div class="flex items-end">
                        <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 w-full">
                            <input type="checkbox" name="is_active" id="isActive" value="1" checked
                                class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary">
                            <div>
                                <label for="isActive" class="cursor-pointer text-sm font-medium text-gray-800">Set as active</label>
                                <p class="text-[11px] text-gray-500 mt-0.5">Active opinion offices appear in selection dropdowns.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4 bg-gray-50 rounded-b-2xl">
                <button type="button" onclick="closeOpinionOfficeModal()"
                    class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit" id="opinionOfficeSubmitBtn"
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

<!-- Confirmation Modal -->
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

function validateOpinionOfficeForm() {
    let valid = true;

    const name = document.getElementById('opinionOfficeName');
    if (name.value.trim() === '') {
        setFieldError('opinionOfficeName', 'Opinion office name is required.');
        valid = false;
    } else clearFieldError('opinionOfficeName');

    const abbr = document.getElementById('opinionOfficeAbbreviation');
    if (abbr.value.length > 50) {
        setFieldError('opinionOfficeAbbreviation', 'Abbreviation too long (max 50 chars).');
        valid = false;
    } else clearFieldError('opinionOfficeAbbreviation');

    const sort = document.getElementById('sortOrder');
    const sortVal = parseInt(sort.value);
    if (sort.value !== '' && (isNaN(sortVal) || sortVal < 0)) {
        setFieldError('sortOrder', 'Sort order must be a non-negative number.');
        valid = false;
    } else clearFieldError('sortOrder');

    return valid;
}

function attachOpinionOfficeValidation() {
    const fields = ['opinionOfficeName', 'opinionOfficeAbbreviation', 'sortOrder'];
    fields.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', validateOpinionOfficeForm);
        el.addEventListener('blur', validateOpinionOfficeForm);
        el.addEventListener('change', validateOpinionOfficeForm);
    });
    document.getElementById('opinionOfficeForm')?.addEventListener('submit', (e) => {
        if (!validateOpinionOfficeForm()) {
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

// Opinion Office Modal
let opinionOfficeModalOpen = false;
function resetOpinionOfficeForm() {
    const form = document.getElementById('opinionOfficeForm');
    form.reset();
    document.getElementById('opinionOfficeId').value = '';
    document.getElementById('opinionOfficeName').value = '';
    document.getElementById('opinionOfficeAbbreviation').value = '';
    document.getElementById('sortOrder').value = '0';
    document.getElementById('isActive').checked = true;
    form.action = '<?= BASE_URL ?>/master/opinion-offices/store';
    document.getElementById('opinionOfficeModalTitle').textContent = 'Create New Opinion Office';
    document.getElementById('opinionOfficeSubmitBtn').innerHTML = `
        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        Save
    `;
    ['opinionOfficeName', 'opinionOfficeAbbreviation', 'sortOrder'].forEach(clearFieldError);
    attachOpinionOfficeValidation();
}
function openOpinionOfficeModal() {
    if (opinionOfficeModalOpen) return;
    resetOpinionOfficeForm();
    opinionOfficeModalOpen = true;
    const m = document.getElementById('opinionOfficeModal');
    const b = document.getElementById('opinionOfficeModalBackdrop');
    const p = document.getElementById('opinionOfficeModalPanel');
    m.classList.remove('hidden');
    m.classList.add('flex');
    requestAnimationFrame(() => {
        b.classList.remove('opacity-0');
        p.classList.remove('scale-95', 'opacity-0');
        p.classList.add('scale-100', 'opacity-100');
    });
    setTimeout(() => document.getElementById('opinionOfficeName').focus(), 220);
}
function closeOpinionOfficeModal() {
    if (!opinionOfficeModalOpen) return;
    opinionOfficeModalOpen = false;
    const m = document.getElementById('opinionOfficeModal');
    const b = document.getElementById('opinionOfficeModalBackdrop');
    const p = document.getElementById('opinionOfficeModalPanel');
    b.classList.add('opacity-0');
    p.classList.remove('scale-100', 'opacity-100');
    p.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        m.classList.remove('flex');
        m.classList.add('hidden');
    }, 200);
}
document.getElementById('opinionOfficeModalBackdrop')?.addEventListener('click', closeOpinionOfficeModal);
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        if (opinionOfficeModalOpen) closeOpinionOfficeModal();
        if (confirmModalOpen) closeConfirmModal();
    }
});

async function editOpinionOffice(id) {
    try {
        const res = await fetch(`<?= BASE_URL ?>/master/opinion-offices/edit?id=${id}`);
        const data = await res.json();
        if (!data.success) {
            showToast(data.message || 'Opinion office not found.', 'error');
            return;
        }
        const eo = data.data;
        document.getElementById('opinionOfficeId').value = eo.id;
        document.getElementById('opinionOfficeName').value = eo.name;
        document.getElementById('opinionOfficeAbbreviation').value = eo.abbreviation || '';
        document.getElementById('sortOrder').value = eo.sort_order ?? 0;
        document.getElementById('isActive').checked = !!eo.is_active;
        document.getElementById('opinionOfficeForm').action = '<?= BASE_URL ?>/master/opinion-offices/update';
        document.getElementById('opinionOfficeModalTitle').textContent = 'Edit Opinion Office';
        document.getElementById('opinionOfficeSubmitBtn').innerHTML = `
            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            Update
        `;
        opinionOfficeModalOpen = true;
        const m = document.getElementById('opinionOfficeModal');
        const b = document.getElementById('opinionOfficeModalBackdrop');
        const pn = document.getElementById('opinionOfficeModalPanel');
        m.classList.remove('hidden');
        m.classList.add('flex');
        requestAnimationFrame(() => {
            b.classList.remove('opacity-0');
            pn.classList.remove('scale-95', 'opacity-0');
            pn.classList.add('scale-100', 'opacity-100');
        });
    } catch (e) {
        showToast('Failed to load opinion office data.', 'error');
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

async function deleteOpinionOffice(id, name) {
    openConfirmModal({
        title: 'Delete Opinion Office',
        message: `Are you sure you want to delete <strong class="text-gray-800">${name}</strong>?<br><span class="text-xs text-gray-500">This action soft-deletes the record and hides it from dropdowns.</span>`,
        confirmText: 'Delete',
        type: 'danger',
        onConfirm: async () => {
            try {
                const fd = new FormData();
                fd.append('id', id);
                const res = await fetch('<?= BASE_URL ?>/master/opinion-offices/destroy', { method: 'POST', body: fd });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) setTimeout(() => location.reload(), 900);
            } catch (e) { showToast('Network error.', 'error'); }
        }
    });
}

async function toggleStatus(id, name) {
    openConfirmModal({
        title: 'Toggle Status',
        message: `This will toggle the active status of <strong class="text-gray-800">${name}</strong>.<br><span class="text-xs text-gray-500">Inactive opinion offices are hidden from selection dropdowns.</span>`,
        confirmText: 'Proceed',
        type: 'info',
        onConfirm: async () => {
            try {
                const fd = new FormData();
                fd.append('id', id);
                const res = await fetch('<?= BASE_URL ?>/master/opinion-offices/toggle-status', { method: 'POST', body: fd });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) setTimeout(() => location.reload(), 800);
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

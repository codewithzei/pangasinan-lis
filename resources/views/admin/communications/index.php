<?php
ob_start();

$success                 = $success                 ?? null;
$error                   = $error                   ?? null;
$pageTitle               = $pageTitle               ?? 'Communications';
$pageSubtitle            = $pageSubtitle            ?? '';
$totalNoted              = $totalNoted              ?? 0;
$totalRows               = $totalRows               ?? 0;
$search                  = $search                  ?? '';
$filterStatus            = $filterStatus            ?? '';
$filterCat               = $filterCat               ?? '';
$documents               = $documents               ?? [];
$documentStatuses        = $documentStatuses        ?? [];
$communicationCategories = $communicationCategories ?? [];
$totalPages              = $totalPages              ?? 1;
$page                    = $page                    ?? 1;
?>

<div class="space-y-6">

    <!-- ── Header Banner ────────────────────────────────────────────────────── -->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-sm font-medium text-blue-100">DOCUMENT TRACKING</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                        <?= htmlspecialchars($pageTitle) ?>
                    </h1>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-blue-100">
                        <?= htmlspecialchars($pageSubtitle) ?>
                    </p>
                </div>
                <div>
                    <a href="<?= BASE_URL ?>/admin/inbox"
                       class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm
                              font-semibold text-emerald-700 hover:bg-emerald-50 transition shadow-sm">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0
                                     01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0
                                     01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0
                                     006.586 13H4"/>
                        </svg>
                        Back to Inbox
                    </a>
                </div>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
        </div>
    </section>

    <!-- ── Flash messages ──────────────────────────────────────────────────── -->
    <?php if (!empty($success)): ?>
        <div class="rounded-2xl border border-green-200 bg-green-50 p-4">
            <div class="flex items-start">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-green-100">
                    <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <div class="ml-3 flex-1">
                    <p class="text-sm font-medium text-green-800"><?= htmlspecialchars($success) ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="rounded-2xl border border-red-200 bg-red-50 p-4">
            <div class="flex items-start">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-100">
                    <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </div>
                <div class="ml-3 flex-1">
                    <p class="text-sm font-medium text-red-800"><?= htmlspecialchars($error) ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ── Statistics ──────────────────────────────────────────────────────── -->
    <section>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">

            <!-- Total Noted -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Total Communications</p>
                        <p class="mt-2 text-3xl font-bold text-emerald-700"><?= number_format($totalNoted) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-emerald-50 px-2 py-1 font-medium text-emerald-700">
                        Marked as Noted
                    </span>
                </div>
            </div>

            <!-- Filtered count -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Filtered Results</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($totalRows) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 text-gray-500">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13
                                     13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293
                                     6.707A1 1 0 013 6V4z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-gray-100 px-2 py-1 font-medium text-gray-600">Current View</span>
                </div>
            </div>

            <!-- Categories -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Categories Available</p>
                        <p class="mt-2 text-3xl font-bold text-teal-700">
                            <?= number_format(count($communicationCategories)) ?>
                        </p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-teal-50 text-teal-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0
                                     010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0
                                     013 12V7a4 4 0 014-4z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-teal-50 px-2 py-1 font-medium text-teal-700">
                        Communication Types
                    </span>
                </div>
            </div>

        </div>
    </section>

    <!-- ── Documents Table ─────────────────────────────────────────────────── -->
    <section class="rounded-2xl border border-gray-200 bg-white shadow-sm">

        <!-- Filters bar -->
        <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5
                    lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900">Admin Communications</h2>
                <p class="mt-1 text-xs text-gray-500">
                    <?= $totalRows ?> record<?= $totalRows !== 1 ? 's' : '' ?> found
                </p>
            </div>

            <form method="GET" action="<?= BASE_URL ?>/admin/communications"
                  class="flex flex-col gap-3 lg:flex-row lg:items-center">

                <!-- Search -->
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                           placeholder="Search tracking # or subject…"
                           class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3
                                  text-sm text-gray-800 placeholder-gray-400 focus:border-primary
                                  focus:outline-none focus:ring-2 focus:ring-primary/20 lg:w-56">
                </div>

                <!-- Status filter -->
                <select name="status"
                        class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                               text-gray-800 focus:border-primary focus:outline-none
                               focus:ring-2 focus:ring-primary/20">
                    <option value="">All Statuses</option>
                    <?php foreach ($documentStatuses as $st): ?>
                        <option value="<?= (int) $st['id'] ?>"
                            <?= ($filterStatus == $st['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($st['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Category filter -->
                <select name="category"
                        class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                               text-gray-800 focus:border-primary focus:outline-none
                               focus:ring-2 focus:ring-primary/20">
                    <option value="">All Categories</option>
                    <?php foreach ($communicationCategories as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>"
                            <?= ($filterCat == $cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit"
                        class="rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-sm
                               font-medium text-gray-700 hover:bg-gray-50 transition">
                    Filter
                </button>

                <?php if ($search !== '' || $filterStatus !== '' || $filterCat !== ''): ?>
                    <a href="<?= BASE_URL ?>/admin/communications"
                       class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                              font-medium text-gray-700 hover:bg-gray-50 transition text-center">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Tracking Number</th>
                        <th class="px-6 py-3 font-medium">Subject Matter</th>
                        <th class="px-6 py-3 font-medium">Document Type</th>
                        <th class="px-6 py-3 font-medium">Communication Category</th>
                        <th class="px-6 py-3 font-medium">Date Noted</th>
                        <th class="px-6 py-3 font-medium">Current Status</th>
                        <th class="px-6 py-3 font-medium">Noted By</th>
                        <th class="px-6 py-3 font-medium text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($documents)): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center
                                            rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                              d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9
                                                 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512
                                                 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">No communications found.</p>
                                <p class="mt-1 text-xs text-gray-500">
                                    <?= ($search !== '' || $filterStatus !== '' || $filterCat !== '')
                                        ? 'Try adjusting your search criteria or clearing filters.'
                                        : 'Communication documents marked as Noted will appear here.' ?>
                                </p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($documents as $doc): ?>
                            <?php $statusColor = !empty($doc['status_badge_color'])
                                ? $doc['status_badge_color'] : '#6B7280'; ?>
                            <tr class="hover:bg-gray-50/50 transition">

                                <!-- Tracking number -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-900">
                                        <?= htmlspecialchars($doc['tracking_number']) ?>
                                    </div>
                                    <div class="mt-0.5 text-xs text-gray-400">
                                        Received: <?= htmlspecialchars(date('M j, Y', strtotime($doc['date_received']))) ?>
                                    </div>
                                </td>

                                <!-- Subject matter -->
                                <td class="px-6 py-4 max-w-xs">
                                    <div class="text-sm font-medium text-gray-900 line-clamp-2">
                                        <?= htmlspecialchars($doc['subject_matter']) ?>
                                    </div>
                                </td>

                                <!-- Document type -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                          style="background-color: <?= htmlspecialchars($doc['document_type_badge_color'] ?? '#2563EB') ?>1a;
                                                 color: <?= htmlspecialchars($doc['document_type_badge_color'] ?? '#2563EB') ?>;">
                                        <?= htmlspecialchars($doc['document_type_name'] ?? '—') ?>
                                    </span>
                                </td>

                                <!-- Communication category -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if (!empty($doc['communication_category_name'])): ?>
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50
                                                     px-2.5 py-0.5 text-xs font-medium text-emerald-700
                                                     border border-emerald-200">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0
                                                         010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0
                                                         013 12V7a4 4 0 014-4z"/>
                                            </svg>
                                            <?= htmlspecialchars($doc['communication_category_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Date noted -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars(date('M j, Y', strtotime($doc['noted_at']))) ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?= htmlspecialchars(date('g:i A', strtotime($doc['noted_at']))) ?>
                                    </div>
                                </td>

                                <!-- Status -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border
                                                 px-2.5 py-0.5 text-xs font-semibold shadow-sm"
                                          style="background-color: <?= htmlspecialchars($statusColor) ?>;
                                                 border-color: <?= htmlspecialchars($statusColor) ?>;
                                                 color: white;">
                                        <?= htmlspecialchars($doc['status'] ?? '—') ?>
                                    </span>
                                </td>

                                <!-- Noted by -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($doc['routed_by_username'] ?? '—') ?>
                                    </div>
                                    <?php if (!empty(trim($doc['routed_by_fullname'] ?? ''))): ?>
                                        <div class="text-xs text-gray-400">
                                            <?= htmlspecialchars(trim($doc['routed_by_fullname'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Action -->
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <a href="<?= BASE_URL ?>/admin/communications/show?id=<?= (int) $doc['id'] ?>"
                                       class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200
                                              px-3 py-1.5 text-xs font-medium text-gray-700
                                              hover:border-emerald-500 hover:bg-emerald-50
                                              hover:text-emerald-700 transition"
                                       title="View Details">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943
                                                     9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943
                                                     -9.542-7z"/>
                                        </svg>
                                        View Details
                                    </a>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="flex flex-col items-center justify-between gap-3 border-t border-gray-100
                    px-6 py-4 sm:flex-row">
            <p class="text-xs text-gray-500">
                Showing page <span class="font-medium text-gray-700"><?= $page ?></span> of
                <span class="font-medium text-gray-700"><?= $totalPages ?></span>
                (<?= $totalRows ?> total records)
            </p>
            <div class="flex items-center gap-1">
                <?php
                $qArgs = [];
                if ($search !== '')       $qArgs['search']   = $search;
                if ($filterStatus !== '') $qArgs['status']   = $filterStatus;
                if ($filterCat !== '')    $qArgs['category'] = $filterCat;
                $qs = !empty($qArgs) ? '&' . http_build_query($qArgs) : '';
                ?>
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $qs ?>"
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
                    <a href="?page=<?= $i ?><?= $qs ?>"
                       class="rounded-lg border px-3 py-1.5 text-sm font-medium transition
                              <?= $i === $page
                                  ? 'border-primary bg-primary text-white'
                                  : 'border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $qs ?>"
                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium
                              text-gray-700 hover:bg-gray-50 transition">
                        Next
                    </a>
                <?php endif; ?>
            </div>
        </div>

    </section>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

<?php
/**
 * Archived Records — List page
 *
 * Variables from ArchivedRecordsController::index():
 *   $records        array   Paginated list rows
 *   $total          int
 *   $totalCount     int     Total unfiltered records
 *   $totalPages     int
 *   $page           int
 *   $search         string
 *   $filterType     string
 *   $filterYear     string
 *   $documentTypes  array   For filter dropdown
 *   $years          array   Available years
 *   $spMembers      array   For create modal
 *   $committees     array   For create modal
 *   $pageTitle      string
 */

$records       = $records       ?? [];
$pageTitle     = $pageTitle     ?? 'Archived Records';
$total         = $total         ?? 0;
$totalCount    = $totalCount    ?? 0;
$totalPages    = $totalPages    ?? 1;
$page          = $page          ?? 1;
$search        = $search        ?? '';
$filterType    = $filterType    ?? '';
$filterYear    = $filterYear    ?? '';
$documentTypes = $documentTypes ?? [];
$years         = $years         ?? [];
$spMembers     = $spMembers     ?? [];
$committees    = $committees    ?? [];

$hasFilter = ($search !== '' || $filterType !== '' || $filterYear !== '');

// Build query-string helper for pagination links
function arQueryString(array $extra = []): string {
    global $search, $filterType, $filterYear;
    $q = [];
    if ($search !== '')      $q['search'] = $search;
    if ($filterType !== '')  $q['type']   = $filterType;
    if ($filterYear !== '')  $q['year']   = $filterYear;
    $q = array_merge($q, $extra);
    return $q ? ('?' . http_build_query($q)) : '';
}

ob_start();
?>

<div class="space-y-6">

    <!-- ── Page header ─────────────────────────────────────────────────────── -->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="max-w-2xl">
                    <p class="text-sm font-medium text-blue-100">ADMIN / DOCUMENT MANAGEMENT</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                        <?= htmlspecialchars($pageTitle) ?>
                    </h1>
                    <p class="mt-2 text-sm leading-6 text-blue-100">
                        <?= htmlspecialchars($pageSubtitle ?? '') ?>
                    </p>
                </div>
                <div class="shrink-0">
                    <button type="button" onclick="openCreateModal()"
                            class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-4 py-2.5 text-sm font-semibold
                                   text-white hover:bg-white/25 transition ring-1 ring-white/30">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        New Record
                    </button>
                </div>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
        </div>
    </section>

    <!-- ── Stats card ──────────────────────────────────────────────────────── -->
    <section>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Total Records</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($totalCount) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-primary">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Showing</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($total) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Page</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= $page ?><span class="text-lg text-gray-400"> / <?= $totalPages ?></span></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Active Filters</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= $hasFilter ? '✓' : '—' ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl <?= $hasFilter ? 'bg-purple-50 text-purple-600' : 'bg-gray-100 text-gray-400' ?>">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ── Table section ───────────────────────────────────────────────────── -->
    <section class="rounded-2xl border border-gray-200 bg-white">

        <!-- Filters bar -->
        <div class="border-b border-gray-100 px-6 py-5">
            <form method="GET" action="<?= BASE_URL ?>/admin/archived-records"
                  class="flex flex-wrap gap-3 items-end">

                <!-- Search -->
                <div class="min-w-[240px] flex-1">
                    <label class="mb-1 block text-xs font-medium text-gray-500">Search</label>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                               placeholder="Search number, subject, type, tags, authors, or committee…"
                               class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm
                                      text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none
                                      focus:ring-2 focus:ring-primary/20">
                    </div>
                </div>

                <!-- Document type -->
                <div class="min-w-[160px]">
                    <label class="mb-1 block text-xs font-medium text-gray-500">Document Type</label>
                    <select name="type"
                            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800
                                   focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <option value="">All Types</option>
                        <?php foreach ($documentTypes as $dt): ?>
                            <option value="<?= (int) $dt['id'] ?>"
                                <?= (string) $filterType === (string) $dt['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dt['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Year -->
                <div class="min-w-[110px]">
                    <label class="mb-1 block text-xs font-medium text-gray-500">Year</label>
                    <select name="year"
                            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800
                                   focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <option value="">All Years</option>
                        <?php foreach ($years as $yr): ?>
                            <option value="<?= (int) $yr ?>"
                                <?= (string) $filterYear === (string) $yr ? 'selected' : '' ?>>
                                <?= (int) $yr ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex items-end gap-2 pb-0">
                    <button type="submit"
                            class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white
                                   hover:bg-primary/90 transition">
                        Filter
                    </button>
                    <?php if ($hasFilter): ?>
                        <a href="<?= BASE_URL ?>/admin/archived-records"
                           class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium
                                  text-gray-600 hover:bg-gray-50 transition">
                            Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Table header row count -->
        <div class="flex items-center justify-between px-6 py-3 border-b border-gray-100">
            <p class="text-xs text-gray-500">
                <span class="font-medium text-gray-700"><?= number_format($total) ?></span>
                record<?= $total !== 1 ? 's' : '' ?> found<?= $hasFilter ? ' (filtered)' : '' ?>
            </p>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th class="px-6 py-4">Approved No.</th>
                        <th class="px-6 py-4">Subject Matter</th>
                        <th class="px-6 py-4">Type</th>
                        <th class="px-6 py-4">Authors</th>
                        <th class="px-6 py-4">Tags</th>
                        <th class="px-6 py-4">Date Approved</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                              d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">
                                    <?= $hasFilter ? 'No records match your filters.' : 'No archived records yet.' ?>
                                </p>
                                <?php if ($hasFilter): ?>
                                    <p class="mt-1 text-xs text-gray-500">Try adjusting or clearing your filters.</p>
                                <?php else: ?>
                                    <p class="mt-1 text-xs text-gray-500">Click "New Record" to add the first entry.</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $row): ?>
                            <?php
                            $badgeColor  = $row['document_type_badge_color'] ?? '#2563EB';
                            $tagList     = $row['tags'] ? array_filter(array_map('trim', explode(',', $row['tags']))) : [];
                            $authors     = $row['authors_summary'] ?? null;
                            $attachCount = (int) ($row['attachment_count'] ?? 0);
                            ?>
                            <tr class="hover:bg-gray-50/60 transition-colors">

                                <!-- Approved No -->
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="font-semibold text-gray-900">
                                        <?= htmlspecialchars($row['approved_no']) ?>
                                    </div>
                                    <?php if ($attachCount > 0): ?>
                                        <div class="mt-1 flex items-center gap-1 text-xs text-gray-400">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                            </svg>
                                            <?= $attachCount ?> file<?= $attachCount !== 1 ? 's' : '' ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Subject Matter -->
                                <td class="px-6 py-4">
                                    <p class="max-w-xs leading-snug text-gray-800 line-clamp-2">
                                        <?= htmlspecialchars($row['subject_matter']) ?>
                                    </p>
                                </td>

                                <!-- Document Type -->
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                                          style="background-color: <?= htmlspecialchars($badgeColor) ?>18; color: <?= htmlspecialchars($badgeColor) ?>;">
                                        <?= htmlspecialchars($row['document_type_name'] ?? 'Unknown') ?>
                                    </span>
                                </td>

                                <!-- Authors -->
                                <td class="px-6 py-4">
                                    <?php if ($authors): ?>
                                        <p class="max-w-[160px] truncate text-sm text-gray-700" title="<?= htmlspecialchars($authors) ?>">
                                            <?= htmlspecialchars($authors) ?>
                                        </p>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400 italic">No authors</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Tags -->
                                <td class="px-6 py-4">
                                    <?php if (!empty($tagList)): ?>
                                        <div class="flex flex-wrap gap-1">
                                            <?php foreach (array_slice($tagList, 0, 3) as $tag): ?>
                                                <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                                                    <?= htmlspecialchars($tag) ?>
                                                </span>
                                            <?php endforeach; ?>
                                            <?php if (count($tagList) > 3): ?>
                                                <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">
                                                    +<?= count($tagList) - 3 ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400 italic">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Date Approved -->
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                    <?= htmlspecialchars(date('M j, Y', strtotime($row['date_approved']))) ?>
                                </td>

                                <!-- Actions -->
                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="<?= BASE_URL ?>/admin/archived-records/show?id=<?= (int) $row['id'] ?>"
                                           class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5
                                                  text-xs font-medium text-gray-700 hover:border-primary hover:bg-blue-50
                                                  hover:text-primary transition"
                                           title="View details">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            View
                                        </a>
                                        <button type="button"
                                                onclick="openDeleteModal(<?= (int) $row['id'] ?>, '<?= addslashes(htmlspecialchars($row['approved_no'])) ?>')"
                                                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5
                                                       text-xs font-medium text-gray-700 hover:border-red-300 hover:bg-red-50
                                                       hover:text-red-600 transition"
                                                title="Delete">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                            Delete
                                        </button>
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
        <div class="flex flex-col items-center justify-between gap-3 border-t border-gray-100 px-6 py-4 sm:flex-row">
            <p class="text-xs text-gray-500">
                Page <span class="font-medium text-gray-700"><?= $page ?></span> of
                <span class="font-medium text-gray-700"><?= $totalPages ?></span>
                (<?= number_format($total) ?> records)
            </p>
            <div class="flex items-center gap-1">
                <?php if ($page > 1): ?>
                    <a href="<?= BASE_URL ?>/admin/archived-records<?= arQueryString(['page' => $page - 1]) ?>"
                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                        Prev
                    </a>
                <?php endif; ?>
                <?php
                $startPage = max(1, $page - 2);
                $endPage   = min($totalPages, $page + 2);
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                    <a href="<?= BASE_URL ?>/admin/archived-records<?= arQueryString(['page' => $i]) ?>"
                       class="rounded-lg border px-3 py-1.5 text-sm font-medium transition <?= $i === $page
                           ? 'border-primary bg-primary text-white'
                           : 'border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= BASE_URL ?>/admin/archived-records<?= arQueryString(['page' => $page + 1]) ?>"
                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                        Next
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </section>

</div>


<!-- ═══════════════════════════════════════════════════════════════════════════
     CREATE MODAL
     ═══════════════════════════════════════════════════════════════════════════ -->
<div id="createModal"
     class="fixed inset-0 z-[100] hidden items-center justify-center"
     role="dialog" aria-modal="true" aria-labelledby="createModalTitle">
    <div id="createModalBackdrop"
         class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm opacity-0 transition-opacity duration-200"></div>
    <div id="createModalPanel"
         class="relative z-10 mx-4 w-full max-w-4xl max-h-[92vh] overflow-y-auto rounded-2xl bg-white shadow-2xl border
                border-gray-100 transform scale-95 opacity-0 transition-all duration-200">

        <form method="POST" action="<?= BASE_URL ?>/admin/archived-records/store" enctype="multipart/form-data">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5">
                <div>
                    <h3 id="createModalTitle" class="text-lg font-semibold text-gray-900">New Archived Record</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Fill in the details below to create a new legislative record</p>
                </div>
                <button type="button" onclick="closeCreateModal()"
                        class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Body -->
            <div class="space-y-5 px-6 py-5">

                <!-- Row 1: Approved No + Date -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="create_approved_no" class="block text-xs font-semibold text-gray-700 mb-1.5">
                            Approved Number <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="create_approved_no" name="approved_no"
                               placeholder="e.g. Resolution No. 2024-001"
                               class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm text-gray-800
                                      placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                               required>
                    </div>
                    <div>
                        <label for="create_date_approved" class="block text-xs font-semibold text-gray-700 mb-1.5">
                            Date Approved <span class="text-red-500">*</span>
                        </label>
                        <input type="date" id="create_date_approved" name="date_approved"
                               class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm text-gray-800
                                      focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                               required>
                    </div>
                </div>

                <!-- Subject Matter -->
                <div>
                    <label for="create_subject_matter" class="block text-xs font-semibold text-gray-700 mb-1.5">
                        Subject Matter <span class="text-red-500">*</span>
                    </label>
                    <textarea id="create_subject_matter" name="subject_matter" rows="3"
                              placeholder="Official subject of this record…"
                              class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm text-gray-800
                                     placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 resize-y"
                              required></textarea>
                </div>

                <!-- Document Type -->
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">
                        Document Type <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <select name="document_type_id" id="create_doc_type_id"
                                    onchange="handleDocTypeChange('create')"
                                    class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm text-gray-800
                                           focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                                <option value="">— Select existing type —</option>
                                <?php foreach ($documentTypes as $dt): ?>
                                    <option value="<?= (int) $dt['id'] ?>"><?= htmlspecialchars($dt['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="mt-1 text-xs text-gray-400">Select from list</p>
                        </div>
                        <div>
                            <input type="text" name="custom_document_type_name" id="create_custom_type"
                                   placeholder="Or enter custom type name"
                                   onchange="handleCustomTypeChange('create')"
                                   class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm text-gray-800
                                          placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                            <p class="mt-1 text-xs text-gray-400">Or type custom name</p>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label for="create_description" class="block text-xs font-semibold text-gray-700 mb-1.5">Description</label>
                    <textarea id="create_description" name="description" rows="2"
                              placeholder="Optional additional description…"
                              class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm text-gray-800
                                     placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 resize-y"></textarea>
                </div>

                <!-- Tags -->
                <div>
                    <label for="create_tags" class="block text-xs font-semibold text-gray-700 mb-1.5">Tags</label>
                    <input type="text" id="create_tags" name="tags"
                           placeholder="budget, health, 2024 (comma-separated)"
                           class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm text-gray-800
                                  placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </div>

                <!-- Authors and co-authors -->
                <section class="rounded-xl border border-gray-200 p-4 sm:p-5">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900">Authors / Sponsors</h4>
                            <p class="mt-1 text-xs text-gray-500">Choose a master-list person or enter a custom name. A person cannot fill both roles.</p>
                        </div>
                        <button type="button" onclick="addArchiveEntry('createAuthorsList', 'authorEntryTemplate')"
                                class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-primary hover:bg-blue-50">
                            + Add person
                        </button>
                    </div>
                    <div id="createAuthorsList" class="space-y-3">
                        <?php foreach ([['AUTHOR', 'Author / Sponsor'], ['CO_AUTHOR', 'Co-author / Co-sponsor']] as [$roleValue, $roleLabel]): ?>
                        <div class="person-entry grid grid-cols-1 gap-3 rounded-lg bg-gray-50 p-3 md:grid-cols-[minmax(150px,0.8fr)_minmax(200px,1.2fr)_minmax(180px,1fr)_auto] md:items-center">
                            <select name="author_role[]" aria-label="Person role" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
                                <option value="AUTHOR" <?= $roleValue === 'AUTHOR' ? 'selected' : '' ?>>Author / Sponsor</option>
                                <option value="CO_AUTHOR" <?= $roleValue === 'CO_AUTHOR' ? 'selected' : '' ?>>Co-author / Co-sponsor</option>
                            </select>
                            <select name="author_sp_member_id[]" aria-label="Select existing person" class="entry-master rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
                                <option value="">Select existing person…</option>
                                <?php foreach ($spMembers as $member): ?>
                                    <option value="<?= (int) $member['sp_member_id'] ?>"><?= htmlspecialchars($member['full_name']) ?><?= !empty($member['position']) ? ' — ' . htmlspecialchars($member['position']) : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="author_custom_name[]" aria-label="Custom person name" maxlength="200" placeholder="Or enter custom name"
                                   class="entry-custom rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
                            <button type="button" onclick="removeArchiveEntry(this)" aria-label="Remove person" class="rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50">Remove</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- Committees -->
                <section class="rounded-xl border border-gray-200 p-4 sm:p-5">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900">Committees</h4>
                            <p class="mt-1 text-xs text-gray-500">Assign existing committees or enter a custom committee name.</p>
                        </div>
                        <button type="button" onclick="addArchiveEntry('createCommitteesList', 'committeeEntryTemplate')"
                                class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-primary hover:bg-blue-50">
                            + Add committee
                        </button>
                    </div>
                    <div id="createCommitteesList" class="space-y-3">
                        <div class="committee-entry grid grid-cols-1 gap-3 rounded-lg bg-gray-50 p-3 sm:grid-cols-[1fr_1fr_auto] sm:items-center">
                            <select name="committee_id[]" aria-label="Select existing committee" class="entry-master rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
                                <option value="">Select existing committee…</option>
                                <?php foreach ($committees as $committee): ?>
                                    <option value="<?= (int) $committee['id'] ?>"><?= htmlspecialchars($committee['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="committee_custom_name[]" aria-label="Custom committee name" maxlength="200" placeholder="Or enter custom committee"
                                   class="entry-custom rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
                            <button type="button" onclick="removeArchiveEntry(this)" aria-label="Remove committee" class="rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50">Remove</button>
                        </div>
                    </div>
                </section>

                <!-- Attachments -->
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Attachments</label>
                    <input type="file" name="attachments[]" multiple
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp"
                           class="w-full rounded-xl border border-dashed border-gray-300 bg-gray-50 px-3 py-2.5 text-sm
                                  text-gray-700 file:mr-4 file:rounded-lg file:border-0 file:bg-primary file:px-3
                                  file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-primary/90">
                    <p class="mt-1 text-xs text-gray-400">PDF, Word, Excel, images — max 25 MB per file, 10 files max.</p>
                </div>

            </div>

            <!-- Footer -->
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4 rounded-b-2xl">
                <button type="button" onclick="closeCreateModal()"
                        class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium
                               text-gray-700 hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2 text-sm font-semibold
                               text-white hover:bg-primary/90 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Create Record
                </button>
            </div>
        </form>
    </div>
</div>

<template id="authorEntryTemplate">
    <div class="person-entry grid grid-cols-1 gap-3 rounded-lg bg-gray-50 p-3 md:grid-cols-[minmax(150px,0.8fr)_minmax(200px,1.2fr)_minmax(180px,1fr)_auto] md:items-center">
        <select name="author_role[]" aria-label="Person role" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
            <option value="AUTHOR">Author / Sponsor</option><option value="CO_AUTHOR">Co-author / Co-sponsor</option>
        </select>
        <select name="author_sp_member_id[]" aria-label="Select existing person" class="entry-master rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
            <option value="">Select existing person…</option>
            <?php foreach ($spMembers as $member): ?><option value="<?= (int) $member['sp_member_id'] ?>"><?= htmlspecialchars($member['full_name']) ?><?= !empty($member['position']) ? ' — ' . htmlspecialchars($member['position']) : '' ?></option><?php endforeach; ?>
        </select>
        <input type="text" name="author_custom_name[]" aria-label="Custom person name" maxlength="200" placeholder="Or enter custom name" class="entry-custom rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
        <button type="button" onclick="removeArchiveEntry(this)" aria-label="Remove person" class="rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50">Remove</button>
    </div>
</template>
<template id="committeeEntryTemplate">
    <div class="committee-entry grid grid-cols-1 gap-3 rounded-lg bg-gray-50 p-3 sm:grid-cols-[1fr_1fr_auto] sm:items-center">
        <select name="committee_id[]" aria-label="Select existing committee" class="entry-master rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
            <option value="">Select existing committee…</option>
            <?php foreach ($committees as $committee): ?><option value="<?= (int) $committee['id'] ?>"><?= htmlspecialchars($committee['name']) ?></option><?php endforeach; ?>
        </select>
        <input type="text" name="committee_custom_name[]" aria-label="Custom committee name" maxlength="200" placeholder="Or enter custom committee" class="entry-custom rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
        <button type="button" onclick="removeArchiveEntry(this)" aria-label="Remove committee" class="rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50">Remove</button>
    </div>
</template>


<!-- ═══════════════════════════════════════════════════════════════════════════
     DELETE CONFIRM MODAL
     ═══════════════════════════════════════════════════════════════════════════ -->
<div id="deleteModal"
     class="fixed inset-0 z-[100] hidden items-center justify-center"
     role="dialog" aria-modal="true">
    <div id="deleteModalBackdrop"
         class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm opacity-0 transition-opacity duration-200"></div>
    <div id="deleteModalPanel"
         class="relative z-10 mx-4 w-full max-w-sm rounded-2xl bg-white shadow-2xl border border-gray-100
                transform scale-95 opacity-0 transition-all duration-200">
        <form method="POST" action="<?= BASE_URL ?>/admin/archived-records/destroy">
            <input type="hidden" name="id" id="deleteRecordId">
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-red-100">
                        <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Delete Record</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            Are you sure you want to delete
                            <strong id="deleteRecordName" class="text-gray-900"></strong>?
                            This action cannot be undone.
                        </p>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4 rounded-b-2xl">
                <button type="button" onclick="closeDeleteModal()"
                        class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium
                               text-gray-700 hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit"
                        class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 transition">
                    Delete
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ── Generic modal open/close ─────────────────────────────────────────────────
function openModal(id) {
    const modal = document.getElementById(id);
    const backdrop = document.getElementById(id + 'Backdrop');
    const panel = document.getElementById(id + 'Panel');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    requestAnimationFrame(() => {
        backdrop.classList.remove('opacity-0');
        panel.classList.remove('scale-95', 'opacity-0');
        panel.classList.add('scale-100', 'opacity-100');
    });
    document.addEventListener('keydown', function esc(e) {
        if (e.key === 'Escape') { closeModal(id); document.removeEventListener('keydown', esc); }
    });
}

function closeModal(id) {
    const modal = document.getElementById(id);
    const backdrop = document.getElementById(id + 'Backdrop');
    const panel = document.getElementById(id + 'Panel');
    backdrop.classList.add('opacity-0');
    panel.classList.add('scale-95', 'opacity-0');
    panel.classList.remove('scale-100', 'opacity-100');
    setTimeout(() => { modal.classList.add('hidden'); modal.classList.remove('flex'); }, 200);
}

function openCreateModal()  { openModal('createModal'); }
function closeCreateModal() { closeModal('createModal'); }

function openDeleteModal(id, name) {
    document.getElementById('deleteRecordId').value = id;
    document.getElementById('deleteRecordName').textContent = name;
    openModal('deleteModal');
}
function closeDeleteModal() { closeModal('deleteModal'); }

function addArchiveEntry(containerId, templateId) {
    const fragment = document.getElementById(templateId).content.cloneNode(true);
    document.getElementById(containerId).appendChild(fragment);
}

function removeArchiveEntry(button) {
    const entries = button.closest('.person-entry, .committee-entry');
    const list = entries.parentElement;
    if (list.children.length > 1) entries.remove();
    else {
        entries.querySelectorAll('select, input').forEach((field) => {
            field.value = field.tagName === 'SELECT' && field.name === 'author_role[]' ? 'AUTHOR' : '';
            field.disabled = false;
        });
    }
}

document.addEventListener('change', function(event) {
    const entry = event.target.closest('.person-entry, .committee-entry');
    if (!entry) return;
    const master = entry.querySelector('.entry-master');
    const custom = entry.querySelector('.entry-custom');
    if (event.target === master && master.value) custom.value = '';
    if (event.target === custom && custom.value.trim()) master.value = '';
});

// Close modal when clicking backdrop
['createModal', 'deleteModal'].forEach(function(id) {
    const modal = document.getElementById(id);
    if (modal) {
        document.getElementById(id + 'Backdrop').addEventListener('click', function() {
            closeModal(id);
        });
    }
});

// ── Mutually exclusive doc type selectors ────────────────────────────────────
function handleDocTypeChange(prefix) {
    const select = document.getElementById(prefix + '_doc_type_id');
    const custom = document.getElementById(prefix + '_custom_type');
    if (select && custom && select.value !== '') {
        custom.value = '';
        custom.disabled = true;
    } else if (custom) {
        custom.disabled = false;
    }
}
function handleCustomTypeChange(prefix) {
    const select = document.getElementById(prefix + '_doc_type_id');
    const custom = document.getElementById(prefix + '_custom_type');
    if (custom && custom.value.trim() !== '') {
        if (select) { select.value = ''; select.disabled = true; }
    } else if (select) {
        select.disabled = false;
    }
}
</script>


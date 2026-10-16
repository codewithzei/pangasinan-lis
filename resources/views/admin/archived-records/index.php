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
 *   $dateFrom       string
 *   $dateTo         string
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
$dateFrom      = $dateFrom      ?? '';
$dateTo        = $dateTo        ?? '';
$documentTypes = $documentTypes ?? [];
$spMembers     = $spMembers     ?? [];
$committees    = $committees    ?? [];

$hasFilter = ($search !== '' || $filterType !== '' || $dateFrom !== '' || $dateTo !== '');

// Build query-string helper for pagination links
function arQueryString(array $extra = []): string {
    global $search, $filterType, $dateFrom, $dateTo;
    $q = [];
    if ($search !== '')      $q['search'] = $search;
    if ($filterType !== '')  $q['type']   = $filterType;
    if ($dateFrom !== '')    $q['date_from'] = $dateFrom;
    if ($dateTo !== '')      $q['date_to']   = $dateTo;
    $q = array_merge($q, $extra);
    return $q ? ('?' . http_build_query($q)) : '';
}

// JSON encode lists for JS
$spMembersJson   = json_encode(array_values($spMembers),   JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$committeesJson  = json_encode(array_values($committees),  JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$docTypesJson    = json_encode(array_values($documentTypes), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
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
                    <button type="button" onclick="AR.openCreateModal()"
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

    <!-- ── Stats cards ─────────────────────────────────────────────────────── -->
    <section>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
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
        </div>
    </section>

    <!-- ── Table section ───────────────────────────────────────────────────── -->
    <section class="rounded-2xl border border-gray-200 bg-white">

        <!-- Table title, result count, and filters -->
        <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="shrink-0">
                <h2 class="text-base font-semibold text-gray-900">Records List</h2>
                <p class="mt-0.5 text-sm text-gray-500">
                    <span class="font-semibold text-gray-800"><?= number_format($total) ?></span>
                    record<?= $total !== 1 ? 's' : '' ?> found<?= $hasFilter ? ' (filtered)' : '' ?>
                </p>
            </div>

            <form method="GET" action="<?= BASE_URL ?>/admin/archived-records"
                  class="flex min-w-0 flex-1 flex-wrap items-center justify-end gap-3">
                <div class="min-w-[240px] flex-1 lg:max-w-sm">
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
                <div class="min-w-[160px]">
                    <select name="type"
                            aria-label="Document Type"
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
                <div class="flex items-center gap-2">
                    <label for="records_date_from" class="shrink-0 text-xs text-gray-500">From</label>
                    <input id="records_date_from" type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>"
                           aria-label="Approved date from"
                           class="w-37.5 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800
                                  focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </div>
                <div class="flex items-center gap-2">
                    <label for="records_date_to" class="shrink-0 text-xs text-gray-500">To</label>
                    <input id="records_date_to" type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>"
                           aria-label="Approved date to"
                           class="w-37.5 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800
                                  focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit"
                            class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium
                                   text-gray-700 hover:bg-gray-50 transition">
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
                                                <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">
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
                                             <button type="button" onclick="AR.openRecordEditModal(<?= (int) $row['id'] ?>)"
                                           class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5
                                                  text-xs font-medium text-gray-700 hover:border-indigo-300 hover:bg-indigo-50
                                                  hover:text-indigo-700 transition"
                                           title="Edit record">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            Edit
                                        </button>
                                        <button type="button"
                                                onclick="AR.openDeleteModal(<?= (int) $row['id'] ?>, '<?= addslashes(htmlspecialchars($row['approved_no'])) ?>')"
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
         class="relative z-10 mx-4 w-full max-w-4xl max-h-[92vh] overflow-y-auto rounded-2xl bg-white shadow-2xl
                border border-gray-100 transform scale-95 opacity-0 transition-all duration-200">

        <form id="createForm" method="POST" action="<?= BASE_URL ?>/admin/archived-records/store"
              enctype="multipart/form-data" novalidate>

            <!-- ── Header ───────────────────────────────────────────────────── -->
            <div class="sticky top-0 z-10 flex items-center justify-between border-b border-gray-100 bg-white px-6 py-5">
                <div>
                    <h3 id="createModalTitle" class="text-lg font-semibold text-gray-900">New Archived Record</h3>
                    <p class="mt-0.5 text-xs text-gray-500">Fill in the details to create a new legislative record</p>
                </div>
                <button type="button" onclick="AR.closeCreateModal()" aria-label="Close"
                        class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- ── Body ─────────────────────────────────────────────────────── -->
            <div class="px-6 py-6 space-y-8">

                <!-- ── Section 1: Core Details ──────────────────────────────── -->
                <fieldset>
                    <legend class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-900">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">1</span>
                        Core Details
                    </legend>
                    <div class="space-y-4">

                        <!-- Approved No + Date -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="c_approved_no" class="ar-label">
                                    Approved Number <span class="text-red-500" aria-hidden="true">*</span>
                                </label>
                                <input type="text" id="c_approved_no" name="approved_no" required
                                       placeholder="e.g. Resolution No. 2024-001"
                                       class="ar-input"
                                       aria-required="true">
                                <p class="ar-hint">Unique identifier for this record</p>
                            </div>
                            <div>
                                <label for="c_date_approved" class="ar-label">
                                    Date Approved <span class="text-red-500" aria-hidden="true">*</span>
                                </label>
                                <input type="date" id="c_date_approved" name="date_approved" required
                                       class="ar-input"
                                       aria-required="true">
                            </div>
                        </div>

                        <!-- Subject Matter -->
                        <div>
                            <label for="c_subject_matter" class="ar-label">
                                Subject Matter <span class="text-red-500" aria-hidden="true">*</span>
                            </label>
                            <textarea id="c_subject_matter" name="subject_matter" rows="3" required
                                      placeholder="Official subject of this legislative record…"
                                      class="ar-input resize-y"
                                      aria-required="true"></textarea>
                        </div>

                        <!-- Description -->
                        <div>
                            <label for="c_description" class="ar-label">
                                Description <span class="text-xs font-normal text-gray-400">(optional)</span>
                            </label>
                            <textarea id="c_description" name="description" rows="2"
                                      placeholder="Additional context or summary…"
                                      class="ar-input resize-y"></textarea>
                        </div>

                    </div>
                </fieldset>

                <hr class="border-gray-100">

                <!-- ── Section 2: Classification ────────────────────────────── -->
                <fieldset>
                    <legend class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-900">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">2</span>
                        Classification
                    </legend>
                    <div class="space-y-5">

                        <!-- Document Type -->
                        <div>
                            <label class="ar-label">
                                Document Type <span class="text-red-500" aria-hidden="true">*</span>
                            </label>
                            <p class="mb-3 text-xs text-gray-500">Choose a listed type or enter a custom type if it is not listed.</p>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div class="ar-exclusive-wrap" data-exclusive-group="c_doctype">
                                    <label class="mb-1 block text-xs text-gray-500">From list</label>
                                    <div class="relative">
                                        <select name="document_type_id" id="c_doc_type_id"
                                                class="ar-input ar-exclusive" data-group="c_doctype" data-type="select">
                                            <option value="">Select a document type…</option>
                                            <?php foreach ($documentTypes as $dt): ?>
                                                <option value="<?= (int) $dt['id'] ?>"><?= htmlspecialchars($dt['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="ar-exclusive-wrap" data-exclusive-group="c_doctype">
                                    <label class="mb-1 block text-xs text-gray-500">Custom type name</label>
                                    <div class="relative">
                                        <input type="text" name="custom_document_type_name" id="c_custom_type"
                                               placeholder="Enter a document type not listed"
                                               maxlength="200"
                                               class="ar-input ar-exclusive pr-10" data-group="c_doctype" data-type="text">
                                        <button type="button" class="ar-exclusive-clear hidden" data-clear-target="text" aria-label="Clear custom document type" title="Clear custom type">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                    <p class="ar-hint">Use when the type is not in the list above</p>
                                </div>
                            </div>
                            <p id="c_doctype_error" class="ar-field-error hidden" role="alert"></p>
                        </div>

                        <!-- Tags -->
                        <div>
                            <label for="c_tags_input" class="ar-label">
                                Tags <span class="text-xs font-normal text-gray-400">(optional)</span>
                            </label>
                            <div class="ar-tag-field" id="c_tags_field">
                                <div id="c_tags_chips" class="flex flex-wrap gap-1.5 empty:hidden mb-1.5"></div>
                                <input type="text" id="c_tags_input"
                                       placeholder="Type a tag and press Enter or comma…"
                                       class="min-w-[180px] flex-1 bg-transparent text-sm text-gray-800 outline-none placeholder-gray-400 py-0.5"
                                       autocomplete="off">
                            </div>
                            <input type="hidden" name="tags" id="c_tags_hidden">
                            <p class="ar-hint">Press <kbd class="rounded border border-gray-200 px-1 py-0.5 text-xs">Enter</kbd> or <kbd class="rounded border border-gray-200 px-1 py-0.5 text-xs">,</kbd> to add each tag</p>
                        </div>

                    </div>
                </fieldset>

                <hr class="border-gray-100">

                <!-- ── Section 3: People ─────────────────────────────────────── -->
                <fieldset>
                    <legend class="mb-1 flex items-center gap-2 text-sm font-semibold text-gray-900">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">3</span>
                        Authors / Sponsors &amp; Co-authors / Co-sponsors
                    </legend>
                    <p class="mb-4 text-xs text-gray-500">
                        Add authors/sponsors in the first column. Co-authors/co-sponsors are optional. The same person cannot be assigned to both columns.
                    </p>

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <section class="rounded-xl border border-gray-200 bg-gray-50/60 p-4">
                            <h4 class="mb-1 text-sm font-semibold text-gray-900">Authors / Sponsors</h4>
                            <p class="mb-3 text-xs text-gray-500">Search and select one or more SP members, or enter custom names below.</p>
                            <div id="c_authors_list" class="ar-multi-group">
                                <div class="ar-multi-select" data-list-key="spMembers" data-role="AUTHOR" data-selected="[]">
                                    <div class="ar-multi-control">
                                        <div class="ar-multi-chips"></div>
                                        <input type="text" class="ar-multi-filter" placeholder="Search and select authors / sponsors…" aria-label="Search authors and sponsors" role="combobox" aria-expanded="false" aria-autocomplete="list" autocomplete="off">
                                        <button type="button" class="ar-multi-toggle" aria-label="Show author options">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                    </div>
                                    <div class="ar-multi-dropdown hidden" role="listbox" aria-multiselectable="true"><div class="ar-multi-options"></div></div>
                                    <div class="ar-multi-post-fields hidden"></div>
                                </div>
                                <input type="text" class="ar-input ar-multi-custom mt-3" placeholder="Or enter custom author / sponsor names, separated by commas" aria-label="Custom author or sponsor names">
                            </div>
                        </section>

                        <section class="rounded-xl border border-indigo-100 bg-indigo-50/30 p-4">
                            <h4 class="mb-1 text-sm font-semibold text-gray-900">Co-authors / Co-sponsors <span class="font-normal text-gray-400">(optional)</span></h4>
                            <p class="mb-3 text-xs text-gray-500">Optional. Search and select multiple people, or enter custom names below.</p>
                            <div id="c_coauthors_list" class="ar-multi-group">
                                <div class="ar-multi-select" data-list-key="spMembers" data-role="CO_AUTHOR" data-selected="[]">
                                    <div class="ar-multi-control">
                                        <div class="ar-multi-chips"></div>
                                        <input type="text" class="ar-multi-filter" placeholder="Search and select co-authors / co-sponsors…" aria-label="Search co-authors and co-sponsors" role="combobox" aria-expanded="false" aria-autocomplete="list" autocomplete="off">
                                        <button type="button" class="ar-multi-toggle" aria-label="Show co-author options">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                    </div>
                                    <div class="ar-multi-dropdown hidden" role="listbox" aria-multiselectable="true"><div class="ar-multi-options"></div></div>
                                    <div class="ar-multi-post-fields hidden"></div>
                                </div>
                                <input type="text" class="ar-input ar-multi-custom mt-3" placeholder="Custom co-author / co-sponsor names, separated by commas" aria-label="Custom co-author or co-sponsor names">
                            </div>
                        </section>
                    </div>

                    <p id="c_authors_global_error" class="ar-field-error hidden mt-2" role="alert"></p>
                </fieldset>

                <hr class="border-gray-100">

                <!-- ── Section 4: Committees ─────────────────────────────────── -->
                <fieldset>
                    <legend class="mb-1 flex items-center gap-2 text-sm font-semibold text-gray-900">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">4</span>
                        Committees
                    </legend>
                    <p class="mb-4 text-xs text-gray-500">Search and select multiple committees, or enter custom committee names separated by commas.</p>

                    <div id="c_committees_list" class="ar-multi-group">
                        <div class="ar-multi-select" data-list-key="committees" data-selected="[]">
                            <div class="ar-multi-control">
                                <div class="ar-multi-chips"></div>
                                <input type="text" class="ar-multi-filter" placeholder="Search and select committees…" aria-label="Search committees" role="combobox" aria-expanded="false" aria-autocomplete="list" autocomplete="off">
                                <button type="button" class="ar-multi-toggle" aria-label="Show committee options">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                            </div>
                            <div class="ar-multi-dropdown hidden" role="listbox" aria-multiselectable="true"><div class="ar-multi-options"></div></div>
                            <div class="ar-multi-post-fields hidden"></div>
                        </div>
                        <input type="text" class="ar-input ar-multi-custom mt-3" placeholder="Custom committee names, separated by commas" aria-label="Custom committee names">
                    </div>
                </fieldset>

                <hr class="border-gray-100">

                <!-- ── Section 5: Attachments ──────────────────────────────── -->
                <fieldset>
                    <legend class="mb-1 flex items-center gap-2 text-sm font-semibold text-gray-900">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">5</span>
                        Attachments
                    </legend>
                    <p class="mb-4 text-xs text-gray-500">
                        Accepted: PDF, Word, Excel, JPEG, PNG, GIF, WebP &mdash; max <strong>25 MB</strong> per file, up to <strong>10 files</strong>.
                    </p>

                    <!-- Drop zone -->
                    <div id="c_drop_zone"
                         class="ar-drop-zone"
                         role="button" tabindex="0" aria-label="Upload files by dropping here or clicking to browse">
                        <svg class="h-10 w-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.4"
                                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        <p class="mt-2 text-sm font-medium text-gray-600">Drop files here or <span class="text-primary underline cursor-pointer">browse</span></p>
                        <p class="mt-1 text-xs text-gray-400">PDF, Word, Excel, images — max 25 MB each, 10 files total</p>
                        <input type="file" name="attachments[]" id="c_file_input" multiple
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp"
                               class="hidden" tabindex="-1" aria-hidden="true">
                    </div>

                    <!-- File previews -->
                    <div id="c_file_list" class="mt-3 space-y-2 empty:hidden"></div>
                    <p id="c_file_error" class="ar-field-error hidden mt-2" role="alert"></p>
                </fieldset>

            </div>

            <!-- ── Footer ───────────────────────────────────────────────────── -->
            <div class="sticky bottom-0 flex items-center justify-end gap-3 border-t border-gray-100 bg-white/95 backdrop-blur-sm px-6 py-4 rounded-b-2xl">
                <button type="button" onclick="AR.closeCreateModal()"
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


<!-- ═══════════════════════════════════════════════════════════════════════════
     DELETE CONFIRM MODAL
     ═══════════════════════════════════════════════════════════════════════════ -->
<div id="deleteModal"
     class="fixed inset-0 z-[100] hidden items-center justify-center"
     role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle">
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
                        <h3 id="deleteModalTitle" class="text-base font-semibold text-gray-900">Delete Record</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            Are you sure you want to delete
                            <strong id="deleteRecordName" class="text-gray-900"></strong>?
                            This action cannot be undone.
                        </p>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4 rounded-b-2xl">
                <button type="button" onclick="AR.closeDeleteModal()"
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


<!-- ═══════════════════════════════════════════════════════════════════════════
     SHARED STYLES
     ═══════════════════════════════════════════════════════════════════════════ -->
<style>
/* ── Base field styles ───────────────────────────────────────────────────── */
.ar-label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.375rem;
}
.ar-hint {
    margin-top: 0.25rem;
    font-size: 0.75rem;
    color: #9ca3af;
}
.ar-field-error {
    font-size: 0.75rem;
    color: #dc2626;
}
.ar-input {
    display: block;
    width: 100%;
    border-radius: 0.75rem;
    border: 1px solid #e5e7eb;
    padding: 0.625rem 0.75rem;
    font-size: 0.875rem;
    color: #1f2937;
    background: #fff;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.ar-input:focus {
    outline: none;
    border-color: var(--color-primary, #2563EB);
    box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
}
.ar-input.ar-exclusive-disabled {
    background: #f3f4f6;
    color: #9ca3af;
    cursor: not-allowed;
    pointer-events: none;
}
.ar-input.ar-exclusive-active {
    border-color: #2563EB;
    background: #eff6ff;
}
.ar-exclusive-clear {
    position: absolute;
    top: 50%;
    right: 0.5rem;
    display: flex;
    height: 1.75rem;
    width: 1.75rem;
    transform: translateY(-50%);
    align-items: center;
    justify-content: center;
    border: 0;
    border-radius: 0.5rem;
    background: transparent;
    color: #9ca3af;
    cursor: pointer;
}
.ar-exclusive-clear:hover { background:#f3f4f6; color:#4b5563; }
.ar-exclusive-clear.hidden { display:none; }

/* ── Tag field ───────────────────────────────────────────────────────────── */
.ar-tag-field {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.375rem;
    border-radius: 0.75rem;
    border: 1px solid #e5e7eb;
    padding: 0.5rem 0.75rem;
    cursor: text;
    min-height: 2.75rem;
    background: #fff;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.ar-tag-field:focus-within {
    border-color: var(--color-primary, #2563EB);
    box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
}
.ar-tag-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.125rem 0.5rem 0.125rem 0.625rem;
    border-radius: 9999px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 0.75rem;
    font-weight: 500;
}
.ar-tag-chip button {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1rem;
    height: 1rem;
    border-radius: 50%;
    background: transparent;
    border: none;
    color: #93c5fd;
    cursor: pointer;
    padding: 0;
    transition: background 0.1s, color 0.1s;
}
.ar-tag-chip button:hover {
    background: #bfdbfe;
    color: #1d4ed8;
}

/* ── Searchable select ───────────────────────────────────────────────────── */
.ar-search-select {
    position: relative;
}
.ar-search-display {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    border-radius: 0.75rem;
    border: 1px solid #e5e7eb;
    padding: 0.625rem 0.75rem;
    font-size: 0.875rem;
    background: #fff;
    cursor: pointer;
    transition: border-color 0.15s, box-shadow 0.15s;
    min-height: 2.625rem;
}
.ar-search-display:focus {
    outline: none;
    border-color: var(--color-primary, #2563EB);
    box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
}
.ar-search-display.ar-active {
    border-color: #2563EB;
    background: #eff6ff;
}
.ar-search-display.ar-exclusive-disabled {
    background: #f3f4f6;
    color: #9ca3af;
    cursor: not-allowed;
    pointer-events: none;
}
.ar-search-display-text {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex: 1;
    font-size: 0.875rem;
}
.ar-search-chevron {
    transition: transform 0.2s;
    flex-shrink: 0;
}
.ar-search-display[aria-expanded="true"] .ar-search-chevron {
    transform: rotate(180deg);
}
.ar-search-dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    z-index: 200;
    border-radius: 0.75rem;
    border: 1px solid #e5e7eb;
    background: #fff;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.12), 0 4px 10px -3px rgba(0,0,0,0.07);
    overflow: hidden;
}
.ar-search-filter-wrap {
    padding: 0.5rem;
    border-bottom: 1px solid #f3f4f6;
}
.ar-search-filter-input {
    width: 100%;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
    padding: 0.375rem 0.625rem;
    font-size: 0.8125rem;
    outline: none;
    transition: border-color 0.15s;
}
.ar-search-filter-input:focus {
    border-color: #2563EB;
}
.ar-search-options {
    max-height: 12rem;
    overflow-y: auto;
}
.ar-search-option {
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    cursor: pointer;
    transition: background 0.1s;
}
.ar-search-option:hover,
.ar-search-option.ar-option-focused {
    background: #eff6ff;
    color: #1d4ed8;
}
.ar-search-option.ar-option-selected {
    background: #dbeafe;
    color: #1d4ed8;
    font-weight: 500;
}
.ar-search-option.ar-option-disabled,
.ar-search-option.ar-option-disabled:hover {
    background: #f9fafb;
    color: #9ca3af;
    cursor: not-allowed;
}
.ar-search-option.ar-option-clear {
    color: #6b7280;
    font-style: italic;
    border-bottom: 1px solid #f3f4f6;
}
.ar-search-no-results {
    padding: 0.625rem 0.75rem;
    font-size: 0.8125rem;
    color: #9ca3af;
    text-align: center;
}
.ar-multi-select { position:relative; }
.ar-multi-control { display:flex; min-height:2.75rem; flex-wrap:wrap; align-items:center; gap:0.375rem; border:1px solid #e5e7eb; border-radius:0.75rem; background:#fff; padding:0.375rem 0.5rem 0.375rem 0.75rem; }
.ar-multi-control:focus-within { border-color:#2563EB; box-shadow:0 0 0 3px rgba(37,99,235,.12); }
.ar-multi-chips { display:contents; }
.ar-multi-filter { min-width:10rem; flex:1; border:0; background:transparent; padding:0.25rem 0; color:#1f2937; font-size:0.875rem; outline:none; }
.ar-multi-filter::placeholder { color:#9ca3af; }
.ar-multi-toggle { display:flex; height:1.75rem; width:1.75rem; flex-shrink:0; align-items:center; justify-content:center; border:0; border-radius:0.5rem; background:transparent; color:#9ca3af; cursor:pointer; }
.ar-multi-toggle:hover { background:#f3f4f6; color:#4b5563; }
.ar-multi-dropdown { position:absolute; top:calc(100% + 4px); left:0; right:0; z-index:210; max-height:15rem; overflow-y:auto; border:1px solid #e5e7eb; border-radius:0.75rem; background:#fff; box-shadow:0 10px 25px -5px rgba(0,0,0,.12),0 4px 10px -3px rgba(0,0,0,.07); }
.ar-multi-dropdown.hidden { display:none; }
.ar-multi-option { display:flex; width:100%; align-items:center; gap:0.625rem; border:0; background:#fff; padding:0.625rem 0.75rem; text-align:left; color:#374151; font-size:0.875rem; cursor:pointer; }
.ar-multi-option:hover { background:#eff6ff; color:#1d4ed8; }
.ar-multi-option[aria-selected="true"] { background:#eff6ff; color:#1d4ed8; font-weight:600; }
.ar-multi-option.ar-option-disabled { background:#f9fafb; color:#9ca3af; cursor:not-allowed; }
.ar-multi-option-check { display:flex; height:1rem; width:1rem; flex-shrink:0; align-items:center; justify-content:center; border:1px solid #d1d5db; border-radius:0.25rem; }
.ar-multi-option[aria-selected="true"] .ar-multi-option-check { border-color:#2563eb; background:#2563eb; color:#fff; }
.ar-multi-chip { display:inline-flex; max-width:100%; align-items:center; gap:0.25rem; border:1px solid #bfdbfe; border-radius:9999px; background:#eff6ff; padding:0.2rem 0.35rem 0.2rem 0.625rem; color:#1d4ed8; font-size:0.75rem; font-weight:500; }
.ar-multi-chip-label { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.ar-multi-chip-remove { display:flex; height:1rem; width:1rem; flex-shrink:0; align-items:center; justify-content:center; border:0; border-radius:50%; background:transparent; color:#60a5fa; cursor:pointer; }
.ar-multi-chip-remove:hover { background:#bfdbfe; color:#1d4ed8; }
.ar-multi-empty { padding:0.75rem; color:#9ca3af; text-align:center; font-size:0.8125rem; }

/* ── Person / committee rows ─────────────────────────────────────────────── */
.ar-person-row,
.ar-committee-row {
    border-radius: 0.75rem;
    border: 1px solid #e5e7eb;
    background: #f9fafb;
    padding: 0.75rem;
}
.ar-person-inner {
    display: grid;
    gap: 0.625rem;
    align-items: start;
    grid-template-columns: minmax(0, 1fr) auto;
}
.ar-person-member-col { grid-column: 1 / -1; }
.ar-person-custom-col { grid-column: 1; }
.ar-committee-inner {
    display: grid;
    gap: 0.625rem;
    align-items: start;
    grid-template-columns: 1fr;
}
@media (min-width: 640px) {
    .ar-committee-inner {
        grid-template-columns: 1fr 1fr auto;
        align-items: center;
    }
}
.ar-remove-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
    background: #fff;
    color: #9ca3af;
    cursor: pointer;
    transition: background 0.1s, color 0.1s, border-color 0.1s;
    flex-shrink: 0;
}
.ar-remove-btn:hover {
    background: #fef2f2;
    color: #dc2626;
    border-color: #fca5a5;
}

/* ── Drop zone ───────────────────────────────────────────────────────────── */
.ar-drop-zone {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-radius: 1rem;
    border: 2px dashed #d1d5db;
    background: #f9fafb;
    padding: 2rem 1.5rem;
    text-align: center;
    cursor: pointer;
    transition: border-color 0.2s, background 0.2s;
    outline: none;
}
.ar-drop-zone:hover,
.ar-drop-zone.ar-drag-over,
.ar-drop-zone:focus-visible {
    border-color: #2563EB;
    background: #eff6ff;
}
.ar-drop-zone.ar-drag-over {
    box-shadow: inset 0 0 0 3px rgba(37,99,235,0.12);
}

/* ── File preview item ───────────────────────────────────────────────────── */
.ar-file-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border-radius: 0.75rem;
    border: 1px solid #e5e7eb;
    background: #fff;
    padding: 0.625rem 0.75rem;
}
.ar-file-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
    flex-shrink: 0;
}
.ar-file-meta {
    flex: 1;
    min-width: 0;
}
.ar-file-name {
    font-size: 0.875rem;
    font-weight: 500;
    color: #111827;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ar-file-size {
    font-size: 0.75rem;
    color: #9ca3af;
    margin-top: 0.125rem;
}
.ar-file-remove {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
    background: #fff;
    color: #9ca3af;
    cursor: pointer;
    transition: background 0.1s, color 0.1s, border-color 0.1s;
    flex-shrink: 0;
}
.ar-file-remove:hover {
    background: #fef2f2;
    color: #dc2626;
    border-color: #fca5a5;
}
.ar-file-existing {
    background: #f0fdf4;
    border-color: #bbf7d0;
}
.ar-file-existing .ar-file-name {
    color: #166534;
}
</style>


<!-- ═══════════════════════════════════════════════════════════════════════════
     SHARED JAVASCRIPT
     ═══════════════════════════════════════════════════════════════════════════ -->
<script>
(function () {
'use strict';

// ── Data injected from PHP ────────────────────────────────────────────────────
const DATA = {
    spMembers:  <?= $spMembersJson ?>,
    committees: <?= $committeesJson ?>,
};
const BASE_LOOKUPS = {
    spMembers: DATA.spMembers.slice(),
    committees: DATA.committees.slice(),
};

const MAX_FILES      = 10;
const MAX_FILE_BYTES = 26214400; // 25 MB
const ALLOWED_EXTS   = ['.pdf','.doc','.docx','.xls','.xlsx','.jpg','.jpeg','.png','.gif','.webp'];

// ── Modal helper ──────────────────────────────────────────────────────────────
function openModal(id) {
    const modal    = document.getElementById(id);
    const backdrop = document.getElementById(id + 'Backdrop');
    const panel    = document.getElementById(id + 'Panel');
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    requestAnimationFrame(() => {
        backdrop.classList.remove('opacity-0');
        panel.classList.remove('scale-95', 'opacity-0');
        panel.classList.add('scale-100', 'opacity-100');
    });
    const esc = (e) => { if (e.key === 'Escape') { closeModal(id); document.removeEventListener('keydown', esc); } };
    document.addEventListener('keydown', esc);
    // Trap back-click
    const bd = document.getElementById(id + 'Backdrop');
    if (bd) bd.onclick = () => closeModal(id);
}
function closeModal(id) {
    const modal    = document.getElementById(id);
    const backdrop = document.getElementById(id + 'Backdrop');
    const panel    = document.getElementById(id + 'Panel');
    if (!modal) return;
    backdrop.classList.add('opacity-0');
    panel.classList.add('scale-95', 'opacity-0');
    panel.classList.remove('scale-100', 'opacity-100');
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        if (id === 'editModal') {
            DATA.spMembers = BASE_LOOKUPS.spMembers.slice();
            DATA.committees = BASE_LOOKUPS.committees.slice();
        }
    }, 200);
}

async function openRecordEditModal(recordId) {
    document.getElementById('editModal')?.remove();

    try {
        const response = await fetch(<?= json_encode(BASE_URL . '/admin/archived-records/show?id=') ?> + encodeURIComponent(recordId), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error('Unable to load this archived record.');

        const responseHtml = await response.text();
        const parsedPage = new DOMParser().parseFromString(responseHtml, 'text/html');
        const modal = parsedPage.getElementById('editModal');
        if (!modal) throw new Error('The edit form could not be loaded.');

        AR.mergeLookupData({
            spMembers: JSON.parse(modal.dataset.spMembers || '[]'),
            committees: JSON.parse(modal.dataset.committees || '[]'),
        });
        document.body.appendChild(modal);

        modal.querySelectorAll('.ar-multi-select').forEach(widget => AR.initMultiSelect(widget));
        modal.querySelectorAll('.ar-search-select').forEach(widget => AR.initSearchSelect(widget));
        modal.querySelectorAll('.ar-person-row, .ar-committee-row').forEach(row => {
            const selector = row.querySelector('.ar-search-select');
            const custom = row.querySelector('.ar-person-custom, .ar-comm-custom');
            const memberId = selector?.querySelector('.ar-search-hidden-id')?.value;
            if (selector && memberId) AR.enforceExclusive(selector, 'select');
            else if (selector && custom?.value.trim()) AR.enforceExclusive(selector, 'custom');
        });
        AR.initDocTypeExclusive('e_doctype');
        AR.initTagField({ fieldId: 'e_tags_field', inputId: 'e_tags_input', chipsId: 'e_tags_chips', hiddenId: 'e_tags_hidden' });
        AR.initDropZone({ zoneId: 'e_drop_zone', inputId: 'e_file_input', listId: 'e_file_list', errorId: 'e_file_error' });

        const editForm = modal.querySelector('#editForm');
        editForm?.addEventListener('submit', event => {
            if (!AR.validateMultiAuthors(editForm)) {
                event.preventDefault();
                return;
            }
            AR.serializeMultiSelects(editForm);
        });

        AR.openModal('editModal');
    } catch (error) {
        console.error('Archived record edit modal failed to load:', error);
        alert(error.message || 'Unable to load the edit form. Please try again.');
    }
}

function initMultiSelect(widget) {
    if (widget.dataset.arInited) return;
    widget.dataset.arInited = '1';

    const listKey = widget.dataset.listKey;
    const idField = listKey === 'spMembers' ? 'sp_member_id' : 'id';
    const nameField = listKey === 'spMembers' ? 'full_name' : 'name';
    const items = DATA[listKey] || [];
    const form = widget.closest('form');
    const role = widget.dataset.role || '';
    const chips = widget.querySelector('.ar-multi-chips');
    const filter = widget.querySelector('.ar-multi-filter');
    const toggle = widget.querySelector('.ar-multi-toggle');
    const dropdown = widget.querySelector('.ar-multi-dropdown');
    const options = widget.querySelector('.ar-multi-options');
    const selected = new Set(JSON.parse(widget.dataset.selected || '[]').map(String));

    function saveSelection() {
        widget.dataset.selected = JSON.stringify(Array.from(selected));
    }

    function assignedToOtherRole(memberId) {
        if (listKey !== 'spMembers' || !role || !form) return false;
        return Array.from(form.querySelectorAll('.ar-multi-select[data-list-key="spMembers"]')).some(other => {
            if (other === widget || other.dataset.role === role) return false;
            return JSON.parse(other.dataset.selected || '[]').map(String).includes(String(memberId));
        });
    }

    function renderChips() {
        chips.replaceChildren();
        selected.forEach(id => {
            const item = items.find(option => String(option[idField]) === id);
            if (!item) return;
            const chip = document.createElement('span');
            chip.className = 'ar-multi-chip';
            const label = document.createElement('span');
            label.className = 'ar-multi-chip-label';
            label.textContent = String(item[nameField] || '');
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'ar-multi-chip-remove';
            remove.setAttribute('aria-label', 'Remove ' + String(item[nameField] || 'selection'));
            remove.innerHTML = '&times;';
            remove.addEventListener('click', event => {
                event.stopPropagation();
                selected.delete(id);
                saveSelection();
                renderChips();
                renderOptions(filter.value);
                filter.focus();
            });
            chip.append(label, remove);
            chips.appendChild(chip);
        });
    }

    function renderOptions(query = '') {
        const normalizedQuery = query.trim().toLocaleLowerCase();
        options.replaceChildren();
        const matching = items.filter(item => String(item[nameField] || '').toLocaleLowerCase().includes(normalizedQuery));
        if (!matching.length) {
            const empty = document.createElement('div');
            empty.className = 'ar-multi-empty';
            empty.textContent = normalizedQuery ? 'No matching options' : 'No options available';
            options.appendChild(empty);
            return;
        }

        matching.forEach(item => {
            const id = String(item[idField]);
            const isSelected = selected.has(id);
            const isBlocked = assignedToOtherRole(id) && !isSelected;
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'ar-multi-option' + (isBlocked ? ' ar-option-disabled' : '');
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', String(isSelected));
            option.disabled = isBlocked;

            const check = document.createElement('span');
            check.className = 'ar-multi-option-check';
            if (isSelected) check.textContent = '✓';
            const label = document.createElement('span');
            const suffix = listKey === 'spMembers' && item.position ? ' — ' + item.position : '';
            label.textContent = String(item[nameField] || '') + suffix;
            option.append(check, label);
            option.addEventListener('click', event => {
                event.stopPropagation();
                if (isBlocked || assignedToOtherRole(id)) return;
                if (selected.has(id)) selected.delete(id);
                else selected.add(id);
                saveSelection();
                renderChips();
                renderOptions(filter.value);
                filter.focus();
            });
            options.appendChild(option);
        });
    }

    function openDropdown() {
        dropdown.classList.remove('hidden');
        filter.setAttribute('aria-expanded', 'true');
        renderOptions(filter.value);
    }
    function closeDropdown() {
        dropdown.classList.add('hidden');
        filter.setAttribute('aria-expanded', 'false');
    }

    filter.addEventListener('focus', openDropdown);
    filter.addEventListener('input', () => renderOptions(filter.value));
    filter.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeDropdown();
        if (event.key === 'Enter') {
            const firstOption = options.querySelector('.ar-multi-option:not(:disabled)');
            if (firstOption) { event.preventDefault(); firstOption.click(); }
        }
        if (event.key === 'Backspace' && !filter.value && selected.size) {
            const lastSelected = Array.from(selected).pop();
            selected.delete(lastSelected);
            saveSelection();
            renderChips();
            renderOptions('');
        }
    });
    toggle.addEventListener('click', () => {
        if (dropdown.classList.contains('hidden')) { filter.focus(); openDropdown(); }
        else closeDropdown();
    });
    widget.querySelector('.ar-multi-control').addEventListener('click', event => {
        if (event.target !== toggle && !event.target.closest('.ar-multi-chip-remove')) filter.focus();
    });
    renderChips();
}

document.addEventListener('click', event => {
    document.querySelectorAll('.ar-multi-select .ar-multi-dropdown:not(.hidden)').forEach(dropdown => {
        const widget = dropdown.closest('.ar-multi-select');
        if (!widget.contains(event.target)) {
            dropdown.classList.add('hidden');
            widget.querySelector('.ar-multi-filter')?.setAttribute('aria-expanded', 'false');
        }
    });
});

function validateMultiAuthors(form) {
    const seen = new Map();
    let valid = true;
    const error = form.querySelector('[id$="authors_global_error"]');
    const register = (key, role) => {
        const previousRole = seen.get(key);
        if (previousRole && previousRole !== role) valid = false;
        else seen.set(key, role);
    };

    form.querySelectorAll('.ar-multi-select[data-list-key="spMembers"]').forEach(widget => {
        const role = widget.dataset.role;
        const selectedIds = JSON.parse(widget.dataset.selected || '[]').map(String);
        const items = DATA.spMembers || [];
        selectedIds.forEach(id => {
            register('member:' + id, role);
            const member = items.find(item => String(item.sp_member_id) === id);
            if (member?.full_name) register('name:' + member.full_name.trim().replace(/\s+/g, ' ').toLocaleLowerCase(), role);
        });
        const customInput = widget.closest('.ar-multi-group')?.querySelector('.ar-multi-custom');
        (customInput?.value || '').split(',').map(name => name.trim().replace(/\s+/g, ' ').toLocaleLowerCase()).filter(Boolean)
            .forEach(name => register('name:' + name, role));
    });

    if (error) {
        error.textContent = valid ? '' : 'The same person cannot be assigned as both an author and a co-author.';
        error.classList.toggle('hidden', valid);
    }
    return valid;
}

function serializeMultiSelects(form) {
    form.querySelectorAll('.ar-multi-select').forEach(widget => {
        const target = widget.querySelector('.ar-multi-post-fields');
        target.replaceChildren();
        const listKey = widget.dataset.listKey;
        const selectedIds = JSON.parse(widget.dataset.selected || '[]').map(String);
        const customNames = (widget.closest('.ar-multi-group')?.querySelector('.ar-multi-custom')?.value || '')
            .split(',').map(name => name.trim()).filter(Boolean);
        const entries = [
            ...selectedIds.map(id => ({ id, custom: '' })),
            ...Array.from(new Set(customNames.map(name => name.toLocaleLowerCase())))
                .map(normalized => ({ id: '', custom: customNames.find(name => name.toLocaleLowerCase() === normalized) })),
        ];
        const addHidden = (name, value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            target.appendChild(input);
        };

        entries.forEach(entry => {
            if (listKey === 'spMembers') {
                addHidden('author_role[]', widget.dataset.role || 'AUTHOR');
                addHidden('author_sp_member_id[]', entry.id);
                addHidden('author_custom_name[]', entry.custom);
            } else {
                addHidden('committee_id[]', entry.id);
                addHidden('committee_custom_name[]', entry.custom);
            }
        });
    });
}

// ── Searchable select component ───────────────────────────────────────────────
function initSearchSelect(widget) {
    if (widget.dataset.arInited) return;
    widget.dataset.arInited = '1';

    const listKey    = widget.dataset.listKey;
    const items      = DATA[listKey] || [];
    const display    = widget.querySelector('.ar-search-display');
    const displayTxt = widget.querySelector('.ar-search-display-text');
    const hiddenId   = widget.querySelector('.ar-search-hidden-id');
    const dropdown   = widget.querySelector('.ar-search-dropdown');
    const filterIn   = widget.querySelector('.ar-search-filter-input');
    const optionsEl  = widget.querySelector('.ar-search-options');

    let selectedId   = hiddenId.value || '';
    let selectedTxt  = '';

    function getIdField()   { return listKey === 'spMembers' ? 'sp_member_id' : 'id'; }
    function getNameField() { return listKey === 'spMembers' ? 'full_name' : 'name'; }

    function isAssignedToOtherRole(memberId) {
        const currentRow = widget.closest('.ar-person-row');
        if (!currentRow || listKey !== 'spMembers' || !memberId) return false;
        const currentRole = currentRow.dataset.role || currentRow.querySelector('[name="author_role[]"]')?.value || 'AUTHOR';
        const form = widget.closest('form') || document;
        return Array.from(form.querySelectorAll('.ar-person-row')).some(row => {
            const role = row.dataset.role || row.querySelector('[name="author_role[]"]')?.value || 'AUTHOR';
            const selectedId = row.querySelector('.ar-search-hidden-id')?.value || '';
            return row !== currentRow && role !== currentRole && selectedId === String(memberId);
        });
    }

    function render(query) {
        query = (query || '').toLowerCase().trim();
        const idField   = getIdField();
        const nameField = getNameField();
        let html = '<div class="ar-search-option ar-option-clear" data-val="">— Clear selection —</div>';
        let count = 0;
        for (const item of items) {
            const name = String(item[nameField] || '');
            const pos  = listKey === 'spMembers' ? (item.position ? ' — ' + item.position : '') : '';
            if (query && !name.toLowerCase().includes(query)) continue;
            const isSelected = String(item[idField]) === String(selectedId);
            const isBlocked = isAssignedToOtherRole(item[idField]) && !isSelected;
            html += `<div class="ar-search-option${isSelected ? ' ar-option-selected' : ''}${isBlocked ? ' ar-option-disabled' : ''}" data-disabled="${isBlocked ? '1' : '0'}" data-val="${item[idField]}" data-label="${escAttr(name)}"${isBlocked ? ' aria-disabled="true"' : ''}>${escHtml(name)}${escHtml(pos)}</div>`;
            count++;
        }
        if (count === 0 && query) html += '<div class="ar-search-no-results">No results for "' + escHtml(query) + '"</div>';
        optionsEl.innerHTML = html;
        // Attach click
        optionsEl.querySelectorAll('.ar-search-option').forEach(opt => {
            opt.addEventListener('mousedown', (e) => {
                e.preventDefault();
                if (opt.dataset.disabled === '1' || isAssignedToOtherRole(opt.dataset.val)) return;
                selectItem(opt.dataset.val, opt.dataset.label);
            });
        });
    }

    function selectItem(val, label) {
        if (val && isAssignedToOtherRole(val)) return;
        selectedId  = val || '';
        selectedTxt = label || '';
        hiddenId.value = selectedId;
        if (selectedId) {
            displayTxt.textContent = selectedTxt;
            displayTxt.classList.remove('text-gray-400');
            displayTxt.classList.add('text-gray-900');
            display.classList.add('ar-active');
            // Enforce exclusivity: clear the paired custom input
            enforceExclusive(widget, 'select');
        } else {
            displayTxt.textContent = listKey === 'spMembers' ? 'Select SP member…' : 'Select committee…';
            displayTxt.classList.add('text-gray-400');
            displayTxt.classList.remove('text-gray-900');
            display.classList.remove('ar-active');
            enforceExclusive(widget, 'clear-select');
        }
        closeDropdown();
    }

    function openDropdown() {
        dropdown.classList.remove('hidden');
        display.setAttribute('aria-expanded', 'true');
        filterIn.value = '';
        render('');
        filterIn.focus();
    }
    function closeDropdown() {
        dropdown.classList.add('hidden');
        display.setAttribute('aria-expanded', 'false');
    }

    display.addEventListener('click', () => {
        if (display.classList.contains('ar-exclusive-disabled')) return;
        dropdown.classList.contains('hidden') ? openDropdown() : closeDropdown();
    });
    display.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openDropdown(); }
    });
    filterIn.addEventListener('input', () => render(filterIn.value));
    filterIn.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeDropdown(); });

    // Initialise with existing value (edit prefill)
    if (selectedId) {
        const idField   = getIdField();
        const nameField = getNameField();
        const found = items.find(i => String(i[idField]) === String(selectedId));
        if (found) selectItem(String(found[idField]), String(found[nameField]));
    }
}

// Close dropdowns when clicking outside
document.addEventListener('click', (e) => {
    document.querySelectorAll('.ar-search-dropdown:not(.hidden)').forEach(dd => {
        if (!dd.closest('.ar-search-select').contains(e.target)) {
            dd.classList.add('hidden');
            dd.closest('.ar-search-select').querySelector('.ar-search-display')?.setAttribute('aria-expanded', 'false');
        }
    });
});

// ── Exclusive pairs ───────────────────────────────────────────────────────────
// Called when a select or custom text changes in a row.
function enforceExclusive(widgetOrInput, type) {
    // Find the row
    const row = widgetOrInput.closest('.ar-person-row, .ar-committee-row');
    if (!row) return;
    const selectWidget = row.querySelector('.ar-search-select');
    const customInput  = row.querySelector('.ar-person-custom, .ar-comm-custom');
    if (!selectWidget || !customInput) return;

    const hiddenId = selectWidget.querySelector('.ar-search-hidden-id');
    const display  = selectWidget.querySelector('.ar-search-display');

    if (type === 'select' && hiddenId.value) {
        // Select has a value — disable custom
        customInput.value = '';
        customInput.disabled = true;
        customInput.classList.add('ar-exclusive-disabled');
        customInput.placeholder = 'Clear selection to use custom name';
    } else if (type === 'custom' && customInput.value.trim()) {
        // Custom has a value — disable select
        hiddenId.value = '';
        display.classList.remove('ar-active');
        const displayTxt = display.querySelector('.ar-search-display-text');
        displayTxt.textContent = display.closest('[data-list-key="spMembers"]') ? 'Select SP member…' : 'Select committee…';
        displayTxt.classList.add('text-gray-400');
        displayTxt.classList.remove('text-gray-900');
        display.classList.add('ar-exclusive-disabled');
    } else {
        // Both empty — re-enable both
        customInput.disabled = false;
        customInput.classList.remove('ar-exclusive-disabled');
        customInput.placeholder = customInput.closest('.ar-person-row') ? 'Or custom name' : 'Or custom name(s), comma-separated';
        display.classList.remove('ar-exclusive-disabled');
    }
}

// ── Tags chip UI ──────────────────────────────────────────────────────────────
function initTagField(config) {
    const { fieldId, inputId, chipsId, hiddenId } = config;
    const field  = document.getElementById(fieldId);
    const input  = document.getElementById(inputId);
    const chips  = document.getElementById(chipsId);
    const hidden = document.getElementById(hiddenId);
    if (!field || !input) return;

    let tags = [];

    // Pre-populate from hidden value (edit mode)
    if (hidden && hidden.value) {
        String(hidden.value).split(',').map(t => t.trim().toLowerCase()).filter(Boolean).forEach(t => {
            if (!tags.includes(t)) tags.push(t);
        });
        renderChips();
    }

    function renderChips() {
        chips.innerHTML = '';
        tags.forEach((tag, idx) => {
            const chip = document.createElement('span');
            chip.className = 'ar-tag-chip';
            chip.innerHTML = escHtml(tag) + '<button type="button" aria-label="Remove tag ' + escAttr(tag) + '">&times;</button>';
            chip.querySelector('button').addEventListener('click', () => removeTag(idx));
            chips.appendChild(chip);
        });
        if (hidden) hidden.value = tags.join(',');
    }

    function addTag(raw) {
        const parts = raw.split(',').map(t => t.trim().toLowerCase()).filter(Boolean);
        let changed = false;
        parts.forEach(t => {
            if (t && !tags.includes(t)) { tags.push(t); changed = true; }
        });
        if (changed) renderChips();
    }

    function removeTag(idx) {
        tags.splice(idx, 1);
        renderChips();
        input.focus();
    }

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            const v = input.value.trim();
            if (v) { addTag(v); input.value = ''; }
        } else if (e.key === 'Backspace' && !input.value && tags.length) {
            removeTag(tags.length - 1);
        }
    });
    input.addEventListener('blur', () => {
        const v = input.value.trim();
        if (v) { addTag(v); input.value = ''; }
    });
    field.addEventListener('click', () => input.focus());
}

// ── Drag-and-drop file upload ─────────────────────────────────────────────────
function initDropZone(config) {
    const { zoneId, inputId, listId, errorId } = config;
    const zone   = document.getElementById(zoneId);
    const input  = document.getElementById(inputId);
    const list   = document.getElementById(listId);
    const errEl  = document.getElementById(errorId);
    if (!zone || !input) return;

    // Track selected File objects
    let files = [];

    function fmt(bytes) {
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
        if (bytes >= 1024)    return (bytes / 1024).toFixed(1) + ' KB';
        return bytes + ' B';
    }

    function mimeIcon(file) {
        if (file.type === 'application/pdf')        return { cls: 'text-red-500',     label: 'PDF' };
        if (file.type.includes('word'))              return { cls: 'text-blue-500',    label: 'DOC' };
        if (file.type.includes('excel') || file.type.includes('spreadsheet')) return { cls: 'text-emerald-500', label: 'XLS' };
        if (file.type.startsWith('image/'))          return { cls: 'text-purple-500',  label: 'IMG' };
        return { cls: 'text-gray-400', label: 'FILE' };
    }

    function validate(newFiles) {
        const errs = [];
        if (files.length + newFiles.length > MAX_FILES) {
            errs.push(`Maximum ${MAX_FILES} files allowed. You already have ${files.length}.`);
            return errs;
        }
        newFiles.forEach(f => {
            if (f.size > MAX_FILE_BYTES) errs.push(`"${f.name}" exceeds the 25 MB limit.`);
            const ext = '.' + f.name.split('.').pop().toLowerCase();
            if (!ALLOWED_EXTS.includes(ext)) errs.push(`"${f.name}" has an unsupported file type.`);
        });
        return errs;
    }

    function renderList() {
        list.innerHTML = '';
        files.forEach((f, idx) => {
            const { cls, label } = mimeIcon(f);
            const item = document.createElement('div');
            item.className = 'ar-file-item';
            item.innerHTML = `
                <div class="ar-file-icon ${cls}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div class="ar-file-meta">
                    <p class="ar-file-name" title="${escAttr(f.name)}">${escHtml(f.name)}</p>
                    <p class="ar-file-size">${fmt(f.size)}</p>
                </div>
                <button type="button" class="ar-file-remove" aria-label="Remove ${escAttr(f.name)}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>`;
            item.querySelector('.ar-file-remove').addEventListener('click', () => {
                files.splice(idx, 1);
                renderList();
                syncInput();
            });
            list.appendChild(item);
        });
    }

    // Sync File objects back to the <input type="file"> via DataTransfer
    function syncInput() {
        try {
            const dt = new DataTransfer();
            files.forEach(f => dt.items.add(f));
            input.files = dt.files;
        } catch (e) {
            // DataTransfer not supported (rare) — input will be empty; server handles gracefully
        }
    }

    function addFiles(newFiles) {
        const arr = Array.from(newFiles);
        const errs = validate(arr);
        if (errs.length) {
            if (errEl) { errEl.textContent = errs.join(' '); errEl.classList.remove('hidden'); }
            return;
        }
        if (errEl) errEl.classList.add('hidden');
        files = files.concat(arr);
        renderList();
        syncInput();
    }

    zone.addEventListener('click', (e) => { if (e.target === zone || zone.contains(e.target)) input.click(); });
    zone.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
    input.addEventListener('change', () => { addFiles(input.files); });

    zone.addEventListener('dragover',  (e) => { e.preventDefault(); zone.classList.add('ar-drag-over'); });
    zone.addEventListener('dragleave', (e) => { zone.classList.remove('ar-drag-over'); });
    zone.addEventListener('drop',      (e) => { e.preventDefault(); zone.classList.remove('ar-drag-over'); addFiles(e.dataTransfer.files); });
}

// ── Row management ────────────────────────────────────────────────────────────
function buildPersonRow(prefill) {
    prefill = prefill || {};
    const role = prefill.role === 'CO_AUTHOR' ? 'CO_AUTHOR' : 'AUTHOR';
    const row = document.createElement('div');
    row.className = 'ar-person-row';
    row.dataset.role = role;

    // Build SP members options
    let memberOpts = '<option value="">Select SP member…</option>';
    DATA.spMembers.forEach(m => {
        const pos = m.position ? ' — ' + m.position : '';
        const sel = prefill.sp_member_id && String(m.sp_member_id) === String(prefill.sp_member_id) ? ' selected' : '';
        memberOpts += `<option value="${m.sp_member_id}"${sel}>${escHtml(m.full_name)}${escHtml(pos)}</option>`;
    });

    row.innerHTML = `
        <input type="hidden" name="author_role[]" value="${role}">
        <div class="ar-person-inner">
            <div class="ar-person-member-col">
                <div class="ar-search-select" data-list-key="spMembers">
                    <div class="ar-search-display" tabindex="0" role="combobox" aria-expanded="false" aria-haspopup="listbox" aria-label="Select existing SP member">
                        <span class="ar-search-display-text text-gray-400">Select SP member…</span>
                        <svg class="ar-search-chevron h-4 w-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <input type="hidden" name="author_sp_member_id[]" class="ar-search-hidden-id" value="${escAttr(String(prefill.sp_member_id || ''))}">
                    <div class="ar-search-dropdown hidden" role="listbox">
                        <div class="ar-search-filter-wrap">
                            <input type="text" class="ar-search-filter-input" placeholder="Search name…" aria-label="Search SP members" autocomplete="off">
                        </div>
                        <div class="ar-search-options"></div>
                    </div>
                </div>
            </div>
            <div class="ar-person-custom-col">
                <input type="text" name="author_custom_name[]"
                       placeholder="Or custom name"
                       maxlength="200"
                       value="${escAttr(prefill.custom_author_name || '')}"
                       class="ar-input ar-person-custom text-sm"
                       aria-label="Custom person name">
            </div>
            <button type="button" class="ar-remove-btn" aria-label="Remove this person">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <p class="ar-row-error hidden text-xs text-red-600 mt-1" role="alert"></p>`;

    row.querySelector('.ar-remove-btn').addEventListener('click', () => removeRow(row.querySelector('.ar-remove-btn')));

    // Init search select after appending
    setTimeout(() => {
        const ss = row.querySelector('.ar-search-select');
        if (ss) {
            initSearchSelect(ss);
            // If prefilled with sp_member_id, enforce exclusivity
            if (prefill.sp_member_id) enforceExclusive(ss, 'select');
            else if (prefill.custom_author_name) enforceExclusive(ss, 'custom');
        }
    }, 0);

    return row;
}

function buildCommitteeRow(prefill) {
    prefill = prefill || {};
    const row = document.createElement('div');
    row.className = 'ar-committee-row';

    row.innerHTML = `
        <div class="ar-committee-inner">
            <div class="ar-comm-member-col">
                <div class="ar-search-select" data-list-key="committees">
                    <div class="ar-search-display" tabindex="0" role="combobox" aria-expanded="false" aria-haspopup="listbox" aria-label="Select existing committee">
                        <span class="ar-search-display-text text-gray-400">Select committee…</span>
                        <svg class="ar-search-chevron h-4 w-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <input type="hidden" name="committee_id[]" class="ar-search-hidden-id" value="${escAttr(String(prefill.committee_id || ''))}">
                    <div class="ar-search-dropdown hidden" role="listbox">
                        <div class="ar-search-filter-wrap">
                            <input type="text" class="ar-search-filter-input" placeholder="Search committees…" aria-label="Search committees" autocomplete="off">
                        </div>
                        <div class="ar-search-options"></div>
                    </div>
                </div>
            </div>
            <div class="ar-comm-custom-col">
                <input type="text" name="committee_custom_name[]"
                       placeholder="Or custom name(s), comma-separated"
                       maxlength="200"
                       value="${escAttr(prefill.custom_committee_name || '')}"
                       class="ar-input ar-comm-custom text-sm"
                       aria-label="Custom committee name">
            </div>
            <button type="button" class="ar-remove-btn" aria-label="Remove this committee">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>`;

    row.querySelector('.ar-remove-btn').addEventListener('click', () => removeRow(row.querySelector('.ar-remove-btn')));

    setTimeout(() => {
        const ss = row.querySelector('.ar-search-select');
        if (ss) {
            initSearchSelect(ss);
            if (prefill.committee_id)          enforceExclusive(ss, 'select');
            else if (prefill.custom_committee_name) enforceExclusive(ss, 'custom');
        }
    }, 0);

    return row;
}

function removeRow(btn) {
    const row  = btn.closest('.ar-person-row, .ar-committee-row');
    const list = row.parentElement;
    if (row.classList.contains('ar-person-row') && row.dataset.role === 'CO_AUTHOR'
        && list.querySelectorAll(':scope > .ar-person-row').length === 1) {
        row.remove();
        return;
    }
    if (list.querySelectorAll(':scope > .ar-person-row, :scope > .ar-committee-row').length > 1) {
        row.remove();
    } else {
        // Last row — clear it rather than removing
        row.querySelectorAll('input[type=text], .ar-search-hidden-id').forEach(f => f.value = '');
        const display = row.querySelector('.ar-search-display');
        if (display) {
            const txt = display.querySelector('.ar-search-display-text');
            if (txt) { txt.textContent = row.querySelector('[data-list-key="spMembers"]') ? 'Select SP member…' : 'Select committee…'; txt.classList.add('text-gray-400'); txt.classList.remove('text-gray-900'); }
            display.classList.remove('ar-active', 'ar-exclusive-disabled');
        }
        const custom = row.querySelector('.ar-person-custom, .ar-comm-custom');
        if (custom) { custom.disabled = false; custom.classList.remove('ar-exclusive-disabled'); custom.placeholder = row.classList.contains('ar-person-row') ? 'Or custom name' : 'Or custom name(s), comma-separated'; }
    }
}

// ── Client-side dual-role validation ─────────────────────────────────────────
function validateAuthors(listIds) {
    const ids = Array.isArray(listIds) ? listIds : [listIds];
    const rows = ids.flatMap(id => Array.from(document.getElementById(id)?.querySelectorAll('.ar-person-row') || []));
    const seenAuthor   = new Set();
    const seenCoAuthor = new Set();
    let valid = true;

    rows.forEach(row => {
        const errEl   = row.querySelector('.ar-row-error');
        const roleEl  = row.querySelector('[name="author_role[]"]');
        const idEl    = row.querySelector('.ar-search-hidden-id');
        const custEl  = row.querySelector('.ar-person-custom');
        const role    = roleEl?.value || row.dataset.role || 'AUTHOR';
        const id      = idEl ? idEl.value.trim() : '';
        const custom  = custEl ? custEl.value.trim().toLowerCase() : '';
        const key     = id ? ('id:' + id) : (custom ? ('name:' + custom) : null);

        if (errEl) errEl.classList.add('hidden');

        if (!key) return; // Empty row, skip

        const otherSet = role === 'AUTHOR' ? seenCoAuthor : seenAuthor;
        const mySet    = role === 'AUTHOR' ? seenAuthor   : seenCoAuthor;

        if (otherSet.has(key)) {
            if (errEl) { errEl.textContent = 'This person is already listed with the other role — a person cannot be both Author and Co-author.'; errEl.classList.remove('hidden'); }
            valid = false;
        }
        mySet.add(key);
    });

    return valid;
}

// ── Exclusive doc type ────────────────────────────────────────────────────────
function initDocTypeExclusive(prefix) {
    const selectEl = document.querySelector(`[data-group="${prefix}"][data-type="select"]`);
    const textEl   = document.querySelector(`[data-group="${prefix}"][data-type="text"]`);
    if (!selectEl || !textEl) return;
    const clearText = textEl.parentElement.querySelector('[data-clear-target="text"]');

    function updateState() {
        if (selectEl.value) {
            textEl.value = '';
            textEl.disabled = true;
            textEl.classList.add('ar-exclusive-disabled');
            selectEl.classList.add('ar-exclusive-active');
        } else if (textEl.value.trim()) {
            selectEl.value = '';
            selectEl.disabled = true;
            selectEl.classList.add('ar-exclusive-disabled');
            textEl.classList.add('ar-exclusive-active');
        } else {
            textEl.disabled   = false;
            selectEl.disabled = false;
            textEl.classList.remove('ar-exclusive-disabled', 'ar-exclusive-active');
            selectEl.classList.remove('ar-exclusive-disabled', 'ar-exclusive-active');
        }
        if (clearText) clearText.classList.toggle('hidden', !selectEl.value && !textEl.value.trim());
    }

    selectEl.addEventListener('change', updateState);
    textEl.addEventListener('input',    updateState);
    clearText?.addEventListener('click', () => {
        selectEl.value = '';
        textEl.value = '';
        updateState();
        selectEl.focus();
    });
    // Keyboard: when user clears the disabled field
    textEl.addEventListener('focus',    () => { if (textEl.disabled) { selectEl.value = ''; selectEl.disabled = false; selectEl.classList.remove('ar-exclusive-disabled','ar-exclusive-active'); textEl.disabled = false; textEl.classList.remove('ar-exclusive-disabled'); } });
    updateState();
}

// ── Utility: HTML/attr escaping ───────────────────────────────────────────────
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escAttr(s) {
    return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

// ── Public API ────────────────────────────────────────────────────────────────
window.AR = {
    openCreateModal: function() {
        openModal('createModal');
    },
    closeCreateModal: function() {
        closeModal('createModal');
    },
    openDeleteModal: function(id, name) {
        document.getElementById('deleteRecordId').value = id;
        document.getElementById('deleteRecordName').textContent = name;
        openModal('deleteModal');
    },
    closeDeleteModal: function() {
        closeModal('deleteModal');
    },
    openRecordEditModal: openRecordEditModal,
    openEditModal: function() { openModal('editModal'); },
    closeEditModal: function() { closeModal('editModal'); },
    addPersonRow: function(listId, role) {
        const list = document.getElementById(listId);
        if (!list) return;
        const row = buildPersonRow({ role: role || (listId.includes('coauthor') ? 'CO_AUTHOR' : 'AUTHOR') });
        list.appendChild(row);
    },
    addCommitteeRow: function(listId) {
        const list = document.getElementById(listId);
        if (!list) return;
        const row = buildCommitteeRow({});
        list.appendChild(row);
    },
    removeRow: removeRow,
    // Allow show.php to call these too
    openModal:  openModal,
    closeModal: closeModal,
    buildPersonRow: buildPersonRow,
    buildCommitteeRow: buildCommitteeRow,
    enforceExclusive: enforceExclusive,
    initSearchSelect: initSearchSelect,
    initDocTypeExclusive: initDocTypeExclusive,
    initTagField: initTagField,
    initDropZone: initDropZone,
    validateAuthors: validateAuthors,
    initMultiSelect: initMultiSelect,
    validateMultiAuthors: validateMultiAuthors,
    serializeMultiSelects: serializeMultiSelects,
    mergeLookupData: function(lists) {
        for (const key of ['spMembers', 'committees']) {
            const idField = key === 'spMembers' ? 'sp_member_id' : 'id';
            const merged = new Map(BASE_LOOKUPS[key].map(item => [String(item[idField]), item]));
            (lists[key] || []).forEach(item => merged.set(String(item[idField]), item));
            DATA[key] = Array.from(merged.values());
        }
    },
};

// ── Init create form ──────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    // Init all search selects in create modal
    document.querySelectorAll('#createModal .ar-search-select').forEach(w => initSearchSelect(w));
    document.querySelectorAll('#createModal .ar-multi-select').forEach(w => initMultiSelect(w));

    // Exclusive change listeners for custom text in rows (delegated)
    document.addEventListener('input', function (e) {
        if (e.target.classList.contains('ar-person-custom') || e.target.classList.contains('ar-comm-custom')) {
            enforceExclusive(e.target, e.target.value.trim() ? 'custom' : 'clear-custom');
        }
    });

    // Doc type exclusive
    initDocTypeExclusive('c_doctype');

    // Tags
    initTagField({ fieldId: 'c_tags_field', inputId: 'c_tags_input', chipsId: 'c_tags_chips', hiddenId: 'c_tags_hidden' });

    // Drop zone
    initDropZone({ zoneId: 'c_drop_zone', inputId: 'c_file_input', listId: 'c_file_list', errorId: 'c_file_error' });

    // Pre-validate on submit
    const form = document.getElementById('createForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            if (!validateMultiAuthors(form)) {
                e.preventDefault();
                return;
            }
            serializeMultiSelects(form);
            // Ensure tags hidden is up-to-date (blur fires on submit anyway, but be safe)
        });
    }
});

})();
</script>

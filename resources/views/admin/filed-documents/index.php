<?php
/**
 * Admin → Filed Documents — index view
 *
 * Rendered via AdminFiledDocumentsController::index()
 * Layout is applied by the controller (ob_start / ob_get_clean pattern).
 */
$pageTitle       = $pageTitle       ?? 'Filed Documents';
$pageSubtitle    = $pageSubtitle    ?? '';
$toBeFiledDocs   = $toBeFiledDocs   ?? [];
$toBeFiledCount  = $toBeFiledCount  ?? 0;
$filedDocuments  = $filedDocuments  ?? [];
$totalRows       = $totalRows       ?? 0;
$totalPages      = $totalPages      ?? 1;
$page            = $page            ?? 1;
$search          = $search          ?? '';
$filterType      = $filterType      ?? '';
$filterYear      = $filterYear      ?? '';
$documentTypes   = $documentTypes   ?? [];
$approvalYears   = $approvalYears   ?? [];
$totalFiledCount = $totalFiledCount ?? 0;
$publicCount     = $publicCount     ?? 0;

$flashSuccess = flash_get('success');
$flashError   = flash_get('error');
$flashErrors  = flash_get('errors');
$filingDocId  = (int) (flash_get('filing_doc_id') ?? 0);
// Re-open the modal when server-side validation fails
$autoOpenModal = ($filingDocId > 0 && !empty($flashErrors));

// Pre-populate modal from failed submission
$prevDescription = htmlspecialchars($_POST['description'] ?? '');
$prevTags        = htmlspecialchars($_POST['tags'] ?? '');
$prevIsPublic    = !empty($_POST['is_public']);
?>

<div class="space-y-6">

    <!-- ── Flash messages ────────────────────────────────────────────────── -->
    <?php if ($flashSuccess): ?>
        <div class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4" role="alert">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <p class="text-sm text-emerald-800"><?= htmlspecialchars($flashSuccess) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 px-5 py-4" role="alert">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            <p class="text-sm text-red-800"><?= htmlspecialchars($flashError) ?></p>
        </div>
    <?php endif; ?>

    <!-- ── Page header ───────────────────────────────────────────────────── -->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm font-medium text-blue-100">DOCUMENT ARCHIVE</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                        <?= htmlspecialchars($pageTitle) ?>
                    </h1>
                    <p class="mt-2 text-sm leading-6 text-blue-100">
                        <?= htmlspecialchars($pageSubtitle) ?>
                    </p>
                </div>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
        </div>
    </section>

    <!-- ── Statistics ────────────────────────────────────────────────────── -->
    <section>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">To Be Filed</p>
                        <p class="mt-2 text-3xl font-bold text-amber-600"><?= number_format($toBeFiledCount) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-500">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-400">Finalized, awaiting filing</p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Total Filed</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($totalFiledCount) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-primary">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-400">Documents in the archive</p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Public Records</p>
                        <p class="mt-2 text-3xl font-bold text-emerald-600"><?= number_format($publicCount) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-500">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"/>
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-400">Visible on public portal</p>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- ── SECTION 1: To Be Filed ──────────────────────────────────────── -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <section class="rounded-2xl border border-amber-200 bg-white">
        <div class="flex items-center justify-between border-b border-amber-100 px-6 py-4">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="font-semibold text-gray-900">To Be Filed</h2>
                    <p class="text-xs text-gray-500">
                        <?= number_format($toBeFiledCount) ?> document<?= $toBeFiledCount !== 1 ? 's' : '' ?> awaiting filing
                    </p>
                </div>
            </div>
            <?php if ($toBeFiledCount > 0): ?>
                <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">
                    <?= number_format($toBeFiledCount) ?> pending
                </span>
            <?php endif; ?>
        </div>

        <!-- Scrollable grid – constrained height -->
        <div class="max-h-[480px] overflow-y-auto px-6 py-5">
            <?php if (empty($toBeFiledDocs)): ?>
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-400">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <p class="mt-4 text-sm font-medium text-gray-700">All caught up!</p>
                    <p class="mt-1 text-xs text-gray-500">No finalized documents are waiting to be filed.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <?php foreach ($toBeFiledDocs as $doc): ?>
                        <?php
                        $typeColor   = $doc['document_type_badge_color'] ?? '#2563EB';
                        $displayDate = !empty($doc['date_approved'])
                            ? date('M j, Y', strtotime($doc['date_approved']))
                            : (!empty($doc['finalized_at']) ? date('M j, Y', strtotime($doc['finalized_at'])) : '—');
                        $isThisDocFailing = $autoOpenModal && $filingDocId === (int)$doc['id'];
                        ?>
                        <div class="flex flex-col justify-between rounded-xl border border-gray-200 bg-gray-50 p-4
                                    transition hover:border-amber-300 hover:shadow-sm
                                    <?= $isThisDocFailing ? 'ring-2 ring-red-300' : '' ?>">

                            <!-- Header -->
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-gray-900 truncate">
                                        <?= htmlspecialchars($doc['tracking_number']) ?>
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 line-clamp-2">
                                        <?= htmlspecialchars($doc['subject_matter']) ?>
                                    </p>
                                </div>
                                <span class="ml-2 shrink-0 inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap"
                                      style="background-color:<?= htmlspecialchars($typeColor) ?>1a; color:<?= htmlspecialchars($typeColor) ?>;">
                                    <?= htmlspecialchars($doc['document_type_name'] ?? '—') ?>
                                </span>
                            </div>

                            <!-- Meta -->
                            <div class="mt-3 flex items-center gap-3 text-[11px] text-gray-500">
                                <span class="font-medium text-gray-700">
                                    <?= !empty($doc['approved_number'])
                                        ? htmlspecialchars($doc['approved_number'])
                                        : 'No Approved No.' ?>
                                </span>
                                <span class="text-gray-300">·</span>
                                <span><?= htmlspecialchars($displayDate) ?></span>
                            </div>

                            <!-- Actions -->
                            <div class="mt-3 flex items-center gap-2">
                                <a href="<?= BASE_URL ?>/admin/filed/source-show?id=<?= (int)$doc['id'] ?>"
                                   class="flex-1 rounded-lg border border-gray-200 py-1.5 text-center text-xs
                                          font-medium text-gray-700 hover:bg-white hover:border-primary
                                          hover:text-primary transition">
                                    View
                                </a>
                                <button type="button"
                                        onclick="openFilingModal(
                                            <?= (int)$doc['id'] ?>,
                                            <?= htmlspecialchars(json_encode($doc['tracking_number']), ENT_QUOTES) ?>,
                                            <?= htmlspecialchars(json_encode($doc['subject_matter']), ENT_QUOTES) ?>,
                                            <?= htmlspecialchars(json_encode($doc['document_type_name'] ?? '—'), ENT_QUOTES) ?>,
                                            <?= htmlspecialchars(json_encode($displayDate), ENT_QUOTES) ?>
                                        )"
                                        class="flex-1 rounded-lg bg-amber-500 py-1.5 text-center text-xs
                                               font-semibold text-white hover:bg-amber-600 transition">
                                    File
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- ── SECTION 2: Filed Documents Table ───────────────────────────── -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <section class="rounded-2xl border border-gray-200 bg-white">

        <!-- Table header / filters -->
        <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900">Filed Documents</h2>
                <p class="mt-1 text-xs text-gray-500">
                    <?= number_format($totalRows) ?> record<?= $totalRows !== 1 ? 's' : '' ?> found
                </p>
            </div>

            <form method="GET" action="<?= BASE_URL ?>/admin/filed"
                  class="flex flex-wrap gap-3 items-center">

                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                           placeholder="Tracking #, subject, approved no…"
                           class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm
                                  text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none
                                  focus:ring-2 focus:ring-primary/20 lg:w-60">
                </div>

                <select name="type"
                        class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800
                               focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">All Types</option>
                    <?php foreach ($documentTypes as $dt): ?>
                        <option value="<?= (int)$dt['id'] ?>" <?= $filterType == $dt['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dt['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="year"
                        class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800
                               focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">All Years</option>
                    <?php foreach ($approvalYears as $yr): ?>
                        <option value="<?= (int)$yr ?>" <?= $filterYear == $yr ? 'selected' : '' ?>>
                            <?= (int)$yr ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit"
                        class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium
                               text-gray-700 hover:bg-gray-50 transition">
                    Filter
                </button>

                <?php if ($search !== '' || $filterType !== '' || $filterYear !== ''): ?>
                    <a href="<?= BASE_URL ?>/admin/filed"
                       class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium
                              text-gray-700 hover:bg-gray-50 transition">
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
                        <th class="px-5 py-3 font-medium">Approved No.</th>
                        <th class="px-5 py-3 font-medium">Type &amp; Subject</th>
                        <th class="px-5 py-3 font-medium">Authors / Sponsors</th>
                        <th class="px-5 py-3 font-medium">Tags</th>
                        <th class="px-5 py-3 font-medium whitespace-nowrap">Date Approved</th>
                        <th class="px-5 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($filedDocuments)): ?>
                        <tr>
                            <td colspan="6" class="px-5 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                              d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">No filed documents found.</p>
                                <p class="mt-1 text-xs text-gray-500">
                                    <?= ($search !== '' || $filterType !== '' || $filterYear !== '')
                                        ? 'Try adjusting your filters.'
                                        : 'Filed documents will appear here once documents are filed.' ?>
                                </p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($filedDocuments as $fd): ?>
                            <?php $typeColor = $fd['document_type_badge_color'] ?? '#2563EB'; ?>
                            <tr class="hover:bg-gray-50/50 transition">

                                <!-- Approved No. -->
                                <td class="px-5 py-4">
                                    <div class="font-semibold text-gray-900 text-sm">
                                        <?= htmlspecialchars($fd['approved_number'] ?? $fd['tracking_number']) ?>
                                    </div>
                                    <?php if (!empty($fd['approved_number'])): ?>
                                        <div class="mt-0.5 text-[11px] text-gray-400">
                                            TN: <?= htmlspecialchars($fd['tracking_number']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($fd['filing_is_public'])): ?>
                                        <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-medium text-emerald-700">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"/>
                                            </svg>
                                            Public
                                        </span>
                                    <?php else: ?>
                                        <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-500">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                            </svg>
                                            Private
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Type & Subject -->
                                <td class="px-5 py-4 max-w-[260px]">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                          style="background-color:<?= htmlspecialchars($typeColor) ?>1a; color:<?= htmlspecialchars($typeColor) ?>;">
                                        <?= htmlspecialchars($fd['document_type_name'] ?? '—') ?>
                                    </span>
                                    <p class="mt-1 text-sm text-gray-800 line-clamp-2">
                                        <?= htmlspecialchars($fd['subject_matter']) ?>
                                    </p>
                                </td>

                                <!-- Authors/Sponsors -->
                                <td class="px-5 py-4 max-w-[180px]">
                                    <?php if (!empty($fd['authors'])): ?>
                                        <div class="space-y-1">
                                            <?php foreach (['AUTHOR', 'CO_AUTHOR'] as $role): ?>
                                                <?php
                                                $roleAuthors = array_values(array_filter(
                                                    $fd['authors'],
                                                    static fn(array $author): bool => $author['author_role'] === $role
                                                ));
                                                ?>
                                                <?php if (!empty($roleAuthors)): ?>
                                                    <?php if (!empty($roleAuthors[0]['is_all_selected'])): ?>
                                                        <div class="text-xs text-gray-700">
                                                            <span class="text-[10px] font-medium uppercase text-gray-400 mr-1"><?= $role === 'CO_AUTHOR' ? 'Co' : 'Au' ?>:</span>
                                                            ALL
                                                        </div>
                                                    <?php else: ?>
                                                        <?php foreach ($roleAuthors as $a): ?>
                                                            <div class="text-xs text-gray-700">
                                                                <span class="text-[10px] font-medium uppercase text-gray-400 mr-1"><?= $role === 'CO_AUTHOR' ? 'Co' : 'Au' ?>:</span>
                                                                <?= htmlspecialchars($a['full_name']) ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Tags -->
                                <td class="px-5 py-4 max-w-[160px]">
                                    <?php if (!empty($fd['tags'])): ?>
                                        <div class="flex flex-wrap gap-1">
                                            <?php foreach (array_slice($fd['tags'], 0, 3) as $tag): ?>
                                                <span class="inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700">
                                                    <?= htmlspecialchars($tag) ?>
                                                </span>
                                            <?php endforeach; ?>
                                            <?php if (count($fd['tags']) > 3): ?>
                                                <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-[11px] text-gray-500">
                                                    +<?= count($fd['tags']) - 3 ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Date Approved -->
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <?php if (!empty($fd['date_approved'])): ?>
                                        <div class="text-sm text-gray-900">
                                            <?= htmlspecialchars(date('M j, Y', strtotime($fd['date_approved']))) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Actions -->
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="<?= BASE_URL ?>/admin/filed/show?id=<?= (int)$fd['id'] ?>"
                                           class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200
                                                  px-3 py-1.5 text-xs font-medium text-gray-700
                                                  hover:border-primary hover:bg-blue-50 hover:text-primary transition">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            View
                                        </a>
                                        <a href="<?= BASE_URL ?>/admin/filed/download-all?id=<?= (int)$fd['id'] ?>"
                                           class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200
                                                  px-3 py-1.5 text-xs font-medium text-gray-700
                                                  hover:border-emerald-400 hover:bg-emerald-50 hover:text-emerald-700 transition">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                            </svg>
                                            Download
                                        </a>
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
                Page <span class="font-medium text-gray-700"><?= $page ?></span>
                of <span class="font-medium text-gray-700"><?= $totalPages ?></span>
                (<?= number_format($totalRows) ?> total)
            </p>
            <div class="flex items-center gap-1">
                <?php
                $qArgs = [];
                if ($search !== '')     $qArgs['search'] = $search;
                if ($filterType !== '') $qArgs['type']   = $filterType;
                if ($filterYear !== '') $qArgs['year']   = $filterYear;
                $qs = !empty($qArgs) ? '&' . http_build_query($qArgs) : '';
                ?>
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $qs ?>"
                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                        Prev
                    </a>
                <?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="?page=<?= $i ?><?= $qs ?>"
                       class="rounded-lg border px-3 py-1.5 text-sm font-medium transition
                              <?= $i === $page ? 'border-primary bg-primary text-white' : 'border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $qs ?>"
                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                        Next
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </section>

</div><!-- end main wrapper -->

<!-- ════════════════════════════════════════════════════════════════════════ -->
<!-- ── Filing Modal ────────────────────────────────────────────────────── -->
<!-- ════════════════════════════════════════════════════════════════════════ -->
<?php
/*
 * Build the data needed to auto-reopen the modal when validation fails.
 * We echo the JSON so JavaScript can read it synchronously on DOMContentLoaded.
 */
$autoReopenData = null;
if ($autoOpenModal) {
    foreach ($toBeFiledDocs as $tbf) {
        if ((int)$tbf['id'] === $filingDocId) {
            $autoReopenData = [
                'id'          => (int)$tbf['id'],
                'tracking'    => $tbf['tracking_number'],
                'subject'     => $tbf['subject_matter'],
                'docType'     => $tbf['document_type_name'] ?? '—',
                'approvedDate'=> !empty($tbf['date_approved'])
                                    ? date('M j, Y', strtotime($tbf['date_approved']))
                                    : (!empty($tbf['finalized_at']) ? date('M j, Y', strtotime($tbf['finalized_at'])) : '—'),
            ];
            break;
        }
    }
}
?>

<!-- Backdrop + modal wrapper -->
<div id="filingModal"
     class="fixed inset-0 z-[100] hidden items-center justify-center p-4"
     role="dialog" aria-modal="true" aria-labelledby="filingModalTitle">

    <!-- Backdrop -->
    <div id="filingModalBackdrop"
         class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm"></div>

    <!-- Panel -->
    <div id="filingModalPanel"
         class="relative z-10 w-full max-w-xl rounded-2xl bg-white shadow-2xl">

        <!-- Header -->
        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
            <h3 id="filingModalTitle" class="text-base font-semibold text-gray-900">
                File Document
            </h3>
            <button type="button" id="filingModalClose"
                    class="flex h-8 w-8 items-center justify-center rounded-xl text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Validation errors (re-shown after failed submission) -->
        <?php if (!empty($flashErrors) && $autoOpenModal): ?>
            <div class="mx-6 mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3">
                <ul class="space-y-1 text-sm text-red-700">
                    <?php foreach ($flashErrors as $err): ?>
                        <li class="flex items-start gap-2">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <?= htmlspecialchars($err) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Source document summary -->
        <div class="mx-6 mt-4 rounded-xl border border-gray-100 bg-gray-50 px-4 py-3">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Source Document</p>
            <div class="mt-2 space-y-1.5">
                <div class="flex items-center gap-2">
                    <span class="w-24 shrink-0 text-xs font-medium text-gray-500">Tracking No.</span>
                    <span id="modal-tracking" class="text-xs font-semibold text-gray-900"></span>
                </div>
                <div class="flex items-start gap-2">
                    <span class="w-24 shrink-0 text-xs font-medium text-gray-500">Subject</span>
                    <span id="modal-subject" class="text-xs text-gray-800 line-clamp-2"></span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-24 shrink-0 text-xs font-medium text-gray-500">Type</span>
                    <span id="modal-type" class="text-xs text-gray-800"></span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-24 shrink-0 text-xs font-medium text-gray-500">Date</span>
                    <span id="modal-date" class="text-xs text-gray-800"></span>
                </div>
            </div>
        </div>

        <!-- Form -->
        <form id="filingForm"
              action="<?= BASE_URL ?>/admin/filed/file"
              method="POST"
              class="px-6 pb-6 pt-4 space-y-4">

            <input type="hidden" id="filingDocumentId" name="document_id" value="">

            <!-- Description -->
            <div>
                <label for="filing-description" class="block text-sm font-medium text-gray-700">
                    Filing Description <span class="text-red-500">*</span>
                </label>
                <textarea id="filing-description"
                          name="description"
                          rows="3"
                          required
                          placeholder="Brief description of this filing…"
                          class="mt-1.5 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm
                                 text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none
                                 focus:ring-2 focus:ring-primary/20 resize-none"><?= $prevDescription ?></textarea>
            </div>

            <!-- Tags -->
            <div>
                <label for="filing-tags" class="block text-sm font-medium text-gray-700">
                    Tags
                    <span class="text-xs font-normal text-gray-400">(comma-separated)</span>
                </label>
                <input type="text"
                       id="filing-tags"
                       name="tags"
                       value="<?= $prevTags ?>"
                       placeholder="e.g. ordinance, health, 2024"
                       class="mt-1.5 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm
                              text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none
                              focus:ring-2 focus:ring-primary/20">
            </div>

            <!-- Set as Public toggle -->
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                <div>
                    <p class="text-sm font-medium text-gray-700">Set as Public</p>
                    <p class="text-xs text-gray-500">Make this filing visible on the public portal</p>
                </div>
                <label class="relative inline-flex cursor-pointer items-center">
                    <input type="checkbox" id="filingIsPublic" name="is_public" value="1"
                           class="sr-only peer" <?= $prevIsPublic ? 'checked' : '' ?>>
                    <div class="h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px]
                                after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition-all
                                after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-5
                                peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary/20"></div>
                </label>
            </div>

            <!-- Buttons -->
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" id="filingCancelBtn"
                        class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-medium
                               text-gray-700 hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit" id="filingSubmitBtn"
                        class="rounded-xl bg-primary px-5 py-2 text-sm font-semibold text-white
                               hover:bg-primary/90 transition">
                    File Document
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ── Filing Modal Controller ─────────────────────────────────────────────────
(function () {
    'use strict';

    var modal         = document.getElementById('filingModal');
    var backdrop      = document.getElementById('filingModalBackdrop');
    var closeBtn      = document.getElementById('filingModalClose');
    var cancelBtn     = document.getElementById('filingCancelBtn');
    var submitBtn     = document.getElementById('filingSubmitBtn');
    var form          = document.getElementById('filingForm');
    var docIdInput    = document.getElementById('filingDocumentId');

    function openModal(id, tracking, subject, docType, approvedDate) {
        docIdInput.value = id;
        document.getElementById('modal-tracking').textContent = tracking;
        document.getElementById('modal-subject').textContent  = subject;
        document.getElementById('modal-type').textContent     = docType;
        document.getElementById('modal-date').textContent     = approvedDate;

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
        closeBtn.focus();
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
        submitBtn.disabled = false;
        submitBtn.textContent = 'File Document';
    }

    // Public hook called from button onclick attributes
    window.openFilingModal = openModal;

    if (closeBtn)  closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    if (backdrop)  backdrop.addEventListener('click', closeModal);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });

    // ── Form submit guard ─────────────────────────────────────────────────
    form.addEventListener('submit', function () {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<svg class="mr-2 inline h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">'
            + '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>'
            + '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>'
            + '</svg>Filing…';
    });

    // ── Auto re-open modal after server-side validation failure ───────────
    var autoReopen = <?= $autoReopenData ? json_encode($autoReopenData) : 'null' ?>;
    if (autoReopen) {
        document.addEventListener('DOMContentLoaded', function () {
            openModal(
                autoReopen.id,
                autoReopen.tracking,
                autoReopen.subject,
                autoReopen.docType,
                autoReopen.approvedDate
            );
        });
    }

}());
</script>

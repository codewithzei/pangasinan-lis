<?php
/**
 * Plenary Referred to Committee — tracking list
 *
 * Tracks documents that were referred to a Committee during First Reading.
 * The page is read-only except for the "Proceed to 2nd Reading" action, which
 * reuses the existing POST plenary/second-reading/advance-from-committee
 * endpoint (gated server-side on the shared "Returned to Plenary" status).
 *
 * The status column reflects documents.current_status_id — the SAME shared
 * status used by every other division. No Plenary-only status is introduced.
 *
 * Variables supplied by PlenaryReadingController::referredToCommitteeIndex():
 *   $documents          array
 *   $page               int
 *   $totalPages         int
 *   $total              int
 *   $search             string
 *   $tab                string  ('in_committee' | 'returned')
 *   $inCommitteeCount   int
 *   $returnedCount      int
 *   $success            string|null
 *   $error              string|null
 *   $errors             array
 */

$documents        = $documents        ?? [];
$page             = $page             ?? 1;
$totalPages       = $totalPages       ?? 1;
$total            = $total            ?? 0;
$search           = $search           ?? '';
$tab              = $tab              ?? 'in_committee';
$inCommitteeCount = $inCommitteeCount ?? 0;
$returnedCount    = $returnedCount    ?? 0;
$errors           = $errors           ?? [];

ob_start();

function rtcSessionLabel(string $type): string {
    return match ($type) {
        'REGULAR'        => 'Regular',
        'REGULAR_ONLINE' => 'Regular (Online)',
        'SPECIAL'        => 'Special',
        'SPECIAL_ONLINE' => 'Special (Online)',
        default          => $type ?: '—',
    };
}

function rtcBuildTabUrl(string $targetTab, string $search): string {
    $qs = 'tab=' . $targetTab;
    if ($search !== '') {
        $qs .= '&search=' . urlencode($search);
    }
    return BASE_URL . '/plenary/referred-to-committee?' . $qs;
}

function rtcBuildPageUrl(string $tab, int $pageNum, string $search): string {
    $qs = 'tab=' . $tab . '&page=' . $pageNum;
    if ($search !== '') {
        $qs .= '&search=' . urlencode($search);
    }
    return BASE_URL . '/plenary/referred-to-committee?' . $qs;
}
?>

<div class="space-y-6">

    <?php if (!empty($success ?? null)): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($error ?? null)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc list-inside space-y-1"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10 max-w-3xl">
                <p class="text-sm font-medium text-blue-100">PLENARY / LEGISLATIVE READINGS</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">Referred to Committee</h1>
                <p class="mt-2 max-w-xl text-sm leading-6 text-blue-100">Documents referred to a Committee during First Reading. Track committee progress and advance returned documents to Second Reading.</p>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
        </div>
    </section>

    <!-- Stats -->
    <section>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">In Committee</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($inCommitteeCount) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4"><span class="rounded-full bg-amber-50 px-2 py-1 text-xs font-medium text-amber-600">Committee work in progress</span></div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Returned — awaiting 2nd Reading</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($returnedCount) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4"><span class="rounded-full bg-violet-50 px-2 py-1 text-xs font-medium text-violet-600">Ready to proceed</span></div>
            </div>
        </div>
    </section>

    <!-- Tabs -->
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-1" aria-label="Referred to Committee tabs">
            <a href="<?= htmlspecialchars(rtcBuildTabUrl('in_committee', $search)) ?>"
               class="inline-flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium transition <?= $tab === 'in_committee' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' ?>">
                In Committee
                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600 <?= $tab === 'in_committee' ? 'bg-primary/10 text-primary' : '' ?>"><?= number_format($inCommitteeCount) ?></span>
            </a>
            <a href="<?= htmlspecialchars(rtcBuildTabUrl('returned', $search)) ?>"
               class="inline-flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium transition <?= $tab === 'returned' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' ?>">
                Returned — Awaiting 2nd Reading
                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600 <?= $tab === 'returned' ? 'bg-primary/10 text-primary' : '' ?>"><?= number_format($returnedCount) ?></span>
            </a>
        </nav>
    </div>

    <!-- Document List -->
    <section class="rounded-2xl border border-gray-200 bg-white">
        <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900">
                    <?= $tab === 'returned' ? 'Returned by Committee' : 'With Committee' ?>
                </h2>
                <p class="mt-1 text-xs text-gray-500"><?= $total ?> record<?= $total !== 1 ? 's' : '' ?> found</p>
            </div>
            <form method="GET" action="<?= BASE_URL ?>/plenary/referred-to-committee" class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search tracking # or subject…"
                           class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 lg:w-64">
                </div>
                <button type="submit" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Filter</button>
                <?php if ($search !== ''): ?>
                    <a href="<?= htmlspecialchars(rtcBuildTabUrl($tab, '')) ?>" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition text-center">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Tracking Number</th>
                        <th class="px-6 py-3 font-medium">Subject Matter / Type</th>
                        <th class="px-6 py-3 font-medium">Committee(s)</th>
                        <th class="px-6 py-3 font-medium">Referred On</th>
                        <th class="px-6 py-3 font-medium">Current Status</th>
                        <th class="px-6 py-3 font-medium text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($documents)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">
                                    <?php if ($tab === 'returned'): ?>
                                        No documents returned from Committee awaiting Second Reading.
                                    <?php else: ?>
                                        No documents currently referred to Committee.
                                    <?php endif; ?>
                                </p>
                                <p class="mt-1 text-xs text-gray-500">Documents referred during First Reading will appear here and remain tracked until advanced to Second Reading.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($documents as $row): ?>
                            <?php
                                $statusColor  = $row['status_badge_color'] ?? '#6B7280';
                                $typeColor    = $row['document_type_badge_color'] ?? '#2563EB';
                                $canAdvance   = !empty($row['can_advance']);
                            ?>
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($row['tracking_number']) ?></div>
                                    <div class="text-xs text-gray-500">
                                        <?php if (!empty($row['proposed_number'])): ?>
                                            <?= htmlspecialchars($row['proposed_number']) ?> ·
                                        <?php endif; ?>
                                        <?= htmlspecialchars(date('M j, Y', strtotime($row['date_received']))) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="max-w-xs">
                                        <p class="line-clamp-2 font-medium text-gray-900"><?= htmlspecialchars($row['subject_matter']) ?></p>
                                        <?php if (!empty($row['document_type_name'])): ?>
                                            <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                                  style="background-color: <?= htmlspecialchars($typeColor) ?>1a; color: <?= htmlspecialchars($typeColor) ?>;">
                                                <?= htmlspecialchars($row['document_type_name']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if (!empty($row['committee_names'])): ?>
                                        <div class="font-medium text-gray-800"><?= htmlspecialchars($row['committee_names']) ?></div>
                                    <?php else: ?>
                                        <span class="text-xs italic text-gray-400">—</span>
                                    <?php endif; ?>
                                    <?php if (!empty($row['accepted_by_username'])): ?>
                                        <div class="text-xs text-gray-400">Accepted by <?= htmlspecialchars($row['accepted_by_username']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <?php if (!empty($row['referred_date'])): ?>
                                        <?= htmlspecialchars(date('M j, Y', strtotime($row['referred_date']))) ?>
                                    <?php else: ?>
                                        <span class="text-xs italic text-gray-400">—</span>
                                    <?php endif; ?>
                                    <?php if ($tab === 'returned' && !empty($row['returned_at'])): ?>
                                        <div class="text-xs text-violet-600">Returned <?= htmlspecialchars(date('M j, Y', strtotime($row['returned_at']))) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if (!empty($row['status'])): ?>
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                              style="background-color: <?= htmlspecialchars($statusColor) ?>1a; color: <?= htmlspecialchars($statusColor) ?>;">
                                            <?= htmlspecialchars($row['status']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs italic text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <?php if ($canAdvance): ?>
                                        <?php if (!empty($row['is_plenary_comm_referred'])): ?>
                                            <!-- Communication returned from Committee: Adopt Committee Report -->
                                            <button type="button"
                                                    onclick="openAdoptModal(<?= (int) $row['document_id'] ?>, '<?= htmlspecialchars(addslashes($row['tracking_number'])) ?>')"
                                                    class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 transition">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                Adopted Committee Report
                                            </button>
                                        <?php else: ?>
                                            <!-- Legislative document: Proceed to 2nd Reading -->
                                            <form method="POST" action="<?= BASE_URL ?>/plenary/second-reading/advance-from-committee" class="inline-block">
                                                <input type="hidden" name="document_id" value="<?= (int) $row['document_id'] ?>">
                                                <button type="submit"
                                                        class="inline-flex items-center gap-1.5 rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-violet-700 transition">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                                    </svg>
                                                    Proceed to 2nd Reading
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-medium text-gray-400">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            In Committee
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="flex flex-col items-center justify-between gap-3 border-t border-gray-100 px-6 py-4 sm:flex-row">
                <p class="text-xs text-gray-500">Showing page <span class="font-medium"><?= $page ?></span> of <span class="font-medium"><?= $totalPages ?></span> (<?= $total ?> total)</p>
                <div class="flex items-center gap-1">
                    <?php if ($page > 1): ?><a href="<?= htmlspecialchars(rtcBuildPageUrl($tab, $page - 1, $search)) ?>" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Prev</a><?php endif; ?>
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="<?= htmlspecialchars(rtcBuildPageUrl($tab, $i, $search)) ?>" class="rounded-lg border px-3 py-1.5 text-sm font-medium transition <?= $i === $page ? 'border-primary bg-primary text-white' : 'border-gray-200 text-gray-700 hover:bg-gray-50' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?><a href="<?= htmlspecialchars(rtcBuildPageUrl($tab, $page + 1, $search)) ?>" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Next</a><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
</div>

<!-- ======================================================================
     Adopt Committee Report Modal placeholder — the actual HTML is injected
     at the body level via $pageModals (see bottom of this file) so it sits
     outside <main> and cannot be clipped or z-index-trapped by any ancestor
     stacking context.  This keeps it consistent with the Sign Out modal.
     ====================================================================== -->

<?php
$content = ob_get_clean();

// ── Adopt Committee Report Modal ──────────────────────────────────────────────
// Injected at body level (outside <main>) via $pageModals in app.php so it
// shares the same stacking context as the Sign Out modal (z-[100], full
// viewport coverage, not trapped by the sidebar or main column).
//
// Structure mirrors the Sign Out modal exactly:
//   container  fixed inset-0 z-[100] hidden  (class-toggled by JS)
//   backdrop   absolute inset-0 backdrop-blur-sm  (animated opacity)
//   panel      relative z-10  (animated scale + opacity)
// ---------------------------------------------------------------------------
ob_start();
?>
<div
    id="adopt-comm-modal"
    class="fixed inset-0 z-[100] hidden items-center justify-center"
    role="dialog"
    aria-modal="true"
    aria-labelledby="adopt-modal-title"
>
    <!-- Backdrop — separate element so it can be blurred without affecting the panel -->
    <div id="adopt-comm-backdrop"
         class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity opacity-0"></div>

    <!-- Panel -->
    <div id="adopt-comm-panel"
         class="relative z-10 w-full max-w-lg mx-4 bg-white rounded-2xl shadow-2xl border border-gray-100
                transform scale-95 opacity-0 transition-all duration-200">

        <!-- Header -->
        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
            <h2 id="adopt-modal-title" class="text-base font-semibold text-gray-900">
                Adopted Committee Report
            </h2>
            <button type="button" id="adopt-close-btn"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition"
                    aria-label="Close">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Body -->
        <form method="POST" action="<?= BASE_URL ?>/plenary/referred-to-committee/adopt-comm"
              enctype="multipart/form-data" class="px-6 py-5 space-y-4" id="adopt-comm-form">
            <input type="hidden" name="document_id" id="adopt-document-id" value="">

            <p class="text-sm text-gray-600">
                Finalizing committee report adoption for:
                <span id="adopt-tracking-number" class="font-semibold text-gray-900"></span>
            </p>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="adopt-resolution-number"
                           class="block text-sm font-medium text-gray-700 mb-1">
                        Approved Resolution Number <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="adopt-resolution-number" name="resolution_number"
                           maxlength="100" required
                           placeholder="e.g. SP Res. No. 2026-001"
                           class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                                  text-gray-800 placeholder-gray-400 focus:border-primary
                                  focus:outline-none focus:ring-2 focus:ring-primary/20">
                </div>
                <div>
                    <label for="adopt-proposed-number"
                           class="block text-sm font-medium text-gray-700 mb-1">
                        Proposed Number
                        <span class="text-xs font-normal text-gray-400">(optional)</span>
                    </label>
                    <input type="text" id="adopt-proposed-number" name="proposed_number"
                           maxlength="100"
                           placeholder="e.g. SP Res. No. 2026-001"
                           class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                                  text-gray-800 placeholder-gray-400 focus:border-primary
                                  focus:outline-none focus:ring-2 focus:ring-primary/20">
                </div>
            </div>

            <!-- ── Resolution Attachment — drag-and-drop upload area ── -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Resolution Attachment(s)
                    <span class="text-xs font-normal text-gray-400">(optional)</span>
                </label>

                <!-- Hidden real file input (accumulator) -->
                <input type="file" id="adopt-resolution-files" name="resolution_files[]"
                       multiple
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp"
                       class="sr-only"
                       aria-label="Resolution attachment files"
                       tabindex="-1">

                <!-- Drop zone -->
                <div id="adopt-drop-zone"
                     role="button"
                     tabindex="0"
                     aria-label="Drop resolution files here or click to browse"
                     class="relative flex flex-col items-center justify-center gap-2
                            rounded-xl border-2 border-dashed border-gray-300 bg-gray-50
                            px-6 py-6 text-center transition
                            hover:border-primary hover:bg-primary/5
                            focus:outline-none focus:ring-2 focus:ring-primary/40
                            cursor-pointer">

                    <svg class="h-7 w-7 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>

                    <p class="text-sm font-medium text-gray-700 pointer-events-none">
                        Drag &amp; drop files here, or
                        <span class="text-primary underline-offset-2 hover:underline">browse</span>
                    </p>
                    <p class="text-xs text-gray-400 pointer-events-none">
                        Up to 10 files · max 25 MB each · PDF, DOC, DOCX, XLS, XLSX, JPG, PNG
                    </p>

                    <div id="adopt-drag-overlay"
                         class="pointer-events-none absolute inset-0 hidden rounded-xl
                                border-2 border-primary bg-primary/10">
                    </div>
                </div>

                <ul id="adopt-file-list"
                    class="hidden mt-3 space-y-2"
                    aria-label="Selected resolution files"
                    aria-live="polite">
                </ul>

                <p id="adopt-file-error" class="hidden mt-1 text-xs text-red-600" role="alert"></p>

                <p class="mt-1 text-xs text-gray-400">
                    Supported: PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, PNG, GIF, WEBP
                </p>
            </div>

            <div>
                <label for="adopt-remarks" class="block text-sm font-medium text-gray-700 mb-1">
                    Remarks <span class="text-xs font-normal text-gray-400">(optional)</span>
                </label>
                <textarea id="adopt-remarks" name="remarks" rows="2" maxlength="2000"
                          class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm
                                 text-gray-800 placeholder-gray-400 focus:border-primary
                                 focus:outline-none focus:ring-2 focus:ring-primary/20"
                          placeholder="Optional remarks…"></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                <button type="button" id="adopt-cancel-btn"
                        class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium
                               text-gray-700 hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2 text-sm
                               font-semibold text-white hover:bg-emerald-700 transition
                               focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Finalize Communication
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    'use strict';

    // ── Modal open / close ────────────────────────────────────────────────────
    // Mirrors the Sign Out modal pattern exactly: separate backdrop + panel
    // elements driven by class toggling with a 200 ms CSS transition.

    var modal    = document.getElementById('adopt-comm-modal');
    var backdrop = document.getElementById('adopt-comm-backdrop');
    var panel    = document.getElementById('adopt-comm-panel');
    var adoptOpen = false;

    window.openAdoptModal = function (documentId, trackingNumber) {
        if (!modal || adoptOpen) return;

        document.getElementById('adopt-document-id').value           = documentId;
        document.getElementById('adopt-tracking-number').textContent = trackingNumber;
        document.getElementById('adopt-resolution-number').value     = '';
        document.getElementById('adopt-proposed-number').value       = '';
        document.getElementById('adopt-remarks').value               = '';
        adoptResetFiles();

        adoptOpen = true;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        requestAnimationFrame(function () {
            backdrop.classList.remove('opacity-0');
            panel.classList.remove('scale-95', 'opacity-0');
            panel.classList.add('scale-100', 'opacity-100');
        });
        document.getElementById('adopt-resolution-number').focus();
        document.addEventListener('keydown', handleAdoptEsc);
    };

    window.closeAdoptModal = function () {
        if (!modal || !adoptOpen) return;
        adoptOpen = false;
        backdrop.classList.add('opacity-0');
        panel.classList.remove('scale-100', 'opacity-100');
        panel.classList.add('scale-95', 'opacity-0');
        setTimeout(function () {
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }, 200);
        document.removeEventListener('keydown', handleAdoptEsc);
    };

    function handleAdoptEsc(e) {
        if (e.key === 'Escape') closeAdoptModal();
    }

    document.getElementById('adopt-comm-backdrop').addEventListener('click', closeAdoptModal);
    document.getElementById('adopt-close-btn').addEventListener('click', closeAdoptModal);
    document.getElementById('adopt-cancel-btn').addEventListener('click', closeAdoptModal);

    // ── Drag-and-drop / multi-file upload ─────────────────────────────────────

    var MAX_FILES    = 10;
    var MAX_SIZE_MB  = 25;
    var MAX_SIZE     = MAX_SIZE_MB * 1024 * 1024;
    var ALLOWED_EXTS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'webp'];

    var fileInput   = document.getElementById('adopt-resolution-files');
    var dropZone    = document.getElementById('adopt-drop-zone');
    var fileList    = document.getElementById('adopt-file-list');
    var fileError   = document.getElementById('adopt-file-error');
    var dragOverlay = document.getElementById('adopt-drag-overlay');

    // DataTransfer accumulates files across multiple browse/drop sessions.
    var dt = new DataTransfer();

    function extOf(name) {
        return (name.split('.').pop() || '').toLowerCase();
    }

    function fmtSize(bytes) {
        if (bytes >= 1024 * 1024) { return (bytes / (1024 * 1024)).toFixed(1) + ' MB'; }
        return Math.round(bytes / 1024) + ' KB';
    }

    function showFileError(msg) {
        fileError.textContent = msg;
        fileError.classList.remove('hidden');
    }

    function clearFileError() {
        fileError.textContent = '';
        fileError.classList.add('hidden');
    }

    function renderFileList() {
        fileList.innerHTML = '';
        if (dt.files.length === 0) {
            fileList.classList.add('hidden');
            return;
        }
        fileList.classList.remove('hidden');

        Array.from(dt.files).forEach(function (file, idx) {
            var li = document.createElement('li');
            li.className = 'flex items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm';
            li.dataset.fileIdx = idx;

            var left = document.createElement('div');
            left.className = 'flex items-center gap-2 min-w-0';

            var icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            icon.setAttribute('class', 'h-4 w-4 shrink-0 text-gray-400');
            icon.setAttribute('fill', 'none');
            icon.setAttribute('stroke', 'currentColor');
            icon.setAttribute('viewBox', '0 0 24 24');
            icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>';

            var nameSpan = document.createElement('span');
            nameSpan.className = 'truncate text-gray-800';
            nameSpan.textContent = file.name;

            var sizeSpan = document.createElement('span');
            sizeSpan.className = 'shrink-0 text-xs text-gray-400 ml-1';
            sizeSpan.textContent = fmtSize(file.size);

            left.appendChild(icon);
            left.appendChild(nameSpan);
            left.appendChild(sizeSpan);

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'shrink-0 rounded-lg p-1 text-gray-400 hover:bg-red-50 hover:text-red-500 transition';
            btn.setAttribute('aria-label', 'Remove ' + file.name);
            btn.innerHTML = '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';
            btn.addEventListener('click', function () { removeAdoptFile(idx); });

            li.appendChild(left);
            li.appendChild(btn);
            fileList.appendChild(li);
        });
    }

    function removeAdoptFile(idx) {
        var updated = new DataTransfer();
        Array.from(dt.files).forEach(function (f, i) {
            if (i !== idx) { updated.items.add(f); }
        });
        dt = updated;
        fileInput.files = dt.files;
        clearFileError();
        renderFileList();
    }

    function addAdoptFiles(incoming) {
        clearFileError();
        var candidates = Array.from(incoming);
        var existing   = Array.from(dt.files);
        var existingKeys = new Set(existing.map(function (f) { return f.name + ':' + f.size; }));

        var rejected = [];
        var accepted = [];

        candidates.forEach(function (file) {
            var ext = extOf(file.name);
            var key = file.name + ':' + file.size;

            if (existingKeys.has(key)) { return; }

            if (!ALLOWED_EXTS.includes(ext)) {
                rejected.push('"' + file.name + '" — type not allowed');
                return;
            }
            if (file.size > MAX_SIZE) {
                rejected.push('"' + file.name + '" — exceeds ' + MAX_SIZE_MB + ' MB');
                return;
            }
            if (existing.length + accepted.length >= MAX_FILES) {
                rejected.push('"' + file.name + '" — maximum ' + MAX_FILES + ' files reached');
                return;
            }
            accepted.push(file);
        });

        accepted.forEach(function (f) {
            dt.items.add(f);
            existing.push(f);
        });

        fileInput.files = dt.files;

        if (rejected.length > 0) {
            showFileError('Some files were not added: ' + rejected.join('; ') + '.');
        }

        renderFileList();
    }

    function adoptResetFiles() {
        dt = new DataTransfer();
        fileInput.files = dt.files;
        renderFileList();
        clearFileError();
    }

    // Click to browse
    dropZone.addEventListener('click', function () {
        fileInput.click();
    });
    dropZone.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); }
    });

    // Native input change (browsed files)
    fileInput.addEventListener('change', function () {
        if (fileInput.files.length > 0) {
            addAdoptFiles(fileInput.files);
            fileInput.value = '';
        }
    });

    // Drag events
    dropZone.addEventListener('dragover', function (e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
        dropZone.classList.add('border-primary', 'bg-primary/5');
        dragOverlay.classList.remove('hidden');
    });
    dropZone.addEventListener('dragleave', function (e) {
        if (!dropZone.contains(e.relatedTarget)) {
            dropZone.classList.remove('border-primary', 'bg-primary/5');
            dragOverlay.classList.add('hidden');
        }
    });
    dropZone.addEventListener('drop', function (e) {
        e.preventDefault();
        dropZone.classList.remove('border-primary', 'bg-primary/5');
        dragOverlay.classList.add('hidden');
        if (e.dataTransfer.files.length > 0) {
            addAdoptFiles(e.dataTransfer.files);
        }
    });

}());
</script>
<?php
$pageModals = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

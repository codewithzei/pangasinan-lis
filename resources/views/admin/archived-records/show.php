<?php
/**
 * Archived Records — Detail page
 *
 * Variables from ArchivedRecordsController::show():
 *   $record        array   The archived record row (with type/creator names)
 *   $authors       array   Rows from archived_records_authors + sp_members join
 *   $committees    array   Rows from archived_records_committees + committees join
 *   $attachments   array   Rows from archived_records_attachments
 *   $documentTypes array   For edit modal dropdown
 *   $spMembers     array   For edit modal dropdown
 *   $allCommittees array   For edit modal dropdown
 *   $pageTitle     string
 */

$record        = $record        ?? [];
$authors       = $authors       ?? [];
$committees    = $committees    ?? [];
$attachments   = $attachments   ?? [];
$documentTypes = $documentTypes ?? [];
$spMembers     = $spMembers     ?? [];
$allCommittees = $allCommittees ?? [];
$editAuthors = $authors ?: [
    ['author_role' => 'AUTHOR', 'sp_member_id' => null, 'custom_author_name' => null],
    ['author_role' => 'CO_AUTHOR', 'sp_member_id' => null, 'custom_author_name' => null],
];
$editCommittees = $committees ?: [
    ['committee_id' => null, 'custom_committee_name' => null],
];

$badgeColor = $record['document_type_badge_color'] ?? '#2563EB';

$tagList = [];
if (!empty($record['tags'])) {
    $tagList = array_filter(array_map('trim', explode(',', $record['tags'])));
}

// Helper: human-readable file size
function arFormatFileSize(int $bytes): string {
    if ($bytes >= 1_048_576) return number_format($bytes / 1_048_576, 2) . ' MB';
    if ($bytes >= 1_024)     return number_format($bytes / 1_024, 2) . ' KB';
    return $bytes . ' bytes';
}

// Helper: icon class by mime type
function arFileIcon(string $mime): string {
    if (str_contains($mime, 'pdf'))   return 'text-red-500';
    if (str_contains($mime, 'word'))  return 'text-blue-500';
    if (str_contains($mime, 'excel') || str_contains($mime, 'spreadsheet')) return 'text-emerald-500';
    if (str_starts_with($mime, 'image/')) return 'text-purple-500';
    return 'text-gray-500';
}

?>

<div class="space-y-6">

    <!-- ── Page header ─────────────────────────────────────────────────────── -->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex items-start gap-4">
                        <a href="<?= BASE_URL ?>/admin/archived-records"
                           class="mt-1 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10
                                  text-white hover:bg-white/25 transition"
                           title="Back to list">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </a>
                        <div>
                            <p class="text-sm font-medium text-blue-100">ADMIN / ARCHIVED RECORDS</p>
                            <h1 class="mt-1 text-xl font-bold tracking-tight text-white sm:text-2xl">
                                <?= htmlspecialchars($record['approved_no']) ?>
                            </h1>
                            <p class="mt-1.5 max-w-2xl text-sm leading-relaxed text-blue-100 line-clamp-2">
                                <?= htmlspecialchars($record['subject_matter']) ?>
                            </p>
                        </div>
                    </div>
                    <div class="ml-14 flex shrink-0 items-center gap-2 sm:ml-0">
                        <button type="button" onclick="openEditModal()"
                                class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-4 py-2 text-sm
                                       font-semibold text-white hover:bg-white/25 transition ring-1 ring-white/30">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Edit
                        </button>
                    </div>
                </div>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
        </div>
    </section>

    <!-- ── Two-column layout ───────────────────────────────────────────────── -->
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

        <!-- ── Main column (2/3) ───────────────────────────────────────────── -->
        <div class="xl:col-span-2 space-y-6">

            <!-- Record Information -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-base font-semibold text-gray-900">Record Information</h2>
                <div class="mt-5 grid grid-cols-1 gap-x-8 gap-y-5 sm:grid-cols-2">

                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Approved Number</dt>
                        <dd class="mt-1.5 text-sm font-semibold text-gray-900">
                            <?= htmlspecialchars($record['approved_no']) ?>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Date Approved</dt>
                        <dd class="mt-1.5 text-sm text-gray-800">
                            <?= htmlspecialchars(date('F j, Y', strtotime($record['date_approved']))) ?>
                        </dd>
                    </div>

                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Subject Matter</dt>
                        <dd class="mt-1.5 text-sm text-gray-800 leading-relaxed">
                            <?= nl2br(htmlspecialchars($record['subject_matter'])) ?>
                        </dd>
                    </div>

                    <?php if (!empty($record['description'])): ?>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Description</dt>
                        <dd class="mt-1.5 text-sm text-gray-700 leading-relaxed">
                            <?= nl2br(htmlspecialchars($record['description'])) ?>
                        </dd>
                    </div>
                    <?php endif; ?>

                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Document Type</dt>
                        <dd class="mt-1.5">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                                  style="background-color: <?= htmlspecialchars($badgeColor) ?>18; color: <?= htmlspecialchars($badgeColor) ?>;">
                                <?= htmlspecialchars($record['document_type_name'] ?? '—') ?>
                            </span>
                        </dd>
                    </div>

                </div>
            </section>

            <!-- Tags -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-base font-semibold text-gray-900">Tags</h2>
                <?php if (!empty($tagList)): ?>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <?php foreach ($tagList as $tag): ?>
                            <a href="<?= BASE_URL ?>/admin/archived-records?search=<?= urlencode($tag) ?>"
                               class="inline-flex items-center rounded-lg bg-gray-100 px-3 py-1.5 text-sm
                                      font-medium text-gray-700 hover:bg-primary/10 hover:text-primary transition">
                                <svg class="mr-1.5 h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                                </svg>
                                <?= htmlspecialchars($tag) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="mt-4 flex items-center gap-3 rounded-xl border border-dashed border-gray-200 bg-gray-50/50 p-4">
                        <svg class="h-5 w-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                        </svg>
                        <p class="text-sm text-gray-400 italic">No tags have been added to this record.</p>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Authors -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-base font-semibold text-gray-900">Authors / Sponsors &amp; Co-authors / Co-sponsors</h2>
                <?php if (!empty($authors)): ?>
                    <?php
                    $mainAuthors = array_filter($authors, fn($a) => $a['author_role'] === 'AUTHOR');
                    $coAuthors   = array_filter($authors, fn($a) => $a['author_role'] === 'CO_AUTHOR');
                    ?>
                    <div class="mt-5 space-y-5">
                        <?php if (!empty($mainAuthors)): ?>
                        <div>
                            <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">Authors / Sponsors</p>
                            <div class="space-y-2">
                                <?php foreach ($mainAuthors as $author): ?>
                                    <?php
                                    $name = $author['member_full_name'] ?: $author['custom_author_name'];
                                    $pos  = $author['member_position'] ?? null;
                                    $isCustom = !$author['sp_member_id'];
                                    ?>
                                    <div class="flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50 px-4 py-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full
                                                    bg-primary/10 text-sm font-bold text-primary">
                                            <?= strtoupper(mb_substr($name, 0, 1)) ?>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-gray-900">
                                                <?= htmlspecialchars($name) ?>
                                            </p>
                                            <?php if ($pos): ?>
                                                <p class="text-xs text-gray-500"><?= htmlspecialchars($pos) ?></p>
                                            <?php elseif ($isCustom): ?>
                                                <p class="text-xs text-gray-400 italic">Custom author</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($coAuthors)): ?>
                        <div>
                            <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">Co-authors / Co-sponsors</p>
                            <div class="space-y-2">
                                <?php foreach ($coAuthors as $author): ?>
                                    <?php
                                    $name = $author['member_full_name'] ?: $author['custom_author_name'];
                                    $pos  = $author['member_position'] ?? null;
                                    $isCustom = !$author['sp_member_id'];
                                    ?>
                                    <div class="flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50 px-4 py-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full
                                                    bg-indigo-50 text-sm font-bold text-indigo-500">
                                            <?= strtoupper(mb_substr($name, 0, 1)) ?>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-gray-900">
                                                <?= htmlspecialchars($name) ?>
                                            </p>
                                            <?php if ($pos): ?>
                                                <p class="text-xs text-gray-500"><?= htmlspecialchars($pos) ?></p>
                                            <?php elseif ($isCustom): ?>
                                                <p class="text-xs text-gray-400 italic">Custom co-author</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="mt-4 flex items-center gap-3 rounded-xl border border-dashed border-gray-200 bg-gray-50/50 p-4">
                        <svg class="h-5 w-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <p class="text-sm text-gray-400 italic">No authors have been assigned to this record.</p>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Committees -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h2 class="text-base font-semibold text-gray-900">Committees</h2>
                <?php if (!empty($committees)): ?>
                    <div class="mt-4 space-y-2">
                        <?php foreach ($committees as $comm): ?>
                            <?php $commName = $comm['committee_name'] ?: $comm['custom_committee_name']; ?>
                            <div class="flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50 px-4 py-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl
                                            bg-amber-50 text-amber-600">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-gray-900">
                                        <?= htmlspecialchars($commName) ?>
                                    </p>
                                    <?php if (!$comm['committee_id']): ?>
                                        <p class="text-xs text-gray-400 italic">Custom committee</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="mt-4 flex items-center gap-3 rounded-xl border border-dashed border-gray-200 bg-gray-50/50 p-4">
                        <svg class="h-5 w-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        <p class="text-sm text-gray-400 italic">No committees have been assigned to this record.</p>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Attachments -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-900">
                        Attachments
                        <?php if (!empty($attachments)): ?>
                            <span class="ml-2 inline-flex h-5 min-w-[20px] items-center justify-center rounded-full
                                         bg-gray-100 px-1.5 text-xs font-semibold text-gray-600">
                                <?= count($attachments) ?>
                            </span>
                        <?php endif; ?>
                    </h2>
                    <button type="button" onclick="openUploadModal()"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5
                                   text-xs font-medium text-gray-700 hover:border-primary hover:bg-blue-50
                                   hover:text-primary transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        Upload Files
                    </button>
                </div>

                <?php if (!empty($attachments)): ?>
                    <div class="mt-4 space-y-2">
                        <?php foreach ($attachments as $att): ?>
                            <?php $iconClass = arFileIcon($att['mime_type']); ?>
                            <div class="flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50 p-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white border border-gray-200 <?= $iconClass ?>">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-900"
                                       title="<?= htmlspecialchars($att['file_name']) ?>">
                                        <?= htmlspecialchars($att['file_name']) ?>
                                    </p>
                                    <p class="text-xs text-gray-400">
                                        <?= arFormatFileSize((int) $att['file_size']) ?>
                                        &bull;
                                        <?= htmlspecialchars(date('M j, Y', strtotime($att['uploaded_at']))) ?>
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-1.5">
                                    <a href="<?= BASE_URL ?>/storage/<?= htmlspecialchars($att['file_path']) ?>"
                                       target="_blank" rel="noopener"
                                       class="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs
                                              font-medium text-gray-700 hover:bg-gray-50 transition"
                                       title="Download">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                    </a>
                                    <button type="button"
                                            onclick="openDeleteAttModal(<?= (int) $att['id'] ?>, '<?= addslashes(htmlspecialchars($att['file_name'])) ?>')"
                                            class="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs
                                                   font-medium text-red-400 hover:border-red-200 hover:bg-red-50
                                                   hover:text-red-600 transition"
                                            title="Delete attachment">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="mt-4 flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed
                                border-gray-200 bg-gray-50/50 py-10">
                        <svg class="h-10 w-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <p class="text-sm font-medium text-gray-500">No attachments yet</p>
                        <p class="text-xs text-gray-400">Click "Upload Files" to add documents</p>
                    </div>
                <?php endif; ?>
            </section>

        </div>

        <!-- ── Sidebar column (1/3) ────────────────────────────────────────── -->
        <div class="space-y-6">

            <!-- Quick info card -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <h3 class="text-sm font-semibold text-gray-900">Record Details</h3>
                <dl class="mt-4 space-y-4">
                    <div>
                        <dt class="text-xs font-medium text-gray-400">Record ID</dt>
                        <dd class="mt-1 text-sm font-mono text-gray-700">#<?= (int) $record['id'] ?></dd>
                    </div>
                    <div class="border-t border-gray-100 pt-4">
                        <dt class="text-xs font-medium text-gray-400">Created By</dt>
                        <dd class="mt-1 text-sm text-gray-700">
                            <?= htmlspecialchars($record['created_by_name'] ?? '—') ?>
                        </dd>
                    </div>
                    <div class="border-t border-gray-100 pt-4">
                        <dt class="text-xs font-medium text-gray-400">Created At</dt>
                        <dd class="mt-1 text-sm text-gray-700">
                            <?= htmlspecialchars(date('M j, Y g:i A', strtotime($record['created_at']))) ?>
                        </dd>
                    </div>
                    <?php if ($record['updated_at'] !== $record['created_at']): ?>
                    <div class="border-t border-gray-100 pt-4">
                        <dt class="text-xs font-medium text-gray-400">Last Updated By</dt>
                        <dd class="mt-1 text-sm text-gray-700">
                            <?= htmlspecialchars($record['updated_by_name'] ?? '—') ?>
                        </dd>
                    </div>
                    <div class="border-t border-gray-100 pt-4">
                        <dt class="text-xs font-medium text-gray-400">Last Updated</dt>
                        <dd class="mt-1 text-sm text-gray-700">
                            <?= htmlspecialchars(date('M j, Y g:i A', strtotime($record['updated_at']))) ?>
                        </dd>
                    </div>
                    <?php endif; ?>
                </dl>
            </section>

            <!-- Danger zone -->
            <section class="rounded-2xl border border-red-100 bg-red-50/50 p-5">
                <h3 class="text-sm font-semibold text-red-700">Danger Zone</h3>
                <p class="mt-1.5 text-xs text-red-600 leading-relaxed">
                    Deleting this record is permanent and cannot be undone. All associated authors, committees,
                    and attachments will be removed.
                </p>
                <button type="button"
                        onclick="openDeleteRecordModal()"
                        class="mt-4 inline-flex items-center gap-2 rounded-xl border border-red-200 bg-white px-4 py-2
                               text-sm font-medium text-red-600 hover:bg-red-600 hover:text-white transition w-full justify-center">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Delete Record
                </button>
            </section>

        </div>
    </div>

</div>


<!-- ═══════════════════════════════════════════════════════════════════════════
     EDIT MODAL
     ═══════════════════════════════════════════════════════════════════════════ -->
<div id="editModal"
     class="fixed inset-0 z-[100] hidden items-center justify-center"
     role="dialog" aria-modal="true" aria-labelledby="editModalTitle">
    <div id="editModalBackdrop"
         class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm opacity-0 transition-opacity duration-200"></div>
    <div id="editModalPanel"
         class="relative z-10 mx-4 w-full max-w-4xl max-h-[92vh] overflow-y-auto rounded-2xl bg-white shadow-2xl
                border border-gray-100 transform scale-95 opacity-0 transition-all duration-200">

        <form method="POST" action="<?= BASE_URL ?>/admin/archived-records/update" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">

            <!-- Header -->
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5">
                <div>
                    <h3 id="editModalTitle" class="text-lg font-semibold text-gray-900">Edit Archived Record</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Update the details for this record</p>
                </div>
                <button type="button" onclick="closeEditModal()"
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
                        <label for="edit_approved_no" class="block text-xs font-semibold text-gray-700 mb-1.5">
                            Approved Number <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="edit_approved_no" name="approved_no"
                               value="<?= htmlspecialchars($record['approved_no']) ?>"
                               class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm text-gray-800
                                      focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                               required>
                    </div>
                    <div>
                        <label for="edit_date_approved" class="block text-xs font-semibold text-gray-700 mb-1.5">
                            Date Approved <span class="text-red-500">*</span>
                        </label>
                        <input type="date" id="edit_date_approved" name="date_approved"
                               value="<?= htmlspecialchars($record['date_approved']) ?>"
                               class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm text-gray-800
                                      focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                               required>
                    </div>
                </div>

                <!-- Subject Matter -->
                <div>
                    <label for="edit_subject_matter" class="block text-xs font-semibold text-gray-700 mb-1.5">
                        Subject Matter <span class="text-red-500">*</span>
                    </label>
                    <textarea id="edit_subject_matter" name="subject_matter" rows="3"
                              class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm text-gray-800
                                     focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 resize-y"
                              required><?= htmlspecialchars($record['subject_matter']) ?></textarea>
                </div>

                <!-- Document Type -->
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">
                        Document Type <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <select name="document_type_id" id="edit_doc_type_id"
                                    onchange="handleDocTypeChange('edit')"
                                    class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm text-gray-800
                                           focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                                <option value="">— Select type —</option>
                                <?php foreach ($documentTypes as $dt): ?>
                                    <option value="<?= (int) $dt['id'] ?>"
                                        <?= (string) ($record['document_type_id'] ?? '') === (string) $dt['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dt['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="mt-1 text-xs text-gray-400">Select from list</p>
                        </div>
                        <div>
                            <input type="text" name="custom_document_type_name" id="edit_custom_type"
                                   value="<?= htmlspecialchars($record['custom_document_type_name'] ?? '') ?>"
                                   placeholder="Or custom type name"
                                   onchange="handleCustomTypeChange('edit')"
                                   class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm text-gray-800
                                          placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                            <p class="mt-1 text-xs text-gray-400">Or type custom name</p>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label for="edit_description" class="block text-xs font-semibold text-gray-700 mb-1.5">Description</label>
                    <textarea id="edit_description" name="description" rows="2"
                              class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm text-gray-800
                                     placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 resize-y"><?= htmlspecialchars($record['description'] ?? '') ?></textarea>
                </div>

                <!-- Tags -->
                <div>
                    <label for="edit_tags" class="block text-xs font-semibold text-gray-700 mb-1.5">Tags</label>
                    <input type="text" id="edit_tags" name="tags"
                           value="<?= htmlspecialchars($record['tags'] ?? '') ?>"
                           placeholder="budget, health, 2024"
                           class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm text-gray-800
                                  placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </div>

                <!-- Existing and custom authors/co-authors -->
                <section class="rounded-xl border border-gray-200 p-4 sm:p-5">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900">Authors / Sponsors</h4>
                            <p class="mt-1 text-xs text-gray-500">Saved people are prefilled. Choose a master-list person or enter a custom name; the same person cannot fill both roles.</p>
                        </div>
                        <button type="button" onclick="addArchiveEntry('editAuthorsList', 'editAuthorEntryTemplate')"
                                class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-primary hover:bg-blue-50">
                            + Add person
                        </button>
                    </div>
                    <div id="editAuthorsList" class="space-y-3">
                        <?php foreach ($editAuthors as $author): ?>
                        <div class="person-entry grid grid-cols-1 gap-3 rounded-lg bg-gray-50 p-3 md:grid-cols-[minmax(150px,0.8fr)_minmax(200px,1.2fr)_minmax(180px,1fr)_auto] md:items-center">
                            <select name="author_role[]" aria-label="Person role" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
                                <option value="AUTHOR" <?= $author['author_role'] === 'AUTHOR' ? 'selected' : '' ?>>Author / Sponsor</option>
                                <option value="CO_AUTHOR" <?= $author['author_role'] === 'CO_AUTHOR' ? 'selected' : '' ?>>Co-author / Co-sponsor</option>
                            </select>
                            <select name="author_sp_member_id[]" aria-label="Select existing person" class="entry-master rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
                                <option value="">Select existing person…</option>
                                <?php foreach ($spMembers as $member): ?>
                                    <option value="<?= (int) $member['sp_member_id'] ?>" <?= (string) ($author['sp_member_id'] ?? '') === (string) $member['sp_member_id'] ? 'selected' : '' ?>><?= htmlspecialchars($member['full_name']) ?><?= !empty($member['position']) ? ' — ' . htmlspecialchars($member['position']) : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="author_custom_name[]" aria-label="Custom person name" maxlength="200" placeholder="Or enter custom name"
                                   value="<?= htmlspecialchars($author['custom_author_name'] ?? '') ?>"
                                   class="entry-custom rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
                            <button type="button" onclick="removeArchiveEntry(this)" aria-label="Remove person" class="rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50">Remove</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- Existing and custom committees -->
                <section class="rounded-xl border border-gray-200 p-4 sm:p-5">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900">Committees</h4>
                            <p class="mt-1 text-xs text-gray-500">Saved committee assignments are prefilled. Existing or custom names are supported.</p>
                        </div>
                        <button type="button" onclick="addArchiveEntry('editCommitteesList', 'editCommitteeEntryTemplate')"
                                class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-primary hover:bg-blue-50">
                            + Add committee
                        </button>
                    </div>
                    <div id="editCommitteesList" class="space-y-3">
                        <?php foreach ($editCommittees as $committee): ?>
                        <div class="committee-entry grid grid-cols-1 gap-3 rounded-lg bg-gray-50 p-3 sm:grid-cols-[1fr_1fr_auto] sm:items-center">
                            <select name="committee_id[]" aria-label="Select existing committee" class="entry-master rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
                                <option value="">Select existing committee…</option>
                                <?php foreach ($allCommittees as $option): ?>
                                    <option value="<?= (int) $option['id'] ?>" <?= (string) ($committee['committee_id'] ?? '') === (string) $option['id'] ? 'selected' : '' ?>><?= htmlspecialchars($option['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="committee_custom_name[]" aria-label="Custom committee name" maxlength="200" placeholder="Or enter custom committee"
                                   value="<?= htmlspecialchars($committee['custom_committee_name'] ?? '') ?>"
                                   class="entry-custom rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
                            <button type="button" onclick="removeArchiveEntry(this)" aria-label="Remove committee" class="rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50">Remove</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- Additional attachments -->
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Add More Attachments</label>
                    <input type="file" name="attachments[]" multiple
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp"
                           class="w-full rounded-xl border border-dashed border-gray-300 bg-gray-50 px-3 py-2.5 text-sm
                                  text-gray-700 file:mr-4 file:rounded-lg file:border-0 file:bg-primary file:px-3
                                  file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-primary/90">
                    <p class="mt-1 text-xs text-gray-400">Existing attachments are preserved. Max 25 MB / file, 10 files.</p>
                </div>

            </div>

            <!-- Footer -->
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4 rounded-b-2xl">
                <button type="button" onclick="closeEditModal()"
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
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<template id="editAuthorEntryTemplate">
    <div class="person-entry grid grid-cols-1 gap-3 rounded-lg bg-gray-50 p-3 md:grid-cols-[minmax(150px,0.8fr)_minmax(200px,1.2fr)_minmax(180px,1fr)_auto] md:items-center">
        <select name="author_role[]" aria-label="Person role" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm"><option value="AUTHOR">Author / Sponsor</option><option value="CO_AUTHOR">Co-author / Co-sponsor</option></select>
        <select name="author_sp_member_id[]" aria-label="Select existing person" class="entry-master rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
            <option value="">Select existing person…</option>
            <?php foreach ($spMembers as $member): ?><option value="<?= (int) $member['sp_member_id'] ?>"><?= htmlspecialchars($member['full_name']) ?><?= !empty($member['position']) ? ' — ' . htmlspecialchars($member['position']) : '' ?></option><?php endforeach; ?>
        </select>
        <input type="text" name="author_custom_name[]" aria-label="Custom person name" maxlength="200" placeholder="Or enter custom name" class="entry-custom rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
        <button type="button" onclick="removeArchiveEntry(this)" aria-label="Remove person" class="rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50">Remove</button>
    </div>
</template>
<template id="editCommitteeEntryTemplate">
    <div class="committee-entry grid grid-cols-1 gap-3 rounded-lg bg-gray-50 p-3 sm:grid-cols-[1fr_1fr_auto] sm:items-center">
        <select name="committee_id[]" aria-label="Select existing committee" class="entry-master rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
            <option value="">Select existing committee…</option>
            <?php foreach ($allCommittees as $option): ?><option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['name']) ?></option><?php endforeach; ?>
        </select>
        <input type="text" name="committee_custom_name[]" aria-label="Custom committee name" maxlength="200" placeholder="Or enter custom committee" class="entry-custom rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
        <button type="button" onclick="removeArchiveEntry(this)" aria-label="Remove committee" class="rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50">Remove</button>
    </div>
</template>


<!-- ═══════════════════════════════════════════════════════════════════════════
     UPLOAD ATTACHMENT MODAL
     ═══════════════════════════════════════════════════════════════════════════ -->
<div id="uploadModal"
     class="fixed inset-0 z-[100] hidden items-center justify-center"
     role="dialog" aria-modal="true">
    <div id="uploadModalBackdrop"
         class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm opacity-0 transition-opacity duration-200"></div>
    <div id="uploadModalPanel"
         class="relative z-10 mx-4 w-full max-w-md rounded-2xl bg-white shadow-2xl border border-gray-100
                transform scale-95 opacity-0 transition-all duration-200">
        <form method="POST" action="<?= BASE_URL ?>/admin/archived-records/upload" enctype="multipart/form-data">
            <input type="hidden" name="record_id" value="<?= (int) $record['id'] ?>">

            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5">
                <h3 class="text-lg font-semibold text-gray-900">Upload Attachments</h3>
                <button type="button" onclick="closeUploadModal()"
                        class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="px-6 py-5 space-y-4">
                <div>
                    <input type="file" name="attachments[]" multiple required
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp"
                           class="w-full rounded-xl border border-dashed border-gray-300 bg-gray-50 px-3 py-3 text-sm
                                  text-gray-700 file:mr-4 file:rounded-lg file:border-0 file:bg-primary file:px-3
                                  file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-primary/90">
                    <p class="mt-2 text-xs text-gray-400">PDF, Word, Excel, images. Max 25 MB per file, up to 10 files.</p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4 rounded-b-2xl">
                <button type="button" onclick="closeUploadModal()"
                        class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium
                               text-gray-700 hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2 text-sm font-semibold
                               text-white hover:bg-primary/90 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Upload
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════════════════════════
     DELETE ATTACHMENT MODAL
     ═══════════════════════════════════════════════════════════════════════════ -->
<div id="deleteAttModal"
     class="fixed inset-0 z-[100] hidden items-center justify-center"
     role="dialog" aria-modal="true">
    <div id="deleteAttModalBackdrop"
         class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm opacity-0 transition-opacity duration-200"></div>
    <div id="deleteAttModalPanel"
         class="relative z-10 mx-4 w-full max-w-sm rounded-2xl bg-white shadow-2xl border border-gray-100
                transform scale-95 opacity-0 transition-all duration-200">
        <form method="POST" action="<?= BASE_URL ?>/admin/archived-records/attachment/delete">
            <input type="hidden" name="record_id" value="<?= (int) $record['id'] ?>">
            <input type="hidden" name="attachment_id" id="deleteAttId">
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-red-100">
                        <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Remove Attachment</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            Remove <strong id="deleteAttName" class="text-gray-900 break-all"></strong> from this record?
                            The file will be permanently deleted.
                        </p>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4 rounded-b-2xl">
                <button type="button" onclick="closeDeleteAttModal()"
                        class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium
                               text-gray-700 hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit"
                        class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 transition">
                    Remove
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════════════════════════
     DELETE RECORD MODAL (from sidebar danger zone)
     ═══════════════════════════════════════════════════════════════════════════ -->
<div id="deleteRecordModal"
     class="fixed inset-0 z-[100] hidden items-center justify-center"
     role="dialog" aria-modal="true">
    <div id="deleteRecordModalBackdrop"
         class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm opacity-0 transition-opacity duration-200"></div>
    <div id="deleteRecordModalPanel"
         class="relative z-10 mx-4 w-full max-w-sm rounded-2xl bg-white shadow-2xl border border-gray-100
                transform scale-95 opacity-0 transition-all duration-200">
        <form method="POST" action="<?= BASE_URL ?>/admin/archived-records/destroy">
            <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-red-100">
                        <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Delete This Record</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            Permanently delete <strong class="text-gray-900"><?= htmlspecialchars($record['approved_no']) ?></strong>?
                            All authors, committees, and attachments will be removed.
                        </p>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4 rounded-b-2xl">
                <button type="button" onclick="closeDeleteRecordModal()"
                        class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium
                               text-gray-700 hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit"
                        class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 transition">
                    Delete Permanently
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ── Generic modal helpers ─────────────────────────────────────────────────────
function openModal(id) {
    const modal    = document.getElementById(id);
    const backdrop = document.getElementById(id + 'Backdrop');
    const panel    = document.getElementById(id + 'Panel');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    requestAnimationFrame(() => {
        backdrop.classList.remove('opacity-0');
        panel.classList.remove('scale-95', 'opacity-0');
        panel.classList.add('scale-100', 'opacity-100');
    });
    const esc = (e) => { if (e.key === 'Escape') { closeModal(id); document.removeEventListener('keydown', esc); } };
    document.addEventListener('keydown', esc);
}

function closeModal(id) {
    const modal    = document.getElementById(id);
    const backdrop = document.getElementById(id + 'Backdrop');
    const panel    = document.getElementById(id + 'Panel');
    backdrop.classList.add('opacity-0');
    panel.classList.add('scale-95', 'opacity-0');
    panel.classList.remove('scale-100', 'opacity-100');
    setTimeout(() => { modal.classList.add('hidden'); modal.classList.remove('flex'); }, 200);
}

function openEditModal()         { openModal('editModal'); }
function closeEditModal()        { closeModal('editModal'); }
function openUploadModal()       { openModal('uploadModal'); }
function closeUploadModal()      { closeModal('uploadModal'); }
function openDeleteRecordModal() { openModal('deleteRecordModal'); }
function closeDeleteRecordModal(){ closeModal('deleteRecordModal'); }

function openDeleteAttModal(id, name) {
    document.getElementById('deleteAttId').value = id;
    document.getElementById('deleteAttName').textContent = name;
    openModal('deleteAttModal');
}
function closeDeleteAttModal() { closeModal('deleteAttModal'); }

function addArchiveEntry(containerId, templateId) {
    const fragment = document.getElementById(templateId).content.cloneNode(true);
    document.getElementById(containerId).appendChild(fragment);
}

function removeArchiveEntry(button) {
    const entry = button.closest('.person-entry, .committee-entry');
    const list = entry.parentElement;
    if (list.children.length > 1) entry.remove();
    else entry.querySelectorAll('select, input').forEach((field) => {
        field.value = field.tagName === 'SELECT' && field.name === 'author_role[]' ? 'AUTHOR' : '';
        field.disabled = false;
    });
}

document.addEventListener('change', function(event) {
    const entry = event.target.closest('.person-entry, .committee-entry');
    if (!entry) return;
    const master = entry.querySelector('.entry-master');
    const custom = entry.querySelector('.entry-custom');
    if (event.target === master && master.value) custom.value = '';
    if (event.target === custom && custom.value.trim()) master.value = '';
});

// Close on backdrop click
['editModal','uploadModal','deleteAttModal','deleteRecordModal'].forEach(function(id) {
    const bd = document.getElementById(id + 'Backdrop');
    if (bd) bd.addEventListener('click', () => closeModal(id));
});

// ── Mutually exclusive doc type ───────────────────────────────────────────────
function handleDocTypeChange(prefix) {
    const sel    = document.getElementById(prefix + '_doc_type_id');
    const custom = document.getElementById(prefix + '_custom_type');
    if (sel && custom && sel.value !== '') {
        custom.value = '';
        custom.disabled = true;
    } else if (custom) {
        custom.disabled = false;
    }
}
function handleCustomTypeChange(prefix) {
    const sel    = document.getElementById(prefix + '_doc_type_id');
    const custom = document.getElementById(prefix + '_custom_type');
    if (custom && custom.value.trim() !== '') {
        if (sel) { sel.value = ''; sel.disabled = true; }
    } else if (sel) {
        sel.disabled = false;
    }
}

// ── Init edit modal state ─────────────────────────────────────────────────────
(function() {
    const sel    = document.getElementById('edit_doc_type_id');
    const custom = document.getElementById('edit_custom_type');
    if (!sel || !custom) return;
    if (sel.value) { custom.disabled = true; }
    else if (custom.value) { sel.disabled = true; }
})();
</script>


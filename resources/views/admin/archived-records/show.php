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

$editAuthors = array_values(array_filter($authors, fn($author) => $author['author_role'] === 'AUTHOR'));
$editCoAuthors = array_values(array_filter($authors, fn($author) => $author['author_role'] === 'CO_AUTHOR'));
if (!$editAuthors) {
    $editAuthors = [['author_role' => 'AUTHOR', 'sp_member_id' => null, 'custom_author_name' => null]];
}
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

// JSON encode lists for the JS (shared component used by edit modal)
$spMembersJson   = json_encode(array_values($spMembers),   JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$committeesJson  = json_encode(array_values($allCommittees), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$docTypesJson    = json_encode(array_values($documentTypes), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

// Existing attachments as JSON for the edit modal
$existingAttJson = json_encode(array_map(static function ($a) {
    return [
        'id'        => (int)  $a['id'],
        'file_name' => $a['file_name'],
        'file_size' => (int)  $a['file_size'],
        'mime_type' => $a['mime_type'],
        'file_path' => $a['file_path'],
    ];
}, $attachments), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
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
                        <button type="button" id="openEditBtn" onclick="AR.openEditModal()"
                                class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-4 py-2 text-sm
                                       font-semibold text-white hover:bg-white/25 transition ring-1 ring-white/30">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Edit Record
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
                               class="inline-flex items-center rounded-lg bg-blue-50 px-3 py-1.5 text-sm
                                      font-medium text-blue-700 hover:bg-primary/10 hover:text-primary transition">
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
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-bold text-primary">
                                            <?= strtoupper(mb_substr($name, 0, 1)) ?>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-gray-900">
                                                <?= htmlspecialchars($name) ?>
                                            </p>
                                            <?php if ($pos): ?><p class="text-xs text-gray-500"><?= htmlspecialchars($pos) ?></p>
                                            <?php elseif ($isCustom): ?><p class="text-xs text-gray-400 italic">Custom author</p>
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
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-sm font-bold text-indigo-500">
                                            <?= strtoupper(mb_substr($name, 0, 1)) ?>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-gray-900">
                                                <?= htmlspecialchars($name) ?>
                                            </p>
                                            <?php if ($pos): ?><p class="text-xs text-gray-500"><?= htmlspecialchars($pos) ?></p>
                                            <?php elseif ($isCustom): ?><p class="text-xs text-gray-400 italic">Custom co-author</p>
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
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
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
                    <button type="button" onclick="AR.openModal('uploadModal')"
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
                                            onclick="AR.openDeleteAttModal(<?= (int) $att['id'] ?>, '<?= addslashes(htmlspecialchars($att['file_name'])) ?>')"
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
                        onclick="AR.openModal('deleteRecordModal')"
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
    data-sp-members="<?= htmlspecialchars($spMembersJson, ENT_QUOTES, 'UTF-8') ?>"
    data-committees="<?= htmlspecialchars($committeesJson, ENT_QUOTES, 'UTF-8') ?>"
     class="fixed inset-0 z-[100] hidden items-center justify-center"
     role="dialog" aria-modal="true" aria-labelledby="editModalTitle">
    <div id="editModalBackdrop"
         class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm opacity-0 transition-opacity duration-200"></div>
    <div id="editModalPanel"
         class="relative z-10 mx-4 w-full max-w-4xl max-h-[92vh] overflow-y-auto rounded-2xl bg-white shadow-2xl
                border border-gray-100 transform scale-95 opacity-0 transition-all duration-200">

        <form id="editForm" method="POST" action="<?= BASE_URL ?>/admin/archived-records/update"
              enctype="multipart/form-data" novalidate>
            <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">

            <!-- ── Header ───────────────────────────────────────────────────── -->
            <div class="sticky top-0 z-10 flex items-center justify-between border-b border-gray-100 bg-white px-6 py-5">
                <div>
                    <h3 id="editModalTitle" class="text-lg font-semibold text-gray-900">Edit Archived Record</h3>
                    <p class="mt-0.5 text-xs text-gray-500">
                        Editing <span class="font-medium text-gray-700"><?= htmlspecialchars($record['approved_no']) ?></span>
                    </p>
                </div>
                <button type="button" onclick="AR.closeEditModal()" aria-label="Close"
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
                                <label for="e_approved_no" class="ar-label">
                                    Approved Number <span class="text-red-500" aria-hidden="true">*</span>
                                </label>
                                <input type="text" id="e_approved_no" name="approved_no" required
                                       value="<?= htmlspecialchars($record['approved_no']) ?>"
                                       class="ar-input"
                                       aria-required="true">
                            </div>
                            <div>
                                <label for="e_date_approved" class="ar-label">
                                    Date Approved <span class="text-red-500" aria-hidden="true">*</span>
                                </label>
                                <input type="date" id="e_date_approved" name="date_approved" required
                                       value="<?= htmlspecialchars($record['date_approved']) ?>"
                                       class="ar-input"
                                       aria-required="true">
                            </div>
                        </div>

                        <!-- Subject Matter -->
                        <div>
                            <label for="e_subject_matter" class="ar-label">
                                Subject Matter <span class="text-red-500" aria-hidden="true">*</span>
                            </label>
                            <textarea id="e_subject_matter" name="subject_matter" rows="3" required
                                      class="ar-input resize-y"
                                      aria-required="true"><?= htmlspecialchars($record['subject_matter']) ?></textarea>
                        </div>

                        <!-- Description -->
                        <div>
                            <label for="e_description" class="ar-label">
                                Description <span class="text-xs font-normal text-gray-400">(optional)</span>
                            </label>
                            <textarea id="e_description" name="description" rows="2"
                                      class="ar-input resize-y"><?= htmlspecialchars($record['description'] ?? '') ?></textarea>
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
                                <div class="ar-exclusive-wrap" data-exclusive-group="e_doctype">
                                    <label class="mb-1 block text-xs text-gray-500">From list</label>
                                    <div class="relative">
                                        <select name="document_type_id" id="e_doc_type_id"
                                                class="ar-input ar-exclusive" data-group="e_doctype" data-type="select">
                                            <option value="">Select a document type…</option>
                                            <?php foreach ($documentTypes as $dt): ?>
                                                <option value="<?= (int) $dt['id'] ?>"
                                                    <?= (string) ($record['document_type_id'] ?? '') === (string) $dt['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($dt['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="ar-exclusive-wrap" data-exclusive-group="e_doctype">
                                    <label class="mb-1 block text-xs text-gray-500">Custom type name</label>
                                    <div class="relative">
                                        <input type="text" name="custom_document_type_name" id="e_custom_type"
                                               value="<?= htmlspecialchars($record['custom_document_type_name'] ?? '') ?>"
                                               placeholder="Enter a document type not listed"
                                               maxlength="200"
                                               class="ar-input ar-exclusive pr-10" data-group="e_doctype" data-type="text">
                                        <button type="button" class="ar-exclusive-clear hidden" data-clear-target="text" aria-label="Clear custom document type" title="Clear custom type">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                    <p class="ar-hint">Use when the type is not in the list above</p>
                                </div>
                            </div>
                            <p id="e_doctype_error" class="ar-field-error hidden" role="alert"></p>
                        </div>

                        <!-- Tags -->
                        <div>
                            <label for="e_tags_input" class="ar-label">
                                Tags <span class="text-xs font-normal text-gray-400">(optional)</span>
                            </label>
                            <div class="ar-tag-field" id="e_tags_field">
                                <div id="e_tags_chips" class="flex flex-wrap gap-1.5 empty:hidden mb-1.5"></div>
                                <input type="text" id="e_tags_input"
                                       placeholder="Type a tag and press Enter or comma…"
                                       class="min-w-[180px] flex-1 bg-transparent text-sm text-gray-800 outline-none placeholder-gray-400 py-0.5"
                                       autocomplete="off">
                            </div>
                            <!-- Pre-populated from saved data -->
                            <input type="hidden" name="tags" id="e_tags_hidden"
                                   value="<?= htmlspecialchars($record['tags'] ?? '') ?>">
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
                        Authors/sponsors are listed on the left. Co-authors/co-sponsors are optional; the same person cannot appear in both columns.
                    </p>

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <?php foreach ([['e_authors_list', 'Authors / Sponsors', 'AUTHOR', $editAuthors], ['e_coauthors_list', 'Co-authors / Co-sponsors', 'CO_AUTHOR', $editCoAuthors]] as [$listId, $listLabel, $fixedRole, $people]): ?>
                        <?php
                            $selectedMemberIds = array_values(array_filter(array_column($people, 'sp_member_id')));
                            $customPersonNames = array_values(array_filter(array_column($people, 'custom_author_name')));
                            $personPlaceholder = $fixedRole === 'AUTHOR'
                                ? 'Search and select authors / sponsors…'
                                : 'Search and select co-authors / co-sponsors…';
                        ?>
                        <section class="rounded-xl border <?= $fixedRole === 'AUTHOR' ? 'border-gray-200 bg-gray-50/60' : 'border-indigo-100 bg-indigo-50/30' ?> p-4">
                            <h4 class="mb-1 text-sm font-semibold text-gray-900"><?= htmlspecialchars($listLabel) ?><?= $fixedRole === 'CO_AUTHOR' ? ' <span class="font-normal text-gray-400">(optional)</span>' : '' ?></h4>
                            <p class="mb-3 text-xs text-gray-500">Search and select multiple SP members, or enter custom names below.</p>
                            <div id="<?= $listId ?>" class="ar-multi-group">
                                <div class="ar-multi-select" data-list-key="spMembers" data-role="<?= $fixedRole ?>" data-selected="<?= htmlspecialchars(json_encode($selectedMemberIds), ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="ar-multi-control">
                                        <div class="ar-multi-chips"></div>
                                        <input type="text" class="ar-multi-filter" placeholder="<?= htmlspecialchars($personPlaceholder) ?>" aria-label="Search <?= htmlspecialchars(strtolower($listLabel)) ?>" role="combobox" aria-expanded="false" aria-autocomplete="list" autocomplete="off">
                                        <button type="button" class="ar-multi-toggle" aria-label="Show <?= strtolower(htmlspecialchars($listLabel)) ?> options">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                    </div>
                                    <div class="ar-multi-dropdown hidden" role="listbox" aria-multiselectable="true"><div class="ar-multi-options"></div></div>
                                    <div class="ar-multi-post-fields hidden"></div>
                                </div>
                                <input type="text" class="ar-input ar-multi-custom mt-3" value="<?= htmlspecialchars(implode(', ', $customPersonNames)) ?>"
                                       placeholder="Custom <?= $fixedRole === 'AUTHOR' ? 'author / sponsor' : 'co-author / co-sponsor' ?> names, separated by commas"
                                       aria-label="Custom <?= $fixedRole === 'AUTHOR' ? 'author or sponsor' : 'co-author or co-sponsor' ?> names">
                            </div>
                        </section>
                        <?php endforeach; ?>
                    </div>

                    <p id="e_authors_global_error" class="ar-field-error hidden mt-2" role="alert"></p>
                </fieldset>

                <hr class="border-gray-100">

                <!-- ── Section 4: Committees ─────────────────────────────────── -->
                <fieldset>
                    <legend class="mb-1 flex items-center gap-2 text-sm font-semibold text-gray-900">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">4</span>
                        Committees
                    </legend>
                    <?php
                        $selectedCommitteeIds = array_values(array_filter(array_column($editCommittees, 'committee_id')));
                        $customCommitteeNames = array_values(array_filter(array_column($editCommittees, 'custom_committee_name')));
                    ?>
                    <p class="mb-4 text-xs text-gray-500">Search and select multiple committees, or enter custom committee names separated by commas.</p>

                    <div id="e_committees_list" class="ar-multi-group">
                        <div class="ar-multi-select" data-list-key="committees" data-selected="<?= htmlspecialchars(json_encode($selectedCommitteeIds), ENT_QUOTES, 'UTF-8') ?>">
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
                        <input type="text" class="ar-input ar-multi-custom mt-3" value="<?= htmlspecialchars(implode(', ', $customCommitteeNames)) ?>"
                               placeholder="Custom committee names, separated by commas" aria-label="Custom committee names">
                    </div>
                </fieldset>

                <hr class="border-gray-100">

                <!-- ── Section 5: Attachments ──────────────────────────────── -->
                <fieldset>
                    <legend class="mb-1 flex items-center gap-2 text-sm font-semibold text-gray-900">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">5</span>
                        Attachments
                    </legend>
                    <p class="mb-1 text-xs text-gray-500">
                        Existing attachments are preserved. Newly selected files will be added to this record.
                    </p>
                    <p class="mb-4 text-xs text-gray-500">
                        Accepted: PDF, Word, Excel, JPEG, PNG, GIF, WebP &mdash; max <strong>25 MB</strong> per file, up to <strong>10 new files</strong>.
                    </p>

                    <!-- Existing attachments list -->
                    <?php if (!empty($attachments)): ?>
                    <div class="mb-4 space-y-2">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            Existing attachments (<?= count($attachments) ?>)
                        </p>
                        <?php foreach ($attachments as $att): ?>
                            <?php $iconClass = arFileIcon($att['mime_type']); ?>
                            <div class="ar-file-item ar-file-existing">
                                <div class="ar-file-icon <?= $iconClass ?>">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <div class="ar-file-meta">
                                    <p class="ar-file-name" title="<?= htmlspecialchars($att['file_name']) ?>">
                                        <?= htmlspecialchars($att['file_name']) ?>
                                    </p>
                                    <p class="ar-file-size"><?= arFormatFileSize((int) $att['file_size']) ?> &bull; Existing</p>
                                </div>
                                <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 border border-emerald-200 px-2 py-1 text-xs text-emerald-700 font-medium">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Saved
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- New files drop zone -->
                    <div id="e_drop_zone"
                         class="ar-drop-zone"
                         role="button" tabindex="0" aria-label="Upload new files by dropping here or clicking to browse">
                        <svg class="h-10 w-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.4"
                                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        <p class="mt-2 text-sm font-medium text-gray-600">Drop new files here or <span class="text-primary underline cursor-pointer">browse</span></p>
                        <p class="mt-1 text-xs text-gray-400">PDF, Word, Excel, images — max 25 MB each, 10 files total</p>
                        <input type="file" name="attachments[]" id="e_file_input" multiple
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp"
                               class="hidden" tabindex="-1" aria-hidden="true">
                    </div>

                    <!-- New file previews -->
                    <div id="e_file_list" class="mt-3 space-y-2 empty:hidden"></div>
                    <p id="e_file_error" class="ar-field-error hidden mt-2" role="alert"></p>
                </fieldset>

            </div>

            <!-- ── Footer ───────────────────────────────────────────────────── -->
            <div class="sticky bottom-0 flex items-center justify-end gap-3 border-t border-gray-100 bg-white/95 backdrop-blur-sm px-6 py-4 rounded-b-2xl">
                <button type="button" onclick="AR.closeEditModal()"
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


<!-- ═══════════════════════════════════════════════════════════════════════════
     UPLOAD ATTACHMENT MODAL (standalone)
     ═══════════════════════════════════════════════════════════════════════════ -->
<div id="uploadModal"
     class="fixed inset-0 z-[100] hidden items-center justify-center"
     role="dialog" aria-modal="true" aria-labelledby="uploadModalTitle">
    <div id="uploadModalBackdrop"
         class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm opacity-0 transition-opacity duration-200"></div>
    <div id="uploadModalPanel"
         class="relative z-10 mx-4 w-full max-w-md rounded-2xl bg-white shadow-2xl border border-gray-100
                transform scale-95 opacity-0 transition-all duration-200">
        <form method="POST" action="<?= BASE_URL ?>/admin/archived-records/upload" enctype="multipart/form-data">
            <input type="hidden" name="record_id" value="<?= (int) $record['id'] ?>">

            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5">
                <h3 id="uploadModalTitle" class="text-lg font-semibold text-gray-900">Upload Attachments</h3>
                <button type="button" onclick="AR.closeModal('uploadModal')"
                        class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="px-6 py-5 space-y-4">
                <div id="up_drop_zone" class="ar-drop-zone"
                     role="button" tabindex="0" aria-label="Upload files">
                    <svg class="h-10 w-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.4"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    <p class="mt-2 text-sm font-medium text-gray-600">Drop files here or <span class="text-primary underline cursor-pointer">browse</span></p>
                    <p class="mt-1 text-xs text-gray-400">PDF, Word, Excel, images — max 25 MB each, 10 files</p>
                    <input type="file" name="attachments[]" id="up_file_input" multiple required
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp"
                           class="hidden" tabindex="-1" aria-hidden="true">
                </div>
                <div id="up_file_list" class="space-y-2 empty:hidden"></div>
                <p id="up_file_error" class="ar-field-error hidden" role="alert"></p>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4 rounded-b-2xl">
                <button type="button" onclick="AR.closeModal('uploadModal')"
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
     role="dialog" aria-modal="true" aria-labelledby="deleteAttModalTitle">
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
                        <h3 id="deleteAttModalTitle" class="text-base font-semibold text-gray-900">Remove Attachment</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            Remove <strong id="deleteAttName" class="text-gray-900 break-all"></strong> from this record?
                            The file will be permanently deleted.
                        </p>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4 rounded-b-2xl">
                <button type="button" onclick="AR.closeModal('deleteAttModal')"
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
     DELETE RECORD MODAL
     ═══════════════════════════════════════════════════════════════════════════ -->
<div id="deleteRecordModal"
     class="fixed inset-0 z-[100] hidden items-center justify-center"
     role="dialog" aria-modal="true" aria-labelledby="deleteRecordModalTitle">
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
                        <h3 id="deleteRecordModalTitle" class="text-base font-semibold text-gray-900">Delete This Record</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            Permanently delete <strong class="text-gray-900"><?= htmlspecialchars($record['approved_no']) ?></strong>?
                            All authors, committees, and attachments will be removed.
                        </p>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4 rounded-b-2xl">
                <button type="button" onclick="AR.closeModal('deleteRecordModal')"
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


<!-- ═══════════════════════════════════════════════════════════════════════════
     SHARED STYLES (same utility classes as index.php)
     ═══════════════════════════════════════════════════════════════════════════ -->
<style>
.ar-label { display:block; font-size:0.75rem; font-weight:600; color:#374151; margin-bottom:0.375rem; }
.ar-hint  { margin-top:0.25rem; font-size:0.75rem; color:#9ca3af; }
.ar-field-error { font-size:0.75rem; color:#dc2626; }
.ar-input { display:block; width:100%; border-radius:0.75rem; border:1px solid #e5e7eb; padding:0.625rem 0.75rem; font-size:0.875rem; color:#1f2937; background:#fff; transition:border-color .15s,box-shadow .15s; }
.ar-input:focus { outline:none; border-color:var(--color-primary,#2563EB); box-shadow:0 0 0 3px rgba(37,99,235,.12); }
.ar-input.ar-exclusive-disabled { background:#f3f4f6; color:#9ca3af; cursor:not-allowed; pointer-events:none; }
.ar-input.ar-exclusive-active { border-color:#2563EB; background:#eff6ff; }
.ar-exclusive-clear { position:absolute; top:50%; right:0.5rem; display:flex; height:1.75rem; width:1.75rem; transform:translateY(-50%); align-items:center; justify-content:center; border:0; border-radius:0.5rem; background:transparent; color:#9ca3af; cursor:pointer; }
.ar-exclusive-clear:hover { background:#f3f4f6; color:#4b5563; }
.ar-exclusive-clear.hidden { display:none; }
.ar-tag-field { display:flex; flex-wrap:wrap; align-items:center; gap:0.375rem; border-radius:0.75rem; border:1px solid #e5e7eb; padding:0.5rem 0.75rem; cursor:text; min-height:2.75rem; background:#fff; transition:border-color .15s,box-shadow .15s; }
.ar-tag-field:focus-within { border-color:var(--color-primary,#2563EB); box-shadow:0 0 0 3px rgba(37,99,235,.12); }
.ar-tag-chip { display:inline-flex; align-items:center; gap:0.25rem; padding:0.125rem 0.5rem 0.125rem 0.625rem; border-radius:9999px; background:#eff6ff; color:#1d4ed8; font-size:0.75rem; font-weight:500; }
.ar-tag-chip button { display:flex; align-items:center; justify-content:center; width:1rem; height:1rem; border-radius:50%; background:transparent; border:none; color:#93c5fd; cursor:pointer; padding:0; transition:background .1s,color .1s; }
.ar-tag-chip button:hover { background:#bfdbfe; color:#1d4ed8; }
.ar-search-select { position:relative; }
.ar-search-display { display:flex; align-items:center; justify-content:space-between; gap:0.5rem; border-radius:0.75rem; border:1px solid #e5e7eb; padding:0.625rem 0.75rem; font-size:0.875rem; background:#fff; cursor:pointer; transition:border-color .15s,box-shadow .15s; min-height:2.625rem; }
.ar-search-display:focus { outline:none; border-color:var(--color-primary,#2563EB); box-shadow:0 0 0 3px rgba(37,99,235,.12); }
.ar-search-display.ar-active { border-color:#2563EB; background:#eff6ff; }
.ar-search-display.ar-exclusive-disabled { background:#f3f4f6; color:#9ca3af; cursor:not-allowed; pointer-events:none; }
.ar-search-display-text { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1; font-size:0.875rem; }
.ar-search-chevron { transition:transform .2s; flex-shrink:0; }
.ar-search-display[aria-expanded="true"] .ar-search-chevron { transform:rotate(180deg); }
.ar-search-dropdown { position:absolute; top:calc(100% + 4px); left:0; right:0; z-index:200; border-radius:0.75rem; border:1px solid #e5e7eb; background:#fff; box-shadow:0 10px 25px -5px rgba(0,0,0,.12),0 4px 10px -3px rgba(0,0,0,.07); overflow:hidden; }
.ar-search-filter-wrap { padding:0.5rem; border-bottom:1px solid #f3f4f6; }
.ar-search-filter-input { width:100%; border-radius:0.5rem; border:1px solid #e5e7eb; padding:0.375rem 0.625rem; font-size:0.8125rem; outline:none; transition:border-color .15s; }
.ar-search-filter-input:focus { border-color:#2563EB; }
.ar-search-options { max-height:12rem; overflow-y:auto; }
.ar-search-option { padding:0.5rem 0.75rem; font-size:0.875rem; cursor:pointer; transition:background .1s; }
.ar-search-option:hover,.ar-search-option.ar-option-focused { background:#eff6ff; color:#1d4ed8; }
.ar-search-option.ar-option-selected { background:#dbeafe; color:#1d4ed8; font-weight:500; }
.ar-search-option.ar-option-disabled,.ar-search-option.ar-option-disabled:hover { background:#f9fafb; color:#9ca3af; cursor:not-allowed; }
.ar-search-option.ar-option-clear { color:#6b7280; font-style:italic; border-bottom:1px solid #f3f4f6; }
.ar-search-no-results { padding:0.625rem 0.75rem; font-size:0.8125rem; color:#9ca3af; text-align:center; }
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
.ar-person-row,.ar-committee-row { border-radius:0.75rem; border:1px solid #e5e7eb; background:#f9fafb; padding:0.75rem; }
.ar-person-inner { display:grid; gap:0.625rem; align-items:start; grid-template-columns:minmax(0,1fr) auto; }
.ar-person-member-col { grid-column:1 / -1; }
.ar-person-custom-col { grid-column:1; }
.ar-committee-inner { display:grid; gap:0.625rem; align-items:start; grid-template-columns:1fr; }
@media(min-width:640px){ .ar-committee-inner { grid-template-columns:1fr 1fr auto; align-items:center; } }
.ar-remove-btn { display:flex; align-items:center; justify-content:center; width:2rem; height:2rem; border-radius:0.5rem; border:1px solid #e5e7eb; background:#fff; color:#9ca3af; cursor:pointer; transition:background .1s,color .1s,border-color .1s; flex-shrink:0; }
.ar-remove-btn:hover { background:#fef2f2; color:#dc2626; border-color:#fca5a5; }
.ar-drop-zone { display:flex; flex-direction:column; align-items:center; justify-content:center; border-radius:1rem; border:2px dashed #d1d5db; background:#f9fafb; padding:2rem 1.5rem; text-align:center; cursor:pointer; transition:border-color .2s,background .2s; outline:none; }
.ar-drop-zone:hover,.ar-drop-zone.ar-drag-over,.ar-drop-zone:focus-visible { border-color:#2563EB; background:#eff6ff; }
.ar-drop-zone.ar-drag-over { box-shadow:inset 0 0 0 3px rgba(37,99,235,.12); }
.ar-file-item { display:flex; align-items:center; gap:0.75rem; border-radius:0.75rem; border:1px solid #e5e7eb; background:#fff; padding:0.625rem 0.75rem; }
.ar-file-icon { display:flex; align-items:center; justify-content:center; width:2.25rem; height:2.25rem; border-radius:0.5rem; border:1px solid #e5e7eb; flex-shrink:0; }
.ar-file-meta { flex:1; min-width:0; }
.ar-file-name { font-size:0.875rem; font-weight:500; color:#111827; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.ar-file-size { font-size:0.75rem; color:#9ca3af; margin-top:0.125rem; }
.ar-file-remove { display:flex; align-items:center; justify-content:center; width:1.75rem; height:1.75rem; border-radius:0.5rem; border:1px solid #e5e7eb; background:#fff; color:#9ca3af; cursor:pointer; transition:background .1s,color .1s,border-color .1s; flex-shrink:0; }
.ar-file-remove:hover { background:#fef2f2; color:#dc2626; border-color:#fca5a5; }
.ar-file-existing { background:#f0fdf4; border-color:#bbf7d0; }
.ar-file-existing .ar-file-name { color:#166534; }
</style>


<!-- ═══════════════════════════════════════════════════════════════════════════
     JAVASCRIPT — data + show-page specific init
     ═══════════════════════════════════════════════════════════════════════════ -->
<script>
(function () {
'use strict';

// ── Data injected from PHP ────────────────────────────────────────────────────
// These override / supplement the AR.DATA set in index.php context;
// on the show page AR is not defined yet, so we define it here.
const DATA = {
    spMembers:  <?= $spMembersJson ?>,
    committees: <?= $committeesJson ?>,
};

const MAX_FILES      = 10;
const MAX_FILE_BYTES = 26214400;
const ALLOWED_EXTS   = ['.pdf','.doc','.docx','.xls','.xlsx','.jpg','.jpeg','.png','.gif','.webp'];

// ── Utilities ─────────────────────────────────────────────────────────────────
function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function escAttr(s) { return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }

// ── Modal ─────────────────────────────────────────────────────────────────────
function openModal(id) {
    const modal = document.getElementById(id);
    const bd    = document.getElementById(id + 'Backdrop');
    const panel = document.getElementById(id + 'Panel');
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    requestAnimationFrame(() => {
        bd.classList.remove('opacity-0');
        panel.classList.remove('scale-95','opacity-0');
        panel.classList.add('scale-100','opacity-100');
    });
    const esc = (e) => { if (e.key === 'Escape') { closeModal(id); document.removeEventListener('keydown', esc); } };
    document.addEventListener('keydown', esc);
    bd.onclick = () => closeModal(id);
}
function closeModal(id) {
    const modal = document.getElementById(id);
    const bd    = document.getElementById(id + 'Backdrop');
    const panel = document.getElementById(id + 'Panel');
    if (!modal) return;
    bd.classList.add('opacity-0');
    panel.classList.add('scale-95','opacity-0');
    panel.classList.remove('scale-100','opacity-100');
    setTimeout(() => { modal.classList.add('hidden'); modal.classList.remove('flex'); }, 200);
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

    function saveSelection() { widget.dataset.selected = JSON.stringify(Array.from(selected)); }
    function assignedToOtherRole(memberId) {
        if (listKey !== 'spMembers' || !role || !form) return false;
        return Array.from(form.querySelectorAll('.ar-multi-select[data-list-key="spMembers"]')).some(other =>
            other !== widget && other.dataset.role !== role && JSON.parse(other.dataset.selected || '[]').map(String).includes(String(memberId))
        );
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
            remove.textContent = '×';
            remove.addEventListener('click', event => {
                event.stopPropagation();
                selected.delete(id); saveSelection(); renderChips(); renderOptions(filter.value); filter.focus();
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
            label.textContent = String(item[nameField] || '') + (listKey === 'spMembers' && item.position ? ' — ' + item.position : '');
            option.append(check, label);
            option.addEventListener('click', event => {
                event.stopPropagation();
                if (isBlocked || assignedToOtherRole(id)) return;
                if (selected.has(id)) selected.delete(id); else selected.add(id);
                saveSelection(); renderChips(); renderOptions(filter.value); filter.focus();
            });
            options.appendChild(option);
        });
    }
    function openDropdown() { dropdown.classList.remove('hidden'); filter.setAttribute('aria-expanded', 'true'); renderOptions(filter.value); }
    function closeDropdown() { dropdown.classList.add('hidden'); filter.setAttribute('aria-expanded', 'false'); }
    filter.addEventListener('focus', openDropdown);
    filter.addEventListener('input', () => renderOptions(filter.value));
    filter.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeDropdown();
        if (event.key === 'Enter') {
            const firstOption = options.querySelector('.ar-multi-option:not(:disabled)');
            if (firstOption) { event.preventDefault(); firstOption.click(); }
        }
        if (event.key === 'Backspace' && !filter.value && selected.size) {
            selected.delete(Array.from(selected).pop()); saveSelection(); renderChips(); renderOptions('');
        }
    });
    toggle.addEventListener('click', () => dropdown.classList.contains('hidden') ? (filter.focus(), openDropdown()) : closeDropdown());
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
        JSON.parse(widget.dataset.selected || '[]').map(String).forEach(id => {
            register('member:' + id, role);
            const member = (DATA.spMembers || []).find(item => String(item.sp_member_id) === id);
            if (member?.full_name) register('name:' + member.full_name.trim().replace(/\s+/g, ' ').toLocaleLowerCase(), role);
        });
        (widget.closest('.ar-multi-group')?.querySelector('.ar-multi-custom')?.value || '')
            .split(',').map(name => name.trim().replace(/\s+/g, ' ').toLocaleLowerCase()).filter(Boolean)
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
            input.type = 'hidden'; input.name = name; input.value = value; target.appendChild(input);
        };
        entries.forEach(entry => {
            if (widget.dataset.listKey === 'spMembers') {
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

// ── Searchable select ─────────────────────────────────────────────────────────
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

    let selectedId  = hiddenId ? (hiddenId.value || widget.dataset.prefillId || '') : '';
    let selectedTxt = '';

    const idField   = listKey === 'spMembers' ? 'sp_member_id' : 'id';
    const nameField = listKey === 'spMembers' ? 'full_name'    : 'name';

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
        let html = '<div class="ar-search-option ar-option-clear" data-val="">— Clear selection —</div>';
        let count = 0;
        for (const item of items) {
            const name = String(item[nameField] || '');
            const pos  = listKey === 'spMembers' ? (item.position ? ' — ' + item.position : '') : '';
            if (query && !name.toLowerCase().includes(query)) continue;
            const isSel = String(item[idField]) === String(selectedId);
            const isBlocked = isAssignedToOtherRole(item[idField]) && !isSel;
            html += `<div class="ar-search-option${isSel ? ' ar-option-selected' : ''}${isBlocked ? ' ar-option-disabled' : ''}" data-disabled="${isBlocked ? '1' : '0'}" data-val="${item[idField]}" data-label="${escAttr(name)}"${isBlocked ? ' aria-disabled="true"' : ''}>${escHtml(name)}${escHtml(pos)}</div>`;
            count++;
        }
        if (count === 0 && query) html += `<div class="ar-search-no-results">No results for "${escHtml(query)}"</div>`;
        optionsEl.innerHTML = html;
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
        if (hiddenId) hiddenId.value = selectedId;
        if (selectedId) {
            displayTxt.textContent = selectedTxt;
            displayTxt.classList.remove('text-gray-400'); displayTxt.classList.add('text-gray-900');
            display.classList.add('ar-active');
            enforceExclusive(widget, 'select');
        } else {
            displayTxt.textContent = listKey === 'spMembers' ? 'Select SP member…' : 'Select committee…';
            displayTxt.classList.add('text-gray-400'); displayTxt.classList.remove('text-gray-900');
            display.classList.remove('ar-active');
            enforceExclusive(widget, 'clear-select');
        }
        closeDropdown();
    }

    function openDropdown() {
        dropdown.classList.remove('hidden');
        display.setAttribute('aria-expanded','true');
        filterIn.value = '';
        render('');
        filterIn.focus();
    }
    function closeDropdown() {
        dropdown.classList.add('hidden');
        display.setAttribute('aria-expanded','false');
    }

    display.addEventListener('click', () => { if (!display.classList.contains('ar-exclusive-disabled')) dropdown.classList.contains('hidden') ? openDropdown() : closeDropdown(); });
    display.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openDropdown(); } });
    filterIn.addEventListener('input', () => render(filterIn.value));
    filterIn.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeDropdown(); });

    // Prefill from existing value
    if (selectedId) {
        const found = items.find(i => String(i[idField]) === String(selectedId));
        if (found) selectItem(String(found[idField]), String(found[nameField]));
    }
}

document.addEventListener('click', (e) => {
    document.querySelectorAll('.ar-search-dropdown:not(.hidden)').forEach(dd => {
        if (!dd.closest('.ar-search-select').contains(e.target)) {
            dd.classList.add('hidden');
            dd.closest('.ar-search-select').querySelector('.ar-search-display')?.setAttribute('aria-expanded','false');
        }
    });
});

// ── Exclusive pairs ───────────────────────────────────────────────────────────
function enforceExclusive(widgetOrInput, type) {
    const row = widgetOrInput.closest('.ar-person-row,.ar-committee-row');
    if (!row) return;
    const ss      = row.querySelector('.ar-search-select');
    const custom  = row.querySelector('.ar-person-custom,.ar-comm-custom');
    if (!ss || !custom) return;
    const hiddenId = ss.querySelector('.ar-search-hidden-id');
    const display  = ss.querySelector('.ar-search-display');

    if (type === 'select' && hiddenId && hiddenId.value) {
        custom.value = ''; custom.disabled = true;
        custom.classList.add('ar-exclusive-disabled');
        custom.placeholder = 'Clear selection to use custom name';
    } else if (type === 'custom' && custom.value.trim()) {
        if (hiddenId) hiddenId.value = '';
        display.classList.remove('ar-active');
        const dt = display.querySelector('.ar-search-display-text');
        if (dt) { dt.textContent = display.closest('[data-list-key="spMembers"]') ? 'Select SP member…' : 'Select committee…'; dt.classList.add('text-gray-400'); dt.classList.remove('text-gray-900'); }
        display.classList.add('ar-exclusive-disabled');
    } else {
        custom.disabled = false; custom.classList.remove('ar-exclusive-disabled');
        custom.placeholder = custom.closest('.ar-person-row') ? 'Or custom name' : 'Or custom name(s), comma-separated';
        display.classList.remove('ar-exclusive-disabled');
    }
}

// ── Tags ──────────────────────────────────────────────────────────────────────
function initTagField(cfg) {
    const field  = document.getElementById(cfg.fieldId);
    const input  = document.getElementById(cfg.inputId);
    const chips  = document.getElementById(cfg.chipsId);
    const hidden = document.getElementById(cfg.hiddenId);
    if (!field || !input) return;

    let tags = [];
    if (hidden && hidden.value) {
        String(hidden.value).split(',').map(t => t.trim().toLowerCase()).filter(Boolean).forEach(t => { if (!tags.includes(t)) tags.push(t); });
    }
    renderChips();

    function renderChips() {
        chips.innerHTML = '';
        tags.forEach((tag, idx) => {
            const chip = document.createElement('span');
            chip.className = 'ar-tag-chip';
            chip.innerHTML = escHtml(tag) + '<button type="button" aria-label="Remove tag ' + escAttr(tag) + '">&times;</button>';
            chip.querySelector('button').addEventListener('click', () => { tags.splice(idx, 1); renderChips(); input.focus(); });
            chips.appendChild(chip);
        });
        if (hidden) hidden.value = tags.join(',');
    }

    function addTag(raw) {
        raw.split(',').map(t => t.trim().toLowerCase()).filter(Boolean).forEach(t => { if (!tags.includes(t)) tags.push(t); });
        renderChips();
    }

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); const v = input.value.trim(); if (v) { addTag(v); input.value = ''; } }
        else if (e.key === 'Backspace' && !input.value && tags.length) { tags.pop(); renderChips(); }
    });
    input.addEventListener('blur', () => { const v = input.value.trim(); if (v) { addTag(v); input.value = ''; } });
    field.addEventListener('click', () => input.focus());
}

// ── Drop zone ─────────────────────────────────────────────────────────────────
function initDropZone(cfg) {
    const zone  = document.getElementById(cfg.zoneId);
    const input = document.getElementById(cfg.inputId);
    const list  = document.getElementById(cfg.listId);
    const errEl = document.getElementById(cfg.errorId);
    if (!zone || !input) return;

    let files = [];

    function fmt(b) { if (b>=1048576) return (b/1048576).toFixed(1)+' MB'; if (b>=1024) return (b/1024).toFixed(1)+' KB'; return b+' B'; }
    function mimeIcon(f) {
        if (f.type==='application/pdf') return 'text-red-500';
        if (f.type.includes('word')) return 'text-blue-500';
        if (f.type.includes('excel')||f.type.includes('spreadsheet')) return 'text-emerald-500';
        if (f.type.startsWith('image/')) return 'text-purple-500';
        return 'text-gray-400';
    }
    function validate(arr) {
        const errs = [];
        if (files.length + arr.length > MAX_FILES) { errs.push(`Max ${MAX_FILES} files. You already have ${files.length}.`); return errs; }
        arr.forEach(f => {
            if (f.size > MAX_FILE_BYTES) errs.push(`"${f.name}" exceeds 25 MB.`);
            const ext = '.' + f.name.split('.').pop().toLowerCase();
            if (!ALLOWED_EXTS.includes(ext)) errs.push(`"${f.name}" has an unsupported type.`);
        });
        return errs;
    }
    function renderList() {
        if (!list) return;
        list.innerHTML = '';
        files.forEach((f, idx) => {
            const cls = mimeIcon(f);
            const item = document.createElement('div');
            item.className = 'ar-file-item';
            item.innerHTML = `<div class="ar-file-icon ${cls}"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></div><div class="ar-file-meta"><p class="ar-file-name" title="${escAttr(f.name)}">${escHtml(f.name)}</p><p class="ar-file-size">${fmt(f.size)} &bull; New</p></div><button type="button" class="ar-file-remove" aria-label="Remove ${escAttr(f.name)}"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>`;
            item.querySelector('.ar-file-remove').addEventListener('click', () => { files.splice(idx, 1); renderList(); syncInput(); });
            list.appendChild(item);
        });
    }
    function syncInput() {
        try { const dt = new DataTransfer(); files.forEach(f => dt.items.add(f)); input.files = dt.files; } catch(e) {}
    }
    function addFiles(newFiles) {
        const arr = Array.from(newFiles);
        const errs = validate(arr);
        if (errs.length) { if (errEl) { errEl.textContent = errs.join(' '); errEl.classList.remove('hidden'); } return; }
        if (errEl) errEl.classList.add('hidden');
        files = files.concat(arr);
        renderList();
        syncInput();
    }
    zone.addEventListener('click', () => input.click());
    zone.addEventListener('keydown', (e) => { if (e.key==='Enter'||e.key===' ') { e.preventDefault(); input.click(); } });
    input.addEventListener('change', () => addFiles(input.files));
    zone.addEventListener('dragover', (e) => { e.preventDefault(); zone.classList.add('ar-drag-over'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('ar-drag-over'));
    zone.addEventListener('drop', (e) => { e.preventDefault(); zone.classList.remove('ar-drag-over'); addFiles(e.dataTransfer.files); });
}

// ── Row management ────────────────────────────────────────────────────────────
function buildPersonRow(prefill) {
    prefill = prefill || {};
    const role = prefill.role === 'CO_AUTHOR' ? 'CO_AUTHOR' : 'AUTHOR';
    const row = document.createElement('div');
    row.className = 'ar-person-row';
    row.dataset.role = role;
    row.innerHTML = `
        <input type="hidden" name="author_role[]" value="${role}">
        <div class="ar-person-inner">
            <div class="ar-person-member-col">
                <div class="ar-search-select" data-list-key="spMembers">
                    <div class="ar-search-display" tabindex="0" role="combobox" aria-expanded="false" aria-haspopup="listbox" aria-label="Select existing SP member">
                        <span class="ar-search-display-text text-gray-400">Select SP member…</span>
                        <svg class="ar-search-chevron h-4 w-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                    <input type="hidden" name="author_sp_member_id[]" class="ar-search-hidden-id" value="${escAttr(String(prefill.sp_member_id||''))}">
                    <div class="ar-search-dropdown hidden" role="listbox">
                        <div class="ar-search-filter-wrap"><input type="text" class="ar-search-filter-input" placeholder="Search name…" aria-label="Search SP members" autocomplete="off"></div>
                        <div class="ar-search-options"></div>
                    </div>
                </div>
            </div>
            <div class="ar-person-custom-col">
                <input type="text" name="author_custom_name[]" placeholder="Or custom name" maxlength="200" value="${escAttr(prefill.custom_author_name||'')}" class="ar-input ar-person-custom text-sm" aria-label="Custom person name">
            </div>
            <button type="button" class="ar-remove-btn" aria-label="Remove this person">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <p class="ar-row-error hidden text-xs text-red-600 mt-1" role="alert"></p>`;
    row.querySelector('.ar-remove-btn').addEventListener('click', () => removeRow(row.querySelector('.ar-remove-btn')));
    setTimeout(() => {
        const ss = row.querySelector('.ar-search-select');
        if (ss) { initSearchSelect(ss); if (prefill.sp_member_id) enforceExclusive(ss,'select'); else if (prefill.custom_author_name) enforceExclusive(ss,'custom'); }
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
                        <svg class="ar-search-chevron h-4 w-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                    <input type="hidden" name="committee_id[]" class="ar-search-hidden-id" value="${escAttr(String(prefill.committee_id||''))}">
                    <div class="ar-search-dropdown hidden" role="listbox">
                        <div class="ar-search-filter-wrap"><input type="text" class="ar-search-filter-input" placeholder="Search committees…" aria-label="Search committees" autocomplete="off"></div>
                        <div class="ar-search-options"></div>
                    </div>
                </div>
            </div>
            <div class="ar-comm-custom-col">
                <input type="text" name="committee_custom_name[]" placeholder="Or custom name(s), comma-separated" maxlength="200" value="${escAttr(prefill.custom_committee_name||'')}" class="ar-input ar-comm-custom text-sm" aria-label="Custom committee name">
            </div>
            <button type="button" class="ar-remove-btn" aria-label="Remove this committee">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>`;
    row.querySelector('.ar-remove-btn').addEventListener('click', () => removeRow(row.querySelector('.ar-remove-btn')));
    setTimeout(() => {
        const ss = row.querySelector('.ar-search-select');
        if (ss) { initSearchSelect(ss); if (prefill.committee_id) enforceExclusive(ss,'select'); else if (prefill.custom_committee_name) enforceExclusive(ss,'custom'); }
    }, 0);
    return row;
}

function removeRow(btn) {
    const row  = btn.closest('.ar-person-row,.ar-committee-row');
    const list = row.parentElement;
    const rowClass = row.classList.contains('ar-person-row') ? '.ar-person-row' : '.ar-committee-row';
    if (row.classList.contains('ar-person-row') && row.dataset.role === 'CO_AUTHOR'
        && list.querySelectorAll(':scope > .ar-person-row').length === 1) { row.remove(); return; }
    if (list.querySelectorAll(':scope > ' + rowClass).length > 1) { row.remove(); return; }
    // Last row — clear it
    row.querySelectorAll('input[type=text],.ar-search-hidden-id').forEach(f => f.value = '');
    const display = row.querySelector('.ar-search-display');
    if (display) {
        const txt = display.querySelector('.ar-search-display-text');
        if (txt) { txt.textContent = row.querySelector('[data-list-key="spMembers"]') ? 'Select SP member…' : 'Select committee…'; txt.classList.add('text-gray-400'); txt.classList.remove('text-gray-900'); }
        display.classList.remove('ar-active','ar-exclusive-disabled');
    }
    const custom = row.querySelector('.ar-person-custom,.ar-comm-custom');
    if (custom) { custom.disabled = false; custom.classList.remove('ar-exclusive-disabled'); custom.placeholder = row.classList.contains('ar-person-row') ? 'Or custom name' : 'Or custom name(s), comma-separated'; }
}

// ── Dual-role validation ──────────────────────────────────────────────────────
function validateAuthors(listIds) {
    const ids = Array.isArray(listIds) ? listIds : [listIds];
    const rows = ids.flatMap(id => Array.from(document.getElementById(id)?.querySelectorAll('.ar-person-row') || []));
    const seenA = new Set(), seenC = new Set();
    let valid = true;
    rows.forEach(row => {
        const errEl  = row.querySelector('.ar-row-error');
        const roleEl = row.querySelector('[name="author_role[]"]');
        const idEl   = row.querySelector('.ar-search-hidden-id');
        const custEl = row.querySelector('.ar-person-custom');
        const role   = roleEl?.value || row.dataset.role || 'AUTHOR';
        const id     = idEl ? idEl.value.trim() : '';
        const custom = custEl ? custEl.value.trim().toLowerCase() : '';
        const key    = id ? ('id:'+id) : (custom ? ('name:'+custom) : null);
        if (errEl) errEl.classList.add('hidden');
        if (!key) return;
        const otherSet = role==='AUTHOR' ? seenC : seenA;
        const mySet    = role==='AUTHOR' ? seenA : seenC;
        if (otherSet.has(key)) {
            if (errEl) { errEl.textContent = 'This person already has the other role — a person cannot be both Author and Co-author.'; errEl.classList.remove('hidden'); }
            valid = false;
        }
        mySet.add(key);
    });
    return valid;
}

// ── Doc type exclusive ────────────────────────────────────────────────────────
function initDocTypeExclusive(prefix) {
    const sel  = document.querySelector(`[data-group="${prefix}"][data-type="select"]`);
    const txt  = document.querySelector(`[data-group="${prefix}"][data-type="text"]`);
    if (!sel || !txt) return;
    const clearText = txt.parentElement.querySelector('[data-clear-target="text"]');
    function update() {
        if (sel.value) {
            txt.value=''; txt.disabled=true; txt.classList.add('ar-exclusive-disabled'); sel.classList.add('ar-exclusive-active');
        } else if (txt.value.trim()) {
            sel.value=''; sel.disabled=true; sel.classList.add('ar-exclusive-disabled'); txt.classList.add('ar-exclusive-active');
        } else {
            txt.disabled=false; sel.disabled=false;
            txt.classList.remove('ar-exclusive-disabled','ar-exclusive-active');
            sel.classList.remove('ar-exclusive-disabled','ar-exclusive-active');
        }
        if (clearText) clearText.classList.toggle('hidden', !sel.value && !txt.value.trim());
    }
    sel.addEventListener('change', update);
    txt.addEventListener('input', update);
    clearText?.addEventListener('click', () => { sel.value=''; txt.value=''; update(); sel.focus(); });
    update();
}

// ── Public AR namespace ───────────────────────────────────────────────────────
window.AR = {
    openModal:  openModal,
    closeModal: closeModal,
    openEditModal:  () => openModal('editModal'),
    closeEditModal: () => closeModal('editModal'),
    openDeleteAttModal: (id, name) => {
        document.getElementById('deleteAttId').value = id;
        document.getElementById('deleteAttName').textContent = name;
        openModal('deleteAttModal');
    },
    addPersonRow:    (listId, role) => { const l = document.getElementById(listId); if (l) l.appendChild(buildPersonRow({role: role || (listId.includes('coauthor') ? 'CO_AUTHOR' : 'AUTHOR')})); },
    addCommitteeRow: (listId) => { const l = document.getElementById(listId); if (l) l.appendChild(buildCommitteeRow({})); },
    removeRow: removeRow,
    initSearchSelect: initSearchSelect,
    initDocTypeExclusive: initDocTypeExclusive,
    initTagField: initTagField,
    initDropZone: initDropZone,
    validateAuthors: validateAuthors,
    initMultiSelect: initMultiSelect,
    validateMultiAuthors: validateMultiAuthors,
    serializeMultiSelects: serializeMultiSelects,
    enforceExclusive: enforceExclusive,
};

// ── Init on DOM ready ─────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {

    document.querySelectorAll('#editModal .ar-multi-select').forEach(w => initMultiSelect(w));

    // Init all pre-rendered search selects in edit modal
    document.querySelectorAll('#editModal .ar-search-select').forEach(w => initSearchSelect(w));

    // Enforce existing exclusive state on pre-rendered rows (edit modal)
    document.querySelectorAll('#editModal .ar-person-row, #editModal .ar-committee-row').forEach(row => {
        const ss     = row.querySelector('.ar-search-select');
        const custom = row.querySelector('.ar-person-custom,.ar-comm-custom');
        if (ss) {
            const hid = ss.querySelector('.ar-search-hidden-id');
            if (hid && hid.value) enforceExclusive(ss, 'select');
            else if (custom && custom.value.trim()) enforceExclusive(ss, 'custom');
        }
    });

    // Delegated input handler for custom text fields
    document.addEventListener('input', function (e) {
        if (e.target.classList.contains('ar-person-custom') || e.target.classList.contains('ar-comm-custom')) {
            enforceExclusive(e.target, e.target.value.trim() ? 'custom' : 'clear-custom');
        }
    });

    // Edit modal: doc type exclusive
    initDocTypeExclusive('e_doctype');

    // Edit modal: tags
    initTagField({ fieldId:'e_tags_field', inputId:'e_tags_input', chipsId:'e_tags_chips', hiddenId:'e_tags_hidden' });

    // Edit modal: drop zone for new attachments
    initDropZone({ zoneId:'e_drop_zone', inputId:'e_file_input', listId:'e_file_list', errorId:'e_file_error' });

    // Upload modal: drop zone
    initDropZone({ zoneId:'up_drop_zone', inputId:'up_file_input', listId:'up_file_list', errorId:'up_file_error' });

    // Edit form submit validation
    const editForm = document.getElementById('editForm');
    if (editForm) {
        editForm.addEventListener('submit', function (e) {
            if (!validateMultiAuthors(editForm)) {
                e.preventDefault();
                return;
            }
            serializeMultiSelects(editForm);
        });
    }

    // Auto-open edit modal when URL hash is #edit (from list row Edit button)
    if (window.location.hash === '#edit') {
        openModal('editModal');
        // Clean up hash without reloading
        history.replaceState(null, '', window.location.pathname + window.location.search);
    }
});

})();
</script>

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

?>

<div class="detail-page space-y-6">

    <!-- ── Page header ─────────────────────────────────────────────────────── -->
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative z-10 flex flex-col gap-5 px-5 py-6 sm:flex-row sm:items-center sm:px-8 sm:py-7">
            <a href="<?= BASE_URL ?>/admin/archived-records"
               class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 text-white
                      transition hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
               aria-label="Back to Archived Records">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div class="min-w-0 flex-1">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-blue-100">ADMIN / ARCHIVED RECORD</p>
                <h1 class="mt-1 break-words text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    <?= htmlspecialchars($record['approved_no']) ?>
                </h1>
                <p class="mt-1.5 max-w-3xl text-sm leading-relaxed text-blue-100 line-clamp-2">
                    <?= htmlspecialchars($record['subject_matter']) ?>
                </p>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="inline-flex max-w-full items-center rounded-full bg-white/15 px-2.5 py-1 text-xs font-semibold text-white ring-1 ring-white/20">
                        <?= htmlspecialchars($record['document_type_name'] ?? 'Unknown type') ?>
                    </span>
                    <?php if (!empty($record['is_public'])): ?>
                        <span class="inline-flex items-center rounded-full bg-emerald-400/20 px-2.5 py-1 text-xs font-semibold text-white ring-1 ring-white/20">
                            Published to portal
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center rounded-full bg-amber-300/20 px-2.5 py-1 text-xs font-semibold text-white ring-1 ring-white/20">
                            Not published
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <button type="button" onclick="openEditModal()"
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-white/15 px-4 py-2.5
                           text-sm font-semibold text-white ring-1 ring-white/30 transition hover:bg-white/25
                           focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit Record
            </button>
        </div>
        <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
    </section>

    <!-- ── Two-column layout ───────────────────────────────────────────────── -->
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3 lg:gap-6">

        <!-- ── Main column (2/3) ───────────────────────────────────────────── -->
        <div class="space-y-5 lg:col-span-2 lg:space-y-6">

            <!-- Record Information -->
            <section class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900">Record Information</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">

                    <div>
                        <dt class="text-xs font-medium text-gray-500">Approved Number</dt>
                        <dd class="mt-1 text-sm font-semibold text-gray-900">
                            <?= htmlspecialchars($record['approved_no']) ?>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs font-medium text-gray-500">Date Approved</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <?= htmlspecialchars(date('F j, Y', strtotime($record['date_approved']))) ?>
                        </dd>
                    </div>

                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium text-gray-500">Subject Matter</dt>
                        <dd class="mt-1 whitespace-pre-line break-words text-sm leading-relaxed text-gray-900"><?= escape_trimmed_text($record['subject_matter']) ?></dd>
                    </div>

                    <?php if (trim((string) ($record['description'] ?? '')) !== ''): ?>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium text-gray-500">Description</dt>
                        <dd class="mt-1 whitespace-pre-line break-words text-sm leading-relaxed text-gray-900"><?= escape_trimmed_text($record['description']) ?></dd>
                    </div>
                    <?php endif; ?>

                    <div>
                        <dt class="text-xs font-medium text-gray-500">Document Type</dt>
                        <dd class="mt-1.5">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                                  style="background-color: <?= htmlspecialchars($badgeColor) ?>18; color: <?= htmlspecialchars($badgeColor) ?>;">
                                <?= htmlspecialchars($record['document_type_name'] ?? '—') ?>
                            </span>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs font-medium text-gray-500">Public Portal</dt>
                        <dd class="mt-1.5">
                            <?php if (!empty($record['is_public'])): ?>
                                <span class="inline-flex items-center rounded-full bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-700">Published</span>
                            <?php else: ?>
                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">Not published</span>
                            <?php endif; ?>
                        </dd>
                    </div>

                </div>
            </section>

            <!-- Tags -->
            <section class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900">Tags</h2>
                <?php if (!empty($tagList)): ?>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <?php foreach ($tagList as $tag): ?>
                            <a href="<?= BASE_URL ?>/admin/archived-records?search=<?= urlencode($tag) ?>"
                               class="inline-flex items-center rounded-lg bg-blue-50 px-3 py-1.5 text-sm
                                      font-medium text-blue-700 hover:bg-blue-100 transition">
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
            <section class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900">Authors &amp; Sponsors</h2>
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
                                <?php foreach (array_values($mainAuthors) as $rank => $author): ?>
                                    <?php
                                    $name = $author['member_full_name'] ?: $author['custom_author_name'];
                                    $pos  = $author['member_position'] ?? null;
                                    $isCustom = !$author['sp_member_id'];
                                    ?>
                                    <div class="flex items-start gap-3 rounded-lg border border-gray-100 bg-gray-50 px-4 py-3">
                                        <span class="shrink-0 pt-0.5 text-xs font-semibold text-gray-500"><?= $rank + 1 ?>.</span>
                                        <div class="min-w-0">
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
                                <?php foreach (array_values($coAuthors) as $rank => $author): ?>
                                    <?php
                                    $name = $author['member_full_name'] ?: $author['custom_author_name'];
                                    $pos  = $author['member_position'] ?? null;
                                    $isCustom = !$author['sp_member_id'];
                                    ?>
                                    <div class="flex items-start gap-3 rounded-lg border border-gray-100 bg-gray-50 px-4 py-3">
                                        <span class="shrink-0 pt-0.5 text-xs font-semibold text-gray-500"><?= $rank + 1 ?>.</span>
                                        <div class="min-w-0">
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
            <section class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900">Committees</h2>
                <?php if (!empty($committees)): ?>
                    <ul class="mt-3 divide-y divide-gray-100">
                        <?php foreach ($committees as $comm): ?>
                            <?php $commName = $comm['committee_name'] ?: $comm['custom_committee_name']; ?>
                            <li class="py-3 first:pt-0 last:pb-0">
                                <p class="break-words text-sm font-medium text-gray-900"><?= htmlspecialchars($commName) ?></p>
                                <?php if (empty($comm['committee_id'])): ?>
                                    <p class="mt-0.5 text-xs text-gray-500">Custom committee</p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="mt-3 border-t border-gray-100 pt-3">
                        <p class="text-sm text-gray-500">No committees have been assigned to this record.</p>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Attachments -->
            <section class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">
                        Attachments
                        <?php if (!empty($attachments)): ?>
                            <span class="ml-2 inline-flex h-5 min-w-[20px] items-center justify-center rounded-full
                                         bg-gray-100 px-1.5 text-xs font-semibold text-gray-600">
                                <?= count($attachments) ?>
                            </span>
                        <?php endif; ?>
                    </h2>
                    <button type="button" onclick="openUploadModal()"
                            class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2
                                   text-sm font-medium text-gray-700 transition hover:border-primary hover:bg-blue-50
                                   hover:text-primary sm:w-auto">
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
                            <div class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex min-w-0 flex-1 items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-primary">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-gray-900"
                                           title="<?= htmlspecialchars($att['file_name']) ?>">
                                            <?= htmlspecialchars($att['file_name']) ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <?= arFormatFileSize((int) $att['file_size']) ?>
                                            &bull;
                                            <?= htmlspecialchars(date('M j, Y', strtotime($att['uploaded_at']))) ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center justify-end gap-2 sm:ml-3">
                                    <a href="<?= BASE_URL ?>/storage/<?= htmlspecialchars($att['file_path']) ?>"
                                       target="_blank" rel="noopener"
                                       class="rounded-lg border border-gray-200 bg-white px-3 py-1.5
                                              text-xs font-medium text-gray-700 transition hover:bg-gray-100">
                                        Download
                                    </a>
                                    <button type="button"
                                            onclick="openDeleteAttModal(<?= (int) $att['id'] ?>, '<?= addslashes(htmlspecialchars($att['file_name'])) ?>')"
                                            class="rounded-lg border border-gray-200 bg-white px-3 py-1.5
                                                   text-xs font-medium text-red-600 transition hover:border-red-200 hover:bg-red-50"
                                            title="Delete attachment" aria-label="Delete attachment">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="mt-4 py-8 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <p class="mt-2 text-sm text-gray-500">No attachments found</p>
                    </div>
                <?php endif; ?>
            </section>

        </div>

        <!-- ── Sidebar column (1/3) ────────────────────────────────────────── -->
        <div class="space-y-5 lg:space-y-6">

            <!-- Quick info card -->
            <section class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-6">
                <h3 class="text-lg font-semibold text-gray-900">Record Details</h3>
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
            <section class="rounded-2xl border border-red-100 bg-red-50/50 p-4 sm:p-5">
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
     class="fixed inset-0 z-[100] hidden items-center justify-center overflow-hidden"
     role="dialog" aria-modal="true" aria-labelledby="editModalTitle">
    <div id="editModalBackdrop"
         class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm opacity-0 transition-opacity duration-200"></div>
    <div id="editModalPanel"
         class="relative z-10 mx-auto flex max-h-[calc(100dvh_-_1rem)] w-[calc(100%_-_1rem)] max-w-4xl flex-col overflow-hidden rounded-2xl
                border border-gray-100 bg-white shadow-2xl transform scale-95 opacity-0 transition-all duration-200 sm:w-[calc(100%_-_2rem)] sm:max-h-[92vh]">

        <form method="POST" action="<?= BASE_URL ?>/admin/archived-records/update" enctype="multipart/form-data"
              class="flex min-h-0 flex-1 flex-col">
            <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">
            <div data-edit-archive-post></div>

            <!-- Header -->
            <div class="flex shrink-0 items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 sm:px-6 sm:py-5">
                <div class="min-w-0">
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
            <div class="min-h-0 flex-1 space-y-5 overflow-y-auto overflow-x-hidden px-4 py-4 sm:px-6 sm:py-5">

                <!-- Row 1: Approved No + Date -->
                <div class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="min-w-0">
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
                    <div class="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2">
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

                <section class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <label for="edit_is_public" class="flex items-start gap-3">
                        <input type="checkbox" id="edit_is_public" name="is_public" value="1"
                               <?= !empty($record['is_public']) ? 'checked' : '' ?>
                               class="mt-1 h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary">
                        <span>
                            <span class="block text-sm font-semibold text-gray-900">Publish to Public Portal</span>
                            <span class="mt-1 block text-xs leading-relaxed text-gray-700">
                                Publishing exposes this record's public details and all attached files. Confirm each attachment is approved for public release before saving.
                                Adding an attachment automatically unpublishes the record until it is reviewed and published again.
                            </span>
                        </span>
                    </label>
                </section>

                <!-- Existing and custom authors/co-authors -->
                <section class="min-w-0 rounded-xl border border-gray-200 p-3 sm:p-5">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900">Authors / Sponsors</h4>
                            <p class="mt-1 text-xs text-gray-500">Select people by role or add names below. A person cannot be selected in both roles.</p>
                        </div>
                        <button type="button" onclick="addArchiveEntry('editCustomAuthorsList', 'editAuthorEntryTemplate')"
                                class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-primary hover:bg-blue-50">
                            + Add person
                        </button>
                    </div>
                    <div class="grid min-w-0 gap-4 lg:grid-cols-2">
                        <?php foreach ([['AUTHOR', 'Authors / Sponsors'], ['CO_AUTHOR', 'Co-authors / Co-sponsors']] as [$roleValue, $roleLabel]): ?>
                            <?php
                            $selectedMemberIds = array_values(array_map(
                                static fn(array $author): string => (string) $author['sp_member_id'],
                                array_filter($authors, static fn(array $author): bool =>
                                    $author['author_role'] === $roleValue && !empty($author['sp_member_id'])
                                )
                            ));
                            ?>
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-gray-700"><?= $roleLabel ?></label>
                                <div class="edit-multiselect" data-list="members" data-role="<?= $roleValue ?>"
                                     data-selected="<?= htmlspecialchars(json_encode($selectedMemberIds), ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="edit-multi-control">
                                        <div class="edit-multi-chips"></div>
                                        <input type="search" class="edit-multi-search" placeholder="Search <?= strtolower($roleLabel) ?>..."
                                               aria-label="Search <?= htmlspecialchars(strtolower($roleLabel)) ?>" autocomplete="off">
                                        <button type="button" class="edit-multi-toggle" aria-label="Show <?= htmlspecialchars(strtolower($roleLabel)) ?> options">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                    </div>
                                    <div class="edit-multi-dropdown hidden" role="listbox" aria-multiselectable="true">
                                        <label class="edit-multi-select-all">
                                            <input type="checkbox" class="edit-multi-select-all-checkbox">
                                            <span>Select all matching options</span>
                                        </label>
                                        <?php foreach ($spMembers as $member): ?>
                                            <button type="button" class="edit-multi-option" data-id="<?= (int) $member['sp_member_id'] ?>">
                                                <?= htmlspecialchars($member['full_name']) ?><?= !empty($member['position']) ? ' — ' . htmlspecialchars($member['position']) : '' ?>
                                            </button>
                                        <?php endforeach; ?>
                                        <p class="edit-multi-empty hidden">No matching people found.</p>
                                    </div>
                                    <div class="mt-1 flex justify-end">
                                        <button type="button" class="edit-multi-clear hidden text-xs font-medium text-red-600 hover:text-red-700">
                                            Remove selection
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4">
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <p class="text-xs font-medium text-gray-700">Additional people</p>
                        </div>
                        <div id="editCustomAuthorsList" class="space-y-2">
                            <?php foreach ($authors as $author): ?>
                                <?php if (empty($author['sp_member_id']) && !empty($author['custom_author_name'])): ?>
                                    <div class="custom-author-entry grid min-w-0 grid-cols-1 gap-2 rounded-lg bg-gray-50 p-3 sm:grid-cols-[minmax(0,0.7fr)_minmax(0,1.3fr)_auto] sm:items-center">
                                        <select name="edit_custom_author_role[]" aria-label="Person role" class="w-full min-w-0 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
                                            <option value="AUTHOR" <?= $author['author_role'] === 'AUTHOR' ? 'selected' : '' ?>>Author / Sponsor</option>
                                            <option value="CO_AUTHOR" <?= $author['author_role'] === 'CO_AUTHOR' ? 'selected' : '' ?>>Co-author / Co-sponsor</option>
                                        </select>
                                        <input type="text" name="edit_custom_author_name[]" aria-label="Person name" maxlength="200"
                                               value="<?= htmlspecialchars($author['custom_author_name']) ?>" placeholder="Enter person name"
                                               class="w-full min-w-0 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
                                        <button type="button" onclick="removeArchiveEntry(this)" aria-label="Remove person" class="rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50">Remove</button>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <!-- Existing committees and additional names -->
                <section class="min-w-0 rounded-xl border border-gray-200 p-3 sm:p-5">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900">Committees</h4>
                            <p class="mt-1 text-xs text-gray-500">Select committees or add names below.</p>
                        </div>
                        <button type="button" onclick="addArchiveEntry('editCustomCommitteesList', 'editCommitteeEntryTemplate')"
                                class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-primary hover:bg-blue-50">
                            + Add committee
                        </button>
                    </div>
                    <?php
                    $selectedCommitteeIds = array_values(array_map(
                        static fn(array $committee): string => (string) $committee['committee_id'],
                        array_filter($committees, static fn(array $committee): bool => !empty($committee['committee_id']))
                    ));
                    ?>
                    <div class="edit-multiselect" data-list="committees"
                         data-selected="<?= htmlspecialchars(json_encode($selectedCommitteeIds), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="edit-multi-control">
                            <div class="edit-multi-chips"></div>
                            <input type="search" class="edit-multi-search" placeholder="Search committees..."
                                   aria-label="Search committees" autocomplete="off">
                            <button type="button" class="edit-multi-toggle" aria-label="Show committee options">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                        </div>
                        <div class="edit-multi-dropdown hidden" role="listbox" aria-multiselectable="true">
                            <label class="edit-multi-select-all">
                                <input type="checkbox" class="edit-multi-select-all-checkbox">
                                <span>Select all matching options</span>
                            </label>
                            <?php foreach ($allCommittees as $option): ?>
                                <button type="button" class="edit-multi-option" data-id="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['name']) ?></button>
                            <?php endforeach; ?>
                            <p class="edit-multi-empty hidden">No matching committees found.</p>
                        </div>
                        <div class="mt-1 flex justify-end">
                            <button type="button" class="edit-multi-clear hidden text-xs font-medium text-red-600 hover:text-red-700">
                                Remove selection
                            </button>
                        </div>
                    </div>
                    <div class="mt-4">
                        <p class="mb-2 text-xs font-medium text-gray-700">Additional committees</p>
                        <div id="editCustomCommitteesList" class="space-y-2">
                            <?php foreach ($committees as $committee): ?>
                                <?php if (empty($committee['committee_id']) && !empty($committee['custom_committee_name'])): ?>
                                    <div class="custom-committee-entry grid min-w-0 grid-cols-1 gap-2 rounded-lg bg-gray-50 p-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                                        <input type="text" name="edit_custom_committee_name[]" aria-label="Committee name" maxlength="200"
                                               value="<?= htmlspecialchars($committee['custom_committee_name']) ?>" placeholder="Enter committee name"
                                               class="w-full min-w-0 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
                                        <button type="button" onclick="removeArchiveEntry(this)" aria-label="Remove committee" class="rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50">Remove</button>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <!-- Additional attachments -->
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Add More Attachments</label>
                    <label id="editAttachmentDropzone" for="editAttachmentInput" data-attachment-dropzone
                           class="flex h-32 w-full cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed
                                  border-gray-300 bg-gray-50 transition hover:bg-gray-100 focus-within:ring-2 focus-within:ring-primary/20">
                        <div class="flex flex-col items-center justify-center px-3 py-4 text-center">
                            <svg class="mb-2 h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <p id="editAttachmentPrompt" class="mb-1 text-sm text-gray-600">
                                <span class="font-semibold text-gray-700">Click to upload</span> or drag and drop
                            </p>
                            <p class="text-xs text-gray-500">PDF, DOC, DOCX, XLS, XLSX, JPG, PNG (MAX. 25 MB each)</p>
                        </div>
                        <input id="editAttachmentInput" type="file" name="attachments[]" multiple
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp"
                               class="hidden">
                    </label>
                    <p class="mt-1 text-xs text-gray-400">Existing attachments are preserved. Maximum 10 files.</p>
                    <ul id="editAttachmentFileList" class="mt-2 hidden space-y-1.5" aria-live="polite"></ul>
                </div>

            </div>

            <!-- Footer -->
            <div class="flex shrink-0 flex-col-reverse gap-2 border-t border-gray-100 bg-gray-50 px-4 py-3 sm:flex-row sm:justify-end sm:gap-3 sm:px-6 sm:py-4">
                <button type="button" onclick="closeEditModal()"
                        class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium
                               text-gray-700 hover:bg-gray-50 transition sm:w-auto">
                    Cancel
                </button>
                <button type="submit"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2 text-sm font-semibold
                               text-white hover:bg-primary/90 transition sm:w-auto">
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
    <div class="custom-author-entry grid min-w-0 grid-cols-1 gap-2 rounded-lg bg-gray-50 p-3 sm:grid-cols-[minmax(0,0.7fr)_minmax(0,1.3fr)_auto] sm:items-center">
        <select name="edit_custom_author_role[]" aria-label="Person role" class="w-full min-w-0 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm"><option value="AUTHOR">Author / Sponsor</option><option value="CO_AUTHOR">Co-author / Co-sponsor</option></select>
        <input type="text" name="edit_custom_author_name[]" aria-label="Person name" maxlength="200" placeholder="Enter person name" class="w-full min-w-0 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
        <button type="button" onclick="removeArchiveEntry(this)" aria-label="Remove person" class="rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50">Remove</button>
    </div>
</template>
<template id="editCommitteeEntryTemplate">
    <div class="custom-committee-entry grid min-w-0 grid-cols-1 gap-2 rounded-lg bg-gray-50 p-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
        <input type="text" name="edit_custom_committee_name[]" aria-label="Committee name" maxlength="200" placeholder="Enter committee name" class="w-full min-w-0 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
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

<style>
    .edit-multiselect { position: relative; min-width: 0; }
    .edit-multi-control { display: flex; min-width: 0; min-height: 2.75rem; flex-wrap: wrap; align-items: center; gap: .375rem; border: 1px solid #e5e7eb; border-radius: .75rem; background: #fff; padding: .375rem .5rem .375rem .75rem; }
    .edit-multi-control:focus-within { border-color: var(--color-primary, #1d4ed8); box-shadow: 0 0 0 3px rgba(29,78,216,.12); }
    .edit-multi-chips { display: contents; }
    .edit-multi-search { width: 100%; min-width: 0; flex: 1; border: 0; background: transparent; padding: .25rem 0; color: #1f2937; font-size: .875rem; outline: none; }
    .edit-multi-search::placeholder { color: #9ca3af; }
    .edit-multi-toggle { display: flex; height: 1.75rem; width: 1.75rem; flex-shrink: 0; align-items: center; justify-content: center; border: 0; border-radius: .5rem; background: transparent; color: #9ca3af; cursor: pointer; }
    .edit-multi-toggle:hover { background: #f3f4f6; color: #4b5563; }
    .edit-multi-dropdown { position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: 110; max-height: 15rem; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: .75rem; background: #fff; box-shadow: 0 10px 25px -5px rgba(0,0,0,.12), 0 4px 10px -3px rgba(0,0,0,.07); }
    .edit-multi-dropdown.hidden, .edit-multi-option.hidden, .edit-multi-clear.hidden, .edit-multi-empty.hidden { display: none; }
    .edit-multi-select-all { position: sticky; top: 0; z-index: 1; display: flex; align-items: center; gap: .625rem; border-bottom: 1px solid #e5e7eb; background: #fff; padding: .625rem .75rem; color: #374151; font-size: .8125rem; font-weight: 600; cursor: pointer; }
    .edit-multi-select-all-checkbox { height: 1rem; width: 1rem; flex-shrink: 0; accent-color: #2563eb; }
    .edit-multi-option { display: flex; width: 100%; align-items: center; gap: .625rem; border: 0; background: #fff; padding: .625rem .75rem; text-align: left; color: #374151; font-size: .875rem; cursor: pointer; }
    .edit-multi-option::before { display: inline-flex; height: 1rem; width: 1rem; flex-shrink: 0; align-items: center; justify-content: center; border: 1px solid #d1d5db; border-radius: .25rem; background: #fff; color: #fff; content: ''; font-size: .75rem; line-height: 1; }
    .edit-multi-option:hover, .edit-multi-option[aria-selected="true"] { background: #eff6ff; color: #1d4ed8; }
    .edit-multi-option[aria-selected="true"] { font-weight: 600; }
    .edit-multi-option[aria-selected="true"]::before { border-color: #2563eb; background: #2563eb; content: '✓'; }
    .edit-multi-option:disabled { background: #f9fafb; color: #9ca3af; cursor: not-allowed; }
    .edit-multi-chip { display: inline-flex; max-width: 100%; align-items: center; gap: .25rem; border: 1px solid #bfdbfe; border-radius: 9999px; background: #eff6ff; padding: .2rem .35rem .2rem .625rem; color: #1d4ed8; font-size: .75rem; font-weight: 500; }
    .edit-multi-chip-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .edit-multi-chip-remove { display: flex; height: 1rem; width: 1rem; flex-shrink: 0; align-items: center; justify-content: center; border: 0; border-radius: 50%; background: transparent; color: #60a5fa; cursor: pointer; }
    .edit-multi-chip-remove:hover { background: #bfdbfe; color: #1d4ed8; }
    .edit-multi-empty { padding: .75rem; color: #9ca3af; text-align: center; font-size: .8125rem; }
</style>

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
    const entry = button.closest('.custom-author-entry, .custom-committee-entry');
    if (entry) entry.remove();
}

const editArchiveMultiSelects = Array.from(document.querySelectorAll('#editModal .edit-multiselect'));

function editArchiveMemberIsTaken(role, id) {
    return editArchiveMultiSelects.some((widget) =>
        widget.dataset.list === 'members' &&
        widget.dataset.role !== role &&
        widget.archiveSelected.has(id)
    );
}

function renderEditArchiveMultiSelects() {
    editArchiveMultiSelects.forEach((widget) => {
        const selected = widget.archiveSelected;
        const chips = widget.querySelector('.edit-multi-chips');
        const search = widget.querySelector('.edit-multi-search');
        const options = Array.from(widget.querySelectorAll('.edit-multi-option'));
        const selectAll = widget.querySelector('.edit-multi-select-all-checkbox');
        const query = search.value.trim().toLocaleLowerCase();
        chips.replaceChildren();

        selected.forEach((id) => {
            const option = options.find((item) => item.dataset.id === id);
            if (!option) return;
            const chip = document.createElement('span');
            chip.className = 'edit-multi-chip';
            const label = document.createElement('span');
            label.className = 'edit-multi-chip-label';
            label.textContent = option.textContent.trim();
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'edit-multi-chip-remove';
            remove.textContent = '×';
            remove.setAttribute('aria-label', 'Remove ' + label.textContent);
            remove.addEventListener('click', () => {
                selected.delete(id);
                renderEditArchiveMultiSelects();
            });
            chip.append(label, remove);
            chips.appendChild(chip);
        });

        let visibleCount = 0;
        options.forEach((option) => {
            const id = option.dataset.id;
            const isSelected = selected.has(id);
            const isTaken = widget.dataset.list === 'members' &&
                !isSelected && editArchiveMemberIsTaken(widget.dataset.role, id);
            const isVisible = option.textContent.trim().toLocaleLowerCase().includes(query);
            option.classList.toggle('hidden', !isVisible);
            option.disabled = isTaken;
            option.setAttribute('aria-selected', String(isSelected));
            if (isVisible && !isTaken) visibleCount++;
        });
        widget.querySelector('.edit-multi-empty').classList.toggle('hidden', visibleCount > 0);
        widget.querySelector('.edit-multi-clear').classList.toggle('hidden', selected.size === 0);

        const available = options.filter((option) =>
            !option.classList.contains('hidden') && !option.disabled
        );
        const selectedCount = available.filter((option) => selected.has(option.dataset.id)).length;
        selectAll.checked = available.length > 0 && selectedCount === available.length;
        selectAll.indeterminate = selectedCount > 0 && selectedCount < available.length;
        selectAll.disabled = available.length === 0;
    });
}

editArchiveMultiSelects.forEach((widget) => {
    let preselected = [];
    try {
        preselected = JSON.parse(widget.dataset.selected || '[]');
    } catch (error) {
        console.error('Unable to read saved archived record selections.', error);
    }
    widget.archiveSelected = new Set(preselected.map(String));
    const dropdown = widget.querySelector('.edit-multi-dropdown');
    const search = widget.querySelector('.edit-multi-search');
    const toggle = widget.querySelector('.edit-multi-toggle');
    const selectAll = widget.querySelector('.edit-multi-select-all-checkbox');

    widget.querySelectorAll('.edit-multi-option').forEach((option) => {
        option.addEventListener('click', () => {
            if (widget.archiveSelected.has(option.dataset.id)) {
                widget.archiveSelected.delete(option.dataset.id);
            } else if (widget.dataset.list !== 'members' ||
                !editArchiveMemberIsTaken(widget.dataset.role, option.dataset.id)) {
                widget.archiveSelected.add(option.dataset.id);
            }
            renderEditArchiveMultiSelects();
        });
    });

    selectAll.addEventListener('change', () => {
        widget.querySelectorAll('.edit-multi-option:not(.hidden):not(:disabled)').forEach((option) => {
            if (selectAll.checked) widget.archiveSelected.add(option.dataset.id);
            else widget.archiveSelected.delete(option.dataset.id);
        });
        renderEditArchiveMultiSelects();
    });
    widget.querySelector('.edit-multi-clear').addEventListener('click', () => {
        widget.archiveSelected.clear();
        renderEditArchiveMultiSelects();
    });
    search.addEventListener('focus', () => dropdown.classList.remove('hidden'));
    search.addEventListener('input', renderEditArchiveMultiSelects);
    search.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') dropdown.classList.add('hidden');
        if (event.key === 'Enter') {
            event.preventDefault();
            const first = widget.querySelector('.edit-multi-option:not(.hidden):not(:disabled)');
            if (first) first.click();
        }
    });
    toggle.addEventListener('click', () => {
        dropdown.classList.toggle('hidden');
        if (!dropdown.classList.contains('hidden')) search.focus();
    });
});
renderEditArchiveMultiSelects();

document.addEventListener('click', (event) => {
    editArchiveMultiSelects.forEach((widget) => {
        if (!widget.contains(event.target)) widget.querySelector('.edit-multi-dropdown').classList.add('hidden');
    });
});

const editArchiveForm = document.querySelector('#editModal form[action$="/update"]');
if (editArchiveForm) {
    editArchiveForm.addEventListener('submit', () => {
        const postFields = editArchiveForm.querySelector('[data-edit-archive-post]');
        postFields.replaceChildren();
        const addHidden = (name, value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            postFields.appendChild(input);
        };

        editArchiveMultiSelects.forEach((widget) => {
            widget.archiveSelected.forEach((id) => {
                if (widget.dataset.list === 'members') {
                    addHidden('author_role[]', widget.dataset.role);
                    addHidden('author_sp_member_id[]', id);
                    addHidden('author_custom_name[]', '');
                } else {
                    addHidden('committee_id[]', id);
                    addHidden('committee_custom_name[]', '');
                }
            });
        });

        editArchiveForm.querySelectorAll('.custom-author-entry').forEach((entry) => {
            const name = entry.querySelector('[name="edit_custom_author_name[]"]').value.trim();
            if (!name) return;
            addHidden('author_role[]', entry.querySelector('[name="edit_custom_author_role[]"]').value);
            addHidden('author_sp_member_id[]', '');
            addHidden('author_custom_name[]', name);
        });
        editArchiveForm.querySelectorAll('.custom-committee-entry').forEach((entry) => {
            const name = entry.querySelector('[name="edit_custom_committee_name[]"]').value.trim();
            if (!name) return;
            addHidden('committee_id[]', '');
            addHidden('committee_custom_name[]', name);
        });
    });
}

const editAttachmentDropzone = document.getElementById('editAttachmentDropzone');
const editAttachmentInput = document.getElementById('editAttachmentInput');
const editAttachmentFileList = document.getElementById('editAttachmentFileList');
const editAttachmentPrompt = document.getElementById('editAttachmentPrompt');

function renderEditAttachmentFiles() {
    editAttachmentFileList.replaceChildren();
    Array.from(editAttachmentInput.files).forEach((file) => {
        const item = document.createElement('li');
        item.className = 'truncate rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-700';
        item.textContent = file.name + ' · ' + (file.size / 1048576).toFixed(2) + ' MB';
        item.title = file.name;
        editAttachmentFileList.appendChild(item);
    });
    editAttachmentFileList.classList.toggle('hidden', editAttachmentInput.files.length === 0);
    editAttachmentPrompt.innerHTML = editAttachmentInput.files.length
        ? '<span class="font-semibold text-gray-700">' + editAttachmentInput.files.length +
          ' file' + (editAttachmentInput.files.length === 1 ? '' : 's') + ' selected</span> · Click or drop to replace'
        : '<span class="font-semibold text-gray-700">Click to upload</span> or drag and drop';
}

editAttachmentInput.addEventListener('change', renderEditAttachmentFiles);
['dragenter', 'dragover'].forEach((eventName) => {
    editAttachmentDropzone.addEventListener(eventName, (event) => {
        event.preventDefault();
        event.stopPropagation();
        editAttachmentDropzone.classList.add('border-primary', 'bg-blue-50');
    });
});
['dragleave', 'drop'].forEach((eventName) => {
    editAttachmentDropzone.addEventListener(eventName, (event) => {
        event.preventDefault();
        event.stopPropagation();
        editAttachmentDropzone.classList.remove('border-primary', 'bg-blue-50');
    });
});
editAttachmentDropzone.addEventListener('drop', (event) => {
    if (!event.dataTransfer || event.dataTransfer.files.length === 0) return;
    const transfer = new DataTransfer();
    Array.from(event.dataTransfer.files).forEach((file) => transfer.items.add(file));
    editAttachmentInput.files = transfer.files;
    renderEditAttachmentFiles();
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

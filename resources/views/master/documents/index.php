<?php ob_start(); ?>
<?php
$filterType = $filterType ?? '';
$filterStatus = $filterStatus ?? '';
$filterEditedByMe = $filterEditedByMe ?? false;
$documentTypes = $documentTypes ?? [];
$documentStatuses = $documentStatuses ?? [];
?>
<div class="space-y-6">
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="px-6 py-8 sm:px-8">
            <p class="text-sm font-medium text-blue-100">SUPER ADMIN · SYSTEM-WIDE TRACKING</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl"><?= htmlspecialchars($pageTitle) ?></h1>
            <p class="mt-2 text-sm leading-6 text-blue-100"><?= htmlspecialchars($pageSubtitle) ?></p>
        </div>
    </section>

    <?php if ($success): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <section class="grid gap-4 sm:grid-cols-2">
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <p class="text-sm text-gray-500">All system documents</p>
            <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($totalDocuments) ?></p>
        </div>
        <a href="<?= BASE_URL ?>/master/documents?edited_by=me"
            class="rounded-2xl border border-blue-200 bg-blue-50 p-5 transition hover:border-blue-300 hover:bg-blue-100">
            <p class="text-sm text-blue-800">Documents I Modified</p>
            <p class="mt-2 text-3xl font-bold text-blue-900"><?= number_format($modifiedDocumentsCount) ?></p>
            <p class="mt-2 text-xs text-blue-700">View your document corrections</p>
        </a>
    </section>

    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
        <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900">Document Registry</h2>
                <p class="mt-1 text-xs text-gray-500"><?= number_format($totalRows) ?> record<?= $totalRows !== 1 ? 's' : '' ?> found</p>
            </div>
            <form method="GET" action="<?= BASE_URL ?>/master/documents" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                <div class="relative w-full sm:w-72">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search tracking no., subject, source..."
                        class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </div>
                <label class="sr-only" for="registry-type-filter">Document type</label>
                <select id="registry-type-filter" name="type"
                    class="w-full min-w-0 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 sm:w-48">
                    <option value="">All Document Types</option>
                    <?php foreach ($documentTypes as $type): ?>
                        <option value="<?= (int)$type['id'] ?>" <?= $filterType === (string)$type['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($type['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label class="sr-only" for="registry-status-filter">Document status</label>
                <select id="registry-status-filter" name="status"
                    class="w-full min-w-0 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 sm:w-48">
                    <option value="">All Statuses</option>
                    <?php foreach ($documentStatuses as $status): ?>
                        <option value="<?= (int)$status['id'] ?>" <?= $filterStatus === (string)$status['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($status['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                    Filter
                </button>
                <?php if ($search !== '' || $filterType !== '' || $filterStatus !== '' || $filterEditedByMe): ?>
                    <a href="<?= BASE_URL ?>/master/documents"
                       class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-center text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Tracking Number</th>
                        <th class="px-6 py-3 font-medium">Subject Matter / Type</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Division</th>
                        <th class="px-6 py-3 font-medium">Date Routed</th>
                        <th class="px-6 py-3 font-medium">Routed By</th>
                        <th class="px-6 py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if ($documents === []): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-16 text-center">
                                <p class="text-sm font-medium text-gray-700">No documents found.</p>
                                <p class="mt-1 text-xs text-gray-500">Try changing your search terms.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($documents as $document): ?>
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="whitespace-nowrap px-6 py-4">
                                    <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($document['tracking_number']) ?></p>
                                    <p class="mt-1 text-xs text-gray-500">Received: <?= htmlspecialchars(date('M j, Y', strtotime($document['date_received']))) ?></p>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="max-w-xs">
                                        <p class="line-clamp-2 text-sm font-medium text-gray-900"><?= htmlspecialchars($document['subject_matter']) ?></p>
                                        <?php $typeBadgeColor = $document['document_type_badge_color'] ?: '#2563EB'; ?>
                                        <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                            style="background-color: <?= htmlspecialchars($typeBadgeColor, ENT_QUOTES, 'UTF-8') ?>1a; color: <?= htmlspecialchars($typeBadgeColor, ENT_QUOTES, 'UTF-8') ?>;">
                                            <?= htmlspecialchars($document['document_type_name'] ?? 'No type assigned') ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <?php $statusBadgeColor = $document['status_badge_color'] ?: '#6B7280'; ?>
                                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold text-white shadow-sm"
                                        style="background-color: <?= htmlspecialchars($statusBadgeColor, ENT_QUOTES, 'UTF-8') ?>; border-color: <?= htmlspecialchars($statusBadgeColor, ENT_QUOTES, 'UTF-8') ?>;">
                                        <?= htmlspecialchars($document['status_name'] ?? '—') ?>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="inline-flex items-center rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-700">
                                        <?= htmlspecialchars(match ($document['current_phase']) {
                                            'RECEIVING' => 'Receiving',
                                            'ADMIN' => 'Admin',
                                            'SP_SECRETARY' => 'SP Secretary',
                                            'PLENARY' => 'Plenary',
                                            'COMMITTEE' => 'Committee',
                                            'FINALIZED' => 'Finalized',
                                            'FILED' => 'Filed',
                                            default => str_replace('_', ' ', $document['current_phase']),
                                        }) ?>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <?php if (!empty($document['routed_at'])): ?>
                                        <p class="text-sm text-gray-900"><?= htmlspecialchars(date('M j, Y', strtotime($document['routed_at']))) ?></p>
                                        <p class="text-xs text-gray-500"><?= htmlspecialchars(date('g:i A', strtotime($document['routed_at']))) ?></p>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">No routing yet</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <?= htmlspecialchars($document['routed_by_username'] ?? '—') ?>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    <a href="<?= BASE_URL ?>/master/documents/edit?id=<?= (int)$document['id'] ?>"
                                        class="inline-flex items-center justify-center rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:border-blue-400 hover:bg-blue-50 hover:text-blue-600 transition"
                                        title="Review or edit document" aria-label="Review or edit document">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
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
                    (<?= number_format($totalRows) ?> total records)
                </p>
                <div class="flex items-center gap-1">
                    <?php
                    $query = [];
                    if ($search !== '') $query['search'] = $search;
                    if ($filterType !== '') $query['type'] = $filterType;
                    if ($filterStatus !== '') $query['status'] = $filterStatus;
                    if ($filterEditedByMe) $query['edited_by'] = 'me';
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
<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

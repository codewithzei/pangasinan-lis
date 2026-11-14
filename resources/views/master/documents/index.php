<?php ob_start(); ?>
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
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
            <p class="text-sm text-amber-800">Documents missing a type</p>
            <p class="mt-2 text-3xl font-bold text-amber-900"><?= number_format($missingTypeCount) ?></p>
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
        <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900">Document records</h2>
                <p class="mt-1 text-xs text-gray-500"><?= number_format($totalRows) ?> record<?= $totalRows !== 1 ? 's' : '' ?> found</p>
            </div>
            <form method="GET" action="<?= BASE_URL ?>/master/documents" class="flex flex-col gap-3 sm:flex-row">
                <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Tracking no., subject, source, type"
                    class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 sm:w-72">
                <button type="submit" class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Search</button>
                <?php if ($search !== ''): ?>
                    <a href="<?= BASE_URL ?>/master/documents" class="rounded-xl border border-gray-200 px-4 py-2 text-center text-sm font-medium text-gray-600 hover:bg-gray-50">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th class="px-6 py-3">Tracking number</th>
                        <th class="px-6 py-3">Document details</th>
                        <th class="px-6 py-3">Source</th>
                        <th class="px-6 py-3">Workflow</th>
                        <th class="px-6 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if ($documents === []): ?>
                        <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">No documents found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($documents as $document): ?>
                            <tr class="hover:bg-gray-50/70">
                                <td class="whitespace-nowrap px-6 py-4">
                                    <p class="font-semibold text-gray-900"><?= htmlspecialchars($document['tracking_number']) ?></p>
                                    <p class="mt-1 text-xs text-gray-500"><?= htmlspecialchars($document['date_received']) ?></p>
                                </td>
                                <td class="max-w-md px-6 py-4">
                                    <p class="font-medium text-gray-800"><?= htmlspecialchars($document['document_type_name'] ?? 'Type not set') ?></p>
                                    <p class="mt-1 line-clamp-2 text-sm text-gray-500"><?= htmlspecialchars($document['subject_matter']) ?></p>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <p><?= htmlspecialchars($document['source_type_name'] ?? '—') ?></p>
                                    <p class="mt-1 text-xs text-gray-500"><?= htmlspecialchars($document['source_name'] ?? '—') ?></p>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($document['status_name'] ?? '—') ?></p>
                                    <p class="mt-1 text-xs text-gray-500"><?= htmlspecialchars(str_replace('_', ' ', $document['current_phase'])) ?></p>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    <a href="<?= BASE_URL ?>/master/documents/edit?id=<?= (int)$document['id'] ?>"
                                        class="inline-flex items-center rounded-lg border border-blue-200 px-3 py-2 text-sm font-semibold text-primary hover:bg-blue-50">
                                        Review / Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
            <div class="flex items-center justify-between border-t border-gray-100 px-6 py-4">
                <span class="text-sm text-gray-500">Page <?= $page ?> of <?= $totalPages ?></span>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="<?= BASE_URL ?>/master/documents?<?= http_build_query(['search' => $search, 'page' => $page - 1]) ?>" class="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Previous</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?= BASE_URL ?>/master/documents?<?= http_build_query(['search' => $search, 'page' => $page + 1]) ?>" class="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

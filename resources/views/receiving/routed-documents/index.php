<?php
ob_start();

$success = $success ?? null;
$error = $error ?? null;
$pageTitle = $pageTitle ?? 'Routed Documents';
$pageSubtitle = $pageSubtitle ?? '';
$totalDocuments = $totalDocuments ?? 0;
$pendingDocuments = $pendingDocuments ?? 0;
$routedDocuments = $routedDocuments ?? 0;
$totalRows = $totalRows ?? 0;
$search = $search ?? '';
$filterStatus = $filterStatus ?? '';
$filterType = $filterType ?? '';
$filterDivision = $filterDivision ?? '';
$documents = $documents ?? [];
$documentStatuses = $documentStatuses ?? [];
$divisions = $divisions ?? [];
$totalPages = $totalPages ?? 1;
$page = $page ?? 1;

// Helper function to format phase names
function formatPhaseName(string $phase): string
{
    $phaseMap = [
        'RECEIVING' => 'Receiving',
        'ADMIN' => 'Admin',
        'SP_SECRETARY' => 'SP Secretary',
        'PLENARY' => 'Plenary',
        'COMMITTEE' => 'Committee',
        'FINALIZED' => 'Finalized',
        'FILED' => 'Filed',
    ];
    return $phaseMap[$phase] ?? $phase;
}
?>

<div class="space-y-6">

    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-blue-700 shadow-md">
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
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
        </div>
    </section>

    <?php if (isset($success) && $success): ?>
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

    <?php if (isset($error) && $error): ?>
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

    <section>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Total Documents</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($totalDocuments) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-primary">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-blue-50 px-2 py-1 font-medium text-primary">All documents</span>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Pending Documents</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($pendingDocuments) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-amber-50 px-2 py-1 font-medium text-amber-600">Awaiting action</span>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Routed Documents</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900"><?= number_format($routedDocuments) ?></p>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-emerald-50 px-2 py-1 font-medium text-emerald-600">In workflow</span>
                </div>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-gray-200 bg-white">
        <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900">Documents List</h2>
                <p class="mt-1 text-xs text-gray-500">
                    <?= $totalRows ?> record<?= $totalRows !== 1 ? 's' : '' ?> found
                </p>
            </div>
            <form method="GET" action="<?= BASE_URL ?>/receiving/routed-documents" class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search tracking # or subject..."
                        class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 lg:w-64">
                </div>
                <select name="status" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">All Status</option>
                    <?php foreach ($documentStatuses as $status): ?>
                        <option value="<?= $status['id'] ?>" <?= $filterStatus == $status['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($status['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="division" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">All Divisions</option>
                    <?php foreach ($divisions as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $filterDivision === $key ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                    Filter
                </button>
                <?php if ($search !== '' || $filterStatus !== '' || $filterDivision !== ''): ?>
                    <a href="<?= BASE_URL ?>/receiving/routed-documents"
                       class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition text-center">
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
                        <th class="px-6 py-3 font-medium">Subject Matter / Document Type</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Division</th>
                        <th class="px-6 py-3 font-medium text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($documents)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">No documents found.</p>
                                <p class="mt-1 text-xs text-gray-500">Try adjusting your filters or search criteria.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($documents as $doc): ?>
                            <?php 
                            $statusBadgeColor = !empty($doc['status_badge_color']) ? $doc['status_badge_color'] : '#6B7280';
                            ?>
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($doc['tracking_number']) ?></div>
                                    <div class="mt-0.5 text-xs text-gray-500">
                                        <?= htmlspecialchars(date('M j, Y', strtotime($doc['date_received']))) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="max-w-md">
                                        <div class="text-sm font-medium text-gray-900 line-clamp-2">
                                            <?= htmlspecialchars($doc['subject_matter']) ?>
                                        </div>
                                        <div class="mt-1">
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                                  style="background-color: <?= htmlspecialchars($doc['document_type_badge_color'] ?? '#2563EB') ?>1a;
                                                         color: <?= htmlspecialchars($doc['document_type_badge_color'] ?? '#2563EB') ?>;">
                                                <?= htmlspecialchars($doc['document_type_name']) ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold shadow-sm" 
                                          style="background-color: <?= htmlspecialchars($statusBadgeColor) ?>; border-color: <?= htmlspecialchars($statusBadgeColor) ?>; color: white;">
                                        <?= htmlspecialchars($doc['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-lg bg-gray-50 border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700">
                                        <?= htmlspecialchars(formatPhaseName($doc['current_phase'])) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="<?= BASE_URL ?>/receiving/routed-documents/show?id=<?= $doc['id'] ?>"
                                       class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:border-primary hover:bg-blue-50 hover:text-primary transition"
                                       title="View Details">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
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

        <?php if ($totalPages > 1): ?>
        <div class="flex flex-col items-center justify-between gap-3 border-t border-gray-100 px-6 py-4 sm:flex-row">
            <p class="text-xs text-gray-500">
                Showing page <span class="font-medium text-gray-700"><?= $page ?></span> of
                <span class="font-medium text-gray-700"><?= $totalPages ?></span>
                (<?= $totalRows ?> total records)
            </p>
            <div class="flex items-center gap-1">
                <?php
                $query = [];
                if ($search !== '') $query['search'] = $search;
                if ($filterStatus !== '') $query['status'] = $filterStatus;
                if ($filterDivision !== '') $query['division'] = $filterDivision;
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
?>

<?php
/**
 * Committee Communications — Index / Listing
 *
 * Variables supplied by CommitteeCommunicationsController::index():
 *   $communications      array   committee_communications rows with document joins
 *   $page                int
 *   $totalPages          int
 *   $total               int     total rows matching current filter
 *   $search              string
 *   $totalCommunications int     all communications created by this user (unfiltered)
 *   $success             string|null
 *   $error               string|null
 *   $errors              array
 */

$communications      = $communications      ?? [];
$page                = $page                ?? 1;
$totalPages          = $totalPages          ?? 1;
$total               = $total               ?? 0;
$search              = $search              ?? '';
$totalCommunications = $totalCommunications ?? 0;
$success             = $success             ?? null;
$error               = $error               ?? null;
$errors              = $errors              ?? [];

ob_start();
?>

<div class="space-y-6">

    <!-- Page header ---------------------------------------------------------->
    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10 max-w-3xl">
                <p class="text-sm font-medium text-blue-100">COMMITTEE / COMMUNICATIONS</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    Communications
                </h1>
                <p class="mt-2 max-w-xl text-sm leading-6 text-blue-100">
                    Communication documents you have logged for Committee review, hearing, and reporting.
                </p>
            </div>
            <!-- Stat pill -->
            <div class="relative z-10 mt-5">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1 text-xs font-semibold text-white">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0
                                 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <?= $totalCommunications ?> total communication<?= $totalCommunications !== 1 ? 's' : '' ?>
                </span>
            </div>
            <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>
        </div>
    </section>

    <!-- Flash messages ------------------------------------------------------->
    <?php if ($success): ?>
        <div class="flex items-start gap-3 rounded-2xl border border-green-200 bg-green-50 p-4">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-green-100">
                <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <p class="mt-1.5 text-sm font-medium text-green-800"><?= htmlspecialchars($success) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-red-100">
                <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </div>
            <p class="mt-1.5 text-sm font-medium text-red-800"><?= htmlspecialchars($error) ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="rounded-2xl border border-red-200 bg-red-50 p-4">
            <p class="text-sm font-semibold text-red-800">Please correct the following errors:</p>
            <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-red-700">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Communications list card --------------------------------------------->
    <section class="rounded-2xl border border-gray-200 bg-white">

        <!-- Toolbar: title + record count (left) | search form (right) -->
        <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900">Communications List</h2>
                <p class="mt-1 text-xs text-gray-500">
                    <?php if ($search !== ''): ?>
                        <?= $total ?> result<?= $total !== 1 ? 's' : '' ?> for
                        "<span class="font-medium text-gray-700"><?= htmlspecialchars($search) ?></span>"
                    <?php else: ?>
                        <?= $total ?> communication<?= $total !== 1 ? 's' : '' ?>
                    <?php endif; ?>
                </p>
            </div>
            <form method="GET" action="<?= BASE_URL ?>/committee/communications"
                  class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text"
                           name="search"
                           value="<?= htmlspecialchars($search) ?>"
                           placeholder="Search tracking no., subject, sender…"
                           class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm
                                  text-gray-800 placeholder-gray-400
                                  focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20
                                  sm:w-72">
                </div>
                <button type="submit"
                        class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium
                               text-gray-700 hover:bg-gray-50 transition">
                    Search
                </button>
                <?php if ($search !== ''): ?>
                    <a href="<?= BASE_URL ?>/committee/communications"
                       class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium
                              text-gray-700 hover:bg-gray-50 transition text-center">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Table ------------------------------------------------------------>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-medium">Tracking No.</th>
                        <th class="px-6 py-3 font-medium">Subject</th>
                        <th class="px-6 py-3 font-medium">Sender</th>
                        <th class="px-6 py-3 font-medium">Date Logged</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Progress</th>
                        <th class="px-6 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">

                    <?php if (empty($communications)): ?>
                        <!-- Empty state (in-table) -->
                        <tr>
                            <td colspan="7" class="px-6 py-16 text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                              d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0
                                                 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <?php if ($search !== ''): ?>
                                    <p class="mt-4 text-sm font-medium text-gray-700">No communications found.</p>
                                    <p class="mt-1 text-xs text-gray-500">
                                        No results for "<?= htmlspecialchars($search) ?>".
                                        <a href="<?= BASE_URL ?>/committee/communications"
                                           class="text-primary hover:underline">Clear search</a>
                                    </p>
                                <?php else: ?>
                                    <p class="mt-4 text-sm font-medium text-gray-700">No communications yet.</p>
                                    <p class="mt-1 text-xs text-gray-500">
                                        Communications you log from your inbox will appear here.
                                    </p>
                                    <a href="<?= BASE_URL ?>/committee/inbox"
                                       class="mt-5 inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2
                                              text-sm font-semibold text-white hover:bg-blue-700 transition">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2
                                                     2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414
                                                     2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414
                                                     -2.414A1 1 0 006.586 13H4"/>
                                        </svg>
                                        Go to Inbox
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>

                    <?php else: ?>
                        <?php foreach ($communications as $comm):
                            $hasAgenda  = !empty($comm['agenda_id']);
                            $hasHearing = !empty($comm['hearing_id']);
                            $hasReport  = !empty($comm['report_id']);
                            $step       = 1 + (int)$hasAgenda + (int)$hasHearing + (int)$hasReport;
                            $sBadge     = $comm['status_badge_color'] ?? '#2563EB';
                        ?>
                            <tr class="hover:bg-gray-50/50 transition">

                                <!-- Tracking number -->
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="font-mono text-xs font-bold text-primary">
                                        <?= htmlspecialchars($comm['tracking_number'] ?? '') ?>
                                    </span>
                                </td>

                                <!-- Subject + document-type badge -->
                                <td class="px-6 py-4 max-w-xs">
                                    <div class="font-medium text-gray-900 truncate"
                                         title="<?= htmlspecialchars($comm['subject'] ?? '') ?>">
                                        <?= htmlspecialchars(mb_strimwidth($comm['subject'] ?? '', 0, 60, '…')) ?>
                                    </div>
                                    <?php if (!empty($comm['document_type_name'])): ?>
                                        <?php $dtBadge = $comm['document_type_badge_color'] ?? '#2563EB'; ?>
                                        <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                              style="background-color:<?= htmlspecialchars($dtBadge) ?>1a;
                                                     color:<?= htmlspecialchars($dtBadge) ?>;">
                                            <?= htmlspecialchars($comm['document_type_name']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Sender -->
                                <td class="px-6 py-4 max-w-[200px]">
                                    <div class="truncate text-gray-600"
                                         title="<?= htmlspecialchars($comm['sender_details'] ?? '') ?>">
                                        <?= htmlspecialchars(mb_strimwidth($comm['sender_details'] ?? '', 0, 50, '…')) ?>
                                    </div>
                                </td>

                                <!-- Date logged -->
                                <td class="whitespace-nowrap px-6 py-4 text-gray-600">
                                    <?= htmlspecialchars(
                                        !empty($comm['date_logged'])
                                            ? date('M j, Y', strtotime($comm['date_logged']))
                                            : '—'
                                    ) ?>
                                </td>

                                <!-- Document status badge -->
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                          style="background-color:<?= htmlspecialchars($sBadge) ?>1a;
                                                 color:<?= htmlspecialchars($sBadge) ?>;">
                                        <?= htmlspecialchars($comm['status'] ?? '—') ?>
                                    </span>
                                </td>

                                <!-- Four-step workflow progress indicator -->
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-1" title="Step <?= $step ?> of 4">
                                        <?php
                                        $stepLabels = ['Logged', 'Agenda', 'Hearing', 'Report'];
                                        foreach ($stepLabels as $i => $label):
                                            $done = $i < $step;
                                        ?>
                                            <div class="group relative">
                                                <div class="h-2.5 w-2.5 rounded-full transition
                                                            <?= $done ? 'bg-primary' : 'bg-gray-200' ?>">
                                                </div>
                                                <span class="pointer-events-none absolute -top-7 left-1/2 -translate-x-1/2
                                                             whitespace-nowrap rounded bg-gray-800 px-1.5 py-0.5
                                                             text-xs text-white opacity-0 group-hover:opacity-100 transition">
                                                    <?= $label ?>
                                                </span>
                                            </div>
                                            <?php if ($i < 3): ?>
                                                <div class="h-px w-3 <?= ($i + 1 < $step) ? 'bg-primary/40' : 'bg-gray-200' ?>"></div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                        <span class="ml-2 text-xs text-gray-400"><?= $step ?>/4</span>
                                    </div>
                                </td>

                                <!-- View action -->
                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    <a href="<?= BASE_URL ?>/committee/communications/show?id=<?= (int)$comm['id'] ?>"
                                       class="rounded-lg border border-gray-200 p-1.5 inline-flex items-center gap-1.5
                                              text-gray-500 hover:border-primary hover:bg-blue-50 hover:text-primary transition"
                                       title="View">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943
                                                     9.542 7-1.274 4.057-5.064 7-9.542 7-4.477
                                                     0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        <span class="text-xs font-medium">View</span>
                                    </a>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </tbody>
            </table>
        </div>

        <!-- Pagination ------------------------------------------------------->
        <?php if ($totalPages > 1): ?>
            <?php
            $query = [];
            if ($search !== '') $query['search'] = $search;
            $queryString = !empty($query) ? '&' . http_build_query($query) : '';
            ?>
            <div class="flex flex-col items-center justify-between gap-3 border-t border-gray-100 px-6 py-4 sm:flex-row">
                <p class="text-xs text-gray-500">
                    Showing page <span class="font-medium text-gray-700"><?= $page ?></span> of
                    <span class="font-medium text-gray-700"><?= $totalPages ?></span>
                    (<?= $total ?> total record<?= $total !== 1 ? 's' : '' ?>)
                </p>
                <div class="flex items-center gap-1">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?><?= $queryString ?>"
                           class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium
                                  text-gray-700 hover:bg-gray-50 transition">
                            Prev
                        </a>
                    <?php endif; ?>

                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage   = min($totalPages, $page + 2);
                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                        <a href="?page=<?= $i ?><?= $queryString ?>"
                           class="rounded-lg border px-3 py-1.5 text-sm font-medium transition
                                  <?= $i === $page
                                      ? 'border-primary bg-primary text-white'
                                      : 'border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?><?= $queryString ?>"
                           class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium
                                  text-gray-700 hover:bg-gray-50 transition">
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

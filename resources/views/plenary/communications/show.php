<?php
/**
 * Plenary Communications — Detail view for a Noted Communication
 *
 * Variables supplied by PlenaryCommController::show():
 *   $document        array    Full document row with joins
 *   $firstReading    array    plenary_comm_first_reading row (decision = NOTED)
 *   $routes          array    document_routes history
 *   $pageTitle       string
 *   $success         string|null
 *   $error           string|null
 */

$document     = $document     ?? [];
$firstReading = $firstReading ?? [];
$routes       = $routes       ?? [];

$documentId = (int) ($document['id'] ?? 0);
$decision   = $firstReading['decision'] ?? '';

ob_start();
?>

<div class="space-y-6">

    <?php if (!empty($success)): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Back + title -->
    <div class="flex items-center gap-4">
        <a href="<?= BASE_URL ?>/plenary/communications"
           class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Plenary / Communications</p>
            <h1 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($document['tracking_number'] ?? '') ?></h1>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <!-- Left: Document details -->
        <div class="lg:col-span-1 space-y-4">

            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">Communication</h2>
                <div>
                    <p class="text-xs text-gray-400">Tracking Number</p>
                    <p class="text-sm font-mono font-medium text-gray-900"><?= htmlspecialchars($document['tracking_number'] ?? '') ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Date Received</p>
                    <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars(date('F j, Y', strtotime($document['date_received']))) ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Subject Matter</p>
                    <p class="text-sm text-gray-800 leading-5"><?= htmlspecialchars($document['subject_matter'] ?? '') ?></p>
                </div>
                <?php if (!empty($document['status'])): ?>
                <div>
                    <p class="text-xs text-gray-400">Status</p>
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold text-white"
                          style="background-color: <?= htmlspecialchars($document['status_badge_color'] ?? '#6B7280') ?>;">
                        <?= htmlspecialchars($document['status']) ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <!-- First Reading summary -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">Plenary First Reading</h2>
                <div>
                    <p class="text-xs text-gray-400">Decision</p>
                    <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                        Noted
                    </span>
                </div>
                <?php if (!empty($firstReading['reading_date'])): ?>
                <div>
                    <p class="text-xs text-gray-400">Reading Date</p>
                    <p class="text-sm text-gray-800"><?= htmlspecialchars(date('F j, Y', strtotime($firstReading['reading_date']))) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($firstReading['communication_category_name'])): ?>
                <div>
                    <p class="text-xs text-gray-400">Communication Category</p>
                    <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($firstReading['communication_category_name']) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($firstReading['performed_by_username'])): ?>
                <div>
                    <p class="text-xs text-gray-400">Noted By</p>
                    <p class="text-sm text-gray-800"><?= htmlspecialchars($firstReading['performed_by_username']) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($firstReading['remarks'])): ?>
                <div>
                    <p class="text-xs text-gray-400">Remarks</p>
                    <p class="text-sm text-gray-800 leading-5"><?= htmlspecialchars($firstReading['remarks']) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Route history -->
        <div class="lg:col-span-2 space-y-4">
            <?php if (!empty($routes)): ?>
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-600">Routing History</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-2 font-medium">From</th>
                                <th class="px-4 py-2 font-medium">To</th>
                                <th class="px-4 py-2 font-medium">Route</th>
                                <th class="px-4 py-2 font-medium">By</th>
                                <th class="px-4 py-2 font-medium">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($routes as $route): ?>
                            <tr class="text-xs text-gray-700">
                                <td class="px-4 py-2"><?= htmlspecialchars($route['from_phase'] ?? '—') ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($route['to_phase'] ?? '—') ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($route['routing_option_name'] ?? '—') ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($route['routed_by_username'] ?? '—') ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($route['created_at'] ?? 'now'))) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Back link -->
            <div class="flex items-center gap-3">
                <a href="<?= BASE_URL ?>/plenary/communications"
                   class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5
                          text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Communications
                </a>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

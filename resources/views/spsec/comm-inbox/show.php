<?php
/**
 * SP Secretary — Communications Inbox — Document detail
 *
 * Variables:
 *   $document       array
 *   $inboxItem      array|false
 *   $firstReading   array|false
 *   $committees     array
 *   $resFiles       array
 *   $routes         array
 *   $pageTitle      string
 *   $success        string|null
 *   $error          string|null
 */

$document     = $document     ?? [];
$inboxItem    = $inboxItem    ?? false;
$firstReading = $firstReading ?? false;
$committees   = $committees   ?? [];
$resFiles     = $resFiles     ?? [];
$routes       = $routes       ?? [];

$documentId     = (int) ($document['id'] ?? 0);
$trackingNumber = $document['tracking_number'] ?? '';

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
        <a href="<?= BASE_URL ?>/spsec/comm-inbox"
           class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">SP Secretary / Communications Inbox</p>
            <h1 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($trackingNumber) ?></h1>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <!-- Left: Document info -->
        <div class="lg:col-span-1 space-y-4">

            <!-- Communication details — date and subject only (no author) -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">Communication Details</h2>
                <div>
                    <p class="text-xs text-gray-400">Communication Date</p>
                    <p class="text-sm font-medium text-gray-900">
                        <?= htmlspecialchars(date('F j, Y', strtotime($document['date_received']))) ?>
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Subject Matter</p>
                    <p class="text-sm text-gray-800"><?= htmlspecialchars($document['subject_matter'] ?? '') ?></p>
                </div>
                <?php if (!empty($document['status'])): ?>
                <div>
                    <p class="text-xs text-gray-400">Status</p>
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                          style="background-color: <?= htmlspecialchars($document['status_badge_color'] ?? '#6B7280') ?>1a; color: <?= htmlspecialchars($document['status_badge_color'] ?? '#6B7280') ?>;">
                        <?= htmlspecialchars($document['status']) ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <!-- First Reading result (if already processed) -->
            <?php if ($firstReading): ?>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-emerald-700">First Reading Decision</h2>
                <div>
                    <p class="text-xs text-emerald-600">Decision</p>
                    <p class="text-sm font-bold text-emerald-900"><?= htmlspecialchars($firstReading['decision']) ?></p>
                </div>
                <div>
                    <p class="text-xs text-emerald-600">Reading Date</p>
                    <p class="text-sm text-emerald-800"><?= htmlspecialchars(date('F j, Y', strtotime($firstReading['reading_date']))) ?></p>
                </div>
                <?php if (!empty($firstReading['communication_category_name'])): ?>
                <div>
                    <p class="text-xs text-emerald-600">Category</p>
                    <p class="text-sm text-emerald-800"><?= htmlspecialchars($firstReading['communication_category_name']) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($firstReading['resolution_number'])): ?>
                <div>
                    <p class="text-xs text-emerald-600">Resolution Number</p>
                    <p class="text-sm font-semibold text-emerald-900"><?= htmlspecialchars($firstReading['resolution_number']) ?></p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>

        <!-- Right: Actions + info -->
        <div class="lg:col-span-2 space-y-4">

            <!-- Send to First Reading action -->
            <?php if (!$inboxItem || empty($inboxItem['sent_to_fr_at'])): ?>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6">
                <h2 class="text-base font-semibold text-amber-900 mb-2">Send to First Reading</h2>
                <p class="text-sm text-amber-700 mb-4">
                    Send this communication document to the First Reading queue where a decision
                    (Noted, Referred, or Approved for Resolution) will be recorded.
                </p>
                <form method="POST" action="<?= BASE_URL ?>/spsec/comm-inbox/send-to-fr"
                      onsubmit="return confirm('Send this communication to the First Reading queue? This action cannot be undone.')">
                    <input type="hidden" name="document_id" value="<?= $documentId ?>">
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-amber-700 transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h10M7 12h10m-3 5H7"/>
                        </svg>
                        Send to First Reading
                    </button>
                </form>
            </div>
            <?php else: ?>
            <div class="rounded-2xl border border-emerald-200 bg-white p-5">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Sent to First Reading</p>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars(date('F j, Y g:i A', strtotime($inboxItem['sent_to_fr_at']))) ?></p>
                    </div>
                    <div class="ml-auto">
                        <a href="<?= BASE_URL ?>/spsec/comm-first-reading"
                           class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 transition">
                            Go to First Reading
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Committee assignments (when referred) -->
            <?php if (!empty($committees)): ?>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">Referred to Committees</h2>
                <ul class="space-y-1">
                    <?php foreach ($committees as $c): ?>
                        <li class="inline-flex mr-2 mb-1 rounded-full bg-violet-50 px-3 py-1 text-xs font-medium text-violet-700">
                            <?= htmlspecialchars($c['committee_name']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Resolution files -->
            <?php if (!empty($resFiles)): ?>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">Resolution Files</h2>
                <ul class="space-y-2">
                    <?php foreach ($resFiles as $rf): ?>
                        <li class="flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50 px-4 py-2.5">
                            <svg class="h-5 w-5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                            <div class="flex-1 min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900"><?= htmlspecialchars($rf['original_filename']) ?></p>
                                <p class="text-xs text-gray-400"><?= number_format($rf['file_size'] / 1024, 1) ?> KB</p>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Route history -->
            <?php if (!empty($routes)): ?>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">Route History</h2>
                <div class="space-y-2">
                    <?php foreach ($routes as $r): ?>
                        <div class="flex items-start gap-3 border-b border-gray-50 pb-2 last:border-0 last:pb-0">
                            <div class="h-2 w-2 mt-1.5 rounded-full bg-gray-300 shrink-0"></div>
                            <div>
                                <p class="text-xs text-gray-500">
                                    <?= htmlspecialchars($r['from_phase'] ?? '') ?> → <?= htmlspecialchars($r['to_phase'] ?? '') ?>
                                    <?php if (!empty($r['routing_option_name'])): ?>
                                        <span class="text-gray-400">(<?= htmlspecialchars($r['routing_option_name']) ?>)</span>
                                    <?php endif; ?>
                                </p>
                                <p class="text-xs text-gray-400"><?= htmlspecialchars(date('M j, Y', strtotime($r['created_at']))) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

<?php
/**
 * Plenary Petitions — Detail view for a Noted Petition
 *
 * Variables supplied by PlenaryPetitionsController::show():
 *   $document        array    Full document row with joins
 *   $firstReading    array    plenary_cases_first_reading row (decision = NOTED)
 *   $routes          array    document_routes history
 *   $pageTitle       string
 *   $success         string|null
 *   $error           string|null
 */

$document     = $document     ?? [];
$firstReading = $firstReading ?? [];
$routes       = $routes       ?? [];

$documentId = (int) ($document['id'] ?? 0);

ob_start();
?>

<div class="detail-page space-y-6">
    <?php require __DIR__ . '/../../partials/source-information.php'; ?>



    <?php if (!empty($success)): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Back + title -->
    <div class="flex items-center gap-4">
        <a href="<?= BASE_URL ?>/plenary/petitions"
           class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition"
           aria-label="Back to Petitions">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Plenary / Petitions</p>
            <h1 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($document['tracking_number'] ?? '') ?></h1>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <!-- Left: Document details -->
        <div class="lg:col-span-1 space-y-4">

            <!-- Document info -->
            <?php
// MFR relationship links — Petitions show related MFRs filed against this Case
$mfrDetailRoute = 'plenary/petitions';
require __DIR__ . '/../../partials/resubmission-relationship.php';
require __DIR__ . '/../../partials/mfr-relationship.php';
?>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">Petition</h2>
                <div>
                    <p class="text-xs text-gray-400">Tracking Number</p>
                    <p class="text-sm font-mono font-medium text-gray-900"><?= htmlspecialchars($document['tracking_number'] ?? '') ?></p>
                </div>
                <?php if (!empty($document['document_type_name'])): ?>
                <div>
                    <p class="text-xs text-gray-400">Document Type</p>
                    <?php $typeColor = $document['document_type_badge_color'] ?? '#2563EB'; ?>
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                          style="background-color:<?= htmlspecialchars($typeColor) ?>1a;color:<?= htmlspecialchars($typeColor) ?>;">
                        <?= htmlspecialchars($document['document_type_name']) ?>
                    </span>
                </div>
                <?php endif; ?>
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
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold"
                          style="background-color: <?= htmlspecialchars($document['status_badge_color'] ?? '#6B7280') ?>1a;
                                 color: <?= htmlspecialchars($document['status_badge_color'] ?? '#6B7280') ?>;">
                        <span class="h-1.5 w-1.5 rounded-full" style="background-color: <?= htmlspecialchars($document['status_badge_color'] ?? '#6B7280') ?>;"></span>
                        <?= htmlspecialchars($document['status']) ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Right: Document actions -->
        <div class="lg:col-span-2 space-y-4">
            <?php require __DIR__ . '/../../partials/document-action-timeline.php'; ?>

            <?php require __DIR__ . '/../../partials/assignment-history.php'; ?>

            <!-- Back link -->
            <div class="flex items-center gap-3">
                <a href="<?= BASE_URL ?>/plenary/petitions"
                   class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5
                          text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Petitions
                </a>
            </div>
        </div>
    </div>
</div>



<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';

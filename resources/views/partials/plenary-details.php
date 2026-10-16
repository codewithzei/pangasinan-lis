<?php
/**
 * Plenary Details — read-only display partial
 *
 * Include after the Source Information section on any document detail/show page.
 *
 * Expected variable (set by each controller's show() before including this page):
 *   $plenaryDetails  array|null   Row from plenary_document_details joined with
 *                                  document_statuses, or null when no row exists.
 *
 * Columns available when $plenaryDetails is not null:
 *   proposed_number              VARCHAR(50)  nullable
 *   target_first_reading_date    DATE         nullable
 *   session_type                 ENUM('REGULAR','REGULAR_ONLINE','SPECIAL','SPECIAL_ONLINE') nullable
 *   plenary_status_name          VARCHAR(50)  from document_statuses.name
 *   plenary_status_badge_color   VARCHAR(7)   from document_statuses.badge_color nullable
 *   additional_details           TEXT         nullable
 */

$plenaryDetails = $plenaryDetails ?? null;

// Human-readable labels for the session_type ENUM values.
$sessionTypeLabels = [
    'REGULAR'        => 'Regular',
    'REGULAR_ONLINE' => 'Regular (Online)',
    'SPECIAL'        => 'Special',
    'SPECIAL_ONLINE' => 'Special (Online)',
];
?>

<!-- Plenary Details ─────────────────────────────────────────────────────── -->
<section class="rounded-2xl border border-indigo-100 bg-indigo-50 p-6">
    <div class="mb-4 flex items-center gap-2">
        <!-- Icon -->
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-100">
            <svg class="h-4 w-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                 aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </span>
        <h2 class="text-base font-semibold text-indigo-900">Plenary Details</h2>
    </div>

    <?php if ($plenaryDetails): ?>

        <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">

            <!-- Proposed Number -->
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-indigo-400">
                    Proposed Number
                </dt>
                <dd class="mt-1 font-mono text-sm font-medium text-indigo-900">
                    <?= htmlspecialchars($plenaryDetails['proposed_number'] ?? '') !== ''
                        ? htmlspecialchars($plenaryDetails['proposed_number'])
                        : '<span class="font-sans font-normal text-indigo-400 italic">—</span>' ?>
                </dd>
            </div>

            <!-- Target First Reading Date -->
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-indigo-400">
                    Target First Reading Date
                </dt>
                <dd class="mt-1 text-sm text-indigo-900">
                    <?php if (!empty($plenaryDetails['target_first_reading_date'])): ?>
                        <?= htmlspecialchars(
                            date('F j, Y', strtotime($plenaryDetails['target_first_reading_date']))
                        ) ?>
                    <?php else: ?>
                        <span class="italic text-indigo-400">—</span>
                    <?php endif; ?>
                </dd>
            </div>

            <!-- Session Type -->
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-indigo-400">
                    Session Type
                </dt>
                <dd class="mt-1 text-sm text-indigo-900">
                    <?php
                    $sessionRaw   = $plenaryDetails['session_type'] ?? '';
                    $sessionLabel = $sessionTypeLabels[$sessionRaw] ?? '';
                    ?>
                    <?php if ($sessionLabel !== ''): ?>
                        <?= htmlspecialchars($sessionLabel) ?>
                    <?php else: ?>
                        <span class="italic text-indigo-400">—</span>
                    <?php endif; ?>
                </dd>
            </div>

            <!-- Plenary Status -->
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-indigo-400">
                    Plenary Status
                </dt>
                <dd class="mt-1">
                    <?php
                    $plenaryStatusName  = $plenaryDetails['plenary_status_name']        ?? '';
                    $plenaryBadgeColor  = $plenaryDetails['plenary_status_badge_color'] ?? '';
                    ?>
                    <?php if ($plenaryStatusName !== ''): ?>
                        <?php if ($plenaryBadgeColor !== ''): ?>
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                  style="background-color:<?= htmlspecialchars($plenaryBadgeColor) ?>1a;
                                         color:<?= htmlspecialchars($plenaryBadgeColor) ?>;">
                                <?= htmlspecialchars($plenaryStatusName) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-sm text-indigo-900">
                                <?= htmlspecialchars($plenaryStatusName) ?>
                            </span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="italic text-indigo-400 text-sm">—</span>
                    <?php endif; ?>
                </dd>
            </div>

            <!-- Additional Details (full-width, only when present) -->
            <?php if (!empty($plenaryDetails['additional_details'])): ?>
            <div class="sm:col-span-2">
                <dt class="text-xs font-semibold uppercase tracking-wide text-indigo-400">
                    Additional Details
                </dt>
                <dd class="mt-1 text-sm text-indigo-900 whitespace-pre-wrap leading-6">
                    <?= htmlspecialchars($plenaryDetails['additional_details']) ?>
                </dd>
            </div>
            <?php endif; ?>

        </dl>

    <?php else: ?>

        <!-- Empty-state note when no plenary row exists for this document -->
        <div class="flex items-start gap-3 rounded-xl border border-indigo-100 bg-white/60 p-4">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-indigo-300" fill="none" stroke="currentColor"
                 viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm text-indigo-500 italic">
                No Plenary details are available for this document yet.
            </p>
        </div>

    <?php endif; ?>
</section>

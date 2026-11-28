<?php
/**
 * Partial: MFR relationship links
 *
 * Shows navigation links between a Motion for Reconsideration and its
 * referenced Complaint / Administrative Case, and vice-versa.
 *
 * Expected variables (all optional – section is suppressed when absent):
 *
 *   $mfrLink        array|null  Full getMotionLink() result for the current
 *                               document (when the current doc IS an MFR).
 *                               Must contain at minimum: ref_id,
 *                               ref_tracking_number, ref_document_type_name,
 *                               ref_subject_matter.
 *                               Optional fields rendered when present:
 *                               ref_document_type_badge_color, ref_status,
 *                               ref_status_badge_color, ref_docket_number,
 *                               ref_nature_of_case, ref_resolution_number,
 *                               ref_finalized_at.
 *
 *   $relatedMotions array       getRelatedMotions() result for the current
 *                               document (when the current doc IS a Case).
 *                               Each entry must contain at minimum:
 *                               mfr_document_id, mfr_tracking_number.
 *                               Optional: mfr_subject_matter, mfr_status,
 *                               mfr_status_badge_color, mfr_date_received,
 *                               linked_at, linked_by_username.
 *
 *   $mfrDetailRoute string      Base route prefix used to build detail URLs
 *                               for documents in the *current* workflow context,
 *                               e.g. 'receiving/routed-documents',
 *                               'admin/inbox', 'spsec/inbox',
 *                               'committee/inbox', 'plenary/inbox'.
 *                               Both MFR and Case links use this same prefix
 *                               so the link stays within the user's current
 *                               permission context.
 *                               Defaults to 'admin/inbox' when not set.
 *
 * Rendering contract:
 *   – Nothing is output when both $mfrLink and $relatedMotions are empty.
 *   – No modal, toggle, or expandable section is added for the "referenced
 *     original case" section (it is always visible).
 *   – The "related motions" list uses a simple expand/collapse affordance
 *     only when there are multiple entries, to save vertical space.
 *
 * This file must NOT contain business logic; it is display-only.
 */

$mfrLink        = $mfrLink        ?? null;
$relatedMotions = $relatedMotions ?? [];
$mfrDetailRoute = rtrim($mfrDetailRoute ?? 'admin/inbox', '/');
$mfrDetailAction = $mfrDetailAction ?? 'show';
$mfrDetailQuery = (string) ($mfrDetailQuery ?? '');
$mfrRelationshipTopMargin = $mfrRelationshipTopMargin ?? 'mt-6';

// ── Nothing to show? Exit immediately ─────────────────────────────────────────
if (empty($mfrLink) && empty($relatedMotions)) {
    return;
}
?>

<?php // ═════════════════════════════════════════════════════════════════════
      // Section A: current doc IS an MFR → show the referenced original case
      // ═════════════════════════════════════════════════════════════════════ ?>
<?php if (!empty($mfrLink)): ?>
<section class="<?= htmlspecialchars($mfrRelationshipTopMargin) ?> rounded-2xl border border-amber-200 bg-amber-50 p-6">
    <h2 class="text-lg font-semibold text-amber-900">Referenced Original Case</h2>
    <p class="mt-1 text-sm text-amber-700">
        This Motion for Reconsideration challenges the following finalized case.
    </p>

    <?php
        $mfrOutcome = strtoupper(trim((string) ($mfrLink['outcome'] ?? 'PENDING')));
        $mfrOutcome = in_array($mfrOutcome, ['GRANTED', 'DENIED'], true) ? $mfrOutcome : 'PENDING';
        $mfrOutcomeConfig = match ($mfrOutcome) {
            'GRANTED' => ['accent' => 'border-l-emerald-500', 'badge' => 'border-emerald-200 bg-emerald-50 text-emerald-700'],
            'DENIED' => ['accent' => 'border-l-red-500', 'badge' => 'border-red-200 bg-red-50 text-red-700'],
            default => ['accent' => 'border-l-gray-400', 'badge' => 'border-gray-200 bg-gray-50 text-gray-600'],
        };
    ?>
    <div class="mt-4">
        <div class="flex flex-wrap items-center gap-2.5">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Plenary Outcome</p>
            <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-bold <?= $mfrOutcomeConfig['badge'] ?>">
                <?= htmlspecialchars(ucwords(strtolower($mfrOutcome))) ?>
            </span>
            <?php if (!empty($mfrLink['outcome_at'])): ?>
            <span class="text-xs text-gray-500">
                <?= htmlspecialchars(date('M j, Y · g:i A', strtotime($mfrLink['outcome_at']))) ?>
            </span>
            <?php endif; ?>
        </div>
        <?php if (!empty($mfrLink['outcome_remarks'])): ?>
        <blockquote class="mt-2 border-l-2 border-gray-300 pl-3 text-sm italic leading-relaxed text-gray-600">
            &ldquo;<?= htmlspecialchars(trim((string) $mfrLink['outcome_remarks'])) ?>&rdquo;
        </blockquote>
        <?php endif; ?>
    </div>

    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">

        <div>
            <p class="text-xs font-medium text-gray-500">Tracking Number</p>
            <a href="<?= BASE_URL ?>/<?= htmlspecialchars($mfrDetailRoute) ?>/<?= htmlspecialchars($mfrDetailAction) ?>?id=<?= (int) ($mfrLink['ref_id'] ?? $mfrLink['referenced_document_id'] ?? 0) ?><?= htmlspecialchars($mfrDetailQuery) ?>"
               class="mt-1 text-sm font-semibold text-primary hover:underline">
                <?= htmlspecialchars($mfrLink['ref_tracking_number'] ?? '—') ?>
            </a>
        </div>

        <div>
            <p class="text-xs font-medium text-gray-500">Document Type</p>
            <span class="mt-1 inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                  style="background-color:<?= htmlspecialchars($mfrLink['ref_document_type_badge_color'] ?? '#2563EB') ?>1a;color:<?= htmlspecialchars($mfrLink['ref_document_type_badge_color'] ?? '#2563EB') ?>;">
                <?= htmlspecialchars($mfrLink['ref_document_type_name'] ?? '—') ?>
            </span>
        </div>

        <div class="sm:col-span-2">
            <p class="text-xs font-medium text-gray-500">Subject Matter</p>
            <p class="mt-1 text-sm text-gray-900">
                <?= htmlspecialchars($mfrLink['ref_subject_matter'] ?? '—') ?>
            </p>
        </div>

        <?php $refCaseNumber = trim((string) ($mfrLink['ref_case_number'] ?? $mfrLink['ref_docket_number'] ?? '')); ?>
        <?php if ($refCaseNumber !== ''): ?>
        <div>
            <p class="text-xs font-medium text-gray-500">Case Number</p>
            <p class="mt-1 text-sm font-mono font-semibold text-gray-900">
                <?= htmlspecialchars($refCaseNumber) ?>
            </p>
        </div>
        <?php endif; ?>

        <?php if (!empty($mfrLink['ref_status'])): ?>
        <div>
            <p class="text-xs font-medium text-gray-500">Current Status</p>
            <span class="mt-1 inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold shadow-sm"
                  style="background-color:<?= htmlspecialchars($mfrLink['ref_status_badge_color'] ?? '#6B7280') ?>;border-color:<?= htmlspecialchars($mfrLink['ref_status_badge_color'] ?? '#6B7280') ?>;color:white;">
                <?= htmlspecialchars($mfrLink['ref_status']) ?>
            </span>
        </div>
        <?php endif; ?>

        <?php if (!empty($mfrLink['ref_nature_of_case'])): ?>
        <div class="sm:col-span-2">
            <p class="text-xs font-medium text-gray-500">Nature of Case</p>
            <p class="mt-1 text-sm text-gray-900">
                <?= htmlspecialchars($mfrLink['ref_nature_of_case']) ?>
            </p>
        </div>
        <?php endif; ?>

        <?php if (!empty($mfrLink['ref_resolution_number'])): ?>
        <div>
            <p class="text-xs font-medium text-gray-500">Resolution No.</p>
            <p class="mt-1 text-sm text-gray-900">
                <?= htmlspecialchars($mfrLink['ref_resolution_number']) ?>
            </p>
        </div>
        <?php endif; ?>

        <?php if (!empty($mfrLink['ref_finalized_at'])): ?>
        <div>
            <p class="text-xs font-medium text-gray-500">Finalized At</p>
            <p class="mt-1 text-sm text-gray-900">
                <?= htmlspecialchars(date('F j, Y', strtotime($mfrLink['ref_finalized_at']))) ?>
            </p>
        </div>
        <?php endif; ?>

    </div><!-- /grid -->

    <div class="mt-4">
        <a href="<?= BASE_URL ?>/<?= htmlspecialchars($mfrDetailRoute) ?>/<?= htmlspecialchars($mfrDetailAction) ?>?id=<?= (int) ($mfrLink['ref_id'] ?? $mfrLink['referenced_document_id'] ?? 0) ?><?= htmlspecialchars($mfrDetailQuery) ?>"
           class="inline-flex items-center gap-2 rounded-xl border border-amber-300 bg-white px-4 py-2 text-sm font-medium text-amber-800 hover:bg-amber-50 transition">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-3-3v6m-7 4h14a2 2 0 002-2V7l-5-5H5a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            </svg>
            View Original Case
        </a>
    </div>
</section>
<?php endif; ?>

<?php // ═════════════════════════════════════════════════════════════════════
      // Section B: current doc IS a Case → list all related MFRs
      // ═════════════════════════════════════════════════════════════════════ ?>
<?php if (!empty($relatedMotions)): ?>
<?php $mfrBodyId = 'relatedMotionsBody_' . md5($mfrDetailRoute); ?>
<section class="<?= htmlspecialchars($mfrRelationshipTopMargin) ?> rounded-2xl border border-purple-200 bg-purple-50 p-6">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h2 class="text-lg font-semibold text-purple-900">
                Related Motions for Reconsideration
                <span class="ml-2 inline-flex items-center rounded-full bg-purple-100 px-2 py-0.5 text-xs font-semibold text-purple-700">
                    <?= count($relatedMotions) ?>
                </span>
            </h2>
            <p class="mt-0.5 text-sm text-purple-700">
                Motions for Reconsideration filed against this case.
            </p>
        </div>
    </div>

    <div class="mt-4 space-y-3">
        <?php foreach ($relatedMotions as $motion): ?>
        <div class="rounded-xl border border-purple-200 bg-white p-4">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <a href="<?= BASE_URL ?>/<?= htmlspecialchars($mfrDetailRoute) ?>/<?= htmlspecialchars($mfrDetailAction) ?>?id=<?= (int) ($motion['mfr_document_id'] ?? 0) ?><?= htmlspecialchars($mfrDetailQuery) ?>"
                       class="text-sm font-semibold text-primary hover:underline">
                        <?= htmlspecialchars($motion['mfr_tracking_number'] ?? '—') ?>
                    </a>
                    <?php if (!empty($motion['mfr_subject_matter'])): ?>
                    <p class="mt-0.5 text-xs text-gray-500">
                        <?= htmlspecialchars(mb_substr($motion['mfr_subject_matter'], 0, 120)) ?>
                    </p>
                    <?php endif; ?>
                </div>
                <div class="flex flex-wrap justify-end gap-2">
                    <?php if (!empty($motion['mfr_status_badge_color'])): ?>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                          style="background-color:<?= htmlspecialchars($motion['mfr_status_badge_color']) ?>;color:white;">
                        <?= htmlspecialchars($motion['mfr_status'] ?? '') ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($motion['mfr_outcome']) && $motion['mfr_outcome'] !== 'PENDING'): ?>
                    <?php $outcomeColor = $motion['mfr_outcome'] === 'GRANTED' ? '#059669' : '#DC2626'; ?>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold text-white"
                          style="background-color:<?= $outcomeColor ?>;">
                        <?= htmlspecialchars($motion['mfr_outcome']) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-2 flex flex-wrap gap-4 text-xs text-gray-500">
                <?php if (!empty($motion['mfr_date_received'])): ?>
                <span>Received: <?= htmlspecialchars(date('M j, Y', strtotime($motion['mfr_date_received']))) ?></span>
                <?php endif; ?>
                <?php if (!empty($motion['linked_at'])): ?>
                <span>Linked: <?= htmlspecialchars(date('M j, Y g:i A', strtotime($motion['linked_at']))) ?></span>
                <?php endif; ?>
                <?php if (!empty($motion['linked_by_username'])): ?>
                <span>By: <?= htmlspecialchars($motion['linked_by_username']) ?></span>
                <?php endif; ?>
            </div>

            <a href="<?= BASE_URL ?>/<?= htmlspecialchars($mfrDetailRoute) ?>/<?= htmlspecialchars($mfrDetailAction) ?>?id=<?= (int) ($motion['mfr_document_id'] ?? 0) ?><?= htmlspecialchars($mfrDetailQuery) ?>"
               class="mt-2 inline-flex items-center gap-1 text-xs text-primary hover:underline font-medium">
                View Motion
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

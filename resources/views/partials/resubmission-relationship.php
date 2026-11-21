<?php
$resubmissionReference = $resubmissionReference ?? null;
$relatedResubmissions = $relatedResubmissions ?? [];
$resubmissionDetailRoute = rtrim(
    $resubmissionDetailRoute ?? $mfrDetailRoute ?? 'admin/inbox',
    '/'
);
$resubmissionTopMargin = $resubmissionTopMargin ?? 'mt-6';

if (empty($resubmissionReference) && empty($relatedResubmissions)) {
    return;
}
?>

<?php if (!empty($resubmissionReference)): ?>
<section class="<?= htmlspecialchars($resubmissionTopMargin) ?> rounded-2xl border border-indigo-200 bg-indigo-50 p-6">
    <div class="flex flex-wrap items-center gap-2">
        <h2 class="text-lg font-semibold text-indigo-900">Resubmission</h2>
        <span class="inline-flex rounded-full border border-indigo-200 bg-white px-2.5 py-0.5 text-xs font-semibold text-indigo-700">
            Resubmitted
        </span>
        <?php if (!empty($resubmissionReference['version_number'])): ?>
            <span class="inline-flex rounded-full border border-indigo-200 bg-white px-2.5 py-0.5 text-xs font-semibold text-indigo-700">
                Version <?= (int) $resubmissionReference['version_number'] ?>
            </span>
        <?php endif; ?>
    </div>
    <p class="mt-1 text-sm text-indigo-700">This document is a resubmission linked to the following prior document.</p>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <p class="text-xs font-medium text-gray-500">Linked Document</p>
            <a href="<?= BASE_URL ?>/<?= htmlspecialchars($resubmissionDetailRoute) ?>/show?id=<?= (int) ($resubmissionReference['ref_id'] ?? $resubmissionReference['referenced_document_id'] ?? 0) ?>"
               class="mt-1 inline-block text-sm font-semibold text-primary hover:underline">
                <?= htmlspecialchars($resubmissionReference['ref_tracking_number'] ?? '—') ?>
            </a>
            <?php if (!empty($resubmissionReference['ref_proposed_number'])): ?>
                <span class="text-sm text-gray-600">(<?= htmlspecialchars($resubmissionReference['ref_proposed_number']) ?>)</span>
            <?php endif; ?>
        </div>

        <?php if (!empty($resubmissionReference['ref_document_type_name'])): ?>
        <div>
            <p class="text-xs font-medium text-gray-500">Document Type</p>
            <span class="mt-1 inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                  style="background-color:<?= htmlspecialchars($resubmissionReference['ref_document_type_badge_color'] ?? '#2563EB') ?>1a;color:<?= htmlspecialchars($resubmissionReference['ref_document_type_badge_color'] ?? '#2563EB') ?>;">
                <?= htmlspecialchars($resubmissionReference['ref_document_type_name']) ?>
            </span>
        </div>
        <?php endif; ?>

        <?php if (!empty($resubmissionReference['ref_status'])): ?>
        <div>
            <p class="text-xs font-medium text-gray-500">Current Status</p>
            <span class="mt-1 inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold text-white"
                  style="background-color:<?= htmlspecialchars($resubmissionReference['ref_status_badge_color'] ?? '#6B7280') ?>;">
                <?= htmlspecialchars($resubmissionReference['ref_status']) ?>
            </span>
        </div>
        <?php endif; ?>

        <?php if (!empty($resubmissionReference['routing_option_name'])): ?>
        <div>
            <p class="text-xs font-medium text-gray-500">Routing Option</p>
            <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($resubmissionReference['routing_option_name']) ?></p>
        </div>
        <?php endif; ?>

        <div class="sm:col-span-2">
            <p class="text-xs font-medium text-gray-500">Subject Matter</p>
            <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($resubmissionReference['ref_subject_matter'] ?? '—') ?></p>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($relatedResubmissions)): ?>
<section class="<?= htmlspecialchars($resubmissionTopMargin) ?> rounded-2xl border border-indigo-200 bg-indigo-50 p-6">
    <h2 class="text-lg font-semibold text-indigo-900">Submission History</h2>
    <p class="mt-1 text-sm text-indigo-700">All submissions in this legislative document lineage.</p>

    <ul class="mt-4 divide-y divide-indigo-100 rounded-xl border border-indigo-100 bg-white">
        <?php foreach ($relatedResubmissions as $resubmission): ?>
        <li class="flex flex-wrap items-center justify-between gap-3 p-4">
            <div>
                <a href="<?= BASE_URL ?>/<?= htmlspecialchars($resubmissionDetailRoute) ?>/show?id=<?= (int) ($resubmission['resubmitted_document_id'] ?? 0) ?>"
                   class="text-sm font-semibold text-primary hover:underline">
                    <?= htmlspecialchars($resubmission['resubmitted_tracking_number'] ?? '—') ?>
                </a>
                <span class="ml-2 inline-flex rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700">
                    <?= (int) ($resubmission['version_number'] ?? 1) === 1
                        ? 'Original · Version 1'
                        : 'Version ' . (int) $resubmission['version_number'] ?>
                </span>
                <?php if (!empty($resubmission['is_current'])): ?>
                    <span class="ml-1 inline-flex rounded-full bg-indigo-700 px-2 py-0.5 text-xs font-semibold text-white">Current</span>
                <?php endif; ?>
                <p class="mt-1 text-sm text-gray-700"><?= htmlspecialchars($resubmission['resubmitted_subject_matter'] ?? '—') ?></p>
                <?php if (!empty($resubmission['resubmitted_proposed_number'])): ?>
                    <p class="mt-1 text-xs text-gray-600">Proposed Number: <?= htmlspecialchars($resubmission['resubmitted_proposed_number']) ?></p>
                <?php endif; ?>
                <?php if (!empty($resubmission['resubmitted_document_type_name'])): ?>
                    <p class="mt-1 text-xs text-gray-500"><?= htmlspecialchars($resubmission['resubmitted_document_type_name']) ?></p>
                <?php endif; ?>
            </div>
            <?php if (!empty($resubmission['resubmitted_status'])): ?>
                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold text-white"
                      style="background-color:<?= htmlspecialchars($resubmission['resubmitted_status_badge_color'] ?? '#6B7280') ?>;">
                    <?= htmlspecialchars($resubmission['resubmitted_status']) ?>
                </span>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>
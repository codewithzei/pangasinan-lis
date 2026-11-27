<?php
$sourceDocument = $sourceDocument ?? [];
foreach ([$comm ?? null, $case ?? null, $document ?? null, $record ?? null] as $candidate) {
    if (is_array($candidate) && $candidate !== []) {
        $sourceDocument = $candidate;
        break;
    }
}
$sourceType = $sourceDocument['source_type']
    ?? $sourceDocument['source_type_name']
    ?? $sourceDocument['source_type_label']
    ?? '—';
$sourceInformationVariant = $sourceInformationVariant ?? 'white';
$isBlueSourceInformation = $sourceInformationVariant === 'blue';
$sourceLabelClasses = $isBlueSourceInformation ? 'text-blue-600' : 'text-gray-400';
$sourceValueClasses = $isBlueSourceInformation ? 'text-blue-950' : 'text-gray-800';
?>
<section class="rounded-2xl p-6 <?= $isBlueSourceInformation
    ? 'border border-blue-200 bg-blue-50'
    : 'border border-gray-200 bg-white' ?>">
    <h2 class="text-base font-semibold <?= $isBlueSourceInformation ? 'text-blue-950' : 'text-gray-900' ?>">Source Information</h2>
    <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
        <div>
            <dt class="text-xs font-medium uppercase tracking-wide <?= $sourceLabelClasses ?>">Source Type</dt>
            <dd class="mt-1 text-sm <?= $sourceValueClasses ?>"><?= htmlspecialchars((string) ($sourceType ?: '—')) ?></dd>
        </div>
        <div>
            <dt class="text-xs font-medium uppercase tracking-wide <?= $sourceLabelClasses ?>">Source Name</dt>
            <dd class="mt-1 text-sm <?= $sourceValueClasses ?>"><?= htmlspecialchars((string) ($sourceDocument['source_name'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt class="text-xs font-medium uppercase tracking-wide <?= $sourceLabelClasses ?>">Contact Number</dt>
            <dd class="mt-1 text-sm <?= $sourceValueClasses ?>"><?= htmlspecialchars((string) ($sourceDocument['source_contact_number'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt class="text-xs font-medium uppercase tracking-wide <?= $sourceLabelClasses ?>">Address</dt>
            <dd class="mt-1 whitespace-pre-wrap text-sm <?= $sourceValueClasses ?>"><?= htmlspecialchars((string) ($sourceDocument['source_address'] ?? '—')) ?></dd>
        </div>
        <div class="sm:col-span-2">
            <dt class="text-xs font-medium uppercase tracking-wide <?= $sourceLabelClasses ?>">Liaison</dt>
            <dd class="mt-1 text-sm <?= $sourceValueClasses ?>"><?= htmlspecialchars((string) ($sourceDocument['source_liaison_name'] ?? '—')) ?></dd>
        </div>
    </dl>
</section>

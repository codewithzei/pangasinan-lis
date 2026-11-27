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
?>
<section class="rounded-2xl border border-gray-200 bg-white p-6">
    <h2 class="text-base font-semibold text-gray-900">Source Information</h2>
    <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Source Type</dt>
            <dd class="mt-1 text-sm text-gray-800"><?= htmlspecialchars((string) ($sourceType ?: '—')) ?></dd>
        </div>
        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Source Name</dt>
            <dd class="mt-1 text-sm text-gray-800"><?= htmlspecialchars((string) ($sourceDocument['source_name'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Contact Number</dt>
            <dd class="mt-1 text-sm text-gray-800"><?= htmlspecialchars((string) ($sourceDocument['source_contact_number'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Address</dt>
            <dd class="mt-1 whitespace-pre-wrap text-sm text-gray-800"><?= htmlspecialchars((string) ($sourceDocument['source_address'] ?? '—')) ?></dd>
        </div>
        <div class="sm:col-span-2">
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Liaison</dt>
            <dd class="mt-1 text-sm text-gray-800"><?= htmlspecialchars((string) ($sourceDocument['source_liaison_name'] ?? '—')) ?></dd>
        </div>
    </dl>
</section>

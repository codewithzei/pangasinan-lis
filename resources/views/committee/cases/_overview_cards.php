<?php
$caseStats = $caseStats ?? [];
$caseCards = [
    [
        'label' => 'Active Cases',
        'count' => (int) ($caseStats['active_cases'] ?? 0),
        'note' => 'Open committee workflow',
        'url' => BASE_URL . '/committee/cases',
        'style' => 'border-blue-100 bg-blue-50 text-blue-700',
    ],
    [
        'label' => 'Needs Details',
        'count' => (int) ($caseStats['details_pending'] ?? 0),
        'note' => 'Complete case information',
        'url' => BASE_URL . '/committee/cases?details_pending=1',
        'style' => 'border-amber-100 bg-amber-50 text-amber-700',
    ],
    [
        'label' => 'Approved',
        'count' => (int) ($caseStats['approved'] ?? 0),
        'note' => 'Approved cases',
        'url' => BASE_URL . '/committee/cases/for-report',
        'style' => 'border-emerald-100 bg-emerald-50 text-emerald-700',
    ],
    [
        'label' => 'Deferred',
        'count' => (int) ($caseStats['deferred'] ?? 0),
        'note' => 'For further review',
        'url' => BASE_URL . '/committee/cases/deferred',
        'style' => 'border-purple-100 bg-purple-50 text-purple-700',
    ],
    [
        'label' => 'Withdrawn',
        'count' => (int) ($caseStats['withdrawn'] ?? 0),
        'note' => 'Withdrawn from workflow',
        'url' => BASE_URL . '/committee/cases/withdrawn',
        'style' => 'border-rose-100 bg-rose-50 text-rose-700',
    ],
    [
        'label' => 'Noted',
        'count' => (int) ($caseStats['noted'] ?? 0),
        'note' => 'Noted by the Committee',
        'url' => BASE_URL . '/committee/cases/noted',
        'style' => 'border-cyan-100 bg-cyan-50 text-cyan-700',
    ],
];
?>
<section class="grid grid-cols-2 gap-3 sm:grid-cols-3 2xl:grid-cols-6" aria-label="Case overview">
    <?php foreach ($caseCards as $card): ?>
        <a href="<?= htmlspecialchars($card['url']) ?>"
           class="group rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition
                  hover:border-gray-300 hover:shadow-md
                  focus:outline-none focus:ring-2 focus:ring-primary/30">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                <?= htmlspecialchars($card['label']) ?>
            </p>
            <p class="mt-3 text-2xl font-bold tracking-tight text-gray-900">
                <?= number_format($card['count']) ?>
            </p>
            <p class="mt-1 text-xs text-gray-500"><?= htmlspecialchars($card['note']) ?></p>
        </a>
    <?php endforeach; ?>
</section>

<?php
$documentEvents = $documentEvents ?? [];
$documentEventPhaseLabel = static fn(?string $phase): string => match ($phase) {
    'RECEIVING' => 'Receiving',
    'ADMIN' => 'Admin',
    'SP_SECRETARY' => 'SP Secretary',
    'PLENARY' => 'Plenary',
    'COMMITTEE' => 'Committee',
    'FINALIZED' => 'Finalized',
    'FILED' => 'Filed',
    default => $phase ?: '—',
};
?>

<section class="rounded-2xl border border-gray-200 bg-white p-6">
    <h2 class="mb-4 text-base font-semibold text-gray-900">Action History</h2>
    <?php if (empty($documentEvents)): ?>
        <p class="text-sm italic text-gray-400">No action history recorded.</p>
    <?php else: ?>
        <ol class="relative ml-3 space-y-5 border-l border-gray-200">
            <?php foreach ($documentEvents as $event): ?>
                <?php
                $eventType = (string) ($event['event_type'] ?? 'OTHER');
                $eventLabel = ucwords(strtolower(str_replace('_', ' ', $eventType)));
                $eventDate = $event['created_at'] ?? null;
                ?>
                <li class="relative ml-6">
                    <span class="absolute -left-[31px] flex h-6 w-6 items-center justify-center rounded-full bg-indigo-600 text-white ring-4 ring-white" aria-hidden="true">
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </span>
                    <p class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($eventLabel) ?></p>
                    <p class="text-xs text-gray-500">
                        <?= htmlspecialchars($documentEventPhaseLabel($event['phase'] ?? null)) ?>
                        <?php if (!empty($event['performed_by_name'])): ?>
                            &middot; by <?= htmlspecialchars($event['performed_by_name']) ?>
                        <?php endif; ?>
                        &middot; <?= !empty($eventDate) ? htmlspecialchars(date('M j, Y g:i A', strtotime($eventDate))) : 'Date unavailable' ?>
                    </p>
                    <?php if (!empty($event['from_status_name']) || !empty($event['to_status_name'])): ?>
                        <p class="mt-1 text-xs text-gray-500">
                            Status: <?= htmlspecialchars($event['from_status_name'] ?: '—') ?>
                            &rarr; <?= htmlspecialchars($event['to_status_name'] ?: '—') ?>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($event['remarks'])): ?>
                        <p class="mt-1 whitespace-pre-line text-xs italic text-gray-500"><?= htmlspecialchars($event['remarks']) ?></p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>

<?php
/**
 * Shared pagination partial for Referred Documents tabs.
 *
 * Expects these variables in scope (set by index.php):
 *   $page        int
 *   $totalPages  int
 *   $total       int
 */
$page       = $page       ?? 1;
$totalPages = $totalPages ?? 1;
$total      = $total      ?? 0;
?>
<div class="flex flex-col items-center justify-between gap-3 border-t border-gray-100
            px-6 py-4 sm:flex-row">
    <p class="text-xs text-gray-500">
        Page <span class="font-medium text-gray-700"><?= $page ?></span>
        of <span class="font-medium text-gray-700"><?= $totalPages ?></span>
        (<?= number_format($total) ?> total)
    </p>
    <div class="flex items-center gap-1">
        <?php if ($page > 1): ?>
            <a href="<?= BASE_URL ?>/committee/referred?<?= htmlspecialchars(referredQueryWith(['page' => $page - 1])) ?>"
               class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium
                      text-gray-700 hover:bg-gray-50 transition">
                Prev
            </a>
        <?php endif; ?>

        <?php
        $rangeStart = max(1, $page - 2);
        $rangeEnd   = min($totalPages, $page + 2);
        for ($p = $rangeStart; $p <= $rangeEnd; $p++):
        ?>
            <a href="<?= BASE_URL ?>/committee/referred?<?= htmlspecialchars(referredQueryWith(['page' => $p])) ?>"
               class="rounded-lg border px-3 py-1.5 text-sm font-medium transition
                      <?= $p === $page
                          ? 'border-primary bg-primary text-white'
                          : 'border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
                <?= $p ?>
            </a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a href="<?= BASE_URL ?>/committee/referred?<?= htmlspecialchars(referredQueryWith(['page' => $page + 1])) ?>"
               class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium
                      text-gray-700 hover:bg-gray-50 transition">
                Next
            </a>
        <?php endif; ?>
    </div>
</div>

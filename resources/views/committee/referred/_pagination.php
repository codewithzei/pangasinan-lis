<?php
/**
 * Shared pagination partial — Referred Documents tabs.
 *
 * Expects in scope (set by index.php):
 *   $page        int
 *   $totalPages  int
 *   $total       int   (used as "total records")
 *
 * Uses referredQueryWith() (defined in index.php) to carry tab / search /
 * doc_type through every page link.
 *
 * Matches the Master table pagination pattern exactly:
 *   border-t border-gray-100 px-6 py-4 | "Showing page X of Y (Z total records)"
 *   ±2 window | active: border-primary bg-primary text-white
 */
$page       = $page       ?? 1;
$totalPages = $totalPages ?? 1;
$total      = $total      ?? 0;
?>
<?php if ($totalPages > 1): ?>
<div class="flex flex-col items-center justify-between gap-3 border-t border-gray-100 px-6 py-4 sm:flex-row">
    <p class="text-xs text-gray-500">
        Showing page <span class="font-medium text-gray-700"><?= $page ?></span> of
        <span class="font-medium text-gray-700"><?= $totalPages ?></span>
        (<?= number_format($total) ?> total record<?= $total !== 1 ? 's' : '' ?>)
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
        $startPage = max(1, $page - 2);
        $endPage   = min($totalPages, $page + 2);
        for ($i = $startPage; $i <= $endPage; $i++):
        ?>
            <a href="<?= BASE_URL ?>/committee/referred?<?= htmlspecialchars(referredQueryWith(['page' => $i])) ?>"
               class="rounded-lg border px-3 py-1.5 text-sm font-medium transition
                      <?= $i === $page
                          ? 'border-primary bg-primary text-white'
                          : 'border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
                <?= $i ?>
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
<?php endif; ?>

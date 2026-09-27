<?php
/**
 * Shared navigation tabs for the Cases outcome pages.
 * Included by index.php, for-report.php, deferred.php, withdrawn.php, noted.php.
 *
 * Detects the active tab from the current request URI.
 * Uses the same underline-tab pattern as the Committee Inbox workflow tabs.
 */
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$currentPath = rtrim($currentPath, '/');

$tabs = [
    ['label' => 'Active Cases', 'url' => BASE_URL . '/committee/cases',           'slug' => 'cases'],
    ['label' => 'For Report',   'url' => BASE_URL . '/committee/cases/for-report', 'slug' => 'for-report'],
    ['label' => 'Deferred',     'url' => BASE_URL . '/committee/cases/deferred',   'slug' => 'deferred'],
    ['label' => 'Withdrawn',    'url' => BASE_URL . '/committee/cases/withdrawn',  'slug' => 'withdrawn'],
    ['label' => 'Noted',        'url' => BASE_URL . '/committee/cases/noted',      'slug' => 'noted'],
];
?>
<div class="border-b border-gray-200">
    <nav class="-mb-px flex gap-6 overflow-x-auto" aria-label="Cases navigation">
        <?php foreach ($tabs as $tab):
            $isActive = str_ends_with($currentPath, '/' . $tab['slug'])
                     || ($tab['slug'] === 'cases' && str_ends_with($currentPath, '/committee/cases'));
        ?>
            <a href="<?= $tab['url'] ?>"
               class="whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition
                      <?= $isActive
                          ? 'border-primary text-primary'
                          : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' ?>">
                <?= $tab['label'] ?>
            </a>
        <?php endforeach; ?>
    </nav>
</div>

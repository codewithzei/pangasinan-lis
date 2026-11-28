<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/app/controllers/Committee/CommitteeCasesController.php');
$view = file_get_contents($root . '/resources/views/committee/cases/show.php');
$migration = file_get_contents($root . '/database/migrations/109_add_remanded_committee_case_outcome.php');

$checks = [
    'Controller accepts REMANDED' => str_contains($controller, "'REMANDED', 'WITHDRAWN'"),
    'Controller maps REMANDED status and event' =>
        str_contains($controller, "'REMANDED'  => 'Remanded'") &&
        str_contains($controller, "'REMANDED'  => 'HEARING_REMANDED'"),
    'Case detail offers and displays Remanded' =>
        str_contains($view, '<option value="REMANDED">Remand — Case sent to Remanded</option>') &&
        str_contains($view, "'REMANDED'  => ['label' => 'Remanded'"),
    'Finalized Remanded case links to Remanded Hearings' =>
        str_contains($view, "'REMANDED'  => ['url' => 'committee/hearing?tab=remanded'"),
    'Migration adds REMANDED to final_outcome enum' =>
        str_contains($migration, "private const OUTCOME = 'REMANDED'") &&
        str_contains($migration, 'MODIFY COLUMN final_outcome ENUM'),
];

$failed = false;
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ": {$label}" . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);

<?php
/**
 * verify_054_migration.php
 *
 * Focused verification that migration 054 has been applied correctly.
 * Mirrors the exact queries used by:
 *   - CommitteeReferredController::requireDocumentStatus()
 *   - CommitteeReferredController::requireOpinionStatus()
 *
 * Run: php verify_054_migration.php
 * Exit 0 = all checks passed. Exit 1 = one or more checks failed.
 */

require_once __DIR__ . '/app/config/database.php';

$pdo = (new Database())->connect();
$pass = 0;
$fail = 0;

// ── helpers ───────────────────────────────────────────────────────────────────

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        echo "\033[32m  PASS\033[0m  {$label}\n";
        $pass++;
    } else {
        echo "\033[31m  FAIL\033[0m  {$label}" . ($detail ? " — {$detail}" : '') . "\n";
        $fail++;
    }
}

/** Exactly replicates requireDocumentStatus() from CommitteeReferredController. */
function lookupDocumentStatus(PDO $pdo, string $name): ?array
{
    $stmt = $pdo->prepare("
        SELECT id, name, badge_color FROM document_statuses
        WHERE name = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1
    ");
    $stmt->execute([$name]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Exactly replicates requireOpinionStatus() from CommitteeReferredController. */
function lookupOpinionStatus(PDO $pdo, string $name): ?array
{
    $stmt = $pdo->prepare("
        SELECT id, name FROM opinion_statuses
        WHERE name = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1
    ");
    $stmt->execute([$name]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// ── migration record ──────────────────────────────────────────────────────────

echo "\n=== Migration record ===\n";
$stmt = $pdo->prepare("SELECT id, batch FROM migrations WHERE migration = ?");
$stmt->execute(['054_seed_committee_workflow_statuses']);
$migRow = $stmt->fetch(PDO::FETCH_ASSOC);
check(
    '054_seed_committee_workflow_statuses is recorded in migrations table',
    (bool) $migRow,
    $migRow ? "batch={$migRow['batch']}" : 'row not found'
);

// ── unique index guard ────────────────────────────────────────────────────────

echo "\n=== Unique-index guard ===\n";
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE table_schema = DATABASE()
      AND table_name   = 'document_statuses'
      AND index_name   = 'uq_document_statuses_name'
");
$stmt->execute();
check('UNIQUE INDEX uq_document_statuses_name exists on document_statuses', (int)$stmt->fetchColumn() > 0);

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE table_schema = DATABASE()
      AND table_name   = 'opinion_statuses'
      AND index_name   = 'uq_opinion_statuses_name'
");
$stmt->execute();
check('UNIQUE INDEX uq_opinion_statuses_name exists on opinion_statuses', (int)$stmt->fetchColumn() > 0);

// ── document_statuses seeded by 054 ─────────────────────────────────────────

echo "\n=== document_statuses (migration 054 rows) ===\n";

$docRequired = [
    'Opinion Requested' => ['badge_color' => '#7C3AED'],
    'Withdrawn'         => ['badge_color' => '#6B7280'],
    'Ready for Agenda'  => ['badge_color' => '#059669'],
];

foreach ($docRequired as $name => $expected) {
    $row = lookupDocumentStatus($pdo, $name);
    $found = $row !== null;
    check(
        "document_statuses \"{$name}\" exists (is_active=1, is_deleted=0)",
        $found,
        $found ? "id={$row['id']}, badge_color={$row['badge_color']}" : 'not found or inactive/deleted'
    );
    if ($found) {
        check(
            "  badge_color is \"{$expected['badge_color']}\"",
            $row['badge_color'] === $expected['badge_color'],
            "got {$row['badge_color']}"
        );
    }
}

// ── critical: the exact lookup that threw the RuntimeException ───────────────

echo "\n=== Endorsement flow — critical status lookup ===\n";
$opinionRequested = lookupDocumentStatus($pdo, 'Opinion Requested');
check(
    'requireDocumentStatus("Opinion Requested") would succeed (no RuntimeException)',
    $opinionRequested !== null,
    $opinionRequested ? "id={$opinionRequested['id']}" : 'WOULD THROW RuntimeException'
);

// ── opinion_statuses seeded by 054 ──────────────────────────────────────────

echo "\n=== opinion_statuses (migration 054 rows) ===\n";

$opRequired = ['Pending', 'Submitted', 'Favorable', 'Unfavorable', 'Completed'];
foreach ($opRequired as $name) {
    $row = lookupOpinionStatus($pdo, $name);
    check(
        "opinion_statuses \"{$name}\" exists (is_active=1, is_deleted=0)",
        $row !== null,
        $row ? "id={$row['id']}" : 'not found or inactive/deleted'
    );
}

// ── no duplicate names ───────────────────────────────────────────────────────

echo "\n=== No duplicate names ===\n";
$dupDoc = $pdo->query("
    SELECT name, COUNT(*) AS cnt FROM document_statuses GROUP BY name HAVING cnt > 1
")->fetchAll(PDO::FETCH_ASSOC);
check('No duplicate names in document_statuses', empty($dupDoc),
    empty($dupDoc) ? '' : implode(', ', array_column($dupDoc, 'name')));

$dupOp = $pdo->query("
    SELECT name, COUNT(*) AS cnt FROM opinion_statuses GROUP BY name HAVING cnt > 1
")->fetchAll(PDO::FETCH_ASSOC);
check('No duplicate names in opinion_statuses', empty($dupOp),
    empty($dupOp) ? '' : implode(', ', array_column($dupOp, 'name')));

// ── pre-existing rows untouched ──────────────────────────────────────────────

echo "\n=== Pre-existing rows untouched ===\n";
$preDoc = $pdo->query("
    SELECT id, name FROM document_statuses WHERE id <= 17 ORDER BY id
")->fetchAll(PDO::FETCH_ASSOC);
check('Original 17 document_statuses rows still present', count($preDoc) === 17,
    'count=' . count($preDoc));

$stmt = $pdo->query("SELECT id, name, is_active, is_deleted FROM opinion_statuses WHERE id IN (1,2,3,4) ORDER BY id");
$preOpRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($preOpRows as $r) {
    check(
        "Pre-existing opinion_status id={$r['id']} ({$r['name']}) is still active",
        (int)$r['is_active'] === 1 && (int)$r['is_deleted'] === 0
    );
}

// ── summary ───────────────────────────────────────────────────────────────────

echo "\n" . str_repeat('─', 52) . "\n";
$total = $pass + $fail;
echo "Result: {$pass}/{$total} checks passed";
if ($fail > 0) {
    echo "  \033[31m({$fail} FAILED)\033[0m\n";
    exit(1);
} else {
    echo "  \033[32m(ALL PASSED)\033[0m\n";
    exit(0);
}

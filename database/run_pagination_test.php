<?php
/**
 * run_pagination_test.php
 *
 * CLI runner for the pagination test seeder and cleanup.
 *
 * Usage (from project root):
 *   php database/run_pagination_test.php seed     — insert test data
 *   php database/run_pagination_test.php cleanup  — remove test data
 *   php database/run_pagination_test.php verify   — count rows per paginated context
 *
 * Examples:
 *   php database/run_pagination_test.php seed
 *   php database/run_pagination_test.php cleanup
 *   php database/run_pagination_test.php verify
 */

declare(strict_types=1);

// ── Bootstrap ─────────────────────────────────────────────────────────────────
$root = dirname(__DIR__);

require_once $root . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable($root);
$dotenv->load();

// Minimal Database class inline (mirrors app/config/database.php)
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $_ENV['DB_HOST'],
    $_ENV['DB_PORT'],
    $_ENV['DB_DATABASE']
);
try {
    $pdo = new PDO($dsn, $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

// Load seeder infrastructure
require_once __DIR__ . '/seeders/Seeder.php';
require_once __DIR__ . '/seeders/PaginationTestSeeder.php';
require_once __DIR__ . '/seeders/PaginationTestCleanup.php';

// ── Command dispatch ──────────────────────────────────────────────────────────
$cmd = $argv[1] ?? 'help';

switch ($cmd) {

    // ─────────────────────────────────────────────────────────────────────────
    case 'seed':
        echo PHP_EOL . "=== Pagination Test Seeder ===" . PHP_EOL . PHP_EOL;
        $seeder = new PaginationTestSeeder($pdo);
        $seeder->run();
        echo PHP_EOL . "Seeding complete." . PHP_EOL;
        break;

    // ─────────────────────────────────────────────────────────────────────────
    case 'cleanup':
        echo PHP_EOL . "=== Pagination Test Cleanup ===" . PHP_EOL . PHP_EOL;
        $cleanup = new PaginationTestCleanup($pdo);
        $cleanup->run();
        echo PHP_EOL . "Cleanup complete." . PHP_EOL;
        break;

    // ─────────────────────────────────────────────────────────────────────────
    case 'verify':
        echo PHP_EOL . "=== Pagination Verification Counts ===" . PHP_EOL;
        echo "Page size = 20.  Need > 20 per tab to confirm multi-page behavior." . PHP_EOL . PHP_EOL;

        // Helper: fetch admin/spsec/committee user IDs
        $adminRow      = $pdo->query("SELECT id FROM user_accounts WHERE username = 'adminlegis+' LIMIT 1")->fetch();
        $spsecRow      = $pdo->query("SELECT id FROM user_accounts WHERE username = 'splegis+' LIMIT 1")->fetch();
        $committeeRow  = $pdo->query("SELECT id FROM user_accounts WHERE username = 'committeelegis+' LIMIT 1")->fetch();

        $adminId     = $adminRow     ? (int) $adminRow['id']     : 0;
        $spsecId     = $spsecRow     ? (int) $spsecRow['id']     : 0;
        $committeeId = $committeeRow ? (int) $committeeRow['id'] : 0;

        // Helper: role IDs
        $adminRoleRow    = $pdo->query("SELECT id FROM roles WHERE name = 'Admin' LIMIT 1")->fetch();
        $spsecRoleRow    = $pdo->query("SELECT id FROM roles WHERE name = 'SP Secretary' LIMIT 1")->fetch();
        $commRoleRow     = $pdo->query("SELECT id FROM roles WHERE name = 'Committee' LIMIT 1")->fetch();
        $recRoleRow      = $pdo->query("SELECT id FROM roles WHERE name = 'Receiving Staff' LIMIT 1")->fetch();

        $adminRoleId   = $adminRoleRow   ? (int) $adminRoleRow['id']   : 0;
        $spsecRoleId   = $spsecRoleRow   ? (int) $spsecRoleRow['id']   : 0;
        $commRoleId    = $commRoleRow    ? (int) $commRoleRow['id']     : 0;
        $recRoleId     = $recRoleRow     ? (int) $recRoleRow['id']      : 0;

        // Noted routing option
        $notedRow = $pdo->query("SELECT id FROM routing_options WHERE name = 'Noted' AND is_deleted = 0 LIMIT 1")->fetch();
        $notedId  = $notedRow ? (int) $notedRow['id'] : 0;

        // Communication type id
        $commTypeRow = $pdo->query("SELECT id FROM document_types WHERE name = 'Communication' AND is_deleted = 0 LIMIT 1")->fetch();
        $commTypeId  = $commTypeRow ? (int) $commTypeRow['id'] : 0;

        // On Going status id
        $onGoingRow = $pdo->query("SELECT id FROM document_statuses WHERE name = 'On Going' LIMIT 1")->fetch();
        $onGoingId  = $onGoingRow ? (int) $onGoingRow['id'] : 0;

        $rows = [];

        // 1. Receiving › Routed Documents
        $n = (int) $pdo->query("
            SELECT COUNT(*) FROM documents d
            INNER JOIN document_statuses ds ON d.current_status_id = ds.id
            WHERE d.tracking_year = 9999
        ")->fetchColumn();
        $rows[] = ['#', 'Page / Tab', 'Total Rows', 'Pages @ 20', 'Pass?'];
        $rows[] = [1, 'Receiving › Routed Documents', $n, ceil($n / 20), $n >= 41 ? 'PASS' : 'FAIL'];

        // 2. Receiving › Inbox – Returned
        $n = (int) $pdo->prepare("
            SELECT COUNT(DISTINCT da.document_id)
            FROM document_assignments da
            INNER JOIN documents d ON da.document_id = d.id
            WHERE da.assigned_to_role_id = ? AND da.phase = 'RECEIVING'
              AND da.decision = 'PENDING' AND da.completed_at IS NULL
              AND EXISTS (
                SELECT 1 FROM document_assignments da2
                WHERE da2.document_id = da.document_id
                  AND da2.phase = 'ADMIN' AND da2.decision = 'DECLINED' AND da2.declined_at IS NOT NULL
              )
              AND d.tracking_year = 9999
        ")->execute([$recRoleId]) ? null : null; // workaround: use fetch
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT da.document_id)
            FROM document_assignments da
            INNER JOIN documents d ON da.document_id = d.id
            WHERE da.assigned_to_role_id = ? AND da.phase = 'RECEIVING'
              AND da.decision = 'PENDING' AND da.completed_at IS NULL
              AND EXISTS (
                SELECT 1 FROM document_assignments da2
                WHERE da2.document_id = da.document_id
                  AND da2.phase = 'ADMIN' AND da2.decision = 'DECLINED' AND da2.declined_at IS NOT NULL
              )
              AND d.tracking_year = 9999
        ");
        $stmt->execute([$recRoleId]);
        $n = (int) $stmt->fetchColumn();
        $rows[] = [2, 'Receiving › Inbox – Returned', $n, ceil($n / 20), $n >= 41 ? 'PASS' : 'FAIL'];

        // 3. Receiving › Inbox – Accepted
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT da.document_id)
            FROM document_assignments da
            INNER JOIN documents d ON da.document_id = d.id
            WHERE da.assigned_to_role_id = ? AND da.phase = 'RECEIVING'
              AND da.decision IN ('ACCEPTED','COMPLETED') AND da.completed_at IS NOT NULL
              AND EXISTS (
                SELECT 1 FROM document_assignments da2
                WHERE da2.document_id = da.document_id
                  AND da2.phase = 'ADMIN' AND da2.decision = 'DECLINED'
              )
              AND d.tracking_year = 9999
        ");
        $stmt->execute([$recRoleId]);
        $n = (int) $stmt->fetchColumn();
        $rows[] = [3, 'Receiving › Inbox – Accepted', $n, ceil($n / 20), $n >= 41 ? 'PASS' : 'FAIL'];

        // 4. Admin › Inbox – Pending
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT da.document_id)
            FROM document_assignments da
            INNER JOIN documents d ON da.document_id = d.id
            WHERE da.assigned_to_role_id = ? AND da.phase = 'ADMIN'
              AND da.decision = 'PENDING' AND da.completed_at IS NULL
              AND da.accepted_by IS NULL
              AND (da.assigned_to_user_id IS NULL OR da.assigned_to_user_id = ?)
              AND d.tracking_year = 9999
        ");
        $stmt->execute([$adminRoleId, $adminId]);
        $n = (int) $stmt->fetchColumn();
        $rows[] = [4, 'Admin › Inbox – Pending', $n, ceil($n / 20), $n >= 41 ? 'PASS' : 'FAIL'];

        // 5. Admin › Inbox – Accepted
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT da.document_id)
            FROM document_assignments da
            INNER JOIN documents d ON da.document_id = d.id
            WHERE da.assigned_to_role_id = ? AND da.phase = 'ADMIN'
              AND da.decision = 'ACCEPTED'
              AND (da.accepted_by = ? OR da.assigned_to_user_id = ?)
              AND d.tracking_year = 9999
        ");
        $stmt->execute([$adminRoleId, $adminId, $adminId]);
        $n = (int) $stmt->fetchColumn();
        $rows[] = [5, 'Admin › Inbox – Accepted', $n, ceil($n / 20), $n >= 41 ? 'PASS' : 'FAIL'];

        // 6. Admin › Routed Documents
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM (
                SELECT dr.document_id
                FROM document_routes dr
                INNER JOIN documents d ON d.id = dr.document_id
                WHERE dr.routed_by = ? AND dr.from_phase = 'ADMIN'
                  AND dr.to_phase IN ('SP_SECRETARY','PLENARY','COMMITTEE')
                  AND d.tracking_year = 9999
                GROUP BY dr.document_id
            ) sub
        ");
        $stmt->execute([$adminId]);
        $n = (int) $stmt->fetchColumn();
        $rows[] = [6, 'Admin › Routed Documents', $n, ceil($n / 20), $n >= 41 ? 'PASS' : 'FAIL'];

        // 7. SP Secretary › Inbox – Pending
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT da.document_id)
            FROM document_assignments da
            INNER JOIN documents d ON da.document_id = d.id
            WHERE da.assigned_to_role_id = ? AND da.phase = 'SP_SECRETARY'
              AND da.decision = 'PENDING' AND da.completed_at IS NULL
              AND da.accepted_by IS NULL
              AND d.tracking_year = 9999
        ");
        $stmt->execute([$spsecRoleId]);
        $n = (int) $stmt->fetchColumn();
        $rows[] = [7, 'SP Secretary › Inbox – Pending', $n, ceil($n / 20), $n >= 41 ? 'PASS' : 'FAIL'];

        // 8. SP Secretary › Inbox – Accepted
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT da.document_id)
            FROM document_assignments da
            INNER JOIN documents d ON da.document_id = d.id
            WHERE da.assigned_to_role_id = ? AND da.phase = 'SP_SECRETARY'
              AND da.decision = 'ACCEPTED'
              AND (da.accepted_by = ? OR da.assigned_to_user_id = ?)
              AND d.tracking_year = 9999
        ");
        $stmt->execute([$spsecRoleId, $spsecId, $spsecId]);
        $n = (int) $stmt->fetchColumn();
        $rows[] = [8, 'SP Secretary › Inbox – Accepted', $n, ceil($n / 20), $n >= 41 ? 'PASS' : 'FAIL'];

        // 9. SP Secretary › Routed Documents
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM (
                SELECT dr.document_id
                FROM document_routes dr
                INNER JOIN documents d ON d.id = dr.document_id
                WHERE dr.from_phase = 'SP_SECRETARY'
                  AND dr.to_phase IN ('PLENARY','COMMITTEE')
                  AND d.tracking_year = 9999
                GROUP BY dr.document_id
            ) sub
        ");
        $stmt->execute([]);
        $n = (int) $stmt->fetchColumn();
        $rows[] = [9, 'SP Secretary › Routed Documents', $n, ceil($n / 20), $n >= 41 ? 'PASS' : 'FAIL'];

        // 10. SP Secretary › Communications
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT d.id)
            FROM documents d
            INNER JOIN (
                SELECT dr.document_id
                FROM document_routes dr
                INNER JOIN routing_options ro ON ro.id = dr.routing_option_id AND ro.name = 'Noted' AND ro.is_deleted = 0
                WHERE dr.from_phase = 'SP_SECRETARY' AND dr.to_phase = 'SP_SECRETARY'
                GROUP BY dr.document_id
            ) noted ON noted.document_id = d.id
            INNER JOIN document_types dt ON dt.id = d.document_type_id AND dt.name = 'Communication' AND dt.is_deleted = 0
            WHERE d.tracking_year = 9999
        ");
        $stmt->execute([]);
        $n = (int) $stmt->fetchColumn();
        $rows[] = [10, 'SP Secretary › Communications', $n, ceil($n / 20), $n >= 41 ? 'PASS' : 'FAIL'];

        // 11. Committee › Inbox – Pending
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT da.document_id)
            FROM document_assignments da
            INNER JOIN documents d ON da.document_id = d.id
            WHERE da.assigned_to_role_id = ? AND da.phase = 'COMMITTEE'
              AND da.decision = 'PENDING' AND da.completed_at IS NULL
              AND da.accepted_by IS NULL
              AND d.tracking_year = 9999
        ");
        $stmt->execute([$commRoleId]);
        $n = (int) $stmt->fetchColumn();
        $rows[] = [11, 'Committee › Inbox – Pending', $n, ceil($n / 20), $n >= 41 ? 'PASS' : 'FAIL'];

        // 12. Committee › Inbox – Accepted
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT da.document_id)
            FROM document_assignments da
            INNER JOIN documents d ON da.document_id = d.id
            WHERE da.assigned_to_role_id = ? AND da.phase = 'COMMITTEE'
              AND da.decision = 'ACCEPTED' AND da.completed_at IS NULL
              AND (da.accepted_by = ? OR da.assigned_to_user_id = ?)
              AND d.tracking_year = 9999
        ");
        $stmt->execute([$commRoleId, $committeeId, $committeeId]);
        $n = (int) $stmt->fetchColumn();
        $rows[] = [12, 'Committee › Inbox – Accepted', $n, ceil($n / 20), $n >= 41 ? 'PASS' : 'FAIL'];

        // 13. Committee › Cases
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM committee_cases cc
            INNER JOIN documents d ON cc.document_id = d.id
            WHERE cc.assigned_by = ? AND cc.final_outcome IS NULL AND d.tracking_year = 9999
        ");
        $stmt->execute([$committeeId]);
        $n = (int) $stmt->fetchColumn();
        $rows[] = [13, 'Committee › Cases', $n, ceil($n / 20), $n >= 41 ? 'PASS' : 'FAIL'];

        // 14. Committee › Communications
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM committee_communications cc
            INNER JOIN documents d ON cc.document_id = d.id
            WHERE cc.assigned_by = ? AND d.tracking_year = 9999
        ");
        $stmt->execute([$committeeId]);
        $n = (int) $stmt->fetchColumn();
        $rows[] = [14, 'Committee › Communications', $n, ceil($n / 20), $n >= 41 ? 'PASS' : 'FAIL'];

        // 15. Committee › Referred – referred tab
        $n = (int) $pdo->prepare("
            SELECT COUNT(DISTINCT d.id)
            FROM documents d
            INNER JOIN (
                SELECT document_id, MAX(id) AS max_cycle_id
                FROM committee_cycles WHERE completed_at IS NOT NULL
                GROUP BY document_id
            ) lc ON lc.document_id = d.id
            INNER JOIN committee_cycles cc ON cc.id = lc.max_cycle_id
            LEFT  JOIN committee_cycle_endorsements cce ON cce.cycle_id = cc.id
            LEFT  JOIN committee_opinion_resolutions cor ON cor.cycle_id = cc.id
            WHERE cce.id IS NULL AND cor.id IS NULL AND d.tracking_year = 9999
        ")->execute([]) ? 0 : 0;
        $stmt = $pdo->query("
            SELECT COUNT(DISTINCT d.id)
            FROM documents d
            INNER JOIN (
                SELECT document_id, MAX(id) AS max_cycle_id
                FROM committee_cycles WHERE completed_at IS NOT NULL
                GROUP BY document_id
            ) lc ON lc.document_id = d.id
            INNER JOIN committee_cycles cc ON cc.id = lc.max_cycle_id
            LEFT  JOIN committee_cycle_endorsements cce ON cce.cycle_id = cc.id
            LEFT  JOIN committee_opinion_resolutions cor ON cor.cycle_id = cc.id
            WHERE cce.id IS NULL AND cor.id IS NULL AND d.tracking_year = 9999
        ");
        $n = (int) $stmt->fetchColumn();
        $rows[] = [15, 'Committee › Referred – referred tab', $n, ceil($n / 20), $n >= 41 ? 'PASS' : 'FAIL'];

        // 16. Committee › Hearings – all tab (documents with agendas)
        $n = (int) $pdo->query("
            SELECT COUNT(DISTINCT d.id)
            FROM documents d
            INNER JOIN agendas ag ON ag.document_id = d.id
            WHERE d.tracking_year = 9999
        ")->fetchColumn();
        $rows[] = [16, 'Committee › Hearings – all tab', $n, ceil($n / 20), $n >= 41 ? 'PASS' : 'FAIL'];

        // 17. Committee › Hearings – scheduled tab (On Going + agenda, no hearing)
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT d.id)
            FROM documents d
            INNER JOIN agendas ag ON ag.document_id = d.id
            LEFT  JOIN committee_hearings ch ON ch.document_id = d.id
            WHERE d.current_status_id = ? AND ch.id IS NULL AND d.tracking_year = 9999
        ");
        $stmt->execute([$onGoingId]);
        $n = (int) $stmt->fetchColumn();
        $rows[] = [17, 'Committee › Hearings – scheduled tab', $n, ceil($n / 20), $n >= 41 ? 'PASS' : 'FAIL'];

        // 18. Committee › Reports
        $n = (int) $pdo->query("
            SELECT COUNT(DISTINCT cr.id)
            FROM committee_reports cr
            WHERE cr.report_number LIKE 'RPT-9999-%'
        ")->fetchColumn();
        $rows[] = [18, 'Committee › Reports', $n, ceil($n / 20), $n >= 41 ? 'PASS' : 'FAIL'];

        // ── Print table ───────────────────────────────────────────────────────
        $colWidths = [3, 46, 12, 12, 6];
        $separator = '+' . implode('+', array_map(fn($w) => str_repeat('-', $w + 2), $colWidths)) . '+';
        echo $separator . PHP_EOL;
        foreach ($rows as $idx => $row) {
            $line = '|';
            foreach ($row as $ci => $cell) {
                $line .= ' ' . str_pad((string) $cell, $colWidths[$ci]) . ' |';
            }
            echo $line . PHP_EOL;
            if ($idx === 0) {
                echo $separator . PHP_EOL;
            }
        }
        echo $separator . PHP_EOL;

        // Summary
        $passed = count(array_filter($rows, fn($r) => isset($r[4]) && $r[4] === 'PASS'));
        $total  = count($rows) - 1; // exclude header
        echo PHP_EOL . "Result: {$passed}/{$total} contexts PASS." . PHP_EOL;
        if ($passed < $total) {
            echo "Run 'php database/run_pagination_test.php seed' to seed missing data." . PHP_EOL;
        }
        echo PHP_EOL;
        break;

    // ─────────────────────────────────────────────────────────────────────────
    default:
        echo PHP_EOL;
        echo "Usage: php database/run_pagination_test.php <command>" . PHP_EOL . PHP_EOL;
        echo "  seed     Insert pagination test data (idempotent — safe to rerun)" . PHP_EOL;
        echo "  cleanup  Remove ALL test data created by the seeder" . PHP_EOL;
        echo "  verify   Count qualifying rows per paginated context" . PHP_EOL . PHP_EOL;
        break;
}

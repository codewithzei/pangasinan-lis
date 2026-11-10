<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';

$pdo = (new Database())->connect();
$communicationStmt = $pdo->query("
    SELECT cc.id, cc.document_id, cc.assigned_by
    FROM committee_communications cc
    WHERE cc.agenda_id IS NULL
    ORDER BY cc.id
    LIMIT 1
");
$communication = $communicationStmt->fetch(PDO::FETCH_ASSOC);

if (!$communication) {
    echo "SKIP: No unscheduled Communication record is available for the agenda-cycle test." . PHP_EOL;
    exit(0);
}

$committeeId = (int) $pdo->query("
    SELECT id FROM committees
    WHERE is_active = 1 AND is_deleted = 0
    ORDER BY id
    LIMIT 1
")->fetchColumn();
if ($committeeId <= 0) {
    fwrite(STDERR, "FAIL: No active committee is available for the test." . PHP_EOL);
    exit(1);
}

$userId = (int) ($communication['assigned_by'] ?? 0);
if ($userId <= 0) {
    $userId = (int) $pdo->query("
        SELECT ua.id
        FROM user_accounts ua
        INNER JOIN roles r ON r.id = ua.role_id
        WHERE r.name = 'Committee' AND ua.status = 'active' AND ua.is_deleted = 0
        ORDER BY ua.id
        LIMIT 1
    ")->fetchColumn();
}

$pdo->beginTransaction();
try {
    $cycleNumberStmt = $pdo->prepare("
        SELECT COALESCE(MAX(cycle_number), 0) + 1
        FROM committee_cycles
        WHERE document_id = ?
        FOR UPDATE
    ");
    $cycleNumberStmt->execute([(int) $communication['document_id']]);
    $cycleNumber = (int) $cycleNumberStmt->fetchColumn();

    $pdo->prepare("
        INSERT INTO committee_cycles (
            document_id, cycle_number, referred_by, accepted_by,
            accepted_at, decision, remarks, started_at
        ) VALUES (?, ?, ?, ?, NOW(), 'ACCEPTED', ?, NOW())
    ")->execute([
        (int) $communication['document_id'],
        $cycleNumber,
        $userId ?: null,
        $userId ?: null,
        'Agenda-cycle regression test.',
    ]);
    $cycleId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT IGNORE INTO committee_cycle_committees (cycle_id, committee_id) VALUES (?, ?)'
    )->execute([$cycleId, $committeeId]);

    $pdo->prepare("
        INSERT INTO agendas (
            document_id, cycle_id, agenda_number, agenda_type,
            agenda_date, agenda_time, venue, created_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ")->execute([
        (int) $communication['document_id'],
        $cycleId,
        'AGENDA-CYCLE-TEST',
        'Regression Test',
        date('Y-m-d'),
        '10:00:00',
        'Regression Test Venue',
        $userId ?: null,
    ]);
    $agendaId = (int) $pdo->lastInsertId();

    $verifyStmt = $pdo->prepare("
        SELECT a.cycle_id, c.document_id
        FROM agendas a
        INNER JOIN committee_cycles c ON c.id = a.cycle_id
        WHERE a.id = ?
        LIMIT 1
    ");
    $verifyStmt->execute([$agendaId]);
    $linkedRows = $verifyStmt->fetch(PDO::FETCH_ASSOC);
    if (!$linkedRows
        || (int) $linkedRows['cycle_id'] !== $cycleId
        || (int) $linkedRows['document_id'] !== (int) $communication['document_id']
    ) {
        throw new RuntimeException('Agenda did not link to the expected Communication committee cycle.');
    }

    $pdo->rollBack();
    echo 'PASS: agenda insert references the Communication committee cycle.' . PHP_EOL;
    echo 'PASS: regression transaction rolled back without changing the Communication.' . PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
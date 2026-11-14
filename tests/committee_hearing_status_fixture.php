<?php
/**
 * Local fixture for mixed-status shared Committee Report testing.
 *
 * Creates one Withdrawn and one Remanded hearing document that can be selected
 * together from Committee > Hearings > All Hearings.
 *
 * Run:     php tests/committee_hearing_status_fixture.php
 * Cleanup: php tests/committee_hearing_status_fixture.php --cleanup
 */

declare(strict_types=1);

const FIXTURE_YEAR = 1900;
const FIXTURE_MARKER = '[COMMITTEE-HEARING-FIXTURE]';

$fixtures = [
    ['sequence' => 99991, 'tracking' => 'FX-COMM-WD-1900', 'outcome' => 'WITHDRAWN', 'status' => 'Withdrawn'],
    ['sequence' => 99992, 'tracking' => 'FX-COMM-RM-1900', 'outcome' => 'REMANDED', 'status' => 'Remanded'],
];

try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;port=3306;dbname=pangasinan_lis;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    fwrite(STDERR, 'Cannot connect to the local development database: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$isCleanup = in_array('--cleanup', $argv ?? [], true);
$documents = [];
$findDocument = $pdo->prepare(
    'SELECT id, tracking_number, subject_matter FROM documents WHERE tracking_year = ? AND tracking_sequence = ? LIMIT 1'
);
foreach ($fixtures as $fixture) {
    $findDocument->execute([FIXTURE_YEAR, $fixture['sequence']]);
    $document = $findDocument->fetch();
    if ($document && !str_starts_with((string) $document['subject_matter'], FIXTURE_MARKER)) {
        fwrite(STDERR, "Reserved fixture sequence {$fixture['sequence']} is already used by another document; no changes made." . PHP_EOL);
        exit(1);
    }
    if ($document) {
        $documents[] = $document;
    }
}

if ($isCleanup) {
    if (count($documents) !== count($fixtures)) {
        echo "No complete Committee Hearing fixture found; nothing removed." . PHP_EOL;
        exit(0);
    }

    $documentIds = array_map('intval', array_column($documents, 'id'));
    $placeholders = implode(',', array_fill(0, count($documentIds), '?'));
    $linked = $pdo->prepare("SELECT COUNT(*) FROM committee_report_documents WHERE document_id IN ({$placeholders})");
    $linked->execute($documentIds);
    if ((int) $linked->fetchColumn() > 0) {
        fwrite(STDERR, 'Fixture documents are linked to a Committee Report; leaving them untouched.' . PHP_EOL);
        exit(1);
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM committee_hearings WHERE document_id IN ({$placeholders})")->execute($documentIds);
        $agendaIdsStmt = $pdo->prepare("SELECT id FROM agendas WHERE document_id IN ({$placeholders})");
        $agendaIdsStmt->execute($documentIds);
        $agendaIds = array_map('intval', array_column($agendaIdsStmt->fetchAll(), 'id'));
        if ($agendaIds !== []) {
            $agendaPlaceholders = implode(',', array_fill(0, count($agendaIds), '?'));
            $pdo->prepare("DELETE FROM agenda_committees WHERE agenda_id IN ({$agendaPlaceholders})")->execute($agendaIds);
            $pdo->prepare("DELETE FROM agendas WHERE id IN ({$agendaPlaceholders})")->execute($agendaIds);
        }
        $pdo->prepare("DELETE FROM committee_cycles WHERE document_id IN ({$placeholders})")->execute($documentIds);
        $pdo->prepare("DELETE FROM documents WHERE id IN ({$placeholders})")->execute($documentIds);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    echo 'Removed the two standalone Committee Hearing fixture documents.' . PHP_EOL;
    exit(0);
}

if ($documents !== []) {
    if (count($documents) === count($fixtures)) {
        echo 'Fixture already exists; no changes made.' . PHP_EOL;
        $statusStmt = $pdo->prepare(
            "SELECT ch.outcome, ds.name AS document_status
             FROM committee_hearings ch
             INNER JOIN documents d ON d.id = ch.document_id
             LEFT JOIN document_statuses ds ON ds.id = d.current_status_id
             WHERE ch.document_id = ?
             ORDER BY ch.id DESC LIMIT 1"
        );
        foreach ($documents as $document) {
            $statusStmt->execute([(int) $document['id']]);
            $status = $statusStmt->fetch() ?: [];
            echo $document['tracking_number']
                . ' | ' . ($status['outcome'] ?? 'NO HEARING')
                . ' | ' . ($status['document_status'] ?? 'NO STATUS')
                . ' | document ID ' . $document['id'] . PHP_EOL;
        }
        exit(0);
    }
    fwrite(STDERR, 'A partial fixture exists; run --cleanup before recreating it.' . PHP_EOL);
    exit(1);
}

$sourceTypeId = (int) $pdo->query(
    'SELECT id FROM source_types WHERE is_active = 1 AND is_deleted = 0 ORDER BY id LIMIT 1'
)->fetchColumn();
$documentTypeId = (int) $pdo->query(
    'SELECT id FROM document_types WHERE is_active = 1 AND is_deleted = 0 ORDER BY id LIMIT 1'
)->fetchColumn();
$committeeId = (int) $pdo->query(
    'SELECT id FROM committees WHERE is_active = 1 AND is_deleted = 0 ORDER BY sort_order, id LIMIT 1'
)->fetchColumn();
$userId = (int) $pdo->query(
    "SELECT ua.id FROM user_accounts ua INNER JOIN roles r ON r.id = ua.role_id
      WHERE ua.status = 'active' AND ua.is_deleted = 0 AND r.name IN ('Committee', 'Super Admin')
      ORDER BY CASE WHEN r.name = 'Committee' THEN 0 ELSE 1 END, ua.id LIMIT 1"
)->fetchColumn();

if (!$sourceTypeId || !$documentTypeId || !$committeeId || !$userId) {
    fwrite(STDERR, 'Fixture prerequisites missing: active source type, document type, committee, or user.' . PHP_EOL);
    exit(1);
}

$statusLookup = $pdo->prepare(
    'SELECT id FROM document_statuses WHERE name = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1'
);
$insertDocument = $pdo->prepare(
    "INSERT INTO documents
        (tracking_number, tracking_year, tracking_sequence, subject_matter,
         document_type_id, source_type_id, current_status_id, date_received,
         time_received, current_phase, created_by, updated_by)
     VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME(), 'COMMITTEE', ?, ?)"
);
$insertCycle = $pdo->prepare(
    "INSERT INTO committee_cycles (document_id, cycle_number, decision, started_at)
     VALUES (?, ?, 'ACCEPTED', NOW())"
);
$insertAgenda = $pdo->prepare(
    "INSERT INTO agendas (document_id, cycle_id, agenda_date, agenda_time, venue, created_by)
     VALUES (?, ?, CURDATE(), '09:00:00', 'Committee Hearing Fixture', ?)"
);
$insertAgendaCommittee = $pdo->prepare(
    'INSERT INTO agenda_committees (agenda_id, committee_id) VALUES (?, ?)'
);
$insertHearing = $pdo->prepare(
    'INSERT INTO committee_hearings (document_id, agenda_id, outcome, performed_by, performed_at) VALUES (?, ?, ?, ?, NOW())'
);

$pdo->beginTransaction();
try {
    foreach ($fixtures as $fixture) {
        $statusLookup->execute([$fixture['status']]);
        $statusId = (int) $statusLookup->fetchColumn();
        if (!$statusId) {
            throw new RuntimeException("Document status '{$fixture['status']}' is unavailable.");
        }

        $insertDocument->execute([
            $fixture['tracking'],
            FIXTURE_YEAR,
            $fixture['sequence'],
            FIXTURE_MARKER . ' Sample document for mixed-status shared Committee Report testing',
            $documentTypeId,
            $sourceTypeId,
            $statusId,
            $userId,
            $userId,
        ]);
        $documentId = (int) $pdo->lastInsertId();

        $insertCycle->execute([$documentId, $fixture['sequence']]);
        $cycleId = (int) $pdo->lastInsertId();
        $insertAgenda->execute([$documentId, $cycleId, $userId]);
        $agendaId = (int) $pdo->lastInsertId();
        $insertAgendaCommittee->execute([$agendaId, $committeeId]);
        $insertHearing->execute([$documentId, $agendaId, $fixture['outcome'], $userId]);

        echo $fixture['tracking'] . ' | ' . $fixture['outcome'] . ' | document ID ' . $documentId . PHP_EOL;
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Fixture creation failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo 'Open Committee > Hearings > All Hearings, select both documents, then add them to one shared report.' . PHP_EOL;
echo 'Cleanup before linking them to a report: php tests/committee_hearing_status_fixture.php --cleanup' . PHP_EOL;

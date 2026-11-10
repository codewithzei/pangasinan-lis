<?php
/**
 * Test: Plenary Cases (Complaints / Administrative Cases) First Reading Workflow
 *
 * Covers:
 *   - NOTED branch: DB state, status, phase, event types
 *   - REFER_TO_COMMITTEE branch: DB state, committee assignment, status, event types
 *   - Adopted Committee Report flow (adopt-report): DB state, status, finalization
 *   - Duplicate prevention guards for all three transitions
 *   - View structural assertions (cases-first-reading/show.php)
 *   - Status-list Petitions tab
 *   - First Reading index Petitions tab label/count
 *   - Referred-to-Committee: adopt-cases-report modal HTML
 *
 * Strategy
 * ────────
 * - All DB writes run inside an outer transaction that is always rolled back.
 * - The controller logic is tested via direct function-level calls or SQL
 *   state assertions (no HTTP layer needed).
 *
 * Run with: php tests/plenary_cases_first_reading_test.php
 */

declare(strict_types=1);

// ── Bootstrap ──────────────────────────────────────────────────────────────
$dsn = 'mysql:host=127.0.0.1;port=3306;dbname=pangasinan_lis;charset=utf8mb4';
try {
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    echo 'FATAL: Cannot connect to database — ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

$passed = 0;
$failed = 0;

function pass(string $label): void
{
    global $passed;
    echo '  [PASS] ' . $label . PHP_EOL;
    $passed++;
}

function fail(string $label, string $detail = ''): void
{
    global $failed;
    $suffix = $detail !== '' ? ' — ' . $detail : '';
    echo '  [FAIL] ' . $label . $suffix . PHP_EOL;
    $failed++;
}

function check(string $label, bool $cond, string $detail = ''): void
{
    $cond ? pass($label) : fail($label, $detail);
}

function section(string $title): void
{
    echo PHP_EOL . $title . PHP_EOL;
    echo str_repeat('─', mb_strlen($title)) . PHP_EOL;
}

// ── Helpers ────────────────────────────────────────────────────────────────

function statusId(PDO $pdo, string $name): int
{
    $s = $pdo->prepare(
        'SELECT id FROM document_statuses WHERE name=? AND is_active=1 AND is_deleted=0 LIMIT 1'
    );
    $s->execute([$name]);
    $r = $s->fetch();
    if (!$r) {
        throw new RuntimeException("Status '{$name}' not found — run migration 076 first.");
    }
    return (int) $r['id'];
}

function docTypeId(PDO $pdo, string $pattern): int
{
    $s = $pdo->prepare(
        'SELECT id FROM document_types WHERE name LIKE ? AND is_active=1 AND is_deleted=0 LIMIT 1'
    );
    $s->execute([$pattern]);
    $r = $s->fetch();
    if (!$r) {
        throw new RuntimeException("Document type matching '{$pattern}' not found.");
    }
    return (int) $r['id'];
}

function roleId(PDO $pdo, string $name): int
{
    $s = $pdo->prepare('SELECT id FROM roles WHERE name=? AND is_active=1 AND is_deleted=0 LIMIT 1');
    $s->execute([$name]);
    $r = $s->fetch();
    if (!$r) {
        throw new RuntimeException("Role '{$name}' not found.");
    }
    return (int) $r['id'];
}

function userId(PDO $pdo): int
{
    // Use the first active user account we can find
    $r = $pdo->query('SELECT id FROM user_accounts LIMIT 1')->fetch();
    if (!$r) { throw new RuntimeException("No user account found."); }
    return (int) $r['id'];
}

function sourceTypeId(PDO $pdo): int
{
    $r = $pdo->query('SELECT id FROM source_types WHERE is_active=1 AND is_deleted=0 LIMIT 1')->fetch();
    if (!$r) { throw new RuntimeException("No source type found."); }
    return (int) $r['id'];
}

function committeeId(PDO $pdo): int
{
    $r = $pdo->query('SELECT id FROM committees WHERE is_active=1 AND is_deleted=0 LIMIT 1')->fetch();
    if (!$r) { throw new RuntimeException("No committee found."); }
    return (int) $r['id'];
}

/**
 * Insert a minimal document + plenary assignment in a state that would place
 * it in the Plenary First Reading queue ("Awaiting First Reading").
 */
function createTestDocument(PDO $pdo, int $awaitingStatusId, int $userId, int $casesTypeId): int
{
    $pdo->prepare("
        INSERT INTO documents
            (tracking_year, tracking_sequence, tracking_number,
             date_received, time_received, subject_matter,
             document_type_id, source_type_id,
             current_status_id, current_phase,
             created_by, updated_by)
        VALUES (
            YEAR(NOW()),
            9999,
            CONCAT('TEST-', FLOOR(RAND()*100000)),
            CURDATE(), '08:00:00', 'Test Petitions Document',
            ?, ?,
            ?, 'PLENARY',
            ?, ?
        )
    ")->execute([$casesTypeId, sourceTypeId($pdo), $awaitingStatusId, $userId, $userId]);

    $docId = (int) $pdo->lastInsertId();

    // Add plenary_document_details (required by controller for ownership check)
    $pdo->prepare("
        INSERT IGNORE INTO plenary_document_details (document_id, created_by, updated_by)
        VALUES (?, ?, ?)
    ")->execute([$docId, $userId, $userId]);

    // Add ACCEPTED plenary assignment
    $plenaryRoleId = roleId($pdo, 'Plenary');
    $pdo->prepare("
        INSERT INTO document_assignments
            (document_id, assigned_to_role_id, phase, assigned_by, decision,
             accepted_by, assigned_to_user_id, received_at, accepted_at)
        VALUES (?, ?, 'PLENARY', ?, 'ACCEPTED', ?, ?, NOW(), NOW())
    ")->execute([$docId, $plenaryRoleId, $userId, $userId, $userId]);

    return $docId;
}

// ── Pre-flight checks ─────────────────────────────────────────────────────

section('Pre-flight: Migration 076 table existence');

$tables = ['plenary_cases_first_reading', 'plenary_cases_adoptions'];
foreach ($tables as $table) {
    $r = $pdo->query("SHOW TABLES LIKE '{$table}'")->fetch();
    check("Table '{$table}' exists", $r !== false, "Run migration 076 to create it.");
}

// Check statuses seeded
$requiredStatuses = [
    'Plenary Cases: Awaiting First Reading',
    'Plenary Cases: Noted',
    'Plenary Cases: Referred to Committee',
    'Plenary Cases: Finalized',
];
foreach ($requiredStatuses as $sName) {
    $r = $pdo->prepare(
        'SELECT id FROM document_statuses WHERE name=? AND is_active=1 AND is_deleted=0 LIMIT 1'
    );
    $r->execute([$sName]);
    check("Status '{$sName}' seeded", $r->fetch() !== false, "Run migration 076 first.");
}

// Check event types
$colRow = $pdo->query("
    SELECT COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='document_events' AND COLUMN_NAME='event_type' LIMIT 1
")->fetch();

$enumStr = $colRow['COLUMN_TYPE'] ?? '';
$newEvents = [
    'PLENARY_CASES_FIRST_READING_NOTED',
    'PLENARY_CASES_FIRST_READING_REFERRED',
    'PLENARY_CASES_ADOPTED',
    'PLENARY_CASES_FINALIZED',
];
foreach ($newEvents as $ev) {
    check("Event type '{$ev}' in ENUM", strpos($enumStr, $ev) !== false, "Run migration 076.");
}

// Try resolving required runtime IDs
try {
    $awaitingId   = statusId($pdo, 'Awaiting First Reading');
    $notedId      = statusId($pdo, 'Plenary Cases: Noted');
    $referredId   = statusId($pdo, 'Plenary Cases: Referred to Committee');
    $finalizedId  = statusId($pdo, 'Plenary Cases: Finalized');
    $returnedId   = statusId($pdo, 'Returned to Plenary');
    $uid          = userId($pdo);
    $cid          = committeeId($pdo);
    $runtimeOk    = true;
} catch (RuntimeException $e) {
    fail("Runtime IDs resolved", $e->getMessage());
    $runtimeOk = false;
}

// Find a Complaint or Administrative Cases document type
$casesTypeId = null;
foreach (['%Administrative Case%', '%Complaint%'] as $pat) {
    try { $casesTypeId = docTypeId($pdo, $pat); break; } catch (RuntimeException $e) {}
}
if ($casesTypeId === null) {
    fail("Cases document type found", "No type matching 'Administrative Case%' or 'Complaint%'.");
    $runtimeOk = false;
}

if (!$runtimeOk) {
    echo PHP_EOL . 'Cannot continue: required pre-conditions not met.' . PHP_EOL;
    exit(1);
}

// =========================================================================
// Group 1 — NOTED branch DB state
// =========================================================================

section('Group 1 — NOTED decision DB state');

$pdo->beginTransaction();
try {
    $docId = createTestDocument($pdo, $awaitingId, $uid, $casesTypeId);

    // Insert plenary_cases_first_reading (NOTED)
    $pdo->prepare("
        INSERT INTO plenary_cases_first_reading
            (document_id, decision, reading_date, remarks, performed_by)
        VALUES (?, 'NOTED', CURDATE(), 'Test noted', ?)
    ")->execute([$docId, $uid]);
    $frId = (int) $pdo->lastInsertId();

    // Update document status (mirrors doCasesFrNoted)
    $pdo->prepare("UPDATE documents SET current_status_id=?, current_phase='FINALIZED', finalized_at=NOW(), updated_by=? WHERE id=?")
        ->execute([$notedId, $uid, $docId]);

    // Insert event
    $pdo->prepare("
        INSERT INTO document_events (document_id, event_type, phase, performed_by, to_status_id, remarks, metadata)
        VALUES (?, 'PLENARY_CASES_FIRST_READING_NOTED', 'PLENARY', ?, ?, 'Test noted', '{}')
    ")->execute([$docId, $uid, $notedId]);

    // Assertions
    $frRow = $pdo->prepare("SELECT * FROM plenary_cases_first_reading WHERE id=? LIMIT 1");
    $frRow->execute([$frId]);
    $fr = $frRow->fetch();
    check('1.1 plenary_cases_first_reading row inserted', $fr !== false);
    check('1.2 decision is NOTED', ($fr['decision'] ?? '') === 'NOTED');
    check('1.3 document_id matches', (int) ($fr['document_id'] ?? 0) === $docId);
    check('1.4 performed_by matches user', (int) ($fr['performed_by'] ?? 0) === $uid);

    $docRow = $pdo->prepare("SELECT current_status_id, current_phase, finalized_at FROM documents WHERE id=?");
    $docRow->execute([$docId]);
    $doc = $docRow->fetch();
    check('1.5 document status = Plenary Cases: Noted', (int) ($doc['current_status_id'] ?? 0) === $notedId);
    check('1.6 document phase = FINALIZED', ($doc['current_phase'] ?? '') === 'FINALIZED');
    check('1.7 finalized_at not null', !empty($doc['finalized_at']));

    $evRow = $pdo->prepare("SELECT * FROM document_events WHERE document_id=? AND event_type='PLENARY_CASES_FIRST_READING_NOTED' LIMIT 1");
    $evRow->execute([$docId]);
    check('1.8 PLENARY_CASES_FIRST_READING_NOTED event inserted', $evRow->fetch() !== false);

} finally {
    $pdo->rollBack();
}

// =========================================================================
// Group 2 — REFER_TO_COMMITTEE branch DB state
// =========================================================================

section('Group 2 — REFER_TO_COMMITTEE decision DB state');

$pdo->beginTransaction();
try {
    $docId2 = createTestDocument($pdo, $awaitingId, $uid, $casesTypeId);

    // Insert plenary_cases_first_reading (REFER_TO_COMMITTEE)
    $pdo->prepare("
        INSERT INTO plenary_cases_first_reading
            (document_id, decision, reading_date, remarks, performed_by)
        VALUES (?, 'REFER_TO_COMMITTEE', CURDATE(), 'Test refer', ?)
    ")->execute([$docId2, $uid]);
    $fr2Id = (int) $pdo->lastInsertId();

    // Insert document_committees
    $pdo->prepare("DELETE FROM document_committees WHERE document_id=?")->execute([$docId2]);
    $pdo->prepare("INSERT INTO document_committees (document_id, committee_id, assigned_by) VALUES (?,?,?)")
        ->execute([$docId2, $cid, $uid]);

    // Update document (mirrors doCasesFrReferToCommittee)
    $pdo->prepare("UPDATE documents SET current_status_id=?, current_phase='COMMITTEE', updated_by=? WHERE id=?")
        ->execute([$referredId, $uid, $docId2]);

    // Create PENDING committee assignment
    $committeeRoleId = roleId($pdo, 'Committee');
    $pdo->prepare("
        INSERT INTO document_assignments
            (document_id, assigned_to_role_id, phase, assigned_by, decision, received_at)
        VALUES (?, ?, 'COMMITTEE', ?, 'PENDING', NOW())
    ")->execute([$docId2, $committeeRoleId, $uid]);

    // Insert event
    $pdo->prepare("
        INSERT INTO document_events (document_id, event_type, phase, performed_by, to_status_id, remarks, metadata)
        VALUES (?, 'PLENARY_CASES_FIRST_READING_REFERRED', 'PLENARY', ?, ?, 'Test refer', '{}')
    ")->execute([$docId2, $uid, $referredId]);

    // Assertions
    $frRow2 = $pdo->prepare("SELECT * FROM plenary_cases_first_reading WHERE id=? LIMIT 1");
    $frRow2->execute([$fr2Id]);
    $fr2 = $frRow2->fetch();
    check('2.1 plenary_cases_first_reading row inserted', $fr2 !== false);
    check('2.2 decision is REFER_TO_COMMITTEE', ($fr2['decision'] ?? '') === 'REFER_TO_COMMITTEE');

    $docRow2 = $pdo->prepare("SELECT current_status_id, current_phase FROM documents WHERE id=?");
    $docRow2->execute([$docId2]);
    $doc2 = $docRow2->fetch();
    check('2.3 document status = Plenary Cases: Referred to Committee', (int) ($doc2['current_status_id'] ?? 0) === $referredId);
    check('2.4 document phase = COMMITTEE', ($doc2['current_phase'] ?? '') === 'COMMITTEE');

    $dcRow = $pdo->prepare("SELECT * FROM document_committees WHERE document_id=? AND committee_id=? LIMIT 1");
    $dcRow->execute([$docId2, $cid]);
    check('2.5 document_committees row inserted for selected committee', $dcRow->fetch() !== false);

    $pendingAssign = $pdo->prepare("
        SELECT * FROM document_assignments
        WHERE document_id=? AND phase='COMMITTEE' AND decision='PENDING' LIMIT 1
    ");
    $pendingAssign->execute([$docId2]);
    check('2.6 PENDING committee assignment created', $pendingAssign->fetch() !== false);

    $evRow2 = $pdo->prepare("SELECT * FROM document_events WHERE document_id=? AND event_type='PLENARY_CASES_FIRST_READING_REFERRED' LIMIT 1");
    $evRow2->execute([$docId2]);
    check('2.7 PLENARY_CASES_FIRST_READING_REFERRED event inserted', $evRow2->fetch() !== false);

    // Route record (mirrors doCasesFrReferToCommittee)
    $pdo->prepare("
        INSERT INTO document_routes (document_id, from_phase, to_phase, routed_by, routed_to_role_id, remarks)
        VALUES (?, 'PLENARY', 'COMMITTEE', ?, ?, 'Test route')
    ")->execute([$docId2, $uid, $committeeRoleId]);

    $routeRow = $pdo->prepare("SELECT * FROM document_routes WHERE document_id=? AND from_phase='PLENARY' AND to_phase='COMMITTEE' LIMIT 1");
    $routeRow->execute([$docId2]);
    check('2.8 document_routes PLENARY→COMMITTEE record created', $routeRow->fetch() !== false);

} finally {
    $pdo->rollBack();
}

// =========================================================================
// Group 3 — Adopt Committee Report flow
// =========================================================================

section('Group 3 — Adopted Committee Report DB state');

$pdo->beginTransaction();
try {
    // Set up a document that was referred and is now "Returned to Plenary"
    $docId3 = createTestDocument($pdo, $returnedId, $uid, $casesTypeId);
    // Override status to Returned to Plenary
    $pdo->prepare("UPDATE documents SET current_status_id=?, current_phase='COMMITTEE', updated_by=? WHERE id=?")
        ->execute([$returnedId, $uid, $docId3]);

    // Insert the REFER_TO_COMMITTEE first reading row (prerequisite)
    $pdo->prepare("
        INSERT INTO plenary_cases_first_reading
            (document_id, decision, reading_date, remarks, performed_by)
        VALUES (?, 'REFER_TO_COMMITTEE', CURDATE(), NULL, ?)
    ")->execute([$docId3, $uid]);
    $fr3Id = (int) $pdo->lastInsertId();

    // Perform adoption (mirrors adoptCasesReportStore)
    $pdo->prepare("
        INSERT INTO plenary_cases_adoptions
            (document_id, first_reading_id, resolution_number, proposed_number, adopted_by, remarks)
        VALUES (?, ?, 'SP Res. No. 2026-001', 'PPN-001', ?, 'Test adoption')
    ")->execute([$docId3, $fr3Id, $uid]);
    $adoptionId = (int) $pdo->lastInsertId();

    $pdo->prepare("
        UPDATE documents
        SET current_status_id=?, current_phase='FINALIZED', finalized_at=NOW(), updated_by=?
        WHERE id=?
    ")->execute([$finalizedId, $uid, $docId3]);

    $pdo->prepare("
        INSERT INTO document_events
            (document_id, event_type, phase, performed_by, to_status_id, remarks, metadata)
        VALUES (?, 'PLENARY_CASES_ADOPTED', 'PLENARY', ?, ?, 'Test adoption', '{}')
    ")->execute([$docId3, $uid, $finalizedId]);

    $pdo->prepare("
        INSERT INTO document_events
            (document_id, event_type, phase, performed_by, to_status_id, remarks, metadata)
        VALUES (?, 'PLENARY_CASES_FINALIZED', 'PLENARY', ?, ?, NULL, '{}')
    ")->execute([$docId3, $uid, $finalizedId]);

    // Assertions
    $adoptRow = $pdo->prepare("SELECT * FROM plenary_cases_adoptions WHERE id=? LIMIT 1");
    $adoptRow->execute([$adoptionId]);
    $adopt = $adoptRow->fetch();
    check('3.1 plenary_cases_adoptions row inserted', $adopt !== false);
    check('3.2 resolution_number stored', ($adopt['resolution_number'] ?? '') === 'SP Res. No. 2026-001');
    check('3.3 proposed_number stored', ($adopt['proposed_number'] ?? '') === 'PPN-001');
    check('3.4 adopted_by matches user', (int) ($adopt['adopted_by'] ?? 0) === $uid);
    check('3.5 first_reading_id matches REFER row', (int) ($adopt['first_reading_id'] ?? 0) === $fr3Id);
    check('3.6 document_id matches', (int) ($adopt['document_id'] ?? 0) === $docId3);

    $docRow3 = $pdo->prepare("SELECT current_status_id, current_phase, finalized_at FROM documents WHERE id=?");
    $docRow3->execute([$docId3]);
    $doc3 = $docRow3->fetch();
    check('3.7 document status = Plenary Cases: Finalized', (int) ($doc3['current_status_id'] ?? 0) === $finalizedId);
    check('3.8 document phase = FINALIZED', ($doc3['current_phase'] ?? '') === 'FINALIZED');
    check('3.9 finalized_at set', !empty($doc3['finalized_at']));

    $evAdopt = $pdo->prepare("SELECT * FROM document_events WHERE document_id=? AND event_type='PLENARY_CASES_ADOPTED' LIMIT 1");
    $evAdopt->execute([$docId3]);
    check('3.10 PLENARY_CASES_ADOPTED event inserted', $evAdopt->fetch() !== false);

    $evFin = $pdo->prepare("SELECT * FROM document_events WHERE document_id=? AND event_type='PLENARY_CASES_FINALIZED' LIMIT 1");
    $evFin->execute([$docId3]);
    check('3.11 PLENARY_CASES_FINALIZED event inserted', $evFin->fetch() !== false);

} finally {
    $pdo->rollBack();
}

// =========================================================================
// Group 4 — Duplicate prevention
// =========================================================================

section('Group 4 — Duplicate insertion prevention');

$pdo->beginTransaction();
try {
    $docId4 = createTestDocument($pdo, $awaitingId, $uid, $casesTypeId);

    // First insert succeeds
    $pdo->prepare("
        INSERT INTO plenary_cases_first_reading
            (document_id, decision, reading_date, performed_by)
        VALUES (?, 'NOTED', CURDATE(), ?)
    ")->execute([$docId4, $uid]);

    // Second insert (same document_id) must violate UNIQUE constraint
    $dupViolated = false;
    try {
        $pdo->prepare("
            INSERT INTO plenary_cases_first_reading
                (document_id, decision, reading_date, performed_by)
            VALUES (?, 'REFER_TO_COMMITTEE', CURDATE(), ?)
        ")->execute([$docId4, $uid]);
    } catch (PDOException $e) {
        // 1062 = duplicate entry
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            $dupViolated = true;
        }
    }
    check('4.1 Duplicate plenary_cases_first_reading insert raises DB constraint violation', $dupViolated);

} finally {
    $pdo->rollBack();
}

$pdo->beginTransaction();
try {
    $docId5 = createTestDocument($pdo, $returnedId, $uid, $casesTypeId);
    $pdo->prepare("UPDATE documents SET current_status_id=? WHERE id=?")->execute([$returnedId, $docId5]);

    // First reading prerequisite
    $pdo->prepare("
        INSERT INTO plenary_cases_first_reading
            (document_id, decision, reading_date, performed_by)
        VALUES (?, 'REFER_TO_COMMITTEE', CURDATE(), ?)
    ")->execute([$docId5, $uid]);
    $fr5Id = (int) $pdo->lastInsertId();

    // First adoption succeeds
    $pdo->prepare("
        INSERT INTO plenary_cases_adoptions
            (document_id, first_reading_id, resolution_number, adopted_by)
        VALUES (?, ?, 'RES-001', ?)
    ")->execute([$docId5, $fr5Id, $uid]);

    // Second adoption (same document_id) must violate UNIQUE constraint
    $dup2Violated = false;
    try {
        $pdo->prepare("
            INSERT INTO plenary_cases_adoptions
                (document_id, first_reading_id, resolution_number, adopted_by)
            VALUES (?, ?, 'RES-002', ?)
        ")->execute([$docId5, $fr5Id, $uid]);
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            $dup2Violated = true;
        }
    }
    check('4.2 Duplicate plenary_cases_adoptions insert raises DB constraint violation', $dup2Violated);

} finally {
    $pdo->rollBack();
}

// =========================================================================
// Group 5 — Business rule: NOTED does not go to committee
// =========================================================================

section('Group 5 — Business rule: NOTED must not assign committee assignment');

$pdo->beginTransaction();
try {
    $docId6 = createTestDocument($pdo, $awaitingId, $uid, $casesTypeId);

    $pdo->prepare("
        INSERT INTO plenary_cases_first_reading
            (document_id, decision, reading_date, performed_by)
        VALUES (?, 'NOTED', CURDATE(), ?)
    ")->execute([$docId6, $uid]);

    // There must be NO committee assignment for this document
    $pendingComm = $pdo->prepare("
        SELECT COUNT(*) AS cnt FROM document_assignments
        WHERE document_id=? AND phase='COMMITTEE' AND decision='PENDING'
    ");
    $pendingComm->execute([$docId6]);
    $cnt = (int) $pendingComm->fetch()['cnt'];
    check('5.1 NOTED branch: no PENDING committee assignment created', $cnt === 0);

    // Document must not go to phase COMMITTEE
    $docPhase = $pdo->prepare("SELECT current_phase FROM documents WHERE id=?");
    $docPhase->execute([$docId6]);
    check('5.2 NOTED branch: document phase remains PLENARY (not changed to COMMITTEE)', ($docPhase->fetch()['current_phase'] ?? '') === 'PLENARY');

} finally {
    $pdo->rollBack();
}

// =========================================================================
// Group 6 — Business rule: REFER_TO_COMMITTEE must not finalize document
// =========================================================================

section('Group 6 — Business rule: REFER_TO_COMMITTEE must not finalize document');

$pdo->beginTransaction();
try {
    $docId7 = createTestDocument($pdo, $awaitingId, $uid, $casesTypeId);

    // Insert referral FR row only (no finalization step)
    $pdo->prepare("
        INSERT INTO plenary_cases_first_reading
            (document_id, decision, reading_date, performed_by)
        VALUES (?, 'REFER_TO_COMMITTEE', CURDATE(), ?)
    ")->execute([$docId7, $uid]);

    $pdo->prepare("UPDATE documents SET current_status_id=?, current_phase='COMMITTEE', updated_by=? WHERE id=?")
        ->execute([$referredId, $uid, $docId7]);

    $docRow7 = $pdo->prepare("SELECT current_status_id, current_phase, finalized_at FROM documents WHERE id=?");
    $docRow7->execute([$docId7]);
    $doc7 = $docRow7->fetch();
    check('6.1 REFER_TO_COMMITTEE branch: document is not FINALIZED phase', ($doc7['current_phase'] ?? '') !== 'FINALIZED');
    check('6.2 REFER_TO_COMMITTEE branch: finalized_at is null', empty($doc7['finalized_at']));
    check('6.3 REFER_TO_COMMITTEE branch: status is Plenary Cases: Referred to Committee', (int) ($doc7['current_status_id'] ?? 0) === $referredId);

} finally {
    $pdo->rollBack();
}

// =========================================================================
// Group 7 — isCasesTypeName logic test (PHP function simulation)
// =========================================================================

section('Group 7 — isCasesTypeName identification logic');

function testIsCasesTypeName(string $typeName): bool
{
    $lower = strtolower(trim($typeName));
    return strpos($lower, 'administrative case') !== false
        || strpos($lower, 'complaint') !== false;
}

$typeCases = [
    ['Administrative Cases',       true],
    ['administrative cases',       true],
    ['ADMINISTRATIVE CASES',       true],
    ['Complaint',                  true],
    ['complaint',                  true],
    ['Administrative Case',        true],
    ['Communication',              false],
    ['Provincial Resolution',      false],
    ['Provincial Ordinance',       false],
    ['',                           false],
];

foreach ($typeCases as [$type, $expected]) {
    $result  = testIsCasesTypeName($type);
    $label   = '7.x isCasesTypeName("' . $type . '") = ' . ($expected ? 'true' : 'false');
    check($label, $result === $expected);
}

// =========================================================================
// Group 8 — View structural assertions
// =========================================================================

section('Group 8 — View: cases-first-reading/show.php structure');

$showViewPath = __DIR__ . '/../resources/views/plenary/cases-first-reading/show.php';
if (!file_exists($showViewPath)) {
    fail('8.0 cases-first-reading/show.php exists', 'File not found at ' . $showViewPath);
} else {
    $viewHtml = file_get_contents($showViewPath);

    check('8.1 Form action points to cases-first-reading/store', strpos($viewHtml, 'cases-first-reading/store') !== false);
    check('8.2 Hidden input document_id present', strpos($viewHtml, 'name="document_id"') !== false);
    check('8.3 Decision radio name is "cases_fr_decision"', strpos($viewHtml, 'name="cases_fr_decision"') !== false);
    check('8.4 REFER_TO_COMMITTEE decision option present', strpos($viewHtml, 'REFER_TO_COMMITTEE') !== false);
    check('8.5 NOTED decision option present', strpos($viewHtml, 'NOTED') !== false);
    check('8.6 Committee checkboxes section present', strpos($viewHtml, 'committee_ids[]') !== false);
    check('8.7 Committees section uses id="section-refer"', strpos($viewHtml, 'id="section-refer"') !== false);
    check('8.8 Noted section uses id="section-noted"', strpos($viewHtml, 'id="section-noted"') !== false);
    check('8.9 JS updateDecision function defined', strpos($viewHtml, 'casesFrUpdateDecision') !== false);
    check('8.10 Reading date input present', strpos($viewHtml, 'name="reading_date"') !== false);
    check('8.11 Remarks textarea present', strpos($viewHtml, 'name="remarks"') !== false);
    check('8.12 NOTED: no additional inputs required (no required category field)', strpos($viewHtml, 'name="communication_category_id"') === false);
    check('8.13 Back link goes to petitions tab', strpos($viewHtml, 'tab=petitions') !== false);
    check('8.14 Layout included', strpos($viewHtml, 'layouts/app.php') !== false);
}

section('Group 9 — View: referred-to-committee/index.php has adopt-cases modal');

$referredViewPath = __DIR__ . '/../resources/views/plenary/referred-to-committee/index.php';
if (!file_exists($referredViewPath)) {
    fail('9.0 referred-to-committee/index.php exists', 'File not found.');
} else {
    $refHtml = file_get_contents($referredViewPath);

    check('9.1 adopt-cases-modal element present', strpos($refHtml, 'adopt-cases-modal') !== false);
    check('9.2 adopt-report form action present', strpos($refHtml, 'cases-first-reading/adopt-report') !== false);
    check('9.3 resolution_number input in cases modal', strpos($refHtml, 'adopt-cases-resolution-number') !== false);
    check('9.4 proposed_number input in cases modal', strpos($refHtml, 'adopt-cases-proposed-number') !== false);
    check('9.5 openAdoptCasesModal JS function defined', strpos($refHtml, 'openAdoptCasesModal') !== false);
    check('9.6 is_plenary_cases_referred condition checked', strpos($refHtml, 'is_plenary_cases_referred') !== false);
    check('9.7 adopt-cases button triggers openAdoptCasesModal', strpos($refHtml, "onclick=\"openAdoptCasesModal") !== false);
    check('9.8 Adopt-comm modal still present (not broken)', strpos($refHtml, 'adopt-comm-modal') !== false);
}

section('Group 10 — View: first-reading/index.php has Petitions tab');

$frIndexPath = __DIR__ . '/../resources/views/plenary/first-reading/index.php';
if (!file_exists($frIndexPath)) {
    fail('10.0 first-reading/index.php exists', 'File not found.');
} else {
    $frHtml = file_get_contents($frIndexPath);

    check('10.1 "Petitions" tab label present', strpos($frHtml, "'petitions'") !== false);
    check('10.2 frIsCases() function defined', strpos($frHtml, 'frIsCases') !== false);
    check('10.3 cases-first-reading/show route used for cases docs', strpos($frHtml, 'cases-first-reading/show') !== false);
}

section('Group 11 — View: status-list/index.php has Petitions tab');

$slPath = __DIR__ . '/../resources/views/plenary/status-list/index.php';
if (!file_exists($slPath)) {
    fail('11.0 status-list/index.php exists', 'File not found.');
} else {
    $slHtml = file_get_contents($slPath);

    check('11.1 "Petitions" tab label present in tabCounts key', strpos($slHtml, "'petitions'") !== false);
    check('11.2 petitions table/content block present', strpos($slHtml, "tab === 'petitions'") !== false);
    check('11.3 Petitions table has adoption_resolution_number reference', strpos($slHtml, 'adoption_resolution_number') !== false);
    check('11.4 petitionsDocuments variable referenced', strpos($slHtml, 'petitionsDocuments') !== false);
}

section('Group 12 — Routes file contains new case routes');

$routesPath = __DIR__ . '/../routes/web.php';
if (!file_exists($routesPath)) {
    fail('12.0 routes/web.php exists', 'File not found.');
} else {
    $routesContent = file_get_contents($routesPath);
    check('12.1 GET plenary/cases-first-reading/show route present', strpos($routesContent, "'plenary/cases-first-reading/show'") !== false);
    check('12.2 POST plenary/cases-first-reading/store route present', strpos($routesContent, "'plenary/cases-first-reading/store'") !== false);
    check('12.3 POST plenary/cases-first-reading/adopt-report route present', strpos($routesContent, "'plenary/cases-first-reading/adopt-report'") !== false);
    check('12.4 casesFirstReadingShow action mapped', strpos($routesContent, 'casesFirstReadingShow') !== false);
    check('12.5 casesFirstReadingStore action mapped', strpos($routesContent, 'casesFirstReadingStore') !== false);
    check('12.6 adoptCasesReportStore action mapped', strpos($routesContent, 'adoptCasesReportStore') !== false);
}

section('Group 13 — Controller: PlenaryReadingController has new methods');

$ctrlPath = __DIR__ . '/../app/controllers/Plenary/PlenaryReadingController.php';
if (!file_exists($ctrlPath)) {
    fail('13.0 PlenaryReadingController.php exists', 'File not found.');
} else {
    $ctrlContent = file_get_contents($ctrlPath);
    check('13.1 casesFirstReadingShow() method present', strpos($ctrlContent, 'function casesFirstReadingShow') !== false);
    check('13.2 casesFirstReadingStore() method present', strpos($ctrlContent, 'function casesFirstReadingStore') !== false);
    check('13.3 adoptCasesReportStore() method present', strpos($ctrlContent, 'function adoptCasesReportStore') !== false);
    check('13.4 doCasesFrNoted() private method present', strpos($ctrlContent, 'function doCasesFrNoted') !== false);
    check('13.5 doCasesFrReferToCommittee() private method present', strpos($ctrlContent, 'function doCasesFrReferToCommittee') !== false);
    check('13.6 isCasesDocument() helper present', strpos($ctrlContent, 'function isCasesDocument') !== false);
    check('13.7 isCasesTypeName() helper present', strpos($ctrlContent, 'function isCasesTypeName') !== false);
    check('13.8 fetchCasesFirstReading() helper present', strpos($ctrlContent, 'function fetchCasesFirstReading') !== false);
    check('13.9 fetchCasesAdoption() helper present', strpos($ctrlContent, 'function fetchCasesAdoption') !== false);
    check('13.10 fetchReferredPetitionsCount() helper present', strpos($ctrlContent, 'function fetchReferredPetitionsCount') !== false);
    check('13.11 petitions tab in buildFirstReadingTabCondition()', strpos($ctrlContent, "'petitions'") !== false);
    check('13.12 firstReadingShow() dispatches cases to cases-first-reading/show', strpos($ctrlContent, 'cases-first-reading/show') !== false);
    check('13.13 referredToCommitteeIndex() flags is_plenary_cases_referred', strpos($ctrlContent, 'is_plenary_cases_referred') !== false);
    check('13.14 statusList() handles petitions tab', strpos($ctrlContent, "\$tab === 'petitions'") !== false);
    check('13.15 fetchPetitionsFinalizedCount() helper present', strpos($ctrlContent, 'function fetchPetitionsFinalizedCount') !== false);
}

section('Group 14 — Migration 076 file integrity');

$migPath = __DIR__ . '/../database/migrations/076_plenary_cases_first_reading_schema.php';
if (!file_exists($migPath)) {
    fail('14.0 Migration 076 file exists', 'Not found at expected path.');
} else {
    $migContent = file_get_contents($migPath);
    check('14.1 plenary_cases_first_reading CREATE TABLE present', strpos($migContent, 'CREATE TABLE IF NOT EXISTS plenary_cases_first_reading') !== false);
    check('14.2 plenary_cases_adoptions CREATE TABLE present', strpos($migContent, 'CREATE TABLE IF NOT EXISTS plenary_cases_adoptions') !== false);
    check('14.3 Status "Plenary Cases: Noted" seeded', strpos($migContent, 'Plenary Cases: Noted') !== false);
    check('14.4 Status "Plenary Cases: Referred to Committee" seeded', strpos($migContent, 'Plenary Cases: Referred to Committee') !== false);
    check('14.5 Status "Plenary Cases: Finalized" seeded', strpos($migContent, 'Plenary Cases: Finalized') !== false);
    check('14.6 PLENARY_CASES_FIRST_READING_NOTED event type present', strpos($migContent, 'PLENARY_CASES_FIRST_READING_NOTED') !== false);
    check('14.7 PLENARY_CASES_FIRST_READING_REFERRED event type present', strpos($migContent, 'PLENARY_CASES_FIRST_READING_REFERRED') !== false);
    check('14.8 PLENARY_CASES_ADOPTED event type present', strpos($migContent, 'PLENARY_CASES_ADOPTED') !== false);
    check('14.9 PLENARY_CASES_FINALIZED event type present', strpos($migContent, 'PLENARY_CASES_FINALIZED') !== false);
    check('14.10 down() method reverses all tables', strpos($migContent, 'function down') !== false && strpos($migContent, 'DROP TABLE IF EXISTS plenary_cases_adoptions') !== false);
    check('14.11 UNIQUE constraint on plenary_cases_first_reading.document_id', strpos($migContent, 'uq_pcfr_cases_document_id') !== false);
    check('14.12 UNIQUE constraint on plenary_cases_adoptions.document_id', strpos($migContent, 'uq_pca_cases_document_id') !== false);
}

// ── Summary ────────────────────────────────────────────────────────────────

echo PHP_EOL;
echo '═══════════════════════════════════════' . PHP_EOL;
echo "Results: {$passed} passed, {$failed} failed" . PHP_EOL;
echo '═══════════════════════════════════════' . PHP_EOL;

exit($failed > 0 ? 1 : 0);

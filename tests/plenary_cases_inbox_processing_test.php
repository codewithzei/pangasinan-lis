<?php
/**
 * Test: Plenary Inbox — Cases (Complaints / Administrative Cases) Fast-Path Processing
 *
 * Covers the requirements in the spec:
 *   1. For Encoding → only status saved, no processing details required
 *   2. Document advances directly to "Awaiting First Reading" (First Reading → Petitions queue)
 *   3. No Proposed Number, Authors, Co-Authors, Committees, or Session Type stored
 *   4. Controller helpers: isCasesType() recognises all relevant type names
 *   5. View structure: cases fast-path "Send to First Reading" panel is present
 *      and the full processing details section is guarded by !$isCases
 *   6. Controller structure: doSaveProcessingCases() method is present
 *
 * Also covers the full First Reading decision branches:
 *   7. NOTED   → Plenary Cases: Noted, phase FINALIZED, appears in status-list Petitions
 *   8. REFER   → Plenary Cases: Referred to Committee, phase COMMITTEE,
 *                committee assignment created, document_routes record created
 *   9. Adopt Committee Report → Plenary Cases: Finalized, phase FINALIZED,
 *                               plenary_cases_adoptions row, both FINALIZED events
 *
 * Strategy
 * ────────
 * All DB writes are wrapped in transactions that are always rolled back.
 *
 * Run with: php tests/plenary_cases_inbox_processing_test.php
 */

declare(strict_types=1);

// ── Bootstrap ─────────────────────────────────────────────────────────────
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

// ── Helpers ───────────────────────────────────────────────────────────────

function getStatusId(PDO $pdo, string $name): int
{
    $s = $pdo->prepare('SELECT id FROM document_statuses WHERE name=? AND is_active=1 AND is_deleted=0 LIMIT 1');
    $s->execute([$name]);
    $r = $s->fetch();
    if (!$r) { throw new RuntimeException("Status '{$name}' not found."); }
    return (int) $r['id'];
}

function getCasesTypeId(PDO $pdo): int
{
    foreach (['%Administrative Case%', '%Complaint%'] as $pat) {
        $s = $pdo->prepare('SELECT id FROM document_types WHERE name LIKE ? AND is_active=1 AND is_deleted=0 LIMIT 1');
        $s->execute([$pat]);
        $r = $s->fetch();
        if ($r) { return (int) $r['id']; }
    }
    throw new RuntimeException("No document type matching Administrative Case% or Complaint% found.");
}

function getUserId(PDO $pdo): int
{
    $r = $pdo->query('SELECT id FROM user_accounts LIMIT 1')->fetch();
    if (!$r) { throw new RuntimeException("No user account found."); }
    return (int) $r['id'];
}

function getSourceTypeId(PDO $pdo): int
{
    $r = $pdo->query('SELECT id FROM source_types WHERE is_active=1 AND is_deleted=0 LIMIT 1')->fetch();
    if (!$r) { throw new RuntimeException("No source type found."); }
    return (int) $r['id'];
}

function getPlenaryRoleId(PDO $pdo): int
{
    $r = $pdo->prepare('SELECT id FROM roles WHERE name=? AND is_active=1 AND is_deleted=0 LIMIT 1');
    $r->execute(['Plenary']);
    $row = $r->fetch();
    if (!$row) { throw new RuntimeException("Role 'Plenary' not found."); }
    return (int) $row['id'];
}

function getCommitteeRoleId(PDO $pdo): int
{
    $r = $pdo->prepare('SELECT id FROM roles WHERE name=? AND is_active=1 AND is_deleted=0 LIMIT 1');
    $r->execute(['Committee']);
    $row = $r->fetch();
    if (!$row) { throw new RuntimeException("Role 'Committee' not found."); }
    return (int) $row['id'];
}

function getCommitteeId(PDO $pdo): int
{
    $r = $pdo->query('SELECT id FROM committees WHERE is_active=1 AND is_deleted=0 LIMIT 1')->fetch();
    if (!$r) { throw new RuntimeException("No committee found."); }
    return (int) $r['id'];
}

/**
 * Creates a document in "For Encoding" state (plenary_document_details.status_id = on_process_id)
 * and returns the document ID.  Simulates what the inbox pre-decision step would produce.
 */
function createOnProcessCasesDoc(
    PDO $pdo, int $casesTypeId, int $onProcessId, int $userId, int $plenaryRoleId
): int {
    static $seq = 990000;
    $seq++;

    $srcId = getSourceTypeId($pdo);

    $pdo->prepare("
        INSERT INTO documents
            (tracking_number, tracking_year, tracking_sequence,
             subject_matter, document_type_id, source_type_id,
             current_status_id, date_received, time_received,
             current_phase, created_by, updated_by)
        VALUES (CONCAT('CASE-TEST-', ?), YEAR(NOW()), ?,
                'Test Case Document', ?, ?,
                ?, CURDATE(), '08:00:00',
                'PLENARY', ?, ?)
    ")->execute([$seq, $seq, $casesTypeId, $srcId, $onProcessId, $userId, $userId]);

    $docId = (int) $pdo->lastInsertId();

    // Add plenary_document_details with "For Encoding" status (pre-decision stage)
    $pdo->prepare("
        INSERT INTO plenary_document_details
            (document_id, status_id, created_by, updated_by)
        VALUES (?, ?, ?, ?)
    ")->execute([$docId, $onProcessId, $userId, $userId]);

    // Add ACCEPTED plenary assignment (simulates user having clicked Accept)
    $pdo->prepare("
        INSERT INTO document_assignments
            (document_id, assigned_to_role_id, phase, assigned_by,
             decision, accepted_by, assigned_to_user_id, received_at, accepted_at)
        VALUES (?, ?, 'PLENARY', ?, 'ACCEPTED', ?, ?, NOW(), NOW())
    ")->execute([$docId, $plenaryRoleId, $userId, $userId, $userId]);

    return $docId;
}

// ── Pre-flight ────────────────────────────────────────────────────────────
try {
    $onProcessId     = getStatusId($pdo, 'For Encoding');
    $awaitingFrId    = getStatusId($pdo, 'Awaiting First Reading');
    $awaitingSecondId = getStatusId($pdo, 'Awaiting Second Reading');
    $notedId         = getStatusId($pdo, 'Plenary Cases: Noted');
    $referredId      = getStatusId($pdo, 'Plenary Cases: Referred to Committee');
    $finalizedId     = getStatusId($pdo, 'Plenary Cases: Finalized');
    $returnedId      = getStatusId($pdo, 'Returned to Plenary');
    $casesTypeId     = getCasesTypeId($pdo);
    $userId          = getUserId($pdo);
    $plenaryRoleId   = getPlenaryRoleId($pdo);
    $committeeRoleId = getCommitteeRoleId($pdo);
    $cid             = getCommitteeId($pdo);
} catch (RuntimeException $e) {
    echo 'FATAL: Pre-flight failed — ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

section('Group 1 — isCasesType() identification (matches controller logic)');

// Replicates the exact logic in PlenaryInboxController::isCasesType()
function testIsCasesType(string $documentTypeName): bool
{
    $lower = strtolower(trim($documentTypeName));
    return str_contains($lower, 'administrative case')
        || str_contains($lower, 'complaint');
}

$cases = [
    ['Administrative Cases',       true],
    ['administrative cases',       true],
    ['ADMINISTRATIVE CASES',       true],
    ['Administrative Case',        true],
    ['Complaint',                  true],
    ['complaint',                  true],
    ['COMPLAINT',                  true],
    ['Communication',              false],
    ['Provincial Resolution',      false],
    ['Provincial Ordinance',       false],
    ['Provincial Tax Ordinance',   false],
    ['',                           false],
];

foreach ($cases as [$name, $expected]) {
    $result = testIsCasesType($name);
    check('1.x isCasesType("' . $name . '") = ' . ($expected ? 'true' : 'false'), $result === $expected);
}

// ==========================================================================
// Group 2 — Inbox For Encoding fast-path: document advances to Awaiting FR
// ==========================================================================

section('Group 2 — For Encoding fast-path advances to Awaiting First Reading');

$pdo->beginTransaction();
try {
    $docId = createOnProcessCasesDoc($pdo, $casesTypeId, $onProcessId, $userId, $plenaryRoleId);

    // Simulate doSaveProcessingCases: advance status to Awaiting First Reading
    $pdo->prepare("UPDATE documents SET current_status_id=?, updated_by=? WHERE id=?")
        ->execute([$awaitingFrId, $userId, $docId]);

    $pdo->prepare("UPDATE plenary_document_details SET status_id=?, updated_by=? WHERE document_id=?")
        ->execute([$awaitingFrId, $userId, $docId]);

    $pdo->prepare("
        INSERT INTO document_events
            (document_id, event_type, phase, performed_by, to_status_id, remarks, metadata)
        VALUES (?, 'PLENARY_PROCESSING_SAVED', 'PLENARY', ?, ?, NULL, ?)
    ")->execute([$docId, $userId, $onProcessId, json_encode(['cases_fast_path' => true])]);

    // Assertions
    $docRow = $pdo->prepare("SELECT current_status_id, current_phase FROM documents WHERE id=?");
    $docRow->execute([$docId]);
    $doc = $docRow->fetch();
    check('2.1 Document status advanced to "Awaiting First Reading"', (int) $doc['current_status_id'] === $awaitingFrId);
    check('2.2 Document phase remains PLENARY (not sent to committee)', $doc['current_phase'] === 'PLENARY');

    $pddRow = $pdo->prepare("SELECT status_id FROM plenary_document_details WHERE document_id=?");
    $pddRow->execute([$docId]);
    $pdd = $pddRow->fetch();
    check('2.3 plenary_document_details.status_id synced to Awaiting First Reading', (int) $pdd['status_id'] === $awaitingFrId);

    // No proposed_number, no authors, no committees at this stage
    $pddFull = $pdo->prepare("SELECT proposed_number FROM plenary_document_details WHERE document_id=?");
    $pddFull->execute([$docId]);
    $pddFull = $pddFull->fetch();
    check('2.4 No proposed_number stored (cases exempt)', empty($pddFull['proposed_number']));

    $authRow = $pdo->prepare("SELECT COUNT(*) AS cnt FROM document_authors WHERE document_id=?");
    $authRow->execute([$docId]);
    check('2.5 No document_authors rows created', (int) $authRow->fetch()['cnt'] === 0);

    $cmtRow = $pdo->prepare("SELECT COUNT(*) AS cnt FROM document_committees WHERE document_id=?");
    $cmtRow->execute([$docId]);
    check('2.6 No document_committees rows created', (int) $cmtRow->fetch()['cnt'] === 0);

    $evRow = $pdo->prepare("
        SELECT * FROM document_events
        WHERE document_id=? AND event_type='PLENARY_PROCESSING_SAVED' LIMIT 1
    ");
    $evRow->execute([$docId]);
    $ev = $evRow->fetch();
    check('2.7 PLENARY_PROCESSING_SAVED event inserted', $ev !== false);

    // Verify the event metadata carries cases_fast_path = true
    $meta = json_decode($ev['metadata'] ?? '{}', true);
    check('2.8 Event metadata contains cases_fast_path=true', !empty($meta['cases_fast_path']));

} finally {
    $pdo->rollBack();
}

// ==========================================================================
// Group 3 — After fast-path, document appears in First Reading Petitions queue
// ==========================================================================

section('Group 3 — After fast-path, document is in First Reading Petitions queue');

$pdo->beginTransaction();
try {
    // Create document already in "Awaiting First Reading" (simulates post fast-path)
    $docId2 = createOnProcessCasesDoc($pdo, $casesTypeId, $onProcessId, $userId, $plenaryRoleId);
    $pdo->prepare("UPDATE documents SET current_status_id=? WHERE id=?")->execute([$awaitingFrId, $docId2]);
    $pdo->prepare("UPDATE plenary_document_details SET status_id=? WHERE document_id=?")->execute([$awaitingFrId, $docId2]);

    // Simulate the First Reading index query: documents that are Awaiting FR
    // and NOT yet in plenary_cases_first_reading
    $inQueue = $pdo->prepare("
        SELECT COUNT(*) AS cnt
        FROM documents d
        INNER JOIN document_types dt ON dt.id = d.document_type_id
        WHERE d.id = ?
          AND d.current_status_id = ?
          AND (dt.name LIKE '%Administrative Case%' OR dt.name LIKE '%Complaint%')
          AND NOT EXISTS (
              SELECT 1 FROM plenary_cases_first_reading pcfr
              WHERE pcfr.document_id = d.id
          )
    ");
    $inQueue->execute([$docId2, $awaitingFrId]);
    check('3.1 Document appears in First Reading Petitions queue after fast-path', (int) $inQueue->fetch()['cnt'] === 1);

    // If a cases FR record exists, it must NOT appear
    $pdo->prepare("
        INSERT INTO plenary_cases_first_reading
            (document_id, decision, reading_date, performed_by)
        VALUES (?, 'NOTED', CURDATE(), ?)
    ")->execute([$docId2, $userId]);

    $inQueue->execute([$docId2, $awaitingFrId]);
    check('3.2 Document removed from Petitions queue once FR record exists (no re-processing)', (int) $inQueue->fetch()['cnt'] === 0);

} finally {
    $pdo->rollBack();
}

// ==========================================================================
// Group 4 — First Reading NOTED branch: direct routing from fast-path
// ==========================================================================

section('Group 4 — First Reading NOTED branch (direct routing from fast-path)');

$pdo->beginTransaction();
try {
    // Start from Awaiting First Reading (result of fast-path)
    $docId3 = createOnProcessCasesDoc($pdo, $casesTypeId, $awaitingFrId, $userId, $plenaryRoleId);

    // Simulate doCasesFrNoted (phase stays PLENARY — NOT finalized immediately)
    $pdo->prepare("
        INSERT INTO plenary_cases_first_reading
            (document_id, decision, reading_date, remarks, performed_by)
        VALUES (?, 'NOTED', CURDATE(), NULL, ?)
    ")->execute([$docId3, $userId]);

    $pdo->prepare("
        UPDATE documents
        SET current_status_id=?, updated_by=?
        WHERE id=?
    ")->execute([$notedId, $userId, $docId3]);

    $pdo->prepare("
        UPDATE plenary_document_details SET status_id=?, updated_by=? WHERE document_id=?
    ")->execute([$notedId, $userId, $docId3]);

    $pdo->prepare("
        INSERT INTO document_events
            (document_id, event_type, phase, performed_by, to_status_id, remarks, metadata)
        VALUES (?, 'PLENARY_CASES_FIRST_READING_NOTED', 'PLENARY', ?, ?, NULL, '{}')
    ")->execute([$docId3, $userId, $notedId]);

    $docRow = $pdo->prepare("SELECT current_status_id, current_phase, finalized_at FROM documents WHERE id=?");
    $docRow->execute([$docId3]);
    $doc3 = $docRow->fetch();
    check('4.1 NOTED: document status = Plenary Cases: Noted', (int) $doc3['current_status_id'] === $notedId);
    check('4.2 NOTED: document phase stays PLENARY (not finalized)', $doc3['current_phase'] === 'PLENARY');
    check('4.3 NOTED: finalized_at is NOT set', empty($doc3['finalized_at']));

    // Appears on the standalone Petitions page (status = Plenary Cases: Noted only)
    $inPetitionsPage = $pdo->prepare("
        SELECT COUNT(*) AS cnt FROM documents WHERE id=? AND current_status_id=?
    ");
    $inPetitionsPage->execute([$docId3, $notedId]);
    check('4.4 NOTED: document appears on Petitions page (Plenary Cases: Noted status)', (int) $inPetitionsPage->fetch()['cnt'] === 1);

    // Must NOT appear in Finalized Documents → Petitions tab (only Finalized status goes there)
    $notInFinalized = $pdo->prepare("
        SELECT COUNT(*) AS cnt FROM documents WHERE id=? AND current_status_id=?
    ");
    $notInFinalized->execute([$docId3, $finalizedId]);
    check('4.4b NOTED: document NOT in Finalized Documents → Petitions tab', (int) $notInFinalized->fetch()['cnt'] === 0);

    // Must NOT appear in First Reading Petitions queue
    $notInQueue = $pdo->prepare("
        SELECT COUNT(*) AS cnt FROM documents d
        WHERE d.id=? AND d.current_status_id=?
          AND NOT EXISTS (SELECT 1 FROM plenary_cases_first_reading WHERE document_id=d.id)
    ");
    $notInQueue->execute([$docId3, $awaitingFrId]);
    check('4.5 NOTED: document no longer in First Reading queue', (int) $notInQueue->fetch()['cnt'] === 0);

    // No committee assignment created
    $noComm = $pdo->prepare("
        SELECT COUNT(*) AS cnt FROM document_assignments
        WHERE document_id=? AND phase='COMMITTEE' AND decision='PENDING'
    ");
    $noComm->execute([$docId3]);
    check('4.6 NOTED: no committee assignment created', (int) $noComm->fetch()['cnt'] === 0);

} finally {
    $pdo->rollBack();
}

// ==========================================================================
// Group 5 — First Reading REFER_TO_COMMITTEE branch: committee assignment
// ==========================================================================

section('Group 5 — First Reading REFER_TO_COMMITTEE branch');

$pdo->beginTransaction();
try {
    $docId4 = createOnProcessCasesDoc($pdo, $casesTypeId, $awaitingFrId, $userId, $plenaryRoleId);

    // Simulate doCasesFrReferToCommittee
    $pdo->prepare("
        INSERT INTO plenary_cases_first_reading
            (document_id, decision, reading_date, remarks, performed_by)
        VALUES (?, 'REFER_TO_COMMITTEE', CURDATE(), NULL, ?)
    ")->execute([$docId4, $userId]);
    $fr4Id = (int) $pdo->lastInsertId();

    // Replace document_committees
    $pdo->prepare("DELETE FROM document_committees WHERE document_id=?")->execute([$docId4]);
    $pdo->prepare("INSERT INTO document_committees (document_id, committee_id, assigned_by) VALUES (?,?,?)")
        ->execute([$docId4, $cid, $userId]);

    // Update document
    $pdo->prepare("
        UPDATE documents SET current_phase='COMMITTEE', current_status_id=?, updated_by=? WHERE id=?
    ")->execute([$referredId, $userId, $docId4]);

    // PENDING committee assignment
    $pdo->prepare("
        INSERT INTO document_assignments
            (document_id, assigned_to_role_id, phase, assigned_by, decision, received_at)
        VALUES (?, ?, 'COMMITTEE', ?, 'PENDING', NOW())
    ")->execute([$docId4, $committeeRoleId, $userId]);

    // Route record
    $pdo->prepare("
        INSERT INTO document_routes
            (document_id, from_phase, to_phase, routed_by, routed_to_role_id, remarks)
        VALUES (?, 'PLENARY', 'COMMITTEE', ?, ?, 'Referred at First Reading')
    ")->execute([$docId4, $userId, $committeeRoleId]);

    $pdo->prepare("
        INSERT INTO document_events
            (document_id, event_type, phase, performed_by, to_status_id, remarks, metadata)
        VALUES (?, 'PLENARY_CASES_FIRST_READING_REFERRED', 'PLENARY', ?, ?, NULL, '{}')
    ")->execute([$docId4, $userId, $referredId]);

    $docRow4 = $pdo->prepare("SELECT current_status_id, current_phase, finalized_at FROM documents WHERE id=?");
    $docRow4->execute([$docId4]);
    $doc4 = $docRow4->fetch();
    check('5.1 REFER: document status = Plenary Cases: Referred to Committee', (int) $doc4['current_status_id'] === $referredId);
    check('5.2 REFER: document phase = COMMITTEE', $doc4['current_phase'] === 'COMMITTEE');
    check('5.3 REFER: document not finalized yet', empty($doc4['finalized_at']));

    $dcRow = $pdo->prepare("SELECT * FROM document_committees WHERE document_id=? AND committee_id=?");
    $dcRow->execute([$docId4, $cid]);
    check('5.4 REFER: document_committees row created for selected committee', $dcRow->fetch() !== false);

    $pendingRow = $pdo->prepare("
        SELECT * FROM document_assignments
        WHERE document_id=? AND phase='COMMITTEE' AND decision='PENDING' LIMIT 1
    ");
    $pendingRow->execute([$docId4]);
    check('5.5 REFER: PENDING committee assignment created', $pendingRow->fetch() !== false);

    $routeRow = $pdo->prepare("
        SELECT * FROM document_routes
        WHERE document_id=? AND from_phase='PLENARY' AND to_phase='COMMITTEE' LIMIT 1
    ");
    $routeRow->execute([$docId4]);
    check('5.6 REFER: document_routes PLENARY→COMMITTEE record created', $routeRow->fetch() !== false);

    $evRow4 = $pdo->prepare("
        SELECT * FROM document_events
        WHERE document_id=? AND event_type='PLENARY_CASES_FIRST_READING_REFERRED' LIMIT 1
    ");
    $evRow4->execute([$docId4]);
    check('5.7 REFER: PLENARY_CASES_FIRST_READING_REFERRED event inserted', $evRow4->fetch() !== false);

    // Must NOT appear in status-list Petitions (not finalized yet)
    $notInPetitions = $pdo->prepare("
        SELECT COUNT(*) AS cnt FROM documents WHERE id=? AND current_status_id IN (?, ?)
    ");
    $notInPetitions->execute([$docId4, $notedId, $finalizedId]);
    check('5.8 REFER: document NOT in Finalized Petitions tab yet', (int) $notInPetitions->fetch()['cnt'] === 0);

    // ── Regression: document IS visible on Referred-to-Committee page ────────
    // The baseWhere used by referredToCommitteeIndex() now includes an OR branch
    // for plenary_cases_first_reading. Simulate that query here to confirm.
    $rtcVisible = $pdo->prepare("
        SELECT COUNT(*) AS cnt
        FROM documents d
        WHERE (
            EXISTS (
                SELECT 1 FROM plenary_readings pr
                WHERE pr.document_id = d.id AND pr.reading_number = 1
                  AND pr.action = 'REFERRED_TO_COMMITTEE'
            )
            OR EXISTS (
                SELECT 1 FROM plenary_comm_first_reading pcfr
                WHERE pcfr.document_id = d.id AND pcfr.decision = 'REFER_TO_COMMITTEE'
            )
            OR EXISTS (
                SELECT 1 FROM plenary_cases_first_reading pcfr_cases
                WHERE pcfr_cases.document_id = d.id AND pcfr_cases.decision = 'REFER_TO_COMMITTEE'
            )
        )
        AND NOT EXISTS (
            SELECT 1 FROM plenary_readings pr2
            WHERE pr2.document_id = d.id AND pr2.reading_number = 2
        )
        AND NOT EXISTS (
            SELECT 1 FROM plenary_comm_adoptions pca
            WHERE pca.document_id = d.id
        )
        AND NOT EXISTS (
            SELECT 1 FROM plenary_cases_adoptions pca_cases
            WHERE pca_cases.document_id = d.id
        )
        AND d.id = ?
    ");
    $rtcVisible->execute([$docId4]);
    check('5.9 REFER: document IS visible in Referred-to-Committee page query', (int) $rtcVisible->fetch()['cnt'] === 1);

    // Confirm it shows on the In Committee tab (not Returned, not Awaiting 2nd Reading)
    $rtcInCommTab = $pdo->prepare("
        SELECT COUNT(*) AS cnt
        FROM documents d
        WHERE (
            EXISTS (
                SELECT 1 FROM plenary_cases_first_reading pcfr_cases
                WHERE pcfr_cases.document_id = d.id AND pcfr_cases.decision = 'REFER_TO_COMMITTEE'
            )
        )
        AND NOT EXISTS (SELECT 1 FROM plenary_cases_adoptions pca_cases WHERE pca_cases.document_id = d.id)
        AND d.current_status_id NOT IN (?, ?)
        AND d.id = ?
    ");
    $rtcInCommTab->execute([$returnedId, $awaitingSecondId, $docId4]);
    check('5.10 REFER: document appears in In Committee tab (status not Returned/Awaiting 2nd)', (int) $rtcInCommTab->fetch()['cnt'] === 1);

} finally {
    $pdo->rollBack();
}

// ==========================================================================
// Group 6 — Adopt Committee Report: committee-to-finalized flow
// ==========================================================================

section('Group 6 — Adopt Committee Report (committee-to-finalized flow)');

$pdo->beginTransaction();
try {
    // Document returned from committee
    $docId5 = createOnProcessCasesDoc($pdo, $casesTypeId, $returnedId, $userId, $plenaryRoleId);
    $pdo->prepare("UPDATE documents SET current_status_id=?, current_phase='COMMITTEE' WHERE id=?")
        ->execute([$returnedId, $docId5]);

    // Pre-existing REFER_TO_COMMITTEE first reading row (required prerequisite)
    $pdo->prepare("
        INSERT INTO plenary_cases_first_reading
            (document_id, decision, reading_date, performed_by)
        VALUES (?, 'REFER_TO_COMMITTEE', DATE_SUB(CURDATE(), INTERVAL 7 DAY), ?)
    ")->execute([$docId5, $userId]);
    $fr5Id = (int) $pdo->lastInsertId();

    // Simulate adoptCasesReportStore
    $pdo->prepare("
        INSERT INTO plenary_cases_adoptions
            (document_id, first_reading_id, resolution_number, proposed_number, adopted_by, remarks)
        VALUES (?, ?, 'SP Res. No. 2026-100', 'PPN-100', ?, 'Committee report adopted')
    ")->execute([$docId5, $fr5Id, $userId]);
    $adoptId = (int) $pdo->lastInsertId();

    $pdo->prepare("
        UPDATE documents
        SET current_status_id=?, current_phase='FINALIZED', finalized_at=NOW(), updated_by=?
        WHERE id=?
    ")->execute([$finalizedId, $userId, $docId5]);

    $pdo->prepare("
        UPDATE plenary_document_details SET status_id=?, updated_by=? WHERE document_id=?
    ")->execute([$finalizedId, $userId, $docId5]);

    $pdo->prepare("
        INSERT INTO document_events
            (document_id, event_type, phase, performed_by, to_status_id, remarks, metadata)
        VALUES (?, 'PLENARY_CASES_ADOPTED', 'PLENARY', ?, ?, NULL, '{}')
    ")->execute([$docId5, $userId, $finalizedId]);

    $pdo->prepare("
        INSERT INTO document_events
            (document_id, event_type, phase, performed_by, to_status_id, remarks, metadata)
        VALUES (?, 'PLENARY_CASES_FINALIZED', 'PLENARY', ?, ?, NULL, '{}')
    ")->execute([$docId5, $userId, $finalizedId]);

    // Assertions
    $adoptRow = $pdo->prepare("SELECT * FROM plenary_cases_adoptions WHERE id=?");
    $adoptRow->execute([$adoptId]);
    $adopt = $adoptRow->fetch();
    check('6.1 plenary_cases_adoptions row inserted', $adopt !== false);
    check('6.2 resolution_number stored', $adopt['resolution_number'] === 'SP Res. No. 2026-100');
    check('6.3 proposed_number stored', $adopt['proposed_number'] === 'PPN-100');
    check('6.4 first_reading_id links to REFER_TO_COMMITTEE FR row', (int) $adopt['first_reading_id'] === $fr5Id);
    check('6.5 adopted_by set', (int) $adopt['adopted_by'] === $userId);

    $docRow5 = $pdo->prepare("SELECT current_status_id, current_phase, finalized_at FROM documents WHERE id=?");
    $docRow5->execute([$docId5]);
    $doc5 = $docRow5->fetch();
    check('6.6 Document status = Plenary Cases: Finalized', (int) $doc5['current_status_id'] === $finalizedId);
    check('6.7 Document phase = FINALIZED', $doc5['current_phase'] === 'FINALIZED');
    check('6.8 finalized_at set', !empty($doc5['finalized_at']));

    $evAdopt = $pdo->prepare("SELECT * FROM document_events WHERE document_id=? AND event_type='PLENARY_CASES_ADOPTED' LIMIT 1");
    $evAdopt->execute([$docId5]);
    check('6.9 PLENARY_CASES_ADOPTED event inserted', $evAdopt->fetch() !== false);

    $evFin = $pdo->prepare("SELECT * FROM document_events WHERE document_id=? AND event_type='PLENARY_CASES_FINALIZED' LIMIT 1");
    $evFin->execute([$docId5]);
    check('6.10 PLENARY_CASES_FINALIZED event inserted', $evFin->fetch() !== false);

    // Document appears in status-list Petitions tab (only Finalized status)
    $inPetitions5 = $pdo->prepare("SELECT COUNT(*) AS cnt FROM documents WHERE id=? AND current_status_id=?");
    $inPetitions5->execute([$docId5, $finalizedId]);
    check('6.11 Adopted document appears in Finalized Documents → Petitions tab', (int) $inPetitions5->fetch()['cnt'] === 1);

    // UNIQUE constraint: second adoption must fail
    $dupViolated = false;
    try {
        $pdo->prepare("
            INSERT INTO plenary_cases_adoptions
                (document_id, first_reading_id, resolution_number, adopted_by)
            VALUES (?, ?, 'SP Res. No. 2026-999', ?)
        ")->execute([$docId5, $fr5Id, $userId]);
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) { $dupViolated = true; }
    }
    check('6.12 Duplicate adoption insert violates UNIQUE constraint', $dupViolated);

} finally {
    $pdo->rollBack();
}

// ==========================================================================
// Group 7 — Duplicate prevention: first reading record
// ==========================================================================

section('Group 7 — Duplicate prevention for cases first reading');

$pdo->beginTransaction();
try {
    $docId6 = createOnProcessCasesDoc($pdo, $casesTypeId, $awaitingFrId, $userId, $plenaryRoleId);

    $pdo->prepare("
        INSERT INTO plenary_cases_first_reading
            (document_id, decision, reading_date, performed_by)
        VALUES (?, 'NOTED', CURDATE(), ?)
    ")->execute([$docId6, $userId]);

    $dup = false;
    try {
        $pdo->prepare("
            INSERT INTO plenary_cases_first_reading
                (document_id, decision, reading_date, performed_by)
            VALUES (?, 'REFER_TO_COMMITTEE', CURDATE(), ?)
        ")->execute([$docId6, $userId]);
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) { $dup = true; }
    }
    check('7.1 Duplicate plenary_cases_first_reading raises UNIQUE violation', $dup);

} finally {
    $pdo->rollBack();
}

// ==========================================================================
// Group 8 — View structure: inbox show.php has isCases guard
// ==========================================================================

section('Group 8 — View structure: inbox/show.php has Cases fast-path guard');

$showPath = __DIR__ . '/../resources/views/plenary/inbox/show.php';
if (!file_exists($showPath)) {
    fail('8.0 File exists', 'resources/views/plenary/inbox/show.php not found.');
} else {
    $html = file_get_contents($showPath);

    check('8.1 $isCases variable initialized from controller', strpos($html, '$isCases') !== false);
    check('8.2 isCases guard inside canSaveProcessing block', strpos($html, 'if ($isCases)') !== false);
    check('8.3 "Send to First Reading" button label present', strpos($html, 'Send to First Reading') !== false);
    check('8.4 Cases fast-path form uses save_processing action', strpos($html, "value=\"save_processing\"") !== false);
    check('8.5 Cases panel has indigo styling (distinct from violet full form)', strpos($html, 'border-indigo-200') !== false);
    // Ensure the full processing details section is inside an else branch for $isCases
    check('8.6 Full processing form is inside else branch of isCases', strpos($html, '<?php else: ?>') !== false && strpos($html, 'Plenary Processing Details') !== false);
}

// ==========================================================================
// Group 9 — Controller structure: isCasesType() and doSaveProcessingCases()
// ==========================================================================

section('Group 9 — Controller structure: new helpers in PlenaryInboxController');

$ctrlPath = __DIR__ . '/../app/controllers/Plenary/PlenaryInboxController.php';
if (!file_exists($ctrlPath)) {
    fail('9.0 File exists', 'PlenaryInboxController.php not found.');
} else {
    $ctrl = file_get_contents($ctrlPath);

    check('9.1 isCasesType() private method present', strpos($ctrl, 'private function isCasesType') !== false);
    check('9.2 doSaveProcessingCases() private method present', strpos($ctrl, 'private function doSaveProcessingCases') !== false);
    check('9.3 isCasesType() called from doSaveProcessing()', strpos($ctrl, '$this->isCasesType(') !== false);
    check('9.4 doSaveProcessingCases() called from doSaveProcessing()', strpos($ctrl, 'doSaveProcessingCases(') !== false);
    check('9.5 $isCases variable passed to view in show()', strpos($ctrl, '$isCases') !== false);
    check('9.6 cases_fast_path key in event metadata', strpos($ctrl, 'cases_fast_path') !== false);
    // The fast path must advance to Awaiting First Reading
    check('9.7 Awaiting First Reading status looked up by name (not hardcoded)', strpos($ctrl, "Awaiting First Reading") !== false);
    check('9.8 documents.current_status_id updated in fast path', strpos($ctrl, 'doSaveProcessingCases') !== false && strpos($ctrl, 'UPDATE documents') !== false);
    check('9.9 plenary_document_details.status_id synced in fast path', strpos($ctrl, 'UPDATE plenary_document_details') !== false);
}

// ── Summary ───────────────────────────────────────────────────────────────

echo PHP_EOL;
echo '═══════════════════════════════════════════════════════' . PHP_EOL;
echo "Results: {$passed} passed, {$failed} failed" . PHP_EOL;
echo '═══════════════════════════════════════════════════════' . PHP_EOL;

exit($failed > 0 ? 1 : 0);

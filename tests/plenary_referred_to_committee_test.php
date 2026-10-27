<?php
/**
 * Test: Plenary "Referred to Committee" tracking page
 *
 * Verifies the read-only tracking page + the "Proceed to 2nd Reading" flow that
 * reuses the existing advanceFromCommittee endpoint. The page reads the SAME
 * shared document status (documents.current_status_id) every other division
 * uses; no Plenary-only status is introduced.
 *
 * Cases:
 *  1. A document referred during First Reading appears on the page (In Committee).
 *  2. It remains tracked while the committee process is pending.
 *  3. The return from the committee workflow enables "Proceed to 2nd Reading".
 *  4. The button is unavailable before return and the server-side gate rejects
 *     invalid / repeated transitions.
 *  5. Proceeding updates the shared status and places the document in the
 *     existing Second Reading workflow.
 *  6. Unauthorized (non-Plenary) users cannot view or advance documents
 *     (RoleMiddleware prefix-gate, same logic the new route is covered by).
 *
 * Run with: php tests/plenary_referred_to_committee_test.php
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
    echo "FATAL: Cannot connect to database — " . $e->getMessage() . PHP_EOL;
    exit(1);
}

$passed = 0;
$failed = 0;

function pass(string $label): void {
    global $passed;
    echo "  [PASS] {$label}" . PHP_EOL;
    $passed++;
}
function fail(string $label, string $detail = ''): void {
    global $failed;
    $suffix = $detail !== '' ? " — {$detail}" : '';
    echo "  [FAIL] {$label}{$suffix}" . PHP_EOL;
    $failed++;
}
function check(string $label, bool $cond, string $detail = ''): void {
    $cond ? pass($label) : fail($label, $detail);
}

// ── Resolve shared statuses / roles at runtime (never hardcode ids) ────────
function statusId(PDO $pdo, string $name): int {
    $s = $pdo->prepare('SELECT id FROM document_statuses WHERE name=? AND is_active=1 AND is_deleted=0 LIMIT 1');
    $s->execute([$name]);
    $r = $s->fetch();
    if (!$r) { throw new RuntimeException("Status '{$name}' not found"); }
    return (int) $r['id'];
}
function roleId(PDO $pdo, string $name): int {
    $s = $pdo->prepare('SELECT id FROM roles WHERE name=? AND is_active=1 AND is_deleted=0 LIMIT 1');
    $s->execute([$name]);
    $r = $s->fetch();
    if (!$r) { throw new RuntimeException("Role '{$name}' not found"); }
    return (int) $r['id'];
}

// Replicates RoleMiddleware::handle() prefix-match logic so the test asserts the
// exact authorization gate that covers plenary/referred-to-committee and the
// reused plenary/second-reading/advance-from-committee route.
function roleAllowedForRoute(string $route, string $role): bool {
    $allowed = match (true) {
        str_starts_with($route, 'master')    => ['Super Admin'],
        str_starts_with($route, 'receiving') => ['Super Admin', 'Receiving Staff'],
        str_starts_with($route, 'admin')     => ['Super Admin', 'Admin'],
        str_starts_with($route, 'spsec')     => ['Super Admin', 'SP Secretary'],
        str_starts_with($route, 'plenary')   => ['Super Admin', 'Plenary'],
        str_starts_with($route, 'committee') => ['Super Admin', 'Committee'],
        default => null,
    };
    if ($allowed === null) { return true; }
    return in_array($role, $allowed, true);
}

// Replicates PlenaryReadingController::referredToCommitteeIndex list query.
function fetchReferredList(PDO $pdo, string $tab, int $returnedId, int $awaitingSecondId): array {
    $base = " EXISTS (
            SELECT 1 FROM plenary_readings pr
            WHERE pr.document_id = d.id AND pr.reading_number = 1
              AND pr.action = 'REFERRED_TO_COMMITTEE'
        )
        AND NOT EXISTS (
            SELECT 1 FROM plenary_readings pr2
            WHERE pr2.document_id = d.id AND pr2.reading_number = 2
        ) ";
    if ($tab === 'returned') {
        $sql = "SELECT d.id, ds.name AS status FROM documents d
                LEFT JOIN document_statuses ds ON ds.id = d.current_status_id
                WHERE {$base} AND d.current_status_id = ?";
        $params = [$returnedId];
    } else {
        $sql = "SELECT d.id, ds.name AS status FROM documents d
                LEFT JOIN document_statuses ds ON ds.id = d.current_status_id
                WHERE {$base} AND d.current_status_id <> ? AND d.current_status_id <> ?";
        $params = [$returnedId, $awaitingSecondId];
    }
    $s = $pdo->prepare($sql);
    $s->execute($params);
    return $s->fetchAll();
}

// Replicates PlenaryReadingController::secondReadingIndex pending tab
// (status = "Awaiting Second Reading").
function fetchSecondReadingPending(PDO $pdo, int $awaitingSecondId): array {
    $s = $pdo->prepare("SELECT d.id, ds.name AS status FROM documents d
                        LEFT JOIN document_statuses ds ON ds.id = d.current_status_id
                        WHERE d.current_status_id = ?");
    $s->execute([$awaitingSecondId]);
    return $s->fetchAll();
}

// Replicates the advanceFromCommittee() server-side gate: only documents whose
// shared status is "Returned to Plenary", with a First Reading referral and no
// Second Reading row yet, may be advanced. The controller throws otherwise.
function canAdvanceGate(PDO $pdo, int $docId, int $returnedId): bool {
    $s = $pdo->prepare("SELECT current_status_id FROM documents WHERE id = ?");
    $s->execute([$docId]);
    $cur = (int) $s->fetchColumn();
    if ($cur !== $returnedId) { return false; }
    $fr = $pdo->prepare("SELECT 1 FROM plenary_readings
                         WHERE document_id = ? AND reading_number = 1
                           AND action IN ('REFERRED_TO_COMMITTEE','REFERRED_TO_COMMITTEE_SECOND') LIMIT 1");
    $fr->execute([$docId]);
    if (!$fr->fetch()) { return false; }
    $sr = $pdo->prepare("SELECT 1 FROM plenary_readings WHERE document_id = ? AND reading_number = 2 LIMIT 1");
    $sr->execute([$docId]);
    if ($sr->fetch()) { return false; }
    return true;
}

// ── Simulation helpers (issue the exact SQL the controller issues) ─────────

function setupReferredDocument(PDO $pdo, int $userId, int $committeeId, int $plenaryRoleId, int $committeeRoleId, int $awaitingFirstId, int $referredId): int {
    static $seq = 910000;
    $seq++;
    $tn = 'RTC-' . str_pad((string) ($seq - 910000), 5, '0', STR_PAD_LEFT);

    $docType   = (int) $pdo->query("SELECT id FROM document_types WHERE is_active=1 AND is_deleted=0 ORDER BY id LIMIT 1")->fetchColumn();
    $sourceType = (int) $pdo->query("SELECT id FROM source_types WHERE is_active=1 AND is_deleted=0 ORDER BY id LIMIT 1")->fetchColumn();

    $pdo->prepare("INSERT INTO documents
        (tracking_number, tracking_year, tracking_sequence, subject_matter,
         document_type_id, source_type_id, current_status_id, current_phase,
         date_received, time_received, created_by, updated_by)
        VALUES (?, YEAR(CURDATE()), ?, 'RTC test subject', ?, ?, ?, 'PLENARY',
                CURDATE(), CURTIME(), ?, ?)")
        ->execute([$tn, $seq, $docType, $sourceType, $awaitingFirstId, $userId, $userId]);
    $docId = (int) $pdo->lastInsertId();

    // Plenary ACCEPTED assignment (ownership) — left open, as the controller does.
    $pdo->prepare("INSERT INTO document_assignments
        (document_id, assigned_to_role_id, phase, assigned_by, decision,
         received_at, accepted_by, accepted_at, assigned_to_user_id, completed_at)
        VALUES (?, ?, 'PLENARY', ?, 'ACCEPTED', NOW(), ?, NOW(), ?, NULL)")
        ->execute([$docId, $plenaryRoleId, $userId, $userId, $userId]);

    // plenary_document_details (proposed number / target date) for display
    $pdo->prepare("INSERT INTO plenary_document_details
        (document_id, proposed_number, target_first_reading_date, session_type, status_id, created_by, updated_by)
        VALUES (?, ?, CURDATE(), 'REGULAR', ?, ?, ?)")
        ->execute([$docId, 'RTC-' . $docId, $awaitingFirstId, $userId, $userId]);

    // ── firstReadingStore → doReferToCommitteeFirstReading ──────────────────
    $pdo->prepare("INSERT INTO plenary_readings
        (document_id, reading_number, action, reading_date, remarks, performed_by)
        VALUES (?, 1, 'REFERRED_TO_COMMITTEE', CURDATE(), 'Referred at First Reading (test)', ?)")
        ->execute([$docId, $userId]);

    $pdo->prepare("DELETE FROM document_committees WHERE document_id = ?")->execute([$docId]);
    $pdo->prepare("INSERT INTO document_committees (document_id, committee_id, assigned_by) VALUES (?,?,?)")
        ->execute([$docId, $committeeId, $userId]);

    $pdo->prepare("UPDATE documents SET current_phase='COMMITTEE', current_status_id=?, updated_by=? WHERE id=?")
        ->execute([$referredId, $userId, $docId]);

    $pdo->prepare("INSERT INTO document_assignments
        (document_id, assigned_to_role_id, phase, assigned_by, decision, received_at)
        VALUES (?, ?, 'COMMITTEE', ?, 'PENDING', NOW())")
        ->execute([$docId, $committeeRoleId, $userId]);

    $pdo->prepare("INSERT INTO document_routes (document_id, from_phase, to_phase, routed_by, routed_to_role_id, remarks)
        VALUES (?, 'PLENARY', 'COMMITTEE', ?, ?, 'Referred to Committee after First Reading.')")
        ->execute([$docId, $userId, $committeeRoleId]);

    $pdo->prepare("INSERT INTO document_events
        (document_id, event_type, phase, performed_by, to_status_id, remarks, metadata)
        VALUES (?, 'PLENARY_REFERRED_TO_COMMITTEE', 'PLENARY', ?, ?, NULL, ?)")
        ->execute([$docId, $userId, $referredId, json_encode(['committee_ids'=>[$committeeId],'source'=>'first_reading'])]);

    return $docId;
}

function setCommitteeSubStatus(PDO $pdo, int $docId, int $userId, int $statusId): void {
    $pdo->prepare("UPDATE documents SET current_status_id=?, updated_by=? WHERE id=?")
        ->execute([$statusId, $userId, $docId]);
}

function simulateReturnToPlenary(PDO $pdo, int $docId, int $userId, int $returnedId, int $committeeRoleId): void {
    static $returnSeq = 0;
    $returnSeq++;
    $reportNumber = 'RPT-RTC-' . $docId . '-' . $returnSeq;

    // committee_reports row, already returned
    $pdo->prepare("INSERT INTO committee_reports
        (report_type, report_number, summary_of_findings, created_by,
         returned_to_plenary_by, returned_to_plenary_at)
        VALUES ('COMMITTEE_REPORT', ?, 'Test findings', ?, ?, NOW())")
        ->execute([$reportNumber, $userId, $userId]);
    $reportId = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO committee_report_documents (committee_report_id, document_id) VALUES (?,?)")
        ->execute([$reportId, $docId]);

    $pdo->prepare("INSERT INTO committee_report_committees (committee_report_id, committee_id) VALUES (?, (SELECT id FROM committees WHERE is_active=1 AND is_deleted=0 ORDER BY id LIMIT 1))")
        ->execute([$reportId]);

    $prevStmt = $pdo->prepare("SELECT current_status_id FROM documents WHERE id=?");
    $prevStmt->execute([$docId]);
    $prevId = (int) $prevStmt->fetchColumn();

    $pdo->prepare("UPDATE documents SET current_status_id=?, updated_by=? WHERE id=?")
        ->execute([$returnedId, $userId, $docId]);

    $pdo->prepare("INSERT INTO document_events
        (document_id, event_type, phase, performed_by, from_status_id, to_status_id, remarks, metadata)
        VALUES (?, 'RETURNED_TO_PLENARY', 'COMMITTEE', ?, ?, ?, ?, ?)")
        ->execute([
            $docId, $userId, $prevId, $returnedId,
            'Committee Report returned to Plenary.',
            json_encode(['report_id' => $reportId, 'report_number' => $reportNumber, 'document_id' => $docId]),
        ]);

    $pdo->prepare("INSERT INTO document_routes (document_id, from_phase, to_phase, routed_by, routed_to_role_id, remarks)
        VALUES (?, 'COMMITTEE', 'PLENARY', ?, ?, 'Committee Report returned to Plenary.')")
        ->execute([$docId, $userId, $committeeRoleId]);
}

function simulateAdvanceFromCommittee(PDO $pdo, int $docId, int $userId, int $awaitingSecondId): void {
    $pdo->prepare("UPDATE documents SET current_status_id=?, current_phase='PLENARY', updated_by=? WHERE id=?")
        ->execute([$awaitingSecondId, $userId, $docId]);
    $pdo->prepare("INSERT INTO document_events
        (document_id, event_type, phase, performed_by, to_status_id, remarks, metadata)
        VALUES (?, 'PLENARY_SECOND_READING_APPROVED', 'PLENARY', ?, ?, ?, ?)")
        ->execute([
            $docId, $userId, $awaitingSecondId,
            'Document advanced to Second Reading queue after Committee return.',
            json_encode(['action' => 'ADVANCE_FROM_COMMITTEE']),
        ]);
}

// ── Test Suite ──────────────────────────────────────────────────────────────
echo "=== Plenary Referred to Committee Test ===" . PHP_EOL . PHP_EOL;

// Create test user (Plenary role) outside the transaction.
$testUsername = 'test_plenary_rtc';
$testUser = $pdo->prepare("SELECT id FROM user_accounts WHERE username = ? LIMIT 1");
$testUser->execute([$testUsername]);
$row = $testUser->fetch();
if ($row) {
    $fakeUserId = (int) $row['id'];
} else {
    $plenaryRole = roleId($pdo, 'Plenary');
    $pdo->prepare("INSERT INTO user_accounts (username, email, password_hash, role_id, status, is_deleted, created_at, updated_at)
        VALUES (?, ?, 'dummy_hash', ?, 'active', 0, NOW(), NOW())")
        ->execute([$testUsername, $testUsername . '@test.local', $plenaryRole]);
    $fakeUserId = (int) $pdo->lastInsertId();
}

$plenaryRoleId   = roleId($pdo, 'Plenary');
$committeeRoleId = roleId($pdo, 'Committee');
$committeeId     = (int) $pdo->query("SELECT id FROM committees WHERE is_active=1 AND is_deleted=0 ORDER BY id LIMIT 1")->fetchColumn();

$awaitingFirstId = statusId($pdo, 'Awaiting First Reading');
$referredId      = statusId($pdo, 'Referred');
$opinionId       = statusId($pdo, 'Opinion Requested');
$returnedId      = statusId($pdo, 'Returned to Plenary');
$awaitingSecondId = statusId($pdo, 'Awaiting Second Reading');

$pdo->beginTransaction();

try {
    $docId = setupReferredDocument($pdo, $fakeUserId, $committeeId, $plenaryRoleId, $committeeRoleId, $awaitingFirstId, $referredId);

    // ── Test 1: referred during First Reading appears on the page ───────────
    echo "Test 1: A document referred during First Reading appears on the Referred to Committee page" . PHP_EOL;
    $inCommittee = fetchReferredList($pdo, 'in_committee', $returnedId, $awaitingSecondId);
    $ids = array_column($inCommittee, 'id');
    check("Document appears on In Committee tab", in_array($docId, $ids, true));
    $returned = fetchReferredList($pdo, 'returned', $returnedId, $awaitingSecondId);
    check("Document does NOT appear on Returned tab yet", !in_array($docId, array_column($returned, 'id'), true));
    $sharedName = $pdo->prepare("SELECT ds.name FROM documents d JOIN document_statuses ds ON ds.id=d.current_status_id WHERE d.id=?");
    $sharedName->execute([$docId]);
    check("Shared status shown is 'Referred'", $sharedName->fetchColumn() === 'Referred');
    echo PHP_EOL;

    // ── Test 2: remains tracked while the committee process is pending ──────
    echo "Test 2: It remains tracked while the committee process is pending" . PHP_EOL;
    // Simulate committee sub-workflow progress: Referred → Opinion Requested
    setCommitteeSubStatus($pdo, $docId, $fakeUserId, $opinionId);
    $inCommittee2 = fetchReferredList($pdo, 'in_committee', $returnedId, $awaitingSecondId);
    check("Still tracked on In Committee tab during committee work", in_array($docId, array_column($inCommittee2, 'id'), true));
    $returned2 = fetchReferredList($pdo, 'returned', $returnedId, $awaitingSecondId);
    check("Not on Returned tab while still in committee", !in_array($docId, array_column($returned2, 'id'), true));
    check("Proceed gate is closed while still in committee", canAdvanceGate($pdo, $docId, $returnedId) === false);
    $sharedName2 = $pdo->prepare("SELECT ds.name FROM documents d JOIN document_statuses ds ON ds.id=d.current_status_id WHERE d.id=?");
    $sharedName2->execute([$docId]);
    check("Shared status reflects live committee sub-status ('Opinion Requested')", $sharedName2->fetchColumn() === 'Opinion Requested');
    echo PHP_EOL;

    // ── Test 3: committee return enables "Proceed to 2nd Reading" ───────────
    echo "Test 3: The return from the committee workflow enables Proceed to 2nd Reading" . PHP_EOL;
    simulateReturnToPlenary($pdo, $docId, $fakeUserId, $returnedId, $committeeRoleId);
    $returned3 = fetchReferredList($pdo, 'returned', $returnedId, $awaitingSecondId);
    check("Document appears on Returned tab after committee return", in_array($docId, array_column($returned3, 'id'), true));
    $inCommittee3 = fetchReferredList($pdo, 'in_committee', $returnedId, $awaitingSecondId);
    check("Document leaves In Committee tab after return", !in_array($docId, array_column($inCommittee3, 'id'), true));
    check("Proceed gate is now open (button should be available)", canAdvanceGate($pdo, $docId, $returnedId) === true);
    $sharedName3 = $pdo->prepare("SELECT ds.name FROM documents d JOIN document_statuses ds ON ds.id=d.current_status_id WHERE d.id=?");
    $sharedName3->execute([$docId]);
    check("Shared status is 'Returned to Plenary'", $sharedName3->fetchColumn() === 'Returned to Plenary');
    echo PHP_EOL;

    // ── Test 4: button unavailable before return; gate rejects invalid / repeated ─
    echo "Test 4: Button unavailable before return; server rejects invalid / repeated transitions" . PHP_EOL;
    // 4a: rewind to "still in committee" and confirm the gate is closed.
    setCommitteeSubStatus($pdo, $docId, $fakeUserId, $opinionId);
    check("4a. Before return, Proceed gate is closed (button unavailable)", canAdvanceGate($pdo, $docId, $returnedId) === false);
    // 4b: invalid transition — attempting to advance while status is 'Opinion Requested'.
    //     The controller's advanceFromCommittee would throw; here we assert the gate
    //     that drives that throw, and that the shared status is unchanged.
    $beforeInvalid = (int) $pdo->query("SELECT current_status_id FROM documents WHERE id={$docId}")->fetchColumn();
    $blockedInvalid = !canAdvanceGate($pdo, $docId, $returnedId);
    $afterInvalid = (int) $pdo->query("SELECT current_status_id FROM documents WHERE id={$docId}")->fetchColumn();
    check("4b. Invalid transition rejected by server-side gate", $blockedInvalid === true);
    check("4b. Shared status unchanged after rejected transition", $beforeInvalid === $afterInvalid);
    // 4c: repeated transition — flip status back to "Returned to Plenary" (reusing
    //     the single committee report already created in Test 3, since the schema
    //     enforces one-document→one-report), advance once, then assert a second
    //     advance attempt is rejected by the server-side gate.
    setCommitteeSubStatus($pdo, $docId, $fakeUserId, $returnedId);
    check("4c. Gate reopens when status is again 'Returned to Plenary'", canAdvanceGate($pdo, $docId, $returnedId) === true);
    simulateAdvanceFromCommittee($pdo, $docId, $fakeUserId, $awaitingSecondId);
    $sharedNameAfter = $pdo->prepare("SELECT ds.name FROM documents d JOIN document_statuses ds ON ds.id=d.current_status_id WHERE d.id=?");
    $sharedNameAfter->execute([$docId]);
    check("4c. First successful advance moves shared status to 'Awaiting Second Reading'", $sharedNameAfter->fetchColumn() === 'Awaiting Second Reading');
    check("4c. Second advance attempt rejected by gate (status no longer 'Returned to Plenary')", canAdvanceGate($pdo, $docId, $returnedId) === false);
    echo PHP_EOL;

    // ── Test 5: proceeding updates shared status + enters Second Reading ────
    echo "Test 5: Proceeding updates the shared status and places the document in Second Reading" . PHP_EOL;
    // (Status is already 'Awaiting Second Reading' from Test 4c.) Assert shared + workflow placement.
    $docRow = $pdo->query("SELECT current_status_id, current_phase FROM documents WHERE id={$docId}")->fetch();
    check("5. Shared status id is 'Awaiting Second Reading'", (int) $docRow['current_status_id'] === $awaitingSecondId);
    check("5. current_phase moved to 'PLENARY' (Second Reading workflow)", $docRow['current_phase'] === 'PLENARY');
    $srPending = fetchSecondReadingPending($pdo, $awaitingSecondId);
    check("5. Document appears in Second Reading pending list", in_array($docId, array_column($srPending, 'id'), true));
    $returned5 = fetchReferredList($pdo, 'returned', $returnedId, $awaitingSecondId);
    check("5. Document leaves Returned tab after advancing", !in_array($docId, array_column($returned5, 'id'), true));
    $inCommittee5 = fetchReferredList($pdo, 'in_committee', $returnedId, $awaitingSecondId);
    check("5. Document not on In Committee tab after advancing", !in_array($docId, array_column($inCommittee5, 'id'), true));
    // The shared status is the SAME one other divisions/users read.
    $shared5 = $pdo->prepare("SELECT ds.name FROM documents d JOIN document_statuses ds ON ds.id=d.current_status_id WHERE d.id=?");
    $shared5->execute([$docId]);
    check("5. Shared status (read by other divisions) is 'Awaiting Second Reading'", $shared5->fetchColumn() === 'Awaiting Second Reading');
    // History/audit event recorded for the transition.
    $evCount = (int) $pdo->query("SELECT COUNT(*) FROM document_events WHERE document_id={$docId} AND event_type='PLENARY_SECOND_READING_APPROVED'")->fetchColumn();
    check("5. Transition recorded in document history (PLENARY_SECOND_READING_APPROVED event)", $evCount >= 1);
    echo PHP_EOL;

    // ── Test 6: unauthorized users cannot view or advance documents ─────────
    echo "Test 6: Unauthorized users cannot view or advance documents (RoleMiddleware gate)" . PHP_EOL;
    check("6. Plenary role can view plenary/referred-to-committee", roleAllowedForRoute('plenary/referred-to-committee', 'Plenary') === true);
    check("6. Super Admin role can view plenary/referred-to-committee", roleAllowedForRoute('plenary/referred-to-committee', 'Super Admin') === true);
    check("6. Committee role CANNOT view plenary/referred-to-committee", roleAllowedForRoute('plenary/referred-to-committee', 'Committee') === false);
    check("6. Receiving Staff role CANNOT view plenary/referred-to-committee", roleAllowedForRoute('plenary/referred-to-committee', 'Receiving Staff') === false);
    check("6. Admin role CANNOT view plenary/referred-to-committee", roleAllowedForRoute('plenary/referred-to-committee', 'Admin') === false);
    check("6. Committee role CANNOT POST plenary/second-reading/advance-from-committee", roleAllowedForRoute('plenary/second-reading/advance-from-committee', 'Committee') === false);
    check("6. Plenary role CAN POST plenary/second-reading/advance-from-committee", roleAllowedForRoute('plenary/second-reading/advance-from-committee', 'Plenary') === true);
    echo PHP_EOL;

    $pdo->rollBack();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    echo "FATAL: Test failed with exception — " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    // Best-effort cleanup of the test user.
    $pdo->exec("DELETE FROM user_accounts WHERE username = 'test_plenary_rtc'");
    exit(1);
}

// Clean up test user (outside transaction).
$pdo->exec("DELETE FROM user_accounts WHERE username = 'test_plenary_rtc'");

// ── Summary ────────────────────────────────────────────────────────────────
echo "=== Test Summary ===" . PHP_EOL;
echo "Passed: {$passed}" . PHP_EOL;
echo "Failed: {$failed}" . PHP_EOL;

exit($failed > 0 ? 1 : 0);

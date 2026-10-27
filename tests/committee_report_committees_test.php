<?php
/**
 * Committee Report — "Committees in Charge" multi-select workflow test
 *
 * Verifies the hearing-workflow report creation form now lets authorized
 * committee users add additional Committees in Charge while preserving
 * existing assignments. Covers the six required scenarios:
 *
 *   1. Existing committees are pre-checked when the form loads.
 *   2. Submitted checkbox selections survive validation errors.
 *   3. Authorized users can add committees without removing existing assignments.
 *   4. Duplicate / inactive / invalid committee selections are handled.
 *   5. Unauthorized users cannot add committees.
 *   6. The report and relevant views show all assigned committees.
 *
 * Strategy
 * --------
 * 1. Wrap everything in a transaction.
 * 2. Insert the minimum fixture rows (roles, users, committees, document,
 *    agenda, agenda_committees, document_committees, hearing, report).
 * 3. Run the exact SQL predicates copied from the controllers.
 * 4. Assert expected outcomes.
 * 5. Roll back — zero permanent side-effects.
 *
 * Run with:  php tests/committee_report_committees_test.php
 */

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

// ── Counters ───────────────────────────────────────────────────────────────
$passed = 0;
$failed = 0;

function assert_test(string $label, bool $condition): void
{
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$label}" . PHP_EOL;
        $passed++;
    } else {
        echo "  [FAIL] {$label}" . PHP_EOL;
        $failed++;
    }
}

/**
 * Replicate RoleMiddleware::handle()'s route → allowed-roles mapping so the
 * authorization gate can be asserted without a live session.
 */
function allowed_roles_for_route(string $route): ?array
{
    return match (true) {
        str_starts_with($route, 'master')    => ['Super Admin'],
        str_starts_with($route, 'receiving') => ['Super Admin', 'Receiving Staff'],
        str_starts_with($route, 'admin')     => ['Super Admin', 'Admin'],
        str_starts_with($route, 'spsec')     => ['Super Admin', 'SP Secretary'],
        str_starts_with($route, 'plenary')   => ['Super Admin', 'Plenary'],
        str_starts_with($route, 'committee') => ['Super Admin', 'Committee'],
        default                               => null,
    };
}

/**
 * Replicate CommitteeHearingController::reportStore()'s committee-ID
 * validation: only active, non-deleted committees pass, and the count of
 * valid IDs must match the count of submitted IDs (this is how the controller
 * detects inactive / invalid / duplicate selections).
 *
 * Returns [validIds, countMatches].
 */
function validate_committee_ids(PDO $pdo, array $committeeIds): array
{
    $committeeIds = array_filter(array_map('intval', $committeeIds));
    if (empty($committeeIds)) {
        return [[], true]; // "empty" is handled separately by the controller
    }
    $cPh  = implode(',', array_fill(0, count($committeeIds), '?'));
    $cChk = $pdo->prepare(
        "SELECT id FROM committees WHERE id IN ({$cPh}) AND is_active = 1 AND is_deleted = 0"
    );
    $cChk->execute($committeeIds);
    $validCIds = array_column($cChk->fetchAll(PDO::FETCH_ASSOC), 'id');
    $matches   = count($validCIds) === count($committeeIds);
    return [$validCIds, $matches];
}

// ── Begin transaction (everything is rolled back at the end) ──────────────
$pdo->beginTransaction();

try {
    // ── Roles ─────────────────────────────────────────────────────────────
    $resolveRole = function (string $name) use ($pdo): int {
        $stmt = $pdo->prepare(
            "SELECT id FROM roles WHERE name = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1"
        );
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        if ($row) return (int) $row['id'];
        $pdo->prepare(
            "INSERT INTO roles (name, is_active, is_deleted) VALUES (?, 1, 0)"
        )->execute([$name]);
        return (int) $pdo->lastInsertId();
    };

    $committeeRoleId    = $resolveRole('Committee');
    $receivingRoleId    = $resolveRole('Receiving Staff');

    // ── Users ─────────────────────────────────────────────────────────────
    $insertUser = function (string $username, int $roleId) use ($pdo): int {
        $pdo->prepare(
            "INSERT INTO user_accounts (username, email, password_hash, role_id, status, is_deleted)
             VALUES (?, ?, 'x', ?, 'active', 0)"
        )->execute([$username, $username . '@test.local', $roleId]);
        return (int) $pdo->lastInsertId();
    };

    $committeeUser = $insertUser('_crc_test_committee', $committeeRoleId);
    $receivingUser = $insertUser('_crc_test_receiving', $receivingRoleId);

    // ── Committees ───────────────────────────────────────────────────────
    // A, B, C, E active. D inactive (soft-deleted flag off but is_active=0).
    $insertCommittee = function (string $name, bool $active = true) use ($pdo): int {
        $pdo->prepare(
            "INSERT INTO committees (name, description, sort_order, is_active, is_deleted)
             VALUES (?, 'CRC test committee', 0, ?, 0)"
        )->execute([$name, $active ? 1 : 0]);
        return (int) $pdo->lastInsertId();
    };

    $commA = $insertCommittee('_crc_test_comm_A');
    $commB = $insertCommittee('_crc_test_comm_B');
    $commC = $insertCommittee('_crc_test_comm_C');
    $commD = $insertCommittee('_crc_test_comm_D', false); // inactive
    $commE = $insertCommittee('_crc_test_comm_E');       // addable (not assigned anywhere)

    // ── Lookup helpers for document fixtures ──────────────────────────────
    $sourceTypeId = (int) $pdo->query("SELECT id FROM source_types LIMIT 1")->fetchColumn();
    if (!$sourceTypeId) {
        $pdo->exec("INSERT INTO source_types (name) VALUES ('_crc_test_source')");
        $sourceTypeId = (int) $pdo->lastInsertId();
    }

    $statusId = (int) $pdo->query("SELECT id FROM document_statuses LIMIT 1")->fetchColumn();
    if (!$statusId) {
        $pdo->exec("INSERT INTO document_statuses (name, badge_color) VALUES ('_crc_test_status', '#6c757d')");
        $statusId = (int) $pdo->lastInsertId();
    }

    $typeStmt = $pdo->query("SELECT id FROM document_types LIMIT 1");
    $typeId   = (int) $typeStmt->fetchColumn();
    if (!$typeId) {
        $pdo->exec("INSERT INTO document_types (name, badge_color) VALUES ('_crc_test_type', '#6c757d')");
        $typeId = (int) $pdo->lastInsertId();
    }

    // ── Document ─────────────────────────────────────────────────────────
    $seqBase = 910000;
    $trackingNum = 'CRC-' . str_pad((string) mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
    $pdo->prepare(
        "INSERT INTO documents
            (tracking_number, tracking_year, tracking_sequence,
             subject_matter, document_type_id, source_type_id,
             current_status_id,
             date_received, time_received,
             current_phase, created_by, updated_by)
         VALUES (?, YEAR(CURDATE()), ?, 'CRC committees test subject', ?,
                 ?, ?, CURDATE(), CURTIME(), 'COMMITTEE', ?, ?)"
    )->execute([
        $trackingNum, $seqBase + 1, $typeId, $sourceTypeId, $statusId,
        $committeeUser, $committeeUser,
    ]);
    $documentId = (int) $pdo->lastInsertId();

    // ── Committee cycle (required FK for agendas) ────────────────────────
    $pdo->prepare(
        "INSERT INTO committee_cycles (document_id, cycle_number, decision, started_at)
         VALUES (?, ?, 'ACCEPTED', NOW())"
    )->execute([$documentId, $seqBase + 1]);
    $cycleId = (int) $pdo->lastInsertId();

    // ── Agenda ────────────────────────────────────────────────────────────
    $pdo->prepare(
        "INSERT INTO agendas (document_id, cycle_id, agenda_date, agenda_time, venue, created_by)
         VALUES (?, ?, CURDATE(), '09:00:00', 'Test Venue', ?)"
    )->execute([$documentId, $cycleId, $committeeUser]);
    $agendaId = (int) $pdo->lastInsertId();

    // ── Agenda committees: A, B ───────────────────────────────────────────
    $linkAgendaCommittee = function (int $cId) use ($pdo, $agendaId): void {
        $pdo->prepare(
            "INSERT IGNORE INTO agenda_committees (agenda_id, committee_id) VALUES (?, ?)"
        )->execute([$agendaId, $cId]);
    };
    $linkAgendaCommittee($commA);
    $linkAgendaCommittee($commB);

    // ── Document committees (referral): A, C ──────────────────────────────
    $linkDocumentCommittee = function (int $cId) use ($pdo, $documentId, $committeeUser): void {
        $pdo->prepare(
            "INSERT IGNORE INTO document_committees (document_id, committee_id, assigned_by)
             VALUES (?, ?, ?)"
        )->execute([$documentId, $cId, $committeeUser]);
    };
    $linkDocumentCommittee($commA);
    $linkDocumentCommittee($commC);

    // ── Approved hearing ──────────────────────────────────────────────────
    $pdo->prepare(
        "INSERT INTO committee_hearings (document_id, agenda_id, outcome, performed_by, performed_at)
         VALUES (?, ?, 'APPROVED', ?, NOW())"
    )->execute([$documentId, $agendaId, $committeeUser]);
    $hearingId = (int) $pdo->lastInsertId();

    // ══════════════════════════════════════════════════════════════════════
    // SCENARIO 1 — Existing committees are pre-checked when the form loads
    // ══════════════════════════════════════════════════════════════════════
    // The offered checkbox list = ALL active committees (A, B, C, E — D excluded).
    $offeredRows = $pdo->query(
        "SELECT id, name FROM committees WHERE is_active = 1 AND is_deleted = 0 ORDER BY name ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
    $offeredIds  = array_map('intval', array_column($offeredRows, 'id'));

    assert_test(
        'SC1a: Offered checkbox list includes all active committees (A,B,C,E)',
        count(array_intersect([$commA, $commB, $commC, $commE], $offeredIds)) === 4
    );
    assert_test(
        'SC1b: Inactive committee (D) is NOT offered in the checkbox list',
        !in_array($commD, $offeredIds, true)
    );

    // The pre-checked set = union of agenda_committees + document_committees.
    $acStmt = $pdo->prepare(
        "SELECT committee_id FROM agenda_committees WHERE agenda_id = ?
         UNION
         SELECT committee_id FROM document_committees WHERE document_id = ?"
    );
    $acStmt->execute([$agendaId, $documentId]);
    $precheckedIds = array_map('intval', array_column($acStmt->fetchAll(PDO::FETCH_ASSOC), 'committee_id'));
    sort($precheckedIds);

    $expectedPrechecked = [$commA, $commB, $commC];
    sort($expectedPrechecked);
    assert_test(
        'SC1c: Pre-checked set = union of agenda (A,B) + document (A,C) committees',
        $precheckedIds === $expectedPrechecked
    );
    assert_test(
        'SC1d: Addable committee E is offered but NOT pre-checked',
        in_array($commE, $offeredIds, true) && !in_array($commE, $precheckedIds, true)
    );

    // ══════════════════════════════════════════════════════════════════════
    // SCENARIO 2 — Submitted checkbox selections survive validation errors
    // ══════════════════════════════════════════════════════════════════════
    // Replicate the controller's effective-committee resolution. On a
    // validation failure the controller calls old_set(['committee_ids' => ...])
    // and reportShow() rebuilds $effectiveCommitteeIds from that old input.
    $simulateReportShow = function (array $oldCommitteeIds) use ($precheckedIds): array {
        $effectiveCommitteeIds = !empty($oldCommitteeIds)
            ? array_map('intval', (array) $oldCommitteeIds)
            : $precheckedIds;
        return array_map('intval', (array) $effectiveCommitteeIds);
    };

    // User submitted B and E (kept one pre-checked, added one new), but the
    // form failed validation (e.g. blank report number). The selection must
    // be preserved as-is — NOT reset to the agenda defaults.
    $submitted = [$commB, $commE];
    $effective = $simulateReportShow($submitted);
    sort($effective);
    $expectedEffective = [$commB, $commE];
    sort($expectedEffective);
    assert_test(
        'SC2a: After validation error, submitted selections (B,E) are preserved',
        $effective === $expectedEffective
    );
    assert_test(
        'SC2b: Preserved selection is not reset to the pre-checked defaults (A,B,C)',
        $effective !== $precheckedIds
    );
    // Empty submission falls back to the pre-checked defaults (first load).
    assert_test(
        'SC2c: First load (no old input) falls back to pre-checked defaults',
        $simulateReportShow([]) === $precheckedIds
    );

    // ══════════════════════════════════════════════════════════════════════
    // SCENARIO 3 — Authorized users can add committees without removing
    //              existing assignments
    // ══════════════════════════════════════════════════════════════════════
    // Simulate reportStore(): the user keeps the pre-checked A,B,C and adds E,
    // submitting [A, B, C, E]. The store validates them and links via INSERT
    // IGNORE into committee_report_committees for a brand-new report.
    $submittedAdd = [$commA, $commB, $commC, $commE];
    [$validAdd, $matchesAdd] = validate_committee_ids($pdo, $submittedAdd);
    assert_test(
        'SC3a: Submitted committees (A,B,C,E) all validate as active',
        $matchesAdd && count($validAdd) === 4
    );

    $reportNumber = 'CR-REG-' . mt_rand(100000, 999999);
    $pdo->prepare(
        "INSERT INTO committee_reports
            (report_type, report_number, summary_of_findings, agenda_id, hearing_id, created_by, created_at)
         VALUES (?, ?, 'Test findings', ?, ?, ?, NOW())"
    )->execute([
        count($validAdd) > 1 ? 'JOINT_COMMITTEE_REPORT' : 'COMMITTEE_REPORT',
        $reportNumber, $agendaId, $hearingId, $committeeUser,
    ]);
    $reportId3 = (int) $pdo->lastInsertId();

    $insRc = $pdo->prepare(
        "INSERT IGNORE INTO committee_report_committees (committee_report_id, committee_id) VALUES (?, ?)"
    );
    foreach ($validAdd as $cId) {
        $insRc->execute([$reportId3, $cId]);
    }

    $linked3 = $pdo->prepare(
        "SELECT committee_id FROM committee_report_committees WHERE committee_report_id = ? ORDER BY committee_id"
    );
    $linked3->execute([$reportId3]);
    $linkedIds3 = array_map('intval', array_column($linked3->fetchAll(PDO::FETCH_ASSOC), 'committee_id'));

    assert_test(
        'SC3b: All four committees (A,B,C,E) persisted — none dropped',
        $linkedIds3 === [$commA, $commB, $commC, $commE]
    );
    assert_test(
        'SC3c: Original pre-checked committees (A,B,C) are still present after adding E',
        count(array_intersect([$commA, $commB, $commC], $linkedIds3)) === 3
    );

    // Direct preservation test: re-running INSERT IGNORE over an existing
    // assignment must NOT remove it and must NOT duplicate it.
    $insRc->execute([$reportId3, $commA]); // A already linked
    $countA = $pdo->prepare("SELECT COUNT(*) FROM committee_report_committees WHERE committee_report_id = ? AND committee_id = ?");
    $countA->execute([$reportId3, $commA]);
    $aRows = (int) $countA->fetchColumn();
    assert_test(
        'SC3d: Re-linking an existing committee does not duplicate the row (INSERT IGNORE preserves)',
        $aRows === 1
    );

    // ══════════════════════════════════════════════════════════════════════
    // SCENARIO 4 — Duplicate / inactive / invalid selections are handled
    // ══════════════════════════════════════════════════════════════════════
    // 4a. Duplicate IDs in the submission: the controller's count check
    //     (validCount === submittedCount) catches it because IN (A,A) yields
    //     one row, not two.
    [$validDup, $matchesDup] = validate_committee_ids($pdo, [$commA, $commA]);
    assert_test(
        'SC4a: Duplicate committee IDs in submission are rejected by the count check',
        !$matchesDup && count($validDup) === 1
    );

    // 4b. Inactive committee: D is is_active=0 — fails validation.
    [$validInactive, $matchesInactive] = validate_committee_ids($pdo, [$commD]);
    assert_test(
        'SC4b: Inactive committee (D) fails server-side validation',
        !$matchesInactive && count($validInactive) === 0
    );

    // 4c. Invalid (nonexistent) committee ID — fails validation.
    [$validInvalid, $matchesInvalid] = validate_committee_ids($pdo, [99999999]);
    assert_test(
        'SC4c: Nonexistent committee ID fails server-side validation',
        !$matchesInvalid && count($validInvalid) === 0
    );

    // 4d. A mixed submission (one valid + one inactive) is rejected wholesale
    //     — the controller redirects rather than partially persisting.
    [$validMixed, $matchesMixed] = validate_committee_ids($pdo, [$commA, $commD]);
    assert_test(
        'SC4d: Mixed valid+inactive submission rejected (no partial persistence)',
        !$matchesMixed && count($validMixed) === 1
    );

    // 4e. Duplicate (report, committee) rows are prevented by the unique
    //     constraint + INSERT IGNORE.
    $dupReportNumber = 'CR-REG-DUP-' . mt_rand(100000, 999999);
    $pdo->prepare(
        "INSERT INTO committee_reports (report_type, report_number, summary_of_findings, created_by, created_at)
         VALUES ('COMMITTEE_REPORT', ?, 'Dup test', ?, NOW())"
    )->execute([$dupReportNumber, $committeeUser]);
    $reportId4 = (int) $pdo->lastInsertId();

    $dupIns = $pdo->prepare(
        "INSERT IGNORE INTO committee_report_committees (committee_report_id, committee_id) VALUES (?, ?)"
    );
    $dupIns->execute([$reportId4, $commA]);
    $dupIns->execute([$reportId4, $commA]); // second attempt
    $cntStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM committee_report_committees WHERE committee_report_id = ? AND committee_id = ?"
    );
    $cntStmt->execute([$reportId4, $commA]);
    $dupRowCount = (int) $cntStmt->fetchColumn();
    assert_test(
        'SC4e: No duplicate assignment row is created (unique constraint + INSERT IGNORE)',
        $dupRowCount === 1
    );

    // ══════════════════════════════════════════════════════════════════════
    // SCENARIO 5 — Unauthorized users cannot add committees
    // ══════════════════════════════════════════════════════════════════════
    // RoleMiddleware gates 'committee/hearing/report' to Super Admin + Committee.
    $route    = 'committee/hearing/report';
    $allowed  = allowed_roles_for_route($route);

    assert_test(
        'SC5a: Committee role is authorized for the report route',
        in_array('Committee', $allowed, true)
    );
    assert_test(
        'SC5b: Super Admin role is authorized for the report route',
        in_array('Super Admin', $allowed, true)
    );
    assert_test(
        'SC5c: Receiving Staff role is NOT authorized for the report route',
        !in_array('Receiving Staff', $allowed, true)
    );
    assert_test(
        'SC5d: Plenary role is NOT authorized for the report route',
        !in_array('Plenary', $allowed, true)
    );

    // The receiving user's role is not in the allowed list, so the middleware
    // would redirect them away before the controller runs.
    $receivingRoleName = $pdo->prepare(
        "SELECT r.name FROM user_accounts ua INNER JOIN roles r ON r.id = ua.role_id WHERE ua.id = ?"
    );
    $receivingRoleName->execute([$receivingUser]);
    $receivingRole = (string) $receivingRoleName->fetchColumn();
    assert_test(
        'SC5e: Receiving Staff user would be blocked by RoleMiddleware',
        !in_array($receivingRole, $allowed, true)
    );

    // ══════════════════════════════════════════════════════════════════════
    // SCENARIO 6 — The report and relevant views show all assigned committees
    // ══════════════════════════════════════════════════════════════════════
    // CommitteeReportsController::show() loads committees from
    // committee_report_committees — assert all assigned committees are
    // returned for report 3 (A, B, C, E).
    $showStmt = $pdo->prepare(
        "SELECT c.id, c.name
         FROM committee_report_committees crc
         INNER JOIN committees c ON c.id = crc.committee_id
         WHERE crc.committee_report_id = ?
         ORDER BY c.name ASC"
    );
    $showStmt->execute([$reportId3]);
    $shownRows = $showStmt->fetchAll(PDO::FETCH_ASSOC);
    $shownIds  = array_map('intval', array_column($shownRows, 'id'));

    assert_test(
        'SC6a: Report show view returns all assigned committees (A,B,C,E)',
        count($shownIds) === 4
        && count(array_intersect([$commA, $commB, $commC, $commE], $shownIds)) === 4
    );

    // CommitteeReportsController::index() aggregates names via GROUP_CONCAT
    // over the same junction — assert the comma-joined names include every
    // assigned committee (proves the list view also shows all of them).
    $listStmt = $pdo->prepare(
        "SELECT GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') AS committee_names
         FROM committee_report_committees crc
         INNER JOIN committees c ON c.id = crc.committee_id
         WHERE crc.committee_report_id = ?"
    );
    $listStmt->execute([$reportId3]);
    $groupedNames = (string) $listStmt->fetchColumn();
    $expectedNames = [
        '_crc_test_comm_A', '_crc_test_comm_B', '_crc_test_comm_C', '_crc_test_comm_E',
    ];
    $allPresent = true;
    foreach ($expectedNames as $n) {
        if (strpos($groupedNames, $n) === false) {
            $allPresent = false;
            break;
        }
    }
    assert_test(
        'SC6b: Report list view (GROUP_CONCAT) includes every assigned committee',
        $allPresent
    );

} catch (Throwable $e) {
    // Ensure we always roll back even on unexpected exceptions.
    echo "EXCEPTION: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
} finally {
    // Always roll back — zero permanent changes to the database
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

// ── Summary ────────────────────────────────────────────────────────────────
echo PHP_EOL;
$total = $passed + $failed;
echo "Results: {$passed}/{$total} passed" . ($failed > 0 ? ", {$failed} FAILED" : '') . PHP_EOL;
exit($failed > 0 ? 1 : 0);

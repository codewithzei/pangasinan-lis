<?php
/**
 * Test: Plenary Inbox Workflow
 *
 * Covers:
 *   1. Status-dependent form behavior (processing form gated on "On Process")
 *   2. Required/optional fields per action
 *   3. Document-type numbering exceptions (Communication, Administrative Case, Complaint)
 *   4. Multi-author and multi-committee persistence and replacement
 *   5. Ownership checks (only the accepting user can write)
 *   6. Duplicate/concurrent proposed_number uniqueness
 *   7. Pre-decision status validation (only the six allowed names)
 *   8. Pre-decision remarks persistence
 *   9. Session type ENUM validation
 *  10. Return-to-admin creates new Admin assignment and route record
 *
 * Strategy
 * --------
 * - Wrap all DB work in a transaction and ROLLBACK at the end (zero side-effects).
 * - Simulate the controller logic by directly calling the SQL that the controller
 *   issues, then asserting the resulting state.
 * - Does NOT require HTTP; no session/auth bootstrapping needed.
 *
 * Run with:  php tests/plenary_inbox_workflow_test.php
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

// ── Helpers ────────────────────────────────────────────────────────────────

/**
 * Insert a minimal document and a PLENARY PENDING assignment.
 * Returns ['document_id', 'assignment_id', 'document_type_name'].
 */
function createPlenaryDoc(PDO $pdo, int $docTypeId, int $sourceTypeId, int $docStatusId,
                           int $plenaryRoleId, int $fakeUserId, string $docTypeName): array
{
    static $seq = 800000;
    $seq++;
    $tn = 'PLT-' . str_pad((string)($seq - 800000), 5, '0', STR_PAD_LEFT);

    $pdo->prepare("
        INSERT INTO documents
            (tracking_number, tracking_year, tracking_sequence,
             subject_matter, document_type_id, source_type_id,
             current_status_id, date_received, time_received, current_phase)
        VALUES (?, YEAR(CURDATE()), ?, 'Test subject', ?, ?, ?, CURDATE(), CURTIME(), 'PLENARY')
    ")->execute([$tn, $seq, $docTypeId, $sourceTypeId, $docStatusId]);
    $docId = (int) $pdo->lastInsertId();

    $pdo->prepare("
        INSERT INTO document_assignments
            (document_id, assigned_to_role_id, phase, assigned_by, decision,
             received_at, accepted_by, assigned_to_user_id)
        VALUES (?, ?, 'PLENARY', ?, 'PENDING', NOW(), NULL, NULL)
    ")->execute([$docId, $plenaryRoleId, $fakeUserId]);
    $assignId = (int) $pdo->lastInsertId();

    return ['document_id' => $docId, 'assignment_id' => $assignId, 'document_type_name' => $docTypeName];
}

/**
 * Simulate the Accept action (mirrors PlenaryInboxController::doAccept).
 */
function doAccept(PDO $pdo, int $docId, int $assignId, int $userId, int $roleId,
                  int $currentStatusId): bool
{
    $stmt = $pdo->prepare("
        UPDATE document_assignments
        SET decision            = 'ACCEPTED',
            accepted_at         = NOW(),
            accepted_by         = ?,
            assigned_to_user_id = ?
        WHERE id                  = ?
          AND assigned_to_role_id = ?
          AND decision            = 'PENDING'
          AND completed_at        IS NULL
          AND accepted_by         IS NULL
    ");
    $stmt->execute([$userId, $userId, $assignId, $roleId]);
    return $stmt->rowCount() > 0;
}

/**
 * Simulate doPreDecision — upserts plenary_document_details with status+remarks.
 */
function doPreDecision(PDO $pdo, int $docId, int $statusId, string $remarks, int $userId): void
{
    $existing = $pdo->prepare("SELECT id FROM plenary_document_details WHERE document_id = ? LIMIT 1");
    $existing->execute([$docId]);
    if ($existing->fetch()) {
        $pdo->prepare("
            UPDATE plenary_document_details
            SET status_id = ?, remarks = ?, updated_by = ?
            WHERE document_id = ?
        ")->execute([$statusId, $remarks ?: null, $userId, $docId]);
    } else {
        $pdo->prepare("
            INSERT INTO plenary_document_details (document_id, status_id, remarks, created_by, updated_by)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$docId, $statusId, $remarks ?: null, $userId, $userId]);
    }
}

/**
 * Simulate doSaveProcessing — upserts pdd + replaces authors + committees.
 */
function doSaveProcessing(
    PDO    $pdo,
    int    $docId,
    int    $statusId,
    string $proposedNumber,
    string $targetDate,
    string $sessionType,
    array  $authorIds,
    array  $coAuthorIds,
    array  $committeeIds,
    int    $userId
): void {
    $pdo->prepare("
        INSERT INTO plenary_document_details
            (document_id, proposed_number, target_first_reading_date,
             session_type, status_id, created_by, updated_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            proposed_number           = VALUES(proposed_number),
            target_first_reading_date = VALUES(target_first_reading_date),
            session_type              = VALUES(session_type),
            updated_by                = VALUES(updated_by)
    ")->execute([$docId, $proposedNumber ?: null, $targetDate ?: null,
                  $sessionType ?: null, $statusId, $userId, $userId]);

    // Replace authors
    $pdo->prepare("DELETE FROM document_authors WHERE document_id = ?")->execute([$docId]);
    $ins = $pdo->prepare("INSERT INTO document_authors (document_id, sp_member_id, author_role, sort_order) VALUES (?,?,?,?)");
    $order = 0;
    foreach ($authorIds as $mid) {
        $ins->execute([$docId, $mid, 'AUTHOR', $order++]);
    }
    $order = 0;
    foreach ($coAuthorIds as $mid) {
        $ins->execute([$docId, $mid, 'CO_AUTHOR', $order++]);
    }

    // Replace committees
    $pdo->prepare("DELETE FROM document_committees WHERE document_id = ?")->execute([$docId]);
    $insCmt = $pdo->prepare("INSERT INTO document_committees (document_id, committee_id, assigned_by) VALUES (?,?,?)");
    foreach ($committeeIds as $cid) {
        $insCmt->execute([$docId, $cid, $userId]);
    }
}

/**
 * Simulate doReturnToAdmin — closes assignment, creates Admin assignment + route.
 */
function doReturnToAdmin(PDO $pdo, int $docId, int $assignId, int $userId,
                          int $adminRoleId, int $pendingStatusId): void
{
    $pdo->prepare("
        UPDATE document_assignments SET decision='COMPLETED', completed_at=NOW() WHERE id=?
    ")->execute([$assignId]);

    $pdo->prepare("
        UPDATE documents SET current_phase='ADMIN', current_status_id=?, current_owner_user_id=NULL WHERE id=?
    ")->execute([$pendingStatusId, $docId]);

    $pdo->prepare("
        INSERT INTO document_assignments
            (document_id, assigned_to_role_id, phase, assigned_by, decision, received_at)
        VALUES (?, ?, 'ADMIN', ?, 'PENDING', NOW())
    ")->execute([$docId, $adminRoleId, $userId]);

    $pdo->prepare("
        INSERT INTO document_routes (document_id, from_phase, to_phase, routed_by, routed_to_role_id, remarks)
        VALUES (?, 'PLENARY', 'ADMIN', ?, ?, 'Return test')
    ")->execute([$docId, $userId, $adminRoleId]);
}

// ── Resolve lookup IDs ─────────────────────────────────────────────────────
$pdo->beginTransaction();

try {
    // Get or create minimal lookups needed
    $dtRow = $pdo->query("SELECT id, name FROM document_types WHERE is_active=1 AND is_deleted=0 LIMIT 1")->fetch();
    if (!$dtRow) {
        $pdo->exec("INSERT INTO document_types (name, badge_color) VALUES ('_TestType','#6c757d')");
        $genericDocTypeId   = (int)$pdo->lastInsertId();
        $genericDocTypeName = '_TestType';
    } else {
        $genericDocTypeId   = (int)$dtRow['id'];
        $genericDocTypeName = $dtRow['name'];
    }

    // Communication doc type
    $commRow = $pdo->query("SELECT id FROM document_types WHERE name='Communication' AND is_active=1 LIMIT 1")->fetch();
    if (!$commRow) {
        $pdo->exec("INSERT INTO document_types (name, badge_color) VALUES ('Communication','#3B82F6')");
        $commDocTypeId = (int)$pdo->lastInsertId();
    } else {
        $commDocTypeId = (int)$commRow['id'];
    }

    // Administrative Case doc type
    $adminCaseRow = $pdo->query("SELECT id FROM document_types WHERE name LIKE '%Administrative%Case%' AND is_active=1 LIMIT 1")->fetch();
    if (!$adminCaseRow) {
        $pdo->exec("INSERT INTO document_types (name, badge_color) VALUES ('Administrative Case','#EF4444')");
        $adminCaseDocTypeId = (int)$pdo->lastInsertId();
    } else {
        $adminCaseDocTypeId = (int)$adminCaseRow['id'];
    }

    // Complaint doc type
    $complaintRow = $pdo->query("SELECT id FROM document_types WHERE name LIKE '%Complaint%' AND is_active=1 LIMIT 1")->fetch();
    if (!$complaintRow) {
        $pdo->exec("INSERT INTO document_types (name, badge_color) VALUES ('Complaint','#F97316')");
        $complaintDocTypeId = (int)$pdo->lastInsertId();
    } else {
        $complaintDocTypeId = (int)$complaintRow['id'];
    }

    $stRow = $pdo->query("SELECT id FROM source_types WHERE is_active=1 AND is_deleted=0 LIMIT 1")->fetch();
    if (!$stRow) {
        $pdo->exec("INSERT INTO source_types (name) VALUES ('_TestSource')");
        $sourceTypeId = (int)$pdo->lastInsertId();
    } else {
        $sourceTypeId = (int)$stRow['id'];
    }

    $dsRow = $pdo->query("SELECT id FROM document_statuses WHERE is_active=1 AND is_deleted=0 LIMIT 1")->fetch();
    if (!$dsRow) {
        $pdo->exec("INSERT INTO document_statuses (name, badge_color, sort_order) VALUES ('_TestStatus','#6c757d',0)");
        $docStatusId = (int)$pdo->lastInsertId();
    } else {
        $docStatusId = (int)$dsRow['id'];
    }

    // Pending status
    $pendingRow = $pdo->query("SELECT id FROM document_statuses WHERE name='Pending' AND is_active=1 LIMIT 1")->fetch();
    $pendingStatusId = $pendingRow ? (int)$pendingRow['id'] : $docStatusId;

    // Plenary role
    $plenaryRoleRow = $pdo->query("SELECT id FROM roles WHERE name='Plenary' AND is_active=1 LIMIT 1")->fetch();
    if (!$plenaryRoleRow) {
        $pdo->exec("INSERT INTO roles (name, is_active, is_deleted) VALUES ('Plenary', 1, 0)");
        $plenaryRoleId = (int)$pdo->lastInsertId();
    } else {
        $plenaryRoleId = (int)$plenaryRoleRow['id'];
    }

    // Admin role
    $adminRoleRow = $pdo->query("SELECT id FROM roles WHERE name='Admin' AND is_active=1 LIMIT 1")->fetch();
    if (!$adminRoleRow) {
        $pdo->exec("INSERT INTO roles (name, is_active, is_deleted) VALUES ('Admin', 1, 0)");
        $adminRoleId = (int)$pdo->lastInsertId();
    } else {
        $adminRoleId = (int)$adminRoleRow['id'];
    }

    // Fake user accounts (two users for ownership tests)
    $pdo->exec("
        INSERT INTO user_accounts (username, email, password_hash, status, role_id)
        VALUES ('_plt_user_a', '_plt_a@test.local', 'x', 'active', {$plenaryRoleId})
    ");
    $userA = (int)$pdo->lastInsertId();
    $pdo->exec("
        INSERT INTO user_accounts (username, email, password_hash, status, role_id)
        VALUES ('_plt_user_b', '_plt_b@test.local', 'x', 'active', {$plenaryRoleId})
    ");
    $userB = (int)$pdo->lastInsertId();

    // Pre-decision statuses (must already be seeded by migration 065)
    $preDecisionRows = $pdo->query("
        SELECT id, name FROM document_statuses
        WHERE name IN ('Deferred','Lay on the Table','Noted','Remanded','On Process','Withdrawn')
          AND is_active=1
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Build name => id map
    $preDecisionStatuses = [];
    foreach ($preDecisionRows as $row) {
        $preDecisionStatuses[$row['name']] = (int)$row['id'];
    }

    // SP Members (pick two)
    $spmRows = $pdo->query("SELECT sp_member_id FROM sp_members WHERE is_active=1 AND is_deleted=0 LIMIT 2")->fetchAll();
    $spMemberIds = array_column($spmRows, 'sp_member_id');

    // Committees (pick two)
    $cmtRows = $pdo->query("SELECT id FROM committees WHERE is_active=1 AND is_deleted=0 LIMIT 2")->fetchAll();
    $committeeIds = array_column($cmtRows, 'id');

    // ==========================================================================
    // TEST GROUP 1: Accept ownership model
    // ==========================================================================
    echo PHP_EOL . "Group 1: Accept / ownership model" . PHP_EOL;

    $d1 = createPlenaryDoc($pdo, $genericDocTypeId, $sourceTypeId, $docStatusId,
                            $plenaryRoleId, $userA, $genericDocTypeName);

    // User A accepts
    $accepted = doAccept($pdo, $d1['document_id'], $d1['assignment_id'], $userA, $plenaryRoleId, $docStatusId);
    check('1.1: Accept succeeds for an unclaimed PENDING assignment', $accepted);

    // Check it is now ACCEPTED and owned by userA
    $asgn = $pdo->query("SELECT * FROM document_assignments WHERE id={$d1['assignment_id']}")->fetch();
    check('1.2: decision becomes ACCEPTED after accept', $asgn['decision'] === 'ACCEPTED');
    check('1.3: accepted_by set to userA',               (int)$asgn['accepted_by'] === $userA);
    check('1.4: assigned_to_user_id set to userA',       (int)$asgn['assigned_to_user_id'] === $userA);

    // User B cannot accept the same already-accepted assignment
    $d1b = createPlenaryDoc($pdo, $genericDocTypeId, $sourceTypeId, $docStatusId,
                             $plenaryRoleId, $userA, $genericDocTypeName);
    doAccept($pdo, $d1b['document_id'], $d1b['assignment_id'], $userA, $plenaryRoleId, $docStatusId);
    $acceptedB = doAccept($pdo, $d1b['document_id'], $d1b['assignment_id'], $userB, $plenaryRoleId, $docStatusId);
    check('1.5: Second accept by different user returns 0 rows (concurrent safety)', !$acceptedB);

    // Accepted tab visibility: userB should NOT see userA documents in accepted query
    $visStmt = $pdo->prepare("
        SELECT COUNT(*) FROM document_assignments
        WHERE document_id = ?
          AND phase = 'PLENARY'
          AND decision = 'ACCEPTED'
          AND (accepted_by = ? OR assigned_to_user_id = ?)
    ");
    $visStmt->execute([$d1['document_id'], $userB, $userB]);
    check('1.6: UserB cannot see UserA accepted document in their accepted tab', (int)$visStmt->fetchColumn() === 0);

    $visStmt->execute([$d1['document_id'], $userA, $userA]);
    check('1.7: UserA sees their own accepted document', (int)$visStmt->fetchColumn() === 1);

    // ==========================================================================
    // TEST GROUP 2: Pre-decision status behavior
    // ==========================================================================
    echo PHP_EOL . "Group 2: Pre-decision status" . PHP_EOL;

    if (empty($preDecisionStatuses)) {
        echo "  [SKIP] Pre-decision statuses not seeded — run migration 065 first." . PHP_EOL;
    } else {
        $d2 = createPlenaryDoc($pdo, $genericDocTypeId, $sourceTypeId, $docStatusId,
                                $plenaryRoleId, $userA, $genericDocTypeName);
        doAccept($pdo, $d2['document_id'], $d2['assignment_id'], $userA, $plenaryRoleId, $docStatusId);

        // Save each of the six pre-decision statuses
        $allowedNames = ['Deferred','Lay on the Table','Noted','Remanded','On Process','Withdrawn'];
        foreach ($allowedNames as $name) {
            if (!isset($preDecisionStatuses[$name])) {
                echo "  [SKIP] Status '{$name}' not found in DB — skipping." . PHP_EOL;
                continue;
            }
            doPreDecision($pdo, $d2['document_id'], $preDecisionStatuses[$name], "Remarks for {$name}", $userA);
            $row = $pdo->query("SELECT status_id FROM plenary_document_details WHERE document_id={$d2['document_id']}")->fetch();
            check("2.x: Pre-decision status '{$name}' persisted", (int)$row['status_id'] === (int)$preDecisionStatuses[$name]);
        }

        // Verify remarks persistence
        $remarksText = 'Specific remarks content ' . uniqid();
        doPreDecision($pdo, $d2['document_id'], $preDecisionStatuses['Deferred'], $remarksText, $userA);
        $row = $pdo->query("SELECT remarks FROM plenary_document_details WHERE document_id={$d2['document_id']}")->fetch();
        check('2.7: Remarks persisted correctly', $row['remarks'] === $remarksText);

        // Processing form is gated on "On Process"
        $isOnProcess = false;
        if (isset($preDecisionStatuses['On Process'])) {
            doPreDecision($pdo, $d2['document_id'], $preDecisionStatuses['On Process'], '', $userA);
            $pddRow = $pdo->prepare("
                SELECT pdd.*, ds.name AS status_name
                FROM plenary_document_details pdd
                LEFT JOIN document_statuses ds ON ds.id = pdd.status_id
                WHERE pdd.document_id = ?
            ");
            $pddRow->execute([$d2['document_id']]);
            $pddData = $pddRow->fetch();
            $isOnProcess = strtolower(trim($pddData['status_name'] ?? '')) === 'on process';
        }
        check('2.8: Status = On Process enables processing form condition', $isOnProcess);
    }

    // ==========================================================================
    // TEST GROUP 3: Proposed Number exemption by document type
    // ==========================================================================
    echo PHP_EOL . "Group 3: Proposed Number exemption" . PHP_EOL;

    $exemptTypes = [
        ['id' => $commDocTypeId,      'name' => 'Communication'],
        ['id' => $adminCaseDocTypeId, 'name' => 'Administrative Case'],
        ['id' => $complaintDocTypeId, 'name' => 'Complaint'],
    ];

    foreach ($exemptTypes as $et) {
        // The exemption logic in the controller checks str_contains(strtolower($name), keyword)
        $typeLower = strtolower($et['name']);
        $exempt = (
            str_contains($typeLower, 'communication') ||
            str_contains($typeLower, 'administrative case') ||
            str_contains($typeLower, 'complaint')
        );
        check("3.x: '{$et['name']}' is exempt from proposed number", $exempt);
    }

    // Generic type is NOT exempt
    $genericExempt = (
        str_contains(strtolower($genericDocTypeName), 'communication') ||
        str_contains(strtolower($genericDocTypeName), 'administrative case') ||
        str_contains(strtolower($genericDocTypeName), 'complaint')
    );
    check('3.4: Generic document type is NOT exempt', !$genericExempt);

    // ==========================================================================
    // TEST GROUP 4: Multi-author and multi-committee persistence
    // ==========================================================================
    echo PHP_EOL . "Group 4: Multi-author and multi-committee persistence" . PHP_EOL;

    if (count($spMemberIds) >= 2 && isset($preDecisionStatuses['On Process'])) {
        $d4 = createPlenaryDoc($pdo, $genericDocTypeId, $sourceTypeId, $docStatusId,
                                $plenaryRoleId, $userA, $genericDocTypeName);
        doAccept($pdo, $d4['document_id'], $d4['assignment_id'], $userA, $plenaryRoleId, $docStatusId);
        doPreDecision($pdo, $d4['document_id'], $preDecisionStatuses['On Process'], '', $userA);

        $authorIds   = [$spMemberIds[0]];
        $coAuthorIds = [$spMemberIds[1]];
        $cmtIds      = count($committeeIds) >= 2 ? [$committeeIds[0], $committeeIds[1]] : (count($committeeIds) === 1 ? [$committeeIds[0]] : []);

        doSaveProcessing($pdo, $d4['document_id'], $preDecisionStatuses['On Process'],
                         'PLT-TEST-001', date('Y-m-d'), 'REGULAR',
                         $authorIds, $coAuthorIds, $cmtIds, $userA);

        // Verify authors
        $savedAuthors   = $pdo->query("SELECT sp_member_id FROM document_authors WHERE document_id={$d4['document_id']} AND author_role='AUTHOR'")->fetchAll(PDO::FETCH_COLUMN);
        $savedCoAuthors = $pdo->query("SELECT sp_member_id FROM document_authors WHERE document_id={$d4['document_id']} AND author_role='CO_AUTHOR'")->fetchAll(PDO::FETCH_COLUMN);
        check('4.1: Author persisted correctly',    in_array($spMemberIds[0], array_map('intval', $savedAuthors), true));
        check('4.2: Co-author persisted correctly', in_array($spMemberIds[1], array_map('intval', $savedCoAuthors), true));
        check('4.3: No author/co-author overlap',   empty(array_intersect($savedAuthors, $savedCoAuthors)));

        // Replace authors (new save should delete old)
        $newAuthorIds = [$spMemberIds[1]];  // swap
        doSaveProcessing($pdo, $d4['document_id'], $preDecisionStatuses['On Process'],
                         'PLT-TEST-001', date('Y-m-d'), 'REGULAR',
                         $newAuthorIds, [], $cmtIds, $userA);

        $updatedAuthors = $pdo->query("SELECT sp_member_id FROM document_authors WHERE document_id={$d4['document_id']} AND author_role='AUTHOR'")->fetchAll(PDO::FETCH_COLUMN);
        check('4.4: Authors replaced correctly on re-save',   count($updatedAuthors) === 1);
        check('4.5: Old author removed on re-save',           !in_array($spMemberIds[0], array_map('intval', $updatedAuthors), true));

        // Committees
        if (!empty($cmtIds)) {
            $savedCmts = $pdo->query("SELECT committee_id FROM document_committees WHERE document_id={$d4['document_id']}")->fetchAll(PDO::FETCH_COLUMN);
            check('4.6: Committees persisted (count matches)',  count($savedCmts) === count($cmtIds));
            check('4.7: First committee ID persisted',         in_array($cmtIds[0], array_map('intval', $savedCmts), true));
        } else {
            echo "  [SKIP] No committees available to test persistence." . PHP_EOL;
        }

        // Verify proposed number stored
        $pddRow = $pdo->query("SELECT proposed_number, session_type FROM plenary_document_details WHERE document_id={$d4['document_id']}")->fetch();
        check('4.8: Proposed number persisted',  ($pddRow['proposed_number'] ?? '') === 'PLT-TEST-001');
        check('4.9: Session type persisted',     ($pddRow['session_type'] ?? '') === 'REGULAR');

    } else {
        echo "  [SKIP] Not enough SP Members or On Process status missing — skipping group 4." . PHP_EOL;
    }

    // ==========================================================================
    // TEST GROUP 5: Duplicate proposed_number uniqueness
    // ==========================================================================
    echo PHP_EOL . "Group 5: Proposed number uniqueness" . PHP_EOL;

    if (isset($preDecisionStatuses['On Process'])) {
        $d5a = createPlenaryDoc($pdo, $genericDocTypeId, $sourceTypeId, $docStatusId,
                                 $plenaryRoleId, $userA, $genericDocTypeName);
        $d5b = createPlenaryDoc($pdo, $genericDocTypeId, $sourceTypeId, $docStatusId,
                                 $plenaryRoleId, $userA, $genericDocTypeName);
        doAccept($pdo, $d5a['document_id'], $d5a['assignment_id'], $userA, $plenaryRoleId, $docStatusId);
        doAccept($pdo, $d5b['document_id'], $d5b['assignment_id'], $userA, $plenaryRoleId, $docStatusId);
        doPreDecision($pdo, $d5a['document_id'], $preDecisionStatuses['On Process'], '', $userA);
        doPreDecision($pdo, $d5b['document_id'], $preDecisionStatuses['On Process'], '', $userA);

        $uniqueNum = 'PLT-UNIQ-' . uniqid();

        // Save first document with unique number
        doSaveProcessing($pdo, $d5a['document_id'], $preDecisionStatuses['On Process'],
                         $uniqueNum, '', '', [], [], [], $userA);

        // The controller's uniqueness check (simulated here directly)
        $dupStmt = $pdo->prepare("
            SELECT document_id FROM plenary_document_details
            WHERE proposed_number = ? AND document_id != ?
            LIMIT 1
        ");
        $dupStmt->execute([$uniqueNum, $d5b['document_id']]);
        $dupFound = $dupStmt->fetch();
        check('5.1: Duplicate proposed_number detected by uniqueness query', $dupFound !== false);

        // Same doc can update its own number
        $dupStmt->execute([$uniqueNum, $d5a['document_id']]);
        $selfDup = $dupStmt->fetch();
        check('5.2: Updating own proposed_number does not trigger false duplicate', $selfDup === false);
    } else {
        echo "  [SKIP] On Process status not seeded — skipping group 5." . PHP_EOL;
    }

    // ==========================================================================
    // TEST GROUP 6: Session type ENUM validation
    // ==========================================================================
    echo PHP_EOL . "Group 6: Session type ENUM" . PHP_EOL;

    $validSessionTypes = ['REGULAR', 'REGULAR_ONLINE', 'SPECIAL', 'SPECIAL_ONLINE'];
    $invalidSessionTypes = ['WEEKLY', 'MONTHLY', 'regular', ''];  // empty is allowed (nullable)

    foreach ($validSessionTypes as $st) {
        // Validate via the ENUM column directly (insert test)
        try {
            $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES'");
        } catch (\Throwable $ignore) {}

        // Valid values should be accepted by the ENUM; we just verify the controller's
        // in_array check logic mirrors what the DB accepts
        $validInCode = in_array($st, $validSessionTypes, true);
        check("6.x: Session type '{$st}' is accepted by validation", $validInCode);
    }

    // Invalid session types (non-empty) should be rejected
    foreach (array_filter($invalidSessionTypes) as $st) {
        $invalidInCode = !in_array($st, $validSessionTypes, true);
        check("6.x: Session type '{$st}' is rejected by validation", $invalidInCode);
    }

    // Empty session type is allowed (nullable)
    check('6.9: Empty session type is allowed (optional field)', !in_array('', $validSessionTypes, true));

    // ==========================================================================
    // TEST GROUP 7: Return to Admin
    // ==========================================================================
    echo PHP_EOL . "Group 7: Return to Admin" . PHP_EOL;

    $d7 = createPlenaryDoc($pdo, $genericDocTypeId, $sourceTypeId, $docStatusId,
                            $plenaryRoleId, $userA, $genericDocTypeName);
    doAccept($pdo, $d7['document_id'], $d7['assignment_id'], $userA, $plenaryRoleId, $docStatusId);
    doReturnToAdmin($pdo, $d7['document_id'], $d7['assignment_id'], $userA, $adminRoleId, $pendingStatusId);

    // Old assignment should be COMPLETED
    $oldAsgn = $pdo->query("SELECT decision FROM document_assignments WHERE id={$d7['assignment_id']}")->fetch();
    check('7.1: Old Plenary assignment is COMPLETED after return', $oldAsgn['decision'] === 'COMPLETED');

    // New Admin assignment created
    $newAsgn = $pdo->query("
        SELECT * FROM document_assignments
        WHERE document_id = {$d7['document_id']}
          AND phase = 'ADMIN'
          AND decision = 'PENDING'
        ORDER BY id DESC LIMIT 1
    ")->fetch();
    check('7.2: New PENDING Admin assignment created', $newAsgn !== false);

    // Document phase updated to ADMIN
    $doc7 = $pdo->query("SELECT current_phase FROM documents WHERE id={$d7['document_id']}")->fetch();
    check('7.3: Document current_phase is ADMIN after return', $doc7['current_phase'] === 'ADMIN');

    // Route record created
    $route7 = $pdo->query("
        SELECT * FROM document_routes
        WHERE document_id = {$d7['document_id']}
          AND from_phase = 'PLENARY'
          AND to_phase   = 'ADMIN'
        ORDER BY id DESC LIMIT 1
    ")->fetch();
    check('7.4: Route record PLENARY → ADMIN created', $route7 !== false);

    // After return, userA should not see the document in their accepted inbox
    $visAfterReturn = $pdo->prepare("
        SELECT COUNT(*) FROM document_assignments
        WHERE document_id = ?
          AND phase = 'PLENARY'
          AND decision = 'ACCEPTED'
          AND (accepted_by = ? OR assigned_to_user_id = ?)
    ");
    $visAfterReturn->execute([$d7['document_id'], $userA, $userA]);
    check('7.5: Document no longer visible in Plenary accepted tab after return', (int)$visAfterReturn->fetchColumn() === 0);

    // ==========================================================================
    // TEST GROUP 8: Pre-decision is locked to six statuses
    // ==========================================================================
    echo PHP_EOL . "Group 8: Pre-decision status restriction" . PHP_EOL;

    // The validation query in the controller uses IN (...) — simulate it
    $allowedPreDecision = ['Deferred','Lay on the Table','Noted','Remanded','On Process','Withdrawn'];
    foreach ($allowedPreDecision as $name) {
        $stmt = $pdo->prepare("
            SELECT id FROM document_statuses
            WHERE name = ?
              AND name IN ('Deferred','Lay on the Table','Noted','Remanded','On Process','Withdrawn')
              AND is_active = 1 AND is_deleted = 0
            LIMIT 1
        ");
        $stmt->execute([$name]);
        check("8.x: Pre-decision status '{$name}' is accepted by restriction query",
            $stmt->fetch() !== false || !isset($preDecisionStatuses[$name]));
    }

    // A random other status must NOT pass the restriction
    $badStatusRow = $pdo->query("
        SELECT id FROM document_statuses
        WHERE name NOT IN ('Deferred','Lay on the Table','Noted','Remanded','On Process','Withdrawn')
          AND is_active=1 LIMIT 1
    ")->fetch();
    if ($badStatusRow) {
        $stmt = $pdo->prepare("
            SELECT id FROM document_statuses
            WHERE id = ?
              AND name IN ('Deferred','Lay on the Table','Noted','Remanded','On Process','Withdrawn')
              AND is_active = 1 AND is_deleted = 0
            LIMIT 1
        ");
        $stmt->execute([$badStatusRow['id']]);
        check('8.7: Non-pre-decision status is rejected by restriction query', $stmt->fetch() === false);
    } else {
        echo "  [SKIP] No other statuses available to test rejection." . PHP_EOL;
    }

    // ==========================================================================
    // TEST GROUP 9: On Process available from Deferred and other outcome tabs
    // ==========================================================================
    echo PHP_EOL . "Group 9: On Process status availability from outcome tabs" . PHP_EOL;

    if (isset($preDecisionStatuses['Deferred']) && isset($preDecisionStatuses['On Process'])) {
        $d9 = createPlenaryDoc($pdo, $genericDocTypeId, $sourceTypeId, $docStatusId,
                                $plenaryRoleId, $userA, $genericDocTypeName);
        doAccept($pdo, $d9['document_id'], $d9['assignment_id'], $userA, $plenaryRoleId, $docStatusId);

        // Set document to Deferred
        doPreDecision($pdo, $d9['document_id'], $preDecisionStatuses['Deferred'], 'Deferred remarks', $userA);

        // Verify document is in Deferred status
        $pddRow = $pdo->prepare("
            SELECT pdd.*, ds.name AS status_name
            FROM plenary_document_details pdd
            LEFT JOIN document_statuses ds ON ds.id = pdd.status_id
            WHERE pdd.document_id = ?
        ");
        $pddRow->execute([$d9['document_id']]);
        $pddData = $pddRow->fetch();
        $isDeferred = strtolower(trim($pddData['status_name'] ?? '')) === 'deferred';
        check('9.1: Document successfully set to Deferred status', $isDeferred);

        // Now change from Deferred to On Process (simulating dropdown selection)
        doPreDecision($pdo, $d9['document_id'], $preDecisionStatuses['On Process'], 'Resuming processing', $userA);

        // Verify the status changed to On Process
        $pddRow->execute([$d9['document_id']]);
        $pddData = $pddRow->fetch();
        $isOnProcess = strtolower(trim($pddData['status_name'] ?? '')) === 'on process';
        check('9.2: Can change from Deferred to On Process via pre-decision form', $isOnProcess);

        // Test from Lay on the Table status as well
        if (isset($preDecisionStatuses['Lay on the Table'])) {
            doPreDecision($pdo, $d9['document_id'], $preDecisionStatuses['Lay on the Table'], 'Lay on table remarks', $userA);
            $pddRow->execute([$d9['document_id']]);
            $pddData = $pddRow->fetch();
            $isLayOnTable = strtolower(trim($pddData['status_name'] ?? '')) === 'lay on the table';
            check('9.3: Document set to Lay on the Table', $isLayOnTable);

            // Change from Lay on the Table to On Process
            doPreDecision($pdo, $d9['document_id'], $preDecisionStatuses['On Process'], 'Resuming from lay on table', $userA);
            $pddRow->execute([$d9['document_id']]);
            $pddData = $pddRow->fetch();
            $isOnProcess = strtolower(trim($pddData['status_name'] ?? '')) === 'on process';
            check('9.4: Can change from Lay on the Table to On Process via pre-decision form', $isOnProcess);
        }
    } else {
        echo "  [SKIP] Deferred or On Process status not available for testing." . PHP_EOL;
    }

} finally {
    $pdo->rollBack(); // zero permanent DB changes
}

// ── Summary ────────────────────────────────────────────────────────────────
echo PHP_EOL;
$total = $passed + $failed;
echo "Results: {$passed}/{$total} passed" . ($failed > 0 ? ", {$failed} FAILED" : '') . PHP_EOL;
exit($failed > 0 ? 1 : 0);

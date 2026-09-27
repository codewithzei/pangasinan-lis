<?php
/**
 * Regression test: Committee Inbox — Accepted tab filtering
 *
 * Verifies the NOT EXISTS predicate logic added to CommitteeInboxController::index()
 * covers all 8 required scenarios without touching production data.
 *
 * Strategy
 * --------
 * 1. Wrap everything in a transaction.
 * 2. Insert the minimum rows needed (role, user, document_types, documents,
 *    document_assignments, and optionally committee_cases / committee_communications).
 * 3. Run the exact WHERE / CASE predicates extracted from the controller.
 * 4. Assert expected inclusion / exclusion.
 * 5. Roll back — zero permanent side-effects.
 *
 * Run with:  php tests/committee_inbox_accepted_regression.php
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

// ── Predicates (exact copy of what the controller generates) ──────────────

/**
 * The WHERE fragment used by both the list query and the count query.
 * Named params: :uid1, :uid2 (both = current user id).
 */
function accepted_predicate(): string
{
    return "
        da.decision     = 'ACCEPTED'
        AND da.completed_at IS NULL
        AND (da.accepted_by = :uid1 OR da.assigned_to_user_id = :uid2)
        AND (
            (SELECT dt_inner.name FROM document_types dt_inner
             WHERE dt_inner.id = d.document_type_id LIMIT 1)
                NOT IN ('Complaint', 'Administrative Cases', 'Communication')
            OR (
                (SELECT dt_inner.name FROM document_types dt_inner
                 WHERE dt_inner.id = d.document_type_id LIMIT 1)
                    IN ('Complaint', 'Administrative Cases')
                AND NOT EXISTS (
                    SELECT 1 FROM committee_cases cc
                    WHERE cc.document_id = da.document_id
                )
            )
            OR (
                (SELECT dt_inner.name FROM document_types dt_inner
                 WHERE dt_inner.id = d.document_type_id LIMIT 1)
                    = 'Communication'
                AND NOT EXISTS (
                    SELECT 1 FROM committee_communications ccomm
                    WHERE ccomm.document_id = da.document_id
                )
            )
        )
    ";
}

/**
 * The accepted_count CASE predicate used in the stats query.
 * Named params: :uid1, :uid2 (both = current user id).
 */
function accepted_stats_predicate(): string
{
    return "
        da.decision     = 'ACCEPTED'
        AND da.completed_at IS NULL
        AND (da.accepted_by = :uid1 OR da.assigned_to_user_id = :uid2)
        AND (
            (SELECT dt_s.name FROM document_types dt_s
             WHERE dt_s.id = d.document_type_id LIMIT 1)
                NOT IN ('Complaint', 'Administrative Cases', 'Communication')
            OR (
                (SELECT dt_s.name FROM document_types dt_s
                 WHERE dt_s.id = d.document_type_id LIMIT 1)
                    IN ('Complaint', 'Administrative Cases')
                AND NOT EXISTS (
                    SELECT 1 FROM committee_cases cc
                    WHERE cc.document_id = da.document_id
                )
            )
            OR (
                (SELECT dt_s.name FROM document_types dt_s
                 WHERE dt_s.id = d.document_type_id LIMIT 1)
                    = 'Communication'
                AND NOT EXISTS (
                    SELECT 1 FROM committee_communications ccomm
                    WHERE ccomm.document_id = da.document_id
                )
            )
        )
    ";
}

/**
 * Returns true when the given assignment_id is visible in the Accepted tab
 * for $userId (list / count query form).
 */
function is_visible_in_accepted(PDO $pdo, int $assignmentId, int $userId): bool
{
    $pred = accepted_predicate();
    $stmt = $pdo->prepare("
        SELECT 1
        FROM document_assignments da
        INNER JOIN documents d ON da.document_id = d.id
        WHERE da.id = :aid
          AND {$pred}
        LIMIT 1
    ");
    $stmt->execute([':aid' => $assignmentId, ':uid1' => $userId, ':uid2' => $userId]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Returns the accepted_count from the stats query for the given user / role.
 * Only counts assignments inserted by this test (owned by $userId).
 */
function accepted_stats_count(PDO $pdo, int $userId, int $roleId): int
{
    $pred = accepted_stats_predicate();
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT CASE WHEN {$pred} THEN da.document_id END) AS cnt
        FROM document_assignments da
        INNER JOIN documents d ON da.document_id = d.id
        WHERE da.assigned_to_role_id = :rid
          AND da.phase               = 'COMMITTEE'
          AND da.accepted_by         = :uid_filter
    ");
    $stmt->execute([
        ':rid'        => $roleId,
        ':uid_filter' => $userId,
        ':uid1'       => $userId,
        ':uid2'       => $userId,
    ]);
    return (int) $stmt->fetchColumn();
}

// ── Begin transaction (everything is rolled back at the end) ──────────────
$pdo->beginTransaction();

try {
    // ── Resolve / create the Committee role ───────────────────────────────
    $roleRow = $pdo->query(
        "SELECT id FROM roles WHERE name = 'Committee' AND is_active = 1 AND is_deleted = 0 LIMIT 1"
    )->fetch();

    if (!$roleRow) {
        $pdo->exec("INSERT INTO roles (name, is_active, is_deleted) VALUES ('Committee', 1, 0)");
        $committeeRoleId = (int) $pdo->lastInsertId();
    } else {
        $committeeRoleId = (int) $roleRow['id'];
    }

    // ── Insert two synthetic test users ───────────────────────────────────
    // user_accounts schema: id, username, email, password_hash, role_id, status, ...
    $pdo->prepare(
        "INSERT INTO user_accounts (username, email, password_hash, role_id, status, is_deleted)
         VALUES ('_reg_test_user', '_reg_test_user@test.local', 'x', ?, 'active', 0)"
    )->execute([$committeeRoleId]);
    $testUserId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO user_accounts (username, email, password_hash, role_id, status, is_deleted)
         VALUES ('_reg_test_other', '_reg_test_other@test.local', 'x', ?, 'active', 0)"
    )->execute([$committeeRoleId]);
    $otherUserId = (int) $pdo->lastInsertId();

    // ── Resolve (or create) required document types ───────────────────────
    $resolveType = function (string $name) use ($pdo): int {
        $stmt = $pdo->prepare("SELECT id FROM document_types WHERE name = ? LIMIT 1");
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        if ($row) return (int) $row['id'];
        $pdo->prepare("INSERT INTO document_types (name, badge_color) VALUES (?, '#6c757d')")
            ->execute([$name]);
        return (int) $pdo->lastInsertId();
    };

    $typeComplaint = $resolveType('Complaint');
    $typeAdminCase = $resolveType('Administrative Cases');
    $typeComm      = $resolveType('Communication');
    $typeOther     = $resolveType('Resolution'); // any non-special type

    // ── Resolve a source_type_id for document inserts ─────────────────────
    $sourceTypeId = (int) $pdo->query("SELECT id FROM source_types LIMIT 1")->fetchColumn();
    if (!$sourceTypeId) {
        $pdo->exec("INSERT INTO source_types (name) VALUES ('_test_source')");
        $sourceTypeId = (int) $pdo->lastInsertId();
    }

    // ── Resolve a current_status_id for document inserts ──────────────────
    $statusId = (int) $pdo->query("SELECT id FROM document_statuses LIMIT 1")->fetchColumn();
    if (!$statusId) {
        $pdo->exec("INSERT INTO document_statuses (name, badge_color) VALUES ('_test_status', '#6c757d')");
        $statusId = (int) $pdo->lastInsertId();
    }

    // ── Helper: insert a minimal document ─────────────────────────────────
    // documents schema requires: tracking_number (varchar 20), date_received, time_received,
    // subject_matter, document_type_id, current_phase, created_by, updated_by
    $docCounter = 0;
    // Use a sequence base far above any real data to avoid unique_tracking_year_sequence collisions.
    $seqBase = 900000;
    $insertDoc = function (int $typeId) use ($pdo, $testUserId, $sourceTypeId, $statusId, $seqBase, &$docCounter): int {
        $docCounter++;
        $seq         = $seqBase + $docCounter;
        $trackingNum = 'RT-' . str_pad((string) $docCounter, 4, '0', STR_PAD_LEFT);
        $pdo->prepare(
            "INSERT INTO documents
                (tracking_number, tracking_year, tracking_sequence,
                 subject_matter, document_type_id, source_type_id,
                 current_status_id,
                 date_received, time_received,
                 current_phase, created_by, updated_by)
             VALUES (?, YEAR(CURDATE()), ?, 'Regression test subject', ?,
                     ?, ?, CURDATE(), CURTIME(), 'COMMITTEE', ?, ?)"
        )->execute([$trackingNum, $seq, $typeId, $sourceTypeId, $statusId, $testUserId, $testUserId]);
        return (int) $pdo->lastInsertId();
    };

    // ── Helper: insert an ACCEPTED committee assignment ───────────────────
    // document_assignments schema: decision, completed_at, accepted_by, assigned_to_user_id, ...
    $insertAccepted = function (
        int  $docId,
        int  $acceptedBy,
        bool $completed = false
    ) use ($pdo, $committeeRoleId, $testUserId): int {
        $completedVal = $completed ? date('Y-m-d H:i:s') : null;
        $pdo->prepare(
            "INSERT INTO document_assignments
                (document_id, assigned_to_role_id, phase, assigned_by,
                 decision, received_at, accepted_by, assigned_to_user_id, completed_at)
             VALUES (?, ?, 'COMMITTEE', ?, 'ACCEPTED', NOW(), ?, ?, ?)"
        )->execute([$docId, $committeeRoleId, $testUserId, $acceptedBy, $acceptedBy, $completedVal]);
        return (int) $pdo->lastInsertId();
    };

    // ── Helper: insert a committee_cases row ──────────────────────────────
    // Required NOT NULL: docket_year, docket_sequence, docket_number,
    //                    date_assigned, nature_of_case, complainant_details, respondents
    // Use high docket_sequence base to avoid uq_year_sequence collisions.
    $caseCounter   = 0;
    $caseSeqBase   = 900000;
    $insertCase = function (int $docId) use ($pdo, $testUserId, $caseSeqBase, &$caseCounter): void {
        $caseCounter++;
        $seq = $caseSeqBase + $caseCounter;
        $pdo->prepare(
            "INSERT INTO committee_cases
                (document_id, docket_year, docket_sequence, docket_number,
                 date_assigned, nature_of_case, complainant_details, respondents,
                 assigned_by)
             VALUES (?, YEAR(CURDATE()), ?, ?, CURDATE(),
                     'Test nature', 'Test complainant', 'Test respondent', ?)"
        )->execute([
            $docId,
            $seq,
            'DC-' . str_pad((string) $caseCounter, 4, '0', STR_PAD_LEFT),
            $testUserId,
        ]);
    };

    // ── Helper: insert a committee_communications row ─────────────────────
    // Required NOT NULL: date_logged, subject, sender_details
    $insertComm = function (int $docId) use ($pdo, $testUserId): void {
        $pdo->prepare(
            "INSERT INTO committee_communications
                (document_id, date_logged, subject, sender_details, assigned_by)
             VALUES (?, CURDATE(), 'Regression test comm', 'Test sender', ?)"
        )->execute([$docId, $testUserId]);
    };

    // ══════════════════════════════════════════════════════════════════════
    // SCENARIO 1 — Accepted unprocessed Complaint  →  MUST appear
    // ══════════════════════════════════════════════════════════════════════
    $docId1 = $insertDoc($typeComplaint);
    $asn1   = $insertAccepted($docId1, $testUserId);
    assert_test(
        'SC1: Accepted unprocessed Complaint appears in Accepted tab',
        is_visible_in_accepted($pdo, $asn1, $testUserId)
    );

    // ══════════════════════════════════════════════════════════════════════
    // SCENARIO 2 — Complaint with committee_cases record  →  MUST NOT appear
    // ══════════════════════════════════════════════════════════════════════
    $docId2 = $insertDoc($typeComplaint);
    $asn2   = $insertAccepted($docId2, $testUserId);
    $insertCase($docId2);
    assert_test(
        'SC2: Complaint with committee_cases record is excluded from Accepted tab',
        !is_visible_in_accepted($pdo, $asn2, $testUserId)
    );

    // ══════════════════════════════════════════════════════════════════════
    // SCENARIO 3 — Accepted unprocessed Administrative Cases  →  MUST appear
    // ══════════════════════════════════════════════════════════════════════
    $docId3 = $insertDoc($typeAdminCase);
    $asn3   = $insertAccepted($docId3, $testUserId);
    assert_test(
        'SC3: Accepted unprocessed Administrative Cases appears in Accepted tab',
        is_visible_in_accepted($pdo, $asn3, $testUserId)
    );

    // ══════════════════════════════════════════════════════════════════════
    // SCENARIO 4 — Administrative Cases with committee_cases record  →  MUST NOT appear
    // ══════════════════════════════════════════════════════════════════════
    $docId4 = $insertDoc($typeAdminCase);
    $asn4   = $insertAccepted($docId4, $testUserId);
    $insertCase($docId4);
    assert_test(
        'SC4: Administrative Cases with committee_cases record is excluded from Accepted tab',
        !is_visible_in_accepted($pdo, $asn4, $testUserId)
    );

    // ══════════════════════════════════════════════════════════════════════
    // SCENARIO 5 — Accepted unprocessed Communication  →  MUST appear
    // ══════════════════════════════════════════════════════════════════════
    $docId5 = $insertDoc($typeComm);
    $asn5   = $insertAccepted($docId5, $testUserId);
    assert_test(
        'SC5: Accepted unprocessed Communication appears in Accepted tab',
        is_visible_in_accepted($pdo, $asn5, $testUserId)
    );

    // ══════════════════════════════════════════════════════════════════════
    // SCENARIO 6 — Communication with committee_communications record  →  MUST NOT appear
    // ══════════════════════════════════════════════════════════════════════
    $docId6 = $insertDoc($typeComm);
    $asn6   = $insertAccepted($docId6, $testUserId);
    $insertComm($docId6);
    assert_test(
        'SC6: Communication with committee_communications record is excluded from Accepted tab',
        !is_visible_in_accepted($pdo, $asn6, $testUserId)
    );

    // ══════════════════════════════════════════════════════════════════════
    // SCENARIO 7 — Completed/endorsed assignment (completed_at IS NOT NULL)
    //              →  MUST NOT appear
    // ══════════════════════════════════════════════════════════════════════
    $docId7 = $insertDoc($typeOther);
    $asn7   = $insertAccepted($docId7, $testUserId, completed: true);
    assert_test(
        'SC7: Completed/endorsed assignment (completed_at IS NOT NULL) is excluded from Accepted tab',
        !is_visible_in_accepted($pdo, $asn7, $testUserId)
    );

    // ══════════════════════════════════════════════════════════════════════
    // SCENARIO 8 — Another user's accepted document  →  MUST NOT appear
    //              for testUser (ownership restriction)
    // ══════════════════════════════════════════════════════════════════════
    $docId8 = $insertDoc($typeOther);
    $asn8   = $insertAccepted($docId8, $otherUserId); // accepted by OTHER user
    assert_test(
        "SC8: Another user's accepted document is excluded (ownership restriction)",
        !is_visible_in_accepted($pdo, $asn8, $testUserId)
    );

    // Sanity: testUser's own Other-type document IS visible
    $docId8b = $insertDoc($typeOther);
    $asn8b   = $insertAccepted($docId8b, $testUserId);
    assert_test(
        'SC8b: Own accepted non-special-type document appears (ownership sanity)',
        is_visible_in_accepted($pdo, $asn8b, $testUserId)
    );

    // ══════════════════════════════════════════════════════════════════════
    // STATS QUERY PARITY — accepted_count from stats must equal list count
    // ══════════════════════════════════════════════════════════════════════
    // Expected visible for testUserId: SC1, SC3, SC5, SC8b = 4 documents
    $expectedVisible = 4;

    // Ground-truth via list predicate
    $pred     = accepted_predicate();
    $listStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT da.document_id) AS total
        FROM document_assignments da
        INNER JOIN documents d ON da.document_id = d.id
        WHERE da.assigned_to_role_id = :rid
          AND da.phase               = 'COMMITTEE'
          AND da.accepted_by         = :uid_filter
          AND {$pred}
    ");
    $listStmt->execute([
        ':rid'        => $committeeRoleId,
        ':uid_filter' => $testUserId,
        ':uid1'       => $testUserId,
        ':uid2'       => $testUserId,
    ]);
    $listCount = (int) $listStmt->fetchColumn();

    $statsCount = accepted_stats_count($pdo, $testUserId, $committeeRoleId);

    assert_test(
        "STATS: list query visible count ({$listCount}) equals expected ({$expectedVisible})",
        $listCount === $expectedVisible
    );

    assert_test(
        "STATS: stats accepted_count ({$statsCount}) matches list count ({$listCount})",
        $statsCount === $listCount
    );

} finally {
    // Always roll back — zero permanent changes to the database
    $pdo->rollBack();
}

// ── Summary ────────────────────────────────────────────────────────────────
echo PHP_EOL;
$total = $passed + $failed;
echo "Results: {$passed}/{$total} passed" . ($failed > 0 ? ", {$failed} FAILED" : '') . PHP_EOL;
exit($failed > 0 ? 1 : 0);

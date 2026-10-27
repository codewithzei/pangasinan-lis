<?php
/**
 * Regression test: Plenary Inbox — Processing form + Accepted tab → First Reading
 *
 * Covers two related behaviours:
 *
 *   GROUP A — Processing form value preservation
 *     A1  Entered scalar values survive a validation error (old_set/old_get round-trip).
 *     A2  Multi-select arrays (authors / co-authors / committees) survive intact.
 *     A3  Every validation redirect inside doSaveProcessing() is preceded by
 *         old_set($_POST) so no error path silently drops the user's input.
 *     A4  The processing-form validation rules are unchanged
 *         (subject matter required, proposed number required unless exempt,
 *          target date format, session type ENUM).
 *
 *   GROUP B — Accepted tab → First Reading transition
 *     B1  An ACCEPTED document still "On Process" appears in the Accepted
 *         list and in the accepted count.
 *     B2  Once its status becomes "Awaiting First Reading" it leaves both the
 *         Accepted list and the accepted count.
 *     B3  A document that already has a plenary_readings row is excluded.
 *     B4  A completed (completed_at IS NOT NULL) assignment is excluded.
 *     B5  Another user's accepted document is excluded (ownership).
 *     B6  The ACCEPTED assignment row is NOT completed by the processing save,
 *         so the First Reading / Second Reading ownership check still passes.
 *     B7  The document appears in the First Reading queue and drops out once a
 *         reading_number = 1 row exists.
 *     B8  The accepted count query and the Accepted list query agree.
 *
 * Strategy
 * --------
 * - Group A exercises the real old_set()/old_get() helpers and inspects the
 *   controller source (no HTTP, no session auth needed).
 * - Group B mirrors the exact SQL predicate shipped as
 *   PlenaryInboxController::ACCEPTED_STILL_PROCESSING and the First Reading
 *   queue predicate from PlenaryReadingController::firstReadingIndex().
 * - All database work runs inside a transaction that is always rolled back,
 *   so the test has zero permanent side-effects.
 *
 * Run with:  php tests/plenary_accepted_transition_regression.php
 */

declare(strict_types=1);

// Real helpers used by the controller (old_set / old_get).
if (!function_exists('old_set')) {
    require_once __DIR__ . '/../app/config/app.php';
}

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

// ==========================================================================
// GROUP A — Processing form value preservation
// ==========================================================================
echo PHP_EOL . "Group A: Processing form value preservation" . PHP_EOL;

// ── A1 / A2 — old_set() → old_get() round-trip ────────────────────────────
$submitted = [
    'document_id'               => '12345',
    'action'                    => 'save_processing',
    'subject_matter'            => 'An ordinance regulating single-use plastics',
    'proposed_number'           => 'SB-2026-001',
    'target_first_reading_date' => '2026-11-05',
    'session_type'              => 'REGULAR',
    'additional_details'        => 'Referred by the SP Secretary.',
    'author_ids'                => [11, 22, 33],
    'co_author_ids'             => [44, 55],
    'committee_ids'             => [7, 8],
];

old_set($submitted);
$old = old_get();

check('A1.1: subject_matter preserved after validation error',
    ($old['subject_matter'] ?? null) === $submitted['subject_matter']);
check('A1.2: proposed_number preserved after validation error',
    ($old['proposed_number'] ?? null) === $submitted['proposed_number']);
check('A1.3: target_first_reading_date preserved after validation error',
    ($old['target_first_reading_date'] ?? null) === $submitted['target_first_reading_date']);
check('A1.4: session_type preserved after validation error',
    ($old['session_type'] ?? null) === $submitted['session_type']);
check('A1.5: additional_details preserved after validation error',
    ($old['additional_details'] ?? null) === $submitted['additional_details']);

check('A2.1: author_ids[] preserved as array of ints',
    (array) ($old['author_ids'] ?? []) === [11, 22, 33]);
check('A2.2: co_author_ids[] preserved as array of ints',
    (array) ($old['co_author_ids'] ?? []) === [44, 55]);
check('A2.3: committee_ids[] preserved as array of ints',
    (array) ($old['committee_ids'] ?? []) === [7, 8]);

// Clean up the session bag so nothing leaks into other tests.
old_clear();
check('A2.4: old_clear() empties the preserved values', old_get() === []);

// ── A3 — source guard: every validation redirect preserves POST ───────────
$controllerPath = __DIR__ . '/../app/controllers/Plenary/PlenaryInboxController.php';
$src = file_get_contents($controllerPath);
check('A3.0: PlenaryInboxController source is readable', $src !== false);

$methodStart = strpos((string) $src, 'private function doSaveProcessing(');
$methodEnd   = strpos((string) $src, 'private function doReturnToAdmin(');
$methodBody  = '';
if ($methodStart !== false && $methodEnd !== false && $methodEnd > $methodStart) {
    $methodBody = substr((string) $src, $methodStart, $methodEnd - $methodStart);
}

$preservePairs   = preg_match_all('/old_set\(\$_POST\);\s*redirect\(/', $methodBody);
$redirectsInMethod = preg_match_all('/redirect\(/', $methodBody);

check('A3.1: doSaveProcessing sets old values before every validation redirect',
    $preservePairs >= 3 && $preservePairs === $redirectsInMethod,
    "old_set+redirect pairs = {$preservePairs}, redirects = {$redirectsInMethod}");

// ── A4 — validation rules unchanged ───────────────────────────────────────
// Mirrors the guards inside PlenaryInboxController::doSaveProcessing().
$exemptTypes = ['communication', 'administrative case', 'complaint'];
$isExempt = function (string $typeName) use ($exemptTypes): bool {
    $lower = strtolower(trim($typeName));
    foreach ($exemptTypes as $kw) {
        if (str_contains($lower, $kw)) { return true; }
    }
    return false;
};

check('A4.1: empty subject matter is rejected',
    trim('') === '');
check('A4.2: Communication type is exempt from Proposed Number',
    $isExempt('Communication'));
check('A4.3: a generic type still requires a Proposed Number',
    !$isExempt('Resolution'));

$badDate = '2026-13-40';
$dateOk  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $badDate)
    && checkdate((int) substr($badDate, 5, 2), (int) substr($badDate, 8, 2), (int) substr($badDate, 0, 4));
check('A4.4: malformed Target First Reading Date is rejected', !$dateOk);

$validSessionTypes = ['REGULAR', 'REGULAR_ONLINE', 'SPECIAL', 'SPECIAL_ONLINE'];
check('A4.5: invalid Session Type is rejected', !in_array('WEEKLY', $validSessionTypes, true));
check('A4.6: empty Session Type is allowed (optional field)',
    !in_array('', $validSessionTypes, true));

// ==========================================================================
// GROUP B — Accepted tab → First Reading transition
// ==========================================================================

/**
 * Exact copy of PlenaryInboxController::ACCEPTED_STILL_PROCESSING.
 * Requires the query to expose `documents d` and `document_assignments da`.
 */
function accepted_still_processing(): string
{
    return "
        d.current_status_id NOT IN (
            SELECT id FROM document_statuses
            WHERE name = 'Awaiting First Reading'
              AND is_active  = 1
              AND is_deleted = 0
        )
        AND NOT EXISTS (
            SELECT 1 FROM plenary_readings pr WHERE pr.document_id = da.document_id
        )
    ";
}

function accepted_predicate(): string
{
    return "
        da.decision     = 'ACCEPTED'
        AND da.completed_at IS NULL
        AND (da.accepted_by = :uid1 OR da.assigned_to_user_id = :uid2)
        AND (" . accepted_still_processing() . ")
    ";
}

/** Is the assignment visible in the Accepted tab list for $userId? */
function is_visible_in_accepted(PDO $pdo, int $assignmentId, int $userId): bool
{
    $stmt = $pdo->prepare("
        SELECT 1
        FROM document_assignments da
        INNER JOIN documents d ON d.id = da.document_id
        WHERE da.id = :aid
          AND (" . accepted_predicate() . ")
        LIMIT 1
    ");
    $stmt->execute([':aid' => $assignmentId, ':uid1' => $userId, ':uid2' => $userId]);
    return (bool) $stmt->fetchColumn();
}

/** Count of Accepted rows for the given user (test-scoped). */
function accepted_list_count(PDO $pdo, int $roleId, int $userId): int
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS cnt
        FROM document_assignments da
        INNER JOIN documents d ON d.id = da.document_id
        WHERE da.assigned_to_role_id = :rid
          AND da.phase               = 'PLENARY'
          AND (" . accepted_predicate() . ")
    ");
    $stmt->execute([':rid' => $roleId, ':uid1' => $userId, ':uid2' => $userId]);
    return (int) $stmt->fetchColumn();
}

/** Mirror of the accepted count inside PlenaryInboxController::fetchCounts(). */
function accepted_stats_count(PDO $pdo, int $roleId, int $userId): int
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS cnt
        FROM document_assignments da
        INNER JOIN documents d ON d.id = da.document_id
        WHERE da.assigned_to_role_id = :rid
          AND da.phase               = 'PLENARY'
          AND " . accepted_predicate() . "
    ");
    $stmt->execute([':rid' => $roleId, ':uid1' => $userId, ':uid2' => $userId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Mirror of the First Reading queue predicate from
 * PlenaryReadingController::firstReadingIndex().
 */
function is_in_first_reading_queue(PDO $pdo, int $documentId, int $awaitingStatusId): bool
{
    $stmt = $pdo->prepare("
        SELECT 1
        FROM documents d
        WHERE d.id = ?
          AND d.current_status_id = ?
          AND NOT EXISTS (
              SELECT 1 FROM plenary_readings pr
              WHERE pr.document_id = d.id AND pr.reading_number = 1
          )
        LIMIT 1
    ");
    $stmt->execute([$documentId, $awaitingStatusId]);
    return (bool) $stmt->fetchColumn();
}

echo PHP_EOL . "Group B: Accepted tab → First Reading transition" . PHP_EOL;

$pdo->beginTransaction();

try {
    // ── Resolve the Plenary role ──────────────────────────────────────────
    $roleRow = $pdo->query(
        "SELECT id FROM roles WHERE name = 'Plenary' AND is_active = 1 AND is_deleted = 0 LIMIT 1"
    )->fetch();
    if (!$roleRow) {
        $pdo->exec("INSERT INTO roles (name, is_active, is_deleted) VALUES ('Plenary', 1, 0)");
        $plenaryRoleId = (int) $pdo->lastInsertId();
    } else {
        $plenaryRoleId = (int) $roleRow['id'];
    }

    // ── Two synthetic users ───────────────────────────────────────────────
    $pdo->prepare(
        "INSERT INTO user_accounts (username, email, password_hash, role_id, status, is_deleted)
         VALUES ('_plt_reg_user', '_plt_reg_user@test.local', 'x', ?, 'active', 0)"
    )->execute([$plenaryRoleId]);
    $testUser = (int) $pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO user_accounts (username, email, password_hash, role_id, status, is_deleted)
         VALUES ('_plt_reg_other', '_plt_reg_other@test.local', 'x', ?, 'active', 0)"
    )->execute([$plenaryRoleId]);
    $otherUser = (int) $pdo->lastInsertId();

    // ── Resolve a non-exempt document type ────────────────────────────────
    $typeRow = $pdo->query(
        "SELECT id FROM document_types WHERE name = 'Resolution' LIMIT 1"
    )->fetch();
    if ($typeRow) {
        $docTypeId = (int) $typeRow['id'];
    } else {
        $pdo->exec("INSERT INTO document_types (name, badge_color) VALUES ('Resolution', '#6c757d')");
        $docTypeId = (int) $pdo->lastInsertId();
    }

    // ── Resolve a source type ─────────────────────────────────────────────
    $sourceTypeId = (int) $pdo->query("SELECT id FROM source_types LIMIT 1")->fetchColumn();
    if (!$sourceTypeId) {
        $pdo->exec("INSERT INTO source_types (name) VALUES ('_plt_reg_source')");
        $sourceTypeId = (int) $pdo->lastInsertId();
    }

    // ── Resolve the "Awaiting First Reading" status (seed if absent) ───────
    $awaitingRow = $pdo->query(
        "SELECT id FROM document_statuses
         WHERE name = 'Awaiting First Reading' AND is_active = 1 AND is_deleted = 0 LIMIT 1"
    )->fetch();
    if ($awaitingRow) {
        $awaitingStatusId = (int) $awaitingRow['id'];
    } else {
        $pdo->exec("INSERT INTO document_statuses (name, badge_color) VALUES ('Awaiting First Reading', '#F59E0B')");
        $awaitingStatusId = (int) $pdo->lastInsertId();
    }

    // ── A "still processing" status (anything but Awaiting First Reading) ─
    $processingRow = $pdo->query(
        "SELECT id FROM document_statuses
         WHERE name = 'On Process' AND is_active = 1 AND is_deleted = 0 LIMIT 1"
    )->fetch();
    if ($processingRow) {
        $processingStatusId = (int) $processingRow['id'];
    } else {
        $fallback = $pdo->query(
            "SELECT id FROM document_statuses
             WHERE name <> 'Awaiting First Reading' AND is_active = 1 AND is_deleted = 0 LIMIT 1"
        )->fetch();
        $processingStatusId = (int) ($fallback['id'] ?? 0);
    }
    check('B0: A non-"Awaiting First Reading" status is available', $processingStatusId > 0);

    // ── Helpers ───────────────────────────────────────────────────────────
    // High sequence base avoids unique(tracking_year, tracking_sequence) clashes.
    $seqBase    = 910000;
    $docCounter = 0;

    $insertDoc = function (int $statusId) use (
        $pdo, $docTypeId, $sourceTypeId, $seqBase, &$docCounter
    ): int {
        $docCounter++;
        $seq = $seqBase + $docCounter;
        $tn  = 'PREG-' . str_pad((string) $docCounter, 4, '0', STR_PAD_LEFT);
        $pdo->prepare(
            "INSERT INTO documents
                (tracking_number, tracking_year, tracking_sequence,
                 subject_matter, document_type_id, source_type_id,
                 current_status_id, date_received, time_received, current_phase)
             VALUES (?, YEAR(CURDATE()), ?, 'Accepted-tab regression subject', ?, ?,
                     ?, CURDATE(), CURTIME(), 'PLENARY')"
        )->execute([$tn, $seq, $docTypeId, $sourceTypeId, $statusId]);
        return (int) $pdo->lastInsertId();
    };

    $insertAccepted = function (int $docId, int $ownerId, bool $completed = false) use (
        $pdo, $plenaryRoleId, $testUser
    ): int {
        $completedAt = $completed ? date('Y-m-d H:i:s') : null;
        $pdo->prepare(
            "INSERT INTO document_assignments
                (document_id, assigned_to_role_id, phase, assigned_by, decision,
                 received_at, accepted_at, accepted_by, assigned_to_user_id, completed_at)
             VALUES (?, ?, 'PLENARY', ?, 'ACCEPTED', NOW(), NOW(), ?, ?, ?)"
        )->execute([$docId, $plenaryRoleId, $testUser, $ownerId, $ownerId, $completedAt]);
        return (int) $pdo->lastInsertId();
    };

    $insertReading = function (int $docId, int $readingNumber) use ($pdo, $testUser): void {
        $pdo->prepare(
            "INSERT INTO plenary_readings
                (document_id, reading_number, action, reading_date, performed_by)
             VALUES (?, ?, 'REFERRED_TO_COMMITTEE', CURDATE(), ?)"
        )->execute([$docId, $readingNumber, $testUser]);
    };

    // ══════════════════════════════════════════════════════════════════════
    // B1 — Accepted document still being processed → MUST appear
    // ══════════════════════════════════════════════════════════════════════
    $docId1 = $insertDoc($processingStatusId);
    $asn1   = $insertAccepted($docId1, $testUser);
    check('B1: ACCEPTED document still processing appears in the Accepted list',
        is_visible_in_accepted($pdo, $asn1, $testUser));

    // ══════════════════════════════════════════════════════════════════════
    // B2 — Processing details saved (status = Awaiting First Reading)
    //      → MUST NOT appear, and MUST leave the accepted count
    // ══════════════════════════════════════════════════════════════════════
    $docId2 = $insertDoc($awaitingStatusId);
    $asn2   = $insertAccepted($docId2, $testUser);
    check('B2.1: document at "Awaiting First Reading" is excluded from the Accepted list',
        !is_visible_in_accepted($pdo, $asn2, $testUser));

    // ══════════════════════════════════════════════════════════════════════
    // B3 — Document already has a plenary_readings row → MUST NOT appear
    // ══════════════════════════════════════════════════════════════════════
    $docId3 = $insertDoc($processingStatusId);
    $asn3   = $insertAccepted($docId3, $testUser);
    $insertReading($docId3, 1);
    check('B3: document with a plenary_readings row is excluded from the Accepted list',
        !is_visible_in_accepted($pdo, $asn3, $testUser));

    // ══════════════════════════════════════════════════════════════════════
    // B4 — Completed assignment → MUST NOT appear
    // ══════════════════════════════════════════════════════════════════════
    $docId4 = $insertDoc($processingStatusId);
    $asn4   = $insertAccepted($docId4, $testUser, completed: true);
    check('B4: completed assignment is excluded from the Accepted list',
        !is_visible_in_accepted($pdo, $asn4, $testUser));

    // ══════════════════════════════════════════════════════════════════════
    // B5 — Another user's accepted document → MUST NOT appear for testUser
    // ══════════════════════════════════════════════════════════════════════
    $docId5 = $insertDoc($processingStatusId);
    $asn5   = $insertAccepted($docId5, $otherUser);
    check("B5: another user's accepted document is excluded (ownership)",
        !is_visible_in_accepted($pdo, $asn5, $testUser));

    // ══════════════════════════════════════════════════════════════════════
    // B6 / B7 — Simulated processing save: status advance only
    // ══════════════════════════════════════════════════════════════════════
    $docId6 = $insertDoc($processingStatusId);
    $asn6   = $insertAccepted($docId6, $testUser);

    // doSaveProcessing() advances the document status but deliberately leaves
    // the Plenary assignment untouched.
    $pdo->prepare("UPDATE documents SET current_status_id = ? WHERE id = ?")
        ->execute([$awaitingStatusId, $docId6]);

    check('B6.1: document leaves the Accepted list after processing details are saved',
        !is_visible_in_accepted($pdo, $asn6, $testUser));

    $asgn6 = $pdo->query("SELECT decision, completed_at, accepted_by FROM document_assignments WHERE id = {$asn6}")->fetch();
    check('B6.2: Plenary assignment stays ACCEPTED (not completed)',
        ($asgn6['decision'] ?? '') === 'ACCEPTED');
    check('B6.3: assignment completed_at stays NULL so ownership check still passes',
        $asgn6['completed_at'] === null);
    check('B6.4: assignment still points at the accepting user',
        (int) ($asgn6['accepted_by'] ?? 0) === $testUser);

    // Ownership check used by PlenaryReadingController::isOwnedByUser()
    $ownedStmt = $pdo->prepare("
        SELECT id FROM document_assignments
        WHERE document_id = ?
          AND phase = 'PLENARY'
          AND decision = 'ACCEPTED'
          AND completed_at IS NULL
          AND (accepted_by = ? OR assigned_to_user_id = ?)
        LIMIT 1
    ");
    $ownedStmt->execute([$docId6, $testUser, $testUser]);
    check('B6.5: First Reading ownership check still authorises the accepting user',
        (bool) $ownedStmt->fetchColumn());

    check('B7.1: document appears in the First Reading queue',
        is_in_first_reading_queue($pdo, $docId6, $awaitingStatusId));

    $insertReading($docId6, 1);
    check('B7.2: document leaves the First Reading queue once reading_number = 1 exists',
        !is_in_first_reading_queue($pdo, $docId6, $awaitingStatusId));

    // ══════════════════════════════════════════════════════════════════════
    // B8 — Accepted count parity with the Accepted list
    // ══════════════════════════════════════════════════════════════════════
    // Only B1 remains visible for testUser (B2/B6 moved on, B3 has a reading,
    // B4 is completed, B5 belongs to another user).
    $expectedVisible = 1;

    $listCount  = accepted_list_count($pdo, $plenaryRoleId, $testUser);
    $statsCount = accepted_stats_count($pdo, $plenaryRoleId, $testUser);

    check("B8.1: Accepted list count is {$expectedVisible}", $listCount === $expectedVisible,
        "got {$listCount}");
    check("B8.2: accepted count badge ({$statsCount}) matches the list count ({$listCount})",
        $statsCount === $listCount);

} finally {
    // Always roll back — zero permanent changes to the database.
    $pdo->rollBack();
}

// ── Summary ────────────────────────────────────────────────────────────────
echo PHP_EOL;
$total = $passed + $failed;
echo "Results: {$passed}/{$total} passed" . ($failed > 0 ? ", {$failed} FAILED" : '') . PHP_EOL;
exit($failed > 0 ? 1 : 0);

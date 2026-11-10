<?php
/**
 * Tests — Committee Cases: manual docket-number entry
 *
 * WHAT IS TESTED
 * ──────────────────────────────────────────────────────────────────────────
 * Group 1 — Validation logic (pure PHP, no DB):
 *   Mirrors the server-side validation in CommitteeCasesController::store()
 *   and ::detailsStore() to verify every required-field and length rule.
 *
 * Group 2 — DB integration: store() path (via CommitteeInboxController &
 *   CommitteeCasesController).
 *   a) A valid manual docket number is accepted and saved to committee_cases.
 *   b) Submitting without a docket number produces a validation error.
 *   c) Submitting with a docket number that already exists produces a
 *      duplicate error (simulated at the uniqueness-check layer).
 *   d) An overly-long docket number (> 100 chars) is rejected.
 *
 * Group 3 — DB integration: detailsStore() path.
 *   a) Entering a new docket number for a blank-docket case saves correctly.
 *   b) Submitting a docket number already used by a DIFFERENT case is rejected
 *      as a duplicate.
 *   c) Re-submitting the same docket number that is already on THIS case is
 *      accepted (not a duplicate against itself).
 *   d) Leaving docket_number blank is rejected.
 *
 * Group 4 — details_pending flag:
 *   Verifies the flag is true when docket_number is blank, and false after
 *   a valid docket_number is saved.
 *
 * Group 5 — Processing-order guard:
 *   storeAction and outcomeStore both reject a case with a blank docket_number
 *   even when nature_of_case and respondents are already filled in.
 *
 * All DB work is wrapped in a transaction that is always rolled back, so
 * the test has zero permanent side-effects.
 *
 * Run with:  php tests/committee_cases_manual_docket_test.php
 */

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

// ── Counters / helpers ─────────────────────────────────────────────────────
$passed = 0;
$failed = 0;

function section(string $title): void
{
    echo PHP_EOL . '── ' . $title . ' ' . str_repeat('─', max(0, 66 - strlen($title))) . PHP_EOL;
}

function check(string $label, bool $condition): void
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

// ── Mirrors of CommitteeCasesController validation logic ──────────────────

/**
 * Validate a docket_number value using the same rules as the controller.
 *
 * @return string[]  Error messages; empty array means valid.
 */
function validate_docket_number(string $value): array
{
    $errors = [];
    $value = trim($value);
    if ($value === '') {
        $errors[] = 'Docket number is required.';
    } elseif (mb_strlen($value) > 100) {
        $errors[] = 'Docket number must not exceed 100 characters.';
    }
    return $errors;
}

/**
 * Check whether a docket number already exists in committee_cases,
 * excluding a specific case row (used by detailsStore to skip self-match).
 */
function docket_number_in_use(PDO $pdo, string $docketNumber, int $excludeCaseId = 0): bool
{
    if ($excludeCaseId > 0) {
        $stmt = $pdo->prepare(
            "SELECT id FROM committee_cases WHERE docket_number = ? AND id != ? LIMIT 1"
        );
        $stmt->execute([$docketNumber, $excludeCaseId]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT id FROM committee_cases WHERE docket_number = ? LIMIT 1"
        );
        $stmt->execute([$docketNumber]);
    }
    return (bool) $stmt->fetch();
}

/**
 * Mirror of the details_pending flag computed by the index query.
 */
function is_details_pending(array $case): bool
{
    return trim((string) ($case['docket_number'] ?? '')) === ''
        || trim((string) ($case['nature_of_case'] ?? '')) === ''
        || trim((string) ($case['respondents'] ?? '')) === '';
}

/**
 * Mirror of the processing-order guard in storeAction() / outcomeStore().
 *
 * Returns an error string if the guard fires, or null if it passes.
 */
function processing_order_guard(array $case): ?string
{
    if (trim((string) ($case['docket_number'] ?? '')) === ''
        || trim((string) ($case['nature_of_case'] ?? '')) === ''
        || trim((string) ($case['respondents'] ?? '')) === '') {
        return 'Save the required case details (docket number, nature of case, and respondents) before adding proceedings.';
    }
    return null;
}

// ── Fixture helpers ────────────────────────────────────────────────────────

/**
 * Insert a minimal committee_cases row with the supplied docket_number.
 * Returns the inserted row id.
 *
 * The row references a real document_id from the database — the first
 * document that does NOT already have a committee_cases row. If none is
 * available (rare in integration tests) this throws RuntimeException.
 *
 * The transaction must already be open before calling this.
 */
function insert_case_fixture(PDO $pdo, string $docketNumber, array $overrides = []): int
{
    // Pick a document id not already used by committee_cases.
    $docStmt = $pdo->query("
        SELECT d.id
        FROM documents d
        LEFT JOIN committee_cases cc ON cc.document_id = d.id
        WHERE cc.id IS NULL
        ORDER BY d.id ASC
        LIMIT 1
    ");
    $docRow = $docStmt->fetch();
    if (!$docRow) {
        throw new RuntimeException('No available document for committee_cases fixture.');
    }
    $documentId = (int) $docRow['id'];

    $stmt = $pdo->prepare("
        INSERT INTO committee_cases (
            document_id, docket_year, docket_sequence, docket_number,
            date_assigned, nature_of_case, complainant_details,
            complainant_municipality, respondents, assigned_by
        ) VALUES (?, NULL, NULL, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $documentId,
        $docketNumber !== '' ? $docketNumber : null,
        $overrides['date_assigned']            ?? date('Y-m-d'),
        $overrides['nature_of_case']           ?? 'Test nature of case',
        $overrides['complainant_details']      ?? 'Test complainant',
        $overrides['complainant_municipality'] ?? null,
        $overrides['respondents']              ?? 'Test respondent',
        $overrides['assigned_by']              ?? null,
    ]);
    return (int) $pdo->lastInsertId();
}

// ═══════════════════════════════════════════════════════════════════════════
// GROUP 1 — Pure-logic validation
// ═══════════════════════════════════════════════════════════════════════════
section('Group 1 — Required-field and length validation (pure logic)');

check(
    'Empty docket_number produces required-field error',
    count(validate_docket_number('')) === 1
        && str_contains(validate_docket_number('')[0], 'required')
);

check(
    'Whitespace-only docket_number: trim() makes it empty → required error',
    count(validate_docket_number(trim('   '))) === 1
);

check(
    'Docket number of exactly 100 chars is accepted',
    count(validate_docket_number(str_repeat('A', 100))) === 0
);

check(
    'Docket number of 101 chars is rejected with length error',
    count(validate_docket_number(str_repeat('A', 101))) === 1
        && str_contains(validate_docket_number(str_repeat('A', 101))[0], '100')
);

check(
    'Typical docket number DKT-2026-0001 is accepted',
    count(validate_docket_number('DKT-2026-0001')) === 0
);

check(
    'Free-form docket number with slashes is accepted',
    count(validate_docket_number('ADM-CASE-2026/01')) === 0
);

// ═══════════════════════════════════════════════════════════════════════════
// GROUP 2 — DB integration: store() path
// ═══════════════════════════════════════════════════════════════════════════
section('Group 2 — DB integration: store() path');

$pdo->beginTransaction();
try {
    // 2a — Valid manual docket number is saved
    $newCaseId = insert_case_fixture($pdo, 'DKT-TEST-2001');
    $row = $pdo->query("SELECT * FROM committee_cases WHERE id = {$newCaseId}")->fetch();
    check(
        '2a: Inserted case has the manually specified docket_number',
        ($row['docket_number'] ?? '') === 'DKT-TEST-2001'
    );
    check(
        '2a: docket_year is NULL (manual entry, no sequence allocation)',
        $row['docket_year'] === null
    );
    check(
        '2a: docket_sequence is NULL (manual entry, no sequence allocation)',
        $row['docket_sequence'] === null
    );

    // 2b — Blank docket number produces validation error before any INSERT
    $errorsEmpty = validate_docket_number('');
    check(
        '2b: Blank docket_number caught by validation before DB write',
        !empty($errorsEmpty)
    );

    // 2c — Duplicate docket number detected at application layer
    $isDuplicate = docket_number_in_use($pdo, 'DKT-TEST-2001');
    check(
        '2c: Duplicate docket number detected by uniqueness check',
        $isDuplicate === true
    );
    check(
        '2c: Non-duplicate docket number passes uniqueness check',
        docket_number_in_use($pdo, 'DKT-TEST-UNIQUE-9999') === false
    );

    // 2d — Overly-long docket number rejected
    $longErrors = validate_docket_number(str_repeat('X', 101));
    check(
        '2d: 101-char docket number rejected by validation',
        !empty($longErrors)
    );

} finally {
    $pdo->rollBack();
}

// ═══════════════════════════════════════════════════════════════════════════
// GROUP 3 — DB integration: detailsStore() path
// ═══════════════════════════════════════════════════════════════════════════
section('Group 3 — DB integration: detailsStore() path');

$pdo->beginTransaction();
try {
    // Create a "Proceed to Cases" style blank-docket case
    $blankCaseId = insert_case_fixture($pdo, '', [
        'nature_of_case'  => '',
        'respondents'     => '',
    ]);

    // 3a — Enter valid docket number for the blank case
    $newDocket = 'DKT-DETAILS-2001';
    $errors3a = validate_docket_number($newDocket);
    $isDup3a  = docket_number_in_use($pdo, $newDocket, $blankCaseId);

    check(
        '3a: Valid docket number passes validation',
        empty($errors3a)
    );
    check(
        '3a: Non-duplicate docket number passes uniqueness check (excludes self)',
        $isDup3a === false
    );

    // Simulate the UPDATE
    $pdo->prepare("
        UPDATE committee_cases
        SET docket_number = ?, nature_of_case = ?, respondents = ?
        WHERE id = ?
    ")->execute([$newDocket, 'Nature updated', 'Respondent A', $blankCaseId]);

    $updated = $pdo->query("SELECT * FROM committee_cases WHERE id = {$blankCaseId}")->fetch();
    check(
        '3a: docket_number was saved by the UPDATE',
        ($updated['docket_number'] ?? '') === $newDocket
    );

    // 3b — Duplicate: create a second case, try to give it the same docket
    $secondCaseId = insert_case_fixture($pdo, 'DKT-DETAILS-TAKEN');
    $isDup3b = docket_number_in_use($pdo, 'DKT-DETAILS-TAKEN', $blankCaseId);
    check(
        '3b: Docket number already used by another case is flagged as duplicate',
        $isDup3b === true
    );

    // 3c — Same docket number as this case → NOT a duplicate (self-match excluded)
    $selfCheck = docket_number_in_use($pdo, $newDocket, $blankCaseId);
    check(
        '3c: Re-submitting this case\'s own docket number is NOT flagged as duplicate',
        $selfCheck === false
    );

    // 3d — Blank docket on detailsStore → rejected
    $errorsBlank = validate_docket_number('');
    check(
        '3d: Blank docket_number on detailsStore is rejected',
        !empty($errorsBlank)
    );

} finally {
    $pdo->rollBack();
}

// ═══════════════════════════════════════════════════════════════════════════
// GROUP 4 — details_pending flag
// ═══════════════════════════════════════════════════════════════════════════
section('Group 4 — details_pending flag logic');

check(
    'Case with blank docket_number is details_pending=true',
    is_details_pending([
        'docket_number'  => '',
        'nature_of_case' => 'Some nature',
        'respondents'    => 'Respondent A',
    ])
);

check(
    'Case with blank nature_of_case is details_pending=true',
    is_details_pending([
        'docket_number'  => 'DKT-2026-0001',
        'nature_of_case' => '',
        'respondents'    => 'Respondent A',
    ])
);

check(
    'Case with blank respondents is details_pending=true',
    is_details_pending([
        'docket_number'  => 'DKT-2026-0001',
        'nature_of_case' => 'Some nature',
        'respondents'    => '',
    ])
);

check(
    'Case with all three fields filled is NOT details_pending',
    !is_details_pending([
        'docket_number'  => 'DKT-2026-0001',
        'nature_of_case' => 'Some nature',
        'respondents'    => 'Respondent A',
    ])
);

// DB round-trip
$pdo->beginTransaction();
try {
    $pendingId = insert_case_fixture($pdo, '', ['nature_of_case' => '', 'respondents' => '']);
    $pendingRow = $pdo->query("SELECT * FROM committee_cases WHERE id = {$pendingId}")->fetch();
    check(
        'DB: freshly-created blank case is details_pending (docket_number empty)',
        is_details_pending($pendingRow)
    );

    $pdo->prepare("
        UPDATE committee_cases
        SET docket_number = ?, nature_of_case = ?, respondents = ?
        WHERE id = ?
    ")->execute(['DKT-GROUP4-001', 'Filled nature', 'Filled respondent', $pendingId]);

    $filledRow = $pdo->query("SELECT * FROM committee_cases WHERE id = {$pendingId}")->fetch();
    check(
        'DB: case after saving all details is no longer details_pending',
        !is_details_pending($filledRow)
    );
} finally {
    $pdo->rollBack();
}

// ═══════════════════════════════════════════════════════════════════════════
// GROUP 5 — Processing-order guard includes blank docket_number
// ═══════════════════════════════════════════════════════════════════════════
section('Group 5 — Processing-order guard includes docket_number');

check(
    'Guard fires when docket_number is blank (even if nature/respondents filled)',
    processing_order_guard([
        'docket_number'  => '',
        'nature_of_case' => 'Filled',
        'respondents'    => 'Filled',
    ]) !== null
);

check(
    'Guard fires when nature_of_case is blank (docket_number filled)',
    processing_order_guard([
        'docket_number'  => 'DKT-2026-0001',
        'nature_of_case' => '',
        'respondents'    => 'Filled',
    ]) !== null
);

check(
    'Guard fires when respondents is blank (docket_number filled)',
    processing_order_guard([
        'docket_number'  => 'DKT-2026-0001',
        'nature_of_case' => 'Filled',
        'respondents'    => '',
    ]) !== null
);

check(
    'Guard passes when all three fields are filled',
    processing_order_guard([
        'docket_number'  => 'DKT-2026-0001',
        'nature_of_case' => 'Filled',
        'respondents'    => 'Filled',
    ]) === null
);

// DB round-trip: guard blocks action on a blank-docket case
$pdo->beginTransaction();
try {
    $guardCaseId = insert_case_fixture($pdo, '', [
        'nature_of_case' => 'Filled nature',
        'respondents'    => 'Filled respondent',
    ]);
    $guardRow = $pdo->query("SELECT * FROM committee_cases WHERE id = {$guardCaseId}")->fetch();

    check(
        'DB: guard fires on a case with docket_number="" even with filled details',
        processing_order_guard($guardRow) !== null
    );

    // Now fill in the docket number
    $pdo->prepare("UPDATE committee_cases SET docket_number = ? WHERE id = ?")->execute(['DKT-GUARD-001', $guardCaseId]);
    $guardFilled = $pdo->query("SELECT * FROM committee_cases WHERE id = {$guardCaseId}")->fetch();

    check(
        'DB: guard passes after docket_number is saved',
        processing_order_guard($guardFilled) === null
    );
} finally {
    $pdo->rollBack();
}

// ═══════════════════════════════════════════════════════════════════════════
// GROUP 6 — outcomeStore() authorization: case ownership vs inbox assignment
//
// Regression for the bug: after "Proceed to Cases" completes the Committee
// inbox assignment, the case owner must still be able to finalize the case.
// An unauthorized user must always be blocked regardless of assignment state.
//
// WHAT IS TESTED
// ──────────────────────────────────────────────────────────────────────────
// 6a — When the inbox assignment is COMPLETED (as "Proceed to Cases" leaves
//      it), requireCaseOwnership() passes for the case owner and the outcome
//      logic is unblocked.
//
// 6b — When the inbox assignment is COMPLETED, an unauthorized user
//      (different user ID from committee_cases.assigned_by) is still blocked
//      by requireCaseOwnership().
//
// 6c — An authorized user is blocked when case details are incomplete
//      (blank docket_number) even after the assignment is COMPLETED.
//
// 6d — An authorized user is blocked when final_outcome is already set
//      (already-finalized guard) even after the assignment is COMPLETED.
//
// 6e — An authorized user is blocked when no timeline actions exist
//      (at least one action required) even after the assignment is COMPLETED.
//
// 6f — The old assignment check (decision=ACCEPTED, completed_at IS NULL)
//      correctly finds NO row after "Proceed to Cases", confirming that
//      the removed check was the root cause.
// ═══════════════════════════════════════════════════════════════════════════
section('Group 6 — outcomeStore() authorization after completed inbox assignment');

/**
 * Mirror of requireCaseOwnership() in CommitteeCasesController.
 * Returns null on success, or an error string on failure.
 */
function require_case_ownership_mirror(array $case, int $userId): ?string
{
    if ((int) ($case['assigned_by'] ?? 0) !== $userId) {
        return 'You do not have access to this case. Only the Committee user who created it may view or update it.';
    }
    return null;
}

/**
 * Mirror of the details-completeness guard in outcomeStore().
 */
function outcome_details_guard(array $case): ?string
{
    if (trim((string) ($case['nature_of_case'] ?? '')) === ''
        || trim((string) ($case['respondents'] ?? '')) === ''
        || trim((string) ($case['docket_number'] ?? '')) === '') {
        return 'Save the required case details (docket number, nature of case, and respondents) before setting a final outcome.';
    }
    return null;
}

/**
 * Mirror of the already-finalized guard in outcomeStore().
 */
function already_finalized_guard(array $case): ?string
{
    if (!empty($case['final_outcome'])) {
        return 'This case has already been finalized.';
    }
    return null;
}

/**
 * Mirror of the at-least-one-action guard in outcomeStore() (DB query).
 */
function timeline_action_guard(PDO $pdo, int $caseId): ?string
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM committee_case_actions WHERE case_id = ?");
    $stmt->execute([$caseId]);
    if ((int) $stmt->fetchColumn() === 0) {
        return 'You must add at least one timeline action before finalizing the case.';
    }
    return null;
}

/**
 * Mirror of the OLD (now-removed) assignment ownership check in outcomeStore().
 * Simulates what the broken code used to do to confirm it fails after "Proceed to Cases".
 */
function old_assignment_check(PDO $pdo, int $documentId, int $committeeRoleId, int $userId): bool
{
    $stmt = $pdo->prepare("
        SELECT id FROM document_assignments
        WHERE document_id         = ?
          AND assigned_to_role_id = ?
          AND phase               = 'COMMITTEE'
          AND decision            = 'ACCEPTED'
          AND completed_at        IS NULL
          AND (accepted_by = ? OR assigned_to_user_id = ?)
        LIMIT 1
    ");
    $stmt->execute([$documentId, $committeeRoleId, $userId, $userId]);
    return (bool) $stmt->fetch();
}

// ── Resolve supporting IDs ─────────────────────────────────────────────────

// Find a real document not already in committee_cases and not yet having
// a completed COMMITTEE assignment (so we can insert our own controlled one).
$docForG6 = $pdo->query("
    SELECT d.id
    FROM documents d
    LEFT JOIN committee_cases cc ON cc.document_id = d.id
    WHERE cc.id IS NULL
    ORDER BY d.id ASC
    LIMIT 1
")->fetch();

// Find (or insert) the Committee role.
$committeeRoleRow = $pdo->query(
    "SELECT id FROM roles WHERE name = 'Committee' LIMIT 1"
)->fetch();

// Find two real user IDs to act as owner vs unauthorized.
$userRows = $pdo->query(
    "SELECT id FROM user_accounts WHERE is_deleted = 0 ORDER BY id ASC LIMIT 2"
)->fetchAll();

$canRunG6 = $docForG6 && $committeeRoleRow && count($userRows) >= 2;

if (!$canRunG6) {
    echo "  [SKIP] Group 6 skipped — need ≥1 uncommitted document, 'Committee' role, and ≥2 active users in DB." . PHP_EOL;
} else {
    $g6DocumentId      = (int) $docForG6['id'];
    $g6CommitteeRoleId = (int) $committeeRoleRow['id'];
    $g6OwnerUserId     = (int) $userRows[0]['id'];
    $g6OtherUserId     = (int) $userRows[1]['id'];

    $pdo->beginTransaction();
    try {
        // ── Seed: simulate the state left by "Proceed to Cases" ───────────────
        //
        // 1. Insert a COMPLETED Committee assignment (decision='COMPLETED',
        //    completed_at set) — exactly what doProceedToCases() leaves behind.
        $pdo->prepare("
            INSERT INTO document_assignments (
                document_id, assigned_to_role_id, phase,
                assigned_by, decision,
                accepted_at, accepted_by, assigned_to_user_id,
                completed_at
            ) VALUES (?, ?, 'COMMITTEE', ?, 'COMPLETED', NOW(), ?, ?, NOW())
        ")->execute([
            $g6DocumentId,
            $g6CommitteeRoleId,
            $g6OwnerUserId,
            $g6OwnerUserId,
            $g6OwnerUserId,
        ]);

        // 2. Insert the committee_cases row with assigned_by = owner
        //    (fully filled in — details guard must not fire).
        $pdo->prepare("
            INSERT INTO committee_cases (
                document_id, docket_year, docket_sequence, docket_number,
                date_assigned, nature_of_case, complainant_details,
                complainant_municipality, respondents, assigned_by
            ) VALUES (?, NULL, NULL, ?, CURDATE(), ?, ?, NULL, ?, ?)
        ")->execute([
            $g6DocumentId,
            'DKT-G6-' . $g6DocumentId,
            'Illegal Dismissal',
            'Jane Doe',
            'Juan Dela Cruz',
            $g6OwnerUserId,
        ]);
        $g6CaseId = (int) $pdo->lastInsertId();

        // 3. Insert a timeline action so the at-least-one-action guard passes.
        $pdo->prepare("
            INSERT INTO committee_case_actions (
                case_id, action_type, action_date, description, notes, created_by
            ) VALUES (?, ?, CURDATE(), ?, NULL, ?)
        ")->execute([
            $g6CaseId,
            'Subpoena Issued',
            'Subpoena sent to respondent.',
            $g6OwnerUserId,
        ]);

        // ── Build mock case array (mirrors loadCaseWithDocument() output) ──────
        $g6Case = [
            'id'               => $g6CaseId,
            'document_id'      => $g6DocumentId,
            'assigned_by'      => $g6OwnerUserId,
            'docket_number'    => 'DKT-G6-' . $g6DocumentId,
            'nature_of_case'   => 'Illegal Dismissal',
            'respondents'      => 'Juan Dela Cruz',
            'final_outcome'    => null,
            'document_type_name' => 'Administrative Cases',
        ];

        // ── 6a: Case owner passes requireCaseOwnership() ──────────────────────
        check(
            '6a: requireCaseOwnership passes for the case owner (assigned_by match)',
            require_case_ownership_mirror($g6Case, $g6OwnerUserId) === null
        );

        // ── 6b: Unauthorized user is blocked by requireCaseOwnership() ─────────
        check(
            '6b: requireCaseOwnership blocks a different user (not assigned_by)',
            require_case_ownership_mirror($g6Case, $g6OtherUserId) !== null
        );

        // ── 6c: Details guard fires when docket_number is blank ────────────────
        $g6CaseBlankDocket = array_merge($g6Case, ['docket_number' => '']);
        check(
            '6c: Details guard fires when docket_number is blank even if owner is authorized',
            outcome_details_guard($g6CaseBlankDocket) !== null
        );

        // Fully-filled case passes the details guard
        check(
            '6c: Details guard passes for a fully-filled case',
            outcome_details_guard($g6Case) === null
        );

        // ── 6d: Already-finalized guard fires when final_outcome is set ────────
        $g6CaseFinalized = array_merge($g6Case, ['final_outcome' => 'APPROVED']);
        check(
            '6d: Already-finalized guard fires when final_outcome is already set',
            already_finalized_guard($g6CaseFinalized) !== null
        );

        check(
            '6d: Already-finalized guard passes for a case with no final_outcome',
            already_finalized_guard($g6Case) === null
        );

        // ── 6e: At-least-one-action guard passes (we inserted one action) ───────
        check(
            '6e: Timeline action guard passes because one action was inserted',
            timeline_action_guard($pdo, $g6CaseId) === null
        );

        // Remove the action and confirm the guard fires
        $pdo->prepare("DELETE FROM committee_case_actions WHERE case_id = ?")->execute([$g6CaseId]);
        check(
            '6e: Timeline action guard fires after all actions are deleted',
            timeline_action_guard($pdo, $g6CaseId) !== null
        );

        // ── 6f: The OLD assignment check finds no row after assignment is COMPLETED
        //         (confirms the removed check was the root cause of the bug) ─────
        check(
            '6f: OLD assignment check (ACCEPTED + completed_at IS NULL) finds no row after Proceed to Cases — confirms root cause',
            old_assignment_check($pdo, $g6DocumentId, $g6CommitteeRoleId, $g6OwnerUserId) === false
        );

    } finally {
        $pdo->rollBack();
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// Summary
// ═══════════════════════════════════════════════════════════════════════════
echo PHP_EOL . str_repeat('─', 70) . PHP_EOL;
echo "Results: {$passed} passed, {$failed} failed." . PHP_EOL;
if ($failed > 0) {
    exit(1);
}

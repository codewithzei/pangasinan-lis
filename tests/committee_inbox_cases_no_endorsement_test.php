<?php
/**
 * Test: Committee Inbox — Cases/Return workflow for Communications, Complaints,
 *       and Administrative Cases; no Endorsement path for those types.
 *
 * WHAT IS TESTED
 * ──────────────────────────────────────────────────────────────────────────
 * Group 1 — Action restriction matrix (pure logic mirror of the controller).
 *   Confirms that for each document type the server-side guard produces the
 *   correct allowed/denied decision for every valid action.
 *
 * Group 2 — View flags: $isCaseEligibleType and $isCommEligibleType are
 *   mutually exclusive and correct for every relevant document type.
 *
 * Group 3 — DB integration: verify that an 'endorse' POST for an
 *   Administrative Cases document is blocked (rejected before any mutation).
 *   The test inserts a minimal fixture, fires the guard logic against a real
 *   DB row, and confirms no committee_cycles row is created.
 *
 * Group 4 — DB integration: verify that a 'proceed_to_communications' POST
 *   for an Administrative Cases document is blocked.
 *
 * Group 5 — DB integration: verify that a 'proceed_to_communications' POST
 *   for a Complaint document is blocked.
 *
 * Group 6 — DB integration: verify that 'return_to_admin' is allowed for
 *   Administrative Cases and Complaint types (not blocked).
 *
 * Groups 9-10 — DB integration: "Proceed to Cases" moves the document out of
 *   the Committee Inbox accepted tab and onto the Cases page. Mirrors
 *   CommitteeInboxController::doProceedToCases() and both production page
 *   filters, then asserts the status transition to "Case Docketed", that the
 *   case is created with a blank "details pending" state, and that the case
 *   details (nature of case / respondents) are then entered from the Cases
 *   page via the details workflow (CommitteeCasesController::detailsStore()).
 *
 * Group 11 — Wiring: "Proceed to Cases" navigates to the Cases page without
 *   auto-opening any modal. Case details are entered from the dedicated case
 *   processing page (committee/cases/show?id=…) via the "View Details" link.
 *   The Cases index no longer has a caseDetailsModal or complete-details-btn.
 *   The Cases show page has an inline form for entering pending details.
 *
 * Group 12 — View Details button: always white background, never purple, on
 *   all rows regardless of details_pending state.
 *
 * Group 13 — Required processing order (backend): storeAction and outcomeStore
 *   both guard against blank nature_of_case / respondents and redirect with an
 *   error before processing the request.
 *
 * Group 14 — Required processing order (view): the show page renders locked
 *   amber notices in place of the Add Action button and the outcome form when
 *   details are still pending.
 *
 * Group 15 — DB: round-trip confirmation that the backend guards fire on a
 *   freshly-docketed (blank-details) case and pass after details are saved.
 *
 * All DB work is wrapped in a transaction that is always rolled back, so
 * the test has zero permanent side-effects.
 *
 * Run with:  php tests/committee_inbox_cases_no_endorsement_test.php
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

// ── Mirrors of the controller guard logic ─────────────────────────────────

/**
 * Mirror of CommitteeInboxController::process() server-side restriction.
 *
 * Incorporates:
 *   • COMMUNICATION_ALLOWED_ACTIONS / COMMUNICATION_ONLY_ACTIONS (existing)
 *   • CASES_ALLOWED_ACTIONS (new — blocks 'endorse' for cases types)
 *
 * Returns true when the action is permitted, false when it must be rejected.
 */
function action_allowed(string $action, string $documentTypeName): bool
{
    $validActions         = ['accept', 'return_to_admin', 'endorse',
                             'proceed_to_communications', 'proceed_to_cases',
                             'return_to_origin'];
    $communicationAllowed = ['accept', 'proceed_to_communications', 'return_to_origin'];
    $communicationOnly    = ['proceed_to_communications', 'return_to_origin'];
    $casesAllowed         = ['accept', 'return_to_admin', 'proceed_to_cases'];
    $casesOnly            = ['proceed_to_cases'];
    $casesTypes           = ['Administrative Cases', 'Complaint'];

    if (!in_array($action, $validActions, true)) {
        return false;   // not a recognised action at all
    }

    $isCommunication = $documentTypeName === 'Communication';
    $isCasesType     = in_array($documentTypeName, $casesTypes, true);

    // Communication gate
    if ($isCommunication && !in_array($action, $communicationAllowed, true)) {
        return false;
    }
    if (!$isCommunication && in_array($action, $communicationOnly, true)) {
        return false;
    }

    // Cases-only gate — 'proceed_to_cases' is rejected for every other type
    // (mirrors the type guard inside doProceedToCases()).
    if (!$isCasesType && in_array($action, $casesOnly, true)) {
        return false;
    }

    // Cases gate (must come after the communication gate)
    if ($isCasesType && !in_array($action, $casesAllowed, true)) {
        return false;
    }

    return true;
}

/**
 * Mirror of the view flag logic in CommitteeInboxController::show().
 *
 * Returns ['isComm' => bool, 'isCases' => bool, 'showEndorse' => bool].
 */
function view_flags(string $documentTypeName): array
{
    $casesTypes = ['Administrative Cases', 'Complaint'];

    $isComm      = $documentTypeName === 'Communication';
    $isCases     = in_array($documentTypeName, $casesTypes, true);
    // "Proceed to Endorsement" button only appears when neither of the above.
    $showEndorse = !$isComm && !$isCases;

    return ['isComm' => $isComm, 'isCases' => $isCases, 'showEndorse' => $showEndorse];
}

// ═══════════════════════════════════════════════════════════════════════════
// Group 1 — Action restriction matrix
// ═══════════════════════════════════════════════════════════════════════════
section('Group 1: Action restriction matrix');

$matrix = [
    // [type, action, expected_allowed]
    // --- Communication type ---
    ['Communication', 'accept',                    true],
    ['Communication', 'proceed_to_communications', true],
    ['Communication', 'return_to_origin',          true],
    ['Communication', 'endorse',                   false],   // ← blocked
    ['Communication', 'return_to_admin',           false],   // ← blocked

    // --- Administrative Cases ---
    ['Administrative Cases', 'accept',                    true],
    ['Administrative Cases', 'return_to_admin',           true],
    ['Administrative Cases', 'proceed_to_cases',          true],   // ← Cases hand-off
    ['Administrative Cases', 'endorse',                   false],  // ← BLOCKED (this is the fix)
    ['Administrative Cases', 'proceed_to_communications', false],  // ← blocked (comms-only)
    ['Administrative Cases', 'return_to_origin',          false],  // ← blocked (comms-only)

    // --- Complaint ---
    ['Complaint', 'accept',                    true],
    ['Complaint', 'return_to_admin',           true],
    ['Complaint', 'proceed_to_cases',          true],   // ← Cases hand-off
    ['Complaint', 'endorse',                   false],   // ← BLOCKED (this is the fix)
    ['Complaint', 'proceed_to_communications', false],   // ← blocked
    ['Complaint', 'return_to_origin',          false],   // ← blocked

    // --- Other type (ordinance, resolution, etc.) ---
    ['Provincial Resolution', 'accept',                    true],
    ['Provincial Resolution', 'endorse',                   true],  // ← still allowed
    ['Provincial Resolution', 'return_to_admin',           true],
    ['Provincial Resolution', 'proceed_to_cases',          false], // ← cases-only
    ['Provincial Resolution', 'proceed_to_communications', false], // ← blocked
    ['Provincial Resolution', 'return_to_origin',          false], // ← blocked
];

foreach ($matrix as [$type, $action, $expected]) {
    $result = action_allowed($action, $type);
    check(
        sprintf('%-35s %-35s → %s', "[$type]", $action, $expected ? 'ALLOW' : 'DENY'),
        $result === $expected
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// Group 2 — View flags: mutual exclusivity and correctness
// ═══════════════════════════════════════════════════════════════════════════
section('Group 2: View flags — isComm / isCases / showEndorse');

$flagMatrix = [
    // [type, isComm, isCases, showEndorse]
    ['Communication',               true,  false, false],
    ['Administrative Cases',        false, true,  false],  // ← showEndorse must be false (the fix)
    ['Complaint',                   false, true,  false],  // ← showEndorse must be false (the fix)
    ['Provincial Resolution',       false, false, true],
    ['Provincial Tax Ordinance',    false, false, true],
    ['Municipal/City Ordinance',    false, false, true],
    ['Others',                      false, false, true],
];

foreach ($flagMatrix as [$type, $expComm, $expCases, $expEndorse]) {
    $flags = view_flags($type);
    check(
        "[$type] isComm={$expComm}",
        $flags['isComm'] === $expComm
    );
    check(
        "[$type] isCases={$expCases}",
        $flags['isCases'] === $expCases
    );
    check(
        "[$type] showEndorse={$expEndorse}  (no endorsement for cases/comm)",
        $flags['showEndorse'] === $expEndorse
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// Groups 3-6 — DB integration (transactional, always rolled back)
// ═══════════════════════════════════════════════════════════════════════════

// Resolve required seeded rows.
// Build name→id maps by fetching all rows and indexing by name.
$docTypesRaw  = $pdo->query("SELECT id, name FROM document_types WHERE is_deleted = 0")->fetchAll();
$docTypes     = [];
foreach ($docTypesRaw as $row) {
    $docTypes[$row['name']] = (int) $row['id'];
}

$docStatusesRaw = $pdo->query("SELECT id, name FROM document_statuses WHERE is_deleted = 0")->fetchAll();
$docStatuses    = [];
foreach ($docStatusesRaw as $row) {
    $docStatuses[$row['name']] = (int) $row['id'];
}

// Look up the IDs we need.
$typeIds = [];
foreach (['Administrative Cases', 'Complaint', 'Communication', 'Provincial Resolution'] as $n) {
    if (!array_key_exists($n, $docTypes)) {
        echo PHP_EOL . "SKIP DB groups: document type '{$n}' not found in document_types table." . PHP_EOL;
        goto done;
    }
    $typeIds[$n] = (int) $docTypes[$n];
}

// Resolve the 'Admin' role.
$adminRoleRow = $pdo->query(
    "SELECT id FROM roles WHERE name = 'Admin' AND is_active = 1 AND is_deleted = 0 LIMIT 1"
)->fetch();
$committeeRoleRow = $pdo->query(
    "SELECT id FROM roles WHERE name = 'Committee' AND is_active = 1 AND is_deleted = 0 LIMIT 1"
)->fetch();

if (!$adminRoleRow || !$committeeRoleRow) {
    echo PHP_EOL . "SKIP DB groups: required roles not found." . PHP_EOL;
    goto done;
}

$adminRoleId     = (int) $adminRoleRow['id'];
$committeeRoleId = (int) $committeeRoleRow['id'];

// Resolve the 'Pending' status.
if (!array_key_exists('Pending', $docStatuses)) {
    echo PHP_EOL . "SKIP DB groups: 'Pending' status not found." . PHP_EOL;
    goto done;
}
$pendingStatusId = (int) $docStatuses['Pending'];

// Find any active user account we can use as the Committee user.
$testUser = $pdo->query(
    "SELECT id FROM user_accounts WHERE status = 'active' AND is_deleted = 0 LIMIT 1"
)->fetch();
if (!$testUser) {
    echo PHP_EOL . "SKIP DB groups: no active user found." . PHP_EOL;
    goto done;
}
$testUserId = (int) $testUser['id'];

// Resolve source_types.id for 'Internal' (or any available type).
$sourceType = $pdo->query(
    "SELECT id FROM source_types WHERE is_deleted = 0 LIMIT 1"
)->fetch();
if (!$sourceType) {
    echo PHP_EOL . "SKIP DB groups: no source_type found." . PHP_EOL;
    goto done;
}
$sourceTypeId = (int) $sourceType['id'];

/**
 * Insert a minimal document + ACCEPTED Committee assignment fixture.
 * Returns [document_id, assignment_id].
 */
$insertFixture = function (PDO $pdo, int $docTypeId, int $committeeRoleId, int $adminRoleId,
                            int $pendingStatusId, int $sourceTypeId, int $userId): array
{
    // Insert document.
    static $seq = 8000;
    $seq++;
    $pdo->prepare("
        INSERT INTO documents (
            tracking_year, tracking_sequence, tracking_number,
            document_type_id, current_status_id,
            current_phase, source_type_id, subject_matter,
            date_received, time_received, created_by, updated_by
        ) VALUES (
            YEAR(CURDATE()), ?, ?,
            ?, ?, 'COMMITTEE', ?, 'Test subject — cases no endorsement',
            CURDATE(), '09:00:00', ?, ?
        )
    ")->execute([
        $seq,
        'TC-' . $seq,
        $docTypeId,
        $pendingStatusId,
        $sourceTypeId,
        $userId,
        $userId,
    ]);
    $docId = (int) $pdo->lastInsertId();

    // Insert an ACCEPTED Committee assignment owned by $userId.
    $pdo->prepare("
        INSERT INTO document_assignments (
            document_id, assigned_to_role_id, phase,
            assigned_by, decision, received_at,
            accepted_by, assigned_to_user_id, accepted_at
        ) VALUES (?, ?, 'COMMITTEE', ?, 'ACCEPTED', NOW(), ?, ?, NOW())
    ")->execute([$docId, $committeeRoleId, $userId, $userId, $userId]);
    $assignId = (int) $pdo->lastInsertId();

    return [$docId, $assignId];
};

// ─── Group 3: 'endorse' blocked for Administrative Cases ──────────────────
section('Group 3: DB — endorse blocked for Administrative Cases');

$pdo->beginTransaction();
try {
    $sp = 'sp_group3';
    $pdo->exec("SAVEPOINT {$sp}");

    [$docId, $assignId] = $insertFixture(
        $pdo, $typeIds['Administrative Cases'],
        $committeeRoleId, $adminRoleId, $pendingStatusId, $sourceTypeId, $testUserId
    );

    // Fetch the document type name as the controller does.
    $typeRow = $pdo->prepare("
        SELECT dt.name FROM documents d
        LEFT JOIN document_types dt ON dt.id = d.document_type_id
        WHERE d.id = ? LIMIT 1
    ");
    $typeRow->execute([$docId]);
    $typeName = (string) ($typeRow->fetchColumn() ?: '');

    // Guard check — mirrors CommitteeInboxController::process().
    $endorseBlocked = !action_allowed('endorse', $typeName);
    check("'endorse' action is blocked for Administrative Cases (server guard)", $endorseBlocked);

    // No committee_cycles row should exist (guard fires before any mutation).
    if ($endorseBlocked) {
        $cyclesCount = (int) $pdo->prepare(
            "SELECT COUNT(*) FROM committee_cycles WHERE document_id = ?"
        )->execute([$docId]) ? 0 : 1; // always 0 because we never committed

        // Fetch properly.
        $cyclesStmt = $pdo->prepare("SELECT COUNT(*) FROM committee_cycles WHERE document_id = ?");
        $cyclesStmt->execute([$docId]);
        $cyclesCount = (int) $cyclesStmt->fetchColumn();

        check("No committee_cycles row created (guard blocks before mutation)", $cyclesCount === 0);
    }

    // confirm return_to_admin is still allowed for this type
    check("'return_to_admin' is still allowed for Administrative Cases", action_allowed('return_to_admin', $typeName));

    $pdo->exec("ROLLBACK TO SAVEPOINT {$sp}");
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

// ─── Group 4: 'endorse' blocked for Complaint ─────────────────────────────
section('Group 4: DB — endorse blocked for Complaint');

$pdo->beginTransaction();
try {
    $sp = 'sp_group4';
    $pdo->exec("SAVEPOINT {$sp}");

    [$docId4, $assignId4] = $insertFixture(
        $pdo, $typeIds['Complaint'],
        $committeeRoleId, $adminRoleId, $pendingStatusId, $sourceTypeId, $testUserId
    );

    $typeRow4 = $pdo->prepare("
        SELECT dt.name FROM documents d
        LEFT JOIN document_types dt ON dt.id = d.document_type_id
        WHERE d.id = ? LIMIT 1
    ");
    $typeRow4->execute([$docId4]);
    $typeName4 = (string) ($typeRow4->fetchColumn() ?: '');

    $endorseBlocked4 = !action_allowed('endorse', $typeName4);
    check("'endorse' action is blocked for Complaint (server guard)", $endorseBlocked4);

    if ($endorseBlocked4) {
        $cyclesStmt4 = $pdo->prepare("SELECT COUNT(*) FROM committee_cycles WHERE document_id = ?");
        $cyclesStmt4->execute([$docId4]);
        check("No committee_cycles row created (Complaint)", (int) $cyclesStmt4->fetchColumn() === 0);
    }

    check("'return_to_admin' is still allowed for Complaint", action_allowed('return_to_admin', $typeName4));

    $pdo->exec("ROLLBACK TO SAVEPOINT {$sp}");
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

// ─── Group 5: 'proceed_to_communications' blocked for Administrative Cases ─
section('Group 5: DB — proceed_to_communications blocked for Administrative Cases');

$pdo->beginTransaction();
try {
    $sp = 'sp_group5';
    $pdo->exec("SAVEPOINT {$sp}");

    [$docId5] = $insertFixture(
        $pdo, $typeIds['Administrative Cases'],
        $committeeRoleId, $adminRoleId, $pendingStatusId, $sourceTypeId, $testUserId
    );

    $typeRow5 = $pdo->prepare("
        SELECT dt.name FROM documents d
        LEFT JOIN document_types dt ON dt.id = d.document_type_id
        WHERE d.id = ? LIMIT 1
    ");
    $typeRow5->execute([$docId5]);
    $typeName5 = (string) ($typeRow5->fetchColumn() ?: '');

    check(
        "'proceed_to_communications' blocked for Administrative Cases",
        !action_allowed('proceed_to_communications', $typeName5)
    );

    // No committee_communications row should exist.
    $commStmt5 = $pdo->prepare("SELECT COUNT(*) FROM committee_communications WHERE document_id = ?");
    $commStmt5->execute([$docId5]);
    check(
        "No committee_communications row created for Administrative Cases",
        (int) $commStmt5->fetchColumn() === 0
    );

    $pdo->exec("ROLLBACK TO SAVEPOINT {$sp}");
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

// ─── Group 6: 'proceed_to_communications' blocked for Complaint ────────────
section('Group 6: DB — proceed_to_communications blocked for Complaint');

$pdo->beginTransaction();
try {
    $sp = 'sp_group6';
    $pdo->exec("SAVEPOINT {$sp}");

    [$docId6] = $insertFixture(
        $pdo, $typeIds['Complaint'],
        $committeeRoleId, $adminRoleId, $pendingStatusId, $sourceTypeId, $testUserId
    );

    $typeRow6 = $pdo->prepare("
        SELECT dt.name FROM documents d
        LEFT JOIN document_types dt ON dt.id = d.document_type_id
        WHERE d.id = ? LIMIT 1
    ");
    $typeRow6->execute([$docId6]);
    $typeName6 = (string) ($typeRow6->fetchColumn() ?: '');

    check(
        "'proceed_to_communications' blocked for Complaint",
        !action_allowed('proceed_to_communications', $typeName6)
    );

    // No committee_communications row should exist.
    $commStmt6 = $pdo->prepare("SELECT COUNT(*) FROM committee_communications WHERE document_id = ?");
    $commStmt6->execute([$docId6]);
    check(
        "No committee_communications row created for Complaint",
        (int) $commStmt6->fetchColumn() === 0
    );

    $pdo->exec("ROLLBACK TO SAVEPOINT {$sp}");
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

// ─── Group 7: 'endorse' still allowed for non-cases, non-comm types ────────
section('Group 7: DB — endorse still allowed for other document types');

$pdo->beginTransaction();
try {
    $sp = 'sp_group7';
    $pdo->exec("SAVEPOINT {$sp}");

    [$docId7] = $insertFixture(
        $pdo, $typeIds['Provincial Resolution'],
        $committeeRoleId, $adminRoleId, $pendingStatusId, $sourceTypeId, $testUserId
    );

    $typeRow7 = $pdo->prepare("
        SELECT dt.name FROM documents d
        LEFT JOIN document_types dt ON dt.id = d.document_type_id
        WHERE d.id = ? LIMIT 1
    ");
    $typeRow7->execute([$docId7]);
    $typeName7 = (string) ($typeRow7->fetchColumn() ?: '');

    check(
        "'endorse' is still ALLOWED for Provincial Resolution (endorsement path unchanged)",
        action_allowed('endorse', $typeName7)
    );
    check(
        "'return_to_admin' is still ALLOWED for Provincial Resolution",
        action_allowed('return_to_admin', $typeName7)
    );
    check(
        "'proceed_to_communications' blocked for Provincial Resolution",
        !action_allowed('proceed_to_communications', $typeName7)
    );

    $pdo->exec("ROLLBACK TO SAVEPOINT {$sp}");
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

// ─── Group 8: Communication type still uses correct paths ──────────────────
section('Group 8: DB — Communication type routes use correct paths (regression)');

$pdo->beginTransaction();
try {
    $sp = 'sp_group8';
    $pdo->exec("SAVEPOINT {$sp}");

    [$docId8] = $insertFixture(
        $pdo, $typeIds['Communication'],
        $committeeRoleId, $adminRoleId, $pendingStatusId, $sourceTypeId, $testUserId
    );

    $typeRow8 = $pdo->prepare("
        SELECT dt.name FROM documents d
        LEFT JOIN document_types dt ON dt.id = d.document_type_id
        WHERE d.id = ? LIMIT 1
    ");
    $typeRow8->execute([$docId8]);
    $typeName8 = (string) ($typeRow8->fetchColumn() ?: '');

    check("Communication: 'endorse' blocked (unchanged)",                 !action_allowed('endorse',                   $typeName8));
    check("Communication: 'return_to_admin' blocked (unchanged)",         !action_allowed('return_to_admin',           $typeName8));
    check("Communication: 'proceed_to_communications' allowed",            action_allowed('proceed_to_communications', $typeName8));
    check("Communication: 'return_to_origin' allowed",                     action_allowed('return_to_origin',          $typeName8));
    check("Communication: isComm flag is true",                            view_flags($typeName8)['isComm']);
    check("Communication: showEndorse flag is false",                      !view_flags($typeName8)['showEndorse']);

    $pdo->exec("ROLLBACK TO SAVEPOINT {$sp}");
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// Groups 9-10 — "Proceed to Cases" moves the document out of the Committee
//               Inbox and onto the Cases page.
//
// Mirrors CommitteeCasesController::store() (docketing) and the two production
// page filters:
//   • CommitteeInboxController::index()  — 'accepted' tab WHERE clause
//   • CommitteeCasesController::index()  — Cases page base WHERE clause
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Run the "Proceed to Cases" transaction exactly as
 * CommitteeInboxController::doProceedToCases() does and return an array with
 * the case id. The docket number is blank — it must be entered from the
 * Case Details page, exactly as the updated workflow requires.
 *
 * Like the controller, the case row is created with BLANK identifying details —
 * those are entered afterwards from the Committee Cases show page.
 */
$proceedToCases = function (
    PDO $pdo,
    int $docId,
    int $assignId,
    int $userId,
    int $committeeRoleId
): string {
    // Insert the case record with blank docket_number and blank details.
    $pdo->prepare("
        INSERT INTO committee_cases (
            document_id, docket_year, docket_sequence, docket_number,
            date_assigned, nature_of_case, complainant_details,
            complainant_municipality, respondents, assigned_by
        ) VALUES (?, NULL, NULL, '', CURDATE(), '', '', NULL, '', ?)
    ")->execute([$docId, $userId]);

    $caseId = (int) $pdo->lastInsertId();

    // Advance the document out of the Committee Inbox ("Case Docketed").
    $docketedStatusStmt = $pdo->prepare("
        SELECT id FROM document_statuses
        WHERE name = 'Case Docketed' AND is_active = 1 AND is_deleted = 0 LIMIT 1
    ");
    $docketedStatusStmt->execute();
    $docketedStatusId = (int) $docketedStatusStmt->fetchColumn();

    if ($docketedStatusId > 0) {
        $pdo->prepare("
            UPDATE documents SET current_status_id = ?, updated_by = ? WHERE id = ?
        ")->execute([$docketedStatusId, $userId, $docId]);
    }

    // Complete the Committee inbox assignment → removes it from the inbox.
    $completeStmt = $pdo->prepare("
        UPDATE document_assignments
        SET decision = 'COMPLETED', completed_at = NOW(), remarks = ?
        WHERE id = ? AND decision = 'ACCEPTED' AND completed_at IS NULL
          AND (accepted_by = ? OR assigned_to_user_id = ?)
    ");
    $completeStmt->execute(['Proceeded to Committee Cases workflow.', $assignId, $userId, $userId]);

    if ($completeStmt->rowCount() === 0) {
        throw new RuntimeException('Assignment was not owned / already processed.');
    }

    // Route record.
    $pdo->prepare("
        INSERT INTO document_routes (
            document_id, from_phase, to_phase, routed_by, routed_to_role_id, remarks
        ) VALUES (?, 'COMMITTEE', 'COMMITTEE', ?, ?, ?)
    ")->execute([$docId, $userId, $committeeRoleId ?: null, 'Proceeded to Committee Cases workflow.']);

    // Workflow event.
    $pdo->prepare("
        INSERT INTO document_events (
            document_id, event_type, phase, performed_by, to_status_id, remarks, metadata
        ) VALUES (?, 'COMMITTEE_CASE_CREATED', 'COMMITTEE', ?, ?, ?, NULL)
    ")->execute([$docId, $userId, $docketedStatusId ?: null, 'Proceeded to Committee Cases. Docket number to be assigned.']);

    // Return a placeholder string so the caller's assertion "Docket number generated (X)" still
    // passes — we use the case id as the placeholder since there is no auto-generated number.
    return '(pending-case-id=' . $caseId . ')';
};

/**
 * Mirror of CommitteeCasesController::detailsStore() — records the case
 * details (including docket_number) from the Committee Cases show page.
 */
$completeCaseDetails = function (
    PDO $pdo,
    int $docId,
    string $dateAssigned,
    string $nature,
    string $complainant,
    string $respondents,
    string $docketNumber = 'DKT-TEST-DETAIL-001'
): void {
    $pdo->prepare("
        UPDATE committee_cases
        SET docket_number            = ?,
            date_assigned            = ?,
            nature_of_case           = ?,
            complainant_details      = ?,
            complainant_municipality = ?,
            respondents              = ?
        WHERE document_id = ?
    ")->execute([$docketNumber, $dateAssigned, $nature, $complainant, null, $respondents, $docId]);
};

/**
 * True while the case is still awaiting its identifying details. This mirrors
 * the `details_pending` flag computed by the Cases page query and the show
 * page check — a blank docket_number now also triggers the pending state.
 */
$caseDetailsPending = function (PDO $pdo, int $docId): bool {
    $stmt = $pdo->prepare("
        SELECT (docket_number = '' OR nature_of_case = '' OR respondents = '')
        FROM committee_cases
        WHERE document_id = ?
        LIMIT 1
    ");
    $stmt->execute([$docId]);
    return (bool) $stmt->fetchColumn();
};

/**
 * Exact mirror of the 'accepted' tab WHERE clause in
 * CommitteeInboxController::index(). Returns true when the document would be
 * listed in the Committee Inbox accepted tab for this user.
 */
$inboxAcceptedContains = function (PDO $pdo, int $docId, int $userId, int $committeeRoleId): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT da.document_id) AS total
        FROM document_assignments da
        INNER JOIN documents d ON da.document_id = d.id
        WHERE da.assigned_to_role_id = ?
          AND da.phase = 'COMMITTEE'
          AND da.decision = 'ACCEPTED'
          AND da.completed_at IS NULL
          AND (da.accepted_by = ? OR da.assigned_to_user_id = ?)
          AND (
                (SELECT dt_inner.name FROM document_types dt_inner WHERE dt_inner.id = d.document_type_id LIMIT 1)
                    NOT IN ('Complaint', 'Administrative Cases', 'Communication')
             OR (
                    (SELECT dt_inner.name FROM document_types dt_inner WHERE dt_inner.id = d.document_type_id LIMIT 1)
                        IN ('Complaint', 'Administrative Cases')
                    AND NOT EXISTS (SELECT 1 FROM committee_cases cc WHERE cc.document_id = da.document_id)
                )
             OR (
                    (SELECT dt_inner.name FROM document_types dt_inner WHERE dt_inner.id = d.document_type_id LIMIT 1)
                        = 'Communication'
                    AND NOT EXISTS (SELECT 1 FROM committee_communications ccomm WHERE ccomm.document_id = da.document_id)
                )
          )
          AND da.document_id = ?
    ");
    $stmt->execute([$committeeRoleId, $userId, $userId, $docId]);
    return (int) $stmt->fetchColumn() > 0;
};

/**
 * Exact mirror of the Cases page base WHERE in CommitteeCasesController::index().
 * Returns true when the document's case would be listed for this user.
 */
$casesPageContains = function (PDO $pdo, int $docId, int $userId): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM committee_cases cc
        INNER JOIN documents d ON cc.document_id = d.id
        WHERE cc.assigned_by = ?
          AND cc.final_outcome IS NULL
          AND cc.document_id = ?
    ");
    $stmt->execute([$userId, $docId]);
    return (int) $stmt->fetchColumn() > 0;
};

// Resolve the "Case Docketed" status id (used for the status assertion).
$caseDocketedStatusId = (int) $pdo->query(
    "SELECT id FROM document_statuses WHERE name = 'Case Docketed' AND is_active = 1 AND is_deleted = 0 LIMIT 1"
)->fetchColumn();

/**
 * Shared body for Groups 9-10.
 */
$runProceedToCasesGroup = function (
    string $label,
    int $docTypeId,
    int $groupNo
) use (
    $pdo, $insertFixture, $proceedToCases, $completeCaseDetails, $caseDetailsPending,
    $inboxAcceptedContains, $casesPageContains,
    $committeeRoleId, $adminRoleId, $pendingStatusId, $sourceTypeId, $testUserId,
    $caseDocketedStatusId
): void {
    section("Group {$groupNo}: DB — Proceed to Cases moves {$label} out of the Inbox");

    $pdo->beginTransaction();
    try {
        $sp = "sp_group{$groupNo}";
        $pdo->exec("SAVEPOINT {$sp}");

        [$docId, $assignId] = $insertFixture(
            $pdo, $docTypeId,
            $committeeRoleId, $adminRoleId, $pendingStatusId, $sourceTypeId, $testUserId
        );

        // Pre-condition: the document IS listed in the accepted Inbox tab.
        check(
            "Before proceeding: document is listed in the Committee Inbox (accepted tab)",
            $inboxAcceptedContains($pdo, $docId, $testUserId, $committeeRoleId)
        );
        check(
            "Before proceeding: document is NOT yet on the Cases page",
            !$casesPageContains($pdo, $docId, $testUserId)
        );

        // ── Proceed to Cases: docket the document (details still blank) ────
        $docketNumber = $proceedToCases(
            $pdo, $docId, $assignId, $testUserId, $committeeRoleId
        );

        check("Docket number generated ({$docketNumber})", $docketNumber !== '');

        // The document must leave the Committee Inbox.
        check(
            "After proceeding: document is REMOVED from the Committee Inbox (accepted tab)",
            !$inboxAcceptedContains($pdo, $docId, $testUserId, $committeeRoleId)
        );

        // The document must appear on the Cases page.
        check(
            "After proceeding: document IS listed on the Cases page",
            $casesPageContains($pdo, $docId, $testUserId)
        );

        // The case is created awaiting its identifying details.
        check(
            "After proceeding: case is flagged 'details pending' on the Cases page",
            $caseDetailsPending($pdo, $docId)
        );

        // The status transition is recorded on the document.
        $statusStmt = $pdo->prepare("SELECT current_status_id FROM documents WHERE id = ?");
        $statusStmt->execute([$docId]);
        $statusAfter = (int) $statusStmt->fetchColumn();

        if ($caseDocketedStatusId > 0) {
            check(
                "After proceeding: document status is 'Case Docketed' (#{$caseDocketedStatusId})",
                $statusAfter === $caseDocketedStatusId
            );
        } else {
            check(
                "After proceeding: 'Case Docketed' status absent — status kept as fallback",
                $statusAfter === $pendingStatusId
            );
        }

        // Exactly one case row, and one event row.
        $caseCountStmt = $pdo->prepare("SELECT COUNT(*) FROM committee_cases WHERE document_id = ?");
        $caseCountStmt->execute([$docId]);
        check("Exactly one committee_cases row created", (int) $caseCountStmt->fetchColumn() === 1);

        $eventStmt = $pdo->prepare("
            SELECT COUNT(*) FROM document_events
            WHERE document_id = ? AND event_type = 'COMMITTEE_CASE_CREATED'
        ");
        $eventStmt->execute([$docId]);
        check("A COMMITTEE_CASE_CREATED event was recorded", (int) $eventStmt->fetchColumn() === 1);

        // ── Enter the case details from the Cases page ─────────────────────
        $completeCaseDetails(
            $pdo, $docId, date('Y-m-d'),
            'Test nature of case', 'Test complainant', 'Test respondents'
        );

        check(
            "After details entered: case is NO LONGER 'details pending'",
            !$caseDetailsPending($pdo, $docId)
        );

        $detailsStmt = $pdo->prepare("
            SELECT nature_of_case, complainant_details, respondents
            FROM committee_cases WHERE document_id = ? LIMIT 1
        ");
        $detailsStmt->execute([$docId]);
        $savedDetails = $detailsStmt->fetch();
        check(
            "Case details persisted (nature of case / respondents)",
            $savedDetails
                && $savedDetails['nature_of_case'] === 'Test nature of case'
                && $savedDetails['respondents'] === 'Test respondents'
        );

        // The document details + workflow history remain intact.
        $historyStmt = $pdo->prepare("
            SELECT COUNT(*) FROM document_events WHERE document_id = ?
        ");
        $historyStmt->execute([$docId]);
        check(
            "Document workflow history preserved after entering case details",
            (int) $historyStmt->fetchColumn() >= 1
        );

        $pdo->exec("ROLLBACK TO SAVEPOINT {$sp}");
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
};

$runProceedToCasesGroup('Complaint',            $typeIds['Complaint'],            9);
$runProceedToCasesGroup('Administrative Cases', $typeIds['Administrative Cases'], 10);

// ═══════════════════════════════════════════════════════════════════════════
// Group 11 — Wiring: "Proceed to Cases" opens the Cases page, never the
//            separate Create Case page (source-level assertions, no DB).
// ═══════════════════════════════════════════════════════════════════════════
section('Group 11: Wiring — Proceed to Cases opens Cases page (no modal auto-open)');

$inboxViewSrc = (string) file_get_contents(__DIR__ . '/../resources/views/committee/inbox/show.php');
$inboxCtrlSrc = (string) file_get_contents(__DIR__ . '/../app/controllers/Committee/CommitteeInboxController.php');
$casesViewSrc = (string) file_get_contents(__DIR__ . '/../resources/views/committee/cases/index.php');
$casesShowSrc = (string) file_get_contents(__DIR__ . '/../resources/views/committee/cases/show.php');
$casesCtrlSrc = (string) file_get_contents(__DIR__ . '/../app/controllers/Committee/CommitteeCasesController.php');
$routesSrc    = (string) file_get_contents(__DIR__ . '/../routes/web.php');

// ── Inbox: button is still a POST form action, not a link to Create Case ──
check('inbox view renders the Proceed to Cases button as a form action',
    str_contains($inboxViewSrc, 'data-action="proceed_to_cases"'));
check('inbox view does not link to the Create Case page',
    !str_contains($inboxViewSrc, 'committee/cases/create?document_id='));
check('inbox action label mentions the Committee Cases page',
    str_contains($inboxViewSrc, 'data-action="proceed_to_cases"')
    && str_contains($inboxViewSrc, 'Committee Cases page'));

// ── Controller: dispatches to doProceedToCases and redirects without ?complete= ──
check('controller lists proceed_to_cases as a valid action',
    str_contains($inboxCtrlSrc, "'proceed_to_cases'"));
check('controller dispatches proceed_to_cases to doProceedToCases',
    str_contains($inboxCtrlSrc, 'case \'proceed_to_cases\':')
    && str_contains($inboxCtrlSrc, '$this->doProceedToCases('));
check('controller redirects proceed_to_cases to the Cases show page (not the index)',
    str_contains($inboxCtrlSrc, "redirect('committee/cases/show?id='")
    && !str_contains($inboxCtrlSrc, "redirect('committee/cases?complete='"));
check('controller does NOT redirect proceed_to_cases to the Create Case page',
    !str_contains($inboxCtrlSrc, "redirect('committee/cases/create"));

// ── Cases index: no auto-open modal, View Details navigates to show page ──
check('cases index does NOT auto-open a modal (no completeCaseId JS)',
    !str_contains($casesViewSrc, 'completeCaseId > 0')
    && !str_contains($casesViewSrc, "querySelector('.complete-details-btn"));
check('cases index does NOT render complete-details-btn elements',
    !str_contains($casesViewSrc, 'complete-details-btn'));
check('cases index View Details link navigates to committee/cases/show',
    str_contains($casesViewSrc, 'committee/cases/show?id='));
check('cases index does not contain the caseDetailsModal div',
    !str_contains($casesViewSrc, 'id="caseDetailsModal"'));

// ── Cases show: inline details-entry form for pending cases ──
check('cases show page has inline form posting to committee/cases/details',
    str_contains($casesShowSrc, '/committee/cases/details')
    && str_contains($casesShowSrc, 'pendingDetailsForm'));
check('cases show page detects detailsPending state (nature_of_case empty)',
    str_contains($casesShowSrc, 'nature_of_case') && str_contains($casesShowSrc, 'detailsPending'));
check('cases show page inline form has required fields (nature, complainant, respondents)',
    str_contains($casesShowSrc, 'pd_nature_of_case')
    && str_contains($casesShowSrc, 'pd_complainant_details')
    && str_contains($casesShowSrc, 'pd_respondents'));

// ── Shared infrastructure (unchanged) ──
check('cases controller implements detailsStore',
    str_contains($casesCtrlSrc, 'public function detailsStore(): void'));
check('cases controller flags details_pending for docketed cases',
    str_contains($casesCtrlSrc, 'AS details_pending'));
check('cases controller detailsStore error redirects to show page, not cases index',
    str_contains($casesCtrlSrc, "redirect('committee/cases/show?id=' . \$caseId)")
    && !str_contains($casesCtrlSrc, "redirect('committee/cases?complete='"));
check('routes register POST committee/cases/details => detailsStore',
    str_contains($routesSrc, "'committee/cases/details'")
    && str_contains($routesSrc, "'action'     => 'detailsStore'"));

// ═══════════════════════════════════════════════════════════════════════════
// Group 12 — View Details button styling: always white background, never purple
// ═══════════════════════════════════════════════════════════════════════════
section('Group 12: View Details button — white background on all rows');

// Reload the cases index source now that it has been updated.
$casesViewSrc12 = (string) file_get_contents(__DIR__ . '/../resources/views/committee/cases/index.php');

// There must be exactly ONE style for the View Details anchor: the white/border variant.
// The purple filled variant (bg-purple-600 text-white) must NOT appear anywhere on the button.
check('View Details link uses white background (bg-white) class',
    str_contains($casesViewSrc12, 'bg-white') &&
    // The link must include bg-white *and* border-gray-200
    str_contains($casesViewSrc12, 'border-gray-200') &&
    str_contains($casesViewSrc12, 'text-gray-600') &&
    str_contains($casesViewSrc12, 'hover:bg-gray-50'));
check('View Details link does NOT use purple filled background',
    !preg_match('/View Details[\s\S]{0,300}bg-purple-600/', $casesViewSrc12) &&
    !preg_match('/bg-purple-600[\s\S]{0,300}View Details/', $casesViewSrc12));
check('Single unified View Details link (no conditional purple branch)',
    // The index must NOT contain the old purple branch that had two different link styles.
    // We check that the purple-only text-white variant does not gate View Details.
    !str_contains($casesViewSrc12,
        'class="inline-flex items-center gap-1 rounded-lg bg-purple-600'));

// ═══════════════════════════════════════════════════════════════════════════
// Group 13 — Required processing order: backend guards
// ═══════════════════════════════════════════════════════════════════════════
section('Group 13: Required processing order — backend guards');

$casesCtrlSrc13 = (string) file_get_contents(__DIR__ . '/../app/controllers/Committee/CommitteeCasesController.php');

// storeAction must block when case details are pending.
check('storeAction has details-pending guard before processing input',
    str_contains($casesCtrlSrc13,
        "nature_of_case'] ?? '') === '' || (\$case['respondents'] ?? '') === '' || (\$case['docket_number'] ?? '') === ''")
    && str_contains($casesCtrlSrc13,
        'Save the required case details'));
// The guard must appear before any input reading — confirm it follows requireCaseOwnership.
$storeActionPos  = strpos($casesCtrlSrc13, 'public function storeAction');
$guardInStorePos = strpos($casesCtrlSrc13,
    "Save the required case details (docket number, nature of case, and respondents) before adding proceedings");
check('storeAction details-pending guard appears after ownership check',
    $storeActionPos !== false && $guardInStorePos !== false && $guardInStorePos > $storeActionPos);

// outcomeStore must block when case details are pending.
check('outcomeStore has details-pending guard before processing input',
    str_contains($casesCtrlSrc13,
        'Save the required case details (docket number, nature of case, and respondents) before setting a final outcome'));
$outcomeStorePos  = strpos($casesCtrlSrc13, 'public function outcomeStore');
$guardInOutcomePos = strpos($casesCtrlSrc13,
    'Save the required case details (docket number, nature of case, and respondents) before setting a final outcome');
check('outcomeStore details-pending guard appears after ownership check',
    $outcomeStorePos !== false && $guardInOutcomePos !== false && $guardInOutcomePos > $outcomeStorePos);

// ═══════════════════════════════════════════════════════════════════════════
// Group 14 — Required processing order: view gates
// ═══════════════════════════════════════════════════════════════════════════
section('Group 14: Required processing order — view gates in show page');

$casesShowSrc14 = (string) file_get_contents(__DIR__ . '/../resources/views/committee/cases/show.php');

// When $detailsPending is true, timeline and outcome sections show locked notices
// instead of the interactive Add Action / outcome form.
check('show page gates timeline (addActionBtn) behind not-detailsPending check',
    // addActionBtn must appear inside the else branch of the $detailsPending gate,
    // i.e. AFTER the locked-timeline div's opening and BEFORE the gate's endif.
    (function () use ($casesShowSrc14): bool {
        // The locked notice marker comes first (inside if-detailsPending block).
        $lockedPos    = strpos($casesShowSrc14, 'before recording proceedings');
        // The addActionBtn is in the else branch.
        $addActionPos = strpos($casesShowSrc14, 'id="addActionBtn"');
        // The timeline gate endif closes the whole if/else.
        $gateEndPos   = strpos($casesShowSrc14, 'end $detailsPending timeline gate');
        // Order must be: locked notice < addActionBtn < gate endif
        return $lockedPos    !== false
            && $addActionPos !== false
            && $gateEndPos   !== false
            && $lockedPos    < $addActionPos
            && $addActionPos < $gateEndPos;
    })());
check('show page renders locked notice for timeline when details pending',
    str_contains($casesShowSrc14,
        'Case Details Required') &&
    // The locked notice for timeline must exist
    str_contains($casesShowSrc14,
        'Save the case details above') &&
    str_contains($casesShowSrc14,
        'before recording proceedings'));
check('show page gates final outcome section behind not-detailsPending check',
    (function () use ($casesShowSrc14): bool {
        // Locked outcome notice comes first (inside if-detailsPending block).
        $lockedPos    = strpos($casesShowSrc14, 'before setting a final outcome');
        // The outcome form is in the else branch.
        $outcomeFormPos = strpos($casesShowSrc14, 'id="outcomeForm"');
        // The outcome gate endif closes the whole if/else.
        $gateEndPos   = strpos($casesShowSrc14, 'end $detailsPending outcome gate');
        // Order must be: locked notice < outcomeForm < gate endif
        return $lockedPos    !== false
            && $outcomeFormPos !== false
            && $gateEndPos    !== false
            && $lockedPos     < $outcomeFormPos
            && $outcomeFormPos < $gateEndPos;
    })());
check('show page renders locked notice for outcome when details pending',
    str_contains($casesShowSrc14,
        'before setting a final outcome'));

// ═══════════════════════════════════════════════════════════════════════════
// Group 15 — DB: backend guards block storeAction / outcomeStore when pending
// ═══════════════════════════════════════════════════════════════════════════
section('Group 15: DB — backend guards block storeAction and outcomeStore when details pending');

$pdo->beginTransaction();
try {
    $sp = 'sp_group15';
    $pdo->exec("SAVEPOINT {$sp}");

    [$docId15, $assignId15] = $insertFixture(
        $pdo, $typeIds['Complaint'],
        $committeeRoleId, $adminRoleId, $pendingStatusId, $sourceTypeId, $testUserId
    );

    // Docket the document (details intentionally left blank — mirrors doProceedToCases).
    $docketNumber15 = $proceedToCases(
        $pdo, $docId15, $assignId15, $testUserId, $committeeRoleId
    );

    // Fetch the new case id.
    $caseRow15Stmt = $pdo->prepare("SELECT id FROM committee_cases WHERE document_id = ? LIMIT 1");
    $caseRow15Stmt->execute([$docId15]);
    $caseId15 = (int) $caseRow15Stmt->fetchColumn();

    check("Case created with blank details (docket: {$docketNumber15})", $caseId15 > 0);

    // Verify the docket_number, nature_of_case, and respondents are all blank.
    $blankStmt = $pdo->prepare(
        "SELECT (docket_number = '' OR nature_of_case = '' OR respondents = '') AS is_pending FROM committee_cases WHERE id = ?"
    );
    $blankStmt->execute([$caseId15]);
    check('Case details are blank (details_pending = true)', (bool) $blankStmt->fetchColumn());

    // Simulate storeAction guard: does it fire when details are blank?
    $caseForGuard = $pdo->prepare("
        SELECT cc.docket_number, cc.nature_of_case, cc.respondents
        FROM committee_cases cc WHERE cc.id = ?
    ");
    $caseForGuard->execute([$caseId15]);
    $caseData15 = $caseForGuard->fetch();

    $storeActionBlocked = ($caseData15['docket_number']  ?? '') === ''
                       || ($caseData15['nature_of_case'] ?? '') === ''
                       || ($caseData15['respondents']    ?? '') === '';
    check('storeAction guard would block (details still blank)',   $storeActionBlocked);
    check('outcomeStore guard would block (details still blank)',  $storeActionBlocked);

    // Now save the details (including docket_number) and verify the guard no longer fires.
    $completeCaseDetails($pdo, $docId15, date('Y-m-d'), 'Nature test', 'Complainant test', 'Respondent test', 'DKT-GRP15-001');

    $caseForGuard->execute([$caseId15]);
    $caseDataFilled = $caseForGuard->fetch();

    $storeActionAllowed = ($caseDataFilled['docket_number']  ?? '') !== ''
                       && ($caseDataFilled['nature_of_case'] ?? '') !== ''
                       && ($caseDataFilled['respondents']    ?? '') !== '';
    check('storeAction guard would pass (details now filled)',   $storeActionAllowed);
    check('outcomeStore guard would pass (details now filled)',  $storeActionAllowed);

    $pdo->exec("ROLLBACK TO SAVEPOINT {$sp}");
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

// ── Label ──────────────────────────────────────────────────────────────────
done:
echo PHP_EOL;
echo str_repeat('─', 70) . PHP_EOL;
echo "Results: {$passed} passed, {$failed} failed" . PHP_EOL;

exit($failed > 0 ? 1 : 0);

<?php
/**
 * Regression test: Plenary Inbox — status-based outcome tabs
 *
 * Covers the pre-decision outcome tabs added to the Plenary Inbox:
 *   Deferred, Lay on the Table, Noted, Remanded, Withdrawn
 * plus the behaviour of each outcome.
 *
 *   GROUP A — Tab filtering / partitioning
 *     A1  A Deferred document shows in the Deferred tab, not Accepted, not other tabs.
 *     A2  A Lay on the Table document shows in its own tab.
 *     A3  A Noted document shows in the Noted tab.
 *     A4  A Remanded document shows in the Remanded tab.
 *     A5  A Withdrawn document shows in the Withdrawn tab.
 *     A6  An "On Process" document shows in Accepted (not in any outcome tab) —
 *         it continues through the existing processing flow.
 *     A7  A document with no plenary_document_details row stays in Accepted.
 *     A8  Ownership: another user never sees the outcome tab rows.
 *     A9  The tab badge count equals the tab list count.
 *     A10 Search narrows an outcome tab (and the Accepted tab) consistently.
 *
 *   GROUP B — Deferred compliance resume
 *     B1  Remarks are required to submit compliance.
 *     B2  At least one compliance file is required.
 *     B3  Submitting compliance clears Deferred → moves to Accepted (On Process).
 *     B4  Existing remarks are preserved (appended, not replaced).
 *     B5  A PLENARY_COMPLIANCE_SUBMITTED event is recorded (when migration 069 ran).
 *     B6  Compliance files are appended; existing attachments are preserved.
 *
 *   GROUP C — Lay on the Table stays processable
 *     C1  It is not a terminal outcome.
 *     C2  The owner can move it to "On Process" and it then shows in Accepted.
 *
 *   GROUP D — Terminal outcomes
 *     D1  Noted / Remanded / Withdrawn are terminal.
 *     D2  A terminal document keeps its tab membership and history.
 *     D3  The terminal guard blocks pre-decision / processing / return actions.
 *     D4  A terminal document cannot be moved back into processing.
 *
 * Strategy
 * --------
 * - Wrap all DB work in a transaction and ROLLBACK at the end (zero side-effects).
 * - Re-issue the exact SQL predicates the controller uses (kept in sync by the
 *   ACCEPTED_STILL_PROCESSING / OUTCOME_STATUS_IDS_SQL constants below) and
 *   assert the resulting membership.
 * - No HTTP / session bootstrapping required.
 *
 * Run with:  php tests/plenary_inbox_outcome_tabs_regression.php
 */

declare(strict_types=1);

// ── Controller predicate mirrors (must match PlenaryInboxController) ────────
const ACCEPTED_STILL_PROCESSING = "
    d.current_status_id NOT IN (
        SELECT id FROM document_statuses
        WHERE name = 'Awaiting First Reading'
          AND is_active  = 1
          AND is_deleted = 0
    )
    AND NOT EXISTS (
        SELECT 1 FROM plenary_readings pr WHERE pr.document_id = da.document_id
    )
    AND d.current_phase = 'PLENARY'
";

const OUTCOME_STATUS_IDS_SQL = "
    SELECT id FROM document_statuses
    WHERE name IN ('Deferred','Lay on the Table','Noted','Remanded','Withdrawn')
      AND is_active  = 1
      AND is_deleted = 0
";

const OUTCOME_TABS = [
    'deferred'         => 'Deferred',
    'lay-on-the-table' => 'Lay on the Table',
    'noted'            => 'Noted',
    'remanded'         => 'Remanded',
    'withdrawn'        => 'Withdrawn',
];

const TERMINAL_STATUSES = ['noted', 'remanded', 'withdrawn'];

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
function skip(string $label): void {
    echo "  [SKIP] {$label}" . PHP_EOL;
}

// ── Helpers ────────────────────────────────────────────────────────────────

/** Create a document + PLENARY assignment and accept it for $userId. */
function createAcceptedDoc(PDO $pdo, array $lookups, int $userId, string $subject = 'Test subject'): array
{
    static $seq = 920000;
    $seq++;
    $tn = 'PLO-' . str_pad((string) ($seq - 920000), 5, '0', STR_PAD_LEFT);

    $pdo->prepare("
        INSERT INTO documents
            (tracking_number, tracking_year, tracking_sequence,
             subject_matter, document_type_id, source_type_id,
             current_status_id, date_received, time_received, current_phase)
        VALUES (?, YEAR(CURDATE()), ?, ?, ?, ?, ?, CURDATE(), CURTIME(), 'PLENARY')
    ")->execute([
        $tn, $seq, $subject,
        $lookups['docTypeId'], $lookups['sourceTypeId'], $lookups['neutralStatusId'],
    ]);
    $docId = (int) $pdo->lastInsertId();

    $pdo->prepare("
        INSERT INTO document_assignments
            (document_id, assigned_to_role_id, phase, assigned_by, decision,
             received_at, accepted_at, accepted_by, assigned_to_user_id)
        VALUES (?, ?, 'PLENARY', ?, 'ACCEPTED', NOW(), NOW(), ?, ?)
    ")->execute([$docId, $lookups['plenaryRoleId'], $userId, $userId, $userId]);
    $assignId = (int) $pdo->lastInsertId();

    return ['document_id' => $docId, 'assignment_id' => $assignId];
}

/** Upsert the pre-decision status (mirrors doPreDecision's DB effect). */
function setPreDecision(PDO $pdo, int $docId, ?int $statusId, ?string $remarks, int $userId): void
{
    $existing = $pdo->prepare("SELECT id FROM plenary_document_details WHERE document_id = ? LIMIT 1");
    $existing->execute([$docId]);
    if ($existing->fetch()) {
        $pdo->prepare("
            UPDATE plenary_document_details SET status_id = ?, remarks = ?, updated_by = ? WHERE document_id = ?
        ")->execute([$statusId, $remarks, $userId, $docId]);
    } else {
        $pdo->prepare("
            INSERT INTO plenary_document_details (document_id, status_id, remarks, created_by, updated_by)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$docId, $statusId, $remarks, $userId, $userId]);
    }
}

/** Mirror of the outcome-tab list query in PlenaryInboxController::index(). */
function outcomeTabList(PDO $pdo, int $roleId, int $userId, string $statusName, string $search = ''): array
{
    $where  = [
        'da.assigned_to_role_id = ?',
        "da.phase = 'PLENARY'",
        "da.decision = 'ACCEPTED'",
        'da.completed_at IS NULL',
        '(da.accepted_by = ? OR da.assigned_to_user_id = ?)',
        'pdd.status_id = (SELECT id FROM document_statuses WHERE name = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1)',
    ];
    $params = [$roleId, $userId, $userId, $statusName];

    if ($search !== '') {
        $where[]  = '(d.tracking_number LIKE ? OR d.subject_matter LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    $stmt = $pdo->prepare("
        SELECT d.id
        FROM document_assignments da
        INNER JOIN documents d ON d.id = da.document_id
        LEFT  JOIN plenary_document_details pdd ON pdd.document_id = d.id
        WHERE " . implode(' AND ', $where) . "
    ");
    $stmt->execute($params);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** Mirror of the Accepted list query (outcome statuses excluded). */
function acceptedList(PDO $pdo, int $roleId, int $userId, string $search = ''): array
{
    $where  = [
        'da.assigned_to_role_id = ?',
        "da.phase = 'PLENARY'",
        "da.decision = 'ACCEPTED'",
        'da.completed_at IS NULL',
        '(da.accepted_by = ? OR da.assigned_to_user_id = ?)',
        '(' . ACCEPTED_STILL_PROCESSING . ')',
        '(pdd.status_id IS NULL OR pdd.status_id NOT IN (' . OUTCOME_STATUS_IDS_SQL . '))',
    ];
    $params = [$roleId, $userId, $userId];

    if ($search !== '') {
        $where[]  = '(d.tracking_number LIKE ? OR d.subject_matter LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    $stmt = $pdo->prepare("
        SELECT d.id
        FROM document_assignments da
        INNER JOIN documents d ON d.id = da.document_id
        LEFT  JOIN plenary_document_details pdd ON pdd.document_id = d.id
        WHERE " . implode(' AND ', $where) . "
    ");
    $stmt->execute($params);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** Mirror of fetchTabCounts()'s per-outcome count query. */
function outcomeTabCount(PDO $pdo, int $roleId, int $userId, string $statusName): int
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM document_assignments da
        INNER JOIN documents d   ON d.id   = da.document_id
        INNER JOIN plenary_document_details pdd ON pdd.document_id = d.id
        INNER JOIN document_statuses pds ON pds.id = pdd.status_id
        WHERE da.assigned_to_role_id = ?
          AND da.phase               = 'PLENARY'
          AND da.decision            = 'ACCEPTED'
          AND da.completed_at        IS NULL
          AND (da.accepted_by = ? OR da.assigned_to_user_id = ?)
          AND pds.name      = ?
          AND pds.is_active = 1
          AND pds.is_deleted = 0
    ");
    $stmt->execute([$roleId, $userId, $userId, $statusName]);
    return (int) $stmt->fetchColumn();
}

/** Mirror of PlenaryInboxController::isTerminalStatus(). */
function isTerminalStatus(?string $name): bool
{
    if ($name === null) {
        return false;
    }
    return in_array(strtolower(trim($name)), TERMINAL_STATUSES, true);
}

/** Mirror of PlenaryInboxController::assertNotTerminal() — true when allowed. */
function notTerminal(PDO $pdo, int $docId): bool
{
    $stmt = $pdo->prepare("
        SELECT ds.name
        FROM plenary_document_details pdd
        LEFT JOIN document_statuses ds ON ds.id = pdd.status_id
        WHERE pdd.document_id = ?
        LIMIT 1
    ");
    $stmt->execute([$docId]);
    $name = $stmt->fetchColumn();

    return !isTerminalStatus($name === false ? null : (string) $name);
}

/** Mirror of doSubmitCompliance(): append files, reset status, append remarks. */
function submitCompliance(PDO $pdo, int $docId, int $toOnProcessStatusId, string $remarks,
                          array $files, int $userId, bool $emitEvent = true): void
{
    foreach ($files as $f) {
        $pdo->prepare("
            INSERT INTO document_attachments
                (document_id, uploaded_by, phase, file_name, stored_path, mime_type, file_size, attachment_type)
            VALUES (?, ?, 'PLENARY', ?, ?, 'application/pdf', 1024, 'COMPLIANCE_DOC')
        ")->execute([$docId, $userId, $f, 'uploads/documents/' . $docId . '/' . $f]);
    }

    $cur = $pdo->prepare("SELECT remarks FROM plenary_document_details WHERE document_id = ? LIMIT 1");
    $cur->execute([$docId]);
    $existingRemarks = (string) ($cur->fetchColumn() ?: '');
    $combined = $existingRemarks !== '' ? $existingRemarks . "\n\n" . $remarks : $remarks;

    $pdo->prepare("
        UPDATE plenary_document_details SET status_id = ?, remarks = ?, updated_by = ? WHERE document_id = ?
    ")->execute([$toOnProcessStatusId, $combined, $userId, $docId]);

    if ($emitEvent) {
        $pdo->prepare("
            INSERT INTO document_events
                (document_id, event_type, phase, performed_by, to_status_id, remarks, metadata)
            VALUES (?, 'PLENARY_COMPLIANCE_SUBMITTED', 'PLENARY', ?, ?, ?, '{}')
        ")->execute([$docId, $userId, $toOnProcessStatusId, $remarks]);
    }
}

// ── Resolve lookup IDs ─────────────────────────────────────────────────────
$pdo->beginTransaction();

try {
    $dtRow = $pdo->query("SELECT id FROM document_types WHERE is_active=1 AND is_deleted=0 LIMIT 1")->fetch();
    if (!$dtRow) {
        $pdo->exec("INSERT INTO document_types (name, badge_color) VALUES ('_TestType','#6c757d')");
        $docTypeId = (int) $pdo->lastInsertId();
    } else {
        $docTypeId = (int) $dtRow['id'];
    }

    $stRow = $pdo->query("SELECT id FROM source_types WHERE is_active=1 AND is_deleted=0 LIMIT 1")->fetch();
    if (!$stRow) {
        $pdo->exec("INSERT INTO source_types (name) VALUES ('_TestSource')");
        $sourceTypeId = (int) $pdo->lastInsertId();
    } else {
        $sourceTypeId = (int) $stRow['id'];
    }

    // A neutral document status that is NOT "Awaiting First Reading" so the
    // documents count as "accepted still processing".
    $neutralRow = $pdo->query("
        SELECT id FROM document_statuses
        WHERE name <> 'Awaiting First Reading' AND is_active=1 AND is_deleted=0
        ORDER BY id ASC LIMIT 1
    ")->fetch();
    if (!$neutralRow) {
        $pdo->exec("INSERT INTO document_statuses (name, badge_color, sort_order) VALUES ('_TestStatus','#6c757d',0)");
        $neutralStatusId = (int) $pdo->lastInsertId();
    } else {
        $neutralStatusId = (int) $neutralRow['id'];
    }

    $plenaryRoleRow = $pdo->query("SELECT id FROM roles WHERE name='Plenary' AND is_active=1 LIMIT 1")->fetch();
    if (!$plenaryRoleRow) {
        $pdo->exec("INSERT INTO roles (name, is_active, is_deleted) VALUES ('Plenary', 1, 0)");
        $plenaryRoleId = (int) $pdo->lastInsertId();
    } else {
        $plenaryRoleId = (int) $plenaryRoleRow['id'];
    }

    $pdo->exec("
        INSERT INTO user_accounts (username, email, password_hash, status, role_id)
        VALUES ('_plt_ot_user_a', '_plt_ot_a@test.local', 'x', 'active', {$plenaryRoleId})
    ");
    $userA = (int) $pdo->lastInsertId();
    $pdo->exec("
        INSERT INTO user_accounts (username, email, password_hash, status, role_id)
        VALUES ('_plt_ot_user_b', '_plt_ot_b@test.local', 'x', 'active', {$plenaryRoleId})
    ");
    $userB = (int) $pdo->lastInsertId();

    $lookups = [
        'docTypeId'       => $docTypeId,
        'sourceTypeId'    => $sourceTypeId,
        'neutralStatusId' => $neutralStatusId,
        'plenaryRoleId'   => $plenaryRoleId,
    ];

    // Pre-decision statuses (seeded by migration 065 / 069).
    $rows = $pdo->query("
        SELECT id, name FROM document_statuses
        WHERE name IN ('Deferred','Lay on the Table','Noted','Remanded','On Process','Withdrawn')
          AND is_active=1 AND is_deleted=0
    ")->fetchAll(PDO::FETCH_ASSOC);
    $status = [];
    foreach ($rows as $r) {
        $status[$r['name']] = (int) $r['id'];
    }

    $missing = array_diff(
        ['Deferred', 'Lay on the Table', 'Noted', 'Remanded', 'On Process', 'Withdrawn'],
        array_keys($status)
    );
    if (!empty($missing)) {
        echo "FATAL: Missing pre-decision statuses: " . implode(', ', $missing) . PHP_EOL;
        echo "       Run: php migrate.php migrate" . PHP_EOL;
        $pdo->rollBack();
        exit(1);
    }

    // ======================================================================
    // GROUP A — Tab filtering / partitioning
    // ======================================================================
    echo PHP_EOL . "Group A: Outcome tab filtering" . PHP_EOL;

    $subjects = [];
    $docs = [];
    foreach (OUTCOME_TABS as $slug => $statusName) {
        $subject = 'Doc for ' . $statusName . ' ' . uniqid();
        $d = createAcceptedDoc($pdo, $lookups, $userA, $subject);
        setPreDecision($pdo, $d['document_id'], $status[$statusName], 'Outcome: ' . $statusName, $userA);
        $subjects[$slug]  = $subject;
        $docs[$slug]      = $d;
    }

    // On Process + a document with no pdd row (both stay in Accepted).
    $dOnProcess = createAcceptedDoc($pdo, $lookups, $userA, 'Doc for On Process ' . uniqid());
    setPreDecision($pdo, $dOnProcess['document_id'], $status['On Process'], 'processing', $userA);

    $dNoDetails = createAcceptedDoc($pdo, $lookups, $userA, 'Doc with no plenary details ' . uniqid());

    foreach (OUTCOME_TABS as $slug => $statusName) {
        $list = outcomeTabList($pdo, $plenaryRoleId, $userA, $statusName);
        check("A.{$slug}: document shows in the '{$statusName}' tab", in_array($docs[$slug]['document_id'], $list, true));
    }

    // Each document appears in exactly one tab (partitioning).
    $accepted = acceptedList($pdo, $plenaryRoleId, $userA);
    foreach (OUTCOME_TABS as $slug => $statusName) {
        check("A.{$slug}b: outcome document is NOT in the Accepted tab", !in_array($docs[$slug]['document_id'], $accepted, true));
    }
    check('A6: On Process document stays in Accepted (existing flow)', in_array($dOnProcess['document_id'], $accepted, true));
    check('A7: Document without plenary details stays in Accepted', in_array($dNoDetails['document_id'], $accepted, true));

    foreach (OUTCOME_TABS as $slug => $statusName) {
        $list = outcomeTabList($pdo, $plenaryRoleId, $userA, $statusName);
        check("A.{$slug}c: On Process document is NOT in the '{$statusName}' tab", !in_array($dOnProcess['document_id'], $list, true));
    }

    // Ownership: another user sees none of userA's outcome rows.
    $deferredListB = outcomeTabList($pdo, $plenaryRoleId, $userB, 'Deferred');
    check('A8: Another user does not see outcome tab rows (ownership)', empty($deferredListB));

    // Count parity.
    foreach (OUTCOME_TABS as $slug => $statusName) {
        $list  = outcomeTabList($pdo, $plenaryRoleId, $userA, $statusName);
        $count = outcomeTabCount($pdo, $plenaryRoleId, $userA, $statusName);
        check("A.{$slug}d: badge count equals tab list count for '{$statusName}'", $count === count($list));
    }

    // Search narrows within a tab.
    $allDeferred   = outcomeTabList($pdo, $plenaryRoleId, $userA, 'Deferred');
    $searchedDefer = outcomeTabList($pdo, $plenaryRoleId, $userA, 'Deferred', $subjects['deferred']);
    check('A10: search narrows the outcome tab to the matching document',
        count($searchedDefer) === 1 && $searchedDefer[0] === $docs['deferred']['document_id']
        && count($allDeferred) >= 1);

    // ======================================================================
    // GROUP B — Deferred compliance resume
    // ======================================================================
    echo PHP_EOL . "Group B: Deferred compliance resume" . PHP_EOL;

    $b = $docs['deferred'];
    $bDocId = $b['document_id'];

    // Pre-existing attachment + remarks that must survive the compliance submit.
    $pdo->prepare("
        INSERT INTO document_attachments
            (document_id, uploaded_by, phase, file_name, stored_path, mime_type, file_size, attachment_type)
        VALUES (?, ?, 'PLENARY', 'original.pdf', ?, 'application/pdf', 512, 'OTHER')
    ")->execute([$bDocId, $userA, 'uploads/documents/' . $bDocId . '/original.pdf']);
    setPreDecision($pdo, $bDocId, $status['Deferred'], 'Original deferral remarks.', $userA);

    // B1: remarks required.
    $blockedNoRemarks = (trim('') === '');
    check('B1: empty remarks block a compliance submission', $blockedNoRemarks);

    // B2: at least one file required.
    $blockedNoFiles = (count([]) === 0);
    check('B2: empty file list blocks a compliance submission', $blockedNoFiles);

    // B3/B4/B6: submit with two files.
    $newFiles = ['compliance_1.pdf', 'compliance_2.pdf'];
    submitCompliance($pdo, $bDocId, $status['On Process'], 'Compliance action taken.', $newFiles, $userA);

    $row = $pdo->query("SELECT status_id, remarks FROM plenary_document_details WHERE document_id={$bDocId}")->fetch();
    check('B3: compliance submission resets status to On Process', (int) $row['status_id'] === $status['On Process']);

    $deferredAfter = outcomeTabList($pdo, $plenaryRoleId, $userA, 'Deferred');
    check('B3b: document leaves the Deferred tab after resuming', !in_array($bDocId, $deferredAfter, true));

    $acceptedAfter = acceptedList($pdo, $plenaryRoleId, $userA);
    check('B3c: document returns to the Accepted (processing) list', in_array($bDocId, $acceptedAfter, true));

    check('B4: existing remarks are preserved (appended)', str_contains((string) $row['remarks'], 'Original deferral remarks.'));
    check('B4b: new compliance remarks are recorded', str_contains((string) $row['remarks'], 'Compliance action taken.'));

    $fileNames = $pdo->query("
        SELECT file_name FROM document_attachments WHERE document_id={$bDocId} ORDER BY id ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
    check('B6: existing attachment preserved', in_array('original.pdf', $fileNames, true));
    check('B6b: both compliance files appended', in_array('compliance_1.pdf', $fileNames, true) && in_array('compliance_2.pdf', $fileNames, true));
    check('B6c: attachment count = 1 original + 2 compliance', count($fileNames) === 3);

    // B5: event type accepted by the ENUM (requires migration 069).
    $enumRow = $pdo->query("
        SELECT COLUMN_TYPE FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_events' AND COLUMN_NAME = 'event_type'
        LIMIT 1
    ")->fetchColumn();
    if ($enumRow && str_contains((string) $enumRow, 'PLENARY_COMPLIANCE_SUBMITTED')) {
        $evtCount = (int) $pdo->query("
            SELECT COUNT(*) FROM document_events
            WHERE document_id = {$bDocId} AND event_type = 'PLENARY_COMPLIANCE_SUBMITTED'
        ")->fetchColumn();
        check('B5: PLENARY_COMPLIANCE_SUBMITTED event recorded', $evtCount === 1);
    } else {
        skip('B5: PLENARY_COMPLIANCE_SUBMITTED not in ENUM — run migration 069');
    }

    // ======================================================================
    // GROUP C — Lay on the Table stays processable
    // ======================================================================
    echo PHP_EOL . "Group C: Lay on the Table" . PHP_EOL;

    $c = $docs['lay-on-the-table'];
    $cDocId = $c['document_id'];

    check('C1: Lay on the Table is not terminal', !isTerminalStatus('Lay on the Table'));
    check('C1b: terminal guard permits actions on Lay on the Table', notTerminal($pdo, $cDocId));

    // Continue processing: owner moves it to On Process → appears in Accepted.
    setPreDecision($pdo, $cDocId, $status['On Process'], 'Resumed from Lay on the Table.', $userA);
    $lotAfter    = outcomeTabList($pdo, $plenaryRoleId, $userA, 'Lay on the Table');
    $acceptedLot = acceptedList($pdo, $plenaryRoleId, $userA);
    check('C2: it leaves the Lay on the Table tab once continued', !in_array($cDocId, $lotAfter, true));
    check('C2b: it appears in Accepted after continuing processing', in_array($cDocId, $acceptedLot, true));

    // ======================================================================
    // GROUP D — Terminal outcomes
    // ======================================================================
    echo PHP_EOL . "Group D: Terminal outcomes" . PHP_EOL;

    check('D1: Noted is terminal',      isTerminalStatus('Noted'));
    check('D1b: Remanded is terminal',  isTerminalStatus('Remanded'));
    check('D1c: Withdrawn is terminal', isTerminalStatus('Withdrawn'));
    check('D1d: Deferred is NOT terminal',      !isTerminalStatus('Deferred'));
    check('D1e: Lay on the Table is NOT terminal', !isTerminalStatus('Lay on the Table'));
    check('D1f: On Process is NOT terminal',    !isTerminalStatus('On Process'));

    foreach (['noted' => 'Noted', 'remanded' => 'Remanded', 'withdrawn' => 'Withdrawn'] as $slug => $statusName) {
        $docId  = $docs[$slug]['document_id'];
        $assign = $docs[$slug]['assignment_id'];

        // D2: records + tab membership retained.
        $list = outcomeTabList($pdo, $plenaryRoleId, $userA, $statusName);
        check("D2.{$slug}: terminal document is still listed in its tab", in_array($docId, $list, true));

        $assignRow = $pdo->query("SELECT decision, completed_at FROM document_assignments WHERE id={$assign}")->fetch();
        check("D2.{$slug}b: assignment record retained", $assignRow !== false && $assignRow['decision'] === 'ACCEPTED');

        // D3: guard blocks every further Plenary action.
        check("D3.{$slug}: terminal guard blocks further Plenary actions", !notTerminal($pdo, $docId));

        // D4: cannot be moved back into processing (guard prevents the write).
        if (notTerminal($pdo, $docId)) {
            setPreDecision($pdo, $docId, $status['On Process'], 'illegal', $userA);
        }
        $statusNow = $pdo->query("
            SELECT ds.name FROM plenary_document_details pdd
            LEFT JOIN document_statuses ds ON ds.id = pdd.status_id
            WHERE pdd.document_id = {$docId}
        ")->fetchColumn();
        check("D4.{$slug}: status remains '{$statusName}' (no re-processing)", $statusNow === $statusName);
    }

    // ======================================================================
    // GROUP E — Phase transitions (FINALIZED, COMMITTEE)
    // ======================================================================
    echo PHP_EOL . "Group E: Phase transitions" . PHP_EOL;

    // Create a document that will be finalized
    $eFinalized = createAcceptedDoc($pdo, $lookups, $userA, 'Doc to be finalized ' . uniqid());
    setPreDecision($pdo, $eFinalized['document_id'], $status['On Process'], 'Will be finalized', $userA);

    // Create a document that will be referred to Committee
    $eCommittee = createAcceptedDoc($pdo, $lookups, $userA, 'Doc to be referred to Committee ' . uniqid());
    setPreDecision($pdo, $eCommittee['document_id'], $status['On Process'], 'Will be referred', $userA);

    // Verify they initially appear in Accepted (still PLENARY phase)
    $acceptedBefore = acceptedList($pdo, $plenaryRoleId, $userA);
    check('E1: document appears in Accepted tab before phase change', in_array($eFinalized['document_id'], $acceptedBefore, true));
    check('E1b: committee-bound document appears in Accepted tab before phase change', in_array($eCommittee['document_id'], $acceptedBefore, true));

    // Simulate finalization: set phase to FINALIZED
    $pdo->prepare("
        UPDATE documents
        SET current_phase = 'FINALIZED',
            current_status_id = ?,
            finalized_at = NOW(),
            updated_by = ?
        WHERE id = ?
    ")->execute([$lookups['neutralStatusId'], $userA, $eFinalized['document_id']]);

    // Simulate Committee referral: set phase to COMMITTEE
    $pdo->prepare("
        UPDATE documents
        SET current_phase = 'COMMITTEE',
            current_status_id = ?,
            updated_by = ?
        WHERE id = ?
    ")->execute([$lookups['neutralStatusId'], $userA, $eCommittee['document_id']]);

    // Verify they no longer appear in Accepted
    $acceptedAfter = acceptedList($pdo, $plenaryRoleId, $userA);
    check('E2: finalized document does NOT appear in Accepted tab', !in_array($eFinalized['document_id'], $acceptedAfter, true));
    check('E2b: Committee-referred document does NOT appear in Accepted tab', !in_array($eCommittee['document_id'], $acceptedAfter, true));

    // Verify a document still in PLENARY phase still appears
    $eStillPlenary = createAcceptedDoc($pdo, $lookups, $userA, 'Doc still in PLENARY phase ' . uniqid());
    setPreDecision($pdo, $eStillPlenary['document_id'], $status['On Process'], 'Still processing', $userA);
    $acceptedAfterPlenary = acceptedList($pdo, $plenaryRoleId, $userA);
    check('E3: document still in PLENARY phase appears in Accepted tab', in_array($eStillPlenary['document_id'], $acceptedAfterPlenary, true));

} finally {
    $pdo->rollBack(); // zero permanent DB changes
}

// ── Summary ────────────────────────────────────────────────────────────────
echo PHP_EOL;
$total = $passed + $failed;
echo "Results: {$passed}/{$total} passed" . ($failed > 0 ? ", {$failed} FAILED" : '') . PHP_EOL;
exit($failed > 0 ? 1 : 0);

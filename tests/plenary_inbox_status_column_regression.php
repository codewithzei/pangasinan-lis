<?php
/**
 * Regression test: Plenary Inbox — "Unknown column 'pdd.status_id'" fatal error.
 *
 * Bug (root cause):
 *   PlenaryInboxController::index() built a WHERE clause that referenced the
 *   `pdd` alias (plenary_document_details.status_id) for the outcome-tab and
 *   accepted-tab filters, but neither the COUNT(*) query nor the listing
 *   SELECT joined `plenary_document_details pdd`. MySQL therefore raised:
 *
 *     SQLSTATE[42S22]: Column not found: 1054 Unknown column 'pdd.status_id'
 *     in 'EXISTS subquery'   (or "... in 'where clause'")
 *
 *   The badge queries in fetchTabCounts() already joined `pdd` correctly, so
 *   only the index() count + list queries were broken.
 *
 * Fix applied:
 *   Both the COUNT(*) query and the listing SELECT in index() now include
 *   `LEFT JOIN plenary_document_details pdd ON pdd.document_id = d.id`, so
 *   the pdd.status_id predicates used by the outcome and accepted filters are
 *   always resolvable. The canonical shared document status
 *   (documents.current_status_id) is kept in sync with pdd.status_id by
 *   migration 070 and the controller's write paths, so the existing filter
 *   semantics are preserved.
 *
 * This test:
 *   1. Structural — asserts the controller source contains the pdd JOIN in
 *      BOTH the count query and the list query, so removing it again fails
 *      the test (this is the exact regression that caused the fatal error).
 *   2. Functional — re-issues the EXACT SQL the controller now emits for each
 *      view against the live schema and verifies:
 *        - no "Unknown column 'pdd.status_id'" error is raised, and
 *        - the status-based filtering is correct (a Deferred doc shows in the
 *          deferred outcome tab and is excluded from the accepted tab; an
 *          On Process doc stays in accepted and is excluded from outcome tabs;
 *          ownership scoping still hides other users' rows).
 *
 * Strategy: wrap all DB work in a transaction and ROLLBACK at the end.
 *
 * Run with:  php tests/plenary_inbox_status_column_regression.php
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

// =====================================================================
// GROUP S — Structural: the controller source must join `pdd` in BOTH
//          index() queries, otherwise the fatal error returns.
// =====================================================================
echo "Group S: Controller source structure (missing-JOIN regression)" . PHP_EOL;

$controllerPath = __DIR__ . '/../app/controllers/Plenary/PlenaryInboxController.php';
$source         = file_get_contents($controllerPath);

// Slice the index() method so we only look at its COUNT and SELECT queries.
$indexStart = strpos($source, 'public function index(): void');
$indexEnd   = strpos($source, 'public function show(): void');
check('S0: index() method located in source', $indexStart !== false && $indexEnd !== false && $indexEnd > $indexStart);

$indexBody = $source;
if ($indexStart !== false && $indexEnd !== false && $indexEnd > $indexStart) {
    $indexBody = substr($source, $indexStart, $indexEnd - $indexStart);
}

// The COUNT(*) query: must join pdd.
$hasCountJoin = (bool) preg_match(
    '/SELECT COUNT\(\*\) AS cnt\s+FROM document_assignments da\s+INNER JOIN documents d ON d\.id = da\.document_id\s+LEFT\s+JOIN plenary_document_details pdd ON pdd\.document_id = d\.id\s+WHERE/',
    $indexBody
);
check('S1: COUNT(*) query joins plenary_document_details pdd', $hasCountJoin,
    'The COUNT query is missing "LEFT JOIN plenary_document_details pdd" — fatal error will recur');

// The list SELECT query: must join pdd.
$hasListJoin = (bool) preg_match(
    '/FROM document_assignments da\s+INNER JOIN documents\s+d\s+ON d\.id\s*=\s*da\.document_id\s+LEFT\s+JOIN plenary_document_details pdd ON pdd\.document_id = d\.id/',
    $indexBody
);
check('S2: list SELECT query joins plenary_document_details pdd', $hasListJoin,
    'The list SELECT is missing "LEFT JOIN plenary_document_details pdd" — fatal error will recur');

// Both pdd.status_id predicate sites still exist (otherwise the fix would
// have silently dropped the outcome/accepted filtering).
check('S3: outcome-tab filter still uses pdd.status_id',
    str_contains($indexBody, 'pdd.status_id = ('));
check('S4: accepted-tab partition still excludes outcome statuses via pdd.status_id',
    str_contains($indexBody, 'pdd.status_id IS NULL OR pdd.status_id NOT IN'));

// fetchTabCounts already joined pdd before the fix — make sure it still does,
// so badge counts and list counts cannot drift.
$fetchTabStart = strpos($source, 'private function fetchTabCounts');
$fetchTabEnd   = strpos($source, 'private function fetchPlenaryDetails');
$ftBody = ($fetchTabStart !== false && $fetchTabEnd !== false && $fetchTabEnd > $fetchTabStart)
    ? substr($source, $fetchTabStart, $fetchTabEnd - $fetchTabStart)
    : '';
check('S5: fetchTabCounts() still joins plenary_document_details pdd',
    str_contains($ftBody, 'JOIN plenary_document_details pdd'));

// =====================================================================
// GROUP F — Functional: the exact controller SQL runs against the live
//          schema without "Unknown column 'pdd.status_id'" and filters
//          documents by the correct status.
// =====================================================================
echo PHP_EOL . "Group F: Controller SQL against live schema" . PHP_EOL;

/**
 * Re-issue the EXACT COUNT(*) SQL the controller's index() now emits, with
 * the same WHERE-construction logic, for a given view slug.
 */
function countForView(PDO $pdo, int $roleId, int $userId, string $view, string $search = ''): int
{
    $where  = ['da.assigned_to_role_id = ?', "da.phase = 'PLENARY'"];
    $params = [$roleId];
    $isOutcomeView = isset(OUTCOME_TABS[$view]);

    if ($view === 'inbox') {
        $where[] = "da.decision = 'PENDING'";
        $where[] = 'da.completed_at IS NULL';
        $where[] = '(da.assigned_to_user_id IS NULL OR da.assigned_to_user_id = ?)';
        $where[] = 'da.accepted_by IS NULL';
        $params[] = $userId;
    } elseif ($isOutcomeView) {
        $where[] = "da.decision = 'ACCEPTED'";
        $where[] = 'da.completed_at IS NULL';
        $where[] = '(da.accepted_by = ? OR da.assigned_to_user_id = ?)';
        $params[] = $userId;
        $params[] = $userId;
        $where[]  = 'pdd.status_id = (
            SELECT id FROM document_statuses
            WHERE name = ? AND is_active = 1 AND is_deleted = 0
            LIMIT 1
        )';
        $params[] = OUTCOME_TABS[$view];
    } else { // accepted
        $where[] = "da.decision = 'ACCEPTED'";
        $where[] = 'da.completed_at IS NULL';
        $where[] = '(da.accepted_by = ? OR da.assigned_to_user_id = ?)';
        $params[] = $userId;
        $params[] = $userId;
        $where[] = '(' . ACCEPTED_STILL_PROCESSING . ')';
        $where[] = '(pdd.status_id IS NULL OR pdd.status_id NOT IN (' . OUTCOME_STATUS_IDS_SQL . '))';
    }

    if ($search !== '') {
        $where[]  = '(d.tracking_number LIKE ? OR d.subject_matter LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    $whereClause = implode(' AND ', $where);

    // Exact COUNT(*) SQL emitted by the controller (post-fix).
    $sql = "
        SELECT COUNT(*) AS cnt
        FROM document_assignments da
        INNER JOIN documents d ON d.id = da.document_id
        LEFT  JOIN plenary_document_details pdd ON pdd.document_id = d.id
        WHERE {$whereClause}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

/** Re-issue the EXACT list SELECT SQL the controller's index() now emits. */
function listForView(PDO $pdo, int $roleId, int $userId, string $view, string $search = ''): array
{
    $where  = ['da.assigned_to_role_id = ?', "da.phase = 'PLENARY'"];
    $params = [$roleId];
    $isOutcomeView = isset(OUTCOME_TABS[$view]);

    if ($view === 'inbox') {
        $where[] = "da.decision = 'PENDING'";
        $where[] = 'da.completed_at IS NULL';
        $where[] = '(da.assigned_to_user_id IS NULL OR da.assigned_to_user_id = ?)';
        $where[] = 'da.accepted_by IS NULL';
        $params[] = $userId;
    } elseif ($isOutcomeView) {
        $where[] = "da.decision = 'ACCEPTED'";
        $where[] = 'da.completed_at IS NULL';
        $where[] = '(da.accepted_by = ? OR da.assigned_to_user_id = ?)';
        $params[] = $userId;
        $params[] = $userId;
        $where[]  = 'pdd.status_id = (
            SELECT id FROM document_statuses
            WHERE name = ? AND is_active = 1 AND is_deleted = 0
            LIMIT 1
        )';
        $params[] = OUTCOME_TABS[$view];
    } else { // accepted
        $where[] = "da.decision = 'ACCEPTED'";
        $where[] = 'da.completed_at IS NULL';
        $where[] = '(da.accepted_by = ? OR da.assigned_to_user_id = ?)';
        $params[] = $userId;
        $params[] = $userId;
        $where[] = '(' . ACCEPTED_STILL_PROCESSING . ')';
        $where[] = '(pdd.status_id IS NULL OR pdd.status_id NOT IN (' . OUTCOME_STATUS_IDS_SQL . '))';
    }

    if ($search !== '') {
        $where[]  = '(d.tracking_number LIKE ? OR d.subject_matter LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    $whereClause = implode(' AND ', $where);

    // Exact list SELECT emitted by the controller (post-fix), with the joins
    // that are always present (status + type) — enough to validate filtering.
    $sql = "
        SELECT
            da.id AS assignment_id,
            d.id AS document_id,
            d.tracking_number
        FROM document_assignments da
        INNER JOIN documents        d   ON d.id  = da.document_id
        LEFT  JOIN plenary_document_details pdd ON pdd.document_id = d.id
        LEFT  JOIN document_statuses ds  ON ds.id = d.current_status_id
        LEFT  JOIN document_types    dt  ON dt.id = d.document_type_id
        WHERE {$whereClause}
        ORDER BY da.id DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN, 1));
}

// ── Fixture setup inside a transaction (rolled back at the end) ───────────
$pdo->beginTransaction();

try {
    // Lookups
    $dtRow = $pdo->query("SELECT id FROM document_types WHERE is_active=1 AND is_deleted=0 LIMIT 1")->fetch();
    $docTypeId = $dtRow ? (int) $dtRow['id'] : (int) $pdo->query("SELECT id FROM document_types LIMIT 1")->fetchColumn();

    $stRow = $pdo->query("SELECT id FROM source_types WHERE is_active=1 AND is_deleted=0 LIMIT 1")->fetch();
    $sourceTypeId = $stRow ? (int) $stRow['id'] : (int) $pdo->query("SELECT id FROM source_types LIMIT 1")->fetchColumn();

    $neutralRow = $pdo->query("
        SELECT id FROM document_statuses
        WHERE name <> 'Awaiting First Reading' AND is_active=1 AND is_deleted=0
        ORDER BY id ASC LIMIT 1
    ")->fetch();
    $neutralStatusId = (int) $neutralRow['id'];

    $plenaryRoleRow = $pdo->query("SELECT id FROM roles WHERE name='Plenary' AND is_active=1 AND is_deleted=0 LIMIT 1")->fetch();
    if (!$plenaryRoleRow) {
        echo "FATAL: 'Plenary' role missing — run migrations first." . PHP_EOL;
        $pdo->rollBack();
        exit(1);
    }
    $plenaryRoleId = (int) $plenaryRoleRow['id'];

    $pdo->exec("
        INSERT INTO user_accounts (username, email, password_hash, status, role_id)
        VALUES ('_plt_col_a', '_plt_col_a@test.local', 'x', 'active', {$plenaryRoleId})
    ");
    $userA = (int) $pdo->lastInsertId();
    $pdo->exec("
        INSERT INTO user_accounts (username, email, password_hash, status, role_id)
        VALUES ('_plt_col_b', '_plt_col_b@test.local', 'x', 'active', {$plenaryRoleId})
    ");
    $userB = (int) $pdo->lastInsertId();

    // Pre-decision statuses (seeded by migrations 065 / 069).
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

    // Helper: create an accepted Plenary document owned by $user.
    $seq = 930000;
    $created = [];
    $createAccepted = function (int $userId, int $statusId) use ($pdo, &$seq, $docTypeId, $sourceTypeId, $neutralStatusId, $plenaryRoleId): int {
        $seq++;
        $tn = 'PLC-' . str_pad((string) ($seq - 930000), 5, '0', STR_PAD_LEFT);
        $pdo->prepare("
            INSERT INTO documents
                (tracking_number, tracking_year, tracking_sequence, subject_matter,
                 document_type_id, source_type_id, current_status_id,
                 date_received, time_received, current_phase)
            VALUES (?, YEAR(CURDATE()), ?, 'regression doc', ?, ?, ?, CURDATE(), CURTIME(), 'PLENARY')
        ")->execute([$tn, $seq, $docTypeId, $sourceTypeId, $statusId]);

        $docId = (int) $pdo->lastInsertId();

        $pdo->prepare("
            INSERT INTO document_assignments
                (document_id, assigned_to_role_id, phase, assigned_by, decision,
                 received_at, accepted_at, accepted_by, assigned_to_user_id)
            VALUES (?, ?, 'PLENARY', ?, 'ACCEPTED', NOW(), NOW(), ?, ?)
        ")->execute([$docId, $plenaryRoleId, $userId, $userId, $userId]);

        // Mirror plenary_document_details.status_id = documents.current_status_id
        // (the invariant maintained by migration 070 + the controller writes).
        $pdo->prepare("
            INSERT INTO plenary_document_details (document_id, status_id, created_by, updated_by)
            VALUES (?, ?, ?, ?)
        ")->execute([$docId, $statusId, $userId, $userId]);

        return $docId;
    };

    // Documents in every outcome status + one On Process + one with no pdd row.
    $docDeferred   = $createAccepted($userA, $status['Deferred']);
    $docLayTable   = $createAccepted($userA, $status['Lay on the Table']);
    $docNoted      = $createAccepted($userA, $status['Noted']);
    $docRemanded   = $createAccepted($userA, $status['Remanded']);
    $docWithdrawn  = $createAccepted($userA, $status['Withdrawn']);
    $docOnProcess  = $createAccepted($userA, $status['On Process']);

    // Document with no plenary_document_details row (no pre-decision yet):
    $seq++;
    $tn = 'PLC-NOPDD';
    $pdo->prepare("
        INSERT INTO documents
            (tracking_number, tracking_year, tracking_sequence, subject_matter,
             document_type_id, source_type_id, current_status_id,
             date_received, time_received, current_phase)
        VALUES (?, YEAR(CURDATE()), ?, 'no pdd row', ?, ?, ?, CURDATE(), CURTIME(), 'PLENARY')
    ")->execute([$tn, $seq, $docTypeId, $sourceTypeId, $neutralStatusId]);
    $docNoPdd = (int) $pdo->lastInsertId();
    $pdo->prepare("
        INSERT INTO document_assignments
            (document_id, assigned_to_role_id, phase, assigned_by, decision,
             received_at, accepted_at, accepted_by, assigned_to_user_id)
        VALUES (?, ?, 'PLENARY', ?, 'ACCEPTED', NOW(), NOW(), ?, ?)
    ")->execute([$docNoPdd, $plenaryRoleId, $userA, $userA, $userA]);

    // ── F1: every view query runs without "Unknown column 'pdd.status_id'" ──
    $views = array_merge(['inbox', 'accepted'], array_keys(OUTCOME_TABS));
    foreach ($views as $v) {
        $err = null;
        try {
            $c = countForView($pdo, $plenaryRoleId, $userA, $v);
            $l = listForView($pdo, $plenaryRoleId, $userA, $v);
            check("F1.{$v}: query executes without 'Unknown column pdd.status_id'", true);
        } catch (PDOException $e) {
            check("F1.{$v}: query executes without 'Unknown column pdd.status_id'", false, $e->getMessage());
        }
    }

    // ── F2: Deferred doc is in the deferred tab, NOT in accepted, NOT in others
    $deferredList = listForView($pdo, $plenaryRoleId, $userA, 'deferred');
    check('F2a: Deferred doc appears in the deferred tab', in_array($docDeferred, $deferredList, true));
    $acceptedList = listForView($pdo, $plenaryRoleId, $userA, 'accepted');
    check('F2b: Deferred doc is excluded from the accepted tab', !in_array($docDeferred, $acceptedList, true));
    foreach (['lay-on-the-table', 'noted', 'remanded', 'withdrawn'] as $other) {
        $otherList = listForView($pdo, $plenaryRoleId, $userA, $other);
        check("F2c: Deferred doc is NOT in the '{$other}' tab", !in_array($docDeferred, $otherList, true));
    }

    // ── F3: each outcome doc is only in its own tab
    $map = [
        'lay-on-the-table' => $docLayTable,
        'noted'            => $docNoted,
        'remanded'         => $docRemanded,
        'withdrawn'        => $docWithdrawn,
    ];
    foreach ($map as $slug => $docId) {
        $own  = listForView($pdo, $plenaryRoleId, $userA, $slug);
        $acc  = listForView($pdo, $plenaryRoleId, $userA, 'accepted');
        check("F3.{$slug}: doc appears in its own tab", in_array($docId, $own, true));
        check("F3.{$slug}b: doc is excluded from accepted", !in_array($docId, $acc, true));
        // Not in any other outcome tab
        foreach (array_keys(OUTCOME_TABS) as $other) {
            if ($other === $slug) {
                continue;
            }
            $otherList = listForView($pdo, $plenaryRoleId, $userA, $other);
            check("F3.{$slug}c: doc is NOT in the '{$other}' tab", !in_array($docId, $otherList, true));
        }
    }

    // ── F4: On Process doc stays in accepted, not in any outcome tab
    foreach (array_keys(OUTCOME_TABS) as $slug) {
        $otherList = listForView($pdo, $plenaryRoleId, $userA, $slug);
        check("F4.{$slug}: On Process doc is NOT in the '{$slug}' tab", !in_array($docOnProcess, $otherList, true));
    }
    $acc2 = listForView($pdo, $plenaryRoleId, $userA, 'accepted');
    check('F4b: On Process doc stays in accepted', in_array($docOnProcess, $acc2, true));

    // ── F5: doc with no pdd row stays in accepted (pdd.status_id IS NULL branch)
    check('F5: doc with no plenary_document_details stays in accepted', in_array($docNoPdd, $acc2, true));

    // ── F6: count == list length for every view (badges never drift from list)
    foreach ($views as $v) {
        $c = countForView($pdo, $plenaryRoleId, $userA, $v);
        $l = count(listForView($pdo, $plenaryRoleId, $userA, $v));
        check("F6.{$v}: count query equals list length ({$c} vs {$l})", $c === $l);
    }

    // ── F7: ownership — another user sees none of userA's outcome/accepted rows
    $bDeferred = listForView($pdo, $plenaryRoleId, $userB, 'deferred');
    $bAccepted = listForView($pdo, $plenaryRoleId, $userB, 'accepted');
    check('F7a: another user sees no deferred rows (ownership)', !in_array($docDeferred, $bDeferred, true));
    check('F7b: another user sees no accepted rows (ownership)', !in_array($docOnProcess, $bAccepted, true));

    // ── F8: search narrows a tab to the matching document
    $searched = listForView($pdo, $plenaryRoleId, $userA, 'deferred', 'regression doc');
    // Every outcome doc has subject 'regression doc', so search should still
    // find the deferred one but we also verify the count query matches.
    $searchCount = countForView($pdo, $plenaryRoleId, $userA, 'deferred', 'regression doc');
    check('F8: search filter applies on outcome tab (count == list)', $searchCount === count($searched));
    check('F8b: Deferred doc still matches the search', in_array($docDeferred, $searched, true));

} finally {
    $pdo->rollBack(); // zero permanent DB changes
}

// ── Summary ────────────────────────────────────────────────────────────────
echo PHP_EOL;
$total = $passed + $failed;
echo "Results: {$passed}/{$total} passed" . ($failed > 0 ? ", {$failed} FAILED" : '') . PHP_EOL;
exit($failed > 0 ? 1 : 0);

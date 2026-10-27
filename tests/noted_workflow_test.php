<?php
/**
 * Test: Noted workflow — Admin Inbox, SP Secretary Inbox, and Admin intake
 *
 * Verifies the rule that the Noted action is available for EVERY document
 * type in both the Admin and SP Secretary processing flows, while the
 * Communication Category is required (and persisted) only for documents
 * whose type is Communication.
 *
 * Covers:
 *   Group 1 — Admin and SP Secretary can mark each supported document type
 *             as Noted (terminal status + assignment decision).
 *   Group 2 — Communication documents still use Communication Categories
 *             (valid required, missing/invalid/inactive rejected).
 *   Group 3 — Non-Communication documents neither require nor accept a
 *             Communication Category (stray values are discarded, not saved).
 *   Group 4 — Unauthorized users and invalid status transitions are rejected.
 *   Group 5 — Source-level invariants: the old Communication-only gate is
 *             gone and the Noted action is wired for both roles.
 *
 * Strategy
 * --------
 * - Follows the project's existing test convention: resolve real seeded
 *   lookups, mirror the exact SQL the controllers issue, and assert the
 *   resulting state.
 * - All database work runs inside an outer transaction that is always rolled
 *   back, so the test has zero permanent side-effects. Each simulated action
 *   uses a SAVEPOINT so that a rejected action is rolled back exactly the way
 *   the controller's own transaction would roll back on exception.
 *
 * Run with:  php tests/noted_workflow_test.php
 */

declare(strict_types=1);

// Real application helpers (client_ip(), audit_log(), etc.) — included before
// any output so session_start() inside app.php stays clean.
if (!function_exists('client_ip')) {
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
function section(string $title): void {
    echo PHP_EOL . $title . PHP_EOL;
}

// ── Helpers ────────────────────────────────────────────────────────────────

/** Insert a minimal document in the given phase. Returns the document id. */
function createDoc(PDO $pdo, int $docTypeId, int $sourceTypeId, int $statusId, string $phase): int
{
    static $seq = 950000;
    $seq++;
    $tn = 'NTS-' . str_pad((string) ($seq - 950000), 5, '0', STR_PAD_LEFT);

    $pdo->prepare("
        INSERT INTO documents
            (tracking_number, tracking_year, tracking_sequence,
             subject_matter, document_type_id, source_type_id,
             current_status_id, date_received, time_received, current_phase)
        VALUES (?, YEAR(CURDATE()), ?, 'Noted workflow test', ?, ?, ?, CURDATE(), CURTIME(), ?)
    ")->execute([$tn, $seq, $docTypeId, $sourceTypeId, $statusId, $phase]);

    return (int) $pdo->lastInsertId();
}

/**
 * Insert an assignment. When $decision = 'ACCEPTED' ownership is stored in
 * both accepted_by and assigned_to_user_id (matching the Accept action).
 */
function createAssignment(PDO $pdo, int $docId, int $roleId, string $phase, int $userId, string $decision): int
{
    $acceptedAt  = $decision === 'ACCEPTED' ? 'NOW()' : 'NULL';
    $completedAt = in_array($decision, ['NOTED', 'COMPLETED', 'DECLINED'], true) ? 'NOW()' : 'NULL';
    $acceptedBy  = $decision === 'ACCEPTED' ? $userId : null;
    $ownedBy     = $decision === 'ACCEPTED' ? $userId : null;

    $pdo->prepare("
        INSERT INTO document_assignments
            (document_id, assigned_to_role_id, phase, assigned_by, decision,
             received_at, accepted_at, completed_at, accepted_by, assigned_to_user_id)
        VALUES (?, ?, ?, ?, ?, NOW(), {$acceptedAt}, {$completedAt}, ?, ?)
    ")->execute([$docId, $roleId, $phase, $userId, $decision, $acceptedBy, $ownedBy]);

    return (int) $pdo->lastInsertId();
}

/**
 * Mirror of AdminInboxController::doNoted() / SpsecInboxController::doNoted().
 *
 * The action runs inside a SAVEPOINT: a rejected action rolls back to the
 * savepoint, exactly as the controller's outer transaction would on exception.
 *
 * @return array{ok:bool,error?:string,category_name?:?string}
 */
function simulateNoted(
    PDO    $pdo,
    int    $docId,
    int    $assignId,
    int    $userId,
    string $docTypeName,
    int    $categoryId,
    string $phase,
    int    $routedToRoleId
): array {
    $pdo->exec('SAVEPOINT noted_action');

    // ── Validation (mirrors the controller, enforced server-side) ──────────
    $isCommunication = stripos($docTypeName, 'communication') !== false;
    $categoryName    = null;

    if ($isCommunication) {
        if ($categoryId <= 0) {
            $pdo->exec('ROLLBACK TO SAVEPOINT noted_action');
            return ['ok' => false, 'error' => 'category_required'];
        }
        $cat = $pdo->prepare(
            "SELECT id, name FROM communication_categories
              WHERE id = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1"
        );
        $cat->execute([$categoryId]);
        $row = $cat->fetch();
        if (!$row) {
            $pdo->exec('ROLLBACK TO SAVEPOINT noted_action');
            return ['ok' => false, 'error' => 'invalid_category'];
        }
        $categoryName = $row['name'];
    } else {
        // Non-Communication documents do not accept a communication category.
        $categoryId = 0;
    }

    // ── Resolve the 'Noted' document status ───────────────────────────────
    $notedRow      = $pdo->query(
        "SELECT id FROM document_statuses WHERE name = 'Noted' AND is_active = 1 AND is_deleted = 0 LIMIT 1"
    )->fetch();
    $notedStatusId = $notedRow ? (int) $notedRow['id'] : null;

    // ── Update document (category persisted only for Communication) ───────
    $pdo->prepare("
        UPDATE documents
        SET communication_category_id = ?,
            current_status_id         = ?,
            current_phase             = ?,
            current_owner_user_id     = NULL,
            updated_by                = ?
        WHERE id = ?
    ")->execute([
        $isCommunication ? $categoryId : null,
        $notedStatusId,
        $phase,
        $userId,
        $docId,
    ]);

    // ── Mark assignment NOTED (ownership compare-and-swap guard) ─────────
    $stmt = $pdo->prepare("
        UPDATE document_assignments
        SET decision     = 'NOTED',
            completed_at = NOW(),
            remarks      = ?
        WHERE id         = ?
          AND decision   = 'ACCEPTED'
          AND completed_at IS NULL
          AND (accepted_by = ? OR assigned_to_user_id = ?)
    ");
    $stmt->execute([null, $assignId, $userId, $userId]);

    if ($stmt->rowCount() === 0) {
        // The controller throws here and its transaction rolls back.
        $pdo->exec('ROLLBACK TO SAVEPOINT noted_action');
        return ['ok' => false, 'error' => 'not_owner_or_already_processed'];
    }

    // ── Route record (terminal: from and to are the acting phase) ─────────
    $notedRo   = $pdo->query(
        "SELECT id FROM routing_options WHERE name = 'Noted' AND is_active = 1 AND is_deleted = 0 LIMIT 1"
    )->fetch();
    $notedRoId = $notedRo ? (int) $notedRo['id'] : null;

    $pdo->prepare("
        INSERT INTO document_routes (
            document_id, from_phase, to_phase,
            routing_option_id, routed_by, routed_to_role_id, remarks
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $docId,
        $phase,
        $phase,
        $notedRoId,
        $userId,
        $routedToRoleId,
        $categoryName !== null
            ? 'Marked as Noted — Communication category: ' . $categoryName
            : 'Marked as Noted.',
    ]);

    // ── Workflow event ───────────────────────────────────────────────────
    $pdo->prepare("
        INSERT INTO document_events (
            document_id, event_type, phase, performed_by,
            from_status_id, to_status_id, remarks, metadata
        ) VALUES (?, 'DOCUMENT_NOTED', ?, ?, ?, ?, ?, ?)
    ")->execute([
        $docId,
        $phase,
        $userId,
        null,
        $notedStatusId,
        $categoryName !== null ? "Document noted. Category: {$categoryName}." : 'Document noted.',
        json_encode(['ip_address' => client_ip()]),
    ]);

    $pdo->exec('RELEASE SAVEPOINT noted_action');

    return ['ok' => true, 'category_name' => $categoryName];
}

/**
 * Mirror of the Noted branch of AdminReceiveDocumentController::submit()
 * (and the equivalent check inside DocumentService::receiveDocument()).
 *
 * @param  array $docTypes        Rows with id + name.
 * @param  array $activeCategoryIds
 * @return array{errors:string[],category_id:int}
 */
function intakeNotedValidation(array $docTypes, int $docTypeId, int $categoryId, array $activeCategoryIds): array
{
    $errors          = [];
    $selectedDocType = null;
    foreach ($docTypes as $dt) {
        if ((int) $dt['id'] === $docTypeId) {
            $selectedDocType = $dt;
            break;
        }
    }

    $isCommunicationDoc = $selectedDocType
        && stripos((string) $selectedDocType['name'], 'communication') !== false;

    if ($isCommunicationDoc) {
        if ($categoryId <= 0) {
            $errors[] = 'Communication Category is required when marking a Communication document as Noted.';
        } elseif (!in_array($categoryId, $activeCategoryIds, true)) {
            $errors[] = 'Invalid communication category selected.';
        }
    } else {
        // Non-Communication documents do not accept a communication category.
        $categoryId = 0;
    }

    return ['errors' => $errors, 'category_id' => $categoryId];
}

function docState(PDO $pdo, int $docId): array {
    $s = $pdo->prepare("SELECT current_status_id, current_phase, communication_category_id FROM documents WHERE id = ?");
    $s->execute([$docId]);
    return $s->fetch() ?: [];
}

function assignState(PDO $pdo, int $assignId): array {
    $s = $pdo->prepare("SELECT decision, completed_at FROM document_assignments WHERE id = ?");
    $s->execute([$assignId]);
    return $s->fetch() ?: [];
}

// ── Run ────────────────────────────────────────────────────────────────────
$pdo->beginTransaction();

try {
    // ── Lookups ───────────────────────────────────────────────────────────
    $row = $pdo->query(
        "SELECT id FROM source_types WHERE is_active = 1 AND is_deleted = 0 LIMIT 1"
    )->fetch();
    $sourceTypeId = $row ? (int) $row['id'] : 0;
    if ($sourceTypeId <= 0) {
        $pdo->exec("INSERT INTO source_types (name) VALUES ('_NotedTestSource')");
        $sourceTypeId = (int) $pdo->lastInsertId();
    }

    $row = $pdo->query(
        "SELECT id FROM document_statuses WHERE name = 'Pending' AND is_active = 1 AND is_deleted = 0 LIMIT 1"
    )->fetch();
    $pendingStatusId = $row ? (int) $row['id'] : 0;
    if ($pendingStatusId <= 0) {
        $pdo->exec("INSERT INTO document_statuses (name, badge_color, sort_order) VALUES ('Pending', '#6c757d', 0)");
        $pendingStatusId = (int) $pdo->lastInsertId();
    }

    $row = $pdo->query(
        "SELECT id FROM document_statuses WHERE name = 'Noted' AND is_active = 1 AND is_deleted = 0 LIMIT 1"
    )->fetch();
    $notedStatusId = $row ? (int) $row['id'] : 0;
    if ($notedStatusId <= 0) {
        $pdo->exec("INSERT INTO document_statuses (name, badge_color, sort_order) VALUES ('Noted', '#0d9488', 50)");
        $notedStatusId = (int) $pdo->lastInsertId();
    }

    // Routing option 'Noted' (id 6 when seeded).
    $notedRoRow = $pdo->query(
        "SELECT id FROM routing_options WHERE name = 'Noted' AND is_active = 1 AND is_deleted = 0 LIMIT 1"
    )->fetch();
    if (!$notedRoRow) {
        $pdo->exec("INSERT INTO routing_options (name, is_active, is_deleted) VALUES ('Noted', 1, 0)");
    }

    // Document types: Communication + at least two non-Communication types.
    $docTypes = $pdo->query(
        "SELECT id, name FROM document_types WHERE is_active = 1 AND is_deleted = 0 ORDER BY id ASC"
    )->fetchAll();

    $commDocTypeId = 0;
    $nonCommTypes  = [];
    foreach ($docTypes as $dt) {
        if (stripos((string) $dt['name'], 'communication') !== false && $commDocTypeId === 0) {
            $commDocTypeId = (int) $dt['id'];
        } elseif (stripos((string) $dt['name'], 'communication') === false) {
            $nonCommTypes[] = $dt;
        }
    }
    if ($commDocTypeId === 0) {
        $pdo->exec("INSERT INTO document_types (name, badge_color) VALUES ('Communication', '#EC4899')");
        $commDocTypeId = (int) $pdo->lastInsertId();
    }
    while (count($nonCommTypes) < 2) {
        $name = '_NotedTestNonComm' . count($nonCommTypes);
        $pdo->prepare("INSERT INTO document_types (name, badge_color) VALUES (?, '#64748B')")->execute([$name]);
        $nonCommTypes[] = ['id' => (int) $pdo->lastInsertId(), 'name' => $name];
    }
    $nonCommA = $nonCommTypes[0];
    $nonCommB = $nonCommTypes[1];

    // Roles
    $roleIdByKey = [];
    foreach (['Admin', 'SP Secretary', 'Plenary', 'Committee', 'Super Admin'] as $roleName) {
        $r = $pdo->prepare("SELECT id FROM roles WHERE name = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1");
        $r->execute([$roleName]);
        $row = $r->fetch();
        if (!$row) {
            $pdo->prepare("INSERT INTO roles (name, is_active, is_deleted) VALUES (?, 1, 0)")->execute([$roleName]);
            $row = ['id' => (int) $pdo->lastInsertId()];
        }
        $roleIdByKey[$roleName] = (int) $row['id'];
    }
    $adminRoleId = $roleIdByKey['Admin'];
    $spsecRoleId = $roleIdByKey['SP Secretary'];

    // Users (real rows so FKs hold)
    $mkUser = function (string $uname, string $email, int $roleId) use ($pdo): int {
        $pdo->prepare("
            INSERT INTO user_accounts (username, email, password_hash, status, role_id)
            VALUES (?, ?, 'x', 'active', ?)
        ")->execute([$uname, $email, $roleId]);
        return (int) $pdo->lastInsertId();
    };
    $userA = $mkUser('_noted_user_a', '_noted_a@test.local', $adminRoleId);
    $userB = $mkUser('_noted_user_b', '_noted_b@test.local', $adminRoleId);

    // Communication categories
    $activeCatRow = $pdo->query(
        "SELECT id, name FROM communication_categories WHERE is_active = 1 AND is_deleted = 0 ORDER BY id ASC LIMIT 1"
    )->fetch();
    if (!$activeCatRow) {
        $pdo->exec("INSERT INTO communication_categories (name, is_active, is_deleted) VALUES ('_NotedTestCategory', 1, 0)");
        $activeCategoryId = (int) $pdo->lastInsertId();
    } else {
        $activeCategoryId = (int) $activeCatRow['id'];
    }

    $row = $pdo->query(
        "SELECT id FROM communication_categories WHERE is_active = 0 AND is_deleted = 0 LIMIT 1"
    )->fetch();
    $inactiveCatId = $row ? (int) $row['id'] : 0;
    if ($inactiveCatId === 0) {
        $pdo->exec("INSERT INTO communication_categories (name, is_active, is_deleted) VALUES ('_NotedInactiveCat', 0, 0)");
        $inactiveCatId = (int) $pdo->lastInsertId();
    }

    $activeCategoryIds = array_map('intval', array_column(
        $pdo->query("SELECT id FROM communication_categories WHERE is_active = 1 AND is_deleted = 0")->fetchAll(),
        'id'
    ));

    // =====================================================================
    section('Group 1: Noted is available for every document type (Admin + SP Secretary)');

    // 1.1 Admin — Communication document
    $docId = createDoc($pdo, $commDocTypeId, $sourceTypeId, $pendingStatusId, 'ADMIN');
    $asgId = createAssignment($pdo, $docId, $adminRoleId, 'ADMIN', $userA, 'ACCEPTED');
    $r = simulateNoted($pdo, $docId, $asgId, $userA, 'Communication', $activeCategoryId, 'ADMIN', $adminRoleId);
    $st = docState($pdo, $docId);
    check('1.1: Admin can mark a Communication document as Noted', $r['ok'] === true);
    check('1.2: Communication Noted sets status to Noted', (int) $st['current_status_id'] === $notedStatusId);
    check('1.3: Communication Noted sets phase to ADMIN', $st['current_phase'] === 'ADMIN');
    check('1.4: Assignment decision becomes NOTED', assignState($pdo, $asgId)['decision'] === 'NOTED');

    // 1.5 Admin — first non-Communication type (no category)
    $docId = createDoc($pdo, (int) $nonCommA['id'], $sourceTypeId, $pendingStatusId, 'ADMIN');
    $asgId = createAssignment($pdo, $docId, $adminRoleId, 'ADMIN', $userA, 'ACCEPTED');
    $r = simulateNoted($pdo, $docId, $asgId, $userA, $nonCommA['name'], 0, 'ADMIN', $adminRoleId);
    $st = docState($pdo, $docId);
    check('1.5: Admin can mark a non-Communication document as Noted (' . $nonCommA['name'] . ')', $r['ok'] === true);
    check('1.6: Non-Communication Noted sets status to Noted', (int) $st['current_status_id'] === $notedStatusId);
    check('1.7: Non-Communication Noted stores no communication category', $st['communication_category_id'] === null);

    // 1.8 Admin — second non-Communication type
    $docId = createDoc($pdo, (int) $nonCommB['id'], $sourceTypeId, $pendingStatusId, 'ADMIN');
    $asgId = createAssignment($pdo, $docId, $adminRoleId, 'ADMIN', $userA, 'ACCEPTED');
    $r = simulateNoted($pdo, $docId, $asgId, $userA, $nonCommB['name'], 0, 'ADMIN', $adminRoleId);
    check('1.8: Admin can mark another non-Communication type as Noted (' . $nonCommB['name'] . ')', $r['ok'] === true);

    // 1.9 SP Secretary — Communication document
    $docId = createDoc($pdo, $commDocTypeId, $sourceTypeId, $pendingStatusId, 'SP_SECRETARY');
    $asgId = createAssignment($pdo, $docId, $spsecRoleId, 'SP_SECRETARY', $userA, 'ACCEPTED');
    $r = simulateNoted($pdo, $docId, $asgId, $userA, 'Communication', $activeCategoryId, 'SP_SECRETARY', $spsecRoleId);
    $st = docState($pdo, $docId);
    check('1.9: SP Secretary can mark a Communication document as Noted', $r['ok'] === true);
    check('1.10: SPSEC Communication Noted sets phase to SP_SECRETARY', $st['current_phase'] === 'SP_SECRETARY');

    // 1.11 SP Secretary — non-Communication document
    $docId = createDoc($pdo, (int) $nonCommB['id'], $sourceTypeId, $pendingStatusId, 'SP_SECRETARY');
    $asgId = createAssignment($pdo, $docId, $spsecRoleId, 'SP_SECRETARY', $userA, 'ACCEPTED');
    $r = simulateNoted($pdo, $docId, $asgId, $userA, $nonCommB['name'], 0, 'SP_SECRETARY', $spsecRoleId);
    $st = docState($pdo, $docId);
    check('1.11: SP Secretary can mark a non-Communication document as Noted', $r['ok'] === true);
    check('1.12: SPSEC non-Communication Noted stores no category', $st['communication_category_id'] === null);

    // =====================================================================
    section('Group 2: Communication documents still use Communication Categories');

    $docId = createDoc($pdo, $commDocTypeId, $sourceTypeId, $pendingStatusId, 'ADMIN');
    $asgId = createAssignment($pdo, $docId, $adminRoleId, 'ADMIN', $userA, 'ACCEPTED');
    $r = simulateNoted($pdo, $docId, $asgId, $userA, 'Communication', $activeCategoryId, 'ADMIN', $adminRoleId);
    $st = docState($pdo, $docId);
    check('2.1: Communication Noted persists the selected category', (int) $st['communication_category_id'] === $activeCategoryId);

    $docId = createDoc($pdo, $commDocTypeId, $sourceTypeId, $pendingStatusId, 'ADMIN');
    $asgId = createAssignment($pdo, $docId, $adminRoleId, 'ADMIN', $userA, 'ACCEPTED');
    $r = simulateNoted($pdo, $docId, $asgId, $userA, 'Communication', 0, 'ADMIN', $adminRoleId);
    $st = docState($pdo, $docId);
    check('2.2: Communication Noted without a category is rejected', $r['ok'] === false && ($r['error'] ?? '') === 'category_required');
    check('2.3: Rejected Noted leaves the document status unchanged', (int) $st['current_status_id'] === $pendingStatusId);

    $docId = createDoc($pdo, $commDocTypeId, $sourceTypeId, $pendingStatusId, 'ADMIN');
    $asgId = createAssignment($pdo, $docId, $adminRoleId, 'ADMIN', $userA, 'ACCEPTED');
    $r = simulateNoted($pdo, $docId, $asgId, $userA, 'Communication', 999999999, 'ADMIN', $adminRoleId);
    check('2.4: Communication Noted with an unknown category is rejected', $r['ok'] === false && ($r['error'] ?? '') === 'invalid_category');

    $docId = createDoc($pdo, $commDocTypeId, $sourceTypeId, $pendingStatusId, 'ADMIN');
    $asgId = createAssignment($pdo, $docId, $adminRoleId, 'ADMIN', $userA, 'ACCEPTED');
    $r = simulateNoted($pdo, $docId, $asgId, $userA, 'Communication', $inactiveCatId, 'ADMIN', $adminRoleId);
    check('2.5: Communication Noted with an inactive category is rejected', $r['ok'] === false && ($r['error'] ?? '') === 'invalid_category');

    // =====================================================================
    section('Group 3: Non-Communication documents do not require/accept a category');

    $docId = createDoc($pdo, (int) $nonCommA['id'], $sourceTypeId, $pendingStatusId, 'ADMIN');
    $asgId = createAssignment($pdo, $docId, $adminRoleId, 'ADMIN', $userA, 'ACCEPTED');
    $r = simulateNoted($pdo, $docId, $asgId, $userA, $nonCommA['name'], 0, 'ADMIN', $adminRoleId);
    check('3.1: Non-Communication Noted succeeds with no category', $r['ok'] === true);

    // Stray category supplied for a non-Communication document must not persist.
    $docId = createDoc($pdo, (int) $nonCommA['id'], $sourceTypeId, $pendingStatusId, 'ADMIN');
    $asgId = createAssignment($pdo, $docId, $adminRoleId, 'ADMIN', $userA, 'ACCEPTED');
    $r = simulateNoted($pdo, $docId, $asgId, $userA, $nonCommA['name'], $activeCategoryId, 'ADMIN', $adminRoleId);
    $st = docState($pdo, $docId);
    check('3.2: Non-Communication Noted with a stray category still succeeds', $r['ok'] === true);
    check('3.3: Stray category is discarded (persisted as NULL)', $st['communication_category_id'] === null);

    // Intake validation mirror (AdminReceiveDocumentController::submit()).
    $v = intakeNotedValidation($docTypes, (int) $nonCommA['id'], 0, $activeCategoryIds);
    check('3.4: Intake — non-Communication Noted requires no category', $v['errors'] === []);
    $v = intakeNotedValidation($docTypes, (int) $nonCommA['id'], $activeCategoryId, $activeCategoryIds);
    check('3.5: Intake — stray category for non-Communication is reset to 0', $v['errors'] === [] && $v['category_id'] === 0);
    $v = intakeNotedValidation($docTypes, $commDocTypeId, 0, $activeCategoryIds);
    check('3.6: Intake — Communication Noted still requires a category', count($v['errors']) === 1);
    $v = intakeNotedValidation($docTypes, $commDocTypeId, $activeCategoryId, $activeCategoryIds);
    check('3.7: Intake — Communication Noted accepts a valid category', $v['errors'] === [] && $v['category_id'] === $activeCategoryId);

    // =====================================================================
    section('Group 4: Unauthorized users and invalid transitions are rejected');

    // 4.1 A different user cannot Note an assignment owned by someone else.
    $docId = createDoc($pdo, (int) $nonCommA['id'], $sourceTypeId, $pendingStatusId, 'ADMIN');
    $asgId = createAssignment($pdo, $docId, $adminRoleId, 'ADMIN', $userA, 'ACCEPTED');
    $r = simulateNoted($pdo, $docId, $asgId, $userB, $nonCommA['name'], 0, 'ADMIN', $adminRoleId);
    $st = docState($pdo, $docId);
    check('4.1: Non-owner cannot mark another user\'s document as Noted', $r['ok'] === false);
    check('4.2: Rejected non-owner leaves the document status unchanged', (int) $st['current_status_id'] === $pendingStatusId);
    check('4.3: Rejected non-owner leaves the assignment ACCEPTED', assignState($pdo, $asgId)['decision'] === 'ACCEPTED');

    // 4.4 A PENDING (not yet accepted) assignment cannot be Noted.
    $docId = createDoc($pdo, (int) $nonCommA['id'], $sourceTypeId, $pendingStatusId, 'ADMIN');
    $asgId = createAssignment($pdo, $docId, $adminRoleId, 'ADMIN', $userA, 'PENDING');
    $r = simulateNoted($pdo, $docId, $asgId, $userA, $nonCommA['name'], 0, 'ADMIN', $adminRoleId);
    check('4.4: PENDING assignment cannot be marked as Noted', $r['ok'] === false);
    check('4.5: PENDING assignment is untouched after a rejected Noted', assignState($pdo, $asgId)['decision'] === 'PENDING');

    // 4.6 An already-Noted (completed) assignment cannot be Noted twice.
    $docId = createDoc($pdo, (int) $nonCommA['id'], $sourceTypeId, $pendingStatusId, 'ADMIN');
    $asgId = createAssignment($pdo, $docId, $adminRoleId, 'ADMIN', $userA, 'NOTED');
    $r = simulateNoted($pdo, $docId, $asgId, $userA, $nonCommA['name'], 0, 'ADMIN', $adminRoleId);
    check('4.6: An already-Noted assignment cannot be re-processed', $r['ok'] === false);

    // 4.7 A Communication document on the SP Secretary side rejects a bad category.
    $docId = createDoc($pdo, $commDocTypeId, $sourceTypeId, $pendingStatusId, 'SP_SECRETARY');
    $asgId = createAssignment($pdo, $docId, $spsecRoleId, 'SP_SECRETARY', $userA, 'ACCEPTED');
    $r = simulateNoted($pdo, $docId, $asgId, $userB, 'Communication', $activeCategoryId, 'SP_SECRETARY', $spsecRoleId);
    check('4.7: SPSEC non-owner cannot mark a Communication document as Noted', $r['ok'] === false);

    // =====================================================================
    section('Group 5: Source-level invariants');

    $adminCtrlSrc  = (string) file_get_contents(__DIR__ . '/../app/controllers/Admin/AdminInboxController.php');
    $spsecCtrlSrc  = (string) file_get_contents(__DIR__ . '/../app/controllers/Spsec/SpsecInboxController.php');
    $intakeCtrlSrc = (string) file_get_contents(__DIR__ . '/../app/controllers/Admin/AdminReceiveDocumentController.php');
    $serviceSrc    = (string) file_get_contents(__DIR__ . '/../app/services/DocumentService.php');
    $adminViewSrc  = (string) file_get_contents(__DIR__ . '/../resources/views/admin/inbox/show.php');
    $spsecViewSrc  = (string) file_get_contents(__DIR__ . '/../resources/views/spsec/inbox/show.php');

    check('5.1: AdminInboxController exposes the noted action', str_contains($adminCtrlSrc, "'noted',"));
    check('5.2: SpsecInboxController exposes the noted action', str_contains($spsecCtrlSrc, "'noted',"));
    check('5.3: Admin doNoted no longer restricts Noted to Communication',
        !str_contains($adminCtrlSrc, 'only available for Communication'));
    check('5.4: SPSEC doNoted no longer restricts Noted to Communication',
        !str_contains($spsecCtrlSrc, 'only available for Communication'));
    check('5.5: Intake controller no longer restricts Noted to Communication',
        !str_contains($intakeCtrlSrc, 'only available for Communication'));
    check('5.6: DocumentService no longer restricts Noted to Communication',
        !str_contains($serviceSrc, 'The NOTED routing option is only available for Communication document types.'));
    check('5.7: Admin inbox view renders a Noted action button',
        str_contains($adminViewSrc, 'data-action="noted"'));
    check('5.8: SPSEC inbox view renders a Noted action button',
        str_contains($spsecViewSrc, 'data-action="noted"'));
    check('5.9: Admin inbox view gates the category field on Communication',
        str_contains($adminViewSrc, 'if ($isCommunication):'));
    check('5.10: SPSEC inbox view gates the category field on Communication',
        str_contains($spsecViewSrc, 'if ($isCommunication):'));

    // Role gate (mirrors RoleMiddleware).
    $roleGate = function (string $route): array {
        return match (true) {
            str_starts_with($route, 'master')     => ['Super Admin'],
            str_starts_with($route, 'receiving')  => ['Super Admin', 'Receiving Staff'],
            str_starts_with($route, 'admin')      => ['Super Admin', 'Admin'],
            str_starts_with($route, 'spsec')      => ['Super Admin', 'SP Secretary'],
            str_starts_with($route, 'plenary')    => ['Super Admin', 'Plenary'],
            str_starts_with($route, 'committee')  => ['Super Admin', 'Committee'],
            default                               => [],
        };
    };
    check('5.11: Admin routes allow the Admin role', in_array('Admin', $roleGate('admin/inbox/process'), true));
    check('5.12: Admin routes are closed to the SP Secretary role', !in_array('SP Secretary', $roleGate('admin/inbox/process'), true));
    check('5.13: SPSEC routes allow the SP Secretary role', in_array('SP Secretary', $roleGate('spsec/inbox/process'), true));
    check('5.14: SPSEC routes are closed to the Admin role', !in_array('Admin', $roleGate('spsec/inbox/process'), true));

} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack(); // zero permanent DB changes
    }
}

// ── Summary ────────────────────────────────────────────────────────────────
echo PHP_EOL;
$total = $passed + $failed;
echo "Results: {$passed}/{$total} passed" . ($failed > 0 ? ", {$failed} FAILED" : '') . PHP_EOL;
exit($failed > 0 ? 1 : 0);

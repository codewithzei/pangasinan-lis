<?php
/**
 * Test: Committee Inbox — Communication document actions
 *
 * Verifies the fix to the Committee Inbox workflow on
 * /committee/inbox/show?id=... for documents whose type is "Communication":
 *
 *   Group 1 — Server-side action restriction matrix (and source invariants).
 *   Group 2 — "Proceed to Communications": the document leaves the Committee
 *             Inbox, appears on the Committee Communications page with the
 *             correct status, and reuses the EXISTING committee_communications
 *             record (one row, no parallel workflow).
 *   Group 3 — Duplicate-processing protection (second submission rejected,
 *             no second communication record, DB-level UNIQUE guard present).
 *   Group 4 — "Return": the destination is the ACTUAL origin resolved from the
 *             routing history (Plenary / SP Secretary), never a silent Admin
 *             fallback; an undeterminable origin fails loudly with no mutation.
 *   Group 5 — Ownership and remarks guards on the new actions.
 *
 * Strategy
 * --------
 * Follows the project's existing test convention: resolve real seeded lookups,
 * mirror the exact SQL the controller issues, and assert the resulting state.
 * All database work happens inside an OUTER transaction that is always rolled
 * back, so the test has zero permanent side-effects. Each scenario group takes
 * a SAVEPOINT so it can be rolled back independently.
 *
 * Run with:  php tests/committee_inbox_communication_actions_test.php
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

// ── Counters / reporting ───────────────────────────────────────────────────
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

// ── Mirrors of the controller logic ────────────────────────────────────────

/**
 * Mirror of CommitteeInboxController's server-side action restriction.
 * (COMMUNICATION_ALLOWED_ACTIONS / COMMUNICATION_ONLY_ACTIONS)
 */
function action_allowed(string $action, string $documentTypeName): bool
{
    $communicationAllowed = ['accept', 'proceed_to_communications', 'return_to_origin'];
    $communicationOnly    = ['proceed_to_communications', 'return_to_origin'];
    $isCommunication      = $documentTypeName === 'Communication';

    if ($isCommunication && !in_array($action, $communicationAllowed, true)) {
        return false;
    }
    if (!$isCommunication && in_array($action, $communicationOnly, true)) {
        return false;
    }
    return true;
}

/** Mirror of the PENDING (inbox tab) visibility predicate. */
function visible_in_inbox(PDO $pdo, int $documentId, int $committeeRoleId, int $userId): bool
{
    $st = $pdo->prepare("
        SELECT COUNT(*)
        FROM document_assignments da
        INNER JOIN documents d ON da.document_id = d.id
        WHERE da.document_id         = ?
          AND da.assigned_to_role_id = ?
          AND da.phase               = 'COMMITTEE'
          AND da.decision            = 'PENDING'
          AND da.completed_at        IS NULL
          AND (da.assigned_to_user_id IS NULL OR da.assigned_to_user_id = ?)
          AND da.accepted_by         IS NULL
    ");
    $st->execute([$documentId, $committeeRoleId, $userId]);
    return (int) $st->fetchColumn() > 0;
}

/** Mirror of the ACCEPTED tab visibility predicate (excluding processed Comms). */
function visible_in_accepted(PDO $pdo, int $documentId, int $userId): bool
{
    $st = $pdo->prepare("
        SELECT COUNT(*)
        FROM document_assignments da
        INNER JOIN documents d ON da.document_id = d.id
        WHERE da.document_id = ?
          AND da.decision     = 'ACCEPTED'
          AND da.completed_at IS NULL
          AND (da.accepted_by = ? OR da.assigned_to_user_id = ?)
          AND (
              (SELECT dt_inner.name FROM document_types dt_inner WHERE dt_inner.id = d.document_type_id LIMIT 1)
                  NOT IN ('Complaint', 'Administrative Cases', 'Communication')
              OR (
                  (SELECT dt_inner.name FROM document_types dt_inner WHERE dt_inner.id = d.document_type_id LIMIT 1)
                      = 'Communication'
                  AND NOT EXISTS (
                      SELECT 1 FROM committee_communications ccomm
                      WHERE ccomm.document_id = da.document_id
                  )
              )
          )
    ");
    $st->execute([$documentId, $userId, $userId]);
    return (int) $st->fetchColumn() > 0;
}

/** Mirror of CommitteeCommunicationsController::index() row lookup. */
function comms_page_row(PDO $pdo, int $documentId, int $userId): array|false
{
    $st = $pdo->prepare("
        SELECT cc.id, cc.subject, ds.name AS status
        FROM committee_communications cc
        INNER JOIN documents         d  ON cc.document_id      = d.id
        LEFT  JOIN document_statuses ds ON d.current_status_id = ds.id
        WHERE cc.assigned_by   = ?
          AND cc.document_id   = ?
        LIMIT 1
    ");
    $st->execute([$userId, $documentId]);
    return $st->fetch() ?: false;
}

/** Mirror of CommitteeInboxController::resolveOrigin(). */
function mirror_resolve_origin(PDO $pdo, int $documentId): array
{
    $phaseRoleMap = [
        'RECEIVING'    => 'Receiving Staff',
        'ADMIN'        => 'Admin',
        'SP_SECRETARY' => 'SP Secretary',
        'PLENARY'      => 'Plenary',
    ];

    $phase  = null;
    $userId = 0;

    $routeStmt = $pdo->prepare("
        SELECT from_phase, routed_by
        FROM document_routes
        WHERE document_id = ?
          AND to_phase    = 'COMMITTEE'
          AND from_phase <> 'COMMITTEE'
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ");
    $routeStmt->execute([$documentId]);
    $route = $routeStmt->fetch();

    if ($route && isset($phaseRoleMap[$route['from_phase']])) {
        $phase  = (string) $route['from_phase'];
        $userId = (int) ($route['routed_by'] ?? 0);
    } else {
        $assignStmt = $pdo->prepare("
            SELECT phase, accepted_by, assigned_to_user_id
            FROM document_assignments
            WHERE document_id = ?
              AND phase      <> 'COMMITTEE'
            ORDER BY created_at DESC, id DESC
            LIMIT 1
        ");
        $assignStmt->execute([$documentId]);
        $prev = $assignStmt->fetch();

        if ($prev && isset($phaseRoleMap[$prev['phase']])) {
            $phase  = (string) $prev['phase'];
            $userId = (int) ($prev['assigned_to_user_id'] ?: ($prev['accepted_by'] ?: 0));
        }
    }

    if ($phase === null) {
        throw new RuntimeException(
            'The originating office for this document could not be determined from its routing history, '
            . 'so it cannot be returned. Please coordinate with the System Administrator.'
        );
    }

    if ($userId > 0) {
        $userStmt = $pdo->prepare(
            "SELECT id FROM user_accounts WHERE id = ? AND status = 'active' AND is_deleted = 0 LIMIT 1"
        );
        $userStmt->execute([$userId]);
        if (!$userStmt->fetch()) {
            $userId = 0;
        }
    }

    return [$phase, $userId];
}

/**
 * Mirror of CommitteeInboxController::process() + the two new action methods.
 *
 * @return array{ok:bool,error:?string,comm_id:?int,origin_phase:?string}
 */
function mirror_process(
    PDO $pdo,
    string $action,
    int $documentId,
    int $committeeRoleId,
    int $userId,
    string $remarks
): array {
    $result = ['ok' => false, 'error' => null, 'comm_id' => null, 'origin_phase' => null];

    // ── Type guard (process(), before the transaction) ────────────────────
    $tStmt = $pdo->prepare("
        SELECT dt.name
        FROM documents d
        LEFT JOIN document_types dt ON dt.id = d.document_type_id
        WHERE d.id = ?
        LIMIT 1
    ");
    $tStmt->execute([$documentId]);
    $typeName = (string) ($tStmt->fetchColumn() ?: '');

    if (!action_allowed($action, $typeName)) {
        $result['error'] = 'type-restriction';
        return $result;
    }

    // ── Assignment resolution (process()) ─────────────────────────────────
    if ($action === 'accept') {
        $aStmt = $pdo->prepare("
            SELECT *
            FROM document_assignments
            WHERE document_id         = ?
              AND assigned_to_role_id = ?
              AND decision            = 'PENDING'
              AND completed_at        IS NULL
              AND accepted_by         IS NULL
              AND (assigned_to_user_id IS NULL OR assigned_to_user_id = ?)
            ORDER BY id ASC
            LIMIT 1
        ");
        $aStmt->execute([$documentId, $committeeRoleId, $userId]);
    } else {
        $aStmt = $pdo->prepare("
            SELECT *
            FROM document_assignments
            WHERE document_id         = ?
              AND assigned_to_role_id = ?
              AND decision            = 'ACCEPTED'
              AND completed_at        IS NULL
              AND (accepted_by = ? OR assigned_to_user_id = ?)
            ORDER BY id ASC
            LIMIT 1
        ");
        $aStmt->execute([$documentId, $committeeRoleId, $userId, $userId]);
    }

    $assignment = $aStmt->fetch();
    if (!$assignment) {
        $result['error'] = 'no-ownership';
        return $result;
    }

    $dStmt = $pdo->prepare("SELECT * FROM documents WHERE id = ? LIMIT 1");
    $dStmt->execute([$documentId]);
    $document = $dStmt->fetch();
    if (!$document) {
        $result['error'] = 'no-document';
        return $result;
    }

    $assignmentId    = (int) $assignment['id'];
    $currentStatusId = (int) $document['current_status_id'];

    // ── proceed_to_communications (doProceedToCommunications) ─────────────
    if ($action === 'proceed_to_communications') {
        $dupStmt = $pdo->prepare("SELECT id FROM committee_communications WHERE document_id = ? LIMIT 1");
        $dupStmt->execute([$documentId]);
        if ($dupStmt->fetch()) {
            $result['error'] = 'duplicate-communication';
            return $result;
        }

        $statusStmt = $pdo->prepare(
            "SELECT id FROM document_statuses WHERE name = 'Communication In Progress'
             AND is_active = 1 AND is_deleted = 0 LIMIT 1"
        );
        $statusStmt->execute();
        $commStatusId = (int) $statusStmt->fetchColumn();

        $subject = trim((string) ($document['subject_matter'] ?? ''));
        if ($subject === '') {
            $subject = 'Communication — ' . (string) $document['tracking_number'];
        }
        $subject = mb_substr($subject, 0, 500);

        $pdo->prepare("
            INSERT INTO committee_communications (
                document_id, date_logged, subject, sender_details, notes, assigned_by
            ) VALUES (?, CURDATE(), ?, ?, ?, ?)
        ")->execute([
            $documentId,
            $subject,
            'Test sender details',
            $remarks !== '' ? $remarks : null,
            $userId,
        ]);
        $commId = (int) $pdo->lastInsertId();

        $pdo->prepare("UPDATE documents SET current_status_id = ?, updated_by = ? WHERE id = ?")
            ->execute([$commStatusId, $userId, $documentId]);

        $completeStmt = $pdo->prepare("
            UPDATE document_assignments
            SET decision     = 'COMPLETED',
                completed_at = NOW(),
                remarks      = ?
            WHERE id           = ?
              AND decision     = 'ACCEPTED'
              AND completed_at IS NULL
              AND (accepted_by = ? OR assigned_to_user_id = ?)
        ");
        $completeStmt->execute([
            $remarks !== '' ? $remarks : 'Proceeded to Communications workflow.',
            $assignmentId,
            $userId,
            $userId,
        ]);

        if ($completeStmt->rowCount() === 0) {
            $result['error'] = 'assignment-already-processed';
            return $result;
        }

        $committeeRole = (int) ($assignment['assigned_to_role_id'] ?? 0);
        $pdo->prepare("
            INSERT INTO document_routes (
                document_id, from_phase, to_phase, routed_by, routed_to_role_id, remarks
            ) VALUES (?, 'COMMITTEE', 'COMMITTEE', ?, ?, ?)
        ")->execute([
            $documentId,
            $userId,
            $committeeRole ?: null,
            'Proceeded to Committee Communications workflow.',
        ]);

        $pdo->prepare("
            INSERT INTO document_events (
                document_id, event_type, phase, performed_by,
                from_status_id, to_status_id, remarks, metadata
            ) VALUES (?, 'COMMITTEE_COMMUNICATION_CREATED', 'COMMITTEE', ?, ?, ?, ?, ?)
        ")->execute([
            $documentId,
            $userId,
            $currentStatusId ?: null,
            $commStatusId,
            "Communication record opened: {$subject}",
            json_encode(['comm_id' => $commId, 'source' => 'committee_inbox']),
        ]);

        $result['ok']      = true;
        $result['comm_id'] = $commId;
        return $result;
    }

    // ── return_to_origin (doReturnToOrigin) ───────────────────────────────
    if ($action === 'return_to_origin') {
        if ($remarks === '') {
            $result['error'] = 'remarks-required';
            return $result;
        }

        try {
            [$originPhase, $originUserId] = mirror_resolve_origin($pdo, $documentId);
        } catch (RuntimeException $e) {
            $result['error'] = $e->getMessage();
            return $result;
        }

        $phaseRoleMap = [
            'RECEIVING'    => 'Receiving Staff',
            'ADMIN'        => 'Admin',
            'SP_SECRETARY' => 'SP Secretary',
            'PLENARY'      => 'Plenary',
        ];
        $statusByPhase = [
            'RECEIVING'    => 'Pending',
            'ADMIN'        => 'Pending',
            'SP_SECRETARY' => 'Pending',
            'PLENARY'      => 'Returned to Plenary',
        ];

        $roleStmt = $pdo->prepare(
            "SELECT id FROM roles WHERE name = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1"
        );
        $roleStmt->execute([$phaseRoleMap[$originPhase]]);
        $originRoleId = (int) ($roleStmt->fetchColumn() ?: 0);
        if ($originRoleId <= 0) {
            $result['error'] = 'origin-role-missing';
            return $result;
        }

        $declineStmt = $pdo->prepare("
            UPDATE document_assignments
            SET decision       = 'DECLINED',
                declined_at    = NOW(),
                completed_at   = NOW(),
                decline_reason = ?,
                remarks        = ?
            WHERE id           = ?
              AND decision     = 'ACCEPTED'
              AND completed_at IS NULL
              AND (accepted_by = ? OR assigned_to_user_id = ?)
        ");
        $declineStmt->execute([$remarks, $remarks, $assignmentId, $userId, $userId]);

        if ($declineStmt->rowCount() === 0) {
            $result['error'] = 'assignment-already-processed';
            return $result;
        }

        $pdo->prepare("
            INSERT INTO document_assignments (
                document_id, assigned_to_role_id, phase,
                assigned_by, decision, received_at,
                accepted_by, assigned_to_user_id
            ) VALUES (?, ?, ?, ?, 'PENDING', NOW(), NULL, ?)
        ")->execute([
            $documentId,
            $originRoleId,
            $originPhase,
            $userId,
            $originUserId > 0 ? $originUserId : null,
        ]);

        $returnStatusStmt = $pdo->prepare(
            "SELECT id FROM document_statuses WHERE name = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1"
        );
        $returnStatusStmt->execute([$statusByPhase[$originPhase] ?? 'Pending']);
        $returnStatusId = (int) $returnStatusStmt->fetchColumn();

        $pdo->prepare("
            UPDATE documents
            SET current_phase         = ?,
                current_status_id     = ?,
                current_owner_user_id = NULL,
                updated_by            = ?
            WHERE id = ?
        ")->execute([$originPhase, $returnStatusId, $userId, $documentId]);

        $pdo->prepare("
            INSERT INTO document_routes (
                document_id, from_phase, to_phase, routed_by,
                routed_to_role_id, routed_to_user_id, remarks
            ) VALUES (?, 'COMMITTEE', ?, ?, ?, ?, ?)
        ")->execute([
            $documentId,
            $originPhase,
            $userId,
            $originRoleId,
            $originUserId > 0 ? $originUserId : null,
            'Returned by Committee to ' . $originPhase . ': ' . $remarks,
        ]);

        $pdo->prepare("
            INSERT INTO document_events (
                document_id, event_type, phase, performed_by,
                to_status_id, remarks, metadata
            ) VALUES (?, 'COMMITTEE_RETURNED_TO_ORIGIN', ?, ?, ?, ?, ?)
        ")->execute([
            $documentId,
            $originPhase,
            $userId,
            $returnStatusId,
            'Document returned to ' . $originPhase . ' for re-routing.',
            json_encode(['return_reason' => $remarks, 'origin_phase' => $originPhase]),
        ]);

        $result['ok']           = true;
        $result['origin_phase'] = $originPhase;
        return $result;
    }

    $result['error'] = 'unsupported-action-in-test';
    return $result;
}

// ── Fixture helpers ────────────────────────────────────────────────────────
function create_test_user(PDO $pdo, int $roleId, string $prefix): int
{
    $suffix = substr(uniqid('', true), -9);
    $st = $pdo->prepare("
        INSERT INTO user_accounts (username, email, password_hash, role_id, status, is_deleted)
        VALUES (?, ?, ?, ?, 'active', 0)
    ");
    $st->execute([
        $prefix . '_tst_' . $suffix,
        $prefix . '_tst_' . $suffix . '@example.test',
        password_hash('test', PASSWORD_BCRYPT),
        $roleId,
    ]);
    return (int) $pdo->lastInsertId();
}

function create_test_document(
    PDO $pdo,
    int $typeId,
    int $statusId,
    int $sourceTypeId,
    string $phase,
    string $subjectMatter,
    int $createdBy
): int {
    $sequence = random_int(900000, 989999);
    $tracking = 'T-' . date('ymd') . '-' . $sequence;   // ≤ 20 chars

    $st = $pdo->prepare("
        INSERT INTO documents (
            tracking_year, tracking_sequence, tracking_number,
            date_received, time_received,
            source_type_id, source_name, current_status_id, current_phase,
            subject_matter, document_type_id, created_by, updated_by
        ) VALUES (?, ?, ?, CURDATE(), '09:00:00', ?, 'Test Source', ?, ?, ?, ?, ?, ?)
    ");
    $st->execute([
        (int) date('Y'),
        $sequence,
        $tracking,
        $sourceTypeId,
        $statusId,
        $phase,
        $subjectMatter,
        $typeId,
        $createdBy,
        $createdBy,
    ]);
    return (int) $pdo->lastInsertId();
}

function create_test_assignment(
    PDO $pdo,
    int $documentId,
    int $roleId,
    string $phase,
    string $decision,
    ?int $assignedToUser,
    ?int $acceptedBy,
    int $assignedBy
): int {
    $st = $pdo->prepare("
        INSERT INTO document_assignments (
            document_id, assigned_to_role_id, phase, assigned_by,
            decision, received_at, accepted_at, accepted_by, assigned_to_user_id
        ) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?)
    ");
    $st->execute([
        $documentId,
        $roleId,
        $phase,
        $assignedBy,
        $decision,
        $decision === 'ACCEPTED' ? date('Y-m-d H:i:s') : null,
        $acceptedBy,
        $assignedToUser,
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * Mirror of CommitteeInboxController::doAccept() — the claim step that turns a
 * PENDING inbox assignment into an owned (ACCEPTED) one. The Communications
 * hand-off requires ownership, so the real flow is:
 *   pending inbox  →  Accept  →  Proceed to Communications / Return
 */
function mirror_accept(
    PDO $pdo,
    int $documentId,
    int $assignmentId,
    int $userId,
    int $committeeRoleId
): bool {
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
          AND (assigned_to_user_id IS NULL OR assigned_to_user_id = ?)
    ");
    $stmt->execute([$userId, $userId, $assignmentId, $committeeRoleId, $userId]);

    return $stmt->rowCount() > 0;
}

function create_test_route(
    PDO $pdo,
    int $documentId,
    string $fromPhase,
    string $toPhase,
    int $routedBy,
    ?int $toRoleId
): int {
    $st = $pdo->prepare("
        INSERT INTO document_routes (document_id, from_phase, to_phase, routed_by, routed_to_role_id, remarks)
        VALUES (?, ?, ?, ?, ?, 'test fixture')
    ");
    $st->execute([$documentId, $fromPhase, $toPhase, $routedBy, $toRoleId]);
    return (int) $pdo->lastInsertId();
}

// ── Resolve real seeded lookups ────────────────────────────────────────────
$roles = [];
foreach ($pdo->query("SELECT id, name FROM roles WHERE is_active = 1 AND is_deleted = 0")->fetchAll() as $row) {
    $roles[$row['name']] = (int) $row['id'];
}

$statuses = [];
foreach ($pdo->query("SELECT id, name FROM document_statuses WHERE is_active = 1 AND is_deleted = 0")->fetchAll() as $row) {
    $statuses[$row['name']] = (int) $row['id'];
}

$commTypeId = (int) ($pdo->query("SELECT id FROM document_types WHERE name = 'Communication' LIMIT 1")->fetchColumn() ?: 0);
$otherTypeId = (int) ($pdo->query("SELECT id FROM document_types WHERE name = 'Complaint' LIMIT 1")->fetchColumn() ?: 0);
if ($otherTypeId === 0) {
    $otherTypeId = (int) ($pdo->query("SELECT id FROM document_types WHERE name <> 'Communication' LIMIT 1")->fetchColumn() ?: 0);
}
$sourceTypeId = (int) ($pdo->query("SELECT id FROM source_types LIMIT 1")->fetchColumn() ?: 0);

$missing = [];
if ($commTypeId === 0)                       $missing[] = 'document_types.Communication';
if ($otherTypeId === 0)                      $missing[] = 'document_types (non-Communication)';
if ($sourceTypeId === 0)                     $missing[] = 'source_types';
if (!isset($statuses['Pending']))            $missing[] = 'document_statuses.Pending';
if (!isset($statuses['Communication In Progress'])) $missing[] = 'document_statuses.Communication In Progress';
if (!isset($statuses['Returned to Plenary']))       $missing[] = 'document_statuses.Returned to Plenary';
foreach (['Committee', 'Plenary', 'SP Secretary', 'Admin'] as $roleName) {
    if (!isset($roles[$roleName])) $missing[] = 'roles.' . $roleName;
}

if (!empty($missing)) {
    echo 'SKIP: required seed data missing — ' . implode(', ', $missing) . PHP_EOL;
    exit(0);
}

// ══════════════════════════════════════════════════════════════════════════
// Group 1 — Source-level invariants (no DB writes)
// ══════════════════════════════════════════════════════════════════════════
section('Group 1 — Server-side restriction + source invariants');

$controllerSrc = (string) file_get_contents(__DIR__ . '/../app/controllers/Committee/CommitteeInboxController.php');
$viewSrc       = (string) file_get_contents(__DIR__ . '/../resources/views/committee/inbox/show.php');
$migrationSrc  = (string) file_get_contents(__DIR__ . '/../database/migrations/076_committee_return_to_origin_event.php');

check('controller accepts the two new action keys', str_contains($controllerSrc, "'proceed_to_communications'")
    && str_contains($controllerSrc, "'return_to_origin'"));
check('controller defines COMMUNICATION_ALLOWED_ACTIONS', str_contains($controllerSrc, 'COMMUNICATION_ALLOWED_ACTIONS'));
check('controller dispatches proceed_to_communications', str_contains($controllerSrc, "case 'proceed_to_communications':"));
check('controller dispatches return_to_origin', str_contains($controllerSrc, "case 'return_to_origin':"));
check('controller rejects other actions on Communication docs',
    str_contains($controllerSrc, 'Communication documents can only proceed to the Communications workflow or be returned.'));
check('controller rejects Communication-only actions on other types',
    str_contains($controllerSrc, 'This action is only available for Communication documents.'));
check('controller resolves the origin from routing history', str_contains($controllerSrc, 'private function resolveOrigin'));
check('controller does not fall back to Admin when origin is unknown',
    str_contains($controllerSrc, 'could not be determined from its routing history')
    && str_contains($controllerSrc, 'Do not fall back') === false);
check('proceed reuses committee_communications (no parallel workflow)',
    str_contains($controllerSrc, 'INSERT INTO committee_communications'));
check('proceed reuses COMMITTEE_COMMUNICATION_CREATED', str_contains($controllerSrc, "'COMMITTEE_COMMUNICATION_CREATED'"));
check('return writes COMMITTEE_RETURNED_TO_ORIGIN', str_contains($controllerSrc, "'COMMITTEE_RETURNED_TO_ORIGIN'"));
check('migration 076 declares COMMITTEE_RETURNED_TO_ORIGIN', str_contains($migrationSrc, 'COMMITTEE_RETURNED_TO_ORIGIN'));

// Live ENUM check — the migration must actually have been applied.
$enumRow = $pdo->query("
    SELECT COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_events' AND COLUMN_NAME = 'event_type'
    LIMIT 1
")->fetchColumn();
check('document_events.event_type includes COMMITTEE_RETURNED_TO_ORIGIN',
    is_string($enumRow) && str_contains($enumRow, "'COMMITTEE_RETURNED_TO_ORIGIN'"));

check('view renders the Proceed to Communications action', str_contains($viewSrc, 'data-action="proceed_to_communications"'));
check('view renders the Return to origin action', str_contains($viewSrc, 'data-action="return_to_origin"'));
check('view branches the action set on the document type', str_contains($viewSrc, '<?php if ($isCommEligibleType): ?>'));
check('view no longer renders the passive create link',
    !str_contains($viewSrc, 'committee/communications/create?document_id='));

check('action matrix: Communication + endorse is blocked',  action_allowed('endorse', 'Communication') === false);
check('action matrix: Communication + return_to_admin blocked', action_allowed('return_to_admin', 'Communication') === false);
check('action matrix: Communication + proceed allowed',     action_allowed('proceed_to_communications', 'Communication') === true);
check('action matrix: Communication + return_to_origin allowed', action_allowed('return_to_origin', 'Communication') === true);
check('action matrix: Communication + accept allowed',      action_allowed('accept', 'Communication') === true);
check('action matrix: Complaint + endorse allowed',         action_allowed('endorse', 'Complaint') === true);
check('action matrix: Complaint + return_to_admin allowed', action_allowed('return_to_admin', 'Complaint') === true);
check('action matrix: Complaint + proceed blocked',         action_allowed('proceed_to_communications', 'Complaint') === false);
check('action matrix: Complaint + return_to_origin blocked',action_allowed('return_to_origin', 'Complaint') === false);

// ══════════════════════════════════════════════════════════════════════════
// Database-backed groups — outer transaction, always rolled back
// ══════════════════════════════════════════════════════════════════════════
$pdo->beginTransaction();

try {
    $committeeRoleId = $roles['Committee'];
    $plenaryRoleId   = $roles['Plenary'];
    $spsecRoleId     = $roles['SP Secretary'];

    $committeeUser = create_test_user($pdo, $committeeRoleId, 'comm');
    $otherCommUser = create_test_user($pdo, $committeeRoleId, 'comm2');
    $plenaryUser   = create_test_user($pdo, $plenaryRoleId, 'plen');
    $spsecUser     = create_test_user($pdo, $spsecRoleId, 'spsec');

    // ── Group 2 — Proceed to Communications ───────────────────────────────
    section('Group 2 — Proceed to Communications');
    $pdo->exec('SAVEPOINT g2');

    $docComm = create_test_document(
        $pdo, $commTypeId, $statuses['Pending'], $sourceTypeId,
        'COMMITTEE', 'Request for assistance from the Provincial Board', $committeeUser
    );
    $assignComm = create_test_assignment(
        $pdo, $docComm, $committeeRoleId, 'COMMITTEE', 'PENDING',
        null, null, $plenaryUser
    );

    check('Communication document starts visible in the pending Committee Inbox',
        visible_in_inbox($pdo, $docComm, $committeeRoleId, $committeeUser) === true);

    // Ownership is required for every non-accept action, so the Committee user
    // claims the document first (mirrors doAccept()).
    check('Communication document can be claimed by the Committee user',
        mirror_accept($pdo, $docComm, $assignComm, $committeeUser, $committeeRoleId) === true);
    check('Communication document is no longer available to claim by another user',
        visible_in_inbox($pdo, $docComm, $committeeRoleId, $otherCommUser) === false);
    check('claimed Communication document is visible in the Accepted tab',
        visible_in_accepted($pdo, $docComm, $committeeUser) === true);

    $proceed = mirror_process($pdo, 'proceed_to_communications', $docComm, $committeeRoleId, $committeeUser, '');
    check('proceed succeeds', $proceed['ok'] === true);

    $commRow = comms_page_row($pdo, $docComm, $committeeUser);
    check('communication record exists on the Communications page', $commRow !== false);
    check('communication record shows the correct status',
        $commRow !== false && $commRow['status'] === 'Communication In Progress');
    check('communication record carries the document subject',
        $commRow !== false && $commRow['subject'] === 'Request for assistance from the Provincial Board');

    $commCount = (int) $pdo->query("SELECT COUNT(*) FROM committee_communications WHERE document_id = {$docComm}")->fetchColumn();
    check('exactly ONE committee_communications row was created', $commCount === 1);

    $docState = $pdo->query("SELECT current_status_id, current_phase FROM documents WHERE id = {$docComm}")->fetch();
    check('document status is now "Communication In Progress"', (int) $docState['current_status_id'] === $statuses['Communication In Progress']);
    check('document phase stays COMMITTEE (Communications workflow continues)', $docState['current_phase'] === 'COMMITTEE');

    $assignState = $pdo->query("SELECT decision, completed_at FROM document_assignments WHERE id = {$assignComm}")->fetch();
    check('Committee assignment is COMPLETED', $assignState['decision'] === 'COMPLETED');
    check('Committee assignment has completed_at set', $assignState['completed_at'] !== null);

    check('document is NO LONGER visible in the Committee Inbox',
        visible_in_inbox($pdo, $docComm, $committeeRoleId, $committeeUser) === false);
    check('document is NO LONGER visible in the Accepted tab (already processed)',
        visible_in_accepted($pdo, $docComm, $committeeUser) === false);

    $eventCount = (int) $pdo->query("
        SELECT COUNT(*) FROM document_events
        WHERE document_id = {$docComm} AND event_type = 'COMMITTEE_COMMUNICATION_CREATED'
    ")->fetchColumn();
    check('COMMITTEE_COMMUNICATION_CREATED workflow event was recorded', $eventCount === 1);

    $routeCount = (int) $pdo->query("
        SELECT COUNT(*) FROM document_routes
        WHERE document_id = {$docComm} AND from_phase = 'COMMITTEE' AND to_phase = 'COMMITTEE'
          AND routed_by = {$committeeUser}
    ")->fetchColumn();
    check('COMMITTEE → COMMITTEE route entry was recorded', $routeCount === 1);

    // ── Group 3 — Duplicate-processing protection ─────────────────────────
    section('Group 3 — Duplicate-processing protection');
    $pdo->exec('SAVEPOINT g3');

    $again = mirror_process($pdo, 'proceed_to_communications', $docComm, $committeeRoleId, $committeeUser, '');
    check('second proceed is rejected', $again['ok'] === false);
    check('second proceed is rejected for lack of an active assignment',
        $again['error'] === 'no-ownership');

    $commCountAfter = (int) $pdo->query("SELECT COUNT(*) FROM committee_communications WHERE document_id = {$docComm}")->fetchColumn();
    check('no duplicate communication record was created', $commCountAfter === 1);

    $uniqIndex = (int) $pdo->query("
        SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'committee_communications'
          AND COLUMN_NAME = 'document_id' AND NON_UNIQUE = 0
    ")->fetchColumn();
    check('committee_communications.document_id still has a UNIQUE guard', $uniqIndex > 0);

    $pdo->exec('ROLLBACK TO SAVEPOINT g3');

    // ── Group 4 — Return to the actual origin ─────────────────────────────
    section('Group 4 — Return to the actual origin');

    // Scenario A — origin = PLENARY
    $pdo->exec('SAVEPOINT g4a');
    $docPlenary = create_test_document(
        $pdo, $commTypeId, $statuses['Pending'], $sourceTypeId,
        'COMMITTEE', 'Endorsed resolution from Plenary', $plenaryUser
    );
    create_test_assignment($pdo, $docPlenary, $plenaryRoleId, 'PLENARY', 'COMPLETED', $plenaryUser, $plenaryUser, $plenaryUser);
    create_test_route($pdo, $docPlenary, 'PLENARY', 'COMMITTEE', $plenaryUser, $committeeRoleId);
    $assignPlenary = create_test_assignment(
        $pdo, $docPlenary, $committeeRoleId, 'COMMITTEE', 'ACCEPTED',
        $committeeUser, $committeeUser, $plenaryUser
    );

    $returned = mirror_process($pdo, 'return_to_origin', $docPlenary, $committeeRoleId, $committeeUser, 'Missing committee report');
    check('return_to_origin succeeds for a Plenary-origin document', $returned['ok'] === true);
    check('resolved origin is PLENARY', $returned['origin_phase'] === 'PLENARY');

    $docPlenaryState = $pdo->query("SELECT current_phase, current_status_id FROM documents WHERE id = {$docPlenary}")->fetch();
    check('document phase returns to PLENARY (not ADMIN)', $docPlenaryState['current_phase'] === 'PLENARY');
    check('document status is "Returned to Plenary"',
        (int) $docPlenaryState['current_status_id'] === $statuses['Returned to Plenary']);

    $newPlenaryAssign = $pdo->query("
        SELECT assigned_to_role_id, phase, decision, assigned_to_user_id
        FROM document_assignments
        WHERE document_id = {$docPlenary} AND phase = 'PLENARY' AND decision = 'PENDING'
    ")->fetch();
    check('a fresh PENDING Plenary assignment was created', $newPlenaryAssign !== false);
    check('the Pending Plenary assignment targets the Plenary role',
        $newPlenaryAssign !== false && (int) $newPlenaryAssign['assigned_to_role_id'] === $plenaryRoleId);
    check('the Pending Plenary assignment is pre-assigned to the sender',
        $newPlenaryAssign !== false && (int) $newPlenaryAssign['assigned_to_user_id'] === $plenaryUser);

    $pendingAdmin = (int) $pdo->query("
        SELECT COUNT(*) FROM document_assignments
        WHERE document_id = {$docPlenary} AND phase = 'ADMIN' AND decision = 'PENDING'
    ")->fetchColumn();
    check('NO Pending Admin assignment was created', $pendingAdmin === 0);

    $assignPlenaryState = $pdo->query("SELECT decision, completed_at, accepted_by FROM document_assignments WHERE id = {$assignPlenary}")->fetch();
    check('Committee assignment is DECLINED', $assignPlenaryState['decision'] === 'DECLINED');
    check('Committee assignment has completed_at set', $assignPlenaryState['completed_at'] !== null);
    check('accepted_by is preserved on the declined assignment',
        (int) $assignPlenaryState['accepted_by'] === $committeeUser);

    check('returned document is NO LONGER visible in the Committee Inbox',
        visible_in_inbox($pdo, $docPlenary, $committeeRoleId, $committeeUser) === false);

    $returnRoute = $pdo->query("
        SELECT from_phase, to_phase, routed_to_role_id FROM document_routes
        WHERE document_id = {$docPlenary} AND from_phase = 'COMMITTEE'
        ORDER BY id DESC LIMIT 1
    ")->fetch();
    check('COMMITTEE → PLENARY route entry was recorded',
        $returnRoute !== false && $returnRoute['to_phase'] === 'PLENARY'
        && (int) $returnRoute['routed_to_role_id'] === $plenaryRoleId);

    $returnEvent = $pdo->query("
        SELECT phase FROM document_events
        WHERE document_id = {$docPlenary} AND event_type = 'COMMITTEE_RETURNED_TO_ORIGIN'
        ORDER BY id DESC LIMIT 1
    ")->fetch();
    check('COMMITTEE_RETURNED_TO_ORIGIN event was recorded in the PLENARY phase',
        $returnEvent !== false && $returnEvent['phase'] === 'PLENARY');

    $pdo->exec('ROLLBACK TO SAVEPOINT g4a');

    // Scenario B — origin = SP_SECRETARY
    $pdo->exec('SAVEPOINT g4b');
    $docSpsec = create_test_document(
        $pdo, $commTypeId, $statuses['Pending'], $sourceTypeId,
        'COMMITTEE', 'Referred communication from the SP Secretary', $spsecUser
    );
    create_test_route($pdo, $docSpsec, 'SP_SECRETARY', 'COMMITTEE', $spsecUser, $committeeRoleId);
    create_test_assignment($pdo, $docSpsec, $committeeRoleId, 'COMMITTEE', 'ACCEPTED', $committeeUser, $committeeUser, $spsecUser);

    $returnedB = mirror_process($pdo, 'return_to_origin', $docSpsec, $committeeRoleId, $committeeUser, 'Wrong committee');
    check('return_to_origin succeeds for an SP Secretary-origin document', $returnedB['ok'] === true);
    check('resolved origin is SP_SECRETARY', $returnedB['origin_phase'] === 'SP_SECRETARY');

    $docSpsecState = $pdo->query("SELECT current_phase, current_status_id FROM documents WHERE id = {$docSpsec}")->fetch();
    check('document phase returns to SP_SECRETARY', $docSpsecState['current_phase'] === 'SP_SECRETARY');
    check('document status returns to Pending', (int) $docSpsecState['current_status_id'] === $statuses['Pending']);

    $spsecAssignCount = (int) $pdo->query("
        SELECT COUNT(*) FROM document_assignments
        WHERE document_id = {$docSpsec} AND phase = 'SP_SECRETARY' AND decision = 'PENDING'
    ")->fetchColumn();
    check('a fresh PENDING SP Secretary assignment was created', $spsecAssignCount === 1);

    check('returned document is NO LONGER visible in the Committee Inbox (SP Secretary origin)',
        visible_in_inbox($pdo, $docSpsec, $committeeRoleId, $committeeUser) === false);

    $pdo->exec('ROLLBACK TO SAVEPOINT g4b');

    // Scenario C — undeterminable origin must fail loudly with no mutation
    $pdo->exec('SAVEPOINT g4c');
    $docUnknown = create_test_document(
        $pdo, $commTypeId, $statuses['Pending'], $sourceTypeId,
        'COMMITTEE', 'Document with no inbound routing history', $committeeUser
    );
    $assignUnknown = create_test_assignment(
        $pdo, $docUnknown, $committeeRoleId, 'COMMITTEE', 'ACCEPTED',
        $committeeUser, $committeeUser, $committeeUser
    );

    $returnedC = mirror_process($pdo, 'return_to_origin', $docUnknown, $committeeRoleId, $committeeUser, 'Cannot review');
    check('return_to_origin fails when the origin is undeterminable', $returnedC['ok'] === false);
    check('the failure message is explicit',
        is_string($returnedC['error']) && str_contains($returnedC['error'], 'could not be determined'));

    $unknownDocState = $pdo->query("SELECT current_phase FROM documents WHERE id = {$docUnknown}")->fetch();
    check('no mutation: document phase is untouched', $unknownDocState['current_phase'] === 'COMMITTEE');

    $unknownAssignState = $pdo->query("SELECT decision, completed_at FROM document_assignments WHERE id = {$assignUnknown}")->fetch();
    check('no mutation: the Committee assignment is still ACCEPTED and open',
        $unknownAssignState['decision'] === 'ACCEPTED' && $unknownAssignState['completed_at'] === null);

    $unknownRouteCount = (int) $pdo->query("
        SELECT COUNT(*) FROM document_routes WHERE document_id = {$docUnknown} AND from_phase = 'COMMITTEE'
    ")->fetchColumn();
    check('no mutation: no return route was written', $unknownRouteCount === 0);

    $pdo->exec('ROLLBACK TO SAVEPOINT g4c');

    // ── Group 5 — Ownership and remarks guards ────────────────────────────
    section('Group 5 — Ownership and remarks guards');
    $pdo->exec('SAVEPOINT g5');

    $docGuard = create_test_document(
        $pdo, $commTypeId, $statuses['Pending'], $sourceTypeId,
        'COMMITTEE', 'Guard scenario document', $committeeUser
    );
    create_test_route($pdo, $docGuard, 'PLENARY', 'COMMITTEE', $plenaryUser, $committeeRoleId);
    create_test_assignment($pdo, $docGuard, $committeeRoleId, 'COMMITTEE', 'ACCEPTED', $committeeUser, $committeeUser, $plenaryUser);

    $noRemarks = mirror_process($pdo, 'return_to_origin', $docGuard, $committeeRoleId, $committeeUser, '');
    check('return_to_origin without remarks is rejected', $noRemarks['error'] === 'remarks-required');

    $otherUser = mirror_process($pdo, 'return_to_origin', $docGuard, $committeeRoleId, $otherCommUser, 'Not mine');
    check('return_to_origin by a non-owner is rejected', $otherUser['error'] === 'no-ownership');

    $otherProceed = mirror_process($pdo, 'proceed_to_communications', $docGuard, $committeeRoleId, $otherCommUser, '');
    check('proceed_to_communications by a non-owner is rejected', $otherProceed['error'] === 'no-ownership');

    $docGuardState = $pdo->query("SELECT current_phase FROM documents WHERE id = {$docGuard}")->fetch();
    check('guard scenario left the document untouched', $docGuardState['current_phase'] === 'COMMITTEE');

    $pdo->exec('ROLLBACK TO SAVEPOINT g5');

    // Non-Communication document must not accept the Communications actions.
    $pdo->exec('SAVEPOINT g5b');
    $docOther = create_test_document(
        $pdo, $otherTypeId, $statuses['Pending'], $sourceTypeId,
        'COMMITTEE', 'Non-communication document', $committeeUser
    );
    create_test_assignment($pdo, $docOther, $committeeRoleId, 'COMMITTEE', 'ACCEPTED', $committeeUser, $committeeUser, $committeeUser);

    $otherProceed2 = mirror_process($pdo, 'proceed_to_communications', $docOther, $committeeRoleId, $committeeUser, '');
    check('proceed_to_communications on a non-Communication document is rejected',
        $otherProceed2['error'] === 'type-restriction');

    $otherReturn = mirror_process($pdo, 'return_to_origin', $docOther, $committeeRoleId, $committeeUser, 'reason');
    check('return_to_origin on a non-Communication document is rejected',
        $otherReturn['error'] === 'type-restriction');

    $otherCommCount = (int) $pdo->query("SELECT COUNT(*) FROM committee_communications WHERE document_id = {$docOther}")->fetchColumn();
    check('no communication record was created for a non-Communication document', $otherCommCount === 0);

    $pdo->exec('ROLLBACK TO SAVEPOINT g5b');

} catch (Throwable $e) {
    echo PHP_EOL . '  [FAIL] Unexpected exception: ' . $e->getMessage() . PHP_EOL;
    echo '         at ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
    $failed++;
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

// ── Summary ────────────────────────────────────────────────────────────────
echo PHP_EOL . str_repeat('═', 72) . PHP_EOL;
echo "  Passed: {$passed}    Failed: {$failed}" . PHP_EOL;
echo str_repeat('═', 72) . PHP_EOL;

exit($failed > 0 ? 1 : 0);

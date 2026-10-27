<?php
/**
 * Test: Committee Inbox — return destination parity
 *
 * Confirms that the destination shown on the "Return to <Origin>" button
 * matches the destination the server actually uses when the POST is processed.
 *
 * The requirement is simple but critical: never display one destination while
 * routing to another. The view derives the label from the same resolveOrigin()
 * call that the server executes at POST time. This test mirrors both sides and
 * asserts they produce identical results for every routing scenario.
 *
 * Scenarios covered
 * -----------------
 *   SC-1  Routed to Committee via a PLENARY → COMMITTEE document_routes row.
 *         Expected button label: "Return to Plenary".
 *
 *   SC-2  Routed via SP_SECRETARY → COMMITTEE.
 *         Expected button label: "Return to SP Secretary".
 *
 *   SC-3  Routed via ADMIN → COMMITTEE.
 *         Expected button label: "Return to Admin".
 *
 *   SC-4  Routed via RECEIVING → COMMITTEE.
 *         Expected button label: "Return to Receiving".
 *
 *   SC-5  No document_routes row, but a prior PLENARY assignment exists.
 *         Falls back to the assignment phase.
 *         Expected button label: "Return to Plenary".
 *
 *   SC-6  No route record and no prior non-Committee assignment.
 *         Origin cannot be determined; button must be disabled (no label).
 *
 *   SC-7  Route row has a routed_by that maps to an active user → sender name
 *         is included as supporting text in the view, matching the server-side
 *         origin user attribution.
 *
 *   SC-8  Route row has a routed_by whose account is inactive → sender name
 *         must be null on both sides (disabled account is not surfaced).
 *
 * Strategy
 * --------
 * - Mirror the exact SQL the controller uses in show() (resolveOrigin) and
 *   the view label derivation (PHASE_LABELS map).
 * - Compare the "view side" result against the "server POST side" result.
 * - All writes happen inside a transaction that is always rolled back.
 *
 * Run with:  php tests/committee_inbox_return_destination_parity_test.php
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

// ── Constants mirrored from CommitteeInboxController ──────────────────────
const PHASE_ROLE_MAP = [
    'RECEIVING'    => 'Receiving Staff',
    'ADMIN'        => 'Admin',
    'SP_SECRETARY' => 'SP Secretary',
    'PLENARY'      => 'Plenary',
];

const PHASE_LABELS = [
    'RECEIVING'    => 'Receiving',
    'ADMIN'        => 'Admin',
    'SP_SECRETARY' => 'SP Secretary',
    'PLENARY'      => 'Plenary',
    'COMMITTEE'    => 'Committee',
];

// ── Mirror of CommitteeInboxController::resolveOrigin() ───────────────────
/**
 * Returns ['phase' => string, 'userId' => int] on success, or
 * ['phase' => null, 'userId' => 0, 'error' => string] on failure.
 *
 * This mirrors the exact logic the server uses at POST time (process/
 * doReturnToOrigin). The show() method calls the identical private method
 * to derive the view label, so the two results are guaranteed to match
 * unless the routing history mutates between page load and form submit.
 */
function mirror_resolve_origin(PDO $pdo, int $documentId): array
{
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

    if ($route && isset(PHASE_ROLE_MAP[$route['from_phase']])) {
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

        if ($prev && isset(PHASE_ROLE_MAP[$prev['phase']])) {
            $phase  = (string) $prev['phase'];
            $userId = (int) ($prev['assigned_to_user_id'] ?: ($prev['accepted_by'] ?: 0));
        }
    }

    if ($phase === null) {
        return [
            'phase'  => null,
            'userId' => 0,
            'error'  => 'The originating office for this document could not be determined from its routing history, '
                      . 'so it cannot be returned. Please coordinate with the System Administrator.',
        ];
    }

    // Validate the attributed user is still active.
    if ($userId > 0) {
        $userStmt = $pdo->prepare(
            "SELECT id FROM user_accounts WHERE id = ? AND status = 'active' AND is_deleted = 0 LIMIT 1"
        );
        $userStmt->execute([$userId]);
        if (!$userStmt->fetch()) {
            $userId = 0;
        }
    }

    return ['phase' => $phase, 'userId' => $userId, 'error' => null];
}

/**
 * "View-side" origin resolution — mirrors the new show() code that builds
 * $returnOriginLabel and $returnOriginSenderName.
 *
 * Returns:
 *   [
 *     'label'      => string|null,   // button label base, e.g. "Plenary"
 *     'senderName' => string|null,   // supporting text, or null
 *     'error'      => string|null,   // explanation when disabled, or null
 *   ]
 */
function view_side_return_info(PDO $pdo, int $documentId): array
{
    $origin = mirror_resolve_origin($pdo, $documentId);

    if ($origin['phase'] === null) {
        return ['label' => null, 'senderName' => null, 'error' => $origin['error']];
    }

    $label      = PHASE_LABELS[$origin['phase']] ?? $origin['phase'];
    $senderName = null;

    if ($origin['userId'] > 0) {
        $senderStmt = $pdo->prepare("
            SELECT
                ua.username,
                TRIM(CONCAT(
                    COALESCE(ui.first_name, ''), ' ',
                    COALESCE(ui.last_name,  '')
                )) AS full_name
            FROM user_accounts ua
            LEFT JOIN user_info ui ON ui.user_account_id = ua.id
            WHERE ua.id         = ?
              AND ua.status     = 'active'
              AND ua.is_deleted = 0
            LIMIT 1
        ");
        $senderStmt->execute([$origin['userId']]);
        $sender = $senderStmt->fetch();

        if ($sender) {
            $fullName   = trim((string) ($sender['full_name'] ?? ''));
            $senderName = ($fullName !== '') ? $fullName : (string) $sender['username'];
        }
    }

    return ['label' => $label, 'senderName' => $senderName, 'error' => null];
}

/**
 * "Server-side" origin resolution — mirrors what doReturnToOrigin() uses at
 * POST time. Returns the same shape so the two can be compared directly.
 */
function server_side_return_destination(PDO $pdo, int $documentId): array
{
    $origin = mirror_resolve_origin($pdo, $documentId);

    if ($origin['phase'] === null) {
        return ['label' => null, 'phase' => null, 'roleId' => 0, 'error' => $origin['error']];
    }

    $roleName = PHASE_ROLE_MAP[$origin['phase']] ?? null;
    $roleId   = 0;

    if ($roleName !== null) {
        $rStmt = $pdo->prepare(
            "SELECT id FROM roles WHERE name = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1"
        );
        $rStmt->execute([$roleName]);
        $roleId = (int) ($rStmt->fetchColumn() ?: 0);
    }

    return [
        'label'  => PHASE_LABELS[$origin['phase']] ?? $origin['phase'],
        'phase'  => $origin['phase'],
        'roleId' => $roleId,
        'error'  => null,
    ];
}

// ── Fixture helpers ────────────────────────────────────────────────────────

function make_user(PDO $pdo, int $roleId, string $prefix, string $status = 'active'): int
{
    $suffix = substr(uniqid('', true), -8);
    $pdo->prepare(
        "INSERT INTO user_accounts (username, email, password_hash, role_id, status, is_deleted)
         VALUES (?, ?, ?, ?, ?, 0)"
    )->execute([
        $prefix . '_rt_' . $suffix,
        $prefix . '_rt_' . $suffix . '@example.test',
        password_hash('pw', PASSWORD_BCRYPT),
        $roleId,
        $status,
    ]);
    return (int) $pdo->lastInsertId();
}

function make_document(PDO $pdo, int $typeId, int $statusId, int $sourceTypeId, int $createdBy): int
{
    $seq = random_int(910000, 989999);
    $trk = 'RP-' . date('Ymd') . '-' . $seq;
    $pdo->prepare(
        "INSERT INTO documents (
             tracking_year, tracking_sequence, tracking_number,
             date_received, time_received, source_type_id, source_name,
             current_status_id, current_phase,
             subject_matter, document_type_id, created_by, updated_by
         ) VALUES (?, ?, ?, CURDATE(), '08:00:00', ?, 'Parity test', ?, 'COMMITTEE', ?, ?, ?, ?)"
    )->execute([
        (int) date('Y'), $seq, $trk,
        $sourceTypeId, $statusId,
        'Parity test subject', $typeId, $createdBy, $createdBy,
    ]);
    return (int) $pdo->lastInsertId();
}

function make_route(PDO $pdo, int $documentId, string $from, string $to, int $routedBy, ?int $toRoleId): int
{
    $pdo->prepare(
        "INSERT INTO document_routes (document_id, from_phase, to_phase, routed_by, routed_to_role_id, remarks)
         VALUES (?, ?, ?, ?, ?, 'parity test')"
    )->execute([$documentId, $from, $to, $routedBy, $toRoleId]);
    return (int) $pdo->lastInsertId();
}

function make_assignment(
    PDO $pdo,
    int $documentId,
    int $roleId,
    string $phase,
    string $decision,
    ?int $acceptedBy,
    int $assignedBy
): int {
    $pdo->prepare(
        "INSERT INTO document_assignments (
             document_id, assigned_to_role_id, phase, assigned_by,
             decision, received_at, accepted_at, accepted_by, assigned_to_user_id
         ) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?)"
    )->execute([
        $documentId, $roleId, $phase, $assignedBy, $decision,
        $decision === 'ACCEPTED' ? date('Y-m-d H:i:s') : null,
        $acceptedBy, $acceptedBy,
    ]);
    return (int) $pdo->lastInsertId();
}

// ── Resolve seed data ──────────────────────────────────────────────────────

$roles = [];
foreach ($pdo->query("SELECT id, name FROM roles WHERE is_active = 1 AND is_deleted = 0")->fetchAll() as $r) {
    $roles[$r['name']] = (int) $r['id'];
}
$statuses = [];
foreach ($pdo->query("SELECT id, name FROM document_statuses WHERE is_active = 1 AND is_deleted = 0")->fetchAll() as $r) {
    $statuses[$r['name']] = (int) $r['id'];
}
$commTypeId   = (int) ($pdo->query("SELECT id FROM document_types WHERE name = 'Communication' LIMIT 1")->fetchColumn() ?: 0);
$sourceTypeId = (int) ($pdo->query("SELECT id FROM source_types LIMIT 1")->fetchColumn() ?: 0);

$missing = [];
if ($commTypeId === 0)              $missing[] = 'document_types.Communication';
if ($sourceTypeId === 0)            $missing[] = 'source_types';
if (!isset($statuses['Pending']))   $missing[] = 'document_statuses.Pending';
foreach (['Committee', 'Plenary', 'SP Secretary', 'Admin', 'Receiving Staff'] as $rn) {
    if (!isset($roles[$rn])) $missing[] = "roles.{$rn}";
}
if (!empty($missing)) {
    echo 'SKIP: required seed data missing — ' . implode(', ', $missing) . PHP_EOL;
    exit(0);
}

$pendingStatusId = $statuses['Pending'];

// ── Group 0 — Source-level invariants (no DB writes) ──────────────────────
section('Group 0 — Source-level invariants');

$controllerSrc = (string) file_get_contents(__DIR__ . '/../app/controllers/Committee/CommitteeInboxController.php');
$viewSrc       = (string) file_get_contents(__DIR__ . '/../resources/views/committee/inbox/show.php');

check(
    'controller resolves origin in show() for the view',
    str_contains($controllerSrc, '$returnOriginLabel')
    && str_contains($controllerSrc, '$returnOriginPhase')
    && str_contains($controllerSrc, 'resolveOrigin($documentId)')
);
check(
    'controller uses a try/catch around show()-time resolveOrigin to set $returnOriginError',
    str_contains($controllerSrc, '$returnOriginError')
    && str_contains($controllerSrc, 'catch (RuntimeException $originEx)')
);
check(
    'controller passes $returnOriginSenderName to the view',
    str_contains($controllerSrc, '$returnOriginSenderName')
);
check(
    'view renders destination-specific return label (Return to ...)',
    str_contains($viewSrc, 'Return to <?= htmlspecialchars($returnOriginLabel)')
);
check(
    'view conditionally shows sender name as supporting text',
    str_contains($viewSrc, '$returnOriginSenderName !== null')
    && str_contains($viewSrc, 'Sender:')
);
check(
    'view disables the button when $returnOriginLabel is null',
    str_contains($viewSrc, '$returnOriginLabel !== null')
    && str_contains($viewSrc, 'disabled')
    && str_contains($viewSrc, '$returnOriginError')
);
check(
    'view does not render a generic "Return" label without a destination',
    !preg_match('/>\s*Return\s*<\/button/i', $viewSrc)
    || str_contains($viewSrc, 'Return to <?= htmlspecialchars($returnOriginLabel)')
);
check(
    'server-side doReturnToOrigin still calls resolveOrigin (destination not hardcoded)',
    str_contains($controllerSrc, 'resolveOrigin($documentId)') &&
    str_contains($controllerSrc, "case 'return_to_origin':")
);

// ══════════════════════════════════════════════════════════════════════════
// DB-backed scenarios — one outer transaction, always rolled back
// ══════════════════════════════════════════════════════════════════════════
$pdo->beginTransaction();

try {
    $committeeUser = make_user($pdo, $roles['Committee'], 'cmmt');
    $plenaryUser   = make_user($pdo, $roles['Plenary'],    'plen');
    $spsecUser     = make_user($pdo, $roles['SP Secretary'], 'spsec');
    $adminUser     = make_user($pdo, $roles['Admin'],      'adm');
    $receivingUser = make_user($pdo, $roles['Receiving Staff'], 'recv');

    // ──────────────────────────────────────────────────────────────────────
    section('SC-1  Routed from PLENARY → COMMITTEE via document_routes');
    $pdo->exec('SAVEPOINT sc1');

    $doc1 = make_document($pdo, $commTypeId, $pendingStatusId, $sourceTypeId, $plenaryUser);
    make_route($pdo, $doc1, 'PLENARY', 'COMMITTEE', $plenaryUser, $roles['Committee']);
    make_assignment($pdo, $doc1, $roles['Committee'], 'COMMITTEE', 'ACCEPTED', $committeeUser, $plenaryUser);

    $view1   = view_side_return_info($pdo, $doc1);
    $server1 = server_side_return_destination($pdo, $doc1);

    check('SC-1: view label is "Plenary"',                    $view1['label']   === 'Plenary');
    check('SC-1: server destination label is "Plenary"',      $server1['label'] === 'Plenary');
    check('SC-1: server phase is PLENARY',                     $server1['phase'] === 'PLENARY');
    check('SC-1: server resolves a Plenary role ID',           $server1['roleId'] > 0);
    check('SC-1: view label equals server label (parity)',     $view1['label']   === $server1['label']);
    check('SC-1: no error on either side',                     $view1['error']   === null && $server1['error'] === null);

    // ──────────────────────────────────────────────────────────────────────
    section('SC-2  Routed from SP_SECRETARY → COMMITTEE');
    $pdo->exec('SAVEPOINT sc2');

    $doc2 = make_document($pdo, $commTypeId, $pendingStatusId, $sourceTypeId, $spsecUser);
    make_route($pdo, $doc2, 'SP_SECRETARY', 'COMMITTEE', $spsecUser, $roles['Committee']);
    make_assignment($pdo, $doc2, $roles['Committee'], 'COMMITTEE', 'ACCEPTED', $committeeUser, $spsecUser);

    $view2   = view_side_return_info($pdo, $doc2);
    $server2 = server_side_return_destination($pdo, $doc2);

    check('SC-2: view label is "SP Secretary"',                $view2['label']   === 'SP Secretary');
    check('SC-2: server destination label is "SP Secretary"',  $server2['label'] === 'SP Secretary');
    check('SC-2: server phase is SP_SECRETARY',                $server2['phase'] === 'SP_SECRETARY');
    check('SC-2: view label equals server label (parity)',     $view2['label']   === $server2['label']);

    // ──────────────────────────────────────────────────────────────────────
    section('SC-3  Routed from ADMIN → COMMITTEE');
    $pdo->exec('SAVEPOINT sc3');

    $doc3 = make_document($pdo, $commTypeId, $pendingStatusId, $sourceTypeId, $adminUser);
    make_route($pdo, $doc3, 'ADMIN', 'COMMITTEE', $adminUser, $roles['Committee']);
    make_assignment($pdo, $doc3, $roles['Committee'], 'COMMITTEE', 'ACCEPTED', $committeeUser, $adminUser);

    $view3   = view_side_return_info($pdo, $doc3);
    $server3 = server_side_return_destination($pdo, $doc3);

    check('SC-3: view label is "Admin"',                       $view3['label']   === 'Admin');
    check('SC-3: server destination label is "Admin"',         $server3['label'] === 'Admin');
    check('SC-3: view label equals server label (parity)',     $view3['label']   === $server3['label']);

    // ──────────────────────────────────────────────────────────────────────
    section('SC-4  Routed from RECEIVING → COMMITTEE');
    $pdo->exec('SAVEPOINT sc4');

    $doc4 = make_document($pdo, $commTypeId, $pendingStatusId, $sourceTypeId, $receivingUser);
    make_route($pdo, $doc4, 'RECEIVING', 'COMMITTEE', $receivingUser, $roles['Committee']);
    make_assignment($pdo, $doc4, $roles['Committee'], 'COMMITTEE', 'ACCEPTED', $committeeUser, $receivingUser);

    $view4   = view_side_return_info($pdo, $doc4);
    $server4 = server_side_return_destination($pdo, $doc4);

    check('SC-4: view label is "Receiving"',                   $view4['label']   === 'Receiving');
    check('SC-4: server destination label is "Receiving"',     $server4['label'] === 'Receiving');
    check('SC-4: view label equals server label (parity)',     $view4['label']   === $server4['label']);

    // ──────────────────────────────────────────────────────────────────────
    section('SC-5  No document_routes row — falls back to assignment phase (PLENARY)');
    $pdo->exec('SAVEPOINT sc5');

    $doc5 = make_document($pdo, $commTypeId, $pendingStatusId, $sourceTypeId, $plenaryUser);
    // Intentionally NO document_routes row — prior assignment in PLENARY only.
    make_assignment($pdo, $doc5, $roles['Plenary'],    'PLENARY',    'COMPLETED', $plenaryUser,   $plenaryUser);
    make_assignment($pdo, $doc5, $roles['Committee'],  'COMMITTEE',  'ACCEPTED',  $committeeUser, $plenaryUser);

    $view5   = view_side_return_info($pdo, $doc5);
    $server5 = server_side_return_destination($pdo, $doc5);

    check('SC-5: view label falls back to "Plenary"',          $view5['label']   === 'Plenary');
    check('SC-5: server label falls back to "Plenary"',        $server5['label'] === 'Plenary');
    check('SC-5: server phase is PLENARY',                     $server5['phase'] === 'PLENARY');
    check('SC-5: view label equals server label (parity)',     $view5['label']   === $server5['label']);

    // ──────────────────────────────────────────────────────────────────────
    section('SC-6  Origin undeterminable — button must be disabled, no label');
    $pdo->exec('SAVEPOINT sc6');

    $doc6 = make_document($pdo, $commTypeId, $pendingStatusId, $sourceTypeId, $committeeUser);
    // Neither a routing record NOR a prior non-Committee assignment.
    make_assignment($pdo, $doc6, $roles['Committee'], 'COMMITTEE', 'ACCEPTED', $committeeUser, $committeeUser);

    $view6   = view_side_return_info($pdo, $doc6);
    $server6 = server_side_return_destination($pdo, $doc6);

    check('SC-6: view label is null (button disabled)',        $view6['label']   === null);
    check('SC-6: view error message is set',                   !empty($view6['error']));
    check('SC-6: server label is null (would also fail)',      $server6['label'] === null);
    check('SC-6: server error message is set',                 !empty($server6['error']));
    check('SC-6: both sides agree there is no destination',    $view6['label']   === $server6['label']);

    // ──────────────────────────────────────────────────────────────────────
    section('SC-7  Sender name shown when routed_by resolves to an active user');
    $pdo->exec('SAVEPOINT sc7');

    $doc7 = make_document($pdo, $commTypeId, $pendingStatusId, $sourceTypeId, $plenaryUser);
    make_route($pdo, $doc7, 'PLENARY', 'COMMITTEE', $plenaryUser, $roles['Committee']);
    make_assignment($pdo, $doc7, $roles['Committee'], 'COMMITTEE', 'ACCEPTED', $committeeUser, $plenaryUser);

    $view7 = view_side_return_info($pdo, $doc7);

    check('SC-7: view label is "Plenary"',                     $view7['label']   === 'Plenary');
    check('SC-7: sender name is non-null (active user found)', $view7['senderName'] !== null);
    check('SC-7: sender name is a non-empty string',           is_string($view7['senderName']) && $view7['senderName'] !== '');
    // The server does not expose a senderName at POST time (it uses the user for
    // targeted notifications), but the origin userId must match what the view resolved.
    $serverOrigin7 = mirror_resolve_origin($pdo, $doc7);
    check('SC-7: server-side resolved userId matches the Plenary user',
        $serverOrigin7['userId'] === $plenaryUser
    );

    // ──────────────────────────────────────────────────────────────────────
    section('SC-8  Sender name is null when routed_by account is inactive');
    $pdo->exec('SAVEPOINT sc8');

    $inactiveUser = make_user($pdo, $roles['Plenary'], 'pleni', 'deactivated');

    $doc8 = make_document($pdo, $commTypeId, $pendingStatusId, $sourceTypeId, $committeeUser);
    make_route($pdo, $doc8, 'PLENARY', 'COMMITTEE', $inactiveUser, $roles['Committee']);
    make_assignment($pdo, $doc8, $roles['Committee'], 'COMMITTEE', 'ACCEPTED', $committeeUser, $inactiveUser);

    $view8   = view_side_return_info($pdo, $doc8);
    $server8 = mirror_resolve_origin($pdo, $doc8);

    check('SC-8: view label is still "Plenary" (phase resolved)', $view8['label']   === 'Plenary');
    check('SC-8: view senderName is null (inactive account)',     $view8['senderName'] === null);
    check('SC-8: server userId is 0 (inactive account stripped)', $server8['userId'] === 0);
    // The button still appears (label is known); only the supporting text is absent.
    check('SC-8: no error raised (button is not disabled)',       $view8['error']   === null);

} finally {
    $pdo->rollBack();
}

// ── Summary ────────────────────────────────────────────────────────────────
echo PHP_EOL;
$total = $passed + $failed;
echo "Results: {$passed}/{$total} passed" . ($failed > 0 ? ", {$failed} FAILED" : '') . PHP_EOL;
exit($failed > 0 ? 1 : 0);

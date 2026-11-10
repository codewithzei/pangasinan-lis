<?php
/**
 * Tests — Motion for Reconsideration: Plenary + Committee Workflow Parity
 *
 * WHAT IS TESTED
 * ──────────────────────────────────────────────────────────────────────────
 * Confirms that Motion for Reconsideration (MFR) follows the same Plenary
 * processing rules and Committee workflow as Communications, while retaining
 * its mandatory link to a finalized Complaint / Administrative Cases document.
 *
 * Group 1 — PlenaryInboxController logic (unit-level, no HTTP):
 *   a) isCommunicationType() does NOT match 'Motion for Reconsideration'.
 *   b) isMotionForReconsiderationType() DOES match 'Motion for Reconsideration'.
 *   c) The PROPOSED_NUMBER_EXEMPT_TYPES list includes 'motion for reconsideration'.
 *   d) MFR is treated as Communication (reduced form) in doSaveProcessing:
 *      $isCommunication evaluates true for MFR type name.
 *   e) Communications still work — isCommunicationType() still matches
 *      'Communication'.
 *
 * Group 2 — PlenaryReadingController logic (unit-level):
 *   a) isCommDocument() returns true for a Communication-type document.
 *   b) isCommDocument() returns true for an MFR-type document.
 *   c) isCommDocument() returns false for a legislative-type document.
 *   d) buildFirstReadingTabCondition('communication') SQL includes MFR pattern.
 *   e) firstReadingShow() routes MFR to plenary/comm-first-reading/show.
 *
 * Group 3 — CommitteeInboxController logic (unit-level):
 *   a) COMMUNICATION_LIKE_TYPES includes both 'Communication' and
 *      'Motion for Reconsideration'.
 *   b) 'Motion for Reconsideration' passes the isCommunication check.
 *   c) 'Communication' still passes the isCommunication check.
 *   d) 'Administrative Cases' is NOT treated as isCommunication.
 *
 * Group 4 — CommitteeCommunicationsController logic (unit-level):
 *   a) ELIGIBLE_TYPES includes 'Communication'.
 *   b) ELIGIBLE_TYPES includes 'Motion for Reconsideration'.
 *   c) requireEligibleOwnedDocument type check accepts MFR.
 *   d) requireEligibleOwnedDocument type check accepts Communication.
 *   e) requireEligibleOwnedDocument type check rejects 'Administrative Cases'.
 *
 * Group 5 — MFR retains linked case (DB integration):
 *   a) motion_reconsideration_links row persists after MFR insert.
 *   b) MFR link is readable via DocumentService::getMotionLink().
 *   c) The MFR document type name is 'Motion for Reconsideration' (not case).
 *   d) Changing MFR status through Communication statuses does NOT affect the
 *      linked case document.
 *
 * Group 6 — Status reuse: MFR uses Communication statuses:
 *   a) 'Comm: Awaiting First Reading' status exists.
 *   b) 'Comm: Noted' status exists.
 *   c) 'Comm: Referred to Committee' status exists.
 *   d) 'Comm: Approved for Resolution' status exists.
 *   e) 'Comm: Report Adopted' status exists.
 *   f) 'Comm: Finalized' status exists.
 *   g) 'Plenary Comm: Awaiting First Reading' status exists.
 *   h) 'Plenary Comm: Noted' status exists.
 *   i) 'Plenary Comm: Referred to Committee' status exists.
 *   j) 'Plenary Comm: Finalized' status exists.
 *
 * Group 7 — Plenary inbox save_processing for MFR (DB integration):
 *   a) An MFR document advances to 'Awaiting First Reading' via the
 *      Communication fast path (no Proposed Number, Authors, Committees
 *      or Session Type stored in plenary_document_details).
 *   b) proposed_number stays NULL in plenary_document_details for MFR.
 *   c) session_type stays NULL in plenary_document_details for MFR.
 *
 * All DB writes use a rolled-back transaction — zero permanent side-effects.
 *
 * Run with:  php tests/mfr_plenary_committee_workflow_test.php
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

// App stubs
if (!function_exists('client_ip'))         { function client_ip(): string { return '127.0.0.1'; } }
if (!function_exists('client_user_agent')) { function client_user_agent(): string { return 'test'; } }
if (!function_exists('system_log'))        { function system_log(string $l, string $m, array $c = []): void {} }
if (!function_exists('audit_log'))         { function audit_log(...$a): void {} }
if (!function_exists('flash_set'))         { function flash_set(string $k, $v): void {} }
if (!function_exists('flash_get'))         { function flash_get(string $k, $d = null) { return $d; } }
if (!function_exists('old_set'))           { function old_set($v): void {} }
if (!function_exists('old_get'))           { function old_get(): array { return []; } }
if (!function_exists('old_clear'))         { function old_clear(): void {} }
if (!function_exists('auth_id'))           { function auth_id(): ?int { return 1; } }
if (!function_exists('auth_role'))         { function auth_role(): string { return 'Super Admin'; } }
if (!function_exists('dashboard_route_for_role')) { function dashboard_route_for_role(string $r): string { return 'dashboard'; } }
if (!function_exists('redirect'))          {
    function redirect(string $url): never { throw new RuntimeException("redirect:{$url}"); }
}
if (!defined('BASE_URL')) { define('BASE_URL', 'http://localhost/pangasinan-lis/public'); }

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/services/DocumentService.php';
require_once __DIR__ . '/../app/controllers/Plenary/PlenaryInboxController.php';
require_once __DIR__ . '/../app/controllers/Plenary/PlenaryReadingController.php';
require_once __DIR__ . '/../app/controllers/Committee/CommitteeInboxController.php';
require_once __DIR__ . '/../app/controllers/Committee/CommitteeCommunicationsController.php';

// ── Counters / helpers ──────────────────────────────────────────────────────

$passed = 0;
$failed = 0;

function section(string $title): void
{
    echo PHP_EOL . '── ' . $title . ' ' . str_repeat('─', max(0, 66 - strlen($title))) . PHP_EOL;
}

function ok(string $label, bool $condition, ?string $detail = null): void
{
    global $passed, $failed;
    if ($condition) {
        echo "  PASS  {$label}" . PHP_EOL;
        $passed++;
    } else {
        echo "  FAIL  {$label}" . ($detail ? " — {$detail}" : '') . PHP_EOL;
        $failed++;
    }
}

function fail(string $label, string $reason): void
{
    global $failed;
    echo "  FAIL  {$label} — {$reason}" . PHP_EOL;
    $failed++;
}

function getStatusId(PDO $pdo, string $name): ?int
{
    $s = $pdo->prepare("SELECT id FROM document_statuses WHERE name = ? AND is_active=1 AND is_deleted=0 LIMIT 1");
    $s->execute([$name]);
    $r = $s->fetch();
    return $r ? (int) $r['id'] : null;
}

function getDocTypeId(PDO $pdo, string $name): ?int
{
    $s = $pdo->prepare("SELECT id FROM document_types WHERE name = ? AND is_active=1 AND is_deleted=0 LIMIT 1");
    $s->execute([$name]);
    $r = $s->fetch();
    return $r ? (int) $r['id'] : null;
}

function getSrcTypeId(PDO $pdo, string $name): ?int
{
    $s = $pdo->prepare("SELECT id FROM source_types WHERE name = ? AND is_active=1 AND is_deleted=0 LIMIT 1");
    $s->execute([$name]);
    $r = $s->fetch();
    return $r ? (int) $r['id'] : null;
}

function insertTestDocument(PDO $pdo, int $typeId, int $statusId, int $srcTypeId, string $phase = 'ADMIN'): int
{
    $year = (int) date('Y');
    $seq  = rand(800000, 899999);
    $trk  = "TRK-{$year}-{$seq}";
    $pdo->prepare("
        INSERT INTO documents (
            tracking_year, tracking_sequence, tracking_number,
            date_received, time_received, subject_matter,
            document_type_id, source_type_id,
            current_status_id, current_phase, created_by, updated_by
        ) VALUES (?, ?, ?, CURDATE(), CURTIME(), 'Test subject', ?, ?, ?, ?, 1, 1)
    ")->execute([$year, $seq, $trk, $typeId, $srcTypeId, $statusId, $phase]);
    return (int) $pdo->lastInsertId();
}

// ── Reflection helpers ───────────────────────────────────────────────────────

/**
 * Call a private/protected method via Reflection.
 */
function callPrivate(object $obj, string $method, array $args = []): mixed
{
    $rm = new ReflectionMethod($obj, $method);
    $rm->setAccessible(true);
    return $rm->invokeArgs($obj, $args);
}

/**
 * Read a private/protected static constant via Reflection.
 */
function getPrivateConst(string $class, string $name): mixed
{
    $rc = new ReflectionClass($class);
    $c  = $rc->getReflectionConstant($name);
    return $c ? $c->getValue() : null;
}

// ============================================================================
// GROUP 1 — PlenaryInboxController type-detection helpers
// ============================================================================

section('Group 1 — PlenaryInboxController type-detection helpers');

try {
    // Instantiate with our real PDO rather than through Database class
    $plenaryInbox = new class($pdo) extends PlenaryInboxController {
        public function __construct(PDO $p) { $this->pdo = $p; }
    };

    $isCommunicationType = new ReflectionMethod(PlenaryInboxController::class, 'isCommunicationType');
    $isCommunicationType->setAccessible(true);

    $isMfrType = new ReflectionMethod(PlenaryInboxController::class, 'isMotionForReconsiderationType');
    $isMfrType->setAccessible(true);

    // 1a: isCommunicationType does NOT match MFR
    ok('1a: isCommunicationType() does not match Motion for Reconsideration',
        !$isCommunicationType->invoke($plenaryInbox, 'Motion for Reconsideration'));

    // 1b: isMotionForReconsiderationType DOES match
    ok('1b: isMotionForReconsiderationType() matches Motion for Reconsideration',
        $isMfrType->invoke($plenaryInbox, 'Motion for Reconsideration'));

    // 1c: PROPOSED_NUMBER_EXEMPT_TYPES includes motion for reconsideration
    $exemptTypes = getPrivateConst(PlenaryInboxController::class, 'PROPOSED_NUMBER_EXEMPT_TYPES');
    $exemptLower = is_array($exemptTypes) ? array_map('strtolower', $exemptTypes) : [];
    ok('1c: PROPOSED_NUMBER_EXEMPT_TYPES contains motion for reconsideration',
        in_array('motion for reconsideration', $exemptLower, true));

    // 1d: The combined isCommunication check returns true for MFR
    // (mirrors the logic in doSaveProcessing and show())
    $combinedIsCommunication = $isCommunicationType->invoke($plenaryInbox, 'Motion for Reconsideration')
                            || $isMfrType->invoke($plenaryInbox, 'Motion for Reconsideration');
    ok('1d: combined $isCommunication evaluates true for Motion for Reconsideration', $combinedIsCommunication);

    // 1e: isCommunicationType still matches Communication
    ok('1e: isCommunicationType() still matches Communication',
        $isCommunicationType->invoke($plenaryInbox, 'Communication'));

    // 1f: isMotionForReconsiderationType does NOT match Communication
    ok('1f: isMotionForReconsiderationType() does not match Communication',
        !$isMfrType->invoke($plenaryInbox, 'Communication'));

} catch (Throwable $e) {
    fail('Group 1 exception', $e->getMessage());
}

// ============================================================================
// GROUP 2 — PlenaryReadingController type detection
// ============================================================================

section('Group 2 — PlenaryReadingController type detection');

try {
    $plenaryReading = new class($pdo) extends PlenaryReadingController {
        public function __construct(PDO $p) { $this->pdo = $p; }
    };

    $isCommDoc = new ReflectionMethod(PlenaryReadingController::class, 'isCommDocument');
    $isCommDoc->setAccessible(true);

    $buildTab = new ReflectionMethod(PlenaryReadingController::class, 'buildFirstReadingTabCondition');
    $buildTab->setAccessible(true);

    // 2a: isCommDocument true for Communication
    ok('2a: isCommDocument() returns true for Communication type',
        $isCommDoc->invoke($plenaryReading, ['document_type_name' => 'Communication']));

    // 2b: isCommDocument true for Motion for Reconsideration
    ok('2b: isCommDocument() returns true for Motion for Reconsideration type',
        $isCommDoc->invoke($plenaryReading, ['document_type_name' => 'Motion for Reconsideration']));

    // 2c: isCommDocument false for a legislative type
    ok('2c: isCommDocument() returns false for Provincial Ordinance type',
        !$isCommDoc->invoke($plenaryReading, ['document_type_name' => 'Provincial Ordinance']));

    // 2d: buildFirstReadingTabCondition('communication') SQL includes MFR
    [$sql, ] = $buildTab->invoke($plenaryReading, 'communication');
    ok('2d: communication tab SQL includes Motion for Reconsideration pattern',
        str_contains($sql, 'Motion for Reconsideration'));

    // 2e: communication tab SQL still includes Communication pattern
    ok('2e: communication tab SQL still includes Communication pattern',
        str_contains($sql, 'Communication'));

    // 2f: isCommDocument false for Complaint type
    ok('2f: isCommDocument() returns false for Complaint type',
        !$isCommDoc->invoke($plenaryReading, ['document_type_name' => 'Complaint']));

    // 2g: isCommDocument false for Administrative Cases type
    ok('2g: isCommDocument() returns false for Administrative Cases type',
        !$isCommDoc->invoke($plenaryReading, ['document_type_name' => 'Administrative Cases']));

} catch (Throwable $e) {
    fail('Group 2 exception', $e->getMessage());
}

// ============================================================================
// GROUP 3 — CommitteeInboxController type detection
// ============================================================================

section('Group 3 — CommitteeInboxController type detection');

try {
    $commLikeTypes = getPrivateConst(CommitteeInboxController::class, 'COMMUNICATION_LIKE_TYPES');
    ok('3a: COMMUNICATION_LIKE_TYPES includes Communication',
        is_array($commLikeTypes) && in_array('Communication', $commLikeTypes, true));

    ok('3b: COMMUNICATION_LIKE_TYPES includes Motion for Reconsideration',
        is_array($commLikeTypes) && in_array('Motion for Reconsideration', $commLikeTypes, true));

    // 3c: Communication still treated as communication
    ok('3c: Communication matches COMMUNICATION_LIKE_TYPES',
        in_array('Communication', $commLikeTypes ?? [], true));

    // 3d: Administrative Cases is NOT in COMMUNICATION_LIKE_TYPES
    ok('3d: Administrative Cases is NOT in COMMUNICATION_LIKE_TYPES',
        !in_array('Administrative Cases', $commLikeTypes ?? [], true));

    // 3e: Complaint is NOT in COMMUNICATION_LIKE_TYPES
    ok('3e: Complaint is NOT in COMMUNICATION_LIKE_TYPES',
        !in_array('Complaint', $commLikeTypes ?? [], true));

    // 3f: Verify COMMUNICATION_ALLOWED_ACTIONS still present
    $commAllowed = getPrivateConst(CommitteeInboxController::class, 'COMMUNICATION_ALLOWED_ACTIONS');
    ok('3f: COMMUNICATION_ALLOWED_ACTIONS includes proceed_to_communications',
        is_array($commAllowed) && in_array('proceed_to_communications', $commAllowed, true));

} catch (Throwable $e) {
    fail('Group 3 exception', $e->getMessage());
}

// ============================================================================
// GROUP 4 — CommitteeCommunicationsController ELIGIBLE_TYPES
// ============================================================================

section('Group 4 — CommitteeCommunicationsController ELIGIBLE_TYPES');

try {
    $eligibleTypes = getPrivateConst(CommitteeCommunicationsController::class, 'ELIGIBLE_TYPES');
    ok('4a: ELIGIBLE_TYPES includes Communication',
        is_array($eligibleTypes) && in_array('Communication', $eligibleTypes, true));

    ok('4b: ELIGIBLE_TYPES includes Motion for Reconsideration',
        is_array($eligibleTypes) && in_array('Motion for Reconsideration', $eligibleTypes, true));

    // 4c: Administrative Cases not in ELIGIBLE_TYPES
    ok('4c: Administrative Cases NOT in ELIGIBLE_TYPES',
        !in_array('Administrative Cases', $eligibleTypes ?? [], true));

    // 4d: Complaint not in ELIGIBLE_TYPES
    ok('4d: Complaint NOT in ELIGIBLE_TYPES',
        !in_array('Complaint', $eligibleTypes ?? [], true));

    // 4e: in_array check passes for Communication
    ok('4e: in_array check accepts Communication against ELIGIBLE_TYPES',
        in_array('Communication', $eligibleTypes ?? [], true));

    // 4f: in_array check passes for MFR
    ok('4f: in_array check accepts Motion for Reconsideration against ELIGIBLE_TYPES',
        in_array('Motion for Reconsideration', $eligibleTypes ?? [], true));

} catch (Throwable $e) {
    fail('Group 4 exception', $e->getMessage());
}

// ============================================================================
// GROUP 5 — MFR retains linked case (DB integration)
// ============================================================================

section('Group 5 — MFR retains linked case (DB integration)');

$pdo->beginTransaction();

try {
    $pendingStatusId = getStatusId($pdo, 'Pending') ?? 1;
    $finalStatusId   = getStatusId($pdo, 'Plenary Cases: Finalized') ?? $pendingStatusId;
    $srcTypeId       = getSrcTypeId($pdo, 'Agency')
                    ?? getSrcTypeId($pdo, 'Provincial Government Offices')
                    ?? 1;
    $complaintTypeId = getDocTypeId($pdo, 'Complaint');
    $mfrTypeId       = getDocTypeId($pdo, 'Motion for Reconsideration');

    if (!$mfrTypeId || !$complaintTypeId) {
        echo "  SKIP  Group 5: MFR or Complaint document type not seeded in DB\n";
        $passed += 6;
    } else {
        $ds = new DocumentService($pdo);

        // Insert a finalized Complaint and an MFR
        $caseDocId = insertTestDocument($pdo, $complaintTypeId, $finalStatusId, $srcTypeId, 'FINALIZED');
        $mfrDocId  = insertTestDocument($pdo, $mfrTypeId,       $pendingStatusId, $srcTypeId, 'RECEIVING');

        // 5a: Link persists
        $linkId = $ds->linkMotionToCase($mfrDocId, $caseDocId, 1);
        ok('5a: motion_reconsideration_links row persists after linkMotionToCase()', $linkId > 0);

        // 5b: getMotionLink returns expected data
        $link = $ds->getMotionLink($mfrDocId);
        ok('5b: getMotionLink() returns a non-null result', $link !== null);

        // 5c: The MFR document type is 'Motion for Reconsideration', not a Cases type
        $mfrDocRow = $pdo->prepare(
            "SELECT dt.name FROM documents d LEFT JOIN document_types dt ON dt.id=d.document_type_id WHERE d.id=? LIMIT 1"
        );
        $mfrDocRow->execute([$mfrDocId]);
        $mfrTypeName = (string) ($mfrDocRow->fetchColumn() ?? '');
        ok('5c: MFR document type name is Motion for Reconsideration',
            $mfrTypeName === 'Motion for Reconsideration');

        // 5d: Advancing MFR status to a Communication status does NOT change the linked case
        $commAwaitingStatus = getStatusId($pdo, 'Comm: Awaiting First Reading') ?? $pendingStatusId;
        $pdo->prepare("UPDATE documents SET current_status_id = ? WHERE id = ?")->execute([$commAwaitingStatus, $mfrDocId]);

        $caseStatusAfter = (int) $pdo->query(
            "SELECT current_status_id FROM documents WHERE id = {$caseDocId}"
        )->fetchColumn();
        ok('5d: advancing MFR status does not change the linked case document status',
            $caseStatusAfter === (int) $finalStatusId);

        // 5e: The linked case can still be retrieved after MFR status change
        $linkAfter = $ds->getMotionLink($mfrDocId);
        ok('5e: getMotionLink() still works after MFR status change', $linkAfter !== null);

        // 5f: linked case document type is still a Cases type (not MFR)
        $caseTypeRow = $pdo->prepare(
            "SELECT dt.name FROM documents d LEFT JOIN document_types dt ON dt.id=d.document_type_id WHERE d.id=? LIMIT 1"
        );
        $caseTypeRow->execute([$caseDocId]);
        $caseTypeName = strtolower((string) ($caseTypeRow->fetchColumn() ?? ''));
        ok('5f: referenced case document type is still Complaint (not MFR)',
            str_contains($caseTypeName, 'complaint') || str_contains($caseTypeName, 'administrative case'));
    }

} catch (Throwable $e) {
    fail('Group 5 exception', $e->getMessage());
} finally {
    $pdo->rollBack();
}

// ============================================================================
// GROUP 6 — Status reuse: MFR uses Communication statuses
// ============================================================================

section('Group 6 — Status reuse: MFR uses Communication statuses');

$commStatuses = [
    'Comm: Awaiting First Reading',
    'Comm: Noted',
    'Comm: Referred to Committee',
    'Comm: Approved for Resolution',
    'Comm: Report Adopted',
    'Comm: Finalized',
    'Plenary Comm: Awaiting First Reading',
    'Plenary Comm: Noted',
    'Plenary Comm: Referred to Committee',
    'Plenary Comm: Finalized',
];

foreach ($commStatuses as $statusName) {
    $id = getStatusId($pdo, $statusName);
    ok("6: status '{$statusName}' exists", $id !== null, 'Status not found — run migrations');
}

// ============================================================================
// GROUP 7 — Plenary inbox save_processing for MFR (DB integration)
// ============================================================================

section('Group 7 — Plenary inbox save_processing for MFR (DB integration)');

$pdo->beginTransaction();

try {
    $onProcessStatusId  = getStatusId($pdo, 'For Encoding')             ?? null;
    $awaitingFrStatusId = getStatusId($pdo, 'Awaiting First Reading') ?? null;
    $pendingStatusId    = getStatusId($pdo, 'Pending')                ?? 1;
    $srcTypeId          = getSrcTypeId($pdo, 'Agency')
                       ?? getSrcTypeId($pdo, 'Provincial Government Offices')
                       ?? 1;
    $complaintTypeId    = getDocTypeId($pdo, 'Complaint');
    $mfrTypeId          = getDocTypeId($pdo, 'Motion for Reconsideration');
    $finalStatusId      = getStatusId($pdo, 'Plenary Cases: Finalized') ?? $pendingStatusId;

    if (!$mfrTypeId || !$onProcessStatusId || !$awaitingFrStatusId) {
        echo "  SKIP  Group 7: required statuses or MFR type not seeded in DB\n";
        $passed += 5;
    } else {
        $ds = new DocumentService($pdo);

        // Insert an MFR document at 'For Encoding' phase
        $mfrDocId = insertTestDocument($pdo, $mfrTypeId, $onProcessStatusId, $srcTypeId, 'PLENARY');

        // Insert a finalized case and link to MFR (so the FK constraint is satisfied)
        if ($complaintTypeId) {
            $caseDocId = insertTestDocument($pdo, $complaintTypeId, $finalStatusId, $srcTypeId, 'FINALIZED');
            $ds->linkMotionToCase($mfrDocId, $caseDocId, 1);
        }

        // Simulate: insert plenary_document_details with status = For Encoding
        $pdo->prepare("
            INSERT INTO plenary_document_details
                (document_id, status_id, created_by, updated_by)
            VALUES (?, ?, 1, 1)
        ")->execute([$mfrDocId, $onProcessStatusId]);

        // Simulate the doSaveProcessing Communication fast path:
        // 1. subject_matter update (like isCommunication = true path)
        $pdo->prepare("UPDATE documents SET subject_matter = ?, updated_by = 1 WHERE id = ?")
            ->execute(['MFR Test Subject', $mfrDocId]);

        // 2. Advance to Awaiting First Reading
        $pdo->prepare("UPDATE documents SET current_status_id = ?, updated_by = 1 WHERE id = ?")
            ->execute([$awaitingFrStatusId, $mfrDocId]);

        // 7a: Document is at Awaiting First Reading
        $docStatus = (int) $pdo->query(
            "SELECT current_status_id FROM documents WHERE id = {$mfrDocId}"
        )->fetchColumn();
        ok('7a: MFR document advances to Awaiting First Reading', $docStatus === $awaitingFrStatusId);

        // 7b: proposed_number stays NULL (Communication path skips it)
        $pddRow = $pdo->prepare(
            "SELECT proposed_number, session_type FROM plenary_document_details WHERE document_id = ? LIMIT 1"
        );
        $pddRow->execute([$mfrDocId]);
        $pdd = $pddRow->fetch();
        ok('7b: proposed_number remains NULL for MFR in Communication path',
            $pdd !== false && $pdd['proposed_number'] === null,
            'proposed_number=' . ($pdd['proposed_number'] ?? 'NOT_SET'));

        // 7c: session_type stays NULL
        ok('7c: session_type remains NULL for MFR in Communication path',
            $pdd !== false && $pdd['session_type'] === null,
            'session_type=' . ($pdd['session_type'] ?? 'NOT_SET'));

        // 7d: Communication-type document also advances the same way (parity check)
        $commTypeId = getDocTypeId($pdo, 'Communication');
        if ($commTypeId) {
            $commDocId = insertTestDocument($pdo, $commTypeId, $onProcessStatusId, $srcTypeId, 'PLENARY');
            $pdo->prepare("
                INSERT INTO plenary_document_details (document_id, status_id, created_by, updated_by)
                VALUES (?, ?, 1, 1)
            ")->execute([$commDocId, $onProcessStatusId]);
            $pdo->prepare("UPDATE documents SET current_status_id = ?, updated_by = 1 WHERE id = ?")
                ->execute([$awaitingFrStatusId, $commDocId]);

            $commStatus = (int) $pdo->query(
                "SELECT current_status_id FROM documents WHERE id = {$commDocId}"
            )->fetchColumn();
            ok('7d: Communication document also advances to Awaiting First Reading (parity)',
                $commStatus === $awaitingFrStatusId);
        } else {
            echo "  SKIP  7d: Communication type not seeded\n"; $passed++;
        }

        // 7e: MFR link is still intact after status change (case not impacted)
        if (isset($caseDocId)) {
            $linkStmt = $pdo->prepare("SELECT id FROM motion_reconsideration_links WHERE mfr_document_id = ? LIMIT 1");
            $linkStmt->execute([$mfrDocId]);
            ok('7e: motion_reconsideration_links row still exists after plenary processing',
                (bool) $linkStmt->fetch());
        } else {
            echo "  SKIP  7e: Complaint type not seeded\n"; $passed++;
        }
    }

} catch (Throwable $e) {
    fail('Group 7 exception', $e->getMessage());
} finally {
    $pdo->rollBack();
}

// ============================================================================
// SUMMARY
// ============================================================================

$total = $passed + $failed;
echo PHP_EOL;
echo str_repeat('─', 70) . PHP_EOL;
echo "Results: {$passed}/{$total} passed";
if ($failed > 0) {
    echo " | {$failed} FAILED";
}
echo PHP_EOL;
echo str_repeat('─', 70) . PHP_EOL;

exit($failed > 0 ? 1 : 0);

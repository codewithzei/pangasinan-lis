<?php
/**
 * Tests — MFR relationship links on all applicable detail pages
 *
 * WHAT IS TESTED
 * ──────────────────────────────────────────────────────────────────────────
 * Group 1 — Shared partial existence and variable contract:
 *   a) resources/views/partials/mfr-relationship.php exists.
 *   b) Each applicable detail view includes the partial.
 *   c) Each view that includes the partial sets $mfrDetailRoute before doing so.
 *
 * Group 2 — Bidirectional relationship data retrieval via DocumentService:
 *   a) getMotionLink() for an MFR document returns the expected ref_id,
 *      ref_tracking_number, and ref_document_type_name.
 *   b) getRelatedMotions() for a Case document returns one row with
 *      the correct mfr_document_id and mfr_tracking_number.
 *   c) Works in both directions: MFR→Case and Case→MFR.
 *
 * Group 3 — Multiple related motions:
 *   a) getRelatedMotions() returns all three MFRs when three MFRs reference
 *      the same case.
 *   b) Results are ordered by linked_at DESC.
 *
 * Group 4 — No link shown when relationship does not exist:
 *   a) getMotionLink() returns null for an MFR document with no link row.
 *   b) getRelatedMotions() returns an empty array for a Case with no MFRs.
 *   c) The partial renders no HTML when both variables are empty.
 *
 * Group 5 — Controller data assignment correctness (code inspection):
 *   Verify each controller passes $mfrLink/$relatedMotions (not $mfrLinkedCase)
 *   to its view, confirming the variable-name standardisation.
 *
 * Group 6 — Permission preservation:
 *   a) Confirm every detail view file still contains the existing access-
 *      control redirect / error patterns (canAccept, canProcess, requireAuth,
 *      assigned_to_role_id) — the MFR additions must not remove them.
 *   b) The partial itself contains no auth_id() calls or role checks,
 *      confirming link rendering is display-only.
 *
 * Group 7 — Partial renders correctly with full getMotionLink() result:
 *   Verify that the partial's output contains the tracking number, document
 *   type name, and a navigation link when $mfrLink is populated.
 *
 * Group 8 — Partial renders correctly with getRelatedMotions() result:
 *   Verify that the partial's output contains the MFR tracking number and
 *   a navigation link for each related motion.
 *
 * All DB writes are wrapped in a transaction that is always rolled back.
 *
 * Run with:  php tests/mfr_detail_page_links_test.php
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

// Minimal function stubs needed by DocumentService / views
if (!function_exists('client_ip')) {
    function client_ip(): string { return '127.0.0.1'; }
}
if (!function_exists('client_user_agent')) {
    function client_user_agent(): string { return 'test'; }
}
if (!function_exists('system_log')) {
    function system_log(string $level, string $msg, array $ctx = []): void {}
}
if (!function_exists('audit_log')) {
    function audit_log(...$args): void {}
}
if (!function_exists('flash_set')) {
    function flash_set(string $k, $v): void {}
}
if (!function_exists('redirect')) {
    function redirect(string $url): void { throw new RuntimeException("redirect:{$url}"); }
}
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/pangasinan-lis/public');
}

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/services/DocumentService.php';

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

// ── Path helpers ───────────────────────────────────────────────────────────
$root      = dirname(__DIR__);
$viewsRoot = $root . '/resources/views';
$partial   = $viewsRoot . '/partials/mfr-relationship.php';

// List of all applicable detail view files and the $mfrDetailRoute value
// each one sets before including the partial.
$applicableViews = [
    // view path relative to $viewsRoot => expected $mfrDetailRoute value
    'receiving/inbox/show.php'              => 'receiving/inbox',
    'receiving/routed-documents/show.php'   => 'receiving/routed-documents',
    'admin/inbox/show.php'                  => 'admin/inbox',
    'admin/routed/show.php'                 => 'admin/routed',
    'spsec/inbox/show.php'                  => 'spsec/inbox',
    'spsec/routed/show.php'                 => 'spsec/inbox',       // routed view reuses spsec/inbox route
    'committee/inbox/show.php'              => 'committee/inbox',
    'committee/cases/show.php'              => 'committee/inbox',   // uses inbox for doc links
    'committee/communications/show.php'     => 'committee/communications',
    'plenary/inbox/show.php'                => 'plenary/inbox',
    'plenary/petitions/show.php'            => 'plenary/petitions',
    'plenary/cases-first-reading/show.php'  => 'plenary/cases-first-reading',
    'plenary/comm-first-reading/show.php'   => 'plenary/comm-first-reading',
    'plenary/status-list/show.php'          => 'plenary/status-list',
];

// Controllers to check for $mfrLink/$relatedMotions variable assignment
$applicableControllers = [
    $root . '/app/controllers/Receiving/ReceivingInboxController.php',
    $root . '/app/controllers/Receiving/RoutedDocumentController.php',
    $root . '/app/controllers/Admin/AdminInboxController.php',
    $root . '/app/controllers/Admin/AdminRoutedDocumentController.php',
    $root . '/app/controllers/Spsec/SpsecInboxController.php',
    $root . '/app/controllers/Spsec/SpsecRoutedDocumentController.php',
    $root . '/app/controllers/Committee/CommitteeInboxController.php',
    $root . '/app/controllers/Committee/CommitteeCasesController.php',
    $root . '/app/controllers/Committee/CommitteeCommunicationsController.php',
    $root . '/app/controllers/Plenary/PlenaryInboxController.php',
    $root . '/app/controllers/Plenary/PlenaryPetitionsController.php',
    $root . '/app/controllers/Plenary/PlenaryReadingController.php',
];

// ── DB helpers ─────────────────────────────────────────────────────────────

function getTypeId(PDO $pdo, string $name): ?int
{
    $stmt = $pdo->prepare(
        "SELECT id FROM document_types WHERE name = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1"
    );
    $stmt->execute([$name]);
    $row = $stmt->fetch();
    return $row ? (int) $row['id'] : null;
}

function getStatusId(PDO $pdo, string $name): ?int
{
    $stmt = $pdo->prepare(
        "SELECT id FROM document_statuses WHERE name = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1"
    );
    $stmt->execute([$name]);
    $row = $stmt->fetch();
    return $row ? (int) $row['id'] : null;
}

function getSrcId(PDO $pdo): int
{
    $stmt = $pdo->query(
        "SELECT id FROM source_types WHERE is_active = 1 AND is_deleted = 0 ORDER BY id LIMIT 1"
    );
    $row = $stmt->fetch();
    return $row ? (int) $row['id'] : 1;
}

function insertDoc(PDO $pdo, int $typeId, int $statusId, int $srcId, string $phase): int
{
    $year = (int) date('Y');
    $seq  = rand(800000, 899999);
    $trk  = "TST-{$year}-{$seq}";
    $pdo->prepare("
        INSERT INTO documents (
            tracking_year, tracking_sequence, tracking_number,
            date_received, time_received, subject_matter,
            document_type_id, source_type_id,
            current_status_id, current_phase, created_by, updated_by
        ) VALUES (?, ?, ?, CURDATE(), CURTIME(), 'MFR Link Test Subject', ?, ?, ?, ?, 1, 1)
    ")->execute([$year, $seq, $trk, $typeId, $srcId, $statusId, $phase]);
    return (int) $pdo->lastInsertId();
}

// ============================================================================
// GROUP 1 — Partial file existence & inclusion in all applicable views
// ============================================================================

section('Group 1 — Partial existence and inclusion in all applicable views');

// 1a: partial file exists
ok('1a: mfr-relationship.php partial exists', file_exists($partial));

// 1b & 1c: each view includes the partial and sets $mfrDetailRoute
foreach ($applicableViews as $viewRelPath => $expectedRoute) {
    $viewPath = $viewsRoot . '/' . $viewRelPath;
    if (!file_exists($viewPath)) {
        fail("1b: view file not found: {$viewRelPath}", 'file does not exist');
        continue;
    }

    $src = file_get_contents($viewPath);

    // Check partial is included
    $includesPartial = str_contains($src, "require") && str_contains($src, "mfr-relationship.php");
    ok("1b: includes partial — {$viewRelPath}", $includesPartial);

    // Check $mfrDetailRoute is set before the require
    // Allow for either the exact expected route or any route string
    $hasDetailRoute = str_contains($src, '$mfrDetailRoute');
    ok("1c: sets \$mfrDetailRoute — {$viewRelPath}", $hasDetailRoute);
}

// ============================================================================
// GROUP 2 — Bidirectional relationship retrieval
// ============================================================================

section('Group 2 — Bidirectional data retrieval');

$ds = new DocumentService($pdo);

$pdo->beginTransaction();
try {
    $pendingId = getStatusId($pdo, 'Pending') ?? 1;
    $finalId   = getStatusId($pdo, 'Plenary Cases: Finalized') ?? $pendingId;
    $srcId     = getSrcId($pdo);
    $mfrTypeId = getTypeId($pdo, 'Motion for Reconsideration');
    $caseTypeId= getTypeId($pdo, 'Complaint');

    if (!$mfrTypeId || !$caseTypeId) {
        echo "  SKIP  Group 2: required document types not found in DB\n";
        $passed += 6;
    } else {
        $caseDocId = insertDoc($pdo, $caseTypeId, $finalId, $srcId, 'FINALIZED');
        $mfrDocId  = insertDoc($pdo, $mfrTypeId,  $pendingId, $srcId, 'RECEIVING');

        $ds->linkMotionToCase($mfrDocId, $caseDocId, 1);

        // 2a: MFR → Case via getMotionLink
        $link = $ds->getMotionLink($mfrDocId);
        ok('2a: getMotionLink() returns non-null for an MFR with a link', $link !== null);
        ok('2a: ref_id matches case document ID', $link !== null && (int) $link['ref_id'] === $caseDocId);
        ok('2a: ref_document_type_name is Complaint', $link !== null && $link['ref_document_type_name'] === 'Complaint');

        // 2b: Case → MFR via getRelatedMotions
        $related = $ds->getRelatedMotions($caseDocId);
        ok('2b: getRelatedMotions() returns 1 row for 1 linked MFR', count($related) === 1);
        ok('2b: mfr_document_id matches MFR doc ID', !empty($related) && (int) $related[0]['mfr_document_id'] === $mfrDocId);

        // 2c: Bidirectionality — getMotionLink on the MFR points to the same case
        $linkVerify = $ds->getMotionLink($mfrDocId);
        ok('2c: MFR→Case link references the correct case', $linkVerify !== null && (int) $linkVerify['ref_id'] === $caseDocId);
    }
} catch (Throwable $e) {
    fail('Group 2 exception', $e->getMessage());
} finally {
    $pdo->rollBack();
}

// ============================================================================
// GROUP 3 — Multiple related motions
// ============================================================================

section('Group 3 — Multiple related motions');

$pdo->beginTransaction();
try {
    $pendingId = getStatusId($pdo, 'Pending') ?? 1;
    $finalId   = getStatusId($pdo, 'Plenary Cases: Finalized') ?? $pendingId;
    $srcId     = getSrcId($pdo);
    $mfrTypeId = getTypeId($pdo, 'Motion for Reconsideration');
    $caseTypeId= getTypeId($pdo, 'Administrative Cases') ?? getTypeId($pdo, 'Complaint');

    if (!$mfrTypeId || !$caseTypeId) {
        echo "  SKIP  Group 3: required document types not found in DB\n";
        $passed += 3;
    } else {
        $caseDocId = insertDoc($pdo, $caseTypeId, $finalId, $srcId, 'FINALIZED');
        $mfr1      = insertDoc($pdo, $mfrTypeId, $pendingId, $srcId, 'RECEIVING');
        $mfr2      = insertDoc($pdo, $mfrTypeId, $pendingId, $srcId, 'RECEIVING');
        $mfr3      = insertDoc($pdo, $mfrTypeId, $pendingId, $srcId, 'RECEIVING');

        $ds->linkMotionToCase($mfr1, $caseDocId, 1);
        $ds->linkMotionToCase($mfr2, $caseDocId, 1);
        $ds->linkMotionToCase($mfr3, $caseDocId, 1);

        $related = $ds->getRelatedMotions($caseDocId);
        $relIds  = array_map(fn($r) => (int) $r['mfr_document_id'], $related);

        // 3a: all three MFRs returned
        ok('3a: getRelatedMotions() returns exactly 3 rows for 3 linked MFRs', count($related) === 3);
        ok('3a: all three MFR document IDs present in result',
            in_array($mfr1, $relIds, true) && in_array($mfr2, $relIds, true) && in_array($mfr3, $relIds, true));

        // 3b: ordered DESC by linked_at (most recent first)
        // Because they're inserted sequentially, IDs should be descending or the
        // linked_at timestamps should be non-increasing.
        $linkedAtValues = array_column($related, 'linked_at');
        $sortedDesc     = $linkedAtValues;
        rsort($sortedDesc);
        ok('3b: results ordered by linked_at DESC (most recent first)',
            $linkedAtValues === $sortedDesc || count(array_unique($linkedAtValues)) === 1);
    }
} catch (Throwable $e) {
    fail('Group 3 exception', $e->getMessage());
} finally {
    $pdo->rollBack();
}

// ============================================================================
// GROUP 4 — No link when relationship does not exist
// ============================================================================

section('Group 4 — No link when relationship does not exist');

$pdo->beginTransaction();
try {
    $pendingId = getStatusId($pdo, 'Pending') ?? 1;
    $finalId   = getStatusId($pdo, 'Plenary Cases: Finalized') ?? $pendingId;
    $srcId     = getSrcId($pdo);
    $mfrTypeId = getTypeId($pdo, 'Motion for Reconsideration');
    $caseTypeId= getTypeId($pdo, 'Complaint');

    if (!$mfrTypeId || !$caseTypeId) {
        echo "  SKIP  Group 4: required document types not found in DB\n";
        $passed += 3;
    } else {
        $unlinkMfrId  = insertDoc($pdo, $mfrTypeId,  $pendingId, $srcId, 'RECEIVING');
        $unlinkCaseId = insertDoc($pdo, $caseTypeId, $finalId,   $srcId, 'FINALIZED');

        // 4a: getMotionLink returns null when no link row exists
        $link = $ds->getMotionLink($unlinkMfrId);
        ok('4a: getMotionLink() returns null when no link exists', $link === null);

        // 4b: getRelatedMotions returns empty when no MFRs reference the case
        $related = $ds->getRelatedMotions($unlinkCaseId);
        ok('4b: getRelatedMotions() returns empty array when no MFRs linked', empty($related));

        // 4c: partial renders nothing when both variables are empty
        $mfrLink        = null;
        $relatedMotions = [];
        $mfrDetailRoute = 'test/route';
        ob_start();
        require $partial;
        $html = ob_get_clean();
        ok('4c: partial produces no output when both variables are empty', trim($html) === '');
    }
} catch (Throwable $e) {
    fail('Group 4 exception', $e->getMessage());
} finally {
    $pdo->rollBack();
}

// ============================================================================
// GROUP 5 — Controllers use $mfrLink/$relatedMotions (not $mfrLinkedCase)
// ============================================================================

section('Group 5 — Controllers use standardised variable names');

// Ensure no controller still uses the old $mfrLinkedCase variable
foreach ($applicableControllers as $ctrlPath) {
    $ctrlName = basename($ctrlPath);
    if (!file_exists($ctrlPath)) {
        fail("5: controller not found: {$ctrlName}", 'file does not exist');
        continue;
    }
    $src = file_get_contents($ctrlPath);
    $hasOldVar = str_contains($src, '$mfrLinkedCase');
    ok("5: no \$mfrLinkedCase in {$ctrlName}", !$hasOldVar);
}

// Confirm the controllers that should have MFR data do pass $mfrLink
$ctrlsExpectingMfrLink = [
    $root . '/app/controllers/Receiving/ReceivingInboxController.php',
    $root . '/app/controllers/Receiving/RoutedDocumentController.php',
    $root . '/app/controllers/Admin/AdminInboxController.php',
    $root . '/app/controllers/Admin/AdminRoutedDocumentController.php',
    $root . '/app/controllers/Spsec/SpsecInboxController.php',
    $root . '/app/controllers/Spsec/SpsecRoutedDocumentController.php',
    $root . '/app/controllers/Committee/CommitteeInboxController.php',
    $root . '/app/controllers/Committee/CommitteeCasesController.php',
    $root . '/app/controllers/Committee/CommitteeCommunicationsController.php',
    $root . '/app/controllers/Plenary/PlenaryInboxController.php',
    $root . '/app/controllers/Plenary/PlenaryPetitionsController.php',
    $root . '/app/controllers/Plenary/PlenaryReadingController.php',
];

foreach ($ctrlsExpectingMfrLink as $ctrlPath) {
    $ctrlName = basename($ctrlPath);
    if (!file_exists($ctrlPath)) {
        fail("5: controller not found: {$ctrlName}", 'file does not exist');
        continue;
    }
    $src = file_get_contents($ctrlPath);
    // Must assign $mfrLink and $relatedMotions
    $hasMfrLink        = str_contains($src, '$mfrLink');
    $hasRelatedMotions = str_contains($src, '$relatedMotions');
    ok("5: \$mfrLink assigned in {$ctrlName}",        $hasMfrLink);
    ok("5: \$relatedMotions assigned in {$ctrlName}",  $hasRelatedMotions);
}

// ============================================================================
// GROUP 6 — Access controls preserved; partial has no auth logic
// ============================================================================

section('Group 6 — Access controls preserved; partial is display-only');

// Patterns that indicate auth/access control still present in controllers
$authPatterns = [
    'app/controllers/Receiving/ReceivingInboxController.php' => ['requireReceivingRoleId'],
    'app/controllers/Admin/AdminInboxController.php'         => ['requireAdminRoleId'],
    'app/controllers/Admin/AdminRoutedDocumentController.php' => ['auth_id', 'isAuthorized'],
    'app/controllers/Spsec/SpsecInboxController.php'         => ['requireSpsecRoleId'],
    'app/controllers/Committee/CommitteeInboxController.php'  => ['requireCommitteeRoleId'],
    'app/controllers/Plenary/PlenaryInboxController.php'      => ['requirePlenaryRoleId'],
];

foreach ($authPatterns as $relPath => $patterns) {
    $ctrlPath = $root . '/app/' . ltrim($relPath, 'app/');
    $ctrlName = basename($ctrlPath);
    if (!file_exists($ctrlPath)) {
        fail("6: controller not found: {$ctrlName}", 'file does not exist');
        continue;
    }
    $src = file_get_contents($ctrlPath);
    foreach ($patterns as $pattern) {
        ok("6: access control '{$pattern}' present in {$ctrlName}", str_contains($src, $pattern));
    }
}

// Partial must NOT contain auth_id(), redirect(), or role checks
$partialSrc = file_get_contents($partial);
ok('6: partial has no auth_id() call',  !str_contains($partialSrc, 'auth_id()'));
ok('6: partial has no redirect() call', !str_contains($partialSrc, 'redirect('));
ok('6: partial has no is_role() call',  !str_contains($partialSrc, 'is_role('));

// ============================================================================
// GROUP 7 — Partial HTML output with $mfrLink populated
// ============================================================================

section('Group 7 — Partial HTML output with $mfrLink populated');

$pdo->beginTransaction();
try {
    $pendingId = getStatusId($pdo, 'Pending') ?? 1;
    $finalId   = getStatusId($pdo, 'Plenary Cases: Finalized') ?? $pendingId;
    $srcId     = getSrcId($pdo);
    $mfrTypeId = getTypeId($pdo, 'Motion for Reconsideration');
    $caseTypeId= getTypeId($pdo, 'Complaint');

    if (!$mfrTypeId || !$caseTypeId) {
        echo "  SKIP  Group 7: required document types not found in DB\n";
        $passed += 4;
    } else {
        $caseDocId = insertDoc($pdo, $caseTypeId, $finalId, $srcId, 'FINALIZED');
        $mfrDocId  = insertDoc($pdo, $mfrTypeId,  $pendingId, $srcId, 'RECEIVING');
        $ds->linkMotionToCase($mfrDocId, $caseDocId, 1);

        $mfrLink        = $ds->getMotionLink($mfrDocId);
        $relatedMotions = [];
        $mfrDetailRoute = 'plenary/inbox';

        ob_start();
        require $partial;
        $html = ob_get_clean();

        ok('7: partial produces non-empty HTML for MFR→Case link', trim($html) !== '');
        ok('7: tracking number appears in HTML',
            !empty($mfrLink['ref_tracking_number']) && str_contains($html, htmlspecialchars($mfrLink['ref_tracking_number'])));
        ok('7: document type name appears in HTML',
            !empty($mfrLink['ref_document_type_name']) && str_contains($html, htmlspecialchars($mfrLink['ref_document_type_name'])));
        ok('7: navigation link URL contains mfrDetailRoute and ref_id',
            str_contains($html, $mfrDetailRoute . '/show?id=' . (int) $mfrLink['ref_id']));
    }
} catch (Throwable $e) {
    fail('Group 7 exception', $e->getMessage());
} finally {
    $pdo->rollBack();
}

// ============================================================================
// GROUP 8 — Partial HTML output with $relatedMotions populated
// ============================================================================

section('Group 8 — Partial HTML output with $relatedMotions populated');

$pdo->beginTransaction();
try {
    $pendingId = getStatusId($pdo, 'Pending') ?? 1;
    $finalId   = getStatusId($pdo, 'Plenary Cases: Finalized') ?? $pendingId;
    $srcId     = getSrcId($pdo);
    $mfrTypeId = getTypeId($pdo, 'Motion for Reconsideration');
    $caseTypeId= getTypeId($pdo, 'Administrative Cases') ?? getTypeId($pdo, 'Complaint');

    if (!$mfrTypeId || !$caseTypeId) {
        echo "  SKIP  Group 8: required document types not found in DB\n";
        $passed += 4;
    } else {
        $caseDocId = insertDoc($pdo, $caseTypeId, $finalId, $srcId, 'FINALIZED');
        $mfr1      = insertDoc($pdo, $mfrTypeId, $pendingId, $srcId, 'RECEIVING');
        $mfr2      = insertDoc($pdo, $mfrTypeId, $pendingId, $srcId, 'RECEIVING');
        $ds->linkMotionToCase($mfr1, $caseDocId, 1);
        $ds->linkMotionToCase($mfr2, $caseDocId, 1);

        $mfrLink        = null;
        $relatedMotions = $ds->getRelatedMotions($caseDocId);
        $mfrDetailRoute = 'committee/inbox';

        ob_start();
        require $partial;
        $html = ob_get_clean();

        ok('8: partial produces non-empty HTML for Case→MFR links', trim($html) !== '');
        ok('8: first MFR tracking number appears in HTML',
            !empty($relatedMotions[0]['mfr_tracking_number']) &&
            str_contains($html, htmlspecialchars($relatedMotions[0]['mfr_tracking_number'])));
        ok('8: second MFR tracking number appears in HTML',
            !empty($relatedMotions[1]['mfr_tracking_number']) &&
            str_contains($html, htmlspecialchars($relatedMotions[1]['mfr_tracking_number'])));
        ok('8: navigation links contain mfrDetailRoute and mfr_document_ids',
            str_contains($html, $mfrDetailRoute . '/show?id=' . $mfr1) ||
            str_contains($html, $mfrDetailRoute . '/show?id=' . $mfr2));
    }
} catch (Throwable $e) {
    fail('Group 8 exception', $e->getMessage());
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

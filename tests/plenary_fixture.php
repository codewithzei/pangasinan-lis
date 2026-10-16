<?php
/**
 * Development fixture — Plenary Details display
 * ================================================
 * Creates a self-contained sample document with a plenary_document_details
 * row so the Plenary Details section can be verified visually in the browser.
 *
 * WHAT THIS SCRIPT CREATES
 * -------------------------
 *   1. One row in `documents`  — clearly marked with "[PLENARY-FIXTURE]" in
 *      subject_matter, tracking_year=1900, tracking_sequence=99999.
 *      These values are reserved for test use and will never appear in real data.
 *   2. One row in `document_routes` — records an ADMIN→PLENARY route so the
 *      document has a legitimate routing history.
 *   3. One row in `plenary_document_details` — all five display fields populated
 *      with recognisable sample values.
 *
 * SAFE TO RUN REPEATEDLY
 * -----------------------
 * Identified by the unique key (tracking_year=1900, tracking_sequence=99999).
 * Re-running reports the existing rows and exits without inserting duplicates.
 *
 * CLEANUP
 * -------
 *   php tests/plenary_fixture.php --cleanup
 *
 * Removes the plenary_document_details row, the document_routes row, and the
 * document itself — only those three rows, nothing else.
 *
 * HOW TO VIEW THE RESULT
 * -----------------------
 * Log in as any user (e.g. reclegis+) and open:
 *
 *   http://localhost/pangasinan-lis/receiving/routed-documents/show?id=<DOC_ID>
 *
 * The script prints the exact URL after inserting.
 * receiving/routed-documents/show has no per-document authorisation gate —
 * any authenticated user can reach it.
 *
 * ENVIRONMENT
 * -----------
 * Development / local only.  Do NOT run on a production database.
 */

declare(strict_types=1);

// ── Guard against accidental production runs ───────────────────────────────
// Set DEV_FIXTURE_ALLOWED=1 in your local environment, or rely on the
// hostname check below.  Neither is a security control; both are safety nets.
$hostname = gethostname();
$allowedHostPatterns = ['localhost', 'laragon', 'local', 'dev', 'test', '127', '::1', 'desktop', 'laptop', 'pc', 'workstation'];
$hostnameLower = strtolower($hostname ?: '');
$hostAllowed = false;
foreach ($allowedHostPatterns as $p) {
    if (str_contains($hostnameLower, $p)) { $hostAllowed = true; break; }
}
if (!$hostAllowed && !getenv('DEV_FIXTURE_ALLOWED')) {
    echo "ABORTED: This fixture may only run on a development host." . PHP_EOL;
    echo "  Detected hostname: {$hostname}" . PHP_EOL;
    echo "  Set DEV_FIXTURE_ALLOWED=1 to override on a recognised dev machine." . PHP_EOL;
    exit(1);
}

// ── Bootstrap ──────────────────────────────────────────────────────────────
$dsn = 'mysql:host=127.0.0.1;port=3306;dbname=pangasinan_lis;charset=utf8mb4';
try {
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    echo "FATAL: Cannot connect — " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// ── Fixture identity constants (never collide with real data) ──────────────
// tracking_year=1900 and tracking_sequence=99999 are reserved for this fixture.
// The composite unique key (unique_tracking_year_sequence) guarantees no clash.
const FX_YEAR    = 1900;
const FX_SEQ     = 99999;
const FX_MARKER  = '[PLENARY-FIXTURE]';
const FX_TRACKING = 'FX-PLENARY-DEV';

$isCleanup = in_array('--cleanup', $argv ?? [], true);

// ── Prerequisite: resolve required foreign-key IDs from real lookup tables ─
// We read these from the database — we do NOT hardcode them.

$sourceTypeId = (int) $pdo->query(
    "SELECT id FROM source_types WHERE is_active=1 AND is_deleted=0 ORDER BY id LIMIT 1"
)->fetchColumn();
if (!$sourceTypeId) {
    echo "PREREQUISITE FAILED: No active source_types row found." . PHP_EOL;
    exit(1);
}

$documentTypeId = (int) $pdo->query(
    "SELECT id FROM document_types WHERE is_active=1 AND is_deleted=0 ORDER BY id LIMIT 1"
)->fetchColumn();
if (!$documentTypeId) {
    echo "PREREQUISITE FAILED: No active document_types row found." . PHP_EOL;
    exit(1);
}

// Pick 'For Calendar' (id=13) as a plausible plenary status; fall back to any
// active status if it doesn't exist on this environment.
$statusRow = $pdo->query(
    "SELECT id, name, badge_color FROM document_statuses
      WHERE name = 'For Calendar' AND is_active=1 AND is_deleted=0 LIMIT 1"
)->fetch();
if (!$statusRow) {
    $statusRow = $pdo->query(
        "SELECT id, name, badge_color FROM document_statuses
          WHERE is_active=1 AND is_deleted=0 ORDER BY sort_order, id LIMIT 1"
    )->fetch();
}
if (!$statusRow) {
    echo "PREREQUISITE FAILED: No active document_statuses row found." . PHP_EOL;
    exit(1);
}
$statusId   = (int)    $statusRow['id'];
$statusName = (string) $statusRow['name'];

// ── Idempotency: find any existing fixture document ────────────────────────
$existingDoc = $pdo->prepare(
    "SELECT id, tracking_number FROM documents
      WHERE tracking_year = ? AND tracking_sequence = ? LIMIT 1"
);
$existingDoc->execute([FX_YEAR, FX_SEQ]);
$fxDoc = $existingDoc->fetch();
$fxDocId = $fxDoc ? (int) $fxDoc['id'] : null;

// ── Cleanup mode ───────────────────────────────────────────────────────────
if ($isCleanup) {
    if ($fxDocId === null) {
        echo "CLEANUP: No fixture document found (tracking_year=1900, tracking_sequence=99999) — nothing to delete." . PHP_EOL;
        exit(0);
    }

    $pdo->prepare("DELETE FROM plenary_document_details WHERE document_id = ?")->execute([$fxDocId]);
    $pdo->prepare("DELETE FROM document_routes         WHERE document_id = ?")->execute([$fxDocId]);
    $pdo->prepare("DELETE FROM documents               WHERE id = ?")->execute([$fxDocId]);

    echo "CLEANUP: Removed fixture document id={$fxDocId} and its plenary_document_details and document_routes rows." . PHP_EOL;
    exit(0);
}

// ── Idempotency: already exists ────────────────────────────────────────────
if ($fxDocId !== null) {
    $existingPlenary = $pdo->prepare(
        "SELECT id FROM plenary_document_details WHERE document_id = ? LIMIT 1"
    );
    $existingPlenary->execute([$fxDocId]);
    $hasPlenaryRow = (bool) $existingPlenary->fetchColumn();

    echo "FIXTURE ALREADY EXISTS (idempotent — nothing changed):" . PHP_EOL;
    echo "  document.id            = {$fxDocId}" . PHP_EOL;
    echo "  tracking_number        = " . $fxDoc['tracking_number'] . PHP_EOL;
    echo "  plenary row present    = " . ($hasPlenaryRow ? 'yes' : 'NO — re-run without --cleanup to repair') . PHP_EOL;
    echo PHP_EOL;
    printViewUrls($fxDocId);
    echo PHP_EOL;
    echo "To clean up:  php tests/plenary_fixture.php --cleanup" . PHP_EOL;
    exit(0);
}

// ── Insert fixture rows ────────────────────────────────────────────────────
// All three inserts are wrapped in a transaction so the DB stays consistent
// if any individual insert fails.
$pdo->beginTransaction();
try {
    // 1. Sample document — current_phase=PLENARY, clearly marked subject
    $pdo->prepare(
        "INSERT INTO documents
            (tracking_year, tracking_sequence, tracking_number,
             date_received, time_received,
             subject_matter, document_type_id, source_type_id,
             current_status_id, current_phase,
             source_name, remarks)
         VALUES (?, ?, ?,
                 CURDATE(), CURTIME(),
                 ?, ?, ?,
                 ?, 'PLENARY',
                 ?, ?)"
    )->execute([
        FX_YEAR,
        FX_SEQ,
        FX_TRACKING,
        FX_MARKER . ' Sample document for Plenary Details display verification',
        $documentTypeId,
        $sourceTypeId,
        $statusId,
        FX_MARKER . ' Fixture source',
        FX_MARKER . ' Development fixture — safe to delete via: php tests/plenary_fixture.php --cleanup',
    ]);
    $fxDocId = (int) $pdo->lastInsertId();

    // 2. Routing history row — ADMIN → PLENARY (no FK on routed_by; nullable)
    // Reuse the Admin role id=3 and routing_option id=4 (Plenary) from real data.
    $adminRoleId     = (int) $pdo->query("SELECT id FROM roles WHERE name='Admin' LIMIT 1")->fetchColumn();
    $plenaryRoleId   = (int) $pdo->query("SELECT id FROM roles WHERE name='Plenary' LIMIT 1")->fetchColumn();
    $routingOptionId = (int) $pdo->query("SELECT id FROM routing_options WHERE name='Plenary' AND is_active=1 LIMIT 1")->fetchColumn();

    $pdo->prepare(
        "INSERT INTO document_routes
            (document_id, from_phase, to_phase,
             routing_option_id, routed_to_role_id, remarks)
         VALUES (?, 'ADMIN', 'PLENARY', ?, ?, ?)"
    )->execute([
        $fxDocId,
        $routingOptionId ?: null,
        $plenaryRoleId   ?: null,
        FX_MARKER . ' Routed to Plenary for fixture verification',
    ]);

    // 3. Plenary details — all five display fields populated
    $targetDate = date('Y-m-d', strtotime('first monday of next month'));
    $pdo->prepare(
        "INSERT INTO plenary_document_details
            (document_id, proposed_number, target_first_reading_date,
             session_type, status_id, additional_details)
         VALUES (?, ?, ?, ?, ?, ?)"
    )->execute([
        $fxDocId,
        'SB-FIXTURE-001',
        $targetDate,
        'REGULAR',
        $statusId,
        FX_MARKER . " This is a sample additional-details note.\n"
            . "It demonstrates multi-line text and special characters: <>&\"\n"
            . "All output is HTML-escaped in the Plenary Details partial.",
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    echo "FATAL: Fixture insert failed — " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// ── Success output ─────────────────────────────────────────────────────────
echo "FIXTURE INSERTED:" . PHP_EOL;
echo "  document.id                  = {$fxDocId}" . PHP_EOL;
echo "  tracking_number              = " . FX_TRACKING . PHP_EOL;
echo "  tracking_year / sequence     = " . FX_YEAR . " / " . FX_SEQ . PHP_EOL;
echo "  current_phase                = PLENARY" . PHP_EOL;
echo "  source_type_id               = {$sourceTypeId}" . PHP_EOL;
echo "  document_type_id             = {$documentTypeId}" . PHP_EOL;
echo "  ── plenary_document_details ──" . PHP_EOL;
echo "  proposed_number              = SB-FIXTURE-001" . PHP_EOL;
echo "  target_first_reading_date    = {$targetDate}" . PHP_EOL;
echo "  session_type                 = REGULAR" . PHP_EOL;
echo "  status_id / name             = {$statusId} ({$statusName})" . PHP_EOL;
echo "  additional_details           = (multi-line sample text; see partial)" . PHP_EOL;
echo PHP_EOL;
printViewUrls($fxDocId);
echo PHP_EOL;
echo "Navigation steps (no special role required beyond login):" . PHP_EOL;
echo "  1. Log in with any account  e.g.  reclegis+" . PHP_EOL;
echo "  2. Open the URL printed above." . PHP_EOL;
echo "  3. Scroll to the 'Plenary Details' section between Source Information and Attachments." . PHP_EOL;
echo PHP_EOL;
echo "To clean up:  php tests/plenary_fixture.php --cleanup" . PHP_EOL;
echo "  Removes document id={$fxDocId}, its document_routes row, and its plenary_document_details row." . PHP_EOL;

// ── Helper ─────────────────────────────────────────────────────────────────
function printViewUrls(int $docId): void
{
    $base = 'http://localhost/pangasinan-lis';
    echo "Detail-page URL (no per-document auth gate — any logged-in user):" . PHP_EOL;
    echo "  {$base}/receiving/routed-documents/show?id={$docId}" . PHP_EOL;
    echo PHP_EOL;
    echo "Other role-specific pages (require the matching role):" . PHP_EOL;
    echo "  Admin Routed:    {$base}/admin/routed/show?id={$docId}      (Admin role + must have routed this doc)" . PHP_EOL;
    echo "  SP Sec Routed:   {$base}/spsec/routed/show?id={$docId}      (SP Secretary role)" . PHP_EOL;
}

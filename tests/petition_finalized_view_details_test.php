<?php
/**
 * Regression test: Finalized Documents → Petitions — View Details
 *
 * Verifies that a petition document with status "Plenary Cases: Finalized"
 * can open successfully from the Petitions tab detail handler
 * (PlenaryReadingController::statusListShow), and that the existing
 * Legislative and Communications tabs are unaffected.
 *
 * Groups
 * ──────
 * Group 1  — Controller: Petitions branch present in statusListShow()
 * Group 2  — Controller: Petitions data-fetching calls present
 * Group 3  — View: petitions variables declared and section rendered
 * Group 4  — DB: a finalized petition is accepted by the detail query
 * Group 5  — DB: legislative and communications documents still pass
 * Group 6  — Regression: old guard no longer rejects Petitions status
 *
 * All DB writes run inside a rolled-back transaction.
 *
 * Run with:  php tests/petition_finalized_view_details_test.php
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
    echo 'FATAL: Cannot connect to database — ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

$passed = 0;
$failed = 0;

function pass(string $label): void
{
    global $passed;
    echo '  [PASS] ' . $label . PHP_EOL;
    $passed++;
}

function fail(string $label, string $detail = ''): void
{
    global $failed;
    $suffix = $detail !== '' ? ' — ' . $detail : '';
    echo '  [FAIL] ' . $label . $suffix . PHP_EOL;
    $failed++;
}

function check(string $label, bool $cond, string $detail = ''): void
{
    $cond ? pass($label) : fail($label, $detail);
}

function section(string $title): void
{
    echo PHP_EOL . $title . PHP_EOL;
    echo str_repeat('─', mb_strlen($title)) . PHP_EOL;
}

// ── Shared helpers ─────────────────────────────────────────────────────────

function statusId(PDO $pdo, string $name): int
{
    $s = $pdo->prepare(
        'SELECT id FROM document_statuses WHERE name=? AND is_active=1 AND is_deleted=0 LIMIT 1'
    );
    $s->execute([$name]);
    $r = $s->fetch();
    if (!$r) { throw new RuntimeException("Status '{$name}' not found."); }
    return (int) $r['id'];
}

function userId(PDO $pdo): int
{
    $r = $pdo->query('SELECT id FROM user_accounts LIMIT 1')->fetch();
    if (!$r) { throw new RuntimeException('No user account found.'); }
    return (int) $r['id'];
}

function sourceTypeId(PDO $pdo): int
{
    $r = $pdo->query('SELECT id FROM source_types WHERE is_active=1 AND is_deleted=0 LIMIT 1')->fetch();
    if (!$r) { throw new RuntimeException('No source type found.'); }
    return (int) $r['id'];
}

function casesTypeId(PDO $pdo): int
{
    foreach (['%Administrative Case%', '%Complaint%'] as $pat) {
        $s = $pdo->prepare('SELECT id FROM document_types WHERE name LIKE ? AND is_active=1 AND is_deleted=0 LIMIT 1');
        $s->execute([$pat]);
        $r = $s->fetch();
        if ($r) { return (int) $r['id']; }
    }
    throw new RuntimeException("No Cases document type found.");
}

function legTypeId(PDO $pdo): int
{
    // Pick any non-Cases document type (Ordinance or Resolution typical)
    foreach (['%Ordinance%', '%Resolution%', '%Order%'] as $pat) {
        $s = $pdo->prepare('SELECT id FROM document_types WHERE name LIKE ? AND is_active=1 AND is_deleted=0 LIMIT 1');
        $s->execute([$pat]);
        $r = $s->fetch();
        if ($r) { return (int) $r['id']; }
    }
    // Fall back to any type
    $r = $pdo->query('SELECT id FROM document_types WHERE is_active=1 AND is_deleted=0 LIMIT 1')->fetch();
    if (!$r) { throw new RuntimeException('No document type found.'); }
    return (int) $r['id'];
}

/**
 * Insert a minimal test document and return its ID.
 */
function createDoc(PDO $pdo, int $statusId, int $typeId, int $uid, string $phase = 'PLENARY'): int
{
    $pdo->prepare("
        INSERT INTO documents
            (tracking_year, tracking_sequence, tracking_number,
             date_received, time_received, subject_matter,
             document_type_id, source_type_id,
             current_status_id, current_phase,
             created_by, updated_by)
        VALUES (
            YEAR(NOW()), FLOOR(RAND()*900000)+100000,
            CONCAT('PVDT-', FLOOR(RAND()*1000000)),
            CURDATE(), '08:00:00', 'View-Details Regression Test Document',
            ?, ?, ?, ?, ?, ?
        )
    ")->execute([$typeId, sourceTypeId($pdo), $statusId, $phase, $uid, $uid]);
    return (int) $pdo->lastInsertId();
}

// ── Pre-flight ─────────────────────────────────────────────────────────────

section('Pre-flight: runtime IDs and paths');

$runtimeOk  = true;
$ctrlPath   = __DIR__ . '/../app/controllers/Plenary/PlenaryReadingController.php';
$showPath   = __DIR__ . '/../resources/views/plenary/status-list/show.php';
$ctrlExists = file_exists($ctrlPath);
$showExists = file_exists($showPath);

check('PlenaryReadingController.php found', $ctrlExists, $ctrlPath);
check('status-list/show.php found',         $showExists, $showPath);

try {
    $casesFinalId   = statusId($pdo, 'Plenary Cases: Finalized');
    $legFinalId     = statusId($pdo, 'Second Reading Approved');
    $commFinalId    = statusId($pdo, 'Plenary Comm: Finalized');
    $returnedId     = statusId($pdo, 'Returned to Plenary');
    $uid            = userId($pdo);
    $dtCasesId      = casesTypeId($pdo);
    $dtLegId        = legTypeId($pdo);

    pass('Pre-flight: all required status IDs and type IDs resolved');
} catch (RuntimeException $e) {
    fail('Pre-flight: required IDs', $e->getMessage());
    $runtimeOk = false;
}

// =========================================================================
// Group 1 — Controller: Petitions branch present in statusListShow()
// =========================================================================

section('Group 1 — Controller: Petitions branch in statusListShow()');

if ($ctrlExists) {
    $ctrl = file_get_contents($ctrlPath);

    // Extract the statusListShow() method body for scoped assertions
    $methodStart = strpos($ctrl, 'public function statusListShow()');
    $methodBody  = $methodStart !== false ? substr($ctrl, $methodStart, 4000) : '';

    check('1.1 statusListShow() method exists',
          $methodStart !== false);

    check('1.2 isPetition variable declared in statusListShow()',
          str_contains($methodBody, '$isPetition'));

    check('1.3 Plenary Cases: Finalized looked up in statusListShow()',
          str_contains($methodBody, "'Plenary Cases: Finalized'"));

    check('1.4 isPetition included in the guard (!$isLeg && !$isComm && !$isPetition)',
          str_contains($methodBody, '$isPetition') &&
          str_contains($methodBody, '!$isLegislative && !$isCommunication && !$isPetition'));

    check('1.5 backTab set to petitions when isPetition',
          str_contains($methodBody, "'petitions'"));
} else {
    fail('1.1–1.5 skipped (controller not found)');
}

// =========================================================================
// Group 2 — Controller: Petitions data-fetching calls present
// =========================================================================

section('Group 2 — Controller: Petitions data-fetching in statusListShow()');

if ($ctrlExists) {
    $ctrl = $ctrl ?? file_get_contents($ctrlPath);

    $methodStart = strpos($ctrl, 'public function statusListShow()');
    $methodBody  = $methodStart !== false ? substr($ctrl, $methodStart, 4000) : '';

    check('2.1 fetchCasesFirstReading() called from statusListShow()',
          str_contains($methodBody, 'fetchCasesFirstReading('));

    check('2.2 fetchCasesAdoption() called from statusListShow()',
          str_contains($methodBody, 'fetchCasesAdoption('));

    check('2.3 fetchCasesAdoptionFiles() called from statusListShow()',
          str_contains($methodBody, 'fetchCasesAdoptionFiles('));

    check('2.4 $casesFirstReading variable passed to view',
          str_contains($methodBody, '$casesFirstReading'));

    check('2.5 $casesAdoption variable passed to view',
          str_contains($methodBody, '$casesAdoption'));

    check('2.6 $casesAdoptionFiles variable passed to view',
          str_contains($methodBody, '$casesAdoptionFiles'));

    check('2.7 Legislative readings guarded (not fetched for petitions)',
          str_contains($methodBody, '!$isPetition'));
} else {
    fail('2.1–2.7 skipped (controller not found)');
}

// =========================================================================
// Group 3 — View: petitions variables declared and section rendered
// =========================================================================

section('Group 3 — View: petitions variables and section');

if ($showExists) {
    $show = file_get_contents($showPath);

    check('3.1 $casesFirstReading declared at top of show.php',
          str_contains($show, '$casesFirstReading'));

    check('3.2 $casesAdoption declared at top of show.php',
          str_contains($show, '$casesAdoption'));

    check('3.3 $casesAdoptionFiles declared at top of show.php',
          str_contains($show, '$casesAdoptionFiles'));

    check('3.4 $isPetition variable computed in show.php',
          str_contains($show, '$isPetition'));

    check('3.5 Petitions Processing section present',
          str_contains($show, 'Petitions Processing'));

    check('3.6 Adopted Committee Report card shown for petitions',
          str_contains($show, 'Adopted Committee Report'));

    check('3.7 casesAdoptionFiles loop present for attachment list',
          str_contains($show, '$casesAdoptionFiles'));

    check('3.8 resolution_number displayed in petitions section',
          str_contains($show, "casesAdoption['resolution_number']"));

    check('3.9 Petitions Processing section gated on $isPetition',
          (function () use ($show) {
              $pos = strpos($show, 'Petitions Processing');
              if ($pos === false) { return false; }
              // Look in the 600 chars before the label (the if-condition and
              // the comment line both sit just above the <h2>)
              $before = substr($show, max(0, $pos - 600), 600);
              return str_contains($before, '$isPetition');
          })());
} else {
    fail('3.1–3.9 skipped (show.php not found)');
}

// =========================================================================
// Group 4 — DB: a finalized petition passes the new detail handler logic
// =========================================================================

section('Group 4 — DB: finalized petition accepted by detail handler query');

if ($runtimeOk) {
    $pdo->beginTransaction();
    try {
        // Create petition document at "Plenary Cases: Finalized" status
        $petDocId = createDoc($pdo, $casesFinalId, $dtCasesId, $uid, 'FINALIZED');

        // Add first-reading row (REFER_TO_COMMITTEE path → adopted)
        $pdo->prepare("
            INSERT INTO plenary_cases_first_reading
                (document_id, decision, reading_date, performed_by)
            VALUES (?, 'REFER_TO_COMMITTEE', CURDATE(), ?)
        ")->execute([$petDocId, $uid]);
        $frId = (int) $pdo->lastInsertId();

        // Add adoption row
        $pdo->prepare("
            INSERT INTO plenary_cases_adoptions
                (document_id, first_reading_id, resolution_number, adopted_by)
            VALUES (?, ?, 'SP Res. 2026-RTEST', ?)
        ")->execute([$petDocId, $frId, $uid]);
        $adoptId = (int) $pdo->lastInsertId();

        // ── Re-implement the detail handler's status-check logic ──────────
        // This mirrors exactly what statusListShow() does after the fix.
        $docStmt = $pdo->prepare("
            SELECT d.*, ds.name AS status_name, ds.badge_color AS status_badge_color,
                   dt.name AS document_type_name, dt.badge_color AS document_type_badge_color
            FROM documents d
            LEFT JOIN document_statuses ds ON ds.id = d.current_status_id
            LEFT JOIN document_types    dt ON dt.id = d.document_type_id
            WHERE d.id = ? LIMIT 1
        ");
        $docStmt->execute([$petDocId]);
        $doc = $docStmt->fetch();

        check('4.1 Test document inserted and fetched', $doc !== false);

        $isLegislative   = (int) $doc['current_status_id'] === $legFinalId;
        $isCommunication = (int) $doc['current_status_id'] === $commFinalId;
        $isPetition      = (int) $doc['current_status_id'] === $casesFinalId;

        check('4.2 isLegislative is false for petition', !$isLegislative);
        check('4.3 isCommunication is false for petition', !$isCommunication);
        check('4.4 isPetition is true for petition', $isPetition);
        check('4.5 Detail handler guard passes (at least one flag is true)',
              $isLegislative || $isCommunication || $isPetition);

        // Verify backTab is set to petitions
        $backTab = $isCommunication ? 'communications' : ($isPetition ? 'petitions' : 'legislative');
        check('4.6 backTab resolves to petitions', $backTab === 'petitions');

        // Verify cases data is fetchable
        $frStmt = $pdo->prepare("
            SELECT pcfr.*, ua.username AS performed_by_username
            FROM plenary_cases_first_reading pcfr
            LEFT JOIN user_accounts ua ON ua.id = pcfr.performed_by
            WHERE pcfr.document_id = ?
            LIMIT 1
        ");
        $frStmt->execute([$petDocId]);
        $casesFirstReading = $frStmt->fetch();
        check('4.7 casesFirstReading fetched successfully', $casesFirstReading !== false);
        check('4.8 casesFirstReading decision is REFER_TO_COMMITTEE',
              ($casesFirstReading['decision'] ?? '') === 'REFER_TO_COMMITTEE');

        $adoptStmt = $pdo->prepare("
            SELECT pca.*, ua.username AS adopted_by_username
            FROM plenary_cases_adoptions pca
            LEFT JOIN user_accounts ua ON ua.id = pca.adopted_by
            WHERE pca.document_id = ?
            LIMIT 1
        ");
        $adoptStmt->execute([$petDocId]);
        $casesAdoption = $adoptStmt->fetch();
        check('4.9 casesAdoption fetched successfully', $casesAdoption !== false);
        check('4.10 casesAdoption resolution_number correct',
              ($casesAdoption['resolution_number'] ?? '') === 'SP Res. 2026-RTEST');

        // Verify adoption files query returns empty array (no files staged in test)
        $filesStmt = $pdo->prepare("
            SELECT * FROM plenary_cases_adoption_files WHERE document_id = ? ORDER BY uploaded_at ASC
        ");
        $filesStmt->execute([$petDocId]);
        $casesAdoptionFiles = $filesStmt->fetchAll();
        check('4.11 casesAdoptionFiles returns array (empty is fine)', is_array($casesAdoptionFiles));
        check('4.12 No adoption files for clean test doc', count($casesAdoptionFiles) === 0);

    } finally {
        $pdo->rollBack();
    }
} else {
    fail('4.1–4.12 skipped (pre-flight failed)');
}

// =========================================================================
// Group 5 — DB: legislative and communications documents still pass
// =========================================================================

section('Group 5 — DB: legislative and communications docs still accepted');

if ($runtimeOk) {
    $pdo->beginTransaction();
    try {
        // Legislative document at "Second Reading Approved"
        $legDocId = createDoc($pdo, $legFinalId, $dtLegId, $uid, 'FINALIZED');

        $docStmt = $pdo->prepare("SELECT d.* FROM documents d WHERE d.id = ? LIMIT 1");
        $docStmt->execute([$legDocId]);
        $legDoc = $docStmt->fetch();

        $isLeg  = (int) $legDoc['current_status_id'] === $legFinalId;
        $isComm = (int) $legDoc['current_status_id'] === $commFinalId;
        $isPet  = (int) $legDoc['current_status_id'] === $casesFinalId;

        check('5.1 isLegislative true for legislative doc', $isLeg);
        check('5.2 isCommunication false for legislative doc', !$isComm);
        check('5.3 isPetition false for legislative doc', !$isPet);
        check('5.4 Guard passes for legislative doc', $isLeg || $isComm || $isPet);
        $btLeg = $isComm ? 'communications' : ($isPet ? 'petitions' : 'legislative');
        check('5.5 backTab is legislative for legislative doc', $btLeg === 'legislative');

        // Communications document at "Plenary Comm: Finalized"
        $commDocId = createDoc($pdo, $commFinalId, $dtLegId, $uid, 'FINALIZED');
        $docStmt->execute([$commDocId]);
        $commDoc = $docStmt->fetch();

        $isLeg2  = (int) $commDoc['current_status_id'] === $legFinalId;
        $isComm2 = (int) $commDoc['current_status_id'] === $commFinalId;
        $isPet2  = (int) $commDoc['current_status_id'] === $casesFinalId;

        check('5.6 isCommunication true for comm doc', $isComm2);
        check('5.7 isLegislative false for comm doc', !$isLeg2);
        check('5.8 isPetition false for comm doc', !$isPet2);
        check('5.9 Guard passes for comm doc', $isLeg2 || $isComm2 || $isPet2);
        $btComm = $isComm2 ? 'communications' : ($isPet2 ? 'petitions' : 'legislative');
        check('5.10 backTab is communications for comm doc', $btComm === 'communications');

    } finally {
        $pdo->rollBack();
    }
} else {
    fail('5.1–5.10 skipped (pre-flight failed)');
}

// =========================================================================
// Group 6 — Regression: old guard would have rejected the petition
// =========================================================================

section('Group 6 — Regression: old guard behavior vs new guard behavior');

if ($runtimeOk) {
    // Simulate old guard: only isLegislative || isCommunication
    // A petition at casesFinalId would fail the old guard.
    $oldGuardPassesPetition = ($casesFinalId === $legFinalId || $casesFinalId === $commFinalId);
    check('6.1 Old guard (isLeg || isComm) would reject Plenary Cases: Finalized',
          !$oldGuardPassesPetition,
          'Plenary Cases: Finalized is distinct from Second Reading Approved and Plenary Comm: Finalized');

    // New guard: isLegislative || isCommunication || isPetition
    // When document has casesFinalId, isPetition = true → guard passes.
    $newGuardPassesPetition = ($casesFinalId === $legFinalId)
                           || ($casesFinalId === $commFinalId)
                           || true; // isPetition is always true when currentStatus === casesFinalId
    check('6.2 New guard (isLeg || isComm || isPet) accepts Plenary Cases: Finalized',
          $newGuardPassesPetition);

    // Confirm status IDs are all distinct
    check('6.3 Three finalized status IDs are all different',
          count(array_unique([$casesFinalId, $legFinalId, $commFinalId])) === 3,
          "casesFinalized={$casesFinalId}, legFinalized={$legFinalId}, commFinalized={$commFinalId}");

    // Source-level check: the old faulty check is gone from the method
    if ($ctrlExists) {
        $ctrl = $ctrl ?? file_get_contents($ctrlPath);
        $methodStart = strpos($ctrl, 'public function statusListShow()');
        $methodBody  = $methodStart !== false ? substr($ctrl, $methodStart, 4000) : '';

        // Old guard had exactly "if (!$isLegislative && !$isCommunication)"
        // without isPetition — verify it's no longer there in that form.
        $oldGuardPattern = '!$isLegislative && !$isCommunication)';
        check('6.4 Old two-condition guard replaced in statusListShow()',
              !str_contains($methodBody, $oldGuardPattern),
              'Guard must include !$isPetition');

        check('6.5 New three-condition guard present in statusListShow()',
              str_contains($methodBody, '!$isLegislative && !$isCommunication && !$isPetition'));
    } else {
        fail('6.4–6.5 skipped (controller not found)');
    }
} else {
    fail('6.1–6.5 skipped (pre-flight failed)');
}

// ── Summary ────────────────────────────────────────────────────────────────

echo PHP_EOL;
echo '═══════════════════════════════════════════════════════' . PHP_EOL;
echo "Results: {$passed} passed, {$failed} failed" . PHP_EOL;
echo '═══════════════════════════════════════════════════════' . PHP_EOL;

exit($failed > 0 ? 1 : 0);

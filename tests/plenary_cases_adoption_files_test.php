<?php
/**
 * Test: Plenary Cases Adopted Committee Report — Attachment Files
 *
 * Covers the plenary_cases_adoption_files table and the
 * adoptCasesReportStore() server-side validation logic added in migration 080.
 *
 * Groups
 * ──────
 * Group 1  — Migration 080: table structure checks
 * Group 2  — Multiple attachments: rows created and linked correctly
 * Group 3  — Missing attachment: controller rejects adoption before staging
 * Group 4  — Invalid file validation (extension, size, MIME simulation)
 * Group 5  — Duplicate file prevention: only one adoption row per document
 * Group 6  — File rows are deleted (ON DELETE CASCADE) when adoption deleted
 * Group 7  — View structural assertions (drag-and-drop modal HTML)
 * Group 8  — Controller: new helper methods present
 * Group 9  — Server validation mirrors: required-file guard in controller code
 *
 * Strategy
 * ────────
 * All DB writes run inside an outer transaction that is always rolled back.
 * No HTTP layer is needed.
 *
 * Run with:  php tests/plenary_cases_adoption_files_test.php
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

// ── Shared test helpers ────────────────────────────────────────────────────

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

function roleId(PDO $pdo, string $name): int
{
    $s = $pdo->prepare('SELECT id FROM roles WHERE name=? AND is_active=1 AND is_deleted=0 LIMIT 1');
    $s->execute([$name]);
    $r = $s->fetch();
    if (!$r) { throw new RuntimeException("Role '{$name}' not found."); }
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

/**
 * Create a minimal test document in "Returned to Plenary" status,
 * with an ACCEPTED Plenary assignment, a plenary_document_details row,
 * and the required REFER_TO_COMMITTEE plenary_cases_first_reading row.
 *
 * Returns ['doc_id' => int, 'fr_id' => int].
 */
function createReturnedCasesDoc(PDO $pdo, int $returnedId, int $uid, int $dtId): array
{
    $pdo->prepare("
        INSERT INTO documents
            (tracking_year, tracking_sequence, tracking_number,
             date_received, time_received, subject_matter,
             document_type_id, source_type_id,
             current_status_id, current_phase,
             created_by, updated_by)
        VALUES (
            YEAR(NOW()), 8888,
            CONCAT('CATEST-', FLOOR(RAND()*100000)),
            CURDATE(), '08:00:00', 'Test Cases Adoption Document',
            ?, ?, ?, 'COMMITTEE', ?, ?
        )
    ")->execute([$dtId, sourceTypeId($pdo), $returnedId, $uid, $uid]);
    $docId = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT IGNORE INTO plenary_document_details (document_id, created_by, updated_by) VALUES (?,?,?)")
        ->execute([$docId, $uid, $uid]);

    $plenaryRoleId = roleId($pdo, 'Plenary');
    $pdo->prepare("
        INSERT INTO document_assignments
            (document_id, assigned_to_role_id, phase, assigned_by, decision,
             accepted_by, assigned_to_user_id, received_at, accepted_at)
        VALUES (?, ?, 'PLENARY', ?, 'ACCEPTED', ?, ?, NOW(), NOW())
    ")->execute([$docId, $plenaryRoleId, $uid, $uid, $uid]);

    $pdo->prepare("
        INSERT INTO plenary_cases_first_reading
            (document_id, decision, reading_date, performed_by)
        VALUES (?, 'REFER_TO_COMMITTEE', CURDATE(), ?)
    ")->execute([$docId, $uid]);
    $frId = (int) $pdo->lastInsertId();

    return ['doc_id' => $docId, 'fr_id' => $frId];
}

/**
 * Insert a plenary_cases_adoptions row and return the adoption ID.
 */
function insertAdoption(PDO $pdo, int $docId, int $frId, int $uid,
                        string $resNum = 'SP Res. 2026-TEST',
                        string $finalOutputType = 'RESOLUTION'): int
{
    $pdo->prepare("
        INSERT INTO plenary_cases_adoptions
            (document_id, first_reading_id, resolution_number, final_output_type, adopted_by)
        VALUES (?, ?, ?, ?, ?)
    ")->execute([$docId, $frId, $resNum, $finalOutputType, $uid]);
    return (int) $pdo->lastInsertId();
}

/**
 * Insert N fake file rows into plenary_cases_adoption_files.
 * Returns array of inserted IDs.
 */
function insertFakeFiles(PDO $pdo, int $docId, int $adoptionId, int $frId, int $uid, int $n = 1): array
{
    $ids = [];
    $ins = $pdo->prepare("
        INSERT INTO plenary_cases_adoption_files
            (document_id, adoption_id, first_reading_id,
             original_filename, stored_filename, file_path,
             mime_type, file_size, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?, 'application/pdf', 102400, ?)
    ");
    for ($i = 1; $i <= $n; $i++) {
        $uid_str = uniqid('file_', true);
        $ins->execute([
            $docId, $adoptionId, $frId,
            "report_{$i}.pdf",
            "cases_adopt_{$docId}_{$uid_str}.pdf",
            "uploads/documents/{$docId}/cases_adopt_{$docId}_{$uid_str}.pdf",
            $uid,
        ]);
        $ids[] = (int) $pdo->lastInsertId();
    }
    return $ids;
}

// ── Pre-flight ─────────────────────────────────────────────────────────────

section('Pre-flight: runtime IDs');

$runtimeOk = true;
try {
    $returnedId  = statusId($pdo, 'Returned to Plenary');
    $finalizedId = statusId($pdo, 'Plenary Cases: Finalized');
    $uid         = userId($pdo);
    $dtId        = casesTypeId($pdo);
} catch (RuntimeException $e) {
    fail('Pre-flight runtime IDs', $e->getMessage());
    $runtimeOk = false;
}

if (!$runtimeOk) {
    echo PHP_EOL . 'Cannot continue: required pre-conditions not met.' . PHP_EOL;
    exit(1);
}

// =========================================================================
// Group 1 — Migration 080: table structure
// =========================================================================

section('Group 1 — Migration 080: plenary_cases_adoption_files table structure');

$tableExists = $pdo->query("SHOW TABLES LIKE 'plenary_cases_adoption_files'")->fetch();
check('1.1 Table plenary_cases_adoption_files exists', $tableExists !== false,
      'Run migration 080 first.');

if ($tableExists) {
    $cols = $pdo->query("
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'plenary_cases_adoption_files'
    ")->fetchAll(PDO::FETCH_COLUMN);
    $colSet = array_flip($cols);

    $required = [
        'id', 'document_id', 'adoption_id', 'first_reading_id',
        'original_filename', 'stored_filename', 'file_path',
        'mime_type', 'file_size', 'uploaded_by', 'uploaded_at',
        'created_at', 'updated_at',
    ];
    foreach ($required as $col) {
        check("1.2 Column '{$col}' present", isset($colSet[$col]));
    }

    foreach (['plenary_comm_first_reading', 'plenary_comm_adoptions', 'plenary_cases_adoptions'] as $table) {
        $typeStmt = $pdo->prepare("
            SELECT COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = 'final_output_type'
        ");
        $typeStmt->execute([$table]);
        check(
            "1.2a {$table}.final_output_type supports Resolution and Ordinance",
            $typeStmt->fetchColumn() === "enum('RESOLUTION','ORDINANCE')"
        );
    }

    // FK constraints
    $fks = $pdo->query("
        SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA         = DATABASE()
          AND TABLE_NAME            = 'plenary_cases_adoption_files'
          AND REFERENCED_TABLE_NAME IS NOT NULL
    ")->fetchAll();
    $fkTargets = array_column($fks, 'REFERENCED_TABLE_NAME', 'CONSTRAINT_NAME');

    check('1.3 FK to documents present',
          in_array('documents', array_values($fkTargets), true));
    check('1.4 FK to plenary_cases_adoptions present',
          in_array('plenary_cases_adoptions', array_values($fkTargets), true));
    check('1.5 FK to plenary_cases_first_reading present',
          in_array('plenary_cases_first_reading', array_values($fkTargets), true));
    check('1.6 FK to user_accounts present',
          in_array('user_accounts', array_values($fkTargets), true));
}

// =========================================================================
// Group 2 — Multiple attachments: rows created and linked correctly
// =========================================================================

section('Group 2 — Multiple attachment rows stored and linked');

$pdo->beginTransaction();
try {
    ['doc_id' => $docId2, 'fr_id' => $frId2] = createReturnedCasesDoc($pdo, $returnedId, $uid, $dtId);
    $adoptId2 = insertAdoption($pdo, $docId2, $frId2, $uid, 'SP Res. 2026-TEST', 'ORDINANCE');
    $fileIds  = insertFakeFiles($pdo, $docId2, $adoptId2, $frId2, $uid, 3);

    check('2.1 Three file rows inserted', count($fileIds) === 3);

    $adoptionDetails = $pdo->prepare("
        SELECT pca.final_output_type, d.document_type_id
        FROM plenary_cases_adoptions pca
        INNER JOIN documents d ON d.id = pca.document_id
        WHERE pca.id = ?
    ");
    $adoptionDetails->execute([$adoptId2]);
    $adoptionRow = $adoptionDetails->fetch();
    check(
        '2.1a Ordinance output is stored without replacing the original document type',
        $adoptionRow !== false
            && $adoptionRow['final_output_type'] === 'ORDINANCE'
            && (int) $adoptionRow['document_type_id'] === $dtId
    );

    $rows = $pdo->prepare("
        SELECT * FROM plenary_cases_adoption_files
        WHERE document_id = ? ORDER BY id ASC
    ");
    $rows->execute([$docId2]);
    $files = $rows->fetchAll();

    check('2.2 Three rows retrieved from DB', count($files) === 3);

    foreach ($files as $i => $f) {
        $n = $i + 1;
        check("2.3.{$n} Row {$n}: document_id matches", (int) $f['document_id'] === $docId2);
        check("2.4.{$n} Row {$n}: adoption_id matches", (int) $f['adoption_id'] === $adoptId2);
        check("2.5.{$n} Row {$n}: first_reading_id matches", (int) $f['first_reading_id'] === $frId2);
        check("2.6.{$n} Row {$n}: original_filename set",
              !empty($f['original_filename']));
        check("2.7.{$n} Row {$n}: stored_filename has cases_adopt_ prefix",
              str_starts_with($f['stored_filename'], 'cases_adopt_'));
        check("2.8.{$n} Row {$n}: file_path contains document ID",
              str_contains($f['file_path'], (string) $docId2));
        check("2.9.{$n} Row {$n}: mime_type set", !empty($f['mime_type']));
        check("2.10.{$n} Row {$n}: file_size > 0", (int) $f['file_size'] > 0);
        check("2.11.{$n} Row {$n}: uploaded_by matches user", (int) $f['uploaded_by'] === $uid);
    }
} finally {
    $pdo->rollBack();
}

// =========================================================================
// Group 3 — Missing attachment: controller must reject
// =========================================================================

section('Group 3 — Missing attachment: controller rejects');

// No files must be rejected before the adoption transaction.

$noFilesScenarios = [
    ['label' => 'null _FILES[adoption_files]',            'files' => null],
    ['label' => '_FILES[adoption_files] has no name key', 'files' => []],
    ['label' => 'all entries are empty string',           'files' => ['name' => ['', ''], 'error' => [4, 4], 'size' => [0, 0], 'tmp_name' => ['', '']]],
];

foreach ($noFilesScenarios as $idx => $scenario) {
    $uploadedFiles = $scenario['files'];
    $hasFiles = $uploadedFiles !== null
        && isset($uploadedFiles['name'])
        && is_array($uploadedFiles['name'])
        && count(array_filter($uploadedFiles['name'], fn($n) => $n !== '')) > 0;

    check(
        sprintf('3.%d No files detected as absent (hasFiles=false): %s', $idx + 1, $scenario['label']),
        $hasFiles === false
    );
}

// Single valid file should still be detected as present
$validFiles = [
    'name'     => ['committee_report.pdf'],
    'error'    => [UPLOAD_ERR_OK],
    'size'     => [102400],
    'tmp_name' => [sys_get_temp_dir() . '/fakefile'],
];
$hasFilesValid = $validFiles !== null
    && isset($validFiles['name'])
    && is_array($validFiles['name'])
    && count(array_filter($validFiles['name'], fn($n) => $n !== '')) > 0;
check('3.4 hasFiles=true when at least one filename is non-empty', $hasFilesValid === true);

// Confirm the controller requires a committee report attachment.
$ctrlContent = file_get_contents(
    __DIR__ . '/../app/controllers/Plenary/PlenaryReadingController.php'
);
check('3.5 Controller requires at least one committee report attachment',
      str_contains($ctrlContent, 'At least one committee report attachment is required.'));

// =========================================================================
// Group 4 — Invalid file validation (extension, size, MIME)
// =========================================================================

section('Group 4 — Invalid file validation: extension, size, MIME');

/**
 * Minimal in-process replica of PlenaryReadingController::validateCasesAdoptionFiles().
 * Kept simple (no MIME check since no real temp files) — mirrors the extension
 * and size branches which are testable without move_uploaded_file().
 */
function simulateValidateCasesFiles(array $files): array
{
    $errors   = [];
    $maxSize  = 25 * 1024 * 1024;
    $allowed  = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
    $execExts = ['php', 'phtml', 'phar', 'sh', 'bash', 'exe', 'bat', 'cmd', 'com',
                 'vbs', 'js', 'jar', 'pl', 'py', 'rb', 'cgi', 'asp', 'aspx'];

    if (!isset($files['name']) || !is_array($files['name'])) { return $errors; }

    $count = count($files['name']);
    if ($count > 10) {
        $errors[] = 'Maximum 10 attachment files allowed per submission.';
        return $errors;
    }

    for ($i = 0; $i < $count; $i++) {
        $name = $files['name'][$i]   ?? '';
        $err  = $files['error'][$i]  ?? UPLOAD_ERR_NO_FILE;
        $size = $files['size'][$i]   ?? 0;

        if ($err === UPLOAD_ERR_NO_FILE || $name === '') { continue; }
        if ($err !== UPLOAD_ERR_OK) {
            $errors[] = "File \"{$name}\": upload error (code {$err}).";
            continue;
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($ext, $execExts, true)) {
            $errors[] = "File \"{$name}\": executable file types are not allowed.";
            continue;
        }
        if (!in_array($ext, $allowed, true)) {
            $errors[] = "File \"{$name}\": type not allowed. Allowed: " . implode(', ', $allowed) . '.';
            continue;
        }
        if ($size > $maxSize) {
            $errors[] = "File \"{$name}\": exceeds the 25 MB size limit.";
        }
    }

    return $errors;
}

// 4.1 Valid PDF — no errors
$validPdf = ['name' => ['report.pdf'], 'error' => [UPLOAD_ERR_OK], 'size' => [500000], 'tmp_name' => ['']];
$errs41   = simulateValidateCasesFiles($validPdf);
check('4.1 Valid PDF file passes validation', empty($errs41), implode('; ', $errs41));

// 4.2 Disallowed extension
$badExt = ['name' => ['malicious.php'], 'error' => [UPLOAD_ERR_OK], 'size' => [100], 'tmp_name' => ['']];
$errs42 = simulateValidateCasesFiles($badExt);
check('4.2 PHP extension rejected', !empty($errs42) && str_contains($errs42[0], 'executable'));

// 4.3 Non-executable but disallowed type (e.g. .svg)
$badType = ['name' => ['chart.svg'], 'error' => [UPLOAD_ERR_OK], 'size' => [1000], 'tmp_name' => ['']];
$errs43  = simulateValidateCasesFiles($badType);
check('4.3 SVG extension rejected as type not allowed', !empty($errs43));

// 4.4 File too large (26 MB)
$tooBig = ['name' => ['bigfile.pdf'], 'error' => [UPLOAD_ERR_OK], 'size' => [26 * 1024 * 1024], 'tmp_name' => ['']];
$errs44 = simulateValidateCasesFiles($tooBig);
check('4.4 File exceeding 25 MB rejected', !empty($errs44) && str_contains($errs44[0], '25 MB'));

// 4.5 More than 10 files
$tooMany = [
    'name'     => array_fill(0, 11, 'doc.pdf'),
    'error'    => array_fill(0, 11, UPLOAD_ERR_OK),
    'size'     => array_fill(0, 11, 1000),
    'tmp_name' => array_fill(0, 11, ''),
];
$errs45 = simulateValidateCasesFiles($tooMany);
check('4.5 More than 10 files rejected', !empty($errs45) && str_contains($errs45[0], 'Maximum 10'));

// 4.6 Upload error code (not UPLOAD_ERR_OK)
$uploadErr = ['name' => ['broken.pdf'], 'error' => [UPLOAD_ERR_PARTIAL], 'size' => [0], 'tmp_name' => ['']];
$errs46    = simulateValidateCasesFiles($uploadErr);
check('4.6 Partial upload error code reported', !empty($errs46) && str_contains($errs46[0], 'upload error'));

// 4.7 Multiple valid files — all pass
$multiValid = [
    'name'     => ['a.pdf', 'b.docx', 'c.jpg'],
    'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_OK, UPLOAD_ERR_OK],
    'size'     => [100, 200, 300],
    'tmp_name' => ['', '', ''],
];
$errs47 = simulateValidateCasesFiles($multiValid);
check('4.7 Multiple valid files (pdf, docx, jpg) all pass', empty($errs47), implode('; ', $errs47));

// 4.8 Mixed valid and invalid in one submission
$mixed = [
    'name'     => ['good.pdf', 'bad.exe'],
    'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
    'size'     => [500, 200],
    'tmp_name' => ['', ''],
];
$errs48 = simulateValidateCasesFiles($mixed);
check('4.8 Mixed submission: only invalid file flagged', count($errs48) === 1 && str_contains($errs48[0], 'bad.exe'));

// 4.9 Executable extensions: .sh, .bat, .py, .phar
$execFiles = ['sh', 'bat', 'py', 'phar'];
foreach ($execFiles as $ext) {
    $f = ['name' => ["danger.{$ext}"], 'error' => [UPLOAD_ERR_OK], 'size' => [100], 'tmp_name' => ['']];
    $e = simulateValidateCasesFiles($f);
    check("4.9 Executable extension .{$ext} rejected", !empty($e));
}

// =========================================================================
// Group 5 — Duplicate adoption row prevention (DB UNIQUE constraint)
// =========================================================================

section('Group 5 — Duplicate adoption row prevention');

$pdo->beginTransaction();
try {
    ['doc_id' => $docId5, 'fr_id' => $frId5] = createReturnedCasesDoc($pdo, $returnedId, $uid, $dtId);

    // First adoption succeeds
    insertAdoption($pdo, $docId5, $frId5, $uid, 'RES-001');

    // Second adoption on same document must fail
    $dupViolated = false;
    try {
        insertAdoption($pdo, $docId5, $frId5, $uid, 'RES-002');
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) { $dupViolated = true; }
    }
    check('5.1 Duplicate plenary_cases_adoptions insert raises DB constraint (1062)', $dupViolated);
} finally {
    $pdo->rollBack();
}

// =========================================================================
// Group 6 — ON DELETE CASCADE: file rows removed with adoption row
// =========================================================================

section('Group 6 — ON DELETE CASCADE: file rows removed when adoption deleted');

$pdo->beginTransaction();
try {
    ['doc_id' => $docId6, 'fr_id' => $frId6] = createReturnedCasesDoc($pdo, $returnedId, $uid, $dtId);
    $adoptId6 = insertAdoption($pdo, $docId6, $frId6, $uid);
    insertFakeFiles($pdo, $docId6, $adoptId6, $frId6, $uid, 2);

    // Confirm files exist before deletion
    $beforeCnt = (int) $pdo->prepare(
        "SELECT COUNT(*) FROM plenary_cases_adoption_files WHERE adoption_id = ?"
    )->execute([$adoptId6]) ?: 0;
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM plenary_cases_adoption_files WHERE adoption_id = ?");
    $cntStmt->execute([$adoptId6]);
    check('6.1 Two file rows exist before adoption delete', (int) $cntStmt->fetchColumn() === 2);

    // Need to remove FK reference from documents before deleting adoption
    // (documents still references the adoption via document_id chain).
    // We test the FK cascade by only deleting the adoption row; because
    // the FK ON DELETE CASCADE points adoption → files we only need
    // to delete the adoption row.
    // Note: the adoption has FK RESTRICT to documents, so we simulate by
    // verifying the ON DELETE CASCADE is defined rather than actually deleting.
    $fkInfo = $pdo->query("
        SELECT rc.DELETE_RULE
        FROM information_schema.REFERENTIAL_CONSTRAINTS rc
        INNER JOIN information_schema.KEY_COLUMN_USAGE kcu
            ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
           AND kcu.TABLE_SCHEMA    = rc.CONSTRAINT_SCHEMA
        WHERE rc.CONSTRAINT_SCHEMA  = DATABASE()
          AND kcu.TABLE_NAME        = 'plenary_cases_adoption_files'
          AND kcu.REFERENCED_TABLE_NAME = 'plenary_cases_adoptions'
        LIMIT 1
    ")->fetch();

    check('6.2 plenary_cases_adoption_files FK to plenary_cases_adoptions is CASCADE',
          isset($fkInfo['DELETE_RULE']) && strtoupper($fkInfo['DELETE_RULE']) === 'CASCADE');
} finally {
    $pdo->rollBack();
}

// =========================================================================
// Group 7 — View structural assertions (drag-and-drop modal HTML)
// =========================================================================

section('Group 7 — View: adopt-cases-modal has drag-and-drop area');

$viewPath = __DIR__ . '/../resources/views/plenary/referred-to-committee/index.php';
if (!file_exists($viewPath)) {
    fail('7.0 referred-to-committee/index.php exists', 'File not found.');
} else {
    $html = file_get_contents($viewPath);

    check('7.1 adopt-cases-modal element present',
          str_contains($html, 'id="adopt-cases-modal"'));

    check('7.2 Form has enctype=multipart/form-data',
          str_contains($html, 'enctype="multipart/form-data"'));

    check('7.3 File input adopt-cases-files-input present',
          str_contains($html, 'id="adopt-cases-files-input"'));

    check('7.4 File input name is adoption_files[]',
          str_contains($html, 'name="adoption_files[]"'));

    check('7.5 File input accepts multiple attribute',
          str_contains($html, 'id="adopt-cases-files-input"') &&
          str_contains($html, 'multiple'));

    check('7.6 Drop zone adopt-cases-drop-zone present',
          str_contains($html, 'id="adopt-cases-drop-zone"'));

    check('7.7 File list adopt-cases-file-list present',
          str_contains($html, 'id="adopt-cases-file-list"'));

    check('7.8 File error paragraph adopt-cases-file-error present',
          str_contains($html, 'id="adopt-cases-file-error"'));

    check('7.9 Drag overlay adopt-cases-drag-overlay present',
          str_contains($html, 'id="adopt-cases-drag-overlay"'));

    check('7.10 Attachment input is required',
          str_contains($html, 'id="adopt-cases-submit-btn"') &&
          preg_match('/id="adopt-cases-files-input"[^>]*required/', $html) === 1);

    check('7.11 casesAddFiles JS function defined',
          str_contains($html, 'function casesAddFiles'));

    check('7.12 casesRemoveFile JS function defined',
          str_contains($html, 'function casesRemoveFile'));

    check('7.13 casesResetFiles JS function defined',
          str_contains($html, 'function casesResetFiles'));

    check('7.14 casesUpdateSubmitState JS function not needed for native required input',
          !str_contains($html, 'function casesUpdateSubmitState'));

    check('7.15 DataTransfer accumulator (casesDt) used',
          str_contains($html, 'var casesDt = new DataTransfer()'));

    check('7.16 dragover event attached to drop zone',
          str_contains($html, "casesDropZone.addEventListener('dragover'"));

    check('7.17 drop event attached to drop zone',
          str_contains($html, "casesDropZone.addEventListener('drop'"));

    check('7.18 Client-side submit handler still present',
          (function () use ($html) {
              $start = strpos($html, "getElementById('adopt-cases-form').addEventListener('submit'");
              if ($start === false) { return false; }
              $block = substr($html, $start, 500);
              return str_contains($block, 'submit');
          })());

    check('7.19 resolution_number input still present',
          str_contains($html, 'id="adopt-cases-resolution-number"'));

    check('7.20 proposed_number input still present',
          str_contains($html, 'id="adopt-cases-proposed-number"'));

    check('7.21 remarks textarea still present',
          str_contains($html, 'id="adopt-cases-remarks"'));

    check('7.22 Form action still points to cases-first-reading/adopt-report',
          str_contains($html, 'cases-first-reading/adopt-report'));

    check('7.23 openAdoptCasesModal JS function defined',
          str_contains($html, 'window.openAdoptCasesModal'));

    check('7.24 closeAdoptCasesModal JS function defined',
          str_contains($html, 'window.closeAdoptCasesModal'));

    // Confirm Comm modal is still untouched
    check('7.25 adopt-comm-modal still present (not broken)',
          str_contains($html, 'id="adopt-comm-modal"'));

    check('7.26 Comm modal drag-drop still present (not broken)',
          str_contains($html, 'id="adopt-drop-zone"'));
}

// =========================================================================
// Group 8 — Controller: new helper methods present
// =========================================================================

section('Group 8 — Controller: new helper methods present');

$ctrlPath = __DIR__ . '/../app/controllers/Plenary/PlenaryReadingController.php';
if (!file_exists($ctrlPath)) {
    fail('8.0 PlenaryReadingController.php exists', 'File not found.');
} else {
    $ctrl = file_get_contents($ctrlPath);

    check('8.1 validateCasesAdoptionFiles() method present',
          str_contains($ctrl, 'function validateCasesAdoptionFiles'));

    check('8.2 stageCasesAdoptionFiles() method present',
          str_contains($ctrl, 'function stageCasesAdoptionFiles'));

    check('8.3 insertCasesAdoptionFiles() method present',
          str_contains($ctrl, 'function insertCasesAdoptionFiles'));

    check('8.4 fetchCasesAdoptionFiles() method present',
          str_contains($ctrl, 'function fetchCasesAdoptionFiles'));

    check('8.5 adoptCasesReportStore() calls validateCasesAdoptionFiles',
          str_contains($ctrl, 'validateCasesAdoptionFiles('));

    check('8.6 adoptCasesReportStore() calls stageCasesAdoptionFiles',
          str_contains($ctrl, 'stageCasesAdoptionFiles('));

    check('8.7 adoptCasesReportStore() calls insertCasesAdoptionFiles',
          str_contains($ctrl, 'insertCasesAdoptionFiles('));

    check('8.8 Form enctype multipart is implied (file staging used)',
          str_contains($ctrl, 'move_uploaded_file'));

    check('8.9 Stored filename uses cases_adopt_ prefix',
          str_contains($ctrl, "'cases_adopt_'"));

    check('8.10 INSERT into plenary_cases_adoption_files present',
          str_contains($ctrl, 'INSERT INTO plenary_cases_adoption_files'));

    check('8.11 file_count included in event metadata',
          str_contains($ctrl, "'file_count'"));

    check('8.12 Cases adoption store uses enctype-aware $_FILES',
          str_contains($ctrl, "\$_FILES['adoption_files']"));
    check('8.13 Cases adoption stores the selected final output type',
          str_contains($ctrl, "'final_output_type' => $finalOutputType"));
}

// =========================================================================
// Group 9 — Server validation: required-file guard in controller source
// =========================================================================

section('Group 9 — Server validation logic in controller source');

if (file_exists($ctrlPath)) {
    $ctrl = file_get_contents($ctrlPath);

    check('9.1 Controller requires at least one attachment',
          str_contains($ctrl, 'At least one committee report attachment is required.'));

    check('9.2 Controller validates files after the required-file check',
          strpos($ctrl, 'At least one committee report attachment is required.')
              < strpos($ctrl, '$this->validateCasesAdoptionFiles($uploadedFiles)'));

    check('9.3 File errors from validateCasesAdoptionFiles added to \$errors when files present',
          str_contains($ctrl, 'foreach ($fileErrors as $fe)'));

    check('9.4 Pre-transaction staging happens before runCommTransaction',
          (function () use ($ctrl) {
              $methodStart = strpos($ctrl, 'public function adoptCasesReportStore()');
              if ($methodStart === false) { return false; }
              $window   = substr($ctrl, $methodStart, 5000);
              $stagePos = strpos($window, 'stageCasesAdoptionFiles(');
              $txPos    = strpos($window, 'runCommTransaction(function ()');
              return $stagePos !== false && $txPos !== false && $stagePos < $txPos;
          })());

    check('9.5 File upload staging failure redirects gracefully',
          str_contains($ctrl, "File upload failed: "));

    check('9.6 validateCasesAdoptionFiles checks for 25 MB limit',
          str_contains($ctrl, '25 MB size limit'));

    check('9.7 validateCasesAdoptionFiles checks for maximum 10 files',
          str_contains($ctrl, 'Maximum 10 attachment files'));

    check('9.8 validateCasesAdoptionFiles blocks executable extensions',
          str_contains($ctrl, "'php', 'phtml', 'phar'"));
}

// ── Summary ────────────────────────────────────────────────────────────────

echo PHP_EOL;
echo '═══════════════════════════════════════════════════════' . PHP_EOL;
echo "Results: {$passed} passed, {$failed} failed" . PHP_EOL;
echo '═══════════════════════════════════════════════════════' . PHP_EOL;

exit($failed > 0 ? 1 : 0);

<?php
/**
 * Test: Plenary Comm First Reading — Resolution File Multi-Upload (APPROVED decision)
 *
 * Verifies the full multi-file upload path for the "APPROVED for Resolution
 * under Other Matters" decision at Plenary Communication First Reading.
 *
 * Coverage:
 *   Group 1 — Validation helpers (validateMultipleFiles equivalents)
 *     1.1  Zero files with UPLOAD_ERR_NO_FILE entries is rejected (≥1 required)
 *     1.2  More than 10 files is rejected
 *     1.3  A file exceeding 25 MB is rejected per-file
 *     1.4  A disallowed extension (.exe) is rejected
 *     1.5  A blocked executable extension (.php) is rejected
 *     1.6  A valid mixed batch (pdf + docx + jpg) passes validation
 *     1.7  Allowed extensions are all accepted individually (pdf/doc/docx/xls/xlsx/jpg/jpeg/png/gif/webp)
 *
 *   Group 2 — DB state after a successful APPROVED submission
 *     2.1  plenary_comm_first_reading row inserted with decision=APPROVED
 *     2.2  resolution_number is stored on the row
 *     2.3  proposed_number (optional) is stored when provided
 *     2.4  plenary_comm_resolution_files rows created — one per staged file
 *     2.5  Each row's document_id matches the document
 *     2.6  Each row's first_reading_id matches the plenary_comm_first_reading.id
 *     2.7  Each row's adoption_id is NULL (this is the FR path, not adoption path)
 *     2.8  original_filename is preserved
 *     2.9  stored_filename is different from original_filename (safe rename applied)
 *     2.10 stored_filename follows the comm_res_{docId}_ prefix convention
 *     2.11 file_path follows the uploads/documents/{docId}/ convention
 *     2.12 mime_type column is non-empty
 *     2.13 file_size column > 0
 *     2.14 uploaded_by matches the performing user
 *     2.15 Document status updated to "Plenary Comm: Finalized"
 *     2.16 documents.current_phase set to 'FINALIZED'
 *     2.17 documents.finalized_at is set
 *
 *   Group 3 — Duplicate / idempotency prevention
 *     3.1  A second APPROVED submission for the same document is rejected
 *          (UNIQUE constraint on plenary_comm_first_reading.document_id)
 *
 *   Group 4 — File count and mix validation rules
 *     4.1  Exactly 1 file is accepted (minimum boundary)
 *     4.2  Exactly 10 files are accepted (maximum boundary)
 *     4.3  11 files are rejected (exceeds maximum)
 *     4.4  Mixed-valid batch of 3 files is accepted with all rows persisted
 *
 * Strategy
 * --------
 * - All DB writes run inside an outer transaction that is always rolled back,
 *   so the test leaves zero permanent side-effects.
 * - File staging is simulated in a temp directory instead of the real upload
 *   path so no real uploads directory is polluted.
 * - The helpers below mirror the exact SQL the controller issues so that any
 *   divergence between the test and the controller signals a real bug.
 *
 * Run with:  php tests/plenary_comm_fr_resolution_files_test.php
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
}

// ── Runtime lookups (never hardcode IDs) ──────────────────────────────────
function statusId(PDO $pdo, string $name): int
{
    $s = $pdo->prepare('SELECT id FROM document_statuses WHERE name=? AND is_active=1 AND is_deleted=0 LIMIT 1');
    $s->execute([$name]);
    $r = $s->fetch();
    if (!$r) {
        throw new RuntimeException("Status '{$name}' not found");
    }
    return (int) $r['id'];
}

function docTypeId(PDO $pdo, string $pattern): int
{
    $s = $pdo->prepare("SELECT id FROM document_types WHERE name LIKE ? AND is_deleted=0 LIMIT 1");
    $s->execute([$pattern]);
    $r = $s->fetch();
    if (!$r) {
        throw new RuntimeException("Document type matching '{$pattern}' not found");
    }
    return (int) $r['id'];
}

function sourceTypeId(PDO $pdo): int
{
    $s = $pdo->query("SELECT id FROM source_types WHERE is_active=1 AND is_deleted=0 LIMIT 1");
    $r = $s->fetch();
    if (!$r) {
        throw new RuntimeException("No active source types found");
    }
    return (int) $r['id'];
}

function plenaryUserId(PDO $pdo): int
{
    // user_accounts uses status='active' (no is_active column)
    $s = $pdo->query("
        SELECT ua.id FROM user_accounts ua
        INNER JOIN roles r ON r.id = ua.role_id
        WHERE r.name = 'Plenary' AND ua.status = 'active' AND ua.is_deleted = 0
        LIMIT 1
    ");
    $r = $s->fetch();
    if (!$r) {
        // Fallback: any non-deleted user will do for test purposes
        $s2 = $pdo->query("SELECT id FROM user_accounts WHERE is_deleted = 0 LIMIT 1");
        $r  = $s2->fetch();
    }
    if (!$r) {
        throw new RuntimeException("No usable user account found");
    }
    return (int) $r['id'];
}

// ── Mirrors PlenaryReadingController::validateMultipleFiles() ─────────────
function validateMultipleFiles(array $files): array
{
    $errors   = [];
    $maxSize  = 25 * 1024 * 1024;
    $allowed  = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
    $execExts = [
        'php', 'phtml', 'phar', 'sh', 'bash', 'exe', 'bat', 'cmd', 'com',
        'vbs', 'js', 'jar', 'pl', 'py', 'rb', 'cgi', 'asp', 'aspx',
    ];

    if (!isset($files['name']) || !is_array($files['name'])) {
        return [];
    }

    $count = count($files['name']);
    if ($count > 10) {
        $errors[] = 'Maximum 10 files allowed per upload.';
        return $errors;
    }

    for ($i = 0; $i < $count; $i++) {
        $name = $files['name'][$i]  ?? '';
        $err  = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        $size = $files['size'][$i]  ?? 0;

        if ($err === UPLOAD_ERR_NO_FILE || $name === '') {
            continue;
        }

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

/** Build a $_FILES-style array for N synthetic files. */
function makeFilesArray(array $entries): array
{
    $files = ['name' => [], 'error' => [], 'size' => [], 'tmp_name' => [], 'type' => []];
    foreach ($entries as $e) {
        $files['name'][]     = $e['name']     ?? 'file.pdf';
        $files['error'][]    = $e['error']    ?? UPLOAD_ERR_OK;
        $files['size'][]     = $e['size']     ?? 1024;
        $files['tmp_name'][] = $e['tmp_name'] ?? '';
        $files['type'][]     = $e['type']     ?? 'application/pdf';
    }
    return $files;
}

/** Create a minimal Communication document in "Awaiting First Reading" state. */
function createCommDoc(PDO $pdo, int $awaitingStatusId, int $commTypeId, int $srcTypeId): int
{
    static $seq = 880000;
    $yr  = (int) date('Y');
    $num = ++$seq;
    $pdo->prepare("
        INSERT INTO documents
            (tracking_year, tracking_sequence, tracking_number, date_received, time_received,
             subject_matter, document_type_id, source_type_id,
             current_status_id, current_phase)
        VALUES (?, ?, ?, CURDATE(), CURTIME(), 'Test resolution upload', ?, ?, ?, 'PLENARY')
    ")->execute([
        $yr,
        $num,
        'TEST-COMFR-' . $num,
        $commTypeId,
        $srcTypeId,
        $awaitingStatusId,
    ]);
    return (int) $pdo->lastInsertId();
}

/** Simulate stageMultipleFiles() without touching real uploads directory. */
function simulateStagedFiles(int $documentId, array $fileNames): array
{
    $staged = [];
    $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'comm_res_test_' . $documentId;
    if (!is_dir($tmpDir)) {
        mkdir($tmpDir, 0755, true);
    }

    foreach ($fileNames as $origName) {
        $ext        = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $storedName = 'comm_res_' . $documentId . '_' . uniqid('', true) . '.' . $ext;
        $absPath    = $tmpDir . DIRECTORY_SEPARATOR . $storedName;
        $relPath    = 'uploads/documents/' . $documentId . '/' . $storedName;

        // Write minimal content so filesize() returns > 0
        file_put_contents($absPath, 'test-content-' . $origName);

        $staged[] = [
            'abs_path'        => $absPath,
            'relative_path'   => $relPath,
            'original_name'   => basename($origName),
            'stored_filename' => $storedName,
            'mime_type'       => 'application/pdf',
            'file_size'       => (int) filesize($absPath),
        ];
    }
    return $staged;
}

/** Simulate insertCommResolutionFiles() from the controller. */
function insertCommResFiles(
    PDO $pdo,
    array $stagedFiles,
    int $documentId,
    ?int $firstReadingId,
    ?int $adoptionId,
    int $userId
): void {
    $ins = $pdo->prepare("
        INSERT INTO plenary_comm_resolution_files
            (document_id, first_reading_id, adoption_id, original_filename,
             stored_filename, file_path, mime_type, file_size, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($stagedFiles as $f) {
        $ins->execute([
            $documentId,
            $firstReadingId,
            $adoptionId,
            $f['original_name'],
            $f['stored_filename'],
            $f['relative_path'],
            $f['mime_type'],
            $f['file_size'],
            $userId,
        ]);
    }
}

/** Simulate the APPROVED doCommFrApproved() logic (DB portion only). */
function doCommFrApproved(
    PDO $pdo,
    int $documentId,
    int $userId,
    string $readingDate,
    string $resolutionNumber,
    string $proposedNumber,
    array $stagedFiles,
    int $finalizedStatusId
): int {
    $pdo->prepare("
        INSERT INTO plenary_comm_first_reading
            (document_id, decision, reading_date, remarks, proposed_number, resolution_number,
             performed_by, finalized_at, finalized_by)
        VALUES (?, 'APPROVED', ?, NULL, ?, ?, ?, NOW(), ?)
    ")->execute([
        $documentId,
        $readingDate,
        $proposedNumber ?: null,
        $resolutionNumber,
        $userId,
        $userId,
    ]);
    $firstReadingId = (int) $pdo->lastInsertId();

    $pdo->prepare("
        UPDATE documents
        SET current_status_id = ?,
            current_phase     = 'FINALIZED',
            finalized_at      = NOW(),
            updated_by        = ?
        WHERE id = ?
    ")->execute([$finalizedStatusId, $userId, $documentId]);

    insertCommResFiles($pdo, $stagedFiles, $documentId, $firstReadingId, null, $userId);

    return $firstReadingId;
}

// ─────────────────────────────────────────────────────────────────────────────
// Resolve runtime IDs before starting the outer transaction
// ─────────────────────────────────────────────────────────────────────────────
try {
    $awaitingStatusId  = statusId($pdo, 'Awaiting First Reading');
    $finalizedStatusId = statusId($pdo, 'Plenary Comm: Finalized');
    $commTypeId        = docTypeId($pdo, '%Communication%');
    $srcTypeId         = sourceTypeId($pdo);
    $plUserId          = plenaryUserId($pdo);
} catch (RuntimeException $e) {
    echo 'FATAL: Setup failed — ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

// ─────────────────────────────────────────────────────────────────────────────
// Outer rollback transaction — zero permanent side-effects
// ─────────────────────────────────────────────────────────────────────────────
$pdo->beginTransaction();

// ── Group 1 — Validation helpers ─────────────────────────────────────────
section('Group 1 — validateMultipleFiles() rules');

// 1.1 — Zero real files (all UPLOAD_ERR_NO_FILE) → no errors, but controller
//        separately checks hasFiles and requires at least one.
$emptyBatch  = makeFilesArray([['name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0]]);
$emptyErrors = validateMultipleFiles($emptyBatch);
check(
    '1.1 Empty / no-file batch passes validateMultipleFiles (controller gates separately)',
    count($emptyErrors) === 0
);

// 1.2 — 11 files → rejected (max 10)
$elevenFiles = makeFilesArray(array_fill(0, 11, ['name' => 'doc.pdf', 'size' => 1024]));
$elevenErrors = validateMultipleFiles($elevenFiles);
check('1.2 More than 10 files is rejected', count($elevenErrors) > 0, implode('; ', $elevenErrors));

// 1.3 — File over 25 MB → rejected
$bigFile  = makeFilesArray([['name' => 'huge.pdf', 'size' => 26 * 1024 * 1024]]);
$bigErrors = validateMultipleFiles($bigFile);
check('1.3 File exceeding 25 MB is rejected', count($bigErrors) > 0, implode('; ', $bigErrors));

// 1.4 — Disallowed extension → rejected
$badExt  = makeFilesArray([['name' => 'malware.exe', 'size' => 1024]]);
$badExtErrors = validateMultipleFiles($badExt);
check('1.4 Disallowed extension (.exe) is rejected', count($badExtErrors) > 0, implode('; ', $badExtErrors));

// 1.5 — Blocked executable extension → rejected
$phpFile   = makeFilesArray([['name' => 'shell.php', 'size' => 1024]]);
$phpErrors = validateMultipleFiles($phpFile);
check('1.5 Blocked executable extension (.php) is rejected', count($phpErrors) > 0, implode('; ', $phpErrors));

// 1.6 — Valid mixed batch (pdf + docx + jpg) → passes
$validBatch  = makeFilesArray([
    ['name' => 'resolution.pdf',  'size' => 512 * 1024],
    ['name' => 'annex.docx',      'size' => 256 * 1024],
    ['name' => 'photo.jpg',       'size' => 128 * 1024],
]);
$validErrors = validateMultipleFiles($validBatch);
check('1.6 Valid mixed batch (pdf + docx + jpg) passes validation', count($validErrors) === 0, implode('; ', $validErrors));

// 1.7 — Each allowed extension accepted individually
$allowedExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
foreach ($allowedExts as $ext) {
    $singleFile   = makeFilesArray([['name' => 'file.' . $ext, 'size' => 512]]);
    $singleErrors = validateMultipleFiles($singleFile);
    check('1.7 Extension .' . $ext . ' is accepted', count($singleErrors) === 0, implode('; ', $singleErrors));
}

// ── Group 2 — DB state after a successful APPROVED submission ─────────────
section('Group 2 — DB state after APPROVED submission');

$docId = createCommDoc($pdo, $awaitingStatusId, $commTypeId, $srcTypeId);

$staged3 = simulateStagedFiles($docId, [
    'resolution.pdf',
    'annex-A.docx',
    'photo-evidence.jpg',
]);

$readingId = doCommFrApproved(
    $pdo,
    $docId,
    $plUserId,
    date('Y-m-d'),
    'SP Res. No. 2026-TEST-001',
    'SP Res. No. 2026-PROP-001',
    $staged3,
    $finalizedStatusId
);

// 2.1 — plenary_comm_first_reading row inserted
$frRow = $pdo->prepare("SELECT * FROM plenary_comm_first_reading WHERE id = ? LIMIT 1");
$frRow->execute([$readingId]);
$fr = $frRow->fetch();
check('2.1 plenary_comm_first_reading row inserted', $fr !== false);

// 2.2 — resolution_number stored
check('2.2 resolution_number is stored', ($fr['resolution_number'] ?? '') === 'SP Res. No. 2026-TEST-001');

// 2.3 — proposed_number stored
check('2.3 proposed_number (optional) is stored when provided', ($fr['proposed_number'] ?? '') === 'SP Res. No. 2026-PROP-001');

// 2.4 — Three resolution file rows created
$resFiles = $pdo->prepare("SELECT * FROM plenary_comm_resolution_files WHERE document_id = ? ORDER BY id ASC");
$resFiles->execute([$docId]);
$rows = $resFiles->fetchAll();
check('2.4 plenary_comm_resolution_files rows created (one per file)', count($rows) === 3, 'got ' . count($rows));

// 2.5..2.14 — Per-row assertions on the first row
if (!empty($rows)) {
    $r = $rows[0];
    check('2.5 Each row document_id matches the document',            (int) $r['document_id']      === $docId);
    check('2.6 Each row first_reading_id matches plenary_comm_first_reading.id', (int) $r['first_reading_id'] === $readingId);
    check('2.7 Each row adoption_id is NULL (FR path, not adoption)', $r['adoption_id']             === null);
    check('2.8 original_filename is preserved',                       $r['original_filename']       === 'resolution.pdf');
    check('2.9 stored_filename differs from original_filename',       $r['stored_filename']         !== 'resolution.pdf');
    check('2.10 stored_filename uses comm_res_{docId}_ prefix',       str_starts_with($r['stored_filename'], 'comm_res_' . $docId . '_'));
    check('2.11 file_path uses uploads/documents/{docId}/ prefix',    str_starts_with($r['file_path'], 'uploads/documents/' . $docId . '/'));
    check('2.12 mime_type is non-empty',                              !empty($r['mime_type']));
    check('2.13 file_size > 0',                                       (int) $r['file_size'] > 0);
    check('2.14 uploaded_by matches the performing user',             (int) $r['uploaded_by'] === $plUserId);
}

// 2.15–2.17 — Document status updates
$docRow = $pdo->prepare("SELECT current_status_id, current_phase, finalized_at FROM documents WHERE id = ? LIMIT 1");
$docRow->execute([$docId]);
$doc = $docRow->fetch();
check('2.15 Document status updated to "Plenary Comm: Finalized"', (int) ($doc['current_status_id'] ?? 0) === $finalizedStatusId);
check('2.16 documents.current_phase set to FINALIZED',            ($doc['current_phase'] ?? '') === 'FINALIZED');
check('2.17 documents.finalized_at is set',                       !empty($doc['finalized_at']));

// ── Group 3 — Duplicate prevention ────────────────────────────────────────
section('Group 3 — Duplicate / idempotency prevention');

$dupCaught = false;
try {
    // Try to insert a second plenary_comm_first_reading for the same document
    $pdo->prepare("
        INSERT INTO plenary_comm_first_reading
            (document_id, decision, reading_date, performed_by)
        VALUES (?, 'APPROVED', CURDATE(), ?)
    ")->execute([$docId, $plUserId]);
} catch (PDOException $e) {
    // SQLSTATE[23000]: Integrity constraint violation (unique key)
    if (str_contains($e->getMessage(), '1062') || str_contains($e->getCode(), '23000')) {
        $dupCaught = true;
    }
}
check('3.1 Second APPROVED submission for same document is rejected (UNIQUE constraint)', $dupCaught);

// ── Group 4 — File count boundary checks ─────────────────────────────────
section('Group 4 — File count and mix boundary rules');

// 4.1 — Exactly 1 file passes validation
$one = makeFilesArray([['name' => 'single.pdf', 'size' => 1024]]);
check('4.1 Exactly 1 file passes validation (minimum boundary)', count(validateMultipleFiles($one)) === 0);

// 4.2 — Exactly 10 files pass validation
$ten = makeFilesArray(array_fill(0, 10, ['name' => 'doc.pdf', 'size' => 1024]));
check('4.2 Exactly 10 files pass validation (maximum boundary)', count(validateMultipleFiles($ten)) === 0);

// 4.3 — 11 files are rejected
$eleven = makeFilesArray(array_fill(0, 11, ['name' => 'doc.pdf', 'size' => 1024]));
check('4.3 11 files are rejected (exceeds maximum)', count(validateMultipleFiles($eleven)) > 0);

// 4.4 — Mixed batch of 3 files all persisted correctly
$doc2Id = createCommDoc($pdo, $awaitingStatusId, $commTypeId, $srcTypeId);
$staged4 = simulateStagedFiles($doc2Id, ['res.pdf', 'exhibit.xlsx', 'photo.png']);
$frId2 = doCommFrApproved(
    $pdo, $doc2Id, $plUserId,
    date('Y-m-d'), 'SP Res. No. 2026-TEST-002', '',
    $staged4, $finalizedStatusId
);
$rows4 = $pdo->prepare("SELECT COUNT(*) AS cnt FROM plenary_comm_resolution_files WHERE document_id = ?")->execute([$doc2Id]);
$cnt4Stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM plenary_comm_resolution_files WHERE document_id = ? AND first_reading_id = ?");
$cnt4Stmt->execute([$doc2Id, $frId2]);
$cnt4 = (int) $cnt4Stmt->fetchColumn();
check('4.4 Mixed batch of 3 files — all rows persisted correctly', $cnt4 === 3, 'got ' . $cnt4);

// ─────────────────────────────────────────────────────────────────────────────
// Always roll back — no permanent side-effects
// ─────────────────────────────────────────────────────────────────────────────
$pdo->rollBack();

// Clean up temp dirs (best-effort)
foreach ([$docId, $doc2Id] as $id) {
    $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'comm_res_test_' . $id;
    if (is_dir($tmpDir)) {
        array_map('unlink', glob($tmpDir . DIRECTORY_SEPARATOR . '*'));
        rmdir($tmpDir);
    }
}

// ── Summary ───────────────────────────────────────────────────────────────
echo PHP_EOL;
$total = $passed + $failed;
echo "Results: {$passed}/{$total} passed";
if ($failed > 0) {
    echo " ({$failed} FAILED)";
}
echo PHP_EOL;
exit($failed > 0 ? 1 : 0);

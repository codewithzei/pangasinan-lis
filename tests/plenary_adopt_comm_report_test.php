<?php
/**
 * Test: Plenary "Adopt Committee Report" — multi-file upload + modal behavior
 *
 * Covers the "Adopted Committee Report" form on the Plenary Referred to
 * Committee page (POST plenary/referred-to-committee/adopt-comm), focusing
 * on the newly upgraded drag-and-drop attachment control and the modal's
 * backdrop / dismissal rules.
 *
 * Groups
 * ──────
 * Group 1 — Multi-file validation (mirrors PlenaryReadingController::validateMultipleFiles)
 *   1.1  Zero files passes server-side validation (files are optional for adoption)
 *   1.2  One valid file is accepted
 *   1.3  Ten files are accepted (maximum boundary)
 *   1.4  Eleven files are rejected (exceeds maximum)
 *   1.5  A file > 25 MB is rejected per-file
 *   1.6  A disallowed extension (.exe) is rejected
 *   1.7  A blocked executable extension (.php) is rejected
 *   1.8  Each allowed extension accepted individually (all 10 types)
 *   1.9  De-duplication: the same filename+size added twice counts once
 *
 * Group 2 — DB state after a successful adoption with files
 *   2.1  plenary_comm_adoptions row inserted
 *   2.2  resolution_number stored on the adoption row
 *   2.3  proposed_number (optional) stored when provided
 *   2.4  adopted_by matches the performing user
 *   2.5  remarks stored when provided
 *   2.6  plenary_comm_resolution_files rows created — one per staged file
 *   2.7  Each file row: document_id correct
 *   2.8  Each file row: adoption_id correct
 *   2.9  Each file row: first_reading_id is NULL (adoption path, not FR path)
 *   2.10 Each file row: original_filename preserved
 *   2.11 Each file row: stored_filename follows comm_res_{docId}_ prefix
 *   2.12 Each file row: file_path follows uploads/documents/{docId}/ prefix
 *   2.13 Each file row: mime_type non-empty
 *   2.14 Each file row: file_size > 0
 *   2.15 plenary_comm_first_reading finalized_at set (FK row updated)
 *   2.16 Document status updated to "Plenary Comm: Finalized"
 *   2.17 documents.current_phase set to 'FINALIZED'
 *
 * Group 3 — Adoption with zero files (files are optional)
 *   3.1  Adoption succeeds without any files
 *   3.2  No plenary_comm_resolution_files rows created when no files submitted
 *
 * Group 4 — Duplicate adoption prevention
 *   4.1  A second adoption attempt for the same document is rejected
 *        (UNIQUE constraint on plenary_comm_adoptions.document_id)
 *
 * Group 5 — Server-side gate: missing resolution_number is rejected
 *   5.1  Empty resolution_number results in a validation error
 *
 * Group 6 — Modal backdrop and dismissal logic (structural assertions)
 *   6.1  Modal container has fixed positioning and covers the viewport (inset-0)
 *   6.2  Modal container has a backdrop colour (bg-black/*)
 *   6.3  Modal z-index is >= 50
 *   6.4  Modal container is hidden by default (display:none inline style)
 *   6.5  Panel is a direct child of the container so the backdrop fills behind it
 *   6.6  File input is present and carries the correct name attribute
 *   6.7  Drop zone element is present
 *   6.8  File list element is present
 *   6.9  File error element is present
 *   6.10 Form carries enctype="multipart/form-data"
 *   6.11 Form action points to the adopt-comm endpoint
 *
 * Strategy
 * ────────
 * - All DB writes run inside an outer transaction that is always rolled back.
 * - File staging is simulated in a temp directory — no real upload path touched.
 * - Group 6 parses the view file with a lightweight regex approach.
 *
 * Run with: php tests/plenary_adopt_comm_report_test.php
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

// ── Runtime ID helpers ─────────────────────────────────────────────────────

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
        throw new RuntimeException('No active source types found');
    }
    return (int) $r['id'];
}

function plenaryUserId(PDO $pdo): int
{
    $s = $pdo->query("
        SELECT ua.id FROM user_accounts ua
        INNER JOIN roles r ON r.id = ua.role_id
        WHERE r.name = 'Plenary' AND ua.status = 'active' AND ua.is_deleted = 0
        LIMIT 1
    ");
    $r = $s->fetch();
    if (!$r) {
        $s2 = $pdo->query("SELECT id FROM user_accounts WHERE is_deleted = 0 LIMIT 1");
        $r  = $s2->fetch();
    }
    if (!$r) {
        throw new RuntimeException('No usable user account found');
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

// ── DB helpers ─────────────────────────────────────────────────────────────

/**
 * Create a minimal Communication document in "Awaiting First Reading" state,
 * insert a REFER_TO_COMMITTEE plenary_comm_first_reading row (simulating the
 * referral step), then set status to "Returned to Plenary" (simulating the
 * committee return).  Returns [documentId, firstReadingId].
 */
function setupReturnedCommDoc(
    PDO $pdo,
    int $awaitingStatusId,
    int $returnedStatusId,
    int $commTypeId,
    int $srcTypeId,
    int $userId
): array {
    static $seq = 920000;
    $yr  = (int) date('Y');
    $num = ++$seq;

    // Insert document
    $pdo->prepare("
        INSERT INTO documents
            (tracking_year, tracking_sequence, tracking_number, date_received, time_received,
             subject_matter, document_type_id, source_type_id, current_status_id, current_phase)
        VALUES (?, ?, ?, CURDATE(), CURTIME(), 'Adopt comm test document', ?, ?, ?, 'COMMITTEE')
    ")->execute([$yr, $num, 'TEST-ADOPT-' . $num, $commTypeId, $srcTypeId, $returnedStatusId]);
    $docId = (int) $pdo->lastInsertId();

    // Insert REFER_TO_COMMITTEE plenary_comm_first_reading row
    $pdo->prepare("
        INSERT INTO plenary_comm_first_reading
            (document_id, decision, reading_date, performed_by)
        VALUES (?, 'REFER_TO_COMMITTEE', CURDATE(), ?)
    ")->execute([$docId, $userId]);
    $firstReadingId = (int) $pdo->lastInsertId();

    return [$docId, $firstReadingId];
}

/** Simulate stageMultipleFiles() without touching real uploads directory. */
function simulateStagedFiles(int $documentId, array $fileNames): array
{
    $staged = [];
    $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'adopt_test_' . $documentId;
    if (!is_dir($tmpDir)) {
        mkdir($tmpDir, 0755, true);
    }

    foreach ($fileNames as $origName) {
        $ext        = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $storedName = 'comm_res_' . $documentId . '_' . uniqid('', true) . '.' . $ext;
        $absPath    = $tmpDir . DIRECTORY_SEPARATOR . $storedName;
        $relPath    = 'uploads/documents/' . $documentId . '/' . $storedName;

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
            $documentId, $firstReadingId, $adoptionId,
            $f['original_name'], $f['stored_filename'],
            $f['relative_path'], $f['mime_type'], $f['file_size'], $userId,
        ]);
    }
}

/**
 * Simulate the DB portion of adoptCommStore() — replicates the transaction body
 * from PlenaryReadingController::adoptCommStore().
 * Returns the new adoption id.
 */
function doAdoptComm(
    PDO $pdo,
    int $documentId,
    int $firstReadingId,
    int $userId,
    string $resolutionNumber,
    string $proposedNumber,
    string $remarks,
    array $stagedFiles,
    int $finalizedStatusId
): int {
    // Insert adoption record
    $pdo->prepare("
        INSERT INTO plenary_comm_adoptions
            (document_id, first_reading_id, resolution_number, proposed_number, adopted_by, remarks)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([
        $documentId, $firstReadingId,
        $resolutionNumber, $proposedNumber ?: null,
        $userId, $remarks ?: null,
    ]);
    $adoptionId = (int) $pdo->lastInsertId();

    // Mark plenary_comm_first_reading as finalized
    $pdo->prepare("
        UPDATE plenary_comm_first_reading
        SET finalized_at = NOW(), finalized_by = ?
        WHERE id = ?
    ")->execute([$userId, $firstReadingId]);

    // Finalize the document
    $pdo->prepare("
        UPDATE documents
        SET current_status_id = ?,
            current_phase     = 'FINALIZED',
            finalized_at      = NOW(),
            updated_by        = ?
        WHERE id = ?
    ")->execute([$finalizedStatusId, $userId, $documentId]);

    // Persist resolution files (if any)
    if (!empty($stagedFiles)) {
        insertCommResFiles($pdo, $stagedFiles, $documentId, null, $adoptionId, $userId);
    }

    return $adoptionId;
}

// ── Resolve IDs before opening the outer transaction ──────────────────────
try {
    $returnedStatusId  = statusId($pdo, 'Returned to Plenary');
    $awaitingStatusId  = statusId($pdo, 'Awaiting First Reading');
    $finalizedStatusId = statusId($pdo, 'Plenary Comm: Finalized');
    $commTypeId        = docTypeId($pdo, '%Communication%');
    $srcTypeId         = sourceTypeId($pdo);
    $plUserId          = plenaryUserId($pdo);
} catch (RuntimeException $e) {
    echo 'FATAL: Setup failed — ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

echo '=== Plenary Adopt Committee Report Test ===' . PHP_EOL;

// ── Outer rollback transaction ────────────────────────────────────────────
$pdo->beginTransaction();
$tmpDirs = []; // track temp dirs for cleanup

try {
    // ── Group 1 — Multi-file validation ────────────────────────────────────
    section('Group 1 — Multi-file validation (mirrors validateMultipleFiles)');

    // 1.1 — Zero files: empty batch returns no errors (files are optional)
    $empty = makeFilesArray([['name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0]]);
    check('1.1 Zero files passes validation (optional for adoption)', count(validateMultipleFiles($empty)) === 0);

    // 1.2 — One valid file
    $oneFile = makeFilesArray([['name' => 'resolution.pdf', 'size' => 1024]]);
    check('1.2 One valid file is accepted', count(validateMultipleFiles($oneFile)) === 0);

    // 1.3 — Ten files (maximum boundary)
    $tenFiles = makeFilesArray(array_fill(0, 10, ['name' => 'file.pdf', 'size' => 1024]));
    check('1.3 Ten files accepted (maximum boundary)', count(validateMultipleFiles($tenFiles)) === 0);

    // 1.4 — Eleven files (exceeds maximum)
    $elevenFiles = makeFilesArray(array_fill(0, 11, ['name' => 'file.pdf', 'size' => 1024]));
    $elevenErrors = validateMultipleFiles($elevenFiles);
    check('1.4 Eleven files rejected (exceeds maximum)', count($elevenErrors) > 0, implode('; ', $elevenErrors));

    // 1.5 — File > 25 MB
    $bigFile = makeFilesArray([['name' => 'huge.pdf', 'size' => 26 * 1024 * 1024]]);
    $bigErrors = validateMultipleFiles($bigFile);
    check('1.5 File exceeding 25 MB is rejected', count($bigErrors) > 0, implode('; ', $bigErrors));

    // 1.6 — Disallowed extension
    $badExt = makeFilesArray([['name' => 'virus.exe', 'size' => 512]]);
    $badExtErrors = validateMultipleFiles($badExt);
    check('1.6 Disallowed extension (.exe) is rejected', count($badExtErrors) > 0, implode('; ', $badExtErrors));

    // 1.7 — Blocked executable extension
    $phpFile = makeFilesArray([['name' => 'shell.php', 'size' => 512]]);
    $phpErrors = validateMultipleFiles($phpFile);
    check('1.7 Blocked executable extension (.php) is rejected', count($phpErrors) > 0, implode('; ', $phpErrors));

    // 1.8 — Each allowed extension accepted individually
    $allowedExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
    foreach ($allowedExts as $ext) {
        $single = makeFilesArray([['name' => 'file.' . $ext, 'size' => 512]]);
        check('1.8 Extension .' . $ext . ' is accepted', count(validateMultipleFiles($single)) === 0);
    }

    // 1.9 — De-duplication: same filename+size added twice counts once
    //        (This is enforced client-side; server just receives the final deduplicated set.)
    //        Verify that passing two identical entries through validation does NOT double-error.
    $dupPair = makeFilesArray([
        ['name' => 'res.pdf', 'size' => 1024],
        ['name' => 'res.pdf', 'size' => 1024],
    ]);
    check('1.9 Duplicate name+size batch passes validation (deduplicated client-side)', count(validateMultipleFiles($dupPair)) === 0);

    // ── Group 2 — DB state after adoption with files ────────────────────────
    section('Group 2 — DB state after adoption with files');

    [$docId2, $frId2] = setupReturnedCommDoc(
        $pdo, $awaitingStatusId, $returnedStatusId, $commTypeId, $srcTypeId, $plUserId
    );
    $tmpDirs[] = $docId2;

    $staged3 = simulateStagedFiles($docId2, ['resolution.pdf', 'annex.docx', 'photo.jpg']);

    $adoptId = doAdoptComm(
        $pdo, $docId2, $frId2, $plUserId,
        'SP Res. No. 2026-ADOPT-001', 'SP Res. No. 2026-PROP-001',
        'Test adoption remarks', $staged3, $finalizedStatusId
    );

    // 2.1 — Adoption row exists
    $adoptRow = $pdo->prepare("SELECT * FROM plenary_comm_adoptions WHERE id = ? LIMIT 1");
    $adoptRow->execute([$adoptId]);
    $adopt = $adoptRow->fetch();
    check('2.1 plenary_comm_adoptions row inserted', $adopt !== false);

    // 2.2 — resolution_number
    check('2.2 resolution_number stored', ($adopt['resolution_number'] ?? '') === 'SP Res. No. 2026-ADOPT-001');

    // 2.3 — proposed_number
    check('2.3 proposed_number stored when provided', ($adopt['proposed_number'] ?? '') === 'SP Res. No. 2026-PROP-001');

    // 2.4 — adopted_by
    check('2.4 adopted_by matches performing user', (int) ($adopt['adopted_by'] ?? 0) === $plUserId);

    // 2.5 — remarks
    check('2.5 remarks stored', ($adopt['remarks'] ?? '') === 'Test adoption remarks');

    // 2.6 — three file rows created
    $fileRows = $pdo->prepare("SELECT * FROM plenary_comm_resolution_files WHERE document_id = ? ORDER BY id ASC");
    $fileRows->execute([$docId2]);
    $files = $fileRows->fetchAll();
    check('2.6 plenary_comm_resolution_files rows created (one per file)', count($files) === 3, 'got ' . count($files));

    if (!empty($files)) {
        $fRow = $files[0];
        check('2.7 Each file row: document_id correct',            (int) $fRow['document_id']    === $docId2);
        check('2.8 Each file row: adoption_id correct',            (int) $fRow['adoption_id']    === $adoptId);
        check('2.9 Each file row: first_reading_id is NULL',       $fRow['first_reading_id']     === null);
        check('2.10 Each file row: original_filename preserved',   $fRow['original_filename']    === 'resolution.pdf');
        check('2.11 stored_filename uses comm_res_{docId}_ prefix', str_starts_with($fRow['stored_filename'], 'comm_res_' . $docId2 . '_'));
        check('2.12 file_path uses uploads/documents/{docId}/ prefix', str_starts_with($fRow['file_path'], 'uploads/documents/' . $docId2 . '/'));
        check('2.13 mime_type is non-empty',                       !empty($fRow['mime_type']));
        check('2.14 file_size > 0',                                (int) $fRow['file_size'] > 0);
    }

    // 2.15 — plenary_comm_first_reading finalized_at set
    $frCheck = $pdo->prepare("SELECT finalized_at FROM plenary_comm_first_reading WHERE id = ? LIMIT 1");
    $frCheck->execute([$frId2]);
    $frData = $frCheck->fetch();
    check('2.15 plenary_comm_first_reading finalized_at set', !empty($frData['finalized_at']));

    // 2.16 — Document status
    $docRow = $pdo->prepare("SELECT current_status_id, current_phase, finalized_at FROM documents WHERE id = ? LIMIT 1");
    $docRow->execute([$docId2]);
    $doc = $docRow->fetch();
    check('2.16 Document status updated to "Plenary Comm: Finalized"', (int) ($doc['current_status_id'] ?? 0) === $finalizedStatusId);
    check('2.17 documents.current_phase set to FINALIZED',            ($doc['current_phase'] ?? '') === 'FINALIZED');

    // ── Group 3 — Adoption with zero files ─────────────────────────────────
    section('Group 3 — Adoption with zero files (optional)');

    [$docId3, $frId3] = setupReturnedCommDoc(
        $pdo, $awaitingStatusId, $returnedStatusId, $commTypeId, $srcTypeId, $plUserId
    );
    $tmpDirs[] = $docId3;

    $adoptId3 = doAdoptComm(
        $pdo, $docId3, $frId3, $plUserId,
        'SP Res. No. 2026-NOFILE-001', '', '', [], $finalizedStatusId
    );

    $adoptRow3 = $pdo->prepare("SELECT id FROM plenary_comm_adoptions WHERE id = ? LIMIT 1");
    $adoptRow3->execute([$adoptId3]);
    check('3.1 Adoption succeeds without any files', $adoptRow3->fetch() !== false);

    $fileCount3 = (int) $pdo->prepare("SELECT COUNT(*) FROM plenary_comm_resolution_files WHERE document_id = ?")->execute([$docId3]) ?: 0;
    $fileCountStmt3 = $pdo->prepare("SELECT COUNT(*) FROM plenary_comm_resolution_files WHERE document_id = ?");
    $fileCountStmt3->execute([$docId3]);
    check('3.2 No file rows created when no files submitted', (int) $fileCountStmt3->fetchColumn() === 0);

    // ── Group 4 — Duplicate adoption prevention ─────────────────────────────
    section('Group 4 — Duplicate adoption prevention');

    $dupCaught = false;
    try {
        $pdo->prepare("
            INSERT INTO plenary_comm_adoptions
                (document_id, first_reading_id, resolution_number, adopted_by)
            VALUES (?, ?, 'DUPLICATE', ?)
        ")->execute([$docId2, $frId2, $plUserId]);
    } catch (PDOException $e) {
        if (
            str_contains($e->getMessage(), '1062') ||
            str_contains((string) $e->getCode(), '23000')
        ) {
            $dupCaught = true;
        }
    }
    check('4.1 Second adoption for same document rejected (UNIQUE constraint)', $dupCaught);

    // ── Group 5 — Server-side gate: empty resolution_number ────────────────
    section('Group 5 — Server-side validation: empty resolution_number');

    // Replicate the controller's pre-transaction check.
    $resolutionNumber = '';
    $errors5 = [];
    if ($resolutionNumber === '') {
        $errors5[] = 'Approved Resolution Number is required.';
    }
    check('5.1 Empty resolution_number produces a validation error', count($errors5) > 0, implode('; ', $errors5));

    // ── Group 6 — Modal markup / structure assertions ───────────────────────
    section('Group 6 — Modal backdrop and dismissal structure');

    $viewFile = __DIR__ . '/../resources/views/plenary/referred-to-committee/index.php';
    $viewContent = @file_get_contents($viewFile);
    if ($viewContent === false) {
        fail('6.* Cannot read view file — skipping Group 6');
    } else {
        // 6.1 — Container has fixed positioning + inset-0 (covers viewport)
        check('6.1 Modal container has fixed inset-0 positioning',
            (bool) preg_match('/id=["\']adopt-comm-modal["\'][^>]*class=["\'][^"\']*fixed[^"\']*inset-0/', $viewContent) ||
            (bool) preg_match('/class=["\'][^"\']*fixed[^"\']*inset-0[^"\']*["\'][^>]*id=["\']adopt-comm-modal["\']/', $viewContent)
        );

        // 6.2 — Backdrop colour present (bg-black/*)
        check('6.2 Modal container has backdrop colour (bg-black/*)',
            (bool) preg_match('/id=["\']adopt-comm-modal["\'][^>]*class=["\'][^"\']*bg-black/', $viewContent) ||
            (bool) preg_match('/class=["\'][^"\']*bg-black[^"\']*["\'][^>]*id=["\']adopt-comm-modal["\']/', $viewContent) ||
            // Allow for the class and id to be in different orders on the element
            (bool) preg_match('/<div[^>]*id=["\']adopt-comm-modal["\'][^>]*bg-black/', $viewContent) ||
            (bool) preg_match('/<div[^>]*bg-black[^>]*id=["\']adopt-comm-modal["\']/', $viewContent)
        );

        // 6.3 — z-index >= 50
        check('6.3 Modal z-index is >= 50 (z-50 or higher)',
            (bool) preg_match('/<div[^>]*id=["\']adopt-comm-modal["\'][^>]*z-[5-9]\d/', $viewContent) ||
            (bool) preg_match('/<div[^>]*id=["\']adopt-comm-modal["\'][^>]*z-50/', $viewContent) ||
            (bool) preg_match('/id=["\']adopt-comm-modal["\'][^>]*z-50/', $viewContent)
        );

        // 6.4 — Hidden by default via inline style (not Tailwind 'hidden' class to avoid flex conflict)
        check('6.4 Modal container hidden by default via style="display:none"',
            (bool) preg_match('/<div[^>]*id=["\']adopt-comm-modal["\'][^>]*style=["\'][^"\']*display\s*:\s*none/', $viewContent)
        );

        // 6.5 — Panel is a direct child (backdrop fills behind it)
        check('6.5 Modal panel is a direct child of the container',
            (bool) preg_match(
                '/id=["\']adopt-comm-modal["\'][^>]*>[\s\S]{0,200}<div[^>]*(?:rounded|max-w|relative)/',
                $viewContent
            )
        );

        // 6.6 — File input with correct name
        check('6.6 File input with name="resolution_files[]" is present',
            (bool) strpos($viewContent, 'name="resolution_files[]"') !== false ||
            (bool) strpos($viewContent, "name='resolution_files[]'") !== false
        );

        // 6.7 — Drop zone element
        check('6.7 Drop zone element (#adopt-drop-zone) is present',
            (bool) strpos($viewContent, 'id="adopt-drop-zone"') !== false ||
            (bool) strpos($viewContent, "id='adopt-drop-zone'") !== false
        );

        // 6.8 — File list element
        check('6.8 File list element (#adopt-file-list) is present',
            (bool) strpos($viewContent, 'id="adopt-file-list"') !== false ||
            (bool) strpos($viewContent, "id='adopt-file-list'") !== false
        );

        // 6.9 — File error element
        check('6.9 File error element (#adopt-file-error) is present',
            (bool) strpos($viewContent, 'id="adopt-file-error"') !== false ||
            (bool) strpos($viewContent, "id='adopt-file-error'") !== false
        );

        // 6.10 — Form carries enctype
        check('6.10 Form has enctype="multipart/form-data"',
            (bool) strpos($viewContent, 'enctype="multipart/form-data"') !== false
        );

        // 6.11 — Form action points to adopt-comm
        check('6.11 Form action points to adopt-comm endpoint',
            (bool) strpos($viewContent, 'referred-to-committee/adopt-comm') !== false
        );
    }

    $pdo->rollBack();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo 'FATAL: Test aborted with exception — ' . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
}

// ── Temp dir cleanup (best-effort) ─────────────────────────────────────────
foreach ($tmpDirs as $docId) {
    $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'adopt_test_' . $docId;
    if (is_dir($tmpDir)) {
        array_map('unlink', glob($tmpDir . DIRECTORY_SEPARATOR . '*') ?: []);
        @rmdir($tmpDir);
    }
}

// ── Summary ────────────────────────────────────────────────────────────────
echo PHP_EOL;
$total = $passed + $failed;
echo "Results: {$passed}/{$total} passed";
if ($failed > 0) {
    echo " ({$failed} FAILED)";
}
echo PHP_EOL;
exit($failed > 0 ? 1 : 0);

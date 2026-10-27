<?php
/**
 * Test: Committee — Referred Documents → Submit Opinion
 *       Multiple Opinion / Compliance file uploads + the Opinion status workflow
 *
 * Covers
 * ------
 * 1. Multiple Opinion files and multiple Compliance files can be submitted.
 * 2. Each file is stored and associated with the correct document + attachment type.
 * 3. Adding files preserves previously submitted attachments (nothing replaced).
 * 4. Invalid file types, oversized files, partial upload failures and
 *    unauthorized submissions are handled safely.
 * 5. Existing review (listing) and download behaviour still resolves.
 * 6. The UI shows the Compliance upload area whenever the outcome is
 *    Unfavorable — including the second (final) endorsement — and hides it for
 *    any other outcome.
 * 7. A direct POST that attaches Compliance files for any other outcome is
 *    rejected server-side (hiding the control is not the only guard).
 * 8. Changing the outcome to Favorable never deletes earlier Compliance files.
 * 9. End-to-end: a first Unfavorable opinion plus Compliance persists every file
 *    row and moves the endorsement to "For Second Endorsement".
 * 10. End-to-end: the second review may be Favorable (existing favourable
 *     workflow) or Unfavorable + Compliance (moves to "Resolve"), and the
 *     second Unfavorable never starts another endorsement cycle.
 * 11. opinion_statuses is the only source of truth — no duplicate rows and no
 *     rows inserted by a submission.
 * 12. The For Opinion progress indicator advances only for a *cleared*
 *     (Favorable) office — an Unfavorable opinion, whether it is awaiting its
 *     second endorsement or has moved to Resolve, never advances it, and the
 *     office exposes the correct pending action instead.
 *
 * Strategy
 * --------
 * - Schema assertions run against information_schema.
 * - Validation assertions call the real DocumentService::validateFileUploads().
 * - Persistence assertions call the real DocumentService::insertStagedAttachments()
 *   with files written using the project's storage convention, then mirror the
 *   controller's SQL for the submission row and the join table.
 * - Authorization is probed in a subprocess against the real RoleMiddleware.
 * - opinionStore() is driven twice: once read-only (opinion_post_probe, reports
 *   the validation flash) and once end-to-end (opinion_store_e2e_probe, injects
 *   an upload service that copies instead of move_uploaded_file so the real
 *   transaction, attachment rows, join rows and status update all run).
 * - Every row / file created here is removed in the `finally` block.
 *
 * Run with:  php tests/committee_referred_opinion_files_test.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/services/DocumentService.php';

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

// ── Counters ───────────────────────────────────────────────────────────────
$passed = 0;
$failed = 0;

function assert_test(string $label, bool $condition): void
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

function section(string $title): void
{
    echo PHP_EOL . "── {$title} " . str_repeat('─', max(0, 66 - strlen($title))) . PHP_EOL;
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $entry;
        is_dir($path) ? rrmdir($path) : @unlink($path);
    }
    @rmdir($dir);
}

/**
 * Resolve a row by name, creating it when missing. Created IDs are recorded so
 * the caller can clean them up.
 */
function resolve_or_create(PDO $pdo, string $table, string $name, array $extra, array &$created, string $bucket): int
{
    $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE name = ? LIMIT 1");
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();
    if ($id) {
        return (int) $id;
    }

    $cols = array_merge(['name'], array_keys($extra));
    $ph   = implode(', ', array_fill(0, count($cols), '?'));
    $pdo->prepare("INSERT INTO {$table} (" . implode(', ', $cols) . ") VALUES ({$ph})")
        ->execute(array_merge([$name], array_values($extra)));

    $id = (int) $pdo->lastInsertId();
    $created[$bucket][] = $id;
    return $id;
}

/**
 * Exact copy of CommitteeReferredController::loadSubmissionFiles() — the query
 * the review screen uses to list every previously submitted file.
 *
 * @return array{opinion: array[], compliance: array[]}
 */
function load_submission_files(PDO $pdo, int $endorsementId): array
{
    $stmt = $pdo->prepare("
        SELECT cesf.file_type, da.id, da.file_name, da.stored_path,
               da.mime_type, da.file_size, da.created_at
          FROM committee_endorsement_submission_files cesf
          JOIN committee_endorsement_submissions ces ON ces.id = cesf.submission_id
          JOIN document_attachments da ON da.id = cesf.attachment_id
         WHERE ces.endorsement_id = ?
         ORDER BY cesf.file_type ASC, cesf.id ASC
    ");
    $stmt->execute([$endorsementId]);

    $result = ['opinion' => [], 'compliance' => []];
    foreach ($stmt->fetchAll() as $row) {
        $key = $row['file_type'] === 'COMPLIANCE' ? 'compliance' : 'opinion';
        $result[$key][] = $row;
    }
    return $result;
}

/**
 * Run RoleMiddleware in a subprocess against 'committee/referred/opinion' for
 * a given role. redirect() exits the process, so the probe reports its outcome
 * from a shutdown handler.
 *
 * @return string  'ALLOWED' | 'DENIED: <flash message>'
 */
function middleware_probe(string $root, ?string $roleName): string
{
    $probePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lis_probe_' . uniqid() . '.php';
    $roleExpr  = $roleName === null
        ? 'null'
        : "['id' => 1, 'username' => 'probe', 'role_name' => " . var_export($roleName, true) . "]";

    $code = "<?php\n"
        . "require_once " . var_export($root . '/app/config/app.php', true) . ";\n"
        . "require_once " . var_export($root . '/app/middleware/RoleMiddleware.php', true) . ";\n"
        . "\$_SESSION['user'] = " . $roleExpr . ";\n"
        . "\$GLOBALS['route'] = 'committee/referred/opinion';\n"
        . "register_shutdown_function(function () {\n"
        . "    \$flash = \$_SESSION['_flash']['error'] ?? '';\n"
        . "    echo 'RESULT:' . (\$flash === '' ? 'ALLOWED' : 'DENIED: ' . \$flash) . PHP_EOL;\n"
        . "});\n"
        . "(new RoleMiddleware())->handle();\n";

    file_put_contents($probePath, $code);
    $output = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probePath) . ' 2>&1');
    @unlink($probePath);

    foreach (array_reverse(explode("\n", $output)) as $line) {
        if (str_starts_with(trim($line), 'RESULT:')) {
            return trim(substr(trim($line), 7));
        }
    }
    return 'NO OUTPUT: ' . trim($output);
}

/**
 * POST to committee/referred/opinion through the real
 * CommitteeReferredController and report the flash it produced.
 *
 * opinionStore() ends in redirect(), which exits the process, so the outcome
 * is reported from a shutdown handler.
 *
 * Only the validation phase is reachable in CLI: the success path calls
 * move_uploaded_file(), which refuses files that were not uploaded over HTTP.
 *
 * @return array{errors: string[], error: string, old: array}
 */
function opinion_post_probe(string $root, int $userId, array $post, array $files, bool $authenticated = true): array
{
    $probePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lis_opinion_' . uniqid() . '.php';

    $userExpr = $authenticated
        ? "['id' => " . (int) $userId . ", 'username' => 'probe', 'role_name' => 'Committee']"
        : 'null';

    $code = "<?php\n"
        . "require_once " . var_export($root . '/app/config/app.php', true) . ";\n"
        . "require_once " . var_export($root . '/app/controllers/Committee/CommitteeReferredController.php', true) . ";\n"
        . "\$_SESSION = [];\n"
        . "\$_SESSION['user'] = {$userExpr};\n"
        . "if (\$_SESSION['user'] === null) { unset(\$_SESSION['user']); }\n"
        . "\$_POST  = " . var_export($post, true) . ";\n"
        . "\$_FILES = " . var_export($files, true) . ";\n"
        . "register_shutdown_function(function () {\n"
        . "    \$flash = \$_SESSION['_flash'] ?? [];\n"
        . "    \$old   = \$_SESSION['_old'] ?? [];\n"
        . "    echo 'RESULT:' . json_encode([\n"
        . "        'errors' => \$flash['errors'] ?? [],\n"
        . "        'error'  => \$flash['error'] ?? '',\n"
        . "        'old'    => \$old,\n"
        . "    ]) . PHP_EOL;\n"
        . "});\n"
        . "ob_start();\n"
        . "(new CommitteeReferredController())->opinionStore();\n";

    file_put_contents($probePath, $code);
    $output = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probePath) . ' 2>&1');
    @unlink($probePath);

    foreach (array_reverse(explode("\n", $output)) as $line) {
        $line = trim($line);
        if (str_starts_with($line, 'RESULT:')) {
            $decoded = json_decode(substr($line, 7), true);
            if (is_array($decoded)) {
                return $decoded + ['errors' => [], 'error' => '', 'old' => []];
            }
        }
    }
    return ['errors' => [], 'error' => 'NO OUTPUT: ' . trim($output), 'old' => []];
}

/** True when any validation message contains $needle (case-insensitive). */
function errors_contain(array $errors, string $needle): bool
{
    foreach ($errors as $e) {
        if (stripos((string) $e, $needle) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Run CommitteeReferredController::opinionStore() END-TO-END in a subprocess.
 *
 * The controller is given a DocumentService subclass whose stageFileUploads()
 * copies the test files instead of calling move_uploaded_file() — that built-in
 * refuses any file that was not uploaded over HTTP, which is impossible from
 * the CLI. Everything else (validation, the transaction, the submission row,
 * the attachment rows, the join rows and the opinion-status update) is the real
 * production code path, so the assertions that follow can query the database
 * for the real result.
 *
 * @return array{success: string, error: string, errors: string[]}
 */
function opinion_store_e2e_probe(string $root, int $userId, array $post, array $files, bool $authenticated = true): array
{
    $probePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lis_opinion_e2e_' . uniqid() . '.php';

    $userExpr = $authenticated
        ? "['id' => " . (int) $userId . ", 'username' => 'probe', 'role_name' => 'Committee']"
        : 'null';

    $code = "<?php\n"
        . "define('LIS_PROBE_ROOT', " . var_export($root, true) . ");\n"
        . "require_once " . var_export($root . '/app/config/app.php', true) . ";\n"
        . "require_once " . var_export($root . '/app/services/DocumentService.php', true) . ";\n"
        . "require_once " . var_export($root . '/app/controllers/Committee/CommitteeReferredController.php', true) . ";\n"
        . "class ProbeUploadService extends DocumentService {\n"
        . "    public function stageFileUploads(array \$files, int \$documentId): array {\n"
        . "        \$uploadDir = LIS_PROBE_ROOT . '/public/uploads/documents/' . \$documentId . '/';\n"
        . "        if (!is_dir(\$uploadDir) && !mkdir(\$uploadDir, 0755, true)) {\n"
        . "            throw new RuntimeException('Failed to create upload directory.');\n"
        . "        }\n"
        . "        \$staged = [];\n"
        . "        for (\$i = 0; \$i < count(\$files['name']); \$i++) {\n"
        . "            if (\$files['error'][\$i] !== UPLOAD_ERR_OK) { continue; }\n"
        . "            \$originalName = basename(\$files['name'][\$i]);\n"
        . "            \$ext          = strtolower(pathinfo(\$originalName, PATHINFO_EXTENSION));\n"
        . "            \$storedName   = 'doc_' . \$documentId . '_' . uniqid('', true) . '.' . \$ext;\n"
        . "            \$absPath      = \$uploadDir . \$storedName;\n"
        . "            if (!copy(\$files['tmp_name'][\$i], \$absPath)) {\n"
        . "                throw new RuntimeException('Failed to stage uploaded file: ' . \$originalName);\n"
        . "            }\n"
        . "            \$finfo = finfo_open(FILEINFO_MIME_TYPE);\n"
        . "            \$mime  = finfo_file(\$finfo, \$absPath);\n"
        . "            finfo_close(\$finfo);\n"
        . "            \$staged[] = [\n"
        . "                'abs_path'      => \$absPath,\n"
        . "                'relative_path' => 'uploads/documents/' . \$documentId . '/' . \$storedName,\n"
        . "                'original_name' => \$originalName,\n"
        . "                'mime_type'     => \$mime,\n"
        . "                'file_size'     => (int) filesize(\$absPath),\n"
        . "            ];\n"
        . "        }\n"
        . "        return \$staged;\n"
        . "    }\n"
        . "}\n"
        . "\$_SESSION = [];\n"
        . "\$_SESSION['user'] = {$userExpr};\n"
        . "if (\$_SESSION['user'] === null) { unset(\$_SESSION['user']); }\n"
        . "\$_POST  = " . var_export($post, true) . ";\n"
        . "\$_FILES = " . var_export($files, true) . ";\n"
        . "\$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=pangasinan_lis;charset=utf8mb4', 'root', '', [\n"
        . "    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,\n"
        . "    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n"
        . "]);\n"
        . "register_shutdown_function(function () {\n"
        . "    \$flash = \$_SESSION['_flash'] ?? [];\n"
        . "    echo 'RESULT:' . json_encode([\n"
        . "        'success' => \$flash['success'] ?? '',\n"
        . "        'error'   => \$flash['error'] ?? '',\n"
        . "        'errors'  => \$flash['errors'] ?? [],\n"
        . "    ]) . PHP_EOL;\n"
        . "});\n"
        . "ob_start();\n"
        . "(new CommitteeReferredController(\$pdo, new ProbeUploadService(\$pdo)))->opinionStore();\n";

    file_put_contents($probePath, $code);
    $output = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probePath) . ' 2>&1');
    @unlink($probePath);

    foreach (array_reverse(explode("\n", $output)) as $line) {
        $line = trim($line);
        if (str_starts_with($line, 'RESULT:')) {
            $decoded = json_decode(substr($line, 7), true);
            if (is_array($decoded)) {
                return $decoded + ['success' => '', 'error' => '', 'errors' => []];
            }
        }
    }
    return ['success' => '', 'error' => 'NO OUTPUT: ' . trim($output), 'errors' => []];
}

/**
 * POST to committee/referred/second-endorse through the real controller and
 * report the flash it produced.
 *
 * @return array{success: string, error: string, errors: string[]}
 */
function second_endorse_probe(string $root, int $userId, int $endorsementId): array
{
    $probePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lis_second_endorse_' . uniqid() . '.php';

    $code = "<?php\n"
        . "require_once " . var_export($root . '/app/config/app.php', true) . ";\n"
        . "require_once " . var_export($root . '/app/controllers/Committee/CommitteeReferredController.php', true) . ";\n"
        . "\$_SESSION = [];\n"
        . "\$_SESSION['user'] = ['id' => " . (int) $userId . ", 'username' => 'probe', 'role_name' => 'Committee'];\n"
        . "\$_POST = ['endorsement_id' => " . (int) $endorsementId . ", 'remarks' => 'third attempt'];\n"
        . "register_shutdown_function(function () {\n"
        . "    \$flash = \$_SESSION['_flash'] ?? [];\n"
        . "    echo 'RESULT:' . json_encode([\n"
        . "        'success' => \$flash['success'] ?? '',\n"
        . "        'error'   => \$flash['error'] ?? '',\n"
        . "        'errors'  => \$flash['errors'] ?? [],\n"
        . "    ]) . PHP_EOL;\n"
        . "});\n"
        . "ob_start();\n"
        . "(new CommitteeReferredController())->secondEndorseStore();\n";

    file_put_contents($probePath, $code);
    $output = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probePath) . ' 2>&1');
    @unlink($probePath);

    foreach (array_reverse(explode("\n", $output)) as $line) {
        $line = trim($line);
        if (str_starts_with($line, 'RESULT:')) {
            $decoded = json_decode(substr($line, 7), true);
            if (is_array($decoded)) {
                return $decoded + ['success' => '', 'error' => '', 'errors' => []];
            }
        }
    }
    return ['success' => '', 'error' => 'NO OUTPUT: ' . trim($output), 'errors' => []];
}

// ── Fixture bookkeeping ────────────────────────────────────────────────────
$created = [
    'users'   => [], 'cycles' => [], 'docs' => [],
    'offices' => [], 'ostatus' => [], 'dtypes' => [], 'stypes' => [], 'dstatus' => [],
];
$dirs = [];

$root      = realpath(__DIR__ . '/..');
$svc       = new DocumentService();
$tmpDir    = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lis_ups_' . uniqid();
mkdir($tmpDir, 0777, true);
$dirs[]    = $tmpDir;

echo "Committee — Referred Documents: multiple Opinion / Compliance uploads" . PHP_EOL;

try {
    // Baseline for "submissions must never insert into opinion_statuses".
    $statusRowsBefore = (int) $pdo->query("SELECT COUNT(*) FROM opinion_statuses")->fetchColumn();

    // ══════════════════════════════════════════════════════════════════════
    section('1. SCHEMA');
    // ══════════════════════════════════════════════════════════════════════
    $cols     = $pdo->query("SHOW COLUMNS FROM committee_endorsement_submission_files")->fetchAll();
    $colNames = array_column($cols, 'Field');

    assert_test(
        'SCHEMA: join table committee_endorsement_submission_files exists',
        !empty($colNames)
    );
    assert_test(
        'SCHEMA: join table has submission_id / attachment_id / file_type',
        empty(array_diff(['submission_id', 'attachment_id', 'file_type'], $colNames))
    );

    $fileTypeCol = null;
    foreach ($cols as $c) {
        if ($c['Field'] === 'file_type') {
            $fileTypeCol = $c['Type'];
        }
    }
    assert_test(
        'SCHEMA: file_type distinguishes OPINION from COMPLIANCE',
        $fileTypeCol !== null
            && stripos($fileTypeCol, "'OPINION'") !== false
            && stripos($fileTypeCol, "'COMPLIANCE'") !== false
    );

    $joinIdx = $pdo->query("SHOW INDEX FROM committee_endorsement_submission_files")->fetchAll();
    $uniquePairs = [];
    foreach ($joinIdx as $idx) {
        if ((int) $idx['Non_unique'] === 0) {
            $uniquePairs[$idx['Key_name']][(int) $idx['Seq_in_index']] = $idx['Column_name'];
        }
    }
    $hasUniquePair = false;
    foreach ($uniquePairs as $pair) {
        ksort($pair);
        if (array_values($pair) === ['submission_id', 'attachment_id']) {
            $hasUniquePair = true;
        }
    }
    assert_test('SCHEMA: (submission_id, attachment_id) is unique — no duplicate links', $hasUniquePair);

    $rules = $pdo->query("
        SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE()
           AND TABLE_NAME = 'committee_endorsement_submission_files'
    ")->fetchAll();
    assert_test(
        'SCHEMA: attachment link uses ON DELETE RESTRICT (a removed link cannot silently drop a file)',
        in_array('RESTRICT', array_column($rules, 'DELETE_RULE'), true)
    );

    $attTypeCol = $pdo->query("SHOW COLUMNS FROM document_attachments LIKE 'attachment_type'")
        ->fetch()['Type'] ?? '';
    assert_test(
        "SCHEMA: document_attachments.attachment_type includes OPINION_FILE and COMPLIANCE_FILE",
        stripos($attTypeCol, "'OPINION_FILE'") !== false && stripos($attTypeCol, "'COMPLIANCE_FILE'") !== false
    );

    $attIdx  = $pdo->query("SHOW INDEX FROM document_attachments")->fetchAll();
    $uniqSets = [];
    foreach ($attIdx as $idx) {
        if ((int) $idx['Non_unique'] === 0 && $idx['Key_name'] !== 'PRIMARY') {
            $uniqSets[$idx['Key_name']][(int) $idx['Seq_in_index']] = $idx['Column_name'];
        }
    }
    $blocksMany = false;
    foreach ($uniqSets as $set) {
        ksort($set);
        if (in_array('document_id', $set, true) && in_array('attachment_type', $set, true)) {
            $blocksMany = true;
        }
    }
    assert_test(
        'SCHEMA: no unique (document_id, attachment_type) — many files per type per document',
        !$blocksMany
    );

    // ══════════════════════════════════════════════════════════════════════
    section('2. SERVER-SIDE VALIDATION (DocumentService::validateFileUploads)');
    // ══════════════════════════════════════════════════════════════════════
    $pdfPath = $tmpDir . '/opinion-a.pdf';
    file_put_contents($pdfPath, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");
    $pngPath = $tmpDir . '/scan-b.png';
    file_put_contents($pngPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg=='));
    $gifPath = $tmpDir . '/scan-c.gif';
    file_put_contents($gifPath, base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
    $exePath = $tmpDir . '/malware.exe';
    file_put_contents($exePath, "MZ\x90\x00\x03\x00\x00\x00");

    // Many files of one type pass validation as a single batch.
    $multiOk = [
        'name'     => ['opinion-a.pdf', 'scan-b.png', 'scan-c.gif'],
        'tmp_name' => [$pdfPath, $pngPath, $gifPath],
        'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_OK, UPLOAD_ERR_OK],
        'size'     => [filesize($pdfPath), filesize($pngPath), filesize($gifPath)],
        'type'     => ['application/pdf', 'image/png', 'image/gif'],
    ];
    assert_test(
        'VALIDATE: three opinion files in one batch produce no errors',
        $svc->validateFileUploads($multiOk, true) === []
    );

    // Disallowed extension.
    $badExt = [
        'name'     => ['malware.exe'],
        'tmp_name' => [$exePath],
        'error'    => [UPLOAD_ERR_OK],
        'size'     => [filesize($exePath)],
    ];
    $errs = $svc->validateFileUploads($badExt, true);
    $extRejected = false;
    foreach ($errs as $e) {
        if (stripos($e, 'invalid extension') !== false) {
            $extRejected = true;
        }
    }
    assert_test('VALIDATE: disallowed extension (.exe) is rejected', $extRejected);

    // Oversized file (sparse 26 MB file).
    $bigPath = $tmpDir . '/oversized.pdf';
    $fh = fopen($bigPath, 'wb');
    fseek($fh, 26 * 1024 * 1024);
    fwrite($fh, 'x');
    fclose($fh);
    $tooBig = [
        'name'     => ['oversized.pdf'],
        'tmp_name' => [$bigPath],
        'error'    => [UPLOAD_ERR_OK],
        'size'     => [filesize($bigPath)],
    ];
    $errs = $svc->validateFileUploads($tooBig, true);
    $sizeRejected = false;
    foreach ($errs as $e) {
        if (stripos($e, 'exceeds the maximum size') !== false) {
            $sizeRejected = true;
        }
    }
    assert_test('VALIDATE: file larger than 25 MB is rejected', $sizeRejected);

    // Partial upload failure inside an otherwise valid batch.
    $partialBatch = [
        'name'     => ['opinion-a.pdf', 'scan-b.png', 'scan-c.gif'],
        'tmp_name' => [$pdfPath, $pngPath, $gifPath],
        'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_PARTIAL, UPLOAD_ERR_OK],
        'size'     => [filesize($pdfPath), filesize($pngPath), filesize($gifPath)],
    ];
    $errs = $svc->validateFileUploads($partialBatch, true);
    assert_test(
        'VALIDATE: partial upload failure is reported for exactly one file',
        count($errs) === 1
            && stripos($errs[0], 'Upload error') !== false
            && strpos($errs[0], 'scan-b.png') !== false
    );

    // More than the 10-file cap.
    $many = ['name' => [], 'tmp_name' => [], 'error' => [], 'size' => []];
    for ($i = 0; $i < 11; $i++) {
        $many['name'][]     = "opinion-{$i}.pdf";
        $many['tmp_name'][] = $pdfPath;
        $many['error'][]    = UPLOAD_ERR_OK;
        $many['size'][]     = filesize($pdfPath);
    }
    $errs = $svc->validateFileUploads($many, true);
    assert_test(
        'VALIDATE: more than 10 files in one area is rejected',
        count($errs) === 1 && stripos($errs[0], 'Maximum 10 files') !== false
    );

    // Required-file enforcement.
    $empty = ['name' => [], 'tmp_name' => [], 'error' => [], 'size' => []];
    assert_test(
        'VALIDATE: missing opinion file is rejected when required',
        count($svc->validateFileUploads($empty, true)) === 1
    );
    assert_test(
        'VALIDATE: missing compliance file is allowed when optional',
        $svc->validateFileUploads($empty, false) === []
    );

    // ══════════════════════════════════════════════════════════════════════
    section('3. FIXTURES');
    // ══════════════════════════════════════════════════════════════════════
    $committeeRoleId = (int) $pdo->query(
        "SELECT id FROM roles WHERE name = 'Committee' AND is_active = 1 AND is_deleted = 0 LIMIT 1"
    )->fetchColumn();
    if (!$committeeRoleId) {
        echo "  [SKIP] Committee role not found — cannot run persistence checks." . PHP_EOL;
        throw new RuntimeException('Committee role missing');
    }

    $username = '_ref_files_' . uniqid();
    $pdo->prepare(
        "INSERT INTO user_accounts (username, email, password_hash, role_id, status, is_deleted)
         VALUES (?, ?, 'x', ?, 'active', 0)"
    )->execute([$username, $username . '@test.local', $committeeRoleId]);
    $userId = (int) $pdo->lastInsertId();
    $created['users'][] = $userId;

    $docTypeId = resolve_or_create($pdo, 'document_types', '_test_ref_files', ['badge_color' => '#6c757d'], $created, 'dtypes');
    $srcTypeId = resolve_or_create($pdo, 'source_types', '_test_ref_files', [], $created, 'stypes');
    $statusId  = resolve_or_create($pdo, 'document_statuses', '_test_ref_files', ['badge_color' => '#6c757d'], $created, 'dstatus');

    $pdo->prepare(
        "INSERT INTO documents
            (tracking_number, tracking_year, tracking_sequence,
             subject_matter, document_type_id, source_type_id, current_status_id,
             date_received, time_received, current_phase, created_by, updated_by)
         VALUES (?, YEAR(CURDATE()), 899999, 'Referred documents upload test', ?, ?, ?,
                 CURDATE(), CURTIME(), 'COMMITTEE', ?, ?)"
    )->execute(['RF-' . uniqid(), $docTypeId, $srcTypeId, $statusId, $userId, $userId]);
    $documentId = (int) $pdo->lastInsertId();
    $created['docs'][] = $documentId;

    $officeId = resolve_or_create($pdo, 'opinion_offices', '_test_ref_office', ['abbreviation' => 'TST'], $created, 'offices');
    $favStatusId = resolve_or_create($pdo, 'opinion_statuses', 'Favorable', [], $created, 'ostatus');
    $unfavStatusId = resolve_or_create($pdo, 'opinion_statuses', 'Unfavorable', [], $created, 'ostatus');

    $pdo->prepare(
        "INSERT INTO committee_cycles (document_id, cycle_number, referred_by, decision)
         VALUES (?, 1, ?, 'PENDING')"
    )->execute([$documentId, $userId]);
    $cycleId = (int) $pdo->lastInsertId();
    $created['cycles'][] = $cycleId;

    $endorsementOn = function (int $cycle, int $number) use ($pdo, $officeId, $favStatusId): int {
        $pdo->prepare(
            "INSERT INTO committee_cycle_endorsements
                (cycle_id, opinion_office_id, endorsement_number, opinion_status_id)
             VALUES (?, ?, ?, ?)"
        )->execute([$cycle, $officeId, $number, $favStatusId]);
        return (int) $pdo->lastInsertId();
    };

    $insertEndorsement = function (int $number) use ($endorsementOn, $cycleId): int {
        return $endorsementOn($cycleId, $number);
    };

    /** Create a referred document plus its first committee cycle. */
    $newReferredDocument = function () use ($pdo, $docTypeId, $srcTypeId, $statusId, $userId, &$created): array {
        // (tracking_year, tracking_sequence) is unique — take the next free slot.
        $sequence = (int) $pdo->query(
            "SELECT COALESCE(MAX(tracking_sequence), 0) + 1 FROM documents WHERE tracking_year = YEAR(CURDATE())"
        )->fetchColumn();

        $pdo->prepare(
            "INSERT INTO documents
                (tracking_number, tracking_year, tracking_sequence,
                 subject_matter, document_type_id, source_type_id, current_status_id,
                 date_received, time_received, current_phase, created_by, updated_by)
             VALUES (?, YEAR(CURDATE()), ?, 'Committee opinion workflow probe', ?, ?, ?,
                     CURDATE(), CURTIME(), 'COMMITTEE', ?, ?)"
        )->execute(['RF-' . uniqid(), $sequence, $docTypeId, $srcTypeId, $statusId, $userId, $userId]);
        $newDocId = (int) $pdo->lastInsertId();
        $created['docs'][] = $newDocId;

        $pdo->prepare(
            "INSERT INTO committee_cycles (document_id, cycle_number, referred_by, decision)
             VALUES (?, 1, ?, 'PENDING')"
        )->execute([$newDocId, $userId]);
        $newCycleId = (int) $pdo->lastInsertId();
        $created['cycles'][] = $newCycleId;

        return [$newDocId, $newCycleId];
    };

    assert_test('FIXTURE: document, cycle and endorsement created', $documentId > 0 && $cycleId > 0);

    // ══════════════════════════════════════════════════════════════════════
    section('4. MULTI-FILE PERSISTENCE + CORRECT TYPES');
    // ══════════════════════════════════════════════════════════════════════
    $uploadDir = $root . '/public/uploads/documents/' . $documentId;
    mkdir($uploadDir, 0755, true);
    $dirs[] = $uploadDir;

    // Emulate DocumentService::stageFileUploads() storage convention exactly:
    // public/uploads/documents/{documentId}/doc_{documentId}_{uniqid}.{ext}
    $stage = function (string $sourcePath, string $originalName) use ($uploadDir, $documentId): array {
        $ext        = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $storedName = 'doc_' . $documentId . '_' . uniqid('', true) . '.' . $ext;
        $absPath    = $uploadDir . '/' . $storedName;

        if (!copy($sourcePath, $absPath)) {
            throw new RuntimeException('Could not stage test file: ' . $originalName);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $absPath);
        finfo_close($finfo);

        return [
            'abs_path'      => $absPath,
            'relative_path' => 'uploads/documents/' . $documentId . '/' . $storedName,
            'original_name' => $originalName,
            'mime_type'     => $mime,
            'file_size'     => (int) filesize($absPath),
        ];
    };

    $opStaged = [
        $stage($pdfPath, 'Opinion A.pdf'),
        $stage($pngPath, 'Opinion B.png'),
        $stage($gifPath, 'Opinion C.gif'),
    ];
    $cpStaged = [
        $stage($pdfPath, 'Compliance A.pdf'),
        $stage($pngPath, 'Compliance B.png'),
    ];

    // Real service call (one DB row per file, one shared attachment_type).
    $opLogs = $svc->insertStagedAttachments($opStaged, $documentId, $userId, 'COMMITTEE', 'OPINION_FILE');
    $cpLogs = $svc->insertStagedAttachments($cpStaged, $documentId, $userId, 'COMMITTEE', 'COMPLIANCE_FILE');

    assert_test('PERSIST: 3 opinion files produce 3 attachment records', count($opLogs) === 3);
    assert_test('PERSIST: 2 compliance files produce 2 attachment records', count($cpLogs) === 2);
    assert_test(
        'PERSIST: deferred upload logs are queued one per file (flushed after commit)',
        count($opLogs) === 3 && count($cpLogs) === 2
            && $opLogs[0]['document_id'] === $documentId
    );

    $idsForStaged = function (array $staged) use ($pdo, $documentId): array {
        $paths = array_column($staged, 'relative_path');
        $ph    = implode(',', array_fill(0, count($paths), '?'));
        $stmt  = $pdo->prepare("
            SELECT id, stored_path FROM document_attachments
             WHERE document_id = ? AND stored_path IN ({$ph})
        ");
        $stmt->execute(array_merge([$documentId], $paths));
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[$row['stored_path']] = (int) $row['id'];
        }
        $ids = [];
        foreach ($staged as $s) {
            $ids[] = $map[$s['relative_path']] ?? 0;
        }
        return $ids;
    };

    $opIds = $idsForStaged($opStaged);
    $cpIds = $idsForStaged($cpStaged);
    assert_test('PERSIST: every staged opinion file has a readable attachment id', count(array_filter($opIds)) === 3);
    assert_test('PERSIST: every staged compliance file has a readable attachment id', count(array_filter($cpIds)) === 2);

    // Mirrors CommitteeReferredController::opinionStore() submission insert.
    $insertSubmission = function (
        int $endorsementId,
        string $opinionType,
        int $opinionAttachmentId,
        ?int $complianceAttachmentId,
        int $submittedBy
    ) use ($pdo): int {
        $pdo->prepare("
            INSERT INTO committee_endorsement_submissions
                (endorsement_id, opinion_type, opinion_file_attachment_id,
                 compliance_file_attachment_id, submitted_by, submitted_at, remarks)
            VALUES (?, ?, ?, ?, ?, NOW(), ?)
        ")->execute([$endorsementId, $opinionType, $opinionAttachmentId, $complianceAttachmentId, $submittedBy, null]);
        return (int) $pdo->lastInsertId();
    };

    // Mirrors the controller's join-table writes.
    $linkFiles = function (int $submissionId, array $opinionIds, array $complianceIds) use ($pdo): void {
        $stmt = $pdo->prepare("
            INSERT INTO committee_endorsement_submission_files
                (submission_id, attachment_id, file_type)
            VALUES (?, ?, ?)
        ");
        foreach ($opinionIds as $aid) {
            $stmt->execute([$submissionId, $aid, 'OPINION']);
        }
        foreach ($complianceIds as $aid) {
            $stmt->execute([$submissionId, $aid, 'COMPLIANCE']);
        }
    };

    $endorsement1 = $insertEndorsement(1);
    $submission1  = $insertSubmission($endorsement1, 'UNFAVORABLE', $opIds[0], $cpIds[0], $userId);
    $linkFiles($submission1, $opIds, $cpIds);

    assert_test('PERSIST: submission row created for the endorsement', $submission1 > 0);

    $opCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM document_attachments
          WHERE document_id = {$documentId} AND attachment_type = 'OPINION_FILE'"
    )->fetchColumn();
    $cpCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM document_attachments
          WHERE document_id = {$documentId} AND attachment_type = 'COMPLIANCE_FILE'"
    )->fetchColumn();
    assert_test("PERSIST: document holds 3 OPINION_FILE attachments (got {$opCount})", $opCount === 3);
    assert_test("PERSIST: document holds 2 COMPLIANCE_FILE attachments (got {$cpCount})", $cpCount === 2);

    $linked = $pdo->prepare("
        SELECT cesf.file_type, da.document_id, da.attachment_type, da.stored_path, da.file_name
          FROM committee_endorsement_submission_files cesf
          JOIN document_attachments da ON da.id = cesf.attachment_id
         WHERE cesf.submission_id = ?
    ");
    $linked->execute([$submission1]);
    $linkedRows = $linked->fetchAll();

    $assocOk   = true;
    $typeOk    = true;
    foreach ($linkedRows as $row) {
        if ((int) $row['document_id'] !== $documentId) {
            $assocOk = false;
        }
        $expected = $row['file_type'] === 'COMPLIANCE' ? 'COMPLIANCE_FILE' : 'OPINION_FILE';
        if ($row['attachment_type'] !== $expected) {
            $typeOk = false;
        }
    }
    assert_test('PERSIST: all 5 files are associated with the correct document', count($linkedRows) === 5 && $assocOk);
    assert_test('PERSIST: each link keeps the matching attachment_type (OPINION/COMPLIANCE)', $typeOk);

    $byType = $pdo->prepare("
        SELECT file_type, COUNT(*) AS cnt
          FROM committee_endorsement_submission_files
         WHERE submission_id = ?
         GROUP BY file_type
    ");
    $byType->execute([$submission1]);
    $counts = array_column($byType->fetchAll(), 'cnt', 'file_type');
    assert_test(
        'PERSIST: join table keeps 3 OPINION + 2 COMPLIANCE rows',
        (int) ($counts['OPINION'] ?? 0) === 3 && (int) ($counts['COMPLIANCE'] ?? 0) === 2
    );

    // Legacy FK columns stay populated (backwards compatibility).
    $legacy = $pdo->prepare("
        SELECT opinion_file_attachment_id, compliance_file_attachment_id
          FROM committee_endorsement_submissions WHERE id = ?
    ");
    $legacy->execute([$submission1]);
    $legacyRow = $legacy->fetch();
    assert_test(
        'PERSIST: legacy single-file FK columns still point at the first file of each type',
        (int) $legacyRow['opinion_file_attachment_id'] === $opIds[0]
            && (int) $legacyRow['compliance_file_attachment_id'] === $cpIds[0]
    );

    // Generated storage filenames.
    $namesSafe = true;
    foreach (array_merge($opStaged, $cpStaged) as $s) {
        $pattern = '#^uploads/documents/' . $documentId . '/doc_' . $documentId . '_[A-Za-z0-9.]+\.(pdf|png|gif)$#';
        if (!preg_match($pattern, $s['relative_path'])
            || stripos($s['relative_path'], str_replace(' ', '', $s['original_name'])) !== false) {
            $namesSafe = false;
        }
    }
    assert_test(
        'PERSIST: storage filenames are generated (never user-supplied) and follow the uploads/documents convention',
        $namesSafe
    );

    // ══════════════════════════════════════════════════════════════════════
    section('5. ADDING FILES PRESERVES PREVIOUS ATTACHMENTS');
    // ══════════════════════════════════════════════════════════════════════
    $beforePaths = array_column(
        $pdo->query("SELECT stored_path FROM document_attachments WHERE document_id = {$documentId} ORDER BY stored_path")
            ->fetchAll(),
        'stored_path'
    );

    $endorsement2 = $insertEndorsement(2);
    $op2Staged = [
        $stage($pdfPath, 'Opinion D.pdf'),
        $stage($gifPath, 'Opinion E.gif'),
    ];
    $cp2Staged = [$stage($pngPath, 'Compliance C.png')];

    $svc->insertStagedAttachments($op2Staged, $documentId, $userId, 'COMMITTEE', 'OPINION_FILE');
    $svc->insertStagedAttachments($cp2Staged, $documentId, $userId, 'COMMITTEE', 'COMPLIANCE_FILE');

    $op2Ids = array_values(array_filter($idsForStaged($op2Staged)));
    $cp2Ids = array_values(array_filter($idsForStaged($cp2Staged)));

    $submission2 = $insertSubmission($endorsement2, 'UNFAVORABLE', $op2Ids[0], $cp2Ids[0], $userId);
    $linkFiles($submission2, $op2Ids, $cp2Ids);

    $afterPaths = array_column(
        $pdo->query("SELECT stored_path FROM document_attachments WHERE document_id = {$documentId} ORDER BY stored_path")
            ->fetchAll(),
        'stored_path'
    );
    $preserved = empty(array_diff($beforePaths, $afterPaths));

    assert_test('PRESERVE: previously submitted attachment rows still exist', $preserved);
    assert_test('PRESERVE: new files add rows instead of replacing them (5 -> 8)', count($afterPaths) === 8);

    $firstLinks = (int) $pdo->query(
        "SELECT COUNT(*) FROM committee_endorsement_submission_files WHERE submission_id = {$submission1}"
    )->fetchColumn();
    assert_test('PRESERVE: the first submission still links all 5 of its original files', $firstLinks === 5);

    $legacyAfter = $pdo->prepare("
        SELECT opinion_file_attachment_id, compliance_file_attachment_id
          FROM committee_endorsement_submissions WHERE id = ?
    ");
    $legacyAfter->execute([$submission1]);
    $legacyAfterRow = $legacyAfter->fetch();
    assert_test(
        'PRESERVE: the first submission keep pointing at its original files',
        (int) $legacyAfterRow['opinion_file_attachment_id'] === $opIds[0]
            && (int) $legacyAfterRow['compliance_file_attachment_id'] === $cpIds[0]
    );

    $firstFiles  = load_submission_files($pdo, $endorsement1);
    $secondFiles = load_submission_files($pdo, $endorsement2);
    assert_test(
        'PRESERVE: review listing for the first endorsement shows 3 opinion + 2 compliance',
        count($firstFiles['opinion']) === 3 && count($firstFiles['compliance']) === 2
    );
    assert_test(
        'PRESERVE: review listing for the second endorsement shows only its own 2 opinion + 1 compliance',
        count($secondFiles['opinion']) === 2 && count($secondFiles['compliance']) === 1
    );

    // ══════════════════════════════════════════════════════════════════════
    section('6. REVIEW / DOWNLOAD BEHAVIOUR');
    // ══════════════════════════════════════════════════════════════════════
    $allResolve = true;
    foreach (array_merge($firstFiles['opinion'], $firstFiles['compliance'], $secondFiles['opinion'], $secondFiles['compliance']) as $f) {
        // The download link is BASE_URL . '/public/' . stored_path
        if (!is_file($root . '/public/' . ltrim($f['stored_path'], '/'))) {
            $allResolve = false;
        }
        if ($f['file_name'] === '' || (int) $f['file_size'] <= 0) {
            $allResolve = false;
        }
    }
    assert_test('REVIEW: every listed file resolves to a real file under public/ (downloadable)', $allResolve);

    // Controller marks the endorsement as submitted with the opinion status.
    $pdo->prepare("
        UPDATE committee_cycle_endorsements
           SET opinion_status_id = ?, submitted_at = NOW()
         WHERE id = ?
    ")->execute([$unfavStatusId, $endorsement1]);

    $marked = $pdo->prepare("
        SELECT opinion_status_id, submitted_at FROM committee_cycle_endorsements WHERE id = ?
    ");
    $marked->execute([$endorsement1]);
    $markedRow = $marked->fetch();
    assert_test(
        'REVIEW: endorsement status/submitted_at still update on submission',
        (int) $markedRow['opinion_status_id'] === $unfavStatusId && $markedRow['submitted_at'] !== null
    );

    $eventSql = $pdo->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_events'
           AND COLUMN_NAME IN ('event_type', 'phase', 'performed_by', 'metadata')
    ");
    $eventSql->execute();
    assert_test('REVIEW: document_events columns used by the audit trail still present', (int) $eventSql->fetchColumn() === 4);

    // ══════════════════════════════════════════════════════════════════════
    section('7. AUTHORIZATION');
    // ══════════════════════════════════════════════════════════════════════
    $routes = file_get_contents($root . '/routes/web.php');
    preg_match("/'committee\\/referred\\/opinion'\s*=>\s*\[(.*?)\n    \],/s", $routes, $m);
    $routeBlock = $m[1] ?? '';
    assert_test(
        'AUTH: POST committee/referred/opinion maps to opinionStore',
        strpos($routeBlock, "'action'     => 'opinionStore'") !== false
    );
    assert_test(
        'AUTH: the upload route requires AuthMiddleware + RoleMiddleware',
        strpos($routeBlock, "'AuthMiddleware'") !== false && strpos($routeBlock, "'RoleMiddleware'") !== false
    );

    $committeeProbe   = middleware_probe($root, 'Committee');
    $receivingProbe   = middleware_probe($root, 'Receiving Staff');
    $anonymousProbe   = middleware_probe($root, null);

    assert_test(
        "AUTH: Committee role reaches the upload route (probe: {$committeeProbe})",
        $committeeProbe === 'ALLOWED'
    );
    assert_test(
        "AUTH: Receiving Staff is redirected away from the upload route (probe: {$receivingProbe})",
        str_starts_with($receivingProbe, 'DENIED')
    );
    assert_test(
        "AUTH: unauthenticated request is redirected to login (probe: {$anonymousProbe})",
        str_starts_with($anonymousProbe, 'DENIED') && strpos($anonymousProbe, 'sign in') !== false
    );

    // ══════════════════════════════════════════════════════════════════════
    section('8. UI — COMPLIANCE AREA ONLY FOR AN UNFAVORABLE OUTCOME');
    // ══════════════════════════════════════════════════════════════════════
    $view = (string) file_get_contents($root . '/resources/views/committee/referred/opinion.php');

    assert_test(
        'UI: the compliance upload area is a hidden-by-default wrapper',
        preg_match('/id="complianceSection"\s+class="[^"]*\bhidden\b/', $view) === 1
    );
    assert_test(
        'UI: the wrapper is revealed only when the outcome is UNFAVORABLE',
        strpos($view, "complianceSection.classList.toggle('hidden', !offered);") !== false
            && strpos($view, "return selectedType() === 'UNFAVORABLE';") !== false
    );
    assert_test(
        'UI: the compliance file input is disabled unless the Compliance step is offered (hidden files are never posted)',
        strpos($view, 'complianceInput.disabled = !offered;') !== false
    );
    assert_test(
        'UI: a second review offers the Compliance step too — its Unfavorable outcome requires it',
        strpos($view, 'data-second-review') === false
            && strpos($view, 'Compliance file required; moves the cycle to Resolve.') !== false
            && strpos($view, '$isSecondReview    = $endorsementNumber > 1;') !== false
    );
    assert_test(
        'UI: the client pre-flight only demands a compliance file when the step is offered',
        strpos($view, 'if (complianceOffered() && compliancePicker.count() === 0)') !== false
    );

    $opinionDropzoneTag = '';
    if (preg_match('/<div[^>]*id="opinionDropzone"[^>]*>/s', $view, $m)) {
        $opinionDropzoneTag = $m[0];
    }
    assert_test(
        'UI: the opinion upload area is always visible (never wrapped in a hidden container)',
        $opinionDropzoneTag !== '' && stripos($opinionDropzoneTag, 'hidden') === false
    );
    assert_test(
        'UI: both upload areas are multi-file inputs',
        strpos($view, 'name="opinion_files[]" multiple') !== false
            && strpos($view, 'name="compliance_files[]" multiple') !== false
    );
    assert_test(
        'UI: drag-and-drop is wired for both upload areas (one shared picker factory)',
        substr_count($view, "addEventListener('drop'") === 1
            && strpos($view, "dropzoneId: 'opinionDropzone'") !== false
            && strpos($view, "dropzoneId: 'complianceDropzone'") !== false
    );
    assert_test(
        'UI: previously submitted files are still listed with a download link when already submitted',
        strpos($view, 'submittedFiles') !== false
            && strpos($view, 'Opinion Documents (') !== false
            && strpos($view, 'Compliance Documents (') !== false
    );

    // ══════════════════════════════════════════════════════════════════════
    section('9. DIRECT POST ENFORCEMENT (opinionStore)');
    // ══════════════════════════════════════════════════════════════════════
    // ── Probe fixtures ────────────────────────────────────────────────────
    // Validation probes need a *first* endorsement (endorsement_number = 1) for
    // the first-review rules, plus a *second* endorsement to prove that the
    // second review also demands Compliance (and only for an Unfavorable
    // outcome). Both live on their own document + cycle so the persistence
    // assertions above stay untouched.
    [$probeDocId, $probeCycleId] = $newReferredDocument();
    $probeFirstEndorsement       = $endorsementOn($probeCycleId, 1);
    $probeSecondEndorsement      = $endorsementOn($probeCycleId, 2);
    $dirs[]                      = $root . '/public/uploads/documents/' . $probeDocId;

    $postOpinion = [
        'name'     => ['opinion-a.pdf'],
        'tmp_name' => [$pdfPath],
        'error'    => [UPLOAD_ERR_OK],
        'size'     => [filesize($pdfPath)],
        'type'     => ['application/pdf'],
    ];
    $postCompliance = [
        'name'     => ['compliance-a.pdf', 'compliance-b.png'],
        'tmp_name' => [$pdfPath, $pngPath],
        'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
        'size'     => [filesize($pdfPath), filesize($pngPath)],
        'type'     => ['application/pdf', 'image/png'],
    ];

    $subsBefore = (int) $pdo->query("SELECT COUNT(*) FROM committee_endorsement_submissions")->fetchColumn();
    $attsBefore = (int) $pdo->query(
        "SELECT COUNT(*) FROM document_attachments WHERE document_id = {$probeDocId}"
    )->fetchColumn();

    // ── A. FAVORABLE + compliance files → rejected ────────────────────────
    $probeA = opinion_post_probe($root, $userId, [
        'endorsement_id' => $probeFirstEndorsement,
        'opinion_type'   => 'FAVORABLE',
    ], [
        'opinion_files'    => $postOpinion,
        'compliance_files' => $postCompliance,
    ]);

    assert_test(
        'POST: FAVORABLE + compliance files is rejected server-side',
        errors_contain($probeA['errors'], 'only accepted when the opinion outcome is Unfavorable')
    );
    assert_test(
        'POST: the rejected request persists nothing (no submission, no attachment rows)',
        (int) $pdo->query("SELECT COUNT(*) FROM committee_endorsement_submissions")->fetchColumn() === $subsBefore
            && (int) $pdo->query("SELECT COUNT(*) FROM document_attachments WHERE document_id = {$probeDocId}")->fetchColumn() === $attsBefore
    );

    $flashOpinionNames = array_column($probeA['old']['selected_files']['opinion'] ?? [], 'name');
    assert_test(
        'POST: the previously selected opinion filenames are preserved for re-attachment',
        $flashOpinionNames === ['opinion-a.pdf']
    );
    assert_test(
        'POST: rejected compliance selections are not carried back to the form',
        ($probeA['old']['selected_files']['compliance'] ?? []) === []
    );

    // ── B. Missing opinion file is still required ─────────────────────────
    $probeB = opinion_post_probe($root, $userId, [
        'endorsement_id' => $probeFirstEndorsement,
        'opinion_type'   => 'FAVORABLE',
    ], []);
    assert_test(
        'POST: a submission without an opinion file is rejected',
        errors_contain($probeB['errors'], 'At least one opinion file is required')
    );

    // ── C. First UNFAVORABLE without compliance → rejected ────────────────
    $probeC = opinion_post_probe($root, $userId, [
        'endorsement_id' => $probeFirstEndorsement,
        'opinion_type'   => 'UNFAVORABLE',
    ], ['opinion_files' => $postOpinion]);
    assert_test(
        'POST: UNFAVORABLE without a compliance file is rejected',
        errors_contain($probeC['errors'], 'At least one compliance file is required for an Unfavorable opinion')
    );
    assert_test(
        'POST: rejected requests never create a submission row',
        (int) $pdo->query("SELECT COUNT(*) FROM committee_endorsement_submissions")->fetchColumn() === $subsBefore
    );

    // ── D. First UNFAVORABLE + multiple compliance files → accepted ───────
    $probeD = opinion_post_probe($root, $userId, [
        'endorsement_id' => $probeFirstEndorsement,
        'opinion_type'   => 'UNFAVORABLE',
    ], [
        'opinion_files'    => $postOpinion,
        'compliance_files' => $postCompliance,
    ]);
    assert_test(
        'POST: UNFAVORABLE + 2 compliance files produces no compliance-related rejection',
        !errors_contain($probeD['errors'], 'only accepted when the opinion outcome is Unfavorable')
            && !errors_contain($probeD['errors'], 'compliance file is required')
    );
    assert_test(
        'POST: UNFAVORABLE + 2 compliance files passes validation and proceeds to staging',
        strpos($probeD['error'], 'File upload failed') !== false
    );

    // ── E. Invalid compliance file type → rejected ────────────────────────
    $probeE = opinion_post_probe($root, $userId, [
        'endorsement_id' => $probeFirstEndorsement,
        'opinion_type'   => 'UNFAVORABLE',
    ], [
        'opinion_files'    => $postOpinion,
        'compliance_files' => [
            'name'     => ['malware.exe'],
            'tmp_name' => [$exePath],
            'error'    => [UPLOAD_ERR_OK],
            'size'     => [filesize($exePath)],
            'type'     => ['application/octet-stream'],
        ],
    ]);
    assert_test(
        'POST: an invalid compliance file type is rejected server-side',
        errors_contain($probeE['errors'], 'invalid extension')
            && errors_contain($probeE['errors'], 'malware.exe')
    );

    // ── F. Unauthenticated direct POST → sent to login ────────────────────
    $probeF = opinion_post_probe($root, $userId, [
        'endorsement_id' => $probeFirstEndorsement,
        'opinion_type'   => 'UNFAVORABLE',
    ], [
        'opinion_files'    => $postOpinion,
        'compliance_files' => $postCompliance,
    ], false);
    assert_test(
        'POST: an unauthenticated submission is refused before any processing',
        errors_contain([$probeF['error']], 'logged in')
    );
    assert_test(
        'POST: the unauthenticated attempt persisted nothing',
        (int) $pdo->query("SELECT COUNT(*) FROM committee_endorsement_submissions")->fetchColumn() === $subsBefore
            && (int) $pdo->query("SELECT COUNT(*) FROM document_attachments WHERE document_id = {$probeDocId}")->fetchColumn() === $attsBefore
    );

    // ── G. Second review + compliance → Compliance is required there too ───
    $probeG = opinion_post_probe($root, $userId, [
        'endorsement_id' => $probeSecondEndorsement,
        'opinion_type'   => 'UNFAVORABLE',
    ], [
        'opinion_files'    => $postOpinion,
        'compliance_files' => $postCompliance,
    ]);
    assert_test(
        'POST: a second Unfavorable + Compliance passes validation (Compliance is required on both reviews)',
        !errors_contain($probeG['errors'], 'compliance')
    );
    assert_test(
        'POST: the accepted second-review request reaches staging (probe: ' . $probeG['error'] . ')',
        strpos($probeG['error'], 'File upload failed') !== false
    );
    assert_test(
        'POST: the second-review Compliance upload never starts another endorsement cycle',
        (int) $pdo->query("SELECT COUNT(*) FROM committee_cycle_endorsements WHERE cycle_id = {$probeCycleId}")->fetchColumn() === 2
    );
    assert_test(
        'POST: a second Unfavorable WITHOUT Compliance is rejected as well',
        errors_contain(
            opinion_post_probe($root, $userId, [
                'endorsement_id' => $probeSecondEndorsement,
                'opinion_type'   => 'UNFAVORABLE',
            ], ['opinion_files' => $postOpinion])['errors'],
            'At least one compliance file is required for an Unfavorable opinion'
        )
    );
    assert_test(
        'POST: the refused second-review requests persisted nothing',
        (int) $pdo->query("SELECT COUNT(*) FROM committee_endorsement_submissions")->fetchColumn() === $subsBefore
            && (int) $pdo->query("SELECT COUNT(*) FROM document_attachments WHERE document_id = {$probeDocId}")->fetchColumn() === $attsBefore
    );

    // ── H. Unknown outcome + compliance files → rejected ──────────────────
    $probeH = opinion_post_probe($root, $userId, [
        'endorsement_id' => $probeSecondEndorsement,
        'opinion_type'   => 'DEFERRED',
    ], [
        'opinion_files'    => $postOpinion,
        'compliance_files' => $postCompliance,
    ]);
    assert_test(
        'POST: an unknown opinion outcome is rejected',
        errors_contain($probeH['errors'], 'valid opinion type')
    );
    assert_test(
        'POST: compliance files sent with an unknown outcome are refused, not stored',
        errors_contain($probeH['errors'], 'only accepted when the opinion outcome is Unfavorable')
            && ($probeH['old']['selected_files']['compliance'] ?? []) === []
    );
    assert_test(
        'POST: the invalid-outcome request persisted nothing',
        (int) $pdo->query("SELECT COUNT(*) FROM committee_endorsement_submissions")->fetchColumn() === $subsBefore
            && (int) $pdo->query("SELECT COUNT(*) FROM document_attachments WHERE document_id = {$probeDocId}")->fetchColumn() === $attsBefore
    );

    // ── I. Second review + FAVORABLE + compliance files → rejected ────────
    $probeI = opinion_post_probe($root, $userId, [
        'endorsement_id' => $probeSecondEndorsement,
        'opinion_type'   => 'FAVORABLE',
    ], [
        'opinion_files'    => $postOpinion,
        'compliance_files' => $postCompliance,
    ]);
    assert_test(
        'POST: a Favorable second review still refuses compliance files',
        errors_contain($probeI['errors'], 'only accepted when the opinion outcome is Unfavorable')
    );

    // ══════════════════════════════════════════════════════════════════════
    section('10. OUTCOME CHANGE PRESERVES SUBMITTED COMPLIANCE FILES');
    // ══════════════════════════════════════════════════════════════════════
    // endorsement1 recorded an UNFAVORABLE opinion with 2 compliance files.
    // A later FAVORABLE opinion for the same cycle must not remove them.
    $complianceBefore = array_column(
        $pdo->query("
            SELECT stored_path FROM document_attachments
             WHERE document_id = {$documentId} AND attachment_type = 'COMPLIANCE_FILE'
             ORDER BY stored_path
        ")->fetchAll(),
        'stored_path'
    );

    $endorsement3 = $insertEndorsement(2);
    $op3Staged    = [$stage($pdfPath, 'Opinion F.pdf')];
    $svc->insertStagedAttachments($op3Staged, $documentId, $userId, 'COMMITTEE', 'OPINION_FILE');
    $op3Ids       = array_values(array_filter($idsForStaged($op3Staged)));

    // No compliance files — a FAVORABLE outcome never carries them.
    $submission3 = $insertSubmission($endorsement3, 'FAVORABLE', $op3Ids[0], null, $userId);
    $linkFiles($submission3, $op3Ids, []);

    $complianceAfter = array_column(
        $pdo->query("
            SELECT stored_path FROM document_attachments
             WHERE document_id = {$documentId} AND attachment_type = 'COMPLIANCE_FILE'
             ORDER BY stored_path
        ")->fetchAll(),
        'stored_path'
    );

    assert_test(
        'CHANGE: a later Favorable outcome leaves every earlier compliance attachment untouched',
        $complianceBefore === $complianceAfter && !empty($complianceAfter)
    );

    $firstFilesAfterChange = load_submission_files($pdo, $endorsement1);
    assert_test(
        'CHANGE: the Unfavorable submission still links its 2 compliance files',
        count($firstFilesAfterChange['compliance']) === 2
    );

    $changeDownloadable = true;
    foreach ($firstFilesAfterChange['compliance'] as $f) {
        if (!is_file($root . '/public/' . ltrim($f['stored_path'], '/'))) {
            $changeDownloadable = false;
        }
    }
    assert_test('CHANGE: those compliance files are still downloadable from disk', $changeDownloadable);

    $favCompliance = (int) $pdo->query("
        SELECT COUNT(*) FROM committee_endorsement_submission_files
         WHERE submission_id = {$submission3} AND file_type = 'COMPLIANCE'
    ")->fetchColumn();
    assert_test('CHANGE: the Favorable submission carries no compliance links', $favCompliance === 0);

    // ══════════════════════════════════════════════════════════════════════
    section('11. E2E — FIRST UNFAVORABLE + COMPLIANCE → FOR SECOND ENDORSEMENT');
    // ══════════════════════════════════════════════════════════════════════
    // These assertions run the REAL CommitteeReferredController::opinionStore()
    // end-to-end (subprocess + injected upload service) and then inspect the
    // database, so they prove the files and the status actually persisted.
    /** Build a $_FILES-shaped array from real paths. */
    $e2eFiles = function (array $names, array $paths): array {
        return [
            'name'     => $names,
            'tmp_name' => $paths,
            'error'    => array_fill(0, count($names), UPLOAD_ERR_OK),
            'size'     => array_map('filesize', $paths),
            'type'     => array_fill(0, count($names), ''),
        ];
    };

    $forSecondEndorsementId = (int) $pdo->query("
        SELECT id FROM opinion_statuses
         WHERE name = 'For Second Endorsement' AND is_active = 1 AND is_deleted = 0 LIMIT 1
    ")->fetchColumn();
    $resolveId = (int) $pdo->query("
        SELECT id FROM opinion_statuses
         WHERE name = 'Resolve' AND is_active = 1 AND is_deleted = 0 LIMIT 1
    ")->fetchColumn();

    assert_test('E2E: opinion_statuses provides a "For Second Endorsement" row', $forSecondEndorsementId > 0);
    assert_test('E2E: opinion_statuses provides a "Resolve" row', $resolveId > 0);

    [$wfDocId, $wfCycleId] = $newReferredDocument();
    $wfEndorsement1        = $endorsementOn($wfCycleId, 1);
    $dirs[]                = $root . '/public/uploads/documents/' . $wfDocId;

    $probeRound1 = opinion_store_e2e_probe($root, $userId, [
        'endorsement_id' => $wfEndorsement1,
        'opinion_type'   => 'UNFAVORABLE',
        'remarks'        => 'Office is not in favour; compliance submitted.',
    ], [
        'opinion_files'    => $e2eFiles(['Opinion W1.pdf'], [$pdfPath]),
        'compliance_files' => $e2eFiles(['Compliance W1.pdf', 'Compliance W2.png'], [$pdfPath, $pngPath]),
    ]);

    assert_test(
        'E2E: the submission succeeds through the real controller (error: ' . ($probeRound1['error'] ?: 'none') . ')',
        $probeRound1['error'] === '' && $probeRound1['errors'] === []
    );
    assert_test(
        'E2E: the success flash announces the move to For Second Endorsement',
        strpos($probeRound1['success'], 'second endorsement') !== false
    );

    $wfSub = $pdo->prepare("SELECT * FROM committee_endorsement_submissions WHERE endorsement_id = ? LIMIT 1");
    $wfSub->execute([$wfEndorsement1]);
    $wfSubRow = $wfSub->fetch();

    assert_test('E2E: a submission row is persisted for the endorsement', !empty($wfSubRow));
    assert_test(
        'E2E: the submission links a real compliance attachment row (the upload reached the DB)',
        !empty($wfSubRow) && (int) $wfSubRow['compliance_file_attachment_id'] > 0
            && (int) $wfSubRow['opinion_file_attachment_id'] > 0
    );

    $wfOpCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM document_attachments WHERE document_id = {$wfDocId} AND attachment_type = 'OPINION_FILE'"
    )->fetchColumn();
    $wfCpCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM document_attachments WHERE document_id = {$wfDocId} AND attachment_type = 'COMPLIANCE_FILE'"
    )->fetchColumn();
    assert_test("E2E: exactly 1 OPINION_FILE attachment row persisted (got {$wfOpCount})", $wfOpCount === 1);
    assert_test("E2E: exactly 2 COMPLIANCE_FILE attachment rows persisted (got {$wfCpCount})", $wfCpCount === 2);

    $wfLinks = load_submission_files($pdo, $wfEndorsement1);
    assert_test(
        'E2E: each submitted file keeps its own database record and stays visible on the review screen',
        count($wfLinks['opinion']) === 1 && count($wfLinks['compliance']) === 2
    );

    $wfOnDisk = true;
    foreach (array_merge($wfLinks['opinion'], $wfLinks['compliance']) as $f) {
        if (!is_file($root . '/public/' . ltrim($f['stored_path'], '/'))) {
            $wfOnDisk = false;
        }
    }
    assert_test('E2E: every saved record resolves to its file on disk', $wfOnDisk);

    $wfStatus = (int) $pdo->query(
        "SELECT opinion_status_id FROM committee_cycle_endorsements WHERE id = {$wfEndorsement1}"
    )->fetchColumn();
    assert_test('E2E: the endorsement moved to the "For Second Endorsement" status row', $wfStatus === $forSecondEndorsementId);

    $wfEvent = $pdo->prepare("
        SELECT metadata FROM document_events
         WHERE document_id = ? AND event_type = 'OPINION_REQUESTED' ORDER BY id DESC LIMIT 1
    ");
    $wfEvent->execute([$wfDocId]);
    $wfMeta = json_decode((string) $wfEvent->fetchColumn(), true) ?: [];
    assert_test(
        'E2E: the audit event still records the new opinion status',
        ($wfMeta['opinion_status'] ?? '') === 'For Second Endorsement'
    );

    // ══════════════════════════════════════════════════════════════════════
    section('12. E2E — SECOND REVIEW: FAVORABLE OR UNFAVORABLE → RESOLVE');
    // ══════════════════════════════════════════════════════════════════════
    [$wf2DocId, $wf2CycleId] = $newReferredDocument();
    $wf2Endorsement1         = $endorsementOn($wf2CycleId, 1);
    $dirs[]                  = $root . '/public/uploads/documents/' . $wf2DocId;

    // Round 1 — first Unfavorable + Compliance → For Second Endorsement.
    $probeRound2 = opinion_store_e2e_probe($root, $userId, [
        'endorsement_id' => $wf2Endorsement1,
        'opinion_type'   => 'UNFAVORABLE',
    ], [
        'opinion_files'    => $e2eFiles(['Opinion Y1.pdf'], [$pdfPath]),
        'compliance_files' => $e2eFiles(['Compliance Y1.pdf'], [$pdfPath]),
    ]);
    assert_test(
        'E2E: round 1 (first Unfavorable + Compliance) succeeds',
        $probeRound2['error'] === '' && $probeRound2['errors'] === []
    );
    assert_test(
        'E2E: round 1 sets the endorsement to "For Second Endorsement"',
        (int) $pdo->query("SELECT opinion_status_id FROM committee_cycle_endorsements WHERE id = {$wf2Endorsement1}")->fetchColumn()
            === $forSecondEndorsementId
    );

    // ── 12a. The second review is FAVORABLE ───────────────────────────────
    $wf2Endorsement2Fav = $endorsementOn($wf2CycleId, 2);
    $probeFav = opinion_store_e2e_probe($root, $userId, [
        'endorsement_id' => $wf2Endorsement2Fav,
        'opinion_type'   => 'FAVORABLE',
    ], ['opinion_files' => $e2eFiles(['Opinion Y2.pdf'], [$pdfPath])]);

    assert_test(
        'E2E: a second review may be recorded as Favorable',
        $probeFav['error'] === '' && $probeFav['errors'] === []
    );
    assert_test(
        'E2E: a Favorable second review follows the existing favorable workflow status',
        (int) $pdo->query("SELECT opinion_status_id FROM committee_cycle_endorsements WHERE id = {$wf2Endorsement2Fav}")->fetchColumn()
            === $favStatusId
    );
    $favLinks = load_submission_files($pdo, $wf2Endorsement2Fav);
    assert_test(
        'E2E: a Favorable second review keeps its opinion file and no compliance file',
        count($favLinks['opinion']) === 1 && count($favLinks['compliance']) === 0
    );

    // ── 12b. The second review is UNFAVORABLE + Compliance → Resolve ──────
    $wf2Endorsement2Unf = $endorsementOn($wf2CycleId, 2);
    $probeUnfav = opinion_store_e2e_probe($root, $userId, [
        'endorsement_id' => $wf2Endorsement2Unf,
        'opinion_type'   => 'UNFAVORABLE',
    ], [
        'opinion_files'    => $e2eFiles(['Opinion Y3.pdf'], [$pdfPath]),
        'compliance_files' => $e2eFiles(['Compliance Y3-a.pdf', 'Compliance Y3-b.png'], [$pdfPath, $pngPath]),
    ]);

    assert_test(
        'E2E: a second review may be recorded as Unfavorable with its Compliance files',
        $probeUnfav['error'] === '' && $probeUnfav['errors'] === []
    );
    assert_test(
        'E2E: the second Unfavorable moves the endorsement to the "Resolve" status row',
        (int) $pdo->query("SELECT opinion_status_id FROM committee_cycle_endorsements WHERE id = {$wf2Endorsement2Unf}")->fetchColumn()
            === $resolveId
    );
    assert_test(
        'E2E: the second Unfavorable redirects to the Resolve workflow',
        strpos($probeUnfav['success'], 'resolution') !== false
    );

    $unfLinks = load_submission_files($pdo, $wf2Endorsement2Unf);
    assert_test(
        'E2E: every second-review file is its own record (1 opinion, 2 compliance)',
        count($unfLinks['opinion']) === 1 && count($unfLinks['compliance']) === 2
    );

    // The first review's Compliance files must survive the second submission.
    $firstLinks = load_submission_files($pdo, $wf2Endorsement1);
    assert_test(
        'E2E: the first review keeps its own Compliance record — nothing is replaced',
        count($firstLinks['compliance']) === 1
            && $firstLinks['compliance'][0]['id'] !== $unfLinks['compliance'][0]['id']
    );
    $unfDownloadable = true;
    foreach (array_merge($unfLinks['opinion'], $unfLinks['compliance'], $firstLinks['compliance']) as $f) {
        if (!is_file($root . '/public/' . ltrim((string) $f['stored_path'], '/'))) {
            $unfDownloadable = false;
        }
    }
    assert_test(
        'E2E: old and new Compliance records all resolve to a file on disk (downloadable)',
        $unfDownloadable
    );
    assert_test(
        'E2E: the second Unfavorable + Compliance created no further endorsement row (no new cycle)',
        (int) $pdo->query("SELECT COUNT(*) FROM committee_cycle_endorsements WHERE cycle_id = {$wf2CycleId}")->fetchColumn() === 3
    );

    $unfMeta = $pdo->prepare("
        SELECT metadata FROM document_events
         WHERE document_id = ? AND event_type = 'OPINION_REQUESTED' ORDER BY id DESC LIMIT 1
    ");
    $unfMeta->execute([$wf2DocId]);
    assert_test(
        'E2E: the audit event for the second Unfavorable records the Resolve status',
        (json_decode((string) $unfMeta->fetchColumn(), true)['opinion_status'] ?? '') === 'Resolve'
    );

    // ── 12c. A third endorsement / cycle is impossible ────────────────────
    $probeThird = second_endorse_probe($root, $userId, $wf2Endorsement1);
    assert_test(
        'E2E: a third endorsement for the same cycle is refused (probe: ' . $probeThird['error'] . ')',
        strpos($probeThird['error'], 'second endorsement already exists') !== false
            && $probeThird['success'] === ''
    );
    assert_test(
        'E2E: the refused third attempt created no endorsement row',
        (int) $pdo->query("SELECT COUNT(*) FROM committee_cycle_endorsements WHERE cycle_id = {$wf2CycleId}")->fetchColumn() === 3
    );

    // ── 12d. Server-side eligibility for the second endorsement ───────────
    // The eligibility rule is enforced by the controller, not by hiding the
    // button. Even with the Unfavorable submission on record, issuing a second
    // endorsement is refused while the endorsement's opinion_statuses row is
    // not "For Second Endorsement".
    $pdo->prepare("UPDATE committee_cycle_endorsements SET opinion_status_id = ? WHERE id = ?")
        ->execute([$unfavStatusId, $wf2Endorsement1]);

    $probeIneligible = second_endorse_probe($root, $userId, $wf2Endorsement1);
    assert_test(
        'ELIGIBILITY: a second endorsement is refused unless the office is awaiting one (probe: '
            . $probeIneligible['error'] . ')',
        stripos($probeIneligible['error'], 'not awaiting a second endorsement') !== false
            && $probeIneligible['success'] === ''
    );
    assert_test(
        'ELIGIBILITY: the refused ineligible attempt created no endorsement row',
        (int) $pdo->query("SELECT COUNT(*) FROM committee_cycle_endorsements WHERE cycle_id = {$wf2CycleId}")->fetchColumn() === 3
    );

    // ══════════════════════════════════════════════════════════════════════
    section('13. opinion_statuses IS THE SINGLE SOURCE OF TRUTH');
    // ══════════════════════════════════════════════════════════════════════
    $duplicateStatuses = $pdo->query("
        SELECT name, COUNT(*) AS cnt FROM opinion_statuses GROUP BY name HAVING cnt > 1
    ")->fetchAll();
    assert_test(
        'STATUS: no duplicate opinion_statuses rows exist (' . count($duplicateStatuses) . ' duplicated names)',
        $duplicateStatuses === []
    );

    $activeStatuses = array_column(
        $pdo->query("SELECT name FROM opinion_statuses WHERE is_active = 1 AND is_deleted = 0")->fetchAll(),
        'name'
    );
    assert_test(
        'STATUS: every status the workflow resolves exists in opinion_statuses',
        empty(array_diff(['Favorable', 'Unfavorable', 'For Second Endorsement', 'Resolve'], $activeStatuses))
    );

    assert_test(
        'STATUS: neither submission inserted into opinion_statuses (row count unchanged)',
        (int) $pdo->query("SELECT COUNT(*) FROM opinion_statuses")->fetchColumn() === $statusRowsBefore
    );

    $statusNameUnique = false;
    foreach ($pdo->query("SHOW INDEX FROM opinion_statuses")->fetchAll() as $idx) {
        if ((int) $idx['Non_unique'] === 0 && $idx['Column_name'] === 'name') {
            $statusNameUnique = true;
        }
    }
    assert_test(
        'STATUS: opinion_statuses.name is uniquely indexed so the seeding migration stays idempotent',
        $statusNameUnique
    );
    assert_test(
        'STATUS: the required workflow statuses are guaranteed by an idempotent migration',
        is_file($root . '/database/migrations/073_normalize_second_endorsement_opinion_statuses.php')
    );

    require_once $root . '/app/controllers/Committee/CommitteeReferredController.php';
    $ruleController = new CommitteeReferredController($pdo);
    assert_test(
        'STATUS: FAVORABLE maps to "Favorable" on any review',
        $ruleController->opinionStatusNameFor('FAVORABLE', 1) === 'Favorable'
            && $ruleController->opinionStatusNameFor('FAVORABLE', 2) === 'Favorable'
    );
    assert_test(
        'STATUS: a first UNFAVORABLE maps to "For Second Endorsement"',
        $ruleController->opinionStatusNameFor('UNFAVORABLE', 1) === 'For Second Endorsement'
    );
    assert_test(
        'STATUS: a second UNFAVORABLE maps to "Resolve"',
        $ruleController->opinionStatusNameFor('UNFAVORABLE', 2) === 'Resolve'
    );

    // ══════════════════════════════════════════════════════════════════════
    section('14. FOR OPINION PROGRESS INDICATOR + RESUBMISSION AFFORDANCES');
    // ══════════════════════════════════════════════════════════════════════
    // The indicator must describe the *review stage*, never the number of
    // submissions: only a cleared (Favorable) office advances it. These
    // assertions call the controller's pure summariser directly.
    $endorsementRow = function (array $overrides): array {
        return $overrides + [
            'endorsement_id'      => 1,
            'cycle_id'            => 1,
            'opinion_office_id'   => 1,
            'endorsement_number'  => 1,
            'submission_id'       => null,
            'opinion_type'        => null,
            'opinion_status_name' => 'Pending',
        ];
    };

    // 1. Nothing submitted yet.
    $p = $ruleController->summarizeOpinionProgress([$endorsementRow([])]);
    assert_test(
        'PROGRESS: an office with no opinion is pending and offers "Submit Opinion"',
        $p['total'] === 1 && $p['cleared'] === 0 && $p['pending'] === 1
            && $p['offices'][1]['pending_action'] === 'SUBMIT_OPINION'
    );

    // 2. First Unfavorable + Compliance → awaiting the second endorsement.
    $p = $ruleController->summarizeOpinionProgress([
        $endorsementRow([
            'submission_id'       => 11,
            'opinion_type'        => 'UNFAVORABLE',
            'opinion_status_name' => 'For Second Endorsement',
        ]),
    ]);
    assert_test(
        'PROGRESS: an Unfavorable opinion awaiting its second endorsement does NOT advance the indicator',
        $p['cleared'] === 0 && $p['reviewed'] === 1 && $p['awaiting_second'] === 1 && $p['pending'] === 1
    );
    assert_test(
        'PROGRESS: that office exposes the second-endorsement action (resubmission entry point)',
        $p['offices'][1]['pending_action'] === 'SECOND_ENDORSEMENT'
            && $p['offices'][1]['cleared'] === false
            && $p['offices'][1]['reviewed'] === true
    );

    // 3. The second endorsement has been issued but not answered yet.
    $p = $ruleController->summarizeOpinionProgress([
        $endorsementRow([
            'endorsement_id'      => 20,
            'submission_id'       => 11,
            'opinion_type'        => 'UNFAVORABLE',
            'opinion_status_name' => 'For Second Endorsement',
        ]),
        $endorsementRow(['endorsement_id' => 21, 'endorsement_number' => 2]),
    ]);
    assert_test(
        'PROGRESS: the highest endorsement per office wins, so the open #2 offers "Submit Opinion"',
        $p['total'] === 1 && $p['cleared'] === 0
            && $p['offices'][1]['endorsement_number'] === 2
            && $p['offices'][1]['pending_action'] === 'SUBMIT_OPINION'
    );

    // 4. Favorable → cleared, no pending action, indicator advances.
    $p = $ruleController->summarizeOpinionProgress([
        $endorsementRow([
            'submission_id'       => 30,
            'opinion_type'        => 'FAVORABLE',
            'opinion_status_name' => 'Favorable',
        ]),
    ]);
    assert_test(
        'PROGRESS: a Favorable opinion clears the office and advances the indicator',
        $p['cleared'] === 1 && $p['pending'] === 0
            && $p['offices'][1]['cleared'] === true
            && $p['offices'][1]['pending_action'] === null
    );

    // 5. Second Unfavorable → Resolve: still not cleared, no further action.
    $p = $ruleController->summarizeOpinionProgress([
        $endorsementRow([
            'endorsement_id'      => 40,
            'submission_id'       => 41,
            'opinion_type'        => 'UNFAVORABLE',
            'opinion_status_name' => 'For Second Endorsement',
        ]),
        $endorsementRow([
            'endorsement_id'      => 42,
            'endorsement_number'  => 2,
            'submission_id'       => 43,
            'opinion_type'        => 'UNFAVORABLE',
            'opinion_status_name' => 'Resolve',
        ]),
    ]);
    assert_test(
        'PROGRESS: a second Unfavorable moves the office to Resolve without advancing the indicator'
            . ' and without offering another cycle',
        $p['total'] === 1 && $p['cleared'] === 0 && $p['awaiting_second'] === 0
            && $p['offices'][1]['pending_action'] === null
    );

    // 6. Two offices, one cleared and one awaiting: 50 %, not 100 %.
    $p = $ruleController->summarizeOpinionProgress([
        $endorsementRow(['opinion_office_id' => 1, 'submission_id' => 50, 'opinion_type' => 'FAVORABLE', 'opinion_status_name' => 'Favorable']),
        $endorsementRow(['opinion_office_id' => 2, 'submission_id' => 51, 'opinion_type' => 'UNFAVORABLE', 'opinion_status_name' => 'For Second Endorsement']),
    ]);
    assert_test(
        'PROGRESS: 1 of 2 offices cleared reports 1/2, so an Unfavorable opinion cannot reach 100 %',
        $p['total'] === 2 && $p['cleared'] === 1 && $p['reviewed'] === 2 && $p['pending'] === 1
    );

    // ── The views must expose those same rules ────────────────────────────
    $indexView   = (string) file_get_contents($root . '/resources/views/committee/referred/index.php');
    $opinionView = (string) file_get_contents($root . '/resources/views/committee/referred/opinion.php');

    assert_test(
        'UI: the For Opinion list advances the indicator from the cleared count, not the submission count',
        strpos($indexView, '$clearedCount') !== false
            && strpos($indexView, '$submittedCount') === false
    );
    assert_test(
        'UI: a chip whose office is For Second Endorsement renders the review link (resubmission works)',
        strpos($indexView, "SECOND_ENDORSEMENT") !== false
            && strpos($indexView, 'committee/referred/opinion?endorsement_id=') !== false
    );
    assert_test(
        'UI: the review screen still offers the second endorsement route',
        strpos($opinionView, 'committee/referred/second-endorse') !== false
            && strpos($opinionView, 'Issue Second Endorsement') !== false
    );
    assert_test(
        'UI: the second review screen offers the Compliance upload as well (no second-review guard)',
        strpos($opinionView, 'data-second-review') === false
            && strpos($opinionView, "return selectedType() === 'UNFAVORABLE';") !== false
    );

} catch (Throwable $e) {
    echo PHP_EOL . '  [ERROR] ' . $e->getMessage() . PHP_EOL;
    $failed++;
} finally {
    // ── Cleanup: remove everything created by this test ────────────────────
    try {
        // Cycles cascade → endorsements → submissions → join rows.
        foreach ($created['cycles'] as $id) {
            $pdo->prepare("DELETE FROM committee_cycles WHERE id = ?")->execute([$id]);
        }
        // document_events → documents is ON DELETE RESTRICT, so the audit rows
        // the end-to-end opinion submissions created must go first.
        foreach ($created['docs'] as $id) {
            $pdo->prepare("DELETE FROM document_events WHERE document_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM document_attachments WHERE document_id = ?")->execute([$id]);
        }
        // Documents cascade → document_attachments.
        foreach ($created['docs'] as $id) {
            $pdo->prepare("DELETE FROM documents WHERE id = ?")->execute([$id]);
        }
        foreach ($created['users'] as $id) {
            $pdo->prepare("DELETE FROM user_accounts WHERE id = ?")->execute([$id]);
        }
        foreach ($created['offices'] as $id) {
            $pdo->prepare("DELETE FROM opinion_offices WHERE id = ?")->execute([$id]);
        }
        foreach ($created['ostatus'] as $id) {
            $pdo->prepare("DELETE FROM opinion_statuses WHERE id = ?")->execute([$id]);
        }
        foreach ($created['dtypes'] as $id) {
            $pdo->prepare("DELETE FROM document_types WHERE id = ?")->execute([$id]);
        }
        foreach ($created['stypes'] as $id) {
            $pdo->prepare("DELETE FROM source_types WHERE id = ?")->execute([$id]);
        }
        foreach ($created['dstatus'] as $id) {
            $pdo->prepare("DELETE FROM document_statuses WHERE id = ?")->execute([$id]);
        }
    } catch (Throwable $cleanupError) {
        echo '  [WARN] Cleanup issue: ' . $cleanupError->getMessage() . PHP_EOL;
    }

    foreach ($dirs as $dir) {
        rrmdir($dir);
    }
}

// ── Summary ────────────────────────────────────────────────────────────────
echo PHP_EOL;
$total = $passed + $failed;
echo "Results: {$passed}/{$total} passed" . ($failed > 0 ? ", {$failed} FAILED" : '') . PHP_EOL;
exit($failed > 0 ? 1 : 0);

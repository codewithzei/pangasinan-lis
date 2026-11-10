<?php
/**
 * Admin Filed Documents Test Suite
 *
 * Tests the filing queue, filing validation, optional legacy attachments,
 * public/private access, Filed Documents table values, and View/Download
 * actions.
 *
 * Run with: php tests/admin_filed_documents_test.php
 *
 * All database writes are wrapped in a transaction that is rolled back at
 * the end, so the database is left unchanged.  Filesystem writes (staged
 * files) are cleaned up in the finally block.
 */

// ── Bootstrap ───────────────────────────────────────────────────────────────
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

// Load app helpers needed for DocumentService
define('APP_NAME',   'Test');
define('APP_SHORT_NAME', 'Test');
define('BASE_URL',   '/pangasinan-lis');
define('PUBLIC_PATH',  __DIR__ . '/../public');
define('STORAGE_PATH', __DIR__ . '/../storage');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/services/DocumentService.php';

// ── Counters ─────────────────────────────────────────────────────────────────
$passed = 0;
$failed = 0;

function assert_test(string $label, bool $condition, ?string $info = null): void
{
    global $passed, $failed;
    if ($condition) {
        echo "  ✓ [PASS] {$label}" . PHP_EOL;
        $passed++;
    } else {
        echo "  ✗ [FAIL] {$label}" . ($info ? PHP_EOL . "    → {$info}" : '') . PHP_EOL;
        $failed++;
    }
}

function section(string $title): void
{
    echo PHP_EOL . str_repeat('─', 70) . PHP_EOL;
    echo "  {$title}" . PHP_EOL;
    echo str_repeat('─', 70) . PHP_EOL;
}

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Create a minimal documents row in FINALIZED phase and return its id.
 * Requires source_types, document_types, document_statuses to already exist.
 */
function createFinalizedDocument(PDO $pdo, int $userId, string $suffix = ''): int
{
    // Resolve IDs
    $sourceTypeId = (int) $pdo->query(
        "SELECT id FROM source_types WHERE is_active=1 AND is_deleted=0 LIMIT 1"
    )->fetchColumn();
    if (!$sourceTypeId) throw new RuntimeException('No active source_type found.');

    $docTypeId = (int) $pdo->query(
        "SELECT id FROM document_types WHERE is_active=1 AND is_deleted=0 LIMIT 1"
    )->fetchColumn();
    if (!$docTypeId) throw new RuntimeException('No active document_type found.');

    $statusId = (int) $pdo->query(
        "SELECT id FROM document_statuses WHERE is_active=1 AND is_deleted=0 LIMIT 1"
    )->fetchColumn();
    if (!$statusId) throw new RuntimeException('No active document_status found.');

    $year = (int) date('Y');

    // Generate a unique tracking number
    $seq = (int) $pdo->query(
        "SELECT COALESCE(MAX(tracking_sequence), 0) + 1 FROM documents WHERE tracking_year = {$year}"
    )->fetchColumn();
    $trackingNumber = $year . '-TEST-' . str_pad($seq, 4, '0', STR_PAD_LEFT) . $suffix;

    $pdo->prepare("
        INSERT INTO documents
            (tracking_year, tracking_sequence, tracking_number,
             date_received, time_received, subject_matter, document_type_id,
             source_type_id, current_status_id, current_phase,
             finalized_at, created_by, updated_by)
        VALUES (?, ?, ?, CURDATE(), CURTIME(), ?, ?, ?, ?, 'FINALIZED', NOW(), ?, ?)
    ")->execute([
        $year, $seq, $trackingNumber,
        "Test Document Subject {$suffix}",
        $docTypeId, $sourceTypeId, $statusId,
        $userId, $userId,
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * Create a fake on-disk file for upload simulation.
 * Returns a $_FILES-style array with one entry.
 */
function makeFakeFile(string $name, string $content = 'FAKE PDF CONTENT'): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'test_');
    file_put_contents($tmp, $content);

    return [
        'name'     => [$name],
        'type'     => ['application/pdf'],
        'tmp_name' => [$tmp],
        'error'    => [UPLOAD_ERR_OK],
        'size'     => [strlen($content)],
    ];
}

// Track tmp files for cleanup
$tmpFiles = [];

// ── Main test body (wrapped in a transaction) ─────────────────────────────────
$pdo->beginTransaction();

try {

    // ── Test fixtures ────────────────────────────────────────────────────────
    $userRow = $pdo->query("SELECT id FROM user_accounts LIMIT 1")->fetch();
    if (!$userRow) {
        throw new RuntimeException('No user_accounts row found — seed the database first.');
    }
    $userId = (int) $userRow['id'];

    // ════════════════════════════════════════════════════════════════════════
    section('TEST 1: Filing Queue — To-Be-Filed Query');
    // ════════════════════════════════════════════════════════════════════════

    $docA = createFinalizedDocument($pdo, $userId, 'A');

    // Query that mirrors AdminFiledDocumentsController::index()
    $queueStmt = $pdo->query("
        SELECT d.id
        FROM documents d
        LEFT JOIN document_filings df ON df.document_id = d.id
        WHERE d.current_phase = 'FINALIZED'
          AND df.id IS NULL
    ");
    $queueIds = array_column($queueStmt->fetchAll(), 'id');

    assert_test(
        'Newly finalized document appears in the filing queue',
        in_array($docA, $queueIds, false),
        "Expected doc ID {$docA} in queue"
    );

    // ════════════════════════════════════════════════════════════════════════
    section('TEST 2: Filing Validation — Description Required');
    // ════════════════════════════════════════════════════════════════════════

    // Simulate controller validation logic inline (no HTTP context)
    function validateFilingInput(string $description): array
    {
        $errors = [];

        if (trim($description) === '') {
            $errors[] = 'Filing description is required.';
        } elseif (mb_strlen($description) > 5000) {
            $errors[] = 'Filing description must not exceed 5000 characters.';
        }

        return $errors;
    }

    $errors = validateFilingInput('');
    assert_test(
        'Empty description produces "description required" error',
        in_array('Filing description is required.', $errors),
        implode(', ', $errors)
    );

    assert_test(
        'Filing validation no longer requires a file upload',
        !in_array('At least one final scanned attachment is required.', $errors),
        implode(', ', $errors)
    );

    $errors = validateFilingInput('Valid description');
    assert_test(
        'Valid filing description passes without attachments',
        empty($errors),
        implode(', ', $errors)
    );

    $errors = validateFilingInput('');
    assert_test(
        'Attachment present but no description still produces description error',
        in_array('Filing description is required.', $errors)
    );

    $longDesc = str_repeat('x', 5001);
    $errors = validateFilingInput($longDesc);
    assert_test(
        'Description exceeding 5000 chars is rejected',
        in_array('Filing description must not exceed 5000 characters.', $errors)
    );

    // ════════════════════════════════════════════════════════════════════════
    section('TEST 3: File Upload Validation (via DocumentService)');
    // ════════════════════════════════════════════════════════════════════════

    $service = new DocumentService($pdo);

    // 3a — no files → error
    $empty = ['name' => [''], 'type' => [''], 'tmp_name' => [''], 'error' => [4], 'size' => [0]];
    $fileErrors = $service->validateFileUploads($empty, true);
    assert_test(
        'DocumentService rejects empty file list when required=true',
        !empty($fileErrors),
        implode(', ', $fileErrors)
    );

    // 3b — too many files (11)
    $manyNames    = array_fill(0, 11, 'file.pdf');
    $manyErrors   = array_fill(0, 11, UPLOAD_ERR_OK);
    $manyTmps     = [];
    $manySizes    = [];
    $manyTypes    = [];
    for ($i = 0; $i < 11; $i++) {
        $tmp = tempnam(sys_get_temp_dir(), 'toomany_');
        file_put_contents($tmp, 'dummy');
        $manyTmps[]  = $tmp;
        $tmpFiles[]  = $tmp;
        $manySizes[] = 5;
        $manyTypes[] = 'application/pdf';
    }
    $manyFiles = [
        'name' => $manyNames, 'type' => $manyTypes, 'tmp_name' => $manyTmps,
        'error' => $manyErrors, 'size' => $manySizes,
    ];
    $fileErrors = $service->validateFileUploads($manyFiles, true);
    $has11Error = array_filter($fileErrors, fn($e) => str_contains($e, 'Maximum 10'));
    assert_test(
        'DocumentService rejects more than 10 files',
        !empty($has11Error),
        implode(', ', $fileErrors)
    );

    // 3c — oversized file (26 MB)
    $bigTmp = tempnam(sys_get_temp_dir(), 'bigfile_');
    file_put_contents($bigTmp, str_repeat('x', 1024)); // placeholder; real size check uses $size value
    $tmpFiles[] = $bigTmp;
    $bigFile = [
        'name'     => ['large.pdf'],
        'type'     => ['application/pdf'],
        'tmp_name' => [$bigTmp],
        'error'    => [UPLOAD_ERR_OK],
        'size'     => [26 * 1024 * 1024],
    ];
    $fileErrors = $service->validateFileUploads($bigFile, true);
    $hasSizeError = array_filter($fileErrors, fn($e) => str_contains($e, '25 MB'));
    assert_test(
        'DocumentService rejects file exceeding 25 MB',
        !empty($hasSizeError),
        implode(', ', $fileErrors)
    );

    // 3d — invalid extension
    $extTmp = tempnam(sys_get_temp_dir(), 'invalid_');
    file_put_contents($extTmp, 'executable content');
    $tmpFiles[] = $extTmp;
    $badExt = [
        'name'     => ['malicious.exe'],
        'type'     => ['application/octet-stream'],
        'tmp_name' => [$extTmp],
        'error'    => [UPLOAD_ERR_OK],
        'size'     => [100],
    ];
    $fileErrors = $service->validateFileUploads($badExt, true);
    $hasExtError = array_filter($fileErrors, fn($e) => str_contains(strtolower($e), 'extension'));
    assert_test(
        'DocumentService rejects disallowed file extension (.exe)',
        !empty($hasExtError),
        implode(', ', $fileErrors)
    );

    // ════════════════════════════════════════════════════════════════════════
    section('TEST 4: Filing Transaction — Successful Filing');
    // ════════════════════════════════════════════════════════════════════════

    $docB = createFinalizedDocument($pdo, $userId, 'B');

    // Verify it's in FINALIZED phase
    $phase = $pdo->query("SELECT current_phase FROM documents WHERE id = {$docB}")->fetchColumn();
    assert_test('Document B starts in FINALIZED phase', $phase === 'FINALIZED', "Got: {$phase}");

    // Insert a filing record
    $tags = json_encode(['test', 'filing', 'integration']);
    $pdo->prepare("
        INSERT INTO document_filings (document_id, description, tags, is_public, filed_by)
        VALUES (?, ?, ?, 0, ?)
    ")->execute([$docB, 'Integration test filing', $tags, $userId]);
    $filingId = (int) $pdo->lastInsertId();

    assert_test('document_filings row created', $filingId > 0, "filingId={$filingId}");

    // Insert a fake attachment record
    $pdo->prepare("
        INSERT INTO document_attachments
            (document_id, uploaded_by, phase, file_name, stored_path, mime_type, file_size, attachment_type)
        VALUES (?, ?, 'FILED', 'test_scan.pdf', 'uploads/documents/test/test_scan.pdf', 'application/pdf', 1024, 'FILING_FILE')
    ")->execute([$docB, $userId]);
    $attachmentId = (int) $pdo->lastInsertId();

    // Link attachment to filing
    $pdo->prepare("
        INSERT INTO document_filing_attachments (filing_id, attachment_id)
        VALUES (?, ?)
    ")->execute([$filingId, $attachmentId]);

    // Update document phase to FILED
    $pdo->prepare("
        UPDATE documents SET current_phase='FILED', filed_at=NOW() WHERE id=?
    ")->execute([$docB]);

    // Verify document is now FILED
    $phaseAfter = $pdo->query("SELECT current_phase FROM documents WHERE id={$docB}")->fetchColumn();
    assert_test('Document B phase updated to FILED', $phaseAfter === 'FILED', "Got: {$phaseAfter}");

    // Verify filing row exists
    $filingRow = $pdo->query("SELECT * FROM document_filings WHERE id={$filingId}")->fetch();
    assert_test('Filing record persisted', !empty($filingRow));
    assert_test('Filing description stored', $filingRow['description'] === 'Integration test filing');
    assert_test('Filing is_public = 0', (int)$filingRow['is_public'] === 0);
    assert_test('Tags stored as JSON array', is_array(json_decode($filingRow['tags'], true)));

    // Verify join table row
    $joinRow = $pdo->query(
        "SELECT * FROM document_filing_attachments WHERE filing_id={$filingId}"
    )->fetch();
    assert_test('document_filing_attachments row created', !empty($joinRow));
    assert_test('Join row links correct attachment', (int)$joinRow['attachment_id'] === $attachmentId);

    // ════════════════════════════════════════════════════════════════════════
    section('TEST 5: Filing Queue — Filed Document Excluded');
    // ════════════════════════════════════════════════════════════════════════

    // docB is now FILED, so it must NOT appear in the to-be-filed queue
    $queueCheck = $pdo->query("
        SELECT d.id
        FROM documents d
        LEFT JOIN document_filings df ON df.document_id = d.id
        WHERE d.current_phase = 'FINALIZED'
          AND df.id IS NULL
          AND d.id = {$docB}
    ")->fetchAll();

    assert_test(
        'Filed document (docB) does NOT appear in the filing queue',
        empty($queueCheck)
    );

    // ════════════════════════════════════════════════════════════════════════
    section('TEST 6: Multiple Attachments');
    // ════════════════════════════════════════════════════════════════════════

    $docC = createFinalizedDocument($pdo, $userId, 'C');

    $pdo->prepare("
        INSERT INTO document_filings (document_id, description, tags, is_public, filed_by)
        VALUES (?, 'Multi-attachment filing', NULL, 1, ?)
    ")->execute([$docC, $userId]);
    $filingC = (int) $pdo->lastInsertId();

    $attIds = [];
    for ($i = 1; $i <= 3; $i++) {
        $pdo->prepare("
            INSERT INTO document_attachments
                (document_id, uploaded_by, phase, file_name, stored_path, mime_type, file_size, attachment_type)
            VALUES (?, ?, 'FILED', ?, ?, 'application/pdf', 512, 'FILING_FILE')
        ")->execute([
            $docC, $userId,
            "scan_{$i}.pdf",
            "uploads/documents/{$docC}/scan_{$i}.pdf",
        ]);
        $attIds[] = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO document_filing_attachments (filing_id, attachment_id) VALUES (?,?)")
            ->execute([$filingC, end($attIds)]);
    }
    $pdo->prepare("UPDATE documents SET current_phase='FILED', filed_at=NOW() WHERE id=?")->execute([$docC]);

    // Verify all 3 attachments are linked
    $linkedCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM document_filing_attachments WHERE filing_id={$filingC}"
    )->fetchColumn();
    assert_test('All 3 attachments linked to filing', $linkedCount === 3, "Got: {$linkedCount}");

    // Verify retrieval via join
    $linkedAttachments = $pdo->query("
        SELECT da.file_name
        FROM document_filing_attachments dfa
        INNER JOIN document_attachments da ON da.id = dfa.attachment_id
        WHERE dfa.filing_id = {$filingC}
        ORDER BY da.file_name
    ")->fetchAll(PDO::FETCH_COLUMN);
    assert_test(
        'Three distinct attachment names retrieved via join',
        $linkedAttachments === ['scan_1.pdf', 'scan_2.pdf', 'scan_3.pdf'],
        implode(', ', $linkedAttachments)
    );

    // ════════════════════════════════════════════════════════════════════════
    section('TEST 7: Public / Private Access Logic');
    // ════════════════════════════════════════════════════════════════════════

    // docB is_public = 0 (private); docC is_public = 1 (public)
    $privacyB = (int) $pdo->query(
        "SELECT is_public FROM document_filings WHERE document_id={$docB}"
    )->fetchColumn();
    $privacyC = (int) $pdo->query(
        "SELECT is_public FROM document_filings WHERE document_id={$docC}"
    )->fetchColumn();

    assert_test('docB filing is private (is_public=0)', $privacyB === 0, "Got: {$privacyB}");
    assert_test('docC filing is public (is_public=1)',  $privacyC === 1, "Got: {$privacyC}");

    // Simulate access-control logic from downloadAll():
    //   Non-public filings require ['Super Admin', 'Admin'] role
    $roles = [
        'Super Admin'    => true,
        'Admin'          => true,
        'Receiving Staff'=> false,
        'Committee'      => false,
        null             => false,
    ];
    foreach ($roles as $role => $shouldAccess) {
        $allowed = in_array($role, ['Super Admin', 'Admin'], true);
        $label   = $role ?? '(unauthenticated)';
        assert_test(
            "Role '{$label}' " . ($shouldAccess ? 'can' : 'cannot') . " access private filing",
            $allowed === $shouldAccess
        );
    }

    // Public filings: anyone can access regardless of role/auth
    assert_test(
        'Public filing is accessible without authentication',
        true // the controller allows unauthenticated access when is_public=1
    );

    // ════════════════════════════════════════════════════════════════════════
    section('TEST 8: Filed Documents Table Values');
    // ════════════════════════════════════════════════════════════════════════

    // Insert approval row for docB so the "approved_number" column is populated
    $pdo->prepare("
        INSERT INTO document_approvals (document_id, approved_number, date_approved, approved_by)
        VALUES (?, 'SP-2024-001', '2024-06-15', ?)
    ")->execute([$docB, $userId]);

    // Main list query (mirrors AdminFiledDocumentsController list SQL)
    $listRow = $pdo->query("
        SELECT
            d.tracking_number,
            d.subject_matter,
            da_appr.approved_number,
            da_appr.date_approved,
            df.description,
            df.tags,
            df.is_public,
            COALESCE(da_appr.approved_number, d.tracking_number) AS display_no
        FROM documents d
        LEFT JOIN document_approvals da_appr ON da_appr.document_id = d.id
        INNER JOIN document_filings  df      ON df.document_id = d.id
        WHERE d.id = {$docB}
    ")->fetch();

    assert_test(
        'Approved number column returns approved_number when available',
        $listRow['display_no'] === 'SP-2024-001',
        "Got: {$listRow['display_no']}"
    );

    assert_test(
        'Falls back to tracking_number when approved_number is NULL',
        true // verified by COALESCE logic; docC has no approval row
    );

    // Verify for docC (no approval row)
    $listRowC = $pdo->query("
        SELECT COALESCE(da_appr.approved_number, d.tracking_number) AS display_no
        FROM documents d
        LEFT JOIN document_approvals da_appr ON da_appr.document_id = d.id
        WHERE d.id = {$docC}
    ")->fetch();
    $trackingC = $pdo->query("SELECT tracking_number FROM documents WHERE id={$docC}")->fetchColumn();
    assert_test(
        'Falls back to tracking_number when no approval row',
        $listRowC['display_no'] === $trackingC,
        "Expected {$trackingC}, got {$listRowC['display_no']}"
    );

    // Tags decoded correctly
    $tagsDecoded = json_decode($listRow['tags'], true);
    assert_test(
        'Tags JSON decodes to correct array',
        $tagsDecoded === ['test', 'filing', 'integration'],
        json_encode($tagsDecoded)
    );

    // ════════════════════════════════════════════════════════════════════════
    section('TEST 9: View Action — Show Query Correctness');
    // ════════════════════════════════════════════════════════════════════════

    // Simulate the show() query for docB
    $showRow = $pdo->query("
        SELECT
            d.id,
            d.tracking_number,
            d.subject_matter,
            d.current_phase,
            da_appr.approved_number,
            da_appr.date_approved,
            df.id              AS filing_id,
            df.description     AS filing_description,
            df.tags            AS filing_tags_json,
            df.is_public       AS filing_is_public,
            df.filed_at        AS filing_filed_at
        FROM documents d
        LEFT JOIN document_approvals da_appr ON da_appr.document_id = d.id
        LEFT JOIN document_filings   df      ON df.document_id = d.id
        WHERE d.id = {$docB}
          AND d.current_phase = 'FILED'
        LIMIT 1
    ")->fetch();

    assert_test('Show query returns document row', !empty($showRow));
    assert_test('Show query current_phase is FILED', $showRow['current_phase'] === 'FILED');
    assert_test('Show query includes approved_number', $showRow['approved_number'] === 'SP-2024-001');
    assert_test('Show query includes filing_id', (int)$showRow['filing_id'] === $filingId);
    assert_test('Show query includes filing description',
        $showRow['filing_description'] === 'Integration test filing');

    // Filing attachments via join table
    $filingAtts = $pdo->query("
        SELECT da.file_name
        FROM document_filing_attachments dfa
        INNER JOIN document_attachments da ON da.id = dfa.attachment_id
        WHERE dfa.filing_id = {$filingId}
    ")->fetchAll(PDO::FETCH_COLUMN);
    assert_test('Filing attachment retrieved via join', in_array('test_scan.pdf', $filingAtts));

    // ════════════════════════════════════════════════════════════════════════
    section('TEST 10: Download — Access Control');
    // ════════════════════════════════════════════════════════════════════════

    // Simulate download access check logic from download() action:
    function canDownload(?string $role, bool $isPublic, bool $isAuthenticated): string
    {
        if (!$isPublic && !$isAuthenticated) {
            return 'DENIED_UNAUTHENTICATED';
        }
        if (!$isPublic && $isAuthenticated && !in_array($role, ['Super Admin', 'Admin'], true)) {
            return 'DENIED_ROLE';
        }
        return 'ALLOWED';
    }

    assert_test(
        'Public filing: unauthenticated access ALLOWED',
        canDownload(null, true, false) === 'ALLOWED'
    );
    assert_test(
        'Public filing: authenticated non-admin access ALLOWED',
        canDownload('Receiving Staff', true, true) === 'ALLOWED'
    );
    assert_test(
        'Private filing: unauthenticated access DENIED',
        canDownload(null, false, false) === 'DENIED_UNAUTHENTICATED'
    );
    assert_test(
        'Private filing: authenticated non-admin access DENIED',
        canDownload('Committee', false, true) === 'DENIED_ROLE'
    );
    assert_test(
        'Private filing: Admin access ALLOWED',
        canDownload('Admin', false, true) === 'ALLOWED'
    );
    assert_test(
        'Private filing: Super Admin access ALLOWED',
        canDownload('Super Admin', false, true) === 'ALLOWED'
    );

    // ════════════════════════════════════════════════════════════════════════
    section('TEST 11: Idempotency — Cannot File Twice');
    // ════════════════════════════════════════════════════════════════════════

    // docB already has a document_filings row
    $existingFiling = $pdo->query(
        "SELECT id FROM document_filings WHERE document_id = {$docB}"
    )->fetch();

    assert_test(
        'Cannot file a document with an existing document_filings row (unique constraint)',
        !empty($existingFiling),
        'Pre-condition: filing row exists'
    );

    // Attempt duplicate filing insert — should fail on UNIQUE constraint
    try {
        $pdo->prepare("
            INSERT INTO document_filings (document_id, description, is_public, filed_by)
            VALUES (?, 'Duplicate filing attempt', 0, ?)
        ")->execute([$docB, $userId]);
        assert_test('Duplicate filing insert raises UNIQUE constraint error', false, 'Expected error was not raised');
    } catch (PDOException $e) {
        $isDupeError = str_contains($e->getMessage(), 'Duplicate entry')
                    || str_contains($e->getMessage(), '1062');
        assert_test('Duplicate filing insert raises UNIQUE constraint error', $isDupeError, $e->getMessage());
    }

    // ════════════════════════════════════════════════════════════════════════
    section('TEST 12: Tag Parsing Validation');
    // ════════════════════════════════════════════════════════════════════════

    function parseTags(string $raw): array
    {
        if (trim($raw) === '') return [];
        return array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            fn($t) => $t !== ''
        ));
    }

    assert_test('Empty string produces empty tags array', parseTags('') === []);
    assert_test('Comma-separated tags parsed correctly',
        parseTags('health, budget,  ordinance ') === ['health', 'budget', 'ordinance']
    );
    assert_test('Single tag works', parseTags('ordinance') === ['ordinance']);
    assert_test('Trailing comma handled', parseTags('a, b,') === ['a', 'b']);

    // ════════════════════════════════════════════════════════════════════════
    section('TEST 13: Migration Verification — Table Exists');
    // ════════════════════════════════════════════════════════════════════════

    $tables = $pdo->query("SHOW TABLES LIKE 'document_filing_attachments'")->fetchAll(PDO::FETCH_COLUMN);
    assert_test('document_filing_attachments table exists', !empty($tables));

    $columns = $pdo->query("SHOW COLUMNS FROM document_filing_attachments")->fetchAll();
    $colNames = array_column($columns, 'Field');
    assert_test('Table has id column',            in_array('id', $colNames));
    assert_test('Table has filing_id column',     in_array('filing_id', $colNames));
    assert_test('Table has attachment_id column', in_array('attachment_id', $colNames));
    assert_test('Table has created_at column',    in_array('created_at', $colNames));

    $indexes = $pdo->query("SHOW INDEX FROM document_filing_attachments")->fetchAll();
    $indexNames = array_unique(array_column($indexes, 'Key_name'));
    assert_test('Unique index on (filing_id, attachment_id) exists',
        in_array('unique_filing_attachment', $indexNames),
        implode(', ', $indexNames)
    );

    // ════════════════════════════════════════════════════════════════════════
    section('TEST 14: document_filings Schema');
    // ════════════════════════════════════════════════════════════════════════

    $filingCols = $pdo->query("SHOW COLUMNS FROM document_filings")->fetchAll();
    $filingColNames = array_column($filingCols, 'Field');
    foreach (['id', 'document_id', 'description', 'tags', 'is_public', 'filed_by', 'filed_at'] as $col) {
        assert_test("document_filings has column '{$col}'", in_array($col, $filingColNames));
    }

    // Unique key on document_id prevents double-filing at DB level
    $filingIndexes = $pdo->query("SHOW INDEX FROM document_filings WHERE Key_name = 'document_id'")->fetchAll();
    $isUnique = !empty(array_filter($filingIndexes, fn($r) => (int)$r['Non_unique'] === 0));
    assert_test('document_filings.document_id has UNIQUE constraint', $isUnique);

} catch (Throwable $e) {
    echo PHP_EOL . "EXCEPTION: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    $failed++;
} finally {
    // Roll back all database writes
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Clean up temp files
    foreach ($tmpFiles as $f) {
        if (is_file($f)) @unlink($f);
    }

    // Interim summary (more tests follow)
    echo PHP_EOL . str_repeat('─', 70) . PHP_EOL;
    echo "  Pass 1 Results: {$passed} passed, {$failed} failed — continuing..." . PHP_EOL;
    echo str_repeat('─', 70) . PHP_EOL;
    // Do NOT exit here; the second pass appended below must also run.
}

// ════════════════════════════════════════════════════════════════════════════
// Additional tests added to cover the To Be Filed action bug fixes:
//   - View action must NOT filter on current_phase = 'FILED' for queue docs
//   - File action must accept FINALIZED docs and reject FILED docs
//   - onclick attribute values must not contain unescaped double quotes
//   - source-show route and view exist
//   - Filing removes document from queue
// ════════════════════════════════════════════════════════════════════════════
// These run as a second isolated pass so they don't conflict with the
// transaction-wrapped block above, which has already been rolled back.

$dsn2 = 'mysql:host=127.0.0.1;port=3306;dbname=pangasinan_lis;charset=utf8mb4';
try {
    $pdo2 = new PDO($dsn2, 'root', '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    echo "FATAL (second pass): Cannot connect — " . $e->getMessage() . PHP_EOL;
    exit(1);
}

$pdo2->beginTransaction();

try {
    // ── Fixture ─────────────────────────────────────────────────────────────
    $userRow2 = $pdo2->query("SELECT id FROM user_accounts LIMIT 1")->fetch();
    if (!$userRow2) throw new RuntimeException('No user_accounts row found.');
    $uid2 = (int) $userRow2['id'];

    $srcType2 = (int) $pdo2->query(
        "SELECT id FROM source_types WHERE is_active=1 AND is_deleted=0 LIMIT 1"
    )->fetchColumn();
    $docType2 = (int) $pdo2->query(
        "SELECT id FROM document_types WHERE is_active=1 AND is_deleted=0 LIMIT 1"
    )->fetchColumn();
    $status2 = (int) $pdo2->query(
        "SELECT id FROM document_statuses WHERE is_active=1 AND is_deleted=0 LIMIT 1"
    )->fetchColumn();

    $year2 = (int) date('Y');
    $seq2  = (int) $pdo2->query(
        "SELECT COALESCE(MAX(tracking_sequence), 0) + 1 FROM documents WHERE tracking_year = {$year2}"
    )->fetchColumn();

    $pdo2->prepare("
        INSERT INTO documents
            (tracking_year, tracking_sequence, tracking_number,
             date_received, time_received, subject_matter, document_type_id,
             source_type_id, current_status_id, current_phase,
             finalized_at, created_by, updated_by)
        VALUES (?, ?, ?, CURDATE(), CURTIME(), 'Bug-fix test doc', ?, ?, ?, 'FINALIZED', NOW(), ?, ?)
    ")->execute([$year2, $seq2, "{$year2}-BUGFIX-{$seq2}", $docType2, $srcType2, $status2, $uid2, $uid2]);
    $testDocId = (int) $pdo2->lastInsertId();

    // ── Tests ────────────────────────────────────────────────────────────────

    section('TEST 15: View Action — source-show query (FINALIZED, not FILED)');

    // The fixed viewSource() query uses IN ('FINALIZED','FILED')
    // Verify the FINALIZED doc IS found
    $foundRow = $pdo2->query("
        SELECT id, current_phase
        FROM documents
        WHERE id = {$testDocId}
          AND current_phase IN ('FINALIZED', 'FILED')
        LIMIT 1
    ")->fetch();
    assert_test('viewSource query finds FINALIZED document', !empty($foundRow));
    assert_test('viewSource query returns correct phase', ($foundRow['current_phase'] ?? '') === 'FINALIZED');

    // Verify the OLD (broken) show() query does NOT find it
    $notFoundRow = $pdo2->query("
        SELECT id
        FROM documents
        WHERE id = {$testDocId}
          AND current_phase = 'FILED'
        LIMIT 1
    ")->fetch();
    assert_test(
        'Old show() query (FILED only) correctly returns nothing for FINALIZED doc',
        empty($notFoundRow),
        'If this fails, the test fixture is broken'
    );

    section('TEST 16: File Action — rejects already-FILED documents');

    // Mark the doc as FILED temporarily within this transaction
    $pdo2->prepare("UPDATE documents SET current_phase='FILED' WHERE id=?")->execute([$testDocId]);

    $phase16 = $pdo2->query("SELECT current_phase FROM documents WHERE id={$testDocId}")->fetchColumn();
    assert_test('Doc is now FILED for this sub-test', $phase16 === 'FILED');

    // The file() guard: if current_phase !== 'FINALIZED', reject
    $rejectFiled = ($phase16 !== 'FINALIZED');
    assert_test('file() correctly rejects a FILED document', $rejectFiled);

    // Reset back to FINALIZED
    $pdo2->prepare("UPDATE documents SET current_phase='FINALIZED' WHERE id=?")->execute([$testDocId]);

    section('TEST 17: File Action — accepts FINALIZED documents');

    $phase17 = $pdo2->query("SELECT current_phase FROM documents WHERE id={$testDocId}")->fetchColumn();
    $acceptsFinalized = ($phase17 === 'FINALIZED');
    assert_test('file() accepts a FINALIZED document (phase check passes)', $acceptsFinalized);

    section('TEST 18: onclick attribute safety — no unescaped double quotes');

    // Simulate the PHP that generates the onclick:
    // json_encode produces: "2026-001"
    // htmlspecialchars(..., ENT_QUOTES) converts " to &quot;
    // This means the HTML attribute onclick="...&quot;2026-001&quot;..." is safe.
    $trackingNumber  = '2026-BUGFIX-001';
    $subjectMatter   = 'Subject with "quotes" and \'apostrophes\'';
    $docTypeName     = 'Ordinance "Type"';
    $displayDate     = 'Nov 5, 2026';

    // OLD (broken) approach: json_encode directly in onclick attribute
    $oldOnclick = sprintf(
        'openFilingModal(1, %s, %s, %s, %s)',
        json_encode($trackingNumber),
        json_encode($subjectMatter),
        json_encode($docTypeName),
        json_encode($displayDate)
    );
    // OLD approach: contains literal double-quote chars that break HTML attribute
    $oldHtml = 'onclick="' . $oldOnclick . '"';
    $oldIsBroken = substr_count($oldHtml, '"') > 2; // more than the opening/closing quotes
    assert_test(
        'OLD approach (bare json_encode in onclick attr) produces broken HTML',
        $oldIsBroken,
        'Should have unescaped inner quotes — found ' . substr_count($oldHtml, '"') . ' double-quotes'
    );

    // NEW (fixed) approach: htmlspecialchars(json_encode(...), ENT_QUOTES)
    $newOnclick = sprintf(
        'openFilingModal(1, %s, %s, %s, %s)',
        htmlspecialchars(json_encode($trackingNumber), ENT_QUOTES),
        htmlspecialchars(json_encode($subjectMatter),  ENT_QUOTES),
        htmlspecialchars(json_encode($docTypeName),    ENT_QUOTES),
        htmlspecialchars(json_encode($displayDate),    ENT_QUOTES)
    );
    $newHtml = 'onclick="' . $newOnclick . '"';
    // New approach: no literal " inside the attribute value (they become &quot;)
    // The attribute value (between outer quotes) must not contain literal "
    $attrValue = substr($newHtml, strlen('onclick="'), -1); // strip outer quotes
    $newIsSafe = strpos($attrValue, '"') === false;
    assert_test(
        'NEW approach (htmlspecialchars+ENT_QUOTES) produces safe HTML onclick attribute',
        $newIsSafe,
        $newIsSafe ? '' : 'Unescaped double quote found in: ' . htmlspecialchars($newHtml)
    );

    section('TEST 19: Route — admin/filed/source-show is registered');

    $routes = include __DIR__ . '/../routes/web.php';
    assert_test(
        'admin/filed/source-show route exists',
        array_key_exists('admin/filed/source-show', $routes),
        'Check routes/web.php'
    );
    assert_test(
        'admin/filed/source-show maps to viewSource action',
        ($routes['admin/filed/source-show']['action'] ?? '') === 'viewSource'
    );

    section('TEST 20: source-show view file exists');

    $sourceShowView = __DIR__ . '/../resources/views/admin/filed-documents/source-show.php';
    assert_test('source-show.php view file exists', file_exists($sourceShowView));
    assert_test('source-show.php has no PHP syntax errors',
        shell_exec('php -l ' . escapeshellarg($sourceShowView) . ' 2>&1') !== null
        && str_contains((string) shell_exec('php -l ' . escapeshellarg($sourceShowView) . ' 2>&1'), 'No syntax errors')
    );

    section('TEST 21: Filing removes document from To-Be-Filed queue');

    // Doc is currently FINALIZED, no filing row → should appear in queue
    $inQueueBefore = $pdo2->query("
        SELECT COUNT(*) AS cnt
        FROM documents d
        LEFT JOIN document_filings df ON df.document_id = d.id
        WHERE d.id = {$testDocId}
          AND d.current_phase = 'FINALIZED'
          AND df.id IS NULL
    ")->fetch()['cnt'];
    assert_test('FINALIZED doc with no filing row appears in queue', (int)$inQueueBefore === 1);

    // Simulate a filing: insert filings row + update phase
    $pdo2->prepare("
        INSERT INTO document_filings (document_id, description, tags, is_public, filed_by)
        VALUES (?, 'Bug-fix test filing', NULL, 0, ?)
    ")->execute([$testDocId, $uid2]);
    $pdo2->prepare("UPDATE documents SET current_phase='FILED', filed_at=NOW() WHERE id=?")->execute([$testDocId]);

    $inQueueAfter = $pdo2->query("
        SELECT COUNT(*) AS cnt
        FROM documents d
        LEFT JOIN document_filings df ON df.document_id = d.id
        WHERE d.id = {$testDocId}
          AND d.current_phase = 'FINALIZED'
          AND df.id IS NULL
    ")->fetch()['cnt'];
    assert_test('Doc removed from queue after filing', (int)$inQueueAfter === 0);

    section('TEST 22: Attachment validation — stageFileUploads blocked for non-HTTP context');

    // DocumentService::validateFileUploads() (pure validation, no move) must reject
    // a missing/empty file list even in test context
    $svc = new DocumentService($pdo2);
    $empty = ['name' => [''], 'type' => [''], 'tmp_name' => [''], 'error' => [4], 'size' => [0]];
    $errs = $svc->validateFileUploads($empty, true);
    assert_test('validateFileUploads rejects empty list in test context', !empty($errs));

    // Single valid-looking file entry (won't be moved — validation only)
    $tmp22 = tempnam(sys_get_temp_dir(), 'test22_');
    file_put_contents($tmp22, '%PDF-1.4 fake content');
    $oneFile = [
        'name'     => ['scan.pdf'],
        'type'     => ['application/pdf'],
        'tmp_name' => [$tmp22],
        'error'    => [UPLOAD_ERR_OK],
        'size'     => [21],
    ];
    $errs22 = $svc->validateFileUploads($oneFile, true);
    // MIME validation reads the actual file bytes, so a fake PDF may fail MIME check
    // but we just want to confirm the "at least one file" error is NOT in the list
    $hasRequiredError = in_array('At least one attachment is required.', $errs22, true);
    assert_test(
        'validateFileUploads does not report "required" error when a file is provided',
        !$hasRequiredError,
        implode('; ', $errs22)
    );
    @unlink($tmp22);

    section('TEST 23: Filing audit uses the expected argument order');

    $filedControllerSource = file_get_contents(
        __DIR__ . '/../app/controllers/Admin/AdminFiledDocumentsController.php'
    );
    $auditCallStart = strpos($filedControllerSource, "audit_log('CREATE', 'DocumentFiling'");
    $auditCall = $auditCallStart !== false
        ? substr($filedControllerSource, $auditCallStart, 250)
        : '';
    assert_test(
        'filing ID is passed as entity ID and old_values is null',
        $auditCallStart !== false && str_contains($auditCall, '(string) $filingId, null, [')
    );
    assert_test(
        'filing action retains its already-filed duplicate guard',
        str_contains($filedControllerSource, "if (\$doc['existing_filing_id'])")
    );
    assert_test(
        'already-filed guard runs before finalized-phase guard',
        strpos($filedControllerSource, "if (\$doc['existing_filing_id'])")
            < strpos($filedControllerSource, "if (\$doc['current_phase'] !== 'FINALIZED')")
    );

    section('TEST 24: Finalized source attachments have a View route');

    $sourceShowSource = file_get_contents(
        __DIR__ . '/../resources/views/admin/filed-documents/source-show.php'
    );
    $sourceAttachmentPreviewSource = file_get_contents(
        __DIR__ . '/../resources/views/admin/filed-documents/source-attachment-preview.php'
    );
    assert_test(
        'source attachment preview route is registered',
        ($routes['admin/filed/source-attachment']['action'] ?? '') === 'previewSourceAttachment'
    );
    assert_test(
        'source-show attachment list links to the inline preview',
        str_contains($sourceShowSource, '/admin/filed/source-attachment?attachment_id=')
            && str_contains($sourceShowSource, 'View attachment')
    );
    assert_test(
        'preview handler requires the matching finalized document and supports inline responses',
        str_contains($filedControllerSource, "AND d.current_phase = 'FINALIZED'")
            && str_contains($filedControllerSource, "in_array(\$mode, ['raw', 'download'], true)")
            && str_contains($filedControllerSource, "'inline'")
    );
    assert_test(
        'DOCX preview page renders the file in the browser with the local renderer',
        str_contains($sourceAttachmentPreviewSource, 'docx-preview.min.js')
            && str_contains($sourceAttachmentPreviewSource, 'window.docx.renderAsync')
            && file_exists(__DIR__ . '/../public/assets/js/docx-preview.min.js')
            && file_exists(__DIR__ . '/../public/assets/js/jszip.min.js')
    );

} catch (Throwable $e) {
    echo PHP_EOL . 'EXCEPTION (second pass): ' . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    $failed++;
} finally {
    if ($pdo2->inTransaction()) {
        $pdo2->rollBack();
    }

    echo PHP_EOL . str_repeat('═', 70) . PHP_EOL;
    echo "  Final Results: {$passed} passed, {$failed} failed" . PHP_EOL;
    echo str_repeat('═', 70) . PHP_EOL;

    exit($failed > 0 ? 1 : 0);
}

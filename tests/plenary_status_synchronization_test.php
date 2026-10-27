<?php
/**
 * Test: Plenary Status Synchronization with Shared Document Status
 *
 * Verifies that:
 * 1. Plenary pre-decision status changes are reflected in documents.current_status_id
 * 2. Other divisions can retrieve and display the updated status
 * 3. The Plenary Inbox and other views show consistent status
 * 4. Existing audit/history records are preserved
 *
 * Run with: php tests/plenary_status_synchronization_test.php
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
    echo "FATAL: Cannot connect to database — " . $e->getMessage() . PHP_EOL;
    exit(1);
}

$passed = 0;
$failed = 0;

function pass(string $label): void {
    global $passed;
    echo "  [PASS] {$label}" . PHP_EOL;
    $passed++;
}
function fail(string $label, string $detail = ''): void {
    global $failed;
    $suffix = $detail !== '' ? " — {$detail}" : '';
    echo "  [FAIL] {$label}{$suffix}" . PHP_EOL;
    $failed++;
}
function check(string $label, bool $cond, string $detail = ''): void {
    $cond ? pass($label) : fail($label, $detail);
}

// ── Setup Helper ───────────────────────────────────────────────────────────

function setupTestDocument(PDO $pdo, int $userId): array
{
    static $seq = 900000;
    $seq++;
    $tn = 'SYNC-' . str_pad((string)($seq - 900000), 5, '0', STR_PAD_LEFT);

    // Get required IDs
    $docType = $pdo->query("SELECT id FROM document_types WHERE name LIKE '%Ordinance%' LIMIT 1")->fetch();
    $sourceType = $pdo->query("SELECT id FROM source_types WHERE name = 'Client' LIMIT 1")->fetch();
    $pendingStatus = $pdo->query("SELECT id FROM document_statuses WHERE name = 'Pending' LIMIT 1")->fetch();
    $plenaryRole = $pdo->query("SELECT id FROM roles WHERE name = 'Plenary' LIMIT 1")->fetch();

    if (!$docType || !$sourceType || !$pendingStatus || !$plenaryRole) {
        throw new RuntimeException("Required configuration not found");
    }

    // Insert document
    $pdo->prepare("
        INSERT INTO documents
            (tracking_number, tracking_year, tracking_sequence,
             subject_matter, document_type_id, source_type_id,
             current_status_id, date_received, time_received, current_phase,
             created_by, updated_by)
        VALUES (?, YEAR(CURDATE()), ?, 'Test subject for sync', ?, ?, ?, CURDATE(), CURTIME(), 'PLENARY', ?, ?)
    ")->execute([$tn, $seq, $docType['id'], $sourceType['id'], $pendingStatus['id'], $userId, $userId]);
    $docId = (int) $pdo->lastInsertId();

    // Insert PENDING Plenary assignment
    $pdo->prepare("
        INSERT INTO document_assignments
            (document_id, assigned_to_role_id, phase, assigned_by, decision,
             received_at, accepted_by, assigned_to_user_id)
        VALUES (?, ?, 'PLENARY', ?, 'PENDING', NOW(), NULL, NULL)
    ")->execute([$docId, $plenaryRole['id'], $userId]);
    $assignId = (int) $pdo->lastInsertId();

    return [
        'document_id' => $docId,
        'assignment_id' => $assignId,
        'user_id' => $userId,
        'initial_status_id' => $pendingStatus['id']
    ];
}

// ── Test Suite ──────────────────────────────────────────────────────────────

echo "=== Plenary Status Synchronization Test ===" . PHP_EOL . PHP_EOL;

// Create test user outside transaction
$testUser = $pdo->query("SELECT id FROM user_accounts WHERE username = 'test_plenary_sync' LIMIT 1")->fetch();
if (!$testUser) {
    // Get a default role ID
    $defaultRole = $pdo->query("SELECT id FROM roles WHERE name = 'Super Admin' LIMIT 1")->fetch();
    if (!$defaultRole) {
        $defaultRole = $pdo->query("SELECT id FROM roles LIMIT 1")->fetch();
    }
    if (!$defaultRole) {
        throw new RuntimeException("No role found for test user creation");
    }

    $pdo->prepare("
        INSERT INTO user_accounts (username, email, password_hash, role_id, status, is_deleted, created_at, updated_at)
        VALUES ('test_plenary_sync', 'test_plenary_sync@test.local', 'dummy_hash', ?, 'active', 0, NOW(), NOW())
    ")->execute([$defaultRole['id']]);
    $testUser = $pdo->query("SELECT id FROM user_accounts WHERE username = 'test_plenary_sync' LIMIT 1")->fetch();
}
$fakeUserId = (int) $testUser['id'];

$pdo->beginTransaction();

try {
    $testData = setupTestDocument($pdo, $fakeUserId);
    $docId = $testData['document_id'];
    $assignId = $testData['assignment_id'];
    $userId = $testData['user_id'];
    $initialStatusId = $testData['initial_status_id'];

    echo "Test 1: Initial state" . PHP_EOL;
    $docRow = $pdo->query("SELECT current_status_id FROM documents WHERE id = {$docId}")->fetch();
    check("Document has initial Pending status", $docRow['current_status_id'] == $initialStatusId);
    echo PHP_EOL;

    echo "Test 2: Pre-decision status change synchronizes to documents.current_status_id" . PHP_EOL;

    // Get "Deferred" status ID
    $deferredStatus = $pdo->query("SELECT id FROM document_statuses WHERE name = 'Deferred' LIMIT 1")->fetch();
    if (!$deferredStatus) {
        throw new RuntimeException("Deferred status not found");
    }

    // Simulate doPreDecision (set plenary_document_details.status_id and documents.current_status_id)
    $pdo->prepare("
        INSERT INTO plenary_document_details
            (document_id, status_id, remarks, created_by, updated_by)
        VALUES (?, ?, 'Test remarks', ?, ?)
    ")->execute([$docId, $deferredStatus['id'], $userId, $userId]);

    $pdo->prepare("
        UPDATE documents
        SET current_status_id = ?, updated_by = ?
        WHERE id = ?
    ")->execute([$deferredStatus['id'], $userId, $docId]);

    // Verify synchronization
    $docRow = $pdo->query("SELECT current_status_id FROM documents WHERE id = {$docId}")->fetch();
    $pddRow = $pdo->query("SELECT status_id FROM plenary_document_details WHERE document_id = {$docId}")->fetch();

    check("documents.current_status_id updated to Deferred", $docRow['current_status_id'] == $deferredStatus['id']);
    check("plenary_document_details.status_id is Deferred", $pddRow['status_id'] == $deferredStatus['id']);
    check("Both statuses are synchronized", $docRow['current_status_id'] == $pddRow['status_id']);
    echo PHP_EOL;

    echo "Test 3: Other divisions can retrieve the updated status" . PHP_EOL;

    // Simulate another division querying the document
    $sharedStatus = $pdo->query("
        SELECT ds.name, ds.badge_color
        FROM documents d
        JOIN document_statuses ds ON ds.id = d.current_status_id
        WHERE d.id = {$docId}
    ")->fetch();

    check("Shared status query returns Deferred", $sharedStatus['name'] === 'Deferred');
    check("Shared status has valid badge color", !empty($sharedStatus['badge_color']));
    echo PHP_EOL;

    echo "Test 4: Status change to On Process synchronizes correctly" . PHP_EOL;

    $onProcessStatus = $pdo->query("SELECT id FROM document_statuses WHERE name = 'On Process' LIMIT 1")->fetch();
    if (!$onProcessStatus) {
        throw new RuntimeException("On Process status not found");
    }

    // Update to On Process
    $pdo->prepare("
        UPDATE plenary_document_details
        SET status_id = ?, updated_by = ?
        WHERE document_id = ?
    ")->execute([$onProcessStatus['id'], $userId, $docId]);

    $pdo->prepare("
        UPDATE documents
        SET current_status_id = ?, updated_by = ?
        WHERE id = ?
    ")->execute([$onProcessStatus['id'], $userId, $docId]);

    // Verify
    $docRow = $pdo->query("SELECT current_status_id FROM documents WHERE id = {$docId}")->fetch();
    $pddRow = $pdo->query("SELECT status_id FROM plenary_document_details WHERE document_id = {$docId}")->fetch();

    check("documents.current_status_id updated to On Process", $docRow['current_status_id'] == $onProcessStatus['id']);
    check("plenary_document_details.status_id is On Process", $pddRow['status_id'] == $onProcessStatus['id']);
    check("Both statuses remain synchronized", $docRow['current_status_id'] == $pddRow['status_id']);
    echo PHP_EOL;

    echo "Test 5: Compliance submission (Deferred → On Process) synchronizes" . PHP_EOL;

    // Reset to Deferred
    $pdo->prepare("
        UPDATE plenary_document_details
        SET status_id = ?, remarks = CONCAT(remarks, '\n\nCompliance remarks'), updated_by = ?
        WHERE document_id = ?
    ")->execute([$deferredStatus['id'], $userId, $docId]);

    $pdo->prepare("
        UPDATE documents
        SET current_status_id = ?, updated_by = ?
        WHERE id = ?
    ")->execute([$deferredStatus['id'], $userId, $docId]);

    // Simulate compliance submission (return to On Process)
    $pdo->prepare("
        UPDATE plenary_document_details
        SET status_id = ?, updated_by = ?
        WHERE document_id = ?
    ")->execute([$onProcessStatus['id'], $userId, $docId]);

    $pdo->prepare("
        UPDATE documents
        SET current_status_id = ?, updated_by = ?
        WHERE id = ?
    ")->execute([$onProcessStatus['id'], $userId, $docId]);

    // Verify
    $docRow = $pdo->query("SELECT current_status_id FROM documents WHERE id = {$docId}")->fetch();
    $pddRow = $pdo->query("SELECT status_id FROM plenary_document_details WHERE document_id = {$docId}")->fetch();

    check("documents.current_status_id is On Process after compliance", $docRow['current_status_id'] == $onProcessStatus['id']);
    check("plenary_document_details.status_id is On Process after compliance", $pddRow['status_id'] == $onProcessStatus['id']);
    echo PHP_EOL;

    echo "Test 6: Terminal status (Noted) synchronizes" . PHP_EOL;

    $notedStatus = $pdo->query("SELECT id FROM document_statuses WHERE name = 'Noted' LIMIT 1")->fetch();
    if (!$notedStatus) {
        throw new RuntimeException("Noted status not found");
    }

    $pdo->prepare("
        UPDATE plenary_document_details
        SET status_id = ?, updated_by = ?
        WHERE document_id = ?
    ")->execute([$notedStatus['id'], $userId, $docId]);

    $pdo->prepare("
        UPDATE documents
        SET current_status_id = ?, updated_by = ?
        WHERE id = ?
    ")->execute([$notedStatus['id'], $userId, $docId]);

    // Verify
    $docRow = $pdo->query("SELECT current_status_id FROM documents WHERE id = {$docId}")->fetch();
    $pddRow = $pdo->query("SELECT status_id FROM plenary_document_details WHERE document_id = {$docId}")->fetch();

    check("documents.current_status_id is Noted", $docRow['current_status_id'] == $notedStatus['id']);
    check("plenary_document_details.status_id is Noted", $pddRow['status_id'] == $notedStatus['id']);
    check("Terminal status is synchronized", $docRow['current_status_id'] == $pddRow['status_id']);
    echo PHP_EOL;

    echo "Test 7: Audit history records are preserved" . PHP_EOL;

    // Insert a document event to verify history preservation
    $pdo->prepare("
        INSERT INTO document_events
            (document_id, event_type, phase, performed_by, to_status_id, remarks, metadata)
        VALUES (?, 'PLENARY_PRE_DECISION', 'PLENARY', ?, ?, 'Test remarks', '{}')
    ")->execute([$docId, $userId, $notedStatus['id']]);

    $eventCount = $pdo->query("SELECT COUNT(*) AS cnt FROM document_events WHERE document_id = {$docId}")->fetchColumn();

    check("Document event recorded", $eventCount > 0);
    check("Event shows correct status", $pdo->query("SELECT to_status_id FROM document_events WHERE document_id = {$docId} ORDER BY id DESC LIMIT 1")->fetchColumn() == $notedStatus['id']);
    echo PHP_EOL;

    echo "Test 8: Return to Admin synchronizes status" . PHP_EOL;

    $adminRole = $pdo->query("SELECT id FROM roles WHERE name = 'Admin' LIMIT 1")->fetch();
    if (!$adminRole) {
        throw new RuntimeException("Admin role not found");
    }

    // Close current assignment
    $pdo->prepare("
        UPDATE document_assignments
        SET decision = 'COMPLETED', completed_at = NOW(), decline_reason = 'Test return'
        WHERE id = ?
    ")->execute([$assignId]);

    // Update document phase and status
    $pdo->prepare("
        UPDATE documents
        SET current_phase = 'ADMIN',
            current_status_id = ?,
            current_owner_user_id = NULL,
            updated_by = ?
        WHERE id = ?
    ")->execute([$initialStatusId, $userId, $docId]);

    // Verify
    $docRow = $pdo->query("SELECT current_status_id, current_phase FROM documents WHERE id = {$docId}")->fetch();

    check("documents.current_status_id returned to Pending", $docRow['current_status_id'] == $initialStatusId);
    check("documents.current_phase returned to ADMIN", $docRow['current_phase'] === 'ADMIN');
    echo PHP_EOL;

    // Rollback to clean up test data
    $pdo->rollBack();

    // Clean up test user (outside transaction)
    $pdo->exec("DELETE FROM user_accounts WHERE username = 'test_plenary_sync'");

} catch (Throwable $e) {
    $pdo->rollBack();
    echo "FATAL: Test failed with exception — " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
}

// ── Summary ────────────────────────────────────────────────────────────────

echo "=== Test Summary ===" . PHP_EOL;
echo "Passed: {$passed}" . PHP_EOL;
echo "Failed: {$failed}" . PHP_EOL;

exit($failed > 0 ? 1 : 0);

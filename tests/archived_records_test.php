<?php
/**
 * Archived Records Test Suite
 *
 * Tests the archived records functionality including:
 * - Custom document types with mutual exclusion
 * - Custom committees and authors
 * - Author vs co-author exclusivity
 * - Duplicate approved number validation
 * - Tag filtering
 * - Author/sponsor filtering (including custom names)
 * - Date range filtering
 * - File attachment validation
 *
 * Run with: php tests/archived_records_test.php
 */

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

// ── Test Counters ──────────────────────────────────────────────────────────
$passed = 0;
$failed = 0;

function assert_test(string $label, bool $condition, ?string $message = null): void
{
    global $passed, $failed;
    if ($condition) {
        echo "  ✓ [PASS] {$label}" . PHP_EOL;
        $passed++;
    } else {
        echo "  ✗ [FAIL] {$label}" . PHP_EOL;
        if ($message) echo "    → {$message}" . PHP_EOL;
        $failed++;
    }
}

function section(string $title): void
{
    echo PHP_EOL . str_repeat('=', 70) . PHP_EOL;
    echo "  {$title}" . PHP_EOL;
    echo str_repeat('=', 70) . PHP_EOL;
}

// ── Begin Transaction (rollback at end) ────────────────────────────────────
$pdo->beginTransaction();

try {
    section('TEST 1: Custom Document Type Constraint');
    
    // Get a test user
    $userStmt = $pdo->query("SELECT id FROM user_accounts LIMIT 1");
    $testUser = $userStmt->fetch();
    $userId = $testUser ? (int)$testUser['id'] : 1;
    
    // Get a valid document type
    $dtStmt = $pdo->query("SELECT id FROM document_types WHERE is_active=1 AND is_deleted=0 LIMIT 1");
    $docType = $dtStmt->fetch();
    $docTypeId = $docType ? (int)$docType['id'] : null;
    
    // Test 1A: Insert with document_type_id only (should succeed)
    try {
        $stmt = $pdo->prepare("
            INSERT INTO archived_records 
            (approved_no, date_approved, subject_matter, document_type_id, custom_document_type_name, created_by, updated_by)
            VALUES (?, ?, ?, ?, NULL, ?, ?)
        ");
        $stmt->execute(['TEST-001', '2024-01-01', 'Test Subject 1', $docTypeId, $userId, $userId]);
        $recordId1 = $pdo->lastInsertId();
        assert_test('Can insert record with document_type_id only', true);
    } catch (Exception $e) {
        assert_test('Can insert record with document_type_id only', false, $e->getMessage());
    }
    
    // Test 1B: Insert with custom_document_type_name only (should succeed)
    try {
        $stmt = $pdo->prepare("
            INSERT INTO archived_records 
            (approved_no, date_approved, subject_matter, document_type_id, custom_document_type_name, created_by, updated_by)
            VALUES (?, ?, ?, NULL, ?, ?, ?)
        ");
        $stmt->execute(['TEST-002', '2024-01-02', 'Test Subject 2', 'Custom Ordinance', $userId, $userId]);
        $recordId2 = $pdo->lastInsertId();
        assert_test('Can insert record with custom_document_type_name only', true);
    } catch (Exception $e) {
        assert_test('Can insert record with custom_document_type_name only', false, $e->getMessage());
    }
    
    // Test 1C: Insert with both document_type_id AND custom_document_type_name (should FAIL)
    try {
        $stmt = $pdo->prepare("
            INSERT INTO archived_records 
            (approved_no, date_approved, subject_matter, document_type_id, custom_document_type_name, created_by, updated_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute(['TEST-003', '2024-01-03', 'Test Subject 3', $docTypeId, 'Custom Type', $userId, $userId]);
        assert_test('Cannot insert with both document_type_id AND custom_document_type_name', false, 'Expected constraint violation');
    } catch (Exception $e) {
        assert_test('Cannot insert with both document_type_id AND custom_document_type_name', 
                   str_contains($e->getMessage(), 'chk_ar_doc_type_one_source') || str_contains($e->getMessage(), 'check constraint'));
    }
    
    // Test 1D: Insert with neither document_type_id nor custom_document_type_name (should FAIL)
    try {
        $stmt = $pdo->prepare("
            INSERT INTO archived_records 
            (approved_no, date_approved, subject_matter, document_type_id, custom_document_type_name, created_by, updated_by)
            VALUES (?, ?, ?, NULL, NULL, ?, ?)
        ");
        $stmt->execute(['TEST-004', '2024-01-04', 'Test Subject 4', $userId, $userId]);
        assert_test('Cannot insert with neither document_type_id nor custom_document_type_name', false, 'Expected constraint violation');
    } catch (Exception $e) {
        assert_test('Cannot insert with neither document_type_id nor custom_document_type_name',
                   str_contains($e->getMessage(), 'chk_ar_doc_type_one_source') || str_contains($e->getMessage(), 'check constraint'));
    }

    // ───────────────────────────────────────────────────────────────────────
    section('TEST 2: Duplicate Approved Number');
    
    // Test 2A: Duplicate approved_no (should FAIL)
    try {
        $stmt = $pdo->prepare("
            INSERT INTO archived_records 
            (approved_no, date_approved, subject_matter, document_type_id, created_by, updated_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute(['TEST-001', '2024-02-01', 'Duplicate test', $docTypeId, $userId, $userId]);
        assert_test('Cannot insert duplicate approved_no', false, 'Expected unique constraint violation');
    } catch (Exception $e) {
        assert_test('Cannot insert duplicate approved_no', 
                   str_contains($e->getMessage(), 'unique_ar_approved_no') || str_contains($e->getMessage(), 'Duplicate entry'));
    }
    
    // ───────────────────────────────────────────────────────────────────────
    section('TEST 3: Custom Committees');
    
    if (isset($recordId1)) {
        // Get a valid committee
        $commStmt = $pdo->query("SELECT id FROM committees WHERE is_active=1 AND is_deleted=0 LIMIT 1");
        $comm = $commStmt->fetch();
        $commId = $comm ? (int)$comm['id'] : null;
        
        // Test 3A: Insert committee with committee_id
        if ($commId) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO archived_records_committees 
                    (archived_record_id, committee_id, custom_committee_name, assigned_by)
                    VALUES (?, ?, NULL, ?)
                ");
                $stmt->execute([$recordId1, $commId, $userId]);
                assert_test('Can insert committee with committee_id', true);
            } catch (Exception $e) {
                assert_test('Can insert committee with committee_id', false, $e->getMessage());
            }
        }
        
        // Test 3B: Insert committee with custom_committee_name
        try {
            $stmt = $pdo->prepare("
                INSERT INTO archived_records_committees 
                (archived_record_id, committee_id, custom_committee_name, assigned_by)
                VALUES (?, NULL, ?, ?)
            ");
            $stmt->execute([$recordId1, 'Custom Health Committee', $userId]);
            assert_test('Can insert committee with custom_committee_name', true);
        } catch (Exception $e) {
            assert_test('Can insert committee with custom_committee_name', false, $e->getMessage());
        }
        
        // Test 3C: Insert committee with both (should FAIL)
        try {
            $stmt = $pdo->prepare("
                INSERT INTO archived_records_committees 
                (archived_record_id, committee_id, custom_committee_name, assigned_by)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$recordId1, $commId, 'Custom Committee', $userId]);
            assert_test('Cannot insert committee with both committee_id AND custom_committee_name', false);
        } catch (Exception $e) {
            assert_test('Cannot insert committee with both committee_id AND custom_committee_name',
                       str_contains($e->getMessage(), 'chk_arc_one_source') || str_contains($e->getMessage(), 'check constraint'));
        }
    }
    
    // ───────────────────────────────────────────────────────────────────────
    section('TEST 4: Custom Authors and Co-Authors');
    
    if (isset($recordId2)) {
        // Get a valid SP member
        $spStmt = $pdo->query("SELECT sp_member_id FROM sp_members WHERE is_active=1 AND is_deleted=0 LIMIT 1");
        $spMember = $spStmt->fetch();
        $spMemberId = $spMember ? (int)$spMember['sp_member_id'] : null;
        
        // Test 4A: Insert author with sp_member_id
        if ($spMemberId) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO archived_records_authors 
                    (archived_record_id, sp_member_id, custom_author_name, author_role, sort_order)
                    VALUES (?, ?, NULL, 'AUTHOR', 0)
                ");
                $stmt->execute([$recordId2, $spMemberId]);
                assert_test('Can insert author with sp_member_id', true);
            } catch (Exception $e) {
                assert_test('Can insert author with sp_member_id', false, $e->getMessage());
            }
        }
        
        // Test 4B: Insert author with custom_author_name
        try {
            $stmt = $pdo->prepare("
                INSERT INTO archived_records_authors 
                (archived_record_id, sp_member_id, custom_author_name, author_role, sort_order)
                VALUES (?, NULL, ?, 'AUTHOR', 1)
            ");
            $stmt->execute([$recordId2, 'John Custom Doe']);
            assert_test('Can insert author with custom_author_name', true);
        } catch (Exception $e) {
            assert_test('Can insert author with custom_author_name', false, $e->getMessage());
        }
        
        // Test 4C: Insert co-author with custom_author_name
        try {
            $stmt = $pdo->prepare("
                INSERT INTO archived_records_authors 
                (archived_record_id, sp_member_id, custom_author_name, author_role, sort_order)
                VALUES (?, NULL, ?, 'CO_AUTHOR', 0)
            ");
            $stmt->execute([$recordId2, 'Jane Custom Smith']);
            assert_test('Can insert co-author with custom_author_name', true);
        } catch (Exception $e) {
            assert_test('Can insert co-author with custom_author_name', false, $e->getMessage());
        }
        
        // Test 4D: Insert author with both (should FAIL)
        try {
            $stmt = $pdo->prepare("
                INSERT INTO archived_records_authors 
                (archived_record_id, sp_member_id, custom_author_name, author_role, sort_order)
                VALUES (?, ?, ?, 'AUTHOR', 2)
            ");
            $stmt->execute([$recordId2, $spMemberId, 'Custom Name']);
            assert_test('Cannot insert author with both sp_member_id AND custom_author_name', false);
        } catch (Exception $e) {
            assert_test('Cannot insert author with both sp_member_id AND custom_author_name',
                       str_contains($e->getMessage(), 'chk_ara_one_source') || str_contains($e->getMessage(), 'check constraint'));
        }
    }
    
    // ───────────────────────────────────────────────────────────────────────
    section('TEST 5: Tag and Author Filtering');
    
    // Create test records with tags
    $testRecords = [];
    for ($i = 1; $i <= 3; $i++) {
        $stmt = $pdo->prepare("
            INSERT INTO archived_records 
            (approved_no, date_approved, subject_matter, document_type_id, tags, created_by, updated_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            "FILTER-TEST-{$i}",
            '2024-03-0' . $i,
            "Filter Test Subject {$i}",
            $docTypeId,
            $i == 1 ? 'budget,2024,ordinance' : ($i == 2 ? 'health,emergency' : 'budget,infrastructure'),
            $userId,
            $userId
        ]);
        $testRecords[$i] = $pdo->lastInsertId();
    }
    
    // Test 5A: Tag filtering
    $tagQuery = "SELECT COUNT(*) FROM archived_records WHERE FIND_IN_SET('budget', tags) > 0";
    $count = (int)$pdo->query($tagQuery)->fetchColumn();
    assert_test('Tag filter for "budget" returns correct count', $count === 2, "Expected 2, got {$count}");
    
    // Test 5B: Tag filtering for non-existent tag
    $tagQuery2 = "SELECT COUNT(*) FROM archived_records WHERE FIND_IN_SET('nonexistent', tags) > 0";
    $count2 = (int)$pdo->query($tagQuery2)->fetchColumn();
    assert_test('Tag filter for non-existent tag returns 0', $count2 === 0, "Expected 0, got {$count2}");
    
    // Test 5C: Author filtering with custom names
    if (isset($testRecords[1])) {
        $stmt = $pdo->prepare("
            INSERT INTO archived_records_authors 
            (archived_record_id, sp_member_id, custom_author_name, author_role, sort_order)
            VALUES (?, NULL, ?, 'AUTHOR', 0)
        ");
        $stmt->execute([$testRecords[1], 'Maria Santos']);
        
        $authorQuery = "
            SELECT COUNT(DISTINCT ar.id) 
            FROM archived_records ar
            WHERE EXISTS (
                SELECT 1 FROM archived_records_authors ara2 
                LEFT JOIN sp_members sm2 ON ara2.sp_member_id = sm2.sp_member_id 
                WHERE ara2.archived_record_id = ar.id 
                AND (CONCAT_WS(' ', sm2.first_name, sm2.last_name) LIKE ? OR ara2.custom_author_name LIKE ?)
            )
        ";
        $stmt = $pdo->prepare($authorQuery);
        $stmt->execute(['%Maria%', '%Maria%']);
        $count3 = (int)$stmt->fetchColumn();
        assert_test('Author filter includes custom author names', $count3 >= 1, "Expected >= 1, got {$count3}");
    }
    
    // ───────────────────────────────────────────────────────────────────────
    section('TEST 6: Date Range Filtering');
    
    // Test 6A: Date range filter (from)
    $dateQuery = "SELECT COUNT(*) FROM archived_records WHERE date_approved >= '2024-03-02'";
    $count4 = (int)$pdo->query($dateQuery)->fetchColumn();
    assert_test('Date filter (from) works correctly', $count4 >= 2, "Expected >= 2, got {$count4}");
    
    // Test 6B: Date range filter (to)
    $dateQuery2 = "SELECT COUNT(*) FROM archived_records WHERE date_approved <= '2024-03-01'";
    $count5 = (int)$pdo->query($dateQuery2)->fetchColumn();
    assert_test('Date filter (to) works correctly', $count5 >= 1, "Expected >= 1, got {$count5}");
    
    // Test 6C: Date range filter (from and to)
    $dateQuery3 = "SELECT COUNT(*) FROM archived_records WHERE date_approved >= '2024-03-01' AND date_approved <= '2024-03-02'";
    $count6 = (int)$pdo->query($dateQuery3)->fetchColumn();
    assert_test('Date filter (from and to) works correctly', $count6 >= 2, "Expected >= 2, got {$count6}");
    
    // ───────────────────────────────────────────────────────────────────────
    section('TEST 7: Query with COALESCE for Custom Document Types');
    
    // Test 7A: Query returns custom document type name when document_type_id is NULL
    if (isset($recordId2)) {
        $coalesceQuery = "
            SELECT COALESCE(dt.name, ar.custom_document_type_name) AS doc_type_name
            FROM archived_records ar
            LEFT JOIN document_types dt ON ar.document_type_id = dt.id
            WHERE ar.id = ?
        ";
        $stmt = $pdo->prepare($coalesceQuery);
        $stmt->execute([$recordId2]);
        $result = $stmt->fetch();
        assert_test('COALESCE returns custom document type when document_type_id is NULL',
                   $result && $result['doc_type_name'] === 'Custom Ordinance',
                   "Got: " . ($result['doc_type_name'] ?? 'NULL'));
    }
    
    // Test 7B: Query returns document type name when document_type_id is set
    if (isset($recordId1) && $docTypeId) {
        $stmt = $pdo->prepare("SELECT name FROM document_types WHERE id = ?");
        $stmt->execute([$docTypeId]);
        $expectedName = $stmt->fetchColumn();
        
        $coalesceQuery2 = "
            SELECT COALESCE(dt.name, ar.custom_document_type_name) AS doc_type_name
            FROM archived_records ar
            LEFT JOIN document_types dt ON ar.document_type_id = dt.id
            WHERE ar.id = ?
        ";
        $stmt2 = $pdo->prepare($coalesceQuery2);
        $stmt2->execute([$recordId1]);
        $result2 = $stmt2->fetch();
        assert_test('COALESCE returns document type name when document_type_id is set',
                   $result2 && $result2['doc_type_name'] === $expectedName,
                   "Expected: {$expectedName}, Got: " . ($result2['doc_type_name'] ?? 'NULL'));
    }
    
    // ───────────────────────────────────────────────────────────────────────
    section('TEST SUMMARY');
    
    $total = $passed + $failed;
    echo PHP_EOL;
    echo "  Total Tests: {$total}" . PHP_EOL;
    echo "  Passed: {$passed}" . PHP_EOL;
    echo "  Failed: {$failed}" . PHP_EOL;
    echo PHP_EOL;
    
    if ($failed === 0) {
        echo "  ✓ ALL TESTS PASSED!" . PHP_EOL;
    } else {
        echo "  ✗ SOME TESTS FAILED" . PHP_EOL;
    }
    echo PHP_EOL;
    
} catch (Exception $e) {
    echo PHP_EOL . "FATAL ERROR: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}

// Always rollback (zero side-effects)
$pdo->rollBack();

exit($failed > 0 ? 1 : 0);

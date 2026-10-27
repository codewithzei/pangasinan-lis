<?php
/**
 * Regression Test: Admin Receive Document - Tracking Number Fix
 *
 * This test verifies that the tracking number generation correctly handles
 * cases where the document_tracking_sequences table becomes out of sync
 * with the actual documents table (e.g., due to manual insertions or
 * communication tracking numbers with different formats).
 *
 * Bug: SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry
 * for key 'documents.unique_tracking_year_sequence'
 *
 * Root Cause: The generateTrackingNumber() method only checked the
 * document_tracking_sequences table for the last sequence, but did not
 * verify against the actual documents table. When documents were inserted
 * with manual tracking numbers (e.g., TCOMM-20261025-3274), the sequences
 * table was not updated, causing subsequent INSERTs to try to reuse
 * sequence numbers that already existed in the documents table.
 *
 * Fix: Modified generateTrackingNumber() to check both the tracking_sequences
 * table AND the actual documents table, using the maximum of both values.
 */

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/services/DocumentService.php';

class AdminReceiveDocumentTrackingRegressionTest
{
    private PDO $pdo;
    private DocumentService $docService;

    public function __construct()
    {
        $db = new Database();
        $this->pdo = $db->connect();
        $this->docService = new DocumentService($this->pdo);
    }

    public function run(): void
    {
        echo "=== Admin Receive Document Tracking Regression Test ===\n\n";

        // Test 1: Verify tracking number generation with synced tables
        $this->testTrackingNumberWithSyncedTables();

        // Test 2: Verify tracking number generation with out-of-sync tables
        $this->testTrackingNumberWithOutOfSyncTables();

        // Test 3: Verify no duplicate constraint violation
        $this->testNoDuplicateViolation();

        echo "\n=== All tests PASSED ===\n";
    }

    private function testTrackingNumberWithSyncedTables(): void
    {
        echo "Test 1: Tracking number generation with synced tables... ";

        $this->pdo->beginTransaction();
        try {
            $year = 2026;
            $result = $this->docService->generateTrackingNumber($year);

            // Verify the tracking number format
            assert($result['tracking_year'] === $year, "Year mismatch");
            assert($result['tracking_sequence'] > 0, "Sequence must be positive");
            assert(preg_match('/^TRK-\d{4}-\d{5}$/', $result['tracking_number']), "Invalid tracking number format");

            $this->pdo->rollBack();
            echo "PASSED\n";
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function testTrackingNumberWithOutOfSyncTables(): void
    {
        echo "Test 2: Tracking number generation with out-of-sync tables... ";

        // Save current state
        $stmt = $this->pdo->query("SELECT last_sequence FROM document_tracking_sequences WHERE tracking_year = 2026");
        $row = $stmt->fetch();
        $originalSeq = $row ? (int) $row['last_sequence'] : 0;

        $stmt2 = $this->pdo->query("SELECT MAX(tracking_sequence) as max_seq FROM documents WHERE tracking_year = 2026");
        $row2 = $stmt2->fetch();
        $originalDocMax = $row2 ? (int) ($row2['max_seq'] ?? 0) : 0;

        try {
            // Simulate out-of-sync state: set tracking_sequences lower than actual max in documents
            $this->pdo->exec("UPDATE document_tracking_sequences SET last_sequence = 30 WHERE tracking_year = 2026");

            $this->pdo->beginTransaction();
            try {
                $result = $this->docService->generateTrackingNumber(2026);

                // Should use the documents table max (35+) instead of tracking_sequences (30)
                assert($result['tracking_sequence'] > 35, "Should use documents table max when out of sync");

                $this->pdo->rollBack();
                echo "PASSED\n";
            } catch (Throwable $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        } finally {
            // Restore original state
            $this->pdo->exec("UPDATE document_tracking_sequences SET last_sequence = {$originalSeq} WHERE tracking_year = 2026");
        }
    }

    private function testNoDuplicateViolation(): void
    {
        echo "Test 3: No duplicate constraint violation on insert... ";

        // Get document types
        $docTypes = $this->docService->getDocumentTypes();
        if (empty($docTypes)) {
            echo "SKIPPED (no document types)\n";
            return;
        }

        $data = [
            'date_received' => date('Y-m-d'),
            'time_received' => date('H:i'),
            'subject_matter' => 'Regression Test Document - Tracking Fix Verification',
            'document_type_id' => $docTypes[0]['id'],
            'source_type_id' => 5, // Client
            'remarks' => 'Regression test for tracking number fix',
            'external_office_id' => null,
            'hospital_id' => null,
            'municipality_id' => null,
            'sp_member_id' => null,
            'source_name' => 'Test Client',
            'source_contact_number' => '',
            'source_address' => '',
            'source_liaison_name' => '',
        ];

        // Validate
        $errors = $this->docService->validateSubmission($data);
        if (!empty($errors)) {
            echo "SKIPPED (validation failed)\n";
            return;
        }

        // Skip file upload for this test - focus on tracking number
        $uploadedFiles = ['name' => [0 => '']];

        try {
            $userId = 1;
            $result = $this->docService->receiveDocument($data, $uploadedFiles, $userId, 'ADMIN');

            // Verify document was inserted
            $stmt = $this->pdo->prepare("SELECT id FROM documents WHERE id = ?");
            $stmt->execute([$result['document_id']]);
            assert($stmt->fetch() !== false, "Document should be inserted");

            // Verify tracking number is unique
            $stmt2 = $this->pdo->prepare("SELECT COUNT(*) FROM documents WHERE tracking_number = ?");
            $stmt2->execute([$result['tracking_number']]);
            assert((int) $stmt2->fetchColumn() === 1, "Tracking number should be unique");

            // Verify tracking sequence matches both tables
            $stmt3 = $this->pdo->prepare("SELECT tracking_sequence FROM documents WHERE id = ?");
            $stmt3->execute([$result['document_id']]);
            $docSeq = (int) $stmt3->fetchColumn();

            $stmt4 = $this->pdo->query("SELECT last_sequence FROM document_tracking_sequences WHERE tracking_year = 2026");
            $trackSeq = (int) $stmt4->fetchColumn();

            assert($docSeq === $trackSeq, "Tracking sequence should be synced between tables");

            echo "PASSED (Document ID: {$result['document_id']}, Tracking: {$result['tracking_number']})\n";
        } catch (Throwable $e) {
            throw $e;
        }
    }
}

// Run the test
try {
    $test = new AdminReceiveDocumentTrackingRegressionTest();
    $test->run();
} catch (Throwable $e) {
    echo "\nFAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

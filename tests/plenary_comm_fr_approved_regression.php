<?php
/**
 * Regression test: Plenary Comm First Reading — "Approved for Resolution"
 * (Other Matters → Approved for Resolution)
 *
 * Bug (root cause):
 *   The plenary_comm_resolution_files table was originally created by a now-
 *   deleted migration (075_comm_resolution_files_flow_columns) using different
 *   column names from the current design:
 *
 *     Old DB columns   │  Old FKs                    │  New expected names
 *     ─────────────────┼─────────────────────────────┼────────────────────
 *     decision_id      │ → plenary_comm_decisions.id  │  first_reading_id
 *     finalized_id     │ → plenary_comm_finalized.id  │  adoption_id
 *
 *   The replacement migration (075_plenary_comm_first_reading_schema) used
 *   CREATE TABLE IF NOT EXISTS, so it silently did nothing on the live DB.
 *   PlenaryReadingController::insertCommResolutionFiles() INSERT-ed rows
 *   naming first_reading_id and adoption_id — columns that did not exist —
 *   producing:
 *
 *     SQLSTATE[42S22]: Column not found: 1054
 *     Unknown column 'first_reading_id' in 'field list'
 *
 * Fix applied:
 *   Migration 077_fix_plenary_comm_resolution_files_columns drops the stale
 *   foreign keys and renames the columns so they match the controller.
 *
 * What this test verifies:
 *   STRUCTURAL (Schema)
 *     S1. plenary_comm_resolution_files has first_reading_id (not decision_id)
 *     S2. plenary_comm_resolution_files has adoption_id (not finalized_id)
 *     S3. FK fk_pcrf_first_reading points to plenary_comm_first_reading.id
 *     S4. FK fk_pcrf_adoption points to plenary_comm_adoptions.id
 *     S5. Stale FKs fk_pcrf_decision and fk_pcrf_finalized are gone
 *
 *   FUNCTIONAL (End-to-end INSERT in a rolled-back transaction)
 *     F1. Insert into plenary_comm_first_reading with decision=APPROVED succeeds
 *     F2. Insert into plenary_comm_resolution_files with first_reading_id succeeds
 *         (this is the exact query that threw the SQLSTATE[42S22] error)
 *     F3. The inserted resolution-file row links back to the first-reading row
 *     F4. Same INSERT with adoption_id (adopt-comm path) also succeeds
 *     F5. Noted path (doCommFrNoted) — no resolution-file INSERT — is unaffected
 *     F6. Refer-to-Committee path — no resolution-file INSERT — is unaffected
 *
 * Strategy: all DB writes are wrapped in a single transaction that is
 * ROLLED BACK at the end.  The test leaves the DB unchanged.
 *
 * Run with:  php tests/plenary_comm_fr_approved_regression.php
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

// ============================================================================
// STRUCTURAL CHECKS  (S1–S5)
// ============================================================================
echo PHP_EOL . "=== Structural: plenary_comm_resolution_files column / FK checks ===" . PHP_EOL;

$cols = $pdo->query("
    SELECT COLUMN_NAME
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'plenary_comm_resolution_files'
")->fetchAll(PDO::FETCH_COLUMN);

check(
    'S1: first_reading_id column exists (new name)',
    in_array('first_reading_id', $cols),
    'Column missing — migration 077 may not have run'
);
check(
    'S2: adoption_id column exists (new name)',
    in_array('adoption_id', $cols),
    'Column missing — migration 077 may not have run'
);
check(
    'S1b: decision_id column is gone (old name)',
    !in_array('decision_id', $cols),
    'Old stale column still present'
);
check(
    'S2b: finalized_id column is gone (old name)',
    !in_array('finalized_id', $cols),
    'Old stale column still present'
);

$fks = $pdo->query("
    SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA          = DATABASE()
      AND TABLE_NAME             = 'plenary_comm_resolution_files'
      AND REFERENCED_TABLE_NAME IS NOT NULL
")->fetchAll();

$fkByName = [];
foreach ($fks as $fk) {
    $fkByName[$fk['CONSTRAINT_NAME']] = $fk;
}

check(
    'S3: fk_pcrf_first_reading points to plenary_comm_first_reading',
    isset($fkByName['fk_pcrf_first_reading'])
    && $fkByName['fk_pcrf_first_reading']['REFERENCED_TABLE_NAME'] === 'plenary_comm_first_reading',
    isset($fkByName['fk_pcrf_first_reading'])
        ? 'FK exists but points to: ' . $fkByName['fk_pcrf_first_reading']['REFERENCED_TABLE_NAME']
        : 'FK fk_pcrf_first_reading not found'
);
check(
    'S4: fk_pcrf_adoption points to plenary_comm_adoptions',
    isset($fkByName['fk_pcrf_adoption'])
    && $fkByName['fk_pcrf_adoption']['REFERENCED_TABLE_NAME'] === 'plenary_comm_adoptions',
    isset($fkByName['fk_pcrf_adoption'])
        ? 'FK exists but points to: ' . $fkByName['fk_pcrf_adoption']['REFERENCED_TABLE_NAME']
        : 'FK fk_pcrf_adoption not found'
);
check(
    'S5a: stale FK fk_pcrf_decision is absent',
    !isset($fkByName['fk_pcrf_decision']),
    'Old FK still present — may block inserts with new column names'
);
check(
    'S5b: stale FK fk_pcrf_finalized is absent',
    !isset($fkByName['fk_pcrf_finalized']),
    'Old FK still present — may block inserts with new column names'
);

// ============================================================================
// FUNCTIONAL CHECKS  (F1–F6)
// All writes are in a single transaction that is ROLLED BACK at the end.
// ============================================================================
echo PHP_EOL . "=== Functional: INSERT path checks (rolled back) ===" . PHP_EOL;

// Resolve prerequisite IDs from the live DB
$docTypeId = (int) $pdo->query(
    "SELECT id FROM document_types WHERE is_active=1 AND is_deleted=0 ORDER BY id LIMIT 1"
)->fetchColumn();
$srcTypeId = (int) $pdo->query(
    "SELECT id FROM source_types WHERE is_active=1 AND is_deleted=0 ORDER BY id LIMIT 1"
)->fetchColumn();
$awaitingStatusId = (int) $pdo->query(
    "SELECT id FROM document_statuses
      WHERE name='Plenary Comm: Awaiting First Reading' AND is_active=1 LIMIT 1"
)->fetchColumn();
$finalizedStatusId = (int) $pdo->query(
    "SELECT id FROM document_statuses
      WHERE name='Plenary Comm: Finalized' AND is_active=1 LIMIT 1"
)->fetchColumn();
$notedStatusId = (int) $pdo->query(
    "SELECT id FROM document_statuses
      WHERE name='Plenary Comm: Noted' AND is_active=1 LIMIT 1"
)->fetchColumn();
$referredStatusId = (int) $pdo->query(
    "SELECT id FROM document_statuses
      WHERE name='Plenary Comm: Referred to Committee' AND is_active=1 LIMIT 1"
)->fetchColumn();

if (!$docTypeId || !$srcTypeId || !$awaitingStatusId || !$finalizedStatusId) {
    fail(
        'F-PREREQ: Required DB rows missing',
        'document_types, source_types, or plenary-comm statuses not found; run migrations first'
    );
    echo PHP_EOL;
    echo "Results: {$passed} passed, {$failed} failed." . PHP_EOL;
    exit($failed > 0 ? 1 : 0);
}

$pdo->beginTransaction();
try {
    // ── Seed a minimal Communication document for the functional tests ──────
    $pdo->prepare("
        INSERT INTO documents
            (tracking_year, tracking_sequence, tracking_number,
             date_received, time_received,
             subject_matter, document_type_id, source_type_id,
             current_status_id, current_phase, source_name)
        VALUES (1900, 99998, 'FX-COMM-APPR-99998',
                CURDATE(), CURTIME(),
                '[TEST] Plenary Comm FR Approved regression fixture',
                ?, ?, ?, 'PLENARY', '[TEST]')
    ")->execute([$docTypeId, $srcTypeId, $awaitingStatusId]);
    $docId = (int) $pdo->lastInsertId();

    // Also seed a plenary_document_details row (required by controller UPDATE)
    $pdo->prepare("
        INSERT INTO plenary_document_details (document_id, status_id)
        VALUES (?, ?)
    ")->execute([$docId, $awaitingStatusId]);

    // ── F1: Insert plenary_comm_first_reading with APPROVED decision ─────────
    $insertedFrId = null;
    try {
        $pdo->prepare("
            INSERT INTO plenary_comm_first_reading
                (document_id, decision, reading_date, remarks,
                 proposed_number, resolution_number,
                 performed_by, finalized_at, finalized_by)
            VALUES (?, 'APPROVED', CURDATE(), NULL,
                    NULL, 'RES-2026-001',
                    NULL, NOW(), NULL)
        ")->execute([$docId]);
        $insertedFrId = (int) $pdo->lastInsertId();
        pass('F1: INSERT into plenary_comm_first_reading (APPROVED) succeeds');
    } catch (PDOException $e) {
        fail('F1: INSERT into plenary_comm_first_reading (APPROVED) failed', $e->getMessage());
    }

    // ── F2 & F3: Insert resolution file with first_reading_id ───────────────
    //   This is the exact INSERT that threw SQLSTATE[42S22] before the fix.
    if ($insertedFrId !== null) {
        try {
            $pdo->prepare("
                INSERT INTO plenary_comm_resolution_files
                    (document_id, first_reading_id, adoption_id,
                     original_filename, stored_filename, file_path,
                     mime_type, file_size, uploaded_by)
                VALUES (?, ?, NULL,
                        'test_resolution.pdf', 'test_resolution_safe.pdf',
                        'uploads/plenary_res/test_resolution_safe.pdf',
                        'application/pdf', 12345, NULL)
            ")->execute([$docId, $insertedFrId]);
            $resFileId = (int) $pdo->lastInsertId();
            pass('F2: INSERT into plenary_comm_resolution_files (first_reading_id path) succeeds');

            // Verify the link
            $resRow = $pdo->prepare(
                "SELECT first_reading_id, adoption_id FROM plenary_comm_resolution_files WHERE id = ?"
            );
            $resRow->execute([$resFileId]);
            $rf = $resRow->fetch();
            check(
                'F3: Inserted resolution file has correct first_reading_id',
                $rf !== false && (int) $rf['first_reading_id'] === $insertedFrId,
                $rf ? "first_reading_id={$rf['first_reading_id']}, expected {$insertedFrId}" : 'row not found'
            );
            check(
                'F3b: adoption_id is NULL for APPROVED-path file',
                $rf !== false && $rf['adoption_id'] === null,
                $rf ? "adoption_id=" . var_export($rf['adoption_id'], true) : 'row not found'
            );
        } catch (PDOException $e) {
            fail('F2: INSERT into plenary_comm_resolution_files (first_reading_id path) failed', $e->getMessage());
            fail('F3: first_reading_id link check skipped (F2 failed)');
            fail('F3b: adoption_id NULL check skipped (F2 failed)');
        }
    } else {
        fail('F2: first_reading_id INSERT skipped (F1 failed)');
        fail('F3: first_reading_id link check skipped (F1 failed)');
        fail('F3b: adoption_id NULL check skipped (F1 failed)');
    }

    // ── F4: Seed a REFER_TO_COMMITTEE row then insert plenary_comm_adoptions
    //       and a resolution file with adoption_id (the adopt-comm path) ──────
    $pdo->prepare("
        INSERT INTO documents
            (tracking_year, tracking_sequence, tracking_number,
             date_received, time_received,
             subject_matter, document_type_id, source_type_id,
             current_status_id, current_phase, source_name)
        VALUES (1900, 99997, 'FX-COMM-ADPT-99997',
                CURDATE(), CURTIME(),
                '[TEST] Plenary Comm Adopt regression fixture',
                ?, ?, ?, 'PLENARY', '[TEST]')
    ")->execute([$docTypeId, $srcTypeId, $awaitingStatusId]);
    $docId2 = (int) $pdo->lastInsertId();

    $pdo->prepare("
        INSERT INTO plenary_document_details (document_id, status_id)
        VALUES (?, ?)
    ")->execute([$docId2, $awaitingStatusId]);

    $pdo->prepare("
        INSERT INTO plenary_comm_first_reading
            (document_id, decision, reading_date, performed_by)
        VALUES (?, 'REFER_TO_COMMITTEE', CURDATE(), NULL)
    ")->execute([$docId2]);
    $frId2 = (int) $pdo->lastInsertId();

    try {
        $pdo->prepare("
            INSERT INTO plenary_comm_adoptions
                (document_id, first_reading_id, resolution_number, adopted_by)
            VALUES (?, ?, 'RES-2026-002', NULL)
        ")->execute([$docId2, $frId2]);
        $adoptionId = (int) $pdo->lastInsertId();

        $pdo->prepare("
            INSERT INTO plenary_comm_resolution_files
                (document_id, first_reading_id, adoption_id,
                 original_filename, stored_filename, file_path,
                 mime_type, file_size, uploaded_by)
            VALUES (?, NULL, ?,
                    'adopt_resolution.pdf', 'adopt_resolution_safe.pdf',
                    'uploads/plenary_res/adopt_resolution_safe.pdf',
                    'application/pdf', 54321, NULL)
        ")->execute([$docId2, $adoptionId]);
        $adoptResFileId = (int) $pdo->lastInsertId();

        $adoptResRow = $pdo->prepare(
            "SELECT first_reading_id, adoption_id FROM plenary_comm_resolution_files WHERE id = ?"
        );
        $adoptResRow->execute([$adoptResFileId]);
        $arf = $adoptResRow->fetch();

        pass('F4: INSERT into plenary_comm_resolution_files (adoption_id path) succeeds');
        check(
            'F4b: Inserted adoption file has correct adoption_id and NULL first_reading_id',
            $arf !== false
            && (int) $arf['adoption_id'] === $adoptionId
            && $arf['first_reading_id'] === null,
            $arf
                ? "adoption_id={$arf['adoption_id']}, first_reading_id=" . var_export($arf['first_reading_id'], true)
                : 'row not found'
        );
    } catch (PDOException $e) {
        fail('F4: adoption_id INSERT path failed', $e->getMessage());
        fail('F4b: adoption_id link check skipped');
    }

    // ── F5: NOTED path — no resolution-file INSERT needed; just verify the
    //        plenary_comm_first_reading INSERT works for NOTED decision ────────
    $pdo->prepare("
        INSERT INTO documents
            (tracking_year, tracking_sequence, tracking_number,
             date_received, time_received,
             subject_matter, document_type_id, source_type_id,
             current_status_id, current_phase, source_name)
        VALUES (1900, 99996, 'FX-COMM-NOTED-99996',
                CURDATE(), CURTIME(),
                '[TEST] Plenary Comm Noted regression fixture',
                ?, ?, ?, 'PLENARY', '[TEST]')
    ")->execute([$docTypeId, $srcTypeId, $awaitingStatusId]);
    $docId3 = (int) $pdo->lastInsertId();

    try {
        $pdo->prepare("
            INSERT INTO plenary_comm_first_reading
                (document_id, decision, reading_date, performed_by)
            VALUES (?, 'NOTED', CURDATE(), NULL)
        ")->execute([$docId3]);
        pass('F5: NOTED path INSERT into plenary_comm_first_reading succeeds (unaffected)');
    } catch (PDOException $e) {
        fail('F5: NOTED path INSERT failed', $e->getMessage());
    }

    // ── F6: REFER_TO_COMMITTEE path — no resolution-file INSERT needed ────────
    $pdo->prepare("
        INSERT INTO documents
            (tracking_year, tracking_sequence, tracking_number,
             date_received, time_received,
             subject_matter, document_type_id, source_type_id,
             current_status_id, current_phase, source_name)
        VALUES (1900, 99995, 'FX-COMM-REFR-99995',
                CURDATE(), CURTIME(),
                '[TEST] Plenary Comm Refer regression fixture',
                ?, ?, ?, 'PLENARY', '[TEST]')
    ")->execute([$docTypeId, $srcTypeId, $awaitingStatusId]);
    $docId4 = (int) $pdo->lastInsertId();

    try {
        $pdo->prepare("
            INSERT INTO plenary_comm_first_reading
                (document_id, decision, reading_date, performed_by)
            VALUES (?, 'REFER_TO_COMMITTEE', CURDATE(), NULL)
        ")->execute([$docId4]);
        pass('F6: REFER_TO_COMMITTEE path INSERT into plenary_comm_first_reading succeeds (unaffected)');
    } catch (PDOException $e) {
        fail('F6: REFER_TO_COMMITTEE path INSERT failed', $e->getMessage());
    }

} catch (Throwable $e) {
    fail('UNEXPECTED: An unhandled exception occurred during functional tests', $e->getMessage());
} finally {
    // Always roll back — test leaves the DB unchanged
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

// ============================================================================
// Summary
// ============================================================================
echo PHP_EOL;
echo "Results: {$passed} passed, {$failed} failed." . PHP_EOL;
if ($failed > 0) {
    echo PHP_EOL . "REGRESSION DETECTED — fix plenary_comm_resolution_files column names." . PHP_EOL;
    echo "  Run:  php migrate.php migrate" . PHP_EOL;
    echo "  This should apply migration 077_fix_plenary_comm_resolution_files_columns." . PHP_EOL;
}
echo PHP_EOL;
exit($failed > 0 ? 1 : 0);

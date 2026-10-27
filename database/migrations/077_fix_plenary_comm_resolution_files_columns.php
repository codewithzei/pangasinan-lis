<?php

/**
 * Migration 077 — Fix plenary_comm_resolution_files column names
 *
 * PROBLEM
 * ───────
 * The plenary_comm_resolution_files table was first created by an earlier,
 * now-deleted migration (075_comm_resolution_files_flow_columns) that used
 * a different schema design:
 *
 *   decision_id   BIGINT UNSIGNED NULL  → FK to plenary_comm_decisions.id
 *   finalized_id  BIGINT UNSIGNED NULL  → FK to plenary_comm_finalized.id
 *
 * The current code (PlenaryReadingController::insertCommResolutionFiles) and
 * the replacement migration (075_plenary_comm_first_reading_schema) both expect
 * the columns to be named:
 *
 *   first_reading_id  BIGINT UNSIGNED NULL  → FK to plenary_comm_first_reading.id
 *   adoption_id       BIGINT UNSIGNED NULL  → FK to plenary_comm_adoptions.id
 *
 * Because 075_plenary_comm_first_reading_schema used CREATE TABLE IF NOT EXISTS,
 * the table was never re-created and the old column names persisted.  This
 * caused SQLSTATE[42S22] "Unknown column 'first_reading_id' in 'field list'"
 * whenever Approved for Resolution was selected under Other Matters.
 *
 * FIX
 * ───
 * 1. Drop the stale foreign keys that reference the old tables.
 * 2. Rename decision_id  → first_reading_id.
 * 3. Rename finalized_id → adoption_id.
 * 4. Add the correct foreign keys pointing to plenary_comm_first_reading and
 *    plenary_comm_adoptions (the tables that now exist).
 * 5. Rename the stale indexes to match the new column names.
 *
 * All steps are guarded so re-running the migration is safe.
 */
class FixPlenaryCommResolutionFilesColumns
{
    public function up(PDO $pdo): void
    {
        // ── 1. Drop stale foreign keys (ignore if they no longer exist) ────────
        foreach (['fk_pcrf_decision', 'fk_pcrf_finalized'] as $fkName) {
            $exists = $pdo->query("
                SELECT COUNT(*) AS cnt
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA    = DATABASE()
                  AND TABLE_NAME      = 'plenary_comm_resolution_files'
                  AND CONSTRAINT_NAME = '{$fkName}'
                  AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            ")->fetch(PDO::FETCH_ASSOC);

            if ((int) ($exists['cnt'] ?? 0) > 0) {
                $pdo->exec("
                    ALTER TABLE plenary_comm_resolution_files
                    DROP FOREIGN KEY `{$fkName}`
                ");
            }
        }

        // ── 2. Rename decision_id → first_reading_id (if still old name) ──────
        $hasDecisionId = $pdo->query("
            SELECT COUNT(*) AS cnt
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'plenary_comm_resolution_files'
              AND COLUMN_NAME  = 'decision_id'
        ")->fetch(PDO::FETCH_ASSOC);

        if ((int) ($hasDecisionId['cnt'] ?? 0) > 0) {
            $pdo->exec("
                ALTER TABLE plenary_comm_resolution_files
                CHANGE COLUMN `decision_id` `first_reading_id`
                    BIGINT UNSIGNED NULL
                    COMMENT 'FK to plenary_comm_first_reading.id; set for APPROVED decision files'
            ");
        }

        // ── 3. Rename finalized_id → adoption_id (if still old name) ──────────
        $hasFinalizedId = $pdo->query("
            SELECT COUNT(*) AS cnt
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'plenary_comm_resolution_files'
              AND COLUMN_NAME  = 'finalized_id'
        ")->fetch(PDO::FETCH_ASSOC);

        if ((int) ($hasFinalizedId['cnt'] ?? 0) > 0) {
            $pdo->exec("
                ALTER TABLE plenary_comm_resolution_files
                CHANGE COLUMN `finalized_id` `adoption_id`
                    BIGINT UNSIGNED NULL
                    COMMENT 'FK to plenary_comm_adoptions.id; set for adopted-report files'
            ");
        }

        // ── 4. Add correct FK for first_reading_id ────────────────────────────
        $fkFrExists = $pdo->query("
            SELECT COUNT(*) AS cnt
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA    = DATABASE()
              AND TABLE_NAME      = 'plenary_comm_resolution_files'
              AND CONSTRAINT_NAME = 'fk_pcrf_first_reading'
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ")->fetch(PDO::FETCH_ASSOC);

        if ((int) ($fkFrExists['cnt'] ?? 0) === 0) {
            // Only add if plenary_comm_first_reading exists
            $parentExists = $pdo->query("
                SELECT COUNT(*) AS cnt
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME   = 'plenary_comm_first_reading'
            ")->fetch(PDO::FETCH_ASSOC);

            if ((int) ($parentExists['cnt'] ?? 0) > 0) {
                $pdo->exec("
                    ALTER TABLE plenary_comm_resolution_files
                    ADD CONSTRAINT fk_pcrf_first_reading
                        FOREIGN KEY (first_reading_id)
                        REFERENCES plenary_comm_first_reading(id) ON DELETE SET NULL
                ");
            }
        }

        // ── 5. Add correct FK for adoption_id ────────────────────────────────
        $fkAdoptExists = $pdo->query("
            SELECT COUNT(*) AS cnt
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA    = DATABASE()
              AND TABLE_NAME      = 'plenary_comm_resolution_files'
              AND CONSTRAINT_NAME = 'fk_pcrf_adoption'
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ")->fetch(PDO::FETCH_ASSOC);

        if ((int) ($fkAdoptExists['cnt'] ?? 0) === 0) {
            // Only add if plenary_comm_adoptions exists
            $parentExists = $pdo->query("
                SELECT COUNT(*) AS cnt
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME   = 'plenary_comm_adoptions'
            ")->fetch(PDO::FETCH_ASSOC);

            if ((int) ($parentExists['cnt'] ?? 0) > 0) {
                $pdo->exec("
                    ALTER TABLE plenary_comm_resolution_files
                    ADD CONSTRAINT fk_pcrf_adoption
                        FOREIGN KEY (adoption_id)
                        REFERENCES plenary_comm_adoptions(id) ON DELETE SET NULL
                ");
            }
        }

        // ── 6. Rename stale indexes if they still reference old column names ──
        // MySQL renames the index automatically on CHANGE COLUMN, but if an
        // explicit old index was created with the old name, rename it.
        foreach (['idx_pcrf_decision_id' => 'idx_pcrf_first_reading_id',
                  'idx_pcrf_finalized_id' => 'idx_pcrf_adoption_id'] as $oldIdx => $newIdx) {
            $idxExists = $pdo->query("
                SELECT COUNT(*) AS cnt
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME   = 'plenary_comm_resolution_files'
                  AND INDEX_NAME   = '{$oldIdx}'
            ")->fetch(PDO::FETCH_ASSOC);

            if ((int) ($idxExists['cnt'] ?? 0) > 0) {
                $pdo->exec("
                    ALTER TABLE plenary_comm_resolution_files
                    RENAME INDEX `{$oldIdx}` TO `{$newIdx}`
                ");
            }
        }
    }

    public function down(PDO $pdo): void
    {
        // Drop the new FKs
        foreach (['fk_pcrf_first_reading', 'fk_pcrf_adoption'] as $fkName) {
            $exists = $pdo->query("
                SELECT COUNT(*) AS cnt
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA    = DATABASE()
                  AND TABLE_NAME      = 'plenary_comm_resolution_files'
                  AND CONSTRAINT_NAME = '{$fkName}'
                  AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            ")->fetch(PDO::FETCH_ASSOC);

            if ((int) ($exists['cnt'] ?? 0) > 0) {
                $pdo->exec("
                    ALTER TABLE plenary_comm_resolution_files
                    DROP FOREIGN KEY `{$fkName}`
                ");
            }
        }

        // Rename columns back
        $hasFirstReadingId = $pdo->query("
            SELECT COUNT(*) AS cnt
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'plenary_comm_resolution_files'
              AND COLUMN_NAME  = 'first_reading_id'
        ")->fetch(PDO::FETCH_ASSOC);

        if ((int) ($hasFirstReadingId['cnt'] ?? 0) > 0) {
            $pdo->exec("
                ALTER TABLE plenary_comm_resolution_files
                CHANGE COLUMN `first_reading_id` `decision_id`
                    BIGINT UNSIGNED NULL
            ");
        }

        $hasAdoptionId = $pdo->query("
            SELECT COUNT(*) AS cnt
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'plenary_comm_resolution_files'
              AND COLUMN_NAME  = 'adoption_id'
        ")->fetch(PDO::FETCH_ASSOC);

        if ((int) ($hasAdoptionId['cnt'] ?? 0) > 0) {
            $pdo->exec("
                ALTER TABLE plenary_comm_resolution_files
                CHANGE COLUMN `adoption_id` `finalized_id`
                    BIGINT UNSIGNED NULL
            ");
        }
    }
}

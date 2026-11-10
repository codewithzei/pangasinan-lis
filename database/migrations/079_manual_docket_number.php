<?php

/**
 * Migration 079 — Switch Committee Cases to manual docket-number entry
 *
 * WHAT CHANGED
 * ────────────────────────────────────────────────────────────────────────
 * The docket number is now entered manually by the committee user instead
 * of being auto-generated from committee_case_docket_sequences.
 *
 * Schema adjustments
 * ────────────────────────────────────────────────────────────────────────
 * 1. committee_cases.docket_year     — change from NOT NULL to NULL so rows
 *    created through the manual path (which do not have a sequence year)
 *    are valid. Existing auto-generated rows are unaffected.
 *
 * 2. committee_cases.docket_sequence — same; change to NULL for the same
 *    reason. Existing rows keep their numeric values.
 *
 *    UNIQUE KEY uq_year_sequence(docket_year, docket_sequence) remains in
 *    place but now operates under MySQL's standard NULL-allows-multiple-rows
 *    semantics (NULL != NULL), so two manually-entered rows with
 *    docket_year = NULL / docket_sequence = NULL do not conflict.
 *
 * 3. committee_cases.docket_number   — already VARCHAR(30) UNIQUE NOT NULL;
 *    widen to VARCHAR(100) to accommodate free-form manual numbers that may
 *    be longer than the old DKT-YYYY-NNNN format.
 *
 * 4. committee_case_docket_sequences — the table is kept (it has existing
 *    rows that were used by the old algorithm and may be referenced in audit
 *    logs). It is simply no longer written to by new code paths.
 *
 * 5. The UNIQUE constraint on docket_number already exists (uq_docket_number).
 *    No additional constraint work is needed — uniqueness of manually entered
 *    numbers is enforced at the application layer before the INSERT and backed
 *    by this constraint at the database layer.
 *
 * Idempotent: every ALTER is guarded by a column-definition check.
 */
class ManualDocketNumber
{
    public function up(PDO $pdo): void
    {
        // ── 1. Make docket_year nullable ──────────────────────────────────────
        $colType = $this->getColumnType($pdo, 'committee_cases', 'docket_year');
        if ($colType !== null && stripos($colType, 'NOT NULL') !== false) {
            $pdo->exec("
                ALTER TABLE committee_cases
                MODIFY COLUMN docket_year SMALLINT NULL DEFAULT NULL
            ");
        }

        // ── 2. Make docket_sequence nullable ──────────────────────────────────
        $colType = $this->getColumnType($pdo, 'committee_cases', 'docket_sequence');
        if ($colType !== null && stripos($colType, 'NOT NULL') !== false) {
            $pdo->exec("
                ALTER TABLE committee_cases
                MODIFY COLUMN docket_sequence INT NULL DEFAULT NULL
            ");
        }

        // ── 3. Widen docket_number from VARCHAR(30) to VARCHAR(100) ───────────
        $colInfo = $this->getColumnInfo($pdo, 'committee_cases', 'docket_number');
        if ($colInfo !== null) {
            // Re-apply with VARCHAR(100) regardless of current size — safe, non-destructive.
            $nullable = (strtoupper($colInfo['IS_NULLABLE']) === 'YES') ? 'NULL' : 'NOT NULL';
            $pdo->exec("
                ALTER TABLE committee_cases
                MODIFY COLUMN docket_number VARCHAR(100) {$nullable}
            ");
        }
    }

    public function down(PDO $pdo): void
    {
        // Restore docket_year and docket_sequence to NOT NULL.
        // Existing NULL rows would need to be backfilled before this can succeed
        // in a database that has manual-docket rows; safe only on a fresh DB.
        $pdo->exec("
            ALTER TABLE committee_cases
            MODIFY COLUMN docket_year SMALLINT NOT NULL
        ");
        $pdo->exec("
            ALTER TABLE committee_cases
            MODIFY COLUMN docket_sequence INT NOT NULL
        ");
        // Narrow docket_number back to VARCHAR(30).
        $pdo->exec("
            ALTER TABLE committee_cases
            MODIFY COLUMN docket_number VARCHAR(30) NOT NULL
        ");
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * Return the raw COLUMN_TYPE + IS_NULLABLE combined string for a column,
     * e.g. "smallint(6) NOT NULL".  Returns null if the column does not exist.
     */
    private function getColumnType(PDO $pdo, string $table, string $column): ?string
    {
        $stmt = $pdo->prepare("
            SELECT CONCAT(COLUMN_TYPE, ' ', IF(IS_NULLABLE='NO','NOT NULL','NULL')) AS col_def
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = ?
              AND COLUMN_NAME  = ?
            LIMIT 1
        ");
        $stmt->execute([$table, $column]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (string) $row['col_def'] : null;
    }

    /**
     * Return the information_schema row for a column, or null.
     */
    private function getColumnInfo(PDO $pdo, string $table, string $column): ?array
    {
        $stmt = $pdo->prepare("
            SELECT COLUMN_TYPE, IS_NULLABLE, CHARACTER_MAXIMUM_LENGTH
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = ?
              AND COLUMN_NAME  = ?
            LIMIT 1
        ");
        $stmt->execute([$table, $column]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

<?php

/**
 * Migration 060 — Committee Case Final Outcome
 *
 * 1. Adds four outcome columns to committee_cases:
 *      final_outcome         ENUM(APPROVED,DEFERRED,WITHDRAWN,NOTED) NULL
 *      final_outcome_remarks TEXT NULL
 *      finalized_by          BIGINT NULL  → FK → user_accounts(id) ON DELETE SET NULL
 *      finalized_at          TIMESTAMP NULL
 *
 * 2. Adds indexes:
 *      idx_cc_final_outcome   (final_outcome)
 *      idx_cc_finalized_by    (finalized_by)
 *      idx_cc_finalized_at    (finalized_at)
 *
 * 3. Seeds document_statuses (INSERT … ON DUPLICATE KEY UPDATE):
 *      Deferred               — already seeded in migration 055; UPDATE repairs it.
 *      Withdrawn              — already seeded in migration 054; UPDATE repairs it.
 *      Noted                  — new
 *      Hearing Completed      — already seeded in migration 055; UPDATE repairs it.
 *      Committee Report Created — already seeded in migration 055; UPDATE repairs it.
 *
 * 4. Expands document_events.event_type ENUM to include:
 *      COMMITTEE_CASE_APPROVED
 *      HEARING_DEFERRED       (may already exist from migration 055)
 *      HEARING_WITHDRAWN      (may already exist from migration 055)
 *      COMMITTEE_CASE_NOTED
 *
 * All DDL changes are column-presence–checked before execution so the
 * migration is safe to re-run.
 */
class CommitteeCaseOutcomes
{
    public function up(PDO $pdo): void
    {
        // ── 1. Add columns to committee_cases ────────────────────────────────

        // final_outcome
        if (!$this->columnExists($pdo, 'committee_cases', 'final_outcome')) {
            $pdo->exec("
                ALTER TABLE committee_cases
                ADD COLUMN final_outcome ENUM('APPROVED','DEFERRED','WITHDRAWN','NOTED')
                    NULL DEFAULT NULL
                AFTER respondents
            ");
        }

        // final_outcome_remarks
        if (!$this->columnExists($pdo, 'committee_cases', 'final_outcome_remarks')) {
            $pdo->exec("
                ALTER TABLE committee_cases
                ADD COLUMN final_outcome_remarks TEXT NULL DEFAULT NULL
                AFTER final_outcome
            ");
        }

        // finalized_by
        if (!$this->columnExists($pdo, 'committee_cases', 'finalized_by')) {
            $pdo->exec("
                ALTER TABLE committee_cases
                ADD COLUMN finalized_by BIGINT NULL DEFAULT NULL
                AFTER final_outcome_remarks
            ");
        }

        // finalized_at
        if (!$this->columnExists($pdo, 'committee_cases', 'finalized_at')) {
            $pdo->exec("
                ALTER TABLE committee_cases
                ADD COLUMN finalized_at TIMESTAMP NULL DEFAULT NULL
                AFTER finalized_by
            ");
        }

        // ── 2. Add indexes ────────────────────────────────────────────────────

        if (!$this->indexExists($pdo, 'committee_cases', 'idx_cc_final_outcome')) {
            $pdo->exec("
                ALTER TABLE committee_cases
                ADD INDEX idx_cc_final_outcome (final_outcome)
            ");
        }

        if (!$this->indexExists($pdo, 'committee_cases', 'idx_cc_finalized_by')) {
            $pdo->exec("
                ALTER TABLE committee_cases
                ADD INDEX idx_cc_finalized_by (finalized_by)
            ");
        }

        if (!$this->indexExists($pdo, 'committee_cases', 'idx_cc_finalized_at')) {
            $pdo->exec("
                ALTER TABLE committee_cases
                ADD INDEX idx_cc_finalized_at (finalized_at)
            ");
        }

        // ── 3. Foreign key: finalized_by → user_accounts(id) ─────────────────

        if (!$this->foreignKeyExists($pdo, 'committee_cases', 'fk_cc_finalized_by')) {
            $pdo->exec("
                ALTER TABLE committee_cases
                ADD CONSTRAINT fk_cc_finalized_by
                    FOREIGN KEY (finalized_by)
                    REFERENCES user_accounts(id)
                    ON DELETE SET NULL
            ");
        }

        // ── 4. Seed document_statuses ─────────────────────────────────────────
        //
        // Requires UNIQUE constraint on document_statuses.name.
        // Migration 054 already adds it; ensureUniqueIndex() is idempotent.
        $this->ensureUniqueIndex($pdo, 'document_statuses', 'uq_document_statuses_name', 'name');

        $statuses = [
            [
                'name'        => 'Deferred',
                'description' => 'Case deferred for further review.',
                'badge_color' => '#6B7280',   // gray-500
                'sort_order'  => 80,
            ],
            [
                'name'        => 'Withdrawn',
                'description' => 'Case withdrawn from the workflow.',
                'badge_color' => '#6B7280',   // gray-500
                'sort_order'  => 40,
            ],
            [
                'name'        => 'Noted',
                'description' => 'Case noted by the Committee.',
                'badge_color' => '#0891B2',   // cyan-600
                'sort_order'  => 90,
            ],
            [
                'name'        => 'Hearing Completed',
                'description' => 'Committee hearing has been conducted and an outcome has been recorded.',
                'badge_color' => '#2563EB',   // blue-600
                'sort_order'  => 60,
            ],
            [
                'name'        => 'Committee Report Created',
                'description' => 'A Committee or Joint Committee Report has been filed for this document.',
                'badge_color' => '#059669',   // emerald-600
                'sort_order'  => 70,
            ],
        ];

        $upsert = $pdo->prepare("
            INSERT INTO document_statuses
                (name, description, badge_color, sort_order, is_active, is_deleted)
            VALUES (?, ?, ?, ?, 1, 0)
            ON DUPLICATE KEY UPDATE
                description = VALUES(description),
                badge_color = VALUES(badge_color),
                sort_order  = VALUES(sort_order),
                is_active   = 1,
                is_deleted  = 0
        ");

        foreach ($statuses as $s) {
            $upsert->execute([$s['name'], $s['description'], $s['badge_color'], $s['sort_order']]);
        }

        // ── 5. Expand document_events.event_type ENUM ─────────────────────────

        $colRow = $pdo->query("
            SELECT COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'document_events'
              AND COLUMN_NAME  = 'event_type'
            LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);

        $existingValues = [];
        if ($colRow && preg_match("/^enum\((.+)\)$/i", $colRow['COLUMN_TYPE'], $m)) {
            preg_match_all("/'([^']+)'/", $m[1], $matches);
            $existingValues = $matches[1];
        }

        $newValues = [
            'COMMITTEE_CASE_APPROVED',
            'HEARING_DEFERRED',
            'HEARING_WITHDRAWN',
            'COMMITTEE_CASE_NOTED',
        ];

        $merged = $existingValues;
        foreach ($newValues as $v) {
            if (!in_array($v, $merged, true)) {
                $merged[] = $v;
            }
        }

        $enumList = implode(',', array_map(fn($v) => "'{$v}'", $merged));
        $pdo->exec("ALTER TABLE document_events MODIFY COLUMN event_type ENUM({$enumList}) NOT NULL");
    }

    // =========================================================================

    public function down(PDO $pdo): void
    {
        // Drop FK first, then indexes, then columns.
        if ($this->foreignKeyExists($pdo, 'committee_cases', 'fk_cc_finalized_by')) {
            $pdo->exec("ALTER TABLE committee_cases DROP FOREIGN KEY fk_cc_finalized_by");
        }

        foreach (['idx_cc_final_outcome', 'idx_cc_finalized_by', 'idx_cc_finalized_at'] as $idx) {
            if ($this->indexExists($pdo, 'committee_cases', $idx)) {
                $pdo->exec("ALTER TABLE committee_cases DROP INDEX {$idx}");
            }
        }

        foreach (['finalized_at', 'finalized_by', 'final_outcome_remarks', 'final_outcome'] as $col) {
            if ($this->columnExists($pdo, 'committee_cases', $col)) {
                $pdo->exec("ALTER TABLE committee_cases DROP COLUMN {$col}");
            }
        }

        // Remove only the statuses exclusively added by this migration.
        $pdo->exec("DELETE FROM document_statuses WHERE name = 'Noted'");

        // Revert event_type ENUM.
        $colRow = $pdo->query("
            SELECT COLUMN_TYPE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'document_events'
              AND COLUMN_NAME  = 'event_type'
            LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);

        $existingValues = [];
        if ($colRow && preg_match("/^enum\((.+)\)$/i", $colRow['COLUMN_TYPE'], $m)) {
            preg_match_all("/'([^']+)'/", $m[1], $matches);
            $existingValues = $matches[1];
        }

        $removeValues = ['COMMITTEE_CASE_APPROVED', 'COMMITTEE_CASE_NOTED'];
        $reverted = array_values(array_filter(
            $existingValues,
            fn($v) => !in_array($v, $removeValues, true)
        ));

        if (!empty($reverted)) {
            $enumList = implode(',', array_map(fn($v) => "'{$v}'", $reverted));
            $pdo->exec("ALTER TABLE document_events MODIFY COLUMN event_type ENUM({$enumList}) NOT NULL");
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = ?
              AND COLUMN_NAME  = ?
        ");
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function indexExists(PDO $pdo, string $table, string $indexName): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = ?
              AND INDEX_NAME   = ?
        ");
        $stmt->execute([$table, $indexName]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function foreignKeyExists(PDO $pdo, string $table, string $constraintName): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA      = DATABASE()
              AND TABLE_NAME        = ?
              AND CONSTRAINT_NAME   = ?
              AND CONSTRAINT_TYPE   = 'FOREIGN KEY'
        ");
        $stmt->execute([$table, $constraintName]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function ensureUniqueIndex(PDO $pdo, string $table, string $indexName, string $column): void
    {
        if ($this->indexExists($pdo, $table, $indexName)) {
            return;
        }

        // De-duplicate before adding constraint.
        $dup = $pdo->prepare("
            SELECT `{$column}`, COUNT(*) AS cnt
            FROM `{$table}`
            GROUP BY `{$column}`
            HAVING cnt > 1
        ");
        $dup->execute();
        $dupes = $dup->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($dupes)) {
            foreach ($dupes as $d) {
                $pdo->prepare("
                    UPDATE `{$table}` SET is_deleted = 1
                    WHERE `{$column}` = ?
                    ORDER BY id DESC
                    LIMIT 1
                ")->execute([$d[$column]]);
            }
        }

        $pdo->exec("CREATE UNIQUE INDEX `{$indexName}` ON `{$table}` (`{$column}`)");
    }
}

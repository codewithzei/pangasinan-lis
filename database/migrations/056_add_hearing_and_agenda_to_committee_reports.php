<?php

/**
 * Migration 056 — Link committee_reports to agendas and committee_hearings
 *
 * The committee_reports table was created in migration 042 without any
 * reference to the agenda or the specific hearing that produced the report.
 * This migration adds:
 *
 *   committee_reports
 *     agenda_id   BIGINT UNSIGNED NULL FK→agendas(id)
 *     hearing_id  BIGINT UNSIGNED NULL FK→committee_hearings(id)
 *
 * Both columns are nullable so that any pre-existing rows survive the ALTER
 * without error and the migration remains safe to re-run.
 *
 * The columns are added only when they do not already exist (checked via
 * information_schema.COLUMNS) to make the migration idempotent.
 */
class AddHearingAndAgendaToCommitteeReports
{
    public function up(PDO $pdo): void
    {
        // ── committee_reports.agenda_id ───────────────────────────────────────
        if (!$this->columnExists($pdo, 'committee_reports', 'agenda_id')) {
            $pdo->exec("
                ALTER TABLE committee_reports
                ADD COLUMN agenda_id BIGINT UNSIGNED NULL
                    COMMENT 'Agenda that produced this report'
                    AFTER summary_of_findings,
                ADD INDEX idx_committee_reports_agenda (agenda_id),
                ADD CONSTRAINT fk_cr_agenda
                    FOREIGN KEY (agenda_id)
                    REFERENCES agendas(id)
                    ON DELETE SET NULL
            ");
        }

        // ── committee_reports.hearing_id ─────────────────────────────────────
        if (!$this->columnExists($pdo, 'committee_reports', 'hearing_id')) {
            $pdo->exec("
                ALTER TABLE committee_reports
                ADD COLUMN hearing_id BIGINT UNSIGNED NULL
                    COMMENT 'Hearing record that produced this report'
                    AFTER agenda_id,
                ADD INDEX idx_committee_reports_hearing (hearing_id),
                ADD CONSTRAINT fk_cr_hearing
                    FOREIGN KEY (hearing_id)
                    REFERENCES committee_hearings(id)
                    ON DELETE SET NULL
            ");
        }
    }

    // =========================================================================

    public function down(PDO $pdo): void
    {
        // Drop FKs before columns (required by InnoDB).
        $this->dropForeignKeyIfExists($pdo, 'committee_reports', 'fk_cr_hearing');
        $this->dropForeignKeyIfExists($pdo, 'committee_reports', 'fk_cr_agenda');

        if ($this->columnExists($pdo, 'committee_reports', 'hearing_id')) {
            $pdo->exec("ALTER TABLE committee_reports DROP COLUMN hearing_id");
        }
        if ($this->columnExists($pdo, 'committee_reports', 'agenda_id')) {
            $pdo->exec("ALTER TABLE committee_reports DROP COLUMN agenda_id");
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

    private function dropForeignKeyIfExists(PDO $pdo, string $table, string $fkName): void
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND TABLE_NAME        = ?
              AND CONSTRAINT_NAME   = ?
              AND CONSTRAINT_TYPE   = 'FOREIGN KEY'
        ");
        $stmt->execute([$table, $fkName]);
        if ((int) $stmt->fetchColumn() > 0) {
            $pdo->exec("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`");
        }
    }
}

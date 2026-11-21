<?php

/**
 * Migration 108 — Motion for Reconsideration Plenary Outcome
 *
 * Stores the final Plenary decision for each MFR after it is adopted:
 * GRANTED or DENIED.
 */
class AddMfrPlenaryOutcome
{
    public function up(PDO $pdo): void
    {
        $pdo->exec("
            ALTER TABLE motion_reconsideration_links
                ADD COLUMN outcome ENUM('PENDING', 'GRANTED', 'DENIED') NOT NULL DEFAULT 'PENDING' AFTER referenced_document_id,
                ADD COLUMN outcome_remarks TEXT NULL AFTER outcome,
                ADD COLUMN outcome_at TIMESTAMP NULL AFTER outcome_remarks,
                ADD COLUMN outcome_by BIGINT NULL AFTER outcome_at,
                ADD INDEX idx_mrl_outcome (outcome),
                ADD INDEX idx_mrl_outcome_by (outcome_by),
                ADD CONSTRAINT fk_mrl_outcome_by
                    FOREIGN KEY (outcome_by) REFERENCES user_accounts (id)
                    ON DELETE SET NULL
        ");
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("ALTER TABLE motion_reconsideration_links DROP FOREIGN KEY fk_mrl_outcome_by");
        $pdo->exec("ALTER TABLE motion_reconsideration_links DROP INDEX idx_mrl_outcome");
        $pdo->exec("ALTER TABLE motion_reconsideration_links DROP INDEX idx_mrl_outcome_by");
        $pdo->exec("ALTER TABLE motion_reconsideration_links
            DROP COLUMN outcome_by,
            DROP COLUMN outcome_at,
            DROP COLUMN outcome_remarks,
            DROP COLUMN outcome
        ");
    }
}

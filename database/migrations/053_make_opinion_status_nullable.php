<?php

/**
 * Migration 053 — Make committee_cycle_endorsements.opinion_status_id nullable
 *
 * The original migration 040 defined opinion_status_id as NOT NULL, which
 * requires every freshly-created endorsement row to carry a valid status ID
 * up-front. However, at endorsement time a "Pending" opinion status must be
 * looked up dynamically (the table has no guaranteed ID). The cleanest fix
 * that is consistent with every other nullable FK in this codebase is to make
 * the column nullable so that new endorsement rows can be inserted with
 * opinion_status_id = NULL (meaning "status not yet set / Pending") and
 * updated once a submission is received.
 *
 * Foreign-key constraint is preserved.  Existing rows with valid values are
 * unaffected.
 */
class MakeOpinionStatusNullable
{
    public function up(PDO $pdo): void
    {
        // Make the column nullable; keep the FK.
        // We must drop and re-add the FK because MySQL requires the column
        // definition to match when re-adding a FK constraint.
        $pdo->exec("
            ALTER TABLE committee_cycle_endorsements
            MODIFY COLUMN opinion_status_id INT NULL
        ");
    }

    public function down(PDO $pdo): void
    {
        // Restore NOT NULL — set any NULL rows to 0 first (safe sentinel).
        $pdo->exec("
            UPDATE committee_cycle_endorsements
            SET opinion_status_id = 0
            WHERE opinion_status_id IS NULL
        ");

        $pdo->exec("
            ALTER TABLE committee_cycle_endorsements
            MODIFY COLUMN opinion_status_id INT NOT NULL
        ");
    }
}

<?php

/**
 * Migration 081 — Motion for Reconsideration
 *
 * Adds everything required to receive and process a Motion for Reconsideration
 * (MFR) document:
 *
 * 1. motion_reconsideration_links
 *    Stores the FK relationship between an MFR document and the
 *    Complaint / Administrative Cases document it challenges.
 *    Only documents whose document type is "Administrative Cases" or
 *    "Complaint" AND whose current_phase is 'FINALIZED' are eligible
 *    as reference documents.
 *    A single MFR maps to exactly one reference case (UNIQUE on mfr_document_id).
 *    A single case may receive multiple MFRs (no unique constraint on
 *    referenced_document_id).
 *
 * 2. STATUS SEEDS (idempotent via ON DUPLICATE KEY UPDATE)
 *    No new statuses are needed — MFRs follow the Communication workflow
 *    exactly and reuse its existing statuses.
 *
 * 3. EVENT TYPE ENUM additions (idempotent)
 *    MFR_LINKED — recorded when an MFR is received and its reference case
 *    is persisted to motion_reconsideration_links.
 *
 * All steps are idempotent / safe to re-run.
 */
class MotionForReconsideration
{
    private const NEW_EVENT_TYPES = ['MFR_LINKED'];

    // =========================================================================
    // UP
    // =========================================================================

    public function up(PDO $pdo): void
    {
        // ── 1. motion_reconsideration_links ───────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS motion_reconsideration_links (
                id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                mfr_document_id         BIGINT UNSIGNED NOT NULL
                    COMMENT 'FK → documents.id for the Motion for Reconsideration document',
                referenced_document_id  BIGINT UNSIGNED NOT NULL
                    COMMENT 'FK → documents.id for the original Complaint or Administrative Cases document',
                linked_by               BIGINT          NULL
                    COMMENT 'FK → user_accounts.id of the receiving user who logged the MFR',
                linked_at               TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                        ON UPDATE CURRENT_TIMESTAMP,

                PRIMARY KEY (id),

                -- An MFR document may only reference one original case.
                UNIQUE KEY uq_mrl_mfr_document_id (mfr_document_id),

                -- Speed up lookups from either direction.
                INDEX idx_mrl_mfr_document_id        (mfr_document_id),
                INDEX idx_mrl_referenced_document_id (referenced_document_id),
                INDEX idx_mrl_linked_by              (linked_by),
                INDEX idx_mrl_linked_at              (linked_at),

                CONSTRAINT fk_mrl_mfr_document
                    FOREIGN KEY (mfr_document_id)
                    REFERENCES documents (id)
                    ON DELETE RESTRICT,

                CONSTRAINT fk_mrl_referenced_document
                    FOREIGN KEY (referenced_document_id)
                    REFERENCES documents (id)
                    ON DELETE RESTRICT,

                CONSTRAINT fk_mrl_linked_by
                    FOREIGN KEY (linked_by)
                    REFERENCES user_accounts (id)
                    ON DELETE SET NULL

            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── 2. Expand document_events.event_type ENUM ─────────────────────────
        $this->addEnumValues($pdo, 'document_events', 'event_type', self::NEW_EVENT_TYPES);
    }

    // =========================================================================
    // DOWN
    // =========================================================================

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS motion_reconsideration_links");

        // Revert ENUM — remove MFR_LINKED if present.
        $colRow = $pdo->query("
            SELECT COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'document_events'
              AND COLUMN_NAME  = 'event_type'
            LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);

        if ($colRow && preg_match("/^enum\((.+)\)$/i", $colRow['COLUMN_TYPE'], $m)) {
            preg_match_all("/'([^']+)'/", $m[1], $matches);
            $values  = $matches[1];
            $reverted = array_values(array_filter($values, fn($v) => !in_array($v, self::NEW_EVENT_TYPES, true)));
            if (!empty($reverted)) {
                $enumList = implode(',', array_map(fn($v) => "'{$v}'", $reverted));
                $pdo->exec("ALTER TABLE document_events MODIFY COLUMN event_type ENUM({$enumList}) NOT NULL");
            }
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Idempotently append values to an ENUM column.
     *
     * @param PDO    $pdo
     * @param string $table
     * @param string $column
     * @param array  $newValues
     */
    private function addEnumValues(PDO $pdo, string $table, string $column, array $newValues): void
    {
        $colRow = $pdo->query("
            SELECT COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = '{$table}'
              AND COLUMN_NAME  = '{$column}'
            LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);

        $existing = [];
        if ($colRow && preg_match("/^enum\((.+)\)$/i", $colRow['COLUMN_TYPE'], $m)) {
            preg_match_all("/'([^']+)'/", $m[1], $matches);
            $existing = $matches[1];
        }

        $merged = $existing;
        $added  = false;
        foreach ($newValues as $v) {
            if (!in_array($v, $merged, true)) {
                $merged[] = $v;
                $added    = true;
            }
        }

        if ($added) {
            $enumList = implode(',', array_map(fn($v) => "'{$v}'", $merged));
            $pdo->exec("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` ENUM({$enumList}) NOT NULL");
        }
    }
}

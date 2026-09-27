<?php

/**
 * Migration 061 — Committee Communications table
 *
 * Creates the committee_communications table that supports the Communications
 * sub-workflow for documents whose document type is "Communication".
 *
 * Flow:
 *   Committee Inbox → Proceed to Communications → Communication record
 *   → Add to Agenda (agendas table, reused) → Committee Hearing
 *   → Committee Report → Return to Plenary
 *
 * This migration:
 * 1. Creates committee_communications (one row per document, duplicate guard).
 * 2. Seeds required document_statuses (idempotent INSERT … ON DUPLICATE KEY).
 * 3. Expands document_events.event_type ENUM with the new event types.
 *
 * Idempotent: uses IF NOT EXISTS and ON DUPLICATE KEY so it is safe to re-run.
 */
class CreateCommitteeCommunicationsTable
{
    public function up(PDO $pdo): void
    {
        // ── 1. committee_communications ───────────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS committee_communications (
                id             BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
                document_id    BIGINT UNSIGNED  NOT NULL,
                date_logged    DATE             NOT NULL,
                subject        VARCHAR(500)     NOT NULL,
                sender_details TEXT             NOT NULL,
                notes          TEXT             NULL DEFAULT NULL,
                assigned_by    BIGINT           NULL DEFAULT NULL,
                agenda_id      BIGINT UNSIGNED  NULL DEFAULT NULL,
                hearing_id     BIGINT UNSIGNED  NULL DEFAULT NULL,
                report_id      BIGINT UNSIGNED  NULL DEFAULT NULL,
                created_at     TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at     TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                ON UPDATE CURRENT_TIMESTAMP,

                PRIMARY KEY (id),

                -- A document may only have one communication record.
                UNIQUE KEY uq_comm_document_id (document_id),

                INDEX idx_comm_document_id  (document_id),
                INDEX idx_comm_date_logged  (date_logged),
                INDEX idx_comm_assigned_by  (assigned_by),
                INDEX idx_comm_agenda_id    (agenda_id),
                INDEX idx_comm_hearing_id   (hearing_id),
                INDEX idx_comm_report_id    (report_id),

                CONSTRAINT fk_ccomm_document
                    FOREIGN KEY (document_id) REFERENCES documents (id)
                    ON DELETE RESTRICT,

                CONSTRAINT fk_ccomm_assigned_by
                    FOREIGN KEY (assigned_by) REFERENCES user_accounts (id)
                    ON DELETE SET NULL,

                CONSTRAINT fk_ccomm_agenda
                    FOREIGN KEY (agenda_id) REFERENCES agendas (id)
                    ON DELETE SET NULL,

                CONSTRAINT fk_ccomm_hearing
                    FOREIGN KEY (hearing_id) REFERENCES committee_hearings (id)
                    ON DELETE SET NULL,

                CONSTRAINT fk_ccomm_report
                    FOREIGN KEY (report_id) REFERENCES committee_reports (id)
                    ON DELETE SET NULL

            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── 2. Seed document_statuses ─────────────────────────────────────────
        $this->ensureUniqueIndex($pdo, 'document_statuses', 'uq_document_statuses_name', 'name');

        $statuses = [
            [
                'name'        => 'Communication In Progress',
                'description' => 'A Communication record has been opened for this document.',
                'badge_color' => '#0D9488',   // teal-600
                'sort_order'  => 95,
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

        // ── 3. Expand document_events.event_type ENUM ─────────────────────────
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
            'COMMITTEE_COMMUNICATION_CREATED',
            'COMMITTEE_COMMUNICATION_AGENDA_SCHEDULED',
            'COMMITTEE_COMMUNICATION_HEARING_RECORDED',
            'COMMITTEE_COMMUNICATION_REPORT_CREATED',
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

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS committee_communications");

        // Remove seeded statuses
        $pdo->exec("
            DELETE FROM document_statuses
            WHERE name IN ('Communication In Progress')
        ");

        // Revert ENUM — remove the four communication event types
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

        $remove = [
            'COMMITTEE_COMMUNICATION_CREATED',
            'COMMITTEE_COMMUNICATION_AGENDA_SCHEDULED',
            'COMMITTEE_COMMUNICATION_HEARING_RECORDED',
            'COMMITTEE_COMMUNICATION_REPORT_CREATED',
        ];
        $reverted = array_values(array_filter($existingValues, fn($v) => !in_array($v, $remove, true)));

        if (!empty($reverted)) {
            $enumList = implode(',', array_map(fn($v) => "'{$v}'", $reverted));
            $pdo->exec("ALTER TABLE document_events MODIFY COLUMN event_type ENUM({$enumList}) NOT NULL");
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function ensureUniqueIndex(PDO $pdo, string $table, string $indexName, string $column): void
    {
        $exists = $pdo->query("
            SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = '{$table}'
              AND INDEX_NAME   = '{$indexName}'
        ")->fetchColumn();

        if ((int) $exists === 0) {
            $pdo->exec("ALTER TABLE `{$table}` ADD UNIQUE INDEX `{$indexName}` (`{$column}`)");
        }
    }
}

<?php

/**
 * Migration 066 — Plenary Readings Workflow Schema
 *
 * Adds the tables and status seeds needed for the full First Reading →
 * Second Reading → Finalized workflow in the Plenary module.
 *
 * NEW TABLES
 * ──────────
 * 1. plenary_readings
 *    One row per completed reading action on a document.
 *    reading_number = 1 (First Reading) or 2 (Second Reading).
 *    action = what the Plenary decided at that reading.
 *
 * 2. plenary_second_reading_details
 *    Additional data captured for Second Reading–specific actions
 *    (deferral date/remarks, withdrawal date/remarks, compliance attachment).
 *    Kept separate so the core plenary_readings row stays lean and reusable.
 *
 * SCHEMA ALTERATIONS
 * ──────────────────
 * 3. Expand document_events.event_type ENUM with new Plenary reading events.
 * 4. Expand document_attachments.attachment_type ENUM with COMPLIANCE_DOC.
 *    (Existing value COMPLIANCE_FILE from migration 030 may differ — checked
 *    dynamically.)
 *
 * STATUS SEEDS (idempotent via ON DUPLICATE KEY UPDATE)
 * ─────────────────────────────────────────────────────
 * 5. "Awaiting First Reading"   — document has Under Processing details saved,
 *                                 queued for the First Reading session.
 * 6. "First Reading Completed"  — First Reading done, awaiting next step.
 * 7. "Awaiting Second Reading"  — ready for Second Reading (either direct
 *                                 adoption or returned from Committee).
 * 8. "Second Reading Deferred"  — deferred at Second Reading (pending status).
 * 9. "Withdrawn"                — already seeded (migration 054 + 065); UPDATE
 *                                 ensures it is active.
 *
 * All steps are idempotent / safe to re-run.
 */
class PlenaryReadingsWorkflowSchema
{
    private const NEW_EVENT_TYPES = [
        'PLENARY_FIRST_READING_COMPLETED',
        'PLENARY_REFERRED_TO_COMMITTEE',
        'PLENARY_SECOND_READING_APPROVED',
        'PLENARY_SECOND_READING_DEFERRED',
        'PLENARY_SECOND_READING_WITHDRAWN',
        'PLENARY_SECOND_READING_REFERRED_TO_COMMITTEE',
        'PLENARY_FINALIZED',
    ];

    private const STATUS_SEEDS = [
        [
            'name'        => 'Awaiting First Reading',
            'description' => 'Plenary processing details saved; document queued for First Reading.',
            'badge_color' => '#F59E0B',
            'sort_order'  => 85,
        ],
        [
            'name'        => 'First Reading Completed',
            'description' => 'First Reading completed; pending next action.',
            'badge_color' => '#10B981',
            'sort_order'  => 86,
        ],
        [
            'name'        => 'Awaiting Second Reading',
            'description' => 'Document ready for Second Reading.',
            'badge_color' => '#3B82F6',
            'sort_order'  => 87,
        ],
        [
            'name'        => 'Second Reading Deferred',
            'description' => 'Document deferred at Second Reading; awaiting compliance update.',
            'badge_color' => '#EF4444',
            'sort_order'  => 88,
        ],
        [
            'name'        => 'Second Reading Approved',
            'description' => 'Document approved on Second Reading; finalized.',
            'badge_color' => '#059669',
            'sort_order'  => 89,
        ],
        [
            'name'        => 'Withdrawn from Second Reading',
            'description' => 'Document withdrawn during Second Reading processing.',
            'badge_color' => '#6B7280',
            'sort_order'  => 90,
        ],
    ];

    public function up(PDO $pdo): void
    {
        // ── 1. plenary_readings ───────────────────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS plenary_readings (
                id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                document_id         BIGINT UNSIGNED NOT NULL,
                reading_number      TINYINT UNSIGNED NOT NULL COMMENT '1 = First Reading, 2 = Second Reading',
                action              ENUM(
                                        'REFERRED_TO_COMMITTEE',
                                        'ADOPTED_SUSPENDED_RULES',
                                        'APPROVED',
                                        'DEFERRED',
                                        'WITHDRAWN',
                                        'REFERRED_TO_COMMITTEE_SECOND'
                                    ) NOT NULL,
                reading_date        DATE NOT NULL,
                remarks             TEXT NULL,
                performed_by        BIGINT NULL,
                performed_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_pr_document_id    (document_id),
                INDEX idx_pr_reading_number (reading_number),
                INDEX idx_pr_action         (action),
                INDEX idx_pr_performed_by   (performed_by),

                CONSTRAINT fk_pr_document    FOREIGN KEY (document_id)  REFERENCES documents(id) ON DELETE RESTRICT,
                CONSTRAINT fk_pr_performed   FOREIGN KEY (performed_by) REFERENCES user_accounts(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // ── 2. plenary_second_reading_details ─────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS plenary_second_reading_details (
                id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                reading_id              BIGINT UNSIGNED NOT NULL UNIQUE
                                            COMMENT 'FK to plenary_readings.id',
                document_id             BIGINT UNSIGNED NOT NULL,

                -- Approved fields
                approved_no             VARCHAR(100) NULL
                                            COMMENT 'Final approved number (Resolution/Ordinance No.)',
                date_finalized          DATE NULL,
                approved_subject_matter TEXT NULL,

                -- Deferral fields
                date_deferred           DATE NULL,
                deferred_remarks        TEXT NULL,

                -- Withdrawal fields
                date_withdrawn          DATE NULL,
                withdrawal_remark       TEXT NULL,

                -- Compliance attachment for deferred documents
                compliance_file_name    VARCHAR(500) NULL,
                compliance_file_path    VARCHAR(1000) NULL,
                compliance_mime_type    VARCHAR(200) NULL,
                compliance_file_size    BIGINT UNSIGNED NULL,
                compliance_uploaded_by  BIGINT NULL,
                compliance_uploaded_at  TIMESTAMP NULL,

                -- Re-open deferred: set by Plenary user when compliance is done
                returned_to_second_reading_by  BIGINT NULL,
                returned_to_second_reading_at  TIMESTAMP NULL,

                created_by  BIGINT NULL,
                updated_by  BIGINT NULL,
                created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_psrd_document_id (document_id),
                INDEX idx_psrd_reading_id  (reading_id),

                CONSTRAINT fk_psrd_reading   FOREIGN KEY (reading_id)  REFERENCES plenary_readings(id)   ON DELETE CASCADE,
                CONSTRAINT fk_psrd_document  FOREIGN KEY (document_id) REFERENCES documents(id)           ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

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

        $toAdd = array_diff(self::NEW_EVENT_TYPES, $existingValues);
        if (!empty($toAdd)) {
            $merged   = array_merge($existingValues, array_values($toAdd));
            $enumList = implode(',', array_map(fn($v) => "'{$v}'", $merged));
            $pdo->exec(
                "ALTER TABLE document_events
                 MODIFY COLUMN event_type ENUM({$enumList}) NOT NULL"
            );
        }

        // ── 4. Add COMPLIANCE_DOC to document_attachments.attachment_type ─────
        //      (if not already present; COMPLIANCE_FILE already exists)
        $attRow = $pdo->query("
            SELECT COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'document_attachments'
              AND COLUMN_NAME  = 'attachment_type'
            LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);

        $attValues = [];
        if ($attRow && preg_match("/^enum\((.+)\)$/i", $attRow['COLUMN_TYPE'], $m)) {
            preg_match_all("/'([^']+)'/", $m[1], $matches);
            $attValues = $matches[1];
        }

        if (!in_array('COMPLIANCE_DOC', $attValues, true)) {
            $otherIdx = array_search('OTHER', $attValues, true);
            if ($otherIdx !== false) {
                array_splice($attValues, $otherIdx, 0, ['COMPLIANCE_DOC']);
            } else {
                $attValues[] = 'COMPLIANCE_DOC';
            }
            $enumList = implode(',', array_map(fn($v) => "'{$v}'", $attValues));
            $pdo->exec(
                "ALTER TABLE document_attachments
                 MODIFY COLUMN attachment_type ENUM({$enumList}) NOT NULL DEFAULT 'OTHER'"
            );
        }

        // ── 5. Seed statuses ──────────────────────────────────────────────────
        // Ensure unique index on document_statuses.name (migration 054 adds it)
        $this->ensureUniqueIndex($pdo);

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

        foreach (self::STATUS_SEEDS as $s) {
            $upsert->execute([$s['name'], $s['description'], $s['badge_color'], $s['sort_order']]);
        }
    }

    public function down(PDO $pdo): void
    {
        // Remove new tables
        $pdo->exec("DROP TABLE IF EXISTS plenary_second_reading_details;");
        $pdo->exec("DROP TABLE IF EXISTS plenary_readings;");

        // Remove seeded statuses
        $names = array_column(self::STATUS_SEEDS, 'name');
        // Only remove ones we added; skip "Withdrawn" which predates us
        $ours = array_filter($names, fn($n) => $n !== 'Withdrawn');
        if (!empty($ours)) {
            $placeholders = implode(',', array_fill(0, count($ours), '?'));
            $pdo->prepare("DELETE FROM document_statuses WHERE name IN ({$placeholders})")
                ->execute(array_values($ours));
        }

        // Remove ENUM values from document_events.event_type
        $colRow = $pdo->query("
            SELECT COLUMN_TYPE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'document_events'
              AND COLUMN_NAME = 'event_type' LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);

        if ($colRow) {
            preg_match_all("/'([^']+)'/", $colRow['COLUMN_TYPE'], $matches);
            $reverted = array_values(array_diff($matches[1], self::NEW_EVENT_TYPES));
            if (!empty($reverted)) {
                $enumList = implode(',', array_map(fn($v) => "'{$v}'", $reverted));
                $pdo->exec("ALTER TABLE document_events MODIFY COLUMN event_type ENUM({$enumList}) NOT NULL");
            }
        }
    }

    private function ensureUniqueIndex(PDO $pdo): void
    {
        $existing = $pdo->query("
            SHOW INDEX FROM document_statuses WHERE Column_name = 'name' AND Non_unique = 0
        ")->fetchAll();

        if (empty($existing)) {
            $pdo->exec("ALTER TABLE document_statuses ADD UNIQUE KEY uq_ds_name_066 (name)");
        }
    }
}

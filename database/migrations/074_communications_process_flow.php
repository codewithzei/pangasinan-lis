<?php

/**
 * Migration 074 — Communications Process Flow
 *
 * Adds the schema needed for the full Communications Process Flow that lives
 * entirely in the SP Secretary → (Committee) → Plenary workflow:
 *
 *  1. comm_inbox_items          — tracks a Communication document entering the
 *                                 SP Secretary Communications Inbox (one per doc).
 *  2. comm_first_reading        — records the First Reading decision made by the
 *                                 SP Secretary on a comm_inbox_item.
 *  3. comm_committee_assignments — maps a comm_first_reading row to one or more
 *                                 committees when decision = 'REFERRED'.
 *  4. comm_resolution_files     — multi-file upload table for resolution files
 *                                 attached to a comm_first_reading (APPROVED) or
 *                                 to a committee_reports row (adopted report).
 *
 * STATUS SEEDS (idempotent)
 *  5. "Comm: Awaiting First Reading"
 *  6. "Comm: Noted"
 *  7. "Comm: Referred to Committee"
 *  8. "Comm: Approved for Resolution"
 *  9. "Comm: Report Adopted"
 * 10. "Comm: Finalized"
 *
 * EVENT TYPE ENUM additions (idempotent)
 *  11. COMM_SENT_TO_FIRST_READING
 *  12. COMM_FIRST_READING_NOTED
 *  13. COMM_FIRST_READING_REFERRED
 *  14. COMM_FIRST_READING_APPROVED
 *  15. COMM_REPORT_ADOPTED
 *  16. COMM_FINALIZED
 *
 * ATTACHMENT TYPE ENUM additions (idempotent)
 *  17. COMM_RESOLUTION_FILE
 *
 * All steps use CREATE TABLE IF NOT EXISTS and ON DUPLICATE KEY UPDATE so the
 * migration is safe to re-run.
 */
class CommunicationsProcessFlow
{
    private const STATUS_SEEDS = [
        [
            'name'        => 'Comm: Awaiting First Reading',
            'description' => 'Communication document sent to SP Secretary First Reading queue.',
            'badge_color' => '#F59E0B',
            'sort_order'  => 110,
        ],
        [
            'name'        => 'Comm: Noted',
            'description' => 'Communication noted at First Reading; sent to Plenary Communications page.',
            'badge_color' => '#10B981',
            'sort_order'  => 111,
        ],
        [
            'name'        => 'Comm: Referred to Committee',
            'description' => 'Communication referred to one or more committees at First Reading.',
            'badge_color' => '#8B5CF6',
            'sort_order'  => 112,
        ],
        [
            'name'        => 'Comm: Approved for Resolution',
            'description' => 'Communication approved for resolution under Other Matters at First Reading.',
            'badge_color' => '#3B82F6',
            'sort_order'  => 113,
        ],
        [
            'name'        => 'Comm: Report Adopted',
            'description' => 'Committee report on communication adopted; pending Plenary finalization.',
            'badge_color' => '#059669',
            'sort_order'  => 114,
        ],
        [
            'name'        => 'Comm: Finalized',
            'description' => 'Communication finalized and available on Finalized Documents.',
            'badge_color' => '#1D4ED8',
            'sort_order'  => 115,
        ],
    ];

    private const NEW_EVENT_TYPES = [
        'COMM_SENT_TO_FIRST_READING',
        'COMM_FIRST_READING_NOTED',
        'COMM_FIRST_READING_REFERRED',
        'COMM_FIRST_READING_APPROVED',
        'COMM_REPORT_ADOPTED',
        'COMM_FINALIZED',
    ];

    // =========================================================================
    // UP
    // =========================================================================

    public function up(PDO $pdo): void
    {
        // ── 1. comm_inbox_items ───────────────────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS comm_inbox_items (
                id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                document_id         BIGINT UNSIGNED NOT NULL,
                sent_to_fr_by       BIGINT          NULL COMMENT 'User who sent it to First Reading',
                sent_to_fr_at       TIMESTAMP       NULL,
                created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                    ON UPDATE CURRENT_TIMESTAMP,

                PRIMARY KEY (id),
                UNIQUE  KEY uq_cii_document_id  (document_id),
                INDEX        idx_cii_sent_by     (sent_to_fr_by),

                CONSTRAINT fk_cii_document   FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE RESTRICT,
                CONSTRAINT fk_cii_sent_by    FOREIGN KEY (sent_to_fr_by) REFERENCES user_accounts(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── 2. comm_first_reading ─────────────────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS comm_first_reading (
                id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                document_id         BIGINT UNSIGNED NOT NULL,
                inbox_item_id       BIGINT UNSIGNED NOT NULL,
                decision            ENUM('NOTED','REFERRED','APPROVED') NOT NULL,
                reading_date        DATE            NOT NULL,
                remarks             TEXT            NULL,

                -- NOTED: communication category (FK to communication_categories)
                communication_category_id  INT UNSIGNED NULL,

                -- APPROVED: resolution numbers
                proposed_number     VARCHAR(100)    NULL,
                resolution_number   VARCHAR(100)    NULL,

                performed_by        BIGINT          NULL,
                performed_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                    ON UPDATE CURRENT_TIMESTAMP,

                PRIMARY KEY (id),
                UNIQUE  KEY uq_cfr_document_id       (document_id),
                INDEX        idx_cfr_inbox_item_id   (inbox_item_id),
                INDEX        idx_cfr_decision        (decision),
                INDEX        idx_cfr_performed_by    (performed_by),

                CONSTRAINT fk_cfr_document   FOREIGN KEY (document_id)   REFERENCES documents(id)               ON DELETE RESTRICT,
                CONSTRAINT fk_cfr_inbox_item FOREIGN KEY (inbox_item_id) REFERENCES comm_inbox_items(id)        ON DELETE RESTRICT,
                CONSTRAINT fk_cfr_performed  FOREIGN KEY (performed_by)  REFERENCES user_accounts(id)           ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── 3. comm_committee_assignments ─────────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS comm_committee_assignments (
                id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                first_reading_id    BIGINT UNSIGNED NOT NULL,
                document_id         BIGINT UNSIGNED NOT NULL,
                committee_id        INT             NOT NULL,
                created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

                PRIMARY KEY (id),
                UNIQUE  KEY uq_cca_reading_committee  (first_reading_id, committee_id),
                INDEX        idx_cca_document_id      (document_id),
                INDEX        idx_cca_committee_id     (committee_id),

                CONSTRAINT fk_cca_reading    FOREIGN KEY (first_reading_id) REFERENCES comm_first_reading(id) ON DELETE CASCADE,
                CONSTRAINT fk_cca_document   FOREIGN KEY (document_id)      REFERENCES documents(id)          ON DELETE RESTRICT,
                CONSTRAINT fk_cca_committee  FOREIGN KEY (committee_id)     REFERENCES committees(id)         ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── 4. comm_resolution_files ──────────────────────────────────────────
        // Stores resolution files for APPROVED First Reading decisions and for
        // adopted committee reports. One of (first_reading_id, committee_report_id)
        // must be non-NULL; both may be set when a committee report adopts an
        // originally APPROVED communication.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS comm_resolution_files (
                id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                document_id         BIGINT UNSIGNED NOT NULL,
                first_reading_id    BIGINT UNSIGNED NULL,
                committee_report_id BIGINT UNSIGNED NULL,
                original_filename   VARCHAR(500)    NOT NULL,
                stored_filename     VARCHAR(500)    NOT NULL COMMENT 'Safe generated filename on disk',
                file_path           VARCHAR(1000)   NOT NULL,
                mime_type           VARCHAR(200)    NOT NULL,
                file_size           BIGINT UNSIGNED NOT NULL,
                uploaded_by         BIGINT          NULL,
                uploaded_at         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

                PRIMARY KEY (id),
                INDEX idx_crf_document_id         (document_id),
                INDEX idx_crf_first_reading_id    (first_reading_id),
                INDEX idx_crf_committee_report_id (committee_report_id),
                INDEX idx_crf_uploaded_by         (uploaded_by),

                CONSTRAINT fk_crf_document       FOREIGN KEY (document_id)         REFERENCES documents(id)         ON DELETE RESTRICT,
                CONSTRAINT fk_crf_first_reading  FOREIGN KEY (first_reading_id)    REFERENCES comm_first_reading(id) ON DELETE SET NULL,
                CONSTRAINT fk_crf_report         FOREIGN KEY (committee_report_id) REFERENCES committee_reports(id)  ON DELETE SET NULL,
                CONSTRAINT fk_crf_uploaded_by    FOREIGN KEY (uploaded_by)         REFERENCES user_accounts(id)     ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── 5. Seed document_statuses ─────────────────────────────────────────
        $this->ensureUniqueStatusIndex($pdo);

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

        // ── 6. Expand document_events.event_type ENUM ─────────────────────────
        $this->addEnumValues($pdo, 'document_events', 'event_type', self::NEW_EVENT_TYPES);

        // ── 7. Expand document_attachments.attachment_type ENUM ───────────────
        $this->addEnumValues($pdo, 'document_attachments', 'attachment_type', ['COMM_RESOLUTION_FILE']);

        // ── 8. Add comm_report_adopted columns to committee_reports ───────────
        // These capture resolution details when a committee adopts its report
        // on a Communication document.
        $cols = $pdo->query("
            SELECT COLUMN_NAME
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'committee_reports'
        ")->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('comm_resolution_number', $cols, true)) {
            $pdo->exec("ALTER TABLE committee_reports
                ADD COLUMN comm_resolution_number VARCHAR(100) NULL COMMENT 'Resolution number when comm report adopted'
                AFTER summary_of_findings");
        }
        if (!in_array('comm_proposed_number', $cols, true)) {
            $pdo->exec("ALTER TABLE committee_reports
                ADD COLUMN comm_proposed_number VARCHAR(100) NULL COMMENT 'Proposed number when comm report adopted'
                AFTER comm_resolution_number");
        }
        if (!in_array('comm_adopted_at', $cols, true)) {
            $pdo->exec("ALTER TABLE committee_reports
                ADD COLUMN comm_adopted_at TIMESTAMP NULL COMMENT 'When comm report was marked adopted'
                AFTER comm_proposed_number");
        }
        if (!in_array('comm_adopted_by', $cols, true)) {
            $pdo->exec("ALTER TABLE committee_reports
                ADD COLUMN comm_adopted_by BIGINT NULL COMMENT 'User who adopted the comm report'
                AFTER comm_adopted_at");
        }
    }

    // =========================================================================
    // DOWN
    // =========================================================================

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS comm_resolution_files");
        $pdo->exec("DROP TABLE IF EXISTS comm_committee_assignments");
        $pdo->exec("DROP TABLE IF EXISTS comm_first_reading");
        $pdo->exec("DROP TABLE IF EXISTS comm_inbox_items");

        $names = array_column(self::STATUS_SEEDS, 'name');
        if (!empty($names)) {
            $placeholders = implode(',', array_fill(0, count($names), '?'));
            $pdo->prepare("DELETE FROM document_statuses WHERE name IN ({$placeholders})")
                ->execute(array_values($names));
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function ensureUniqueStatusIndex(PDO $pdo): void
    {
        $row = $pdo->query("
            SELECT COUNT(*) AS cnt
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA  = DATABASE()
              AND TABLE_NAME    = 'document_statuses'
              AND INDEX_NAME    = 'uq_document_statuses_name'
              AND NON_UNIQUE    = 0
        ")->fetch(PDO::FETCH_ASSOC);

        if ((int) ($row['cnt'] ?? 0) === 0) {
            try {
                $pdo->exec("ALTER TABLE document_statuses ADD UNIQUE KEY uq_document_statuses_name (name)");
            } catch (PDOException $e) {
                // Already exists or duplicate data — ignore
            }
        }
    }

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

        $toAdd = array_diff($newValues, $existing);
        if (empty($toAdd)) {
            return;
        }

        $merged   = array_merge($existing, array_values($toAdd));
        $enumList = implode(',', array_map(fn($v) => "'{$v}'", $merged));
        $pdo->exec("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` ENUM({$enumList}) NOT NULL");
    }
}

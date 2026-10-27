<?php

/**
 * Migration 075 — Plenary Communication First Reading Schema
 *
 * Adds the tables and seeds needed for the Plenary side of the Communication
 * First Reading workflow.  The SP Secretary side (comm_first_reading,
 * comm_committee_assignments, comm_resolution_files) was added in migration 074
 * and is left untouched here.
 *
 * NEW TABLES
 * ──────────
 * 1. plenary_comm_first_reading
 *    One row per Communication document that has been processed at Plenary's
 *    own First Reading.  This is distinct from the SP Secretary
 *    comm_first_reading table.  Possible decisions:
 *      • REFER_TO_COMMITTEE  — hand off to the existing Committee workflow.
 *      • APPROVED            — "Approved for Resolution under Other Matters";
 *                             requires resolution_number and optional
 *                             proposed_number; multiple resolution files stored
 *                             via plenary_comm_resolution_files.
 *      • NOTED               — Noted; requires selecting a communication
 *                             category; document shown on the Plenary
 *                             Communications page.
 *
 * 2. plenary_comm_resolution_files
 *    Multi-file upload table for resolution files attached to a
 *    plenary_comm_first_reading (APPROVED decision) or to a Plenary adoption
 *    (adopt-comm action on returned Communications).
 *
 * 3. plenary_comm_adoptions
 *    Records "Adopted Committee Report" action for Communications returned from
 *    a Committee after Plenary referred them.  Contains resolution numbers and
 *    links to resolution files.  Finalises the Communication and routes it to
 *    Finalized Documents > Communications tab.
 *
 * SCHEMA ALTERATIONS
 * ──────────────────
 * 4. Expand document_events.event_type ENUM with new Plenary Comm events.
 *
 * STATUS SEEDS (idempotent via ON DUPLICATE KEY UPDATE)
 * ─────────────────────────────────────────────────────
 * 5. "Plenary Comm: Awaiting First Reading"
 * 6. "Plenary Comm: Noted"
 * 7. "Plenary Comm: Referred to Committee"
 * 8. "Plenary Comm: Finalized"
 *
 * All steps are idempotent / safe to re-run.
 */
class PlenaryCommFirstReadingSchema
{
    private const NEW_EVENT_TYPES = [
        'PLENARY_COMM_FIRST_READING_NOTED',
        'PLENARY_COMM_FIRST_READING_REFERRED',
        'PLENARY_COMM_FIRST_READING_APPROVED',
        'PLENARY_COMM_ADOPTED',
        'PLENARY_COMM_FINALIZED',
    ];

    private const STATUS_SEEDS = [
        [
            'name'        => 'Plenary Comm: Awaiting First Reading',
            'description' => 'Communication document queued for Plenary First Reading.',
            'badge_color' => '#F59E0B',
            'sort_order'  => 120,
        ],
        [
            'name'        => 'Plenary Comm: Noted',
            'description' => 'Communication noted at Plenary First Reading; shown on Plenary Communications page.',
            'badge_color' => '#10B981',
            'sort_order'  => 121,
        ],
        [
            'name'        => 'Plenary Comm: Referred to Committee',
            'description' => 'Communication referred to one or more committees at Plenary First Reading.',
            'badge_color' => '#8B5CF6',
            'sort_order'  => 122,
        ],
        [
            'name'        => 'Plenary Comm: Finalized',
            'description' => 'Communication finalized by Plenary; shown in Finalized Documents > Communications tab.',
            'badge_color' => '#1D4ED8',
            'sort_order'  => 123,
        ],
    ];

    // =========================================================================
    // UP
    // =========================================================================

    public function up(PDO $pdo): void
    {
        // ── 1. plenary_comm_first_reading ─────────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS plenary_comm_first_reading (
                id                        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                document_id               BIGINT UNSIGNED NOT NULL,
                decision                  ENUM(
                                              'REFER_TO_COMMITTEE',
                                              'APPROVED',
                                              'NOTED'
                                          ) NOT NULL,
                reading_date              DATE            NOT NULL,
                remarks                   TEXT            NULL,

                -- NOTED path
                communication_category_id INT UNSIGNED    NULL
                    COMMENT 'FK to communication_categories; required for NOTED decision',

                -- APPROVED path
                proposed_number           VARCHAR(100)    NULL
                    COMMENT 'Optional proposed number for APPROVED decision',
                resolution_number         VARCHAR(100)    NULL
                    COMMENT 'Required resolution number for APPROVED decision',

                -- Finalization metadata
                finalized_at              TIMESTAMP       NULL,
                finalized_by              BIGINT          NULL,

                performed_by              BIGINT          NULL,
                performed_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at                TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at                TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                          ON UPDATE CURRENT_TIMESTAMP,

                PRIMARY KEY (id),
                UNIQUE  KEY uq_pcfr_document_id       (document_id),
                INDEX        idx_pcfr_decision        (decision),
                INDEX        idx_pcfr_performed_by    (performed_by),
                INDEX        idx_pcfr_finalized_by    (finalized_by),

                CONSTRAINT fk_pcfr_document   FOREIGN KEY (document_id)
                    REFERENCES documents(id) ON DELETE RESTRICT,
                CONSTRAINT fk_pcfr_performed  FOREIGN KEY (performed_by)
                    REFERENCES user_accounts(id) ON DELETE SET NULL,
                CONSTRAINT fk_pcfr_finalized  FOREIGN KEY (finalized_by)
                    REFERENCES user_accounts(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── 2. plenary_comm_resolution_files ──────────────────────────────────
        // Stores resolution files for the APPROVED First Reading path AND for
        // the adopt-committee-report action on returned Communications.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS plenary_comm_resolution_files (
                id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                document_id             BIGINT UNSIGNED NOT NULL,
                first_reading_id        BIGINT UNSIGNED NULL
                    COMMENT 'FK to plenary_comm_first_reading.id; set for APPROVED decision files',
                adoption_id             BIGINT UNSIGNED NULL
                    COMMENT 'FK to plenary_comm_adoptions.id; set for adopted-report files',
                original_filename       VARCHAR(500)    NOT NULL,
                stored_filename         VARCHAR(500)    NOT NULL
                    COMMENT 'Safe generated filename on disk',
                file_path               VARCHAR(1000)   NOT NULL,
                mime_type               VARCHAR(200)    NOT NULL,
                file_size               BIGINT UNSIGNED NOT NULL,
                uploaded_by             BIGINT          NULL,
                uploaded_at             TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

                PRIMARY KEY (id),
                INDEX idx_pcrf_document_id       (document_id),
                INDEX idx_pcrf_first_reading_id  (first_reading_id),
                INDEX idx_pcrf_adoption_id       (adoption_id),
                INDEX idx_pcrf_uploaded_by       (uploaded_by),

                CONSTRAINT fk_pcrf_document      FOREIGN KEY (document_id)
                    REFERENCES documents(id) ON DELETE RESTRICT,
                CONSTRAINT fk_pcrf_first_reading FOREIGN KEY (first_reading_id)
                    REFERENCES plenary_comm_first_reading(id) ON DELETE SET NULL,
                CONSTRAINT fk_pcrf_uploaded_by   FOREIGN KEY (uploaded_by)
                    REFERENCES user_accounts(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── 3. plenary_comm_adoptions ─────────────────────────────────────────
        // Records the "Adopted Committee Report" action on a Communication that
        // was referred to Committee at Plenary First Reading and then returned.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS plenary_comm_adoptions (
                id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                document_id             BIGINT UNSIGNED NOT NULL,
                first_reading_id        BIGINT UNSIGNED NOT NULL
                    COMMENT 'FK to plenary_comm_first_reading.id (the REFER_TO_COMMITTEE row)',
                resolution_number       VARCHAR(100)    NOT NULL
                    COMMENT 'Approved Resolution Number (required)',
                proposed_number         VARCHAR(100)    NULL
                    COMMENT 'Optional Proposed Number',
                adopted_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                adopted_by              BIGINT          NULL,
                remarks                 TEXT            NULL,
                created_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                        ON UPDATE CURRENT_TIMESTAMP,

                PRIMARY KEY (id),
                UNIQUE  KEY uq_pca_document_id    (document_id),
                INDEX        idx_pca_first_reading (first_reading_id),
                INDEX        idx_pca_adopted_by    (adopted_by),

                CONSTRAINT fk_pca_document      FOREIGN KEY (document_id)
                    REFERENCES documents(id) ON DELETE RESTRICT,
                CONSTRAINT fk_pca_first_reading FOREIGN KEY (first_reading_id)
                    REFERENCES plenary_comm_first_reading(id) ON DELETE RESTRICT,
                CONSTRAINT fk_pca_adopted_by    FOREIGN KEY (adopted_by)
                    REFERENCES user_accounts(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Now that plenary_comm_adoptions exists, add the deferred FK from
        // plenary_comm_resolution_files.adoption_id:
        // Check if the FK already exists (idempotent)
        $fkRow = $pdo->query("
            SELECT COUNT(*) AS cnt
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA        = DATABASE()
              AND TABLE_NAME          = 'plenary_comm_resolution_files'
              AND CONSTRAINT_NAME     = 'fk_pcrf_adoption'
        ")->fetch(PDO::FETCH_ASSOC);

        if ((int) ($fkRow['cnt'] ?? 0) === 0) {
            try {
                $pdo->exec("
                    ALTER TABLE plenary_comm_resolution_files
                    ADD CONSTRAINT fk_pcrf_adoption
                        FOREIGN KEY (adoption_id)
                        REFERENCES plenary_comm_adoptions(id) ON DELETE SET NULL
                ");
            } catch (PDOException $e) {
                // If the table didn't exist at ALTER time (unlikely) — ignore
            }
        }

        // ── 4. Expand document_events.event_type ENUM ─────────────────────────
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

        // ── 5. Seed document_statuses ─────────────────────────────────────────
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

    // =========================================================================
    // DOWN
    // =========================================================================

    public function down(PDO $pdo): void
    {
        // Drop FK first to avoid constraint errors
        try {
            $pdo->exec("
                ALTER TABLE plenary_comm_resolution_files
                DROP FOREIGN KEY fk_pcrf_adoption
            ");
        } catch (PDOException $e) {
            // May not exist
        }

        $pdo->exec("DROP TABLE IF EXISTS plenary_comm_adoptions");
        $pdo->exec("DROP TABLE IF EXISTS plenary_comm_resolution_files");
        $pdo->exec("DROP TABLE IF EXISTS plenary_comm_first_reading");

        // Remove seeded statuses
        $names = array_column(self::STATUS_SEEDS, 'name');
        if (!empty($names)) {
            $placeholders = implode(',', array_fill(0, count($names), '?'));
            $pdo->prepare("DELETE FROM document_statuses WHERE name IN ({$placeholders})")
                ->execute(array_values($names));
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
                $pdo->exec(
                    "ALTER TABLE document_events MODIFY COLUMN event_type ENUM({$enumList}) NOT NULL"
                );
            }
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function ensureUniqueIndex(PDO $pdo): void
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
                $pdo->exec(
                    "ALTER TABLE document_statuses ADD UNIQUE KEY uq_document_statuses_name (name)"
                );
            } catch (PDOException $e) {
                // Already exists or duplicate data — ignore
            }
        }
    }
}

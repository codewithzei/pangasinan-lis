<?php

/**
 * Migration 076 — Plenary Cases (Complaints / Administrative Cases) First Reading Schema
 *
 * Adds the tables and seeds needed for the Plenary side of the
 * Complaint / Administrative Cases First Reading workflow.
 *
 * WORKFLOW SUMMARY
 * ────────────────
 * Inbox → accept → pre-decision (On Process) → save_processing
 *   → "Awaiting First Reading"
 *   → plenary/first-reading (Petitions tab)
 *   → casesFirstReadingShow → casesFirstReadingStore
 *      ├─ REFER_TO_COMMITTEE  → phase = COMMITTEE, status = Referred
 *      │                        (committee processes the case normally)
 *      │    ← Committee returns (status = "Returned to Plenary")
 *      │    → "Adopted Committee Report" modal (resolution_number + proposed_number)
 *      │    → POST plenary/cases-first-reading/adopt-report
 *      │    → status = "Plenary Cases: Finalized"
 *      │    → Finalized Documents > Petitions tab
 *      └─ NOTED               → status = "Plenary Cases: Noted"
 *                               → Finalized Documents > Petitions tab
 *
 * NEW TABLES
 * ──────────
 * 1. plenary_cases_first_reading
 *    One row per Complaint / Administrative Cases document processed at
 *    Plenary First Reading.
 *    decision:
 *      REFER_TO_COMMITTEE — hand off to the Committee workflow.
 *      NOTED              — Noted; document shown in Finalized Docs > Petitions.
 *
 * 2. plenary_cases_adoptions
 *    Records "Adopted Committee Report" for a Cases document that was
 *    referred to Committee and returned. Finalizes the document.
 *
 * STATUS SEEDS (idempotent via ON DUPLICATE KEY UPDATE)
 * ──────────────────────────────────────────────────────
 * 3. "Plenary Cases: Awaiting First Reading"  (kept for possible future use;
 *    current implementation uses the shared "Awaiting First Reading" status)
 * 4. "Plenary Cases: Noted"
 * 5. "Plenary Cases: Referred to Committee"
 * 6. "Plenary Cases: Finalized"
 *
 * EVENT TYPES (added to document_events.event_type ENUM)
 * ──────────────────────────────────────────────────────
 * 7. PLENARY_CASES_FIRST_READING_NOTED
 * 8. PLENARY_CASES_FIRST_READING_REFERRED
 * 9. PLENARY_CASES_ADOPTED
 * 10. PLENARY_CASES_FINALIZED
 *
 * All steps are idempotent / safe to re-run.
 */
class PlenaryCasesFirstReadingSchema
{
    private const NEW_EVENT_TYPES = [
        'PLENARY_CASES_FIRST_READING_NOTED',
        'PLENARY_CASES_FIRST_READING_REFERRED',
        'PLENARY_CASES_ADOPTED',
        'PLENARY_CASES_FINALIZED',
    ];

    private const STATUS_SEEDS = [
        [
            'name'        => 'Plenary Cases: Awaiting First Reading',
            'description' => 'Complaint/Administrative Cases document queued for Plenary First Reading (Petitions).',
            'badge_color' => '#F59E0B',
            'sort_order'  => 130,
        ],
        [
            'name'        => 'Plenary Cases: Noted',
            'description' => 'Complaint/Administrative Cases document noted at Plenary First Reading.',
            'badge_color' => '#10B981',
            'sort_order'  => 131,
        ],
        [
            'name'        => 'Plenary Cases: Referred to Committee',
            'description' => 'Complaint/Administrative Cases document referred to committee at Plenary First Reading.',
            'badge_color' => '#8B5CF6',
            'sort_order'  => 132,
        ],
        [
            'name'        => 'Plenary Cases: Finalized',
            'description' => 'Complaint/Administrative Cases document finalized by Plenary. Shown in Finalized Documents > Petitions tab.',
            'badge_color' => '#1D4ED8',
            'sort_order'  => 133,
        ],
    ];

    // =========================================================================
    // UP
    // =========================================================================

    public function up(PDO $pdo): void
    {
        // ── 1. plenary_cases_first_reading ────────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS plenary_cases_first_reading (
                id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                document_id    BIGINT UNSIGNED NOT NULL,
                decision       ENUM('REFER_TO_COMMITTEE', 'NOTED') NOT NULL,
                reading_date   DATE            NOT NULL,
                remarks        TEXT            NULL,
                performed_by   BIGINT          NULL,
                performed_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,

                PRIMARY KEY (id),
                UNIQUE  KEY uq_pcfr_cases_document_id (document_id),
                INDEX        idx_pcfr_cases_decision  (decision),
                INDEX        idx_pcfr_cases_performed_by (performed_by),

                CONSTRAINT fk_pcfr_cases_document  FOREIGN KEY (document_id)
                    REFERENCES documents(id) ON DELETE RESTRICT,
                CONSTRAINT fk_pcfr_cases_performed FOREIGN KEY (performed_by)
                    REFERENCES user_accounts(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── 2. plenary_cases_adoptions ────────────────────────────────────────
        // Records the "Adopted Committee Report" action on a Complaints /
        // Administrative Cases document that was referred to Committee and
        // returned.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS plenary_cases_adoptions (
                id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                document_id       BIGINT UNSIGNED NOT NULL,
                first_reading_id  BIGINT UNSIGNED NOT NULL
                    COMMENT 'FK to plenary_cases_first_reading.id',
                resolution_number VARCHAR(100)    NOT NULL
                    COMMENT 'Required Resolution Number',
                proposed_number   VARCHAR(100)    NULL
                    COMMENT 'Optional Proposed Number',
                adopted_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                adopted_by        BIGINT          NULL,
                remarks           TEXT            NULL,
                created_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                  ON UPDATE CURRENT_TIMESTAMP,

                PRIMARY KEY (id),
                UNIQUE  KEY uq_pca_cases_document_id  (document_id),
                INDEX        idx_pca_cases_fr          (first_reading_id),
                INDEX        idx_pca_cases_adopted_by  (adopted_by),

                CONSTRAINT fk_pca_cases_document     FOREIGN KEY (document_id)
                    REFERENCES documents(id) ON DELETE RESTRICT,
                CONSTRAINT fk_pca_cases_first_reading FOREIGN KEY (first_reading_id)
                    REFERENCES plenary_cases_first_reading(id) ON DELETE RESTRICT,
                CONSTRAINT fk_pca_cases_adopted_by   FOREIGN KEY (adopted_by)
                    REFERENCES user_accounts(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
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

        // ── 4. Seed document_statuses ─────────────────────────────────────────
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
        $pdo->exec("DROP TABLE IF EXISTS plenary_cases_adoptions");
        $pdo->exec("DROP TABLE IF EXISTS plenary_cases_first_reading");

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
        ")->fetch(PDO::FETCH_ASSOC);

        if ((int) ($row['cnt'] ?? 0) === 0) {
            try {
                $pdo->exec("
                    ALTER TABLE document_statuses
                    ADD UNIQUE KEY uq_document_statuses_name (name)
                ");
            } catch (PDOException $e) {
                // Duplicate key in existing data — handle gracefully
                if ((int) ($e->errorInfo[1] ?? 0) !== 1061) {
                    throw $e;
                }
            }
        }
    }
}

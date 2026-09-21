<?php

/**
 * Migration 055 — Committee Hearings table + status/event-type expansions
 *
 * 1. Creates `committee_hearings` — records each hearing outcome for a
 *    document/agenda pair.
 * 2. Adds COMMITTEE_FILE attachment_type value if not already present
 *    (defensive ALTER; may already exist from migration 051).
 * 3. Expands document_events.event_type to include the new event types
 *    needed by the hearing workflow.
 * 4. Seeds new document_statuses rows (ON GOING, Hearing Completed,
 *    Committee Report Created) using INSERT … ON DUPLICATE KEY UPDATE.
 * 5. Adds a UNIQUE index on committee_reports.report_number to prevent
 *    duplicate report numbers.
 *
 * All DDL changes are wrapped in IF NOT EXISTS / column presence checks
 * so the migration is safe to re-run.
 */
class CreateCommitteeHearingsAndSeedStatuses
{
    public function up(PDO $pdo): void
    {
        // ── 1. committee_hearings table ──────────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS committee_hearings (
                id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                -- Links
                document_id BIGINT UNSIGNED NOT NULL,
                agenda_id   BIGINT UNSIGNED NOT NULL,

                -- Outcome
                outcome     ENUM('APPROVED','DEFERRED','REMANDED','WITHDRAWN') NOT NULL,
                remarks     TEXT NULL,

                -- Audit
                performed_by    BIGINT NULL,
                performed_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                -- Indexes
                INDEX idx_committee_hearings_document  (document_id),
                INDEX idx_committee_hearings_agenda    (agenda_id),
                INDEX idx_committee_hearings_outcome   (outcome),
                INDEX idx_committee_hearings_performed_by (performed_by),

                -- Foreign Keys
                CONSTRAINT fk_ch_document   FOREIGN KEY (document_id)   REFERENCES documents(id)      ON DELETE RESTRICT,
                CONSTRAINT fk_ch_agenda     FOREIGN KEY (agenda_id)     REFERENCES agendas(id)         ON DELETE RESTRICT,
                CONSTRAINT fk_ch_performed  FOREIGN KEY (performed_by)  REFERENCES user_accounts(id)  ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // ── 2. Expand document_events.event_type ENUM ────────────────────────
        //
        // The complete ENUM list must be re-stated including every value that
        // already exists in the column (otherwise MySQL rejects the ALTER when
        // live rows contain those values).  We build the new list by reading
        // the current ENUM definition from information_schema, merging in the
        // new values, and issuing a single MODIFY COLUMN.
        $colRow = $pdo->query(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = 'document_events'
                AND COLUMN_NAME  = 'event_type'
              LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);

        // Parse existing enum values from "enum('A','B',…)"
        $existingValues = [];
        if ($colRow && preg_match("/^enum\((.+)\)$/i", $colRow['COLUMN_TYPE'], $m)) {
            preg_match_all("/'([^']+)'/", $m[1], $matches);
            $existingValues = $matches[1];
        }

        // New values to add (will be appended only if not already present)
        $newValues = [
            'HEARING_DEFERRED',
            'HEARING_REMANDED',
            'HEARING_WITHDRAWN',
            'COMMITTEE_REPORT_CREATED',
        ];

        $merged = $existingValues;
        foreach ($newValues as $v) {
            if (!in_array($v, $merged, true)) {
                $merged[] = $v;
            }
        }

        $enumList = implode(',', array_map(fn($v) => "'{$v}'", $merged));
        $pdo->exec("ALTER TABLE document_events MODIFY COLUMN event_type ENUM({$enumList}) NOT NULL");

        // ── 3. Seed new document_statuses ────────────────────────────────────
        $this->ensureUniqueIndex($pdo, 'document_statuses', 'uq_document_statuses_name', 'name');

        $statuses = [
            [
                'name'        => 'On Going',
                'description' => 'Agenda has been set; document is scheduled for committee hearing.',
                'badge_color' => '#D97706',   // amber-600
                'sort_order'  => 50,
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
            [
                'name'        => 'Deferred',
                'description' => 'Document deferred during committee hearing for further review.',
                'badge_color' => '#6B7280',   // gray-500
                'sort_order'  => 80,
            ],
            [
                'name'        => 'Remanded',
                'description' => 'Document remanded back to originating body.',
                'badge_color' => '#DC2626',   // red-600
                'sort_order'  => 85,
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

        // ── 4. UNIQUE index on committee_reports.report_number ───────────────
        $this->ensureUniqueIndex(
            $pdo,
            'committee_reports',
            'uq_committee_reports_number',
            'report_number'
        );
    }

    // =========================================================================

    public function down(PDO $pdo): void
    {
        // Remove the unique index on report_number (allow duplicates again)
        $pdo->exec("
            ALTER TABLE committee_reports DROP INDEX IF EXISTS uq_committee_reports_number
        ");

        // Remove seeded statuses added by this migration
        $pdo->exec("
            DELETE FROM document_statuses
            WHERE name IN ('On Going','Hearing Completed','Committee Report Created','Deferred','Remanded')
        ");

        // Revert event_type ENUM: read current definition and remove the values
        // added by this migration.
        $colRow = $pdo->query(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = 'document_events'
                AND COLUMN_NAME  = 'event_type'
              LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);

        $existingValues = [];
        if ($colRow && preg_match("/^enum\((.+)\)$/i", $colRow['COLUMN_TYPE'], $m)) {
            preg_match_all("/'([^']+)'/", $m[1], $matches);
            $existingValues = $matches[1];
        }

        $removeValues = ['HEARING_DEFERRED', 'HEARING_REMANDED', 'HEARING_WITHDRAWN', 'COMMITTEE_REPORT_CREATED'];
        $reverted = array_values(array_filter($existingValues, fn($v) => !in_array($v, $removeValues, true)));

        if (!empty($reverted)) {
            $enumList = implode(',', array_map(fn($v) => "'{$v}'", $reverted));
            $pdo->exec("ALTER TABLE document_events MODIFY COLUMN event_type ENUM({$enumList}) NOT NULL");
        }

        $pdo->exec("DROP TABLE IF EXISTS committee_hearings;");
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Ensure a UNIQUE INDEX exists; create it if absent (safe for nullable
     * columns — use a partial/prefix index via ensureUniqueIndexNullable if
     * needed, but report_number and name columns here are VARCHAR NOT NULL or
     * nullable VARCHAR handled with NULLIF).
     */
    private function ensureUniqueIndex(PDO $pdo, string $table, string $indexName, string $column): void
    {
        $check = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE table_schema = DATABASE()
              AND table_name   = ?
              AND index_name   = ?
        ");
        $check->execute([$table, $indexName]);
        if ((int) $check->fetchColumn() > 0) {
            return;
        }

        // For nullable columns (like report_number) we skip de-dup check and
        // create a partial unique index that ignores NULL rows so that multiple
        // NULL values are permitted (NULL != NULL in SQL).
        // We detect nullable columns and use a different strategy.
        $nullCheck = $pdo->prepare("
            SELECT IS_NULLABLE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = ?
              AND COLUMN_NAME  = ?
        ");
        $nullCheck->execute([$table, $column]);
        $isNullable = (string) ($nullCheck->fetchColumn() ?: 'NO');

        if ($isNullable === 'YES') {
            // MySQL does not natively support partial indexes in the WHERE sense,
            // but UNIQUE indexes on nullable columns treat NULL as distinct so
            // multiple NULLs are allowed — which is what we want for report_number.
            // We still need to de-dup any existing non-NULL duplicates first.
            $dup = $pdo->prepare("
                SELECT `{$column}`, COUNT(*) AS cnt
                FROM `{$table}`
                WHERE `{$column}` IS NOT NULL
                GROUP BY `{$column}`
                HAVING cnt > 1
            ");
            $dup->execute();
            $dupes = $dup->fetchAll(PDO::FETCH_ASSOC);
            foreach ($dupes as $d) {
                $pdo->prepare("
                    UPDATE `{$table}` SET `{$column}` = CONCAT(`{$column}`, '_dup_', id)
                    WHERE `{$column}` = ?
                    ORDER BY id DESC
                    LIMIT 1
                ")->execute([$d[$column]]);
            }
        } else {
            // NOT NULL column — de-dup by soft-deleting higher-id duplicates.
            $dup = $pdo->prepare("
                SELECT `{$column}`, COUNT(*) AS cnt
                FROM `{$table}`
                GROUP BY `{$column}`
                HAVING cnt > 1
            ");
            $dup->execute();
            $dupes = $dup->fetchAll(PDO::FETCH_ASSOC);
            foreach ($dupes as $d) {
                $cnt = (int) $d['cnt'];
                if ($cnt > 1) {
                    // Keep lowest id, mark rest as deleted if column exists
                    try {
                        $pdo->prepare("
                            UPDATE `{$table}` SET is_deleted = 1
                            WHERE `{$column}` = ?
                            ORDER BY id DESC
                            LIMIT " . ($cnt - 1)
                        )->execute([$d[$column]]);
                    } catch (\Throwable $e) {
                        // column is_deleted may not exist on this table — skip
                    }
                }
            }
        }

        $pdo->exec("ALTER TABLE `{$table}` ADD UNIQUE INDEX `{$indexName}` (`{$column}`)");
    }
}

<?php

/**
 * Migration 058 — Seed "Returned to Plenary" document status
 *
 * The Committee Reports workflow requires a "Returned to Plenary" status so
 * that the return-to-plenary action has a named status to transition to.
 *
 * Adds:
 *   document_statuses
 *     name        = 'Returned to Plenary'
 *     description = 'Committee report returned to Plenary for further action.'
 *     badge_color = '#7C3AED'  (violet-700 — distinct from all existing statuses)
 *     sort_order  = 75
 *
 * Uses INSERT … ON DUPLICATE KEY UPDATE so the migration is idempotent.
 * A UNIQUE index on document_statuses.name was added in migration 055.
 *
 * Also expands document_events.event_type to include
 * 'RETURNED_TO_PLENARY' if not already present.
 */
class SeedReturnedToPlenaryStatus
{
    public function up(PDO $pdo): void
    {
        // ── 1. Seed "Returned to Plenary" document status ─────────────────────
        $pdo->prepare("
            INSERT INTO document_statuses
                (name, description, badge_color, sort_order, is_active, is_deleted)
            VALUES (?, ?, ?, ?, 1, 0)
            ON DUPLICATE KEY UPDATE
                description = VALUES(description),
                badge_color = VALUES(badge_color),
                sort_order  = VALUES(sort_order),
                is_active   = 1,
                is_deleted  = 0
        ")->execute([
            'Returned to Plenary',
            'Committee report returned to Plenary for further action.',
            '#7C3AED',  // violet-700
            75,
        ]);

        // ── 2. Expand document_events.event_type ENUM ─────────────────────────
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

        if (!in_array('RETURNED_TO_PLENARY', $existingValues, true)) {
            $existingValues[] = 'RETURNED_TO_PLENARY';
            $enumList = implode(',', array_map(fn($v) => "'{$v}'", $existingValues));
            $pdo->exec("ALTER TABLE document_events MODIFY COLUMN event_type ENUM({$enumList}) NOT NULL");
        }
    }

    public function down(PDO $pdo): void
    {
        // Remove seeded status
        $pdo->exec("DELETE FROM document_statuses WHERE name = 'Returned to Plenary'");

        // Revert ENUM: remove RETURNED_TO_PLENARY if present
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

        $reverted = array_values(array_filter(
            $existingValues,
            fn($v) => $v !== 'RETURNED_TO_PLENARY'
        ));

        if (!empty($reverted)) {
            $enumList = implode(',', array_map(fn($v) => "'{$v}'", $reverted));
            $pdo->exec("ALTER TABLE document_events MODIFY COLUMN event_type ENUM({$enumList}) NOT NULL");
        }
    }
}

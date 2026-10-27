<?php

/**
 * Migration 069 — Plenary Inbox Outcome Tabs
 *
 * The Plenary Inbox now exposes status-based tabs for the document's
 * pre-decision outcome (Deferred, Lay on the Table, Noted, Remanded,
 * Withdrawn).  Resuming a Deferred document requires a compliance update
 * (remarks + one or more uploaded files), which is recorded as its own
 * document event.
 *
 * 1. Expand document_events.event_type ENUM with PLENARY_COMPLIANCE_SUBMITTED.
 *    Strategy: dynamically read the current ENUM, merge the new value, re-apply.
 *
 * 2. Ensure the six pre-decision statuses exist (idempotent) so the outcome
 *    tabs always have a status row to filter against — same guarantee as
 *    migration 065, re-applied here so a fresh install that skipped 065's
 *    statuses still resolves correctly.
 *
 * All steps are guarded so the migration is safe to re-run.
 */
class PlenaryInboxOutcomeTabs
{
    private const NEW_EVENT_TYPE = 'PLENARY_COMPLIANCE_SUBMITTED';

    private const OUTCOME_STATUSES = [
        ['name' => 'Deferred',         'badge_color' => '#F59E0B', 'sort_order' => 81],
        ['name' => 'Lay on the Table', 'badge_color' => '#6B7280', 'sort_order' => 82],
        ['name' => 'Noted',            'badge_color' => '#3B82F6', 'sort_order' => 83],
        ['name' => 'Remanded',         'badge_color' => '#EF4444', 'sort_order' => 84],
        ['name' => 'On Process',       'badge_color' => '#8B5CF6', 'sort_order' => 85],
        ['name' => 'Withdrawn',        'badge_color' => '#6B7280', 'sort_order' => 86],
    ];

    public function up(PDO $pdo): void
    {
        // ── 1. Expand document_events.event_type ENUM ─────────────────────
        $colRow = $pdo->query("
            SELECT COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'document_events'
              AND COLUMN_NAME  = 'event_type'
            LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);

        $existingValues = [];
        if ($colRow && preg_match('/^enum\((.+)\)$/i', $colRow['COLUMN_TYPE'], $m)) {
            preg_match_all("/'([^']+)'/", $m[1], $matches);
            $existingValues = $matches[1];
        }

        if (!in_array(self::NEW_EVENT_TYPE, $existingValues, true)) {
            $merged   = array_merge($existingValues, [self::NEW_EVENT_TYPE]);
            $enumList = implode(',', array_map(fn($v) => "'{$v}'", $merged));
            $pdo->exec(
                "ALTER TABLE document_events
                 MODIFY COLUMN event_type ENUM({$enumList}) NOT NULL"
            );
        }

        // ── 2. Ensure the pre-decision/outcome statuses exist ─────────────
        $idxCheck = $pdo->query("
            SHOW INDEX FROM document_statuses WHERE Key_name = 'uq_document_statuses_name'
        ")->fetchAll();

        if (empty($idxCheck)) {
            $anyUniq = $pdo->query("
                SHOW INDEX FROM document_statuses
                 WHERE Column_name = 'name' AND Non_unique = 0
            ")->fetchAll();
            if (empty($anyUniq)) {
                $pdo->exec("
                    ALTER TABLE document_statuses
                    ADD UNIQUE KEY uq_document_statuses_name (name)
                ");
            }
        }

        $upsert = $pdo->prepare("
            INSERT INTO document_statuses
                (name, description, badge_color, sort_order, is_active, is_deleted)
            VALUES (?, ?, ?, ?, 1, 0)
            ON DUPLICATE KEY UPDATE
                is_active  = 1,
                is_deleted = 0
        ");

        foreach (self::OUTCOME_STATUSES as $s) {
            $upsert->execute([
                $s['name'],
                'Plenary pre-decision outcome: ' . $s['name'],
                $s['badge_color'],
                $s['sort_order'],
            ]);
        }
    }

    public function down(PDO $pdo): void
    {
        // ── 1. Remove the Plenary compliance event type from the ENUM ─────
        $colRow = $pdo->query("
            SELECT COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'document_events'
              AND COLUMN_NAME  = 'event_type'
            LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);

        if ($colRow) {
            preg_match_all("/'([^']+)'/", $colRow['COLUMN_TYPE'], $matches);
            $reverted = array_values(array_filter(
                $matches[1],
                fn($v) => $v !== self::NEW_EVENT_TYPE
            ));
            if (!empty($reverted)) {
                $enumList = implode(',', array_map(fn($v) => "'{$v}'", $reverted));
                $pdo->exec(
                    "ALTER TABLE document_events
                     MODIFY COLUMN event_type ENUM({$enumList}) NOT NULL"
                );
            }
        }
    }
}

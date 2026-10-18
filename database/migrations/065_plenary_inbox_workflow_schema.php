<?php

/**
 * Migration 065 — Plenary Inbox Workflow Schema Additions
 *
 * 1. Add `remarks` column to plenary_document_details for pre-decision remarks.
 *
 * 2. Expand document_events.event_type ENUM with Plenary-specific events:
 *    PLENARY_ACCEPTED, PLENARY_PRE_DECISION, PLENARY_PROCESSING_SAVED,
 *    PLENARY_RETURNED_TO_ADMIN.
 *    Strategy: dynamically read the current ENUM, merge new values, re-apply.
 *
 * 3. Add PLENARY_FILE to document_attachments.attachment_type ENUM.
 *    Cumulative from migration 063 (SPSEC_FILE).
 *
 * 4. Seed the six pre-decision statuses used by the Plenary pre-decision form:
 *    "Deferred", "Lay on the Table", "Noted", "Remanded",
 *    "Under Processing", "Withdrawn".
 *    ("Under Processing" and "Withdrawn" may already exist — ON DUPLICATE KEY
 *    UPDATE is idempotent.)
 *
 * All steps are guarded so the migration is safe to re-run.
 */
class PlenaryInboxWorkflowSchema
{
    private const NEW_EVENT_TYPES = [
        'PLENARY_ACCEPTED',
        'PLENARY_PRE_DECISION',
        'PLENARY_PROCESSING_SAVED',
        'PLENARY_RETURNED_TO_ADMIN',
    ];

    private const PREDECISION_STATUSES = [
        [
            'name'        => 'Deferred',
            'description' => 'Document deferred at Plenary.',
            'badge_color' => '#F59E0B',
            'sort_order'  => 81,
        ],
        [
            'name'        => 'Lay on the Table',
            'description' => 'Document laid on the table at Plenary.',
            'badge_color' => '#6B7280',
            'sort_order'  => 82,
        ],
        [
            'name'        => 'Noted',
            'description' => 'Document noted at Plenary.',
            'badge_color' => '#3B82F6',
            'sort_order'  => 10,   // may already exist — UPDATE will correct badge_color only if different
        ],
        [
            'name'        => 'Remanded',
            'description' => 'Document remanded at Plenary.',
            'badge_color' => '#EF4444',
            'sort_order'  => 83,
        ],
        [
            'name'        => 'Under Processing',
            'description' => 'Document is under processing at Plenary.',
            'badge_color' => '#8B5CF6',
            'sort_order'  => 20,   // may already exist
        ],
        [
            'name'        => 'Withdrawn',
            'description' => 'Document withdrawn from the legislative workflow.',
            'badge_color' => '#6B7280',
            'sort_order'  => 40,   // may already exist
        ],
    ];

    public function up(PDO $pdo): void
    {
        // ── 1. Add remarks column to plenary_document_details ─────────────
        $existing = $pdo->query(
            "SHOW COLUMNS FROM plenary_document_details LIKE 'remarks'"
        )->fetchAll();

        if (empty($existing)) {
            $pdo->exec("
                ALTER TABLE plenary_document_details
                ADD COLUMN remarks TEXT NULL
                    AFTER additional_details
            ");
        }

        // ── 2. Expand document_events.event_type ENUM ─────────────────────
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

        // ── 3. Add PLENARY_FILE to document_attachments.attachment_type ───
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

        if (!in_array('PLENARY_FILE', $attValues, true)) {
            // Insert PLENARY_FILE before OTHER for readability
            $otherIdx = array_search('OTHER', $attValues, true);
            if ($otherIdx !== false) {
                array_splice($attValues, $otherIdx, 0, ['PLENARY_FILE']);
            } else {
                $attValues[] = 'PLENARY_FILE';
            }
            $enumList = implode(',', array_map(fn($v) => "'{$v}'", $attValues));
            $pdo->exec(
                "ALTER TABLE document_attachments
                 MODIFY COLUMN attachment_type ENUM({$enumList}) NOT NULL DEFAULT 'OTHER'"
            );
        }

        // ── 4. Seed pre-decision statuses ─────────────────────────────────
        // Ensure unique index exists (migration 054 adds it for document_statuses).
        $idxCheck = $pdo->query("
            SHOW INDEX FROM document_statuses WHERE Key_name = 'uq_document_statuses_name'
        ")->fetchAll();

        if (empty($idxCheck)) {
            // Check for any unique index on name before adding
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
                description = VALUES(description),
                badge_color = VALUES(badge_color),
                sort_order  = VALUES(sort_order),
                is_active   = 1,
                is_deleted  = 0
        ");

        foreach (self::PREDECISION_STATUSES as $s) {
            $upsert->execute([
                $s['name'],
                $s['description'],
                $s['badge_color'],
                $s['sort_order'],
            ]);
        }
    }

    public function down(PDO $pdo): void
    {
        // ── 1. Remove remarks column ──────────────────────────────────────
        $existing = $pdo->query(
            "SHOW COLUMNS FROM plenary_document_details LIKE 'remarks'"
        )->fetchAll();

        if (!empty($existing)) {
            $pdo->exec("
                ALTER TABLE plenary_document_details
                DROP COLUMN remarks
            ");
        }

        // ── 2. Remove Plenary event types from ENUM ───────────────────────
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
            $reverted = array_values(array_diff($matches[1], self::NEW_EVENT_TYPES));
            if (!empty($reverted)) {
                $enumList = implode(',', array_map(fn($v) => "'{$v}'", $reverted));
                $pdo->exec(
                    "ALTER TABLE document_events
                     MODIFY COLUMN event_type ENUM({$enumList}) NOT NULL"
                );
            }
        }

        // ── 3. Remove PLENARY_FILE from attachment_type ───────────────────
        $pdo->exec("
            UPDATE document_attachments
            SET attachment_type = 'OTHER'
            WHERE attachment_type = 'PLENARY_FILE'
        ");

        $attRow = $pdo->query("
            SELECT COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'document_attachments'
              AND COLUMN_NAME  = 'attachment_type'
            LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);

        if ($attRow) {
            preg_match_all("/'([^']+)'/", $attRow['COLUMN_TYPE'], $matches);
            $reverted = array_values(array_filter($matches[1], fn($v) => $v !== 'PLENARY_FILE'));
            if (!empty($reverted)) {
                $enumList = implode(',', array_map(fn($v) => "'{$v}'", $reverted));
                $pdo->exec(
                    "ALTER TABLE document_attachments
                     MODIFY COLUMN attachment_type ENUM({$enumList}) NOT NULL DEFAULT 'OTHER'"
                );
            }
        }

        // ── 4. Remove seeded statuses that are Plenary-specific ───────────
        //    Only remove "Deferred", "Lay on the Table", "Remanded" — the others
        //    ("Noted", "Under Processing", "Withdrawn") may have been seeded earlier.
        $pdo->exec("
            DELETE FROM document_statuses
            WHERE name IN ('Deferred', 'Lay on the Table', 'Remanded')
              AND is_deleted = 0
        ");
    }
}

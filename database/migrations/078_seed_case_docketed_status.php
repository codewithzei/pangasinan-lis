<?php

/**
 * Migration 078 — Seed the "Case Docketed" document status
 *
 * When the Committee selects "Proceed to Cases" for a Complaint or an
 * Administrative Cases document, `CommitteeCasesController::store()` dockets
 * the document and advances it out of the Committee Inbox by moving its
 * `documents.current_status_id` to "Case Docketed".
 *
 * The status row was seeded directly in some environments without a tracked
 * migration file, so `migrate fresh` would lose it and the docketing step would
 * have to fall back to the document's previous status. This migration makes the
 * row reproducible everywhere.
 *
 * Strategy: INSERT ... ON DUPLICATE KEY UPDATE on the unique index over
 * `document_statuses.name`, exactly as migration 072 does. Safe to run
 * repeatedly; the existing row id is preserved and no duplicate rows are
 * created.
 */
class SeedCaseDocketedStatus
{
    public function up(PDO $pdo): void
    {
        $this->ensureUniqueIndex($pdo, 'document_statuses', 'uq_document_statuses_name', 'name');

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

        $upsert->execute([
            'Case Docketed',
            'Document has been docketed as a committee case. Removed from Committee Inbox.',
            '#7C3AED',
            55,
        ]);
    }

    public function down(PDO $pdo): void
    {
        // This row is created by this migration only.
        $pdo->exec("DELETE FROM document_statuses WHERE name = 'Case Docketed'");
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /**
     * Add a UNIQUE INDEX on $column in $table if it does not already exist, so
     * the ON DUPLICATE KEY UPDATE path works even on databases created without
     * the constraint.
     */
    private function ensureUniqueIndex(PDO $pdo, string $table, string $indexName, string $column): void
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE table_schema = DATABASE()
              AND table_name   = ?
              AND index_name   = ?
        ");
        $stmt->execute([$table, $indexName]);
        if ((int) $stmt->fetchColumn() > 0) {
            return; // already exists
        }

        // Check there are no existing duplicates before adding the constraint.
        $dup = $pdo->prepare("
            SELECT name, COUNT(*) AS cnt
            FROM {$table}
            GROUP BY name
            HAVING cnt > 1
        ");
        $dup->execute();
        foreach ($dup->fetchAll(PDO::FETCH_ASSOC) as $d) {
            // Keep the lowest id, soft-delete the rest.
            $pdo->prepare("
                UPDATE {$table} SET is_deleted = 1
                WHERE name = ?
                ORDER BY id DESC
                LIMIT " . ((int) $d['cnt'] - 1)
            )->execute([$d['name']]);
        }

        $pdo->exec("ALTER TABLE `{$table}` ADD UNIQUE INDEX `{$indexName}` (`{$column}`)");
    }
}

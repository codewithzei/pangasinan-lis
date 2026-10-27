<?php

/**
 * Migration 072 — Seed the second-review opinion statuses
 *
 * The Committee Opinion → Compliance workflow moves an endorsement through two
 * statuses that were never seeded into `opinion_statuses`:
 *
 *   - "For Second Reading" — set on endorsement #1 once an Unfavorable opinion
 *     has been submitted together with its Compliance documents. It marks the
 *     opinion as awaiting the second review of the same office.
 *   - "Resolve"            — set on endorsement #2 when the second review is
 *     also Unfavorable. The endorsement cycle has produced no favorable
 *     opinion, so the cycle must go through the existing resolution workflow
 *     (`committee_opinion_resolutions`) instead of another
 *     Compliance → Second Reading cycle.
 *
 * Strategy: INSERT … ON DUPLICATE KEY UPDATE on the unique index over
 * `opinion_statuses.name`, exactly as migration 054 does. Safe to run
 * repeatedly; the existing row id is preserved and no duplicate rows are
 * created. Nothing else writes to this table during a submission.
 */
class SeedSecondReadingAndResolveStatuses
{
    public function up(PDO $pdo): void
    {
        $this->ensureUniqueIndex($pdo, 'opinion_statuses', 'uq_opinion_statuses_name', 'name');

        $statuses = [
            [
                'name'        => 'For Second Reading',
                'description' => 'Unfavorable opinion with Compliance submitted; awaiting the second review.',
                'sort_order'  => 45,
            ],
            [
                'name'        => 'Resolve',
                'description' => 'Second opinion was Unfavorable; the cycle must be resolved by the committee.',
                'sort_order'  => 60,
            ],
        ];

        $upsert = $pdo->prepare("
            INSERT INTO opinion_statuses
                (name, description, sort_order, is_active, is_deleted)
            VALUES (?, ?, ?, 1, 0)
            ON DUPLICATE KEY UPDATE
                description = VALUES(description),
                sort_order  = VALUES(sort_order),
                is_active   = 1,
                is_deleted  = 0
        ");

        foreach ($statuses as $s) {
            $upsert->execute([$s['name'], $s['description'], $s['sort_order']]);
        }
    }

    public function down(PDO $pdo): void
    {
        // These two rows are created by this migration only.
        $pdo->exec("DELETE FROM opinion_statuses WHERE name IN ('For Second Reading', 'Resolve')");
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

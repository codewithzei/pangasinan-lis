<?php

/**
 * Migration 073 — Normalise the second-review opinion statuses
 *
 * The Committee Opinion → Compliance workflow moves an endorsement through two
 * `opinion_statuses` rows:
 *
 *   - "For Second Endorsement" — endorsement #1 answered Unfavorable together
 *     with its Compliance documents. The opinion is waiting for the second
 *     review of the same office.
 *   - "Resolve"                — endorsement #2 answered Unfavorable as well.
 *     The cycle produced no favorable opinion and must go through the existing
 *     resolution workflow; another Compliance → Second Endorsement cycle is not
 *     allowed.
 *
 * Migration 072 seeded a "For Second Reading" row instead of using the existing
 * "For Second Endorsement" row (id 4, created by OpinionStatusSeeder), so an
 * Unfavorable opinion never reached the state the review screen — and
 * CommitteeReferredController::secondEndorseStore() — expects, and could not be
 * submitted again. This migration:
 *
 *   1. guarantees both required rows exist (idempotent upsert on the unique
 *      index over `name` — no duplicates, and nothing is ever written here by a
 *      submission),
 *   2. repoints endorsements still stuck on the superseded "For Second Reading"
 *      row so they become reviewable again,
 *   3. retires that superseded row so it cannot be selected any more.
 *
 * Not idempotent-unsafe: running it repeatedly is a no-op after the first pass.
 * `down()` restores the retired row; the data repair in step 2 is one-way.
 */
class NormalizeSecondEndorsementOpinionStatuses
{
    public function up(PDO $pdo): void
    {
        $this->ensureUniqueIndex($pdo, 'opinion_statuses', 'uq_opinion_statuses_name', 'name');

        // 1. Required workflow statuses, resolved by name. `sort_order` keeps
        //    them in workflow order without colliding with the seeded rows.
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
        $upsert->execute([
            'For Second Endorsement',
            'Unfavorable opinion with Compliance submitted; awaiting the second review.',
            4,
        ]);
        $upsert->execute([
            'Resolve',
            'Second opinion was Unfavorable; the cycle must be resolved by the committee.',
            60,
        ]);

        $targetId = (int) $pdo->query(
            "SELECT id FROM opinion_statuses WHERE name = 'For Second Endorsement' LIMIT 1"
        )->fetchColumn();

        $legacyStmt = $pdo->prepare("SELECT id FROM opinion_statuses WHERE name = 'For Second Reading' LIMIT 1");
        $legacyStmt->execute();
        $legacyId = (int) ($legacyStmt->fetchColumn() ?: 0);

        // 2 + 3. Move endorsements onto the status the workflow now owns, then
        //        retire the superseded row. Soft-delete keeps history intact.
        if ($legacyId > 0 && $targetId > 0 && $legacyId !== $targetId) {
            $pdo->prepare("
                UPDATE committee_cycle_endorsements
                SET opinion_status_id = ?
                WHERE opinion_status_id = ?
            ")->execute([$targetId, $legacyId]);

            $pdo->prepare("
                UPDATE opinion_statuses
                SET is_active = 0, is_deleted = 1
                WHERE id = ?
            ")->execute([$legacyId]);
        }
    }

    public function down(PDO $pdo): void
    {
        // Only the retirement is reversible; endorsements repointed in up()
        // keep the status that now owns the workflow step.
        $pdo->prepare("
            UPDATE opinion_statuses
            SET is_active = 1, is_deleted = 0
            WHERE name = 'For Second Reading'
        ")->execute();
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /**
     * Add a UNIQUE INDEX on $column in $table if it does not already exist, so
     * the ON DUPLICATE KEY UPDATE path works even on databases created without
     * the constraint. Mirrors migration 072.
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

<?php

/**
 * Migration 054 — Seed committee workflow statuses
 *
 * Ensures the following rows exist and are active (is_active=1, is_deleted=0).
 *
 * Strategy: INSERT … ON DUPLICATE KEY UPDATE
 *   - Uses the unique index on `name` so the INSERT is skipped when the row
 *     already exists, but the UPDATE clause repairs any row that is present
 *     but inactive or soft-deleted.
 *   - Safe to run multiple times without creating duplicates.
 *   - Preserves the existing row id (no DELETE + re-INSERT).
 *
 * document_statuses:
 *   - "Opinion Requested"  — set when a document is endorsed to opinion offices
 *   - "Withdrawn"          — already seeded (id=3); UPDATE repairs it if inactive
 *   - "Ready for Agenda"   — set when the committee resolves to proceed to agenda
 *   (Note: "Referred" with id=8 is assumed to exist from the original seed.)
 *
 * opinion_statuses:
 *   - "Pending"      — already seeded (id=1); UPDATE repairs if inactive
 *   - "Submitted"    — once an opinion file has been submitted
 *   - "Favorable"    — already seeded (id=2); UPDATE repairs if inactive
 *   - "Unfavorable"  — already seeded (id=3); UPDATE repairs if inactive
 *   - "Completed"    — the endorsement cycle for this office is complete
 *
 * Rows intentionally NOT touched: "For Second Endorsement" (id=4).
 */
class SeedCommitteeWorkflowStatuses
{
    public function up(PDO $pdo): void
    {
        // ── document_statuses ────────────────────────────────────────────────
        //
        // Requires a UNIQUE constraint on document_statuses.name for the
        // ON DUPLICATE KEY path to fire.  The constraint is checked below
        // and added when absent so the migration is self-contained.
        $this->ensureUniqueIndex($pdo, 'document_statuses', 'uq_document_statuses_name', 'name');

        $docStatuses = [
            [
                'name'        => 'Opinion Requested',
                'description' => 'Document sent to opinion offices for review.',
                'badge_color' => '#7C3AED',
                'sort_order'  => 30,
            ],
            [
                'name'        => 'Withdrawn',
                'description' => 'Document withdrawn from the legislative workflow by Committee decision.',
                'badge_color' => '#6B7280',
                'sort_order'  => 40,
            ],
            [
                'name'        => 'Ready for Agenda',
                'description' => 'Committee has resolved to proceed; document awaiting agenda scheduling.',
                'badge_color' => '#059669',
                'sort_order'  => 35,
            ],
        ];

        $upsertDoc = $pdo->prepare("
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

        foreach ($docStatuses as $s) {
            $upsertDoc->execute([$s['name'], $s['description'], $s['badge_color'], $s['sort_order']]);
        }

        // ── opinion_statuses ─────────────────────────────────────────────────
        $this->ensureUniqueIndex($pdo, 'opinion_statuses', 'uq_opinion_statuses_name', 'name');

        $opStatuses = [
            ['name' => 'Pending',     'description' => 'Endorsement sent; awaiting opinion submission.',  'sort_order' => 10],
            ['name' => 'Submitted',   'description' => 'Opinion has been submitted.',                      'sort_order' => 20],
            ['name' => 'Favorable',   'description' => 'Opinion submitted as Favorable.',                  'sort_order' => 30],
            ['name' => 'Unfavorable', 'description' => 'Opinion submitted as Unfavorable.',                'sort_order' => 40],
            ['name' => 'Completed',   'description' => 'Endorsement cycle for this office is complete.',   'sort_order' => 50],
        ];

        $upsertOp = $pdo->prepare("
            INSERT INTO opinion_statuses
                (name, description, sort_order, is_active, is_deleted)
            VALUES (?, ?, ?, 1, 0)
            ON DUPLICATE KEY UPDATE
                description = VALUES(description),
                sort_order  = VALUES(sort_order),
                is_active   = 1,
                is_deleted  = 0
        ");

        foreach ($opStatuses as $s) {
            $upsertOp->execute([$s['name'], $s['description'], $s['sort_order']]);
        }
    }

    public function down(PDO $pdo): void
    {
        // Only remove the rows that this migration is responsible for adding.
        // "Withdrawn" existed before this migration so we leave it.
        // "Pending", "Favorable", "Unfavorable" existed before — leave them too.
        $pdo->exec("DELETE FROM document_statuses WHERE name IN ('Opinion Requested', 'Ready for Agenda')");
        $pdo->exec("DELETE FROM opinion_statuses  WHERE name IN ('Submitted', 'Completed')");
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /**
     * Add a UNIQUE INDEX on $column in $table if it does not already exist.
     * This makes the ON DUPLICATE KEY UPDATE path work even on databases that
     * were created without the constraint.
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
        $dupes = $dup->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($dupes)) {
            // De-duplicate: keep the lowest id, soft-delete the rest.
            foreach ($dupes as $d) {
                $pdo->prepare("
                    UPDATE {$table} SET is_deleted = 1
                    WHERE name = ?
                    ORDER BY id DESC
                    LIMIT " . ((int)$d['cnt'] - 1)
                )->execute([$d['name']]);
            }
        }

        $pdo->exec("ALTER TABLE `{$table}` ADD UNIQUE INDEX `{$indexName}` (`{$column}`)");
    }
}

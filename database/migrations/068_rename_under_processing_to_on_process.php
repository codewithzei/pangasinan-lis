<?php

/**
 * Migration 068 — Rename "Under Processing" document status to "On Process"
 *
 * The Plenary pre-decision workflow previously used a status named
 * "Under Processing". It has been renamed to "On Process" for display
 * consistency across the system. The meaning of the status and the actions
 * it unlocks are unchanged — only the label changes.
 *
 * Existing records are preserved automatically because every workflow record
 * references the status by its primary-key id (documents.current_status_id,
 * plenary_document_details.status_id, document_events.from_status_id /
 * to_status_id). Renaming the row's `name` therefore keeps all documents,
 * audit events and permissions flowing through the exact same process.
 *
 * Behaviour:
 *   1. If only "Under Processing" exists → rename it in place to "On Process".
 *   2. If "On Process" already exists (partial/duplicate seed) → re-point every
 *      status reference from the old row to the surviving "On Process" row and
 *      remove the stale duplicate. History rows are re-pointed, not deleted.
 *   3. If neither exists, or the rename has already been applied → no-op.
 *
 * The migration is idempotent and safe to re-run.
 */
class RenameUnderProcessingToOnProcess
{
    private const OLD_NAME = 'Under Processing';
    private const NEW_NAME = 'On Process';

    /**
     * Every column that stores a document_statuses.id, used only by the
     * merge path (both rows present) so no reference is left dangling.
     */
    private const STATUS_REFERENCE_COLUMNS = [
        ['documents',                'current_status_id'],
        ['plenary_document_details', 'status_id'],
        ['document_events',          'from_status_id'],
        ['document_events',          'to_status_id'],
    ];

    public function up(PDO $pdo): void
    {
        $oldRow = $this->findStatus($pdo, self::OLD_NAME);
        $newRow = $this->findStatus($pdo, self::NEW_NAME);

        // Nothing named "Under Processing" — either already renamed or never
        // seeded. Nothing to do.
        if (!$oldRow) {
            return;
        }

        // Common case: only the legacy row exists. Rename in place so every
        // existing record (all keyed by id) keeps pointing at the same status.
        if (!$newRow) {
            $pdo->prepare("UPDATE document_statuses SET name = ? WHERE id = ?")
                ->execute([self::NEW_NAME, (int) $oldRow['id']]);
            return;
        }

        // Duplicate case: both rows exist. Consolidate onto the "On Process"
        // row by re-pointing all references, then drop the stale duplicate.
        $oldId = (int) $oldRow['id'];
        $newId = (int) $newRow['id'];

        $pdo->beginTransaction();
        try {
            foreach (self::STATUS_REFERENCE_COLUMNS as [$table, $column]) {
                $pdo->prepare("UPDATE {$table} SET {$column} = ? WHERE {$column} = ?")
                    ->execute([$newId, $oldId]);
            }

            $pdo->prepare("DELETE FROM document_statuses WHERE id = ?")
                ->execute([$oldId]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function down(PDO $pdo): void
    {
        // Best-effort revert: only rename back when no legacy row already
        // exists (avoids violating the unique index on document_statuses.name).
        $newRow = $this->findStatus($pdo, self::NEW_NAME);
        $oldRow = $this->findStatus($pdo, self::OLD_NAME);

        if ($newRow && !$oldRow) {
            $pdo->prepare("UPDATE document_statuses SET name = ? WHERE id = ?")
                ->execute([self::OLD_NAME, (int) $newRow['id']]);
        }
    }

    private function findStatus(PDO $pdo, string $name): ?array
    {
        $stmt = $pdo->prepare(
            "SELECT id, name FROM document_statuses WHERE name = ? LIMIT 1"
        );
        $stmt->execute([$name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

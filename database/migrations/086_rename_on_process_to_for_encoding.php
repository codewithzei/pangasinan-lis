<?php

class RenameOnProcessToForEncoding
{
    private const OLD_NAME = 'On Process';
    private const NEW_NAME = 'For Encoding';

    private const STATUS_REFERENCE_COLUMNS = [
        ['documents', 'current_status_id'],
        ['plenary_document_details', 'status_id'],
        ['document_events', 'from_status_id'],
        ['document_events', 'to_status_id'],
    ];

    public function up(PDO $pdo): void
    {
        $oldStatus = $this->findStatus($pdo, self::OLD_NAME);
        $newStatus = $this->findStatus($pdo, self::NEW_NAME);

        if (!$oldStatus) {
            return;
        }

        if (!$newStatus) {
            $pdo->prepare("UPDATE document_statuses SET name = ? WHERE id = ?")
                ->execute([self::NEW_NAME, (int) $oldStatus['id']]);
            return;
        }

        $oldId = (int) $oldStatus['id'];
        $newId = (int) $newStatus['id'];

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
        $newStatus = $this->findStatus($pdo, self::NEW_NAME);
        $oldStatus = $this->findStatus($pdo, self::OLD_NAME);

        if ($newStatus && !$oldStatus) {
            $pdo->prepare("UPDATE document_statuses SET name = ? WHERE id = ?")
                ->execute([self::OLD_NAME, (int) $newStatus['id']]);
        }
    }

    private function findStatus(PDO $pdo, string $name): ?array
    {
        $stmt = $pdo->prepare(
            "SELECT id, name FROM document_statuses WHERE name = ? LIMIT 1"
        );
        $stmt->execute([$name]);
        $status = $stmt->fetch(PDO::FETCH_ASSOC);

        return $status ?: null;
    }
}

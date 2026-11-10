<?php

class RenameExternalOfficesToProvincialGovernmentOffices
{
    private const OLD_TABLE = 'external_offices';
    private const NEW_TABLE = 'provincial_government_offices';
    private const OLD_SOURCE_TYPE = 'External Office';
    private const NEW_SOURCE_TYPE = 'Provincial Government Offices';

    public function up(PDO $pdo): void
    {
        $oldTableExists = $this->tableExists($pdo, self::OLD_TABLE);
        $newTableExists = $this->tableExists($pdo, self::NEW_TABLE);
        if ($oldTableExists && $newTableExists) {
            throw new RuntimeException(
                'Both external office tables exist; resolve the duplicate tables before migrating.'
            );
        }
        if ($oldTableExists) {
            $pdo->exec('RENAME TABLE external_offices TO provincial_government_offices');
        }

        $oldSourceType = $this->findSourceType($pdo, self::OLD_SOURCE_TYPE);
        $newSourceType = $this->findSourceType($pdo, self::NEW_SOURCE_TYPE);
        if ($oldSourceType && !$newSourceType) {
            $pdo->prepare("UPDATE source_types SET name = ? WHERE id = ?")
                ->execute([self::NEW_SOURCE_TYPE, (int) $oldSourceType['id']]);
        } elseif ($oldSourceType && $newSourceType) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare('UPDATE documents SET source_type_id = ? WHERE source_type_id = ?')
                    ->execute([(int) $newSourceType['id'], (int) $oldSourceType['id']]);
                $pdo->prepare('DELETE FROM source_types WHERE id = ?')
                    ->execute([(int) $oldSourceType['id']]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        }
    }

    public function down(PDO $pdo): void
    {
        $oldTableExists = $this->tableExists($pdo, self::OLD_TABLE);
        $newTableExists = $this->tableExists($pdo, self::NEW_TABLE);
        if ($oldTableExists && $newTableExists) {
            throw new RuntimeException(
                'Both external office tables exist; resolve the duplicate tables before rolling back.'
            );
        }
        if ($newTableExists) {
            $pdo->exec('RENAME TABLE provincial_government_offices TO external_offices');
        }

        $oldSourceType = $this->findSourceType($pdo, self::OLD_SOURCE_TYPE);
        $newSourceType = $this->findSourceType($pdo, self::NEW_SOURCE_TYPE);
        if ($newSourceType && !$oldSourceType) {
            $pdo->prepare("UPDATE source_types SET name = ? WHERE id = ?")
                ->execute([self::OLD_SOURCE_TYPE, (int) $newSourceType['id']]);
        }
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
        ");
        $stmt->execute([$table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function findSourceType(PDO $pdo, string $name): ?array
    {
        $stmt = $pdo->prepare('SELECT id FROM source_types WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $sourceType = $stmt->fetch(PDO::FETCH_ASSOC);

        return $sourceType ?: null;
    }
}

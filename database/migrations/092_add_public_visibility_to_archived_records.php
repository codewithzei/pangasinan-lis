<?php

class AddPublicVisibilityToArchivedRecords
{
    public function up(PDO $pdo): void
    {
        if (!$this->columnExists($pdo, 'is_public')) {
            $pdo->exec("
                ALTER TABLE archived_records
                ADD COLUMN is_public TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active,
                ADD INDEX idx_ar_is_public (is_public)
            ");
        }
    }

    public function down(PDO $pdo): void
    {
        if ($this->columnExists($pdo, 'is_public')) {
            $pdo->exec("ALTER TABLE archived_records DROP COLUMN is_public");
        }
    }

    private function columnExists(PDO $pdo, string $column): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'archived_records'
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$column]);

        return (int) $stmt->fetchColumn() > 0;
    }
}

<?php

class AddFinalOutputTypeToPlenaryRecords
{
    private const TABLES = [
        'plenary_comm_first_reading',
        'plenary_comm_adoptions',
        'plenary_cases_adoptions',
    ];

    public function up(PDO $pdo): void
    {
        foreach (self::TABLES as $table) {
            if ($this->tableExists($pdo, $table) && !$this->columnExists($pdo, $table)) {
                $pdo->exec("
                    ALTER TABLE `{$table}`
                    ADD COLUMN final_output_type ENUM('RESOLUTION', 'ORDINANCE') NULL
                        COMMENT 'Classification of the finalized output, independent of the original document type'
                        AFTER resolution_number
                ");
            }
        }
    }

    public function down(PDO $pdo): void
    {
        foreach (self::TABLES as $table) {
            if ($this->tableExists($pdo, $table) && $this->columnExists($pdo, $table)) {
                $pdo->exec("ALTER TABLE `{$table}` DROP COLUMN final_output_type");
            }
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

    private function columnExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = 'final_output_type'
        ");
        $stmt->execute([$table]);

        return (int) $stmt->fetchColumn() > 0;
    }
}

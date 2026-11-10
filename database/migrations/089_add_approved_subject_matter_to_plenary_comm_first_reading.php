<?php

class AddApprovedSubjectMatterToPlenaryCommFirstReading
{
    public function up(PDO $pdo): void
    {
        if (!$this->columnExists($pdo)) {
            $pdo->exec("
                ALTER TABLE plenary_comm_first_reading
                ADD COLUMN approved_subject_matter TEXT NULL
                    COMMENT 'Subject matter recorded when approved for resolution'
                    AFTER resolution_number
            ");
        }
    }

    public function down(PDO $pdo): void
    {
        if ($this->columnExists($pdo)) {
            $pdo->exec("
                ALTER TABLE plenary_comm_first_reading
                DROP COLUMN approved_subject_matter
            ");
        }
    }

    private function columnExists(PDO $pdo): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'plenary_comm_first_reading'
              AND COLUMN_NAME = 'approved_subject_matter'
        ");
        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
    }
}

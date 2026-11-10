<?php

class AddApprovedSubjectMatterToPlenaryAdoptions
{
    public function up(PDO $pdo): void
    {
        $this->addColumnIfMissing($pdo, 'plenary_comm_adoptions');
        $this->addColumnIfMissing($pdo, 'plenary_cases_adoptions');
    }

    public function down(PDO $pdo): void
    {
        $this->dropColumnIfPresent($pdo, 'plenary_comm_adoptions');
        $this->dropColumnIfPresent($pdo, 'plenary_cases_adoptions');
    }

    private function addColumnIfMissing(PDO $pdo, string $table): void
    {
        if (!$this->tableExists($pdo, $table) || $this->columnExists($pdo, $table)) {
            return;
        }

        $pdo->exec("
            ALTER TABLE `{$table}`
            ADD COLUMN approved_subject_matter TEXT NULL
                COMMENT 'Subject matter recorded when the committee report is approved'
                AFTER resolution_number
        ");
    }

    private function dropColumnIfPresent(PDO $pdo, string $table): void
    {
        if ($this->tableExists($pdo, $table) && $this->columnExists($pdo, $table)) {
            $pdo->exec("ALTER TABLE `{$table}` DROP COLUMN approved_subject_matter");
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
              AND COLUMN_NAME = 'approved_subject_matter'
        ");
        $stmt->execute([$table]);

        return (int) $stmt->fetchColumn() > 0;
    }
}

<?php

abstract class Seeder
{
    protected PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    abstract public function run(): void;

    protected function truncate(string $table): void
    {
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $this->pdo->exec("TRUNCATE TABLE `{$table}`");
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    }

    protected function insert(string $table, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $columns = array_keys($data[0]);
        $columnList = implode(', ', array_map(fn($col) => "`{$col}`", $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        $sql = "INSERT INTO `{$table}` ({$columnList}) VALUES ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);

        foreach ($data as $row) {
            $stmt->execute(array_values($row));
        }
    }

    protected function insertOrIgnore(string $table, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $columns = array_keys($data[0]);
        $columnList = implode(', ', array_map(fn($col) => "`{$col}`", $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        $sql = "INSERT IGNORE INTO `{$table}` ({$columnList}) VALUES ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);

        foreach ($data as $row) {
            $stmt->execute(array_values($row));
        }
    }
}

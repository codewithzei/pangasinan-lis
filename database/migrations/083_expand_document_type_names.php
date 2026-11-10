<?php

class ExpandDocumentTypeNames
{
    private const LONG_DOCUMENT_TYPE_NAMES = [
        'Provincial Appropriation Ordinance for Supplemental Budget',
        'Provincial Appropriation Ordinance for Annual Budget',
        'Municipal/City Appropriation Ordinance for Supplemental Budget',
        'Municipal/City Appropriation Ordinance for Annual Budget',
    ];

    public function up(PDO $pdo): void
    {
        $pdo->exec("ALTER TABLE document_types MODIFY COLUMN name VARCHAR(255) NOT NULL");

        $repair = $pdo->prepare("
            UPDATE document_types
            SET name = ?
            WHERE name = LEFT(?, 50)
        ");

        foreach (self::LONG_DOCUMENT_TYPE_NAMES as $name) {
            $repair->execute([$name, $name]);
        }
    }

    public function down(PDO $pdo): void
    {
        $truncate = $pdo->prepare("UPDATE document_types SET name = LEFT(name, 50) WHERE name = ?");

        foreach (self::LONG_DOCUMENT_TYPE_NAMES as $name) {
            $truncate->execute([$name]);
        }

        $pdo->exec("ALTER TABLE document_types MODIFY COLUMN name VARCHAR(50) NOT NULL");
    }
}
<?php

/**
 * Migration 084 — Allow cases to exist before a docket number is assigned.
 *
 * The Committee Inbox creates a case container before the committee user enters
 * its docket details. A nullable docket_number allows multiple such containers
 * despite the unique docket-number index; MySQL permits multiple NULLs in a
 * unique index but permits only one empty string.
 */
class AllowUnassignedCommitteeCaseDocket
{
    public function up(PDO $pdo): void
    {
        $stmt = $pdo->prepare("
            SELECT IS_NULLABLE, CHARACTER_MAXIMUM_LENGTH
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'committee_cases'
              AND COLUMN_NAME = 'docket_number'
            LIMIT 1
        ");
        $stmt->execute();
        $column = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$column) {
            throw new RuntimeException('committee_cases.docket_number was not found.');
        }

        $length = max(100, (int) $column['CHARACTER_MAXIMUM_LENGTH']);
        if ($column['IS_NULLABLE'] !== 'YES') {
            $pdo->exec("ALTER TABLE committee_cases MODIFY COLUMN docket_number VARCHAR({$length}) NULL DEFAULT NULL");
        }

        $pdo->exec("UPDATE committee_cases SET docket_number = NULL WHERE TRIM(docket_number) = ''");
    }

    public function down(PDO $pdo): void
    {
        $nullCount = (int) $pdo->query(
            'SELECT COUNT(*) FROM committee_cases WHERE docket_number IS NULL'
        )->fetchColumn();
        if ($nullCount > 0) {
            throw new RuntimeException(
                'Cannot restore NOT NULL while cases have unassigned docket numbers. Save a unique docket number for every case first.'
            );
        }

        $pdo->exec('ALTER TABLE committee_cases MODIFY COLUMN docket_number VARCHAR(100) NOT NULL');
    }
}
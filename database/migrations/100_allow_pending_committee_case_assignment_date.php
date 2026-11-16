<?php

/**
 * Migration 100 — Keep the assignment date blank until it is confirmed.
 *
 * Cases created by the Committee Inbox are pending details, so they do not
 * have an assignment date yet. Existing pending rows were seeded with the
 * creation date and must be cleared along with making the column nullable.
 */
class AllowPendingCommitteeCaseAssignmentDate
{
    public function up(PDO $pdo): void
    {
        $stmt = $pdo->prepare("
            SELECT IS_NULLABLE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'committee_cases'
              AND COLUMN_NAME = 'date_assigned'
            LIMIT 1
        ");
        $stmt->execute();
        $column = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$column) {
            throw new RuntimeException('committee_cases.date_assigned was not found.');
        }

        if ($column['IS_NULLABLE'] !== 'YES') {
            $pdo->exec('ALTER TABLE committee_cases MODIFY COLUMN date_assigned DATE NULL DEFAULT NULL');
        }

        $pdo->exec("
            UPDATE committee_cases
            SET date_assigned = NULL
            WHERE docket_number IS NULL
               OR TRIM(COALESCE(docket_number, '')) = ''
               OR TRIM(COALESCE(nature_of_case, '')) = ''
               OR TRIM(COALESCE(complainant_details, '')) = ''
               OR TRIM(COALESCE(respondents, '')) = ''
        ");
    }

    public function down(PDO $pdo): void
    {
        $nullCount = (int) $pdo->query(
            'SELECT COUNT(*) FROM committee_cases WHERE date_assigned IS NULL'
        )->fetchColumn();
        if ($nullCount > 0) {
            throw new RuntimeException(
                'Cannot restore NOT NULL while cases have no confirmed assignment date. Enter a date for every case first.'
            );
        }

        $pdo->exec('ALTER TABLE committee_cases MODIFY COLUMN date_assigned DATE NOT NULL');
    }
}

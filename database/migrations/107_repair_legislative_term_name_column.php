<?php

class RepairLegislativeTermNameColumn
{
    public function up(PDO $pdo): void
    {
        $columns = $pdo->query("SHOW COLUMNS FROM plenary_document_details")
            ->fetchAll(PDO::FETCH_COLUMN, 0);

        if (!in_array('legislative_term_id', $columns, true)) {
            $pdo->exec("
                ALTER TABLE plenary_document_details
                    ADD COLUMN legislative_term_id BIGINT NULL AFTER proposed_number
            ");
            $columns = $pdo->query("SHOW COLUMNS FROM plenary_document_details")
                ->fetchAll(PDO::FETCH_COLUMN, 0);
        }

        if (!in_array('legislative_term_name', $columns, true)) {
            $pdo->exec("
                ALTER TABLE plenary_document_details
                    ADD COLUMN legislative_term_name VARCHAR(255) NULL AFTER legislative_term_id
            ");
        }
    }

    public function down(PDO $pdo): void
    {
        throw new RuntimeException(
            'Migration 107 is irreversible because it repairs a column required by current application queries.'
        );
    }
}

<?php

class AddProposedNumberTermAndSequences
{
    public function up($pdo)
    {
        $columns = $pdo->query("SHOW COLUMNS FROM plenary_document_details")
            ->fetchAll(PDO::FETCH_COLUMN, 0);
        if (!in_array('legislative_term_id', $columns, true)) {
            $pdo->exec("ALTER TABLE plenary_document_details
                ADD COLUMN legislative_term_id BIGINT NULL AFTER proposed_number");
        }
        if (!in_array('legislative_term_name', $columns, true)) {
            $pdo->exec("ALTER TABLE plenary_document_details
                ADD COLUMN legislative_term_name VARCHAR(255) NULL AFTER legislative_term_id");
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS proposed_number_sequences (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            legislative_term_id BIGINT NOT NULL,
            prefix VARCHAR(10) NOT NULL,
            last_sequence INT UNSIGNED NOT NULL DEFAULT 0,
            UNIQUE KEY uq_proposed_number_term_prefix (legislative_term_id, prefix),
            CONSTRAINT fk_proposed_number_sequences_term
                FOREIGN KEY (legislative_term_id) REFERENCES terms(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS proposed_number_sequences");
        $pdo->exec("ALTER TABLE plenary_document_details
            DROP COLUMN legislative_term_name,
            DROP COLUMN legislative_term_id");
    }
}

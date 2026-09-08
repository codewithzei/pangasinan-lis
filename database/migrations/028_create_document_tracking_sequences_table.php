<?php

class CreateDocumentTrackingSequencesTable
{
    public function up($pdo)
    {
        $sql = "CREATE TABLE IF NOT EXISTS document_tracking_sequences (
            tracking_year SMALLINT PRIMARY KEY,
            last_sequence INT NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql);
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS document_tracking_sequences;");
    }
}

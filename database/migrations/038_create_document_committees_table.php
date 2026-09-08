<?php

class CreateDocumentCommitteesTable
{
    public function up($pdo)
    {
        $sql = "CREATE TABLE IF NOT EXISTS document_committees (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            document_id BIGINT UNSIGNED NOT NULL,
            committee_id INT NOT NULL,
            assigned_by BIGINT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Unique Constraint
            UNIQUE KEY unique_document_committee (document_id, committee_id),
            
            -- Indexes
            INDEX idx_document_committees_document (document_id),
            INDEX idx_document_committees_committee (committee_id),
            INDEX idx_document_committees_assigned_by (assigned_by),
            
            -- Foreign Keys
            CONSTRAINT fk_document_committees_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
            CONSTRAINT fk_document_committees_committee FOREIGN KEY (committee_id) REFERENCES committees(id) ON DELETE RESTRICT,
            CONSTRAINT fk_document_committees_assigned_by FOREIGN KEY (assigned_by) REFERENCES user_accounts(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql);
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS document_committees;");
    }
}

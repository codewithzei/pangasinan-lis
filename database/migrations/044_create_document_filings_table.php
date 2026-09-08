<?php

class CreateDocumentFilingsTable
{
    public function up($pdo)
    {
        $sql = "CREATE TABLE IF NOT EXISTS document_filings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            document_id BIGINT UNSIGNED NOT NULL UNIQUE,
            description TEXT NULL,
            tags JSON NULL COMMENT 'Array of tags for search and categorization',
            is_public TINYINT(1) NOT NULL DEFAULT 1,
            filed_by BIGINT NULL,
            filed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Indexes
            INDEX idx_document_filings_document (document_id),
            INDEX idx_document_filings_is_public (is_public),
            INDEX idx_document_filings_filed_by (filed_by),
            INDEX idx_document_filings_filed_at (filed_at),
            
            -- Foreign Keys
            CONSTRAINT fk_document_filings_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE RESTRICT,
            CONSTRAINT fk_document_filings_filed_by FOREIGN KEY (filed_by) REFERENCES user_accounts(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql);
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS document_filings;");
    }
}

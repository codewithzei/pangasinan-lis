<?php

class CreateDocumentApprovalsTable
{
    public function up($pdo)
    {
        $sql = "CREATE TABLE IF NOT EXISTS document_approvals (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            document_id BIGINT UNSIGNED NOT NULL UNIQUE,
            approved_number VARCHAR(50) NULL,
            date_approved DATE NOT NULL,
            approved_by BIGINT NULL,
            approved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Indexes
            INDEX idx_document_approvals_document (document_id),
            INDEX idx_document_approvals_date (date_approved),
            INDEX idx_document_approvals_approved_by (approved_by),
            
            -- Foreign Keys
            CONSTRAINT fk_document_approvals_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE RESTRICT,
            CONSTRAINT fk_document_approvals_approved_by FOREIGN KEY (approved_by) REFERENCES user_accounts(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql);
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS document_approvals;");
    }
}

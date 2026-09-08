<?php

class CreateDocumentAttachmentsTable
{
    public function up($pdo)
    {
        $sql = "CREATE TABLE IF NOT EXISTS document_attachments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            document_id BIGINT UNSIGNED NOT NULL,
            uploaded_by BIGINT NULL,
            phase ENUM('RECEIVING', 'ADMIN', 'SP_SECRETARY', 'PLENARY', 'COMMITTEE', 'FINALIZED', 'FILED') NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            stored_path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            file_size BIGINT UNSIGNED NOT NULL,
            attachment_type ENUM(
                'RECEIVING_FILE',
                'OPINION_FILE',
                'COMPLIANCE_FILE',
                'COMMITTEE_REPORT',
                'APPROVAL_FILE',
                'FILING_FILE',
                'OTHER'
            ) NOT NULL DEFAULT 'OTHER',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Indexes
            INDEX idx_document_attachments_document (document_id),
            INDEX idx_document_attachments_uploaded_by (uploaded_by),
            INDEX idx_document_attachments_phase (phase),
            INDEX idx_document_attachments_type (attachment_type),
            INDEX idx_document_attachments_created_at (created_at),
            
            -- Foreign Keys
            CONSTRAINT fk_document_attachments_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
            CONSTRAINT fk_document_attachments_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES user_accounts(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql);
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS document_attachments;");
    }
}

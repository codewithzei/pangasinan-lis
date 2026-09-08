<?php

class CreateDocumentRevisionsTable
{
    public function up($pdo)
    {
        $sql = "CREATE TABLE IF NOT EXISTS document_revisions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            document_id BIGINT UNSIGNED NOT NULL,
            revision_number INT NOT NULL,
            changed_by BIGINT NULL,
            phase ENUM('RECEIVING', 'ADMIN', 'SP_SECRETARY', 'PLENARY', 'COMMITTEE', 'FINALIZED', 'FILED') NOT NULL,
            
            -- Snapshot of document data
            subject_matter TEXT NOT NULL,
            date_received DATE NOT NULL,
            time_received TIME NOT NULL,
            document_type_id INT NOT NULL,
            source_type_id INT NOT NULL,
            source_snapshot JSON NULL COMMENT 'Stores external_office_id, hospital_id, municipality_id, source_name, etc.',
            
            remarks TEXT NULL,
            change_reason VARCHAR(500) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Unique Constraint
            UNIQUE KEY unique_document_revision (document_id, revision_number),
            
            -- Indexes
            INDEX idx_document_revisions_document (document_id),
            INDEX idx_document_revisions_changed_by (changed_by),
            INDEX idx_document_revisions_phase (phase),
            INDEX idx_document_revisions_created_at (created_at),
            
            -- Foreign Keys
            CONSTRAINT fk_document_revisions_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE RESTRICT,
            CONSTRAINT fk_document_revisions_changed_by FOREIGN KEY (changed_by) REFERENCES user_accounts(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql);
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS document_revisions;");
    }
}

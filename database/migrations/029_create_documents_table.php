<?php

class CreateDocumentsTable
{
    public function up($pdo)
    {
        $sql = "CREATE TABLE IF NOT EXISTS documents (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            
            -- Tracking Information
            tracking_year SMALLINT NOT NULL,
            tracking_sequence INT NOT NULL,
            tracking_number VARCHAR(20) NOT NULL UNIQUE,
            
            -- Receipt Information
            date_received DATE NOT NULL,
            time_received TIME NOT NULL,
            
            -- Document Details
            subject_matter_document_type TEXT NOT NULL,
            
            -- Source Information
            source_type_id INT NOT NULL,
            external_office_id BIGINT NULL,
            hospital_id INT NULL,
            municipality_id BIGINT NULL,
            source_name VARCHAR(255) NULL,
            source_contact_number VARCHAR(50) NULL,
            source_address TEXT NULL,
            source_liaison_name VARCHAR(255) NULL,
            
            -- Current State
            current_status_id INT NOT NULL,
            current_owner_user_id BIGINT NULL,
            current_phase ENUM('RECEIVING', 'ADMIN', 'SP_SECRETARY', 'PLENARY', 'COMMITTEE', 'FINALIZED', 'FILED') NOT NULL DEFAULT 'RECEIVING',
            
            -- Additional Information
            remarks TEXT NULL,
            is_public TINYINT(1) NOT NULL DEFAULT 0,
            
            -- Workflow Timestamps
            finalized_at TIMESTAMP NULL,
            filed_at TIMESTAMP NULL,
            
            -- Audit Trail
            created_by BIGINT NULL,
            updated_by BIGINT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            -- Unique Constraints
            UNIQUE KEY unique_tracking_year_sequence (tracking_year, tracking_sequence),
            
            -- Indexes
            INDEX idx_documents_current_owner (current_owner_user_id),
            INDEX idx_documents_current_status (current_status_id),
            INDEX idx_documents_current_phase (current_phase),
            INDEX idx_documents_source_type (source_type_id),
            INDEX idx_documents_date_received (date_received),
            INDEX idx_documents_tracking_year (tracking_year),
            INDEX idx_documents_is_public (is_public),
            INDEX idx_documents_finalized_at (finalized_at),
            INDEX idx_documents_filed_at (filed_at),
            
            -- Foreign Keys
            CONSTRAINT fk_documents_source_type FOREIGN KEY (source_type_id) REFERENCES source_types(id) ON DELETE RESTRICT,
            CONSTRAINT fk_documents_external_office FOREIGN KEY (external_office_id) REFERENCES external_offices(id) ON DELETE RESTRICT,
            CONSTRAINT fk_documents_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE RESTRICT,
            CONSTRAINT fk_documents_municipality FOREIGN KEY (municipality_id) REFERENCES municities(id) ON DELETE RESTRICT,
            CONSTRAINT fk_documents_current_status FOREIGN KEY (current_status_id) REFERENCES document_statuses(id) ON DELETE RESTRICT,
            CONSTRAINT fk_documents_current_owner FOREIGN KEY (current_owner_user_id) REFERENCES user_accounts(id) ON DELETE SET NULL,
            CONSTRAINT fk_documents_created_by FOREIGN KEY (created_by) REFERENCES user_accounts(id) ON DELETE SET NULL,
            CONSTRAINT fk_documents_updated_by FOREIGN KEY (updated_by) REFERENCES user_accounts(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql);
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS documents;");
    }
}

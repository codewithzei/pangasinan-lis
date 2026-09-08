<?php

class CreatePlenaryDocumentDetailsTable
{
    public function up($pdo)
    {
        $sql = "CREATE TABLE IF NOT EXISTS plenary_document_details (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            document_id BIGINT UNSIGNED NOT NULL UNIQUE,
            proposed_number VARCHAR(50) NULL,
            target_first_reading_date DATE NULL,
            session_type ENUM('REGULAR', 'REGULAR_ONLINE', 'SPECIAL', 'SPECIAL_ONLINE') NULL,
            status_id INT NOT NULL,
            additional_details TEXT NULL,
            created_by BIGINT NULL,
            updated_by BIGINT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            -- Indexes
            INDEX idx_plenary_document_details_document (document_id),
            INDEX idx_plenary_document_details_status (status_id),
            INDEX idx_plenary_document_details_session_type (session_type),
            INDEX idx_plenary_document_details_target_date (target_first_reading_date),
            
            -- Foreign Keys
            CONSTRAINT fk_plenary_document_details_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
            CONSTRAINT fk_plenary_document_details_status FOREIGN KEY (status_id) REFERENCES document_statuses(id) ON DELETE RESTRICT,
            CONSTRAINT fk_plenary_document_details_created_by FOREIGN KEY (created_by) REFERENCES user_accounts(id) ON DELETE SET NULL,
            CONSTRAINT fk_plenary_document_details_updated_by FOREIGN KEY (updated_by) REFERENCES user_accounts(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql);
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS plenary_document_details;");
    }
}

<?php

class CreateDocumentAssignmentsTable
{
    public function up($pdo)
    {
        $sql = "CREATE TABLE IF NOT EXISTS document_assignments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            document_id BIGINT UNSIGNED NOT NULL,
            assigned_to_user_id BIGINT NULL,
            assigned_to_role_id INT NULL,
            phase ENUM('RECEIVING', 'ADMIN', 'SP_SECRETARY', 'PLENARY', 'COMMITTEE', 'FINALIZED', 'FILED') NOT NULL,
            assigned_by BIGINT NULL,
            received_at TIMESTAMP NULL,
            accepted_at TIMESTAMP NULL,
            declined_at TIMESTAMP NULL,
            completed_at TIMESTAMP NULL,
            decision ENUM('PENDING', 'ACCEPTED', 'DECLINED', 'NOTED', 'REJECTED', 'COMPLETED') NOT NULL DEFAULT 'PENDING',
            decline_reason TEXT NULL,
            remarks TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Indexes
            INDEX idx_document_assignments_document (document_id),
            INDEX idx_document_assignments_assigned_to_user (assigned_to_user_id),
            INDEX idx_document_assignments_assigned_to_role (assigned_to_role_id),
            INDEX idx_document_assignments_phase (phase),
            INDEX idx_document_assignments_decision (decision),
            INDEX idx_document_assignments_assigned_by (assigned_by),
            INDEX idx_document_assignments_created_at (created_at),
            
            -- Foreign Keys
            CONSTRAINT fk_document_assignments_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE RESTRICT,
            CONSTRAINT fk_document_assignments_assigned_to_user FOREIGN KEY (assigned_to_user_id) REFERENCES user_accounts(id) ON DELETE SET NULL,
            CONSTRAINT fk_document_assignments_assigned_to_role FOREIGN KEY (assigned_to_role_id) REFERENCES roles(id) ON DELETE RESTRICT,
            CONSTRAINT fk_document_assignments_assigned_by FOREIGN KEY (assigned_by) REFERENCES user_accounts(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql);
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS document_assignments;");
    }
}

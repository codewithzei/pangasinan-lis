<?php

class CreateDocumentRoutesTable
{
    public function up($pdo)
    {
        $sql = "CREATE TABLE IF NOT EXISTS document_routes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            document_id BIGINT UNSIGNED NOT NULL,
            from_phase ENUM('RECEIVING', 'ADMIN', 'SP_SECRETARY', 'PLENARY', 'COMMITTEE', 'FINALIZED', 'FILED') NULL,
            to_phase ENUM('RECEIVING', 'ADMIN', 'SP_SECRETARY', 'PLENARY', 'COMMITTEE', 'FINALIZED', 'FILED') NOT NULL,
            routing_option_id INT NULL,
            routed_by BIGINT NULL,
            routed_to_user_id BIGINT NULL,
            routed_to_role_id INT NULL,
            remarks TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Indexes
            INDEX idx_document_routes_document (document_id),
            INDEX idx_document_routes_from_phase (from_phase),
            INDEX idx_document_routes_to_phase (to_phase),
            INDEX idx_document_routes_routing_option (routing_option_id),
            INDEX idx_document_routes_routed_by (routed_by),
            INDEX idx_document_routes_routed_to_user (routed_to_user_id),
            INDEX idx_document_routes_routed_to_role (routed_to_role_id),
            INDEX idx_document_routes_created_at (created_at),
            
            -- Foreign Keys
            CONSTRAINT fk_document_routes_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE RESTRICT,
            CONSTRAINT fk_document_routes_routing_option FOREIGN KEY (routing_option_id) REFERENCES routing_options(id) ON DELETE RESTRICT,
            CONSTRAINT fk_document_routes_routed_by FOREIGN KEY (routed_by) REFERENCES user_accounts(id) ON DELETE SET NULL,
            CONSTRAINT fk_document_routes_routed_to_user FOREIGN KEY (routed_to_user_id) REFERENCES user_accounts(id) ON DELETE SET NULL,
            CONSTRAINT fk_document_routes_routed_to_role FOREIGN KEY (routed_to_role_id) REFERENCES roles(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql);
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS document_routes;");
    }
}

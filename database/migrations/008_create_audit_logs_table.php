<?php

class CreateAuditLogsTable
{
    public function up($pdo)
    {
        $sql = "CREATE TABLE IF NOT EXISTS audit_logs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NULL,
            action ENUM('CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'VIEW', 'EXPORT', 'IMPORT', 'ARCHIVE', 'RESTORE', 'OTHER') NOT NULL DEFAULT 'OTHER',
            entity_type VARCHAR(100) NULL,
            entity_id VARCHAR(100) NULL,
            old_values JSON NULL,
            new_values JSON NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(500) NULL,
            description TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_audit_logs_user_id (user_id),
            INDEX idx_audit_logs_action (action),
            INDEX idx_audit_logs_entity (entity_type, entity_id),
            INDEX idx_audit_logs_created_at (created_at),
            INDEX idx_audit_logs_ip (ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql);
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS audit_logs;");
    }
}

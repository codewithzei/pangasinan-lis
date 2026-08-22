<?php

class CreateSystemLogsTable
{
    public function up($pdo)
    {
        $sql = "CREATE TABLE IF NOT EXISTS system_logs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            log_level ENUM('DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY') NOT NULL DEFAULT 'INFO',
            message TEXT NOT NULL,
            context JSON NULL,
            user_id BIGINT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(500) NULL,
            request_url VARCHAR(500) NULL,
            request_method VARCHAR(10) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_system_logs_level (log_level),
            INDEX idx_system_logs_user_id (user_id),
            INDEX idx_system_logs_created_at (created_at),
            INDEX idx_system_logs_ip (ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql);
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS system_logs;");
    }
}

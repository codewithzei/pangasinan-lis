<?php

class CreateOpinionOfficesTable
{
    public function up($pdo)
    {
        $sql = "CREATE TABLE IF NOT EXISTS opinion_offices (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            abbreviation VARCHAR(50) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            deleted_at TIMESTAMP NULL,
            deleted_by BIGINT NULL,
            created_by BIGINT NULL,
            updated_by BIGINT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_opinion_offices_name (name),
            INDEX idx_opinion_offices_is_active (is_active),
            INDEX idx_opinion_offices_is_deleted (is_deleted),
            INDEX idx_opinion_offices_sort_order (sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql);
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS opinion_offices;");
    }
}

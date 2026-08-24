<?php

class CreateMunicitiesTable
{
    public function up($pdo)
    {
        $sql = "CREATE TABLE IF NOT EXISTS municities (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            district_id BIGINT NOT NULL,
            name VARCHAR(255) NOT NULL,
            type VARCHAR(20) NOT NULL DEFAULT 'Municipality',
            description TEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            deleted_at TIMESTAMP NULL,
            deleted_by BIGINT NULL,
            created_by BIGINT NULL,
            updated_by BIGINT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_municities_name (name),
            INDEX idx_municities_district_id (district_id),
            INDEX idx_municities_type (type),
            INDEX idx_municities_is_active (is_active),
            INDEX idx_municities_is_deleted (is_deleted),
            INDEX idx_municities_sort_order (sort_order),
            CONSTRAINT fk_municities_district
                FOREIGN KEY (district_id) REFERENCES districts (id)
                ON UPDATE CASCADE
                ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql);
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS municities;");
    }
}

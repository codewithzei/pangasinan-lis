<?php

class CreateSpMembersTable
{
    public function up($pdo)
    {
        $sql = "CREATE TABLE IF NOT EXISTS sp_members (
            sp_member_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(100) NOT NULL,
            middle_name VARCHAR(100) NULL,
            last_name VARCHAR(100) NOT NULL,
            suffix VARCHAR(20) NULL,
            photo_path VARCHAR(500) NULL,
            position VARCHAR(150) NOT NULL,
            district_id BIGINT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            deleted_at TIMESTAMP NULL,
            deleted_by BIGINT NULL,
            created_by BIGINT NULL,
            updated_by BIGINT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_sp_members_district_id (district_id),
            INDEX idx_sp_members_is_active (is_active),
            INDEX idx_sp_members_is_deleted (is_deleted),
            INDEX idx_sp_members_sort_order (sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql);
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS sp_members;");
    }
}

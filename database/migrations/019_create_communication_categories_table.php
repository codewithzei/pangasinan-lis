<?php

class CreateCommunicationCategoriesTable 
{
    public function up($pdo) 
    {
        $sql = "CREATE TABLE IF NOT EXISTS communication_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL UNIQUE,
            description VARCHAR(255) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            deleted_at TIMESTAMP NULL,   
            deleted_by BIGINT NULL,
            
            created_by BIGINT NULL,
            updated_by BIGINT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql);
    }

    public function down($pdo) 
    {
        $sql = "DROP TABLE IF EXISTS communication_categories;";
        $pdo->exec($sql);
    }
}
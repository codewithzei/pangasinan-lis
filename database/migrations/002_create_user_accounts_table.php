<?php 

class CreateUserAccountsTable 
{
    public function up($pdo) 
    {
        $sql = "CREATE TABLE IF NOT EXISTS user_accounts (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,

            role_id INT NOT NULL,
            status ENUM('active', 'deactivated', 'blocked') NOT NULL DEFAULT 'active',

            failed_login_attempts INT NOT NULL DEFAULT 0,
            password_changed_at TIMESTAMP NULL,
            
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            deleted_at TIMESTAMP NULL,   
            deleted_by BIGINT NULL,
            
            created_by BIGINT NULL,
            updated_by BIGINT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            -- Primary Foreign Key (Role)
            CONSTRAINT fk_accounts_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
            
            -- Self-Referencing Foreign Keys (Audit Trail)
            CONSTRAINT fk_accounts_created_by FOREIGN KEY (created_by) REFERENCES user_accounts(id) ON DELETE SET NULL,
            CONSTRAINT fk_accounts_updated_by FOREIGN KEY (updated_by) REFERENCES user_accounts(id) ON DELETE SET NULL,
            CONSTRAINT fk_accounts_deleted_by FOREIGN KEY (deleted_by) REFERENCES user_accounts(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql);
    }

    public function down($pdo) 
    {
        $sql = "DROP TABLE IF EXISTS user_accounts;";
        $pdo->exec($sql);
    }
}

?>
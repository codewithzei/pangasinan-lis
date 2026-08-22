<?php 

class CreateUserInfoTable 
{
    public function up($pdo) 
    {
        $sql = "CREATE TABLE IF NOT EXISTS user_info (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_account_id BIGINT NOT NULL UNIQUE, -- Forces strict 1-to-1 relationship
            
            -- Name Fields (Atomic Values)
            first_name VARCHAR(50) NOT NULL,
            middle_name VARCHAR(50) NULL,
            last_name VARCHAR(50) NOT NULL,
            suffix VARCHAR(10) NULL, -- e.g., Jr., Sr., III
            
            -- Contact Details & Profile
            contact_number VARCHAR(20) NULL,
            profile_path VARCHAR(255) NULL,
            
            -- Timestamps
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            -- Foreign Key Constraint (Cascade Delete cleans up profile if account is hard-deleted)
            CONSTRAINT fk_user_info_account FOREIGN KEY (user_account_id) REFERENCES user_accounts(id) ON DELETE CASCADE
            
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql);
    }

    public function down($pdo) 
    {
        $sql = "DROP TABLE IF EXISTS user_info;";
        $pdo->exec($sql);
    }
}

?>
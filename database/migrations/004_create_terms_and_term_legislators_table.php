<?php

class CreateTermsAndTermLegislatorsTable
{
    public function up($pdo)
    {
        $termsSql = "CREATE TABLE IF NOT EXISTS terms (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            session_number INT NOT NULL DEFAULT 1,
            year INT NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 0,
            description TEXT NULL,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            deleted_at TIMESTAMP NULL,
            deleted_by BIGINT NULL,
            created_by BIGINT NULL,
            updated_by BIGINT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_terms_year (year),
            INDEX idx_terms_is_active (is_active),
            INDEX idx_terms_is_deleted (is_deleted),
            INDEX idx_terms_start_date (start_date),
            INDEX idx_terms_end_date (end_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($termsSql);

        $legislatorsSql = "CREATE TABLE IF NOT EXISTS term_legislators (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            term_id BIGINT NOT NULL,
            user_info_id BIGINT NOT NULL,
            role ENUM('Sponsor', 'Author', 'Co-author', 'Ex-officio', 'Guest') NOT NULL DEFAULT 'Author',
            date_assigned DATE NULL,
            notes TEXT NULL,
            created_by BIGINT NULL,
            updated_by BIGINT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tl_term_id (term_id),
            INDEX idx_tl_user_info_id (user_info_id),
            INDEX idx_tl_role (role),
            UNIQUE KEY uk_tl_term_user_role (term_id, user_info_id, role),
            CONSTRAINT fk_tl_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE,
            CONSTRAINT fk_tl_user_info FOREIGN KEY (user_info_id) REFERENCES user_info(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($legislatorsSql);
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS term_legislators;");
        $pdo->exec("DROP TABLE IF EXISTS terms;");
    }
}

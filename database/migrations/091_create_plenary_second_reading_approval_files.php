<?php

class CreatePlenarySecondReadingApprovalFiles
{
    public function up(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS plenary_second_reading_approval_files (
                id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                document_id       BIGINT UNSIGNED NOT NULL,
                reading_id        BIGINT UNSIGNED NOT NULL,
                original_filename VARCHAR(500) NOT NULL,
                stored_filename   VARCHAR(500) NOT NULL,
                file_path         VARCHAR(1000) NOT NULL,
                mime_type         VARCHAR(200) NOT NULL,
                file_size         BIGINT UNSIGNED NOT NULL,
                uploaded_by       BIGINT NULL,
                uploaded_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

                PRIMARY KEY (id),
                INDEX idx_psraf_document (document_id),
                INDEX idx_psraf_reading (reading_id),
                INDEX idx_psraf_uploaded_by (uploaded_by),

                CONSTRAINT fk_psraf_document
                    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE RESTRICT,
                CONSTRAINT fk_psraf_reading
                    FOREIGN KEY (reading_id) REFERENCES plenary_readings(id) ON DELETE CASCADE,
                CONSTRAINT fk_psraf_uploaded_by
                    FOREIGN KEY (uploaded_by) REFERENCES user_accounts(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS plenary_second_reading_approval_files");
    }
}

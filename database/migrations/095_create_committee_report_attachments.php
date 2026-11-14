<?php

class CreateCommitteeReportAttachments
{
    public function up(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS committee_report_attachments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                committee_report_id BIGINT UNSIGNED NOT NULL,
                uploaded_by BIGINT NULL,
                file_name VARCHAR(255) NOT NULL,
                stored_path VARCHAR(500) NOT NULL,
                mime_type VARCHAR(100) NOT NULL,
                file_size BIGINT UNSIGNED NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

                INDEX idx_cra_report (committee_report_id),
                INDEX idx_cra_uploaded_by (uploaded_by),
                CONSTRAINT fk_cra_report
                    FOREIGN KEY (committee_report_id)
                    REFERENCES committee_reports(id) ON DELETE CASCADE,
                CONSTRAINT fk_cra_uploaded_by
                    FOREIGN KEY (uploaded_by)
                    REFERENCES user_accounts(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS committee_report_attachments");
    }
}

<?php

class CommitteeReportAttachmentTransmittals
{
    public function up(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS committee_report_attachment_transmittals (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                committee_report_id BIGINT UNSIGNED NOT NULL,
                attachment_id BIGINT UNSIGNED NOT NULL,
                returned_by BIGINT NULL,
                returned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

                UNIQUE KEY uq_crat_attachment (attachment_id),
                INDEX idx_crat_report (committee_report_id),
                CONSTRAINT fk_crat_report
                    FOREIGN KEY (committee_report_id)
                    REFERENCES committee_reports(id) ON DELETE CASCADE,
                CONSTRAINT fk_crat_attachment
                    FOREIGN KEY (attachment_id)
                    REFERENCES document_attachments(id) ON DELETE RESTRICT,
                CONSTRAINT fk_crat_returned_by
                    FOREIGN KEY (returned_by)
                    REFERENCES user_accounts(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS committee_report_attachment_transmittals");
    }
}
<?php

class CreateCommitteeReportsTables
{
    public function up($pdo)
    {
        // Main committee reports table
        $sql = "CREATE TABLE IF NOT EXISTS committee_reports (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            report_type ENUM('COMMITTEE_REPORT', 'JOINT_COMMITTEE_REPORT') NOT NULL,
            report_number VARCHAR(50) NULL,
            summary_of_findings TEXT NULL,
            created_by BIGINT NULL,
            returned_to_plenary_by BIGINT NULL,
            returned_to_plenary_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            -- Indexes
            INDEX idx_committee_reports_report_type (report_type),
            INDEX idx_committee_reports_created_by (created_by),
            INDEX idx_committee_reports_returned_by (returned_to_plenary_by),
            INDEX idx_committee_reports_created_at (created_at),
            
            -- Foreign Keys
            CONSTRAINT fk_committee_reports_created_by FOREIGN KEY (created_by) REFERENCES user_accounts(id) ON DELETE SET NULL,
            CONSTRAINT fk_committee_reports_returned_by FOREIGN KEY (returned_to_plenary_by) REFERENCES user_accounts(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql);

        // Committee report documents junction table
        $sql2 = "CREATE TABLE IF NOT EXISTS committee_report_documents (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            committee_report_id BIGINT UNSIGNED NOT NULL,
            document_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Unique Constraint
            UNIQUE KEY unique_report_document (committee_report_id, document_id),
            
            -- Indexes
            INDEX idx_committee_report_documents_report (committee_report_id),
            INDEX idx_committee_report_documents_document (document_id),
            
            -- Foreign Keys
            CONSTRAINT fk_committee_report_documents_report FOREIGN KEY (committee_report_id) REFERENCES committee_reports(id) ON DELETE CASCADE,
            CONSTRAINT fk_committee_report_documents_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql2);

        // Committee report committees junction table
        $sql3 = "CREATE TABLE IF NOT EXISTS committee_report_committees (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            committee_report_id BIGINT UNSIGNED NOT NULL,
            committee_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Unique Constraint
            UNIQUE KEY unique_report_committee (committee_report_id, committee_id),
            
            -- Indexes
            INDEX idx_committee_report_committees_report (committee_report_id),
            INDEX idx_committee_report_committees_committee (committee_id),
            
            -- Foreign Keys
            CONSTRAINT fk_committee_report_committees_report FOREIGN KEY (committee_report_id) REFERENCES committee_reports(id) ON DELETE CASCADE,
            CONSTRAINT fk_committee_report_committees_committee FOREIGN KEY (committee_id) REFERENCES committees(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql3);
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS committee_report_committees;");
        $pdo->exec("DROP TABLE IF EXISTS committee_report_documents;");
        $pdo->exec("DROP TABLE IF EXISTS committee_reports;");
    }
}

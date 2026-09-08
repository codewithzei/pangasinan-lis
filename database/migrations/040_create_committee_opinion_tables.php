<?php

class CreateCommitteeOpinionTables
{
    public function up($pdo)
    {
        // Committee cycle endorsements/opinions table
        $sql = "CREATE TABLE IF NOT EXISTS committee_cycle_endorsements (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cycle_id BIGINT UNSIGNED NOT NULL,
            opinion_office_id BIGINT NOT NULL,
            endorsement_number TINYINT NOT NULL CHECK (endorsement_number IN (1, 2)),
            opinion_status_id INT NOT NULL,
            requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            submitted_at TIMESTAMP NULL,
            remarks TEXT NULL,
            
            -- Indexes
            INDEX idx_committee_cycle_endorsements_cycle (cycle_id),
            INDEX idx_committee_cycle_endorsements_office (opinion_office_id),
            INDEX idx_committee_cycle_endorsements_status (opinion_status_id),
            INDEX idx_committee_cycle_endorsements_number (endorsement_number),
            
            -- Foreign Keys
            CONSTRAINT fk_committee_cycle_endorsements_cycle FOREIGN KEY (cycle_id) REFERENCES committee_cycles(id) ON DELETE CASCADE,
            CONSTRAINT fk_committee_cycle_endorsements_office FOREIGN KEY (opinion_office_id) REFERENCES opinion_offices(id) ON DELETE RESTRICT,
            CONSTRAINT fk_committee_cycle_endorsements_status FOREIGN KEY (opinion_status_id) REFERENCES opinion_statuses(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql);

        // Opinion submission details
        $sql2 = "CREATE TABLE IF NOT EXISTS committee_endorsement_submissions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            endorsement_id BIGINT UNSIGNED NOT NULL,
            opinion_type ENUM('FAVORABLE', 'UNFAVORABLE') NOT NULL,
            opinion_file_attachment_id BIGINT UNSIGNED NOT NULL,
            compliance_file_attachment_id BIGINT UNSIGNED NULL,
            submitted_by BIGINT NULL,
            submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            remarks TEXT NULL,
            
            -- Indexes
            INDEX idx_committee_endorsement_submissions_endorsement (endorsement_id),
            INDEX idx_committee_endorsement_submissions_opinion_type (opinion_type),
            INDEX idx_committee_endorsement_submissions_submitted_by (submitted_by),
            
            -- Foreign Keys
            CONSTRAINT fk_committee_endorsement_submissions_endorsement FOREIGN KEY (endorsement_id) REFERENCES committee_cycle_endorsements(id) ON DELETE CASCADE,
            CONSTRAINT fk_committee_endorsement_submissions_opinion_file FOREIGN KEY (opinion_file_attachment_id) REFERENCES document_attachments(id) ON DELETE RESTRICT,
            CONSTRAINT fk_committee_endorsement_submissions_compliance_file FOREIGN KEY (compliance_file_attachment_id) REFERENCES document_attachments(id) ON DELETE RESTRICT,
            CONSTRAINT fk_committee_endorsement_submissions_submitted_by FOREIGN KEY (submitted_by) REFERENCES user_accounts(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql2);

        // Opinion resolutions
        $sql3 = "CREATE TABLE IF NOT EXISTS committee_opinion_resolutions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cycle_id BIGINT UNSIGNED NOT NULL UNIQUE,
            resolution ENUM('PROCEED_TO_AGENDA', 'WITHDRAW_DOCUMENT') NOT NULL,
            resolved_by BIGINT NULL,
            remarks TEXT NULL,
            resolved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Indexes
            INDEX idx_committee_opinion_resolutions_cycle (cycle_id),
            INDEX idx_committee_opinion_resolutions_resolution (resolution),
            INDEX idx_committee_opinion_resolutions_resolved_by (resolved_by),
            
            -- Foreign Keys
            CONSTRAINT fk_committee_opinion_resolutions_cycle FOREIGN KEY (cycle_id) REFERENCES committee_cycles(id) ON DELETE CASCADE,
            CONSTRAINT fk_committee_opinion_resolutions_resolved_by FOREIGN KEY (resolved_by) REFERENCES user_accounts(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql3);
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS committee_opinion_resolutions;");
        $pdo->exec("DROP TABLE IF EXISTS committee_endorsement_submissions;");
        $pdo->exec("DROP TABLE IF EXISTS committee_cycle_endorsements;");
    }
}

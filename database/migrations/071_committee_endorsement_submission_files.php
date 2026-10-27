<?php

/**
 * Migration 071 — Multiple Opinion / Compliance files per endorsement submission
 *
 * committee_endorsement_submissions stores a single opinion file and a single
 * compliance file via the FK columns opinion_file_attachment_id /
 * compliance_file_attachment_id. The Committee workflow now allows several
 * Opinion files and several Compliance files per submission.
 *
 * This migration adds a join table that links one submission to many
 * document_attachments rows, tagging each link with its file_type. Existing
 * single-file attachments are backfilled into the new table so no historical
 * attachment data is lost. The original FK columns are kept (they continue to
 * reference the first file of each type) for backwards compatibility.
 */
class CommitteeEndorsementSubmissionFiles
{
    public function up($pdo)
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS committee_endorsement_submission_files (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            submission_id BIGINT UNSIGNED NOT NULL,
            attachment_id BIGINT UNSIGNED NOT NULL,
            file_type ENUM('OPINION', 'COMPLIANCE') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            -- Indexes
            UNIQUE KEY uq_cesf_submission_attachment (submission_id, attachment_id),
            INDEX idx_cesf_submission (submission_id),
            INDEX idx_cesf_attachment (attachment_id),
            INDEX idx_cesf_file_type (file_type),

            -- Foreign Keys
            CONSTRAINT fk_cesf_submission FOREIGN KEY (submission_id) REFERENCES committee_endorsement_submissions(id) ON DELETE CASCADE,
            CONSTRAINT fk_cesf_attachment FOREIGN KEY (attachment_id) REFERENCES document_attachments(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Backfill existing single opinion file per submission.
        $pdo->exec("
            INSERT INTO committee_endorsement_submission_files (submission_id, attachment_id, file_type)
            SELECT id, opinion_file_attachment_id, 'OPINION'
              FROM committee_endorsement_submissions
             WHERE opinion_file_attachment_id IS NOT NULL
            ON DUPLICATE KEY UPDATE submission_id = VALUES(submission_id)
        ");

        // Backfill existing single compliance file per submission.
        $pdo->exec("
            INSERT INTO committee_endorsement_submission_files (submission_id, attachment_id, file_type)
            SELECT id, compliance_file_attachment_id, 'COMPLIANCE'
              FROM committee_endorsement_submissions
             WHERE compliance_file_attachment_id IS NOT NULL
            ON DUPLICATE KEY UPDATE submission_id = VALUES(submission_id)
        ");
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS committee_endorsement_submission_files;");
    }
}

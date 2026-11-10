<?php

/**
 * Migration 082 — Create document_filing_attachments join table.
 *
 * The shared document_attachments table already stores uploaded files for
 * every phase (including 'FILING_FILE' typed rows). This migration adds an
 * explicit join table that links a document_filings record to one or more
 * document_attachments rows, making the "final scanned attachments for a
 * filing" relationship explicit and queryable without scanning attachment_type.
 *
 * The document_attachments FK uses ON DELETE CASCADE so that when an
 * attachment row is deleted the join row disappears with it (no orphans).
 * The document_filings FK uses ON DELETE CASCADE for the same reason.
 *
 * Safe to run multiple times — uses CREATE TABLE IF NOT EXISTS.
 */
class CreateDocumentFilingAttachmentsTable
{
    public function up(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS document_filing_attachments (
                id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                filing_id      BIGINT UNSIGNED NOT NULL,
                attachment_id  BIGINT UNSIGNED NOT NULL,
                created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                UNIQUE KEY unique_filing_attachment (filing_id, attachment_id),

                INDEX idx_dfa_filing     (filing_id),
                INDEX idx_dfa_attachment (attachment_id),

                CONSTRAINT fk_dfa_filing
                    FOREIGN KEY (filing_id)
                    REFERENCES document_filings(id)
                    ON DELETE CASCADE,

                CONSTRAINT fk_dfa_attachment
                    FOREIGN KEY (attachment_id)
                    REFERENCES document_attachments(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS document_filing_attachments;");
    }
}

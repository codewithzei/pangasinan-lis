<?php

/**
 * Migration 080 — plenary_cases_adoption_files
 *
 * Adds the attachment table for the "Adopted Committee Report" modal that
 * appears on the Plenary → Referred-to-Committee → Returned tab for
 * Complaints / Administrative Cases documents.
 *
 * RELATIONSHIP
 * ────────────
 * Every row in this table is linked to:
 *   – document_id        — the parent document
 *   – adoption_id        — the plenary_cases_adoptions row (required; the
 *                          files are captured at adoption time)
 *   – first_reading_id   — the plenary_cases_first_reading row (nullable
 *                          convenience FK kept parallel to comm table)
 *
 * UPLOAD CONVENTIONS (match the rest of the codebase)
 * ────────────────────────────────────────────────────
 *   directory : public/uploads/documents/{document_id}/
 *   filename  : cases_adopt_{document_id}_{uniqid()}.{ext}
 *   file_path : relative from public/ root (stored in DB)
 *
 * IDEMPOTENT — safe to re-run.
 */
class PlenaryCasesAdoptionFiles
{
    // =========================================================================
    // UP
    // =========================================================================

    public function up(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS plenary_cases_adoption_files (
                id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                document_id      BIGINT UNSIGNED NOT NULL,
                adoption_id      BIGINT UNSIGNED NOT NULL
                    COMMENT 'FK to plenary_cases_adoptions.id',
                first_reading_id BIGINT UNSIGNED NULL
                    COMMENT 'FK to plenary_cases_first_reading.id (convenience)',
                original_filename VARCHAR(500)  NOT NULL,
                stored_filename   VARCHAR(500)  NOT NULL,
                file_path         VARCHAR(1000) NOT NULL
                    COMMENT 'Relative path from public/ root',
                mime_type         VARCHAR(200)  NOT NULL DEFAULT '',
                file_size         BIGINT        NOT NULL DEFAULT 0
                    COMMENT 'Bytes',
                uploaded_by       BIGINT        NULL,
                uploaded_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                ON UPDATE CURRENT_TIMESTAMP,

                PRIMARY KEY (id),
                INDEX idx_pcaf_document   (document_id),
                INDEX idx_pcaf_adoption   (adoption_id),
                INDEX idx_pcaf_fr         (first_reading_id),
                INDEX idx_pcaf_uploader   (uploaded_by),

                CONSTRAINT fk_pcaf_document     FOREIGN KEY (document_id)
                    REFERENCES documents(id) ON DELETE RESTRICT,
                CONSTRAINT fk_pcaf_adoption     FOREIGN KEY (adoption_id)
                    REFERENCES plenary_cases_adoptions(id) ON DELETE CASCADE,
                CONSTRAINT fk_pcaf_first_reading FOREIGN KEY (first_reading_id)
                    REFERENCES plenary_cases_first_reading(id) ON DELETE SET NULL,
                CONSTRAINT fk_pcaf_uploader     FOREIGN KEY (uploaded_by)
                    REFERENCES user_accounts(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    // =========================================================================
    // DOWN
    // =========================================================================

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS plenary_cases_adoption_files");
    }
}

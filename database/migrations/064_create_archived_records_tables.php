<?php

/**
 * Migration 064 — Create archived_records and related tables
 *
 * Tables created:
 *   archived_records             — Core legislative record (approved resolution/ordinance/etc.)
 *   archived_records_authors     — Authors and co-authors (SP member or custom name)
 *   archived_records_committees  — Committees assigned to the record
 *   archived_records_attachments — File attachments uploaded against the record
 *
 * Constraints:
 *   - document_type_id and custom_document_type_name are mutually exclusive (CHECK).
 *   - archived_records_authors: sp_member_id and custom_author_name are mutually exclusive.
 *   - archived_records_committees: committee_id and custom_committee_name are mutually exclusive.
 *   - approved_no is unique across all records.
 *
 * Column type notes (must match referenced table PKs):
 *   document_types.id    = INT (signed)
 *   committees.id        = INT (signed)
 *   sp_members.sp_member_id = BIGINT UNSIGNED
 *   user_accounts.id     = BIGINT (signed)
 */
class CreateArchivedRecordsTables
{
    public function up(PDO $pdo): void
    {
        // ── 1. archived_records ────────────────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS archived_records (
                id                        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                approved_no               VARCHAR(100)  NOT NULL COMMENT 'Resolution / Ordinance number',
                date_approved             DATE          NOT NULL,
                subject_matter            TEXT          NOT NULL,
                description               TEXT          NULL,
                tags                      VARCHAR(500)  NULL COMMENT 'Comma-separated tags',
                document_type_id          INT           NULL,
                custom_document_type_name VARCHAR(200)  NULL,
                is_active                 TINYINT(1)    NOT NULL DEFAULT 1,
                is_deleted                TINYINT(1)    NOT NULL DEFAULT 0,
                created_by                BIGINT        NOT NULL,
                updated_by                BIGINT        NOT NULL,
                created_at                TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at                TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT unique_ar_approved_no UNIQUE (approved_no),
                CONSTRAINT chk_ar_doc_type_one_source CHECK (
                    (document_type_id IS NOT NULL AND custom_document_type_name IS NULL)
                    OR
                    (document_type_id IS NULL AND custom_document_type_name IS NOT NULL)
                ),
                INDEX idx_ar_date_approved    (date_approved),
                INDEX idx_ar_document_type_id (document_type_id),
                INDEX idx_ar_is_deleted       (is_deleted),
                INDEX idx_ar_is_active        (is_active),
                CONSTRAINT fk_ar_document_type FOREIGN KEY (document_type_id)
                    REFERENCES document_types(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // ── 2. archived_records_authors ────────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS archived_records_authors (
                id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                archived_record_id  BIGINT UNSIGNED   NOT NULL,
                sp_member_id        BIGINT UNSIGNED   NULL,
                custom_author_name  VARCHAR(200)      NULL,
                author_role         ENUM('AUTHOR','CO_AUTHOR') NOT NULL DEFAULT 'AUTHOR',
                sort_order          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                CONSTRAINT chk_ara_one_source CHECK (
                    (sp_member_id IS NOT NULL AND custom_author_name IS NULL)
                    OR
                    (sp_member_id IS NULL AND custom_author_name IS NOT NULL)
                ),
                INDEX idx_ara_archived_record_id (archived_record_id),
                INDEX idx_ara_sp_member_id       (sp_member_id),
                CONSTRAINT fk_ara_archived_record FOREIGN KEY (archived_record_id)
                    REFERENCES archived_records(id) ON DELETE CASCADE,
                CONSTRAINT fk_ara_sp_member FOREIGN KEY (sp_member_id)
                    REFERENCES sp_members(sp_member_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // ── 3. archived_records_committees ────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS archived_records_committees (
                id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                archived_record_id    BIGINT UNSIGNED NOT NULL,
                committee_id          INT             NULL,
                custom_committee_name VARCHAR(200)    NULL,
                assigned_by           BIGINT          NULL,
                sort_order            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                CONSTRAINT chk_arc_one_source CHECK (
                    (committee_id IS NOT NULL AND custom_committee_name IS NULL)
                    OR
                    (committee_id IS NULL AND custom_committee_name IS NOT NULL)
                ),
                INDEX idx_arc_archived_record_id (archived_record_id),
                INDEX idx_arc_committee_id       (committee_id),
                CONSTRAINT fk_arc_archived_record FOREIGN KEY (archived_record_id)
                    REFERENCES archived_records(id) ON DELETE CASCADE,
                CONSTRAINT fk_arc_committee FOREIGN KEY (committee_id)
                    REFERENCES committees(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // ── 4. archived_records_attachments ───────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS archived_records_attachments (
                id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                archived_record_id  BIGINT UNSIGNED NOT NULL,
                file_name           VARCHAR(500)    NOT NULL,
                file_size           BIGINT UNSIGNED NOT NULL DEFAULT 0,
                mime_type           VARCHAR(200)    NOT NULL DEFAULT 'application/octet-stream',
                file_path           VARCHAR(1000)   NOT NULL COMMENT 'Path relative to STORAGE_PATH',
                uploaded_by         BIGINT          NULL,
                uploaded_at         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_aratt_archived_record_id (archived_record_id),
                CONSTRAINT fk_aratt_archived_record FOREIGN KEY (archived_record_id)
                    REFERENCES archived_records(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS archived_records_attachments;");
        $pdo->exec("DROP TABLE IF EXISTS archived_records_committees;");
        $pdo->exec("DROP TABLE IF EXISTS archived_records_authors;");
        $pdo->exec("DROP TABLE IF EXISTS archived_records;");
    }
}

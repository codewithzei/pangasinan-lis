<?php

/**
 * Migration 049 — Split documents.subject_matter_document_type into
 *                 subject_matter (TEXT) + document_type_id (INT NULL FK)
 *
 * Migration 029 created the documents table with a single combined column
 * `subject_matter_document_type TEXT NOT NULL`.  Every controller, view,
 * service, and query in the application references two separate columns:
 *   - subject_matter     TEXT NOT NULL
 *   - document_type_id   INT  NULL  (FK → document_types.id)
 *
 * This migration:
 *   1. Adds the two replacement columns.
 *   2. Copies the content of the old column into subject_matter so that any
 *      existing rows are not silently lost.
 *   3. Drops the old combined column.
 *   4. Adds the FK constraint for document_type_id.
 *
 * The table is empty in development (0 rows at time of authoring), so the
 * data-copy step is a safety measure only.
 */
class SplitSubjectMatterDocumentTypeColumn
{
    public function up(PDO $pdo): void
    {
        // 1a. Add subject_matter as NULL first (TEXT cannot have a DEFAULT value
        //     in MySQL), then populate it from the old column, then tighten to NOT NULL.
        $pdo->exec("
            ALTER TABLE documents
                ADD COLUMN subject_matter   TEXT NULL AFTER time_received,
                ADD COLUMN document_type_id INT  NULL AFTER subject_matter
        ");

        // 2. Migrate existing data: treat the old combined text as subject_matter.
        //    document_type_id cannot be reliably parsed from free text, so it
        //    stays NULL for pre-existing rows — operators can correct if needed.
        $pdo->exec("
            UPDATE documents
               SET subject_matter = COALESCE(subject_matter_document_type, '')
        ");

        // 1b. Now that every row has a value, tighten subject_matter to NOT NULL.
        $pdo->exec("
            ALTER TABLE documents
                MODIFY COLUMN subject_matter TEXT NOT NULL
        ");

        // 3. Drop the old combined column now that data is migrated.
        $pdo->exec("
            ALTER TABLE documents
                DROP COLUMN subject_matter_document_type
        ");

        // 4. Add foreign-key index + constraint for document_type_id.
        $pdo->exec("
            ALTER TABLE documents
                ADD INDEX idx_documents_document_type (document_type_id),
                ADD CONSTRAINT fk_documents_document_type
                    FOREIGN KEY (document_type_id)
                    REFERENCES document_types(id)
                    ON DELETE RESTRICT
        ");
    }

    public function down(PDO $pdo): void
    {
        // Remove FK and index added in up().
        $pdo->exec("
            ALTER TABLE documents
                DROP FOREIGN KEY fk_documents_document_type,
                DROP INDEX idx_documents_document_type
        ");

        // Restore the combined column (add nullable, fill, tighten).
        $pdo->exec("
            ALTER TABLE documents
                ADD COLUMN subject_matter_document_type TEXT NULL AFTER time_received
        ");

        // Best-effort: copy subject_matter back.
        $pdo->exec("
            UPDATE documents
               SET subject_matter_document_type = COALESCE(subject_matter, '')
        ");

        $pdo->exec("
            ALTER TABLE documents
                MODIFY COLUMN subject_matter_document_type TEXT NOT NULL
        ");

        // Remove the split columns.
        $pdo->exec("
            ALTER TABLE documents
                DROP COLUMN subject_matter,
                DROP COLUMN document_type_id
        ");
    }
}

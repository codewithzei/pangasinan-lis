<?php

/**
 * Migration 050: Add accepted_by to document_assignments
 *
 * Adds explicit ownership tracking for the Accept/Claim workflow.
 *
 * accepted_by  — the user who clicked Accept and claimed the document.
 *                Distinct from assigned_by (who created the assignment)
 *                and from assigned_to_user_id (which can be used for
 *                pre-assigned routing).
 *
 * For PENDING assignments accepted_by IS NULL (unclaimed).
 * The Accept action atomically sets both accepted_by and assigned_to_user_id
 * to the authenticated user's ID so that ownership is stored in two places:
 *   - accepted_by          → accountability / who claimed it
 *   - assigned_to_user_id  → inbox filtering / ownership gate
 *
 * Existing ACCEPTED rows where accepted_by would be NULL are left as-is
 * (legacy/unattributed).  Historical investigation should use audit_logs
 * and ADMIN_ACCEPTED workflow events.
 */
class AddAcceptedByToDocumentAssignments
{
    public function up(PDO $pdo): void
    {
        // Guard: skip if the column already exists (idempotent re-run).
        $cols = $pdo->query("SHOW COLUMNS FROM document_assignments LIKE 'accepted_by'")->fetchAll();
        if (!empty($cols)) {
            return;
        }

        // 1. Add accepted_by column after accepted_at
        $pdo->exec("
            ALTER TABLE document_assignments
            ADD COLUMN accepted_by BIGINT NULL
                AFTER accepted_at,
            ADD INDEX idx_document_assignments_accepted_by (accepted_by),
            ADD CONSTRAINT fk_document_assignments_accepted_by
                FOREIGN KEY (accepted_by)
                REFERENCES user_accounts(id)
                ON DELETE SET NULL
        ");
    }

    public function down(PDO $pdo): void
    {
        // Guard: skip if the column does not exist.
        $cols = $pdo->query("SHOW COLUMNS FROM document_assignments LIKE 'accepted_by'")->fetchAll();
        if (empty($cols)) {
            return;
        }

        $pdo->exec("
            ALTER TABLE document_assignments
            DROP FOREIGN KEY fk_document_assignments_accepted_by,
            DROP INDEX       idx_document_assignments_accepted_by,
            DROP COLUMN      accepted_by
        ");
    }
}

<?php

/**
 * Migration 048 — Add ADMIN_FILE to document_attachments.attachment_type ENUM
 *
 * The AdminInboxController previously used 'ADMIN_FILE' as the attachment_type
 * when uploading additional files from the Admin inbox.  That value was missing
 * from the ENUM defined in migration 030, which caused a MySQL data-truncation
 * error on every Admin attachment upload.
 *
 * This migration adds 'ADMIN_FILE' to the ENUM so Admin-uploaded attachments
 * are stored with a meaningful type instead of falling back to 'OTHER'.
 *
 * Once this migration has been applied, DocumentService::processAdditionalAttachments()
 * can be updated to pass 'ADMIN_FILE' instead of 'OTHER'.
 */
class AddAdminFileAttachmentType
{
    public function up($pdo): void
    {
        $pdo->exec("
            ALTER TABLE document_attachments
            MODIFY COLUMN attachment_type ENUM(
                'RECEIVING_FILE',
                'ADMIN_FILE',
                'OPINION_FILE',
                'COMPLIANCE_FILE',
                'COMMITTEE_REPORT',
                'APPROVAL_FILE',
                'FILING_FILE',
                'OTHER'
            ) NOT NULL DEFAULT 'OTHER'
        ");
    }

    public function down($pdo): void
    {
        // Note: removing an ENUM value that existing rows reference will cause
        // a data-truncation error.  The down migration converts ADMIN_FILE rows
        // to 'OTHER' first, then drops the value.
        $pdo->exec("
            UPDATE document_attachments
            SET attachment_type = 'OTHER'
            WHERE attachment_type = 'ADMIN_FILE'
        ");

        $pdo->exec("
            ALTER TABLE document_attachments
            MODIFY COLUMN attachment_type ENUM(
                'RECEIVING_FILE',
                'OPINION_FILE',
                'COMPLIANCE_FILE',
                'COMMITTEE_REPORT',
                'APPROVAL_FILE',
                'FILING_FILE',
                'OTHER'
            ) NOT NULL DEFAULT 'OTHER'
        ");
    }
}

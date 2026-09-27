<?php

/**
 * Migration 063 — Add SP_SECRETARY_FILE to document_attachments.attachment_type ENUM
 *
 * The SpsecInboxController uploads files via DocumentService::processAdditionalAttachments()
 * with phase 'SP_SECRETARY'. Without a dedicated attachment_type value the row would fall
 * back to 'OTHER', losing phase traceability.
 *
 * Cumulative from migration 051 (which added COMMITTEE_FILE).
 */
class AddSpsecFileAttachmentType
{
    public function up(PDO $pdo): void
    {
        $pdo->exec("
            ALTER TABLE document_attachments
            MODIFY COLUMN attachment_type ENUM(
                'RECEIVING_FILE',
                'ADMIN_FILE',
                'SPSEC_FILE',
                'COMMITTEE_FILE',
                'OPINION_FILE',
                'COMPLIANCE_FILE',
                'COMMITTEE_REPORT',
                'APPROVAL_FILE',
                'FILING_FILE',
                'OTHER'
            ) NOT NULL DEFAULT 'OTHER'
        ");
    }

    public function down(PDO $pdo): void
    {
        // Convert SPSEC_FILE rows to OTHER before removing the value
        $pdo->exec("
            UPDATE document_attachments
            SET attachment_type = 'OTHER'
            WHERE attachment_type = 'SPSEC_FILE'
        ");

        $pdo->exec("
            ALTER TABLE document_attachments
            MODIFY COLUMN attachment_type ENUM(
                'RECEIVING_FILE',
                'ADMIN_FILE',
                'COMMITTEE_FILE',
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

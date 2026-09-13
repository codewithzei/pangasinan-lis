<?php

/**
 * Migration 046: Admin Inbox Schema Additions
 *
 * Changes:
 * 1. Make document_revisions.document_type_id nullable (existing code omits it).
 * 2. Expand document_events.event_type ENUM with Admin routing event types.
 * 3. Expand document_attachments.attachment_type ENUM with ADMIN_FILE.
 * 4. Add communication_category_id to documents for NOTED workflow.
 * 5. Expand notifications.type ENUM with DOCUMENT_ROUTED.
 */
class AdminInboxSchemaAdditions
{
    public function up(PDO $pdo): void
    {
        // 1. Make document_revisions.document_type_id nullable
        //    The existing receiving submit omits this column entirely.
        $pdo->exec("
            ALTER TABLE document_revisions
            MODIFY COLUMN document_type_id INT NULL
        ");

        // 2. Expand document_events.event_type ENUM
        //    Add: ROUTED_TO_SP_SECRETARY, ROUTED_TO_PLENARY, ROUTED_TO_COMMITTEE,
        //         DOCUMENT_NOTED, ADMIN_RETURNED_TO_RECEIVING
        $pdo->exec("
            ALTER TABLE document_events
            MODIFY COLUMN event_type ENUM(
                'DOCUMENT_RECEIVED',
                'ROUTED_TO_ADMIN',
                'ADMIN_ACCEPTED',
                'ADMIN_DECLINED',
                'ADMIN_RETURNED_TO_RECEIVING',
                'ROUTED_TO_SP_SECRETARY',
                'ROUTED_TO_PLENARY',
                'ROUTED_TO_COMMITTEE',
                'DOCUMENT_NOTED',
                'DOCUMENT_EDITED',
                'SP_SECRETARY_REJECTED',
                'SUBJECT_MATTER_CHANGED',
                'REFERRED_TO_COMMITTEE',
                'OPINION_REQUESTED',
                'AGENDA_SCHEDULED',
                'HEARING_COMPLETED',
                'RETURNED_TO_PLENARY',
                'SECOND_READING_APPROVED',
                'DOCUMENT_FINALIZED',
                'DOCUMENT_FILED',
                'OTHER'
            ) NOT NULL
        ");

        // 3. Expand document_attachments.attachment_type ENUM with ADMIN_FILE
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

        // 4. Add communication_category_id to documents table
        //    Used when Admin marks a Communication document as NOTED.
        $pdo->exec("
            ALTER TABLE documents
            ADD COLUMN communication_category_id INT NULL
                AFTER current_phase,
            ADD INDEX idx_documents_comm_category (communication_category_id),
            ADD CONSTRAINT fk_documents_comm_category
                FOREIGN KEY (communication_category_id)
                REFERENCES communication_categories(id)
                ON DELETE RESTRICT
        ");

        // 5. Expand notifications.type ENUM with DOCUMENT_ROUTED
        $pdo->exec("
            ALTER TABLE notifications
            MODIFY COLUMN type ENUM(
                'DOCUMENT_ASSIGNED',
                'DOCUMENT_ACCEPTED',
                'DOCUMENT_DECLINED',
                'DOCUMENT_REJECTED',
                'DOCUMENT_RETURNED',
                'DOCUMENT_ROUTED',
                'OPINION_REQUESTED',
                'COMPLIANCE_REQUIRED',
                'AGENDA_SCHEDULED',
                'HEARING_ACTION_REQUIRED',
                'REPORT_RETURNED',
                'DOCUMENT_FINALIZED',
                'DOCUMENT_READY_FOR_FILING',
                'OTHER'
            ) NOT NULL
        ");
    }

    public function down(PDO $pdo): void
    {
        // Reverse communication_category_id addition
        $pdo->exec("
            ALTER TABLE documents
            DROP FOREIGN KEY fk_documents_comm_category,
            DROP INDEX idx_documents_comm_category,
            DROP COLUMN communication_category_id
        ");

        // Reverse document_revisions nullable
        // Note: rows with NULL document_type_id must be removed or set before this runs.
        $pdo->exec("
            ALTER TABLE document_revisions
            MODIFY COLUMN document_type_id INT NOT NULL
        ");

        // Revert document_events.event_type to original ENUM
        $pdo->exec("
            ALTER TABLE document_events
            MODIFY COLUMN event_type ENUM(
                'DOCUMENT_RECEIVED',
                'ROUTED_TO_ADMIN',
                'ADMIN_ACCEPTED',
                'ADMIN_DECLINED',
                'DOCUMENT_EDITED',
                'SP_SECRETARY_REJECTED',
                'SUBJECT_MATTER_CHANGED',
                'REFERRED_TO_COMMITTEE',
                'OPINION_REQUESTED',
                'AGENDA_SCHEDULED',
                'HEARING_COMPLETED',
                'RETURNED_TO_PLENARY',
                'SECOND_READING_APPROVED',
                'DOCUMENT_FINALIZED',
                'DOCUMENT_FILED',
                'OTHER'
            ) NOT NULL
        ");

        // Revert document_attachments.attachment_type
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

        // Revert notifications.type
        $pdo->exec("
            ALTER TABLE notifications
            MODIFY COLUMN type ENUM(
                'DOCUMENT_ASSIGNED',
                'DOCUMENT_ACCEPTED',
                'DOCUMENT_DECLINED',
                'DOCUMENT_REJECTED',
                'DOCUMENT_RETURNED',
                'OPINION_REQUESTED',
                'COMPLIANCE_REQUIRED',
                'AGENDA_SCHEDULED',
                'HEARING_ACTION_REQUIRED',
                'REPORT_RETURNED',
                'DOCUMENT_FINALIZED',
                'DOCUMENT_READY_FOR_FILING',
                'OTHER'
            ) NOT NULL
        ");
    }
}

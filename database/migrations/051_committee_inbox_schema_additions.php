<?php

/**
 * Migration 051 — Committee Inbox Schema Additions
 *
 * 1. Expand document_events.event_type ENUM with Committee-specific events:
 *    COMMITTEE_ACCEPTED, COMMITTEE_RETURNED_TO_ADMIN
 *
 * 2. Add COMMITTEE_FILE to document_attachments.attachment_type ENUM so that
 *    files uploaded from the Committee inbox have a meaningful type.
 *
 * Follows the same pattern as migration 046 (admin inbox additions) and
 * migration 048 (ADMIN_FILE attachment type).
 */
class CommitteeInboxSchemaAdditions
{
    public function up(PDO $pdo): void
    {
        // 1. Expand document_events.event_type ENUM
        //    Adding: COMMITTEE_ACCEPTED, COMMITTEE_RETURNED_TO_ADMIN
        //    Full list is cumulative — must include every value from migration 046.
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
                'SP_SECRETARY_ACCEPTED',
                'SP_SECRETARY_RETURNED_TO_ADMIN',
                'COMMITTEE_ACCEPTED',
                'COMMITTEE_RETURNED_TO_ADMIN',
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

        // 2. Expand document_attachments.attachment_type ENUM with COMMITTEE_FILE
        //    Cumulative from migration 048 (which added ADMIN_FILE).
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

    public function down(PDO $pdo): void
    {
        // Convert COMMITTEE_FILE rows to 'OTHER' before removing the value
        $pdo->exec("
            UPDATE document_attachments
            SET attachment_type = 'OTHER'
            WHERE attachment_type = 'COMMITTEE_FILE'
        ");

        // Revert attachment_type ENUM (back to migration 048 state)
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

        // Revert event_type ENUM (back to migration 046 state)
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
    }
}

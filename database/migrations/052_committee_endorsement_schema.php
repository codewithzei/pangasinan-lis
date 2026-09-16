<?php

/**
 * Migration 052 — Committee Endorsement Schema
 *
 * 1. Expand document_events.event_type ENUM with:
 *    COMMITTEE_ENDORSED_REFERRED  — document endorsed as Referred by Committee
 *
 * Follows the same cumulative pattern as migration 051.
 * No new tables are required: the existing committee_cycles table represents
 * the Referred state (a completed cycle), and document_status id=8 ('Referred')
 * already exists in document_statuses.
 */
class CommitteeEndorsementSchema
{
    public function up(PDO $pdo): void
    {
        // Expand document_events.event_type ENUM — cumulative from migration 051
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
                'COMMITTEE_ENDORSED_REFERRED',
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

    public function down(PDO $pdo): void
    {
        // Convert any COMMITTEE_ENDORSED_REFERRED rows to OTHER before removing
        $pdo->exec("
            UPDATE document_events
            SET event_type = 'OTHER'
            WHERE event_type = 'COMMITTEE_ENDORSED_REFERRED'
        ");

        // Revert to migration 051 state
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
    }
}

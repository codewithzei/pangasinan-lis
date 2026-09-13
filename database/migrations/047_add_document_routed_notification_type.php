<?php

/**
 * Migration: Add DOCUMENT_ROUTED to notifications.type ENUM
 * 
 * This migration adds the DOCUMENT_ROUTED notification type to support
 * routing notifications when documents are routed between roles.
 */
class AddDocumentRoutedNotificationType
{
    public function up($pdo)
    {
        // Add DOCUMENT_ROUTED to the notifications.type ENUM
        $sql = "ALTER TABLE notifications 
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
                ) NOT NULL";

        $pdo->exec($sql);
    }

    public function down($pdo)
    {
        // Remove DOCUMENT_ROUTED from the ENUM (rollback)
        $sql = "ALTER TABLE notifications 
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
                ) NOT NULL";

        $pdo->exec($sql);
    }
}

<?php

/**
 * Migration 070 — Synchronize Plenary Status with Shared Document Status
 *
 * This migration ensures that documents with Plenary pre-decision statuses
 * have their shared document status (documents.current_status_id) synchronized
 * with plenary_document_details.status_id.
 *
 * This is a one-time data migration to fix any existing inconsistencies before
 * the application logic takes over automatic synchronization.
 */
class SynchronizePlenaryStatusWithSharedDocumentStatus
{
    public function up(PDO $pdo): void
    {
        // Update documents.current_status_id to match plenary_document_details.status_id
        // for all documents that have Plenary details but may have diverged statuses
        $pdo->exec("
            UPDATE documents d
            INNER JOIN plenary_document_details pdd ON pdd.document_id = d.id
            SET d.current_status_id = pdd.status_id,
                d.updated_by = COALESCE(d.updated_by, 1)
            WHERE pdd.status_id IS NOT NULL
              AND d.current_status_id != pdd.status_id
        ");
    }

    public function down(PDO $pdo): void
    {
        // This is a data synchronization migration - rollback is not applicable
        // The application logic will now keep these synchronized going forward
        // No down() needed as this is a one-time data fix
    }
}

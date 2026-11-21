<?php

class AutoApproveMfrHearings
{
    public function up(PDO $pdo): void
    {
        $pdo->exec("
            ALTER TABLE committee_hearings
                MODIFY COLUMN agenda_id BIGINT UNSIGNED NULL
        ");

        $pdo->exec("
            INSERT INTO committee_hearings
                (document_id, agenda_id, outcome, remarks, performed_by, performed_at, created_at)
            SELECT d.id, NULL, 'APPROVED',
                   'Automatically approved for Motion for Reconsideration; no agenda or hearing required.',
                   comm.assigned_by, NOW(), NOW()
            FROM documents d
            INNER JOIN document_types dt ON dt.id = d.document_type_id
            INNER JOIN committee_communications comm ON comm.document_id = d.id
            WHERE dt.name = 'Motion for Reconsideration'
              AND NOT EXISTS (
                  SELECT 1 FROM committee_hearings ch
                  WHERE ch.document_id = d.id
              )
        ");

        $pdo->exec("
            UPDATE committee_communications comm
            INNER JOIN committee_hearings ch
                ON ch.document_id = comm.document_id
               AND ch.agenda_id IS NULL
               AND ch.outcome = 'APPROVED'
            INNER JOIN document_types dt
                ON dt.name = 'Motion for Reconsideration'
            INNER JOIN documents d
                ON d.id = comm.document_id
               AND d.document_type_id = dt.id
            SET comm.hearing_id = ch.id
            WHERE comm.hearing_id IS NULL
        ");

        $pdo->exec("
            UPDATE documents d
            INNER JOIN document_types dt ON dt.id = d.document_type_id
            INNER JOIN committee_hearings ch
                ON ch.document_id = d.id
               AND ch.agenda_id IS NULL
               AND ch.outcome = 'APPROVED'
            INNER JOIN document_statuses current_status
                ON current_status.id = d.current_status_id
               AND current_status.name = 'Communication In Progress'
            INNER JOIN document_statuses completed_status
                ON completed_status.name = 'Hearing Completed'
               AND completed_status.is_active = 1
               AND completed_status.is_deleted = 0
            SET d.current_status_id = completed_status.id
            WHERE dt.name = 'Motion for Reconsideration'
        ");

        $pdo->exec("
            INSERT INTO document_events (
                document_id, event_type, phase, performed_by,
                from_status_id, to_status_id, remarks, metadata
            )
            SELECT d.id, 'HEARING_COMPLETED', 'COMMITTEE', ch.performed_by,
                   NULL, d.current_status_id,
                   'Motion for Reconsideration automatically approved; no agenda or hearing required.',
                   JSON_OBJECT(
                       'hearing_id', ch.id,
                       'outcome', 'APPROVED',
                       'auto_approved', TRUE,
                       'reason', 'MOTION_FOR_RECONSIDERATION'
                   )
            FROM documents d
            INNER JOIN document_types dt ON dt.id = d.document_type_id
            INNER JOIN committee_hearings ch
                ON ch.document_id = d.id
               AND ch.agenda_id IS NULL
               AND ch.outcome = 'APPROVED'
            WHERE dt.name = 'Motion for Reconsideration'
              AND NOT EXISTS (
                  SELECT 1
                  FROM document_events de
                  WHERE de.document_id = d.id
                    AND de.event_type = 'HEARING_COMPLETED'
                    AND de.remarks = 'Motion for Reconsideration automatically approved; no agenda or hearing required.'
              )
        ");
    }

    public function down(PDO $pdo): void
    {
        throw new RuntimeException(
            'Migration 106 is irreversible because it creates approved hearing records for existing MFR documents.'
        );
    }
}

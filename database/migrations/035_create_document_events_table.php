<?php

class CreateDocumentEventsTable
{
    public function up($pdo)
    {
        $sql = "CREATE TABLE IF NOT EXISTS document_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            document_id BIGINT UNSIGNED NOT NULL,
            event_type ENUM(
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
            ) NOT NULL,
            phase ENUM('RECEIVING', 'ADMIN', 'SP_SECRETARY', 'PLENARY', 'COMMITTEE', 'FINALIZED', 'FILED') NOT NULL,
            performed_by BIGINT NULL,
            from_status_id INT NULL,
            to_status_id INT NULL,
            from_owner_user_id BIGINT NULL,
            to_owner_user_id BIGINT NULL,
            remarks TEXT NULL,
            metadata JSON NULL COMMENT 'Additional event-specific data',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Indexes
            INDEX idx_document_events_document (document_id),
            INDEX idx_document_events_event_type (event_type),
            INDEX idx_document_events_phase (phase),
            INDEX idx_document_events_performed_by (performed_by),
            INDEX idx_document_events_created_at (created_at),
            
            -- Foreign Keys
            CONSTRAINT fk_document_events_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE RESTRICT,
            CONSTRAINT fk_document_events_performed_by FOREIGN KEY (performed_by) REFERENCES user_accounts(id) ON DELETE SET NULL,
            CONSTRAINT fk_document_events_from_status FOREIGN KEY (from_status_id) REFERENCES document_statuses(id) ON DELETE RESTRICT,
            CONSTRAINT fk_document_events_to_status FOREIGN KEY (to_status_id) REFERENCES document_statuses(id) ON DELETE RESTRICT,
            CONSTRAINT fk_document_events_from_owner FOREIGN KEY (from_owner_user_id) REFERENCES user_accounts(id) ON DELETE SET NULL,
            CONSTRAINT fk_document_events_to_owner FOREIGN KEY (to_owner_user_id) REFERENCES user_accounts(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql);
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS document_events;");
    }
}

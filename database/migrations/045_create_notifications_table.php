<?php

class CreateNotificationsTable
{
    public function up($pdo)
    {
        $sql = "CREATE TABLE IF NOT EXISTS notifications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            document_id BIGINT UNSIGNED NULL,
            recipient_user_id BIGINT NOT NULL,
            sender_user_id BIGINT NULL,
            type ENUM(
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
            ) NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            action_url VARCHAR(500) NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            read_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Indexes
            INDEX idx_notifications_recipient_read (recipient_user_id, is_read),
            INDEX idx_notifications_document (document_id),
            INDEX idx_notifications_type (type),
            INDEX idx_notifications_created_at (created_at),
            INDEX idx_notifications_sender (sender_user_id),
            
            -- Foreign Keys
            CONSTRAINT fk_notifications_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
            CONSTRAINT fk_notifications_recipient FOREIGN KEY (recipient_user_id) REFERENCES user_accounts(id) ON DELETE CASCADE,
            CONSTRAINT fk_notifications_sender FOREIGN KEY (sender_user_id) REFERENCES user_accounts(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql);
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS notifications;");
    }
}

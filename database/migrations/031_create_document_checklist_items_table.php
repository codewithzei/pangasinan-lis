<?php

class CreateDocumentChecklistItemsTable
{
    public function up($pdo)
    {
        $sql = "CREATE TABLE IF NOT EXISTS document_checklist_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            document_id BIGINT UNSIGNED NOT NULL,
            checklist_id BIGINT NOT NULL,
            is_completed TINYINT(1) NOT NULL DEFAULT 0,
            completed_by BIGINT NULL,
            completed_at TIMESTAMP NULL,
            remarks TEXT NULL,
            
            -- Unique Constraint
            UNIQUE KEY unique_document_checklist (document_id, checklist_id),
            
            -- Indexes
            INDEX idx_document_checklist_items_document (document_id),
            INDEX idx_document_checklist_items_checklist (checklist_id),
            INDEX idx_document_checklist_items_completed (is_completed),
            INDEX idx_document_checklist_items_completed_by (completed_by),
            
            -- Foreign Keys
            CONSTRAINT fk_document_checklist_items_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
            CONSTRAINT fk_document_checklist_items_checklist FOREIGN KEY (checklist_id) REFERENCES checklists(id) ON DELETE RESTRICT,
            CONSTRAINT fk_document_checklist_items_completed_by FOREIGN KEY (completed_by) REFERENCES user_accounts(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql);
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS document_checklist_items;");
    }
}

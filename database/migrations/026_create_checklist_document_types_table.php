<?php

class CreateChecklistDocumentTypesTable
{
    public function up($pdo)
    {
        $sql = "CREATE TABLE IF NOT EXISTS checklist_document_types (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            checklist_id BIGINT NOT NULL,
            document_type_id INT NOT NULL,
            is_required TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            notes TEXT NULL,
            created_by BIGINT NULL,
            updated_by BIGINT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_cdt_checklist_id (checklist_id),
            INDEX idx_cdt_document_type_id (document_type_id),
            INDEX idx_cdt_sort_order (sort_order),
            UNIQUE KEY uk_cdt_checklist_doctype (checklist_id, document_type_id),
            CONSTRAINT fk_cdt_checklist FOREIGN KEY (checklist_id) REFERENCES checklists(id) ON DELETE CASCADE,
            CONSTRAINT fk_cdt_document_type FOREIGN KEY (document_type_id) REFERENCES document_types(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql);
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS checklist_document_types;");
    }
}

<?php

class CreateDocumentAuthorsTable
{
    public function up($pdo)
    {
        $sql = "CREATE TABLE IF NOT EXISTS document_authors (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            document_id BIGINT UNSIGNED NOT NULL,
            sp_member_id BIGINT UNSIGNED NOT NULL,
            author_role ENUM('AUTHOR', 'CO_AUTHOR') NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Unique Constraint: same member cannot have multiple roles for same document
            UNIQUE KEY unique_document_member_role (document_id, sp_member_id, author_role),
            
            -- Indexes
            INDEX idx_document_authors_document (document_id),
            INDEX idx_document_authors_sp_member (sp_member_id),
            INDEX idx_document_authors_role (author_role),
            INDEX idx_document_authors_sort_order (sort_order),
            
            -- Foreign Keys
            CONSTRAINT fk_document_authors_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
            CONSTRAINT fk_document_authors_sp_member FOREIGN KEY (sp_member_id) REFERENCES sp_members(sp_member_id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql);
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS document_authors;");
    }
}

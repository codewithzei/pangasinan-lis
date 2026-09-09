<?php

class AlterDocumentsCombineSubjectMatterDocumentType
{
    public function up($pdo)
    {
        // First, create the new combined column
        $sql1 = "ALTER TABLE documents 
                 ADD COLUMN subject_matter_document_type TEXT NOT NULL AFTER time_received";
        $pdo->exec($sql1);
        
        // Migrate existing data: combine subject_matter and document_type name
        $sql2 = "UPDATE documents d
                 INNER JOIN document_types dt ON d.document_type_id = dt.id
                 SET d.subject_matter_document_type = CONCAT(d.subject_matter, ' [', dt.name, ']')";
        $pdo->exec($sql2);
        
        // Drop the foreign key constraint first
        $sql3 = "ALTER TABLE documents 
                 DROP FOREIGN KEY fk_documents_document_type";
        $pdo->exec($sql3);
        
        // Drop the index
        $sql4 = "ALTER TABLE documents 
                 DROP INDEX idx_documents_document_type";
        $pdo->exec($sql4);
        
        // Now drop the old columns
        $sql5 = "ALTER TABLE documents 
                 DROP COLUMN subject_matter,
                 DROP COLUMN document_type_id";
        $pdo->exec($sql5);
    }

    public function down($pdo)
    {
        // Restore the old structure
        $sql1 = "ALTER TABLE documents 
                 ADD COLUMN subject_matter TEXT NOT NULL AFTER time_received,
                 ADD COLUMN document_type_id INT NOT NULL AFTER subject_matter";
        $pdo->exec($sql1);
        
        // Add back the index
        $sql2 = "ALTER TABLE documents 
                 ADD INDEX idx_documents_document_type (document_type_id)";
        $pdo->exec($sql2);
        
        // Add back the foreign key
        $sql3 = "ALTER TABLE documents 
                 ADD CONSTRAINT fk_documents_document_type 
                 FOREIGN KEY (document_type_id) REFERENCES document_types(id) ON DELETE RESTRICT";
        $pdo->exec($sql3);
        
        // Remove the combined column
        $sql4 = "ALTER TABLE documents 
                 DROP COLUMN subject_matter_document_type";
        $pdo->exec($sql4);
    }
}

<?php

class AddBadgeColorToDocumentTypesTable
{
    public function up($pdo)
    {
        $checkSql = "SHOW COLUMNS FROM document_types LIKE 'badge_color';";
        $exists = $pdo->query($checkSql)->fetch();

        if (!$exists) {
            $sql = "ALTER TABLE document_types ADD COLUMN badge_color VARCHAR(7) NOT NULL DEFAULT '#2563EB' AFTER description;";
            $pdo->exec($sql);
        }
    }

    public function down($pdo)
    {
        $checkSql = "SHOW COLUMNS FROM document_types LIKE 'badge_color';";
        $exists = $pdo->query($checkSql)->fetch();

        if ($exists) {
            $sql = "ALTER TABLE document_types DROP COLUMN badge_color;";
            $pdo->exec($sql);
        }
    }
}

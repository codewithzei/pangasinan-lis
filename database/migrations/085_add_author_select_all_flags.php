<?php

class AddAuthorSelectAllFlags
{
    public function up(PDO $pdo): void
    {
        $columns = [
            'plenary_document_details' => ['authors_all', 'co_authors_all'],
            'archived_records' => ['authors_all', 'co_authors_all'],
        ];

        foreach ($columns as $table => $names) {
            foreach ($names as $name) {
                if (!$this->columnExists($pdo, $table, $name)) {
                    $pdo->exec(
                        "ALTER TABLE {$table} ADD COLUMN {$name} TINYINT(1) NOT NULL DEFAULT 0"
                    );
                }
            }
        }

        $pdo->exec("
            UPDATE plenary_document_details pdd
            SET authors_all = (
                    SELECT IF(
                        COUNT(DISTINCT sm.sp_member_id) > 0
                        AND COUNT(DISTINCT sm.sp_member_id) = (
                            SELECT COUNT(*)
                            FROM sp_members
                            WHERE is_active = 1 AND is_deleted = 0
                        ),
                        1,
                        0
                    )
                    FROM document_authors da
                    INNER JOIN sp_members sm ON sm.sp_member_id = da.sp_member_id
                        AND sm.is_active = 1 AND sm.is_deleted = 0
                    WHERE da.document_id = pdd.document_id
                      AND da.author_role = 'AUTHOR'
                ),
                co_authors_all = (
                    SELECT IF(
                        COUNT(DISTINCT sm.sp_member_id) > 0
                        AND COUNT(DISTINCT sm.sp_member_id) = (
                            SELECT COUNT(*)
                            FROM sp_members
                            WHERE is_active = 1 AND is_deleted = 0
                        ),
                        1,
                        0
                    )
                    FROM document_authors da
                    INNER JOIN sp_members sm ON sm.sp_member_id = da.sp_member_id
                        AND sm.is_active = 1 AND sm.is_deleted = 0
                    WHERE da.document_id = pdd.document_id
                      AND da.author_role = 'CO_AUTHOR'
                )
        ");

        $pdo->exec("
            UPDATE archived_records ar
            SET authors_all = (
                    SELECT IF(
                        COUNT(DISTINCT sm.sp_member_id) > 0
                        AND COUNT(DISTINCT sm.sp_member_id) = (
                            SELECT COUNT(*)
                            FROM sp_members
                            WHERE is_active = 1 AND is_deleted = 0
                        ),
                        1,
                        0
                    )
                    FROM archived_records_authors ara
                    INNER JOIN sp_members sm ON sm.sp_member_id = ara.sp_member_id
                        AND sm.is_active = 1 AND sm.is_deleted = 0
                    WHERE ara.archived_record_id = ar.id
                      AND ara.author_role = 'AUTHOR'
                ),
                co_authors_all = (
                    SELECT IF(
                        COUNT(DISTINCT sm.sp_member_id) > 0
                        AND COUNT(DISTINCT sm.sp_member_id) = (
                            SELECT COUNT(*)
                            FROM sp_members
                            WHERE is_active = 1 AND is_deleted = 0
                        ),
                        1,
                        0
                    )
                    FROM archived_records_authors ara
                    INNER JOIN sp_members sm ON sm.sp_member_id = ara.sp_member_id
                        AND sm.is_active = 1 AND sm.is_deleted = 0
                    WHERE ara.archived_record_id = ar.id
                      AND ara.author_role = 'CO_AUTHOR'
                )
        ");
    }

    public function down(PDO $pdo): void
    {
        foreach (['plenary_document_details', 'archived_records'] as $table) {
            foreach (['authors_all', 'co_authors_all'] as $column) {
                if ($this->columnExists($pdo, $table, $column)) {
                    $pdo->exec("ALTER TABLE {$table} DROP COLUMN {$column}");
                }
            }
        }
    }

    private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$table, $column]);

        return (int) $stmt->fetchColumn() > 0;
    }
}

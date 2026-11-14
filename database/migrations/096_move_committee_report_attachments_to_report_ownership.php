<?php

class MoveCommitteeReportAttachmentsToReportOwnership
{
    private const SOURCE_COLUMNS = [
        'source_document_id' => 'BIGINT UNSIGNED NULL',
        'source_document_attachment_id' => 'BIGINT UNSIGNED NULL',
        'source_phase' => "ENUM('RECEIVING','ADMIN','SP_SECRETARY','PLENARY','COMMITTEE','FINALIZED','FILED') NULL",
        'source_transmittal_id' => 'BIGINT UNSIGNED NULL',
        'returned_by' => 'BIGINT NULL',
        'returned_at' => 'TIMESTAMP NULL',
    ];

    public function up(PDO $pdo): void
    {
        foreach (self::SOURCE_COLUMNS as $column => $definition) {
            if (!$this->columnExists($pdo, $column)) {
                $pdo->exec("ALTER TABLE committee_report_attachments ADD COLUMN `{$column}` {$definition}");
            }
        }

        if (!$this->indexExists($pdo, 'uq_cra_source_attachment')) {
            $pdo->exec("ALTER TABLE committee_report_attachments ADD UNIQUE KEY uq_cra_source_attachment (source_document_attachment_id)");
        }

        $pdo->exec("
            INSERT INTO committee_report_attachments
                (committee_report_id, uploaded_by, file_name, stored_path, mime_type, file_size, created_at,
                 source_document_id, source_document_attachment_id, source_phase,
                 source_transmittal_id, returned_by, returned_at)
            SELECT crd.committee_report_id, da.uploaded_by, da.file_name, da.stored_path,
                   da.mime_type, da.file_size, da.created_at,
                   da.document_id, da.id, da.phase,
                   crt.id, crt.returned_by, crt.returned_at
            FROM document_attachments da
            INNER JOIN committee_report_documents crd ON crd.document_id = da.document_id
            LEFT JOIN committee_report_attachment_transmittals crt
                ON crt.attachment_id = da.id
               AND crt.committee_report_id = crd.committee_report_id
            WHERE da.attachment_type = 'COMMITTEE_REPORT'
            ON DUPLICATE KEY UPDATE
                source_document_attachment_id = VALUES(source_document_attachment_id)
        ");

        $pdo->exec("
            DELETE crt
            FROM committee_report_attachment_transmittals crt
            INNER JOIN document_attachments da ON da.id = crt.attachment_id
            INNER JOIN committee_report_documents crd
                ON crd.committee_report_id = crt.committee_report_id
               AND crd.document_id = da.document_id
            WHERE da.attachment_type = 'COMMITTEE_REPORT'
        ");

        $pdo->exec("
            DELETE da
            FROM document_attachments da
            INNER JOIN committee_report_documents crd ON crd.document_id = da.document_id
            WHERE da.attachment_type = 'COMMITTEE_REPORT'
        ");
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("
            INSERT INTO document_attachments
                (id, document_id, uploaded_by, phase, file_name, stored_path, mime_type, file_size,
                 attachment_type, created_at)
            SELECT source_document_attachment_id, source_document_id, uploaded_by, source_phase,
                   file_name, stored_path, mime_type, file_size, 'COMMITTEE_REPORT', created_at
            FROM committee_report_attachments
            WHERE source_document_attachment_id IS NOT NULL
            ON DUPLICATE KEY UPDATE id = VALUES(id)
        ");

        $pdo->exec("
            INSERT INTO committee_report_attachment_transmittals
                (id, committee_report_id, attachment_id, returned_by, returned_at)
            SELECT source_transmittal_id, committee_report_id, source_document_attachment_id,
                   returned_by, returned_at
            FROM committee_report_attachments
            WHERE source_transmittal_id IS NOT NULL
            ON DUPLICATE KEY UPDATE id = VALUES(id)
        ");

        if ($this->indexExists($pdo, 'uq_cra_source_attachment')) {
            $pdo->exec("ALTER TABLE committee_report_attachments DROP INDEX uq_cra_source_attachment");
        }
        foreach (array_keys(self::SOURCE_COLUMNS) as $column) {
            if ($this->columnExists($pdo, $column)) {
                $pdo->exec("ALTER TABLE committee_report_attachments DROP COLUMN `{$column}`");
            }
        }
    }

    private function columnExists(PDO $pdo, string $column): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'committee_report_attachments'
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function indexExists(PDO $pdo, string $indexName): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'committee_report_attachments'
              AND INDEX_NAME = ?
        ");
        $stmt->execute([$indexName]);
        return (int) $stmt->fetchColumn() > 0;
    }
}

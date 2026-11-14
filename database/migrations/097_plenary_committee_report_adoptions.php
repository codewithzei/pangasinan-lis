<?php

class PlenaryCommitteeReportAdoptions
{
    public function up(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS plenary_committee_report_adoptions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                document_id BIGINT UNSIGNED NOT NULL,
                committee_report_id BIGINT UNSIGNED NOT NULL,
                decision ENUM('APPROVED', 'DISAPPROVED', 'ADOPTED') NOT NULL,
                remarks TEXT NULL,
                adopted_by BIGINT NULL,
                adopted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                PRIMARY KEY (id),
                UNIQUE KEY uq_pcra_document_id (document_id),
                INDEX idx_pcra_report_id (committee_report_id),
                INDEX idx_pcra_decision (decision),
                INDEX idx_pcra_adopted_by (adopted_by),

                CONSTRAINT fk_pcra_document FOREIGN KEY (document_id)
                    REFERENCES documents(id) ON DELETE RESTRICT,
                CONSTRAINT fk_pcra_committee_report FOREIGN KEY (committee_report_id)
                    REFERENCES committee_reports(id) ON DELETE RESTRICT,
                CONSTRAINT fk_pcra_adopted_by FOREIGN KEY (adopted_by)
                    REFERENCES user_accounts(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->backfillLegacySecondReadingApprovals($pdo);
        $this->backfillExistingAdoptions($pdo, 'plenary_comm_adoptions');
        $this->backfillExistingAdoptions($pdo, 'plenary_cases_adoptions');
        $this->ensureDisapprovedStatus($pdo);
        $this->ensureEventTypes($pdo);
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS plenary_committee_report_adoptions");
    }

    private function backfillLegacySecondReadingApprovals(PDO $pdo): void
    {
        $pdo->exec("
            INSERT IGNORE INTO plenary_committee_report_adoptions
                (document_id, committee_report_id, decision, remarks, adopted_by, adopted_at)
            SELECT first_reading.document_id,
                   CAST(JSON_UNQUOTE(JSON_EXTRACT(return_event.metadata, '$.report_id')) AS UNSIGNED),
                   'APPROVED',
                   approval_event.remarks,
                   approval_event.performed_by,
                   approval_event.created_at
            FROM plenary_readings first_reading
            INNER JOIN (
                SELECT document_id, MAX(id) AS event_id
                FROM document_events
                WHERE event_type = 'PLENARY_SECOND_READING_APPROVED'
                  AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.action')) = 'ADVANCE_FROM_COMMITTEE'
                GROUP BY document_id
            ) latest_approval ON latest_approval.document_id = first_reading.document_id
            INNER JOIN document_events approval_event ON approval_event.id = latest_approval.event_id
            INNER JOIN (
                SELECT document_id, MAX(id) AS event_id
                FROM document_events
                WHERE event_type = 'RETURNED_TO_PLENARY'
                GROUP BY document_id
            ) latest_return ON latest_return.document_id = first_reading.document_id
            INNER JOIN document_events return_event ON return_event.id = latest_return.event_id
            INNER JOIN committee_report_documents crd
                ON crd.document_id = first_reading.document_id
               AND crd.committee_report_id =
                   CAST(JSON_UNQUOTE(JSON_EXTRACT(return_event.metadata, '$.report_id')) AS UNSIGNED)
            WHERE first_reading.reading_number = 1
              AND first_reading.action = 'REFERRED_TO_COMMITTEE'
              AND JSON_EXTRACT(return_event.metadata, '$.report_id') IS NOT NULL
        ");
    }

    private function backfillExistingAdoptions(PDO $pdo, string $table): void
    {
        $pdo->exec("
            INSERT IGNORE INTO plenary_committee_report_adoptions
                (document_id, committee_report_id, decision, remarks, adopted_by, adopted_at)
            SELECT adoption.document_id,
                   CAST(JSON_UNQUOTE(JSON_EXTRACT(return_event.metadata, '$.report_id')) AS UNSIGNED),
                   'ADOPTED',
                   adoption.remarks,
                   adoption.adopted_by,
                   adoption.adopted_at
            FROM `{$table}` adoption
            INNER JOIN (
                SELECT document_id, MAX(id) AS event_id
                FROM document_events
                WHERE event_type = 'RETURNED_TO_PLENARY'
                GROUP BY document_id
            ) latest_return ON latest_return.document_id = adoption.document_id
            INNER JOIN document_events return_event ON return_event.id = latest_return.event_id
            INNER JOIN committee_report_documents crd
                ON crd.document_id = adoption.document_id
               AND crd.committee_report_id =
                   CAST(JSON_UNQUOTE(JSON_EXTRACT(return_event.metadata, '$.report_id')) AS UNSIGNED)
            WHERE JSON_EXTRACT(return_event.metadata, '$.report_id') IS NOT NULL
        ");
    }

    private function ensureDisapprovedStatus(PDO $pdo): void
    {
        $pdo->prepare("
            INSERT INTO document_statuses (name, description, badge_color, sort_order, is_active, is_deleted)
            VALUES (?, ?, ?, ?, 1, 0)
            ON DUPLICATE KEY UPDATE
                description = VALUES(description),
                badge_color = VALUES(badge_color),
                is_active = 1,
                is_deleted = 0
        ")->execute([
            'Plenary: Committee Report Adopted',
            'Committee report adopted without advancing the document to Second Reading.',
            '#6B7280',
            134,
        ]);
    }

    private function ensureEventTypes(PDO $pdo): void
    {
        $stmt = $pdo->query("
            SELECT COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'document_events'
              AND COLUMN_NAME = 'event_type'
            LIMIT 1
        ");
        $columnType = $stmt->fetchColumn();
        if (!is_string($columnType) || !preg_match("/^enum\\((.+)\\)$/i", $columnType, $matches)) {
            throw new RuntimeException('Unable to inspect document_events.event_type enum.');
        }

        preg_match_all("/'([^']+)'/", $matches[1], $values);
        $existing = $values[1];
        $newValues = array_values(array_diff(
            [
                'PLENARY_COMMITTEE_REPORT_APPROVED',
                'PLENARY_COMMITTEE_REPORT_DISAPPROVED',
            ],
            $existing
        ));
        if ($newValues === []) {
            return;
        }

        $merged = array_merge($existing, $newValues);
        $enumList = implode(',', array_map(
            static fn(string $value): string => "'" . str_replace("'", "''", $value) . "'",
            $merged
        ));
        $pdo->exec("ALTER TABLE document_events MODIFY COLUMN event_type ENUM({$enumList}) NOT NULL");
    }
}

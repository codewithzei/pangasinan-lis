<?php

/**
 * Migration 103 — Document resubmission references.
 *
 * Stores the relationship between a newly received resubmission and its prior
 * Remanded, Deferred, or Withdrawn document.
 */
class DocumentResubmissionLinks
{
    private const NEW_EVENT_TYPES = ['RESUBMISSION_LINKED'];

    public function up(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS document_resubmission_links (
                id                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                resubmitted_document_id  BIGINT UNSIGNED NOT NULL,
                referenced_document_id   BIGINT UNSIGNED NOT NULL,
                routing_option_id        INT NULL,
                linked_by                BIGINT NULL,
                linked_at                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                   ON UPDATE CURRENT_TIMESTAMP,

                PRIMARY KEY (id),
                UNIQUE KEY uq_drl_resubmitted_document (resubmitted_document_id),
                INDEX idx_drl_referenced_document (referenced_document_id),

                CONSTRAINT fk_drl_resubmitted_document
                    FOREIGN KEY (resubmitted_document_id) REFERENCES documents (id)
                    ON DELETE RESTRICT,
                CONSTRAINT fk_drl_referenced_document
                    FOREIGN KEY (referenced_document_id) REFERENCES documents (id)
                    ON DELETE RESTRICT,
                CONSTRAINT fk_drl_routing_option
                    FOREIGN KEY (routing_option_id) REFERENCES routing_options (id)
                    ON DELETE SET NULL,
                CONSTRAINT fk_drl_linked_by
                    FOREIGN KEY (linked_by) REFERENCES user_accounts (id)
                    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addEnumValues($pdo, self::NEW_EVENT_TYPES);
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS document_resubmission_links');

        $column = $pdo->query("
            SELECT COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'document_events'
              AND COLUMN_NAME = 'event_type'
            LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);

        if ($column && preg_match("/^enum\\((.+)\\)$/i", $column['COLUMN_TYPE'], $matches)) {
            preg_match_all("/'([^']+)'/", $matches[1], $values);
            $remaining = array_values(array_filter(
                $values[1],
                static fn(string $value): bool => !in_array($value, self::NEW_EVENT_TYPES, true)
            ));
            if ($remaining) {
                $enumList = implode(',', array_map(
                    static fn(string $value): string => "'" . str_replace("'", "''", $value) . "'",
                    $remaining
                ));
                $pdo->exec("ALTER TABLE document_events MODIFY COLUMN event_type ENUM({$enumList}) NOT NULL");
            }
        }
    }

    private function addEnumValues(PDO $pdo, array $newValues): void
    {
        $column = $pdo->query("
            SELECT COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'document_events'
              AND COLUMN_NAME = 'event_type'
            LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);

        if (!$column || !preg_match("/^enum\\((.+)\\)$/i", $column['COLUMN_TYPE'], $matches)) {
            throw new RuntimeException('Unable to read document_events.event_type enum values.');
        }

        preg_match_all("/'([^']+)'/", $matches[1], $currentValues);
        $values = $currentValues[1];
        foreach ($newValues as $value) {
            if (!in_array($value, $values, true)) {
                $values[] = $value;
            }
        }

        $enumList = implode(',', array_map(
            static fn(string $value): string => "'" . str_replace("'", "''", $value) . "'",
            $values
        ));
        $pdo->exec("ALTER TABLE document_events MODIFY COLUMN event_type ENUM({$enumList}) NOT NULL");
    }
}

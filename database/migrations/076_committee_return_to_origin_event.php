<?php

/**
 * Migration 076 — Committee return-to-origin event type
 *
 * The Committee inbox "Return" action returns a document to the office/phase
 * that actually sent it to Committee (derived from the latest inbound
 * document_routes row or, as a fallback, the previous non-Committee
 * assignment). That origin is not always Admin, so a generic event type is
 * needed in addition to the existing 'COMMITTEE_RETURNED_TO_ADMIN'.
 *
 * Adds document_events.event_type:
 *   COMMITTEE_RETURNED_TO_ORIGIN
 *
 * Uses the same dynamic ENUM-expansion pattern as migrations 058 / 059 / 060
 * / 061 so it stays correct no matter which migrations ran before it.
 */
class CommitteeReturnToOriginEvent
{
    private const EVENT_TYPE = 'COMMITTEE_RETURNED_TO_ORIGIN';

    public function up(PDO $pdo): void
    {
        $values = $this->currentEventTypes($pdo);

        if (!in_array(self::EVENT_TYPE, $values, true)) {
            $values[]  = self::EVENT_TYPE;
            $enumList  = implode(',', array_map(fn($v) => "'{$v}'", $values));
            $pdo->exec("ALTER TABLE document_events MODIFY COLUMN event_type ENUM({$enumList}) NOT NULL");
        }
    }

    public function down(PDO $pdo): void
    {
        $values = $this->currentEventTypes($pdo);

        $reverted = array_values(array_filter(
            $values,
            fn($v) => $v !== self::EVENT_TYPE
        ));

        if (!empty($reverted)) {
            $enumList = implode(',', array_map(fn($v) => "'{$v}'", $reverted));
            $pdo->exec("ALTER TABLE document_events MODIFY COLUMN event_type ENUM({$enumList}) NOT NULL");
        }
    }

    /** Read the current ENUM values of document_events.event_type. */
    private function currentEventTypes(PDO $pdo): array
    {
        $row = $pdo->query("
            SELECT COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'document_events'
              AND COLUMN_NAME  = 'event_type'
            LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);

        if ($row && preg_match("/^enum\((.+)\)$/i", $row['COLUMN_TYPE'], $m)) {
            preg_match_all("/'([^']+)'/", $m[1], $matches);
            return $matches[1];
        }

        return [];
    }
}

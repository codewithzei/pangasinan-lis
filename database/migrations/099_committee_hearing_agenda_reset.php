<?php

class CommitteeHearingAgendaReset
{
    private const OUTCOME = 'RESET';
    private const EVENT = 'HEARING_AGENDA_RESET';

    public function up(PDO $pdo): void
    {
        $this->updateEnum($pdo, 'committee_hearings', 'outcome', self::OUTCOME, true);
        $this->updateEnum($pdo, 'document_events', 'event_type', self::EVENT, true);
    }

    public function down(PDO $pdo): void
    {
        $outcomeCount = $pdo->prepare("
            SELECT COUNT(*) FROM committee_hearings WHERE outcome = ?
        ");
        $outcomeCount->execute([self::OUTCOME]);
        if ((int) $outcomeCount->fetchColumn() > 0) {
            throw new RuntimeException('Cannot remove RESET while hearing records use it.');
        }

        $eventCount = $pdo->prepare("
            SELECT COUNT(*) FROM document_events WHERE event_type = ?
        ");
        $eventCount->execute([self::EVENT]);
        if ((int) $eventCount->fetchColumn() > 0) {
            throw new RuntimeException('Cannot remove HEARING_AGENDA_RESET while document events use it.');
        }

        $this->updateEnum($pdo, 'committee_hearings', 'outcome', self::OUTCOME, false);
        $this->updateEnum($pdo, 'document_events', 'event_type', self::EVENT, false);
    }

    private function updateEnum(PDO $pdo, string $table, string $column, string $value, bool $add): void
    {
        $stmt = $pdo->prepare("
            SELECT COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1
        ");
        $stmt->execute([$table, $column]);
        $columnType = $stmt->fetchColumn();
        if (!is_string($columnType) || !preg_match('/^enum\((.*)\)$/i', $columnType, $matches)) {
            throw new RuntimeException("Unable to inspect {$table}.{$column} enum.");
        }

        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $matches[1], $enumMatches);
        $values = array_map(
            static fn(string $enumValue): string => stripcslashes($enumValue),
            $enumMatches[1]
        );
        if ($values === []) {
            throw new RuntimeException("{$table}.{$column} has no enum values.");
        }

        if ($add) {
            if (in_array($value, $values, true)) {
                return;
            }
            $values[] = $value;
        } else {
            $values = array_values(array_filter(
                $values,
                static fn(string $enumValue): bool => $enumValue !== $value
            ));
        }

        $enumList = implode(',', array_map(
            static fn(string $enumValue): string => "'" . str_replace(
                ["\\", "'"],
                ["\\\\", "\\'"],
                $enumValue
            ) . "'",
            $values
        ));
        $pdo->exec("ALTER TABLE {$table} MODIFY COLUMN {$column} ENUM({$enumList}) NOT NULL");
    }
}

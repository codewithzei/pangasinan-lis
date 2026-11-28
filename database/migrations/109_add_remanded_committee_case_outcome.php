<?php

/**
 * Migration 109 — Add Remanded as a Committee Case final outcome.
 */
class AddRemandedCommitteeCaseOutcome
{
    private const OUTCOME = 'REMANDED';

    public function up(PDO $pdo): void
    {
        $values = $this->currentOutcomes($pdo);
        if (!in_array(self::OUTCOME, $values, true)) {
            $values[] = self::OUTCOME;
            $this->updateOutcomes($pdo, $values);
        }
    }

    public function down(PDO $pdo): void
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM committee_cases
            WHERE final_outcome = ?
        ");
        $stmt->execute([self::OUTCOME]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException(
                'Cannot remove the Remanded outcome while committee cases use it.'
            );
        }

        $values = array_values(array_filter(
            $this->currentOutcomes($pdo),
            static fn (string $value): bool => $value !== self::OUTCOME
        ));
        $this->updateOutcomes($pdo, $values);
    }

    private function currentOutcomes(PDO $pdo): array
    {
        $stmt = $pdo->query("
            SELECT COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'committee_cases'
              AND COLUMN_NAME = 'final_outcome'
            LIMIT 1
        ");
        $columnType = $stmt->fetchColumn();

        if (!is_string($columnType)
            || !preg_match("/^enum\\((.+)\\)$/i", $columnType, $matches)
        ) {
            throw new RuntimeException('committee_cases.final_outcome is not an ENUM column.');
        }

        preg_match_all("/'([^']+)'/", $matches[1], $enumMatches);
        return $enumMatches[1];
    }

    private function updateOutcomes(PDO $pdo, array $values): void
    {
        $enumList = implode(',', array_map(
            static fn (string $value): string => $pdo->quote($value),
            $values
        ));
        $pdo->exec("
            ALTER TABLE committee_cases
            MODIFY COLUMN final_outcome ENUM({$enumList}) NULL DEFAULT NULL
        ");
    }
}

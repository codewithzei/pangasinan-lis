<?php

class CommunicationHearingCompletedTerminated
{
    private const OUTCOME = 'COMPLETED_TERMINATED';
    private const STATUS = 'Completed/Terminated';

    public function up(PDO $pdo): void
    {
        $this->updateHearingOutcomeEnum($pdo, true);

        $pdo->prepare("
            INSERT INTO document_statuses
                (name, description, badge_color, sort_order, is_active, is_deleted)
            VALUES (?, ?, ?, ?, 1, 0)
            ON DUPLICATE KEY UPDATE
                description = VALUES(description),
                badge_color = VALUES(badge_color),
                sort_order = VALUES(sort_order),
                is_active = 1,
                is_deleted = 0
        ")->execute([
            self::STATUS,
            'Communication hearing recorded as completed or terminated with no further committee action.',
            '#64748B',
            18,
        ]);
    }

    public function down(PDO $pdo): void
    {
        $outcomeCount = $pdo->prepare("
            SELECT COUNT(*) FROM committee_hearings WHERE outcome = ?
        ");
        $outcomeCount->execute([self::OUTCOME]);
        if ((int)$outcomeCount->fetchColumn() > 0) {
            throw new RuntimeException('Cannot remove COMPLETED_TERMINATED while hearing records use it.');
        }

        $status = $pdo->prepare("SELECT id FROM document_statuses WHERE name = ? LIMIT 1");
        $status->execute([self::STATUS]);
        $statusId = $status->fetchColumn();
        if ($statusId !== false) {
            $documentCount = $pdo->prepare("
                SELECT COUNT(*) FROM documents WHERE current_status_id = ?
            ");
            $documentCount->execute([(int)$statusId]);
            if ((int)$documentCount->fetchColumn() > 0) {
                throw new RuntimeException('Cannot remove Completed/Terminated while documents use this status.');
            }
            $historyCount = $pdo->prepare("
                SELECT COUNT(*) FROM document_events
                WHERE from_status_id = ? OR to_status_id = ?
            ");
            $historyCount->execute([(int)$statusId, (int)$statusId]);
            if ((int)$historyCount->fetchColumn() > 0) {
                throw new RuntimeException('Cannot remove Completed/Terminated while document history references this status.');
            }
            $plenaryCount = $pdo->prepare("
                SELECT COUNT(*) FROM plenary_document_details WHERE status_id = ?
            ");
            $plenaryCount->execute([(int)$statusId]);
            if ((int)$plenaryCount->fetchColumn() > 0) {
                throw new RuntimeException('Cannot remove Completed/Terminated while Plenary records reference this status.');
            }
        }

        $this->updateHearingOutcomeEnum($pdo, false);

        if ($statusId !== false) {
            $deleteStatus = $pdo->prepare("DELETE FROM document_statuses WHERE id = ?");
            $deleteStatus->execute([(int)$statusId]);
        }
    }

    private function updateHearingOutcomeEnum(PDO $pdo, bool $add): void
    {
        $stmt = $pdo->query("
            SELECT COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'committee_hearings'
              AND COLUMN_NAME = 'outcome'
            LIMIT 1
        ");
        $columnType = $stmt->fetchColumn();
        if (!is_string($columnType) || !preg_match('/^enum\((.*)\)$/i', $columnType, $matches)) {
            throw new RuntimeException('Unable to inspect committee_hearings.outcome enum.');
        }

        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $matches[1], $enumMatches);
        $values = array_map(
            static fn(string $value): string => stripcslashes($value),
            $enumMatches[1]
        );
        if ($values === []) {
            throw new RuntimeException('committee_hearings.outcome has no enum values.');
        }

        if ($add && !in_array(self::OUTCOME, $values, true)) {
            $values[] = self::OUTCOME;
        } elseif (!$add) {
            $values = array_values(array_filter(
                $values,
                static fn(string $value): bool => $value !== self::OUTCOME
            ));
        } else {
            return;
        }

        $enumList = implode(',', array_map(
            static fn(string $value): string => "'" . str_replace(
                ["\\", "'"],
                ["\\\\", "\\'"],
                $value
            ) . "'",
            $values
        ));
        $pdo->exec("ALTER TABLE committee_hearings MODIFY COLUMN outcome ENUM({$enumList}) NOT NULL");
    }
}

<?php

/**
 * Migration 101 — Allow any number of Committee opinion endorsements.
 *
 * Unfavorable opinions may be followed by endorsement #3, #4, and so on.
 * The old CHECK constraint and TINYINT column limited that workflow to two.
 * Existing unresolved Unfavorable submissions are moved to their next
 * numbered endorsement status; resolved cycles remain closed.
 */
class UnlimitedCommitteeOpinionEndorsements
{
    public function up(PDO $pdo): void
    {
        $checkStmt = $pdo->prepare("
            SELECT tc.CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS tc
            INNER JOIN information_schema.CHECK_CONSTRAINTS cc
                ON cc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA
               AND cc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
            WHERE tc.TABLE_SCHEMA = DATABASE()
              AND tc.TABLE_NAME = 'committee_cycle_endorsements'
              AND tc.CONSTRAINT_TYPE = 'CHECK'
              AND cc.CHECK_CLAUSE LIKE '%endorsement_number%'
        ");
        $checkStmt->execute();
        foreach ($checkStmt->fetchAll(PDO::FETCH_COLUMN) as $constraintName) {
            $quotedName = str_replace('`', '``', (string) $constraintName);
            $pdo->exec("ALTER TABLE committee_cycle_endorsements DROP CHECK `{$quotedName}`");
        }

        $pdo->exec("
            ALTER TABLE committee_cycle_endorsements
            MODIFY COLUMN endorsement_number BIGINT UNSIGNED NOT NULL
        ");

        $legacyStatusStmt = $pdo->prepare("
            SELECT id FROM opinion_statuses
            WHERE name = 'For Second Endorsement' LIMIT 1
        ");
        $legacyStatusStmt->execute();
        $legacyStatusId = (int) ($legacyStatusStmt->fetchColumn() ?: 0);

        $unresolvedStmt = $pdo->query("
            SELECT cce.id, cce.endorsement_number
            FROM committee_cycle_endorsements cce
            INNER JOIN committee_endorsement_submissions ces
                ON ces.endorsement_id = cce.id
            LEFT JOIN committee_opinion_resolutions cor
                ON cor.cycle_id = cce.cycle_id
            WHERE ces.opinion_type = 'UNFAVORABLE'
              AND cor.id IS NULL
        ");
        $upsertStatus = $pdo->prepare("
            INSERT INTO opinion_statuses
                (name, description, sort_order, is_active, is_deleted)
            VALUES (?, ?, ?, 1, 0)
            ON DUPLICATE KEY UPDATE
                id = LAST_INSERT_ID(id),
                description = VALUES(description),
                sort_order = VALUES(sort_order),
                is_active = 1,
                is_deleted = 0
        ");
        $updateEndorsement = $pdo->prepare("
            UPDATE committee_cycle_endorsements
            SET opinion_status_id = ?
            WHERE id = ?
        ");

        foreach ($unresolvedStmt->fetchAll(PDO::FETCH_ASSOC) as $endorsement) {
            $number = (int) $endorsement['endorsement_number'];
            if ($number < 1 || $number >= PHP_INT_MAX) {
                continue;
            }
            $nextNumber = $number + 1;
            $name = $nextNumber === 2
                ? 'For Second Endorsement'
                : 'For ' . $this->ordinal($nextNumber) . ' Endorsement';
            $upsertStatus->execute([
                $name,
                "Unfavorable opinion submitted; awaiting {$name}.",
                20 + min($nextNumber, 1000000),
            ]);

            $statusStmt = $pdo->prepare("
                SELECT id FROM opinion_statuses
                WHERE name = ? AND is_active = 1 AND is_deleted = 0 LIMIT 1
            ");
            $statusStmt->execute([$name]);
            $statusId = (int) ($statusStmt->fetchColumn() ?: 0);
            if ($statusId > 0) {
                $updateEndorsement->execute([$statusId, $endorsement['id']]);
            }
        }

        // Keep the old #2 state name for backwards compatibility and create
        // the next state for #3 when existing unresolved records require it.
        if ($legacyStatusId > 0) {
            $pdo->prepare("
                UPDATE opinion_statuses
                SET description = 'Unfavorable opinion submitted; awaiting endorsement #2.',
                    is_active = 1,
                    is_deleted = 0
                WHERE id = ?
            ")->execute([$legacyStatusId]);
        }
    }

    public function down(PDO $pdo): void
    {
        $maxNumber = (int) $pdo->query("
            SELECT COALESCE(MAX(endorsement_number), 1)
            FROM committee_cycle_endorsements
        ")->fetchColumn();
        if ($maxNumber > 2) {
            throw new RuntimeException(
                'Migration 101 cannot be reversed while endorsements beyond #2 exist.'
            );
        }

        $pdo->exec("
            ALTER TABLE committee_cycle_endorsements
            MODIFY COLUMN endorsement_number TINYINT NOT NULL,
            ADD CONSTRAINT committee_cycle_endorsements_chk_1
                CHECK (endorsement_number IN (1, 2))
        ");
    }

    private function ordinal(int $number): string
    {
        $lastTwoDigits = $number % 100;
        $suffix = ($lastTwoDigits >= 11 && $lastTwoDigits <= 13)
            ? 'th'
            : match ($number % 10) {
                1 => 'st',
                2 => 'nd',
                3 => 'rd',
                default => 'th',
            };

        return $number . $suffix;
    }
}

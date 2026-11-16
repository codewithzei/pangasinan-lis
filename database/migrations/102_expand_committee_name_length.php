<?php

class ExpandCommitteeNameLength
{
    private const SEEDED_LONG_COMMITTEE_NAMES = [
        3 => 'Committee on Barangay and Rural Development, Public Order and Safety',
        4 => 'Committee on Children, Women, Senior Citizens, Family Affairs and Social Welfare',
        5 => 'Committee on Cooperative, Micro, Small and Medium Business and Entrepreneurship Development',
        8 => 'Committee on Environment, Natural Resources and Energy',
        9 => 'Committee on Good Government and Accountability of Public Officers, Justice and Human Rights',
        11 => 'Committee on Housing, Land Utilization and Agrarian Reform',
        12 => 'Committee on Human Resources and Development, Labor and Employment Concerns',
        13 => 'Committee on Information and Communication Technology and Games and Amusement',
        14 => 'Committee on Infrastructure, Public Services and Utilities',
        15 => 'Committee on Inter-Local Government and People\'s and Non-Governmental Organizations Relations',
        18 => 'Committee on Tourism, Foreign Affairs and Migrant Workers Concerns',
    ];

    public function up(PDO $pdo): void
    {
        $pdo->exec("ALTER TABLE committees MODIFY COLUMN name VARCHAR(255) NOT NULL");

        $repair = $pdo->prepare("
            UPDATE committees
            SET name = ?
            WHERE sort_order = ?
              AND name = LEFT(?, 50)
        ");

        foreach (self::SEEDED_LONG_COMMITTEE_NAMES as $sortOrder => $name) {
            $repair->execute([$name, $sortOrder, $name]);
        }
    }

    public function down(PDO $pdo): void
    {
        $truncate = $pdo->prepare("
            UPDATE committees
            SET name = LEFT(?, 50)
            WHERE sort_order = ?
              AND name = ?
        ");

        foreach (self::SEEDED_LONG_COMMITTEE_NAMES as $sortOrder => $name) {
            $truncate->execute([$name, $sortOrder, $name]);
        }

        $pdo->exec("ALTER TABLE committees MODIFY COLUMN name VARCHAR(50) NOT NULL");
    }
}

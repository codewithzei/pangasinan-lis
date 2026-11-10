<?php

class UpdateProvincialGovernmentOfficesSourceDescription
{
    public function up(PDO $pdo): void
    {
        $pdo->prepare("
            UPDATE source_types
            SET description = ?
            WHERE name = ?
              AND description = ?
        ")->execute([
            'Provincial government offices and agencies',
            'Provincial Government Offices',
            'External government offices and agencies',
        ]);
    }

    public function down(PDO $pdo): void
    {
        $pdo->prepare("
            UPDATE source_types
            SET description = ?
            WHERE name = ?
              AND description = ?
        ")->execute([
            'External government offices and agencies',
            'Provincial Government Offices',
            'Provincial government offices and agencies',
        ]);
    }
}

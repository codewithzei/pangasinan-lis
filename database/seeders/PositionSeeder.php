<?php

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $positions = [
            [
                'name' => 'Vice Governor',
                'abbreviation' => 'VG',
                'description' => 'Presiding Officer of the Sangguniang Panlalawigan',
                'sort_order' => 1,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => 'Board Member',
                'abbreviation' => 'BM',
                'description' => 'Elected member of the Sangguniang Panlalawigan representing a district',
                'sort_order' => 2,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => 'Secretary',
                'abbreviation' => 'SEC',
                'description' => 'SP Secretary — records officer and administrative head of the Sanggunian Secretariat',
                'sort_order' => 3,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => 'Ex-officio',
                'abbreviation' => 'EXO',
                'description' => 'Ex-officio members of the Sanggunian (e.g., PBL, SK Federation, ABC Presidents)',
                'sort_order' => 4,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => 'Sectoral Representative',
                'abbreviation' => 'SEC-REP',
                'description' => 'Sectoral representatives as provided under the Local Government Code',
                'sort_order' => 5,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => 1,
                'updated_by' => 1,
            ],
        ];

        $this->insertOrIgnore('positions', $positions);

        echo "  Inserted " . count($positions) . " positions" . PHP_EOL;
    }
}

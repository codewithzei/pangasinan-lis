<?php

class TermSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');
        $currentYear = (int)date('Y');

        $terms = [
            [
                'name' => '17th Congress - ' . ($currentYear - 2) . ' Regular Session',
                'session_number' => 17,
                'year' => $currentYear - 2,
                'start_date' => ($currentYear - 2) . '-07-01',
                'end_date' => ($currentYear - 1) . '-06-30',
                'is_active' => 0,
                'description' => 'Regular legislative session for FY ' . ($currentYear - 2) . '-' . ($currentYear - 1),
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => '18th Congress - ' . ($currentYear - 1) . ' Regular Session',
                'session_number' => 18,
                'year' => $currentYear - 1,
                'start_date' => ($currentYear - 1) . '-07-01',
                'end_date' => $currentYear . '-06-30',
                'is_active' => 0,
                'description' => 'Regular legislative session for FY ' . ($currentYear - 1) . '-' . $currentYear,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => '19th Congress - ' . $currentYear . ' Regular Session',
                'session_number' => 19,
                'year' => $currentYear,
                'start_date' => $currentYear . '-07-01',
                'end_date' => ($currentYear + 1) . '-06-30',
                'is_active' => 1,
                'description' => 'Current active regular legislative session for FY ' . $currentYear . '-' . ($currentYear + 1),
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => '19th Congress - ' . $currentYear . ' Special Session',
                'session_number' => 19,
                'year' => $currentYear,
                'start_date' => $currentYear . '-01-15',
                'end_date' => $currentYear . '-02-28',
                'is_active' => 0,
                'description' => 'Special session called for urgent legislative matters',
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => '20th Congress - ' . ($currentYear + 1) . ' Regular Session (Proposed)',
                'session_number' => 20,
                'year' => $currentYear + 1,
                'start_date' => ($currentYear + 1) . '-07-01',
                'end_date' => ($currentYear + 2) . '-06-30',
                'is_active' => 0,
                'description' => 'Proposed term for upcoming fiscal year',
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => 1,
                'updated_by' => 1,
            ],
        ];

        $this->insertOrIgnore('terms', $terms);

        echo "  Inserted " . count($terms) . " legislative terms" . PHP_EOL;
    }
}

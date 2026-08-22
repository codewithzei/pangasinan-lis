<?php

class DistrictSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $districts = [
            [
                'name' => '1st District',
                'district_number' => 1,
                'description' => 'First Legislative District of Pangasinan',
                'sort_order' => 1,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => '2nd District',
                'district_number' => 2,
                'description' => 'Second Legislative District of Pangasinan',
                'sort_order' => 2,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => '3rd District',
                'district_number' => 3,
                'description' => 'Third Legislative District of Pangasinan',
                'sort_order' => 3,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => '4th District',
                'district_number' => 4,
                'description' => 'Fourth Legislative District of Pangasinan',
                'sort_order' => 4,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => '5th District',
                'district_number' => 5,
                'description' => 'Fifth Legislative District of Pangasinan',
                'sort_order' => 5,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => '6th District',
                'district_number' => 6,
                'description' => 'Sixth Legislative District of Pangasinan',
                'sort_order' => 6,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => 1,
                'updated_by' => 1,
            ],
        ];

        $this->insertOrIgnore('districts', $districts);

        echo "  Inserted " . count($districts) . " districts" . PHP_EOL;
    }
}

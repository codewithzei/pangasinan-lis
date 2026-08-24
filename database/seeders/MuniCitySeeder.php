<?php

class MuniCitySeeder extends Seeder
{
    public function run(): void
    {
        $districtStmt = $this->pdo->query("SELECT id, district_number FROM districts");
        $districts = $districtStmt->fetchAll(PDO::FETCH_ASSOC);

        $districtMap = [];
        foreach ($districts as $district) {
            $districtMap[(int) $district['district_number']] = (int) $district['id'];
        }

        $now = date('Y-m-d H:i:s');

        $municities = [
            // 1st District
            ['district_number' => 1, 'name' => 'Agno', 'type' => 'Municipality', 'description' => 'Municipality of Agno'],
            ['district_number' => 1, 'name' => 'Alaminos City', 'type' => 'City', 'description' => 'City of Alaminos'],
            ['district_number' => 1, 'name' => 'Anda', 'type' => 'Municipality', 'description' => 'Municipality of Anda'],
            ['district_number' => 1, 'name' => 'Bani', 'type' => 'Municipality', 'description' => 'Municipality of Bani'],
            ['district_number' => 1, 'name' => 'Bolinao', 'type' => 'Municipality', 'description' => 'Municipality of Bolinao'],
            ['district_number' => 1, 'name' => 'Burgos', 'type' => 'Municipality', 'description' => 'Municipality of Burgos'],
            ['district_number' => 1, 'name' => 'Dasol', 'type' => 'Municipality', 'description' => 'Municipality of Dasol'],
            ['district_number' => 1, 'name' => 'Infanta', 'type' => 'Municipality', 'description' => 'Municipality of Infanta'],
            ['district_number' => 1, 'name' => 'Mabini', 'type' => 'Municipality', 'description' => 'Municipality of Mabini'],
            ['district_number' => 1, 'name' => 'Sual', 'type' => 'Municipality', 'description' => 'Municipality of Sual'],

            // 2nd District
            ['district_number' => 2, 'name' => 'Aguilar', 'type' => 'Municipality', 'description' => 'Municipality of Aguilar'],
            ['district_number' => 2, 'name' => 'Basista', 'type' => 'Municipality', 'description' => 'Municipality of Basista'],
            ['district_number' => 2, 'name' => 'Binmaley', 'type' => 'Municipality', 'description' => 'Municipality of Binmaley'],
            ['district_number' => 2, 'name' => 'Bugallon', 'type' => 'Municipality', 'description' => 'Municipality of Bugallon'],
            ['district_number' => 2, 'name' => 'Labrador', 'type' => 'Municipality', 'description' => 'Municipality of Labrador'],
            ['district_number' => 2, 'name' => 'Lingayen', 'type' => 'Municipality', 'description' => 'Municipality of Lingayen'],
            ['district_number' => 2, 'name' => 'Mangatarem', 'type' => 'Municipality', 'description' => 'Municipality of Mangatarem'],
            ['district_number' => 2, 'name' => 'Urbiztondo', 'type' => 'Municipality', 'description' => 'Municipality of Urbiztondo'],

            // 3rd District
            ['district_number' => 3, 'name' => 'Bayambang', 'type' => 'Municipality', 'description' => 'Municipality of Bayambang'],
            ['district_number' => 3, 'name' => 'Calasiao', 'type' => 'Municipality', 'description' => 'Municipality of Calasiao'],
            ['district_number' => 3, 'name' => 'Malasiqui', 'type' => 'Municipality', 'description' => 'Municipality of Malasiqui'],
            ['district_number' => 3, 'name' => 'Mapandan', 'type' => 'Municipality', 'description' => 'Municipality of Mapandan'],
            ['district_number' => 3, 'name' => 'San Carlos City', 'type' => 'City', 'description' => 'City of San Carlos'],
            ['district_number' => 3, 'name' => 'Sta. Barbara', 'type' => 'Municipality', 'description' => 'Municipality of Sta. Barbara'],

            // 4th District
            ['district_number' => 4, 'name' => 'Dagupan City', 'type' => 'City', 'description' => 'City of Dagupan'],
            ['district_number' => 4, 'name' => 'Manaoag', 'type' => 'Municipality', 'description' => 'Municipality of Manaoag'],
            ['district_number' => 4, 'name' => 'Mangaldan', 'type' => 'Municipality', 'description' => 'Municipality of Mangaldan'],
            ['district_number' => 4, 'name' => 'San Fabian', 'type' => 'Municipality', 'description' => 'Municipality of San Fabian'],
            ['district_number' => 4, 'name' => 'San Jacinto', 'type' => 'Municipality', 'description' => 'Municipality of San Jacinto'],

            // 5th District
            ['district_number' => 5, 'name' => 'Alcala', 'type' => 'Municipality', 'description' => 'Municipality of Alcala'],
            ['district_number' => 5, 'name' => 'Bautista', 'type' => 'Municipality', 'description' => 'Municipality of Bautista'],
            ['district_number' => 5, 'name' => 'Binalonan', 'type' => 'Municipality', 'description' => 'Municipality of Binalonan'],
            ['district_number' => 5, 'name' => 'Laoac', 'type' => 'Municipality', 'description' => 'Municipality of Laoac'],
            ['district_number' => 5, 'name' => 'Pozorrubio', 'type' => 'Municipality', 'description' => 'Municipality of Pozorrubio'],
            ['district_number' => 5, 'name' => 'Sison', 'type' => 'Municipality', 'description' => 'Municipality of Sison'],
            ['district_number' => 5, 'name' => 'Sto. Tomas', 'type' => 'Municipality', 'description' => 'Municipality of Sto. Tomas'],
            ['district_number' => 5, 'name' => 'Urdaneta City', 'type' => 'City', 'description' => 'City of Urdaneta'],
            ['district_number' => 5, 'name' => 'Villasis', 'type' => 'Municipality', 'description' => 'Municipality of Villasis'],

            // 6th District
            ['district_number' => 6, 'name' => 'Asingan', 'type' => 'Municipality', 'description' => 'Municipality of Asingan'],
            ['district_number' => 6, 'name' => 'Balungao', 'type' => 'Municipality', 'description' => 'Municipality of Balungao'],
            ['district_number' => 6, 'name' => 'Natividad', 'type' => 'Municipality', 'description' => 'Municipality of Natividad'],
            ['district_number' => 6, 'name' => 'Rosales', 'type' => 'Municipality', 'description' => 'Municipality of Rosales'],
            ['district_number' => 6, 'name' => 'San Manuel', 'type' => 'Municipality', 'description' => 'Municipality of San Manuel'],
            ['district_number' => 6, 'name' => 'San Nicolas', 'type' => 'Municipality', 'description' => 'Municipality of San Nicolas'],
            ['district_number' => 6, 'name' => 'San Quintin', 'type' => 'Municipality', 'description' => 'Municipality of San Quintin'],
            ['district_number' => 6, 'name' => 'Sta. Maria', 'type' => 'Municipality', 'description' => 'Municipality of Sta. Maria'],
            ['district_number' => 6, 'name' => 'Tayug', 'type' => 'Municipality', 'description' => 'Municipality of Tayug'],
            ['district_number' => 6, 'name' => 'Umingan', 'type' => 'Municipality', 'description' => 'Municipality of Umingan'],
        ];

        // Pagbukud-bukurin ang lahat nang alpabetikal ayon sa pangalan
        usort($municities, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        $records = [];
        $sortOrder = 1;
        foreach ($municities as $municipality) {
            $districtId = $districtMap[$municipality['district_number']] ?? null;
            if ($districtId === null) {
                continue;
            }

            $records[] = [
                'district_id' => $districtId,
                'name' => $municipality['name'],
                'type' => $municipality['type'],
                'description' => $municipality['description'],
                'sort_order' => $sortOrder++,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => 1,
                'updated_by' => 1,
            ];
        }

        if (empty($records)) {
            echo "  No Pangasinan municipalities or cities inserted." . PHP_EOL;
            return;
        }

        $this->insertOrIgnore('municities', $records);

        echo "  Inserted " . count($records) . " municipalities and cities" . PHP_EOL;
    }
}
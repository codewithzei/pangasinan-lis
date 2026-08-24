<?php

class HospitalSeeder extends Seeder
{
    public function run(): void
    {
        $hospitalNames = [
            'Asingan Community Hospital',
            'Bayambang District Hospital',
            'Bolinao Community Hospital',
            'Dasol Community Hospital',
            'Eastern Pangasinan District Hospital',
            'Lingayen District Hospital',
            'Manaoag Community Hospital',
            'Mangatarem District Hospital',
            'Mapandan Community Hospital',
            'Pangasinan Provincial Hospital',
            'Pozorrubio Community Hospital',
            'Umingan Community Hospital',
            'Urdaneta District Hospital',
            'Western Pangasinan District Hospital',
        ];

        $hospitals = [];
        $sortOrder = 1;

        foreach ($hospitalNames as $name) {
            $hospitals[] = [
                'name' => $name,
                'sort_order' => $sortOrder++,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }

        $this->insertOrIgnore('hospitals', $hospitals);

        echo "  Inserted " . count($hospitals) . " hospitals" . PHP_EOL;
    }
}
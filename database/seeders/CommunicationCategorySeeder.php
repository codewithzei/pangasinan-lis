<?php

class CommunicationCategorySeeder extends Seeder
{
    public function run(): void
    {
        $communicationCategories = [
            [
                'name' => 'MIS',
                'description' => 'Management Information System - handles system and data processing',
                'sort_order' => 1,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Personnel Services',
                'description' => 'Handles personnel and HR-related matters',
                'sort_order' => 2,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Posting',
                'description' => 'Handles document posting and publication',
                'sort_order' => 3,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Records Services',
                'description' => 'Manages records and archives',
                'sort_order' => 4,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];

        $this->insertOrIgnore('communication_categories', $communicationCategories);

        echo "  Inserted " . count($communicationCategories) . " communication categories" . PHP_EOL;
    }
}
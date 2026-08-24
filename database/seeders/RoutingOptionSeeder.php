<?php

class RoutingOptionSeeder extends Seeder
{
    public function run(): void
    {
        $routingOptions = [
            [
                'name' => 'Receiving Staff',
                'description' => 'Receives and logs incoming documents and requests',
                'sort_order' => 1,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Admin',
                'description' => 'Routes documents and reviews them before forwarding to departments',
                'sort_order' => 2,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'SP Secretary',
                'description' => 'Manages Sangguniang Panlalawigan documentation and records',
                'sort_order' => 3,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Plenary',
                'description' => 'Oversees plenary session records and legislative workflow',
                'sort_order' => 4,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Committee',
                'description' => 'Reviews and processes documents at the committee level',
                'sort_order' => 5,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Noted',
                'description' => 'Document is noted and set aside as final stop - no further routing needed',
                'sort_order' => 6,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];

        $this->insertOrIgnore('routing_options', $routingOptions);

        echo "  Inserted " . count($routingOptions) . " routing options" . PHP_EOL;
    }
}
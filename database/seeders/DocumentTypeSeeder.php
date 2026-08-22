<?php

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $document_types = [
            [
                'name' => 'Super Admin',
                'description' => 'Full system access with all permissions',
                'sort_order' => 1,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Receiving Staff',
                'description' => 'Handles incoming documents/requests',
                'sort_order' => 2,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Admin',
                'description' => 'Routes and review documents to appropriate departments',
                'sort_order' => 3,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'SP Secretary',
                'description' => 'Sangguniang Panlalawigan Secretary',
                'sort_order' => 4,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Plenary',
                'description' => 'Handles plenary session records and workflow',
                'sort_order' => 5,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Committee',
                'description' => 'Committee-level access and review',
                'sort_order' => 6,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];

        $this->insertOrIgnore('document_types', $document_types);

        echo "  Inserted " . count($document_types) . " document types" . PHP_EOL;
    }
}

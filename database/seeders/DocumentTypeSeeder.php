<?php

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $document_types = [
            [
                'name' => 'Provincial Tax Ordinance',
                'description' => 'Provincial tax legislation',
                'badge_color' => '#2563EB',
                'sort_order' => 1,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Provincial Resolution',
                'description' => 'Provincial legislative resolution',
                'badge_color' => '#0EA5E9',
                'sort_order' => 2,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Provincial Appropriation Ordinance for Supplemental Budget',
                'description' => 'Provincial supplemental budget appropriation',
                'badge_color' => '#7C3AED',
                'sort_order' => 3,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Provincial Appropriation Ordinance for Annual Budget',
                'description' => 'Provincial annual budget appropriation',
                'badge_color' => '#8B5CF6',
                'sort_order' => 4,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Provincial Ordinance',
                'description' => 'Provincial ordinance',
                'badge_color' => '#14B8A6',
                'sort_order' => 5,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Municipal/City Ordinance',
                'description' => 'Municipal or city ordinance',
                'badge_color' => '#10B981',
                'sort_order' => 6,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Municipal/City Resolution',
                'description' => 'Municipal or city resolution',
                'badge_color' => '#22C55E',
                'sort_order' => 7,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Municipal/City Appropriation Ordinance for Supplemental Budget',
                'description' => 'Municipal/City supplemental budget appropriation',
                'badge_color' => '#F59E0B',
                'sort_order' => 8,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Municipal/City Appropriation Ordinance for Annual Budget',
                'description' => 'Municipal/City annual budget appropriation',
                'badge_color' => '#F97316',
                'sort_order' => 9,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Communication',
                'description' => 'Official correspondence and communications',
                'badge_color' => '#EC4899',
                'sort_order' => 10,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Administrative Cases',
                'description' => 'Administrative case records',
                'badge_color' => '#EF4444',
                'sort_order' => 11,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Complaint',
                'description' => 'Complaint filings',
                'badge_color' => '#DC2626',
                'sort_order' => 12,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Others',
                'description' => 'Other document types not classified',
                'badge_color' => '#64748B',
                'sort_order' => 13,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];

        $this->insertOrIgnore('document_types', $document_types);

        echo "  Inserted " . count($document_types) . " document types" . PHP_EOL;
    }
}
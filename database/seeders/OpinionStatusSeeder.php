<?php

class OpinionStatusSeeder extends Seeder
{
    public function run(): void
    {
        $opinion_statuses = [
            [
                'name' => 'Pending',
                'description' => 'Awaiting action or decision',
                'badge_color' => '#F59E0B', // Amber - waiting/processing
                'sort_order' => 1,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Favorable',
                'description' => 'Approved and finalized',
                'badge_color' => '#22C55E', // Green - positive/approved
                'sort_order' => 2,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Unfavorable',
                'description' => 'Rejected or disapproved',
                'badge_color' => '#EF4444', // Red - negative/rejected
                'sort_order' => 3,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'For Second Endorsement',
                'description' => 'Needs further review or approval',
                'badge_color' => '#3B82F6', // Blue - pending further action
                'sort_order' => 4,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];

        $this->insertOrIgnore('opinion_statuses', $opinion_statuses);

        echo "  Inserted " . count($opinion_statuses) . " opinion statuses" . PHP_EOL;
    }
}
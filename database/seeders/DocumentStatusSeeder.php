<?php

class DocumentStatusSeeder extends Seeder
{
    public function run(): void
    {
        $document_statuses = [
            [
                'name' => 'Pending',
                'description' => 'Awaiting action or decision',
                'badge_color' => '#F59E0B', // Amber
                'sort_order' => 1,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Approved',
                'description' => 'Approved and finalized',
                'badge_color' => '#22C55E', // Green
                'sort_order' => 2,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Withdrawn',
                'description' => 'Withdrawn by the proponent',
                'badge_color' => '#EF4444', // Red
                'sort_order' => 3,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Deferred',
                'description' => 'Postponed for later consideration',
                'badge_color' => '#F97316', // Orange
                'sort_order' => 4,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Noted',
                'description' => 'Acknowledged and recorded',
                'badge_color' => '#8B5CF6', // Violet
                'sort_order' => 5,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Under Processing',
                'description' => 'Currently being processed',
                'badge_color' => '#3B82F6', // Blue
                'sort_order' => 6,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Lay on the Table',
                'description' => 'Set aside temporarily',
                'badge_color' => '#6B7280', // Gray
                'sort_order' => 7,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Referred',
                'description' => 'Referred to committee or office',
                'badge_color' => '#0EA5E9', // Sky Blue
                'sort_order' => 8,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Remanded',
                'description' => 'Sent back for further review',
                'badge_color' => '#DC2626', // Red
                'sort_order' => 9,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Returned to Plenary',
                'description' => 'Returned to the plenary session',
                'badge_color' => '#7C3AED', // Purple
                'sort_order' => 10,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'For Committee Report',
                'description' => 'Awaiting committee report',
                'badge_color' => '#14B8A6', // Teal
                'sort_order' => 11,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'For Opinion',
                'description' => 'Awaiting legal or technical opinion',
                'badge_color' => '#EC4899', // Pink
                'sort_order' => 12,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'For Calendar',
                'description' => 'Scheduled for calendar inclusion',
                'badge_color' => '#10B981', // Emerald
                'sort_order' => 13,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'On Going',
                'description' => 'Currently in progress',
                'badge_color' => '#06B6D4', // Cyan
                'sort_order' => 14,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'For Second Reading',
                'description' => 'Scheduled for second reading',
                'badge_color' => '#A855F7', // Purple
                'sort_order' => 15,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Draft',
                'description' => 'Initial draft version',
                'badge_color' => '#94A3B8', // Slate
                'sort_order' => 16,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Archived',
                'description' => 'Archived for record keeping',
                'badge_color' => '#475569', // Dark Slate
                'sort_order' => 17,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];

        $this->insertOrIgnore('document_statuses', $document_statuses);

        echo "  Inserted " . count($document_statuses) . " document statuses" . PHP_EOL;
    }
}
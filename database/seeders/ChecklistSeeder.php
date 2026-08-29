<?php

class ChecklistSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $checklists = [
            ['name' => 'Agency/Office', 'description' => 'Agency or office designation', 'sort_order' => 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Annual Budget for Calendar Year', 'description' => 'Annual budget for the calendar year', 'sort_order' => 2, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Annual Investment Plan (AIP)', 'description' => 'Annual Investment Plan document', 'sort_order' => 3, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Appropriation Number', 'description' => 'Appropriation number identifier', 'sort_order' => 4, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Appropriation Ordinance', 'description' => 'Appropriation ordinance document', 'sort_order' => 5, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Appropriation Ordinance Number', 'description' => 'Appropriation ordinance number identifier', 'sort_order' => 6, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Attendance Sheet', 'description' => 'Attendance sheet records', 'sort_order' => 7, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Attachments', 'description' => 'Supporting attachments and documents', 'sort_order' => 8, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Budget Message', 'description' => 'Budget message document', 'sort_order' => 9, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Budget Review Matrix', 'description' => 'Budget review matrix analysis', 'sort_order' => 10, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Certificate Posting', 'description' => 'Certificate of posting', 'sort_order' => 11, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Certification from Local Finance Committee (LFC)', 'description' => 'Certification from Local Finance Committee', 'sort_order' => 12, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Certification of Availability of Funds', 'description' => 'Certification of availability of funds', 'sort_order' => 13, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Copy of Old Ordinance', 'description' => 'Copy of old or previous ordinance', 'sort_order' => 14, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Declaration of Real Property', 'description' => 'Declaration of real property', 'sort_order' => 15, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Department of Agriculture (DA) Certification for Land Use', 'description' => 'DA certification for land use', 'sort_order' => 16, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Department of Agrarian Reform (DAR) Certification', 'description' => 'DAR certification', 'sort_order' => 17, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Department of Interior and Local Government (DILG) approved GAD Plan', 'description' => 'DILG approved Gender and Development Plan', 'sort_order' => 18, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Details', 'description' => 'Additional details and information', 'sort_order' => 19, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Draft Memorandum of Agreement (MOA)', 'description' => 'Draft memorandum of agreement', 'sort_order' => 20, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Draft Ordinance', 'description' => 'Draft ordinance document', 'sort_order' => 21, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Endorsement from Department of Human Settlements and Urban Development (DHSUD)', 'description' => 'DHSUD endorsement', 'sort_order' => 22, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Endorsement Letter', 'description' => 'Endorsement letter', 'sort_order' => 23, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Executive Committee Resolution', 'description' => 'Executive committee resolution', 'sort_order' => 24, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Land Bank of the Philippines (LBP) Form/Source of Fund', 'description' => 'LBP form or source of fund document', 'sort_order' => 25, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Legal Opinion', 'description' => 'Legal opinion document', 'sort_order' => 26, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Local Expenditure Program', 'description' => 'Local expenditure program', 'sort_order' => 27, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Memorandum of Agreement (MOA)', 'description' => 'Memorandum of agreement', 'sort_order' => 28, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Minutes of Public Hearing', 'description' => 'Minutes of public hearing', 'sort_order' => 29, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Municipal/City Ordinance', 'description' => 'Municipal or city ordinance', 'sort_order' => 30, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Municipal Development Council (MDC) Resolution', 'description' => 'MDC resolution', 'sort_order' => 31, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Office of City Defense (OCD) Certification', 'description' => 'OCD certification', 'sort_order' => 32, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Opinion', 'description' => 'Opinion document', 'sort_order' => 33, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Peace and Order Plan', 'description' => 'Peace and order plan', 'sort_order' => 34, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Plantilla of Personnel', 'description' => 'Plantilla of personnel', 'sort_order' => 35, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'PPAs/Plans', 'description' => 'Programs, projects, and activities or plans', 'sort_order' => 36, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Publication/Affidavit of Publication', 'description' => 'Publication or affidavit of publication', 'sort_order' => 37, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Resolution', 'description' => 'Resolution document', 'sort_order' => 38, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Resolution Number', 'description' => 'Resolution number identifier', 'sort_order' => 39, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Sangguniang Bayan (SB) Resolution', 'description' => 'Sangguniang Bayan resolution', 'sort_order' => 40, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Subject Matter', 'description' => 'Subject matter details', 'sort_order' => 41, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Supplemental Budget Number', 'description' => 'Supplemental budget number identifier', 'sort_order' => 42, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Supplemental Investment Program (SIP) Form', 'description' => 'Supplemental Investment Program form', 'sort_order' => 43, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Table of Contents', 'description' => 'Table of contents', 'sort_order' => 44, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Transfer Certificate of Title (TCT)', 'description' => 'Transfer certificate of title', 'sort_order' => 45, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Transmittal Letter', 'description' => 'Transmittal letter', 'sort_order' => 46, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ];

        $this->insertOrIgnore('checklists', $checklists);

        echo "  Inserted " . count($checklists) . " checklist items" . PHP_EOL;
    }
}

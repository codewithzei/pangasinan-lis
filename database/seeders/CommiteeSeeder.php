<?php

class CommitteeSeeder extends Seeder
{
    public function run(): void
    {
        $committees = [
            [
                'name' => 'Committee on Agriculture',
                'description' => 'Reviews and handles agricultural policies, programs, and concerns',
                'sort_order' => 1,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Committee on Appropriations',
                'description' => 'Reviews budget proposals and fund allocations',
                'sort_order' => 2,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Committee on Barangay and Rural Development, Public Order and Safety',
                'description' => 'Oversees barangay development, public order, and safety concerns',
                'sort_order' => 3,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Committee on Children, Women, Senior Citizens, Family Affairs and Social Welfare',
                'description' => 'Hands welfare programs for children, women, senior citizens, and families',
                'sort_order' => 4,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Committee on Cooperative, Micro, Small and Medium Business and Entrepreneurship Development',
                'description' => 'Supports cooperatives, MSMEs, and entrepreneurship development initiatives',
                'sort_order' => 5,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Committee on Economic Affairs and Ways and Means',
                'description' => 'Handles economic policies, revenue generation, and financial strategies',
                'sort_order' => 6,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Committee on Education, Arts and Culture',
                'description' => 'Oversees educational programs, cultural preservation, and arts development',
                'sort_order' => 7,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Committee on Environment, Natural Resources and Energy',
                'description' => 'Manages environmental protection, natural resource management, and energy concerns',
                'sort_order' => 8,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Committee on Good Government and Accountability of Public Officers, Justice and Human Rights',
                'description' => 'Ensures good governance, public accountability, justice, and human rights protection',
                'sort_order' => 9,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Committee on Health',
                'description' => 'Oversees health programs, medical services, and public health concerns',
                'sort_order' => 10,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Committee on Housing, Land Utilization and Agrarian Reform',
                'description' => 'Manages housing programs, land use planning, and agrarian reform initiatives',
                'sort_order' => 11,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Committee on Human Resources and Development, Labor and Employment Concerns',
                'description' => 'Handles human resource development, labor policies, and employment programs',
                'sort_order' => 12,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Committee on Information and Communication Technology and Games and Amusement',
                'description' => 'Oversees ICT development, digital transformation, and amusement/gaming regulations',
                'sort_order' => 13,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Committee on Infrastructure, Public Services and Utilities',
                'description' => 'Manages infrastructure projects, public services, and utility concerns',
                'sort_order' => 14,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Committee on Inter-Local Government and People\'s and Non-Governmental Organizations Relations',
                'description' => 'Facilitates inter-LGU cooperation and partnerships with NGOs and people\'s organizations',
                'sort_order' => 15,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Committee on Laws and Ordinances',
                'description' => 'Reviews and deliberates on proposed laws, ordinances, and legislative measures',
                'sort_order' => 16,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Committee on Rules, Privileges and Ethics',
                'description' => 'Enforces legislative rules, privileges, and ethical standards for members',
                'sort_order' => 17,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Committee on Tourism, Foreign Affairs and Migrant Workers Concerns',
                'description' => 'Promotes tourism development, handles foreign relations, and protects migrant workers\' welfare',
                'sort_order' => 18,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Committee on Youth and Sports',
                'description' => 'Develops youth programs, sports development, and recreational activities for the youth',
                'sort_order' => 19,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];

        $this->insertOrIgnore('committees', $committees);

        echo "  Inserted " . count($committees) . " committees" . PHP_EOL;
    }
}
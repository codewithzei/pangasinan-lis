<?php

class SpMemberSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $members = [
            // Vice Governor first
            [
                'first_name'  => 'Mark Ronald',
                'middle_name' => null,
                'last_name'   => 'Lambino',
                'suffix'      => null,
                'position'    => 'Vice Governor',
                'district'    => '4th District',
                'sort_order'  => 1,
            ],
            // Board Members — alphabetical by last name
            [
                'first_name'  => 'Apolonia',
                'middle_name' => null,
                'last_name'   => 'Bacay',
                'suffix'      => null,
                'position'    => 'Board Member',
                'district'    => '1st District',
                'sort_order'  => 2,
            ],
            [
                'first_name'  => 'Sheila Marie',
                'middle_name' => null,
                'last_name'   => 'Baniqued',
                'suffix'      => null,
                'position'    => 'Board Member',
                'district'    => '3rd District',
                'sort_order'  => 3,
            ],
            [
                'first_name'  => 'Noel',
                'middle_name' => null,
                'last_name'   => 'Bince',
                'suffix'      => null,
                'position'    => 'Board Member',
                'district'    => '6th District',
                'sort_order'  => 4,
            ],
            [
                'first_name'  => 'Philip Theodore',
                'middle_name' => null,
                'last_name'   => 'Cruz',
                'suffix'      => null,
                'position'    => 'Board Member',
                'district'    => '2nd District',
                'sort_order'  => 5,
            ],
            [
                'first_name'  => 'Marinor',
                'middle_name' => null,
                'last_name'   => 'De Guzman',
                'suffix'      => null,
                'position'    => 'Board Member',
                'district'    => '4th District',
                'sort_order'  => 6,
            ],
            [
                'first_name'  => 'Jerome Vic',
                'middle_name' => null,
                'last_name'   => 'Espino',
                'suffix'      => null,
                'position'    => 'Board Member',
                'district'    => '1st District',
                'sort_order'  => 7,
            ],
            [
                'first_name'  => 'Napoleon',
                'middle_name' => null,
                'last_name'   => 'Fontelera',
                'suffix'      => 'Jr.',
                'position'    => 'Board Member',
                'district'    => '1st District',
                'sort_order'  => 8,
            ],
            [
                'first_name'  => 'Haidee',
                'middle_name' => null,
                'last_name'   => 'Pacheco',
                'suffix'      => null,
                'position'    => 'Board Member',
                'district'    => '2nd District',
                'sort_order'  => 9,
            ],
            [
                'first_name'  => 'Rosary Gracia',
                'middle_name' => null,
                'last_name'   => 'Perez-Tababa',
                'suffix'      => null,
                'position'    => 'Board Member',
                'district'    => '5th District',
                'sort_order'  => 10,
            ],
            [
                'first_name'  => 'Salvador',
                'middle_name' => null,
                'last_name'   => 'Perez',
                'suffix'      => null,
                'position'    => 'Board Member',
                'district'    => '6th District',
                'sort_order'  => 11,
            ],
            [
                'first_name'  => 'Jerry Agerico',
                'middle_name' => null,
                'last_name'   => 'Rosario',
                'suffix'      => null,
                'position'    => 'Board Member',
                'district'    => '4th District',
                'sort_order'  => 12,
            ],
            [
                'first_name'  => 'Carlyon',
                'middle_name' => null,
                'last_name'   => 'Sison',
                'suffix'      => null,
                'position'    => 'Board Member',
                'district'    => '1st District',
                'sort_order'  => 13,
            ],
            [
                'first_name'  => 'Nichol Jan Louie',
                'middle_name' => null,
                'last_name'   => 'Sison',
                'suffix'      => null,
                'position'    => 'Board Member',
                'district'    => '5th District',
                'sort_order'  => 14,
            ],
            [
                'first_name'  => 'Vici',
                'middle_name' => null,
                'last_name'   => 'Ventanilla',
                'suffix'      => null,
                'position'    => 'Board Member',
                'district'    => '3rd District',
                'sort_order'  => 15,
            ],
            [
                'first_name'  => 'Jeanne Jinky',
                'middle_name' => null,
                'last_name'   => 'Zaplan',
                'suffix'      => null,
                'position'    => 'Board Member',
                'district'    => '1st District',
                'sort_order'  => 16,
            ],
        ];

        $districtStmt = $this->pdo->prepare("SELECT id FROM districts WHERE name = ? LIMIT 1");

        $rows = [];
        foreach ($members as $member) {
            $districtStmt->execute([$member['district']]);
            $district = $districtStmt->fetch();

            if (!$district) {
                echo "   SKIP {$member['first_name']} {$member['last_name']}: District '{$member['district']}' not found." . PHP_EOL;
                continue;
            }

            $rows[] = [
                'first_name'  => $member['first_name'],
                'middle_name' => $member['middle_name'],
                'last_name'   => $member['last_name'],
                'suffix'      => $member['suffix'],
                'photo_path'  => null,
                'position'    => $member['position'],
                'district_id' => $district['id'],
                'sort_order'  => $member['sort_order'],
                'is_active'   => 1,
                'is_deleted'  => 0,
                'deleted_at'  => null,
                'deleted_by'  => null,
                'created_by'  => 1,
                'updated_by'  => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];

            echo "   Queued: {$member['first_name']} {$member['last_name']} ({$member['position']} - {$member['district']})" . PHP_EOL;
        }

        $this->insertOrIgnore('sp_members', $rows);

        echo "  Inserted " . count($rows) . " SP members." . PHP_EOL;
    }
}

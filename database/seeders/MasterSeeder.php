<?php

class MasterSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $users = [
            [
                'role_name' => 'Super Admin',
                'username' => 'masterlegis+',
                'email' => 'superadmin@pangasinan.local',
                'password' => 'legis+123!',
                'first_name' => 'System',
                'middle_name' => null,
                'last_name' => 'Administrator',
                'suffix' => null,
                'contact_number' => '09000000001',
            ],
            [
                'role_name' => 'Receiving Staff',
                'username' => 'reclegis+',
                'email' => 'receiving@pangasinan.local',
                'password' => 'legis+123!',
                'first_name' => 'Receiving',
                'middle_name' => 'Staff',
                'last_name' => 'User',
                'suffix' => null,
                'contact_number' => '09000000002',
            ],
            [
                'role_name' => 'Admin',
                'username' => 'adminlegis+',
                'email' => 'admin@pangasinan.local',
                'password' => 'legis+123!',
                'first_name' => 'Admin',
                'middle_name' => 'Routing',
                'last_name' => 'Officer',
                'suffix' => null,
                'contact_number' => '09000000003',
            ],
            [
                'role_name' => 'SP Secretary',
                'username' => 'splegis+',
                'email' => 'spsec@pangasinan.local',
                'password' => 'legis+123!',
                'first_name' => 'SP',
                'middle_name' => 'Secretary',
                'last_name' => 'User',
                'suffix' => null,
                'contact_number' => '09000000004',
            ],
            [
                'role_name' => 'Plenary',
                'username' => 'plenarylegis+',
                'email' => 'plenary@pangasinan.local',
                'password' => 'legis+123!',
                'first_name' => 'Plenary',
                'middle_name' => 'Session',
                'last_name' => 'Handler',
                'suffix' => null,
                'contact_number' => '09000000005',
            ],
            [
                'role_name' => 'Committee',
                'username' => 'committeelegis+',
                'email' => 'committee@pangasinan.local',
                'password' => 'legis+123!',
                'first_name' => 'Committee',
                'middle_name' => 'Review',
                'last_name' => 'Officer',
                'suffix' => null,
                'contact_number' => '09000000006',
            ],
        ];

        foreach ($users as $user) {
            $roleStmt = $this->pdo->prepare("SELECT id FROM roles WHERE name = ? LIMIT 1");
            $roleStmt->execute([$user['role_name']]);
            $role = $roleStmt->fetch();

            if (!$role) {
                echo "   SKIP {$user['username']}: Role '{$user['role_name']}' not found." . PHP_EOL;
                continue;
            }

            $account = [
                'username' => $user['username'],
                'email' => $user['email'],
                'password_hash' => password_hash($user['password'], PASSWORD_BCRYPT),
                'role_id' => $role['id'],
                'status' => 'active',
                'failed_login_attempts' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $this->insertOrIgnore('user_accounts', [$account]);

            $accountStmt = $this->pdo->prepare("SELECT id FROM user_accounts WHERE username = ? LIMIT 1");
            $accountStmt->execute([$user['username']]);
            $accountRow = $accountStmt->fetch();

            if ($accountRow) {
                $profile = [
                    'user_account_id' => $accountRow['id'],
                    'first_name' => $user['first_name'],
                    'middle_name' => $user['middle_name'],
                    'last_name' => $user['last_name'],
                    'suffix' => $user['suffix'],
                    'contact_number' => $user['contact_number'],
                    'profile_path' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $this->insertOrIgnore('user_info', [$profile]);

                echo "   Seeded: {$user['username']} ({$user['role_name']})" . PHP_EOL;
            }
        }

        echo "   All default accounts seeded." . PHP_EOL;
    }
}
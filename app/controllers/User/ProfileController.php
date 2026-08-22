<?php

require_once __DIR__ . '/../../config/database.php';

class ProfileController
{
    protected PDO $pdo;

    public function __construct()
    {
        $database = new Database();
        $this->pdo = $database->connect();
    }

    public function index(): void
    {
        $userId = auth_id();

        $stmt = $this->pdo->prepare("
            SELECT 
                ua.id, ua.username, ua.email, ua.status, ua.created_at,
                r.name AS role_name,
                ui.first_name, ui.middle_name, ui.last_name, ui.suffix,
                ui.contact_number, ui.profile_path
            FROM user_accounts ua
            INNER JOIN roles r ON r.id = ua.role_id
            LEFT JOIN user_info ui ON ui.user_account_id = ua.id
            WHERE ua.id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();

        if (!$profile) {
            flash_set('error', 'Profile not found.');
            redirect('dashboard');
        }

        $pageTitle = 'My Profile';
        require __DIR__ . '/../../../resources/views/user/profile.php';
    }

    public function update(): void
    {
        $userId = auth_id();
        $user = auth();

        $data = [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'middle_name' => trim($_POST['middle_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'suffix' => trim($_POST['suffix'] ?? ''),
            'contact_number' => trim($_POST['contact_number'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
        ];

        old_set($data);

        $errors = [];
        if ($data['first_name'] === '') $errors[] = 'First name is required.';
        if ($data['last_name'] === '') $errors[] = 'Last name is required.';
        if ($data['email'] === '') $errors[] = 'Email is required.';
        elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format.';

        $emailCheck = $this->pdo->prepare("SELECT COUNT(*) FROM user_accounts WHERE email = ? AND id != ?");
        $emailCheck->execute([$data['email'], $userId]);
        if ($emailCheck->fetchColumn() > 0) {
            $errors[] = 'Email is already in use by another account.';
        }

        if (!empty($errors)) {
            flash_set('errors', $errors);
            redirect('profile');
        }

        try {
            $oldStmt = $this->pdo->prepare("
                SELECT 
                    ua.email,
                    ui.first_name, ui.middle_name, ui.last_name, ui.suffix, ui.contact_number
                FROM user_accounts ua
                LEFT JOIN user_info ui ON ui.user_account_id = ua.id
                WHERE ua.id = ? LIMIT 1
            ");
            $oldStmt->execute([$userId]);
            $oldData = $oldStmt->fetch() ?: [];

            $updateAccount = $this->pdo->prepare("UPDATE user_accounts SET email = ?, updated_at = NOW() WHERE id = ?");
            $updateAccount->execute([$data['email'], $userId]);

            $checkInfo = $this->pdo->prepare("SELECT user_account_id FROM user_info WHERE user_account_id = ? LIMIT 1");
            $checkInfo->execute([$userId]);
            $exists = $checkInfo->fetch();

            if ($exists) {
                $updateInfo = $this->pdo->prepare("
                    UPDATE user_info 
                    SET first_name = ?, middle_name = ?, last_name = ?, suffix = ?, contact_number = ?, updated_at = NOW()
                    WHERE user_account_id = ?
                ");
                $updateInfo->execute([
                    $data['first_name'] ?: null,
                    $data['middle_name'] ?: null,
                    $data['last_name'] ?: null,
                    $data['suffix'] ?: null,
                    $data['contact_number'] ?: null,
                    $userId,
                ]);
            } else {
                $insertInfo = $this->pdo->prepare("
                    INSERT INTO user_info 
                        (user_account_id, first_name, middle_name, last_name, suffix, contact_number, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $insertInfo->execute([
                    $userId,
                    $data['first_name'] ?: null,
                    $data['middle_name'] ?: null,
                    $data['last_name'] ?: null,
                    $data['suffix'] ?: null,
                    $data['contact_number'] ?: null,
                ]);
            }

            $fullName = trim(implode(' ', array_filter([
                $data['first_name'],
                $data['suffix'],
                $data['last_name']
            ])));

            $_SESSION['user']['first_name'] = $data['first_name'];
            $_SESSION['user']['middle_name'] = $data['middle_name'];
            $_SESSION['user']['last_name'] = $data['last_name'];
            $_SESSION['user']['suffix'] = $data['suffix'];
            $_SESSION['user']['contact_number'] = $data['contact_number'];
            $_SESSION['user']['email'] = $data['email'];
            $_SESSION['user']['full_name'] = $fullName !== '' ? $fullName : ($user['username'] ?? '');

            $oldVals = [
                'email' => $oldData['email'] ?? null,
                'first_name' => $oldData['first_name'] ?? null,
                'middle_name' => $oldData['middle_name'] ?? null,
                'last_name' => $oldData['last_name'] ?? null,
                'suffix' => $oldData['suffix'] ?? null,
                'contact_number' => $oldData['contact_number'] ?? null,
            ];
            $newVals = [
                'email' => $data['email'],
                'first_name' => $data['first_name'] ?: null,
                'middle_name' => $data['middle_name'] ?: null,
                'last_name' => $data['last_name'] ?: null,
                'suffix' => $data['suffix'] ?: null,
                'contact_number' => $data['contact_number'] ?: null,
            ];
            $changed = [];
            foreach ($newVals as $k => $v) {
                if (($oldVals[$k] ?? null) !== $v) {
                    $changed[$k] = ['old' => $oldVals[$k] ?? null, 'new' => $v];
                }
            }
            audit_log(
                'UPDATE',
                'User',
                (string)$userId,
                !empty($oldVals) ? $oldVals : null,
                $newVals,
                'Profile updated by owner' . (!empty($changed) ? ' — changed: ' . implode(', ', array_keys($changed)) : '')
            );
            system_log('INFO', "Profile updated: User #{$userId}", ['changes' => $changed, 'fields' => array_keys($changed)]);

            flash_set('success', 'Profile updated successfully.');
            redirect('profile');
        } catch (Throwable $e) {
            system_log('ERROR', 'Profile update failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString(), 'user_id' => $userId]);
            flash_set('error', 'Failed to update profile. Please try again.');
            redirect('profile');
        }
    }
}

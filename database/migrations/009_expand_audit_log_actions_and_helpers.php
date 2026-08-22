<?php

class ExpandAuditLogActionsAndHelpers
{
    public function up($pdo)
    {
        $sql = "ALTER TABLE audit_logs 
                MODIFY COLUMN action ENUM(
                    'CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'VIEW', 
                    'EXPORT', 'IMPORT', 'ARCHIVE', 'RESTORE', 'APPROVE', 
                    'REJECT', 'SUBMIT', 'ROUTE', 'UNLOCK', 'PASSWORD_CHANGE', 
                    'LOCKOUT', 'OTHER'
                ) NOT NULL DEFAULT 'OTHER'";
        $pdo->exec($sql);

        $stmt = $pdo->query("SHOW COLUMNS FROM user_accounts LIKE 'locked_at'");
        $hasLockedAt = $stmt->fetch();
        if (!$hasLockedAt) {
            $pdo->exec("ALTER TABLE user_accounts 
                        ADD COLUMN locked_at TIMESTAMP NULL DEFAULT NULL,
                        ADD COLUMN last_lockout_reason VARCHAR(255) NULL DEFAULT NULL");
        }

        $stmt2 = $pdo->query("SHOW COLUMNS FROM user_accounts LIKE 'last_password_change'");
        $hasLastPwd = $stmt2->fetch();
        if (!$hasLastPwd) {
            $pdo->exec("ALTER TABLE user_accounts 
                        ADD COLUMN last_password_change TIMESTAMP NULL DEFAULT NULL");
        }
    }

    public function down($pdo)
    {
        $sql = "ALTER TABLE audit_logs 
                MODIFY COLUMN action ENUM(
                    'CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'VIEW', 
                    'EXPORT', 'IMPORT', 'ARCHIVE', 'RESTORE', 'OTHER'
                ) NOT NULL DEFAULT 'OTHER'";
        $pdo->exec($sql);

        $pdo->exec("ALTER TABLE user_accounts 
                    DROP COLUMN IF EXISTS locked_at,
                    DROP COLUMN IF EXISTS last_lockout_reason,
                    DROP COLUMN IF EXISTS last_password_change");
    }
}

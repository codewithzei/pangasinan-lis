<?php

class UpdateTermLegislatorsToSpMembers
{
    public function up($pdo)
    {
        // Step 1: Add the new sp_member_id column (nullable initially)
        $pdo->exec("ALTER TABLE term_legislators ADD COLUMN sp_member_id BIGINT UNSIGNED NULL AFTER term_id");
        
        // Step 2: Try to migrate existing data if possible
        // This attempts to find matching sp_members by matching user_info names
        // Only migrate if there's a clear 1-to-1 match to avoid data corruption
        $pdo->exec("
            UPDATE term_legislators tl
            INNER JOIN user_info ui ON ui.id = tl.user_info_id
            INNER JOIN sp_members sm ON 
                sm.first_name = ui.first_name 
                AND sm.last_name = ui.last_name
                AND (sm.middle_name = ui.middle_name OR (sm.middle_name IS NULL AND ui.middle_name IS NULL))
                AND (sm.suffix = ui.suffix OR (sm.suffix IS NULL AND ui.suffix IS NULL))
                AND sm.is_deleted = 0
            SET tl.sp_member_id = sm.sp_member_id
            WHERE tl.sp_member_id IS NULL
        ");
        
        // Step 3: Remove records that couldn't be migrated (optional - comment out if you want to preserve)
        // If you prefer to keep orphaned records for manual review, comment out the next line
        $pdo->exec("DELETE FROM term_legislators WHERE sp_member_id IS NULL");
        
        // Step 4: Make sp_member_id NOT NULL now that migration is complete
        $pdo->exec("ALTER TABLE term_legislators MODIFY sp_member_id BIGINT UNSIGNED NOT NULL");
        
        // Step 5: Drop the old unique constraint that included role
        $pdo->exec("ALTER TABLE term_legislators DROP INDEX uk_tl_term_user_role");
        
        // Step 6: Drop the old foreign key constraint
        $pdo->exec("ALTER TABLE term_legislators DROP FOREIGN KEY fk_tl_user_info");
        
        // Step 7: Drop the old user_info_id column and index
        $pdo->exec("ALTER TABLE term_legislators DROP INDEX idx_tl_user_info_id");
        $pdo->exec("ALTER TABLE term_legislators DROP COLUMN user_info_id");
        
        // Step 8: Drop the role column and index
        $pdo->exec("ALTER TABLE term_legislators DROP INDEX idx_tl_role");
        $pdo->exec("ALTER TABLE term_legislators DROP COLUMN role");
        
        // Step 9: Add new unique constraint (term + sp_member)
        $pdo->exec("ALTER TABLE term_legislators ADD UNIQUE KEY uk_tl_term_sp_member (term_id, sp_member_id)");
        
        // Step 10: Add foreign key to sp_members
        $pdo->exec("ALTER TABLE term_legislators ADD CONSTRAINT fk_tl_sp_member FOREIGN KEY (sp_member_id) REFERENCES sp_members(sp_member_id) ON DELETE CASCADE");
        
        // Step 11: Add index for better query performance
        $pdo->exec("ALTER TABLE term_legislators ADD INDEX idx_tl_sp_member_id (sp_member_id)");
    }

    public function down($pdo)
    {
        // Reverse migration - restore original structure
        
        // Drop new constraints and indexes
        $pdo->exec("ALTER TABLE term_legislators DROP FOREIGN KEY fk_tl_sp_member");
        $pdo->exec("ALTER TABLE term_legislators DROP INDEX uk_tl_term_sp_member");
        $pdo->exec("ALTER TABLE term_legislators DROP INDEX idx_tl_sp_member_id");
        
        // Add back user_info_id column
        $pdo->exec("ALTER TABLE term_legislators ADD COLUMN user_info_id BIGINT NULL AFTER term_id");
        
        // Add back role column
        $pdo->exec("ALTER TABLE term_legislators ADD COLUMN role ENUM('Sponsor', 'Author', 'Co-author', 'Ex-officio', 'Guest') NOT NULL DEFAULT 'Author' AFTER user_info_id");
        
        // Note: Cannot automatically restore data - manual intervention required
        // The sp_member_id column will be dropped, but we cannot reliably reverse-map to user_info_id
        
        // Drop sp_member_id column
        $pdo->exec("ALTER TABLE term_legislators DROP COLUMN sp_member_id");
        
        // Make user_info_id NOT NULL (will fail if there's no data)
        $pdo->exec("ALTER TABLE term_legislators MODIFY user_info_id BIGINT NOT NULL");
        
        // Restore original constraints
        $pdo->exec("ALTER TABLE term_legislators ADD INDEX idx_tl_user_info_id (user_info_id)");
        $pdo->exec("ALTER TABLE term_legislators ADD INDEX idx_tl_role (role)");
        $pdo->exec("ALTER TABLE term_legislators ADD UNIQUE KEY uk_tl_term_user_role (term_id, user_info_id, role)");
        $pdo->exec("ALTER TABLE term_legislators ADD CONSTRAINT fk_tl_user_info FOREIGN KEY (user_info_id) REFERENCES user_info(id) ON DELETE CASCADE");
    }
}

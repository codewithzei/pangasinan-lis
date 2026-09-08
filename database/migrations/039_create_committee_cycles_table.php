<?php

class CreateCommitteeCyclesTable
{
    public function up($pdo)
    {
        $sql = "CREATE TABLE IF NOT EXISTS committee_cycles (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            document_id BIGINT UNSIGNED NOT NULL,
            cycle_number INT NOT NULL,
            referred_by BIGINT NULL,
            accepted_by BIGINT NULL,
            accepted_at TIMESTAMP NULL,
            decision ENUM('PENDING', 'ACCEPTED', 'DENIED') NOT NULL DEFAULT 'PENDING',
            remarks TEXT NULL,
            started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            
            -- Unique Constraint
            UNIQUE KEY unique_document_cycle (document_id, cycle_number),
            
            -- Indexes
            INDEX idx_committee_cycles_document (document_id),
            INDEX idx_committee_cycles_cycle_number (cycle_number),
            INDEX idx_committee_cycles_decision (decision),
            INDEX idx_committee_cycles_referred_by (referred_by),
            INDEX idx_committee_cycles_accepted_by (accepted_by),
            INDEX idx_committee_cycles_started_at (started_at),
            INDEX idx_committee_cycles_completed_at (completed_at),
            
            -- Foreign Keys
            CONSTRAINT fk_committee_cycles_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE RESTRICT,
            CONSTRAINT fk_committee_cycles_referred_by FOREIGN KEY (referred_by) REFERENCES user_accounts(id) ON DELETE SET NULL,
            CONSTRAINT fk_committee_cycles_accepted_by FOREIGN KEY (accepted_by) REFERENCES user_accounts(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql);

        // Create committee_cycle_committees junction table
        $sql2 = "CREATE TABLE IF NOT EXISTS committee_cycle_committees (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cycle_id BIGINT UNSIGNED NOT NULL,
            committee_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Unique Constraint
            UNIQUE KEY unique_cycle_committee (cycle_id, committee_id),
            
            -- Indexes
            INDEX idx_committee_cycle_committees_cycle (cycle_id),
            INDEX idx_committee_cycle_committees_committee (committee_id),
            
            -- Foreign Keys
            CONSTRAINT fk_committee_cycle_committees_cycle FOREIGN KEY (cycle_id) REFERENCES committee_cycles(id) ON DELETE CASCADE,
            CONSTRAINT fk_committee_cycle_committees_committee FOREIGN KEY (committee_id) REFERENCES committees(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql2);
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS committee_cycle_committees;");
        $pdo->exec("DROP TABLE IF EXISTS committee_cycles;");
    }
}

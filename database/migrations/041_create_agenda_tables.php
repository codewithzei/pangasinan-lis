<?php

class CreateAgendaTables
{
    public function up($pdo)
    {
        // Main agendas table
        $sql = "CREATE TABLE IF NOT EXISTS agendas (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            document_id BIGINT UNSIGNED NOT NULL,
            cycle_id BIGINT UNSIGNED NOT NULL,
            agenda_number VARCHAR(50) NULL,
            agenda_type VARCHAR(100) NULL,
            agenda_date DATE NOT NULL,
            agenda_time TIME NOT NULL,
            venue VARCHAR(255) NULL,
            remarks TEXT NULL,
            created_by BIGINT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            -- Indexes
            INDEX idx_agendas_document (document_id),
            INDEX idx_agendas_cycle (cycle_id),
            INDEX idx_agendas_agenda_date (agenda_date),
            INDEX idx_agendas_created_by (created_by),
            
            -- Foreign Keys
            CONSTRAINT fk_agendas_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE RESTRICT,
            CONSTRAINT fk_agendas_cycle FOREIGN KEY (cycle_id) REFERENCES committee_cycles(id) ON DELETE RESTRICT,
            CONSTRAINT fk_agendas_created_by FOREIGN KEY (created_by) REFERENCES user_accounts(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql);

        // Agenda committees junction table
        $sql2 = "CREATE TABLE IF NOT EXISTS agenda_committees (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            agenda_id BIGINT UNSIGNED NOT NULL,
            committee_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Unique Constraint
            UNIQUE KEY unique_agenda_committee (agenda_id, committee_id),
            
            -- Indexes
            INDEX idx_agenda_committees_agenda (agenda_id),
            INDEX idx_agenda_committees_committee (committee_id),
            
            -- Foreign Keys
            CONSTRAINT fk_agenda_committees_agenda FOREIGN KEY (agenda_id) REFERENCES agendas(id) ON DELETE CASCADE,
            CONSTRAINT fk_agenda_committees_committee FOREIGN KEY (committee_id) REFERENCES committees(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql2);

        // Agenda chairpersons junction table
        $sql3 = "CREATE TABLE IF NOT EXISTS agenda_chairpersons (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            agenda_id BIGINT UNSIGNED NOT NULL,
            sp_member_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Unique Constraint
            UNIQUE KEY unique_agenda_chairperson (agenda_id, sp_member_id),
            
            -- Indexes
            INDEX idx_agenda_chairpersons_agenda (agenda_id),
            INDEX idx_agenda_chairpersons_sp_member (sp_member_id),
            
            -- Foreign Keys
            CONSTRAINT fk_agenda_chairpersons_agenda FOREIGN KEY (agenda_id) REFERENCES agendas(id) ON DELETE CASCADE,
            CONSTRAINT fk_agenda_chairpersons_sp_member FOREIGN KEY (sp_member_id) REFERENCES sp_members(sp_member_id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $pdo->exec($sql3);
    }

    public function down($pdo)
    {
        $pdo->exec("DROP TABLE IF EXISTS agenda_chairpersons;");
        $pdo->exec("DROP TABLE IF EXISTS agenda_committees;");
        $pdo->exec("DROP TABLE IF EXISTS agendas;");
    }
}

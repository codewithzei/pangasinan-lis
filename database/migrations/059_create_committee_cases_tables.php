<?php

/**
 * Migration 059 — Committee Cases tables
 *
 * Creates four tables that support the Committee case-docketing workflow
 * for documents with document types "Administrative Cases" and "Complaint".
 *
 * Tables created
 * ──────────────────────────────────────────────────────────────────────────
 * 1. committee_case_docket_sequences
 *    Tracks the last-used docket sequence per calendar year, enabling
 *    gap-free, race-free DKT-YYYY-NNNN generation via SELECT … FOR UPDATE.
 *
 * 2. committee_cases
 *    One row per docketed case, linked 1-to-1 with a documents row.
 *    Carries the generated docket_number (DKT-YYYY-0001), date_assigned,
 *    nature_of_case, complainant_details, complainant_municipality,
 *    respondents, and the user who created the case record.
 *
 * 3. committee_case_actions
 *    Timeline entries for a case (e.g. Subpoena Issued, Hearing Conducted).
 *    Ordered by action_date ASC, created_at ASC in every query.
 *
 * 4. committee_case_action_attachments
 *    File attachments associated with individual timeline actions.
 *    Stored using the same uploads/documents/<id>/ directory convention
 *    already used by document_attachments.
 *
 * Docket-number generation contract
 * ──────────────────────────────────────────────────────────────────────────
 * The controller MUST:
 *   1. Begin a transaction.
 *   2. INSERT … ON DUPLICATE KEY UPDATE last_sequence = last_sequence
 *      (ensures the year row exists without clobbering the sequence).
 *   3. SELECT last_sequence FROM committee_case_docket_sequences
 *      WHERE docket_year = ? FOR UPDATE   ← row-level lock
 *   4. Increment and UPDATE last_sequence.
 *   5. Build docket_number = sprintf('DKT-%d-%04d', year, next).
 *   6. INSERT into committee_cases.
 *   7. COMMIT.
 *   Using FOR UPDATE prevents two concurrent requests from reading the same
 *   last_sequence value and generating duplicate docket numbers.
 *   The UNIQUE constraints on docket_number and (docket_year, docket_sequence)
 *   provide a final database-level safety net.
 *
 * Also expands document_events.event_type ENUM to include
 * 'COMMITTEE_CASE_CREATED' so the workflow event can be recorded.
 *
 * Idempotent: each CREATE TABLE uses IF NOT EXISTS and every INSERT uses
 * ON DUPLICATE KEY UPDATE so the migration is safe to re-run.
 */
class CreateCommitteeCasesTables
{
    public function up(PDO $pdo): void
    {
        // ── 1. committee_case_docket_sequences ────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS committee_case_docket_sequences (
                id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                docket_year   SMALLINT        NOT NULL,
                last_sequence INT             NOT NULL DEFAULT 0,
                created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                              ON UPDATE CURRENT_TIMESTAMP,

                PRIMARY KEY (id),
                UNIQUE KEY uq_docket_year (docket_year)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── 2. committee_cases ────────────────────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS committee_cases (
                id                       BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
                document_id              BIGINT UNSIGNED  NOT NULL,
                docket_year              SMALLINT         NOT NULL,
                docket_sequence          INT              NOT NULL,
                docket_number            VARCHAR(30)      NOT NULL,
                date_assigned            DATE             NOT NULL,
                nature_of_case           TEXT             NOT NULL,
                complainant_details      TEXT             NOT NULL,
                complainant_municipality VARCHAR(255)     NULL     DEFAULT NULL,
                respondents              TEXT             NOT NULL,
                assigned_by              BIGINT           NULL     DEFAULT NULL,
                created_at               TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at               TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                          ON UPDATE CURRENT_TIMESTAMP,

                PRIMARY KEY (id),

                -- A document may only have one case record.
                UNIQUE KEY uq_document_id     (document_id),

                -- No two cases in the same year may share a sequence number.
                UNIQUE KEY uq_year_sequence   (docket_year, docket_sequence),

                -- Docket number string must be globally unique.
                UNIQUE KEY uq_docket_number   (docket_number),

                -- Speed up lookups by document, year, and date.
                INDEX idx_document_id         (document_id),
                INDEX idx_docket_year         (docket_year),
                INDEX idx_date_assigned       (date_assigned),

                CONSTRAINT fk_cc_document
                    FOREIGN KEY (document_id) REFERENCES documents (id)
                    ON DELETE RESTRICT,

                CONSTRAINT fk_cc_assigned_by
                    FOREIGN KEY (assigned_by) REFERENCES user_accounts (id)
                    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── 3. committee_case_actions ─────────────────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS committee_case_actions (
                id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                case_id     BIGINT UNSIGNED NOT NULL,
                action_type VARCHAR(150)    NOT NULL,
                action_date DATE            NOT NULL,
                description TEXT            NOT NULL,
                notes       TEXT            NULL DEFAULT NULL,
                created_by  BIGINT          NULL DEFAULT NULL,
                created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                            ON UPDATE CURRENT_TIMESTAMP,

                PRIMARY KEY (id),

                INDEX idx_case_id    (case_id),
                INDEX idx_action_date (action_date),
                INDEX idx_created_at (created_at),

                CONSTRAINT fk_cca_case
                    FOREIGN KEY (case_id) REFERENCES committee_cases (id)
                    ON DELETE CASCADE,

                CONSTRAINT fk_cca_created_by
                    FOREIGN KEY (created_by) REFERENCES user_accounts (id)
                    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── 4. committee_case_action_attachments ──────────────────────────────
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS committee_case_action_attachments (
                id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                action_id   BIGINT UNSIGNED NOT NULL,
                uploaded_by BIGINT          NULL DEFAULT NULL,
                file_name   VARCHAR(255)    NOT NULL,
                stored_path VARCHAR(500)    NOT NULL,
                mime_type   VARCHAR(100)    NOT NULL,
                file_size   BIGINT UNSIGNED NOT NULL,
                created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

                PRIMARY KEY (id),

                INDEX idx_action_id  (action_id),
                INDEX idx_created_at (created_at),

                CONSTRAINT fk_ccaa_action
                    FOREIGN KEY (action_id) REFERENCES committee_case_actions (id)
                    ON DELETE CASCADE,

                CONSTRAINT fk_ccaa_uploaded_by
                    FOREIGN KEY (uploaded_by) REFERENCES user_accounts (id)
                    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── 5. Expand document_events.event_type ENUM ─────────────────────────
        //    Read the current COLUMN_TYPE, append 'COMMITTEE_CASE_CREATED' if
        //    it is not already present, and issue a single ALTER TABLE.
        $colRow = $pdo->query("
            SELECT COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'document_events'
              AND COLUMN_NAME  = 'event_type'
            LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);

        $existingValues = [];
        if ($colRow && preg_match("/^enum\((.+)\)$/i", $colRow['COLUMN_TYPE'], $m)) {
            preg_match_all("/'([^']+)'/", $m[1], $matches);
            $existingValues = $matches[1];
        }

        if (!in_array('COMMITTEE_CASE_CREATED', $existingValues, true)) {
            $existingValues[] = 'COMMITTEE_CASE_CREATED';
            $enumList = implode(',', array_map(fn($v) => "'{$v}'", $existingValues));
            $pdo->exec("ALTER TABLE document_events MODIFY COLUMN event_type ENUM({$enumList}) NOT NULL");
        }
    }

    public function down(PDO $pdo): void
    {
        // Drop in reverse dependency order.
        $pdo->exec("DROP TABLE IF EXISTS committee_case_action_attachments");
        $pdo->exec("DROP TABLE IF EXISTS committee_case_actions");
        $pdo->exec("DROP TABLE IF EXISTS committee_cases");
        $pdo->exec("DROP TABLE IF EXISTS committee_case_docket_sequences");

        // Revert ENUM: remove COMMITTEE_CASE_CREATED if present.
        $colRow = $pdo->query("
            SELECT COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'document_events'
              AND COLUMN_NAME  = 'event_type'
            LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);

        $existingValues = [];
        if ($colRow && preg_match("/^enum\((.+)\)$/i", $colRow['COLUMN_TYPE'], $m)) {
            preg_match_all("/'([^']+)'/", $m[1], $matches);
            $existingValues = $matches[1];
        }

        $reverted = array_values(array_filter(
            $existingValues,
            fn($v) => $v !== 'COMMITTEE_CASE_CREATED'
        ));

        if (!empty($reverted)) {
            $enumList = implode(',', array_map(fn($v) => "'{$v}'", $reverted));
            $pdo->exec("ALTER TABLE document_events MODIFY COLUMN event_type ENUM({$enumList}) NOT NULL");
        }
    }
}

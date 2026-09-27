<?php
/**
 * PaginationTestCleanup
 *
 * Removes ONLY the records created by PaginationTestSeeder.
 *
 * Identification strategy
 * -----------------------
 *  - documents:                   remarks LIKE '%TEST_PAGINATION%'
 *                                 OR tracking_year = 9999
 *  - All child rows are removed first (FK order), then the documents themselves.
 *  - document_tracking_sequences: row for tracking_year = 9999 is also removed.
 *  - committee_case_docket_sequences: row for docket_year = 9999 is removed.
 *  - committee_reports identified by report_number LIKE 'TEST-RPT-9999-%'.
 *
 * SAFE: Only rows created by the test seeder are touched.
 *       Existing production data is unaffected.
 */
class PaginationTestCleanup extends Seeder
{
    private const MARKER      = 'TEST_PAGINATION';
    private const TEST_YEAR   = 9999;
    private const RPT_PREFIX  = 'RPT-9999-%';
    private const DKT_PREFIX  = 'DKT-9999-%';

    public function run(): void
    {
        echo "  [PaginationTestCleanup] Collecting test document IDs…" . PHP_EOL;

        // Collect all test document IDs (tracking_year=9999 OR remarks marker)
        $docIdRows = $this->pdo->query("
            SELECT id FROM documents
            WHERE tracking_year = " . self::TEST_YEAR . "
               OR remarks LIKE '%" . self::MARKER . "%'
        ")->fetchAll(\PDO::FETCH_COLUMN);

        $docIds = array_map('intval', $docIdRows);
        $count  = count($docIds);
        echo "  [PaginationTestCleanup] Found {$count} test document(s). Removing children…" . PHP_EOL;

        if (empty($docIds)) {
            echo "  [PaginationTestCleanup] Nothing to clean up." . PHP_EOL;
            $this->cleanOrphanReports();
            $this->cleanSequences();
            return;
        }

        $in = implode(',', $docIds);

        // 1. committee_case_action_attachments → via committee_case_actions → via committee_cases
        $caseIds = $this->pdo->query("SELECT id FROM committee_cases WHERE document_id IN ({$in})")
                             ->fetchAll(\PDO::FETCH_COLUMN);
        if (!empty($caseIds)) {
            $caseIn = implode(',', array_map('intval', $caseIds));
            $actionIds = $this->pdo->query("SELECT id FROM committee_case_actions WHERE case_id IN ({$caseIn})")
                                   ->fetchAll(\PDO::FETCH_COLUMN);
            if (!empty($actionIds)) {
                $actionIn = implode(',', array_map('intval', $actionIds));
                $this->pdo->exec("DELETE FROM committee_case_action_attachments WHERE action_id IN ({$actionIn})");
            }
            $this->pdo->exec("DELETE FROM committee_case_actions WHERE case_id IN ({$caseIn})");
        }
        $this->pdo->exec("DELETE FROM committee_cases WHERE document_id IN ({$in})");

        // 2. committee_communications (unique per document)
        $this->pdo->exec("DELETE FROM committee_communications WHERE document_id IN ({$in})");

        // 3. committee_reports linked to test documents
        //    (also catches any orphaned test reports by number prefix)
        $reportIds = $this->pdo->query("
            SELECT DISTINCT crd.committee_report_id
            FROM committee_report_documents crd
            WHERE crd.document_id IN ({$in})
            UNION
            SELECT id FROM committee_reports WHERE report_number LIKE '" . self::RPT_PREFIX . "'
        ")->fetchAll(\PDO::FETCH_COLUMN);

        if (!empty($reportIds)) {
            $rptIn = implode(',', array_map('intval', $reportIds));
            $this->pdo->exec("DELETE FROM committee_report_committees  WHERE committee_report_id IN ({$rptIn})");
            $this->pdo->exec("DELETE FROM committee_report_documents   WHERE committee_report_id IN ({$rptIn})");
            $this->pdo->exec("DELETE FROM committee_reports            WHERE id IN ({$rptIn})");
        }

        // 4. committee_hearings (FK → agendas, document)
        $hearingIds = $this->pdo->query("SELECT id FROM committee_hearings WHERE document_id IN ({$in})")
                                ->fetchAll(\PDO::FETCH_COLUMN);
        if (!empty($hearingIds)) {
            $hIn = implode(',', array_map('intval', $hearingIds));
            $this->pdo->exec("DELETE FROM committee_hearings WHERE id IN ({$hIn})");
        }

        // 5. agendas (children: agenda_committees, agenda_chairpersons)
        $agendaIds = $this->pdo->query("SELECT id FROM agendas WHERE document_id IN ({$in})")
                               ->fetchAll(\PDO::FETCH_COLUMN);
        if (!empty($agendaIds)) {
            $agIn = implode(',', array_map('intval', $agendaIds));
            $this->pdo->exec("DELETE FROM agenda_chairpersons WHERE agenda_id IN ({$agIn})");
            $this->pdo->exec("DELETE FROM agenda_committees   WHERE agenda_id IN ({$agIn})");
            $this->pdo->exec("DELETE FROM agendas             WHERE id IN ({$agIn})");
        }

        // 6. committee_cycles and their children
        $cycleIds = $this->pdo->query("SELECT id FROM committee_cycles WHERE document_id IN ({$in})")
                              ->fetchAll(\PDO::FETCH_COLUMN);
        if (!empty($cycleIds)) {
            $cyIn = implode(',', array_map('intval', $cycleIds));
            $this->pdo->exec("DELETE FROM committee_opinion_resolutions  WHERE cycle_id IN ({$cyIn})");
            $this->pdo->exec("DELETE FROM committee_cycle_endorsements   WHERE cycle_id IN ({$cyIn})");
            $this->pdo->exec("DELETE FROM committee_cycle_committees     WHERE cycle_id IN ({$cyIn})");
            $this->pdo->exec("DELETE FROM committee_cycles               WHERE id IN ({$cyIn})");
        }

        // 7. document_events
        $this->pdo->exec("DELETE FROM document_events WHERE document_id IN ({$in})");

        // 8. document_committees
        $this->pdo->exec("DELETE FROM document_committees WHERE document_id IN ({$in})");

        // 9. document_routes
        $this->pdo->exec("DELETE FROM document_routes WHERE document_id IN ({$in})");

        // 10. document_assignments
        $this->pdo->exec("DELETE FROM document_assignments WHERE document_id IN ({$in})");

        // 11. document_checklist_items
        $this->pdo->exec("DELETE FROM document_checklist_items WHERE document_id IN ({$in})");

        // 12. document_attachments
        $this->pdo->exec("DELETE FROM document_attachments WHERE document_id IN ({$in})");

        // 13. document_revisions
        $this->pdo->exec("DELETE FROM document_revisions WHERE document_id IN ({$in})");

        // 14. document_approvals (if exists)
        $this->execIfTableExists("DELETE FROM document_approvals WHERE document_id IN ({$in})");

        // 15. document_filings (if exists)
        $this->execIfTableExists("DELETE FROM document_filings WHERE document_id IN ({$in})");

        // 16. document_authors (if exists)
        $this->execIfTableExists("DELETE FROM document_authors WHERE document_id IN ({$in})");

        // 17. notifications (if exists)
        $this->execIfTableExists("DELETE FROM notifications WHERE document_id IN ({$in})");

        // 18. plenary_document_details (if exists)
        $this->execIfTableExists("DELETE FROM plenary_document_details WHERE document_id IN ({$in})");

        // 19. Finally, the documents themselves
        $this->pdo->exec("DELETE FROM documents WHERE id IN ({$in})");

        echo "  [PaginationTestCleanup] Removed {$count} test document(s) and all child rows." . PHP_EOL;

        $this->cleanOrphanReports();
        $this->cleanSequences();
    }

    // -------------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------------

    private function cleanOrphanReports(): void
    {
        // Clean any orphaned committee_cases by docket prefix (year=9999)
        $this->pdo->exec("DELETE FROM committee_case_docket_sequences WHERE docket_year = 9999");

        // Clean any orphaned committee_reports by report_number prefix
        $orphanRptIds = $this->pdo->query("
            SELECT id FROM committee_reports WHERE report_number LIKE '" . self::RPT_PREFIX . "'
        ")->fetchAll(\PDO::FETCH_COLUMN);

        if (!empty($orphanRptIds)) {
            $rIn = implode(',', array_map('intval', $orphanRptIds));
            $this->pdo->exec("DELETE FROM committee_report_committees WHERE committee_report_id IN ({$rIn})");
            $this->pdo->exec("DELETE FROM committee_report_documents  WHERE committee_report_id IN ({$rIn})");
            $this->pdo->exec("DELETE FROM committee_reports           WHERE id IN ({$rIn})");
        }
    }

    private function cleanSequences(): void
    {
        $this->pdo->exec("DELETE FROM document_tracking_sequences WHERE tracking_year = " . self::TEST_YEAR);
        echo "  [PaginationTestCleanup] Removed test tracking/docket sequence rows." . PHP_EOL;
    }

    /**
     * Run a DELETE statement only if the table referenced in it exists.
     * Prevents crashes on projects that haven't run every migration.
     */
    private function execIfTableExists(string $sql): void
    {
        // Extract table name from the SQL (assumes standard DELETE FROM <table>)
        if (preg_match('/FROM\s+(\w+)/i', $sql, $m)) {
            $table = $m[1];
            $exists = $this->pdo->query("
                SELECT COUNT(*) FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}'
            ")->fetchColumn();
            if (!$exists) {
                return;
            }
        }
        $this->pdo->exec($sql);
    }
}

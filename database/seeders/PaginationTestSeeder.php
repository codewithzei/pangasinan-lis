<?php
/**
 * PaginationTestSeeder
 *
 * Creates enough realistic test records to exceed 2 full pages (page-size = 20)
 * on every paginated table/tab outside the Master folder.
 *
 * SAFE DESIGN
 * -----------
 *  - tracking_year = 9999 (reserved test year; never used in production).
 *  - tracking_number format: 9999-NNNNN  (fits VARCHAR(20), globally unique per
 *    year because a single incrementing counter is shared across all contexts).
 *  - documents.remarks = 'TEST_PAGINATION | <CONTEXT_PREFIX>' for identification.
 *  - Before inserting, the seeder checks by tracking_number and skips if present
 *    (INSERT IGNORE), making reruns fully idempotent.
 *  - No existing user data, documents, routes, or assignments are modified.
 *
 * COVERED PAGES / CONTEXTS  (41 docs each → 3 pages @ perPage=20)
 * ----------------------------------------------------------------
 *  1.  Receiving › Routed Documents        (all documents)
 *  2.  Receiving › Inbox – Returned        (RECEIVING PENDING + ADMIN DECLINED)
 *  3.  Receiving › Inbox – Accepted        (RECEIVING ACCEPTED/COMPLETED + ADMIN DECLINED)
 *  4.  Admin › Inbox – Pending             (ADMIN PENDING, unclaimed)
 *  5.  Admin › Inbox – Accepted            (ADMIN ACCEPTED by admin user)
 *  6.  Admin › Routed Documents            (document_routes FROM ADMIN)
 *  7.  SP Secretary › Inbox – Pending      (SP_SECRETARY PENDING)
 *  8.  SP Secretary › Inbox – Accepted     (SP_SECRETARY ACCEPTED)
 *  9.  SP Secretary › Routed Documents     (document_routes FROM SP_SECRETARY)
 * 10.  SP Secretary › Communications       (Noted route + doc type=Communication)
 * 11.  Committee › Inbox – Pending         (COMMITTEE PENDING)
 * 12.  Committee › Inbox – Accepted        (COMMITTEE ACCEPTED, non-excluded type)
 * 13.  Committee › Cases                   (committee_cases assigned_by = committee user)
 * 14.  Committee › Communications          (committee_communications assigned_by = committee user)
 * 15.  Committee › Referred – referred tab (completed cycle, no endorsement/resolution)
 * 16+17. Committee › Hearings – all + scheduled (On Going + agenda, no hearing outcome)
 * 18.  Committee › Reports                 (committee_reports rows)
 *
 * CLEANUP
 * -------
 *  Run:  php database/run_pagination_test.php cleanup
 */
class PaginationTestSeeder extends Seeder
{
    /** Records per context — 41 → 3 pages at perPage=20 */
    private const COUNT = 41;

    /** Marker stored in remarks to identify test rows */
    private const MARKER = 'TEST_PAGINATION';

    /** Reserved tracking year — never used in production */
    private const TEST_YEAR = 9999;

    // ── Looked-up FK IDs (populated by lookupIds()) ──────────────────────────
    private int $adminUserId;
    private int $spsecUserId;
    private int $committeeUserId;
    private int $receivingUserId;

    private int $adminRoleId;
    private int $spsecRoleId;
    private int $committeeRoleId;
    private int $receivingRoleId;

    private int $sourceTypeId;
    private int $committeeId;

    private int $pendingStatusId;
    private int $onGoingStatusId;
    private int $referredStatusId;

    private int $routingOptionAdminId;
    private int $routingOptionCommitteeId;
    private int $routingOptionNotedId;

    private int $docTypeResolutionId;
    private int $docTypeCommunicationId;
    private int $docTypeComplaintId;

    /** Global sequence counter shared across all document batches */
    private int $globalSeq = 0;

    // =========================================================================
    public function run(): void
    {
        echo "  [PaginationTestSeeder] Looking up prerequisite IDs…" . PHP_EOL;
        $this->lookupIds();

        echo "  [PaginationTestSeeder] Ensuring test tracking sequence row…" . PHP_EOL;
        $this->ensureTrackingSequence();

        // ── 1: Receiving › Routed Documents ──────────────────────────────────
        echo "  [1] Receiving › Routed Documents…" . PHP_EOL;
        $this->createDocuments(self::COUNT, 'REC-ROUTED', $this->pendingStatusId, 'RECEIVING', $this->docTypeResolutionId);

        // ── 2: Receiving › Inbox – Returned ──────────────────────────────────
        // RECEIVING PENDING assignment + ADMIN DECLINED assignment
        echo "  [2] Receiving › Inbox – Returned…" . PHP_EOL;
        $docIds2 = $this->createDocuments(self::COUNT, 'REC-INBOX-RET', $this->pendingStatusId, 'RECEIVING', $this->docTypeResolutionId);
        foreach ($docIds2 as $docId) {
            $this->insertAssignment($docId, 'ADMIN',     $this->adminRoleId,     $this->adminUserId, 'DECLINED', [
                'declined_at'    => date('Y-m-d H:i:s'),
                'decline_reason' => self::MARKER . ' returned for correction',
            ]);
            $this->insertAssignment($docId, 'RECEIVING', $this->receivingRoleId, null, 'PENDING', []);
        }

        // ── 3: Receiving › Inbox – Accepted ──────────────────────────────────
        echo "  [3] Receiving › Inbox – Accepted…" . PHP_EOL;
        $docIds3 = $this->createDocuments(self::COUNT, 'REC-INBOX-ACC', $this->pendingStatusId, 'RECEIVING', $this->docTypeResolutionId);
        foreach ($docIds3 as $docId) {
            $this->insertAssignment($docId, 'ADMIN', $this->adminRoleId, $this->adminUserId, 'DECLINED', [
                'declined_at'    => date('Y-m-d H:i:s'),
                'decline_reason' => self::MARKER . ' returned',
            ]);
            $this->insertAssignment($docId, 'RECEIVING', $this->receivingRoleId, $this->receivingUserId, 'ACCEPTED', [
                'accepted_by'  => $this->receivingUserId,
                'accepted_at'  => date('Y-m-d H:i:s'),
                'completed_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // ── 4: Admin › Inbox – Pending ────────────────────────────────────────
        echo "  [4] Admin › Inbox – Pending…" . PHP_EOL;
        $docIds4 = $this->createDocuments(self::COUNT, 'ADM-INBOX-PND', $this->pendingStatusId, 'ADMIN', $this->docTypeResolutionId);
        foreach ($docIds4 as $docId) {
            $this->insertAssignment($docId, 'ADMIN', $this->adminRoleId, null, 'PENDING', []);
        }

        // ── 5: Admin › Inbox – Accepted ───────────────────────────────────────
        echo "  [5] Admin › Inbox – Accepted…" . PHP_EOL;
        $docIds5 = $this->createDocuments(self::COUNT, 'ADM-INBOX-ACC', $this->pendingStatusId, 'ADMIN', $this->docTypeResolutionId);
        foreach ($docIds5 as $docId) {
            $this->insertAssignment($docId, 'ADMIN', $this->adminRoleId, $this->adminUserId, 'ACCEPTED', [
                'accepted_by' => $this->adminUserId,
                'accepted_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // ── 6: Admin › Routed Documents ───────────────────────────────────────
        echo "  [6] Admin › Routed Documents…" . PHP_EOL;
        $docIds6 = $this->createDocuments(self::COUNT, 'ADM-ROUTED', $this->pendingStatusId, 'SP_SECRETARY', $this->docTypeResolutionId);
        foreach ($docIds6 as $docId) {
            $this->insertRoute($docId, 'ADMIN', 'SP_SECRETARY', $this->adminUserId, $this->spsecRoleId, $this->routingOptionAdminId);
        }

        // ── 7: SP Secretary › Inbox – Pending ────────────────────────────────
        echo "  [7] SP Secretary › Inbox – Pending…" . PHP_EOL;
        $docIds7 = $this->createDocuments(self::COUNT, 'SPS-INBOX-PND', $this->pendingStatusId, 'SP_SECRETARY', $this->docTypeResolutionId);
        foreach ($docIds7 as $docId) {
            $this->insertAssignment($docId, 'SP_SECRETARY', $this->spsecRoleId, null, 'PENDING', []);
        }

        // ── 8: SP Secretary › Inbox – Accepted ───────────────────────────────
        echo "  [8] SP Secretary › Inbox – Accepted…" . PHP_EOL;
        $docIds8 = $this->createDocuments(self::COUNT, 'SPS-INBOX-ACC', $this->pendingStatusId, 'SP_SECRETARY', $this->docTypeResolutionId);
        foreach ($docIds8 as $docId) {
            $this->insertAssignment($docId, 'SP_SECRETARY', $this->spsecRoleId, $this->spsecUserId, 'ACCEPTED', [
                'accepted_by' => $this->spsecUserId,
                'accepted_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // ── 9: SP Secretary › Routed Documents ───────────────────────────────
        echo "  [9] SP Secretary › Routed Documents…" . PHP_EOL;
        $docIds9 = $this->createDocuments(self::COUNT, 'SPS-ROUTED', $this->pendingStatusId, 'COMMITTEE', $this->docTypeResolutionId);
        foreach ($docIds9 as $docId) {
            $this->insertRoute($docId, 'SP_SECRETARY', 'COMMITTEE', $this->spsecUserId, $this->committeeRoleId, $this->routingOptionCommitteeId);
        }

        // ── 10: SP Secretary › Communications ────────────────────────────────
        // doc type = Communication, route from SP_SECRETARY to SP_SECRETARY via Noted option
        echo "  [10] SP Secretary › Communications…" . PHP_EOL;
        $docIds10 = $this->createDocuments(self::COUNT, 'SPS-COMM', $this->pendingStatusId, 'SP_SECRETARY', $this->docTypeCommunicationId);
        foreach ($docIds10 as $docId) {
            $this->insertRoute($docId, 'SP_SECRETARY', 'SP_SECRETARY', $this->spsecUserId, null, $this->routingOptionNotedId);
        }

        // ── 11: Committee › Inbox – Pending ──────────────────────────────────
        echo "  [11] Committee › Inbox – Pending…" . PHP_EOL;
        $docIds11 = $this->createDocuments(self::COUNT, 'COM-INBOX-PND', $this->pendingStatusId, 'COMMITTEE', $this->docTypeResolutionId);
        foreach ($docIds11 as $docId) {
            $this->insertAssignment($docId, 'COMMITTEE', $this->committeeRoleId, null, 'PENDING', []);
        }

        // ── 12: Committee › Inbox – Accepted ─────────────────────────────────
        // Use Provincial Resolution type (not Complaint/Communication) so exclusion logic doesn't hide rows
        echo "  [12] Committee › Inbox – Accepted…" . PHP_EOL;
        $docIds12 = $this->createDocuments(self::COUNT, 'COM-INBOX-ACC', $this->pendingStatusId, 'COMMITTEE', $this->docTypeResolutionId);
        foreach ($docIds12 as $docId) {
            $this->insertAssignment($docId, 'COMMITTEE', $this->committeeRoleId, $this->committeeUserId, 'ACCEPTED', [
                'accepted_by' => $this->committeeUserId,
                'accepted_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // ── 13: Committee › Cases ─────────────────────────────────────────────
        // Complaint doc type; needs committee_cases + docket sequence for year 9999
        echo "  [13] Committee › Cases…" . PHP_EOL;
        $docIds13 = $this->createDocuments(self::COUNT, 'COM-CASES', $this->pendingStatusId, 'COMMITTEE', $this->docTypeComplaintId);
        $this->ensureDocketSequenceRow();
        foreach ($docIds13 as $docId) {
            $seq          = $this->nextDocketSequence();
            $docketNumber = 'DKT-9999-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
            $exists = (int) $this->pdo->prepare("SELECT COUNT(*) FROM committee_cases WHERE document_id=?")->execute([$docId]) ? 0 : 0;
            $chk = $this->pdo->prepare("SELECT COUNT(*) FROM committee_cases WHERE document_id=?");
            $chk->execute([$docId]);
            if ((int) $chk->fetchColumn() === 0) {
                $this->pdo->prepare("
                    INSERT INTO committee_cases
                        (document_id, docket_year, docket_sequence, docket_number,
                         date_assigned, nature_of_case, complainant_details, respondents, assigned_by)
                    VALUES (?, 9999, ?, ?, CURDATE(), ?, ?, ?, ?)
                ")->execute([
                    $docId, $seq, $docketNumber,
                    self::MARKER . ' nature of case ' . $seq,
                    'Complainant ' . $seq . '; ' . self::MARKER,
                    'Respondent '  . $seq . '; ' . self::MARKER,
                    $this->committeeUserId,
                ]);
            }
        }

        // ── 14: Committee › Communications ───────────────────────────────────
        echo "  [14] Committee › Communications…" . PHP_EOL;
        $docIds14 = $this->createDocuments(self::COUNT, 'COM-COMMS', $this->pendingStatusId, 'COMMITTEE', $this->docTypeCommunicationId);
        foreach ($docIds14 as $docId) {
            $chk = $this->pdo->prepare("SELECT COUNT(*) FROM committee_communications WHERE document_id=?");
            $chk->execute([$docId]);
            if ((int) $chk->fetchColumn() === 0) {
                $this->pdo->prepare("
                    INSERT INTO committee_communications
                        (document_id, date_logged, subject, sender_details, notes, assigned_by)
                    VALUES (?, CURDATE(), ?, ?, ?, ?)
                ")->execute([
                    $docId,
                    self::MARKER . ' subject for doc ' . $docId,
                    self::MARKER . ' sender details',
                    self::MARKER . ' notes',
                    $this->committeeUserId,
                ]);
            }
        }

        // ── 15: Committee › Referred – referred tab ───────────────────────────
        // completed cycle + COMMITTEE_ENDORSED_REFERRED event, no endorsements/resolutions
        echo "  [15] Committee › Referred…" . PHP_EOL;
        $docIds15 = $this->createDocuments(self::COUNT, 'COM-REFERRED', $this->referredStatusId, 'COMMITTEE', $this->docTypeResolutionId);
        foreach ($docIds15 as $docId) {
            $cycleId = $this->insertCycle($docId);
            if ($cycleId > 0) {
                $this->pdo->prepare("INSERT IGNORE INTO committee_cycle_committees (cycle_id, committee_id) VALUES (?,?)")
                          ->execute([$cycleId, $this->committeeId]);
            }
            $this->pdo->prepare("INSERT IGNORE INTO document_committees (document_id, committee_id, assigned_by) VALUES (?,?,?)")
                      ->execute([$docId, $this->committeeId, $this->committeeUserId]);
            // Required by the referred-tab query
            $this->pdo->prepare("
                INSERT INTO document_events
                    (document_id, event_type, phase, performed_by, remarks)
                VALUES (?, 'COMMITTEE_ENDORSED_REFERRED', 'COMMITTEE', ?, ?)
            ")->execute([$docId, $this->committeeUserId, self::MARKER]);
        }

        // ── 16+17: Committee › Hearings – all + scheduled ────────────────────
        // On Going status + agenda row, NO committee_hearings row
        echo "  [16/17] Committee › Hearings – all + scheduled…" . PHP_EOL;
        $docIds16 = $this->createDocuments(self::COUNT, 'COM-HEARING', $this->onGoingStatusId, 'COMMITTEE', $this->docTypeResolutionId);
        foreach ($docIds16 as $docId) {
            $cycleId = $this->insertCycle($docId);
            if ($cycleId > 0) {
                // Agenda (required by both tabs)
                $chkAg = $this->pdo->prepare("SELECT COUNT(*) FROM agendas WHERE document_id=?");
                $chkAg->execute([$docId]);
                if ((int) $chkAg->fetchColumn() === 0) {
                    $this->pdo->prepare("
                        INSERT INTO agendas
                            (document_id, cycle_id, agenda_number, agenda_type,
                             agenda_date, agenda_time, venue, remarks, created_by)
                        VALUES (?, ?, ?, 'TEST', CURDATE(), '09:00:00', 'Test Venue', ?, ?)
                    ")->execute([
                        $docId, $cycleId,
                        'AG-9999-' . str_pad($docId, 5, '0', STR_PAD_LEFT),
                        self::MARKER,
                        $this->committeeUserId,
                    ]);
                    $agendaId = (int) $this->pdo->lastInsertId();
                    $this->pdo->prepare("INSERT IGNORE INTO agenda_committees (agenda_id, committee_id) VALUES (?,?)")
                              ->execute([$agendaId, $this->committeeId]);
                }
            }
            $this->pdo->prepare("INSERT IGNORE INTO document_committees (document_id, committee_id, assigned_by) VALUES (?,?,?)")
                      ->execute([$docId, $this->committeeId, $this->committeeUserId]);
        }
        // Intentionally no committee_hearings rows → docs appear in both "all" and "scheduled"

        // ── 18: Committee › Reports ───────────────────────────────────────────
        echo "  [18] Committee › Reports…" . PHP_EOL;
        $docIds18 = $this->createDocuments(self::COUNT, 'COM-REPORTS', $this->pendingStatusId, 'COMMITTEE', $this->docTypeResolutionId);
        foreach ($docIds18 as $i => $docId) {
            $rptNumber = 'RPT-9999-' . str_pad(($i + 1), 4, '0', STR_PAD_LEFT);

            // Skip if report for this doc already exists
            $chk = $this->pdo->prepare("SELECT COUNT(*) FROM committee_report_documents WHERE document_id=?");
            $chk->execute([$docId]);
            if ((int) $chk->fetchColumn() > 0) {
                continue;
            }

            $this->pdo->prepare("
                INSERT IGNORE INTO committee_reports
                    (report_type, report_number, summary_of_findings, created_by)
                VALUES ('COMMITTEE_REPORT', ?, ?, ?)
            ")->execute([$rptNumber, self::MARKER . ' summary', $this->committeeUserId]);

            $reportId = (int) $this->pdo->lastInsertId();
            if ($reportId === 0) {
                // Already existed — look up
                $look = $this->pdo->prepare("SELECT id FROM committee_reports WHERE report_number=? LIMIT 1");
                $look->execute([$rptNumber]);
                $reportId = (int) $look->fetchColumn();
            }

            if ($reportId > 0) {
                $this->pdo->prepare("INSERT IGNORE INTO committee_report_documents (committee_report_id, document_id) VALUES (?,?)")
                          ->execute([$reportId, $docId]);
                $this->pdo->prepare("INSERT IGNORE INTO committee_report_committees (committee_report_id, committee_id) VALUES (?,?)")
                          ->execute([$reportId, $this->committeeId]);
            }
        }

        echo "  [PaginationTestSeeder] Done. All 18 contexts seeded." . PHP_EOL;
    }

    // =========================================================================
    // DOCUMENT FACTORY
    // =========================================================================

    /**
     * Create $count documents for the given context.
     * All share tracking_year=9999. Uses a single global counter so
     * (tracking_year, tracking_sequence) is unique across all contexts.
     *
     * @return int[]  IDs of newly-inserted or already-existing docs.
     */
    private function createDocuments(int $count, string $ctxLabel, int $statusId, string $phase, int $docTypeId): array
    {
        $ids = [];
        $year = self::TEST_YEAR;
        $marker = self::MARKER . ' | ' . $ctxLabel;

        $insertStmt = $this->pdo->prepare("
            INSERT IGNORE INTO documents
                (tracking_year, tracking_sequence, tracking_number,
                 date_received, time_received,
                 subject_matter, document_type_id,
                 source_type_id, source_name,
                 current_status_id, current_phase,
                 remarks, is_public, created_by)
            VALUES
                (?, ?, ?, CURDATE(), '09:00:00',
                 ?, ?,
                 ?, ?,
                 ?, ?,
                 ?, 0, ?)
        ");

        $selectStmt = $this->pdo->prepare("SELECT id FROM documents WHERE tracking_number = ? LIMIT 1");

        for ($i = 1; $i <= $count; $i++) {
            $this->globalSeq++;
            $seq            = $this->globalSeq;
            // Format: 9999-NNNNN  (10 chars max — well within VARCHAR(20))
            $trackingNumber = $year . '-' . str_pad($seq, 5, '0', STR_PAD_LEFT);
            $subject        = $marker . ' #' . $seq;

            $insertStmt->execute([
                $year,
                $seq,
                $trackingNumber,
                $subject,
                $docTypeId,
                $this->sourceTypeId,
                self::MARKER . ' Source',
                $statusId,
                $phase,
                $marker,
                $this->adminUserId,
            ]);

            $selectStmt->execute([$trackingNumber]);
            $id = (int) $selectStmt->fetchColumn();
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    // =========================================================================
    // CHILD-TABLE HELPERS
    // =========================================================================

    /**
     * Insert a document_assignments row; skips if a matching row already exists.
     */
    private function insertAssignment(int $docId, string $phase, int $roleId, ?int $userId, string $decision, array $extra): void
    {
        $chk = $this->pdo->prepare("
            SELECT COUNT(*) FROM document_assignments
            WHERE document_id=? AND phase=? AND decision=? AND assigned_to_role_id=?
        ");
        $chk->execute([$docId, $phase, $decision, $roleId]);
        if ((int) $chk->fetchColumn() > 0) {
            return;
        }

        $this->pdo->prepare("
            INSERT INTO document_assignments
                (document_id, assigned_to_user_id, assigned_to_role_id,
                 phase, assigned_by, received_at,
                 accepted_at, accepted_by,
                 declined_at, completed_at,
                 decision, decline_reason, remarks)
            VALUES (?,?,?, ?,?,NOW(), ?,?, ?,?, ?,?,?)
        ")->execute([
            $docId,
            $userId,
            $roleId,
            $phase,
            $this->adminUserId,
            $extra['accepted_at']   ?? null,
            $extra['accepted_by']   ?? null,
            $extra['declined_at']   ?? null,
            $extra['completed_at']  ?? null,
            $decision,
            $extra['decline_reason'] ?? null,
            self::MARKER,
        ]);
    }

    /**
     * Insert a document_routes row; skips if identical route already exists.
     */
    private function insertRoute(int $docId, string $fromPhase, string $toPhase, int $routedBy, ?int $toRoleId, ?int $routingOptionId): void
    {
        $chk = $this->pdo->prepare("
            SELECT COUNT(*) FROM document_routes
            WHERE document_id=? AND from_phase=? AND to_phase=? AND routed_by=?
        ");
        $chk->execute([$docId, $fromPhase, $toPhase, $routedBy]);
        if ((int) $chk->fetchColumn() > 0) {
            return;
        }

        $this->pdo->prepare("
            INSERT INTO document_routes
                (document_id, from_phase, to_phase,
                 routing_option_id, routed_by, routed_to_role_id, remarks)
            VALUES (?,?,?, ?,?,?,?)
        ")->execute([
            $docId, $fromPhase, $toPhase,
            $routingOptionId, $routedBy, $toRoleId,
            self::MARKER,
        ]);
    }

    /**
     * Insert a completed committee_cycle for the document; returns cycleId.
     * Skips if a cycle with cycle_number=1 already exists for this document.
     */
    private function insertCycle(int $docId): int
    {
        $chk = $this->pdo->prepare("SELECT id FROM committee_cycles WHERE document_id=? AND cycle_number=1 LIMIT 1");
        $chk->execute([$docId]);
        $existing = $chk->fetchColumn();
        if ($existing !== false) {
            return (int) $existing;
        }

        $this->pdo->prepare("
            INSERT INTO committee_cycles
                (document_id, cycle_number, referred_by, accepted_by, accepted_at,
                 decision, started_at, completed_at)
            VALUES (?, 1, ?, ?, NOW(), 'ACCEPTED', NOW(), NOW())
        ")->execute([$docId, $this->committeeUserId, $this->committeeUserId]);

        return (int) $this->pdo->lastInsertId();
    }

    // =========================================================================
    // SEQUENCE MANAGEMENT
    // =========================================================================

    private function ensureTrackingSequence(): void
    {
        // Seed globalSeq from the highest existing test sequence so reruns continue
        $max = (int) $this->pdo->query("
            SELECT COALESCE(MAX(tracking_sequence), 0)
            FROM documents WHERE tracking_year = " . self::TEST_YEAR
        )->fetchColumn();
        $this->globalSeq = $max;

        $this->pdo->prepare("
            INSERT IGNORE INTO document_tracking_sequences (tracking_year, last_sequence)
            VALUES (?, ?)
        ")->execute([self::TEST_YEAR, $max]);
    }

    private function ensureDocketSequenceRow(): void
    {
        $this->pdo->prepare("
            INSERT IGNORE INTO committee_case_docket_sequences (docket_year, last_sequence)
            VALUES (9999, 0)
        ")->execute([]);
    }

    private function nextDocketSequence(): int
    {
        $this->pdo->exec("
            UPDATE committee_case_docket_sequences
               SET last_sequence = last_sequence + 1
             WHERE docket_year = 9999
        ");
        return (int) $this->pdo->query("
            SELECT last_sequence FROM committee_case_docket_sequences WHERE docket_year = 9999
        ")->fetchColumn();
    }

    // =========================================================================
    // ID LOOKUP HELPERS
    // =========================================================================

    private function lookupIds(): void
    {
        $this->adminRoleId      = $this->requireId('roles', 'name', 'Admin');
        $this->spsecRoleId      = $this->requireId('roles', 'name', 'SP Secretary');
        $this->committeeRoleId  = $this->requireId('roles', 'name', 'Committee');
        $this->receivingRoleId  = $this->requireId('roles', 'name', 'Receiving Staff');

        $this->adminUserId      = $this->requireId('user_accounts', 'username', 'adminlegis+');
        $this->spsecUserId      = $this->requireId('user_accounts', 'username', 'splegis+');
        $this->committeeUserId  = $this->requireId('user_accounts', 'username', 'committeelegis+');
        $this->receivingUserId  = $this->requireId('user_accounts', 'username', 'reclegis+');

        $this->sourceTypeId     = $this->requireFirstId('source_types',  'is_active = 1');
        $this->committeeId      = $this->requireFirstId('committees',     'is_active = 1 AND is_deleted = 0');

        $this->pendingStatusId  = $this->requireId('document_statuses', 'name', 'Pending');
        $this->onGoingStatusId  = $this->requireId('document_statuses', 'name', 'On Going');
        $this->referredStatusId = $this->requireIdOrFallback('document_statuses', 'name', 'Referred to Committee', $this->pendingStatusId);

        $fallbackDocType = $this->requireFirstId('document_types', 'is_active = 1 AND is_deleted = 0');
        $this->docTypeResolutionId    = $this->requireIdOrFallback('document_types', 'name', 'Provincial Resolution', $fallbackDocType);
        $this->docTypeCommunicationId = $this->requireId('document_types', 'name', 'Communication');
        $this->docTypeComplaintId     = $this->requireIdOrFallback('document_types', 'name', 'Complaint', $fallbackDocType);

        $fallbackRoute = $this->requireFirstId('routing_options', 'is_active = 1 AND is_deleted = 0');
        $this->routingOptionAdminId     = $this->requireIdOrFallback('routing_options', 'name', 'SP Secretary', $fallbackRoute);
        $this->routingOptionCommitteeId = $this->requireIdOrFallback('routing_options', 'name', 'Committee',    $fallbackRoute);
        $this->routingOptionNotedId     = $this->requireId('routing_options', 'name', 'Noted');
    }

    private function requireId(string $table, string $column, string $value): int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM `{$table}` WHERE `{$column}` = ? LIMIT 1");
        $stmt->execute([$value]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new \RuntimeException("[PaginationTestSeeder] Required row missing: {$table}.{$column}='{$value}'");
        }
        return (int) $id;
    }

    private function requireFirstId(string $table, string $where): int
    {
        $id = $this->pdo->query("SELECT id FROM `{$table}` WHERE {$where} ORDER BY id ASC LIMIT 1")->fetchColumn();
        if ($id === false) {
            throw new \RuntimeException("[PaginationTestSeeder] No rows in {$table} WHERE {$where}");
        }
        return (int) $id;
    }

    private function requireIdOrFallback(string $table, string $column, string $value, int $fallback): int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM `{$table}` WHERE `{$column}` = ? LIMIT 1");
        $stmt->execute([$value]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : $fallback;
    }
}

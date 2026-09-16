<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/DocumentService.php';

/**
 * CommitteeReferredController
 *
 * Handles the Committee Referred Documents page — documents that have been
 * endorsed as REFERRED from the Committee inbox.
 *
 * A document is considered referred when:
 *   - A COMMITTEE_ENDORSED_REFERRED event exists in document_events.
 *   - The corresponding committee_cycles row has completed_at IS NOT NULL.
 *
 * This controller implements Phase 1 of the endorsement workflow.
 * Future phases (For Opinion, Ready for Agenda, Withdrawn) will be added
 * to this controller once those workflow stages are implemented.
 *
 * AUTHORIZATION:
 *   - RoleMiddleware restricts all committee/* routes to ['Super Admin', 'Committee'].
 *   - All Committee users can see referred documents (role-level visibility),
 *     since a referred document is no longer owned by an individual user.
 */
class CommitteeReferredController
{
    protected PDO             $pdo;
    protected DocumentService $docService;

    public function __construct()
    {
        $database         = new Database();
        $this->pdo        = $database->connect();
        $this->docService = new DocumentService();
    }

    // =========================================================================
    // Referred Documents list
    // =========================================================================

    public function index(): void
    {
        $userId = auth_id();
        if ($userId === null) {
            flash_set('error', 'You must be logged in.');
            redirect('login');
        }

        $committeeRoleId = $this->requireCommitteeRoleId();

        // ── Tab / sub-view ────────────────────────────────────────────────────
        // Phase 1: only 'referred' is functional.
        // 'for_opinion', 'ready_for_agenda', 'withdrawn' are placeholders.
        $tab = trim($_GET['tab'] ?? 'referred');
        if (!in_array($tab, ['referred', 'for_opinion', 'ready_for_agenda', 'withdrawn'], true)) {
            $tab = 'referred';
        }

        // ── Filters ───────────────────────────────────────────────────────────
        $search = trim($_GET['search'] ?? '');

        // ── Pagination ────────────────────────────────────────────────────────
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        // ── Build list and count (referred tab only) ──────────────────────────
        //
        // The source of truth for a referred document is the
        // COMMITTEE_ENDORSED_REFERRED event.  We join to committee_cycles via
        // document_id (matching completed rows) to get endorsement metadata.
        // DISTINCT on document_id prevents duplicate rows for documents that
        // might have multiple historical events or assignments.
        //
        // For the placeholder tabs we return empty results so no misleading
        // data is shown.
        // ─────────────────────────────────────────────────────────────────────

        $total      = 0;
        $totalPages = 1;
        $documents  = [];

        if ($tab === 'referred') {
            // ── WHERE for search ──────────────────────────────────────────────
            $searchWhere  = '';
            $searchParams = [];
            if ($search !== '') {
                $searchWhere  = ' AND (d.tracking_number LIKE ? OR d.subject_matter LIKE ?)';
                $searchParams = ["%{$search}%", "%{$search}%"];
            }

            // ── Count query ───────────────────────────────────────────────────
            // Count distinct documents that have a COMMITTEE_ENDORSED_REFERRED event.
            $countStmt = $this->pdo->prepare("
                SELECT COUNT(DISTINCT d.id) AS total
                FROM documents d
                INNER JOIN document_events de
                    ON de.document_id = d.id
                   AND de.event_type  = 'COMMITTEE_ENDORSED_REFERRED'
                INNER JOIN committee_cycles cc
                    ON cc.document_id  = d.id
                   AND cc.completed_at IS NOT NULL
                WHERE 1=1
                {$searchWhere}
            ");
            $countStmt->execute($searchParams);
            $total      = (int) $countStmt->fetchColumn();
            $totalPages = max(1, (int) ceil($total / $perPage));

            // ── List query ────────────────────────────────────────────────────
            // One row per document.  Use the most-recent endorsement event so
            // that if a document is ever re-endorsed the latest row is shown.
            //
            // "Referred By" = the user who routed the document INTO the
            // Committee phase.  That is the document_routes row whose
            // from_phase = 'ADMIN' AND to_phase = 'COMMITTEE'.  We pick the
            // latest such route (ORDER BY created_at DESC, id DESC) so that if
            // the document is re-routed back to Committee the most-recent
            // sender is shown.  sender_remarks is read from the same route row.
            $listStmt = $this->pdo->prepare("
                SELECT
                    d.id                          AS document_id,
                    d.tracking_number,
                    d.subject_matter,
                    d.current_phase,
                    dt.name                       AS document_type_name,
                    dt.badge_color                AS document_type_badge_color,
                    ds.name                       AS status,
                    ds.badge_color                AS status_badge_color,
                    de.created_at                 AS endorsed_at,
                    de.remarks                    AS endorsement_remarks,
                    de.performed_by               AS endorsed_by_id,
                    cc.cycle_number,
                    cc.completed_at               AS cycle_completed_at,
                    -- Sender's remarks: taken from the latest document_routes row where
                    -- from_phase = 'ADMIN' AND to_phase = 'COMMITTEE'.  For rows created
                    -- before the fix (where route remarks were saved as NULL/empty), we
                    -- fall back to documents.remarks (the original Receiving remark).
                    -- This is the only value displayed in the Referred table's Remarks column.
                    -- No fallback to endorsement_remarks or any other source.
                    COALESCE(
                        NULLIF(TRIM(dr.remarks), ''),
                        NULLIF(TRIM(d.remarks),  '')
                    )                             AS sender_remarks,
                    -- Referred By: the user who routed the document to COMMITTEE
                    COALESCE(
                        NULLIF(TRIM(CONCAT(
                            COALESCE(rbi.first_name, ''),
                            ' ',
                            COALESCE(rbi.last_name, '')
                        )), ''),
                        rb.username,
                        '—'
                    )                             AS referred_by_name,
                    rb.username                   AS referred_by_username
                FROM documents d
                INNER JOIN (
                    -- Most-recent endorsement event per document
                    SELECT document_id, MAX(id) AS max_event_id
                    FROM document_events
                    WHERE event_type = 'COMMITTEE_ENDORSED_REFERRED'
                    GROUP BY document_id
                ) latest_de ON latest_de.document_id = d.id
                INNER JOIN document_events de
                    ON de.id = latest_de.max_event_id
                INNER JOIN (
                    -- Most-recent completed cycle per document
                    SELECT document_id, MAX(id) AS max_cycle_id
                    FROM committee_cycles
                    WHERE completed_at IS NOT NULL
                    GROUP BY document_id
                ) latest_cc ON latest_cc.document_id = d.id
                INNER JOIN committee_cycles cc
                    ON cc.id = latest_cc.max_cycle_id
                LEFT JOIN document_types     dt  ON dt.id  = d.document_type_id
                LEFT JOIN document_statuses  ds  ON ds.id  = d.current_status_id
                -- Inbound route: the latest route where Admin sent the document to COMMITTEE.
                -- Uses a correlated subquery so that if the same document is routed more
                -- than once we always display the most-recent sender's remarks.
                LEFT JOIN document_routes dr
                    ON dr.id = (
                        SELECT dr2.id
                        FROM document_routes dr2
                        WHERE dr2.document_id = d.id
                          AND dr2.from_phase  = 'ADMIN'
                          AND dr2.to_phase    = 'COMMITTEE'
                        ORDER BY dr2.created_at DESC, dr2.id DESC
                        LIMIT 1
                    )
                LEFT JOIN user_accounts      rb  ON rb.id  = dr.routed_by
                LEFT JOIN user_info          rbi ON rbi.user_account_id = rb.id
                WHERE 1=1
                {$searchWhere}
                ORDER BY de.created_at DESC, d.id DESC
                LIMIT ? OFFSET ?
            ");
            $listStmt->execute([...$searchParams, $perPage, $offset]);
            $documents = $listStmt->fetchAll();
        }

        // ── Referred count for badge ──────────────────────────────────────────
        $referredCountStmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT d.id) AS cnt
            FROM documents d
            INNER JOIN document_events de
                ON de.document_id = d.id
               AND de.event_type  = 'COMMITTEE_ENDORSED_REFERRED'
            INNER JOIN committee_cycles cc
                ON cc.document_id  = d.id
               AND cc.completed_at IS NOT NULL
        ");
        $referredCountStmt->execute();
        $referredCount = (int) $referredCountStmt->fetchColumn();

        $success = flash_get('success');
        $error   = flash_get('error');

        $pageTitle = 'Referred Documents';
        require __DIR__ . '/../../../resources/views/committee/referred/index.php';
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** Require and return the Committee role ID; redirect if not found. */
    private function requireCommitteeRoleId(): int
    {
        $stmt = $this->pdo->query(
            "SELECT id FROM roles WHERE name = 'Committee' AND is_active = 1 AND is_deleted = 0 LIMIT 1"
        );
        $row = $stmt->fetch();
        if (!$row) {
            flash_set('error', 'Committee role not found. Please contact the system administrator.');
            redirect('dashboard');
        }
        return (int) $row['id'];
    }
}

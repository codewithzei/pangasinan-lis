<?php

/**
 * Migration 057 — Fix documents with agendas stuck at the wrong status
 *
 * Background
 * ──────────
 * Migration 055 seeds the "On Going" document status with id=14 and badge
 * color #D97706.  In environments where the status table already contained a
 * row with name="On Going" at a *different* id (e.g., id=20 which is now
 * "Ready for Agenda"), the agendaStore() call in CommitteeReferredController
 * picked up that wrong id and updated documents.current_status_id to the
 * "Ready for Agenda" row instead of the real "On Going" row.  Those documents
 * therefore never appeared on the Committee Hearings page.
 *
 * This migration:
 *   1. Resolves the canonical "On Going" status id (the one seeded by 055 with
 *      badge_color='#D97706').
 *   2. Finds every document that:
 *        - Has at least one agenda record in the agendas table, AND
 *        - Does NOT yet have a committee_hearings outcome recorded, AND
 *        - Is NOT currently at one of the post-hearing statuses, AND
 *        - Is currently at any status other than the correct "On Going" id.
 *   3. Updates those documents' current_status_id to the canonical "On Going" id.
 *   4. Inserts a corrective document_events row so the audit trail is complete.
 */
class FixOnGoingStatusForAgendaDocuments
{
    public function up(PDO $pdo): void
    {
        // ── 1. Resolve the correct "On Going" status ──────────────────────────
        $onGoingRow = $pdo->prepare(
            "SELECT id FROM document_statuses
              WHERE name = 'On Going' AND is_active = 1 AND is_deleted = 0
              LIMIT 1"
        );
        $onGoingRow->execute();
        $onGoingId = (int) ($onGoingRow->fetchColumn() ?: 0);

        if ($onGoingId === 0) {
            // Migration 055 hasn't run yet — nothing to fix
            return;
        }

        // ── 2. Post-hearing status IDs (we must not touch these) ──────────────
        $postHearingNames  = ['Hearing Completed', 'Committee Report Created', 'Deferred', 'Remanded', 'Withdrawn'];
        $phPlaceholders    = implode(',', array_fill(0, count($postHearingNames), '?'));
        $phStmt = $pdo->prepare(
            "SELECT id FROM document_statuses WHERE name IN ({$phPlaceholders})"
        );
        $phStmt->execute($postHearingNames);
        $postHearingIds = array_map('intval', $phStmt->fetchAll(PDO::FETCH_COLUMN));

        // Build the NOT IN clause (always exclude "On Going" itself too)
        $excludeIds  = array_unique(array_merge([$onGoingId], $postHearingIds));
        $exPlaceholds = implode(',', array_fill(0, count($excludeIds), '?'));

        // ── 3. Find documents to fix ──────────────────────────────────────────
        // Criteria:
        //   - at least one row in agendas for this document
        //   - no row in committee_hearings for this document
        //   - current status NOT already in the "correct" or "past-hearing" set
        $findStmt = $pdo->prepare("
            SELECT d.id AS document_id, d.current_status_id
            FROM documents d
            INNER JOIN agendas ag ON ag.document_id = d.id
            LEFT  JOIN committee_hearings ch ON ch.document_id = d.id
            WHERE ch.id IS NULL
              AND d.current_status_id NOT IN ({$exPlaceholds})
            GROUP BY d.id, d.current_status_id
        ");
        $findStmt->execute($excludeIds);
        $stuckDocuments = $findStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($stuckDocuments)) {
            return; // Nothing to fix
        }

        // ── 4. Fix each stuck document ────────────────────────────────────────
        $updateStmt = $pdo->prepare(
            "UPDATE documents SET current_status_id = ? WHERE id = ?"
        );

        $eventStmt = $pdo->prepare("
            INSERT INTO document_events
                (document_id, event_type, phase, performed_by,
                 from_status_id, to_status_id, remarks, metadata)
            VALUES (?, 'AGENDA_SCHEDULED', 'COMMITTEE', NULL, ?, ?, ?, ?)
        ");

        foreach ($stuckDocuments as $row) {
            $docId      = (int) $row['document_id'];
            $fromStatus = (int) $row['current_status_id'];

            $pdo->beginTransaction();
            try {
                $updateStmt->execute([$onGoingId, $docId]);
                $eventStmt->execute([
                    $docId,
                    $fromStatus,
                    $onGoingId,
                    'Status corrected to On Going by migration 057 (agenda exists, hearing not yet recorded).',
                    json_encode([
                        'migration'    => '057_fix_on_going_status_for_agenda_documents',
                        'from_status'  => $fromStatus,
                        'to_status'    => $onGoingId,
                        'fixed_at'     => date('Y-m-d H:i:s'),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
        }
    }

    public function down(PDO $pdo): void
    {
        // Revert is not safe (we don't know the original wrong status per doc).
        // Delete only the corrective events added by this migration.
        $pdo->exec("
            DELETE FROM document_events
            WHERE event_type = 'AGENDA_SCHEDULED'
              AND remarks LIKE '%migration 057%'
        ");
    }
}

<?php

/**
 * Migration 062 — Enforce one-document → one-report rule on committee_report_documents
 *
 * BACKGROUND
 * ──────────
 * Migration 042 created committee_report_documents with a COMPOSITE unique key:
 *
 *   UNIQUE KEY unique_report_document (committee_report_id, document_id)
 *
 * This prevents a document from being added to the SAME report twice, but it
 * does NOT prevent a document from being linked to two DIFFERENT reports.
 *
 * The business rule ("a document is eligible only if it has no existing
 * Committee Report") requires a UNIQUE KEY on document_id alone so that any
 * attempt to assign a document that already belongs to a report is rejected by
 * the database engine — regardless of which code path triggered the INSERT.
 *
 * WHAT THIS MIGRATION DOES
 * ────────────────────────
 * 1. Checks whether a UNIQUE index covering only document_id already exists on
 *    committee_report_documents.  If so, it is a no-op (safe to re-run).
 * 2. If not, it first checks for any existing duplicate document_id rows (there
 *    should be none in a correctly operated system, but we log them if found
 *    and abort rather than silently dropping data).
 * 3. Adds the new UNIQUE index: uq_crd_document_id (document_id).
 *
 * ROLLBACK
 * ────────
 * Drops the uq_crd_document_id index (reverts to composite-only enforcement).
 */
class AddUniqueDocumentConstraintToCommitteeReportDocuments
{
    public function up(PDO $pdo): void
    {
        // ── Guard: skip if the per-document unique index already exists ────────
        if ($this->uniqueIndexExists($pdo, 'committee_report_documents', 'uq_crd_document_id')) {
            echo "  ↳ uq_crd_document_id already exists — skipping.\n";
            return;
        }

        // ── Safety: detect duplicate document_id rows before adding constraint ─
        $dupStmt = $pdo->query("
            SELECT document_id, COUNT(*) AS cnt
            FROM committee_report_documents
            GROUP BY document_id
            HAVING cnt > 1
        ");
        $duplicates = $dupStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($duplicates)) {
            $list = implode(', ', array_column($duplicates, 'document_id'));
            throw new RuntimeException(
                "Migration 062 aborted: found document_id(s) assigned to multiple Committee Reports: [{$list}]. " .
                "Resolve duplicate assignments manually before running this migration."
            );
        }

        // ── Add the unique constraint ─────────────────────────────────────────
        $pdo->exec("
            ALTER TABLE committee_report_documents
            ADD UNIQUE KEY uq_crd_document_id (document_id)
        ");

        echo "  ✓ UNIQUE KEY uq_crd_document_id (document_id) added to committee_report_documents.\n";
    }

    // =========================================================================

    public function down(PDO $pdo): void
    {
        if (!$this->uniqueIndexExists($pdo, 'committee_report_documents', 'uq_crd_document_id')) {
            echo "  ↳ uq_crd_document_id does not exist — nothing to roll back.\n";
            return;
        }

        $pdo->exec("
            ALTER TABLE committee_report_documents
            DROP INDEX uq_crd_document_id
        ");

        echo "  ✓ UNIQUE KEY uq_crd_document_id dropped (rolled back).\n";
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Returns true if a UNIQUE index with the given name exists on the table.
     */
    private function uniqueIndexExists(PDO $pdo, string $table, string $indexName): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = ?
              AND INDEX_NAME   = ?
              AND NON_UNIQUE   = 0
        ");
        $stmt->execute([$table, $indexName]);
        return (int) $stmt->fetchColumn() > 0;
    }
}

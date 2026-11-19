<?php

/**
 * Migration 104 — Stable document resubmission lineage and version numbers.
 */
class DocumentResubmissionLineage
{
    public function up(PDO $pdo): void
    {
        $columns = $pdo->query("SHOW COLUMNS FROM document_resubmission_links")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('original_document_id', $columns, true)) {
            $pdo->exec("
                ALTER TABLE document_resubmission_links
                    ADD COLUMN original_document_id BIGINT UNSIGNED NULL
                        AFTER referenced_document_id
            ");
        }
        if (!in_array('version_number', $columns, true)) {
            $pdo->exec("
                ALTER TABLE document_resubmission_links
                    ADD COLUMN version_number INT UNSIGNED NULL
                        AFTER original_document_id
            ");
        }

        $links = $pdo->query("
            SELECT id, resubmitted_document_id, referenced_document_id
            FROM document_resubmission_links
            ORDER BY linked_at ASC, id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $parentByDocument = [];
        foreach ($links as $link) {
            $childId = (int) $link['resubmitted_document_id'];
            if (isset($parentByDocument[$childId])) {
                throw new RuntimeException("Multiple resubmission parent links exist for document {$childId}.");
            }
            $parentByDocument[$childId] = (int) $link['referenced_document_id'];
        }

        $rootCache = [];
        $findRoot = function (int $documentId, array $path = []) use (&$findRoot, &$rootCache, $parentByDocument): int {
            if (isset($rootCache[$documentId])) {
                return $rootCache[$documentId];
            }
            if (isset($path[$documentId])) {
                throw new RuntimeException('A cycle was found in existing document resubmission links.');
            }
            $path[$documentId] = true;
            $parentId = $parentByDocument[$documentId] ?? null;
            $rootCache[$documentId] = $parentId === null
                ? $documentId
                : $findRoot($parentId, $path);
            return $rootCache[$documentId];
        };

        $lineageLinks = [];
        foreach ($links as $link) {
            $childId = (int) $link['resubmitted_document_id'];
            $lineageLinks[] = [
                'id' => (int) $link['id'],
                'root_id' => $findRoot($childId),
            ];
        }

        $versionsByRoot = [];
        $update = $pdo->prepare("
            UPDATE document_resubmission_links
            SET original_document_id = ?, version_number = ?
            WHERE id = ?
        ");
        foreach ($lineageLinks as $link) {
            $rootId = $link['root_id'];
            $version = ($versionsByRoot[$rootId] ?? 1) + 1;
            $versionsByRoot[$rootId] = $version;
            $update->execute([$rootId, $version, $link['id']]);
        }

        $pdo->exec("
            ALTER TABLE document_resubmission_links
                MODIFY COLUMN original_document_id BIGINT UNSIGNED NOT NULL,
                MODIFY COLUMN version_number INT UNSIGNED NOT NULL,
                ADD UNIQUE KEY uq_drl_original_version (original_document_id, version_number),
                ADD CONSTRAINT fk_drl_original_document
                    FOREIGN KEY (original_document_id) REFERENCES documents (id)
                    ON DELETE RESTRICT
        ");
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("
            ALTER TABLE document_resubmission_links
                DROP FOREIGN KEY fk_drl_original_document,
                DROP INDEX uq_drl_original_version,
                DROP COLUMN version_number,
                DROP COLUMN original_document_id
        ");
    }
}

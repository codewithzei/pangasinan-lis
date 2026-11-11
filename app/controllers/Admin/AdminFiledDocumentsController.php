<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/DocumentService.php';

/**
 * AdminFiledDocumentsController
 *
 * Manages the Admin → Filed Documents page.
 *
 * Actions
 * ───────
 *   index()       GET  admin/filed             – "To Be Filed" queue + Filed table
 *   file()        POST admin/filed/file        – File a finalized document
 *   viewSource()  GET  admin/filed/source-show – View a finalized (not-yet-filed) document
 *   show()        GET  admin/filed/show        – View a completed filing detail
 *   download()    GET  admin/filed/download    – Stream an attachment (auth-guarded)
 *   downloadAll() GET  admin/filed/download-all – Stream or redirect for all attachments
 */
class AdminFiledDocumentsController
{
    protected PDO $pdo;

    public function __construct()
    {
        $database  = new Database();
        $this->pdo = $database->connect();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INDEX
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $authUserId = auth_id();
        if (!$authUserId) {
            redirect('login');
        }

        // ── "To Be Filed" queue ───────────────────────────────────────────
        // Finalized documents that do not yet have a document_filings row.
        $toBeFiledStmt = $this->pdo->query("
            SELECT
                d.id,
                d.tracking_number,
                d.subject_matter,
                d.finalized_at,
                dt.name        AS document_type_name,
                dt.badge_color AS document_type_badge_color,
                da_appr.approved_number,
                da_appr.date_approved
            FROM documents d
            LEFT JOIN document_types     dt      ON dt.id      = d.document_type_id
            LEFT JOIN document_approvals da_appr ON da_appr.document_id = d.id
            LEFT JOIN document_filings   df      ON df.document_id = d.id
            WHERE d.current_phase = 'FINALIZED'
              AND df.id IS NULL
            ORDER BY d.finalized_at ASC
        ");
        $toBeFiledDocs = $toBeFiledStmt->fetchAll();
        $toBeFiledCount = count($toBeFiledDocs);

        // ── Filed documents table ─────────────────────────────────────────
        $search      = trim($_GET['search']   ?? '');
        $filterType  = trim($_GET['type']     ?? '');
        $filterYear  = trim($_GET['year']     ?? '');
        $page        = max(1, (int)($_GET['page'] ?? 1));
        $perPage     = 20;
        $offset      = ($page - 1) * $perPage;

        $filterConditions = [];
        $filterParams     = [];

        if ($search !== '') {
            $filterConditions[] = "(d.tracking_number LIKE ? OR d.subject_matter LIKE ? OR da_appr.approved_number LIKE ?)";
            $filterParams[] = "%{$search}%";
            $filterParams[] = "%{$search}%";
            $filterParams[] = "%{$search}%";
        }

        if ($filterType !== '') {
            $filterConditions[] = 'dt.id = ?';
            $filterParams[] = $filterType;
        }

        if ($filterYear !== '') {
            $filterConditions[] = 'YEAR(da_appr.date_approved) = ?';
            $filterParams[] = $filterYear;
        }

        $extraWhere = count($filterConditions)
            ? 'AND ' . implode(' AND ', $filterConditions)
            : '';

        // Count query
        $countSql = "
            SELECT COUNT(*) AS total
            FROM documents d
            LEFT JOIN document_types     dt      ON dt.id      = d.document_type_id
            LEFT JOIN document_approvals da_appr ON da_appr.document_id = d.id
            INNER JOIN document_filings  df      ON df.document_id = d.id
            WHERE d.current_phase = 'FILED'
            {$extraWhere}
        ";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($filterParams);
        $totalRows  = (int) $countStmt->fetch()['total'];
        $totalPages = max(1, (int) ceil($totalRows / $perPage));

        // Main list query
        $listSql = "
            SELECT
                d.id,
                d.tracking_number,
                d.subject_matter,
                d.filed_at,
                dt.name            AS document_type_name,
                dt.badge_color     AS document_type_badge_color,
                da_appr.approved_number,
                da_appr.date_approved,
                df.id              AS filing_id,
                df.description     AS filing_description,
                df.tags            AS filing_tags_json,
                df.is_public       AS filing_is_public,
                df.filed_at        AS filing_filed_at,
                filer.username     AS filed_by_username
            FROM documents d
            LEFT JOIN document_types     dt      ON dt.id      = d.document_type_id
            LEFT JOIN document_approvals da_appr ON da_appr.document_id = d.id
            INNER JOIN document_filings  df      ON df.document_id = d.id
            LEFT JOIN user_accounts      filer   ON filer.id = df.filed_by
            WHERE d.current_phase = 'FILED'
            {$extraWhere}
            ORDER BY df.filed_at DESC, d.id DESC
            LIMIT ? OFFSET ?
        ";
        $listParams = array_merge($filterParams, [$perPage, $offset]);
        $listStmt   = $this->pdo->prepare($listSql);
        $listStmt->execute($listParams);
        $filedDocuments = $listStmt->fetchAll();

        // Decode tags JSON + fetch author strings for each filed document
        foreach ($filedDocuments as &$fd) {
            $fd['tags'] = !empty($fd['filing_tags_json'])
                ? (json_decode($fd['filing_tags_json'], true) ?? [])
                : [];

            // Authors/sponsors
            $authorsStmt = $this->pdo->prepare("
                SELECT
                    da.sp_member_id,
                    da.author_role,
                    pdd.authors_all,
                    pdd.co_authors_all,
                    TRIM(CONCAT_WS(' ',
                        sm.first_name,
                        COALESCE(NULLIF(TRIM(sm.middle_name),''), NULL),
                        sm.last_name,
                        COALESCE(NULLIF(TRIM(sm.suffix),''), NULL)
                    )) AS full_name
                FROM document_authors da
                INNER JOIN sp_members sm ON sm.sp_member_id = da.sp_member_id
                LEFT JOIN plenary_document_details pdd ON pdd.document_id = da.document_id
                WHERE da.document_id = ?
                ORDER BY da.author_role ASC, da.sort_order ASC
            ");
            $authorsStmt->execute([$fd['id']]);
            $fd['authors'] = $this->markAllSelectedAuthorGroups($authorsStmt->fetchAll());
        }
        unset($fd);

        // Filter options
        $documentTypes = $this->pdo->query("
            SELECT id, name FROM document_types
            WHERE is_active = 1 AND is_deleted = 0
            ORDER BY name ASC
        ")->fetchAll();

        $approvalYears = $this->pdo->query("
            SELECT DISTINCT YEAR(date_approved) AS yr
            FROM document_approvals
            WHERE date_approved IS NOT NULL
            ORDER BY yr DESC
        ")->fetchAll(PDO::FETCH_COLUMN);

        // Stats
        $totalFiledCount = (int) $this->pdo->query("
            SELECT COUNT(*) FROM documents WHERE current_phase = 'FILED'
        ")->fetchColumn();

        $publicCount = (int) $this->pdo->query("
            SELECT COUNT(*) FROM document_filings WHERE is_public = 1
        ")->fetchColumn();

        $pageTitle    = 'Filed Documents';
        $pageSubtitle = 'Manage finalized documents and the filing archive';

        ob_start();
        require __DIR__ . '/../../../resources/views/admin/filed-documents/index.php';
        $content = ob_get_clean();
        require __DIR__ . '/../../../resources/views/layouts/app.php';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FILE  –  process a filing submission
    // ─────────────────────────────────────────────────────────────────────────

    public function file(): void
    {
        $authUserId = auth_id();
        if (!$authUserId) {
            redirect('login');
        }

        $documentId = (int) ($_POST['document_id'] ?? 0);

        if ($documentId <= 0) {
            flash_set('error', 'Invalid document ID.');
            redirect('admin/filed');
        }

        // ── Verify document is finalized and not yet filed ────────────────
        $docStmt = $this->pdo->prepare("
            SELECT d.id, d.tracking_number, d.subject_matter, d.current_phase,
                   d.current_status_id,
                   df.id AS existing_filing_id
            FROM documents d
            LEFT JOIN document_filings df ON df.document_id = d.id
            WHERE d.id = ?
            LIMIT 1
        ");
        $docStmt->execute([$documentId]);
        $doc = $docStmt->fetch();

        if (!$doc) {
            flash_set('error', 'Document not found.');
            redirect('admin/filed');
        }

        if ($doc['existing_filing_id']) {
            flash_set('error', 'This document has already been filed.');
            redirect('admin/filed');
        }

        if ($doc['current_phase'] !== 'FINALIZED') {
            flash_set('error', 'Only finalized documents can be filed.');
            redirect('admin/filed');
        }

        // ── Server-side validation ────────────────────────────────────────
        $description = trim($_POST['description'] ?? '');
        $tagsRaw     = trim($_POST['tags']        ?? '');
        $isPublic    = isset($_POST['is_public']) ? 1 : 0;

        $errors = [];

        if ($description === '') {
            $errors[] = 'Filing description is required.';
        } elseif (mb_strlen($description) > 5000) {
            $errors[] = 'Filing description must not exceed 5000 characters.';
        }

        if (!empty($errors)) {
            flash_set('errors', $errors);
            flash_set('filing_doc_id', $documentId);
            redirect('admin/filed');
        }

        // ── Parse tags ────────────────────────────────────────────────────
        $tags = [];
        if ($tagsRaw !== '') {
            $tags = array_values(array_filter(
                array_map('trim', explode(',', $tagsRaw)),
                fn($t) => $t !== ''
            ));
        }

        // ── Resolve "Filed" status ID ─────────────────────────────────────
        $filedStatus = $this->pdo->query("
            SELECT id FROM document_statuses
            WHERE name = 'Filed' AND is_active = 1 AND is_deleted = 0
            LIMIT 1
        ")->fetch();

        // Fallback: reuse current status if "Filed" status row doesn't exist
        $filedStatusId = $filedStatus ? (int) $filedStatus['id'] : (int) $doc['current_status_id'];

        // ── Atomic transaction ────────────────────────────────────────────
        $this->pdo->beginTransaction();

        try {
            // 1. Insert document_filings row
            $insertFiling = $this->pdo->prepare("
                INSERT INTO document_filings (document_id, description, tags, is_public, filed_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            $insertFiling->execute([
                $documentId,
                $description ?: null,
                !empty($tags) ? json_encode($tags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                $isPublic,
                $authUserId,
            ]);
            $filingId = (int) $this->pdo->lastInsertId();

            // 2. Update document phase → FILED
            $this->pdo->prepare("
                UPDATE documents
                SET current_phase     = 'FILED',
                    current_status_id = ?,
                    filed_at          = NOW(),
                    updated_by        = ?
                WHERE id = ?
            ")->execute([$filedStatusId, $authUserId, $documentId]);

            // 3. Document event
            $this->pdo->prepare("
                INSERT INTO document_events
                    (document_id, event_type, phase, performed_by, to_status_id, remarks, metadata)
                VALUES (?, 'DOCUMENT_FILED', 'FILED', ?, ?, ?, ?)
            ")->execute([
                $documentId,
                $authUserId,
                $filedStatusId,
                'Document filed by admin.',
                json_encode([
                    'filing_id'   => $filingId,
                    'is_public'   => $isPublic,
                    'tags'        => $tags,
                    'ip_address'  => client_ip(),
                    'user_agent'  => client_user_agent(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            $this->pdo->commit();

        } catch (Throwable $e) {
            $this->pdo->rollBack();

            system_log('ERROR', 'AdminFiledDocumentsController: filing transaction failed', [
                'document_id' => $documentId,
                'error'       => $e->getMessage(),
                'user_id'     => $authUserId,
            ]);

            flash_set('errors', ['A system error occurred while filing the document. Please try again.']);
            flash_set('filing_doc_id', $documentId);
            redirect('admin/filed');
        }

        // ── Post-commit: audit log ────────────────────────────────────────
        audit_log('CREATE', 'DocumentFiling', (string) $filingId, null, [
            'document_id' => $documentId,
            'is_public'   => $isPublic,
            'tags'        => $tags,
        ], "Document #{$doc['tracking_number']} filed.");

        flash_set('success', "Document {$doc['tracking_number']} has been successfully filed.");
        redirect('admin/filed');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VIEW SOURCE  –  read-only detail for a finalized but not-yet-filed doc
    // ─────────────────────────────────────────────────────────────────────────
    // These documents are in the "To Be Filed" queue.  They have
    // current_phase = 'FINALIZED' so the standard show() (which filters on
    // 'FILED') returns nothing.  This action accepts either phase so the
    // Admin can review the full document before deciding to file it.
    // ─────────────────────────────────────────────────────────────────────────

    public function viewSource(): void
    {
        $authUserId = auth_id();
        if (!$authUserId) {
            redirect('login');
        }

        $documentId = (int) ($_GET['id'] ?? 0);
        if ($documentId <= 0) {
            flash_set('error', 'Invalid document ID.');
            redirect('admin/filed');
        }

        // Fetch document — accepts FINALIZED or FILED (covers edge case where
        // another admin filed it between page load and this request)
        $docSql = "
            SELECT
                d.*,
                dt.name            AS document_type_name,
                dt.badge_color     AS document_type_badge_color,
                ds.name            AS status,
                ds.badge_color     AS status_badge_color,
                st.name            AS source_type,
                eo.name            AS external_office_name,
                eo.abbreviation    AS external_office_abbr,
                h.name             AS hospital_name,
                m.name             AS municipality_name,
                m.type             AS municipality_type,
                creator.username   AS created_by_username,
                updater.username   AS updated_by_username,
                da_appr.approved_number,
                da_appr.date_approved
            FROM documents d
            LEFT JOIN document_types     dt      ON dt.id       = d.document_type_id
            LEFT JOIN document_statuses  ds      ON ds.id       = d.current_status_id
            LEFT JOIN source_types       st      ON st.id       = d.source_type_id
            LEFT JOIN provincial_government_offices   eo      ON eo.id       = d.external_office_id
            LEFT JOIN hospitals          h       ON h.id        = d.hospital_id
            LEFT JOIN municities         m       ON m.id        = d.municipality_id
            LEFT JOIN user_accounts      creator ON creator.id  = d.created_by
            LEFT JOIN user_accounts      updater ON updater.id  = d.updated_by
            LEFT JOIN document_approvals da_appr ON da_appr.document_id = d.id
            WHERE d.id = ?
              AND d.current_phase = 'FINALIZED'
            LIMIT 1
        ";
        $docStmt = $this->pdo->prepare($docSql);
        $docStmt->execute([$documentId]);
        $document = $docStmt->fetch();

        if (!$document) {
            flash_set('error', 'Document not found or not in a finalized state.');
            redirect('admin/filed');
        }

        // If it was filed in the meantime, redirect to the proper show page
        if ($document['current_phase'] === 'FILED') {
            redirect("admin/filed/show?id={$documentId}");
        }

        // Authors/sponsors
        $authorsStmt = $this->pdo->prepare("
            SELECT
                da.sp_member_id,
                da.author_role,
                da.sort_order,
                pdd.authors_all,
                pdd.co_authors_all,
                TRIM(CONCAT_WS(' ',
                    sm.first_name,
                    COALESCE(NULLIF(TRIM(sm.middle_name),''), NULL),
                    sm.last_name,
                    COALESCE(NULLIF(TRIM(sm.suffix),''), NULL)
                )) AS full_name
            FROM document_authors da
            INNER JOIN sp_members sm ON sm.sp_member_id = da.sp_member_id
            LEFT JOIN plenary_document_details pdd ON pdd.document_id = da.document_id
            WHERE da.document_id = ?
            ORDER BY da.author_role ASC, da.sort_order ASC
        ");
        $authorsStmt->execute([$documentId]);
        $authors = $this->markAllSelectedAuthorGroups($authorsStmt->fetchAll());

        // All attachments for this document
        $allAttachmentsStmt = $this->pdo->prepare("
            SELECT id, file_name, stored_path, mime_type, file_size,
                   phase, attachment_type, created_at
            FROM document_attachments
            WHERE document_id = ?
            ORDER BY created_at ASC
        ");
        $allAttachmentsStmt->execute([$documentId]);
        $allAttachments = $allAttachmentsStmt->fetchAll();

        $pageTitle    = 'Finalized Document Details';
        $pageSubtitle = $document['tracking_number'];

        ob_start();
        require __DIR__ . '/../../../resources/views/admin/filed-documents/source-show.php';
        $content = ob_get_clean();
        require __DIR__ . '/../../../resources/views/layouts/app.php';
    }

    /**
     * Preview an attachment for a finalized document in the filing queue.
     */
    public function previewSourceAttachment(): void
    {
        $authUserId = auth_id();
        if (!$authUserId) {
            redirect('login');
        }

        if (!in_array(auth_role(), ['Admin', 'Super Admin'], true)) {
            http_response_code(403);
            exit('Access denied.');
        }

        $attachmentId = (int) ($_GET['attachment_id'] ?? 0);
        $documentId   = (int) ($_GET['document_id'] ?? 0);
        if ($attachmentId <= 0 || $documentId <= 0) {
            http_response_code(400);
            exit('Invalid request.');
        }

        $stmt = $this->pdo->prepare("
            SELECT da.file_name, da.stored_path, da.mime_type, da.file_size
            FROM document_attachments da
            INNER JOIN documents d ON d.id = da.document_id
            WHERE da.id = ?
              AND da.document_id = ?
              AND d.current_phase IN ('FINALIZED', 'FILED')
            LIMIT 1
        ");
        $stmt->execute([$attachmentId, $documentId]);
        $attachment = $stmt->fetch();
        if (!$attachment) {
            http_response_code(404);
            exit('Attachment not found.');
        }

        $relativePath = str_replace('\\', '/', (string) $attachment['stored_path']);
        $relativePath = ltrim($relativePath, '/');
        $filePath = PUBLIC_PATH . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $realPath = realpath($filePath);
        $realPublic = realpath(PUBLIC_PATH);
        $publicPrefix = $realPublic !== false
            ? rtrim($realPublic, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            : '';

        if (
            $realPath === false
            || $publicPrefix === ''
            || strpos($realPath, $publicPrefix) !== 0
        ) {
            http_response_code(403);
            exit('Access denied.');
        }
        if (!is_file($realPath) || !is_readable($realPath)) {
            http_response_code(404);
            exit('File not found.');
        }

        $mode = (string) ($_GET['mode'] ?? '');
        $filename = basename((string) $attachment['file_name']);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $previewExtensions = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'docx'];
        if (in_array($mode, ['raw'], true) && !in_array($extension, $previewExtensions, true)) {
            http_response_code(415);
            exit('Inline preview is not available for this file type.');
        }
        $previewMimeTypes = [
            'pdf'  => 'application/pdf',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        $mimeType = $previewMimeTypes[$extension] ?? (string) $attachment['mime_type'];
        if (!preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9.+-]*\/[a-zA-Z0-9][a-zA-Z0-9.+-]*\z/', $mimeType)) {
            $mimeType = 'application/octet-stream';
        }

        if (in_array($mode, ['raw', 'download'], true)) {
            $safeFilename = str_replace(["\r", "\n", '"', '\\'], '_', $filename);
            $disposition = $mode === 'download' ? 'attachment' : 'inline';
            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: ' . $disposition . '; filename="' . $safeFilename . '"');
            header('Content-Length: ' . filesize($realPath));
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('Cache-Control: private, no-store');
            readfile($realPath);
            exit;
        }

        $rawUrl = BASE_URL . '/admin/filed/source-attachment?attachment_id='
            . $attachmentId . '&document_id=' . $documentId . '&mode=raw';
        $downloadUrl = BASE_URL . '/admin/filed/source-attachment?attachment_id='
            . $attachmentId . '&document_id=' . $documentId . '&mode=download';
        $pageTitle = 'Attachment Preview';
        $pageSubtitle = $filename;
        $isDocx = $extension === 'docx';
        $isBrowserPreviewable = in_array($extension, ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp'], true);

        ob_start();
        require __DIR__ . '/../../../resources/views/admin/filed-documents/source-attachment-preview.php';
        $content = ob_get_clean();
        require __DIR__ . '/../../../resources/views/layouts/app.php';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SHOW  –  detail view for one filed document
    // ─────────────────────────────────────────────────────────────────────────

    public function show(): void
    {
        $authUserId = auth_id();
        if (!$authUserId) {
            redirect('login');
        }

        $documentId = (int) ($_GET['id'] ?? 0);
        if ($documentId <= 0) {
            flash_set('error', 'Invalid document ID.');
            redirect('admin/filed');
        }

        // Fetch full document + filing + approval data
        $docSql = "
            SELECT
                d.*,
                dt.name            AS document_type_name,
                dt.badge_color     AS document_type_badge_color,
                ds.name            AS status,
                ds.badge_color     AS status_badge_color,
                st.name            AS source_type,
                eo.name            AS external_office_name,
                eo.abbreviation    AS external_office_abbr,
                h.name             AS hospital_name,
                m.name             AS municipality_name,
                m.type             AS municipality_type,
                creator.username   AS created_by_username,
                updater.username   AS updated_by_username,
                da_appr.approved_number,
                da_appr.date_approved,
                df.id              AS filing_id,
                df.description     AS filing_description,
                df.tags            AS filing_tags_json,
                df.is_public       AS filing_is_public,
                df.filed_at        AS filing_filed_at,
                filer.username     AS filed_by_username
            FROM documents d
            LEFT JOIN document_types     dt      ON dt.id       = d.document_type_id
            LEFT JOIN document_statuses  ds      ON ds.id       = d.current_status_id
            LEFT JOIN source_types       st      ON st.id       = d.source_type_id
            LEFT JOIN provincial_government_offices   eo      ON eo.id       = d.external_office_id
            LEFT JOIN hospitals          h       ON h.id        = d.hospital_id
            LEFT JOIN municities         m       ON m.id        = d.municipality_id
            LEFT JOIN user_accounts      creator ON creator.id  = d.created_by
            LEFT JOIN user_accounts      updater ON updater.id  = d.updated_by
            LEFT JOIN document_approvals da_appr ON da_appr.document_id = d.id
            LEFT JOIN document_filings   df      ON df.document_id      = d.id
            LEFT JOIN user_accounts      filer   ON filer.id    = df.filed_by
            WHERE d.id = ?
              AND d.current_phase = 'FILED'
            LIMIT 1
        ";
        $docStmt = $this->pdo->prepare($docSql);
        $docStmt->execute([$documentId]);
        $document = $docStmt->fetch();

        if (!$document) {
            flash_set('error', 'Filed document not found.');
            redirect('admin/filed');
        }

        // Tags
        $document['tags'] = !empty($document['filing_tags_json'])
            ? (json_decode($document['filing_tags_json'], true) ?? [])
            : [];

        // Authors/sponsors
        $authorsStmt = $this->pdo->prepare("
            SELECT
                da.sp_member_id,
                da.author_role,
                da.sort_order,
                pdd.authors_all,
                pdd.co_authors_all,
                TRIM(CONCAT_WS(' ',
                    sm.first_name,
                    COALESCE(NULLIF(TRIM(sm.middle_name),''), NULL),
                    sm.last_name,
                    COALESCE(NULLIF(TRIM(sm.suffix),''), NULL)
                )) AS full_name
            FROM document_authors da
            INNER JOIN sp_members sm ON sm.sp_member_id = da.sp_member_id
            LEFT JOIN plenary_document_details pdd ON pdd.document_id = da.document_id
            WHERE da.document_id = ?
            ORDER BY da.author_role ASC, da.sort_order ASC
        ");
        $authorsStmt->execute([$documentId]);
        $authors = $this->markAllSelectedAuthorGroups($authorsStmt->fetchAll());

        // Filing attachments (via join table for precision, fallback to phase/type)
        $filingId = (int) ($document['filing_id'] ?? 0);

        if ($filingId > 0) {
            $attachStmt = $this->pdo->prepare("
                SELECT
                    da.id,
                    da.file_name,
                    da.stored_path,
                    da.mime_type,
                    da.file_size,
                    da.phase,
                    da.attachment_type,
                    da.created_at
                FROM document_filing_attachments dfa
                INNER JOIN document_attachments da ON da.id = dfa.attachment_id
                WHERE dfa.filing_id = ?
                ORDER BY da.created_at ASC
            ");
            $attachStmt->execute([$filingId]);
        } else {
            // Fallback: attachments for this document in FILED phase
            $attachStmt = $this->pdo->prepare("
                SELECT id, file_name, stored_path, mime_type, file_size,
                       phase, attachment_type, created_at
                FROM document_attachments
                WHERE document_id     = ?
                  AND attachment_type = 'FILING_FILE'
                ORDER BY created_at ASC
            ");
            $attachStmt->execute([$documentId]);
        }
        $filingAttachments = $attachStmt->fetchAll();

        // All document attachments (full history)
        $allAttachmentsStmt = $this->pdo->prepare("
            SELECT id, file_name, stored_path, mime_type, file_size,
                   phase, attachment_type, created_at
            FROM document_attachments
            WHERE document_id = ?
            ORDER BY created_at ASC
        ");
        $allAttachmentsStmt->execute([$documentId]);
        $allAttachments = $allAttachmentsStmt->fetchAll();

        $documentEvents = (new DocumentService($this->pdo))->getDocumentEvents($documentId);

        $pageTitle    = 'Filed Document Details';
        $pageSubtitle = $document['tracking_number'];

        ob_start();
        require __DIR__ . '/../../../resources/views/admin/filed-documents/show.php';
        $content = ob_get_clean();
        require __DIR__ . '/../../../resources/views/layouts/app.php';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DOWNLOAD ALL  –  redirect to first attachment or list page
    // ─────────────────────────────────────────────────────────────────────────
    // When a filing has a single attachment, streams it directly.
    // When there are multiple attachments, redirects to the show page so the
    // user can choose which file to download — zip creation is not available
    // without a third-party library in this project.
    // ─────────────────────────────────────────────────────────────────────────

    public function downloadAll(): void
    {
        $authUserId = auth_id();
        if (!$authUserId) {
            redirect('login');
        }

        $documentId = (int) ($_GET['id'] ?? 0);
        if ($documentId <= 0) {
            flash_set('error', 'Invalid document ID.');
            redirect('admin/filed');
        }

        // Fetch the filing record
        $filingStmt = $this->pdo->prepare("
            SELECT df.id, df.is_public
            FROM document_filings df
            INNER JOIN documents d ON d.id = df.document_id
            WHERE df.document_id = ?
              AND d.current_phase = 'FILED'
            LIMIT 1
        ");
        $filingStmt->execute([$documentId]);
        $filing = $filingStmt->fetch();

        if (!$filing) {
            flash_set('error', 'Filed document not found.');
            redirect('admin/filed');
        }

        // Enforce visibility for non-public filings
        if (!$filing['is_public']) {
            $role = auth_role();
            if (!in_array($role, ['Super Admin', 'Admin'], true)) {
                flash_set('error', 'You do not have permission to download this document.');
                redirect('admin/filed');
            }
        }

        // Fetch filing attachments
        $attachStmt = $this->pdo->prepare("
            SELECT da.id, da.file_name, da.stored_path, da.mime_type, da.file_size
            FROM document_filing_attachments dfa
            INNER JOIN document_attachments da ON da.id = dfa.attachment_id
            WHERE dfa.filing_id = ?
            ORDER BY da.created_at ASC
        ");
        $attachStmt->execute([(int)$filing['id']]);
        $attachments = $attachStmt->fetchAll();

        if (empty($attachments)) {
            flash_set('error', 'No attachments found for this filing.');
            redirect("admin/filed/show?id={$documentId}");
        }

        // Single file: stream directly
        if (count($attachments) === 1) {
            $att      = $attachments[0];
            $filePath = PUBLIC_PATH . '/' . $att['stored_path'];
            $realPath = realpath($filePath);
            $realPub  = realpath(PUBLIC_PATH);

            if ($realPath === false || $realPub === false || strpos($realPath, $realPub) !== 0) {
                flash_set('error', 'File access denied.');
                redirect("admin/filed/show?id={$documentId}");
            }

            if (!file_exists($filePath)) {
                flash_set('error', 'Attachment file not found on disk.');
                redirect("admin/filed/show?id={$documentId}");
            }

            log_file_download($att['file_name'], 'FilingAttachment', (string)$att['id'], $att['stored_path']);

            $safe = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $att['file_name']);
            header('Content-Type: '        . $att['mime_type']);
            header('Content-Disposition: attachment; filename="' . $safe . '"');
            header('Content-Length: '      . $att['file_size']);
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            readfile($filePath);
            exit;
        }

        // Multiple files: redirect to show page so user can choose
        redirect("admin/filed/show?id={$documentId}");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DOWNLOAD  –  stream a filing attachment
    // ─────────────────────────────────────────────────────────────────────────

    public function download(): void
    {
        $authUserId = auth_id();

        $attachmentId = (int) ($_GET['attachment_id'] ?? 0);
        $documentId   = (int) ($_GET['document_id']   ?? 0);

        if ($attachmentId <= 0 || $documentId <= 0) {
            http_response_code(400);
            exit('Invalid request.');
        }

        // Fetch the attachment + filing visibility
        $stmt = $this->pdo->prepare("
            SELECT
                da.id,
                da.file_name,
                da.stored_path,
                da.mime_type,
                da.file_size,
                da.attachment_type,
                da.document_id,
                df.is_public AS filing_is_public
            FROM document_attachments da
            INNER JOIN documents       d  ON d.id  = da.document_id
            LEFT  JOIN document_filings df ON df.document_id = d.id
            WHERE da.id          = ?
              AND da.document_id = ?
              AND d.current_phase = 'FILED'
            LIMIT 1
        ");
        $stmt->execute([$attachmentId, $documentId]);
        $attachment = $stmt->fetch();

        if (!$attachment) {
            http_response_code(404);
            exit('Attachment not found.');
        }

        // Access control: private filings require authentication
        $isPublic = (bool) ($attachment['filing_is_public'] ?? false);

        if (!$isPublic && !$authUserId) {
            http_response_code(403);
            exit('This file is not publicly accessible.');
        }

        // Non-public: only authenticated admin/super-admin users
        if (!$isPublic && $authUserId) {
            $role = auth_role();
            if (!in_array($role, ['Super Admin', 'Admin'], true)) {
                http_response_code(403);
                exit('Access denied.');
            }
        }

        // Resolve file path
        $filePath = PUBLIC_PATH . '/' . $attachment['stored_path'];
        $realPath = realpath($filePath);
        $realPublic = realpath(PUBLIC_PATH);

        if ($realPath === false || $realPublic === false || strpos($realPath, $realPublic) !== 0) {
            http_response_code(403);
            system_log('WARNING', 'Potential directory traversal in filed-document download', [
                'attachment_id' => $attachmentId,
                'stored_path'   => $attachment['stored_path'],
                'ip_address'    => client_ip(),
            ]);
            exit('Access denied.');
        }

        if (!file_exists($filePath)) {
            http_response_code(404);
            system_log('ERROR', 'Filing attachment file missing on disk', [
                'attachment_id' => $attachmentId,
                'stored_path'   => $attachment['stored_path'],
            ]);
            exit('File not found.');
        }

        $inlinePreview = ($_GET['mode'] ?? '') === 'inline';
        $safeFilename = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $attachment['file_name']);
        if ($inlinePreview) {
            $extension = strtolower(pathinfo($safeFilename, PATHINFO_EXTENSION));
            $previewMimeTypes = [
                'pdf'  => 'application/pdf',
                'png'  => 'image/png',
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif'  => 'image/gif',
                'webp' => 'image/webp',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ];
            if (!isset($previewMimeTypes[$extension])) {
                http_response_code(415);
                exit('Inline preview is not available for this file type.');
            }
            $mimeType = $previewMimeTypes[$extension];
        } else {
            log_file_download(
                $attachment['file_name'],
                'FilingAttachment',
                (string) $attachmentId,
                $attachment['stored_path']
            );
            $mimeType = $attachment['mime_type'];
        }

        header('Content-Type: '        . $mimeType);
        header('Content-Disposition: ' . ($inlinePreview ? 'inline' : 'attachment') . '; filename="' . $safeFilename . '"');
        header('Content-Length: '      . $attachment['file_size']);
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: ' . ($inlinePreview ? 'SAMEORIGIN' : 'DENY'));
        readfile($filePath);
        exit;
    }

    private function markAllSelectedAuthorGroups(array $authors): array
    {
        foreach ($authors as &$author) {
            $allFlag = $author['author_role'] === 'AUTHOR' ? 'authors_all' : 'co_authors_all';
            $author['is_all_selected'] = !empty($author[$allFlag]);
        }
        unset($author);

        return $authors;
    }
}

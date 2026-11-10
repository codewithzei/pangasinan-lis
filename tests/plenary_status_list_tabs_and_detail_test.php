<?php
/**
 * Test: Plenary Finalized Documents — tabs + read-only full-detail view
 *
 * Covers the two deliverables on the Plenary Finalized Documents page:
 *
 *   GROUP A — Tab behavior (mirrors PlenaryReadingController::statusList)
 *     A1  Legislative tab lists only 'Second Reading Approved' documents.
 *     A2  Communications tab lists only 'Plenary Comm: Finalized' documents.
 *     A3  The two tabs never overlap.
 *     A4  Tab badge counts match each tab's list.
 *     A5  Search narrows the legislative tab by approved_no.
 *     A6  Search narrows the communications tab by resolution_number.
 *     A7  Resolution files load per communications row.
 *
 *   GROUP B — Full-detail view data (mirrors ::statusListShow)
 *     B1  Gating accepts a finalized legislative document.
 *     B2  Gating accepts a finalized communication document.
 *     B3  Gating rejects a document that is not finalized.
 *     B4  Back-tab is derived from the finalized status.
 *     B5  Events are returned chronologically (ASC).
 *     B6  Attachments and assignments are returned for the document.
 *     B7  Second-Reading details link through the reading row.
 *     B8  Communication-only fetches run for comm docs and are absent otherwise.
 *     B9  Source information resolves and tolerates NULL optional columns.
 *     B10 The merged history timeline is chronological and empty-safe.
 *
 *   GROUP C — Routing / permissions preserved
 *     C1  plenary/status-list/show is registered and maps to statusListShow.
 *     C2  It uses the same middleware as plenary/status-list.
 *     C3  The role gate still allows only Super Admin / Plenary.
 *
 * Strategy
 * --------
 * - Wrap all DB work in a transaction and ROLLBACK at the end (zero side-effects).
 * - Re-issue the exact SQL predicates the controller uses and assert the result,
 *   per this repository's no-HTTP standalone-PDO test convention.
 *
 * Run with:  php tests/plenary_status_list_tabs_and_detail_test.php
 */

declare(strict_types=1);

// ── Bootstrap ──────────────────────────────────────────────────────────────
$dsn = 'mysql:host=127.0.0.1;port=3306;dbname=pangasinan_lis;charset=utf8mb4';
try {
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    echo "FATAL: Cannot connect to database — " . $e->getMessage() . PHP_EOL;
    exit(1);
}

$passed = 0;
$failed = 0;

function pass(string $label): void {
    global $passed;
    echo "  [PASS] {$label}" . PHP_EOL;
    $passed++;
}
function fail(string $label, string $detail = ''): void {
    global $failed;
    echo "  [FAIL] {$label}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
    $failed++;
}
function check(string $label, bool $cond, string $detail = ''): void {
    $cond ? pass($label) : fail($label, $detail);
}

// ── Runtime lookups (never hardcode ids) ───────────────────────────────────
function statusId(PDO $pdo, string $name): int {
    $s = $pdo->prepare("SELECT id FROM document_statuses WHERE name=? AND is_active=1 AND is_deleted=0 LIMIT 1");
    $s->execute([$name]);
    $r = $s->fetch();
    if (!$r) { throw new RuntimeException("Status '{$name}' not found — run pending migrations."); }
    return (int) $r['id'];
}
function roleId(PDO $pdo, string $name): int {
    $s = $pdo->prepare("SELECT id FROM roles WHERE name=? AND is_active=1 AND is_deleted=0 LIMIT 1");
    $s->execute([$name]);
    $r = $s->fetch();
    if (!$r) { throw new RuntimeException("Role '{$name}' not found."); }
    return (int) $r['id'];
}

// ═══════════════════════════════════════════════════════════════════════════
// Controller query mirrors (kept in sync with PlenaryReadingController)
// ═══════════════════════════════════════════════════════════════════════════

function slTabCount(PDO $pdo, string $statusName): int {
    $s = $pdo->prepare("
        SELECT COUNT(*) FROM documents d
        INNER JOIN document_statuses ds ON ds.id = d.current_status_id
        WHERE ds.name = ? AND ds.is_active = 1 AND ds.is_deleted = 0
    ");
    $s->execute([$statusName]);
    return (int) $s->fetchColumn();
}

function slLegislativeList(PDO $pdo, int $statusId, string $search = ''): array {
    $extra  = '';
    $params = [$statusId];
    if ($search !== '') {
        $extra = " AND (d.tracking_number LIKE ? OR d.subject_matter LIKE ? OR psrd.approved_no LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    $s = $pdo->prepare("
        SELECT d.id
        FROM documents d
        LEFT JOIN plenary_readings pr2 ON pr2.document_id = d.id AND pr2.reading_number = 2 AND pr2.action = 'APPROVED'
        LEFT JOIN plenary_second_reading_details psrd ON psrd.reading_id = pr2.id
        WHERE d.current_status_id = ? {$extra}
    ");
    $s->execute($params);
    return array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
}

function slCommList(PDO $pdo, int $statusId, string $search = ''): array {
    $extra  = '';
    $params = [$statusId];
    if ($search !== '') {
        $extra = " AND (d.tracking_number LIKE ? OR d.subject_matter LIKE ?
                        OR pcfr.resolution_number LIKE ? OR pca.resolution_number LIKE ?)";
        for ($i = 0; $i < 4; $i++) { $params[] = "%{$search}%"; }
    }
    $s = $pdo->prepare("
        SELECT d.id
        FROM documents d
        LEFT JOIN plenary_comm_first_reading pcfr ON pcfr.document_id = d.id
        LEFT JOIN plenary_comm_adoptions pca      ON pca.document_id  = d.id
        WHERE d.current_status_id = ? {$extra}
    ");
    $s->execute($params);
    return array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
}

function slFetchEventsChronological(PDO $pdo, int $documentId): array {
    $s = $pdo->prepare("
        SELECT de.*, ua.username AS performed_by_username, ds.name AS to_status_name
        FROM document_events de
        LEFT JOIN user_accounts ua ON ua.id = de.performed_by
        LEFT JOIN document_statuses ds ON ds.id = de.to_status_id
        WHERE de.document_id = ?
        ORDER BY de.created_at ASC, de.id ASC
    ");
    $s->execute([$documentId]);
    return $s->fetchAll();
}

function slFetchAttachments(PDO $pdo, int $documentId): array {
    $s = $pdo->prepare("
        SELECT da.*, ua.username AS uploaded_by_username
        FROM document_attachments da
        LEFT JOIN user_accounts ua ON ua.id = da.uploaded_by
        WHERE da.document_id = ?
        ORDER BY da.created_at ASC
    ");
    $s->execute([$documentId]);
    return $s->fetchAll();
}

function slFetchAllAssignments(PDO $pdo, int $documentId): array {
    $s = $pdo->prepare("
        SELECT da.*, ab.username AS accepted_by_username, r.name AS role_name
        FROM document_assignments da
        LEFT JOIN user_accounts ab ON ab.id = da.accepted_by
        LEFT JOIN roles r ON r.id = da.assigned_to_role_id
        WHERE da.document_id = ?
        ORDER BY da.id ASC
    ");
    $s->execute([$documentId]);
    return $s->fetchAll();
}

function slFetchDocumentSource(PDO $pdo, int $documentId): array {
    $s = $pdo->prepare("
        SELECT st.name AS source_type_name,
               eo.name AS external_office_name,
               h.name  AS hospital_name,
               m.name  AS municity_name,
               d.source_name, d.source_contact_number,
               d.source_address, d.source_liaison_name
        FROM documents d
        LEFT JOIN source_types     st ON st.id = d.source_type_id
        LEFT JOIN provincial_government_offices eo ON eo.id = d.external_office_id
        LEFT JOIN hospitals        h  ON h.id  = d.hospital_id
        LEFT JOIN municities       m  ON m.id  = d.municipality_id
        WHERE d.id = ?
        LIMIT 1
    ");
    $s->execute([$documentId]);
    return $s->fetch() ?: [];
}

function slFetchReading(PDO $pdo, int $documentId, int $n): ?array {
    $s = $pdo->prepare("
        SELECT pr.* FROM plenary_readings pr
        WHERE pr.document_id = ? AND pr.reading_number = ?
        ORDER BY pr.id DESC LIMIT 1
    ");
    $s->execute([$documentId, $n]);
    return $s->fetch() ?: null;
}

/** Mirrors the ordering decision of the merged timeline in show.php. */
function slBuildTimeline(array $events, ?array $first, ?array $second): array {
    $tl = [];
    foreach ($events as $ev) { $tl[] = ['ts' => $ev['created_at'] ?? '']; }
    if ($first)  { $tl[] = ['ts' => ($first['reading_date'] ?? '') . ' 00:00:00']; }
    if ($second) { $tl[] = ['ts' => ($second['reading_date'] ?? '') . ' 00:00:00']; }
    usort($tl, static fn($a, $b) => strcmp((string) $a['ts'], (string) $b['ts']));
    return $tl;
}

/** Mirrors RoleMiddleware prefix-match for the plenary module. */
function roleAllowedForRoute(string $route, string $role): bool {
    $allowed = match (true) {
        str_starts_with($route, 'plenary') => ['Super Admin', 'Plenary'],
        default => null,
    };
    if ($allowed === null) { return true; }
    return in_array($role, $allowed, true);
}

// ── Fixture prerequisites ──────────────────────────────────────────────────
$sourceTypeId = (int) $pdo->query("SELECT id FROM source_types WHERE is_active=1 AND is_deleted=0 ORDER BY id LIMIT 1")->fetchColumn();
$docTypeId    = (int) $pdo->query("SELECT id FROM document_types WHERE is_active=1 AND is_deleted=0 ORDER BY id LIMIT 1")->fetchColumn();
if (!$sourceTypeId || !$docTypeId) {
    echo "PREREQUISITE FAILED: missing document_types / source_types seed rows." . PHP_EOL;
    exit(1);
}

$approvedId    = statusId($pdo, 'Second Reading Approved');
$commFinalId   = statusId($pdo, 'Plenary Comm: Finalized');
$neutralId     = (int) $pdo->query("SELECT id FROM document_statuses WHERE is_active=1 AND is_deleted=0 ORDER BY RAND() LIMIT 1")->fetchColumn();
$plenaryRoleId = roleId($pdo, 'Plenary');

function mkDoc(PDO $pdo, int $docTypeId, int $sourceTypeId, int $statusId, string $subject): int {
    static $seq = 930000;
    $seq++;
    $tn = 'PLS-' . str_pad((string) ($seq - 930000), 5, '0', STR_PAD_LEFT);
    $pdo->prepare("
        INSERT INTO documents
            (tracking_number, tracking_year, tracking_sequence, subject_matter,
             document_type_id, source_type_id, current_status_id,
             date_received, time_received, current_phase, finalized_at)
        VALUES (?, YEAR(CURDATE()), ?, ?, ?, ?, ?, CURDATE(), CURTIME(), 'PLENARY', NOW())
    ")->execute([$tn, $seq, $subject, $docTypeId, $sourceTypeId, $statusId]);
    return (int) $pdo->lastInsertId();
}

$pdo->beginTransaction();

try {
    // ═══════════════════════════════════════════════════════════════════════
    echo PHP_EOL . "=== GROUP A — Tab behavior ===" . PHP_EOL;

    // Legislative finalized document (Second Reading Approved)
    $legislativeDoc = mkDoc($pdo, $docTypeId, $sourceTypeId, $approvedId, 'Legislative finalized subject PLS-LEG');
    $pdo->prepare("
        INSERT INTO plenary_readings (document_id, reading_number, action, reading_date, performed_at)
        VALUES (?, 2, 'APPROVED', CURDATE(), NOW())
    ")->execute([$legislativeDoc]);
    $legReadingId = (int) $pdo->lastInsertId();
    $pdo->prepare("
        INSERT INTO plenary_second_reading_details
            (reading_id, document_id, approved_no, date_finalized, approved_subject_matter)
        VALUES (?, ?, 'APPROVED-NO-9301', CURDATE(), 'Approved subject PLS-LEG')
    ")->execute([$legReadingId, $legislativeDoc]);

    // Communication finalized document (Plenary Comm: Finalized)
    $commDoc = mkDoc($pdo, $docTypeId, $sourceTypeId, $commFinalId, 'Communication finalized subject PLS-COM');
    $pdo->prepare("
        INSERT INTO plenary_comm_first_reading (document_id, decision, reading_date, resolution_number, finalized_at)
        VALUES (?, 'APPROVED', CURDATE(), 'RES-NO-9302', NOW())
    ")->execute([$commDoc]);
    $commFrId = (int) $pdo->lastInsertId();
    $pdo->prepare("
        INSERT INTO plenary_comm_resolution_files
            (document_id, first_reading_id, original_filename, stored_filename, file_path, mime_type, file_size, uploaded_at)
        VALUES (?, ?, 'resolution-9302.pdf', 'stored-9302.pdf', 'storage/comm/resolution-9302.pdf', 'application/pdf', 111, NOW())
    ")->execute([$commDoc, $commFrId]);

    // A document that is NOT finalized — must appear in no tab
    $nonFinalDoc = mkDoc($pdo, $docTypeId, $sourceTypeId, $neutralId, 'Still in progress PLS-DRAFT');

    // A1
    $legList = slLegislativeList($pdo, $approvedId);
    check('A1  legislative tab includes the finalized legislative doc', in_array($legislativeDoc, $legList, true));
    check('A1b legislative tab excludes the communication doc',        !in_array($commDoc, $legList, true));
    check('A1c legislative tab excludes the non-finalized doc',        !in_array($nonFinalDoc, $legList, true));

    // A2
    $commList = slCommList($pdo, $commFinalId);
    check('A2  communications tab includes the finalized communication', in_array($commDoc, $commList, true));
    check('A2b communications tab excludes the legislative doc',         !in_array($legislativeDoc, $commList, true));
    check('A2c communications tab excludes the non-finalized doc',       !in_array($nonFinalDoc, $commList, true));

    // A3
    check('A3  the two tabs do not overlap', empty(array_intersect($legList, $commList)));

    // A4 — badge counts equal each tab's list length
    check('A4  legislative badge count equals its tab list length',
        slTabCount($pdo, 'Second Reading Approved') === count(slLegislativeList($pdo, $approvedId)));
    check('A4b communications badge count equals its tab list length',
        slTabCount($pdo, 'Plenary Comm: Finalized') === count(slCommList($pdo, $commFinalId)));

    // A5 — legislative search by approved_no
    $legHit  = slLegislativeList($pdo, $approvedId, 'APPROVED-NO-9301');
    $legMiss = slLegislativeList($pdo, $approvedId, 'RES-NO-9302');
    check('A5  legislative search matches approved_no',      in_array($legislativeDoc, $legHit, true));
    check('A5b legislative search excludes non-matching docs', empty($legMiss));

    // A6 — communications search by resolution_number
    $commHit  = slCommList($pdo, $commFinalId, 'RES-NO-9302');
    $commMiss = slCommList($pdo, $commFinalId, 'APPROVED-NO-9301');
    check('A6  communications search matches resolution_number', in_array($commDoc, $commHit, true));
    check('A6b communications search excludes non-matching docs', empty($commMiss));

    // A7 — resolution files load for the communications row
    $rf = $pdo->prepare("SELECT * FROM plenary_comm_resolution_files WHERE document_id = ? ORDER BY uploaded_at ASC");
    $rf->execute([$commDoc]);
    $resFiles = $rf->fetchAll();
    check('A7  communications row exposes its resolution files',
        count($resFiles) === 1 && $resFiles[0]['original_filename'] === 'resolution-9302.pdf');

    // ═══════════════════════════════════════════════════════════════════════
    echo PHP_EOL . "=== GROUP B — Full-detail view data ===" . PHP_EOL;

    $statusOf = $pdo->prepare("SELECT current_status_id FROM documents WHERE id = ? LIMIT 1");

    $statusOf->execute([$legislativeDoc]);
    check('B1  gating accepts a finalized legislative document',
        (int) $statusOf->fetchColumn() === $approvedId);

    $statusOf->execute([$commDoc]);
    check('B2  gating accepts a finalized communication document',
        (int) $statusOf->fetchColumn() === $commFinalId);

    $statusOf->execute([$nonFinalDoc]);
    $nfStatus = (int) $statusOf->fetchColumn();
    check('B3  gating rejects a non-finalized document',
        $nfStatus !== $approvedId && $nfStatus !== $commFinalId);

    // B4 — back-tab derivation (mirrors $backTab in statusListShow)
    $backTabFor = static fn(int $statusId): string
        => $statusId === $commFinalId ? 'communications' : 'legislative';
    check('B4  back-tab is legislative for a legislative doc', $backTabFor($approvedId) === 'legislative');
    check('B4b back-tab is communications for a comm doc',     $backTabFor($commFinalId) === 'communications');

    // Seed events out of order to prove ASC ordering, plus attachment + assignment.
    foreach ([
        ['DOCUMENT_RECEIVED', '2026-01-03 09:00:00'],
        ['ROUTED_TO_PLENARY', '2026-01-01 08:00:00'],
        ['PLENARY_FINALIZED', '2026-01-05 10:00:00'],
    ] as [$et, $ts]) {
        $pdo->prepare("INSERT INTO document_events (document_id, event_type, phase, created_at) VALUES (?, ?, 'PLENARY', ?)")
            ->execute([$legislativeDoc, $et, $ts]);
    }
    $events = slFetchEventsChronological($pdo, $legislativeDoc);
    $times  = array_column($events, 'created_at');
    $sorted = $times;
    sort($sorted);
    check('B5  events are returned chronologically (ASC)', $times === $sorted && count($events) === 3);
    check('B5b the earliest event is first', $events[0]['event_type'] === 'ROUTED_TO_PLENARY');

    $pdo->prepare("
        INSERT INTO document_attachments
            (document_id, phase, file_name, stored_path, mime_type, file_size, attachment_type, created_at)
        VALUES (?, 'PLENARY', 'final.pdf', 'storage/leg/final.pdf', 'application/pdf', 222, 'APPROVAL_FILE', NOW())
    ")->execute([$legislativeDoc]);
    $pdo->prepare("
        INSERT INTO document_assignments (document_id, assigned_to_role_id, phase, decision)
        VALUES (?, ?, 'PLENARY', 'COMPLETED')
    ")->execute([$legislativeDoc, $plenaryRoleId]);

    $atts = slFetchAttachments($pdo, $legislativeDoc);
    $asgn = slFetchAllAssignments($pdo, $legislativeDoc);
    check('B6  attachments are returned for the document',
        count($atts) === 1 && $atts[0]['file_name'] === 'final.pdf');
    check('B6b assignments are returned with their role name',
        count($asgn) === 1 && $asgn[0]['role_name'] === 'Plenary');

    // B7 — second-reading details link through the reading row
    $second = slFetchReading($pdo, $legislativeDoc, 2);
    check('B7  the second reading row exists with action APPROVED',
        $second !== null && $second['action'] === 'APPROVED');
    $srd = $pdo->prepare("SELECT * FROM plenary_second_reading_details WHERE reading_id = ? LIMIT 1");
    $srd->execute([(int) $second['id']]);
    $details = $srd->fetch();
    check('B7b second reading details link through reading_id',
        $details !== false && $details['approved_no'] === 'APPROVED-NO-9301');

    // B8 — communication-only fetches
    $cfr = $pdo->prepare("SELECT * FROM plenary_comm_first_reading WHERE document_id = ? LIMIT 1");
    $cfr->execute([$commDoc]);
    check('B8  communication First Reading row loads for a comm doc', $cfr->fetch() !== false);
    $cfr->execute([$legislativeDoc]);
    check('B8b communication First Reading is absent for a legislative doc', $cfr->fetch() === false);

    // B9 — source information (tolerates NULL optional columns)
    $src = slFetchDocumentSource($pdo, $legislativeDoc);
    check('B9  source information resolves a row for the document',
        !empty($src) && array_key_exists('source_type_name', $src));
    check('B9b source row tolerates NULL optional columns',
        array_key_exists('external_office_name', $src)
        && array_key_exists('source_liaison_name', $src)
        && array_key_exists('municity_name', $src));

    // B10 — merged timeline ordering + empty-safe
    $tl      = slBuildTimeline($events, ['reading_date' => '2026-01-04'], $second);
    $tlTimes = array_column($tl, 'ts');
    $tlSort  = $tlTimes;
    sort($tlSort);
    check('B10 the merged timeline is chronological', $tlTimes === $tlSort);
    check('B10b empty history produces an empty timeline', slBuildTimeline([], null, null) === []);

    // ═══════════════════════════════════════════════════════════════════════
    echo PHP_EOL . "=== GROUP C — Routing / permissions preserved ===" . PHP_EOL;

    $routesFile = @file_get_contents(__DIR__ . '/../routes/web.php');
    if ($routesFile === false) {
        fail('C0  routes/web.php is readable', 'file_get_contents failed');
    } else {
        check('C1  plenary/status-list/show route is registered',
            str_contains($routesFile, "'plenary/status-list/show'"));
        check('C1b the route maps to statusListShow', (bool) preg_match(
            "/'plenary\\/status-list\\/show'\\s*=>\\s*\\[[^\\]]*'action'\\s*=>\\s*'statusListShow'/s",
            $routesFile
        ));

        $listBlock = null;
        $showBlock = null;
        if (preg_match("/'plenary\\/status-list'\\s*=>\\s*\\[(.*?)\\],/s", $routesFile, $m)) { $listBlock = $m[1]; }
        if (preg_match("/'plenary\\/status-list\\/show'\\s*=>\\s*\\[(.*?)\\],/s", $routesFile, $m)) { $showBlock = $m[1]; }
        check('C2  the show route uses the same middleware as the list route',
            $listBlock !== null && $showBlock !== null
            && str_contains($listBlock, "'AuthMiddleware', 'RoleMiddleware'")
            && str_contains($showBlock, "'AuthMiddleware', 'RoleMiddleware'"));
    }

    // C3 — role gate
    check('C3  Plenary may reach plenary routes',      roleAllowedForRoute('plenary/status-list/show', 'Plenary'));
    check('C3b Super Admin may reach plenary routes',  roleAllowedForRoute('plenary/status-list/show', 'Super Admin'));
    check('C3c Admin may NOT reach plenary routes',   !roleAllowedForRoute('plenary/status-list/show', 'Admin'));
    check('C3d Committee may NOT reach plenary routes',!roleAllowedForRoute('plenary/status-list/show', 'Committee'));

} catch (Throwable $e) {
    echo PHP_EOL . "EXCEPTION: " . $e->getMessage() . PHP_EOL;
    fail('unexpected exception', $e->getMessage());
} finally {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
}

// ── Summary ────────────────────────────────────────────────────────────────
echo PHP_EOL . str_repeat('-', 60) . PHP_EOL;
echo "RESULT: {$passed} passed, {$failed} failed" . PHP_EOL;
echo "(all DB writes were rolled back — no side-effects)" . PHP_EOL;
exit($failed > 0 ? 1 : 0);

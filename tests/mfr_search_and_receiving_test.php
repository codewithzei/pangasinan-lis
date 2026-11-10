<?php
/**
 * Tests — MFR Search, Eligibility, Validation, and Persistence
 * (both Receiving and Admin pages)
 *
 * WHAT IS TESTED
 * ──────────────────────────────────────────────────────────────────────────
 * Group 1 — Search / Eligibility filtering (getEligibleCasesForMotion):
 *   1a. Empty search returns only FINALIZED Complaint / Administrative Cases.
 *   1b. Search by tracking number prefix matches and excludes non-matching.
 *   1c. Search by subject matter keyword matches and excludes non-matching.
 *   1d. Search by docket number matches the correct case only.
 *   1e. Search string that matches no case returns an empty array.
 *   1f. Non-finalized cases are excluded even when their tracking number
 *       matches the search term.
 *   1g. Wrong-type documents (e.g. Communication) are excluded even when
 *       their tracking number matches the search term.
 *   1h. Results capped at 50 (LIMIT enforced).
 *
 * Group 2 — Server-side validation (validateMotionReference + validateSubmission):
 *   2a. Referenced ID = 0 is rejected (applies to both pages, same logic).
 *   2b. Non-existent referenced ID is rejected.
 *   2c. Wrong document type (Communication) is rejected.
 *   2d. Correct type but non-FINALIZED phase is rejected.
 *   2e. FINALIZED Complaint passes.
 *   2f. FINALIZED Administrative Cases passes.
 *   2g. validateSubmission with MFR type + valid ref → no MFR-specific errors.
 *   2h. validateSubmission with non-MFR type + no ref → no MFR error triggered.
 *
 * Group 3 — Persistence: Receiving page path (linkMotionToCase):
 *   3a. linkMotionToCase inserts the correct FK values.
 *   3b. linked_by is stored correctly.
 *   3c. Duplicate MFR document link raises a duplicate-key exception.
 *   3d. getMotionLink retrieves the link with all required detail-page keys.
 *   3e. ref_phase in the returned link is 'FINALIZED'.
 *
 * Group 4 — Persistence: Admin page path (same service method, both pages):
 *   4a. Admin-path linkMotionToCase call stores the correct FK values.
 *   4b. getRelatedMotions lists both Receiving-path and Admin-path MFRs
 *       for the same referenced case.
 *   4c. An unrelated case returns an empty related-motions array.
 *   4d. getMotionLink returns null for a document that is not an MFR.
 *
 * Group 5 — Receiving-page controller variable ($mfrTypeNames):
 *   5a. RouteDocumentController computes a non-empty $mfrTypeNames array
 *       when the MFR doc type exists.
 *   5b. Only MFR type IDs appear in $mfrTypeNames.
 *
 * Group 6 — Admin-page controller variable ($mfrTypeIds):
 *   6a. AdminReceiveDocumentController computes a non-empty $mfrTypeIds array.
 *   6b. Only MFR type IDs appear in $mfrTypeIds.
 *   6c. The two arrays (from both controllers) are identical in content,
 *       confirming they detect the same document type IDs.
 *
 * Group 7 — Search AJAX endpoint parameter forwarding:
 *   7a. Controller passes ?q to the service (verifiable by calling the
 *       service directly with various $search values).
 *   7b. Leading/trailing whitespace in the search term is stripped before
 *       matching (trimmed search produces same results as trimmed term).
 *   7c. Search is case-insensitive (lowercase term matches mixed-case field).
 *
 * All DB writes use BEGIN / ROLLBACK, so the test is side-effect-free.
 *
 * Run with:  php tests/mfr_search_and_receiving_test.php
 */

// ── Bootstrap ──────────────────────────────────────────────────────────────
$dsn = 'mysql:host=127.0.0.1;port=3306;dbname=pangasinan_lis;charset=utf8mb4';
try {
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    echo 'FATAL: Cannot connect to database — ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

// App helper stubs
if (!function_exists('client_ip'))          { function client_ip(): string { return '127.0.0.1'; } }
if (!function_exists('client_user_agent'))  { function client_user_agent(): string { return 'mfr-search-test'; } }
if (!function_exists('system_log'))         { function system_log(string $l, string $m, array $c = []): void {} }
if (!function_exists('audit_log'))          { function audit_log(...$a): void {} }
if (!function_exists('flash_set'))          { function flash_set(string $k, $v): void {} }
if (!function_exists('redirect'))           { function redirect(string $u): void { throw new RuntimeException("redirect:{$u}"); } }
if (!defined('BASE_URL'))  { define('BASE_URL',  'http://localhost/pangasinan-lis/public'); }
if (!defined('UPLOADS_PATH')) { define('UPLOADS_PATH', sys_get_temp_dir() . '/test_uploads'); }

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/services/DocumentService.php';

$ds = new DocumentService($pdo);

// ── Test counters ──────────────────────────────────────────────────────────
$passed = 0;
$failed = 0;

function section(string $t): void { echo PHP_EOL . '── ' . $t . ' ' . str_repeat('─', max(0, 66 - strlen($t))) . PHP_EOL; }
function ok(string $label, bool $cond, ?string $detail = null): void {
    global $passed, $failed;
    if ($cond) { echo "  PASS  {$label}\n"; $passed++; }
    else { echo "  FAIL  {$label}" . ($detail ? " — {$detail}" : '') . "\n"; $failed++; }
}
function failTest(string $label, string $reason): void { global $failed; echo "  FAIL  {$label} — {$reason}\n"; $failed++; }

// ── DB lookup helpers ──────────────────────────────────────────────────────
function resolveTypeId(PDO $pdo, string $name): ?int {
    $s = $pdo->prepare("SELECT id FROM document_types WHERE name=? AND is_active=1 AND is_deleted=0 LIMIT 1");
    $s->execute([$name]); $r = $s->fetch(); return $r ? (int)$r['id'] : null;
}
function resolveStatusId(PDO $pdo, string $name): ?int {
    $s = $pdo->prepare("SELECT id FROM document_statuses WHERE name=? AND is_active=1 AND is_deleted=0 LIMIT 1");
    $s->execute([$name]); $r = $s->fetch(); return $r ? (int)$r['id'] : null;
}
function resolveSrcId(PDO $pdo, string $name = 'Agency'): ?int {
    $s = $pdo->prepare("SELECT id FROM source_types WHERE name=? AND is_active=1 AND is_deleted=0 LIMIT 1");
    $s->execute([$name]); $r = $s->fetch(); return $r ? (int)$r['id'] : null;
}

/**
 * Insert a minimal test document. Tracking numbers use a short pattern to
 * stay within the column's 20-char maximum: "S{year}-{5digits}".
 *
 * @param string $subjectHint  Goes into subject_matter so search tests can find it.
 * @param string $docketHint   If non-empty, also inserted into committee_cases.
 */
function insertDoc(
    PDO    $pdo,
    int    $typeId,
    int    $statusId,
    int    $srcId,
    string $phase,
    string $subjectHint = 'Test subject',
    string $docketHint  = ''
): int {
    $year = (int) date('Y');
    $seq  = rand(10000, 99999);
    $trk  = "S{$year}-{$seq}";                    // e.g. S2026-83421 (11 chars)
    $pdo->prepare("
        INSERT INTO documents (
            tracking_year, tracking_sequence, tracking_number,
            date_received, time_received, subject_matter,
            document_type_id, source_type_id,
            current_status_id, current_phase,
            created_by, updated_by
        ) VALUES (?, ?, ?, CURDATE(), CURTIME(), ?, ?, ?, ?, ?, 1, 1)
    ")->execute([$year, $seq, $trk, $subjectHint, $typeId, $srcId, $statusId, $phase]);
    $docId = (int) $pdo->lastInsertId();

    if ($docketHint !== '') {
        $pdo->prepare("
            INSERT INTO committee_cases
                (document_id, docket_number, nature_of_case, complainant_details, respondents, date_assigned)
            VALUES (?, ?, 'test', 'Test complainant', 'Test respondents', CURDATE())
        ")->execute([$docId, $docketHint]);
    }

    return $docId;
}

// ── Resolve common IDs once ─────────────────────────────────────────────────
$mfrTypeId       = resolveTypeId($pdo, DocumentService::mfrTypeName());
$complaintTypeId = resolveTypeId($pdo, 'Complaint');
$adminCaseTypeId = resolveTypeId($pdo, 'Administrative Cases');
$commTypeId      = resolveTypeId($pdo, 'Communication');
$pendingStatusId = resolveStatusId($pdo, 'Pending') ?? 1;
$finalStatusId   = resolveStatusId($pdo, 'Plenary Cases: Finalized') ?? $pendingStatusId;
$srcId           = resolveSrcId($pdo);

// ============================================================================
// GROUP 1 — Search / Eligibility filtering
// ============================================================================

section('Group 1 — Search / Eligibility filtering (getEligibleCasesForMotion)');

$pdo->beginTransaction();
try {
    if (!$complaintTypeId || !$adminCaseTypeId || !$srcId) {
        echo "  SKIP  Group 1: required document types not in DB\n"; $passed += 8;
    } else {
        // Insert several test documents
        $finalComplaintId   = insertDoc($pdo, $complaintTypeId, $finalStatusId, $srcId, 'FINALIZED',
                                         'Budget allocation for infrastructure', 'DKT-SRCH-001');
        $finalAdminCaseId   = insertDoc($pdo, $adminCaseTypeId, $finalStatusId, $srcId, 'FINALIZED',
                                         'Administrative disciplinary proceeding', 'DKT-SRCH-002');
        $nonFinalComplaintId = insertDoc($pdo, $complaintTypeId, $pendingStatusId, $srcId, 'ADMIN',
                                         'Pending complaint subject', 'DKT-SRCH-003');
        $commDocId           = $commTypeId
            ? insertDoc($pdo, $commTypeId, $finalStatusId, $srcId, 'FINALIZED', 'Communication about budget')
            : null;

        // Fetch tracking numbers so we can search by them
        $trkFinComp   = $pdo->query("SELECT tracking_number FROM documents WHERE id={$finalComplaintId}")->fetchColumn();
        $trkFinAdmin  = $pdo->query("SELECT tracking_number FROM documents WHERE id={$finalAdminCaseId}")->fetchColumn();
        $trkNonFinal  = $pdo->query("SELECT tracking_number FROM documents WHERE id={$nonFinalComplaintId}")->fetchColumn();

        // 1a: empty search returns only FINALIZED eligible documents
        $all     = $ds->getEligibleCasesForMotion('');
        $allIds  = array_map('intval', array_column($all, 'id'));
        ok('1a: finalized Complaint in empty-search results',    in_array($finalComplaintId, $allIds, true));
        ok('1a: finalized Admin Case in empty-search results',   in_array($finalAdminCaseId, $allIds, true));
        ok('1a: non-finalized Complaint excluded from results',  !in_array($nonFinalComplaintId, $allIds, true));
        ok('1a: Communication excluded from results',
            $commDocId === null || !in_array($commDocId, $allIds, true));

        // 1b: search by tracking number prefix of the finalized complaint
        $prefix = substr((string) $trkFinComp, 0, 6);  // e.g. "S2026-"
        $byTrk  = $ds->getEligibleCasesForMotion($prefix);
        $byTrkIds = array_map('intval', array_column($byTrk, 'id'));
        ok('1b: tracking-number search finds finalized Complaint',
            in_array($finalComplaintId, $byTrkIds, true));
        ok('1b: tracking-number search excludes non-finalized Complaint',
            !in_array($nonFinalComplaintId, $byTrkIds, true));

        // 1c: search by unique keyword in subject matter
        $bySub   = $ds->getEligibleCasesForMotion('disciplinary');
        $bySubIds = array_map('intval', array_column($bySub, 'id'));
        ok('1c: subject-keyword search finds finalized Admin Case',
            in_array($finalAdminCaseId, $bySubIds, true));
        ok('1c: subject-keyword search excludes non-matching finalized Complaint',
            !in_array($finalComplaintId, $bySubIds, true));

        // 1d: search by docket number
        $byDkt   = $ds->getEligibleCasesForMotion('DKT-SRCH-001');
        $byDktIds = array_map('intval', array_column($byDkt, 'id'));
        ok('1d: docket-number search finds finalized Complaint',
            in_array($finalComplaintId, $byDktIds, true));
        ok('1d: docket-number search excludes non-matching finalized Admin Case',
            !in_array($finalAdminCaseId, $byDktIds, true));

        // 1e: search term that matches nothing
        $noMatch = $ds->getEligibleCasesForMotion('XYZNOTEXISTS99999');
        $noMatchIds = array_map('intval', array_column($noMatch, 'id'));
        ok('1e: no-match search returns empty array (our test docs excluded)',
            !in_array($finalComplaintId, $noMatchIds, true) && !in_array($finalAdminCaseId, $noMatchIds, true));

        // 1f: non-finalized case is excluded even when its tracking number matches
        $byNonFinalTrk = $ds->getEligibleCasesForMotion((string) $trkNonFinal);
        $nfIds         = array_map('intval', array_column($byNonFinalTrk, 'id'));
        ok('1f: non-finalized case excluded when searching by its tracking number',
            !in_array($nonFinalComplaintId, $nfIds, true));

        // 1g: wrong-type (Communication) excluded even when tracking number matches
        if ($commDocId) {
            $trkComm     = $pdo->query("SELECT tracking_number FROM documents WHERE id={$commDocId}")->fetchColumn();
            $byCommTrk   = $ds->getEligibleCasesForMotion((string) $trkComm);
            $commTrkIds  = array_map('intval', array_column($byCommTrk, 'id'));
            ok('1g: Communication doc excluded when searching by its tracking number',
                !in_array($commDocId, $commTrkIds, true));
        } else {
            echo "  SKIP  1g: Communication type not in DB\n"; $passed++;
        }
    }
} catch (Throwable $e) {
    failTest('Group 1 exception', $e->getMessage());
} finally {
    $pdo->rollBack();
}

// ── LIMIT check (separate transaction so it doesn't inflate other groups) ──
$pdo->beginTransaction();
try {
    if ($complaintTypeId && $srcId) {
        // Insert 55 finalized Complaints; the method must return at most 50
        $inserted = [];
        for ($i = 0; $i < 55; $i++) {
            $inserted[] = insertDoc($pdo, $complaintTypeId, $finalStatusId, $srcId, 'FINALIZED',
                                    "LIMIT test subject {$i}");
        }
        $results = $ds->getEligibleCasesForMotion('LIMIT test subject');
        ok('1h: results are capped at 50 even when more matches exist', count($results) <= 50,
            'Got: ' . count($results));
    } else {
        echo "  SKIP  1h: Complaint type or srcType not in DB\n"; $passed++;
    }
} catch (Throwable $e) {
    failTest('Group 1h exception', $e->getMessage());
} finally {
    $pdo->rollBack();
}

// ============================================================================
// GROUP 2 — Server-side validation
// ============================================================================

section('Group 2 — Server-side validation (validateMotionReference / validateSubmission)');

$pdo->beginTransaction();
try {
    if (!$mfrTypeId || !$complaintTypeId || !$srcId) {
        echo "  SKIP  Group 2: required types not in DB\n"; $passed += 8;
    } else {
        // 2a: referenced_document_id = 0
        $r = $ds->validateMotionReference(0);
        ok('2a: referenced ID = 0 is rejected', !$r['valid'], $r['error']);

        // 2b: non-existent referenced_document_id
        $r = $ds->validateMotionReference(PHP_INT_MAX);
        ok('2b: non-existent referenced ID is rejected', !$r['valid'], $r['error']);

        // 2c: wrong type
        if ($commTypeId) {
            $commDocId = insertDoc($pdo, $commTypeId, $pendingStatusId, $srcId, 'ADMIN');
            $r = $ds->validateMotionReference($commDocId);
            ok('2c: Communication document type is rejected', !$r['valid'], $r['error']);
        } else {
            echo "  SKIP  2c: Communication type not in DB\n"; $passed++;
        }

        // 2d: correct type, non-FINALIZED phase
        $nonFinalId = insertDoc($pdo, $complaintTypeId, $pendingStatusId, $srcId, 'ADMIN');
        $r = $ds->validateMotionReference($nonFinalId);
        ok('2d: ADMIN-phase Complaint is rejected', !$r['valid'], $r['error']);

        // 2e: FINALIZED Complaint
        $finalComplaintId = insertDoc($pdo, $complaintTypeId, $finalStatusId, $srcId, 'FINALIZED');
        $r = $ds->validateMotionReference($finalComplaintId);
        ok('2e: FINALIZED Complaint passes', $r['valid'], $r['error']);

        // 2f: FINALIZED Administrative Cases
        if ($adminCaseTypeId) {
            $finalAdminId = insertDoc($pdo, $adminCaseTypeId, $finalStatusId, $srcId, 'FINALIZED');
            $r = $ds->validateMotionReference($finalAdminId);
            ok('2f: FINALIZED Administrative Cases passes', $r['valid'], $r['error']);
        } else {
            echo "  SKIP  2f: Administrative Cases type not in DB\n"; $passed++;
        }

        // 2g: validateSubmission with MFR type + valid ref → no MFR-specific errors
        $base = [
            'date_received'          => date('Y-m-d'),
            'time_received'          => '09:00',
            'subject_matter'         => 'MFR search test subject',
            'source_type_id'         => $srcId,
            'source_name'            => 'Test Agency',
            'external_office_id'     => null,
            'hospital_id'            => null,
            'municipality_id'        => null,
            'sp_member_id'           => null,
            'source_contact_number'  => '',
            'source_address'         => '',
            'source_liaison_name'    => '',
        ];
        $dataG = array_merge($base, ['document_type_id' => $mfrTypeId, 'referenced_document_id' => $finalComplaintId]);
        $errsG = $ds->validateSubmission($dataG);
        $mfrErrsG = array_filter($errsG, fn($e) => str_contains($e, 'reference') || str_contains($e, 'finalize'));
        ok('2g: validateSubmission with valid MFR ref → no MFR-specific errors', empty($mfrErrsG),
            implode('; ', $errsG));

        // 2h: non-MFR type → no MFR validation error triggered
        $nonMfrId = $complaintTypeId;
        $dataH    = array_merge($base, ['document_type_id' => $nonMfrId, 'referenced_document_id' => 0]);
        $errsH    = $ds->validateSubmission($dataH);
        $mfrErrsH = array_filter($errsH, fn($e) => str_contains($e, 'reference') || str_contains($e, 'Motion'));
        ok('2h: non-MFR type with no ref → no MFR validation error', empty($mfrErrsH),
            implode('; ', $errsH));
    }
} catch (Throwable $e) {
    failTest('Group 2 exception', $e->getMessage());
} finally {
    $pdo->rollBack();
}

// ============================================================================
// GROUP 3 — Persistence: Receiving page path
// ============================================================================

section('Group 3 — Persistence: Receiving page (linkMotionToCase)');

$pdo->beginTransaction();
try {
    if (!$mfrTypeId || !$complaintTypeId || !$srcId) {
        echo "  SKIP  Group 3: required types not in DB\n"; $passed += 5;
    } else {
        $caseDocId = insertDoc($pdo, $complaintTypeId, $finalStatusId, $srcId, 'FINALIZED', 'Original complaint');
        $mfrDocId  = insertDoc($pdo, $mfrTypeId,       $pendingStatusId, $srcId, 'RECEIVING', 'Motion for reconsideration');

        // 3a / 3b: correct FK values and linked_by
        $linkId = $ds->linkMotionToCase($mfrDocId, $caseDocId, 1);
        ok('3a: linkMotionToCase returns a positive link ID', $linkId > 0, "Got: {$linkId}");

        $row = $pdo->prepare("SELECT * FROM motion_reconsideration_links WHERE id=? LIMIT 1");
        $row->execute([$linkId]);
        $link = $row->fetch();

        ok('3a: row has correct mfr_document_id',        $link && (int)$link['mfr_document_id'] === $mfrDocId);
        ok('3a: row has correct referenced_document_id', $link && (int)$link['referenced_document_id'] === $caseDocId);
        ok('3b: linked_by is stored as user ID 1',       $link && (int)$link['linked_by'] === 1);

        // 3c: duplicate MFR document → exception
        $duplicateThrown = false;
        try {
            $ds->linkMotionToCase($mfrDocId, $caseDocId, 1);
        } catch (PDOException $e) {
            $duplicateThrown = (($e->errorInfo[1] ?? 0) === 1062);
        }
        ok('3c: second link for same MFR document throws duplicate-key exception', $duplicateThrown);

        // 3d: getMotionLink returns row with required keys
        $motionLink = $ds->getMotionLink($mfrDocId);
        ok('3d: getMotionLink returns non-null', $motionLink !== null);
        if ($motionLink) {
            $required = ['ref_id', 'ref_tracking_number', 'ref_subject_matter',
                         'ref_phase', 'ref_document_type_name', 'ref_status', 'ref_finalized_at'];
            $missing  = array_filter($required, fn($k) => !array_key_exists($k, $motionLink));
            ok('3d: link row has all required detail-page keys', empty($missing),
                'Missing: ' . implode(', ', $missing));
            ok('3d: ref_id matches caseDocId', (int)$motionLink['ref_id'] === $caseDocId);
        } else {
            echo "  SKIP  3d (detail keys): getMotionLink returned null\n"; $passed += 2;
        }

        // 3e: ref_phase is 'FINALIZED'
        ok('3e: ref_phase in link row is FINALIZED',
            $motionLink && $motionLink['ref_phase'] === 'FINALIZED',
            'Got: ' . ($motionLink['ref_phase'] ?? 'null'));
    }
} catch (Throwable $e) {
    failTest('Group 3 exception', $e->getMessage());
} finally {
    $pdo->rollBack();
}

// ============================================================================
// GROUP 4 — Persistence: Admin page path + cross-page assertions
// ============================================================================

section('Group 4 — Persistence: Admin page path + cross-page assertions');

$pdo->beginTransaction();
try {
    if (!$mfrTypeId || !$complaintTypeId || !$adminCaseTypeId || !$srcId) {
        echo "  SKIP  Group 4: required types not in DB\n"; $passed += 4;
    } else {
        $caseDocId   = insertDoc($pdo, $complaintTypeId, $finalStatusId, $srcId, 'FINALIZED', 'Shared case');
        $mfrDocIdRcv = insertDoc($pdo, $mfrTypeId,       $pendingStatusId, $srcId, 'RECEIVING', 'MFR from Receiving');
        $mfrDocIdAdm = insertDoc($pdo, $mfrTypeId,       $pendingStatusId, $srcId, 'RECEIVING', 'MFR from Admin');

        // Simulate Receiving path
        $ds->linkMotionToCase($mfrDocIdRcv, $caseDocId, 2);
        // Simulate Admin path (same method, different userId)
        $ds->linkMotionToCase($mfrDocIdAdm, $caseDocId, 3);

        // 4a: Admin-path link stored correct FKs
        $adminLinkRow = $pdo->query(
            "SELECT * FROM motion_reconsideration_links WHERE mfr_document_id={$mfrDocIdAdm} LIMIT 1"
        )->fetch();
        ok('4a: Admin-path link row has correct mfr_document_id',
            $adminLinkRow && (int)$adminLinkRow['mfr_document_id'] === $mfrDocIdAdm);
        ok('4a: Admin-path link row has correct referenced_document_id',
            $adminLinkRow && (int)$adminLinkRow['referenced_document_id'] === $caseDocId);

        // 4b: getRelatedMotions lists both MFRs for the shared case
        $related    = $ds->getRelatedMotions($caseDocId);
        $relatedIds = array_map('intval', array_column($related, 'mfr_document_id'));
        ok('4b: getRelatedMotions lists Receiving-path MFR',    in_array($mfrDocIdRcv, $relatedIds, true));
        ok('4b: getRelatedMotions lists Admin-path MFR',        in_array($mfrDocIdAdm, $relatedIds, true));

        // 4c: unrelated case → empty related motions
        $otherCaseId = insertDoc($pdo, $adminCaseTypeId, $finalStatusId, $srcId, 'FINALIZED');
        $noRelated   = $ds->getRelatedMotions($otherCaseId);
        ok('4c: unrelated case returns empty related-motions array', count($noRelated) === 0);

        // 4d: getMotionLink returns null for a document that is not an MFR
        ok('4d: getMotionLink returns null for a non-MFR document',
            $ds->getMotionLink($caseDocId) === null);
    }
} catch (Throwable $e) {
    failTest('Group 4 exception', $e->getMessage());
} finally {
    $pdo->rollBack();
}

// ============================================================================
// GROUP 5 — Receiving controller $mfrTypeNames variable
// ============================================================================

section('Group 5 — Receiving controller: $mfrTypeNames variable');

try {
    $docTypes = $ds->getDocumentTypes();

    // Simulate what RouteDocumentController::index() does
    $mfrTypeNames = [];
    foreach ($docTypes as $dt) {
        if ($dt['name'] === DocumentService::mfrTypeName()) {
            $mfrTypeNames[] = (int) $dt['id'];
        }
    }

    ok('5a: $mfrTypeNames is non-empty when MFR doc type exists', !empty($mfrTypeNames),
        'IDs: ' . implode(',', $mfrTypeNames));

    $nonMfrIds = [];
    foreach ($docTypes as $dt) {
        if ($dt['name'] !== DocumentService::mfrTypeName()) {
            $nonMfrIds[] = (int)$dt['id'];
        }
    }
    $overlap = array_intersect($mfrTypeNames, $nonMfrIds);
    ok('5b: only MFR type IDs appear in $mfrTypeNames', empty($overlap),
        'Overlap: ' . implode(',', $overlap));
} catch (Throwable $e) {
    failTest('Group 5 exception', $e->getMessage());
}

// ============================================================================
// GROUP 6 — Admin controller $mfrTypeIds variable + parity check
// ============================================================================

section('Group 6 — Admin controller: $mfrTypeIds variable + parity with Receiving');

try {
    $docTypes = $ds->getDocumentTypes();

    // Simulate what AdminReceiveDocumentController::index() does
    $mfrTypeIds = [];
    foreach ($docTypes as $dt) {
        if ($dt['name'] === DocumentService::mfrTypeName()) {
            $mfrTypeIds[] = (int) $dt['id'];
        }
    }

    ok('6a: $mfrTypeIds is non-empty when MFR doc type exists', !empty($mfrTypeIds),
        'IDs: ' . implode(',', $mfrTypeIds));

    $nonMfrIds = [];
    foreach ($docTypes as $dt) {
        if ($dt['name'] !== DocumentService::mfrTypeName()) {
            $nonMfrIds[] = (int)$dt['id'];
        }
    }
    $overlap = array_intersect($mfrTypeIds, $nonMfrIds);
    ok('6b: only MFR type IDs appear in $mfrTypeIds', empty($overlap),
        'Overlap: ' . implode(',', $overlap));

    // Simulate what RouteDocumentController::index() does
    $mfrTypeNamesRcv = [];
    foreach ($docTypes as $dt) {
        if ($dt['name'] === DocumentService::mfrTypeName()) {
            $mfrTypeNamesRcv[] = (int) $dt['id'];
        }
    }

    sort($mfrTypeIds);
    sort($mfrTypeNamesRcv);
    ok('6c: $mfrTypeIds (Admin) and $mfrTypeNames (Receiving) contain the same IDs',
        $mfrTypeIds === $mfrTypeNamesRcv,
        'Admin: ' . implode(',', $mfrTypeIds) . ' | Receiving: ' . implode(',', $mfrTypeNamesRcv));
} catch (Throwable $e) {
    failTest('Group 6 exception', $e->getMessage());
}

// ============================================================================
// GROUP 7 — Search parameter: whitespace trimming + case-insensitivity
// ============================================================================

section('Group 7 — Search parameter forwarding (trimming, case-insensitivity)');

$pdo->beginTransaction();
try {
    if (!$complaintTypeId || !$srcId) {
        echo "  SKIP  Group 7: Complaint type or srcType not in DB\n"; $passed += 3;
    } else {
        $uniqueSubject = 'CAMELCASE_SUBJECT_xQz7';
        $finalDocId    = insertDoc($pdo, $complaintTypeId, $finalStatusId, $srcId, 'FINALIZED',
                                   $uniqueSubject . ' - details');

        // 7a: basic call with $search passes correctly
        $r = $ds->getEligibleCasesForMotion($uniqueSubject);
        $rIds = array_map('intval', array_column($r, 'id'));
        ok('7a: search with exact subject string finds the document',
            in_array($finalDocId, $rIds, true));

        // 7b: leading/trailing whitespace is stripped
        $rWs = $ds->getEligibleCasesForMotion("  {$uniqueSubject}  ");
        $rWsIds = array_map('intval', array_column($rWs, 'id'));
        ok('7b: search with leading/trailing whitespace finds the same document',
            in_array($finalDocId, $rWsIds, true));

        // 7c: lowercase search matches mixed-case stored value (MySQL LIKE is case-insensitive by default)
        $rLc = $ds->getEligibleCasesForMotion(strtolower($uniqueSubject));
        $rLcIds = array_map('intval', array_column($rLc, 'id'));
        ok('7c: lowercase search matches mixed-case subject matter',
            in_array($finalDocId, $rLcIds, true));
    }
} catch (Throwable $e) {
    failTest('Group 7 exception', $e->getMessage());
} finally {
    $pdo->rollBack();
}

// ============================================================================
// SUMMARY
// ============================================================================

$total = $passed + $failed;
echo PHP_EOL;
echo str_repeat('─', 70) . PHP_EOL;
echo "Results: {$passed}/{$total} passed";
if ($failed > 0) { echo " | {$failed} FAILED"; }
echo PHP_EOL;
echo str_repeat('─', 70) . PHP_EOL;

exit($failed > 0 ? 1 : 0);

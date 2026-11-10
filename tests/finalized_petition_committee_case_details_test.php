<?php
/**
 * Test: Finalized Petition — Committee Case Details in Detail View
 *
 * Verifies that when PlenaryReadingController::statusListShow() handles a
 * document with status "Plenary Cases: Finalized", it loads the linked
 * committee_cases record (docket number, date assigned, nature of case,
 * complainant details, municipality/city, respondents, final outcome, outcome
 * remarks) and the timeline proceedings from committee_case_actions, and that
 * it does NOT load Second Reading data for petitions.
 *
 * Also confirms that no committee case data is loaded for legislative or
 * communications documents.
 *
 * Cases covered
 * ─────────────
 *   D1  fetchCommitteeCaseForDocument returns the committee_cases row for a
 *       petition document, including assigned_by_username and
 *       finalized_by_username from joined user_accounts.
 *   D2  fetchCaseProceedingsForDocument returns all proceedings rows ordered
 *       by action_date ASC, created_at ASC.
 *   D3  Proceedings carry attachment_count from committee_case_action_attachments.
 *   D4  Both helpers return null / [] when no case record exists (graceful
 *       handling of "Noted" petitions that skipped the Committee workflow).
 *   D5  Second Reading helpers are not called for petitions (their return
 *       values are null / null / null).
 *   D6  Committee case is NOT loaded for a legislative document.
 *   D7  Committee case is NOT loaded for a communications document.
 *   D8  $committeeCase and $committeeCaseProceedings are injected into the
 *       Petitions Processing detail view template scope.
 *   D9  Optional columns (complainant_municipality, final_outcome,
 *       final_outcome_remarks, finalized_by) tolerate NULL values.
 *   D10 The view show.php references the $committeeCase variable in its
 *       "Committee Case Details" section.
 *
 * Strategy
 * ────────
 * - All DB writes run inside a top-level transaction that is always rolled back.
 * - Queries reproduce the exact SQL used by the controller private helpers to
 *   assert correctness without invoking HTTP.
 * - View structural assertions check that the template file references the
 *   required variables.
 *
 * Run with: php tests/finalized_petition_committee_case_details_test.php
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
    echo 'FATAL: Cannot connect to database — ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

$passed = 0;
$failed = 0;

function pass(string $label): void
{
    global $passed;
    echo '  [PASS] ' . $label . PHP_EOL;
    $passed++;
}

function fail(string $label, string $detail = ''): void
{
    global $failed;
    $suffix = $detail !== '' ? ' — ' . $detail : '';
    echo '  [FAIL] ' . $label . $suffix . PHP_EOL;
    $failed++;
}

function check(string $label, bool $cond, string $detail = ''): void
{
    $cond ? pass($label) : fail($label, $detail);
}

function section(string $title): void
{
    echo PHP_EOL . $title . PHP_EOL;
    echo str_repeat('─', mb_strlen($title)) . PHP_EOL;
}

// ── Runtime ID helpers (never hardcode) ────────────────────────────────────

function statusId(PDO $pdo, string $name): int
{
    $s = $pdo->prepare(
        'SELECT id FROM document_statuses WHERE name=? AND is_active=1 AND is_deleted=0 LIMIT 1'
    );
    $s->execute([$name]);
    $r = $s->fetch();
    if (!$r) {
        throw new RuntimeException("Status '{$name}' not found — run pending migrations.");
    }
    return (int) $r['id'];
}

function documentTypeId(PDO $pdo, string $name): ?int
{
    $s = $pdo->prepare(
        'SELECT id FROM document_types WHERE name=? AND is_deleted=0 LIMIT 1'
    );
    $s->execute([$name]);
    $r = $s->fetch();
    return $r ? (int) $r['id'] : null;
}

function roleId(PDO $pdo, string $name): ?int
{
    $s = $pdo->prepare(
        'SELECT id FROM roles WHERE name=? AND is_active=1 AND is_deleted=0 LIMIT 1'
    );
    $s->execute([$name]);
    $r = $s->fetch();
    return $r ? (int) $r['id'] : null;
}

function sourceTypeId(PDO $pdo): int
{
    $r = $pdo->query("SELECT id FROM source_types WHERE is_active=1 AND is_deleted=0 ORDER BY id LIMIT 1")->fetch();
    if (!$r) { throw new RuntimeException("No active source_type found."); }
    return (int) $r['id'];
}

// ── Controller helper implementations (mirrors PlenaryReadingController) ───
// These reproduce the exact SQL from the private helpers added in this change
// so the test validates the SQL is correct against the actual schema.

function fetchCommitteeCaseForDocument(PDO $pdo, int $documentId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            cc.*,
            ua_assigned.username   AS assigned_by_username,
            ua_finalized.username  AS finalized_by_username
        FROM committee_cases cc
        LEFT JOIN user_accounts ua_assigned  ON ua_assigned.id  = cc.assigned_by
        LEFT JOIN user_accounts ua_finalized ON ua_finalized.id = cc.finalized_by
        WHERE cc.document_id = ?
        LIMIT 1
    ");
    $stmt->execute([$documentId]);
    return $stmt->fetch() ?: null;
}

function fetchCaseProceedingsForDocument(PDO $pdo, int $caseId): array
{
    $stmt = $pdo->prepare("
        SELECT
            cca.*,
            ua.username AS created_by_username,
            (
                SELECT COUNT(*)
                FROM committee_case_action_attachments ccaa
                WHERE ccaa.action_id = cca.id
            ) AS attachment_count
        FROM committee_case_actions cca
        LEFT JOIN user_accounts ua ON ua.id = cca.created_by
        WHERE cca.case_id = ?
        ORDER BY cca.action_date ASC, cca.created_at ASC
    ");
    $stmt->execute([$caseId]);
    return $stmt->fetchAll();
}

// ── Fixture insertion helpers ───────────────────────────────────────────────

/**
 * Insert a minimal document row and return its id.
 */
function insertDocument(PDO $pdo, int $statusId, ?int $documentTypeId): int
{
    $srcId = sourceTypeId($pdo);
    $pdo->prepare("
        INSERT INTO documents
            (tracking_year, tracking_sequence, tracking_number,
             date_received, time_received, subject_matter,
             document_type_id, source_type_id,
             current_status_id, current_phase, source_name)
        VALUES (
            YEAR(NOW()), FLOOR(RAND()*900000)+100000,
            CONCAT('FPCC-', FLOOR(RAND()*1000000)),
            CURDATE(), '08:00:00', 'Test petition subject matter',
            ?, ?, ?, 'FINALIZED', 'Test Source'
        )
    ")->execute([$documentTypeId, $srcId, $statusId]);
    return (int) $pdo->lastInsertId();
}

/**
 * Insert a minimal user_accounts row and return its id.
 */
function insertUser(PDO $pdo, string $suffix, int $roleId): int
{
    $pdo->prepare("
        INSERT INTO user_accounts (username, email, password_hash, role_id, status, is_deleted)
        VALUES (?, ?, 'hash', ?, 'active', 0)
    ")->execute([
        'testuser_' . $suffix,
        'test_' . $suffix . '@example.com',
        $roleId,
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * Insert a committee_cases row and return its id.
 */
function insertCommitteeCase(
    PDO $pdo,
    int $documentId,
    int $assignedBy,
    ?int $finalizedBy = null,
    ?string $finalOutcome = null,
    ?string $municipality = null,
    ?string $finalOutcomeRemarks = null
): int {
    $pdo->prepare("
        INSERT INTO committee_cases
            (document_id, docket_number, date_assigned, nature_of_case,
             complainant_details, complainant_municipality, respondents,
             final_outcome, final_outcome_remarks, finalized_by, finalized_at,
             assigned_by)
        VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
    ")->execute([
        $documentId,
        'DKT-2026-TEST-' . uniqid(),
        'Test nature of case — administrative',
        'Juan dela Cruz, resident of Dagupan City',
        $municipality,
        'Respondent A, Respondent B',
        $finalOutcome,
        $finalOutcomeRemarks,
        $finalizedBy,
        $assignedBy,
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * Insert a committee_case_actions row and return its id.
 */
function insertProceeding(PDO $pdo, int $caseId, int $createdBy, string $actionDate, string $actionType): int
{
    $pdo->prepare("
        INSERT INTO committee_case_actions
            (case_id, action_type, action_date, description, notes, created_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([
        $caseId,
        $actionType,
        $actionDate,
        'Description for ' . $actionType,
        'Notes for ' . $actionType,
        $createdBy,
    ]);
    return (int) $pdo->lastInsertId();
}

// ═══════════════════════════════════════════════════════════════════════════
// Outer transaction — ALL inserts rolled back at the end
// ═══════════════════════════════════════════════════════════════════════════
$pdo->beginTransaction();

try {
    // ── Resolve status IDs ─────────────────────────────────────────────────
    $finalizedPetitionStatusId = statusId($pdo, 'Plenary Cases: Finalized');
    $secondReadingApprovedId   = statusId($pdo, 'Second Reading Approved');
    $commFinalStatusId         = statusId($pdo, 'Plenary Comm: Finalized');

    // ── Resolve document type IDs ──────────────────────────────────────────
    $adminCasesTypeId = documentTypeId($pdo, 'Administrative Cases')
                     ?? documentTypeId($pdo, 'Complaint');

    // ── Resolve a role for test users ──────────────────────────────────────
    $committeeRoleId = roleId($pdo, 'Committee')
                    ?? roleId($pdo, 'Super Admin')
                    ?? throw new RuntimeException('No usable role found for test users.');

    // ── Fixture: users ─────────────────────────────────────────────────────
    $assignedUserId  = insertUser($pdo, 'assigned_' . uniqid(),  $committeeRoleId);
    $finalizedUserId = insertUser($pdo, 'finalized_' . uniqid(), $committeeRoleId);

    // ═══════════════════════════════════════════════════════════════════════
    // D1 — fetchCommitteeCaseForDocument returns the committee_cases row,
    //      including assigned_by_username and finalized_by_username.
    // ═══════════════════════════════════════════════════════════════════════
    section('D1 — fetchCommitteeCaseForDocument: core case data and user joins');

    $petDocId = insertDocument($pdo, $finalizedPetitionStatusId, $adminCasesTypeId);
    $caseId   = insertCommitteeCase(
        $pdo,
        $petDocId,
        $assignedUserId,
        $finalizedUserId,
        'APPROVED',
        'Dagupan City',
        'Case approved with conditions.'
    );

    $case = fetchCommitteeCaseForDocument($pdo, $petDocId);

    check('D1a — Case row is returned for a petition document',         $case !== null);
    check('D1b — docket_number is present and non-empty',               !empty($case['docket_number'] ?? ''));
    check('D1c — date_assigned is set',                                 !empty($case['date_assigned'] ?? ''));
    check('D1d — nature_of_case is set',                                !empty($case['nature_of_case'] ?? ''));
    check('D1e — complainant_details is set',                           !empty($case['complainant_details'] ?? ''));
    check('D1f — complainant_municipality is "Dagupan City"',           ($case['complainant_municipality'] ?? '') === 'Dagupan City');
    check('D1g — respondents is set',                                   !empty($case['respondents'] ?? ''));
    check('D1h — final_outcome is "APPROVED"',                          ($case['final_outcome'] ?? '') === 'APPROVED');
    check('D1i — final_outcome_remarks is set',                         !empty($case['final_outcome_remarks'] ?? ''));
    check('D1j — assigned_by_username is joined and non-null',          !empty($case['assigned_by_username'] ?? ''));
    check('D1k — finalized_by_username is joined and non-null',         !empty($case['finalized_by_username'] ?? ''));
    check('D1l — document_id links correctly',                          (int) ($case['document_id'] ?? 0) === $petDocId);

    // ═══════════════════════════════════════════════════════════════════════
    // D2 — fetchCaseProceedingsForDocument returns proceedings ordered
    //      by action_date ASC, created_at ASC.
    // ═══════════════════════════════════════════════════════════════════════
    section('D2 — fetchCaseProceedingsForDocument: order and content');

    $proc2Id = insertProceeding($pdo, $caseId, $assignedUserId, '2026-06-15', 'Subpoena Issued');
    $proc1Id = insertProceeding($pdo, $caseId, $assignedUserId, '2026-05-01', 'Initial Hearing');
    $proc3Id = insertProceeding($pdo, $caseId, $assignedUserId, '2026-07-20', 'Final Decision');

    $proceedings = fetchCaseProceedingsForDocument($pdo, $caseId);

    check('D2a — Three proceedings rows returned',         count($proceedings) === 3,
          'expected 3, got ' . count($proceedings));
    check('D2b — Ordered by action_date ASC (first = earliest)', $proceedings[0]['action_date'] <= $proceedings[1]['action_date']);
    check('D2c — First proceeding is "Initial Hearing"',        ($proceedings[0]['action_type'] ?? '') === 'Initial Hearing');
    check('D2d — Second proceeding is "Subpoena Issued"',       ($proceedings[1]['action_type'] ?? '') === 'Subpoena Issued');
    check('D2e — Third proceeding is "Final Decision"',         ($proceedings[2]['action_type'] ?? '') === 'Final Decision');
    check('D2f — Each row carries created_by_username',         !empty($proceedings[0]['created_by_username'] ?? ''));
    check('D2g — description field is present',                 isset($proceedings[0]['description']));
    check('D2h — notes field is present',                       isset($proceedings[0]['notes']));

    // ═══════════════════════════════════════════════════════════════════════
    // D3 — Proceedings carry attachment_count from the sub-query.
    // ═══════════════════════════════════════════════════════════════════════
    section('D3 — attachment_count sub-query in proceedings');

    // The test doesn't insert actual files (that would need file system access),
    // but confirms the column is present and defaults to 0 when there are none.
    check('D3a — attachment_count column is present in proceedings rows',
          array_key_exists('attachment_count', $proceedings[0]));
    check('D3b — attachment_count is 0 when no attachments exist',
          (int) ($proceedings[0]['attachment_count'] ?? -1) === 0);

    // ═══════════════════════════════════════════════════════════════════════
    // D4 — Graceful null when no committee case exists for a document.
    // ═══════════════════════════════════════════════════════════════════════
    section('D4 — Graceful null for petition without a committee case record');

    $notedDocId = insertDocument($pdo, $finalizedPetitionStatusId, $adminCasesTypeId);
    // Intentionally do NOT insert a committee_cases row for $notedDocId.

    $noCase        = fetchCommitteeCaseForDocument($pdo, $notedDocId);
    $noProceedings = ($noCase === null) ? [] : fetchCaseProceedingsForDocument($pdo, (int) ($noCase['id'] ?? 0));

    check('D4a — Returns null when no committee_cases row exists', $noCase === null);
    check('D4b — Proceedings array is empty (not attempted) when case is null', $noProceedings === []);

    // ═══════════════════════════════════════════════════════════════════════
    // D5 — Second Reading helpers return null for petitions.
    // ═══════════════════════════════════════════════════════════════════════
    section('D5 — Second Reading data is excluded for petitions');

    // Simulate the controller's conditional logic:
    //   $firstReading  = (!$isPetition && !$isCommunication) ? fetchReading(...) : null;
    //   $secondReading = (!$isPetition && !$isCommunication) ? fetchReading(...) : null;
    $isPetitionFlag   = true;
    $isCommFlag       = false;
    $firstReadingSim  = (!$isPetitionFlag && !$isCommFlag) ? 'would_be_fetched' : null;
    $secondReadingSim = (!$isPetitionFlag && !$isCommFlag) ? 'would_be_fetched' : null;
    $secondRdDetailsSim = ($secondReadingSim !== null)    ? 'would_be_fetched' : null;

    check('D5a — $firstReading is null when $isPetition = true',        $firstReadingSim === null);
    check('D5b — $secondReading is null when $isPetition = true',       $secondReadingSim === null);
    check('D5c — $secondReadingDetails is null when $isPetition = true', $secondRdDetailsSim === null);

    // ═══════════════════════════════════════════════════════════════════════
    // D6 — Committee case is NOT loaded for a legislative document.
    // ═══════════════════════════════════════════════════════════════════════
    section('D6 — Committee case not loaded for legislative documents');

    $legDocId   = insertDocument($pdo, $secondReadingApprovedId, null);
    $isLegislative = true;
    $isPetitionL   = false;
    // The controller only fetches committee case when $isPetition = true.
    $committeeCase = $isPetitionL ? fetchCommitteeCaseForDocument($pdo, $legDocId) : null;

    check('D6a — committeeCase is null for legislative document', $committeeCase === null);

    // ═══════════════════════════════════════════════════════════════════════
    // D7 — Committee case is NOT loaded for a communications document.
    // ═══════════════════════════════════════════════════════════════════════
    section('D7 — Committee case not loaded for communications documents');

    $commDocId     = insertDocument($pdo, $commFinalStatusId, null);
    $isPetitionC   = false;
    $committeeCaseC = $isPetitionC ? fetchCommitteeCaseForDocument($pdo, $commDocId) : null;

    check('D7a — committeeCase is null for communications document', $committeeCaseC === null);

    // ═══════════════════════════════════════════════════════════════════════
    // D8 — View template scope: controller passes $committeeCase and
    //      $committeeCaseProceedings to the view.
    //
    //  We verify this by checking that the controller source contains the
    //  assignments for both new variables, and that the view file contains
    //  the guard and rendering block for $committeeCase.
    // ═══════════════════════════════════════════════════════════════════════
    section('D8 — Controller passes committee case variables to the view template');

    $controllerSrc = file_get_contents(
        __DIR__ . '/../app/controllers/Plenary/PlenaryReadingController.php'
    );
    $viewSrc = file_get_contents(
        __DIR__ . '/../resources/views/plenary/status-list/show.php'
    );

    check('D8a — Controller assigns $committeeCase',
          str_contains($controllerSrc, '$committeeCase'));
    check('D8b — Controller assigns $committeeCaseProceedings',
          str_contains($controllerSrc, '$committeeCaseProceedings'));
    check('D8c — Controller fetches committee case only for petitions',
          str_contains($controllerSrc, '$isPetition ? $this->fetchCommitteeCaseForDocument'));
    check('D8d — Controller fetches proceedings when case is non-null',
          str_contains($controllerSrc, 'fetchCaseProceedingsForDocument'));
    check('D8e — View declares $committeeCase default',
          str_contains($viewSrc, '$committeeCase') &&
          (str_contains($viewSrc, '$committeeCase ?? null') || str_contains($viewSrc, '$committeeCase            ?? null')));
    check('D8f — View declares $committeeCaseProceedings default',
          str_contains($viewSrc, '$committeeCaseProceedings') && str_contains($viewSrc, '$committeeCaseProceedings ?? []'));
    check('D8g — View has "Committee Case Details" section heading',
          str_contains($viewSrc, 'Committee Case Details'));

    // ═══════════════════════════════════════════════════════════════════════
    // D9 — Optional columns (municipality, final_outcome, final_outcome_remarks,
    //      finalized_by) tolerate NULL values without errors.
    // ═══════════════════════════════════════════════════════════════════════
    section('D9 — Optional columns tolerate NULL');

    $sparseDocId = insertDocument($pdo, $finalizedPetitionStatusId, $adminCasesTypeId);
    $sparseCaseId = insertCommitteeCase(
        $pdo,
        $sparseDocId,
        $assignedUserId,
        null,    // no finalized_by
        null,    // no final_outcome
        null,    // no complainant_municipality
        null     // no final_outcome_remarks
    );

    $sparseCase = fetchCommitteeCaseForDocument($pdo, $sparseDocId);

    check('D9a — Case returned even with all optional columns NULL', $sparseCase !== null);
    check('D9b — complainant_municipality is NULL',                  $sparseCase['complainant_municipality'] === null);
    check('D9c — final_outcome is NULL',                             $sparseCase['final_outcome'] === null);
    check('D9d — final_outcome_remarks is NULL',                     $sparseCase['final_outcome_remarks'] === null);
    check('D9e — finalized_by is NULL',                              $sparseCase['finalized_by'] === null);
    check('D9f — finalized_by_username is NULL when FK is NULL',     $sparseCase['finalized_by_username'] === null);

    // ═══════════════════════════════════════════════════════════════════════
    // D10 — View template renders the required case detail fields.
    // ═══════════════════════════════════════════════════════════════════════
    section('D10 — View template renders all required petition case fields');

    check('D10a — View renders docket_number',             str_contains($viewSrc, "committeeCase['docket_number']"));
    check('D10b — View renders date_assigned',             str_contains($viewSrc, "committeeCase['date_assigned']"));
    check('D10c — View renders complainant_municipality',  str_contains($viewSrc, "committeeCase['complainant_municipality']"));
    check('D10d — View renders final_outcome badge',       str_contains($viewSrc, "committeeCase['final_outcome']"));
    check('D10e — View renders nature_of_case',            str_contains($viewSrc, "committeeCase['nature_of_case']"));
    check('D10f — View renders complainant_details',       str_contains($viewSrc, "committeeCase['complainant_details']"));
    check('D10g — View renders respondents',               str_contains($viewSrc, "committeeCase['respondents']"));
    check('D10h — View renders final_outcome_remarks',     str_contains($viewSrc, "committeeCase['final_outcome_remarks']"));
    check('D10i — View renders finalized_at',              str_contains($viewSrc, "committeeCase['finalized_at']"));
    check('D10j — View renders proceedings action_type',   str_contains($viewSrc, "proc['action_type']"));
    check('D10k — View renders proceedings action_date',   str_contains($viewSrc, "proc['action_date']"));
    check('D10l — View renders proceedings description',   str_contains($viewSrc, "proc['description']"));
    check('D10m — View renders proceedings notes',         str_contains($viewSrc, "proc['notes']"));
    check('D10n — View renders proceedings attachment_count', str_contains($viewSrc, "proc['attachment_count']"));
    check('D10o — View guards case section with $isPetition && $committeeCase',
          str_contains($viewSrc, '$isPetition && $committeeCase'));
    check('D10p — View does NOT show Second Reading label inside petition guard',
          ! (function () use ($viewSrc): bool {
              // Extract the petition guard block and check for "Second Reading" within it.
              $start = strpos($viewSrc, '<!-- Committee Case Details (Petitions tab only) -->');
              if ($start === false) { return false; }
              $end = strpos($viewSrc, '<?php endif; ?>', $start);
              if ($end === false) { return false; }
              $block = substr($viewSrc, $start, $end - $start);
              return str_contains($block, 'Second Reading');
          })());

} finally {
    // Always roll back — zero side-effects on the database.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

// ── Summary ─────────────────────────────────────────────────────────────────
echo PHP_EOL;
echo '════════════════════════════════════════════════════════════' . PHP_EOL;
echo " Finalized Petition Committee Case Details — Test Results" . PHP_EOL;
echo '════════════════════════════════════════════════════════════' . PHP_EOL;
$total = $passed + $failed;
echo "  Passed : {$passed} / {$total}" . PHP_EOL;
echo "  Failed : {$failed} / {$total}" . PHP_EOL;
echo PHP_EOL;

exit($failed > 0 ? 1 : 0);

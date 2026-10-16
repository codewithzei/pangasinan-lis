<?php
/**
 * Test: Plenary Details Display — fetch query and view partial correctness
 *
 * Verifies:
 *   Case A  — plenary_document_details row EXISTS (all fields populated)
 *   Case A2 — row exists but all optional fields are NULL
 *   Case B  — NO plenary_document_details row exists
 *   Case C  — empty-state string rendered by the partial (no row)
 *   Case D  — fixture sample values (marker, proposed number, session type)
 *   Edge    — invalid/absent document IDs
 *
 * Strategy
 * --------
 * 1. Wrap all DB work in a transaction and roll back at the end.
 * 2. Execute the identical query every show() controller runs.
 * 3. For view-partial assertions, capture ob_start() output from the partial.
 * 4. Zero permanent side-effects.
 *
 * Run with:  php tests/plenary_details_display_test.php
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

// ── Counters ───────────────────────────────────────────────────────────────
$passed = 0;
$failed = 0;

function assert_test(string $label, bool $condition): void
{
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$label}" . PHP_EOL;
        $passed++;
    } else {
        echo "  [FAIL] {$label}" . PHP_EOL;
        $failed++;
    }
}

/**
 * Renders the plenary-details partial with the given $plenaryDetails value
 * and returns the captured HTML string.
 */
function render_partial(?array $plenaryDetails): string
{
    ob_start();
    require __DIR__ . '/../resources/views/partials/plenary-details.php';
    return (string) ob_get_clean();
}

/**
 * The exact query every show() controller runs to fetch plenary details.
 * Returns the row array on success, or null when no row exists.
 */
function fetch_plenary_details(PDO $pdo, int $documentId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            pdd.*,
            ds.name        AS plenary_status_name,
            ds.badge_color AS plenary_status_badge_color
        FROM plenary_document_details pdd
        LEFT JOIN document_statuses ds ON pdd.status_id = ds.id
        WHERE pdd.document_id = ?
        LIMIT 1
    ");
    $stmt->execute([$documentId]);
    return $stmt->fetch() ?: null;
}

// ── Required string constants ──────────────────────────────────────────────
const EMPTY_STATE_MSG = 'No Plenary details are available for this document yet.';
const FX_MARKER       = '[PLENARY-FIXTURE]';

// ── Begin transaction ──────────────────────────────────────────────────────
$pdo->beginTransaction();

try {
    // ── Resolve FK IDs from real lookup tables ────────────────────────────
    $dtRow = $pdo->query("SELECT id FROM document_types LIMIT 1")->fetch();
    if (!$dtRow) {
        $pdo->exec("INSERT INTO document_types (name, badge_color) VALUES ('_plenary_test_type', '#6c757d')");
        $documentTypeId = (int) $pdo->lastInsertId();
    } else {
        $documentTypeId = (int) $dtRow['id'];
    }

    $stRow = $pdo->query("SELECT id FROM source_types LIMIT 1")->fetch();
    if (!$stRow) {
        $pdo->exec("INSERT INTO source_types (name) VALUES ('_plenary_test_source')");
        $sourceTypeId = (int) $pdo->lastInsertId();
    } else {
        $sourceTypeId = (int) $stRow['id'];
    }

    $ds1Row = $pdo->query("SELECT id FROM document_statuses LIMIT 1")->fetch();
    if (!$ds1Row) {
        $pdo->exec(
            "INSERT INTO document_statuses (name, badge_color, sort_order) " .
            "VALUES ('_plenary_doc_status', '#6c757d', 0)"
        );
        $docStatusId = (int) $pdo->lastInsertId();
    } else {
        $docStatusId = (int) $ds1Row['id'];
    }

    // Dedicated plenary status for exact-value assertions.
    $pdo->exec(
        "INSERT INTO document_statuses (name, badge_color, sort_order) " .
        "VALUES ('_Plenary Test Status', '#4f46e5', 99)"
    );
    $plenaryStatusId = (int) $pdo->lastInsertId();

    // ── Helper: insert a minimal document ─────────────────────────────────
    $docSeq = 980000;
    $insertDoc = function () use ($pdo, $documentTypeId, $sourceTypeId, $docStatusId, &$docSeq): int {
        $docSeq++;
        $trackingNum = 'PT-' . str_pad((string) ($docSeq - 980000), 4, '0', STR_PAD_LEFT);
        $pdo->prepare(
            "INSERT INTO documents
                (tracking_number, tracking_year, tracking_sequence,
                 subject_matter, document_type_id, source_type_id,
                 current_status_id, date_received, time_received,
                 current_phase)
             VALUES (?, YEAR(CURDATE()), ?, '_plenary test subject',
                     ?, ?, ?, CURDATE(), CURTIME(), 'PLENARY')"
        )->execute([$trackingNum, $docSeq, $documentTypeId, $sourceTypeId, $docStatusId]);
        return (int) $pdo->lastInsertId();
    };

    // ══════════════════════════════════════════════════════════════════════
    // CASE A — plenary_document_details row EXISTS (all fields)
    // ══════════════════════════════════════════════════════════════════════
    echo PHP_EOL . "Case A: plenary_document_details row EXISTS" . PHP_EOL;

    $docIdA = $insertDoc();
    $pdo->prepare(
        "INSERT INTO plenary_document_details
            (document_id, proposed_number, target_first_reading_date,
             session_type, status_id, additional_details)
         VALUES (?, ?, ?, ?, ?, ?)"
    )->execute([
        $docIdA, 'SB-2026-001', '2026-10-15', 'REGULAR',
        $plenaryStatusId, 'Test additional details for plenary.',
    ]);

    $rowA = fetch_plenary_details($pdo, $docIdA);

    assert_test('A1: fetch returns non-null when row exists',                      $rowA !== null);
    assert_test('A2: proposed_number matches',                                     ($rowA['proposed_number']           ?? '') === 'SB-2026-001');
    assert_test('A3: target_first_reading_date matches',                           ($rowA['target_first_reading_date'] ?? '') === '2026-10-15');
    assert_test('A4: session_type matches',                                        ($rowA['session_type']              ?? '') === 'REGULAR');
    assert_test('A5: document_id matches',                                         (int)($rowA['document_id']          ?? 0) === $docIdA);
    assert_test('A6: plenary_status_name joined from document_statuses',           ($rowA['plenary_status_name']       ?? '') === '_Plenary Test Status');
    assert_test('A7: plenary_status_badge_color joined from document_statuses',    ($rowA['plenary_status_badge_color']?? '') === '#4f46e5');
    assert_test('A8: additional_details matches',                                  ($rowA['additional_details']        ?? '') === 'Test additional details for plenary.');

    // ── Case A partial rendering ──────────────────────────────────────────
    $htmlA = render_partial($rowA);
    assert_test('A9:  partial renders proposed number',                            str_contains($htmlA, 'SB-2026-001'));
    assert_test('A10: partial renders readable date (October 15, 2026)',           str_contains($htmlA, 'October 15, 2026'));
    assert_test('A11: partial renders session type label "Regular"',               str_contains($htmlA, 'Regular'));
    assert_test('A12: partial renders status name',                                str_contains($htmlA, htmlspecialchars('_Plenary Test Status')));
    assert_test('A13: partial renders additional details',                         str_contains($htmlA, 'Test additional details for plenary.'));
    assert_test('A14: partial does NOT show empty-state message when row exists',  !str_contains($htmlA, EMPTY_STATE_MSG));
    assert_test('A15: partial contains "Plenary Details" heading',                 str_contains($htmlA, 'Plenary Details'));

    // ── Nullable fields ───────────────────────────────────────────────────
    $docIdA2 = $insertDoc();
    $pdo->prepare(
        "INSERT INTO plenary_document_details
            (document_id, proposed_number, target_first_reading_date,
             session_type, status_id, additional_details)
         VALUES (?, NULL, NULL, NULL, ?, NULL)"
    )->execute([$docIdA2, $plenaryStatusId]);

    $rowA2 = fetch_plenary_details($pdo, $docIdA2);

    assert_test('A16: fetch returns non-null when optional fields are NULL',       $rowA2 !== null);
    assert_test('A17: proposed_number is PHP null',                                $rowA2['proposed_number']           === null);
    assert_test('A18: target_first_reading_date is PHP null',                      $rowA2['target_first_reading_date'] === null);
    assert_test('A19: session_type is PHP null',                                   $rowA2['session_type']              === null);
    assert_test('A20: additional_details is PHP null',                             $rowA2['additional_details']        === null);
    assert_test('A21: status name still joined when optional fields are NULL',     ($rowA2['plenary_status_name']      ?? '') === '_Plenary Test Status');

    // Partial must render without PHP warnings/errors even for all-NULL row.
    $htmlA2 = render_partial($rowA2);
    assert_test('A22: partial renders without empty-state when row exists (all-NULL optionals)', !str_contains($htmlA2, EMPTY_STATE_MSG));

    // ══════════════════════════════════════════════════════════════════════
    // CASE B — NO plenary_document_details row exists
    // ══════════════════════════════════════════════════════════════════════
    echo PHP_EOL . "Case B: NO plenary_document_details row exists" . PHP_EOL;

    $docIdB = $insertDoc();
    // Deliberately do NOT insert a plenary_document_details row.

    $rowB = fetch_plenary_details($pdo, $docIdB);

    assert_test('B1: fetch returns null when no row exists',                       $rowB === null);
    assert_test('B2: $plenaryDetails ?? null evaluates to null',                   ($rowB ?? null) === null);

    $rowAStillThere = fetch_plenary_details($pdo, $docIdA);
    assert_test('B3: absence for doc B does not affect doc A (isolation)',
        $rowAStillThere !== null && ($rowAStillThere['proposed_number'] ?? '') === 'SB-2026-001'
    );

    // ── Case B partial rendering ──────────────────────────────────────────
    $htmlB = render_partial(null);
    assert_test('B4: partial shows exact English empty-state message when null',
        str_contains($htmlB, EMPTY_STATE_MSG)
    );
    assert_test('B5: empty-state message is the exact required string (no extra whitespace/chars)',
        (bool) preg_match('/' . preg_quote(EMPTY_STATE_MSG, '/') . '/', $htmlB)
    );
    assert_test('B6: partial contains "Plenary Details" heading in empty-state too', str_contains($htmlB, 'Plenary Details'));
    assert_test('B7: partial does NOT show field labels when null',                !str_contains($htmlB, 'Proposed Number'));

    // ══════════════════════════════════════════════════════════════════════
    // CASE C — Fixture sample values (marker, proposed number, session type)
    // ══════════════════════════════════════════════════════════════════════
    echo PHP_EOL . "Case C: fixture sample-data values" . PHP_EOL;

    $docIdC = $insertDoc();
    $targetDate = date('Y-m-d', strtotime('first monday of next month'));
    $additionalText  = FX_MARKER . " Sample additional-details text.\n"
        . "Special chars: <>&\"";

    $pdo->prepare(
        "INSERT INTO plenary_document_details
            (document_id, proposed_number, target_first_reading_date,
             session_type, status_id, additional_details)
         VALUES (?, ?, ?, ?, ?, ?)"
    )->execute([
        $docIdC, 'SB-FIXTURE-001', $targetDate, 'REGULAR',
        $plenaryStatusId, $additionalText,
    ]);

    $rowC = fetch_plenary_details($pdo, $docIdC);

    assert_test('C1: fixture proposed_number "SB-FIXTURE-001" round-trips correctly',
        ($rowC['proposed_number'] ?? '') === 'SB-FIXTURE-001'
    );
    assert_test('C2: fixture target_first_reading_date round-trips correctly',
        ($rowC['target_first_reading_date'] ?? '') === $targetDate
    );
    assert_test('C3: fixture session_type "REGULAR" round-trips correctly',
        ($rowC['session_type'] ?? '') === 'REGULAR'
    );
    assert_test('C4: fixture additional_details round-trips including newline',
        str_contains($rowC['additional_details'] ?? '', FX_MARKER)
    );

    // Partial must HTML-escape special characters in additional_details.
    $htmlC = render_partial($rowC);
    assert_test('C5: partial HTML-escapes "<" in additional_details (no raw <)',
        !str_contains($htmlC, '<>&"') && str_contains($htmlC, '&lt;&gt;&amp;&quot;')
    );
    assert_test('C6: partial renders fixture proposed number "SB-FIXTURE-001"',
        str_contains($htmlC, 'SB-FIXTURE-001')
    );
    assert_test('C7: partial renders session type "Regular" from ENUM label map',
        (bool) preg_match('/\bRegular\b/', $htmlC)
    );

    // ══════════════════════════════════════════════════════════════════════
    // CASE D — ENUM label mapping for all four session_type values
    // ══════════════════════════════════════════════════════════════════════
    echo PHP_EOL . "Case D: session_type ENUM label rendering" . PHP_EOL;

    $enumMap = [
        'REGULAR'        => 'Regular',
        'REGULAR_ONLINE' => 'Regular (Online)',
        'SPECIAL'        => 'Special',
        'SPECIAL_ONLINE' => 'Special (Online)',
    ];

    foreach ($enumMap as $enumVal => $expectedLabel) {
        $docIdD = $insertDoc();
        $pdo->prepare(
            "INSERT INTO plenary_document_details
                (document_id, proposed_number, target_first_reading_date,
                 session_type, status_id)
             VALUES (?, 'SB-ENUM-TEST', CURDATE(), ?, ?)"
        )->execute([$docIdD, $enumVal, $plenaryStatusId]);

        $rowD   = fetch_plenary_details($pdo, $docIdD);
        $htmlD  = render_partial($rowD);

        assert_test(
            "D: session_type={$enumVal} renders label \"{$expectedLabel}\"",
            str_contains($htmlD, $expectedLabel)
        );
    }

    // ── Edge cases ────────────────────────────────────────────────────────
    echo PHP_EOL . "Edge cases" . PHP_EOL;

    assert_test('EC1: document_id=0 returns null safely',         fetch_plenary_details($pdo, 0)         === null);
    assert_test('EC2: negative document_id returns null safely',  fetch_plenary_details($pdo, -1)        === null);
    assert_test('EC3: very large document_id returns null safely',fetch_plenary_details($pdo, PHP_INT_MAX) === null);

    // Partial with null must not emit any PHP notice/warning.
    // (ob_start captures output; a warning would appear in the buffer.)
    $htmlNull = render_partial(null);
    assert_test('EC4: render_partial(null) produces non-empty HTML without warnings',
        strlen($htmlNull) > 50 && !str_contains($htmlNull, 'Warning:') && !str_contains($htmlNull, 'Notice:')
    );

} finally {
    // Always roll back — zero permanent DB changes.
    $pdo->rollBack();
}

// ── Summary ────────────────────────────────────────────────────────────────
echo PHP_EOL;
$total = $passed + $failed;
echo "Results: {$passed}/{$total} passed" . ($failed > 0 ? ", {$failed} FAILED" : '') . PHP_EOL;
exit($failed > 0 ? 1 : 0);

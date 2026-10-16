<?php
/**
 * Plenary Details — standalone browser preview
 * ==============================================
 * Renders the plenary-details.php partial with hard-coded sample data.
 * No database connection, no framework bootstrap, no file modifications.
 *
 * LOCAL URL:
 *   http://localhost/pangasinan-lis/tests/plenary_details_preview.php
 *
 * The page is served directly by Laragon's web server; just open the URL above
 * in a browser.  No login or routing is required.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Sample data — mirrors the shape produced by the controller query that joins
// plenary_document_details with document_statuses.
// ---------------------------------------------------------------------------
$plenaryDetails = [
    'proposed_number'            => 'SB-FIXTURE-001',
    'target_first_reading_date'  => '2026-11-03',          // first Monday of next month (from fixture)
    'session_type'               => 'REGULAR',
    'plenary_status_name'        => 'For Calendar',
    'plenary_status_badge_color' => '#4f46e5',
    'additional_details'         =>
        "[PLENARY-FIXTURE] This is a sample additional-details note.\n" .
        "It demonstrates multi-line text and special characters: <>&\"\n" .
        "All output is HTML-escaped in the Plenary Details partial.",
];

// ---------------------------------------------------------------------------
// Also provide a second variant with every nullable field left empty so the
// empty-state branch of the partial can be previewed on the same page.
// ---------------------------------------------------------------------------
$plenaryDetailsEmpty = null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview — Plenary Details Partial</title>

    <!--
        Project stylesheet (compiled Tailwind CSS).
        Path is relative to this file: tests/ → ../public/assets/css/output.css
    -->
    <link rel="stylesheet" href="../public/assets/css/output.css">
</head>
<body class="bg-gray-100 p-8 font-sans text-gray-900">

    <!-- ── Page header ─────────────────────────────────────────────────── -->
    <div class="mx-auto max-w-3xl space-y-8">

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <h1 class="text-lg font-semibold text-gray-800">Plenary Details — partial preview</h1>
            <p class="mt-1 text-sm text-gray-500">
                This page renders <code class="rounded bg-gray-100 px-1 py-0.5 text-xs">resources/views/partials/plenary-details.php</code>
                with sample data. No database or framework is involved.
            </p>
            <p class="mt-2 text-xs text-indigo-500">
                <!-- LOCAL URL: http://localhost/pangasinan-lis/tests/plenary_details_preview.php -->
                Local URL: <a href="http://localhost/pangasinan-lis/tests/plenary_details_preview.php"
                              class="underline">http://localhost/pangasinan-lis/tests/plenary_details_preview.php</a>
            </p>
        </div>

        <!-- ── Variant 1: fully-populated row ──────────────────────────── -->
        <div>
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                Variant 1 — all fields populated
            </p>
            <?php
            // The partial reads $plenaryDetails from the enclosing scope.
            // $plenaryDetails is already set above.
            include __DIR__ . '/../resources/views/partials/plenary-details.php';
            ?>
        </div>

        <!-- ── Variant 2: no plenary row (empty state) ─────────────────── -->
        <div>
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                Variant 2 — no plenary row (null / empty state)
            </p>
            <?php
            // Temporarily swap $plenaryDetails with null so the partial renders
            // its empty-state branch, then restore the original value.
            $plenaryDetails = $plenaryDetailsEmpty;
            include __DIR__ . '/../resources/views/partials/plenary-details.php';
            ?>
        </div>

    </div><!-- /max-w-3xl -->

</body>
</html>

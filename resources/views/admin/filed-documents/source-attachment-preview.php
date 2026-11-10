<?php
$filename = (string) ($filename ?? 'Attachment');
$rawUrl = (string) ($rawUrl ?? '');
$downloadUrl = (string) ($downloadUrl ?? '');
$documentId = (int) ($documentId ?? 0);
$extension = strtolower((string) ($extension ?? pathinfo($filename, PATHINFO_EXTENSION)));
$isDocx = (bool) ($isDocx ?? ($extension === 'docx'));
$isBrowserPreviewable = (bool) ($isBrowserPreviewable ?? in_array(
    $extension,
    ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp'],
    true
));
$pageTitle = 'Attachment Preview';
$pageSubtitle = $filename;
?>
<div class="space-y-5">
    <header class="flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 px-5 py-4 text-white shadow-md sm:px-6">
        <div class="flex min-w-0 items-center gap-3">
            <a href="<?= BASE_URL ?>/admin/filed/source-show?id=<?= (int) $documentId ?>"
               class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/10 hover:bg-white/20 transition"
               aria-label="Back to document details">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div class="min-w-0">
                <p class="text-xs font-medium text-blue-100">ATTACHMENT PREVIEW</p>
                <h1 class="truncate text-lg font-semibold" title="<?= htmlspecialchars($filename) ?>">
                    <?= htmlspecialchars($filename) ?>
                </h1>
            </div>
        </div>
        <a href="<?= htmlspecialchars($downloadUrl) ?>"
           class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm font-semibold text-primary hover:bg-blue-50 transition">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Download
        </a>
    </header>

    <main class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-3 shadow-sm sm:p-5">
        <?php if ($isDocx): ?>
            <div id="docxPreviewStatus" role="status" class="mb-3 text-sm text-gray-500">Preparing document preview…</div>
            <div id="docxPreview" class="min-h-[65vh] overflow-auto"></div>
        <?php elseif ($isBrowserPreviewable && $extension === 'pdf'): ?>
            <iframe src="<?= htmlspecialchars($rawUrl) ?>"
                    title="<?= htmlspecialchars($filename) ?>"
                    class="h-[75vh] min-h-[480px] w-full rounded-lg border border-gray-200 bg-gray-50"></iframe>
        <?php elseif ($isBrowserPreviewable): ?>
            <div class="flex min-h-[60vh] items-center justify-center bg-gray-50 p-4">
                <img src="<?= htmlspecialchars($rawUrl) ?>"
                     alt="<?= htmlspecialchars($filename) ?>"
                     class="max-h-[70vh] max-w-full object-contain">
            </div>
        <?php else: ?>
            <div class="flex min-h-[40vh] flex-col items-center justify-center gap-2 text-center">
                <p class="text-sm font-medium text-gray-800">Preview is not available for this file type.</p>
                <p class="text-xs text-gray-500">Download the attachment to open it in a compatible application.</p>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php if ($isDocx): ?>
<script src="<?= BASE_URL ?>/public/assets/js/jszip.min.js"></script>
<script src="<?= BASE_URL ?>/public/assets/js/docx-preview.min.js"></script>
<script>
(function () {
    var status = document.getElementById('docxPreviewStatus');
    var preview = document.getElementById('docxPreview');
    var fileUrl = <?= json_encode($rawUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    fetch(fileUrl, { credentials: 'same-origin' })
        .then(function (response) {
            if (!response.ok) throw new Error('The attachment could not be loaded.');
            return response.arrayBuffer();
        })
        .then(function (buffer) {
            if (!window.docx || typeof window.docx.renderAsync !== 'function') {
                throw new Error('The document preview library did not load.');
            }
            return window.docx.renderAsync(buffer, preview);
        })
        .then(function () {
            status.remove();
        })
        .catch(function (error) {
            status.textContent = error.message || 'Unable to preview this document.';
            status.className = 'mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700';
        });
}());
</script>
<?php endif; ?>

# PowerShell script to clean up inline flash rendering from Committee views
# This removes variable initialization and inline flash divs, preserving all other content

$files = @(
    "resources/views/committee/inbox/show.php",
    "resources/views/committee/referred/index.php",
    "resources/views/committee/referred/endorse.php",
    "resources/views/committee/referred/opinion.php",
    "resources/views/committee/referred/resolve.php",
    "resources/views/committee/referred/agenda.php",
    "resources/views/committee/hearing/index.php",
    "resources/views/committee/hearing/show.php",
    "resources/views/committee/hearing/report.php",
    "resources/views/committee/hearing/report-show.php",
    "resources/views/committee/hearing/share-to-report.php",
    "resources/views/committee/reports/index.php",
    "resources/views/committee/reports/show.php",
    "resources/views/committee/cases/index.php",
    "resources/views/committee/cases/create.php",
    "resources/views/committee/cases/show.php",
    "resources/views/committee/cases/case-report.php",
    "resources/views/committee/cases/for-report.php",
    "resources/views/committee/cases/deferred.php",
    "resources/views/committee/cases/withdrawn.php",
    "resources/views/committee/cases/noted.php",
    "resources/views/committee/communications/index.php",
    "resources/views/committee/communications/create.php",
    "resources/views/committee/communications/show.php",
    "resources/views/committee/communications/agenda.php",
    "resources/views/committee/communications/hearing.php",
    "resources/views/committee/communications/report.php"
)

Write-Host "Committee Views Cleanup Script" -ForegroundColor Cyan
Write-Host "===============================" -ForegroundColor Cyan
Write-Host ""

$totalFiles = 0
$processedFiles = 0

foreach ($file in $files) {
    if (Test-Path $file) {
        $totalFiles++
        Write-Host "Processing: $file" -ForegroundColor Yellow
        
        $content = Get-Content $file -Raw
        $originalContent = $content
        
        # Pattern 1: Remove flash variable initialization lines
        $content = $content -replace '(?m)^\$success\s*=\s*\$success\s*\?\?\s*null;\s*[\r\n]+', ''
        $content = $content -replace '(?m)^\$error\s*=\s*\$error\s*\?\?\s*null;\s*[\r\n]+', ''
        $content = $content -replace '(?m)^\$errors\s*=\s*\$errors\s*\?\?\s*\[\];\s*[\r\n]+', ''
        
        # Pattern 2: Remove docblock flash variable comments (3 lines)
        $content = $content -replace '(?m)^ \*\s+\$success\s+string\|null[\r\n]+', ''
        $content = $content -replace '(?m)^ \*\s+\$error\s+string\|null[\r\n]+', ''
        $content = $content -replace '(?m)^ \*\s+\$errors\s+array[\r\n]+', ''
        
        if ($content -ne $originalContent) {
            Set-Content -Path $file -Value $content -NoNewline
            $processedFiles++
            Write-Host "  ✓ Variables removed" -ForegroundColor Green
        } else {
            Write-Host "  - No variable changes needed" -ForegroundColor Gray
        }
    } else {
        Write-Host "SKIP: $file (not found)" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "Summary:" -ForegroundColor Cyan
Write-Host "  Total files checked: $totalFiles" -ForegroundColor White
Write-Host "  Files processed: $processedFiles" -ForegroundColor Green
Write-Host ""
Write-Host "Note: Inline flash divs must be removed manually to preserve context" -ForegroundColor Yellow

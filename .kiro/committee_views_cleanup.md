# Committee Views Cleanup - Batch Processing Plan

## Views with Inline Flash Rendering (from grep results)

### Group 1: Index/List Pages (7 files)
1. committee/inbox/index.php - Has $success, $error, $errors + inline divs
2. committee/referred/index.php - Has $success, $error, $errors + inline divs
3. committee/hearing/index.php - Has $success, $error + inline divs
4. committee/reports/index.php - Has $success, $error + inline divs
5. committee/cases/index.php - Has $success, $error, $errors + inline divs
6. committee/communications/index.php - Has $success, $error, $errors + inline divs
7. committee/cases/for-report.php, deferred.php, withdrawn.php, noted.php - Has $success, $error + inline divs

### Group 2: Show/Detail Pages (4 files)
8. committee/inbox/show.php - Has $success, $error, $errors + inline divs
9. committee/reports/show.php - Has $success, $error + inline divs
10. committee/hearing/show.php - Has $success, $error, $errors + inline divs
11. committee/hearing/report-show.php - Has $success, $error + inline divs
12. committee/cases/show.php - Has $success, $error, $errors + inline divs
13. committee/communications/show.php - Has $success, $error, $errors + inline divs

### Group 3: Form Pages (10 files)
14. committee/referred/endorse.php - Has $success, $error, $errors + inline divs
15. committee/referred/opinion.php - Has $success, $error, $errors + inline divs
16. committee/referred/resolve.php - Has $error, $errors + inline divs
17. committee/referred/agenda.php - Has $success, $error, $errors + inline divs
18. committee/hearing/report.php - Has $success, $error, $errors + inline divs
19. committee/hearing/share-to-report.php - Has $success, $error, $errors + inline divs
20. committee/cases/create.php - Has $success, $error, $errors + inline divs
21. committee/cases/case-report.php - Has $success, $error, $errors + inline divs
22. committee/communications/create.php - Has $success, $error, $errors + inline divs
23. committee/communications/agenda.php - Has $success, $error, $errors + inline divs
24. committee/communications/hearing.php - Has $success, $error, $errors + inline divs
25. committee/communications/report.php - Has $error, $errors + inline divs

## Pattern to Remove

### In PHP Variable Section:
```php
$success = $success ?? null;
$error   = $error   ?? null;
$errors  = $errors  ?? [];
```

### In HTML Section (Common Pattern 1):
```php
<?php if ($success): ?>
    <div class="flex items-start gap-3 rounded-2xl border border-green-200 bg-green-50 p-4">
        [icon + text]
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4">
        [icon + text]
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="rounded-2xl border border-red-200 bg-red-50 p-4">
        <ul>...</ul>
    </div>
<?php endif; ?>
```

### In Docblocks:
```
*   $success          string|null
*   $error            string|null
*   $errors           array
```

## Important: Preserve Contextual Information

DO NOT remove:
- Case details, docket numbers, timeline information
- Hearing outcomes, agenda details
- Report summaries, committee information
- Document status, tracking numbers
- Return reasons, remarks fields
- Validation guidance for specific form fields

These are persistent context, not flash messages.

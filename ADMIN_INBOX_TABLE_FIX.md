# Admin Inbox Table Fix

## Problem Summary

The Admin Inbox dashboard showed document counts (Pending: 4, Accepted: 3), but the table displayed "No documents found."

## Root Cause

The issue was a mismatch between two queries in `AdminInboxController.php`:

1. **Statistics Query** (lines 145-153):
   - Only joins `document_assignments` table
   - Counts documents successfully: 7 total (4 pending, 3 accepted)

2. **List Query** (lines 103-130):
   - Used `INNER JOIN` for `document_statuses`, `document_types`, and `source_types`
   - Excluded all 7 documents because they had `NULL` values for `document_type_id`
   - Result: 0 documents displayed in the table

### Data Analysis

All 7 documents in the ADMIN phase had:
- `document_type_id` = `NULL` ← **This caused the exclusion**
- `current_status_id` = 1 (valid)
- `source_type_id` = 1, 2, or 5 (valid)

## Solution Applied

### 1. Controller Fix (`app/controllers/Admin/AdminInboxController.php`)

Changed the list query from `INNER JOIN` to `LEFT JOIN` for optional foreign key relationships:

```php
// BEFORE (INNER JOIN - excludes NULL values)
INNER JOIN document_statuses ds ON d.current_status_id = ds.id
INNER JOIN document_types    dt ON d.document_type_id  = dt.id
INNER JOIN source_types      st ON d.source_type_id    = st.id

// AFTER (LEFT JOIN - includes NULL values)
LEFT  JOIN document_statuses ds ON d.current_status_id = ds.id
LEFT  JOIN document_types    dt ON d.document_type_id  = dt.id
LEFT  JOIN source_types      st ON d.source_type_id    = st.id
```

### 2. View Fix (`resources/views/admin/inbox/index.php`)

Added fallback values for NULL fields:

```php
// Document Type Name
<?= htmlspecialchars($row['document_type_name'] ?? 'Unspecified') ?>

// Status Name
<?= htmlspecialchars($row['status'] ?? 'Unknown') ?>
```

Badge colors already had fallbacks via the `??` operator.

## Verification Results

After applying the fix:

| Metric | Statistics Query | Old INNER JOIN | New LEFT JOIN |
|--------|-----------------|----------------|---------------|
| **Total Documents** | 7 | 0 ❌ | 7 ✅ |
| **Pending Count** | 4 | - | 4 ✅ |
| **Accepted Count** | 3 | - | 3 ✅ |

**Result**: ✅ List query now matches statistics count perfectly!

## Files Modified

1. **`app/controllers/Admin/AdminInboxController.php`**
   - Line 103-130 (index method): Changed INNER JOIN to LEFT JOIN for document lookups in list query
   - Line 167-197 (show method): Changed INNER JOIN to LEFT JOIN for document lookups in detail query

2. **`app/controllers/Receiving/RoutedDocumentController.php`**
   - Line 69-89 (index method): Changed INNER JOIN to LEFT JOIN for document lookups in list query
   - Line 152-182 (show method): Changed INNER JOIN to LEFT JOIN for document lookups in detail query

3. **`resources/views/admin/inbox/index.php`**
   - Line 215: Added fallback for `document_type_name` (displays "Unspecified" if NULL)
   - Line 219: Added fallback for `status` name (displays "Unknown" if NULL)

## Why This Happened

Documents were created with incomplete data (NULL `document_type_id`). The statistics query counted them because it only looked at `document_assignments`, but the list query excluded them because `INNER JOIN` requires matching records in all joined tables.

## Impact

✅ **Fixed**: All 7 documents now appear in the Admin Inbox table
✅ **Fixed**: Admin Inbox document detail pages now load correctly
✅ **Fixed**: Routed Documents list now displays all documents
✅ **Fixed**: Routed Documents detail pages now load correctly
✅ **Maintained**: Data integrity - NULL values display as "Unspecified" or "Unknown"
✅ **Consistent**: Dashboard counts match table results across all affected pages

## Recommendation

Consider adding database constraints or application validation to prevent documents from being created with NULL required foreign keys in the future.

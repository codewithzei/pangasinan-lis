# Receiving Inbox Quick Reference

## Database Conditions

### Returned Documents
```sql
-- Shows documents returned by Admin to Receiving Staff
assigned_to_role_id = {receiving_role_id}
AND phase = 'RECEIVING'
AND decision = 'PENDING'
AND completed_at IS NULL
AND EXISTS (
    SELECT 1 FROM document_assignments
    WHERE document_id = da.document_id
      AND phase = 'ADMIN'
      AND decision = 'DECLINED'
)
```

### Accepted Documents
```sql
-- Shows documents corrected and re-routed to Admin
assigned_to_role_id = {receiving_role_id}
AND phase = 'RECEIVING'
AND decision IN ('ACCEPTED', 'COMPLETED')
AND completed_at IS NOT NULL
AND EXISTS (
    SELECT 1 FROM document_assignments
    WHERE document_id = da.document_id
      AND phase = 'ADMIN'
      AND decision = 'DECLINED'
)
```

## Return Reason Display

### Source Priority
1. **Primary**: `document_assignments.decline_reason` (from Admin DECLINED assignment)
2. **Fallback**: "No reason provided" if NULL

### Display Locations
- **Returned Table**: Visible column (not hidden in tooltip)
- **Edit Page**: Prominent red alert box at top

### Query Pattern
```sql
LEFT JOIN (
    SELECT document_id, decline_reason, declined_at, assigned_by
    FROM document_assignments
    WHERE phase = 'ADMIN' AND decision = 'DECLINED'
    ORDER BY declined_at DESC
) da_admin ON da_admin.document_id = da.document_id
```

## Edit and Route-Back Workflow

### Transaction Steps (All or Nothing)
1. ✓ Fetch current document state
2. ✓ Verify Receiving assignment still PENDING (optimistic lock)
3. ✓ Resolve source name if SP Member
4. ✓ Create document revision (snapshot)
5. ✓ Update document with new values
6. ✓ Mark Receiving assignment COMPLETED (atomic WHERE)
7. ✓ Get Admin role ID
8. ✓ Check for existing pending Admin assignment (duplicate prevention)
9. ✓ Create new PENDING Admin assignment
10. ✓ Update document phase to ADMIN
11. ✓ Insert route record (RECEIVING → ADMIN)
12. ✓ Insert DOCUMENT_EDITED event
13. ✓ Insert ROUTED_TO_ADMIN event
14. ✓ **COMMIT transaction**
15. ✓ Send notifications (AFTER commit)
16. ✓ Log audit events

### Optimistic Locking
```sql
UPDATE document_assignments
SET decision = 'COMPLETED', completed_at = NOW()
WHERE id = ? 
  AND decision = 'PENDING' 
  AND completed_at IS NULL
-- Check rowCount() = 1, else throw exception
```

### Duplicate Prevention
```sql
SELECT id FROM document_assignments
WHERE document_id = ?
  AND assigned_to_role_id = {admin_role_id}
  AND phase = 'ADMIN'
  AND decision = 'PENDING'
  AND completed_at IS NULL
-- If found, throw exception (duplicate exists)
```

## Table Columns

### Returned Tab
1. Tracking Number (hyperlink)
2. Subject Matter
3. Document Type (badge)
4. Current Status (badge)
5. Current Phase (badge)
6. **Date Returned** (declined_at)
7. **Returned By** (declined by user)
8. **Return Reason** (decline_reason - VISIBLE)
9. Action (Edit button)

### Accepted Tab
1. Tracking Number (hyperlink)
2. Subject Matter
3. Document Type (badge)
4. Current Status (badge)
5. Current Phase (badge)
6. **Date Accepted** (completed_at)
7. **Accepted By** (assigned_by user)
8. Action (View button)

## Files Created

```
app/controllers/Receiving/ReceivingInboxController.php
resources/views/receiving/inbox/index.php
resources/views/receiving/inbox/show.php
routes/web.php (modified)
```

## Routes Added

```php
'receiving/inbox' => [
    'method' => 'GET',
    'controller' => 'Receiving/ReceivingInboxController',
    'action' => 'index',
    'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
],

'receiving/inbox/show' => [
    'method' => 'GET',
    'controller' => 'Receiving/ReceivingInboxController',
    'action' => 'show',
    'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
],

'receiving/inbox/update' => [
    'method' => 'POST',
    'controller' => 'Receiving/ReceivingInboxController',
    'action' => 'update',
    'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
],
```

## Testing Validation

✅ **PHP Syntax**: All files pass `php -l` with no errors  
✅ **Transaction Safety**: Optimistic locking + duplicate prevention  
✅ **Notification Pattern**: Sent AFTER commit (not during transaction)  
✅ **UI Design**: Matches Admin Inbox patterns  
✅ **Authorization**: AuthMiddleware + RoleMiddleware on all routes  

## Access URL

Navigate to: `{BASE_URL}/receiving/inbox`

Default view: Returned documents  
Alternative: `{BASE_URL}/receiving/inbox?view=accepted`

## Expected Workflow

```
Admin Returns Document
         ↓
Receiving Inbox → Returned Tab
         ↓
Receiving Staff Edits Document
         ↓
Receiving Staff Routes to Admin
         ↓
New Pending Admin Assignment Created
         ↓
Document Appears in Admin Inbox
```

## Key Safety Features

1. **Optimistic Locking**: Prevents concurrent processing
2. **Duplicate Prevention**: No duplicate Admin assignments
3. **Atomic Updates**: WHERE clause with multiple conditions
4. **Transaction Rollback**: All-or-nothing processing
5. **Notifications After Commit**: No lock timeouts
6. **Double-Submit Prevention**: JavaScript button disable

## Validation Status

- [x] Code syntax validated
- [x] Transaction safety implemented
- [x] UI design matches requirements
- [x] Database queries optimized
- [x] Error handling comprehensive
- [ ] Manual testing pending (see RECEIVING_INBOX_IMPLEMENTATION.md)

**Status**: Ready for deployment and testing

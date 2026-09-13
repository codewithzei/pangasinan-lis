# Admin Inbox Document Processing Fix - Verification Guide

## Overview

This document provides a comprehensive verification checklist for the Admin document processing fix that resolves the "Lock wait timeout exceeded" error when routing documents to SP Secretary.

## Root Cause Summary

The lock timeout was caused by three interconnected issues:

1. **Missing ENUM Value**: The `notifications.type` column did not include `DOCUMENT_ROUTED`, causing INSERT failures
2. **Notification Inside Transaction**: `notifyRoleUsers()` was called within the main transaction, holding locks on multiple tables (documents, assignments, routes, events)
3. **Lock Contention**: The failed notification INSERT caused the transaction to hang, eventually timing out while holding critical locks

## Solution Implemented

### 1. Database Schema Fix
- **File**: `database/migrations/047_add_document_routed_notification_type.php`
- **Change**: Added `DOCUMENT_ROUTED` to the `notifications.type` ENUM
- **Impact**: Allows notification records to be created for routing events

### 2. Transaction Flow Refactoring
- **File**: `app/controllers/Admin/AdminInboxController.php`
- **Changes**:
  - Modified `process()` method to commit transaction BEFORE sending notifications
  - Changed `doRoute()` to return notification data instead of calling `notifyRoleUsers()` directly
  - Notifications are now sent AFTER successful commit in a separate try-catch block
- **Impact**: Eliminates lock contention; workflow success is independent of notification delivery

### 3. Bounded Retry Logic
- **File**: `app/controllers/Admin/AdminInboxController.php`
- **Changes**:
  - Added retry loop (max 3 attempts) for transient database errors
  - Implements exponential backoff (100ms, 200ms, 300ms)
  - Retries only for specific error codes:
    - `1205`: Lock wait timeout
    - `1213`: Deadlock
    - `40001`: Serialization failure
- **Impact**: Transient errors automatically retry; persistent errors fail fast

### 4. Improved Error Handling
- **File**: `app/controllers/Admin/AdminInboxController.php`
- **Changes**:
  - Separate exception handling for:
    - `RuntimeException`: Race conditions, validation logic (no retry)
    - `InvalidArgumentException`: User input validation (no retry)
    - `PDOException`: Database errors (retry if transient)
    - `Throwable`: All other errors (no retry)
  - Notification failures logged as warnings, not errors
  - Success message shown when workflow succeeds even if notification fails
- **Impact**: Clear error messages; no false failures when only notification delivery fails

## Pre-Deployment Verification

### Step 1: Run Migration

```bash
# Run your migration tool to apply migration 047
# Example (adjust to your migration runner):
php artisan migrate
# or
php migrate.php
```

**Verify**:
```sql
SELECT COLUMN_TYPE 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'notifications'
  AND COLUMN_NAME = 'type';
```

Expected output should include `'DOCUMENT_ROUTED'` in the ENUM list.

### Step 2: Run PHP Syntax Check

```bash
php -l app/controllers/Admin/AdminInboxController.php
php -l database/migrations/047_add_document_routed_notification_type.php
```

Expected: `No syntax errors detected`

### Step 3: Run Validation Tests

```bash
php test_admin_inbox_fix.php
```

Expected: All tests should pass.

## Post-Deployment Testing

### Test Case 1: Successful Routing to SP Secretary

**Steps**:
1. Log in as Admin user
2. Navigate to Admin Inbox
3. Open a document with PENDING status
4. Click "Route to SP Secretary"
5. Add optional remarks
6. Submit the form

**Expected Results**:
- ✓ Success message: "Document processed successfully: Route to SP Secretary"
- ✓ Document phase changes to `SP_SECRETARY`
- ✓ Admin assignment marked `COMPLETED`
- ✓ New assignment created for SP Secretary role
- ✓ Document route recorded in `document_routes` table
- ✓ Workflow event recorded in `document_events` table
- ✓ Notifications created for all active SP Secretary users
- ✓ No database errors in system logs

**Verify in Database**:
```sql
-- Check assignment completion
SELECT * FROM document_assignments 
WHERE document_id = ? AND assigned_to_role_id = (SELECT id FROM roles WHERE name = 'Admin')
ORDER BY id DESC LIMIT 1;
-- Expected: decision = 'COMPLETED', completed_at IS NOT NULL

-- Check new assignment
SELECT * FROM document_assignments 
WHERE document_id = ? AND assigned_to_role_id = (SELECT id FROM roles WHERE name = 'SP Secretary')
ORDER BY id DESC LIMIT 1;
-- Expected: decision = 'PENDING', phase = 'SP_SECRETARY'

-- Check notifications
SELECT * FROM notifications 
WHERE document_id = ? AND type = 'DOCUMENT_ROUTED'
ORDER BY id DESC;
-- Expected: One notification per active SP Secretary user
```

### Test Case 2: Multiple Active SP Secretary Users

**Prerequisites**:
- Ensure at least 2 active users with SP Secretary role exist

**Steps**:
1. Route a document to SP Secretary
2. Check notifications table

**Expected Results**:
- ✓ One notification created for EACH active SP Secretary user
- ✓ All notifications have correct `type = 'DOCUMENT_ROUTED'`

### Test Case 3: Concurrent Processing (Race Condition)

**Setup** (requires two browser windows/tabs):
1. Open the same document in two Admin sessions
2. Simultaneously click "Route to SP Secretary" in both windows

**Expected Results**:
- ✓ First request: Success message
- ✓ Second request: Error message "This assignment has already been processed by another request. Please refresh the page."
- ✓ Only ONE assignment completion in database
- ✓ No duplicate routes or events

### Test Case 4: Notification Failure Handling

**Setup** (temporary test):
1. Temporarily break notification delivery (e.g., comment out notification INSERT)
2. Route a document

**Expected Results**:
- ✓ Document routing succeeds
- ✓ Success message shown to user
- ✓ Warning logged: "Notification delivery failed after successful document routing"
- ✓ No rollback of document workflow
- ✓ Transaction committed successfully

**Restore**:
- Uncomment notification code after test

### Test Case 5: Invalid Document ID

**Steps**:
1. Submit routing request with invalid `document_id = 99999`

**Expected Results**:
- ✓ Error message: "Document not found."
- ✓ No database changes
- ✓ Transaction rolled back

### Test Case 6: Already Processed Document

**Steps**:
1. Route a document to SP Secretary
2. Use browser back button
3. Try to route the same document again

**Expected Results**:
- ✓ Error message: "No pending Admin assignment found for this document. It may have already been processed."
- ✓ No duplicate processing

## Performance Verification

### Check Transaction Duration

Monitor the transaction duration before and after the fix:

```sql
-- Enable slow query log temporarily
SET GLOBAL slow_query_log = 1;
SET GLOBAL long_query_time = 0.1;

-- After routing some documents, check slow query log
-- Look for queries that previously took >1s now taking <0.5s
```

**Expected**:
- Transaction duration should be significantly reduced
- No more lock wait timeouts in error logs

### Monitor System Logs

Check `system_logs` table for:

```sql
-- Check for transient errors and retries
SELECT * FROM system_logs 
WHERE level = 'WARNING' 
  AND action LIKE '%Transient database error%'
ORDER BY created_at DESC
LIMIT 10;

-- Check for notification failures
SELECT * FROM system_logs 
WHERE level = 'WARNING' 
  AND action LIKE '%Notification delivery failed%'
ORDER BY created_at DESC
LIMIT 10;
```

## Rollback Plan

If issues arise, rollback in this order:

### 1. Revert Code Changes
```bash
git revert <commit-hash-of-controller-changes>
```

### 2. Rollback Migration (if needed)
```bash
# Run the down() method of migration 047
# This removes DOCUMENT_ROUTED from ENUM
```

**Warning**: Only rollback migration if no DOCUMENT_ROUTED notifications exist:
```sql
SELECT COUNT(*) FROM notifications WHERE type = 'DOCUMENT_ROUTED';
-- If count > 0, DO NOT rollback migration
```

## Success Criteria

The fix is considered successful when ALL of the following are true:

- [ ] Migration 047 applied successfully
- [ ] No syntax errors in modified files
- [ ] All validation tests pass
- [ ] Test Case 1 (successful routing) passes
- [ ] Test Case 2 (multiple users) passes
- [ ] Test Case 3 (race condition) passes
- [ ] Test Case 4 (notification failure) passes
- [ ] No lock timeout errors in production for 24 hours
- [ ] Average transaction duration < 500ms
- [ ] All notifications delivered successfully (or failures logged as warnings)

## Monitoring Recommendations

### For First 48 Hours After Deployment:

1. **Monitor Error Logs**:
   ```sql
   SELECT * FROM system_logs 
   WHERE level IN ('ERROR', 'WARNING') 
     AND action LIKE '%Admin process action%'
     AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
   ORDER BY created_at DESC;
   ```

2. **Check Notification Delivery Rate**:
   ```sql
   SELECT 
     DATE(created_at) as date,
     COUNT(*) as total_notifications,
     SUM(CASE WHEN type = 'DOCUMENT_ROUTED' THEN 1 ELSE 0 END) as routing_notifications
   FROM notifications
   WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
   GROUP BY DATE(created_at)
   ORDER BY date DESC;
   ```

3. **Monitor Retry Frequency**:
   ```sql
   SELECT COUNT(*) as retry_count 
   FROM system_logs 
   WHERE action LIKE '%Transient database error, retrying%'
     AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY);
   ```
   
   If retry_count > 10 per day, investigate database performance.

4. **Check Average Processing Time**:
   Monitor application response times for `/admin/inbox/process` endpoint.
   Expected: < 500ms for successful routing

## Files Modified

1. `database/migrations/047_add_document_routed_notification_type.php` (NEW)
2. `app/controllers/Admin/AdminInboxController.php` (MODIFIED)
   - `process()` method: Added retry logic, moved notification after commit
   - `doRoute()` method: Changed return type from `void` to `array`

## Files Created

1. `test_admin_inbox_fix.php` - Validation test script
2. `ADMIN_INBOX_FIX_VERIFICATION.md` - This verification guide

## Support

If issues persist after applying this fix:

1. Check that migration 047 was successfully applied
2. Review system_logs for specific error messages
3. Verify no custom code is calling the old notification pattern
4. Check database server performance and connection pool settings
5. Consider increasing `innodb_lock_wait_timeout` if transient errors are frequent

## References

- **Original Error**: `SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded`
- **Stack Trace Origin**: 
  - `app/services/DocumentService.php:718`
  - `app/controllers/Admin/AdminInboxController.php:812`
  - `app/controllers/Admin/AdminInboxController.php:341`
- **Action**: `route_sp_secretary`
- **Fix Date**: 2026-09-11

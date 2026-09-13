# Admin Inbox Lock Timeout Fix - Testing Guide

## Quick Testing Checklist

### 1. Basic Functionality Tests

#### Test Case 1.1: Accept Document
1. Login as Admin
2. Navigate to Admin Inbox
3. Click on a pending document
4. Click "Accept" button
5. Add optional remarks
6. Submit
7. ✅ Expected: Document accepted, redirected to inbox with success message

#### Test Case 1.2: Decline Document
1. Login as Admin
2. Navigate to Admin Inbox
3. Click on a pending document
4. Click "Decline" button
5. Enter decline reason (required)
6. Submit
7. ✅ Expected: Document returned to Receiving Staff, success message shown

#### Test Case 1.3: Route to SP Secretary
1. Login as Admin
2. Navigate to Admin Inbox
3. Click on a pending document
4. Click "Route to SP Secretary" button
5. Add optional remarks
6. Submit
7. ✅ Expected: Document routed to SP Secretary, success message shown

#### Test Case 1.4: Route to Plenary
1. Login as Admin
2. Navigate to Admin Inbox
3. Click on a pending document
4. Click "Route to Plenary" button
5. Add optional remarks
6. Submit
7. ✅ Expected: Document routed to Plenary, success message shown

#### Test Case 1.5: Route to Committee
1. Login as Admin
2. Navigate to Admin Inbox
3. Click on a pending document
4. Click "Route to Committee" button
5. Add optional remarks
6. Submit
7. ✅ Expected: Document routed to Committee, success message shown

#### Test Case 1.6: Mark as Noted (Communication Documents Only)
1. Login as Admin
2. Navigate to Admin Inbox
3. Click on a pending Communication document
4. Click "Noted" button
5. Select communication category (required)
6. Add optional remarks
7. Submit
8. ✅ Expected: Document marked as Noted, success message shown

---

### 2. Concurrency Tests (Race Condition Detection)

#### Test Case 2.1: Double-Click Protection
1. Login as Admin
2. Navigate to Admin Inbox
3. Click on a pending document
4. Rapidly double-click "Accept" button
5. ✅ Expected: 
   - First click succeeds
   - Second click either prevented by UI or shows "already processed" error

#### Test Case 2.2: Multiple Tabs - Same Document
1. Login as Admin
2. Open the same pending document in TWO browser tabs
3. In Tab 1: Click "Accept"
4. In Tab 2: Click "Route to SP Secretary"
5. ✅ Expected:
   - Whichever submitted first succeeds
   - The second tab shows: "This assignment has already been processed by another request. Please refresh the page."

#### Test Case 2.3: Multiple Tabs - Different Actions
1. Login as Admin
2. Open the same pending document in TWO browser tabs
3. In Tab 1: Start filling out "Route to Committee" form
4. In Tab 2: Quickly submit "Accept"
5. In Tab 1: Now submit "Route to Committee"
6. ✅ Expected: Tab 1 shows "already processed" error

---

### 3. Lock Contention Tests (Sidebar Interaction)

#### Test Case 3.1: Sidebar Badge While Processing
1. Login as Admin
2. Open Admin Inbox in one tab (sidebar shows badge count)
3. In another tab, open a pending document
4. Submit "Accept" action
5. Refresh first tab to see updated badge count
6. ✅ Expected: 
   - No lock timeout errors
   - Badge count updates correctly
   - Processing completes successfully

#### Test Case 3.2: Multiple Tabs with Sidebar Active
1. Login as Admin
2. Open 5-10 browser tabs, all showing different pages with sidebar visible
3. In one tab, navigate to Admin Inbox
4. Open a pending document
5. Submit any routing action
6. ✅ Expected:
   - No "Lock wait timeout" errors
   - Processing completes successfully
   - All sidebar badges remain responsive

#### Test Case 3.3: Auto-Refresh Scenario
1. Login as Admin
2. Open Admin Inbox (with badge count visible)
3. Set up a browser extension to auto-refresh the page every 2 seconds (or use manual refresh)
4. In another tab, process multiple documents one after another
5. ✅ Expected:
   - No lock timeout errors
   - All processing completes successfully
   - Badge count updates correctly

---

### 4. Error Handling Tests

#### Test Case 4.1: Already Processed Document
1. Login as Admin
2. Navigate to Admin Inbox
3. Click on a pending document (note the ID)
4. Submit "Accept" action - succeeds
5. Manually navigate back to the same document using URL: `/admin/inbox/show?id={document_id}`
6. Try to submit another action
7. ✅ Expected: "No pending Admin assignment found for this document. It may have already been processed."

#### Test Case 4.2: Invalid Document ID
1. Login as Admin
2. Manually navigate to: `/admin/inbox/show?id=999999`
3. ✅ Expected: "Document not found" error, redirected to inbox

#### Test Case 4.3: Decline Without Reason
1. Login as Admin
2. Navigate to Admin Inbox
3. Click on a pending document
4. Click "Decline" button
5. Leave decline reason blank
6. Submit
7. ✅ Expected: "A decline reason is required." error

#### Test Case 4.4: Noted on Non-Communication Document
1. Login as Admin
2. Navigate to Admin Inbox
3. Click on a pending NON-Communication document (e.g., Ordinance, Resolution)
4. Try to submit "Noted" action
5. ✅ Expected: "The NOTED action is only available for Communication documents." error

---

### 5. Load Tests (Optional)

#### Test Case 5.1: Rapid Sequential Processing
1. Login as Admin
2. Navigate to Admin Inbox
3. Process 10 documents rapidly, one after another (Accept, Route, etc.)
4. ✅ Expected:
   - All actions complete successfully
   - No lock timeout errors
   - Correct state transitions for all documents

#### Test Case 5.2: Concurrent Users
1. Create 2-3 Admin user accounts
2. Login with all accounts in different browsers/incognito windows
3. Have all users navigate to Admin Inbox
4. Assign different documents to process
5. Process documents simultaneously
6. ✅ Expected:
   - All actions complete successfully
   - No lock timeout errors
   - Each user only processes their assigned documents once

---

## Error Messages Reference

### ✅ Expected New Error (Race Condition Detected)
```
This assignment has already been processed by another request. Please refresh the page.
```
**When:** Another request already processed the same assignment
**Action:** User should refresh the page to see updated state

### ❌ Should NOT Appear (Old Lock Timeout Error)
```
Another request is currently processing this document. Please wait a moment and try again.
```
**If this appears:** The fix did not work correctly, investigate MySQL error logs

### ⚠️ Validation Errors (Expected)
- "A decline reason is required."
- "A communication category is required for the NOTED action."
- "Invalid or inactive communication category selected."
- "The NOTED action is only available for Communication documents."

### ⚠️ State Errors (Expected)
- "No pending Admin assignment found for this document. It may have already been processed."
- "Document not found."
- "Invalid document ID."

---

## Automated Test Script Ideas

### Browser Console Test (JavaScript)
```javascript
// Test: Rapid double-submit protection
const form = document.querySelector('form');
const button = form.querySelector('button[type="submit"]');

// Simulate rapid clicks
for (let i = 0; i < 5; i++) {
  setTimeout(() => button.click(), i * 100);
}
```

### PHP Manual Test Script
```php
<?php
// File: test_concurrent_process.php
// Simulates concurrent requests to the same document

require_once 'app/config/database.php';

$documentId = 1; // Replace with actual pending document ID
$action = 'accept';

// Fork 2 processes to simulate concurrent requests
$pid = pcntl_fork();

if ($pid == -1) {
    die('Fork failed');
} elseif ($pid) {
    // Parent process
    sleep(1);
    echo "Parent: Processing document {$documentId}\n";
    // Make HTTP request to process endpoint
} else {
    // Child process
    echo "Child: Processing document {$documentId}\n";
    // Make HTTP request to process endpoint
    exit(0);
}

pcntl_wait($status);
```

---

## Monitoring During Testing

### Things to Watch in Logs

1. **System Logs** (`system_logs` table):
   - Look for `Lock wait timeout` messages - should be ZERO
   - Check for any new ERROR level entries

2. **Audit Logs** (`audit_logs` table):
   - Verify all processed documents have correct audit entries
   - Check for duplicate processing (should NOT happen)

3. **Document Events** (`document_events` table):
   - Verify workflow events are created correctly
   - No duplicate or missing events

4. **Document Assignments** (`document_assignments` table):
   - All processed assignments should have:
     - `decision` changed from 'PENDING' to final state
     - `completed_at` timestamp set
   - No assignments stuck in 'PENDING' state after processing

### MySQL Query to Check for Issues

```sql
-- Check for documents processed multiple times (should return 0 rows)
SELECT 
    document_id, 
    COUNT(*) as process_count,
    GROUP_CONCAT(decision) as decisions
FROM document_assignments 
WHERE assigned_to_role_id = (SELECT id FROM roles WHERE name = 'Admin')
  AND decision != 'PENDING'
  AND completed_at IS NOT NULL
GROUP BY document_id
HAVING COUNT(*) > 1;

-- Check for lock wait timeouts in recent system logs
SELECT * FROM system_logs 
WHERE level = 'WARNING'
  AND message LIKE '%lock%wait%timeout%'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
ORDER BY created_at DESC;
```

---

## Rollback Indicators

**If you see any of these, consider rolling back:**

1. ❌ Lock wait timeout errors (1205) still occurring
2. ❌ Documents processed multiple times (duplicate events)
3. ❌ Assignment state corruption (multiple assignments in wrong states)
4. ❌ Increased error rates in general
5. ❌ Data integrity issues (missing events, routes, or audit logs)

**Success Indicators:**

1. ✅ Zero lock wait timeout errors
2. ✅ All documents processed exactly once
3. ✅ Race condition errors appear only when genuinely racing (multiple tabs)
4. ✅ Sidebar remains responsive during processing
5. ✅ All workflow events and audit logs correct

---

## Post-Deployment Monitoring

### First 24 Hours
- Monitor error logs every 2 hours
- Check system_logs table for any ERROR or WARNING entries
- Verify document processing continues normally
- Track race condition error frequency (should be rare)

### First Week
- Daily review of error logs
- Weekly audit of document_assignments table for any anomalies
- User feedback collection

### Long-Term
- Set up alerting for MySQL error 1205
- Monitor race condition error frequency trends
- Track document processing throughput improvements

---

## Contact Information

**Issue Reporter:** [Admin User]  
**Developer:** [Your Name]  
**Date Fixed:** 2026-09-09  
**Commit Hash:** [To be filled after commit]  

---

## Notes for QA Team

- Focus on **concurrency testing** - the fix specifically addresses race conditions
- Test with **real production traffic patterns** if possible
- Use **multiple browser tabs** to simulate concurrent access
- Pay attention to **sidebar behavior** - it should never block document processing
- Document any new error patterns that emerge


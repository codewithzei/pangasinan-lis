# Admin Inbox Process Document - Test Checklist

## Pre-Testing Setup
- [ ] Ensure you have an Admin role user account
- [ ] Ensure there's at least one document in the Admin inbox with PENDING status
- [ ] Clear browser cache to ensure fresh JavaScript
- [ ] Open browser developer console (F12) to monitor for errors

## Test Cases

### Test Case 1: Accept Document
**Objective**: Verify that accepting a document works without errors

1. [ ] Log in as Admin user
2. [ ] Navigate to Admin Inbox (`/admin/inbox`)
3. [ ] Click on a pending document to view details
4. [ ] Enter optional remarks in the remarks field
5. [ ] Click "Accept" button
6. [ ] Verify confirmation modal appears
7. [ ] Click "Confirm" button
8. [ ] Verify button shows spinner and "Processing..." text
9. [ ] **Expected**: Success message "Document processed successfully: Accepted"
10. [ ] **Expected**: Redirected to Admin Inbox list
11. [ ] **Expected**: Document no longer appears in inbox

**❌ FAIL if**: "Another request is currently processing this document" error appears

---

### Test Case 2: Decline Document
**Objective**: Verify that declining a document with proper validation works

1. [ ] Log in as Admin user
2. [ ] Navigate to Admin Inbox
3. [ ] Click on a pending document
4. [ ] **DO NOT** enter remarks (test validation)
5. [ ] Click "Decline & Return to Receiving" button
6. [ ] **Expected**: Red border on remarks field (client-side validation)
7. [ ] Enter a decline reason in remarks field
8. [ ] Click "Decline & Return to Receiving" again
9. [ ] Verify confirmation modal appears
10. [ ] Click "Confirm"
11. [ ] **Expected**: Success message "Document processed successfully: Declined and returned to Receiving"
12. [ ] **Expected**: Document status changed
13. [ ] **Expected**: Document returned to Receiving Staff inbox

**❌ FAIL if**: Can decline without remarks OR "Another request" error appears

---

### Test Case 3: Route to SP Secretary
**Objective**: Verify routing functionality works correctly

1. [ ] Log in as Admin user
2. [ ] Navigate to Admin Inbox
3. [ ] Click on a pending document
4. [ ] Enter optional remarks
5. [ ] Click "Route to SP Secretary" button
6. [ ] Verify confirmation modal
7. [ ] Click "Confirm"
8. [ ] **Expected**: Success message "Document processed successfully: Routed to SP Secretary"
9. [ ] **Expected**: Document moved to SP Secretary inbox
10. [ ] **Expected**: Document no longer in Admin inbox

**❌ FAIL if**: "Another request" error appears OR document not routed

---

### Test Case 4: Route to Plenary
**Objective**: Verify plenary routing works

1. [ ] Log in as Admin user
2. [ ] Navigate to Admin Inbox
3. [ ] Click on a pending document
4. [ ] Click "Route to Plenary" button
5. [ ] Confirm action
6. [ ] **Expected**: Success message "Document processed successfully: Routed to Plenary"
7. [ ] **Expected**: Document moved to Plenary inbox

**❌ FAIL if**: Routing fails or error appears

---

### Test Case 5: Route to Committee
**Objective**: Verify committee routing works

1. [ ] Log in as Admin user
2. [ ] Navigate to Admin Inbox
3. [ ] Click on a pending document
4. [ ] Click "Route to Committee" button
5. [ ] Confirm action
6. [ ] **Expected**: Success message "Document processed successfully: Routed to Committee"
7. [ ] **Expected**: Document moved to Committee inbox

**❌ FAIL if**: Routing fails or error appears

---

### Test Case 6: Mark as Noted (Communication Documents Only)
**Objective**: Verify NOTED action works for communication documents

1. [ ] Log in as Admin user
2. [ ] Navigate to Admin Inbox
3. [ ] Find a document with "Communication" in its type
4. [ ] Click on the document
5. [ ] **Expected**: "Mark as Noted" button should be enabled (green)
6. [ ] Click "Mark as Noted" button
7. [ ] **Expected**: Communication Category dropdown appears
8. [ ] **DO NOT** select category (test validation)
9. [ ] Click in confirmation
10. [ ] **Expected**: Red border on category dropdown
11. [ ] Select a communication category
12. [ ] Click "Mark as Noted" again and confirm
13. [ ] **Expected**: Success message "Document processed successfully: Marked as Noted"
14. [ ] **Expected**: Document status changed to "Noted"

**Note**: For non-communication documents, the "Noted" button should be disabled (grayed out)

**❌ FAIL if**: Can mark as noted without category OR "Another request" error

---

### Test Case 7: Double-Click Prevention
**Objective**: Verify that multiple rapid clicks don't cause issues

1. [ ] Log in as Admin user
2. [ ] Navigate to Admin Inbox
3. [ ] Click on a pending document
4. [ ] Click any action button
5. [ ] In confirmation modal, rapidly click "Confirm" button 5-10 times
6. [ ] **Expected**: Button becomes disabled after first click
7. [ ] **Expected**: Spinner and "Processing..." text appears
8. [ ] **Expected**: Only ONE request is processed
9. [ ] **Expected**: No duplicate errors

**❌ FAIL if**: Multiple requests processed OR error appears

---

### Test Case 8: Already Processed Document
**Objective**: Verify proper error message when document already processed

1. [ ] Open document in Admin Inbox in TWO browser tabs/windows
2. [ ] In TAB 1: Process the document (any action)
3. [ ] Wait for success message in TAB 1
4. [ ] In TAB 2: Try to process the same document
5. [ ] **Expected**: Error message "No pending Admin assignment found for this document. It may have already been processed."
6. [ ] **NOT Expected**: "Another request is currently processing" error

**❌ FAIL if**: Wrong error message OR allows double processing

---

### Test Case 9: Upload Additional Attachments
**Objective**: Verify file upload still works alongside process actions

1. [ ] Log in as Admin user
2. [ ] Navigate to Admin Inbox
3. [ ] Click on a pending document
4. [ ] Scroll to "Upload Additional Files" section
5. [ ] Select one or more files
6. [ ] Click "Upload" button
7. [ ] **Expected**: Success message with file count
8. [ ] **Expected**: Files appear in attachments list
9. [ ] Now process the document (any action)
10. [ ] **Expected**: Both upload and process work independently

**❌ FAIL if**: Upload or process fails

---

## Error Scenarios to Verify

### Proper Error Messages
The following scenarios should show SPECIFIC error messages (not generic "Another request" error):

| Scenario | Expected Error Message |
|----------|----------------------|
| Decline without remarks | "A decline reason is required." |
| Noted without category | "A communication category is required for the NOTED action." |
| Invalid category selected | "Invalid or inactive communication category selected." |
| Already processed document | "No pending Admin assignment found for this document. It may have already been processed." |
| Invalid action specified | "Invalid action specified." |
| Document not found | "Document not found." |

---

## Browser Console Check
During all tests, check browser console (F12) for:
- [ ] No JavaScript errors
- [ ] No network errors (check Network tab)
- [ ] POST request to `/admin/inbox/process` returns HTTP 302 (redirect)

---

## Database Verification (Optional)
After processing actions:
1. [ ] Check `document_assignments` table: Decision should be updated
2. [ ] Check `documents` table: Phase/status should be updated
3. [ ] Check `document_events` table: Event should be logged
4. [ ] Check `document_routes` table: Route record created (for routing actions)

---

## Performance Check
- [ ] Each action completes within 3 seconds
- [ ] No hanging or timeout issues
- [ ] No excessive database queries (check system logs)

---

## Pass/Fail Criteria

### ✅ PASS if:
- All test cases complete successfully
- No "Another request is currently processing" errors appear on valid actions
- Proper error messages shown for validation failures
- Double-click prevention works
- Database records updated correctly

### ❌ FAIL if:
- "Another request" error appears on first attempt
- Actions don't process documents correctly
- Validation doesn't work
- Multiple submissions possible
- Wrong error messages displayed

---

## Notes
- Test with fresh browser session to ensure no cached data
- Test on different browsers (Chrome, Firefox, Edge) if possible
- If any test fails, check `system_logs` table for detailed error information
- Document any issues found with screenshots and console errors

# Toast Notification QA Test Plan

## Objective
Verify toast notification behavior across all Receiving and Admin document-processing pages and actions.

## Test Environment
- **Environment**: Local development (Laragon)
- **Base URL**: http://pangasinan-lis.test (or as configured)
- **Test Data**: Disposable test records only
- **Database**: Local MySQL database

## Test Scope
- ✅ Receiving workflows (9 routes)
- ✅ Admin workflows (14 routes)
- ❌ Committee, Plenary, SP Secretary (excluded per requirements)

## Test Cases

### Success Criteria for Each Test
1. ✓ Success toast appears for successful actions (green, checkmark icon)
2. ✓ Error toast appears for validation/error cases (red, X or warning icon)
3. ✓ Toast appears exactly once (no duplicates)
4. ✓ Toast is readable and properly positioned
5. ✓ Toast dismisses according to existing behavior (~4.5s auto-dismiss)
6. ✓ Toast does not cover essential controls
7. ✓ Message is not lost due to premature flash consumption
8. ✓ Field-level guidance remains visible
9. ✓ Persistent contextual information remains available
10. ✓ Responsive at desktop and mobile widths

### Test Result Codes
- **PASS**: All success criteria met
- **FAIL**: One or more criteria failed
- **BLOCKED**: Cannot test due to dependency/access issue
- **N/A**: Route does not exist or is not applicable

---

## RECEIVING WORKFLOWS

### 1. Receive Document Page
**Route**: `GET /receiving/receive-document`  
**Controller**: `Receiving/RouteDocumentController::index()`

#### Test Cases:
- [ ] **RCV-001**: Page loads, any existing flash messages display as toast
- [ ] **RCV-002**: Success submit - valid data → green toast "Document submitted successfully"
- [ ] **RCV-003**: Validation error - missing required fields → red toast with error list
- [ ] **RCV-004**: Upload validation failure - invalid file type → red toast with file errors
- [ ] **RCV-005**: Server error (if safely reproducible) → red toast with error message

**Expected Behavior**: 
- Flash messages set by previous actions display on page load
- Form submission redirects back with flash message
- Toast appears in header, not inline in form

---

### 2. Receive Document Checklist AJAX
**Route**: `GET /receiving/receive-document/get-checklists?document_type_id={id}`  
**Controller**: `Receiving/RouteDocumentController::getChecklistsByDocumentType()`

#### Test Cases:
- [ ] **RCV-006**: Valid document type ID → JSON response with checklists, no toast needed
- [ ] **RCV-007**: Invalid/missing document type ID → appropriate error handling
- [ ] **RCV-008**: No JavaScript errors in console

**Expected Behavior**:
- AJAX endpoint returns JSON data
- No toast notification for successful data retrieval
- Error handling graceful (no uncaught exceptions)

---

### 3. Receiving Inbox List
**Route**: `GET /receiving/inbox`  
**Controller**: `Receiving/ReceivingInboxController::index()`

#### Test Cases:
- [ ] **RCV-009**: Page loads after redirect from accept action → green toast "Document accepted"
- [ ] **RCV-010**: Page loads after redirect from update action → green toast "Document updated"
- [ ] **RCV-011**: Page loads with error flash → red toast with error message

**Expected Behavior**:
- Flash messages from POST actions display as toast
- No inline flash rendering in page body
- Toast appears once in header

---

### 4. Returned Document Detail & Accept
**Route**: `GET /receiving/inbox/show?id={test-id}`  
**Controller**: `Receiving/ReceivingInboxController::show()`  
**Action**: `POST /receiving/inbox/accept`

#### Test Cases:
- [ ] **RCV-012**: View returned document detail → any flash messages display as toast
- [ ] **RCV-013**: Accept document (valid) → redirect to inbox with green toast
- [ ] **RCV-014**: Accept document (already accepted) → red toast "Document already accepted"
- [ ] **RCV-015**: Accept document (no permission) → red toast "No permission"
- [ ] **RCV-016**: Return reason remains visible on page (persistent context)

**Expected Behavior**:
- Accept button triggers POST to `/receiving/inbox/accept`
- Redirect back to inbox with flash message
- Toast displays in header after redirect
- Document return reason visible in detail view (not replaced by toast)

---

### 5. Update Returned Document
**Route**: `POST /receiving/inbox/update`  
**Controller**: `Receiving/ReceivingInboxController::update()`

#### Test Cases:
- [ ] **RCV-017**: Valid update → redirect with green toast "Document updated successfully"
- [ ] **RCV-018**: Validation failure (missing required fields) → red toast with error list
- [ ] **RCV-019**: Server error (if safely reproducible) → red toast with error message
- [ ] **RCV-020**: Field-level validation messages remain visible in form

**Expected Behavior**:
- Form submission redirects with flash message
- Validation errors display as toast with bulleted list
- Field-level guidance not replaced by toast

---

### 6. Attachment Upload (Receiving)
**Route**: `POST /receiving/inbox/attachment/upload`  
**Controller**: `Receiving/ReceivingInboxController::uploadAttachment()`

#### Test Cases:
- [ ] **RCV-021**: Valid file upload → redirect with green toast "File uploaded successfully"
- [ ] **RCV-022**: Invalid file type → red toast "Invalid file type"
- [ ] **RCV-023**: File size exceeds limit → red toast "File too large"
- [ ] **RCV-024**: No file selected → red toast "No file selected"

**Expected Behavior**:
- Form POST redirects with flash message
- Toast displays upload result
- No duplicate messages

---

### 7. Attachment Delete (Receiving)
**Route**: `POST /receiving/inbox/attachment/delete`  
**Controller**: `Receiving/ReceivingInboxController::deleteAttachment()`

#### Test Cases:
- [ ] **RCV-025**: Valid delete → redirect with green toast "Attachment deleted"
- [ ] **RCV-026**: Invalid attachment ID → red toast "Invalid attachment"
- [ ] **RCV-027**: No permission → red toast "Permission denied"

**Expected Behavior**:
- Form POST redirects with flash message
- Toast displays delete result

---

### 8. Routed Documents List
**Route**: `GET /receiving/routed-documents`  
**Controller**: `Receiving/RoutedDocumentController::index()`

#### Test Cases:
- [ ] **RCV-028**: Page loads → any flash messages display as toast
- [ ] **RCV-029**: No inline flash rendering in page

**Expected Behavior**:
- Read-only page
- Flash messages from previous actions may display
- Toast in header only

---

### 9. Routed Document Detail
**Route**: `GET /receiving/routed-documents/show?id={test-id}`  
**Controller**: `Receiving/RoutedDocumentController::show()`

#### Test Cases:
- [ ] **RCV-030**: Page loads → any flash messages display as toast
- [ ] **RCV-031**: Read-only view, no actions to test

**Expected Behavior**:
- Read-only detail page
- Flash messages from routing actions may display

---

### 10. Legacy Route Document (if used)
**Route**: `GET /receiving/route-document`  
**Controller**: `Receiving/RouteDocumentController::index()` (same as receive-document)

#### Test Cases:
- [ ] **RCV-032**: Check if route is actively used
- [ ] **RCV-033**: If used, verify same behavior as /receiving/receive-document

**Expected Behavior**:
- May be alias for /receiving/receive-document
- Same controller and toast behavior

---

## ADMIN WORKFLOWS

### 11. Admin Direct Receive Document
**Route**: `GET /admin/receive-document`  
**Controller**: `Admin/AdminReceiveDocumentController::index()`

#### Test Cases:
- [ ] **ADM-001**: Page loads, any existing flash messages display as toast
- [ ] **ADM-002**: Success submit - valid data → green toast "Document received successfully"
- [ ] **ADM-003**: Validation error - missing required fields → red toast with error list
- [ ] **ADM-004**: Upload validation failure - invalid file → red toast with file errors
- [ ] **ADM-005**: Server error (if safely reproducible) → red toast with error message

**Expected Behavior**:
- Same pattern as Receiving receive-document
- Flash messages display in header only

---

### 12. Admin Receive Document Checklist AJAX
**Route**: `GET /admin/receive-document/get-checklists?document_type_id={id}`  
**Controller**: `Admin/AdminReceiveDocumentController::getChecklistsByDocumentType()`

#### Test Cases:
- [ ] **ADM-006**: Valid document type ID → JSON response, no toast
- [ ] **ADM-007**: Invalid ID → appropriate error handling
- [ ] **ADM-008**: No JavaScript errors in console

**Expected Behavior**:
- AJAX endpoint returns JSON data
- No toast for data retrieval
- Graceful error handling

---

### 13. Admin Inbox List
**Route**: `GET /admin/inbox`  
**Controller**: `Admin/AdminInboxController::index()`

#### Test Cases:
- [ ] **ADM-009**: Page loads after process action → toast displays result
- [ ] **ADM-010**: Page loads after upload → green toast "Files uploaded"
- [ ] **ADM-011**: Page loads with error flash → red toast with error

**Expected Behavior**:
- Flash messages from POST actions display as toast
- No inline flash rendering

---

### 14. Admin Inbox Document Detail
**Route**: `GET /admin/inbox/show?id={test-id}`  
**Controller**: `Admin/AdminInboxController::show()`

#### Test Cases:
- [ ] **ADM-012**: View document detail → any flash messages display as toast
- [ ] **ADM-013**: Page shows accept button if document is PENDING
- [ ] **ADM-014**: Page shows process actions if document is ACCEPTED
- [ ] **ADM-015**: Upload form visible when user has permission

**Expected Behavior**:
- Detail page with conditional action buttons
- Flash messages display in header
- No inline flash rendering

---

### 15. Admin Inbox Process Actions
**Route**: `POST /admin/inbox/process`  
**Controller**: `Admin/AdminInboxController::process()`

#### Test Cases - Accept:
- [ ] **ADM-016**: Accept document (valid) → redirect with green toast "Document accepted"
- [ ] **ADM-017**: Accept document (already accepted) → red toast with error

#### Test Cases - Decline/Return:
- [ ] **ADM-018**: Decline document (valid with remarks) → green toast "Document declined"
- [ ] **ADM-019**: Decline without remarks → red toast "Remarks required"

#### Test Cases - Route:
- [ ] **ADM-020**: Route to SP Secretary (valid) → green toast "Document routed"
- [ ] **ADM-021**: Route to Plenary (valid) → green toast "Document routed"
- [ ] **ADM-022**: Route to Committee (valid with committee selected) → green toast "Routed to committee"
- [ ] **ADM-023**: Route to Committee (no committee selected) → red toast "Committee required"

#### Test Cases - Mark as Noted:
- [ ] **ADM-024**: Mark as noted (valid) → green toast "Marked as noted"
- [ ] **ADM-025**: Mark as noted (invalid state) → red toast with error

**Expected Behavior**:
- All actions redirect with flash message
- Toast displays action result
- Decline reason persists in database (not just toast)

---

### 16. Admin Inbox Attachment Upload
**Route**: `POST /admin/inbox/upload`  
**Controller**: `Admin/AdminInboxController::upload()`

#### Test Cases:
- [ ] **ADM-026**: Valid file upload → redirect with green toast "N file(s) uploaded"
- [ ] **ADM-027**: Invalid file type → red toast with validation errors
- [ ] **ADM-028**: File size exceeds limit → red toast with error
- [ ] **ADM-029**: No file selected → red toast "No files selected"
- [ ] **ADM-030**: No permission → red toast "No permission"

**Expected Behavior**:
- Form POST redirects with flash message
- Toast displays upload result with file count

---

### 17. Admin Routed Documents List
**Route**: `GET /admin/routed`  
**Controller**: `Admin/AdminRoutedDocumentController::index()`

#### Test Cases:
- [ ] **ADM-031**: Page loads → any flash messages display as toast
- [ ] **ADM-032**: Read-only list, no actions to test

**Expected Behavior**:
- Read-only page
- Flash messages from routing actions may display

---

### 18. Admin Routed Document Detail
**Route**: `GET /admin/routed/show?id={test-id}`  
**Controller**: `Admin/AdminRoutedDocumentController::show()`

#### Test Cases:
- [ ] **ADM-033**: Page loads → any flash messages display as toast
- [ ] **ADM-034**: Read-only view, no actions

**Expected Behavior**:
- Read-only detail page
- No action buttons

---

### 19. Admin Communications List
**Route**: `GET /admin/communications`  
**Controller**: `Admin/AdminCommunicationsController::index()`

#### Test Cases:
- [ ] **ADM-035**: Page loads → any flash messages display as toast
- [ ] **ADM-036**: Read-only list, no actions

**Expected Behavior**:
- Read-only page
- Flash messages may display from previous actions

---

### 20. Admin Communication Detail
**Route**: `GET /admin/communications/show?id={test-id}`  
**Controller**: `Admin/AdminCommunicationsController::show()`

#### Test Cases:
- [ ] **ADM-037**: Page loads → any flash messages display as toast
- [ ] **ADM-038**: Read-only view, no actions

**Expected Behavior**:
- Read-only detail page
- No action buttons

---

### 21. Admin Archived Records List
**Route**: `GET /admin/archived-records`  
**Controller**: `Admin/ArchivedRecordsController::index()`

#### Test Cases:
- [ ] **ADM-039**: Page loads → check for flash message toast
- [ ] **ADM-040**: Check if page uses inline toast or header toast

**Note**: Archived records was explicitly excluded from toast standardization. This is for observation only.

**Expected Behavior**:
- May still use inline toast implementation
- Not modified during toast standardization

---

### 22. Admin Archived Record Detail
**Route**: `GET /admin/archived-records/show?id={test-id}`  
**Controller**: `Admin/ArchivedRecordsController::show()`

#### Test Cases:
- [ ] **ADM-041**: Page loads → check toast implementation
- [ ] **ADM-042**: Delete action → verify feedback mechanism

**Expected Behavior**:
- May use different toast pattern (module not standardized)

---

### 23. Admin Archived Record Edit
**Route**: `GET /admin/archived-records/edit?id={test-id}`  
**Controller**: `Admin/ArchivedRecordsController::edit()`

#### Test Cases:
- [ ] **ADM-043**: Page loads → check toast implementation
- [ ] **ADM-044**: Update action → verify feedback

**Expected Behavior**:
- May use different toast pattern

---

### 24. Admin Archived Record CRUD Actions
**Routes**: 
- `POST /admin/archived-records/store`
- `POST /admin/archived-records/update`
- `POST /admin/archived-records/destroy`
- `POST /admin/archived-records/attachment/delete`

#### Test Cases:
- [ ] **ADM-045**: Create record → verify feedback
- [ ] **ADM-046**: Update record → verify feedback
- [ ] **ADM-047**: Delete record → verify feedback (AJAX JSON response)
- [ ] **ADM-048**: Delete attachment → verify feedback (AJAX JSON response)

**Expected Behavior**:
- May use AJAX with client-side toast
- Module not standardized, separate implementation

---

## RESPONSIVE TESTING

For each page with active toast testing:
- [ ] **RESP-001**: Desktop width (1920x1080) - toast visible and positioned correctly
- [ ] **RESP-002**: Tablet width (768x1024) - toast adapts to narrower screen
- [ ] **RESP-003**: Mobile width (375x667) - toast doesn't obscure content

---

## ACCESSIBILITY & UX CHECKS

- [ ] **A11Y-001**: Toast has sufficient color contrast
- [ ] **A11Y-002**: Toast content is readable (text size, spacing)
- [ ] **A11Y-003**: Toast icon + text provide redundant information
- [ ] **A11Y-004**: Toast auto-dismisses within reasonable time (~4.5s)
- [ ] **A11Y-005**: Toast does not block essential controls during display
- [ ] **A11Y-006**: Keyboard users can continue interaction while toast displays

---

## Test Execution Log

Testing Date: _________________  
Tester: _________________  
Environment: _________________  
Browser: _________________

---

## Summary Template

### Results Summary
- **Total Test Cases**: ___
- **PASS**: ___
- **FAIL**: ___
- **BLOCKED**: ___
- **N/A**: ___

### Failures Detail
_(For each failure, include: Test ID, Route/Action, Expected Result, Actual Result, Likely Cause)_

1. 
2. 
3. 

### Recommendations
_(Do not fix failures in this QA run - document only)_

1. 
2. 
3. 

---

## Notes
- Use disposable test data only
- Clean up test records after testing
- Do not modify real documents
- Document any blockers or access issues
- Take screenshots of failures if needed

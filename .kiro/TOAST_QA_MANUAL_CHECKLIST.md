# Toast Notification QA - Manual Testing Checklist

**Ginagamit para sa**: Browser-based manual testing  
**Base URL**: http://pangasinan-lis.test  
**Instructions**: I-check ang box pagkatapos ng bawat test

---

## ⚠️ PAALALA: CRITICAL BUG EXISTS

May critical bug pa sa `ReceivingInboxController::show()` method.  
**I-fix muna ito bago mag-test!**

Fix location: Line 486-488 ng `app/controllers/Receiving/ReceivingInboxController.php`

---

## Pre-Test Setup

- [ ] Laragon running (Apache + MySQL)
- [ ] Browser open (Chrome/Firefox/Edge)
- [ ] Navigate to: http://pangasinan-lis.test
- [ ] Test user accounts ready (Receiving role + Admin role)
- [ ] Test documents available sa database
- [ ] DevTools ready (F12) para sa console errors

---

## RECEIVING WORKFLOWS

### 1. Receive Document - `/receiving/receive-document`

- [ ] **Load page** → check kung may flash toast from previous action
- [ ] **Submit valid document** → ✅ Green toast: "Document submitted successfully"
- [ ] **Submit without required fields** → ❌ Red toast with validation errors
- [ ] **Upload invalid file (wrong type)** → ❌ Red toast: file error
- [ ] **Upload oversized file** → ❌ Red toast: "File too large"
- [ ] **AJAX: Select document type** → Checklists load, NO toast (data only)
- [ ] **AJAX: Check console** → Walang JavaScript errors

**Toast checks:**
- [ ] Appears exactly once (no duplicates)
- [ ] Top-right position (fixed)
- [ ] Auto-dismiss after ~4.5s
- [ ] Proper color (green=success, red=error)
- [ ] Icon + text visible
- [ ] Message is HTML-escaped

---

### 2. Receiving Inbox - `/receiving/inbox`

- [ ] **Load after accept** → ✅ Green toast: "Document accepted"
- [ ] **Load after update** → ✅ Green toast: "Document updated"
- [ ] **Load with error** → ❌ Red toast with error message
- [ ] **Check page source** → Walang inline flash divs

---

### 3. Returned Document Detail - `/receiving/inbox/show?id={test-id}`

- [ ] **View document detail** → Check kung may flash toast
- [ ] **Return reason visible** → Persistent context (hindi replaced by toast)
- [ ] **Accept button visible** → Kung document is returned/PENDING
- [ ] **Accept document (valid)** → ✅ Redirect to inbox with green toast
- [ ] **Accept already accepted** → ❌ Red toast: "Already accepted"

**Critical**: This page ay may bug if hindi pa na-fix ang controller!

---

### 4. Update Returned Document - `/receiving/inbox/update`

- [ ] **Update with valid data** → ✅ Green toast: "Document updated successfully"
- [ ] **Update without required fields** → ❌ Red toast with errors array
- [ ] **Field-level validation messages** → Visible sa form (not replaced)
- [ ] **Toast appears after redirect** → Sa inbox/show page

---

### 5. Attachments - Upload/Delete

**Upload** (`/receiving/inbox/attachment/upload`):
- [ ] **Valid file upload** → ✅ Green toast: "File uploaded successfully"
- [ ] **Invalid file type** → ❌ Red toast: "Invalid file type"
- [ ] **File too large** → ❌ Red toast: "File too large"
- [ ] **No file selected** → ❌ Red toast: "No file selected"

**Delete** (`/receiving/inbox/attachment/delete`):
- [ ] **Delete attachment** → ✅ Green toast: "Attachment deleted"
- [ ] **Invalid attachment ID** → ❌ Red toast: error message
- [ ] **No permission** → ❌ Red toast: "Permission denied"

---

### 6. Routed Documents (Read-Only)

**List** (`/receiving/routed-documents`):
- [ ] **Page loads** → Any flash toast displays sa header
- [ ] **No inline flash divs** → Check page source

**Detail** (`/receiving/routed-documents/show?id={test-id}`):
- [ ] **View document** → Any flash toast displays
- [ ] **Read-only** → No action buttons

---

## ADMIN WORKFLOWS

### 7. Admin Receive Document - `/admin/receive-document`

- [ ] **Load page** → Check for flash toast
- [ ] **Submit valid document** → ✅ Green toast: "Document received successfully"
- [ ] **Submit without required fields** → ❌ Red toast with errors
- [ ] **Upload invalid file** → ❌ Red toast: validation error
- [ ] **AJAX: Select document type** → Checklists load, no toast
- [ ] **AJAX: Check console** → No errors

---

### 8. Admin Inbox - `/admin/inbox`

- [ ] **Load after process action** → Toast displays action result
- [ ] **Load after upload** → ✅ Green toast: "Files uploaded"
- [ ] **Load with error** → ❌ Red toast with error

---

### 9. Admin Inbox Detail - `/admin/inbox/show?id={test-id}`

- [ ] **View document detail** → Any flash toast displays
- [ ] **PENDING document** → Accept button visible
- [ ] **ACCEPTED document** → Process actions visible (Route/Decline/Noted)
- [ ] **Upload form visible** → When user has permission

---

### 10. Admin Inbox Process - `/admin/inbox/process`

**Accept**:
- [ ] **Accept valid document** → ✅ Green toast: "Document accepted"
- [ ] **Accept already accepted** → ❌ Red toast: error

**Decline/Return**:
- [ ] **Decline with remarks** → ✅ Green toast: "Document declined"
- [ ] **Decline without remarks** → ❌ Red toast: "Remarks required"
- [ ] **Decline reason persists** → Visible sa database/detail page

**Route**:
- [ ] **Route to SP Secretary** → ✅ Green toast: "Document routed"
- [ ] **Route to Plenary** → ✅ Green toast: "Document routed"
- [ ] **Route to Committee (valid)** → ✅ Green toast: "Routed to committee"
- [ ] **Route to Committee (no selection)** → ❌ Red toast: "Committee required"

**Mark as Noted**:
- [ ] **Mark as noted (valid)** → ✅ Green toast: "Marked as noted"
- [ ] **Mark as noted (invalid state)** → ❌ Red toast: error

---

### 11. Admin Inbox Upload - `/admin/inbox/upload`

- [ ] **Upload valid file(s)** → ✅ Green toast: "N file(s) uploaded successfully"
- [ ] **Multiple files** → Toast shows correct count
- [ ] **Invalid file type** → ❌ Red toast with validation errors
- [ ] **File too large** → ❌ Red toast: "File too large"
- [ ] **No file selected** → ❌ Red toast: "No files selected"
- [ ] **No permission** → ❌ Red toast: "No permission"

---

### 12. Admin Routed Documents (Read-Only)

**List** (`/admin/routed`):
- [ ] **Page loads** → Any flash toast displays
- [ ] **Read-only list** → No action buttons

**Detail** (`/admin/routed/show?id={test-id}`):
- [ ] **View document** → Any flash toast displays
- [ ] **Read-only detail** → No actions

---

### 13. Admin Communications (Read-Only)

**List** (`/admin/communications`):
- [ ] **Page loads** → Any flash toast displays
- [ ] **Read-only list** → No actions

**Detail** (`/admin/communications/show?id={test-id}`):
- [ ] **View communication** → Any flash toast displays
- [ ] **Read-only detail** → No actions

---

### 14. Admin Archived Records (OBSERVATION ONLY)

**NOTE**: This module was excluded from toast standardization.  
**Goal**: Observe lang kung gumagana pa, hindi i-standardize.

- [ ] **List page** → Check toast implementation (may be different)
- [ ] **Detail page** → Check toast implementation
- [ ] **Edit page** → Check toast implementation
- [ ] **Create/Update/Delete actions** → Observe feedback mechanism
- [ ] **AJAX delete** → Check if returns JSON with client-side toast

**Expected**: May use inline toast or AJAX toast, NOT header.php pattern.

---

## RESPONSIVE TESTING

Test sa iba't ibang screen widths:

### Desktop (1920x1080)
- [ ] Toast visible
- [ ] Top-right position correct
- [ ] Doesn't cover content
- [ ] Proper size and spacing

### Tablet (768x1024)
- [ ] Toast adapts to width
- [ ] Still readable
- [ ] Position appropriate
- [ ] No overflow

### Mobile (375x667)
- [ ] Toast visible
- [ ] Doesn't obscure content
- [ ] Text readable
- [ ] Can interact with page while toast visible

**How to test**: Browser DevTools → Toggle device toolbar → Select device

---

## ACCESSIBILITY CHECKS

- [ ] **Color contrast** → Sufficient (green/red vs white backgrounds)
- [ ] **Text readability** → Font size OK, proper spacing
- [ ] **Icon + text** → Redundant information (both convey meaning)
- [ ] **Auto-dismiss timing** → Approximately 4.5 seconds
- [ ] **Doesn't block controls** → Can still interact with page
- [ ] **Keyboard navigation** → Can tab through page while toast displays

---

## TOAST VERIFICATION CHECKLIST

Para sa EVERY toast na makita mo:

- [ ] Appears exactly **once** (no duplicates)
- [ ] Appears in **header area** (not inline in page body)
- [ ] Position: **fixed top-right** (inspect: `fixed top-24 right-6`)
- [ ] **Success = green** (emerald colors), **Error = red**
- [ ] Has appropriate **icon** (✓ for success, ✗ for error)
- [ ] **Auto-dismisses** after ~4.5 seconds
- [ ] Message is **HTML-escaped** (view source to confirm)
- [ ] Multiple errors show as **bullet list**
- [ ] Toast doesn't replace **persistent context** (e.g., return reason)

---

## COMMON ISSUES TO WATCH FOR

### 🔴 Critical Issues
- [ ] Toast not appearing at all
- [ ] Flash message lost/consumed too early
- [ ] Duplicate toasts (appearing twice)
- [ ] Toast appears on wrong page

### 🟡 High Priority Issues
- [ ] Wrong toast color (red for success, green for error)
- [ ] Toast position wrong (not top-right)
- [ ] Toast doesn't auto-dismiss
- [ ] Message not escaped (XSS risk)

### 🟢 Medium Priority Issues
- [ ] Toast timing too fast/slow
- [ ] Toast covers essential controls
- [ ] Responsive issues on mobile
- [ ] Missing icon

### 🔵 Low Priority Issues
- [ ] Toast animation not smooth
- [ ] Color/styling inconsistent
- [ ] Text spacing/alignment
- [ ] Minor UX improvements

---

## TEST DATA CLEANUP

After testing, i-clean up ang test records:

- [ ] Delete test documents created
- [ ] Remove test attachments uploaded
- [ ] Reset test document states
- [ ] Clear any test assignments
- [ ] Verify database back to clean state

**Command** (kung may access sa DB):
```sql
-- List test documents
SELECT * FROM documents WHERE tracking_number LIKE 'TEST-%' ORDER BY created_at DESC;

-- Delete if needed (CAUTION!)
-- DELETE FROM documents WHERE tracking_number LIKE 'TEST-%';
```

---

## FINAL VERIFICATION

- [ ] All Receiving routes tested
- [ ] All Admin routes tested
- [ ] AJAX endpoints verified
- [ ] Responsive behavior confirmed
- [ ] Accessibility checked
- [ ] No console errors observed
- [ ] Test data cleaned up
- [ ] Results documented

---

## RESULTS SUMMARY

Fill out after testing:

**Date Tested**: _______________  
**Tester Name**: _______________  
**Browser(s)**: _______________  

**Pass Rate**:
- Receiving: ___ of ___ tests passed (___%)
- Admin: ___ of ___ tests passed (___%)
- Overall: ___ of ___ tests passed (___%)

**Critical Failures**: ___  
**High Priority Issues**: ___  
**Medium Priority Issues**: ___  
**Low Priority Issues**: ___

**Deployment Recommendation**: 
- [ ] ✅ Ready to deploy (all critical tests pass)
- [ ] ⚠️ Deploy with cautions (minor issues only)
- [ ] ❌ Do not deploy (critical issues exist)

---

## NEXT STEPS

After QA:

1. [ ] Document all failures in detail
2. [ ] Create fix tasks for issues found
3. [ ] Prioritize fixes (critical → high → medium → low)
4. [ ] Apply fixes
5. [ ] Re-test failed scenarios
6. [ ] Final sign-off and deployment

---

**QA Checklist Version**: 1.0  
**Last Updated**: 2026-10-07  
**Status**: Ready for manual execution (after critical bug fix)

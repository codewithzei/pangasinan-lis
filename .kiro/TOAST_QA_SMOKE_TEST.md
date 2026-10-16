# Toast Notification - Smoke Test Script

**Purpose**: Quick verification that the critical bug fix works  
**Time Required**: 5-10 minutes  
**Prerequisites**: Laragon running, browser open, logged in as Receiving user

---

## Smoke Test Checklist

### Test 1: Accept Returned Document
**Route**: `/receiving/inbox/show?id={test-id}` → Accept button

**Steps**:
1. Navigate to `/receiving/inbox`
2. Find a returned document (status: RETURNED/PENDING)
3. Click on the document to view details
4. Click "Accept" button
5. Observe redirect to `/receiving/inbox`

**Expected Result**:
- ✅ Green toast appears: "Document accepted successfully"
- ✅ Toast appears in top-right corner
- ✅ Toast appears exactly once (no duplicates)
- ✅ Toast auto-dismisses after ~4.5 seconds

**Test Status**: [ ] PASS / [ ] FAIL

**Notes**: _______________________________________________

---

### Test 2: Update Returned Document (Success)
**Route**: `/receiving/inbox/show?id={test-id}` → Update form

**Steps**:
1. Navigate to accepted returned document detail
2. Fill out the update form with valid data
3. Click "Submit" or "Update" button
4. Observe redirect

**Expected Result**:
- ✅ Green toast appears: "Document updated successfully"
- ✅ Toast appears in header (not inline)
- ✅ No duplicate toasts

**Test Status**: [ ] PASS / [ ] FAIL

**Notes**: _______________________________________________

---

### Test 3: Update with Validation Errors
**Route**: `/receiving/inbox/show?id={test-id}` → Update form

**Steps**:
1. Navigate to accepted returned document detail
2. Clear a required field in the update form
3. Click "Submit" button
4. Observe redirect

**Expected Result**:
- ❌ Red toast appears with validation error list
- ✅ Toast shows bullets for multiple errors
- ✅ Field-level validation messages also visible in form
- ✅ Toast doesn't replace form guidance

**Test Status**: [ ] PASS / [ ] FAIL

**Notes**: _______________________________________________

---

### Test 4: View Document Detail (Persistent Context)
**Route**: `/receiving/inbox/show?id={test-id}`

**Steps**:
1. Find a document that was returned with a reason
2. View the document detail page
3. Check the page content

**Expected Result**:
- ✅ Return reason is visible on the page (persistent)
- ✅ If there's a flash message, it shows as toast
- ✅ Return reason is NOT replaced by toast
- ✅ Both context and feedback are available

**Test Status**: [ ] PASS / [ ] FAIL

**Notes**: _______________________________________________

---

### Test 5: Console Check (No JavaScript Errors)

**Steps**:
1. Open DevTools (F12)
2. Go to Console tab
3. Perform any of the above tests
4. Check for errors

**Expected Result**:
- ✅ No JavaScript errors in console
- ✅ No uncaught exceptions
- ✅ Toast animations work smoothly

**Test Status**: [ ] PASS / [ ] FAIL

**Notes**: _______________________________________________

---

## Smoke Test Results

**Date**: _______________  
**Tester**: _______________  
**Browser**: _______________

**Overall Status**:
- [ ] ✅ ALL PASS - Proceed with full QA
- [ ] ⚠️ SOME FAIL - Investigate and fix before full QA
- [ ] ❌ CRITICAL FAIL - Bug fix did not work, need to debug

**Pass Rate**: ___ of 5 tests passed (___%)

---

## If Smoke Test Passes

✅ Critical bug is fixed  
✅ Toast notifications working  
✅ Ready for comprehensive QA  

**Next Step**: Execute full test plan using **TOAST_QA_MANUAL_CHECKLIST.md**

---

## If Smoke Test Fails

Document the failure:

**Failed Test ID**: _______________  
**Expected Behavior**: _______________  
**Actual Behavior**: _______________  
**Error Messages**: _______________  
**Screenshot/Console Output**: _______________

**Action Required**: Debug the issue before proceeding with full QA

---

**Smoke Test Version**: 1.0  
**Last Updated**: 2026-10-07

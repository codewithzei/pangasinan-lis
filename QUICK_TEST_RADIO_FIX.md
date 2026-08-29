# Quick Test Guide: Radio Button Pre-selection Fix

## 🎯 Quick Verification (2 minutes)

### Test 1: Required Document Type ✅
1. Go to: **Master > Checklists > [Any Checklist]**
2. Find a document type with **amber "Required"** badge
3. Click the **toggle icon** (warning triangle)
4. **✅ VERIFY:** "Required" radio button is selected
5. **✅ VERIFY:** Message shows "Current status: Required"

### Test 2: Optional Document Type ✅
1. Find a document type with **gray "Optional"** badge
2. Click the **toggle icon**
3. **✅ VERIFY:** "Optional" radio button is selected
4. **✅ VERIFY:** Message shows "Current status: Optional"

### Test 3: Status Change Works ✅
1. Open modal for a "Required" document type
2. Select "Optional" radio button
3. Click "Update Status"
4. **✅ VERIFY:** Success toast appears
5. **✅ VERIFY:** Badge changes to gray "Optional"
6. Refresh page
7. **✅ VERIFY:** Badge still shows "Optional"

### Test 4: Toggle Back ✅
1. Open modal again (same document type)
2. **✅ VERIFY:** "Optional" radio is now selected
3. Select "Required" radio button
4. Click "Update Status"
5. **✅ VERIFY:** Badge changes back to amber "Required"

---

## 🐛 If You See This = BUG NOT FIXED

❌ **Problem:** Modal opens showing "Optional" selected for a "Required" document type
- **Action:** Check if changes were deployed correctly
- **Check:** Browser console for JavaScript errors

❌ **Problem:** Clicking "Update Status" doesn't change the status
- **Action:** Check network tab for failed requests
- **Check:** Server logs for PHP errors

❌ **Problem:** Badge doesn't update after page reload
- **Action:** Check database `is_required` column
- **Check:** Clear browser cache

---

## ✅ Success = All These Pass

- ✅ Required document types show "Required" radio selected
- ✅ Optional document types show "Optional" radio selected
- ✅ Status changes persist after page reload
- ✅ Modal message matches current status
- ✅ No JavaScript console errors
- ✅ No PHP errors in logs

---

## 📱 Quick Mobile Test

1. Open browser DevTools (F12)
2. Toggle device toolbar (Ctrl+Shift+M)
3. Select iPhone SE or iPad
4. Repeat Test 1 and Test 2 above
5. **✅ VERIFY:** Radio buttons easy to tap
6. **✅ VERIFY:** Modal fits on screen

---

## 🔍 Technical Verification

### Check JavaScript (Browser Console):
```javascript
// Open modal and run this in console:
document.querySelectorAll('input[name="status_choice"]').forEach(r => {
    console.log('Radio value:', r.value, 'Checked:', r.checked);
});
// Should show checked=true for current status
```

### Check POST Request (Network Tab):
1. Open Network tab in DevTools
2. Click "Update Status" in modal
3. Find the request to `update-required`
4. Check Form Data:
   - **✅ Should see:** `id: <number>`
   - **✅ Should see:** `is_required: 0 or 1`

---

## Expected Results Summary

| Current Badge | Radio Selected | Message Shown | After Change |
|---------------|----------------|---------------|--------------|
| Required (amber) | Required | "Current status: Required" | Can change to Optional |
| Optional (gray) | Optional | "Current status: Optional" | Can change to Required |

---

## 🎉 All Tests Pass?

**Congratulations!** The radio button pre-selection bug is fixed! ✅

**Status:** Ready for production deployment

---

## ❓ Quick Troubleshooting

| Issue | Solution |
|-------|----------|
| Modal doesn't open | Refresh page, check console errors |
| Wrong radio selected | Clear browser cache, verify fix deployed |
| Status doesn't change | Check network tab, verify server running |
| Page doesn't reload | Check for JavaScript errors |

---

## Need More Details?

See: `FIX_RADIO_BUTTON_PRESELECTION.md` for complete technical documentation

---

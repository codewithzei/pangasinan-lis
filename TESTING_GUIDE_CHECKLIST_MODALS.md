# Testing Guide: Checklist Modals

## Quick Start Testing Guide

This guide will help you test the new interactive modals for the Checklist document-type management feature.

---

## Prerequisites

1. **Access the application:**
   - Navigate to: `http://your-domain/master/checklists`
   - Login with appropriate credentials (requires Master module access)

2. **Test Data Required:**
   - At least one active checklist
   - At least 2-3 document types assigned to a checklist

---

## Test 1: Status Toggle Modal (Required ↔ Optional)

### Steps:

1. **Navigate to Checklist Details**
   - Go to Master > Checklists
   - Click on any checklist name to view details

2. **Locate Assigned Document Types**
   - Scroll to the "Assigned Document Types" section on the right
   - You should see document types with either "Required" (amber badge) or "Optional" (gray badge)

3. **Open Status Modal**
   - Click the **toggle icon** button (warning triangle or hand icon) next to any document type
   - ✅ **Expected:** Status modal should open smoothly with animation
   - ✅ **Expected:** Modal should show:
     - Document type name in bold
     - Current status (e.g., "Current status: Required")
     - Two radio options (Required and Optional)
     - The opposite status should be pre-selected

4. **Change Selection (Optional)**
   - Try clicking the other radio button
   - ✅ **Expected:** Radio button switches correctly

5. **Cancel Action**
   - Click "Cancel" button
   - ✅ **Expected:** Modal closes smoothly, no changes made
   - ✅ **Expected:** Page content unchanged

6. **Reopen and Confirm**
   - Click the toggle icon again
   - Click "Update Status" button
   - ✅ **Expected:** Modal closes
   - ✅ **Expected:** Green success toast appears (top right)
   - ✅ **Expected:** Page reloads within 1 second
   - ✅ **Expected:** Document type badge changes color/text (Required ↔ Optional)

7. **Verify Toggle Works Both Ways**
   - Click the same document type's toggle icon again
   - ✅ **Expected:** Modal opens with the NEW current status
   - ✅ **Expected:** Opposite status pre-selected
   - Update again to verify it toggles back

### Edge Cases to Test:

- **Backdrop Click:** Click outside the modal (on the dark backdrop)
  - ✅ **Expected:** Modal should close
  
- **Rapid Clicking:** Click toggle icon multiple times quickly
  - ✅ **Expected:** Only one modal opens (no duplicates)

- **Network Error Simulation:** 
  - Disconnect internet or stop the server
  - Try to update status
  - ✅ **Expected:** Red error toast appears with error message

---

## Test 2: Remove Document Type Confirmation Modal

### Steps:

1. **Open Remove Confirmation**
   - On the Checklist details page
   - Find any assigned document type
   - Click the **X (remove) button** (red X icon)
   - ✅ **Expected:** Confirmation modal opens with red danger theme
   - ✅ **Expected:** Modal shows:
     - Title: "Remove Document Type"
     - Document type name in bold
     - Warning message explaining the action
     - Note that it can be re-added later

2. **Cancel Removal**
   - Click "Cancel" button
   - ✅ **Expected:** Modal closes, no changes made

3. **Confirm Removal**
   - Click the remove (X) button again
   - Click "Remove" button (red button)
   - ✅ **Expected:** Modal closes
   - ✅ **Expected:** Green success toast appears
   - ✅ **Expected:** Page reloads
   - ✅ **Expected:** Document type no longer in "Assigned" list
   - ✅ **Expected:** Document type appears in "Available to Assign" list (left panel)

4. **Re-assign Removed Document Type**
   - In the left panel, find the document type you just removed
   - Check its checkbox
   - Click "Assign to Checklist" button
   - ✅ **Expected:** Document type re-appears in assigned list

### Edge Cases to Test:

- **Backdrop Click:** Click outside the modal
  - ✅ **Expected:** Modal closes without removing
  
- **Special Characters in Name:** Test with document types that have special characters or quotes
  - ✅ **Expected:** Name displays correctly without breaking HTML

- **Last Document Type:** Remove all document types from a checklist
  - ✅ **Expected:** Empty state message appears in assigned list

---

## Test 3: Mobile Responsiveness

### Steps:

1. **Open Browser DevTools**
   - Press F12 or right-click > Inspect
   - Toggle device toolbar (Ctrl+Shift+M or Cmd+Shift+M)

2. **Test on Various Screen Sizes:**
   - **iPhone SE (375x667)**
     - ✅ Modal fits within screen
     - ✅ Text is readable
     - ✅ Buttons are touch-friendly
   
   - **iPad (768x1024)**
     - ✅ Modal centered properly
     - ✅ Spacing looks appropriate
   
   - **Desktop (1920x1080)**
     - ✅ Modal doesn't get too large
     - ✅ Content well-centered

3. **Test Touch Interactions**
   - Use device emulation touch mode
   - ✅ Radio buttons easy to tap
   - ✅ Buttons have adequate touch target size
   - ✅ Backdrop click works

---

## Test 4: Accessibility

### Steps:

1. **Keyboard Navigation**
   - Use Tab key to navigate
   - ✅ **Expected:** Can tab through radio buttons and buttons
   - ✅ **Expected:** Focus indicators visible
   
2. **ARIA Attributes**
   - Inspect modal in DevTools
   - ✅ **Expected:** `role="dialog"` present
   - ✅ **Expected:** `aria-modal="true"` present

---

## Test 5: Browser Compatibility

Test in multiple browsers:

- [ ] Chrome/Edge (latest)
- [ ] Firefox (latest)
- [ ] Safari (if available)

✅ **Expected for all:** Modals work identically with smooth animations

---

## Test 6: Integration with Existing Features

### Steps:

1. **Assign New Document Types**
   - Use left panel to assign document types
   - ✅ **Expected:** Newly assigned types appear with "Required" status by default
   - ✅ **Expected:** Toggle and remove work on newly assigned types

2. **Page Navigation**
   - Navigate away and back to checklist
   - ✅ **Expected:** Changes persist

3. **Multiple Checklists**
   - Test modals on different checklists
   - ✅ **Expected:** Each checklist maintains its own assignments

---

## Common Issues and Troubleshooting

### Issue: Modal doesn't open
- **Check:** Browser console for JavaScript errors
- **Check:** Ensure page loaded completely
- **Solution:** Refresh page and try again

### Issue: Modal doesn't close
- **Check:** Click backdrop or Cancel button
- **Solution:** Refresh page if stuck

### Issue: Changes don't persist
- **Check:** Network tab in DevTools for failed requests
- **Check:** Server logs for errors
- **Solution:** Verify database connection and permissions

### Issue: Modal animation glitchy
- **Check:** Browser GPU acceleration settings
- **Solution:** Try different browser or update graphics drivers

---

## Performance Testing

### Quick Performance Checks:

1. **Animation Smoothness**
   - Open/close modals multiple times
   - ✅ **Expected:** Smooth 60fps animation

2. **Response Time**
   - Time from button click to server response
   - ✅ **Expected:** < 500ms for status update
   - ✅ **Expected:** < 500ms for removal

3. **Page Reload Speed**
   - After successful action
   - ✅ **Expected:** < 2 seconds

---

## Security Testing (Optional)

### Steps:

1. **SQL Injection Test**
   - Try injecting SQL in document type names
   - ✅ **Expected:** Properly escaped/sanitized

2. **XSS Test**
   - Try adding `<script>alert('XSS')</script>` in document type name
   - ✅ **Expected:** Displayed as plain text, not executed

3. **CSRF Protection**
   - Verify CSRF tokens if implemented
   - ✅ **Expected:** Unauthorized requests blocked

---

## Test Results Template

Use this template to document your test results:

```
Date: [Date]
Tester: [Your Name]
Browser: [Browser Name + Version]
Device: [Desktop/Mobile/Tablet]

STATUS TOGGLE MODAL:
- Opens correctly: [ ] Pass [ ] Fail
- Pre-selection works: [ ] Pass [ ] Fail
- Update successful: [ ] Pass [ ] Fail
- Toast notification: [ ] Pass [ ] Fail
- Mobile responsive: [ ] Pass [ ] Fail

REMOVE CONFIRMATION MODAL:
- Opens correctly: [ ] Pass [ ] Fail
- Confirmation works: [ ] Pass [ ] Fail
- Removal successful: [ ] Pass [ ] Fail
- Re-assign works: [ ] Pass [ ] Fail
- Mobile responsive: [ ] Pass [ ] Fail

ACCESSIBILITY:
- Keyboard navigation: [ ] Pass [ ] Fail
- ARIA attributes: [ ] Pass [ ] Fail

NOTES:
[Any issues or observations]
```

---

## Success Criteria

All tests should pass with the following criteria:

✅ No JavaScript console errors
✅ Smooth animations (no jank)
✅ All buttons functional
✅ Data persists after reload
✅ Responsive on all screen sizes
✅ Toast notifications appear correctly
✅ Server responses within 500ms
✅ No visual glitches or layout breaks

---

## Need Help?

If you encounter issues:
1. Check browser console for errors
2. Review server logs
3. Verify database connectivity
4. Check `CHECKLIST_MODALS_IMPLEMENTATION.md` for technical details
5. Contact development team

---

## Congratulations!

If all tests pass, the modal implementation is working correctly and ready for production use! 🎉

# Committee Module - Quick Testing Guide

## Prerequisites
1. Database migration completed (table `committees` exists)
2. Database seeded with CommitteeSeeder
3. User logged in with appropriate role/permissions
4. Server running (Laragon or similar)

## Quick Test Steps

### 1. Access the Module (30 seconds)
```
URL: http://localhost/master/committees
```

**Expected:**
- Page loads successfully
- Header shows "Committees Management"
- Three stat cards display (Total, Active, Inactive)
- Table shows 19 seeded committees
- "New Committee" button visible in header

---

### 2. Create New Committee (1 minute)

**Steps:**
1. Click "New Committee" button
2. Modal opens with form
3. Fill in:
   - Name: "Committee on Testing"
   - Description: "Test committee for QA"
   - Sort Order: 99
   - Keep "Set as active" checked
4. Click "Save"

**Expected:**
- Green success toast appears: "Committee 'Committee on Testing' created successfully."
- Page refreshes
- New committee appears in table
- Total count increases by 1

**Test Validation:**
- Try submitting empty name → Should show error
- Try name with 51+ characters → Should show error
- Try negative sort order → Should show error

---

### 3. Edit Committee (1 minute)

**Steps:**
1. Find "Committee on Testing" in table
2. Click blue Edit icon (pencil)
3. Modal opens with pre-filled data
4. Change:
   - Name: "Committee on Quality Assurance"
   - Description: "Updated test committee"
5. Click "Update"

**Expected:**
- Green success toast: "Committee 'Committee on Quality Assurance' updated successfully."
- Page refreshes
- Changes reflected in table

---

### 4. Search Functionality (30 seconds)

**Steps:**
1. Type "Agriculture" in search box
2. Click "Filter"

**Expected:**
- Only "Committee on Agriculture" shows
- Record count shows "1 record found"

**Clear:**
- Click "Clear" button
- All committees show again

---

### 5. Status Filter (30 seconds)

**Steps:**
1. Select "Active" from status dropdown
2. Click "Filter"

**Expected:**
- Only active committees shown
- Active count matches filtered results

---

### 6. Toggle Status (45 seconds)

**Steps:**
1. Find "Committee on Quality Assurance"
2. Click green toggle icon (checkmark/cross)
3. Confirmation modal appears
4. Click "Proceed"

**Expected:**
- Success toast appears
- Page refreshes
- Status badge changes from "Active" to "Inactive" (or vice versa)
- Active/Inactive count updates

---

### 7. Delete Committee (45 seconds)

**Steps:**
1. Find "Committee on Quality Assurance"
2. Click red Delete icon (trash)
3. Confirmation modal appears with warning
4. Click "Delete"

**Expected:**
- Red confirmation modal with warning text
- Success toast: "Committee 'Committee on Quality Assurance' deleted successfully."
- Page refreshes
- Committee removed from list
- Total count decreases by 1

---

### 8. Pagination Test (30 seconds)
*(Only if you have more than 10 committees)*

**Steps:**
1. Create multiple test committees if needed
2. Check bottom of table for pagination

**Expected:**
- "Showing page X of Y" text
- "Prev" and "Next" buttons work
- Page numbers clickable
- 10 records per page

---

### 9. Responsive Design (30 seconds)

**Steps:**
1. Resize browser to mobile width (< 640px)
2. Check layout

**Expected:**
- Layout adjusts to mobile view
- Stat cards stack vertically
- Table scrolls horizontally if needed
- Modals fit mobile screen
- Buttons remain accessible

---

### 10. Validation Tests (2 minutes)

#### Empty Name
1. Open "New Committee"
2. Leave name empty
3. Click "Save"
4. **Expected:** Red border on name field, error message

#### Long Name
1. Open "New Committee"
2. Enter 51+ characters in name
3. Click "Save"
4. **Expected:** Error: "Committee name must not exceed 50 characters."

#### Duplicate Name
1. Open "New Committee"
2. Enter "Committee on Agriculture" (existing)
3. Click "Save"
4. **Expected:** Error: "A committee with the name 'Committee on Agriculture' already exists."

#### Invalid Sort Order
1. Open "New Committee"
2. Enter -5 in sort order
3. Tab out of field
4. **Expected:** Red border, error message

---

## Console Check

**Open browser console (F12) during testing:**
- Should see NO JavaScript errors
- Network tab should show successful requests (200 status)
- No 404 or 500 errors

---

## Database Verification

**Optional - Check database directly:**

```sql
-- View all committees
SELECT * FROM committees WHERE is_deleted = 0;

-- Check audit logs
SELECT * FROM audit_logs WHERE entity_type = 'Committee' ORDER BY created_at DESC LIMIT 10;

-- Check system logs
SELECT * FROM system_logs WHERE message LIKE '%Committee%' ORDER BY created_at DESC LIMIT 10;
```

---

## Common Issues & Solutions

### Issue: Page not found (404)
**Solution:** 
- Verify routes added to `routes/web.php`
- Clear any route cache
- Check web server configuration

### Issue: Database error
**Solution:**
- Run migration: `php migrate.php`
- Run seeder: Check DatabaseSeeder includes CommitteeSeeder
- Verify database connection in `.env`

### Issue: Modal not opening
**Solution:**
- Check browser console for JavaScript errors
- Verify JavaScript at bottom of index.php loaded
- Clear browser cache

### Issue: Validation not working
**Solution:**
- Check `validateCommittee()` method in controller
- Verify JavaScript validation functions loaded
- Test with network tab open to see server response

### Issue: Flash messages not showing
**Solution:**
- Verify session started
- Check flash helper functions in `app/config/app.php`
- Check toast JavaScript at bottom of view

---

## Expected Test Duration

| Test | Time |
|------|------|
| Basic Navigation | 30 sec |
| Create Committee | 1 min |
| Edit Committee | 1 min |
| Search & Filter | 1 min |
| Toggle Status | 45 sec |
| Delete Committee | 45 sec |
| Validation Tests | 2 min |
| Responsive Check | 30 sec |
| **Total** | **~7 minutes** |

---

## Test Completion Checklist

- [ ] All 19 seeded committees visible
- [ ] Create new committee works
- [ ] Edit committee works
- [ ] Delete committee works (soft delete)
- [ ] Toggle status works
- [ ] Search works
- [ ] Filter by status works
- [ ] Pagination works (if applicable)
- [ ] Validation prevents invalid data
- [ ] Flash messages appear correctly
- [ ] Modals open and close smoothly
- [ ] No JavaScript console errors
- [ ] No PHP errors
- [ ] Mobile responsive design works
- [ ] Audit logs created for actions

---

## Success Criteria

✅ **All CRUD operations work**
✅ **No errors in browser console**
✅ **No PHP errors in server logs**
✅ **UI matches other Master modules (Districts, Positions, etc.)**
✅ **Validation prevents bad data**
✅ **Flash messages provide feedback**
✅ **Audit logs track all changes**

---

**Ready to test!** 🚀

Start with Step 1 and work through sequentially. Each test should take less than 1 minute except validation tests which are more thorough.

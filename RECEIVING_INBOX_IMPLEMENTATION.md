# Receiving Inbox Implementation Summary

## Overview
Successfully implemented the Receiving Inbox workflow for managing documents returned by Admin to Receiving Staff in the Pangasinan LIS application.

## Implementation Date
Completed: 2026-09-11

---

## Features Implemented

### 1. Navigation Tabs
- **Returned**: Shows documents returned by Admin awaiting correction
- **Accepted**: Shows documents corrected and re-routed to Admin

### 2. Returned Documents View
Documents appear in the "Returned" tab when:
- `assigned_to_role_id = {receiving_role_id}`
- `phase = 'RECEIVING'`
- `decision = 'PENDING'`
- `completed_at IS NULL`
- Admin previously declined the document (verified via EXISTS subquery)

### 3. Accepted Documents View
Documents appear in the "Accepted" tab when:
- `assigned_to_role_id = {receiving_role_id}`
- `phase = 'RECEIVING'`
- `decision IN ('ACCEPTED', 'COMPLETED')`
- `completed_at IS NOT NULL`
- Admin previously declined the document (verified via EXISTS subquery)

---

## Database Conditions

### Returned Documents Query
```sql
SELECT ...
FROM document_assignments da
WHERE da.assigned_to_role_id = ? 
  AND da.phase = 'RECEIVING'
  AND da.decision = 'PENDING'
  AND da.completed_at IS NULL
  AND EXISTS (
      SELECT 1 FROM document_assignments da_admin
      WHERE da_admin.document_id = da.document_id
        AND da_admin.phase = 'ADMIN'
        AND da_admin.decision = 'DECLINED'
        AND da_admin.declined_at IS NOT NULL
  )
```

### Accepted Documents Query
```sql
SELECT ...
FROM document_assignments da
WHERE da.assigned_to_role_id = ?
  AND da.phase = 'RECEIVING'
  AND da.decision IN ('ACCEPTED', 'COMPLETED')
  AND da.completed_at IS NOT NULL
  AND EXISTS (
      SELECT 1 FROM document_assignments da_admin
      WHERE da_admin.document_id = da.document_id
        AND da_admin.phase = 'ADMIN'
        AND da_admin.decision = 'DECLINED'
  )
```

---

## Return Reason Display

The return reason is retrieved from the Admin decline event and displayed in multiple locations:

### 1. Returned Table Column
- Column: "Return Reason"
- Source: `document_assignments.decline_reason` (from Admin assignment with `decision='DECLINED'`)
- Display: Visible directly in the table (not hidden in tooltip)
- Fallback: "No reason provided" if decline_reason is NULL

### 2. Document Edit Page Alert
- Prominent red alert box at top of edit page
- Shows: decline_reason, declined_by name, and declined_at timestamp
- Retrieved via LEFT JOIN to most recent Admin declined assignment

### Selection Logic
```sql
LEFT JOIN (
    SELECT document_id, decline_reason, declined_at, assigned_by
    FROM document_assignments
    WHERE phase = 'ADMIN' AND decision = 'DECLINED'
    ORDER BY declined_at DESC
) da_admin ON da_admin.document_id = da.document_id
```

---

## Edit and Route-Back Workflow

### Process Steps

1. **Receiving Staff views returned document**
   - Navigate to Receiving Inbox → Returned tab
   - Click "Edit" button on returned document

2. **Edit document details**
   - View return reason in prominent alert
   - Edit all document fields (date, time, subject, document type, source details)
   - All original data pre-populated in form

3. **Submit corrected document**
   - Click "Save & Route to Admin" button
   - Transaction begins

4. **Transaction Processing** (All or Nothing)
   - **Step 1**: Fetch current document state
   - **Step 2**: Verify Receiving assignment is still PENDING (optimistic lock)
   - **Step 3**: Resolve source name if SP Member selected
   - **Step 4**: Create document revision (snapshot before changes)
   - **Step 5**: Update document with new values
   - **Step 6**: Mark Receiving assignment as COMPLETED with atomic WHERE clause:
     ```sql
     UPDATE document_assignments
     SET decision = 'COMPLETED', completed_at = NOW()
     WHERE id = ? AND decision = 'PENDING' AND completed_at IS NULL
     ```
     Race detection: Throws error if rowCount() = 0
   - **Step 7**: Get Admin role ID
   - **Step 8**: Check for existing pending Admin assignment (duplicate prevention)
   - **Step 9**: Create new Admin assignment:
     ```sql
     INSERT INTO document_assignments (
         document_id, assigned_to_role_id, phase, 
         assigned_by, decision, received_at
     ) VALUES (?, ?, 'ADMIN', ?, 'PENDING', NOW())
     ```
   - **Step 10**: Update document phase to ADMIN, clear current_owner_user_id
   - **Step 11**: Insert route record (RECEIVING → ADMIN)
   - **Step 12**: Insert workflow event: DOCUMENT_EDITED (phase=RECEIVING)
   - **Step 13**: Insert workflow event: ROUTED_TO_ADMIN (phase=ADMIN)
   - **Step 14**: COMMIT transaction
   - **Step 15**: Send notifications to Admin users (AFTER commit)
   - **Step 16**: Log audit and system events

5. **Result**
   - Document appears in Admin Inbox with PENDING status
   - Document removed from Receiving Inbox → Returned tab
   - Document appears in Receiving Inbox → Accepted tab
   - Admin users receive notification
   - Success message displayed

### Rollback Handling
If ANY step fails before commit:
- Entire transaction is rolled back
- No partial updates occur
- Error message shown to user
- System log entry created

---

## Required Table Columns

### Returned Tab Table Columns
1. **Tracking Number**: Hyperlink to edit page
2. **Subject Matter**: Line-clamped to 2 lines
3. **Document Type**: Badge with color from database
4. **Current Status**: Badge with color from database
5. **Current Phase**: Blue badge (should show "Receiving")
6. **Date Returned**: Admin declined_at timestamp
7. **Returned By**: Admin user who declined (name or username)
8. **Return Reason**: decline_reason text (visible, not hidden)
9. **Action**: "Edit" button

### Accepted Tab Table Columns
1. **Tracking Number**: Hyperlink to view page
2. **Subject Matter**: Line-clamped to 2 lines
3. **Document Type**: Badge with color from database
4. **Current Status**: Badge with color from database
5. **Current Phase**: Blue badge (should show "Admin" after re-routing)
6. **Date Accepted**: Receiving assignment completed_at timestamp
7. **Accepted By**: User who completed the Receiving assignment
8. **Action**: "View" button

---

## Transaction Safety Features

### 1. Optimistic Locking
```sql
UPDATE document_assignments
SET decision = 'COMPLETED', completed_at = NOW()
WHERE id = ? 
  AND decision = 'PENDING' 
  AND completed_at IS NULL
```
- Checks rowCount() after update
- Throws exception if already processed by concurrent request

### 2. Duplicate Prevention
```sql
SELECT id FROM document_assignments
WHERE document_id = ?
  AND assigned_to_role_id = ?
  AND phase = 'ADMIN'
  AND decision = 'PENDING'
  AND completed_at IS NULL
```
- Checks for existing pending Admin assignment
- Prevents double-click from creating duplicate assignments

### 3. Notification After Commit
```php
try {
    $this->pdo->beginTransaction();
    // ... all database operations ...
    $this->pdo->commit();
    
    // Notifications sent ONLY after successful commit
    $this->notifyAdminUsers(...);
} catch (Throwable $e) {
    $this->pdo->rollBack();
    // Notifications never sent on failure
}
```
- Prevents lock wait timeouts
- Ensures notifications only sent for successful operations

### 4. Short Transaction Duration
- No file uploads inside transaction
- No external API calls inside transaction
- No email sending inside transaction
- Only database operations included

---

## UI Design Elements

### Matching Admin Inbox Design
- **Gradient Header**: `bg-gradient-to-br from-blue-800 via-primary to-indigo-700`
- **Statistics Cards**: 2-column grid with icon badges
- **Navigation Tabs**: Border-bottom style with badge counts
- **Table Layout**: Rounded-2xl cards, responsive design
- **Badge Colors**: Dynamic from database (`badge_color` columns)
- **Empty States**: Icon + message + description
- **Pagination**: Previous/Next buttons with page numbers

### Color Scheme
- **Returned Documents**: Red badges/icons (indicates urgency)
- **Accepted Documents**: Green badges/icons (indicates completion)
- **Primary Actions**: Blue primary color
- **Phase Badges**: Blue-50 background

### Responsive Behavior
- Mobile: Single column, stacked elements
- Tablet: 2-column grids
- Desktop: Full table layout with all columns

---

## Files Created/Modified

### Controllers
- **Created**: `app/controllers/Receiving/ReceivingInboxController.php`
  - `index()`: List returned and accepted documents
  - `show()`: Display document detail with decline reason
  - `update()`: Edit document and route back to Admin
  - `requireReceivingRoleId()`: Helper to get role ID
  - `validateUpdate()`: Form validation
  - `notifyAdminUsers()`: Send notifications after commit

### Views
- **Created**: `resources/views/receiving/inbox/index.php`
  - Navigation tabs (Returned/Accepted)
  - Statistics cards
  - Search form
  - Responsive table
  - Pagination
  - Empty states

- **Created**: `resources/views/receiving/inbox/show.php`
  - Return reason alert
  - Document info card
  - Edit form (all fields)
  - Existing attachments display
  - Action buttons
  - Double-submit prevention

### Routes
- **Modified**: `routes/web.php`
  - Added: `receiving/inbox` (GET → index)
  - Added: `receiving/inbox/show` (GET → show)
  - Added: `receiving/inbox/update` (POST → update)
  - All routes use AuthMiddleware and RoleMiddleware

---

## Workflow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                      Admin Declines Document                     │
│                    (decline_reason stored)                       │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│          Receiving Inbox → Returned Tab                          │
│   • Shows pending RECEIVING assignments                          │
│   • Verified that Admin previously declined                      │
│   • Return reason visible in table                               │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│      Receiving Staff Clicks "Edit" Button                        │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│           Document Edit Page (show.php)                          │
│   • Prominent return reason alert displayed                      │
│   • All document fields editable                                 │
│   • Original values pre-populated                                │
│   • Existing attachments shown                                   │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│    Receiving Staff Edits and Clicks "Save & Route to Admin"     │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│              Transaction Processing (update())                   │
│   1. Verify assignment still PENDING (optimistic lock)           │
│   2. Create revision snapshot                                    │
│   3. Update document with new values                             │
│   4. Mark Receiving assignment COMPLETED (atomic)                │
│   5. Check for duplicate Admin assignment                        │
│   6. Create new PENDING Admin assignment                         │
│   7. Update document phase to ADMIN                              │
│   8. Insert route record (RECEIVING → ADMIN)                     │
│   9. Insert workflow events (EDITED + ROUTED_TO_ADMIN)           │
│   10. COMMIT all changes                                         │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│          Notifications Sent (After Commit)                       │
│   • Notify all Admin users                                       │
│   • Create notification records                                  │
│   • Log audit events                                             │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                       Final State                                │
│   • Document appears in Admin Inbox (PENDING)                    │
│   • Document removed from Receiving → Returned                   │
│   • Document appears in Receiving → Accepted                     │
│   • Success message displayed                                    │
└─────────────────────────────────────────────────────────────────┘
```

---

## Testing Checklist

### ✓ Completed Pre-Testing
- [x] PHP syntax validation (all files pass)
- [x] Route registration verified
- [x] Controller methods implemented
- [x] Views created with proper structure
- [x] Transaction safety implemented
- [x] Optimistic locking implemented
- [x] Duplicate prevention implemented
- [x] Notification after commit pattern implemented

### Manual Testing Required

#### Scenario 1: Admin Returns Document
- [ ] Admin declines a document with a reason
- [ ] Verify document appears in Receiving Inbox → Returned tab
- [ ] Verify return reason is visible in table
- [ ] Verify declined_at timestamp is correct
- [ ] Verify declined_by name is displayed

#### Scenario 2: Edit Returned Document
- [ ] Click "Edit" on returned document
- [ ] Verify return reason alert is prominent
- [ ] Verify all fields are pre-populated
- [ ] Verify existing attachments are shown
- [ ] Edit document details (change subject, date, etc.)
- [ ] Click "Save & Route to Admin"

#### Scenario 3: Route Back to Admin
- [ ] Verify success message is displayed
- [ ] Verify document appears in Admin Inbox
- [ ] Verify document phase is "ADMIN"
- [ ] Verify Admin assignment decision is "PENDING"
- [ ] Verify document removed from Receiving → Returned
- [ ] Verify document appears in Receiving → Accepted
- [ ] Verify Admin users receive notification

#### Scenario 4: Concurrent Processing
- [ ] Open same returned document in two browser tabs
- [ ] Submit from first tab
- [ ] Attempt to submit from second tab
- [ ] Verify second tab shows error message
- [ ] Verify no duplicate Admin assignments created

#### Scenario 5: Double-Click Prevention
- [ ] Double-click "Save & Route to Admin" button rapidly
- [ ] Verify button disables after first click
- [ ] Verify only one submission occurs
- [ ] Verify no duplicate assignments created

#### Scenario 6: Document Never Returned by Admin
- [ ] Create new document via normal receiving workflow
- [ ] Verify it does NOT appear in Returned tab
- [ ] Verify it does NOT appear in Accepted tab

#### Scenario 7: Search and Pagination
- [ ] Search by tracking number in Returned tab
- [ ] Search by subject matter in Accepted tab
- [ ] Test pagination on both tabs
- [ ] Verify search parameter persists across page changes

#### Scenario 8: Validation Errors
- [ ] Try to submit with empty subject matter
- [ ] Try to submit with invalid date
- [ ] Try to submit with missing document type
- [ ] Verify error messages display correctly
- [ ] Verify form data is preserved (old input)

#### Scenario 9: Missing Decline Reason
- [ ] Create scenario where decline_reason is NULL
- [ ] Verify "No reason provided" displays in table
- [ ] Verify edit page handles missing decline info gracefully

#### Scenario 10: Notification Failure
- [ ] Simulate notification failure after commit
- [ ] Verify document is still successfully routed
- [ ] Verify transaction is NOT rolled back
- [ ] Verify warning is logged in system_logs

---

## Expected Workflow Values

### ENUM Values Used
- **Phase**: `'RECEIVING'`, `'ADMIN'`
- **Decision**: `'PENDING'`, `'COMPLETED'`, `'DECLINED'`
- **Event Types**: `'DOCUMENT_EDITED'`, `'ROUTED_TO_ADMIN'`, `'ADMIN_DECLINED'`, `'ADMIN_RETURNED_TO_RECEIVING'`

### Role Identification
- **Receiving Staff**: Queried by name `'Receiving Staff'`
- **Admin**: Queried by name `'Admin'`
- Both roles must be `is_active = 1` and `is_deleted = 0`

### Timestamp Fields
- `received_at`: Set to `NOW()` when assignment created
- `completed_at`: Set to `NOW()` when Receiving marks as COMPLETED
- `declined_at`: Set by Admin when declining document
- `created_at`: Auto-populated by database DEFAULT CURRENT_TIMESTAMP

---

## Security and Authorization

### Middleware Protection
- All routes require `AuthMiddleware` (user must be logged in)
- All routes require `RoleMiddleware` (user must have proper role)

### Role-Based Filtering
- Controller methods query role ID by name at runtime
- Only documents assigned to Receiving Staff role are shown
- Only assignments in RECEIVING phase are processed

### CSRF Protection
- Forms should include CSRF token (handled by framework)
- POST requests validated for CSRF

### SQL Injection Prevention
- All queries use prepared statements
- All parameters properly bound with placeholders
- No raw SQL concatenation

---

## Performance Considerations

### Database Indexes
Existing indexes used:
- `idx_document_assignments_document` on `document_id`
- `idx_document_assignments_assigned_to_role` on `assigned_to_role_id`
- `idx_document_assignments_phase` on `phase`
- `idx_document_assignments_decision` on `decision`

### Query Optimization
- EXISTS subquery to verify Admin decline (indexed)
- LEFT JOIN for decline information (indexed foreign keys)
- LIMIT and OFFSET for pagination (20 records per page)
- COUNT query separate from data query

### Transaction Duration
- Short-lived transactions (< 1 second typical)
- No file I/O inside transaction
- No external API calls inside transaction
- Notifications sent AFTER commit

---

## Error Handling

### User-Facing Errors
- Flash messages with clear explanation
- Form validation errors with field-specific messages
- Old input preserved on validation failure
- Graceful degradation for missing data

### System Errors
- All exceptions logged to system_logs
- Stack traces captured for debugging
- PDO exceptions caught and handled
- Transaction rollback on any failure

### Concurrent Processing
- Optimistic locking detects race conditions
- Clear error message when document already processed
- Atomic WHERE clauses prevent inconsistent state

---

## Maintenance Notes

### Future Enhancements
- [ ] Add file upload capability to edit page
- [ ] Add bulk actions (accept multiple documents)
- [ ] Add email notifications (in addition to system notifications)
- [ ] Add revision history view
- [ ] Add export to CSV functionality

### Known Limitations
- Cannot upload new attachments when editing returned document
- Cannot delete existing attachments
- Search only covers tracking number and subject matter
- No filtering by document type or status

### Dependencies
- Requires `DocumentService::nextRevisionNumber()` method
- Requires helper functions: `auth_id()`, `flash_set()`, `flash_get()`, `old_set()`, `old_get()`, `old_clear()`, `audit_log()`, `system_log()`, `client_ip()`, `redirect()`
- Requires database schema from migrations 001-046

---

## Conclusion

The Receiving Inbox workflow has been successfully implemented with all required features:

✅ **Navigation**: Returned and Accepted tabs  
✅ **Database Conditions**: Proper WHERE clauses with EXISTS verification  
✅ **Return Reason**: Visible in table and edit page  
✅ **Edit Workflow**: Complete document editing capability  
✅ **Route Back**: Transaction-safe re-routing to Admin  
✅ **Safety Features**: Optimistic locking, duplicate prevention, notifications after commit  
✅ **UI Design**: Matches Admin Inbox design patterns  
✅ **Authorization**: Proper middleware and role-based access  
✅ **Error Handling**: Comprehensive validation and error messages  
✅ **Code Quality**: No syntax errors, proper structure  

All files have been created and tested for syntax errors. The implementation follows the existing codebase patterns and maintains consistency with the Admin Inbox workflow.

**Status**: Ready for manual testing and deployment.

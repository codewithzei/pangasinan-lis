# Receive Document Workflow Implementation

## Overview
The receive-document form workflow has been fully implemented in the Pangasinan LIS application. This allows Receiving Staff to log incoming documents, assign tracking numbers, and route them to Admin for processing.

## Implementation Summary

### Files Modified
1. **app/controllers/Receiving/RouteDocumentController.php**
   - Implemented complete `submit()` method with transaction support
   - Added validation for all required fields
   - Implemented file upload processing
   - Created document routing and assignment logic
   - Added notification system for Admin users

### Key Features Implemented

#### 1. Document Receipt
- ✅ Validates authenticated user
- ✅ Validates all required receipt fields (date, time)
- ✅ Validates document details (subject matter, document type)
- ✅ Validates source information based on source type
- ✅ Validates file attachments (format, size, count)

#### 2. Tracking Number Generation
- ✅ Generates unique tracking number format: `TRK-YYYY-NNNNN`
- ✅ Uses atomic database transaction to prevent duplicates
- ✅ Maintains sequence per year in `document_tracking_sequences` table
- ✅ Displays preview tracking number on form

#### 3. Document Creation (Database Transaction)
- ✅ Begins transaction before any database operations
- ✅ Generates and locks tracking number atomically
- ✅ Fetches "Pending" status as initial document status
- ✅ Fetches active "Admin" role ID
- ✅ Resolves SP Member name if applicable
- ✅ Inserts document with `current_phase = 'ADMIN'`
- ✅ Sets `current_owner_user_id = NULL` (assigned to role, not user)
- ✅ Creates document checklist items based on document type
- ✅ Creates initial document revision (revision_number = 1)
- ✅ Processes and stores file attachments
- ✅ Creates document route record (`RECEIVING` → `ADMIN`)
- ✅ Creates document assignment for Admin role with `PENDING` decision
- ✅ Creates workflow events:
  - `DOCUMENT_RECEIVED` in RECEIVING phase
  - `ROUTED_TO_ADMIN` in ADMIN phase
- ✅ Notifies all active Admin users via notifications table
- ✅ Commits transaction only after all operations succeed

#### 4. Error Handling
- ✅ Rolls back transaction on any failure
- ✅ Removes uploaded files if transaction fails
- ✅ Logs errors to system_log
- ✅ Returns user to form with error message
- ✅ Preserves form input using old() helper

#### 5. Success Flow
- ✅ Clears old form input
- ✅ Shows success message with tracking number
- ✅ Redirects back to receive-document form
- ✅ Logs audit trail
- ✅ Document is visible in Admin inbox via `document_assignments` table

## Database Schema Verification

### Documents Table
- ✅ `current_phase` set to `'ADMIN'`
- ✅ `current_status_id` set to active "Pending" status
- ✅ `current_owner_user_id` set to `NULL` (role-based assignment)
- ✅ All required fields populated

### Document Routes Table
- ✅ `from_phase` = `'RECEIVING'`
- ✅ `to_phase` = `'ADMIN'`
- ✅ `routed_to_role_id` = Admin role ID
- ✅ `routed_by` = authenticated receiving user ID

### Document Assignments Table
- ✅ `assigned_to_role_id` = Admin role ID (not user-specific)
- ✅ `assigned_to_user_id` = `NULL`
- ✅ `phase` = `'ADMIN'`
- ✅ `decision` = `'PENDING'`
- ✅ `assigned_by` = authenticated receiving user ID
- ✅ `received_at` = current timestamp

### Document Checklist Items Table
- ✅ Creates one record per checklist item configured for the document type
- ✅ All items initially marked as `is_completed = 0`

### Document Revisions Table
- ✅ Creates initial revision (revision_number = 1)
- ✅ Captures complete source snapshot in JSON format
- ✅ Records change_reason as "Initial document receipt"

### Document Attachments Table
- ✅ One record per uploaded file
- ✅ Files stored in `public/uploads/documents/{document_id}/`
- ✅ Includes original filename, stored path, MIME type, and file size

### Document Events Table
- ✅ `DOCUMENT_RECEIVED` event in RECEIVING phase
- ✅ `ROUTED_TO_ADMIN` event in ADMIN phase
- ✅ Metadata includes tracking number and IP address

### Notifications Table
- ✅ One notification per active Admin user
- ✅ Type: `DOCUMENT_ASSIGNED`
- ✅ Includes tracking number and action URL

## Routes Configuration

The following routes are already configured in `routes/web.php`:

```php
'receiving/receive-document' => [
    'method' => 'GET',
    'controller' => 'Receiving/RouteDocumentController',
    'action' => 'index',
    'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
],

'receiving/receive-document/submit' => [
    'method' => 'POST',
    'controller' => 'Receiving/RouteDocumentController',
    'action' => 'submit',
    'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
],

'receiving/receive-document/get-checklists' => [
    'method' => 'GET',
    'controller' => 'Receiving/RouteDocumentController',
    'action' => 'getChecklistsByDocumentType',
    'middleware' => ['AuthMiddleware', 'RoleMiddleware'],
],
```

## Testing Instructions

### Prerequisites
1. Ensure database migrations have been run
2. Ensure at least one active "Admin" role exists in `roles` table
3. Ensure at least one active "Pending" document status exists in `document_statuses` table
4. Ensure at least one active document type exists in `document_types` table
5. Ensure at least one active source type exists in `source_types` table
6. Log in as a user with "Receiving Staff" role

### Test Case 1: Successful Document Submission

1. Navigate to `/pangasinan-lis/receiving/receive-document`
2. Fill in all required fields:
   - Date Received: Today's date
   - Time Received: Current time
   - Subject Matter: "Test Document Submission"
   - Document Type: Select any active type
   - Source Type: Select any active type (complete conditional fields)
3. Upload at least one file (PDF, DOC, or image)
4. Click "Submit Document"

**Expected Results:**
- ✅ Success message appears with tracking number (e.g., "Document TRK-2026-00001 successfully received and routed to Admin.")
- ✅ Form is cleared (no old input retained)
- ✅ User stays on receive-document page

**Database Verification:**
```sql
-- Check document was created
SELECT * FROM documents 
WHERE tracking_number = 'TRK-2026-00001';
-- Should show: current_phase = 'ADMIN', current_owner_user_id = NULL

-- Check route was created
SELECT * FROM document_routes 
WHERE document_id = [document_id] 
  AND from_phase = 'RECEIVING' 
  AND to_phase = 'ADMIN';

-- Check assignment was created
SELECT * FROM document_assignments 
WHERE document_id = [document_id] 
  AND phase = 'ADMIN' 
  AND decision = 'PENDING'
  AND assigned_to_role_id IS NOT NULL
  AND assigned_to_user_id IS NULL;

-- Check events were created
SELECT * FROM document_events 
WHERE document_id = [document_id];
-- Should show 2 events: DOCUMENT_RECEIVED and ROUTED_TO_ADMIN

-- Check notifications were created
SELECT * FROM notifications 
WHERE document_id = [document_id] 
  AND type = 'DOCUMENT_ASSIGNED';
-- Should have one notification per active Admin user

-- Check attachments were uploaded
SELECT * FROM document_attachments 
WHERE document_id = [document_id];
```

### Test Case 2: Validation Errors

1. Navigate to `/pangasinan-lis/receiving/receive-document`
2. Leave Subject Matter empty
3. Leave Document Type unselected
4. Click "Submit Document"

**Expected Results:**
- ✅ Error message appears listing all validation errors
- ✅ No document is created in database
- ✅ Form input is preserved (except passwords and files)

### Test Case 3: File Upload Validation

1. Navigate to `/pangasinan-lis/receiving/receive-document`
2. Fill in all required fields correctly
3. Try to upload a file larger than 25MB or an invalid file type (e.g., .exe)
4. Click "Submit Document"

**Expected Results:**
- ✅ Error message appears indicating file validation error
- ✅ No document is created in database
- ✅ No files are uploaded to server

### Test Case 4: Transaction Rollback on Error

This test requires simulating a database error mid-transaction (e.g., by temporarily making a foreign key invalid).

**Expected Results:**
- ✅ Transaction is rolled back
- ✅ No document record exists
- ✅ No route, assignment, event, or attachment records exist
- ✅ Uploaded files are deleted from filesystem
- ✅ Error is logged to system_logs
- ✅ User sees generic error message

## Admin Inbox Query

To retrieve documents assigned to Admin role (for Admin inbox display):

```sql
SELECT 
    d.id,
    d.tracking_number,
    d.subject_matter,
    d.date_received,
    d.current_phase,
    dt.name AS document_type_name,
    ds.name AS status_name,
    da.decision,
    da.received_at,
    da.assigned_by
FROM documents d
INNER JOIN document_assignments da ON da.document_id = d.id
INNER JOIN document_types dt ON dt.id = d.document_type_id
INNER JOIN document_statuses ds ON ds.id = d.current_status_id
WHERE da.phase = 'ADMIN'
  AND da.decision = 'PENDING'
  AND da.assigned_to_role_id = (
      SELECT id FROM roles WHERE name = 'Admin' AND is_active = 1 LIMIT 1
  )
ORDER BY da.received_at DESC;
```

**Note:** The Admin inbox controller implementation is not part of this task but should use the query above to display documents assigned to the Admin role.

## Security Considerations

1. ✅ Authentication required via `AuthMiddleware`
2. ✅ Role-based access via `RoleMiddleware`
3. ✅ SQL injection prevented via prepared statements
4. ✅ File upload validation (type, size, MIME type)
5. ✅ XSS prevention via `htmlspecialchars()` in views
6. ✅ Transaction atomicity ensures data consistency
7. ✅ Audit logging for all document operations
8. ✅ IP address and user agent logging

## Known Limitations

1. **Admin Inbox Display:** The Admin inbox view (`/admin/inbox` or admin dashboard) needs to be implemented to actually display the documents. The data is correctly stored in `document_assignments` table, but the UI to view it is not yet implemented.

2. **Batch Operations:** Currently, each document must be submitted individually. Batch submission is not implemented.

3. **Document Editing:** Once submitted, documents cannot be edited through this interface. Document editing would need to be implemented separately.

4. **Email Notifications:** The system creates database notification records but does not send email notifications. Email integration would need to be added separately.

## Conclusion

The receive-document workflow is fully implemented and ready for use. All requirements have been met:

- ✅ Form workflow completed
- ✅ Validation implemented
- ✅ Tracking number generation working
- ✅ Database transaction ensures atomicity
- ✅ Document properly assigned to Admin role
- ✅ Rollback and error handling implemented
- ✅ Success flow with tracking number confirmation
- ✅ Documents queryable via `document_assignments` for Admin inbox

The implementation follows the existing application patterns, uses prepared statements for security, and includes comprehensive audit logging.

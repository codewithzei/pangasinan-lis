# Receiving Inbox PDO Error Fix

## Issue
**Fatal Error:** `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'ui_declined.user_id' in 'on clause'`

## Root Cause
The `user_info` table uses `user_account_id` as the foreign key column, not `user_id`. This was confirmed in the migration file `database/migrations/003_create_user_info_table.php`.

## Files Changed
- `app/controllers/Receiving/ReceivingInboxController.php`

## Changes Made

### 1. Fixed declined user join (line ~144)
**Before:**
```sql
LEFT JOIN user_info ui_declined ON ua_declined.id = ui_declined.user_id
```

**After:**
```sql
LEFT JOIN user_info ui_declined ON ua_declined.id = ui_declined.user_account_id
```

### 2. Fixed accepted user join (line ~182)
**Before:**
```sql
LEFT JOIN user_info ui_accepted ON ua_accepted.id = ui_accepted.user_id
```

**After:**
```sql
LEFT JOIN user_info ui_accepted ON ua_accepted.id = ui_accepted.user_account_id
```

### 3. Fixed decline info join (line ~323)
**Before:**
```sql
LEFT JOIN user_info ui ON ua.id = ui.user_id
```

**After:**
```sql
LEFT JOIN user_info ui ON ua.id = ui.user_account_id
```

## Verification Performed

### 1. PHP Syntax Validation
✅ No syntax errors detected in ReceivingInboxController.php

### 2. Project-Wide Audit
Searched the entire project for similar incorrect joins:
- ✅ `ProfileController.php` - already using correct `user_account_id`
- ✅ `AuthController.php` - already using correct `user_account_id`
- ✅ `DashboardController.php` - already using correct `user_account_id`
- ✅ `SystemLogController.php` - already using correct `user_account_id`
- ✅ `AuditLogController.php` - already using correct `user_account_id`

### 3. Database Schema Confirmation
The migration file confirms the correct column:
```php
user_account_id BIGINT NOT NULL UNIQUE, -- Forces strict 1-to-1 relationship
```

## Impact
These fixes resolve the SQL error in:
1. Receiving Inbox "Returned" view - displays documents returned by Admin with decline information
2. Receiving Inbox "Accepted" view - displays documents accepted by Receiving Staff
3. Document detail page - displays decline information from Admin

## Testing Checklist
- [ ] Load Receiving Inbox "Returned" tab without SQL error
- [ ] Load Receiving Inbox "Accepted" tab without SQL error
- [ ] View a returned document detail page
- [ ] Verify declined_by_name displays correctly in the Returned view
- [ ] Verify accepted_by_name displays correctly in the Accepted view
- [ ] Verify decline information shows correct user name on document detail page

## Date
2026-09-11

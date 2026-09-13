# Admin Inbox - Quick Reference Card

## Navigation Structure

### Two Tabs Only
1. **Inbox** - Pending documents awaiting Admin action
2. **Accepted** - Documents already accepted by Admin

> ⚠️ **Note:** "Received" tab was removed - it was redundant and confusing

## Query Conditions

### Inbox Tab
```php
WHERE assigned_to_role_id = {Admin Role ID}
  AND phase = 'ADMIN'
  AND decision = 'PENDING'
  AND completed_at IS NULL
```
**Shows:** Documents that Admin has NOT yet processed

### Accepted Tab
```php
WHERE assigned_to_role_id = {Admin Role ID}
  AND phase = 'ADMIN'
  AND decision = 'ACCEPTED'
```
**Shows:** Documents that Admin has accepted and can now route

## Database Constants

| Item | Value | Source |
|------|-------|--------|
| Admin Role ID | 3 | `roles` table |
| Admin Role Name | "Admin" | `roles.name` |
| Phase | 'ADMIN' | `document_assignments.phase` ENUM |
| Decision Values | 'PENDING', 'ACCEPTED', 'DECLINED', 'NOTED' | `document_assignments.decision` ENUM |

## Document Status Flow

```
PENDING (Inbox) → Admin Actions → ACCEPTED (Accepted) → Route to Next Phase
```

### From Inbox (PENDING):
- ✅ Accept → Moves to Accepted tab
- ❌ Decline → Returns to Receiving
- 🔀 Route → Sends to SP Secretary/Plenary/Committee
- 📝 Noted → Archives as communication

### From Accepted:
- 🔀 Route → Sends to next phase
- 📎 Upload → Add attachments
- 👁️ View → Review details

## Common Issues & Solutions

### Issue: "No documents found"
**Check:**
1. Admin role exists in database: `SELECT * FROM roles WHERE name='Admin'`
2. Documents have Admin assignments: `SELECT * FROM document_assignments WHERE assigned_to_role_id=3`
3. Query conditions match database values
4. ENUM values are exact match (case-sensitive)

### Issue: Duplicate documents
**Check:**
```sql
SELECT document_id, COUNT(*) 
FROM document_assignments 
WHERE assigned_to_role_id=3 
  AND phase='ADMIN' 
  AND decision='PENDING' 
  AND completed_at IS NULL
GROUP BY document_id 
HAVING COUNT(*) > 1
```

### Issue: Wrong counts in statistics
**Check:**
```sql
-- Pending count
SELECT COUNT(DISTINCT document_id) 
FROM document_assignments 
WHERE assigned_to_role_id=3 
  AND phase='ADMIN' 
  AND decision='PENDING' 
  AND completed_at IS NULL;

-- Accepted count
SELECT COUNT(DISTINCT document_id) 
FROM document_assignments 
WHERE assigned_to_role_id=3 
  AND phase='ADMIN' 
  AND decision='ACCEPTED';
```

## File Locations

| Component | Path |
|-----------|------|
| Controller | `app/controllers/Admin/AdminInboxController.php` |
| View | `resources/views/admin/inbox/index.php` |
| Routes | `routes/web.php` |
| Migration | `database/migrations/032_create_document_assignments_table.php` |

## Controller Methods

| Method | Purpose | Route |
|--------|---------|-------|
| `index()` | List documents (inbox/accepted) | GET `/admin/inbox` |
| `show()` | View document details | GET `/admin/inbox/show?id={id}` |
| `process()` | Process document action | POST `/admin/inbox/process` |
| `upload()` | Upload attachments | POST `/admin/inbox/upload` |

## Query Parameters

| Parameter | Values | Purpose |
|-----------|--------|---------|
| `view` | inbox, accepted | Switch between tabs |
| `search` | string | Search tracking # or subject |
| `page` | integer | Pagination |

## Important SQL Joins

```sql
FROM document_assignments da
INNER JOIN documents d ON da.document_id = d.id
INNER JOIN document_statuses ds ON d.current_status_id = ds.id
INNER JOIN document_types dt ON d.document_type_id = dt.id
INNER JOIN source_types st ON d.source_type_id = st.id
LEFT JOIN external_offices eo ON d.external_office_id = eo.id
LEFT JOIN hospitals h ON d.hospital_id = h.id
LEFT JOIN municities m ON d.municipality_id = m.id
```

> ⚠️ Use LEFT JOIN for optional relationships to avoid filtering out documents

## Testing Command

Create and run this test script:
```php
<?php
require_once 'app/config/database.php';
$db = new Database();
$pdo = $db->connect();

$adminRoleId = 3;

// Test Inbox
$inbox = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM document_assignments 
    WHERE assigned_to_role_id = ? 
      AND phase = 'ADMIN' 
      AND decision = 'PENDING' 
      AND completed_at IS NULL
");
$inbox->execute([$adminRoleId]);
echo "Inbox: " . $inbox->fetch()['count'] . " documents\n";

// Test Accepted
$accepted = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM document_assignments 
    WHERE assigned_to_role_id = ? 
      AND phase = 'ADMIN' 
      AND decision = 'ACCEPTED'
");
$accepted->execute([$adminRoleId]);
echo "Accepted: " . $accepted->fetch()['count'] . " documents\n";
```

## Maintenance Tips

1. **Always use prepared statements** for role_id parameter
2. **Test both tabs** after any query changes
3. **Verify DISTINCT** in count queries to avoid duplicates
4. **Check completed_at IS NULL** for active assignments only
5. **Maintain ENUM consistency** between migration and queries

## Related Documentation

- `ADMIN_INBOX_FIX_SUMMARY_2.md` - Full implementation details
- `ADMIN_INBOX_NAVIGATION_DIAGRAM.md` - Visual workflow diagrams
- `ADMIN_INBOX_FIX_VERIFICATION.md` - Original bug report and testing

---

**Last Updated:** 2026-09-11  
**Status:** ✅ Fixed and Verified

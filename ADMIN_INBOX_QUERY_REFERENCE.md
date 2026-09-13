# Admin Inbox Query Reference

## Quick Overview

This document explains the exact SQL conditions used for each Admin Inbox view.

## Common Base Query

All views start with this base:

```sql
SELECT
    da.id              AS assignment_id,
    da.received_at,
    da.decision,
    d.id               AS document_id,
    d.tracking_number,
    d.subject_matter,
    d.document_type_id,
    dt.name            AS document_type_name,
    dt.badge_color     AS document_type_badge_color,
    d.current_phase,
    d.date_received,
    d.time_received,
    ds.name            AS status,
    ds.badge_color     AS status_badge_color,
    st.name            AS source_type,
    COALESCE(eo.name, h.name, m.name, d.source_name, '—') AS source_display
FROM document_assignments da
INNER JOIN documents         d  ON da.document_id      = d.id
INNER JOIN document_statuses ds ON d.current_status_id = ds.id
INNER JOIN document_types    dt ON d.document_type_id  = dt.id
INNER JOIN source_types      st ON d.source_type_id    = st.id
LEFT  JOIN external_offices  eo ON d.external_office_id = eo.id
LEFT  JOIN hospitals          h ON d.hospital_id        = h.id
LEFT  JOIN municities         m ON d.municipality_id    = m.id
```

## View-Specific WHERE Clauses

### 1. Inbox View (`?view=inbox` or default)

**Purpose**: Show documents awaiting Admin action

```sql
WHERE da.assigned_to_role_id = ? -- Admin role ID
  AND da.phase = 'ADMIN'
  AND da.decision = 'PENDING'
  AND da.completed_at IS NULL
```

**Explanation**:
- `assigned_to_role_id = ?` - Only Admin role assignments
- `phase = 'ADMIN'` - Only assignments in Admin phase
- `decision = 'PENDING'` - Not yet processed
- `completed_at IS NULL` - Not marked as completed

**Example Documents**:
- Newly routed from Receiving
- Documents that were declined and returned, then re-sent to Admin
- Documents where Admin clicked "Accept" but the assignment isn't completed yet

---

### 2. Accepted View (`?view=accepted`)

**Purpose**: Show documents that Admin has accepted

```sql
WHERE da.assigned_to_role_id = ? -- Admin role ID
  AND da.phase = 'ADMIN'
  AND da.decision = 'ACCEPTED'
```

**Explanation**:
- `assigned_to_role_id = ?` - Only Admin role assignments
- `phase = 'ADMIN'` - Only assignments in Admin phase
- `decision = 'ACCEPTED'` - Admin clicked "Accept" button
- No `completed_at` filter - includes both completed and ongoing

**Example Documents**:
- Documents Admin accepted for processing
- Documents Admin accepted then routed to SP Secretary/Committee/Plenary
- Documents marked as "Noted" by Admin

---

### 3. Received View (`?view=received`)

**Purpose**: Show all documents that came through Admin

```sql
WHERE da.assigned_to_role_id = ? -- Admin role ID
  AND da.phase = 'ADMIN'
```

**Explanation**:
- `assigned_to_role_id = ?` - Only Admin role assignments
- `phase = 'ADMIN'` - Only assignments in Admin phase
- No decision filter - shows ALL statuses (PENDING, ACCEPTED, DECLINED, NOTED, etc.)
- No completion filter - shows all regardless of completion status

**Example Documents**:
- All documents from Inbox view
- All documents from Accepted view
- Declined documents
- Noted documents
- Everything that was ever routed to Admin

---

## Optional Search Filter

Applies to ALL views when user enters search text:

```sql
AND (d.tracking_number LIKE ? OR d.subject_matter LIKE ?)
-- Parameters: "%{$search}%", "%{$search}%"
```

**Example**:
- User searches "DOC-2024-001"
- Matches tracking numbers containing "DOC-2024-001"
- Also matches subject matter containing "DOC-2024-001"

---

## ORDER BY Clause

All views use the same ordering:

```sql
ORDER BY da.received_at ASC, d.date_received ASC, d.id ASC
```

**Explanation**:
- Primary: Assignment received date (oldest first)
- Secondary: Document received date (oldest first)
- Tertiary: Document ID (lowest first, for stable sort)

---

## Pagination

All views use the same pagination:

```sql
LIMIT ? OFFSET ?
```

Where:
- `LIMIT` = 20 (perPage constant)
- `OFFSET` = (page - 1) * 20

---

## Decision Values in document_assignments Table

The `decision` column can have these values:
- `PENDING` - Not yet processed
- `ACCEPTED` - Admin accepted the document
- `DECLINED` - Admin declined and returned to Receiving
- `NOTED` - Admin marked as noted (communication)
- `REJECTED` - (rare) Document rejected
- `COMPLETED` - (rare) Assignment fully completed

---

## Workflow State Examples

### Example 1: New Document Flow
1. **Receiving** creates document → routes to Admin
2. Assignment created: `phase='ADMIN'`, `decision='PENDING'`, `completed_at=NULL`
3. **Appears in**: Inbox ✓, Accepted ✗, Received ✓

### Example 2: Admin Accepts Document
1. Admin clicks "Accept" on document
2. Assignment updated: `decision='ACCEPTED'`, `accepted_at=NOW()`, `completed_at` stays NULL
3. **Appears in**: Inbox ✗, Accepted ✓, Received ✓

### Example 3: Admin Routes Document
1. Admin accepts then routes to SP Secretary
2. Assignment updated: `decision='ACCEPTED'`, `completed_at=NOW()`
3. New assignment created for SP Secretary
4. **Appears in**: Inbox ✗, Accepted ✓, Received ✓

### Example 4: Admin Declines Document
1. Admin clicks "Decline"
2. Assignment updated: `decision='DECLINED'`, `declined_at=NOW()`, `completed_at=NOW()`
3. New assignment created for Receiving Staff
4. **Appears in**: Inbox ✗, Accepted ✗, Received ✓

---

## Statistics Query

Dashboard cards use this aggregation:

```sql
SELECT 
    COUNT(DISTINCT CASE WHEN da.decision = 'PENDING' AND da.completed_at IS NULL THEN da.document_id END) AS pending_count,
    COUNT(DISTINCT CASE WHEN da.decision = 'ACCEPTED' THEN da.document_id END) AS accepted_count,
    COUNT(DISTINCT da.document_id) AS total_count
FROM document_assignments da
WHERE da.assigned_to_role_id = ? 
  AND da.phase = 'ADMIN'
```

**Returns**:
- `pending_count` - Documents in Inbox
- `accepted_count` - Documents in Accepted
- `total_count` - Documents in Received

---

## Common Troubleshooting

### "Inbox is empty" but documents exist

**Check**:
1. Do assignments exist with `phase='ADMIN'`?
   ```sql
   SELECT * FROM document_assignments WHERE phase='ADMIN';
   ```

2. Are they PENDING?
   ```sql
   SELECT * FROM document_assignments WHERE phase='ADMIN' AND decision='PENDING';
   ```

3. Is completed_at NULL?
   ```sql
   SELECT * FROM document_assignments WHERE phase='ADMIN' AND decision='PENDING' AND completed_at IS NULL;
   ```

4. Is the Admin role ID correct?
   ```sql
   SELECT id FROM roles WHERE name='Admin' AND is_active=1 AND is_deleted=0;
   ```

### Documents don't appear in any view

**Check**:
1. Does the document have an Admin assignment?
   ```sql
   SELECT da.* 
   FROM document_assignments da
   JOIN documents d ON da.document_id = d.id
   WHERE d.tracking_number = 'DOC-2024-001';
   ```

2. Is the assignment in ADMIN phase?
   ```sql
   SELECT * FROM document_assignments WHERE document_id = ? AND phase = 'ADMIN';
   ```

### Wrong counts in statistics

**Check**:
1. Run statistics query manually:
   ```sql
   SELECT 
       COUNT(DISTINCT CASE WHEN da.decision = 'PENDING' AND da.completed_at IS NULL THEN da.document_id END) AS pending,
       COUNT(DISTINCT CASE WHEN da.decision = 'ACCEPTED' THEN da.document_id END) AS accepted,
       COUNT(DISTINCT da.document_id) AS total
   FROM document_assignments da
   WHERE da.assigned_to_role_id = (SELECT id FROM roles WHERE name='Admin' LIMIT 1)
     AND da.phase = 'ADMIN';
   ```

2. Compare with actual table counts to identify discrepancies

---

## Testing Queries

### Find all pending Admin documents
```sql
SELECT d.tracking_number, d.subject_matter, da.decision, da.completed_at
FROM documents d
JOIN document_assignments da ON d.id = da.document_id
WHERE da.phase = 'ADMIN'
  AND da.decision = 'PENDING'
  AND da.completed_at IS NULL
ORDER BY da.received_at DESC;
```

### Find all accepted Admin documents
```sql
SELECT d.tracking_number, d.subject_matter, da.decision, da.accepted_at
FROM documents d
JOIN document_assignments da ON d.id = da.document_id
WHERE da.phase = 'ADMIN'
  AND da.decision = 'ACCEPTED'
ORDER BY da.accepted_at DESC;
```

### Find all Admin documents (received)
```sql
SELECT d.tracking_number, d.subject_matter, da.decision, da.completed_at
FROM documents d
JOIN document_assignments da ON d.id = da.document_id
WHERE da.phase = 'ADMIN'
ORDER BY da.received_at DESC;
```

### Check assignment history for a document
```sql
SELECT 
    da.phase,
    da.decision,
    da.received_at,
    da.accepted_at,
    da.declined_at,
    da.completed_at,
    r.name AS role_name
FROM document_assignments da
LEFT JOIN roles r ON da.assigned_to_role_id = r.id
WHERE da.document_id = ?
ORDER BY da.created_at ASC;
```

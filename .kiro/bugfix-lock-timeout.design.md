# Design

## Overview

**Bug:** Lock wait timeout in AdminInboxController::process() when processing Admin inbox assignments.

**Root Cause:** The sidebar's badge count query holds shared locks on document_assignments rows during page loads. When AdminInboxController::process() tries to acquire an exclusive FOR UPDATE lock on the same rows, it blocks until all shared locks are released. With multiple browser tabs or auto-refresh, this frequently exceeds the 5-second lock-wait timeout.

**Solution:** Replace pessimistic row locks (FOR UPDATE) with **optimistic locking**. The WHERE decision = 'PENDING' clause in the UPDATE statement acts as a compare-and-swap guard: only one request can change a row from PENDING to another state. After the UPDATE, check owCount() to detect races. This eliminates lock contention because reads are lock-free and the UPDATE holds an exclusive lock only for microseconds.

**Impact:** Eliminates lock-wait timeouts, increases throughput, and provides clear error messages for genuine concurrent submissions.

---

## Architecture

### Current Implementation (Pessimistic Locking)

`
[Browser Tab 1]       [Browser Tab 2]       [Browser Tab 3]
      ↓                     ↓                     ↓
  Sidebar Badge       Sidebar Badge         Sidebar Badge
   Query (shared        Query (shared        Query (shared
    lock held)           lock held)           lock held)
      ↓                     ↓                     ↓
    ┌──────────────────────────────────────────────┐
    │  document_assignments table                  │
    │  Rows locked in SHARED mode                  │
    └──────────────────────────────────────────────┘
                          ↓
              [Admin clicks "Accept"]
                          ↓
             SELECT ... FOR UPDATE
              (waits for shared locks)
                          ↓
               ⏱ Timeout (5s)
                          ↓
          Lock wait timeout error
`

**Problem:** The FOR UPDATE exclusive lock blocks on shared locks from sidebar queries running in other tabs/sessions.

### Proposed Implementation (Optimistic Locking)

`
[Browser Tab 1]       [Browser Tab 2]       [Browser Tab 3]
      ↓                     ↓                     ↓
  Sidebar Badge       Sidebar Badge         Sidebar Badge
   Query (NO lock)     Query (NO lock)      Query (NO lock)
      ↓                     ↓                     ↓
    ┌──────────────────────────────────────────────┐
    │  document_assignments table                  │
    │  Rows read without locking                   │
    └──────────────────────────────────────────────┘
                          ↓
              [Admin clicks "Accept"]
                          ↓
              SELECT (plain read, NO lock)
                          ↓
    UPDATE ... WHERE id = X AND decision = 'PENDING'
       (exclusive lock for microseconds only)
                          ↓
              Check rowCount():
               - 1 row → success
               - 0 rows → race detected, rollback
`

**Benefit:** Reads never block writes. The UPDATE is atomic and fast. Lock contention eliminated.

---

## Components and Interfaces

### Modified Component

**File:** pp/controllers/Admin/AdminInboxController.php

**Methods Modified:**
1. process() — Remove FOR UPDATE from assignment SELECT; remove SET innodb_lock_wait_timeout.
2. doAccept() — Add AND decision = 'PENDING' to UPDATE; add rowCount check.
3. doDecline() — Add AND decision = 'PENDING' to UPDATE; add rowCount check.
4. doRoute() — Add AND decision = 'PENDING' to UPDATE; add rowCount check.
5. doNoted() — Add AND decision = 'PENDING' to UPDATE; add rowCount check.

**Interface Changes:** None. All public method signatures remain unchanged. Error handling is internal (flash messages + redirect).

---

## Data Models

**Table:** document_assignments

**Columns Used as Race Guard:**
- id (PRIMARY KEY) — identifies the specific assignment row.
- decision (ENUM) — state machine column. Valid states: PENDING, ACCEPTED, DECLINED, COMPLETED, NOTED.

**Optimistic Lock Invariant:**  
A row can transition from PENDING to another state **exactly once**. The WHERE decision = 'PENDING' clause ensures only the first UPDATE succeeds.

**No Schema Changes Required:** The existing decision column is sufficient.

---

## Error Handling

### Race Condition Detected

**When:** Two requests attempt to process the same assignment concurrently. The second request's UPDATE matches 0 rows because the first request already changed decision from PENDING.

**Handling:**
`php
if ($stmt->rowCount() === 0) {
    throw new RuntimeException(
        'This assignment has already been processed by another request. Please refresh the page.'
    );
}
`

**User Experience:**
- Flash error message: "This assignment has already been processed by another request. Please refresh the page."
- Redirect to inbox detail page.
- Transaction rolled back automatically (no partial updates).

### Legacy Lock-Wait Timeout Handling

**Current Code:**
`php
if (str_contains($message, '1205') || str_contains($message, 'Lock wait timeout')) {
    flash_set('error', 'Another request is currently processing this document. Please wait a moment and try again.');
}
`

**Action:** Keep this as a fallback (for unexpected deadlocks or other DB issues), but it should never trigger under normal operation after this fix.

---

## Correctness Properties

### Property 1: Single-Processing Guarantee

**Invariant:** Each assignment can be processed (decision changed from PENDING) exactly once.

**Proof:**
1. The UPDATE ... WHERE id = X AND decision = 'PENDING' is atomic.
2. Only one transaction can match the decision = 'PENDING' condition.
3. Once committed, subsequent UPDATEs match 0 rows.

### Property 2: No Lost Updates

**Invariant:** If a request successfully checks owCount() === 1, its UPDATE has persisted.

**Proof:** The owCount() check occurs before commit(). If the check passes, the transaction proceeds to commit. If another failure occurs (e.g., network error), the entire transaction rolls back, and no partial state is left.

### Property 3: No Phantom Reads

**Concern:** Could a concurrent INSERT create a new PENDING row after the SELECT but before the UPDATE?

**Answer:** No. The WHERE id = X clause ensures we UPDATE the exact row we selected. New INSERTs would have different IDs.

---

## Deployment Checklist

- [ ] Code review: Verify AND decision = 'PENDING' clause added to all assignment UPDATEs.
- [ ] Code review: Verify FOR UPDATE removed from assignment SELECT.
- [ ] Code review: Verify rowCount checks added after each UPDATE.
- [ ] Code review: Verify SET innodb_lock_wait_timeout line removed.
- [ ] Test: Manual concurrent request test (two browser tabs).
- [ ] Test: Sidebar load test (verify no timeouts while badge queries run).
- [ ] Deploy to staging.
- [ ] Monitor logs for "already processed" errors (should be rare).
- [ ] Deploy to production.
- [ ] Monitor for 24 hours: verify no lock-wait timeout errors (error code 1205).

---

## Acceptance Criteria

1. **No Lock-Wait Timeouts:** The error "Lock wait timeout exceeded" (error code 1205) no longer occurs when processing Admin inbox assignments under normal operation.
2. **Race Detection:** When two requests attempt to process the same assignment concurrently, the second request fails gracefully with: "This assignment has already been processed by another request. Please refresh the page."
3. **Data Integrity:** Assignment decision column remains consistent (no double-processing, no invalid state transitions).
4. **Performance:** Admin inbox processing completes successfully under load, even with concurrent sidebar badge queries running in other tabs.

# Implementation Plan: Fix Database Lock Contention in Admin Inbox Processing

## Overview

This bugfix addresses a database lock contention issue in `AdminInboxController::process()`. The current implementation uses pessimistic locking (`FOR UPDATE`), which blocks on shared locks held by the sidebar's badge count query. This fix replaces it with optimistic locking: the `WHERE decision = 'PENDING'` clause acts as a race guard, and `rowCount()` checks detect concurrent modifications.

**Total Tasks:** 9  
**Estimated Duration:** 2–3 hours (including testing)

---

## Tasks

- [ ] 1. Remove FOR UPDATE lock from assignment read in `process()` method
- [ ] 2. Add optimistic lock check (AND decision = 'PENDING' + rowCount) to `doAccept()`
- [ ] 3. Add optimistic lock check to `doDecline()`
- [ ] 4. Add optimistic lock check to `doRoute()`
- [ ] 5. Add optimistic lock check to `doNoted()`
- [ ] 6. Remove `SET innodb_lock_wait_timeout = 5` statement from `process()`
- [ ] 7. Manual test: concurrent request handling (two browser tabs submit simultaneously)
- [ ] 8. Manual test: verify no lock-wait timeouts under load (sidebar queries + process action)
- [ ] 9. Run PHP syntax check and code review checklist

---

## Task Dependency Graph

```json
{
  "waves": [
    {
      "name": "Remove Pessimistic Lock",
      "tasks": [1]
    },
    {
      "name": "Add Optimistic Lock Checks",
      "tasks": [2, 3, 4, 5]
    },
    {
      "name": "Cleanup",
      "tasks": [6]
    },
    {
      "name": "Testing",
      "tasks": [7, 8]
    },
    {
      "name": "Verification",
      "tasks": [9]
    }
  ]
}
```

**Critical Path:** 1 → 2 → 6 → 7 → 8 → 9  
**Parallelizable:** Tasks 2, 3, 4, 5 can be done in any order after Task 1.

---

## Notes

**Phase 1: Core Lock Removal (Tasks 1–6)**  
Remove `FOR UPDATE` and add optimistic lock checks to all assignment UPDATE statements.

**Phase 2: Testing (Tasks 7–8)**  
Manual testing to verify race detection and elimination of lock-wait timeouts.

**Phase 3: Verification (Task 9)**  
Code review and syntax checks.

**Rollback Plan:** If issues arise, revert to the previous version (re-add `FOR UPDATE`). The database schema is unchanged, so rollback is safe.

**Performance Impact:** Expected improvement in throughput due to reduced lock contention. No negative performance impact anticipated.

**Monitoring:** After deployment, monitor error logs for "already processed" messages. These indicate genuine concurrent submissions (rare under normal operation).

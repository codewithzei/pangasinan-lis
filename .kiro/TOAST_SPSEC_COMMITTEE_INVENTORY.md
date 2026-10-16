# Toast Notification - SP Secretary & Committee Route Inventory

**Created**: 2026-10-07  
**Purpose**: Complete audit before implementing toast notifications

---

## SP SECRETARY ROUTES (10 routes)

### Inbox (4 routes)
1. `GET /spsec/inbox` → SpsecInboxController::index()
2. `GET /spsec/inbox/show` → SpsecInboxController::show()
3. `POST /spsec/inbox/process` → SpsecInboxController::process()
4. `POST /spsec/inbox/upload` → SpsecInboxController::upload()

### Route Document (2 routes)
5. `GET /spsec/route-document` → SpsecRouteDocumentController::index()
6. `POST /spsec/route-document/submit` → SpsecRouteDocumentController::submit()

### Routed Documents (2 routes)
7. `GET /spsec/routed` → SpsecRoutedDocumentController::index()
8. `GET /spsec/routed/show` → SpsecRoutedDocumentController::show()

### Communications (2 routes)
9. `GET /spsec/communications` → SpsecCommunicationsController::index()
10. `GET /spsec/communications/show` → SpsecCommunicationsController::show()

---

## COMMITTEE ROUTES (47 routes)

### Inbox (4 routes)
1. `GET /committee/inbox` → CommitteeInboxController::index()
2. `GET /committee/inbox/show` → CommitteeInboxController::show()
3. `POST /committee/inbox/process` → CommitteeInboxController::process()
4. `POST /committee/inbox/upload` → CommitteeInboxController::upload()

### Referred Documents (10 routes)
5. `GET /committee/referred` → CommitteeReferredController::index()
6. `GET /committee/referred/endorse` → CommitteeReferredController::endorseShow()
7. `POST /committee/referred/endorse` → CommitteeReferredController::endorseStore()
8. `GET /committee/referred/opinion` → CommitteeReferredController::opinionShow()
9. `POST /committee/referred/opinion` → CommitteeReferredController::opinionStore()
10. `GET /committee/referred/resolve` → CommitteeReferredController::resolveShow()
11. `POST /committee/referred/resolve` → CommitteeReferredController::resolveStore()
12. `POST /committee/referred/second-endorse` → CommitteeReferredController::secondEndorseStore()
13. `GET /committee/referred/agenda` → CommitteeReferredController::agendaShow()
14. `POST /committee/referred/agenda` → CommitteeReferredController::agendaStore()

### Hearing (9 routes)
15. `GET /committee/hearing` → CommitteeHearingController::index()
16. `GET /committee/hearing/show` → CommitteeHearingController::show()
17. `POST /committee/hearing/store` → CommitteeHearingController::store()
18. `GET /committee/hearing/report` → CommitteeHearingController::reportShow()
19. `POST /committee/hearing/report` → CommitteeHearingController::reportStore()
20. `GET /committee/hearing/report/show` → CommitteeHearingController::reportDetail()
21. `GET /committee/hearing/share-to-report` → CommitteeHearingController::shareToReportShow()
22. `POST /committee/hearing/share-to-report` → CommitteeHearingController::shareToReport()
23. `GET /committee/hearing/reports-json` → CommitteeHearingController::reportsJson()

### Reports (3 routes)
24. `GET /committee/reports` → CommitteeReportsController::index()
25. `GET /committee/reports/show` → CommitteeReportsController::show()
26. `POST /committee/reports/return-to-plenary` → CommitteeReportsController::returnToPlenary()

### Cases (12 routes)
27. `GET /committee/cases` → CommitteeCasesController::index()
28. `GET /committee/cases/create` → CommitteeCasesController::create()
29. `POST /committee/cases/create` → CommitteeCasesController::store()
30. `GET /committee/cases/show` → CommitteeCasesController::show()
31. `POST /committee/cases/actions/store` → CommitteeCasesController::storeAction()
32. `POST /committee/cases/outcome` → CommitteeCasesController::outcomeStore()
33. `GET /committee/cases/for-report` → CommitteeCasesController::forReport()
34. `GET /committee/cases/deferred` → CommitteeCasesController::deferred()
35. `GET /committee/cases/withdrawn` → CommitteeCasesController::withdrawn()
36. `GET /committee/cases/noted` → CommitteeCasesController::noted()
37. `GET /committee/cases/report` → CommitteeCasesController::caseReportShow()
38. `POST /committee/cases/report` → CommitteeCasesController::caseReportStore()

### Communications (9 routes)
39. `GET /committee/communications` → CommitteeCommunicationsController::index()
40. `GET /committee/communications/create` → CommitteeCommunicationsController::create()
41. `POST /committee/communications/create` → CommitteeCommunicationsController::store()
42. `GET /committee/communications/show` → CommitteeCommunicationsController::show()
43. `GET /committee/communications/agenda` → CommitteeCommunicationsController::agendaShow()
44. `POST /committee/communications/agenda` → CommitteeCommunicationsController::agendaStore()
45. `GET /committee/communications/hearing` → CommitteeCommunicationsController::hearingShow()
46. `POST /committee/communications/hearing` → CommitteeCommunicationsController::hearingStore()
47. `GET /committee/communications/report` → CommitteeCommunicationsController::reportShow()
48. `POST /committee/communications/report` → CommitteeCommunicationsController::reportStore()

---

## CONTROLLER FILES TO AUDIT

### SP Secretary (3 controllers)
- [ ] SpsecInboxController.php
- [ ] SpsecRoutedDocumentController.php
- [ ] SpsecCommunicationsController.php

### Committee (6 controllers)
- [ ] CommitteeInboxController.php
- [ ] CommitteeReferredController.php
- [ ] CommitteeHearingController.php
- [ ] CommitteeReportsController.php
- [ ] CommitteeCasesController.php
- [ ] CommitteeCommunicationsController.php

---

## VIEW DIRECTORIES TO AUDIT

### SP Secretary Views
- [ ] resources/views/spsec/inbox/
- [ ] resources/views/spsec/route-document/
- [ ] resources/views/spsec/routed/
- [ ] resources/views/spsec/communications/

### Committee Views
- [ ] resources/views/committee/inbox/
- [ ] resources/views/committee/referred/
- [ ] resources/views/committee/hearing/
- [ ] resources/views/committee/reports/
- [ ] resources/views/committee/cases/
- [ ] resources/views/committee/communications/

---

## AJAX ENDPOINTS IDENTIFIED

### Committee
- `GET /committee/hearing/reports-json` → CommitteeHearingController::reportsJson()

---

## SUMMARY

**Total Routes**: 57
- SP Secretary: 10 routes
- Committee: 47 routes

**Total Controllers**: 9
- SP Secretary: 3 controllers
- Committee: 6 controllers

**Action Types to Handle**:
- GET (index/list pages) → Display flash messages from previous actions
- GET (show/detail pages) → Display flash messages, preserve context
- GET (form pages) → Display flash messages, preserve validation errors
- POST (process actions) → Set flash messages, redirect
- POST (AJAX/JSON) → Return JSON response (committee reports-json)

---

## NEXT STEPS

1. Audit each controller for flash_get() usage
2. Audit each view for inline flash rendering
3. Check shared layouts used by SP Secretary & Committee
4. Identify AJAX/fetch actions
5. Document current implementation patterns
6. Apply toast notification standardization
7. Verify all routes work correctly

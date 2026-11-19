<?php

// Keep Committee's read-only details consistent with the existing document view.
$document['status'] = $document['status'] ?? '—';
$document['source_type'] = $document['source_type'] ?? '—';
$pageSubtitle = $document['tracking_number'] ?? 'Document Details';
$detailBackUrl = $backUrl ?? BASE_URL . '/committee/referred';
$detailBackLabel = 'Back to Referred Documents';
$showMfrRelationship = false;

require __DIR__ . '/../../receiving/routed-documents/show.php';

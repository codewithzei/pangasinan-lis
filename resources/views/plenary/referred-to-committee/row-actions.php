<?php
$canAdvance = !empty($row['can_advance']);
$hearingApproved = ($row['committee_hearing_outcome'] ?? '') === 'APPROVED';
?>
<div class="ml-auto flex w-full max-w-56 flex-col items-end gap-2">
    <a href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>"
       class="inline-flex max-w-full items-center justify-center gap-1.5 whitespace-normal rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-center text-xs font-medium text-gray-700 transition hover:border-primary hover:bg-blue-50 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
       aria-label="View details for <?= htmlspecialchars($row['tracking_number'], ENT_QUOTES, 'UTF-8') ?>"
       title="View details">
        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5s8.268 2.943 9.542 7c-1.274 4.057-5.065 7-9.542 7s-8.268-2.943-9.542-7z"/>
        </svg>
        <span>View details</span>
    </a>
    <?php if ($canAdvance): ?>
        <?php if ($hearingApproved && !empty($row['is_plenary_cases_referred'])): ?>
            <button type="button"
                    onclick="openAdoptCasesModal(<?= (int) $row['document_id'] ?>, <?= htmlspecialchars(json_encode((string) $row['tracking_number'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode((string) ($row['subject_matter'] ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>)"
                    class="inline-flex max-w-full items-center justify-center gap-1.5 whitespace-normal rounded-lg bg-primary px-3 py-1.5 text-center text-xs font-medium text-white transition hover:bg-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    aria-label="Adopt Committee Report for <?= htmlspecialchars($row['tracking_number'], ENT_QUOTES, 'UTF-8') ?>"
                    title="Adopt Committee Report">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Adopt Committee Report</span>
            </button>
        <?php elseif ($hearingApproved && !empty($row['is_plenary_comm_referred'])): ?>
            <button type="button"
                    onclick="openAdoptModal(<?= (int) $row['document_id'] ?>, <?= htmlspecialchars(json_encode((string) $row['tracking_number'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode((string) ($row['subject_matter'] ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>, <?= !empty($row['is_mfr']) ? 'true' : 'false' ?>)"
                    class="inline-flex max-w-full items-center justify-center gap-1.5 whitespace-normal rounded-lg bg-primary px-3 py-1.5 text-center text-xs font-medium text-white transition hover:bg-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    aria-label="Adopt Committee Report for <?= htmlspecialchars($row['tracking_number'], ENT_QUOTES, 'UTF-8') ?>"
                    title="Adopt Committee Report">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Adopt Committee Report</span>
            </button>
        <?php else: ?>
            <button type="button"
                    class="open-report-adoption-confirmation inline-flex max-w-full items-center justify-center gap-1.5 whitespace-normal rounded-lg px-3 py-1.5 text-center text-xs font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 <?= $hearingApproved ? 'bg-violet-600 text-white hover:bg-violet-700' : 'border border-gray-300 bg-white text-gray-600 hover:bg-gray-50' ?>"
                    data-document-id="<?= (int) $row['document_id'] ?>"
                    data-decision="<?= $hearingApproved ? 'APPROVED' : 'DISAPPROVED' ?>"
                    data-tracking-number="<?= htmlspecialchars($row['tracking_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    aria-label="<?= $hearingApproved ? 'Approve and proceed to Second Reading' : 'Adopt report only' ?> for <?= htmlspecialchars($row['tracking_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    title="<?= $hearingApproved ? 'Approve and proceed to 2nd Reading' : 'Adopt Report Only' ?>">
                <?php if ($hearingApproved): ?>
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>
                    <span>Approve &amp; Proceed to 2nd Reading</span>
                <?php else: ?>
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Adopt Report Only</span>
                <?php endif; ?>
            </button>
        <?php endif; ?>
    <?php endif; ?>
</div>

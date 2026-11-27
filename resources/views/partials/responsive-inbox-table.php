<?php
$responsiveInboxId = preg_replace('/[^a-zA-Z0-9_-]/', '', $responsiveInboxId ?? '');
$responsiveInboxLabels = $responsiveInboxLabels ?? [];
?>
<style>
    @media (max-width: 1279px) {
        #<?= $responsiveInboxId ?> .inbox-responsive-table {
            display: block;
            width: 100%;
        }

        #<?= $responsiveInboxId ?> .inbox-responsive-table thead {
            position: absolute;
            width: 1px;
            height: 1px;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        #<?= $responsiveInboxId ?> .inbox-responsive-table tbody {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 290px), 1fr));
            gap: 0.875rem;
            padding: 1rem;
        }

        #<?= $responsiveInboxId ?> .inbox-responsive-table tbody > tr:not(.inbox-empty-row) {
            position: relative;
            display: flex;
            min-width: 0;
            flex-direction: column;
            gap: 0.875rem;
            padding: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            background: #fff;
            box-shadow: 0 1px 2px rgb(15 23 42 / 0.05);
        }

        #<?= $responsiveInboxId ?> .inbox-responsive-table tbody > tr:not(.inbox-empty-row) > td:first-child {
            padding-right: 3.25rem;
        }

        #<?= $responsiveInboxId ?> .inbox-responsive-table td {
            display: block;
            min-width: 0;
            padding: 0;
            overflow-wrap: anywhere;
            border: 0;
        }

        #<?= $responsiveInboxId ?> .inbox-responsive-table td::before {
            display: block;
            margin-bottom: 0.25rem;
            color: #6b7280;
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

<?php foreach ($responsiveInboxLabels as $index => $label): ?>
        #<?= $responsiveInboxId ?> .inbox-responsive-table td:nth-child(<?= (int) $index + 1 ?>)::before {
            content: <?= json_encode($label, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        }
<?php endforeach; ?>

        #<?= $responsiveInboxId ?> .inbox-responsive-table tbody > tr:not(.inbox-empty-row) > td:last-child {
            position: absolute;
            top: 1rem;
            right: 1rem;
            display: flex;
            width: auto;
            margin: 0;
            padding: 0;
            justify-content: flex-end;
            text-align: right;
        }

        #<?= $responsiveInboxId ?> .inbox-responsive-table tbody > tr:not(.inbox-empty-row) > td:last-child > div {
            flex-direction: column;
            align-items: flex-end;
            justify-content: flex-end;
        }

        #<?= $responsiveInboxId ?> .inbox-responsive-table tbody > tr:not(.inbox-empty-row) > td:last-child::before,
        #<?= $responsiveInboxId ?> .inbox-responsive-table .inbox-empty-row td::before {
            content: none;
        }

        #<?= $responsiveInboxId ?> .inbox-responsive-table .inbox-empty-row {
            display: block;
            grid-column: 1 / -1;
            padding: 0;
        }

        #<?= $responsiveInboxId ?> .inbox-responsive-table .inbox-empty-row td {
            padding: 2rem 1rem;
            text-align: center;
        }
    }
</style>

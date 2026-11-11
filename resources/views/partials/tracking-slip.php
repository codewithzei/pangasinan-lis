<?php
$receiptUser = auth();
$receiptDefaultDestination = $receiptDefaultDestination ?? 'ADMIN';
?>

<article id="trackingSlip" class="tracking-slip-print"
         data-default-destination="<?= htmlspecialchars($receiptDefaultDestination, ENT_QUOTES, 'UTF-8') ?>"
         data-received-by="<?= htmlspecialchars((string) ($receiptUser['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <div class="tracking-slip">
        <p class="tracking-slip-reference">
            <strong>TRACKING / REFERENCE NO.</strong>
            <span id="slipTrackingNumber"><?= htmlspecialchars($trackingNumberPreview ?? '', ENT_QUOTES, 'UTF-8') ?></span>
        </p>

        <header class="tracking-slip-header">
            <img class="tracking-slip-logo"
                 src="<?= BASE_URL ?>/public/assets/images/branding/logo.png"
                 alt="Province of Pangasinan logo">
            <p class="tracking-slip-republic">Republic of the Philippines</p>
            <p class="tracking-slip-province">PROVINCE OF PANGASINAN</p>
            <p class="tracking-slip-lingayen">Lingayen</p>
            <p class="tracking-slip-secretariat">SANGGUNIANG PANLALAWIGAN SECRETARIAT</p>
            <p class="tracking-slip-division">ADMINISTRATIVE DIVISION</p>
            <h1 class="tracking-slip-title">TRACKING SLIP</h1>
        </header>

        <section class="tracking-slip-fields">
            <p class="tracking-slip-line"><strong>DOCUMENT TYPE:</strong><span id="slipDocumentType"></span></p>
            <p class="tracking-slip-line"><strong>SOURCE TYPE:</strong><span id="slipSourceType"></span></p>
            <p class="tracking-slip-line"><strong>SOURCE NAME:</strong><span id="slipAgency"></span></p>
            <p class="tracking-slip-subject-label"><strong>SUBJECT MATTER:</strong></p>
            <p id="slipSubject" class="tracking-slip-subject"></p>
        </section>

        <section class="tracking-slip-source-details" aria-label="Source contact details">
            <p><strong>CONTACT NUMBER:</strong><span id="slipContactNumber"></span></p>
            <p><strong>LIAISON:</strong><span id="slipLiaison"></span></p>
            <p><strong>ADDRESS:</strong><span id="slipAddress"></span></p>
        </section>

        <section class="tracking-slip-dates">
            <p><strong>DATE RECEIVED:</strong> <span id="slipDateReceived"></span></p>
            <p><strong>TIME RECEIVED:</strong> <span id="slipTimeReceived"></span></p>
            <p><strong>DATE&TIME ENDORSED:</strong> <span class="tracking-slip-date-blank">____-____-________ / ____:____</span></p>
        </section>

        <section class="tracking-slip-routes" aria-label="Routing destination">
            <div class="tracking-slip-route-column">
                <p><span class="tracking-slip-checkbox" data-route="VTNP"></span> VTNP</p>
                <p><span class="tracking-slip-checkbox" data-route="PLENARY"></span> PLENARY</p>
                <p><span class="tracking-slip-checkbox" data-route="COMMITTEE"></span> COMMITTEE</p>
                <p class="tracking-slip-others"><span class="tracking-slip-checkbox" data-route="OTHERS"></span> OTHERS: <span id="slipOtherRoute"></span></p>
            </div>
            <div class="tracking-slip-route-column">
                <p><span class="tracking-slip-checkbox" data-route="ADMIN"></span> ADMIN</p>
                <p><span class="tracking-slip-checkbox" data-route="RECORDS"></span> RECORDS</p>
                <p><span class="tracking-slip-checkbox" data-route="PERSONNEL"></span> PERSONNEL</p>
                <p><span class="tracking-slip-checkbox" data-route="POSTING"></span> POSTING</p>
            </div>
        </section>

        <section id="slipAdditionalSection" class="tracking-slip-additional" hidden>
            <h2>ADDITIONAL RECEIPT DETAILS</h2>
            <dl id="slipAdditionalDetails"></dl>
        </section>

        <section class="tracking-slip-footer">
            <div class="tracking-slip-signature">
                <strong>BY:</strong>
                <span class="tracking-slip-signature-line"></span>
                <small>(Signature over Printed Name)</small>
                <span id="slipReceivedBy" class="tracking-slip-printed-name"></span>
            </div>
            <p class="tracking-slip-to"><strong>TO:</strong><span id="slipDestination"></span></p>
            <p class="tracking-slip-remarks"><strong>Remarks:</strong><span id="slipRemarks"></span></p>
        </section>
    </div>
</article>

<style>
    .tracking-slip-print {
        display: none;
    }

    @media print {
        @page {
            size: letter portrait;
            margin: 6.35mm;
        }

        html,
        body {
            background: #fff !important;
            min-width: 0 !important;
            width: auto !important;
            height: auto !important;
        }

        body * {
            visibility: hidden !important;
        }

        body > :not(.tracking-slip-print) {
            display: none !important;
        }

        .tracking-slip-print,
        .tracking-slip-print * {
            visibility: visible !important;
        }

        .tracking-slip-print {
            display: block !important;
            position: absolute;
            top: 0;
            left: 0;
            width: 4.25in;
            height: 5.5in;
            break-inside: avoid;
            page-break-inside: avoid;
            color: #20242a;
            font-family: Arial, Helvetica, sans-serif;
        }

        .tracking-slip,
        .tracking-slip * {
            box-sizing: border-box;
        }

        .tracking-slip {
            width: 4.25in;
            height: 5.5in;
            overflow: hidden;
            padding: 3mm;
            border: 0.3mm solid #777;
            font-size: 6.5pt;
            line-height: 1.15;
        }

        .tracking-slip-reference {
            display: flex;
            justify-content: flex-end;
            gap: 1mm;
            margin: 0 0 1mm;
            color: #9c4545;
            font-size: 6pt;
        }

        .tracking-slip-header {
            text-align: center;
        }

        .tracking-slip-logo {
            display: block;
            width: 9mm;
            height: 9mm;
            object-fit: contain;
            margin: 0 auto 0.5mm;
        }

        .tracking-slip-header p,
        .tracking-slip-title {
            margin: 0;
        }

        .tracking-slip-republic,
        .tracking-slip-lingayen {
            font-size: 6pt;
        }

        .tracking-slip-province {
            font-weight: 700;
        }

        .tracking-slip-secretariat,
        .tracking-slip-division,
        .tracking-slip-title,
        .tracking-slip-remarks strong {
            color: #315f91;
        }

        .tracking-slip-secretariat {
            margin-top: 1mm !important;
            font-size: 7pt;
            letter-spacing: 0.02em;
        }

        .tracking-slip-division {
            font-size: 6pt;
            font-weight: 700;
        }

        .tracking-slip-title {
            margin: 0.5mm 0 2mm !important;
            font-size: 11pt;
            font-style: italic;
            font-weight: 800;
        }

        .tracking-slip-fields {
            margin-bottom: 1.5mm;
        }

        .tracking-slip-line {
            display: flex;
            align-items: flex-end;
            gap: 1mm;
            min-height: 3.5mm;
            margin: 0;
            border-bottom: 1px solid #444;
        }

        .tracking-slip-line strong,
        .tracking-slip-subject-label strong {
            flex: 0 0 auto;
        }

        .tracking-slip-line span {
            flex: 1;
            min-width: 0;
            min-height: 2.5mm;
            overflow-wrap: anywhere;
        }

        .tracking-slip-subject-label {
            margin: 1mm 0 0;
        }

        .tracking-slip-subject {
            min-height: 7mm;
            margin: 0;
            padding: 0.5mm 0;
            border-bottom: 1px solid #444;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .tracking-slip-source-details {
            margin: 1mm 0;
            font-size: 6pt;
        }

        .tracking-slip-source-details p {
            display: flex;
            gap: 1mm;
            min-height: 3mm;
            margin: 0;
            border-bottom: 1px solid #aaa;
        }

        .tracking-slip-source-details strong {
            flex: 0 0 auto;
        }

        .tracking-slip-source-details span {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .tracking-slip-dates {
            margin: 1mm 0;
            font-size: 6pt;
        }

        .tracking-slip-dates p {
            margin: 0.5mm 0;
        }

        .tracking-slip-date-blank {
            white-space: nowrap;
            margin-left: 1mm;
        }

        .tracking-slip-routes {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2mm;
            margin: 1.5mm 0;
            padding: 0 2mm;
            font-size: 6pt;
        }

        .tracking-slip-route-column p {
            margin: 0.5mm 0;
        }

        .tracking-slip-checkbox {
            display: inline-flex;
            width: 4mm;
            height: 3mm;
            align-items: center;
            justify-content: center;
            margin-right: 1mm;
            border-bottom: 1px solid #444;
            font-weight: 700;
        }

        .tracking-slip-checkbox.is-checked::before {
            content: "X";
        }

        .tracking-slip-others > span:last-child {
            display: inline-block;
            min-width: 20mm;
            min-height: 3mm;
            border-bottom: 1px solid #444;
            vertical-align: bottom;
        }

        .tracking-slip-additional {
            margin: 1mm 0;
            font-size: 6pt;
        }

        .tracking-slip-additional h2 {
            margin: 0 0 0.5mm;
            color: #315f91;
            font-size: 6pt;
        }

        .tracking-slip-additional dl {
            display: grid;
            grid-template-columns: 20mm 1fr;
            gap: 0.5mm 1mm;
            margin: 0;
        }

        .tracking-slip-additional dt {
            font-weight: 700;
        }

        .tracking-slip-additional dd {
            margin: 0;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .tracking-slip-footer {
            margin-top: 1mm;
        }

        .tracking-slip-signature {
            display: grid;
            grid-template-columns: 8mm 1fr;
            align-items: end;
            column-gap: 1mm;
            width: 80%;
            margin: 0 auto 1mm;
        }

        .tracking-slip-signature-line {
            min-height: 4mm;
            border-bottom: 1px solid #444;
        }

        .tracking-slip-signature small {
            grid-column: 2;
            text-align: center;
            font-family: cursive;
            font-size: 5.5pt;
        }

        .tracking-slip-printed-name {
            grid-column: 2;
            min-height: 2mm;
            text-align: center;
            font-size: 5.5pt;
        }

        .tracking-slip-to,
        .tracking-slip-remarks {
            display: flex;
            align-items: flex-end;
            gap: 1mm;
            min-height: 4mm;
            margin: 0.5mm 0;
        }

        .tracking-slip-to strong,
        .tracking-slip-remarks strong {
            flex: 0 0 auto;
        }

        .tracking-slip-to span,
        .tracking-slip-remarks span {
            flex: 1;
            min-width: 0;
            min-height: 3mm;
            border-bottom: 1px solid #444;
            overflow-wrap: anywhere;
        }

        .tracking-slip-remarks span {
            border-color: #315f91;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const printButton = document.getElementById('printTrackingSlip');
    const slip = document.getElementById('trackingSlip');
    if (!printButton || !slip) return;

    const originalParent = slip.parentNode;
    const originalNextSibling = slip.nextSibling;
    const form = document.getElementById('documentForm');
    const getValue = function (id) {
        const element = document.getElementById(id);
        return element && 'value' in element ? String(element.value || '').trim() : '';
    };
    const getSelectedLabel = function (id) {
        const select = document.getElementById(id);
        if (!select || select.selectedIndex < 0 || !select.value) return '';
        return select.options[select.selectedIndex].textContent.trim();
    };
    const getText = function (id) {
        const element = document.getElementById(id);
        return element ? element.textContent.trim() : '';
    };
    const setText = function (id, value) {
        const element = document.getElementById(id);
        if (element) element.textContent = value || '';
    };

    const updateTrackingSlip = function () {
        const sourceType = getSelectedLabel('source_type_id');
        let sourceName = '';
        if (sourceType === 'Provincial Government Offices') {
            sourceName = getSelectedLabel('external_office_id');
        } else if (sourceType === 'Hospital') {
            sourceName = getSelectedLabel('hospital_id');
        } else if (sourceType === 'SP Member') {
            sourceName = getSelectedLabel('sp_member_id');
        } else if (sourceType === 'Agency') {
            sourceName = getValue('source_name_agency');
        } else if (sourceType === 'Client') {
            sourceName = getValue('source_name_client');
            const municipality = getSelectedLabel('municipality_id');
            if (municipality) sourceName += ' — ' + municipality;
        }

        const receivedDate = getValue('date_received');
        const dateParts = receivedDate.split('-');
        const formattedDate = dateParts.length === 3
            ? dateParts[1] + '-' + dateParts[2] + '-' + dateParts[0]
            : '';
        const receivedTime = getValue('time_received');
        const timeParts = receivedTime.match(/^([01]\d|2[0-3]):([0-5]\d)$/);
        const formattedTime = timeParts
            ? (Number(timeParts[1]) % 12 || 12) + ':' + timeParts[2] + (Number(timeParts[1]) < 12 ? ' AM' : ' PM')
            : '';

        setText('slipTrackingNumber', getText('trackingNumberPreview'));
        setText('slipAgency', sourceName);
        setText('slipSourceType', sourceType);
        setText('slipDocumentType', getSelectedLabel('document_type_id'));
        setText('slipSubject', getValue('subject_matter'));
        setText('slipDateReceived', formattedDate);
        setText('slipTimeReceived', formattedTime);
        setText('slipContactNumber', getValue('source_contact_number'));
        setText('slipLiaison', getValue('source_liaison_name'));
        setText('slipAddress', getValue('source_address'));
        setText('slipRemarks', getValue('remarks'));
        setText('slipReceivedBy', slip.dataset.receivedBy || '');

        const routingSelect = document.getElementById('routing_option_id');
        const destination = routingSelect && routingSelect.value
            ? getSelectedLabel('routing_option_id')
            : (slip.dataset.defaultDestination || 'ADMIN');
        setText('slipDestination', destination);

        const selectedRouteKey = destination.toUpperCase().replace(/[^A-Z]/g, '');
        const routeKey = selectedRouteKey === 'SPSECRETARY' ? 'VTNP' : selectedRouteKey;
        const routeBoxes = slip.querySelectorAll('.tracking-slip-checkbox[data-route]');
        routeBoxes.forEach(function (box) {
            const key = box.dataset.route;
            const checked = key === routeKey || (key === 'ADMIN' && routeKey === 'ADMININBOX');
            box.classList.toggle('is-checked', checked);
        });
        const knownRoutes = ['VTNP', 'PLENARY', 'COMMITTEE', 'ADMIN', 'RECORDS', 'PERSONNEL', 'POSTING'];
        const otherRoute = knownRoutes.includes(routeKey) || !destination ? '' : destination;
        setText('slipOtherRoute', otherRoute);

        const details = [];
        const category = getSelectedLabel('communication_category_id');
        const reference = getText('mfrPreviewTracking');
        const checkedItems = form
            ? Array.from(form.querySelectorAll('#checklistItems input[type="checkbox"]:checked'))
                .map(function (checkbox) {
                    const label = checkbox.closest('label');
                    return label ? label.textContent.trim() : '';
                })
                .filter(Boolean)
            : [];
        const attachmentInput = document.getElementById('attachments');
        const attachments = attachmentInput && attachmentInput.files
            ? Array.from(attachmentInput.files).map(function (file) { return file.name; })
            : [];

        if (destination && routingSelect && routingSelect.value) details.push(['Routing option', destination]);
        if (category) details.push(['Communication category', category]);
        if (reference) details.push(['Referenced case', reference]);
        details.push(['Checklist', checkedItems.length ? checkedItems.join(', ') : 'None selected']);
        if (attachments.length) details.push(['Attachments', attachments.join(', ')]);

        const detailList = document.getElementById('slipAdditionalDetails');
        const detailSection = document.getElementById('slipAdditionalSection');
        detailList.replaceChildren();
        details.forEach(function (detail) {
            const label = document.createElement('dt');
            const value = document.createElement('dd');
            label.textContent = detail[0];
            value.textContent = detail[1];
            detailList.append(label, value);
        });
        detailSection.hidden = details.length === 0;
    };

    const moveSlipToPrintLayer = function () {
        if (slip.parentElement !== document.body) document.body.appendChild(slip);
    };
    const restoreSlip = function () {
        if (originalParent && slip.parentNode === document.body) {
            originalParent.insertBefore(slip, originalNextSibling);
        }
    };

    printButton.addEventListener('click', function () {
        updateTrackingSlip();
        moveSlipToPrintLayer();
        window.print();
    });
    window.addEventListener('beforeprint', function () {
        updateTrackingSlip();
        moveSlipToPrintLayer();
    });
    window.addEventListener('afterprint', restoreSlip);
});
</script>

<div class="print-area" style="border: none;">
    <h6 style="font-size: 11px; font-weight: 600; text-align: center; margin-bottom: 2px;">
        PALAWAN ELECTRIC COOPERATIVE
    </h6>

    <?php if (!empty(trim($slip['office_address'] ?? ''))): ?>
        <p style="font-size: 10px; text-align: center; margin: 0 0 2px 0;">
            <?= htmlspecialchars($slip['office_address'], ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php else: ?>
        <div style="height: 12px;"></div>
    <?php endif; ?>

    <table class="no-border">
        <tr>
            <td><strong>Gas slip #:</strong> <?= htmlspecialchars($slip['gas_slip_id'], ENT_QUOTES, 'UTF-8') ?></td>
            <td class="right"><strong>Date:</strong> <?= date('M j, Y', strtotime($slip['date_issued'])) ?></td>
        </tr>
    </table>

    <p style="font-size: 10px; text-align: center; margin: 5px 0px;">FUEL/OIL REQUISITION SLIP</p>

    <table class="no-border mb-0"><tr><td>
        TO: <strong><u><?= htmlspecialchars(strtoupper($slip['fuel_supplier']), ENT_QUOTES, 'UTF-8') ?></u></strong><br>
        Please issue:<br>
        Vehicles: <strong><?= htmlspecialchars(ucfirst($slip['ownership']), ENT_QUOTES, 'UTF-8') ?></strong>
    </td></tr></table>

    <table>
        <tr><th class="center" style="width: 10%;">No</th><th class="center" style="width: 50%;">Fuel Items</th><th class="center" style="width: 20%;">Qty</th><th class="center" style="width: 20%;">Unit</th></tr>
        <?php $counter = 1; foreach ($fuel_items as $fuel): ?>
            <tr>
                <td class="center"><?= $counter++ ?></td>
                <td><?= htmlspecialchars($fuel['fuel_name'] . ($fuel['container'] === 'yes' ? ' (Container)' : ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="center"><?= htmlspecialchars($fuel['quantity'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="center"><?= htmlspecialchars($fuel['unit'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <table class="no-border mt-2"><tr><td>
        Type of Vehicle: <u><strong><?= htmlspecialchars($slip['plate_no'] . ' - ' . $slip['brand'] . ' ' . $slip['model'], ENT_QUOTES, 'UTF-8') ?></strong></u>
        <br>Destination: <u><?= htmlspecialchars($slip['route_path'] ?? '', ENT_QUOTES, 'UTF-8') ?></u>
        <br>Purpose: <u><?= htmlspecialchars($slip['purpose'], ENT_QUOTES, 'UTF-8') ?></u>
        <br>Validity Until: <u><?= date('F j, Y', strtotime($slip['validity_until'])) ?></u>
    </td></tr></table>

    <table class="no-border mt-2"><tr>
        <td style="vertical-align: top;">REQUESTED BY:<br><br><u><strong><?= htmlspecialchars(strtoupper($slip['requested_by']), ENT_QUOTES, 'UTF-8') ?></strong></u></td>
        <td style="width: 50%; text-align: left;">RECEIVED BY:<br><br>
            <table class="no-border" style="width:100%;"><tr><td style="width:50%;">_____________</td><td style="width:50%;">ODO: _____________</td></tr><tr><td colspan="2">Driver</td></tr></table>
        </td>
    </tr></table>
    <br>
    <table class="no-border mt-0"><tr><td class="center" colspan="3" style="font-size: 8px;"><b>NOTE: UP TO FULL TANK CAPACITY ONLY<br>BALANCE FORFEITED</b></td></tr></table>
    <table class="no-border border-top"><tr>
        <td style="vertical-align: middle; text-align: left;">
            <div style="font-size:10px;"><strong>This is a system generated document.</strong><br><?= htmlspecialchars($documentTimestampLabel ?? 'Date Printed', ENT_QUOTES, 'UTF-8') ?>: <?= date('M j, Y, g:i A') ?></div>
            <div style="font-size:10px; font-style: italic;"><u>Use the QR code to validate the authenticity of this gas slip.</u></div>
        </td>
        <td style="width: 80px; text-align: center;">
            <?php if ($isApproved): ?><img src="data:image/png;base64,<?= $qrBase64 ?>" style="width:90px; height:90px;" alt="Scan to verify gas slip"><?php endif; ?>
        </td>
    </tr></table>
</div>

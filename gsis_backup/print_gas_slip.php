<?php
require_once 'includes/config.php';
$conn = getDBConnection();

// Get selected IDs from GET
$ids = isset($_GET['ids']) ? $_GET['ids'] : [];

if (empty($ids)) {
    header("Location: main.php?alert=no_selection");
    exit;
}


// Sanitize IDs
$ids_list = implode(',', array_map('intval', $ids));


// // ✅ Fetch settings
// $settings_stmt = $conn->prepare("SELECT * FROM settings WHERE id = 1 LIMIT 1");
// $settings_stmt->execute();
// $settings = $settings_stmt->get_result()->fetch_assoc();
// if (!$settings) {
//     die("Settings not found.");
// }

$slips = [];
foreach ($ids as $id) {
    ob_start();

    // Fetch gas slip
    $slip_stmt = $conn->prepare("
        SELECT gs.*, v.plate_no, v.brand, v.model, v.category, v.ownership,
               r.origin, r.destination, r.distance_km
        FROM gas_slips gs
        JOIN vehicles v ON gs.vehicle_id = v.id
        JOIN routes r ON gs.route_id = r.id
        WHERE gs.id = ?
    ");
    $slip_stmt->bind_param("i", $id);
    $slip_stmt->execute();
    $slip = $slip_stmt->get_result()->fetch_assoc();
    if (!$slip) continue;

    // Fetch fuel items
    $fuel_stmt = $conn->prepare("
        SELECT fi.name, fr.quantity, fr.container, fi.unit
        FROM fuel_requests fr
        JOIN fuel_items fi ON fr.fuel_item_id = fi.id
        WHERE fr.gas_slip_id = ?
    ");
    $fuel_stmt->bind_param("s", $slip['gas_slip_id']);
    $fuel_stmt->execute();
    $fuel_items = $fuel_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Fetch gas slip (with concatenated route)
    $slip_stmt = $conn->prepare("
        SELECT gs.*, 
            v.plate_no, v.brand, v.model, v.category, v.ownership,
            MIN(r.origin) AS origin,
            CONCAT(
                MIN(r.origin), 
                ' → ',
                GROUP_CONCAT(r.destination ORDER BY gsr.sequence_no SEPARATOR ' → ')
            ) AS route_chain
        FROM gas_slips gs
        JOIN vehicles v ON gs.vehicle_id = v.id
        LEFT JOIN gas_slip_routes gsr ON gs.id = gsr.gas_slip_id
        LEFT JOIN routes r ON gsr.route_id = r.id
        WHERE gs.id = ?
        GROUP BY gs.id
    ");
    $slip_stmt->bind_param("i", $id);
    $slip_stmt->execute();
    $slip = $slip_stmt->get_result()->fetch_assoc();
    if (!$slip) continue;

    // ✅ Fetch settings for this slip’s area
    $settings_stmt = $conn->prepare("SELECT * FROM settings WHERE area = ? LIMIT 1");
    $settings_stmt->bind_param("s", $slip['area']);
    $settings_stmt->execute();
    $settings = $settings_stmt->get_result()->fetch_assoc();
    if (!$settings) {
        die("Settings not found for area: " . htmlspecialchars($slip['area']));
    }

    ?>
    <div class="print-area" style="border: none;">
        <h6 style="font-size: 11px; font-weight: 600; text-align: center;">
            PALAWAN ELECTRIC COOPERATIVE
        </h6>
        <p style="font-size: 10px; text-align: center; margin-top: -8px;">
            Bgy. Tiniguiban, Puerto Princesa City
        </p>

        <table class="no-border">
            <tr>
                <td><strong>Gas slip #:</strong> <?= $slip['gas_slip_id'] ?></td>
                <td class="right"><strong>Date:</strong> <?= date('M j, Y', strtotime($slip['date_issued'])) ?></td>
            </tr>
        </table>

        <p style="font-size: 10px; text-align: center; margin: 5px 0px;">
            FUEL/OIL REQUISITION SLIP
        </p>

        <table class="no-border mb-0">
            <tr>
                <td>
                    TO: <strong><u><?= strtoupper($settings['fuel_supplier']) ?></u></strong><br>
                    Please issue:<br>
                    Vehicles: <strong><?= ucfirst($slip['ownership']) ?></strong>
                </td>
            </tr>
        </table>

        <table >
            <tr>
                <th class="center" style="width: 10%;">No</th>
                <th class="center" style="width: 50%;">Fuel Items</th>
                <th class="center" style="width: 20%;">Qty</th>
                <th class="center" style="width: 20%;">Unit</th>
            </tr>
            <?php $counter = 1; foreach ($fuel_items as $fuel): ?>
                <tr>
                    <td class="center"><?= $counter++ ?></td>
                    <td><?= $fuel['name'] . ($fuel['container'] === 'yes' ? ' (Container)' : '') ?></td>
                    <td class="center"><?= $fuel['quantity'] ?></td>
                    <td class="center"><?= $fuel['unit'] ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <table class="no-border mt-2">
            <tr>
                <td>
                    Type of Vehicle: <u><strong><?= $slip['plate_no'] . " - " . $slip['brand'] . " " . $slip['model'] ?></strong></u>
                    <br>Destination: <u><?= htmlspecialchars($slip['route_chain'] ?? '', ENT_QUOTES, 'UTF-8') ?></u>
                    <br>Purpose: <u><?= $slip['purpose'] ?></u>
                    <br>Validity Until: <u><?= date('F j, Y', strtotime($slip['validity_until'])) ?></u>
                </td>
            </tr>
        </table>

        <table class="no-border mt-2">
            <tr>
                <td style="vertical-align: top;">
                    REQUESTED BY:
                    <br><br><u><strong><?= strtoupper($slip['requested_by']) ?></strong></u>
                </td>
                <td style="width: 40px;"></td>
                <td style="vertical-align: top;">
                    RECOMMENDING APPROVAL:
                    <br><br><u><strong><?= strtoupper($settings['r_approval']) ?></strong></u>
                    <br><?= $settings['r_designation'] ?>
                </td>
            </tr>
            <tr>
                <td style="vertical-align: top;">
                    APPROVED BY:
                    <br><br>
                    <?php if ($slip['ownership'] === 'coop-owned'): ?>
                        <u><strong><?= strtoupper($settings['b_approval']) ?></strong></u>
                        <br><?= $settings['b_designation'] ?>
                    <?php else: ?>
                        <u><strong><?= strtoupper($settings['a_approval']) ?></strong></u>
                        <br><?= $settings['a_designation'] ?>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <br>
        <table class="no-border mt-0">
            <tr>
                <td style="vertical-align: top;">RECEIVED BY:</td>
                <td>_____________<br>Driver</td>
                <td style="vertical-align: top;">ODO: _____________</td>
            </tr>
            <tr>
                <td class="center" colspan="3" style="font-size: 8px;">
                    <b>NOTE: UP TO FULL TANK CAPACITY ONLY<br>BALANCE FORFEITED</b>
                </td>
            </tr>
        </table>
    </div>
    <?php
    $slips[] = ob_get_clean();
}
$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Print Gas Slip</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="includes/style.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>

<div class="no-print">
    <div class="container py-1">
    <?php
        include 'header.php';
    ?>
    
    <div class="bg-white p-3 rounded shadow-sm mb-0 w-50">
        <div class="d-flex align-items-center gap-3 mb-0">
            <label class="text-primary small" for="copyCount">Number of Copies:</label>
            <select id="copyCount" class="form-select small-select" onchange="generateCopies()">
                <option value="1" selected>1</option>
                <option value="2">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
            </select>
            <div style="display: flex; gap: 6px;">
                <!-- <button class="btn btn-primary px-4 print-button" onclick="window.print()">🖨️ Print</button> -->
                <button class="btn btn-primary px-2" onclick="prepareAndPrint()"><i class="fa fa-print"></i> Print</button>
                <a href="main.php" class="btn btn-secondary px-2"><i class="fa fa-reply-all"></i> Back</a>
            </div>
        </div>
    </div>
    </div>

</div>

<!-- Output container -->
<div class="copy-container" id="slipOutput"></div>

<!-- Templates for each slip -->
<?php foreach ($slips as $i => $slipHtml): ?>
<template id="slipTemplate<?= $i ?>">
    <?= $slipHtml ?>
</template>
<?php endforeach; ?>

</body>
</html>

<script>
function generateCopies() {
    const copies = parseInt(document.getElementById('copyCount').value);
    const output = document.getElementById('slipOutput');
    output.innerHTML = '';

    document.querySelectorAll('template[id^="slipTemplate"]').forEach(template => {
        for (let i = 0; i < copies; i++) {
            const slip = document.createElement('div');
            slip.classList.add('print-area');
            slip.innerHTML = template.innerHTML;
            output.appendChild(slip);
        }
    });
}

function prepareAndPrint() {
    generateCopies();

    // Collect slip IDs from PHP
    const ids = <?= json_encode($ids) ?>;

    // Send AJAX to update status
    fetch('update_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ids: ids })
    }).then(() => {
        setTimeout(() => window.print(), 200);
    });
}

window.onload = generateCopies;

// Escape key handler
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    window.location.href = 'main.php';
    e.preventDefault();
  }
}, true);


</script>

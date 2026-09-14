<?php
require_once 'includes/config.php';
$conn = getDBConnection();

if (!isset($_GET['id'])) {
    die("Invalid request.");
}

$id = (int)$_GET['id'];

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
$result = $slip_stmt->get_result();
$slip = $result->fetch_assoc();
if (!$slip) {
    die("Gas slip not found.");
}

// Fetch fuel items
$fuel_stmt = $conn->prepare("
    SELECT fi.name, fr.quantity, fr.container, fi.unit
    FROM fuel_requests fr
    JOIN fuel_items fi ON fr.fuel_item_id = fi.id
    WHERE fr.gas_slip_id = ?
");

$fuel_stmt->bind_param("i", $slip['gas_slip_id']);
$fuel_stmt->execute();
$fuel_result = $fuel_stmt->get_result();
$fuel_items = $fuel_result->fetch_all(MYSQLI_ASSOC);

// ✅ Fetch settings (approvals, supplier, etc.)
$settings_stmt = $conn->prepare("SELECT * FROM settings WHERE id = 1 LIMIT 1");
$settings_stmt->execute();
$settings_result = $settings_stmt->get_result();
$settings = $settings_result->fetch_assoc();
if (!$settings) {
    die("Settings not found.");
}
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
</head>
<body>

<div class="no-print">
    
    <div class="container py-4">
        <?php
            include 'header.php';
        ?>
    
        <div class="bg-white p-3 rounded shadow-sm mb-4 w-50">
            <div class="d-flex align-items-center gap-3 mb-2">
                <label class="text-primary small" for="copyCount">Number of Copies:</label>
                <select id="copyCount" class="form-select small-select" onchange="generateCopies()">
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4" selected>4</option>
                </select>
                <div style="display: flex; gap: 6px;">
                    <button class="btn btn-primary px-4 print-button" onclick="window.print()">🖨️ Print</button>
                    <a href="main.php" class="btn btn-secondary px-4">Back</a>
                </div>

            </div>
        </div>
    </div>

</div>

<div class="copy-container" id="copies"></div>

<template id="slipTemplate">
    <div class="print-area">
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
                    TO:<strong> <u><?= strtoupper($settings['fuel_supplier']) ?></strong></u><br>
                    Please issue:<br>
                    Vehicles: <strong><?= ucfirst($slip['ownership']) ?></strong>
                </td>
            </tr>
        </table>

        <!-- // Display fuel items -->
        <table>
            <tr>
                <th class="center" style="width: 10%;">No</th>
                <th class="center" style="width: 50%;">Fuel Items</th>
                <th class="center" style="width: 20%;">Qty</th>
                <th class="center" style="width: 20%;">Unit</th>
            </tr>
            <?php 
                $counter = 1;
                foreach ($fuel_items as $fuel): ?>
                <tr>
                    <td class="center"><?= $counter++ ?></td>
                    <td>
                        <?= $fuel['name'] . ($fuel['container'] === 'yes' ? ' (Container)' : '') ?>
                    </td>
                    <td class="center"><?= $fuel['quantity'] ?></td>
                    <td class="center"><?= $fuel['unit'] ?></td>
                </tr>
            <?php endforeach; ?>
        </table>


        <table class="no-border mt-2">
            <tr>
                <td>
                    Type of Vehicle: <u><strong><?= $slip['plate_no'] . " - " . $slip['brand'] . " " . $slip['model'] ?></strong></u>
                    <br>Destination: <u><?= $slip['origin'] ?> → <?= $slip['destination'] ?></u>
                    <br>Purpose: <u><?= $slip['purpose'] ?></u>
                    <br>Validity Until: <u><?= date('F j, Y', strtotime($slip['validity_until'])) ?></u>
                </td>
            </tr>
        </table>

        <table class="no-border mt-2">
            <tr>
                <td style="vertical-align: top;">
                    REQUESTED BY:
                    <br>
                    <br><u><strong><?= strtoupper($slip['requested_by']) ?></strong></u>
                </td>
                <td style="width: 40px;"></td>
                <td style="vertical-align: top;">
                    RECOMMENDING APPROVAL:
                    <br>
                    <br><u><strong><?= strtoupper($settings['r_approval']) ?></strong></u>
                    <br><?= $settings['r_designation'] ?>
                </td>
            </tr>
            <tr>
                <td style="vertical-align: top;">
                    APPROVED BY:
                    <br>
                    <br><u><strong><?= strtoupper($settings['a_approval']) ?></strong></u>
                    <br><?= $settings['a_designation'] ?>
                </td>
            </tr>
        </table>
        <br>
        <table class="no-border mt-0">
            <tr>
                <td style="vertical-align: top;">
                    RECEIVED BY:
                </td>
                <td>
                    _____________
                    <br>Driver
                </td>
                <td style="vertical-align: top;">ODO: _____________</td>
            </tr>
            <tr>
                <td class="center" colspan="3" style="vertical-align: top; font-size: 8px;"><b>NOTE: UP TO FULL TANK CAPACITY ONLY<br> BALANCE FORFEITED</b></td>
            </tr>
        </table>

    </div>
</template>

<script>
    function generateCopies() {
        const count = parseInt(document.getElementById("copyCount").value);
        const container = document.getElementById("copies");
        const template = document.getElementById("slipTemplate");

        container.innerHTML = "";

        for (let i = 0; i < count; i++) {
            const clone = template.content.cloneNode(true);
            container.appendChild(clone);
        }
    }

    // Auto-generate 4 on load
    window.onload = generateCopies;

    // Escape key handler
    document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        const updateBtn = document.getElementById('updateBtn');
        const isEditing = updateBtn && getComputedStyle(updateBtn).display !== 'none';

        if (isEditing) {
        cancelBtn.click();
        } else {
        const referrer = document.referrer;
        if (referrer.includes('main.php')) {
            window.location.href = 'main.php';
        } else if (referrer.includes('add_slip.php')) {
            window.location.href = 'add_slip.php';
        } else {
            // Default fallback
            window.location.href = 'main.php';
        }
        }

        e.preventDefault();
    }
    }, true);


</script>

</body>
</html>

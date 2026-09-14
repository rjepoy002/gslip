<?php
session_start();
require_once 'includes/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

$conn = getDBConnection();

// Get selected IDs from GET (string)
$ids = $_GET['ids'] ?? '';

if ($ids === '') {
    header("Location: main.php?alert=no_selection");
    exit;
}

// Convert string → array → sanitize
$idsArray = array_filter(
    array_map('intval', explode(',', $ids))
);

$slips = [];
foreach ($idsArray as $id) {

    ob_start();

    // Fetch gas slip
    $slip_stmt = $conn->prepare("
        SELECT
            gs.id AS gs_id,
            gs.gas_slip_id,
            gs.date_issued,
            gs.validity_until,
            gs.requested_by,
            gs.purpose,
            gs.status,
            gs.approved_by,
            gs.approved_at,
            u.id AS user_id,
            u.department_id,
            u.area_id,
            v.plate_no,
            v.brand,
            v.model,
            v.ownership,
            a.fuel_supplier,

            CONCAT(
            MIN(r.origin),
            ' → ',
            GROUP_CONCAT(
                r.destination
                ORDER BY gsr.id
                SEPARATOR ' → '
            )
            ) AS route_path

        FROM gas_slips gs
        LEFT JOIN gas_slip_routes gsr ON gsr.gas_slip_id = gs.id
        LEFT JOIN routes r ON r.id = gsr.route_id
        LEFT JOIN users u ON u.id = gs.user_id
        LEFT JOIN vehicles v ON v.id = gs.vehicle_id
        LEFT JOIN areas a ON a.id = gs.area_id

        WHERE gs.id = ?
        GROUP BY gs.id
    ");
    $slip_stmt->bind_param("i", $id);
    $slip_stmt->execute();
    $slip = $slip_stmt->get_result()->fetch_assoc();
    $slip_stmt->close();

    if (!$slip) continue;

    // Generate QR Code
    $gs  = $slip['gas_slip_id'];
    $sig = hash_hmac('sha256', $gs, QR_SECRET_KEY);

    $verifyUrl = VERIFY_BASE_URL . '/verify_gas_slip.php'
            . '?gs=' . urlencode($gs)
            . '&sig=' . $sig;

    $qrCode = new QrCode($verifyUrl);

    $writer = new PngWriter();
    $qrResult = $writer->write($qrCode);
    $qrBase64 = base64_encode($qrResult->getString());

    // Fetch fuel items
    $fuel_stmt = $conn->prepare("
        SELECT 
            fi.name AS fuel_name,
            fr.quantity,
            fr.container,
            fi.unit
        FROM fuel_requests fr
        LEFT JOIN fuel_items fi ON fi.id = fr.fuel_item_id
        WHERE fr.gas_slip_id = ?
    ");
    $fuel_stmt->bind_param("i", $id);
    $fuel_stmt->execute();
    $fuel_items = $fuel_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $fuel_stmt->close();

    // echo "FUEL SUPPLIER: " . $slip['fuel_supplier'];
    // ✅ Fetch settings for this slip’s area
    // $settings_stmt = $conn->prepare("SELECT * FROM areas WHERE area_name = ? LIMIT 1");
    // $settings_stmt->bind_param("s", $slip['area']);
    // $settings_stmt->execute();
    // $settings = $settings_stmt->get_result()->fetch_assoc();
    // $settings_stmt->close();

    // if (!$settings) {
    //     die("Settings not found for area: " . htmlspecialchars($slip['area']));
    // }

    $qrData = 'GAS-SLIP:' . $slip['gas_slip_id'];
    $qrData = json_encode([
    'gas_slip_id' => $slip['gas_slip_id'],
    'issued'      => $slip['date_issued'],
    ]);

    $isApproved = ($slip['status'] === 'approved');

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
                    TO: <strong><u><?= strtoupper($slip['fuel_supplier']) ?></u></strong><br>
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
                    <td><?= $fuel['fuel_name'] . ($fuel['container'] === 'yes' ? ' (Container)' : '') ?></td>
                    <td class="center"><?= $fuel['quantity'] ?></td>
                    <td class="center"><?= $fuel['unit'] ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <table class="no-border mt-2">
            <tr>
                <td>
                    Type of Vehicle: <u><strong><?= $slip['plate_no'] . " - " . $slip['brand'] . " " . $slip['model'] ?></strong></u>
                    <br>Destination: <u><?= htmlspecialchars($slip['route_path'] ?? '', ENT_QUOTES, 'UTF-8') ?></u>
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
                <td style="width: 50%; text-align: left;">
                    RECEIVED BY:
                    <br><br>

                    <table class="no-border" style="width:100%;">
                        <tr>
                            <td style="width:50%;">_____________</td>
                            <td style="width:50%;">ODO: _____________</td>
                        </tr>
                        <tr>
                            <td colspan="2">Driver</td>
                        </tr>
                    </table>
                </td>

            </tr>
        </table>
        <br>
        <table class="no-border mt-0">
            <tr>
                <td class="center" colspan="3" style="font-size: 8px;">
                    <b>NOTE: UP TO FULL TANK CAPACITY ONLY<br>BALANCE FORFEITED</b>
                </td>
            </tr>
        </table>

        <table class="no-border border-top">
            <tr>
                <td style="vertical-align: middle; text-align: left;">
                    <div style="font-size:10px;">
                        <strong>
                        This is a system generated document.
                        </strong>
                        <br>
                        Date Printed: <?= date('M j, Y, g:i A') ?>
                    </div>
                    <div style="font-size:10px; font-style: italic;">
                        <u>
                        Use the QR code to validate the authenticity of this gas slip.</u>
                    </div>
                </td>
                <td style="width: 80px; text-align: center;">
                    <?php if ($isApproved): ?>
                        <img src="data:image/png;base64,<?= $qrBase64 ?>"
                            style="width:90px; height:90px;"
                            alt="Scan to verify gas slip">
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>

    <?php
    $slips[] = ob_get_clean();
}
// $conn->close(); --> comment out to prevent "MySQL connection was not properly closed" warning, since we may still need the connection in sidebar.php for counts

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Print Gas Slip</title>

    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/icons/bootstrap-icons.css">
    <script src="assets/js/sweetalert2.all.min.js"></script>
</head>
<body>

<div class="no-print">
    <?php include 'includes/sidebar.php'; ?>
</div>


<div class="app-content">
    <!-- Main Page Content -->
    <main class="main-content">
        <!-- Temporary Empty State -->
        <div class="panel-container no-print">
            <!-- Page Header -->
            <div class="page-header mb-2">
              <h1 class="mb-1">Print Gas Slips</h1>
              <p class="page-subtitle mb-2">
                  This page displays the selected gas slips that have been approved and are ready for printing.
              </p>
            </div>
        
            <div class="container">

                <div class="bg-white p-3 rounded shadow-sm mb-2 w-50 no-print">
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
                            <a href="<?= APP_BASE_URL ?>/approved_slips.php" class="btn btn-secondary px-2"><i class="fa fa-reply-all"></i> Back</a>
                        </div>
                    </div>
                </div>
        </div>
</div>
                <!-- Output container -->
                <div class="copy-container" style="margin-left: 20px;" id="slipOutput"></div>

                <!-- Templates for each slip -->
                <?php foreach ($slips as $i => $slipHtml): ?>
                <template id="slipTemplate<?= $i ?>">
                    <?= $slipHtml ?>
                </template>
                <?php endforeach; ?>

            </div>
    
        
    </main>


</body>
</html>

<script src="assets/js/app-ui.js"></script>
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

    // Store IDs for later
    window.printSlipIds = <?= json_encode($ids) ?>;

    // Open print dialog
    setTimeout(() => window.print(), 200);
}

// Fires AFTER print dialog closes
window.onafterprint = function () {
    Swal.fire({
        title: 'Printing Confirmation',
        text: 'Did the gas slip(s) print successfully?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes',
        cancelButtonText: 'No',
        allowOutsideClick: false,
        allowEscapeKey: false
    }).then((result) => {
        if (!result.isConfirmed) return;

        const ids = window.printSlipIds
            .split(',')
            .map(id => parseInt(id, 10));

        fetch('update_printed_at.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ ids })
        })
        .then(res => res.json())
        .then(data => {
            console.log('Update response:', data);

            Swal.fire({
                icon: 'success',
                title: 'Updated',
                text: `Printed date saved for ${data.affected_rows} slip(s)`
            });
        })
        .catch(err => {
            console.error('Update failed:', err);
            Swal.fire('Error', 'Failed to update printed status', 'error');
        });
    });
};



window.onload = generateCopies;

// Escape key handler
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    window.location.href = '<?= APP_BASE_URL ?>/approved_slips.php';
    e.preventDefault();
  }
}, true);


</script>

<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

if (!isset($_SESSION['user_id'], $_SESSION['session_token'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$gasSlipId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$gasSlipId) {
    http_response_code(400);
    exit('Invalid gas slip ID.');
}

$conn = getDBConnection();
$slipStmt = $conn->prepare("\n    SELECT\n        gs.id AS gs_id, gs.gas_slip_id, gs.date_issued, gs.validity_until,\n        gs.requested_by, gs.purpose, gs.status, gs.user_id,\n        v.plate_no, v.brand, v.model, v.ownership,\n        a.fuel_supplier, a.office_address,\n        CONCAT(MIN(r.origin), ' → ', GROUP_CONCAT(r.destination ORDER BY gsr.id SEPARATOR ' → ')) AS route_path\n    FROM gas_slips gs\n    LEFT JOIN gas_slip_routes gsr ON gsr.gas_slip_id = gs.id\n    LEFT JOIN routes r ON r.id = gsr.route_id\n    LEFT JOIN vehicles v ON v.id = gs.vehicle_id\n    LEFT JOIN areas a ON a.id = gs.area_id\n    WHERE gs.id = ?\n    GROUP BY gs.id\n");
$slipStmt->bind_param('i', $gasSlipId);
$slipStmt->execute();
$slip = $slipStmt->get_result()->fetch_assoc();
$slipStmt->close();

if (!$slip || $slip['status'] !== 'approved' || (
    (int) $slip['user_id'] !== (int) $_SESSION['user_id']
    && ($_SESSION['role'] ?? '') !== 'admin'
)) {
    http_response_code(403);
    exit('You are not authorized to view this gas slip.');
}

$fuelStmt = $conn->prepare("\n    SELECT fi.name AS fuel_name, fr.quantity, fr.container, fi.unit\n    FROM fuel_requests fr\n    LEFT JOIN fuel_items fi ON fi.id = fr.fuel_item_id\n    WHERE fr.gas_slip_id = ?\n");
$fuelStmt->bind_param('i', $gasSlipId);
$fuelStmt->execute();
$fuel_items = $fuelStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$fuelStmt->close();

$verifyUrl = VERIFY_BASE_URL . '/verify_gas_slip.php?gs=' . urlencode($slip['gas_slip_id'])
    . '&sig=' . hash_hmac('sha256', $slip['gas_slip_id'], QR_SECRET_KEY);
$qrBase64 = base64_encode((new PngWriter())->write(new QrCode($verifyUrl))->getString());
$isApproved = true;
$documentTimestampLabel = 'Date Viewed';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gas Slip <?= htmlspecialchars($slip['gas_slip_id'], ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/mobile.css">
</head>
<body>
  <main class="container py-4" style="max-width: 760px;">
    <?php require __DIR__ . '/includes/gas_slip_document_template.php'; ?>
  </main>
</body>
</html>

<?php
require_once 'includes/config.php';

$conn = getDBConnection();

/*
|--------------------------------------------------------------------------
| 1. Read QR parameters
|--------------------------------------------------------------------------
*/
$gs  = $_GET['gs']  ?? '';
$sig = $_GET['sig'] ?? '';

if ($gs === '' || $sig === '') {
    http_response_code(400);
    die('❌ Invalid QR code');
}

/*
|--------------------------------------------------------------------------
| 2. Verify HMAC signature (anti-tamper)
|--------------------------------------------------------------------------
*/
$expectedSig = hash_hmac('sha256', $gs, QR_SECRET_KEY);

if (!hash_equals($expectedSig, $sig)) {
    http_response_code(403);
    die('❌ Invalid or tampered gas slip');
}

/*
|--------------------------------------------------------------------------
| 3. Fetch gas slip + approvals
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT
        gs.id,
        gs.gas_slip_id,
        gs.date_issued,
        gs.validity_until,
        gs.status,
        gs.purpose,
        gs.approved_at,

        CONCAT(
            ru.first_name,
            ' ',
            IF(ru.middle_name IS NOT NULL AND ru.middle_name <> '',
                CONCAT(LEFT(ru.middle_name, 1), '. '),
                ''
            ),
            ru.last_name
        ) AS recommender_name,

        ru.designation as recommender_designation,
        au.designation as approver_designation,
        
        CONCAT(
            au.first_name,
            ' ',
            IF(au.middle_name IS NOT NULL AND au.middle_name <> '',
                CONCAT(LEFT(au.middle_name, 1), '. '),
                ''
            ),
            au.last_name
        ) AS approver_name,

        gs.approved_at,

        v.plate_no,
        v.brand,
        v.model



    FROM gas_slips gs
    LEFT JOIN vehicles v ON v.id = gs.vehicle_id
    LEFT JOIN users ru ON ru.id = gs.recommended_by
    LEFT JOIN users au ON au.id = gs.approved_by

    WHERE gs.gas_slip_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $gs);
$stmt->execute();
$result = $stmt->get_result();

$slip = $result->fetch_assoc();

if (!$slip) {
    http_response_code(404);
    die('❌ Gas slip not found');
}

/*
|--------------------------------------------------------------------------
| 4. Approval state
|--------------------------------------------------------------------------
*/
$isApproved = ($slip['status'] === 'approved');

$approvedDate = $slip['approved_at']
    ? date('M d, Y', strtotime($slip['approved_at']))
    : '—';

$recommenderDisplay = $slip['recommender_name']
    ? '<strong>' . mb_strtoupper($slip['recommender_name'], 'UTF-8') . '</strong>'
      . ' (' . htmlspecialchars($slip['recommender_designation']) . ')'
    : '—';

$approverDisplay = $slip['approver_name']
    ? '<strong>' . mb_strtoupper($slip['approver_name'], 'UTF-8') . '</strong>'
      . ' (' . htmlspecialchars($slip['approver_designation']) . ')'
    : '—';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Verify Gas Slip</title>

<style>
body {
    font-family: Arial, sans-serif;
    background: #f4f6f8;
    padding: 20px;
}

.card {
    max-width: 520px;
    margin: auto;
    background: #ffffff;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 6px 18px rgba(0,0,0,.12);
}

.status {
    display: inline-block;
    padding: 8px 14px;
    border-radius: 6px;
    font-weight: bold;
    margin-bottom: 14px;
}

.status.success {
    background: #e6f4ea;
    color: #198754;
}

.label {
    font-weight: bold;
    margin-top: 10px;
}

.value {
    margin-bottom: 6px;
}
</style>
</head>

<body>

<div class="card">

    <?php if ($isApproved): ?>
        <div class="status success">APPROVED</div>
    <?php endif; ?>

    <div class="label">Gas Slip #</div>
    <div class="value"><?= htmlspecialchars($slip['gas_slip_id']) ?></div>

    <div class="label">Vehicle</div>
    <div class="value">
        <?= htmlspecialchars($slip['plate_no']) ?> –
        <?= htmlspecialchars($slip['brand']) ?>
        <?= htmlspecialchars($slip['model']) ?>
    </div>

    <div class="label">Purpose</div>
    <div class="value"><?= htmlspecialchars($slip['purpose']) ?></div>

    <div class="label">Date Issued</div>
    <div class="value"><?= htmlspecialchars($slip['date_issued']) ?></div>

    <div class="label">Valid Until</div>
    <div class="value"><?= htmlspecialchars($slip['validity_until']) ?></div>

    <hr>

    <div class="label">Recommending Approval</div>
    <div class="value"><?= $recommenderDisplay ?></div>

    <div class="label">Approved By</div>
    <div class="value"><?= $approverDisplay ?></div>


    <div class="label">Date Approved</div>
    <div class="value"><?= htmlspecialchars($approvedDate) ?></div>

</div>

</body>
</html>

<?php
require_once 'includes/config.php';

$conn = getDBConnection();
// $host = "localhost";
// $username = "palecxzp";
// $password = "Palecoweb143$$";
// $database = "palecxzp_pal_db";

// $conn = new mysqli($host, $username, $password, $database);
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
        gs.recommended_at,

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

$recommendedDate = $slip['recommended_at']
    ? date('M d, Y', strtotime($slip['recommended_at']))
    : '—';

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

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Gas Slip Verification</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #eef2f6;
    color: #1f2937;
}

/* =========================================================
   PAGE
========================================================= */

.page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px 16px;
}

/* =========================================================
   CARD
========================================================= */

.verification-card {
    width: 100%;
    max-width: 560px;
    background: #ffffff;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 10px 35px rgba(0, 0, 0, .12);
}

/* =========================================================
   HEADER
========================================================= */

.card-header {
    background: #0b5ed7;
    color: #ffffff;
    padding: 26px 24px;
    text-align: center;
}

.card-header h1 {
    margin: 0;
    font-size: 22px;
    font-weight: 700;
}

.card-header p {
    margin: 7px 0 0;
    font-size: 14px;
    opacity: .85;
}

/* =========================================================
   STATUS
========================================================= */

.status-section {
    padding: 25px 24px 15px;
    text-align: center;
}

.status-icon {
    width: 68px;
    height: 68px;
    margin: auto;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 34px;
    font-weight: bold;
}

.status-icon.approved {
    background: #d1e7dd;
    color: #198754;
}

.status-title {
    margin-top: 13px;
    font-size: 21px;
    font-weight: 700;
    color: #198754;
}

.status-description {
    margin-top: 5px;
    font-size: 13px;
    color: #6b7280;
}

/* =========================================================
   GAS SLIP NUMBER
========================================================= */

.slip-number {
    margin: 5px 24px 20px;
    padding: 15px;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    text-align: center;
}

.slip-number .label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #6b7280;
}

.slip-number .number {
    margin-top: 5px;
    font-size: 23px;
    font-weight: 700;
    color: #111827;
}

/* =========================================================
   CONTENT
========================================================= */

.card-body {
    padding: 0 24px 25px;
}

.section-title {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .8px;
    color: #0b5ed7;
    margin: 22px 0 10px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    padding: 12px 0;
    border-bottom: 1px solid #edf0f3;
}

.info-label {
    font-size: 13px;
    color: #6b7280;
    flex-shrink: 0;
}

.info-value {
    font-size: 14px;
    font-weight: 600;
    text-align: right;
    color: #111827;
}

/* =========================================================
   APPROVAL BOX
========================================================= */

.approval-box {
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 16px;
    margin-top: 10px;
}

.approval-item {
    margin-bottom: 17px;
}

.approval-item:last-child {
    margin-bottom: 0;
}

.approval-label {
    font-size: 11px;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 5px;
}

.approval-value {
    font-size: 14px;
    color: #111827;
    line-height: 1.5;
}

/* =========================================================
   FOOTER
========================================================= */

.card-footer {
    background: #f8fafc;
    border-top: 1px solid #e5e7eb;
    padding: 17px 24px;
    text-align: center;
    font-size: 12px;
    color: #6b7280;
}

.verified-text {
    color: #198754;
    font-weight: 600;
}

/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 500px) {

    .page {
        padding: 0;
        align-items: flex-start;
    }

    .verification-card {
        max-width: none;
        min-height: 100vh;
        border-radius: 0;
        box-shadow: none;
    }

    .card-header {
        padding: 22px 20px;
    }

    .card-body {
        padding-left: 20px;
        padding-right: 20px;
    }

    .slip-number {
        margin-left: 20px;
        margin-right: 20px;
    }

    .info-row {
        display: block;
    }

    .info-value {
        text-align: left;
        margin-top: 5px;
    }

}

</style>

</head>

<body>

<div class="page">

    <div class="verification-card">

        <!-- HEADER -->

        <div class="card-header">

            <h1>Gas Slip Verification</h1>

            <p>
                Palawan Electric Cooperative
            </p>

        </div>


        <!-- STATUS -->

        <div class="status-section">

            <?php if ($isApproved): ?>

                <div class="status-icon approved">
                    ✓
                </div>

                <div class="status-title">
                    APPROVED
                </div>

                <div class="status-description">
                    This gas slip has been verified and approved.
                </div>

            <?php endif; ?>

        </div>


        <!-- GAS SLIP NUMBER -->

        <div class="slip-number">

            <div class="label">
                Gas Slip Number
            </div>

            <div class="number">
                <?= htmlspecialchars($slip['gas_slip_id']) ?>
            </div>

        </div>


        <!-- CONTENT -->

        <div class="card-body">


            <div class="section-title">
                Gas Slip Information
            </div>


            <div class="info-row">

                <div class="info-label">
                    Vehicle
                </div>

                <div class="info-value">

                    <?= htmlspecialchars($slip['plate_no']) ?>

                    <br>

                    <?= htmlspecialchars($slip['brand']) ?>
                    <?= htmlspecialchars($slip['model']) ?>

                </div>

            </div>


            <div class="info-row">

                <div class="info-label">
                    Purpose
                </div>

                <div class="info-value">
                    <?= htmlspecialchars($slip['purpose']) ?>
                </div>

            </div>


            <div class="info-row">

                <div class="info-label">
                    Date Issued
                </div>

                <div class="info-value">

                    <?= date(
                        'F d, Y',
                        strtotime($slip['date_issued'])
                    ) ?>

                </div>

            </div>


            <div class="info-row">

                <div class="info-label">
                    Valid Until
                </div>

                <div class="info-value">

                    <?= date(
                        'F d, Y',
                        strtotime($slip['validity_until'])
                    ) ?>

                </div>

            </div>


            <!-- APPROVAL -->

            <div class="section-title">
                Approval Information
            </div>


            <div class="approval-box">


                <div class="approval-item">

                    <div class="approval-label">
                        Recommending Approval
                    </div>

                    <div class="approval-value">
                        <?= $recommenderDisplay ?>
                    </div>

                </div>

                <div class="approval-item">

                <div class="approval-label">
                    Date Recommended
                </div>

                <div class="approval-value">
                    <?= htmlspecialchars($recommendedDate) ?>
                </div>

                </div>


                <div class="approval-item">

                    <div class="approval-label">
                        Approved By
                    </div>

                    <div class="approval-value">
                        <?= $approverDisplay ?>
                    </div>

                </div>


                <div class="approval-item">

                    <div class="approval-label">
                        Date Approved
                    </div>

                    <div class="approval-value">
                        <?= htmlspecialchars($approvedDate) ?>
                    </div>

                </div>


            </div>

        </div>


        <!-- FOOTER -->

        <div class="card-footer">

            <span class="verified-text">
                ✓ QR Verification Successful
            </span>

            <br><br>

            This page confirms the authenticity of the gas slip.

        </div>

    </div>

</div>

</body>
</html>

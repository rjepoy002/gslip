<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once 'includes/config.php';
require_once 'includes/notifications.php';

header('Content-Type: application/json');

/* =========================================================
   BASIC VALIDATION
========================================================= */

if (!isset($_POST['id'])) {

    echo json_encode([
        'success' => false,
        'message' => 'Missing gas slip ID'
    ]);

    exit;
}

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);

    exit;
}

$gasSlipId = (int) $_POST['id'];
$userId    = (int) $_SESSION['user_id'];

/* =========================================================
   DATABASE CONNECTION
========================================================= */

$conn = getDBConnection();

/* =========================================================
   GET GAS SLIP CONTEXT
========================================================= */

$ctx = getGasSlipContext(
    $conn,
    $gasSlipId
);

if (!$ctx) {

    echo json_encode([
        'success' => false,
        'message' => 'Gas slip not found.'
    ]);

    exit;
}

$departmentId = (int) $ctx['requester_department_id'];
$areaId       = (int) $ctx['gas_area_id'];
$ownership    = strtolower(
    trim((string) $ctx['ownership'])
);

/* =========================================================
   CHECK RECOMMENDER ONLY APPROVAL

   IMPORTANT:
   Private vehicles are never affected.
========================================================= */

$recommenderOnly = false;

if ($ownership !== 'private') {

    $settingStmt = $conn->prepare("
        SELECT recommender_only
        FROM department_approval_settings
        WHERE department_id = ?
          AND area_id = ?
        LIMIT 1
    ");

    $settingStmt->bind_param(
        'ii',
        $departmentId,
        $areaId
    );

    $settingStmt->execute();

    $settingResult =
        $settingStmt->get_result();

    if ($settingRow =
        $settingResult->fetch_assoc()
    ) {

        $recommenderOnly =
            (int) $settingRow['recommender_only'] === 1;

    }

    $settingStmt->close();
}

/* =========================================================
   RECOMMENDER ONLY APPROVAL
========================================================= */

if ($recommenderOnly) {

    /*
    The recommender becomes the final approver.
    */

    $approverType = 'recommender';

    $stmt = $conn->prepare("
        UPDATE gas_slips
        SET
            status = 'approved',

            recommended_by = ?,
            recommended_at = NOW(),

            approved_by = ?,
            approver_type = ?,
            approved_at = NOW()

        WHERE id = ?
          AND status = 'pending'
    ");

    $stmt->bind_param(
        'iisi',
        $userId,
        $userId,
        $approverType,
        $gasSlipId
    );

    $stmt->execute();

    $affectedRows = $stmt->affected_rows;

    $stmt->close();

    if ($affectedRows > 0) {

        /*
        Final approval notification.
        No approver notification is created.
        */
        notifyGasSlipApproved(
            $conn,
            $gasSlipId
        );

        echo json_encode([
            'success' => true,
            'recommender_only' => true
        ]);

    } else {

        echo json_encode([
            'success' => false,
            'message' =>
                'Gas slip not updated (already processed or invalid state)'
        ]);
    }

    exit;
}

/* =========================================================
   NORMAL RECOMMENDATION WORKFLOW

   This includes:
   - Regular vehicles with Recommender Only OFF
   - ALL private vehicles
========================================================= */

$stmt = $conn->prepare("
    UPDATE gas_slips
    SET
        status = 'recommended',
        recommended_by = ?,
        recommended_at = NOW()
    WHERE id = ?
      AND status = 'pending'
");

$stmt->bind_param(
    'ii',
    $userId,
    $gasSlipId
);

$stmt->execute();

$affectedRows = $stmt->affected_rows;

$stmt->close();

/* =========================================================
   CREATE NOTIFICATIONS
========================================================= */

if ($affectedRows > 0) {

    notifyGasSlipRecommended(
        $conn,
        $gasSlipId
    );

    echo json_encode([
        'success' => true,
        'recommender_only' => false
    ]);

} else {

    echo json_encode([
        'success' => false,
        'message' =>
            'Gas slip not updated (already processed or invalid state)'
    ]);
}
?>
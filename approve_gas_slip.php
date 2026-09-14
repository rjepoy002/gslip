<?php
session_start();

require_once 'includes/config.php';
require_once 'includes/notifications.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

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

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    !isset($_SESSION['department_id'])
) {

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);

    exit;
}

$gasSlipId    = (int) $_POST['id'];
$userId       = (int) $_SESSION['user_id'];
$departmentId = (int) $_SESSION['department_id'];

/* =========================================================
   DATABASE CONNECTION
========================================================= */

$conn = getDBConnection();

/* =========================================================
   SAFETY CHECK:
   BLOCK APPROVER FOR RECOMMENDER-ONLY AREAS

   Private vehicles are not affected.
========================================================= */

$slipStmt = $conn->prepare("
    SELECT
        gs.area_id,
        gs.user_id,
        COALESCE(v.ownership, '') AS ownership,
        COALESCE(u.department_id, 0) AS requester_department_id
    FROM gas_slips gs
    LEFT JOIN vehicles v
        ON v.id = gs.vehicle_id
    LEFT JOIN users u
        ON u.id = gs.user_id
    WHERE gs.id = ?
    LIMIT 1
");

$slipStmt->bind_param(
    'i',
    $gasSlipId
);

$slipStmt->execute();

$slipResult = $slipStmt->get_result();

if (!$slip = $slipResult->fetch_assoc()) {

    $slipStmt->close();

    echo json_encode([
        'success' => false,
        'message' => 'Gas slip not found.'
    ]);

    exit;
}

$slipStmt->close();

$areaId = (int) $slip['area_id'];

$slipDepartmentId =
    (int) $slip['requester_department_id'];

$ownership = strtolower(
    trim((string) $slip['ownership'])
);

/*
Private vehicles always use their existing workflow.
Only regular vehicles are checked.
*/

if ($ownership !== 'private') {

    $settingStmt = $conn->prepare("
        SELECT recommender_only
        FROM department_approval_settings
        WHERE department_id = ?
          AND area_id = ?
          AND recommender_only = 1
        LIMIT 1
    ");

    $settingStmt->bind_param(
        'ii',
        $slipDepartmentId,
        $areaId
    );

    $settingStmt->execute();

    $settingResult =
        $settingStmt->get_result();

    $recommenderOnly =
        $settingResult->num_rows > 0;

    $settingStmt->close();

    if ($recommenderOnly) {

        echo json_encode([
            'success' => false,
            'message' =>
                'This gas slip uses Recommender Only Approval. Final approval must be completed by a recommender.'
        ]);

        exit;
    }
}

/* =========================================================
   DETERMINE APPROVER TYPE
========================================================= */

$stmt = $conn->prepare("
    SELECT is_primary
    FROM department_approvers
    WHERE department_id = ?
      AND user_id = ?
    LIMIT 1
");

$stmt->bind_param(
    'ii',
    $departmentId,
    $userId
);

$stmt->execute();

$result = $stmt->get_result();

if (!$approver = $result->fetch_assoc()) {

    $stmt->close();

    echo json_encode([
        'success' => false,
        'message' => 'You are not authorized to approve this gas slip.'
    ]);

    exit;
}

$stmt->close();

/* =========================================================
   SET APPROVER TYPE
========================================================= */

$approverType =
    ((int) $approver['is_primary'] === 1)
        ? 'primary'
        : 'secondary';

/* =========================================================
   UPDATE GAS SLIP STATUS
========================================================= */

$stmt = $conn->prepare("
    UPDATE gas_slips
    SET
        status = 'approved',
        approved_by = ?,
        approver_type = ?,
        approved_at = NOW()
    WHERE id = ?
      AND status = 'recommended'
");

$stmt->bind_param(
    'isi',
    $userId,
    $approverType,
    $gasSlipId
);

$stmt->execute();

$affectedRows = $stmt->affected_rows;

$stmt->close();

/* =========================================================
   CREATE NOTIFICATIONS
========================================================= */

if ($affectedRows > 0) {

    notifyGasSlipApproved(
        $conn,
        $gasSlipId
    );

    echo json_encode([
        'success' => true
    ]);

} else {

    echo json_encode([
        'success' => false,
        'message' =>
            'Gas slip not updated (already processed or invalid state)'
    ]);
}

?>
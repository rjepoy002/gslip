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

$gasSlipId   = (int) $_POST['id'];
$userId      = (int) $_SESSION['user_id'];
$departmentId = (int) $_SESSION['department_id'];

/* =========================================================
   DATABASE CONNECTION
========================================================= */

$conn = getDBConnection();

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
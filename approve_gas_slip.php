<?php
session_start();

require_once 'includes/config.php';
require_once 'includes/notifications.php';
require_once 'includes/gas_slip_access.php';

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
require_once __DIR__ . '/includes/gas_slip_workflow.php';
echo json_encode(approveGasSlipWorkflow($conn, $gasSlipId, $userId, $departmentId));

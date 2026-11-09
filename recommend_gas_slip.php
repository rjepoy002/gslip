<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once 'includes/config.php';
require_once 'includes/notifications.php';
require_once 'includes/recommender_assignment.php';
require_once 'includes/gas_slip_access.php';

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
   VERIFY RECOMMENDER AUTHORITY
   Allows:
   - Permanent Primary Recommender
   - Active Secondary Recommender
========================================================= */

$conn = getDBConnection();
require_once __DIR__ . '/includes/gas_slip_workflow.php';
echo json_encode(recommendGasSlipWorkflow($conn, $gasSlipId, $userId, 0));

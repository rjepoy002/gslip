<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/notifications.php';

$conn = getDBConnection();

/* ==========================================
   AUTH GUARD
========================================== */
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['session_token']) ||
    !isset($_SESSION['role'])
) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$role   = $_SESSION['role'] ?? '';

$isAdmin           = ($role === 'admin');
$isRecommender     = !empty($_SESSION['is_recommender']);
$isApprover        = !empty($_SESSION['is_approver']);
$isPrivateApprover = !empty($_SESSION['is_private_approver']);

/* ==========================================
   ROLE CHECK
========================================== */
if (!$isRecommender && !$isApprover && !$isPrivateApprover) {
    $_SESSION['error'] = 'You are not authorized to reject gas slips.';
    header('Location: pending_gas_slips.php');
    exit;
}

/* ==========================================
   POST VALIDATION
========================================== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pending_gas_slips.php');
    exit;
}

$gasSlipId = (int) ($_POST['gas_slip_id'] ?? 0);

if ($gasSlipId <= 0) {
    $_SESSION['error'] = 'Invalid gas slip selected.';
    header('Location: pending_gas_slips.php');
    exit;
}

/* ==========================================
   CHECK GAS SLIP
========================================== */
$stmt = $conn->prepare("
    SELECT
        id,
        gas_slip_id,
        status
    FROM gas_slips
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $gasSlipId);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = 'Gas slip not found.';
    header('Location: pending_gas_slips.php');
    exit;
}

$gasSlip = $result->fetch_assoc();

/* ==========================================
   STATUS VALIDATION
========================================== */
$currentStatus = strtolower(trim($gasSlip['status']));

if ($currentStatus === 'approved') {
    $_SESSION['error'] = 'Approved gas slips cannot be rejected.';
    header('Location: pending_gas_slips.php');
    exit;
}

if ($currentStatus === 'rejected') {
    $_SESSION['error'] = 'This gas slip has already been rejected.';
    header('Location: pending_gas_slips.php');
    exit;
}

/* ==========================================
   UPDATE GAS SLIP
========================================== */
$update = $conn->prepare("
    UPDATE gas_slips
    SET
        status = 'rejected',
        rejected_by = ?,
        rejected_at = NOW()

    WHERE id = ?
");

$update->bind_param(
    "ii",
    $userId,
    $gasSlipId
);

if (!$update->execute()) {
    $_SESSION['error'] = 'Failed to reject gas slip.';
    header('Location: pending_gas_slips.php');
    exit;
}

/* ==========================================
   CREATE NOTIFICATION
========================================== */
notifyGasSlipRejected($conn, $gasSlipId);

/* ==========================================
   SUCCESS
========================================== */
$_SESSION['swal_success'] =
    'Gas Slip ' .
    $gasSlip['gas_slip_id'] .
    ' has been rejected successfully.';

header('Location: pending_gas_slips.php');
exit;
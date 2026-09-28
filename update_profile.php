<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/recommender_assignment.php';

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }

    $userId = (int) $_SESSION['user_id'];

    $firstName  = trim($_POST['first_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName   = trim($_POST['last_name'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $mobileNumber = trim($_POST['mobile_number'] ?? '');
    $mobileNumber = $mobileNumber !== '' ? $mobileNumber : null;
    $assignedRecommenderId = (int) ($_POST['assigned_recommender_id'] ?? 0);
    $canCreateGasSlip = canUserCreateGasSlip($conn, $userId);

    if ($canCreateGasSlip && $assignedRecommenderId > 0 && !isEligibleAssignedRecommender($conn, $userId, $assignedRecommenderId)) {
        $_SESSION['swal_error'] = 'The selected recommender is no longer available for your department/area.';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'));
        exit;
    }

    if ($canCreateGasSlip) {
        $stmt = $conn->prepare("
            UPDATE users
            SET
                first_name = ?,
                middle_name = ?,
                last_name = ?,
                designation = ?,
                mobile_number = ?,
                assigned_recommender_id = NULLIF(?, 0)
            WHERE id = ?
        ");

        $stmt->bind_param(
            "sssssii",
            $firstName,
            $middleName,
            $lastName,
            $designation,
            $mobileNumber,
            $assignedRecommenderId,
            $userId
        );
    } else {
        $stmt = $conn->prepare("
            UPDATE users
            SET
                first_name = ?,
                middle_name = ?,
                last_name = ?,
                designation = ?,
                mobile_number = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "sssssi",
            $firstName,
            $middleName,
            $lastName,
            $designation,
            $mobileNumber,
            $userId
        );
    }

    if ($stmt->execute()) {

        // Update session values
        $_SESSION['fullname'] = trim(
            $firstName . ' ' .
            ($middleName ? $middleName . ' ' : '') .
            $lastName
        );

        $_SESSION['designation'] = $designation;

        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

    $stmt->close();
}

header("Location: dashboard.php");
exit;

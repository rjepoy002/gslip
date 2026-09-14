<?php
session_start();
require_once 'includes/config.php';

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $userId = $_SESSION['user_id'];

    $firstName  = trim($_POST['first_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName   = trim($_POST['last_name'] ?? '');
    $designation = trim($_POST['designation'] ?? '');

    $stmt = $conn->prepare("
        UPDATE users
        SET 
            first_name = ?,
            middle_name = ?,
            last_name = ?,
            designation = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "ssssi",
        $firstName,
        $middleName,
        $lastName,
        $designation,
        $userId
    );

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
<?php
session_start();
require_once 'includes/config.php';

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    exit;
}

$conn = getDBConnection();

/* Prevent admin from removing own admin access */
if (
    $_POST['id'] == $_SESSION['user_id'] &&
    empty($_POST['role'])
) {
    echo json_encode([
        'success' => false,
        'message' => 'You cannot remove your own administrator access.'
    ]);
    exit;
}

$stmt = $conn->prepare("
    UPDATE users
    SET
        first_name = ?,
        middle_name = ?,
        last_name = ?,
        username = ?,
        designation = ?,
        role = ?,
        department_id = ?,
        area_id = ?,
        status = ?
    WHERE id = ?
");

$stmt->bind_param(
    "ssssssiisi",
    $_POST['first_name'],
    $_POST['middle_name'],
    $_POST['last_name'],
    $_POST['username'],
    $_POST['designation'],
    $_POST['role'],
    $_POST['department_id'],
    $_POST['area_id'],
    $_POST['status'],
    $_POST['id']
);

$stmt->execute();

echo json_encode([
    'success' => true
]);
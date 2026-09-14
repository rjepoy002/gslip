<?php
session_start();
require_once 'includes/config.php';

if ($_SESSION['role'] !== 'admin') {
    exit;
}

$conn = getDBConnection();

$id = (int)$_GET['id'];

$stmt = $conn->prepare("
        SELECT
            u.id,
            u.first_name,
            u.middle_name,
            u.last_name,
            u.username,
            u.designation,
            u.role,
            u.status,
            u.department_id,
            u.area_id,
            a.area_name
        FROM users u
        LEFT JOIN areas a
            ON a.id = u.area_id
        WHERE u.id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();

echo json_encode(
    $stmt->get_result()->fetch_assoc()
);
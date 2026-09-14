<?php
header('Content-Type: application/json');
require_once 'includes/config.php';
$conn = getDBConnection();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $changePass_user_id = $_POST['changePass_user_id'] ?? '';
    $oldPassword = $_POST['oldPassword'] ?? '';
    $newPassword = $_POST['newPassword'] ?? '';

    if (empty($changePass_user_id) || empty($oldPassword) || empty($newPassword)) {
        echo json_encode([
            "success" => false,
            "message" => "Missing required fields.",
            "debug" => [
                "changePass_user_id" => $changePass_user_id,
                "oldPassword" => $oldPassword,
                "newPassword" => $newPassword
            ]
        ]);
        exit;
    }

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $changePass_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            "success" => false,
            "message" => "User not found.",
            "debug" => ["changePass_user_id" => $changePass_user_id]
        ]);
        exit;
    }

    $row = $result->fetch_assoc();
    $hashedPassword = $row['password'];

    if (!password_verify($oldPassword, $hashedPassword)) {
        echo json_encode([
            "success" => false,
            "message" => "Old password is incorrect.",
            "debug" => ["oldPassword" => $oldPassword]
        ]);
        exit;
    }

    $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $updateStmt->bind_param("si", $newHashedPassword, $changePass_user_id);

    if (!$updateStmt->execute()) {
        echo json_encode([
            "success" => false,
            "message" => "Failed to update password."
        ]);
        exit;
    }

    echo json_encode([
        "success" => true,
        "message" => "Password updated successfully."
    ]);
    exit;

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Server error: " . $e->getMessage()
    ]);
    exit;
}

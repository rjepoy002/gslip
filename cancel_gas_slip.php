<?php
session_start();
require_once 'includes/config.php';
$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $userId = $_SESSION['user_id'] ?? null;

    if (!$id || !$userId) {
        exit('Invalid request');
    }

    // 🔒 ensure user owns the draft
    $stmt = $conn->prepare("
        UPDATE gas_slips
        SET status = 'cancelled'
        WHERE id = ? AND status = 'draft'
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    $_SESSION['swal_success'] = 'Draft cancelled successfully';
    header('Location: draft_gas_slips.php');
    exit;
}
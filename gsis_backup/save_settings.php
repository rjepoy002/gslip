<?php
require_once 'includes/config.php';
$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $area = trim($_POST['area']); // area must come from form
    $fuel_supplier = trim($_POST['fuel_supplier']);
    $r_approval = trim($_POST['r_approval']);
    $r_designation = trim($_POST['r_designation']);
    $a_approval = trim($_POST['a_approval']);
    $a_designation = trim($_POST['a_designation']);
    $b_approval = !empty($_POST['b_approval']) ? trim($_POST['b_approval']) : null;
    $b_designation = !empty($_POST['b_designation']) ? trim($_POST['b_designation']) : null;

    // Check if area already has settings
    $stmt = $conn->prepare("SELECT id FROM settings WHERE area = ?");
    $stmt->bind_param("s", $area);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Update existing
        $row = $result->fetch_assoc();
        $stmt = $conn->prepare("
            UPDATE settings
            SET fuel_supplier=?, r_approval=?, r_designation=?, a_approval=?, a_designation=?, b_approval=?, b_designation=?
            WHERE id=?
        ");
        $stmt->bind_param("sssssssi", $fuel_supplier, $r_approval, $r_designation, $a_approval, $a_designation, $b_approval, $b_designation, $row['id']);
    } else {
        // Insert new
        $stmt = $conn->prepare("
            INSERT INTO settings (area, fuel_supplier, r_approval, r_designation, a_approval, a_designation, b_approval, b_designation)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssssss", $area, $fuel_supplier, $r_approval, $r_designation, $a_approval, $a_designation, $b_approval, $b_designation);
    }

    if ($stmt->execute()) {
        header("Location: main.php?success=" . urlencode("Settings saved successfully for area: $area"));
        exit;
    } else {
        header("Location: main.php?error=" . urlencode("Failed to save settings."));
        exit;
    }
}

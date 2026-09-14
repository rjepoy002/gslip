<?php
header("Content-Type: application/json");
require_once 'includes/config.php';

$conn = getDBConnection();
if (!$conn) {
    echo json_encode(["status" => "error", "message" => "DB connection failed"]);
    exit;
}

// $result = $conn->query("SELECT requested_by, plate_no, origin, destination FROM trial_uploads ORDER BY id DESC");
$result = $conn->query("SELECT area, route, origin, destination FROM routes WHERE route IS NOT NULL ORDER BY id ASC");
$rows = [];
while ($r = $result->fetch_assoc()) {
    $rows[] = $r;
}

echo json_encode(["status" => "success", "records" => $rows]);
$conn->close();
?>

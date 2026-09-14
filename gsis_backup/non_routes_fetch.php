<?php
ob_clean(); // Clear any previous output
header("Content-Type: application/json");
require_once 'includes/config.php';
$conn = getDBConnection(true); // JSON response on fail

$origins = [];

$sql = "SELECT DISTINCT origin FROM routes WHERE route IS NULL ORDER BY origin ASC";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    if (!in_array($row['origin'], $origins)) {
        $origins[] = $row['origin'];
    }
}

echo json_encode([
    'origins' => $origins,
]);
exit;
?>

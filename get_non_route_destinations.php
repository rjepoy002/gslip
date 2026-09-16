<?php
require_once 'includes/config.php';
$conn = getDBConnection();

$origin = $_GET['origin'] ?? '';
$destinations = [];

if ($origin !== '') {
  $stmt = $conn->prepare("SELECT DISTINCT id, destination FROM routes WHERE origin = ? AND route IS NULL ORDER BY destination ASC");
  $stmt->bind_param('s', $origin);
  $stmt->execute();
  $result = $stmt->get_result();

  while ($row = $result->fetch_assoc()) {
      $destinations[] = [
          'id' => $row['id'],
          'name' => $row['destination']
      ];
  }
}

echo json_encode([
    'debug_origin' => $origin,  // 👈 check if origin is received
    'destinations' => $destinations
]);
?>

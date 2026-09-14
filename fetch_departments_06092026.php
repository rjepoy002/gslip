<?php
require_once 'includes/config.php';
$conn = getDBConnection();

$area = $_GET['area'] ?? '';

$stmt = $conn->prepare("
  SELECT MIN(id) AS id, name
  FROM departments
  WHERE area = ?
    AND status = 'active'
  GROUP BY name
  ORDER BY name ASC
");

$stmt->bind_param("s", $area);
$stmt->execute();

$result = $stmt->get_result();
echo json_encode($result->fetch_all(MYSQLI_ASSOC));

<?php
require_once 'includes/config.php';

$conn = getDBConnection();

$area = $_GET['area'] ?? '';

$stmt = $conn->prepare("
    SELECT DISTINCT
        d.id,
        d.name
    FROM departments d
    INNER JOIN department_areas da
        ON da.department_id = d.id
    INNER JOIN areas a
        ON a.id = da.area_id
    WHERE a.area_name = ?
      AND d.status = 'active'
      AND a.status = 'active'
    ORDER BY d.name ASC
");

$stmt->bind_param("s", $area);
$stmt->execute();

$result = $stmt->get_result();

echo json_encode(
    $result->fetch_all(MYSQLI_ASSOC)
);
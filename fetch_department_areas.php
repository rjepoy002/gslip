<?php
require_once 'includes/config.php';
$conn = getDBConnection();

$department = $_GET['department'] ?? '';

// $stmt = $conn->prepare("
//   SELECT DISTINCT area
//   FROM departments
//   WHERE name = ?
//     AND status = 'active'
//   ORDER BY area ASC
// ");

$stmt = $conn->prepare("
    SELECT DISTINCT
        a.id AS area_id,
        a.area_name
    FROM department_areas da
    LEFT JOIN departments d
        ON d.id = da.department_id
    LEFT JOIN areas a
        ON a.id = da.area_id
    WHERE d.name = ?
      AND d.status = 'active'
      AND a.status = 'active'
    ORDER BY a.area_name ASC
");

$stmt->bind_param("s", $department);
$stmt->execute();

$result = $stmt->get_result();
echo json_encode($result->fetch_all(MYSQLI_ASSOC));

<?php
require_once 'includes/config.php';

$conn = getDBConnection();

$result = $conn->query("
    SELECT
        id,
        name
    FROM departments
    WHERE status = 'active'
    ORDER BY name ASC
");

echo json_encode(
    $result->fetch_all(MYSQLI_ASSOC)
);
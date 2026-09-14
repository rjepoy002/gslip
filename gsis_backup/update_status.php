<?php
require_once 'includes/config.php';
$conn = getDBConnection();

$data = json_decode(file_get_contents("php://input"), true);
if (!empty($data['ids'])) {
    $ids = implode(',', array_map('intval', $data['ids']));
    $conn->query("UPDATE gas_slips SET status = 'printed' WHERE id IN ($ids)");
}

$conn->close();
?>

<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

$conn = getDBConnection();
$conn->begin_transaction();

try {
    $user_id = $_SESSION['user_id'];
    $area = $_SESSION['area'] ?? 'Puerto Princesa [Main Office]';

    $date_issued    = DateTime::createFromFormat('m/d/Y', $_POST['date_issued'])->format('Y-m-d');
    $validity_until = $_POST['validity_until'];
    $vehicle_id     = (int)$_POST['vehicle_id'];
    $purpose        = $_POST['purpose'];
    $requested_by   = $_POST['requested_by'];
    $status         = 'pending';

    $primary_route_id = $_POST['destination'][0] ?? null;

    /* Insert gas slip */
    $stmt = $conn->prepare("
        INSERT INTO gas_slips
        (date_issued, validity_until, vehicle_id, route_id, purpose, requested_by, area, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "ssiissss",
        $date_issued,
        $validity_until,
        $vehicle_id,
        $primary_route_id,
        $purpose,
        $requested_by,
        $area,
        $status
    );
    $stmt->execute();

    $slip_id = $stmt->insert_id;

    /* Generate custom ID */
    $year = date('Y');
    $uid  = str_pad($_SESSION['user_id'], 2, '0', STR_PAD_LEFT);
    $sid  = str_pad($slip_id, 5, '0', STR_PAD_LEFT);
    $gas_slip_id = "$year-$uid-$sid";

    $conn->query("
        UPDATE gas_slips
        SET gas_slip_id = '$gas_slip_id'
        WHERE id = $slip_id
    ");

    /* Routes */
    if (!empty($_POST['destination'])) {
        $stmtR = $conn->prepare("
            INSERT INTO gas_slip_routes (gas_slip_id, route_id, sequence_no)
            VALUES (?, ?, ?)
        ");
        foreach ($_POST['destination'] as $i => $rid) {
            if ($rid) {
                $seq = $i + 1;
                $stmtR->bind_param("iii", $slip_id, $rid, $seq);
                $stmtR->execute();
            }
        }
    }

    /* Fuel */
    $stmtF = $conn->prepare("
        INSERT INTO fuel_requests
        (gas_slip_id, fuel_item_id, quantity, container)
        VALUES (?, ?, ?, ?)
    ");

    foreach ($_POST['fuel_item_id'] as $i => $fid) {
        $raw = trim($_POST['quantity'][$i] ?? '');
        $qty = strtoupper($raw) === 'FT' ? 'FT' : (float)$raw;
        $container = isset($_POST['container'][$i]) ? 'Yes' : 'No';

        if ($fid && ($qty === 'FT' || $qty > 0)) {
            $stmtF->bind_param("siss", $gas_slip_id, $fid, $qty, $container);
            $stmtF->execute();
        }
    }

    $conn->commit();
    header('Location: ../create_gas_slip.php?success=1');
    exit;

} catch (Exception $e) {
    $conn->rollback();
    header('Location: ../create_gas_slip.php?error=' . urlencode($e->getMessage()));
    exit;
}

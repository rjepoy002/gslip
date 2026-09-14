<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';
session_start();

header('Content-Type: application/json');

$conn = getDBConnection();
$input = json_decode(file_get_contents("php://input"), true);

$templateName = trim($input['name'] ?? '');
$rows         = $input['data'] ?? [];
$createdBy    = $_SESSION['user_id'] ?? 0;

if (!$templateName || empty($rows)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid template data'
    ]);
    exit;
}

$conn->begin_transaction();

try {

    // ============================
    // 1. INSERT TEMPLATE (HEADER)
    // ============================
    $stmt = $conn->prepare("
        INSERT INTO templates 
        (template_name, created_by, created_at, status) 
        VALUES (?, ?, NOW(), 'active')
    ");

    $stmt->bind_param("si", $templateName, $createdBy);
    $stmt->execute();

    $templateId = $conn->insert_id;


    // ============================
    // 2. LOOP EACH ROW
    // ============================
    foreach ($rows as $index => $row) {

        $rowOrder  = $index + 1;
        $vehicleId = $row['vehicle_id'] ?: null;
        $purpose   = $row['purpose'] ?? '';
        $requestedBy  = $row['requested_by'] ?? '';

        // ========================
        // INSERT TEMPLATE ROW
        // ========================
        $stmtRow = $conn->prepare("
            INSERT INTO template_rows
            (template_id, vehicle_id, purpose, requested_by, row_order)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmtRow->bind_param("iissi", $templateId, $vehicleId, $purpose, $requestedBy, $rowOrder);
        $stmtRow->execute();

        $rowId = $conn->insert_id;


        // ========================
        // ROUTES (DESTINATIONS)
        // ========================
        $routes = $row['destinations'] ?? [];

        foreach ($routes as $rIndex => $r) {

            $routeId = $r['route_id'] ?? null;
            $order   = $rIndex + 1;

            if (!$routeId) continue;

            $stmtRoute = $conn->prepare("
                INSERT INTO templates_routes
                (template_id, route_id, route_order, row_no, row_id)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmtRoute->bind_param(
                "iiiii",
                $templateId,
                $routeId,
                $order,
                $rowOrder,
                $rowId
            );

            $stmtRoute->execute();
        }

        // ========================
        // FUEL REQUESTS
        // ========================
        $fuelItems = $row['fuel_requests'] ?? [];

        foreach ($fuelItems as $f) {

            $fuelId   = $f['id'] ?? null;
            $qty      = $f['qty'] ?? 0;
            $container= $f['container'] ?? '';

            if (!$fuelId) continue;

            $stmtFuel = $conn->prepare("
                INSERT INTO templates_fuel
                (template_id, fuel_item_id, quantity, container, row_no, row_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmtFuel->bind_param(
                "iidsii",
                $templateId,
                $fuelId,
                $qty,
                $container,
                $rowOrder,
                $rowId
            );

            $stmtFuel->execute();
        }
    }

    $conn->commit();

    echo json_encode([
        'success' => true
    ]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
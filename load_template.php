<?php
session_start();

require_once 'includes/config.php';

$conn = getDBConnection();

header('Content-Type: application/json');

// ==========================
// AUTH
// ==========================
if (!isset($_SESSION['user_id'])) {

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);

    exit;
}

$userId = $_SESSION['user_id'];

// ==========================
// INPUT
// ==========================
$templateId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($templateId <= 0) {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid template ID'
    ]);

    exit;
}

// ==========================
// TEMPLATE HEADER
// ==========================
$stmt = $conn->prepare("
    SELECT *
    FROM templates
    WHERE id = ?
      AND created_by = ?
      AND status = 'active'
");

$stmt->bind_param("ii", $templateId, $userId);
$stmt->execute();

$template = $stmt->get_result()->fetch_assoc();

if (!$template) {

    echo json_encode([
        'success' => false,
        'message' => 'Template not found'
    ]);

    exit;
}

// ==========================
// TEMPLATE ROWS
// ==========================
$stmtRows = $conn->prepare("
    SELECT
        tr.*,

        v.plate_no,
        v.brand,
        v.model,
        v.category,
        v.km_per_liter

    FROM template_rows tr

    LEFT JOIN vehicles v
        ON v.id = tr.vehicle_id

    WHERE tr.template_id = ?

    ORDER BY tr.row_order ASC
");

$stmtRows->bind_param("i", $templateId);
$stmtRows->execute();

$rowsResult = $stmtRows->get_result();

$rows = [];

while ($row = $rowsResult->fetch_assoc()) {

    $rowId = $row['id'];

    // ======================
    // ROUTES
    // ======================
    $stmtRoutes = $conn->prepare("
        SELECT *
        FROM templates_routes
        WHERE row_id = ?
        ORDER BY route_order ASC
    ");

    $stmtRoutes->bind_param("i", $rowId);
    $stmtRoutes->execute();

    $routes = $stmtRoutes
        ->get_result()
        ->fetch_all(MYSQLI_ASSOC);

    // ======================
    // FUEL
    // ======================
    $stmtFuel = $conn->prepare("
        SELECT
            fuel_item_id AS id,
            quantity AS qty,
            container
        FROM templates_fuel
        WHERE row_id = ?
    ");

    $stmtFuel->bind_param("i", $rowId);
    $stmtFuel->execute();

    $fuel = $stmtFuel
        ->get_result()
        ->fetch_all(MYSQLI_ASSOC);

    $row['destinations'] = $routes;
    $row['fuel_requests'] = $fuel;

    $rows[] = $row;
}

// ==========================
// RESPONSE
// ==========================
echo json_encode([
    'success' => true,
    'template' => $template,
    'rows' => $rows
]);
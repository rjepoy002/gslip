<?php
session_start();

require_once 'includes/config.php';

header('Content-Type: application/json');

$conn = getDBConnection();

/* =========================================================
   AUTH CHECK
========================================================= */

if (!isset($_SESSION['user_id'])) {

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.'
    ]);

    exit;
}

/* =========================================================
   USER INFO
========================================================= */

$userId = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT 
        u.role,
        d.name
    FROM users u
    LEFT JOIN departments d 
        ON u.department_id = d.id
    WHERE u.id = ?
    LIMIT 1
");

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();

$current = $result->fetch_assoc();

$stmt->close();

/* =========================================================
   GET ALLOWED DEPARTMENT IDS
========================================================= */

$allowedDepartmentIds = [];

if (!empty($allowedFuelSupplierDepartments)) {

    $placeholders = implode(
        ',',
        array_fill(
            0,
            count($allowedFuelSupplierDepartments),
            '?'
        )
    );

    $types = str_repeat(
        's',
        count($allowedFuelSupplierDepartments)
    );

    $deptStmt = $conn->prepare("
        SELECT id
        FROM departments
        WHERE name IN ($placeholders)
    ");

    $deptStmt->bind_param(
        $types,
        ...$allowedFuelSupplierDepartments
    );

    $deptStmt->execute();

    $deptResult = $deptStmt->get_result();

    while ($row = $deptResult->fetch_assoc()) {

        $allowedDepartmentIds[] = (int)$row['id'];
    }

    $deptStmt->close();
}

/* =========================================================
   PERMISSION CHECK
========================================================= */

$departmentId = (int)$_SESSION['department_id'];

$canEditFuelSupplier =
    $current['role'] === 'admin' ||
    in_array(
        $departmentId,
        $allowedDepartmentIds
    );

if (!$canEditFuelSupplier) {

    echo json_encode([
        'success' => false,
        'message' => 'You are not allowed to modify fuel supplier settings.'
    ]);

    exit;
}

/* =========================================================
   INPUTS
========================================================= */

$areaId = intval($_POST['area_id'] ?? 0);

$fuelSupplier = trim(
    $_POST['fuel_supplier'] ?? ''
);

/* =========================================================
   VALIDATION
========================================================= */

if ($areaId <= 0) {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid area selected.'
    ]);

    exit;
}

if ($fuelSupplier === '') {

    echo json_encode([
        'success' => false,
        'message' => 'Fuel supplier is required.'
    ]);

    exit;
}

/* =========================================================
   UPDATE AREA FUEL SUPPLIER
========================================================= */

$updateStmt = $conn->prepare("
    UPDATE areas
    SET fuel_supplier = ?
    WHERE id = ?
");

$updateStmt->bind_param(
    "si",
    $fuelSupplier,
    $areaId
);

if ($updateStmt->execute()) {

    echo json_encode([
        'success' => true,
        'message' => 'Fuel supplier updated successfully.'
    ]);

} else {

    echo json_encode([
        'success' => false,
        'message' => 'Database update failed.'
    ]);
}

$updateStmt->close();
$conn->close();
?>
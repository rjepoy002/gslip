<?php

$conn = getDBConnection();

$userId     = $_SESSION['user_id'];
$role       = $_SESSION['role'];
$department = $_SESSION['department_id'];
$area       = $_SESSION['area'];
/* =========================================================
   LOAD USERS IN THIS DEPARTMENT
========================================================= */

$users = [];

$userStmt = $conn->prepare("
    SELECT 
        id,
        first_name,
        middle_name,
        last_name,
        designation
    FROM users
    WHERE department_id = ?
    AND status = 'active'
    ORDER BY last_name ASC, first_name ASC
");

$userStmt->bind_param("i", $department);
$userStmt->execute();

$result = $userStmt->get_result();

while ($userRow = $result->fetch_assoc()) {
    $users[] = $userRow;
}

$userStmt->close();

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
   GET FUEL SUPPLIER BASED ON USER AREA
========================================================= */

$fuelSupplier = '';

$fuelStmt = $conn->prepare("
    SELECT fuel_supplier
    FROM areas
    WHERE id = ?
    LIMIT 1
");

$fuelStmt->bind_param("i", $area);
$fuelStmt->execute();

$fuelResult = $fuelStmt->get_result();
$fuelData = $fuelResult->fetch_assoc();

if ($fuelData) {
    $fuelSupplier = $fuelData['fuel_supplier'];
}

$fuelStmt->close();

$canEditFuelSupplier = false;

if (
    $role === 'admin' ||
    in_array(
        (int)$department,
        $allowedDepartmentIds
    )
) {
    $canEditFuelSupplier = true;
}

/* =========================================================
   GET CURRENT USER / DEPARTMENT SETTINGS
========================================================= */

$settingsStmt = $conn->prepare("
    SELECT
        a.id,
        a.area_name,
        a.office_address,
        a.fuel_supplier,
        d.name AS department_name
    FROM users u

    LEFT JOIN departments d
        ON d.id = u.department_id

    LEFT JOIN areas a
        ON a.id = u.area_id

    WHERE u.id = ?
    LIMIT 1
");

$settingsStmt->bind_param("i", $userId);
$settingsStmt->execute();

$settingsResult = $settingsStmt->get_result();

$current = $settingsResult->fetch_assoc();

$settingsStmt->close();

/* =========================================================
   LOAD ACCESSIBLE AREAS
========================================================= */

$areas = [];

/* ---------------------------------------------
ADMIN OR ISD = ALL AREAS
--------------------------------------------- */

if (
    $role === 'admin' ||
    strtoupper(trim($current['department_name'])) === 'ISD'
) {

    $areaStmt = $conn->prepare("
        SELECT 
            id,
            area_name,
            office_address,
            fuel_supplier
        FROM areas
        ORDER BY area_name ASC
    ");

}

/* ---------------------------------------------
OTHER ALLOWED DEPARTMENTS
--------------------------------------------- */

else {

    $areaStmt = $conn->prepare("
        SELECT
            a.id,
            a.area_name,
            a.office_address,
            a.fuel_supplier
        FROM areas a

        INNER JOIN department_areas da
            ON da.area_id = a.id

        WHERE da.department_id = ?

        ORDER BY a.area_name ASC
    ");

    $areaStmt->bind_param(
        "i",
        $department
    );
}

$areaStmt->execute();

$areaResult = $areaStmt->get_result();

while ($row = $areaResult->fetch_assoc()) {

    $areas[] = $row;
}

$areaStmt->close();


/* =========================================================
   LOAD ALL DEPARTMENTS
========================================================= */

$departments = [];

$departmentStmt = $conn->prepare("
    SELECT
        id,
        name
    FROM departments
    ORDER BY name ASC
");

$departmentStmt->execute();

$departmentResult = $departmentStmt->get_result();

while ($row = $departmentResult->fetch_assoc()) {

    $departments[] = $row;
}

$departmentStmt->close();

?>
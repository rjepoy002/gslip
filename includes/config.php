<?php
date_default_timezone_set('Asia/Manila');

// App version
define('APP_VERSION', '3.7');

// Base URL
define('LOGIN_PAGE', 'index.php');

// Base URL (used for QR verification links)
define('APP_BASE_URL', 'https://192.168.18.14/gslip');
define('VERIFY_BASE_URL', 'https://paleco.net/paleco/gslip');

// QR Code secret key (used for signing/verifying QR codes)
define('QR_SECRET_KEY', 'GASSLIP2026SECRETKEY');

// Database credentials
$host = "localhost";
$username = "root";
$password = "";
$database = "gsccxzp_pal_db_test";

// Allowed departments for fuel supplier editing
$allowedFuelSupplierDepartments = [
    'ISD',
    'ASOD',
    'ANOD'
];

// Function to get DB connection safely
function getDBConnection($expectJson = false) {
    // These must be declared inside the function scope if used here
    $host = "localhost";
    $username = "root";
    $password = "";
    $database = "gsccxzp_pal_db_test";

    $conn = new mysqli($host, $username, $password, $database);

    if ($conn->connect_error) {
        if ($expectJson) {
            $response = [
                "status" => "error",
                "message" => "Database connection failed: " . $conn->connect_error,
                "rows_uploaded" => 0,
                "elapsed_time" => 0
            ];
            ob_clean();
            header("Content-Type: application/json");
            echo json_encode($response);
            exit;
        } else {
            die("Database connection failed: " . $conn->connect_error);
        }
    }

    return $conn;
}
?>

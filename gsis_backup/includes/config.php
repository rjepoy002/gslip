<?php
date_default_timezone_set('Asia/Manila');

// Database credentials
$host = "localhost";
$username = "root";
$password = "";
$database = "gsccxzp_pal_db";

// Function to get DB connection safely
function getDBConnection($expectJson = false) {
    // These must be declared inside the function scope if used here
    $host = "localhost";
    $username = "root";
    $password = "";
    $database = "gsccxzp_pal_db";

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

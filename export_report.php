<?php
session_start();

require_once 'includes/config.php';

$conn = getDBConnection();

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['session_token'])
) {
    exit('Unauthorized');
}

$reportType = $_GET['report_type'] ?? '';
$dateFrom   = $_GET['date_from'] ?? '';
$dateTo     = $_GET['date_to'] ?? '';

switch ($reportType) {

    case 'gas_slips':
        include 'exports/export_gas_slips.php';
        break;

    case 'vehicle_consumption':
        include 'exports/export_vehicle_consumption.php';
        break;

    case 'route_utilization':
        include 'exports/export_route_utilization.php';
        break;

    case 'fuel_utilization':
        include 'exports/export_fuel_utilization.php';
        break;

    default:
        exit('Invalid report type');
}
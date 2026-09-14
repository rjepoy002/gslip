<?php
session_start();
require_once 'includes/config.php';
// require_once 'includes/pagination_setup.php';

$conn = getDBConnection();
// 🔐 AUTH GUARD
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['session_token']) ||
    !isset($_SESSION['role']) ||
    !isset($_SESSION['department_id']) ||
    !isset($_SESSION['area'])
) {
    header('Location: index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];
$department = $_SESSION['department_id'];
$area = $_SESSION['area'];
$hasDates = !empty($_SESSION['date_from']) || !empty($_SESSION['date_to']);

$isAdmin        = ($role === 'admin');
$isRecommender  = !empty($_SESSION['is_recommender']);
$isApprover     = !empty($_SESSION['is_approver']);
$isPrivateApprover     = !empty($_SESSION['is_private_approver']);
$isFullReport         = !empty($_SESSION['is_full_report']);
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to'] ?? date('Y-m-d');

// Departments allowed to generate reports for all departments
$fullReportDepartments = [
    4,  // FSD
    5,  // Audit
];

$canViewAllReports = in_array($department, $fullReportDepartments);
// Is the user requesting all departments?
$viewAllDepartments = $canViewAllReports && isset($_GET['view_all']);

$departmentFilter = '';

if (
    !$isAdmin &&
    !$isPrivateApprover &&
    !$viewAllDepartments
) {
    $departmentFilter = " AND u.department_id = {$department} ";
}

/* 🔎 DEBUG — TEMPORARY */
// var_dump($dateFrom, $dateTo);
// exit;
// var_dump($_SESSION['role'], $_SESSION['user_id'], $_SESSION['area'], $_SESSION['department_id']);
// exit;



?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard | e-GSlip</title>
    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/icons/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="assets/js/sweetalert2.all.min.js"></script>
</head>

<body>

<?php include 'includes/sidebar.php'; ?>

<div class="app-content">

    <!-- Main Page Content -->
    <main class="main-content">
        <!-- Temporary Empty State -->
        <div class="panel-container">
            <!-- Page Header -->
            <div class="page-header mb-4">
              <h1 class="mb-1">Reports</h1>
              <p class="page-subtitle mb-1">
                    Generate, analyze, and export gas slip reports and utilization statistics
              </p>
            </div>

            <!-- Reports Filter -->
            <div class="card p-3 mb-4">

            <form method="GET" id="reportForm">

                <div class="row g-3 align-items-end">

                    <div class="col-md-2">
                        <label class="form-label">Date From</label>
                        <input
                            type="text"
                            name="date_from"
                            id="date_from"
                            class="form-control"
                            placeholder="Select start date"
                            value="<?= htmlspecialchars($dateFrom); ?>"
                        >
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Date To</label>
                        <input
                            type="text"
                            name="date_to"
                            id="date_to"
                            class="form-control"
                            placeholder="Select end date"
                            value="<?= htmlspecialchars($dateTo); ?>"
                        >
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Report Type</label>

                        <select
                            name="report_type"
                            id="reportType"
                            class="form-select"
                            required
                        >
                            <option value="">-- Select Report --</option>

                            <option value="gas_slips"
                                <?= ($_GET['report_type'] ?? '') === 'gas_slips' ? 'selected' : ''; ?>>
                                Gas Slip Transactions
                            </option>

                            <option value="vehicle_consumption"
                                <?= ($_GET['report_type'] ?? '') === 'vehicle_consumption' ? 'selected' : ''; ?>>
                                Vehicle Fuel Consumption
                            </option>

                            <option value="route_utilization"
                                <?= ($_GET['report_type'] ?? '') === 'route_utilization' ? 'selected' : ''; ?>>
                                Route Utilization
                            </option>

                            <option value="fuel_utilization"
                                <?= ($_GET['report_type'] ?? '') === 'fuel_utilization' ? 'selected' : ''; ?>>
                                Fuel Item Utilization
                            </option>

                        </select>
                    </div>

                    <?php if ($canViewAllReports): ?>
                    <div class="col-md-2">
                        <div class="form-check mt-4">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="view_all"
                                name="view_all"
                                value="1"
                                <?= isset($_GET['view_all']) ? 'checked' : ''; ?>
                            >

                            <label class="form-check-label" for="view_all">
                                Include All Departments
                            </label>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="col-md-1 d-grid">
                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            <i class="fas fa-search"></i>
                            Preview
                        </button>
                    </div>

                    <?php if (!empty($_GET['report_type'])): ?>
                    <div class="col-md-1 d-grid">
                        <a
                            href="export_report.php?<?= http_build_query($_GET); ?>"
                            class="btn btn-success"
                        >
                            <i class="fas fa-file-csv"></i>
                            Export
                        </a>
                    </div>
                    <?php endif; ?>

                </div>

            </form>

            </div>

            <div class="table-responsive">

            <?php

            $reportType = $_GET['report_type'] ?? '';
            // $dateFrom   = $_GET['date_from'] ?? '';
            // $dateTo     = $_GET['date_to'] ?? '';

            switch ($reportType) {

                case 'gas_slips':
                    include 'reports/report_gas_slips.php';
                    break;

                case 'vehicle_consumption':
                    include 'reports/report_vehicle_consumption.php';
                    break;

                case 'route_utilization':
                    include 'reports/report_route_utilization.php';
                    break;

                case 'fuel_utilization':
                    include 'reports/report_fuel_utilization.php';
                    break;

                default:
                    echo '
                        <div class="text-center text-muted py-5">
                            Select a report and click Preview.
                        </div>
                    ';
                    break;
            }

            ?>

            </div>


        </div>

    </main>

</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/sweetalert2.all.min.js"></script>

<script src="assets/js/app-ui.js"></script>
<script src="assets/js/notifications.js"></script>

<?php if (!empty($_SESSION['swal_success'])): ?>
<script>
Swal.fire({
  icon: 'success',
  title: 'Success',
  text: '<?= $_SESSION['swal_success']; ?>',
  timer: 2000,
  showConfirmButton: false
});
</script>
<?php unset($_SESSION['swal_success']); endif; ?>

<script>
const fromPicker = flatpickr("#date_from", {
    dateFormat: "Y-m-d",
    allowInput: true,
    maxDate: "today",
    onChange: function(selectedDates) {
        if (selectedDates.length) {
            toPicker.set('minDate', selectedDates[0]);
        }
    }
});

const toPicker = flatpickr("#date_to", {
    dateFormat: "Y-m-d",
    allowInput: true,
    maxDate: "today"
});
</script>

</body>
</html>

<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/dashboard/counts.php';
require_once 'includes/dashboard/recent.php';
require_once 'includes/dashboard/approved.php';
require_once 'includes/dashboard/fuel.php';
require_once 'includes/dashboard/vehicles.php';
require_once 'includes/dashboard/routes.php';
require_once 'includes/dashboard/user.php';
require_once 'includes/dashboard/fuel_items.php';
require_once 'includes/dashboard/drivers.php';

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
$role = $_SESSION['role'] ?? '';
$department = $_SESSION['department_id'] ?? 0;
$area = $_SESSION['area'];

$isAdmin        = ($role === 'admin');
$isRecommender  = !empty($_SESSION['is_recommender']);
$isApprover     = !empty($_SESSION['is_approver']);
$isPrivateApprover     = !empty($_SESSION['is_private_approver']);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>

    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/icons/bootstrap-icons.css">
    <script src="assets/js/sweetalert2.all.min.js"></script>
    <script src="assets/js/chart.js/chart.min.js"></script>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<div class="app-content">

    <main class="main-content">
        <div class="panel-container">
            <!-- Page Header -->
            <div class="page-header mb-4">
              <h1 class="mb-1">Dashboard</h1>
              <p class="page-subtitle mb-1">
                  Summary of gas slip activities, request statuses, and recent transactions.
              </p>
            </div>

            <?php include 'includes/dashboard/cards.php'; ?>
            <?php include 'includes/dashboard/charts.php'; ?>


        </div>
    </main>

</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/sweetalert2.all.min.js"></script>

<script src="assets/js/app-ui.js"></script>
<script src="assets/js/gas-slip.js"></script>

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
window.dashboardData = {
    monthlyData: <?= json_encode($monthlyData ?? []) ?>,

    dieselData: <?= json_encode($dieselData ?? []) ?>,
    unleadedData: <?= json_encode($unleadedData ?? []) ?>,

    topVehicleLabels: <?= json_encode($topVehicleLabels ?? []) ?>,
    topVehicleData: <?= json_encode($topVehicleData ?? []) ?>,

    deptLabels: <?= json_encode($deptLabels ?? []) ?>,
    deptTotals: <?= json_encode($deptTotals ?? []) ?>,

    canViewDepartmentChart: <?= ($isPrivateApprover || $isAdmin) ? 'true' : 'false' ?>
};
</script>

<script src="assets/js/dashboard.js"></script>
<script src="assets/js/notifications.js"></script>

<?php
$autoRefreshTime = 60000;
include 'includes/autorefresh.php';
?>

</body>
</html>
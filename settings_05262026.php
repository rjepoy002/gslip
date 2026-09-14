<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/settings/load_users.php';
require_once 'includes/settings/load_departments.php';
require_once 'includes/settings/load_recommenders.php';
require_once 'includes/settings/load_department_approvers.php';

$conn = getDBConnection();

/* =========================================================
   AUTH GUARD
========================================================= */
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

$userId     = $_SESSION['user_id'];
$role       = $_SESSION['role'];
$department = $_SESSION['department_id'];
$area       = $_SESSION['area'];

function formatUserDisplayName($user)
{
    $middleInitial = '';

    if (!empty($user['middle_name'])) {

        $middleInitial =
            strtoupper(substr($user['middle_name'], 0, 1)) . '.';
    }

    return trim(
        $user['last_name'] . ', ' .
        $user['first_name'] . ' ' .
        $middleInitial
    );
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings | e-GSlip</title>

    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/icons/bootstrap-icons.css">

    <script src="assets/js/sweetalert2.all.min.js"></script>
</head>

<body>

<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/modals/recommender_modal.php'; ?>
<?php include 'includes/modals/primary_approver_modal.php'; ?>
<?php include 'includes/modals/private_vehicle_approver_modal.php'; ?>
<?php include 'includes/modals/department_approver_modal.php'; ?>

<div class="app-content">

    <main class="main-content">

        <div class="panel-container">

            <!-- PAGE HEADER -->
            <div class="page-header mb-4">
                <h1 class="mb-1">Settings</h1>

                <p class="page-subtitle mb-1">
                    Configure department approval assignments, recommenders, approvers,
                    and system-wide e-GSlip settings.
                </p>
            </div>

            <div class="row g-4">

                <?php include 'includes/settings/cards/recommender_card.php'; ?>

                <?php include 'includes/settings/cards/primary_approver_card.php'; ?>

                <?php include 'includes/settings/cards/fuel_supplier_card.php'; ?>

            </div>

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

window.departmentApprovers =
    <?= json_encode($departmentApprovers); ?>;

</script>

<script src="assets/js/settings.js"></script>

</body>
</html>
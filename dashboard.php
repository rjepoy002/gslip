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


/* =========================================================
   SECONDARY RECOMMENDER DELEGATION NOTICE
========================================================= */

$secondaryDelegation = null;

$delegationStmt = $conn->prepare("
    SELECT
        rd.id,
        rd.start_date,
        rd.end_date,
        CONCAT(
            primary_user.first_name,
            ' ',
            CASE
                WHEN primary_user.middle_name IS NOT NULL
                     AND primary_user.middle_name != ''
                THEN CONCAT(LEFT(primary_user.middle_name, 1), '. ')
                ELSE ''
            END,
            primary_user.last_name
        ) AS primary_name
    FROM recommender_delegations rd

    INNER JOIN users primary_user
        ON primary_user.id = rd.primary_recommender_id

    INNER JOIN users secondary_user
        ON secondary_user.id = rd.secondary_recommender_id

    WHERE rd.secondary_recommender_id = ?
      AND rd.department_id = ?
      AND secondary_user.area_id = ?
      AND rd.status = 'active'
      AND CURDATE() BETWEEN rd.start_date AND rd.end_date

    ORDER BY rd.id DESC
    LIMIT 1
");

if ($delegationStmt) {

    $delegationStmt->bind_param(
        "iii",
        $userId,
        $department,
        $area
    );

    $delegationStmt->execute();

    $delegationResult = $delegationStmt->get_result();

    if ($delegationResult->num_rows > 0) {
        $secondaryDelegation = $delegationResult->fetch_assoc();
    }

    $delegationStmt->close();
}

/* =========================================================
   SECONDARY APPROVER ASSIGNMENT NOTICE
========================================================= */

$secondaryApprover = null;

$secondaryApproverStmt = $conn->prepare("
    SELECT
        da.id,
        da.department_id,
        d.name AS department_name,

        CONCAT(
            primary_user.first_name,
            ' ',
            CASE
                WHEN primary_user.middle_name IS NOT NULL
                     AND primary_user.middle_name != ''
                THEN CONCAT(LEFT(primary_user.middle_name, 1), '. ')
                ELSE ''
            END,
            primary_user.last_name
        ) AS primary_name,

        primary_user.designation AS primary_designation

    FROM department_approvers da

    INNER JOIN departments d
        ON d.id = da.department_id

    INNER JOIN department_approvers primary_da
        ON primary_da.department_id = da.department_id
        AND primary_da.is_primary = 1

    INNER JOIN users primary_user
        ON primary_user.id = primary_da.user_id

    WHERE da.user_id = ?
      AND da.is_primary = 0
      AND d.status = 'active'

    ORDER BY da.id DESC
    LIMIT 1
");

if ($secondaryApproverStmt) {

    $secondaryApproverStmt->bind_param(
        "i",
        $userId
    );

    $secondaryApproverStmt->execute();

    $secondaryApproverResult = $secondaryApproverStmt->get_result();

    if ($secondaryApproverResult->num_rows > 0) {
        $secondaryApprover = $secondaryApproverResult->fetch_assoc();
    }

    $secondaryApproverStmt->close();
}

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

            <?php if ($secondaryDelegation): ?>

            <div class="secondary-recommender-notice mb-4">

                <div class="secondary-notice-icon">
                    <i class="fa-solid fa-user-check"></i>
                </div>

                <div class="secondary-notice-content">

                    <div class="secondary-notice-title">
                        Secondary Recommender Assignment
                    </div>

                    <div class="secondary-notice-text">
                        You have been designated as a Secondary Recommender by
                        <strong>
                            <?= htmlspecialchars(strtoupper($secondaryDelegation['primary_name'])) ?>
                        </strong>.
                    </div>

                    <div class="secondary-notice-period">
                        <span>
                            <i class="fa-regular fa-calendar"></i>
                            Delegation Period:
                        </span>

                        <strong>
                            <?= date('F j, Y', strtotime($secondaryDelegation['start_date'])) ?>
                            –
                            <?= date('F j, Y', strtotime($secondaryDelegation['end_date'])) ?>
                        </strong>
                    </div>

                    <div class="secondary-notice-note">
                        You may recommend gas slips within your assigned department and area
                        during this period.
                    </div>

                </div>

            </div>

            <?php endif; ?>

            <?php if ($secondaryApprover): ?>

            <div class="secondary-recommender-notice mb-4">

                <div class="secondary-notice-icon">
                    <i class="fa-solid fa-user-shield"></i>
                </div>

                <div class="secondary-notice-content">

                    <div class="secondary-notice-title">
                        
                        <span class="secondary-notice-status">Secondary Approver Assignment</span>
                    </div>

                    <div class="secondary-notice-text">
                        You have been designated as a Secondary Approver for
                        <strong>
                            <?= htmlspecialchars($secondaryApprover['department_name']) ?>
                        </strong>.
                    </div>

                    <div class="secondary-notice-period">
                        <span>
                            <i class="fa-solid fa-user-check"></i>
                            Primary Approver:
                        </span>

                        <strong>
                            <?= htmlspecialchars(strtoupper($secondaryApprover['primary_name'])) ?>
                        </strong>
                    </div>

                    <?php if (!empty($secondaryApprover['primary_designation'])): ?>
                    <div class="secondary-notice-note">
                        <?= htmlspecialchars($secondaryApprover['primary_designation']) ?>
                    </div>
                    <?php endif; ?>

                    <div class="secondary-notice-note">
                        You may approve gas slips as an alternate approver when
                        the primary approver is unavailable.
                    </div>

                </div>

            </div>

            <?php endif; ?>

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
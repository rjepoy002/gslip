<?php
session_start();
require_once 'includes/config.php';

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

/* =========================================================
   GET CURRENT DEPARTMENT SETTINGS
========================================================= */

$settingsStmt = $conn->prepare("
    SELECT 
        das.recommender_user_id,
        das.approver_user_id,
        a.id,
        a.area_name,
        a.fuel_supplier,
        d.name AS department_name
    FROM users u

    LEFT JOIN departments d
        ON d.id = u.department_id

    LEFT JOIN areas a
        ON a.id = u.area_id

    LEFT JOIN department_approval_settings das
        ON das.department_id = u.department_id

    WHERE u.id = ?
    LIMIT 1
");

$settingsStmt->bind_param("i", $userId);
$settingsStmt->execute();
$settingsResult = $settingsStmt->get_result();
$current = $settingsResult->fetch_assoc();
$settingsStmt->close();
// echo $current['recommender_user_id'] . '<br>';
// echo $current['approver_user_id'] . '<br>';
                                    
$currentRecommenderId = (int)($current['recommender_user_id'] ?? 0);
$currentApproverId    = (int)($current['approver_user_id'] ?? 0);
                                    

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
    in_array(
        strtolower(trim($current['department_name'])),
        $allowedFuelSupplierDepartments
    )
) {
    $canEditFuelSupplier = true;
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

<?php include 'includes/admin_sidebar.php'; ?>

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

            <!-- SETTINGS FORM -->
            <form action="save_settings.php" method="POST">

                <div class="row g-4">

                    <!-- APPROVAL SETTINGS -->
                    <div class="col-md-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body">

                                <h5 class="mb-4">
                                    <i class="fas fa-user-check me-2"></i>
                                    Department Approval Settings
                                </h5>

                                <!-- RECOMMENDER -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        Recommender
                                    </label>
                                    
                                    <select 
                                        name="recommender_user_id"
                                        class="form-select"
                                    >
                                        <option value="">-- Select Recommender --</option>

                                        <?php if (!empty($users)): ?>
                                            <?php foreach ($users as $userRow): ?>
                                                
                                                <!-- display name, designation -->
                                                <?php
                                                    $middleInitial = '';

                                                    if (!empty($userRow['middle_name'])) {
                                                        $middleInitial = strtoupper(substr($userRow['middle_name'], 0, 1)) . '.';
                                                    }

                                                    $fullName = trim(
                                                        $userRow['last_name'] . ', ' .
                                                        $userRow['first_name'] . ' ' .
                                                        $middleInitial
                                                    );

                                                    $displayName = $fullName;

                                                    if (!empty($userRow['designation'])) {
                                                        $displayName .= ' [' . $userRow['designation'] . ']';
                                                    }
                                                ?>

                                                <option 
                                                    value="<?= htmlspecialchars($userRow['id']) ?>"
                                                    <?= (
                                                        isset($currentRecommenderId) &&
                                                        $currentRecommenderId === (int)$userRow['id']
                                                    ) ? 'selected' : ''; ?>
                                                >
                                                    <?= htmlspecialchars($displayName) ?>
                                                </option>

                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <!-- APPROVER -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        Approver
                                    </label>

                                    <select 
                                        name="approver_user_id"
                                        class="form-select"
                                    >
                                        <option value="">-- Select Approver --</option>

                                        <?php if (!empty($users)): ?>
                                            <?php foreach ($users as $userRow): ?>

                                                <?php
                                                    $middleInitial = '';

                                                    if (!empty($userRow['middle_name'])) {
                                                        $middleInitial = strtoupper(substr($userRow['middle_name'], 0, 1)) . '.';
                                                    }

                                                    $fullName = trim(
                                                        $userRow['last_name'] . ', ' .
                                                        $userRow['first_name'] . ' ' .
                                                        $middleInitial
                                                    );

                                                    $displayName = $fullName;

                                                    if (!empty($userRow['designation'])) {
                                                        $displayName .= ' [' . $userRow['designation'] . ']';
                                                    }
                                                ?>

                                                    <option 
                                                        value="<?= htmlspecialchars($userRow['id']) ?>"
                                                        <?= (
                                                            isset($currentApproverId) &&
                                                            $currentApproverId === (int)$userRow['id']
                                                        ) ? 'selected' : ''; ?>
                                                    >
                                                        <?= htmlspecialchars($displayName) ?>
                                                    </option>

                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- SYSTEM SETTINGS -->
                    <div class="col-md-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body">

                                <h5 class="mb-4">
                                    <i class="fas fa-gas-pump me-2"></i>
                                    Fuel Supplier Settings
                                </h5>

                                <div class="mb-3">

                                    <label class="form-label fw-semibold">
                                        Fuel Supplier
                                    </label>

                                    <input 
                                        type="text"
                                        name="fuel_supplier"
                                        class="form-control"
                                        value="<?= htmlspecialchars($fuelSupplier ?? ''); ?>"
                                        <?= !$canEditFuelSupplier ? 'readonly' : ''; ?>
                                    >

                                    <?php if (!$canEditFuelSupplier): ?>
                                        <small class="text-muted">
                                            Only ISD - Puerto Princesa [Main Office]
                                            can modify this setting.
                                        </small>
                                    <?php endif; ?>

                                </div>

                            </div>
                        </div>
                    </div>

                </div>

                <!-- SAVE BUTTON -->
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        Save Settings
                    </button>
                </div>

            </form>

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


</body>
</html>
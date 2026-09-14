<?php
session_start();
require_once 'includes/config.php';

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

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

/* =========================================
   DASHBOARD COUNTS
========================================= */

    if ($role === 'approver') {

        // Count gas slips approved by this approver
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total
            FROM gas_slips
            WHERE approved_by = ?
        ");

        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total = $row['total'] ?? 0;

    } else {

        // Normal user → own gas slips
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total
            FROM gas_slips
            WHERE user_id = ?
        ");

        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total = $row['total'] ?? 0;
    }


/* =========================================
   RECENT GAS SLIPS
========================================= */

$recentQuery = "
    SELECT 
        gs.id, 
        gs.gas_slip_id, 
        gs.date_issued, 
        gs.requested_by,
        gs.purpose,
        gs.status, 
        gs.approved_by,
        gs.approved_at,
        gs.printed_at,
        u.id AS user_id,
        v.plate_no,
        r.origin, 
        r.destination,

        CONCAT(
        MIN(r.origin),
        ' → ',
        GROUP_CONCAT(
            r.destination
            ORDER BY gsr.id
            SEPARATOR ' → '
        )
        ) AS route_path

    FROM gas_slips gs
    LEFT JOIN gas_slip_routes gsr ON gsr.gas_slip_id = gs.id
    LEFT JOIN routes r ON r.id = gsr.route_id
    LEFT JOIN users u ON u.id = gs.user_id
    LEFT JOIN vehicles v ON v.id = gs.vehicle_id

";

if ($role === 'user') {
    $recentQuery .= " WHERE user_id = " . intval($user_id);
}

$recentQuery .= "GROUP BY gs.id ORDER BY gs.date_issued DESC LIMIT 5";

$recentResult = $conn->query($recentQuery);
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

        <!-- =========================
            STAT CARDS
        ========================== -->

        <div class="row g-4 mb-4">

            <!-- TOTAL CARD -->
            <div class="col-md-3">
                <a href="create_gas_slip.php" class="text-decoration-none">
                    <div class="card text-bg-primary shadow" 
                        style="border: 4px solid #ffffff;">
                        <div class="card-body p-3">
                            <div class="fw-semibold">
                                <?php if ($role === 'approver'): ?>
                                    Total Gas Slips Approved
                                <?php else: ?>
                                    Total Gas Slips Created
                                <?php endif; ?>
                            </div>
                            <!-- <hr class="my-2"> -->
                            <div class="text-center">
                                <span class="fw-bold" style="font-size: 4rem;">
                                    <?= $total ?? 0 ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- STATUS SECTION -->
            <div class="col-md-9 mb-4">
                
                <div class="fw-semibold mb-2 p-1">Active Gas Slip Status Overview</div>
                <!-- <hr class="border-primary border-2 opacity-50 my-2"> -->
                <hr class="hr-modern my-2">

                <div class="row g-3">

                    <?php if ($role !== 'approver'): ?>
                        <div class="col-md-4">
                            <a href="draft_gas_slips.php" class="text-decoration-none">
                                <div class="card text-bg-secondary shadow" 
                                    style="border: 4px solid #ffffff;">
                                    <div class="card-body p-3">
                                        <div class="small fw-semibold">Draft</div>
                                        <!-- <hr class="my-2"> -->
                                        <div class="fs-2 fw-bold text-center">
                                            <?= $counts['draft'] ?? 0 ?>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endif; ?>

                    <div class="col-md-4">
                        <a href="pending_gas_slips.php" class="text-decoration-none">
                            <div class="card text-bg-info text-white shadow" 
                                style="border: 4px solid #ffffff;">
                                <div class="card-body p-3">
                                    <div class="small fw-semibold">Pending</div>
                                    <!-- <hr class="my-2"> -->
                                    <div class="fs-2 fw-bold text-center">
                                        <?= $counts['pending'] ?? 0 ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>

                    <div class="col-md-4">
                        <a href="approved_slips.php" class="text-decoration-none">
                            <div class="card text-bg-success shadow" 
                                style="border: 4px solid #ffffff;">
                                <div class="card-body p-3">
                                    <div class="small fw-semibold">Approved</div>
                                    <!-- <hr class="my-2"> -->
                                    <div class="fs-2 fw-bold text-center">
                                        <?= $counts['approved'] ?? 0 ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>

                </div>
            </div>

        </div>

            <!-- =========================
                RECENT GAS SLIPS
            ========================== -->

            <div class="card p-0 mb-4">

                <div class="fw-semibold mb-0 px-3" style="padding-top: 10px; color: #64748b;">Recent Gas Slips</div>
                <hr class="hr-modern my-2">

                <table class="styled-table excel-table mt-0">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Gas Slip No.</th>
                            <th>Date Issued</th>
                            <th>Requested By</th>
                            <th>Purpose</th>
                            <th>Route</th>
                        </tr>
                    </thead>
                    <tbody>

                    <?php if ($recentResult && $recentResult->num_rows > 0): ?>
                        <?php $no = 1; ?>
                        <?php while ($row = $recentResult->fetch_assoc()): ?>
                            <tr>
                                <td><?= $no++; ?> </td>
                                <td>
                                    <strong><?= htmlspecialchars($row['gas_slip_id']); ?></strong>
                                    <?php
                                    if (!empty($row['printed_at'])) {
                                    echo '<span class="badge bg-primary" title="Approved"><i class="bi bi-check-lg"></i></span>';
                                    }else{
                                    switch ($row['status']) {
                                        case 'recommended':
                                        echo '<span class="badge bg-warning ms-1" title="Recommended">R</span>';
                                        break;
                                        case 'approved':
                                        echo '<span class="badge bg-success ms-1" title="Approved">A</span>';
                                        break;
                                        case 'pending':
                                        echo '<span class="badge bg-info ms-1" title="Pending">P</span>';
                                        break;
                                        case 'draft':
                                        echo '<span class="badge bg-secondary ms-1" title="Draft">D</span>';
                                        break;
                                        default:
                                        echo '<span class="badge bg-light ms-1 text-black" title="Unknown">?</span>';
                                    }
                                    }
                                    ?>
                                </td>

                                <td>
                                    <?= date('M d, Y · h:i A', strtotime($row['date_issued'])); ?>
                                </td>
                                
                                <td>
                                    <?= htmlspecialchars($row['requested_by']); ?>
                                </td>

                                <td><?= htmlspecialchars($row['purpose']) ?></td>

                                <td>
                                    <?= htmlspecialchars($row['route_path']) ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">
                                No recent records found
                            </td>
                        </tr>
                    <?php endif; ?>

                    </tbody>
                </table>

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

</body>
</html>
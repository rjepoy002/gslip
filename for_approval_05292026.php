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

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];
$department = $_SESSION['department_id'];
$area = $_SESSION['area'];
$hasDates = !empty($_SESSION['date_from']) || !empty($_SESSION['date_to']);

$isAdmin        = ($role === 'admin');
$isRecommender  = !empty($_SESSION['is_recommender']);
$isApprover     = !empty($_SESSION['is_approver']);

/* 🔎 DEBUG — TEMPORARY */
// var_dump($dateFrom, $dateTo);
// exit;
// var_dump($_SESSION['role'], $_SESSION['user_id'], $_SESSION['area'], $_SESSION['department_id']);
// exit;


/* =========================================================
 QUERY ACCOUNTS FOR APPROVAL
========================================================= */
// $conn->begin_transaction();

if($isApprover){
    $stmt = $conn->prepare("
            SELECT 
                u.*,
                d.name AS department_name,
                a.area_name
            FROM users u

            LEFT JOIN departments d
                ON d.id = u.department_id

            LEFT JOIN areas a
                ON a.id = u.area_id

            WHERE u.status = 'inactive'
            AND u.department_id = (
                SELECT u2.department_id
                FROM users u2
                WHERE u2.id = ?
            );
    ");

    $stmt->bind_param(
        'i',$userId
    );
}elseif($isAdmin){
    $stmt = $conn->prepare("
            SELECT 
                u.*,
                d.name AS department_name,
                a.area_name
            FROM users u

            LEFT JOIN departments d
                ON d.id = u.department_id

            LEFT JOIN areas a
                ON a.id = u.area_id

            WHERE u.status = 'inactive'
    ");
} 

$stmt->execute();
$result = $stmt->get_result();

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
              <h1 class="mb-1">New Accounts For Approval</h1>
              <p class="page-subtitle mb-1">
                  Review and manage newly registered user accounts pending verification
              </p>
            </div>

            <!-- Recent Activity -->
            <table class="styled-table excel-table">
                <thead>
                    <tr class="group-header">
                      <th>No.</th>
                      <th>Name</th>
                      <th>Designation</th>
                      <th>Area</th>
                      <th>Date Registered</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($result->num_rows > 0): ?>
                  <?php $no = 1; ?>
                  <?php while ($row = $result->fetch_assoc()): ?>

                      <tr class="approval-row" data-id="<?= $row['id']; ?>">

                      <td><?= $no++; ?> </td>

                      <td>
                        <strong>
                          <?= strtoupper(htmlspecialchars(
                              $row['last_name'] . ", " . 
                              $row['first_name'] . " " . 
                              substr($row['middle_name'], 0, 1) . "."
                          )); ?>
                          </strong>
                      </td>

                      <td>
                        <?= htmlspecialchars($row['designation']); ?>
                      </td>

                      <td>
                        <?= htmlspecialchars($row['area_name']); ?>
                      </td>

                      <td class="text-truncate" style="max-width: 240px;">
                        <?= date('M d, Y · h:i A', strtotime($row['created_at'])); ?>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                      No pending gas slips found
                    </td>
                  </tr>
                <?php endif; ?>
                </tbody>

            </table>

        </div>

    </main>

</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/sweetalert2.all.min.js"></script>

<script src="assets/js/app-ui.js"></script>

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

document.addEventListener('DOMContentLoaded', function () {

    document.querySelectorAll('.approval-row').forEach(row => {
        row.addEventListener('click', function () {

            const userId = this.dataset.id;

            Swal.fire({
                title: 'Account Action',
                text: 'Do you want to accept or decline this account?',
                icon: 'question',
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: 'Accept',
                denyButtonText: 'Decline',
                cancelButtonText: 'Cancel'
            }).then((result) => {

                if (result.isConfirmed) {

                    fetch('approve_user.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'id=' + encodeURIComponent(userId)
                    })
                    .then(response => response.json())
                    .then(data => {

                        if (data.success) {
                            Swal.fire('Approved!', 'User account activated.', 'success')
                                .then(() => location.reload());
                        } else {
                            Swal.fire('Error', data.message || 'Something went wrong.', 'error');
                        }

                    })
                    .catch(() => {
                        Swal.fire('Error', 'Server error occurred.', 'error');
                    });

                }else if (result.isDenied) {

                  Swal.fire({
                      title: 'Decline Account',
                      input: 'textarea',
                      inputLabel: 'Reason for rejection',
                      inputPlaceholder: 'Enter reason here...',
                      inputAttributes: {
                          'aria-label': 'Rejection reason'
                      },
                      showCancelButton: true,
                      confirmButtonText: 'Submit',
                      cancelButtonText: 'Cancel',
                      inputValidator: (value) => {
                          if (!value) {
                              return 'Reason is required';
                          }
                      }
                  }).then((reasonResult) => {

                      if (reasonResult.isConfirmed) {

                          fetch('decline_user.php', {
                              method: 'POST',
                              headers: {
                                  'Content-Type': 'application/x-www-form-urlencoded'
                              },
                              body: 'id=' + encodeURIComponent(userId) +
                                    '&remarks=' + encodeURIComponent(reasonResult.value)
                          })
                          .then(res => res.json())
                          .then(data => {

                              if (data.success) {
                                  Swal.fire('Declined', 'User account rejected.', 'success')
                                      .then(() => location.reload());
                              } else {
                                  Swal.fire('Error', data.message || 'Decline failed.', 'error');
                              }

                          });

                      }

                  });

                } else {
                    console.log('Cancelled');
                }

            });

        });
    });

});


</script>

</body>
</html>

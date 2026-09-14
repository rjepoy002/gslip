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

/* 🔎 DEBUG — TEMPORARY */
// var_dump($dateFrom, $dateTo);
// exit;
// var_dump($_SESSION['role'], $_SESSION['user_id'], $_SESSION['area'], $_SESSION['department_id']);
// exit;


/* =========================================================
 QUERY ALL USER ACCOUNTS
========================================================= */
$conn->begin_transaction();

/* ===============================
 ADMIN → show all users
 NON-ADMIN → only same department
=============================== */

$sql = "
    SELECT
        u.id,

        CONCAT(
            u.last_name, ', ',
            u.first_name,

            IF(
                u.middle_name IS NOT NULL
                AND u.middle_name != '',
                CONCAT(' ', LEFT(u.middle_name, 1), '.'),
                ''
            )
        ) AS fullname,

        u.username,
        u.designation,
        a.area_name AS area,
        u.role,
        u.status,
        u.last_login,
        u.created_at,

        EXISTS (
            SELECT 1
            FROM approval_global_settings ags
            WHERE ags.private_vehicle_approver_user_id = u.id
        ) AS is_private_approver,

        EXISTS (
            SELECT 1
            FROM department_approvers da
            WHERE da.user_id = u.id
        ) AS is_approver

    FROM users u

    LEFT JOIN departments d
        ON d.id = u.department_id

    LEFT JOIN areas a
        ON a.id = u.area_id
";

/* ===============================
 FILTER FOR NON-ADMIN
=============================== */
if ($role !== 'admin') {

    $sql .= "
        WHERE u.department_id = ?
    ";
}

$sql .= "
    ORDER BY
        u.last_name ASC,
        u.first_name ASC
";

$stmt = $conn->prepare($sql);

/* ===============================
 BIND PARAM FOR NON-ADMIN
=============================== */
if ($role !== 'admin') {
    $stmt->bind_param("i", $department);
}

$stmt->execute();

$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
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
              <h1 class="mb-1">Accounts</h1>
              <p class="page-subtitle mb-1">
                  View registered user accounts, designation details, account status, and recent login activity within the e-GSlip system.
              </p>
            </div>

           <!-- Accounts Table -->
          <table class="styled-table excel-table">
                <thead>
                    <tr class="group-header">
                        <th>No.</th>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Designation</th>
                        <th>Area</th>
                        <!-- <th>Role</th>
                        <th>Approval Scope</th> -->
                        <th>Status</th>
                        <th>Last Login</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (!empty($users)): ?>
                    <?php $no = 1; ?>

                    <?php foreach ($users as $row): ?>

                    <?php

                    $nameClass = '';

                    if ($row['role'] === 'admin') {

                        $nameClass = 'role-admin';

                    } elseif (!empty($row['is_private_approver'])) {

                        $nameClass = 'role-private-approver';

                    } elseif (!empty($row['is_approver'])) {

                        $nameClass = 'role-approver';

                    } elseif ($row['role'] === 'recommender') {

                        $nameClass = 'role-recommender';
                    }

                    ?>
                    <tr class="account-row" data-id="<?= $row['id']; ?>">

                        <td>
                            <?= $no++; ?>
                        </td>

                        <td>
                            <strong class="<?= $nameClass ?>">

                                <?= strtoupper(htmlspecialchars($row['fullname'])); ?>

                                <?php if ($row['role'] === 'admin'): ?>
                                    <i class="fas fa-star text-warning ms-1"
                                      title="Administrator"></i>
                                <?php endif; ?>

                                <?php if (!empty($row['is_private_approver'])): ?>
                                    <i class="fas fa-user-shield text-primary ms-1"
                                      title="Private Vehicle Approver"></i>
                                <?php endif; ?>

                            </strong>
                        </td>

                        <td>
                            <?= htmlspecialchars($row['username']); ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row['designation'] ?? '-'); ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row['area'] ?? '-'); ?>
                        </td>
<!-- 
                        <td>
                            <?= strtoupper(htmlspecialchars($row['role'])); ?>
                        </td>

                        <td>
                            <?= strtoupper(htmlspecialchars($row['approval_scope'] ?? '-')); ?>
                        </td> -->

                        <td>
                            <?php if ($row['status'] === 'active'): ?>
                                <span class="badge bg-success">ACTIVE</span>
                            <?php else: ?>
                                <span class="badge bg-danger">INACTIVE</span>
                            <?php endif; ?>
                        </td>

                        <td class="text-truncate" style="max-width: 180px;">
                            <?= !empty($row['last_login'])
                                ? date('M d, Y · h:i A', strtotime($row['last_login']))
                                : '-'; ?>
                        </td>

                    </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            No user accounts found
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

  document.querySelectorAll('.template-row').forEach(row => {

    row.addEventListener('click', function () {

      const templateId = this.dataset.id;

      Swal.fire({
        title: 'Template Actions',
        text: 'Select an action for this template.',
        icon: 'question',

        showDenyButton: true,
        showCancelButton: true,

        confirmButtonText: 'Load',
        denyButtonText: 'Delete',
        cancelButtonText: 'Edit Name'

      }).then((result) => {

        // =========================
        // LOAD TEMPLATE
        // =========================
        if (result.isConfirmed) {

          window.location.href =
            'create_gas_slip.php?template_id=' +
            encodeURIComponent(templateId);

        }

        // =========================
        // DELETE TEMPLATE
        // =========================
        else if (result.isDenied) {

          Swal.fire({
            title: 'Delete Template?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            confirmButtonColor: '#d33'
          }).then((deleteResult) => {

            if (!deleteResult.isConfirmed) return;

            fetch('delete_template.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
              },
              body: 'id=' + encodeURIComponent(templateId)
            })
            .then(res => res.json())
            .then(data => {

              if (data.success) {

                Swal.fire({
                  icon: 'success',
                  title: 'Template Deleted',
                  timer: 1200,
                  showConfirmButton: false
                }).then(() => location.reload());

              } else {

                Swal.fire({
                  icon: 'error',
                  title: 'Delete Failed',
                  text: data.message || 'Unable to delete template'
                });

              }

            });

          });

        }

        // =========================
        // EDIT TEMPLATE NAME
        // =========================
        else if (result.dismiss === Swal.DismissReason.cancel) {

          Swal.fire({
            title: 'Edit Template Name',
            input: 'text',
            inputPlaceholder: 'Enter new template name',
            showCancelButton: true,
            confirmButtonText: 'Save',

            inputValidator: (value) => {
              if (!value) {
                return 'Template name is required';
              }
            }

          }).then((editResult) => {

            if (!editResult.isConfirmed) return;

            fetch('update_template.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
              },
              body:
                'id=' + encodeURIComponent(templateId) +
                '&template_name=' + encodeURIComponent(editResult.value)

            })
            .then(res => res.json())
            .then(data => {

              if (data.success) {

                Swal.fire({
                  icon: 'success',
                  title: 'Template Updated',
                  timer: 1200,
                  showConfirmButton: false
                }).then(() => location.reload());

              } else {

                Swal.fire({
                  icon: 'error',
                  title: 'Update Failed',
                  text: data.message || 'Unable to update template'
                });

              }

            });

          });

        }

      });

    });

  });

});


</script>

</body>
</html>

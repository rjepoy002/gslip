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
                    <tr class="account-row <?= ($role === 'admin') ? 'clickable-row' : '' ?>" data-id="<?= $row['id']; ?>">

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

<!-- Account Details Modal -->
<div class="modal fade" id="accountModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Account Details</h5>
                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <input type="hidden" id="user_id">

                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label">First Name</label>
                        <input type="text" class="form-control" id="first_name">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="last_name">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Middle Name</label>
                        <input type="text" class="form-control" id="middle_name">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" id="username">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Designation</label>
                        <input type="text" class="form-control" id="designation">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Department</label>
                        <select class="form-select" id="department_id">
                            <!-- load departments here -->
                        </select>
                    </div>

                    <div class="col-md-6" id="areaWrapper">
                        <label class="form-label">Area</label>
                        <select class="form-select" id="area_id">
                            <option value="">Select Area</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Administrator</label>
                        <select class="form-select" id="role">
                            <option value="">No</option>
                            <option value="admin">Yes</option>
                        </select>
                    </div>

                </div>

            </div>

            <div class="modal-footer">
                <button type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">
                    Close
                </button>

                <button type="button"
                        class="btn btn-primary"
                        id="saveAccountBtn">
                    Save Changes
                </button>
            </div>

        </div>
    </div>
</div>


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

<?php if ($role === 'admin'): ?>
<script>

const accountModal =
    new bootstrap.Modal(document.getElementById('accountModal'));

document.querySelectorAll('.account-row').forEach(row => {

    row.addEventListener('click', function () {

        const userId = this.dataset.id;

        fetch('get_account.php?id=' + userId)
        .then(response => response.json())
        .then(data => {

          console.log('USER DATA:', data);
          
            document.getElementById('user_id').value = data.id;
            document.getElementById('first_name').value = data.first_name ?? '';
            document.getElementById('middle_name').value = data.middle_name ?? '';
            document.getElementById('last_name').value = data.last_name ?? '';
            document.getElementById('username').value = data.username ?? '';
            document.getElementById('designation').value = data.designation ?? '';
            document.getElementById('role').value = data.role ?? '';
            document.getElementById('status').value = data.status ?? 'active';

            window.selectedAreaId = data.area_id;

            console.log('Department ID:', data.department_id);
            console.log('Area:', data.area_name);

            loadDepartments(data.department_id);

            accountModal.show();
        });

    });

});

function loadDepartments(selectedDept)
{
    fetch('fetch_all_departments.php')
    .then(response => response.json())
    .then(rows => {

        const dept =
            document.getElementById('department_id');

        dept.innerHTML = '';

        rows.forEach(row => {

            const option =
                document.createElement('option');

            option.value = row.id;
            option.textContent = row.name;

            if (selectedDept == row.id) {
                option.selected = true;
            }

            dept.appendChild(option);
        });

        dept.dispatchEvent(new Event('change'));
    });
}

document.getElementById('department_id')
.addEventListener('change', function() {

      if (!this.value) return;

    const departmentName =
        this.options[this.selectedIndex].text;

    const areaWrapper =
        document.getElementById('areaWrapper');

    const areaSelect =
        document.getElementById('area_id');

    // if (
    //     departmentName === 'ASOD' ||
    //     departmentName === 'ANOD'
    // ) {

        // areaWrapper.classList.remove('d-none');

        fetch(
            'fetch_department_areas.php?department=' +
            encodeURIComponent(departmentName)
        )
        .then(response => response.json())
        .then(rows => {

            areaSelect.innerHTML = '';

            rows.forEach(row => {

                const option =
                    document.createElement('option');

                option.value = row.area_id;
                option.textContent = row.area_name;

                if (
                    window.selectedAreaId ==
                    row.area_id
                ) {
                    option.selected = true;
                }

                areaSelect.appendChild(option);
            });

        });

    // } 
    // else {

    //     // areaWrapper.classList.add('d-none');

    //     areaSelect.innerHTML =
    //         '<option value="">N/A</option>';
    // }
});

document.getElementById('saveAccountBtn')
.addEventListener('click', function () {

    const formData = new FormData();

    formData.append('id',
        document.getElementById('user_id').value);

    formData.append('first_name',
        document.getElementById('first_name').value);

    formData.append('middle_name',
        document.getElementById('middle_name').value);

    formData.append('last_name',
        document.getElementById('last_name').value);

    formData.append('username',
        document.getElementById('username').value);

    formData.append('designation',
        document.getElementById('designation').value);

    formData.append(
        'department_id',
        document.getElementById('department_id').value
    );

    formData.append(
        'area_id',
        document.getElementById('area_id').value
    );

    formData.append(
        'role',
        document.getElementById('role').value
    );

    formData.append('status',
        document.getElementById('status').value);

    fetch('update_account.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {

      if (data.success) {

      Swal.fire({
          icon: 'success',
          title: 'Updated',
          text: 'Account updated successfully'
      }).then(() => {
          location.reload();
      });

      } else {

      Swal.fire({
          icon: 'error',
          title: 'Error',
          text: data.message
      });

      }

    });

});

</script>
<?php endif; ?>

</body>
</html>

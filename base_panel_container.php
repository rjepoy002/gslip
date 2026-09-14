<?php
session_start();
require_once 'includes/config.php';

// 🔐 AUTH GUARD
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['session_token'])
) {
    header('Location: index.php');
    exit;
}

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
            <div class="page-header">
                <h1>Dashboard</h1>
                <p class="page-subtitle">System overview and recent activity</p>
            </div>

            <!-- Dashboard Stats -->
            <div class="dashboard-cards">

                <div class="dash-card">
                    <div class="dash-card-icon bg-blue">
                        <i class="fas fa-gas-pump"></i>
                    </div>
                    <div class="dash-card-info">
                        <h3>0</h3>
                        <span>Total Gas Slips</span>
                    </div>
                </div>

                <div class="dash-card">
                    <div class="dash-card-icon bg-green">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="dash-card-info">
                        <h3>0</h3>
                        <span>Approved</span>
                    </div>
                </div>

                <div class="dash-card">
                    <div class="dash-card-icon bg-yellow">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="dash-card-info">
                        <h3>0</h3>
                        <span>Pending</span>
                    </div>
                </div>

                <div class="dash-card">
                    <div class="dash-card-icon bg-red">
                        <i class="fas fa-ban"></i>
                    </div>
                    <div class="dash-card-info">
                        <h3>0</h3>
                        <span>Rejected</span>
                    </div>
                </div>

            </div>

            <!-- Recent Activity -->
            <div class="content-card">
                <div class="content-card-header">
                    <h2>Recent Gas Slips</h2>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Slip No.</th>
                                <th>Vehicle</th>
                                <th>Route</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="5" class="text-center text-muted">
                                    No recent records found
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </main>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

  /* ===== Sidebar Toggle ===== */
  const sidebar = document.querySelector('.app-sidebar');
  const content = document.querySelector('.app-content');
  const toggleBtn = document.getElementById('sidebarToggle');

  if (sidebar && content && toggleBtn) {
    // Load saved state
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
      sidebar.classList.add('collapsed');
      content.classList.add('sidebar-collapsed');
    }

    toggleBtn.addEventListener('click', () => {
      sidebar.classList.toggle('collapsed');
      content.classList.toggle('sidebar-collapsed');

      localStorage.setItem(
        'sidebarCollapsed',
        sidebar.classList.contains('collapsed')
      );
    });
  }

  /* ===== Logout SweetAlert ===== */
  const logoutBtn = document.getElementById('logoutBtn');

  if (logoutBtn) {
    logoutBtn.addEventListener('click', function (e) {
      e.preventDefault(); // 🔑 THIS stops auto redirect

      Swal.fire({
        title: 'Logout Confirmation',
        text: 'Are you sure you want to log out?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, logout',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        reverseButtons: true
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = 'logout.php';
        }
      });
    });
  }

});
</script>



</body>
</html>

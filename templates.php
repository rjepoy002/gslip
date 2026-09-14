<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/pagination_setup.php';

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
 QUERY ACCOUNTS FOR APPROVAL
========================================================= */
$conn->begin_transaction();

// Count total records for pagination
$countStmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM templates t
    WHERE t.created_by = ?
    AND t.status = 'active'
");

$countStmt->bind_param("i", $userId);
$countStmt->execute();

$totalRecords = $countStmt
    ->get_result()
    ->fetch_assoc()['total'];

$totalPages = max(1, ceil($totalRecords / $recordsPerPage));

// =========================================================
// FETCH TEMPLATES
// =========================================================
$sql = "
    SELECT 
        t.id,
        t.template_name,
        t.created_at,
        t.status,
        COUNT(DISTINCT tr.id) AS total_rows,
        COUNT(DISTINCT tf.id) AS total_fuel_items,
        COUNT(DISTINCT tro.id) AS total_routes
    FROM templates t

    LEFT JOIN template_rows tr
        ON tr.template_id = t.id

    LEFT JOIN templates_fuel tf
        ON tf.template_id = t.id

    LEFT JOIN templates_routes tro
        ON tro.template_id = t.id

    WHERE t.created_by = ?
      AND t.status = 'active'

    GROUP BY t.id

    ORDER BY t.created_at DESC
    LIMIT ?, ?;
";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "iii",
    $userId,
    $offset,
    $recordsPerPage
);
$stmt->execute();

$result = $stmt->get_result();
$templates = $result->fetch_all(MYSQLI_ASSOC);

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
              <h1 class="mb-1">Templates</h1>
              <p class="page-subtitle mb-1">
                  Create, organize, and manage reusable gas slip configurations for faster and more consistent issuance.
              </p>
            </div>

            <!-- Recent Activity -->
            <table class="styled-table excel-table">
                <thead>
                    <tr class="group-header">
                      <th>No.</th>
                      <th>Name</th>
                      <th>Date Created</th>
                      <th>Total Rows</th>
                      <th>Total Fuel Items</th>
                      <th>Total Routes</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($templates)): ?>
                  <?php $no = 1; ?>
                  <?php foreach ($templates as $row): ?>

                      <tr class="template-row" data-id="<?= $row['id']; ?>">

                      <td><?= $no++; ?> </td>

                      <td>
                        <strong>
                          <?= strtoupper(htmlspecialchars(
                              $row['template_name']
                          )); ?>
                          </strong>
                      </td>

                      <td class="text-truncate" style="max-width: 240px;">
                        <?= date('M d, Y · h:i A', strtotime($row['created_at'])); ?>
                      </td>

                      <td>
                        <?= htmlspecialchars($row['total_rows']); ?>
                      </td>
                      <td>
                        <?= htmlspecialchars($row['total_fuel_items']); ?>
                      </td>
                      <td>
                        <?= htmlspecialchars($row['total_routes']); ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                      No pending gas slips found
                    </td>
                  </tr>
                <?php endif; ?>
                </tbody>

            </table>
            <?php include 'includes/pagination.php'; ?>

        </div>

    </main>

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

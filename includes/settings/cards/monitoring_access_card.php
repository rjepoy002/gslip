<?php
$monitoringDepartments = $conn->query('SELECT id, name FROM departments ORDER BY name')->fetch_all(MYSQLI_ASSOC);
$monitoringSelected = getMonitoringDepartmentIds($conn);
if (empty($_SESSION['monitoring_csrf'])) $_SESSION['monitoring_csrf'] = bin2hex(random_bytes(32));
?>
<div class="col-md-6">
    <div class="card shadow-sm border-0 h-100">
        <div class="card-body">
            <h5 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Monitoring Module Access</h5>
            <form action="save_monitoring_access.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['monitoring_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                <div class="row g-2 mb-3">
                    <?php foreach ($monitoringDepartments as $monitoringDepartment): ?>
                        <div class="col-sm-6"><div class="form-check">
                            <input class="form-check-input" type="checkbox" name="department_ids[]" id="monitoring-department-<?= (int)$monitoringDepartment['id'] ?>" value="<?= (int)$monitoringDepartment['id'] ?>" <?= in_array((int)$monitoringDepartment['id'], $monitoringSelected, true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="monitoring-department-<?= (int)$monitoringDepartment['id'] ?>"><?= htmlspecialchars($monitoringDepartment['name'], ENT_QUOTES, 'UTF-8') ?></label>
                        </div></div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-success">Save Changes</button>
            </form>
        </div>
    </div>
</div>

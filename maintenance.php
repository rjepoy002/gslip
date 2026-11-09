<?php
session_start();
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/maintenance.php';
$conn = getDBConnection();
header('Cache-Control: no-store');
$state = maintenanceState($conn);
$actor = maintenanceActor($conn);
$admin = $actor && $actor['role'] === 'admin';
$_SESSION['maintenance_csrf'] ??= bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!$actor || !hash_equals($_SESSION['maintenance_csrf'], $_POST['csrf'] ?? '')) {
        http_response_code(403); echo json_encode(['success'=>false]); exit;
    }
    // Serialize lifecycle changes and logout validation, including rapid reactivation.
    $locked = $conn->query("SELECT GET_LOCK('gslip_maintenance_event', 10)")->fetch_row()[0];
    if ((int)$locked !== 1) { http_response_code(503); echo json_encode(['success'=>false]); exit; }
    try {
        $state = maintenanceState($conn);
        $action = $_POST['action'] ?? '';
        if ($action === 'logout') {
            if ($state['enabled'] && hash_equals($state['id'], $_POST['event_id'] ?? '') && !$admin) {
                $_SESSION = []; session_destroy();
                echo json_encode(['success'=>true,'logout'=>true]);
            } else echo json_encode(['success'=>true,'logout'=>false]);
        } elseif ($admin && in_array($action, ['enable','disable'], true)) {
            if ($action === 'enable' && !$state['enabled']) {
                $state = ['id'=>bin2hex(random_bytes(12)), 'enabled'=>true,'effective_at'=>time()+60,'ended_at'=>0,
                    'reason'=>mb_substr(trim($_POST['reason'] ?? ''),0,80)];
            } elseif ($action === 'disable') {
                $state['enabled'] = false; $state['ended_at'] = time();
            }
            unset($state['phase'], $state['server_time']);
            $json = json_encode($state, JSON_UNESCAPED_UNICODE);
            // Existing setting_value is VARCHAR(255); reject overflow instead of truncating JSON.
            if (mb_strlen($json) > 255) throw new RuntimeException('Reason is too long.');
            $s = $conn->prepare("INSERT INTO system_settings(setting_key,setting_value) VALUES('maintenance_event',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
            $s->bind_param('s',$json); $s->execute();
            echo json_encode(['success'=>true]);
        } else { http_response_code(403); echo json_encode(['success'=>false]); }
    } catch (Throwable $e) {
        error_log('Maintenance: '.$e->getMessage()); http_response_code(500);
        echo json_encode(['success'=>false,'message'=>'Unable to change maintenance state.']);
    } finally { $conn->query("SELECT RELEASE_LOCK('gslip_maintenance_event')"); }
    exit;
}
if (isset($_GET['state'])) {
    header('Content-Type: application/json');
    // Public state has no user data; service workers can validate delayed events.
    echo json_encode(['state'=>$state,'server_time_ms'=>(int)round(microtime(true)*1000),'admin'=>$admin,'authenticated'=>(bool)$actor,
        'csrf'=>$actor ? $_SESSION['maintenance_csrf'] : null]); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>System Maintenance | e-GSlip</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/sweetalert2.min.css">
    <?php include __DIR__.'/includes/dark-mode-preload.php'; ?>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/maintenance.css?v=<?= filemtime(__DIR__.'/assets/css/maintenance.css') ?>">
    <script src="assets/js/sweetalert2.all.min.js"></script>
</head>
<body class="maintenance-page<?= $admin ? '' : ' maintenance-guest' ?>">
<?php if ($admin): ?>
    <?php include __DIR__.'/includes/sidebar.php'; ?>
<?php endif; ?>
<div class="app-content">
    <main class="main-content">
        <div class="panel-container">
            <div class="page-header mb-4">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <span class="maintenance-heading-icon"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i></span>
                    <h1 class="mb-0">System Maintenance</h1>
                </div>
                <p class="page-subtitle mb-0"><?= $admin ? 'Manage system availability and keep users informed during planned updates.' : 'Check system availability and maintenance updates.' ?></p>
            </div>
            <div class="row g-4">
                <div class="<?= $admin ? 'col-lg-7' : 'col-12' ?>">
                    <section class="card border-0 shadow-sm maintenance-card h-100" aria-labelledby="maintenance-status-title">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-start justify-content-between gap-3 mb-4">
                                <div>
                                    <h2 id="maintenance-status-title" class="h5 mb-1">System availability</h2>
                                    <p class="text-muted small mb-0">Current maintenance status</p>
                                </div>
                                <span id="maintenance-badge" class="badge rounded-pill maintenance-badge <?= $state['enabled'] ? 'is-active' : 'is-inactive' ?>"><?= $state['enabled'] ? 'Active' : 'Operational' ?></span>
                            </div>
                            <div id="maintenance-summary" class="maintenance-summary<?= $state['enabled'] ? '' : ' is-completed' ?>" role="status">
                                <span class="maintenance-summary-icon"><i id="maintenance-summary-icon" class="fa-solid <?= $state['enabled'] ? 'fa-shield-halved' : 'fa-circle-check' ?>" aria-hidden="true"></i></span>
                                <div>
                                    <h3 id="maintenance-completion-heading" class="h6 mb-1" <?= $state['enabled'] ? 'hidden' : '' ?>>System Maintenance Completed.</h3>
                                    <p id="maintenance-status" class="mb-0"><?= $state['enabled'] ? 'Checking current maintenance status...' : 'GSLIP is now available. You may log in to continue using the system.' ?></p>
                                </div>
                            </div>
                            <div id="maintenance-warning" class="maintenance-countdown-panel mt-4" hidden>
                                <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                                    <div><h3 class="h6 mb-1">Maintenance starts soon</h3><p class="small mb-0">Please finish your work before access is restricted.</p></div>
                                    <span id="maintenance-countdown" class="maintenance-countdown" aria-label="Seconds until maintenance starts">60<span>s</span></span>
                                </div>
                                <div id="maintenance-progress" class="progress" role="progressbar" aria-label="Maintenance warning countdown" aria-valuemin="0" aria-valuemax="60" aria-valuenow="60">
                                    <div id="maintenance-progress-bar" class="progress-bar" style="width:100%"></div>
                                </div>
                            </div>
                            <dl class="maintenance-details mt-4 mb-0">
                                <div><dt>Maintenance reason</dt><dd id="maintenance-reason"><?= htmlspecialchars($state['reason'] ?: 'No reason provided.', ENT_QUOTES, 'UTF-8') ?></dd></div>
                                <div><dt>Enabled at</dt><dd id="maintenance-activated">Not available</dd></div>
                            </dl>
                            <?php if (!$admin): ?>
                                <div class="maintenance-safe-actions mt-4">
                                    <a href="index.php" class="btn btn-outline-secondary"><i class="fa-solid fa-right-to-bracket me-2" aria-hidden="true"></i>Return to Login</a>
                                    <button id="maintenance-acknowledge" type="button" class="btn btn-warning" hidden>Acknowledge and log out</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
                <?php if ($admin): ?>
                <div class="col-lg-5">
                    <section class="card border-0 shadow-sm maintenance-card h-100" aria-labelledby="maintenance-controls-title">
                        <div class="card-body p-4">
                            <h2 id="maintenance-controls-title" class="h5 mb-2"><i class="fa-solid fa-sliders me-2" aria-hidden="true"></i>Maintenance controls</h2>
                            <p class="text-muted small mb-4">Users receive a 60-second warning before maintenance access restrictions begin. Administrators retain access.</p>
                            <form id="maintenance-controls">
                                <label for="maintenance-reason-input" class="form-label fw-semibold">Reason <span class="text-muted fw-normal">(optional)</span></label>
                                <textarea id="maintenance-reason-input" class="form-control" name="reason" rows="3" maxlength="80" placeholder="e.g. Updating the system to improve performance" aria-describedby="maintenance-reason-help"></textarea>
                                <p id="maintenance-reason-help" class="form-text mb-4">Up to 80 characters. This message is shown to users.</p>
                                <div class="maintenance-control-actions">
                                    <button type="submit" name="action" value="enable" class="btn btn-primary" <?= $state['enabled'] ? 'disabled' : '' ?>><i class="fa-solid fa-power-off me-2" aria-hidden="true"></i>Enable maintenance</button>
                                    <button type="submit" name="action" value="disable" class="btn btn-outline-secondary" <?= !$state['enabled'] ? 'disabled' : '' ?>><i class="fa-solid fa-check me-2" aria-hidden="true"></i>Disable maintenance</button>
                                </div>
                            </form>
                            <p id="maintenance-action-feedback" class="small mt-3 mb-0" role="status"></p>
                            <div class="maintenance-help mt-4"><i class="fa-solid fa-circle-info me-2" aria-hidden="true"></i>Disabling maintenance cancels the current warning and restores normal access.</div>
                        </div>
                    </section>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app-ui.js"></script>
<?php if (!$admin): ?>
<script src="assets/js/dark-mode.js"></script>
<script src="assets/js/maintenance.js" defer></script>
<?php endif; ?>
</body>
</html>

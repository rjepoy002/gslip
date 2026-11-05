<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/monitoring_access.php';
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_SESSION['user_id']) || empty($_SESSION['session_token'])) {
    http_response_code(403); exit('Access denied.');
}
if (!is_string($_POST['csrf_token'] ?? null) || empty($_SESSION['monitoring_csrf']) || !hash_equals($_SESSION['monitoring_csrf'], $_POST['csrf_token'])) {
    http_response_code(403); exit('Invalid security token. Reload Settings.');
}
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = getDBConnection();
    saveMonitoringDepartments($conn, (int)$_SESSION['user_id'], $_POST['department_ids'] ?? []);
    $_SESSION['swal_success'] = 'Monitoring department access saved.';
    header('Location: settings.php', true, 303);
} catch (DomainException $e) {
    http_response_code(403); echo 'Access denied.';
} catch (InvalidArgumentException $e) {
    http_response_code(422); echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
} catch (Throwable $e) {
    error_log('Monitoring access save failed: ' . $e->getMessage());
    http_response_code(500); echo 'Unable to save Monitoring access. Please try again.';
}

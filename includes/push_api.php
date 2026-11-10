<?php
// JSON-only authentication boundary shared by the three push endpoints.
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function pushApiResponse(int $status, bool $success, string $message = '', array $extra = []): void
{
    http_response_code($status);
    echo json_encode(['success'=>$success, 'message'=>$message] + $extra);
    exit;
}

set_exception_handler(static function (Throwable $error): void {
    // Do not log subscription endpoints, encryption keys or database SQL.
    error_log('[WEB PUSH] API failure | type='.get_class($error).' | code='.(int)$error->getCode());
    pushApiResponse(500, false, 'The server could not save notification settings. Please try again.');
});

require_once __DIR__.'/config.php';
// This is a JSON API even when a caller omits its Accept header. The existing
// maintenance guard must return JSON instead of redirecting to an HTML page.
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$conn = getDBConnection(true);
if (!maintenanceActor($conn)) pushApiResponse(401, false, 'Your session has expired. Sign in again to update notifications.');
$_SESSION['push_csrf'] ??= bin2hex(random_bytes(32));

function pushApiRequirePost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') pushApiResponse(405, false, 'POST required.');
    $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($csrf) || !hash_equals($_SESSION['push_csrf'], $csrf))
        pushApiResponse(403, false, 'This page has expired. Reload it and try again.');
}

<?php
// One atomic event document; no historical alerts are inserted into notifications.
function maintenanceState(mysqli $conn): array
{
    $r = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='maintenance_event' LIMIT 1");
    $state = json_decode($r->fetch_assoc()['setting_value'] ?? '{}', true) ?: [];
    return maintenanceNormalize($state, time());
}

function maintenanceNormalize(array $state, int $now): array
{
    $state += ['id'=>'', 'enabled'=>false, 'effective_at'=>0, 'ended_at'=>0, 'reason'=>''];
    $state['phase'] = !$state['enabled'] ? 'ended' : ($now < $state['effective_at'] ? 'warning' : 'active');
    $state['server_time'] = $now;
    return $state;
}

function maintenanceActor(mysqli $conn): ?array
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['session_token'])) return null;
    $s = $conn->prepare("SELECT id,role FROM users WHERE id=? AND session_token=? AND status='active'");
    $s->bind_param('is', $_SESSION['user_id'], $_SESSION['session_token']);
    $s->execute();
    return $s->get_result()->fetch_assoc();
}

function maintenanceGuard(mysqli $conn): void
{
    if (PHP_SAPI === 'cli') return;
    $page = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
    if (in_array($page, ['index.php','maintenance.php','logout.php'], true)) return;
    $state = maintenanceState($conn);
    if ($state['phase'] !== 'active') return;
    $actor = maintenanceActor($conn);
    if ($actor && $actor['role'] === 'admin') return;
    // Do not destroy sessions here: a stale tab must never invalidate a newer login.
    http_response_code(503);
    header('Cache-Control: no-store');
    header('Retry-After: 30');
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success'=>false,'maintenance'=>true,'message'=>'System maintenance is in progress. Please try again later.']);
    } else {
        header('Location: maintenance.php', true, 302);
    }
    exit;
}

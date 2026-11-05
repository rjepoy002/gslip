<?php
require_once __DIR__ . '/system_settings.php';

function monitoringActorIsAdmin(mysqli $conn, int $userId): bool
{
    $stmt = $conn->prepare("SELECT 1 FROM users WHERE id = ? AND role = 'admin' AND status = 'active'");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $admin = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $admin;
}

function getMonitoringDepartmentIds(mysqli $conn): array
{
    $ids = json_decode(getSystemSetting($conn, 'monitoring_departments', '[]'), true);
    if (!is_array($ids) || !array_is_list($ids)) return [];
    foreach ($ids as $id) if (!is_int($id) || $id < 1) return [];
    return array_values(array_unique($ids));
}

function canAccessMonitoring(mysqli $conn, int $userId): bool
{
    try {
        // Resolve current role/department on every request, never session flags.
        $stmt = $conn->prepare('SELECT role, department_id FROM users WHERE id = ? AND status = \'active\'');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $actor = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$actor) return false;
        if ($actor['role'] === 'admin') return true;
        // A legacy global enabled value, if present, has no authorization role.
        return in_array((int)$actor['department_id'], getMonitoringDepartmentIds($conn), true);
    } catch (Throwable $e) {
        error_log('Unable to check monitoring access: ' . $e->getMessage());
        return false;
    }
}

function saveMonitoringDepartments(mysqli $conn, int $actorId, $values): void
{
    if (!monitoringActorIsAdmin($conn, $actorId)) throw new DomainException('Only administrators can change Monitoring access.');
    if (!is_array($values)) throw new InvalidArgumentException('Invalid department selection.');
    $ids = [];
    foreach ($values as $value) {
        if ((!is_int($value) && !is_string($value)) || !ctype_digit((string)$value) || (int)$value < 1 || (string)(int)$value !== (string)$value) throw new InvalidArgumentException('Invalid department selection.');
        $ids[(int)$value] = (int)$value;
    }
    $ids = array_values($ids);
    sort($ids);
    if ($ids) {
        $stmt = $conn->prepare('SELECT id FROM departments WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $stmt->execute();
        $count = $stmt->get_result()->num_rows;
        $stmt->close();
        if ($count !== count($ids)) throw new InvalidArgumentException('One or more departments no longer exist. Reload Settings.');
    }
    $encoded = json_encode($ids, JSON_THROW_ON_ERROR);
    // Respect the existing system_settings VARCHAR(255), without a migration.
    if (strlen($encoded) > 255) throw new InvalidArgumentException('The department selection exceeds the settings storage limit.');
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('monitoring_departments', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->bind_param('s', $encoded);
    $stmt->execute();
    $stmt->close();
}

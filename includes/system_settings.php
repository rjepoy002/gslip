<?php

/**
 * Returns a global system setting, falling back safely when the setting has
 * not yet been deployed or cannot be read.
 */
function getSystemSetting(mysqli $conn, string $key, ?string $default = null): ?string
{
    $stmt = $conn->prepare(
        'SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1'
    );

    if (!$stmt) {
        error_log('Unable to read system setting: ' . $conn->error);
        return $default;
    }

    $stmt->bind_param('s', $key);

    if (!$stmt->execute()) {
        error_log('Unable to execute system setting query: ' . $stmt->error);
        $stmt->close();
        return $default;
    }

    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row['setting_value'] ?? $default;
}

function isPrintOnceEnabled(mysqli $conn): bool
{
    return getSystemSetting($conn, 'print_once', '0') === '1';
}

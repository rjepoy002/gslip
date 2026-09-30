<?php

/**
 * Applies the same ownership relationship used by the approved-slip workflow
 * when a slip is opened directly from the print endpoint.
 */
function canAccessPrintGasSlip(array $slip, int $userId, string $role): bool
{
    if ($role === 'admin') {
        return true;
    }

    return (int)($slip['user_id'] ?? 0) === $userId
        || (int)($slip['recommended_by'] ?? 0) === $userId
        || (int)($slip['approved_by'] ?? 0) === $userId;
}

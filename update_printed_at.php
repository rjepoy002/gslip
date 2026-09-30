<?php
session_start();

require_once 'includes/config.php';
require_once 'includes/system_settings.php';
require_once 'includes/print_access.php';

header('Content-Type: application/json');

function printUpdateError(int $status, string $message): void
{
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST'
    || empty($_SESSION['user_id'])
    || empty($_SESSION['session_token'])
    || !isset($_SESSION['role'])
) {
    printUpdateError(403, 'Unauthorized access.');
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['ids']) || !is_array($data['ids']) || empty($data['ids'])) {
    printUpdateError(400, 'Invalid input. "ids" must be a non-empty array.');
}

$ids = array_values(array_unique(array_filter(
    array_map('intval', $data['ids']),
    static fn ($id) => $id > 0
)));

if (empty($ids)) {
    printUpdateError(400, 'No valid gas slip IDs were supplied.');
}

$conn = getDBConnection();
$userId = (int)$_SESSION['user_id'];
$role = (string)$_SESSION['role'];
$printOnceEnabled = isPrintOnceEnabled($conn);

$selectStmt = $conn->prepare(
    'SELECT id, user_id, recommended_by, approved_by, status '
    . 'FROM gas_slips WHERE id = ? FOR UPDATE'
);
$firstPrintStmt = $conn->prepare(
    "UPDATE gas_slips SET status = 'printed', printed_at = COALESCE(printed_at, NOW()) "
    . "WHERE id = ? AND status = 'approved'"
);

if (!$selectStmt || !$firstPrintStmt) {
    error_log('Unable to prepare print status update: ' . $conn->error);
    printUpdateError(500, 'Unable to update the print status.');
}

$conn->begin_transaction();

try {
    $firstPrintCount = 0;

    foreach ($ids as $id) {
        $selectStmt->bind_param('i', $id);
        $selectStmt->execute();
        $slip = $selectStmt->get_result()->fetch_assoc();

        if (!$slip) {
            throw new RuntimeException('One of the selected gas slips no longer exists.');
        }

        if (!canAccessPrintGasSlip($slip, $userId, $role)) {
            throw new RuntimeException('You are not authorized to print one of the selected gas slips.');
        }

        $canPrint = $slip['status'] === 'approved'
            || ($slip['status'] === 'printed' && !$printOnceEnabled);

        if (!$canPrint) {
            throw new RuntimeException(
                'One of the selected gas slips has already been printed and Print Once is enabled.'
            );
        }

        if ($slip['status'] === 'approved') {
            $firstPrintStmt->bind_param('i', $id);
            $firstPrintStmt->execute();

            if ($firstPrintStmt->affected_rows !== 1) {
                throw new RuntimeException('The gas slip could not be marked as printed.');
            }

            $firstPrintCount++;
        }
    }

    $conn->commit();
    $selectStmt->close();
    $firstPrintStmt->close();

    echo json_encode([
        'success' => true,
        'ids_received' => $ids,
        'affected_rows' => $firstPrintCount
    ]);
} catch (Throwable $exception) {
    $conn->rollback();
    $selectStmt->close();
    $firstPrintStmt->close();
    printUpdateError(403, $exception->getMessage());
}

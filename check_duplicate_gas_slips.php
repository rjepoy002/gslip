<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/duplicate_gas_slips.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'], $_SESSION['session_token'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$conn = getDBConnection();
$proposals = getDuplicateProposalsFromPost($_POST);
$excludeGasSlipId = (int) ($_POST['gas_slip_id'] ?? 0);
$duplicates = findPossibleDuplicateGasSlips($conn, $proposals, $excludeGasSlipId);

foreach ($duplicates as &$duplicate) {
    $duplicate['can_view'] = $duplicate['source'] === 'database'
        && $duplicate['status'] === 'approved'
        && ((int) $duplicate['created_by_id'] === (int) $_SESSION['user_id'] || ($_SESSION['role'] ?? '') === 'admin');
    if ($duplicate['can_view']) {
        $duplicate['view_url'] = 'duplicate_view_gas_slip.php?id=' . (int) $duplicate['id'];
    }
}
unset($duplicate);

echo json_encode(['success' => true, 'duplicate' => !empty($duplicates), 'duplicates' => $duplicates]);

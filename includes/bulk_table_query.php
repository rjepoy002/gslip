<?php
// Only a selected custom tab changes the display query. Default lists retain their original queries.
if (!empty($_GET['bulk_group']) && (!empty($_SESSION['is_recommender']) || !empty($_SESSION['is_approver']) || !empty($_SESSION['is_private_approver']))) {
    require_once __DIR__ . '/bulk_processing.php';
    $bulkWorkflow = 'auto';
    try {
        $bulkRows = bulkRecords($conn,(int)$_SESSION['user_id'],$bulkWorkflow,(int)$_GET['bulk_group']);
        $bulkDisplayIds = bulkIds($bulkRows);
        $totalRecords = count($bulkDisplayIds);
        $totalPages = max(1,(int)ceil($totalRecords/$recordsPerPage));
        $page = max(1,min($page,$totalPages));
        $offset = ($page-1)*$recordsPerPage;
        $bulkIdSql = $bulkDisplayIds ? implode(',',$bulkDisplayIds) : '0';
        $result = $conn->query("SELECT gs.id AS gs_id,gs.*, CONCAT(MIN(r.origin),' → ',GROUP_CONCAT(r.destination ORDER BY gsr.id SEPARATOR ' → ')) AS route_path
            FROM gas_slips gs LEFT JOIN gas_slip_routes gsr ON gsr.gas_slip_id=gs.id LEFT JOIN routes r ON r.id=gsr.route_id
            WHERE gs.id IN ($bulkIdSql) GROUP BY gs.id ORDER BY gs.date_issued DESC,gs.id DESC LIMIT " . (int)$offset . ',' . (int)$recordsPerPage);
    } catch (Throwable $e) {
        // Fail closed for invalid/foreign groups; never display the default queue under a custom label.
        $result = $conn->query('SELECT id AS gs_id FROM gas_slips WHERE 1=0');
        $totalRecords=0; $totalPages=1;
    }
}

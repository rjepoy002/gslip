<?php
session_start();
require_once __DIR__ . '/includes/config.php';
$api = isset($_GET['action']);
header('Cache-Control: no-store');
if (empty($_SESSION['user_id']) || empty($_SESSION['session_token']) || !isset($_SESSION['role'], $_SESSION['department_id'], $_SESSION['area'])) {
    if ($api) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Your session has expired. Please sign in again.']);
    } else header('Location: index.php');
    exit;
}
$userId = (int)$_SESSION['user_id'];
session_write_close();
require_once __DIR__ . '/includes/monitoring.php';
require_once __DIR__ . '/includes/monitoring_access.php';
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = getDBConnection();
    $conn->set_charset('utf8mb4');
    if (!canAccessMonitoring($conn, $userId)) {
        http_response_code(403);
        if ($api) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'You do not have access to Monitoring.']);
        } else {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Access denied</title><p>You do not have access to Monitoring.</p><a href="dashboard.php">Back to Dashboard</a></html>';
        }
        exit;
    }
} catch (Throwable $e) {
    error_log('Monitoring access unavailable: ' . $e->getMessage());
    http_response_code(503);
    if ($api) { header('Content-Type: application/json'); echo json_encode(['error' => 'Monitoring is temporarily unavailable.']); }
    else echo 'Monitoring is temporarily unavailable.';
    exit;
}
if ($api) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        // A single consistent snapshot keeps counts, chart and page reconciled.
        $conn->begin_transaction(MYSQLI_TRANS_START_READ_ONLY | MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
        switch ($_GET['action']) {
            case 'export': $download = monitoringExportCsv($conn, monitoringFilters($_GET), $userId); break;
            case 'data': $data = monitoringData($conn, monitoringFilters($_GET), $userId); break;
            case 'options': $data = monitoringOptions($conn, $userId); break;
            case 'detail':
                $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if (!$id) throw new InvalidArgumentException('Invalid gas slip ID.');
                $data = monitoringDetail($conn, $id, $userId);
                if (!$data) { http_response_code(404); $data = ['error' => 'Gas slip is missing or inaccessible.']; }
                break;
            default: throw new InvalidArgumentException('Invalid request.');
        }
        $conn->commit();
        if (isset($download)) {
            gasSlipReportDownloadHeaders();
            header('X-Content-Type-Options: nosniff');
            fpassthru($download);
            fclose($download);
            exit;
        }
        echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    } catch (Throwable $e) {
        error_log('Monitoring: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Monitoring data is temporarily unavailable. Please try again.']);
    }
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>GSLIP Monitoring Dashboard</title>
<link rel="stylesheet" href="assets/css/sweetalert2.min.css">
<link rel="stylesheet" href="assets/css/bootstrap.min.css">
<?php include __DIR__ . '/includes/dark-mode-preload.php'; ?>
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/monitoring.css">
<script src="assets/js/sweetalert2.all.min.js" defer></script>
<script src="assets/js/bootstrap.bundle.min.js" defer></script>
<script src="assets/js/app-ui.js" defer></script>
<script src="assets/js/chart.js/chart.min.js" defer></script>
<script src="assets/js/gas-slip.js" defer></script>
<script src="assets/js/monitoring.js" defer></script>
</head>
<body data-year="<?= date('Y') ?>" data-refresh="60000">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="app-content">
<main class="main-content monitoring-content">
<div class="panel-container">
<div class="page-header mb-4"><h1 class="mb-1">Monitoring Dashboard</h1><p class="page-subtitle mb-1">Organization-wide Approved and Printed gas slips | Read only</p></div>
<p id="message" role="status" aria-live="polite"></p>
<section class="overview" aria-labelledby="overview-title">
<div class="overview-heading"><h2 id="overview-title">Gas Slip Overview</h2><button type="button" id="overview-toggle" aria-expanded="true" aria-controls="overview-body"><svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M7.646 4.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1-.708.708L8 5.707l-5.646 5.647a.5.5 0 0 1-.708-.708l6-6z"/></svg> <span>Minimize</span></button></div>
<div id="overview-body" class="overview-body">
<section class="cards" aria-label="Summary">
<article><span>Total Gas Slips</span><strong id="total">—</strong></article>
<article><span>Approved Gas Slips</span><strong id="approved">—</strong></article>
<article><span>Printed Gas Slips</span><strong id="printed">—</strong></article>
</section>
<section class="panel"><h2>Department Gas Slip Distribution</h2><p class="hint">Click a bar to select its department and, for ASOD/ANOD, area. Click again to clear. The graph keeps all authorized departments and areas for comparison.</p><div class="chart-scroll"><div class="chart"><canvas id="distribution" aria-label="Department gas slip distribution" role="img"></canvas></div></div><p id="chart-empty" class="hint" hidden>No matching records in this reporting period.</p></section>
</div>
</section>
<form id="filters" class="panel" autocomplete="off">
<div class="filters">
<label class="search">Search<input name="search" type="search" maxlength="200" placeholder="Slip number, requester, purpose, routes…"></label>
<div class="buttons"><button type="button" id="open-filters" aria-haspopup="dialog" aria-controls="filter-dialog"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M1.5 1h13a.5.5 0 0 1 .4.8L10 8.333V14a.5.5 0 0 1-.724.447l-3-1.5A.5.5 0 0 1 6 12.5V8.333L1.1 1.8a.5.5 0 0 1 .4-.8zM2.5 2 7 8v4.191l2 1V8l4.5-6z"/></svg> Filters<span id="filter-count" class="badge" hidden></span></button><button type="button" id="reset">Reset Filters</button><button type="button" id="export" class="primary" title="Download all matching records as an Excel-compatible CSV"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M.5 9.9a.5.5 0 0 1 .5.5V13a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.6a.5.5 0 0 1 1 0V13a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.6a.5.5 0 0 1 .5-.5z"/><path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/></svg> Export Report</button></div>
</div>
<p id="refresh-status" class="visually-hidden" role="status" aria-live="polite">Updating</p>
<dialog id="filter-dialog" aria-labelledby="filter-title">
<div class="dialog-header"><h2 id="filter-title">Gas Slip Filters</h2><button type="button" id="close-filters" aria-label="Close filters">&times;</button></div>
<div class="filter-fields">
<label>Reporting year<select name="year" id="year"><option><?= date('Y') ?></option></select></label>
<label>From Date<input type="date" name="from"></label>
<label>To Date<input type="date" name="to"></label>
<label>Department<select name="department" id="department"><option value="0">All Departments</option></select></label>
<label>Area<select name="area" id="area"><option value="0">All Areas</option></select></label>
<label>Status<select name="status"><option value="">All (Approved and Printed)</option><option value="approved">Approved</option><option value="printed">Printed</option></select></label>
</div>
<p class="hint">Dates narrow the selected year. Monitoring includes Approved and Printed slips across all departments and areas.</p>
<p id="filter-error" role="alert"></p>
<div class="filter-actions"><button type="button" id="cancel-filters">Cancel</button><button type="submit" class="primary">Apply Filters</button></div>
</dialog>
</form>
<section class="panel"><div class="table-heading"><h2>Approved / Printed Gas Slips</h2><label>Rows per page<select id="size"><option>10</option><option selected>25</option><option>50</option><option>100</option></select></label></div>
<div class="table-scroll"><table><thead><tr>
<?php foreach (['number'=>'Gas Slip No.', 'date'=>'Date Issued', 'department'=>'Department', 'area'=>'Area', 'requester'=>'Requested By'] as $key=>$label): ?>
<th scope="col" data-column="<?= $key ?>" <?= $key === 'date' ? 'aria-sort="descending"' : '' ?>><button type="button" data-sort="<?= $key ?>"><?= $label ?> <span><?= $key === 'date' ? '▼' : '↕' ?></span></button></th>
<?php endforeach; ?>
<th scope="col">Purpose</th><th scope="col">Routes</th><th scope="col">Status</th></tr></thead><tbody id="records"><tr><td colspan="8">Loading records…</td></tr></tbody></table></div>
<div class="pagination"><span id="range">Loading…</span><nav aria-label="Pagination"><button id="previous" disabled>Previous</button><span id="page-label"></span><button id="next" disabled>Next</button></nav></div>
</section>
<footer id="updated">Last Updated: —</footer>

</div>
</main>
</div>
<?php $gasSlipModalContext = 'monitoring'; include __DIR__.'/includes/gas_slip_details_modal.php'; ?>
</body></html>

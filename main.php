<?php
require_once 'includes/config.php';
$conn = getDBConnection();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (empty($_SESSION['user_id'])) {
    header('Location: ' . LOGIN_PAGE);
    exit;
}

// Handle logout (when logout button is clicked)
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: ' . LOGIN_PAGE);
    exit;
}

/** Hard-fail on SQL errors and use utf8mb4 */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

/** Pagination config (server-side) */
$limit  = 1000;
$page   = isset($_GET['page']) ? max((int)$_GET['page'], 1) : 1;
$offset = ($page - 1) * $limit;

$totalSql = "
    SELECT COUNT(DISTINCT gs.id) AS total 
    FROM gas_slips gs
    JOIN vehicles v ON gs.vehicle_id = v.id
";
$totalStmt = $conn->prepare($totalSql);
$totalStmt->execute();
$totalResult   = $totalStmt->get_result();
$totalRow      = $totalResult->fetch_assoc();
$totalRecords  = (int)($totalRow['total'] ?? 0);
$total_pages   = (int)ceil($totalRecords / $limit);

/** Fetch settings (prepared) */
$settingsSql = "
    SELECT area, fuel_supplier, r_approval, r_designation, 
           a_approval, a_designation, b_approval, b_designation 
    FROM settings 
    WHERE area = ? 
    LIMIT 1
";
$settingsStmt = $conn->prepare($settingsSql);
$settingsStmt->bind_param("s", $_SESSION['area']); // assuming area is a string
$settingsStmt->execute();
$result = $settingsStmt->get_result();
$settings = $result->fetch_assoc(); // either array or null

if (!$settings) {
    // fallback so your modal/form won’t break
    $settings = [
        'fuel_supplier' => '',
        'r_approval'    => '',
        'r_designation' => '',
        'a_approval'    => '',
        'a_designation' => '',
        'b_approval'    => '',
        'b_designation' => '',
    ];
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Gas Slip Issuance Dashboard</title>
    <!-- CSS in head.php -->
    <link rel="stylesheet" href="assets/css/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
</head>
<body>
<div class="container py-4">
<?php include 'includes/header.php'; ?>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show mt-2" role="alert">
        <?= htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php elseif (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show mt-2" role="alert">
        <?= htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php elseif (!$result || !$result->num_rows): ?>
    <div class="alert alert-warning mt-2" role="alert">
        ⚠️ Signatories for your area 
        (<strong><?= htmlspecialchars($_SESSION['area'], ENT_QUOTES, 'UTF-8') ?></strong>) 
        have not been set yet. 
        Please go to 
        <a href="#" class="alert-link" data-bs-toggle="modal" data-bs-target="#settingsModal">
            Settings
        </a> 
        to configure them.
    </div>
<?php endif; ?>

<div id="alertPlaceholder"></div>

<div class="table-container">
    <h5><strong>List of Gas Slip Issuance</strong></h5>

    <div class="top-bar mb-3 d-flex justify-content-between align-items-center">
        <div>
            <?php if (!$result || !$result->num_rows): ?>
                <!-- Disabled-looking button, but opens settings modal -->
                <button type="button" 
                        class="btn btn-primary px-2" 
                        id="create-slip-btn" 
                        data-bs-toggle="modal" 
                        data-bs-target="#settingsModal"
                        data-bs-toggle="tooltip" 
                        title="⚠️ Configure Settings first">
                    <i class="fa fa-file-text"></i> Create Slip
                </button>
            <?php else: ?>
                <!-- Active link if settings exist -->
                <a href="add_slip.php" class="btn btn-primary px-2" id="create-slip-btn"
                data-bs-toggle="tooltip" title="Create a new gas slip">
                    <i class="fa fa-file-text"></i> Create Slip
                </a>
            <?php endif; ?>

            <button type="submit" class="btn btn-secondary px-2" id="print-selected" 
                    data-bs-toggle="tooltip" title="Print selected slips">
                <i class="fa fa-print"></i> Print
            </button>
        </div>
        <div>

            <?php if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="register.php" 
                class="btn btn-success px-2" 
                id="register-btn"
                data-bs-toggle="tooltip" 
                title="Register a new account">
                    <i class="fa fa-user-plus"></i> Register
                </a>
            <?php endif; ?>

            <button type="button" 
                class="btn btn-info text-white px-2" 
                id="settings-btn" 
                data-bs-toggle="modal" 
                data-bs-target="#settingsModal"
                title="Open settings">
            <i class="fa fa-cogs"></i> Settings
            </button>
        </div>

    </div>

    <?php
    /** Paged list query (prepared with limit/offset) */

    $listSql = "
        SELECT gs.id, gs.gas_slip_id, gs.date_issued, gs.validity_until, 
            gs.purpose, gs.requested_by, gs.status,
            v.plate_no,
            MIN(r.origin) AS origin,
            CONCAT(
                MIN(r.origin), 
                ' → ',
                GROUP_CONCAT(r.destination ORDER BY gsr.sequence_no SEPARATOR ' → ')
            ) AS route
        FROM gas_slips gs
        JOIN vehicles v ON gs.vehicle_id = v.id
        LEFT JOIN gas_slip_routes gsr ON gs.id = gsr.gas_slip_id
        LEFT JOIN routes r ON gsr.route_id = r.id
        WHERE gs.area = ?
        GROUP BY gs.id
        ORDER BY gs.id DESC 
        LIMIT ? OFFSET ?
    ";

    $listStmt = $conn->prepare($listSql);
    $listStmt->bind_param("sii", $_SESSION['area'], $limit, $offset);
    $listStmt->execute();
    $result  = $listStmt->get_result();
    $counter = $offset;

    if ($result->num_rows > 0): ?>
        <form id="gas-slip-form" method="get" action="print_gas_slip.php">
            <div class="table-responsive">
                <table class="styled-table" id="gasSlipTable">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all"></th>
                            <!-- <th>ID</th> -->
                            <th width="10%">Gas Slip #</th>
                            <th width="10%">Date Issued</th>
                            <!-- <th>Valid Until</th> -->
                            <th width="10%">Plate No.</th>
                            <th width="40%">Route</th>
                            <th width="20%">Purpose</th>
                            <th width="10%">Requested By</th>
                            <th width="10%">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $result->fetch_assoc()):
                        $counter++;
                        $gas_slip_id   = htmlspecialchars($row['gas_slip_id'] ?? '', ENT_QUOTES, 'UTF-8');
                        $date_issued   = $row['date_issued'] ? date('Y-m-d', strtotime($row['date_issued'])) : '';
                        $valid_until   = $row['validity_until'] ? date('Y-m-d', strtotime($row['validity_until'])) : '';
                        $plate_no      = htmlspecialchars($row['plate_no'] ?? '', ENT_QUOTES, 'UTF-8');
                        $origin        = htmlspecialchars($row['origin'] ?? '', ENT_QUOTES, 'UTF-8');
                        $destination   = htmlspecialchars($row['destination'] ?? '', ENT_QUOTES, 'UTF-8');
                        $purpose       = htmlspecialchars($row['purpose'] ?? '', ENT_QUOTES, 'UTF-8');
                        $requested_by  = htmlspecialchars($row['requested_by'] ?? '', ENT_QUOTES, 'UTF-8');
                        $status        = htmlspecialchars(strtolower($row['status'] ?? ''), ENT_QUOTES, 'UTF-8');
                        $statusLabel   = htmlspecialchars(ucfirst($row['status'] ?? ''), ENT_QUOTES, 'UTF-8');
                        $idValue       = (int)$row['id'];
                    ?>
                        <tr class="clickable-row">
                            <td>
                                <input type="checkbox" class="row-checkbox" name="ids[]" value="<?= $idValue ?>">
                            </td>
                            <!-- <td><?= $counter ?></td> -->
                            <td class="status-<?= $status ?>">
                                <?= $gas_slip_id ?>
                            </td>
                            <td class="status-<?= $status ?>"><?= $date_issued ?></td>
                            <!-- <td><?= $valid_until ?></td> -->
                            <td class="status-<?= $status ?>"><?= $plate_no ?></td>
                            <td class="status-<?= $status ?>"><?= htmlspecialchars($row['route'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="status-<?= $status ?>"><?= $purpose ?></td>
                            <td class="status-<?= $status ?>"><?= $requested_by ?></td>
                            <td class="status-<?= $status ?>"><?= $statusLabel ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-2">
                <!-- (pagination UI if you add it later) -->
            </div>
        </form>

    <?php else: ?>
        <p>No gas slips have been created yet.</p>
    <?php endif; ?>
</div>

<!-- Settings Modal -->
<div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="save_settings.php" method="POST">
                <div class="modal-header">
                    <h6 class="modal-title" id="settingsModalLabel"><strong>System Settings</strong>
                        <?php if (!empty($_SESSION['area'])): ?>
                            <span class="badge bg-secondary ms-2">
                                <?= htmlspecialchars($_SESSION['area'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        <?php endif; ?>
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="area" value="<?= htmlspecialchars($_SESSION['area'] ?? 'Puerto Princesa [Main Office]', ENT_QUOTES, 'UTF-8') ?>">

                    <div class="mb-3">
                        <label class="form-label">Fuel Supplier</label>
                        <input type="text" name="fuel_supplier" class="form-control" value="<?= htmlspecialchars($settings['fuel_supplier'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Recommending Approval</label>
                        <input type="text" name="r_approval" class="form-control" value="<?= htmlspecialchars($settings['r_approval'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Designation</label>
                        <input type="text" name="r_designation" class="form-control" value="<?= htmlspecialchars($settings['r_designation'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Approved By [Coop-owned]</label>
                        <input type="text" name="b_approval" class="form-control" value="<?= htmlspecialchars($settings['b_approval'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Designation [Coop-owned]</label>
                        <input type="text" name="b_designation" class="form-control" value="<?= htmlspecialchars($settings['b_designation'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Approved By [Private/Personnal]</label>
                        <input type="text" name="a_approval" class="form-control" value="<?= htmlspecialchars($settings['a_approval'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Designation [Private/Personnal]</label>
                        <input type="text" name="a_designation" class="form-control" value="<?= htmlspecialchars($settings['a_designation'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" style="font-size: 14px;" data-bs-dismiss="modal"><i class="fa fa-ban"></i> Cancel</button>
                    <button type="submit" class="btn btn-primary" style="font-size: 14px;"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script>
$(document).ready(function () {
    // === GLOBAL SELECTED IDS ===
    let selectedIds = [];

    // === Initialize DataTable ===
    const table = $('#gasSlipTable').DataTable({
        paging: true,
        searching: true,
        ordering: true,
        responsive: true,
        pageLength: 10,
        language: {
            lengthMenu: 'Rows per page: _MENU_',
            search: '',
            searchPlaceholder: 'Search...',
            infoCallback: function (settings, start, end, max, total, pre) {
                return 'Entries: ' + start + '–' + end + '  |  Total: ' + total;
            },
            paginate: {
                first: 'First',
                previous: '<',
                next: '>',
                last: 'Last'
            }
        },
        pagingType: "full_numbers",
        lengthMenu: [[10, 25, 50, 75, 100], [10, 25, 50, 75, 100]],
        dom: '<"d-flex justify-content-between align-items-center mb-2"lf>rt<"d-flex justify-content-between align-items-center mt-3"ip>'
    });

    //for layout rearrangement
    const wrapper = $('.dataTables_wrapper');
    const lengthControl = wrapper.find('.dataTables_length');
    const filterControl = wrapper.find('.dataTables_filter');
    const topControls = $('<div class="top-controls"></div>');
    topControls.append(filterControl);
    topControls.append(lengthControl);
    wrapper.prepend(topControls);

    // Sync checkboxes on draw
    table.on('draw', function() {
        $('#gasSlipTable tbody .row-checkbox').each(function() {
            $(this).prop('checked', selectedIds.includes($(this).val()));
        });
        const allVisibleChecked = $('#gasSlipTable tbody .row-checkbox').length === 
                                  $('#gasSlipTable tbody .row-checkbox:checked').length;
        $('#select-all').prop('checked', allVisibleChecked);
    });

    // Row click toggling
    $('#gasSlipTable tbody').on('click', 'tr', function(e) {
        if ($(e.target).is('input[type="checkbox"]')) return;
        const checkbox = $(this).find('.row-checkbox');
        const id = checkbox.val();
        checkbox.prop('checked', !checkbox.prop('checked'));
        if (checkbox.prop('checked')) {
            if (!selectedIds.includes(id)) selectedIds.push(id);
        } else {
            selectedIds = selectedIds.filter(x => x !== id);
        }
    });

    // Individual checkbox change
    $('#gasSlipTable tbody').on('change', '.row-checkbox', function() {
        const id = $(this).val();
        if (this.checked) {
            if (!selectedIds.includes(id)) selectedIds.push(id);
        } else {
            selectedIds = selectedIds.filter(x => x !== id);
        }
    });

    // Select/Deselect all visible
    $('#select-all').on('change', function() {
        const checked = this.checked;
        $('#gasSlipTable tbody .row-checkbox').each(function() {
            const id = $(this).val();
            $(this).prop('checked', checked);
            if (checked && !selectedIds.includes(id)) selectedIds.push(id);
            if (!checked && selectedIds.includes(id)) selectedIds = selectedIds.filter(x => x !== id);
        });
    });

    // Form submission with all selected IDs
    $('#gas-slip-form').on('submit', function(e) {
        if (selectedIds.length === 0) {
            e.preventDefault();

            // Remove any previous alert
            $('#alertPlaceholder').empty();

            // Add Bootstrap warning alert
            $('#alertPlaceholder').html(`
                <div class="alert alert-warning alert-dismissible fade show mt-2" role="alert">
                    <strong>⚠️ No gas slips selected!</strong> Please choose at least one before printing.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `);

            // Auto-dismiss after 3 seconds
            setTimeout(() => {
                const alertElement = document.querySelector('#alertPlaceholder .alert');
                if (alertElement) {
                    bootstrap.Alert.getOrCreateInstance(alertElement).close();
                }
            }, 3000);

            return;
        }

        // Clear old hidden inputs and append new ones
        $(this).find('input[name="ids[]"]').remove();
        selectedIds.forEach(id => {
            $(this).append('<input type="hidden" name="ids[]" value="' + id + '">');
        });
    });


    // Row double-click → print single slip
    $('#gasSlipTable tbody').on('dblclick', 'tr', function(e) {
        const id = $(this).find('.row-checkbox').val();
        window.location.href = 'print_gas_slip.php?ids[]=' + id;
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.altKey && !e.shiftKey && !e.ctrlKey) {
            switch (e.key.toLowerCase()) {
                case 'c':
                    e.preventDefault();
                    window.location.href = 'add_slip.php';
                    break;
                case 'p':
                    e.preventDefault();
                    document.getElementById('gas-slip-form').submit();
                    break;
                case 's':
                    e.preventDefault();
                    new bootstrap.Modal(document.getElementById('settingsModal')).show();
                    break;
            }
        }
    });

    // Print selected button
    $('#print-selected').on('click', function() {
        $('#gas-slip-form').submit();
    });

    // Tooltips
    document.querySelectorAll('[title]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    // Auto-dismiss only dismissible alerts after 3s
    setTimeout(function () {
        document.querySelectorAll('.alert-dismissible').forEach(function(alertEl) {
            bootstrap.Alert.getOrCreateInstance(alertEl).close();
        });
    }, 3000);


    // Reset settings modal on close (back to initial values)
    document.getElementById('settingsModal').addEventListener('hidden.bs.modal', function () {
        this.querySelector('form').reset();
    });

});
</script>
<?php include 'includes/footer.php'; ?>
</body>
</html>

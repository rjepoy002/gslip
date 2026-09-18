<?php

session_start();

require_once 'includes/config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = getDBConnection();

// 🔐 AUTH GUARD
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['session_token']) ||
    !isset($_SESSION['role']) ||
    !isset($_SESSION['department_id']) ||
    !isset($_SESSION['area'])
) {
    header('Location: index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];
$department = $_SESSION['department_id'];
$area = $_SESSION['area'];

$hasDates = !empty($_SESSION['date_from']) || !empty($_SESSION['date_to']);


/* =========================================================
   ADD / UPDATE FUEL ITEM
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    header('Content-Type: application/json');

    $id = $_POST['fuel_item_id'] ?? null;
    $mode = $_POST['form_mode'] ?? '';

    $name = $_POST['fuel_name'] ?? '';
    $unit = $_POST['unit'] ?? '';
    $status = $_POST['status'] ?? '';
    $remarks = $_POST['remarks'] ?? '';

    if ($mode === 'add') {

        /*
         * Container is intentionally not included.
         * The database column remains untouched.
         */
        $stmt = $conn->prepare("
            INSERT INTO fuel_items
                (name, unit, status, remarks)
            VALUES
                (?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "ssss",
            $name,
            $unit,
            $status,
            $remarks
        );

    } else {

        /*
         * Container is intentionally not updated.
         * Existing container value remains unchanged.
         */
        $stmt = $conn->prepare("
            UPDATE fuel_items
            SET
                name = ?,
                unit = ?,
                status = ?,
                remarks = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "ssssi",
            $name,
            $unit,
            $status,
            $remarks,
            $id
        );
    }

    if ($stmt->execute()) {

        echo json_encode([
            'status' => 'success',
            'message' => $mode === 'add'
                ? 'Fuel item added successfully.'
                : 'Fuel item updated successfully.'
        ]);

    } else {

        echo json_encode([
            'status' => 'error',
            'message' => $stmt->error
        ]);
    }

    exit;
}


/* =========================================================
   DEACTIVATE FUEL ITEM
========================================================= */

if (isset($_GET['delete'])) {

    $id = intval($_GET['delete']);

    $conn->query("
        UPDATE fuel_items
        SET status = 'inactive'
        WHERE id = $id
    ");
}


/* =========================================================
   FETCH FUEL ITEMS LIST
========================================================= */

$conn->begin_transaction();

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        unit,
        status,
        remarks
    FROM fuel_items
    ORDER BY id DESC
");

$stmt->execute();

$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>Dashboard | e-GSlip</title>

    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">

    <?php include 'includes/dark-mode-preload.php'; ?>
    
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/icons/bootstrap-icons.css">

    <script src="assets/js/sweetalert2.all.min.js"></script>

</head>

<body>

<?php include 'includes/sidebar.php'; ?>

<?php include 'includes/add_fuelitems_modal.php'; ?>


<div class="app-content">

    <!-- Main Page Content -->
    <main class="main-content">

        <div class="panel-container">

            <!-- Page Header -->
            <div class="page-header mb-4">

                <h1 class="mb-1">
                    Fuel Items Management
                </h1>

                <p class="page-subtitle mb-1">
                    Manage fuel items with defined types, units, and status to ensure accurate and consistent fuel requests.
                </p>

            </div>


            <!-- Add Fuel Item Button -->
            <button
                class="btn btn-primary btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#addFuelItemsModal"
                onclick="resetFuelItemsForm()"
            >
                + Add Fuel Items
            </button>


            <!-- Fuel Items Table -->
            <table
                class="styled-table excel-table"
                id="FuelItemsTable"
            >

                <thead>

                    <tr class="group-header">

                        <th>No.</th>

                        <th>Fuel Name</th>

                        <th>Unit</th>

                        <th>Status</th>

                        <th>Remarks</th>

                    </tr>

                </thead>


                <tbody>

                <?php if ($result->num_rows > 0): ?>

                    <?php $no = 1; ?>

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <tr
                            class="FuelItems-row"
                            data-id="<?= $row['id'] ?>"
                            data-name="<?= htmlspecialchars($row['name']) ?>"
                            data-unit="<?= htmlspecialchars($row['unit']) ?>"
                            data-status="<?= htmlspecialchars($row['status']) ?>"
                            data-remarks="<?= htmlspecialchars($row['remarks']) ?>"
                        >

                            <td>
                                <?= $no++; ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['name']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['unit']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['status']) ?>
                            </td>

                            <td>
                                <?= $row['remarks'] !== null
                                    ? htmlspecialchars($row['remarks'])
                                    : '' ?>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="5"
                            class="text-center text-muted py-4"
                        >
                            No fuel items found
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </main>

</div>


<!-- JS -->
<script src="assets/js/jquery.min.js"></script>

<script src="assets/js/jquery.dataTables.min.js"></script>

<script src="assets/js/bootstrap.bundle.min.js"></script>

<script src="assets/js/sweetalert2.all.min.js"></script>

<script src="assets/js/app-ui.js"></script>

<script src="assets/js/notifications.js"></script>


<?php if (!empty($_SESSION['swal_success'])): ?>

<script>

Swal.fire({

    icon: 'success',

    title: 'Success',

    text: '<?= $_SESSION['swal_success']; ?>',

    timer: 2000,

    showConfirmButton: false

});

</script>

<?php
unset($_SESSION['swal_success']);
endif;
?>


<script>

$(function () {


    /* =========================================
       DATATABLE INITIALIZATION
    ========================================= */

    if ($.fn.DataTable.isDataTable('#FuelItemsTable')) {

        $('#FuelItemsTable').DataTable().destroy();

    }


    const table = $('#FuelItemsTable').DataTable({

        paging: true,

        searching: true,

        ordering: true,

        responsive: true,

        pageLength: 15,

        pagingType: "full_numbers",

        lengthMenu: [
            [15, 25, 50, 75, 100],
            [15, 25, 50, 75, 100]
        ],

        language: {

            lengthMenu: 'Rows per page: _MENU_',

            search: '',

            searchPlaceholder: 'Search...',

            infoCallback: function (
                settings,
                start,
                end,
                max,
                total
            ) {

                return 'Entries: ' +
                    start +
                    '–' +
                    end +
                    '  |  Total: ' +
                    total;

            },

            paginate: {

                first: 'First',

                previous: '<',

                next: '>',

                last: 'Last'

            }

        },

        dom:
            '<"d-flex justify-content-between align-items-center mb-2"fl>' +
            'rt' +
            '<"d-flex justify-content-between align-items-center mt-3"ip>'

    });


    /* =========================================
       ROW CLICK → EDIT MODE
    ========================================= */

    $(document).on('click', '.FuelItems-row', function () {

        const modal = new bootstrap.Modal(
            document.getElementById('addFuelItemsModal')
        );


        $('#fuel_item_id').val(
            $(this).data('id')
        );


        $('#fuel_name').val(
            $(this).data('name')
        );


        $('#unit').val(
            $(this).data('unit')
        );


        $('#status').val(
            $(this).data('status')
        );


        $('#remarks').val(
            $(this).data('remarks')
        );


        $('#formTitle').text(
            'Edit Fuel Items'
        );


        $('#addBtn').addClass('d-none');


        $('#updateBtn').removeClass('d-none');


        $('#form_mode').val('edit');


        modal.show();

    });


    /* =========================================
       SAVE FUEL ITEMS (AJAX)
    ========================================= */

    $('#fuelitemsform').on('submit', function (e) {

        e.preventDefault();


        $.ajax({

            url: 'fuel_items.php',

            type: 'POST',

            data: $(this).serialize(),

            dataType: 'json',


            success: function (response) {

                if (response.status === 'success') {

                    Swal.fire({

                        icon: 'success',

                        title: 'Success',

                        text: response.message,

                        timer: 2000,

                        showConfirmButton: false

                    }).then(() => {

                        location.reload();

                    });

                } else {

                    Swal.fire({

                        icon: 'error',

                        title: 'Error',

                        text: response.message

                    });

                }

            },


            error: function (xhr) {

                let message =
                    "Unknown server error.";


                try {

                    const json =
                        JSON.parse(
                            xhr.responseText
                        );

                    if (json.message) {

                        message =
                            json.message;

                    }

                } catch (e) {

                    message =
                        xhr.responseText;

                }


                Swal.fire({

                    icon: 'error',

                    title: 'Server Error',

                    text: message

                });

            }

        });

    });

});


/* =========================================
   RESET FUEL ITEMS FORM
========================================= */

function resetFuelItemsForm() {

    $('#fuelitemsform')[0].reset();


    $('#fuel_item_id').val('');


    $('#form_mode').val('add');


    $('#formTitle').text(
        'Add New Fuel Items'
    );


    $('#addBtn').removeClass('d-none');


    $('#updateBtn').addClass('d-none');

}

</script>

</body>

</html>
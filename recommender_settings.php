<?php
session_start();
require_once 'includes/config.php';

$conn = getDBConnection();

/* =========================================================
   AUTH GUARD
========================================================= */
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

$userId     = $_SESSION['user_id'];
$role       = $_SESSION['role'];
$department = $_SESSION['department_id'];
$area       = $_SESSION['area'];

/* =========================================================
   VERIFY CURRENT USER IS A PRIMARY RECOMMENDER
========================================================= */

$primaryStmt = $conn->prepare("
    SELECT 
        dr.id,
        u.first_name,
        u.middle_name,
        u.last_name,
        u.designation,
        u.department_id,
        u.area_id
    FROM department_recommenders dr
    INNER JOIN users u ON u.id = dr.user_id
    WHERE dr.user_id = ?
      AND dr.department_id = ?
      AND u.status = 'active'
    LIMIT 1
");

$primaryStmt->bind_param("ii", $userId, $department);
$primaryStmt->execute();

$primaryResult = $primaryStmt->get_result();
$primary = $primaryResult->fetch_assoc();

$primaryStmt->close();

if (!$primary) {

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>Access Restricted</title>

        <link rel="stylesheet" href="assets/css/bootstrap.min.css">
        <link rel="stylesheet" href="assets/css/sweetalert2.min.css">
    </head>

    <body>

        <script src="assets/js/sweetalert2.all.min.js"></script>

        <script>
        document.addEventListener('DOMContentLoaded', function () {

            Swal.fire({
                icon: 'warning',
                title: 'Access Restricted',
                text: 'Only Primary Recommenders can access Recommender Settings.',
                confirmButtonText: 'OK',
                confirmButtonColor: '#0d6efd',
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then(function () {

                window.location.href = 'dashboard.php';

            });

        });
        </script>

    </body>
    </html>
    <?php

    exit;
}
/* =========================================================
   LOAD CURRENT ACTIVE DELEGATION
========================================================= */

$delegation = null;

$delegationStmt = $conn->prepare("
    SELECT
        rd.id,
        rd.secondary_recommender_id,
        rd.start_date,
        rd.end_date,
        rd.status,
        CONCAT(
            u.first_name,
            CASE
                WHEN u.middle_name IS NOT NULL
                     AND TRIM(u.middle_name) <> ''
                THEN CONCAT(' ', LEFT(TRIM(u.middle_name), 1), '.')
                ELSE ''
            END,
            ' ',
            u.last_name
        ) AS secondary_name,
        u.designation AS secondary_designation
    FROM recommender_delegations rd
    INNER JOIN users u
        ON u.id = rd.secondary_recommender_id
    WHERE rd.primary_recommender_id = ?
      AND rd.department_id = ?
      AND rd.status = 'active'
      AND CURDATE() BETWEEN rd.start_date AND rd.end_date
    ORDER BY rd.id DESC
    LIMIT 1
");

$delegationStmt->bind_param("ii", $userId, $department);
$delegationStmt->execute();

$delegationResult = $delegationStmt->get_result();
$delegation = $delegationResult->fetch_assoc();

$delegationStmt->close();

/* =========================================================
   LOAD ELIGIBLE SECONDARY RECOMMENDERS
========================================================= */

$secondaryUsers = [];

$secondaryStmt = $conn->prepare("
    SELECT
        u.id,
        u.first_name,
        u.middle_name,
        u.last_name,
        u.designation
    FROM users u

    WHERE u.status = 'active'

      AND u.department_id = ?
      AND u.area_id = ?

      /* Do not allow the current Primary Recommender */
      AND u.id <> ?

      /* Do not allow existing Primary Recommenders */
      AND NOT EXISTS (
          SELECT 1
          FROM department_recommenders dr
          WHERE dr.user_id = u.id
            AND dr.department_id = u.department_id
      )

      /* Do not allow Approvers */
      AND NOT EXISTS (
          SELECT 1
          FROM department_approvers da
          WHERE da.user_id = u.id
      )

      /* Do not allow users who already have
         an active Secondary delegation */
      AND NOT EXISTS (
          SELECT 1
          FROM recommender_delegations rd
          WHERE rd.secondary_recommender_id = u.id
            AND rd.status = 'active'
            AND CURDATE() BETWEEN rd.start_date AND rd.end_date
      )

    ORDER BY
        u.first_name ASC,
        u.last_name ASC
");

$secondaryStmt->bind_param(
    "iii",
    $department,
    $area,
    $userId
);

$secondaryStmt->execute();

$secondaryResult = $secondaryStmt->get_result();

while ($row = $secondaryResult->fetch_assoc()) {
    $secondaryUsers[] = $row;
}

$secondaryStmt->close();


/* =========================================================
   DISPLAY NAME
========================================================= */

$primaryMiddle = '';

if (!empty($primary['middle_name'])) {
    $primaryMiddle =
        ' ' .
        strtoupper(substr(trim($primary['middle_name']), 0, 1)) .
        '.';
}

$primaryName =
    $primary['first_name'] .
    $primaryMiddle .
    ' ' .
    $primary['last_name'];

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Recommender Settings</title>
    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/icons/bootstrap-icons.css">

    <script src="assets/js/sweetalert2.all.min.js"></script>
</head>

<body>

<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/modals/recommender_modal.php'; ?>
<?php include 'includes/modals/primary_approver_modal.php'; ?>
<?php include 'includes/modals/secondary_approver_modal.php'; ?>
<?php include 'includes/modals/private_vehicle_approver_modal.php'; ?>
<?php include 'includes/modals/department_approver_modal.php'; ?>


<div class="app-content">

    <main class="main-content">

        <div class="panel-container">

            <!-- PAGE HEADER -->
            <div class="page-header mb-4">

                <h1 class="mb-1">
                    Settings
                </h1>

                <p class="page-subtitle mb-1">
                    Manage your temporary Secondary Recommender delegation.
                </p>

            </div>


        <!-- =====================================================
            PRIMARY RECOMMENDER
        ====================================================== -->

        <div class="settings-card">

            <div class="settings-card-header">

                <div class="settings-card-title">

                    <div>
                        <h2>Primary Recommender</h2>
                        <span>Your current recommender assignment</span>
                    </div>

                </div>

                <div class="authority-badge">
                    <i class="fa-solid fa-circle-check"></i>
                    Active
                </div>

            </div>

            <div class="settings-card-body">

                <div class="primary-profile">

                    <div class="primary-avatar">
                        <?= strtoupper(
                            substr($primary['first_name'], 0, 1) .
                            substr($primary['last_name'], 0, 1)
                        ) ?>
                    </div>

                    <div class="primary-info">

                        <div class="primary-name">
                            <?= htmlspecialchars($primaryName) ?>
                        </div>

                        <div class="primary-designation">
                            <?= htmlspecialchars(
                                $primary['designation'] ?? ''
                            ) ?>
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <?php if ($delegation): ?>

        <!-- =====================================================
            CURRENT ACTIVE DELEGATION
        ====================================================== -->

        <div class="settings-card">

            <div class="settings-card-header">

                <div class="settings-card-title">

                    <div>
                        <h2>Current Secondary Recommender</h2>
                        <span>Your temporary delegation is currently active</span>
                    </div>

                </div>

                <span class="status-active">
                    Active
                </span>

            </div>


            <div class="settings-card-body">

                <div class="delegation-grid">

                    <div>
                        <div class="info-label">
                            Secondary Recommender
                        </div>

                        <div class="info-value">
                            <?= htmlspecialchars(
                                $delegation['secondary_name']
                            ) ?>
                        </div>

                        <?php if (!empty($delegation['secondary_designation'])): ?>

                            <div class="info-subvalue">
                                <?= htmlspecialchars(
                                    $delegation['secondary_designation']
                                ) ?>
                            </div>

                        <?php endif; ?>
                    </div>


                    <div>
                        <div class="info-label">
                            Leave From
                        </div>

                        <div class="info-value">
                            <?= date(
                                'M d, Y',
                                strtotime($delegation['start_date'])
                            ) ?>
                        </div>
                    </div>


                    <div>
                        <div class="info-label">
                            Leave Until
                        </div>

                        <div class="info-value">
                            <?= date(
                                'M d, Y',
                                strtotime($delegation['end_date'])
                            ) ?>
                        </div>
                    </div>


                    <div>
                        <div class="info-label">
                            Status
                        </div>

                        <span class="status-active">
                            Active
                        </span>
                    </div>

                </div>


                <div class="delegation-note">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    The Secondary Recommender can recommend gas slips on your behalf
                    during the specified delegation period.
                </div>

                <!-- CANCEL BUTTON -->
                <form
                    method="POST"
                    action="cancel_recommender_delegation.php"
                    class="mt-3"
                    onsubmit="return confirmCancelDelegation(event);"
                >
                    <input
                        type="hidden"
                        name="delegation_id"
                        value="<?= (int) $delegation['id'] ?>"
                    >

                    <button
                        type="submit"
                        class="btn btn-outline-danger"
                    >
                        <i class="fa-solid fa-xmark me-1"></i>
                        Cancel Delegation
                    </button>
                </form>

            </div>

        </div>


        <?php else: ?>

        <!-- =====================================================
            CREATE DELEGATION
        ====================================================== -->

        <div class="settings-card">

            <div class="settings-card-header">

                <div class="settings-card-title">

                    <div>
                        <h2>Set Secondary Recommender</h2>
                        <span>Delegate your recommender authority during your leave</span>
                    </div>

                </div>

            </div>

            <div class="settings-card-body">

                    <form
                        method="POST"
                        action="save_recommender_delegation.php"
                        id="delegationForm"
                        onsubmit="return confirmSetDelegation(event);"
                    >

                    <div class="delegation-form">

                        <div class="form-group-modern">

                            <label for="secondary_recommender">
                                Secondary Recommender
                            </label>

                            <select
                                name="secondary_recommender_id"
                                id="secondary_recommender"
                                class="form-select"
                                required>

                                <option value="" selected disabled>
                                    Select a user
                                </option>

                                <?php foreach ($secondaryUsers as $user): ?>

                                    <?php

                                    $middleInitial = '';

                                    if (!empty($user['middle_name'])) {
                                        $middleInitial =
                                            ' ' .
                                            strtoupper(
                                                substr(
                                                    trim($user['middle_name']),
                                                    0,
                                                    1
                                                )
                                            ) .
                                            '.';
                                    }

                                    $name =
                                        $user['first_name'] .
                                        $middleInitial .
                                        ' ' .
                                        $user['last_name'];

                                    ?>

                                    <option value="<?= (int)$user['id'] ?>">
                                        <?= htmlspecialchars($name) ?>
                                        <?php if (!empty($user['designation'])): ?>
                                            — <?= htmlspecialchars($user['designation']) ?>
                                        <?php endif; ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                            <?php if (empty($secondaryUsers)): ?>

                                <div class="form-text text-danger mt-2">
                                    No eligible users are currently available.
                                </div>

                            <?php endif; ?>

                        </div>


                        <div class="form-group-modern">

                            <label for="start_date">
                                Leave From
                            </label>

                            <input
                                type="date"
                                name="start_date"
                                id="start_date"
                                class="form-control"
                                required>

                        </div>


                        <div class="form-group-modern">

                            <label for="end_date">
                                Leave Until
                            </label>

                            <input
                                type="date"
                                name="end_date"
                                id="end_date"
                                class="form-control"
                                required>

                        </div>

                    </div>


                    <div class="mt-4">

                        <button
                            type="submit"
                            class="btn-delegation"
                            <?= empty($secondaryUsers) ? 'disabled' : '' ?>>

                            <i class="fa-solid fa-user-check me-2"></i>
                            Set Secondary Recommender

                        </button>

                    </div>

                </form>

            </div>

        </div>

        <?php endif; ?>

    </div>
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/sweetalert2.all.min.js"></script>

<script src="assets/js/app-ui.js"></script>
<script src="assets/js/gas-slip.js"></script>
<script src="assets/js/notifications.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const startDate = document.getElementById('start_date');
    const endDate   = document.getElementById('end_date');

    function openCalendar(input) {
        if (input && typeof input.showPicker === 'function') {
            try {
                input.showPicker();
            } catch (e) {
                // Browser does not allow programmatic picker opening
            }
        }
    }

    if (startDate) {
        startDate.addEventListener('click', function () {
            openCalendar(this);
        });
    }

    if (endDate) {
        endDate.addEventListener('click', function () {
            openCalendar(this);
        });
    }

    /* Leave Until cannot be earlier than Leave From */
    if (startDate && endDate) {

        startDate.addEventListener('change', function () {

            endDate.min = this.value;

            if (endDate.value && endDate.value < this.value) {
                endDate.value = '';
            }

        });

    }

});
</script>

<script>
function confirmSetDelegation(event) {

    event.preventDefault();

    const form = event.target;

    const secondarySelect = document.getElementById(
        'secondary_recommender'
    );

    const startDate = document.getElementById(
        'start_date'
    );

    const endDate = document.getElementById(
        'end_date'
    );

    if (
        !secondarySelect ||
        !secondarySelect.value ||
        !startDate ||
        !startDate.value ||
        !endDate ||
        !endDate.value
    ) {
        return false;
    }

    const secondaryName =
        secondarySelect.options[
            secondarySelect.selectedIndex
        ].text;

    const formatDate = function (dateValue) {

        const date = new Date(
            dateValue + 'T00:00:00'
        );

        return date.toLocaleDateString('en-US', {
            month: 'long',
            day: 'numeric',
            year: 'numeric'
        });
    };

    const start = formatDate(startDate.value);
    const end   = formatDate(endDate.value);

    Swal.fire({

        icon: 'question',

        title: 'Set Secondary Recommender?',

        html: `
            <div style="text-align:left; font-size:14px;">

                <p style="margin-bottom:10px;">
                    You are about to designate:
                </p>

                <div style="
                    padding:12px 14px;
                    background:#f8f9fa;
                    border:1px solid #e9ecef;
                    border-radius:8px;
                    margin-bottom:14px;
                ">
                    <strong>
                        ${secondaryName}
                    </strong>
                </div>

                <div style="
                    margin-bottom:5px;
                    font-weight:600;
                ">
                    Delegation Period
                </div>

                <div style="
                    margin-bottom:14px;
                    color:#495057;
                ">
                    ${start} – ${end}
                </div>

                <div style="
                    color:#6c757d;
                    font-size:13px;
                    line-height:1.5;
                ">
                    This user will be authorized to recommend
                    gas slips on your behalf during this
                    delegation period.
                </div>

            </div>
        `,

        showCancelButton: true,

        confirmButtonText: 'Yes, Set Delegation',

        cancelButtonText: 'Cancel',

        confirmButtonColor: '#0d6efd',

        cancelButtonColor: '#6c757d',

        reverseButtons: true,

        allowOutsideClick: false,

        allowEscapeKey: false

    }).then(function (result) {

        if (result.isConfirmed) {

            form.submit();

        }

    });

    return false;
}

function confirmCancelDelegation(event) {

    event.preventDefault();

    const form = event.target;

    Swal.fire({
        icon: 'warning',
        title: 'Cancel Delegation?',
        text: 'This will immediately remove the Secondary Recommender\'s delegated authority.',
        showCancelButton: true,
        confirmButtonText: 'Yes, Cancel Delegation',
        cancelButtonText: 'Keep Delegation',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        reverseButtons: true,
        allowOutsideClick: false
    }).then((result) => {

        if (result.isConfirmed) {
            form.submit();
        }

    });

    return false;
}
</script>
</body>
</html>
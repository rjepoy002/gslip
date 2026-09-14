    <?php
    session_start();
    require_once 'includes/config.php';

    $conn = getDBConnection();

    /* ===============================
    Collect & sanitize inputs
    =============================== */
    $first_name  = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name   = trim($_POST['last_name'] ?? '');
    $username    = trim($_POST['username'] ?? '');
    $password    = $_POST['password'] ?? '';
    $confirm     = $_POST['confirm_password'] ?? '';
    $department_id = $_POST['department_id'] ?? '';
    $designation = trim($_POST['designation'] ?? '');
    $area_id        = trim($_POST['area'] ?? '8'); //area_id

    /* ===============================
    Basic validation
    =============================== */
    if (
        empty($first_name) ||
        empty($last_name) ||
        empty($username) ||
        empty($password) ||
        empty($confirm) ||
        empty($department_id) ||
        empty($designation)
    ) {
        header("Location: index.php?signup=error");
        exit;
    }

    if ($password !== $confirm) {
        header("Location: index.php?signup=error");
        exit;
    }

    if (strlen($password) < 8) {
        header("Location: index.php?signup=error");
        exit;
    }

    /* ===============================
    Get department name (for logic)
    =============================== */
    $stmt = $conn->prepare("
        SELECT name 
        FROM departments 
        WHERE id = ? AND status = 'active'
        LIMIT 1
    ");
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $dept = $result->fetch_assoc();
    $stmt->close();

    if (!$dept) {
        header("Location: index.php?signup=error");
        exit;
    }

    $department_name = $dept['name'];

    /* ===============================
    ANOD / ASOD → area REQUIRED
    =============================== */
    if (in_array($department_name, ['ANOD', 'ASOD']) && empty($area_id)) {
        header("Location: index.php?signup=error");
        exit;
    }

    /* ===============================
    Check existing username
    =============================== */
    $stmt = $conn->prepare("
        SELECT id, status
        FROM users
        WHERE username = ?
        LIMIT 1
    ");

    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();
    $existingUser = $result->fetch_assoc();

    $stmt->close();

    /* ===============================
    Hash password
    =============================== */
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    /* ===============================
    Rejected account → RESUBMIT
    =============================== */
    if ($existingUser && $existingUser['status'] === 'rejected') {

        $stmt = $conn->prepare("
            UPDATE users
            SET
                first_name = ?,
                middle_name = ?,
                last_name = ?,
                password = ?,
                department_id = ?,
                designation = ?,
                area_id = ?,
                status = 'inactive',
                remarks = NULL,
                declined_by = NULL,
                declined_at = NULL,
                created_at = NOW()
            WHERE username = ?
        ");

        $stmt->bind_param(
            "ssssisis",
            $first_name,
            $middle_name,
            $last_name,
            $hashed_password,
            $department_id,
            $designation,
            $area_id,
            $username
        );

        $stmt->execute();
        $stmt->close();

        $conn->close();

        header("Location: index.php?signup=resubmitted");
        exit;
    }

    /* ===============================
    Username already exists
    =============================== */
    if ($existingUser) {

        $conn->close();

        header("Location: index.php?signup=exists");
        exit;
    }

    /* ===============================
    Insert NEW user
    =============================== */
    $stmt = $conn->prepare("
        INSERT INTO users
        (
            first_name,
            middle_name,
            last_name,
            username,
            password,
            department_id,
            designation,
            area_id,
            role,
            status,
            created_at
        )
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, 'user', 'inactive', NOW())
    ");

    $stmt->bind_param(
        "sssssisi",
        $first_name,
        $middle_name,
        $last_name,
        $username,
        $hashed_password,
        $department_id,
        $designation,
        $area_id
    );

    $stmt->execute();
    $stmt->close();

    $conn->close();
    /* ===============================
    Redirect for SweetAlert success
    =============================== */
    header("Location: index.php?signup=success");
    exit;

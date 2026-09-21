<?php
session_start();
require_once "../includes/db.php";

/* =========================================================
   ADMIN ACCESS
========================================================= */
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../login.php");
    exit();
}

/* =========================================================
   CSRF
========================================================= */
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION["csrf_token"];

/* =========================================================
   HELPERS
========================================================= */
function cleanValue($value): string
{
    return trim((string)$value);
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$message = "";
$messageType = "success";

/* =========================================================
   HANDLE ACTIONS
========================================================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $postedToken = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["csrf_token"], $postedToken)) {

        $message = "Security validation failed. Please try again.";
        $messageType = "error";

    } else {

        $action = $_POST["action"] ?? "";

        /* -------------------------------------------------
           ADD STAFF
        ------------------------------------------------- */
        if ($action === "add_staff") {

            $name = cleanValue($_POST["name"] ?? "");
            $email = strtolower(cleanValue($_POST["email"] ?? ""));
            $password = (string)($_POST["password"] ?? "");

            if ($name === "" || $email === "" || $password === "") {

                $message = "Name, email and password are required.";
                $messageType = "error";

            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

                $message = "Please enter a valid email address.";
                $messageType = "error";

            } elseif (strlen($password) < 6) {

                $message = "Password must contain at least 6 characters.";
                $messageType = "error";

            } else {

                $check = mysqli_prepare(
                    $conn,
                    "SELECT id FROM users WHERE email = ? LIMIT 1"
                );

                mysqli_stmt_bind_param($check, "s", $email);
                mysqli_stmt_execute($check);
                $result = mysqli_stmt_get_result($check);
                $existing = mysqli_fetch_assoc($result);
                mysqli_stmt_close($check);

                if ($existing) {

                    $message = "A user with this email already exists.";
                    $messageType = "error";

                } else {

                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $role = "staff";

                    $stmt = mysqli_prepare(
                        $conn,
                        "INSERT INTO users (name, email, password, role)
                         VALUES (?, ?, ?, ?)"
                    );

                    mysqli_stmt_bind_param(
                        $stmt,
                        "ssss",
                        $name,
                        $email,
                        $hashedPassword,
                        $role
                    );

                    if (mysqli_stmt_execute($stmt)) {
                        $message = "Staff member added successfully.";
                        $messageType = "success";
                    } else {
                        $message = "Unable to add staff member.";
                        $messageType = "error";
                    }

                    mysqli_stmt_close($stmt);
                }
            }
        }

        /* -------------------------------------------------
           EDIT STAFF
        ------------------------------------------------- */
        if ($action === "edit_staff") {

            $staffId = (int)($_POST["staff_id"] ?? 0);
            $name = cleanValue($_POST["name"] ?? "");
            $email = strtolower(cleanValue($_POST["email"] ?? ""));
            $password = (string)($_POST["password"] ?? "");

            if ($staffId <= 0 || $name === "" || $email === "") {

                $message = "Staff ID, name and email are required.";
                $messageType = "error";

            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

                $message = "Please enter a valid email address.";
                $messageType = "error";

            } else {

                $checkStaff = mysqli_prepare(
                    $conn,
                    "SELECT id FROM users WHERE id = ? AND role = 'staff' LIMIT 1"
                );

                mysqli_stmt_bind_param($checkStaff, "i", $staffId);
                mysqli_stmt_execute($checkStaff);
                $staffResult = mysqli_stmt_get_result($checkStaff);
                $staffExists = mysqli_fetch_assoc($staffResult);
                mysqli_stmt_close($checkStaff);

                if (!$staffExists) {

                    $message = "Staff member not found.";
                    $messageType = "error";

                } else {

                    $checkEmail = mysqli_prepare(
                        $conn,
                        "SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1"
                    );

                    mysqli_stmt_bind_param($checkEmail, "si", $email, $staffId);
                    mysqli_stmt_execute($checkEmail);
                    $emailResult = mysqli_stmt_get_result($checkEmail);
                    $emailExists = mysqli_fetch_assoc($emailResult);
                    mysqli_stmt_close($checkEmail);

                    if ($emailExists) {

                        $message = "Another account already uses this email.";
                        $messageType = "error";

                    } elseif ($password !== "" && strlen($password) < 6) {

                        $message = "New password must contain at least 6 characters.";
                        $messageType = "error";

                    } else {

                        if ($password !== "") {

                            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                            $stmt = mysqli_prepare(
                                $conn,
                                "UPDATE users
                                 SET name = ?, email = ?, password = ?
                                 WHERE id = ? AND role = 'staff'"
                            );

                            mysqli_stmt_bind_param(
                                $stmt,
                                "sssi",
                                $name,
                                $email,
                                $hashedPassword,
                                $staffId
                            );

                        } else {

                            $stmt = mysqli_prepare(
                                $conn,
                                "UPDATE users
                                 SET name = ?, email = ?
                                 WHERE id = ? AND role = 'staff'"
                            );

                            mysqli_stmt_bind_param(
                                $stmt,
                                "ssi",
                                $name,
                                $email,
                                $staffId
                            );
                        }

                        if (mysqli_stmt_execute($stmt)) {
                            $message = "Staff member updated successfully.";
                            $messageType = "success";
                        } else {
                            $message = "Unable to update staff member.";
                            $messageType = "error";
                        }

                        mysqli_stmt_close($stmt);
                    }
                }
            }
        }

        /* -------------------------------------------------
           DELETE STAFF
        ------------------------------------------------- */
        if ($action === "delete_staff") {

            $staffId = (int)($_POST["staff_id"] ?? 0);

            if ($staffId <= 0) {

                $message = "Invalid staff member selected.";
                $messageType = "error";

            } else {

                $checkStaff = mysqli_prepare(
                    $conn,
                    "SELECT id, name FROM users
                     WHERE id = ? AND role = 'staff'
                     LIMIT 1"
                );

                mysqli_stmt_bind_param($checkStaff, "i", $staffId);
                mysqli_stmt_execute($checkStaff);
                $staffResult = mysqli_stmt_get_result($checkStaff);
                $staffRow = mysqli_fetch_assoc($staffResult);
                mysqli_stmt_close($checkStaff);

                if (!$staffRow) {

                    $message = "Staff member not found.";
                    $messageType = "error";

                } else {

                    $bookingCount = 0;

                    $checkBookings = mysqli_prepare(
                        $conn,
                        "SELECT COUNT(*) AS total FROM booking WHERE user_id = ?"
                    );

                    mysqli_stmt_bind_param($checkBookings, "i", $staffId);
                    mysqli_stmt_execute($checkBookings);
                    $bookingResult = mysqli_stmt_get_result($checkBookings);
                    $bookingRow = mysqli_fetch_assoc($bookingResult);
                    $bookingCount = (int)($bookingRow["total"] ?? 0);
                    mysqli_stmt_close($checkBookings);

                    if ($bookingCount > 0) {

                        $message = "This staff account has linked booking records and cannot be deleted.";
                        $messageType = "error";

                    } else {

                        $delete = mysqli_prepare(
                            $conn,
                            "DELETE FROM users WHERE id = ? AND role = 'staff'"
                        );

                        mysqli_stmt_bind_param($delete, "i", $staffId);

                        if (mysqli_stmt_execute($delete)) {
                            $message = "Staff member deleted successfully.";
                            $messageType = "success";
                        } else {
                            $message = "Unable to delete staff member.";
                            $messageType = "error";
                        }

                        mysqli_stmt_close($delete);
                    }
                }
            }
        }
    }
}

/* =========================================================
   FILTER / SEARCH
========================================================= */
$search = cleanValue($_GET["search"] ?? "");

if ($search !== "") {

    $like = "%" . $search . "%";

    $staffStmt = mysqli_prepare(
        $conn,
        "SELECT id, name, email, role
         FROM users
         WHERE role = 'staff'
         AND (CAST(id AS CHAR) LIKE ? OR name LIKE ? OR email LIKE ?)
         ORDER BY id DESC"
    );

    mysqli_stmt_bind_param(
        $staffStmt,
        "sss",
        $like,
        $like,
        $like
    );

    mysqli_stmt_execute($staffStmt);
    $staffResult = mysqli_stmt_get_result($staffStmt);

} else {

    $staffResult = mysqli_query(
        $conn,
        "SELECT id, name, email, role
         FROM users
         WHERE role = 'staff'
         ORDER BY id DESC"
    );
}

/* =========================================================
   STATS
========================================================= */
$staffCountResult = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM users WHERE role = 'staff'"
);
$staffCount = (int)(mysqli_fetch_assoc($staffCountResult)["total"] ?? 0);

$customerCountResult = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM users WHERE role = 'customer'"
);
$customerCount = (int)(mysqli_fetch_assoc($customerCountResult)["total"] ?? 0);

$adminCountResult = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM users WHERE role = 'admin'"
);
$adminCount = (int)(mysqli_fetch_assoc($adminCountResult)["total"] ?? 0);

$staffList = [];

if ($staffResult) {
    while ($row = mysqli_fetch_assoc($staffResult)) {
        $staffList[] = $row;
    }
}

if (isset($staffStmt)) {
    mysqli_stmt_close($staffStmt);
}

$adminName = $_SESSION["user_name"] ?? $_SESSION["name"] ?? "Administrator";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Staff | GlobeTrek</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <link rel="stylesheet" href="/globetrak/css/manage-staff.css?v=1000">
</head>

<body>

<div class="admin-staff-app">

    <!-- =====================================================
         SIDEBAR
    ====================================================== -->
    <aside class="staff-sidebar" id="staffSidebar">

        <div class="staff-brand">
            <a href="adashboard.php">
                Globe<span>Trek</span>
            </a>
            <small>ADMIN CONTROL PANEL</small>
        </div>

        <nav class="staff-admin-nav">

            <span class="sidebar-section-title">MAIN MENU</span>

            <a href="adashboard.php">
                <i class="fa-solid fa-gauge-high"></i>
                <span>Dashboard</span>
            </a>

            <a href="manage-users.php">
                <i class="fa-solid fa-users"></i>
                <span>Users</span>
            </a>

            <a href="manage-packages.php">
                <i class="fa-solid fa-suitcase-rolling"></i>
                <span>Packages</span>
            </a>

            <a href="manage-bookings.php">
                <i class="fa-solid fa-calendar-check"></i>
                <span>Bookings</span>
            </a>

            <a href="manage-payments.php">
                <i class="fa-solid fa-credit-card"></i>
                <span>Payments</span>
            </a>

            <span class="sidebar-section-title second">MANAGEMENT</span>

            <a href="manage_staff.php" class="active">
                <i class="fa-solid fa-user-tie"></i>
                <span>Staff</span>
            </a>

            <a href="../index.php" target="_blank">
                <i class="fa-solid fa-globe"></i>
                <span>View Website</span>
            </a>

        </nav>

        <div class="staff-sidebar-footer">

            <div class="staff-admin-card">
                <div class="staff-admin-avatar">
                    <?php echo strtoupper(substr($adminName, 0, 1)); ?>
                </div>

                <div>
                    <strong><?php echo h($adminName); ?></strong>
                    <span>Administrator</span>
                </div>
            </div>

            <a class="staff-logout" href="../logout.php">
                <i class="fa-solid fa-right-from-bracket"></i>
                Logout
            </a>

        </div>

    </aside>

    <!-- =====================================================
         MAIN
    ====================================================== -->
    <main class="staff-main">

        <header class="staff-topbar">

            <div class="staff-topbar-left">
                <button
                    type="button"
                    class="sidebar-toggle"
                    onclick="toggleStaffSidebar()"
                    aria-label="Toggle sidebar"
                >
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div>
                    <span class="topbar-kicker">TEAM MANAGEMENT</span>
                    <h1>Staff</h1>
                </div>
            </div>

            <div class="staff-topbar-right">
                <div class="topbar-user">
                    <div class="topbar-user-avatar">
                        <?php echo strtoupper(substr($adminName, 0, 1)); ?>
                    </div>
                    <div>
                        <strong><?php echo h($adminName); ?></strong>
                        <span>Administrator</span>
                    </div>
                </div>
            </div>

        </header>

        <section class="staff-content">

            <!-- PAGE INTRO -->
            <div class="staff-page-intro">

                <div>
                    <span class="eyebrow">
                        <i class="fa-solid fa-users-gear"></i>
                        GLOBETREK ADMIN
                    </span>

                    <h2>Manage Staff</h2>

                    <p>
                        Create, update and control staff accounts used by the GlobeTrek team.
                    </p>
                </div>

                <button
                    type="button"
                    class="primary-btn"
                    onclick="openAddStaffModal()"
                >
                    <i class="fa-solid fa-user-plus"></i>
                    Add Staff
                </button>

            </div>

            <!-- FLASH MESSAGE -->
            <?php if ($message !== ""): ?>
                <div class="staff-alert <?php echo $messageType === "success" ? "success" : "error"; ?>">
                    <div class="staff-alert-icon">
                        <i class="fa-solid <?php echo $messageType === "success" ? "fa-circle-check" : "fa-circle-exclamation"; ?>"></i>
                    </div>

                    <div>
                        <strong>
                            <?php echo $messageType === "success" ? "Success" : "Attention"; ?>
                        </strong>
                        <p><?php echo h($message); ?></p>
                    </div>

                    <button type="button" onclick="this.parentElement.remove()" aria-label="Close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            <?php endif; ?>

            <!-- STATS -->
            <div class="staff-stat-grid">

                <div class="staff-stat-card">
                    <div class="stat-icon orange">
                        <i class="fa-solid fa-user-tie"></i>
                    </div>
                    <div>
                        <span>Staff Accounts</span>
                        <strong><?php echo number_format($staffCount); ?></strong>
                    </div>
                </div>

                <div class="staff-stat-card">
                    <div class="stat-icon blue">
                        <i class="fa-solid fa-user-group"></i>
                    </div>
                    <div>
                        <span>Customer Accounts</span>
                        <strong><?php echo number_format($customerCount); ?></strong>
                    </div>
                </div>

                <div class="staff-stat-card">
                    <div class="stat-icon green">
                        <i class="fa-solid fa-user-shield"></i>
                    </div>
                    <div>
                        <span>Admin Accounts</span>
                        <strong><?php echo number_format($adminCount); ?></strong>
                    </div>
                </div>

            </div>

            <!-- SEARCH / FILTER -->
            <section class="staff-filter-card">

                <div class="filter-heading">
                    <div>
                        <span>STAFF DIRECTORY</span>
                        <h3>Find Staff Members</h3>
                    </div>

                    <div class="directory-count">
                        <strong><?php echo count($staffList); ?></strong>
                        <span>shown</span>
                    </div>
                </div>

                <form method="GET" class="staff-search-form">

                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="text"
                            name="search"
                            value="<?php echo h($search); ?>"
                            placeholder="Search by ID, name or email..."
                        >
                    </div>

                    <button class="search-btn" type="submit">
                        Search
                    </button>

                    <?php if ($search !== ""): ?>
                        <a class="clear-btn" href="manage_staff.php">
                            <i class="fa-solid fa-rotate-left"></i>
                            Clear
                        </a>
                    <?php endif; ?>

                </form>

            </section>

            <!-- TABLE -->
            <section class="staff-table-panel">

                <div class="panel-header">

                    <div>
                        <span class="panel-kicker">STAFF DIRECTORY</span>
                        <h3>All Staff Members</h3>
                    </div>

                    <span class="panel-records">
                        <?php echo number_format(count($staffList)); ?> records
                    </span>

                </div>

                <div class="staff-table-wrap">

                    <table class="staff-table">

                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>STAFF MEMBER</th>
                                <th>EMAIL</th>
                                <th>ACCESS</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php if (count($staffList) > 0): ?>

                            <?php foreach ($staffList as $staff): ?>

                                <tr>

                                    <td>
                                        <span class="staff-id">
                                            #<?php echo (int)$staff["id"]; ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="staff-person">
                                            <div class="staff-person-avatar">
                                                <?php echo strtoupper(substr($staff["name"], 0, 1)); ?>
                                            </div>

                                            <div>
                                                <strong><?php echo h($staff["name"]); ?></strong>
                                                <span>GlobeTrek Staff</span>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="staff-email">
                                            <?php echo h($staff["email"]); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="role-badge">
                                            <i class="fa-solid fa-user-tie"></i>
                                            Staff
                                        </span>
                                    </td>

                                    <td>

                                        <div class="row-actions">

                                            <button
                                                type="button"
                                                class="icon-btn edit"
                                                title="Edit staff"
                                                onclick='openEditStaffModal(
                                                    <?php echo (int)$staff["id"]; ?>,
                                                    <?php echo json_encode($staff["name"], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
                                                    <?php echo json_encode($staff["email"], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
                                                )'
                                            >
                                                <i class="fa-solid fa-pen"></i>
                                            </button>

                                            <button
                                                type="button"
                                                class="icon-btn delete"
                                                title="Delete staff"
                                                onclick='openDeleteStaffModal(
                                                    <?php echo (int)$staff["id"]; ?>,
                                                    <?php echo json_encode($staff["name"], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
                                                )'
                                            >
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <div class="empty-icon">
                                            <i class="fa-solid fa-user-slash"></i>
                                        </div>

                                        <h4>No staff members found</h4>

                                        <p>
                                            <?php
                                            echo $search !== ""
                                                ? "Try another search term."
                                                : "Add your first staff member to start managing the team.";
                                            ?>
                                        </p>

                                        <?php if ($search === ""): ?>
                                            <button
                                                type="button"
                                                class="primary-btn small"
                                                onclick="openAddStaffModal()"
                                            >
                                                <i class="fa-solid fa-user-plus"></i>
                                                Add Staff
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>

        </section>

    </main>

</div>

<!-- =========================================================
     ADD STAFF MODAL
========================================================= -->
<div class="admin-modal" id="addStaffModal">

    <div class="modal-backdrop" onclick="closeModal('addStaffModal')"></div>

    <div class="modal-card">

        <div class="modal-header">

            <div>
                <span class="modal-kicker">TEAM MANAGEMENT</span>
                <h3>Add Staff Member</h3>
            </div>

            <button
                type="button"
                class="modal-close"
                onclick="closeModal('addStaffModal')"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>

        </div>

        <form method="POST" class="staff-form">

            <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
            <input type="hidden" name="action" value="add_staff">

            <div class="form-grid">

                <div class="form-group full">
                    <label>Full Name</label>
                    <div class="input-with-icon">
                        <i class="fa-regular fa-user"></i>
                        <input
                            type="text"
                            name="name"
                            placeholder="Enter staff full name"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <div class="input-with-icon">
                        <i class="fa-regular fa-envelope"></i>
                        <input
                            type="email"
                            name="email"
                            placeholder="staff@globetr ek.com"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="input-with-icon">
                        <i class="fa-solid fa-lock"></i>
                        <input
                            type="password"
                            name="password"
                            placeholder="Minimum 6 characters"
                            minlength="6"
                            required
                        >
                    </div>
                </div>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="secondary-btn"
                    onclick="closeModal('addStaffModal')"
                >
                    Cancel
                </button>

                <button type="submit" class="primary-btn">
                    <i class="fa-solid fa-user-plus"></i>
                    Add Staff
                </button>

            </div>

        </form>

    </div>

</div>

<!-- =========================================================
     EDIT STAFF MODAL
========================================================= -->
<div class="admin-modal" id="editStaffModal">

    <div class="modal-backdrop" onclick="closeModal('editStaffModal')"></div>

    <div class="modal-card">

        <div class="modal-header">

            <div>
                <span class="modal-kicker">TEAM MANAGEMENT</span>
                <h3>Edit Staff Member</h3>
            </div>

            <button
                type="button"
                class="modal-close"
                onclick="closeModal('editStaffModal')"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>

        </div>

        <form method="POST" class="staff-form">

            <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
            <input type="hidden" name="action" value="edit_staff">
            <input type="hidden" name="staff_id" id="edit_staff_id">

            <div class="form-grid">

                <div class="form-group full">
                    <label>Full Name</label>
                    <div class="input-with-icon">
                        <i class="fa-regular fa-user"></i>
                        <input
                            type="text"
                            name="name"
                            id="edit_staff_name"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <div class="input-with-icon">
                        <i class="fa-regular fa-envelope"></i>
                        <input
                            type="email"
                            name="email"
                            id="edit_staff_email"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label>New Password <span>(optional)</span></label>
                    <div class="input-with-icon">
                        <i class="fa-solid fa-lock"></i>
                        <input
                            type="password"
                            name="password"
                            minlength="6"
                            placeholder="Leave blank to keep current"
                        >
                    </div>
                </div>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="secondary-btn"
                    onclick="closeModal('editStaffModal')"
                >
                    Cancel
                </button>

                <button type="submit" class="primary-btn">
                    <i class="fa-solid fa-floppy-disk"></i>
                    Save Changes
                </button>

            </div>

        </form>

    </div>

</div>

<!-- =========================================================
     DELETE STAFF MODAL
========================================================= -->
<div class="admin-modal" id="deleteStaffModal">

    <div class="modal-backdrop" onclick="closeModal('deleteStaffModal')"></div>

    <div class="modal-card delete-modal-card">

        <div class="delete-icon">
            <i class="fa-solid fa-trash-can"></i>
        </div>

        <span class="modal-kicker center">REMOVE STAFF</span>

        <h3>Delete Staff Member?</h3>

        <p>
            You are about to delete
            <strong id="delete_staff_name">this staff member</strong>.
            This action cannot be undone.
        </p>

        <form method="POST">

            <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
            <input type="hidden" name="action" value="delete_staff">
            <input type="hidden" name="staff_id" id="delete_staff_id">

            <div class="delete-actions">

                <button
                    type="button"
                    class="secondary-btn"
                    onclick="closeModal('deleteStaffModal')"
                >
                    Cancel
                </button>

                <button type="submit" class="danger-btn">
                    <i class="fa-solid fa-trash-can"></i>
                    Delete Staff
                </button>

            </div>

        </form>

    </div>

</div>

<script>
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add("show");
        document.body.classList.add("modal-open");
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove("show");
        document.body.classList.remove("modal-open");
    }
}

function openAddStaffModal() {
    const form = document.querySelector("#addStaffModal form");
    if (form) {
        form.reset();
    }
    openModal("addStaffModal");
}

function openEditStaffModal(id, name, email) {
    document.getElementById("edit_staff_id").value = id;
    document.getElementById("edit_staff_name").value = name;
    document.getElementById("edit_staff_email").value = email;

    openModal("editStaffModal");
}

function openDeleteStaffModal(id, name) {
    document.getElementById("delete_staff_id").value = id;
    document.getElementById("delete_staff_name").textContent = name;

    openModal("deleteStaffModal");
}

function toggleStaffSidebar() {
    const sidebar = document.getElementById("staffSidebar");
    if (sidebar) {
        sidebar.classList.toggle("open");
    }
}

/* ESC closes modal */
document.addEventListener("keydown", function(event) {
    if (event.key === "Escape") {
        document.querySelectorAll(".admin-modal.show").forEach(function(modal) {
            modal.classList.remove("show");
        });

        document.body.classList.remove("modal-open");
    }
});

/* Auto-hide success/error messages */
setTimeout(function() {
    document.querySelectorAll(".staff-alert").forEach(function(alertBox) {
        alertBox.classList.add("hide");
    });
}, 6000);
</script>

</body>
</html>

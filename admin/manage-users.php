<?php
session_start();
require_once "../includes/db.php";

/* ---------------------------------------------------------
   ADMIN ACCESS
--------------------------------------------------------- */
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit();
}

/* ---------------------------------------------------------
   CSRF TOKEN
--------------------------------------------------------- */
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION["csrf_token"];

/* ---------------------------------------------------------
   HELPERS
--------------------------------------------------------- */
function clean($value): string
{
    return trim((string)$value);
}

$message = "";
$messageType = "success";

/* ---------------------------------------------------------
   HANDLE POST ACTIONS
--------------------------------------------------------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $postedToken = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["csrf_token"], $postedToken)) {
        $message = "Security validation failed. Please try again.";
        $messageType = "error";
    } else {

        $action = $_POST["action"] ?? "";

        /* ---------------- ADD USER ---------------- */
        if ($action === "add_user") {

            $name = clean($_POST["name"] ?? "");
            $email = strtolower(clean($_POST["email"] ?? ""));
            $phone = clean($_POST["phone"] ?? "");
            $location = clean($_POST["location"] ?? "");
            $role = clean($_POST["role"] ?? "customer");
            $password = (string)($_POST["password"] ?? "");
            $bio = clean($_POST["bio"] ?? "");

            $allowedRoles = ["customer", "staff", "admin"];

            if ($name === "" || $email === "" || $password === "") {
                $message = "Name, email and password are required.";
                $messageType = "error";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = "Please enter a valid email address.";
                $messageType = "error";
            } elseif (strlen($password) < 6) {
                $message = "Password must contain at least 6 characters.";
                $messageType = "error";
            } elseif (!in_array($role, $allowedRoles, true)) {
                $message = "Invalid user role selected.";
                $messageType = "error";
            } else {

                $check = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? LIMIT 1");
                mysqli_stmt_bind_param($check, "s", $email);
                mysqli_stmt_execute($check);
                $result = mysqli_stmt_get_result($check);
                $exists = mysqli_fetch_assoc($result);
                mysqli_stmt_close($check);

                if ($exists) {
                    $message = "A user with this email already exists.";
                    $messageType = "error";
                } else {

                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                    $stmt = mysqli_prepare(
                        $conn,
                        "INSERT INTO users (name, email, password, role, phone, location, bio)
                         VALUES (?, ?, ?, ?, ?, ?, ?)"
                    );

                    mysqli_stmt_bind_param(
                        $stmt,
                        "sssssss",
                        $name,
                        $email,
                        $hashedPassword,
                        $role,
                        $phone,
                        $location,
                        $bio
                    );

                    if (mysqli_stmt_execute($stmt)) {
                        $message = "User added successfully.";
                        $messageType = "success";
                    } else {
                        $message = "Unable to add user.";
                        $messageType = "error";
                    }

                    mysqli_stmt_close($stmt);
                }
            }
        }

        /* ---------------- EDIT USER ---------------- */
        if ($action === "edit_user") {

            $userId = (int)($_POST["user_id"] ?? 0);
            $name = clean($_POST["name"] ?? "");
            $email = strtolower(clean($_POST["email"] ?? ""));
            $phone = clean($_POST["phone"] ?? "");
            $location = clean($_POST["location"] ?? "");
            $role = clean($_POST["role"] ?? "customer");
            $password = (string)($_POST["password"] ?? "");
            $bio = clean($_POST["bio"] ?? "");

            $allowedRoles = ["customer", "staff", "admin"];

            if ($userId <= 0 || $name === "" || $email === "") {
                $message = "Name and email are required.";
                $messageType = "error";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = "Please enter a valid email address.";
                $messageType = "error";
            } elseif (!in_array($role, $allowedRoles, true)) {
                $message = "Invalid user role selected.";
                $messageType = "error";
            } elseif ($userId === (int)$_SESSION["user_id"] && $role !== "admin") {
                $message = "You cannot remove the admin role from your own account.";
                $messageType = "error";
            } else {

                $check = mysqli_prepare(
                    $conn,
                    "SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1"
                );
                mysqli_stmt_bind_param($check, "si", $email, $userId);
                mysqli_stmt_execute($check);
                $result = mysqli_stmt_get_result($check);
                $exists = mysqli_fetch_assoc($result);
                mysqli_stmt_close($check);

                if ($exists) {
                    $message = "Another user already uses this email.";
                    $messageType = "error";
                } else {

                    if ($password !== "") {

                        if (strlen($password) < 6) {
                            $message = "New password must contain at least 6 characters.";
                            $messageType = "error";
                        } else {
                            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                            $stmt = mysqli_prepare(
                                $conn,
                                "UPDATE users
                                 SET name = ?, email = ?, password = ?, role = ?, phone = ?, location = ?, bio = ?
                                 WHERE id = ?"
                            );

                            mysqli_stmt_bind_param(
                                $stmt,
                                "sssssssi",
                                $name,
                                $email,
                                $hashedPassword,
                                $role,
                                $phone,
                                $location,
                                $bio,
                                $userId
                            );

                            if (mysqli_stmt_execute($stmt)) {
                                $message = "User updated successfully.";
                                $messageType = "success";
                            } else {
                                $message = "Unable to update user.";
                                $messageType = "error";
                            }

                            mysqli_stmt_close($stmt);
                        }

                    } else {

                        $stmt = mysqli_prepare(
                            $conn,
                            "UPDATE users
                             SET name = ?, email = ?, role = ?, phone = ?, location = ?, bio = ?
                             WHERE id = ?"
                        );

                        mysqli_stmt_bind_param(
                            $stmt,
                            "ssssssi",
                            $name,
                            $email,
                            $role,
                            $phone,
                            $location,
                            $bio,
                            $userId
                        );

                        if (mysqli_stmt_execute($stmt)) {
                            $message = "User updated successfully.";
                            $messageType = "success";
                        } else {
                            $message = "Unable to update user.";
                            $messageType = "error";
                        }

                        mysqli_stmt_close($stmt);
                    }
                }
            }
        }

        /* ---------------- DELETE USER ---------------- */
        if ($action === "delete_user") {

            $userId = (int)($_POST["user_id"] ?? 0);

            if ($userId <= 0) {
                $message = "Invalid user selected.";
                $messageType = "error";
            } elseif ($userId === (int)$_SESSION["user_id"]) {
                $message = "You cannot delete your own admin account.";
                $messageType = "error";
            } else {

                /* Check whether the user has bookings.
                   Their user account should not be deleted if bookings exist,
                   because those records depend on the customer ID. */
                $bookingCheck = mysqli_prepare(
                    $conn,
                    "SELECT COUNT(*) AS total FROM booking WHERE user_id = ?"
                );
                mysqli_stmt_bind_param($bookingCheck, "i", $userId);
                mysqli_stmt_execute($bookingCheck);
                $bookingResult = mysqli_stmt_get_result($bookingCheck);
                $bookingRow = mysqli_fetch_assoc($bookingResult);
                mysqli_stmt_close($bookingCheck);

                $bookingCount = (int)($bookingRow["total"] ?? 0);

                if ($bookingCount > 0) {
                    $message = "This user has {$bookingCount} booking(s). Delete or manage those bookings first.";
                    $messageType = "error";
                } else {

                    $deleteStmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
                    mysqli_stmt_bind_param($deleteStmt, "i", $userId);

                    if (mysqli_stmt_execute($deleteStmt) && mysqli_stmt_affected_rows($deleteStmt) > 0) {
                        $message = "User deleted successfully.";
                        $messageType = "success";
                    } else {
                        $message = "Unable to delete user.";
                        $messageType = "error";
                    }

                    mysqli_stmt_close($deleteStmt);
                }
            }
        }
    }
}

/* ---------------------------------------------------------
   FILTERS
--------------------------------------------------------- */
$search = clean($_GET["search"] ?? "");
$roleFilter = clean($_GET["role"] ?? "");

$allowedRoles = ["customer", "staff", "admin"];

if (!in_array($roleFilter, $allowedRoles, true)) {
    $roleFilter = "";
}

/* ---------------------------------------------------------
   COUNTS
--------------------------------------------------------- */
$totalUsers = 0;
$totalCustomers = 0;
$totalStaff = 0;
$totalAdmins = 0;

$countResult = mysqli_query(
    $conn,
    "SELECT
        COUNT(*) AS total_users,
        SUM(role = 'customer') AS customers,
        SUM(role = 'staff') AS staff_count,
        SUM(role = 'admin') AS admins
     FROM users"
);

if ($countResult) {
    $countRow = mysqli_fetch_assoc($countResult);
    $totalUsers = (int)($countRow["total_users"] ?? 0);
    $totalCustomers = (int)($countRow["customers"] ?? 0);
    $totalStaff = (int)($countRow["staff_count"] ?? 0);
    $totalAdmins = (int)($countRow["admins"] ?? 0);
}

/* ---------------------------------------------------------
   LOAD USERS
--------------------------------------------------------- */
$users = [];

if ($search !== "" && $roleFilter !== "") {

    $like = "%" . $search . "%";

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, name, email, role, phone, location, bio, profile_image
         FROM users
         WHERE role = ?
           AND (name LIKE ? OR email LIKE ? OR phone LIKE ? OR location LIKE ?)
         ORDER BY id DESC"
    );

    mysqli_stmt_bind_param($stmt, "sssss", $roleFilter, $like, $like, $like, $like);

} elseif ($search !== "") {

    $like = "%" . $search . "%";

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, name, email, role, phone, location, bio, profile_image
         FROM users
         WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? OR location LIKE ?
         ORDER BY id DESC"
    );

    mysqli_stmt_bind_param($stmt, "ssss", $like, $like, $like, $like);

} elseif ($roleFilter !== "") {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, name, email, role, phone, location, bio, profile_image
         FROM users
         WHERE role = ?
         ORDER BY id DESC"
    );

    mysqli_stmt_bind_param($stmt, "s", $roleFilter);

} else {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, name, email, role, phone, location, bio, profile_image
         FROM users
         ORDER BY id DESC"
    );
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $users[] = $row;
}

mysqli_stmt_close($stmt);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Users | GlobeTrek</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link
        rel="stylesheet"
        href="../css/manage-users.css?v=300">
</head>

<body>

<div class="admin-booking-app">

    <!-- =================================================
         SIDEBAR
    ================================================== -->
    <aside class="booking-sidebar" id="bookingSidebar">

        <div class="booking-brand">
            <a href="../index.php">
                Globe<span>Trek</span>
            </a>

            <small>
                ADMIN PANEL
            </small>
        </div>

        <nav class="booking-admin-nav">

            <span class="sidebar-section-title">
                MAIN MENU
            </span>

            <a href="adashboard.php">
                <i class="fa-solid fa-chart-pie"></i>
                Dashboard
            </a>

            <a href="manage-users.php" class="active">
                <i class="fa-solid fa-users"></i>
                Users
            </a>

            <a href="manage-packages.php">
                <i class="fa-solid fa-map-location-dot"></i>
                Packages
            </a>

            <a href="manage-bookings.php">
                <i class="fa-solid fa-calendar-check"></i>
                Bookings
            </a>

            <a href="manage-payments.php">
                <i class="fa-solid fa-credit-card"></i>
                Payments
            </a>

            <span class="sidebar-section-title second">
                MANAGEMENT
            </span>

            <a href="manage_staff.php">
                <i class="fa-solid fa-user-shield"></i>
                Staff
            </a>

            <a href="../index.php">
                <i class="fa-solid fa-globe"></i>
                View Website
            </a>

        </nav>

        <div class="booking-sidebar-bottom">

            <div class="booking-admin-profile">

                <div class="booking-admin-avatar">
                    <?php echo htmlspecialchars($adminName[0] ?? "A"); ?>
                </div>

                <div>
                    <strong>
                        <?php echo htmlspecialchars($adminName ?? "Administrator"); ?>
                    </strong>

                    <small>
                        Administrator
                    </small>
                </div>

            </div>

            <a
                href="../logout.php"
                class="sidebar-logout">

                <i class="fa-solid fa-right-from-bracket"></i>
                Logout

            </a>

        </div>

    </aside>

    <!-- =================================================
         MAIN
    ================================================== -->
    <main class="booking-main">

        <header class="booking-topbar">

            <button
                type="button"
                class="mobile-sidebar-button"
                id="mobileSidebarButton"
                aria-label="Open admin menu">

                <i class="fa-solid fa-bars"></i>

            </button>

            <div>

                <span class="topbar-eyebrow">
                    MANAGEMENT
                </span>

                <h1>
                    Users
                </h1>

                <p>
                    Review and manage GlobeTrek customer, staff and administrator accounts.
                </p>

            </div>

            <div class="topbar-admin">

                <div class="topbar-avatar">
                    <?php echo htmlspecialchars($adminName[0] ?? "A"); ?>
                </div>

                <div>
                    <strong>
                        <?php echo htmlspecialchars($adminName ?? "Administrator"); ?>
                    </strong>

                    <small>
                        Administrator
                    </small>
                </div>

            </div>

        </header>

        <!-- =================================================
             ALERT
        ================================================== -->
        <?php if ($message !== ""): ?>

            <div class="booking-alert <?php echo $messageType === "success" ? "success" : "error"; ?>">

                <i class="fa-solid <?php echo $messageType === "success" ? "fa-circle-check" : "fa-circle-exclamation"; ?>"></i>

                <span>
                    <?php echo htmlspecialchars($message); ?>
                </span>

                <button
                    type="button"
                    class="alert-close"
                    onclick="this.parentElement.remove();">

                    <i class="fa-solid fa-xmark"></i>

                </button>

            </div>

        <?php endif; ?>

        <!-- =================================================
             INTRO
        ================================================== -->
        <section class="booking-page-intro">

            <div>

                <span>
                    USER OPERATIONS
                </span>

                <h2>
                    User Management
                </h2>

                <p>
                    Manage registered accounts, roles and contact information from one place.
                </p>

            </div>

            <div class="intro-side">

                <div class="intro-total">
                    <span>
                        TOTAL USERS
                    </span>

                    <strong>
                        <?php echo number_format($totalUsers); ?>
                    </strong>
                </div>

                <button
                    type="button"
                    class="intro-add-button"
                    onclick="openAddModal()">

                    <i class="fa-solid fa-user-plus"></i>
                    Add User

                </button>

            </div>

        </section>

        <!-- =================================================
             USER STATS
        ================================================== -->
        <section class="booking-stat-grid user-stat-grid">

            <div class="booking-stat">

                <div class="booking-stat-icon total">
                    <i class="fa-solid fa-users"></i>
                </div>

                <div>
                    <span>
                        All Users
                    </span>

                    <strong>
                        <?php echo number_format($totalUsers); ?>
                    </strong>
                </div>

            </div>

            <div class="booking-stat">

                <div class="booking-stat-icon pending">
                    <i class="fa-solid fa-user"></i>
                </div>

                <div>
                    <span>
                        Customers
                    </span>

                    <strong>
                        <?php echo number_format($totalCustomers); ?>
                    </strong>
                </div>

            </div>

            <div class="booking-stat">

                <div class="booking-stat-icon confirmed">
                    <i class="fa-solid fa-user-tie"></i>
                </div>

                <div>
                    <span>
                        Staff
                    </span>

                    <strong>
                        <?php echo number_format($totalStaff); ?>
                    </strong>
                </div>

            </div>

            <div class="booking-stat">

                <div class="booking-stat-icon paid">
                    <i class="fa-solid fa-user-shield"></i>
                </div>

                <div>
                    <span>
                        Administrators
                    </span>

                    <strong>
                        <?php echo number_format($totalAdmins); ?>
                    </strong>
                </div>

            </div>

        </section>

        <!-- =================================================
             FILTER
        ================================================== -->
        <section class="booking-filter-card">

            <form
                method="GET"
                class="booking-filter-form user-filter-form">

                <div class="filter-search">

                    <i class="fa-solid fa-magnifying-glass"></i>

                    <input
                        type="text"
                        name="search"
                        value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Search name, email, phone or location...">

                </div>

                <div class="filter-status">

                    <select name="role">

                        <option value="">
                            All roles
                        </option>

                        <option
                            value="customer"
                            <?php echo $roleFilter === "customer" ? "selected" : ""; ?>>
                            Customer
                        </option>

                        <option
                            value="staff"
                            <?php echo $roleFilter === "staff" ? "selected" : ""; ?>>
                            Staff
                        </option>

                        <option
                            value="admin"
                            <?php echo $roleFilter === "admin" ? "selected" : ""; ?>>
                            Admin
                        </option>

                    </select>

                </div>

                <button
                    type="submit"
                    class="filter-button">

                    <i class="fa-solid fa-filter"></i>
                    Filter

                </button>

                <?php if ($search !== "" || $roleFilter !== ""): ?>

                    <a
                        href="manage-users.php"
                        class="clear-filter">

                        Clear

                    </a>

                <?php endif; ?>

            </form>

        </section>

        <!-- =================================================
             TABLE
        ================================================== -->
        <section class="booking-table-panel">

            <div class="table-panel-header">

                <div>

                    <span>
                        ACCOUNT DIRECTORY
                    </span>

                    <h2>
                        Registered Users
                    </h2>

                </div>

                <div class="result-count">

                    <?php echo count($users); ?>

                    result<?php echo count($users) === 1 ? "" : "s"; ?>

                </div>

            </div>

            <?php if (!empty($users)): ?>

                <div class="table-scroll">

                    <table class="booking-table users-table">

                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Contact</th>
                                <th>Location</th>
                                <th>Role</th>
                                <th>Account</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($users as $user): ?>

                            <?php
                            $userId = (int)$user["id"];
                            $userName = $user["name"] ?? "Unknown User";
                            $userEmail = $user["email"] ?? "";
                            $userPhone = $user["phone"] ?? "";
                            $userLocation = $user["location"] ?? "";
                            $userRole = $user["role"] ?? "customer";
                            $userBio = $user["bio"] ?? "";
                            $profileImage = $user["profile_image"] ?? "";

                            $initial = strtoupper(substr($userName, 0, 1));
                            $roleClass = strtolower($userRole);
                            ?>

                            <tr>

                                <!-- USER -->
                                <td>

                                    <div class="customer-cell">

                                        <?php if ($profileImage !== ""): ?>

                                            <img
                                                src="../images/<?php echo htmlspecialchars($profileImage); ?>"
                                                alt="<?php echo htmlspecialchars($userName); ?>"
                                                class="customer-avatar profile-user-avatar"
                                                onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';">

                                            <div
                                                class="customer-avatar profile-fallback"
                                                style="display:none;">
                                                <?php echo htmlspecialchars($initial); ?>
                                            </div>

                                        <?php else: ?>

                                            <div class="customer-avatar">
                                                <?php echo htmlspecialchars($initial); ?>
                                            </div>

                                        <?php endif; ?>

                                        <div>

                                            <strong>
                                                <?php echo htmlspecialchars($userName); ?>
                                            </strong>

                                            <small>
                                                <?php echo htmlspecialchars($userEmail); ?>
                                            </small>

                                        </div>

                                    </div>

                                </td>

                                <!-- CONTACT -->
                                <td>

                                    <div class="user-contact-cell">

                                        <strong>
                                            <?php echo $userPhone !== "" ? htmlspecialchars($userPhone) : "No phone"; ?>
                                        </strong>

                                        <small>
                                            <?php echo htmlspecialchars($userEmail); ?>
                                        </small>

                                    </div>

                                </td>

                                <!-- LOCATION -->
                                <td>

                                    <div class="package-cell">

                                        <strong>
                                            <?php echo $userLocation !== "" ? htmlspecialchars($userLocation) : "Not set"; ?>
                                        </strong>

                                        <span>
                                            <i class="fa-solid fa-location-dot"></i>
                                            Account location
                                        </span>

                                    </div>

                                </td>

                                <!-- ROLE -->
                                <td>

                                    <span class="user-role-badge <?php echo htmlspecialchars($roleClass); ?>">

                                        <i class="fa-solid
                                            <?php
                                            if ($roleClass === "admin") {
                                                echo "fa-user-shield";
                                            } elseif ($roleClass === "staff") {
                                                echo "fa-user-tie";
                                            } else {
                                                echo "fa-user";
                                            }
                                            ?>">
                                        </i>

                                        <?php echo htmlspecialchars(ucfirst($userRole)); ?>

                                    </span>

                                </td>

                                <!-- ACCOUNT -->
                                <td>

                                    <div class="booking-id-cell">

                                        <span class="booking-id-icon">
                                            <i class="fa-solid fa-hashtag"></i>
                                        </span>

                                        <div>
                                            <strong>
                                                <?php echo $userId; ?>
                                            </strong>

                                            <small>
                                                User ID
                                            </small>
                                        </div>

                                    </div>

                                </td>

                                <!-- ACTIONS -->
                                <td>

                                    <div class="booking-actions">

                                        <button
                                            type="button"
                                            class="action-button update"
                                            onclick='openEditModal(<?php echo json_encode([
                                                "id" => $userId,
                                                "name" => $userName,
                                                "email" => $userEmail,
                                                "phone" => $userPhone,
                                                "location" => $userLocation,
                                                "role" => $userRole,
                                                "bio" => $userBio
                                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>)'>

                                            <i class="fa-solid fa-pen"></i>
                                            Edit

                                        </button>

                                        <?php if ($userId !== (int)$_SESSION["user_id"]): ?>

                                            <button
                                                type="button"
                                                class="action-button delete"
                                                onclick='openDeleteModal(
                                                    <?php echo $userId; ?>,
                                                    <?php echo json_encode($userName, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>
                                                )'>

                                                <i class="fa-solid fa-trash"></i>

                                            </button>

                                        <?php endif; ?>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-bookings">

                    <div class="empty-icon">
                        <i class="fa-solid fa-user-slash"></i>
                    </div>

                    <h3>
                        No users found
                    </h3>

                    <p>
                        No accounts match your current search or role filter.
                    </p>

                    <a
                        href="manage-users.php"
                        class="empty-button">

                        View All Users

                    </a>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>

<!-- =====================================================
     ADD USER MODAL
====================================================== -->
<div class="admin-modal" id="addUserModal">

    <div class="modal-card">

        <button
            type="button"
            class="modal-close"
            onclick="closeAddModal()">

            <i class="fa-solid fa-xmark"></i>

        </button>

        <div class="modal-icon update-icon">
            <i class="fa-solid fa-user-plus"></i>
        </div>

        <span class="modal-label">
            ACCOUNT MANAGEMENT
        </span>

        <h2>
            Add User
        </h2>

        <p>
            Create a new GlobeTrek user account.
        </p>

        <form method="POST" class="modal-form">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars($csrfToken); ?>">

            <input
                type="hidden"
                name="action"
                value="add_user">

            <div class="modal-grid">

                <div>
                    <label for="add_name">Full Name</label>

                    <input
                        type="text"
                        id="add_name"
                        name="name"
                        maxlength="100"
                        required>
                </div>

                <div>
                    <label for="add_email">Email</label>

                    <input
                        type="email"
                        id="add_email"
                        name="email"
                        maxlength="150"
                        required>
                </div>

                <div>
                    <label for="add_phone">Phone</label>

                    <input
                        type="text"
                        id="add_phone"
                        name="phone"
                        maxlength="30">
                </div>

                <div>
                    <label for="add_location">Location</label>

                    <input
                        type="text"
                        id="add_location"
                        name="location"
                        maxlength="100">
                </div>

                <div>
                    <label for="add_role">Role</label>

                    <select
                        id="add_role"
                        name="role"
                        required>

                        <option value="customer">Customer</option>
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>

                    </select>
                </div>

                <div>
                    <label for="add_password">Password</label>

                    <input
                        type="password"
                        id="add_password"
                        name="password"
                        minlength="6"
                        required>
                </div>

                <div class="full">
                    <label for="add_bio">Bio</label>

                    <textarea
                        id="add_bio"
                        name="bio"
                        maxlength="500"
                        rows="3"></textarea>
                </div>

            </div>

            <div class="delete-actions">

                <button
                    type="button"
                    class="modal-secondary-button"
                    onclick="closeAddModal()">

                    Cancel

                </button>

                <button
                    type="submit"
                    class="modal-primary-button">

                    <i class="fa-solid fa-user-plus"></i>
                    Create User

                </button>

            </div>

        </form>

    </div>

</div>

<!-- =====================================================
     EDIT USER MODAL
====================================================== -->
<div class="admin-modal" id="editUserModal">

    <div class="modal-card">

        <button
            type="button"
            class="modal-close"
            onclick="closeEditModal()">

            <i class="fa-solid fa-xmark"></i>

        </button>

        <div class="modal-icon update-icon">
            <i class="fa-solid fa-user-pen"></i>
        </div>

        <span class="modal-label">
            ACCOUNT MANAGEMENT
        </span>

        <h2>
            Edit User
        </h2>

        <p>
            Update account details and permissions.
        </p>

        <form method="POST" class="modal-form">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars($csrfToken); ?>">

            <input
                type="hidden"
                name="action"
                value="edit_user">

            <input
                type="hidden"
                name="user_id"
                id="edit_user_id">

            <div class="modal-grid">

                <div>
                    <label for="edit_name">Full Name</label>

                    <input
                        type="text"
                        id="edit_name"
                        name="name"
                        maxlength="100"
                        required>
                </div>

                <div>
                    <label for="edit_email">Email</label>

                    <input
                        type="email"
                        id="edit_email"
                        name="email"
                        maxlength="150"
                        required>
                </div>

                <div>
                    <label for="edit_phone">Phone</label>

                    <input
                        type="text"
                        id="edit_phone"
                        name="phone"
                        maxlength="30">
                </div>

                <div>
                    <label for="edit_location">Location</label>

                    <input
                        type="text"
                        id="edit_location"
                        name="location"
                        maxlength="100">
                </div>

                <div>
                    <label for="edit_role">Role</label>

                    <select
                        id="edit_role"
                        name="role"
                        required>

                        <option value="customer">Customer</option>
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>

                    </select>
                </div>

                <div>
                    <label for="edit_password">New Password</label>

                    <input
                        type="password"
                        id="edit_password"
                        name="password"
                        minlength="6"
                        placeholder="Leave empty to keep current">
                </div>

                <div class="full">
                    <label for="edit_bio">Bio</label>

                    <textarea
                        id="edit_bio"
                        name="bio"
                        maxlength="500"
                        rows="3"></textarea>
                </div>

            </div>

            <div class="delete-actions">

                <button
                    type="button"
                    class="modal-secondary-button"
                    onclick="closeEditModal()">

                    Cancel

                </button>

                <button
                    type="submit"
                    class="modal-primary-button">

                    <i class="fa-solid fa-check"></i>
                    Save Changes

                </button>

            </div>

        </form>

    </div>

</div>

<!-- =====================================================
     DELETE USER MODAL
====================================================== -->
<div class="admin-modal" id="deleteUserModal">

    <div class="modal-card delete-modal-card">

        <button
            type="button"
            class="modal-close"
            onclick="closeDeleteModal()">

            <i class="fa-solid fa-xmark"></i>

        </button>

        <div class="modal-icon delete-icon">
            <i class="fa-solid fa-trash"></i>
        </div>

        <span class="modal-label danger-label">
            PERMANENT ACTION
        </span>

        <h2>
            Delete User?
        </h2>

        <p>
            This will permanently delete
            <strong id="deleteUserLabel">
                this account
            </strong>.
        </p>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars($csrfToken); ?>">

            <input
                type="hidden"
                name="action"
                value="delete_user">

            <input
                type="hidden"
                name="user_id"
                id="delete_user_id">

            <div class="delete-actions">

                <button
                    type="button"
                    class="modal-secondary-button"
                    onclick="closeDeleteModal()">

                    Cancel

                </button>

                <button
                    type="submit"
                    class="modal-danger-button">

                    <i class="fa-solid fa-trash"></i>
                    Delete User

                </button>

            </div>

        </form>

    </div>

</div>

<script>
const sidebar = document.getElementById("bookingSidebar");
const mobileButton = document.getElementById("mobileSidebarButton");

if (mobileButton && sidebar) {
    mobileButton.addEventListener("click", function () {
        sidebar.classList.toggle("mobile-open");
    });
}

function openAddModal() {
    const modal = document.getElementById("addUserModal");
    modal.classList.add("show");

    const field = document.getElementById("add_name");

    if (field) {
        setTimeout(() => field.focus(), 100);
    }
}

function closeAddModal() {
    document.getElementById("addUserModal").classList.remove("show");
}

function openEditModal(user) {

    document.getElementById("edit_user_id").value = user.id || "";
    document.getElementById("edit_name").value = user.name || "";
    document.getElementById("edit_email").value = user.email || "";
    document.getElementById("edit_phone").value = user.phone || "";
    document.getElementById("edit_location").value = user.location || "";
    document.getElementById("edit_role").value = user.role || "customer";
    document.getElementById("edit_bio").value = user.bio || "";
    document.getElementById("edit_password").value = "";

    document.getElementById("editUserModal").classList.add("show");
}

function closeEditModal() {
    document.getElementById("editUserModal").classList.remove("show");
}

function openDeleteModal(userId, userName) {

    document.getElementById("delete_user_id").value = userId;
    document.getElementById("deleteUserLabel").textContent = userName || "this account";

    document.getElementById("deleteUserModal").classList.add("show");
}

function closeDeleteModal() {
    document.getElementById("deleteUserModal").classList.remove("show");
}

document.addEventListener("click", function(event) {

    if (event.target.classList.contains("admin-modal")) {
        event.target.classList.remove("show");
    }

});

document.addEventListener("keydown", function(event) {

    if (event.key === "Escape") {
        closeAddModal();
        closeEditModal();
        closeDeleteModal();

        if (sidebar) {
            sidebar.classList.remove("mobile-open");
        }
    }

});
</script>

</body>
</html>

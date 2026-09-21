<?php

session_start();
require_once "../includes/db.php";

/* =========================================================
   ADMIN SECURITY
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
   ADMIN DATA
========================================================= */

$adminName = $_SESSION["name"] ?? "Administrator";
$adminLetter = strtoupper(substr($adminName, 0, 1));

/* =========================================================
   CSRF
========================================================= */

if (empty($_SESSION["admin_package_csrf"])) {
    $_SESSION["admin_package_csrf"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["admin_package_csrf"];

/* =========================================================
   MESSAGE
========================================================= */

$message = "";
$messageType = "";

/* =========================================================
   HELPERS
========================================================= */

function cleanValue($value): string
{
    return trim((string)$value);
}

/* =========================================================
   PACKAGE ACTIONS
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $postedToken = $_POST["csrf_token"] ?? "";

    if (
        empty($postedToken) ||
        !hash_equals($_SESSION["admin_package_csrf"], $postedToken)
    ) {
        $message = "Security verification failed. Please try again.";
        $messageType = "error";
    } else {

        $action = $_POST["action"] ?? "";

        /* -----------------------------------------------------
           ADD PACKAGE
        ----------------------------------------------------- */

        if ($action === "add_package") {

            $title = cleanValue($_POST["title"] ?? "");
            $description = cleanValue($_POST["description"] ?? "");
            $price = (float)($_POST["price"] ?? 0);
            $image = cleanValue($_POST["image"] ?? "");
            $destination = cleanValue($_POST["destination"] ?? "");
            $duration = (int)($_POST["duration"] ?? 0);

            if (
                $title === "" ||
                $description === "" ||
                $destination === "" ||
                $price <= 0 ||
                $duration <= 0
            ) {
                $message = "Please complete all required package fields.";
                $messageType = "error";
            } else {

                $stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO packages
                    (title, description, price, image, destination, duration)
                    VALUES (?, ?, ?, ?, ?, ?)"
                );

                if (!$stmt) {
                    $message = "Unable to prepare package creation.";
                    $messageType = "error";
                } else {

                    mysqli_stmt_bind_param(
                        $stmt,
                        "ssdssi",
                        $title,
                        $description,
                        $price,
                        $image,
                        $destination,
                        $duration
                    );

                    if (mysqli_stmt_execute($stmt)) {
                        $message = "Package added successfully.";
                        $messageType = "success";
                        $_SESSION["admin_package_csrf"] = bin2hex(random_bytes(32));
                        $csrfToken = $_SESSION["admin_package_csrf"];
                    } else {
                        $message = "Unable to add package.";
                        $messageType = "error";
                    }

                    mysqli_stmt_close($stmt);
                }
            }
        }

        /* -----------------------------------------------------
           EDIT PACKAGE
        ----------------------------------------------------- */

        elseif ($action === "edit_package") {

            $packageId = (int)($_POST["package_id"] ?? 0);
            $title = cleanValue($_POST["title"] ?? "");
            $description = cleanValue($_POST["description"] ?? "");
            $price = (float)($_POST["price"] ?? 0);
            $image = cleanValue($_POST["image"] ?? "");
            $destination = cleanValue($_POST["destination"] ?? "");
            $duration = (int)($_POST["duration"] ?? 0);

            if (
                $packageId <= 0 ||
                $title === "" ||
                $description === "" ||
                $destination === "" ||
                $price <= 0 ||
                $duration <= 0
            ) {
                $message = "Please complete all required package fields.";
                $messageType = "error";
            } else {

                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE packages
                     SET title = ?,
                         description = ?,
                         price = ?,
                         image = ?,
                         destination = ?,
                         duration = ?
                     WHERE id = ?"
                );

                if (!$stmt) {
                    $message = "Unable to prepare package update.";
                    $messageType = "error";
                } else {

                    mysqli_stmt_bind_param(
                        $stmt,
                        "ssdssii",
                        $title,
                        $description,
                        $price,
                        $image,
                        $destination,
                        $duration,
                        $packageId
                    );

                    if (mysqli_stmt_execute($stmt)) {
                        $message = "Package updated successfully.";
                        $messageType = "success";
                        $_SESSION["admin_package_csrf"] = bin2hex(random_bytes(32));
                        $csrfToken = $_SESSION["admin_package_csrf"];
                    } else {
                        $message = "Unable to update package.";
                        $messageType = "error";
                    }

                    mysqli_stmt_close($stmt);
                }
            }
        }

        /* -----------------------------------------------------
           DELETE PACKAGE
        ----------------------------------------------------- */

        elseif ($action === "delete_package") {

            $packageId = (int)($_POST["package_id"] ?? 0);

            if ($packageId <= 0) {

                $message = "Invalid package selected.";
                $messageType = "error";

            } else {

                /* Keep packages with booking records safe. */
                $checkStmt = mysqli_prepare(
                    $conn,
                    "SELECT COUNT(*) AS total
                     FROM booking
                     WHERE package_id = ?"
                );

                if (!$checkStmt) {

                    $message = "Unable to check package bookings.";
                    $messageType = "error";

                } else {

                    mysqli_stmt_bind_param(
                        $checkStmt,
                        "i",
                        $packageId
                    );

                    mysqli_stmt_execute($checkStmt);

                    $checkResult = mysqli_stmt_get_result($checkStmt);
                    $checkRow = mysqli_fetch_assoc($checkResult);

                    mysqli_stmt_close($checkStmt);

                    $bookingCount = (int)($checkRow["total"] ?? 0);

                    if ($bookingCount > 0) {

                        $message =
                            "This package has " .
                            $bookingCount .
                            " booking(s). Manage those bookings before deleting the package.";

                        $messageType = "error";

                    } else {

                        $deleteStmt = mysqli_prepare(
                            $conn,
                            "DELETE FROM packages WHERE id = ?"
                        );

                        if (!$deleteStmt) {

                            $message = "Unable to prepare package deletion.";
                            $messageType = "error";

                        } else {

                            mysqli_stmt_bind_param(
                                $deleteStmt,
                                "i",
                                $packageId
                            );

                            if (
                                mysqli_stmt_execute($deleteStmt) &&
                                mysqli_stmt_affected_rows($deleteStmt) > 0
                            ) {
                                $message = "Package deleted successfully.";
                                $messageType = "success";

                                $_SESSION["admin_package_csrf"] = bin2hex(random_bytes(32));
                                $csrfToken = $_SESSION["admin_package_csrf"];
                            } else {
                                $message = "Unable to delete package.";
                                $messageType = "error";
                            }

                            mysqli_stmt_close($deleteStmt);
                        }
                    }
                }
            }
        }
    }
}

/* =========================================================
   FILTERS
========================================================= */

$search = cleanValue($_GET["search"] ?? "");

/* =========================================================
   PACKAGE STATS
========================================================= */

$packageStats = [
    "total" => 0,
    "destinations" => 0,
    "average_price" => 0,
    "highest_price" => 0
];

$statsResult = mysqli_query(
    $conn,
    "SELECT
        COUNT(*) AS total,
        COUNT(DISTINCT destination) AS destinations,
        COALESCE(AVG(price), 0) AS average_price,
        COALESCE(MAX(price), 0) AS highest_price
     FROM packages"
);

if ($statsResult) {
    $statsRow = mysqli_fetch_assoc($statsResult);

    $packageStats["total"] = (int)($statsRow["total"] ?? 0);
    $packageStats["destinations"] = (int)($statsRow["destinations"] ?? 0);
    $packageStats["average_price"] = (float)($statsRow["average_price"] ?? 0);
    $packageStats["highest_price"] = (float)($statsRow["highest_price"] ?? 0);
}

/* =========================================================
   LOAD PACKAGES
========================================================= */

$packages = [];

if ($search !== "") {

    $like = "%" . $search . "%";

    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            id,
            title,
            description,
            price,
            image,
            destination,
            duration
         FROM packages
         WHERE title LIKE ?
            OR destination LIKE ?
            OR description LIKE ?
         ORDER BY id DESC"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "sss",
        $like,
        $like,
        $like
    );

} else {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            id,
            title,
            description,
            price,
            image,
            destination,
            duration
         FROM packages
         ORDER BY id DESC"
    );
}

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $packages[] = $row;
}

mysqli_stmt_close($stmt);

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Manage Packages | GlobeTrek
    </title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link
        rel="stylesheet"
        href="../css/manage-packages.css?v=1">

</head>

<body>

<div class="admin-package-app">

    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <aside class="package-sidebar">

        <div class="package-brand">

            <a href="../index.php">
                Globe<span>Trek</span>
            </a>

            <small>
                ADMIN PANEL
            </small>

        </div>

        <nav class="package-admin-nav">

            <span class="sidebar-section-title">
                MAIN MENU
            </span>

            <a href="adashboard.php">
                <i class="fa-solid fa-chart-pie"></i>
                Dashboard
            </a>

            <a href="manage-users.php">
                <i class="fa-solid fa-users"></i>
                Users
            </a>

            <a
                href="manage-packages.php"
                class="active">

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

        <div class="package-sidebar-bottom">

            <div class="package-admin-profile">

                <div class="package-admin-avatar">
                    <?php echo htmlspecialchars($adminLetter); ?>
                </div>

                <div>

                    <strong>
                        <?php echo htmlspecialchars($adminName); ?>
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

    <!-- =====================================================
         MAIN
    ====================================================== -->

    <main class="package-main">

        <header class="package-topbar">

            <button
                type="button"
                class="mobile-sidebar-button"
                id="mobileSidebarButton">

                <i class="fa-solid fa-bars"></i>

            </button>

            <div>

                <span class="topbar-eyebrow">
                    MANAGEMENT
                </span>

                <h1>
                    Packages
                </h1>

                <p>
                    Create and manage GlobeTrek travel packages.
                </p>

            </div>

            <div class="topbar-admin">

                <div class="topbar-avatar">
                    <?php echo htmlspecialchars($adminLetter); ?>
                </div>

                <div>

                    <strong>
                        <?php echo htmlspecialchars($adminName); ?>
                    </strong>

                    <small>
                        Administrator
                    </small>

                </div>

            </div>

        </header>

        <!-- =====================================================
             ALERT
        ====================================================== -->

        <?php if ($message !== ""): ?>

            <div
                class="package-alert <?php echo $messageType === "success" ? "success" : "error"; ?>">

                <i
                    class="fa-solid <?php echo $messageType === "success" ? "fa-circle-check" : "fa-circle-exclamation"; ?>">
                </i>

                <span>
                    <?php echo htmlspecialchars($message); ?>
                </span>

                <button
                    type="button"
                    onclick="this.parentElement.remove();">

                    <i class="fa-solid fa-xmark"></i>

                </button>

            </div>

        <?php endif; ?>

        <!-- =====================================================
             INTRO
        ====================================================== -->

        <section class="package-page-intro">

            <div>

                <span>
                    TRAVEL INVENTORY
                </span>

                <h2>
                    Package Management
                </h2>

                <p>
                    Manage destinations, pricing, duration,
                    descriptions and package images from one place.
                </p>

            </div>

            <button
                type="button"
                class="intro-add-button"
                onclick="openAddModal()">

                <i class="fa-solid fa-plus"></i>
                Add Package

            </button>

        </section>

        <!-- =====================================================
             STATS
        ====================================================== -->

        <section class="package-stat-grid">

            <div class="package-stat">

                <div class="package-stat-icon total">
                    <i class="fa-solid fa-suitcase-rolling"></i>
                </div>

                <div>

                    <span>
                        All Packages
                    </span>

                    <strong>
                        <?php echo $packageStats["total"]; ?>
                    </strong>

                </div>

            </div>

            <div class="package-stat">

                <div class="package-stat-icon destination">
                    <i class="fa-solid fa-location-dot"></i>
                </div>

                <div>

                    <span>
                        Destinations
                    </span>

                    <strong>
                        <?php echo $packageStats["destinations"]; ?>
                    </strong>

                </div>

            </div>

            <div class="package-stat">

                <div class="package-stat-icon average">
                    <i class="fa-solid fa-chart-line"></i>
                </div>

                <div>

                    <span>
                        Average Price
                    </span>

                    <strong class="price-value">
                        LKR <?php echo number_format($packageStats["average_price"], 0); ?>
                    </strong>

                </div>

            </div>

            <div class="package-stat">

                <div class="package-stat-icon highest">
                    <i class="fa-solid fa-arrow-up"></i>
                </div>

                <div>

                    <span>
                        Highest Package
                    </span>

                    <strong class="price-value">
                        LKR <?php echo number_format($packageStats["highest_price"], 0); ?>
                    </strong>

                </div>

            </div>

        </section>

        <!-- =====================================================
             SEARCH
        ====================================================== -->

        <section class="package-filter-card">

            <form
                method="GET"
                class="package-filter-form">

                <div class="filter-search">

                    <i class="fa-solid fa-magnifying-glass"></i>

                    <input
                        type="text"
                        name="search"
                        value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Search package, destination or description...">

                </div>

                <button
                    type="submit"
                    class="filter-button">

                    <i class="fa-solid fa-filter"></i>
                    Search

                </button>

                <?php if ($search !== ""): ?>

                    <a
                        href="manage-packages.php"
                        class="clear-filter">

                        Clear

                    </a>

                <?php endif; ?>

            </form>

        </section>

        <!-- =====================================================
             TABLE
        ====================================================== -->

        <section class="package-table-panel">

            <div class="table-panel-header">

                <div>

                    <span>
                        TRAVEL INVENTORY
                    </span>

                    <h2>
                        Available Packages
                    </h2>

                </div>

                <div class="result-count">

                    <?php echo count($packages); ?>

                    result(s)

                </div>

            </div>

            <?php if (!empty($packages)): ?>

                <div class="table-scroll">

                    <table class="package-table">

                        <thead>

                            <tr>

                                <th>
                                    Package
                                </th>

                                <th>
                                    Destination
                                </th>

                                <th>
                                    Duration
                                </th>

                                <th>
                                    Price
                                </th>

                                <th>
                                    Image
                                </th>

                                <th>
                                    Action
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($packages as $package): ?>

                            <?php

                            $packageId = (int)$package["id"];
                            $packageTitle = $package["title"] ?? "Untitled Package";
                            $packageDescription = $package["description"] ?? "";
                            $packagePrice = (float)($package["price"] ?? 0);
                            $packageImage = $package["image"] ?? "";
                            $packageDestination = $package["destination"] ?? "Sri Lanka";
                            $packageDuration = (int)($package["duration"] ?? 0);

                            $packageLetter = strtoupper(
                                substr($packageTitle, 0, 1)
                            );

                            ?>

                            <tr>

                                <td>

                                    <div class="package-name-cell">

                                        <div class="package-mini-icon">
                                            <i class="fa-solid fa-route"></i>
                                        </div>

                                        <div>

                                            <strong>
                                                <?php echo htmlspecialchars($packageTitle); ?>
                                            </strong>

                                            <small>
                                                ID #<?php echo $packageId; ?>
                                            </small>

                                        </div>

                                    </div>

                                </td>

                                <td>

                                    <div class="destination-cell">

                                        <i class="fa-solid fa-location-dot"></i>

                                        <span>
                                            <?php echo htmlspecialchars($packageDestination); ?>
                                        </span>

                                    </div>

                                </td>

                                <td>

                                    <span class="duration-pill">

                                        <i class="fa-regular fa-clock"></i>

                                        <?php echo $packageDuration; ?>

                                        <?php echo $packageDuration === 1 ? "day" : "days"; ?>

                                    </span>

                                </td>

                                <td>

                                    <strong class="price-cell">

                                        LKR
                                        <?php echo number_format($packagePrice, 2); ?>

                                    </strong>

                                </td>

                                <td>

                                    <?php if ($packageImage !== ""): ?>

                                        <img
                                            class="package-thumb"
                                            src="../images/<?php echo htmlspecialchars($packageImage); ?>"
                                            alt="<?php echo htmlspecialchars($packageTitle); ?>"
                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">

                                        <span
                                            class="image-status fallback-image"
                                            style="display:none;">

                                            <i class="fa-regular fa-image"></i>

                                            Missing

                                        </span>

                                    <?php else: ?>

                                        <span class="image-status">

                                            <i class="fa-regular fa-image"></i>

                                            No image

                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <div class="package-actions">

                                        <button
                                            type="button"
                                            class="action-button update"
                                            onclick='openEditModal(<?php echo json_encode([
                                                "id" => $packageId,
                                                "title" => $packageTitle,
                                                "description" => $packageDescription,
                                                "price" => $packagePrice,
                                                "image" => $packageImage,
                                                "destination" => $packageDestination,
                                                "duration" => $packageDuration
                                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>)'>

                                            <i class="fa-solid fa-pen"></i>
                                            Edit

                                        </button>

                                        <button
                                            type="button"
                                            class="action-button delete"
                                            onclick="openDeleteModal(
                                                <?php echo $packageId; ?>,
                                                <?php echo json_encode($packageTitle); ?>
                                            )">

                                            <i class="fa-solid fa-trash"></i>

                                        </button>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-packages">

                    <div class="empty-icon">
                        <i class="fa-solid fa-map-location-dot"></i>
                    </div>

                    <h3>
                        No packages found
                    </h3>

                    <p>
                        There are no packages matching your current search.
                    </p>

                    <button
                        type="button"
                        class="empty-button"
                        onclick="openAddModal()">

                        <i class="fa-solid fa-plus"></i>
                        Create Package

                    </button>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>

<!-- =========================================================
     ADD PACKAGE MODAL
========================================================= -->

<div
    class="admin-modal"
    id="addModal">

    <div
        class="modal-overlay"
        onclick="closeAddModal()">
    </div>

    <div class="modal-card">

        <button
            type="button"
            class="modal-close"
            onclick="closeAddModal()">

            <i class="fa-solid fa-xmark"></i>

        </button>

        <div class="modal-icon add-icon">
            <i class="fa-solid fa-plus"></i>
        </div>

        <span class="modal-label">
            TRAVEL INVENTORY
        </span>

        <h2>
            Add Package
        </h2>

        <p>
            Create a new travel package for GlobeTrek customers.
        </p>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars($csrfToken); ?>">

            <input
                type="hidden"
                name="action"
                value="add_package">

            <div class="form-grid">

                <div class="form-field full">
                    <label for="addTitle">
                        Package Title
                    </label>

                    <input
                        type="text"
                        id="addTitle"
                        name="title"
                        maxlength="150"
                        placeholder="e.g. Kandy Cultural Escape"
                        required>
                </div>

                <div class="form-field">

                    <label for="addDestination">
                        Destination
                    </label>

                    <input
                        type="text"
                        id="addDestination"
                        name="destination"
                        maxlength="100"
                        placeholder="e.g. Kandy"
                        required>

                </div>

                <div class="form-field">

                    <label for="addDuration">
                        Duration
                    </label>

                    <input
                        type="number"
                        id="addDuration"
                        name="duration"
                        min="1"
                        max="365"
                        placeholder="Days"
                        required>

                </div>

                <div class="form-field">

                    <label for="addPrice">
                        Price
                    </label>

                    <input
                        type="number"
                        id="addPrice"
                        name="price"
                        min="0.01"
                        step="0.01"
                        placeholder="25000.00"
                        required>

                </div>

                <div class="form-field">

                    <label for="addImage">
                        Image File Name
                    </label>

                    <input
                        type="text"
                        id="addImage"
                        name="image"
                        maxlength="255"
                        placeholder="kandy.jpg">

                </div>

                <div class="form-field full">

                    <label for="addDescription">
                        Description
                    </label>

                    <textarea
                        id="addDescription"
                        name="description"
                        rows="5"
                        maxlength="1000"
                        placeholder="Describe the travel experience..."
                        required></textarea>

                </div>

            </div>

            <div class="modal-actions">

                <button
                    type="button"
                    class="secondary-button"
                    onclick="closeAddModal()">

                    Cancel

                </button>

                <button
                    type="submit"
                    class="modal-primary-button">

                    <i class="fa-solid fa-check"></i>
                    Create Package

                </button>

            </div>

        </form>

    </div>

</div>

<!-- =========================================================
     EDIT PACKAGE MODAL
========================================================= -->

<div
    class="admin-modal"
    id="editModal">

    <div
        class="modal-overlay"
        onclick="closeEditModal()">
    </div>

    <div class="modal-card">

        <button
            type="button"
            class="modal-close"
            onclick="closeEditModal()">

            <i class="fa-solid fa-xmark"></i>

        </button>

        <div class="modal-icon update-icon">
            <i class="fa-solid fa-pen-to-square"></i>
        </div>

        <span class="modal-label">
            PACKAGE MANAGEMENT
        </span>

        <h2>
            Edit Package
        </h2>

        <p>
            Update the selected travel package details.
        </p>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars($csrfToken); ?>">

            <input
                type="hidden"
                name="action"
                value="edit_package">

            <input
                type="hidden"
                name="package_id"
                id="editId">

            <div class="form-grid">

                <div class="form-field full">

                    <label for="editTitle">
                        Package Title
                    </label>

                    <input
                        type="text"
                        id="editTitle"
                        name="title"
                        maxlength="150"
                        required>

                </div>

                <div class="form-field">

                    <label for="editDestination">
                        Destination
                    </label>

                    <input
                        type="text"
                        id="editDestination"
                        name="destination"
                        maxlength="100"
                        required>

                </div>

                <div class="form-field">

                    <label for="editDuration">
                        Duration
                    </label>

                    <input
                        type="number"
                        id="editDuration"
                        name="duration"
                        min="1"
                        max="365"
                        required>

                </div>

                <div class="form-field">

                    <label for="editPrice">
                        Price
                    </label>

                    <input
                        type="number"
                        id="editPrice"
                        name="price"
                        min="0.01"
                        step="0.01"
                        required>

                </div>

                <div class="form-field">

                    <label for="editImage">
                        Image File Name
                    </label>

                    <input
                        type="text"
                        id="editImage"
                        name="image"
                        maxlength="255">

                </div>

                <div class="form-field full">

                    <label for="editDescription">
                        Description
                    </label>

                    <textarea
                        id="editDescription"
                        name="description"
                        rows="5"
                        maxlength="1000"
                        required></textarea>

                </div>

            </div>

            <div class="modal-actions">

                <button
                    type="button"
                    class="secondary-button"
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

<!-- =========================================================
     DELETE MODAL
========================================================= -->

<div
    class="admin-modal"
    id="deleteModal">

    <div
        class="modal-overlay"
        onclick="closeDeleteModal()">
    </div>

    <div class="modal-card delete-card">

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
            Delete Package?
        </h2>

        <p>
            This will permanently delete
            <strong id="deletePackageLabel">
                this package
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
                value="delete_package">

            <input
                type="hidden"
                name="package_id"
                id="deletePackageId">

            <div class="delete-actions">

                <button
                    type="button"
                    class="secondary-button"
                    onclick="closeDeleteModal()">

                    Cancel

                </button>

                <button
                    type="submit"
                    class="danger-button">

                    <i class="fa-solid fa-trash"></i>
                    Delete Package

                </button>

            </div>

        </form>

    </div>

</div>

<script>
function openAddModal() {

    const modal = document.getElementById("addModal");

    modal.classList.add("show");
}

function closeAddModal() {

    const modal = document.getElementById("addModal");

    modal.classList.remove("show");
}

function openEditModal(packageData) {

    document.getElementById("editId").value =
        packageData.id || "";

    document.getElementById("editTitle").value =
        packageData.title || "";

    document.getElementById("editDestination").value =
        packageData.destination || "";

    document.getElementById("editDuration").value =
        packageData.duration || "";

    document.getElementById("editPrice").value =
        packageData.price || "";

    document.getElementById("editImage").value =
        packageData.image || "";

    document.getElementById("editDescription").value =
        packageData.description || "";

    document.getElementById("editModal").classList.add("show");
}

function closeEditModal() {

    document.getElementById("editModal")
        .classList.remove("show");
}

function openDeleteModal(id, title) {

    document.getElementById("deletePackageId").value =
        id;

    document.getElementById("deletePackageLabel").textContent =
        title || "this package";

    document.getElementById("deleteModal")
        .classList.add("show");
}

function closeDeleteModal() {

    document.getElementById("deleteModal")
        .classList.remove("show");
}

document.addEventListener("keydown", function(event) {

    if (event.key === "Escape") {

        closeAddModal();
        closeEditModal();
        closeDeleteModal();

    }

});

const mobileButton =
    document.getElementById("mobileSidebarButton");

if (mobileButton) {

    mobileButton.addEventListener("click", function() {

        document
            .querySelector(".package-sidebar")
            .classList.toggle("open");

    });

}
</script>

</body>
</html>

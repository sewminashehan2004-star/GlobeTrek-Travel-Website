<?php
session_start();
require_once "../includes/db.php";

/* STAFF ACCESS */
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "staff"
) {
    header("Location: ../login.php");
    exit();
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$staffName = $_SESSION["name"] ?? $_SESSION["user_name"] ?? "Staff Member";
$staffInitial = strtoupper(substr(trim($staffName), 0, 1));
if ($staffInitial === "") $staffInitial = "S";

if (empty($_SESSION["staff_package_csrf"])) {
    $_SESSION["staff_package_csrf"] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION["staff_package_csrf"];

$message = "";
$messageType = "success";

/* PACKAGE ACTIONS */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $postedToken = $_POST["csrf_token"] ?? "";

    if (!hash_equals($csrfToken, $postedToken)) {
        $message = "Security verification failed. Please try again.";
        $messageType = "error";
    } else {

        $action = $_POST["action"] ?? "";

        if ($action === "add_package" || $action === "edit_package") {

            $packageId = (int)($_POST["package_id"] ?? 0);
            $title = trim((string)($_POST["title"] ?? ""));
            $description = trim((string)($_POST["description"] ?? ""));
            $price = (float)($_POST["price"] ?? 0);
            $image = trim((string)($_POST["image"] ?? ""));
            $destination = trim((string)($_POST["destination"] ?? ""));
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
            } elseif ($action === "add_package") {

                $stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO packages
                    (title, description, price, image, destination, duration)
                    VALUES (?, ?, ?, ?, ?, ?)"
                );

                if ($stmt) {
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
                        $_SESSION["staff_package_csrf"] = bin2hex(random_bytes(32));
                        $csrfToken = $_SESSION["staff_package_csrf"];
                    } else {
                        $message = "Unable to add package.";
                        $messageType = "error";
                    }

                    mysqli_stmt_close($stmt);
                } else {
                    $message = "Unable to prepare package creation.";
                    $messageType = "error";
                }

            } else {

                if ($packageId <= 0) {
                    $message = "Invalid package selected.";
                    $messageType = "error";
                } else {

                    $stmt = mysqli_prepare(
                        $conn,
                        "UPDATE packages
                         SET title = ?, description = ?, price = ?,
                             image = ?, destination = ?, duration = ?
                         WHERE id = ?"
                    );

                    if ($stmt) {
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
                            $_SESSION["staff_package_csrf"] = bin2hex(random_bytes(32));
                            $csrfToken = $_SESSION["staff_package_csrf"];
                        } else {
                            $message = "Unable to update package.";
                            $messageType = "error";
                        }

                        mysqli_stmt_close($stmt);
                    } else {
                        $message = "Unable to prepare package update.";
                        $messageType = "error";
                    }
                }
            }

        } elseif ($action === "delete_package") {

            $packageId = (int)($_POST["package_id"] ?? 0);

            if ($packageId <= 0) {
                $message = "Invalid package selected.";
                $messageType = "error";
            } else {

                $check = mysqli_prepare(
                    $conn,
                    "SELECT COUNT(*) AS total FROM booking WHERE package_id = ?"
                );

                if (!$check) {
                    $message = "Unable to check package bookings.";
                    $messageType = "error";
                } else {

                    mysqli_stmt_bind_param($check, "i", $packageId);
                    mysqli_stmt_execute($check);

                    $checkResult = mysqli_stmt_get_result($check);
                    $checkRow = mysqli_fetch_assoc($checkResult);
                    mysqli_stmt_close($check);

                    $bookingCount = (int)($checkRow["total"] ?? 0);

                    if ($bookingCount > 0) {

                        $message =
                            "This package has " .
                            $bookingCount .
                            " booking(s). You cannot delete a package that is linked to bookings.";
                        $messageType = "error";

                    } else {

                        $delete = mysqli_prepare(
                            $conn,
                            "DELETE FROM packages WHERE id = ?"
                        );

                        if ($delete) {

                            mysqli_stmt_bind_param($delete, "i", $packageId);

                            if (
                                mysqli_stmt_execute($delete) &&
                                mysqli_stmt_affected_rows($delete) > 0
                            ) {
                                $message = "Package deleted successfully.";
                                $_SESSION["staff_package_csrf"] = bin2hex(random_bytes(32));
                                $csrfToken = $_SESSION["staff_package_csrf"];
                            } else {
                                $message = "Unable to delete package.";
                                $messageType = "error";
                            }

                            mysqli_stmt_close($delete);

                        } else {
                            $message = "Unable to prepare package deletion.";
                            $messageType = "error";
                        }
                    }
                }
            }
        }
    }
}

/* SEARCH */
$search = trim((string)($_GET["search"] ?? ""));

/* STATS */
$stats = [
    "total" => 0,
    "destinations" => 0,
    "average" => 0,
    "highest" => 0
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
    $row = mysqli_fetch_assoc($statsResult);
    $stats["total"] = (int)($row["total"] ?? 0);
    $stats["destinations"] = (int)($row["destinations"] ?? 0);
    $stats["average"] = (float)($row["average_price"] ?? 0);
    $stats["highest"] = (float)($row["highest_price"] ?? 0);
}

/* PACKAGES */
$packages = [];

if ($search !== "") {

    $like = "%" . $search . "%";

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, title, description, price, image, destination, duration
         FROM packages
         WHERE title LIKE ?
            OR destination LIKE ?
            OR description LIKE ?
         ORDER BY id DESC"
    );

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sss", $like, $like, $like);
    }

} else {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, title, description, price, image, destination, duration
         FROM packages
         ORDER BY id DESC"
    );
}

if ($stmt) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $packages[] = $row;
    }

    mysqli_stmt_close($stmt);
}

$currentDate = date("l, d F Y");
$currentTime = date("h:i A");

?>
<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Packages | GlobeTrek Staff</title>

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

    <link
        rel="stylesheet"
        href="/globetrak/css/staff-packages.css?v=1000"
    >

</head>

<body>

<div class="staff-package-app">

    <aside class="staff-package-sidebar" id="staffPackageSidebar">

        <div class="staff-package-brand">

            <a href="sdashboard.php">
                Globe<span>Trek</span>
            </a>

            <small>STAFF CONTROL PANEL</small>

        </div>

        <nav class="staff-package-nav">

            <span class="nav-section-title">MAIN MENU</span>

            <a href="sdashboard.php">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Dashboard</span>
            </a>

            <a href="manage-packages.php" class="active">
                <i class="fa-solid fa-map-location-dot"></i>
                <span>Packages</span>
            </a>

            <a href="manage-bookings.php">
                <i class="fa-solid fa-calendar-check"></i>
                <span>Bookings</span>
            </a>

            <a href="manage-payment.php">
                <i class="fa-solid fa-credit-card"></i>
                <span>Payments</span>
            </a>

            <span class="nav-section-title second">QUICK ACCESS</span>

            <a href="../index.php" target="_blank" rel="noopener">
                <i class="fa-solid fa-globe"></i>
                <span>View Website</span>
            </a>

        </nav>

        <div class="staff-package-sidebar-bottom">

            <div class="staff-package-profile">

                <div class="profile-avatar">
                    <?php echo h($staffInitial); ?>
                </div>

                <div>
                    <strong><?php echo h($staffName); ?></strong>
                    <span>Travel Staff</span>
                </div>

            </div>

            <a href="../logout.php" class="staff-package-logout">
                <i class="fa-solid fa-right-from-bracket"></i>
                Logout
            </a>

        </div>

    </aside>

    <main class="staff-package-main">

        <header class="staff-package-topbar">

            <div class="topbar-left">

                <button
                    type="button"
                    class="mobile-menu-button"
                    id="mobilePackageMenu"
                    aria-label="Open menu"
                >
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div>

                    <span class="topbar-eyebrow">STAFF MANAGEMENT</span>

                    <h1>Manage Packages</h1>

                    <p>
                        Create, update and maintain GlobeTrek travel packages.
                    </p>

                </div>

            </div>

            <div class="topbar-right">

                <div class="topbar-date">

                    <i class="fa-regular fa-calendar"></i>

                    <div>
                        <strong><?php echo h($currentDate); ?></strong>
                        <span><?php echo h($currentTime); ?></span>
                    </div>

                </div>

                <div class="topbar-user">

                    <div class="topbar-user-avatar">
                        <?php echo h($staffInitial); ?>
                    </div>

                    <div>
                        <strong><?php echo h($staffName); ?></strong>
                        <span>Staff Member</span>
                    </div>

                </div>

            </div>

        </header>

        <section class="staff-package-content">

            <section class="page-intro">

                <div>

                    <span class="intro-kicker">
                        <i class="fa-solid fa-map-location-dot"></i>
                        TRAVEL INVENTORY
                    </span>

                    <h2>Package Management</h2>

                    <p>
                        Add new destinations, update package information and keep the
                        customer travel catalogue ready for bookings.
                    </p>

                </div>

                <button
                    type="button"
                    class="primary-action"
                    onclick="openModal('addPackageModal')"
                >
                    <i class="fa-solid fa-plus"></i>
                    Add Package
                </button>

            </section>

            <?php if ($message !== ""): ?>

                <div class="staff-alert <?php echo $messageType === "success" ? "success" : "error"; ?>">

                    <div class="alert-icon">
                        <i class="fa-solid <?php echo $messageType === "success"
                            ? "fa-circle-check"
                            : "fa-circle-exclamation"; ?>"></i>
                    </div>

                    <div>
                        <strong>
                            <?php echo $messageType === "success" ? "Success" : "Attention"; ?>
                        </strong>

                        <p><?php echo h($message); ?></p>
                    </div>

                    <button
                        type="button"
                        onclick="this.parentElement.remove()"
                        aria-label="Close"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>

                </div>

            <?php endif; ?>

            <section class="package-stat-grid">

                <div class="package-stat-card">

                    <div class="stat-icon orange">
                        <i class="fa-solid fa-suitcase-rolling"></i>
                    </div>

                    <div>
                        <span>Total Packages</span>
                        <strong><?php echo number_format($stats["total"]); ?></strong>
                        <small>Travel products</small>
                    </div>

                </div>

                <div class="package-stat-card">

                    <div class="stat-icon blue">
                        <i class="fa-solid fa-location-dot"></i>
                    </div>

                    <div>
                        <span>Destinations</span>
                        <strong><?php echo number_format($stats["destinations"]); ?></strong>
                        <small>Unique destinations</small>
                    </div>

                </div>

                <div class="package-stat-card">

                    <div class="stat-icon green">
                        <i class="fa-solid fa-tags"></i>
                    </div>

                    <div>
                        <span>Average Price</span>
                        <strong>LKR <?php echo number_format($stats["average"], 0); ?></strong>
                        <small>Across packages</small>
                    </div>

                </div>

                <div class="package-stat-card">

                    <div class="stat-icon purple">
                        <i class="fa-solid fa-arrow-up-right-dots"></i>
                    </div>

                    <div>
                        <span>Highest Price</span>
                        <strong>LKR <?php echo number_format($stats["highest"], 0); ?></strong>
                        <small>Current catalogue</small>
                    </div>

                </div>

            </section>

            <section class="filter-card">

                <div class="filter-heading">

                    <div>
                        <span>PACKAGE DIRECTORY</span>
                        <h3>Find Packages</h3>
                    </div>

                    <b>
                        <?php echo number_format(count($packages)); ?> shown
                    </b>

                </div>

                <form method="GET" class="filter-form">

                    <div class="search-box">

                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="text"
                            name="search"
                            value="<?php echo h($search); ?>"
                            placeholder="Search package, destination or description..."
                        >

                    </div>

                    <button type="submit" class="filter-button">
                        <i class="fa-solid fa-filter"></i>
                        Search
                    </button>

                    <?php if ($search !== ""): ?>

                        <a href="manage-packages.php" class="reset-button">
                            <i class="fa-solid fa-rotate-left"></i>
                            Reset
                        </a>

                    <?php endif; ?>

                </form>

            </section>

            <section class="package-panel">

                <div class="panel-header">

                    <div>
                        <span>TRAVEL INVENTORY</span>
                        <h3>Available Packages</h3>
                    </div>

                    <button
                        type="button"
                        class="small-add-button"
                        onclick="openModal('addPackageModal')"
                    >
                        <i class="fa-solid fa-plus"></i>
                        Add New
                    </button>

                </div>

                <?php if (count($packages) > 0): ?>

                    <div class="package-grid">

                        <?php foreach ($packages as $package): ?>

                            <?php
                            $packageId = (int)$package["id"];
                            $title = (string)($package["title"] ?? "");
                            $description = (string)($package["description"] ?? "");
                            $image = trim((string)($package["image"] ?? ""));
                            $destination = (string)($package["destination"] ?? "");
                            $duration = (int)($package["duration"] ?? 0);
                            $price = (float)($package["price"] ?? 0);
                            ?>

                            <article class="package-card">

                                <div class="package-image">

                                    <?php if ($image !== ""): ?>

                                        <img
                                            src="<?php echo h($image); ?>"
                                            alt="<?php echo h($title); ?>"
                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                        >

                                        <div class="image-fallback" style="display:none;">
                                            <i class="fa-solid fa-mountain-sun"></i>
                                        </div>

                                    <?php else: ?>

                                        <div class="image-fallback">
                                            <i class="fa-solid fa-mountain-sun"></i>
                                        </div>

                                    <?php endif; ?>

                                    <span class="package-number">
                                        #<?php echo $packageId; ?>
                                    </span>

                                    <span class="duration-badge">
                                        <i class="fa-regular fa-clock"></i>
                                        <?php echo $duration; ?> day<?php echo $duration === 1 ? "" : "s"; ?>
                                    </span>

                                </div>

                                <div class="package-body">

                                    <div class="destination">
                                        <i class="fa-solid fa-location-dot"></i>
                                        <?php echo h($destination); ?>
                                    </div>

                                    <h4>
                                        <?php echo h($title); ?>
                                    </h4>

                                    <p>
                                        <?php echo h($description); ?>
                                    </p>

                                    <div class="package-bottom">

                                        <div>
                                            <span>FROM</span>

                                            <strong>
                                                LKR <?php echo number_format($price, 2); ?>
                                            </strong>
                                        </div>

                                        <div class="package-actions">

                                            <button
                                                type="button"
                                                class="icon-button edit"
                                                title="Edit package"
                                                onclick='openEditModal(
                                                    <?php echo htmlspecialchars(
                                                        json_encode(
                                                            [
                                                                "id" => $packageId,
                                                                "title" => $title,
                                                                "description" => $description,
                                                                "price" => $price,
                                                                "image" => $image,
                                                                "destination" => $destination,
                                                                "duration" => $duration
                                                            ],
                                                            JSON_HEX_TAG |
                                                            JSON_HEX_APOS |
                                                            JSON_HEX_QUOT |
                                                            JSON_HEX_AMP
                                                        ),
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ); ?>
                                                )'
                                            >
                                                <i class="fa-solid fa-pen"></i>
                                            </button>

                                            <button
                                                type="button"
                                                class="icon-button delete"
                                                title="Delete package"
                                                onclick='openDeleteModal(
                                                    <?php echo $packageId; ?>,
                                                    <?php echo htmlspecialchars(
                                                        json_encode($title, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ); ?>
                                                )'
                                            >
                                                <i class="fa-solid fa-trash"></i>
                                            </button>

                                        </div>

                                    </div>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="empty-packages">

                        <div class="empty-icon">
                            <i class="fa-solid fa-map-location-dot"></i>
                        </div>

                        <h4>No packages found</h4>

                        <p>
                            Try another search or create a new travel package.
                        </p>

                        <button
                            type="button"
                            class="primary-action"
                            onclick="openModal('addPackageModal')"
                        >
                            <i class="fa-solid fa-plus"></i>
                            Create Package
                        </button>

                    </div>

                <?php endif; ?>

            </section>

        </section>

    </main>

</div>

<!-- ADD MODAL -->
<div class="staff-modal" id="addPackageModal">

    <div class="modal-overlay" onclick="closeModal('addPackageModal')"></div>

    <div class="modal-card">

        <button
            type="button"
            class="modal-close"
            onclick="closeModal('addPackageModal')"
        >
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="modal-icon orange">
            <i class="fa-solid fa-plus"></i>
        </div>

        <span class="modal-kicker">TRAVEL INVENTORY</span>

        <h3>Add Package</h3>

        <p>
            Create a new package for GlobeTrek customers.
        </p>

        <form method="POST">

            <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
            <input type="hidden" name="action" value="add_package">

            <div class="form-grid">

                <div class="form-field full">
                    <label>Package Title</label>
                    <input
                        type="text"
                        name="title"
                        maxlength="150"
                        placeholder="e.g. Kandy Cultural Escape"
                        required
                    >
                </div>

                <div class="form-field">
                    <label>Destination</label>
                    <input
                        type="text"
                        name="destination"
                        maxlength="100"
                        placeholder="e.g. Kandy"
                        required
                    >
                </div>

                <div class="form-field">
                    <label>Duration (Days)</label>
                    <input
                        type="number"
                        name="duration"
                        min="1"
                        max="365"
                        placeholder="3"
                        required
                    >
                </div>

                <div class="form-field">
                    <label>Price (LKR)</label>
                    <input
                        type="number"
                        name="price"
                        min="0.01"
                        step="0.01"
                        placeholder="45000"
                        required
                    >
                </div>

                <div class="form-field">
                    <label>Image URL</label>
                    <input
                        type="url"
                        name="image"
                        placeholder="https://..."
                    >
                </div>

                <div class="form-field full">
                    <label>Description</label>
                    <textarea
                        name="description"
                        rows="4"
                        maxlength="1000"
                        placeholder="Describe the travel package..."
                        required
                    ></textarea>
                </div>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="secondary-button"
                    onclick="closeModal('addPackageModal')"
                >
                    Cancel
                </button>

                <button type="submit" class="primary-button">
                    <i class="fa-solid fa-plus"></i>
                    Add Package
                </button>

            </div>

        </form>

    </div>

</div>

<!-- EDIT MODAL -->
<div class="staff-modal" id="editPackageModal">

    <div class="modal-overlay" onclick="closeModal('editPackageModal')"></div>

    <div class="modal-card">

        <button
            type="button"
            class="modal-close"
            onclick="closeModal('editPackageModal')"
        >
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="modal-icon blue">
            <i class="fa-solid fa-pen"></i>
        </div>

        <span class="modal-kicker">TRAVEL INVENTORY</span>

        <h3>Edit Package</h3>

        <p>
            Update package information and pricing.
        </p>

        <form method="POST">

            <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
            <input type="hidden" name="action" value="edit_package">
            <input type="hidden" name="package_id" id="editPackageId">

            <div class="form-grid">

                <div class="form-field full">
                    <label>Package Title</label>
                    <input type="text" name="title" id="editTitle" maxlength="150" required>
                </div>

                <div class="form-field">
                    <label>Destination</label>
                    <input type="text" name="destination" id="editDestination" maxlength="100" required>
                </div>

                <div class="form-field">
                    <label>Duration (Days)</label>
                    <input type="number" name="duration" id="editDuration" min="1" max="365" required>
                </div>

                <div class="form-field">
                    <label>Price (LKR)</label>
                    <input type="number" name="price" id="editPrice" min="0.01" step="0.01" required>
                </div>

                <div class="form-field">
                    <label>Image URL</label>
                    <input type="url" name="image" id="editImage">
                </div>

                <div class="form-field full">
                    <label>Description</label>
                    <textarea name="description" id="editDescription" rows="4" maxlength="1000" required></textarea>
                </div>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="secondary-button"
                    onclick="closeModal('editPackageModal')"
                >
                    Cancel
                </button>

                <button type="submit" class="primary-button">
                    <i class="fa-solid fa-floppy-disk"></i>
                    Save Changes
                </button>

            </div>

        </form>

    </div>

</div>

<!-- DELETE MODAL -->
<div class="staff-modal" id="deletePackageModal">

    <div class="modal-overlay" onclick="closeModal('deletePackageModal')"></div>

    <div class="modal-card delete-modal">

        <button
            type="button"
            class="modal-close"
            onclick="closeModal('deletePackageModal')"
        >
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="modal-icon red">
            <i class="fa-solid fa-trash"></i>
        </div>

        <span class="modal-kicker">PACKAGE MANAGEMENT</span>

        <h3>Delete Package?</h3>

        <p>
            You are about to delete
            <strong id="deletePackageName">this package</strong>.
            Packages connected to existing bookings cannot be deleted.
        </p>

        <form method="POST">

            <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
            <input type="hidden" name="action" value="delete_package">
            <input type="hidden" name="package_id" id="deletePackageId">

            <div class="modal-footer">

                <button
                    type="button"
                    class="secondary-button"
                    onclick="closeModal('deletePackageModal')"
                >
                    Cancel
                </button>

                <button type="submit" class="danger-button">
                    <i class="fa-solid fa-trash"></i>
                    Delete Package
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
    }

    if (!document.querySelector(".staff-modal.show")) {
        document.body.classList.remove("modal-open");
    }
}

function openEditModal(data) {

    document.getElementById("editPackageId").value = data.id || "";
    document.getElementById("editTitle").value = data.title || "";
    document.getElementById("editDescription").value = data.description || "";
    document.getElementById("editPrice").value = data.price || "";
    document.getElementById("editImage").value = data.image || "";
    document.getElementById("editDestination").value = data.destination || "";
    document.getElementById("editDuration").value = data.duration || "";

    openModal("editPackageModal");
}

function openDeleteModal(id, title) {

    document.getElementById("deletePackageId").value = id || "";
    document.getElementById("deletePackageName").textContent =
        title ? '"' + title + '"' : "this package";

    openModal("deletePackageModal");
}

document.addEventListener("keydown", function(event) {

    if (event.key === "Escape") {

        document.querySelectorAll(".staff-modal.show").forEach(function(modal) {
            modal.classList.remove("show");
        });

        document.body.classList.remove("modal-open");
    }

});

const menuButton = document.getElementById("mobilePackageMenu");
const sidebar = document.getElementById("staffPackageSidebar");

if (menuButton && sidebar) {

    menuButton.addEventListener("click", function() {
        sidebar.classList.toggle("open");
    });

    document.addEventListener("click", function(event) {

        if (window.innerWidth <= 900) {

            if (
                !sidebar.contains(event.target) &&
                !menuButton.contains(event.target)
            ) {
                sidebar.classList.remove("open");
            }

        }

    });
}
</script>

</body>
</html>

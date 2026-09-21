<?php
/*
    GlobeTrek - Staff Dashboard
    File: staff/sdashboard.php
*/

session_start();
require_once "../includes/db.php";

/* =========================================================
   STAFF SECURITY
========================================================= */
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"]) || $_SESSION["role"] !== "staff") {
    header("Location: ../login.php");
    exit();
}

/* =========================================================
   HELPERS
========================================================= */
function esc($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function fetchSingleValue(mysqli $conn, string $sql, string $field, $default = 0)
{
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        return $default;
    }

    $row = mysqli_fetch_assoc($result);

    return isset($row[$field]) && $row[$field] !== null
        ? $row[$field]
        : $default;
}

function statusClass(string $status): string
{
    $status = strtolower(trim($status));

    switch ($status) {
        case "paid":
            return "paid";
        case "confirmed":
            return "confirmed";
        case "completed":
            return "completed";
        case "cancelled":
        case "canceled":
            return "cancelled";
        case "pending":
        default:
            return "pending";
    }
}

function formatBookingDate($travelDate, $bookingDate): string
{
    if (!empty($travelDate) && $travelDate !== "0000-00-00") {
        $time = strtotime($travelDate);

        if ($time !== false) {
            return date("d M Y", $time);
        }

        return (string)$travelDate;
    }

    if ($bookingDate !== "" && $bookingDate !== null) {
        return (string)$bookingDate;
    }

    return "—";
}

/* =========================================================
   CURRENT STAFF
========================================================= */
$staffName = $_SESSION["name"]
    ?? $_SESSION["user_name"]
    ?? "Staff Member";

$staffEmail = $_SESSION["email"] ?? "";

$staffInitial = strtoupper(substr(trim($staffName), 0, 1));
if ($staffInitial === "") {
    $staffInitial = "S";
}

/* =========================================================
   MAIN STATISTICS
========================================================= */
$totalBookings = (int)fetchSingleValue(
    $conn,
    "SELECT COUNT(*) AS total FROM booking",
    "total",
    0
);

$totalPackages = (int)fetchSingleValue(
    $conn,
    "SELECT COUNT(*) AS total FROM packages",
    "total",
    0
);

$totalPayments = (int)fetchSingleValue(
    $conn,
    "SELECT COUNT(*) AS total FROM payments",
    "total",
    0
);

$paidRevenue = (float)fetchSingleValue(
    $conn,
    "SELECT COALESCE(SUM(amount), 0) AS total
     FROM payments
     WHERE LOWER(TRIM(payment_status)) IN ('paid', 'success', 'completed')",
    "total",
    0
);

/* =========================================================
   BOOKING STATUS COUNTS
========================================================= */
$pendingBookings = (int)fetchSingleValue(
    $conn,
    "SELECT COUNT(*) AS total
     FROM booking
     WHERE LOWER(TRIM(status)) = 'pending'",
    "total",
    0
);

$paidBookings = (int)fetchSingleValue(
    $conn,
    "SELECT COUNT(*) AS total
     FROM booking
     WHERE LOWER(TRIM(status)) = 'paid'",
    "total",
    0
);

$confirmedBookings = (int)fetchSingleValue(
    $conn,
    "SELECT COUNT(*) AS total
     FROM booking
     WHERE LOWER(TRIM(status)) = 'confirmed'",
    "total",
    0
);

$completedBookings = (int)fetchSingleValue(
    $conn,
    "SELECT COUNT(*) AS total
     FROM booking
     WHERE LOWER(TRIM(status)) = 'completed'",
    "total",
    0
);

$cancelledBookings = (int)fetchSingleValue(
    $conn,
    "SELECT COUNT(*) AS total
     FROM booking
     WHERE LOWER(TRIM(status)) IN ('cancelled', 'canceled')",
    "total",
    0
);

/* =========================================================
   RECENT BOOKINGS
========================================================= */
$recentBookings = [];

$recentSql = "
    SELECT
        b.id,
        b.status,
        b.persons,
        b.travel_date,
        b.booking_date,
        b.total_price,
        u.name AS customer_name,
        u.email AS customer_email,
        p.title AS package_title,
        p.destination AS package_destination
    FROM booking b
    LEFT JOIN users u
        ON b.user_id = u.id
    LEFT JOIN packages p
        ON b.package_id = p.id
    ORDER BY b.id DESC
    LIMIT 6
";

$recentResult = mysqli_query($conn, $recentSql);

if ($recentResult) {
    while ($row = mysqli_fetch_assoc($recentResult)) {
        $recentBookings[] = $row;
    }
}

/* =========================================================
   POPULAR PACKAGES
========================================================= */
$popularPackages = [];

$popularSql = "
    SELECT
        p.id,
        p.title,
        p.destination,
        COUNT(b.id) AS booking_count
    FROM packages p
    LEFT JOIN booking b
        ON p.id = b.package_id
    GROUP BY
        p.id,
        p.title,
        p.destination
    ORDER BY booking_count DESC, p.id DESC
    LIMIT 5
";

$popularResult = mysqli_query($conn, $popularSql);

if ($popularResult) {
    while ($row = mysqli_fetch_assoc($popularResult)) {
        $popularPackages[] = $row;
    }
}

/* =========================================================
   STATUS BAR PERCENTAGES
========================================================= */
$statusTotal = $pendingBookings
    + $paidBookings
    + $confirmedBookings
    + $completedBookings
    + $cancelledBookings;

if ($statusTotal <= 0) {
    $pendingPercent = 0;
    $paidPercent = 0;
    $confirmedPercent = 0;
    $completedPercent = 0;
    $cancelledPercent = 0;
} else {
    $pendingPercent = round(($pendingBookings / $statusTotal) * 100);
    $paidPercent = round(($paidBookings / $statusTotal) * 100);
    $confirmedPercent = round(($confirmedBookings / $statusTotal) * 100);
    $completedPercent = round(($completedBookings / $statusTotal) * 100);
    $cancelledPercent = round(($cancelledBookings / $statusTotal) * 100);
}

/* =========================================================
   DATE / TIME
========================================================= */
$currentDate = date("l, d F Y");
$currentTime = date("h:i A");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Staff Dashboard | GlobeTrek</title>

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
        href="/globetrak/css/staff.css?v=2000"
    >
</head>

<body>

<div class="staff-dashboard-app">

    <!-- =====================================================
         SIDEBAR
    ====================================================== -->
    <aside class="staff-sidebar" id="staffSidebar">

        <div class="staff-brand">

            <a href="../index.php">
                Globe<span>Trek</span>
            </a>

            <small>STAFF CONTROL PANEL</small>

        </div>

        <nav class="staff-nav">

            <span class="nav-section-title">
                MAIN MENU
            </span>

            <a
                href="sdashboard.php"
                class="active"
            >
                <i class="fa-solid fa-chart-pie"></i>
                <span>Dashboard</span>
            </a>

            <a href="manage-packages.php">
                <i class="fa-solid fa-map-location-dot"></i>
                <span>Packages</span>
            </a>

            <a href="manage-bookings.php">
                <i class="fa-solid fa-calendar-check"></i>
                <span>Bookings</span>

                <?php if ($pendingBookings > 0): ?>
                    <b class="nav-badge">
                        <?php echo $pendingBookings; ?>
                    </b>
                <?php endif; ?>
            </a>

            <a href="manage-payment.php">
                <i class="fa-solid fa-credit-card"></i>
                <span>Payments</span>
            </a>

            <span class="nav-section-title second">
                QUICK ACCESS
            </span>

            <a
                href="../index.php"
                target="_blank"
                rel="noopener"
            >
                <i class="fa-solid fa-globe"></i>
                <span>View Website</span>
            </a>

        </nav>

        <div class="staff-sidebar-bottom">

            <div class="staff-profile-mini">

                <div class="profile-mini-avatar">
                    <?php echo esc($staffInitial); ?>
                </div>

                <div class="profile-mini-info">

                    <strong>
                        <?php echo esc($staffName); ?>
                    </strong>

                    <span>
                        Travel Staff
                    </span>

                </div>

            </div>

            <a
                href="../logout.php"
                class="staff-logout"
            >
                <i class="fa-solid fa-right-from-bracket"></i>
                Logout
            </a>

        </div>

    </aside>

    <!-- =====================================================
         MAIN
    ====================================================== -->
    <main class="staff-main">

        <!-- TOP BAR -->
        <header class="staff-topbar">

            <div class="topbar-left">

                <button
                    type="button"
                    class="mobile-menu-btn"
                    id="mobileMenuBtn"
                    aria-label="Open menu"
                >
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div>
                    <span class="topbar-eyebrow">
                        STAFF MANAGEMENT
                    </span>

                    <h1>
                        Staff Dashboard
                    </h1>

                    <p>
                        Monitor bookings, packages and payment activity from one place.
                    </p>
                </div>

            </div>

            <div class="topbar-right">

                <div class="date-box">

                    <i class="fa-regular fa-calendar"></i>

                    <div>
                        <strong>
                            <?php echo esc($currentDate); ?>
                        </strong>

                        <span>
                            <?php echo esc($currentTime); ?>
                        </span>
                    </div>

                </div>

                <div class="topbar-user">

                    <div class="topbar-avatar">
                        <?php echo esc($staffInitial); ?>
                    </div>

                    <div>
                        <strong>
                            <?php echo esc($staffName); ?>
                        </strong>

                        <span>
                            Staff Member
                        </span>
                    </div>

                </div>

            </div>

        </header>

        <section class="staff-content">

            <!-- =================================================
                 WELCOME HERO
            ================================================== -->
            <section class="welcome-card">

                <div class="welcome-copy">

                    <span class="welcome-kicker">
                        <i class="fa-solid fa-suitcase-rolling"></i>
                        GLOBETREK OPERATIONS
                    </span>

                    <h2>
                        Welcome back, <?php echo esc($staffName); ?>.
                    </h2>

                    <p>
                        Keep track of customer bookings, travel packages and payment records.
                        Everything you need for daily travel operations is here.
                    </p>

                    <div class="welcome-actions">

                        <a
                            href="manage-bookings.php"
                            class="hero-btn primary"
                        >
                            <i class="fa-solid fa-calendar-check"></i>
                            Manage Bookings
                        </a>

                        <a
                            href="manage-packages.php"
                            class="hero-btn secondary"
                        >
                            <i class="fa-solid fa-map-location-dot"></i>
                            Manage Packages
                        </a>

                    </div>

                </div>

                <div class="welcome-visual">

                    <div class="visual-orbit orbit-one"></div>
                    <div class="visual-orbit orbit-two"></div>
                    <div class="visual-icon">
                        <i class="fa-solid fa-plane-departure"></i>
                    </div>

                </div>

            </section>

            <!-- =================================================
                 STAT CARDS
            ================================================== -->
            <section class="stat-grid">

                <div class="stat-card">

                    <div class="stat-icon orange">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>

                    <div class="stat-copy">
                        <span>Total Bookings</span>
                        <strong>
                            <?php echo number_format($totalBookings); ?>
                        </strong>

                        <small>
                            All booking records
                        </small>
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-icon blue">
                        <i class="fa-solid fa-box-archive"></i>
                    </div>

                    <div class="stat-copy">
                        <span>Total Packages</span>
                        <strong>
                            <?php echo number_format($totalPackages); ?>
                        </strong>

                        <small>
                            Travel packages
                        </small>
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-icon green">
                        <i class="fa-solid fa-credit-card"></i>
                    </div>

                    <div class="stat-copy">
                        <span>Total Payments</span>
                        <strong>
                            <?php echo number_format($totalPayments); ?>
                        </strong>

                        <small>
                            Payment records
                        </small>
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-icon purple">
                        <i class="fa-solid fa-sack-dollar"></i>
                    </div>

                    <div class="stat-copy">
                        <span>Paid Revenue</span>
                        <strong class="revenue">
                            Rs. <?php echo number_format($paidRevenue, 2); ?>
                        </strong>

                        <small>
                            Successful payments
                        </small>
                    </div>

                </div>

            </section>

            <!-- =================================================
                 MAIN GRID
            ================================================== -->
            <div class="dashboard-grid">

                <!-- RECENT BOOKINGS -->
                <section class="dashboard-panel recent-panel">

                    <div class="panel-header">

                        <div>
                            <span class="panel-kicker">
                                BOOKING ACTIVITY
                            </span>

                            <h3>
                                Recent Bookings
                            </h3>
                        </div>

                        <a
                            href="manage-bookings.php"
                            class="view-link"
                        >
                            View all
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>

                    </div>

                    <div class="booking-table-wrap">

                        <table class="dashboard-table">

                            <thead>
                                <tr>
                                    <th>BOOKING</th>
                                    <th>CUSTOMER</th>
                                    <th>PACKAGE</th>
                                    <th>TRAVEL DATE</th>
                                    <th>STATUS</th>
                                </tr>
                            </thead>

                            <tbody>

                            <?php if (count($recentBookings) > 0): ?>

                                <?php foreach ($recentBookings as $booking): ?>

                                    <?php
                                    $bookingStatus = trim((string)($booking["status"] ?? "Pending"));
                                    $statusKey = statusClass($bookingStatus);
                                    $customerName = trim((string)($booking["customer_name"] ?? ""));
                                    $packageTitle = trim((string)($booking["package_title"] ?? ""));
                                    $destination = trim((string)($booking["package_destination"] ?? ""));
                                    $customerInitial = strtoupper(substr($customerName !== "" ? $customerName : "C", 0, 1));
                                    ?>

                                    <tr>

                                        <td>
                                            <span class="booking-number">
                                                #<?php echo (int)$booking["id"]; ?>
                                            </span>
                                        </td>

                                        <td>

                                            <div class="table-person">

                                                <div class="person-avatar">
                                                    <?php echo esc($customerInitial); ?>
                                                </div>

                                                <div>
                                                    <strong>
                                                        <?php echo esc($customerName !== "" ? $customerName : "Unknown Customer"); ?>
                                                    </strong>

                                                    <span>
                                                        <?php echo esc($booking["customer_email"] ?? ""); ?>
                                                    </span>
                                                </div>

                                            </div>

                                        </td>

                                        <td>

                                            <div class="package-cell">
                                                <strong>
                                                    <?php echo esc($packageTitle !== "" ? $packageTitle : "Package unavailable"); ?>
                                                </strong>

                                                <?php if ($destination !== ""): ?>
                                                    <span>
                                                        <i class="fa-solid fa-location-dot"></i>
                                                        <?php echo esc($destination); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                        </td>

                                        <td>

                                            <span class="travel-date">
                                                <?php
                                                echo esc(
                                                    formatBookingDate(
                                                        $booking["travel_date"] ?? "",
                                                        $booking["booking_date"] ?? ""
                                                    )
                                                );
                                                ?>
                                            </span>

                                            <?php if (!empty($booking["persons"])): ?>
                                                <small class="persons">
                                                    <?php echo (int)$booking["persons"]; ?>
                                                    <?php echo (int)$booking["persons"] === 1 ? "person" : "persons"; ?>
                                                </small>
                                            <?php endif; ?>

                                        </td>

                                        <td>

                                            <span class="status-badge <?php echo esc($statusKey); ?>">
                                                <i class="fa-solid fa-circle"></i>
                                                <?php echo esc(ucfirst(strtolower($bookingStatus))); ?>
                                            </span>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state compact">
                                            <div class="empty-icon">
                                                <i class="fa-solid fa-calendar-xmark"></i>
                                            </div>
                                            <strong>No bookings found</strong>
                                            <span>
                                                Booking activity will appear here when customers make reservations.
                                            </span>
                                        </div>
                                    </td>
                                </tr>

                            <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </section>

                <!-- STATUS OVERVIEW -->
                <section class="dashboard-panel status-panel">

                    <div class="panel-header">

                        <div>
                            <span class="panel-kicker">
                                LIVE OVERVIEW
                            </span>

                            <h3>
                                Booking Status
                            </h3>
                        </div>

                        <span class="panel-chip">
                            <?php echo number_format($statusTotal); ?> total
                        </span>

                    </div>

                    <div class="status-summary">

                        <div class="status-summary-top">

                            <div class="summary-ring">

                                <div class="ring-inner">
                                    <strong>
                                        <?php echo number_format($statusTotal); ?>
                                    </strong>
                                    <span>Bookings</span>
                                </div>

                            </div>

                            <div class="summary-copy">

                                <strong>
                                    Booking pipeline
                                </strong>

                                <span>
                                    Current status distribution across all reservations.
                                </span>

                            </div>

                        </div>

                        <div class="status-bars">

                            <div class="status-line">
                                <div class="status-line-head">
                                    <span>
                                        <i class="dot pending-dot"></i>
                                        Pending
                                    </span>
                                    <strong>
                                        <?php echo $pendingBookings; ?>
                                    </strong>
                                </div>

                                <div class="bar">
                                    <span
                                        style="width: <?php echo $pendingPercent; ?>%;"
                                        class="bar-fill pending-fill"
                                    ></span>
                                </div>
                            </div>

                            <div class="status-line">
                                <div class="status-line-head">
                                    <span>
                                        <i class="dot paid-dot"></i>
                                        Paid
                                    </span>
                                    <strong>
                                        <?php echo $paidBookings; ?>
                                    </strong>
                                </div>

                                <div class="bar">
                                    <span
                                        style="width: <?php echo $paidPercent; ?>%;"
                                        class="bar-fill paid-fill"
                                    ></span>
                                </div>
                            </div>

                            <div class="status-line">
                                <div class="status-line-head">
                                    <span>
                                        <i class="dot confirmed-dot"></i>
                                        Confirmed
                                    </span>
                                    <strong>
                                        <?php echo $confirmedBookings; ?>
                                    </strong>
                                </div>

                                <div class="bar">
                                    <span
                                        style="width: <?php echo $confirmedPercent; ?>%;"
                                        class="bar-fill confirmed-fill"
                                    ></span>
                                </div>
                            </div>

                            <div class="status-line">
                                <div class="status-line-head">
                                    <span>
                                        <i class="dot completed-dot"></i>
                                        Completed
                                    </span>
                                    <strong>
                                        <?php echo $completedBookings; ?>
                                    </strong>
                                </div>

                                <div class="bar">
                                    <span
                                        style="width: <?php echo $completedPercent; ?>%;"
                                        class="bar-fill completed-fill"
                                    ></span>
                                </div>
                            </div>

                            <div class="status-line">
                                <div class="status-line-head">
                                    <span>
                                        <i class="dot cancelled-dot"></i>
                                        Cancelled
                                    </span>
                                    <strong>
                                        <?php echo $cancelledBookings; ?>
                                    </strong>
                                </div>

                                <div class="bar">
                                    <span
                                        style="width: <?php echo $cancelledPercent; ?>%;"
                                        class="bar-fill cancelled-fill"
                                    ></span>
                                </div>
                            </div>

                        </div>

                    </div>

                </section>

            </div>

            <!-- =================================================
                 LOWER GRID
            ================================================== -->
            <div class="lower-grid">

                <!-- POPULAR PACKAGES -->
                <section class="dashboard-panel">

                    <div class="panel-header">

                        <div>
                            <span class="panel-kicker">
                                PACKAGE PERFORMANCE
                            </span>

                            <h3>
                                Popular Packages
                            </h3>
                        </div>

                        <a
                            href="manage-packages.php"
                            class="view-link"
                        >
                            Manage
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>

                    </div>

                    <div class="package-list">

                        <?php if (count($popularPackages) > 0): ?>

                            <?php foreach ($popularPackages as $index => $package): ?>

                                <div class="package-row">

                                    <div class="package-number">
                                        <?php echo $index + 1; ?>
                                    </div>

                                    <div class="package-main">

                                        <strong>
                                            <?php echo esc($package["title"]); ?>
                                        </strong>

                                        <span>
                                            <i class="fa-solid fa-location-dot"></i>
                                            <?php echo esc($package["destination"] ?? "Destination not set"); ?>
                                        </span>

                                    </div>

                                    <div class="package-count">
                                        <strong>
                                            <?php echo (int)$package["booking_count"]; ?>
                                        </strong>

                                        <span>
                                            bookings
                                        </span>
                                    </div>

                                </div>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <div class="empty-state">
                                <div class="empty-icon">
                                    <i class="fa-solid fa-suitcase-rolling"></i>
                                </div>

                                <strong>No packages found</strong>
                                <span>
                                    Add packages to start tracking package activity.
                                </span>
                            </div>

                        <?php endif; ?>

                    </div>

                </section>

                <!-- QUICK ACTIONS -->
                <section class="dashboard-panel quick-panel">

                    <div class="panel-header">

                        <div>
                            <span class="panel-kicker">
                                DAILY TOOLS
                            </span>

                            <h3>
                                Quick Actions
                            </h3>
                        </div>

                    </div>

                    <div class="quick-actions">

                        <a
                            href="manage-bookings.php"
                            class="quick-action"
                        >
                            <div class="quick-icon orange">
                                <i class="fa-solid fa-calendar-check"></i>
                            </div>

                            <div>
                                <strong>Manage Bookings</strong>
                                <span>
                                    Review and update booking statuses.
                                </span>
                            </div>

                            <i class="fa-solid fa-chevron-right arrow"></i>
                        </a>

                        <a
                            href="manage-packages.php"
                            class="quick-action"
                        >
                            <div class="quick-icon blue">
                                <i class="fa-solid fa-map-location-dot"></i>
                            </div>

                            <div>
                                <strong>Manage Packages</strong>
                                <span>
                                    Add and update travel packages.
                                </span>
                            </div>

                            <i class="fa-solid fa-chevron-right arrow"></i>
                        </a>

                        <a
                            href="manage-payment.php"
                            class="quick-action"
                        >
                            <div class="quick-icon green">
                                <i class="fa-solid fa-credit-card"></i>
                            </div>

                            <div>
                                <strong>View Payments</strong>
                                <span>
                                    Review customer payment records.
                                </span>
                            </div>

                            <i class="fa-solid fa-chevron-right arrow"></i>
                        </a>

                        <a
                            href="../index.php"
                            target="_blank"
                            rel="noopener"
                            class="quick-action"
                        >
                            <div class="quick-icon dark">
                                <i class="fa-solid fa-globe"></i>
                            </div>

                            <div>
                                <strong>Open Website</strong>
                                <span>
                                    View the customer-facing website.
                                </span>
                            </div>

                            <i class="fa-solid fa-arrow-up-right-from-square arrow"></i>
                        </a>

                    </div>

                </section>

            </div>

            <!-- FOOTER -->
            <footer class="staff-dashboard-footer">

                <span>
                    © <?php echo date("Y"); ?> GlobeTrek Adventures
                </span>

                <span>
                    Staff Dashboard
                </span>

            </footer>

        </section>

    </main>

</div>

<script>
(function () {

    const sidebar = document.getElementById("staffSidebar");
    const menuButton = document.getElementById("mobileMenuBtn");

    if (!sidebar || !menuButton) {
        return;
    }

    menuButton.addEventListener("click", function () {
        sidebar.classList.toggle("open");
        document.body.classList.toggle("menu-open");
    });

    document.addEventListener("click", function (event) {

        const clickedInsideSidebar = sidebar.contains(event.target);
        const clickedMenuButton = menuButton.contains(event.target);

        if (
            window.innerWidth <= 900 &&
            !clickedInsideSidebar &&
            !clickedMenuButton
        ) {
            sidebar.classList.remove("open");
            document.body.classList.remove("menu-open");
        }

    });

})();
</script>

</body>
</html>

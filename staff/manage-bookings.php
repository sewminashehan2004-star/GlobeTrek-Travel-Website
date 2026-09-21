<?php
/*
    GlobeTrek - Staff Manage Bookings
    File: staff/manage-bookings.php
*/

session_start();
require_once "../includes/db.php";

/* =========================================================
   STAFF ACCESS
========================================================= */
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "staff"
) {
    header("Location: ../login.php");
    exit();
}

/* =========================================================
   HELPERS
========================================================= */
function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function bookingStatusClass(string $status): string
{
    switch (strtolower(trim($status))) {
        case "paid":
            return "paid";
        case "confirmed":
            return "confirmed";
        case "completed":
            return "completed";
        case "cancelled":
        case "canceled":
            return "cancelled";
        default:
            return "pending";
    }
}

function formatDateValue($date): string
{
    if (empty($date) || $date === "0000-00-00") {
        return "—";
    }

    $time = strtotime((string)$date);

    return $time !== false
        ? date("d M Y", $time)
        : (string)$date;
}

/* =========================================================
   CSRF
========================================================= */
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];

/* =========================================================
   CURRENT STAFF
========================================================= */
$staffName = $_SESSION["name"]
    ?? $_SESSION["user_name"]
    ?? "Staff Member";

$staffInitial = strtoupper(substr(trim($staffName), 0, 1));

if ($staffInitial === "") {
    $staffInitial = "S";
}

/* =========================================================
   FLASH MESSAGE
========================================================= */
$message = "";
$messageType = "success";

/* =========================================================
   HANDLE UPDATE STATUS
========================================================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $postedToken = $_POST["csrf_token"] ?? "";

    if (!hash_equals($csrfToken, $postedToken)) {

        $message = "Security validation failed. Please refresh and try again.";
        $messageType = "error";

    } else {

        $action = $_POST["action"] ?? "";

        if ($action === "update_status") {

            $bookingId = (int)($_POST["booking_id"] ?? 0);
            $newStatus = trim((string)($_POST["status"] ?? ""));

            $allowedStatuses = [
                "Pending",
                "Paid",
                "Confirmed",
                "Completed",
                "Cancelled"
            ];

            if ($bookingId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {

                $message = "Invalid booking or status.";
                $messageType = "error";

            } else {

                $check = mysqli_prepare(
                    $conn,
                    "SELECT id FROM booking WHERE id = ? LIMIT 1"
                );

                mysqli_stmt_bind_param($check, "i", $bookingId);
                mysqli_stmt_execute($check);
                $checkResult = mysqli_stmt_get_result($check);
                $bookingExists = mysqli_fetch_assoc($checkResult);
                mysqli_stmt_close($check);

                if (!$bookingExists) {

                    $message = "Booking not found.";
                    $messageType = "error";

                } else {

                    $stmt = mysqli_prepare(
                        $conn,
                        "UPDATE booking SET status = ? WHERE id = ?"
                    );

                    mysqli_stmt_bind_param(
                        $stmt,
                        "si",
                        $newStatus,
                        $bookingId
                    );

                    if (mysqli_stmt_execute($stmt)) {
                        $message = "Booking #{$bookingId} status updated to {$newStatus}.";
                        $messageType = "success";
                    } else {
                        $message = "Unable to update booking status.";
                        $messageType = "error";
                    }

                    mysqli_stmt_close($stmt);
                }
            }
        }
    }
}

/* =========================================================
   SEARCH + STATUS FILTER
========================================================= */
$search = trim((string)($_GET["search"] ?? ""));
$statusFilter = trim((string)($_GET["status"] ?? ""));

$allowedFilterStatuses = [
    "Pending",
    "Paid",
    "Confirmed",
    "Completed",
    "Cancelled"
];

if (!in_array($statusFilter, $allowedFilterStatuses, true)) {
    $statusFilter = "";
}

/* =========================================================
   BOOKINGS QUERY
========================================================= */
$bookings = [];

$sql = "
    SELECT
        b.id,
        b.booking_date,
        b.travel_date,
        b.status,
        b.persons,
        b.days,
        b.total_price,
        b.user_id,
        b.package_id,

        u.name AS customer_name,
        u.email AS customer_email,
        u.phone AS customer_phone,
        u.location AS customer_location,

        p.title AS package_title,
        p.destination AS package_destination

    FROM booking b

    LEFT JOIN users u
        ON b.user_id = u.id

    LEFT JOIN packages p
        ON b.package_id = p.id
";

/* Build conditions safely */
$conditions = [];
$types = "";
$params = [];

/* Search */
if ($search !== "") {

    $conditions[] = "(
        CAST(b.id AS CHAR) LIKE ?
        OR u.name LIKE ?
        OR u.email LIKE ?
        OR p.title LIKE ?
        OR p.destination LIKE ?
    )";

    $like = "%" . $search . "%";

    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;

    $types .= "sssss";
}

/* Status */
if ($statusFilter !== "") {

    $conditions[] = "LOWER(TRIM(b.status)) = LOWER(?)";
    $params[] = $statusFilter;
    $types .= "s";
}

if (count($conditions) > 0) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY b.id DESC LIMIT 100";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die(
        "Booking query error: " .
        h(mysqli_error($conn))
    );
}

/*
    mysqli_stmt_bind_param requires references.
*/
if ($types !== "") {

    $bindValues = [];
    $bindValues[] = $types;

    foreach ($params as $key => $value) {
        $bindValues[] = &$params[$key];
    }

    call_user_func_array(
        [$stmt, "bind_param"],
        $bindValues
    );
}

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $bookings[] = $row;
    }
}

mysqli_stmt_close($stmt);

/* =========================================================
   STATS
========================================================= */
function bookingCount(mysqli $conn, string $where = ""): int
{
    $sql = "SELECT COUNT(*) AS total FROM booking";

    if ($where !== "") {
        $sql .= " WHERE " . $where;
    }

    $result = mysqli_query($conn, $sql);

    if (!$result) {
        return 0;
    }

    $row = mysqli_fetch_assoc($result);

    return (int)($row["total"] ?? 0);
}

$totalBookings = bookingCount($conn);
$pendingBookings = bookingCount($conn, "LOWER(TRIM(status)) = 'pending'");
$paidBookings = bookingCount($conn, "LOWER(TRIM(status)) = 'paid'");
$confirmedBookings = bookingCount($conn, "LOWER(TRIM(status)) = 'confirmed'");
$completedBookings = bookingCount($conn, "LOWER(TRIM(status)) = 'completed'");

$cancelledBookings = bookingCount(
    $conn,
    "LOWER(TRIM(status)) IN ('cancelled', 'canceled')"
);

/* =========================================================
   DATE
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

    <title>Manage Bookings | GlobeTrek Staff</title>

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
        href="/globetrak/css/staff-bookings.css?v=1000"
    >

</head>

<body>

<div class="staff-booking-app">

    <!-- =====================================================
         SIDEBAR
    ====================================================== -->
    <aside
        class="staff-booking-sidebar"
        id="staffBookingSidebar"
    >

        <div class="staff-booking-brand">

            <a href="sdashboard.php">
                Globe<span>Trek</span>
            </a>

            <small>
                STAFF CONTROL PANEL
            </small>

        </div>

        <nav class="staff-booking-nav">

            <span class="nav-section-title">
                MAIN MENU
            </span>

            <a href="sdashboard.php">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Dashboard</span>
            </a>

            <a href="manage-packages.php">
                <i class="fa-solid fa-map-location-dot"></i>
                <span>Packages</span>
            </a>

            <a
                href="manage-bookings.php"
                class="active"
            >
                <i class="fa-solid fa-calendar-check"></i>
                <span>Bookings</span>

                <?php if ($pendingBookings > 0): ?>
                    <b class="nav-count">
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

        <div class="staff-booking-sidebar-bottom">

            <div class="staff-booking-profile">

                <div class="profile-avatar">
                    <?php echo h($staffInitial); ?>
                </div>

                <div>
                    <strong>
                        <?php echo h($staffName); ?>
                    </strong>

                    <span>
                        Travel Staff
                    </span>
                </div>

            </div>

            <a
                href="../logout.php"
                class="staff-booking-logout"
            >
                <i class="fa-solid fa-right-from-bracket"></i>
                Logout
            </a>

        </div>

    </aside>

    <!-- =====================================================
         MAIN
    ====================================================== -->
    <main class="staff-booking-main">

        <header class="staff-booking-topbar">

            <div class="topbar-left">

                <button
                    type="button"
                    class="mobile-menu-button"
                    id="mobileBookingMenu"
                    aria-label="Open menu"
                >
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div>

                    <span class="topbar-eyebrow">
                        STAFF MANAGEMENT
                    </span>

                    <h1>
                        Manage Bookings
                    </h1>

                    <p>
                        Review customer reservations and update travel booking statuses.
                    </p>

                </div>

            </div>

            <div class="topbar-right">

                <div class="topbar-date">

                    <i class="fa-regular fa-calendar"></i>

                    <div>
                        <strong>
                            <?php echo h($currentDate); ?>
                        </strong>

                        <span>
                            <?php echo h($currentTime); ?>
                        </span>
                    </div>

                </div>

                <div class="topbar-user">

                    <div class="topbar-user-avatar">
                        <?php echo h($staffInitial); ?>
                    </div>

                    <div>
                        <strong>
                            <?php echo h($staffName); ?>
                        </strong>

                        <span>
                            Staff Member
                        </span>
                    </div>

                </div>

            </div>

        </header>

        <section class="staff-booking-content">

            <!-- PAGE INTRO -->
            <section class="page-intro">

                <div>

                    <span class="intro-kicker">
                        <i class="fa-solid fa-calendar-check"></i>
                        BOOKING OPERATIONS
                    </span>

                    <h2>
                        Customer Bookings
                    </h2>

                    <p>
                        View booking information, customer details and update the current
                        reservation status.
                    </p>

                </div>

                <div class="intro-badge">
                    <i class="fa-solid fa-clock"></i>
                    <?php echo number_format($pendingBookings); ?> pending
                </div>

            </section>

            <!-- ALERT -->
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

                        <p>
                            <?php echo h($message); ?>
                        </p>
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

            <!-- STATS -->
            <section class="booking-stat-grid">

                <div class="booking-stat-card">

                    <div class="stat-icon orange">
                        <i class="fa-solid fa-calendar-days"></i>
                    </div>

                    <div>
                        <span>Total Bookings</span>
                        <strong><?php echo number_format($totalBookings); ?></strong>
                        <small>All reservations</small>
                    </div>

                </div>

                <div class="booking-stat-card">

                    <div class="stat-icon yellow">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>

                    <div>
                        <span>Pending</span>
                        <strong><?php echo number_format($pendingBookings); ?></strong>
                        <small>Need attention</small>
                    </div>

                </div>

                <div class="booking-stat-card">

                    <div class="stat-icon blue">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>

                    <div>
                        <span>Confirmed</span>
                        <strong><?php echo number_format($confirmedBookings); ?></strong>
                        <small>Confirmed trips</small>
                    </div>

                </div>

                <div class="booking-stat-card">

                    <div class="stat-icon green">
                        <i class="fa-solid fa-flag-checkered"></i>
                    </div>

                    <div>
                        <span>Completed</span>
                        <strong><?php echo number_format($completedBookings); ?></strong>
                        <small>Finished trips</small>
                    </div>

                </div>

            </section>

            <!-- FILTER -->
            <section class="filter-card">

                <div class="filter-card-heading">

                    <div>
                        <span class="filter-kicker">
                            RESERVATION DIRECTORY
                        </span>

                        <h3>
                            Find Bookings
                        </h3>
                    </div>

                    <span class="result-pill">
                        <?php echo number_format(count($bookings)); ?> shown
                    </span>

                </div>

                <form
                    method="GET"
                    class="filter-form"
                >

                    <div class="booking-search">

                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="text"
                            name="search"
                            value="<?php echo h($search); ?>"
                            placeholder="Booking ID, customer, email, package or destination..."
                        >

                    </div>

                    <select
                        name="status"
                        class="status-select"
                    >
                        <option value="">All statuses</option>

                        <?php foreach ($allowedFilterStatuses as $option): ?>

                            <option
                                value="<?php echo h($option); ?>"
                                <?php echo $statusFilter === $option ? "selected" : ""; ?>
                            >
                                <?php echo h($option); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <button
                        type="submit"
                        class="search-button"
                    >
                        <i class="fa-solid fa-filter"></i>
                        Filter
                    </button>

                    <?php if ($search !== "" || $statusFilter !== ""): ?>

                        <a
                            href="manage-bookings.php"
                            class="clear-button"
                        >
                            <i class="fa-solid fa-rotate-left"></i>
                            Reset
                        </a>

                    <?php endif; ?>

                </form>

            </section>

            <!-- TABLE -->
            <section class="booking-table-panel">

                <div class="table-panel-header">

                    <div>
                        <span class="panel-kicker">
                            RESERVATION MANAGEMENT
                        </span>

                        <h3>
                            Booking Records
                        </h3>
                    </div>

                    <span class="panel-date">
                        <?php echo h(date("d M Y")); ?>
                    </span>

                </div>

                <div class="booking-table-wrap">

                    <table class="booking-table">

                        <thead>

                            <tr>
                                <th>BOOKING</th>
                                <th>CUSTOMER</th>
                                <th>PACKAGE</th>
                                <th>TRAVEL DATE</th>
                                <th>PEOPLE</th>
                                <th>TOTAL</th>
                                <th>STATUS</th>
                                <th>ACTION</th>
                            </tr>

                        </thead>

                        <tbody>

                        <?php if (count($bookings) > 0): ?>

                            <?php foreach ($bookings as $booking): ?>

                                <?php
                                $bookingId = (int)$booking["id"];
                                $customerName = trim((string)($booking["customer_name"] ?? ""));
                                $customerEmail = trim((string)($booking["customer_email"] ?? ""));
                                $packageTitle = trim((string)($booking["package_title"] ?? ""));
                                $destination = trim((string)($booking["package_destination"] ?? ""));
                                $bookingStatus = trim((string)($booking["status"] ?? "Pending"));

                                $customerInitial = strtoupper(
                                    substr(
                                        $customerName !== "" ? $customerName : "C",
                                        0,
                                        1
                                    )
                                );
                                ?>

                                <tr>

                                    <td>

                                        <span class="booking-id">
                                            #<?php echo $bookingId; ?>
                                        </span>

                                    </td>

                                    <td>

                                        <div class="customer-cell">

                                            <div class="customer-avatar">
                                                <?php echo h($customerInitial); ?>
                                            </div>

                                            <div>

                                                <strong>
                                                    <?php echo h(
                                                        $customerName !== ""
                                                            ? $customerName
                                                            : "Unknown Customer"
                                                    ); ?>
                                                </strong>

                                                <span>
                                                    <?php echo h($customerEmail); ?>
                                                </span>

                                            </div>

                                        </div>

                                    </td>

                                    <td>

                                        <div class="package-cell">

                                            <strong>
                                                <?php echo h(
                                                    $packageTitle !== ""
                                                        ? $packageTitle
                                                        : "Package unavailable"
                                                ); ?>
                                            </strong>

                                            <span>
                                                <i class="fa-solid fa-location-dot"></i>

                                                <?php echo h(
                                                    $destination !== ""
                                                        ? $destination
                                                        : "Destination not set"
                                                ); ?>
                                            </span>

                                        </div>

                                    </td>

                                    <td>

                                        <div class="date-cell">

                                            <strong>
                                                <?php echo h(
                                                    formatDateValue(
                                                        $booking["travel_date"] ?? ""
                                                    )
                                                ); ?>
                                            </strong>

                                            <span>
                                                Booking:
                                                <?php echo h(
                                                    !empty($booking["booking_date"])
                                                        ? $booking["booking_date"]
                                                        : "—"
                                                ); ?>
                                            </span>

                                        </div>

                                    </td>

                                    <td>

                                        <span class="people-count">
                                            <i class="fa-solid fa-users"></i>
                                            <?php echo (int)($booking["persons"] ?? 0); ?>
                                        </span>

                                    </td>

                                    <td>

                                        <strong class="booking-total">
                                            LKR
                                            <?php echo number_format(
                                                (float)($booking["total_price"] ?? 0),
                                                2
                                            ); ?>
                                        </strong>

                                    </td>

                                    <td>

                                        <span class="status-badge <?php echo h(
                                            bookingStatusClass($bookingStatus)
                                        ); ?>">
                                            <i class="fa-solid fa-circle"></i>
                                            <?php echo h(ucfirst(strtolower($bookingStatus))); ?>
                                        </span>

                                    </td>

                                    <td>

                                        <div class="action-group">

                                            <button
                                                type="button"
                                                class="table-action view"
                                                title="View booking details"
                                                onclick='openBookingDetails(
                                                    <?php echo htmlspecialchars(
                                                        json_encode(
                                                            [
                                                                "id" => $bookingId,
                                                                "customer" => $customerName !== "" ? $customerName : "Unknown Customer",
                                                                "email" => $customerEmail,
                                                                "phone" => $booking["customer_phone"] ?? "",
                                                                "location" => $booking["customer_location"] ?? "",
                                                                "package" => $packageTitle !== "" ? $packageTitle : "Package unavailable",
                                                                "destination" => $destination,
                                                                "travel_date" => formatDateValue($booking["travel_date"] ?? ""),
                                                                "booking_date" => !empty($booking["booking_date"]) ? $booking["booking_date"] : "—",
                                                                "persons" => (int)($booking["persons"] ?? 0),
                                                                "days" => (int)($booking["days"] ?? 0),
                                                                "total" => number_format((float)($booking["total_price"] ?? 0), 2),
                                                                "status" => $bookingStatus
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
                                                <i class="fa-solid fa-eye"></i>
                                            </button>

                                            <button
                                                type="button"
                                                class="table-action edit"
                                                title="Update status"
                                                onclick='openStatusModal(
                                                    <?php echo $bookingId; ?>,
                                                    <?php echo json_encode($bookingStatus); ?>
                                                )'
                                            >
                                                <i class="fa-solid fa-pen"></i>
                                            </button>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="8">

                                    <div class="empty-bookings">

                                        <div class="empty-icon">
                                            <i class="fa-solid fa-calendar-xmark"></i>
                                        </div>

                                        <h4>
                                            No bookings found
                                        </h4>

                                        <p>
                                            Try another search or status filter.
                                        </p>

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
     VIEW BOOKING MODAL
========================================================= -->
<div
    class="staff-modal"
    id="bookingDetailsModal"
>

    <div
        class="modal-overlay"
        onclick="closeModal('bookingDetailsModal')"
    ></div>

    <div class="modal-card details-modal">

        <button
            type="button"
            class="modal-close"
            onclick="closeModal('bookingDetailsModal')"
        >
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="modal-icon blue">
            <i class="fa-solid fa-calendar-check"></i>
        </div>

        <span class="modal-kicker">
            BOOKING DETAILS
        </span>

        <h3>
            Booking <span id="detailBookingId">#0</span>
        </h3>

        <div class="detail-grid">

            <div class="detail-item">
                <span>CUSTOMER</span>
                <strong id="detailCustomer">—</strong>
            </div>

            <div class="detail-item">
                <span>EMAIL</span>
                <strong id="detailEmail">—</strong>
            </div>

            <div class="detail-item">
                <span>PHONE</span>
                <strong id="detailPhone">—</strong>
            </div>

            <div class="detail-item">
                <span>LOCATION</span>
                <strong id="detailLocation">—</strong>
            </div>

            <div class="detail-item full">
                <span>PACKAGE</span>
                <strong id="detailPackage">—</strong>
            </div>

            <div class="detail-item">
                <span>DESTINATION</span>
                <strong id="detailDestination">—</strong>
            </div>

            <div class="detail-item">
                <span>TRAVEL DATE</span>
                <strong id="detailTravelDate">—</strong>
            </div>

            <div class="detail-item">
                <span>BOOKING DATE</span>
                <strong id="detailBookingDate">—</strong>
            </div>

            <div class="detail-item">
                <span>PEOPLE</span>
                <strong id="detailPersons">—</strong>
            </div>

            <div class="detail-item">
                <span>DAYS</span>
                <strong id="detailDays">—</strong>
            </div>

            <div class="detail-item">
                <span>TOTAL</span>
                <strong id="detailTotal">LKR 0.00</strong>
            </div>

            <div class="detail-item">
                <span>STATUS</span>
                <strong id="detailStatus">—</strong>
            </div>

        </div>

        <div class="modal-footer single">
            <button
                type="button"
                class="secondary-button"
                onclick="closeModal('bookingDetailsModal')"
            >
                Close
            </button>
        </div>

    </div>

</div>

<!-- =========================================================
     UPDATE STATUS MODAL
========================================================= -->
<div
    class="staff-modal"
    id="statusModal"
>

    <div
        class="modal-overlay"
        onclick="closeModal('statusModal')"
    ></div>

    <div class="modal-card status-modal-card">

        <button
            type="button"
            class="modal-close"
            onclick="closeModal('statusModal')"
        >
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="modal-icon orange">
            <i class="fa-solid fa-arrows-rotate"></i>
        </div>

        <span class="modal-kicker">
            BOOKING OPERATIONS
        </span>

        <h3>
            Update Booking Status
        </h3>

        <p class="modal-description">
            Change the current status of booking
            <strong id="statusBookingId">#0</strong>.
        </p>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo h($csrfToken); ?>"
            >

            <input
                type="hidden"
                name="action"
                value="update_status"
            >

            <input
                type="hidden"
                name="booking_id"
                id="statusBookingInput"
                value=""
            >

            <label
                class="modal-label"
                for="bookingStatusInput"
            >
                Booking Status
            </label>

            <select
                name="status"
                id="bookingStatusInput"
                class="modal-select"
                required
            >
                <?php foreach ($allowedFilterStatuses as $option): ?>

                    <option value="<?php echo h($option); ?>">
                        <?php echo h($option); ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <div class="modal-footer">

                <button
                    type="button"
                    class="secondary-button"
                    onclick="closeModal('statusModal')"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="primary-button"
                >
                    <i class="fa-solid fa-floppy-disk"></i>
                    Save Status
                </button>

            </div>

        </form>

    </div>

</div>

<script>
function closeModal(id) {
    const modal = document.getElementById(id);

    if (modal) {
        modal.classList.remove("show");
        document.body.classList.remove("modal-open");
    }
}

function openModal(id) {
    const modal = document.getElementById(id);

    if (modal) {
        modal.classList.add("show");
        document.body.classList.add("modal-open");
    }
}

function openStatusModal(bookingId, currentStatus) {

    const idInput = document.getElementById("statusBookingInput");
    const idLabel = document.getElementById("statusBookingId");
    const select = document.getElementById("bookingStatusInput");

    if (!idInput || !idLabel || !select) {
        return;
    }

    idInput.value = bookingId;
    idLabel.textContent = "#" + bookingId;

    const optionValues = Array.from(select.options).map(function(option) {
        return option.value.toLowerCase();
    });

    const wanted = String(currentStatus || "Pending").toLowerCase();

    if (optionValues.includes(wanted)) {
        select.value = currentStatus;
    } else {
        select.value = "Pending";
    }

    openModal("statusModal");
}

function openBookingDetails(data) {

    document.getElementById("detailBookingId").textContent = "#" + (data.id || "0");
    document.getElementById("detailCustomer").textContent = data.customer || "—";
    document.getElementById("detailEmail").textContent = data.email || "—";
    document.getElementById("detailPhone").textContent = data.phone || "—";
    document.getElementById("detailLocation").textContent = data.location || "—";
    document.getElementById("detailPackage").textContent = data.package || "—";
    document.getElementById("detailDestination").textContent = data.destination || "—";
    document.getElementById("detailTravelDate").textContent = data.travel_date || "—";
    document.getElementById("detailBookingDate").textContent = data.booking_date || "—";
    document.getElementById("detailPersons").textContent = data.persons ?? "—";
    document.getElementById("detailDays").textContent = data.days ?? "—";
    document.getElementById("detailTotal").textContent = "LKR " + (data.total || "0.00");
    document.getElementById("detailStatus").textContent = data.status || "—";

    openModal("bookingDetailsModal");
}

document.addEventListener("keydown", function(event) {

    if (event.key === "Escape") {

        document.querySelectorAll(".staff-modal.show").forEach(function(modal) {
            modal.classList.remove("show");
        });

        document.body.classList.remove("modal-open");
    }

});

const bookingMenuButton = document.getElementById("mobileBookingMenu");
const bookingSidebar = document.getElementById("staffBookingSidebar");

if (bookingMenuButton && bookingSidebar) {

    bookingMenuButton.addEventListener("click", function() {

        bookingSidebar.classList.toggle("open");
        document.body.classList.toggle("booking-menu-open");

    });

    document.addEventListener("click", function(event) {

        if (window.innerWidth <= 900) {

            const insideSidebar = bookingSidebar.contains(event.target);
            const insideButton = bookingMenuButton.contains(event.target);

            if (!insideSidebar && !insideButton) {
                bookingSidebar.classList.remove("open");
                document.body.classList.remove("booking-menu-open");
            }
        }

    });

}
</script>

</body>
</html>

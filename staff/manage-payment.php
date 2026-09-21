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

if (empty($_SESSION["staff_payment_csrf"])) {
    $_SESSION["staff_payment_csrf"] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION["staff_payment_csrf"];

$message = "";
$messageType = "success";

/* PAYMENT ACTIONS */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $postedToken = $_POST["csrf_token"] ?? "";

    if (!hash_equals($csrfToken, $postedToken)) {
        $message = "Security verification failed. Please try again.";
        $messageType = "error";
    } else {

        $action = $_POST["action"] ?? "";
        $paymentId = (int)($_POST["payment_id"] ?? 0);

        if ($action === "update_payment") {

            $status = trim((string)($_POST["payment_status"] ?? ""));
            $allowedStatuses = ["Pending", "Paid", "Failed", "Refunded"];

            if ($paymentId <= 0 || !in_array($status, $allowedStatuses, true)) {
                $message = "Invalid payment or payment status.";
                $messageType = "error";
            } else {

                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE payments SET payment_status = ? WHERE id = ?"
                );

                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "si", $status, $paymentId);

                    if (mysqli_stmt_execute($stmt)) {
                        $message = "Payment status updated successfully.";
                        $_SESSION["staff_payment_csrf"] = bin2hex(random_bytes(32));
                        $csrfToken = $_SESSION["staff_payment_csrf"];
                    } else {
                        $message = "Unable to update payment status.";
                        $messageType = "error";
                    }

                    mysqli_stmt_close($stmt);
                } else {
                    $message = "Unable to prepare payment update.";
                    $messageType = "error";
                }
            }

        } elseif ($action === "delete_payment") {

            if ($paymentId <= 0) {
                $message = "Invalid payment selected.";
                $messageType = "error";
            } else {

                $stmt = mysqli_prepare(
                    $conn,
                    "DELETE FROM payments WHERE id = ?"
                );

                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "i", $paymentId);

                    if (
                        mysqli_stmt_execute($stmt) &&
                        mysqli_stmt_affected_rows($stmt) > 0
                    ) {
                        $message = "Payment record deleted successfully.";
                        $_SESSION["staff_payment_csrf"] = bin2hex(random_bytes(32));
                        $csrfToken = $_SESSION["staff_payment_csrf"];
                    } else {
                        $message = "Unable to delete payment record.";
                        $messageType = "error";
                    }

                    mysqli_stmt_close($stmt);
                } else {
                    $message = "Unable to prepare payment deletion.";
                    $messageType = "error";
                }
            }
        }
    }
}

/* FILTERS */
$search = trim((string)($_GET["search"] ?? ""));
$statusFilter = trim((string)($_GET["status"] ?? ""));

$allowedFilters = ["Pending", "Paid", "Failed", "Refunded"];
if ($statusFilter !== "" && !in_array($statusFilter, $allowedFilters, true)) {
    $statusFilter = "";
}

/* STATS */
$paymentStats = [
    "total" => 0,
    "paid" => 0,
    "pending" => 0,
    "value" => 0
];

$statsResult = mysqli_query(
    $conn,
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN payment_status = 'Paid' THEN 1 ELSE 0 END) AS paid,
        SUM(CASE WHEN payment_status = 'Pending' THEN 1 ELSE 0 END) AS pending,
        COALESCE(SUM(CASE WHEN payment_status = 'Paid' THEN amount ELSE 0 END), 0) AS paid_value
     FROM payments"
);

if ($statsResult) {
    $row = mysqli_fetch_assoc($statsResult);

    $paymentStats["total"] = (int)($row["total"] ?? 0);
    $paymentStats["paid"] = (int)($row["paid"] ?? 0);
    $paymentStats["pending"] = (int)($row["pending"] ?? 0);
    $paymentStats["value"] = (float)($row["paid_value"] ?? 0);
}

/* PAYMENTS */
$payments = [];

$sql = "SELECT
            p.id,
            p.booking_id,
            p.amount,
            p.payment_status,
            p.payment_method,
            p.user_id,
            p.card_name,
            p.card_number,
            b.travel_date,
            b.persons,
            b.days,
            pk.title AS package_title,
            pk.destination,
            u.name AS customer_name,
            u.email AS customer_email
        FROM payments p
        LEFT JOIN booking b ON b.id = p.booking_id
        LEFT JOIN packages pk ON pk.id = b.package_id
        LEFT JOIN users u ON u.id = p.user_id";

$where = [];
$params = [];
$types = "";

if ($search !== "") {
    $where[] = "(
        CAST(p.id AS CHAR) LIKE ?
        OR CAST(p.booking_id AS CHAR) LIKE ?
        OR COALESCE(u.name, '') LIKE ?
        OR COALESCE(u.email, '') LIKE ?
        OR COALESCE(pk.title, '') LIKE ?
        OR COALESCE(pk.destination, '') LIKE ?
        OR COALESCE(p.payment_method, '') LIKE ?
    )";

    $like = "%" . $search . "%";

    for ($i = 0; $i < 7; $i++) {
        $params[] = $like;
        $types .= "s";
    }
}

if ($statusFilter !== "") {
    $where[] = "p.payment_status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

if (count($where) > 0) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY p.id DESC";

$stmt = mysqli_prepare($conn, $sql);

if ($stmt) {

    if (count($params) > 0) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $payments[] = $row;
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

    <title>Manage Payments | GlobeTrek Staff</title>

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
        href="/globetrak/css/staff-payments.css?v=1000"
    >

</head>

<body>

<div class="staff-payment-app">

    <aside class="staff-payment-sidebar" id="staffPaymentSidebar">

        <div class="staff-payment-brand">

            <a href="sdashboard.php">
                Globe<span>Trek</span>
            </a>

            <small>STAFF CONTROL PANEL</small>

        </div>

        <nav class="staff-payment-nav">

            <span class="nav-section-title">MAIN MENU</span>

            <a href="sdashboard.php">
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
            </a>

            <a href="manage-payment.php" class="active">
                <i class="fa-solid fa-credit-card"></i>
                <span>Payments</span>
            </a>

            <span class="nav-section-title second">QUICK ACCESS</span>

            <a href="../index.php" target="_blank" rel="noopener">
                <i class="fa-solid fa-globe"></i>
                <span>View Website</span>
            </a>

        </nav>

        <div class="staff-payment-sidebar-bottom">

            <div class="staff-payment-profile">

                <div class="profile-avatar">
                    <?php echo h($staffInitial); ?>
                </div>

                <div>
                    <strong><?php echo h($staffName); ?></strong>
                    <span>Travel Staff</span>
                </div>

            </div>

            <a href="../logout.php" class="staff-payment-logout">
                <i class="fa-solid fa-right-from-bracket"></i>
                Logout
            </a>

        </div>

    </aside>

    <main class="staff-payment-main">

        <header class="staff-payment-topbar">

            <div class="topbar-left">

                <button
                    type="button"
                    class="mobile-menu-button"
                    id="mobilePaymentMenu"
                    aria-label="Open menu"
                >
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div>

                    <span class="topbar-eyebrow">STAFF MANAGEMENT</span>

                    <h1>Manage Payments</h1>

                    <p>
                        Monitor payment records and transaction statuses.
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

        <section class="staff-payment-content">

            <section class="page-intro">

                <div>

                    <span class="intro-kicker">
                        <i class="fa-solid fa-credit-card"></i>
                        TRANSACTION MANAGEMENT
                    </span>

                    <h2>Payment Management</h2>

                    <p>
                        Review customer payments, linked bookings, payment methods
                        and transaction statuses from one place.
                    </p>

                </div>

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

            <section class="payment-stat-grid">

                <div class="payment-stat-card">

                    <div class="stat-icon orange">
                        <i class="fa-solid fa-receipt"></i>
                    </div>

                    <div>
                        <span>All Payments</span>
                        <strong><?php echo number_format($paymentStats["total"]); ?></strong>
                        <small>Payment records</small>
                    </div>

                </div>

                <div class="payment-stat-card">

                    <div class="stat-icon green">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>

                    <div>
                        <span>Paid</span>
                        <strong><?php echo number_format($paymentStats["paid"]); ?></strong>
                        <small>Completed transactions</small>
                    </div>

                </div>

                <div class="payment-stat-card">

                    <div class="stat-icon blue">
                        <i class="fa-solid fa-clock"></i>
                    </div>

                    <div>
                        <span>Pending</span>
                        <strong><?php echo number_format($paymentStats["pending"]); ?></strong>
                        <small>Awaiting confirmation</small>
                    </div>

                </div>

                <div class="payment-stat-card">

                    <div class="stat-icon purple">
                        <i class="fa-solid fa-coins"></i>
                    </div>

                    <div>
                        <span>Paid Value</span>
                        <strong>LKR <?php echo number_format($paymentStats["value"], 0); ?></strong>
                        <small>Current paid total</small>
                    </div>

                </div>

            </section>

            <section class="filter-card">

                <div class="filter-heading">

                    <div>
                        <span>TRANSACTION DIRECTORY</span>
                        <h3>Find Payments</h3>
                    </div>

                    <b>
                        <?php echo number_format(count($payments)); ?> shown
                    </b>

                </div>

                <form method="GET" class="filter-form">

                    <div class="search-box">

                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="text"
                            name="search"
                            value="<?php echo h($search); ?>"
                            placeholder="Search payment, booking, customer, package..."
                        >

                    </div>

                    <select name="status" class="status-select">

                        <option value="">All Statuses</option>

                        <?php foreach ($allowedFilters as $filter): ?>

                            <option
                                value="<?php echo h($filter); ?>"
                                <?php echo $statusFilter === $filter ? "selected" : ""; ?>
                            >
                                <?php echo h($filter); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <button type="submit" class="filter-button">
                        <i class="fa-solid fa-filter"></i>
                        Filter
                    </button>

                    <?php if ($search !== "" || $statusFilter !== ""): ?>

                        <a href="manage-payment.php" class="reset-button">
                            <i class="fa-solid fa-rotate-left"></i>
                            Reset
                        </a>

                    <?php endif; ?>

                </form>

            </section>

            <section class="payment-panel">

                <div class="panel-header">

                    <div>
                        <span>TRANSACTIONS</span>
                        <h3>Payment Records</h3>
                    </div>

                    <span class="panel-note">
                        <i class="fa-solid fa-shield-halved"></i>
                        Card security protected
                    </span>

                </div>

                <?php if (count($payments) > 0): ?>

                    <div class="table-wrap">

                        <table class="payment-table">

                            <thead>

                                <tr>
                                    <th>Payment</th>
                                    <th>Customer</th>
                                    <th>Booking</th>
                                    <th>Package</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($payments as $payment): ?>

                                    <?php
                                    $paymentId = (int)$payment["id"];
                                    $bookingId = (int)($payment["booking_id"] ?? 0);
                                    $amount = (float)($payment["amount"] ?? 0);
                                    $status = (string)($payment["payment_status"] ?? "Pending");
                                    $method = (string)($payment["payment_method"] ?? "Not specified");
                                    $customer = (string)($payment["customer_name"] ?? "Unknown Customer");
                                    $email = (string)($payment["customer_email"] ?? "");
                                    $packageTitle = (string)($payment["package_title"] ?? "Unknown Package");
                                    $destination = (string)($payment["destination"] ?? "—");
                                    $travelDate = (string)($payment["travel_date"] ?? "");
                                    $cardName = (string)($payment["card_name"] ?? "");
                                    $maskedCard = (string)($payment["card_number"] ?? "");
                                    $persons = (int)($payment["persons"] ?? 0);
                                    $days = (int)($payment["days"] ?? 0);

                                    $statusClass = strtolower($status);
                                    ?>

                                    <tr>

                                        <td>

                                            <div class="payment-id">

                                                <div class="payment-icon">
                                                    <i class="fa-solid fa-credit-card"></i>
                                                </div>

                                                <div>
                                                    <strong>#<?php echo $paymentId; ?></strong>
                                                    <span>
                                                        Booking #<?php echo $bookingId; ?>
                                                    </span>
                                                </div>

                                            </div>

                                        </td>

                                        <td>

                                            <div class="customer-cell">

                                                <strong>
                                                    <?php echo h($customer); ?>
                                                </strong>

                                                <span>
                                                    <?php echo h($email); ?>
                                                </span>

                                            </div>

                                        </td>

                                        <td>

                                            <span class="booking-chip">
                                                #<?php echo $bookingId; ?>
                                            </span>

                                            <?php if ($travelDate !== ""): ?>

                                                <small class="travel-date">
                                                    <i class="fa-regular fa-calendar"></i>
                                                    <?php echo h($travelDate); ?>
                                                </small>

                                            <?php endif; ?>

                                        </td>

                                        <td>

                                            <div class="package-cell">

                                                <strong>
                                                    <?php echo h($packageTitle); ?>
                                                </strong>

                                                <span>
                                                    <i class="fa-solid fa-location-dot"></i>
                                                    <?php echo h($destination); ?>
                                                </span>

                                            </div>

                                        </td>

                                        <td>

                                            <strong class="amount">
                                                LKR <?php echo number_format($amount, 2); ?>
                                            </strong>

                                        </td>

                                        <td>

                                            <span class="method-chip">
                                                <i class="fa-solid fa-wallet"></i>
                                                <?php echo h($method); ?>
                                            </span>

                                        </td>

                                        <td>

                                            <span class="status-badge <?php echo h($statusClass); ?>">
                                                <i class="fa-solid
                                                    <?php
                                                    echo $status === "Paid"
                                                        ? "fa-circle-check"
                                                        : ($status === "Pending"
                                                            ? "fa-clock"
                                                            : ($status === "Refunded"
                                                                ? "fa-rotate-left"
                                                                : "fa-circle-xmark"));
                                                    ?>">
                                                </i>

                                                <?php echo h($status); ?>
                                            </span>

                                        </td>

                                        <td>

                                            <div class="table-actions">

                                                <button
                                                    type="button"
                                                    class="icon-button view"
                                                    title="View payment"
                                                    onclick='openViewModal(<?php
                                                        echo htmlspecialchars(
                                                            json_encode(
                                                                [
                                                                    "id" => $paymentId,
                                                                    "booking_id" => $bookingId,
                                                                    "amount" => $amount,
                                                                    "status" => $status,
                                                                    "method" => $method,
                                                                    "customer" => $customer,
                                                                    "email" => $email,
                                                                    "package" => $packageTitle,
                                                                    "destination" => $destination,
                                                                    "travel_date" => $travelDate,
                                                                    "card_name" => $cardName,
                                                                    "card_number" => $maskedCard,
                                                                    "persons" => $persons,
                                                                    "days" => $days
                                                                ],
                                                                JSON_HEX_TAG |
                                                                JSON_HEX_APOS |
                                                                JSON_HEX_QUOT |
                                                                JSON_HEX_AMP
                                                            ),
                                                            ENT_QUOTES,
                                                            "UTF-8"
                                                        );
                                                    ?>)'
                                                >
                                                    <i class="fa-solid fa-eye"></i>
                                                </button>

                                                <button
                                                    type="button"
                                                    class="icon-button edit"
                                                    title="Update status"
                                                    onclick='openStatusModal(
                                                        <?php echo $paymentId; ?>,
                                                        <?php echo htmlspecialchars(json_encode($status, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, "UTF-8"); ?>
                                                    )'
                                                >
                                                    <i class="fa-solid fa-pen"></i>
                                                </button>

                                                <button
                                                    type="button"
                                                    class="icon-button delete"
                                                    title="Delete payment"
                                                    onclick="openDeleteModal(<?php echo $paymentId; ?>)"
                                                >
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

                    <div class="empty-payments">

                        <div class="empty-icon">
                            <i class="fa-solid fa-credit-card"></i>
                        </div>

                        <h4>No payment records found</h4>

                        <p>
                            Try another search or change the payment status filter.
                        </p>

                    </div>

                <?php endif; ?>

            </section>

        </section>

    </main>

</div>

<!-- VIEW MODAL -->
<div class="staff-modal" id="viewPaymentModal">

    <div class="modal-overlay" onclick="closeModal('viewPaymentModal')"></div>

    <div class="modal-card">

        <button
            type="button"
            class="modal-close"
            onclick="closeModal('viewPaymentModal')"
        >
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="modal-icon blue">
            <i class="fa-solid fa-receipt"></i>
        </div>

        <span class="modal-kicker">TRANSACTION DETAILS</span>

        <h3>Payment Details</h3>

        <p>
            Review the transaction and linked booking information.
        </p>

        <div class="detail-grid">

            <div class="detail-item">
                <span>Payment ID</span>
                <strong id="viewPaymentId">—</strong>
            </div>

            <div class="detail-item">
                <span>Booking ID</span>
                <strong id="viewBookingId">—</strong>
            </div>

            <div class="detail-item">
                <span>Customer</span>
                <strong id="viewCustomer">—</strong>
            </div>

            <div class="detail-item">
                <span>Email</span>
                <strong id="viewEmail">—</strong>
            </div>

            <div class="detail-item">
                <span>Package</span>
                <strong id="viewPackage">—</strong>
            </div>

            <div class="detail-item">
                <span>Destination</span>
                <strong id="viewDestination">—</strong>
            </div>

            <div class="detail-item">
                <span>Travel Date</span>
                <strong id="viewTravelDate">—</strong>
            </div>

            <div class="detail-item">
                <span>Amount</span>
                <strong id="viewAmount">—</strong>
            </div>

            <div class="detail-item">
                <span>Payment Method</span>
                <strong id="viewMethod">—</strong>
            </div>

            <div class="detail-item">
                <span>Status</span>
                <strong id="viewStatus">—</strong>
            </div>

            <div class="detail-item">
                <span>Travelers</span>
                <strong id="viewPersons">—</strong>
            </div>

            <div class="detail-item">
                <span>Duration</span>
                <strong id="viewDays">—</strong>
            </div>

            <div class="detail-item">
                <span>Card Name</span>
                <strong id="viewCardName">—</strong>
            </div>

            <div class="detail-item">
                <span>Card Number</span>
                <strong id="viewCardNumber">Protected</strong>
            </div>

        </div>

        <div class="security-note">
            <i class="fa-solid fa-shield-halved"></i>
            Sensitive card security values are not displayed.
        </div>

    </div>

</div>

<!-- STATUS MODAL -->
<div class="staff-modal" id="statusPaymentModal">

    <div class="modal-overlay" onclick="closeModal('statusPaymentModal')"></div>

    <div class="modal-card small-modal">

        <button
            type="button"
            class="modal-close"
            onclick="closeModal('statusPaymentModal')"
        >
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="modal-icon orange">
            <i class="fa-solid fa-pen"></i>
        </div>

        <span class="modal-kicker">TRANSACTION MANAGEMENT</span>

        <h3>Update Payment</h3>

        <p>
            Change the payment status for this transaction.
        </p>

        <form method="POST">

            <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
            <input type="hidden" name="action" value="update_payment">
            <input type="hidden" name="payment_id" id="statusPaymentId">

            <div class="form-field">

                <label>Payment Status</label>

                <select name="payment_status" id="statusPaymentValue" required>

                    <?php foreach ($allowedFilters as $filter): ?>

                        <option value="<?php echo h($filter); ?>">
                            <?php echo h($filter); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="secondary-button"
                    onclick="closeModal('statusPaymentModal')"
                >
                    Cancel
                </button>

                <button type="submit" class="primary-button">
                    <i class="fa-solid fa-floppy-disk"></i>
                    Save Status
                </button>

            </div>

        </form>

    </div>

</div>

<!-- DELETE MODAL -->
<div class="staff-modal" id="deletePaymentModal">

    <div class="modal-overlay" onclick="closeModal('deletePaymentModal')"></div>

    <div class="modal-card small-modal">

        <button
            type="button"
            class="modal-close"
            onclick="closeModal('deletePaymentModal')"
        >
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="modal-icon red">
            <i class="fa-solid fa-trash"></i>
        </div>

        <span class="modal-kicker">TRANSACTION MANAGEMENT</span>

        <h3>Delete Payment?</h3>

        <p>
            This will permanently remove payment record
            <strong id="deletePaymentText">#0</strong>.
            This action cannot be undone.
        </p>

        <form method="POST">

            <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
            <input type="hidden" name="action" value="delete_payment">
            <input type="hidden" name="payment_id" id="deletePaymentId">

            <div class="modal-footer">

                <button
                    type="button"
                    class="secondary-button"
                    onclick="closeModal('deletePaymentModal')"
                >
                    Cancel
                </button>

                <button type="submit" class="danger-button">
                    <i class="fa-solid fa-trash"></i>
                    Delete Payment
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

function openViewModal(data) {

    document.getElementById("viewPaymentId").textContent = "#" + (data.id || "—");
    document.getElementById("viewBookingId").textContent = "#" + (data.booking_id || "—");
    document.getElementById("viewCustomer").textContent = data.customer || "—";
    document.getElementById("viewEmail").textContent = data.email || "—";
    document.getElementById("viewPackage").textContent = data.package || "—";
    document.getElementById("viewDestination").textContent = data.destination || "—";
    document.getElementById("viewTravelDate").textContent = data.travel_date || "—";
    document.getElementById("viewAmount").textContent =
        "LKR " + Number(data.amount || 0).toLocaleString("en-LK", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    document.getElementById("viewMethod").textContent = data.method || "—";
    document.getElementById("viewStatus").textContent = data.status || "—";
    document.getElementById("viewPersons").textContent =
        data.persons ? data.persons + " person(s)" : "—";
    document.getElementById("viewDays").textContent =
        data.days ? data.days + " day(s)" : "—";
    document.getElementById("viewCardName").textContent = data.card_name || "—";
    document.getElementById("viewCardNumber").textContent =
        data.card_number && data.card_number !== "***"
            ? "Protected / masked"
            : "Protected";

    openModal("viewPaymentModal");
}

function openStatusModal(id, status) {

    document.getElementById("statusPaymentId").value = id;
    document.getElementById("statusPaymentValue").value = status;

    openModal("statusPaymentModal");
}

function openDeleteModal(id) {

    document.getElementById("deletePaymentId").value = id;
    document.getElementById("deletePaymentText").textContent = "#" + id;

    openModal("deletePaymentModal");
}

document.addEventListener("keydown", function(event) {

    if (event.key === "Escape") {

        document.querySelectorAll(".staff-modal.show").forEach(function(modal) {
            modal.classList.remove("show");
        });

        document.body.classList.remove("modal-open");
    }

});

const menuButton = document.getElementById("mobilePaymentMenu");
const sidebar = document.getElementById("staffPaymentSidebar");

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

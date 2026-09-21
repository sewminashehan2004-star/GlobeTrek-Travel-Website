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

if (empty($_SESSION["admin_payment_csrf"])) {
    $_SESSION["admin_payment_csrf"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["admin_payment_csrf"];

/* =========================================================
   MESSAGE
========================================================= */

$message = "";
$messageType = "";

/* =========================================================
   STATUS OPTIONS
========================================================= */

$statusOptions = [
    "Pending",
    "Paid",
    "Failed",
    "Refunded"
];

/* =========================================================
   UPDATE PAYMENT
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["update_payment"])
) {

    $postedToken = $_POST["csrf_token"] ?? "";

    if (
        empty($postedToken) ||
        !hash_equals($_SESSION["admin_payment_csrf"], $postedToken)
    ) {

        $message = "Security verification failed. Please try again.";
        $messageType = "error";

    } else {

        $paymentId = (int)($_POST["payment_id"] ?? 0);
        $newStatus = trim($_POST["payment_status"] ?? "");

        if ($paymentId <= 0) {

            $message = "Invalid payment ID.";
            $messageType = "error";

        } elseif (!in_array($newStatus, $statusOptions, true)) {

            $message = "Invalid payment status.";
            $messageType = "error";

        } else {

            $stmt = mysqli_prepare(
                $conn,
                "UPDATE payments
                 SET payment_status = ?
                 WHERE id = ?"
            );

            if (!$stmt) {

                $message = "Unable to prepare payment update.";
                $messageType = "error";

            } else {

                mysqli_stmt_bind_param(
                    $stmt,
                    "si",
                    $newStatus,
                    $paymentId
                );

                if (mysqli_stmt_execute($stmt)) {

                    $message =
                        "Payment #" .
                        $paymentId .
                        " status updated successfully.";

                    $messageType = "success";

                    $_SESSION["admin_payment_csrf"] =
                        bin2hex(random_bytes(32));

                    $csrfToken =
                        $_SESSION["admin_payment_csrf"];

                } else {

                    $message =
                        "Unable to update payment status.";

                    $messageType = "error";
                }

                mysqli_stmt_close($stmt);
            }
        }
    }
}

/* =========================================================
   DELETE PAYMENT
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["delete_payment"])
) {

    $postedToken = $_POST["csrf_token"] ?? "";

    if (
        empty($postedToken) ||
        !hash_equals($_SESSION["admin_payment_csrf"], $postedToken)
    ) {

        $message = "Security verification failed. Please try again.";
        $messageType = "error";

    } else {

        $paymentId = (int)($_POST["payment_id"] ?? 0);

        if ($paymentId <= 0) {

            $message = "Invalid payment selected.";
            $messageType = "error";

        } else {

            $stmt = mysqli_prepare(
                $conn,
                "DELETE FROM payments
                 WHERE id = ?"
            );

            if (!$stmt) {

                $message = "Unable to prepare payment deletion.";
                $messageType = "error";

            } else {

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $paymentId
                );

                if (
                    mysqli_stmt_execute($stmt) &&
                    mysqli_stmt_affected_rows($stmt) > 0
                ) {

                    $message =
                        "Payment #" .
                        $paymentId .
                        " deleted successfully.";

                    $messageType = "success";

                    $_SESSION["admin_payment_csrf"] =
                        bin2hex(random_bytes(32));

                    $csrfToken =
                        $_SESSION["admin_payment_csrf"];

                } else {

                    $message =
                        "Unable to delete payment.";

                    $messageType = "error";
                }

                mysqli_stmt_close($stmt);
            }
        }
    }
}

/* =========================================================
   FILTERS
========================================================= */

$search = trim($_GET["search"] ?? "");
$statusFilter = trim($_GET["status"] ?? "");

if (!in_array($statusFilter, $statusOptions, true)) {
    $statusFilter = "";
}

/* =========================================================
   PAYMENT STATS
========================================================= */

$paymentStats = [
    "total" => 0,
    "paid" => 0,
    "pending" => 0,
    "amount" => 0
];

$totalResult = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(amount), 0) AS amount
     FROM payments"
);

if ($totalResult) {

    $row = mysqli_fetch_assoc($totalResult);

    $paymentStats["total"] =
        (int)($row["total"] ?? 0);

    $paymentStats["amount"] =
        (float)($row["amount"] ?? 0);
}

$statusResult = mysqli_query(
    $conn,
    "SELECT LOWER(TRIM(payment_status)) AS payment_status,
            COUNT(*) AS total
     FROM payments
     GROUP BY LOWER(TRIM(payment_status))"
);

if ($statusResult) {

    while ($row = mysqli_fetch_assoc($statusResult)) {

        $status = strtolower(
            trim($row["payment_status"] ?? "")
        );

        $count = (int)($row["total"] ?? 0);

        if ($status === "paid") {
            $paymentStats["paid"] = $count;
        }

        if ($status === "pending") {
            $paymentStats["pending"] = $count;
        }
    }
}

/* =========================================================
   LOAD PAYMENTS
========================================================= */

$payments = [];

$baseSelect = "
    SELECT
        pay.id AS payment_id,
        pay.booking_id,
        pay.amount,
        pay.payment_status,
        pay.payment_method,
        pay.user_id,
        pay.card_name,
        pay.card_number,
        pay.exp_date,

        b.travel_date,
        b.persons,
        b.days,
        b.status AS booking_status,

        u.name AS customer_name,
        u.email AS customer_email,

        p.title AS package_title,
        p.destination AS package_destination

    FROM payments pay

    LEFT JOIN booking b
        ON pay.booking_id = b.id

    LEFT JOIN users u
        ON pay.user_id = u.id

    LEFT JOIN packages p
        ON b.package_id = p.id
";

if ($search !== "" && $statusFilter !== "") {

    $like = "%" . $search . "%";

    $sql = $baseSelect . "
        WHERE pay.payment_status = ?
          AND (
                CAST(pay.id AS CHAR) LIKE ?
                OR CAST(pay.booking_id AS CHAR) LIKE ?
                OR u.name LIKE ?
                OR u.email LIKE ?
                OR p.title LIKE ?
                OR p.destination LIKE ?
                OR pay.payment_method LIKE ?
             )
        ORDER BY pay.id DESC
    ";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "ssssssss",
        $statusFilter,
        $like,
        $like,
        $like,
        $like,
        $like,
        $like,
        $like
    );

} elseif ($search !== "") {

    $like = "%" . $search . "%";

    $sql = $baseSelect . "
        WHERE
            CAST(pay.id AS CHAR) LIKE ?
            OR CAST(pay.booking_id AS CHAR) LIKE ?
            OR u.name LIKE ?
            OR u.email LIKE ?
            OR p.title LIKE ?
            OR p.destination LIKE ?
            OR pay.payment_method LIKE ?
        ORDER BY pay.id DESC
    ";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "sssssss",
        $like,
        $like,
        $like,
        $like,
        $like,
        $like,
        $like
    );

} elseif ($statusFilter !== "") {

    $sql = $baseSelect . "
        WHERE pay.payment_status = ?
        ORDER BY pay.id DESC
    ";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "s",
        $statusFilter
    );

} else {

    $sql = $baseSelect . "
        ORDER BY pay.id DESC
    ";

    $stmt = mysqli_prepare($conn, $sql);
}

if (!$stmt) {
    die(
        "Payment query error: " .
        htmlspecialchars(mysqli_error($conn))
    );
}

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $payments[] = $row;
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
        Manage Payments | GlobeTrek
    </title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="stylesheet" href="/globetrak/css/mange-payments.css?v=999">

</head>

<body>

<div class="admin-payment-app">

    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <aside class="payment-sidebar">

        <div class="payment-brand">

            <a href="../index.php">
                Globe<span>Trek</span>
            </a>

            <small>
                ADMIN PANEL
            </small>

        </div>

        <nav class="payment-admin-nav">

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

            <a href="manage-packages.php">
                <i class="fa-solid fa-map-location-dot"></i>
                Packages
            </a>

            <a href="manage-bookings.php">
                <i class="fa-solid fa-calendar-check"></i>
                Bookings
            </a>

            <a
                href="manage-payments.php"
                class="active">

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

        <div class="payment-sidebar-bottom">

            <div class="payment-admin-profile">

                <div class="payment-admin-avatar">
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

    <main class="payment-main">

        <header class="payment-topbar">

            <button
                type="button"
                class="mobile-sidebar-button"
                id="mobileSidebarButton">

                <i class="fa-solid fa-bars"></i>

            </button>

            <div>

                <span class="topbar-eyebrow">
                    FINANCIAL MANAGEMENT
                </span>

                <h1>
                    Payments
                </h1>

                <p>
                    Review and manage customer payment records.
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
                class="payment-alert <?php echo $messageType === "success" ? "success" : "error"; ?>">

                <i class="fa-solid <?php echo $messageType === "success" ? "fa-circle-check" : "fa-circle-exclamation"; ?>"></i>

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

        <section class="payment-page-intro">

            <div>

                <span>
                    TRANSACTION MANAGEMENT
                </span>

                <h2>
                    Payment Management
                </h2>

                <p>
                    Monitor payment records, amounts, methods,
                    linked bookings and transaction statuses.
                </p>

            </div>

            <div class="intro-total">

                <span>
                    TOTAL PAYMENT VALUE
                </span>

                <strong>
                    LKR <?php echo number_format($paymentStats["amount"], 2); ?>
                </strong>

            </div>

        </section>

        <!-- =====================================================
             STATS
        ====================================================== -->

        <section class="payment-stat-grid">

            <div class="payment-stat">

                <div class="payment-stat-icon total">
                    <i class="fa-solid fa-receipt"></i>
                </div>

                <div>

                    <span>
                        All Payments
                    </span>

                    <strong>
                        <?php echo $paymentStats["total"]; ?>
                    </strong>

                </div>

            </div>

            <div class="payment-stat">

                <div class="payment-stat-icon paid">
                    <i class="fa-solid fa-circle-check"></i>
                </div>

                <div>

                    <span>
                        Paid
                    </span>

                    <strong>
                        <?php echo $paymentStats["paid"]; ?>
                    </strong>

                </div>

            </div>

            <div class="payment-stat">

                <div class="payment-stat-icon pending">
                    <i class="fa-solid fa-clock"></i>
                </div>

                <div>

                    <span>
                        Pending
                    </span>

                    <strong>
                        <?php echo $paymentStats["pending"]; ?>
                    </strong>

                </div>

            </div>

            <div class="payment-stat">

                <div class="payment-stat-icon value">
                    <i class="fa-solid fa-coins"></i>
                </div>

                <div>

                    <span>
                        Payment Value
                    </span>

                    <strong class="amount-value">
                        LKR <?php echo number_format($paymentStats["amount"], 0); ?>
                    </strong>

                </div>

            </div>

        </section>

        <!-- =====================================================
             FILTER
        ====================================================== -->

        <section class="payment-filter-card">

            <form
                method="GET"
                class="payment-filter-form">

                <div class="filter-search">

                    <i class="fa-solid fa-magnifying-glass"></i>

                    <input
                        type="text"
                        name="search"
                        value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Search payment, booking, customer, package or method...">

                </div>

                <div class="filter-status">

                    <select name="status">

                        <option value="">
                            All statuses
                        </option>

                        <?php foreach ($statusOptions as $option): ?>

                            <option
                                value="<?php echo htmlspecialchars($option); ?>"
                                <?php echo $statusFilter === $option ? "selected" : ""; ?>>

                                <?php echo htmlspecialchars($option); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <button
                    type="submit"
                    class="filter-button">

                    <i class="fa-solid fa-filter"></i>
                    Filter

                </button>

                <?php if ($search !== "" || $statusFilter !== ""): ?>

                    <a
                        href="manage-payments.php"
                        class="clear-filter">

                        Clear

                    </a>

                <?php endif; ?>

            </form>

        </section>

        <!-- =====================================================
             TABLE
        ====================================================== -->

        <section class="payment-table-panel">

            <div class="table-panel-header">

                <div>

                    <span>
                        TRANSACTION RECORDS
                    </span>

                    <h2>
                        Customer Payments
                    </h2>

                </div>

                <div class="result-count">

                    <?php echo count($payments); ?>
                    result(s)

                </div>

            </div>

            <?php if (!empty($payments)): ?>

                <div class="table-scroll">

                    <table class="payment-table">

                        <thead>

                            <tr>

                                <th>
                                    Payment
                                </th>

                                <th>
                                    Customer
                                </th>

                                <th>
                                    Booking
                                </th>

                                <th>
                                    Package
                                </th>

                                <th>
                                    Amount
                                </th>

                                <th>
                                    Method
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Action
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($payments as $payment): ?>

                            <?php

                            $paymentId =
                                (int)$payment["payment_id"];

                            $bookingId =
                                (int)($payment["booking_id"] ?? 0);

                            $customerName =
                                $payment["customer_name"]
                                ?? "Unknown Customer";

                            $customerEmail =
                                $payment["customer_email"]
                                ?? "-";

                            $packageTitle =
                                $payment["package_title"]
                                ?? "Package unavailable";

                            $destination =
                                $payment["package_destination"]
                                ?? "Sri Lanka";

                            $amount =
                                (float)($payment["amount"] ?? 0);

                            $paymentStatus =
                                trim(
                                    $payment["payment_status"]
                                    ?? "Pending"
                                );

                            $method =
                                trim(
                                    $payment["payment_method"]
                                    ?? "Card"
                                );

                            $cardNumber =
                                trim(
                                    $payment["card_number"]
                                    ?? ""
                                );

                            $cardName =
                                trim(
                                    $payment["card_name"]
                                    ?? ""
                                );

                            $expiry =
                                trim(
                                    $payment["exp_date"]
                                    ?? ""
                                );

                            $travelDate =
                                "Not set";

                            if (!empty($payment["travel_date"])) {

                                $timestamp =
                                    strtotime(
                                        $payment["travel_date"]
                                    );

                                if ($timestamp !== false) {
                                    $travelDate =
                                        date(
                                            "d M Y",
                                            $timestamp
                                        );
                                }
                            }

                            $statusLower =
                                strtolower($paymentStatus);

                            $statusClass =
                                "pending";

                            if ($statusLower === "paid") {
                                $statusClass = "paid";
                            } elseif ($statusLower === "failed") {
                                $statusClass = "failed";
                            } elseif ($statusLower === "refunded") {
                                $statusClass = "refunded";
                            }

                            $customerLetter =
                                strtoupper(
                                    substr(
                                        $customerName,
                                        0,
                                        1
                                    )
                                );

                            ?>

                            <tr>

                                <!-- PAYMENT -->

                                <td>

                                    <div class="payment-id-cell">

                                        <span class="payment-id-icon">

                                            <i class="fa-solid fa-receipt"></i>

                                        </span>

                                        <div>

                                            <strong>
                                                #<?php echo $paymentId; ?>
                                            </strong>

                                            <small>
                                                Booking #<?php echo $bookingId; ?>
                                            </small>

                                        </div>

                                    </div>

                                </td>

                                <!-- CUSTOMER -->

                                <td>

                                    <div class="customer-cell">

                                        <div class="customer-avatar">

                                            <?php
                                            echo htmlspecialchars(
                                                $customerLetter
                                            );
                                            ?>

                                        </div>

                                        <div>

                                            <strong>
                                                <?php
                                                echo htmlspecialchars(
                                                    $customerName
                                                );
                                                ?>
                                            </strong>

                                            <small>
                                                <?php
                                                echo htmlspecialchars(
                                                    $customerEmail
                                                );
                                                ?>
                                            </small>

                                        </div>

                                    </div>

                                </td>

                                <!-- BOOKING -->

                                <td>

                                    <div class="booking-cell">

                                        <strong>
                                            #<?php echo $bookingId; ?>
                                        </strong>

                                        <small>
                                            <?php echo htmlspecialchars($travelDate); ?>
                                        </small>

                                    </div>

                                </td>

                                <!-- PACKAGE -->

                                <td>

                                    <div class="package-cell">

                                        <strong>
                                            <?php
                                            echo htmlspecialchars(
                                                $packageTitle
                                            );
                                            ?>
                                        </strong>

                                        <span>

                                            <i class="fa-solid fa-location-dot"></i>

                                            <?php
                                            echo htmlspecialchars(
                                                $destination
                                            );
                                            ?>

                                        </span>

                                    </div>

                                </td>

                                <!-- AMOUNT -->

                                <td>

                                    <strong class="amount-cell">
                                        LKR
                                        <?php
                                        echo number_format(
                                            $amount,
                                            2
                                        );
                                        ?>
                                    </strong>

                                </td>

                                <!-- METHOD -->

                                <td>

                                    <span class="method-pill">

                                        <i class="fa-regular fa-credit-card"></i>

                                        <?php
                                        echo htmlspecialchars(
                                            ucfirst($method)
                                        );
                                        ?>

                                    </span>

                                </td>

                                <!-- STATUS -->

                                <td>

                                    <span
                                        class="payment-status <?php echo $statusClass; ?>">

                                        <i
                                            class="fa-solid
                                            <?php
                                            if ($statusLower === "paid") {
                                                echo "fa-circle-check";
                                            } elseif ($statusLower === "failed") {
                                                echo "fa-circle-xmark";
                                            } elseif ($statusLower === "refunded") {
                                                echo "fa-rotate-left";
                                            } else {
                                                echo "fa-clock";
                                            }
                                            ?>">
                                        </i>

                                        <?php
                                        echo htmlspecialchars(
                                            $paymentStatus
                                        );
                                        ?>

                                    </span>

                                </td>

                                <!-- ACTIONS -->

                                <td>

                                    <div class="payment-actions">

                                        <button
                                            type="button"
                                            class="action-button view"
                                            onclick='openViewModal(<?php echo json_encode([
                                                "paymentId" => $paymentId,
                                                "bookingId" => $bookingId,
                                                "customer" => $customerName,
                                                "email" => $customerEmail,
                                                "package" => $packageTitle,
                                                "destination" => $destination,
                                                "amount" => number_format($amount, 2),
                                                "method" => ucfirst($method),
                                                "status" => $paymentStatus,
                                                "cardName" => $cardName,
                                                "cardNumber" => $cardNumber,
                                                "expiry" => $expiry,
                                                "travelDate" => $travelDate,
                                                "persons" => (int)($payment["persons"] ?? 0),
                                                "days" => (int)($payment["days"] ?? 0)
                                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>)'>

                                            <i class="fa-solid fa-eye"></i>

                                        </button>

                                        <button
                                            type="button"
                                            class="action-button update"
                                            onclick="openUpdateModal(
                                                <?php echo $paymentId; ?>,
                                                <?php echo json_encode($paymentStatus); ?>
                                            )">

                                            <i class="fa-solid fa-pen"></i>

                                        </button>

                                        <button
                                            type="button"
                                            class="action-button delete"
                                            onclick="openDeleteModal(
                                                <?php echo $paymentId; ?>,
                                                <?php echo json_encode($customerName); ?>
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

                <div class="empty-payments">

                    <div class="empty-icon">
                        <i class="fa-regular fa-credit-card"></i>
                    </div>

                    <h3>
                        No payments found
                    </h3>

                    <p>
                        No payment records match your current filter.
                    </p>

                    <a
                        href="manage-payments.php"
                        class="empty-button">

                        View All Payments

                    </a>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>

<!-- =========================================================
     VIEW PAYMENT MODAL
========================================================= -->

<div
    class="admin-modal"
    id="viewModal">

    <div
        class="modal-overlay"
        onclick="closeViewModal()">
    </div>

    <div class="modal-card view-card">

        <button
            type="button"
            class="modal-close"
            onclick="closeViewModal()">

            <i class="fa-solid fa-xmark"></i>

        </button>

        <div class="modal-icon view-icon">
            <i class="fa-solid fa-receipt"></i>
        </div>

        <span class="modal-label">
            PAYMENT DETAILS
        </span>

        <h2>
            Payment <span id="viewPaymentTitle">#0</span>
        </h2>

        <p>
            Transaction information connected to the customer booking.
        </p>

        <div class="detail-grid">

            <div class="detail-item">
                <span>Customer</span>
                <strong id="viewCustomer">-</strong>
            </div>

            <div class="detail-item">
                <span>Email</span>
                <strong id="viewEmail">-</strong>
            </div>

            <div class="detail-item">
                <span>Booking</span>
                <strong id="viewBooking">-</strong>
            </div>

            <div class="detail-item">
                <span>Travel Date</span>
                <strong id="viewTravelDate">-</strong>
            </div>

            <div class="detail-item">
                <span>Package</span>
                <strong id="viewPackage">-</strong>
            </div>

            <div class="detail-item">
                <span>Destination</span>
                <strong id="viewDestination">-</strong>
            </div>

            <div class="detail-item">
                <span>Persons</span>
                <strong id="viewPersons">-</strong>
            </div>

            <div class="detail-item">
                <span>Days</span>
                <strong id="viewDays">-</strong>
            </div>

            <div class="detail-item">
                <span>Payment Method</span>
                <strong id="viewMethod">-</strong>
            </div>

            <div class="detail-item">
                <span>Amount</span>
                <strong id="viewAmount">-</strong>
            </div>

            <div class="detail-item">
                <span>Card Holder</span>
                <strong id="viewCardName">-</strong>
            </div>

            <div class="detail-item">
                <span>Card Reference</span>
                <strong id="viewCardNumber">-</strong>
            </div>

            <div class="detail-item">
                <span>Expiry</span>
                <strong id="viewExpiry">-</strong>
            </div>

            <div class="detail-item">
                <span>Status</span>
                <strong id="viewStatus">-</strong>
            </div>

        </div>

        <div class="security-note">

            <i class="fa-solid fa-shield-halved"></i>

            <span>
                Sensitive card security values such as CVV are not displayed.
            </span>

        </div>

    </div>

</div>

<!-- =========================================================
     UPDATE MODAL
========================================================= -->

<div
    class="admin-modal"
    id="updateModal">

    <div
        class="modal-overlay"
        onclick="closeUpdateModal()">
    </div>

    <div class="modal-card small-card">

        <button
            type="button"
            class="modal-close"
            onclick="closeUpdateModal()">

            <i class="fa-solid fa-xmark"></i>

        </button>

        <div class="modal-icon update-icon">
            <i class="fa-solid fa-pen-to-square"></i>
        </div>

        <span class="modal-label">
            PAYMENT MANAGEMENT
        </span>

        <h2>
            Update Status
        </h2>

        <p>
            Update the status of payment
            <strong id="updatePaymentTitle">
                #0
            </strong>.
        </p>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars($csrfToken); ?>">

            <input
                type="hidden"
                name="payment_id"
                id="updatePaymentId">

            <label
                class="modal-form-label"
                for="updatePaymentStatus">

                Payment Status

            </label>

            <div class="modal-select">

                <i class="fa-solid fa-list-check"></i>

                <select
                    name="payment_status"
                    id="updatePaymentStatus"
                    required>

                    <?php foreach ($statusOptions as $option): ?>

                        <option
                            value="<?php echo htmlspecialchars($option); ?>">

                            <?php echo htmlspecialchars($option); ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <button
                type="submit"
                name="update_payment"
                class="modal-primary-button">

                <i class="fa-solid fa-check"></i>
                Save Changes

            </button>

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

    <div class="modal-card small-card delete-card">

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
            Delete Payment?
        </h2>

        <p>
            This will permanently delete payment
            <strong id="deletePaymentTitle">
                #0
            </strong>
            from the payment records.
        </p>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars($csrfToken); ?>">

            <input
                type="hidden"
                name="payment_id"
                id="deletePaymentId">

            <div class="delete-actions">

                <button
                    type="button"
                    class="secondary-button"
                    onclick="closeDeleteModal()">

                    Cancel

                </button>

                <button
                    type="submit"
                    name="delete_payment"
                    class="danger-button">

                    <i class="fa-solid fa-trash"></i>
                    Delete Payment

                </button>

            </div>

        </form>

    </div>

</div>

<script>
function openViewModal(payment) {

    document.getElementById("viewPaymentTitle").textContent =
        "#" + payment.paymentId;

    document.getElementById("viewCustomer").textContent =
        payment.customer || "-";

    document.getElementById("viewEmail").textContent =
        payment.email || "-";

    document.getElementById("viewBooking").textContent =
        "#" + (payment.bookingId || 0);

    document.getElementById("viewTravelDate").textContent =
        payment.travelDate || "-";

    document.getElementById("viewPackage").textContent =
        payment.package || "-";

    document.getElementById("viewDestination").textContent =
        payment.destination || "-";

    document.getElementById("viewPersons").textContent =
        payment.persons || 0;

    document.getElementById("viewDays").textContent =
        payment.days || 0;

    document.getElementById("viewMethod").textContent =
        payment.method || "-";

    document.getElementById("viewAmount").textContent =
        "LKR " + (payment.amount || "0.00");

    document.getElementById("viewCardName").textContent =
        payment.cardName || "-";

    let cardReference =
        payment.cardNumber || "";

    if (cardReference === "") {
        cardReference = "Not available";
    }

    document.getElementById("viewCardNumber").textContent =
        cardReference;

    document.getElementById("viewExpiry").textContent =
        payment.expiry || "-";

    document.getElementById("viewStatus").textContent =
        payment.status || "-";

    document
        .getElementById("viewModal")
        .classList.add("show");
}

function closeViewModal() {

    document
        .getElementById("viewModal")
        .classList.remove("show");
}

function openUpdateModal(id, status) {

    document.getElementById("updatePaymentId").value =
        id;

    document.getElementById("updatePaymentTitle").textContent =
        "#" + id;

    document.getElementById("updatePaymentStatus").value =
        status || "Pending";

    document
        .getElementById("updateModal")
        .classList.add("show");
}

function closeUpdateModal() {

    document
        .getElementById("updateModal")
        .classList.remove("show");
}

function openDeleteModal(id) {

    document.getElementById("deletePaymentId").value =
        id;

    document.getElementById("deletePaymentTitle").textContent =
        "#" + id;

    document
        .getElementById("deleteModal")
        .classList.add("show");
}

function closeDeleteModal() {

    document
        .getElementById("deleteModal")
        .classList.remove("show");
}

document.addEventListener("keydown", function(event) {

    if (event.key === "Escape") {

        closeViewModal();
        closeUpdateModal();
        closeDeleteModal();

    }

});

const mobileButton =
    document.getElementById("mobileSidebarButton");

if (mobileButton) {

    mobileButton.addEventListener("click", function() {

        document
            .querySelector(".payment-sidebar")
            .classList.toggle("open");

    });

}
</script>

</body>
</html>

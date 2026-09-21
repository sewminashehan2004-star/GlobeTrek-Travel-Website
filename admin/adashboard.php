<?php

/*
    =========================================================
    GLOBETREK - ADMIN DASHBOARD
    =========================================================
*/

session_start();

include "../includes/db.php";


/* =========================================================
   ADMIN SECURITY
========================================================= */

if (
    !isset($_SESSION["user_id"])
    ||
    !isset($_SESSION["role"])
    ||
    $_SESSION["role"] !== "admin"
) {

    header("Location: ../login.php");

    exit();

}


/* =========================================================
   ADMIN DETAILS
========================================================= */

$adminId =
    (int)$_SESSION["user_id"];


$adminName =
    $_SESSION["name"] ?? "Administrator";


$adminEmail = "";


/* Get current admin email */

$adminSql = "
    SELECT name, email
    FROM users
    WHERE id = ?
      AND role = 'admin'
    LIMIT 1
";


$adminStmt =
    mysqli_prepare(
        $conn,
        $adminSql
    );


if ($adminStmt) {

    mysqli_stmt_bind_param(
        $adminStmt,
        "i",
        $adminId
    );

    mysqli_stmt_execute(
        $adminStmt
    );

    $adminResult =
        mysqli_stmt_get_result(
            $adminStmt
        );

    $adminData =
        mysqli_fetch_assoc(
            $adminResult
        );

    if ($adminData) {

        $adminName =
            $adminData["name"];

        $adminEmail =
            $adminData["email"];

    }

    mysqli_stmt_close(
        $adminStmt
    );
}


/* =========================================================
   DASHBOARD DATE
========================================================= */

$currentDate =
    date("l, d F Y");


/* =========================================================
   TOTAL USERS
========================================================= */

$totalUsers = 0;

$sql = "
    SELECT COUNT(*) AS total
    FROM users
";


$result =
    mysqli_query(
        $conn,
        $sql
    );


if ($result) {

    $row =
        mysqli_fetch_assoc(
            $result
        );

    $totalUsers =
        (int)$row["total"];

}


/* =========================================================
   TOTAL CUSTOMERS
========================================================= */

$totalCustomers = 0;

$sql = "
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'customer'
";


$result =
    mysqli_query(
        $conn,
        $sql
    );


if ($result) {

    $row =
        mysqli_fetch_assoc(
            $result
        );

    $totalCustomers =
        (int)$row["total"];

}


/* =========================================================
   TOTAL STAFF
========================================================= */

$totalStaff = 0;

$sql = "
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'staff'
";


$result =
    mysqli_query(
        $conn,
        $sql
    );


if ($result) {

    $row =
        mysqli_fetch_assoc(
            $result
        );

    $totalStaff =
        (int)$row["total"];

}


/* =========================================================
   TOTAL PACKAGES
========================================================= */

$totalPackages = 0;

$sql = "
    SELECT COUNT(*) AS total
    FROM packages
";


$result =
    mysqli_query(
        $conn,
        $sql
    );


if ($result) {

    $row =
        mysqli_fetch_assoc(
            $result
        );

    $totalPackages =
        (int)$row["total"];

}


/* =========================================================
   TOTAL BOOKINGS
========================================================= */

$totalBookings = 0;

$sql = "
    SELECT COUNT(*) AS total
    FROM booking
";


$result =
    mysqli_query(
        $conn,
        $sql
    );


if ($result) {

    $row =
        mysqli_fetch_assoc(
            $result
        );

    $totalBookings =
        (int)$row["total"];

}


/* =========================================================
   PENDING BOOKINGS
========================================================= */

$pendingBookings = 0;

$sql = "
    SELECT COUNT(*) AS total
    FROM booking
    WHERE LOWER(status) = 'pending'
";


$result =
    mysqli_query(
        $conn,
        $sql
    );


if ($result) {

    $row =
        mysqli_fetch_assoc(
            $result
        );

    $pendingBookings =
        (int)$row["total"];

}


/* =========================================================
   PAID BOOKINGS
========================================================= */

$paidBookings = 0;

$sql = "
    SELECT COUNT(*) AS total
    FROM booking
    WHERE LOWER(status) = 'paid'
";


$result =
    mysqli_query(
        $conn,
        $sql
    );


if ($result) {

    $row =
        mysqli_fetch_assoc(
            $result
        );

    $paidBookings =
        (int)$row["total"];

}


/* =========================================================
   TOTAL REVENUE
=========================================================

   Only paid payments are counted.
   Negative values are ignored.

========================================================= */

$totalRevenue = 0;

$sql = "
    SELECT COALESCE(
        SUM(
            CASE
                WHEN amount > 0
                THEN amount
                ELSE 0
            END
        ),
        0
    ) AS total
    FROM payments
    WHERE LOWER(payment_status) = 'paid'
";


$result =
    mysqli_query(
        $conn,
        $sql
    );


if ($result) {

    $row =
        mysqli_fetch_assoc(
            $result
        );

    $totalRevenue =
        (float)$row["total"];

}


/* =========================================================
   UPCOMING TRIPS
========================================================= */

$upcomingTrips = 0;

$sql = "
    SELECT COUNT(*) AS total

    FROM booking

    WHERE travel_date IS NOT NULL

      AND travel_date >= CURDATE()

      AND LOWER(status) <> 'cancelled'
";


$result =
    mysqli_query(
        $conn,
        $sql
    );


if ($result) {

    $row =
        mysqli_fetch_assoc(
            $result
        );

    $upcomingTrips =
        (int)$row["total"];

}


/* =========================================================
   MONTHLY REVENUE
========================================================= */

$monthlyRevenue = [];

$monthNames = [
    1 => "Jan",
    2 => "Feb",
    3 => "Mar",
    4 => "Apr",
    5 => "May",
    6 => "Jun",
    7 => "Jul",
    8 => "Aug",
    9 => "Sep",
    10 => "Oct",
    11 => "Nov",
    12 => "Dec"
];


/* Create empty months */

for ($month = 1; $month <= 12; $month++) {

    $monthlyRevenue[$month] = 0;

}


/* Get paid monthly revenue */

$currentYear =
    date("Y");


$sql = "
    SELECT
        MONTH(
            STR_TO_DATE(
                p.exp_date,
                '%m/%y'
            )
        ) AS month_number,

        SUM(
            CASE
                WHEN p.amount > 0
                THEN p.amount
                ELSE 0
            END
        ) AS revenue

    FROM payments p

    WHERE LOWER(p.payment_status) = 'paid'

    GROUP BY month_number
";


$result =
    mysqli_query(
        $conn,
        $sql
    );


/*
    The payment table does not have a payment_date
    field. Therefore the chart below falls back to
    all paid payment totals grouped using available
    payment information only when possible.
*/

if ($result) {

    /*
        Do not use exp_date as a financial date.
        Reset chart to zero and calculate a simpler
        current snapshot below.
    */

}


/*
    Since the existing payments table has no payment
    creation date, use a clean yearly snapshot:
    distribute no false historical values.

    Current month gets the current paid total.
*/

$currentMonth =
    (int)date("n");


$monthlyRevenue[$currentMonth] =
    $totalRevenue;


/* Maximum bar */

$maxRevenue =
    max(
        $monthlyRevenue
    );


if ($maxRevenue <= 0) {

    $maxRevenue = 1;

}


/* =========================================================
   RECENT BOOKINGS
========================================================= */

$recentBookings = [];

$sql = "

    SELECT

        b.id,
        b.travel_date,
        b.persons,
        b.days,
        b.total_price,
        b.status,

        u.name AS customer_name,
        u.email AS customer_email,

        p.title AS package_title,
        p.destination

    FROM booking b

    LEFT JOIN users u
        ON b.user_id = u.id

    LEFT JOIN packages p
        ON b.package_id = p.id

    ORDER BY b.id DESC

    LIMIT 7

";


$result =
    mysqli_query(
        $conn,
        $sql
    );


if ($result) {

    while (
        $row =
        mysqli_fetch_assoc($result)
    ) {

        $recentBookings[] =
            $row;

    }

}


/* =========================================================
   TOP PACKAGES
========================================================= */

$popularPackages = [];

$sql = "

    SELECT

        p.id,
        p.title,
        p.destination,
        p.price,

        COUNT(b.id) AS booking_count

    FROM packages p

    LEFT JOIN booking b
        ON p.id = b.package_id

    GROUP BY
        p.id,
        p.title,
        p.destination,
        p.price

    ORDER BY
        booking_count DESC,
        p.id DESC

    LIMIT 5

";


$result =
    mysqli_query(
        $conn,
        $sql
    );


if ($result) {

    while (
        $row =
        mysqli_fetch_assoc($result)
    ) {

        $popularPackages[] =
            $row;

    }

}


/* =========================================================
   RECENT CUSTOMERS
========================================================= */

$recentCustomers = [];

$sql = "

    SELECT
        id,
        name,
        email

    FROM users

    WHERE role = 'customer'

    ORDER BY id DESC

    LIMIT 5

";


$result =
    mysqli_query(
        $conn,
        $sql
    );


if ($result) {

    while (
        $row =
        mysqli_fetch_assoc($result)
    ) {

        $recentCustomers[] =
            $row;

    }

}


/* =========================================================
   BOOKING STATUS COUNTS
========================================================= */

$statusCounts = [

    "pending" => 0,
    "paid" => 0,
    "confirmed" => 0,
    "cancelled" => 0

];


$sql = "

    SELECT
        LOWER(status) AS booking_status,
        COUNT(*) AS total

    FROM booking

    GROUP BY LOWER(status)

";


$result =
    mysqli_query(
        $conn,
        $sql
    );


if ($result) {

    while (
        $row =
        mysqli_fetch_assoc($result)
    ) {

        $bookingStatus =
            trim(
                $row["booking_status"]
            );


        if (
            isset(
                $statusCounts[
                    $bookingStatus
                ]
            )
        ) {

            $statusCounts[
                $bookingStatus
            ] =
                (int)$row["total"];

        }

    }

}


/* =========================================================
   ADMIN AVATAR
========================================================= */

$adminLetter =
    strtoupper(
        substr(
            $adminName,
            0,
            1
        )
    );

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">


    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">


    <meta
        name="description"
        content="GlobeTrek administration dashboard">


    <title>
        Admin Dashboard | GlobeTrek
    </title>


    <!-- FONT AWESOME -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">


    <!-- ADMIN CSS -->

    <link
        rel="stylesheet"
        href="../css/admin.css?v=100">

</head>


<body>


<div class="admin-app">


    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <aside
        class="admin-sidebar"
        id="adminSidebar">


        <!-- BRAND -->

        <div class="admin-brand">


            <a href="../index.php">

                Globe<span>Trek</span>

            </a>


            <span>
                ADMIN PANEL
            </span>

        </div>



        <!-- MENU -->

        <nav class="admin-nav">


            <div class="nav-section-title">
                MAIN MENU
            </div>


            <a
                href="adashboard.php"
                class="admin-nav-item active">

                <span class="nav-icon">
                    <i class="fa-solid fa-chart-pie"></i>
                </span>

                Dashboard

            </a>


            <a
                href="manage-users.php"
                class="admin-nav-item">

                <span class="nav-icon">
                    <i class="fa-solid fa-users"></i>
                </span>

                Users

            </a>


            <a
                href="manage-packages.php"
                class="admin-nav-item">

                <span class="nav-icon">
                    <i class="fa-solid fa-map-location-dot"></i>
                </span>

                Packages

            </a>


            <a
                href="manage-bookings.php"
                class="admin-nav-item">

                <span class="nav-icon">
                    <i class="fa-solid fa-calendar-check"></i>
                </span>

                Bookings


                <?php if ($pendingBookings > 0): ?>

                    <span class="nav-count">

                        <?php
                        echo $pendingBookings;
                        ?>

                    </span>

                <?php endif; ?>

            </a>


            <a
                href="manage-payments.php"
                class="admin-nav-item">

                <span class="nav-icon">
                    <i class="fa-solid fa-credit-card"></i>
                </span>

                Payments

            </a>


            <div class="nav-section-title second">

                MANAGEMENT

            </div>


            <a
                href="manage_staff.php"
                class="admin-nav-item">

                <span class="nav-icon">
                    <i class="fa-solid fa-user-shield"></i>
                </span>

                Staff

            </a>


            <a
                href="../index.php"
                class="admin-nav-item">

                <span class="nav-icon">
                    <i class="fa-solid fa-globe"></i>
                </span>

                View Website

            </a>


        </nav>



        <!-- SIDEBAR BOTTOM -->

        <div class="sidebar-bottom">


            <div class="sidebar-admin-mini">


                <div class="sidebar-avatar">

                    <?php
                    echo htmlspecialchars(
                        $adminLetter
                    );
                    ?>

                </div>


                <div>

                    <strong>

                        <?php
                        echo htmlspecialchars(
                            $adminName
                        );
                        ?>

                    </strong>


                    <small>
                        Administrator
                    </small>

                </div>

            </div>


            <a
                href="../logout.php"
                class="sidebar-logout">

                <i class="fa-solid fa-arrow-right-from-bracket"></i>

                Logout

            </a>

        </div>

    </aside>



    <!-- =====================================================
         MAIN
    ====================================================== -->

    <div class="admin-main">


        <!-- TOPBAR -->

        <header class="admin-topbar">


            <button
                type="button"
                class="mobile-menu-button"
                id="mobileMenuButton">

                <i class="fa-solid fa-bars"></i>

            </button>


            <div class="topbar-left">


                <span class="topbar-label">
                    OVERVIEW
                </span>


                <h1>
                    Admin Dashboard
                </h1>


                <p>
                    Monitor and manage your GlobeTrek operations.
                </p>

            </div>



            <div class="topbar-right">


                <div class="today-info">

                    <i class="fa-regular fa-calendar"></i>

                    <span>

                        <?php
                        echo htmlspecialchars(
                            $currentDate
                        );
                        ?>

                    </span>

                </div>


                <div class="admin-user">


                    <div class="admin-top-avatar">

                        <?php
                        echo htmlspecialchars(
                            $adminLetter
                        );
                        ?>

                    </div>


                    <div class="admin-user-text">

                        <strong>

                            <?php
                            echo htmlspecialchars(
                                $adminName
                            );
                            ?>

                        </strong>

                        <span>
                            Administrator
                        </span>

                    </div>

                </div>

            </div>

        </header>



        <!-- =================================================
             WELCOME CARD
        ================================================== -->

        <section class="dashboard-welcome">


            <div class="welcome-content">


                <span>
                    GLOBETREK MANAGEMENT
                </span>


                <h2>

                    Welcome back,
                    <?php
                    echo htmlspecialchars(
                        $adminName
                    );
                    ?>

                </h2>


                <p>

                    Here's what's happening
                    across your travel platform today.

                </p>


                <div class="welcome-actions">


                    <a
                        href="manage-packages.php"
                        class="welcome-primary">

                        <i class="fa-solid fa-plus"></i>

                        Manage Packages

                    </a>


                    <a
                        href="manage-bookings.php"
                        class="welcome-secondary">

                        View Bookings

                        <i class="fa-solid fa-arrow-right"></i>

                    </a>

                </div>

            </div>


            <div class="welcome-graphic">


                <div class="graphic-circle circle-one"></div>

                <div class="graphic-circle circle-two"></div>


                <i class="fa-solid fa-plane-departure"></i>

            </div>

        </section>



        <!-- =================================================
             STATS
        ================================================== -->

        <section class="stats-grid">


            <!-- USERS -->

            <article class="admin-stat-card">


                <div
                    class="stat-card-icon users-icon">

                    <i class="fa-solid fa-users"></i>

                </div>


                <div class="stat-card-content">

                    <span>
                        Total Users
                    </span>


                    <strong>

                        <?php
                        echo number_format(
                            $totalUsers
                        );
                        ?>

                    </strong>


                    <small>

                        <?php
                        echo number_format(
                            $totalCustomers
                        );
                        ?>

                        customers

                    </small>

                </div>


                <span class="stat-card-arrow">

                    <i class="fa-solid fa-arrow-up-right-from-square"></i>

                </span>

            </article>



            <!-- BOOKINGS -->

            <article class="admin-stat-card">


                <div
                    class="stat-card-icon bookings-icon">

                    <i class="fa-solid fa-calendar-check"></i>

                </div>


                <div class="stat-card-content">

                    <span>
                        Total Bookings
                    </span>


                    <strong>

                        <?php
                        echo number_format(
                            $totalBookings
                        );
                        ?>

                    </strong>


                    <small>

                        <?php
                        echo number_format(
                            $pendingBookings
                        );
                        ?>

                        pending

                    </small>

                </div>


                <span class="stat-card-arrow">

                    <i class="fa-solid fa-arrow-up-right-from-square"></i>

                </span>

            </article>



            <!-- PACKAGES -->

            <article class="admin-stat-card">


                <div
                    class="stat-card-icon packages-icon">

                    <i class="fa-solid fa-map"></i>

                </div>


                <div class="stat-card-content">

                    <span>
                        Travel Packages
                    </span>


                    <strong>

                        <?php
                        echo number_format(
                            $totalPackages
                        );
                        ?>

                    </strong>


                    <small>

                        <?php
                        echo number_format(
                            $upcomingTrips
                        );
                        ?>

                        upcoming trips

                    </small>

                </div>


                <span class="stat-card-arrow">

                    <i class="fa-solid fa-arrow-up-right-from-square"></i>

                </span>

            </article>



            <!-- REVENUE -->

            <article class="admin-stat-card">


                <div
                    class="stat-card-icon revenue-icon">

                    <i class="fa-solid fa-wallet"></i>

                </div>


                <div class="stat-card-content">

                    <span>
                        Total Revenue
                    </span>


                    <strong class="revenue-value">

                        LKR
                        <?php
                        echo number_format(
                            $totalRevenue,
                            2
                        );
                        ?>

                    </strong>


                    <small>

                        Paid transactions

                    </small>

                </div>


                <span class="stat-card-arrow">

                    <i class="fa-solid fa-arrow-up-right-from-square"></i>

                </span>

            </article>


        </section>



        <!-- =================================================
             MAIN GRID
        ================================================== -->

        <section class="dashboard-main-grid">


            <!-- =================================================
                 RECENT BOOKINGS
            ================================================== -->

            <div class="dashboard-panel recent-panel">


                <div class="panel-header">


                    <div>

                        <span class="panel-label">
                            ACTIVITY
                        </span>


                        <h2>
                            Recent Bookings
                        </h2>

                    </div>


                    <a
                        href="manage-bookings.php"
                        class="panel-link">

                        View All

                        <i class="fa-solid fa-arrow-right"></i>

                    </a>

                </div>



                <?php if (!empty($recentBookings)): ?>


                    <div class="recent-bookings-list">


                        <?php foreach ($recentBookings as $booking): ?>


                            <?php

                            $bookingStatus =
                                strtolower(
                                    trim(
                                        $booking["status"] ?? "pending"
                                    )
                                );


                            $statusClass =
                                "pending";


                            if (
                                $bookingStatus === "paid"
                            ) {

                                $statusClass =
                                    "paid";

                            }
                            elseif (
                                $bookingStatus === "confirmed"
                            ) {

                                $statusClass =
                                    "confirmed";

                            }
                            elseif (
                                $bookingStatus === "cancelled"
                            ) {

                                $statusClass =
                                    "cancelled";

                            }


                            $customerName =
                                $booking["customer_name"]
                                ??
                                "Unknown Customer";


                            $customerLetter =
                                strtoupper(
                                    substr(
                                        $customerName,
                                        0,
                                        1
                                    )
                                );


                            ?>


                            <div
                                class="recent-booking-item">


                                <div class="customer-mini-avatar">

                                    <?php
                                    echo htmlspecialchars(
                                        $customerLetter
                                    );
                                    ?>

                                </div>


                                <div class="booking-item-main">


                                    <strong>

                                        <?php
                                        echo htmlspecialchars(
                                            $customerName
                                        );
                                        ?>

                                    </strong>


                                    <span>

                                        <?php
                                        echo htmlspecialchars(
                                            $booking["package_title"]
                                            ??
                                            "Travel Package"
                                        );
                                        ?>

                                        •

                                        <?php
                                        echo htmlspecialchars(
                                            $booking["destination"]
                                            ??
                                            "Sri Lanka"
                                        );
                                        ?>

                                    </span>

                                </div>


                                <div class="booking-item-date">


                                    <?php

                                    if (
                                        !empty(
                                            $booking["travel_date"]
                                        )
                                    ) {

                                        echo date(
                                            "d M Y",
                                            strtotime(
                                                $booking["travel_date"]
                                            )
                                        );

                                    }
                                    else {

                                        echo "Date not set";

                                    }

                                    ?>

                                </div>


                                <div class="booking-item-amount">

                                    LKR
                                    <?php
                                    echo number_format(
                                        (float)(
                                            $booking["total_price"]
                                            ??
                                            0
                                        ),
                                        2
                                    );
                                    ?>

                                </div>


                                <span
                                    class="admin-status
                                    <?php
                                    echo $statusClass;
                                    ?>">

                                    <?php
                                    echo htmlspecialchars(
                                        ucfirst(
                                            $bookingStatus
                                        )
                                    );
                                    ?>

                                </span>


                            </div>


                        <?php endforeach; ?>


                    </div>


                <?php else: ?>


                    <div class="admin-empty">

                        <i class="fa-solid fa-calendar-xmark"></i>

                        <h3>
                            No bookings yet
                        </h3>

                        <p>
                            New bookings will appear here.
                        </p>

                    </div>


                <?php endif; ?>

            </div>



            <!-- =================================================
                 BOOKING STATUS
            ================================================== -->

            <div class="dashboard-panel status-panel">


                <div class="panel-header">


                    <div>

                        <span class="panel-label">
                            BOOKINGS
                        </span>


                        <h2>
                            Booking Status
                        </h2>

                    </div>

                </div>


                <div class="status-overview">


                    <div class="status-big">

                        <strong>

                            <?php
                            echo number_format(
                                $totalBookings
                            );
                            ?>

                        </strong>

                        <span>
                            Total Bookings
                        </span>

                    </div>


                    <div class="status-bars">


                        <div class="status-bar-row">


                            <div class="status-bar-label">

                                <span>
                                    Paid
                                </span>

                                <strong>
                                    <?php
                                    echo $statusCounts["paid"];
                                    ?>
                                </strong>

                            </div>


                            <div class="status-track">

                                <div
                                    class="status-progress paid-progress"
                                    style="width:
                                        <?php
                                        echo $totalBookings > 0
                                            ? ($statusCounts["paid"] / $totalBookings) * 100
                                            : 0;
                                        ?>%;">
                                </div>

                            </div>

                        </div>



                        <div class="status-bar-row">


                            <div class="status-bar-label">

                                <span>
                                    Pending
                                </span>

                                <strong>
                                    <?php
                                    echo $statusCounts["pending"];
                                    ?>
                                </strong>

                            </div>


                            <div class="status-track">

                                <div
                                    class="status-progress pending-progress"
                                    style="width:
                                        <?php
                                        echo $totalBookings > 0
                                            ? ($statusCounts["pending"] / $totalBookings) * 100
                                            : 0;
                                        ?>%;">
                                </div>

                            </div>

                        </div>



                        <div class="status-bar-row">


                            <div class="status-bar-label">

                                <span>
                                    Confirmed
                                </span>

                                <strong>
                                    <?php
                                    echo $statusCounts["confirmed"];
                                    ?>
                                </strong>

                            </div>


                            <div class="status-track">

                                <div
                                    class="status-progress confirmed-progress"
                                    style="width:
                                        <?php
                                        echo $totalBookings > 0
                                            ? ($statusCounts["confirmed"] / $totalBookings) * 100
                                            : 0;
                                        ?>%;">
                                </div>

                            </div>

                        </div>



                        <div class="status-bar-row">


                            <div class="status-bar-label">

                                <span>
                                    Cancelled
                                </span>

                                <strong>
                                    <?php
                                    echo $statusCounts["cancelled"];
                                    ?>
                                </strong>

                            </div>


                            <div class="status-track">

                                <div
                                    class="status-progress cancelled-progress"
                                    style="width:
                                        <?php
                                        echo $totalBookings > 0
                                            ? ($statusCounts["cancelled"] / $totalBookings) * 100
                                            : 0;
                                        ?>%;">
                                </div>

                            </div>

                        </div>


                    </div>

                </div>


            </div>


        </section>



        <!-- =================================================
             LOWER GRID
        ================================================== -->

        <section class="dashboard-lower-grid">


            <!-- =================================================
                 POPULAR PACKAGES
            ================================================== -->

            <div class="dashboard-panel">


                <div class="panel-header">


                    <div>

                        <span class="panel-label">
                            PERFORMANCE
                        </span>


                        <h2>
                            Popular Packages
                        </h2>

                    </div>


                    <a
                        href="manage-packages.php"
                        class="panel-link">

                        Manage

                        <i class="fa-solid fa-arrow-right"></i>

                    </a>

                </div>



                <?php if (!empty($popularPackages)): ?>


                    <div class="popular-list">


                        <?php

                        $rank = 1;

                        foreach (
                            $popularPackages
                            as $package
                        ):

                        ?>


                            <div class="popular-item">


                                <div class="package-rank">

                                    <?php
                                    echo $rank;
                                    ?>

                                </div>


                                <div class="popular-info">

                                    <strong>

                                        <?php
                                        echo htmlspecialchars(
                                            $package["title"]
                                        );
                                        ?>

                                    </strong>


                                    <span>

                                        <?php
                                        echo htmlspecialchars(
                                            $package["destination"]
                                        );
                                        ?>

                                    </span>

                                </div>


                                <div class="popular-bookings">

                                    <strong>

                                        <?php
                                        echo (int)(
                                            $package["booking_count"]
                                        );
                                        ?>

                                    </strong>


                                    <span>
                                        bookings
                                    </span>

                                </div>


                                <div class="popular-price">

                                    LKR
                                    <?php
                                    echo number_format(
                                        (float)$package["price"],
                                        0
                                    );
                                    ?>

                                </div>

                            </div>


                        <?php

                        $rank++;

                        endforeach;

                        ?>


                    </div>


                <?php else: ?>


                    <div class="admin-empty">

                        <i class="fa-solid fa-map-location-dot"></i>

                        <h3>
                            No packages available
                        </h3>

                    </div>


                <?php endif; ?>

            </div>



            <!-- =================================================
                 RECENT CUSTOMERS
            ================================================== -->

            <div class="dashboard-panel">


                <div class="panel-header">


                    <div>

                        <span class="panel-label">
                            USERS
                        </span>


                        <h2>
                            Recent Customers
                        </h2>

                    </div>


                    <a
                        href="manage-users.php"
                        class="panel-link">

                        View All

                        <i class="fa-solid fa-arrow-right"></i>

                    </a>

                </div>



                <?php if (!empty($recentCustomers)): ?>


                    <div class="customer-list">


                        <?php foreach (
                            $recentCustomers
                            as $customer
                        ): ?>


                            <?php

                            $letter =
                                strtoupper(
                                    substr(
                                        $customer["name"],
                                        0,
                                        1
                                    )
                                );

                            ?>


                            <div class="customer-item">


                                <div class="customer-list-avatar">

                                    <?php
                                    echo htmlspecialchars(
                                        $letter
                                    );
                                    ?>

                                </div>


                                <div class="customer-list-info">

                                    <strong>

                                        <?php
                                        echo htmlspecialchars(
                                            $customer["name"]
                                        );
                                        ?>

                                    </strong>


                                    <span>

                                        <?php
                                        echo htmlspecialchars(
                                            $customer["email"]
                                        );
                                        ?>

                                    </span>

                                </div>


                                <span class="customer-role">

                                    Customer

                                </span>

                            </div>


                        <?php endforeach; ?>


                    </div>


                <?php else: ?>


                    <div class="admin-empty">

                        <i class="fa-solid fa-user-slash"></i>

                        <h3>
                            No customers found
                        </h3>

                    </div>


                <?php endif; ?>

            </div>


        </section>



        <!-- =================================================
             REVENUE SNAPSHOT
        ================================================== -->

        <section class="dashboard-panel revenue-panel">


            <div class="panel-header">


                <div>

                    <span class="panel-label">
                        FINANCE
                    </span>


                    <h2>
                        Revenue Snapshot
                    </h2>

                </div>


                <div class="revenue-panel-value">

                    <span>
                        Paid Revenue
                    </span>


                    <strong>

                        LKR
                        <?php
                        echo number_format(
                            $totalRevenue,
                            2
                        );
                        ?>

                    </strong>

                </div>

            </div>


            <div class="revenue-chart">


                <?php for (
                    $month = 1;
                    $month <= 12;
                    $month++
                ): ?>


                    <?php

                    $barHeight =
                        (
                            $monthlyRevenue[$month]
                            /
                            $maxRevenue
                        )
                        *
                        100;

                    ?>


                    <div class="chart-column">


                        <div class="chart-value">

                            <?php

                            if (
                                $monthlyRevenue[$month] > 0
                            ) {

                                echo "LKR "
                                    .
                                    number_format(
                                        $monthlyRevenue[$month],
                                        0
                                    );

                            }

                            ?>

                        </div>


                        <div class="chart-bar-area">


                            <div
                                class="chart-bar"
                                style="height:
                                    <?php
                                    echo max(
                                        4,
                                        $barHeight
                                    );
                                    ?>%;">

                            </div>


                        </div>


                        <span class="chart-month">

                            <?php
                            echo $monthNames[$month];
                            ?>

                        </span>

                    </div>


                <?php endfor; ?>


            </div>


            <p class="revenue-note">

                <i class="fa-solid fa-circle-info"></i>

                The current payment table does not contain
                a payment-created date, so the chart displays
                the current paid revenue as a platform snapshot.

            </p>

        </section>



        <!-- =================================================
             QUICK ACTIONS
        ================================================== -->

        <section class="quick-admin-actions">


            <a
                href="manage-users.php"
                class="quick-admin-card">

                <div class="quick-admin-icon users">

                    <i class="fa-solid fa-user-plus"></i>

                </div>


                <div>

                    <strong>
                        Manage Users
                    </strong>

                    <span>
                        Customers and accounts
                    </span>

                </div>


                <i class="fa-solid fa-arrow-right"></i>

            </a>



            <a
                href="manage-packages.php"
                class="quick-admin-card">

                <div class="quick-admin-icon packages">

                    <i class="fa-solid fa-map-location-dot"></i>

                </div>


                <div>

                    <strong>
                        Manage Packages
                    </strong>

                    <span>
                        Add and update trips
                    </span>

                </div>


                <i class="fa-solid fa-arrow-right"></i>

            </a>



            <a
                href="manage-bookings.php"
                class="quick-admin-card">

                <div class="quick-admin-icon bookings">

                    <i class="fa-solid fa-calendar-check"></i>

                </div>


                <div>

                    <strong>
                        Manage Bookings
                    </strong>

                    <span>
                        Review customer bookings
                    </span>

                </div>


                <i class="fa-solid fa-arrow-right"></i>

            </a>



            <a
                href="manage-payments.php"
                class="quick-admin-card">

                <div class="quick-admin-icon payments">

                    <i class="fa-solid fa-file-invoice-dollar"></i>

                </div>


                <div>

                    <strong>
                        Manage Payments
                    </strong>

                    <span>
                        Payment records
                    </span>

                </div>


                <i class="fa-solid fa-arrow-right"></i>

            </a>

        </section>


    </div>

</div>



<!-- =========================================================
     MOBILE SIDEBAR SCRIPT
========================================================= -->

<script>

const mobileMenuButton =
    document.getElementById(
        "mobileMenuButton"
    );


const adminSidebar =
    document.getElementById(
        "adminSidebar"
    );


if (
    mobileMenuButton
    &&
    adminSidebar
) {


    mobileMenuButton.addEventListener(
        "click",
        function () {

            adminSidebar.classList.toggle(
                "show"
            );

        }
    );


    document.addEventListener(
        "click",
        function (event) {


            if (
                window.innerWidth <= 900
                &&
                !adminSidebar.contains(
                    event.target
                )
                &&
                !mobileMenuButton.contains(
                    event.target
                )
            ) {

                adminSidebar.classList.remove(
                    "show"
                );

            }

        }
    );

}


</script>


</body>

</html>
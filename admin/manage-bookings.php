<?php

session_start();

include "../includes/db.php";


/* =====================================================
   LOCAL DEBUG
   Remove these 3 lines after everything works.
===================================================== */

ini_set("display_errors", "1");
ini_set("display_startup_errors", "1");
error_reporting(E_ALL);


/* =====================================================
   ADMIN SECURITY
===================================================== */

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


/* =====================================================
   ADMIN DATA
===================================================== */

$adminName =
    $_SESSION["name"] ?? "Administrator";

$adminLetter =
    strtoupper(
        substr(
            $adminName,
            0,
            1
        )
    );


/* =====================================================
   CSRF TOKEN
===================================================== */

if (
    empty(
        $_SESSION["admin_booking_csrf"]
    )
) {

    $_SESSION["admin_booking_csrf"] =
        bin2hex(
            random_bytes(32)
        );

}

$csrfToken =
    $_SESSION["admin_booking_csrf"];


/* =====================================================
   MESSAGE
===================================================== */

$message = "";

$messageType = "";


/* =====================================================
   STATUS OPTIONS
===================================================== */

$statusOptions = [

    "Pending",
    "Paid",
    "Confirmed",
    "Completed",
    "Cancelled"

];


/* =====================================================
   UPDATE BOOKING STATUS
===================================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    &&
    isset($_POST["update_booking"])
) {

    $postedToken =
        $_POST["csrf_token"] ?? "";


    if (
        empty($postedToken)
        ||
        !hash_equals(
            $_SESSION["admin_booking_csrf"],
            $postedToken
        )
    ) {

        $message =
            "Security verification failed.";

        $messageType =
            "error";

    }
    else {

        $bookingId =
            (int)(
                $_POST["booking_id"]
                ?? 0
            );

        $newStatus =
            trim(
                $_POST["status"]
                ?? ""
            );


        if ($bookingId <= 0) {

            $message =
                "Invalid booking ID.";

            $messageType =
                "error";

        }
        elseif (
            !in_array(
                $newStatus,
                $statusOptions,
                true
            )
        ) {

            $message =
                "Invalid booking status.";

            $messageType =
                "error";

        }
        else {

            $sql = "

                UPDATE booking

                SET status = ?

                WHERE id = ?

            ";


            $stmt =
                mysqli_prepare(
                    $conn,
                    $sql
                );


            if (!$stmt) {

                $message =
                    "Database error: unable to prepare update.";

                $messageType =
                    "error";

            }
            else {

                mysqli_stmt_bind_param(
                    $stmt,
                    "si",
                    $newStatus,
                    $bookingId
                );


                if (
                    mysqli_stmt_execute(
                        $stmt
                    )
                ) {

                    $message =
                        "Booking #"
                        . $bookingId
                        . " status updated successfully.";

                    $messageType =
                        "success";


                    $_SESSION["admin_booking_csrf"] =
                        bin2hex(
                            random_bytes(32)
                        );


                    $csrfToken =
                        $_SESSION["admin_booking_csrf"];

                }
                else {

                    $message =
                        "Unable to update booking status.";

                    $messageType =
                        "error";

                }


                mysqli_stmt_close(
                    $stmt
                );

            }

        }

    }

}


/* =====================================================
   DELETE BOOKING
===================================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    &&
    isset($_POST["delete_booking"])
) {

    $postedToken =
        $_POST["csrf_token"] ?? "";


    if (
        empty($postedToken)
        ||
        !hash_equals(
            $_SESSION["admin_booking_csrf"],
            $postedToken
        )
    ) {

        $message =
            "Security verification failed.";

        $messageType =
            "error";

    }
    else {

        $bookingId =
            (int)(
                $_POST["booking_id"]
                ?? 0
            );


        if ($bookingId <= 0) {

            $message =
                "Invalid booking ID.";

            $messageType =
                "error";

        }
        else {

            /*
                First delete related payments.
            */

            mysqli_begin_transaction(
                $conn
            );


            try {


                /* -----------------------------------------
                   DELETE PAYMENT RECORDS
                ----------------------------------------- */

                $paymentSql = "

                    DELETE FROM payments

                    WHERE booking_id = ?

                ";


                $paymentStmt =
                    mysqli_prepare(
                        $conn,
                        $paymentSql
                    );


                if (!$paymentStmt) {

                    throw new Exception(
                        "Payment deletion failed."
                    );

                }


                mysqli_stmt_bind_param(
                    $paymentStmt,
                    "i",
                    $bookingId
                );


                if (
                    !mysqli_stmt_execute(
                        $paymentStmt
                    )
                ) {

                    mysqli_stmt_close(
                        $paymentStmt
                    );

                    throw new Exception(
                        "Payment deletion failed."
                    );

                }


                mysqli_stmt_close(
                    $paymentStmt
                );


                /* -----------------------------------------
                   DELETE BOOKING
                ----------------------------------------- */

                $bookingSql = "

                    DELETE FROM booking

                    WHERE id = ?

                ";


                $bookingStmt =
                    mysqli_prepare(
                        $conn,
                        $bookingSql
                    );


                if (!$bookingStmt) {

                    throw new Exception(
                        "Booking deletion failed."
                    );

                }


                mysqli_stmt_bind_param(
                    $bookingStmt,
                    "i",
                    $bookingId
                );


                if (
                    !mysqli_stmt_execute(
                        $bookingStmt
                    )
                ) {

                    mysqli_stmt_close(
                        $bookingStmt
                    );

                    throw new Exception(
                        "Booking deletion failed."
                    );

                }


                $affectedRows =
                    mysqli_stmt_affected_rows(
                        $bookingStmt
                    );


                mysqli_stmt_close(
                    $bookingStmt
                );


                if ($affectedRows <= 0) {

                    throw new Exception(
                        "Booking not found."
                    );

                }


                mysqli_commit(
                    $conn
                );


                $message =
                    "Booking #"
                    . $bookingId
                    . " deleted successfully.";

                $messageType =
                    "success";


            }
            catch (Exception $e) {

                mysqli_rollback(
                    $conn
                );


                $message =
                    "Unable to delete the booking.";

                $messageType =
                    "error";

            }

        }

    }

}


/* =====================================================
   SEARCH
===================================================== */

$search =
    trim(
        $_GET["search"]
        ?? ""
    );


$statusFilter =
    trim(
        $_GET["status"]
        ?? ""
    );


/* =====================================================
   LOAD BOOKINGS
===================================================== */

$bookings = [];


/*
    IMPORTANT:
    We use separate prepared statements instead
    of dynamic bind_param().
    This avoids PHP reference-related errors.
*/


if (
    $search !== ""
    &&
    $statusFilter !== ""
    &&
    in_array(
        $statusFilter,
        $statusOptions,
        true
    )
) {


    $sql = "

        SELECT

            b.id,
            b.user_id,
            b.package_id,
            b.booking_date,
            b.travel_date,
            b.status,
            b.persons,
            b.days,
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

        WHERE

        (
            CAST(b.id AS CHAR) LIKE ?
            OR
            u.name LIKE ?
            OR
            u.email LIKE ?
            OR
            p.title LIKE ?
            OR
            p.destination LIKE ?
        )

        AND b.status = ?

        ORDER BY b.id DESC

    ";


    $stmt =
        mysqli_prepare(
            $conn,
            $sql
        );


    $searchValue =
        "%" . $search . "%";


    mysqli_stmt_bind_param(
        $stmt,
        "ssssss",
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $statusFilter
    );

}


elseif (
    $search !== ""
) {


    $sql = "

        SELECT

            b.id,
            b.user_id,
            b.package_id,
            b.booking_date,
            b.travel_date,
            b.status,
            b.persons,
            b.days,
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

        WHERE

        (
            CAST(b.id AS CHAR) LIKE ?
            OR
            u.name LIKE ?
            OR
            u.email LIKE ?
            OR
            p.title LIKE ?
            OR
            p.destination LIKE ?
        )

        ORDER BY b.id DESC

    ";


    $stmt =
        mysqli_prepare(
            $conn,
            $sql
        );


    $searchValue =
        "%" . $search . "%";


    mysqli_stmt_bind_param(
        $stmt,
        "sssss",
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue
    );

}


elseif (
    $statusFilter !== ""
    &&
    in_array(
        $statusFilter,
        $statusOptions,
        true
    )
) {


    $sql = "

        SELECT

            b.id,
            b.user_id,
            b.package_id,
            b.booking_date,
            b.travel_date,
            b.status,
            b.persons,
            b.days,
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

        WHERE b.status = ?

        ORDER BY b.id DESC

    ";


    $stmt =
        mysqli_prepare(
            $conn,
            $sql
        );


    mysqli_stmt_bind_param(
        $stmt,
        "s",
        $statusFilter
    );

}


else {


    $sql = "

        SELECT

            b.id,
            b.user_id,
            b.package_id,
            b.booking_date,
            b.travel_date,
            b.status,
            b.persons,
            b.days,
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

    ";


    $stmt =
        mysqli_prepare(
            $conn,
            $sql
        );

}


/* =====================================================
   RUN QUERY
===================================================== */

if (!$stmt) {

    die(
        "Booking query error: "
        .
        htmlspecialchars(
            mysqli_error($conn)
        )
    );

}


mysqli_stmt_execute(
    $stmt
);


$result =
    mysqli_stmt_get_result(
        $stmt
    );


while (
    $row =
    mysqli_fetch_assoc(
        $result
    )
) {

    $bookings[] =
        $row;

}


mysqli_stmt_close(
    $stmt
);


/* =====================================================
   BOOKING COUNTS
===================================================== */

$counts = [

    "total" => 0,
    "pending" => 0,
    "paid" => 0,
    "confirmed" => 0,
    "completed" => 0,
    "cancelled" => 0

];


$sql = "

    SELECT
        LOWER(status) AS booking_status,
        COUNT(*) AS total

    FROM booking

    GROUP BY
        LOWER(status)

";


$result =
    mysqli_query(
        $conn,
        $sql
    );


if ($result) {

    while (
        $row =
        mysqli_fetch_assoc(
            $result
        )
    ) {

        $count =
            (int)$row["total"];


        $counts["total"] +=
            $count;


        $status =
            strtolower(
                trim(
                    $row["booking_status"]
                )
            );


        if (
            isset(
                $counts[$status]
            )
        ) {

            $counts[$status] =
                $count;

        }

    }

}


/* =====================================================
   DATE
===================================================== */

$currentDate =
    date(
        "l, d F Y"
    );

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">


    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">


    <title>
        Manage Bookings | GlobeTrek
    </title>


    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">


    <link
        rel="stylesheet"
        href="../css/manage-booking.css?v=200">

</head>


<body>


<div class="admin-booking-app">


    <!-- =================================================
         SIDEBAR
    ================================================== -->

    <aside
        class="booking-sidebar"
        id="bookingSidebar">


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


            <a href="manage-users.php">

                <i class="fa-solid fa-users"></i>

                Users

            </a>


            <a href="manage-packages.php">

                <i class="fa-solid fa-map-location-dot"></i>

                Packages

            </a>


            <a
                href="manage-bookings.php"
                class="active">

                <i class="fa-solid fa-calendar-check"></i>

                Bookings


                <?php if ($counts["pending"] > 0): ?>

                    <span class="sidebar-count">

                        <?php
                        echo $counts["pending"];
                        ?>

                    </span>

                <?php endif; ?>


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
                id="mobileSidebarButton">

                <i class="fa-solid fa-bars"></i>

            </button>


            <div>

                <span class="topbar-eyebrow">
                    MANAGEMENT
                </span>


                <h1>
                    Bookings
                </h1>


                <p>
                    Review and manage customer travel reservations.
                </p>

            </div>


            <div class="topbar-admin">


                <div class="topbar-avatar">

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


        </header>



        <!-- =================================================
             ALERT
        ================================================== -->

        <?php if ($message !== ""): ?>


            <div class="booking-alert <?php echo $messageType === "success" ? "success" : "error"; ?>">


                <i
                    class="fa-solid
                    <?php
                    echo
                        $messageType === "success"
                        ? "fa-circle-check"
                        : "fa-circle-exclamation";
                    ?>">
                </i>


                <span>

                    <?php
                    echo htmlspecialchars(
                        $message
                    );
                    ?>

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
                    TRAVEL OPERATIONS
                </span>


                <h2>
                    Booking Management
                </h2>


                <p>

                    Track customer reservations,
                    travel dates, guests and booking statuses.

                </p>

            </div>


            <div class="intro-total">


                <span>
                    TOTAL BOOKINGS
                </span>


                <strong>

                    <?php
                    echo number_format(
                        $counts["total"]
                    );
                    ?>

                </strong>

            </div>


        </section>



        <!-- =================================================
             STATUS CARDS
        ================================================== -->

        <section class="booking-stat-grid">


            <div class="booking-stat">

                <div class="booking-stat-icon total">

                    <i class="fa-solid fa-calendar-days"></i>

                </div>


                <div>

                    <span>
                        All Bookings
                    </span>


                    <strong>
                        <?php
                        echo $counts["total"];
                        ?>
                    </strong>

                </div>

            </div>



            <div class="booking-stat">

                <div class="booking-stat-icon pending">

                    <i class="fa-solid fa-clock"></i>

                </div>


                <div>

                    <span>
                        Pending
                    </span>


                    <strong>
                        <?php
                        echo $counts["pending"];
                        ?>
                    </strong>

                </div>

            </div>



            <div class="booking-stat">

                <div class="booking-stat-icon paid">

                    <i class="fa-solid fa-credit-card"></i>

                </div>


                <div>

                    <span>
                        Paid
                    </span>


                    <strong>
                        <?php
                        echo $counts["paid"];
                        ?>
                    </strong>

                </div>

            </div>



            <div class="booking-stat">

                <div class="booking-stat-icon confirmed">

                    <i class="fa-solid fa-circle-check"></i>

                </div>


                <div>

                    <span>
                        Confirmed
                    </span>


                    <strong>
                        <?php
                        echo $counts["confirmed"];
                        ?>
                    </strong>

                </div>

            </div>



            <div class="booking-stat">

                <div class="booking-stat-icon completed">

                    <i class="fa-solid fa-flag-checkered"></i>

                </div>


                <div>

                    <span>
                        Completed
                    </span>


                    <strong>
                        <?php
                        echo $counts["completed"];
                        ?>
                    </strong>

                </div>

            </div>



            <div class="booking-stat">

                <div class="booking-stat-icon cancelled">

                    <i class="fa-solid fa-ban"></i>

                </div>


                <div>

                    <span>
                        Cancelled
                    </span>


                    <strong>
                        <?php
                        echo $counts["cancelled"];
                        ?>
                    </strong>

                </div>

            </div>


        </section>



        <!-- =================================================
             SEARCH
        ================================================== -->

        <section class="booking-filter-card">


            <form
                method="GET"
                class="booking-filter-form">


                <div class="filter-search">


                    <i class="fa-solid fa-magnifying-glass"></i>


                    <input
                        type="text"
                        name="search"
                        value="<?php
                            echo htmlspecialchars(
                                $search
                            );
                        ?>"
                        placeholder="Search customer, package, destination or booking ID...">

                </div>


                <div class="filter-status">


                    <select name="status">


                        <option value="">
                            All statuses
                        </option>


                        <?php foreach (
                            $statusOptions
                            as $option
                        ): ?>


                            <option
                                value="<?php
                                    echo htmlspecialchars(
                                        $option
                                    );
                                ?>"
                                <?php
                                echo
                                    $statusFilter === $option
                                    ? "selected"
                                    : "";
                                ?>>

                                <?php
                                echo htmlspecialchars(
                                    $option
                                );
                                ?>

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


                <?php if (
                    $search !== ""
                    ||
                    $statusFilter !== ""
                ): ?>


                    <a
                        href="manage-bookings.php"
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
                        RESERVATION RECORDS
                    </span>


                    <h2>
                        Customer Bookings
                    </h2>

                </div>


                <div class="result-count">

                    <?php
                    echo count(
                        $bookings
                    );
                    ?>

                    result(s)

                </div>

            </div>



            <?php if (!empty($bookings)): ?>


                <div class="table-scroll">


                    <table class="booking-table">


                        <thead>

                            <tr>

                                <th>
                                    Booking
                                </th>

                                <th>
                                    Customer
                                </th>

                                <th>
                                    Package
                                </th>

                                <th>
                                    Travel Date
                                </th>

                                <th>
                                    Guests
                                </th>

                                <th>
                                    Amount
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


                            <?php foreach (
                                $bookings
                                as $row
                            ): ?>


                                <?php

                                $status =
                                    trim(
                                        $row["status"]
                                        ??
                                        "Pending"
                                    );


                                $statusLower =
                                    strtolower(
                                        $status
                                    );


                                $statusClass =
                                    "pending";


                                if (
                                    $statusLower === "paid"
                                ) {

                                    $statusClass =
                                        "paid";

                                }
                                elseif (
                                    $statusLower === "confirmed"
                                ) {

                                    $statusClass =
                                        "confirmed";

                                }
                                elseif (
                                    $statusLower === "completed"
                                ) {

                                    $statusClass =
                                        "completed";

                                }
                                elseif (
                                    $statusLower === "cancelled"
                                ) {

                                    $statusClass =
                                        "cancelled";

                                }


                                $customerName =
                                    $row["customer_name"]
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


                                $travelDate =
                                    "Not set";


                                if (
                                    !empty(
                                        $row["travel_date"]
                                    )
                                ) {

                                    $travelDate =
                                        date(
                                            "d M Y",
                                            strtotime(
                                                $row["travel_date"]
                                            )
                                        );

                                }


                                ?>


                                <tr>


                                    <!-- BOOKING ID -->

                                    <td>

                                        <div class="booking-id-cell">


                                            <span class="booking-id-icon">

                                                <i class="fa-solid fa-hashtag"></i>

                                            </span>


                                            <div>

                                                <strong>

                                                    <?php
                                                    echo (int)$row["id"];
                                                    ?>

                                                </strong>


                                                <small>

                                                    <?php
                                                    echo (int)(
                                                        $row["booking_date"]
                                                    );
                                                    ?>

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
                                                        $row["customer_email"]
                                                        ??
                                                        "-"
                                                    );
                                                    ?>

                                                </small>

                                            </div>


                                        </div>

                                    </td>



                                    <!-- PACKAGE -->

                                    <td>

                                        <div class="package-cell">


                                            <strong>

                                                <?php
                                                echo htmlspecialchars(
                                                    $row["package_title"]
                                                    ??
                                                    "Package unavailable"
                                                );
                                                ?>

                                            </strong>


                                            <span>

                                                <i class="fa-solid fa-location-dot"></i>

                                                <?php
                                                echo htmlspecialchars(
                                                    $row["package_destination"]
                                                    ??
                                                    "Sri Lanka"
                                                );
                                                ?>

                                            </span>


                                        </div>

                                    </td>



                                    <!-- TRAVEL DATE -->

                                    <td>

                                        <div class="travel-date-cell">


                                            <i class="fa-regular fa-calendar-days"></i>


                                            <div>

                                                <strong>

                                                    <?php
                                                    echo htmlspecialchars(
                                                        $travelDate
                                                    );
                                                    ?>

                                                </strong>


                                                <small>

                                                    <?php
                                                    echo (int)$row["days"];
                                                    ?>

                                                    <?php
                                                    echo
                                                        (
                                                            (int)$row["days"]
                                                            ===
                                                            1
                                                        )
                                                        ? " day"
                                                        : " days";
                                                    ?>

                                                </small>


                                            </div>


                                        </div>

                                    </td>



                                    <!-- GUESTS -->

                                    <td>

                                        <span class="guest-pill">

                                            <i class="fa-solid fa-users"></i>

                                            <?php
                                            echo (int)(
                                                $row["persons"]
                                            );
                                            ?>

                                        </span>

                                    </td>



                                    <!-- AMOUNT -->

                                    <td>

                                        <strong class="amount-cell">

                                            LKR
                                            <?php
                                            echo number_format(
                                                (float)(
                                                    $row["total_price"]
                                                    ??
                                                    0
                                                ),
                                                2
                                            );
                                            ?>

                                        </strong>

                                    </td>



                                    <!-- STATUS -->

                                    <td>

                                        <span
                                            class="
                                                booking-status
                                                <?php
                                                echo $statusClass;
                                                ?>">

                                            <i
                                                class="fa-solid
                                                <?php

                                                if (
                                                    $statusLower ===
                                                    "paid"
                                                ) {

                                                    echo "fa-credit-card";

                                                }
                                                elseif (
                                                    $statusLower ===
                                                    "confirmed"
                                                ) {

                                                    echo "fa-circle-check";

                                                }
                                                elseif (
                                                    $statusLower ===
                                                    "completed"
                                                ) {

                                                    echo "fa-flag-checkered";

                                                }
                                                elseif (
                                                    $statusLower ===
                                                    "cancelled"
                                                ) {

                                                    echo "fa-ban";

                                                }
                                                else {

                                                    echo "fa-clock";

                                                }

                                                ?>">
                                            </i>


                                            <?php
                                            echo htmlspecialchars(
                                                $status
                                            );
                                            ?>

                                        </span>

                                    </td>



                                    <!-- ACTIONS -->

                                    <td>


                                        <div class="booking-actions">


                                            <button
                                                type="button"
                                                class="action-button update"
                                                onclick="openUpdateModal(
                                                    <?php
                                                    echo (int)$row["id"];
                                                    ?>,
                                                    '<?php
                                                    echo htmlspecialchars(
                                                        $status,
                                                        ENT_QUOTES
                                                    );
                                                    ?>'
                                                )">

                                                <i class="fa-solid fa-pen"></i>

                                                Update

                                            </button>


                                            <button
                                                type="button"
                                                class="action-button delete"
                                                onclick="openDeleteModal(
                                                    <?php
                                                    echo (int)$row["id"];
                                                    ?>,
                                                    '<?php
                                                    echo htmlspecialchars(
                                                        $customerName,
                                                        ENT_QUOTES
                                                    );
                                                    ?>'
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


                <div class="empty-bookings">


                    <div class="empty-icon">

                        <i class="fa-solid fa-calendar-xmark"></i>

                    </div>


                    <h3>
                        No bookings found
                    </h3>


                    <p>

                        No bookings match your current
                        search or status filter.

                    </p>


                    <a
                        href="manage-bookings.php"
                        class="empty-button">

                        View All Bookings

                    </a>


                </div>


            <?php endif; ?>


        </section>


    </main>

</div>



<!-- =====================================================
     UPDATE MODAL
====================================================== -->

<div
    class="admin-modal"
    id="updateModal">


    <div class="modal-card">


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
            BOOKING MANAGEMENT
        </span>


        <h2>
            Update Status
        </h2>


        <p>

            Update the status of
            <strong id="updateBookingLabel">
                Booking #0
            </strong>.

        </p>


        <form method="POST">


            <input
                type="hidden"
                name="csrf_token"
                value="<?php
                    echo htmlspecialchars(
                        $csrfToken
                    );
                ?>">


            <input
                type="hidden"
                name="booking_id"
                id="updateBookingId">


            <label
                for="updateStatus">

                Booking Status

            </label>


            <div class="modal-select">


                <i class="fa-solid fa-list-check"></i>


                <select
                    name="status"
                    id="updateStatus"
                    required>


                    <?php foreach (
                        $statusOptions
                        as $option
                    ): ?>


                        <option
                            value="<?php
                                echo htmlspecialchars(
                                    $option
                                );
                            ?>">

                            <?php
                            echo htmlspecialchars(
                                $option
                            );
                            ?>

                        </option>


                    <?php endforeach; ?>


                </select>


            </div>


            <button
                type="submit"
                name="update_booking"
                class="modal-primary-button">

                <i class="fa-solid fa-check"></i>

                Save Changes

            </button>


        </form>


    </div>


</div>



<!-- =====================================================
     DELETE MODAL
====================================================== -->

<div
    class="admin-modal"
    id="deleteModal">


    <div class="modal-card">


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
            Delete Booking?
        </h2>


        <p>

            This will permanently delete
            <strong id="deleteCustomerLabel">
                this booking
            </strong>
            and related payment records.

        </p>


        <form method="POST">


            <input
                type="hidden"
                name="csrf_token"
                value="<?php
                    echo htmlspecialchars(
                        $csrfToken
                    );
                ?>">


            <input
                type="hidden"
                name="booking_id"
                id="deleteBookingId">


            <div class="delete-actions">


                <button
                    type="button"
                    class="cancel-modal-button"
                    onclick="closeDeleteModal()">

                    Cancel

                </button>


                <button
                    type="submit"
                    name="delete_booking"
                    class="confirm-delete-button">

                    <i class="fa-solid fa-trash"></i>

                    Delete

                </button>


            </div>


        </form>


    </div>


</div>



<script>


/* =====================================================
   MOBILE SIDEBAR
===================================================== */

const mobileSidebarButton =
    document.getElementById(
        "mobileSidebarButton"
    );


const bookingSidebar =
    document.getElementById(
        "bookingSidebar"
    );


if (
    mobileSidebarButton
    &&
    bookingSidebar
) {

    mobileSidebarButton.addEventListener(
        "click",
        function () {

            bookingSidebar.classList.toggle(
                "show"
            );

        }
    );

}


/* =====================================================
   UPDATE MODAL
===================================================== */

const updateModal =
    document.getElementById(
        "updateModal"
    );


const updateBookingId =
    document.getElementById(
        "updateBookingId"
    );


const updateBookingLabel =
    document.getElementById(
        "updateBookingLabel"
    );


const updateStatus =
    document.getElementById(
        "updateStatus"
    );


function openUpdateModal(
    bookingId,
    currentStatus
) {


    updateBookingId.value =
        bookingId;


    updateBookingLabel.textContent =
        "Booking #" +
        bookingId;


    updateStatus.value =
        currentStatus;


    updateModal.classList.add(
        "show"
    );

}


function closeUpdateModal() {

    updateModal.classList.remove(
        "show"
    );

}


/* =====================================================
   DELETE MODAL
===================================================== */

const deleteModal =
    document.getElementById(
        "deleteModal"
    );


const deleteBookingId =
    document.getElementById(
        "deleteBookingId"
    );


const deleteCustomerLabel =
    document.getElementById(
        "deleteCustomerLabel"
    );


function openDeleteModal(
    bookingId,
    customerName
) {


    deleteBookingId.value =
        bookingId;


    deleteCustomerLabel.textContent =
        "Booking #" +
        bookingId +
        " for " +
        customerName;


    deleteModal.classList.add(
        "show"
    );

}


function closeDeleteModal() {

    deleteModal.classList.remove(
        "show"
    );

}


/* =====================================================
   MODAL OUTSIDE CLICK
===================================================== */

window.addEventListener(
    "click",
    function (event) {


        if (
            event.target ===
            updateModal
        ) {

            closeUpdateModal();

        }


        if (
            event.target ===
            deleteModal
        ) {

            closeDeleteModal();

        }

    }
);


/* =====================================================
   ESCAPE
===================================================== */

document.addEventListener(
    "keydown",
    function (event) {

        if (
            event.key ===
            "Escape"
        ) {

            closeUpdateModal();

            closeDeleteModal();

        }

    }
);

</script>


</body>

</html>
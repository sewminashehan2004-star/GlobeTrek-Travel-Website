<?php

include "../includes/customer_auth.php";
include "../includes/db.php";

$basePath = "../";
$activePage = "dashboard";

$userId = (int)$_SESSION["user_id"];


/* ==========================================
   USER DETAILS
========================================== */

$sql = "
    SELECT
        id,
        name,
        email,
        phone,
        location,
        bio,
        profile_image

    FROM users

    WHERE id = ?

      AND role = 'customer'

    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $userId
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$user = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


if (!$user) {

    session_destroy();

    header("Location: ../login.php");

    exit();

}


/* ==========================================
   TOTAL BOOKINGS
========================================== */

$totalBookings = 0;

$sql = "
    SELECT COUNT(*) AS total

    FROM booking

    WHERE user_id = ?
";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $userId
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$row = mysqli_fetch_assoc($result);

$totalBookings = (int)$row["total"];

mysqli_stmt_close($stmt);


/* ==========================================
   PENDING BOOKINGS
========================================== */

$pendingBookings = 0;

$sql = "
    SELECT COUNT(*) AS total

    FROM booking

    WHERE user_id = ?

      AND LOWER(status) = 'pending'
";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $userId
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$row = mysqli_fetch_assoc($result);

$pendingBookings = (int)$row["total"];

mysqli_stmt_close($stmt);


/* ==========================================
   PAID BOOKINGS
========================================== */

$paidBookings = 0;

$sql = "
    SELECT COUNT(*) AS total

    FROM booking

    WHERE user_id = ?

      AND LOWER(status) = 'paid'
";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $userId
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$row = mysqli_fetch_assoc($result);

$paidBookings = (int)$row["total"];

mysqli_stmt_close($stmt);


/* ==========================================
   TOTAL SPENT
========================================== */

$totalSpent = 0;

$sql = "
    SELECT COALESCE(SUM(amount), 0) AS total

    FROM payments

    WHERE user_id = ?

      AND LOWER(payment_status) = 'paid'
";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $userId
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$row = mysqli_fetch_assoc($result);

$totalSpent = (float)$row["total"];

mysqli_stmt_close($stmt);


/* ==========================================
   RECENT BOOKINGS
========================================== */

$recentBookings = [];

$sql = "
    SELECT
        b.id,
        b.travel_date,
        b.persons,
        b.days,
        b.total_price,
        b.status,

        p.title,
        p.destination,
        p.image

    FROM booking b

    LEFT JOIN packages p
        ON b.package_id = p.id

    WHERE b.user_id = ?

    ORDER BY b.id DESC

    LIMIT 5
";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $userId
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {

    $recentBookings[] = $row;

}

mysqli_stmt_close($stmt);


/* ==========================================
   PROFILE
========================================== */

$profileImage = "";

if (!empty($user["profile_image"])) {

    $profileImage =
        "../uploads/profile/" .
        basename($user["profile_image"]);

}

$firstLetter =
    strtoupper(
        substr(
            $user["name"],
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

    <title>
        Dashboard | GlobeTrek
    </title>


    <!-- SHARED CSS -->

    <link
        rel="stylesheet"
        href="../css/site.css">


    <!-- DASHBOARD CSS -->

    <link
        rel="stylesheet"
        href="../css/cdashboard.css">


    <!-- ICONS -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

</head>


<body>


<?php include "../includes/navbar.php"; ?>


<!-- ==================================================
     DASHBOARD HERO
================================================== -->

<section class="dashboard-hero">

    <div class="dashboard-hero-overlay"></div>


    <div class="dashboard-hero-content">


        <div class="welcome-text">

            <span class="welcome-small">

                CUSTOMER DASHBOARD

            </span>


            <h1>

                Welcome back,
                <?php
                echo htmlspecialchars(
                    $user["name"]
                );
                ?>

            </h1>


            <p>

                Manage your bookings, discover new
                destinations and plan your next adventure
                with GlobeTrek.

            </p>

        </div>


        <a
            href="packages.php"
            class="hero-book-button">

            <i class="fa-solid fa-compass"></i>

            Explore Packages

        </a>

    </div>

</section>



<!-- ==================================================
     DASHBOARD CONTENT
================================================== -->

<main class="dashboard-container">


    <!-- PROFILE -->

    <section class="profile-summary">


        <div class="profile-left">


            <div class="dashboard-avatar">

                <?php if (!empty($profileImage)): ?>

                    <img
                        src="<?php echo htmlspecialchars($profileImage); ?>"
                        alt="Profile">

                <?php else: ?>

                    <span>

                        <?php
                        echo htmlspecialchars(
                            $firstLetter
                        );
                        ?>

                    </span>

                <?php endif; ?>

            </div>


            <div class="profile-information">

                <h2>

                    <?php
                    echo htmlspecialchars(
                        $user["name"]
                    );
                    ?>

                </h2>


                <p>

                    <i class="fa-solid fa-envelope"></i>

                    <?php
                    echo htmlspecialchars(
                        $user["email"]
                    );
                    ?>

                </p>


                <p>

                    <i class="fa-solid fa-location-dot"></i>

                    <?php
                    echo !empty($user["location"])
                        ? htmlspecialchars($user["location"])
                        : "Location not added";
                    ?>

                </p>

            </div>

        </div>


        <a
            href="profile.php"
            class="edit-profile-button">

            <i class="fa-solid fa-pen"></i>

            Edit Profile

        </a>

    </section>



    <!-- STATISTICS -->

    <section class="stats-grid">


        <div class="stat-card">

            <div class="stat-icon booking-icon">

                <i class="fa-solid fa-calendar-check"></i>

            </div>

            <div class="stat-content">

                <span>
                    Total Bookings
                </span>

                <strong>
                    <?php echo $totalBookings; ?>
                </strong>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon pending-icon">

                <i class="fa-solid fa-clock"></i>

            </div>

            <div class="stat-content">

                <span>
                    Pending
                </span>

                <strong>
                    <?php echo $pendingBookings; ?>
                </strong>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon paid-icon">

                <i class="fa-solid fa-circle-check"></i>

            </div>

            <div class="stat-content">

                <span>
                    Paid Bookings
                </span>

                <strong>
                    <?php echo $paidBookings; ?>
                </strong>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon money-icon">

                <i class="fa-solid fa-wallet"></i>

            </div>

            <div class="stat-content">

                <span>
                    Total Spent
                </span>

                <strong>

                    LKR
                    <?php
                    echo number_format(
                        $totalSpent,
                        2
                    );
                    ?>

                </strong>

            </div>

        </div>

    </section>



    <!-- QUICK ACTIONS -->

    <section class="dashboard-section">

        <div class="section-heading">

            <div>

                <span class="section-label">
                    QUICK ACTIONS
                </span>

                <h2>
                    Manage Your Travel
                </h2>

            </div>

        </div>


        <div class="quick-actions">


            <a
                href="packages.php"
                class="quick-card">

                <div class="quick-icon">

                    <i class="fa-solid fa-map"></i>

                </div>


                <div class="quick-content">

                    <h3>
                        Explore Packages
                    </h3>

                    <p>
                        Find destinations and
                        travel experiences.
                    </p>

                </div>


                <i class="fa-solid fa-arrow-right quick-arrow"></i>

            </a>


            <a
                href="my-bookings.php"
                class="quick-card">

                <div class="quick-icon">

                    <i class="fa-solid fa-calendar-days"></i>

                </div>


                <div class="quick-content">

                    <h3>
                        My Bookings
                    </h3>

                    <p>
                        View and manage your
                        travel bookings.
                    </p>

                </div>


                <i class="fa-solid fa-arrow-right quick-arrow"></i>

            </a>


            <a
                href="profile.php"
                class="quick-card">

                <div class="quick-icon">

                    <i class="fa-solid fa-user"></i>

                </div>


                <div class="quick-content">

                    <h3>
                        My Profile
                    </h3>

                    <p>
                        Update your personal
                        travel information.
                    </p>

                </div>


                <i class="fa-solid fa-arrow-right quick-arrow"></i>

            </a>

        </div>

    </section>



    <!-- RECENT BOOKINGS -->

    <section class="dashboard-section">


        <div class="section-heading">

            <div>

                <span class="section-label">
                    RECENT ACTIVITY
                </span>

                <h2>
                    Recent Bookings
                </h2>

            </div>


            <a
                href="my-bookings.php"
                class="view-all-button">

                View All

                <i class="fa-solid fa-arrow-right"></i>

            </a>

        </div>


        <?php if (!empty($recentBookings)): ?>


            <div class="booking-table-wrapper">

                <div class="booking-table">


                    <div class="booking-row booking-header">

                        <div>
                            Package
                        </div>

                        <div>
                            Travel Date
                        </div>

                        <div>
                            Guests
                        </div>

                        <div>
                            Amount
                        </div>

                        <div>
                            Status
                        </div>

                        <div>
                            Action
                        </div>

                    </div>


                    <?php foreach ($recentBookings as $booking): ?>


                        <?php

                        $status =
                            strtolower(
                                trim(
                                    $booking["status"] ?? "pending"
                                )
                            );


                        $statusClass = "pending";


                        if ($status === "paid") {

                            $statusClass = "paid";

                        }
                        elseif ($status === "confirmed") {

                            $statusClass = "confirmed";

                        }
                        elseif ($status === "cancelled") {

                            $statusClass = "cancelled";

                        }

                        ?>


                        <div class="booking-row">


                            <div class="package-cell">


                                <img
                                    src="../images/<?php
                                        echo htmlspecialchars(
                                            basename(
                                                $booking["image"] ?? "kandy.jpg"
                                            )
                                        );
                                    ?>"
                                    alt="Package"
                                    onerror="this.src='../images/kandy.jpg';">


                                <div>

                                    <strong>

                                        <?php
                                        echo htmlspecialchars(
                                            $booking["title"] ??
                                            "Travel Package"
                                        );
                                        ?>

                                    </strong>


                                    <span>

                                        <?php
                                        echo htmlspecialchars(
                                            $booking["destination"] ??
                                            "Sri Lanka"
                                        );
                                        ?>

                                    </span>

                                </div>

                            </div>


                            <div class="date-cell">

                                <?php

                                echo !empty(
                                    $booking["travel_date"]
                                )
                                ? date(
                                    "d M Y",
                                    strtotime(
                                        $booking["travel_date"]
                                    )
                                )
                                : "Not set";

                                ?>

                            </div>


                            <div class="guests-cell">

                                <?php
                                echo (int)$booking["persons"];
                                ?>

                                <?php
                                echo
                                    ((int)$booking["persons"] === 1)
                                    ? " Person"
                                    : " People";
                                ?>

                            </div>


                            <div class="amount-cell">

                                LKR
                                <?php
                                echo number_format(
                                    (float)$booking["total_price"],
                                    2
                                );
                                ?>

                            </div>


                            <div>

                                <span
                                    class="status-badge <?php echo $statusClass; ?>">

                                    <?php if ($status === "paid"): ?>

                                        <i class="fa-solid fa-check"></i>

                                    <?php elseif ($status === "confirmed"): ?>

                                        <i class="fa-solid fa-check"></i>

                                    <?php elseif ($status === "cancelled"): ?>

                                        <i class="fa-solid fa-xmark"></i>

                                    <?php else: ?>

                                        <i class="fa-solid fa-clock"></i>

                                    <?php endif; ?>


                                    <?php
                                    echo htmlspecialchars(
                                        ucfirst($status)
                                    );
                                    ?>

                                </span>

                            </div>


                            <div>

                                <a
                                    href="booking-details.php?id=<?php echo (int)$booking["id"]; ?>"
                                    class="details-button">

                                    Details

                                    <i class="fa-solid fa-chevron-right"></i>

                                </a>

                            </div>

                        </div>


                    <?php endforeach; ?>

                </div>

            </div>


        <?php else: ?>


            <div class="empty-bookings">

                <div class="empty-icon">

                    <i class="fa-solid fa-plane-departure"></i>

                </div>


                <h3>
                    No bookings yet
                </h3>


                <p>

                    Your next adventure is waiting.
                    Explore our packages and start planning
                    your journey.

                </p>


                <a
                    href="packages.php"
                    class="empty-button">

                    Explore Packages

                    <i class="fa-solid fa-arrow-right"></i>

                </a>

            </div>

        <?php endif; ?>

    </section>



    <!-- CTA -->

    <section class="travel-cta">


        <div class="travel-cta-content">

            <span>
                YOUR NEXT ADVENTURE
            </span>


            <h2>
                Discover more of Sri Lanka
            </h2>


            <p>

                From mountains to beaches,
                explore unforgettable destinations
                with GlobeTrek.

            </p>


            <a href="packages.php">

                Explore Trips

                <i class="fa-solid fa-arrow-right"></i>

            </a>

        </div>

    </section>


</main>


<?php include "../includes/footer.php"; ?>


</body>

</html>
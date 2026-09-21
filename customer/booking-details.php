<?php

include "../includes/customer_auth.php";
include "../includes/db.php";

$basePath = "../";
$activePage = "bookings";

$userId = (int) $_SESSION["user_id"];

$bookingId =
    isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;

if ($bookingId <= 0) {
    header("Location: my-bookings.php");
    exit();
}


$sql = "
    SELECT
        b.id,
        b.user_id,
        b.package_id,
        b.travel_date,
        b.status,
        b.persons,
        b.days,
        b.total_price,
        p.title,
        p.destination,
        p.description,
        p.image,
        p.duration,
        (
            SELECT py.payment_status
            FROM payments py
            WHERE py.booking_id = b.id
            ORDER BY py.id DESC
            LIMIT 1
        ) AS payment_status
    FROM booking b
    INNER JOIN packages p
        ON b.package_id = p.id
    WHERE b.id = ?
      AND b.user_id = ?
    LIMIT 1
";

$stmt = mysqli_prepare(
    $conn,
    $sql
);

if (!$stmt) {
    die("Unable to load booking details.");
}

mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $bookingId,
    $userId
);

mysqli_stmt_execute($stmt);

$result =
    mysqli_stmt_get_result(
        $stmt
    );

$booking =
    mysqli_fetch_assoc(
        $result
    );

mysqli_stmt_close($stmt);

if (!$booking) {
    die("Booking not found.");
}

$paymentStatus =
    $booking["payment_status"] ?? "Unpaid";

$travelDate =
    !empty($booking["travel_date"])
    ? date(
        "d F Y",
        strtotime(
            $booking["travel_date"]
        )
    )
    : "Date not set";

$imageName =
    basename(
        $booking["image"]
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
        Booking #<?php echo $bookingId; ?> | GlobeTrek
    </title>

    <link
        rel="stylesheet"
        href="../css/site.css?v=30">

    <link
        rel="stylesheet"
        href="../css/booking-details.css?v=1">

</head>


<body>


<?php include "../includes/navbar.php"; ?>


<main class="booking-details-page">

    <div class="booking-details-wrapper">


        <div class="details-back">

            <a href="my-bookings.php">
                ← Back to My Bookings
            </a>

        </div>


        <div class="details-header">

            <span>
                BOOKING #<?php echo $bookingId; ?>
            </span>

            <h1>
                <?php echo htmlspecialchars($booking["title"]); ?>
            </h1>

            <p>
                <?php echo htmlspecialchars($booking["destination"]); ?>
            </p>

        </div>


        <div class="details-grid">


            <section class="details-image-card">

                <img
                    src="../images/<?php echo htmlspecialchars($imageName); ?>"
                    alt="<?php echo htmlspecialchars($booking["title"]); ?>"
                    onerror="this.src='../images/kandy.jpg';">

                <div class="details-image-content">

                    <span>
                        <?php echo htmlspecialchars($booking["duration"]); ?>
                    </span>

                    <h2>
                        Your travel experience
                    </h2>

                    <p>
                        <?php echo htmlspecialchars($booking["description"]); ?>
                    </p>

                </div>

            </section>


            <section class="details-info-card">

                <div class="detail-status-row">

                    <div>

                        <small>
                            Booking Status
                        </small>

                        <strong>
                            <?php echo htmlspecialchars($booking["status"]); ?>
                        </strong>

                    </div>


                    <div>

                        <small>
                            Payment
                        </small>

                        <strong>
                            <?php echo htmlspecialchars($paymentStatus); ?>
                        </strong>

                    </div>

                </div>


                <div class="details-data-grid">

                    <div>

                        <small>
                            Travel Date
                        </small>

                        <strong>
                            <?php echo htmlspecialchars($travelDate); ?>
                        </strong>

                    </div>


                    <div>

                        <small>
                            Number of Guests
                        </small>

                        <strong>
                            <?php echo (int) $booking["persons"]; ?>
                        </strong>

                    </div>


                    <div>

                        <small>
                            Number of Days
                        </small>

                        <strong>
                            <?php echo (int) $booking["days"]; ?>
                        </strong>

                    </div>


                    <div>

                        <small>
                            Total Price
                        </small>

                        <strong>
                            Rs.
                            <?php
                            echo number_format(
                                (float) $booking["total_price"],
                                2
                            );
                            ?>
                        </strong>

                    </div>

                </div>


                <div class="details-total">

                    <span>
                        Total amount
                    </span>

                    <strong>
                        Rs.
                        <?php
                        echo number_format(
                            (float) $booking["total_price"],
                            2
                        );
                        ?>
                    </strong>

                </div>


                <div class="details-actions">

                    <?php if (
                        strtolower(
                            $paymentStatus
                        ) !== "paid"
                        &&
                        strtolower(
                            $booking["status"]
                        ) !== "cancelled"
                    ) { ?>

                        <a
                            href="payment.php?booking_id=<?php echo $bookingId; ?>"
                            class="primary-action">

                            Continue to Payment →

                        </a>

                    <?php } ?>


                    <a
                        href="packages.php"
                        class="secondary-action">

                        Explore More Packages

                    </a>

                </div>

            </section>

        </div>

    </div>

</main>


<?php include "../includes/footer.php"; ?>


</body>
</html>

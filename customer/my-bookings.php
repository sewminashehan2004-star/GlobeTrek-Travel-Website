<?php

include "../includes/customer_auth.php";
include "../includes/db.php";

$basePath = "../";
$activePage = "bookings";

$userId = (int) $_SESSION["user_id"];

$bookings = [];

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
        p.image,
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
    WHERE b.user_id = ?
    ORDER BY b.id DESC
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Unable to load your bookings.");
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $userId
);

mysqli_stmt_execute($stmt);

$result =
    mysqli_stmt_get_result(
        $stmt
    );

while ($row = mysqli_fetch_assoc($result)) {
    $bookings[] = $row;
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
        My Bookings | GlobeTrek
    </title>

    <link
        rel="stylesheet"
        href="../css/site.css?v=30">

    <link
        rel="stylesheet"
        href="../css/my-bookings.css?v=1">

</head>


<body>


<?php include "../includes/navbar.php"; ?>


<section class="bookings-hero">

    <div class="bookings-hero-overlay"></div>

    <div class="bookings-hero-content">

        <span>
            YOUR TRAVEL JOURNEY
        </span>

        <h1>
            My Bookings
        </h1>

        <p>
            View your upcoming trips, payment status and booking details.
        </p>

    </div>

</section>


<main class="bookings-page">

    <div class="bookings-wrapper">


        <div class="booking-tools">

            <div>

                <span>
                    TRAVEL HISTORY
                </span>

                <h2>
                    Your bookings
                </h2>

            </div>


            <a
                href="packages.php"
                class="new-booking-button">

                + Explore Packages

            </a>

        </div>


        <?php if (count($bookings) > 0) { ?>


            <div class="booking-list" id="bookingList">


                <?php foreach ($bookings as $booking) { ?>

                    <?php

                    $status =
                        strtolower(
                            trim(
                                $booking["status"] ?? "pending"
                            )
                        );

                    $paymentStatus =
                        strtolower(
                            trim(
                                $booking["payment_status"] ?? "unpaid"
                            )
                        );

                    $badgeClass = "pending";

                    if (
                        $status === "paid"
                        ||
                        $status === "confirmed"
                    ) {
                        $badgeClass = "paid";
                    }

                    if ($status === "cancelled") {
                        $badgeClass = "cancelled";
                    }

                    $travelDateText =
                        !empty($booking["travel_date"])
                        ? date(
                            "d M Y",
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


                    <article
                        class="booking-card-item"
                        data-search="<?php
                            echo htmlspecialchars(
                                strtolower(
                                    $booking["title"]
                                    . " "
                                    . $booking["destination"]
                                    . " "
                                    . $status
                                )
                            );
                        ?>">


                        <div class="booking-card-image">

                            <img
                                src="../images/<?php echo htmlspecialchars($imageName); ?>"
                                alt="<?php echo htmlspecialchars($booking["title"]); ?>"
                                onerror="this.src='../images/kandy.jpg';">

                        </div>


                        <div class="booking-card-content">

                            <div class="booking-top">

                                <div>

                                    <span class="booking-id">
                                        Booking #<?php echo (int) $booking["id"]; ?>
                                    </span>

                                    <h3>
                                        <?php echo htmlspecialchars($booking["title"]); ?>
                                    </h3>

                                    <p>
                                        📍 <?php echo htmlspecialchars($booking["destination"]); ?>
                                    </p>

                                </div>


                                <span class="status-badge <?php echo $badgeClass; ?>">

                                    <?php
                                    echo htmlspecialchars(
                                        ucfirst(
                                            $status
                                        )
                                    );
                                    ?>

                                </span>

                            </div>


                            <div class="booking-info-grid">

                                <div>

                                    <small>
                                        Travel Date
                                    </small>

                                    <strong>
                                        <?php echo htmlspecialchars($travelDateText); ?>
                                    </strong>

                                </div>


                                <div>

                                    <small>
                                        Guests
                                    </small>

                                    <strong>
                                        <?php echo (int) $booking["persons"]; ?>
                                    </strong>

                                </div>


                                <div>

                                    <small>
                                        Days
                                    </small>

                                    <strong>
                                        <?php echo (int) $booking["days"]; ?>
                                    </strong>

                                </div>


                                <div>

                                    <small>
                                        Total
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


                            <div class="booking-card-footer">

                                <span class="payment-state">

                                    Payment:
                                    <strong>
                                        <?php
                                        echo htmlspecialchars(
                                            ucfirst(
                                                $paymentStatus
                                            )
                                        );
                                        ?>
                                    </strong>

                                </span>


                                <div class="booking-actions">

                                    <?php if ($paymentStatus !== "paid" && $status !== "cancelled") { ?>

                                        <a
                                            href="payment.php?booking_id=<?php echo (int) $booking["id"]; ?>"
                                            class="pay-small-button">

                                            Pay Now

                                        </a>

                                    <?php } ?>


                                    <a
                                        href="booking-details.php?id=<?php echo (int) $booking["id"]; ?>"
                                        class="details-small-button">

                                        View Details →

                                    </a>

                                </div>

                            </div>

                        </div>

                    </article>


                <?php } ?>

            </div>


        <?php } else { ?>


            <div class="empty-bookings">

                <div class="empty-bookings-icon">
                    ✈
                </div>

                <h3>
                    No bookings yet
                </h3>

                <p>
                    Start planning your next Sri Lankan adventure
                    by exploring our travel packages.
                </p>

                <a
                    href="packages.php"
                    class="new-booking-button">

                    Explore Packages →

                </a>

            </div>


        <?php } ?>


    </div>

</main>


<?php include "../includes/footer.php"; ?>


<script>

function createSearchBox() {

    const wrapper =
        document.querySelector(".booking-tools");

    if (!wrapper) {
        return;
    }

    const input =
        document.createElement("input");

    input.type = "search";
    input.placeholder = "Search your bookings...";
    input.className = "booking-search";
    input.setAttribute(
        "aria-label",
        "Search bookings"
    );

    wrapper.appendChild(input);

    const cards =
        document.querySelectorAll(
            ".booking-card-item"
        );

    input.addEventListener(
        "input",
        function () {

            const value =
                this.value
                    .toLowerCase()
                    .trim();

            cards.forEach(
                function (card) {

                    const text =
                        card.dataset.search || "";

                    card.style.display =
                        text.includes(value)
                        ? "grid"
                        : "none";

                }
            );

        }
    );

}

createSearchBox();

</script>


</body>
</html>

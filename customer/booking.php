<?php

/*
    =========================================================
    GLOBETREK - CUSTOMER BOOKING
    =========================================================
*/

include "../includes/customer_auth.php";
include "../includes/db.php";

$basePath = "../";
$activePage = "packages";

$userId = (int)($_SESSION["user_id"] ?? 0);

/* ---------------------------------------------------------
   CSRF TOKEN
--------------------------------------------------------- */

if (empty($_SESSION["booking_csrf"])) {
    $_SESSION["booking_csrf"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["booking_csrf"];

/* ---------------------------------------------------------
   PACKAGE ID
--------------------------------------------------------- */

$packageId = 0;

if (isset($_GET["id"])) {
    $packageId = (int)$_GET["id"];
} elseif (isset($_GET["package_id"])) {
    $packageId = (int)$_GET["package_id"];
}

if ($packageId <= 0) {
    header("Location: packages.php");
    exit();
}

/* =========================================================
   GET PACKAGE
========================================================= */

$sql = "
    SELECT
        id,
        title,
        description,
        price,
        image,
        destination,
        duration
    FROM packages
    WHERE id = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Unable to load the selected travel package.");
}

mysqli_stmt_bind_param($stmt, "i", $packageId);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$package = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$package) {
    header("Location: packages.php");
    exit();
}

/* ---------------------------------------------------------
   PACKAGE VALUES
--------------------------------------------------------- */

$title = $package["title"];
$description = $package["description"];
$price = (float)$package["price"];
$destination = $package["destination"];
$duration = $package["duration"];

/* ---------------------------------------------------------
   FORM VALUES
--------------------------------------------------------- */

$personsValue = 1;
$daysValue = 1;
$travelDateValue = "";

$message = "";
$messageType = "";

/* =========================================================
   BOOKING PROCESS
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $postedToken = $_POST["csrf_token"] ?? "";

    if (
        empty($postedToken) ||
        empty($_SESSION["booking_csrf"]) ||
        !hash_equals($_SESSION["booking_csrf"], $postedToken)
    ) {

        $message = "Security verification failed. Please refresh the page and try again.";
        $messageType = "error";

    } else {

        $personsValue = (int)($_POST["persons"] ?? 0);
        $daysValue = (int)($_POST["days"] ?? 0);
        $travelDateValue = trim($_POST["travel_date"] ?? "");

        if ($personsValue < 1 || $personsValue > 50) {

            $message = "Please select between 1 and 50 travellers.";
            $messageType = "error";

        } elseif ($daysValue < 1 || $daysValue > 30) {

            $message = "Please select between 1 and 30 days.";
            $messageType = "error";

        } else {

            $dateObject = DateTimeImmutable::createFromFormat("!Y-m-d", $travelDateValue);
            $dateErrors = DateTimeImmutable::getLastErrors();

            $validDate =
                $dateObject !== false &&
                (
                    $dateErrors === false ||
                    (
                        $dateErrors["warning_count"] === 0 &&
                        $dateErrors["error_count"] === 0
                    )
                ) &&
                $dateObject->format("Y-m-d") === $travelDateValue;

            if (!$validDate) {

                $message = "Please select a valid travel date.";
                $messageType = "error";

            } elseif ($travelDateValue < date("Y-m-d")) {

                $message = "Travel date cannot be in the past.";
                $messageType = "error";

            } else {

                /* Server-side price calculation */
                $totalPrice = $price * $personsValue * $daysValue;

                if ($totalPrice > 99999999.99) {

                    $message = "The booking amount is too large. Please reduce the number of travellers or days.";
                    $messageType = "error";

                } else {

                    /* Existing database keeps booking_date as year */
                    $bookingYear = (int)date("Y");

                    $insertSql = "
                        INSERT INTO booking
                        (
                            user_id,
                            package_id,
                            booking_date,
                            travel_date,
                            status,
                            persons,
                            days,
                            total_price
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?,
                            'Pending',
                            ?,
                            ?,
                            ?
                        )
                    ";

                    $insertStmt = mysqli_prepare($conn, $insertSql);

                    if (!$insertStmt) {

                        $message = "We could not prepare your booking. Please try again.";
                        $messageType = "error";

                    } else {

                        mysqli_stmt_bind_param(
                            $insertStmt,
                            "iissiid",
                            $userId,
                            $packageId,
                            $bookingYear,
                            $travelDateValue,
                            $personsValue,
                            $daysValue,
                            $totalPrice
                        );

                        if (mysqli_stmt_execute($insertStmt)) {

                            $newBookingId = mysqli_insert_id($conn);

                            mysqli_stmt_close($insertStmt);

                            $_SESSION["booking_csrf"] = bin2hex(random_bytes(32));

                            header(
                                "Location: payment.php?booking_id=" .
                                $newBookingId
                            );

                            exit();
                        }

                        $message = "Your booking could not be created. Please try again.";
                        $messageType = "error";

                        mysqli_stmt_close($insertStmt);
                    }
                }
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta
        name="description"
        content="Plan and book your next Sri Lankan adventure with GlobeTrek.">

    <title>Book Your Trip | GlobeTrek</title>

    <link rel="stylesheet" href="../css/site.css?v=100">
    <link rel="stylesheet" href="../css/booking.css?v=100">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

</head>

<body>

<?php include "../includes/navbar.php"; ?>

<!-- =========================================================
     COMPACT BOOKING HERO
     Uses external travel image, NOT package image
========================================================= -->

<section class="booking-hero">

    <div class="booking-hero-overlay"></div>

    <div class="booking-hero-content">

        <a href="packages.php" class="booking-breadcrumb">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Packages
        </a>

        <span class="booking-hero-label">
            PLAN YOUR NEXT ADVENTURE
        </span>

        <h1>Make your trip happen.</h1>

        <p>
            Choose your travel date, group size and trip length
            to continue your GlobeTrek adventure.
        </p>

    </div>

</section>

<!-- =========================================================
     MAIN BOOKING AREA
========================================================= -->

<main class="booking-page">

    <div class="booking-container">

        <!-- =================================================
             TRIP SUMMARY
        ================================================== -->

        <section class="trip-card">

            <div class="trip-visual">

                <div class="trip-visual-overlay"></div>

                <div class="trip-visual-content">

                    <span>GLOBETREK EXPERIENCE</span>

                    <h2>
                        <?php echo htmlspecialchars($title); ?>
                    </h2>

                    <div class="trip-location">
                        <i class="fa-solid fa-location-dot"></i>
                        <?php echo htmlspecialchars($destination); ?>
                    </div>

                </div>

            </div>

            <div class="trip-content">

                <div class="trip-heading">
                    <span>TRIP SUMMARY</span>
                    <h2>Your selected adventure</h2>
                </div>

                <?php if (!empty($description)): ?>
                    <p class="trip-description">
                        <?php echo htmlspecialchars($description); ?>
                    </p>
                <?php endif; ?>

                <div class="trip-info-grid">

                    <div class="trip-info">
                        <div class="trip-info-icon">
                            <i class="fa-regular fa-clock"></i>
                        </div>
                        <div>
                            <small>Package Duration</small>
                            <strong>
                                <?php echo htmlspecialchars($duration); ?>
                            </strong>
                        </div>
                    </div>

                    <div class="trip-info">
                        <div class="trip-info-icon">
                            <i class="fa-solid fa-user-group"></i>
                        </div>
                        <div>
                            <small>Travellers</small>
                            <strong id="tripTravellers">1 Person</strong>
                        </div>
                    </div>

                    <div class="trip-info">
                        <div class="trip-info-icon">
                            <i class="fa-solid fa-calendar-days"></i>
                        </div>
                        <div>
                            <small>Trip Length</small>
                            <strong id="tripDays">1 Day</strong>
                        </div>
                    </div>

                    <div class="trip-info">
                        <div class="trip-info-icon">
                            <i class="fa-solid fa-tag"></i>
                        </div>
                        <div>
                            <small>Price / Person / Day</small>
                            <strong>
                                LKR <?php echo number_format($price, 2); ?>
                            </strong>
                        </div>
                    </div>

                </div>

                <div class="trip-help">

                    <div class="trip-help-icon">
                        <i class="fa-solid fa-headset"></i>
                    </div>

                    <div>
                        <strong>Need help planning?</strong>
                        <p>
                            Select your group size and travel period.
                            Your total price will update automatically.
                        </p>
                    </div>

                </div>

            </div>

        </section>

        <!-- =================================================
             BOOKING FORM
        ================================================== -->

        <section class="booking-form-card">

            <div class="booking-form-heading">
                <span>BOOKING DETAILS</span>
                <h2>Plan your journey</h2>
                <p>Complete the details below to continue.</p>
            </div>

            <?php if ($message !== ""): ?>

                <div
                    class="booking-message <?php echo $messageType === "error" ? "error" : "success"; ?>"
                    role="alert">

                    <i class="fa-solid <?php echo $messageType === "error" ? "fa-circle-exclamation" : "fa-circle-check"; ?>"></i>

                    <span>
                        <?php echo htmlspecialchars($message); ?>
                    </span>

                </div>

            <?php endif; ?>

            <form method="POST" id="bookingForm">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo htmlspecialchars($csrfToken); ?>">

                <!-- TRAVELLERS -->

                <div class="booking-form-group">

                    <label for="persons">
                        <span class="label-title">Number of Travellers</span>
                        <span class="label-hint">1 - 50 people</span>
                    </label>

                    <div class="booking-input-wrapper">

                        <span class="booking-input-icon">
                            <i class="fa-solid fa-user-group"></i>
                        </span>

                        <input
                            type="number"
                            id="persons"
                            name="persons"
                            value="<?php echo $personsValue; ?>"
                            min="1"
                            max="50"
                            required>

                        <span class="input-unit">PEOPLE</span>

                    </div>

                </div>

                <!-- DAYS -->

                <div class="booking-form-group">

                    <label for="days">
                        <span class="label-title">Number of Days</span>
                        <span class="label-hint">1 - 30 days</span>
                    </label>

                    <div class="booking-input-wrapper">

                        <span class="booking-input-icon">
                            <i class="fa-regular fa-calendar"></i>
                        </span>

                        <input
                            type="number"
                            id="days"
                            name="days"
                            value="<?php echo $daysValue; ?>"
                            min="1"
                            max="30"
                            required>

                        <span class="input-unit">DAYS</span>

                    </div>

                </div>

                <!-- TRAVEL DATE -->

                <div class="booking-form-group">

                    <label for="travel_date">
                        <span class="label-title">Travel Date</span>
                        <span class="label-hint">Choose your departure date</span>
                    </label>

                    <div class="booking-input-wrapper">

                        <span class="booking-input-icon">
                            <i class="fa-regular fa-calendar-days"></i>
                        </span>

                        <input
                            type="date"
                            id="travel_date"
                            name="travel_date"
                            value="<?php echo htmlspecialchars($travelDateValue); ?>"
                            min="<?php echo date("Y-m-d"); ?>"
                            required>

                    </div>

                </div>

                <!-- SUMMARY -->

                <div class="booking-summary">

                    <div class="summary-header">

                        <div>
                            <span>YOUR TRIP</span>
                            <strong>Booking Summary</strong>
                        </div>

                        <i class="fa-solid fa-receipt"></i>

                    </div>

                    <div class="summary-line">
                        <span>Package</span>
                        <strong><?php echo htmlspecialchars($title); ?></strong>
                    </div>

                    <div class="summary-line">
                        <span>Price / Person / Day</span>
                        <strong>LKR <?php echo number_format($price, 2); ?></strong>
                    </div>

                    <div class="summary-line">
                        <span>Travellers</span>
                        <strong id="summaryPersons">1</strong>
                    </div>

                    <div class="summary-line">
                        <span>Days</span>
                        <strong id="summaryDays">1</strong>
                    </div>

                    <div class="summary-total">
                        <span>Estimated Total</span>
                        <strong id="summaryTotal">
                            LKR <?php echo number_format($price, 2); ?>
                        </strong>
                    </div>

                    <small class="summary-note">
                        Price = package price × travellers × days.
                    </small>

                </div>

                <button
                    type="submit"
                    class="booking-submit"
                    id="bookingSubmit">

                    <span>Continue to Payment</span>
                    <i class="fa-solid fa-arrow-right"></i>

                </button>

                <p class="form-security">
                    <i class="fa-solid fa-shield-halved"></i>
                    Your booking details are protected by server-side validation.
                </p>

            </form>

        </section>

    </div>

</main>

<?php include "../includes/footer.php"; ?>

<script>

const bookingPrice = <?php echo json_encode($price); ?>;

const personsInput = document.getElementById("persons");
const daysInput = document.getElementById("days");
const travelDateInput = document.getElementById("travel_date");

const summaryPersons = document.getElementById("summaryPersons");
const summaryDays = document.getElementById("summaryDays");
const summaryTotal = document.getElementById("summaryTotal");

const tripTravellers = document.getElementById("tripTravellers");
const tripDays = document.getElementById("tripDays");

function formatLKR(amount) {

    return "LKR " + Number(amount).toLocaleString("en-LK", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });

}

function updateBookingSummary() {

    let persons = parseInt(personsInput.value, 10);
    let days = parseInt(daysInput.value, 10);

    if (Number.isNaN(persons) || persons < 1) {
        persons = 1;
    }

    if (Number.isNaN(days) || days < 1) {
        days = 1;
    }

    const total = bookingPrice * persons * days;

    summaryPersons.textContent = persons;
    summaryDays.textContent = days;
    summaryTotal.textContent = formatLKR(total);

    tripTravellers.textContent =
        persons + (persons === 1 ? " Person" : " People");

    tripDays.textContent =
        days + (days === 1 ? " Day" : " Days");
}

personsInput.addEventListener("input", updateBookingSummary);
daysInput.addEventListener("input", updateBookingSummary);

updateBookingSummary();

const bookingForm = document.getElementById("bookingForm");

if (bookingForm) {

    bookingForm.addEventListener("submit", function (event) {

        const persons = parseInt(personsInput.value, 10);
        const days = parseInt(daysInput.value, 10);

        if (Number.isNaN(persons) || persons < 1 || persons > 50) {
            event.preventDefault();
            alert("Please select between 1 and 50 travellers.");
            personsInput.focus();
            return;
        }

        if (Number.isNaN(days) || days < 1 || days > 30) {
            event.preventDefault();
            alert("Please select between 1 and 30 days.");
            daysInput.focus();
            return;
        }

        if (!travelDateInput.value) {
            event.preventDefault();
            alert("Please select your travel date.");
            travelDateInput.focus();
            return;
        }

        const button = document.getElementById("bookingSubmit");

        if (button) {
            button.disabled = true;
            button.innerHTML =
                '<span><i class="fa-solid fa-spinner fa-spin"></i> Creating Booking...</span>';
        }

    });

}

</script>

</body>
</html>

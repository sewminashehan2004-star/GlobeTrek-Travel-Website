<?php

/*
    =====================================================
    GLOBETREK - CUSTOMER PAYMENT
    =====================================================
*/


/* -----------------------------------------------------
   Customer authentication
----------------------------------------------------- */

include "../includes/customer_auth.php";

include "../includes/db.php";


/* -----------------------------------------------------
   Page settings
----------------------------------------------------- */

$basePath = "../";

$activePage = "bookings";


/* -----------------------------------------------------
   Current customer
----------------------------------------------------- */

$userId = (int) $_SESSION["user_id"];


/* -----------------------------------------------------
   Generate CSRF token
----------------------------------------------------- */

if (
    !isset($_SESSION["payment_csrf"])
    ||
    empty($_SESSION["payment_csrf"])
) {

    $_SESSION["payment_csrf"] =
        bin2hex(
            random_bytes(32)
        );

}


$csrfToken =
    $_SESSION["payment_csrf"];


/* -----------------------------------------------------
   Booking ID
----------------------------------------------------- */

$bookingId =
    isset($_GET["booking_id"])
    ? (int) $_GET["booking_id"]
    : 0;


if ($bookingId <= 0) {

    header(
        "Location: my-bookings.php"
    );

    exit();

}


/* =====================================================
   GET BOOKING
===================================================== */

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
        p.image,
        p.description

    FROM booking b

    INNER JOIN packages p
        ON b.package_id = p.id

    WHERE b.id = ?
      AND b.user_id = ?

    LIMIT 1

";


$stmt =
    mysqli_prepare(
        $conn,
        $sql
    );


if (!$stmt) {

    die(
        "Unable to load the booking."
    );

}


mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $bookingId,
    $userId
);


mysqli_stmt_execute(
    $stmt
);


$result =
    mysqli_stmt_get_result(
        $stmt
    );


$booking =
    mysqli_fetch_assoc(
        $result
    );


mysqli_stmt_close(
    $stmt
);


if (!$booking) {

    die(
        "This booking was not found or you do not have permission to access it."
    );

}


/* =====================================================
   GET LATEST PAID PAYMENT
===================================================== */

$paidPaymentId = 0;

$paymentCheckSql = "

    SELECT
        id

    FROM payments

    WHERE booking_id = ?
      AND user_id = ?
      AND LOWER(payment_status) = 'paid'

    ORDER BY id DESC

    LIMIT 1

";


$paymentCheckStmt =
    mysqli_prepare(
        $conn,
        $paymentCheckSql
    );


if (!$paymentCheckStmt) {

    die(
        "Unable to check payment status."
    );

}


mysqli_stmt_bind_param(
    $paymentCheckStmt,
    "ii",
    $bookingId,
    $userId
);


mysqli_stmt_execute(
    $paymentCheckStmt
);


$paymentCheckResult =
    mysqli_stmt_get_result(
        $paymentCheckStmt
    );


$paidPayment =
    mysqli_fetch_assoc(
        $paymentCheckResult
    );


mysqli_stmt_close(
    $paymentCheckStmt
);


if ($paidPayment) {

    $paidPaymentId =
        (int) $paidPayment["id"];

}


/* -----------------------------------------------------
   Payment state
----------------------------------------------------- */

$alreadyPaid =
    $paidPaymentId > 0;


/* -----------------------------------------------------
   Message
----------------------------------------------------- */

$message = "";

$messageType = "";


/* -----------------------------------------------------
   Total
----------------------------------------------------- */

$total =
    (float) $booking["total_price"];


/* =====================================================
   SUCCESS MESSAGE
   Only show success if payment really exists
===================================================== */

if (
    isset($_GET["success"])
    &&
    $_GET["success"] === "1"
) {

    if ($alreadyPaid) {

        $message =
            "Payment successful! Your booking has been confirmed.";

        $messageType = "success";

    }

}


/* =====================================================
   PROCESS PAYMENT
===================================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    &&
    isset($_POST["pay_now"])
    &&
    !$alreadyPaid
) {


    /* -------------------------------------------------
       CSRF validation
    ------------------------------------------------- */

    $postedToken =
        $_POST["csrf_token"] ?? "";


    if (
        empty($postedToken)
        ||
        empty($_SESSION["payment_csrf"])
        ||
        !hash_equals(
            $_SESSION["payment_csrf"],
            $postedToken
        )
    ) {

        $message =
            "Security verification failed. Please refresh the page and try again.";

        $messageType = "error";

    }


    else {


        /* ---------------------------------------------
           Card name
        --------------------------------------------- */

        $cardName =
            trim(
                $_POST["card_name"] ?? ""
            );


        /* ---------------------------------------------
           Card number
        --------------------------------------------- */

        $cardNumber =
            preg_replace(
                "/[^0-9]/",
                "",
                $_POST["card_number"] ?? ""
            );


        /* ---------------------------------------------
           Expiry
        --------------------------------------------- */

        $expiry =
            trim(
                $_POST["exp_date"] ?? ""
            );


        /* ---------------------------------------------
           CVV
        --------------------------------------------- */

        $cvv =
            preg_replace(
                "/[^0-9]/",
                "",
                $_POST["cvv"] ?? ""
            );


        /* =================================================
           VALIDATION
        ================================================= */


        /* Card holder */

        if ($cardName === "") {

            $message =
                "Please enter the card holder name.";

            $messageType =
                "error";

        }


        /* Card number */

        elseif (
            strlen($cardNumber) < 13
            ||
            strlen($cardNumber) > 19
        ) {

            $message =
                "Please enter a valid card number.";

            $messageType =
                "error";

        }


        /* Expiry */

        elseif (
            !preg_match(
                "/^(0[1-9]|1[0-2])\/([0-9]{2})$/",
                $expiry
            )
        ) {

            $message =
                "Expiry date must use MM/YY format.";

            $messageType =
                "error";

        }


        /* CVV */

        elseif (
            strlen($cvv) < 3
            ||
            strlen($cvv) > 4
        ) {

            $message =
                "Please enter a valid CVV.";

            $messageType =
                "error";

        }


        else {


            /* -----------------------------------------
               Expiry date validation
            ----------------------------------------- */

            $expiryParts =
                explode(
                    "/",
                    $expiry
                );


            $expiryMonth =
                (int) $expiryParts[0];


            $expiryYear =
                (int) $expiryParts[1];


            $currentMonth =
                (int) date("m");


            $currentYear =
                (int) date("y");


            if (
                $expiryYear < $currentYear
                ||
                (
                    $expiryYear === $currentYear
                    &&
                    $expiryMonth < $currentMonth
                )
            ) {

                $message =
                    "This card has expired.";

                $messageType =
                    "error";

            }


            else {


                /* -------------------------------------
                   Optional Luhn validation
                ------------------------------------- */

                function isValidCardNumber($number)
                {

                    $sum = 0;

                    $length =
                        strlen($number);

                    $double =
                        false;


                    for (
                        $i = $length - 1;
                        $i >= 0;
                        $i--
                    ) {

                        $digit =
                            (int) $number[$i];


                        if ($double) {

                            $digit *= 2;


                            if ($digit > 9) {

                                $digit -= 9;

                            }

                        }


                        $sum += $digit;


                        $double =
                            !$double;

                    }


                    return
                        ($sum % 10) === 0;

                }


                if (
                    !isValidCardNumber(
                        $cardNumber
                    )
                ) {

                    $message =
                        "Please enter a valid card number.";

                    $messageType =
                        "error";

                }


                else {


                    /* =================================
                       CHECK AGAIN BEFORE INSERT
                       Prevent duplicate payment
                    ================================= */

                    $duplicateSql = "

                        SELECT id

                        FROM payments

                        WHERE booking_id = ?
                          AND user_id = ?
                          AND LOWER(payment_status) = 'paid'

                        LIMIT 1

                    ";


                    $duplicateStmt =
                        mysqli_prepare(
                            $conn,
                            $duplicateSql
                        );


                    if ($duplicateStmt) {


                        mysqli_stmt_bind_param(
                            $duplicateStmt,
                            "ii",
                            $bookingId,
                            $userId
                        );


                        mysqli_stmt_execute(
                            $duplicateStmt
                        );


                        $duplicateResult =
                            mysqli_stmt_get_result(
                                $duplicateStmt
                            );


                        $duplicatePayment =
                            mysqli_fetch_assoc(
                                $duplicateResult
                            );


                        mysqli_stmt_close(
                            $duplicateStmt
                        );


                        if ($duplicatePayment) {

                            $alreadyPaid = true;


                            $message =
                                "This booking has already been paid.";

                            $messageType =
                                "success";

                        }

                    }


                    /* =================================
                       CREATE PAYMENT
                    ================================= */

                    if (!$alreadyPaid) {


                        /*
                            Store only a masked card number.

                            Example:
                            ************1234
                        */

                        $maskedCard =
                            str_repeat(
                                "*",
                                max(
                                    0,
                                    strlen($cardNumber) - 4
                                )
                            )
                            .
                            substr(
                                $cardNumber,
                                -4
                            );


                        /*
                            Never store the real CVV.
                            This is a demo/student system.
                        */

                        $safeCvv = "***";


                        /*
                            Start transaction
                        */

                        mysqli_begin_transaction(
                            $conn
                        );


                        try {


                            /* ---------------------------------
                               Insert payment
                            --------------------------------- */

                            $insertSql = "

                                INSERT INTO payments
                                (
                                    booking_id,
                                    amount,
                                    payment_status,
                                    payment_method,
                                    user_id,
                                    card_name,
                                    card_number,
                                    exp_date,
                                    cvv
                                )

                                VALUES
                                (
                                    ?,
                                    ?,
                                    'Paid',
                                    'Card',
                                    ?,
                                    ?,
                                    ?,
                                    ?,
                                    ?
                                )

                            ";


                            $insertStmt =
                                mysqli_prepare(
                                    $conn,
                                    $insertSql
                                );


                            if (!$insertStmt) {

                                throw new Exception(
                                    "Payment could not be prepared."
                                );

                            }


                            mysqli_stmt_bind_param(
                                $insertStmt,
                                "idissss",
                                $bookingId,
                                $total,
                                $userId,
                                $cardName,
                                $maskedCard,
                                $expiry,
                                $safeCvv
                            );


                            if (
                                !mysqli_stmt_execute(
                                    $insertStmt
                                )
                            ) {

                                mysqli_stmt_close(
                                    $insertStmt
                                );


                                throw new Exception(
                                    "Payment could not be completed."
                                );

                            }


                            mysqli_stmt_close(
                                $insertStmt
                            );


                            /* ---------------------------------
                               Update booking status
                            --------------------------------- */

                            $updateSql = "

                                UPDATE booking

                                SET status = 'Paid'

                                WHERE id = ?
                                  AND user_id = ?

                            ";


                            $updateStmt =
                                mysqli_prepare(
                                    $conn,
                                    $updateSql
                                );


                            if (!$updateStmt) {

                                throw new Exception(
                                    "Booking status could not be updated."
                                );

                            }


                            mysqli_stmt_bind_param(
                                $updateStmt,
                                "ii",
                                $bookingId,
                                $userId
                            );


                            if (
                                !mysqli_stmt_execute(
                                    $updateStmt
                                )
                            ) {

                                mysqli_stmt_close(
                                    $updateStmt
                                );


                                throw new Exception(
                                    "Booking status could not be updated."
                                );

                            }


                            mysqli_stmt_close(
                                $updateStmt
                            );


                            /*
                                Everything worked.
                            */

                            mysqli_commit(
                                $conn
                            );


                            /*
                                Regenerate CSRF token
                                after successful payment
                            */

                            $_SESSION["payment_csrf"] =
                                bin2hex(
                                    random_bytes(32)
                                );


                            /*
                                Redirect to prevent
                                duplicate form submission
                            */

                            header(
                                "Location: payment.php?booking_id="
                                . $bookingId
                                . "&success=1"
                            );

                            exit();


                        }
                        catch (Exception $e) {


                            mysqli_rollback(
                                $conn
                            );


                            $message =
                                "Payment could not be completed. Please try again.";

                            $messageType =
                                "error";

                        }

                    }

                }

            }

        }

    }

}


/* =====================================================
   DISPLAY VALUES
===================================================== */

$travelDate = "Date not set";


if (!empty($booking["travel_date"])) {

    $travelDate =
        date(
            "d M Y",
            strtotime(
                $booking["travel_date"]
            )
        );

}


$imageName =
    basename(
        $booking["image"] ?? "kandy.jpg"
    );


$bookingStatus =
    ucfirst(
        strtolower(
            $booking["status"] ?? "Pending"
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
        Payment | GlobeTrek
    </title>


    <!-- =================================================
         SHARED WEBSITE CSS
    ================================================== -->

    <link
        rel="stylesheet"
        href="../css/site.css?v=41">


    <!-- =================================================
         PAYMENT CSS
    ================================================== -->

    <link
        rel="stylesheet"
        href="../css/payment.css?v=41">


    <!-- =================================================
         FONT AWESOME
    ================================================== -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

</head>


<body>


<?php include "../includes/navbar.php"; ?>


<!-- =====================================================
     PAYMENT PAGE
====================================================== -->

<main class="payment-page">


    <div class="payment-container">


        <!-- =================================================
             BOOKING SUMMARY
        ================================================== -->

        <section class="booking-card">


            <div class="booking-image">


                <img
                    src="../images/<?php echo htmlspecialchars($imageName); ?>"
                    alt="<?php echo htmlspecialchars($booking["title"]); ?>"
                    onerror="this.src='../images/kandy.jpg';">


                <div class="image-overlay"></div>


                <span class="image-label">

                    GLOBETREK ADVENTURE

                </span>

            </div>


            <div class="booking-content">


                <span class="section-label">

                    YOUR TRAVEL PLAN

                </span>


                <h1>

                    <?php
                    echo htmlspecialchars(
                        $booking["title"]
                    );
                    ?>

                </h1>


                <div class="booking-location">

                    <i class="fa-solid fa-location-dot"></i>

                    <?php
                    echo htmlspecialchars(
                        $booking["destination"]
                    );
                    ?>

                </div>


                <?php if (!empty($booking["description"])): ?>

                    <p class="booking-description">

                        <?php
                        echo htmlspecialchars(
                            $booking["description"]
                        );
                        ?>

                    </p>

                <?php endif; ?>


                <!-- DETAILS -->

                <div class="booking-details">


                    <div class="booking-detail">

                        <div class="detail-icon">

                            <i class="fa-regular fa-calendar-days"></i>

                        </div>


                        <div>

                            <small>
                                Travel Date
                            </small>

                            <strong>

                                <?php
                                echo htmlspecialchars(
                                    $travelDate
                                );
                                ?>

                            </strong>

                        </div>

                    </div>


                    <div class="booking-detail">

                        <div class="detail-icon">

                            <i class="fa-solid fa-users"></i>

                        </div>


                        <div>

                            <small>
                                Travellers
                            </small>

                            <strong>

                                <?php
                                echo (int)$booking["persons"];
                                ?>

                                <?php
                                echo
                                    ((int)$booking["persons"] === 1)
                                    ? " Person"
                                    : " People";
                                ?>

                            </strong>

                        </div>

                    </div>


                    <div class="booking-detail">

                        <div class="detail-icon">

                            <i class="fa-regular fa-clock"></i>

                        </div>


                        <div>

                            <small>
                                Duration
                            </small>

                            <strong>

                                <?php
                                echo (int)$booking["days"];
                                ?>

                                <?php
                                echo
                                    ((int)$booking["days"] === 1)
                                    ? " Day"
                                    : " Days";
                                ?>

                            </strong>

                        </div>

                    </div>


                    <div class="booking-detail">

                        <div class="detail-icon">

                            <i class="fa-solid fa-circle-info"></i>

                        </div>


                        <div>

                            <small>
                                Booking Status
                            </small>

                            <strong>

                                <?php
                                echo htmlspecialchars(
                                    $bookingStatus
                                );
                                ?>

                            </strong>

                        </div>

                    </div>

                </div>


                <!-- TOTAL -->

                <div class="booking-total">


                    <span>
                        Total Amount
                    </span>


                    <strong>

                        LKR
                        <?php
                        echo number_format(
                            $total,
                            2
                        );
                        ?>

                    </strong>

                </div>

            </div>

        </section>



        <!-- =================================================
             PAYMENT CARD
        ================================================== -->

        <section class="payment-card">


            <div class="payment-title">


                <div class="secure-title">

                    <i class="fa-solid fa-shield-halved"></i>

                    SECURE CHECKOUT

                </div>


                <h2>
                    Complete your payment
                </h2>


                <p>

                    Enter your card details to complete
                    your GlobeTrek booking.

                </p>

            </div>



            <!-- =================================================
                 MESSAGE
            ================================================== -->

            <?php if ($message !== ""): ?>

                <div
                    class="payment-message
                    <?php
                    echo $messageType === "success"
                        ? "message-success"
                        : "message-error";
                    ?>">

                    <div class="message-icon">

                        <?php if ($messageType === "success"): ?>

                            <i class="fa-solid fa-check"></i>

                        <?php else: ?>

                            <i class="fa-solid fa-exclamation"></i>

                        <?php endif; ?>

                    </div>


                    <p>

                        <?php
                        echo htmlspecialchars(
                            $message
                        );
                        ?>

                    </p>

                </div>

            <?php endif; ?>



            <!-- =================================================
                 PAYMENT FORM
            ================================================== -->

            <?php if (!$alreadyPaid): ?>


                <form
                    method="POST"
                    class="payment-form"
                    id="paymentForm"
                    autocomplete="off">


                    <!-- CSRF -->

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo htmlspecialchars($csrfToken); ?>">



                    <!-- CARD HOLDER -->

                    <div class="form-group">


                        <label for="card_name">

                            Card Holder Name

                        </label>


                        <div class="input-wrapper">


                            <span class="input-icon">

                                <i class="fa-regular fa-user"></i>

                            </span>


                            <input
                                type="text"
                                id="card_name"
                                name="card_name"
                                maxlength="100"
                                autocomplete="cc-name"
                                placeholder="Name on card"
                                required>

                        </div>

                    </div>



                    <!-- CARD NUMBER -->

                    <div class="form-group">


                        <label for="card_number">

                            Card Number

                        </label>


                        <div class="input-wrapper">


                            <span class="input-icon">

                                <i class="fa-regular fa-credit-card"></i>

                            </span>


                            <input
                                type="text"
                                id="card_number"
                                name="card_number"
                                maxlength="23"
                                inputmode="numeric"
                                autocomplete="cc-number"
                                placeholder="1234 5678 9012 3456"
                                required>


                            <span
                                class="card-security-icon">

                                <i class="fa-solid fa-lock"></i>

                            </span>

                        </div>

                    </div>



                    <!-- EXPIRY / CVV -->

                    <div class="two-fields">


                        <div class="form-group">


                            <label for="exp_date">

                                Expiry Date

                            </label>


                            <div class="input-wrapper">


                                <span class="input-icon">

                                    <i class="fa-regular fa-calendar"></i>

                                </span>


                                <input
                                    type="text"
                                    id="exp_date"
                                    name="exp_date"
                                    maxlength="5"
                                    inputmode="numeric"
                                    autocomplete="cc-exp"
                                    placeholder="MM/YY"
                                    required>

                            </div>

                        </div>



                        <div class="form-group">


                            <label for="cvv">

                                CVV

                            </label>


                            <div class="input-wrapper">


                                <span class="input-icon">

                                    <i class="fa-solid fa-lock"></i>

                                </span>


                                <input
                                    type="password"
                                    id="cvv"
                                    name="cvv"
                                    maxlength="4"
                                    inputmode="numeric"
                                    autocomplete="cc-csc"
                                    placeholder="123"
                                    required>

                            </div>

                        </div>

                    </div>



                    <!-- SECURITY NOTICE -->

                    <div class="payment-notice">


                        <div class="notice-icon">

                            <i class="fa-solid fa-shield-halved"></i>

                        </div>


                        <div>

                            <strong>
                                Demo Payment Protection
                            </strong>


                            <p>

                                This is a student/demo payment system.
                                The real card number and CVV are not stored.

                            </p>

                        </div>

                    </div>



                    <!-- PAYMENT AMOUNT -->

                    <div class="pay-summary">


                        <span>
                            Amount to Pay
                        </span>


                        <strong>

                            LKR
                            <?php
                            echo number_format(
                                $total,
                                2
                            );
                            ?>

                        </strong>

                    </div>



                    <!-- PAY BUTTON -->

                    <button
                        type="submit"
                        name="pay_now"
                        class="pay-button">


                        <span>

                            <i class="fa-solid fa-lock"></i>

                            Pay LKR
                            <?php
                            echo number_format(
                                $total,
                                2
                            );
                            ?>

                        </span>


                        <i class="fa-solid fa-arrow-right"></i>

                    </button>


                </form>


            <?php else: ?>


                <!-- =================================================
                     PAYMENT COMPLETE
                ================================================== -->

                <div class="payment-complete">


                    <div class="success-icon">

                        <i class="fa-solid fa-check"></i>

                    </div>


                    <div class="complete-label">

                        PAYMENT COMPLETED

                    </div>


                    <h3>
                        Booking Confirmed
                    </h3>


                    <p>

                        Your payment has been successfully
                        recorded and your GlobeTrek booking
                        is confirmed.

                    </p>


                    <div class="confirmation-box">


                        <div>

                            <span>
                                Booking ID
                            </span>

                            <strong>

                                #
                                <?php
                                echo $bookingId;
                                ?>

                            </strong>

                        </div>


                        <div>

                            <span>
                                Amount Paid
                            </span>

                            <strong>

                                LKR
                                <?php
                                echo number_format(
                                    $total,
                                    2
                                );
                                ?>

                            </strong>

                        </div>


                    </div>


                    <div class="complete-actions">


                        <a
                            href="my-bookings.php"
                            class="primary-action">

                            <i class="fa-solid fa-calendar-check"></i>

                            View My Bookings

                        </a>


                        <a
                            href="packages.php"
                            class="secondary-action">

                            <i class="fa-solid fa-compass"></i>

                            Explore More

                        </a>

                    </div>

                </div>

            <?php endif; ?>


        </section>

    </div>

</main>


<?php include "../includes/footer.php"; ?>



<!-- =====================================================
     PAYMENT JAVASCRIPT
====================================================== -->

<script>


/* =====================================================
   CARD NUMBER
===================================================== */

const cardNumber =
    document.getElementById(
        "card_number"
    );


if (cardNumber) {


    cardNumber.addEventListener(
        "input",
        function () {


            let value =
                this.value.replace(
                    /[^0-9]/g,
                    ""
                );


            /*
                Maximum 19 digits
            */

            value =
                value.substring(
                    0,
                    19
                );


            let formatted = "";


            for (
                let i = 0;
                i < value.length;
                i += 4
            ) {


                if (i > 0) {

                    formatted += " ";

                }


                formatted +=
                    value.substring(
                        i,
                        i + 4
                    );

            }


            this.value =
                formatted;

        }
    );

}


/* =====================================================
   EXPIRY DATE
===================================================== */

const expiry =
    document.getElementById(
        "exp_date"
    );


if (expiry) {


    expiry.addEventListener(
        "input",
        function () {


            let value =
                this.value.replace(
                    /[^0-9]/g,
                    ""
                );


            value =
                value.substring(
                    0,
                    4
                );


            if (
                value.length >= 3
            ) {

                value =
                    value.substring(
                        0,
                        2
                    )
                    +
                    "/"
                    +
                    value.substring(
                        2
                    );

            }


            this.value =
                value;

        }
    );

}


/* =====================================================
   CVV
===================================================== */

const cvv =
    document.getElementById(
        "cvv"
    );


if (cvv) {


    cvv.addEventListener(
        "input",
        function () {

            this.value =
                this.value.replace(
                    /[^0-9]/g,
                    ""
                );

        }
    );

}


/* =====================================================
   PAYMENT FORM
===================================================== */

const paymentForm =
    document.getElementById(
        "paymentForm"
    );


if (paymentForm) {


    paymentForm.addEventListener(
        "submit",
        function (event) {


            const card =
                document.getElementById(
                    "card_number"
                );


            const exp =
                document.getElementById(
                    "exp_date"
                );


            const cvvInput =
                document.getElementById(
                    "cvv"
                );


            const cardDigits =
                card.value.replace(
                    /[^0-9]/g,
                    ""
                );


            /*
                Basic front-end validation.
                Server validation is still required
                and is already implemented above.
            */

            if (
                cardDigits.length < 13
                ||
                cardDigits.length > 19
            ) {

                event.preventDefault();

                alert(
                    "Please enter a valid card number."
                );

                card.focus();

                return;

            }


            if (
                !/^(0[1-9]|1[0-2])\/([0-9]{2})$/.test(
                    exp.value
                )
            ) {

                event.preventDefault();

                alert(
                    "Please enter expiry date in MM/YY format."
                );

                exp.focus();

                return;

            }


            if (
                cvvInput.value.length < 3
                ||
                cvvInput.value.length > 4
            ) {

                event.preventDefault();

                alert(
                    "Please enter a valid CVV."
                );

                cvvInput.focus();

                return;

            }


            /*
                Prevent double click
            */

            const button =
                this.querySelector(
                    ".pay-button"
                );


            if (button) {

                button.disabled =
                    true;


                button.innerHTML =

                    '<span>' +
                    '<i class="fa-solid fa-spinner fa-spin"></i>' +
                    ' Processing Payment...' +
                    '</span>';

            }

        }
    );

}

</script>


</body>

</html>
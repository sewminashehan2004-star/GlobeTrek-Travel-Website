<?php

include "includes/db.php";

$message = "";
$messageType = "";


/*
    REGISTRATION PROCESS
*/

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirmPassword = $_POST["confirm_password"];


    /*
        CHECK NAME
    */

    if ($name == "") {

        $message = "Please enter your full name.";
        $messageType = "error";


    /*
        CHECK EMAIL
    */

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";
        $messageType = "error";


    /*
        CHECK PASSWORD LENGTH
    */

    } elseif (strlen($password) < 6) {

        $message = "Password must contain at least 6 characters.";
        $messageType = "error";


    /*
        CHECK CONFIRM PASSWORD
    */

    } elseif ($password !== $confirmPassword) {

        $message = "Passwords do not match.";
        $messageType = "error";


    } else {


        /*
            CHECK WHETHER EMAIL ALREADY EXISTS
        */

        $checkSql =
            "SELECT id FROM users WHERE email = ?";


        $checkStmt =
            mysqli_prepare($conn, $checkSql);


        mysqli_stmt_bind_param(
            $checkStmt,
            "s",
            $email
        );


        mysqli_stmt_execute($checkStmt);


        $checkResult =
            mysqli_stmt_get_result($checkStmt);


        if (mysqli_num_rows($checkResult) > 0) {

            $message = "An account with this email already exists.";
            $messageType = "error";


        } else {


            /*
                HASH PASSWORD
            */

            $hashedPassword =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );


            /*
                New users are customers
            */

            $role = "customer";


            /*
                INSERT USER
            */

            $insertSql =
                "INSERT INTO users
                (name, email, password, role)
                VALUES (?, ?, ?, ?)";


            $insertStmt =
                mysqli_prepare($conn, $insertSql);


            mysqli_stmt_bind_param(
                $insertStmt,
                "ssss",
                $name,
                $email,
                $hashedPassword,
                $role
            );


            if (mysqli_stmt_execute($insertStmt)) {

                /*
                    Registration successful

                    Send user to login page.
                */

                header("Location: login.php?success=1");

                exit();

            } else {

                $message =
                    "Something went wrong. Please try again.";

                $messageType = "error";

            }


            mysqli_stmt_close($insertStmt);

        }


        mysqli_stmt_close($checkStmt);

    }

}

?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Create Account | GlobeTrek Adventures</title>

    <link rel="stylesheet"
          href="css/auth.css?v=10">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

</head>


<body>


<!-- =====================================================
     MAIN AUTH PAGE
===================================================== -->

<div class="auth-page">


    <!-- =================================================
         LEFT SIDE
    ================================================== -->

    <div class="auth-image register-image">

        <div class="image-dark"></div>


        <div class="image-content">

            <a href="index.php" class="auth-logo">
                Globe<span>Trek</span>
            </a>


            <div class="image-text">

                <span class="small-title">
                    START YOUR ADVENTURE
                </span>

                <h1>
                    Discover more.
                    <br>
                    Experience more.
                </h1>

                <p>
                    Create your GlobeTrek account and discover
                    destinations, travel packages and unforgettable
                    experiences.
                </p>


                <div class="image-features">

                    <div>
                        <i class="fa-solid fa-compass"></i>
                        <span>Discover new places</span>
                    </div>

                    <div>
                        <i class="fa-solid fa-suitcase-rolling"></i>
                        <span>Plan your next trip</span>
                    </div>

                    <div>
                        <i class="fa-solid fa-star"></i>
                        <span>Create unforgettable memories</span>
                    </div>

                </div>

            </div>


            <div class="image-bottom">
                Your adventure is waiting.
            </div>

        </div>

    </div>



    <!-- =================================================
         RIGHT SIDE
    ================================================== -->

    <div class="auth-form-side">

        <div class="auth-card register-card">


            <!-- MOBILE LOGO -->

            <a href="index.php" class="mobile-logo">
                Globe<span>Trek</span>
            </a>


            <!-- FORM HEADER -->

            <div class="form-header">

                <span class="form-label">
                    CREATE ACCOUNT
                </span>

                <h2>
                    Join GlobeTrek
                </h2>

                <p>
                    Create your account and start exploring.
                </p>

            </div>



            <!-- ERROR MESSAGE -->

            <?php if ($message != "") { ?>

                <div class="alert error-alert">

                    <i class="fa-solid fa-circle-exclamation"></i>

                    <span>
                        <?php echo htmlspecialchars($message); ?>
                    </span>

                </div>

            <?php } ?>



            <!-- REGISTER FORM -->

            <form method="POST"
                  action="register.php"
                  class="auth-form">


                <!-- NAME -->

                <div class="form-group">

                    <label for="name">
                        Full Name
                    </label>


                    <div class="input-wrapper">

                        <i class="fa-regular fa-user"></i>

                        <input
                            type="text"
                            id="name"
                            name="name"
                            placeholder="Enter your full name"
                            autocomplete="name"
                            value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>"
                            required>

                    </div>

                </div>



                <!-- EMAIL -->

                <div class="form-group">

                    <label for="registerEmail">
                        Email Address
                    </label>


                    <div class="input-wrapper">

                        <i class="fa-regular fa-envelope"></i>

                        <input
                            type="email"
                            id="registerEmail"
                            name="email"
                            placeholder="Enter your email"
                            autocomplete="email"
                            value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>"
                            required>

                    </div>

                </div>



                <!-- PASSWORD -->

                <div class="form-group">

                    <label for="registerPassword">
                        Password
                    </label>


                    <div class="input-wrapper">

                        <i class="fa-solid fa-lock"></i>

                        <input
                            type="password"
                            id="registerPassword"
                            name="password"
                            placeholder="Create a password"
                            autocomplete="new-password"
                            oninput="checkPasswordStrength()"
                            required>


                        <button
                            type="button"
                            class="password-button"
                            onclick="togglePassword('registerPassword', 'registerEye')">

                            <i id="registerEye"
                               class="fa-regular fa-eye">
                            </i>

                        </button>

                    </div>


                    <!-- PASSWORD STRENGTH -->

                    <div class="password-strength">

                        <div class="strength-bars">

                            <span id="bar1"></span>
                            <span id="bar2"></span>
                            <span id="bar3"></span>
                            <span id="bar4"></span>

                        </div>

                        <small id="strengthText">
                            Use at least 6 characters
                        </small>

                    </div>

                </div>



                <!-- CONFIRM PASSWORD -->

                <div class="form-group">

                    <label for="confirmPassword">
                        Confirm Password
                    </label>


                    <div class="input-wrapper">

                        <i class="fa-solid fa-lock"></i>

                        <input
                            type="password"
                            id="confirmPassword"
                            name="confirm_password"
                            placeholder="Confirm your password"
                            autocomplete="new-password"
                            required>


                        <button
                            type="button"
                            class="password-button"
                            onclick="togglePassword('confirmPassword', 'confirmEye')">

                            <i id="confirmEye"
                               class="fa-regular fa-eye">
                            </i>

                        </button>

                    </div>

                </div>



                <!-- TERMS -->

                <label class="terms-label">

                    <input
                        type="checkbox"
                        name="terms"
                        required>

                    <span>
                        I agree to the GlobeTrek
                        <a href="#" onclick="return false;">
                            Terms & Conditions
                        </a>
                    </span>

                </label>



                <!-- CREATE ACCOUNT -->

                <button type="submit"
                        class="submit-button">

                    <span>
                        Create My Account
                    </span>

                    <i class="fa-solid fa-arrow-right"></i>

                </button>



                <!-- DIVIDER -->

                <div class="form-divider">

                    <span>OR</span>

                </div>



                <!-- LOGIN -->

                <div class="account-link">

                    <span>
                        Already have an account?
                    </span>

                    <a href="login.php">
                        Login here
                    </a>

                </div>

            </form>


            <!-- BACK HOME -->

            <a href="index.php" class="back-home">

                <i class="fa-solid fa-arrow-left"></i>

                Back to home

            </a>

        </div>

    </div>

</div>



<script>

/*
    SHOW / HIDE PASSWORD
*/

function togglePassword(inputId, iconId) {

    const input =
        document.getElementById(inputId);

    const icon =
        document.getElementById(iconId);


    if (input.type === "password") {

        input.type = "text";

        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");

    } else {

        input.type = "password";

        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");

    }

}


/*
    PASSWORD STRENGTH
*/

function checkPasswordStrength() {

    const password =
        document.getElementById("registerPassword").value;


    const bars = [

        document.getElementById("bar1"),
        document.getElementById("bar2"),
        document.getElementById("bar3"),
        document.getElementById("bar4")

    ];


    const text =
        document.getElementById("strengthText");


    // Reset all bars

    bars.forEach(function(bar) {

        bar.className = "";

    });


    // Empty password

    if (password.length === 0) {

        text.textContent =
            "Use at least 6 characters";

        return;

    }


    /*
        Calculate password strength
    */

    let strength = 0;


    if (password.length >= 6) {
        strength++;
    }

    if (password.length >= 8) {
        strength++;
    }

    if (/[A-Z]/.test(password)) {
        strength++;
    }

    if (/[0-9]/.test(password)) {
        strength++;
    }


    /*
        Update visual bars
    */

    for (let i = 0; i < strength; i++) {

        bars[i].classList.add("active");

    }


    /*
        Update text
    */

    if (strength <= 1) {

        text.textContent = "Weak password";

    } else if (strength === 2) {

        text.textContent = "Fair password";

    } else if (strength === 3) {

        text.textContent = "Good password";

    } else {

        text.textContent = "Strong password";

    }

}

</script>


</body>
</html>
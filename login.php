<?php

include "includes/db.php";
session_start();

$message = "";

/*
    LOGIN PROCESS
*/

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    // Check that the email is valid
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";

    } else {

        /*
            Get user by email

            Prepared statements are used so the email
            is handled safely.
        */

        $sql = "SELECT id, name, email, password, role
                FROM users
                WHERE email = ?";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param($stmt, "s", $email);

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);


        /*
            Check whether the user exists
        */

        if (mysqli_num_rows($result) == 1) {

            $user = mysqli_fetch_assoc($result);


            /*
                Check password
            */

            if (password_verify($password, $user["password"])) {

                /*
                    Create a new session ID after login
                */

                session_regenerate_id(true);


                /*
                    Save user information in session
                */

                $_SESSION["user_id"] = $user["id"];
                $_SESSION["name"] = $user["name"];
                $_SESSION["role"] = $user["role"];


                /*
                    Redirect according to user role
                */

                if ($user["role"] == "admin") {

                    header("Location: admin/adashboard.php");
                    exit();

                } elseif ($user["role"] == "staff") {

                    header("Location: staff/sdashboard.php");
                    exit();

                } else {

                    header("Location: customer/profile.php");
                    exit();

                }

            } else {

                $message = "Incorrect email or password.";

            }

        } else {

            $message = "Incorrect email or password.";

        }


        mysqli_stmt_close($stmt);

    }

}

?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Login | GlobeTrek Adventures</title>

    <link rel="stylesheet" href="css/auth.css?v=10">

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

    <div class="auth-image">

        <div class="image-dark"></div>


        <div class="image-content">

            <a href="index.php" class="auth-logo">
                Globe<span>Trek</span>
            </a>


            <div class="image-text">

                <span class="small-title">
                    WELCOME TO GLOBETREK
                </span>

                <h1>
                    Your journey
                    <br>
                    starts here.
                </h1>

                <p>
                    Discover beautiful destinations, plan unforgettable
                    adventures and experience Sri Lanka in a whole new way.
                </p>


                <div class="image-features">

                    <div>
                        <i class="fa-solid fa-location-dot"></i>
                        <span>Beautiful destinations</span>
                    </div>

                    <div>
                        <i class="fa-solid fa-map"></i>
                        <span>Exciting travel packages</span>
                    </div>

                    <div>
                        <i class="fa-solid fa-heart"></i>
                        <span>Memorable experiences</span>
                    </div>

                </div>

            </div>


            <div class="image-bottom">
                Explore � Discover � Experience
            </div>

        </div>

    </div>



    <!-- =================================================
         RIGHT SIDE
    ================================================== -->

    <div class="auth-form-side">

        <div class="auth-card">


            <!-- MOBILE LOGO -->

            <a href="index.php" class="mobile-logo">
                Globe<span>Trek</span>
            </a>


            <!-- FORM HEADER -->

            <div class="form-header">

                <span class="form-label">
                    ACCOUNT LOGIN
                </span>

                <h2>
                    Welcome back
                </h2>

                <p>
                    Login to continue your adventure.
                </p>

            </div>



            <!-- SUCCESS MESSAGE -->

            <?php if (isset($_GET["success"])) { ?>

                <div class="alert success-alert">

                    <i class="fa-solid fa-circle-check"></i>

                    <span>
                        Your account was created successfully.
                    </span>

                </div>

            <?php } ?>



            <!-- ERROR MESSAGE -->

            <?php if ($message != "") { ?>

                <div class="alert error-alert">

                    <i class="fa-solid fa-circle-exclamation"></i>

                    <span>
                        <?php echo htmlspecialchars($message); ?>
                    </span>

                </div>

            <?php } ?>



            <!-- LOGIN FORM -->

            <form method="POST"
                  action="login.php"
                  class="auth-form">


                <!-- EMAIL -->

                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>


                    <div class="input-wrapper">

                        <i class="fa-regular fa-envelope"></i>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="Enter your email"
                            autocomplete="email"
                            value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>"
                            required>

                    </div>

                </div>



                <!-- PASSWORD -->

                <div class="form-group">

                    <div class="label-row">

                        <label for="loginPassword">
                            Password
                        </label>

                        <a href="#"
                           onclick="openForgotPassword(event)">
                            Forgot password?
                        </a>

                    </div>


                    <div class="input-wrapper">

                        <i class="fa-solid fa-lock"></i>

                        <input
                            type="password"
                            id="loginPassword"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required>

                        <button
                            type="button"
                            class="password-button"
                            onclick="togglePassword('loginPassword', 'loginEye')">

                            <i id="loginEye"
                               class="fa-regular fa-eye">
                            </i>

                        </button>

                    </div>

                </div>



                <!-- REMEMBER ME -->

                <div class="form-options">

                    <label class="check-label">

                        <input
                            type="checkbox"
                            name="remember">

                        <span>
                            Remember me
                        </span>

                    </label>

                </div>



                <!-- LOGIN BUTTON -->

                <button type="submit"
                        class="submit-button">

                    <span>
                        Login to GlobeTrek
                    </span>

                    <i class="fa-solid fa-arrow-right"></i>

                </button>



                <!-- DIVIDER -->

                <div class="form-divider">

                    <span>OR</span>

                </div>



                <!-- REGISTER LINK -->

                <div class="account-link">

                    <span>
                        Don't have a GlobeTrek account?
                    </span>

                    <a href="register.php">
                        Create an account
                    </a>

                </div>


            </form>


            <!-- BACK TO HOME -->

            <a href="index.php" class="back-home">

                <i class="fa-solid fa-arrow-left"></i>

                Back to home

            </a>

        </div>

    </div>

</div>



<!-- =====================================================
     FORGOT PASSWORD POPUP
===================================================== -->

<div class="forgot-overlay"
     id="forgotOverlay">


    <div class="forgot-card">


        <button class="close-forgot"
                type="button"
                onclick="closeForgotPassword()">

            <i class="fa-solid fa-xmark"></i>

        </button>


        <div class="forgot-icon">

            <i class="fa-solid fa-key"></i>

        </div>


        <span class="form-label">
            PASSWORD RECOVERY
        </span>


        <h3>
            Forgot your password?
        </h3>


        <p>
            Enter your email address and we will help you
            recover your account.
        </p>


        <form onsubmit="return forgotMessage()">

            <div class="input-wrapper popup-input">

                <i class="fa-regular fa-envelope"></i>

                <input
                    type="email"
                    placeholder="Enter your email"
                    required>

            </div>


            <button type="submit"
                    class="submit-button">

                Send Recovery Request

            </button>

        </form>

    </div>

</div>



<script>

/*
    SHOW / HIDE PASSWORD
*/

function togglePassword(inputId, iconId) {

    const passwordInput =
        document.getElementById(inputId);

    const eyeIcon =
        document.getElementById(iconId);


    if (passwordInput.type === "password") {

        passwordInput.type = "text";

        eyeIcon.classList.remove("fa-eye");
        eyeIcon.classList.add("fa-eye-slash");

    } else {

        passwordInput.type = "password";

        eyeIcon.classList.remove("fa-eye-slash");
        eyeIcon.classList.add("fa-eye");

    }

}


/*
    OPEN FORGOT PASSWORD POPUP
*/

function openForgotPassword(event) {

    event.preventDefault();

    document.getElementById("forgotOverlay").style.display = "flex";

}


/*
    CLOSE FORGOT PASSWORD POPUP
*/

function closeForgotPassword() {

    document.getElementById("forgotOverlay").style.display = "none";

}


/*
    CLOSE POPUP WHEN CLICKING OUTSIDE
*/

window.addEventListener("click", function(event) {

    const popup =
        document.getElementById("forgotOverlay");

    if (event.target === popup) {

        closeForgotPassword();

    }

});


/*
    Temporary message for forgot password

    This does not pretend to send an email.
    Your actual password reset system can be
    connected later.
*/

function forgotMessage() {

    alert(
        "Password recovery is not connected yet. " +
        "You can add an email-based reset system later."
    );

    return false;

}

</script>


</body>
</html>
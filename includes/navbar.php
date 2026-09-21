<?php

/*
    ======================================================
    GLOBETREK SHARED NAVBAR
    ======================================================
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* ---------------------------------------------
   Page settings
--------------------------------------------- */

$basePath = $basePath ?? "";
$activePage = $activePage ?? "";


/* ---------------------------------------------
   Session details
--------------------------------------------- */

$isLoggedIn = isset($_SESSION["user_id"]);

$userId = $_SESSION["user_id"] ?? null;

$userName = $_SESSION["name"] ?? "Account";

$userEmail = $_SESSION["email"] ?? "";

$userRole = $_SESSION["role"] ?? "";

$profileImage = $_SESSION["profile_image"] ?? "";


/* ---------------------------------------------
   Profile image
--------------------------------------------- */

$profileImageUrl = "";

if (!empty($profileImage)) {

    $profileImageUrl =
        $basePath .
        "uploads/profile/" .
        basename($profileImage);

}


/* ---------------------------------------------
   Avatar letter
--------------------------------------------- */

$avatarLetter = strtoupper(
    substr($userName, 0, 1)
);


/* ---------------------------------------------
   Dashboard URL
--------------------------------------------- */

$dashboardUrl =
    $basePath . "customer/cdashboard.php";


if ($userRole === "admin") {

    $dashboardUrl =
        $basePath . "admin/adashboard.php";

}
elseif ($userRole === "staff") {

    $dashboardUrl =
        $basePath . "staff/sdashboard.php";

}

?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<header class="site-header">

    <div class="navbar">


        <!-- LOGO -->

        <a
            href="<?php echo htmlspecialchars($basePath); ?>index.php"
            class="logo">

            Globe<span>Trek</span>

        </a>


        <!-- MOBILE MENU BUTTON -->

        <button
            type="button"
            class="menu-button"
            id="menuButton"
            aria-label="Open navigation">

            <i class="fa-solid fa-bars"></i>

        </button>


        <!-- NAVIGATION -->

        <nav
            class="main-nav"
            id="mainNav">


            <a
                href="<?php echo htmlspecialchars($basePath); ?>index.php"
                class="<?php echo $activePage === "home" ? "active" : ""; ?>">

                Home

            </a>


            <a
                href="<?php echo htmlspecialchars($basePath); ?>about.php"
                class="<?php echo $activePage === "about" ? "active" : ""; ?>">

                About

            </a>


            <a
                href="<?php echo htmlspecialchars($basePath); ?>customer/packages.php"
                class="<?php echo $activePage === "packages" ? "active" : ""; ?>">

                Packages

            </a>


            <a
                href="<?php echo htmlspecialchars($basePath); ?>why-us.php"
                class="<?php echo $activePage === "why" ? "active" : ""; ?>">

                Why Us

            </a>


            <a
                href="<?php echo htmlspecialchars($basePath); ?>contact.php"
                class="<?php echo $activePage === "contact" ? "active" : ""; ?>">

                Contact

            </a>

        </nav>


        <!-- RIGHT SIDE -->

        <div class="nav-actions">


            <?php if (!$isLoggedIn): ?>


                <a
                    href="<?php echo htmlspecialchars($basePath); ?>login.php"
                    class="login-link">

                    Login

                </a>


                <a
                    href="<?php echo htmlspecialchars($basePath); ?>register.php"
                    class="nav-register">

                    Register

                </a>


            <?php elseif ($userRole === "customer"): ?>


                <!-- CUSTOMER PROFILE -->

                <div class="user-area">

                    <button
                        type="button"
                        class="user-button"
                        id="userButton"
                        aria-expanded="false">

                        <?php if (!empty($profileImageUrl)): ?>

                            <img
                                src="<?php echo htmlspecialchars($profileImageUrl); ?>"
                                alt="Profile"
                                class="navbar-avatar">

                        <?php else: ?>

                            <span class="navbar-avatar avatar-letter">

                                <?php
                                echo htmlspecialchars($avatarLetter);
                                ?>

                            </span>

                        <?php endif; ?>


                        <span class="user-name">

                            <?php
                            echo htmlspecialchars($userName);
                            ?>

                        </span>


                        <i class="fa-solid fa-chevron-down user-arrow"></i>

                    </button>


                    <!-- DROPDOWN -->

                    <div
                        class="user-dropdown"
                        id="userDropdown">


                        <div class="dropdown-user">

                            <?php if (!empty($profileImageUrl)): ?>

                                <img
                                    src="<?php echo htmlspecialchars($profileImageUrl); ?>"
                                    alt="Profile"
                                    class="dropdown-avatar">

                            <?php else: ?>

                                <span class="dropdown-avatar dropdown-letter">

                                    <?php
                                    echo htmlspecialchars($avatarLetter);
                                    ?>

                                </span>

                            <?php endif; ?>


                            <div>

                                <strong>

                                    <?php
                                    echo htmlspecialchars($userName);
                                    ?>

                                </strong>

                                <small>

                                    <?php
                                    echo htmlspecialchars($userEmail);
                                    ?>

                                </small>

                            </div>

                        </div>


                        <div class="dropdown-divider"></div>


                       


                        <a
                            href="<?php echo htmlspecialchars($basePath); ?>customer/profile.php">

                            <i class="fa-solid fa-user"></i>

                            My Profile

                        </a>


                        <a
                            href="<?php echo htmlspecialchars($basePath); ?>customer/my-bookings.php">

                            <i class="fa-solid fa-calendar-check"></i>

                            My Bookings

                        </a>


                        <a
                            href="<?php echo htmlspecialchars($basePath); ?>customer/packages.php">

                            <i class="fa-solid fa-compass"></i>

                            Explore Packages

                        </a>


                        <div class="dropdown-divider"></div>


                        <a
                            href="<?php echo htmlspecialchars($basePath); ?>logout.php"
                            class="logout-link">

                            <i class="fa-solid fa-right-from-bracket"></i>

                            Logout

                        </a>

                    </div>

                </div>


            <?php else: ?>


                <!-- ADMIN / STAFF -->

                <a
                    href="<?php echo htmlspecialchars($dashboardUrl); ?>"
                    class="dashboard-link">

                    Dashboard

                </a>


                <a
                    href="<?php echo htmlspecialchars($basePath); ?>logout.php"
                    class="nav-register">

                    Logout

                </a>


            <?php endif; ?>

        </div>

    </div>

</header>


<script>

document.addEventListener("DOMContentLoaded", function () {


    /* ==========================================
       MOBILE MENU
    ========================================== */

    const menuButton =
        document.getElementById("menuButton");

    const mainNav =
        document.getElementById("mainNav");


    if (menuButton && mainNav) {

        menuButton.addEventListener(
            "click",
            function () {

                mainNav.classList.toggle("show");

                const icon =
                    menuButton.querySelector("i");

                if (mainNav.classList.contains("show")) {

                    icon.className =
                        "fa-solid fa-xmark";

                } else {

                    icon.className =
                        "fa-solid fa-bars";

                }

            }
        );

    }


    /* ==========================================
       USER DROPDOWN
    ========================================== */

    const userButton =
        document.getElementById("userButton");

    const userDropdown =
        document.getElementById("userDropdown");


    if (userButton && userDropdown) {


        userButton.addEventListener(
            "click",
            function (event) {

                event.stopPropagation();

                userDropdown.classList.toggle("show");

            }
        );


        document.addEventListener(
            "click",
            function () {

                userDropdown.classList.remove("show");

            }
        );


        userDropdown.addEventListener(
            "click",
            function (event) {

                event.stopPropagation();

            }
        );

    }

});

</script>
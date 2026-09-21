<footer class="site-footer">

    <div class="footer-container">

        <div class="footer-brand">

            <a href="<?php echo htmlspecialchars($basePath ?? ""); ?>index.php"
               class="logo">
                Globe<span>Trek</span>
            </a>

            <p>
                Discover Sri Lanka, explore amazing destinations
                and create unforgettable travel experiences.
            </p>

        </div>


        <div class="footer-column">

            <h4>Explore</h4>

            <a href="<?php echo htmlspecialchars($basePath ?? ""); ?>index.php">
                Home
            </a>

            <a href="<?php echo htmlspecialchars($basePath ?? ""); ?>about.php">
                About
            </a>

            <a href="<?php echo htmlspecialchars($basePath ?? ""); ?>customer/packages.php">
                Packages
            </a>

            <a href="<?php echo htmlspecialchars($basePath ?? ""); ?>why-us.php">
                Why Us
            </a>

            <a href="<?php echo htmlspecialchars($basePath ?? ""); ?>contact.php">
                Contact
            </a>

        </div>


        <div class="footer-column">

            <h4>Account</h4>

            <?php if (isset($_SESSION["user_id"]) && ($_SESSION["role"] ?? "") === "customer") { ?>

                <a href="<?php echo htmlspecialchars($basePath ?? ""); ?>customer/cdashboard.php">
                    Dashboard
                </a>

                <a href="<?php echo htmlspecialchars($basePath ?? ""); ?>customer/profile.php">
                    My Profile
                </a>

                <a href="<?php echo htmlspecialchars($basePath ?? ""); ?>customer/my-bookings.php">
                    My Bookings
                </a>

                <a href="<?php echo htmlspecialchars($basePath ?? ""); ?>logout.php">
                    Logout
                </a>

            <?php } else { ?>

                <a href="<?php echo htmlspecialchars($basePath ?? ""); ?>login.php">
                    Login
                </a>

                <a href="<?php echo htmlspecialchars($basePath ?? ""); ?>register.php">
                    Register
                </a>

            <?php } ?>

        </div>


        <div class="footer-column">

            <h4>Contact</h4>

            <a href="<?php echo htmlspecialchars($basePath ?? ""); ?>contact.php">
                Contact Us
            </a>

            <p>📍 Sri Lanka</p>

            <p>✈ Travel • Explore • Experience</p>

        </div>

    </div>


    <div class="footer-bottom">

        <span>
            © <?php echo date("Y"); ?> GlobeTrek Adventures
        </span>

        <span>
            All Rights Reserved
        </span>

    </div>

</footer>

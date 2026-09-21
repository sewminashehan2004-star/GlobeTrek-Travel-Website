<?php

/*
    Start session
*/

session_start();


/*
    Database
*/

include "includes/db.php";


/*
    Get packages

    These are displayed from MySQL.
*/

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
    ORDER BY id ASC
    LIMIT 4
";


$packages =
    mysqli_query(
        $conn,
        $sql
    );


/*
    Logged-in customer?
*/

$isCustomer =
    isset($_SESSION["user_id"])
    &&
    isset($_SESSION["role"])
    &&
    $_SESSION["role"] === "customer";


/*
    Navbar settings
*/

$basePath = "";

$activePage = "home";

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">


    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">


    <title>
        GlobeTrek Adventures | Explore Sri Lanka
    </title>


    <meta
        name="description"
        content="Discover destinations, adventures and travel packages with GlobeTrek Adventures.">


    <link
        rel="stylesheet"
        href="css/site.css?v=10">


    <link
        rel="stylesheet"
        href="css/style.css?v=10">


</head>


<body>


<?php

/*
    Shared navbar
*/

include "includes/navbar.php";

?>



<!-- =====================================================
     HERO
===================================================== -->

<section class="home-hero">


    <video
        class="home-video"
        autoplay
        muted
        loop
        playsinline>

        <source
            src="videos/hero.mp4"
            type="video/mp4">

    </video>


    <div class="home-overlay"></div>


    <div class="home-hero-content">


        <span class="hero-label">
            EXPLORE • DISCOVER • EXPERIENCE
        </span>


        <h1>

            Your next adventure
            <span>starts here.</span>

        </h1>


        <p>

            Discover beautiful destinations,
            exciting travel experiences and
            unforgettable adventures across
            Sri Lanka.

        </p>


        <div class="hero-buttons">


            <a
                href="customer/packages.php"
                class="home-primary-button">

                Explore Packages →

            </a>


            <?php if ($isCustomer) { ?>


                <a
                    href="customer/cdashboard.php"
                    class="home-secondary-button">

                    My Dashboard

                </a>


            <?php } else { ?>


                <a
                    href="register.php"
                    class="home-secondary-button">

                    Start Your Journey

                </a>


            <?php } ?>


        </div>


    </div>


</section>



<!-- =====================================================
     SEARCH
===================================================== -->

<section class="home-search-section">


    <div class="home-search-box">


        <div>

            <span>
                FIND YOUR ADVENTURE
            </span>


            <h2>
                Where do you want to go?
            </h2>

        </div>


        <form
            action="customer/packages.php"
            method="GET"
            class="home-search-form">


            <input
                type="text"
                name="destination"
                placeholder="Destination">


            <input
                type="date"
                name="travel_date">


            <select name="budget">

                <option value="">
                    Any budget
                </option>

                <option value="2500">
                    Up to Rs. 2,500
                </option>

                <option value="3000">
                    Up to Rs. 3,000
                </option>

                <option value="5000">
                    Up to Rs. 5,000
                </option>

                <option value="10000">
                    Up to Rs. 10,000
                </option>

            </select>


            <button type="submit">

                Search

            </button>


        </form>

    </div>

</section>



<!-- =====================================================
     DESTINATIONS
===================================================== -->

<section class="home-section">


    <div class="home-section-heading">


        <div>

            <span>
                DISCOVER SRI LANKA
            </span>


            <h2>
                Popular Destinations
            </h2>


            <p>
                Find your next place to explore.
            </p>

        </div>


        <a href="customer/packages.php">

            View All Packages →

        </a>


    </div>



    <div class="home-package-grid">


        <?php if (
            $packages
            &&
            mysqli_num_rows($packages) > 0
        ) { ?>


            <?php while (
                $package =
                mysqli_fetch_assoc($packages)
            ) { ?>


                <article class="home-package-card">


                    <div class="home-card-image">


                        <img
                            src="images/<?php
                                echo htmlspecialchars(
                                    $package["image"]
                                );
                            ?>"
                            alt="<?php
                                echo htmlspecialchars(
                                    $package["title"]
                                );
                            ?>"
                            loading="lazy">


                        <span>
                            Adventure
                        </span>


                    </div>


                    <div class="home-card-content">


                        <small>
                            📍
                            <?php
                            echo htmlspecialchars(
                                $package["destination"]
                            );
                            ?>
                        </small>


                        <h3>

                            <?php
                            echo htmlspecialchars(
                                $package["title"]
                            );
                            ?>

                        </h3>


                        <p>

                            <?php
                            echo htmlspecialchars(
                                $package["description"]
                            );
                            ?>

                        </p>


                        <div
                            class="home-card-bottom">


                            <strong>

                                Rs.
                                <?php
                                echo number_format(
                                    $package["price"],
                                    0
                                );
                                ?>

                            </strong>


                            <a
                                href="customer/packages.php">

                                Explore →

                            </a>


                        </div>


                    </div>

                </article>


            <?php } ?>


        <?php } else { ?>


            <p>
                No travel packages are available right now.
            </p>


        <?php } ?>


    </div>

</section>



<!-- =====================================================
     WHY
===================================================== -->

<section class="home-why">


    <div class="home-why-content">


        <span>
            WHY GLOBETREK
        </span>


        <h2>

            Travel more.
            <strong>Worry less.</strong>

        </h2>


        <p>

            Discover places, compare travel options
            and plan memorable journeys through
            one simple platform.

        </p>


        <a
            href="why-us.php">

            Discover Why GlobeTrek →

        </a>


    </div>

</section>



<?php

include "includes/footer.php";

?>


</body>

</html>
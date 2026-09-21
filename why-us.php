<?php

session_start();

$basePath = "";

$activePage = "why";

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">


    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">


    <title>
        Why GlobeTrek
    </title>


    <link
        rel="stylesheet"
        href="css/site.css?v=10">


    <link
        rel="stylesheet"
        href="css/why.css?v=5">

</head>


<body>


<?php
include "includes/navbar.php";
?>


<!-- =====================================================
     HERO
===================================================== -->

<section class="why-hero">


    <div class="why-overlay"></div>


    <div class="why-content">


        <span>
            WHY GLOBETREK
        </span>


        <h1>

            A simpler way
            <strong>to travel.</strong>

        </h1>


        <p>

            Discover destinations, explore packages
            and plan your journey through one
            easy-to-use travel platform.

        </p>


    </div>

</section>



<!-- =====================================================
     FEATURES
===================================================== -->

<section class="why-section">


    <div class="why-heading">

        <span>
            THE GLOBETREK EXPERIENCE
        </span>


        <h2>
            Designed around travelers.
        </h2>

    </div>



    <div class="why-grid">


        <div class="why-card">

            <div>
                🌍
            </div>

            <h3>
                Discover Easily
            </h3>

            <p>
                Explore destinations and travel packages
                through a simple interface.
            </p>

        </div>


        <div class="why-card">

            <div>
                🔎
            </div>

            <h3>
                Find What Fits
            </h3>

            <p>
                Search by destination and budget to
                find suitable travel options.
            </p>

        </div>


        <div class="why-card">

            <div>
                🧳
            </div>

            <h3>
                Easy Booking
            </h3>

            <p>
                Registered customers can move from
                package selection to booking.
            </p>

        </div>


        <div class="why-card">

            <div>
                🔒
            </div>

            <h3>
                Protected Account
            </h3>

            <p>
                Customer-only booking and payment pages
                are protected by server-side login checks.
            </p>

        </div>


    </div>

</section>



<?php
include "includes/footer.php";
?>


</body>

</html>
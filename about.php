<?php

session_start();

$basePath = "";

$activePage = "about";

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">


    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">


    <title>
        About GlobeTrek
    </title>


    <link
        rel="stylesheet"
        href="css/site.css?v=10">


    <link
        rel="stylesheet"
        href="css/about.css?v=5">

</head>


<body>


<?php
include "includes/navbar.php";
?>


<!-- =====================================================
     ABOUT HERO
===================================================== -->

<section class="about-hero">


    <div class="about-hero-overlay"></div>


    <div class="about-hero-content">


        <span>
            ABOUT GLOBETREK
        </span>


        <h1>

            Travel with purpose.
            <strong>Explore with freedom.</strong>

        </h1>


        <p>

            GlobeTrek is designed to make discovering
            destinations and planning travel easier,
            simpler and more enjoyable.

        </p>


    </div>

</section>



<!-- =====================================================
     STORY
===================================================== -->

<section class="about-section">


    <div class="about-grid">


        <div>


            <span class="section-label">
                OUR STORY
            </span>


            <h2>
                More than a travel website.
            </h2>


            <p>

                GlobeTrek brings travel discovery and
                trip planning together in one platform.

            </p>


            <p>

                From discovering a destination to
                selecting a package and starting a
                booking, the experience is designed
                to be simple for travelers.

            </p>


        </div>



        <div class="about-card">


            <div>
                🌍
            </div>


            <h3>
                Explore
            </h3>


            <p>
                Discover destinations worth visiting.
            </p>


        </div>


        <div class="about-card">


            <div>
                🧭
            </div>


            <h3>
                Discover
            </h3>


            <p>
                Find travel experiences that match your plans.
            </p>


        </div>


        <div class="about-card">


            <div>
                ✈
            </div>


            <h3>
                Experience
            </h3>


            <p>
                Turn travel plans into unforgettable memories.
            </p>


        </div>


    </div>

</section>



<?php
include "includes/footer.php";
?>


</body>

</html>
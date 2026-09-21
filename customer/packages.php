<?php

/*
    ========================================================
    GLOBETREK - TRAVEL PACKAGES PAGE
    ========================================================

    Guests:
        - Can view packages
        - Can search packages
        - Can filter packages
        - Must login before booking

    Customers:
        - Can view packages
        - Can search packages
        - Can filter packages
        - Can book packages
*/


/*
    --------------------------------------------------------
    START SESSION
    --------------------------------------------------------
*/

session_start();


/*
    --------------------------------------------------------
    DATABASE CONNECTION
    --------------------------------------------------------
*/

include "../includes/db.php";


/*
    --------------------------------------------------------
    SHARED NAVBAR SETTINGS
    --------------------------------------------------------
*/

$basePath = "../";

$activePage = "packages";


/*
    --------------------------------------------------------
    CHECK LOGIN
    --------------------------------------------------------
*/

$isLoggedIn =
    isset($_SESSION["user_id"]);


/*
    --------------------------------------------------------
    CHECK CUSTOMER
    --------------------------------------------------------
*/

$isCustomer =
    $isLoggedIn
    &&
    isset($_SESSION["role"])
    &&
    $_SESSION["role"] === "customer";


/*
    --------------------------------------------------------
    GET SEARCH VALUE FROM HOMEPAGE
    --------------------------------------------------------

    Example:

    index.php
        ↓
    customer/packages.php?destination=Kandy

*/

$searchFromHome =
    isset($_GET["destination"])
    ? trim($_GET["destination"])
    : "";


$budgetFromHome =
    isset($_GET["budget"])
    ? trim($_GET["budget"])
    : "";


/*
    --------------------------------------------------------
    GET ALL PACKAGES
    --------------------------------------------------------
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

    ORDER BY id DESC

";


$packages =
    mysqli_query(
        $conn,
        $sql
    );


/*
    --------------------------------------------------------
    CHECK QUERY
    --------------------------------------------------------
*/

if (!$packages) {

    die(
        "Unable to load travel packages."
    );

}


/*
    --------------------------------------------------------
    GET UNIQUE DESTINATIONS
    --------------------------------------------------------
*/

$destinations = [];


while (
    $package =
    mysqli_fetch_assoc(
        $packages
    )
) {

    $destination =
        trim(
            $package["destination"]
        );


    /*
        Add destination only once
    */

    if (
        $destination !== ""
        &&
        !in_array(
            $destination,
            $destinations
        )
    ) {

        $destinations[] =
            $destination;

    }

}


/*
    Sort destinations
*/

sort(
    $destinations
);


/*
    Reset result pointer
*/

mysqli_data_seek(
    $packages,
    0
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
        Travel Packages | GlobeTrek
    </title>


    <meta
        name="description"
        content="Explore GlobeTrek travel packages and discover your next adventure in Sri Lanka."
    >


    <!--
        Shared navbar/footer CSS
    -->

    <link
        rel="stylesheet"
        href="../css/site.css?v=20"
    >


    <!--
        Packages page CSS
    -->

    <link
        rel="stylesheet"
        href="../css/packages.css?v=20"
    >

</head>



<body>


<!-- =====================================================
     SHARED NAVBAR
===================================================== -->

<?php

include "../includes/navbar.php";

?>



<!-- =====================================================
     PAGE HERO
===================================================== -->

<section class="packages-hero">


    <div class="packages-hero-overlay"></div>


    <div class="hero-content">


        <span class="hero-badge">

            EXPLORE SRI LANKA

        </span>


        <h1>

            Find your next

            <span>
                adventure.
            </span>

        </h1>


        <p>

            Discover beautiful destinations,
            exciting experiences and travel
            packages designed for unforgettable
            journeys.

        </p>


    </div>

</section>



<!-- =====================================================
     SEARCH + FILTER
===================================================== -->

<section class="search-section">


    <div class="search-box">


        <!-- SEARCH TITLE -->

        <div class="search-heading">


            <span>

                FIND YOUR ADVENTURE

            </span>


            <h2>

                Explore our packages

            </h2>


        </div>



        <!-- FILTER GRID -->

        <div class="filter-grid">


            <!-- =================================================
                 SEARCH
            ================================================== -->

            <div class="filter-field">


                <label
                    for="searchInput">

                    Search

                </label>


                <input

                    type="text"

                    id="searchInput"

                    placeholder="Package or destination..."

                    value="<?php
                        echo htmlspecialchars(
                            $searchFromHome
                        );
                    ?>"

                >

            </div>



            <!-- =================================================
                 DESTINATION
            ================================================== -->

            <div class="filter-field">


                <label
                    for="destinationFilter">

                    Destination

                </label>


                <select
                    id="destinationFilter">


                    <option
                        value="all">

                        All destinations

                    </option>


                    <?php

                    foreach (
                        $destinations
                        as $destination
                    ) {

                    ?>


                        <option

                            value="<?php

                                echo htmlspecialchars(
                                    strtolower(
                                        $destination
                                    )
                                );

                            ?>"

                            <?php

                            if (
                                strtolower(
                                    $searchFromHome
                                )
                                ===
                                strtolower(
                                    $destination
                                )
                            ) {

                                echo "selected";

                            }

                            ?>

                        >

                            <?php

                            echo htmlspecialchars(
                                $destination
                            );

                            ?>

                        </option>


                    <?php

                    }

                    ?>


                </select>

            </div>



            <!-- =================================================
                 PRICE
            ================================================== -->

            <div class="filter-field">


                <label
                    for="priceFilter">

                    Maximum Price

                </label>


                <select
                    id="priceFilter">


                    <option
                        value="all">

                        Any price

                    </option>


                    <option
                        value="2500"
                        <?php
                        echo $budgetFromHome === "2500"
                            ? "selected"
                            : "";
                        ?>>

                        Up to Rs. 2,500

                    </option>


                    <option
                        value="3000"
                        <?php
                        echo $budgetFromHome === "3000"
                            ? "selected"
                            : "";
                        ?>>

                        Up to Rs. 3,000

                    </option>


                    <option
                        value="5000"
                        <?php
                        echo $budgetFromHome === "5000"
                            ? "selected"
                            : "";
                        ?>>

                        Up to Rs. 5,000

                    </option>


                    <option
                        value="10000"
                        <?php
                        echo $budgetFromHome === "10000"
                            ? "selected"
                            : "";
                        ?>>

                        Up to Rs. 10,000

                    </option>


                </select>

            </div>



            <!-- =================================================
                 RESET
            ================================================== -->

            <button
                type="button"
                id="resetButton"
                class="reset-button">

                Reset

            </button>


        </div>

    </div>

</section>



<!-- =====================================================
     PACKAGE SECTION
===================================================== -->

<main class="packages-section">


    <!-- SECTION TOP -->

    <div class="section-top">


        <div>


            <span class="section-label">

                OUR COLLECTION

            </span>


            <h2>

                Popular Adventures

            </h2>


            <p>

                Choose your destination
                and start planning your journey.

            </p>


        </div>


        <div
            id="packageCount"
            class="package-count">

            Loading packages...

        </div>


    </div>



    <!-- =================================================
         PACKAGE GRID
    ================================================== -->

    <div
        class="package-grid"
        id="packageGrid">


        <?php

        /*
            Loop through all packages
        */

        while (
            $package =
            mysqli_fetch_assoc(
                $packages
            )
        ) {


            /*
                Package ID
            */

            $id =
                (int) $package["id"];


            /*
                Package title
            */

            $title =
                htmlspecialchars(
                    $package["title"]
                );


            /*
                Package description
            */

            $description =
                htmlspecialchars(
                    $package["description"]
                );


            /*
                Price
            */

            $price =
                (float) $package["price"];


            /*
                Image
            */

            $image =
                htmlspecialchars(
                    $package["image"]
                );


            /*
                Destination
            */

            $destination =
                htmlspecialchars(
                    $package["destination"]
                );


            /*
                Duration
            */

            $duration =
                htmlspecialchars(
                    $package["duration"]
                );


            /*
                Search-friendly values
            */

            $searchTitle =
                strtolower(
                    $package["title"]
                );


            $searchDescription =
                strtolower(
                    $package["description"]
                );


            $searchDestination =
                strtolower(
                    $package["destination"]
                );

        ?>


            <!-- =================================================
                 PACKAGE CARD
            ================================================== -->

            <article

                class="package-card"

                data-title="<?php
                    echo htmlspecialchars(
                        $searchTitle
                    );
                ?>"

                data-description="<?php
                    echo htmlspecialchars(
                        $searchDescription
                    );
                ?>"

                data-destination="<?php
                    echo htmlspecialchars(
                        $searchDestination
                    );
                ?>"

                data-price="<?php
                    echo $price;
                ?>"
            >


                <!-- =============================================
                     IMAGE
                ============================================== -->

                <div class="package-image">


                    <img

                        src="../images/<?php
                            echo $image;
                        ?>"

                        alt="<?php
                            echo $title;
                        ?>"

                        loading="lazy"

                    >


                    <span class="package-badge">

                        Adventure

                    </span>


                </div>



                <!-- =============================================
                     CONTENT
                ============================================== -->

                <div class="package-content">


                    <!-- LOCATION -->

                    <div class="package-location">

                        📍

                        <?php
                        echo $destination;
                        ?>

                    </div>



                    <!-- TITLE -->

                    <h3>

                        <?php
                        echo $title;
                        ?>

                    </h3>



                    <!-- DESCRIPTION -->

                    <p class="package-description">

                        <?php
                        echo $description;
                        ?>

                    </p>



                    <!-- =========================================
                         PACKAGE INFO
                    ========================================== -->

                    <div class="package-info">


                        <div>

                            <small>

                                Duration

                            </small>


                            <strong>

                                <?php
                                echo $duration;
                                ?>

                            </strong>

                        </div>


                        <div>

                            <small>

                                Price

                            </small>


                            <strong>

                                Rs.

                                <?php

                                echo number_format(
                                    $price,
                                    0
                                );

                                ?>

                            </strong>

                        </div>


                    </div>



                    <!-- =========================================
                         PACKAGE FOOTER
                    ========================================== -->

                    <div class="package-footer">


                        <!-- PRICE -->

                        <div class="package-price">


                            <small>

                                Starting from

                            </small>


                            <strong>

                                Rs.

                                <?php

                                echo number_format(
                                    $price,
                                    2
                                );

                                ?>

                            </strong>


                            <span>

                                / person / day

                            </span>


                        </div>



                        <!-- =====================================
                             BOOK BUTTON
                        ====================================== -->


                        <?php if ($isCustomer) { ?>


                            <!--
                                Logged-in customer

                                Can directly book.
                            -->

                            <a

                                href="booking.php?id=<?php
                                    echo $id;
                                ?>"

                                class="book-button"

                            >

                                Book Now

                                <span>
                                    →
                                </span>

                            </a>


                        <?php } else { ?>


                            <!--
                                Guest

                                Login required.
                            -->

                            <a

                                href="../login.php"

                                class="book-button"

                            >

                                Login to Book

                                <span>
                                    →
                                </span>

                            </a>


                        <?php } ?>


                    </div>


                </div>


            </article>


        <?php

        }

        ?>


    </div>



    <!-- =================================================
         NO RESULTS
    ================================================== -->

    <div
        class="no-results"
        id="noResults">


        <div class="no-results-icon">

            🔎

        </div>


        <h3>

            No packages found

        </h3>


        <p>

            Try another destination,
            package name or price range.

        </p>


        <button
            type="button"
            id="showAllButton">

            Show All Packages

        </button>


    </div>


</main>



<!-- =====================================================
     CTA
===================================================== -->

<section class="packages-cta">


    <div class="cta-overlay"></div>


    <div class="cta-content">


        <span>

            YOUR NEXT JOURNEY

        </span>


        <h2>

            Ready to explore?

        </h2>


        <p>

            Discover your next destination
            and create unforgettable memories.

        </p>


        <?php if ($isCustomer) { ?>


            <a

                href="packages.php"

                class="cta-button"

            >

                Explore Packages →

            </a>


        <?php } else { ?>


            <a

                href="../register.php"

                class="cta-button"

            >

                Start Your Journey →

            </a>


        <?php } ?>


    </div>

</section>



<!-- =====================================================
     SHARED FOOTER
===================================================== -->

<?php

include "../includes/footer.php";

?>



<!-- =====================================================
     JAVASCRIPT
===================================================== -->

<script>

/*
    ========================================================
    GET PAGE ELEMENTS
    ========================================================
*/

const searchInput =
    document.getElementById(
        "searchInput"
    );


const destinationFilter =
    document.getElementById(
        "destinationFilter"
    );


const priceFilter =
    document.getElementById(
        "priceFilter"
    );


const resetButton =
    document.getElementById(
        "resetButton"
    );


const showAllButton =
    document.getElementById(
        "showAllButton"
    );


const packageCards =
    document.querySelectorAll(
        ".package-card"
    );


const noResults =
    document.getElementById(
        "noResults"
    );


const packageCount =
    document.getElementById(
        "packageCount"
    );


/*
    ========================================================
    FILTER PACKAGES
    ========================================================
*/

function filterPackages() {


    /*
        Search text
    */

    const searchText =
        searchInput.value
            .toLowerCase()
            .trim();


    /*
        Selected destination
    */

    const selectedDestination =
        destinationFilter.value;


    /*
        Selected price
    */

    const selectedPrice =
        priceFilter.value;


    /*
        Visible package counter
    */

    let visiblePackages = 0;



    /*
        Loop through cards
    */

    packageCards.forEach(
        function (card) {


            /*
                Get package data
            */

            const title =
                card.dataset.title;


            const description =
                card.dataset.description;


            const destination =
                card.dataset.destination;


            const price =
                parseFloat(
                    card.dataset.price
                );


            /*
                --------------------------------------------
                SEARCH MATCH
                --------------------------------------------
            */

            const matchesSearch =

                searchText === ""

                ||

                title.includes(
                    searchText
                )

                ||

                description.includes(
                    searchText
                )

                ||

                destination.includes(
                    searchText
                );


            /*
                --------------------------------------------
                DESTINATION MATCH
                --------------------------------------------
            */

            const matchesDestination =

                selectedDestination === "all"

                ||

                destination ===
                selectedDestination;


            /*
                --------------------------------------------
                PRICE MATCH
                --------------------------------------------
            */

            let matchesPrice = true;


            if (
                selectedPrice !== "all"
            ) {

                matchesPrice =
                    price <=
                    parseFloat(
                        selectedPrice
                    );

            }


            /*
                --------------------------------------------
                SHOW OR HIDE
                --------------------------------------------
            */

            if (
                matchesSearch
                &&
                matchesDestination
                &&
                matchesPrice
            ) {

                card.style.display =
                    "block";


                visiblePackages++;


            } else {

                card.style.display =
                    "none";

            }

        }
    );


    /*
        ====================================================
        UPDATE PACKAGE COUNT
        ====================================================
    */

    if (
        visiblePackages === 0
    ) {

        packageCount.textContent =
            "No packages found";


    } else if (
        visiblePackages === 1
    ) {

        packageCount.textContent =
            "1 package found";


    } else {

        packageCount.textContent =
            visiblePackages
            + " packages found";

    }


    /*
        ====================================================
        SHOW / HIDE NO RESULTS
        ====================================================
    */

    if (
        visiblePackages === 0
    ) {

        noResults.style.display =
            "block";


    } else {

        noResults.style.display =
            "none";

    }

}


/*
    ========================================================
    RESET FILTERS
    ========================================================
*/

function resetFilters() {


    /*
        Clear search
    */

    searchInput.value =
        "";


    /*
        Reset destination
    */

    destinationFilter.value =
        "all";


    /*
        Reset price
    */

    priceFilter.value =
        "all";


    /*
        Run filter
    */

    filterPackages();

}


/*
    ========================================================
    EVENTS
    ========================================================
*/


/*
    Search while typing
*/

searchInput.addEventListener(
    "input",
    filterPackages
);


/*
    Destination changed
*/

destinationFilter.addEventListener(
    "change",
    filterPackages
);


/*
    Price changed
*/

priceFilter.addEventListener(
    "change",
    filterPackages
);


/*
    Reset button
*/

resetButton.addEventListener(
    "click",
    resetFilters
);


/*
    Show all button
*/

showAllButton.addEventListener(
    "click",
    resetFilters
);


/*
    ========================================================
    FIRST LOAD
    ========================================================
*/

filterPackages();

</script>


</body>

</html>
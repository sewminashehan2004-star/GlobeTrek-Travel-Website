<?php

session_start();

$basePath = "";

$activePage = "contact";

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">


    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">


    <title>
        Contact GlobeTrek
    </title>


    <link
        rel="stylesheet"
        href="css/site.css?v=10">


    <link
        rel="stylesheet"
        href="css/contact.css?v=5">

</head>


<body>


<?php
include "includes/navbar.php";
?>


<!-- =====================================================
     HERO
===================================================== -->

<section class="contact-hero">


    <div class="contact-overlay"></div>


    <div class="contact-content">


        <span>
            CONTACT GLOBETREK
        </span>


        <h1>

            Let's plan your
            <strong>next journey.</strong>

        </h1>


        <p>

            Have a question about a destination,
            package or booking? Get in touch with us.

        </p>


    </div>

</section>



<!-- =====================================================
     CONTACT SECTION
===================================================== -->

<section class="contact-section">


    <div class="contact-grid">


        <!-- DETAILS -->

        <div class="contact-details">


            <span>
                GET IN TOUCH
            </span>


            <h2>
                We'd love to hear from you.
            </h2>


            <p>

                Our team is here to help with
                travel information and booking
                questions.

            </p>


            <div class="contact-item">


                <div>
                    📍
                </div>


                <div>

                    <strong>
                        Location
                    </strong>

                    <p>
                        Sri Lanka
                    </p>

                </div>


            </div>



            <div class="contact-item">


                <div>
                    ✈
                </div>


                <div>

                    <strong>
                        Travel Support
                    </strong>

                    <p>
                        GlobeTrek Adventures
                    </p>

                </div>


            </div>


        </div>



        <!-- FORM -->

        <div class="contact-form-card">


            <h2>
                Send us a message
            </h2>


            <form
                method="POST"
                action="">


                <label>
                    Your Name
                </label>


                <input
                    type="text"
                    name="name"
                    placeholder="Enter your name"
                    required>



                <label>
                    Email Address
                </label>


                <input
                    type="email"
                    name="email"
                    placeholder="Enter your email"
                    required>



                <label>
                    Message
                </label>


                <textarea
                    name="message"
                    placeholder="How can we help you?"
                    required></textarea>



                <button type="submit">

                    Send Message →

                </button>


            </form>


            <small>

                This form is ready for PHP email
                processing when you connect your mail service.

            </small>


        </div>

    </div>

</section>



<?php
include "includes/footer.php";
?>


</body>

</html>
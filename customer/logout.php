<?php

/*
    Start session
*/

session_start();


/*
    Remove all session data
*/

$_SESSION = array();


/*
    Destroy the session
*/

session_destroy();


/*
    Send user back to home page
*/

header("Location: ../index.php");
exit();

?>
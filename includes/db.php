<?php

/*
    GlobeTrek Database Connection
*/

$servername = "localhost";
$username = "root";
$password = "";
$database = "globetrek_db";

$conn = mysqli_connect(
    $servername,
    $username,
    $password,
    $database
);

if (!$conn) {

    die("Database connection failed. Please check XAMPP and MySQL.");

}

mysqli_set_charset($conn, "utf8mb4");

?>

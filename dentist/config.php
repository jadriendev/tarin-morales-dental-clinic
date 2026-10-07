<?php
// config.php
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "tarin_morales_dental_clinic";
$db_host = "127.0.0.1";

$link = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if ($link === false) {
    die("ERROR: Could not connect. " . mysqli_connect_error());
}
?>
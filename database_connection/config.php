<?php
$conn = mysqli_connect("localhost", "root", "", "tarin_morales_dental_clinic");

if (!$conn)
    {
        die("Connection Failed: " . mysqli_connect_error());
    }
else
    {
        echo "Connected na bobo ka tanga";
    }

?>
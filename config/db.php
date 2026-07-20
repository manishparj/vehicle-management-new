<?php
$servername = "localhost";
$username = "root";
$password = "Dniirncd@#$1984";
$dbname = "vehicle_management";

// Create connection
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check connection
if (!$conn) {
  die("Connection failed: " . mysqli_connect_error());
}
// echo "Connected successfully";
?>

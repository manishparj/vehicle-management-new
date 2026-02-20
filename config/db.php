<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = new mysqli("localhost","root","","vehicle_management");

if($conn->connect_error){
    die("Connection Failed");
}
?>
<?php
include("../config/db.php");

// Unset all session variables
$_SESSION = array();

// Destroy session
session_destroy();

// Prevent browser caching after logout
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect to login page
header("Location: login.php");
exit();
?>
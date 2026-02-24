<?php
session_start();
include("../config/db.php");

if(!isset($_SESSION['user'])){
    die("Unauthorized Access");
}

if(isset($_GET['file'])){

    $file = basename($_GET['file']); // prevent directory traversal
    $filePath = dirname(__DIR__) . "/uploads/tour_orders/" . $file;

    if(file_exists($filePath)){
        header("Content-Type: application/pdf");
        header("Content-Disposition: inline; filename=\"$file\"");
        readfile($filePath);
        exit;
    } else {
        echo "File not found.";
    }

} else {
    echo "Invalid request.";
}
?>
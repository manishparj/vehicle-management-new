<?php
include("../config/db.php");

if(!isset($_SESSION['user'])){
header("location:login.php");
exit();
}

$id = $_GET['id'];
$type = $_POST['type'];
$remark = $_POST['remark'];

$conn->query("
UPDATE vehicle_applications 
SET status='$type', remark='$remark'
WHERE id='$id'
");

header("location:dashboard_forwarded.php");
?>
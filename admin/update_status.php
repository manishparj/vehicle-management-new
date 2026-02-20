<?php
include("../config/db.php");

$id=$_GET['id'];
$type=$_GET['type'];

$app = $conn->query("SELECT * FROM vehicle_applications WHERE id='$id'");
$data=$app->fetch_assoc();

$conn->query("UPDATE vehicle_applications SET status='$type' WHERE id='$id'");

if($type=='Completed' OR $type=='Cancelled'){
$conn->query("UPDATE drivers SET status='Available' WHERE id='".$data['driver_id']."'");
$conn->query("UPDATE vehicles SET status='Available' WHERE id='".$data['vehicle_id']."'");
}

header("location:dashboard_super.php");
?>
<?php include("../config/db.php"); ?>
<!DOCTYPE html>
<html>
<head>
<title>Check Status</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include('../header.php'); ?>

<div class="container mt-5">
<div class="card shadow p-4">

<h4 class="mb-3">Check Vehicle Application Status</h4>

<form method="POST">
<div class="row">
<div class="col-md-9">
<input type="text" name="mobile" class="form-control" placeholder="Enter Mobile Number" required>
</div>
<div class="col-md-3">
<button class="btn btn-primary w-100">Check Status</button>
</div>
</div>
</form>

<?php
if(isset($_POST['mobile'])){

$mobile = $_POST['mobile'];

$res=$conn->query("
SELECT va.*, 
d.driver_name, d.driver_mob,
v.vehicle_type, v.vehicle_name, v.vehicle_no,
u.name AS forwarded_admin_name, u.designation
FROM vehicle_applications va
LEFT JOIN drivers d ON va.driver_id=d.id
LEFT JOIN vehicles v ON va.vehicle_id=v.id
LEFT JOIN users u ON va.forwarded_to=u.id
WHERE va.mobile='$mobile'
ORDER BY va.created_at DESC
");

if($res->num_rows > 0){

while($row=$res->fetch_assoc()){
?>

<div class="card mt-4 border-primary">
<div class="card-header bg-primary text-white">
Application ID: <?= $row['id'] ?> | Status: <?= $row['status'] ?>
</div>

<div class="card-body">

<h5 class="text-success">Applicant Details</h5>
<p>
<b>Name:</b> <?= $row['applicant_name'] ?><br>
<b>Mobile:</b> <?= $row['mobile'] ?><br>
<b>Email:</b> <?= $row['email'] ?>
</p>

<hr>

<h5 class="text-success">Journey Details</h5>
<p>
<b>Vehicle Required:</b> <?= $row['vehicle_required'] ?><br>
<b>Date & Time From:</b> <?= $row['datetime_from'] ?><br>
<b>Date & Time To:</b> <?= $row['datetime_to'] ?><br>
<b>Place of Visit:</b> <?= $row['place_of_visit'] ?><br>
<b>Purpose:</b> <?= $row['purpose'] ?>
</p>

<hr>

<h5 class="text-success">Co-Travelers</h5>
<ul>
<?php
for($i=1;$i<=4;$i++){
if(!empty($row["cotraveler$i"])){
echo "<li>".$row["cotraveler$i"]."</li>";
}
}
?>
</ul>

<hr>

<h5 class="text-success">Forwarded To</h5>
<p>
<?= $row['forwarded_admin_name'] ?> (<?= $row['designation'] ?>)
</p>

<hr>

<h5 class="text-success">Approval & Assignment Details</h5>

<?php if($row['status']=="Approved" || $row['status']=="Completed" || $row['status']=="Cancelled"){ ?>

<p>
<b>Driver Assigned:</b> 
<?= $row['driver_name'] ? $row['driver_name']." (".$row['driver_mob'].")" : "Not Assigned Yet" ?><br>

<b>Vehicle Assigned:</b> 
<?= $row['vehicle_name'] ? $row['vehicle_type']." - ".$row['vehicle_name']." (".$row['vehicle_no'].")" : "Not Assigned Yet" ?>
</p>

<?php } else { ?>
<p class="text-warning">Driver & Vehicle not assigned yet.</p>
<?php } ?>

<hr>

<h5 class="text-success">Remarks</h5>
<p><?= $row['remark'] ? $row['remark'] : "No Remarks" ?></p>

<hr>

<p class="text-muted">
<b>Application Submitted On:</b> <?= $row['created_at'] ?>
</p>

</div>
</div>

<?php
}
}else{
echo "<div class='alert alert-danger mt-4'>No application found for this mobile number.</div>";
}
}
?>

</div>
</div>

</body>
</html>
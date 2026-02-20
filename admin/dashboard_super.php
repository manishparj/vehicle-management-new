<?php
include("../config/db.php");

# -------------------------
# HANDLE APPROVE / REJECT
# -------------------------
if(isset($_POST['approve'])){

$id = $_POST['app_id'];
$driver_id = $_POST['driver_id'];
$vehicle_id = $_POST['vehicle_id'];
$remark = $_POST['remark'];

$conn->query("UPDATE vehicle_applications SET 
status='Approved',
driver_id='$driver_id',
vehicle_id='$vehicle_id',
remark='$remark'
WHERE id='$id'");

$conn->query("UPDATE drivers SET status='Assigned' WHERE id='$driver_id'");
$conn->query("UPDATE vehicles SET status='Assigned' WHERE id='$vehicle_id'");
}

if(isset($_POST['reject'])){
$id = $_POST['app_id'];
$remark = $_POST['remark'];

$conn->query("UPDATE vehicle_applications 
SET status='Rejected', remark='$remark' 
WHERE id='$id'");
}

$res = $conn->query("SELECT * FROM vehicle_applications ORDER BY id DESC");
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Super Admin Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>
body{
    background:#f4f6f9;
}
.card{
    border-radius:15px;
}
.table{
    vertical-align: middle;
}
</style>
</head>

<body>

<div class="container py-4">

<h3 class="fw-bold text-primary mb-4">
<i class="bi bi-speedometer2"></i> Super Admin Dashboard
</h3>
<a href="logout.php" class="btn btn-danger btn-sm">Logout</a>

<div class="card shadow">
<div class="card-body">

<div class="table-responsive">
<table class="table table-hover">

<thead class="table-light">
<tr>
<th>#</th>
<th>User Details</th>
<th>Journey</th>
<th>Driver Details</th>
<th>Vehicle Details</th>
<th>Status</th>
<th class="text-center">Action</th>
</tr>
</thead>

<tbody>
<?php 
$i=1;
while($row=$res->fetch_assoc()){ 
?>
<tr>
<td><?= $i++ ?></td>

<!-- USER FULL DETAILS -->
<td>
<strong><?= $row['applicant_name'] ?></strong><br>
Mobile: <?= $row['mobile'] ?><br>
Email: <?= $row['email'] ?><br>
Purpose: <?= $row['purpose'] ?><br>
Vehicle Required: <?= $row['vehicle_required'] ?>
</td>

<!-- JOURNEY DETAILS -->
<td>
From: <?= $row['datetime_from'] ?><br>
To: <?= $row['datetime_to'] ?><br>
Place: <?= $row['place_of_visit'] ?>
</td>

<!-- DRIVER DETAILS -->
<td>
<?php
if($row['status']=="Approved" || $row['status']=="Completed"){

    $driver = $conn->query("SELECT * FROM drivers WHERE id='".$row['driver_id']."'")->fetch_assoc();

    if($driver){
        echo "Name: ".$driver['driver_name']."<br>";
        echo "Mobile: ".$driver['driver_mob'];
    } else {
        echo "<span class='text-muted'>Not Assigned</span>";
    }

}else{
    echo "<span class='text-muted'>Not Assigned</span>";
}
?>
</td>

<!-- VEHICLE DETAILS -->
<td>
<?php
if($row['status']=="Approved" || $row['status']=="Completed"){

    $vehicle = $conn->query("SELECT * FROM vehicles WHERE id='".$row['vehicle_id']."'")->fetch_assoc();

    if($vehicle){
        echo "Type: ".$vehicle['vehicle_type']."<br>";
        echo "Name: ".$vehicle['vehicle_name']."<br>";
        echo "No: ".$vehicle['vehicle_no'];
    } else {
        echo "<span class='text-muted'>Not Assigned</span>";
    }

}else{
    echo "<span class='text-muted'>Not Assigned</span>";
}
?>
</td>

<!-- STATUS -->
<td>
<?php 
$status = $row['status'];

if($status == "Pending"){
    echo '<span class="badge bg-secondary">Pending</span>';
}
elseif($status == "Recommended"){
    echo '<span class="badge bg-warning text-dark">Recommended</span>';
}
elseif($status == "Not Recommended"){
    echo '<span class="badge bg-danger">Not Recommended</span>';
}
elseif($status == "Approved"){
    echo '<span class="badge bg-success">Approved</span>';
}
elseif($status == "Rejected"){
    echo '<span class="badge bg-dark">Rejected</span>';
}
elseif($status == "Completed"){
    echo '<span class="badge bg-primary">Completed</span>';
}
elseif($status == "Cancelled"){
    echo '<span class="badge bg-danger">Cancelled</span>';
}
else{
    echo '<span class="badge bg-secondary">Pending</span>';
}
?>
</td>

<!-- ACTION -->
<td class="text-center">

<?php if($status=="Recommended"){ ?>
<button 
class="btn btn-sm btn-primary"
data-bs-toggle="modal"
data-bs-target="#approveModal<?= $row['id'] ?>">
<i class="bi bi-pencil-square"></i>
</button>
<?php } ?>

<?php if($status=="Approved"){ ?>
<a href="update_status.php?id=<?= $row['id'] ?>&type=Completed"
class="btn btn-sm btn-success">
<i class="bi bi-check-circle"></i>
</a>

<a href="update_status.php?id=<?= $row['id'] ?>&type=Cancelled"
class="btn btn-sm btn-danger">
<i class="bi bi-x-circle"></i>
</a>
<?php } ?>

</td>
</tr>

<!-- ================= MODAL ================= -->

<div class="modal fade" id="approveModal<?= $row['id'] ?>">
<div class="modal-dialog">
<div class="modal-content">

<form method="POST">

<div class="modal-header">
<h5 class="modal-title">Approve / Reject</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">

<input type="hidden" name="app_id" value="<?= $row['id'] ?>">

<div class="mb-3">
<label>Select Driver</label>
<select name="driver_id" class="form-select">
<option value="">-- Select Available Driver --</option>
<?php
$d = $conn->query("SELECT * FROM drivers WHERE status='Available'");
while($dr=$d->fetch_assoc()){
echo "<option value='".$dr['id']."'>".$dr['driver_name']." (".$dr['driver_mob'].")</option>";
}
?>
</select>
</div>

<div class="mb-3">
<label>Select Vehicle</label>
<select name="vehicle_id" class="form-select">
<option value="">-- Select Available Vehicle --</option>
<?php
$v = $conn->query("SELECT * FROM vehicles WHERE status='Available'");
while($vh=$v->fetch_assoc()){
echo "<option value='".$vh['id']."'>".$vh['vehicle_type']." - ".$vh['vehicle_name']." (".$vh['vehicle_no'].")</option>";
}
?>
</select>
</div>

<div class="mb-3">
<label>Remark</label>
<textarea name="remark" class="form-control"></textarea>
</div>

</div>

<div class="modal-footer">
<button name="approve" class="btn btn-success">
<i class="bi bi-check-circle-fill"></i> Approve
</button>

<button name="reject" class="btn btn-danger">
<i class="bi bi-x-circle-fill"></i> Reject
</button>
</div>

</form>

</div>
</div>
</div>

<?php } ?>
</tbody>
</table>
</div>

</div>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
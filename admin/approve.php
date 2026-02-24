<?php
include("../config/db.php");
$id = $_GET['id'];

if(isset($_POST['approve'])){
$driver_id = $_POST['driver_id'];
$vehicle_id = $_POST['vehicle_id'];

$conn->query("UPDATE vehicle_applications SET 
status='Approved',
driver_id='$driver_id',
vehicle_id='$vehicle_id',
remark='$_POST[remark]'
WHERE id='$id'");

// $conn->query("UPDATE drivers SET status='Assigned' WHERE id='$driver_id'");
// $conn->query("UPDATE vehicles SET status='Assigned' WHERE id='$vehicle_id'");

header("location:dashboard_super.php");
}

if(isset($_POST['reject'])){
$conn->query("UPDATE vehicle_applications 
SET status='Rejected', remark='$_POST[remark]' 
WHERE id='$id'");
header("location:dashboard_super.php");
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Approve Request</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>
body{
    background: linear-gradient(135deg,#667eea,#764ba2);
    min-height:100vh;
}
.card{
    border-radius:20px;
}
</style>
</head>

<body>

<div class="container py-5">

<div class="row justify-content-center">
<div class="col-lg-6 col-md-8">

<div class="card shadow-lg p-4">

<h4 class="text-center text-primary mb-4">
<i class="bi bi-clipboard-check"></i> Approve / Reject Application
</h4>

<form method="POST">

<div class="mb-3">
<label class="form-label">Select Driver</label>
<select name="driver_id" class="form-select" required>
<option value="">-- Select Available Driver --</option>
<?php
$d = $conn->query("SELECT * FROM drivers");
while($row=$d->fetch_assoc()){
echo "<option value='".$row['id']."'>".$row['driver_name']." (".$row['driver_mob'].")</option>";
}
?>
</select>
</div>

<div class="mb-3">
<label class="form-label">Select Vehicle</label>
<select name="vehicle_id" class="form-select" required>
<option value="">-- Select Available Vehicle --</option>
<?php
$v = $conn->query("SELECT * FROM vehicles");
while($row=$v->fetch_assoc()){
echo "<option value='".$row['id']."'>".$row['vehicle_type']." - ".$row['vehicle_name']." (".$row['vehicle_no'].")</option>";
}
?>
</select>
</div>

<div class="mb-3">
<label class="form-label">Remark</label>
<textarea name="remark" class="form-control" rows="3" placeholder="Enter remark"></textarea>
</div>

<div class="d-grid gap-2">

<button name="approve" class="btn btn-success btn-lg">
<i class="bi bi-check-circle-fill"></i> Approve
</button>

<button name="reject" class="btn btn-danger btn-lg">
<i class="bi bi-x-circle-fill"></i> Reject
</button>

<a href="dashboard_super.php" class="btn btn-secondary">
<i class="bi bi-arrow-left"></i> Back
</a>

</div>

</form>

</div>

</div>
</div>

</div>

</body>
</html>
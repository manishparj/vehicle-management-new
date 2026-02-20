<?php 
include("../config/db.php");

if(!isset($_SESSION['user']) || $_SESSION['user']['role']!='forwarded_admin'){
    header("location:login.php");
    exit();
}

$user = $_SESSION['user'];

$res=$conn->query("
SELECT va.*, u.name AS forwarded_admin_name 
FROM vehicle_applications va
LEFT JOIN users u ON va.forwarded_to=u.id
WHERE va.forwarded_to=".$user['id']."
ORDER BY va.created_at DESC
");
?>

<!DOCTYPE html>
<html>
<head>
<title>Forwarded Admin Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-4">

<div class="d-flex justify-content-between mb-3">
<h3>Forwarded Requests Dashboard</h3>
<a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
</div>

<?php if($res->num_rows > 0){ ?>

<?php while($row=$res->fetch_assoc()){ ?>

<div class="card mb-4 shadow-sm">
<div class="card-header bg-dark text-white">
Application ID: <?= $row['id'] ?>

<span class="float-end">
<?php
$status = $row['status'];
$badge="secondary";

if($status=="Pending") $badge="warning";
if($status=="Recommended") $badge="success";
if($status=="Not Recommended") $badge="danger";
if($status=="Approved") $badge="primary";
if($status=="Rejected") $badge="danger";
if($status=="Completed") $badge="success";
if($status=="Cancelled") $badge="dark";

echo "<span class='badge bg-$badge'>$status</span>";
?>
</span>

</div>

<div class="card-body">

<div class="row">

<div class="col-md-6">
<h5 class="text-primary">Applicant Details</h5>
<p>
<b>Name:</b> <?= $row['applicant_name'] ?><br>
<b>Mobile:</b> <?= $row['mobile'] ?><br>
<b>Email:</b> <?= $row['email'] ?>
</p>
</div>

<div class="col-md-6">
<h5 class="text-primary">Journey Details</h5>
<p>
<b>Vehicle Required:</b> <?= $row['vehicle_required'] ?><br>
<b>From:</b> <?= $row['datetime_from'] ?><br>
<b>To:</b> <?= $row['datetime_to'] ?><br>
<b>Place:</b> <?= $row['place_of_visit'] ?><br>
<b>Purpose:</b> <?= $row['purpose'] ?>
</p>
</div>

</div>

<hr>

<h6 class="text-primary">Co-Travelers</h6>
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

<?php if($row['status']=="Pending"){ ?>

<form method="POST" action="recommend.php?id=<?= $row['id'] ?>" class="row g-2">

<div class="col-md-8">
<textarea name="remark" class="form-control" placeholder="Optional Remark"></textarea>
</div>

<div class="col-md-2 d-grid">
<button name="type" value="Recommended" class="btn btn-success">
Recommend
</button>
</div>

<div class="col-md-2 d-grid">
<button name="type" value="Not Recommended" class="btn btn-danger">
Not Recommend
</button>
</div>

</form>

<?php } else { ?>

<p><b>Remark:</b> <?= $row['remark'] ? $row['remark'] : "No Remark" ?></p>

<?php } ?>

<p class="text-muted mt-2">
Submitted On: <?= $row['created_at'] ?>
</p>

</div>
</div>

<?php } ?>

<?php } else { ?>

<div class="alert alert-info">
No forwarded applications found.
</div>

<?php } ?>

</div>
</body>
</html>
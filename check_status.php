<?php include("./config/db.php"); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Check Vehicle Application Status</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">


</head>
<body>
<?php include('./header.php'); ?>

<div class="container mt-5">
<div class="card shadow p-4">

<h4 class="mb-4 text-center text-primary fw-bold">
<i class="bi bi-clipboard-check"></i> Check Vehicle Application Status
</h4>

<form method="POST" class="row g-2 mb-4">
<div class="col-md-9">
<input type="text" name="mobile" class="form-control" placeholder="Enter Mobile Number" required>
</div>
<div class="col-md-3 d-grid">
<button class="btn btn-primary"><i class="bi bi-search"></i> Check Status</button>
</div>
</form>

<?php
if(isset($_POST['mobile'])){
$mobile = $_POST['mobile'];

$res = $conn->query("
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

if($res && $res->num_rows > 0){
while($row=$res->fetch_assoc()){
?>

<div class="card mt-4 border-primary">
<div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
<span><i class="bi bi-file-earmark-text"></i> Application ID: <?= $row['id'] ?></span>
<?php
$status = $row['status'];
$badgeClass = "secondary";

if($status=="Pending") $badgeClass="warning text-dark";
elseif($status=="Recommended") $badgeClass="info";
elseif($status=="Approved") $badgeClass="success";
elseif($status=="Rejected") $badgeClass="danger";
elseif($status=="Completed") $badgeClass="primary";
elseif($status=="Cancelled") $badgeClass="dark";

echo "<span class='badge badge-status bg-$badgeClass'>$status</span>";
?>
</div>

<div class="card-body">

<!-- Main two-column layout -->
<div class="row card-columns g-4">

<!-- LEFT COLUMN: Applicant + Co-Travelers -->
<div class="col-md-6">
<h6 class="section-title"><i class="bi bi-person-circle"></i> Applicant Details</h6>
<p>
<b>Booking For:</b> <?= htmlspecialchars($row['booking_for']) ?><br>

<?php if($row['booking_for'] == "Someone" && !empty($row['other_person_name'])){ ?>
<b>Person Name:</b> <?= htmlspecialchars($row['other_person_name']) ?><br>
<b>Applicant Name:</b> <?= htmlspecialchars($row['applicant_name']) ?><br>
<?php } else { ?>
<b>Name:</b> <?= htmlspecialchars($row['applicant_name']) ?><br>
<?php } ?>
<b>Staff Type:</b> <?= htmlspecialchars($row['staff_type']) ?><br>

<b>Mobile:</b> <?= htmlspecialchars($row['mobile']) ?><br>
<b>Email:</b> <?= htmlspecialchars($row['email']) ?><br>
<b>Purpose:</b> <?= htmlspecialchars($row['purpose']) ?>
</p>

<h6 class="section-title"><i class="bi bi-people-fill"></i> Co-Travelers</h6>
<ul>
<?php
$hasCoTravelers = false;
for($i=1;$i<=6;$i++){
if(!empty($row["cotraveler$i"])){
$hasCoTravelers = true;
echo "<li>".$row["cotraveler$i"]."</li>";
}
}
if(!$hasCoTravelers){ echo "<li class='text-muted'>No Co-Travelers</li>"; }
?>
</ul>
</div>

<!-- RIGHT COLUMN: Journey + Assignment + Forwarded + Remarks -->
<div class="col-md-6">
<h6 class="section-title"><i class="bi bi-geo-alt-fill"></i> Journey Details</h6>
<p>
<b>Vehicle Required:</b> <?= $row['vehicle_required'] ?><br>
<b>Date & Time From:</b> <?= $row['datetime_from'] ?><br>
<b>Date & Time To:</b> <?= $row['datetime_to'] ?><br>
<b>Place of Visit:</b> <?= $row['place_of_visit'] ?>
</p>

<h6 class="section-title"><i class="bi bi-person-badge-fill"></i> Forwarded To</h6>
<p><?= $row['forwarded_admin_name'] ?> (<?= $row['designation'] ?>)</p>

<h6 class="section-title"><i class="bi bi-truck"></i> Vehicle & Driver Details</h6>
<?php if(in_array($row['status'], ['Approved','Completed','Cancelled', 'Started', 'Pending', 'In Progress', 'Departure'])){ ?>
<p>
<b>Driver Assigned:</b> <?= $row['driver_name'] ? $row['driver_name']." (".$row['driver_mob'].")" : "Not Assigned Yet" ?><br>
<b>Vehicle Assigned:</b> <?= $row['vehicle_name'] ? $row['vehicle_type']." - ".$row['vehicle_name']." (".$row['vehicle_no'].")" : "Not Assigned Yet" ?>
</p>
<?php } else { ?>
<p class="text-warning"><i class="bi bi-exclamation-triangle-fill"></i> Driver & Vehicle not assigned yet.</p>
<?php } ?>

<h6 class="section-title"><i class="bi bi-chat-left-text-fill"></i> Remarks</h6>
<p><?= $row['remark'] ? $row['remark'] : "No Remarks" ?></p>

<p class="text-muted"><b>Application Submitted On:</b> <?= $row['created_at'] ?></p>
</div>

</div> <!-- end row -->

</div>
</div>

<?php
}
} else {
echo "<div class='alert alert-danger mt-4'><i class='bi bi-x-circle-fill'></i> No application found for this mobile number.</div>";
}
}
?>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php include("../config/db.php"); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vehicle Application</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>
body {
    background: linear-gradient(135deg, #4e73df, #1cc88a);
    min-height: 100vh;
}

.card {
    border-radius: 20px;
    border: none;
}

.card-header {
    background: transparent;
    border-bottom: none;
}

.form-control, .form-select {
    border-radius: 10px;
}

.btn-primary {
    border-radius: 10px;
    padding: 10px;
    font-weight: 600;
}

.section-title {
    font-weight: 600;
    margin-top: 20px;
    margin-bottom: 10px;
    color: #4e73df;
}

@media (max-width: 576px) {
    .card {
        padding: 15px !important;
    }
}
</style>
</head>

<body>
    <?php include('../header.php'); ?>

<div class="container py-5">
<div class="row justify-content-center">
<div class="col-lg-8 col-md-10 col-sm-12">

<div class="card shadow-lg p-4">

<div class="card-header text-center mb-3">
<h3 class="fw-bold text-primary">
<i class="bi bi-car-front-fill"></i> Vehicle Application Form
</h3>
<p class="text-muted">Fill the details carefully</p>
</div>


<form method="POST" action="submit_application.php">

<div class="row">
<div class="col-md-6 mb-3">
<label class="form-label">Applicant Name</label>
<input type="text" name="applicant_name" class="form-control" placeholder="Enter full name" required>
</div>

<div class="col-md-6 mb-3">
<label class="form-label">Vehicle Required</label>
<select name="vehicle_required" class="form-select">
<option value="Car">Car</option>
<option value="Bus">Bus</option>
</select>
</div>
</div>

<div class="row">
<div class="col-md-6 mb-3">
<label class="form-label">Date & Time From</label>
<input type="datetime-local" name="datetime_from" class="form-control" required>
</div>

<div class="col-md-6 mb-3">
<label class="form-label">Date & Time To</label>
<input type="datetime-local" name="datetime_to" class="form-control" required>
</div>
</div>

<div class="mb-3">
<label class="form-label">Place of Visit</label>
<input type="text" name="place_of_visit" class="form-control" placeholder="Enter destination">
</div>

<div class="mb-3">
<label class="form-label">Purpose</label>
<textarea name="purpose" class="form-control" rows="3" placeholder="Enter purpose of visit"></textarea>
</div>

<hr>

<h5 class="section-title"><i class="bi bi-people-fill"></i> Co-Travelers (Optional)</h5>

<div class="row">
<div class="col-md-6 mb-2">
<input type="text" name="cotraveler1" class="form-control" placeholder="Co-Traveler 1">
</div>
<div class="col-md-6 mb-2">
<input type="text" name="cotraveler2" class="form-control" placeholder="Co-Traveler 2">
</div>
<div class="col-md-6 mb-2">
<input type="text" name="cotraveler3" class="form-control" placeholder="Co-Traveler 3">
</div>
<div class="col-md-6 mb-2">
<input type="text" name="cotraveler4" class="form-control" placeholder="Co-Traveler 4">
</div>
</div>

<hr>

<h5 class="section-title"><i class="bi bi-telephone-fill"></i> Contact Details</h5>

<div class="row">
<div class="col-md-6 mb-3">
<label class="form-label">Mobile No</label>
<input type="text" name="mobile" class="form-control" placeholder="Enter mobile number" required>
</div>

<div class="col-md-6 mb-3">
<label class="form-label">Email</label>
<input type="email" name="email" class="form-control" placeholder="Enter email address">
</div>
</div>

<div class="mb-4">
<label class="form-label">Forward To</label>
<select name="forwarded_to" class="form-select">
<?php
$result = $conn->query("SELECT * FROM users WHERE role='forwarded_admin'");
while($row=$result->fetch_assoc()){
echo "<option value='".$row['id']."'>".$row['name']." (".$row['designation'].")</option>";
}
?>
</select>
</div>

<div class="d-grid">
<button class="btn btn-primary btn-lg">
<i class="bi bi-send-fill"></i> Submit Application
</button>
</div>

</form>
</div>

</div>
</div>
</div>

</body>
</html>
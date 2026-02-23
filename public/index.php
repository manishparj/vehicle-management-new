<?php include("../config/db.php"); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vehicle Application</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
body {
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #4e73df, #1cc88a);
    min-height: 100vh;
}

/* Glass Card */
.glass-card {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(15px);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
    border: 1px solid rgba(255,255,255,0.3);
}

/* Section Title */
.section-title {
    font-weight: 600;
    margin-top: 25px;
    margin-bottom: 15px;
    color: #fff;
    border-left: 4px solid #fff;
    padding-left: 10px;
}

/* Inputs */
.form-control, .form-select {
    border-radius: 12px;
    padding: 12px 15px;
    border: none;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.form-control:focus, .form-select:focus {
    box-shadow: 0 0 0 0.2rem rgba(78,115,223,.25);
}

/* Button */
.btn-custom {
    border-radius: 12px;
    padding: 12px;
    font-weight: 600;
    font-size: 16px;
    background: linear-gradient(45deg, #4e73df, #1cc88a);
    border: none;
    transition: 0.3s ease;
}

.btn-custom:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
}

/* Icon inside input */
.input-group-text {
    background: #fff;
    border: none;
    border-radius: 12px 0 0 12px;
}

/* Responsive padding */
@media (max-width: 576px) {
    .glass-card {
        padding: 20px !important;
    }
}
</style>
</head>

<body>
<?php include('../header.php'); ?>

<div class="container py-5">
<div class="row justify-content-center">
<div class="col-lg-9 col-md-11 col-sm-12">

<div class="glass-card p-4 text-dark">

<div class="text-center mb-4 text-white">
<h3 class="fw-bold">
<i class="bi bi-car-front-fill"></i> Vehicle Application Form
</h3>
<p class="small">Please fill all required details carefully</p>
</div>

<form method="POST" action="submit_application.php">

<h5 class="section-title text-white"><i class="bi bi-person-fill"></i> Applicant Details</h5>

<div class="row">
<div class="col-md-6 mb-3">
<div class="input-group">
<span class="input-group-text"><i class="bi bi-person"></i></span>
<input type="text" name="applicant_name" class="form-control" placeholder="Full Name" required>
</div>
</div>

<div class="col-md-6 mb-3">
<div class="input-group">
<span class="input-group-text"><i class="bi bi-truck"></i></span>
<select name="vehicle_required" class="form-select">
<option value="Car">🚗 Car</option>
<option value="Bus">🚌 Bus</option>
</select>
</div>
</div>
</div>

<div class="row">
<div class="col-md-6 mb-3">
<div class="input-group">
<span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
<input type="datetime-local" name="datetime_from" class="form-control" required>
</div>
</div>

<div class="col-md-6 mb-3">
<div class="input-group">
<span class="input-group-text"><i class="bi bi-calendar-check"></i></span>
<input type="datetime-local" name="datetime_to" class="form-control" required>
</div>
</div>
</div>

<div class="mb-3">
<div class="input-group">
<span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
<input type="text" name="place_of_visit" class="form-control" placeholder="Place of Visit">
</div>
</div>

<div class="mb-3">
<div class="input-group">
<span class="input-group-text"><i class="bi bi-chat-left-text"></i></span>
<textarea name="purpose" class="form-control" rows="3" placeholder="Purpose of Visit"></textarea>
</div>
</div>

<h5 class="section-title text-white"><i class="bi bi-people-fill"></i> Co-Travelers</h5>

<div class="row">
<?php for($i=1;$i<=4;$i++){ ?>
<div class="col-md-6 mb-2">
<div class="input-group">
<span class="input-group-text"><i class="bi bi-person-plus"></i></span>
<input type="text" name="cotraveler<?php echo $i; ?>" class="form-control" placeholder="Co-Traveler <?php echo $i; ?>">
</div>
</div>
<?php } ?>
</div>

<h5 class="section-title text-white"><i class="bi bi-telephone-fill"></i> Contact Details</h5>

<div class="row">
<div class="col-md-6 mb-3">
<div class="input-group">
<span class="input-group-text"><i class="bi bi-phone"></i></span>
<input type="text" name="mobile" class="form-control" placeholder="Mobile Number" required>
</div>
</div>

<div class="col-md-6 mb-3">
<div class="input-group">
<span class="input-group-text"><i class="bi bi-envelope"></i></span>
<input type="email" name="email" class="form-control" placeholder="Email Address">
</div>
</div>
</div>

<div class="mb-4">
<div class="input-group">
<span class="input-group-text"><i class="bi bi-person-check"></i></span>
<select name="forwarded_to" class="form-select">
<?php
$result = $conn->query("SELECT * FROM users WHERE role='forwarded_admin'");
while($row=$result->fetch_assoc()){
echo "<option value='".$row['id']."'>".$row['name']." (".$row['designation'].")</option>";
}
?>
</select>
</div>
</div>

<div class="d-grid">
<button class="btn btn-custom btn-lg text-white">
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
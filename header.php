<?php
// Define the base URL to avoid path issues between admin and public folders
$base_url = "http://localhost/vehicle_app/";
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?php echo $base_url; ?>public/index.php">Vehicle App</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link" href="<?php echo $base_url; ?>public/index.php">Home</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?php echo $base_url; ?>public/check_status.php">Check Status</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?php echo $base_url; ?>admin/login.php">Admin Login</a>
        </li>
      </ul>
    </div>
  </div>
</nav>
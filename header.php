<?php
$base_url = "http://192.168.17.207/vehicle_app/";
?>

<!-- Google Font -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
body {
    font-family: 'Poppins', sans-serif;
}

.navbar {
    background: linear-gradient(90deg, #0f2027, #203a43, #2c5364);
    padding: 12px 20px;
}

.navbar-brand {
    font-size: 22px;
    font-weight: 600;
    letter-spacing: 1px;
    color: #ffffff !important;
}

.navbar-brand:hover {
    color: #00d4ff !important;
}

.nav-link {
    color: #e0e0e0 !important;
    font-weight: 500;
    margin-right: 15px;
    transition: all 0.3s ease;
}

.nav-link:hover {
    color: #00d4ff !important;
    transform: translateY(-2px);
}

.navbar-toggler {
    border: none;
}

.navbar-toggler:focus {
    box-shadow: none;
}

/* Active link style */
.nav-link.active {
    color: #00d4ff !important;
    border-bottom: 2px solid #00d4ff;
}

/* Mobile spacing */
@media (max-width: 991px) {
    .nav-link {
        margin: 8px 0;
    }
}
</style>

<nav class="navbar navbar-expand-lg navbar-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?php echo $base_url; ?>public/index.php">
       Vehicle App
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link active" href="<?php echo $base_url; ?>public/index.php">Home</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?php echo $base_url; ?>public/check_status.php">Check Status</a>
        </li>
        <li class="nav-item">
          <a class="nav-link btn btn-outline-info px-3 rounded-pill ms-lg-3" 
             href="<?php echo $base_url; ?>admin/login.php">
             Admin Login
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>
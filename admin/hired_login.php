<?php
session_start();
include("../config/db.php");

// Redirect if already logged in
if (isset($_SESSION['hired_user_id']) && isset($_SESSION['hired_role'])) {
    if ($_SESSION['hired_role'] === 'hired_super_admin') {
        header("Location: hired_super_admin_dashboard.php");
    } elseif ($_SESSION['hired_role'] === 'hired_forwarded_admin') {
        header("Location: hired_forwarded_admin_dashboard.php");
    } elseif ($_SESSION['hired_role'] === 'hired_agency') {
        header("Location: hired_agency_dashboard.php"); // We will create a placeholder for this
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $userFound = false;

        // 1. CHECK APPLICANTS TABLE (Admins)
        $stmt = $conn->prepare("SELECT id, name, email, password, role FROM hired_applicants WHERE email = ? AND role IN ('hired_super_admin', 'hired_forwarded_admin')");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['hired_user_id'] = $user['id'];
                $_SESSION['hired_user_name'] = $user['name'];
                $_SESSION['hired_user_email'] = $user['email'];
                $_SESSION['hired_role'] = $user['role'];

                if ($user['role'] === 'hired_super_admin') {
                    header("Location: hired_super_admin_dashboard.php");
                } else {
                    header("Location: hired_forwarded_admin_dashboard.php");
                }
                exit;
            }
            $userFound = true; // Email found, but password was wrong
        }
        $stmt->close();

        // 2. CHECK AGENCIES TABLE (if not found in applicants)
        if (!$userFound) {
            $stmt = $conn->prepare("SELECT id, agency_name, email, password, status FROM hired_agencies WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $agency = $result->fetch_assoc();
                
                if ($agency['status'] === 'Inactive') {
                    $error = 'Your agency account is currently inactive. Please contact the Super Admin.';
                } elseif (password_verify($password, $agency['password'])) {
                    // Agency Login Success
                    $_SESSION['hired_user_id'] = $agency['id'];
                    $_SESSION['hired_user_name'] = $agency['agency_name'];
                    $_SESSION['hired_user_email'] = $agency['email'];
                    $_SESSION['hired_role'] = 'hired_agency';

                    header("Location: hired_agency_dashboard.php");
                    exit;
                } else {
                    $error = 'Invalid email or password.';
                }
            } else {
                $error = 'Invalid email or password.';
            }
            $stmt->close();
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hired Vehicle - Login</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="./login.css">
</head>
<body>

<?php include('../header.php'); ?>

<div class="bg-blobs" aria-hidden="true">
  <span></span><span></span><span></span><span></span>
</div>

<main class="login-wrap" role="main">

  <section class="login-hero">
    <span class="hero-badge">
      <i class="bi bi-shield-check"></i> Secure Access
    </span>
    <h1>
      Manage your <span class="grad">Hired Vehicle fleet</span><br>with confidence
    </h1>
    <p>
      Sign in to review applications, approve tour requests, and keep
      ICMR-NIHR Jodhpur transport operations running smoothly — all from
      one secure dashboard.
    </p>
    <div class="hero-features">
      <div class="hf"><i class="bi bi-person-badge-fill"></i> Admin &amp; Agency login in one place</div>
      <div class="hf"><i class="bi bi-speedometer2"></i> Real-time request tracking</div>
      <div class="hf"><i class="bi bi-people-fill"></i> Role-based access control</div>
    </div>
  </section>

  <section class="login-card-shell">
    <div class="login-card">

      <div class="card-icon"><i class="bi bi-shield-lock-fill"></i></div>
      <h1 class="card-title">Welcome to Hired Vehicle App</h1>
      <p class="card-sub">Sign in to continue to your dashboard</p>
      
      <?php if($error != ""){ ?>
        <div class="alert-soft" role="alert">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <div><?php echo htmlspecialchars($error); ?></div>
        </div>
      <?php } ?>

      <form method="POST" autocomplete="off">

        <div class="field">
          <label class="field-label" for="email">Email Address</label>
          <div class="field-input">
            <span class="field-icon">
              <i class="bi bi-envelope-fill"></i>
            </span>
            <input type="email" name="email" class="form-control" placeholder="your@domain.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          </div>
        </div>

        <div class="field">
          <label class="field-label" for="password">Password</label>
          <div class="field-input">
            <span class="field-icon"><i class="bi bi-lock-fill"></i></span>
            <input type="password" name="password" id="password" class="form-control" placeholder="Enter password" required>
            <button type="button" class="toggle-pass" id="togglePswd" aria-label="Show password" aria-pressed="false">
              <i class="bi bi-eye" id="toggleIcon"></i>
            </button>
          </div>
        </div>

        <button type="submit" name="login" class="btn-login">
          <i class="bi bi-box-arrow-in-right"></i>
          <span>Sign In</span>
        </button>

      </form>

      <div class="hint" id="modeHint">
        <i class="bi bi-info-circle-fill"></i>
        <span>Admins and Agencies sign in with their <strong>email</strong></span>
      </div>

      <div class="trust-row">
        <span><i class="bi bi-shield-lock-fill"></i> SSL Secured</span>
        <span><i class="bi bi-patch-check-fill"></i> ICMR-NIHR</span>
      </div>

    </div>
  </section>
</main>

<script>
'use strict';
(function(){
  const toggle = document.getElementById('togglePswd');
  const pwd    = document.getElementById('password');
  const icon   = document.getElementById('toggleIcon');

  toggle.addEventListener('click', function(){
    const isPwd = pwd.type === 'password';
    pwd.type = isPwd ? 'text' : 'password';
    icon.className = isPwd ? 'bi bi-eye-slash' : 'bi bi-eye';
    toggle.setAttribute('aria-label', isPwd ? 'Hide password' : 'Show password');
    toggle.setAttribute('aria-pressed', String(isPwd));
    pwd.focus();
  });
})();
</script>
</body>
</html>
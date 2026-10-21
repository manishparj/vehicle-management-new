<?php 
session_start();
include("../config/db.php");

$error = "";
$stickyIdentifier = "";

if(isset($_POST['login'])){

    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';
    $stickyIdentifier = $identifier;

    if ($identifier === '' || $password === '') {
        $error = "Please enter your credentials!";
    }
    /* ==========================================================
       DRIVER LOGIN  →  10-digit mobile number
       ========================================================== */
    elseif (preg_match('/^[0-9]{10}$/', $identifier)) {

        $stmt = $conn->prepare("SELECT id, driver_name, driver_mob, password, status FROM drivers WHERE driver_mob=? LIMIT 1");
        $stmt->bind_param("s", $identifier);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 0) {
            $error = "Invalid Mobile Number or Password!";
        } else {
            $drv = $res->fetch_assoc();

            if (($drv['status'] ?? 'Active') !== 'Active') {
                $error = "Your account is inactive. Please contact the administrator.";
            } elseif (empty($drv['password'])) {
                $error = "No password has been set for this account. Please contact the administrator.";
            } elseif (!password_verify($password, $drv['password'])) {
                $error = "Invalid Mobile Number or Password!";
            } else {
                session_regenerate_id(true);
                $_SESSION['driver'] = [
                    'id'   => (int) $drv['id'],
                    'name' => $drv['driver_name'],
                    'mob'  => $drv['driver_mob'],
                    'role' => 'driver',
                ];
                header("Location: driver_dashboard.php");
                exit();
            }
        }
        $stmt->close();
    }
    /* ==========================================================
       ADMIN / STAFF LOGIN  →  email
       ========================================================== */
    else {

        $stmt = $conn->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $identifier);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 0) {
            $error = "Invalid Email or Password!";
        } else {
            $row = $res->fetch_assoc();

            if (!password_verify($password, $row['password'])) {
                $error = "Invalid Email or Password!";
            } else {
                session_regenerate_id(true);
                $_SESSION['user'] = $row;

                if ($row['role'] === "forwarded_admin") {
                    header("Location: dashboard_forwarded.php");
                } elseif ($row['role'] === "security_admin") {
                    header("Location: security_dashboard.php");
                } elseif ($row['role'] === "super_admin") {
                    header("Location: dashboard_super.php");
                } else {
                    session_unset();
                    session_destroy();
                    $error = "Unknown role. Please contact the administrator.";
                }
                exit();
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In — ICMR-NIHR Transport Portal</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<link rel="stylesheet" href="./login.css">
</head>

<body>

<?php include('../header.php'); ?>

<div class="bg-blobs" aria-hidden="true">
  <span></span><span></span><span></span><span></span>
</div>

<main class="login-wrap" role="main">

  <!-- ══════════════ HERO (desktop only) ══════════════ -->
  <section class="login-hero">
    <span class="hero-badge">
      <i class="bi bi-shield-check"></i> Secure Access
    </span>
    <h1>
      Manage your <span class="grad">vehicle fleet</span><br>with confidence
    </h1>
    <p>
      Sign in to review applications, approve tour requests, and keep
      ICMR-NIHR Jodhpur transport operations running smoothly — all from
      one secure dashboard.
    </p>
    <div class="hero-features">
      <div class="hf"><i class="bi bi-person-badge-fill"></i> Driver &amp; staff login in one place</div>
      <div class="hf"><i class="bi bi-speedometer2"></i> Real-time request tracking</div>
      <div class="hf"><i class="bi bi-people-fill"></i> Role-based access control</div>
    </div>
  </section>

  <!-- ══════════════ LOGIN CARD ══════════════ -->
  <section class="login-card-shell">
    <div class="login-card">

      <div class="card-icon"><i class="bi bi-shield-lock-fill"></i></div>
      <h1 class="card-title">Welcome Back</h1>
      <p class="card-sub">Sign in to continue to your dashboard</p>

      <!-- ═══ Login type tabs ═══ -->
      <div class="login-tabs" role="tablist">
        <button type="button" class="active" id="tabAdmin" data-mode="admin" role="tab" aria-selected="true">
          <i class="bi bi-person-workspace"></i>
          <span>Admin</span>
        </button>
        <button type="button" id="tabDriver" data-mode="driver" role="tab" aria-selected="false">
          <i class="bi bi-person-badge-fill"></i>
          <span>Driver</span>
        </button>
        <a href="hired_login.php" class="login-tab-link" role="tab" aria-selected="false">
          <i class="bi bi-truck"></i>
          <span>Hired</span>
        </a>
      </div>

      <!-- ═══ Error ═══ -->
      <?php if($error != ""){ ?>
        <div class="alert-soft" role="alert">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <div><?php echo htmlspecialchars($error); ?></div>
        </div>
      <?php } ?>

      <!-- ═══ Form ═══ -->
      <form method="POST" id="loginForm" autocomplete="on" novalidate>

        <div class="field">
          <label class="field-label" for="identifier" id="identifierLabel">Email Address</label>
          <div class="field-input">
            <span class="field-icon" id="identifierIcon">
              <i class="bi bi-envelope-fill"></i>
            </span>
            <input
              type="text"
              id="identifier"
              name="identifier"
              placeholder="Enter your email"
              autocomplete="username"
              value="<?= htmlspecialchars($stickyIdentifier) ?>"
              required>
          </div>
        </div>

        <div class="field">
          <label class="field-label" for="password">Password</label>
          <div class="field-input">
            <span class="field-icon"><i class="bi bi-lock-fill"></i></span>
            <input type="password" id="password" name="password"
                   placeholder="Enter your password"
                   autocomplete="current-password" required>
            <button type="button" class="toggle-pass" id="togglePass"
                    aria-label="Show password" aria-pressed="false">
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
        <span>Admins &amp; staff sign in with their <strong>email</strong></span>
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
  /* ---------- Password toggle ---------- */
  const toggle = document.getElementById('togglePass');
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

  /* ---------- Login mode tabs ---------- */
  const tabAdmin  = document.getElementById('tabAdmin');
  const tabDriver = document.getElementById('tabDriver');
  const identifier       = document.getElementById('identifier');
  const identifierLabel  = document.getElementById('identifierLabel');
  const identifierIcon   = document.getElementById('identifierIcon');
  const modeHint         = document.getElementById('modeHint');

  function setMode(mode){
    if (mode === 'driver'){
      tabDriver.classList.add('active');
      tabAdmin.classList.remove('active');
      tabDriver.setAttribute('aria-selected', 'true');
      tabAdmin.setAttribute('aria-selected', 'false');

      identifierLabel.textContent = 'Mobile Number';
      identifierIcon.innerHTML = '<i class="bi bi-telephone-fill"></i>';
      identifier.type = 'tel';
      identifier.setAttribute('inputmode', 'numeric');
      identifier.setAttribute('pattern', '[0-9]{10}');
      identifier.setAttribute('maxlength', '10');
      identifier.placeholder = '10-digit mobile number';
      identifier.setAttribute('autocomplete', 'tel');
      modeHint.innerHTML = '<i class="bi bi-info-circle-fill"></i> <span>Drivers sign in with their <strong>10-digit mobile number</strong></span>';
    } else {
      tabAdmin.classList.add('active');
      tabDriver.classList.remove('active');
      tabAdmin.setAttribute('aria-selected', 'true');
      tabDriver.setAttribute('aria-selected', 'false');

      identifierLabel.textContent = 'Email Address';
      identifierIcon.innerHTML = '<i class="bi bi-envelope-fill"></i>';
      identifier.type = 'email';
      identifier.removeAttribute('inputmode');
      identifier.removeAttribute('pattern');
      identifier.removeAttribute('maxlength');
      identifier.placeholder = 'Enter your email';
      identifier.setAttribute('autocomplete', 'username');
      modeHint.innerHTML = '<i class="bi bi-info-circle-fill"></i> <span>Admins &amp; staff sign in with their <strong>email</strong></span>';
    }
    identifier.value = '';
    identifier.focus();
  }

  tabAdmin.addEventListener('click',  () => setMode('admin'));
  tabDriver.addEventListener('click', () => setMode('driver'));

  /* ---------- Numeric-only for driver mobile ---------- */
  identifier.addEventListener('input', function(){
    if (this.inputMode === 'numeric') {
      const v = this.value.replace(/\D/g, '');
      if (this.value !== v) this.value = v;
    }
  });
})();
</script>
</body>
</html>
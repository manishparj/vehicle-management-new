<?php 
session_start();
include("../config/db.php");

$error = "";

if(isset($_POST['login'])){

    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';

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
                    // fallback — send unknown roles back to login with message
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
<title>Login</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>
/* ═══════════════════════════════════════════
   THEME TOKENS
   ═══════════════════════════════════════════ */
:root{
  --ink:#0f172a;
  --ink-mut:#64748b;
  --line:#e2e8f0;
  --teal:#0e7c86;
  --teal-dark:#0a5f67;
  --acc:#22d3ee;
  --acc-2:#38bdf8;
  --acc-glow:rgba(34,211,238,.35);
  --danger-bg:#fef2f2;
  --danger-line:#fecaca;
  --radius:20px;
  --radius-sm:12px;
  --shadow-lg:0 30px 70px -20px rgba(11,18,32,.5),0 12px 30px -12px rgba(11,18,32,.35);
  --t:.25s cubic-bezier(.4,0,.2,1);
}
*{ box-sizing:border-box; }

html, body{
  margin:0;
  padding:0;
  min-height:100%;
  -webkit-text-size-adjust:100%;
}
body{
  font-family:'Inter',system-ui,-apple-system,sans-serif;
  color:var(--ink);
  background:
    radial-gradient(1200px 600px at 10% -10%, #1e3a5f 0%, transparent 60%),
    radial-gradient(1000px 500px at 110% 10%, #0e7c86 0%, transparent 55%),
    linear-gradient(135deg,#0b1220 0%,#111c33 55%,#0a1a2e 100%);
  min-height:100vh;
  -webkit-font-smoothing:antialiased;
  overflow-x:hidden;
}

.bg-blobs{ position:fixed; inset:0; pointer-events:none; overflow:hidden; z-index:0; }
.bg-blobs span{
  position:absolute; border-radius:50%;
  filter:blur(60px); opacity:.35;
  animation:blobFloat 22s ease-in-out infinite;
}
.bg-blobs span:nth-child(1){
  width:420px;height:420px;
  background:radial-gradient(circle,#22d3ee 0%,transparent 70%);
  top:-120px;left:-120px;
}
.bg-blobs span:nth-child(2){
  width:520px;height:520px;
  background:radial-gradient(circle,#6366f1 0%,transparent 70%);
  bottom:-160px;right:-160px;
  animation-delay:-8s;
}
.bg-blobs span:nth-child(3){
  width:340px;height:340px;
  background:radial-gradient(circle,#0e7c86 0%,transparent 70%);
  top:40%;left:55%;
  animation-delay:-15s;
}
@keyframes blobFloat{
  0%,100%{ transform:translate(0,0) scale(1); }
  33%    { transform:translate(40px,-30px) scale(1.08); }
  66%    { transform:translate(-30px,25px) scale(.96); }
}

.login-wrap{
  position:relative;
  z-index:1;
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:flex-start;
  gap:0;
  padding:20px 16px 32px;
  min-height:calc(100vh - 64px);
}

.login-hero{ display:none; }

.login-card-shell{
  width:100%;
  max-width:440px;
  display:flex;
  justify-content:center;
  animation:cardIn .7s cubic-bezier(.2,.9,.3,1.2) both;
}
@keyframes cardIn{
  from{ opacity:0; transform:translateY(20px) scale(.98); }
  to  { opacity:1; transform:none; }
}

.login-card{
  width:100%;
  background:rgba(255,255,255,.97);
  border-radius:18px;
  padding:28px 22px;
  box-shadow:var(--shadow-lg);
  border:1px solid rgba(255,255,255,.4);
  position:relative;
  overflow:hidden;
  text-align:center;
}
.login-card::before{
  content:""; position:absolute; top:0;left:0;right:0;height:5px;
  background:linear-gradient(90deg,#22d3ee 0%,#38bdf8 40%,#6366f1 100%);
}

.card-icon{
  width:58px;height:58px; border-radius:16px;
  background:linear-gradient(135deg,#22d3ee 0%,#38bdf8 100%);
  color:#04121c; display:grid; place-items:center;
  font-size:1.5rem; margin:0 auto 14px;
  box-shadow:0 12px 26px -10px var(--acc-glow);
  animation:floatY 3.6s ease-in-out infinite;
}
@keyframes floatY{
  0%,100%{ transform:translateY(0); }
  50%    { transform:translateY(-6px); }
}
.card-title{
  font-family:'Inter',system-ui,sans-serif;
  font-weight:800; font-size:1.25rem;
  color:black; margin:0 0 4px;
  letter-spacing:-.01em;
}
.card-sub{
  color:black;
  font-size:.85rem; margin:0 0 20px;
}

/* Login type tabs */
.login-tabs{
  display:flex;
  gap:8px;
  background:#f1f5f9;
  padding:5px;
  border-radius:12px;
  margin-bottom:20px;
}
.login-tabs button{
  flex:1;
  border:none;
  background:transparent;
  padding:9px 10px;
  border-radius:9px;
  font-family:inherit;
  font-size:.85rem;
  font-weight:700;
  color:#64748b;
  cursor:pointer;
  transition:all .2s ease;
  display:flex;
  align-items:center;
  justify-content:center;
  gap:6px;
}
.login-tabs button.active{
  background:#fff;
  color:var(--teal-dark);
  box-shadow:0 2px 8px rgba(15,23,42,.10);
}
.login-tabs button:hover:not(.active){ color:var(--ink); }

.alert-soft{
  display:flex; align-items:flex-start; gap:10px;
  padding:11px 13px; border-radius:12px;
  font-size:.85rem; line-height:1.5;
  margin-bottom:16px;
  background:var(--danger-bg);
  border:1px solid var(--danger-line);
  color:#991b1b;
  text-align:left;
  animation:shakeIn .5s ease both;
}
.alert-soft i{ flex-shrink:0; margin-top:2px; font-size:1rem; }
@keyframes shakeIn{
  0%  { opacity:0; transform:translateX(-6px); }
  40% { transform:translateX(4px); }
  100%{ opacity:1; transform:translateX(0); }
}

.field{ margin-bottom:16px; text-align:left; }
.field-label{
  display:block; font-size:.82rem; font-weight:600;
  color:black; margin-bottom:6px;
}
.field-input{
  display:flex; align-items:stretch;
  border:1.5px solid var(--line);
  border-radius:12px;
  background:#fff;
  transition:var(--t);
  overflow:hidden;
  width:100%;
}
.field-input:focus-within{
  border-color:var(--acc);
  box-shadow:0 0 0 4px rgba(34,211,238,.14);
}
.field-icon{
  width:44px; flex-shrink:0;
  display:grid; place-items:center;
  background:#f8fafc; color:var(--ink-mut);
  border-right:1.5px solid var(--line);
  transition:var(--t); font-size:1rem;
}
.field-input:focus-within .field-icon{
  background:#ecfeff; color:var(--teal-dark);
}
.field-input input{
  flex:1 1 auto;
  border:none; outline:none;
  padding:13px 12px;
  font-size:16px;
  font-family:inherit;
  color:var(--ink);
  background:transparent;
  min-width:0;
  width:100%;
}
.field-input input::placeholder{ color:#94a3b8; }

.toggle-pass{
  width:44px; flex-shrink:0;
  border:none; background:#f8fafc;
  border-left:1.5px solid var(--line);
  color:var(--ink-mut); cursor:pointer;
  display:grid; place-items:center; font-size:1rem;
  transition:var(--t);
  padding:0;
}
.toggle-pass:hover{ background:#ecfeff; color:var(--teal-dark); }
.toggle-pass:focus-visible{ outline:2px solid var(--acc); outline-offset:-3px; }

.btn-login{
  width:100%; padding:13px 20px;
  border:none; border-radius:12px;
  background:linear-gradient(135deg,#0e7c86 0%,#0a5f67 100%);
  color:#fff; font-family:inherit;
  font-weight:700; font-size:.98rem;
  cursor:pointer;
  transition:var(--t);
  box-shadow:0 12px 26px -10px rgba(14,124,134,.6);
  display:inline-flex; align-items:center;
  justify-content:center; gap:8px;
}
.btn-login:hover{
  transform:translateY(-2px);
  filter:brightness(1.06);
  box-shadow:0 16px 32px -12px rgba(14,124,134,.75);
}
.btn-login:active{ transform:translateY(0); }

.hint{
  margin-top:14px;
  font-size:.75rem;
  color:#64748b;
  line-height:1.5;
  text-align:center;
}
.hint i{ color:var(--teal-dark); }

@media (min-width:576px){
  .login-wrap{
    padding:32px 24px 40px;
    min-height:calc(100vh - 72px);
    justify-content:center;
  }
  .login-card{ padding:36px 32px; border-radius:20px; }
  .card-icon{ width:62px;height:62px;font-size:1.6rem; }
  .card-title{ font-size:1.35rem; }
  .card-sub{ font-size:.9rem; margin-bottom:24px; }
  .field{ margin-bottom:18px; }
  .field-input input{ padding:13px 14px; }
  .field-icon, .toggle-pass{ width:46px; }
}

@media (min-width:992px){
  .login-wrap{
    display:grid;
    grid-template-columns:1.05fr 1fr;
    align-items:center;
    justify-items:stretch;
    gap:clamp(20px,4vw,60px);
    padding:clamp(40px,5vw,60px) clamp(40px,5vw,72px);
    min-height:calc(100vh - 72px);
  }
  .login-hero{
    display:block;
    color:#fff;
    max-width:560px;
    margin-inline:auto;
    animation:heroIn .8s cubic-bezier(.2,.9,.3,1.2) both;
  }
  @keyframes heroIn{
    from{ opacity:0; transform:translateY(24px); }
    to  { opacity:1; transform:none; }
  }
  .hero-badge{
    display:inline-flex; align-items:center; gap:8px;
    padding:7px 16px; border-radius:999px;
    background:rgba(34,211,238,.14);
    border:1px solid rgba(34,211,238,.3);
    color:#a5f3fc; font-size:.78rem; font-weight:600;
    letter-spacing:.04em; text-transform:uppercase;
    margin-bottom:22px;
  }
  .hero-badge i{ font-size:.9rem; }
  .login-hero h1{
    font-weight:800;
    font-size:clamp(1.8rem,3vw,2.6rem);
    line-height:1.12; letter-spacing:-.02em;
    margin:0 0 16px;
  }
  .login-hero h1 .grad{
    background:linear-gradient(90deg,#22d3ee,#38bdf8,#a5b4fc);
    -webkit-background-clip:text; background-clip:text; color:transparent;
  }
  .login-hero p{
    color:#94a9bd; font-size:1rem;
    line-height:1.6; margin:0 0 26px; max-width:480px;
  }
  .hero-features{ display:flex; flex-direction:column; gap:12px; margin-top:22px; }
  .hero-features .hf{
    display:flex; align-items:center; gap:12px;
    font-size:.92rem; color:#cbd5e1;
  }
  .hero-features .hf i{
    width:34px;height:34px; border-radius:10px;
    background:rgba(34,211,238,.12);
    border:1px solid rgba(34,211,238,.25);
    color:#22d3ee; display:grid; place-items:center;
    font-size:1rem; flex-shrink:0;
  }
  .login-card-shell{
    justify-self:center;
    animation:cardIn .8s .15s cubic-bezier(.2,.9,.3,1.2) both;
  }
  .login-card{ padding:clamp(32px,3vw,44px); }
}

@media (max-width:360px){
  .login-wrap{ padding:16px 12px 24px; }
  .login-card{ padding:24px 16px; border-radius:16px; }
  .card-icon{ width:50px;height:50px;font-size:1.3rem;border-radius:14px; }
  .card-title{ font-size:1.1rem; }
  .card-sub{ font-size:.78rem; margin-bottom:16px; }
  .field-label{ font-size:.78rem; }
  .field-icon, .toggle-pass{ width:40px; font-size:.9rem; }
  .field-input input{ padding:12px 10px; }
  .btn-login{ padding:12px 16px; font-size:.9rem; }
  .alert-soft{ font-size:.78rem; padding:10px 11px; }
  .login-tabs button{ font-size:.78rem; padding:8px 6px; }
}

@media (max-height:600px) and (orientation:landscape){
  .login-wrap{ padding-top:16px; padding-bottom:16px; }
  .card-icon{ display:none; }
  .card-title{ margin-top:6px; }
  .card-sub{ margin-bottom:14px; }
}

@supports (padding:max(0px)){
  .login-wrap{
    padding-left:max(16px, env(safe-area-inset-left));
    padding-right:max(16px, env(safe-area-inset-right));
    padding-bottom:max(32px, env(safe-area-inset-bottom));
  }
}

@media (prefers-reduced-motion:reduce){
  *,*::before,*::after{
    animation-duration:.01ms !important;
    animation-iteration-count:1 !important;
    transition-duration:.01ms !important;
  }
}
</style>
</head>

<body>

<?php include('../header.php'); ?>

<div class="bg-blobs" aria-hidden="true">
  <span></span><span></span><span></span>
</div>

<main class="login-wrap" role="main">

  <section class="login-hero">
    <span class="hero-badge">
      <i class="bi bi-shield-check"></i> Secure Access
    </span>
    <h1>
      Manage your <span class="grad">vehicle fleet</span>
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

  <section class="login-card-shell">
    <div class="login-card">

      <div class="card-icon"><i class="bi bi-shield-lock-fill"></i></div>
      <h1 class="card-title">Welcome Back</h1>
      <p class="card-sub">Sign in to continue</p>

      <!-- Login type tabs -->
      <div class="login-tabs" role="tablist">
        <button type="button" class="active" id="tabAdmin" data-mode="admin">
          <i class="bi bi-person-workspace"></i> Admin / Guard
        </button>
        <button type="button" id="tabDriver" data-mode="driver">
          <i class="bi bi-person-badge-fill"></i> Driver
        </button>
      </div>

      <?php if($error != ""){ ?>
        <div class="alert-soft" role="alert">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <div><?php echo htmlspecialchars($error); ?></div>
        </div>
      <?php } ?>

      <form method="POST" id="loginForm" autocomplete="on" novalidate>

        <div class="field">
          <label class="field-label" for="identifier" id="identifierLabel">Email</label>
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
          <i class="bi bi-box-arrow-in-right"></i> Login
        </button>

      </form>

      <div class="hint" id="modeHint">
        <i class="bi bi-info-circle-fill"></i>
        Admins &amp; staff sign in with their <strong>email</strong>.
      </div>

    </div>
  </section>
</main>

<script>
'use strict';
(function(){
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

  /* ---- Login mode tabs ---- */
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

      identifierLabel.textContent = 'Mobile Number';
      identifierIcon.innerHTML = '<i class="bi bi-telephone-fill"></i>';
      identifier.type = 'tel';
      identifier.setAttribute('inputmode', 'numeric');
      identifier.setAttribute('pattern', '[0-9]{10}');
      identifier.setAttribute('maxlength', '10');
      identifier.placeholder = 'Enter your 10-digit mobile number';
      identifier.setAttribute('autocomplete', 'tel');
      modeHint.innerHTML = '<i class="bi bi-info-circle-fill"></i> Drivers sign in with their <strong>10-digit mobile number</strong>.';
    } else {
      tabAdmin.classList.add('active');
      tabDriver.classList.remove('active');

      identifierLabel.textContent = 'Email';
      identifierIcon.innerHTML = '<i class="bi bi-envelope-fill"></i>';
      identifier.type = 'email';
      identifier.removeAttribute('inputmode');
      identifier.removeAttribute('pattern');
      identifier.removeAttribute('maxlength');
      identifier.placeholder = 'Enter your email';
      identifier.setAttribute('autocomplete', 'username');
      modeHint.innerHTML = '<i class="bi bi-info-circle-fill"></i> Admins &amp; staff sign in with their <strong>email</strong>.';
    }
    identifier.value = '';
    identifier.focus();
  }

  tabAdmin.addEventListener('click',  () => setMode('admin'));
  tabDriver.addEventListener('click', () => setMode('driver'));

  /* Auto-detect: if user types only digits, we can optionally hint driver mode */
  identifier.addEventListener('input', function(){
    const v = this.value.replace(/\D/g, '');
    // Keep driver mobile strictly numeric
    if (this.inputMode === 'numeric') {
      if (this.value !== v) this.value = v;
    }
  });
})();
</script>
</body>
</html>
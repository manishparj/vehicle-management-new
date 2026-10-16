<?php
$base_url = "https://niirncd.org/vehicle_app/";
?>

<!-- Google Font -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
/* ═══════════════════════════════════════════
   VEHICLE APP — MODERN NAVBAR
   ═══════════════════════════════════════════ */
:root{
  --nav-1:#0b1220;
  --nav-2:#111c33;
  --nav-3:#1e3a5f;
  --acc:#22d3ee;
  --acc-2:#38bdf8;
  --acc-glow:rgba(34,211,238,.35);
  --ink55:#e8f1f8;
  --ink55-mut:#94a9bd;
  --nav-h:72px;
}

/* ── Base ── */
/* body{ font-family:'Poppins',sans-serif; } */

/* ── Navbar shell ── */
.navbar{
  position:sticky;
  top:0;
  z-index:1030;
  padding:10px 0;
  min-height:var(--nav-h);
  background:linear-gradient(120deg,var(--nav-1) 0%,var(--nav-2) 45%,var(--nav-3) 100%);
  background-size:200% 200%;
  animation:navShift 14s ease infinite;
  border-bottom:1px solid rgba(255,255,255,.06);
  transition:box-shadow .3s ease,background .3s ease,padding .3s ease;
  backdrop-filter:blur(10px);
  -webkit-backdrop-filter:blur(10px);
}
@keyframes navShift{
  0%,100%{ background-position:0% 50%; }
  50%    { background-position:100% 50%; }
}
.navbar.scrolled{
  box-shadow:0 10px 30px -12px rgba(0,0,0,.55),0 1px 0 rgba(255,255,255,.05) inset;
  padding:6px 0;
  background:linear-gradient(120deg,#0a0f1c 0%,#0f1a2e 45%,#16294a 100%);
}

/* ── Container ── */
.navbar .container-fluid{
  padding-left:clamp(14px,3vw,28px);
  padding-right:clamp(14px,3vw,28px);
  gap:12px;
}

/* ── Brand ── */
.navbar-brand{
  display:flex;
  align-items:center;
  gap:11px;
  font-size:clamp(1rem,2.4vw,1.2rem);
  font-weight:700;
  letter-spacing:.3px;
  color:#fff !important;
  padding:0;
  margin:0;
  text-decoration:none;
  transition:transform .25s ease;
}
.navbar-brand:hover{ transform:translateY(-1px); }
.brand-badge{
  width:40px;height:40px;
  border-radius:12px;
  background:linear-gradient(135deg,var(--acc) 0%,var(--acc-2) 100%);
  display:flex;align-items:center;justify-content:center;
  color:#04121c;
  font-size:1.2rem;
  box-shadow:0 8px 20px -6px var(--acc-glow),0 0 0 1px rgba(255,255,255,.08) inset;
  transition:transform .35s cubic-bezier(.2,.9,.3,1.3),box-shadow .3s ease;
  flex-shrink:0;
}
.navbar-brand:hover .brand-badge{
  transform:rotate(-8deg) scale(1.06);
  box-shadow:0 12px 26px -6px var(--acc-glow),0 0 0 1px rgba(255,255,255,.14) inset;
}
.brand-text{
  display:flex;flex-direction:column;line-height:1.05;
}
.brand-text .bt-1{
  font-family:'Poppins',sans-serif;
  font-weight:700;
  letter-spacing:.2px;
}
.brand-text .bt-2{
  font-size:.62rem;
  font-weight:500;
  color:var(--ink55-mut);
  letter-spacing:.16em;
  text-transform:uppercase;
  margin-top:2px;
}

/* ── Toggler (custom animated hamburger) ── */
.navbar-toggler{
  border:none;
  padding:8px 10px;
  border-radius:12px;
  background:rgba(255,255,255,.06);
  transition:background .2s ease,transform .2s ease;
  --bs-navbar-toggler-icon-bg:none;
}
.navbar-toggler:hover{ background:rgba(34,211,238,.15); }
.navbar-toggler:focus{ box-shadow:0 0 0 3px rgba(34,211,238,.25); outline:none; }
.hamburger{
  display:inline-block;
  width:22px;height:16px;
  position:relative;
}
.hamburger span{
  position:absolute;left:0;right:0;height:2px;border-radius:2px;
  background:linear-gradient(90deg,var(--acc),var(--acc-2));
  transition:transform .3s ease,opacity .2s ease,top .3s ease;
}
.hamburger span:nth-child(1){ top:0; }
.hamburger span:nth-child(2){ top:7px; }
.hamburger span:nth-child(3){ top:14px; }
.navbar-toggler[aria-expanded="true"] .hamburger span:nth-child(1){
  top:7px; transform:rotate(45deg);
}
.navbar-toggler[aria-expanded="true"] .hamburger span:nth-child(2){
  opacity:0;
}
.navbar-toggler[aria-expanded="true"] .hamburger span:nth-child(3){
  top:7px; transform:rotate(-45deg);
}

/* ── Nav links ── */
.navbar-nav{ align-items:center; gap:6px; }
.navbar .nav-link{
  position:relative;
  color:var(--ink55) !important;
  font-weight:500;
  font-size:.94rem;
  padding:10px 16px !important;
  border-radius:10px;
  transition:color .25s ease,background .25s ease,transform .2s ease;
  display:inline-flex;
  align-items:center;
  gap:8px;
}
.navbar .nav-link i{ font-size:1rem; opacity:.85; }
.navbar .nav-link::after{
  content:'';
  position:absolute;
  left:16px;right:16px;bottom:6px;
  height:2px;
  border-radius:2px;
  background:linear-gradient(90deg,var(--acc),var(--acc-2));
  transform:scaleX(0);
  transform-origin:center;
  transition:transform .3s cubic-bezier(.4,0,.2,1);
}
.navbar .nav-link:hover{
  color:#fff !important;
  background:rgba(34,211,238,.08);
}
.navbar .nav-link:hover::after{ transform:scaleX(1); }
.navbar .nav-link.active{
  color:#fff !important;
  background:rgba(34,211,238,.12);
}
.navbar .nav-link.active::after{ transform:scaleX(1); }

/* ── Admin pill button ── */
.navbar .nav-link.btn{
  border:none !important;
  color:#04121c !important;
  font-weight:600;
  padding:9px 20px !important;
  border-radius:999px !important;
  background:linear-gradient(135deg,var(--acc) 0%,var(--acc-2) 100%);
  box-shadow:0 8px 20px -8px var(--acc-glow);
  transition:transform .25s ease,box-shadow .3s ease,filter .25s ease;
  margin-left:8px;
}
.navbar .nav-link.btn:hover{
  transform:translateY(-2px);
  filter:brightness(1.06);
  box-shadow:0 14px 28px -10px var(--acc-glow);
  color:#04121c !important;
  background:linear-gradient(135deg,var(--acc) 0%,var(--acc-2) 100%);
}
.navbar .nav-link.btn::after{ display:none; }
.navbar .nav-link.btn i{ opacity:1; }

/* ═══════════════════════════════════════════
   RESPONSIVE
   ═══════════════════════════════════════════ */

/* Tablet & below — hamburger menu */
@media (max-width:991.98px){
  .navbar{ padding:10px 0; }
  .navbar .container-fluid{ padding-left:16px; padding-right:16px; }

  .navbar-collapse{
    margin-top:12px;
    padding:14px;
    border-radius:16px;
    background:linear-gradient(160deg,rgba(17,28,51,.98),rgba(30,58,95,.98));
    border:1px solid rgba(255,255,255,.08);
    box-shadow:0 20px 40px -12px rgba(0,0,0,.55);
    backdrop-filter:blur(14px);
  }

  .navbar-nav{ align-items:stretch; gap:4px; width:100%; }
  .navbar .nav-link{
    padding:13px 16px !important;
    border-radius:12px;
    font-size:.96rem;
    justify-content:flex-start;
  }
  .navbar .nav-link::after{ display:none; }
  .navbar .nav-link.active{
    background:linear-gradient(90deg,rgba(34,211,238,.18),rgba(56,189,248,.06));
    border-left:3px solid var(--acc);
  }

  .navbar .nav-link.btn{
    margin:6px 0 0;
    justify-content:center;
    width:100%;
    padding:12px 20px !important;
  }

  /* Staggered fade-in for menu items */
  .navbar-collapse.show .nav-item,
  .navbar-collapse.collapsing .nav-item{
    animation:navItemIn .35s ease both;
  }
  .navbar-collapse.show .nav-item:nth-child(1){ animation-delay:.03s; }
  .navbar-collapse.show .nav-item:nth-child(2){ animation-delay:.09s; }
  .navbar-collapse.show .nav-item:nth-child(3){ animation-delay:.15s; }
  @keyframes navItemIn{
    from{ opacity:0; transform:translateY(-8px); }
    to  { opacity:1; transform:translateY(0); }
  }
}

/* Mobile */
@media (max-width:575.98px){
  .navbar{ padding:8px 0; min-height:64px; }
  .brand-badge{ width:36px; height:36px; font-size:1.05rem; border-radius:10px; }
  .brand-text .bt-1{ font-size:1rem; }
  .brand-text .bt-2{ font-size:.56rem; letter-spacing:.14em; }
  .navbar .container-fluid{ padding-left:12px; padding-right:12px; }
  .navbar-collapse{ padding:12px; border-radius:14px; }
}

/* Extra small */
@media (max-width:380px){
  .brand-text .bt-2{ display:none; }
  .navbar .nav-link{ font-size:.92rem; padding:12px 14px !important; }
}

/* Safe area for notched phones */
@supports (padding:max(0px)){
  .navbar .container-fluid{
    padding-left:max(14px,env(safe-area-inset-left));
    padding-right:max(14px,env(safe-area-inset-right));
  }
}

/* Reduced motion */
@media (prefers-reduced-motion:reduce){
  .navbar,.brand-badge,.nav-link,.nav-link.btn{ animation:none !important; transition:none !important; }
}
</style>

<nav class="navbar navbar-expand-lg navbar-dark" id="mainNav">
  <div class="container-fluid">

    <!-- Brand -->
    <a class="navbar-brand" href="<?php echo $base_url; ?>index.php">
      <span class="brand-badge"><i class="bi bi-car-front-fill"></i></span>
      <span class="brand-text">
        <span class="bt-1">Vehicle App</span>
        <span class="bt-2">ICMR-NIHR</span>
      </span>
    </a>

    <!-- Toggler -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="hamburger"><span></span><span></span><span></span></span>
    </button>

    <!-- Menu -->
    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link active" href="<?php echo $base_url; ?>index.php">
            <i class="bi bi-house-door"></i> Home
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?php echo $base_url; ?>check_status.php">
            <i class="bi bi-search"></i> Check Status
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link btn" href="<?php echo $base_url; ?>admin/login.php">
            <i class="bi bi-shield-lock"></i> Admin Login
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<script>
// Scroll-aware navbar shadow + compact mode
(function(){
  var nav = document.getElementById('mainNav');
  if(!nav) return;
  var onScroll = function(){
    if(window.scrollY > 8) nav.classList.add('scrolled');
    else nav.classList.remove('scrolled');
  };
  window.addEventListener('scroll', onScroll, {passive:true});
  onScroll();

  // Auto-close mobile menu on link click
  var collapse = document.getElementById('navbarNav');
  if(collapse){
    collapse.querySelectorAll('.nav-link').forEach(function(a){
      a.addEventListener('click', function(){
        if(window.innerWidth < 992 && collapse.classList.contains('show')){
          var bsCollapse = bootstrap.Collapse.getOrCreateInstance(collapse);
          bsCollapse.hide();
        }
      });
    });
  }
})();
</script>
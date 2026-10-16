<?php include("./config/db.php"); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Check Vehicle Application Status — ICMR-NIHR</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" media="print" onload="this.media='all'">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet" media="print" onload="this.media='all'">
<noscript>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</noscript>

<style>
/* =====================================================================
   DESIGN TOKENS — shared visual language with the booking form
   ===================================================================== */
:root{
  --navy:#0F2A43;
  --teal:#0E7C86;
  --teal-dark:#0A5F67;
  --teal-tint:#E5F3F3;
  --paper:#F5F8F9;
  --card:#FFFFFF;
  --line:#E1E8EC;
  --amber:#C9762A;   --amber-tint:#FBF0E4;  --amber-text:#7A4A17;
  --success:#1F8A5F; --success-tint:#EAF7F0;
  --danger:#C4433B;  --danger-tint:#FBEAE9;
  --slate:#48586B;   --slate-tint:#ECEFF2;
  --wa:#25D366;      --wa-dark:#1DA851;
  --tm:#5B6B7A;
  --radius-lg:16px; --radius-md:12px; --radius-sm:8px;
  --shadow-card:0 1px 2px rgba(15,42,67,.06),0 12px 32px -14px rgba(15,42,67,.22);
}
*{box-sizing:border-box}
body{
  font-family:'Inter',sans-serif;
  background:var(--paper);
  color:var(--navy);
  margin:0;
  -webkit-font-smoothing:antialiased;
}
:focus-visible{outline:2px solid var(--teal);outline-offset:2px}

/* =====================================================================
   HERO
   ===================================================================== */
.ph{
  background:linear-gradient(155deg,var(--navy) 0%,var(--teal-dark) 100%);
  color:#fff;text-align:center;position:relative;overflow:hidden;
  padding:clamp(36px,6vw,52px) 0 clamp(64px,10vw,84px);
}
.ph::after{
  content:"";position:absolute;inset:auto 0 0 0;height:70px;
  background:repeating-linear-gradient(90deg,rgba(255,255,255,.35) 0 26px,transparent 26px 52px);
  opacity:.12;
}
.ph-badge{
  display:inline-block;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.28);
  padding:6px 16px;border-radius:999px;font-size:.78rem;letter-spacing:.02em;margin-bottom:18px;
}
.ph-icon{
  width:64px;height:64px;border-radius:18px;background:rgba(255,255,255,.14);
  display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:1.7rem;
}
.ph h1{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:clamp(1.4rem,3.4vw,2rem);margin:0 0 8px}
.ph p{color:rgba(255,255,255,.78);margin:0;font-size:1rem}

/* =====================================================================
   SHELL / SEARCH CARD
   ===================================================================== */
.container{max-width:960px}
.fc{margin-top:clamp(-40px,-6vw,-56px);position:relative;z-index:2}
.fb{
  background:var(--card);border-radius:var(--radius-lg);box-shadow:var(--shadow-card);
  border:1px solid var(--line);
}
.search-title{display:flex;align-items:center;gap:14px;margin-bottom:22px}
.search-title .si{
  width:42px;height:42px;border-radius:var(--radius-md);background:var(--teal-tint);color:var(--teal-dark);
  display:flex;align-items:center;justify-content:center;font-size:1.05rem;flex-shrink:0;
}
.search-title h4{margin:0;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:1.15rem;color:var(--navy)}
.search-title small{display:block;color:var(--tm);font-size:.82rem;margin-top:2px}
.input-group-text{background:var(--paper);border-color:var(--line);color:var(--teal-dark)}
.form-control{border-color:var(--line);padding:.65rem .9rem;font-size:.96rem}
.form-control:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(14,124,134,.14)}
.btn-search{
  background:linear-gradient(135deg,var(--teal),var(--teal-dark));border:none;color:#fff;font-weight:600;
  border-radius:var(--radius-sm);transition:filter .2s,transform .2s;
}
.btn-search:hover{filter:brightness(1.06);color:#fff}
.btn-search:active{transform:translateY(1px)}

/* =====================================================================
   EMPTY STATE
   ===================================================================== */
.empty-state{
  text-align:center;padding:40px 20px;border:1px dashed var(--line);border-radius:var(--radius-md);
  background:var(--paper);color:var(--tm);
}
.empty-state i{font-size:2rem;color:var(--danger);margin-bottom:10px;display:block}
.empty-state strong{color:var(--navy)}

/* =====================================================================
   APPLICATION CARD
   ===================================================================== */
.app-card{
  background:var(--card);border:1px solid var(--line);border-radius:var(--radius-lg);
  box-shadow:var(--shadow-card);overflow:hidden;margin-top:22px;
}
.app-card:first-of-type{margin-top:28px}

.app-head{
  background:linear-gradient(135deg,var(--navy),var(--teal-dark));color:#fff;
  padding:16px 20px;display:flex;flex-wrap:wrap;align-items:center;gap:12px;
}
.app-id{display:flex;align-items:center;gap:10px;font-weight:700;font-size:1rem;min-width:0}
.app-id i{font-size:1.1rem;flex-shrink:0}
.app-id .sub{display:block;font-weight:500;font-size:.74rem;color:rgba(255,255,255,.72);margin-top:1px}
.app-actions{display:flex;align-items:center;gap:10px;margin-left:auto}

.status-pill{
  display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:999px;
  font-weight:700;font-size:.8rem;white-space:nowrap;background:rgba(255,255,255,.16);color:#fff;
  border:1px solid rgba(255,255,255,.3);
}
.status-pill.st-pending{background:var(--amber-tint);color:var(--amber-text);border-color:transparent}
.status-pill.st-recommended{background:var(--teal-tint);color:var(--teal-dark);border-color:transparent}
.status-pill.st-approved{background:var(--success-tint);color:var(--success);border-color:transparent}
.status-pill.st-rejected{background:var(--danger-tint);color:var(--danger);border-color:transparent}
.status-pill.st-completed{background:#fff;color:var(--navy);border-color:transparent}
.status-pill.st-cancelled{background:var(--slate-tint);color:var(--slate);border-color:transparent}

.btn-wa{
  display:inline-flex;align-items:center;gap:7px;background:var(--wa);border:none;color:#fff;
  font-weight:600;font-size:.82rem;padding:8px 14px;border-radius:999px;transition:background .2s,transform .2s;
  white-space:nowrap;
}
.btn-wa:hover{background:var(--wa-dark);color:#fff}
.btn-wa:active{transform:translateY(1px)}
.btn-wa i{font-size:1rem}
.btn-wa.copied{background:var(--teal-dark)}

.app-body{padding:22px 24px 24px}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:28px}

.blk{margin-bottom:20px}
.blk:last-child{margin-bottom:0}
.blk-title{
  display:flex;align-items:center;gap:8px;font-weight:700;font-size:.86rem;color:var(--navy);
  text-transform:none;margin-bottom:10px;
}
.blk-title i{color:var(--teal-dark);font-size:.95rem}

.kv-row{display:flex;gap:10px;padding:5px 0;font-size:.9rem;border-bottom:1px dashed var(--line)}
.kv-row:last-child{border-bottom:none}
.kv-label{flex:0 0 130px;color:var(--tm);font-weight:600}
.kv-value{flex:1;color:var(--navy);word-break:break-word}

.chip-wrap{display:flex;flex-wrap:wrap;gap:8px}
.chip{
  background:var(--teal-tint);color:var(--teal-dark);font-size:.82rem;font-weight:600;
  padding:5px 12px;border-radius:999px;
}
.chip.muted{background:var(--paper);color:var(--tm);font-weight:500}

.assign-box{
  background:var(--paper);border:1px solid var(--line);border-radius:var(--radius-sm);padding:12px 14px;
}
.assign-box .kv-row{border-bottom:1px dashed var(--line)}
.assign-warn{
  display:flex;align-items:center;gap:8px;background:var(--amber-tint);color:var(--amber-text);
  border-radius:var(--radius-sm);padding:10px 14px;font-size:.86rem;font-weight:500;
}

.remark-box{background:var(--teal-tint);border-radius:var(--radius-sm);padding:12px 14px;font-size:.9rem;color:var(--navy)}
.remark-box.empty{background:var(--paper);color:var(--tm);font-style:italic}

.app-foot{
  border-top:1px solid var(--line);padding:12px 24px;font-size:.78rem;color:var(--tm);
  background:var(--paper);
}

/* =====================================================================
   TOAST
   ===================================================================== */
#copyToast{
  position:fixed;left:50%;bottom:26px;transform:translate(-50%,20px);
  background:var(--navy);color:#fff;padding:10px 20px;border-radius:999px;font-size:.86rem;font-weight:600;
  display:flex;align-items:center;gap:8px;opacity:0;pointer-events:none;transition:opacity .25s,transform .25s;
  box-shadow:0 12px 30px -8px rgba(0,0,0,.4);z-index:9999;
}
#copyToast.on{opacity:1;transform:translate(-50%,0)}
#copyToast i{color:var(--wa)}

/* =====================================================================
   RESPONSIVE
   ===================================================================== */
@media (max-width:767px){
  .fc{margin-top:-40px}
  .fb{border-radius:var(--radius-md)}
  .info-grid{grid-template-columns:1fr;gap:6px}
  .app-head{padding:14px 16px}
  .app-body{padding:18px}
  .app-actions{width:100%;justify-content:space-between;margin-left:0}
  .kv-label{flex-basis:110px}
}
@media (max-width:420px){
  .kv-row{flex-direction:column;gap:2px}
  .kv-label{flex-basis:auto}
  .btn-wa span{display:none}
  .btn-wa{padding:9px 11px}
  .status-pill{font-size:.74rem;padding:5px 10px}
}
</style>
</head>
<body>
<?php include('./header.php'); ?>

<div class="ph">
  <div class="container">
    <span class="ph-badge">ICMR-NIHR Jodhpur</span>
    <div class="ph-icon"><i class="bi bi-clipboard2-check-fill"></i></div>
    <h1>Track Vehicle Application</h1>
    <p>Enter your registered mobile number to check the live status</p>
  </div>
</div>

<div class="container pb-5">
  <div class="row justify-content-center">
    <div class="col-12">
      <div class="fc">
        <div class="fb p-4 p-md-5">

          <div class="search-title">
            <div class="si"><i class="bi bi-search"></i></div>
            <div>
              <h4>Check Application Status</h4>
              <small>Results show every request filed with this mobile number, most recent first</small>
            </div>
          </div>

          <form method="POST" class="row g-2">
            <div class="col-md-9">
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-phone"></i></span>
                <input type="text" name="mobile" class="form-control" placeholder="Enter 10-digit mobile number"
                       value="<?= isset($_POST['mobile']) ? htmlspecialchars($_POST['mobile']) : '' ?>"
                       maxlength="10" inputmode="numeric" required>
              </div>
            </div>
            <div class="col-md-3 d-grid">
              <button class="btn btn-search"><i class="bi bi-search me-1"></i> Check Status</button>
            </div>
          </form>

<?php
if (isset($_POST['mobile'])) {
    $mobile = trim($_POST['mobile']);

    $stmt = $conn->prepare("
        SELECT va.*,
               d.driver_name, d.driver_mob,
               v.vehicle_type, v.vehicle_name, v.vehicle_no,
               u.name AS forwarded_admin_name, u.designation
        FROM vehicle_applications va
        LEFT JOIN drivers d ON va.driver_id = d.id
        LEFT JOIN vehicles v ON va.vehicle_id = v.id
        LEFT JOIN users u ON va.forwarded_to = u.id
        WHERE va.mobile = ?
        ORDER BY va.created_at DESC
    ");
    $stmt->bind_param("s", $mobile);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {

        $statusMeta = [
            'Pending'     => ['class' => 'st-pending',     'icon' => 'hourglass-split'],
            'Recommended' => ['class' => 'st-recommended', 'icon' => 'hand-thumbs-up-fill'],
            'Approved'    => ['class' => 'st-approved',    'icon' => 'check-circle-fill'],
            'Rejected'    => ['class' => 'st-rejected',    'icon' => 'x-circle-fill'],
            'Completed'   => ['class' => 'st-completed',   'icon' => 'flag-fill'],
            'Cancelled'   => ['class' => 'st-cancelled',   'icon' => 'slash-circle-fill'],
        ];

        while ($row = $res->fetch_assoc()) {
            $status = $row['status'];
            $meta   = $statusMeta[$status] ?? ['class' => 'st-default', 'icon' => 'circle-fill'];

            $isSomeone = ($row['booking_for'] === 'Someone' && !empty($row['other_person_name']));
            $displayName = $isSomeone ? $row['other_person_name'] : $row['applicant_name'];

            $coTravelers = [];
            for ($i = 1; $i <= 6; $i++) {
                if (!empty($row["cotraveler$i"])) $coTravelers[] = $row["cotraveler$i"];
            }

            $assignVisible = in_array($row['status'], ['Approved','Completed','Cancelled','Started','Pending','In Progress','Departure']);
            $driverLine  = $row['driver_name']  ? $row['driver_name']." (".$row['driver_mob'].")" : "Not assigned yet";
            $vehicleLine = $row['vehicle_name'] ? $row['vehicle_type']." – ".$row['vehicle_name']." (".$row['vehicle_no'].")" : "Not assigned yet";

            // Build a WhatsApp-friendly plain-text summary for the copy button
            $lines = [];
            $lines[] = "*Vehicle Application Status — ICMR-NIHR Jodhpur*";
            $lines[] = "";
            $lines[] = "Application ID: #".$row['id'];
            $lines[] = "Status: ".$status;
            $lines[] = "";
            $lines[] = "*Applicant*";
            $lines[] = "Name: ".$displayName;
            if ($isSomeone) $lines[] = "Booked by: ".$row['applicant_name'];
            $lines[] = "Staff Type: ".$row['staff_type'];
            $lines[] = "Mobile: ".$row['mobile'];
            if (!empty($coTravelers)) $lines[] = "Co-Travelers: ".implode(", ", $coTravelers);
            $lines[] = "";
            $lines[] = "*Journey*";
            $lines[] = "Vehicle: ".$row['vehicle_required'];
            $lines[] = "From: ".$row['datetime_from'];
            $lines[] = "To: ".$row['datetime_to'];
            if (!empty($row['place_of_visit'])) $lines[] = "Place of Visit: ".$row['place_of_visit'];
            $lines[] = "";
            $lines[] = "*Forwarded To*";
            $lines[] = trim(($row['forwarded_admin_name'] ?? '')." (".($row['designation'] ?? '').")");
            $lines[] = "";
            $lines[] = "*Vehicle & Driver*";
            $lines[] = $assignVisible ? "Driver: ".$driverLine."\nVehicle: ".$vehicleLine : "Not assigned yet";
            $lines[] = "";
            $lines[] = "Remarks: ".($row['remark'] ?: "No remarks");
            $lines[] = "";
            $lines[] = "Submitted on: ".$row['created_at'];
            $shareText = implode("\n", $lines);
?>

<div class="app-card">
  <div class="app-head">
    <div class="app-id">
      <i class="bi bi-file-earmark-text"></i>
      <span>Application #<?= htmlspecialchars($row['id']) ?>
        <span class="sub">Submitted <?= htmlspecialchars($row['created_at']) ?></span>
      </span>
    </div>
    <div class="app-actions">
      <span class="status-pill <?= $meta['class'] ?>"><i class="bi bi-<?= $meta['icon'] ?>"></i> <?= htmlspecialchars($status) ?></span>
      <button type="button" class="btn-wa copy-wa" data-copy="<?= htmlspecialchars($shareText, ENT_QUOTES) ?>">
        <i class="bi bi-whatsapp"></i><span>Copy for WhatsApp</span>
      </button>
    </div>
  </div>

  <div class="app-body">
    <div class="info-grid">

      <!-- LEFT: Applicant + Co-Travelers -->
      <div>
        <div class="blk">
          <div class="blk-title"><i class="bi bi-person-circle"></i> Applicant Details</div>
          <div class="kv-row"><div class="kv-label">Booking For</div><div class="kv-value"><?= htmlspecialchars($row['booking_for']) ?></div></div>
          <?php if ($isSomeone): ?>
            <div class="kv-row"><div class="kv-label">Person</div><div class="kv-value"><?= htmlspecialchars($row['other_person_name']) ?></div></div>
            <div class="kv-row"><div class="kv-label">Booked By</div><div class="kv-value"><?= htmlspecialchars($row['applicant_name']) ?></div></div>
          <?php else: ?>
            <div class="kv-row"><div class="kv-label">Name</div><div class="kv-value"><?= htmlspecialchars($row['applicant_name']) ?></div></div>
          <?php endif; ?>
          <div class="kv-row"><div class="kv-label">Staff Type</div><div class="kv-value"><?= htmlspecialchars($row['staff_type']) ?></div></div>
          <div class="kv-row"><div class="kv-label">Mobile</div><div class="kv-value"><?= htmlspecialchars($row['mobile']) ?></div></div>
          <div class="kv-row"><div class="kv-label">Email</div><div class="kv-value"><?= htmlspecialchars($row['email']) ?></div></div>
          <div class="kv-row"><div class="kv-label">Purpose</div><div class="kv-value"><?= htmlspecialchars($row['purpose']) ?></div></div>
        </div>

        <div class="blk">
          <div class="blk-title"><i class="bi bi-people-fill"></i> Co-Travelers</div>
          <div class="chip-wrap">
            <?php if (!empty($coTravelers)): foreach ($coTravelers as $ct): ?>
              <span class="chip"><?= htmlspecialchars($ct) ?></span>
            <?php endforeach; else: ?>
              <span class="chip muted">No co-travelers</span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- RIGHT: Journey + Forwarded + Vehicle/Driver + Remarks -->
      <div>
        <div class="blk">
          <div class="blk-title"><i class="bi bi-geo-alt-fill"></i> Journey Details</div>
          <div class="kv-row"><div class="kv-label">Vehicle</div><div class="kv-value"><?= htmlspecialchars($row['vehicle_required']) ?></div></div>
          <div class="kv-row"><div class="kv-label">From</div><div class="kv-value"><?= htmlspecialchars($row['datetime_from']) ?></div></div>
          <div class="kv-row"><div class="kv-label">To</div><div class="kv-value"><?= htmlspecialchars($row['datetime_to']) ?></div></div>
          <div class="kv-row"><div class="kv-label">Place of Visit</div><div class="kv-value"><?= $row['place_of_visit'] ? htmlspecialchars($row['place_of_visit']) : '—' ?></div></div>
        </div>

        <div class="blk">
          <div class="blk-title"><i class="bi bi-person-badge-fill"></i> Forwarded To</div>
          <div class="kv-row"><div class="kv-label">Officer</div><div class="kv-value"><?= htmlspecialchars(trim(($row['forwarded_admin_name'] ?? '—')." (".($row['designation'] ?? '').")")) ?></div></div>
        </div>

        <div class="blk">
          <div class="blk-title"><i class="bi bi-truck"></i> Vehicle &amp; Driver</div>
          <?php if ($assignVisible): ?>
            <div class="assign-box">
              <div class="kv-row"><div class="kv-label">Driver</div><div class="kv-value"><?= htmlspecialchars($driverLine) ?></div></div>
              <div class="kv-row"><div class="kv-label">Vehicle</div><div class="kv-value"><?= htmlspecialchars($vehicleLine) ?></div></div>
            </div>
          <?php else: ?>
            <div class="assign-warn"><i class="bi bi-exclamation-triangle-fill"></i> Driver &amp; vehicle not assigned yet.</div>
          <?php endif; ?>
        </div>

        <div class="blk">
          <div class="blk-title"><i class="bi bi-chat-left-text-fill"></i> Remarks</div>
          <?php if (!empty($row['remark'])): ?>
            <div class="remark-box"><?= htmlspecialchars($row['remark']) ?></div>
          <?php else: ?>
            <div class="remark-box empty">No remarks</div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>

  <div class="app-foot"><i class="bi bi-clock-history me-1"></i>Application submitted on <?= htmlspecialchars($row['created_at']) ?></div>
</div>

<?php
        } // end while
    } else {
?>
        <div class="empty-state mt-4">
          <i class="bi bi-x-circle-fill"></i>
          No application found for <strong><?= htmlspecialchars($mobile) ?></strong>. Please check the mobile number and try again.
        </div>
<?php
    }
} // end isset POST
?>

        </div>
      </div>
    </div>
  </div>
</div>

<div id="copyToast"><i class="bi bi-whatsapp"></i><span id="copyToastText">Copied — paste it in WhatsApp</span></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
<script>
'use strict';

function copyText(text){
  if (navigator.clipboard && window.isSecureContext) {
    return navigator.clipboard.writeText(text);
  }
  // Fallback for older browsers / non-HTTPS contexts
  return new Promise((resolve, reject) => {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.focus();
    ta.select();
    try {
      document.execCommand('copy') ? resolve() : reject();
    } catch (err) {
      reject(err);
    } finally {
      document.body.removeChild(ta);
    }
  });
}

function showToast(msg){
  const t = document.getElementById('copyToast');
  document.getElementById('copyToastText').textContent = msg;
  t.classList.add('on');
  clearTimeout(showToast._tid);
  showToast._tid = setTimeout(() => t.classList.remove('on'), 2200);
}

document.addEventListener('click', function(e){
  const btn = e.target.closest('.copy-wa');
  if(!btn) return;
  const text = btn.getAttribute('data-copy') || '';
  copyText(text).then(() => {
    const icon = btn.querySelector('i');
    const label = btn.querySelector('span');
    const prevLabel = label.textContent;
    btn.classList.add('copied');
    icon.className = 'bi bi-check2';
    label.textContent = 'Copied!';
    showToast('Copied — paste it in WhatsApp');
    setTimeout(() => {
      btn.classList.remove('copied');
      icon.className = 'bi bi-whatsapp';
      label.textContent = prevLabel;
    }, 1800);
  }).catch(() => {
    showToast('Could not copy — please try again');
  });
});
</script>
</body>
</html>
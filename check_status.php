<?php
include("./config/db.php");

/* ============================================================
   HELPERS
   ============================================================ */
function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

function fmtDt($v, string $fmt = 'd M Y, h:i A'): string {
    if (empty($v) || $v === '0000-00-00 00:00:00') return '—';
    $t = strtotime($v);
    return $t ? date($fmt, $t) : (string)$v;
}

/** Build stepper definition from a status string. */
function buildSteps(string $status): array {
    $labels = ['Submitted', 'Recommended', 'Approved', 'Completed'];
    $rankMap = [
        'Pending' => 0, 'Recommended' => 1, 'Approved' => 2,
        'Started' => 2, 'In Progress' => 2, 'Departure' => 2, 'Completed' => 3,
    ];

    if ($status === 'Rejected' || $status === 'Cancelled') {
        return [
            ['label' => 'Submitted', 'class' => 'done'],
            ['label' => $status,     'class' => 'fail' . ($status === 'Cancelled' ? ' muted-fail' : '')],
        ];
    }

    $rank  = $rankMap[$status] ?? 0;
    $steps = [];
    foreach ($labels as $i => $label) {
        $cls = $i <= $rank ? 'done' : ($i === $rank + 1 ? 'current' : '');
        $steps[] = ['label' => $label, 'class' => $cls];
    }
    return $steps;
}

$statusMeta = [
    'Pending'     => ['class' => 'st-pending',     'icon' => 'hourglass-split'],
    'Recommended' => ['class' => 'st-recommended', 'icon' => 'hand-thumbs-up-fill'],
    'Approved'    => ['class' => 'st-approved',    'icon' => 'check-circle-fill'],
    'Rejected'    => ['class' => 'st-rejected',    'icon' => 'x-circle-fill'],
    'Completed'   => ['class' => 'st-completed',   'icon' => 'flag-fill'],
    'Cancelled'   => ['class' => 'st-cancelled',   'icon' => 'slash-circle-fill'],
];

/* ============================================================
   REQUEST HANDLING
   ============================================================ */
$mobile   = '';
$searched = false;
$invalid  = false;
$rows     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mobile'])) {
    $mobile   = trim($_POST['mobile']);
    $searched = true;

    if (!preg_match('/^\d{10}$/', $mobile)) {
        $invalid = true;
    } else {
        $stmt = $conn->prepare("
            SELECT va.*,
                   d.driver_name, d.driver_mob,
                   v.vehicle_type, v.vehicle_name, v.vehicle_no,
                   u.name AS forwarded_admin_name, u.designation
            FROM vehicle_applications va
            LEFT JOIN drivers  d ON va.driver_id    = d.id
            LEFT JOIN vehicles v ON va.vehicle_id   = v.id
            LEFT JOIN users    u ON va.forwarded_to = u.id
            WHERE va.mobile = ?
            ORDER BY va.created_at DESC
        ");
        $stmt->bind_param("s", $mobile);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Check Vehicle Application Status — ICMR-NIHR</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="./tracker.css" rel="stylesheet">
</head>
<body>
<?php include('./header.php'); ?>

<header class="ph">
  <div class="container">
    <span class="ph-badge">ICMR-NIHR Jodhpur</span>
    <div class="ph-icon"><i class="bi bi-clipboard2-check-fill"></i></div>
    <h1>Track Vehicle Application</h1>
    <p>Enter your registered mobile number to check the live status</p>
  </div>
</header>

<main class="container pb-5">

  <!-- Tab switcher -->
  <div class="tabs-wrap">
    <nav class="tabs-nav" aria-label="Application type">
      <a class="tab-link active" href="check_status.php" aria-current="page">
        <i class="bi bi-car-front-fill"></i><span>Office Vehicle</span>
      </a>
      <a class="tab-link" href="hired_application_status.php">
        <i class="bi bi-truck-front-fill"></i><span>Hired Vehicle</span>
      </a>
    </nav>
  </div>

  <div class="fc">
    <div class="fb p-4 p-md-5">

      <div class="search-title">
        <div class="si"><i class="bi bi-search"></i></div>
        <div>
          <h4>Check Application Status</h4>
          <small>Shows every request filed with this mobile number, most recent first</small>
        </div>
      </div>

      <form method="POST" class="row g-2" novalidate>
        <div class="col-md-9">
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-phone"></i></span>
            <input type="tel" name="mobile" class="form-control" placeholder="Enter 10-digit mobile number"
                   value="<?= e($mobile) ?>" maxlength="10" inputmode="numeric" pattern="\d{10}"
                   autocomplete="tel-national" aria-label="Mobile number" required>
          </div>
        </div>
        <div class="col-md-3 d-grid">
          <button class="btn btn-search"><i class="bi bi-search me-1"></i> Check Status</button>
        </div>
      </form>

<?php if ($searched): ?>

  <?php if ($invalid): ?>
      <div class="empty-state warn">
        <i class="bi bi-exclamation-triangle-fill"></i>
        Please enter a valid <strong>10-digit</strong> mobile number.
      </div>

  <?php elseif (empty($rows)): ?>
      <div class="empty-state">
        <i class="bi bi-x-circle-fill"></i>
        No application found for <strong><?= e($mobile) ?></strong>.<br>
        Please check the mobile number and try again.
      </div>

  <?php else: ?>
      <div class="result-head">
        <h5>Your Applications</h5>
        <span class="result-count"><?= count($rows) ?> found</span>
      </div>

    <?php foreach ($rows as $row):
        $status = $row['status'];
        $meta   = $statusMeta[$status] ?? ['class' => 'st-default', 'icon' => 'circle-fill'];
        $steps  = buildSteps($status);

        $isSomeone   = ($row['booking_for'] === 'Someone' && !empty($row['other_person_name']));
        $displayName = $isSomeone ? $row['other_person_name'] : $row['applicant_name'];

        $coTravelers = [];
        for ($i = 1; $i <= 6; $i++) {
            if (!empty($row["cotraveler$i"])) $coTravelers[] = $row["cotraveler$i"];
        }

        $assignVisible = in_array($status, ['Approved','Completed','Cancelled','Started','Pending','In Progress','Departure'], true);
        $driverLine  = $row['driver_name']  ? $row['driver_name'] . " (" . $row['driver_mob'] . ")" : "Not assigned yet";
        $vehicleLine = $row['vehicle_name'] ? $row['vehicle_type'] . " – " . $row['vehicle_name'] . " (" . $row['vehicle_no'] . ")" : "Not assigned yet";
        $officer     = trim(($row['forwarded_admin_name'] ?? '—') . " (" . ($row['designation'] ?? '') . ")");

        // WhatsApp-friendly plain-text summary
        $L   = [];
        $L[] = "*Vehicle Application Status — ICMR-NIHR Jodhpur*";
        $L[] = "";
        $L[] = "Application ID: #" . $row['id'];
        $L[] = "Status: " . $status;
        $L[] = "";
        $L[] = "*Applicant*";
        $L[] = "Name: " . $displayName;
        if ($isSomeone) $L[] = "Booked by: " . $row['applicant_name'];
        $L[] = "Staff Type: " . $row['staff_type'];
        $L[] = "Mobile: " . $row['mobile'];
        if ($coTravelers) $L[] = "Co-Travelers: " . implode(", ", $coTravelers);
        $L[] = "";
        $L[] = "*Journey*";
        $L[] = "Vehicle: " . $row['vehicle_required'];
        $L[] = "From: " . fmtDt($row['datetime_from']);
        $L[] = "To: " . fmtDt($row['datetime_to']);
        if (!empty($row['place_of_visit'])) $L[] = "Place of Visit: " . $row['place_of_visit'];
        $L[] = "";
        $L[] = "*Forwarded To*";
        $L[] = $officer;
        $L[] = "";
        $L[] = "*Vehicle & Driver*";
        $L[] = $assignVisible ? "Driver: " . $driverLine . "\nVehicle: " . $vehicleLine : "Not assigned yet";
        $L[] = "";
        $L[] = "Remarks: " . ($row['remark'] ?: "No remarks");
        $L[] = "";
        $L[] = "Submitted on: " . fmtDt($row['created_at']);
        $shareText = implode("\n", $L);
    ?>
      <article class="app-card">
        <div class="app-head">
          <div class="app-id">
            <span class="ico"><i class="bi bi-file-earmark-text"></i></span>
            <span>Application #<?= e($row['id']) ?>
              <span class="sub">Submitted <?= e(fmtDt($row['created_at'])) ?></span>
            </span>
          </div>
          <div class="app-actions">
            <span class="status-pill <?= e($meta['class']) ?>"><i class="bi bi-<?= e($meta['icon']) ?>"></i> <?= e($status) ?></span>
            <button type="button" class="btn-wa copy-wa" data-copy="<?= e($shareText) ?>" aria-label="Copy details for WhatsApp">
              <i class="bi bi-whatsapp"></i><span>Copy for WhatsApp</span>
            </button>
          </div>
        </div>

        <ol class="stepper" aria-label="Application progress">
          <?php foreach ($steps as $s): ?>
            <li class="step <?= e($s['class']) ?>"><?= e($s['label']) ?></li>
          <?php endforeach; ?>
        </ol>

        <div class="app-body">
          <div class="info-grid">

            <!-- LEFT -->
            <div>
              <section class="blk">
                <div class="blk-title"><i class="bi bi-person-circle"></i> Applicant Details</div>
                <div class="kv-row"><div class="kv-label">Booking For</div><div class="kv-value"><?= e($row['booking_for']) ?></div></div>
                <?php if ($isSomeone): ?>
                  <div class="kv-row"><div class="kv-label">Person</div><div class="kv-value"><?= e($row['other_person_name']) ?></div></div>
                  <div class="kv-row"><div class="kv-label">Booked By</div><div class="kv-value"><?= e($row['applicant_name']) ?></div></div>
                <?php else: ?>
                  <div class="kv-row"><div class="kv-label">Name</div><div class="kv-value"><?= e($row['applicant_name']) ?></div></div>
                <?php endif; ?>
                <div class="kv-row"><div class="kv-label">Staff Type</div><div class="kv-value"><?= e($row['staff_type']) ?></div></div>
                <div class="kv-row"><div class="kv-label">Mobile</div><div class="kv-value"><?= e($row['mobile']) ?></div></div>
                <div class="kv-row"><div class="kv-label">Email</div><div class="kv-value"><?= e($row['email']) ?></div></div>
                <div class="kv-row"><div class="kv-label">Purpose</div><div class="kv-value"><?= e($row['purpose']) ?></div></div>
              </section>

              <section class="blk">
                <div class="blk-title"><i class="bi bi-people-fill"></i> Co-Travelers</div>
                <div class="chip-wrap">
                  <?php if ($coTravelers): foreach ($coTravelers as $ct): ?>
                    <span class="chip"><?= e($ct) ?></span>
                  <?php endforeach; else: ?>
                    <span class="chip muted">No co-travelers</span>
                  <?php endif; ?>
                </div>
              </section>
            </div>

            <!-- RIGHT -->
            <div>
              <section class="blk">
                <div class="blk-title"><i class="bi bi-geo-alt-fill"></i> Journey Details</div>
                <div class="kv-row"><div class="kv-label">Vehicle</div><div class="kv-value"><?= e($row['vehicle_required']) ?></div></div>
                <div class="kv-row"><div class="kv-label">From</div><div class="kv-value"><?= e(fmtDt($row['datetime_from'])) ?></div></div>
                <div class="kv-row"><div class="kv-label">To</div><div class="kv-value"><?= e(fmtDt($row['datetime_to'])) ?></div></div>
                <div class="kv-row"><div class="kv-label">Place of Visit</div><div class="kv-value"><?= !empty($row['place_of_visit']) ? e($row['place_of_visit']) : '—' ?></div></div>
              </section>

              <section class="blk">
                <div class="blk-title"><i class="bi bi-person-badge-fill"></i> Forwarded To</div>
                <div class="kv-row"><div class="kv-label">Officer</div><div class="kv-value"><?= e($officer) ?></div></div>
              </section>

              <section class="blk">
                <div class="blk-title"><i class="bi bi-truck"></i> Vehicle &amp; Driver</div>
                <?php if ($assignVisible): ?>
                  <div class="assign-box">
                    <div class="kv-row"><div class="kv-label">Driver</div><div class="kv-value"><?= e($driverLine) ?></div></div>
                    <div class="kv-row"><div class="kv-label">Vehicle</div><div class="kv-value"><?= e($vehicleLine) ?></div></div>
                  </div>
                <?php else: ?>
                  <div class="assign-warn"><i class="bi bi-exclamation-triangle-fill"></i> Driver &amp; vehicle not assigned yet.</div>
                <?php endif; ?>
              </section>

              <section class="blk">
                <div class="blk-title"><i class="bi bi-chat-left-text-fill"></i> Remarks</div>
                <?php if (!empty($row['remark'])): ?>
                  <div class="remark-box"><?= nl2br(e($row['remark'])) ?></div>
                <?php else: ?>
                  <div class="remark-box empty">No remarks</div>
                <?php endif; ?>
              </section>
            </div>

          </div>
        </div>

        <div class="app-foot"><i class="bi bi-clock-history"></i> Application submitted on <?= e(fmtDt($row['created_at'])) ?></div>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>

<?php endif; ?>

    </div>
  </div>
</main>

<div id="copyToast" role="status" aria-live="polite"><i class="bi bi-whatsapp"></i><span id="copyToastText">Copied — paste it in WhatsApp</span></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
<script>
'use strict';

function copyText(text) {
  if (navigator.clipboard && window.isSecureContext) {
    return navigator.clipboard.writeText(text);
  }
  return new Promise((resolve, reject) => {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.cssText = 'position:fixed;left:-9999px;opacity:0';
    document.body.appendChild(ta);
    ta.focus(); ta.select();
    try { document.execCommand('copy') ? resolve() : reject(); }
    catch (err) { reject(err); }
    finally { document.body.removeChild(ta); }
  });
}

function showToast(msg) {
  const t = document.getElementById('copyToast');
  document.getElementById('copyToastText').textContent = msg;
  t.classList.add('on');
  clearTimeout(showToast._tid);
  showToast._tid = setTimeout(() => t.classList.remove('on'), 2200);
}

document.addEventListener('click', function (e) {
  const btn = e.target.closest('.copy-wa');
  if (!btn) return;
  copyText(btn.getAttribute('data-copy') || '').then(() => {
    const icon = btn.querySelector('i'), label = btn.querySelector('span');
    const prev = label.textContent;
    btn.classList.add('copied');
    icon.className = 'bi bi-check2';
    label.textContent = 'Copied!';
    showToast('Copied — paste it in WhatsApp');
    setTimeout(() => {
      btn.classList.remove('copied');
      icon.className = 'bi bi-whatsapp';
      label.textContent = prev;
    }, 1800);
  }).catch(() => showToast('Could not copy — please try again'));
});
</script>
</body>
</html>
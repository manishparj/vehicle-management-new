<?php
include("./config/db.php");

/* ============================================================
   CONSTANTS
   ============================================================ */
$TRIP_TYPES = [
    'one_way'    => 'One Way Trip',
    'round_trip' => 'Round Trip',
];

$LOCATION_LABELS = [
    'mrhu_bhanpur_kallan_jaipur'     => 'MRHRU, Bhanpur Kallan, Jaipur (Rajasthan)',
    'icmr_niht_jodhpur_or_rajasthan' => 'ICMR-NIHR, Jodhpur (Rajasthan) or anywhere in Rajasthan',
    'any_city_rajasthan'             => 'Any city of Rajasthan',
    'any_city_india_excl_rajasthan'  => 'Any city of India excluding Rajasthan',
    'other'                          => 'Other',
];

$STATUS_META = [
    'pending'   => ['class' => 'st-pending',   'icon' => 'hourglass-split'],
    'approved'  => ['class' => 'st-approved',  'icon' => 'check-circle-fill'],
    'rejected'  => ['class' => 'st-rejected',  'icon' => 'x-circle-fill'],
    'completed' => ['class' => 'st-completed', 'icon' => 'flag-fill'],
    'cancelled' => ['class' => 'st-cancelled', 'icon' => 'slash-circle-fill'],
];

/* Driver & vehicle details are shown to the applicant only once the request is approved */
const ASSIGNMENT_VISIBLE_STATUSES = ['approved', 'completed'];

/* ============================================================
   HELPERS
   ============================================================ */
function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

function fmtDt($v, string $fmt = 'd M Y, h:i A'): string {
    if (empty($v) || $v === '0000-00-00 00:00:00') return '—';
    $t = strtotime($v);
    return $t ? date($fmt, $t) : (string)$v;
}

function buildSteps(string $statusKey): array {
    if ($statusKey === 'rejected' || $statusKey === 'cancelled') {
        return [
            ['label' => 'Submitted',          'class' => 'done'],
            ['label' => ucfirst($statusKey),  'class' => 'fail' . ($statusKey === 'cancelled' ? ' muted-fail' : '')],
        ];
    }
    $rank = ['pending' => 0, 'approved' => 1, 'completed' => 2][$statusKey] ?? 0;
    $out  = [];
    foreach (['Submitted', 'Approved', 'Completed'] as $i => $label) {
        $out[] = ['label' => $label, 'class' => $i <= $rank ? 'done' : ($i === $rank + 1 ? 'current' : '')];
    }
    return $out;
}

/**
 * $assign  = assignment rows of THIS application, keyed 'categoryId|vehicleIndex'
 * $showAsg = whether driver & vehicle details may be shown for this application
 */
function buildAppRecord(array $app, array $catMap, array $projectMap, array $TRIP_TYPES, array $LOCATION_LABELS,
                        array $assign = [], bool $showAsg = false): array {
    $coTravelers  = json_decode($app['co_travelers'] ?? '[]', true) ?: [];
    $selectedCats = json_decode($app['selected_categories'] ?? '[]', true) ?: [];

    $tripKey = $app['trip_type'] ?? '';
    $tripLbl = $TRIP_TYPES[$tripKey] ?? ucfirst(str_replace('_', ' ', $tripKey));
    $locKey  = $app['place_of_visit'] ?? '';
    $locLbl  = $LOCATION_LABELS[$locKey] ?? ucfirst(str_replace('_', ' ', $locKey));
    $projId  = (int)($app['project_id'] ?? 0);

    $cats = [];
    $totalPrice = 0.0;
    $totalVeh = 0; $assigned = 0;
    foreach ($selectedCats as $sc) {
        $ci = $catMap[(int)($sc['id'] ?? 0)] ?? null;
        if (!$ci) continue;

        $catTripKey = $ci['trip_type'] ?? '';
        $catLocKey  = $ci['location_type'] ?? '';
        $catLocLbl  = $LOCATION_LABELS[$catLocKey] ?? ucfirst(str_replace('_', ' ', $catLocKey));
        if ($catLocKey === 'other' && !empty($ci['location_other'])) $catLocLbl = $ci['location_other'];

        $qty       = max(0, (int)($sc['qty'] ?? 0));
        $lineTotal = (float)$ci['price_per_vehicle'] * $qty;
        $totalPrice += $lineTotal;
        $totalVeh   += $qty;

        // one row per vehicle with its driver / vehicle number (empty strings when not yet assigned)
        $vehicles = [];
        if ($showAsg) {
            for ($i = 1; $i <= $qty; $i++) {
                $a = $assign[(int)$ci['id'] . '|' . $i] ?? null;
                $v = [
                    'idx'            => $i,
                    'driver_name'    => trim((string)($a['driver_name'] ?? '')),
                    'driver_mobile'  => trim((string)($a['driver_mobile'] ?? '')),
                    'vehicle_number' => trim((string)($a['vehicle_number'] ?? '')),
                ];
                if ($v['driver_name'] !== '' || $v['vehicle_number'] !== '') $assigned++;
                $vehicles[] = $v;
            }
        }

        $cats[] = [
            'id'              => (int)$ci['id'],
            'category_name'   => $ci['category_name'],
            'trip_type_label' => $TRIP_TYPES[$catTripKey] ?? ucfirst(str_replace('_', ' ', $catTripKey)),
            'location_label'  => $catLocLbl,
            'vehicle_details' => $ci['vehicle_details'] ?? '',
            'parameters'      => $ci['parameters'] ?? '',
            'unit_price'      => (float)$ci['price_per_vehicle'],
            'qty'             => $qty,
            'line_total'      => $lineTotal,
            'vehicles'        => $vehicles,
        ];
    }

    return [
        'id'                    => (int)$app['id'],
        'status'                => $app['status'] ?? '',
        'created_at'            => $app['created_at'] ?? '',
        'applicant_name'        => $app['applicant_name'] ?? '',
        'applicant_designation' => $app['applicant_designation'] ?? '',
        'applicant_email'       => $app['applicant_email'] ?? '',
        'applicant_mobile'      => $app['applicant_mobile'] ?? '',
        'project'               => $projectMap[$projId] ?? '',
        'trip_type_key'         => $tripKey,
        'trip_type_label'       => $tripLbl,
        'datetime_from'         => $app['datetime_from'] ?? '',
        'datetime_to'           => $app['datetime_to'] ?? '',
        'place_required'        => $app['place_required'] ?? '',
        'place_of_visit_label'  => $locLbl,
        'purpose'               => $app['purpose'] ?? '',
        'details'               => $app['details'] ?? '',
        'co_travelers'          => $coTravelers,
        'categories'            => $cats,
        'total_price'           => $totalPrice,
        'total_vehicles'        => $totalVeh,
        'assigned_vehicles'     => $assigned,
        'show_assignments'      => $showAsg,
        'tour_order_pdf'        => $app['tour_order_pdf'] ?? '',
        'super_admin_remark'    => $app['super_admin_remark'] ?? '',
        'forwarded_remark'      => $app['forwarded_remark'] ?? '',
        'actual_departure'      => $app['actual_departure'] ?? '',
        'actual_arrival'        => $app['actual_arrival'] ?? '',
        'in_km'                 => $app['in_km'] ?? '',
        'out_km'                => $app['out_km'] ?? '',
    ];
}

/* ============================================================
   REQUEST HANDLING
   ============================================================ */
$applications = [];
$searched     = false;
$mobile       = '';
$invalid      = false;
$catMap       = [];
$projectMap   = [];
$assignByApp  = [];   // [appId => ['catId|idx' => row]]

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mobile   = trim($_POST['mobile'] ?? '');
    $searched = true;

    if (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
        $invalid = true;
    } else {
        $stmt = $conn->prepare("SELECT * FROM hired_applications WHERE applicant_mobile = ? ORDER BY created_at DESC");
        $stmt->bind_param("s", $mobile);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $applications[] = $row;
        $stmt->close();

        // Lookup tables are only needed when there are results to render
        if ($applications) {
            $cr = $conn->query("SELECT id, category_name, trip_type, location_type, location_other,
                                       vehicle_details, parameters, price_per_vehicle
                                FROM hired_vehicles");
            if ($cr) while ($c = $cr->fetch_assoc()) $catMap[(int)$c['id']] = $c;

            $pr = $conn->query("SELECT id, label FROM projects");
            if ($pr) while ($p = $pr->fetch_assoc()) $projectMap[(int)$p['id']] = $p['label'];

            // Driver & vehicle assignments — only for this mobile's approved / completed applications
            $ids = [];
            foreach ($applications as $a) {
                if (in_array(strtolower(trim((string)($a['status'] ?? ''))), ASSIGNMENT_VISIBLE_STATUSES, true)) $ids[] = (int)$a['id'];
            }
            if ($ids) {
                $in = implode(',', array_map('intval', $ids));      // ints only — safe to inline
                $ar = $conn->query("SELECT application_id, category_id, vehicle_index, driver_name, driver_mobile, vehicle_number
                                    FROM hired_application_vehicles
                                    WHERE deleted_at IS NULL AND application_id IN ($in)");
                if ($ar) while ($x = $ar->fetch_assoc()) {
                    $assignByApp[(int)$x['application_id']][(int)$x['category_id'] . '|' . (int)$x['vehicle_index']] = $x;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Track Hired Vehicle Application — ICMR-NIHR</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="./tracker.css" rel="stylesheet" id="trackerCss">
<!-- Driver & vehicle table (kept here so tracker.css needs no change; also copied into the PDF window) -->
<style id="asgCss">
.asg-wrap{margin-top:12px;padding-top:10px;border-top:1px dashed var(--line,#E1E8EC)}
.asg-title{display:flex;align-items:center;gap:6px;font-size:.72rem;font-weight:800;color:var(--tm,#5E6E7D);text-transform:uppercase;letter-spacing:.07em;margin-bottom:8px}
.asg-title i{color:var(--teal,#0F7C86)}
.asg-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch;border:1px solid var(--line,#E1E8EC);border-radius:10px}
.asg-tbl{width:100%;min-width:480px;border-collapse:collapse;font-size:.86rem;margin:0}
.asg-tbl th,.asg-tbl td{padding:8px 12px;border-bottom:1px solid #EEF2F5;text-align:left;vertical-align:middle}
.asg-tbl tbody tr:last-child td{border-bottom:0}
.asg-tbl thead th{background:#F3F6F9;color:var(--tm,#5E6E7D);font-size:.66rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;white-space:nowrap}
.asg-tbl .c-idx{width:52px;text-align:center;font-weight:800;color:var(--teal,#0F7C86)}
.asg-tbl .vno{font-weight:700;letter-spacing:.03em;white-space:nowrap}
.asg-tbl a{color:inherit;text-decoration:none;white-space:nowrap}
.asg-tbl a:hover{text-decoration:underline}
.asg-pending{color:#8896A3;font-style:italic}
.asg-note{margin-top:10px;font-size:.8rem;color:var(--tm,#5E6E7D);background:#F7F9FB;border:1px dashed #D5DDE4;border-radius:10px;padding:8px 12px}
@media (max-width:575.98px){
  .asg-scroll{border:0;overflow:visible}
  .asg-tbl{min-width:0}
  .asg-tbl thead{display:none}
  .asg-tbl,.asg-tbl tbody,.asg-tbl tr,.asg-tbl td{display:block;width:100%}
  .asg-tbl tr{border:1px solid var(--line,#E1E8EC);border-radius:10px;padding:6px 12px;margin-bottom:8px;background:#fff}
  .asg-tbl td{display:flex;gap:10px;border:0;padding:4px 0;text-align:left}
  .asg-tbl td::before{content:attr(data-label);flex:0 0 84px;font-size:.64rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--tm,#5E6E7D)}
  .asg-tbl td.c-idx{text-align:left;width:100%}
}
</style>
</head>
<body>
<?php include('./header.php'); ?>

<header class="ph">
  <div class="container">
    <span class="ph-badge">ICMR-NIHR Jodhpur</span>
    <div class="ph-icon"><i class="bi bi-truck-front-fill"></i></div>
    <h1>Track Hired Vehicle Application</h1>
    <p>Enter your registered mobile number to view the status of your hired vehicle requests</p>
  </div>
</header>

<main class="container pb-5">

  <!-- Tab switcher -->
  <div class="tabs-wrap">
    <nav class="tabs-nav" aria-label="Application type">
      <a class="tab-link" href="check_status.php">
        <i class="bi bi-car-front-fill"></i><span>Office Vehicle</span>
      </a>
      <a class="tab-link active" href="hired_application_status.php" aria-current="page">
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
          <small>Shows every hired vehicle request filed with this mobile number, most recent first</small>
        </div>
      </div>

      <form method="POST" class="row g-2" novalidate>
        <div class="col-md-9">
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-phone"></i></span>
            <input type="tel" name="mobile" class="form-control" placeholder="Enter 10-digit mobile number"
                   value="<?= e($mobile) ?>" maxlength="10" inputmode="numeric" pattern="[6-9]\d{9}"
                   autocomplete="tel-national" aria-label="Mobile number" required>
          </div>
        </div>
        <div class="col-md-3 d-grid">
          <button type="submit" class="btn btn-search"><i class="bi bi-search me-1"></i> Check Status</button>
        </div>
      </form>

<?php if ($searched): ?>

  <?php if ($invalid): ?>
      <div class="empty-state warn">
        <i class="bi bi-exclamation-triangle-fill"></i>
        Please enter a valid <strong>10-digit</strong> mobile number.
      </div>

  <?php elseif (empty($applications)): ?>
      <div class="empty-state">
        <i class="bi bi-inbox-fill"></i>
        No applications found for <strong><?= e($mobile) ?></strong>.<br>
        Please check the mobile number and try again.
      </div>

  <?php else: ?>
      <div class="result-head">
        <h5>Your Applications</h5>
        <span class="result-count"><?= count($applications) ?> found</span>
      </div>

    <?php foreach ($applications as $app):
        $statusKey = strtolower(trim((string)($app['status'] ?? '')));
        $showAsg   = in_array($statusKey, ASSIGNMENT_VISIBLE_STATUSES, true);
        $r         = buildAppRecord($app, $catMap, $projectMap, $TRIP_TYPES, $LOCATION_LABELS,
                                    $assignByApp[(int)$app['id']] ?? [], $showAsg);
        $meta      = $STATUS_META[$statusKey] ?? ['class' => '', 'icon' => 'circle-fill'];
        $steps     = buildSteps($statusKey);

        $hasKm   = ($r['out_km'] !== '' && $r['out_km'] !== null) || ($r['in_km'] !== '' && $r['in_km'] !== null);
        $hasTrip = !empty($r['actual_departure']) || !empty($r['actual_arrival']) || $hasKm;
        $totalKm = (is_numeric($r['in_km']) && is_numeric($r['out_km']))
                 ? abs((float)$r['in_km'] - (float)$r['out_km']) : null;

        // JSON payload for copy / PDF (display-friendly values)
        $payload = $r;
        $payload['datetime_from']    = fmtDt($r['datetime_from']);
        $payload['datetime_to']      = !empty($r['datetime_to']) ? fmtDt($r['datetime_to']) : '';
        $payload['actual_departure'] = !empty($r['actual_departure']) ? fmtDt($r['actual_departure']) : '';
        $payload['actual_arrival']   = !empty($r['actual_arrival'])   ? fmtDt($r['actual_arrival'])   : '';
        $payload['created_at']       = fmtDt($r['created_at']);
        $jsonData = e(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    ?>
      <article class="app-card" data-app-json="<?= $jsonData ?>" data-app-id="<?= (int)$r['id'] ?>">
        <div class="app-head">
          <div class="app-id">
            <span class="ico"><i class="bi bi-file-earmark-text"></i></span>
            <span>Application #<?= (int)$r['id'] ?>
              <span class="sub">Submitted <?= e(fmtDt($r['created_at'])) ?></span>
            </span>
          </div>
          <div class="app-actions">
            <span class="status-pill <?= e($meta['class']) ?>"><i class="bi bi-<?= e($meta['icon']) ?>"></i> <?= e(ucfirst($statusKey ?: 'Unknown')) ?></span>
            <button type="button" class="btn-wa" onclick="copyAppDetails(this)" aria-label="Copy details">
              <i class="bi bi-clipboard-check"></i><span>Copy details</span>
            </button>
            <button type="button" class="btn-ghost" onclick="printSingle(this)" aria-label="Download PDF">
              <i class="bi bi-file-earmark-pdf"></i><span>Download PDF</span>
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

            <!-- LEFT: Applicant + Co-travelers -->
            <div>
              <section class="blk">
                <div class="blk-title"><i class="bi bi-person-circle"></i> Applicant Details</div>
                <div class="kv-row"><div class="kv-label">Name</div><div class="kv-value"><?= e($r['applicant_name']) ?></div></div>
                <?php if ($r['applicant_designation']): ?>
                <div class="kv-row"><div class="kv-label">Designation</div><div class="kv-value"><?= e($r['applicant_designation']) ?></div></div>
                <?php endif; ?>
                <?php if ($r['applicant_mobile']): ?>
                <div class="kv-row"><div class="kv-label">Mobile</div><div class="kv-value"><?= e($r['applicant_mobile']) ?></div></div>
                <?php endif; ?>
                <?php if ($r['applicant_email']): ?>
                <div class="kv-row"><div class="kv-label">Email</div><div class="kv-value"><?= e($r['applicant_email']) ?></div></div>
                <?php endif; ?>
                <?php if ($r['project']): ?>
                <div class="kv-row"><div class="kv-label">Project</div><div class="kv-value"><?= e($r['project']) ?></div></div>
                <?php endif; ?>
                <div class="kv-row"><div class="kv-label">Purpose</div><div class="kv-value"><?= e($r['purpose']) ?></div></div>
                <?php if (!empty($r['details'])): ?>
                <div class="kv-row"><div class="kv-label">Details</div><div class="kv-value"><?= nl2br(e($r['details'])) ?></div></div>
                <?php endif; ?>
              </section>

              <section class="blk">
                <div class="blk-title"><i class="bi bi-people-fill"></i> Co-Travelers</div>
                <div class="chip-wrap">
                  <?php if (!empty($r['co_travelers'])): foreach ($r['co_travelers'] as $ct): ?>
                    <span class="chip"><?= e($ct) ?></span>
                  <?php endforeach; else: ?>
                    <span class="chip muted">No co-travelers</span>
                  <?php endif; ?>
                </div>
              </section>
            </div>

            <!-- RIGHT: Journey + Remarks -->
            <div>
              <section class="blk">
                <div class="blk-title"><i class="bi bi-geo-alt-fill"></i> Journey Details</div>
                <?php if ($r['trip_type_key']): ?>
                <div class="kv-row"><div class="kv-label">Trip Type</div><div class="kv-value">
                  <span class="badge-trip <?= e($r['trip_type_key']) ?>"><i class="bi bi-<?= $r['trip_type_key'] === 'one_way' ? 'arrow-right' : 'arrow-left-right' ?>"></i><?= e($r['trip_type_label']) ?></span>
                </div></div>
                <?php endif; ?>
                <div class="kv-row"><div class="kv-label">From</div><div class="kv-value"><?= e(fmtDt($r['datetime_from'])) ?></div></div>
                <?php if (!empty($r['datetime_to'])): ?>
                <div class="kv-row"><div class="kv-label">To</div><div class="kv-value"><?= e(fmtDt($r['datetime_to'])) ?></div></div>
                <?php endif; ?>
                <div class="kv-row"><div class="kv-label">Place Required</div><div class="kv-value"><?= e($r['place_required']) ?: '—' ?></div></div>
                <div class="kv-row"><div class="kv-label">Place of Visit</div><div class="kv-value"><?= e($r['place_of_visit_label']) ?: '—' ?></div></div>
              </section>

              <section class="blk">
                <div class="blk-title"><i class="bi bi-chat-left-text-fill"></i> Remarks</div>
                <?php if (!empty($r['super_admin_remark']) || !empty($r['forwarded_remark'])): ?>
                  <?php if (!empty($r['super_admin_remark'])): ?>
                    <div class="remark-box"><small>Admin Remark</small><?= nl2br(e($r['super_admin_remark'])) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($r['forwarded_remark'])): ?>
                    <div class="remark-box"><small>Forwarded Admin Remark</small><?= nl2br(e($r['forwarded_remark'])) ?></div>
                  <?php endif; ?>
                <?php else: ?>
                  <div class="remark-box empty">No remarks</div>
                <?php endif; ?>
              </section>

              <?php if ($r['tour_order_pdf']): ?>
              <section class="blk">
                <a href="./uploads/hired_tour_order/<?= rawurlencode($r['tour_order_pdf']) ?>" target="_blank" rel="noopener" class="btn-doc">
                  <i class="bi bi-file-earmark-pdf"></i> View Tour Order
                </a>
              </section>
              <?php endif; ?>
            </div>

            <!-- FULL WIDTH: Vehicle categories (+ driver & vehicle details) -->
            <?php if (!empty($r['categories'])): ?>
            <section class="blk full">
              <div class="blk-title"><i class="bi bi-truck"></i> Vehicle Categories<?php if ($showAsg): ?>, Drivers &amp; Vehicles<?php endif; ?></div>
              <?php foreach ($r['categories'] as $cat): ?>
                <div class="cat-box">
                  <div class="cat-head">
                    <div class="cat-name"><?= e($cat['category_name']) ?><small>#<?= (int)$cat['id'] ?></small></div>
                    <div class="cat-price">₹<?= number_format($cat['unit_price'], 2) ?> × <?= (int)$cat['qty'] ?> = ₹<?= number_format($cat['line_total'], 2) ?></div>
                  </div>
                  <div class="cat-grid">
                    <div><span class="lbl">Trip Type</span><span class="val"><?= e($cat['trip_type_label']) ?></span></div>
                    <div><span class="lbl">Location</span><span class="val"><?= e($cat['location_label']) ?></span></div>
                    <?php if (!empty($cat['vehicle_details'])): ?>
                    <div class="full"><span class="lbl">Vehicle Details</span><span class="val"><?= nl2br(e($cat['vehicle_details'])) ?></span></div>
                    <?php endif; ?>
                    <?php if (!empty($cat['parameters'])): ?>
                    <div class="full"><span class="lbl">Parameters</span><span class="val"><?= nl2br(e($cat['parameters'])) ?></span></div>
                    <?php endif; ?>
                  </div>

                  <?php if ($showAsg && !empty($cat['vehicles'])): ?>
                  <div class="asg-wrap">
                    <div class="asg-title"><i class="bi bi-person-badge"></i> Driver &amp; Vehicle Details</div>
                    <div class="asg-scroll">
                      <table class="asg-tbl">
                        <thead><tr><th class="c-idx">#</th><th>Driver Name</th><th>Driver Mobile</th><th>Vehicle Number</th></tr></thead>
                        <tbody>
                        <?php foreach ($cat['vehicles'] as $v):
                            $none = ($v['driver_name'] === '' && $v['driver_mobile'] === '' && $v['vehicle_number'] === ''); ?>
                          <tr>
                            <td class="c-idx" data-label="Vehicle"><?= (int)$v['idx'] ?></td>
                            <?php if ($none): ?>
                              <td colspan="3" class="asg-pending">To be assigned</td>
                            <?php else: ?>
                              <td data-label="Driver"><?= e($v['driver_name'] ?: '—') ?></td>
                              <td data-label="Mobile"><?php if ($v['driver_mobile'] !== ''): ?><a href="tel:<?= e($v['driver_mobile']) ?>"><i class="bi bi-telephone me-1"></i><?= e($v['driver_mobile']) ?></a><?php else: ?>—<?php endif; ?></td>
                              <td data-label="Vehicle No." class="vno"><?= e($v['vehicle_number'] ?: '—') ?></td>
                            <?php endif; ?>
                          </tr>
                        <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
              <?php if ($showAsg && $r['total_vehicles'] > 0 && $r['assigned_vehicles'] === 0): ?>
                <div class="asg-note"><i class="bi bi-info-circle me-1"></i>Driver and vehicle details have not been assigned yet. They will appear here once assigned.</div>
              <?php endif; ?>
              <div class="cat-total"><span>Total</span><span>₹<?= number_format($r['total_price'], 2) ?></span></div>
            </section>
            <?php endif; ?>

            <!-- FULL WIDTH: Actual trip record -->
            <?php if ($hasTrip): ?>
            <section class="blk full">
              <div class="blk-title"><i class="bi bi-speedometer2"></i> Actual Trip Record</div>
              <div class="stat-row">
                <?php if (!empty($r['actual_departure'])): ?>
                  <div class="stat"><span class="lbl">Departure</span><span class="val"><?= e(fmtDt($r['actual_departure'])) ?></span></div>
                <?php endif; ?>
                <?php if (!empty($r['actual_arrival'])): ?>
                  <div class="stat"><span class="lbl">Arrival</span><span class="val"><?= e(fmtDt($r['actual_arrival'])) ?></span></div>
                <?php endif; ?>
                <?php if ($r['out_km'] !== '' && $r['out_km'] !== null): ?>
                  <div class="stat"><span class="lbl">Out KM</span><span class="val"><?= e($r['out_km']) ?></span></div>
                <?php endif; ?>
                <?php if ($r['in_km'] !== '' && $r['in_km'] !== null): ?>
                  <div class="stat"><span class="lbl">In KM</span><span class="val"><?= e($r['in_km']) ?></span></div>
                <?php endif; ?>
                <?php if ($totalKm !== null): ?>
                  <div class="stat hl"><span class="lbl">Total KM</span><span class="val"><?= e(number_format($totalKm, 2)) ?></span></div>
                <?php endif; ?>
              </div>
            </section>
            <?php endif; ?>

          </div>
        </div>

        <div class="app-foot"><i class="bi bi-clock-history"></i> Application submitted on <?= e(fmtDt($r['created_at'])) ?></div>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>

<?php endif; ?>

    </div>
  </div>
</main>

<div id="copyToast" role="status" aria-live="polite"><i class="bi bi-check-circle-fill"></i><span id="copyToastText">Copied to clipboard</span></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
<script>
'use strict';

/* ---------- Toast ---------- */
function showToast(msg) {
  const t = document.getElementById('copyToast');
  document.getElementById('copyToastText').textContent = msg;
  t.classList.add('on');
  clearTimeout(showToast._tid);
  showToast._tid = setTimeout(() => t.classList.remove('on'), 2200);
}

/* ---------- Record access ---------- */
function getRecord(btn) {
  const card = btn.closest('.app-card');
  if (!card) return null;
  try { return JSON.parse(card.getAttribute('data-app-json')); } catch (e) { return null; }
}

/* ---------- Plain-text summary ---------- */
function formatRecordText(r) {
  const L = [];
  const add = (label, v) => { if (v !== '' && v !== null && v !== undefined) L.push(`${label}: ${v}`); };
  const dash = v => (v !== '' && v !== null && v !== undefined) ? v : '—';

  L.push(`*HIRED VEHICLE APPLICATION #${r.id}*`);
  add('Status', r.status);
  L.push('');
  add('Applicant', r.applicant_name);
  add('Designation', r.applicant_designation);
  add('Email', r.applicant_email);
  add('Mobile', r.applicant_mobile);
  add('Project', r.project);
  add('Trip Type', r.trip_type_label);
  L.push('');
  add('From', r.datetime_from);
  add('To', r.datetime_to);
  add('Place Required', r.place_required);
  add('Place of Visit', r.place_of_visit_label);
  add('Purpose', r.purpose);
  add('Details', r.details);
  if (r.co_travelers && r.co_travelers.length) add('Co-Travelers', r.co_travelers.join(', '));
  L.push('');

  if (r.categories && r.categories.length) {
    L.push('*VEHICLE CATEGORIES*');
    r.categories.forEach((c, i) => {
      L.push(`${i + 1}. ${c.category_name} (#${c.id})`);
      L.push(`   Trip Type : ${c.trip_type_label}`);
      L.push(`   Location  : ${c.location_label}`);
      if (c.vehicle_details) L.push(`   Details   : ${c.vehicle_details}`);
      if (c.parameters)      L.push(`   Parameters: ${c.parameters}`);
      L.push(`   Price     : ₹${c.unit_price.toFixed(2)} × ${c.qty} = ₹${c.line_total.toFixed(2)}`);
      (c.vehicles || []).forEach(v => {
        const assigned = v.driver_name || v.driver_mobile || v.vehicle_number;
        L.push(assigned
          ? `   Vehicle ${v.idx}: Driver ${dash(v.driver_name)} | Mobile ${dash(v.driver_mobile)} | Vehicle No. ${dash(v.vehicle_number)}`
          : `   Vehicle ${v.idx}: To be assigned`);
      });
    });
    L.push(`*TOTAL: ₹${Number(r.total_price).toFixed(2)}*`);
    L.push('');
  }

  add('Admin Remark', r.super_admin_remark);
  add('Forwarded Admin Remark', r.forwarded_remark);
  add('Actual Departure', r.actual_departure);
  add('Actual Arrival', r.actual_arrival);
  add('Out KM', r.out_km);
  add('In KM', r.in_km);
  return L.join('\n').trim();
}

/* ---------- Copy ---------- */
function fallbackCopy(text, ok) {
  const ta = document.createElement('textarea');
  ta.value = text;
  ta.style.cssText = 'position:fixed;left:-9999px;opacity:0';
  document.body.appendChild(ta);
  ta.focus(); ta.select();
  try { document.execCommand('copy') ? ok() : showToast('Copy failed'); }
  catch (e) { showToast('Copy failed'); }
  document.body.removeChild(ta);
}

function copyAppDetails(btn) {
  const r = getRecord(btn);
  if (!r) { showToast('Could not read record'); return; }
  const text = formatRecordText(r);
  const ok = () => showToast('Copied to clipboard');

  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(text).then(ok).catch(() => fallbackCopy(text, ok));
  } else {
    fallbackCopy(text, ok);
  }
}

/* ---------- Print / Save as PDF ---------- */
function printSingle(btn) {
  const card = btn.closest('.app-card');
  if (!card) return;

  const clone = card.cloneNode(true);
  clone.querySelectorAll('.app-actions').forEach(el => el.remove());
  clone.style.animation = 'none';

  // use the very same stylesheet the page loaded (was a different path before)
  const cssUrl = document.getElementById('trackerCss').href;
  const asgCss = document.getElementById('asgCss').outerHTML;
  const win = window.open('', '_blank');
  if (!win) { showToast('Please allow pop-ups to download the PDF'); return; }

  win.document.write(`<!DOCTYPE html><html lang="en"><head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hired Vehicle Application #${card.dataset.appId}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="${cssUrl}" rel="stylesheet">
    ${asgCss}
    <style>
      body{background:#fff;padding:24px;max-width:900px;margin:0 auto}
      .doc-title{font-family:var(--font-head);font-size:1.25rem;font-weight:800;margin:0 0 2px;color:var(--navy)}
      .doc-sub{color:var(--tm);font-size:.85rem;margin-bottom:16px}
      .app-card{margin-top:0;box-shadow:none}
      .info-grid{grid-template-columns:1fr 1fr}
      .asg-tbl{min-width:0}.asg-scroll{overflow:visible}
      .asg-tbl,.asg-tbl thead{display:table}.asg-tbl thead{display:table-header-group}.asg-tbl tbody{display:table-row-group}.asg-tbl tr{display:table-row;border:0;padding:0;margin:0}
      .asg-tbl td,.asg-tbl th{display:table-cell;width:auto}.asg-tbl td::before{content:none}
      @media print{body{padding:0}}
    </style></head><body>
    <h1 class="doc-title"><i class="bi bi-truck-front-fill"></i> Hired Vehicle Application</h1>
    <div class="doc-sub">ICMR-NIHR Jodhpur — Generated on ${new Date().toLocaleString()}</div>
    ${clone.outerHTML}
    <script>window.addEventListener('load',function(){setTimeout(function(){window.print()},400)})<\/script>
  </body></html>`);
  win.document.close();
}
</script>
</body>
</html>
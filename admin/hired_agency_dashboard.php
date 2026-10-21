<?php
session_start();
include("../config/db.php");

// ================= AUTH GUARD =================
if (!isset($_SESSION['hired_user_id']) || ($_SESSION['hired_role'] ?? '') !== 'hired_agency') {
    header("Location: hired_login.php");
    exit;
}

$agencyId   = (int)$_SESSION['hired_user_id'];
$agencyName = $_SESSION['hired_user_name'] ?? 'Agency';

// ================= SHARED HELPERS =================
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function hired_csrf_token(): string {
    if (empty($_SESSION['hired_csrf'])) $_SESSION['hired_csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['hired_csrf'];
}
function hired_csrf_ok(): bool {
    return isset($_POST['csrf'], $_SESSION['hired_csrf']) && hash_equals($_SESSION['hired_csrf'], (string)$_POST['csrf']);
}
function hired_fmt_dt($v): string {
    if ($v === null || $v === '' || strpos((string)$v, '0000-00-00') === 0) return '';
    $t = strtotime((string)$v); return $t ? date('d/m/Y H:i', $t) : '';
}
function hired_money($n): string { return '₹' . number_format((float)$n, 2); }
function hired_json(array $a): void { while (ob_get_level() > 0) ob_end_clean(); echo json_encode($a); exit; }

const HIRED_TRIP_TYPES = [
    'one_way'    => 'One Way Trip',
    'round_trip' => 'Round Trip',
];
const HIRED_LOCATION_LABELS = [
    'mrhu_bhanpur_kallan_jaipur'     => 'MRHRU, Bhanpur Kallan, Jaipur (Rajasthan)',
    'icmr_niht_jodhpur_or_rajasthan' => 'ICMR-NIHR, Jodhpur (Rajasthan) or anywhere in Rajasthan',
    'any_city_rajasthan'             => 'Any city of Rajasthan',
    'any_city_india_excl_rajasthan'  => 'Any city of India excluding Rajasthan',
    'other'                          => 'Other',
];

/* ============================================================
   =============== AJAX ENDPOINTS =============================
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    ini_set('display_errors', '0'); ob_start();
    set_exception_handler(function (Throwable $e) { hired_json(['ok' => false, 'msg' => 'Server error: ' . $e->getMessage()]); });
    header('Content-Type: application/json');
    if (!hired_csrf_ok()) hired_json(['ok' => false, 'msg' => 'Session token expired. Please reload the page.']);
    $ajax = $_POST['ajax'];

    // --- DRIVER CRUD ---
    if ($ajax === 'save_driver') {
        $id     = (int)($_POST['id'] ?? 0);
        $name   = trim($_POST['driver_name'] ?? '');
        $mob    = trim($_POST['driver_mob'] ?? '');
        $status = $_POST['status'] === 'Inactive' ? 'Inactive' : 'Active';
        if ($name === '' || !preg_match('/^\d{10}$/', $mob)) hired_json(['ok' => false, 'msg' => 'Invalid name or mobile.']);

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE hired_drivers SET driver_name=?, driver_mob=?, status=? WHERE id=?");
            $stmt->bind_param("sssi", $name, $mob, $status, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO hired_drivers (driver_name, driver_mob, status) VALUES (?,?,?)");
            $stmt->bind_param("sss", $name, $mob, $status);
        }
        hired_json(['ok' => $stmt->execute()]);
    }
    if ($ajax === 'delete_driver') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM hired_drivers WHERE id=?");
        $stmt->bind_param("i", $id);
        hired_json(['ok' => $stmt->execute()]);
    }

    // --- VEHICLE CRUD ---
    if ($ajax === 'save_vehicle') {
        $id     = (int)($_POST['id'] ?? 0);
        $no     = trim($_POST['vehicle_no'] ?? '');
        $name   = trim($_POST['vehicle_name'] ?? '');
        $status = $_POST['status'] === 'Inactive' ? 'Inactive' : 'Active';
        if ($no === '') hired_json(['ok' => false, 'msg' => 'Vehicle number required.']);

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE hired_vehicles_detail SET vehicle_no=?, vehicle_name=?, status=? WHERE id=?");
            $stmt->bind_param("sssi", $no, $name, $status, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO hired_vehicles_detail (vehicle_no, vehicle_name, status) VALUES (?,?,?)");
            $stmt->bind_param("sss", $no, $name, $status);
        }
        hired_json(['ok' => $stmt->execute()]);
    }
    if ($ajax === 'delete_vehicle') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM hired_vehicles_detail WHERE id=?");
        $stmt->bind_param("i", $id);
        hired_json(['ok' => $stmt->execute()]);
    }

    // --- SAVE ALL ASSIGNMENTS (For Bookings) ---
    if ($ajax === 'save_all_assignments') {
        $appId = (int)($_POST['application_id'] ?? 0);
        $rows  = json_decode($_POST['rows'] ?? '[]', true);
        if ($appId <= 0 || !is_array($rows)) hired_json(['ok' => false, 'msg' => 'Invalid data.']);

        // Check if app is pending
        $chk = $conn->prepare("SELECT status FROM hired_applications WHERE id = ?");
        $chk->bind_param("i", $appId); $chk->execute();
        $row = $chk->get_result()->fetch_assoc(); $chk->close();
        if (!$row || $row['status'] !== 'pending') hired_json(['ok' => false, 'msg' => 'Application is not pending (it may be locked).']);

        $clean = [];
        foreach ($rows as $r) {
            $catId = (int)($r['cat_id'] ?? 0);
            $idx   = (int)($r['idx'] ?? 0);
            $dName = mb_substr(trim($r['driver_name'] ?? ''), 0, 150);
            $dMob  = mb_substr(trim($r['driver_mobile'] ?? ''), 0, 15);
            $vNum  = mb_substr(trim($r['vehicle_number'] ?? ''), 0, 30);
            if ($catId <= 0 || $idx <= 0) continue;
            if ($dMob !== '' && !preg_match('/^\d{10}$/', $dMob)) hired_json(['ok' => false, 'msg' => "Row #{$idx}: mobile must be exactly 10 digits."]);
            $clean[] = [$catId, $idx, $dName, $dMob, $vNum];
        }

        $stmt = $conn->prepare("INSERT INTO hired_application_vehicles
              (application_id, category_id, vehicle_index, driver_name, driver_mobile, vehicle_number, updated_by)
            VALUES (?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
              driver_name = VALUES(driver_name), driver_mobile = VALUES(driver_mobile),
              vehicle_number = VALUES(vehicle_number), updated_by = VALUES(updated_by), deleted_at = NULL");
        $ok = true;
        foreach ($clean as [$catId, $idx, $dName, $dMob, $vNum]) {
            $stmt->bind_param("iiisssi", $appId, $catId, $idx, $dName, $dMob, $vNum, $agencyId);
            if (!$stmt->execute()) $ok = false;
        }
        $stmt->close();
        hired_json(['ok' => $ok, 'msg' => $ok ? 'Assignments saved successfully.' : 'Some rows could not be saved.', 'updated_at' => date('d/m/Y H:i')]);
    }
}

// ================= FETCH DATA =================
$drivers = [];
$dRes = $conn->query("SELECT * FROM hired_drivers ORDER BY driver_name ASC");
if ($dRes) { while ($r = $dRes->fetch_assoc()) $drivers[] = $r; }

$vehicles = [];
$vRes = $conn->query("SELECT * FROM hired_vehicles_detail ORDER BY vehicle_no ASC");
if ($vRes) { while ($r = $vRes->fetch_assoc()) $vehicles[] = $r; }

// Fetch ALL Applications (grouped later)
$applications = [];
$appRes = $conn->query("SELECT * FROM hired_applications ORDER BY created_at DESC");
if ($appRes) { while ($a = $appRes->fetch_assoc()) $applications[] = $a; }

// Category map
$catMap = [];
$cRes = $conn->query("SELECT * FROM hired_vehicles");
if ($cRes) { while ($c = $cRes->fetch_assoc()) $catMap[(int)$c['id']] = $c; }

// Existing assignments map
$assign = [];
$asgRes = $conn->query("SELECT application_id, category_id, vehicle_index, driver_name, driver_mobile, vehicle_number, updated_at FROM hired_application_vehicles WHERE deleted_at IS NULL");
if ($asgRes) {
    while ($r = $asgRes->fetch_assoc()) {
        $assign[$r['application_id'].'|'.$r['category_id'].'|'.$r['vehicle_index']] = $r;
    }
}

// Group by Status
$groupedApps = ['pending' => [], 'approved' => [], 'completed' => []];
foreach ($applications as $app) {
    $st = $app['status'] ?? 'pending';
    if (isset($groupedApps[$st])) $groupedApps[$st][] = $app;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Agency Dashboard — Hired Vehicles</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{--navy:#0F2A43;--teal:#0E7C86;--teal-dark:#0A5F67;--paper:#F5F8F9;--card:#fff;--line:#E1E8EC;--tm:#5B6B7A}
body{font-family:'Inter',sans-serif;background:var(--paper);color:var(--navy)}
.dash-header{background:linear-gradient(155deg,var(--navy) 0%,var(--teal-dark) 100%);color:#fff;padding:20px 0}
.dash-header h1{font-weight:800;font-size:1.4rem;margin:0}
.card-soft{background:var(--card);border:1px solid var(--line);border-radius:12px;box-shadow:0 1px 2px rgba(15,42,67,.06);margin-bottom:20px;}
.card-header-teal{background:var(--teal);color:#fff;padding:12px 18px;border-radius:12px 12px 0 0;font-weight:600;display:flex;justify-content:space-between;align-items:center;}
.table-clean thead th{background:#F8FAFB;color:var(--tm);font-size:.75rem;text-transform:uppercase;font-weight:700;padding:10px 14px;}
.table-clean tbody td{padding:10px 14px;vertical-align:middle;}
.badge-status{font-size:.72rem;font-weight:700;padding:3px 8px;border-radius:999px;}
.badge-status.active{background:#D1E7DD;color:#0F5132;}
.badge-status.inactive{background:#E2E3E5;color:#41464B;}
.badge-status.pending{background:#FFF3CD;color:#664D03;}
.badge-status.approved{background:#D1E7DD;color:#0F5132;}
.badge-status.completed{background:#CFE2FF;color:#084298;}
.app-card{border:1px solid var(--line);border-radius:10px;padding:16px;margin-bottom:20px;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,0.03);}
.veh-input{border:1px solid var(--line);border-radius:6px;padding:5px 8px;font-size:.85rem;}
.cat-detail-box{background:#f8fcfc;border:1px solid #d0e5e8;border-radius:8px;padding:12px;margin-bottom:12px;}
.cat-detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:0.85rem;}
.cat-detail-grid .span-all{grid-column:1 / -1;}
.cat-detail-grid .lbl{display:block;font-weight:600;color:#5B6B7A;font-size:0.75rem;text-transform:uppercase;margin-bottom:2px;}
.cat-detail-grid .val{display:block;color:#0F2A43;}
</style>
</head>
<body>

<header class="dash-header">
  <div class="container d-flex justify-content-between align-items-center">
    <div>
      <h1><i class="bi bi-building me-2"></i>Agency Dashboard</h1>
      <div class="text-white-50 small mt-1">Welcome, <?= h($agencyName) ?></div>
    </div>
    <div class="d-flex gap-2 align-items-center">
      <a href="hired_logout.php" class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
    </div>
  </div>
</header>

<main class="container py-4">

  <!-- Main Tabs -->
  <ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-bookings" type="button"><i class="bi bi-journal-text me-1"></i>Bookings</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-drivers" type="button"><i class="bi bi-person-badge me-1"></i>My Drivers</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-vehicles" type="button"><i class="bi bi-truck me-1"></i>My Vehicles</button>
    </li>
  </ul>

  <div class="tab-content">
    
    <!-- ================= BOOKINGS TAB ================= -->
    <div class="tab-pane fade show active" id="tab-bookings" role="tabpanel">
      
      <!-- Sub-tabs for Booking Status -->
      <ul class="nav nav-pills mb-3" id="booking-tabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#book-pending" type="button">Pending (<?= count($groupedApps['pending']) ?>)</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" data-bs-toggle="pill" data-bs-target="#book-approved" type="button">Approved (<?= count($groupedApps['approved']) ?>)</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" data-bs-toggle="pill" data-bs-target="#book-completed" type="button">Completed (<?= count($groupedApps['completed']) ?>)</button>
        </li>
      </ul>

      <div class="tab-content">
        <?php foreach (['pending', 'approved', 'completed'] as $statusKey): ?>
          <div class="tab-pane fade <?= $statusKey === 'pending' ? 'show active' : '' ?>" id="book-<?= $statusKey ?>">
            
            <?php if (empty($groupedApps[$statusKey])): ?>
              <div class="text-center text-muted py-5"><i class="bi bi-inbox fs-1"></i><p class="mt-2">No <?= $statusKey ?> bookings.</p></div>
            <?php else: foreach ($groupedApps[$statusKey] as $app):
              $appId = (int)$app['id'];
              $isEditable = ($statusKey === 'pending'); // Lock approved/completed
              $sel = json_decode($app['selected_categories'] ?? '[]', true) ?: [];
              $cats = [];
              foreach ($sel as $s) {
                  $cid = (int)($s['id'] ?? 0); $qty = max(0, (int)($s['qty'] ?? 0));
                  if ($cid && $qty && isset($catMap[$cid])) $cats[] = ['cat' => $catMap[$cid], 'qty' => $qty];
              }
              if (!$cats) continue;

              // Build JSON for copy/print
              $appData = $app;
              $appData['categories'] = [];
              foreach($cats as $c) {
                  $cat = $c['cat'];
                  $appData['categories'][] = [
                      'name' => $cat['category_name'],
                      'id' => $cat['id'],
                      'qty' => $c['qty'],
                      'trip_type' => HIRED_TRIP_TYPES[$cat['trip_type']] ?? $cat['trip_type'],
                      'location' => HIRED_LOCATION_LABELS[$cat['location_type']] ?? $cat['location_type'],
                      'details' => $cat['vehicle_details'],
                      'params' => $cat['parameters'],
                      'assignments' => []
                  ];
                  // Attach assignments
                  for($i=1; $i<=$c['qty']; $i++) {
                      $key = $appId.'|'.$cat['id'].'|'.$i;
                      $row = $assign[$key] ?? null;
                      $appData['categories'][count($appData['categories'])-1]['assignments'][] = [
                          'idx' => $i,
                          'driver_name' => $row['driver_name'] ?? '',
                          'driver_mobile' => $row['driver_mobile'] ?? '',
                          'vehicle_number' => $row['vehicle_number'] ?? ''
                      ];
                  }
              }
              $jsonStr = htmlspecialchars(json_encode($appData), ENT_QUOTES, 'UTF-8');
            ?>
            <div class="app-card" data-app-id="<?= $appId ?>" data-json="<?= $jsonStr ?>">
              
              <!-- Card Header -->
              <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                <div>
                  <span class="badge bg-secondary me-2">#<?= $appId ?></span>
                  <strong><?= h($app['applicant_name']) ?></strong>
                  <?php if ($app['applicant_designation']): ?><span class="text-muted">— <?= h($app['applicant_designation']) ?></span><?php endif; ?>
                  <div class="text-muted small mt-1">
                    <i class="bi bi-calendar-event me-1"></i><?= h(hired_fmt_dt($app['datetime_from'])) ?> 
                    <?php if ($app['datetime_to']): ?>to <?= h(hired_fmt_dt($app['datetime_to'])) ?><?php endif; ?>
                    | <i class="bi bi-geo-alt me-1"></i><?= h($app['place_required']) ?> ➜ <?= h(HIRED_LOCATION_LABELS[$app['place_of_visit']] ?? $app['place_of_visit']) ?>
                  </div>
                </div>
                <div class="d-flex gap-2 align-items-center">
                  <span class="badge-status <?= $statusKey ?>"><?= ucfirst($statusKey) ?></span>
                  <button class="btn btn-sm btn-outline-secondary btn-copy" title="Copy Details"><i class="bi bi-clipboard"></i> Copy</button>
                  <button class="btn btn-sm btn-outline-secondary btn-pdf" title="Download PDF"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
                  <?php if ($isEditable): ?>
                    <button type="button" class="btn btn-sm btn-success btn-save-assignments"><i class="bi bi-save me-1"></i>Save Assignments</button>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Applicant Details -->
              <div class="row g-2 mb-3 bg-light p-2 rounded">
                <div class="col-md-4"><small class="text-muted">Contact:</small> <strong><?= h($app['applicant_mobile']) ?></strong> (<?= h($app['applicant_email']) ?>)</div>
                <div class="col-md-4"><small class="text-muted">Trip Type:</small> <strong><?= h(HIRED_TRIP_TYPES[$app['trip_type']] ?? $app['trip_type']) ?></strong></div>
                <div class="col-md-4"><small class="text-muted">Purpose:</small> <strong><?= h($app['purpose']) ?></strong></div>
              </div>

              <!-- Vehicle Categories Details & Assignment -->
              <?php foreach ($cats as $c): $cat = $c['cat']; $qty = $c['qty']; ?>
                <div class="cat-detail-box">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 fw-bold" style="color:var(--teal-dark);"><?= h($cat['category_name']) ?> (Qty: <?= $qty ?>)</h6>
                  </div>
                  <div class="cat-detail-grid mb-3">
                    <div><span class="lbl">Trip Type</span><span class="val"><?= h(HIRED_TRIP_TYPES[$cat['trip_type']] ?? $cat['trip_type']) ?></span></div>
                    <div><span class="lbl">Location</span><span class="val"><?= h(HIRED_LOCATION_LABELS[$cat['location_type']] ?? $cat['location_type']) ?></span></div>
                    <?php if ($cat['vehicle_details']): ?><div class="span-all"><span class="lbl">Vehicle Specs</span><span class="val"><?= nl2br(h($cat['vehicle_details'])) ?></span></div><?php endif; ?>
                    <?php if ($cat['parameters']): ?><div class="span-all"><span class="lbl">Parameters</span><span class="val"><?= nl2br(h($cat['parameters'])) ?></span></div><?php endif; ?>
                  </div>

                  <table class="table table-sm table-bordered bg-white mb-0">
                    <thead class="table-light">
                      <tr><th style="width:40px;">#</th><th>Driver</th><th style="width:120px;">Mobile</th><th>Vehicle No.</th><th style="width:120px;">Last Updated</th></tr>
                    </thead>
                    <tbody>
                      <?php for ($i = 1; $i <= $qty; $i++):
                          $key = $appId . '|' . $cat['id'] . '|' . $i;
                          $row = $assign[$key] ?? null;
                      ?>
                      <tr data-app="<?= $appId ?>" data-cat="<?= (int)$cat['id'] ?>" data-idx="<?= $i ?>">
                        <td class="text-center fw-bold"><?= $i ?></td>
                        <td>
                          <select class="form-select form-select-sm driver-select" onchange="syncDriverFields(this)" <?= $isEditable ? '' : 'disabled' ?>>
                            <option value="">-- Select Driver --</option>
                            <?php foreach ($drivers as $d): ?>
                              <option value="<?= h($d['id']) ?>" data-name="<?= h($d['driver_name']) ?>" data-mob="<?= h($d['driver_mob']) ?>" <?= (($row['driver_name'] ?? '') === $d['driver_name']) ? 'selected' : '' ?>><?= h($d['driver_name']) ?> (<?= h($d['driver_mob']) ?>)</option>
                            <?php endforeach; ?>
                          </select>
                          <input type="hidden" name="driver_name" value="<?= h($row['driver_name'] ?? '') ?>">
                        </td>
                        <td><input type="text" class="form-control form-control-sm" name="driver_mobile" value="<?= h($row['driver_mobile'] ?? '') ?>" readonly></td>
                        <td>
                          <select class="form-select form-select-sm vehicle-select" onchange="syncVehicleFields(this)" <?= $isEditable ? '' : 'disabled' ?>>
                            <option value="">-- Select Vehicle --</option>
                            <?php foreach ($vehicles as $v): ?>
                              <option value="<?= h($v['id']) ?>" data-no="<?= h($v['vehicle_no']) ?>" <?= (($row['vehicle_number'] ?? '') === $v['vehicle_no']) ? 'selected' : '' ?>><?= h($v['vehicle_no']) ?> <?= $v['vehicle_name'] ? '- '.h($v['vehicle_name']) : '' ?></option>
                            <?php endforeach; ?>
                          </select>
                          <input type="hidden" name="vehicle_number" value="<?= h($row['vehicle_number'] ?? '') ?>">
                        </td>
                        <td class="text-muted small text-center"><?= $row && !empty($row['updated_at']) ? h(date('d/m H:i', strtotime($row['updated_at']))) : '—' ?></td>
                      </tr>
                      <?php endfor; ?>
                    </tbody>
                  </table>
                </div>
              <?php endforeach; ?>
            </div>
            <?php endforeach; endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ================= DRIVERS TAB ================= -->
    <div class="tab-pane fade" id="tab-drivers" role="tabpanel">
      <div class="card-soft">
        <div class="card-header-teal">
          <span>My Drivers</span>
          <button class="btn btn-sm btn-light" onclick="openDriverModal()"><i class="bi bi-plus-lg me-1"></i>Add Driver</button>
        </div>
        <div class="table-responsive">
          <table class="table table-clean mb-0">
            <thead><tr><th>Name</th><th>Mobile</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
              <?php foreach ($drivers as $d): ?>
              <tr>
                <td><?= h($d['driver_name']) ?></td>
                <td><?= h($d['driver_mob']) ?></td>
                <td><span class="badge-status <?= strtolower($d['status']) ?>"><?= h($d['status']) ?></span></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-primary" onclick='openDriverModal(<?= json_encode($d) ?>)'><i class="bi bi-pencil"></i></button>
                  <button class="btn btn-sm btn-outline-danger" onclick="deleteDriver(<?= $d['id'] ?>)"><i class="bi bi-trash"></i></button>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if(!$drivers): ?><tr><td colspan="4" class="text-center text-muted py-4">No drivers added yet.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ================= VEHICLES TAB ================= -->
    <div class="tab-pane fade" id="tab-vehicles" role="tabpanel">
      <div class="card-soft">
        <div class="card-header-teal">
          <span>My Vehicles</span>
          <button class="btn btn-sm btn-light" onclick="openVehicleModal()"><i class="bi bi-plus-lg me-1"></i>Add Vehicle</button>
        </div>
        <div class="table-responsive">
          <table class="table table-clean mb-0">
            <thead><tr><th>Vehicle No</th><th>Model/Name</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
              <?php foreach ($vehicles as $v): ?>
              <tr>
                <td><strong><?= h($v['vehicle_no']) ?></strong></td>
                <td><?= h($v['vehicle_name']) ?></td>
                <td><span class="badge-status <?= strtolower($v['status']) ?>"><?= h($v['status']) ?></span></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-primary" onclick='openVehicleModal(<?= json_encode($v) ?>)'><i class="bi bi-pencil"></i></button>
                  <button class="btn btn-sm btn-outline-danger" onclick="deleteVehicle(<?= $v['id'] ?>)"><i class="bi bi-trash"></i></button>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if(!$vehicles): ?><tr><td colspan="4" class="text-center text-muted py-4">No vehicles added yet.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- Driver Modal -->
<div class="modal fade" id="driverModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="driverForm">
      <input type="hidden" name="id" id="dId">
      <div class="modal-header bg-teal text-white" style="background:var(--teal)">
        <h5 class="modal-title">Driver Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Driver Name</label><input type="text" class="form-control" name="driver_name" id="dName" required></div>
        <div class="mb-3"><label class="form-label">Mobile (10 digits)</label><input type="text" class="form-control" name="driver_mob" id="dMob" maxlength="10" required></div>
        <div class="mb-3"><label class="form-label">Status</label><select class="form-select" name="status" id="dStatus"><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-teal text-white" style="background:var(--teal)">Save Driver</button></div>
    </form>
  </div>
</div>

<!-- Vehicle Modal -->
<div class="modal fade" id="vehicleModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="vehicleForm">
      <input type="hidden" name="id" id="vId">
      <div class="modal-header bg-teal text-white" style="background:var(--teal)">
        <h5 class="modal-title">Vehicle Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Vehicle Number</label><input type="text" class="form-control" name="vehicle_no" id="vNo" required></div>
        <div class="mb-3"><label class="form-label">Model/Name</label><input type="text" class="form-control" name="vehicle_name" id="vName"></div>
        <div class="mb-3"><label class="form-label">Status</label><select class="form-select" name="status" id="vStatus"><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-teal text-white" style="background:var(--teal)">Save Vehicle</button></div>
    </form>
  </div>
</div>

<!-- Toast -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:9999">
  <div id="mainToast" class="toast align-items-center text-bg-success border-0" role="status">
    <div class="d-flex">
      <div class="toast-body"><i class="bi bi-check-circle-fill me-1"></i><span id="toastMsg">Saved</span></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const CSRF = '<?= hired_csrf_token() ?>';
const dModal = new bootstrap.Modal(document.getElementById('driverModal'));
const vModal = new bootstrap.Modal(document.getElementById('vehicleModal'));

function showToast(msg, tone) {
  const el = document.getElementById('mainToast');
  document.getElementById('toastMsg').textContent = msg;
  el.classList.remove('text-bg-success', 'text-bg-danger');
  el.classList.add(tone === 'error' ? 'text-bg-danger' : 'text-bg-success');
  bootstrap.Toast.getOrCreateInstance(el, { delay: 2500 }).show();
}

/* --- Driver Logic --- */
function openDriverModal(data) {
  document.getElementById('driverForm').reset();
  document.getElementById('dId').value = data ? data.id : '';
  if(data) {
    document.getElementById('dName').value = data.driver_name;
    document.getElementById('dMob').value = data.driver_mob;
    document.getElementById('dStatus').value = data.status;
  }
  dModal.show();
}
document.getElementById('driverForm').addEventListener('submit', async e => {
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append('ajax', 'save_driver'); fd.append('csrf', CSRF);
  const res = await fetch(location.href, { method: 'POST', body: fd }).then(r=>r.json());
  if(res.ok) { dModal.hide(); location.reload(); } else alert('Error saving driver');
});
async function deleteDriver(id) {
  if(!confirm('Delete this driver?')) return;
  const fd = new FormData(); fd.append('ajax', 'delete_driver'); fd.append('csrf', CSRF); fd.append('id', id);
  await fetch(location.href, { method: 'POST', body: fd });
  location.reload();
}

/* --- Vehicle Logic --- */
function openVehicleModal(data) {
  document.getElementById('vehicleForm').reset();
  document.getElementById('vId').value = data ? data.id : '';
  if(data) {
    document.getElementById('vNo').value = data.vehicle_no;
    document.getElementById('vName').value = data.vehicle_name;
    document.getElementById('vStatus').value = data.status;
  }
  vModal.show();
}
document.getElementById('vehicleForm').addEventListener('submit', async e => {
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append('ajax', 'save_vehicle'); fd.append('csrf', CSRF);
  const res = await fetch(location.href, { method: 'POST', body: fd }).then(r=>r.json());
  if(res.ok) { vModal.hide(); location.reload(); } else alert('Error saving vehicle');
});
async function deleteVehicle(id) {
  if(!confirm('Delete this vehicle?')) return;
  const fd = new FormData(); fd.append('ajax', 'delete_vehicle'); fd.append('csrf', CSRF); fd.append('id', id);
  await fetch(location.href, { method: 'POST', body: fd });
  location.reload();
}

/* --- Sync Booking Dropdowns --- */
function syncDriverFields(select) {
  const tr = select.closest('tr');
  const opt = select.options[select.selectedIndex];
  tr.querySelector('[name=driver_name]').value = opt.value ? opt.dataset.name : '';
  tr.querySelector('[name=driver_mobile]').value = opt.value ? opt.dataset.mob : '';
}
function syncVehicleFields(select) {
  const tr = select.closest('tr');
  const opt = select.options[select.selectedIndex];
  tr.querySelector('[name=vehicle_number]').value = opt.value ? opt.dataset.no : '';
}

/* --- Save Assignments Logic --- */
document.querySelectorAll('.btn-save-assignments').forEach(btn => {
  btn.addEventListener('click', async function() {
    const card = this.closest('.app-card');
    const appId = card.dataset.appId;
    const rows = [];
    card.querySelectorAll('tbody tr[data-app]').forEach(tr => {
      rows.push({
        cat_id: tr.dataset.cat,
        idx: tr.dataset.idx,
        driver_name: tr.querySelector('[name=driver_name]').value.trim(),
        driver_mobile: tr.querySelector('[name=driver_mobile]').value.trim(),
        vehicle_number: tr.querySelector('[name=vehicle_number]').value.trim()
      });
    });

    const old = this.innerHTML;
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

    const fd = new FormData();
    fd.append('ajax', 'save_all_assignments');
    fd.append('csrf', CSRF);
    fd.append('application_id', appId);
    fd.append('rows', JSON.stringify(rows));

    try {
      const res = await fetch(location.href, { method: 'POST', body: fd }).then(r=>r.json());
      if(res.ok) {
        showToast('All assignments saved successfully.');
        location.reload();
      } else {
        showToast(res.msg || 'Error saving', 'error');
      }
    } catch (e) {
      showToast('Network error', 'error');
    } finally {
      this.disabled = false;
      this.innerHTML = old;
    }
  });
});

/* ============================================================
   COPY & PDF LOGIC (WITH FALLBACK FOR HTTP)
   ============================================================ */
function fmtDt(s) {
  if(!s || s.startsWith('0000-00-00')) return '';
  const d = new Date(s.replace(' ','T'));
  return d.toLocaleDateString('en-GB', {day:'2-digit', month:'2-digit', year:'numeric'}) + ' ' + d.toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit'});
}
function money(n) { return '₹' + Number(n).toLocaleString('en-IN'); }

// Robust Copy Function
async function copyToClipboard(text) {
  // Try modern API first (works on HTTPS / localhost)
  if (navigator.clipboard && window.isSecureContext) {
    try {
      await navigator.clipboard.writeText(text);
      return true;
    } catch (err) {
      console.warn('Clipboard API failed, falling back...', err);
    }
  }
  
  // Fallback for HTTP or older browsers
  const textArea = document.createElement("textarea");
  textArea.value = text;
  textArea.style.position = "fixed";
  textArea.style.left = "-999999px";
  textArea.style.top = "-999999px";
  document.body.appendChild(textArea);
  textArea.focus();
  textArea.select();
  
  try {
    const successful = document.execCommand('copy');
    document.body.removeChild(textArea);
    return successful;
  } catch (err) {
    document.body.removeChild(textArea);
    console.error('Fallback copy failed', err);
    return false;
  }
}

document.querySelectorAll('.btn-copy').forEach(btn => {
  btn.addEventListener('click', async function() {
    const card = this.closest('.app-card');
    const d = JSON.parse(card.dataset.json);
    let txt = `🚐 *Booking #${d.id}* (${d.status.toUpperCase()})\n`;
    txt += `👤 ${d.applicant_name} (${d.applicant_mobile})\n`;
    txt += `🗓 ${fmtDt(d.datetime_from)} to ${fmtDt(d.datetime_to)}\n`;
    txt += `📍 ${d.place_required} ➜ ${d.place_of_visit}\n\n`;

    d.categories.forEach(c => {
      txt += `🚗 *${c.name}* (Qty: ${c.qty})\n`;
      txt += `   ↳ ${c.trip_type} | ${c.location}\n`;
      if(c.details) txt += `   ↳ ${c.details}\n`;
      c.assignments.forEach(a => {
        if(a.driver_name || a.vehicle_number) {
          txt += `   ${a.idx}. 👨‍✈️ ${a.driver_name} (${a.driver_mobile}) - 🔢 ${a.vehicle_number}\n`;
        } else {
          txt += `   ${a.idx}. _Not assigned_\n`;
        }
      });
      txt += `\n`;
    });

    const success = await copyToClipboard(txt);
    if (success) {
      showToast('Details copied to clipboard!');
    } else {
      showToast('Copy failed. Please check browser permissions.', 'error');
    }
  });
});

document.querySelectorAll('.btn-pdf').forEach(btn => {
  btn.addEventListener('click', function() {
    const card = this.closest('.app-card');
    const d = JSON.parse(card.dataset.json);
    
    let rows = '';
    d.categories.forEach(c => {
      rows += `<tr style="background:#f0f8f8;"><td colspan="4"><strong>${c.name}</strong> (Qty: ${c.qty}) - ${c.trip_type} | ${c.location}</td></tr>`;
      c.assignments.forEach(a => {
        rows += `<tr><td class="text-center">${a.idx}</td><td>${a.driver_name || '—'}</td><td>${a.driver_mobile || '—'}</td><td>${a.vehicle_number || '—'}</td></tr>`;
      });
    });

    const html = `
      <html><head><title>Booking #${d.id}</title>
      <style>body{font-family:sans-serif;font-size:12px;padding:20px;} table{width:100%;border-collapse:collapse;margin-top:10px;} th,td{border:1px solid #ccc;padding:6px;text-align:left;} th{background:#f4f4f4;}</style>
      </head><body>
      <h2>Booking #${d.id} - ${d.applicant_name}</h2>
      <p><strong>Status:</strong> ${d.status.toUpperCase()}<br>
      <strong>Contact:</strong> ${d.applicant_mobile} / ${d.applicant_email}<br>
      <strong>Travel:</strong> ${fmtDt(d.datetime_from)} to ${fmtDt(d.datetime_to)}<br>
      <strong>Route:</strong> ${d.place_required} ➜ ${d.place_of_visit}</p>
      
      <table>
        <thead><tr><th>#</th><th>Driver Name</th><th>Mobile</th><th>Vehicle No.</th></tr></thead>
        <tbody>${rows}</tbody>
      </table>
      <p style="margin-top:20px;font-size:10px;color:#666;">Generated on ${new Date().toLocaleString()}</p>
      </body></html>
    `;

    const w = window.open('', '_blank');
    w.document.write(html);
    w.document.close();
    setTimeout(() => w.print(), 500);
  });
});
</script>
</body>
</html>
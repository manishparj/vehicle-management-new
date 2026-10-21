<?php
session_start();
include("../config/db.php");

// ================= AUTH GUARD =================
if (!isset($_SESSION['hired_user_id']) || ($_SESSION['hired_role'] ?? '') !== 'hired_super_admin') {
    header("Location: hired_login.php");
    exit;
}

$adminName = $_SESSION['hired_user_name'] ?? 'Super Admin';

// ================= CONSTANTS =================
$TRIP_TYPES = [
    'one_way'    => 'One Way Trip',
    'round_trip' => 'Round Trip',
];

$LOCATION_OPTIONS = [
    'mrhu_bhanpur_kallan_jaipur'     => 'MRHRU, Bhanpur Kallan, Jaipur (Rajasthan)',
    'icmr_niht_jodhpur_or_rajasthan' => 'ICMR-NIHR, Jodhpur (Rajasthan) or anywhere in Rajasthan',
    'any_city_rajasthan'             => 'Any city of Rajasthan',
    'any_city_india_excl_rajasthan'  => 'Any city of India excluding Rajasthan',
    'other'                          => 'Other',
];

// ================= HELPERS =================
function redirectWith($type, $msg) {
    $_SESSION['flash_type'] = $type;
    $_SESSION['flash_msg']  = $msg;
    header("Location: hired_manage_vehicle_categories.php");
    exit;
}
function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// ================= HANDLE POST ACTIONS =================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---------- CREATE ----------
    if ($action === 'create') {
        $catName      = trim($_POST['category_name'] ?? '');
        $tripType     = trim($_POST['trip_type'] ?? '');
        $locationType = trim($_POST['location_type'] ?? '');
        $locationOther= trim($_POST['location_other'] ?? '');
        $vDetails     = trim($_POST['vehicle_details'] ?? '');
        $params       = trim($_POST['parameters'] ?? '');
        $price        = (float)($_POST['price_per_vehicle'] ?? 0);
        $isActive     = isset($_POST['is_active']) ? 1 : 0;

        $errors = [];
        if ($catName === '')                                    $errors[] = 'Category name is required.';
        if (!array_key_exists($tripType, $TRIP_TYPES))          $errors[] = 'Valid trip type is required.';
        if (!array_key_exists($locationType, $LOCATION_OPTIONS))$errors[] = 'Valid location type is required.';
        if ($locationType === 'other' && $locationOther === '') $errors[] = 'Please specify the "Other" location.';
        if ($price < 0)                                         $errors[] = 'Price cannot be negative.';

        if (!empty($errors)) redirectWith('danger', implode(' ', $errors));

        $stmt = $conn->prepare("INSERT INTO hired_vehicles
            (category_name, trip_type, location_type, location_other, vehicle_details, parameters, price_per_vehicle, is_active, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "ssssssdii",
            $catName, $tripType, $locationType, $locationOther,
            $vDetails, $params, $price, $isActive, $_SESSION['hired_user_id']
        );
        if ($stmt->execute()) {
            redirectWith('success', "Category <strong>" . h($catName) . "</strong> created successfully.");
        } else {
            error_log("create category failed: " . $stmt->error);
            redirectWith('danger', 'Could not create category.');
        }
        $stmt->close();
    }

    // ---------- UPDATE ----------
    if ($action === 'update') {
        $id           = (int)($_POST['id'] ?? 0);
        $catName      = trim($_POST['category_name'] ?? '');
        $tripType     = trim($_POST['trip_type'] ?? '');
        $locationType = trim($_POST['location_type'] ?? '');
        $locationOther= trim($_POST['location_other'] ?? '');
        $vDetails     = trim($_POST['vehicle_details'] ?? '');
        $params       = trim($_POST['parameters'] ?? '');
        $price        = (float)($_POST['price_per_vehicle'] ?? 0);
        $isActive     = isset($_POST['is_active']) ? 1 : 0;

        $errors = [];
        if ($id <= 0)                                           $errors[] = 'Invalid ID.';
        if ($catName === '')                                    $errors[] = 'Category name is required.';
        if (!array_key_exists($tripType, $TRIP_TYPES))          $errors[] = 'Valid trip type is required.';
        if (!array_key_exists($locationType, $LOCATION_OPTIONS))$errors[] = 'Valid location type is required.';
        if ($locationType === 'other' && $locationOther === '') $errors[] = 'Please specify the "Other" location.';
        if ($price < 0)                                         $errors[] = 'Price cannot be negative.';

        if (!empty($errors)) redirectWith('danger', implode(' ', $errors));

        $stmt = $conn->prepare("UPDATE hired_vehicles
            SET category_name=?, trip_type=?, location_type=?, location_other=?,
                vehicle_details=?, parameters=?, price_per_vehicle=?, is_active=?
            WHERE id=?");
        $stmt->bind_param(
            "ssssssdii",
            $catName, $tripType, $locationType, $locationOther,
            $vDetails, $params, $price, $isActive, $id
        );
        if ($stmt->execute()) {
            redirectWith('success', "Category <strong>" . h($catName) . "</strong> updated successfully.");
        } else {
            error_log("update category failed: " . $stmt->error);
            redirectWith('danger', 'Could not update category.');
        }
        $stmt->close();
    }

    // ---------- TOGGLE ACTIVE ----------
    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) redirectWith('danger', 'Invalid ID.');

        $stmt = $conn->prepare("UPDATE hired_vehicles SET is_active = 1 - is_active WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) redirectWith('success', 'Category status updated.');
        else redirectWith('danger', 'Could not toggle category.');
        $stmt->close();
    }

    // ---------- DUPLICATE ----------
    if ($action === 'duplicate') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) redirectWith('danger', 'Invalid ID.');

        $src = $conn->prepare("SELECT * FROM hired_vehicles WHERE id = ?");
        $src->bind_param("i", $id);
        $src->execute();
        $row = $src->get_result()->fetch_assoc();
        $src->close();

        if (!$row) redirectWith('danger', 'Category not found.');

        $newName = $row['category_name'] . ' (Copy)';
        $stmt = $conn->prepare("INSERT INTO hired_vehicles
            (category_name, trip_type, location_type, location_other, vehicle_details, parameters, price_per_vehicle, is_active, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "ssssssdii",
            $newName,
            $row['trip_type'],
            $row['location_type'],
            $row['location_other'],
            $row['vehicle_details'],
            $row['parameters'],
            $row['price_per_vehicle'],
            $row['is_active'],
            $_SESSION['hired_user_id']
        );
        if ($stmt->execute()) redirectWith('success', 'Category duplicated successfully.');
        else redirectWith('danger', 'Could not duplicate category.');
        $stmt->close();
    }

    // ---------- DELETE ----------
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) redirectWith('danger', 'Invalid ID.');

        // Safety: check if used in any pending/approved/completed applications
        $chk = $conn->prepare("SELECT COUNT(*) AS c FROM hired_applications WHERE selected_categories LIKE ?");
        $like = '%"id":' . $id . '%';
        $chk->bind_param("s", $like);
        $chk->execute();
        $usage = (int)($chk->get_result()->fetch_assoc()['c'] ?? 0);
        $chk->close();

        if ($usage > 0) {
            redirectWith('danger', "Cannot delete — this category is used in {$usage} application(s). Deactivate it instead.");
        }

        $stmt = $conn->prepare("DELETE FROM hired_vehicles WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) redirectWith('success', 'Category deleted successfully.');
        else redirectWith('danger', 'Could not delete category.');
        $stmt->close();
    }

    // ---------- BULK DELETE ----------
    if ($action === 'bulk_delete') {
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) $ids = [];
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_values(array_filter($ids, fn($v) => $v > 0));

        if (empty($ids)) redirectWith('danger', 'No categories selected.');

        // Check usage for each
        $inUse = [];
        foreach ($ids as $cid) {
            $chk = $conn->prepare("SELECT COUNT(*) AS c FROM hired_applications WHERE selected_categories LIKE ?");
            $like = '%"id":' . $cid . '%';
            $chk->bind_param("s", $like);
            $chk->execute();
            $cnt = (int)($chk->get_result()->fetch_assoc()['c'] ?? 0);
            $chk->close();
            if ($cnt > 0) $inUse[] = $cid;
        }

        if (!empty($inUse)) {
            redirectWith('danger', "Some categories are in use and cannot be deleted: #" . implode(', #', $inUse) . ". Deactivate them instead.");
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $stmt = $conn->prepare("DELETE FROM hired_vehicles WHERE id IN ($placeholders)");
        $stmt->bind_param($types, ...$ids);
        if ($stmt->execute()) {
            redirectWith('success', count($ids) . " categor(y/ies) deleted successfully.");
        } else {
            redirectWith('danger', 'Could not delete categories.');
        }
        $stmt->close();
    }
}

// ================= FLASH =================
$flashType = $_SESSION['flash_type'] ?? '';
$flashMsg  = $_SESSION['flash_msg']  ?? '';
unset($_SESSION['flash_type'], $_SESSION['flash_msg']);

// ================= FETCH LIST =================
$search       = trim($_GET['q'] ?? '');
$tripFilter   = trim($_GET['trip'] ?? '');
$locFilter    = trim($_GET['loc'] ?? '');
$activeFilter = trim($_GET['active'] ?? '');

$sql = "SELECT hv.*,
               (SELECT COUNT(*) FROM hired_applications ha WHERE ha.selected_categories LIKE CONCAT('%\"id\":', hv.id, '%')) AS usage_count
        FROM hired_vehicles hv
        WHERE 1=1";
$params = [];
$types  = '';

if ($search !== '') {
    $sql .= " AND (hv.category_name LIKE ? OR hv.vehicle_details LIKE ? OR hv.parameters LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'sss';
}
if ($tripFilter !== '' && array_key_exists($tripFilter, $TRIP_TYPES)) {
    $sql .= " AND hv.trip_type = ?";
    $params[] = $tripFilter;
    $types .= 's';
}
if ($locFilter !== '' && array_key_exists($locFilter, $LOCATION_OPTIONS)) {
    $sql .= " AND hv.location_type = ?";
    $params[] = $locFilter;
    $types .= 's';
}
if ($activeFilter === '1' || $activeFilter === '0') {
    $sql .= " AND hv.is_active = ?";
    $params[] = (int)$activeFilter;
    $types .= 'i';
}
$sql .= " ORDER BY hv.is_active DESC, hv.trip_type ASC, hv.category_name ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ================= STATS =================
$stats = ['total' => 0, 'active' => 0, 'inactive' => 0, 'one_way' => 0, 'round_trip' => 0];
$sRes = $conn->query("
    SELECT
      COUNT(*) AS total,
      SUM(is_active = 1) AS active,
      SUM(is_active = 0) AS inactive,
      SUM(trip_type = 'one_way') AS one_way,
      SUM(trip_type = 'round_trip') AS round_trip
    FROM hired_vehicles
");
if ($sRes) {
    $row = $sRes->fetch_assoc();
    $stats = [
        'total'      => (int)($row['total'] ?? 0),
        'active'     => (int)($row['active'] ?? 0),
        'inactive'   => (int)($row['inactive'] ?? 0),
        'one_way'    => (int)($row['one_way'] ?? 0),
        'round_trip' => (int)($row['round_trip'] ?? 0),
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Vehicle Categories — Hired Vehicle</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
<style>
:root{--navy:#0F2A43;--teal:#0E7C86;--teal-dark:#0A5F67;--paper:#F5F8F9;--card:#fff;--line:#E1E8EC;--tm:#5B6B7A;--amber:#C9762A}
body{font-family:'Inter',sans-serif;background:var(--paper);color:var(--navy)}
.dash-header{background:linear-gradient(155deg,var(--navy) 0%,var(--teal-dark) 100%);color:#fff;padding:26px 0}
.dash-header h1{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:1.4rem;margin:0}
.crumb{color:rgba(255,255,255,.65);font-size:.82rem;margin-top:4px}
.crumb a{color:#fff;text-decoration:none;opacity:.85}
.crumb a:hover{opacity:1;text-decoration:underline}
.card-soft{background:var(--card);border:1px solid var(--line);border-radius:14px;box-shadow:0 1px 2px rgba(15,42,67,.06)}
.stat-mini{padding:14px 18px;border-radius:12px;border:1px solid var(--line);background:#fff;display:flex;align-items:center;gap:12px}
.stat-mini .icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.05rem}
.stat-mini .num{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:1.2rem;line-height:1}
.stat-mini .lbl{font-size:.76rem;color:var(--tm);font-weight:600}
.icon-navy{background:#E5EEF4;color:var(--navy)}
.icon-teal{background:#E5F3F3;color:var(--teal-dark)}
.icon-amber{background:#FBF0E4;color:#7A4A17}
.icon-muted{background:#F1F5F8;color:#5B6B7A}
.form-control,.form-select{border-color:var(--line);padding:.55rem .8rem;font-size:.92rem}
.form-control:focus,.form-select:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(14,124,134,.14)}
.form-label{font-weight:600;font-size:.85rem;color:var(--navy);margin-bottom:5px}
.btn-teal{background:var(--teal);color:#fff;font-weight:600;border:none}
.btn-teal:hover{background:var(--teal-dark);color:#fff}
.table-clean{font-size:.88rem}
.table-clean thead th{background:#F8FAFB;color:var(--tm);font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;font-weight:700;border-bottom:1px solid var(--line);padding:12px 14px}
.table-clean tbody td{padding:12px 14px;vertical-align:middle;border-color:var(--line)}
.badge-active{background:#EAF7F0;color:#1F8A5F;font-size:.72rem;font-weight:700;padding:3px 10px;border-radius:999px}
.badge-inactive{background:#FBEAE9;color:#C4433B;font-size:.72rem;font-weight:700;padding:3px 10px;border-radius:999px}
.badge-trip{font-size:.72rem;font-weight:700;padding:3px 10px;border-radius:6px;white-space:nowrap}
.badge-trip.one_way{background:#E5EEF4;color:#0F2A43}
.badge-trip.round_trip{background:#FBF0E4;color:#7A4A17}
.btn-icon{width:32px;height:32px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:8px}
.mono{font-family:'SFMono-Regular',Consolas,Menlo,monospace;font-size:.78rem;background:#F1F5F8;color:#4A5A68;padding:2px 8px;border-radius:5px}
.empty-state{text-align:center;padding:60px 20px;color:var(--tm)}
.empty-state i{font-size:2.8rem;opacity:.4}
.modal-header-teal{background:linear-gradient(135deg,var(--teal),var(--teal-dark));color:#fff}
.modal-header-teal .btn-close{filter:invert(1) brightness(2)}
.modal-content{border-radius:14px;overflow:hidden;border:none}
.alert-flash{border-radius:12px;font-size:.9rem;display:flex;align-items:center;gap:10px;margin-bottom:20px}
.usage-pill{font-size:.72rem;font-weight:700;padding:3px 8px;border-radius:6px;background:#F1F5F8;color:#4A5A68;display:inline-flex;align-items:center;gap:4px}
.usage-pill.used{background:#E5F3F3;color:#0A5F67}
#bulkBar{position:sticky;bottom:16px;z-index:100;background:#0F2A43;color:#fff;border-radius:12px;padding:14px 18px;box-shadow:0 12px 32px rgba(15,42,67,.35);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-top:16px}
@media(max-width:900px){
  .table-clean thead{display:none}
  .table-clean tbody tr{display:block;border:1px solid var(--line);border-radius:12px;margin-bottom:10px;background:#fff;padding:10px}
  .table-clean tbody td{display:flex;justify-content:space-between;border:none;padding:6px 4px}
  .table-clean tbody td::before{content:attr(data-label);font-weight:700;color:var(--tm);font-size:.72rem;text-transform:uppercase;margin-right:10px;flex-shrink:0}
}
</style>
</head>
<body>

<div class="dash-header">
  <div class="container">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
      <div>
        <h1><i class="bi bi-truck-front me-2"></i>Manage Vehicle Categories</h1>
        <div class="crumb">
          <a href="hired_super_admin_dashboard.php"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
          <span class="mx-2">/</span>Vehicle Categories
        </div>
      </div>
      <div class="d-flex align-items-center gap-3 flex-wrap">
        <span class="text-white-50" style="font-size:.85rem"><i class="bi bi-person-circle me-1"></i><?= h($adminName) ?></span>
        <a href="hired_logout.php" class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
      </div>
    </div>
  </div>
</div>

<div class="container py-4">

  <?php if ($flashMsg): ?>
  <div class="alert alert-<?= h($flashType) ?> alert-flash">
    <i class="bi bi-<?= $flashType === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?>"></i>
    <div><?= $flashMsg ?></div>
  </div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
      <div class="stat-mini">
        <div class="icon icon-navy"><i class="bi bi-truck-front"></i></div>
        <div>
          <div class="num"><?= $stats['total'] ?></div>
          <div class="lbl">Total Categories</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-mini">
        <div class="icon icon-teal"><i class="bi bi-check-circle"></i></div>
        <div>
          <div class="num"><?= $stats['active'] ?></div>
          <div class="lbl">Active</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-mini">
        <div class="icon icon-muted"><i class="bi bi-x-circle"></i></div>
        <div>
          <div class="num"><?= $stats['inactive'] ?></div>
          <div class="lbl">Inactive</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3 d-flex justify-content-md-end align-items-center">
      <button class="btn btn-teal w-100 w-md-auto" data-bs-toggle="modal" data-bs-target="#createModal">
        <i class="bi bi-plus-circle me-1"></i>Add New Category
      </button>
    </div>
  </div>

  <!-- Filters -->
  <div class="card-soft p-3 mb-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label">Search</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" name="q" class="form-control" placeholder="Category name, details, params..." value="<?= h($search) ?>">
        </div>
      </div>
      <div class="col-md-2">
        <label class="form-label">Trip Type</label>
        <select name="trip" class="form-select">
          <option value="">All</option>
          <?php foreach ($TRIP_TYPES as $k => $v): ?>
            <option value="<?= h($k) ?>" <?= $tripFilter === $k ? 'selected' : '' ?>><?= h($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Location</label>
        <select name="loc" class="form-select">
          <option value="">All</option>
          <?php foreach ($LOCATION_OPTIONS as $k => $v): ?>
            <option value="<?= h($k) ?>" <?= $locFilter === $k ? 'selected' : '' ?>><?= h($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-1">
        <label class="form-label">Status</label>
        <select name="active" class="form-select">
          <option value="">All</option>
          <option value="1" <?= $activeFilter === '1' ? 'selected' : '' ?>>Active</option>
          <option value="0" <?= $activeFilter === '0' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-teal flex-fill"><i class="bi bi-funnel me-1"></i>Filter</button>
        <a href="hired_manage_vehicle_categories.php" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
      </div>
    </form>
  </div>

  <!-- Table -->
  <div class="card-soft overflow-hidden">
    <?php if (empty($list)): ?>
      <div class="empty-state">
        <i class="bi bi-inbox"></i>
        <p class="mt-3 mb-1">No vehicle categories found.</p>
        <small>Try clearing filters or add a new category.</small>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-clean mb-0">
          <thead>
            <tr>
              <th style="width:36px"><input type="checkbox" id="bulkSelectAll" title="Select all"></th>
              <th>Category</th>
              <th>Trip Type</th>
              <th>Location</th>
              <th>Price (₹)</th>
              <th>Status</th>
              <th>Usage</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($list as $vc):
            $tripKey = $vc['trip_type'] ?? 'round_trip';
            $tripLbl = $TRIP_TYPES[$tripKey] ?? ucfirst(str_replace('_', ' ', $tripKey));
            $locKey  = $vc['location_type'] ?? '';
            $locLbl  = $LOCATION_OPTIONS[$locKey] ?? '—';
            if ($locKey === 'other' && !empty($vc['location_other'])) $locLbl = $vc['location_other'];
          ?>
            <tr>
              <td data-label="Select" style="width:36px">
                <input type="checkbox" class="bulk-select-row" value="<?= (int)$vc['id'] ?>">
              </td>
              <td data-label="Category">
                <div style="font-weight:700"><?= h($vc['category_name']) ?></div>
                <div style="font-size:.72rem;color:var(--tm)">ID #<?= (int)$vc['id'] ?></div>
                <?php if (!empty($vc['vehicle_details'])): ?>
                  <div style="font-size:.75rem;color:var(--tm);margin-top:2px" title="<?= h($vc['vehicle_details']) ?>">
                    <?= h(mb_strimwidth($vc['vehicle_details'], 0, 60, '…')) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td data-label="Trip Type">
                <span class="badge-trip <?= h($tripKey) ?>">
                  <i class="bi bi-<?= $tripKey === 'one_way' ? 'arrow-right' : 'arrow-left-right' ?> me-1"></i><?= h($tripLbl) ?>
                </span>
              </td>
              <td data-label="Location">
                <span style="font-size:.82rem"><?= h($locLbl) ?></span>
              </td>
              <td data-label="Price">
                <span style="font-weight:700;color:var(--teal-dark)">₹<?= number_format((float)$vc['price_per_vehicle'], 2) ?></span>
              </td>
              <td data-label="Status">
                <?php if ((int)$vc['is_active'] === 1): ?>
                  <span class="badge-active"><i class="bi bi-check-circle-fill me-1"></i>Active</span>
                <?php else: ?>
                  <span class="badge-inactive"><i class="bi bi-x-circle-fill me-1"></i>Inactive</span>
                <?php endif; ?>
              </td>
              <td data-label="Usage">
                <?php if ((int)$vc['usage_count'] > 0): ?>
                  <span class="usage-pill used"><i class="bi bi-link-45deg"></i><?= (int)$vc['usage_count'] ?> app(s)</span>
                <?php else: ?>
                  <span class="usage-pill">Not used</span>
                <?php endif; ?>
              </td>
              <td data-label="Actions" class="text-end">
                <button class="btn btn-sm btn-outline-primary btn-icon me-1 edit-btn"
                  title="Edit"
                  data-id="<?= (int)$vc['id'] ?>"
                  data-name="<?= h($vc['category_name']) ?>"
                  data-trip="<?= h($tripKey) ?>"
                  data-loc="<?= h($locKey) ?>"
                  data-other="<?= h($vc['location_other'] ?? '') ?>"
                  data-details="<?= h($vc['vehicle_details'] ?? '') ?>"
                  data-params="<?= h($vc['parameters'] ?? '') ?>"
                  data-price="<?= h($vc['price_per_vehicle']) ?>"
                  data-active="<?= (int)$vc['is_active'] ?>">
                  <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm btn-outline-success btn-icon me-1 dup-btn"
                  title="Duplicate"
                  data-id="<?= (int)$vc['id'] ?>"
                  data-name="<?= h($vc['category_name']) ?>">
                  <i class="bi bi-files"></i>
                </button>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int)$vc['id'] ?>">
                  <button class="btn btn-sm btn-outline-secondary btn-icon me-1" title="Toggle active">
                    <i class="bi bi-toggle-<?= (int)$vc['is_active'] === 1 ? 'on' : 'off' ?>"></i>
                  </button>
                </form>
                <button class="btn btn-sm btn-outline-danger btn-icon del-btn"
                  title="Delete"
                  data-id="<?= (int)$vc['id'] ?>"
                  data-name="<?= h($vc['category_name']) ?>"
                  data-usage="<?= (int)$vc['usage_count'] ?>">
                  <i class="bi bi-trash"></i>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Bulk bar -->
  <div id="bulkBar" class="d-none">
    <div><strong id="bulkCount">0</strong> categor(y/ies) selected</div>
    <div class="d-flex gap-2 flex-wrap">
      <button type="button" class="btn btn-sm btn-outline-light" id="bulkClearBtn">Clear</button>
      <button type="button" class="btn btn-sm btn-danger" id="bulkDeleteBtn">
        <i class="bi bi-trash me-1"></i>Delete Selected
      </button>
    </div>
  </div>

</div>

<!-- ================= CREATE MODAL ================= -->
<div class="modal fade" id="createModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header modal-header-teal">
        <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Vehicle Category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="createForm" autocomplete="off">
        <input type="hidden" name="action" value="create">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Category Name <span class="text-danger">*</span></label>
              <input type="text" name="category_name" class="form-control" required maxlength="150" placeholder="e.g. Sedan, SUV, Tempo Traveller">
            </div>
            <div class="col-md-6">
              <label class="form-label">Trip Type <span class="text-danger">*</span></label>
              <select name="trip_type" class="form-select" required>
                <option value="">Select trip type</option>
                <?php foreach ($TRIP_TYPES as $k => $v): ?>
                  <option value="<?= h($k) ?>"><?= h($v) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label">Location for Service (Pick &amp; Drop) <span class="text-danger">*</span></label>
              <select name="location_type" id="createLocType" class="form-select" required>
                <option value="">Select location</option>
                <?php foreach ($LOCATION_OPTIONS as $k => $v): ?>
                  <option value="<?= h($k) ?>"><?= h($v) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12" id="createOtherWrap" style="display:none">
              <label class="form-label">Specify "Other" Location <span class="text-danger">*</span></label>
              <input type="text" name="location_other" id="createOther" class="form-control" maxlength="255" placeholder="Enter location details">
            </div>

            <div class="col-12">
              <label class="form-label">Details of Vehicles</label>
              <textarea name="vehicle_details" class="form-control" rows="2" placeholder="Mentioned in bid..."></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Parameters</label>
              <textarea name="parameters" class="form-control" rows="2" placeholder="Mentioned in bid..."></textarea>
            </div>
            <div class="col-md-8">
              <label class="form-label">Price per Vehicle (Inclusive all GST) <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-currency-rupee"></i></span>
                <input type="number" name="price_per_vehicle" class="form-control" step="0.01" min="0" required placeholder="0.00">
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Status</label>
              <div class="form-check form-switch mt-2">
                <input class="form-check-input" type="checkbox" name="is_active" id="createActive" value="1" checked>
                <label class="form-check-label fw-semibold" for="createActive">Active</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-teal"><i class="bi bi-check-lg me-1"></i>Create</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ================= EDIT MODAL ================= -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header modal-header-teal">
        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Vehicle Category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="editForm" autocomplete="off">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="editId">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Category Name <span class="text-danger">*</span></label>
              <input type="text" name="category_name" id="editName" class="form-control" required maxlength="150">
            </div>
            <div class="col-md-6">
              <label class="form-label">Trip Type <span class="text-danger">*</span></label>
              <select name="trip_type" id="editTrip" class="form-select" required>
                <?php foreach ($TRIP_TYPES as $k => $v): ?>
                  <option value="<?= h($k) ?>"><?= h($v) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label">Location for Service (Pick &amp; Drop) <span class="text-danger">*</span></label>
              <select name="location_type" id="editLocType" class="form-select" required>
                <?php foreach ($LOCATION_OPTIONS as $k => $v): ?>
                  <option value="<?= h($k) ?>"><?= h($v) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12" id="editOtherWrap" style="display:none">
              <label class="form-label">Specify "Other" Location <span class="text-danger">*</span></label>
              <input type="text" name="location_other" id="editOther" class="form-control" maxlength="255">
            </div>

            <div class="col-12">
              <label class="form-label">Details of Vehicles</label>
              <textarea name="vehicle_details" id="editDetails" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Parameters</label>
              <textarea name="parameters" id="editParams" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-md-8">
              <label class="form-label">Price per Vehicle (Inclusive all GST) <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-currency-rupee"></i></span>
                <input type="number" name="price_per_vehicle" id="editPrice" class="form-control" step="0.01" min="0" required>
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Status</label>
              <div class="form-check form-switch mt-2">
                <input class="form-check-input" type="checkbox" name="is_active" id="editActive" value="1">
                <label class="form-check-label fw-semibold" for="editActive">Active</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-teal"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ================= DUPLICATE MODAL ================= -->
<div class="modal fade" id="dupModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header modal-header-teal">
        <h5 class="modal-title"><i class="bi bi-files me-2"></i>Duplicate Category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="duplicate">
        <input type="hidden" name="id" id="dupId">
        <div class="modal-body">
          <p>Duplicate "<strong id="dupName"></strong>"?</p>
          <div class="alert alert-info mb-0" style="font-size:.85rem">
            <i class="bi bi-info-circle me-1"></i>
            A new category will be created with " (Copy)" appended to the name. You can edit it afterwards.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-teal"><i class="bi bi-files me-1"></i>Duplicate</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ================= DELETE MODAL ================= -->
<div class="modal fade" id="delModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background:#C4433B;color:#fff">
        <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>Delete Category</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delId">
        <div class="modal-body">
          <p>Delete "<strong id="delName"></strong>"?</p>
          <div class="alert alert-warning mb-0" style="font-size:.85rem" id="delWarning">
            <i class="bi bi-info-circle me-1"></i>
            This action cannot be undone.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger" id="delConfirmBtn"><i class="bi bi-trash me-1"></i>Delete</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ================= BULK DELETE FORM ================= -->
<form method="POST" id="bulkDeleteForm" style="display:none">
  <input type="hidden" name="action" value="bulk_delete">
  <div id="bulkDeleteInputs"></div>
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
'use strict';
document.addEventListener('DOMContentLoaded', () => {

  // ---------- Helper: toggle "other" input visibility ----------
  function syncOther(selectId, wrapId, inputId) {
    const sel  = document.getElementById(selectId);
    const wrap = document.getElementById(wrapId);
    const inp  = document.getElementById(inputId);
    const toggle = () => {
      const isOther = sel.value === 'other';
      wrap.style.display = isOther ? 'block' : 'none';
      inp.required = isOther;
      if (!isOther) inp.value = '';
    };
    sel.addEventListener('change', toggle);
    toggle();
  }
  syncOther('createLocType', 'createOtherWrap', 'createOther');
  syncOther('editLocType',   'editOtherWrap',   'editOther');

  // ---------- Reset create form on close ----------
  const createModal = document.getElementById('createModal');
  createModal.addEventListener('hidden.bs.modal', () => {
    document.getElementById('createForm').reset();
    document.getElementById('createOtherWrap').style.display = 'none';
    document.getElementById('createOther').required = false;
  });

  // ---------- Edit modal ----------
  const editModal = new bootstrap.Modal(document.getElementById('editModal'));
  document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('editId').value   = btn.dataset.id;
      document.getElementById('editName').value = btn.dataset.name;
      document.getElementById('editTrip').value = btn.dataset.trip;

      const locSel = document.getElementById('editLocType');
      locSel.value = btn.dataset.loc || '';

      const otherWrap = document.getElementById('editOtherWrap');
      const otherInp  = document.getElementById('editOther');
      if (btn.dataset.loc === 'other') {
        otherWrap.style.display = 'block';
        otherInp.value = btn.dataset.other || '';
        otherInp.required = true;
      } else {
        otherWrap.style.display = 'none';
        otherInp.value = '';
        otherInp.required = false;
      }

      document.getElementById('editDetails').value = btn.dataset.details || '';
      document.getElementById('editParams').value  = btn.dataset.params || '';
      document.getElementById('editPrice').value   = btn.dataset.price || '0';
      document.getElementById('editActive').checked = btn.dataset.active === '1';

      editModal.show();
    });
  });

  // ---------- Duplicate modal ----------
  const dupModal = new bootstrap.Modal(document.getElementById('dupModal'));
  document.querySelectorAll('.dup-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('dupId').value = btn.dataset.id;
      document.getElementById('dupName').textContent = btn.dataset.name;
      dupModal.show();
    });
  });

  // ---------- Delete modal ----------
  const delModal = new bootstrap.Modal(document.getElementById('delModal'));
  document.querySelectorAll('.del-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const usage = parseInt(btn.dataset.usage || '0', 10);
      document.getElementById('delId').value = btn.dataset.id;
      document.getElementById('delName').textContent = btn.dataset.name;

      const warn = document.getElementById('delWarning');
      const confirmBtn = document.getElementById('delConfirmBtn');
      if (usage > 0) {
        warn.className = 'alert alert-danger mb-0';
        warn.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i>
          This category is used in <strong>${usage} application(s)</strong>.
          You must delete or reassign those applications first, or deactivate this category instead.`;
        confirmBtn.disabled = true;
      } else {
        warn.className = 'alert alert-warning mb-0';
        warn.innerHTML = '<i class="bi bi-info-circle me-1"></i>This action cannot be undone.';
        confirmBtn.disabled = false;
      }
      delModal.show();
    });
  });

  // ---------- Bulk selection ----------
  const bulkBar       = document.getElementById('bulkBar');
  const bulkCount     = document.getElementById('bulkCount');
  const bulkSelectAll = document.getElementById('bulkSelectAll');
  const rowChecks     = () => Array.from(document.querySelectorAll('.bulk-select-row'));

  function updateBulkBar() {
    const n = rowChecks().filter(cb => cb.checked).length;
    bulkCount.textContent = n;
    bulkBar.classList.toggle('d-none', n === 0);
    if (bulkSelectAll) {
      const all = rowChecks();
      bulkSelectAll.checked = all.length > 0 && n === all.length;
      bulkSelectAll.indeterminate = n > 0 && n < all.length;
    }
  }
  document.querySelectorAll('.bulk-select-row').forEach(cb => cb.addEventListener('change', updateBulkBar));
  if (bulkSelectAll) {
    bulkSelectAll.addEventListener('change', () => {
      rowChecks().forEach(cb => cb.checked = bulkSelectAll.checked);
      updateBulkBar();
    });
  }
  document.getElementById('bulkClearBtn').addEventListener('click', () => {
    rowChecks().forEach(cb => cb.checked = false);
    if (bulkSelectAll) bulkSelectAll.checked = false;
    updateBulkBar();
  });
  document.getElementById('bulkDeleteBtn').addEventListener('click', () => {
    const ids = rowChecks().filter(cb => cb.checked).map(cb => cb.value);
    if (ids.length === 0) return alert('Select at least one category.');
    if (!confirm(`Delete ${ids.length} selected categor(y/ies)? This cannot be undone.`)) return;

    const form   = document.getElementById('bulkDeleteForm');
    const inputs = document.getElementById('bulkDeleteInputs');
    inputs.innerHTML = '';
    ids.forEach(id => {
      const i = document.createElement('input');
      i.type = 'hidden';
      i.name = 'ids[]';
      i.value = id;
      inputs.appendChild(i);
    });
    form.submit();
  });

});
</script>
</body>
</html>
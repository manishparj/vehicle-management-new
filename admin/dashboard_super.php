<?php
session_start();

/* ------------------------------------------------------------------
   SECURITY HEADERS + AUTH
------------------------------------------------------------------ */
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: strict-origin-when-cross-origin");

include("../config/db.php");

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'super_admin') {
    header("location:login.php");
    exit();
}

/* ------------------------------------------------------------------
   HELPERS
------------------------------------------------------------------ */
const STATUS_META = [            // status => [badge class, icon, accent colour]
    'Pending'         => ['sb-pending',   'hourglass-split',         '#f59e0b'],
    'Recommended'     => ['sb-recom',     'hand-thumbs-up-fill',     '#3b82f6'],
    'Not Recommended' => ['sb-notrecom',  'hand-thumbs-down-fill',   '#ef4444'],
    'Approved'        => ['sb-approved',  'check-circle-fill',       '#10b981'],
    'Rejected'        => ['sb-rejected',  'x-circle-fill',           '#ef4444'],
    'Completed'       => ['sb-completed', 'flag-fill',               '#8b5cf6'],
    'Cancelled'       => ['sb-cancelled', 'slash-circle-fill',       '#6b7280'],
    'Departure'       => ['sb-departure', 'arrow-right-circle-fill', '#14b8a6'],
];
const JSON_SAFE = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

function e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function statusMeta(string $s): array { return STATUS_META[$s] ?? ['sb-pending', 'info-circle', '#94a3b8']; }
function vehicleIcon(?string $type): string { return strcasecmp((string) $type, 'Bus') === 0 ? 'bus-front-fill' : 'car-front-fill'; }
function fmt($v, string $f = 'd/m/Y H:i'): string { return $v ? date($f, strtotime($v)) : ''; }

/** 51.25 hours -> "2d 3h 15m" (works for trips spanning any number of days). */
function fmtDuration($hours): string {
    if ($hours === null || $hours === '' || (float) $hours <= 0) return '';
    $mins = (int) round((float) $hours * 60);
    $d = intdiv($mins, 1440); $h = intdiv($mins % 1440, 60); $m = $mins % 60;
    $parts = [];
    if ($d) $parts[] = $d . 'd';
    if ($h) $parts[] = $h . 'h';
    if ($m || !$parts) $parts[] = $m . 'm';
    return implode(' ', $parts);
}

/** Alert + reload (GET) and stop, so a refresh never re-posts the form. */
function notify(string $msg): void {
    echo '<script>alert(' . json_encode($msg, JSON_SAFE) . ");window.location.href='';</script>";
    exit;
}

/** Prepared statement helper. */
function run(mysqli $c, string $sql, string $types, ...$params): void {
    $s = $c->prepare($sql);
    $s->bind_param($types, ...$params);
    $s->execute();
    $s->close();
}

/* ------------------------------------------------------------------
   CSRF
------------------------------------------------------------------ */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

function verify_csrf(): void {
    global $csrf_token;
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
        notify('Session expired or invalid request. Please refresh the page and try again.');
    }
}

/* Optional "approved_at" column */
$hasApprovedAtColumn = false;
if ($colCheck = $conn->query("SHOW COLUMNS FROM vehicle_applications LIKE 'approved_at'")) {
    $hasApprovedAtColumn = $colCheck->num_rows > 0;
}

/* ------------------------------------------------------------------
   WRITE OPERATIONS
------------------------------------------------------------------ */
if (isset($_POST['approve'])) {
    verify_csrf();
    $id         = (int) $_POST['app_id'];
    $driver_id  = !empty($_POST['driver_id'])  ? (int) $_POST['driver_id']  : null;
    $vehicle_id = !empty($_POST['vehicle_id']) ? (int) $_POST['vehicle_id'] : null;
    $remark     = trim($_POST['remark']);
    run($conn,
        "UPDATE vehicle_applications SET status='Approved', driver_id=?, vehicle_id=?, remark=?"
        . ($hasApprovedAtColumn ? ", approved_at=NOW()" : "") . " WHERE id=?",
        "iisi", $driver_id, $vehicle_id, $remark, $id);
    notify('Application Approved');
}

if (isset($_POST['reject'])) {
    verify_csrf();
    run($conn, "UPDATE vehicle_applications SET status='Rejected', remark=? WHERE id=?",
        "si", trim($_POST['remark']), (int) $_POST['app_id']);
    notify('Application Rejected');
}

if (isset($_POST['add_driver'])) {
    verify_csrf();
    $name  = trim($_POST['driver_name'] ?? '');
    $mob   = trim($_POST['driver_mob'] ?? '');
    $pass  = $_POST['driver_password'] ?? '';
    $pass2 = $_POST['driver_password_confirm'] ?? '';

    if ($name === '' || !preg_match('/^[0-9]{10}$/', $mob)) notify('Please enter a valid name and 10-digit mobile number.');
    if (strlen($pass) < 6)  notify('Password must be at least 6 characters long.');
    if ($pass !== $pass2)   notify('Password and confirmation do not match.');

    $chk = $conn->prepare("SELECT id FROM drivers WHERE driver_mob = ? LIMIT 1");
    $chk->bind_param("s", $mob);
    $chk->execute();
    $chk->store_result();
    $exists = $chk->num_rows > 0;
    $chk->close();
    if ($exists) notify('A driver with this mobile number already exists.');

    run($conn, "INSERT INTO drivers (driver_name, driver_mob, password) VALUES (?, ?, ?)",
        "sss", $name, $mob, password_hash($pass, PASSWORD_DEFAULT));
    notify('Driver Added');
}

if (isset($_POST['set_driver_password'])) {
    verify_csrf();
    $pass  = $_POST['new_password'] ?? '';
    $pass2 = $_POST['new_password_confirm'] ?? '';
    if (strlen($pass) < 6) notify('Password must be at least 6 characters long.');
    if ($pass !== $pass2)  notify('Password and confirmation do not match.');
    run($conn, "UPDATE drivers SET password=? WHERE id=?", "si",
        password_hash($pass, PASSWORD_DEFAULT), (int) $_POST['driver_id']);
    notify('Driver password updated successfully.');
}

if (isset($_POST['add_vehicle'])) {
    verify_csrf();
    $type = trim($_POST['vehicle_type'] ?? '');
    $name = trim($_POST['vehicle_name'] ?? '');
    $no   = trim($_POST['vehicle_no'] ?? '');
    if (!in_array($type, ['Car', 'Bus'], true) || $name === '' || $no === '') notify('Please fill in all vehicle fields correctly.');
    run($conn, "INSERT INTO vehicles (vehicle_type, vehicle_name, vehicle_no) VALUES (?, ?, ?)", "sss", $type, $name, $no);
    notify('Vehicle Added');
}

if (isset($_POST['update_driver_status'])) {
    verify_csrf();
    run($conn, "UPDATE drivers SET status=? WHERE id=?", "si",
        ($_POST['status'] === 'Inactive') ? 'Inactive' : 'Active', (int) $_POST['driver_id']);
    notify('Driver status updated successfully!');
}

if (isset($_POST['update_vehicle_status'])) {
    verify_csrf();
    run($conn, "UPDATE vehicles SET status=? WHERE id=?", "si",
        ($_POST['status'] === 'Inactive') ? 'Inactive' : 'Active', (int) $_POST['vehicle_id']);
    notify('Vehicle status updated successfully!');
}

if (isset($_POST['update_security'])) {
    verify_csrf();
    $id           = (int) $_POST['app_id'];
    $out_datetime = !empty($_POST['out_datetime']) ? $_POST['out_datetime'] : null;
    $in_datetime  = !empty($_POST['in_datetime'])  ? $_POST['in_datetime']  : null;
    $km_out       = (isset($_POST['km_out']) && $_POST['km_out'] !== '') ? floatval($_POST['km_out']) : null;
    $km_in        = (isset($_POST['km_in'])  && $_POST['km_in']  !== '') ? floatval($_POST['km_in'])  : null;

    /* guard name is locked once recorded */
    $lock = $conn->prepare("SELECT security_guard_name FROM vehicle_applications WHERE id=?");
    $lock->bind_param("i", $id);
    $lock->execute();
    $existingGuard = $lock->get_result()->fetch_assoc()['security_guard_name'] ?? '';
    $lock->close();
    $guard = !empty($existingGuard) ? $existingGuard : trim($_POST['security_guard_name'] ?? '');

    $total_km = ($km_out !== null && $km_in !== null && $km_in >= $km_out) ? round($km_in - $km_out, 2) : null;
    $total_hours = null;
    if ($out_datetime && $in_datetime) {
        $diff = strtotime($in_datetime) - strtotime($out_datetime);
        if ($diff > 0) $total_hours = round($diff / 3600, 2);
    }

    run($conn,
        "UPDATE vehicle_applications SET out_datetime=?, in_datetime=?, km_out=?, km_in=?,
         security_guard_name=?, total_km=?, total_hours=? WHERE id=?",
        "ssddsddi", $out_datetime, $in_datetime, $km_out, $km_in, $guard, $total_km, $total_hours, $id);
    notify('Security data updated successfully!');
}

/* ------------------------------------------------------------------
   READ DATA
------------------------------------------------------------------ */
$res = $conn->query("
  SELECT va.*, u.name AS forwarded_admin_name, u.designation AS forwarded_designation
  FROM vehicle_applications va
  LEFT JOIN users u ON va.forwarded_to = u.id
  ORDER BY va.created_at DESC
");
if (!$res) die("Query Failed: " . $conn->error);

/* drivers / vehicles currently on a trip are locked in the dropdowns */
$lockedDrivers = $lockedVehicles = [];
if ($q = $conn->query("SELECT driver_id, vehicle_id FROM vehicle_applications WHERE status = 'Departure'")) {
    while ($r = $q->fetch_assoc()) {
        if ($r['driver_id'])  $lockedDrivers[(int) $r['driver_id']]   = true;
        if ($r['vehicle_id']) $lockedVehicles[(int) $r['vehicle_id']] = true;
    }
}

$driverById = $driverOptions = [];
if ($q = $conn->query("SELECT * FROM drivers ORDER BY driver_name ASC")) {
    while ($d = $q->fetch_assoc()) {
        $driverById[(int) $d['id']] = $d;
        $driverOptions[] = [
            'id'       => (int) $d['id'],
            'label'    => $d['driver_name'] . ' (' . $d['driver_mob'] . ')',
            'locked'   => isset($lockedDrivers[(int) $d['id']]),
            'inactive' => (($d['status'] ?? 'Active') === 'Inactive'),
        ];
    }
}
$vehicleById = $vehicleOptions = [];
if ($q = $conn->query("SELECT * FROM vehicles ORDER BY vehicle_name ASC")) {
    while ($v = $q->fetch_assoc()) {
        $vehicleById[(int) $v['id']] = $v;
        $vehicleOptions[] = [
            'id'       => (int) $v['id'],
            'label'    => $v['vehicle_type'] . ' — ' . $v['vehicle_name'] . ' (' . $v['vehicle_no'] . ')',
            'locked'   => isset($lockedVehicles[(int) $v['id']]),
            'inactive' => (($v['status'] ?? 'Active') === 'Inactive'),
        ];
    }
}

/* ------------------------------------------------------------------
   BUILD APPLICATION LIST (+ data handed to JavaScript)
------------------------------------------------------------------ */
$apps = [];
$jsApps = [];
while ($row = $res->fetch_assoc()) {
    $id      = (int) $row['id'];
    $driver  = !empty($row['driver_id'])  ? ($driverById[(int) $row['driver_id']]   ?? null) : null;
    $vehicle = !empty($row['vehicle_id']) ? ($vehicleById[(int) $row['vehicle_id']] ?? null) : null;

    $coTrav = implode(", ", array_filter([
        $row['cotraveler1'] ?? null, $row['cotraveler2'] ?? null, $row['cotraveler3'] ?? null,
        $row['cotraveler4'] ?? null, $row['cotraveler5'] ?? null, $row['cotraveler6'] ?? null,
    ]));
    $forwardedName = $row['forwarded_admin_name'] ?? "N/A";
    $designation   = $row['forwarded_designation'] ?? "";
    $forwardedTo   = $forwardedName . ($designation ? " ($designation)" : "");

    $totalHours = $row['total_hours'] ?? null;
    if (!empty($row['out_datetime']) && !empty($row['in_datetime'])) {
        $diff = strtotime($row['in_datetime']) - strtotime($row['out_datetime']);
        if ($diff > 0) $totalHours = round($diff / 3600, 2);   /* recalculated from dates, never trust a truncated stored value */
    }

    $status    = $row['status'];
    $staffType = $row['staff_type'];

    /* which buttons are shown (rules unchanged) */
    $showAction = false;
    if ($staffType == "Permanent" && $status != "Approved" && $status != "Rejected") $showAction = true;
    if ($staffType == "Permanent" && $status == "Completed") $showAction = false;
    if ($status == "Approved") $showAction = true;
    if ($status == "Departure") $showAction = false;
    if ($staffType == "Temporary" && $status == "Recommended") $showAction = true;

    $apps[$id] = [
        'id' => $id, 'raw' => $row, 'driver' => $driver, 'vehicle' => $vehicle,
        'coTrav' => $coTrav, 'forwardedTo' => $forwardedTo, 'totalHours' => $totalHours,
        'status' => $status, 'staffType' => $staffType,
        'showAction' => $showAction, 'showSecurity' => ($status == "Completed"),
        'guard' => $row['security_guard_name'] ?? '',
    ];

    $jsApps[$id] = [
        'id' => $id,
        'bookingFor'  => $row['booking_for'] ?? '',
        'otherPerson' => $row['other_person_name'] ?? '',
        'name'        => $row['applicant_name'] ?? '',
        'mobile'      => $row['mobile'] ?? '',
        'email'       => $row['email'] ?? '',
        'staffType'   => $staffType,
        'from'        => fmt($row['datetime_from']),
        'to'          => fmt($row['datetime_to']),
        'place'       => $row['place_of_visit'] ?? '',
        'purpose'     => $row['purpose'] ?? '',
        'tour'        => ($row['tour_approved'] == 1) ? 'Attached' : 'N/A',
        'source'      => $row['source'] ?? 'ICMR-NIHR Jodhpur',
        'forwarded'   => $forwardedTo,
        'cotraveler'  => $coTrav,
        'driver'      => $driver['driver_name'] ?? '',
        'driverMob'   => $driver['driver_mob'] ?? '',
        'vehicleType' => $vehicle['vehicle_type'] ?? '',
        'vehicleName' => $vehicle['vehicle_name'] ?? '',
        'vehicleNo'   => $vehicle['vehicle_no'] ?? '',
        'outDt'       => fmt($row['out_datetime'], 'Y-m-d H:i'),
        'inDt'        => fmt($row['in_datetime'],  'Y-m-d H:i'),
        'kmOut'       => $row['km_out'] ?? '',
        'kmIn'        => $row['km_in'] ?? '',
        'totalKm'     => $row['total_km'] ?? '',
        'totalHours'  => $totalHours ?? '',
        'duration'    => fmtDuration($totalHours),
        'security'    => $apps[$id]['guard'],
        'remark'      => $row['remark'] ?? '',
        'status'      => $status,
        'submitted'   => $row['created_at'] ?? '',
        'approved_at' => $row['approved_at'] ?? null,
        'out_datetime_raw' => $row['out_datetime'] ?? null,
        'journey_from_raw' => $row['datetime_from'] ?? null,
        'overdue' => false, 'overdueReasons' => [],
    ];
}

/* Today's journeys */
$today = date('Y-m-d');
$todayAssignments = array_values(array_filter($apps, fn($a) =>
    !empty($a['raw']['datetime_from']) && date('Y-m-d', strtotime($a['raw']['datetime_from'])) === $today));
usort($todayAssignments, fn($a, $b) => strtotime($a['raw']['datetime_from']) <=> strtotime($b['raw']['datetime_from']));

$todayCounts = ['Pending' => 0, 'Recommended' => 0, 'Approved' => 0, 'Departure' => 0, 'Completed' => 0, 'Other' => 0];
foreach ($todayAssignments as $a) {
    $todayCounts[isset($todayCounts[$a['status']]) ? $a['status'] : 'Other']++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Vehicle Management — Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
<link href="./super.css" rel="stylesheet">
</head>
<body>

<?php include('../header.php'); ?>

<div class="export-overlay" id="exportOverlay">
  <div class="export-spinner"></div>
  <div class="export-msg" id="exportMsg">Generating…</div>
</div>

<div class="topbar">
  <a class="brand" href="">
    <div class="brand-icon"><i class="bi bi-truck-front-fill"></i></div>
    <span class="brand-text">Fleet<span>Admin</span></span>
  </a>
  <div class="topbar-right">
    <div class="admin-chip"><div class="avi">SA</div><span>Super Admin</span></div>
    <a href="logout.php" class="btn-logout"><i class="bi bi-box-arrow-right"></i> <span>Logout</span></a>
  </div>
</div>

<div class="page-wrap">

  <div class="page-header">
    <div>
      <div class="page-title">Vehicle <span>Applications</span></div>
      <div class="page-sub">Manage, approve and track all vehicle requests</div>
    </div>
    <div class="header-actions">
      <button class="abtn abtn-green" data-bs-toggle="modal" data-bs-target="#addDriverModal">
        <i class="bi bi-person-plus-fill"></i><span>Add/View Drivers</span>
      </button>
      <button class="abtn abtn-blue" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
        <i class="bi bi-truck"></i><span>Add/View Vehicles</span>
      </button>
      <a class="abtn abtn-blue" href="vehicle_utilization.php">
        <i class="bi bi-bar-chart-fill"></i><span>Vehicle &amp; Driver Utilization</span>
      </a>
    </div>
  </div>

  <?php if ($todayAssignments): ?>
  <div class="active-assign-card">
    <h6>
      <i class="bi bi-calendar-day-fill" style="color:#14b8a6;font-size:16px"></i>
      Today's Journeys — <?=date('d M Y')?>
      <span class="count-badge"><?=count($todayAssignments)?> total</span>
      <span class="status-chip chip-overdue" id="todayOverdueCount" style="display:none">
        <i class="bi bi-exclamation-triangle-fill"></i> <span id="overdueCountNum">0</span> Overdue
      </span>
      <?php if ($todayCounts['Departure'] > 0): ?>
        <span class="status-chip chip-departure"><i class="bi bi-arrow-right-circle-fill"></i> <?=$todayCounts['Departure']?> Departure</span>
      <?php endif; ?>
      <?php if ($todayCounts['Approved'] > 0): ?>
        <span class="status-chip chip-approved"><i class="bi bi-check-circle-fill"></i> <?=$todayCounts['Approved']?> Approved</span>
      <?php endif; ?>
      <?php if ($todayCounts['Completed'] > 0): ?>
        <span class="status-chip chip-completed"><i class="bi bi-flag-fill"></i> <?=$todayCounts['Completed']?> Completed</span>
      <?php endif; ?>
      <?php if (($todayCounts['Pending'] + $todayCounts['Recommended']) > 0): ?>
        <span class="status-chip chip-pending"><i class="bi bi-hourglass-split"></i> <?=$todayCounts['Pending'] + $todayCounts['Recommended']?> Pending</span>
      <?php endif; ?>
    </h6>

    <div class="active-assign-list" id="todayJourneyList">
      <?php foreach ($todayAssignments as $app):
        $a = $app['raw']; $driver = $app['driver']; $vehicle = $app['vehicle'];
        [$cls, $icon, $accent] = statusMeta($app['status']);
      ?>
      <div class="active-assign-item" style="border-left-color:<?=e($accent)?>" data-id="<?=$app['id']?>">
        <div class="aa-head">
          <span class="aa-id">#<?=$app['id']?></span>
          <span class="sbadge <?=$cls?>" style="font-size:10.5px;padding:4px 10px"><i class="bi bi-<?=$icon?>"></i> <?=e($app['status'])?></span>
        </div>
        <div class="aa-name"><?=e($a['applicant_name'])?></div>

        <?php if ($driver): ?>
        <div class="aa-row">
          <span class="ico-badge sm ico-driver"><i class="bi bi-person-badge-fill"></i></span>
          <strong><?=e($driver['driver_name'])?></strong><span class="text-secondary">·</span><span><?=e($driver['driver_mob'])?></span>
        </div>
        <?php else: ?>
        <div class="aa-row aa-muted"><i class="bi bi-person-dash"></i> Driver not assigned</div>
        <?php endif; ?>

        <?php if ($vehicle): ?>
        <div class="aa-row">
          <span class="ico-badge sm ico-vehicle"><i class="bi bi-<?=vehicleIcon($vehicle['vehicle_type'])?>"></i></span>
          <strong><?=e($vehicle['vehicle_type'])?> — <?=e($vehicle['vehicle_name'])?></strong><span class="text-secondary">·</span><span><?=e($vehicle['vehicle_no'])?></span>
        </div>
        <?php else: ?>
        <div class="aa-row aa-muted"><i class="bi bi-truck"></i> Vehicle not assigned</div>
        <?php endif; ?>

        <div class="aa-row"><i class="bi bi-geo-alt-fill" style="color:#f97316"></i><span><?=e($a['place_of_visit'])?></span></div>

        <div class="aa-row">
          <i class="bi bi-clock-fill" style="color:#3b5cf6"></i>
          <span><strong><?=fmt($a['datetime_from'], 'H:i')?></strong> → <?=fmt($a['datetime_to'], 'H:i')?></span>
        </div>

        <?php if (!empty($a['out_datetime'])): ?>
        <div class="aa-row">
          <i class="bi bi-box-arrow-right" style="color:#f59e0b"></i>
          <span>Out: <strong><?=fmt($a['out_datetime'], 'H:i')?></strong></span>
          <?php if (!empty($a['km_out'])): ?><span class="text-secondary">·</span><span><?=number_format($a['km_out'], 0)?> km</span><?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="overdue-banner" style="display:none">
          <i class="bi bi-exclamation-triangle-fill"></i><span class="overdue-message"></span>
        </div>

        <div class="aa-card-actions">
          <?php if ($app['showAction']): ?>
          <button type="button" class="tbl-btn tbl-btn-action btnAction" data-id="<?=$app['id']?>"><i class="bi bi-pencil-square"></i> Action</button>
          <?php endif; ?>
          <?php if ($app['showSecurity']): ?>
          <button type="button" class="tbl-btn tbl-btn-security btnSecurity" data-id="<?=$app['id']?>"><i class="bi bi-shield-lock"></i> Security</button>
          <?php endif; ?>
          <button type="button" class="tbl-btn tbl-btn-pdf exportRow" data-id="<?=$app['id']?>" title="Export PDF"><i class="bi bi-file-earmark-pdf"></i> Export</button>
          <button type="button" class="tbl-btn tbl-btn-copy copyRow" data-id="<?=$app['id']?>" title="Copy for WhatsApp"><i class="bi bi-whatsapp"></i> Copy</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="toolbar-card">
    <div class="search-wrap">
      <i class="bi bi-search"></i>
      <input type="text" id="globalSearch" placeholder="Search by name, mobile or email…">
    </div>

    <select class="ui-select" id="rowsPerPage">
      <option value="5">5 per page</option>
      <option value="10" selected>10 per page</option>
      <option value="25">25 per page</option>
      <option value="50">50 per page</option>
      <option value="9999">Show all</option>
    </select>

    <div class="toolbar-sep"></div>

    <button class="btn-filter-toggle" id="toggleFilters">
      <i class="bi bi-sliders"></i> Filters <span class="filter-badge" id="filterBadge">0</span>
    </button>

    <div class="toolbar-sep"></div>

    <div class="btn-group-actions">
      <button class="abtn abtn-outline" id="exportAllPDF"><i class="bi bi-file-earmark-pdf-fill" style="color:var(--red)"></i><span>PDF All</span></button>
      <button class="abtn abtn-amber" id="exportAllCompletedPDF"><i class="bi bi-file-earmark-check-fill"></i><span>PDF Completed</span></button>
      <button class="abtn abtn-outline" id="exportAllCSV"><i class="bi bi-filetype-csv" style="color:var(--green)"></i><span>Export CSV</span></button>
    </div>
  </div>

  <div class="filter-panel" id="filterPanel">
    <div class="flt-group">
      <label for="fStatus">Status</label>
      <select id="fStatus">
        <option value="">All statuses</option>
        <?php foreach (array_keys(STATUS_META) as $s): ?><option><?=e($s)?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="flt-group">
      <label for="fStaffType">Staff Type</label>
      <select id="fStaffType"><option value="">All types</option><option>Permanent</option><option>Temporary</option></select>
    </div>
    <div class="flt-group">
      <label for="fBookingFor">Booking For</label>
      <select id="fBookingFor"><option value="">All</option><option>Self</option><option>Someone</option></select>
    </div>
    <div class="flt-group"><label for="fDateFrom">Journey From</label><input type="date" id="fDateFrom"></div>
    <div class="flt-group"><label for="fDateTo">Journey To</label><input type="date" id="fDateTo"></div>
    <button class="btn-clear-filters" id="clearFilters"><i class="bi bi-x-circle"></i> Clear all</button>
  </div>

  <div class="table-card">
    <div class="table-scroll">
      <table id="mainTable">
        <thead>
          <tr>
            <th data-col="0" class="sortable">#<span class="sort-ico"></span></th>
            <th data-col="1" class="sortable">Applicant<span class="sort-ico"></span></th>
            <th data-col="2" class="sortable">Journey Details<span class="sort-ico"></span></th>
            <th>Assignment</th>
            <th>Security Movement</th>
            <th data-col="5" class="sortable">Status<span class="sort-ico"></span></th>
            <th style="text-align:center;white-space:nowrap">Actions</th>
          </tr>
        </thead>
        <tbody id="tableBody">
<?php if ($apps): foreach ($apps as $app):
    $row = $app['raw']; $driver = $app['driver']; $vehicle = $app['vehicle'];
    $coTrav = $app['coTrav']; $totalHours = $app['totalHours']; $guard = $app['guard'];
    $status = $app['status']; $staffType = $app['staffType'];
    [$sbClass, $sbIcon] = statusMeta($status);
?>
<tr data-id="<?=$row['id']?>"
    data-status="<?=e($status)?>"
    data-staff-type="<?=e($staffType)?>"
    data-booking-for="<?=e($row['booking_for'])?>"
    data-date="<?=strtotime($row['datetime_from'])?>">

  <td data-label="ID"><span class="cell-id">#<?=$row['id']?></span></td>

  <td data-label="Applicant"
      data-name="<?=e($row['applicant_name'])?>"
      data-mobile="<?=e($row['mobile'])?>"
      data-email="<?=e($row['email'])?>">
    <div class="applicant">
      <div class="tags">
        <span class="pill pill-slate"><?=e($row['booking_for'])?></span>
        <span class="pill <?=$staffType == 'Permanent' ? 'pill-blue' : 'pill-amber'?>"><?=e($staffType)?></span>
      </div>
      <?php if ($row['booking_for'] == "Someone" && !empty($row['other_person_name'])): ?>
        <div class="appl-name"><?=e($row['other_person_name'])?></div>
        <div class="appl-sub"><i class="bi bi-person-fill"></i> <?=e($row['applicant_name'])?></div>
      <?php else: ?>
        <div class="appl-name"><?=e($row['applicant_name'])?></div>
      <?php endif; ?>
      <div class="appl-sub"><i class="bi bi-telephone-fill"></i> <?=e($row['mobile'])?></div>
      <div class="appl-sub"><i class="bi bi-envelope-fill"></i> <?=e($row['email'])?></div>
    </div>
  </td>

  <td data-label="Journey Details">
    <div class="jcell">
      <div class="jline">
        <i class="bi bi-file-earmark-check-fill c-blue"></i><span class="jlabel">Tour</span>
        <?php if ($row['tour_approved'] == 1 && !empty($row['tour_order_pdf'])): ?>
          <a class="tour-view" href="view_tour_pdf.php?file=<?=urlencode($row['tour_order_pdf'])?>" target="_blank"><i class="bi bi-eye-fill"></i> View</a>
        <?php else: ?><span class="tour-na">N/A</span><?php endif; ?>
      </div>

      <!-- From → To on ONE line -->
      <div class="jline">
        <i class="bi bi-calendar-range-fill c-blue"></i><span class="jlabel">Journey</span>
        <span class="jval jrange"><?=fmt($row['datetime_from'])?> <i class="bi bi-arrow-right jarrow"></i> <?=fmt($row['datetime_to'])?></span>
      </div>

      <hr class="jdiv">

      <div class="jline"><i class="bi bi-share-fill c-ink"></i><span class="jlabel">Fwd</span><span class="jval"><?=e($row['forwarded_admin_name'] ?? 'N/A')?></span></div>

      <!-- Source → Destination on ONE line -->
      <div class="jline">
        <i class="bi bi-geo-alt-fill c-coral"></i><span class="jlabel">Route</span>
        <span class="jval jrange"><?=e($row['source'] ?? 'N/A')?> <i class="bi bi-arrow-right jarrow"></i> <?=e($row['place_of_visit'])?></span>
      </div>

      <div class="jline"><i class="bi bi-chat-left-quote-fill c-ink"></i><span class="jlabel">Purpose</span><span class="jval"><?=e($row['purpose'])?></span></div>

      <?php if (!empty($coTrav)): ?>
      <div class="jline">
        <i class="bi bi-people-fill c-violet"></i><span class="jlabel">Co-Trav</span>
        <div class="d-flex flex-wrap gap-1">
          <?php foreach (explode(", ", $coTrav) as $ct): ?><span class="pill pill-teal"><?=e($ct)?></span><?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </td>

  <td data-label="Assignment">
    <?php if ($driver || $vehicle): ?>
    <div class="assign-block">
      <?php if ($driver): ?>
      <div class="assign-item">
        <span class="ico-badge ico-driver"><i class="bi bi-person-badge-fill"></i></span>
        <div class="assign-txt">
          <div class="assign-section-label">Driver</div>
          <div class="assign-name"><?=e($driver['driver_name'])?></div>
          <div class="assign-detail"><i class="bi bi-telephone-fill"></i><?=e($driver['driver_mob'])?></div>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($driver && $vehicle): ?><hr class="assign-divider"><?php endif; ?>
      <?php if ($vehicle): ?>
      <div class="assign-item">
        <span class="ico-badge ico-vehicle"><i class="bi bi-<?=vehicleIcon($vehicle['vehicle_type'])?>"></i></span>
        <div class="assign-txt">
          <div class="assign-section-label">Vehicle</div>
          <div class="assign-name"><?=e($vehicle['vehicle_type'])?> — <?=e($vehicle['vehicle_name'])?></div>
          <div class="assign-detail"><i class="bi bi-upc-scan"></i><?=e($vehicle['vehicle_no'])?></div>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php else: ?><span class="not-assigned"><i class="bi bi-dash-circle"></i> Not assigned</span><?php endif; ?>
  </td>

  <td data-label="Security Movement">
    <?php if (!empty($row['out_datetime']) || !empty($row['in_datetime']) || !empty($row['km_out']) || !empty($row['security_guard_name'])): ?>
    <div class="sec-block">
      <?php if (!empty($row['out_datetime'])): ?><div class="sec-row"><i class="bi bi-box-arrow-right" style="color:var(--amber)"></i><span class="sec-label">Out</span><span class="sec-val"><?=fmt($row['out_datetime'])?></span></div><?php endif; ?>
      <?php if (!empty($row['in_datetime'])):  ?><div class="sec-row"><i class="bi bi-box-arrow-in-left" style="color:var(--teal)"></i><span class="sec-label">In</span><span class="sec-val"><?=fmt($row['in_datetime'])?></span></div><?php endif; ?>
      <?php if (!empty($row['km_out'])): ?><div class="sec-row"><i class="bi bi-speedometer2" style="color:var(--green)"></i><span class="sec-label">KM Out</span><span class="sec-val"><?=number_format($row['km_out'], 2)?></span></div><?php endif; ?>
      <?php if (!empty($row['km_in'])):  ?><div class="sec-row"><i class="bi bi-speedometer2" style="color:var(--red)"></i><span class="sec-label">KM In</span><span class="sec-val"><?=number_format($row['km_in'], 2)?></span></div><?php endif; ?>
      <?php if (!empty($row['total_km'])): ?><div class="sec-row"><i class="bi bi-graph-up-arrow" style="color:var(--blue)"></i><span class="sec-label">Total</span><span class="badge-km"><?=number_format($row['total_km'], 2)?> km</span></div><?php endif; ?>
      <?php if (!empty($totalHours)): ?><div class="sec-row"><i class="bi bi-clock-history" style="color:var(--violet)"></i><span class="sec-label">Hours</span><span class="badge-hrs" title="<?=e($totalHours)?> hours"><?=e(fmtDuration($totalHours))?></span><small class="text-muted ms-1"></small></div><?php endif; ?>
      <?php if (!empty($guard)): ?><div class="sec-row"><i class="bi bi-shield-lock-fill" style="color:var(--ink3)"></i><span class="sec-label">Guard / Driver</span><span class="sec-val" style="font-size:11px"><?=e($guard)?></span></div><?php endif; ?>
    </div>
    <?php else: ?>
    <div class="not-completed"><i class="bi bi-dash-circle"></i> Not completed</div>
    <?php endif; ?>
  </td>

  <td data-label="Status">
    <span class="sbadge <?=$sbClass?>"><i class="bi bi-<?=$sbIcon?>"></i><?=e($status)?></span>
    <div class="overdue-chip" style="display:none"><i class="bi bi-exclamation-triangle-fill"></i> Overdue — consider rejecting</div>
  </td>

  <td data-label="Actions">
    <div class="row-actions">
      <?php if ($app['showAction']): ?>
      <button type="button" class="tbl-btn tbl-btn-action btnAction" data-id="<?=$row['id']?>"><i class="bi bi-pencil-square"></i> Action</button>
      <?php endif; ?>
      <?php if ($app['showSecurity']): ?>
      <button type="button" class="tbl-btn tbl-btn-security btnSecurity" data-id="<?=$row['id']?>"><i class="bi bi-shield-lock"></i> Security</button>
      <?php endif; ?>
      <button type="button" class="tbl-btn tbl-btn-pdf exportRow" data-id="<?=$row['id']?>" title="Export PDF"><i class="bi bi-file-earmark-pdf"></i></button>
      <button type="button" class="tbl-btn tbl-btn-copy copyRow" data-id="<?=$row['id']?>" title="Copy for WhatsApp"><i class="bi bi-whatsapp"></i></button>
    </div>
  </td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="7"><div class="empty-state"><i class="bi bi-inbox"></i><p>No applications found</p></div></td></tr>
<?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="pagi-footer">
      <span class="pagi-info" id="pagiInfo"></span>
      <div class="pagi-btns" id="pagiContainer"></div>
    </div>
  </div>

</div><!-- /page-wrap -->

<!-- ACTION MODAL -->
<div class="modal fade" id="actionModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="POST" id="actionForm">
        <div class="modal-header">
          <div class="modal-title"><i class="bi bi-check2-square" style="color:var(--blue)"></i> Review Application #<span id="actionAppIdLabel"></span></div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?=e($csrf_token)?>">
          <input type="hidden" name="app_id" id="actionAppId" value="">
          <div class="mb-3">
            <label class="form-label" for="actionDriverSelect"><i class="bi bi-person-badge-fill" style="color:var(--teal)"></i> Select Driver</label>
            <select name="driver_id" class="form-select" id="actionDriverSelect"></select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="actionVehicleSelect"><i class="bi bi-truck-front-fill" style="color:var(--blue)"></i> Select Vehicle</label>
            <select name="vehicle_id" class="form-select" id="actionVehicleSelect"></select>
          </div>
          <div class="mb-0">
            <label class="form-label" for="actionRemark">Remark</label>
            <textarea name="remark" class="form-control" id="actionRemark" placeholder="Optional remark…"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="abtn abtn-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="reject" class="abtn abtn-outline-red"><i class="bi bi-x-circle-fill"></i> Reject</button>
          <button type="submit" name="approve" class="abtn abtn-green"><i class="bi bi-check-circle-fill"></i> Approve</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- SECURITY MODAL -->
<div class="modal fade" id="securityModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="POST" class="security-form" id="securityForm">
        <div class="modal-header">
          <div class="modal-title"><i class="bi bi-shield-lock" style="color:var(--teal)"></i> Security Movement #<span id="secAppIdLabel"></span></div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?=e($csrf_token)?>">
          <input type="hidden" name="app_id" id="secAppId" value="">

          <div class="row g-3">
            <div class="col-md-6">
              <div class="sec-input-group">
                <h6 class="text-primary mb-3"><i class="bi bi-box-arrow-right"></i> Departure</h6>
                <div class="mb-3">
                  <label class="sec-label" for="secOutDatetime">Date &amp; Time</label>
                  <input type="text" name="out_datetime" class="form-control" id="secOutDatetime" placeholder="YYYY-MM-DD HH:MM" autocomplete="off">
                </div>
                <div>
                  <label class="sec-label" for="secKmOut">KM Reading</label>
                  <input type="number" step="0.01" name="km_out" class="form-control" id="secKmOut" placeholder="Enter KM reading">
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="sec-input-group">
                <h6 class="text-success mb-3"><i class="bi bi-box-arrow-in-left"></i> Return</h6>
                <div class="mb-3">
                  <label class="sec-label" for="secInDatetime">Date &amp; Time</label>
                  <input type="text" name="in_datetime" class="form-control" id="secInDatetime" placeholder="YYYY-MM-DD HH:MM" autocomplete="off">
                </div>
                <div>
                  <label class="sec-label" for="secKmIn">KM Reading</label>
                  <input type="number" step="0.01" name="km_in" class="form-control" id="secKmIn" placeholder="Enter KM reading">
                </div>
              </div>
            </div>
            <div class="col-12">
              <div class="sec-input-group">
                <label class="sec-label" for="secGuardName">Security Guard / Driver Name</label>
                <input type="text" name="security_guard_name" class="form-control" id="secGuardName" placeholder="Enter Security Guard / Driver name">
                <div class="lock-note" id="secGuardLockNote" style="display:none">
                  <i class="bi bi-lock-fill"></i> Guard name is locked once recorded and can no longer be edited here.
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="sec-input-group">
                <label class="sec-label" for="secTotalKm">Total KM (Auto-calculated)</label>
                <input type="text" class="form-control" id="secTotalKm" readonly>
                <small class="text-muted">KM In − KM Out</small>
              </div>
            </div>
            <div class="col-md-6">
              <div class="sec-input-group">
                <label class="sec-label" for="secTotalHours">Total Hours (Auto-calculated)</label>
                <input type="text" class="form-control" id="secTotalHours" readonly>
                <small class="text-muted">Return − Departure</small>
              </div>
            </div>
          </div>

          <div class="mt-3 alert alert-info mb-0">
            <i class="bi bi-info-circle"></i>
            <strong>Note:</strong> Departure/return times and KM readings can be updated even after completion. Totals are always recalculated automatically and cannot be edited directly.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="abtn abtn-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="update_security" class="abtn abtn-teal"><i class="bi bi-check-circle-fill"></i> Save Security Data</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MANAGE DRIVERS MODAL -->
<div class="modal fade" id="addDriverModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title"><i class="bi bi-person-plus-fill" style="color:var(--green)"></i> Manage Drivers</div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="card mb-4">
          <div class="card-header bg-primary text-white"><h5 class="mb-0">Drivers List</h5></div>
          <div class="card-body table-responsive">
            <table class="table table-bordered table-hover align-middle">
              <thead class="table-light">
                <tr><th>#</th><th>Driver Name</th><th>Mobile</th><th>Password</th><th>Status</th><th style="min-width:260px">Action</th></tr>
              </thead>
              <tbody>
                <?php $i = 1; foreach ($driverById as $row):
                  $isAssigned = isset($lockedDrivers[(int) $row['id']]);
                  $status = $row['status'] ?? 'Active';
                  $hasPwd = !empty($row['password']);
                ?>
                <tr>
                  <td><?=$i++?></td>
                  <td><span class="ico-badge sm ico-driver d-inline-grid me-1 align-middle"><i class="bi bi-person-badge-fill"></i></span><?=e($row['driver_name'])?></td>
                  <td><?=e($row['driver_mob'])?></td>
                  <td><span class="badge bg-<?=$hasPwd ? 'success' : 'secondary'?>"><?=$hasPwd ? 'Set' : 'Not set'?></span></td>
                  <td>
                    <span class="badge bg-<?=$status == 'Active' ? 'success' : 'danger'?>"><?=e($status)?></span>
                    <?php if ($isAssigned): ?><small class="text-muted d-block">🔒 On Departure</small><?php endif; ?>
                  </td>
                  <td>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                      <form method="POST" action="" class="d-inline-flex gap-1 align-items-center m-0">
                        <input type="hidden" name="csrf_token" value="<?=e($csrf_token)?>">
                        <input type="hidden" name="driver_id" value="<?=$row['id']?>">
                        <select name="status" class="form-select form-select-sm" style="width:auto">
                          <option value="Active"   <?=$status == 'Active'   ? 'selected' : ''?>>Active</option>
                          <option value="Inactive" <?=$status == 'Inactive' ? 'selected' : ''?>>Inactive</option>
                        </select>
                        <button type="submit" name="update_driver_status" class="btn btn-sm btn-primary">Update</button>
                      </form>
                      <button type="button" class="btn btn-sm btn-warning btnSetPwd" data-id="<?=$row['id']?>" data-name="<?=e($row['driver_name'])?>">
                        <i class="bi bi-key-fill"></i> <?=$hasPwd ? 'Reset' : 'Set'?> Password
                      </button>
                    </div>
                  </td>
                </tr>
                <?php endforeach; if (!$driverById): ?>
                <tr><td colspan="6" class="text-center text-muted">No Drivers Found</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <hr>
        <h6 class="mb-3">Add New Driver</h6>
        <form method="POST" action="">
          <input type="hidden" name="csrf_token" value="<?=e($csrf_token)?>">
          <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" name="driver_name" class="form-control" placeholder="Driver's full name" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Mobile Number</label>
            <input type="text" name="driver_mob" class="form-control" placeholder="10-digit number" pattern="[0-9]{10}" title="Enter a 10-digit mobile number" required>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Password</label>
              <input type="password" name="driver_password" class="form-control" placeholder="Min. 6 characters" minlength="6" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Confirm Password</label>
              <input type="password" name="driver_password_confirm" class="form-control" placeholder="Re-enter password" minlength="6" required>
            </div>
          </div>
          <button type="submit" name="add_driver" class="abtn abtn-green"><i class="bi bi-check-circle-fill"></i> Save Driver</button>
        </form>
      </div>
      <div class="modal-footer"><button type="button" class="abtn abtn-outline" data-bs-dismiss="modal">Close</button></div>
    </div>
  </div>
</div>

<!-- MANAGE VEHICLES MODAL -->
<div class="modal fade" id="addVehicleModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title"><i class="bi bi-truck" style="color:var(--blue)"></i> Manage Vehicles</div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="card mb-4">
          <div class="card-header bg-success text-white"><h5 class="mb-0">Vehicles List</h5></div>
          <div class="card-body table-responsive">
            <table class="table table-bordered table-hover align-middle">
              <thead class="table-light">
                <tr><th>#</th><th>Vehicle Type</th><th>Vehicle Name</th><th>Vehicle Number</th><th>Status</th><th>Action</th></tr>
              </thead>
              <tbody>
                <?php $i = 1; foreach ($vehicleById as $row):
                  $isAssigned = isset($lockedVehicles[(int) $row['id']]);
                  $status = $row['status'] ?? 'Active';
                ?>
                <tr>
                  <td><?=$i++?></td>
                  <td><span class="ico-badge sm ico-vehicle d-inline-grid me-1 align-middle"><i class="bi bi-<?=vehicleIcon($row['vehicle_type'])?>"></i></span><?=e($row['vehicle_type'])?></td>
                  <td><?=e($row['vehicle_name'])?></td>
                  <td><?=e($row['vehicle_no'])?></td>
                  <td>
                    <span class="badge bg-<?=$status == 'Active' ? 'success' : 'danger'?>"><?=e($status)?></span>
                    <?php if ($isAssigned): ?><small class="text-muted d-block">🔒 On Departure</small><?php endif; ?>
                  </td>
                  <td>
                    <form method="POST" action="" class="d-inline-flex gap-1 align-items-center m-0">
                      <input type="hidden" name="csrf_token" value="<?=e($csrf_token)?>">
                      <input type="hidden" name="vehicle_id" value="<?=$row['id']?>">
                      <select name="status" class="form-select form-select-sm" style="width:auto">
                        <option value="Active"   <?=$status == 'Active'   ? 'selected' : ''?>>Active</option>
                        <option value="Inactive" <?=$status == 'Inactive' ? 'selected' : ''?>>Inactive</option>
                      </select>
                      <button type="submit" name="update_vehicle_status" class="btn btn-sm btn-primary">Update</button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; if (!$vehicleById): ?>
                <tr><td colspan="6" class="text-center text-muted">No Vehicles Found</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <hr>
        <h6 class="mb-3">Add New Vehicle</h6>
        <form method="POST" action="">
          <input type="hidden" name="csrf_token" value="<?=e($csrf_token)?>">
          <div class="mb-3">
            <label class="form-label">Vehicle Type</label>
            <select name="vehicle_type" class="form-select" required><option>Car</option><option>Bus</option></select>
          </div>
          <div class="mb-3">
            <label class="form-label">Vehicle Name</label>
            <input type="text" name="vehicle_name" class="form-control" placeholder="e.g. Toyota Innova" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Registration Number</label>
            <input type="text" name="vehicle_no" class="form-control" placeholder="e.g. MH 12 AB 1234" required>
          </div>
          <button type="submit" name="add_vehicle" class="abtn abtn-blue"><i class="bi bi-check-circle-fill"></i> Save Vehicle</button>
        </form>
      </div>
      <div class="modal-footer"><button type="button" class="abtn abtn-outline" data-bs-dismiss="modal">Close</button></div>
    </div>
  </div>
</div>

<!-- SET / RESET DRIVER PASSWORD MODAL -->
<div class="modal fade" id="setDriverPwdModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="">
        <div class="modal-header">
          <div class="modal-title"><i class="bi bi-key-fill" style="color:#f59e0b"></i> Set Password for&nbsp;<span id="setPwdDriverName"></span></div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?=e($csrf_token)?>">
          <input type="hidden" name="driver_id" id="setPwdDriverId" value="">
          <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" minlength="6" placeholder="Min. 6 characters" required>
          </div>
          <div>
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="new_password_confirm" class="form-control" minlength="6" placeholder="Re-enter password" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="abtn abtn-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="set_driver_password" class="abtn abtn-gold"><i class="bi bi-check-circle-fill"></i> Save Password</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
/* ============================================================
   DATA + SMALL HELPERS
   ============================================================ */
const driverOptions    = <?=json_encode($driverOptions,  JSON_SAFE)?>;
const vehicleOptions   = <?=json_encode($vehicleOptions, JSON_SAFE)?>;
const applicationsById = <?=json_encode($jsApps,         JSON_SAFE)?>;

const $     = id => document.getElementById(id);
const modal = id => bootstrap.Modal.getOrCreateInstance($(id));

/* ============================================================
   OVERDUE DETECTION
   Triggers when (status = Approved, no departure yet) AND:
     1. Expected journey time (datetime_from) is > 30 min past, OR
     2. Approval time is > 30 min past
   Both reasons are shown when both apply.
   ============================================================ */
   function computeOverdue(a) {
  const none = { overdue: false, reasons: [] };
  if ((a.status || '').trim() !== 'Approved' || a.out_datetime_raw) return none;

  const reasons = [];

  /* --- Reason 1: expected journey start time has passed by > 30 min --- */
  if (a.journey_from_raw) {
    const jt = new Date(a.journey_from_raw.replace(' ', 'T')).getTime();
    if (!isNaN(jt)) {
      const minsLate = Math.floor((Date.now() - jt) / 60000);
      if (minsLate > 30) {
        reasons.push(`Journey was scheduled ${minsLate} min ago, not yet departed`);
      }
    }
  }

  /* --- Reason 2: approved more than 30 min ago --- */
  if (a.approved_at) {
    const at = new Date(a.approved_at.replace(' ', 'T')).getTime();
    if (!isNaN(at)) {
      const minsSince = Math.floor((Date.now() - at) / 60000);
      if (minsSince > 30) {
        reasons.push(`Approved ${minsSince} min ago, not yet departed`);
      }
    }
  }

  return reasons.length ? { overdue: true, reasons } : none;
}

function refreshOverdueUI() {
  Object.values(applicationsById).forEach(a => {
    const r = computeOverdue(a);
    a.overdue = r.overdue;
    a.overdueReasons = r.reasons;
  });

  let count = 0;
  document.querySelectorAll('.active-assign-item').forEach(card => {
    const a = applicationsById[card.dataset.id];
    if (!a) return;
    if (a.overdue) count++;
    card.classList.toggle('aa-overdue', a.overdue);
    const banner = card.querySelector('.overdue-banner');
    if (!banner) return;
    banner.style.display = a.overdue ? 'flex' : 'none';
    if (a.overdue) banner.querySelector('.overdue-message').textContent = a.overdueReasons.join(' · ') + ' — consider rejecting this request.';
  });

  const wrap = $('todayOverdueCount');
  if (wrap) { $('overdueCountNum').textContent = count; wrap.style.display = count ? '' : 'none'; }

  document.querySelectorAll('#tableBody tr[data-status]').forEach(tr => {
    const a = applicationsById[tr.dataset.id];
    if (!a) return;
    tr.classList.toggle('row-overdue', a.overdue);
    const chip = tr.querySelector('.overdue-chip');
    if (chip) { chip.style.display = a.overdue ? 'inline-flex' : 'none'; chip.title = a.overdueReasons.join(' · '); }
  });
}

/* ============================================================
   SECURITY MODAL (flatpickr + auto totals)
   ============================================================ */
let secOutFP, secInFP;

/* 51.25 hours -> "2d 3h 15m" */
function fmtDuration(hours) {
  const mins = Math.round(hours * 60), d = Math.floor(mins / 1440), h = Math.floor((mins % 1440) / 60), m = mins % 60;
  return [d && d + 'd', h && h + 'h', (m || (!d && !h)) && m + 'm'].filter(Boolean).join(' ');
}

function recalcSecurity() {
  const kmOutVal = $('secKmOut').value, kmInVal = $('secKmIn').value;
  const kmOut = parseFloat(kmOutVal) || 0, kmIn = parseFloat(kmInVal) || 0;
  $('secTotalKm').value = (kmIn > kmOut && (kmOutVal || kmInVal)) ? (kmIn - kmOut).toFixed(2) : '';

  const d1 = new Date($('secOutDatetime').value.replace(' ', 'T'));
  const d2 = new Date($('secInDatetime').value.replace(' ', 'T'));
  $('secTotalHours').value = (!isNaN(d1) && !isNaN(d2) && d2 > d1)
    ? `${fmtDuration((d2 - d1) / 3600000)}  (${((d2 - d1) / 3600000).toFixed(2)} h)` : '';
}

function populateSelect(sel, data) {
  sel.innerHTML = '<option value="">— Available —</option>';
  data.forEach(item => {
    const opt = document.createElement('option');
    opt.value = item.id;
    opt.textContent = item.label + (item.locked ? ' 🔒 Currently on Departure' : '') + (item.inactive ? ' ⛔ Inactive' : '');
    opt.disabled = item.locked || item.inactive;
    sel.appendChild(opt);
  });
}

/* ============================================================
   DELEGATED CLICK HANDLERS (work across pagination / filtering)
   ============================================================ */
document.addEventListener('click', e => {
  const actionBtn = e.target.closest('.btnAction');
  if (actionBtn) {
    const id = actionBtn.dataset.id;
    $('actionAppIdLabel').textContent = id;
    $('actionAppId').value = id;
    $('actionRemark').value = '';
    populateSelect($('actionDriverSelect'), driverOptions);
    populateSelect($('actionVehicleSelect'), vehicleOptions);
    modal('actionModal').show();
    return;
  }

  const secBtn = e.target.closest('.btnSecurity');
  if (secBtn) {
    const id = secBtn.dataset.id, app = applicationsById[id] || {};
    $('secAppIdLabel').textContent = id;
    $('secAppId').value = id;

    app.outDt ? secOutFP.setDate(app.outDt, true, 'Y-m-d H:i') : secOutFP.clear();
    app.inDt  ? secInFP.setDate(app.inDt, true, 'Y-m-d H:i')   : secInFP.clear();
    $('secKmOut').value = app.kmOut || '';
    $('secKmIn').value  = app.kmIn  || '';

    const guard = $('secGuardName'), existing = app.security || '';
    guard.value = existing;
    guard.readOnly = !!existing;
    $('secGuardLockNote').style.display = existing ? 'flex' : 'none';

    recalcSecurity();
    modal('securityModal').show();
    return;
  }

  /* Set/Reset driver password: close the driver list first so only one modal is open (keeps inputs focusable) */
  const pwdBtn = e.target.closest('.btnSetPwd');
  if (pwdBtn) {
    $('setPwdDriverId').value = pwdBtn.dataset.id;
    $('setPwdDriverName').textContent = pwdBtn.dataset.name;
    document.querySelectorAll('#setDriverPwdModal input[type=password]').forEach(i => i.value = '');
    const open = () => modal('setDriverPwdModal').show();
    const list = $('addDriverModal');
    if (list.classList.contains('show')) {
      list.addEventListener('hidden.bs.modal', open, { once: true });
      modal('addDriverModal').hide();
    } else open();
  }
});

document.addEventListener('DOMContentLoaded', () => {
  const fp = { enableTime: true, dateFormat: 'Y-m-d H:i', time_24hr: true, minuteIncrement: 1, allowInput: true };
  secOutFP = flatpickr('#secOutDatetime', fp);
  secInFP  = flatpickr('#secInDatetime',  fp);
  ['secKmOut', 'secKmIn', 'secOutDatetime', 'secInDatetime'].forEach(id => {
    $(id).addEventListener('input', recalcSecurity);
    $(id).addEventListener('change', recalcSecurity);
  });
  refreshOverdueUI();
  setInterval(refreshOverdueUI, 30000);
});

/* ============================================================
   TABLE ENGINE (search · filters · sort · pagination)
   ============================================================ */
(function () {
  const tbody = $('tableBody'), searchEl = $('globalSearch'), rowsEl = $('rowsPerPage');
  const pagiInfo = $('pagiInfo'), pagiCont = $('pagiContainer');
  const fStatus = $('fStatus'), fStaff = $('fStaffType'), fBook = $('fBookingFor'), fFrom = $('fDateFrom'), fTo = $('fDateTo');
  const badge = $('filterBadge'), panel = $('filterPanel'), toggleBtn = $('toggleFilters');

  const allRows = Array.from(tbody.querySelectorAll('tr[data-status]'));
  let filtered = [...allRows], page = 1, sortCol = -1, sortDir = 1;

  toggleBtn.addEventListener('click', () => { panel.classList.toggle('open'); toggleBtn.classList.toggle('active'); });
  $('clearFilters').addEventListener('click', () => { fStatus.value = fStaff.value = fBook.value = fFrom.value = fTo.value = ''; apply(); });

  document.querySelectorAll('#mainTable thead th.sortable').forEach(th => {
    th.addEventListener('click', () => {
      const col = parseInt(th.dataset.col);
      if (sortCol === col) sortDir *= -1; else { sortCol = col; sortDir = 1; }
      document.querySelectorAll('#mainTable thead th').forEach(t => t.classList.remove('sort-asc', 'sort-desc'));
      th.classList.add(sortDir === 1 ? 'sort-asc' : 'sort-desc');
      apply();
    });
  });

  function colVal(tr, col) {
    switch (col) {
      case 0: return parseInt(tr.dataset.id) || 0;
      case 1: return (tr.querySelector('td[data-name]')?.dataset.name || '').toLowerCase();
      case 2: return parseInt(tr.dataset.date) || 0;
      case 5: return (tr.dataset.status || '').toLowerCase();
      default: return (tr.children[col]?.innerText || '').toLowerCase();
    }
  }

  function apply() {
    const kw = (searchEl.value || '').toLowerCase().trim();
    const dfrom = fFrom.value ? new Date(fFrom.value).getTime() / 1000 : null;
    const dto   = fTo.value   ? new Date(fTo.value).getTime() / 1000 + 86399 : null;

    const active = [fStatus, fStaff, fBook, fFrom, fTo].filter(el => el.value).length;
    badge.textContent = active;
    badge.classList.toggle('show', active > 0);

    filtered = allRows.filter(tr => {
      if (kw) {
        const d = tr.querySelector('td[data-name]')?.dataset || {};
        if (![d.name, d.mobile, d.email].some(v => (v || '').toLowerCase().includes(kw))) return false;
      }
      if (fStatus.value && tr.dataset.status !== fStatus.value) return false;
      if (fStaff.value  && tr.dataset.staffType !== fStaff.value) return false;
      if (fBook.value   && tr.dataset.bookingFor !== fBook.value) return false;
      const d = parseInt(tr.dataset.date) || 0;
      if (dfrom && d < dfrom) return false;
      if (dto && d > dto) return false;
      return true;
    });

    if (sortCol >= 0) {
      filtered.sort((a, b) => {
        const va = colVal(a, sortCol), vb = colVal(b, sortCol);
        return (typeof va === 'number' ? va - vb : va.localeCompare(vb)) * sortDir;
      });
    }
    page = 1;
    render();
  }

  function render() {
    const perPage = parseInt(rowsEl.value);
    const total = filtered.length;
    const pages = Math.ceil(total / perPage) || 1;
    if (page > pages) page = pages;
    const start = (page - 1) * perPage, end = Math.min(start + perPage, total);

    tbody.innerHTML = '';
    if (!total) {
      tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state"><i class="bi bi-search"></i><p>No records match your filters.</p></div></td></tr>';
    } else {
      const frag = document.createDocumentFragment();
      filtered.slice(start, end).forEach(tr => frag.appendChild(tr));
      tbody.appendChild(frag);
    }
    pagiInfo.textContent = total ? `Showing ${start + 1}–${end} of ${total} records` : 'No records';
    buildPagi(pages);
    refreshOverdueUI();          /* newly shown rows get their overdue state immediately */
  }

  function buildPagi(pages) {
    pagiCont.innerHTML = '';
    const btn = (label, p, cls = '') => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'pg-btn ' + cls;
      b.innerHTML = label;
      b.addEventListener('click', () => { page = p; render(); });
      pagiCont.appendChild(b);
    };
    const dots = () => { const s = document.createElement('span'); s.className = 'pg-ellipsis'; s.textContent = '…'; pagiCont.appendChild(s); };
    const lo = Math.max(1, page - 2), hi = Math.min(pages, page + 2);

    btn('<i class="bi bi-chevron-double-left"></i>', 1);
    btn('<i class="bi bi-chevron-left"></i>', Math.max(1, page - 1));
    if (lo > 1) dots();
    for (let i = lo; i <= hi; i++) btn(i, i, i === page ? 'active' : '');
    if (hi < pages) dots();
    btn('<i class="bi bi-chevron-right"></i>', Math.min(pages, page + 1));
    btn('<i class="bi bi-chevron-double-right"></i>', pages);
  }

  searchEl.addEventListener('input', apply);
  rowsEl.addEventListener('change', () => { page = 1; render(); });
  [fStatus, fStaff, fBook, fFrom, fTo].forEach(el => el.addEventListener('change', apply));
  apply();

  window.__getFiltered = () => filtered;
})();

/* ============================================================
   EXPORTS (PDF · CSV · WhatsApp copy)
   ============================================================ */
const showLoad = msg => { $('exportMsg').textContent = msg; $('exportOverlay').classList.add('show'); };
const hideLoad = () => $('exportOverlay').classList.remove('show');
const rowData  = id => applicationsById[id] || {};
const currentRows = () => window.__getFiltered ? window.__getFiltered() : Array.from(document.querySelectorAll('#tableBody tr[data-status]'));

/* ---------- WhatsApp ---------- */
function buildWhatsAppMessage(d) {
  const line = (label, val) => (val !== undefined && val !== null && String(val).trim() !== '') ? `*${label}:* ${val}\n` : '';
  let msg = `🚐 *APPLICATION #${d.id}*  |  *${d.status || 'Pending'}*\n━━━━━━━━━━━━━━━━━━\n\n👤 *APPLICANT DETAILS*\n`;

  if (d.bookingFor)  msg += line('Booking For', d.bookingFor);
  if (d.otherPerson) msg += line('For', d.otherPerson);
  if (d.name || d.mobile) msg += `*Name:* ${d.name || ''}${d.mobile ? ` (${d.mobile})` : ''}\n`;

  msg += `\n🗺️ *JOURNEY DETAILS*\n`;
  if (d.from || d.to)      msg += `*Journey:* ${d.from || ''}${d.to ? ` → ${d.to}` : ''}\n`;
  if (d.source || d.place) msg += `*Route:* ${d.source || ''}${d.place ? ` → ${d.place}` : ''}\n`;
  if (d.cotraveler)        msg += line('👥 Co-Travelers', d.cotraveler);
  msg += '\n';

  if (d.driver || d.vehicleName || d.vehicleNo) {
    msg += `🚘 *ASSIGNMENT*\n`;
    if (d.driver || d.driverMob) msg += `*Driver:* ${d.driver || ''}${d.driverMob ? ` (${d.driverMob})` : ''}\n`;
    if (d.vehicleName || d.vehicleNo) {
      const vehicle = d.vehicleType ? `${d.vehicleType} — ${d.vehicleName || ''}` : (d.vehicleName || '');
      msg += `*Vehicle:* ${vehicle}${d.vehicleNo ? ` (${d.vehicleNo})` : ''}\n`;
    }
    msg += '\n';
  }
  if (d.overdue && d.overdueReasons?.length) msg += `⚠️ *${d.overdueReasons.join(' · ')}*\n`;
  return msg.trim();
}

function copyTextToClipboard(text) {
  if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text);
  return new Promise((resolve, reject) => {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.cssText = 'position:fixed;opacity:0';
    document.body.appendChild(ta);
    ta.focus(); ta.select();
    try { document.execCommand('copy') ? resolve() : reject(new Error('copy failed')); }
    catch (err) { reject(err); }
    finally { document.body.removeChild(ta); }
  });
}

function showCopyToast(msg) {
  let toast = $('copyToast');
  if (!toast) { toast = document.createElement('div'); toast.id = 'copyToast'; toast.className = 'copy-toast'; document.body.appendChild(toast); }
  toast.innerHTML = `<i class="bi bi-check-circle-fill"></i> ${msg}`;
  toast.classList.add('show');
  clearTimeout(toast._t);
  toast._t = setTimeout(() => toast.classList.remove('show'), 1800);
}

document.addEventListener('click', e => {
  const btn = e.target.closest('.copyRow');
  if (!btn) return;
  copyTextToClipboard(buildWhatsAppMessage(rowData(btn.dataset.id)))
    .then(() => showCopyToast('Copied — paste it in WhatsApp'))
    .catch(() => alert('Could not copy automatically. Please copy manually.'));
});

/* ---------- PDF ---------- */
function makePDF(dataArr) {
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF('p', 'pt', 'a4');
  const pw = doc.internal.pageSize.getWidth();
  dataArr.forEach((d, i) => {
    if (i > 0) doc.addPage();
    doc.setFontSize(16); doc.setTextColor(30, 58, 138); doc.setFont('helvetica', 'bold');
    doc.text(`Vehicle Application  #${d.id}`, pw / 2, 38, { align: 'center' });
    doc.setDrawColor(30, 58, 138); doc.setLineWidth(.5); doc.line(40, 46, pw - 40, 46);
    const body = [
      ['Booking For', d.bookingFor], ['Other Person', d.otherPerson], ['Applicant', d.name],
      ['Staff Type', d.staffType], ['Mobile', d.mobile], ['Email', d.email],
      ['Journey From', d.from], ['Journey To', d.to], ['Source', d.source], ['Place of Visit', d.place],
      ['Purpose', d.purpose], ['Tour Order', d.tour], ['Forwarded To', d.forwarded], ['Co-Travelers', d.cotraveler],
      ['Driver', d.driver], ['Driver Mobile', d.driverMob],
      ['Vehicle Type', d.vehicleType], ['Vehicle Name', d.vehicleName], ['Vehicle No', d.vehicleNo],
      ['Departure', d.outDt], ['Return', d.inDt],
      ['KM Out', d.kmOut], ['KM In', d.kmIn], ['Total KM', d.totalKm], ['Total Hours', d.totalHours ? `${d.duration} (${d.totalHours} h)` : ''],
      ['Security Guard / Driver', d.security], ['Remark', d.remark],
      ['Status', d.overdue ? `${d.status}  (Overdue - not yet departed)` : d.status],
      ['Submitted', d.submitted]
    ].filter(r => r[1]);
    doc.autoTable({
      startY: 58, head: [['Field', 'Details']], body, theme: 'grid',
      styles: { fontSize: 9.5, cellPadding: 5, font: 'helvetica' },
      headStyles: { fillColor: [30, 58, 138], textColor: 255, fontStyle: 'bold' },
      alternateRowStyles: { fillColor: [247, 248, 252] },
      columnStyles: { 0: { fontStyle: 'bold', cellWidth: 130, textColor: [61, 79, 110] } }
    });
  });
  return doc;
}

function exportPDF(rows, message, filename, emptyMsg) {
  if (!rows.length) { alert(emptyMsg); return; }
  showLoad(message.replace('{n}', rows.length));
  setTimeout(() => { makePDF(rows.map(tr => rowData(tr.dataset.id))).save(filename); hideLoad(); }, 80);
}

document.addEventListener('click', e => {
  const btn = e.target.closest('.exportRow');
  if (!btn) return;
  const id = btn.dataset.id;
  showLoad('Generating PDF…');
  setTimeout(() => { makePDF([rowData(id)]).save(`application_${id}.pdf`); hideLoad(); }, 80);
});

$('exportAllPDF').addEventListener('click', () =>
  exportPDF(currentRows(), 'Building PDF for {n} records…', 'All_Vehicle_Applications.pdf', 'No records to export.'));

$('exportAllCompletedPDF').addEventListener('click', () =>
  exportPDF(currentRows().filter(tr => (tr.dataset.status || '').trim().toLowerCase() === 'completed'),
    'Building PDF for {n} completed records…', 'Completed_Vehicle_Applications.pdf', 'No Completed applications found.'));

/* ---------- CSV ---------- */
$('exportAllCSV').addEventListener('click', () => {
  const rows = currentRows();
  if (!rows.length) { alert('No records to export.'); return; }
  showLoad('Generating CSV…');
  setTimeout(() => {
    const hdrs = ['App ID', 'Booking For', 'Other Person', 'Applicant', 'Mobile', 'Email', 'Staff Type',
      'Journey From', 'Journey To', 'Source', 'Place', 'Purpose', 'Tour Order', 'Forwarded To', 'Co-Travelers',
      'Driver', 'Driver Mobile', 'Vehicle Type', 'Vehicle Name', 'Vehicle No',
      'Departure', 'Return', 'KM Out', 'KM In', 'Total KM', 'Total Hours', 'Security Guard / Driver', 'Remark', 'Status', 'Overdue', 'Submitted'];
    const fmtD = v => {
      if (!v) return '';
      const d = new Date(v);
      return isNaN(d) ? v : d.toLocaleString('en-IN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true });
    };
    const esc = v => `"${String(v || '').replace(/"/g, '""')}"`;
    let csv = '\uFEFF' + hdrs.map(esc).join(',') + '\n';
    rows.forEach(tr => {
      const d = rowData(tr.dataset.id);
      const overdueText = d.overdue && Array.isArray(d.overdueReasons) ? d.overdueReasons.join(' | ') : '';
      csv += [esc(d.id), esc(d.bookingFor), esc(d.otherPerson), esc(d.name), `="${d.mobile}"`, esc(d.email), esc(d.staffType),
        esc(d.from), esc(d.to), esc(d.source), esc(d.place), esc(d.purpose), esc(d.tour), esc(d.forwarded), esc(d.cotraveler),
        esc(d.driver), `="${d.driverMob}"`, esc(d.vehicleType), esc(d.vehicleName), esc(d.vehicleNo),
        esc(fmtD(d.outDt)), esc(fmtD(d.inDt)), esc(d.kmOut), esc(d.kmIn), esc(d.totalKm), esc(d.totalHours ? `${d.duration} (${d.totalHours} h)` : ''),
        esc(d.security), esc(d.remark), esc(d.status), esc(overdueText), esc(fmtD(d.submitted))].join(',') + '\n';
    });
    const ts = new Date().toISOString().slice(0, 16).replace(/[-:T]/g, '_');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
    a.download = `vehicle_applications_${rows.length}_${ts}.csv`;
    a.click();
    setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    hideLoad();
  }, 80);
});
</script>
</body>
</html>
<?php
session_start();

/* ------------------------------------------------------------------
   BASIC SECURITY HEADERS
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
   CSRF PROTECTION
------------------------------------------------------------------ */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

function verify_csrf() {
    global $csrf_token;
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
        die("<script>alert('Session expired or invalid request. Please refresh the page and try again.'); window.location.href='';</script>");
    }
}

/* ------------------------------------------------------------------
   OPTIONAL "approved_at" COLUMN
------------------------------------------------------------------ */
$hasApprovedAtColumn = false;
if ($colCheck = $conn->query("SHOW COLUMNS FROM vehicle_applications LIKE 'approved_at'")) {
    $hasApprovedAtColumn = $colCheck->num_rows > 0;
}

/* ------------------------------------------------------------------
   ALL WRITE OPERATIONS
------------------------------------------------------------------ */
if (isset($_POST['approve'])) {
    verify_csrf();
    $id         = (int) $_POST['app_id'];
    $driver_id  = !empty($_POST['driver_id'])  ? (int) $_POST['driver_id']  : null;
    $vehicle_id = !empty($_POST['vehicle_id']) ? (int) $_POST['vehicle_id'] : null;
    $remark     = trim($_POST['remark']);

    if ($hasApprovedAtColumn) {
        $stmt = $conn->prepare("UPDATE vehicle_applications SET status='Approved', driver_id=?, vehicle_id=?, remark=?, approved_at=NOW() WHERE id=?");
    } else {
        $stmt = $conn->prepare("UPDATE vehicle_applications SET status='Approved', driver_id=?, vehicle_id=?, remark=? WHERE id=?");
    }
    $stmt->bind_param("iisi", $driver_id, $vehicle_id, $remark, $id);
    $stmt->execute();
    $stmt->close();
    echo "<script>alert('Application Approved'); window.location.href='';</script>";
}

if (isset($_POST['add_driver'])) {
    verify_csrf();
    $name = trim($_POST['driver_name'] ?? '');
    $mob  = trim($_POST['driver_mob'] ?? '');
    $pass = $_POST['driver_password'] ?? '';
    $pass2= $_POST['driver_password_confirm'] ?? '';

    if ($name === '' || !preg_match('/^[0-9]{10}$/', $mob)) {
        echo "<script>alert('Please enter a valid name and 10-digit mobile number.'); window.location.href='';</script>";
    } elseif (strlen($pass) < 6) {
        echo "<script>alert('Password must be at least 6 characters long.'); window.location.href='';</script>";
    } elseif ($pass !== $pass2) {
        echo "<script>alert('Password and confirmation do not match.'); window.location.href='';</script>";
    } else {
        // check duplicate mobile
        $chk = $conn->prepare("SELECT id FROM drivers WHERE driver_mob = ? LIMIT 1");
        $chk->bind_param("s", $mob);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            $chk->close();
            echo "<script>alert('A driver with this mobile number already exists.'); window.location.href='';</script>";
        } else {
            $chk->close();
            $hashed = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO drivers (driver_name, driver_mob, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $mob, $hashed);
            $stmt->execute();
            $stmt->close();
            echo "<script>alert('Driver Added'); window.location.href='';</script>";
        }
    }
}

/* NEW: set / reset a driver password (for existing drivers) */
if (isset($_POST['set_driver_password'])) {
    verify_csrf();
    $driver_id = (int) $_POST['driver_id'];
    $pass      = $_POST['new_password'] ?? '';
    $pass2     = $_POST['new_password_confirm'] ?? '';

    if (strlen($pass) < 6) {
        echo "<script>alert('Password must be at least 6 characters long.'); window.location.href='';</script>";
    } elseif ($pass !== $pass2) {
        echo "<script>alert('Password and confirmation do not match.'); window.location.href='';</script>";
    } else {
        $hashed = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE drivers SET password=? WHERE id=?");
        $stmt->bind_param("si", $hashed, $driver_id);
        $stmt->execute();
        $stmt->close();
        echo "<script>alert('Driver password updated successfully.'); window.location.href='';</script>";
    }
}

if (isset($_POST['add_vehicle'])) {
    verify_csrf();
    $allowedTypes = ['Car', 'Bus'];
    $type = trim($_POST['vehicle_type'] ?? '');
    $name = trim($_POST['vehicle_name'] ?? '');
    $no   = trim($_POST['vehicle_no'] ?? '');

    if (!in_array($type, $allowedTypes, true) || $name === '' || $no === '') {
        echo "<script>alert('Please fill in all vehicle fields correctly.'); window.location.href='';</script>";
    } else {
        $stmt = $conn->prepare("INSERT INTO vehicles (vehicle_type, vehicle_name, vehicle_no) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $type, $name, $no);
        $stmt->execute();
        $stmt->close();
        echo "<script>alert('Vehicle Added'); window.location.href='';</script>";
    }
}

if (isset($_POST['reject'])) {
    verify_csrf();
    $id     = (int) $_POST['app_id'];
    $remark = trim($_POST['remark']);

    $stmt = $conn->prepare("UPDATE vehicle_applications SET status='Rejected', remark=? WHERE id=?");
    $stmt->bind_param("si", $remark, $id);
    $stmt->execute();
    $stmt->close();
    echo "<script>alert('Application Rejected'); window.location.href='';</script>";
}

if (isset($_POST['update_driver_status'])) {
    verify_csrf();
    $driver_id = (int) $_POST['driver_id'];
    $status    = ($_POST['status'] === 'Inactive') ? 'Inactive' : 'Active';

    $stmt = $conn->prepare("UPDATE drivers SET status=? WHERE id=?");
    $stmt->bind_param("si", $status, $driver_id);
    $stmt->execute();
    $stmt->close();
    echo "<script>alert('Driver status updated successfully!'); window.location.href='';</script>";
}

if (isset($_POST['update_vehicle_status'])) {
    verify_csrf();
    $vehicle_id = (int) $_POST['vehicle_id'];
    $status     = ($_POST['status'] === 'Inactive') ? 'Inactive' : 'Active';

    $stmt = $conn->prepare("UPDATE vehicles SET status=? WHERE id=?");
    $stmt->bind_param("si", $status, $vehicle_id);
    $stmt->execute();
    $stmt->close();
    echo "<script>alert('Vehicle status updated successfully!'); window.location.href='';</script>";
}

if (isset($_POST['update_security'])) {
    verify_csrf();
    $id = (int) $_POST['app_id'];

    $out_datetime = !empty($_POST['out_datetime']) ? $_POST['out_datetime'] : null;
    $in_datetime  = !empty($_POST['in_datetime'])  ? $_POST['in_datetime']  : null;
    $km_out       = ($_POST['km_out'] !== '' && isset($_POST['km_out'])) ? floatval($_POST['km_out']) : null;
    $km_in        = ($_POST['km_in']  !== '' && isset($_POST['km_in']))  ? floatval($_POST['km_in'])  : null;

    $lockStmt = $conn->prepare("SELECT security_guard_name FROM vehicle_applications WHERE id=?");
    $lockStmt->bind_param("i", $id);
    $lockStmt->execute();
    $existingGuard = $lockStmt->get_result()->fetch_assoc()['security_guard_name'] ?? '';
    $lockStmt->close();
    $security_guard_name = !empty($existingGuard) ? $existingGuard : trim($_POST['security_guard_name'] ?? '');

    $total_km = null;
    if ($km_out !== null && $km_in !== null && $km_in >= $km_out) {
        $total_km = round($km_in - $km_out, 2);
    }
    $total_hours = null;
    if ($out_datetime && $in_datetime) {
        $diff = strtotime($in_datetime) - strtotime($out_datetime);
        if ($diff > 0) $total_hours = round($diff / 3600, 2);
    }

    $stmt = $conn->prepare("UPDATE vehicle_applications SET
        out_datetime = ?, in_datetime = ?, km_out = ?, km_in = ?,
        security_guard_name = ?, total_km = ?, total_hours = ?
        WHERE id = ?");
    $stmt->bind_param("ssddsddi", $out_datetime, $in_datetime, $km_out, $km_in, $security_guard_name, $total_km, $total_hours, $id);
    $stmt->execute();
    $stmt->close();
    echo "<script>alert('Security data updated successfully!'); window.location.href='';</script>";
}

$res = $conn->query("
  SELECT va.*, u.name AS forwarded_admin_name, u.designation AS forwarded_designation
  FROM vehicle_applications va
  LEFT JOIN users u ON va.forwarded_to=u.id
  ORDER BY va.created_at DESC
");
if (!$res) die("Query Failed: " . $conn->error);

$drivers  = $conn->query("SELECT * FROM drivers ORDER BY driver_name ASC");
$vehicles = $conn->query("SELECT * FROM vehicles ORDER BY vehicle_name ASC");

$assigned_drivers  = [];
$assigned_vehicles = [];
$assigned_query = $conn->query("SELECT driver_id, vehicle_id FROM vehicle_applications WHERE status = 'Departure'");
while ($row = $assigned_query->fetch_assoc()) {
    if ($row['driver_id'])  $assigned_drivers[]  = (int) $row['driver_id'];
    if ($row['vehicle_id']) $assigned_vehicles[] = (int) $row['vehicle_id'];
}
$assigned_drivers  = array_unique($assigned_drivers);
$assigned_vehicles = array_unique($assigned_vehicles);

$driverById = [];
$driverOptions = [];
while ($d = $drivers->fetch_assoc()) {
    $driverById[(int) $d['id']] = $d;
    $driverOptions[] = [
        'id'       => (int) $d['id'],
        'label'    => $d['driver_name'] . ' (' . $d['driver_mob'] . ')',
        'locked'   => in_array((int) $d['id'], $assigned_drivers, true),
        'inactive' => (($d['status'] ?? 'Active') === 'Inactive'),
    ];
}
$vehicleById = [];
$vehicleOptions = [];
while ($v = $vehicles->fetch_assoc()) {
    $vehicleById[(int) $v['id']] = $v;
    $vehicleOptions[] = [
        'id'       => (int) $v['id'],
        'label'    => $v['vehicle_type'] . ' — ' . $v['vehicle_name'] . ' (' . $v['vehicle_no'] . ')',
        'locked'   => in_array((int) $v['id'], $assigned_vehicles, true),
        'inactive' => (($v['status'] ?? 'Active') === 'Inactive'),
    ];
}
$drivers->data_seek(0);
$vehicles->data_seek(0);

/* ------------------------------------------------------------------
   SYSTEM TIME — used for all "overdue" calculations.
   The server timestamp is passed to JavaScript but never used for
   determining whether a journey is overdue. The browser's own clock
   is authoritative.
------------------------------------------------------------------ */
$serverTime = time();
$allApplications = [];

while ($row = $res->fetch_assoc()) {
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
    if (empty($totalHours) && !empty($row['out_datetime']) && !empty($row['in_datetime'])) {
        $totalHours = round((strtotime($row['in_datetime']) - strtotime($row['out_datetime'])) / 3600, 2);
    }

    $status    = $row['status'];
    $staffType = $row['staff_type'];

    $showAction = false;
    if ($staffType == "Permanent" && $status != "Approved" && $status != "Rejected") $showAction = true;
    if ($staffType == "Permanent" && $status == "Completed") $showAction = false;
    if ($status == "Approved") $showAction = true;
    if ($status == "Departure") $showAction = false;
    if ($staffType == "Temporary" && $status == "Recommended") $showAction = true;

    $showSecurity = ($status == "Completed");
    $guardDisplay = $row['security_guard_name'] ?? '';

    /* ---- OVERDUE-DEPARTURE CHECK (SYSTEM TIME) ----
       The approved_at timestamp is sent to the browser and compared
       against the user's system clock. JavaScript performs the final
       overdue determination, so no server time dependency exists.
    */
    $allApplications[(int) $row['id']] = [
        'id'                => (int) $row['id'],
        'raw'               => $row,
        'driver'            => $driver,
        'vehicle'           => $vehicle,
        'coTrav'            => $coTrav,
        'forwardedTo'       => $forwardedTo,
        'totalHours'        => $totalHours,
        'status'            => $status,
        'staffType'         => $staffType,
        'showAction'        => $showAction,
        'showSecurity'      => $showSecurity,
        'guardDisplay'      => $guardDisplay,
        'approved_at'       => $row['approved_at'] ?? null,   // raw for JS
        'out_datetime'      => $row['out_datetime'] ?? null,  // raw for JS
        'overdue'           => false,   // placeholder; JS will set
        'overdueReasons'    => [],
        'minsSinceApproval' => null,
    ];
}

/* ------------------------------------------------------------------
   TODAY'S JOURNEYS
------------------------------------------------------------------ */
$todayAssignments = [];
foreach ($allApplications as $app) {
    $r = $app['raw'];
    if (!empty($r['datetime_from']) && date('Y-m-d', strtotime($r['datetime_from'])) === date('Y-m-d')) {
        $todayAssignments[] = $app;
    }
}
usort($todayAssignments, fn($a, $b) => strtotime($a['raw']['datetime_from']) <=> strtotime($b['raw']['datetime_from']));

$todayCounts = ['Pending' => 0, 'Recommended' => 0, 'Approved' => 0, 'Departure' => 0, 'Completed' => 0, 'Other' => 0];

$applicationsByIdForJs = [];
foreach ($allApplications as $app) {
    $r = $app['raw'];
    $driver  = $app['driver'];
    $vehicle = $app['vehicle'];
    $applicationsByIdForJs[$app['id']] = [
        'id'          => $app['id'],
        'bookingFor'  => $r['booking_for'] ?? '',
        'otherPerson' => $r['other_person_name'] ?? '',
        'name'        => $r['applicant_name'] ?? '',
        'mobile'      => $r['mobile'] ?? '',
        'email'       => $r['email'] ?? '',
        'staffType'   => $app['staffType'],
        'from'        => !empty($r['datetime_from']) ? date('d/m/Y H:i', strtotime($r['datetime_from'])) : '',
        'to'          => !empty($r['datetime_to'])   ? date('d/m/Y H:i', strtotime($r['datetime_to']))   : '',
        'place'       => $r['place_of_visit'] ?? '',
        'purpose'     => $r['purpose'] ?? '',
        'tour'        => ($r['tour_approved'] == 1) ? 'Attached' : 'N/A',
        'source'      => $r['source'] ?? 'ICMR-NIHR Jodhpur',
        'forwarded'   => $app['forwardedTo'],
        'cotraveler'  => $app['coTrav'],
        'driver'      => $driver['driver_name'] ?? '',
        'driverMob'   => $driver['driver_mob'] ?? '',
        'vehicleType' => $vehicle['vehicle_type'] ?? '',
        'vehicleName' => $vehicle['vehicle_name'] ?? '',
        'vehicleNo'   => $vehicle['vehicle_no'] ?? '',
        'outDt'       => !empty($r['out_datetime']) ? date('Y-m-d H:i', strtotime($r['out_datetime'])) : '',
        'inDt'        => !empty($r['in_datetime'])  ? date('Y-m-d H:i', strtotime($r['in_datetime']))  : '',
        'kmOut'       => $r['km_out'] ?? '',
        'kmIn'        => $r['km_in'] ?? '',
        'totalKm'     => $r['total_km'] ?? '',
        'totalHours'  => $app['totalHours'] ?? '',
        'security'    => $app['guardDisplay'],
        'remark'      => $r['remark'] ?? '',
        'status'      => $app['status'],
        'submitted'   => $r['created_at'] ?? '',
        'approved_at' => $app['approved_at'],
        'out_datetime_raw' => $app['out_datetime'],
        // overdue fields will be filled by JS
        'overdue'     => false,
        'overdueReasons' => [],
    ];
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

<style id="premium-theme-overrides">
:root{
  --shadow-soft: 0 2px 12px rgba(15,23,42,.07), 0 1px 3px rgba(15,23,42,.05);
  --shadow-lift: 0 12px 32px rgba(15,23,42,.13), 0 3px 10px rgba(15,23,42,.06);
  --shadow-premium: 0 6px 28px rgba(30,41,59,.10), 0 1px 4px rgba(30,41,59,.06);
  --radius-lg: 18px;
  --radius-md: 14px;
  --radius-sm: 10px;
  --gold-accent: #c9a227;
  --deep-navy: #0f172a;
  --royal-blue: #1e3a8a;
}

body{
  background: linear-gradient(160deg, #f4f6fb 0%, #e8ecf6 50%, #eef1f8 100%);
  font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
  font-weight: 500;
  letter-spacing: -0.005em;
  color: #1e293b;
}

.appl-name{ font-weight: 700 !important; font-size: 13.5px !important; color: #0f172a !important; letter-spacing: -0.01em; }
.appl-sub{ font-weight: 500 !important; font-size: 12px !important; color: #475569 !important; }
.jval{ font-weight: 600 !important; color: #1e293b !important; }
.jlabel{ font-weight: 700 !important; color: #64748b !important; }
.assign-name{ font-weight: 700 !important; color: #0f172a !important; }
.assign-detail{ font-weight: 500 !important; color: #475569 !important; }
.sec-val{ font-weight: 600 !important; color: #1e293b !important; }
.sec-label{ font-weight: 700 !important; color: #64748b !important; }
.pill{ font-weight: 700 !important; letter-spacing: .02em; }
.sbadge{ font-weight: 700 !important; letter-spacing: .02em; }
.cell-id{ font-weight: 700 !important; color: #1e3a8a !important; }
.tbl-btn{ font-weight: 600 !important; }
.abtn{ font-weight: 700 !important; letter-spacing: .01em; }

.topbar{
  backdrop-filter: saturate(200%) blur(20px);
  box-shadow: 0 1px 0 rgba(15,23,42,.06), var(--shadow-soft);
  background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(255,255,255,.90)) !important;
  border-bottom: 1px solid rgba(15,23,42,.05);
}

.toolbar-card, .table-card, .filter-panel, .active-assign-card{
  border-radius: var(--radius-lg) !important;
  box-shadow: var(--shadow-premium) !important;
  border: 1px solid rgba(15,23,42,.05) !important;
  background: #fff !important;
}

.abtn{
  border-radius: var(--radius-sm) !important;
  font-weight: 700 !important;
  letter-spacing: .015em;
  transition: all .18s cubic-bezier(.4,0,.2,1);
  box-shadow: 0 1px 3px rgba(15,23,42,.08);
}
.abtn:hover{ transform: translateY(-2px); box-shadow: var(--shadow-lift); }
.abtn:active{ transform: translateY(0); }

.tbl-btn{
  border-radius: 9px !important;
  font-weight: 600;
  transition: all .15s ease;
  box-shadow: 0 1px 2px rgba(15,23,42,.06);
}
.tbl-btn:hover{ transform: translateY(-1px); box-shadow: var(--shadow-soft); }

#mainTable thead th{
  font-weight: 700 !important;
  letter-spacing: .03em;
  text-transform: uppercase;
  font-size: 11px;
  color: #475569 !important;
  background: linear-gradient(180deg, #f8fafc, #f1f5f9) !important;
  border-bottom: 2px solid #e2e8f0 !important;
}
#mainTable tbody tr{ transition: background .15s ease; }
#mainTable tbody tr:hover{ background: #f8fafc !important; }

.active-assign-item{
  border-radius: var(--radius-md) !important;
  box-shadow: var(--shadow-premium);
  transition: all .2s cubic-bezier(.4,0,.2,1);
  background: #fff;
  border: 1px solid rgba(15,23,42,.06);
}
.active-assign-item:hover{ transform: translateY(-3px); box-shadow: var(--shadow-lift); }

.modal-content{
  border-radius: var(--radius-lg) !important;
  box-shadow: 0 24px 60px rgba(15,23,42,.22), 0 4px 16px rgba(15,23,42,.10) !important;
  border: none !important;
}
.modal-header{
  border-bottom: 1px solid #eef0f5;
  background: linear-gradient(180deg, #fbfcfe, #f6f8fc);
  border-radius: var(--radius-lg) var(--radius-lg) 0 0;
}
.modal-title{ font-weight: 700 !important; letter-spacing: -.01em; }

.pill{ border-radius: 999px !important; font-weight: 700; padding: 3px 10px; font-size: 10.5px; letter-spacing: .02em; }

.overdue-banner{
  display: flex; align-items: flex-start; gap: 8px;
  background: linear-gradient(90deg, #fef2f2, #fff7ed);
  border: 1px solid #fecaca; color: #991b1b;
  border-radius: 10px; padding: 9px 12px; margin-top: 10px;
  font-size: 11.5px; font-weight: 700; line-height: 1.45;
  box-shadow: 0 1px 3px rgba(239,68,68,.10);
}
.overdue-banner i{
  color: #dc2626; font-size: 14px; margin-top: 1px;
  animation: pulseWarn 1.8s ease-in-out infinite;
  flex: 0 0 auto;
}
@keyframes pulseWarn{ 0%,100%{opacity:1;} 50%{opacity:.45;} }

.active-assign-item.aa-overdue{
  border-left-width: 4px !important;
  border-left-color: #dc2626 !important;
  background: linear-gradient(180deg, #ffffff, #fef7f7);
  animation: overdueGlow 2.6s ease-in-out infinite;
}
@keyframes overdueGlow{
  0%,100%{ box-shadow: var(--shadow-premium); }
  50%{ box-shadow: 0 0 0 3px rgba(220,38,38,.16), var(--shadow-lift); }
}

tr.row-overdue{ background: linear-gradient(90deg, #fef7f7, #ffffff) !important; }
tr.row-overdue td:first-child{ box-shadow: inset 4px 0 0 #dc2626; }

.overdue-chip{
  display: inline-flex; align-items: center; gap: 4px;
  background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;
  font-size: 10px; font-weight: 700; padding: 3px 9px; border-radius: 999px;
  margin-top: 6px; white-space: nowrap;
  box-shadow: 0 1px 2px rgba(220,38,38,.08);
}
.overdue-chip i{ animation: pulseWarn 1.8s infinite; }

.status-chip.chip-overdue{
  background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;
  font-weight: 700;
}

.aa-card-actions{
  display: flex; gap: 7px; flex-wrap: wrap;
  margin-top: 12px; padding-top: 11px;
  border-top: 1px dashed #e2e8f0;
}
.aa-card-actions .tbl-btn{ font-size: 11px; padding: 5px 10px; }

.jrow{ line-height: 1.55; }
.sec-row{ line-height: 1.6; }
.assign-block{ line-height: 1.5; }

.badge-km, .badge-hrs{ font-weight: 700 !important; letter-spacing: .01em; }

@media (max-width:1200px){ .active-assign-list{ grid-template-columns:repeat(auto-fill,minmax(230px,1fr)) !important; } }
@media (max-width:992px){
  .page-header{ flex-direction:column; align-items:flex-start !important; gap:12px; }
  .toolbar-card{ flex-wrap:wrap; }
  .btn-group-actions{ width:100%; justify-content:flex-start; }
}
@media (max-width:768px){
  .page-wrap{ padding-left:12px !important; padding-right:12px !important; }
  .toolbar-card{ flex-direction:column; align-items:stretch !important; }
  .search-wrap{ width:100% !important; }
  .btn-group-actions{ flex-direction:column; }
  .btn-group-actions .abtn{ width:100%; justify-content:center; }
  .filter-panel{ grid-template-columns:1fr !important; }
  .active-assign-list{ grid-template-columns:1fr !important; }

  #mainTable thead{ display:none; }
  #mainTable, #mainTable tbody, #mainTable tr, #mainTable td{ display:block; width:100%; }
  #mainTable tr{
    background:#fff; border-radius:var(--radius-md); box-shadow:var(--shadow-soft);
    margin-bottom:14px; padding:10px 12px; border:1px solid #eef0f5;
  }
  #mainTable td{
    display:flex; align-items:flex-start; gap:10px;
    border:none !important; padding:8px 2px !important;
    border-bottom:1px dashed #eef0f5 !important;
  }
  #mainTable td:last-child{ border-bottom:none !important; }
  #mainTable td::before{
    content: attr(data-label);
    flex:0 0 112px; font-weight:700; color:#64748b; font-size:11px;
    text-transform:uppercase; letter-spacing:.03em; padding-top:2px;
  }
  #mainTable td[data-label="Actions"]{ justify-content:center; flex-wrap:wrap; }
  #mainTable td[data-label="Actions"]::before{ display:none; }
}
@media (max-width:576px){
  .page-title{ font-size:20px !important; }
  .modal-dialog{ margin:8px !important; max-width:calc(100% - 16px) !important; }
  .abtn span{ display:none; }
  .abtn{ padding:8px 10px !important; }
}
</style>
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
    <div class="admin-chip">
      <div class="avi">SA</div>
      <span>Super Admin</span>
    </div>
    <a href="logout.php" class="btn-logout"><i class="bi bi-box-arrow-right"></i> <span>Logout</span></a>
  </div>
</div>

<div class="page-wrap">

  <div class="page-header">
    <div>
      <div class="page-title">Vehicle <span>Applications</span></div>
      <div class="page-sub">Manage, approve and track all vehicle requests</div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
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

  <?php if (!empty($todayAssignments)): ?>
  <div class="active-assign-card">
    <h6>
      <i class="bi bi-calendar-day-fill" style="color:#14b8a6;font-size:16px;"></i>
      Today's Journeys — <?=date('d M Y')?>
      <span class="count-badge"><?=count($todayAssignments)?> total</span>
      <span class="status-chip chip-overdue" id="todayOverdueCount" style="display:none;">
        <i class="bi bi-exclamation-triangle-fill"></i> <span id="overdueCountNum">0</span> Overdue
      </span>
      <?php if ($todayCounts['Departure'] > 0): ?>
        <span class="status-chip chip-departure">
          <i class="bi bi-arrow-right-circle-fill"></i> <?=$todayCounts['Departure']?> Departure
        </span>
      <?php endif; ?>
      <?php if ($todayCounts['Approved'] > 0): ?>
        <span class="status-chip chip-approved">
          <i class="bi bi-check-circle-fill"></i> <?=$todayCounts['Approved']?> Approved
        </span>
      <?php endif; ?>
      <?php if ($todayCounts['Completed'] > 0): ?>
        <span class="status-chip chip-completed">
          <i class="bi bi-flag-fill"></i> <?=$todayCounts['Completed']?> Completed
        </span>
      <?php endif; ?>
      <?php if (($todayCounts['Pending'] + $todayCounts['Recommended']) > 0): ?>
        <span class="status-chip chip-pending">
          <i class="bi bi-hourglass-split"></i> <?=$todayCounts['Pending'] + $todayCounts['Recommended']?> Pending
        </span>
      <?php endif; ?>
    </h6>

    <div class="active-assign-list" id="todayJourneyList">
      <?php foreach ($todayAssignments as $app):
        $a       = $app['raw'];
        $driver  = $app['driver'];
        $vehicle = $app['vehicle'];
        $s       = $app['status'];
        $sBadgeMap = [
          'Pending'         => ['sb-pending',    'hourglass-split',           '#f59e0b'],
          'Recommended'     => ['sb-recom',      'hand-thumbs-up-fill',       '#3b82f6'],
          'Not Recommended' => ['sb-notrecom',   'hand-thumbs-down-fill',     '#ef4444'],
          'Approved'        => ['sb-approved',   'check-circle-fill',         '#10b981'],
          'Rejected'        => ['sb-rejected',   'x-circle-fill',             '#ef4444'],
          'Completed'       => ['sb-completed',  'flag-fill',                 '#8b5cf6'],
          'Cancelled'       => ['sb-cancelled',  'slash-circle-fill',         '#6b7280'],
          'Departure'       => ['sb-departure',  'arrow-right-circle-fill',   '#14b8a6'],
        ];
        [$cls, $icon, $accent] = $sBadgeMap[$s] ?? ['sb-pending', 'info-circle', '#94a3b8'];
        $cardClasses = 'active-assign-item';
      ?>
      <div class="<?=$cardClasses?>" style="border-left-color: <?=$accent?>;" data-id="<?=$app['id']?>" 
           data-approved-at="<?=htmlspecialchars($app['approved_at'] ?? '')?>"
           data-out-datetime="<?=htmlspecialchars($app['out_datetime'] ?? '')?>"
           data-status="<?=htmlspecialchars($s)?>">
        <div class="aa-head">
          <span class="aa-id">#<?=$app['id']?></span>
          <span class="sbadge <?=$cls?>" style="font-size:10.5px;">
            <i class="bi bi-<?=$icon?>"></i> <?=htmlspecialchars($s)?>
          </span>
        </div>
        <div class="aa-name"><?=htmlspecialchars($a['applicant_name'])?></div>

        <?php if ($driver): ?>
        <div class="aa-row">
          <i class="bi bi-person-badge-fill"></i>
          <strong><?=htmlspecialchars($driver['driver_name'])?></strong>
          <span style="color:#94a3b8;">·</span>
          <span><?=htmlspecialchars($driver['driver_mob'])?></span>
        </div>
        <?php else: ?>
        <div class="aa-row" style="color:#94a3b8;font-style:italic;">
          <i class="bi bi-person-dash"></i> Driver not assigned
        </div>
        <?php endif; ?>

        <?php if ($vehicle): ?>
        <div class="aa-row">
          <i class="bi bi-truck"></i>
          <strong><?=htmlspecialchars($vehicle['vehicle_type'])?> — <?=htmlspecialchars($vehicle['vehicle_name'])?></strong>
          <span style="color:#94a3b8;">·</span>
          <span><?=htmlspecialchars($vehicle['vehicle_no'])?></span>
        </div>
        <?php else: ?>
        <div class="aa-row" style="color:#94a3b8;font-style:italic;">
          <i class="bi bi-truck"></i> Vehicle not assigned
        </div>
        <?php endif; ?>

        <div class="aa-row">
          <i class="bi bi-geo-alt-fill" style="color:#f97316;"></i>
          <span><?=htmlspecialchars($a['place_of_visit'])?></span>
        </div>

        <div class="aa-row">
          <i class="bi bi-clock-fill" style="color:#3b5cf6;"></i>
          <span style="font-size:11.5px;">
            <strong><?=date('H:i', strtotime($a['datetime_from']))?></strong>
            → <?=date('H:i', strtotime($a['datetime_to']))?>
          </span>
        </div>

        <?php if (!empty($a['out_datetime'])): ?>
        <div class="aa-row">
          <i class="bi bi-box-arrow-right" style="color:#f59e0b;"></i>
          <span>Out: <strong><?=date('H:i', strtotime($a['out_datetime']))?></strong></span>
          <?php if (!empty($a['km_out'])): ?>
            <span style="color:#94a3b8;">·</span>
            <span><?=number_format($a['km_out'], 0)?> km</span>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="overdue-banner" style="display:none;">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <span class="overdue-message"></span>
        </div>

        <div class="aa-card-actions">
          <?php if ($app['showAction']): ?>
          <button type="button" class="tbl-btn tbl-btn-action btnAction" data-id="<?=$app['id']?>">
            <i class="bi bi-pencil-square"></i> Action
          </button>
          <?php endif; ?>
          <?php if ($app['showSecurity']): ?>
          <button type="button" class="tbl-btn tbl-btn-security btnSecurity" data-id="<?=$app['id']?>">
            <i class="bi bi-shield-lock"></i> Security
          </button>
          <?php endif; ?>
          <button type="button" class="tbl-btn tbl-btn-pdf exportRow" data-id="<?=$app['id']?>" title="Export PDF">
            <i class="bi bi-file-earmark-pdf"></i> Export
          </button>
          <button type="button" class="tbl-btn tbl-btn-copy copyRow" data-id="<?=$app['id']?>" title="Copy for WhatsApp">
            <i class="bi bi-whatsapp" style="color:#25D366"></i> Copy
          </button>
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
      <i class="bi bi-sliders"></i>
      Filters
      <span class="filter-badge" id="filterBadge">0</span>
    </button>

    <div class="toolbar-sep"></div>

    <div class="btn-group-actions">
      <button class="abtn abtn-outline" id="exportAllPDF">
        <i class="bi bi-file-earmark-pdf-fill" style="color:var(--red)"></i><span>PDF All</span>
      </button>
      <button class="abtn abtn-amber" id="exportAllCompletedPDF">
        <i class="bi bi-file-earmark-check-fill"></i><span>PDF Completed</span>
      </button>
      <button class="abtn abtn-outline" id="exportAllCSV">
        <i class="bi bi-filetype-csv" style="color:var(--green)"></i><span>Export CSV</span>
      </button>
    </div>
  </div>

  <div class="filter-panel" id="filterPanel">
    <div class="flt-group">
      <label>Status</label>
      <select id="fStatus" class="ui-select">
        <option value="">All statuses</option>
        <option>Pending</option><option>Recommended</option><option>Not Recommended</option>
        <option>Approved</option><option>Rejected</option><option>Completed</option>
        <option>Cancelled</option><option>Departure</option>
      </select>
    </div>
    <div class="flt-group">
      <label>Staff Type</label>
      <select id="fStaffType" class="ui-select">
        <option value="">All types</option>
        <option>Permanent</option><option>Temporary</option>
      </select>
    </div>
    <div class="flt-group">
      <label>Booking For</label>
      <select id="fBookingFor" class="ui-select">
        <option value="">All</option>
        <option>Self</option><option>Someone</option>
      </select>
    </div>
    <div class="flt-group">
      <label>Journey From</label>
      <input type="date" id="fDateFrom">
    </div>
    <div class="flt-group">
      <label>Journey To</label>
      <input type="date" id="fDateTo">
    </div>
    <button class="btn-clear-filters" id="clearFilters">
      <i class="bi bi-x-circle"></i> Clear all
    </button>
  </div>

  <div class="table-card">
    <div class="table-scroll">
      <table id="mainTable">
        <thead>
          <tr>
            <th data-col="0" class="sortable">#<span class="sort-ico"></span></th>
            <th data-col="1" class="sortable">Applicant<span class="sort-ico"></span></th>
            <th data-col="2" class="sortable">Journey Details<span class="sort-ico"></span></th>
            <th data-col="3">Assignment</th>
            <th data-col="4">Security Movement</th>
            <th data-col="5" class="sortable">Status<span class="sort-ico"></span></th>
            <th style="text-align:center;white-space:nowrap">Actions</th>
          </tr>
        </thead>
        <tbody id="tableBody">
<?php
if (!empty($allApplications)) {
  foreach ($allApplications as $app) {
    $row     = $app['raw'];
    $driver  = $app['driver'];
    $vehicle = $app['vehicle'];
    $coTrav  = $app['coTrav'];
    $forwardedTo = $app['forwardedTo'];
    $totalHours  = $app['totalHours'];
    $status      = $app['status'];
    $staffType   = $app['staffType'];
    $showAction  = $app['showAction'];
    $showSecurity = $app['showSecurity'];
    $guardDisplay = $app['guardDisplay'];

    $sMap = [
      'Pending'          => ['sb-pending', 'hourglass-split'],
      'Recommended'      => ['sb-recom', 'hand-thumbs-up-fill'],
      'Not Recommended'  => ['sb-notrecom', 'hand-thumbs-down-fill'],
      'Approved'         => ['sb-approved', 'check-circle-fill'],
      'Rejected'         => ['sb-rejected', 'x-circle-fill'],
      'Completed'        => ['sb-completed', 'flag-fill'],
      'Cancelled'        => ['sb-cancelled', 'slash-circle-fill'],
      'Departure'        => ['sb-departure', 'arrow-right-circle-fill'],
    ];
    [$sbClass, $sbIcon] = $sMap[$status] ?? ['sb-pending', 'info-circle'];

    $outDtFmt = !empty($row['out_datetime']) ? date('Y-m-d H:i', strtotime($row['out_datetime'])) : '';
    $inDtFmt  = !empty($row['in_datetime'])  ? date('Y-m-d H:i', strtotime($row['in_datetime']))  : '';
?>
<tr
  class=""
  data-id="<?=$row['id']?>"
  data-status="<?=htmlspecialchars($status)?>"
  data-staff-type="<?=htmlspecialchars($staffType)?>"
  data-booking-for="<?=htmlspecialchars($row['booking_for'])?>"
  data-date="<?=strtotime($row['datetime_from'])?>"
  data-cotraveler="<?=htmlspecialchars($coTrav)?>"
  data-forwarded="<?=htmlspecialchars($forwardedTo)?>"
  data-remark="<?=htmlspecialchars($row['remark'] ?? '')?>"
  data-submitted="<?=htmlspecialchars($row['created_at'])?>"
  data-out-datetime="<?=htmlspecialchars($outDtFmt)?>"
  data-in-datetime="<?=htmlspecialchars($inDtFmt)?>"
  data-km-out="<?=htmlspecialchars($row['km_out'] ?? '')?>"
  data-km-in="<?=htmlspecialchars($row['km_in'] ?? '')?>"
  data-total-km="<?=htmlspecialchars($row['total_km'] ?? '')?>"
  data-total-hours="<?=htmlspecialchars($totalHours ?? '')?>"
  data-security-guard="<?=htmlspecialchars($guardDisplay)?>"
  data-approved-at="<?=htmlspecialchars($row['approved_at'] ?? '')?>"
  data-out-datetime-raw="<?=htmlspecialchars($row['out_datetime'] ?? '')?>">

  <td data-label="ID"><span class="cell-id">#<?=$row['id']?></span></td>

  <td data-label="Applicant"
    data-name="<?=htmlspecialchars($row['applicant_name'])?>"
    data-mobile="<?=htmlspecialchars($row['mobile'])?>"
    data-email="<?=htmlspecialchars($row['email'])?>"
    data-booking-for="<?=htmlspecialchars($row['booking_for'])?>"
    data-other-person="<?=htmlspecialchars($row['other_person_name'] ?? '')?>"
    data-staff-type="<?=htmlspecialchars($staffType)?>">
    <div style="display:flex;flex-direction:column;gap:5px;min-width:160px">
      <div style="display:flex;gap:5px;flex-wrap:wrap">
        <span class="pill pill-slate"><?=htmlspecialchars($row['booking_for'])?></span>
        <span class="pill <?=$staffType == 'Permanent' ? 'pill-blue' : 'pill-amber'?>"><?=$staffType?></span>
      </div>
      <?php if ($row['booking_for'] == "Someone" && !empty($row['other_person_name'])): ?>
        <div class="appl-name"><?=htmlspecialchars($row['other_person_name'])?></div>
        <div class="appl-sub"><i class="bi bi-person-fill"></i> <?=htmlspecialchars($row['applicant_name'])?></div>
      <?php else: ?>
        <div class="appl-name"><?=htmlspecialchars($row['applicant_name'])?></div>
      <?php endif; ?>
      <div class="appl-sub" style="margin-top:2px"><i class="bi bi-telephone-fill"></i> <?=htmlspecialchars($row['mobile'])?></div>
      <div class="appl-sub"><i class="bi bi-envelope-fill"></i> <?=htmlspecialchars($row['email'])?></div>
    </div>
  </td>

  <td data-label="Journey Details"
    data-from="<?=htmlspecialchars(date('d/m/Y H:i', strtotime($row['datetime_from'])))?>"
    data-to="<?=htmlspecialchars(date('d/m/Y H:i', strtotime($row['datetime_to'])))?>"
    data-source="<?=htmlspecialchars($row['source'] ?? 'ICMR-NIHR Jodhpur')?>"
    data-place="<?=htmlspecialchars($row['place_of_visit'])?>"
    data-purpose="<?=htmlspecialchars($row['purpose'])?>"
    data-tour="<?=$row['tour_approved'] == 1 ? 'Attached' : 'N/A'?>"
    data-date="<?=strtotime($row['datetime_from'])?>">
    <div class="jcell" style="min-width:210px">
      <div class="jrow">
        <i class="bi bi-file-earmark-check-fill" style="color:var(--blue)"></i>
        <span class="jlabel">Tour</span>
        <?php if ($row['tour_approved'] == 1 && !empty($row['tour_order_pdf'])): ?>
          <a class="tour-view" href="view_tour_pdf.php?file=<?=urlencode($row['tour_order_pdf'])?>" target="_blank"><i class="bi bi-eye-fill"></i> View</a>
        <?php else: ?><span class="tour-na">N/A</span><?php endif; ?>
      </div>
      <div class="jrow"><i class="bi bi-calendar2-event-fill" style="color:var(--blue)"></i><span class="jlabel">From</span><span class="jval"><?=date("d/m/Y H:i", strtotime($row['datetime_from']))?></span></div>
      <div class="jrow"><i class="bi bi-calendar2-check-fill" style="color:var(--green)"></i><span class="jlabel">To</span><span class="jval"><?=date("d/m/Y H:i", strtotime($row['datetime_to']))?></span></div>
      <hr class="jdiv">
      <div class="jrow"><i class="bi bi-share-fill" style="color:var(--ink4)"></i><span class="jlabel">Fwd</span><span class="jval"><?=htmlspecialchars($row['forwarded_admin_name'] ?? 'N/A')?></span></div>
      <div class="jrow"><i class="bi bi-geo-alt" style="color:var(--green)"></i><span class="jlabel">Source</span><span class="jval"><?=htmlspecialchars($row['source'] ?? 'N/A')?></span></div>
      <div class="jrow"><i class="bi bi-geo-alt-fill" style="color:var(--coral)"></i><span class="jlabel">Dest</span><span class="jval"><?=htmlspecialchars($row['place_of_visit'])?></span></div>
      <div class="jrow"><i class="bi bi-chat-left-quote-fill" style="color:var(--ink4)"></i><span class="jlabel">Purpose</span><span class="jval"><?=htmlspecialchars($row['purpose'])?></span></div>
      <?php if (!empty($coTrav)): ?>
      <div class="jrow" style="align-items:flex-start"><i class="bi bi-people-fill" style="color:var(--violet)"></i><span class="jlabel">Co-Trav</span>
        <div style="display:flex;flex-wrap:wrap;gap:3px">
          <?php foreach (explode(", ", $coTrav) as $ct): ?><span class="pill pill-teal" style="font-size:10px"><?=htmlspecialchars($ct)?></span><?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </td>

  <td data-label="Assignment"
    data-driver="<?=htmlspecialchars($driver['driver_name'] ?? '')?>"
    data-driver-mobile="<?=htmlspecialchars($driver['driver_mob'] ?? '')?>"
    data-vehicle-type="<?=htmlspecialchars($vehicle['vehicle_type'] ?? '')?>"
    data-vehicle-name="<?=htmlspecialchars($vehicle['vehicle_name'] ?? '')?>"
    data-vehicle-no="<?=htmlspecialchars($vehicle['vehicle_no'] ?? '')?>">
    <?php if ($driver || $vehicle): ?>
    <div class="assign-block" style="min-width:150px">
      <?php if ($driver): ?>
      <div>
        <div class="assign-section-label"><i class="bi bi-person-badge"></i> Driver</div>
        <div class="assign-name"><?=htmlspecialchars($driver['driver_name'])?></div>
        <div class="assign-detail"><i class="bi bi-telephone"></i><?=htmlspecialchars($driver['driver_mob'])?></div>
      </div>
      <?php endif; ?>
      <?php if ($driver && $vehicle): ?><hr class="assign-divider"><?php endif; ?>
      <?php if ($vehicle): ?>
      <div>
        <div class="assign-section-label"><i class="bi bi-truck"></i> Vehicle</div>
        <div class="assign-name"><?=htmlspecialchars($vehicle['vehicle_type'])?> — <?=htmlspecialchars($vehicle['vehicle_name'])?></div>
        <div class="assign-detail"><i class="bi bi-upc-scan"></i><?=htmlspecialchars($vehicle['vehicle_no'])?></div>
      </div>
      <?php endif; ?>
    </div>
    <?php else: ?><span style="color:var(--ink4);font-size:12.5px;font-style:italic">Not assigned</span><?php endif; ?>
  </td>

  <td data-label="Security Movement">
    <?php if (!empty($row['out_datetime']) || !empty($row['in_datetime']) || !empty($row['km_out']) || !empty($row['security_guard_name'])): ?>
    <div class="sec-block" style="min-width:170px">
      <?php if (!empty($row['out_datetime'])): ?>
      <div class="sec-row"><i class="bi bi-box-arrow-right" style="color:var(--amber)"></i><span class="sec-label">Out</span><span class="sec-val"><?=date('d/m/Y H:i', strtotime($row['out_datetime']))?></span></div>
      <?php endif; ?>
      <?php if (!empty($row['in_datetime'])): ?>
      <div class="sec-row"><i class="bi bi-box-arrow-in-left" style="color:var(--teal)"></i><span class="sec-label">In</span><span class="sec-val"><?=date('d/m/Y H:i', strtotime($row['in_datetime']))?></span></div>
      <?php endif; ?>
      <?php if (!empty($row['km_out'])): ?>
      <div class="sec-row"><i class="bi bi-speedometer2" style="color:var(--green)"></i><span class="sec-label">KM Out</span><span class="sec-val"><?=number_format($row['km_out'], 2)?></span></div>
      <?php endif; ?>
      <?php if (!empty($row['km_in'])): ?>
      <div class="sec-row"><i class="bi bi-speedometer2" style="color:var(--red)"></i><span class="sec-label">KM In</span><span class="sec-val"><?=number_format($row['km_in'], 2)?></span></div>
      <?php endif; ?>
      <?php if (!empty($row['total_km'])): ?>
      <div class="sec-row"><i class="bi bi-graph-up-arrow" style="color:var(--blue)"></i><span class="sec-label">Total</span><span class="badge-km"><?=number_format($row['total_km'], 2)?> km</span></div>
      <?php endif; ?>
      <?php if (!empty($totalHours)): ?>
      <div class="sec-row"><i class="bi bi-clock-history" style="color:var(--violet)"></i><span class="sec-label">Hours</span><span class="badge-hrs"><?=$totalHours?>h</span></div>
      <?php endif; ?>
      <?php if (!empty($guardDisplay)): ?>
      <div class="sec-row"><i class="bi bi-shield-lock-fill" style="color:var(--ink3)"></i><span class="sec-label">Guard / Driver</span><span class="sec-val" style="font-size:11px;"><?=htmlspecialchars($guardDisplay)?></span></div>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="not-completed"><i class="bi bi-dash-circle"></i> Not completed</div>
    <?php endif; ?>
  </td>

  <td data-label="Status">
    <span class="sbadge <?=$sbClass?>"><i class="bi bi-<?=$sbIcon?>"></i><?=$status?></span>
    <div class="overdue-chip" style="display:none;">
      <i class="bi bi-exclamation-triangle-fill"></i> Overdue — consider rejecting
    </div>
  </td>

  <td data-label="Actions" style="text-align:center">
    <div style="display:flex;gap:7px;justify-content:center;flex-wrap:wrap">
      <?php if ($showAction): ?>
      <button type="button" class="tbl-btn tbl-btn-action btnAction" data-id="<?=$row['id']?>">
        <i class="bi bi-pencil-square"></i> Action
      </button>
      <?php endif; ?>
      <?php if ($showSecurity): ?>
      <button type="button" class="tbl-btn tbl-btn-security btnSecurity" data-id="<?=$row['id']?>">
        <i class="bi bi-shield-lock"></i> Security
      </button>
      <?php endif; ?>
      <button class="tbl-btn tbl-btn-pdf exportRow" data-id="<?=$row['id']?>">
        <i class="bi bi-file-earmark-pdf"></i>
      </button>
      <button class="tbl-btn tbl-btn-copy copyRow" data-id="<?=$row['id']?>" title="Copy for WhatsApp">
        <i class="bi bi-whatsapp" style="color:#25D366"></i>
      </button>
    </div>
  </td>
</tr>
<?php
  }
} else {
?>
<tr><td colspan="7"><div class="empty-state"><i class="bi bi-inbox"></i><p>No applications found</p></div></td></tr>
<?php } ?>
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
<div class="modal fade" id="actionModal">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="POST" id="actionForm">
        <div class="modal-header">
          <div class="modal-title"><i class="bi bi-check2-square" style="color:var(--blue)"></i> Review Application #<span id="actionAppIdLabel"></span></div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf_token)?>">
          <input type="hidden" name="app_id" id="actionAppId" value="">

          <div class="mb-3">
            <label class="form-label">Select Driver</label>
            <select name="driver_id" class="form-select" id="actionDriverSelect"></select>
          </div>

          <div class="mb-3">
            <label class="form-label">Select Vehicle</label>
            <select name="vehicle_id" class="form-select" id="actionVehicleSelect"></select>
          </div>

          <div class="mb-0">
            <label class="form-label">Remark</label>
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
<div class="modal fade" id="securityModal">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="POST" class="security-form" id="securityForm">
        <div class="modal-header">
          <div class="modal-title"><i class="bi bi-shield-lock" style="color:var(--teal)"></i> Security Movement #<span id="secAppIdLabel"></span></div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf_token)?>">
          <input type="hidden" name="app_id" id="secAppId" value="">

          <div class="row">
            <div class="col-md-6">
              <div class="sec-input-group">
                <h6 class="text-primary mb-3"><i class="bi bi-box-arrow-right"></i> Departure</h6>
                <div class="mb-3">
                  <label class="sec-label">Date &amp; Time</label>
                  <input type="text" name="out_datetime" class="form-control datetimepicker out-datetime" id="secOutDatetime" placeholder="YYYY-MM-DD HH:MM">
                </div>
                <div class="mb-3">
                  <label class="sec-label">KM Reading</label>
                  <input type="number" step="0.01" name="km_out" class="form-control km-out" id="secKmOut" placeholder="Enter KM reading">
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div class="sec-input-group">
                <h6 class="text-success mb-3"><i class="bi bi-box-arrow-in-left"></i> Return</h6>
                <div class="mb-3">
                  <label class="sec-label">Date &amp; Time</label>
                  <input type="text" name="in_datetime" class="form-control datetimepicker in-datetime" id="secInDatetime" placeholder="YYYY-MM-DD HH:MM">
                </div>
                <div class="mb-3">
                  <label class="sec-label">KM Reading</label>
                  <input type="number" step="0.01" name="km_in" class="form-control km-in" id="secKmIn" placeholder="Enter KM reading">
                </div>
              </div>
            </div>
          </div>

          <div class="row mt-2">
            <div class="col-md-12">
              <div class="sec-input-group">
                <label class="sec-label">Security Guard / Driver Name</label>
                <input type="text" name="security_guard_name" class="form-control" id="secGuardName" placeholder="Enter Security Guard / Driver name">
                <div class="lock-note" id="secGuardLockNote" style="display:none">
                  <i class="bi bi-lock-fill"></i> Guard name is locked once recorded and can no longer be edited here.
                </div>
              </div>
            </div>
          </div>

          <div class="row mt-3">
            <div class="col-md-6">
              <div class="sec-input-group bg-white">
                <label class="sec-label">Total KM (Auto-calculated)</label>
                <input type="text" class="form-control total-km" id="secTotalKm" readonly>
                <small class="text-muted">KM In − KM Out</small>
              </div>
            </div>
            <div class="col-md-6">
              <div class="sec-input-group bg-white">
                <label class="sec-label">Total Hours (Auto-calculated)</label>
                <input type="text" class="form-control total-hours" id="secTotalHours" readonly>
                <small class="text-muted">Return − Departure</small>
              </div>
            </div>
          </div>

          <div class="mt-3 alert alert-info">
            <i class="bi bi-info-circle"></i>
            <strong>Note:</strong> Departure/return times and KM readings can be updated even after completion. Totals are always recalculated automatically and cannot be edited directly.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="abtn abtn-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="update_security" class="abtn" style="background:var(--teal);color:#fff;">
            <i class="bi bi-check-circle-fill"></i> Save Security Data
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ADD DRIVER MODAL -->
<div class="modal fade" id="addDriverModal">
  <div class="modal-dialog modal-dialog-centered" style="max-width:800px;">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title"><i class="bi bi-person-plus-fill" style="color:var(--green)"></i> Manage Drivers</div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="card mb-4">
          <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Drivers List</h5>
          </div>
          <div class="card-body table-responsive">
            <table class="table table-bordered table-hover align-middle">
  <thead class="table-light">
    <tr>
      <th>#</th>
      <th>Driver Name</th>
      <th>Mobile</th>
      <th>Password</th>
      <th>Status</th>
      <th style="min-width:260px;">Action</th>
    </tr>
  </thead>
  <tbody>
    <?php
    $i = 1;
    $drivers->data_seek(0);
    while ($row = $drivers->fetch_assoc()) {
      $isAssigned = in_array((int) $row['id'], $assigned_drivers, true);
      $status = $row['status'] ?? 'Active';
      $statusColor = $status == 'Active' ? 'success' : 'danger';
      $statusText  = $status == 'Active' ? 'Active'  : 'Inactive';
      $hasPwd = !empty($row['password']);
    ?>
    <tr>
      <td><?=$i++?></td>
      <td><?=htmlspecialchars($row['driver_name'])?></td>
      <td><?=htmlspecialchars($row['driver_mob'])?></td>
      <td>
        <?php if ($hasPwd): ?>
          <span class="badge bg-success">Set</span>
        <?php else: ?>
          <span class="badge bg-secondary">Not set</span>
        <?php endif; ?>
      </td>
      <td>
        <span class="badge bg-<?=$statusColor?>"><?=$statusText?></span>
        <?php if ($isAssigned): ?><small class="text-muted d-block">🔒 On Departure</small><?php endif; ?>
      </td>
      <td>
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <!-- Status update -->
          <form method="POST" action="" class="d-inline-flex gap-1 align-items-center m-0">
            <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf_token)?>">
            <input type="hidden" name="driver_id" value="<?=$row['id']?>">
            <select name="status" class="form-select form-select-sm" style="width:auto;">
              <option value="Active"   <?=$status == 'Active'   ? 'selected' : ''?>>Active</option>
              <option value="Inactive" <?=$status == 'Inactive' ? 'selected' : ''?>>Inactive</option>
            </select>
            <button type="submit" name="update_driver_status" class="btn btn-sm btn-primary">Update</button>
          </form>

          <!-- Set / Reset password -->
          <button type="button"
                  class="btn btn-sm btn-warning btnSetPwd"
                  data-id="<?=$row['id']?>"
                  data-name="<?=htmlspecialchars($row['driver_name'], ENT_QUOTES)?>">
            <i class="bi bi-key-fill"></i> <?=$hasPwd ? 'Reset' : 'Set'?> Password
          </button>
        </div>
      </td>
    </tr>
    <?php } if ($drivers->num_rows == 0) { ?>
    <tr><td colspan="6" class="text-center text-muted">No Drivers Found</td></tr>
    <?php } ?>
  </tbody>
</table>
          </div>
        </div>

        <hr>

        <h6 class="mb-3">Add New Driver</h6>
<form method="POST" action="">
  <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf_token)?>">
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
  <div class="mt-2">
    <button type="submit" name="add_driver" class="abtn abtn-green"><i class="bi bi-check-circle-fill"></i> Save Driver</button>
  </div>
</form>
      </div>
      <div class="modal-footer">
        <button type="button" class="abtn abtn-outline" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- ADD VEHICLE MODAL -->
<div class="modal fade" id="addVehicleModal">
  <div class="modal-dialog modal-dialog-centered" style="max-width:800px;">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title"><i class="bi bi-truck" style="color:var(--blue)"></i> Manage Vehicles</div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="card mb-4">
          <div class="card-header bg-success text-white">
            <h5 class="mb-0">Vehicles List</h5>
          </div>
          <div class="card-body table-responsive">
            <table class="table table-bordered table-hover">
              <thead class="table-light">
                <tr><th>#</th><th>Vehicle Type</th><th>Vehicle Name</th><th>Vehicle Number</th><th>Status</th><th>Action</th></tr>
              </thead>
              <tbody>
                <?php
                $i = 1;
                $vehicles->data_seek(0);
                while ($row = $vehicles->fetch_assoc()) {
                  $isAssigned = in_array((int) $row['id'], $assigned_vehicles, true);
                  $status = $row['status'] ?? 'Active';
                  $statusColor = $status == 'Active' ? 'success' : 'danger';
                  $statusText = $status == 'Active' ? 'Active' : 'Inactive';
                ?>
                <tr>
                  <td><?=$i++?></td>
                  <td><?=htmlspecialchars($row['vehicle_type'])?></td>
                  <td><?=htmlspecialchars($row['vehicle_name'])?></td>
                  <td><?=htmlspecialchars($row['vehicle_no'])?></td>
                  <td>
                    <span class="badge bg-<?=$statusColor?>"><?=$statusText?></span>
                    <?php if ($isAssigned): ?><small class="text-muted d-block">🔒 On Departure</small><?php endif; ?>
                  </td>
                  <td>
                    <form method="POST" action="" style="display:inline-flex;gap:5px;align-items:center;">
                      <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf_token)?>">
                      <input type="hidden" name="vehicle_id" value="<?=$row['id']?>">
                      <select name="status" class="form-select form-select-sm" style="width:auto;display:inline-block;">
                        <option value="Active" <?=$status == 'Active' ? 'selected' : ''?>>Active</option>
                        <option value="Inactive" <?=$status == 'Inactive' ? 'selected' : ''?>>Inactive</option>
                      </select>
                      <button type="submit" name="update_vehicle_status" class="btn btn-sm btn-primary">Update</button>
                    </form>
                  </td>
                </tr>
                <?php } if ($vehicles->num_rows == 0) { ?>
                <tr><td colspan="6" class="text-center text-muted">No Vehicles Found</td></tr>
                <?php } ?>
              </tbody>
            </table>
          </div>
        </div>

        <hr>

        <h6 class="mb-3">Add New Vehicle</h6>
        <form method="POST" action="">
          <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf_token)?>">
          <div class="mb-3">
            <label class="form-label">Vehicle Type</label>
            <select name="vehicle_type" class="form-select" required>
              <option>Car</option>
              <option>Bus</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Vehicle Name</label>
            <input type="text" name="vehicle_name" class="form-control" placeholder="e.g. Toyota Innova" required>
          </div>
          <div class="mb-0">
            <label class="form-label">Registration Number</label>
            <input type="text" name="vehicle_no" class="form-control" placeholder="e.g. MH 12 AB 1234" required>
          </div>
          <div class="mt-3">
            <button type="submit" name="add_vehicle" class="abtn abtn-blue"><i class="bi bi-check-circle-fill"></i> Save Vehicle</button>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="abtn abtn-outline" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- SET / RESET DRIVER PASSWORD MODAL -->
<div class="modal fade" id="setDriverPwdModal">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="">
        <div class="modal-header">
          <div class="modal-title">
            <i class="bi bi-key-fill" style="color:#f59e0b"></i>
            Set Password for <span id="setPwdDriverName"></span>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf_token)?>">
          <input type="hidden" name="driver_id" id="setPwdDriverId" value="">
          <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" minlength="6" placeholder="Min. 6 characters" required>
          </div>
          <div class="mb-0">
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="new_password_confirm" class="form-control" minlength="6" placeholder="Re-enter password" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="abtn abtn-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="set_driver_password" class="abtn" style="background:#f59e0b;color:#fff;">
            <i class="bi bi-check-circle-fill"></i> Save Password
          </button>
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
   SYSTEM TIME–BASED OVERDUE DETECTION
   ============================================================
   The "approved but not departed within 30 minutes" condition is
   evaluated entirely against the client's system clock. No server
   time is used anywhere in this logic.
   ============================================================ */
function computeOverdue(appData) {
  const status = (appData.status || '').trim();
  const outRaw = appData.out_datetime_raw;
  const approvedAt = appData.approved_at;

  // Only flag Approved applications that have NOT departed yet
  if (status !== 'Approved') return { overdue: false, reasons: [], mins: null };
  if (outRaw) return { overdue: false, reasons: [], mins: null };        // already departed
  if (!approvedAt) return { overdue: false, reasons: [], mins: null };   // no approval timestamp

  const approvedTime = new Date(approvedAt.replace(' ', 'T')).getTime();
  if (isNaN(approvedTime)) return { overdue: false, reasons: [], mins: null };

  const now = Date.now();
  const diffMs = now - approvedTime;
  if (diffMs <= 0) return { overdue: false, reasons: [], mins: null };

  const mins = Math.floor(diffMs / 60000);
  if (mins > 30) {
    return {
      overdue: true,
      reasons: [`Approved ${mins} min ago, still not departed`],
      mins: mins
    };
  }
  return { overdue: false, reasons: [], mins: null };
}

/* Driver / vehicle option data */
const driverOptions  = <?=json_encode($driverOptions)?>;
const vehicleOptions = <?=json_encode($vehicleOptions)?>;
const applicationsById = <?=json_encode($applicationsByIdForJs)?>;

/* ---------- APPLY OVERDUE STATE TO CARDS & TABLE ---------- */
function refreshOverdueUI() {
  let overdueCardCount = 0;

  // 1. Cards
  document.querySelectorAll('.active-assign-item').forEach(card => {
    const id = card.dataset.id;
    const app = applicationsById[id];
    if (!app) return;

    const res = computeOverdue({
      status: app.status,
      out_datetime_raw: app.out_datetime_raw || null,
      approved_at: app.approved_at || null
    });

    app.overdue = res.overdue;
    app.overdueReasons = res.reasons;

    const banner = card.querySelector('.overdue-banner');
    const msgSpan = card.querySelector('.overdue-message');
    if (res.overdue) {
      overdueCardCount++;
      card.classList.add('aa-overdue');
      if (banner && msgSpan) {
        banner.style.display = 'flex';
        msgSpan.textContent = res.reasons.join(' · ') + ' — still not departed. Consider rejecting this request.';
      }
    } else {
      card.classList.remove('aa-overdue');
      if (banner) banner.style.display = 'none';
    }
  });

  // update today's overdue count badge
  const badgeWrap = document.getElementById('todayOverdueCount');
  const badgeNum = document.getElementById('overdueCountNum');
  if (badgeWrap && badgeNum) {
    if (overdueCardCount > 0) {
      badgeNum.textContent = overdueCardCount;
      badgeWrap.style.display = '';
    } else {
      badgeWrap.style.display = 'none';
    }
  }

  // 2. Table rows
  document.querySelectorAll('#tableBody tr[data-status]').forEach(tr => {
    const id = tr.dataset.id;
    const app = applicationsById[id];
    if (!app) return;

    const res = computeOverdue({
      status: app.status,
      out_datetime_raw: app.out_datetime_raw || null,
      approved_at: app.approved_at || null
    });

    app.overdue = res.overdue;
    app.overdueReasons = res.reasons;

    if (res.overdue) {
      tr.classList.add('row-overdue');
      const chip = tr.querySelector('.overdue-chip');
      if (chip) {
        chip.style.display = 'inline-flex';
        chip.title = res.reasons.join(' · ');
      }
    } else {
      tr.classList.remove('row-overdue');
      const chip = tr.querySelector('.overdue-chip');
      if (chip) chip.style.display = 'none';
    }
  });

  // Update applicationsById so PDF / CSV / copy reflect current state
  for (const id in applicationsById) {
    const app = applicationsById[id];
    const res = computeOverdue({
      status: app.status,
      out_datetime_raw: app.out_datetime_raw || null,
      approved_at: app.approved_at || null
    });
    app.overdue = res.overdue;
    app.overdueReasons = res.reasons;
  }
}

/* ---------- FLATPICKR SECURITY ---------- */
let secOutFP, secInFP;

document.addEventListener('DOMContentLoaded', function () {
  // Overdue initial pass
  refreshOverdueUI();

  document.querySelectorAll('.btnSetPwd').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById('setPwdDriverId').value = btn.dataset.id;
    document.getElementById('setPwdDriverName').textContent = btn.dataset.name;
    document.querySelector('#setDriverPwdModal input[name="new_password"]').value = '';
    document.querySelector('#setDriverPwdModal input[name="new_password_confirm"]').value = '';
    new bootstrap.Modal(document.getElementById('setDriverPwdModal')).show();
  });
});
  // Re-evaluate every 30 seconds using system time
  setInterval(refreshOverdueUI, 30000);

  secOutFP = flatpickr('#secOutDatetime', { enableTime: true, dateFormat: "Y-m-d H:i", time_24hr: true, minuteIncrement: 1, allowInput: true });
  secInFP  = flatpickr('#secInDatetime',  { enableTime: true, dateFormat: "Y-m-d H:i", time_24hr: true, minuteIncrement: 1, allowInput: true });

  function recalcSecurity() {
    const kmOut = parseFloat(document.getElementById('secKmOut').value) || 0;
    const kmIn  = parseFloat(document.getElementById('secKmIn').value)  || 0;
    const totalKmField = document.getElementById('secTotalKm');
    if (kmIn > kmOut && (document.getElementById('secKmOut').value || document.getElementById('secKmIn').value)) {
      totalKmField.value = (kmIn - kmOut).toFixed(2);
    } else {
      totalKmField.value = '';
    }

    const outVal = document.getElementById('secOutDatetime').value;
    const inVal  = document.getElementById('secInDatetime').value;
    const totalHoursField = document.getElementById('secTotalHours');
    if (outVal && inVal) {
      const d1 = new Date(outVal.replace(' ', 'T'));
      const d2 = new Date(inVal.replace(' ', 'T'));
      if (!isNaN(d1) && !isNaN(d2) && d2 > d1) {
        totalHoursField.value = ((d2 - d1) / 3600000).toFixed(2);
      } else {
        totalHoursField.value = '';
      }
    } else {
      totalHoursField.value = '';
    }
  }
  window.__recalcSecurity = recalcSecurity;

  ['secKmOut', 'secKmIn', 'secOutDatetime', 'secInDatetime'].forEach(id => {
    const el = document.getElementById(id);
    el.addEventListener('input', recalcSecurity);
    el.addEventListener('change', recalcSecurity);
  });

  document.querySelectorAll('.btnAction').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      document.getElementById('actionAppIdLabel').textContent = id;
      document.getElementById('actionAppId').value = id;
      document.getElementById('actionRemark').value = '';
      populateSelect(document.getElementById('actionDriverSelect'), driverOptions);
      populateSelect(document.getElementById('actionVehicleSelect'), vehicleOptions);
      new bootstrap.Modal(document.getElementById('actionModal')).show();
    });
  });

  document.querySelectorAll('.btnSecurity').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      const app = applicationsById[id] || {};

      document.getElementById('secAppIdLabel').textContent = id;
      document.getElementById('secAppId').value = id;

      const outDt = app.outDt || '';
      const inDt  = app.inDt  || '';
      secOutFP.setDate(outDt, true, "Y-m-d H:i");
      secInFP.setDate(inDt, true, "Y-m-d H:i");
      if (!outDt) secOutFP.clear();
      if (!inDt)  secInFP.clear();

      document.getElementById('secKmOut').value = app.kmOut || '';
      document.getElementById('secKmIn').value  = app.kmIn  || '';

      const guardField = document.getElementById('secGuardName');
      const lockNote   = document.getElementById('secGuardLockNote');
      const existingGuard = app.security || '';
      guardField.value = existingGuard;
      if (existingGuard) {
        guardField.setAttribute('readonly', 'readonly');
        lockNote.style.display = 'flex';
      } else {
        guardField.removeAttribute('readonly');
        lockNote.style.display = 'none';
      }

      recalcSecurity();
      new bootstrap.Modal(document.getElementById('securityModal')).show();
    });
  });

});

/* ---------- populateSelect helper ---------- */
function populateSelect(sel, data) {
  sel.innerHTML = '<option value="">— Available —</option>';
  data.forEach(item => {
    const opt = document.createElement('option');
    opt.value = item.id;
    let label = item.label;
    if (item.locked)   label += ' 🔒 Currently on Departure';
    if (item.inactive) label += ' ⛔ Inactive';
    opt.textContent = label;
    if (item.locked || item.inactive) opt.disabled = true;
    sel.appendChild(opt);
  });
}

/* ============================================================
   TABLE ENGINE
   ============================================================ */
(function () {
  const tbody    = document.getElementById("tableBody");
  const searchEl = document.getElementById("globalSearch");
  const rowsEl   = document.getElementById("rowsPerPage");
  const pagiInfo = document.getElementById("pagiInfo");
  const pagiCont = document.getElementById("pagiContainer");
  const fStatus  = document.getElementById("fStatus");
  const fStaff   = document.getElementById("fStaffType");
  const fBook    = document.getElementById("fBookingFor");
  const fFrom    = document.getElementById("fDateFrom");
  const fTo      = document.getElementById("fDateTo");
  const badge    = document.getElementById("filterBadge");
  const panel    = document.getElementById("filterPanel");
  const toggleBtn = document.getElementById("toggleFilters");

  let allRows = Array.from(tbody.querySelectorAll("tr[data-status]"));
  let filtered = [...allRows];
  let page = 1, perPage = 10, sortCol = -1, sortDir = 1;

  toggleBtn.addEventListener("click", () => {
    panel.classList.toggle("open");
    toggleBtn.classList.toggle("active");
  });
  document.getElementById("clearFilters").addEventListener("click", () => {
    fStatus.value = fStaff.value = fBook.value = fFrom.value = fTo.value = "";
    apply();
  });

  document.querySelectorAll("thead th.sortable").forEach(th => {
    th.addEventListener("click", () => {
      const col = parseInt(th.dataset.col);
      if (sortCol === col) sortDir *= -1; else { sortCol = col; sortDir = 1; }
      document.querySelectorAll("thead th").forEach(t => t.classList.remove("sort-asc", "sort-desc"));
      th.classList.add(sortDir === 1 ? "sort-asc" : "sort-desc");
      apply();
    });
  });

  function colVal(tr, col) {
    const tds = tr.querySelectorAll("td");
    switch (col) {
      case 0: return parseInt(tr.querySelector(".cell-id")?.textContent.replace("#", "")) || 0;
      case 1: return (tds[1]?.dataset.name || "").toLowerCase();
      case 2: return parseInt(tds[2]?.dataset.date) || 0;
      case 5: return (tr.dataset.status || "").toLowerCase();
      default: return (tds[col]?.innerText || "").toLowerCase();
    }
  }

  function apply() {
    const kw = (searchEl.value || "").toLowerCase().trim();
    const dfrom = fFrom.value ? new Date(fFrom.value).getTime() / 1000 : null;
    const dto = fTo.value ? new Date(fTo.value).getTime() / 1000 + 86399 : null;
    let active = 0;
    if (fStatus.value) active++; if (fStaff.value) active++; if (fBook.value) active++;
    if (fFrom.value) active++; if (fTo.value) active++;
    badge.textContent = active;
    badge.classList.toggle("show", active > 0);

    filtered = allRows.filter(tr => {
      const td1 = tr.querySelector("td[data-name]");
      if (kw) {
        const n = (td1?.dataset.name || "").toLowerCase();
        const m = (td1?.dataset.mobile || "").toLowerCase();
        const e = (td1?.dataset.email || "").toLowerCase();
        if (!n.includes(kw) && !m.includes(kw) && !e.includes(kw)) return false;
      }
      if (fStatus.value && tr.dataset.status !== fStatus.value) return false;
      if (fStaff.value && tr.dataset.staffType !== fStaff.value) return false;
      if (fBook.value && (td1?.dataset.bookingFor || tr.dataset.bookingFor) !== fBook.value) return false;
      const d = parseInt(tr.dataset.date) || 0;
      if (dfrom && d < dfrom) return false;
      if (dto && d > dto) return false;
      return true;
    });

    if (sortCol >= 0) {
      filtered.sort((a, b) => {
        const va = colVal(a, sortCol), vb = colVal(b, sortCol);
        return (typeof va === "number" ? (va - vb) : va.localeCompare(vb)) * sortDir;
      });
    }
    page = 1; render();
  }

  function render() {
    perPage = parseInt(rowsEl.value);
    const total = filtered.length;
    const pages = Math.ceil(total / perPage) || 1;
    if (page > pages) page = pages;
    const start = (page - 1) * perPage, end = Math.min(start + perPage, total);

    tbody.innerHTML = "";
    if (!filtered.length) {
      tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state"><i class="bi bi-search"></i><p>No records match your filters.</p></div></td></tr>`;
    } else {
      filtered.slice(start, end).forEach(tr => tbody.appendChild(tr));
    }

    pagiInfo.textContent = total ? `Showing ${start + 1}–${end} of ${total} records` : "No records";
    buildPagi(pages);
  }

  function buildPagi(pages) {
    pagiCont.innerHTML = "";
    const mkBtn = (lbl, p, cls = "") => {
      const b = document.createElement("button");
      b.className = "pg-btn" + (cls ? " " + cls : "");
      b.innerHTML = lbl;
      b.addEventListener("click", () => { page = p; render(); });
      pagiCont.appendChild(b);
    };
    mkBtn('<i class="bi bi-chevron-double-left"></i>', 1);
    mkBtn('<i class="bi bi-chevron-left"></i>', Math.max(1, page - 1));
    const lo = Math.max(1, page - 2), hi = Math.min(pages, page + 2);
    if (lo > 1) { const s = document.createElement("span"); s.className = "pg-ellipsis"; s.textContent = "…"; pagiCont.appendChild(s); }
    for (let i = lo; i <= hi; i++) mkBtn(i, i, i === page ? "active" : "");
    if (hi < pages) { const s = document.createElement("span"); s.className = "pg-ellipsis"; s.textContent = "…"; pagiCont.appendChild(s); }
    mkBtn('<i class="bi bi-chevron-right"></i>', Math.min(pages, page + 1));
    mkBtn('<i class="bi bi-chevron-double-right"></i>', pages);
  }

  searchEl.addEventListener("input", apply);
  rowsEl.addEventListener("change", () => { page = 1; render(); });
  [fStatus, fStaff, fBook, fFrom, fTo].forEach(el => el.addEventListener("change", apply));
  apply();

  window.__getFiltered = () => filtered;
})();

/* ============================================================
   EXPORT UTILITIES
   ============================================================ */
const showLoad = msg => { document.getElementById("exportMsg").textContent = msg; document.getElementById("exportOverlay").classList.add("show"); };
const hideLoad = () => document.getElementById("exportOverlay").classList.remove("show");

function rowData(id) {
  return applicationsById[id] || {};
}

/* ---------- WHATSAPP COPY ---------- */
function buildWhatsAppMessage(d) {
  const line = (label, val) =>
    val !== undefined && val !== null && String(val).trim() !== ''
      ? `*${label}:* ${val}\n`
      : '';

  let msg = '';
  msg += `🚐 *APPLICATION #${d.id}*  |  *${d.status || 'Pending'}*\n`;
  msg += `━━━━━━━━━━━━━━━━━━\n\n`;

  msg += `👤 *APPLICANT DETAILS*\n`;
  if (d.bookingFor) msg += line('Booking For', d.bookingFor);
  if (d.otherPerson) msg += line('For', d.otherPerson);
  if (d.name || d.mobile) {
    msg += `*Name:* ${d.name || ''}${d.mobile ? ` (${d.mobile})` : ''}\n`;
  }

  msg += `\n🗺️ *JOURNEY DETAILS*\n`;
  if (d.from || d.to) {
    msg += `*Journey:* ${d.from || ''}${d.to ? ` → ${d.to}` : ''}\n`;
  }
  if (d.source || d.place) {
    msg += `*Route:* ${d.source || ''}${d.place ? ` → ${d.place}` : ''}\n`;
  }
  if (d.cotraveler) msg += line('👥 Co-Travelers', d.cotraveler);
  msg += `\n`;

  if (d.driver || d.vehicleName || d.vehicleNo) {
    msg += `🚘 *ASSIGNMENT*\n`;
    if (d.driver || d.driverMob) {
      msg += `*Driver:* ${d.driver || ''}${d.driverMob ? ` (${d.driverMob})` : ''}\n`;
    }
    if (d.vehicleName || d.vehicleNo) {
      const vehicle = d.vehicleType
        ? `${d.vehicleType} — ${d.vehicleName || ''}`
        : (d.vehicleName || '');
      msg += `*Vehicle:* ${vehicle}${d.vehicleNo ? ` (${d.vehicleNo})` : ''}\n`;
    }
    msg += `\n`;
  }

  if (d.overdue && Array.isArray(d.overdueReasons) && d.overdueReasons.length) {
    msg += `⚠️ *${d.overdueReasons.join(' · ')} — still not departed*\n`;
  }

  return msg.trim();
}

function copyTextToClipboard(text) {
  if (navigator.clipboard && window.isSecureContext) {
    return navigator.clipboard.writeText(text);
  }
  return new Promise((resolve, reject) => {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.focus();
    ta.select();
    try {
      document.execCommand('copy');
      resolve();
    } catch (err) {
      reject(err);
    } finally {
      document.body.removeChild(ta);
    }
  });
}

function showCopyToast(msg) {
  let toast = document.getElementById('copyToast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'copyToast';
    toast.style.cssText =
      'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);' +
      'background:#25D366;color:#fff;padding:10px 20px;border-radius:8px;' +
      'font-size:14px;font-weight:600;z-index:9999;box-shadow:0 4px 14px rgba(0,0,0,.2);' +
      'display:flex;align-items:center;gap:8px;opacity:0;transition:opacity .25s;pointer-events:none;';
    document.body.appendChild(toast);
  }
  toast.innerHTML = `<i class="bi bi-check-circle-fill"></i> ${msg}`;
  toast.style.opacity = '1';
  clearTimeout(toast._t);
  toast._t = setTimeout(() => { toast.style.opacity = '0'; }, 1800);
}

document.addEventListener('click', e => {
  const btn = e.target.closest('.copyRow');
  if (!btn) return;
  const id = btn.dataset.id;
  const d = rowData(id);
  const text = buildWhatsAppMessage(d);
  copyTextToClipboard(text)
    .then(() => showCopyToast('Copied — paste it in WhatsApp'))
    .catch(() => alert('Could not copy automatically. Please copy manually.'));
});

/* ---------- PDF ---------- */
function makePDF(dataArr) {
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF("p", "pt", "a4");
  const pw = doc.internal.pageSize.getWidth();
  dataArr.forEach((d, i) => {
    if (i > 0) doc.addPage();
    doc.setFontSize(16); doc.setTextColor(30, 58, 138); doc.setFont("helvetica", "bold");
    doc.text(`Vehicle Application  #${d.id}`, pw / 2, 38, { align: "center" });
    doc.setDrawColor(30, 58, 138); doc.setLineWidth(.5); doc.line(40, 46, pw - 40, 46);
    const body = [
      ["Booking For", d.bookingFor], ["Other Person", d.otherPerson], ["Applicant", d.name],
      ["Staff Type", d.staffType], ["Mobile", d.mobile], ["Email", d.email],
      ["Journey From", d.from], ["Journey To", d.to], ["Source", d.source],
      ["Place of Visit", d.place],
      ["Purpose", d.purpose], ["Tour Order", d.tour],
      ["Forwarded To", d.forwarded], ["Co-Travelers", d.cotraveler],
      ["Driver", d.driver], ["Driver Mobile", d.driverMob],
      ["Vehicle Type", d.vehicleType], ["Vehicle Name", d.vehicleName], ["Vehicle No", d.vehicleNo],
      ["Departure", d.outDt], ["Return", d.inDt],
      ["KM Out", d.kmOut], ["KM In", d.kmIn], ["Total KM", d.totalKm], ["Total Hours", d.totalHours],
      ["Security Guard / Driver / Driver", d.security], ["Remark", d.remark],
      ["Status", d.overdue ? `${d.status}  (⚠ Overdue — not yet departed)` : d.status],
      ["Submitted", d.submitted]
    ].filter(r => r[1]);
    doc.autoTable({
      startY: 58, head: [["Field", "Details"]], body, theme: "grid",
      styles: { fontSize: 9.5, cellPadding: 5, font: "helvetica" },
      headStyles: { fillColor: [30, 58, 138], textColor: 255, fontStyle: "bold" },
      alternateRowStyles: { fillColor: [247, 248, 252] },
      columnStyles: { 0: { fontStyle: "bold", cellWidth: 130, textColor: [61, 79, 110] } }
    });
  });
  return doc;
}

document.addEventListener("click", e => {
  const btn = e.target.closest(".exportRow");
  if (!btn) return;
  const id = btn.dataset.id;
  showLoad("Generating PDF…");
  setTimeout(() => { const d = rowData(id); makePDF([d]).save(`application_${d.id}.pdf`); hideLoad(); }, 80);
});

document.getElementById("exportAllPDF").addEventListener("click", () => {
  const rows = window.__getFiltered ? window.__getFiltered() : Array.from(document.querySelectorAll("tbody tr[data-status]"));
  if (!rows.length) { alert("No records to export."); return; }
  const data = rows.map(tr => rowData(tr.dataset.id));
  showLoad(`Building PDF for ${data.length} records…`);
  setTimeout(() => { makePDF(data).save("All_Vehicle_Applications.pdf"); hideLoad(); }, 80);
});

document.getElementById("exportAllCompletedPDF").addEventListener("click", () => {
  const all = window.__getFiltered ? window.__getFiltered() : Array.from(document.querySelectorAll("tbody tr[data-status]"));
  const rows = all.filter(tr => (tr.dataset.status || "").trim().toLowerCase() === "completed");
  if (!rows.length) { alert("No Completed applications found."); return; }
  const data = rows.map(tr => rowData(tr.dataset.id));
  showLoad(`Building PDF for ${data.length} completed records…`);
  setTimeout(() => { makePDF(data).save("Completed_Vehicle_Applications.pdf"); hideLoad(); }, 80);
});

document.getElementById("exportAllCSV").addEventListener("click", () => {
  const rows = window.__getFiltered ? window.__getFiltered() : Array.from(document.querySelectorAll("tbody tr[data-status]"));
  if (!rows.length) { alert("No records to export."); return; }
  showLoad("Generating CSV…");
  setTimeout(() => {
    const hdrs = ["App ID", "Booking For", "Other Person", "Applicant", "Mobile", "Email", "Staff Type",
      "Journey From", "Journey To", "Source", "Place", "Purpose", "Tour Order", "Forwarded To", "Co-Travelers",
      "Driver", "Driver Mobile", "Vehicle Type", "Vehicle Name", "Vehicle No",
      "Departure", "Return", "KM Out", "KM In", "Total KM", "Total Hours", "Security Guard / Driver", "Remark", "Status", "Overdue", "Submitted"];
    const fmtD = v => { if (!v) return ""; try { const d = new Date(v); return isNaN(d) ? v : d.toLocaleString("en-IN", { day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit", hour12: true }) } catch { return v } };
    const esc = v => `"${String(v || "").replace(/"/g, '""')}"`;
    let csv = "\uFEFF" + hdrs.map(esc).join(",") + "\n";
    rows.forEach(tr => {
      const d = rowData(tr.dataset.id);
      const overdueText = d.overdue && Array.isArray(d.overdueReasons) ? d.overdueReasons.join(' | ') : '';
      csv += [esc(d.id), esc(d.bookingFor), esc(d.otherPerson), esc(d.name), `="${d.mobile}"`, esc(d.email), esc(d.staffType),
        esc(d.from), esc(d.to), esc(d.source), esc(d.place), esc(d.purpose), esc(d.tour), esc(d.forwarded), esc(d.cotraveler),
        esc(d.driver), `="${d.driverMob}"`, esc(d.vehicleType), esc(d.vehicleName), esc(d.vehicleNo),
        esc(fmtD(d.outDt)), esc(fmtD(d.inDt)), esc(d.kmOut), esc(d.kmIn), esc(d.totalKm), esc(d.totalHours),
        esc(d.security), esc(d.remark), esc(d.status), esc(overdueText), esc(fmtD(d.submitted))].join(",") + "\n";
    });
    const ts = new Date().toISOString().slice(0, 16).replace(/[-:T]/g, "_");
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], { type: "text/csv;charset=utf-8;" }));
    a.download = `vehicle_applications_${rows.length}_${ts}.csv`;
    a.click(); hideLoad();
  }, 80);
});
</script>
</body>
</html>
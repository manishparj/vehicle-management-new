<?php
session_start();
include("../config/db.php");

if(!isset($_SESSION['user']) || $_SESSION['user']['role']!='super_admin'){
    header("location:login.php"); exit();
}

/* ---------- Master lists (so unused vehicles/drivers still show at 0) ---------- */
$vehiclesMaster=[];
$vr=$conn->query("SELECT id, vehicle_name, vehicle_no, vehicle_type FROM vehicles ORDER BY vehicle_name ASC");
while($v=$vr->fetch_assoc()){
  $vehiclesMaster[]=['id'=>(int)$v['id'],'name'=>$v['vehicle_type']." — ".$v['vehicle_name']." (".$v['vehicle_no'].")"];
}

$driversMaster=[];
$dr=$conn->query("SELECT id, driver_name, driver_mob FROM drivers ORDER BY driver_name ASC");
while($d=$dr->fetch_assoc()){
  $driversMaster[]=['id'=>(int)$d['id'],'name'=>$d['driver_name'],'mob'=>$d['driver_mob']];
}

$usersMaster=[];
$ur=$conn->query("SELECT id, name, designation, role FROM users ORDER BY name ASC");
while($u=$ur->fetch_assoc()){
  $usersMaster[]=['id'=>(int)$u['id'],'name'=>$u['name'],'designation'=>$u['designation']];
}

/* supports "5:30" or "05:30:00" style duration strings */
function hhmm_to_hours($v){
  if(preg_match('/^(\d+):(\d{2})(?::\d{2})?$/',trim((string)$v),$m)) return $m[1]+$m[2]/60;
  return null;
}

/* ---------- Applications (the full dataset — filtering happens client-side) ---------- */
$res=$conn->query("
  SELECT va.*, u.name AS forwarded_name, u.designation AS forwarded_designation
  FROM vehicle_applications va
  LEFT JOIN users u ON va.forwarded_to=u.id
  ORDER BY va.created_at DESC
");
if(!$res) die("Query Failed: ".$conn->error);

$apps=[];
while($row=$res->fetch_assoc()){
  /* HOURS: stored value only if > 0, else HH:MM string, else out/in timestamps */
  $hours=null;
  if(is_numeric($row['total_hours']) && (float)$row['total_hours']>0) $hours=(float)$row['total_hours'];
  elseif(($h=hhmm_to_hours($row['total_hours']))!==null && $h>0)      $hours=round($h,2);

  if($hours===null && !empty($row['out_datetime']) && !empty($row['in_datetime'])){
    $diff=(strtotime($row['in_datetime'])-strtotime($row['out_datetime']))/3600;
    if($diff>0) $hours=round($diff,2);
  }

  /* KM: stored value only if > 0, else km_in - km_out */
  $km=null;
  if(is_numeric($row['total_km']) && (float)$row['total_km']>0) $km=(float)$row['total_km'];
  elseif(is_numeric($row['km_out']) && is_numeric($row['km_in']) && (float)$row['km_in']>(float)$row['km_out'])
    $km=(float)$row['km_in']-(float)$row['km_out'];

  $vehicleLabel=null;
  if($row['vehicle_name']) $vehicleLabel=($row['vehicle_type']?:"").( $row['vehicle_type']?" — ":"" ).$row['vehicle_name'].($row['vehicle_no']?" (".$row['vehicle_no'].")":"");

  $apps[]=[
    'id'=>(int)$row['id'],
    'applicant'=>$row['applicant_name'],
    'otherPerson'=>$row['other_person_name'],
    'bookingFor'=>$row['booking_for'],
    'mobile'=>$row['mobile'],
    'email'=>$row['email'],
    'staffType'=>$row['staff_type'],
    'status'=>$row['status'],
    'source'=>$row['source'],
    'vehicleType'=>$row['vehicle_type'],
    'vehicleRequired'=>$row['vehicle_required'],
    'place'=>$row['place_of_visit'],
    'purpose'=>$row['purpose'],
    'createdAt'=>$row['created_at'],
    'datetimeFrom'=>$row['datetime_from'],
    'datetimeTo'=>$row['datetime_to'],
    'verificationTime'=>$row['verification_time'],
    'tourApproved'=>(int)$row['tour_approved']===1,
    'forwardedTo'=>$row['forwarded_to']?(int)$row['forwarded_to']:null,
    'forwardedName'=>$row['forwarded_name'],
    'forwardedDesignation'=>$row['forwarded_designation'],
    'driverId'=>$row['driver_id']?(int)$row['driver_id']:null,
    'driverName'=>$row['driver_name'],
    'driverMob'=>$row['driver_mob'],
    'vehicleId'=>$row['vehicle_id']?(int)$row['vehicle_id']:null,
    'vehicleLabel'=>$vehicleLabel,
    'outDatetime'=>$row['out_datetime'],
    'inDatetime'=>$row['in_datetime'],
    'km'=>$km,
    'hours'=>$hours,
  ];
}
$appsJson=json_encode($apps, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP);
$vehiclesJson=json_encode($vehiclesMaster, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP);
$driversJson=json_encode($driversMaster, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP);
$usersJson=json_encode($usersMaster, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Fleet Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<style>
:root {
  --page:#F4F6FB; --white:#FFFFFF; --ink:#1A2340; --ink2:#3D4F6E; --ink3:#7A8BAD; --ink4:#B4BECE;
  --border:#E3E8F4; --border2:#D0D8EE;
  --blue:#3B5CF6; --blue-lt:#EEF1FE; --blue-mid:#C7D0FC;
  --coral:#F26B3A; --coral-lt:#FEF0EA;
  --green:#18A96B; --green-lt:#E7F8F1;
  --amber:#E8A020; --amber-lt:#FEF6E4;
  --red:#E03D3D; --red-lt:#FEEBEB;
  --violet:#7C4DFF; --violet-lt:#F0EBFF;
  --teal:#0891B2; --teal-lt:#E0F4F9;
  --slate:#64748B; --slate-lt:#F1F5F9;
  --radius:14px; --radius-sm:9px;
  --shadow-sm:0 1px 4px rgba(26,35,64,.06),0 2px 12px rgba(26,35,64,.04);
  --shadow-md:0 4px 20px rgba(26,35,64,.10),0 1px 4px rgba(26,35,64,.06);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;background:var(--page);color:var(--ink);font-size:13.5px;line-height:1.55;-webkit-font-smoothing:antialiased;}

.topbar{background:var(--white);border-bottom:1.5px solid var(--border);height:64px;padding:0 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:200;box-shadow:var(--shadow-sm);}
.brand{display:flex;align-items:center;gap:12px;text-decoration:none;}
.brand-icon{width:40px;height:40px;background:linear-gradient(135deg,var(--blue) 0%,#6B7FFF 100%);border-radius:11px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(59,92,246,.35);}
.brand-icon i{color:#fff;font-size:18px;}
.brand-text{font-size:16px;font-weight:700;color:var(--ink);letter-spacing:-.3px;}
.brand-text span{color:var(--blue);}
.btn-back{display:flex;align-items:center;gap:6px;padding:7px 16px;border-radius:8px;border:1.5px solid var(--border2);background:var(--white);color:var(--ink2);font-size:12.5px;font-weight:600;cursor:pointer;text-decoration:none;transition:all .18s;}
.btn-back:hover{border-color:var(--blue);color:var(--blue);background:var(--blue-lt);}

.page-wrap{padding:26px 28px 48px;max-width:1760px;margin:0 auto;}
.page-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:18px;gap:16px;flex-wrap:wrap;}
.page-title{font-size:22px;font-weight:800;letter-spacing:-.5px;color:var(--ink);}
.page-title span{color:var(--blue);}
.page-sub{font-size:13px;color:var(--ink3);margin-top:2px;}
.result-count{font-size:12.5px;background:var(--blue-lt);color:var(--blue);padding:6px 14px;border-radius:999px;font-weight:700;white-space:nowrap;}

/* Filters */
.filters-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--radius);padding:16px 20px;margin-bottom:16px;box-shadow:var(--shadow-sm);}
.filters-row{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;margin-bottom:12px;}
.filters-row:last-child{margin-bottom:0;}
.ctl-group{display:flex;flex-direction:column;gap:5px;min-width:130px;}
.ctl-group.grow{flex:1 1 200px;}
.ctl-group label{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--ink3);}
.ctl-group input,.ctl-group select{padding:8px 12px;background:var(--page);border:1.5px solid var(--border);border-radius:var(--radius-sm);font-family:inherit;font-size:12.5px;color:var(--ink2);width:100%;}
.ctl-group input:focus,.ctl-group select:focus{outline:none;border-color:var(--blue);}
.chip-row{display:flex;gap:6px;flex-wrap:wrap;}
.chip{padding:7px 13px;border-radius:999px;border:1.5px solid var(--border2);background:var(--page);font-size:12px;font-weight:600;color:var(--ink3);cursor:pointer;transition:all .15s;white-space:nowrap;}
.chip:hover{border-color:var(--blue);color:var(--blue);}
.chip.active{background:var(--blue);border-color:var(--blue);color:#fff;}
.ctl-check{display:flex;align-items:center;gap:7px;font-size:12.5px;color:var(--ink2);font-weight:500;padding-bottom:8px;white-space:nowrap;}
.ctl-spacer{flex:1 1 auto;}
.reset-btn{padding:8px 16px;background:transparent;border:1.5px solid var(--border2);border-radius:var(--radius-sm);font-family:inherit;font-size:12.5px;font-weight:600;color:var(--ink3);cursor:pointer;transition:all .18s;display:flex;align-items:center;gap:5px;white-space:nowrap;}
.reset-btn:hover{border-color:var(--red);color:var(--red);background:var(--red-lt);}
.filters-divider{border:none;border-top:1px dashed var(--border);margin:2px 0;}

/* KPI cards */
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(175px,1fr));gap:12px;margin-bottom:18px;}
.kpi-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--radius);padding:16px 18px;box-shadow:var(--shadow-sm);position:relative;overflow:hidden;}
.kpi-card::before{content:"";position:absolute;top:0;left:0;width:4px;height:100%;background:var(--accent,var(--blue));}
.kpi-label{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--ink3);display:flex;align-items:center;gap:6px;margin-bottom:7px;}
.kpi-label i{font-size:12.5px;color:var(--accent,var(--blue));}
.kpi-value{font-size:22px;font-weight:800;color:var(--ink);letter-spacing:-.4px;font-family:'DM Mono',monospace;}
.kpi-value sup{font-size:12.5px;font-weight:600;color:var(--ink3);margin-left:2px;}
.kpi-sub{font-size:11px;color:var(--ink4);margin-top:3px;}

/* Section headers */
.section-label{font-size:13px;font-weight:800;color:var(--ink);letter-spacing:-.2px;margin:22px 0 12px;display:flex;align-items:center;gap:8px;}
.section-label i{color:var(--blue);}

/* Chart cards */
.chart-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:4px;}
.chart-grid.thirds{grid-template-columns:1.4fr 1fr;}
@media (max-width:1100px){.chart-grid,.chart-grid.thirds{grid-template-columns:1fr;}}
.chart-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--radius);padding:18px 20px;box-shadow:var(--shadow-sm);margin-bottom:16px;}
.chart-card.full{grid-column:1/-1;}
.chart-card-title{font-size:13.5px;font-weight:700;color:var(--ink);margin-bottom:2px;display:flex;align-items:center;gap:8px;}
.chart-card-title i{color:var(--blue);}
.chart-card-sub{font-size:11.5px;color:var(--ink4);margin-bottom:12px;}
.chart-wrap{position:relative;height:260px;}
.chart-wrap.tall{height:300px;}
.chart-empty{display:flex;align-items:center;justify-content:center;height:260px;color:var(--ink4);font-size:12.5px;font-style:italic;}

/* Table cards */
.table-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow-sm);margin-bottom:18px;}
.table-card-head{display:flex;align-items:center;justify-content:space-between;padding:15px 20px;border-bottom:1.5px solid var(--border);flex-wrap:wrap;gap:10px;}
.table-card-title{font-size:14px;font-weight:700;color:var(--ink);display:flex;align-items:center;gap:8px;}
.table-card-title i{color:var(--blue);}
.table-search{position:relative;flex:0 1 220px;}
.table-search i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--ink4);font-size:13px;}
.table-search input{width:100%;padding:7px 12px 7px 32px;background:var(--page);border:1.5px solid var(--border);border-radius:var(--radius-sm);font-family:inherit;font-size:12.5px;color:var(--ink);}
.table-search input:focus{outline:none;border-color:var(--blue);}
.abtn-mini{display:inline-flex;align-items:center;gap:6px;padding:7px 13px;border-radius:8px;font-family:inherit;font-size:12px;font-weight:600;cursor:pointer;border:1.5px solid var(--border2);background:var(--white);color:var(--ink2);transition:all .18s;}
.abtn-mini:hover{border-color:var(--green);color:var(--green);background:var(--green-lt);}
.table-scroll{overflow-x:auto;}
table{width:100%;border-collapse:collapse;min-width:640px;}
thead tr{background:var(--page);border-bottom:2px solid var(--border);}
thead th{padding:12px 16px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--ink3);white-space:nowrap;cursor:pointer;user-select:none;}
thead th:hover{color:var(--ink);}
thead th .sort-ico{margin-left:5px;font-size:10px;opacity:.4;}
thead th.sort-asc .sort-ico::before{content:"↑";opacity:1;color:var(--blue);}
thead th.sort-desc .sort-ico::before{content:"↓";opacity:1;color:var(--blue);}
thead th:not(.sort-asc):not(.sort-desc) .sort-ico::before{content:"↕";}
thead th.sort-asc,thead th.sort-desc{color:var(--blue);}
tbody tr{border-bottom:1px solid var(--border);transition:background .14s;}
tbody tr:last-child{border-bottom:none;}
tbody tr:hover{background:#F8FAFF;}
td{padding:12px 16px;vertical-align:middle;}
.rank-cell{font-family:'DM Mono',monospace;font-size:12px;font-weight:600;color:var(--ink4);}
.entity-name{font-size:13px;font-weight:700;color:var(--ink);}
.entity-sub{font-size:11.5px;color:var(--ink3);margin-top:1px;}
.num-cell{font-family:'DM Mono',monospace;font-size:12px;color:var(--ink2);font-weight:500;}
.util-bar-wrap{display:flex;align-items:center;gap:9px;min-width:140px;}
.util-track{flex:1;height:7px;background:var(--slate-lt);border-radius:99px;overflow:hidden;}
.util-fill{height:100%;border-radius:99px;}
.util-pct{font-family:'DM Mono',monospace;font-size:12px;font-weight:700;min-width:44px;text-align:right;}
.pill{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:999px;font-size:10.5px;font-weight:700;}
.empty-state{padding:40px 24px;text-align:center;color:var(--ink4);}
.empty-state i{font-size:32px;display:block;margin-bottom:8px;}
.empty-state p{font-size:12.5px;color:var(--ink3);}
::-webkit-scrollbar{width:7px;height:7px;}
::-webkit-scrollbar-track{background:var(--page);}
::-webkit-scrollbar-thumb{background:var(--border2);border-radius:4px;}
@media (max-width:900px){.page-wrap{padding:16px;} .topbar{padding:0 16px;} table{font-size:12px;} td,thead th{padding:10px 12px;} .page-title{font-size:18px;}}
</style>
</head>
<body>

<div class="topbar">
  <a class="brand" href="">
    <div class="brand-icon"><i class="bi bi-speedometer2"></i></div>
    <span class="brand-text">Fleet<span>Admin</span></span>
  </a>
  <a href="dashboard_super.php" class="btn-back"><i class="bi bi-arrow-left"></i> Back to Applications</a>
</div>

<div class="page-wrap">

  <div class="page-header">
    <div>
      <div class="page-title">Fleet <span>Dashboard</span></div>
      <div class="page-sub">Applications, vehicle utilization, driver performance &amp; admin workload — all in one view</div>
    </div>
    <div class="result-count" id="resultCount">0 records</div>
  </div>

  <!-- FILTERS -->
  <div class="filters-card">
    <div class="filters-row">
      <div class="ctl-group"><label>From</label><input type="date" id="dFrom"></div>
      <div class="ctl-group"><label>To</label><input type="date" id="dTo"></div>
      <div class="ctl-group">
        <label>Quick range</label>
        <div class="chip-row">
          <button class="chip" data-range="7">7d</button>
          <button class="chip" data-range="30">30d</button>
          <button class="chip active" data-range="90">90d</button>
          <button class="chip" data-range="365">1y</button>
          <button class="chip" data-range="all">All time</button>
        </div>
      </div>
      <div class="ctl-group"><label>Working hrs / day</label><input type="number" id="hrsPerDay" value="8" min="1" max="24" step="0.5" style="width:90px"></div>
      <div class="ctl-check"><input type="checkbox" id="excludeSun" checked style="width:15px;height:15px"><label for="excludeSun" style="text-transform:none;letter-spacing:0">Exclude Sundays</label></div>
      <div class="ctl-spacer"></div>
      <button class="reset-btn" id="resetCtl"><i class="bi bi-arrow-counterclockwise"></i> Reset all</button>
    </div>
    <hr class="filters-divider">
    <div class="filters-row">
      <div class="ctl-group grow"><label>Search</label><input type="text" id="fSearch" placeholder="Applicant, driver, vehicle, place…"></div>
      <div class="ctl-group"><label>Status</label><select id="fStatus"><option value="">All statuses</option></select></div>
      <div class="ctl-group"><label>Staff Type</label><select id="fStaffType"><option value="">All types</option></select></div>
      <div class="ctl-group"><label>Booking For</label><select id="fBookingFor"><option value="">All</option></select></div>
      <div class="ctl-group"><label>Vehicle Type</label><select id="fVehicleType"><option value="">All</option></select></div>
      <div class="ctl-group"><label>Source</label><select id="fSource"><option value="">All</option></select></div>
      <div class="ctl-group"><label>Forwarded To</label><select id="fForwarded"><option value="">All</option></select></div>
      <div class="ctl-group"><label>Tour Approved</label><select id="fTour"><option value="">All</option><option value="yes">Yes</option><option value="no">No</option></select></div>
    </div>
  </div>

  <!-- KPI CARDS -->
  <div class="kpi-grid" id="kpiGrid"></div>

  <!-- APPLICATIONS OVERVIEW -->
  <div class="section-label"><i class="bi bi-clipboard-data-fill"></i>Applications Overview</div>
  <div class="chart-grid thirds">
    <div class="chart-card"><div class="chart-card-title"><i class="bi bi-graph-up"></i>Applications Over Time</div><div class="chart-card-sub">Volume trend for the selected period</div><div class="chart-wrap" id="trendWrap"><canvas id="trendChart"></canvas></div></div>
    <div class="chart-card"><div class="chart-card-title"><i class="bi bi-pie-chart-fill"></i>Status Distribution</div><div class="chart-card-sub">Where applications currently stand</div><div class="chart-wrap" id="statusWrap"><canvas id="statusChart"></canvas></div></div>
  </div>

  <!-- VEHICLE & DRIVER -->
  <div class="section-label"><i class="bi bi-truck"></i>Vehicle &amp; Driver Utilization</div>
  <div class="chart-grid">
    <div class="chart-card"><div class="chart-card-title"><i class="bi bi-bar-chart-fill"></i>Vehicle Utilization %</div><div class="chart-card-sub">Hours used ÷ available working hours</div><div class="chart-wrap" id="utilWrap"><canvas id="utilChart"></canvas></div></div>
    <div class="chart-card"><div class="chart-card-title"><i class="bi bi-person-lines-fill"></i>Driver Performance Breakdown</div><div class="chart-card-sub">Weighted score points: KM 55% · Duty hrs 25% · Night hrs 20%</div><div class="chart-wrap" id="driverWrap"><canvas id="driverChart"></canvas></div></div>
  </div>

  <div class="table-card">
    <div class="table-card-head">
      <div class="table-card-title"><i class="bi bi-truck"></i>Vehicle-wise Statistics</div>
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <div class="table-search"><i class="bi bi-search"></i><input type="text" id="vehicleSearch" placeholder="Search vehicle…"></div>
        <button class="abtn-mini" id="exportVehicleCSV"><i class="bi bi-filetype-csv" style="color:var(--green)"></i> CSV</button>
      </div>
    </div>
    <div class="table-scroll">
      <table id="vehicleTable">
        <thead><tr>
          <th data-col="rank">#<span class="sort-ico"></span></th>
          <th data-col="name" class="sort-asc">Vehicle<span class="sort-ico"></span></th>
          <th data-col="trips">Trips<span class="sort-ico"></span></th>
          <th data-col="km">KM<span class="sort-ico"></span></th>
          <th data-col="hours">Hours<span class="sort-ico"></span></th>
          <th data-col="avgKm">Avg KM/Trip<span class="sort-ico"></span></th>
          <th data-col="nightHours">Night Hrs<span class="sort-ico"></span></th>
          <th data-col="nightKm">Night KM<span class="sort-ico"></span></th>
          <th data-col="util">Utilization<span class="sort-ico"></span></th>
        </tr></thead>
        <tbody id="vehicleTbody"></tbody>
      </table>
    </div>
  </div>

  <div class="table-card">
    <div class="table-card-head">
      <div class="table-card-title"><i class="bi bi-person-badge"></i>Driver Performance</div>
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <div class="table-search"><i class="bi bi-search"></i><input type="text" id="driverSearch" placeholder="Search driver…"></div>
        <button class="abtn-mini" id="exportDriverCSV"><i class="bi bi-filetype-csv" style="color:var(--green)"></i> CSV</button>
      </div>
    </div>
    <div class="table-scroll">
      <table id="driverTable">
        <thead><tr>
          <th data-col="rank">#<span class="sort-ico"></span></th>
          <th data-col="name" class="sort-asc">Driver<span class="sort-ico"></span></th>
          <th data-col="trips">Trips<span class="sort-ico"></span></th>
          <th data-col="km">KM<span class="sort-ico"></span></th>
          <th data-col="hours">Duty Hrs<span class="sort-ico"></span></th>
          <th data-col="nightTrips">Night Trips<span class="sort-ico"></span></th>
          <th data-col="nightHours">Night Hrs<span class="sort-ico"></span></th>
          <th data-col="nightKm">Night KM<span class="sort-ico"></span></th>
          <th data-col="score" class="sort-desc">Performance<span class="sort-ico"></span></th>
        </tr></thead>
        <tbody id="driverTbody"></tbody>
      </table>
    </div>
  </div>

  <!-- USERS / ADMIN WORKLOAD -->
  <div class="section-label"><i class="bi bi-people-fill"></i>Admin / Forwarding Workload</div>
  <div class="table-card">
    <div class="table-card-head">
      <div class="table-card-title"><i class="bi bi-diagram-3-fill"></i>Applications by Forwarded Admin</div>
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <div class="table-search"><i class="bi bi-search"></i><input type="text" id="adminSearch" placeholder="Search admin…"></div>
        <button class="abtn-mini" id="exportAdminCSV"><i class="bi bi-filetype-csv" style="color:var(--green)"></i> CSV</button>
      </div>
    </div>
    <div class="table-scroll">
      <table id="adminTable">
        <thead><tr>
          <th data-col="rank">#<span class="sort-ico"></span></th>
          <th data-col="name" class="sort-asc">Admin<span class="sort-ico"></span></th>
          <th data-col="total">Received<span class="sort-ico"></span></th>
          <th data-col="approved">Approved<span class="sort-ico"></span></th>
          <th data-col="recommended">Recommended<span class="sort-ico"></span></th>
          <th data-col="rejected">Rejected<span class="sort-ico"></span></th>
          <th data-col="cancelled">Cancelled<span class="sort-ico"></span></th>
          <th data-col="pending">Pending<span class="sort-ico"></span></th>
          <th data-col="avgTurnaround">Avg Response<span class="sort-ico"></span></th>
        </tr></thead>
        <tbody id="adminTbody"></tbody>
      </table>
    </div>
  </div>

</div><!-- /page-wrap -->

<script>
const RAW_APPS   = <?php echo $appsJson; ?>;
const MASTER_VEHICLES = <?php echo $vehiclesJson; ?>;
const MASTER_DRIVERS  = <?php echo $driversJson; ?>;
const MASTER_USERS    = <?php echo $usersJson; ?>;

(function(){
  const dFrom=document.getElementById("dFrom"), dTo=document.getElementById("dTo");
  const hrsPerDay=document.getElementById("hrsPerDay"), excludeSun=document.getElementById("excludeSun");
  const chips=document.querySelectorAll(".chip");
  const fSearch=document.getElementById("fSearch"), fStatus=document.getElementById("fStatus"),
        fStaffType=document.getElementById("fStaffType"), fBookingFor=document.getElementById("fBookingFor"),
        fVehicleType=document.getElementById("fVehicleType"), fSource=document.getElementById("fSource"),
        fForwarded=document.getElementById("fForwarded"), fTour=document.getElementById("fTour");
  const vSearch=document.getElementById("vehicleSearch"), drSearch=document.getElementById("driverSearch"), adSearch=document.getElementById("adminSearch");

  let vSort={col:"util",dir:-1}, drSort={col:"score",dir:-1}, adSort={col:"total",dir:-1};
  let vehicleRows=[], driverRows=[], adminRows=[], filteredApps=[];
  let charts={};

  const STATUS_COLORS={
    "Draft":"#94A3B8","Pending":"#64748B","Recommended":"#E8A020","Not Recommended":"#E03D3D",
    "Approved":"#18A96B","Rejected":"#E03D3D","Completed":"#3B5CF6","Cancelled":"#F26B3A","Departure":"#0891B2"
  };
  const PALETTE=["#3B5CF6","#18A96B","#E8A020","#F26B3A","#7C4DFF","#0891B2","#E03D3D","#64748B","#DB2777","#059669"];
  function colorFor(str,idx){ return STATUS_COLORS[str] || PALETTE[idx % PALETTE.length]; }

  /* ---------- helpers ---------- */
  function esc(s){ return String(s??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c])); }
  function mountCanvas(wrapId,canvasId){
    document.getElementById(wrapId).innerHTML=`<canvas id="${canvasId}"></canvas>`;
    return document.getElementById(canvasId);
  }
  function showEmpty(wrapId,msg){ document.getElementById(wrapId).innerHTML=`<div class="chart-empty">${msg}</div>`; }
  function destroy(key){ if(charts[key]){ charts[key].destroy(); charts[key]=null; } }

  /* ONE classifier used by KPIs and the admin table */
  function outcomeOf(a){
    const s=(a.status||"").toLowerCase().trim();
    if(s.includes("not recommend")||s.includes("not approv")||s.includes("reject")||s.includes("decline")) return "rejected";
    if(s.includes("cancel")) return "cancelled";
    if(s.includes("approv")||s.includes("complet")||s.includes("departure")) return "approved"; // completed/departed trips were approved
    if(s.includes("recommend")) return "recommended";
    if(a.tourApproved) return "approved";
    return "pending"; // Draft, Pending, in review
  }

  /* ---------- populate dropdowns dynamically from data ---------- */
  function uniqueVals(key){ return [...new Set(RAW_APPS.map(a=>a[key]).filter(v=>v!==null && v!==undefined && v!==""))].sort(); }
  function fillSelect(sel,vals){ vals.forEach(v=>{ const o=document.createElement("option"); o.value=v; o.textContent=v; sel.appendChild(o); }); }
  fillSelect(fStatus, uniqueVals("status"));
  fillSelect(fStaffType, uniqueVals("staffType"));
  fillSelect(fBookingFor, uniqueVals("bookingFor"));
  fillSelect(fVehicleType, uniqueVals("vehicleType"));
  fillSelect(fSource, uniqueVals("source"));
  MASTER_USERS.forEach(u=>{ const o=document.createElement("option"); o.value=u.id; o.textContent=u.name+(u.designation?" ("+u.designation+")":""); fForwarded.appendChild(o); });

  /* ---------- date helpers (local time, not UTC) ---------- */
  function toISO(d){ const p=n=>String(n).padStart(2,"0"); return d.getFullYear()+"-"+p(d.getMonth()+1)+"-"+p(d.getDate()); }
  function setRange(days){
    const to=new Date();
    if(days==="all"){
      const times=RAW_APPS.map(t=>new Date(t.datetimeFrom||t.createdAt).getTime()).filter(n=>!isNaN(n));
      const min=times.length?new Date(Math.min(...times)):new Date(to.getFullYear(),0,1);
      dFrom.value=toISO(min); dTo.value=toISO(to);
    } else {
      const from=new Date(); from.setDate(from.getDate()-parseInt(days));
      dFrom.value=toISO(from); dTo.value=toISO(to);
    }
  }
  chips.forEach(c=>c.addEventListener("click",()=>{ chips.forEach(x=>x.classList.remove("active")); c.classList.add("active"); setRange(c.dataset.range); compute(); }));
  document.getElementById("resetCtl").addEventListener("click",()=>{
    chips.forEach(x=>x.classList.remove("active"));
    document.querySelector('.chip[data-range="90"]').classList.add("active");
    setRange("90"); hrsPerDay.value=8; excludeSun.checked=true;
    fSearch.value=""; [fStatus,fStaffType,fBookingFor,fVehicleType,fSource,fForwarded,fTour].forEach(s=>s.value="");
    compute();
  });
  setRange("90");

  function workingHoursInRange(fromDate,toDate,perDay,noSun){
    let hrs=0; const d=new Date(fromDate);
    while(d<=toDate){ if(!(noSun && d.getDay()===0)) hrs+=perDay; d.setDate(d.getDate()+1); }
    return hrs;
  }
  function fmt(n,dec=1){ if(!isFinite(n)||isNaN(n)) return "0"; return dec===0?Math.round(n).toLocaleString("en-IN"):(+n.toFixed(dec)).toLocaleString("en-IN"); }
  function daysBetween(a,b){ return (new Date(b)-new Date(a))/86400000; }

  /* ---------- night-duty helpers (night = 20:00 → 06:00) ---------- */
  function parseDT(v){ if(!v) return null; const d=new Date(String(v).replace(" ","T")); return isNaN(d)?null:d; }
  /* real trip length in hours — only when BOTH out and in times exist and in > out */
  function spanOf(a){ const s=parseDT(a.outDatetime), e=parseDT(a.inDatetime); return (s&&e&&e>s)?(e-s)/3600000:0; }
  /* one source of truth for duty hours: real timestamps first, stored value as fallback */
  function tripHours(a){ const s=spanOf(a); return s>0 ? s : (a.hours||0); }
  /* trips that actually count for vehicle/driver stats (skip rejected & cancelled) */
  function isRealTrip(a){
    if(!a.outDatetime) return false;
    const o=outcomeOf(a);
    return o!=="rejected" && o!=="cancelled";
  }
  /* match manual driver names/mobiles to the master list so one driver = one row */
  const normName=s=>String(s||"").toLowerCase().replace(/\s+/g," ").trim();
  const digits=s=>String(s||"").replace(/\D/g,"").slice(-10);
  const driverByName=new Map(MASTER_DRIVERS.map(d=>[normName(d.name),d.id]));
  const driverByMob =new Map(MASTER_DRIVERS.filter(d=>digits(d.mob)).map(d=>[digits(d.mob),d.id]));
  function resolveDriverId(a){
    if(a.driverId) return a.driverId;
    const m=digits(a.driverMob);
    return (m && driverByMob.get(m)) ?? driverByName.get(normName(a.driverName)) ?? null;
  }
  /* exact overlap of [out,in] with every 00:00–06:00 and 20:00–24:00 window; open/invalid trips return 0 */
  function nightHoursOf(outDt,inDt){
    const start=parseDT(outDt), end=parseDT(inDt);
    if(!start||!end||end<=start) return 0;
    const S=start.getTime(), E=end.getTime();
    let ms=0;
    let day=new Date(start.getFullYear(),start.getMonth(),start.getDate());
    while(day.getTime()<E){
      const y=day.getFullYear(), m=day.getMonth(), d=day.getDate();
      const wins=[[new Date(y,m,d,0).getTime(),new Date(y,m,d,6).getTime()],[new Date(y,m,d,20).getTime(),new Date(y,m,d+1,0).getTime()]];
      wins.forEach(([a,b])=>{ const lo=Math.max(a,S), hi=Math.min(b,E); if(hi>lo) ms+=hi-lo; });
      day=new Date(y,m,d+1);
    }
    return ms/3600000;
  }

  /* ---------- core aggregation ---------- */
  function compute(){
    const from=dFrom.value?new Date(dFrom.value+"T00:00:00"):null;
    const to=dTo.value?new Date(dTo.value+"T23:59:59"):null;
    const perDay=parseFloat(hrsPerDay.value)||8;
    const noSun=excludeSun.checked;
    const kw=(fSearch.value||"").toLowerCase().trim();

    filteredApps=RAW_APPS.filter(a=>{
      const refDate=a.datetimeFrom||a.createdAt;
      if(refDate){ const dt=new Date(refDate); if(from&&dt<from) return false; if(to&&dt>to) return false; }
      if(fStatus.value && a.status!==fStatus.value) return false;
      if(fStaffType.value && a.staffType!==fStaffType.value) return false;
      if(fBookingFor.value && a.bookingFor!==fBookingFor.value) return false;
      if(fVehicleType.value && a.vehicleType!==fVehicleType.value) return false;
      if(fSource.value && a.source!==fSource.value) return false;
      if(fForwarded.value && String(a.forwardedTo||"")!==fForwarded.value) return false;
      if(fTour.value==="yes" && !a.tourApproved) return false;
      if(fTour.value==="no" && a.tourApproved) return false;
      if(kw){
        const hay=[a.applicant,a.otherPerson,a.driverName,a.vehicleLabel,a.place,a.mobile,a.email].join(" ").toLowerCase();
        if(!hay.includes(kw)) return false;
      }
      return true;
    });

    const availHours=(from&&to)?workingHoursInRange(from,to,perDay,noSun):0;

    /* vehicle aggregation — seed with the full master list so idle vehicles show at 0 */
    const vMap=new Map();
    MASTER_VEHICLES.forEach(v=>vMap.set(v.id,{id:v.id,name:v.name,trips:0,km:0,hours:0,nightHours:0,nightKm:0}));
    filteredApps.forEach(a=>{
      if(!isRealTrip(a)) return;
      const key=a.vehicleId ?? ("lbl:"+(a.vehicleLabel||"Unassigned"));
      if(!vMap.has(key)) vMap.set(key,{id:key,name:a.vehicleLabel||"Unassigned vehicle",trips:0,km:0,hours:0,nightHours:0,nightKm:0});
      const v=vMap.get(key);
      v.trips++; v.km+=(a.km||0); v.hours+=tripHours(a);
      const vNight=nightHoursOf(a.outDatetime,a.inDatetime);
      if(vNight>=0.01){
        v.nightHours+=vNight;
        const vSpan=spanOf(a);
        if(vSpan>0) v.nightKm+=(a.km||0)*Math.min(1,vNight/vSpan);
      }
    });
    vehicleRows=Array.from(vMap.values()).map(v=>({...v,avgKm:v.trips?v.km/v.trips:0,util:availHours?Math.min(999,(v.hours/availHours)*100):0}));

    /* driver aggregation — seed with master list; track night duty */
    const dMap=new Map();
    MASTER_DRIVERS.forEach(d=>dMap.set(d.id,{id:d.id,name:d.name,mob:d.mob,trips:0,km:0,hours:0,nightTrips:0,nightHours:0,nightKm:0,score:0}));
    filteredApps.forEach(a=>{
      if(!isRealTrip(a)) return;
      const did=resolveDriverId(a);
      const key=did ?? ("lbl:"+normName(a.driverName||"Unassigned"));
      if(!dMap.has(key)) dMap.set(key,{id:key,name:a.driverName||"Unassigned driver",mob:a.driverMob||"",trips:0,km:0,hours:0,nightTrips:0,nightHours:0,nightKm:0,score:0});
      const d=dMap.get(key);
      d.trips++; d.km+=(a.km||0); d.hours+=tripHours(a);
      const night=nightHoursOf(a.outDatetime,a.inDatetime);
      if(night>=0.01){
        d.nightTrips++; d.nightHours+=night;
        /* Night KM = trip KM prorated by the share of the trip that fell inside 20:00–06:00 */
        const span=spanOf(a);
        if(span>0) d.nightKm+=(a.km||0)*Math.min(1,night/span);
      }
    });

    /* Driver score: KM heaviest, then duty hours, then night duty */
    const KM_W=0.55, HR_W=0.25, NIGHT_W=0.20;
    const dList=[...dMap.values()];
    const maxKm=Math.max(1,...dList.map(d=>d.km));
    const maxHr=Math.max(1,...dList.map(d=>d.hours));
    const maxNt=Math.max(1,...dList.map(d=>d.nightHours));
    driverRows=dList.map(d=>{
      const kmPts=(d.km/maxKm)*KM_W*100, hrPts=(d.hours/maxHr)*HR_W*100, ntPts=(d.nightHours/maxNt)*NIGHT_W*100;
      return {...d,kmPts,hrPts,ntPts,score:kmPts+hrPts+ntPts};
    });

    /* Admin workload */
    const aMap=new Map();
    filteredApps.forEach(a=>{
      if(!a.forwardedTo) return;
      if(!aMap.has(a.forwardedTo)) aMap.set(a.forwardedTo,{
        id:a.forwardedTo,
        name:(a.forwardedName||"Unknown")+(a.forwardedDesignation?" ("+a.forwardedDesignation+")":""),
        total:0,approved:0,recommended:0,rejected:0,cancelled:0,pending:0,turnSum:0,turnCnt:0
      });
      const r=aMap.get(a.forwardedTo);
      r.total++;
      const o=outcomeOf(a);
      r[o]++;
      /* Response time = created → admin action (verification_time), decided items only */
      if(["approved","recommended","rejected"].includes(o) && a.createdAt && a.verificationTime){
        const days=daysBetween(a.createdAt,a.verificationTime);
        if(days>=0 && days<3650){ r.turnSum+=days; r.turnCnt++; }
      }
    });
    adminRows=Array.from(aMap.values()).map(r=>({...r,avgTurnaround:r.turnCnt?r.turnSum/r.turnCnt:null}));

    renderKpis(availHours);
    renderTrendChart();
    renderStatusChart();
    renderUtilChart();
    renderDriverChart();
    renderVehicleTable();
    renderDriverTable();
    renderAdminTable();
    document.getElementById("resultCount").textContent=filteredApps.length.toLocaleString("en-IN")+" records";
  }

  /* ---------- KPI cards ---------- */
  function renderKpis(availHours){
    const total=filteredApps.length;
    const cnt=k=>filteredApps.filter(a=>outcomeOf(a)===k).length;
    const approved=cnt("approved"), rejected=cnt("rejected"), cancelled=cnt("cancelled");
    const pending=cnt("pending")+cnt("recommended");
    const completed=filteredApps.filter(a=>(a.status||"").toLowerCase().includes("complet")).length;
    const totalKm=vehicleRows.reduce((s,v)=>s+v.km,0);
    const totalHours=vehicleRows.reduce((s,v)=>s+v.hours,0);
    const activeVehicles=vehicleRows.filter(v=>v.trips>0).length;
    const activeDrivers=driverRows.filter(d=>d.trips>0).length;
    const totalNight=driverRows.reduce((s,d)=>s+d.nightTrips,0);
    const totalNightHrs=driverRows.reduce((s,d)=>s+d.nightHours,0);
    const totalNightKm=driverRows.reduce((s,d)=>s+d.nightKm,0);
    const avgUtil=activeVehicles?vehicleRows.filter(v=>v.trips>0).reduce((s,v)=>s+v.util,0)/activeVehicles:0;

    const cards=[
      {label:"Total Applications",icon:"bi-clipboard-data-fill",accent:"var(--blue)",value:fmt(total,0),sub:"in selected period"},
      {label:"Approved",icon:"bi-check-circle-fill",accent:"var(--green)",value:fmt(approved,0),sub:"incl. completed · "+(total?fmt(approved/total*100,0):0)+"%"},
      {label:"Rejected",icon:"bi-x-circle-fill",accent:"var(--red)",value:fmt(rejected,0),sub:total?fmt(rejected/total*100,0)+"% of total":""},
      {label:"Cancelled",icon:"bi-slash-circle-fill",accent:"var(--coral)",value:fmt(cancelled,0),sub:total?fmt(cancelled/total*100,0)+"% of total":""},
      {label:"Pending / In Review",icon:"bi-hourglass-split",accent:"var(--amber)",value:fmt(pending,0),sub:"awaiting action"},
      {label:"Completed Trips",icon:"bi-flag-fill",accent:"var(--teal)",value:fmt(completed,0),sub:"fully closed out"},
      {label:"Active Vehicles",icon:"bi-truck",accent:"var(--blue)",value:activeVehicles,sub:"of "+MASTER_VEHICLES.length+" registered"},
      {label:"Active Drivers",icon:"bi-person-badge",accent:"var(--violet)",value:activeDrivers,sub:"of "+MASTER_DRIVERS.length+" registered"},
      {label:"Night Trips",icon:"bi-moon-stars-fill",accent:"var(--violet)",value:fmt(totalNight,0),sub:"duty between 20:00–06:00"},
      {label:"Night Hours",icon:"bi-moon-fill",accent:"var(--violet)",value:fmt(totalNightHrs,1),unit:"hrs",sub:"driven 20:00–06:00"},
      {label:"Night KM",icon:"bi-signpost-2-fill",accent:"var(--violet)",value:fmt(totalNightKm,0),unit:"km",sub:"prorated to night window"},
      {label:"Total Distance",icon:"bi-map-fill",accent:"var(--green)",value:fmt(totalKm,0),unit:"km",sub:"across all vehicles"},
      {label:"Total Hours Used",icon:"bi-clock-history",accent:"var(--amber)",value:fmt(totalHours,0),unit:"hrs",sub:fmt(availHours,0)+" hrs available"},
      {label:"Avg Utilization",icon:"bi-speedometer2",accent:"var(--coral)",value:fmt(avgUtil,1),unit:"%",sub:"active fleet average"},
    ];
    document.getElementById("kpiGrid").innerHTML=cards.map(c=>`
      <div class="kpi-card" style="--accent:${c.accent}">
        <div class="kpi-label"><i class="bi ${c.icon}"></i>${c.label}</div>
        <div class="kpi-value">${c.value}${c.unit?`<sup>${c.unit}</sup>`:""}</div>
        <div class="kpi-sub">${c.sub}</div>
      </div>`).join("");
  }

  /* ---------- charts ---------- */
  function renderTrendChart(){
    destroy("trend");
    if(!filteredApps.length) return showEmpty("trendWrap","No applications in this range");
    const byDay=new Map();
    filteredApps.forEach(a=>{ const d=(a.datetimeFrom||a.createdAt||"").slice(0,10); if(d) byDay.set(d,(byDay.get(d)||0)+1); });
    const labels=[...byDay.keys()].sort();
    charts.trend=new Chart(mountCanvas("trendWrap","trendChart"),{type:"line",
      data:{labels,datasets:[{data:labels.map(l=>byDay.get(l)),borderColor:"#3B5CF6",backgroundColor:"rgba(59,92,246,.12)",fill:true,tension:.3,pointRadius:labels.length>40?0:3}]},
      options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{display:false},ticks:{maxTicksLimit:10,font:{size:10}}},y:{beginAtZero:true,ticks:{stepSize:1},grid:{color:"#E3E8F4"}}}}});
  }

  function renderStatusChart(){
    destroy("status");
    if(!filteredApps.length) return showEmpty("statusWrap","No applications in this range");
    const map=new Map();
    filteredApps.forEach(a=>{const k=a.status||"Unknown"; map.set(k,(map.get(k)||0)+1);});
    const labels=[...map.keys()];
    charts.status=new Chart(mountCanvas("statusWrap","statusChart"),{type:"doughnut",
      data:{labels,datasets:[{data:labels.map(l=>map.get(l)),backgroundColor:labels.map((l,i)=>colorFor(l,i)),borderWidth:2,borderColor:"#fff"}]},
      options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:"right",labels:{boxWidth:11,font:{size:11}}}}}});
  }

  function utilColor(p){ if(p>=85) return "#E03D3D"; if(p>=60) return "#18A96B"; if(p>=30) return "#E8A020"; return "#B4BECE"; }
  function perfColor(s){ if(s>=75) return "#18A96B"; if(s>=50) return "#E8A020"; if(s>=25) return "#F26B3A"; return "#B4BECE"; }

  function renderUtilChart(){
    destroy("util");
    const active=vehicleRows.filter(v=>v.trips>0);
    if(!active.length) return showEmpty("utilWrap","No vehicle activity in this range");
    const sorted=[...active].sort((a,b)=>b.util-a.util).slice(0,12);
    charts.util=new Chart(mountCanvas("utilWrap","utilChart"),{type:"bar",
      data:{labels:sorted.map(v=>v.name),datasets:[{data:sorted.map(v=>+v.util.toFixed(1)),backgroundColor:sorted.map(v=>utilColor(v.util)),borderRadius:5,barThickness:16}]},
      options:{indexAxis:"y",responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>c.parsed.x+"% utilization"}}},scales:{x:{beginAtZero:true,ticks:{callback:v=>v+"%"},grid:{color:"#E3E8F4"}},y:{grid:{display:false},ticks:{font:{size:11}}}}}});
  }

  /* stacked score points: total bar length = performance score */
  function renderDriverChart(){
    destroy("driver");
    const active=driverRows.filter(d=>d.trips>0);
    if(!active.length) return showEmpty("driverWrap","No driver activity in this range");
    const sorted=[...active].sort((a,b)=>b.score-a.score).slice(0,12);
    const ds=(label,key,color)=>({label,data:sorted.map(d=>+d[key].toFixed(1)),backgroundColor:color,borderRadius:3,barThickness:16});
    charts.driver=new Chart(mountCanvas("driverWrap","driverChart"),{type:"bar",
      data:{labels:sorted.map(d=>d.name),datasets:[ds("KM (55%)","kmPts","#3B5CF6"),ds("Duty hrs (25%)","hrPts","#18A96B"),ds("Night hrs (20%)","ntPts","#7C4DFF")]},
      options:{indexAxis:"y",responsive:true,maintainAspectRatio:false,
        plugins:{legend:{position:"bottom",labels:{boxWidth:11,font:{size:11}}},tooltip:{callbacks:{label:c=>c.dataset.label+": "+c.parsed.x+" pts"}}},
        scales:{x:{stacked:true,beginAtZero:true,max:100,title:{display:true,text:"Performance score"},grid:{color:"#E3E8F4"}},y:{stacked:true,grid:{display:false},ticks:{font:{size:11}}}}}});
  }

  /* ---------- generic sort ---------- */
  function sortRows(rows,sortState){
    const {col,dir}=sortState;
    return [...rows].sort((a,b)=>{
      let va=a[col], vb=b[col];
      if(va===null||va===undefined) va = typeof vb==="string" ? "" : -Infinity;
      if(vb===null||vb===undefined) vb = typeof va==="string" ? "" : -Infinity;
      if(typeof va==="string") return va.localeCompare(vb)*dir;
      return (va-vb)*dir;
    });
  }

  function renderVehicleTable(){
    const kw=(vSearch.value||"").toLowerCase().trim();
    let rows=sortRows(vehicleRows.filter(v=>!kw||v.name.toLowerCase().includes(kw)),vSort);
    const tbody=document.getElementById("vehicleTbody");
    if(!rows.length){ tbody.innerHTML=`<tr><td colspan="9"><div class="empty-state"><i class="bi bi-inbox"></i><p>No vehicles found</p></div></td></tr>`; return; }
    tbody.innerHTML=rows.map((v,i)=>`
      <tr>
        <td class="rank-cell">${i+1}</td>
        <td><div class="entity-name">${esc(v.name)}</div></td>
        <td class="num-cell">${fmt(v.trips,0)}</td>
        <td class="num-cell">${fmt(v.km,0)} km</td>
        <td class="num-cell">${fmt(v.hours,1)} h</td>
        <td class="num-cell">${fmt(v.avgKm,1)} km</td>
        <td class="num-cell">${fmt(v.nightHours,1)} h</td>
        <td class="num-cell">${fmt(v.nightKm,0)} km</td>
        <td><div class="util-bar-wrap"><div class="util-track"><div class="util-fill" style="width:${Math.min(100,v.util)}%;background:${utilColor(v.util)}"></div></div><span class="util-pct" style="color:${utilColor(v.util)}">${fmt(v.util,1)}%</span></div></td>
      </tr>`).join("");
  }

  function renderDriverTable(){
    const kw=(drSearch.value||"").toLowerCase().trim();
    let rows=sortRows(driverRows.filter(d=>!kw||d.name.toLowerCase().includes(kw)),drSort);
    const tbody=document.getElementById("driverTbody");
    if(!rows.length){ tbody.innerHTML=`<tr><td colspan="9"><div class="empty-state"><i class="bi bi-inbox"></i><p>No drivers found</p></div></td></tr>`; return; }
    tbody.innerHTML=rows.map((d,i)=>{
      const c=perfColor(d.score);
      return `
      <tr>
        <td class="rank-cell">${i+1}</td>
        <td><div class="entity-name">${esc(d.name)}</div>${d.mob?`<div class="entity-sub"><i class="bi bi-telephone-fill"></i> ${esc(d.mob)}</div>`:""}</td>
        <td class="num-cell">${fmt(d.trips,0)}</td>
        <td class="num-cell">${fmt(d.km,0)} km</td>
        <td class="num-cell">${fmt(d.hours,1)} h</td>
        <td class="num-cell">${fmt(d.nightTrips,0)}</td>
        <td class="num-cell">${fmt(d.nightHours,1)} h</td>
        <td class="num-cell">${fmt(d.nightKm,0)} km</td>
        <td><div class="util-bar-wrap"><div class="util-track"><div class="util-fill" style="width:${Math.min(100,d.score)}%;background:${c}"></div></div><span class="util-pct" style="color:${c}">${fmt(d.score,1)}</span></div></td>
      </tr>`;}).join("");
  }

  function renderAdminTable(){
    const kw=(adSearch.value||"").toLowerCase().trim();
    const rows=sortRows(adminRows.filter(a=>!kw||a.name.toLowerCase().includes(kw)),adSort);
    const tbody=document.getElementById("adminTbody");
    if(!rows.length){ tbody.innerHTML=`<tr><td colspan="9"><div class="empty-state"><i class="bi bi-inbox"></i><p>No forwarded applications in this range</p></div></td></tr>`; return; }
    tbody.innerHTML=rows.map((a,i)=>`
      <tr>
        <td class="rank-cell">${i+1}</td>
        <td><div class="entity-name">${esc(a.name)}</div></td>
        <td class="num-cell">${fmt(a.total,0)}</td>
        <td class="num-cell" style="color:var(--green)">${fmt(a.approved,0)}</td>
        <td class="num-cell" style="color:var(--teal)">${fmt(a.recommended,0)}</td>
        <td class="num-cell" style="color:var(--red)">${fmt(a.rejected,0)}</td>
        <td class="num-cell" style="color:var(--coral)">${fmt(a.cancelled,0)}</td>
        <td class="num-cell" style="color:var(--amber)">${fmt(a.pending,0)}</td>
        <td class="num-cell">${a.avgTurnaround!==null?fmt(a.avgTurnaround,1)+" days":"—"}</td>
      </tr>`).join("");
  }

  /* header sorting */
  function bindSort(tableId,sortStateRef,renderFn){
    document.querySelectorAll(`#${tableId} thead th`).forEach(th=>{
      th.addEventListener("click",()=>{
        const col=th.dataset.col; if(col==="rank") return;
        if(sortStateRef.col===col) sortStateRef.dir*=-1; else { sortStateRef.col=col; sortStateRef.dir=-1; }
        document.querySelectorAll(`#${tableId} thead th`).forEach(t=>t.classList.remove("sort-asc","sort-desc"));
        th.classList.add(sortStateRef.dir===1?"sort-asc":"sort-desc");
        renderFn();
      });
    });
  }
  bindSort("vehicleTable",vSort,renderVehicleTable);
  bindSort("driverTable",drSort,renderDriverTable);
  bindSort("adminTable",adSort,renderAdminTable);

  /* CSV export */
  function toCSV(headers,rows){
    const q=v=>`"${String(v??"").replace(/"/g,'""')}"`;
    let csv="\uFEFF"+headers.map(q).join(",")+"\n";
    rows.forEach(r=>csv+=r.map(q).join(",")+"\n");
    return csv;
  }
  function download(csv,name){ const a=document.createElement("a"); a.href=URL.createObjectURL(new Blob([csv],{type:"text/csv;charset=utf-8;"})); a.download=name; a.click(); }

  document.getElementById("exportVehicleCSV").addEventListener("click",()=>{
    const rows=sortRows(vehicleRows,vSort).map(v=>[v.name,fmt(v.trips,0),fmt(v.km,0),fmt(v.hours,1),fmt(v.avgKm,1),fmt(v.nightHours,1),fmt(v.nightKm,0),fmt(v.util,1)+"%"]);
    download(toCSV(["Vehicle","Trips","KM","Hours","Avg KM/Trip","Night Hours","Night KM","Utilization"],rows),"vehicle_utilization.csv");
  });
  document.getElementById("exportDriverCSV").addEventListener("click",()=>{
    const rows=sortRows(driverRows,drSort).map(d=>[d.name,d.mob,fmt(d.trips,0),fmt(d.km,0),fmt(d.hours,1),fmt(d.nightTrips,0),fmt(d.nightHours,1),fmt(d.nightKm,0),fmt(d.score,1)]);
    download(toCSV(["Driver","Mobile","Trips","KM","Duty Hours","Night Trips","Night Hours","Night KM","Performance Score"],rows),"driver_performance.csv");
  });
  document.getElementById("exportAdminCSV").addEventListener("click",()=>{
    const rows=sortRows(adminRows,adSort).map(a=>[a.name,a.total,a.approved,a.recommended,a.rejected,a.cancelled,a.pending,a.avgTurnaround!==null?fmt(a.avgTurnaround,1):""]);
    download(toCSV(["Admin","Received","Approved","Recommended","Rejected","Cancelled","Pending","Avg Response (days)"],rows),"admin_workload.csv");
  });

  /* live inputs */
  [dFrom,dTo,hrsPerDay,excludeSun,fStatus,fStaffType,fBookingFor,fVehicleType,fSource,fForwarded,fTour].forEach(el=>el.addEventListener("change",()=>{chips.forEach(x=>x.classList.remove("active"));compute();}));
  fSearch.addEventListener("input",compute);
  vSearch.addEventListener("input",renderVehicleTable);
  drSearch.addEventListener("input",renderDriverTable);
  adSearch.addEventListener("input",renderAdminTable);

  compute();
})();
</script>
</body>
</html>
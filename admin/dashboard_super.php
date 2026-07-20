<?php
session_start();
include("../config/db.php");

if(!isset($_SESSION['user']) || $_SESSION['user']['role']!='super_admin'){
    header("location:login.php"); exit();
}

if(isset($_POST['approve'])){
    $id=$conn->real_escape_string($_POST['app_id']);
    $driver_id=$conn->real_escape_string($_POST['driver_id']);
    $vehicle_id=$conn->real_escape_string($_POST['vehicle_id']);
    $remark=$conn->real_escape_string($_POST['remark']);
    $conn->query("UPDATE vehicle_applications SET status='Approved',driver_id='$driver_id',vehicle_id='$vehicle_id',remark='$remark' WHERE id='$id'");
    echo "<script>alert('Application Approved'); window.location.href='';</script>";
}
if(isset($_POST['add_driver'])){
    $name=mysqli_real_escape_string($conn,$_POST['driver_name']);
    $mob=mysqli_real_escape_string($conn,$_POST['driver_mob']);
    $conn->query("INSERT INTO drivers (driver_name,driver_mob) VALUES ('$name','$mob')");
    echo "<script>alert('Driver Added'); window.location.href='';</script>";
}
if(isset($_POST['add_vehicle'])){
    $type=mysqli_real_escape_string($conn,$_POST['vehicle_type']);
    $name=mysqli_real_escape_string($conn,$_POST['vehicle_name']);
    $no=mysqli_real_escape_string($conn,$_POST['vehicle_no']);
    $conn->query("INSERT INTO vehicles (vehicle_type,vehicle_name,vehicle_no) VALUES ('$type','$name','$no')");
    echo "<script>alert('Vehicle Added'); window.location.href='';</script>";
}
if(isset($_POST['reject'])){
    $id=$conn->real_escape_string($_POST['app_id']);
    $remark=$conn->real_escape_string($_POST['remark']);
    $conn->query("UPDATE vehicle_applications SET status='Rejected',remark='$remark' WHERE id='$id'");
    echo "<script>alert('Application Rejected'); window.location.href='';</script>";
}

$res=$conn->query("
  SELECT va.*, u.name AS forwarded_admin_name, u.designation AS forwarded_designation
  FROM vehicle_applications va
  LEFT JOIN users u ON va.forwarded_to=u.id
  ORDER BY va.created_at DESC
");
if(!$res) die("Query Failed: ".$conn->error);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Vehicle Management — Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>
/* ══════════════════════════════════════════
   TOKEN SYSTEM
══════════════════════════════════════════ */
:root {
  --page:       #F4F6FB;
  --white:      #FFFFFF;
  --ink:        #1A2340;
  --ink2:       #3D4F6E;
  --ink3:       #7A8BAD;
  --ink4:       #B4BECE;
  --border:     #E3E8F4;
  --border2:    #D0D8EE;

  --blue:       #3B5CF6;
  --blue-lt:    #EEF1FE;
  --blue-mid:   #C7D0FC;

  --coral:      #F26B3A;
  --coral-lt:   #FEF0EA;

  --green:      #18A96B;
  --green-lt:   #E7F8F1;

  --amber:      #E8A020;
  --amber-lt:   #FEF6E4;

  --red:        #E03D3D;
  --red-lt:     #FEEBEB;

  --violet:     #7C4DFF;
  --violet-lt:  #F0EBFF;

  --teal:       #0891B2;
  --teal-lt:    #E0F4F9;

  --slate:      #64748B;
  --slate-lt:   #F1F5F9;

  --radius:     14px;
  --radius-sm:  9px;
  --radius-xs:  6px;
  --shadow-sm:  0 1px 4px rgba(26,35,64,.06), 0 2px 12px rgba(26,35,64,.04);
  --shadow-md:  0 4px 20px rgba(26,35,64,.10), 0 1px 4px rgba(26,35,64,.06);
  --shadow-lg:  0 12px 40px rgba(26,35,64,.14);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html { scroll-behavior: smooth; }

body {
  font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
  background: var(--page);
  color: var(--ink);
  font-size: 13.5px;
  line-height: 1.55;
  -webkit-font-smoothing: antialiased;
}

/* ══════════════════════════════════════════
   TOPBAR
══════════════════════════════════════════ */
.topbar {
  background: var(--white);
  border-bottom: 1.5px solid var(--border);
  height: 64px;
  padding: 0 28px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: sticky;
  top: 0;
  z-index: 200;
  box-shadow: var(--shadow-sm);
}

.brand {
  display: flex;
  align-items: center;
  gap: 12px;
  text-decoration: none;
}
.brand-icon {
  width: 40px; height: 40px;
  background: linear-gradient(135deg, var(--blue) 0%, #6B7FFF 100%);
  border-radius: 11px;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 12px rgba(59,92,246,.35);
}
.brand-icon i { color: #fff; font-size: 18px; }
.brand-text {
  font-size: 16px;
  font-weight: 700;
  color: var(--ink);
  letter-spacing: -.3px;
}
.brand-text span { color: var(--blue); }

.topbar-right { display: flex; align-items: center; gap: 10px; }
.admin-chip {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 14px 6px 8px;
  background: var(--blue-lt);
  border-radius: 999px;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--blue);
}
.admin-chip .avi {
  width: 26px; height: 26px;
  background: var(--blue);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  color: #fff;
  font-size: 11px;
  font-weight: 700;
}
.btn-logout {
  display: flex; align-items: center; gap: 6px;
  padding: 7px 16px;
  border-radius: 8px;
  border: 1.5px solid var(--border2);
  background: var(--white);
  color: var(--ink2);
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  transition: all .18s;
}
.btn-logout:hover { border-color: var(--red); color: var(--red); background: var(--red-lt); }

/* ══════════════════════════════════════════
   PAGE LAYOUT
══════════════════════════════════════════ */
.page-wrap { padding: 28px 28px 48px; max-width: 1700px; margin: 0 auto; }

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 24px;
  gap: 16px;
  flex-wrap: wrap;
}
.page-title {
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -.5px;
  color: var(--ink);
}
.page-title span { color: var(--blue); }
.page-sub { font-size: 13px; color: var(--ink3); margin-top: 2px; font-weight: 400; }

/* ══════════════════════════════════════════
   TOOLBAR CARD
══════════════════════════════════════════ */
.toolbar-card {
  background: var(--white);
  border: 1.5px solid var(--border);
  border-radius: var(--radius);
  padding: 18px 20px;
  margin-bottom: 16px;
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
  box-shadow: var(--shadow-sm);
}

.search-wrap {
  position: relative;
  flex: 1 1 260px;
  max-width: 340px;
}
.search-wrap i {
  position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
  color: var(--ink4); font-size: 14px; pointer-events: none;
}
.search-wrap input {
  width: 100%;
  padding: 9px 12px 9px 38px;
  background: var(--page);
  border: 1.5px solid var(--border);
  border-radius: var(--radius-sm);
  font-family: inherit;
  font-size: 13px;
  color: var(--ink);
  transition: border-color .2s, box-shadow .2s;
}
.search-wrap input:focus { outline: none; border-color: var(--blue); box-shadow: 0 0 0 3px rgba(59,92,246,.1); background: var(--white); }
.search-wrap input::placeholder { color: var(--ink4); }

.ui-select {
  padding: 9px 12px;
  background: var(--page);
  border: 1.5px solid var(--border);
  border-radius: var(--radius-sm);
  font-family: inherit;
  font-size: 13px;
  color: var(--ink2);
  cursor: pointer;
  transition: border-color .2s;
}
.ui-select:focus { outline: none; border-color: var(--blue); }

.toolbar-sep { width: 1px; height: 32px; background: var(--border); flex-shrink: 0; }

.btn-filter-toggle {
  display: flex; align-items: center; gap: 7px;
  padding: 9px 14px;
  background: var(--page);
  border: 1.5px solid var(--border);
  border-radius: var(--radius-sm);
  font-family: inherit;
  font-size: 13px;
  font-weight: 500;
  color: var(--ink2);
  cursor: pointer;
  transition: all .18s;
  white-space: nowrap;
}
.btn-filter-toggle:hover, .btn-filter-toggle.active { border-color: var(--blue); color: var(--blue); background: var(--blue-lt); }
.filter-badge {
  background: var(--blue);
  color: #fff;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
  padding: 1px 7px;
  display: none;
}
.filter-badge.show { display: inline; }

.btn-group-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-left: auto; }

/* ══════════════════════════════════════════
   ACTION BUTTONS
══════════════════════════════════════════ */
.abtn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 9px 16px;
  border-radius: var(--radius-sm);
  font-family: inherit;
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all .18s;
  text-decoration: none;
  white-space: nowrap;
  letter-spacing: .1px;
}
.abtn i { font-size: 14px; }
.abtn:active { transform: scale(.97); }

.abtn-blue { background: var(--blue); color: #fff; }
.abtn-blue:hover { background: #2D4CE8; color: #fff; box-shadow: 0 4px 14px rgba(59,92,246,.4); transform: translateY(-1px); }

.abtn-green { background: var(--green); color: #fff; }
.abtn-green:hover { background: #129959; color: #fff; box-shadow: 0 4px 14px rgba(24,169,107,.4); transform: translateY(-1px); }

.abtn-coral { background: var(--coral); color: #fff; }
.abtn-coral:hover { background: #D95A2B; color: #fff; box-shadow: 0 4px 14px rgba(242,107,58,.4); transform: translateY(-1px); }

.abtn-outline-red {
  background: var(--red-lt);
  color: var(--red);
  border: 1.5px solid rgba(224,61,61,.25);
}
.abtn-outline-red:hover { background: var(--red); color: #fff; border-color: var(--red); }

.abtn-outline {
  background: var(--white);
  color: var(--ink2);
  border: 1.5px solid var(--border2);
}
.abtn-outline:hover { border-color: var(--blue); color: var(--blue); background: var(--blue-lt); }

.abtn-amber {
  background: var(--amber-lt);
  color: var(--amber);
  border: 1.5px solid rgba(232,160,32,.25);
}
.abtn-amber:hover { background: var(--amber); color: #fff; border-color: var(--amber); }

/* ══════════════════════════════════════════
   FILTER PANEL
══════════════════════════════════════════ */
.filter-panel {
  background: var(--white);
  border: 1.5px solid var(--border);
  border-radius: var(--radius);
  padding: 16px 20px;
  margin-bottom: 16px;
  display: none;
  gap: 14px;
  flex-wrap: wrap;
  align-items: flex-end;
  box-shadow: var(--shadow-sm);
}
.filter-panel.open { display: flex; }

.flt-group { display: flex; flex-direction: column; gap: 5px; flex: 1 1 140px; }
.flt-group label {
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .7px;
  color: var(--ink3);
}
.flt-group select, .flt-group input {
  padding: 8px 12px;
  background: var(--page);
  border: 1.5px solid var(--border);
  border-radius: var(--radius-sm);
  font-family: inherit;
  font-size: 13px;
  color: var(--ink2);
  transition: border-color .2s;
}
.flt-group select:focus, .flt-group input:focus { outline: none; border-color: var(--blue); }

.btn-clear-filters {
  padding: 8px 16px;
  background: transparent;
  border: 1.5px solid var(--border2);
  border-radius: var(--radius-sm);
  font-family: inherit;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--ink3);
  cursor: pointer;
  align-self: flex-end;
  transition: all .18s;
  display: flex; align-items: center; gap: 5px;
}
.btn-clear-filters:hover { border-color: var(--red); color: var(--red); background: var(--red-lt); }

/* ══════════════════════════════════════════
   TABLE CARD
══════════════════════════════════════════ */
.table-card {
  background: var(--white);
  border: 1.5px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
  box-shadow: var(--shadow-sm);
}

.table-scroll { overflow-x: auto; }
.table-scroll::-webkit-scrollbar { height: 5px; }
.table-scroll::-webkit-scrollbar-track { background: var(--page); }
.table-scroll::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 3px; }

table {
  width: 100%;
  border-collapse: collapse;
  min-width: 1020px;
}

/* THEAD */
thead tr {
  background: var(--page);
  border-bottom: 2px solid var(--border);
}
thead th {
  padding: 13px 18px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .7px;
  color: var(--ink3);
  white-space: nowrap;
  cursor: pointer;
  user-select: none;
  transition: color .15s;
}
thead th:hover { color: var(--ink); }
thead th .sort-ico { margin-left: 5px; font-size: 10px; opacity: .4; }
thead th.sort-asc .sort-ico::before { content:"↑"; opacity:1; color:var(--blue); }
thead th.sort-desc .sort-ico::before { content:"↓"; opacity:1; color:var(--blue); }
thead th:not(.sort-asc):not(.sort-desc) .sort-ico::before { content:"↕"; }
thead th.sort-asc, thead th.sort-desc { color: var(--blue); }

/* TBODY */
tbody tr {
  border-bottom: 1px solid var(--border);
  transition: background .14s;
  /* SIGNATURE: left-border status stripe */
  border-left: 3.5px solid transparent;
}
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: #F8FAFF; }

/* Status stripe colors */
tbody tr[data-status="Pending"]          { border-left-color: var(--ink4); }
tbody tr[data-status="Recommended"]      { border-left-color: var(--amber); }
tbody tr[data-status="Not Recommended"]  { border-left-color: var(--red); }
tbody tr[data-status="Approved"]         { border-left-color: var(--green); }
tbody tr[data-status="Rejected"]         { border-left-color: var(--red); }
tbody tr[data-status="Completed"]        { border-left-color: var(--blue); }
tbody tr[data-status="Cancelled"]        { border-left-color: var(--coral); }
tbody tr[data-status="Departure"]        { border-left-color: var(--teal); }

td {
  padding: 16px 18px;
  vertical-align: top;
}

/* ══════════════════════════════════════════
   CELL STYLES
══════════════════════════════════════════ */

/* ID Cell */
.cell-id {
  font-family: 'DM Mono', monospace;
  font-size: 12px;
  font-weight: 500;
  color: var(--ink3);
  background: var(--page);
  border: 1px solid var(--border);
  padding: 3px 9px;
  border-radius: 6px;
  white-space: nowrap;
}

/* Applicant */
.appl-name {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink);
  letter-spacing: -.2px;
}
.appl-sub {
  font-size: 12px;
  color: var(--ink3);
  margin-top: 1px;
  display: flex;
  align-items: center;
  gap: 4px;
}
.appl-sub i { font-size: 11px; }

/* Tags / Pills */
.pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 9px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: .1px;
  border: 1px solid transparent;
}
.pill-blue   { background: var(--blue-lt);   color: var(--blue);   border-color: var(--blue-mid); }
.pill-amber  { background: var(--amber-lt);  color: var(--amber);  border-color: rgba(232,160,32,.25); }
.pill-violet { background: var(--violet-lt); color: var(--violet); border-color: rgba(124,77,255,.2); }
.pill-slate  { background: var(--slate-lt);  color: var(--slate);  border-color: #CBD5E1; }
.pill-teal   { background: var(--teal-lt);   color: var(--teal);   border-color: rgba(8,145,178,.2); }

/* Journey cell */
.jcell { display: flex; flex-direction: column; gap: 7px; }
.jrow {
  display: flex;
  align-items: flex-start;
  gap: 7px;
  font-size: 12.5px;
  color: var(--ink2);
}
.jrow i { color: var(--ink4); font-size: 12px; flex-shrink: 0; margin-top: 2px; }
.jlabel {
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .5px;
  color: var(--ink3);
  min-width: 54px;
  flex-shrink: 0;
  margin-top: 2px;
}
.jval { color: var(--ink); font-size: 12.5px; }
.jdiv { border: none; border-top: 1px dashed var(--border); margin: 3px 0; }

.tour-view {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 11.5px; font-weight: 600;
  color: var(--blue);
  text-decoration: none;
  padding: 2px 8px;
  border-radius: 5px;
  background: var(--blue-lt);
  border: 1px solid var(--blue-mid);
  transition: all .15s;
}
.tour-view:hover { background: var(--blue); color: #fff; }
.tour-na { font-size: 11.5px; color: var(--ink4); }

/* Assignment cell */
.assign-block {
  background: var(--page);
  border: 1.5px solid var(--border);
  border-radius: 10px;
  padding: 10px 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.assign-section-label {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .6px;
  color: var(--ink4);
  margin-bottom: 2px;
}
.assign-name { font-size: 13px; font-weight: 600; color: var(--ink); }
.assign-detail { font-size: 12px; color: var(--ink3); display: flex; align-items: center; gap: 4px; }
.assign-detail i { font-size: 11px; }
.assign-divider { border: none; border-top: 1px solid var(--border); }

/* Security cell */
.sec-block { display: flex; flex-direction: column; gap: 6px; }
.sec-row {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12.5px;
  color: var(--ink2);
}
.sec-row i { font-size: 13px; width: 16px; flex-shrink: 0; }
.sec-label {
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .4px;
  color: var(--ink4);
  min-width: 52px;
}
.sec-val { color: var(--ink); font-weight: 500; }
.badge-km {
  background: var(--blue-lt);
  color: var(--blue);
  border: 1px solid var(--blue-mid);
  padding: 2px 9px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  font-family: 'DM Mono', monospace;
}
.badge-hrs {
  background: var(--violet-lt);
  color: var(--violet);
  border: 1px solid rgba(124,77,255,.2);
  padding: 2px 9px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  font-family: 'DM Mono', monospace;
}
.not-completed {
  display: flex; align-items: center; gap: 6px;
  font-size: 12px;
  color: var(--ink4);
  font-style: italic;
}

/* Status badges */
.sbadge {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 5px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 700;
  white-space: nowrap;
  border: 1.5px solid transparent;
}
.sbadge i { font-size: 11px; }
.sb-pending   { background: var(--slate-lt); color: var(--slate); border-color: #CBD5E1; }
.sb-recom     { background: var(--amber-lt); color: var(--amber); border-color: rgba(232,160,32,.3); }
.sb-notrecom  { background: var(--red-lt); color: var(--red); border-color: rgba(224,61,61,.25); }
.sb-approved  { background: var(--green-lt); color: var(--green); border-color: rgba(24,169,107,.25); }
.sb-rejected  { background: var(--red-lt); color: var(--red); border-color: rgba(224,61,61,.25); }
.sb-completed { background: var(--blue-lt); color: var(--blue); border-color: var(--blue-mid); }
.sb-cancelled { background: var(--coral-lt); color: var(--coral); border-color: rgba(242,107,58,.25); }
.sb-departure { background: var(--teal-lt); color: var(--teal); border-color: rgba(8,145,178,.2); }

/* Action buttons in table */
.tbl-btn {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 6px 12px;
  border-radius: 7px;
  font-family: inherit;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all .18s;
  white-space: nowrap;
}
.tbl-btn:active { transform: scale(.96); }
.tbl-btn-action {
  background: var(--blue-lt);
  color: var(--blue);
  border: 1.5px solid var(--blue-mid);
}
.tbl-btn-action:hover { background: var(--blue); color: #fff; box-shadow: 0 3px 10px rgba(59,92,246,.3); }
.tbl-btn-pdf {
  background: var(--page);
  color: var(--ink3);
  border: 1.5px solid var(--border2);
}
.tbl-btn-pdf:hover { background: var(--red-lt); color: var(--red); border-color: rgba(224,61,61,.3); }

/* ══════════════════════════════════════════
   PAGINATION
══════════════════════════════════════════ */
.pagi-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-top: 1.5px solid var(--border);
  background: var(--page);
  gap: 12px;
  flex-wrap: wrap;
  border-radius: 0 0 var(--radius) var(--radius);
}
.pagi-info { font-size: 12.5px; color: var(--ink3); font-weight: 500; }
.pagi-btns { display: flex; gap: 5px; align-items: center; }
.pg-btn {
  min-width: 34px; height: 34px;
  padding: 0 8px;
  background: var(--white);
  border: 1.5px solid var(--border2);
  border-radius: 8px;
  color: var(--ink3);
  font-size: 13px;
  font-family: inherit;
  font-weight: 600;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: all .15s;
}
.pg-btn:hover { border-color: var(--blue); color: var(--blue); background: var(--blue-lt); }
.pg-btn.active { background: var(--blue); border-color: var(--blue); color: #fff; box-shadow: 0 3px 10px rgba(59,92,246,.3); }
.pg-ellipsis { color: var(--ink4); padding: 0 4px; font-size: 13px; align-self: center; }

/* ══════════════════════════════════════════
   MODALS
══════════════════════════════════════════ */
.modal-content {
  border: 1.5px solid var(--border) !important;
  border-radius: var(--radius) !important;
  box-shadow: var(--shadow-lg) !important;
  overflow: hidden;
}
.modal-header {
  padding: 18px 22px !important;
  border-bottom: 1.5px solid var(--border) !important;
  background: var(--page) !important;
}
.modal-title {
  font-family: 'Plus Jakarta Sans', sans-serif !important;
  font-weight: 700 !important;
  font-size: 15px !important;
  color: var(--ink) !important;
  display: flex; align-items: center; gap: 8px;
}
.modal-body { padding: 22px !important; }
.modal-footer { padding: 14px 22px !important; border-top: 1.5px solid var(--border) !important; gap: 8px; }
.form-label {
  font-family: 'Plus Jakarta Sans', sans-serif !important;
  font-size: 12px !important;
  font-weight: 600 !important;
  color: var(--ink2) !important;
  margin-bottom: 6px !important;
  text-transform: uppercase !important;
  letter-spacing: .5px !important;
}
.form-control, .form-select {
  font-family: 'Plus Jakarta Sans', sans-serif !important;
  font-size: 13.5px !important;
  color: var(--ink) !important;
  border: 1.5px solid var(--border) !important;
  border-radius: var(--radius-sm) !important;
  padding: 9px 13px !important;
  background: var(--page) !important;
  transition: border-color .2s, box-shadow .2s !important;
}
.form-control:focus, .form-select:focus {
  border-color: var(--blue) !important;
  box-shadow: 0 0 0 3px rgba(59,92,246,.1) !important;
  background: var(--white) !important;
  outline: none !important;
}
textarea.form-control { resize: vertical; min-height: 80px; }

/* ══════════════════════════════════════════
   EXPORT LOADING OVERLAY
══════════════════════════════════════════ */
.export-overlay {
  position: fixed; inset: 0;
  background: rgba(26,35,64,.35);
  backdrop-filter: blur(6px);
  display: none;
  flex-direction: column; align-items: center; justify-content: center;
  z-index: 9999;
  gap: 14px;
}
.export-overlay.show { display: flex; }
.export-spinner {
  width: 46px; height: 46px;
  border: 3.5px solid var(--border2);
  border-top-color: var(--blue);
  border-radius: 50%;
  animation: spin .65s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.export-msg {
  font-size: 14px;
  font-weight: 600;
  color: var(--white);
  text-shadow: 0 1px 4px rgba(0,0,0,.2);
}

/* ══════════════════════════════════════════
   EMPTY STATE
══════════════════════════════════════════ */
.empty-state {
  padding: 56px 24px;
  text-align: center;
  color: var(--ink4);
}
.empty-state i { font-size: 44px; display: block; margin-bottom: 12px; }
.empty-state p { font-size: 14px; color: var(--ink3); }

/* ══════════════════════════════════════════
   RESPONSIVE
══════════════════════════════════════════ */
@media (max-width: 900px) {
  .page-wrap { padding: 16px; }
  .topbar { padding: 0 16px; }
  .toolbar-card { padding: 14px; gap: 10px; }
  .search-wrap { max-width: 100%; }
  .btn-group-actions { margin-left: 0; width: 100%; }
  table { font-size: 12.5px; }
  td, thead th { padding: 12px 14px; }
  .page-title { font-size: 18px; }
}

@media (max-width: 600px) {
  .brand-text { display: none; }
  .admin-chip span { display: none; }
  .abtn span { display: none; }
  .abtn { padding: 9px 10px; }
  .page-header { flex-direction: column; }
}

/* ══════════════════════════════════════════
   SCROLLBAR (global)
══════════════════════════════════════════ */
::-webkit-scrollbar { width: 7px; height: 7px; }
::-webkit-scrollbar-track { background: var(--page); }
::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: var(--ink4); }
</style>
</head>
<body>

<?php include('../header.php'); ?>

<!-- Export Overlay -->
<div class="export-overlay" id="exportOverlay">
  <div class="export-spinner"></div>
  <div class="export-msg" id="exportMsg">Generating…</div>
</div>

<div class="page-wrap">

  <!-- TOPBAR -->
  <div class="topbar" style="position:fixed;top:0;left:0;right:0;width:100%;">
    <a class="brand" href="">
      <div class="brand-icon"><i class="bi bi-truck-front-fill"></i></div>
      <span class="brand-text">Fleet<span>Admin</span></span>
    </a>
    <div class="topbar-right">
      <div class="admin-chip">
        <div class="avi">SA</div>
        <span>Super Admin</span>
      </div>
      <a href="logout.php" class="btn-logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
  </div>

  <!-- PAGE HEADER -->
  <div class="page-header">
    <div>
      <div class="page-title">Vehicle <span>Applications</span></div>
      <div class="page-sub">Manage, approve and track all vehicle requests</div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <button class="abtn abtn-green" data-bs-toggle="modal" data-bs-target="#addDriverModal">
        <i class="bi bi-person-plus-fill"></i><span>Add Driver</span>
      </button>
      <button class="abtn abtn-blue" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
        <i class="bi bi-truck"></i><span>Add Vehicle</span>
      </button>
    </div>
  </div>

  <!-- TOOLBAR -->
  <div class="toolbar-card">
    <div class="search-wrap">
      <i class="bi bi-search"></i>
      <input type="text" id="globalSearch" placeholder="Search by name, mobile or email…">
    </div>

    <select class="ui-select" id="rowsPerPage">
      <option value="10">10 per page</option>
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

  <!-- FILTER PANEL -->
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

  <!-- TABLE -->
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
if($res && $res->num_rows > 0){
  while($row = $res->fetch_assoc()){
    $driver = ($row['driver_id']) ? $conn->query("SELECT * FROM drivers WHERE id='".$row['driver_id']."'")->fetch_assoc() : null;
    $vehicle = ($row['vehicle_id']) ? $conn->query("SELECT * FROM vehicles WHERE id='".$row['vehicle_id']."'")->fetch_assoc() : null;
    $coTrav = implode(", ", array_filter([
      $row['cotraveler1']??null,$row['cotraveler2']??null,$row['cotraveler3']??null,
      $row['cotraveler4']??null,$row['cotraveler5']??null,$row['cotraveler6']??null,
    ]));
    $forwardedName = $row['forwarded_admin_name'] ?? "N/A";
    $designation = $row['forwarded_designation'] ?? "";
    $forwardedTo = $forwardedName . ($designation ? " ($designation)" : "");
    $totalHours = $row['total_hours'];
    if(empty($totalHours) && !empty($row['out_datetime']) && !empty($row['in_datetime'])) {
      $totalHours = round((strtotime($row['in_datetime']) - strtotime($row['out_datetime'])) / 3600, 2);
    }
    $status = $row['status'];
    $sMap = [
      'Pending'=>['sb-pending','hourglass-split'],
      'Recommended'=>['sb-recom','hand-thumbs-up-fill'],
      'Not Recommended'=>['sb-notrecom','hand-thumbs-down-fill'],
      'Approved'=>['sb-approved','check-circle-fill'],
      'Rejected'=>['sb-rejected','x-circle-fill'],
      'Completed'=>['sb-completed','flag-fill'],
      'Cancelled'=>['sb-cancelled','slash-circle-fill'],
      'Departure'=>['sb-departure','arrow-right-circle-fill'],
    ];
    [$sbClass,$sbIcon] = $sMap[$status] ?? ['sb-pending','info-circle'];
    $staffType = $row['staff_type'];
    $showAction = false;
    if($staffType=="Permanent" && $status!="Approved" && $status!="Rejected") $showAction=true;
    if($staffType=="Permanent" && $status=="Completed") $showAction=false;
    if($status=="Approved") $showAction=true;
    if($status=="Departure") $showAction=false;
    if($staffType=="Temporary" && $status=="Recommended") $showAction=true;
?>
<tr
  data-status="<?=htmlspecialchars($status)?>"
  data-staff-type="<?=htmlspecialchars($staffType)?>"
  data-booking-for="<?=htmlspecialchars($row['booking_for'])?>"
  data-date="<?=strtotime($row['datetime_from'])?>"
  data-cotraveler="<?=htmlspecialchars($coTrav)?>"
  data-forwarded="<?=htmlspecialchars($forwardedTo)?>"
  data-remark="<?=htmlspecialchars($row['remark']??'')?>"
  data-submitted="<?=htmlspecialchars($row['created_at'])?>"
  data-out-datetime="<?=htmlspecialchars($row['out_datetime']??'')?>"
  data-in-datetime="<?=htmlspecialchars($row['in_datetime']??'')?>"
  data-km-out="<?=htmlspecialchars($row['km_out']??'')?>"
  data-km-in="<?=htmlspecialchars($row['km_in']??'')?>"
  data-total-km="<?=htmlspecialchars($row['total_km']??'')?>"
  data-total-hours="<?=htmlspecialchars($totalHours??'')?>"
  data-security-guard="<?=htmlspecialchars($row['security_guard_name']??'')?>">

  <!-- # -->
  <td><span class="cell-id">#<?=$row['id']?></span></td>

  <!-- APPLICANT -->
  <td
    data-name="<?=htmlspecialchars($row['applicant_name'])?>"
    data-mobile="<?=htmlspecialchars($row['mobile'])?>"
    data-email="<?=htmlspecialchars($row['email'])?>"
    data-booking-for="<?=htmlspecialchars($row['booking_for'])?>"
    data-other-person="<?=htmlspecialchars($row['other_person_name']??'')?>"
    data-staff-type="<?=htmlspecialchars($staffType)?>">
    <div style="display:flex;flex-direction:column;gap:5px;min-width:160px">
      <div style="display:flex;gap:5px;flex-wrap:wrap">
        <span class="pill pill-slate"><?=htmlspecialchars($row['booking_for'])?></span>
        <span class="pill <?=$staffType=='Permanent'?'pill-blue':'pill-amber'?>"><?=$staffType?></span>
      </div>
      <?php if($row['booking_for']=="Someone" && !empty($row['other_person_name'])): ?>
        <div class="appl-name"><?=htmlspecialchars($row['other_person_name'])?></div>
        <div class="appl-sub"><i class="bi bi-person-fill"></i> <?=htmlspecialchars($row['applicant_name'])?></div>
      <?php else: ?>
        <div class="appl-name"><?=htmlspecialchars($row['applicant_name'])?></div>
      <?php endif; ?>
      <div class="appl-sub" style="margin-top:2px"><i class="bi bi-telephone-fill"></i> <?=htmlspecialchars($row['mobile'])?></div>
      <div class="appl-sub"><i class="bi bi-envelope-fill"></i> <?=htmlspecialchars($row['email'])?></div>
    </div>
  </td>

  <!-- JOURNEY -->
  <td
    data-from="<?=htmlspecialchars(date('d/m/Y H:i',strtotime($row['datetime_from'])))?>"
    data-to="<?=htmlspecialchars(date('d/m/Y H:i',strtotime($row['datetime_to'])))?>"
    data-place="<?=htmlspecialchars($row['place_of_visit'])?>"
    data-purpose="<?=htmlspecialchars($row['purpose'])?>"
    data-tour="<?=$row['tour_approved']==1?'Attached':'N/A'?>"
    data-date="<?=strtotime($row['datetime_from'])?>">
    <div class="jcell" style="min-width:210px">
      <div class="jrow">
        <i class="bi bi-file-earmark-check-fill" style="color:var(--blue)"></i>
        <span class="jlabel">Tour</span>
        <?php if($row['tour_approved']==1 && !empty($row['tour_order_pdf'])): ?>
          <a class="tour-view" href="view_tour_pdf.php?file=<?=urlencode($row['tour_order_pdf'])?>" target="_blank"><i class="bi bi-eye-fill"></i> View</a>
        <?php else: ?><span class="tour-na">N/A</span><?php endif; ?>
      </div>
      <div class="jrow"><i class="bi bi-calendar2-event-fill" style="color:var(--blue)"></i><span class="jlabel">From</span><span class="jval"><?=date("d/m/Y H:i",strtotime($row['datetime_from']))?></span></div>
      <div class="jrow"><i class="bi bi-calendar2-check-fill" style="color:var(--green)"></i><span class="jlabel">To</span><span class="jval"><?=date("d/m/Y H:i",strtotime($row['datetime_to']))?></span></div>
      <hr class="jdiv">
      <div class="jrow"><i class="bi bi-share-fill" style="color:var(--ink4)"></i><span class="jlabel">Fwd</span><span class="jval"><?=htmlspecialchars($row['forwarded_admin_name']??'N/A')?></span></div>
      <div class="jrow"><i class="bi bi-geo-alt" style="color:var(--green)"></i><span class="jlabel">Source</span><span class="jval"><?=htmlspecialchars($row['source']??'N/A')?></span></div>
      <div class="jrow"><i class="bi bi-geo-alt-fill" style="color:var(--coral)"></i><span class="jlabel">Dest</span><span class="jval"><?=htmlspecialchars($row['place_of_visit'])?></span></div>
      <div class="jrow"><i class="bi bi-chat-left-quote-fill" style="color:var(--ink4)"></i><span class="jlabel">Purpose</span><span class="jval"><?=htmlspecialchars($row['purpose'])?></span></div>
      <?php if(!empty($coTrav)): ?>
      <div class="jrow" style="align-items:flex-start"><i class="bi bi-people-fill" style="color:var(--violet)"></i><span class="jlabel">Co-Trav</span>
        <div style="display:flex;flex-wrap:wrap;gap:3px">
          <?php foreach(explode(", ",$coTrav) as $ct): ?><span class="pill pill-teal" style="font-size:10px"><?=htmlspecialchars($ct)?></span><?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </td>

  <!-- ASSIGNMENT -->
  <td
    data-driver="<?=htmlspecialchars($driver['driver_name']??'')?>"
    data-driver-mobile="<?=htmlspecialchars($driver['driver_mob']??'')?>"
    data-vehicle-type="<?=htmlspecialchars($vehicle['vehicle_type']??'')?>"
    data-vehicle-name="<?=htmlspecialchars($vehicle['vehicle_name']??'')?>"
    data-vehicle-no="<?=htmlspecialchars($vehicle['vehicle_no']??'')?>">
    <?php if($driver||$vehicle): ?>
    <div class="assign-block" style="min-width:150px">
      <?php if($driver): ?>
      <div>
        <div class="assign-section-label"><i class="bi bi-person-badge"></i> Driver</div>
        <div class="assign-name"><?=htmlspecialchars($driver['driver_name'])?></div>
        <div class="assign-detail"><i class="bi bi-telephone"></i><?=htmlspecialchars($driver['driver_mob'])?></div>
      </div>
      <?php endif; ?>
      <?php if($driver && $vehicle): ?><hr class="assign-divider"><?php endif; ?>
      <?php if($vehicle): ?>
      <div>
        <div class="assign-section-label"><i class="bi bi-truck"></i> Vehicle</div>
        <div class="assign-name"><?=htmlspecialchars($vehicle['vehicle_type'])?> — <?=htmlspecialchars($vehicle['vehicle_name'])?></div>
        <div class="assign-detail"><i class="bi bi-upc-scan"></i><?=htmlspecialchars($vehicle['vehicle_no'])?></div>
      </div>
      <?php endif; ?>
    </div>
    <?php else: ?><span style="color:var(--ink4);font-size:12.5px;font-style:italic">Not assigned</span><?php endif; ?>
  </td>

  <!-- SECURITY MOVEMENT -->
  <td>
    <?php if(!empty($row['out_datetime'])||!empty($row['in_datetime'])||!empty($row['km_out'])||!empty($row['security_guard_name'])): ?>
    <div class="sec-block" style="min-width:170px">
      <?php if(!empty($row['out_datetime'])): ?>
      <div class="sec-row"><i class="bi bi-box-arrow-right" style="color:var(--amber)"></i><span class="sec-label">Out</span><span class="sec-val"><?=date('d/m/Y H:i',strtotime($row['out_datetime']))?></span></div>
      <?php endif; ?>
      <?php if(!empty($row['in_datetime'])): ?>
      <div class="sec-row"><i class="bi bi-box-arrow-in-left" style="color:var(--teal)"></i><span class="sec-label">In</span><span class="sec-val"><?=date('d/m/Y H:i',strtotime($row['in_datetime']))?></span></div>
      <?php endif; ?>
      <?php if(!empty($row['km_out'])): ?>
      <div class="sec-row"><i class="bi bi-speedometer2" style="color:var(--green)"></i><span class="sec-label">KM Out</span><span class="sec-val"><?=$row['km_out']?></span></div>
      <?php endif; ?>
      <?php if(!empty($row['km_in'])): ?>
      <div class="sec-row"><i class="bi bi-speedometer2" style="color:var(--red)"></i><span class="sec-label">KM In</span><span class="sec-val"><?=$row['km_in']?></span></div>
      <?php endif; ?>
      <?php if(!empty($row['total_km'])): ?>
      <div class="sec-row"><i class="bi bi-graph-up-arrow" style="color:var(--blue)"></i><span class="sec-label">Total</span><span class="badge-km"><?=$row['total_km']?> km</span></div>
      <?php endif; ?>
      <?php if(!empty($totalHours)): ?>
      <div class="sec-row"><i class="bi bi-clock-history" style="color:var(--violet)"></i><span class="sec-label">Hours</span><span class="badge-hrs"><?=$totalHours?>h</span></div>
      <?php endif; ?>
      <?php if(!empty($row['security_guard_name'])): ?>
      <div class="sec-row"><i class="bi bi-shield-lock-fill" style="color:var(--ink3)"></i><span class="sec-label">Guard</span><span class="sec-val"><?=htmlspecialchars($row['security_guard_name'])?></span></div>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="not-completed"><i class="bi bi-dash-circle"></i> Not completed</div>
    <?php endif; ?>
  </td>

  <!-- STATUS -->
  <td><span class="sbadge <?=$sbClass?>"><i class="bi bi-<?=$sbIcon?>"></i><?=$status?></span></td>

  <!-- ACTIONS -->
  <td style="text-align:center">
    <div style="display:flex;gap:7px;justify-content:center;flex-wrap:wrap">
      <?php if($showAction): ?>
      <button class="tbl-btn tbl-btn-action" data-bs-toggle="modal" data-bs-target="#approveModal<?=$row['id']?>">
        <i class="bi bi-pencil-square"></i> Action
      </button>
      <?php endif; ?>
      <button class="tbl-btn tbl-btn-pdf exportRow" data-id="<?=$row['id']?>">
        <i class="bi bi-file-earmark-pdf"></i>
      </button>
    </div>
  </td>
</tr>

<!-- ACTION MODAL -->
<div class="modal fade" id="approveModal<?=$row['id']?>">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <div class="modal-title"><i class="bi bi-check2-square" style="color:var(--blue)"></i> Review Application #<?=$row['id']?></div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="app_id" value="<?=$row['id']?>">
          <div class="mb-3">
            <label class="form-label">Select Driver</label>
            <select name="driver_id" class="form-select">
              <option value="">— Available Drivers —</option>
              <?php $d=$conn->query("SELECT * FROM drivers"); while($dr=$d->fetch_assoc()) echo "<option value='".$dr['id']."'>".$dr['driver_name']." (".$dr['driver_mob'].")</option>"; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Select Vehicle</label>
            <select name="vehicle_id" class="form-select">
              <option value="">— Available Vehicles —</option>
              <?php $v=$conn->query("SELECT * FROM vehicles"); while($vh=$v->fetch_assoc()) echo "<option value='".$vh['id']."'>".$vh['vehicle_type']." — ".$vh['vehicle_name']." (".$vh['vehicle_no'].")</option>"; ?>
            </select>
          </div>
          <div class="mb-0">
            <label class="form-label">Remark</label>
            <textarea name="remark" class="form-control" placeholder="Optional remark…"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="abtn abtn-outline" data-bs-dismiss="modal">Cancel</button>
          <button name="reject" class="abtn abtn-outline-red"><i class="bi bi-x-circle-fill"></i> Reject</button>
          <button name="approve" class="abtn abtn-green"><i class="bi bi-check-circle-fill"></i> Approve</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php }} else { ?>
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

<!-- ADD DRIVER MODAL -->
<div class="modal fade" id="addDriverModal">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <div class="modal-title"><i class="bi bi-person-plus-fill" style="color:var(--green)"></i> Add New Driver</div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">Full Name</label><input type="text" name="driver_name" class="form-control" placeholder="Driver's full name" required></div>
          <div class="mb-0"><label class="form-label">Mobile Number</label><input type="text" name="driver_mob" class="form-control" placeholder="10-digit number" required></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="abtn abtn-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="add_driver" class="abtn abtn-green"><i class="bi bi-check-circle-fill"></i> Save Driver</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ADD VEHICLE MODAL -->
<div class="modal fade" id="addVehicleModal">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <div class="modal-title"><i class="bi bi-truck" style="color:var(--blue)"></i> Add New Vehicle</div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">Vehicle Type</label><select name="vehicle_type" class="form-select" required><option>Car</option><option>Bus</option></select></div>
          <div class="mb-3"><label class="form-label">Vehicle Name</label><input type="text" name="vehicle_name" class="form-control" placeholder="e.g. Toyota Innova" required></div>
          <div class="mb-0"><label class="form-label">Registration Number</label><input type="text" name="vehicle_no" class="form-control" placeholder="e.g. MH 12 AB 1234" required></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="abtn abtn-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="add_vehicle" class="abtn abtn-blue"><i class="bi bi-check-circle-fill"></i> Save Vehicle</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
<script>
/* ══════════════════════════════════════════
   TABLE ENGINE
══════════════════════════════════════════ */
(function(){
  const tbody   = document.getElementById("tableBody");
  const searchEl= document.getElementById("globalSearch");
  const rowsEl  = document.getElementById("rowsPerPage");
  const pagiInfo= document.getElementById("pagiInfo");
  const pagiCont= document.getElementById("pagiContainer");
  const fStatus = document.getElementById("fStatus");
  const fStaff  = document.getElementById("fStaffType");
  const fBook   = document.getElementById("fBookingFor");
  const fFrom   = document.getElementById("fDateFrom");
  const fTo     = document.getElementById("fDateTo");
  const badge   = document.getElementById("filterBadge");
  const panel   = document.getElementById("filterPanel");
  const toggleBtn=document.getElementById("toggleFilters");

  let allRows = Array.from(tbody.querySelectorAll("tr[data-status]"));
  let filtered= [...allRows];
  let page=1, perPage=10, sortCol=-1, sortDir=1;

  /* Toggle filter panel */
  toggleBtn.addEventListener("click",()=>{
    panel.classList.toggle("open");
    toggleBtn.classList.toggle("active");
  });
  document.getElementById("clearFilters").addEventListener("click",()=>{
    fStatus.value=fStaff.value=fBook.value=fFrom.value=fTo.value="";
    apply();
  });

  /* Sort on header click */
  document.querySelectorAll("thead th.sortable").forEach(th=>{
    th.addEventListener("click",()=>{
      const col=parseInt(th.dataset.col);
      if(sortCol===col) sortDir*=-1; else{sortCol=col;sortDir=1;}
      document.querySelectorAll("thead th").forEach(t=>t.classList.remove("sort-asc","sort-desc"));
      th.classList.add(sortDir===1?"sort-asc":"sort-desc");
      apply();
    });
  });

  function colVal(tr,col){
    const tds=tr.querySelectorAll("td");
    switch(col){
      case 0: return parseInt(tr.querySelector(".cell-id")?.textContent.replace("#",""))||0;
      case 1: return (tds[1]?.dataset.name||"").toLowerCase();
      case 2: return parseInt(tds[2]?.dataset.date)||0;
      case 5: return (tr.dataset.status||"").toLowerCase();
      default: return (tds[col]?.innerText||"").toLowerCase();
    }
  }

  function apply(){
    const kw=(searchEl.value||"").toLowerCase().trim();
    const dfrom=fFrom.value?new Date(fFrom.value).getTime()/1000:null;
    const dto=fTo.value?new Date(fTo.value).getTime()/1000+86399:null;
    let active=0;
    if(fStatus.value)active++;if(fStaff.value)active++;if(fBook.value)active++;
    if(fFrom.value)active++;if(fTo.value)active++;
    badge.textContent=active;
    badge.classList.toggle("show",active>0);

    filtered=allRows.filter(tr=>{
      const td1=tr.querySelector("td[data-name]");
      if(kw){
        const n=(td1?.dataset.name||"").toLowerCase();
        const m=(td1?.dataset.mobile||"").toLowerCase();
        const e=(td1?.dataset.email||"").toLowerCase();
        if(!n.includes(kw)&&!m.includes(kw)&&!e.includes(kw)) return false;
      }
      if(fStatus.value&&tr.dataset.status!==fStatus.value) return false;
      if(fStaff.value&&tr.dataset.staffType!==fStaff.value) return false;
      if(fBook.value&&(td1?.dataset.bookingFor||tr.dataset.bookingFor)!==fBook.value) return false;
      const d=parseInt(tr.dataset.date)||0;
      if(dfrom&&d<dfrom) return false;
      if(dto&&d>dto) return false;
      return true;
    });

    if(sortCol>=0){
      filtered.sort((a,b)=>{
        const va=colVal(a,sortCol),vb=colVal(b,sortCol);
        return(typeof va==="number"?(va-vb):va.localeCompare(vb))*sortDir;
      });
    }
    page=1; render();
  }

  function render(){
    perPage=parseInt(rowsEl.value);
    const total=filtered.length;
    const pages=Math.ceil(total/perPage)||1;
    if(page>pages) page=pages;
    const start=(page-1)*perPage, end=Math.min(start+perPage,total);

    tbody.innerHTML="";
    if(!filtered.length){
      tbody.innerHTML=`<tr><td colspan="7"><div class="empty-state"><i class="bi bi-search"></i><p>No records match your filters.</p></div></td></tr>`;
    } else {
      filtered.slice(start,end).forEach(tr=>tbody.appendChild(tr));
    }

    pagiInfo.textContent=total?`Showing ${start+1}–${end} of ${total} records`:"No records";
    buildPagi(pages);
  }

  function buildPagi(pages){
    pagiCont.innerHTML="";
    const mkBtn=(lbl,p,cls="")=>{
      const b=document.createElement("button");
      b.className="pg-btn"+(cls?" "+cls:"");
      b.innerHTML=lbl;
      b.addEventListener("click",()=>{page=p;render();});
      pagiCont.appendChild(b);
    };
    mkBtn('<i class="bi bi-chevron-double-left"></i>',1);
    mkBtn('<i class="bi bi-chevron-left"></i>',Math.max(1,page-1));
    const lo=Math.max(1,page-2),hi=Math.min(pages,page+2);
    if(lo>1){const s=document.createElement("span");s.className="pg-ellipsis";s.textContent="…";pagiCont.appendChild(s);}
    for(let i=lo;i<=hi;i++) mkBtn(i,i,i===page?"active":"");
    if(hi<pages){const s=document.createElement("span");s.className="pg-ellipsis";s.textContent="…";pagiCont.appendChild(s);}
    mkBtn('<i class="bi bi-chevron-right"></i>',Math.min(pages,page+1));
    mkBtn('<i class="bi bi-chevron-double-right"></i>',pages);
  }

  searchEl.addEventListener("input",apply);
  rowsEl.addEventListener("change",()=>{page=1;render();});
  [fStatus,fStaff,fBook,fFrom,fTo].forEach(el=>el.addEventListener("change",apply));
  apply();

  window.__getFiltered=()=>filtered;
})();

/* ══════════════════════════════════════════
   EXPORT UTILS
══════════════════════════════════════════ */
const showLoad=msg=>{document.getElementById("exportMsg").textContent=msg;document.getElementById("exportOverlay").classList.add("show");};
const hideLoad=()=>document.getElementById("exportOverlay").classList.remove("show");

function rowData(tr){
  const tds=tr.querySelectorAll("td");
  const g=(el,a)=>el?.getAttribute(a)||"";
  return{
    id:tds[0]?.innerText.trim().replace("#",""),
    bookingFor:g(tds[1],"data-booking-for"),otherPerson:g(tds[1],"data-other-person"),
    name:g(tds[1],"data-name"),mobile:g(tds[1],"data-mobile"),email:g(tds[1],"data-email"),staffType:g(tds[1],"data-staff-type"),
    from:g(tds[2],"data-from"),to:g(tds[2],"data-to"),place:g(tds[2],"data-place"),purpose:g(tds[2],"data-purpose"),tour:g(tds[2],"data-tour"),
    forwarded:tr.dataset.forwarded||"",cotraveler:tr.dataset.cotraveler||"",
    driver:g(tds[3],"data-driver"),driverMob:g(tds[3],"data-driver-mobile"),
    vehicleType:g(tds[3],"data-vehicle-type"),vehicleName:g(tds[3],"data-vehicle-name"),vehicleNo:g(tds[3],"data-vehicle-no"),
    outDt:tr.dataset.outDatetime||"",inDt:tr.dataset.inDatetime||"",
    kmOut:tr.dataset.kmOut||"",kmIn:tr.dataset.kmIn||"",totalKm:tr.dataset.totalKm||"",totalHours:tr.dataset.totalHours||"",
    security:tr.dataset.securityGuard||"",remark:tr.dataset.remark||"",
    status:tds[5]?.innerText.trim()||"",submitted:tr.dataset.submitted||""
  };
}

function makePDF(rows){
  const{jsPDF}=window.jspdf;
  const doc=new jsPDF("p","pt","a4");
  const pw=doc.internal.pageSize.getWidth();
  rows.forEach((tr,i)=>{
    if(i>0) doc.addPage();
    const d=rowData(tr);
    doc.setFontSize(16);doc.setTextColor(59,92,246);doc.setFont("helvetica","bold");
    doc.text(`Vehicle Application  #${d.id}`,pw/2,38,{align:"center"});
    doc.setDrawColor(59,92,246);doc.setLineWidth(.5);doc.line(40,46,pw-40,46);
    const body=[
      ["Booking For",d.bookingFor],["Other Person",d.otherPerson],["Applicant",d.name],
      ["Staff Type",d.staffType],["Mobile",d.mobile],["Email",d.email],
      ["Journey From",d.from],["Journey To",d.to],["Place of Visit",d.place],
      ["Purpose",d.purpose],["Tour Order",d.tour],
      ["Forwarded To",d.forwarded],["Co-Travelers",d.cotraveler],
      ["Driver",d.driver],["Driver Mobile",d.driverMob],
      ["Vehicle Type",d.vehicleType],["Vehicle Name",d.vehicleName],["Vehicle No",d.vehicleNo],
      ["Departure",d.outDt],["Return",d.inDt],
      ["KM Out",d.kmOut],["KM In",d.kmIn],["Total KM",d.totalKm],["Total Hours",d.totalHours],
      ["Security Guard",d.security],["Remark",d.remark],
      ["Status",d.status],["Submitted",d.submitted]
    ].filter(r=>r[1]);
    doc.autoTable({
      startY:58,head:[["Field","Details"]],body,theme:"grid",
      styles:{fontSize:9.5,cellPadding:5,font:"helvetica"},
      headStyles:{fillColor:[59,92,246],textColor:255,fontStyle:"bold"},
      alternateRowStyles:{fillColor:[247,248,252]},
      columnStyles:{0:{fontStyle:"bold",cellWidth:130,textColor:[61,79,110]}}
    });
  });
  return doc;
}

document.addEventListener("click",e=>{
  const btn=e.target.closest(".exportRow");
  if(!btn) return;
  const tr=btn.closest("tr");
  if(!tr) return;
  showLoad("Generating PDF…");
  setTimeout(()=>{const d=rowData(tr);makePDF([tr]).save(`application_${d.id}.pdf`);hideLoad();},80);
});

document.getElementById("exportAllPDF").addEventListener("click",()=>{
  const rows=window.__getFiltered?window.__getFiltered():Array.from(document.querySelectorAll("tbody tr[data-status]"));
  if(!rows.length){alert("No records to export.");return;}
  showLoad(`Building PDF for ${rows.length} records…`);
  setTimeout(()=>{makePDF(rows).save("All_Vehicle_Applications.pdf");hideLoad();},80);
});

document.getElementById("exportAllCompletedPDF").addEventListener("click", () => {

  const all = window.__getFiltered
      ? window.__getFiltered()
      : Array.from(document.querySelectorAll("tbody tr[data-status]"));

  const rows = all.filter(tr =>
      (tr.dataset.status || "").trim().toLowerCase() === "completed"
  );

  console.log("Completed rows:", rows.length);

  if (!rows.length) {
      alert("No Completed applications found.");
      return;
  }

  showLoad(`Building PDF for ${rows.length} completed records…`);

  setTimeout(() => {
      makePDF(rows).save("Completed_Vehicle_Applications.pdf");
      hideLoad();
  }, 80);
});

document.getElementById("exportAllCSV").addEventListener("click",()=>{
  const rows=window.__getFiltered?window.__getFiltered():Array.from(document.querySelectorAll("tbody tr[data-status]"));
  if(!rows.length){alert("No records to export.");return;}
  showLoad("Generating CSV…");
  setTimeout(()=>{
    const hdrs=["App ID","Booking For","Other Person","Applicant","Mobile","Email","Staff Type",
      "Journey From","Journey To","Place","Purpose","Tour Order","Forwarded To","Co-Travelers",
      "Driver","Driver Mobile","Vehicle Type","Vehicle Name","Vehicle No",
      "Departure","Return","KM Out","KM In","Total KM","Total Hours","Security Guard","Remark","Status","Submitted"];
    const fmtD=v=>{if(!v)return"";try{const d=new Date(v);return isNaN(d)?v:d.toLocaleString("en-IN",{day:"2-digit",month:"2-digit",year:"numeric",hour:"2-digit",minute:"2-digit",hour12:true})}catch{return v}};
    const esc=v=>`"${String(v||"").replace(/"/g,'""')}"`;
    let csv="\uFEFF"+hdrs.map(esc).join(",")+"\n";
    rows.forEach(tr=>{
      const d=rowData(tr);
      csv+=[esc(d.id),esc(d.bookingFor),esc(d.otherPerson),esc(d.name),`="${d.mobile}"`,esc(d.email),esc(d.staffType),
        esc(d.from),esc(d.to),esc(d.place),esc(d.purpose),esc(d.tour),esc(d.forwarded),esc(d.cotraveler),
        esc(d.driver),`="${d.driverMob}"`,esc(d.vehicleType),esc(d.vehicleName),esc(d.vehicleNo),
        esc(fmtD(d.outDt)),esc(fmtD(d.inDt)),esc(d.kmOut),esc(d.kmIn),esc(d.totalKm),esc(d.totalHours),
        esc(d.security),esc(d.remark),esc(d.status),esc(fmtD(d.submitted))].join(",")+"\n";
    });
    const ts=new Date().toISOString().slice(0,16).replace(/[-:T]/g,"_");
    const a=document.createElement("a");
    a.href=URL.createObjectURL(new Blob([csv],{type:"text/csv;charset=utf-8;"}));
    a.download=`vehicle_applications_${rows.length}_${ts}.csv`;
    a.click();hideLoad();
  },80);
});
</script>
</body>
</html>

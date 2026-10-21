<?php
session_start();
include("../config/db.php");

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function hired_csrf_token(): string {
    if (empty($_SESSION['hired_csrf'])) $_SESSION['hired_csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['hired_csrf'];
}
function hired_csrf_ok(): bool {
    return isset($_POST['csrf'], $_SESSION['hired_csrf']) && hash_equals($_SESSION['hired_csrf'], (string)$_POST['csrf']);
}

// AUTH GUARD
if (!isset($_SESSION['hired_user_id']) || ($_SESSION['hired_role'] ?? '') !== 'hired_super_admin') {
    header("Location: hired_login.php"); exit;
}
$adminName = $_SESSION['hired_user_name'] ?? 'Super Admin';

// HANDLE AJAX CRUD ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    if (!hired_csrf_ok()) { echo json_encode(['ok' => false, 'msg' => 'Session expired.']); exit; }
    $action = $_POST['ajax'];

    // --- DRIVERS CRUD ---
    if ($action === 'save_driver') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['driver_name'] ?? '');
        $mob = trim($_POST['driver_mob'] ?? '');
        $status = $_POST['status'] === 'Inactive' ? 'Inactive' : 'Active';
        
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE hired_drivers SET driver_name=?, driver_mob=?, status=? WHERE id=?");
            $stmt->bind_param("sssi", $name, $mob, $status, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO hired_drivers (driver_name, driver_mob, status) VALUES (?,?,?)");
            $stmt->bind_param("sss", $name, $mob, $status);
        }
        echo json_encode(['ok' => $stmt->execute()]); exit;
    }
    if ($action === 'delete_driver') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM hired_drivers WHERE id=?");
        $stmt->bind_param("i", $id);
        echo json_encode(['ok' => $stmt->execute()]); exit;
    }

    // --- VEHICLES CRUD ---
    if ($action === 'save_vehicle') {
        $id = (int)($_POST['id'] ?? 0);
        $no = trim($_POST['vehicle_no'] ?? '');
        $name = trim($_POST['vehicle_name'] ?? '');
        $status = $_POST['status'] === 'Inactive' ? 'Inactive' : 'Active';
        
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE hired_vehicles_detail SET vehicle_no=?, vehicle_name=?, status=? WHERE id=?");
            $stmt->bind_param("sssi", $no, $name, $status, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO hired_vehicles_detail (vehicle_no, vehicle_name, status) VALUES (?,?,?)");
            $stmt->bind_param("sss", $no, $name, $status);
        }
        echo json_encode(['ok' => $stmt->execute()]); exit;
    }
    if ($action === 'delete_vehicle') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM hired_vehicles_detail WHERE id=?");
        $stmt->bind_param("i", $id);
        echo json_encode(['ok' => $stmt->execute()]); exit;
    }
}

// FETCH DATA
$drivers = [];
$dRes = $conn->query("SELECT * FROM hired_drivers ORDER BY driver_name ASC");
if ($dRes) { while ($r = $dRes->fetch_assoc()) $drivers[] = $r; }

$vehicles = [];
$vRes = $conn->query("SELECT * FROM hired_vehicles_detail ORDER BY vehicle_no ASC");
if ($vRes) { while ($r = $vRes->fetch_assoc()) $vehicles[] = $r; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Drivers & Vehicles</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
  body { background:#f5f7f8; font-family: Inter, system-ui, sans-serif; }
  .page-wrap { max-width: 1200px; margin: 20px auto; padding: 0 15px; }
  .card-custom { background:#fff; border:1px solid #e3e6ea; border-radius:12px; padding:20px; margin-bottom:20px; }
  .table th { font-size: 0.8rem; text-transform: uppercase; color: #5b6b73; }
  .table td { vertical-align: middle; }
  .btn-teal { background:#0A5F67; color:#fff; border:none; }
  .btn-teal:hover { background:#084a51; color:#fff; }
</style>
</head>
<body>

<div class="page-wrap">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h3 class="mb-1" style="font-weight:700;">Drivers & Vehicles Registry</h3>
      <p class="text-muted mb-0">Manage the master list of drivers and vehicles available for assignment.</p>
    </div>
    <a href="hired_super_admin_dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
  </div>

  <div class="row g-4">
    <!-- DRIVERS -->
    <div class="col-lg-6">
      <div class="card-custom h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="mb-0"><i class="bi bi-person-badge me-2"></i>Drivers</h5>
          <button class="btn btn-sm btn-teal" onclick="openDriverModal()"><i class="bi bi-plus-lg"></i> Add Driver</button>
        </div>
        <div class="table-responsive">
          <table class="table table-hover">
            <thead><tr><th>Name</th><th>Mobile</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
              <?php foreach ($drivers as $d): ?>
              <tr>
                <td><?= h($d['driver_name']) ?></td>
                <td><?= h($d['driver_mob']) ?></td>
                <td><span class="badge bg-<?= $d['status']==='Active'?'success':'secondary' ?>"><?= h($d['status']) ?></span></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-primary" onclick='openDriverModal(<?= json_encode($d) ?>)'><i class="bi bi-pencil"></i></button>
                  <button class="btn btn-sm btn-outline-danger" onclick="deleteDriver(<?= $d['id'] ?>)"><i class="bi bi-trash"></i></button>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if(!$drivers): ?><tr><td colspan="4" class="text-center text-muted">No drivers found.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- VEHICLES -->
    <div class="col-lg-6">
      <div class="card-custom h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="mb-0"><i class="bi bi-truck me-2"></i>Vehicles</h5>
          <button class="btn btn-sm btn-teal" onclick="openVehicleModal()"><i class="bi bi-plus-lg"></i> Add Vehicle</button>
        </div>
        <div class="table-responsive">
          <table class="table table-hover">
            <thead><tr><th>Vehicle No</th><th>Model/Name</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
              <?php foreach ($vehicles as $v): ?>
              <tr>
                <td><strong><?= h($v['vehicle_no']) ?></strong></td>
                <td><?= h($v['vehicle_name']) ?></td>
                <td><span class="badge bg-<?= $v['status']==='Active'?'success':'secondary' ?>"><?= h($v['status']) ?></span></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-primary" onclick='openVehicleModal(<?= json_encode($v) ?>)'><i class="bi bi-pencil"></i></button>
                  <button class="btn btn-sm btn-outline-danger" onclick="deleteVehicle(<?= $v['id'] ?>)"><i class="bi bi-trash"></i></button>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if(!$vehicles): ?><tr><td colspan="4" class="text-center text-muted">No vehicles found.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Driver Modal -->
<div class="modal fade" id="driverModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="driverForm">
      <input type="hidden" name="id" id="dId">
      <div class="modal-header"><h5 class="modal-title">Driver</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Driver Name</label><input type="text" class="form-control" name="driver_name" id="dName" required></div>
        <div class="mb-3"><label class="form-label">Mobile</label><input type="text" class="form-control" name="driver_mob" id="dMob" maxlength="10" required></div>
        <div class="mb-3"><label class="form-label">Status</label><select class="form-select" name="status" id="dStatus"><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-teal">Save</button></div>
    </form>
  </div>
</div>

<!-- Vehicle Modal -->
<div class="modal fade" id="vehicleModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="vehicleForm">
      <input type="hidden" name="id" id="vId">
      <div class="modal-header"><h5 class="modal-title">Vehicle</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Vehicle Number</label><input type="text" class="form-control" name="vehicle_no" id="vNo" required></div>
        <div class="mb-3"><label class="form-label">Model/Name</label><input type="text" class="form-control" name="vehicle_name" id="vName"></div>
        <div class="mb-3"><label class="form-label">Status</label><select class="form-select" name="status" id="vStatus"><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-teal">Save</button></div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const CSRF = '<?= hired_csrf_token() ?>';
const dModal = new bootstrap.Modal(document.getElementById('driverModal'));
const vModal = new bootstrap.Modal(document.getElementById('vehicleModal'));

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

document.getElementById('driverForm').addEventListener('submit', async e => {
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append('ajax', 'save_driver');
  fd.append('csrf', CSRF);
  const res = await fetch(location.href, { method: 'POST', body: fd }).then(r=>r.json());
  if(res.ok) location.reload(); else alert('Error saving driver');
});

document.getElementById('vehicleForm').addEventListener('submit', async e => {
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append('ajax', 'save_vehicle');
  fd.append('csrf', CSRF);
  const res = await fetch(location.href, { method: 'POST', body: fd }).then(r=>r.json());
  if(res.ok) location.reload(); else alert('Error saving vehicle');
});

async function deleteDriver(id) {
  if(!confirm('Delete this driver?')) return;
  const fd = new FormData();
  fd.append('ajax', 'delete_driver'); fd.append('csrf', CSRF); fd.append('id', id);
  await fetch(location.href, { method: 'POST', body: fd });
  location.reload();
}

async function deleteVehicle(id) {
  if(!confirm('Delete this vehicle?')) return;
  const fd = new FormData();
  fd.append('ajax', 'delete_vehicle'); fd.append('csrf', CSRF); fd.append('id', id);
  await fetch(location.href, { method: 'POST', body: fd });
  location.reload();
}
</script>
</body>
</html>
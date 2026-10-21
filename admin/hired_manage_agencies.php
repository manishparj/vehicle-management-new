<?php
session_start();
include("../config/db.php");

// ================= AUTH GUARD =================
if (!isset($_SESSION['hired_user_id']) || ($_SESSION['hired_role'] ?? '') !== 'hired_super_admin') {
    header("Location: hired_login.php");
    exit;
}

$adminName = $_SESSION['hired_user_name'] ?? 'Super Admin';

// ================= HELPERS =================
function redirectWith($type, $msg) {
    $_SESSION['flash_type'] = $type;
    $_SESSION['flash_msg']  = $msg;
    header("Location: hired_manage_agencies.php");
    exit;
}
function isValidMobile($m) { return preg_match('/^[6-9]\d{9}$/', $m); }
function isValidEmail($e)  { return filter_var($e, FILTER_VALIDATE_EMAIL) !== false; }

// ================= HANDLE ACTIONS =================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---------- CREATE ----------
    if ($action === 'create') {
        $name     = trim($_POST['agency_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $mobile   = trim($_POST['mobile'] ?? '');
        $password = $_POST['password'] ?? '';
        $status   = trim($_POST['status'] ?? 'Active');

        $errors = [];
        if ($name === '')                              $errors[] = 'Agency name is required.';
        if ($email === '' || !isValidEmail($email))    $errors[] = 'A valid email is required.';
        if ($mobile === '' || !isValidMobile($mobile)) $errors[] = 'A valid 10-digit mobile is required.';
        if (strlen($password) < 6)                     $errors[] = 'Password must be at least 6 characters.';
        if (!in_array($status, ['Active', 'Inactive'], true)) $errors[] = 'Invalid status.';

        if (empty($errors)) {
            $chk = $conn->prepare("SELECT id FROM hired_agencies WHERE email = ? OR mobile = ?");
            $chk->bind_param("ss", $email, $mobile);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) $errors[] = 'Email or Mobile is already registered.';
            $chk->close();
        }

        if (!empty($errors)) redirectWith('danger', implode(' ', $errors));

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO hired_agencies (agency_name, email, mobile, password, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $email, $mobile, $hash, $status);
        
        if ($stmt->execute()) {
            redirectWith('success', "Agency <strong>" . htmlspecialchars($name) . "</strong> created successfully.");
        } else {
            redirectWith('danger', 'Could not create agency.');
        }
        $stmt->close();
    }

    // ---------- UPDATE ----------
    if ($action === 'update') {
        $id       = (int)($_POST['id'] ?? 0);
        $name     = trim($_POST['agency_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $mobile   = trim($_POST['mobile'] ?? '');
        $password = $_POST['password'] ?? '';
        $status   = trim($_POST['status'] ?? 'Active');

        $errors = [];
        if ($id <= 0)                                  $errors[] = 'Invalid ID.';
        if ($name === '')                              $errors[] = 'Agency name is required.';
        if ($email === '' || !isValidEmail($email))    $errors[] = 'A valid email is required.';
        if ($mobile === '' || !isValidMobile($mobile)) $errors[] = 'A valid 10-digit mobile is required.';
        if ($password !== '' && strlen($password) < 6) $errors[] = 'New password must be at least 6 characters.';
        if (!in_array($status, ['Active', 'Inactive'], true)) $errors[] = 'Invalid status.';

        if (empty($errors)) {
            $chk = $conn->prepare("SELECT id FROM hired_agencies WHERE (email = ? OR mobile = ?) AND id <> ?");
            $chk->bind_param("ssi", $email, $mobile, $id);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) $errors[] = 'Email or Mobile is already used by another agency.';
            $chk->close();
        }

        if (!empty($errors)) redirectWith('danger', implode(' ', $errors));

        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE hired_agencies SET agency_name=?, email=?, mobile=?, password=?, status=? WHERE id=?");
            $stmt->bind_param("sssssi", $name, $email, $mobile, $hash, $status, $id);
        } else {
            $stmt = $conn->prepare("UPDATE hired_agencies SET agency_name=?, email=?, mobile=?, status=? WHERE id=?");
            $stmt->bind_param("ssssi", $name, $email, $mobile, $status, $id);
        }

        if ($stmt->execute()) {
            redirectWith('success', "Agency <strong>" . htmlspecialchars($name) . "</strong> updated successfully.");
        } else {
            redirectWith('danger', 'Could not update agency.');
        }
        $stmt->close();
    }

    // ---------- DELETE ----------
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) redirectWith('danger', 'Invalid ID.');

        $stmt = $conn->prepare("DELETE FROM hired_agencies WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            redirectWith('success', 'Agency deleted successfully.');
        } else {
            redirectWith('danger', 'Could not delete agency.');
        }
        $stmt->close();
    }
}

// ================= FLASH =================
$flashType = $_SESSION['flash_type'] ?? '';
$flashMsg  = $_SESSION['flash_msg']  ?? '';
unset($_SESSION['flash_type'], $_SESSION['flash_msg']);

// ================= FETCH LIST =================
$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$sql = "SELECT * FROM hired_agencies WHERE 1=1";
$params = [];
$types  = '';

if ($search !== '') {
    $sql .= " AND (agency_name LIKE ? OR email LIKE ? OR mobile LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'sss';
}
if ($statusFilter !== '' && in_array($statusFilter, ['Active', 'Inactive'], true)) {
    $sql .= " AND status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}
$sql .= " ORDER BY agency_name ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ================= COUNTS =================
$counts = ['Active' => 0, 'Inactive' => 0];
$cRes = $conn->query("SELECT status, COUNT(*) as c FROM hired_agencies GROUP BY status");
while ($r = $cRes->fetch_assoc()) $counts[$r['status']] = (int)$r['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Agencies — Hired Vehicle</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
<style>
:root{--navy:#0F2A43;--teal:#0E7C86;--teal-dark:#0A5F67;--paper:#F5F8F9;--card:#fff;--line:#E1E8EC;--tm:#5B6B7A}
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
.icon-success{background:#D1E7DD;color:#0F5132}
.icon-danger{background:#F8D7DA;color:#842029}
.form-control,.form-select{border-color:var(--line);padding:.55rem .8rem;font-size:.92rem}
.form-control:focus,.form-select:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(14,124,134,.14)}
.form-label{font-weight:600;font-size:.85rem;color:var(--navy);margin-bottom:5px}
.btn-teal{background:var(--teal);color:#fff;font-weight:600;border:none}
.btn-teal:hover{background:var(--teal-dark);color:#fff}
.table-clean{font-size:.88rem}
.table-clean thead th{background:#F8FAFB;color:var(--tm);font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;font-weight:700;border-bottom:1px solid var(--line);padding:12px 14px}
.table-clean tbody td{padding:12px 14px;vertical-align:middle;border-color:var(--line)}
.badge-status{font-size:.72rem;font-weight:700;padding:4px 10px;border-radius:999px;white-space:nowrap}
.badge-status.active{background:#D1E7DD;color:#0F5132}
.badge-status.inactive{background:#E2E3E5;color:#41464B}
.btn-icon{width:32px;height:32px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:8px}
.avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--teal),var(--teal-dark));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;flex-shrink:0}
.empty-state{text-align:center;padding:60px 20px;color:var(--tm)}
.empty-state i{font-size:2.8rem;opacity:.4}
.modal-header-teal{background:linear-gradient(135deg,var(--teal),var(--teal-dark));color:#fff}
.modal-header-teal .btn-close{filter:invert(1) brightness(2)}
.modal-content{border-radius:14px;overflow:hidden;border:none}
.alert-flash{border-radius:12px;font-size:.9rem;display:flex;align-items:center;gap:10px;margin-bottom:20px}
@media(max-width:767px){
  .table-clean thead{display:none}
  .table-clean tbody tr{display:block;border:1px solid var(--line);border-radius:12px;margin-bottom:10px;background:#fff;padding:10px}
  .table-clean tbody td{display:flex;justify-content:space-between;border:none;padding:6px 4px}
  .table-clean tbody td::before{content:attr(data-label);font-weight:700;color:var(--tm);font-size:.72rem;text-transform:uppercase;margin-right:10px}
}
</style>
</head>
<body>

<div class="dash-header">
  <div class="container">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
      <div>
        <h1><i class="bi bi-building me-2"></i>Manage Agencies</h1>
        <div class="crumb">
          <a href="hired_super_admin_dashboard.php"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
          <span class="mx-2">/</span>Agencies
        </div>
      </div>
      <div class="d-flex align-items-center gap-3">
        <span class="text-white-50" style="font-size:.85rem"><i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($adminName) ?></span>
        <a href="hired_logout.php" class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
      </div>
    </div>
  </div>
</div>

<div class="container py-4">

  <?php if ($flashMsg): ?>
  <div class="alert alert-<?= htmlspecialchars($flashType) ?> alert-flash">
    <i class="bi bi-<?= $flashType === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?>"></i>
    <div><?= $flashMsg ?></div>
  </div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
      <div class="stat-mini">
        <div class="icon icon-success"><i class="bi bi-check-circle-fill"></i></div>
        <div>
          <div class="num"><?= $counts['Active'] ?></div>
          <div class="lbl">Active Agencies</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-mini">
        <div class="icon icon-danger"><i class="bi bi-x-circle-fill"></i></div>
        <div>
          <div class="num"><?= $counts['Inactive'] ?></div>
          <div class="lbl">Inactive Agencies</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-mini">
        <div class="icon icon-teal"><i class="bi bi-buildings"></i></div>
        <div>
          <div class="num"><?= count($list) ?></div>
          <div class="lbl">Total Results</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3 d-flex justify-content-md-end align-items-center">
      <button class="btn btn-teal w-100 w-md-auto" data-bs-toggle="modal" data-bs-target="#createModal">
        <i class="bi bi-plus-circle me-1"></i>Add New Agency
      </button>
    </div>
  </div>

  <!-- Filters -->
  <div class="card-soft p-3 mb-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-6">
        <label class="form-label">Search</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" name="q" class="form-control" placeholder="Agency name, email or mobile..." value="<?= htmlspecialchars($search) ?>">
        </div>
      </div>
      <div class="col-md-4">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="">All Statuses</option>
          <option value="Active" <?= $statusFilter === 'Active' ? 'selected' : '' ?>>Active</option>
          <option value="Inactive" <?= $statusFilter === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-teal flex-fill" title="Filter"><i class="bi bi-funnel"></i></button>
        <a href="hired_manage_agencies.php" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
      </div>
    </form>
  </div>

  <!-- Table -->
  <div class="card-soft overflow-hidden">
    <?php if (empty($list)): ?>
      <div class="empty-state">
        <i class="bi bi-inbox"></i>
        <p class="mt-3 mb-1">No agencies found.</p>
        <small>Try clearing filters or add a new agency.</small>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-clean mb-0">
          <thead>
            <tr>
              <th>Agency Name</th>
              <th>Contact Details</th>
              <th>Status</th>
              <th>Created On</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($list as $row):
            $initials = strtoupper(substr($row['agency_name'] ?: '?', 0, 1));
          ?>
            <tr>
              <td data-label="Agency">
                <div class="d-flex align-items-center gap-2">
                  <div class="avatar"><?= htmlspecialchars($initials) ?></div>
                  <div>
                    <div style="font-weight:700"><?= htmlspecialchars($row['agency_name']) ?></div>
                    <div style="font-size:.75rem;color:var(--tm)">ID #<?= (int)$row['id'] ?></div>
                  </div>
                </div>
              </td>
              <td data-label="Contact">
                <div><i class="bi bi-envelope me-1 text-muted"></i><?= htmlspecialchars($row['email']) ?></div>
                <div style="font-size:.8rem;color:var(--tm)"><i class="bi bi-phone me-1"></i><?= htmlspecialchars($row['mobile']) ?></div>
              </td>
              <td data-label="Status">
                <?php if ($row['status'] === 'Active'): ?>
                  <span class="badge-status active"><i class="bi bi-check-circle-fill me-1"></i>Active</span>
                <?php else: ?>
                  <span class="badge-status inactive"><i class="bi bi-dash-circle-fill me-1"></i>Inactive</span>
                <?php endif; ?>
              </td>
              <td data-label="Created"><?= date('d M Y', strtotime($row['created_at'])) ?></td>
              <td data-label="Actions" class="text-end">
                <button class="btn btn-sm btn-outline-primary btn-icon me-1 edit-btn"
                  title="Edit"
                  data-id="<?= (int)$row['id'] ?>"
                  data-name="<?= htmlspecialchars($row['agency_name'] ?? '', ENT_QUOTES) ?>"
                  data-email="<?= htmlspecialchars($row['email'] ?? '', ENT_QUOTES) ?>"
                  data-mobile="<?= htmlspecialchars($row['mobile'] ?? '', ENT_QUOTES) ?>"
                  data-status="<?= htmlspecialchars($row['status'] ?? '', ENT_QUOTES) ?>">
                  <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger btn-icon delete-btn"
                  title="Delete"
                  data-id="<?= (int)$row['id'] ?>"
                  data-name="<?= htmlspecialchars($row['agency_name'] ?? '', ENT_QUOTES) ?>">
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

</div>

<!-- ================= CREATE MODAL ================= -->
<div class="modal fade" id="createModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header modal-header-teal">
        <h5 class="modal-title"><i class="bi bi-building-add me-2"></i>Add New Agency</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" autocomplete="off">
        <input type="hidden" name="action" value="create">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-12">
              <label class="form-label">Agency Name <span class="text-danger">*</span></label>
              <input type="text" name="agency_name" class="form-control" required maxlength="150">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email ID <span class="text-danger">*</span></label>
              <input type="email" name="email" class="form-control" required maxlength="150">
            </div>
            <div class="col-md-6">
              <label class="form-label">Mobile Number <span class="text-danger">*</span></label>
              <input type="text" name="mobile" class="form-control" required pattern="[6-9]\d{9}" maxlength="10" inputmode="numeric" placeholder="10-digit">
            </div>
            <div class="col-md-6">
              <label class="form-label">Password <span class="text-danger">*</span></label>
              <div class="input-group">
                <input type="password" name="password" id="createPassword" class="form-control" required minlength="6">
                <button type="button" class="btn btn-outline-secondary toggle-pw" data-target="createPassword"><i class="bi bi-eye"></i></button>
              </div>
              <small class="text-muted">Minimum 6 characters.</small>
            </div>
            <div class="col-md-6">
              <label class="form-label">Status <span class="text-danger">*</span></label>
              <select name="status" class="form-select" required>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-teal"><i class="bi bi-check-lg me-1"></i>Create Agency</button>
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
        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Agency</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" autocomplete="off">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="editId">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-12">
              <label class="form-label">Agency Name <span class="text-danger">*</span></label>
              <input type="text" name="agency_name" id="editName" class="form-control" required maxlength="150">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email ID <span class="text-danger">*</span></label>
              <input type="email" name="email" id="editEmail" class="form-control" required maxlength="150">
            </div>
            <div class="col-md-6">
              <label class="form-label">Mobile Number <span class="text-danger">*</span></label>
              <input type="text" name="mobile" id="editMobile" class="form-control" required pattern="[6-9]\d{9}" maxlength="10" inputmode="numeric">
            </div>
            <div class="col-md-6">
              <label class="form-label">New Password</label>
              <div class="input-group">
                <input type="password" name="password" id="editPassword" class="form-control" minlength="6" placeholder="Leave blank to keep current">
                <button type="button" class="btn btn-outline-secondary toggle-pw" data-target="editPassword"><i class="bi bi-eye"></i></button>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Status <span class="text-danger">*</span></label>
              <select name="status" id="editStatus" class="form-select" required>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
              </select>
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

<!-- ================= DELETE MODAL ================= -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background:#C4433B;color:#fff">
        <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Delete</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
        <div class="modal-body">
          <p class="mb-2">Are you sure you want to permanently delete:</p>
          <p class="mb-0"><strong id="deleteName"></strong></p>
          <div class="alert alert-warning mt-3 mb-0" style="font-size:.85rem">
            <i class="bi bi-info-circle me-1"></i>
            This action cannot be undone.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Delete</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
'use strict';
document.addEventListener('DOMContentLoaded', () => {

  // Toggle password visibility
  document.querySelectorAll('.toggle-pw').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = document.getElementById(btn.dataset.target);
      if (!input) return;
      const isPw = input.type === 'password';
      input.type = isPw ? 'text' : 'password';
      btn.innerHTML = `<i class="bi bi-eye${isPw ? '-slash' : ''}"></i>`;
    });
  });

  // Edit Modal
  const editModal = new bootstrap.Modal(document.getElementById('editModal'));
  document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('editId').value       = btn.dataset.id;
      document.getElementById('editName').value     = btn.dataset.name;
      document.getElementById('editEmail').value    = btn.dataset.email;
      document.getElementById('editMobile').value   = btn.dataset.mobile;
      document.getElementById('editStatus').value   = btn.dataset.status;
      document.getElementById('editPassword').value = '';
      editModal.show();
    });
  });

  // Delete Modal
  const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
  document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('deleteId').value = btn.dataset.id;
      document.getElementById('deleteName').textContent = btn.dataset.name;
      deleteModal.show();
    });
  });

});
</script>
</body>
</html>
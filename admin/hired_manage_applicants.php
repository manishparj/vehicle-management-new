<?php
session_start();
include("../config/db.php");

// ================= AUTH GUARD =================
if (!isset($_SESSION['hired_user_id']) || ($_SESSION['hired_role'] ?? '') !== 'hired_super_admin') {
    header("Location: hired_login.php");
    exit;
}

$currentAdminId = (int) $_SESSION['hired_user_id'];
$adminName = $_SESSION['hired_user_name'] ?? 'Super Admin';

// ================= HELPERS =================
function redirectWith($type, $msg) {
    $_SESSION['flash_type'] = $type;
    $_SESSION['flash_msg']  = $msg;
    header("Location: hired_manage_applicants.php");
    exit;
}
function isValidMobile($m) { return preg_match('/^[6-9]\d{9}$/', $m); }
function isValidEmail($e)  { return filter_var($e, FILTER_VALIDATE_EMAIL) !== false; }

$validRoles = ['hired_super_admin', 'hired_forwarded_admin'];

// ================= LOAD PROJECTS MASTER =================
$projects = [];
$pRes = $conn->query("SELECT id, `key`, label, color FROM projects WHERE is_active = 1 ORDER BY label ASC");
if ($pRes) {
    while ($p = $pRes->fetch_assoc()) {
        $projects[(int)$p['id']] = $p;
    }
}
$validProjectIds = array_keys($projects);

// ================= HANDLE ACTIONS =================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---------- CREATE ----------
    if ($action === 'create') {
        $name        = trim($_POST['name'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $mob         = trim($_POST['mob'] ?? '');
        $designation = trim($_POST['designation'] ?? '');
        $password    = $_POST['password'] ?? '';
        $role        = trim($_POST['role'] ?? '');

        $projectIds = $_POST['projects'] ?? [];
        if (!is_array($projectIds)) $projectIds = [];
        $projectIds = array_values(array_unique(array_map('intval', $projectIds)));
        $projectIds = array_values(array_filter($projectIds, fn($pid) => in_array($pid, $validProjectIds, true)));

        $errors = [];
        if ($name === '')                              $errors[] = 'Name is required.';
        if ($email === '' || !isValidEmail($email))    $errors[] = 'A valid email is required.';
        if ($mob === '' || !isValidMobile($mob))       $errors[] = 'A valid 10-digit mobile is required.';
        if (strlen($password) < 6)                     $errors[] = 'Password must be at least 6 characters.';
        if (!in_array($role, $validRoles, true))       $errors[] = 'Invalid role.';
        if (empty($projectIds))                        $errors[] = 'Select at least one project.';

        if (empty($errors)) {
            $chk = $conn->prepare("SELECT id FROM hired_applicants WHERE email = ?");
            $chk->bind_param("s", $email);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) $errors[] = 'This email is already registered.';
            $chk->close();
        }

        if (!empty($errors)) redirectWith('danger', implode(' ', $errors));

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO hired_applicants (name, email, mob, designation, password, role) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $name, $email, $mob, $designation, $hash, $role);
            $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();

            $linkStmt = $conn->prepare("INSERT INTO hired_applicant_projects (applicant_id, project_id) VALUES (?, ?)");
            foreach ($projectIds as $pid) {
                $linkStmt->bind_param("ii", $newId, $pid);
                $linkStmt->execute();
            }
            $linkStmt->close();

            $conn->commit();
            redirectWith('success', "Applicant <strong>" . htmlspecialchars($name) . "</strong> created successfully.");
        } catch (Throwable $ex) {
            $conn->rollback();
            error_log("create applicant failed: " . $ex->getMessage());
            redirectWith('danger', 'Could not create applicant.');
        }
    }

    // ---------- UPDATE ----------
    if ($action === 'update') {
        $id          = (int)($_POST['id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $mob         = trim($_POST['mob'] ?? '');
        $designation = trim($_POST['designation'] ?? '');
        $password    = $_POST['password'] ?? '';
        $role        = trim($_POST['role'] ?? '');

        $projectIds = $_POST['projects'] ?? [];
        if (!is_array($projectIds)) $projectIds = [];
        $projectIds = array_values(array_unique(array_map('intval', $projectIds)));
        $projectIds = array_values(array_filter($projectIds, fn($pid) => in_array($pid, $validProjectIds, true)));

        $errors = [];
        if ($id <= 0)                                  $errors[] = 'Invalid ID.';
        if ($name === '')                              $errors[] = 'Name is required.';
        if ($email === '' || !isValidEmail($email))    $errors[] = 'A valid email is required.';
        if ($mob === '' || !isValidMobile($mob))       $errors[] = 'A valid 10-digit mobile is required.';
        if (!in_array($role, $validRoles, true))       $errors[] = 'Invalid role.';
        if ($password !== '' && strlen($password) < 6) $errors[] = 'New password must be at least 6 characters.';
        if (empty($projectIds))                        $errors[] = 'Select at least one project.';

        if ($id === $currentAdminId && $role !== 'hired_super_admin') {
            $errors[] = 'You cannot change your own role.';
        }

        if (empty($errors)) {
            $chk = $conn->prepare("SELECT id FROM hired_applicants WHERE email = ? AND id <> ?");
            $chk->bind_param("si", $email, $id);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) $errors[] = 'This email is already used by another applicant.';
            $chk->close();
        }

        if (!empty($errors)) redirectWith('danger', implode(' ', $errors));

        $conn->begin_transaction();
        try {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE hired_applicants SET name=?, email=?, mob=?, designation=?, role=?, password=? WHERE id=?");
                $stmt->bind_param("ssssssi", $name, $email, $mob, $designation, $role, $hash, $id);
            } else {
                $stmt = $conn->prepare("UPDATE hired_applicants SET name=?, email=?, mob=?, designation=?, role=? WHERE id=?");
                $stmt->bind_param("sssssi", $name, $email, $mob, $designation, $role, $id);
            }
            $stmt->execute();
            $stmt->close();

            $del = $conn->prepare("DELETE FROM hired_applicant_projects WHERE applicant_id = ?");
            $del->bind_param("i", $id);
            $del->execute();
            $del->close();

            $linkStmt = $conn->prepare("INSERT INTO hired_applicant_projects (applicant_id, project_id) VALUES (?, ?)");
            foreach ($projectIds as $pid) {
                $linkStmt->bind_param("ii", $id, $pid);
                $linkStmt->execute();
            }
            $linkStmt->close();

            $conn->commit();
            redirectWith('success', "Applicant <strong>" . htmlspecialchars($name) . "</strong> updated successfully.");
        } catch (Throwable $ex) {
            $conn->rollback();
            error_log("update applicant failed: " . $ex->getMessage());
            redirectWith('danger', 'Could not update applicant.');
        }
    }

    // ---------- ASSIGN PROJECTS ONLY ----------
    if ($action === 'assign_projects') {
        $applicantId = (int)($_POST['applicant_id'] ?? 0);
        $projectIds  = $_POST['projects'] ?? [];
        if (!is_array($projectIds)) $projectIds = [];
        $projectIds = array_values(array_unique(array_map('intval', $projectIds)));
        $projectIds = array_values(array_filter($projectIds, fn($pid) => in_array($pid, $validProjectIds, true)));

        $errors = [];
        if ($applicantId <= 0) $errors[] = 'Invalid applicant.';
        if (empty($projectIds)) $errors[] = 'Select at least one project.';

        $appRow = null;
        if (empty($errors)) {
            $chk = $conn->prepare("SELECT id, name FROM hired_applicants WHERE id = ?");
            $chk->bind_param("i", $applicantId);
            $chk->execute();
            $appRow = $chk->get_result()->fetch_assoc();
            $chk->close();
            if (!$appRow) $errors[] = 'Applicant not found.';
        }

        if (!empty($errors)) redirectWith('danger', implode(' ', $errors));

        $conn->begin_transaction();
        try {
            $del = $conn->prepare("DELETE FROM hired_applicant_projects WHERE applicant_id = ?");
            $del->bind_param("i", $applicantId);
            $del->execute();
            $del->close();

            $linkStmt = $conn->prepare("INSERT INTO hired_applicant_projects (applicant_id, project_id) VALUES (?, ?)");
            foreach ($projectIds as $pid) {
                $linkStmt->bind_param("ii", $applicantId, $pid);
                $linkStmt->execute();
            }
            $linkStmt->close();

            $conn->commit();
            redirectWith('success', "Projects updated for <strong>" . htmlspecialchars($appRow['name']) . "</strong>.");
        } catch (Throwable $ex) {
            $conn->rollback();
            error_log("assign projects failed: " . $ex->getMessage());
            redirectWith('danger', 'Could not update project assignments.');
        }
    }

    // ---------- BULK ASSIGN ----------
    if ($action === 'bulk_assign') {
        $projectId    = (int)($_POST['project_id'] ?? 0);
        $applicantIds = $_POST['applicant_ids'] ?? [];
        $mode         = $_POST['mode'] ?? 'add';

        if (!is_array($applicantIds)) $applicantIds = [];
        $applicantIds = array_values(array_unique(array_map('intval', $applicantIds)));
        $applicantIds = array_values(array_filter($applicantIds, fn($v) => $v > 0));

        if ($projectId <= 0 || !in_array($projectId, $validProjectIds, true)) {
            redirectWith('danger', 'Invalid project.');
        }
        if (empty($applicantIds)) {
            redirectWith('danger', 'No applicants selected.');
        }
        if (!in_array($mode, ['add', 'remove'], true)) {
            redirectWith('danger', 'Invalid mode.');
        }

        $conn->begin_transaction();
        try {
            if ($mode === 'add') {
                $stmt = $conn->prepare("INSERT IGNORE INTO hired_applicant_projects (applicant_id, project_id) VALUES (?, ?)");
            } else {
                $stmt = $conn->prepare("DELETE FROM hired_applicant_projects WHERE applicant_id = ? AND project_id = ?");
            }
            foreach ($applicantIds as $aid) {
                $stmt->bind_param("ii", $aid, $projectId);
                $stmt->execute();
            }
            $stmt->close();
            $conn->commit();

            $label = $mode === 'add' ? 'assigned to' : 'removed from';
            $projLabel = $projects[$projectId]['label'] ?? 'project';
            redirectWith('success', count($applicantIds) . " applicant(s) {$label} <strong>" . htmlspecialchars($projLabel) . "</strong>.");
        } catch (Throwable $ex) {
            $conn->rollback();
            error_log("bulk assign failed: " . $ex->getMessage());
            redirectWith('danger', 'Bulk operation failed.');
        }
    }

    // ---------- DELETE ----------
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) redirectWith('danger', 'Invalid ID.');
        if ($id === $currentAdminId) redirectWith('danger', 'You cannot delete your own account.');

        $stmt = $conn->prepare("DELETE FROM hired_applicants WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            redirectWith('success', 'Applicant deleted successfully.');
        } else {
            error_log("delete applicant failed: " . $stmt->error);
            redirectWith('danger', 'Could not delete applicant.');
        }
        $stmt->close();
    }
}

// ================= FLASH =================
$flashType = $_SESSION['flash_type'] ?? '';
$flashMsg  = $_SESSION['flash_msg']  ?? '';
unset($_SESSION['flash_type'], $_SESSION['flash_msg']);

// ================= FETCH LIST =================
$search     = trim($_GET['q'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');
$projFilter = (int)($_GET['project'] ?? 0);

$sql = "SELECT DISTINCT a.id, a.name, a.email, a.mob, a.designation, a.role, a.created_at
        FROM hired_applicants a";
$params = [];
$types  = '';

if ($projFilter > 0 && in_array($projFilter, $validProjectIds, true)) {
    $sql .= " INNER JOIN hired_applicant_projects hap ON hap.applicant_id = a.id AND hap.project_id = ?";
    $params[] = $projFilter;
    $types .= 'i';
}

$sql .= " WHERE 1=1";

if ($search !== '') {
    $sql .= " AND (a.name LIKE ? OR a.email LIKE ? OR a.mob LIKE ? OR a.designation LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'ssss';
}
if ($roleFilter !== '' && in_array($roleFilter, $validRoles, true)) {
    $sql .= " AND a.role = ?";
    $params[] = $roleFilter;
    $types .= 's';
}
$sql .= " ORDER BY a.role DESC, a.name ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ================= FETCH PROJECTS PER APPLICANT =================
$applicantProjectMap = [];
if (!empty($list)) {
    $ids = array_column($list, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $typesList = str_repeat('i', count($ids));

    $psql = "SELECT hap.applicant_id, p.id AS project_id, p.`key`, p.label, p.color
             FROM hired_applicant_projects hap
             INNER JOIN projects p ON p.id = hap.project_id
             WHERE hap.applicant_id IN ($placeholders)
             ORDER BY p.label ASC";
    $pstmt = $conn->prepare($psql);
    $pstmt->bind_param($typesList, ...$ids);
    $pstmt->execute();
    $pres = $pstmt->get_result();
    while ($row = $pres->fetch_assoc()) {
        $applicantProjectMap[(int)$row['applicant_id']][] = $row;
    }
    $pstmt->close();
}

// ================= COUNTS =================
$counts = ['hired_super_admin' => 0, 'hired_forwarded_admin' => 0];
$cRes = $conn->query("SELECT role, COUNT(*) as c FROM hired_applicants GROUP BY role");
while ($r = $cRes->fetch_assoc()) $counts[$r['role']] = (int)$r['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Applicants — Hired Vehicle</title>
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
.form-control,.form-select{border-color:var(--line);padding:.55rem .8rem;font-size:.92rem}
.form-control:focus,.form-select:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(14,124,134,.14)}
.form-label{font-weight:600;font-size:.85rem;color:var(--navy);margin-bottom:5px}
.btn-teal{background:var(--teal);color:#fff;font-weight:600;border:none}
.btn-teal:hover{background:var(--teal-dark);color:#fff}
.table-clean{font-size:.88rem}
.table-clean thead th{background:#F8FAFB;color:var(--tm);font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;font-weight:700;border-bottom:1px solid var(--line);padding:12px 14px}
.table-clean tbody td{padding:12px 14px;vertical-align:middle;border-color:var(--line)}
.badge-role{font-size:.72rem;font-weight:700;padding:3px 10px;border-radius:999px;white-space:nowrap}
.badge-role.super{background:#E5EEF4;color:var(--navy)}
.badge-role.forwarded{background:#E5F3F3;color:var(--teal-dark)}
.badge-you{background:#FBF0E4;color:#7A4A17;font-size:.68rem;font-weight:700;padding:2px 8px;border-radius:999px;margin-left:6px}
.proj-badge{font-size:.7rem;font-weight:700;padding:3px 9px;border-radius:6px;background:#F1F5F8;color:#4A5A68;white-space:nowrap;display:inline-flex;align-items:center;gap:5px;margin:2px 3px 2px 0}
.proj-dot{width:8px;height:8px;border-radius:50%;display:inline-block;flex-shrink:0}
.btn-icon{width:32px;height:32px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:8px}
.avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--teal),var(--teal-dark));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;flex-shrink:0}
.empty-state{text-align:center;padding:60px 20px;color:var(--tm)}
.empty-state i{font-size:2.8rem;opacity:.4}
.modal-header-teal{background:linear-gradient(135deg,var(--teal),var(--teal-dark));color:#fff}
.modal-header-teal .btn-close{filter:invert(1) brightness(2)}
.modal-content{border-radius:14px;overflow:hidden;border:none}
.alert-flash{border-radius:12px;font-size:.9rem;display:flex;align-items:center;gap:10px;margin-bottom:20px}

/* Projects multi-select grid */
.proj-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:8px;border:1px solid var(--line);border-radius:10px;padding:12px;background:#F8FAFB}
.proj-item{display:flex;align-items:center;gap:8px;background:#fff;border:1px solid var(--line);border-radius:8px;padding:8px 10px;cursor:pointer;transition:.15s;font-size:.85rem;font-weight:600;color:var(--navy);user-select:none;margin:0}
.proj-item:hover{border-color:var(--teal);background:#F3FBFB}
.proj-item input{width:16px;height:16px;cursor:pointer;margin:0;flex-shrink:0}
.proj-item.checked{border-color:var(--teal);background:#E5F3F3;color:var(--teal-dark)}
.bulk-bar{position:sticky;bottom:16px;z-index:100;background:#0F2A43;color:#fff;border-radius:12px;padding:14px 18px;box-shadow:0 12px 32px rgba(15,42,67,.35);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px}
.bulk-bar .form-select{max-width:240px}
.bulk-bar .btn{white-space:nowrap}

@media(max-width:767px){
  .table-clean thead{display:none}
  .table-clean tbody tr{display:block;border:1px solid var(--line);border-radius:12px;margin-bottom:10px;background:#fff;padding:10px}
  .table-clean tbody td{display:flex;justify-content:space-between;border:none;padding:6px 4px}
  .table-clean tbody td::before{content:attr(data-label);font-weight:700;color:var(--tm);font-size:.72rem;text-transform:uppercase;margin-right:10px}
  .proj-grid{grid-template-columns:1fr}
  .bulk-bar{flex-direction:column;align-items:stretch}
  .bulk-bar .form-select{max-width:100%}
}
</style>
</head>
<body>

<div class="dash-header">
  <div class="container">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
      <div>
        <h1><i class="bi bi-people-fill me-2"></i>Manage Applicants</h1>
        <div class="crumb">
          <a href="hired_super_admin_dashboard.php"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
          <span class="mx-2">/</span>
          <a href="hired_manage_projects.php">Projects</a>
          <span class="mx-2">/</span>Applicants &amp; Mapping
        </div>
      </div>
      <div class="d-flex align-items-center gap-3">
        <span class="text-white-50" style="font-size:.85rem"><i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($adminName) ?></span>
        <a href="hired_manage_projects.php" class="btn btn-sm btn-outline-light"><i class="bi bi-diagram-3 me-1"></i>Manage Projects</a>
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
        <div class="icon icon-navy"><i class="bi bi-person-badge"></i></div>
        <div>
          <div class="num"><?= $counts['hired_super_admin'] ?></div>
          <div class="lbl">Super Admins</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-mini">
        <div class="icon icon-teal"><i class="bi bi-people-fill"></i></div>
        <div>
          <div class="num"><?= $counts['hired_forwarded_admin'] ?></div>
          <div class="lbl">Forwarded Admins</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-mini">
        <div class="icon icon-teal"><i class="bi bi-diagram-3"></i></div>
        <div>
          <div class="num"><?= count($projects) ?></div>
          <div class="lbl">Active Projects</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3 d-flex justify-content-md-end align-items-center">
      <button class="btn btn-teal w-100 w-md-auto" data-bs-toggle="modal" data-bs-target="#createModal">
        <i class="bi bi-plus-circle me-1"></i>Add New Applicant
      </button>
    </div>
  </div>

  <!-- Filters -->
  <div class="card-soft p-3 mb-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-5">
        <label class="form-label">Search</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" name="q" class="form-control" placeholder="Name, email, mobile or designation..." value="<?= htmlspecialchars($search) ?>">
        </div>
      </div>
      <div class="col-md-3">
        <label class="form-label">Role</label>
        <select name="role" class="form-select">
          <option value="">All Roles</option>
          <option value="hired_super_admin"     <?= $roleFilter === 'hired_super_admin' ? 'selected' : '' ?>>Super Admin</option>
          <option value="hired_forwarded_admin" <?= $roleFilter === 'hired_forwarded_admin' ? 'selected' : '' ?>>Forwarded Admin</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Project</label>
        <select name="project" class="form-select">
          <option value="">All Projects</option>
          <?php foreach ($projects as $pid => $p): ?>
            <option value="<?= (int)$pid ?>" <?= $projFilter === $pid ? 'selected' : '' ?>><?= htmlspecialchars($p['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-1 d-flex gap-2">
        <button type="submit" class="btn btn-teal flex-fill" title="Filter"><i class="bi bi-funnel"></i></button>
        <a href="hired_manage_applicants.php" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
      </div>
    </form>
  </div>

  <!-- Bulk bar -->
  <div id="bulkBar" class="bulk-bar d-none mb-3">
    <div>
      <strong id="bulkCount">0</strong> applicant(s) selected
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <select id="bulkProjectId" class="form-select form-select-sm">
        <option value="">— Choose project —</option>
        <?php foreach ($projects as $pid => $p): ?>
          <option value="<?= (int)$pid ?>"><?= htmlspecialchars($p['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="button" class="btn btn-sm btn-success" id="bulkAddBtn">
        <i class="bi bi-plus-circle me-1"></i>Add project
      </button>
      <button type="button" class="btn btn-sm btn-outline-light" id="bulkRemoveBtn">
        <i class="bi bi-dash-circle me-1"></i>Remove
      </button>
      <button type="button" class="btn btn-sm btn-outline-light" id="bulkClearBtn">Clear</button>
    </div>
  </div>

  <!-- Table -->
  <div class="card-soft overflow-hidden">
    <?php if (empty($list)): ?>
      <div class="empty-state">
        <i class="bi bi-inbox"></i>
        <p class="mt-3 mb-1">No applicants found.</p>
        <small>Try clearing filters or add a new applicant.</small>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-clean mb-0">
          <thead>
            <tr>
              <th style="width:36px">
                <input type="checkbox" id="bulkSelectAll" title="Select all">
              </th>
              <th>Applicant</th>
              <th>Contact</th>
              <th>Designation</th>
              <th>Projects</th>
              <th>Role</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($list as $row):
            $isSelf = ((int)$row['id'] === $currentAdminId);
            $initials = strtoupper(substr($row['name'] ?: '?', 0, 1));
            $assignedProjects = $applicantProjectMap[(int)$row['id']] ?? [];
            $assignedIds = array_column($assignedProjects, 'project_id');
          ?>
            <tr>
              <td data-label="Select" style="width:36px">
                <input type="checkbox" class="bulk-select-row" value="<?= (int)$row['id'] ?>">
              </td>
              <td data-label="Applicant">
                <div class="d-flex align-items-center gap-2">
                  <div class="avatar"><?= htmlspecialchars($initials) ?></div>
                  <div>
                    <div style="font-weight:700"><?= htmlspecialchars($row['name'] ?: '—') ?>
                      <?php if ($isSelf): ?><span class="badge-you">YOU</span><?php endif; ?>
                    </div>
                    <div style="font-size:.75rem;color:var(--tm)">ID #<?= (int)$row['id'] ?></div>
                  </div>
                </div>
              </td>
              <td data-label="Contact">
                <div><i class="bi bi-envelope me-1 text-muted"></i><?= htmlspecialchars($row['email'] ?: '—') ?></div>
                <div style="font-size:.8rem;color:var(--tm)"><i class="bi bi-phone me-1"></i><?= htmlspecialchars($row['mob'] ?: '—') ?></div>
              </td>
              <td data-label="Designation"><?= htmlspecialchars($row['designation'] ?: '—') ?></td>
              <td data-label="Projects">
                <?php if (empty($assignedProjects)): ?>
                  <span style="color:#a7b2ba;font-size:.82rem">No projects</span>
                <?php else: ?>
                  <?php foreach ($assignedProjects as $ap): ?>
                    <span class="proj-badge">
                      <span class="proj-dot" style="background:<?= htmlspecialchars($ap['color'] ?: '#0E7C86') ?>"></span>
                      <?= htmlspecialchars($ap['label']) ?>
                    </span>
                  <?php endforeach; ?>
                <?php endif; ?>
              </td>
              <td data-label="Role">
                <?php if ($row['role'] === 'hired_super_admin'): ?>
                  <span class="badge-role super"><i class="bi bi-shield-fill me-1"></i>Super Admin</span>
                <?php else: ?>
                  <span class="badge-role forwarded"><i class="bi bi-person-workspace me-1"></i>Forwarded Admin</span>
                <?php endif; ?>
              </td>
              <td data-label="Actions" class="text-end">
                <button class="btn btn-sm btn-outline-success btn-icon me-1 assign-btn"
                  title="Assign Projects"
                  data-id="<?= (int)$row['id'] ?>"
                  data-name="<?= htmlspecialchars($row['name'] ?? '', ENT_QUOTES) ?>"
                  data-projects="<?= htmlspecialchars(implode(',', $assignedIds), ENT_QUOTES) ?>">
                  <i class="bi bi-diagram-3"></i>
                </button>
                <button class="btn btn-sm btn-outline-primary btn-icon me-1 edit-btn"
                  title="Edit"
                  data-id="<?= (int)$row['id'] ?>"
                  data-name="<?= htmlspecialchars($row['name'] ?? '', ENT_QUOTES) ?>"
                  data-email="<?= htmlspecialchars($row['email'] ?? '', ENT_QUOTES) ?>"
                  data-mob="<?= htmlspecialchars($row['mob'] ?? '', ENT_QUOTES) ?>"
                  data-designation="<?= htmlspecialchars($row['designation'] ?? '', ENT_QUOTES) ?>"
                  data-role="<?= htmlspecialchars($row['role'] ?? '', ENT_QUOTES) ?>"
                  data-projects="<?= htmlspecialchars(implode(',', $assignedIds), ENT_QUOTES) ?>">
                  <i class="bi bi-pencil"></i>
                </button>
                <?php if (!$isSelf): ?>
                <button class="btn btn-sm btn-outline-danger btn-icon delete-btn"
                  title="Delete"
                  data-id="<?= (int)$row['id'] ?>"
                  data-name="<?= htmlspecialchars($row['name'] ?? '', ENT_QUOTES) ?>">
                  <i class="bi bi-trash"></i>
                </button>
                <?php else: ?>
                <button class="btn btn-sm btn-outline-secondary btn-icon" disabled title="You cannot delete yourself">
                  <i class="bi bi-trash"></i>
                </button>
                <?php endif; ?>
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
        <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2"></i>Add New Applicant</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" autocomplete="off">
        <input type="hidden" name="action" value="create">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Full Name <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" required maxlength="150">
            </div>
            <div class="col-md-6">
              <label class="form-label">Designation</label>
              <input type="text" name="designation" class="form-control" maxlength="150">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" name="email" class="form-control" required maxlength="150">
            </div>
            <div class="col-md-6">
              <label class="form-label">Mobile <span class="text-danger">*</span></label>
              <input type="text" name="mob" class="form-control" required pattern="[6-9]\d{9}" maxlength="10" inputmode="numeric" placeholder="10-digit">
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
              <label class="form-label">Role <span class="text-danger">*</span></label>
              <select name="role" class="form-select" required>
                <option value="">Select role</option>
                <option value="hired_forwarded_admin">Forwarded Admin</option>
                <option value="hired_super_admin">Super Admin</option>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label">Assigned Projects <span class="text-danger">*</span>
                <small class="text-muted" style="font-weight:400">(Select one or more)</small>
              </label>
              <div class="proj-grid">
                <?php foreach ($projects as $pid => $p): ?>
                  <label class="proj-item" data-proj-id="<?= (int)$pid ?>">
                    <input type="checkbox" name="projects[]" value="<?= (int)$pid ?>">
                    <span class="proj-dot" style="background:<?= htmlspecialchars($p['color'] ?: '#0E7C86') ?>"></span>
                    <span><?= htmlspecialchars($p['label']) ?></span>
                  </label>
                <?php endforeach; ?>
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
        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Applicant</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" autocomplete="off">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="editId">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Full Name <span class="text-danger">*</span></label>
              <input type="text" name="name" id="editName" class="form-control" required maxlength="150">
            </div>
            <div class="col-md-6">
              <label class="form-label">Designation</label>
              <input type="text" name="designation" id="editDesignation" class="form-control" maxlength="150">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" name="email" id="editEmail" class="form-control" required maxlength="150">
            </div>
            <div class="col-md-6">
              <label class="form-label">Mobile <span class="text-danger">*</span></label>
              <input type="text" name="mob" id="editMob" class="form-control" required pattern="[6-9]\d{9}" maxlength="10" inputmode="numeric">
            </div>
            <div class="col-md-6">
              <label class="form-label">New Password</label>
              <div class="input-group">
                <input type="password" name="password" id="editPassword" class="form-control" minlength="6" placeholder="Leave blank to keep">
                <button type="button" class="btn btn-outline-secondary toggle-pw" data-target="editPassword"><i class="bi bi-eye"></i></button>
              </div>
              <small class="text-muted">Leave blank to keep the current password.</small>
            </div>
            <div class="col-md-6">
              <label class="form-label">Role <span class="text-danger">*</span></label>
              <select name="role" id="editRole" class="form-select" required>
                <option value="hired_forwarded_admin">Forwarded Admin</option>
                <option value="hired_super_admin">Super Admin</option>
              </select>
              <small class="text-muted" id="editRoleHint"></small>
            </div>

            <div class="col-12">
              <label class="form-label">Assigned Projects <span class="text-danger">*</span>
                <small class="text-muted" style="font-weight:400">(Select one or more)</small>
              </label>
              <div class="proj-grid" id="editProjGrid">
                <?php foreach ($projects as $pid => $p): ?>
                  <label class="proj-item" data-proj-id="<?= (int)$pid ?>">
                    <input type="checkbox" name="projects[]" value="<?= (int)$pid ?>">
                    <span class="proj-dot" style="background:<?= htmlspecialchars($p['color'] ?: '#0E7C86') ?>"></span>
                    <span><?= htmlspecialchars($p['label']) ?></span>
                  </label>
                <?php endforeach; ?>
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

<!-- ================= ASSIGN PROJECTS MODAL ================= -->
<div class="modal fade" id="assignModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header modal-header-teal">
        <h5 class="modal-title"><i class="bi bi-diagram-3 me-2"></i>Assign Projects</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="assignForm">
        <input type="hidden" name="action" value="assign_projects">
        <input type="hidden" name="applicant_id" id="assignApplicantId">
        <div class="modal-body">
          <p class="mb-3">For: <strong id="assignApplicantName"></strong></p>

          <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <label class="form-label mb-0">Choose Projects <span class="text-danger">*</span></label>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-sm btn-outline-secondary" id="assignCheckAll">Check all</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="assignClearAll">Clear</button>
            </div>
          </div>

          <div class="proj-grid" id="assignProjGrid">
            <?php foreach ($projects as $pid => $p): ?>
              <label class="proj-item" data-proj-id="<?= (int)$pid ?>">
                <input type="checkbox" name="projects[]" value="<?= (int)$pid ?>">
                <span class="proj-dot" style="background:<?= htmlspecialchars($p['color'] ?: '#0E7C86') ?>"></span>
                <span><?= htmlspecialchars($p['label']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-teal"><i class="bi bi-check-lg me-1"></i>Save Assignments</button>
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
            This will also remove the applicant's project assignments.
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

  // ---------- Toggle password visibility ----------
  document.querySelectorAll('.toggle-pw').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = document.getElementById(btn.dataset.target);
      if (!input) return;
      const isPw = input.type === 'password';
      input.type = isPw ? 'text' : 'password';
      btn.innerHTML = `<i class="bi bi-eye${isPw ? '-slash' : ''}"></i>`;
    });
  });

  // ---------- Project checkbox visual state ----------
  document.querySelectorAll('.proj-item input[type="checkbox"]').forEach(cb => {
    const label = cb.closest('.proj-item');
    const sync = () => label.classList.toggle('checked', cb.checked);
    cb.addEventListener('change', sync);
    sync();
  });

  // ---------- Edit modal ----------
  const editModal = new bootstrap.Modal(document.getElementById('editModal'));
  document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('editId').value          = btn.dataset.id;
      document.getElementById('editName').value        = btn.dataset.name;
      document.getElementById('editEmail').value       = btn.dataset.email;
      document.getElementById('editMob').value         = btn.dataset.mob;
      document.getElementById('editDesignation').value = btn.dataset.designation;
      document.getElementById('editRole').value        = btn.dataset.role;
      document.getElementById('editPassword').value    = '';

      const assigned = (btn.dataset.projects || '')
        .split(',').map(s => parseInt(s.trim(), 10))
        .filter(n => !isNaN(n) && n > 0);

      document.querySelectorAll('#editProjGrid .proj-item').forEach(label => {
        const cb  = label.querySelector('input[type="checkbox"]');
        const pid = parseInt(label.dataset.projId, 10);
        cb.checked = assigned.includes(pid);
        label.classList.toggle('checked', cb.checked);
      });

      const isSelf = (btn.dataset.id === '<?= $currentAdminId ?>');
      const roleSel = document.getElementById('editRole');
      roleSel.disabled = isSelf;
      document.getElementById('editRoleHint').textContent = isSelf
        ? 'You cannot change your own role.'
        : '';
      editModal.show();
    });
  });

  // ---------- Assign Projects modal ----------
  const assignModal = new bootstrap.Modal(document.getElementById('assignModal'));
  document.querySelectorAll('.assign-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('assignApplicantId').value = btn.dataset.id;
      document.getElementById('assignApplicantName').textContent = btn.dataset.name;

      const assigned = (btn.dataset.projects || '')
        .split(',').map(s => parseInt(s.trim(), 10))
        .filter(n => !isNaN(n) && n > 0);

      document.querySelectorAll('#assignProjGrid .proj-item').forEach(label => {
        const cb  = label.querySelector('input[type="checkbox"]');
        const pid = parseInt(label.dataset.projId, 10);
        cb.checked = assigned.includes(pid);
        label.classList.toggle('checked', cb.checked);
      });
      assignModal.show();
    });
  });

  document.getElementById('assignCheckAll').addEventListener('click', () => {
    document.querySelectorAll('#assignProjGrid .proj-item').forEach(label => {
      const cb = label.querySelector('input[type="checkbox"]');
      cb.checked = true;
      label.classList.add('checked');
    });
  });
  document.getElementById('assignClearAll').addEventListener('click', () => {
    document.querySelectorAll('#assignProjGrid .proj-item').forEach(label => {
      const cb = label.querySelector('input[type="checkbox"]');
      cb.checked = false;
      label.classList.remove('checked');
    });
  });

  // ---------- Delete modal ----------
  const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
  document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('deleteId').value = btn.dataset.id;
      document.getElementById('deleteName').textContent = btn.dataset.name;
      deleteModal.show();
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

  document.querySelectorAll('.bulk-select-row').forEach(cb => {
    cb.addEventListener('change', updateBulkBar);
  });
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

  function submitBulk(mode) {
    const ids = rowChecks().filter(cb => cb.checked).map(cb => cb.value);
    const pid = document.getElementById('bulkProjectId').value;

    if (ids.length === 0) { alert('Select at least one applicant.'); return; }
    if (!pid)             { alert('Choose a project first.'); return; }

    const f = document.createElement('form');
    f.method = 'POST';
    f.style.display = 'none';

    const addHidden = (name, val) => {
      const i = document.createElement('input');
      i.type = 'hidden'; i.name = name; i.value = val;
      f.appendChild(i);
    };

    addHidden('action', 'bulk_assign');
    addHidden('project_id', pid);
    addHidden('mode', mode);
    ids.forEach(id => addHidden('applicant_ids[]', id));

    document.body.appendChild(f);
    f.submit();
  }

  document.getElementById('bulkAddBtn').addEventListener('click', () => submitBulk('add'));
  document.getElementById('bulkRemoveBtn').addEventListener('click', () => submitBulk('remove'));

});
</script>
</body>
</html>
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
    header("Location: hired_manage_projects.php");
    exit;
}
function slugify($s) {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '_', $s);
    return trim($s, '_');
}

// ================= HANDLE ACTIONS =================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---------- CREATE ----------
    if ($action === 'create') {
        $label = trim($_POST['label'] ?? '');
        $key   = trim($_POST['key'] ?? '');
        $color = trim($_POST['color'] ?? '#0E7C86');

        if ($key === '') $key = slugify($label);

        $errors = [];
        if ($label === '')                          $errors[] = 'Label is required.';
        if ($key === '')                            $errors[] = 'Key is required.';
        if (!preg_match('/^[a-z0-9_]+$/', $key))    $errors[] = 'Key must contain only lowercase letters, numbers and underscores.';
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) $errors[] = 'Color must be a hex value like #0E7C86.';

        if (empty($errors)) {
            $chk = $conn->prepare("SELECT id FROM projects WHERE `key` = ?");
            $chk->bind_param("s", $key);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) $errors[] = 'A project with this key already exists.';
            $chk->close();
        }

        if (!empty($errors)) redirectWith('danger', implode(' ', $errors));

        $stmt = $conn->prepare("INSERT INTO projects (`key`, label, color, is_active) VALUES (?, ?, ?, 1)");
        $stmt->bind_param("sss", $key, $label, $color);
        if ($stmt->execute()) {
            redirectWith('success', "Project <strong>" . htmlspecialchars($label) . "</strong> created successfully.");
        } else {
            error_log("create project failed: " . $stmt->error);
            redirectWith('danger', 'Could not create project.');
        }
        $stmt->close();
    }

    // ---------- UPDATE ----------
    if ($action === 'update') {
        $id     = (int)($_POST['id'] ?? 0);
        $label  = trim($_POST['label'] ?? '');
        $key    = trim($_POST['key'] ?? '');
        $color  = trim($_POST['color'] ?? '#0E7C86');
        $active = isset($_POST['is_active']) ? 1 : 0;

        if ($key === '') $key = slugify($label);

        $errors = [];
        if ($id <= 0)                               $errors[] = 'Invalid ID.';
        if ($label === '')                          $errors[] = 'Label is required.';
        if (!preg_match('/^[a-z0-9_]+$/', $key))    $errors[] = 'Key must contain only lowercase letters, numbers and underscores.';
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) $errors[] = 'Color must be a hex value.';

        if (empty($errors)) {
            $chk = $conn->prepare("SELECT id FROM projects WHERE `key` = ? AND id <> ?");
            $chk->bind_param("si", $key, $id);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) $errors[] = 'Another project already uses this key.';
            $chk->close();
        }

        if (!empty($errors)) redirectWith('danger', implode(' ', $errors));

        $stmt = $conn->prepare("UPDATE projects SET `key`=?, label=?, color=?, is_active=? WHERE id=?");
        $stmt->bind_param("sssii", $key, $label, $color, $active, $id);
        if ($stmt->execute()) {
            redirectWith('success', "Project <strong>" . htmlspecialchars($label) . "</strong> updated.");
        } else {
            error_log("update project failed: " . $stmt->error);
            redirectWith('danger', 'Could not update project.');
        }
        $stmt->close();
    }

    // ---------- TOGGLE ACTIVE ----------
    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) redirectWith('danger', 'Invalid ID.');

        $stmt = $conn->prepare("UPDATE projects SET is_active = 1 - is_active WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            redirectWith('success', 'Project status updated.');
        } else {
            error_log("toggle project failed: " . $stmt->error);
            redirectWith('danger', 'Could not toggle project.');
        }
        $stmt->close();
    }

    // ---------- DELETE ----------
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) redirectWith('danger', 'Invalid ID.');

        // Safety: block delete if in use
        $chk = $conn->prepare("SELECT COUNT(*) AS c FROM hired_applicant_projects WHERE project_id = ?");
        $chk->bind_param("i", $id);
        $chk->execute();
        $inUse = (int)($chk->get_result()->fetch_assoc()['c'] ?? 0);
        $chk->close();

        if ($inUse > 0) {
            redirectWith('danger', "This project is assigned to {$inUse} applicant(s). Deactivate it instead, or reassign first.");
        }

        $stmt = $conn->prepare("DELETE FROM projects WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            redirectWith('success', 'Project deleted.');
        } else {
            error_log("delete project failed: " . $stmt->error);
            redirectWith('danger', 'Could not delete project.');
        }
        $stmt->close();
    }
}

// ================= FLASH =================
$flashType = $_SESSION['flash_type'] ?? '';
$flashMsg  = $_SESSION['flash_msg']  ?? '';
unset($_SESSION['flash_type'], $_SESSION['flash_msg']);

// ================= FETCH PROJECTS WITH USAGE COUNT =================
$sql = "SELECT p.id, p.`key`, p.label, p.color, p.is_active, p.created_at,
               (SELECT COUNT(*) FROM hired_applicant_projects hap WHERE hap.project_id = p.id) AS usage_count
        FROM projects p
        ORDER BY p.is_active DESC, p.label ASC";
$list = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$totalActive   = 0;
$totalInactive = 0;
foreach ($list as $p) {
    if ((int)$p['is_active'] === 1) $totalActive++;
    else $totalInactive++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Projects — Hired Vehicle</title>
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
.btn-icon{width:32px;height:32px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:8px}
.color-dot{width:14px;height:14px;border-radius:50%;display:inline-block;border:1px solid rgba(0,0,0,.1);vertical-align:middle}
.badge-active{background:#EAF7F0;color:#1F8A5F;font-size:.72rem;font-weight:700;padding:3px 10px;border-radius:999px}
.badge-inactive{background:#FBEAE9;color:#C4433B;font-size:.72rem;font-weight:700;padding:3px 10px;border-radius:999px}
.empty-state{text-align:center;padding:60px 20px;color:var(--tm)}
.empty-state i{font-size:2.8rem;opacity:.4}
.modal-header-teal{background:linear-gradient(135deg,var(--teal),var(--teal-dark));color:#fff}
.modal-header-teal .btn-close{filter:invert(1) brightness(2)}
.modal-content{border-radius:14px;overflow:hidden;border:none}
.alert-flash{border-radius:12px;font-size:.9rem;display:flex;align-items:center;gap:10px;margin-bottom:20px}
.mono{font-family:'SFMono-Regular',Consolas,Menlo,monospace;font-size:.82rem;background:#F1F5F8;color:#4A5A68;padding:2px 8px;border-radius:5px}
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
        <h1><i class="bi bi-diagram-3 me-2"></i>Manage Projects</h1>
        <div class="crumb">
          <a href="hired_super_admin_dashboard.php"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
          <span class="mx-2">/</span>
          <a href="hired_manage_applicants.php">Applicants</a>
          <span class="mx-2">/</span>Projects
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

  <!-- Stats + Add button -->
  <div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
      <div class="stat-mini">
        <div class="icon icon-teal"><i class="bi bi-diagram-3"></i></div>
        <div>
          <div class="num"><?= $totalActive ?></div>
          <div class="lbl">Active Projects</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-mini">
        <div class="icon icon-navy"><i class="bi bi-archive"></i></div>
        <div>
          <div class="num"><?= $totalInactive ?></div>
          <div class="lbl">Inactive</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-mini">
        <div class="icon icon-teal"><i class="bi bi-link-45deg"></i></div>
        <div>
          <div class="num">
            <?php
              $totalAssign = $conn->query("SELECT COUNT(*) AS c FROM hired_applicant_projects")->fetch_assoc()['c'] ?? 0;
              echo (int)$totalAssign;
            ?>
          </div>
          <div class="lbl">Total Assignments</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3 d-flex justify-content-md-end align-items-center">
      <button class="btn btn-teal w-100 w-md-auto" data-bs-toggle="modal" data-bs-target="#createModal">
        <i class="bi bi-plus-circle me-1"></i>Add New Project
      </button>
    </div>
  </div>

  <!-- Table -->
  <div class="card-soft overflow-hidden">
    <?php if (empty($list)): ?>
      <div class="empty-state">
        <i class="bi bi-inbox"></i>
        <p class="mt-3 mb-1">No projects yet.</p>
        <small>Click "Add New Project" to create your first one.</small>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-clean mb-0">
          <thead>
            <tr>
              <th>Project</th>
              <th>Key</th>
              <th>Color</th>
              <th>Assigned To</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($list as $p): ?>
            <tr>
              <td data-label="Project" style="font-weight:700"><?= htmlspecialchars($p['label']) ?></td>
              <td data-label="Key"><span class="mono"><?= htmlspecialchars($p['key']) ?></span></td>
              <td data-label="Color">
                <span class="color-dot" style="background:<?= htmlspecialchars($p['color'] ?: '#0E7C86') ?>"></span>
                <span class="mono ms-2"><?= htmlspecialchars($p['color'] ?: '#0E7C86') ?></span>
              </td>
              <td data-label="Assigned To">
                <span class="badge bg-light text-dark border"><?= (int)$p['usage_count'] ?> applicant(s)</span>
              </td>
              <td data-label="Status">
                <?php if ((int)$p['is_active'] === 1): ?>
                  <span class="badge-active"><i class="bi bi-check-circle-fill me-1"></i>Active</span>
                <?php else: ?>
                  <span class="badge-inactive"><i class="bi bi-x-circle-fill me-1"></i>Inactive</span>
                <?php endif; ?>
              </td>
              <td data-label="Actions" class="text-end">
                <button class="btn btn-sm btn-outline-primary btn-icon me-1 edit-btn"
                  title="Edit"
                  data-id="<?= (int)$p['id'] ?>"
                  data-key="<?= htmlspecialchars($p['key'], ENT_QUOTES) ?>"
                  data-label="<?= htmlspecialchars($p['label'], ENT_QUOTES) ?>"
                  data-color="<?= htmlspecialchars($p['color'] ?? '#0E7C86', ENT_QUOTES) ?>"
                  data-active="<?= (int)$p['is_active'] ?>">
                  <i class="bi bi-pencil"></i>
                </button>

                <form method="POST" class="d-inline" onsubmit="return confirm('Toggle status?')">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <button class="btn btn-sm btn-outline-secondary btn-icon me-1" title="Toggle active">
                    <i class="bi bi-toggle-<?= (int)$p['is_active'] === 1 ? 'on' : 'off' ?>"></i>
                  </button>
                </form>

                <button class="btn btn-sm btn-outline-danger btn-icon delete-btn"
                  title="Delete"
                  data-id="<?= (int)$p['id'] ?>"
                  data-label="<?= htmlspecialchars($p['label'], ENT_QUOTES) ?>"
                  data-usage="<?= (int)$p['usage_count'] ?>">
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
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header modal-header-teal">
        <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add New Project</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" autocomplete="off">
        <input type="hidden" name="action" value="create">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Label <span class="text-danger">*</span></label>
            <input type="text" name="label" id="createLabel" class="form-control" required maxlength="120" placeholder="e.g. Hired Vehicle">
          </div>
          <div class="mb-3">
            <label class="form-label">Key <small class="text-muted">(auto-generated if blank)</small></label>
            <input type="text" name="key" id="createKey" class="form-control" maxlength="50" pattern="[a-z0-9_]+" placeholder="hired_vehicle">
            <small class="text-muted">Only lowercase letters, numbers and underscores.</small>
          </div>
          <div class="mb-3">
            <label class="form-label">Color <span class="text-danger">*</span></label>
            <div class="input-group">
              <input type="color" name="color" id="createColor" class="form-control form-control-color" value="#0E7C86" style="max-width:64px">
              <input type="text" id="createColorHex" class="form-control" value="#0E7C86" maxlength="7" pattern="#[0-9A-Fa-f]{6}">
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
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header modal-header-teal">
        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Project</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" autocomplete="off">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="editId">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Label <span class="text-danger">*</span></label>
            <input type="text" name="label" id="editLabel" class="form-control" required maxlength="120">
          </div>
          <div class="mb-3">
            <label class="form-label">Key</label>
            <input type="text" name="key" id="editKey" class="form-control" required maxlength="50" pattern="[a-z0-9_]+">
          </div>
          <div class="mb-3">
            <label class="form-label">Color <span class="text-danger">*</span></label>
            <div class="input-group">
              <input type="color" name="color" id="editColor" class="form-control form-control-color" value="#0E7C86" style="max-width:64px">
              <input type="text" id="editColorHex" class="form-control" value="#0E7C86" maxlength="7" pattern="#[0-9A-Fa-f]{6}">
            </div>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" id="editActive" value="1">
            <label class="form-check-label fw-semibold" for="editActive">Active</label>
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
        <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>Delete Project</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
        <div class="modal-body">
          <p class="mb-2">Delete project:</p>
          <p class="mb-0"><strong id="deleteLabel"></strong></p>
          <div class="alert alert-warning mt-3 mb-0" style="font-size:.85rem" id="deleteWarning">
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

  // Sync color picker ↔ hex input
  function bindColor(pickerId, hexId) {
    const picker = document.getElementById(pickerId);
    const hex    = document.getElementById(hexId);
    picker.addEventListener('input', () => hex.value = picker.value);
    hex.addEventListener('input', () => {
      if (/^#[0-9A-Fa-f]{6}$/.test(hex.value)) picker.value = hex.value;
    });
  }
  bindColor('createColor', 'createColorHex');
  bindColor('editColor', 'editColorHex');

  // Auto-slug key from label on create
  const createLabel = document.getElementById('createLabel');
  const createKey   = document.getElementById('createKey');
  createLabel.addEventListener('input', () => {
    if (createKey.dataset.touched === '1') return;
    createKey.value = createLabel.value
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '');
  });
  createKey.addEventListener('input', () => createKey.dataset.touched = '1');

  // Edit modal
  const editModal = new bootstrap.Modal(document.getElementById('editModal'));
  document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('editId').value    = btn.dataset.id;
      document.getElementById('editLabel').value = btn.dataset.label;
      document.getElementById('editKey').value   = btn.dataset.key;
      const c = btn.dataset.color || '#0E7C86';
      document.getElementById('editColor').value    = c;
      document.getElementById('editColorHex').value = c;
      document.getElementById('editActive').checked = (btn.dataset.active === '1');
      editModal.show();
    });
  });

  // Delete modal
  const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
  document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const usage = parseInt(btn.dataset.usage || '0', 10);
      document.getElementById('deleteId').value = btn.dataset.id;
      document.getElementById('deleteLabel').textContent = btn.dataset.label;

      const warn = document.getElementById('deleteWarning');
      if (usage > 0) {
        warn.className = 'alert alert-danger mt-3 mb-0';
        warn.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i>
          This project is assigned to <strong>${usage} applicant(s)</strong>. You must remove those assignments first.`;
        document.querySelector('#deleteModal button[type="submit"]').disabled = true;
      } else {
        warn.className = 'alert alert-warning mt-3 mb-0';
        warn.innerHTML = '<i class="bi bi-info-circle me-1"></i>This action cannot be undone.';
        document.querySelector('#deleteModal button[type="submit"]').disabled = false;
      }
      deleteModal.show();
    });
  });

});
</script>
</body>
</html>
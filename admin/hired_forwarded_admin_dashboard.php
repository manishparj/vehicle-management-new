<?php
session_start();
include("../config/db.php");

/* ===== shared helpers (identical in both dashboards) ===== */
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

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function hired_csrf_token(): string {
    if (empty($_SESSION['hired_csrf'])) $_SESSION['hired_csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['hired_csrf'];
}
function hired_csrf_ok(): bool {
    return isset($_POST['csrf'], $_SESSION['hired_csrf']) && hash_equals($_SESSION['hired_csrf'], (string)$_POST['csrf']);
}
function hired_flash_set(string $type, string $msg): void { $_SESSION['hired_flash'] = ['type' => $type, 'msg' => $msg]; }
function hired_flash_get(): ?array {
    if (empty($_SESSION['hired_flash'])) return null;
    $f = $_SESSION['hired_flash']; unset($_SESSION['hired_flash']); return $f;
}
function hired_fmt_dt($v): string {
    if ($v === null || $v === '' || strpos((string)$v, '0000-00-00') === 0) return '';
    $t = strtotime((string)$v); return $t ? date('d/m/Y H:i', $t) : '';
}
function hired_money($n): string { return '₹' . number_format((float)$n, 2); }
function hired_load_cat_map(mysqli $conn): array {
    $map = []; $res = $conn->query("SELECT * FROM hired_vehicles");
    if ($res) { while ($c = $res->fetch_assoc()) $map[(int)$c['id']] = $c; }
    return $map;
}
function hired_load_project_map(mysqli $conn): array {
    $map = []; $res = $conn->query("SELECT id, label FROM projects");
    if ($res) { while ($p = $res->fetch_assoc()) $map[(int)$p['id']] = $p['label']; }
    return $map;
}
/** Group the assignment rows by application: [appId => ['app|cat|idx' => row]] */
function hired_load_assignments(mysqli $conn): array {
    $out = [];
    $ar = $conn->query("SELECT application_id, category_id, vehicle_index, driver_name, driver_mobile, vehicle_number, updated_at
                        FROM hired_application_vehicles WHERE deleted_at IS NULL");
    if ($ar) {
        while ($r = $ar->fetch_assoc()) {
            $out[(int)$r['application_id']][$r['application_id'].'|'.$r['category_id'].'|'.$r['vehicle_index']] = $r;
        }
    }
    return $out;
}
function hired_json(array $a): void { while (ob_get_level() > 0) ob_end_clean(); echo json_encode($a); exit; }

/* ---------- normalise one application row ---------- */
function buildAppRecord(array $app, array $catMap, array $projectMap, array $assign = []): array {
    $coTravelers  = json_decode($app['co_travelers'] ?? '[]', true);
    $selectedCats = json_decode($app['selected_categories'] ?? '[]', true);
    if (!is_array($coTravelers))  $coTravelers  = [];
    if (!is_array($selectedCats)) $selectedCats = [];

    $tripKey = $app['trip_type'] ?? '';
    $tripLbl = HIRED_TRIP_TYPES[$tripKey] ?? ucfirst(str_replace('_', ' ', (string)$tripKey));
    $locKey  = $app['place_of_visit'] ?? '';
    $locLbl  = HIRED_LOCATION_LABELS[$locKey] ?? ucfirst(str_replace('_', ' ', (string)$locKey));
    $projId  = (int)($app['project_id'] ?? 0);
    $appId   = (int)$app['id'];

    $cats = []; $totalPrice = 0.0; $totalVeh = 0; $assigned = 0;
    foreach ($selectedCats as $sc) {
        if (!is_array($sc)) continue;
        $ci = $catMap[(int)($sc['id'] ?? 0)] ?? null;
        if (!$ci) continue;

        $cTripKey = $ci['trip_type'] ?? '';
        $cTripLbl = HIRED_TRIP_TYPES[$cTripKey] ?? ucfirst(str_replace('_', ' ', (string)$cTripKey));
        $cLocKey  = $ci['location_type'] ?? '';
        $cLocLbl  = HIRED_LOCATION_LABELS[$cLocKey] ?? ucfirst(str_replace('_', ' ', (string)$cLocKey));
        if ($cLocKey === 'other' && !empty($ci['location_other'])) $cLocLbl = $ci['location_other'];

        $qty       = max(0, (int)($sc['qty'] ?? 0));
        $unit      = (float)$ci['price_per_vehicle'];
        $lineTotal = $unit * $qty;
        $totalPrice += $lineTotal; $totalVeh += $qty;

        for ($i = 1; $i <= $qty; $i++) {
            $a = $assign[$appId.'|'.(int)$ci['id'].'|'.$i] ?? null;
            if ($a && (trim((string)$a['driver_name']) !== '' || trim((string)$a['vehicle_number']) !== '')) $assigned++;
        }

        $cats[] = [
            'id'              => (int)$ci['id'],
            'category_name'   => $ci['category_name'],
            'trip_type_key'   => $cTripKey,
            'trip_type_label' => $cTripLbl,
            'location_label'  => $cLocLbl,
            'vehicle_details' => $ci['vehicle_details'] ?? '',
            'parameters'      => $ci['parameters'] ?? '',
            'unit_price'      => $unit,
            'qty'             => $qty,
            'line_total'      => $lineTotal,
        ];
    }

    $in = $app['in_km'] ?? ''; $out = $app['out_km'] ?? '';
    $km = (is_numeric($in) && is_numeric($out)) ? abs((float)$in - (float)$out) : 0;

    return [
        'id' => $appId, 'status' => $app['status'] ?? '', 'created_at' => $app['created_at'] ?? '',
        'approved_at' => $app['approved_at'] ?? '', 'forwarded_at' => $app['forwarded_at'] ?? '',
        'applicant_name' => $app['applicant_name'] ?? '', 'applicant_designation' => $app['applicant_designation'] ?? '',
        'applicant_email' => $app['applicant_email'] ?? '', 'applicant_mobile' => $app['applicant_mobile'] ?? '',
        'project' => $projectMap[$projId] ?? '', 'trip_type_key' => $tripKey, 'trip_type_label' => $tripLbl,
        'datetime_from' => $app['datetime_from'] ?? '', 'datetime_to' => $app['datetime_to'] ?? '',
        'place_required' => $app['place_required'] ?? '', 'place_of_visit_label' => $locLbl,
        'purpose' => $app['purpose'] ?? '', 'details' => $app['details'] ?? '',
        'co_travelers' => $coTravelers, 'categories' => $cats, 'total_price' => $totalPrice,
        'total_vehicles' => $totalVeh, 'assigned_vehicles' => $assigned,
        'tour_order_pdf' => $app['tour_order_pdf'] ?? '', 'super_admin_remark' => $app['super_admin_remark'] ?? '',
        'forwarded_remark' => $app['forwarded_remark'] ?? '', 'actual_departure' => $app['actual_departure'] ?? '',
        'actual_arrival' => $app['actual_arrival'] ?? '', 'in_km' => $in, 'out_km' => $out, 'total_km' => $km,
        'assignments' => $assign,
    ];
}

/* ---------- UI fragments ---------- */
function hired_render_header(string $title, string $icon, string $userName, array $nav = []): void { ?>
<header class="dash-header">
  <div class="container d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div class="dash-brand">
      <div class="brand-icon"><i class="bi bi-<?= h($icon) ?>"></i></div>
      <div style="min-width:0">
        <h1><?= h($title) ?></h1>
        <div class="sub">Hired Vehicle Management — ICMR-NIHR Jodhpur</div>
      </div>
    </div>
    <div class="dash-actions">
      <span class="date-chip"><i class="bi bi-calendar3"></i><?= h(date('D, d M Y')) ?></span>
      <span class="user-chip"><i class="bi bi-person-circle"></i><span><?= h($userName) ?></span></span>
      <?php foreach ($nav as [$href, $ico, $label]): ?>
        <a href="<?= h($href) ?>" class="btn btn-sm btn-outline-light"><i class="bi bi-<?= h($ico) ?> me-1"></i><?= h($label) ?></a>
      <?php endforeach; ?>
      <a href="hired_logout.php" class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
    </div>
  </div>
</header>
<?php }

function hired_render_stat($num, string $label, string $icon, string $tone = ''): void { ?>
<div class="stat-card <?= h($tone) ?>">
  <div class="stat-ico"><i class="bi bi-<?= h($icon) ?>"></i></div>
  <div class="stat-body">
    <div class="num"><?= (int)$num ?></div>
    <div class="lbl"><?= h($label) ?></div>
  </div>
</div>
<?php }

function hired_render_flash(?array $f): void {
    if (!$f) return;
    $type = in_array($f['type'], ['success', 'warning', 'danger', 'info'], true) ? $f['type'] : 'info';
    $ico  = $type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'; ?>
<div class="alert alert-<?= h($type) ?> d-flex align-items-center gap-2" role="alert">
  <i class="bi bi-<?= $ico ?>"></i><span><?= h($f['msg']) ?></span>
</div>
<?php }

function hired_render_empty(string $msg): void { ?>
<div class="empty-state"><i class="bi bi-inbox"></i><p><?= h($msg) ?></p></div>
<?php }

function hired_render_toast(): void { ?>
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:9999">
  <div id="copyToast" class="toast align-items-center text-bg-success border-0" role="status" aria-live="polite" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body"><i class="bi bi-check-circle-fill me-1"></i><span id="toastMsg">Done</span></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>
<?php }

/** Export toolbar (buttons are wired by admin-hired.js) */
function hired_render_toolbar(): void { ?>
<div class="toolbar no-print">
  <div class="tb-group">
    <span class="tb-label"><i class="bi bi-download me-1"></i>Export</span>
    <button type="button" class="btn-action" data-act="export-view-csv" title="Applications in the current tab, after filters"><i class="bi bi-filetype-csv"></i> Current tab (CSV)</button>
    <button type="button" class="btn-action" data-act="export-view-pdf" title="Applications in the current tab, after filters"><i class="bi bi-file-earmark-pdf"></i> Current tab (PDF)</button>
    <button type="button" class="btn-action" data-act="export-all-csv"><i class="bi bi-filetype-csv"></i> All (CSV)</button>
    <button type="button" class="btn-action" data-act="export-vehicles-csv"><i class="bi bi-truck"></i> Vehicles Dispatch (CSV)</button>
  </div>
</div>
<?php }

/** Filter bar (options are filled in by admin-hired.js from the loaded records) */
function hired_render_filters(): void { ?>
<section class="filter-panel no-print" aria-label="Filters">
  <div class="filter-head">
    <span class="filter-title"><i class="bi bi-funnel-fill"></i> Filters</span>
    <span class="filter-summary" id="filterSummary"></span>
    <button type="button" class="btn-action" data-act="filter-reset"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
  </div>
  <div class="filter-grid">
    <div class="f-field f-search">
      <label for="cardSearch">Search</label>
      <div class="search-box"><i class="bi bi-search"></i>
        <input type="search" id="cardSearch" data-filter="q" placeholder="Name, purpose, driver, vehicle no., ID…" autocomplete="off" aria-label="Search applications">
      </div>
    </div>
    <div class="f-field"><label for="fProject">Project</label><select id="fProject" class="form-select form-select-sm" data-filter="project"></select></div>
    <div class="f-field"><label for="fApplicant">Applicant</label><select id="fApplicant" class="form-select form-select-sm" data-filter="applicant"></select></div>
    <div class="f-field"><label for="fCategory">Vehicle category</label><select id="fCategory" class="form-select form-select-sm" data-filter="category"></select></div>
    <div class="f-field"><label for="fTrip">Trip type</label><select id="fTrip" class="form-select form-select-sm" data-filter="trip"></select></div>
    <div class="f-field"><label for="fPlace">Place of visit</label><select id="fPlace" class="form-select form-select-sm" data-filter="place"></select></div>
    <div class="f-field"><label for="fAssign">Driver / vehicle</label><select id="fAssign" class="form-select form-select-sm" data-filter="assign"></select></div>
    <div class="f-field"><label for="fFrom">Travel from</label><input type="date" id="fFrom" class="form-control form-control-sm" data-filter="from"></div>
    <div class="f-field"><label for="fTo">Travel to</label><input type="date" id="fTo" class="form-control form-control-sm" data-filter="to"></div>
  </div>
</section>
<?php }

/** One nav tab. $countKey = status (or comma list) whose filtered count is shown. */
function hired_render_tab(string $target, string $icon, string $label, ?string $countKey, int $count, bool $active = false, string $btnId = ''): void { ?>
<li class="nav-item" role="presentation">
  <button class="nav-link<?= $active ? ' active' : '' ?>" <?= $btnId !== '' ? 'id="' . h($btnId) . '"' : '' ?> data-bs-toggle="tab" data-bs-target="#<?= h($target) ?>" type="button" role="tab">
    <i class="bi bi-<?= h($icon) ?> me-1"></i><?= h($label) ?><?php if ($countKey !== null): ?> <span class="tab-count" data-count="<?= h($countKey) ?>"><?= $count ?></span><?php endif; ?>
  </button>
</li>
<?php }

/** A tab pane holding application cards. */
function hired_render_pane(string $id, string $label, string $statuses, array $list, array $opts, string $emptyMsg, bool $active = false, string $note = ''): void { ?>
<div class="tab-pane fade<?= $active ? ' show active' : '' ?>" id="<?= h($id) ?>" role="tabpanel" data-statuses="<?= h($statuses) ?>" data-label="<?= h($label) ?>">
  <?php foreach ($list as $rec) hired_render_card($rec, $opts); ?>
  <?php if ($note !== ''): ?><p class="text-center text-muted small"><?= h($note) ?></p><?php endif; ?>
  <div class="no-match"><i class="bi bi-funnel me-1"></i>No applications match the current filters.</div>
  <?php if (!$list) hired_render_empty($emptyMsg); ?>
</div>
<?php }

/**
 * Render one application card.
 * $opts: form      => 'approve' | 'complete' | null
 *        scheduled => bool
 *        readonly  => bool  (driver/vehicle details visible but not editable)
 */
function hired_render_card(array $r, array $opts = []): void {
    $scheduled = !empty($opts['scheduled']);
    $form      = $opts['form'] ?? null;
    $readonly  = !empty($opts['readonly']);
    $status    = $r['status'];
    $from      = hired_fmt_dt($r['datetime_from']);
    $to        = hired_fmt_dt($r['datetime_to']);

    $hasActual = ($status === 'completed') || $r['actual_departure'] || $r['actual_arrival']
                 || ($r['in_km'] !== '' && $r['in_km'] !== null) || ($r['out_km'] !== '' && $r['out_km'] !== null);

    $isEditable = ($status === 'pending') && !$readonly;
    $locked     = !$isEditable;
    $totalVeh   = (int)$r['total_vehicles'];
    $co         = array_map('strval', $r['co_travelers']);
    ?>
<article class="app-card is-<?= h($status) ?>" data-app-id="<?= (int)$r['id'] ?>">
  <div class="app-head">
    <div class="who">
      <span class="app-id">#<?= (int)$r['id'] ?></span>
      <div class="app-name"><?= h($r['applicant_name']) ?>
        <?php if ($r['applicant_designation'] !== ''): ?><span class="designation">— <?= h($r['applicant_designation']) ?></span><?php endif; ?>
      </div>
      <div class="app-meta">
        <?php if ($r['applicant_email'] !== ''): ?><span><i class="bi bi-envelope"></i><a href="mailto:<?= h($r['applicant_email']) ?>"><?= h($r['applicant_email']) ?></a></span><?php endif; ?>
        <?php if ($r['applicant_mobile'] !== ''): ?><span><i class="bi bi-phone"></i><a href="tel:<?= h($r['applicant_mobile']) ?>"><?= h($r['applicant_mobile']) ?></a></span><?php endif; ?>
      </div>
    </div>
    <div class="badges">
      <span class="badge-status <?= h($status) ?>"><?= h(ucfirst($status)) ?></span>
      <span class="badge-pill total-veh-badge">Vehicles: <b><?= $totalVeh ?></b></span>
      <span class="badge-pill">Est. cost: <b class="js-cost-badge"><?= h(hired_money($r['total_price'])) ?></b></span>
    </div>
  </div>

  <div class="action-row no-print">
    <button type="button" class="btn-action" data-act="copy"><i class="bi bi-clipboard"></i> Copy details</button>
    <button type="button" class="btn-action" data-act="print"><i class="bi bi-file-earmark-pdf"></i> Download PDF</button>
  </div>

  <div class="detail-grid">
    <?php if ($r['project'] !== ''): ?><div><span class="dt">Project</span><span class="dd"><?= h($r['project']) ?></span></div><?php endif; ?>
    <?php if ($r['trip_type_key'] !== ''): ?>
    <div><span class="dt">Trip Type</span><span class="dd"><span class="badge-trip <?= h($r['trip_type_key']) ?>"><i class="bi bi-<?= $r['trip_type_key'] === 'one_way' ? 'arrow-right' : 'arrow-left-right' ?> me-1"></i><?= h($r['trip_type_label']) ?></span></span></div>
    <?php endif; ?>
    <div><span class="dt"><?= $scheduled ? 'Scheduled From' : 'From' ?></span><span class="dd"><?= h($from ?: '—') ?></span></div>
    <?php if ($to !== ''): ?><div><span class="dt"><?= $scheduled ? 'Scheduled To' : 'To' ?></span><span class="dd"><?= h($to) ?></span></div><?php endif; ?>
    <div><span class="dt">Place Required</span><span class="dd"><?= h($r['place_required'] ?: '—') ?></span></div>
    <div><span class="dt">Place of Visit</span><span class="dd"><?= h($r['place_of_visit_label'] ?: '—') ?></span></div>
    <div><span class="dt">Purpose</span><span class="dd"><?= h($r['purpose'] ?: '—') ?></span></div>
    <div><span class="dt">Submitted</span><span class="dd"><?= h(hired_fmt_dt($r['created_at']) ?: '—') ?></span></div>
    <?php if (hired_fmt_dt($r['approved_at']) !== ''): ?><div><span class="dt">Processed On</span><span class="dd"><?= h(hired_fmt_dt($r['approved_at'])) ?></span></div><?php endif; ?>
    <?php if (hired_fmt_dt($r['forwarded_at']) !== ''): ?><div><span class="dt">Completed On</span><span class="dd"><?= h(hired_fmt_dt($r['forwarded_at'])) ?></span></div><?php endif; ?>
    <?php if (!empty($r['details'])): ?><div class="span-all"><span class="dt">Details</span><span class="dd"><?= nl2br(h($r['details'])) ?></span></div><?php endif; ?>
    <?php if ($co): ?><div class="span-all"><span class="dt">Co-Travelers (<?= count($co) ?>)</span><span class="dd"><?= h(implode(', ', $co)) ?></span></div><?php endif; ?>
  </div>

  <?php if (!empty($r['categories'])): ?>
  <div class="card-section">
    <div class="section-head">
      <h3 class="section-title"><i class="bi bi-truck-front"></i> Vehicle Categories, Drivers &amp; Vehicles</h3>
      <?php if ($isEditable): ?>
        <button type="button" class="btn btn-save" data-act="save-all-assignments"><i class="bi bi-save me-1"></i>Save Driver &amp; Vehicle Details</button>
      <?php endif; ?>
    </div>

    <?php foreach ($r['categories'] as $cat):
        $cid = (int)$cat['id']; $qty = (int)$cat['qty']; ?>
    <div class="cat-card cat-wrapper" data-cat="<?= $cid ?>" data-app="<?= (int)$r['id'] ?>" data-unit="<?= h($cat['unit_price']) ?>">
      <div class="cat-card-head">
        <div class="cat-title"><i class="bi bi-truck"></i><span><?= h($cat['category_name']) ?></span><span class="cat-id">#<?= $cid ?></span></div>
        <div class="cat-pricing">
          <span class="chip"><?= h(hired_money($cat['unit_price'])) ?> × <b class="js-qty-label"><?= $qty ?></b></span>
          <span class="chip chip-total js-line"><?= h(hired_money($cat['line_total'])) ?></span>
          <?php if (!$readonly): ?><button type="button" class="btn-icon edit no-print" data-act="edit-cat" title="Edit category"
              data-cat-id="<?= $cid ?>" data-cat-name="<?= h($cat['category_name']) ?>" data-cat-price="<?= h($cat['unit_price']) ?>"
              data-cat-details="<?= h($cat['vehicle_details']) ?>" data-cat-params="<?= h($cat['parameters']) ?>"><i class="bi bi-pencil"></i></button><?php endif; ?>
        </div>
      </div>

      <div class="cat-info">
        <div><span class="lbl">Trip Type</span><span class="val"><?= h($cat['trip_type_label'] ?: '—') ?></span></div>
        <div><span class="lbl">Location</span><span class="val"><?= h($cat['location_label'] ?: '—') ?></span></div>
        <?php if (!empty($cat['vehicle_details'])): ?><div class="span-all"><span class="lbl">Vehicle Details</span><span class="val"><?= nl2br(h($cat['vehicle_details'])) ?></span></div><?php endif; ?>
        <?php if (!empty($cat['parameters'])): ?><div class="span-all"><span class="lbl">Parameters</span><span class="val"><?= nl2br(h($cat['parameters'])) ?></span></div><?php endif; ?>
      </div>

      <div class="assign-bar">
        <div class="assign-title"><i class="bi bi-person-badge"></i> Driver &amp; Vehicle Assignments</div>
        <?php if ($isEditable): ?>
        <div class="qty-control" role="group" aria-label="Vehicle quantity">
          <button type="button" data-act="qty-dec" aria-label="Decrease">−</button>
          <input type="number" class="qty-input" value="<?= $qty ?>" min="0" readonly aria-label="Quantity">
          <button type="button" data-act="qty-inc" aria-label="Increase">+</button>
        </div>
        <?php else: ?>
        <span class="badge-pill">Qty: <b><?= $qty ?></b><?= $readonly ? '' : ' · Locked (' . h($status) . ')' ?></span>
        <input type="hidden" class="qty-input" value="<?= $qty ?>">
        <?php endif; ?>
      </div>

      <div class="table-scroll">
        <table class="veh-table">
          <thead>
            <tr><th class="c-idx">#</th><th>Driver Name</th><th class="c-mob">Driver Mobile</th><th class="c-num">Vehicle Number</th><th class="c-upd">Last Updated</th><?php if ($isEditable): ?><th class="c-act">Action</th><?php endif; ?></tr>
          </thead>
          <tbody class="veh-tbody">
            <?php for ($i = 1; $i <= $qty; $i++):
                $row = $r['assignments'][$r['id'].'|'.$cid.'|'.$i] ?? null; ?>
            <tr data-app="<?= (int)$r['id'] ?>" data-cat="<?= $cid ?>" data-idx="<?= $i ?>">
              <td class="veh-idx"><?= $i ?></td>
              <td data-label="Driver"><input class="veh-input" name="driver_name" maxlength="150" value="<?= h($row['driver_name'] ?? '') ?>" <?= $locked ? 'readonly' : '' ?> placeholder="<?= $locked ? '—' : 'Driver name' ?>" aria-label="Driver name"></td>
              <td data-label="Mobile"><input class="veh-input" name="driver_mobile" maxlength="10" inputmode="numeric" value="<?= h($row['driver_mobile'] ?? '') ?>" <?= $locked ? 'readonly' : '' ?> placeholder="<?= $locked ? '—' : '10-digit mobile' ?>" aria-label="Driver mobile"></td>
              <td data-label="Vehicle No."><input class="veh-input" name="vehicle_number" maxlength="30" value="<?= h($row['vehicle_number'] ?? '') ?>" <?= $locked ? 'readonly' : '' ?> placeholder="<?= $locked ? '—' : 'RJ14-AB-1234' ?>" aria-label="Vehicle number"></td>
              <td class="updated-at" data-label="Updated"><?= $row && !empty($row['updated_at']) ? h(date('d/m/Y H:i', strtotime($row['updated_at']))) : '—' ?></td>
              <?php if ($isEditable): ?>
              <td data-label="Action"><div class="veh-actions"><button type="button" class="btn-icon danger" data-act="del-row" title="Remove row" aria-label="Remove row"><i class="bi bi-trash"></i></button></div></td>
              <?php endif; ?>
            </tr>
            <?php endfor; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endforeach; ?>

    <div class="cat-total"><span>Estimated total (<span class="js-veh-total"><?= $totalVeh ?></span> vehicles)</span><span class="js-grand"><?= h(hired_money($r['total_price'])) ?></span></div>
  </div>
  <?php endif; ?>

  <?php if (!empty($r['super_admin_remark'])): ?><div class="remark-box"><span class="dt">Super Admin Remark</span><?= h($r['super_admin_remark']) ?></div><?php endif; ?>
  <?php if (!empty($r['forwarded_remark'])): ?><div class="remark-box fwd"><span class="dt">Forwarded Remark</span><?= h($r['forwarded_remark']) ?></div><?php endif; ?>

  <?php if ($hasActual): ?>
  <div class="card-section">
    <h3 class="section-title"><i class="bi bi-speedometer2"></i>Actual Trip Record</h3>
    <div class="detail-grid" style="margin-top:8px">
      <div><span class="dt">Actual Departure</span><span class="dd"><?= h(hired_fmt_dt($r['actual_departure']) ?: '—') ?></span></div>
      <div><span class="dt">Actual Arrival</span><span class="dd"><?= h(hired_fmt_dt($r['actual_arrival']) ?: '—') ?></span></div>
      <div><span class="dt">Out KM</span><span class="dd"><?= ($r['out_km'] !== '' && $r['out_km'] !== null) ? h($r['out_km']) : '—' ?></span></div>
      <div><span class="dt">In KM</span><span class="dd"><?= ($r['in_km'] !== '' && $r['in_km'] !== null) ? h($r['in_km']) : '—' ?></span></div>
      <?php if (is_numeric($r['in_km']) && is_numeric($r['out_km'])): ?><div><span class="dt">Total KM</span><span class="dd"><strong><?= h(number_format($r['total_km'], 2)) ?></strong></span></div><?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!empty($r['tour_order_pdf'])): ?>
  <div class="mt-3 no-print"><a href="../uploads/hired_tour_order/<?= h(rawurlencode($r['tour_order_pdf'])) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary btn-tour"><i class="bi bi-file-pdf me-1"></i>View Tour Order</a></div>
  <?php endif; ?>

  <?php if ($form === 'approve'): ?>
  <hr class="my-4">
  <form method="POST" class="form-panel row g-2 align-items-end" data-confirm-form>
    <input type="hidden" name="csrf" value="<?= h(hired_csrf_token()) ?>">
    <input type="hidden" name="application_id" value="<?= (int)$r['id'] ?>">
    <div class="col-12 col-md-6">
      <label class="form-label" for="remark-<?= (int)$r['id'] ?>">Admin Remark <span class="text-muted text-lowercase fw-normal">(optional)</span></label>
      <input id="remark-<?= (int)$r['id'] ?>" type="text" name="remark" maxlength="500" class="form-control form-control-sm" placeholder="Add a remark before approving/rejecting…">
    </div>
    <div class="col-12 col-md-6 d-flex flex-column flex-sm-row gap-2">
      <button type="submit" name="action" value="approve" class="btn btn-approve btn-sm flex-fill"><i class="bi bi-check-lg me-1"></i>Approve Application</button>
      <button type="submit" name="action" value="reject" class="btn btn-reject btn-sm flex-fill"><i class="bi bi-x-lg me-1"></i>Reject Application</button>
    </div>
  </form>
  <?php elseif ($form === 'complete'): ?>
  <hr class="my-4">
  <form method="POST" class="form-panel row g-3 align-items-end" data-complete-form>
    <input type="hidden" name="csrf" value="<?= h(hired_csrf_token()) ?>">
    <input type="hidden" name="application_id" value="<?= (int)$r['id'] ?>">
    <input type="hidden" name="complete_application" value="1">
    <div class="col-12 col-sm-6 col-lg-3"><label class="form-label">Actual Departure <span class="text-danger">*</span></label><input type="datetime-local" name="actual_departure" class="form-control form-control-sm" required></div>
    <div class="col-12 col-sm-6 col-lg-3"><label class="form-label">Actual Arrival <span class="text-danger">*</span></label><input type="datetime-local" name="actual_arrival" class="form-control form-control-sm" required></div>
    <div class="col-6 col-lg-2"><label class="form-label">Out KM</label><input type="number" name="out_km" class="form-control form-control-sm" step="0.01" min="0" inputmode="decimal" placeholder="0"></div>
    <div class="col-6 col-lg-2"><label class="form-label">In KM</label><input type="number" name="in_km" class="form-control form-control-sm" step="0.01" min="0" inputmode="decimal" placeholder="0"></div>
    <div class="col-12 col-lg-2"><label class="form-label">Remark</label><input type="text" name="forwarded_remark" maxlength="500" class="form-control form-control-sm" placeholder="Optional"></div>
    <div class="col-12"><button type="submit" class="btn btn-complete btn-sm"><i class="bi bi-check-lg me-1"></i>Mark as Completed</button></div>
  </form>
  <?php endif; ?>
</article>
<?php }

/* ===== end shared helpers ===== */

/* ---------------------------------------------------------------
   VISIBILITY RULE (logged-in forwarded admin only)
   - Completed  : only applications this admin completed (forwarded_admin_id = me)
   - Approved   : applications assigned to this admin (forwarded_admin_id = me)
                  + when HIRED_FWD_SEE_UNASSIGNED is true, approved applications
                  that nobody is assigned to yet (forwarded_admin_id NULL/0).
                  Set it to false once the super admin assigns a forwarded admin
                  to every approved application.
   --------------------------------------------------------------- */
const HIRED_FWD_SEE_UNASSIGNED = true;

/* ---------- datetime-local -> MySQL DATETIME (null when invalid) ---------- */
function hired_parse_local_dt(string $v): ?string {
    $v = trim(str_replace('T', ' ', $v));
    if ($v === '') return null;
    $d = DateTime::createFromFormat('Y-m-d H:i', $v) ?: DateTime::createFromFormat('Y-m-d H:i:s', $v);
    return $d ? $d->format('Y-m-d H:i:s') : null;
}

// ================= AUTH GUARD =================
if (!isset($_SESSION['hired_user_id']) || ($_SESSION['hired_role'] ?? '') !== 'hired_forwarded_admin') {
    header("Location: hired_login.php");
    exit;
}
$adminId   = (int)$_SESSION['hired_user_id'];
$adminName = $_SESSION['hired_user_name'] ?? 'Forwarded Admin';

// ================= HANDLE COMPLETION =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_application'])) {
    if (!hired_csrf_ok()) {
        hired_flash_set('danger', 'Your session token expired. Please try again.');
    } else {
        $appId     = (int)($_POST['application_id'] ?? 0);
        $departure = hired_parse_local_dt($_POST['actual_departure'] ?? '');
        $arrival   = hired_parse_local_dt($_POST['actual_arrival'] ?? '');
        $inKm      = max(0.0, (float)($_POST['in_km'] ?? 0));
        $outKm     = max(0.0, (float)($_POST['out_km'] ?? 0));
        $fwdRemark = mb_substr(trim($_POST['forwarded_remark'] ?? ''), 0, 500);

        if ($appId <= 0 || $departure === null || $arrival === null) {
            hired_flash_set('warning', 'Please fill all required fields (Actual Departure & Arrival) with valid dates.');
        } elseif (strtotime($arrival) < strtotime($departure)) {
            hired_flash_set('warning', 'Actual Arrival cannot be earlier than Actual Departure.');
        } else {
            // the application must belong to (or be unassigned for) THIS admin
            $scope = HIRED_FWD_SEE_UNASSIGNED
                ? "(forwarded_admin_id IS NULL OR forwarded_admin_id = 0 OR forwarded_admin_id = ?)"
                : "forwarded_admin_id = ?";
            $upd = $conn->prepare("UPDATE hired_applications
                                   SET actual_departure = ?, actual_arrival = ?, in_km = ?, out_km = ?,
                                       forwarded_remark = ?, forwarded_admin_id = ?, forwarded_at = NOW(),
                                       status = 'completed'
                                   WHERE id = ? AND status = 'approved' AND $scope");
            $upd->bind_param("ssddsiii", $departure, $arrival, $inKm, $outKm, $fwdRemark, $adminId, $appId, $adminId);
            if ($upd->execute() && $upd->affected_rows > 0) {
                hired_flash_set('success', "Application #{$appId} has been marked as completed.");
            } else {
                hired_flash_set('warning', "Could not update application #{$appId}. It may not be approved or not assigned to you.");
            }
            $upd->close();
        }
    }
    header("Location: hired_forwarded_admin_dashboard.php");
    exit;
}

$flash = hired_flash_get();

// ================= FETCH DATA (only this admin's applications) =================
$catMap      = hired_load_cat_map($conn);
$projectMap  = hired_load_project_map($conn);
$assignByApp = hired_load_assignments($conn);

$records = []; $loadError = '';
try {
    $sql = "SELECT * FROM hired_applications
            WHERE (status = 'approved' AND (forwarded_admin_id = ?" . (HIRED_FWD_SEE_UNASSIGNED ? " OR forwarded_admin_id IS NULL OR forwarded_admin_id = 0" : "") . "))
               OR (status = 'completed' AND forwarded_admin_id = ?)
            ORDER BY created_at DESC";
    $q = $conn->prepare($sql);
    $q->bind_param("ii", $adminId, $adminId);
    $q->execute();
    $appRes = $q->get_result();
    while ($a = $appRes->fetch_assoc()) {
        $id = (int)$a['id'];
        $records[$id] = buildAppRecord($a, $catMap, $projectMap, $assignByApp[$id] ?? []);
    }
    $q->close();
} catch (Throwable $e) {
    $loadError = 'Could not load applications: ' . $e->getMessage();
}

$approvedList = array_values(array_filter($records, fn($r) => $r['status'] === 'approved'));
usort($approvedList, fn($a, $b) => strcmp($a['datetime_from'], $b['datetime_from']));                         // earliest trip first

$completedAll = array_values(array_filter($records, fn($r) => $r['status'] === 'completed'));
usort($completedAll, fn($a, $b) => strcmp($b['forwarded_at'] ?: $b['created_at'], $a['forwarded_at'] ?: $a['created_at']));
$completedList = array_slice($completedAll, 0, 100);

$aCount = count($approvedList);
$cTotal = count($completedAll);
$cShown = count($completedList);
$vehAwaiting = array_sum(array_column($approvedList, 'total_vehicles'));
$vehAssigned = array_sum(array_column($approvedList, 'assigned_vehicles'));
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
           | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forwarded Admin Dashboard — Hired Vehicles</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
<link href="admin-hired.css?v=<?= (int)@filemtime(__DIR__ . '/admin-hired.css') ?>" rel="stylesheet" data-common-css>
</head>
<body>

<?php hired_render_header('Forwarded Admin Dashboard', 'truck', $adminName); ?>

<main class="container dash-body">

  <div class="row g-3 stats-row mb-4">
    <div class="col-6 col-lg-3"><?php hired_render_stat($aCount,      'Approved — Awaiting Completion', 'check-circle',  'approved'); ?></div>
    <div class="col-6 col-lg-3"><?php hired_render_stat($cTotal,      'Completed',                      'flag'); ?></div>
    <div class="col-6 col-lg-3"><?php hired_render_stat($vehAwaiting, 'Vehicles Awaiting Trip',         'truck-front',   'navy'); ?></div>
    <div class="col-6 col-lg-3"><?php hired_render_stat($vehAssigned, 'Drivers / Vehicles Assigned',    'person-check',  'gold'); ?></div>
  </div>

  <?php hired_render_flash($flash); ?>
  <?php if ($loadError !== ''): hired_render_flash(['type' => 'danger', 'msg' => $loadError]); endif; ?>

  <?php hired_render_filters(); ?>
  <?php hired_render_toolbar(); ?>

  <ul class="nav nav-tabs mb-3" id="dashTabs" role="tablist">
    <?php hired_render_tab('tab-approved',  'check-circle', 'Approved',  'approved',  $aCount, true); ?>
    <?php hired_render_tab('tab-completed', 'flag',         'Completed', 'completed', $cTotal); ?>
  </ul>

  <div class="tab-content">
    <?php hired_render_pane('tab-approved', 'Approved', 'approved',
        $approvedList, ['scheduled' => true, 'form' => 'complete', 'readonly' => true],
        'No approved applications awaiting completion.', true); ?>

    <?php hired_render_pane('tab-completed', 'Completed', 'completed',
        $completedList, ['scheduled' => true, 'readonly' => true],
        'No completed applications yet.', false,
        $cTotal > $cShown ? "Showing the latest {$cShown} of {$cTotal} completed applications. Use “Current tab” export to get all of them." : ''); ?>
  </div>
</main>

<footer class="dash-footer container">ICMR-NIHR Jodhpur &middot; <b>Hired Vehicle Management</b> &middot; &copy; <?= date('Y') ?></footer>

<?php hired_render_toast(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* =====================================================================
   admin-hired.js — shared client logic for the Hired Vehicle dashboards
   Provides: filters + search, tab counts, "Copy details" (WhatsApp style
   with icons), professional print/PDF, CSV exports.
   Usage:  const D = HiredDash.init({ records: RECORDS, csrf: CSRF });
   Load AFTER bootstrap bundle.
   ===================================================================== */
(function (win) {
  'use strict';

  /* ---------------- utilities ---------------- */
  const $  = (s, c) => (c || document).querySelector(s);
  const $$ = (s, c) => Array.from((c || document).querySelectorAll(s));
  const esc = v => String(v ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
  const cap = s => { s = String(s || ''); return s.charAt(0).toUpperCase() + s.slice(1); };
  const money = v => '₹' + Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const num = v => Number(v || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
  const hasVal = v => v !== '' && v !== null && v !== undefined;
  const pad = n => String(n).padStart(2, '0');
  const dash = v => (hasVal(v) && String(v).trim() !== '') ? String(v) : '—';
  const deepCopy = o => JSON.parse(JSON.stringify(o));
  function fmtDt(v) {
    const s = String(v || ''); if (!s || s.startsWith('0000-00-00')) return '';
    const d = new Date(s.replace(' ', 'T')); if (isNaN(d)) return s;
    return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }
  const util = { $, $$, esc, cap, money, num, hasVal, dash, fmtDt, deepCopy };

  function init(cfg) {
    const CSRF = cfg.csrf, RECORDS = cfg.records || [], BY_ID = {};
    RECORDS.forEach(r => { BY_ID[r.id] = r; });
    const listeners = [];

    /* ---------------- toast + ajax ---------------- */
    function showToast(msg, tone) {
      const el = $('#copyToast'); if (!el) return;
      $('#toastMsg').textContent = msg;
      el.classList.remove('text-bg-success', 'text-bg-danger', 'text-bg-warning');
      el.classList.add(tone === 'error' ? 'text-bg-danger' : tone === 'warn' ? 'text-bg-warning' : 'text-bg-success');
      bootstrap.Toast.getOrCreateInstance(el, { delay: 2600 }).show();
    }
    async function post(fields) {
      const fd = new FormData(); fd.append('csrf', CSRF);
      Object.entries(fields).forEach(([k, v]) => fd.append(k, v));
      const resp = await fetch(location.href, { method: 'POST', body: fd, credentials: 'same-origin' });
      return resp.json();
    }

    /* ---------------- live record (server snapshot + what is on screen) ---------------- */
    function liveRecord(card) {
      const r = deepCopy(BY_ID[card.dataset.appId]);
      const asg = {}; let veh = 0, total = 0, assigned = 0;
      $$('.cat-wrapper', card).forEach(w => {
        const cid = +w.dataset.cat, cat = r.categories.find(c => c.id === cid);
        const qty = parseInt($('.qty-input', w)?.value) || 0;
        if (cat) { cat.qty = qty; cat.line_total = qty * cat.unit_price; veh += qty; total += cat.line_total; }
        $$('tr[data-idx]', w).forEach(tr => {
          const row = {
            driver_name: $('[name=driver_name]', tr).value.trim(),
            driver_mobile: $('[name=driver_mobile]', tr).value.trim(),
            vehicle_number: $('[name=vehicle_number]', tr).value.trim(),
            updated_at: ($('.updated-at', tr)?.textContent || '').trim()
          };
          if (row.driver_name || row.vehicle_number) assigned++;
          asg[`${r.id}|${cid}|${tr.dataset.idx}`] = row;
        });
      });
      r.assignments = asg; r.total_vehicles = veh; r.total_price = total; r.assigned_vehicles = assigned;
      return r;
    }
    const asgFor = (r, cat) => {
      const out = [], a = r.assignments || {};
      for (let i = 1; i <= cat.qty; i++) out.push(Object.assign({ idx: i, driver_name: '', driver_mobile: '', vehicle_number: '' }, a[`${r.id}|${cat.id}|${i}`] || {}));
      return out;
    };
    const kmText = r => (hasVal(r.in_km) && hasVal(r.out_km) && !isNaN(r.in_km) && !isNaN(r.out_km)) ? num(Math.abs(r.in_km - r.out_km)) : '';
    const tourUrl = r => r.tour_order_pdf ? new URL('../uploads/hired_tour_order/' + encodeURIComponent(r.tour_order_pdf), location.href).href : '';

    /* ============================================================
       FILTERS + SEARCH
       ============================================================ */
    const FILTERS = { q: '', project: '', applicant: '', category: '', trip: '', place: '', assign: '', from: '', to: '' };
    const hayCache = {};
    function hay(id) {
      if (hayCache[id]) return hayCache[id];
      const r = BY_ID[id]; if (!r) return '';
      const drv = Object.values(r.assignments || {}).map(a => [a.driver_name, a.driver_mobile, a.vehicle_number].join(' ')).join(' ');
      return hayCache[id] = [r.id, r.status, r.applicant_name, r.applicant_designation, r.applicant_email, r.applicant_mobile, r.project, r.purpose, r.details,
        r.place_required, r.place_of_visit_label, r.trip_type_label, r.co_travelers.join(' '), r.categories.map(c => c.category_name).join(' '), drv].join(' ').toLowerCase();
    }
    const projectOf = r => r.project || 'No project';
    function assignState(r) {
      const t = r.total_vehicles, a = r.assigned_vehicles || 0;
      return (!t || a === 0) ? 'none' : (a >= t ? 'full' : 'partial');
    }
    function matches(r) {
      const f = FILTERS;
      if (f.project   && projectOf(r) !== f.project) return false;
      if (f.applicant && r.applicant_name !== f.applicant) return false;
      if (f.category  && !r.categories.some(c => c.category_name === f.category)) return false;
      if (f.trip      && r.trip_type_label !== f.trip) return false;
      if (f.place     && r.place_of_visit_label !== f.place) return false;
      if (f.assign    && assignState(r) !== f.assign) return false;
      const d = String(r.datetime_from || '').slice(0, 10);
      if (f.from && (!d || d < f.from)) return false;
      if (f.to   && (!d || d > f.to)) return false;
      if (f.q && !hay(r.id).includes(f.q)) return false;
      return true;
    }
    const filtered = () => RECORDS.filter(matches);

    function fillSelect(id, label, values) {
      const el = $('#' + id); if (!el) return;
      const uniq = [...new Set(values.filter(v => v && String(v).trim() !== ''))].sort((a, b) => String(a).localeCompare(String(b)));
      el.innerHTML = `<option value="">All ${esc(label)}</option>` + uniq.map(v => `<option value="${esc(v)}">${esc(v)}</option>`).join('');
    }
    function buildFilterOptions() {
      fillSelect('fProject', 'projects', RECORDS.map(projectOf));
      fillSelect('fApplicant', 'applicants', RECORDS.map(r => r.applicant_name));
      fillSelect('fCategory', 'categories', RECORDS.flatMap(r => r.categories.map(c => c.category_name)));
      fillSelect('fTrip', 'trip types', RECORDS.map(r => r.trip_type_label));
      fillSelect('fPlace', 'places', RECORDS.map(r => r.place_of_visit_label));
      const a = $('#fAssign');
      if (a) a.innerHTML = '<option value="">All</option><option value="full">Fully assigned</option><option value="partial">Partly assigned</option><option value="none">Not assigned</option>';
    }

    function applyFilters() {
      $$('.app-card').forEach(c => { const r = BY_ID[c.dataset.appId]; c.classList.toggle('is-hidden', !(r && matches(r))); });
      $$('.tab-pane').forEach(p => {
        const cards = $$('.app-card', p), nm = $('.no-match', p); if (!nm) return;
        nm.classList.toggle('show', cards.length > 0 && cards.every(c => c.classList.contains('is-hidden')));
      });
      const counts = {}; let shown = 0;
      RECORDS.forEach(r => { if (matches(r)) { counts[r.status] = (counts[r.status] || 0) + 1; shown++; } });
      $$('[data-count]').forEach(el => { el.textContent = el.dataset.count.split(',').reduce((s, k) => s + (counts[k] || 0), 0); });
      $$('[data-filter]').forEach(el => el.closest('.f-field')?.classList.toggle('is-active', !!FILTERS[el.dataset.filter]));
      const active = Object.values(FILTERS).filter(Boolean).length, sum = $('#filterSummary');
      if (sum) sum.textContent = active ? `${shown} of ${RECORDS.length} applications match · ${active} filter${active > 1 ? 's' : ''} active` : `${RECORDS.length} applications`;
      listeners.forEach(fn => fn());
    }
    function resetFilters() {
      Object.keys(FILTERS).forEach(k => FILTERS[k] = '');
      $$('[data-filter]').forEach(el => { el.value = ''; });
      applyFilters();
    }
    function bindFilters() {
      let t;
      $$('[data-filter]').forEach(el => {
        const key = el.dataset.filter;
        const handler = () => { FILTERS[key] = key === 'q' ? el.value.trim().toLowerCase() : el.value; applyFilters(); };
        if (key === 'q') el.addEventListener('input', () => { clearTimeout(t); t = setTimeout(handler, 150); });
        else el.addEventListener('change', handler);
      });
    }

    /* ============================================================
       COPY DETAILS — WhatsApp-friendly, every field, with icons
       ============================================================ */
    const STATUS_ICON = { pending: '⏳', approved: '✅', rejected: '❌', completed: '🏁' };
    const NUM_ICON = ['0️⃣', '1️⃣', '2️⃣', '3️⃣', '4️⃣', '5️⃣', '6️⃣', '7️⃣', '8️⃣', '9️⃣'];
    const numIcon = n => String(n).split('').map(d => NUM_ICON[+d]).join('');
    function recordText(r) {
      const L = [], line = (ico, label, val) => L.push(`${ico} ${label}: ${dash(val)}`);
      const rule = '━━━━━━━━━━━━━━━━━━━━';
      L.push(`🚐 *HIRED VEHICLE REQUEST #${r.id}*`, `${STATUS_ICON[r.status] || '📄'} Status: *${cap(r.status)}*`, rule);

      L.push('', '👤 *APPLICANT*');
      line('🙍', 'Name', r.applicant_name); line('💼', 'Designation', r.applicant_designation);
      line('📧', 'Email', r.applicant_email); line('📱', 'Mobile', r.applicant_mobile);

      L.push('', '🧭 *TRIP DETAILS*');
      line('🗂️', 'Project', r.project); line('🔁', 'Trip Type', r.trip_type_label);
      line('🗓️', 'From', fmtDt(r.datetime_from)); line('🗓️', 'To', fmtDt(r.datetime_to));
      line('📍', 'Place Required', r.place_required); line('🗺️', 'Place of Visit', r.place_of_visit_label);
      line('🎯', 'Purpose', r.purpose); line('📝', 'Details', String(r.details || '').replace(/\s*\n\s*/g, '; '));
      line('👥', `Co-Travelers (${r.co_travelers.length})`, r.co_travelers.join(', '));

      L.push('', rule, `🚗 *VEHICLES* (${r.total_vehicles} requested · ${r.assigned_vehicles || 0} assigned)`);
      if (!r.categories.length) L.push('—');
      r.categories.forEach((c, n) => {
        L.push('', `${numIcon(n + 1)} *${c.category_name}* (#${c.id})`);
        line('🔁', 'Trip Type', c.trip_type_label); line('📍', 'Location', c.location_label);
        L.push(`💰 Rate: ${money(c.unit_price)} × ${c.qty} = *${money(c.line_total)}*`);
        line('🛠️', 'Vehicle Details', String(c.vehicle_details || '').replace(/\s*\n\s*/g, '; '));
        line('⚙️', 'Parameters', String(c.parameters || '').replace(/\s*\n\s*/g, '; '));
        asgFor(r, c).forEach(a => L.push(`   🚘 Vehicle ${a.idx} → 👨‍✈️ ${dash(a.driver_name)} | 📞 ${dash(a.driver_mobile)} | 🔢 ${dash(a.vehicle_number)}`));
      });
      L.push('', `💵 *ESTIMATED TOTAL: ${money(r.total_price)}*`, rule);

      L.push('', '💬 *REMARKS*');
      line('🛡️', 'Super Admin', r.super_admin_remark); line('📤', 'Forwarded Admin', r.forwarded_remark);

      L.push('', '🏁 *ACTUAL TRIP RECORD*');
      line('🛫', 'Departure', fmtDt(r.actual_departure)); line('🛬', 'Arrival', fmtDt(r.actual_arrival));
      line('⏱️', 'Out KM', r.out_km); line('⏱️', 'In KM', r.in_km); line('🛣️', 'Total KM', kmText(r));
      line('📎', 'Tour Order', tourUrl(r));

      L.push('', '🕒 *TIMELINE*');
      line('📥', 'Submitted', fmtDt(r.created_at)); line('✔️', 'Processed On', fmtDt(r.approved_at)); line('🏁', 'Completed On', fmtDt(r.forwarded_at));
      L.push('', '— ICMR-NIHR Jodhpur · Hired Vehicle Management');
      return L.join('\n');
    }
    async function copyText(text) {
      try { await navigator.clipboard.writeText(text); return true; } catch (_) {
        const ta = document.createElement('textarea'); ta.value = text; ta.style.cssText = 'position:fixed;opacity:0';
        document.body.appendChild(ta); ta.select(); let ok = false;
        try { ok = document.execCommand('copy'); } catch (_) {} ta.remove(); return ok;
      }
    }

    /* ============================================================
       PROFESSIONAL PDF (print window) — tabular grid layout
       ============================================================ */
    const PRINT_CSS = `
      @page{size:A4;margin:12mm 12mm 14mm;@bottom-right{content:"Page " counter(page) " of " counter(pages);font-size:9px;color:#778}}
      *{box-sizing:border-box}
      body{font:11px/1.45 'Segoe UI',Arial,Helvetica,sans-serif;color:#1a2a36;margin:0;-webkit-print-color-adjust:exact;print-color-adjust:exact}
      .lh{display:flex;justify-content:space-between;align-items:flex-end;gap:12px;border-bottom:3px solid #0E7C86;padding-bottom:8px;margin-bottom:12px}
      .lh h1{font-size:17px;margin:0;color:#0B2239}.lh .sub{color:#5B6B7A;font-size:10px;margin-top:2px}
      .lh .meta{text-align:right;font-size:10px;color:#5B6B7A;white-space:nowrap}
      table{width:100%;border-collapse:collapse;margin:0}
      th,td{border:1px solid #cfd8de;padding:4px 7px;text-align:left;vertical-align:top;word-break:break-word}
      thead{display:table-header-group}tr{break-inside:avoid}
      thead th{background:#0B2239;color:#fff;font-size:9.5px;text-transform:uppercase;letter-spacing:.04em}
      tbody tr:nth-child(even) td{background:#F7FAFB}
      .kv th{width:16%;background:#EEF3F6;color:#34505f;font-size:9.5px;text-transform:uppercase;letter-spacing:.03em}
      .kv td{width:34%;background:#fff !important}
      .r{text-align:right;white-space:nowrap}.c{text-align:center}
      tfoot td{font-weight:700;background:#E5F3F3 !important;color:#0A5F67}
      .sumwrap{margin-bottom:16px}.sumwrap h4{margin-top:0}
      section.app{margin-bottom:18px}section.app + section.app{break-before:page}
      .bar{display:flex;justify-content:space-between;align-items:center;gap:8px;background:#E5F3F3;border:1px solid #cfe5e8;border-left:5px solid #0E7C86;padding:7px 10px;margin-bottom:6px;font-size:12.5px;font-weight:700;color:#0A5F67}
      .st{font-size:9.5px;text-transform:uppercase;letter-spacing:.05em;padding:2px 10px;border-radius:99px;border:1px solid currentColor;background:#fff}
      .st-pending{color:#B8650F}.st-approved{color:#1F8A5F}.st-rejected{color:#C4433B}.st-completed{color:#0E7C86}
      h4{margin:12px 0 5px;font-size:10.5px;text-transform:uppercase;letter-spacing:.06em;color:#0A5F67;border-bottom:1px solid #cfe5e8;padding-bottom:2px}
      .small{display:block;color:#5B6B7A;font-size:9.5px;margin-top:2px}
      .sign{display:flex;justify-content:space-between;gap:20px;margin-top:30px}
      .sign div{flex:1;border-top:1px solid #667;padding-top:3px;text-align:center;font-size:9.5px;color:#5B6B7A}
      .foot{margin-top:14px;font-size:9px;color:#8895a0;text-align:center}
      .noprint{margin:0 0 12px;text-align:right}.noprint button{padding:6px 16px;border:0;border-radius:6px;background:#0E7C86;color:#fff;font-weight:600;cursor:pointer}
      @media screen{body{padding:16px;max-width:900px;margin:0 auto}}
      @media print{.noprint{display:none}}`;
    const pv = x => esc(dash(x)).replace(/\n/g, '<br>');
    function kvTable(cells) {            // cells: [label, htmlValue, fullWidth?]
      let out = '', row = [];
      const flush = () => { if (!row.length) return; while (row.length < 2) row.push(['', '']); out += '<tr>' + row.map(c => `<th>${esc(c[0])}</th><td>${c[1]}</td>`).join('') + '</tr>'; row = []; };
      cells.forEach(c => { if (c[2]) { flush(); out += `<tr><th>${esc(c[0])}</th><td colspan="3">${c[1]}</td></tr>`; } else { row.push(c); if (row.length === 2) flush(); } });
      flush(); return `<table class="kv">${out}</table>`;
    }
    function printArticle(r) {
      const catRows = r.categories.map((c, n) => `<tr>
        <td class="c">${n + 1}</td>
        <td><b>${esc(c.category_name)}</b> <span class="small">#${c.id}</span>${c.vehicle_details ? `<span class="small">${pv(c.vehicle_details)}</span>` : ''}${c.parameters ? `<span class="small">${pv(c.parameters)}</span>` : ''}</td>
        <td>${pv(c.trip_type_label)}</td><td>${pv(c.location_label)}</td>
        <td class="r">${money(c.unit_price)}</td><td class="c">${c.qty}</td><td class="r">${money(c.line_total)}</td></tr>`).join('');
      const asgRows = r.categories.flatMap(c => asgFor(r, c).map(a => `<tr>
        <td>${esc(c.category_name)}</td><td class="c">${a.idx}</td><td>${pv(a.driver_name)}</td><td>${pv(a.driver_mobile)}</td><td>${pv(a.vehicle_number)}</td></tr>`)).join('');
      return `
      <section class="app">
        <div class="bar"><span>Application #${r.id} — ${esc(r.applicant_name)}</span><span class="st st-${esc(r.status)}">${esc(cap(r.status))}</span></div>
        <h4>1. Applicant &amp; Trip Details</h4>
        ${kvTable([
          ['Applicant', pv(r.applicant_name)], ['Designation', pv(r.applicant_designation)],
          ['Email', pv(r.applicant_email)], ['Mobile', pv(r.applicant_mobile)],
          ['Project', pv(r.project)], ['Trip Type', pv(r.trip_type_label)],
          ['From', pv(fmtDt(r.datetime_from))], ['To', pv(fmtDt(r.datetime_to))],
          ['Place Required', pv(r.place_required)], ['Place of Visit', pv(r.place_of_visit_label)],
          ['Purpose', pv(r.purpose), 1], ['Details', pv(r.details), 1],
          ['Co-Travelers (' + r.co_travelers.length + ')', pv(r.co_travelers.join(', ')), 1],
          ['Submitted', pv(fmtDt(r.created_at))], ['Processed On', pv(fmtDt(r.approved_at))],
          ['Completed On', pv(fmtDt(r.forwarded_at))], ['Vehicles', r.total_vehicles + ' (' + (r.assigned_vehicles || 0) + ' assigned)']
        ])}
        <h4>2. Vehicle Categories</h4>
        <table><thead><tr><th style="width:5%">#</th><th>Category / Details</th><th>Trip Type</th><th>Location</th><th class="r">Rate</th><th class="c">Qty</th><th class="r">Amount</th></tr></thead>
          <tbody>${catRows || '<tr><td colspan="7" class="c">—</td></tr>'}</tbody>
          <tfoot><tr><td colspan="5" class="r">Estimated total</td><td class="c">${r.total_vehicles}</td><td class="r">${money(r.total_price)}</td></tr></tfoot></table>
        <h4>3. Driver &amp; Vehicle Assignment</h4>
        <table><thead><tr><th>Category</th><th class="c" style="width:7%">Veh #</th><th>Driver Name</th><th>Driver Mobile</th><th>Vehicle Number</th></tr></thead>
          <tbody>${asgRows || '<tr><td colspan="5" class="c">—</td></tr>'}</tbody></table>
        <h4>4. Actual Trip Record</h4>
        ${kvTable([
          ['Actual Departure', pv(fmtDt(r.actual_departure))], ['Actual Arrival', pv(fmtDt(r.actual_arrival))],
          ['Out KM', pv(r.out_km)], ['In KM', pv(r.in_km)], ['Total KM', pv(kmText(r))], ['Tour Order', pv(r.tour_order_pdf)]
        ])}
        <h4>5. Remarks</h4>
        ${kvTable([['Super Admin', pv(r.super_admin_remark), 1], ['Forwarded Admin', pv(r.forwarded_remark), 1]])}
        <div class="sign"><div>Applicant</div><div>Super Admin</div><div>Forwarded Admin</div></div>
      </section>`;
    }
    function printSummary(recs) {
      return `<div class="sumwrap"><h4>Summary — ${recs.length} application${recs.length === 1 ? '' : 's'}</h4>
        <table><thead><tr><th>ID</th><th>Applicant</th><th>Project</th><th>Travel</th><th>Place of Visit</th><th class="c">Veh.</th><th class="r">Est. Cost</th><th class="r">KM</th><th>Status</th></tr></thead><tbody>
        ${recs.map(r => `<tr><td>#${r.id}</td><td>${esc(r.applicant_name)}</td><td>${pv(r.project)}</td><td>${esc(fmtDt(r.datetime_from) || '—')}${r.datetime_to ? '<span class="small">to ' + esc(fmtDt(r.datetime_to)) + '</span>' : ''}</td>
          <td>${pv(r.place_of_visit_label)}</td><td class="c">${r.total_vehicles}</td><td class="r">${money(r.total_price)}</td><td class="r">${esc(kmText(r) || '—')}</td><td>${esc(cap(r.status))}</td></tr>`).join('')}
        </tbody><tfoot><tr><td colspan="5" class="r">Total</td><td class="c">${recs.reduce((s, r) => s + r.total_vehicles, 0)}</td><td class="r">${money(recs.reduce((s, r) => s + r.total_price, 0))}</td><td class="r">${num(recs.reduce((s, r) => s + (r.total_km || 0), 0))}</td><td></td></tr></tfoot></table></div>`;
    }
    function openPrint(recs, title) {
      const w = window.open('', '_blank');
      if (!w) { showToast('Allow pop-ups to download the PDF', 'warn'); return; }
      w.document.write(`<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
        <title>${esc(title)}</title><style>${PRINT_CSS}</style></head><body>
        <div class="noprint"><button onclick="window.print()">Print / Save as PDF</button></div>
        <div class="lh"><div><h1>${esc(title)}</h1><div class="sub">Hired Vehicle Management — ICMR-NIHR Jodhpur</div></div>
          <div class="meta">Generated ${esc(fmtDt(new Date().toISOString()))}<br>${recs.length} application${recs.length === 1 ? '' : 's'}</div></div>
        ${recs.length > 1 ? printSummary(recs) : ''}
        ${recs.map(printArticle).join('')}
        <div class="foot">System generated report · ICMR-NIHR Jodhpur — Hired Vehicle Management</div>
        </body></html>`);
      w.document.close();
      let done = false; const go = () => { if (done) return; done = true; w.focus(); w.print(); };
      w.addEventListener('load', () => setTimeout(go, 300)); setTimeout(go, 1500);
    }

    /* ============================================================
       CSV EXPORTS
       ============================================================ */
    function csvCell(v) {
      let s = String(v ?? '');
      if (/^[=@]/.test(s) || /^[+\-][^0-9]/.test(s)) s = "'" + s;           // spreadsheet-formula guard
      return /[",\n\r]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
    }
    function downloadCsv(name, rows) {
      const blob = new Blob(['\uFEFF' + rows.map(r => r.map(csvCell).join(',')).join('\r\n')], { type: 'text/csv;charset=utf-8' });
      const a = document.createElement('a'); a.href = URL.createObjectURL(blob);
      a.download = `${name}_${new Date().toISOString().slice(0, 10)}.csv`;
      document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 1500);
    }
    const APP_HEAD = ['ID', 'Status', 'Applicant', 'Designation', 'Email', 'Mobile', 'Project', 'Trip Type', 'From', 'To', 'Place Required', 'Place of Visit', 'Purpose', 'Details', 'Co-Travelers', 'Categories (name × qty @ rate)', 'Total Vehicles', 'Assigned Vehicles', 'Estimated Cost (INR)', 'Drivers / Vehicles', 'Super Admin Remark', 'Forwarded Remark', 'Actual Departure', 'Actual Arrival', 'Out KM', 'In KM', 'Total KM', 'Tour Order', 'Submitted', 'Processed On', 'Completed On'];
    function appRow(r) {
      const drivers = [];
      r.categories.forEach(c => asgFor(r, c).forEach(a => { if (a.driver_name || a.vehicle_number || a.driver_mobile) drivers.push(`${c.category_name} #${a.idx}: ${a.driver_name} / ${a.driver_mobile} / ${a.vehicle_number}`); }));
      return [r.id, cap(r.status), r.applicant_name, r.applicant_designation, r.applicant_email, r.applicant_mobile, r.project, r.trip_type_label,
        fmtDt(r.datetime_from), fmtDt(r.datetime_to), r.place_required, r.place_of_visit_label, r.purpose, r.details, r.co_travelers.join('; '),
        r.categories.map(c => `${c.category_name} × ${c.qty} @ ${c.unit_price}`).join('; '), r.total_vehicles, r.assigned_vehicles || 0, r.total_price, drivers.join(' | '),
        r.super_admin_remark, r.forwarded_remark, fmtDt(r.actual_departure), fmtDt(r.actual_arrival), r.out_km, r.in_km, kmText(r).replace(/,/g, ''),
        tourUrl(r), fmtDt(r.created_at), fmtDt(r.approved_at), fmtDt(r.forwarded_at)];
    }
    function dispatchRows() {
      const rows = [['App ID', 'Status', 'Applicant', 'Mobile', 'Project', 'From', 'To', 'Place Required', 'Place of Visit', 'Category', 'Trip Type', 'Vehicle #', 'Driver', 'Driver Mobile', 'Vehicle Number']];
      filtered().filter(r => r.status !== 'rejected').forEach(r => r.categories.forEach(c => asgFor(r, c).forEach(a =>
        rows.push([r.id, cap(r.status), r.applicant_name, r.applicant_mobile, r.project, fmtDt(r.datetime_from), fmtDt(r.datetime_to), r.place_required, r.place_of_visit_label, c.category_name, c.trip_type_label, a.idx, a.driver_name, a.driver_mobile, a.vehicle_number]))));
      return rows;
    }
    /** Records of the active tab, after filters. */
    function viewRecords() {
      const pane = $('.tab-pane.active'), st = pane && pane.dataset.statuses ? pane.dataset.statuses.split(',') : null;
      return { recs: RECORDS.filter(r => matches(r) && (!st || st.includes(r.status))), label: (pane && pane.dataset.label) || 'All' };
    }

    /* ---------------- click delegation (shared actions) ---------------- */
    document.addEventListener('click', async e => {
      const t = e.target.closest('[data-act]'); if (!t) return;
      const act = t.dataset.act, card = t.closest('.app-card');
      switch (act) {
        case 'copy':  if (card) { const ok = await copyText(recordText(liveRecord(card))); showToast(ok ? 'Full details copied to clipboard' : 'Copy failed', ok ? '' : 'error'); } break;
        case 'print': if (card) { const r = liveRecord(card); openPrint([r], `Application #${r.id} — ${r.applicant_name}`); } break;
        case 'filter-reset': resetFilters(); break;
        case 'export-all-csv': downloadCsv('hired_all_applications', [APP_HEAD, ...RECORDS.map(appRow)]); break;
        case 'export-vehicles-csv': downloadCsv('hired_vehicles_dispatch', dispatchRows()); break;
        case 'export-view-csv': { const v = viewRecords(); v.recs.length ? downloadCsv('hired_' + v.label.toLowerCase(), [APP_HEAD, ...v.recs.map(appRow)]) : showToast('Nothing to export', 'warn'); break; }
        case 'export-view-pdf': { const v = viewRecords(); v.recs.length ? openPrint(v.recs, `Hired Vehicle Applications — ${v.label}`) : showToast('Nothing to export', 'warn'); break; }
      }
    });

    buildFilterOptions(); bindFilters(); applyFilters();

    return {
      RECORDS, BY_ID, util, showToast, post, liveRecord, asgFor, kmText, filtered, applyFilters, downloadCsv,
      onFilter: fn => listeners.push(fn),
      syncRecord(card) {
        const rec = liveRecord(card), i = RECORDS.findIndex(x => x.id === rec.id);
        BY_ID[rec.id] = rec; if (i > -1) RECORDS[i] = rec; delete hayCache[rec.id];
      }
    };
  }

  win.HiredDash = { init, util };
})(window);

</script>
<script>
(function () {
  'use strict';
  const D = HiredDash.init({ records: <?= json_encode(array_values($records), $jsonFlags) ?>, csrf: <?= json_encode(hired_csrf_token()) ?> });
  const { $$ } = D.util;

  // completion form guards
  $$('form[data-complete-form]').forEach(form => {
    form.addEventListener('submit', e => {
      const dep = form.elements['actual_departure'].value, arr = form.elements['actual_arrival'].value;
      const out = parseFloat(form.elements['out_km'].value), inn = parseFloat(form.elements['in_km'].value);
      if (dep && arr && new Date(arr) < new Date(dep)) { e.preventDefault(); D.showToast('Arrival cannot be before departure', 'error'); return; }
      if (!isNaN(out) && !isNaN(inn) && inn < out) { if (!window.confirm('In KM is less than Out KM. Submit anyway?')) e.preventDefault(); return; }
      if (!window.confirm('Mark this application as completed?')) e.preventDefault();
    });
  });
})();
</script>
</body>
</html>
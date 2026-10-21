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
/** MySQL DATETIME -> value for <input type="datetime-local"> ('' when empty) */
function hired_input_dt($v): string {
    if ($v === null || $v === '' || strpos((string)$v, '0000-00-00') === 0) return '';
    $t = strtotime((string)$v); return $t ? date('Y-m-d\TH:i', $t) : '';
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
        // AGENCY DETAILS
        'agency_id'     => $app['agency_id'] ?? '',
        'agency_name'   => $app['assigned_agency_name'] ?? '',
        'agency_mobile' => $app['assigned_agency_mobile'] ?? '',
        'agency_email'  => $app['assigned_agency_email'] ?? ''
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

/** Export toolbar (buttons are wired by the shared JS block) */
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

/** Filter bar (options are filled in by the shared JS block from the loaded records) */
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
function hired_render_pane(string $id, string $label, string $statuses, array $list, array $opts, string $emptyMsg, bool $active = false, string $note = '', array $driversList = [], array $vehiclesList = [], array $agenciesList = []): void { ?>
<div class="tab-pane fade<?= $active ? ' show active' : '' ?>" id="<?= h($id) ?>" role="tabpanel" data-statuses="<?= h($statuses) ?>" data-label="<?= h($label) ?>">
  <?php foreach ($list as $rec) hired_render_card($rec, $opts, $driversList, $vehiclesList, $agenciesList); ?>
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
function hired_render_card(array $r, array $opts = [], array $driversList = [], array $vehiclesList = [], array $agenciesList = []): void {
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
    
    <!-- AGENCY ASSIGNMENT OR DETAILS -->
    <?php if ($isEditable): ?>
      <div class="span-all" style="background:#f8fcfc; padding:10px; border-radius:8px; border:1px solid #d0e5e8;">
        <span class="dt" style="color:var(--teal-dark);"><i class="bi bi-building me-1"></i>Service Provider (Agency)</span>
        <div class="d-flex gap-2 align-items-center mt-1">
          <select class="form-select form-select-sm" style="max-width: 300px;" id="agency-select-<?= (int)$r['id'] ?>">
            <option value="">-- Select Agency --</option>
            <?php foreach ($agenciesList as $ag): ?>
              <option value="<?= (int)$ag['id'] ?>" <?= ((int)$r['agency_id'] === (int)$ag['id']) ? 'selected' : '' ?>>
                <?= h($ag['agency_name']) ?> (<?= h($ag['mobile']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <button type="button" class="btn btn-sm btn-outline-primary" onclick="saveAgency(<?= (int)$r['id'] ?>)">
            <i class="bi bi-check-lg"></i> Save
          </button>
        </div>
      </div>
    <?php elseif (!empty($r['agency_name'])): ?>
      <div><span class="dt">Service Provider</span><span class="dd"><?= h($r['agency_name']) ?> <br><small class="text-muted"><?= h($r['agency_mobile']) ?> | <?= h($r['agency_email']) ?></small></span></div>
    <?php endif; ?>

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
        <div class="d-flex gap-2">
          <a href="hired_manage_drivers_vehicles.php" class="btn btn-outline-secondary btn-sm" title="Manage full driver and vehicle lists">
            <i class="bi bi-gear-fill me-1"></i> Manage Master List
          </a>
          <button type="button" class="btn btn-save" data-act="save-all-assignments"><i class="bi bi-save me-1"></i>Save Driver &amp; Vehicle Details</button>
        </div>
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
              <td data-label="Driver">
                <select class="veh-input form-select form-select-sm" name="driver_select" <?= $locked ? 'disabled' : '' ?> onchange="syncDriverFields(this)">
                  <option value="">-- Select Driver --</option>
                  <?php foreach ($driversList as $d): ?>
                    <option value="<?= h($d['id']) ?>" data-name="<?= h($d['driver_name']) ?>" data-mob="<?= h($d['driver_mob']) ?>" <?= (($row['driver_name'] ?? '') === $d['driver_name']) ? 'selected' : '' ?>>
                      <?= h($d['driver_name']) ?> (<?= h($d['driver_mob']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
                <input type="hidden" name="driver_name" value="<?= h($row['driver_name'] ?? '') ?>">
              </td>
              <td data-label="Mobile">
                <input class="veh-input" name="driver_mobile" maxlength="10" value="<?= h($row['driver_mobile'] ?? '') ?>" <?= $locked ? 'readonly' : '' ?> readonly>
              </td>
              <td data-label="Vehicle No.">
                <select class="veh-input form-select form-select-sm" name="vehicle_select" <?= $locked ? 'disabled' : '' ?> onchange="syncVehicleFields(this)">
                  <option value="">-- Select Vehicle --</option>
                  <?php foreach ($vehiclesList as $v): ?>
                    <option value="<?= h($v['id']) ?>" data-no="<?= h($v['vehicle_no']) ?>" <?= (($row['vehicle_number'] ?? '') === $v['vehicle_no']) ? 'selected' : '' ?>>
                      <?= h($v['vehicle_no']) ?> <?= $v['vehicle_name'] ? '- ' . h($v['vehicle_name']) : '' ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <input type="hidden" name="vehicle_number" value="<?= h($row['vehicle_number'] ?? '') ?>">
              </td>
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

  <?php if ($hasActual || !empty($opts['trip_edit'])): ?>
  <div class="card-section">
    <div class="section-head">
      <h3 class="section-title"><i class="bi bi-speedometer2"></i>Actual Trip Record</h3>
      <?php if (!empty($opts['trip_edit'])): ?>
      <button type="button" class="btn-action no-print" data-act="edit-trip"
        data-app="<?= (int)$r['id'] ?>" data-dep="<?= h(hired_input_dt($r['actual_departure'])) ?>" data-arr="<?= h(hired_input_dt($r['actual_arrival'])) ?>"
        data-out="<?= h($r['out_km']) ?>" data-in="<?= h($r['in_km']) ?>"><i class="bi bi-pencil-square"></i> Edit trip record</button>
      <?php endif; ?>
    </div>
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

// ================= AUTH GUARD =================
if (!isset($_SESSION['hired_user_id']) || ($_SESSION['hired_role'] ?? '') !== 'hired_super_admin') {
    header("Location: hired_login.php"); exit;
}
$adminId   = (int)$_SESSION['hired_user_id'];
$adminName = $_SESSION['hired_user_name'] ?? 'Super Admin';

/** datetime-local -> MySQL DATETIME (null when empty/invalid) */
function hired_parse_local_dt(string $v): ?string {
    $v = trim(str_replace('T', ' ', $v));
    if ($v === '') return null;
    $d = DateTime::createFromFormat('Y-m-d H:i', $v) ?: DateTime::createFromFormat('Y-m-d H:i:s', $v);
    return $d ? $d->format('Y-m-d H:i:s') : null;
}

/** Fetch an application's status (null when it does not exist). */
function hired_app_status(mysqli $conn, int $appId): ?string {
    $chk = $conn->prepare("SELECT status FROM hired_applications WHERE id = ?");
    $chk->bind_param("i", $appId); $chk->execute();
    $row = $chk->get_result()->fetch_assoc(); $chk->close();
    return $row ? (string)$row['status'] : null;
}

/* ============================================================
   =============== AJAX ENDPOINTS =============================
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    ini_set('display_errors', '0'); ob_start();
    set_exception_handler(function (Throwable $e) { hired_json(['ok' => false, 'msg' => 'Server error: ' . $e->getMessage()]); });
    header('Content-Type: application/json');
    if (!hired_csrf_ok()) hired_json(['ok' => false, 'msg' => 'Session token expired. Please reload the page.']);
    $ajax = $_POST['ajax'];

    // 0. SAVE AGENCY ASSIGNMENT
    if ($ajax === 'save_agency') {
        $appId = (int)($_POST['application_id'] ?? 0);
        $agencyId = (int)($_POST['agency_id'] ?? 0);
        if ($appId <= 0) hired_json(['ok' => false, 'msg' => 'Invalid application.']);
        
        $stmt = $conn->prepare("UPDATE hired_applications SET agency_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $agencyId, $appId);
        $ok = $stmt->execute(); $stmt->close();
        hired_json(['ok' => $ok, 'msg' => $ok ? 'Agency assigned successfully.' : 'Failed to assign agency.']);
    }

    // 1. SAVE ONE VEHICLE ASSIGNMENT
    if ($ajax === 'save_vehicle_assignment') {
        $appId = (int)($_POST['application_id'] ?? 0);
        $catId = (int)($_POST['category_id'] ?? 0);
        $index = (int)($_POST['vehicle_index'] ?? 0);
        $dName = mb_substr(trim($_POST['driver_name'] ?? ''), 0, 150);
        $dMob  = mb_substr(trim($_POST['driver_mobile'] ?? ''), 0, 15);
        $vNum  = mb_substr(trim($_POST['vehicle_number'] ?? ''), 0, 30);

        if ($appId <= 0 || $catId <= 0 || $index < 1) hired_json(['ok' => false, 'msg' => 'Invalid identifiers.']);
        if ($dMob !== '' && !preg_match('/^\d{10}$/', $dMob)) hired_json(['ok' => false, 'msg' => 'Driver mobile must be exactly 10 digits.']);
        $st = hired_app_status($conn, $appId);
        if ($st === null)      hired_json(['ok' => false, 'msg' => 'Application not found.']);
        if ($st !== 'pending') hired_json(['ok' => false, 'msg' => 'Application is not pending.']);

        $stmt = $conn->prepare("INSERT INTO hired_application_vehicles
              (application_id, category_id, vehicle_index, driver_name, driver_mobile, vehicle_number, updated_by)
            VALUES (?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
              driver_name = VALUES(driver_name), driver_mobile = VALUES(driver_mobile),
              vehicle_number = VALUES(vehicle_number), updated_by = VALUES(updated_by), deleted_at = NULL");
        $stmt->bind_param("iiisssi", $appId, $catId, $index, $dName, $dMob, $vNum, $adminId);
        $ok = $stmt->execute(); $stmt->close();
        hired_json(['ok' => $ok, 'msg' => $ok ? 'Saved.' : 'Save failed.', 'updated_at' => date('d/m/Y H:i')]);
    }

    // 2. UPDATE QUANTITY
    if ($ajax === 'update_quantity') {
        $appId = (int)($_POST['application_id'] ?? 0);
        $catId = (int)($_POST['category_id'] ?? 0);
        $qty   = (int)($_POST['qty'] ?? 0);
        if ($appId <= 0 || $catId <= 0 || $qty < 0) hired_json(['ok' => false, 'msg' => 'Invalid data.']);

        $chk = $conn->prepare("SELECT selected_categories, status FROM hired_applications WHERE id = ?");
        $chk->bind_param("i", $appId); $chk->execute();
        $row = $chk->get_result()->fetch_assoc(); $chk->close();
        if (!$row)                        hired_json(['ok' => false, 'msg' => 'Application not found.']);
        if ($row['status'] !== 'pending') hired_json(['ok' => false, 'msg' => 'Application is not pending.']);

        $sel = json_decode($row['selected_categories'] ?? '[]', true) ?: [];
        $found = false;
        foreach ($sel as &$s) {
            if ((int)($s['id'] ?? 0) === $catId) { $s['qty'] = $qty; $found = true; break; }
        }
        unset($s);
        if (!$found) hired_json(['ok' => false, 'msg' => 'Category not found.']);

        $newJson = json_encode($sel);
        $upd = $conn->prepare("UPDATE hired_applications SET selected_categories = ? WHERE id = ?");
        $upd->bind_param("si", $newJson, $appId);
        $ok = $upd->execute(); $upd->close();
        hired_json(['ok' => $ok, 'msg' => $ok ? 'Quantity updated.' : 'Update failed.']);
    }

    // 3. REMOVE A VEHICLE ROW (rows below shift up so indexes stay 1..qty)
    if ($ajax === 'delete_vehicle_row') {
        $appId = (int)($_POST['application_id'] ?? 0);
        $catId = (int)($_POST['category_id'] ?? 0);
        $index = (int)($_POST['vehicle_index'] ?? 0);
        if ($appId <= 0 || $catId <= 0 || $index < 1) hired_json(['ok' => false, 'msg' => 'Invalid data.']);
        if (hired_app_status($conn, $appId) !== 'pending') hired_json(['ok' => false, 'msg' => 'Not pending.']);

        $conn->begin_transaction();
        try {
            $q = $conn->prepare("DELETE FROM hired_application_vehicles WHERE application_id=? AND category_id=? AND (vehicle_index=? OR deleted_at IS NOT NULL)");
            $q->bind_param("iii", $appId, $catId, $index); $q->execute(); $q->close();
            $q = $conn->prepare("UPDATE hired_application_vehicles SET vehicle_index = vehicle_index - 1
                                 WHERE application_id=? AND category_id=? AND vehicle_index > ? ORDER BY vehicle_index ASC");
            $q->bind_param("iii", $appId, $catId, $index); $q->execute(); $q->close();
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            hired_json(['ok' => false, 'msg' => 'Could not remove the row.']);
        }
        hired_json(['ok' => true, 'msg' => 'Row removed.']);
    }

    // 4. SAVE ALL ASSIGNMENTS OF A CARD (batch)
    if ($ajax === 'save_all_assignments') {
        $appId = (int)($_POST['application_id'] ?? 0);
        $rows  = json_decode($_POST['rows'] ?? '[]', true);
        if ($appId <= 0 || !is_array($rows)) hired_json(['ok' => false, 'msg' => 'Invalid data.']);
        if (hired_app_status($conn, $appId) !== 'pending') hired_json(['ok' => false, 'msg' => 'Not pending.']);

        $clean = [];
        foreach ($rows as $r) {
            $catId = (int)($r['cat_id'] ?? 0);
            $idx   = (int)($r['idx'] ?? 0);
            $dName = mb_substr(trim($r['driver_name'] ?? ''), 0, 150);
            $dMob  = mb_substr(trim($r['driver_mobile'] ?? ''), 0, 15);
            $vNum  = mb_substr(trim($r['vehicle_number'] ?? ''), 0, 30);
            if ($catId <= 0 || $idx <= 0) continue;
            if ($dMob !== '' && !preg_match('/^\d{10}$/', $dMob)) hired_json(['ok' => false, 'msg' => "Row #{$idx}: driver mobile must be exactly 10 digits."]);
            $clean[] = [$catId, $idx, $dName, $dMob, $vNum];
        }

        $stmt = $conn->prepare("INSERT INTO hired_application_vehicles
              (application_id, category_id, vehicle_index, driver_name, driver_mobile, vehicle_number, updated_by)
            VALUES (?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
              driver_name = VALUES(driver_name), driver_mobile = VALUES(driver_mobile),
              vehicle_number = VALUES(vehicle_number), updated_by = VALUES(updated_by), deleted_at = NULL");
        $ok = true;
        foreach ($clean as [$catId, $idx, $dName, $dMob, $vNum]) {
            $stmt->bind_param("iiisssi", $appId, $catId, $idx, $dName, $dMob, $vNum, $adminId);
            if (!$stmt->execute()) $ok = false;
        }
        $stmt->close();
        hired_json(['ok' => $ok, 'msg' => $ok ? 'All assignments saved successfully.' : 'Some rows could not be saved.', 'updated_at' => date('d/m/Y H:i')]);
    }

    // 5. UPDATE CATEGORY MASTER
    if ($ajax === 'update_category') {
        $catId = (int)($_POST['category_id'] ?? 0);
        $name  = mb_substr(trim($_POST['category_name'] ?? ''), 0, 150);
        $det   = trim($_POST['vehicle_details'] ?? '');
        $par   = trim($_POST['parameters'] ?? '');
        $price = (float)($_POST['price_per_vehicle'] ?? 0);
        if ($catId <= 0 || $name === '' || $price < 0) hired_json(['ok' => false, 'msg' => 'Invalid data.']);

        $upd = $conn->prepare("UPDATE hired_vehicles SET category_name=?, vehicle_details=?, parameters=?, price_per_vehicle=? WHERE id=?");
        $upd->bind_param("sssdi", $name, $det, $par, $price, $catId);
        $ok = $upd->execute(); $upd->close();
        hired_json(['ok' => $ok, 'msg' => $ok ? 'Category updated.' : 'Update failed.']);
    }

    // 6. UPDATE ACTUAL TRIP RECORD (super admin may correct what the forwarded admin filled in)
    if ($ajax === 'update_trip_record') {
        $appId = (int)($_POST['application_id'] ?? 0);
        $depRaw = trim($_POST['actual_departure'] ?? ''); $arrRaw = trim($_POST['actual_arrival'] ?? '');
        $dep = hired_parse_local_dt($depRaw); $arr = hired_parse_local_dt($arrRaw);
        $outRaw = trim($_POST['out_km'] ?? ''); $inRaw = trim($_POST['in_km'] ?? '');
        if ($appId <= 0) hired_json(['ok' => false, 'msg' => 'Invalid application.']);
        if (($depRaw !== '' && $dep === null) || ($arrRaw !== '' && $arr === null)) hired_json(['ok' => false, 'msg' => 'Invalid date/time.']);
        if ($dep !== null && $arr !== null && strtotime($arr) < strtotime($dep)) hired_json(['ok' => false, 'msg' => 'Actual Arrival cannot be earlier than Actual Departure.']);
        foreach ([$outRaw, $inRaw] as $k) { if ($k !== '' && (!is_numeric($k) || (float)$k < 0)) hired_json(['ok' => false, 'msg' => 'KM values must be valid positive numbers.']); }
        $outKm = $outRaw === '' ? null : (float)$outRaw;
        $inKm  = $inRaw  === '' ? null : (float)$inRaw;

        $st = hired_app_status($conn, $appId);
        if ($st === null) hired_json(['ok' => false, 'msg' => 'Application not found.']);
        if (!in_array($st, ['approved', 'completed'], true)) hired_json(['ok' => false, 'msg' => 'Trip record can be edited only for approved or completed applications.']);
        if ($st === 'completed' && ($dep === null || $arr === null)) hired_json(['ok' => false, 'msg' => 'Departure and arrival are required for a completed application.']);

        $upd = $conn->prepare("UPDATE hired_applications SET actual_departure = ?, actual_arrival = ?, out_km = ?, in_km = ? WHERE id = ?");
        $upd->bind_param("ssddi", $dep, $arr, $outKm, $inKm, $appId);
        $ok = $upd->execute(); $upd->close();
        hired_json(['ok' => $ok, 'msg' => $ok ? 'Actual trip record updated.' : 'Update failed.']);
    }

    hired_json(['ok' => false, 'msg' => 'Unknown action.']);
}

// ================= HANDLE ACTIONS (approve / reject) =================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!hired_csrf_ok()) { hired_flash_set('danger', 'Session token expired.'); }
    elseif (in_array($action, ['approve', 'reject'], true)) {
        $appId  = (int)($_POST['application_id'] ?? 0);
        $remark = mb_substr(trim($_POST['remark'] ?? ''), 0, 500);
        if ($appId > 0) {
            $newStatus = $action === 'approve' ? 'approved' : 'rejected';
            $upd = $conn->prepare("UPDATE hired_applications SET status=?, super_admin_remark=?, approved_by=?, approved_at=NOW() WHERE id=? AND status='pending'");
            $uid = (int)$_SESSION['hired_user_id'];
            $upd->bind_param("ssii", $newStatus, $remark, $uid, $appId);
            $upd->execute();
            if ($upd->affected_rows > 0) hired_flash_set('success', "Application #{$appId} has been {$newStatus}.");
            else hired_flash_set('warning', "Application #{$appId} could not be updated.");
            $upd->close();
        }
    }
    header("Location: hired_super_admin_dashboard.php"); exit;
}

$flash = hired_flash_get();

// ================= FETCH DATA (single pass) =================
$catMap       = hired_load_cat_map($conn);
$projectMap   = hired_load_project_map($conn);
$assignByApp  = hired_load_assignments($conn);

// FETCH DRIVERS & VEHICLES FOR DROPDOWNS
$driversList = [];
$dRes = $conn->query("SELECT id, driver_name, driver_mob FROM hired_drivers WHERE status = 'Active' ORDER BY driver_name ASC");
if ($dRes) { while ($r = $dRes->fetch_assoc()) $driversList[] = $r; }

$vehiclesList = [];
$vRes = $conn->query("SELECT id, vehicle_no, vehicle_name FROM hired_vehicles_detail WHERE status = 'Active' ORDER BY vehicle_no ASC");
if ($vRes) { while ($r = $vRes->fetch_assoc()) $vehiclesList[] = $r; }

// FETCH AGENCIES FOR DROPDOWN
$agenciesList = [];
$aRes = $conn->query("SELECT id, agency_name, mobile FROM hired_agencies WHERE status = 'Active' ORDER BY agency_name ASC");
if ($aRes) { while ($r = $aRes->fetch_assoc()) $agenciesList[] = $r; }

$records = []; $loadError = '';
try {
    // Check if agency_id exists to prevent fatal errors on older databases
    $hasAgencyId = false;
    $colRes = $conn->query("SHOW COLUMNS FROM `hired_applications` LIKE 'agency_id'");
    if ($colRes && $colRes->num_rows > 0) $hasAgencyId = true;

    if ($hasAgencyId) {
        // JOIN the agencies table to get the service provider details
        $appRes = $conn->query("SELECT a.*, ag.agency_name AS assigned_agency_name, ag.mobile AS assigned_agency_mobile, ag.email AS assigned_agency_email 
                                FROM hired_applications a 
                                LEFT JOIN hired_agencies ag ON a.agency_id = ag.id 
                                ORDER BY a.created_at DESC");
    } else {
        // Fallback if column is missing
        $appRes = $conn->query("SELECT a.*, NULL AS assigned_agency_name, NULL AS assigned_agency_mobile, NULL AS assigned_agency_email 
                                FROM hired_applications a 
                                ORDER BY a.created_at DESC");
    }

    if ($appRes) {
        while ($a = $appRes->fetch_assoc()) {
            $id = (int)$a['id'];
            $records[$id] = buildAppRecord($a, $catMap, $projectMap, $assignByApp[$id] ?? []);
        }
    } else {
        $loadError = 'Could not load applications: ' . $conn->error;
    }
} catch (Throwable $e) {
    $loadError = 'Could not load applications: ' . $e->getMessage();
}

$stats = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'completed' => 0];
foreach ($records as $r) { if (isset($stats[$r['status']])) $stats[$r['status']]++; }

const HIRED_CARD_LIMIT = 300;   // max cards rendered per tab (exports/analytics still use ALL records)
$byStatus = fn(string $s) => array_values(array_filter($records, fn($r) => $r['status'] === $s));

$pendingList = $byStatus('pending');
usort($pendingList, fn($a, $b) => strcmp($a['created_at'], $b['created_at']));                                        // oldest first

$approvedList = $byStatus('approved');
usort($approvedList, fn($a, $b) => strcmp($b['approved_at'] ?: $b['created_at'], $a['approved_at'] ?: $a['created_at']));

$completedList = $byStatus('completed');
usort($completedList, fn($a, $b) => strcmp($b['forwarded_at'] ?: $b['created_at'], $a['forwarded_at'] ?: $a['created_at']));

$rejectedList = $byStatus('rejected');
usort($rejectedList, fn($a, $b) => strcmp($b['approved_at'] ?: $b['created_at'], $a['approved_at'] ?: $a['created_at']));

$note = fn(array $all) => count($all) > HIRED_CARD_LIMIT ? 'Showing the latest ' . HIRED_CARD_LIMIT . ' of ' . count($all) . ' applications. Use the filters or "Current tab" export to get all of them.' : '';
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
           | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Super Admin Dashboard — Hired Vehicles</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
<link href="admin-hired.css?v=<?= (int)@filemtime(__DIR__ . '/admin-hired.css') ?>" rel="stylesheet" data-common-css>
</head>
<body>

<?php hired_render_header('Super Admin Dashboard', 'speedometer2', $adminName, [
    ['hired_manage_applicants.php',          'people-fill',  'Applicants'],
    ['hired_manage_vehicle_categories.php',  'truck-front',  'Categories'],
    ['hired_manage_projects.php',            'diagram-3',    'Projects'],
    ['hired_manage_drivers_vehicles.php',    'gear-fill',    'Drivers & Vehicles'],
    ['hired_manage_agencies.php',            'building',     'Agencies'],
]); ?>

<main class="container dash-body">

  <div class="row g-3 stats-row mb-4">
    <div class="col-6 col-lg-3"><?php hired_render_stat($stats['pending'],   'Pending',               'hourglass-split', 'pending'); ?></div>
    <div class="col-6 col-lg-3"><?php hired_render_stat($stats['approved'],  'Processed (Approved)',  'check-circle',    'approved'); ?></div>
    <div class="col-6 col-lg-3"><?php hired_render_stat($stats['completed'], 'Completed',             'flag'); ?></div>
    <div class="col-6 col-lg-3"><?php hired_render_stat($stats['rejected'],  'Rejected',              'x-circle',        'rejected'); ?></div>
  </div>

  <?php hired_render_flash($flash); ?>
  <?php if ($loadError !== ''): hired_render_flash(['type' => 'danger', 'msg' => $loadError]); endif; ?>

  <?php hired_render_filters(); ?>
  <?php hired_render_toolbar(); ?>

  <ul class="nav nav-tabs mb-3" id="dashTabs" role="tablist">
    <?php hired_render_tab('tab-pending',   'hourglass-split', 'Pending',   'pending',   $stats['pending'],   true); ?>
    <?php hired_render_tab('tab-processed', 'check-circle',    'Processed', 'approved',  $stats['approved']); ?>
    <?php hired_render_tab('tab-completed', 'flag',            'Completed', 'completed', $stats['completed']); ?>
    <?php hired_render_tab('tab-rejected',  'x-circle',        'Rejected',  'rejected',  $stats['rejected']); ?>
    <?php hired_render_tab('tab-analytics', 'table',           'Analytics', null,        0, false, 'analyticsTabBtn'); ?>
  </ul>

  <div class="tab-content">
    <?php hired_render_pane('tab-pending',   'Pending',   'pending',   array_slice($pendingList,   0, HIRED_CARD_LIMIT), ['form' => 'approve'], 'No pending applications.', true, $note($pendingList), $driversList, $vehiclesList, $agenciesList); ?>
    <?php hired_render_pane('tab-processed', 'Processed', 'approved',  array_slice($approvedList,  0, HIRED_CARD_LIMIT), ['trip_edit' => true], 'No processed (approved) applications.', false, $note($approvedList), $driversList, $vehiclesList, $agenciesList); ?>
    <?php hired_render_pane('tab-completed', 'Completed', 'completed', array_slice($completedList, 0, HIRED_CARD_LIMIT), ['trip_edit' => true], 'No completed applications yet.', false, $note($completedList), $driversList, $vehiclesList, $agenciesList); ?>
    <?php hired_render_pane('tab-rejected',  'Rejected',  'rejected',  array_slice($rejectedList,  0, HIRED_CARD_LIMIT), [], 'No rejected applications.', false, $note($rejectedList), $driversList, $vehiclesList, $agenciesList); ?>

    <!-- ===================== ANALYTICS (tabular) ===================== -->
    <div class="tab-pane fade" id="tab-analytics" role="tabpanel" data-label="All">
      <div class="kpi-grid" id="kpiGrid"></div>

      <div class="chart-card an-card">
        <div class="an-head">
          <div>
            <h3><i class="bi bi-table me-1"></i>Performance report</h3>
            <div class="chart-sub" id="anSub"></div>
          </div>
          <div class="an-tools">
            <select id="anLimit" class="form-select form-select-sm" aria-label="Rows to show">
              <option value="0">All rows</option><option value="10">Top 10</option><option value="20">Top 20</option><option value="50">Top 50</option>
            </select>
            <button type="button" class="btn-action" data-act="an-csv"><i class="bi bi-filetype-csv"></i> Export table</button>
          </div>
        </div>
        <div class="pill-nav" id="anPills" role="tablist" aria-label="Group by"></div>
        <div class="an-table-wrap"><table class="an-table" id="anTable"></table></div>
      </div>
    </div>
  </div>
</main>

<!-- Category edit modal -->
<div class="modal fade" id="catEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-sm-down">
    <form class="modal-content" id="catEditForm">
      <input type="hidden" name="category_id" id="ceId">
      <div class="modal-header">
        <h5 class="modal-title">Edit Category <span id="ceTitleId" class="text-muted"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body row g-3">
        <div class="col-md-6"><label class="form-label">Category Name</label><input class="form-control" name="category_name" id="ceName" maxlength="150" required></div>
        <div class="col-md-6"><label class="form-label">Price per Vehicle (₹)</label><input type="number" step="0.01" min="0" class="form-control" name="price_per_vehicle" id="cePrice" required></div>
        <div class="col-12"><label class="form-label">Vehicle Details</label><textarea class="form-control" name="vehicle_details" id="ceDetails" rows="3"></textarea></div>
        <div class="col-12"><label class="form-label">Parameters</label><textarea class="form-control" name="parameters" id="ceParams" rows="3"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-teal"><i class="bi bi-save me-1"></i>Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Actual trip record edit modal -->
<div class="modal fade" id="tripEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-sm-down">
    <form class="modal-content" id="tripEditForm">
      <input type="hidden" name="application_id" id="teId">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-speedometer2 me-1 text-muted"></i>Edit Actual Trip Record <span id="teTitleId" class="text-muted"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body row g-3">
        <div class="col-md-6"><label class="form-label" for="teDep">Actual Departure</label><input type="datetime-local" class="form-control" name="actual_departure" id="teDep"></div>
        <div class="col-md-6"><label class="form-label" for="teArr">Actual Arrival</label><input type="datetime-local" class="form-control" name="actual_arrival" id="teArr"></div>
        <div class="col-md-6"><label class="form-label" for="teOut">Out KM</label><input type="number" step="0.01" min="0" inputmode="decimal" class="form-control" name="out_km" id="teOut" placeholder="0"></div>
        <div class="col-md-6"><label class="form-label" for="teIn">In KM</label><input type="number" step="0.01" min="0" inputmode="decimal" class="form-control" name="in_km" id="teIn" placeholder="0"></div>
        <div class="col-12"><div class="form-text">Departure and arrival are mandatory for completed applications. For approved ones, leave a field blank to clear it. The application status does not change.</div></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-teal"><i class="bi bi-save me-1"></i>Save Trip Record</button>
      </div>
    </form>
  </div>
</div>

<footer class="dash-footer container">ICMR-NIHR Jodhpur &middot; <b>Hired Vehicle Management</b> &middot; &copy; <?= date('Y') ?></footer>

<?php hired_render_toast(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* =====================================================================
   Shared client logic for the Hired Vehicle dashboards (identical block
   in both dashboard files).
   Provides: filters + search, tab counts, compact "Copy details"
   (WhatsApp style), compact print/PDF, CSV exports.
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
  const moneyS = v => money(v).replace(/\.00$/, '');
  const num = v => Number(v || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
  const hasVal = v => v !== '' && v !== null && v !== undefined;
  const filled = v => hasVal(v) && String(v).trim() !== '';
  const flat = s => String(s || '').replace(/\s*\n\s*/g, '; ');
  const pad = n => String(n).padStart(2, '0');
  const dash = v => filled(v) ? String(v) : '—';
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
        r.place_required, r.place_of_visit_label, r.trip_type_label, r.co_travelers.join(' '), r.categories.map(c => c.category_name).join(' '), drv, r.agency_name].join(' ').toLowerCase();
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
      const uniq = [...new Set(values.filter(filled))].sort((a, b) => String(a).localeCompare(String(b)));
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
       COPY DETAILS — compact, WhatsApp-friendly, empty fields skipped
       ============================================================ */
    const STATUS_ICON = { pending: '⏳', approved: '✅', rejected: '❌', completed: '🏁' };
    const NUM_ICON = ['0️⃣', '1️⃣', '2️⃣', '3️⃣', '4️⃣', '5️⃣', '6️⃣', '7️⃣', '8️⃣', '9️⃣'];
    const numIcon = n => String(n).split('').map(d => NUM_ICON[+d]).join('');
    function recordText(r) {
      const L = [], add = (ico, val, label) => { if (filled(val)) L.push(label ? `${ico} ${label}: ${val}` : `${ico} ${val}`); };
      L.push(`🚐 *Hired Vehicle #${r.id}*  ${STATUS_ICON[r.status] || '📄'} *${cap(r.status)}*`, '┄┄┄┄┄┄┄┄┄┄┄┄┄┄');
      add('👤', [r.applicant_name, r.applicant_designation].filter(filled).join(' — '));
      add('📱', [r.applicant_mobile, r.applicant_email].filter(filled).join(' · '));
      add('🗂️', r.project);
      
      // AGENCY DETAILS
      if (filled(r.agency_name)) {
          add('🏢', `Service Provider: ${r.agency_name} (${r.agency_mobile} · ${r.agency_email})`);
      }

      const when = [fmtDt(r.datetime_from), fmtDt(r.datetime_to)].filter(Boolean).join(' → ');
      if (when) add('🗓️', when + (r.trip_type_label ? ` (${r.trip_type_label})` : ''));
      else add('🔁', r.trip_type_label);
      if (filled(r.place_required) || filled(r.place_of_visit_label)) add('📍', `${r.place_required || '—'} ➜ ${r.place_of_visit_label || '—'}`);
      add('🎯', r.purpose); add('📝', flat(r.details));
      if (r.co_travelers.length) add('👥', r.co_travelers.join(', '), `Co-travelers (${r.co_travelers.length})`);

      L.push('', `🚗 *Vehicles* — ${r.total_vehicles} requested · ${r.assigned_vehicles || 0} assigned`);
      if (!r.categories.length) L.push('—');
      r.categories.forEach((c, n) => {
        L.push(`${numIcon(n + 1)} *${c.category_name}*  ${c.qty} × ${moneyS(c.unit_price)} = *${moneyS(c.line_total)}*`);
        const meta = [c.trip_type_label, c.location_label, flat(c.vehicle_details), flat(c.parameters)].filter(filled).join(' · ');
        if (meta) L.push(`   ↳ ${meta}`);
        asgFor(r, c).forEach(a => L.push(
          (a.driver_name || a.driver_mobile || a.vehicle_number)
            ? `   🚘 ${a.idx} · ${dash(a.driver_name)} · 📞 ${dash(a.driver_mobile)} · 🔢 ${dash(a.vehicle_number)}`
            : `   🚘 ${a.idx} · _not assigned_`));
      });
      L.push(`💵 *Estimated total: ${moneyS(r.total_price)}*`);

      if (filled(r.super_admin_remark) || filled(r.forwarded_remark)) {
        L.push(''); add('🛡️', r.super_admin_remark, 'Super Admin'); add('📤', r.forwarded_remark, 'Forwarded');
      }
      const trip = [fmtDt(r.actual_departure), fmtDt(r.actual_arrival)].filter(Boolean).join(' → ');
      if (trip || filled(r.out_km) || filled(r.in_km)) {
        L.push('', '🏁 *Actual trip*'); add('🕒', trip);
        if (filled(r.out_km) || filled(r.in_km)) add('🛣️', `Out ${dash(r.out_km)} · In ${dash(r.in_km)}${kmText(r) ? ` · *${kmText(r)} km*` : ''}`);
      }
      add('📎', tourUrl(r), 'Tour order');
      const tl = [['Submitted', r.created_at], ['Processed', r.approved_at], ['Completed', r.forwarded_at]].filter(x => fmtDt(x[1])).map(x => `${x[0]} ${fmtDt(x[1])}`).join(' · ');
      if (tl) L.push('', `🕒 ${tl}`);
      L.push('', '— ICMR-NIHR Jodhpur · Hired Vehicle Mgmt');
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
       COMPACT PDF (print window) — tabular grid layout
       ============================================================ */
    const PRINT_CSS = `
      @page{size:A4;margin:10mm 10mm 12mm;@bottom-right{content:"Page " counter(page) " of " counter(pages);font-size:8px;color:#778}}
      *{box-sizing:border-box}
      body{font:9.5px/1.35 'Segoe UI',Arial,sans-serif;color:#1a2a36;margin:0;-webkit-print-color-adjust:exact;print-color-adjust:exact}
      .lh{display:flex;justify-content:space-between;align-items:flex-end;gap:10px;border-bottom:2.5px solid #0E7C86;padding-bottom:6px;margin-bottom:8px}
      .lh h1{font-size:15px;margin:0;color:#0B2239}.lh .sub{color:#5B6B7A;font-size:9px;margin-top:1px}
      .lh .meta{text-align:right;font-size:9px;color:#5B6B7A;white-space:nowrap}
      table{width:100%;border-collapse:collapse;margin:0}
      th,td{border:1px solid #d3dce2;padding:3px 6px;text-align:left;vertical-align:top;word-break:break-word}
      thead{display:table-header-group}tr{break-inside:avoid}
      thead th{background:#0B2239;color:#fff;font-size:8.5px;text-transform:uppercase;letter-spacing:.04em}
      .kv th{width:14%;background:#EEF3F6;color:#34505f;font-size:8.5px;text-transform:uppercase;letter-spacing:.03em}
      .kv td{width:36%}
      .r{text-align:right;white-space:nowrap}.c{text-align:center}
      tr.cat td{background:#E9F4F5;font-weight:600}
      tr.asg td{background:#fff;color:#34505f;font-size:9px}
      tfoot td{font-weight:700;background:#0E7C86;color:#fff;border-color:#0E7C86}
      .sumwrap{margin-bottom:12px}.sumwrap h4{margin-top:0}
      section.app{margin-bottom:12px;break-inside:avoid}
      .bar{display:flex;justify-content:space-between;align-items:center;gap:8px;background:#E5F3F3;border-left:4px solid #0E7C86;padding:5px 9px;margin-bottom:4px;font-size:11.5px;font-weight:700;color:#0A5F67}
      .st{font-size:8.5px;text-transform:uppercase;letter-spacing:.05em;padding:1px 9px;border-radius:99px;border:1px solid currentColor;background:#fff}
      .st-pending{color:#B8650F}.st-approved{color:#1F8A5F}.st-rejected{color:#C4433B}.st-completed{color:#0E7C86}
      h4{margin:8px 0 3px;font-size:9px;text-transform:uppercase;letter-spacing:.06em;color:#0A5F67;border-bottom:1px solid #cfe5e8;padding-bottom:1px}
      .small{display:block;color:#5B6B7A;font-size:8.5px;font-weight:400;margin-top:1px}
      .sign{display:flex;gap:16px;margin-top:20px}
      .sign div{flex:1;border-top:1px solid #667;padding-top:2px;text-align:center;font-size:8.5px;color:#5B6B7A}
      .foot{margin-top:8px;font-size:8px;color:#8895a0;text-align:center}
      .noprint{margin:0 0 10px;text-align:right}.noprint button{padding:6px 16px;border:0;border-radius:6px;background:#0E7C86;color:#fff;font-weight:600;cursor:pointer}
      @media screen{body{padding:16px;max-width:900px;margin:0 auto}}
      @media print{.noprint{display:none}}`;
    const pv = x => esc(dash(x)).replace(/\n/g, '<br>');
    /* cells: [label, rawText, fullWidth?] — empty values are skipped */
    function kvTable(cells) {
      let out = '', row = [];
      const one = c => `<tr><th>${esc(c[0])}</th><td colspan="3">${pv(c[1])}</td></tr>`;
      const flush = () => { if (!row.length) return; out += row.length === 1 ? one(row[0]) : '<tr>' + row.map(c => `<th>${esc(c[0])}</th><td>${pv(c[1])}</td>`).join('') + '</tr>'; row = []; };
      cells.filter(c => filled(c[1])).forEach(c => { if (c[2]) { flush(); out += one(c); } else { row.push(c); if (row.length === 2) flush(); } });
      flush(); return out ? `<table class="kv">${out}</table>` : '';
    }
    function printArticle(r) {
      const vehRows = r.categories.map((c, n) => {
        const sub = [c.trip_type_label, c.location_label].filter(filled).join(' · ');
        const extra = [flat(c.vehicle_details), flat(c.parameters)].filter(filled).join(' | ');
        return `<tr class="cat"><td class="c">${n + 1}</td><td colspan="3"><b>${esc(c.category_name)}</b>${sub ? `<span class="small">${esc(sub)}</span>` : ''}${extra ? `<span class="small">${esc(extra)}</span>` : ''}</td>
          <td class="r">${moneyS(c.unit_price)}</td><td class="c">${c.qty}</td><td class="r">${moneyS(c.line_total)}</td></tr>`
          + asgFor(r, c).map(a => `<tr class="asg"><td class="c">${a.idx}</td><td>${pv(a.driver_name)}</td><td>${pv(a.driver_mobile)}</td><td>${pv(a.vehicle_number)}</td><td colspan="3"></td></tr>`).join('');
      }).join('');
      const when = [fmtDt(r.datetime_from), fmtDt(r.datetime_to)].filter(Boolean).join('  →  ');
      return `
      <section class="app">
        <div class="bar"><span>#${r.id} — ${esc(r.applicant_name)}${r.applicant_designation ? ' · ' + esc(r.applicant_designation) : ''}</span><span class="st st-${esc(r.status)}">${esc(cap(r.status))}</span></div>
        ${kvTable([
          ['Contact', [r.applicant_mobile, r.applicant_email].filter(filled).join(' · ')], ['Project', r.project],
          
          // AGENCY DETAILS
          ['Service Provider', r.agency_name ? r.agency_name + ' (' + r.agency_mobile + ' · ' + r.agency_email + ')' : '', 1],
          
          ['Travel', when], ['Trip Type', r.trip_type_label],
          ['Required At', r.place_required], ['Place of Visit', r.place_of_visit_label],
          ['Purpose', r.purpose, 1], ['Details', r.details, 1],
          ['Co-Travelers (' + r.co_travelers.length + ')', r.co_travelers.join(', '), 1],
          ['Submitted', fmtDt(r.created_at)], ['Processed', fmtDt(r.approved_at)], ['Completed', fmtDt(r.forwarded_at)]
        ])}
        <h4>Vehicles &amp; Drivers (${r.total_vehicles} requested · ${r.assigned_vehicles || 0} assigned)</h4>
        <table><thead><tr><th style="width:5%">#</th><th>Category / Driver</th><th style="width:15%">Mobile</th><th style="width:17%">Vehicle No.</th><th class="r">Rate</th><th class="c">Qty</th><th class="r">Amount</th></tr></thead>
          <tbody>${vehRows || '<tr><td colspan="7" class="c">—</td></tr>'}</tbody>
          <tfoot><tr><td colspan="5" class="r">Estimated total</td><td class="c">${r.total_vehicles}</td><td class="r">${moneyS(r.total_price)}</td></tr></tfoot></table>
        ${kvTable([
          ['Departure', fmtDt(r.actual_departure)], ['Arrival', fmtDt(r.actual_arrival)],
          ['Out KM', r.out_km], ['In KM', r.in_km], ['Total KM', kmText(r)], ['Tour Order', r.tour_order_pdf],
          ['Super Admin', r.super_admin_remark, 1], ['Forwarded', r.forwarded_remark, 1]
        ]).replace('<table class="kv">', '<h4>Trip Record &amp; Remarks</h4><table class="kv">')}
        <div class="sign"><div>Applicant</div><div>Super Admin</div><div>Forwarded Admin</div></div>
      </section>`;
    }
    function printSummary(recs) {
      return `<div class="sumwrap"><h4>Summary — ${recs.length} application${recs.length === 1 ? '' : 's'}</h4>
        <table><thead><tr><th>ID</th><th>Applicant</th><th>Project</th><th>Travel</th><th>Place of Visit</th><th class="c">Veh.</th><th class="r">Est. Cost</th><th class="r">KM</th><th>Status</th></tr></thead><tbody>
        ${recs.map(r => `<tr><td>#${r.id}</td><td>${esc(r.applicant_name)}</td><td>${pv(r.project)}</td><td>${esc(fmtDt(r.datetime_from) || '—')}${r.datetime_to ? '<span class="small">to ' + esc(fmtDt(r.datetime_to)) + '</span>' : ''}</td>
          <td>${pv(r.place_of_visit_label)}</td><td class="c">${r.total_vehicles}</td><td class="r">${moneyS(r.total_price)}</td><td class="r">${esc(kmText(r) || '—')}</td><td>${esc(cap(r.status))}</td></tr>`).join('')}
        </tbody><tfoot><tr><td colspan="5" class="r">Total</td><td class="c">${recs.reduce((s, r) => s + r.total_vehicles, 0)}</td><td class="r">${moneyS(recs.reduce((s, r) => s + r.total_price, 0))}</td><td class="r">${num(recs.reduce((s, r) => s + (r.total_km || 0), 0))}</td><td></td></tr></tfoot></table></div>`;
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
    const APP_HEAD = ['ID', 'Status', 'Applicant', 'Designation', 'Email', 'Mobile', 'Project', 'Trip Type', 'From', 'To', 'Place Required', 'Place of Visit', 'Purpose', 'Details', 'Co-Travelers', 'Categories (name × qty @ rate)', 'Total Vehicles', 'Assigned Vehicles', 'Estimated Cost (INR)', 'Drivers / Vehicles', 'Super Admin Remark', 'Forwarded Remark', 'Actual Departure', 'Actual Arrival', 'Out KM', 'In KM', 'Total KM', 'Tour Order', 'Submitted', 'Processed On', 'Completed On', 'Agency Name', 'Agency Mobile', 'Agency Email'];
    function appRow(r) {
      const drivers = [];
      r.categories.forEach(c => asgFor(r, c).forEach(a => { if (a.driver_name || a.vehicle_number || a.driver_mobile) drivers.push(`${c.category_name} #${a.idx}: ${a.driver_name} / ${a.driver_mobile} / ${a.vehicle_number}`); }));
      return [r.id, cap(r.status), r.applicant_name, r.applicant_designation, r.applicant_email, r.applicant_mobile, r.project, r.trip_type_label,
        fmtDt(r.datetime_from), fmtDt(r.datetime_to), r.place_required, r.place_of_visit_label, r.purpose, r.details, r.co_travelers.join('; '),
        r.categories.map(c => `${c.category_name} × ${c.qty} @ ${c.unit_price}`).join('; '), r.total_vehicles, r.assigned_vehicles || 0, r.total_price, drivers.join(' | '),
        r.super_admin_remark, r.forwarded_remark, fmtDt(r.actual_departure), fmtDt(r.actual_arrival), r.out_km, r.in_km, kmText(r).replace(/,/g, ''),
        tourUrl(r), fmtDt(r.created_at), fmtDt(r.approved_at), fmtDt(r.forwarded_at),
        r.agency_name || '', r.agency_mobile || '', r.agency_email || ''];
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
        case 'copy':  if (card) { const ok = await copyText(recordText(liveRecord(card))); showToast(ok ? 'Details copied to clipboard' : 'Copy failed', ok ? '' : 'error'); } break;
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
  const CSRF    = <?= json_encode(hired_csrf_token()) ?>;
  const RECORDS = <?= json_encode(array_values($records), $jsonFlags) ?>;
  const D = HiredDash.init({ records: RECORDS, csrf: CSRF });
  const { $, $$, esc, cap, money, num, hasVal } = D.util;
  const { showToast, post, asgFor, syncRecord } = D;

  /* ============================================================
     DYNAMIC ROW & QUANTITY MANAGEMENT  (pending cards only)
     ============================================================ */
  function syncCat(w) {
    const qty = parseInt($('.qty-input', w).value) || 0, unit = parseFloat(w.dataset.unit) || 0;
    $('.js-qty-label', w).textContent = qty; $('.js-line', w).textContent = money(unit * qty);
    const card = w.closest('.app-card'); let veh = 0, tot = 0;
    $$('.cat-wrapper', card).forEach(x => { const q = parseInt($('.qty-input', x).value) || 0; veh += q; tot += q * (parseFloat(x.dataset.unit) || 0); });
    $('.total-veh-badge', card).innerHTML = 'Vehicles: <b>' + veh + '</b>';
    $('.js-veh-total', card).textContent = veh; $('.js-grand', card).textContent = money(tot); $('.js-cost-badge', card).textContent = money(tot);
  }
  const renumber = w => $$('tr[data-idx]', w).forEach((tr, i) => { tr.dataset.idx = i + 1; $('.veh-idx', tr).textContent = i + 1; });

  async function changeQty(wrapper, diff, skipDom) {
    const qtyInp = $('.qty-input', wrapper), cur = parseInt(qtyInp.value) || 0, next = cur + diff;
    if (next < 0) return;
    qtyInp.value = next; syncCat(wrapper);
    try {
      const data = await post({ ajax: 'update_quantity', application_id: wrapper.dataset.app, category_id: wrapper.dataset.cat, qty: next });
      if (data.ok) {
        if (!skipDom) { diff > 0 ? addRow(wrapper) : removeLastRow(wrapper); }
        syncRecord(wrapper.closest('.app-card')); showToast('Quantity updated to ' + next);
      } else { showToast(data.msg || 'Failed to update quantity', 'error'); qtyInp.value = cur; syncCat(wrapper); }
    } catch (e) { showToast('Network error', 'error'); qtyInp.value = cur; syncCat(wrapper); }
  }
  /** option list of an existing select, with nothing pre-selected (the select already carries its placeholder option) */
  function cleanOptions(name, placeholder) {
    const src = $('select[name="' + name + '"]');
    if (!src) return '<option value="">' + placeholder + '</option>';
    const c = src.cloneNode(true);
    $$('option', c).forEach(o => { o.removeAttribute('selected'); o.selected = false; });
    return c.innerHTML;
  }
  function addRow(wrapper) {
    const tbody = $('.veh-tbody', wrapper), index = $$('tr', tbody).length + 1, tr = document.createElement('tr');
    tr.dataset.app = wrapper.dataset.app; tr.dataset.cat = wrapper.dataset.cat; tr.dataset.idx = index;

    tr.innerHTML = `<td class="veh-idx">${index}</td>
      <td data-label="Driver">
        <select class="veh-input form-select form-select-sm" name="driver_select" onchange="syncDriverFields(this)">${cleanOptions('driver_select', '-- Select Driver --')}</select>
        <input type="hidden" name="driver_name" value="">
      </td>
      <td data-label="Mobile"><input class="veh-input" name="driver_mobile" maxlength="10" readonly></td>
      <td data-label="Vehicle No.">
        <select class="veh-input form-select form-select-sm" name="vehicle_select" onchange="syncVehicleFields(this)">${cleanOptions('vehicle_select', '-- Select Vehicle --')}</select>
        <input type="hidden" name="vehicle_number" value="">
      </td>
      <td class="updated-at" data-label="Updated">—</td>
      <td data-label="Action"><div class="veh-actions"><button type="button" class="btn-icon danger" data-act="del-row" title="Remove row" aria-label="Remove row"><i class="bi bi-trash"></i></button></div></td>`;

    tbody.appendChild(tr);
  }
  const removeLastRow = w => { const t = $('.veh-tbody', w); if (t.lastElementChild) t.removeChild(t.lastElementChild); };

  async function deleteRow(tr) {
    const w = tr.closest('.cat-wrapper');
    if (!confirm('Remove vehicle row #' + tr.dataset.idx + '?')) return;
    try {
      const data = await post({ ajax: 'delete_vehicle_row', application_id: tr.dataset.app, category_id: tr.dataset.cat, vehicle_index: tr.dataset.idx });
      if (!data.ok) { showToast(data.msg || 'Delete failed', 'error'); return; }
      tr.remove(); renumber(w); showToast('Row removed');
      changeQty(w, -1, true);
    } catch (e) { showToast('Network error', 'error'); }
  }

  async function saveAllAssignments(btn) {
    const card = btn.closest('.app-card'), rows = [];
    for (const tr of $$('tr[data-app]', card)) {
      const mob = $('[name=driver_mobile]', tr).value.trim();
      if (mob && !/^\d{10}$/.test(mob)) { $('[name=driver_mobile]', tr).focus(); showToast(`Vehicle #${tr.dataset.idx}: mobile must be exactly 10 digits`, 'warn'); return; }
      rows.push({ cat_id: tr.dataset.cat, idx: tr.dataset.idx, driver_name: $('[name=driver_name]', tr).value.trim(), driver_mobile: mob, vehicle_number: $('[name=vehicle_number]', tr).value.trim() });
    }
    const old = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';
    try {
      const data = await post({ ajax: 'save_all_assignments', application_id: card.dataset.appId, rows: JSON.stringify(rows) });
      if (data.ok) {
        $$('tr[data-app]', card).forEach(tr => { $('.updated-at', tr).textContent = data.updated_at || '—'; tr.classList.add('row-saved'); setTimeout(() => tr.classList.remove('row-saved'), 1000); });
        syncRecord(card); showToast('All assignments saved successfully.');
      } else showToast(data.msg || 'Save failed', 'error');
    } catch (e) { showToast('Network error', 'error'); }
    finally { btn.disabled = false; btn.innerHTML = old; }
  }

  // Global function to save agency assignment
  window.saveAgency = async function(appId) {
    const sel = document.getElementById('agency-select-' + appId);
    const agencyId = sel.value;
    
    const btn = sel.nextElementSibling;
    const old = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    try {
      const data = await post({ ajax: 'save_agency', application_id: appId, agency_id: agencyId });
      if (data.ok) {
        showToast('Agency assigned successfully. Refreshing...');
        setTimeout(() => location.reload(), 800);
      } else {
        showToast(data.msg || 'Failed to assign', 'error');
        btn.disabled = false;
        btn.innerHTML = old;
      }
    } catch (e) {
      showToast('Network error', 'error');
      btn.disabled = false;
      btn.innerHTML = old;
    }
  };

  /* ---------------- click delegation (super-admin-only actions) ---------------- */
  document.addEventListener('click', e => {
    const t = e.target.closest('[data-act]'); if (!t) return;
    switch (t.dataset.act) {
      case 'qty-inc': case 'qty-dec': { const w = t.closest('.cat-wrapper'); if (w) changeQty(w, t.dataset.act === 'qty-inc' ? 1 : -1); break; }
      case 'del-row':  { const tr = t.closest('tr'); if (tr) deleteRow(tr); break; }
      case 'save-all-assignments': saveAllAssignments(t); break;
      case 'edit-cat':
        $('#ceId').value = t.dataset.catId; $('#ceTitleId').textContent = '#' + t.dataset.catId;
        $('#ceName').value = t.dataset.catName || ''; $('#cePrice').value = t.dataset.catPrice || '';
        $('#ceDetails').value = t.dataset.catDetails || ''; $('#ceParams').value = t.dataset.catParams || '';
        bootstrap.Modal.getOrCreateInstance($('#catEditModal')).show(); break;
      case 'an-csv': exportAnalytics(); break;
      case 'edit-trip':
        $('#teId').value = t.dataset.app; $('#teTitleId').textContent = '#' + t.dataset.app;
        $('#teDep').value = t.dataset.dep || ''; $('#teArr').value = t.dataset.arr || '';
        $('#teOut').value = t.dataset.out || ''; $('#teIn').value = t.dataset.in || '';
        bootstrap.Modal.getOrCreateInstance($('#tripEditModal')).show(); break;
    }
  });

  /** submit a modal form to an ajax action, then reload on success */
  function bindModalForm(formId, modalId, action, okMsg, guard) {
    $(formId).addEventListener('submit', async e => {
      e.preventDefault();
      const form = e.target;
      if (guard && !guard(form)) return;
      const fd = new FormData(form); fd.append('ajax', action); fd.append('csrf', CSRF);
      const btn = $('button[type=submit]', form), old = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
      try {
        const data = await (await fetch(location.href, { method: 'POST', body: fd, credentials: 'same-origin' })).json();
        if (data.ok) { bootstrap.Modal.getInstance($(modalId)).hide(); showToast(okMsg + ' — refreshing…'); setTimeout(() => location.reload(), 800); }
        else showToast(data.msg || 'Update failed.', 'error');
      } catch { showToast('Network error.', 'error'); }
      finally { btn.disabled = false; btn.innerHTML = old; }
    });
  }
  bindModalForm('#catEditForm', '#catEditModal', 'update_category', 'Category updated');
  bindModalForm('#tripEditForm', '#tripEditModal', 'update_trip_record', 'Trip record updated', form => {
    const f = form.elements;
    const dep = f.actual_departure.value, arr = f.actual_arrival.value, out = parseFloat(f.out_km.value), inn = parseFloat(f.in_km.value);
    if (dep && arr && new Date(arr) < new Date(dep)) { showToast('Arrival cannot be before departure', 'error'); return false; }
    if (!isNaN(out) && !isNaN(inn) && inn < out && !confirm('In KM is less than Out KM. Save anyway?')) return false;
    return true;
  });

  // Auto-fill mobile and hidden name when a driver is selected
  window.syncDriverFields = function (select) {
    const tr = select.closest('tr'), opt = select.options[select.selectedIndex];
    tr.querySelector('[name=driver_name]').value   = opt.value ? opt.dataset.name : '';
    tr.querySelector('[name=driver_mobile]').value = opt.value ? opt.dataset.mob : '';
  };

  // Sync hidden vehicle number when a vehicle is selected
  window.syncVehicleFields = function (select) {
    const tr = select.closest('tr'), opt = select.options[select.selectedIndex];
    tr.querySelector('[name=vehicle_number]').value = opt.value ? opt.dataset.no : '';
  };

  /* ============================================================
     ANALYTICS — tabular (respects the filter bar above)
     ============================================================ */
  const month = r => String(r.created_at || '').slice(0, 7) || 'Unknown';
  const perVeh = r => r.total_vehicles ? 1 / r.total_vehicles : 1;
  const catItems = (r, label) => r.categories.map(c => ({ k: label(c), veh: c.qty, cost: c.line_total, w: r.total_vehicles ? c.qty / r.total_vehicles : 1 }));
  const slotItems = (r, key, label) => {            // one item per assigned vehicle slot
    const out = [];
    r.categories.forEach(c => asgFor(r, c).forEach(a => { if (a[key]) out.push({ k: label(a), veh: 1, cost: c.unit_price, w: perVeh(r) }); }));
    return out;
  };
  // item fields: k = group label · veh / cost / w (km weight) default to whole-application values
  const DIMS = {
    category:    { label: 'Vehicle category', icon: 'truck',        get: r => catItems(r, c => c.category_name) },
    catLocation: { label: 'Category × location', icon: 'signpost-2', get: r => catItems(r, c => c.category_name + ' — ' + (c.location_label || '—')) },
    location:    { label: 'Place of visit',   icon: 'geo-alt',      get: r => [{ k: r.place_of_visit_label || '—' }] },
    required:    { label: 'Place required',   icon: 'pin-map',      get: r => [{ k: r.place_required || '—' }] },
    applicant:   { label: 'Applicant',        icon: 'person',       get: r => [{ k: r.applicant_name || '—' }] },
    designation: { label: 'Designation',      icon: 'briefcase',    get: r => [{ k: r.applicant_designation || '—' }] },
    project:     { label: 'Project',          icon: 'diagram-3',    get: r => [{ k: r.project || 'No project' }] },
    trip:        { label: 'Trip type',        icon: 'arrow-left-right', get: r => [{ k: r.trip_type_label || '—' }] },
    month:       { label: 'Month',            icon: 'calendar3',    get: r => [{ k: month(r) }], sort: 'key' },
    driver:      { label: 'Driver',           icon: 'person-badge', get: r => slotItems(r, 'driver_name', a => a.driver_name + (a.driver_mobile ? ' (' + a.driver_mobile + ')' : '')) },
    vehicle:     { label: 'Vehicle number',   icon: 'hash',         get: r => slotItems(r, 'vehicle_number', a => a.vehicle_number) }
  };
  const COLS = [
    ['k', 'Group'], ['apps', 'Applications'], ['pending', 'Pending'], ['approved', 'Approved'], ['completed', 'Completed'],
    ['rejected', 'Rejected'], ['veh', 'Vehicles'], ['cost', 'Est. cost'], ['km', 'KM'], ['share', 'Share %']
  ];
  const st = { dim: 'category', key: 'apps', dir: -1, user: false };

  function aggregate(dimKey, recs) {
    const map = new Map();
    recs.forEach(r => {
      const per = new Map();
      DIMS[dimKey].get(r).forEach(it => {
        const k = it.k || '—', o = per.get(k) || { veh: 0, cost: 0, w: 0 };
        o.veh += it.veh ?? r.total_vehicles; o.cost += it.cost ?? r.total_price; o.w += it.w ?? 1; per.set(k, o);
      });
      per.forEach((o, k) => {
        const a = map.get(k) || { k, apps: 0, pending: 0, approved: 0, completed: 0, rejected: 0, veh: 0, cost: 0, km: 0 };
        a.apps++; if (a[r.status] !== undefined) a[r.status]++;
        a.veh += o.veh; a.cost += o.cost; a.km += (r.total_km || 0) * Math.min(1, o.w);
        map.set(k, a);
      });
    });
    return [...map.values()];
  }
  function analyticsRows() {
    const recs = D.filtered(), rows = aggregate(st.dim, recs), totApps = recs.length;
    rows.forEach(a => { a.share = totApps ? a.apps / totApps * 100 : 0; });
    let key = st.key, dir = st.dir;
    if (DIMS[st.dim].sort === 'key' && !st.user) { key = 'k'; dir = 1; }      // month: chronological by default
    rows.sort((a, b) => key === 'k' ? dir * String(a.k).localeCompare(String(b.k), undefined, { numeric: true }) : dir * (a[key] - b[key]) || String(a.k).localeCompare(String(b.k)));
    const lim = +$('#anLimit').value; return { recs, rows: lim ? rows.slice(0, lim) : rows };
  }
  const fmtCell = (c, v) => c === 'cost' ? money(v) : c === 'share' ? num(v) + '%' : num(v);

  function renderTable() {
    const { recs, rows } = analyticsRows(), tbl = $('#anTable');
    $('#anSub').textContent = `${DIMS[st.dim].label}-wise · ${recs.length} application${recs.length === 1 ? '' : 's'} (filters applied) · click a column to sort`;
    if (!rows.length) { tbl.innerHTML = '<tbody><tr><td class="an-empty" style="text-align:center">No data for the current filters</td></tr></tbody>'; return; }
    const maxApps = Math.max(...rows.map(r => r.apps), 1);
    const head = '<thead><tr><th>#</th>' + COLS.map(([k, l]) => `<th data-sort="${k}" class="${st.key === k ? 'sorted' : ''}" ${k === 'k' ? 'style="text-align:left"' : ''}>${esc(k === 'k' ? DIMS[st.dim].label : l)}<span class="ar">${st.key === k ? (st.dir > 0 ? '▲' : '▼') : '↕'}</span></th>`).join('') + '</tr></thead>';
    const body = rows.map((a, i) => '<tr><td style="text-align:left;color:#7A8B99">' + (i + 1) + '</td>' + COLS.map(([c]) => {
      if (c === 'k') return `<td class="an-name">${esc(a.k)}</td>`;
      if (c === 'apps') return `<td class="bar-cell" style="--p:${Math.round(a.apps / maxApps * 100)}%"><b>${num(a.apps)}</b></td>`;
      return `<td class="${!a[c] && ['pending', 'approved', 'completed', 'rejected', 'km'].includes(c) ? 'muted' : ''}">${fmtCell(c, a[c])}</td>`;
    }).join('') + '</tr>').join('');
    const T = rows.reduce((s, a) => { ['veh', 'cost', 'km'].forEach(k => s[k] += a[k]); return s; }, { veh: 0, cost: 0, km: 0 });
    const cnt = s => recs.filter(r => r.status === s).length;
    const foot = `<tfoot><tr><td></td><td style="text-align:left">Total</td><td>${num(recs.length)}</td><td>${cnt('pending')}</td><td>${cnt('approved')}</td><td>${cnt('completed')}</td><td>${cnt('rejected')}</td><td>${num(T.veh)}</td><td>${money(T.cost)}</td><td>${num(T.km)}</td><td>100%</td></tr></tfoot>`;
    tbl.innerHTML = head + '<tbody>' + body + '</tbody>' + foot;
  }
  function exportAnalytics() {
    const { rows } = analyticsRows();
    D.downloadCsv('hired_analytics_' + st.dim, [[DIMS[st.dim].label, ...COLS.slice(1).map(c => c[1])], ...rows.map(a => [a.k, ...COLS.slice(1).map(([c]) => c === 'cost' || c === 'km' || c === 'share' ? Math.round(a[c] * 100) / 100 : a[c])])]);
  }

  function kpi(label, value, icon, tone) {
    return `<div class="stat-card ${tone || ''}"><div class="stat-ico"><i class="bi bi-${icon}"></i></div><div class="stat-body"><div class="num">${value}</div><div class="lbl">${esc(label)}</div></div></div>`;
  }
  function renderKpis() {
    const R = D.filtered(), n = R.length, sum = f => R.reduce((s, r) => s + f(r), 0);
    const veh = sum(r => r.total_vehicles), cost = sum(r => r.total_price), km = sum(r => r.total_km || 0), asg = sum(r => r.assigned_vehicles || 0);
    const kmTrips = R.filter(r => r.total_km > 0).length;
    const ok = R.filter(r => r.status === 'approved' || r.status === 'completed').length;
    const decided = R.filter(r => r.status !== 'pending').length;
    $('#kpiGrid').innerHTML = [
      kpi('Applications', num(n), 'files', ''), kpi('Vehicles requested', num(veh), 'truck-front', 'navy'),
      kpi('Estimated cost', money(cost).replace('.00', ''), 'currency-rupee', 'gold'), kpi('Total KM travelled', num(km), 'speedometer2', ''),
      kpi('Avg KM / trip', kmTrips ? num(km / kmTrips) : '0', 'signpost-split', ''), kpi('Avg vehicles / app', n ? num(veh / n) : '0', 'calculator', 'navy'),
      kpi('Vehicles assigned', veh ? Math.round(asg / veh * 100) + '%' : '0%', 'person-check', 'approved'),
      kpi('Approval rate', decided ? Math.round(ok / decided * 100) + '%' : '—', 'patch-check', 'approved')
    ].join('');
  }
  function renderAnalytics() { renderKpis(); renderTable(); }

  $('#anPills').innerHTML = Object.entries(DIMS).map(([k, d]) => `<button type="button" data-dim="${k}" class="${k === st.dim ? 'active' : ''}"><i class="bi bi-${d.icon}"></i>${esc(d.label)}</button>`).join('');
  $('#anPills').addEventListener('click', e => {
    const b = e.target.closest('[data-dim]'); if (!b) return;
    st.dim = b.dataset.dim; st.key = 'apps'; st.dir = -1; st.user = false;
    $$('#anPills button').forEach(x => x.classList.toggle('active', x === b)); renderTable();
  });
  $('#anTable').addEventListener('click', e => {
    const th = e.target.closest('th[data-sort]'); if (!th) return;
    const k = th.dataset.sort; st.dir = st.key === k ? -st.dir : (k === 'k' ? 1 : -1); st.key = k; st.user = true; renderTable();
  });
  $('#anLimit').addEventListener('change', renderTable);
  $('#analyticsTabBtn').addEventListener('shown.bs.tab', renderAnalytics);
  D.onFilter(() => { if ($('#tab-analytics').classList.contains('active')) renderAnalytics(); });
})();
</script>
</body>
</html>
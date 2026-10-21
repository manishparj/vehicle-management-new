<?php
include('./config/db.php');

// ============================================================
// Constants — must match ENUMs in hired_vehicles
// ============================================================
$TRIP_TYPES = [
    'one_way'    => 'One Way Trip',
    'round_trip' => 'Round Trip',
];

$LOCATION_LABELS = [
    'mrhu_bhanpur_kallan_jaipur'     => 'MRHRU, Bhanpur Kallan, Jaipur (Rajasthan)',
    'icmr_niht_jodhpur_or_rajasthan' => 'ICMR-NIHR, Jodhpur (Rajasthan) or anywhere in Rajasthan',
    'any_city_rajasthan'             => 'Any city of Rajasthan',
    'any_city_india_excl_rajasthan'  => 'Any city of India excluding Rajasthan',
    'other'                          => 'Other',
];

// ============================================================
// Fetch forwarded admins + their assigned projects
// ============================================================
$applicantsQuery = "
    SELECT a.id, a.name, a.email, a.mob, a.designation,
           GROUP_CONCAT(hap.project_id ORDER BY p.label SEPARATOR ',') AS project_ids,
           GROUP_CONCAT(p.label      ORDER BY p.label SEPARATOR '||') AS project_labels
    FROM hired_applicants a
    LEFT JOIN hired_applicant_projects hap ON hap.applicant_id = a.id
    LEFT JOIN projects p                   ON p.id = hap.project_id AND p.is_active = 1
    WHERE a.role = 'hired_forwarded_admin'
    GROUP BY a.id
    ORDER BY a.name ASC
";
$applicantsResult = $conn->query($applicantsQuery);
$applicantsList = [];
if ($applicantsResult && $applicantsResult->num_rows > 0) {
    while ($row = $applicantsResult->fetch_assoc()) {
        $applicantsList[] = $row;
    }
}

// ============================================================
// Fetch ALL active vehicle categories (we'll filter client-side)
// ============================================================
$catQuery = "SELECT id, category_name, trip_type, location_type, location_other,
                    vehicle_details, parameters, price_per_vehicle
             FROM hired_vehicles
             WHERE is_active = 1
             ORDER BY trip_type ASC, location_type ASC, category_name ASC";
$catResult = $conn->query($catQuery);
$catRows = ($catResult && $catResult->num_rows > 0) ? $catResult->fetch_all(MYSQLI_ASSOC) : [];

// ============================================================
// Build unique location list for the "Place of Visit" dropdown
// ============================================================
$availableLocations = [];
$locSeen = [];
foreach ($catRows as $c) {
    $key = $c['location_type'] ?? '';
    if ($key === '' || isset($locSeen[$key])) continue;
    $locSeen[$key] = true;

    if ($key === 'other' && !empty($c['location_other'])) {
        $availableLocations[$key] = 'Other: ' . $c['location_other'];
    } else {
        $availableLocations[$key] = $LOCATION_LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }
}
if (empty($availableLocations)) {
    foreach ($LOCATION_LABELS as $k => $v) $availableLocations[$k] = $v;
}

$availableTripTypes = [];
foreach ($catRows as $c) {
    $t = $c['trip_type'] ?? '';
    if ($t !== '') $availableTripTypes[$t] = true;
}
$availableTripTypes = array_keys($availableTripTypes);

$serverNow = date('Y-m-d H:i');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0B2540">
<title>Hired Vehicle Application — ICMR-NIHR</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&family=Noto+Sans+Devanagari:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="form.css">
</head>
<body class="page-wide">
<?php include('./header.php'); ?>

<!-- PORTAL SWITCHER -->
<div class="portal-switch">
  <div class="portal-switch-inner">
    <nav class="portal-tabs" role="tablist" aria-label="Application portal">
      <a href="index.php" class="portal-tab" role="tab" aria-selected="false">
        <i class="bi bi-car-front-fill"></i><span>Institutional Vehicle</span>
      </a>
      <a href="hired_application_form.php" class="portal-tab active" role="tab" aria-selected="true">
        <i class="bi bi-truck"></i><span>Hired Vehicle</span>
      </a>
    </nav>
  </div>
</div>

<!-- HERO -->
<header class="ph">
  <div class="container">
    <span class="ph-badge">ICMR-NIHR Jodhpur</span>
    <div class="ph-icon"><i class="bi bi-truck"></i></div>
    <h1>Hired Vehicle Application</h1>
    <p>Fill in all required details to submit your hired transport request</p>
  </div>
</header>

<main class="container app-container pb-5">
  <div class="fc">
    <div class="fb fb-solo fb-pad">
    <form id="appForm" method="POST" action="hired_submit_application.php" enctype="multipart/form-data">

      <!-- §1 Applicant Details -->
      <section class="fsec">
        <div class="sh">
          <div class="si"><i class="bi bi-person-fill"></i></div>
          <div><h5>Applicant Details</h5><small class="hi">आवेदक विवरण — Select applicant to auto-fill</small></div>
          <div class="sn">1</div>
        </div>

        <!-- Applicant + Project on one row -->
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label">Select Applicant <span class="rd"></span><span class="hi">आवेदक चुनें</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-people-fill"></i></span>
              <select id="applicantSelect" class="form-select" required>
                <option value="" disabled selected>— Select an applicant —</option>
                <?php foreach ($applicantsList as $ap): ?>
                  <option
                    value="<?= (int)$ap['id'] ?>"
                    data-name="<?= htmlspecialchars($ap['name'] ?? '') ?>"
                    data-email="<?= htmlspecialchars($ap['email'] ?? '') ?>"
                    data-mob="<?= htmlspecialchars($ap['mob'] ?? '') ?>"
                    data-designation="<?= htmlspecialchars($ap['designation'] ?? '') ?>"
                    data-project-ids="<?= htmlspecialchars($ap['project_ids'] ?? '') ?>"
                    data-project-labels="<?= htmlspecialchars($ap['project_labels'] ?? '') ?>">
                    <?= htmlspecialchars($ap['name'] ?? 'Unnamed') ?>
                    <?php if (!empty($ap['designation'])): ?> — <?= htmlspecialchars($ap['designation']) ?><?php endif; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php if (empty($applicantsList)): ?>
              <div class="nw mt-2">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>No applicants found. Please ask the administrator to add forwarded admin users first.</div>
              </div>
            <?php endif; ?>
          </div>

          <div class="col-md-6">
            <label class="form-label">Project <span class="rd"></span><span class="hi">परियोजना चुनें</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-diagram-3"></i></span>
              <select id="projectSelect" name="project_id" class="form-select" required disabled>
                <option value="" disabled selected>— Select an applicant first —</option>
              </select>
            </div>
            <small class="form-text-sm">Only projects assigned to the selected applicant are shown.</small>
            <div id="projectEmptyNote" class="nw mt-2" style="display:none">
              <i class="bi bi-exclamation-triangle-fill"></i>
              <div>This applicant has no projects assigned. Please contact the administrator.</div>
            </div>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label">Applicant Name <span class="rd"></span><span class="hi">आवेदक का नाम</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-person"></i></span>
              <input type="text" name="applicant_name" id="applicant_name" class="form-control" placeholder="Auto-filled" readonly required>
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Designation <span class="rd"></span><span class="hi">पदनाम</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-briefcase"></i></span>
              <input type="text" name="applicant_designation" id="applicant_designation" class="form-control" placeholder="Auto-filled" readonly required>
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Mobile Number <span class="rd"></span><span class="hi">मोबाइल नंबर</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-phone"></i></span>
              <input type="text" name="applicant_mobile" id="applicant_mobile" class="form-control" placeholder="Auto-filled" readonly required>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label">Email Address <span class="rd"></span><span class="hi">ईमेल पता</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-envelope"></i></span>
              <input type="email" name="applicant_email" id="applicant_email" class="form-control" placeholder="Auto-filled" readonly required>
              <button type="button" id="sendOtpBtn" class="btn btn-p" disabled><i class="bi bi-send me-1"></i><span>Send OTP</span></button>
            </div>
            <div id="emailError" class="msg fe"></div>
          </div>
        </div>

        <!-- OTP Section -->
        <div class="row g-3">
          <div class="col-12">
            <div class="nw mb-0">
              <i class="bi bi-exclamation-triangle-fill"></i>
              <div class="nw-body"><strong>Email verification is mandatory.</strong> Please verify your email with OTP before submitting.
              <span class="hi">ईमेल सत्यापन अनिवार्य है। कृपया OTP से अपना ईमेल सत्यापित करें।</span></div>
            </div>
            <div class="otps" id="otpSection">
              <div class="otpl"><i class="bi bi-shield-lock"></i> Enter the 4-digit OTP sent to your inbox</div>
              <div class="input-group otp-group">
                <span class="input-group-text"><i class="bi bi-key"></i></span>
                <input type="text" id="otpCode" class="form-control otp-input" placeholder="_ _ _ _" maxlength="4" pattern="\d{4}" inputmode="numeric" autocomplete="one-time-code">
                <button type="button" id="verifyOtpBtn" class="btn btn-ok"><i class="bi bi-check-circle me-1"></i><span>Verify</span></button>
              </div>
              <div id="otpError" class="msg fe"></div>
              <div id="otpSuccess" class="msg fs"></div>
            </div>
          </div>
        </div>
      </section>

      <!-- §2 Co-Travelers -->
      <section class="fsec">
        <div class="sh">
          <div class="si"><i class="bi bi-people-fill"></i></div>
          <div><h5>Co-Travelers</h5><small class="hi">सह-यात्री — Add as many as needed</small></div>
          <div class="sn">2</div>
        </div>
        <div id="coTravelersContainer" class="row g-3 mb-3">
          <div class="col-sm-6 col-lg-4 co-traveler-row">
            <div class="ct">
              <div class="ctn">1</div>
              <input type="text" name="co_travelers[]" placeholder="Co-Traveler 1 name">
            </div>
          </div>
        </div>
        <button type="button" id="addCoTraveler" class="btn btn-sm btn-outline-secondary"><i class="bi bi-plus-circle me-1"></i>Add Co-Traveler</button>
      </section>

      <!-- §3 Travel Details + Tour Order (merged) -->
      <section class="fsec">
        <div class="sh">
          <div class="si"><i class="bi bi-calendar-event"></i></div>
          <div><h5>Travel Details &amp; Tour Order</h5><small class="hi">यात्रा विवरण एवं आदेश — Dates, places, purpose &amp; tour order</small></div>
          <div class="sn">3</div>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">From Date &amp; Time <span class="rd"></span><span class="hi">प्रस्थान तिथि व समय</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
              <input type="text" id="datetime_from" name="datetime_from" class="form-control" placeholder="dd/mm/yyyy HH:MM" required>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label">To Date &amp; Time <span class="rd"></span><span class="hi">वापसी तिथि व समय</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-calendar-check"></i></span>
              <input type="text" id="datetime_to" name="datetime_to" class="form-control" placeholder="dd/mm/yyyy HH:MM" required>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Place Where Vehicle is Required <span class="rd"></span><span class="hi">वाहन कहाँ आवश्यक है</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
              <input type="text" name="place_required" class="form-control" placeholder="Pickup location" required>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Place of Visit <span class="rd"></span><span class="hi">भ्रमण स्थान</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-signpost"></i></span>
              <select id="placeOfVisit" name="place_of_visit" class="form-select" required>
                <option value="">Select Place of Visit</option>
                <?php foreach ($availableLocations as $key => $label): ?>
                  <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Purpose of Visit <span class="rd"></span><span class="hi">यात्रा का उद्देश्य</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-chat-left-text"></i></span>
              <input type="text" name="purpose" class="form-control" placeholder="Purpose of the trip" required>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Trip Type <span class="rd"></span><span class="hi">यात्रा प्रकार</span></label>
            <div class="trip-pills" id="tripTypeGroup">
              <div class="trip-pill">
                <input type="radio" name="trip_type" id="tripOneWay" value="one_way" required>
                <label for="tripOneWay"><i class="bi bi-arrow-right"></i>One Way Trip</label>
              </div>
              <div class="trip-pill">
                <input type="radio" name="trip_type" id="tripRound" value="round_trip" required>
                <label for="tripRound"><i class="bi bi-arrow-left-right"></i>Round Trip</label>
              </div>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label">Details <span class="hi">विवरण</span></label>
            <textarea name="details" class="form-control" rows="2" placeholder="Any additional details about the journey..."></textarea>
          </div>

          <!-- Tour Order — inline within same section -->
          <div class="col-12">
            <hr class="fsec-divider">
            <label class="form-label"><i class="bi bi-file-earmark-check me-1"></i>Tour Order <span class="rd"></span><span class="hi">यात्रा आदेश — Compulsory PDF</span></label>
            <div class="nw">
              <i class="bi bi-exclamation-circle-fill"></i>
              <div class="nw-body">Uploading the tour order PDF is <strong>compulsory</strong> for all hired vehicle requests.
              <span class="hi">सभी किराए के वाहन अनुरोधों के लिए यात्रा आदेश पीडीएफ अपलोड करना अनिवार्य है।</span></div>
            </div>
            <label class="uz d-block" for="tourFile">
              <i class="bi bi-cloud-arrow-up"></i>
              <p class="fw-semibold">Click or drag &amp; drop your PDF here</p>
              <small>Accepted format: PDF only (max 2MB)</small>
              <input type="file" name="tour_order_pdf" id="tourFile" hidden accept="application/pdf" required>
            </label>
            <div class="text-center mt-3">
              <button type="button" id="previewBtn" class="btn btn-sm btn-outline-primary d-none"><i class="bi bi-eye me-1"></i>Preview PDF</button>
            </div>
          </div>
        </div>
      </section>

      <!-- §4 Vehicle Categories -->
      <section class="fsec">
        <div class="sh">
          <div class="si"><i class="bi bi-truck-front"></i></div>
          <div><h5>Vehicle Categories Required</h5><small class="hi">आवश्यक वाहन श्रेणियाँ — Filtered by Place of Visit &amp; Trip Type</small></div>
          <div class="sn">4</div>
        </div>

        <div id="filterChip" class="filter-chip" style="display:none">
          <i class="bi bi-funnel-fill"></i>
          <span id="filterChipText"></span>
        </div>

        <?php if (empty($catRows)): ?>
          <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>No vehicle categories available. Please contact the administrator.</div>
        <?php else: ?>
          <div class="veh-table-wrap mb-4" id="vehTableWrap">
            <table class="veh-table">
              <thead>
                <tr>
                  <th style="width:18%">Category</th>
                  <th style="width:14%">Trip Type</th>
                  <th style="width:20%">Vehicle Details</th>
                  <th style="width:20%">Parameters</th>
                  <th style="width:20%">Location Type</th>
                  <th style="width:14%" class="text-center">Price / Vehicle</th>
                  <th style="width:7%" class="text-center">Select</th>
                  <th style="width:7%" class="text-center">Qty</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($catRows as $cat):
                  $tripKey = $cat['trip_type'] ?? 'round_trip';
                  $tripLbl = $TRIP_TYPES[$tripKey] ?? ucfirst(str_replace('_', ' ', $tripKey));
                  $locationKey = $cat['location_type'] ?? 'any_city_rajasthan';
                  $locationLbl = $LOCATION_LABELS[$locationKey] ?? ucfirst(str_replace('_', ' ', $locationKey));
                ?>
                  <tr data-cat-id="<?= (int)$cat['id'] ?>"
                      data-trip-type="<?= htmlspecialchars($tripKey) ?>"
                      data-location-type="<?= htmlspecialchars($cat['location_type'] ?? '') ?>">
                    <td data-label="Category">
                      <div class="veh-cat-name"><?= htmlspecialchars($cat['category_name']) ?></div>
                      <div class="veh-cat-id">#<?= (int)$cat['id'] ?></div>
                    </td>
                    <td data-label="Trip Type">
                      <span class="badge-trip <?= htmlspecialchars($tripKey) ?>">
                        <i class="bi bi-<?= $tripKey === 'one_way' ? 'arrow-right' : 'arrow-left-right' ?> me-1"></i><?= htmlspecialchars($tripLbl) ?>
                      </span>
                    </td>
                    <td data-label="Vehicle Details">
                      <div class="veh-detail-text">
                        <?= !empty($cat['vehicle_details']) ? nl2br(htmlspecialchars($cat['vehicle_details'])) : '<span style="color:#a7b2ba">—</span>' ?>
                      </div>
                    </td>
                    <td data-label="Parameters">
                      <div class="veh-detail-text">
                        <?= !empty($cat['parameters']) ? nl2br(htmlspecialchars($cat['parameters'])) : '<span style="color:#a7b2ba">—</span>' ?>
                      </div>
                    </td>
                    <td data-label="Location Type">
                      <div class="veh-detail-text">
                        <?= !empty($locationLbl) ? nl2br(htmlspecialchars($locationLbl)) : '<span style="color:#a7b2ba">—</span>' ?>
                      </div>
                    </td>
                    <td data-label="Price / Vehicle" class="text-center">
                      <div class="veh-price">
                        ₹<?= number_format((float)$cat['price_per_vehicle'], 2) ?>
                        <small>incl. GST</small>
                      </div>
                    </td>
                    <td data-label="Select" class="veh-check-cell">
                      <input class="form-check-input cat-checkbox" type="checkbox"
                             name="vehicle_categories[]"
                             value="<?= (int)$cat['id'] ?>"
                             id="cat_<?= (int)$cat['id'] ?>"
                             aria-label="Select <?= htmlspecialchars($cat['category_name']) ?>">
                    </td>
                    <td data-label="Qty" class="veh-qty-cell text-center">
                      <input type="number"
                             name="vehicle_qty[<?= (int)$cat['id'] ?>]"
                             class="veh-qty-input"
                             min="1" max="10" value="1"
                             disabled>
                      <div class="veh-qty-hint">No. of vehicles</div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="filtered-empty" id="filteredEmpty">
            <i class="bi bi-search"></i>
            <p>No vehicle categories match your filters.</p>
            <small>Please change the Place of Visit or Trip Type above.</small>
          </div>
        <?php endif; ?>
      </section>

      <button id="submitBtn" class="btn-sub" type="submit" disabled>
        <span id="btnText"><i class="bi bi-send-fill me-2"></i>Submit Application <span class="hi" style="color:inherit;opacity:.85">/ आवेदन जमा करें</span></span>
        <span id="btnLoader" style="display:none"><span class="spinner-border spinner-border-sm me-2"></span>Submitting…</span>
      </button>
      <p class="secure-note"><i class="bi bi-lock-fill me-1"></i>Your data is securely handled by ICMR-NIHR Jodhpur</p>

    </form>
    </div>
  </div>
</main>

<!-- PDF Modal -->
<div class="modal fade" id="pdfModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-file-pdf me-2 text-danger"></i>Tour Order Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <iframe id="pdfFrame" width="100%" height="650" style="display:block;border:none"></iframe>
      </div>
    </div>
  </div>
</div>

<!-- Loading Overlay -->
<div id="pageOverlay" class="position-fixed top-0 start-0 w-100 h-100 d-none" style="z-index:9998">
  <div class="journey-box">
    <div class="road-track"><span class="road-car"><i class="bi bi-truck"></i></span></div>
    <h6>Your request is on its way</h6>
    <span class="hi">आपका अनुरोध भेजा जा रहा है</span>
    <p class="jsub">Please wait, submitting application…</p>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
'use strict';

document.addEventListener('DOMContentLoaded', function () {

  if (typeof flatpickr === 'undefined') {
    console.error('Flatpickr failed to load from CDN');
    alert('Date picker failed to load. Please refresh the page.');
    return;
  }

  const $ = id => document.getElementById(id);
  const cls = (el, add, c) => el.classList[add ? 'add' : 'remove'](c);
  const setMsg = (el, html) => { el.innerHTML = html; cls(el, true, 'on'); };
  const clearMsg = el => { cls(el, false, 'on'); el.innerHTML = ''; };

  let verified = false;
  let coTravelerCount = 1;

  // ============================================================
  // DATE PICKERS
  // ============================================================
  const now       = new Date();
  const nowPlus15 = new Date(now.getTime() + 15 * 60 * 1000);
  const nowPlus60 = new Date(now.getTime() + 60 * 60 * 1000);

  const pad = n => String(n).padStart(2, '0');

  const baseConfig = {
    enableTime:   true,
    altInput:     true,
    altFormat:    'd/m/Y H:i',
    dateFormat:   'Y-m-d H:i:S',
    time_24hr:    true,
    disableMobile: true,
    minDate:      'today'
  };

  let toPicker = null;

  const fromPicker = flatpickr('#datetime_from', Object.assign({}, baseConfig, {
    defaultDate: nowPlus15,
    minTime: `${pad(now.getHours())}:${pad(now.getMinutes())}`,
    onReady: function (selectedDates, dateStr, instance) {
      applyMinTime(instance, 'from');
    },
    onChange: function (selectedDates, dateStr, instance) {
      applyMinTime(instance, 'from');
      if (selectedDates[0] && toPicker) {
        toPicker.set('minDate', selectedDates[0]);
        const curTo = toPicker.selectedDates[0];
        if (curTo && curTo < selectedDates[0]) toPicker.clear();
      }
      applyMinTime(toPicker, 'to');
    }
  }));

  toPicker = flatpickr('#datetime_to', Object.assign({}, baseConfig, {
    defaultDate: nowPlus60,
    minTime: `${pad(nowPlus60.getHours())}:${pad(nowPlus60.getMinutes())}`,
    onReady: function (selectedDates, dateStr, instance) {
      applyMinTime(instance, 'to');
    },
    onChange: function (selectedDates, dateStr, instance) {
      applyMinTime(instance, 'to');
    }
  }));

  applyMinTime(fromPicker, 'from');
  applyMinTime(toPicker, 'to');

  function applyMinTime(picker, kind) {
    if (!picker) return;
    const sel = picker.selectedDates[0];
    if (!sel) return;

    const isToday = sel.toDateString() === now.toDateString();
    if (!isToday) { picker.set('minTime', '00:00'); return; }

    let minTime = `${pad(now.getHours())}:${pad(now.getMinutes())}`;
    if (kind === 'to' && fromPicker) {
      const fd = fromPicker.selectedDates[0];
      if (fd && fd.toDateString() === now.toDateString()) {
        const fMin = `${pad(fd.getHours())}:${pad(fd.getMinutes())}`;
        if (fMin > minTime) minTime = fMin;
      }
    }
    picker.set('minTime', minTime);
  }

  // ============================================================
  // APPLICANT DROPDOWN → AUTO-FILL + POPULATE PROJECT DROPDOWN
  // ============================================================
  const applicantSelect    = $('applicantSelect');
  const projectSelect      = $('projectSelect');
  const projectEmptyNote   = $('projectEmptyNote');

  if (applicantSelect) {
    applicantSelect.addEventListener('change', function () {
      const opt = this.options[this.selectedIndex];
      if (!opt || !this.value) return;

      const name        = opt.dataset.name || '';
      const email       = opt.dataset.email || '';
      const mob         = opt.dataset.mob || '';
      const designation = opt.dataset.designation || '';

      $('applicant_name').value        = name;
      $('applicant_email').value       = email;
      $('applicant_mobile').value      = mob;
      $('applicant_designation').value = designation;

      populateProjectDropdown(opt.dataset.projectIds || '', opt.dataset.projectLabels || '');
      resetOtpState();
      $('sendOtpBtn').disabled = !email;
      clearMsg($('emailError'));
      clearMsg($('otpError'));
      clearMsg($('otpSuccess'));
    });
  }

  function populateProjectDropdown(ids, labels) {
    projectSelect.innerHTML = '';
    const idArr    = ids    ? ids.split(',').map(s => s.trim()).filter(Boolean)     : [];
    const labelArr = labels ? labels.split('||').map(s => s.trim()).filter(Boolean) : [];

    if (idArr.length === 0 || idArr.length !== labelArr.length) {
      projectSelect.disabled = true;
      const opt = document.createElement('option');
      opt.value = ''; opt.disabled = true; opt.selected = true;
      opt.textContent = '— No projects assigned —';
      projectSelect.appendChild(opt);
      if (projectEmptyNote) projectEmptyNote.style.display = 'flex';
      return;
    }

    if (projectEmptyNote) projectEmptyNote.style.display = 'none';

    const placeholder = document.createElement('option');
    placeholder.value = ''; placeholder.disabled = true; placeholder.selected = true;
    placeholder.textContent = '— Select a project —';
    projectSelect.appendChild(placeholder);

    idArr.forEach((pid, idx) => {
      const opt = document.createElement('option');
      opt.value = pid;
      opt.textContent = labelArr[idx] || ('Project #' + pid);
      projectSelect.appendChild(opt);
    });
    projectSelect.disabled = false;
  }

  // ============================================================
  // CO-TRAVELERS
  // ============================================================
  $('addCoTraveler').addEventListener('click', () => {
    coTravelerCount++;
    const container = $('coTravelersContainer');
    const col = document.createElement('div');
    col.className = 'col-sm-6 col-lg-4 co-traveler-row';
    col.innerHTML = `
      <div class="ct">
        <div class="ctn">${coTravelerCount}</div>
        <input type="text" name="co_travelers[]" placeholder="Co-Traveler ${coTravelerCount} name">
        <button type="button" class="btn btn-sm text-danger remove-ct" title="Remove" style="padding:0 4px"><i class="bi bi-x-circle"></i></button>
      </div>`;
    container.appendChild(col);
  });

  document.addEventListener('click', (e) => {
    if (e.target.closest('.remove-ct')) {
      const row = e.target.closest('.co-traveler-row');
      if (row) { row.remove(); renumberCoTravelers(); }
    }
  });

  function renumberCoTravelers() {
    const rows = document.querySelectorAll('#coTravelersContainer .co-traveler-row');
    coTravelerCount = Math.max(rows.length, 1);
    rows.forEach((r, idx) => {
      const badge = r.querySelector('.ctn');
      const input = r.querySelector('input');
      if (badge) badge.textContent = idx + 1;
      if (input) input.placeholder = `Co-Traveler ${idx + 1} name`;
    });
  }

  // ============================================================
  // TOUR FILE PREVIEW
  // ============================================================
  $('tourFile').addEventListener('change', function () {
    if (this.files.length) cls($('previewBtn'), false, 'd-none');
    else cls($('previewBtn'), true, 'd-none');
  });
  $('previewBtn').addEventListener('click', () => {
    const f = $('tourFile').files[0];
    if (f) {
      $('pdfFrame').src = URL.createObjectURL(f);
      new bootstrap.Modal($('pdfModal')).show();
    }
  });

  // ============================================================
  // DYNAMIC FILTERING — Place of Visit + Trip Type → Vehicle Table
  // ============================================================
  const placeOfVisitSelect = $('placeOfVisit');
  const tripTypeGroup      = $('tripTypeGroup');
  const vehTableWrap       = $('vehTableWrap');
  const filteredEmpty      = $('filteredEmpty');
  const filterChip         = $('filterChip');
  const filterChipText     = $('filterChipText');

  function getSelectedTripType() {
    const checked = tripTypeGroup.querySelector('input[name="trip_type"]:checked');
    return checked ? checked.value : '';
  }

  function applyFilters() {
    if (!vehTableWrap) return;

    const locKey = placeOfVisitSelect.value;
    const tripKey = getSelectedTripType();

    const rows = vehTableWrap.querySelectorAll('tbody tr');
    let visibleCount = 0;

    rows.forEach(tr => {
      const rowTrip = tr.dataset.tripType || '';
      const rowLoc  = tr.dataset.locationType || '';

      const locMatch  = !locKey  || rowLoc === locKey;
      const tripMatch = !tripKey || rowTrip === tripKey;

      if (locMatch && tripMatch) {
        tr.style.display = '';
        visibleCount++;
      } else {
        tr.style.display = 'none';
        const cb = tr.querySelector('.cat-checkbox');
        const qty = tr.querySelector('.veh-qty-input');
        if (cb && cb.checked) {
          cb.checked = false;
          tr.classList.remove('selected');
          if (qty) { qty.disabled = true; qty.required = false; qty.value = 1; }
        }
      }
    });

    if (visibleCount === 0 && (locKey || tripKey)) {
      vehTableWrap.style.display = 'none';
      if (filteredEmpty) filteredEmpty.classList.add('on');
    } else {
      vehTableWrap.style.display = '';
      if (filteredEmpty) filteredEmpty.classList.remove('on');
    }

    const parts = [];
    if (locKey) {
      const opt = placeOfVisitSelect.options[placeOfVisitSelect.selectedIndex];
      if (opt) parts.push('📍 ' + opt.textContent.trim());
    }
    if (tripKey) {
      parts.push(tripKey === 'one_way' ? '→ One Way' : '⇄ Round Trip');
    }
    if (parts.length && filterChip) {
      filterChipText.textContent = parts.join('  •  ');
      filterChip.style.display = 'inline-flex';
    } else if (filterChip) {
      filterChip.style.display = 'none';
    }
  }

  placeOfVisitSelect.addEventListener('change', applyFilters);
  tripTypeGroup.querySelectorAll('input[name="trip_type"]').forEach(r => {
    r.addEventListener('change', applyFilters);
  });

  // ============================================================
  // VEHICLE CATEGORY TABLE — CHECKBOX TOGGLE
  // ============================================================
  document.querySelectorAll('.cat-checkbox').forEach(cb => {
    cb.addEventListener('change', function () {
      const tr = this.closest('tr');
      const qtyInput = tr.querySelector('.veh-qty-input');
      if (this.checked) {
        tr.classList.add('selected');
        if (qtyInput) {
          qtyInput.disabled = false;
          qtyInput.required = true;
          if (!qtyInput.value || qtyInput.value < 1) qtyInput.value = 1;
          qtyInput.focus();
          qtyInput.select();
        }
      } else {
        tr.classList.remove('selected');
        if (qtyInput) {
          qtyInput.disabled = true;
          qtyInput.required = false;
          qtyInput.value = 1;
        }
      }
    });
  });

  // ============================================================
  // SEND OTP
  // ============================================================
  $('sendOtpBtn').addEventListener('click', async function () {
    const email = $('applicant_email').value.trim();
    if (!email) { alert('Please select an applicant first.'); return; }
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span><span>Sending…</span>';
    try {
      const r = await fetch('hired_send_otp.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, staff_type: 'Hired' })
      });
      const d = await r.json();
      if (d.success) {
        cls($('otpSection'), true, 'on');
        alert('OTP sent! Please check your inbox.');
        $('otpCode').focus();
      } else {
        alert(d.message || 'Failed to send OTP. Please try again.');
        this.disabled = false;
      }
    } catch {
      alert('Error sending OTP. Please try again.');
      this.disabled = false;
    } finally {
      this.innerHTML = '<i class="bi bi-send me-1"></i><span>Send OTP</span>';
      if (verified) this.disabled = true;
    }
  });

  // ============================================================
  // VERIFY OTP
  // ============================================================
  $('verifyOtpBtn').addEventListener('click', async function () {
    const otp = $('otpCode').value, ee = $('otpError'), es = $('otpSuccess');
    if (otp.length !== 4) {
      setMsg(ee, '<i class="bi bi-x-circle"></i> Please enter the 4-digit OTP.');
      return;
    }
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span><span>Verifying…</span>';
    try {
      const r = await fetch('hired_verify_otp.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: $('applicant_email').value, otp })
      });
      const d = await r.json();
      if (d.success) {
        verified = true;
        clearMsg(ee);
        setMsg(es, '<i class="bi bi-check-circle-fill"></i> Email verified successfully!');
        $('sendOtpBtn').disabled = true;
        this.disabled = true;
        this.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i><span>Verified</span>';
        $('submitBtn').disabled = false;
        setTimeout(() => cls($('otpSection'), false, 'on'), 2000);
      } else {
        setMsg(ee, '<i class="bi bi-x-circle"></i> ' + (d.message || 'Invalid OTP. Please try again.'));
        this.disabled = false;
        this.innerHTML = '<i class="bi bi-check-circle me-1"></i><span>Verify</span>';
      }
    } catch {
      setMsg(ee, '<i class="bi bi-x-circle"></i> Error verifying OTP. Please try again.');
      this.disabled = false;
      this.innerHTML = '<i class="bi bi-check-circle me-1"></i><span>Verify</span>';
    }
  });

  // ============================================================
  // SUBMIT GUARD
  // ============================================================
  $('appForm').addEventListener('submit', function (e) {
    if (!applicantSelect.value) {
      e.preventDefault();
      alert('Please select an applicant from the dropdown first.');
      applicantSelect.focus();
      return;
    }
    if (!projectSelect.value) {
      e.preventDefault();
      alert('Please select a project.');
      projectSelect.focus();
      return;
    }
    if (!verified) {
      e.preventDefault();
      alert('Please verify the applicant\'s email address with OTP before submitting.');
      return;
    }

    const fromVal = $('datetime_from').value;
    const toVal   = $('datetime_to').value;
    if (!fromVal) { e.preventDefault(); alert('Please pick a From Date & Time.'); return; }
    if (!toVal)   { e.preventDefault(); alert('Please pick a To Date & Time.');   return; }

    const tsFrom = new Date(fromVal).getTime();
    const tsTo   = new Date(toVal).getTime();
    if (tsTo <= tsFrom) {
      e.preventDefault();
      alert('"To Date & Time" must be after "From Date & Time".');
      return;
    }

    if (!placeOfVisitSelect.value) {
      e.preventDefault();
      alert('Please select a Place of Visit.');
      placeOfVisitSelect.focus();
      return;
    }

    const tripKey = getSelectedTripType();
    if (!tripKey) {
      e.preventDefault();
      alert('Please select a Trip Type.');
      return;
    }

    const anyCat = document.querySelector('.cat-checkbox:checked');
    if (!anyCat) {
      e.preventDefault();
      alert('Please select at least one vehicle category.');
      return;
    }

    $('submitBtn').disabled = true;
    $('btnText').style.display = 'none';
    $('btnLoader').style.display = 'inline-block';
    cls($('pageOverlay'), false, 'd-none');
  });

  // ============================================================
  // RESET OTP STATE
  // ============================================================
  function resetOtpState() {
    verified = false;
    $('otpCode').value = '';
    $('sendOtpBtn').disabled = false;
    $('verifyOtpBtn').disabled = false;
    $('verifyOtpBtn').innerHTML = '<i class="bi bi-check-circle me-1"></i><span>Verify</span>';
    cls($('otpSection'), false, 'on');
    $('submitBtn').disabled = true;
    clearMsg($('otpError'));
    clearMsg($('otpSuccess'));
  }

});
</script>
</body>
</html>
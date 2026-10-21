<?php include('./config/db.php');
$serverNow = date('Y-m-d H:i'); // kept for reference, no longer used by JS
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0B2540">
<title>Vehicle Application — ICMR-NIHR</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&family=Noto+Sans+Devanagari:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<!-- Shared theme (must load after the libraries above) -->
<link rel="stylesheet" href="form.css">
</head>
<body>
<?php include('./header.php'); ?>

<!-- PORTAL SWITCHER -->
<div class="portal-switch">
  <div class="portal-switch-inner">
    <nav class="portal-tabs" role="tablist" aria-label="Application portal">
      <a href="application_form.php" class="portal-tab active" role="tab" aria-selected="true">
        <i class="bi bi-car-front-fill"></i><span>Institutional Vehicle</span>
      </a>
      <a href="hired_application_form.php" class="portal-tab" role="tab" aria-selected="false">
        <i class="bi bi-truck"></i><span>Hired Vehicle</span>
      </a>
    </nav>
  </div>
</div>

<!-- HERO -->
<header class="ph">
  <div class="container">
    <span class="ph-badge">ICMR-NIHR Jodhpur</span>
    <div class="ph-icon"><i class="bi bi-car-front-fill"></i></div>
    <h1>Institutional Vehicle Booking Request</h1>
    <p>Fill in all required details to submit your transport request</p>
  </div>
</header>

<main class="container app-container pb-5">
  <div class="fc">

    <!-- Progress -->
    <div class="fps">
      <?php foreach([['1','Applicant','आवेदक'],['2','Co-Travelers','सह-यात्री'],['3','Tour Order','यात्रा आदेश'],['4','Contact','संपर्क']] as [$n,$l,$hl]): ?>
      <div class="ps on">
        <div class="psd"><?= $n ?></div>
        <span class="psl"><?= $l ?><span class="hi"><?= $hl ?></span></span>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="fb fb-pad pt-4">
    <form id="appForm" method="POST" action="submit_application.php" enctype="multipart/form-data">

      <!-- §1 Applicant -->
      <section class="fsec">
        <div class="sh">
          <div class="si"><i class="bi bi-person-fill"></i></div>
          <div><h5>Applicant Details</h5><small class="hi">आवेदक विवरण — Personal and travel information</small></div>
          <div class="sn">1</div>
        </div>
        <div class="row g-3">
          <div class="col-sm-6 col-md-4">
            <label class="form-label">Booking For <span class="rd"></span><span class="hi">बुकिंग हेतु</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
              <select name="booking_for" id="booking_for" class="form-select" required>
                <option value="" disabled selected>Select</option>
                <option value="Self">Self / स्वयं</option><option value="Someone">Someone / अन्य व्यक्ति</option>
              </select>
            </div>
          </div>
          <div class="col-sm-6 col-md-4">
            <label class="form-label">Staff Type <span class="rd"></span><span class="hi">कर्मचारी प्रकार</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-briefcase"></i></span>
              <select name="staff_type" id="staff_type" class="form-select" required>
                <option value="" disabled selected>Select Type</option>
                <option value="Permanent" id="opt_permanent">Permanent / स्थायी</option>
                <option value="Temporary">Temporary / अस्थायी</option>
              </select>
            </div>
            <div id="staffTypeNote" class="msg fs" style="background:transparent;color:var(--tm);padding:6px 0 0"></div>
          </div>
          <div class="col-sm-6 col-md-4">
            <label class="form-label">Vehicle Required <span class="rd"></span><span class="hi">वाहन आवश्यक</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-truck"></i></span>
              <select name="vehicle_required" class="form-select" required>
                <option value="Car" selected>🚗 Car / कार</option>
                <option value="Bus">🚌 Bus / बस</option>
              </select>
            </div>
          </div>
          <div class="col-sm-6">
            <label class="form-label">Applicant Name <span class="rd"></span><span class="hi">आवेदक का नाम</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-person"></i></span>
              <input type="text" name="applicant_name" class="form-control" placeholder="Full Name" required>
            </div>
          </div>
          <div class="col-sm-6" id="op_div" style="display:none">
            <label class="form-label">Other Person's Name <span class="rd"></span><span class="hi">अन्य व्यक्ति का नाम</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-person-lines-fill"></i></span>
              <input type="text" name="other_person_name" id="other_person_name" class="form-control" placeholder="Enter Person's Name">
            </div>
          </div>
          <div class="col-sm-6">
            <label class="form-label">Source <span class="rd"></span><span class="hi">स्रोत</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-geo"></i></span>
              <select name="source_type" id="source_type" class="form-select" required>
                <option value="">Select Source</option>
                <option value="ICMR">ICMR-NIHR Jodhpur</option>
                <option value="Other">Enter Place / अन्य स्थान</option>
              </select>
            </div>
          </div>
          <div class="col-sm-6" id="cs_div" style="display:none">
            <label class="form-label">Source Place <span class="rd"></span><span class="hi">स्रोत स्थान</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-pencil"></i></span>
              <input type="text" name="custom_source" id="custom_source" class="form-control" placeholder="Type source location">
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label">From Date &amp; Time <span class="rd"></span><span class="hi">प्रस्थान तिथि व समय</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
              <input type="text" id="dt_from" name="datetime_from" class="form-control" placeholder="dd/mm/yyyy HH:MM" required>
            </div>
            <div class="nw mt-2">
              <i class="bi bi-exclamation-triangle-fill"></i>
              <div class="nw-body">After approval, if the journey does not start within <strong>30 minutes</strong> of the scheduled From Date &amp; Time, the vehicle request will be <strong>automatically rejected</strong>.
              <span class="hi">अनुमोदन के बाद, यदि निर्धारित तिथि एवं समय से 30 मिनट के भीतर यात्रा प्रारंभ नहीं होती है, तो वाहन अनुरोध स्वतः अस्वीकृत कर दिया जाएगा।</span></div>
            </div>
            <small class="text-muted d-block">Only today's and tomorrow's date/time can be selected. <span class="hi">केवल आज व कल की तिथि/समय चुनी जा सकती है।</span></small>
          </div>
          <div class="col-md-6">
            <label class="form-label">To Date &amp; Time <span class="rd"></span><span class="hi">वापसी तिथि व समय</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-calendar-check"></i></span>
              <input type="text" id="dt_to" name="datetime_to" class="form-control" placeholder="dd/mm/yyyy HH:MM" required>
            </div>
          </div>

          <div class="col-lg-5 field-fill">
            <label class="form-label">Place of Visit<span class="hi">भ्रमण स्थान</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
              <input type="text" name="place_of_visit" class="form-control" placeholder="Destination / Place of Visit">
            </div>
          </div>
          <div class="col-lg-7 field-fill">
            <label class="form-label">Purpose of Visit<span class="hi">यात्रा का उद्देश्य</span></label>
            <div class="input-group">
              <span class="input-group-text" style="align-self:flex-start;padding-top:12px"><i class="bi bi-chat-left-text"></i></span>
              <textarea name="purpose" class="form-control" rows="1" placeholder="Briefly describe the purpose of this trip…"></textarea>
            </div>
          </div>
        </div>
      </section>

      <!-- §2 Co-Travelers -->
      <section class="fsec">
        <div class="sh">
          <div class="si"><i class="bi bi-people-fill"></i></div>
          <div><h5>Co-Travelers</h5><small class="hi">सह-यात्री — Up to 6 additional passengers</small></div>
          <div class="sn">2</div>
        </div>
        <div class="row g-3">
          <?php for($i=1;$i<=6;$i++): ?>
          <div class="col-sm-6 col-lg-4">
            <div class="ct">
              <div class="ctn"><?= $i ?></div>
              <input type="text" name="cotraveler<?= $i ?>" placeholder="Co-Traveler <?= $i ?> name">
            </div>
          </div>
          <?php endfor; ?>
        </div>
      </section>

      <!-- §3 Tour Approval -->
      <section class="fsec">
        <div class="sh">
          <div class="si"><i class="bi bi-file-earmark-check"></i></div>
          <div><h5>Tour Approval Order</h5><small class="hi">यात्रा स्वीकृति आदेश — Required for destinations outside Jodhpur</small></div>
          <div class="sn">3</div>
        </div>
        <div class="tc">
          <div class="nw">
            <i class="bi bi-exclamation-circle-fill"></i>
            <div class="nw-body">For locations <strong>outside Jodhpur city</strong>, uploading the tour order is mandatory. Requests without it will not be processed.
            <span class="hi">जोधपुर शहर के बाहर के स्थानों के लिए यात्रा आदेश अपलोड करना अनिवार्य है।</span></div>
          </div>
          <div class="form-check form-switch d-flex align-items-center gap-3">
            <input class="form-check-input" type="checkbox" id="tourApproved" name="tour_approved" value="1" style="cursor:pointer;width:44px;height:22px;flex-shrink:0">
            <label class="form-check-label fw-semibold" for="tourApproved" style="cursor:pointer">Tour has been approved <span class="hi">यात्रा स्वीकृत हो चुकी है</span></label>
          </div>
          <div id="tourUpload" style="display:none;margin-top:16px">
            <label class="uz d-block" for="tourFile">
              <i class="bi bi-cloud-arrow-up"></i>
              <p class="fw-semibold">Click or drag &amp; drop your PDF here</p>
              <small>Accepted format: PDF only</small>
              <input type="file" name="tour_order_pdf" id="tourFile" hidden accept="application/pdf">
            </label>
            <div class="text-center mt-3">
              <button type="button" id="previewBtn" class="btn btn-sm btn-outline-primary d-none"><i class="bi bi-eye me-1"></i> Preview PDF</button>
            </div>
          </div>
        </div>
      </section>

      <!-- §4 Contact -->
      <section class="fsec">
        <div class="sh">
          <div class="si"><i class="bi bi-telephone-fill"></i></div>
          <div><h5>Contact Details</h5><small class="hi">संपर्क विवरण — Mobile &amp; verified email required</small></div>
          <div class="sn">4</div>
        </div>
        <div class="row g-3 mb-2">
          <div class="col-md-4">
            <label class="form-label">Mobile Number <span class="rd"></span><span class="hi">मोबाइल नंबर</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-phone"></i></span>
              <input type="text" name="mobile" class="form-control" placeholder="10-digit mobile number" required maxlength="10" inputmode="numeric">
            </div>
          </div>
          <div class="col-md-8">
            <label class="form-label">Email Address <span class="rd"></span><span class="hi">ईमेल पता</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-envelope"></i></span>
              <input type="email" name="email" id="email" class="form-control" placeholder="your@email.com" required>
              <button type="button" id="sendOtpBtn" class="btn btn-p"><i class="bi bi-send me-1"></i><span>Send OTP</span></button>
            </div>
            <div id="emailError" class="msg fe"></div>
          </div>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-12">
            <div class="nw mb-0">
              <i class="bi bi-exclamation-triangle-fill"></i>
              <div class="nw-body"><strong>Permanent staff</strong> should use their official <strong>@icmr.gov.in</strong> address. If unavailable, a personal <strong>Gmail</strong> ID may be used.
              <span class="hi">स्थायी कर्मचारी कृपया अपना आधिकारिक ईमेल उपयोग करें।</span></div>
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
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Forward To <span class="rd"></span><span class="hi">अग्रेषित करें</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-person-check"></i></span>
              <select name="forwarded_to" id="forwarded_to" class="form-select" required>
                <option value="">Select Staff Type first</option>
              </select>
            </div>
          </div>
        </div>
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

<!-- Journey loading overlay -->
<div id="pageOverlay" class="position-fixed top-0 start-0 w-100 h-100 d-none" style="z-index:9998">
  <div class="journey-box">
    <div class="road-track"><span class="road-car"><i class="bi bi-car-front-fill"></i></span></div>
    <h6>Your request is on its way</h6>
    <span class="hi">आपका अनुरोध भेजा जा रहा है</span>
    <p class="jsub">Please wait, submitting application…</p>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr" defer></script>
<script>
'use strict';
const $ = id => document.getElementById(id);
const cls = (el, add, c) => el.classList[add ? 'add' : 'remove'](c);
const setMsg = (el, html) => { el.innerHTML = html; cls(el, true, 'on'); };
const clearMsg = el => { cls(el, false, 'on'); el.innerHTML = ''; };

let verified = false, staffType = '';

document.addEventListener('DOMContentLoaded', () => {

  // ══════════════════════════════════════════════════════════
  // USE SYSTEM (BROWSER) TIME — not server time
  // ══════════════════════════════════════════════════════════
  const now = new Date();
  const nowPlus30 = new Date(now.getTime() + 30 * 60 * 1000);

  const tomorrowEnd = new Date(now);
  tomorrowEnd.setDate(tomorrowEnd.getDate() + 1);
  tomorrowEnd.setHours(23, 59, 0, 0);

  let ep;
  const sp = flatpickr('#dt_from', {
    enableTime:true, altInput:true, altFormat:'d/m/Y H:i',
    dateFormat:'Y-m-d H:i:S', time_24hr:true,
    disableMobile: true,
    defaultDate: nowPlus30,
    minDate: 'today',
    maxDate: tomorrowEnd,
    onReady:(_,__,i)=>setMinFrom(i),
    onChange:(d,_,i)=>{
      setMinFrom(i);
      if (d[0] && ep) {
        ep.set('minDate', d[0]);
        if (ep.selectedDates[0] && ep.selectedDates[0] < d[0]) ep.clear();
      }
    }
  });

  ep = flatpickr('#dt_to', {
    enableTime:true, altInput:true, altFormat:'d/m/Y H:i',
    dateFormat:'Y-m-d H:i:S', time_24hr:true,
    disableMobile: true,
    minDate: nowPlus30,
    defaultDate: nowPlus30,
    onReady:(_,__,i)=>setMinTo(i),
    onChange:(d,_,i)=>setMinTo(i)
  });

  function setMinFrom(i){
    const s = i.selectedDates[0];
    if(!s) return;
    const sameDay = s.toDateString() === now.toDateString();
    i.set('minTime', sameDay ? `${pad(now.getHours())}:${pad(now.getMinutes())}` : '00:00');
  }

  function setMinTo(i){
    const s = i.selectedDates[0];
    if(!s) return;
    const sameDay = s.toDateString() === now.toDateString();
    i.set('minTime', sameDay ? `${pad(now.getHours())}:${pad(now.getMinutes())}` : '00:00');
  }

  function pad(n){ return String(n).padStart(2,'0'); }

  $('staff_type').addEventListener('change', function(){
    staffType = this.value;
    const ef = $('email');
    if(staffType === 'Permanent'){
      ef.addEventListener('blur', valEmail);
      if(ef.value) valEmail();
    } else {
      ef.removeEventListener('blur', valEmail);
      clearMsg($('emailError'));
    }
    resetOtp();
    const x = new XMLHttpRequest();
    x.open('POST', './admin/get_users.php', true);
    x.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    x.onload = () => $('forwarded_to').innerHTML = x.responseText;
    x.send('staff_type=' + staffType);
  });

  $('booking_for').addEventListener('change', function(){
    const show = this.value === 'Someone';
    $('op_div').style.display = show ? 'block' : 'none';
    $('other_person_name').required = show;
    if(!show) $('other_person_name').value = '';

    const permOpt = $('opt_permanent');
    const st = $('staff_type');
    const note = $('staffTypeNote');
    if(show){
      permOpt.disabled = true;
      if(st.value === 'Permanent' || !st.value){
        st.value = 'Temporary';
        st.dispatchEvent(new Event('change'));
      }
      setMsg(note, '<i class="bi bi-info-circle"></i> Only Temporary staff type is available when booking for someone else. <span class="hi">किसी अन्य व्यक्ति हेतु बुकिंग पर केवल अस्थायी विकल्प उपलब्ध है।</span>');
    } else {
      permOpt.disabled = false;
      clearMsg(note);
    }
  });

  $('source_type').addEventListener('change', function(){
    const show = this.value === 'Other';
    $('cs_div').style.display = show ? 'block' : 'none';
    $('custom_source').required = show;
  });

  $('tourApproved').addEventListener('change', function(){
    $('tourUpload').style.display = this.checked ? 'block' : 'none';
    $('tourFile').required = this.checked;
    if(!this.checked){ $('tourFile').value=''; cls($('previewBtn'), true, 'd-none'); }
  });
  $('tourFile').addEventListener('change', function(){
    if(this.files.length) cls($('previewBtn'), false, 'd-none');
  });
  $('previewBtn').addEventListener('click', () => {
    const f = $('tourFile').files[0];
    if(f){ $('pdfFrame').src = URL.createObjectURL(f); new bootstrap.Modal($('pdfModal')).show(); }
  });

  $('sendOtpBtn').addEventListener('click', async function(){
    const email = $('email').value.trim(), st = $('staff_type').value;
    if(!email){ alert('Please enter your email address first.'); return; }
    if(st === 'Permanent' && !isValidEmail(email)){
      alert('Permanent staff must use @icmr.gov.in or a personal Gmail address.'); return;
    }
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span><span>Sending…</span>';
    try{
      const r = await fetch('send_otp.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({email, staff_type:st}) });
      const d = await r.json();
      if(d.success){ cls($('otpSection'), true, 'on'); alert('OTP sent! Please check your inbox.'); $('otpCode').focus(); }
      else alert(d.message || 'Failed to send OTP. Please try again.');
    }catch{ alert('Error sending OTP. Please try again.'); }
    finally{ this.disabled=false; this.innerHTML='<i class="bi bi-send me-1"></i><span>Send OTP</span>'; }
  });

  $('verifyOtpBtn').addEventListener('click', async function(){
    const otp = $('otpCode').value, ee = $('otpError'), es = $('otpSuccess');
    if(otp.length !== 4){ setMsg(ee, '<i class="bi bi-x-circle"></i> Please enter the 4-digit OTP.'); return; }
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span><span>Verifying…</span>';
    try{
      const r = await fetch('verify_otp.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({email:$('email').value, otp}) });
      const d = await r.json();
      if(d.success){
        verified = true; clearMsg(ee); setMsg(es, '<i class="bi bi-check-circle-fill"></i> Email verified successfully!');
        $('email').readOnly = true; $('sendOtpBtn').disabled = true;
        this.disabled = true; this.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i><span>Verified</span>';
        $('submitBtn').disabled = false;
        setTimeout(() => cls($('otpSection'), false, 'on'), 2000);
      } else {
        setMsg(ee, '<i class="bi bi-x-circle"></i> ' + (d.message || 'Invalid OTP. Please try again.'));
        this.disabled = false; this.innerHTML = '<i class="bi bi-check-circle me-1"></i><span>Verify</span>';
      }
    }catch{
      setMsg(ee, '<i class="bi bi-x-circle"></i> Error verifying OTP. Please try again.');
      this.disabled = false; this.innerHTML = '<i class="bi bi-check-circle me-1"></i><span>Verify</span>';
    }
  });

  $('appForm').addEventListener('submit', function(e){
    if(!verified){ e.preventDefault(); alert('Please verify your email address with OTP before submitting.'); return; }
    $('submitBtn').disabled = true;
    $('btnText').style.display = 'none';
    $('btnLoader').style.display = 'inline-block';
    cls($('pageOverlay'), false, 'd-none');
  });

});

function valEmail(){
  const v = $('email').value.trim().toLowerCase(), ee = $('emailError');
  const ok = !v || isValidEmail(v);
  $('sendOtpBtn').disabled = !ok;
  ok ? clearMsg(ee) : setMsg(ee, '<i class="bi bi-x-circle"></i> Permanent staff must use @icmr.gov.in or Gmail address.');
  return ok;
}
function isValidEmail(e){ return e.endsWith('@icmr.gov.in') || e.endsWith('@gmail.com'); }
function resetOtp(){
  verified = false;
  $('email').readOnly = false; $('sendOtpBtn').disabled = false;
  cls($('otpSection'), false, 'on'); $('otpCode').value = '';
  clearMsg($('otpError')); clearMsg($('otpSuccess'));
  $('submitBtn').disabled = true;
}
</script>
</body>
</html>
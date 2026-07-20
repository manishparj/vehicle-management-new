<?php include('./config/db.php');
$serverNow = date('Y-m-d H:i');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Vehicle Application — ICMR-NIHR</title>
<!-- Fonts: display=swap for perf -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
<!-- Non-blocking CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" media="print" onload="this.media='all'">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" media="print" onload="this.media='all'">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" media="print" onload="this.media='all'">
  <link href="./index.css" rel="stylesheet">
<noscript>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</noscript>
</head>
<body>
<?php include('./header.php'); ?>

<div class="ph">
  <div class="container">
    <span class="ph-badge">ICMR-NIHR Jodhpur</span>
    <div class="ph-icon"><i class="bi bi-car-front-fill"></i></div>
    <h1 style="font-family:'Plus Jakarta Sans',sans-serif">Vehicle Booking Request</h1>
    <p>Fill in all required details to submit your transport request</p>
  </div>
</div>

<div class="container pb-5">
  <div class="row justify-content-center">
    <div class="col-xl-8 col-lg-10 col-md-12">
      <div class="fc">

        <!-- Progress -->
        <div class="fps">
          <?php foreach([['1','Applicant'],['2','Co-Travelers'],['3','Contact'],['4','Tour Order']] as [$n,$l]): ?>
          <div class="ps on">
            <div class="psd"><?= $n ?></div>
            <span class="psl"><?= $l ?></span>
          </div>
          <?php endforeach; ?>
        </div>

        <div class="fb p-4 p-md-5 pt-4">
        <form id="appForm" method="POST" action="submit_application.php" enctype="multipart/form-data">

          <!-- §1 Applicant -->
          <div class="sh">
            <div class="si"><i class="bi bi-person-fill"></i></div>
            <div><h5>Applicant Details</h5><small>Personal and travel information</small></div>
            <div class="sn">1</div>
          </div>
          <div class="row g-3 mb-4">
            <div class="col-sm-6 col-md-4">
              <label class="form-label">Booking For <span class="rd"></span></label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                <select name="booking_for" id="booking_for" class="form-select" required>
                  <option value="" disabled selected>Select</option>
                  <option>Self</option><option value="Someone">Someone</option>
                </select>
              </div>
            </div>
            <div class="col-sm-6 col-md-4">
              <label class="form-label">Staff Type <span class="rd"></span></label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-briefcase"></i></span>
                <select name="staff_type" id="staff_type" class="form-select" required>
                  <option value="" disabled selected>Select Type</option>
                  <option>Permanent</option><option>Temporary</option>
                </select>
              </div>
            </div>
            <div class="col-sm-6 col-md-4">
              <label class="form-label">Vehicle Required <span class="rd"></span></label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-truck"></i></span>
                <select name="vehicle_required" class="form-select" required>
                  <option value="" disabled selected>Select Vehicle</option>
                  <option value="Car">🚗 Car</option><option value="Bus">🚌 Bus</option>
                </select>
              </div>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Applicant Name <span class="rd"></span></label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <input type="text" name="applicant_name" class="form-control" placeholder="Full Name" required>
              </div>
            </div>
            <div class="col-sm-6" id="op_div" style="display:none">
              <label class="form-label">Other Person's Name <span class="rd"></span></label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person-lines-fill"></i></span>
                <input type="text" name="other_person_name" id="other_person_name" class="form-control" placeholder="Enter Person's Name">
              </div>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Source <span class="rd"></span></label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-geo"></i></span>
                <select name="source_type" id="source_type" class="form-select" required>
                  <option value="">Select Source</option>
                  <option value="ICMR">ICMR-NIHR Jodhpur</option>
                  <option value="Other">Enter Place</option>
                </select>
              </div>
            </div>
            <div class="col-sm-6" id="cs_div" style="display:none">
              <label class="form-label">Source Place <span class="rd"></span></label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-pencil"></i></span>
                <input type="text" name="custom_source" id="custom_source" class="form-control" placeholder="Type source location">
              </div>
            </div>
            <div class="col-sm-6">
              <label class="form-label">From Date & Time <span class="rd"></span></label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
                <input type="text" id="dt_from" name="datetime_from" class="form-control" placeholder="dd/mm/yyyy HH:MM" required>
              </div>
            </div>
            <div class="col-sm-6">
              <label class="form-label">To Date & Time <span class="rd"></span></label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-calendar-check"></i></span>
                <input type="text" id="dt_to" name="datetime_to" class="form-control" placeholder="dd/mm/yyyy HH:MM" required>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label">Place of Visit</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                <input type="text" name="place_of_visit" class="form-control" placeholder="Destination / Place of Visit">
              </div>
            </div>
            <div class="col-12">
              <label class="form-label">Purpose of Visit</label>
              <div class="input-group">
                <span class="input-group-text" style="align-self:flex-start;padding-top:12px"><i class="bi bi-chat-left-text"></i></span>
                <textarea name="purpose" class="form-control" rows="3" placeholder="Briefly describe the purpose of this trip…"></textarea>
              </div>
            </div>
          </div>

          <!-- §2 Co-Travelers -->
          <div class="sh">
            <div class="si"><i class="bi bi-people-fill"></i></div>
            <div><h5>Co-Travelers</h5><small>Up to 6 additional passengers</small></div>
            <div class="sn">2</div>
          </div>
          <div class="row g-3 mb-4">
            <?php for($i=1;$i<=6;$i++): ?>
            <div class="col-sm-6">
              <div class="ct">
                <div class="ctn"><?= $i ?></div>
                <input type="text" name="cotraveler<?= $i ?>" placeholder="Co-Traveler <?= $i ?> name">
              </div>
            </div>
            <?php endfor; ?>
          </div>

          <!-- §3 Contact -->
          <div class="sh">
            <div class="si"><i class="bi bi-telephone-fill"></i></div>
            <div><h5>Contact Details</h5><small>Mobile &amp; verified email required</small></div>
            <div class="sn">3</div>
          </div>
          <div class="row g-3 mb-4">
            <div class="col-sm-6">
              <label class="form-label">Mobile Number <span class="rd"></span></label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-phone"></i></span>
                <input type="text" name="mobile" class="form-control" placeholder="10-digit mobile number" required>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label">Email Address <span class="rd"></span></label>
              <div class="nw"><i class="bi bi-exclamation-triangle-fill"></i><div><strong>Permanent staff</strong> should use their official <strong>@icmr.gov.in</strong> address. If unavailable, a personal <strong>Gmail</strong> ID may be used.</div></div>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input type="email" name="email" id="email" class="form-control" placeholder="your@email.com" required>
                <button type="button" id="sendOtpBtn" class="btn btn-p"><i class="bi bi-send me-1"></i> Send OTP</button>
              </div>
              <div id="emailError" class="fe"></div>
              <div class="nw mt-2"><i class="bi bi-info-circle-fill"></i><div>If booking for someone else (Permanent staff), enter <strong>that staff member's own email</strong>. Requests with another person's email may be rejected.</div></div>
              <div class="otps" id="otpSection">
                <div class="otpl"><i class="bi bi-shield-lock"></i> Enter the 4-digit OTP sent to your inbox</div>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-key"></i></span>
                  <input type="text" id="otpCode" class="form-control" placeholder="_ _ _ _" maxlength="4" pattern="\d{4}" style="letter-spacing:.25em;font-weight:700;font-size:1.1rem">
                  <button type="button" id="verifyOtpBtn" class="btn btn-ok"><i class="bi bi-check-circle me-1"></i> Verify</button>
                </div>
                <div id="otpError" class="fe"></div>
                <div id="otpSuccess" class="fs"></div>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label">Forward To <span class="rd"></span></label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person-check"></i></span>
                <select name="forwarded_to" id="forwarded_to" class="form-select" required>
                  <option value="">Select Staff Type first</option>
                </select>
              </div>
            </div>
          </div>

          <!-- §4 Tour Approval -->
          <div class="sh">
            <div class="si"><i class="bi bi-file-earmark-check"></i></div>
            <div><h5>Tour Approval Order</h5><small>Required for destinations outside Jodhpur</small></div>
            <div class="sn">4</div>
          </div>
          <div class="tc mb-4">
            <div class="nw"><i class="bi bi-exclamation-circle-fill"></i><span>For locations <strong>outside Jodhpur city</strong>, uploading the tour order is mandatory. Requests without it will not be processed.</span></div>
            <div class="form-check form-switch d-flex align-items-center gap-3">
              <input class="form-check-input" type="checkbox" id="tourApproved" name="tour_approved" value="1" style="cursor:pointer;width:44px;height:22px">
              <label class="form-check-label fw-semibold" for="tourApproved" style="cursor:pointer">Tour has been approved</label>
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

          <button id="submitBtn" class="btn-sub" type="submit" disabled>
            <span id="btnText"><i class="bi bi-send-fill me-2"></i>Submit Application</span>
            <span id="btnLoader" style="display:none"><span class="spinner-border spinner-border-sm me-2"></span>Submitting…</span>
          </button>
          <p class="text-center mt-3 mb-0" style="font-size:.78rem;color:var(--tm)"><i class="bi bi-lock-fill me-1"></i>Your data is securely handled by ICMR-NIHR Jodhpur</p>

        </form>
        </div>
      </div>
    </div>
  </div>
</div>

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

<div id="pageOverlay" class="position-fixed top-0 start-0 w-100 h-100 bg-secondary bg-opacity-50 d-none" style="z-index:9998;backdrop-filter:blur(3px)"></div>

<!-- Scripts deferred -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr" defer></script>
<script>
'use strict';
// ── Cached refs ──
const $ = id => document.getElementById(id);
const cls = (el, add, cls) => el.classList[add ? 'add' : 'remove'](cls);

let verified = false, staffType = '';
const serverNow = '<?= $serverNow ?>';

// ── DOMContentLoaded ──
document.addEventListener('DOMContentLoaded', () => {

  // Flatpickr
  let ep;
  const sp = flatpickr('#dt_from', {
    enableTime:true, altInput:true, altFormat:'d/m/Y H:i',
    dateFormat:'Y-m-d H:i:S', time_24hr:true,
    defaultDate:serverNow, minDate:'today',
    onReady:(_,__,i)=>setMin(i),
    onChange:(d,_,i)=>{
      setMin(i);
      if(d[0]){ep.set('minDate',d[0]);if(ep.selectedDates[0]<=d[0])ep.clear();}
    }
  });
  ep = flatpickr('#dt_to',{enableTime:true,altInput:true,altFormat:'d/m/Y H:i',dateFormat:'Y-m-d H:i:S',time_24hr:true,minDate:'today'});

  function setMin(i){
    const now=new Date(serverNow),s=i.selectedDates[0];
    if(!s)return;
    i.set('minTime',s.toDateString()===now.toDateString()?`${now.getHours()}:${now.getMinutes()}`:'00:00');
  }

  // Staff Type
  $('staff_type').addEventListener('change', function(){
    staffType = this.value;
    const ef=$('email'), ee=$('emailError');
    if(staffType==='Permanent'){
      ef.addEventListener('blur',valEmail);
      if(ef.value)valEmail();
    } else {
      ef.removeEventListener('blur',valEmail);
      cls(ee,false,'on');
    }
    resetOtp();
    const x=new XMLHttpRequest();
    x.open('POST','./admin/get_users.php',true);
    x.setRequestHeader('Content-type','application/x-www-form-urlencoded');
    x.onload=()=>$('forwarded_to').innerHTML=x.responseText;
    x.send('staff_type='+staffType);
  });

  // Booking For
  $('booking_for').addEventListener('change',function(){
    const show=this.value==='Someone';
    $('op_div').style.display=show?'block':'none';
    $('other_person_name').required=show;
    if(!show)$('other_person_name').value='';
  });

  // Source Type
  $('source_type').addEventListener('change',function(){
    const show=this.value==='Other';
    $('cs_div').style.display=show?'block':'none';
    $('custom_source').required=show;
  });

  // Tour toggle
  $('tourApproved').addEventListener('change',function(){
    $('tourUpload').style.display=this.checked?'block':'none';
    $('tourFile').required=this.checked;
    if(!this.checked){$('tourFile').value='';cls($('previewBtn'),true,'d-none');}
  });
  $('tourFile').addEventListener('change',function(){if(this.files.length)cls($('previewBtn'),false,'d-none');});
  $('previewBtn').addEventListener('click',()=>{
    const f=$('tourFile').files[0];
    if(f){$('pdfFrame').src=URL.createObjectURL(f);new bootstrap.Modal($('pdfModal')).show();}
  });

  // Send OTP
  $('sendOtpBtn').addEventListener('click', async function(){
    const email=$('email').value.trim(), st=$('staff_type').value;
    if(!email){alert('Please enter your email address first.');return;}
    if(st==='Permanent'&&!isValidEmail(email)){
      alert('Permanent staff must use @icmr.gov.in or a personal Gmail address.');return;
    }
    this.disabled=true;
    this.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Sending…';
    try{
      const r=await fetch('send_otp.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({email,staff_type:st})});
      const d=await r.json();
      if(d.success){cls($('otpSection'),true,'on');alert('OTP sent! Please check your inbox.');$('otpCode').focus();}
      else alert(d.message||'Failed to send OTP. Please try again.');
    }catch{alert('Error sending OTP. Please try again.');}
    finally{this.disabled=false;this.innerHTML='<i class="bi bi-send me-1"></i> Send OTP';}
  });

  // Verify OTP
  $('verifyOtpBtn').addEventListener('click', async function(){
    const otp=$('otpCode').value, ee=$('otpError'), es=$('otpSuccess');
    if(otp.length!==4){setMsg(ee,'<i class="bi bi-x-circle"></i> Please enter the 4-digit OTP.');return;}
    this.disabled=true;
    this.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Verifying…';
    try{
      const r=await fetch('verify_otp.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({email:$('email').value,otp})});
      const d=await r.json();
      if(d.success){
        verified=true;cls(ee,false,'on');setMsg(es,'<i class="bi bi-check-circle-fill"></i> Email verified successfully!',true);
        $('email').readOnly=true;$('sendOtpBtn').disabled=true;
        this.disabled=true;this.innerHTML='<i class="bi bi-check-circle-fill me-1"></i>Verified';
        $('submitBtn').disabled=false;
        setTimeout(()=>cls($('otpSection'),false,'on'),2000);
      }else{
        setMsg(ee,'<i class="bi bi-x-circle"></i> '+(d.message||'Invalid OTP. Please try again.'));
        this.disabled=false;this.innerHTML='<i class="bi bi-check-circle me-1"></i> Verify';
      }
    }catch{
      setMsg(ee,'<i class="bi bi-x-circle"></i> Error verifying OTP. Please try again.');
      this.disabled=false;this.innerHTML='<i class="bi bi-check-circle me-1"></i> Verify';
    }
  });

  // Submit guard + overlay
  $('appForm').addEventListener('submit',function(e){
    if(!verified){e.preventDefault();alert('Please verify your email address with OTP before submitting.');return;}
    $('submitBtn').disabled=true;
    $('btnText').style.display='none';
    $('btnLoader').style.display='inline-block';
    $('pageOverlay').classList.remove('d-none');
  });

});// end DOMContentLoaded

// ── Helpers ──
function valEmail(){
  const v=$('email').value.trim().toLowerCase(), ee=$('emailError');
  const ok=!v||isValidEmail(v);
  $('sendOtpBtn').disabled=!ok;
  ok?cls(ee,false,'on'):setMsg(ee,'<i class="bi bi-x-circle"></i> Permanent staff must use @icmr.gov.in or Gmail address.');
  return ok;
}
function isValidEmail(e){return e.endsWith('@icmr.gov.in')||e.endsWith('@gmail.com');}
function setMsg(el,html,isSuccess=false){el.innerHTML=html;cls(el,true,'on');}
function resetOtp(){
  verified=false;
  $('email').readOnly=false;$('sendOtpBtn').disabled=false;
  cls($('otpSection'),false,'on');$('otpCode').value='';
  cls($('otpError'),false,'on');cls($('otpSuccess'),false,'on');
  $('submitBtn').disabled=true;
}
</script>
</body>
</html>
<?php include('./config/db.php');
$serverNow = date('Y-m-d H:i'); // kept for reference, no longer used by JS
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Vehicle Application — ICMR-NIHR</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&family=Noto+Sans+Devanagari:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" media="print" onload="this.media='all'">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" media="print" onload="this.media='all'">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" media="print" onload="this.media='all'">
<noscript>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</noscript>
<style>
/* =====================================================================
   DESIGN TOKENS
   ===================================================================== */
:root{
  --navy:#0F2A43;
  --navy-soft:#274A63;
  --teal:#0E7C86;
  --teal-dark:#0A5F67;
  --teal-tint:#E5F3F3;
  --paper:#F5F8F9;
  --card:#FFFFFF;
  --line:#E1E8EC;
  --amber:#C9762A;
  --amber-tint:#FBF0E4;
  --amber-text:#7A4A17;
  --amber-line:#EFD9BC;
  --success:#1F8A5F;
  --success-tint:#EAF7F0;
  --danger:#C4433B;
  --danger-tint:#FBEAE9;
  --tm:#5B6B7A;
  --radius-lg:16px;
  --radius-md:12px;
  --radius-sm:8px;
  --shadow-card:0 1px 2px rgba(15,42,67,.06),0 12px 32px -14px rgba(15,42,67,.22);
  --shadow-pop:0 20px 50px -12px rgba(0,0,0,.4);
  /* fluid spacing scale */
  --sp-1:.375rem;
  --sp-2:.625rem;
  --sp-3:1rem;
  --sp-4:1.5rem;
  --sp-5:2.25rem;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{
  font-family:'Inter',sans-serif;
  background:var(--paper);
  color:var(--navy);
  margin:0;
  -webkit-font-smoothing:antialiased;
  text-rendering:optimizeLegibility;
}
.hi{font-family:'Noto Sans Devanagari','Inter',sans-serif;font-weight:500;color:var(--tm);font-size:.86em}
:focus-visible{outline:2px solid var(--teal);outline-offset:2px}

/* =====================================================================
   PAGE HEADER / HERO
   ===================================================================== */
.ph{
  background:linear-gradient(155deg,var(--navy) 0%,var(--teal-dark) 100%);
  color:#fff;
  padding:clamp(36px,6vw,52px) 0 clamp(64px,10vw,84px);
  text-align:center;
  position:relative;
  overflow:hidden;
}
.ph::after{
  content:"";
  position:absolute;inset:auto 0 0 0;height:70px;
  background:repeating-linear-gradient(90deg,rgba(255,255,255,.35) 0 26px,transparent 26px 52px);
  opacity:.12;
}
.ph-badge{
  display:inline-block;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.28);
  padding:6px 16px;border-radius:999px;font-size:.78rem;letter-spacing:.02em;margin-bottom:18px;
}
.ph-icon{
  width:64px;height:64px;border-radius:18px;background:rgba(255,255,255,.14);
  display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:1.7rem;
}
.ph h1{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:clamp(1.4rem,3.4vw,2rem);margin:0 0 8px}
.ph p{color:rgba(255,255,255,.78);margin:0;font-size:1rem}

/* =====================================================================
   FORM CARD SHELL
   ===================================================================== */
.container{max-width:960px}
.fc{margin-top:clamp(-40px,-6vw,-56px);position:relative;z-index:2}
.fb{
  background:var(--card);
  border-radius:0 0 var(--radius-lg) var(--radius-lg);
  box-shadow:var(--shadow-card);
  border:1px solid var(--line);
  border-top:none;
}

/* Road-styled progress */
.fps{
  display:flex;justify-content:space-between;align-items:flex-start;
  background:var(--card);border-radius:var(--radius-lg) var(--radius-lg) 0 0;
  border:1px solid var(--line);border-bottom:none;
  padding:22px 28px 18px;position:relative;
}
.fps::before{
  content:"";position:absolute;top:37px;left:60px;right:60px;height:2px;
  background:repeating-linear-gradient(90deg,var(--line) 0 10px,transparent 10px 18px);
}
.ps{display:flex;flex-direction:column;align-items:center;gap:8px;position:relative;z-index:1;flex:1;min-width:0}
.psd{
  width:34px;height:34px;border-radius:50%;background:var(--card);border:2px solid var(--line);
  color:var(--tm);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.9rem;
  flex-shrink:0;
}
.ps.on .psd{background:var(--teal);border-color:var(--teal);color:#fff}
.psl{font-size:.76rem;font-weight:600;color:var(--tm);text-align:center;line-height:1.25}
.ps.on .psl{color:var(--navy)}
.psl .hi{display:block;font-size:.72rem;font-weight:500;margin-top:1px}

/* Section headers */
.sh{display:flex;align-items:center;gap:14px;margin-bottom:var(--sp-4);padding-bottom:var(--sp-3);border-bottom:1px solid var(--line)}
.si{
  width:42px;height:42px;border-radius:var(--radius-md);background:var(--teal-tint);color:var(--teal-dark);
  display:flex;align-items:center;justify-content:center;font-size:1.05rem;flex-shrink:0;
}
.sh h5{margin:0;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:1.05rem;color:var(--navy);line-height:1.3}
.sh small{color:var(--tm);font-size:.82rem;display:block;margin-top:2px}
.sn{
  margin-left:auto;width:28px;height:28px;border-radius:var(--radius-sm);background:var(--paper);border:1px solid var(--line);
  display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;color:var(--tm);flex-shrink:0;
}

/* =====================================================================
   FIELDS
   ===================================================================== */
.form-label{font-weight:600;font-size:.87rem;color:var(--navy);margin-bottom:6px;display:block}
.form-label .hi{display:block;font-weight:500;font-size:.78rem;color:var(--tm);margin-top:1px}
.rd{color:var(--danger);font-weight:700}
.rd::after{content:"*"}
.input-group-text{background:var(--paper);border-color:var(--line);color:var(--teal-dark)}
.form-control,.form-select{border-color:var(--line);padding:.6rem .8rem;font-size:.94rem}
.form-control:focus,.form-select:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(14,124,134,.14)}
.form-select:disabled,.form-select option:disabled{color:#a7b2ba}
textarea.form-control{resize:vertical;min-height:calc(1.5em * 3 + 1.2rem)}

/* Match textarea + single-line input heights when paired side-by-side */
.row-align-stretch{align-items:stretch}
.field-fill{height:100%;display:flex;flex-direction:column}
.field-fill .input-group{flex:1;align-items:stretch}
.field-fill textarea.form-control{height:100%}

/* Notice / message boxes — unified so warnings, errors and confirmations
   never collide with the icon or wrap awkwardly */
.nw{
  display:flex;gap:10px;align-items:flex-start;background:var(--amber-tint);border:1px solid var(--amber-line);
  color:var(--amber-text);border-radius:var(--radius-sm);padding:10px 14px;font-size:.83rem;
  line-height:1.5;margin-bottom:10px;
}
.nw i{flex:0 0 auto;margin-top:.2em;font-size:1em;line-height:1}
.nw .nw-body{min-width:0}
.nw .hi{display:block;color:var(--amber-text);opacity:.85;margin-top:2px}

.msg{
  display:none;align-items:flex-start;gap:8px;font-size:.82rem;margin-top:8px;
  border-radius:var(--radius-sm);padding:8px 12px;line-height:1.45;
}
.msg i{flex:0 0 auto;margin-top:.15em}
.msg.on{display:flex}
.fe{color:var(--danger);background:var(--danger-tint)}
.fs{color:var(--success);background:var(--success-tint)}

/* Co-traveler rows */
.ct{display:flex;align-items:center;gap:10px;border:1px solid var(--line);border-radius:var(--radius-sm);padding:8px 10px;background:var(--paper);height:100%}
.ctn{width:26px;height:26px;border-radius:50%;background:var(--teal);color:#fff;font-size:.78rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.ct input{border:none;background:transparent;flex:1;font-size:.92rem;outline:none;padding:4px 2px;min-width:0}

/* OTP */
.otps{max-height:0;overflow:hidden;opacity:0;transition:max-height .3s ease,opacity .3s ease,margin-top .3s ease;margin-top:0}
.otps.on{max-height:220px;opacity:1;margin-top:14px}
.otpl{font-size:.82rem;color:var(--tm);margin-bottom:8px;display:flex;align-items:center;gap:6px}
.btn-p{background:var(--teal);border-color:var(--teal);color:#fff;font-weight:600;white-space:nowrap}
.btn-p:hover{background:var(--teal-dark);border-color:var(--teal-dark);color:#fff}
.btn-p:disabled{opacity:.6}
.btn-ok{background:var(--success);border-color:var(--success);color:#fff;font-weight:600;white-space:nowrap}
.btn-ok:hover{background:#176e4c;border-color:#176e4c;color:#fff}

/* Tour approval */
.tc{border:1px solid var(--line);border-radius:var(--radius-md);padding:20px;background:var(--paper)}
.uz{border:2px dashed #C7D3DA;border-radius:var(--radius-md);padding:28px 16px;text-align:center;cursor:pointer;background:var(--card);transition:.2s}
.uz:hover{border-color:var(--teal);background:var(--teal-tint)}
.uz i{font-size:1.8rem;color:var(--teal-dark)}
.uz p{margin:8px 0 2px;color:var(--navy)}
.uz small{color:var(--tm)}

/* Submit button */
.btn-sub{
  width:100%;padding:14px;border:none;border-radius:var(--radius-md);
  background:linear-gradient(135deg,var(--teal),var(--teal-dark));
  color:#fff;font-weight:700;font-size:1rem;letter-spacing:.01em;transition:filter .2s,transform .2s;
}
.btn-sub:hover:not(:disabled){filter:brightness(1.06)}
.btn-sub:active:not(:disabled){transform:translateY(1px)}
.btn-sub:disabled{opacity:.55;cursor:not-allowed}

/* =====================================================================
   JOURNEY LOADING OVERLAY
   ===================================================================== */
#pageOverlay{background:rgba(15,42,67,.55);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;padding:16px}
.journey-box{background:var(--card);border-radius:18px;padding:34px 30px 26px;width:min(360px,92vw);text-align:center;box-shadow:var(--shadow-pop)}
.road-track{position:relative;height:46px;margin:10px 0 18px;border-radius:var(--radius-sm);background:linear-gradient(#3b4a55,#2b3740);overflow:hidden}
.road-track::before{
  content:"";position:absolute;top:50%;left:0;right:0;height:3px;transform:translateY(-50%);
  background:repeating-linear-gradient(90deg,#fff 0 18px,transparent 18px 34px);
  animation:roadmove .6s linear infinite;
}
@keyframes roadmove{from{background-position-x:0}to{background-position-x:-52px}}
.road-car{position:absolute;top:50%;left:8px;transform:translateY(-58%);font-size:1.5rem;color:#fff;animation:drivecar 1.4s ease-in-out infinite}
@keyframes drivecar{0%{left:6%}50%{left:70%}100%{left:6%}}
.journey-box h6{font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;color:var(--navy);margin:0 0 2px}
.journey-box .hi{font-size:.85rem}
.journey-box p.jsub{color:var(--tm);font-size:.82rem;margin:6px 0 0}

@media (prefers-reduced-motion:reduce){
  .road-car,.road-track::before{animation:none}
}

/* =====================================================================
   RESPONSIVE
   ===================================================================== */
@media (max-width:767px){
  .ph{text-align:center}
  .fc{margin-top:-40px}
  .fps{padding:16px 10px 12px;overflow-x:auto}
  .fps::before{left:40px;right:40px}
  .psl{font-size:.68rem}
  .psl .hi{font-size:.62rem}
  .psd{width:28px;height:28px;font-size:.78rem}
  .sh{gap:10px;margin-bottom:16px;padding-bottom:10px}
  .si{width:36px;height:36px;font-size:.9rem}
  .sh h5{font-size:.95rem}
  .sh small{font-size:.74rem}
  .p-md-5{padding:1.15rem !important}
  .input-group{flex-wrap:nowrap}
  #sendOtpBtn span,#verifyOtpBtn span{display:none}
  .btn-sub{font-size:.92rem;padding:12px}
  .field-fill{height:auto}
  textarea.form-control{min-height:calc(1.5em * 2 + 1.2rem)}
}
@media (max-width:420px){
  .input-group-text{padding:.5rem .55rem}
  #sendOtpBtn,#verifyOtpBtn{padding:.5rem .6rem;font-size:.82rem}
  .nw{padding:9px 11px;font-size:.79rem}
}
</style>
</head>
<body>
<?php include('./header.php'); ?>

<div class="ph">
  <div class="container">
    <span class="ph-badge">ICMR-NIHR Jodhpur</span>
    <div class="ph-icon"><i class="bi bi-car-front-fill"></i></div>
    <h1>Vehicle Booking Request</h1>
    <p>Fill in all required details to submit your transport request</p>
  </div>
</div>

<div class="container pb-5">
  <div class="row justify-content-center">
    <div class="col-12">
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

        <div class="fb p-4 p-md-5 pt-4">
        <form id="appForm" method="POST" action="submit_application.php" enctype="multipart/form-data">

          <!-- §1 Applicant -->
          <div class="sh">
            <div class="si"><i class="bi bi-person-fill"></i></div>
            <div><h5>Applicant Details</h5><small class="hi">आवेदक विवरण — Personal and travel information</small></div>
            <div class="sn">1</div>
          </div>
          <div class="row g-3 mb-4">
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
            <div class="col-sm-6">
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
            <div class="col-sm-6">
              <label class="form-label">To Date &amp; Time <span class="rd"></span><span class="hi">वापसी तिथि व समय</span></label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-calendar-check"></i></span>
                <input type="text" id="dt_to" name="datetime_to" class="form-control" placeholder="dd/mm/yyyy HH:MM" required>
              </div>
            </div>

            <!-- Place of Visit + Purpose of Visit share a row -->
            <div class="col-md-5 field-fill">
              <label class="form-label">Place of Visit<span class="hi">भ्रमण स्थान</span></label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                <input type="text" name="place_of_visit" class="form-control" placeholder="Destination / Place of Visit">
              </div>
            </div>
            <div class="col-md-7 field-fill">
              <label class="form-label">Purpose of Visit<span class="hi">यात्रा का उद्देश्य</span></label>
              <div class="input-group">
                <span class="input-group-text" style="align-self:flex-start;padding-top:12px"><i class="bi bi-chat-left-text"></i></span>
                <textarea name="purpose" class="form-control" rows="1" placeholder="Briefly describe the purpose of this trip…"></textarea>
              </div>
            </div>
          </div>

          <!-- §2 Co-Travelers -->
          <div class="sh">
            <div class="si"><i class="bi bi-people-fill"></i></div>
            <div><h5>Co-Travelers</h5><small class="hi">सह-यात्री — Up to 6 additional passengers</small></div>
            <div class="sn">2</div>
          </div>
          <div class="row g-3 mb-4">
            <?php for($i=1;$i<=6;$i++): ?>
            <div class="col-sm-6 col-lg-4">
              <div class="ct">
                <div class="ctn"><?= $i ?></div>
                <input type="text" name="cotraveler<?= $i ?>" placeholder="Co-Traveler <?= $i ?> name">
              </div>
            </div>
            <?php endfor; ?>
          </div>

          <!-- §3 Tour Approval -->
          <div class="sh">
            <div class="si"><i class="bi bi-file-earmark-check"></i></div>
            <div><h5>Tour Approval Order</h5><small class="hi">यात्रा स्वीकृति आदेश — Required for destinations outside Jodhpur</small></div>
            <div class="sn">3</div>
          </div>
          <div class="tc mb-4">
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

          <!-- §4 Contact -->
          <div class="sh">
            <div class="si"><i class="bi bi-telephone-fill"></i></div>
            <div><h5>Contact Details</h5><small class="hi">संपर्क विवरण — Mobile &amp; verified email required</small></div>
            <div class="sn">4</div>
          </div>
          <div class="row g-3 mb-2">
            <!-- Mobile Number + Email Address share a row -->
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
          <div class="row g-3 mb-4">
            <div class="col-12">
              <div class="nw mb-0">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div class="nw-body"><strong>Permanent staff</strong> should use their official <strong>@icmr.gov.in</strong> address. If unavailable, a personal <strong>Gmail</strong> ID may be used.
                <span class="hi">स्थायी कर्मचारी कृपया अपना आधिकारिक ईमेल उपयोग करें।</span></div>
              </div>
              <div class="otps" id="otpSection">
                <div class="otpl"><i class="bi bi-shield-lock"></i> Enter the 4-digit OTP sent to your inbox</div>
                <div class="input-group" style="max-width:360px">
                  <span class="input-group-text"><i class="bi bi-key"></i></span>
                  <input type="text" id="otpCode" class="form-control" placeholder="_ _ _ _" maxlength="4" pattern="\d{4}" style="letter-spacing:.25em;font-weight:700;font-size:1.1rem">
                  <button type="button" id="verifyOtpBtn" class="btn btn-ok"><i class="bi bi-check-circle me-1"></i><span>Verify</span></button>
                </div>
                <div id="otpError" class="msg fe"></div>
                <div id="otpSuccess" class="msg fs"></div>
              </div>
            </div>
          </div>
          <div class="row g-3 mb-4">
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

          <button id="submitBtn" class="btn-sub" type="submit" disabled>
            <span id="btnText"><i class="bi bi-send-fill me-2"></i>Submit Application <span class="hi" style="color:inherit;opacity:.85">/ आवेदन जमा करें</span></span>
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
  const now = new Date();                               // user's local system time
  const nowPlus30 = new Date(now.getTime() + 30 * 60 * 1000);

  // Upper bound = end of tomorrow (local)
  const tomorrowEnd = new Date(now);
  tomorrowEnd.setDate(tomorrowEnd.getDate() + 1);
  tomorrowEnd.setHours(23, 59, 0, 0);

  // ── From Date & Time: today & tomorrow, default = now + 30 min ──
  let ep; // forward declaration for To picker
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
      // Sync To picker minDate with From's chosen date
      if (d[0] && ep) {
        ep.set('minDate', d[0]);
        if (ep.selectedDates[0] && ep.selectedDates[0] < d[0]) ep.clear();
      }
    }
  });

  // ── To Date & Time: past date/time disabled ──
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

  // Staff Type
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

  // Booking For — "Someone" restricts Staff Type to Temporary only
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

  // Source Type
  $('source_type').addEventListener('change', function(){
    const show = this.value === 'Other';
    $('cs_div').style.display = show ? 'block' : 'none';
    $('custom_source').required = show;
  });

  // Tour toggle
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

  // Send OTP
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

  // Verify OTP
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

  // Submit guard + journey overlay
  $('appForm').addEventListener('submit', function(e){
    if(!verified){ e.preventDefault(); alert('Please verify your email address with OTP before submitting.'); return; }
    $('submitBtn').disabled = true;
    $('btnText').style.display = 'none';
    $('btnLoader').style.display = 'inline-block';
    cls($('pageOverlay'), false, 'd-none');
  });

}); // end DOMContentLoaded

// Helpers
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
<?php
include("../config/db.php");

// -------------------------
// HANDLE APPROVE / REJECT
// -------------------------
if(isset($_POST['approve'])){
    $id = $_POST['app_id'];
    $driver_id = $_POST['driver_id'];
    $vehicle_id = $_POST['vehicle_id'];
    $remark = $_POST['remark'];

    $conn->query("UPDATE vehicle_applications SET 
        status='Approved',
        driver_id='$driver_id',
        vehicle_id='$vehicle_id',
        remark='$remark'
        WHERE id='$id'
    ");

    // $conn->query("UPDATE drivers SET status='Assigned' WHERE id='$driver_id'");
    // $conn->query("UPDATE vehicles SET status='Assigned' WHERE id='$vehicle_id'");
}

if(isset($_POST['reject'])){
    $id = $_POST['app_id'];
    $remark = $_POST['remark'];

    $conn->query("UPDATE vehicle_applications 
        SET status='Rejected', remark='$remark' 
        WHERE id='$id'
    ");
}

// Fetch all applications
$res = $conn->query("SELECT * FROM vehicle_applications ORDER BY id DESC");
if(!$res){
    die("Query Failed: " . $conn->error);
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Super Admin Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
body{
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg,#4e73df,#1cc88a);
    min-height:100vh;
    padding-bottom: 40px;
}

.dashboard-header{
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(15px);
    padding:15px 20px;
    border-radius:15px;
    color:white;
    margin-bottom:20px;
}

.main-card{
    border-radius:20px;
    border:none;
    box-shadow:0 10px 25px rgba(0,0,0,0.15);
}

.table-hover tbody tr:hover{
    background:#f1f7ff;
    transition:0.2s;
}

.badge{
    padding:6px 10px;
    font-size:13px;
    border-radius:20px;
}

.btn{
    border-radius:12px;
    font-weight:500;
}

.modal-content{
    border-radius:20px;
}

.modal-header{
    background:linear-gradient(45deg,#4e73df,#1cc88a);
    color:white;
    border-radius:20px 20px 0 0;
}
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>
<body>

<div class="container py-4">

<!-- HEADER -->
<div class="dashboard-header d-flex justify-content-between align-items-center shadow">
<h4 class="mb-0"><i class="bi bi-speedometer2"></i> Super Admin Dashboard</h4>
<a href="logout.php" class="btn btn-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
</div>

<div class="card main-card">
<div class="card-body">

<div class="table-responsive">
<div class="mb-3 d-flex justify-content-end gap-2">
    <button class="btn btn-warning btn-sm" id="exportAllPDF"><i class="bi bi-file-earmark-pdf"></i> Export All to PDF</button>
    <button class="btn btn-info btn-sm text-white" id="exportAllCSV"><i class="bi bi-file-earmark-spreadsheet"></i> Export All to CSV</button>
</div>

<table class="table table-hover align-middle">
<thead>
<tr>
<th>#</th>
<th><i class="bi bi-person-fill"></i> Applicant</th>
<th><i class="bi bi-geo-alt-fill"></i> Journey</th>
<th><i class="bi bi-person-badge-fill"></i> Driver</th>
<th><i class="bi bi-truck"></i> Vehicle</th>
<th><i class="bi bi-flag-fill"></i> Status</th>
<th class="text-center"><i class="bi bi-gear-fill"></i> Action</th>
</tr>
</thead>
<tbody>
<?php 
$i=1;
if($res && $res->num_rows > 0){
    while($row = $res->fetch_assoc()){ 

        // Get driver & vehicle info
        $driver = ($row['driver_id']) ? $conn->query("SELECT * FROM drivers WHERE id='".$row['driver_id']."'")->fetch_assoc() : null;
        $vehicle = ($row['vehicle_id']) ? $conn->query("SELECT * FROM vehicles WHERE id='".$row['vehicle_id']."'")->fetch_assoc() : null;

        // Co-travelers
        $coTrav = implode(", ", array_filter([
            $row['cotraveler1'] ?? null,
            $row['cotraveler2'] ?? null,
            $row['cotraveler3'] ?? null,
            $row['cotraveler4'] ?? null
        ]));

        // Forwarded To (safe)
        $forwardedName = $row['forwarded_admin_name'] ?? "N/A";
        $designation = $row['designation'] ?? "";
        $forwardedTo = $forwardedName . ($designation ? " ($designation)" : "");
?>
<tr 
    data-cotraveler="<?= htmlspecialchars($coTrav) ?>"
    data-forwarded="<?= htmlspecialchars($forwardedTo) ?>"
    data-remark="<?= htmlspecialchars($row['remark']) ?>"
>
<td><?= $i++ ?></td>

<td 
    data-name="<?= htmlspecialchars($row['applicant_name']) ?>"
    data-mobile="<?= htmlspecialchars($row['mobile']) ?>"
    data-email="<?= htmlspecialchars($row['email']) ?>"
    data-purpose="<?= htmlspecialchars($row['purpose']) ?>"
    data-vehicle="<?= htmlspecialchars($row['vehicle_required']) ?>"
>
<strong><?= $row['applicant_name'] ?></strong><br>
<small>
<i class="bi bi-phone"></i> <?= $row['mobile'] ?><br>
<i class="bi bi-envelope"></i> <?= $row['email'] ?><br>
<i class="bi bi-chat-left-text"></i> <?= $row['purpose'] ?><br>
<i class="bi bi-truck"></i> <?= $row['vehicle_required'] ?>
</small>
</td>

<td 
    data-from="<?= htmlspecialchars($row['datetime_from']) ?>"
    data-to="<?= htmlspecialchars($row['datetime_to']) ?>"
    data-place="<?= htmlspecialchars($row['place_of_visit']) ?>"
>
<small>
<i class="bi bi-calendar-event"></i> <?= $row['datetime_from'] ?><br>
<i class="bi bi-calendar-check"></i> <?= $row['datetime_to'] ?><br>
<i class="bi bi-geo-alt"></i> <?= $row['place_of_visit'] ?>
</small>
</td>

<td 
    data-name="<?= htmlspecialchars($driver['driver_name'] ?? '') ?>"
    data-mobile="<?= htmlspecialchars($driver['driver_mob'] ?? '') ?>"
>
<?php 
if($driver) echo "<i class='bi bi-person-badge'></i> ".$driver['driver_name']."<br><i class='bi bi-phone'></i> ".$driver['driver_mob']; 
else echo "<span class='text-muted'>Not Assigned</span>";
?>
</td>

<td 
    data-type="<?= htmlspecialchars($vehicle['vehicle_type'] ?? '') ?>"
    data-name="<?= htmlspecialchars($vehicle['vehicle_name'] ?? '') ?>"
    data-no="<?= htmlspecialchars($vehicle['vehicle_no'] ?? '') ?>"
>
<?php 
if($vehicle) echo "<i class='bi bi-truck'></i> ".$vehicle['vehicle_type']."<br>".$vehicle['vehicle_name']."<br><i class='bi bi-hash'></i> ".$vehicle['vehicle_no'];
else echo "<span class='text-muted'>Not Assigned</span>";
?>
</td>

<td>
<?php
$status = $row['status'];
$badgeClass="secondary"; $icon="info-circle";
switch($status){
    case "Pending": $badgeClass="secondary"; $icon="hourglass-split"; break;
    case "Recommended": $badgeClass="warning text-dark"; $icon="hand-thumbs-up"; break;
    case "Not Recommended": $badgeClass="danger"; $icon="hand-thumbs-down"; break;
    case "Approved": $badgeClass="success"; $icon="check-circle"; break;
    case "Rejected": $badgeClass="dark"; $icon="x-circle"; break;
    case "Completed": $badgeClass="primary"; $icon="flag"; break;
    case "Cancelled": $badgeClass="danger"; $icon="slash-circle"; break;
}
echo "<span class='badge bg-$badgeClass'><i class='bi bi-$icon'></i> $status</span>";
?>
</td>

<td class="text-center">
<?php if($status=="Recommended"){ ?>
<button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#approveModal<?= $row['id'] ?>"><i class="bi bi-pencil-square"></i></button>
<?php } ?>
<?php if($status=="Approved"){ ?>
<a href="update_status.php?id=<?= $row['id'] ?>&type=Completed" class="btn btn-sm btn-success"><i class="bi bi-check-circle-fill"></i></a>
<a href="update_status.php?id=<?= $row['id'] ?>&type=Cancelled" class="btn btn-sm btn-danger"><i class="bi bi-x-circle-fill"></i></a>
<?php } ?>
<button class="btn btn-sm btn-secondary exportRow" data-id="<?= $row['id'] ?>"><i class="bi bi-file-earmark-pdf"></i></button>
</td>
</tr>

<!-- MODAL -->
<div class="modal fade" id="approveModal<?= $row['id'] ?>">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
<form method="POST">

<div class="modal-header">
<h5 class="modal-title"><i class="bi bi-check2-square"></i> Approve / Reject Request</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
<input type="hidden" name="app_id" value="<?= $row['id'] ?>">

<div class="mb-3">
<label class="form-label"><i class="bi bi-person-badge"></i> Select Driver</label>
<select name="driver_id" class="form-select">
<option value="">-- Available Drivers --</option>
<?php
$d = $conn->query("SELECT * FROM drivers WHERE status='Available'");
while($dr=$d->fetch_assoc()){
    echo "<option value='".$dr['id']."'>".$dr['driver_name']." (".$dr['driver_mob'].")</option>";
}
?>
</select>
</div>

<div class="mb-3">
<label class="form-label"><i class="bi bi-truck"></i> Select Vehicle</label>
<select name="vehicle_id" class="form-select">
<option value="">-- Available Vehicles --</option>
<?php
$v = $conn->query("SELECT * FROM vehicles WHERE status='Available'");
while($vh=$v->fetch_assoc()){
    echo "<option value='".$vh['id']."'>".$vh['vehicle_type']." - ".$vh['vehicle_name']." (".$vh['vehicle_no'].")</option>";
}
?>
</select>
</div>

<div class="mb-3">
<label class="form-label"><i class="bi bi-chat-dots"></i> Remark</label>
<textarea name="remark" class="form-control"></textarea>
</div>
</div>

<div class="modal-footer">
<button name="approve" class="btn btn-success"><i class="bi bi-check-circle-fill"></i> Approve</button>
<button name="reject" class="btn btn-danger"><i class="bi bi-x-circle-fill"></i> Reject</button>
</div>

</form>
</div>
</div>
</div>

<?php } } else { ?>
<tr><td colspan="7" class="text-center text-white py-3">No applications found</td></tr>
<?php } ?>
</tbody>
</table>
</div>

</div>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function(){

    // ---------------- Export All CSV ----------------
    document.getElementById("exportAllCSV").addEventListener("click", function(){
        let csvHeader = [
            "Application ID","Applicant Name","Mobile","Email","Purpose","Vehicle Required",
            "From","To","Place of Visit",
            "Co-Travelers",
            "Forwarded To (Name & Designation)",
            "Driver Assigned","Driver Mobile",
            "Vehicle Type","Vehicle Name","Vehicle No",
            "Remark","Status","Submitted On"
        ];
        let csv = csvHeader.join(",") + "\n";

        document.querySelectorAll("tbody tr").forEach(tr => {
            const tds = tr.querySelectorAll("td");
            if(tds.length < 7) return;

            let row = [
                tds[0].innerText.trim(),
                tds[1].getAttribute("data-name") || "",
                tds[1].getAttribute("data-mobile") || "",
                tds[1].getAttribute("data-email") || "",
                tds[1].getAttribute("data-purpose") || "",
                tds[1].getAttribute("data-vehicle") || "",
                tds[2].getAttribute("data-from") || "",
                tds[2].getAttribute("data-to") || "",
                tds[2].getAttribute("data-place") || "",
                tr.getAttribute("data-cotraveler") || "",
                tr.getAttribute("data-forwarded") || "",
                tds[3].getAttribute("data-name") || "",
                tds[3].getAttribute("data-mobile") || "",
                tds[4].getAttribute("data-type") || "",
                tds[4].getAttribute("data-name") || "",
                tds[4].getAttribute("data-no") || "",
                tr.getAttribute("data-remark") || "",
                tds[5].innerText.trim(),
                tds[8]?.innerText.trim() || "" // submitted on
            ].map(item => `"${item}"`).join(",");

            csv += row + "\n";
        });

        let blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        let link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = "vehicle_applications.csv";
        link.click();
    });

    // ---------------- Export All PDF ----------------
    document.getElementById("exportAllPDF").addEventListener("click", function(){
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p','pt','a4');
        const pageWidth = doc.internal.pageSize.getWidth();

        let y = 30;
        doc.setFontSize(16);
        doc.setFont("helvetica","bold");
        doc.text("Vehicle Applications Report", pageWidth/2, y, null, null, "center");
        y += 20;

        doc.setFontSize(11);
        doc.setFont("helvetica","normal");

        document.querySelectorAll("tbody tr").forEach(tr => {
            const tds = tr.querySelectorAll("td");
            if(tds.length < 7) return;
            let coTrav = tr.getAttribute("data-cotraveler") || "";

            doc.setFont("helvetica","bold");
            doc.setTextColor(21, 101, 192);
            doc.text(`Application ID: ${tds[0].innerText}`, 20, y); y+=15;
            doc.setFont("helvetica","normal"); doc.setTextColor(0,0,0);

            doc.text(`Applicant: ${tds[1].getAttribute("data-name")}`, 20, y); y+=12;
            doc.text(`Mobile: ${tds[1].getAttribute("data-mobile")}`, 20, y); y+=12;
            doc.text(`Email: ${tds[1].getAttribute("data-email")}`, 20, y); y+=12;
            doc.text(`Purpose: ${tds[1].getAttribute("data-purpose")}`, 20, y); y+=12;
            doc.text(`Vehicle Required: ${tds[1].getAttribute("data-vehicle")}`, 20, y); y+=12;

            doc.text(`Journey From: ${tds[2].getAttribute("data-from")}`, 20, y); y+=12;
            doc.text(`Journey To: ${tds[2].getAttribute("data-to")}`, 20, y); y+=12;
            doc.text(`Place: ${tds[2].getAttribute("data-place")}`, 20, y); y+=12;

            doc.text(`Co-Travelers: ${coTrav || "None"}`, 20, y); y+=12;
            doc.text(`Forwarded To: ${tr.getAttribute("data-forwarded") || "N/A"}`, 20, y); y+=12;
            doc.text(`Driver: ${tds[3].getAttribute("data-name") || "Not Assigned"}`, 20, y); y+=12;
            doc.text(`Driver Mobile: ${tds[3].getAttribute("data-mobile") || ""}`, 20, y); y+=12;
            doc.text(`Vehicle Type: ${tds[4].getAttribute("data-type") || ""}`, 20, y); y+=12;
            doc.text(`Vehicle Name: ${tds[4].getAttribute("data-name") || ""}`, 20, y); y+=12;
            doc.text(`Vehicle No: ${tds[4].getAttribute("data-no") || ""}`, 20, y); y+=12;
            doc.text(`Remark: ${tr.getAttribute("data-remark") || "None"}`, 20, y); y+=12;
            doc.text(`Status: ${tds[5].innerText}`, 20, y); y+=12;
            doc.text(`Submitted On: ${tds[8]?.innerText || ""}`, 20, y); y+=20;

            if(y > 750){ doc.addPage(); y = 30; }
        });

        doc.save("vehicle_applications.pdf");
    });

    // ---------------- Export Single Row PDF ----------------
    document.querySelectorAll(".exportRow").forEach(btn=>{
        btn.addEventListener("click", function(){
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('p','pt','a4');
            const tr = this.closest("tr");
            const tds = tr.querySelectorAll("td");
            const pageWidth = doc.internal.pageSize.getWidth();

            let y = 30;
            doc.setFontSize(16);
            doc.setFont("helvetica","bold");
            doc.text(`Vehicle Application ID: ${tds[0].innerText}`, pageWidth/2, y, null, null, "center");
            y += 20;

            doc.setFontSize(12);
            doc.setFont("helvetica","normal");

            const coTrav = tr.getAttribute("data-cotraveler") || "";

            doc.text(`Applicant: ${tds[1].getAttribute("data-name")}`, 20, y); y+=14;
            doc.text(`Mobile: ${tds[1].getAttribute("data-mobile")}`, 20, y); y+=14;
            doc.text(`Email: ${tds[1].getAttribute("data-email")}`, 20, y); y+=14;
            doc.text(`Purpose: ${tds[1].getAttribute("data-purpose")}`, 20, y); y+=14;
            doc.text(`Vehicle Required: ${tds[1].getAttribute("data-vehicle")}`, 20, y); y+=14;

            doc.text(`Journey From: ${tds[2].getAttribute("data-from")}`, 20, y); y+=14;
            doc.text(`Journey To: ${tds[2].getAttribute("data-to")}`, 20, y); y+=14;
            doc.text(`Place: ${tds[2].getAttribute("data-place")}`, 20, y); y+=14;

            doc.text(`Co-Travelers: ${coTrav || "None"}`, 20, y); y+=14;
            doc.text(`Forwarded To: ${tr.getAttribute("data-forwarded") || "N/A"}`, 20, y); y+=14;
            doc.text(`Driver: ${tds[3].getAttribute("data-name") || "Not Assigned"}`, 20, y); y+=14;
            doc.text(`Driver Mobile: ${tds[3].getAttribute("data-mobile") || ""}`, 20, y); y+=14;
            doc.text(`Vehicle Type: ${tds[4].getAttribute("data-type") || ""}`, 20, y); y+=14;
            doc.text(`Vehicle Name: ${tds[4].getAttribute("data-name") || ""}`, 20, y); y+=14;
            doc.text(`Vehicle No: ${tds[4].getAttribute("data-no") || ""}`, 20, y); y+=14;
            doc.text(`Remark: ${tr.getAttribute("data-remark") || "None"}`, 20, y); y+=14;
            doc.text(`Status: ${tds[5].innerText}`, 20, y); y+=14;
            doc.text(`Submitted On: ${tds[8]?.innerText || ""}`, 20, y);

            doc.save(`application_${tds[0].innerText}.pdf`);
        });
    });

});
</script>
</body>
</html>
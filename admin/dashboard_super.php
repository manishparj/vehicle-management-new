<?php
include("../config/db.php");

if(!isset($_SESSION['user']) || $_SESSION['user']['role']!='super_admin'){
    header("location:login.php");
    exit();
}
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


$res = $conn->query("
SELECT 
    va.*, 
    u.name AS forwarded_admin_name,
    u.designation AS forwarded_designation
FROM vehicle_applications va
LEFT JOIN users u ON va.forwarded_to = u.id
ORDER BY va.created_at DESC
");

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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="super.css" rel="stylesheet" >

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>

<body>
    <?php include ('../header.php'); ?>

    <div class="container py-5  ">
        <!-- HEADER -->
        <div class="dashboard-header d-flex justify-content-between align-items-center shadow">
                <a href="logout.php" class="btn btn-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>

        <div class="card main-card">
            <div class="card-body">

                <div class="table-responsive">
                    <div class="mb-3 d-flex justify-content-end gap-2">
                        <button class="btn btn-warning btn-sm" id="exportAllPDF"><i class="bi bi-file-earmark-pdf"></i>
                            Export All to PDF</button>
                        <button class="btn btn-info btn-sm text-white" id="exportAllCSV"><i
                                class="bi bi-file-earmark-spreadsheet"></i> Export All to CSV</button>
                    </div>
                    <?php
                        $totalCount = $conn->query("SELECT COUNT(*) as c FROM vehicle_applications")->fetch_assoc()['c'];
                        $completedCount = $conn->query("SELECT COUNT(*) as c FROM vehicle_applications WHERE status='Completed'")->fetch_assoc()['c'];
                        ?>
                    <div class="row g-4 mb-4">

                        <div class="col-md-3 col-sm-6">
                            <div class="stat-card bg-primary">
                                <div>
                                    <h6>Total Applications</h6>
                                    <h3 class="counter" data-target="<?= $totalCount ?>">0</h3>
                                </div>
                                <i class="bi bi-files fs-1"></i>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="stat-card bg-info">
                                <div>
                                    <h6>Completed</h6>
                                    <h3 class="counter" data-target="<?= $completedCount ?>">0</h3>
                                </div>
                                <i class="bi bi-flag fs-1"></i>
                            </div>
                        </div>

                    </div>
                   <div class="row mb-3 g-3 align-items-center">

    <!-- Search by Name -->
    <div class="col-md-4">
        <input type="text" id="searchInput" class="form-control"
            placeholder="🔍 Search by Applicant Name">
    </div>


    <!-- Rows Per Page -->
    <div class="col-md-4 text-md-end">
        <select id="rowsPerPage" class="form-select w-auto d-inline-block">
            <option value="5">5 per page</option>
            <option value="10">10 per page</option>
            <option value="25">25 per page</option>
            <option value="50">50 per page</option>
        </select>
    </div>

</div>
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th><i class="bi bi-person-fill"></i> Applicant</th>
                                <th><i class="bi bi-geo-alt-fill"></i> Journey</th>
                                <th><i class="bi bi-person-badge-fill"></i> Driver</th>
                                <th><i class="bi bi-truck"></i> Vehicle</th>
                                <th><i class="bi bi-file-earmark-pdf-fill"></i> Tour Order Attached</th>
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

                                        $forwardedName = $row['forwarded_admin_name'] ?? "N/A";
                                        $designation = $row['designation'] ?? "";
                                        $forwardedTo = $forwardedName . ($designation ? " ($designation)" : "");
                                ?>
                            <tr data-cotraveler="<?= htmlspecialchars($coTrav) ?>"
                                data-forwarded="<?= htmlspecialchars($forwardedTo) ?>"
                                data-remark="<?= htmlspecialchars($row['remark']) ?>"
                                data-submitted="<?= htmlspecialchars($row['created_at']) ?>">
                                <td><?= $i++ ?></td>

                                <td data-name="<?= htmlspecialchars($row['applicant_name']) ?>"
                                    data-mobile="<?= htmlspecialchars($row['mobile']) ?>"
                                    data-email="<?= htmlspecialchars($row['email']) ?>"
                                    data-forwarded="<?= htmlspecialchars($row['forwarded_admin_name']) ?>">
                                    <strong><?= $row['applicant_name'] ?></strong><br>
                                    <small>
                                        <i class="bi bi-phone"></i> <?= $row['mobile'] ?><br>
                                        <i class="bi bi-envelope"></i> <?= $row['email'] ?><br>
                                        <i class="bi bi-envelope"></i> Forwarded Through <br>
                                        <?= $row['forwarded_admin_name'] ?><br>
                                    </small>
                                </td>

                                <td data-from="<?= htmlspecialchars(date('d/m/Y H:i', strtotime($row['datetime_from']))) ?>"
                                    data-to="<?= htmlspecialchars(date('d/m/Y H:i', strtotime($row['datetime_to']))) ?>"
                                    data-place="<?= htmlspecialchars($row['place_of_visit']) ?>"
                                    data-forwarded="<?= htmlspecialchars($row['forwarded_admin_name']) ?>"
                                    data-purpose="<?= htmlspecialchars($row['purpose']) ?>"
                                    data-vehicle="<?= htmlspecialchars($row['vehicle_required']) ?>"
                                     data-date="<?= strtotime($row['datetime_from']) ?>"
                                     >

                                    <div class="p-2 rounded bg-light border">

                                        <!-- Date Section -->
                                        <div class="mb-2">
                                            <div class="fw-semibold text-primary">
                                                <i class="bi bi-calendar-event"></i> Journey Period
                                            </div>
                                            <small class="text-dark">
                                                <?= date("d/m/Y H:i", strtotime($row['datetime_from'])) ?>
                                                <span class="mx-1 text-muted">→</span>
                                                <?= date("d/m/Y H:i", strtotime($row['datetime_to'])) ?>
                                            </small>
                                        </div>

                                        <!-- Place -->
                                        <div class="mb-2">
                                            <div class="fw-semibold text-success">
                                                <i class="bi bi-geo-alt-fill"></i> Destination:
                                                <small><?= $row['place_of_visit'] ?></small>
                                            </div>

                                        </div>

                                        <!-- Purpose -->
                                        <div class="mb-2">
                                            <div class="fw-semibold text-info">
                                                <i class="bi bi-chat-left-text"></i> Purpose:
                                                <small><?= $row['purpose'] ?></small>
                                            </div>

                                        </div>

                                        <!-- Co-Travelers -->
                                        <div>
                                            <div class="fw-semibold text-warning">
                                                <i class="bi bi-people-fill"></i> Co-Travelers: <?php if(!empty($coTrav)) { 
                                                $coList = explode(", ", $coTrav);
                                                foreach($coList as $ct){
                                                    echo "<span class='badge bg-secondary me-1 mb-1'><i class='bi bi-person'></i> ".$ct."</span>";
                                                }
                                            } else { ?>
                                                <span class="text-muted small">None</span>
                                                <?php } ?>
                                            </div>

                                        </div>

                                    </div>

                                </td>

                                <td data-name="<?= htmlspecialchars($driver['driver_name'] ?? '') ?>"
                                    data-mobile="<?= htmlspecialchars($driver['driver_mob'] ?? '') ?>">
                                    <?php 
                        if($driver) echo "<i class='bi bi-person-badge'></i> ".$driver['driver_name']."<br><i class='bi bi-phone'></i> ".$driver['driver_mob']; 
                        else echo "<span class='text-muted'>Not Assigned</span>";
                        ?>
                                </td>

                                <td data-type="<?= htmlspecialchars($vehicle['vehicle_type'] ?? '') ?>"
                                    data-name="<?= htmlspecialchars($vehicle['vehicle_name'] ?? '') ?>"
                                    data-no="<?= htmlspecialchars($vehicle['vehicle_no'] ?? '') ?>">
                                    <?php 
        if($vehicle) echo "<i class='bi bi-truck'></i> ".$vehicle['vehicle_type']."<br>".$vehicle['vehicle_name']."<br><i class='bi bi-hash'></i> ".$vehicle['vehicle_no'];
        else echo "<span class='text-muted'>Not Assigned</span>";
        ?>
                                </td>
                                <td class="text-center"
                                    data-tour="<?= $row['tour_approved'] == 1 ? 'Attached' : 'N.A' ?>">

                                    <?php if($row['tour_approved'] == 1){ ?>

                                    <?php if(!empty($row['tour_order_pdf'])){ ?>
                                    <br>
                                    <a href="view_tour_pdf.php?file=<?= urlencode($row['tour_order_pdf']) ?>"
                                        target="_blank" class="btn btn-sm btn-outline-primary mt-1">
                                        <i class="bi bi-eye-fill"></i>
                                    </a>
                                    <?php } ?>

                                    <?php } else { ?>

                                    <span class="badge bg-secondary">
                                        <i class="bi bi-x-circle-fill"></i> N/A
                                    </span>

                                    <?php } ?>
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
                                    <div class="d-flex justify-content-center gap-2 flex-wrap">

                                        <?php if($status=="Recommended"){ ?>
                                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                            data-bs-target="#approveModal<?= $row['id'] ?>">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <?php } ?>

                                        <?php if($status=="Approved"){ ?>
                                        <a href="update_status.php?id=<?= $row['id'] ?>&type=Completed"
                                            class="btn btn-sm btn-success">
                                            <i class="bi bi-check-circle-fill"></i>
                                        </a>

                                        <a href="update_status.php?id=<?= $row['id'] ?>&type=Cancelled"
                                            class="btn btn-sm btn-danger">
                                            <i class="bi bi-x-circle-fill"></i>
                                        </a>
                                        <?php } ?>

                                        <button class="btn btn-sm btn-dark exportRow" data-id="<?= $row['id'] ?>">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                        </button>

                                    </div>
                                </td>
                            </tr>

                            <!-- MODAL -->
                            <div class="modal fade" id="approveModal<?= $row['id'] ?>">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="POST">

                                            <div class="modal-header">
                                                <h5 class="modal-title"><i class="bi bi-check2-square"></i> Approve /
                                                    Reject Request</h5>
                                                <button type="button" class="btn-close btn-close-white"
                                                    data-bs-dismiss="modal"></button>
                                            </div>

                                            <div class="modal-body">
                                                <input type="hidden" name="app_id" value="<?= $row['id'] ?>">

                                                <div class="mb-3">
                                                    <label class="form-label"><i class="bi bi-person-badge"></i> Select
                                                        Driver</label>
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
                                                    <label class="form-label"><i class="bi bi-truck"></i> Select
                                                        Vehicle</label>
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
                                                    <label class="form-label"><i class="bi bi-chat-dots"></i>
                                                        Remark</label>
                                                    <textarea name="remark" class="form-control"></textarea>
                                                </div>
                                            </div>

                                            <div class="modal-footer">
                                                <button name="approve" class="btn btn-success"><i
                                                        class="bi bi-check-circle-fill"></i> Approve</button>
                                                <button name="reject" class="btn btn-danger"><i
                                                        class="bi bi-x-circle-fill"></i> Reject</button>
                                            </div>

                                        </form>
                                    </div>
                                </div>
                            </div>

                            <?php } } else { ?>
                            <tr>
                                <td colspan="7" class="text-center text-white py-3">No applications found</td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                   <nav class="mt-3">
    <ul class="pagination justify-content-end" id="pagination"></ul>
</nav>
                </div>

            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

    <script>
    document.addEventListener("DOMContentLoaded", function() {

        // ---------------- Export All CSV ----------------
        document.getElementById("exportAllCSV").addEventListener("click", function() {

            let csvHeader = [
                "Application ID",
                "Applicant Name",
                "Mobile",
                "Email",
                "Purpose",
                "Vehicle Required",
                "Journey From",
                "Journey To",
                "Place of Visit",
                "Co-Travelers",
                "Forwarded To",
                "Driver Assigned",
                "Driver Mobile",
                "Vehicle Type",
                "Vehicle Name",
                "Vehicle No",
                "Tour Order Attached",
                "Remark",
                "Status",
                "Submitted On"
            ];

            let csv = csvHeader.join(",") + "\n";

            document.querySelectorAll("tbody tr").forEach(tr => {

                const tds = tr.querySelectorAll("td");
                if (tds.length < 8) return; // must match full table

                let row = [
                    tds[0].innerText.trim(), // 0 ID

                    tds[1].getAttribute("data-name") || "", // 1 Applicant
                    tds[1].getAttribute("data-mobile") || "",
                    tds[1].getAttribute("data-email") || "",

                    tds[2].getAttribute("data-purpose") || "", // 2 Journey
                    tds[2].getAttribute("data-vehicle") || "",
                    tds[2].getAttribute("data-from") || "",
                    tds[2].getAttribute("data-to") || "",
                    tds[2].getAttribute("data-place") || "",

                    tr.getAttribute("data-cotraveler") || "",
                    tr.getAttribute("data-forwarded") || "",

                    tds[3].getAttribute("data-name") || "", // 3 Driver
                    tds[3].getAttribute("data-mobile") || "",

                    tds[4].getAttribute("data-type") || "", // 4 Vehicle
                    tds[4].getAttribute("data-name") || "",
                    tds[4].getAttribute("data-no") || "",

                    tds[5].getAttribute("data-tour") || "", // 5 Tour Order
                    tr.getAttribute("data-remark") || "",
                    tds[6].innerText.trim(), // 6 Status
                    tr.getAttribute("data-submitted") || ""
                ];

                row = row.map(item => `"${item}"`).join(",");
                csv += row + "\n";
            });

            let blob = new Blob([csv], {
                type: 'text/csv;charset=utf-8;'
            });
            let link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = "vehicle_applications.csv";
            link.click();
        });


        // ---------------- Export Single Row PDF ----------------
        document.querySelectorAll(".exportRow").forEach(btn => {
            btn.addEventListener("click", function() {

                const {
                    jsPDF
                } = window.jspdf;
                const doc = new jsPDF('p', 'pt', 'a4');
                const tr = this.closest("tr");
                const tds = tr.querySelectorAll("td");
                const pageWidth = doc.internal.pageSize.getWidth();

                doc.setFontSize(18);
                doc.setTextColor(21, 101, 192);
                doc.setFont("helvetica", "bold");
                doc.text(`Vehicle Application ID: ${tds[0].innerText}`, pageWidth / 2, 40, {
                    align: "center"
                });

                const data = [
                    ["Applicant", tds[1].getAttribute("data-name") || ""],
                    ["Mobile", tds[1].getAttribute("data-mobile") || ""],
                    ["Email", tds[1].getAttribute("data-email") || ""],
                    ["Purpose", tds[2].getAttribute("data-purpose") || ""],
                    ["Vehicle Required", tds[2].getAttribute("data-vehicle") || ""],
                    ["Journey From", tds[2].getAttribute("data-from") || ""],
                    ["Journey To", tds[2].getAttribute("data-to") || ""],
                    ["Place", tds[2].getAttribute("data-place") || ""],
                    ["Co-Travelers", tr.getAttribute("data-cotraveler") || "None"],
                    ["Forwarded To", tr.getAttribute("data-forwarded") || "N/A"],
                    ["Driver", tds[3].getAttribute("data-name") || "Not Assigned"],
                    ["Driver Mobile", tds[3].getAttribute("data-mobile") || ""],
                    ["Vehicle Type", tds[4].getAttribute("data-type") || ""],
                    ["Vehicle Name", tds[4].getAttribute("data-name") || ""],
                    ["Vehicle No", tds[4].getAttribute("data-no") || ""],
                    ["Tour Order Attached", tds[5].getAttribute("data-tour") ||
                        ""
                    ], // ✅ ADDED
                    ["Remark", tr.getAttribute("data-remark") || "None"],
                    ["Status", tds[6].innerText || ""], // ✅ FIXED
                    ["Submitted On", tr.getAttribute("data-submitted") || "N/A"]
                ];

                doc.autoTable({
                    startY: 70,
                    head: [
                        ["Field", "Details"]
                    ],
                    body: data,
                    styles: {
                        fontSize: 11,
                        cellPadding: 6
                    },
                    headStyles: {
                        fillColor: [21, 101, 192],
                        textColor: 255,
                        fontStyle: 'bold'
                    },
                    alternateRowStyles: {
                        fillColor: [245, 245, 245]
                    },
                    theme: "grid",
                    margin: {
                        left: 40,
                        right: 40
                    }
                });

                doc.save(`application_${tds[0].innerText}.pdf`);
            });
        });
        // ---------------- Export All PDF ----------------
        document.getElementById("exportAllPDF").addEventListener("click", function() {

            const {
                jsPDF
            } = window.jspdf;
            const doc = new jsPDF('p', 'pt', 'a4');
            const pageWidth = doc.internal.pageSize.getWidth();

            let startY = 40;
            let firstRecord = true;

            document.querySelectorAll("tbody tr").forEach((tr, index) => {

                const tds = tr.querySelectorAll("td");
                if (tds.length < 6) return;

                // Add new page except first record
                if (!firstRecord) {
                    doc.addPage();
                    startY = 40;
                }

                firstRecord = false;

                // ======= HEADER =======
                doc.setFontSize(18);
                doc.setTextColor(21, 101, 192);
                doc.setFont("helvetica", "bold");
                doc.text(`Vehicle Application ID: ${tds[0].innerText}`, pageWidth / 2, startY, {
                    align: "center"
                });

                startY += 30;

                doc.setFontSize(12);
                doc.setFont("helvetica", "normal");
                doc.setTextColor(0, 0, 0);

                const data = [
                    ["Applicant", tds[1].getAttribute("data-name") || ""],
                    ["Mobile", tds[1].getAttribute("data-mobile") || ""],
                    ["Email", tds[1].getAttribute("data-email") || ""],
                    ["Purpose", tds[2].getAttribute("data-purpose") || ""],
                    ["Vehicle Required", tds[2].getAttribute("data-vehicle") || ""],
                    ["Journey From", tds[2].getAttribute("data-from") || ""],
                    ["Journey To", tds[2].getAttribute("data-to") || ""],
                    ["Place", tds[2].getAttribute("data-place") || ""],
                    ["Co-Travelers", tr.getAttribute("data-cotraveler") || "None"],
                    ["Forwarded To", tr.getAttribute("data-forwarded") || "N/A"],
                    ["Driver", tds[3].getAttribute("data-name") || "Not Assigned"],
                    ["Driver Mobile", tds[3].getAttribute("data-mobile") || ""],
                    ["Vehicle Type", tds[4].getAttribute("data-type") || ""],
                    ["Vehicle Name", tds[4].getAttribute("data-name") || ""],
                    ["Vehicle No", tds[4].getAttribute("data-no") || ""],
                    ["Tour Order Attached", tds[5].getAttribute("data-tour") ||
                        ""
                    ], // ✅ ADDED
                    ["Remark", tr.getAttribute("data-remark") || "None"],
                    ["Status", tds[6].innerText || ""], // ✅ FIXED
                    ["Submitted On", tr.getAttribute("data-submitted") || "N/A"]
                ];

                doc.autoTable({
                    startY: startY,
                    head: [
                        ["Field", "Details"]
                    ],
                    body: data,
                    styles: {
                        fontSize: 11,
                        cellPadding: 6,
                        overflow: "linebreak"
                    },
                    headStyles: {
                        fillColor: [21, 101, 192],
                        textColor: 255,
                        fontStyle: "bold"
                    },
                    alternateRowStyles: {
                        fillColor: [245, 245, 245]
                    },
                    theme: "grid",
                    margin: {
                        left: 40,
                        right: 40
                    }
                });

            });

            doc.save("All_Vehicle_Applications.pdf");
        });

    });
    </script>
    <script>
    document.querySelectorAll('.counter').forEach(counter => {
        let updateCount = () => {
            let target = +counter.getAttribute('data-target');
            let count = +counter.innerText;
            let increment = target / 100;

            if (count < target) {
                counter.innerText = Math.ceil(count + increment);
                setTimeout(updateCount, 20);
            } else {
                counter.innerText = target;
            }
        };
        updateCount();
    });
    </script>
<script>
document.addEventListener("DOMContentLoaded", function () {

    const tableBody = document.querySelector("tbody");
    const allRows = Array.from(tableBody.querySelectorAll("tr"));

    const searchInput = document.getElementById("searchInput");
    const rowsSelect = document.getElementById("rowsPerPage");
    const sortSelect = document.getElementById("sortDate");
    const pagination = document.getElementById("pagination");

    let rowsPerPage = parseInt(rowsSelect.value);
    let currentPage = 1;
    let filteredRows = [...allRows];

    // ================= SEARCH BY NAME =================
    function filterRows() {
        const keyword = searchInput.value.toLowerCase().trim();

        filteredRows = allRows.filter(row => {
            const nameCell = row.querySelector("td[data-name]");
            if (!nameCell) return false;

            const name = nameCell.getAttribute("data-name").toLowerCase();
            return name.includes(keyword);
        });

        sortRows();
    }

    // ================= SORT BY DATE =================
    function sortRows() {
        const sortType = sortSelect.value;

        filteredRows.sort((a, b) => {
            const dateA = parseInt(a.getAttribute("data-date"));
            const dateB = parseInt(b.getAttribute("data-date"));

            return sortType === "newest" ? dateB - dateA : dateA - dateB;
        });

        currentPage = 1;
        displayRows();
        setupPagination();
    }

    // ================= DISPLAY ROWS =================
    function displayRows() {
        tableBody.innerHTML = "";

        const start = (currentPage - 1) * rowsPerPage;
        const end = start + rowsPerPage;

        filteredRows.slice(start, end).forEach(row => {
            tableBody.appendChild(row);
        });
    }

    // ================= PAGINATION =================
    function setupPagination() {
        pagination.innerHTML = "";
        const pageCount = Math.ceil(filteredRows.length / rowsPerPage);

        for (let i = 1; i <= pageCount; i++) {
            const li = document.createElement("li");
            li.className = "page-item " + (i === currentPage ? "active" : "");

            const a = document.createElement("a");
            a.className = "page-link";
            a.href = "#";
            a.innerText = i;

            a.addEventListener("click", function (e) {
                e.preventDefault();
                currentPage = i;
                displayRows();
                setupPagination();
            });

            li.appendChild(a);
            pagination.appendChild(li);
        }
    }

    // ================= EVENTS =================
    searchInput.addEventListener("keyup", filterRows);

    rowsSelect.addEventListener("change", function () {
        rowsPerPage = parseInt(this.value);
        currentPage = 1;
        displayRows();
        setupPagination();
    });

    sortSelect.addEventListener("change", sortRows);

    // Initial Load
    filterRows();
});
</script>
</body>

</html>
<?php
session_start();   // REQUIRED
include("../config/db.php");

if(!isset($_SESSION['user']) || $_SESSION['user']['role']!='super_admin'){
    header("location:login.php");
    exit();
}
// -------------------------
// HANDLE APPROVE / REJECT
// -------------------------
if(isset($_POST['approve'])){
    $id = $conn->real_escape_string($_POST['app_id']);
    $driver_id = $conn->real_escape_string($_POST['driver_id']);
    $vehicle_id = $conn->real_escape_string($_POST['vehicle_id']);
    $remark = $conn->real_escape_string($_POST['remark']);

    // Update application status to Approved
    $update_query = "UPDATE vehicle_applications SET 
        status='Approved',
        driver_id='$driver_id',
        vehicle_id='$vehicle_id',
        remark='$remark'
        WHERE id='$id'";
    
    if($conn->query($update_query)) {
        // Optional: Update driver and vehicle status if needed
        // $conn->query("UPDATE drivers SET status='Assigned' WHERE id='$driver_id'");
        // $conn->query("UPDATE vehicles SET status='Assigned' WHERE id='$vehicle_id'");
        
        echo "<script>alert('Application Approved Successfully'); window.location.href='';</script>";
    } else {
        echo "<script>alert('Error: " . $conn->error . "');</script>";
    }
}

// ADD DRIVER
if(isset($_POST['add_driver'])){

    $name = mysqli_real_escape_string($conn, $_POST['driver_name']);
    $mob = mysqli_real_escape_string($conn, $_POST['driver_mob']);

    $conn->query("INSERT INTO drivers (driver_name, driver_mob) 
                  VALUES ('$name','$mob')");

    echo "<script>alert('Driver Added Successfully'); window.location.href='';</script>";
}

// ADD VEHICLE
if(isset($_POST['add_vehicle'])){

    $type = mysqli_real_escape_string($conn, $_POST['vehicle_type']);
    $name = mysqli_real_escape_string($conn, $_POST['vehicle_name']);
    $no = mysqli_real_escape_string($conn, $_POST['vehicle_no']);

    $conn->query("INSERT INTO vehicles (vehicle_type, vehicle_name, vehicle_no) 
                  VALUES ('$type','$name','$no')");

    echo "<script>alert('Vehicle Added Successfully'); window.location.href='';</script>";
}

if(isset($_POST['reject'])){
    $id = $conn->real_escape_string($_POST['app_id']);
    $remark = $conn->real_escape_string($_POST['remark']);

    $update_query = "UPDATE vehicle_applications 
        SET status='Rejected', remark='$remark' 
        WHERE id='$id'";
    
    if($conn->query($update_query)) {
        echo "<script>alert('Application Rejected'); window.location.href='';</script>";
    } else {
        echo "<script>alert('Error: " . $conn->error . "');</script>";
    }
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
    <link href="super.css" rel="stylesheet">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>

<body>
    <?php include ('../header.php'); ?>

    <div class="container py-5  ">
        <!-- HEADER -->
        <div class="dashboard-header d-flex justify-content-between align-items-center">
            <div class="dashboard-title">
                <i class="bi bi-speedometer2 text-primary me-2"></i>
                Vehicle Management Dashboard
            </div>

            <a href="logout.php" class="btn btn-outline-danger px-4">
                <i class="bi bi-box-arrow-right me-1"></i> Logout
            </a>
        </div>
        <div class="card main-card">
            <div class="card-body">
                <div class="row mb-4 g-3 align-items-center">

                    <div class="col-lg-4 col-md-6">
                        <input type="text" id="searchInput" class="form-control shadow-sm"
                            placeholder="🔍 Search by Applicant Name">
                    </div>

                    <div class="col-lg-2 col-md-6">
                        <select id="rowsPerPage" class="form-select shadow-sm">
                            <option value="5">5 per page</option>
                            <option value="10">10 per page</option>
                            <option value="25">25 per page</option>
                            <option value="50">50 per page</option>
                            <option value="1000">All per page</option>
                        </select>
                    </div>

                    <div class="col-lg-6 text-lg-end d-flex flex-wrap justify-content-lg-end gap-2">
                        <button class="btn btn-warning btn-sm" id="exportAllPDF"><i class="bi bi-file-earmark-pdf"></i>
                            Export PDF</button>
                        <button class="btn btn-warning btn-sm" id="exportAllCompletedPDF"><i class="bi bi-file-earmark-pdf"></i>
                            Export All Completed Journey PDF</button>
                        <button class="btn btn-info btn-sm text-white" id="exportAllCSV"><i
                                class="bi bi-file-earmark-spreadsheet"></i> Export CSV</button>

                        <button class="btn btn-success px-3" data-bs-toggle="modal" data-bs-target="#addDriverModal">
                            <i class="bi bi-person-plus me-1"></i> Driver
                        </button>

                        <button class="btn btn-primary px-3" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
                            <i class="bi bi-truck me-1"></i> Vehicle
                        </button>
                    </div>

                </div>

                <!-- ADD DRIVER MODAL -->
                <div class="modal fade" id="addDriverModal">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form method="POST">
                                <div class="modal-header bg-success text-white">
                                    <h5 class="modal-title"><i class="bi bi-person-plus"></i> Add New Driver
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white"
                                        data-bs-dismiss="modal"></button>
                                </div>

                                <div class="modal-body">

                                    <div class="mb-3">
                                        <label class="form-label">Driver Name</label>
                                        <input type="text" name="driver_name" class="form-control" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Mobile Number</label>
                                        <input type="text" name="driver_mob" class="form-control" required>
                                    </div>

                                </div>

                                <div class="modal-footer">
                                    <button type="submit" name="add_driver" class="btn btn-success">
                                        <i class="bi bi-check-circle"></i> Save Driver
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- ADD VEHICLE MODAL -->
                <div class="modal fade" id="addVehicleModal">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form method="POST">
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title"><i class="bi bi-truck"></i> Add New Vehicle</h5>
                                    <button type="button" class="btn-close btn-close-white"
                                        data-bs-dismiss="modal"></button>
                                </div>

                                <div class="modal-body">

                                    <div class="mb-3">
                                        <label class="form-label">Vehicle Type</label>
                                        <select name="vehicle_type" class="form-select" required>
                                            <option value="Car">Car</option>
                                            <option value="Bus">Bus</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Vehicle Name</label>
                                        <input type="text" name="vehicle_name" class="form-control" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Vehicle Number</label>
                                        <input type="text" name="vehicle_no" class="form-control" required>
                                    </div>
                                </div>

                                <div class="modal-footer">
                                    <button type="submit" name="add_vehicle" class="btn btn-primary">
                                        <i class="bi bi-check-circle"></i> Save Vehicle
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive mobile-stack">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><i class="bi bi-person-fill"></i> Applicant</th>
                        <th><i class="bi bi-geo-alt-fill"></i> Journey</th>
                        <th><i class="bi bi-person-lines-fill"></i> Assignment</th>
                        <th><i class="bi bi-shield-lock"></i> Security Movement</th> <!-- New Column -->
                        <th><i class="bi bi-flag-fill"></i> Status</th>
                        <th class="text-center"><i class="bi bi-gear-fill"></i> Action</th>
                    </tr>
                </thead>
                <tbody>
    <?php 
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
                $row['cotraveler4'] ?? null,
                $row['cotraveler5'] ?? null,
                $row['cotraveler6'] ?? null,
            ]));

            $forwardedName = $row['forwarded_admin_name'] ?? "N/A";
            $designation = $row['designation'] ?? "";
            $forwardedTo = $forwardedName . ($designation ? " ($designation)" : "");
            
            // Calculate total hours if not set
            $totalHours = $row['total_hours'];
            if(empty($totalHours) && !empty($row['out_datetime']) && !empty($row['in_datetime'])) {
                $outTime = strtotime($row['out_datetime']);
                $inTime = strtotime($row['in_datetime']);
                $diffSeconds = $inTime - $outTime;
                $totalHours = round($diffSeconds / 3600, 2);
            }
    ?>
    <tr data-cotraveler="<?= htmlspecialchars($coTrav) ?>"
        data-forwarded="<?= htmlspecialchars($forwardedTo) ?>"
        data-remark="<?= htmlspecialchars($row['remark']) ?>"
        data-submitted="<?= htmlspecialchars($row['created_at']) ?>"
        data-out-datetime="<?= htmlspecialchars($row['out_datetime'] ?? '') ?>"
        data-in-datetime="<?= htmlspecialchars($row['in_datetime'] ?? '') ?>"
        data-km-out="<?= htmlspecialchars($row['km_out'] ?? '') ?>"
        data-km-in="<?= htmlspecialchars($row['km_in'] ?? '') ?>"
        data-total-km="<?= htmlspecialchars($row['total_km'] ?? '') ?>"
        data-total-hours="<?= htmlspecialchars($totalHours ?? '') ?>"
        data-security-guard="<?= htmlspecialchars($row['security_guard_name'] ?? '') ?>">
        
        <td data-label="ID"><?= $row['id'] ?></td>

       <!-- Applicant Column -->
       <td data-label="Applicant" 
    data-name="<?= htmlspecialchars($row['applicant_name']) ?>"
    data-mobile="<?= htmlspecialchars($row['mobile']) ?>"
    data-email="<?= htmlspecialchars($row['email']) ?>"
    data-booking-for="<?= htmlspecialchars($row['booking_for']) ?>"
    data-other-person="<?= htmlspecialchars($row['other_person_name'] ?? '') ?>"
    data-staff-type="<?= htmlspecialchars($row['staff_type']) ?>">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-2">
        <div>

            <!-- Booking For -->
            <div class="small mb-1">
                <strong>Booking For:</strong>
                <span class="badge bg-secondary">
                    <?= htmlspecialchars($row['booking_for']) ?>
                </span>
            </div>

            <?php if($row['booking_for'] == "Someone" && !empty($row['other_person_name'])){ ?>
                <div class="fw-semibold">
                    <i class="bi bi-person-badge text-success"></i>
                    <?= htmlspecialchars($row['other_person_name']) ?>
                </div>

                <div class="small text-muted">
                    <i class="bi bi-person-fill"></i>
                    Applicant: <?= htmlspecialchars($row['applicant_name']) ?>
                </div>

            <?php } else { ?>

                <div class="fw-semibold">
                    <i class="bi bi-person-fill text-primary"></i>
                    <?= htmlspecialchars($row['applicant_name']) ?>
                </div>

            <?php } ?>

            <!-- Staff Type -->
            <div class="mt-1">
                <?php
                $staffType = $row['staff_type'];
                $staffBadge = ($staffType == "Permanent") ? "primary" : "warning text-dark";
                echo "<span class='badge bg-$staffBadge px-3 py-2'>$staffType</span>";
                ?>
            </div>

            <!-- Contact -->
            <div class="small text-muted mt-1">
                <i class="bi bi-phone"></i> <?= htmlspecialchars($row['mobile']) ?><br>
                <i class="bi bi-envelope"></i> <?= htmlspecialchars($row['email']) ?>
            </div>

        </div>
    </div>
</td>

        <!-- Journey Column (unchanged) -->
        <td data-label="Tour Details" data-tour="<?= $row['tour_approved'] == 1 ? 'Attached' : 'N.A' ?>"
            data-from="<?= htmlspecialchars(date('d/m/Y H:i', strtotime($row['datetime_from']))) ?>"
            data-to="<?= htmlspecialchars(date('d/m/Y H:i', strtotime($row['datetime_to']))) ?>"
            data-place="<?= htmlspecialchars($row['place_of_visit']) ?>"
            data-forwarded="<?= htmlspecialchars($row['forwarded_admin_name']) ?>"
            data-purpose="<?= htmlspecialchars($row['purpose']) ?>"
            data-date="<?= strtotime($row['datetime_from']) ?>">
            <div class="p-3 rounded-3 shadow-sm bg-white small">
                <!-- TOUR ORDER -->
                <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-file-earmark-check-fill text-primary"></i>
                        <span class="fw-semibold">Tour Order:</span>
                    </div>
                    <?php if($row['tour_approved'] == 1 && !empty($row['tour_order_pdf'])){ ?>
                    <a href="view_tour_pdf.php?file=<?= urlencode($row['tour_order_pdf']) ?>"
                        target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-eye-fill"></i> View
                    </a>
                    <?php } else { ?>
                    <span class="badge bg-secondary">
                        <i class="bi bi-x-circle-fill"></i> N/A
                    </span>
                    <?php } ?>
                </div>

                <!-- JOURNEY -->
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <i class="bi bi-calendar-event text-primary"></i>
                    <span class="fw-semibold">Journey:</span>
                    <span>
                        <?= date("d/m/Y H:i", strtotime($row['datetime_from'])) ?>
                        →
                        <?= date("d/m/Y H:i", strtotime($row['datetime_to'])) ?>
                    </span>
                </div>

                <!-- FORWARDED -->
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <i class="bi bi-share-fill text-secondary"></i>
                    <span class="fw-semibold">Forwarded:</span>
                    <span><?= $row['forwarded_admin_name'] ?></span>
                </div>

                <!-- Source -->
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <i class="bi bi-geo-alt-fill text-success"></i>
                    <span class="fw-semibold">Source:</span>
                    <span><?= !empty($row['source']) ? htmlspecialchars($row['source']) : 'N/A' ?></span>
                </div>

                <!-- DESTINATION -->
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <i class="bi bi-geo-alt-fill text-success"></i>
                    <span class="fw-semibold">Destination:</span>
                    <span><?= $row['place_of_visit'] ?></span>
                </div>

                <!-- PURPOSE -->
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <i class="bi bi-chat-left-text text-info"></i>
                    <span class="fw-semibold">Purpose:</span>
                    <span><?= $row['purpose'] ?></span>
                </div>

                <!-- CO-TRAVELERS -->
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <i class="bi bi-people-fill text-warning"></i>
                    <span class="fw-semibold">Co-Travelers:</span>
                    <?php 
                    if(!empty($coTrav)) { 
                        $coList = explode(", ", $coTrav);
                        foreach($coList as $ct){
                            echo "<span class='badge bg-secondary me-1'>".$ct."</span>";
                        }
                    } else { 
                    ?>
                    <span class="text-muted">None</span>
                    <?php } ?>
                </div>
            </div>
        </td>

        <!-- Assignment Column (unchanged) -->
        <td data-label="Assignment" data-driver="<?= htmlspecialchars($driver['driver_name'] ?? '') ?>"
            data-driver-mobile="<?= htmlspecialchars($driver['driver_mob'] ?? '') ?>"
            data-vehicle-type="<?= htmlspecialchars($vehicle['vehicle_type'] ?? '') ?>"
            data-vehicle-name="<?= htmlspecialchars($vehicle['vehicle_name'] ?? '') ?>"
            data-vehicle-no="<?= htmlspecialchars($vehicle['vehicle_no'] ?? '') ?>">
            <?php if($driver || $vehicle){ ?>
            <div class="small border rounded p-2 bg-light">
                <!-- DRIVER SECTION -->
                <div class="mb-2">
                    <div class="fw-semibold text-primary">
                        <i class="bi bi-person-badge-fill"></i> Driver
                    </div>
                    <?php if($driver){ ?>
                    <div>
                        <?= $driver['driver_name'] ?><br>
                        <i class="bi bi-phone text-muted"></i>
                        <span class="text-muted"><?= $driver['driver_mob'] ?></span>
                    </div>
                    <?php } else { ?>
                    <span class="text-muted">Not Assigned</span>
                    <?php } ?>
                </div>
                <!-- VEHICLE SECTION -->
                <div>
                    <div class="fw-semibold text-success">
                        <i class="bi bi-truck"></i> Vehicle
                    </div>
                    <?php if($vehicle){ ?>
                    <div>
                        <?= $vehicle['vehicle_type'] ?> - <?= $vehicle['vehicle_name'] ?><br>
                        <i class="bi bi-hash text-muted"></i>
                        <span class="text-muted"><?= $vehicle['vehicle_no'] ?></span>
                    </div>
                    <?php } else { ?>
                    <span class="text-muted">Not Assigned</span>
                    <?php } ?>
                </div>
            </div>
            <?php } else { ?>
            <span class="text-muted">Not Assigned</span>
            <?php } ?>
        </td>

        <!-- NEW: Security Movement Column -->
        <td data-label="Security Movement">
            <div class="security-movement-card small">
                <?php if(!empty($row['out_datetime']) || !empty($row['in_datetime']) || !empty($row['km_out']) || !empty($row['km_in']) || !empty($row['total_km']) || !empty($totalHours) || !empty($row['security_guard_name'])): ?>
                    
                    <!-- Out Time -->
                    <?php if(!empty($row['out_datetime'])): ?>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-box-arrow-right text-warning"></i>
                        <span class="fw-semibold">Out:</span>
                        <span><?= date('d/m/Y H:i', strtotime($row['out_datetime'])) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <!-- In Time -->
                    <?php if(!empty($row['in_datetime'])): ?>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-box-arrow-in-left text-info"></i>
                        <span class="fw-semibold">In:</span>
                        <span><?= date('d/m/Y H:i', strtotime($row['in_datetime'])) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <!-- KM Out -->
                    <?php if(!empty($row['km_out'])): ?>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-speedometer2 text-success"></i>
                        <span class="fw-semibold">KM Out:</span>
                        <span><?= $row['km_out'] ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <!-- KM In -->
                    <?php if(!empty($row['km_in'])): ?>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-speedometer2 text-danger"></i>
                        <span class="fw-semibold">KM In:</span>
                        <span><?= $row['km_in'] ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Total KM -->
                    <?php if(!empty($row['total_km'])): ?>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-graph-up-arrow text-primary"></i>
                        <span class="fw-semibold">Total KM:</span>
                        <span class="badge bg-primary"><?= $row['total_km'] ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Total Hours -->
                    <?php if(!empty($totalHours)): ?>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-clock-history text-secondary"></i>
                        <span class="fw-semibold">Total Hours:</span>
                        <span class="badge bg-secondary"><?= $totalHours ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Security Guard -->
                    <?php if(!empty($row['security_guard_name'])): ?>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-shield-lock-fill text-dark"></i>
                        <span class="fw-semibold">Guard:</span>
                        <span><?= $row['security_guard_name'] ?></span>
                    </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <span class="text-muted fst-italic">
                        <i class="bi bi-dash-circle"></i> Not Completed
                    </span>
                <?php endif; ?>
            </div>
        </td>

        <!-- Status Column (unchanged) -->
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
                case "Departure": $badgeClass="secondary"; $icon="hourglass-split"; break;
            }
            echo "<span class='badge bg-$badgeClass'><i class='bi bi-$icon'></i> $status</span>";
            ?>
        </td>

        <!-- Action Column (unchanged) -->
        <td class="text-center">
            <div class="d-flex justify-content-center justify-content-md-center gap-2 flex-wrap">
                <?php 
                $staffType = $row['staff_type'];
                $showAction = false;

                if($staffType == "Permanent" && 
                    $status != "Approved" && 
                    $status != "Rejected") {
                    $showAction = true;
                }

                if($staffType == "Permanent" && 
                    $status == "Completed") {
                    $showAction = false;
                }

                if($status == "Approved") {
                    $showAction = true;
                }

                if($status == "Departure") {
                    $showAction = false;
                }

                if($staffType == "Temporary" && $status == "Recommended") {
                    $showAction = true;
                }

                if($showAction){ ?>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
                    data-bs-target="#approveModal<?= $row['id'] ?>">
                    <i class="bi bi-pencil-square"></i> Action
                </button>
                <?php } ?>

                <button class="btn btn-sm btn-dark exportRow" data-id="<?= $row['id'] ?>">
                    <i class="bi bi-file-earmark-pdf"></i>
                </button>
            </div>
        </td>
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
                                    $d = $conn->query("SELECT * FROM drivers");
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
                                    $v = $conn->query("SELECT * FROM vehicles");
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
                                        <button name="reject" class="btn btn-danger"><i class="bi bi-x-circle-fill"></i>
                                            Reject</button>
                                    </div>

                                </form>
                            </div>
                        </div>
                    </div>
    </tr>
    <?php } } else { ?>
    <tr>
        <td colspan="7" class="text-center text-white py-3">No applications found</td>
    </tr>
    <?php } ?>
</tbody>
            </table>
        </div>
        <nav class="mt-3">
            <ul class="pagination justify-content-end" id="pagination"></ul>
        </nav>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

    <script>
     document.getElementById("exportAllCSV").addEventListener("click", function () {
    // Configuration object for easy customization
    const config = {
        filename: "vehicle_applications",
        includeFilteredOnly: true, // Set to false to always export all rows
        dateFormat: "dd/mm/yyyy hh:MM TT", // Excel-friendly format
        addBOM: true, // Add Byte Order Mark for UTF-8 in Excel
        formatPhoneNumbers: true, // Keep leading zeros in phone numbers
        showExportSummary: true
    };
    
    // Get rows based on configuration
    let allRows = document.querySelectorAll("tbody tr");
    let visibleRows = Array.from(allRows).filter(tr => 
        window.getComputedStyle(tr).display !== "none"
    );
    
    // Determine which rows to export
    let rowsToExport = (config.includeFilteredOnly && visibleRows.length > 0) 
        ? visibleRows 
        : Array.from(allRows);
    
    if (rowsToExport.length === 0) {
        alert("No data available to export!");
        return;
    }
    
    // Define column groups for better organization
    const columnGroups = {
        identification: ["ID", "Application ID"],
        booking: ["Booked For", "Other Person", "Applicant", "Contact"],
        journey: ["From", "To", "Place", "Purpose"],
        assignment: ["Driver", "Vehicle", "Registration"],
        tracking: ["Out Time", "In Time", "KM", "Hours"],
        status: ["Status", "Remarks", "Submitted"]
    };
    
    // Professional headers with descriptive names
    let csvHeader = [
        "🔑 App ID",
        "👤 Booked For",
        "👥 Other Person",
        "👨‍💼 Applicant Name",
        "📱 Mobile",
        "📧 Email",
        "🏢 Staff Type",
        "📍 From",
        "🎯 To",
        "🏞️ Place",
        "📋 Purpose",
        "↪️ Forwarded To",
        "👪 Co-Travelers",
        "🚗 Driver",
        "📞 Driver Phone",
        "🚘 Vehicle Type",
        "🚙 Vehicle Name",
        "🔢 Vehicle No",
        "📄 Tour Order",
        "⏱️ Departure",
        "⏱️ Return",
        "📊 KM Out",
        "📊 KM In",
        "📈 Total KM",
        "⏳ Duration",
        "🛡️ Security",
        "📝 Remarks",
        "⚡ Status",
        "📅 Submitted"
    ];
    
    // Add BOM for Excel UTF-8 support
    let csv = config.addBOM ? "\uFEFF" : "";
    csv += csvHeader.map(header => `"${header}"`).join(",") + "\n";

    // Helper function to format data for Excel
    const formatForExcel = (value, type = 'text') => {
        if (!value && value !== 0) return "";
        
        value = String(value).trim();
        
        switch(type) {
            case 'phone':
                // Keep leading zeros and format as text
                return `="${value}"`;
            case 'date':
                try {
                    let date = new Date(value);
                    if (!isNaN(date.getTime())) {
                        // Format: DD/MM/YYYY HH:MM AM/PM
                        return date.toLocaleString('en-IN', {
                            day: '2-digit',
                            month: '2-digit',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: true
                        });
                    }
                } catch {}
                return value;
            case 'number':
                // Ensure numbers are treated as numbers
                return value.replace(/,/g, '');
            default:
                return value;
        }
    };

    rowsToExport.forEach((tr, index) => {
        const tds = tr.querySelectorAll("td");
        if (tds.length < 7) return;

        // Get raw data
        let rowData = {
            id: tds[0].innerText.trim(),
            bookingFor: tds[1].getAttribute("data-booking-for") || "",
            otherPerson: tds[1].getAttribute("data-other-person") || "",
            name: tds[1].getAttribute("data-name") || "",
            mobile: tds[1].getAttribute("data-mobile") || "",
            email: tds[1].getAttribute("data-email") || "",
            staffType: tds[1].getAttribute("data-staff-type") || "",
            from: tds[2].getAttribute("data-from") || "",
            to: tds[2].getAttribute("data-to") || "",
            place: tds[2].getAttribute("data-place") || "",
            purpose: tds[2].getAttribute("data-purpose") || "",
            forwarded: tr.getAttribute("data-forwarded") || "",
            cotraveler: tr.getAttribute("data-cotraveler") || "",
            driver: tds[3].getAttribute("data-driver") || "",
            driverMobile: tds[3].getAttribute("data-driver-mobile") || "",
            vehicleType: tds[3].getAttribute("data-vehicle-type") || "",
            vehicleName: tds[3].getAttribute("data-vehicle-name") || "",
            vehicleNo: tds[3].getAttribute("data-vehicle-no") || "",
            tour: tds[2].getAttribute("data-tour") || "",
            outDateTime: tr.getAttribute("data-out-datetime") || "",
            inDateTime: tr.getAttribute("data-in-datetime") || "",
            kmOut: tr.getAttribute("data-km-out") || "",
            kmIn: tr.getAttribute("data-km-in") || "",
            totalKm: tr.getAttribute("data-total-km") || "",
            totalHours: tr.getAttribute("data-total-hours") || "",
            security: tr.getAttribute("data-security-guard") || "",
            remark: tr.getAttribute("data-remark") || "",
            status: tds[5].innerText.trim(),
            submitted: tr.getAttribute("data-submitted") || ""
        };

        // Build row with proper formatting
        let row = [
            // App ID (as text to preserve leading zeros)
            config.formatPhoneNumbers ? `="${rowData.id}"` : rowData.id,
            
            // Booking info
            rowData.bookingFor,
            rowData.otherPerson,
            rowData.name,
            
            // Contact (format phone)
            config.formatPhoneNumbers ? `="${rowData.mobile}"` : rowData.mobile,
            rowData.email,
            rowData.staffType,
            
            // Journey
            rowData.from,
            rowData.to,
            rowData.place,
            rowData.purpose,
            rowData.forwarded,
            rowData.cotraveler,
            
            // Assignment
            rowData.driver,
            config.formatPhoneNumbers ? `="${rowData.driverMobile}"` : rowData.driverMobile,
            rowData.vehicleType,
            rowData.vehicleName,
            rowData.vehicleNo,
            
            // Tour
            rowData.tour,
            
            // Timestamps
            formatForExcel(rowData.outDateTime, 'date'),
            formatForExcel(rowData.inDateTime, 'date'),
            rowData.kmOut,
            rowData.kmIn,
            rowData.totalKm,
            rowData.totalHours,
            rowData.security,
            
            // Status
            rowData.remark,
            rowData.status,
            formatForExcel(rowData.submitted, 'date')
        ];

        // Escape and quote each field
        row = row.map(item => {
            if (item === null || item === undefined) return '""';
            let cleanItem = String(item).replace(/"/g, '""');
            return `"${cleanItem}"`;
        }).join(",");
        
        csv += row + "\n";
    });

    // Generate filename with metadata
    let now = new Date();
    let timestamp = now.toISOString().slice(0,19).replace(/[-:]/g, '').replace('T', '_');
    let recordCount = rowsToExport.length;
    let filterStatus = (config.includeFilteredOnly && visibleRows.length < allRows.length) 
        ? `_filtered_${visibleRows.length}` 
        : `_all_${allRows.length}`;
    
    let filename = `${config.filename}${filterStatus}_${timestamp}.csv`;

    // Create download
    let blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    let link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.click();
    
    // Show summary
    if (config.showExportSummary) {
        let summary = `✅ Export Complete!\n`;
        summary += `📊 Records: ${rowsToExport.length}\n`;
        summary += `📁 File: ${filename}\n`;
        if (config.includeFilteredOnly && visibleRows.length < allRows.length) {
            summary += `🔍 Filtered: Yes (showing ${visibleRows.length} of ${allRows.length} total)`;
        }
        alert(summary);
    }
});

// ---------------- Export Single Row PDF ----------------
       document.addEventListener("click", function (e) {
    const btn = e.target.closest(".exportRow");
    if (!btn) return;

    e.preventDefault();
    e.stopPropagation();

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'pt', 'a4');

    const tr = btn.closest("tr");
    if (!tr) {
        alert("Row not found");
        return;
    }

    const tds = tr.querySelectorAll("td");
    if (tds.length < 7) {
        alert("Table structure mismatch");
        return;
    }

    const pageWidth = doc.internal.pageSize.getWidth();

    doc.setFontSize(18);
    doc.setFont("helvetica", "bold");
    doc.text(`Vehicle Application ID: ${tds[0].innerText.trim()}`, pageWidth / 2, 40, { align: "center" });

    const getAttr = (el, attr) => el?.getAttribute(attr) || "";

    const data = [
         ["Booked For", getAttr(tds[1], "data-booking-for")],
        ["Other Person Name", getAttr(tds[1], "data-other-person")],
        ["Applicant", getAttr(tds[1], "data-name")],
        ["Staff Type", getAttr(tds[1], "data-staff-type")],
        ["Mobile", getAttr(tds[1], "data-mobile")],
        ["Email", getAttr(tds[1], "data-email")],
        ["Journey From", getAttr(tds[2], "data-from")],
        ["Journey To", getAttr(tds[2], "data-to")],
        ["Place", getAttr(tds[2], "data-place")],
        ["Purpose", getAttr(tds[2], "data-purpose")],
        ["Tour Order", getAttr(tds[2], "data-tour")],
        ["Forwarded To", tr.getAttribute("data-forwarded") || ""],
        ["Co-Travelers", tr.getAttribute("data-cotraveler") || ""],
        ["Driver", getAttr(tds[3], "data-driver")],
        ["Driver Mobile", getAttr(tds[3], "data-driver-mobile")],
        ["Vehicle Type", getAttr(tds[3], "data-vehicle-type")],
        ["Vehicle Name", getAttr(tds[3], "data-vehicle-name")],
        ["Vehicle No", getAttr(tds[3], "data-vehicle-no")],
        // Security Movement Details (NEW)
        ["Out Date Time", tr.getAttribute("data-out-datetime") || ""],
        ["In Date Time", tr.getAttribute("data-in-datetime") || ""],
        ["KM Out", tr.getAttribute("data-km-out") || ""],
        ["KM In", tr.getAttribute("data-km-in") || ""],
        ["Total KM", tr.getAttribute("data-total-km") || ""],
        ["Total Hours", tr.getAttribute("data-total-hours") || ""],
        ["Security Guard", tr.getAttribute("data-security-guard") || ""],
        ["Remark", tr.getAttribute("data-remark") || ""],
        ["Status", tds[5].innerText.trim()],
        ["Submitted On", tr.getAttribute("data-submitted") || ""]
    ];

    doc.autoTable({
        startY: 70,
        head: [["Field", "Details"]],
        body: data,
        theme: "grid",
        styles: { fontSize: 10, cellPadding: 5 },
        headStyles: { fillColor: [41, 128, 185], textColor: 255 }
    });

    doc.save(`application_${tds[0].innerText.trim().replace('#', '')}.pdf`);
        });

   // ---------------- Export All PDF ----------------
document.getElementById("exportAllPDF").addEventListener("click", function () {

const { jsPDF } = window.jspdf;
const doc = new jsPDF('p', 'pt', 'a4');

const pageWidth = doc.internal.pageSize.getWidth();

const getAttr = (el, attr) => el?.getAttribute(attr) || "";

let firstRecord = true;

document.querySelectorAll("tbody tr").forEach((tr) => {

    const tds = tr.querySelectorAll("td");
    if (tds.length < 7) return;

    if (!firstRecord) {
        doc.addPage();
    }
    firstRecord = false;

    doc.setFontSize(18);
    doc.setTextColor(21,101,192);
    doc.setFont("helvetica","bold");

    doc.text(
        `Vehicle Application ID: ${tds[0].innerText.trim()}`,
        pageWidth / 2,
        40,
        { align: "center" }
    );

    const data = [

        ["Booked For", getAttr(tds[1], "data-booking-for")],
        ["Other Person Name", getAttr(tds[1], "data-other-person")],
        ["Applicant", getAttr(tds[1], "data-name")],
        ["Staff Type", getAttr(tds[1], "data-staff-type")],
        ["Mobile", getAttr(tds[1], "data-mobile")],
        ["Email", getAttr(tds[1], "data-email")],

        ["Journey From", getAttr(tds[2], "data-from")],
        ["Journey To", getAttr(tds[2], "data-to")],
        ["Place", getAttr(tds[2], "data-place")],
        ["Purpose", getAttr(tds[2], "data-purpose")],
        ["Tour Order", getAttr(tds[2], "data-tour")],

        ["Forwarded To", tr.getAttribute("data-forwarded") || ""],
        ["Co-Travelers", tr.getAttribute("data-cotraveler") || ""],

        ["Driver", getAttr(tds[3], "data-driver")],
        ["Driver Mobile", getAttr(tds[3], "data-driver-mobile")],
        ["Vehicle Type", getAttr(tds[3], "data-vehicle-type")],
        ["Vehicle Name", getAttr(tds[3], "data-vehicle-name")],
        ["Vehicle No", getAttr(tds[3], "data-vehicle-no")],

        ["Out Date Time", tr.getAttribute("data-out-datetime") || ""],
        ["In Date Time", tr.getAttribute("data-in-datetime") || ""],
        ["KM Out", tr.getAttribute("data-km-out") || ""],
        ["KM In", tr.getAttribute("data-km-in") || ""],
        ["Total KM", tr.getAttribute("data-total-km") || ""],
        ["Total Hours", tr.getAttribute("data-total-hours") || ""],
        ["Security Guard", tr.getAttribute("data-security-guard") || ""],
        ["Remark", tr.getAttribute("data-remark") || ""],

        ["Status", tds[5].innerText.trim()],
        ["Submitted On", tr.getAttribute("data-submitted") || ""]
    ];

    doc.autoTable({
        startY: 70,
        head: [["Field", "Details"]],
        body: data,
        theme: "grid",
        styles: { fontSize: 10, cellPadding: 5 },
        headStyles: { fillColor: [41,128,185], textColor: 255 }
    });

});

doc.save("All_Vehicle_Applications.pdf");

});

// ---------------- Export Completed All PDF ----------------
document.getElementById("exportAllCompletedPDF").addEventListener("click", function () {

const { jsPDF } = window.jspdf;
const doc = new jsPDF('p', 'pt', 'a4');
const pageWidth = doc.internal.pageSize.getWidth();

// Helper function to safely get an attribute from an element
const getAttr = (el, attr) => el?.getAttribute(attr) || "";

let firstRecord = true; // To track if we need to add a new page

// Select all table rows in the tbody
document.querySelectorAll("tbody tr").forEach((tr) => {
    const tds = tr.querySelectorAll("td");
    
    // Basic validation: ensure we have all expected columns (7 TDs)
    if (tds.length < 7) return;

    // --- CRITICAL CHECK: Only process rows with 'Completed' status ---
    // The status is in the 6th column (index 5, since index 0 is '#')
    const statusText = tds[5].innerText.trim().toLowerCase();
    if (statusText !== "completed") return; // <-- SKIP non-completed rows
    // ----------------------------------------------------------------

    // If this is NOT the first record to be added, add a new page in the PDF
    if (!firstRecord) {
        doc.addPage();
    }
    firstRecord = false; // Mark that we have added at least one record

    // --- PDF Header (Title) ---
    doc.setFontSize(18);
    doc.setTextColor(21, 101, 192);
    doc.setFont("helvetica", "bold");
    doc.text(
        `Vehicle Application ID: ${tds[0].innerText.trim()}`, // Get ID from the first TD
        pageWidth / 2,
        40,
        { align: "center" }
    );

    // --- Prepare Data for the Table ---
    // *Crucial:* The data is read from the data-* attributes you defined in your PHP.
    // We also use fallbacks like tr.getAttribute() for attributes on the <tr> itself.
    const tableData = [
        ["Booking For", getAttr(tds[1], "data-booking-for")],
        ["Other Person Name", getAttr(tds[1], "data-other-person")],
        ["Applicant", getAttr(tds[1], "data-name")],
        ["Staff Type", getAttr(tds[1], "data-staff-type")],
        ["Mobile", getAttr(tds[1], "data-mobile")],
        ["Email", getAttr(tds[1], "data-email")],

        ["Journey From", getAttr(tds[2], "data-from")],
        ["Journey To", getAttr(tds[2], "data-to")],
        ["Place", getAttr(tds[2], "data-place")],
        ["Purpose", getAttr(tds[2], "data-purpose")],
        ["Tour Order", getAttr(tds[2], "data-tour")],

        ["Forwarded To", tr.getAttribute("data-forwarded") || ""],
        ["Co-Travelers", tr.getAttribute("data-cotraveler") || ""],

        ["Driver", getAttr(tds[3], "data-driver")],
        ["Driver Mobile", getAttr(tds[3], "data-driver-mobile")],
        ["Vehicle Type", getAttr(tds[3], "data-vehicle-type")],
        ["Vehicle Name", getAttr(tds[3], "data-vehicle-name")],
        ["Vehicle No", getAttr(tds[3], "data-vehicle-no")],

        ["Out Date Time", tr.getAttribute("data-out-datetime") || ""],
        ["In Date Time", tr.getAttribute("data-in-datetime") || ""],
        ["KM Out", tr.getAttribute("data-km-out") || ""],
        ["KM In", tr.getAttribute("data-km-in") || ""],
        ["Total KM", tr.getAttribute("data-total-km") || ""],
        ["Total Hours", tr.getAttribute("data-total-hours") || ""],
        ["Security Guard", tr.getAttribute("data-security-guard") || ""],
        ["Remark", tr.getAttribute("data-remark") || ""],

        ["Status", tds[5].innerText.trim()],
        ["Submitted On", tr.getAttribute("data-submitted") || ""]
    ];

    // --- Generate the Table in the PDF ---
    doc.autoTable({
        startY: 70, // Start Y position after the title
        head: [["Field", "Details"]],
        body: tableData,
        theme: "grid",
        styles: { fontSize: 10, cellPadding: 5 },
        headStyles: { fillColor: [41, 128, 185], textColor: 255 }
    });
});

// Check if any completed records were found
if (firstRecord === true) {
    alert("No applications with 'Completed' status found to export.");
    return;
}

// Save the generated PDF
doc.save("All_Completed_Vehicle_Applications.pdf");
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
        document.addEventListener("DOMContentLoaded", function() {

            const tableBody = document.querySelector("tbody");
            const searchInput = document.getElementById("searchInput");
            const rowsSelect = document.getElementById("rowsPerPage");
            const sortSelect = document.getElementById("sortDate");
            const pagination = document.getElementById("pagination");

            let rows = Array.from(tableBody.querySelectorAll("tr"));
            let rowsPerPage = parseInt(rowsSelect?.value || 10);
            let currentPage = 1;
            let filteredRows = [...rows];

            // ================= SEARCH =================
            function filterRows() {
                const keyword = searchInput.value.toLowerCase().trim();

                filteredRows = rows.filter(row => {

                    const cell = row.querySelector("td[data-name]");
                    if (!cell) return false;

                    const name = cell.dataset.name?.toLowerCase() || "";
                    const mobile = cell.dataset.mobile?.toLowerCase() || "";
                    const email = cell.dataset.email?.toLowerCase() || "";

                    return (
                        name.includes(keyword) ||
                        mobile.includes(keyword) ||
                        email.includes(keyword)
                    );
                });

                currentPage = 1;
                sortRows();
            }

            // ================= SORT =================
            function sortRows() {
                if (!sortSelect) {
                    displayRows();
                    setupPagination();
                    return;
                }

                const sortType = sortSelect.value;

                filteredRows.sort((a, b) => {
                    const dateA = parseInt(a.getAttribute("data-date")) || 0;
                    const dateB = parseInt(b.getAttribute("data-date")) || 0;
                    return sortType === "newest" ? dateB - dateA : dateA - dateB;
                });

                displayRows();
                setupPagination();
            }

            // ================= DISPLAY =================
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
                if (!pagination) return;

                pagination.innerHTML = "";
                const pageCount = Math.ceil(filteredRows.length / rowsPerPage);

                for (let i = 1; i <= pageCount; i++) {

                    const li = document.createElement("li");
                    li.className = "page-item " + (i === currentPage ? "active" : "");

                    const a = document.createElement("a");
                    a.className = "page-link";
                    a.href = "#";
                    a.innerText = i;

                    a.addEventListener("click", function(e) {
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

            rowsSelect?.addEventListener("change", function() {
                rowsPerPage = parseInt(this.value);
                currentPage = 1;
                displayRows();
                setupPagination();
            });

            sortSelect?.addEventListener("change", sortRows);

            // Initial Load
            filterRows();
        });
    </script>
</body>

</html>
<?php 
include("../config/db.php");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if(!isset($_SESSION['user']) || $_SESSION['user']['role']!='forwarded_admin'){
    header("location:login.php");
    exit();
}

$user = $_SESSION['user'];

$res = $conn->query("
    SELECT va.*, u.name AS forwarded_admin 
    FROM vehicle_applications va
    LEFT JOIN users u ON va.forwarded_to = u.id
    WHERE va.forwarded_to = ".$user['id']." 
    AND va.staff_type = 'Temporary'
    ORDER BY va.created_at DESC
    ");
?>

<!DOCTYPE html>
<html>

<head>
    <title>Forwarded Admin Dashboard</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="forward.css" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1">

</head>

<body>
    <?php include ('../header.php'); ?>

    <div class="container py-3 py-md-4">

        <!-- Header -->
        <div
            class="dashboard-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 shadow p-3 rounded">

            <h5 class="mb-2 mb-md-0">
                <i class="bi bi-speedometer2"></i>
                <span class="d-none d-sm-inline">Forwarded Requests Dashboard</span>
                <span class="d-inline d-sm-none">Dashboard</span>
            </h5>

            <a href="logout.php" class="btn btn-danger btn-sm">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>

        <?php if($res->num_rows > 0){ ?>
        <?php while($row=$res->fetch_assoc()){ ?>

        <div class="card app-card mb-4 shadow-sm">

            <!-- Card Header -->
            <div class="card-header bg-dark text-white">
                <div
                    class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">

                    <span class="small">
                        <i class="bi bi-file-earmark-text"></i>
                        Application ID: <?= $row['id'] ?>
                    </span>

                    <?php
            $status = $row['status'];
            $badge="secondary";

            if($status=="Draft") $badge="warning";
            if($status=="Pending") $badge="warning";
            if($status=="Recommended") $badge="success";
            if($status=="Not Recommended") $badge="danger";
            ?>

                    <span class="badge bg-<?= $badge ?> px-3 py-2">
                        <?= $status ?>
                    </span>
                </div>
            </div>

            <div class="card-body">

                <!-- Top Info Row -->
                <div class="row g-3">

                    <!-- Applicant -->
                    <div class="col-12 col-md-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="mb-3">
                                <i class="bi bi-person-circle text-primary"></i> Applicant
                            </h6>

                            <p class="mb-1">
                                <strong>Booking For:</strong>
                                <span class="badge bg-secondary">
                                    <?= $row['booking_for'] ?>
                                </span>
                            </p>

                            <?php if($row['booking_for']=="Someone" && !empty($row['other_person_name'])){ ?>

                            <p class="mb-1">
                                <strong>Person Name:</strong> <?= $row['other_person_name'] ?>
                            </p>

                            <p class="mb-1">
                                <strong>Applicant Name:</strong> <?= $row['applicant_name'] ?>
                            </p>

                            <?php } else { ?>

                            <p class="mb-1">
                                <strong>Name:</strong> <?= $row['applicant_name'] ?>
                            </p>

                            <?php } ?>
                            <p class="mb-1">
                                <strong>Staff Type:</strong>
                                <span class="badge bg-info text-dark">
                                    <?= $row['staff_type'] ?>
                                </span>
                            </p>
                            <p class="mb-1"><strong>Mobile:</strong> <?= $row['mobile'] ?></p>
                            <p class="mb-0"><strong>Email:</strong> <?= $row['email'] ?: 'N/A' ?></p>
                        </div>
                    </div>

                    <!-- Journey -->
                    <div class="col-12 col-md-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="mb-3">
                                <i class="bi bi-geo-alt-fill text-success"></i> Journey
                            </h6>

                            <p class="mb-1"><strong>Vehicle:</strong> <?= $row['vehicle_required'] ?></p>
                            <p class="mb-1"><strong>From:</strong>
                                <?= date("d M Y, h:i A", strtotime($row['datetime_from'])) ?></p>
                            <p class="mb-1"><strong>To:</strong>
                                <?= date("d M Y, h:i A", strtotime($row['datetime_to'])) ?></p>
                            <p class="mb-1"><strong>Source:</strong> <?= !empty($row['source']) ? htmlspecialchars($row['source']) : 'N/A' ?></p>
                            <p class="mb-1"><strong>Place:</strong> <?= $row['place_of_visit'] ?></p>
                            <p class="mb-0"><strong>Purpose:</strong> <?= $row['purpose'] ?></p>
                        </div>
                    </div>

                </div>

                <hr>

                <!-- Co Travelers -->
                <div class="mb-3">
                    <h6 class="mb-3">
                        <i class="bi bi-people-fill text-warning"></i> Co-Travelers
                    </h6>

                    <div class="row g-2">
                        <?php
                $has=false;
                for($i=1;$i<=6;$i++){
                    if(!empty($row["cotraveler$i"])){
                        $has=true;
                        echo "<div class='col-12 col-sm-6 col-lg-3'>
                                <span class='badge bg-light text-dark border w-100 text-start p-2'>
                                    <i class='bi bi-person-plus'></i> ".$row["cotraveler$i"]."
                                </span>
                              </div>";
                    }
                }
                if(!$has){
                    echo "<p class='text-muted'>No Co-Travelers</p>";
                }
                ?>
                    </div>
                </div>

                <hr>

                <!-- Tour Order -->
                <div class="mb-3">
                    <h6 class="mb-3">
                        <i class="bi bi-file-earmark-pdf text-danger"></i> Tour Order
                    </h6>
                    <?php if($row['tour_approved'] == 1){ ?>
                    <?php if(!empty($row['tour_order_pdf'])){ ?>
                    <div>
                        <a href="view_tour_pdf.php?file=<?= $row['tour_order_pdf'] ?>" target="_blank"
                            class="btn btn-outline-primary btn-sm w-100 w-md-auto">
                            View Tour Order
                        </a>
                    </div>
                    <?php } ?>
                    <?php } else { ?>
                    <span class="badge bg-secondary">
                        Not Attached
                    </span>
                    <?php } ?>
                </div>

                <!-- Action Buttons -->
                <?php if($row['status']=="Pending" || $row['status']=="Draft"){ ?>
                <div class="row g-2 mt-3">
                    <div class="col-12 col-md-4">
                        <form method="POST" action="recommend.php?id=<?= $row['id'] ?>">
                            <button name="type" value="Recommended" class="btn btn-success btn-sm w-100">
                                Recommend
                            </button>
                        </form>
                    </div>

                    <div class="col-12 col-md-4">
                        <form method="POST" action="recommend.php?id=<?= $row['id'] ?>">
                            <button name="type" value="Not Recommended" class="btn btn-danger btn-sm w-100">
                                Reject
                            </button>
                        </form>
                    </div>
                </div>
                <?php } ?>

                <p class="text-muted small mt-4 mb-0">
                    Submitted: <?= date("d M Y, h:i A", strtotime($row['created_at'])) ?>
                </p>

            </div>
        </div>

        <?php } ?>
        <?php } else { ?>

        <div class="alert alert-info text-center shadow-sm">
            No forwarded applications found.
        </div>

        <?php } ?>

    </div>

</body>

</html>
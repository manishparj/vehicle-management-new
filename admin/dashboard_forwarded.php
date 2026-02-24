<?php 
include("../config/db.php");

if(!isset($_SESSION['user']) || $_SESSION['user']['role']!='forwarded_admin'){
    header("location:login.php");
    exit();
}

$user = $_SESSION['user'];

$res=$conn->query("
SELECT va.*, u.name AS forwarded_admin_name 
FROM vehicle_applications va
LEFT JOIN users u ON va.forwarded_to=u.id
WHERE va.forwarded_to=".$user['id']."
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

</head>

<body>

    <div class="container py-4">

        <!-- Header -->
        <div class="dashboard-header d-flex justify-content-between align-items-center mb-4 shadow">
            <h4 class="mb-0">
                <i class="bi bi-speedometer2"></i> Forwarded Requests Dashboard
            </h4>
            <a href="logout.php" class="btn btn-danger logout-btn">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>

        <?php if($res->num_rows > 0){ ?>

        <?php while($row=$res->fetch_assoc()){ ?>

        <div class="card app-card mb-4">

            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <span>
                    <i class="bi bi-file-earmark-text"></i>
                    Application ID: <?= $row['id'] ?>
                </span>

                <?php
$status = $row['status'];
$badge="secondary";

if($status=="Pending") $badge="warning";
if($status=="Recommended") $badge="success";
if($status=="Not Recommended") $badge="danger";
if($status=="Approved") $badge="primary";
if($status=="Rejected") $badge="danger";
if($status=="Completed") $badge="success";
if($status=="Cancelled") $badge="dark";
?>

                <span class="badge bg-<?= $badge ?>">
                    <i class="bi bi-info-circle"></i> <?= $status ?>
                </span>

            </div>

            <div class="card-body">

                <div class="row gy-3">

                    <div class="col-md-6">
                        <h6 class="section-title">
                            <i class="bi bi-person-circle"></i> Applicant Details
                        </h6>
                        <p>
                            <i class="bi bi-person"></i> <b>Name:</b> <?= $row['applicant_name'] ?><br>
                            <i class="bi bi-phone"></i> <b>Mobile:</b> <?= $row['mobile'] ?><br>
                            <i class="bi bi-envelope"></i> <b>Email:</b> <?= $row['email'] ?>
                        </p>
                    </div>

                    <div class="col-md-6">
                        <h6 class="section-title">
                            <i class="bi bi-geo-alt-fill"></i> Journey Details
                        </h6>
                        <p>
                            <i class="bi bi-truck"></i> <b>Vehicle:</b> <?= $row['vehicle_required'] ?><br>
                            <i class="bi bi-calendar-event"></i> <b>From:</b> <?= date("d/m/Y H:i", strtotime($row['datetime_from'])) ?><br>
                            <i class="bi bi-calendar-check"></i> <b>To:</b> <?= date("d/m/Y H:i", strtotime($row['datetime_to'])) ?><br>
                            <i class="bi bi-geo"></i> <b>Place:</b> <?= $row['place_of_visit'] ?><br>
                            <i class="bi bi-chat-left-text"></i> <b>Purpose:</b> <?= $row['purpose'] ?>
                        </p>
                    </div>

                </div>

                <hr>

                <h6 class="section-title">
                    <i class="bi bi-people-fill"></i> Co-Travelers
                </h6>

                <ul class="list-group list-group-flush mb-3">
                    <?php
$hasCoTraveler=false;
for($i=1;$i<=4;$i++){
if(!empty($row["cotraveler$i"])){
$hasCoTraveler=true;
echo "<li class='list-group-item'><i class='bi bi-person-plus'></i> ".$row["cotraveler$i"]."</li>";
}
}
if(!$hasCoTraveler){
echo "<li class='list-group-item text-muted'>No Co-Travelers</li>";
}
?>
                </ul>

                <?php if($row['status']=="Pending"){ ?>

                <form method="POST" action="recommend.php?id=<?= $row['id'] ?>" class="row g-2 align-items-center">

                    <!-- <div class="col-md-8">
                        <textarea name="remark" class="form-control" placeholder="Optional Remark..."></textarea>
                    </div> -->

                    <div class="col-md-2 d-grid">
                        <button name="type" value="Recommended" class="btn btn-success">
                            <i class="bi bi-hand-thumbs-up-fill"></i> Recommend
                        </button>
                    </div>

                    <div class="col-md-2 d-grid">
                        <button name="type" value="Not Recommended" class="btn btn-danger">
                            <i class="bi bi-hand-thumbs-down-fill"></i> Reject
                        </button>
                    </div>

                </form>

                <?php } else { ?>

                <div class="alert alert-light border mt-3">
                    <i class="bi bi-chat-dots-fill text-primary"></i>
                    <b>Remark:</b> <?= $row['remark'] ? $row['remark'] : "No Remark Provided" ?>
                </div>

                <?php } ?>

                <hr>

<h6 class="section-title">
    <i class="bi bi-file-earmark-pdf"></i> Tour Order Details
</h6>

<div class="d-flex align-items-center gap-3 mb-3">

    <?php if($row['tour_approved'] == 1){ ?>
        
        <span class="badge bg-success">
            <i class="bi bi-check-circle-fill"></i> Tour Order Attached
        </span>

        <?php if(!empty($row['tour_order_pdf'])){ ?>
            
            <a href="view_tour_pdf.php?file=<?= $row['tour_order_pdf'] ?>" 
               target="_blank" 
               class="btn btn-sm btn-outline-primary">
                <i class="bi bi-eye-fill"></i> View PDF
            </a>

        <?php } ?>

    <?php } else { ?>

        <span class="badge bg-secondary">
            <i class="bi bi-x-circle-fill"></i> Not Attached
        </span>

    <?php } ?>

</div>

                <p class="text-muted small mt-3">
                    <i class="bi bi-clock-history"></i>
                    Submitted On: <?= $row['created_at'] ?>
                </p>

            </div>
        </div>

        <?php } ?>

        <?php } else { ?>

        <div class="alert alert-info text-center shadow-sm">
            <i class="bi bi-info-circle-fill"></i>
            No forwarded applications found.
        </div>

        <?php } ?>

    </div>

</body>

</html>
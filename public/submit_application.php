<?php
include("../config/db.php");

// Collect form data safely
$staff_type       = $_POST['staff_type'];
$applicant_name   = $_POST['applicant_name'];
$vehicle_required = $_POST['vehicle_required'];
$datetime_from    = $_POST['datetime_from'];
$datetime_to      = $_POST['datetime_to'];
$place_of_visit   = $_POST['place_of_visit'];
$purpose          = $_POST['purpose'];
$cotraveler1      = $_POST['cotraveler1'];
$cotraveler2      = $_POST['cotraveler2'];
$cotraveler3      = $_POST['cotraveler3'];
$cotraveler4      = $_POST['cotraveler4'];
$mobile           = $_POST['mobile'];
$email            = $_POST['email'];
$forwarded_to     = $_POST['forwarded_to'];

// Boolean value (0 or 1)
$tour_approved = (isset($_POST['tour_approved']) && $_POST['tour_approved'] == "1") ? 1 : 0;

$tour_order_pdf = NULL;

// ================= FILE UPLOAD =================
if ($tour_approved == 1 && isset($_FILES['tour_order_pdf']) && $_FILES['tour_order_pdf']['error'] == 0) {

    $uploadDir = "../uploads/tour_orders/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileTmp  = $_FILES['tour_order_pdf']['tmp_name'];
    $fileName = time() . "_" . basename($_FILES['tour_order_pdf']['name']);
    $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // Allow only PDF
    if ($fileExt === "pdf") {

        // Limit file size (2MB)
        if ($_FILES['tour_order_pdf']['size'] <= 2 * 1024 * 1024) {

            $targetFile = $uploadDir . $fileName;

            if (move_uploaded_file($fileTmp, $targetFile)) {
                $tour_order_pdf = $fileName;
            }
        } else {
            die("File too large. Max 2MB allowed.");
        }

    } else {
        die("Only PDF files allowed.");
    }
}

// ================= INSERT DATA (PREPARED STATEMENT) =================

// ================= INSERT DATA (PREPARED STATEMENT) =================

$stmt = $conn->prepare("INSERT INTO vehicle_applications
(staff_type, applicant_name, vehicle_required, datetime_from, datetime_to, place_of_visit, purpose,
cotraveler1, cotraveler2, cotraveler3, cotraveler4,
mobile, email, forwarded_to, tour_approved, tour_order_pdf)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmt->bind_param(
    "sssssssssssssiis",
    $staff_type,
    $applicant_name,
    $vehicle_required,
    $datetime_from,
    $datetime_to,
    $place_of_visit,
    $purpose,
    $cotraveler1,
    $cotraveler2,
    $cotraveler3,
    $cotraveler4,
    $mobile,
    $email,
    $forwarded_to,
    $tour_approved,
    $tour_order_pdf
);

$stmt->execute();

echo "<script>alert('Application Submitted Successfully');window.location='index.php';</script>";
?>
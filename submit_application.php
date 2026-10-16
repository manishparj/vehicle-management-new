<?php
session_start(); // Start session at the very beginning

include("./config/db.php");
include("./config/email_config.php");

// Include PHPMailer manually (if not using composer)
require_once("./vendor/PHPMailer/src/PHPMailer.php");
require_once("./vendor/phpmailer/src/SMTP.php");
require_once("./vendor/phpmailer/src/Exception.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// ================= METHOD GUARD =================
// Everything below this line must only ever run for a genuine POST.
// (Previously, GET requests fell through the old if-block and still
// reached the INSERT statement with every field empty.)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request method.');
}

// ================= COLLECT + SANITIZE INPUT =================
$booking_for       = trim($_POST['booking_for'] ?? '');
$other_person_name = trim($_POST['other_person_name'] ?? '');
$staff_type        = trim($_POST['staff_type'] ?? '');
$applicant_name    = trim($_POST['applicant_name'] ?? '');
$vehicle_required  = trim($_POST['vehicle_required'] ?? '');
$source_type       = trim($_POST['source_type'] ?? '');
$custom_source     = trim($_POST['custom_source'] ?? '');
$datetime_from     = trim($_POST['datetime_from'] ?? '');
$datetime_to       = trim($_POST['datetime_to'] ?? '');
$place_of_visit    = trim($_POST['place_of_visit'] ?? '');
$purpose           = trim($_POST['purpose'] ?? '');
$cotraveler1       = trim($_POST['cotraveler1'] ?? '');
$cotraveler2       = trim($_POST['cotraveler2'] ?? '');
$cotraveler3       = trim($_POST['cotraveler3'] ?? '');
$cotraveler4       = trim($_POST['cotraveler4'] ?? '');
$cotraveler5       = trim($_POST['cotraveler5'] ?? '');
$cotraveler6       = trim($_POST['cotraveler6'] ?? '');
$mobile            = trim($_POST['mobile'] ?? '');
$email             = trim($_POST['email'] ?? '');
$forwarded_to      = trim($_POST['forwarded_to'] ?? '');

// ================= REQUIRED-FIELD VALIDATION (SERVER SIDE) =================
// The old script relied entirely on the HTML "required" attribute, which is
// client-side only and does nothing to stop a direct POST (curl/Postman/bots)
// or a stale/replayed form submission. This is what let blank rows through.
$errors = [];

if ($booking_for === '')      $errors[] = 'Booking For is required.';
if ($staff_type === '')       $errors[] = 'Staff Type is required.';
if ($applicant_name === '')   $errors[] = 'Applicant Name is required.';
if ($vehicle_required === '') $errors[] = 'Vehicle Required is required.';
if ($source_type === '')      $errors[] = 'Source is required.';
if ($datetime_from === '')    $errors[] = 'From Date & Time is required.';
if ($datetime_to === '')      $errors[] = 'To Date & Time is required.';
if ($mobile === '')           $errors[] = 'Mobile Number is required.';
if ($email === '')            $errors[] = 'Email Address is required.';
if ($forwarded_to === '')     $errors[] = 'Forward To is required.';

if ($booking_for === 'Someone' && $other_person_name === '') {
    $errors[] = "Other Person's Name is required when booking for someone else.";
}

if ($source_type === 'Other' && $custom_source === '') {
    $errors[] = 'Source Place is required when source is "Other".';
}

if (!empty($mobile) && !preg_match('/^[6-9]\d{9}$/', $mobile)) {
    $errors[] = 'Please enter a valid 10-digit mobile number.';
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
}

if (!empty($forwarded_to) && !ctype_digit((string) $forwarded_to)) {
    $errors[] = 'Invalid "Forward To" selection.';
}

// Basic sanity check on date ordering, if both are valid dates
if (!empty($datetime_from) && !empty($datetime_to)) {
    $tsFrom = strtotime($datetime_from);
    $tsTo   = strtotime($datetime_to);
    if ($tsFrom === false || $tsTo === false) {
        $errors[] = 'Invalid date/time format.';
    } elseif ($tsTo <= $tsFrom) {
        $errors[] = '"To Date & Time" must be after "From Date & Time".';
    }
}

$tour_approved = (isset($_POST['tour_approved']) && $_POST['tour_approved'] == "1") ? 1 : 0;

if ($tour_approved === 1 && (!isset($_FILES['tour_order_pdf']) || $_FILES['tour_order_pdf']['error'] === UPLOAD_ERR_NO_FILE)) {
    $errors[] = 'Tour Order PDF is required when the tour has been marked as approved.';
}

if (!empty($errors)) {
    http_response_code(422);
    exit('Submission rejected:<br>' . implode('<br>', array_map('htmlspecialchars', $errors)));
}

// ================= EMAIL VERIFICATION CHECK =================
// Check if email is verified in session
if (!isset($_SESSION['email_verified']) || $_SESSION['email_verified'] !== true) {
    exit("Email not verified. Please verify your email with OTP before submitting.");
}

// Check if email matches the verified email
if (!isset($_SESSION['verified_email']) || $_SESSION['verified_email'] !== $email) {
    exit("Email mismatch. Please verify the correct email address.");
}

// Verification must be recent — a session that was OTP-verified a long time
// ago (e.g. an old browser tab, or a stale/replayed submission) should not
// be able to authorize a brand-new, unrelated submission indefinitely.
$VERIFICATION_TTL_SECONDS = 900; // 15 minutes
if (!isset($_SESSION['verified_at']) || (time() - $_SESSION['verified_at']) > $VERIFICATION_TTL_SECONDS) {
    unset($_SESSION['email_verified'], $_SESSION['verified_email'], $_SESSION['verified_at']);
    exit("Your email verification has expired. Please verify your email again before submitting.");
}

// ================= RESOLVE SOURCE =================
if ($source_type === "ICMR") {
    $source = "ICMR-NIHR Jodhpur";
} else {
    $source = $custom_source;
}

$tour_order_pdf      = NULL;
$uploaded_file_path  = NULL;

// ================= FILE UPLOAD =================
if ($tour_approved === 1 && isset($_FILES['tour_order_pdf']) && $_FILES['tour_order_pdf']['error'] === 0) {

    $uploadDir = "./uploads/tour_orders/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileTmp  = $_FILES['tour_order_pdf']['tmp_name'];
    $origName = basename($_FILES['tour_order_pdf']['name']);
    $fileExt  = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    // Allow only PDF by extension AND real MIME type (extension alone can be spoofed)
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $fileTmp);
    finfo_close($finfo);

    if ($fileExt === "pdf" && $mimeType === 'application/pdf') {

        // Limit file size (2MB)
        if ($_FILES['tour_order_pdf']['size'] <= 2 * 1024 * 1024) {

            $safeName   = time() . "_" . preg_replace('/[^A-Za-z0-9._-]/', '_', $origName);
            $targetFile = $uploadDir . $safeName;

            if (move_uploaded_file($fileTmp, $targetFile)) {
                $tour_order_pdf     = $safeName;
                $uploaded_file_path = $targetFile;
            } else {
                exit("Failed to save the uploaded file. Please try again.");
            }
        } else {
            exit("File too large. Max 2MB allowed.");
        }

    } else {
        exit("Only PDF files are allowed.");
    }
}

// ================= INSERT DATA (PREPARED STATEMENT) =================
$email_verified = 1; // Set the verification status

$stmt = $conn->prepare("INSERT INTO vehicle_applications
(booking_for, other_person_name, staff_type, applicant_name, vehicle_required, source, datetime_from, datetime_to, place_of_visit, purpose,
cotraveler1, cotraveler2, cotraveler3, cotraveler4, cotraveler5, cotraveler6,
mobile, email, email_verified, forwarded_to, tour_approved, tour_order_pdf)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

if (!$stmt) {
    error_log("submit_application.php prepare() failed: " . $conn->error);
    exit("A server error occurred. Please try again later.");
}

// The bind_param string has 22 characters matching the 22 placeholders
$stmt->bind_param(
    "sssssssssssssssssssiss", // 19 's' + 1 'i' + 2 's' = 22 characters
    $booking_for,           // s
    $other_person_name,     // s
    $staff_type,            // s
    $applicant_name,        // s
    $vehicle_required,      // s
    $source,                // s
    $datetime_from,         // s
    $datetime_to,           // s
    $place_of_visit,        // s
    $purpose,                // s
    $cotraveler1,            // s
    $cotraveler2,            // s
    $cotraveler3,            // s
    $cotraveler4,            // s
    $cotraveler5,            // s
    $cotraveler6,            // s
    $mobile,                 // s
    $email,                  // s
    $email_verified,         // i (integer - 0 or 1)
    $forwarded_to,           // s (could be integer but using string is fine)
    $tour_approved,          // i (integer - 0 or 1)
    $tour_order_pdf          // s (could be NULL)
);

$email_status       = "";
$application_saved  = false;

if ($stmt->execute()) {
    $application_saved = true;
    $application_id    = $stmt->insert_id;

    // Clear email verification session state now that it has been consumed
    unset($_SESSION['email_verified']);
    unset($_SESSION['verified_email']);
    unset($_SESSION['verified_at']);

    // ================= GET FORWARDED USER DETAILS =================
    $forwarded_query = "SELECT name, email, designation FROM users WHERE id = ?";
    $forwarded_stmt  = $conn->prepare($forwarded_query);
    $forwarded_stmt->bind_param("i", $forwarded_to);
    $forwarded_stmt->execute();
    $forwarded_result = $forwarded_stmt->get_result();
    $forwarded_data   = $forwarded_result->fetch_assoc();

    if ($forwarded_data) {
        $forwarded_email       = $forwarded_data['email'];
        $forwarded_name        = $forwarded_data['name'];
        $forwarded_designation = $forwarded_data['designation'];

        // ================= SEND EMAIL =================
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_ENCRYPTION;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';

            // Recipients
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($forwarded_email, $forwarded_name);
            $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

            // Content
            $mail->isHTML(true);
            $mail->Subject = "Vehicle Application #{$application_id} - Action Required: {$applicant_name}";

            // Build email body
            $co_travelers_list = array_filter([$cotraveler1, $cotraveler2, $cotraveler3, $cotraveler4, $cotraveler5, $cotraveler6]);
            $formatted_from = date("d/m/Y H:i", strtotime($datetime_from));
            $formatted_to   = date("d/m/Y H:i", strtotime($datetime_to));

            ob_start();
            include 'email_template.php';
            $mail->Body = ob_get_clean();

            // Plain text alternative
            $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $mail->Body));

            // Send email
            if ($mail->send()) {
                $email_status = "Email notification sent successfully to {$forwarded_name}.";
            } else {
                $email_status = "Email could not be sent. Please check your email settings.";
            }

        } catch (Exception $e) {
            $email_status = "Email could not be sent due to a server error.";
            error_log("Email sending failed: " . $mail->ErrorInfo);
        }
    } else {
        $email_status = "Warning: Forwarded user not found in database.";
    }

    $forwarded_stmt->close();

} else {
    $error_message = $stmt->error;
    error_log("submit_application.php insert failed: " . $stmt->error);
}

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Status</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
        }
        .status-card {
            max-width: 600px;
            margin: 50px auto;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        .status-header {
            padding: 30px;
            text-align: center;
        }
        .status-header.success { background: linear-gradient(135deg, #10b981, #059669); }
        .status-header.warning { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .status-header.error { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .status-body {
            background: white;
            padding: 30px;
        }
        .icon-circle {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
            color: white;
        }
        .email-details {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #4361ee;
        }
        .btn-home {
            background: linear-gradient(135deg, #4361ee, #3f37c9);
            color: white;
            padding: 12px 30px;
            border-radius: 10px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.3);
            color: white;
        }
        .debug-info {
            background: #1e293b;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 12px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="status-card">
            <?php if ($application_saved): ?>
                <div class="status-header success">
                    <div class="icon-circle">
                        <i class="bi bi-check-lg"></i>
                    </div>
                    <h2 class="text-white mb-2">Application Submitted!</h2>
                    <p class="text-white mb-0">Application ID: #<?= (int) $application_id ?></p>
                </div>
                <div class="status-body">
                    <div class="text-center mb-4">
                        <i class="bi bi-envelope-check text-success" style="font-size: 48px;"></i>
                        <h5 class="mt-3">Email Status</h5>
                    </div>

                    <div class="email-details">
                        <?php if (strpos($email_status, 'successfully') !== false): ?>
                            <div class="alert alert-success mb-0">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <?= htmlspecialchars($email_status) ?>
                            </div>
                        <?php elseif (strpos($email_status, 'Warning') !== false || strpos($email_status, 'could not be sent') !== false): ?>
                            <div class="alert alert-warning mb-0">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?= htmlspecialchars($email_status) ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger mb-0">
                                <i class="bi bi-x-circle-fill me-2"></i>
                                <?= htmlspecialchars($email_status) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="text-center mt-4">
                        <a href="index.php" class="btn-home">
                            <i class="bi bi-house-door-fill me-2"></i>Back to Home
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="status-header error">
                    <div class="icon-circle">
                        <i class="bi bi-x-lg"></i>
                    </div>
                    <h2 class="text-white mb-2">Submission Failed</h2>
                    <p class="text-white mb-0">Please try again</p>
                </div>
                <div class="status-body">
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?= htmlspecialchars('An error occurred while saving your application. Please try again.') ?>
                    </div>
                    <div class="text-center mt-4">
                        <a href="index.php" class="btn-home">
                            <i class="bi bi-arrow-left me-2"></i>Go Back
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
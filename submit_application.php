<?php
include("../config/db.php");
include("../config/email_config.php");

// Include PHPMailer manually (if not using composer)
require_once("../vendor/PHPMailer/src/PHPMailer.php");
require_once("../vendor/phpmailer/src/SMTP.php");
require_once("../vendor/phpmailer/src/Exception.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Collect form data safely
$booking_for = $_POST['booking_for'];
$other_person_name = $_POST['other_person_name'] ?? '';
$staff_type       = $_POST['staff_type'];
$applicant_name   = $_POST['applicant_name'];
$vehicle_required = $_POST['vehicle_required'];
$datetime_from    = $_POST['datetime_from'];
$datetime_to      = $_POST['datetime_to'];
$place_of_visit   = $_POST['place_of_visit'];
$purpose          = $_POST['purpose'];
$cotraveler1      = $_POST['cotraveler1'] ?? '';
$cotraveler2      = $_POST['cotraveler2'] ?? '';
$cotraveler3      = $_POST['cotraveler3'] ?? '';
$cotraveler4      = $_POST['cotraveler4'] ?? '';
$mobile           = $_POST['mobile'];
$email            = $_POST['email'];
$forwarded_to     = $_POST['forwarded_to'];

// Boolean value (0 or 1)
$tour_approved = (isset($_POST['tour_approved']) && $_POST['tour_approved'] == "1") ? 1 : 0;

$tour_order_pdf = NULL;
$uploaded_file_path = NULL;

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
                $uploaded_file_path = $targetFile;
            }
        } else {
            die("File too large. Max 2MB allowed.");
        }

    } else {
        die("Only PDF files allowed.");
    }
}

// ================= INSERT DATA (PREPARED STATEMENT) =================
$stmt = $conn->prepare("INSERT INTO vehicle_applications
(booking_for, other_person_name, staff_type, applicant_name, vehicle_required, datetime_from, datetime_to, place_of_visit, purpose,
cotraveler1, cotraveler2, cotraveler3, cotraveler4,
mobile, email, forwarded_to, tour_approved, tour_order_pdf)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmt->bind_param(
"sssssssssssssssiss",
$booking_for,
$other_person_name,
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

$email_status = "";
$application_saved = false;

if ($stmt->execute()) {
    $application_saved = true;
    $application_id = $stmt->insert_id;
    
    // ================= GET FORWARDED USER DETAILS =================
    $forwarded_query = "SELECT name, email, designation FROM users WHERE id = ?";
    $forwarded_stmt = $conn->prepare($forwarded_query);
    $forwarded_stmt->bind_param("i", $forwarded_to);
    $forwarded_stmt->execute();
    $forwarded_result = $forwarded_stmt->get_result();
    $forwarded_data = $forwarded_result->fetch_assoc();
    
    if ($forwarded_data) {
        $forwarded_email = $forwarded_data['email'];
        $forwarded_name = $forwarded_data['name'];
        $forwarded_designation = $forwarded_data['designation'];
        
        // ================= SEND EMAIL =================
        $mail = new PHPMailer(true);
        
        try {
            // Enable verbose debug output (remove in production)
            // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            
            // Server settings
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = 'prajapatimanish.mp@gmail.com';
            $mail->Password   = 'avvf cjyv fhto kcia';
            $mail->SMTPSecure = SMTP_ENCRYPTION;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';
            
            // Recipients
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($forwarded_email, $forwarded_name);
            $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = "🚗 Vehicle Application #{$application_id} - Action Required: {$applicant_name}";
            
            // Build email body
            $co_travelers_list = array_filter([$cotraveler1, $cotraveler2, $cotraveler3, $cotraveler4]);
            $formatted_from = date("d/m/Y H:i", strtotime($datetime_from));
            $formatted_to = date("d/m/Y H:i", strtotime($datetime_to));
            
            ob_start();
            include 'email_template.php';
            $mail->Body = ob_get_clean();
            
            // Plain text alternative
            $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $mail->Body));
            
            // Send email
            if ($mail->send()) {
                $email_status = "✅ Email notification sent successfully to {$forwarded_name}.";
            } else {
                $email_status = "⚠️ Email could not be sent. Please check your email settings.";
            }
            
        } catch (Exception $e) {
            $email_status = "❌ Email Error: " . $mail->ErrorInfo;
            // Log error for debugging
            error_log("Email sending failed: " . $mail->ErrorInfo);
        }
    } else {
        $email_status = "⚠️ Warning: Forwarded user not found in database.";
    }
    
    $forwarded_stmt->close();
    
} else {
    $error_message = $stmt->error;
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
                    <p class="text-white mb-0">Application ID: #<?= $application_id ?></p>
                </div>
                <div class="status-body">
                    <div class="text-center mb-4">
                        <i class="bi bi-envelope-check text-success" style="font-size: 48px;"></i>
                        <h5 class="mt-3">Email Status</h5>
                    </div>
                    
                    <div class="email-details">
                        <?php if (strpos($email_status, '✅') !== false): ?>
                            <div class="alert alert-success mb-0">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <?= htmlspecialchars($email_status) ?>
                            </div>
                        <?php elseif (strpos($email_status, '⚠️') !== false): ?>
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
                        <?= htmlspecialchars($error_message ?? 'Unknown error occurred') ?>
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
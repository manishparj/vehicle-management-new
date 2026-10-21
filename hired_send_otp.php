<?php
session_start();
include("./config/db.php");
include("./config/email_config.php");

require_once("./vendor/PHPMailer/src/PHPMailer.php");
require_once("./vendor/phpmailer/src/SMTP.php");
require_once("./vendor/phpmailer/src/Exception.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$data       = json_decode(file_get_contents('php://input'), true);
$email      = trim($data['email'] ?? '');
$staff_type = trim($data['staff_type'] ?? '');

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

// ================= RATE LIMITING =================
if (isset($_SESSION['hired_otp_last_sent']) && (time() - $_SESSION['hired_otp_last_sent']) < 30) {
    $wait = 30 - (time() - $_SESSION['hired_otp_last_sent']);
    echo json_encode(['success' => false, 'message' => "Please wait {$wait}s before requesting another OTP."]);
    exit;
}

$_SESSION['hired_otp_request_count'] = $_SESSION['hired_otp_request_count'] ?? 0;
$_SESSION['hired_otp_request_window_start'] = $_SESSION['hired_otp_request_window_start'] ?? time();

if (time() - $_SESSION['hired_otp_request_window_start'] > 3600) {
    $_SESSION['hired_otp_request_window_start'] = time();
    $_SESSION['hired_otp_request_count'] = 0;
}

if ($_SESSION['hired_otp_request_count'] >= 10) {
    echo json_encode(['success' => false, 'message' => 'Too many OTP requests. Please try again later.']);
    exit;
}

// ================= GENERATE OTP =================
$otp = sprintf("%04d", random_int(0, 9999));

$_SESSION['hired_email_otp']        = $otp;
$_SESSION['hired_otp_email']        = $email;
$_SESSION['hired_otp_time']         = time();
$_SESSION['hired_otp_attempts']     = 0;
$_SESSION['hired_otp_last_sent']    = time();
$_SESSION['hired_otp_request_count']++;

unset($_SESSION['hired_email_verified']);
unset($_SESSION['hired_verified_email']);
unset($_SESSION['hired_verified_at']);

// ================= SEND EMAIL =================
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_ENCRYPTION;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = "Email Verification OTP - Hired Vehicle Application";

    $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');

    $mail->Body = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #0E7C86 0%, #0A5F67 100%); color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f8f9fa; }
            .otp-code { font-size: 32px; font-weight: bold; color: #0E7C86; text-align: center; padding: 20px; letter-spacing: 5px; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Email Verification — Hired Vehicle</h2>
            </div>
            <div class='content'>
                <p>Dear Applicant,</p>
                <p>Your OTP for email verification is:</p>
                <div class='otp-code'><strong>{$safeOtp}</strong></div>
                <p>This OTP is valid for 10 minutes.</p>
                <p>If you didn't request this, please ignore this email.</p>
            </div>
            <div class='footer'>
                <p>This is an auto-generated email. Please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    $mail->AltBody = "Your OTP for email verification is: {$otp}\n\nThis OTP is valid for 10 minutes.";

    $mail->send();

    echo json_encode(['success' => true, 'message' => 'OTP sent successfully']);

} catch (Exception $e) {
    error_log("hired_send_otp.php mail error: " . $mail->ErrorInfo);
    echo json_encode(['success' => false, 'message' => 'Failed to send OTP. Please try again.']);
}
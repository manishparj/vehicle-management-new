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

// Validate email domain for permanent staff
if (
    $staff_type === 'Permanent' &&
    !preg_match('/@icmr\.gov\.in$/i', $email) &&
    !preg_match('/@gmail\.com$/i', $email)
) {
    echo json_encode([
        'success' => false,
        'message' => 'Permanent staff must use @icmr.gov.in email address; if unavailable or facing issues, use a personal Gmail ID.'
    ]);
    exit;
}

// ================= RATE LIMITING =================
// Prevent rapid repeated OTP requests (min 30s between sends for same session)
if (isset($_SESSION['otp_last_sent']) && (time() - $_SESSION['otp_last_sent']) < 30) {
    $wait = 30 - (time() - $_SESSION['otp_last_sent']);
    echo json_encode(['success' => false, 'message' => "Please wait {$wait}s before requesting another OTP."]);
    exit;
}

// Cap total OTP requests per session per hour to slow down abuse/enumeration
$_SESSION['otp_request_count'] = $_SESSION['otp_request_count'] ?? 0;
$_SESSION['otp_request_window_start'] = $_SESSION['otp_request_window_start'] ?? time();

if (time() - $_SESSION['otp_request_window_start'] > 3600) {
    // reset window
    $_SESSION['otp_request_window_start'] = time();
    $_SESSION['otp_request_count'] = 0;
}

if ($_SESSION['otp_request_count'] >= 10) {
    echo json_encode(['success' => false, 'message' => 'Too many OTP requests. Please try again later.']);
    exit;
}

// ================= GENERATE OTP (cryptographically secure) =================
$otp = sprintf("%04d", random_int(0, 9999));

// Store OTP in session with timestamp; reset attempt counter for a fresh OTP
$_SESSION['email_otp']              = $otp;
$_SESSION['otp_email']              = $email;
$_SESSION['otp_time']               = time();
$_SESSION['otp_attempts']           = 0;
$_SESSION['otp_last_sent']          = time();
$_SESSION['otp_request_count']++;

// A new OTP request invalidates any prior "verified" state for a different email flow
unset($_SESSION['email_verified']);
unset($_SESSION['verified_email']);
unset($_SESSION['verified_at']);

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
    $mail->addAddress($email);

    // Content
    $mail->isHTML(true);
    $mail->Subject = "Email Verification OTP - Vehicle Application";

    $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');

    $mail->Body = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f8f9fa; }
            .otp-code { font-size: 32px; font-weight: bold; color: #4361ee; text-align: center; padding: 20px; letter-spacing: 5px; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Email Verification</h2>
            </div>
            <div class='content'>
                <p>Dear User,</p>
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
    // Do not leak PHPMailer internals to the client
    error_log("send_otp.php mail error: " . $mail->ErrorInfo);
    echo json_encode(['success' => false, 'message' => 'Failed to send OTP. Please try again.']);
}
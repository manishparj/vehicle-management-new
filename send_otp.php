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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';
    $staff_type = $data['staff_type'] ?? '';
    
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email is required']);
        exit;
    }
    
    // Validate email for permanent staff
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
        
    // Generate 4-digit OTP
    $otp = sprintf("%04d", mt_rand(1, 9999));
    
    // Store OTP in session with timestamp
    $_SESSION['email_otp'] = $otp;
    $_SESSION['otp_email'] = $email;
    $_SESSION['otp_time'] = time();
    
    // Send email with OTP
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = 'vehiclemgmtncd@gmail.com';
        $mail->Password   = 'segn gjke yxoy rabz';
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = "🔐 Email Verification OTP - Vehicle Application";
        
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
                    <div class='otp-code'><strong>{$otp}</strong></div>
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
        echo json_encode(['success' => false, 'message' => 'Failed to send OTP: ' . $mail->ErrorInfo]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
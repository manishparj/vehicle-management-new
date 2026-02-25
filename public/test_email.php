<?php
require_once("config/email_config.php");
require_once("includes/phpmailer/src/PHPMailer.php");
require_once("includes/phpmailer/src/SMTP.php");
require_once("includes/phpmailer/src/Exception.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->SMTPDebug = 2; // Enable verbose debug output
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_ENCRYPTION;
    $mail->Port       = SMTP_PORT;
    
    // Recipients
    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    $mail->addAddress('test@example.com', 'Test User'); // Add a test email
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Test Email from Vehicle Management System';
    $mail->Body    = 'This is a test email to verify SMTP configuration.';
    
    $mail->send();
    echo 'Email sent successfully!';
    
} catch (Exception $e) {
    echo "Email could not be sent. Error: {$mail->ErrorInfo}";
}
?>
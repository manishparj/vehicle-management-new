<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';
    $otp = $data['otp'] ?? '';
    
    if (empty($email) || empty($otp)) {
        echo json_encode(['success' => false, 'message' => 'Email and OTP are required']);
        exit;
    }
    
    // Check if OTP exists in session
    if (!isset($_SESSION['email_otp']) || !isset($_SESSION['otp_email']) || !isset($_SESSION['otp_time'])) {
        echo json_encode(['success' => false, 'message' => 'No OTP request found. Please request OTP first.']);
        exit;
    }
    
    // Check if email matches
    if ($_SESSION['otp_email'] !== $email) {
        echo json_encode(['success' => false, 'message' => 'Email mismatch. Please request OTP again.']);
        exit;
    }
    
    // Check if OTP is expired (10 minutes = 600 seconds)
    if (time() - $_SESSION['otp_time'] > 600) {
        // Clear expired OTP
        unset($_SESSION['email_otp']);
        unset($_SESSION['otp_email']);
        unset($_SESSION['otp_time']);
        echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new OTP.']);
        exit;
    }
    
    // Verify OTP
    if ($_SESSION['email_otp'] === $otp) {
        // Mark email as verified in session
        $_SESSION['email_verified'] = true;
        $_SESSION['verified_email'] = $email;
        
        // Clear OTP from session
        unset($_SESSION['email_otp']);
        unset($_SESSION['otp_time']);
        
        echo json_encode(['success' => true, 'message' => 'Email verified successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
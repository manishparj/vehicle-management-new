<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

session_start();
header('Content-Type: application/json');

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log('hired_verify_otp.php fatal: ' . $error['message']);
        if (!headers_sent()) { header('Content-Type: application/json'); }
        echo json_encode(['success' => false, 'message' => 'Server error verifying OTP. Please try again.']);
    }
});

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    }

    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request format.']);
        exit;
    }

    $email = trim($data['email'] ?? '');
    $otp   = trim($data['otp'] ?? '');

    if (empty($email) || empty($otp)) {
        echo json_encode(['success' => false, 'message' => 'Email and OTP are required']);
        exit;
    }

    if (!isset($_SESSION['hired_email_otp']) || !isset($_SESSION['hired_otp_email']) || !isset($_SESSION['hired_otp_time'])) {
        echo json_encode(['success' => false, 'message' => 'No OTP request found. Please request OTP first.']);
        exit;
    }

    if (strcasecmp($_SESSION['hired_otp_email'], $email) !== 0) {
        echo json_encode(['success' => false, 'message' => 'Email mismatch. Please request OTP again.']);
        exit;
    }

    if (time() - $_SESSION['hired_otp_time'] > 600) {
        unset($_SESSION['hired_email_otp'], $_SESSION['hired_otp_email'], $_SESSION['hired_otp_time'], $_SESSION['hired_otp_attempts']);
        echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new OTP.']);
        exit;
    }

    $_SESSION['hired_otp_attempts'] = ($_SESSION['hired_otp_attempts'] ?? 0) + 1;

    if ($_SESSION['hired_otp_attempts'] > 5) {
        unset($_SESSION['hired_email_otp'], $_SESSION['hired_otp_email'], $_SESSION['hired_otp_time'], $_SESSION['hired_otp_attempts']);
        echo json_encode(['success' => false, 'message' => 'Too many incorrect attempts. Please request a new OTP.']);
        exit;
    }

    if (hash_equals((string) $_SESSION['hired_email_otp'], (string) $otp)) {
        $_SESSION['hired_email_verified'] = true;
        $_SESSION['hired_verified_email'] = $email;
        $_SESSION['hired_verified_at']    = time();

        unset($_SESSION['hired_email_otp'], $_SESSION['hired_otp_time'], $_SESSION['hired_otp_attempts']);

        echo json_encode(['success' => true, 'message' => 'Email verified successfully']);
    } else {
        $remaining = max(0, 5 - $_SESSION['hired_otp_attempts']);
        echo json_encode([
            'success' => false,
            'message' => "Invalid OTP. Please try again. ({$remaining} attempt(s) left)"
        ]);
    }

} catch (\Throwable $e) {
    error_log('hired_verify_otp.php exception: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error verifying OTP. Please try again.']);
}
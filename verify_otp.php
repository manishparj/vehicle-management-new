<?php
// Never let stray PHP notices/warnings leak into the JSON output —
// this is a very common cause of "valid OTP still fails to verify":
// a warning gets printed before the JSON, the response is no longer
// valid JSON, and the browser's r.json() throws, landing in the
// generic catch{} block regardless of whether the OTP was correct.
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

session_start();
header('Content-Type: application/json');

// Catch any fatal error and still emit valid JSON instead of a blank
// / half-HTML response.
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log('verify_otp.php fatal: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
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
        error_log('verify_otp.php: invalid JSON body received: ' . $raw);
        echo json_encode(['success' => false, 'message' => 'Invalid request format.']);
        exit;
    }

    $email = trim($data['email'] ?? '');
    $otp   = trim($data['otp'] ?? '');

    if (empty($email) || empty($otp)) {
        echo json_encode(['success' => false, 'message' => 'Email and OTP are required']);
        exit;
    }

    // Check if an OTP request exists in session
    if (!isset($_SESSION['email_otp']) || !isset($_SESSION['otp_email']) || !isset($_SESSION['otp_time'])) {
        echo json_encode(['success' => false, 'message' => 'No OTP request found. Please request OTP first.']);
        exit;
    }

    // Check if email matches (case-insensitive — avoids false "mismatch"
    // if autofill/typing changed the casing between send and verify)
    if (strcasecmp($_SESSION['otp_email'], $email) !== 0) {
        echo json_encode(['success' => false, 'message' => 'Email mismatch. Please request OTP again.']);
        exit;
    }

    // Check if OTP is expired (10 minutes = 600 seconds)
    if (time() - $_SESSION['otp_time'] > 600) {
        unset($_SESSION['email_otp'], $_SESSION['otp_email'], $_SESSION['otp_time'], $_SESSION['otp_attempts']);
        echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new OTP.']);
        exit;
    }

    // ================= ATTEMPT LIMITING (anti brute-force) =================
    $_SESSION['otp_attempts'] = ($_SESSION['otp_attempts'] ?? 0) + 1;

    if ($_SESSION['otp_attempts'] > 5) {
        unset($_SESSION['email_otp'], $_SESSION['otp_email'], $_SESSION['otp_time'], $_SESSION['otp_attempts']);
        echo json_encode(['success' => false, 'message' => 'Too many incorrect attempts. Please request a new OTP.']);
        exit;
    }

    // Verify OTP using constant-time comparison
    if (hash_equals((string) $_SESSION['email_otp'], (string) $otp)) {
        $_SESSION['email_verified'] = true;
        $_SESSION['verified_email'] = $email;
        $_SESSION['verified_at']    = time();

        unset($_SESSION['email_otp'], $_SESSION['otp_time'], $_SESSION['otp_attempts']);

        echo json_encode(['success' => true, 'message' => 'Email verified successfully']);
    } else {
        $remaining = max(0, 5 - $_SESSION['otp_attempts']);
        echo json_encode([
            'success' => false,
            'message' => "Invalid OTP. Please try again. ({$remaining} attempt(s) left)"
        ]);
    }

} catch (\Throwable $e) {
    error_log('verify_otp.php exception: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error verifying OTP. Please try again.']);
}
<?php
session_start();
include("./config/db.php");
include("./config/email_config.php");

require_once("./vendor/PHPMailer/src/PHPMailer.php");
require_once("./vendor/phpmailer/src/SMTP.php");
require_once("./vendor/phpmailer/src/Exception.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ================= DB CONNECTION GUARD =================
if (!isset($conn) || !($conn instanceof mysqli)) {
    error_log("hired_submit_application.php: DB connection (\$conn) not available.");
    http_response_code(500);
    exit("A server error occurred. Please try again later.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request method.');
}

// ================= CONSTANTS =================
$TRIP_TYPES = [
    'one_way'    => 'One Way Trip',
    'round_trip' => 'Round Trip',
];

$LOCATION_LABELS = [
    'mrhu_bhanpur_kallan_jaipur'     => 'MRHRU, Bhanpur Kallan, Jaipur (Rajasthan)',
    'icmr_niht_jodhpur_or_rajasthan' => 'ICMR-NIHR, Jodhpur (Rajasthan) or anywhere in Rajasthan',
    'any_city_rajasthan'             => 'Any city of Rajasthan',
    'any_city_india_excl_rajasthan'  => 'Any city of India excluding Rajasthan',
    'other'                          => 'Other',
];

// ================= COLLECT INPUT =================
$applicant_name        = trim($_POST['applicant_name'] ?? '');
$applicant_designation = trim($_POST['applicant_designation'] ?? '');
$applicant_email       = trim($_POST['applicant_email'] ?? '');
$applicant_mobile      = trim($_POST['applicant_mobile'] ?? '');
$datetime_from         = trim($_POST['datetime_from'] ?? '');
$datetime_to           = trim($_POST['datetime_to'] ?? '');
$place_required        = trim($_POST['place_required'] ?? '');
$place_of_visit        = trim($_POST['place_of_visit'] ?? '');   // enum key
$trip_type             = trim($_POST['trip_type'] ?? '');        // enum key
$purpose               = trim($_POST['purpose'] ?? '');
$details               = trim($_POST['details'] ?? '');
$project_id            = (int)($_POST['project_id'] ?? 0);

// Co-travelers
$co_travelers = [];
if (isset($_POST['co_travelers']) && is_array($_POST['co_travelers'])) {
    foreach ($_POST['co_travelers'] as $ct) {
        $ct = trim($ct);
        if ($ct !== '') $co_travelers[] = $ct;
    }
}
$co_travelers_json = json_encode($co_travelers);

// Vehicle categories (filtered: only keep those matching trip_type + location_type)
$vehicle_categories = [];
$vehicle_qty = $_POST['vehicle_qty'] ?? [];
if (isset($_POST['vehicle_categories']) && is_array($_POST['vehicle_categories'])) {
    foreach ($_POST['vehicle_categories'] as $catId) {
        $catId = (int)$catId;
        $qty   = isset($vehicle_qty[$catId]) ? max(1, (int)$vehicle_qty[$catId]) : 1;
        $vehicle_categories[] = ['id' => $catId, 'qty' => $qty];
    }
}
$vehicle_categories_json = json_encode($vehicle_categories);

// ================= VALIDATION =================
$errors = [];

if ($applicant_name === '')        $errors[] = 'Applicant Name is required.';
if ($applicant_designation === '') $errors[] = 'Designation is required.';
if ($applicant_email === '')       $errors[] = 'Email is required.';
if ($applicant_mobile === '')      $errors[] = 'Mobile Number is required.';
if ($datetime_from === '')         $errors[] = 'From Date & Time is required.';
if ($datetime_to === '')           $errors[] = 'To Date & Time is required.';
if ($place_required === '')        $errors[] = 'Place where vehicle is required is required.';
if ($place_of_visit === '')        $errors[] = 'Place of Visit is required.';
if ($trip_type === '')             $errors[] = 'Trip Type is required.';
if ($purpose === '')               $errors[] = 'Purpose of Visit is required.';
if (empty($vehicle_categories))    $errors[] = 'Please select at least one vehicle category.';

if (!preg_match('/^[6-9]\d{9}$/', $applicant_mobile)) {
    $errors[] = 'Please enter a valid 10-digit mobile number.';
}
if (!filter_var($applicant_email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
}

// Enum validation
if ($place_of_visit !== '' && !array_key_exists($place_of_visit, $LOCATION_LABELS)) {
    $errors[] = 'Invalid Place of Visit.';
}
if ($trip_type !== '' && !array_key_exists($trip_type, $TRIP_TYPES)) {
    $errors[] = 'Invalid Trip Type.';
}

// Date ordering
if (!empty($datetime_from) && !empty($datetime_to)) {
    $tsFrom = strtotime($datetime_from);
    $tsTo   = strtotime($datetime_to);
    if ($tsFrom === false || $tsTo === false) {
        $errors[] = 'Invalid date/time format.';
    } elseif ($tsTo <= $tsFrom) {
        $errors[] = '"To Date & Time" must be after "From Date & Time".';
    }
}

// Project required
if ($project_id <= 0) {
    $errors[] = 'Project is required.';
}

// Tamper protection: applicant email must belong to a hired_forwarded_admin
if ($applicant_email !== '') {
    $verifyApplicantStmt = $conn->prepare("SELECT id FROM hired_applicants WHERE email = ? AND role = 'hired_forwarded_admin'");
    if ($verifyApplicantStmt) {
        $verifyApplicantStmt->bind_param("s", $applicant_email);
        $verifyApplicantStmt->execute();
        if ($verifyApplicantStmt->get_result()->num_rows === 0) {
            $errors[] = 'Invalid applicant selection.';
        }
        $verifyApplicantStmt->close();
    }
}

// Tamper protection: project must be assigned to the applicant
if ($project_id > 0 && $applicant_email !== '') {
    $pchk = $conn->prepare("
        SELECT p.id
        FROM projects p
        INNER JOIN hired_applicant_projects hap ON hap.project_id = p.id
        INNER JOIN hired_applicants a         ON a.id = hap.applicant_id
        WHERE p.id = ? AND a.email = ? AND p.is_active = 1
    ");
    if ($pchk) {
        $pchk->bind_param("is", $project_id, $applicant_email);
        $pchk->execute();
        if ($pchk->get_result()->num_rows === 0) {
            $errors[] = 'Invalid project for this applicant.';
        }
        $pchk->close();
    }
}

// Tamper protection: every selected category must match the chosen trip_type + location_type
if (!empty($vehicle_categories) && $trip_type !== '' && $place_of_visit !== '') {
    $ids = array_map(fn($vc) => (int)$vc['id'], $vehicle_categories);
    $ids = array_values(array_filter($ids, fn($v) => $v > 0));

    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        $vchk = $conn->prepare("
            SELECT id
            FROM hired_vehicles
            WHERE id IN ($placeholders)
              AND trip_type = ?
              AND location_type = ?
              AND is_active = 1
        ");
        // Combine types: ids + trip + loc
        $bindTypes = $types . 'ss';
        $bindParams = array_merge($ids, [$trip_type, $place_of_visit]);

        $vchk->bind_param($bindTypes, ...$bindParams);
        $vchk->execute();
        $validCount = $vchk->get_result()->num_rows;
        $vchk->close();

        if ($validCount !== count($ids)) {
            $errors[] = 'One or more selected vehicle categories do not match the chosen Trip Type / Place of Visit.';
        }
    }
}

if (!empty($errors)) {
    http_response_code(422);
    exit('Submission rejected:<br>' . implode('<br>', array_map('htmlspecialchars', $errors)));
}

// ================= EMAIL VERIFICATION CHECK =================
if (!isset($_SESSION['hired_email_verified']) || $_SESSION['hired_email_verified'] !== true) {
    exit("Email not verified. Please verify your email with OTP before submitting.");
}
if (!isset($_SESSION['hired_verified_email']) || strcasecmp($_SESSION['hired_verified_email'], $applicant_email) !== 0) {
    exit("Email mismatch. Please verify the correct email address.");
}

$VERIFICATION_TTL_SECONDS = 900;
if (!isset($_SESSION['hired_verified_at']) || (time() - $_SESSION['hired_verified_at']) > $VERIFICATION_TTL_SECONDS) {
    unset($_SESSION['hired_email_verified'], $_SESSION['hired_verified_email'], $_SESSION['hired_verified_at']);
    exit("Your email verification has expired. Please verify your email again before submitting.");
}

// ================= FILE UPLOAD =================
$tour_order_pdf = NULL;

if (isset($_FILES['tour_order_pdf']) && $_FILES['tour_order_pdf']['error'] === 0) {
    $uploadDir = "./uploads/hired_tour_order/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $fileTmp  = $_FILES['tour_order_pdf']['tmp_name'];
    $origName = basename($_FILES['tour_order_pdf']['name']);
    $fileExt  = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $fileTmp);
    finfo_close($finfo);

    if ($fileExt !== "pdf" || $mimeType !== 'application/pdf') {
        exit("Only PDF files are allowed.");
    }
    if ($_FILES['tour_order_pdf']['size'] > 2 * 1024 * 1024) {
        exit("File too large. Max 2MB allowed.");
    }

    // Deduplicate by SHA-256 (last 24h)
    $fileHash = hash_file('sha256', $fileTmp);
    $existing = null;
    foreach (glob($uploadDir . "*") as $candidate) {
        if (!is_file($candidate)) continue;
        if (filemtime($candidate) < time() - 86400) continue;
        if (hash_file('sha256', $candidate) === $fileHash) {
            $existing = basename($candidate);
            break;
        }
    }

    if ($existing !== null) {
        $tour_order_pdf = $existing;
    } else {
        $safeName   = time() . "_" . preg_replace('/[^A-Za-z0-9._-]/', '', $origName);
        $targetFile = $uploadDir . $safeName;
        if (move_uploaded_file($fileTmp, $targetFile)) {
            $tour_order_pdf = $safeName;
        } else {
            exit("Failed to save the uploaded file. Please try again.");
        }
    }
} else {
    exit("Tour Order PDF is required.");
}

// ================= INSERT =================
$stmt = $conn->prepare("INSERT INTO hired_applications
    (applicant_name, applicant_designation, applicant_email, applicant_mobile, co_travelers,
     datetime_from, datetime_to, place_required, place_of_visit, trip_type,
     details, purpose, tour_order_pdf,
     selected_categories, project_id, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");

if (!$stmt) {
    error_log("hired_submit_application.php prepare() failed: " . $conn->error);
    exit("A server error occurred. Please try again later.");
}

// 15 placeholders → 14 's' + 1 'i'
$stmt->bind_param(
    "ssssssssssssssi",
    $applicant_name,
    $applicant_designation,
    $applicant_email,
    $applicant_mobile,
    $co_travelers_json,
    $datetime_from,
    $datetime_to,
    $place_required,
    $place_of_visit,
    $trip_type,
    $details,
    $purpose,
    $tour_order_pdf,
    $vehicle_categories_json,
    $project_id
);

$email_status      = "";
$application_saved = false;
$application_id    = null;

if ($stmt->execute()) {
    $application_saved = true;
    $application_id    = $stmt->insert_id;

    // Consume verification session
    unset($_SESSION['hired_email_verified']);
    unset($_SESSION['hired_verified_email']);
    unset($_SESSION['hired_verified_at']);

    // -------- Notify all super admins --------
    $adminQuery  = "SELECT email, name FROM hired_applicants WHERE role = 'hired_super_admin' AND email IS NOT NULL AND email <> ''";
    $adminResult = $conn->query($adminQuery);

    if ($adminResult && $adminResult->num_rows > 0) {

        // Resolve project name (for email)
        $projectName = '';
        if ($project_id > 0) {
            $pq = $conn->prepare("SELECT label FROM projects WHERE id = ?");
            if ($pq) {
                $pq->bind_param("i", $project_id);
                $pq->execute();
                $prow = $pq->get_result()->fetch_assoc();
                if ($prow) $projectName = $prow['label'];
                $pq->close();
            }
        }

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
            while ($admin = $adminResult->fetch_assoc()) {
                $mail->addAddress($admin['email'], $admin['name']);
            }
            $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

            $mail->isHTML(true);
            $mail->Subject = "New Hired Vehicle Application #{$application_id} — Action Required";

            $coList        = !empty($co_travelers)
                ? implode(', ', array_map('htmlspecialchars', $co_travelers))
                : 'None';
            $formattedFrom = date("d/m/Y H:i", strtotime($datetime_from));
            $formattedTo   = date("d/m/Y H:i", strtotime($datetime_to));

            $tripLabel     = $TRIP_TYPES[$trip_type] ?? ucfirst(str_replace('_', ' ', $trip_type));
            $locationLabel = $LOCATION_LABELS[$place_of_visit] ?? ucfirst(str_replace('_', ' ', $place_of_visit));

            // Build category list with trip type label per category
            $catListHtml = '';
            if (!empty($vehicle_categories)) {
                foreach ($vehicle_categories as $vc) {
                    $cq = $conn->prepare("SELECT category_name, price_per_vehicle, trip_type FROM hired_vehicles WHERE id = ?");
                    if ($cq) {
                        $cq->bind_param("i", $vc['id']);
                        $cq->execute();
                        $cr = $cq->get_result()->fetch_assoc();
                        if ($cr) {
                            $catTripLabel = $TRIP_TYPES[$cr['trip_type']] ?? '';
                            $catListHtml .= "<li>" . htmlspecialchars($cr['category_name'])
                                . " <em>(" . htmlspecialchars($catTripLabel) . ")</em>"
                                . " × " . (int)$vc['qty']
                                . " — ₹" . number_format((float)$cr['price_per_vehicle'], 2) . " each</li>";
                        }
                        $cq->close();
                    }
                }
            }
            if ($catListHtml === '') $catListHtml = '<li>No categories selected</li>';

            $projectRow = $projectName !== ''
                ? "<div class='detail-row'><span class='label'>Project:</span> " . htmlspecialchars($projectName) . "</div>"
                : '';

            $mail->Body = "
            <!DOCTYPE html><html><head><style>
                body{font-family:Arial,sans-serif}
                .container{max-width:640px;margin:0 auto;padding:20px}
                .header{background:linear-gradient(135deg,#0E7C86,#0A5F67);color:#fff;padding:22px;text-align:center;border-radius:8px 8px 0 0}
                .content{padding:22px;background:#f8f9fa;border:1px solid #e1e8ec}
                .detail-row{padding:6px 0;border-bottom:1px solid #eee}
                .label{font-weight:bold;color:#0F2A43}
                .footer{text-align:center;padding:16px;font-size:12px;color:#666}
                .btn{display:inline-block;background:#0E7C86;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;font-weight:bold}
                .badge{display:inline-block;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:bold;background:#E5F3F3;color:#0A5F67}
            </style></head><body>
                <div class='container'>
                    <div class='header'>
                        <h2>New Hired Vehicle Application</h2>
                        <p>Application #{$application_id}</p>
                    </div>
                    <div class='content'>
                        <div class='detail-row'><span class='label'>Applicant:</span> " . htmlspecialchars($applicant_name) . "</div>
                        <div class='detail-row'><span class='label'>Designation:</span> " . htmlspecialchars($applicant_designation) . "</div>
                        <div class='detail-row'><span class='label'>Email:</span> " . htmlspecialchars($applicant_email) . "</div>
                        <div class='detail-row'><span class='label'>Mobile:</span> " . htmlspecialchars($applicant_mobile) . "</div>
                        {$projectRow}
                        <div class='detail-row'><span class='label'>Trip Type:</span> <span class='badge'>" . htmlspecialchars($tripLabel) . "</span></div>
                        <div class='detail-row'><span class='label'>From:</span> {$formattedFrom}</div>
                        <div class='detail-row'><span class='label'>To:</span> {$formattedTo}</div>
                        <div class='detail-row'><span class='label'>Place Required:</span> " . htmlspecialchars($place_required) . "</div>
                        <div class='detail-row'><span class='label'>Place of Visit:</span> " . htmlspecialchars($locationLabel) . "</div>
                        <div class='detail-row'><span class='label'>Purpose:</span> " . htmlspecialchars($purpose) . "</div>
                        <div class='detail-row'><span class='label'>Co-Travelers:</span> {$coList}</div>
                        <div class='detail-row'><span class='label'>Vehicle Categories:</span><ul>{$catListHtml}</ul></div>
                        <p style='text-align:center;margin-top:20px'>
                            <a href='hired_super_admin_dashboard.php' class='btn'>Review in Dashboard</a>
                        </p>
                    </div>
                    <div class='footer'>This is an auto-generated email from ICMR-NIHR Jodhpur.</div>
                </div>
            </body></html>";

            $mail->AltBody = "New Hired Vehicle Application #{$application_id}\n"
                . "Applicant: {$applicant_name}\n"
                . "Email: {$applicant_email}\n"
                . "Mobile: {$applicant_mobile}\n"
                . "Trip Type: {$tripLabel}\n"
                . "From: {$formattedFrom}\n"
                . "To: {$formattedTo}\n"
                . "Place of Visit: {$locationLabel}";

            if ($mail->send()) {
                $email_status = "Email notification sent successfully to super admin(s).";
            } else {
                $email_status = "Email could not be sent. Please check email settings.";
            }
        } catch (Exception $e) {
            $email_status = "Email could not be sent due to a server error.";
            error_log("Hired application email failed: " . $mail->ErrorInfo);
        }
    } else {
        $email_status = "Warning: No super admin email found in database.";
    }
} else {
    error_log("hired_submit_application.php insert failed: " . $stmt->error);
}

$stmt->close();
// Note: NOT calling $conn->close() because the email block above may still use $conn.
// PHP will clean up automatically at script end.
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
        body{background:linear-gradient(135deg,#0F2A43 0%,#0A5F67 100%);min-height:100vh;font-family:'Inter',sans-serif}
        .status-card{max-width:600px;margin:50px auto;border-radius:20px;overflow:hidden;box-shadow:0 20px 40px rgba(0,0,0,0.2)}
        .status-header{padding:30px;text-align:center}
        .status-header.success{background:linear-gradient(135deg,#10b981,#059669)}
        .status-header.error{background:linear-gradient(135deg,#ef4444,#dc2626)}
        .status-body{background:#fff;padding:30px}
        .icon-circle{width:80px;height:80px;background:rgba(255,255,255,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:40px;color:#fff}
        .btn-home{background:linear-gradient(135deg,#0E7C86,#0A5F67);color:#fff;padding:12px 30px;border-radius:10px;text-decoration:none;display:inline-block;font-weight:600;transition:all 0.3s}
        .btn-home:hover{transform:translateY(-2px);box-shadow:0 10px 20px rgba(14,124,134,0.3);color:#fff}
    </style>
</head>
<body>
    <div class="container">
        <div class="status-card">
            <?php if ($application_saved): ?>
                <div class="status-header success">
                    <div class="icon-circle"><i class="bi bi-check-lg"></i></div>
                    <h2 class="text-white mb-2">Application Submitted!</h2>
                    <p class="text-white mb-0">Application ID: #<?= (int)$application_id ?></p>
                </div>
                <div class="status-body">
                    <div class="text-center mb-4">
                        <i class="bi bi-envelope-check text-success" style="font-size:48px"></i>
                        <h5 class="mt-3">Email Status</h5>
                    </div>
                    <div style="background:#f8fafc;border-radius:12px;padding:20px;border-left:4px solid #0E7C86">
                        <?php if (strpos($email_status, 'successfully') !== false): ?>
                            <div class="alert alert-success mb-0"><i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($email_status) ?></div>
                        <?php else: ?>
                            <div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($email_status) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="text-center mt-4">
                        <a href="hired_application_status.php" class="btn-home"><i class="bi bi-search me-2"></i>Track Application</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="status-header error">
                    <div class="icon-circle"><i class="bi bi-x-lg"></i></div>
                    <h2 class="text-white mb-2">Submission Failed</h2>
                    <p class="text-white mb-0">Please try again</p>
                </div>
                <div class="status-body">
                    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>An error occurred while saving your application. Please try again.</div>
                    <div class="text-center mt-4"><a href="hired_application_form.php" class="btn-home"><i class="bi bi-arrow-left me-2"></i>Go Back</a></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
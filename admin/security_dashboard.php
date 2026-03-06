<?php
session_start();
include("../config/db.php");
include("../config/email_config.php");
require_once("../vendor/phpmailer/src/PHPMailer.php");
    require_once("../vendor/phpmailer/src/SMTP.php");
    require_once("../vendor/phpmailer/src/Exception.php");
    
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

// Check authentication
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'security_admin') {
    header("location:login.php");
    exit();
}

if (isset($_POST['mark_departure'])) {
    $id = $_POST['app_id'];

    // Fetch existing record with driver and vehicle details
    $row = $conn->query("
        SELECT va.*, 
               d.driver_name, d.driver_mob,
               v.vehicle_type, v.vehicle_name, v.vehicle_no
        FROM vehicle_applications va
        LEFT JOIN drivers d ON va.driver_id = d.id
        LEFT JOIN vehicles v ON va.vehicle_id = v.id
        WHERE va.id='$id'
    ")->fetch_assoc();

    if (!$row) {
        die("Invalid application ID");
    }
    
    // Get super admin email from database
    $super_admin_query = $conn->query("SELECT email, name FROM users WHERE role = 'super_admin' LIMIT 1");
    $super_admin = $super_admin_query->fetch_assoc();
    $super_admin_email = $super_admin['email'] ?? 'icmrdigicare@gmail.com';
    $super_admin_name = $super_admin['name'] ?? 'Super Admin';

    if ($row['status'] == 'Approved') {
        // Journey starting: save OUT details
        $out_datetime = $_POST['out_datetime'];
        $km_out = $_POST['km_out'];
        $guard_out = $_POST['guard_out'];

        $conn->query("UPDATE vehicle_applications SET
            out_datetime='$out_datetime',
            km_out='$km_out',
            security_guard_name='$guard_out',
            status='Departure'
            WHERE id='$id'
        ");
        
        // Send Journey Start Email
        sendJourneyStartEmail($row, $out_datetime, $km_out, $guard_out, $super_admin_email, $super_admin_name);
        
    } elseif ($row['status'] == 'Departure') {
        // Journey ending: save IN details
        $in_datetime = $_POST['in_datetime'];
        $km_in = $_POST['km_in'];
        $guard_in = $_POST['guard_in'];
        $status = $_POST['journey_status']; // Completed / Cancelled

        // Calculate total KM
        $total_km = ($row['km_out'] && $km_in) ? $km_in - $row['km_out'] : NULL;

        // Calculate total hours in HH:MM format
        $total_hours = NULL;
        $journey_duration = '';
        if ($row['out_datetime'] && $in_datetime) {
            $start = new DateTime($row['out_datetime']);
            $end = new DateTime($in_datetime);
            $diff = $start->diff($end);
            $hours = ($diff->days * 24) + $diff->h;
            $minutes = $diff->i;
            $total_hours = sprintf("%02d:%02d", $hours, $minutes);
            $journey_duration = "$hours hours $minutes minutes";
        }

        // Append return guard
        $guard_names = $row['security_guard_name'];
        if ($guard_in) {
            $guard_names .= " (Out), $guard_in (In)";
        }

        $conn->query("UPDATE vehicle_applications SET
            in_datetime='$in_datetime',
            km_in='$km_in',
            total_km=" . ($total_km !== NULL ? "'$total_km'" : "NULL") . ",
            total_hours=" . ($total_hours !== NULL ? "'$total_hours'" : "NULL") . ",
            security_guard_name='$guard_names',
            status='$status'
            WHERE id='$id'
        ");
        
        // Send Journey End Email
        sendJourneyEndEmail($row, $in_datetime, $km_in, $guard_in, $total_km, $journey_duration, $status, $super_admin_email, $super_admin_name);
    }
}

// Function to send Journey Start Email
// Function to send Journey Start Email with ALL details
function sendJourneyStartEmail($row, $out_datetime, $km_out, $guard_out, $super_admin_email, $super_admin_name) {
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
        
        // Recipients - To Super Admin, CC to Applicant
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($super_admin_email, $super_admin_name);
        $mail->addCC($row['email'], $row['applicant_name']);
        
        // Format all dates
        $formatted_out = date("d/m/Y H:i", strtotime($out_datetime));
        $formatted_from = date("d/m/Y H:i", strtotime($row['datetime_from']));
        $formatted_to = date("d/m/Y H:i", strtotime($row['datetime_to']));
        $formatted_created = date("d/m/Y H:i", strtotime($row['created_at']));
        
        // Get co-travelers
        $co_travelers = array();
        for ($i = 1; $i <= 4; $i++) {
            if (!empty($row['cotraveler' . $i])) {
                $co_travelers[] = $row['cotraveler' . $i];
            }
        }
        
        // Subject
        $mail->Subject = "🚗 Journey Started - Vehicle #" . str_pad($row['id'], 4, '0', STR_PAD_LEFT) . " - " . $row['applicant_name'];
        
        // HTML Email Body with ALL details
        $htmlBody = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Journey Started Notification</title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                    background-color: #f1f5f9;
                    margin: 0;
                    padding: 20px;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    background: #ffffff;
                    border-radius: 20px;
                    overflow: hidden;
                    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                }
                .header {
                    background: linear-gradient(135deg, #3b82f6, #2563eb);
                    padding: 30px 20px;
                    text-align: center;
                    color: white;
                }
                .header h1 {
                    font-size: 24px;
                    margin: 10px 0 5px;
                }
                .header p {
                    font-size: 14px;
                    opacity: 0.9;
                }
                .content {
                    padding: 30px 25px;
                }
                .section-title {
                    color: #3b82f6;
                    font-size: 18px;
                    font-weight: 600;
                    margin: 25px 0 15px;
                    padding-bottom: 8px;
                    border-bottom: 2px solid #e2e8f0;
                }
                .info-card {
                    background: #f8fafc;
                    border-radius: 12px;
                    padding: 20px;
                    margin-bottom: 20px;
                    border-left: 4px solid #3b82f6;
                }
                .info-grid {
                    display: grid;
                    grid-template-columns: 1fr 2fr;
                    gap: 10px;
                }
                .info-row {
                    display: flex;
                    padding: 8px 0;
                    border-bottom: 1px solid #e2e8f0;
                }
                .info-row:last-child {
                    border-bottom: none;
                }
                .info-label {
                    width: 140px;
                    color: #64748b;
                    font-weight: 600;
                    font-size: 13px;
                }
                .info-value {
                    flex: 1;
                    color: #1e293b;
                    font-weight: 500;
                    font-size: 13px;
                }
                .badge {
                    display: inline-block;
                    padding: 4px 12px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: 600;
                }
                .badge-primary { background: #3b82f6; color: white; }
                .badge-success { background: #10b981; color: white; }
                .badge-warning { background: #f59e0b; color: white; }
                .badge-info { background: #06b6d4; color: white; }
                .badge-secondary { background: #64748b; color: white; }
                
                .alert-box {
                    background: #eff6ff;
                    border-radius: 12px;
                    padding: 20px;
                    margin-bottom: 25px;
                    border-left: 4px solid #3b82f6;
                }
                .button {
                    display: inline-block;
                    background: #3b82f6;
                    color: white;
                    padding: 12px 30px;
                    text-decoration: none;
                    border-radius: 10px;
                    font-weight: 600;
                    margin: 20px 0;
                }
                .footer {
                    text-align: center;
                    padding: 25px;
                    background: #f8fafc;
                    color: #64748b;
                    font-size: 12px;
                    border-top: 1px solid #e2e8f0;
                }
                .traveler-tag {
                    display: inline-block;
                    background: #e0f2fe;
                    color: #0369a1;
                    padding: 3px 10px;
                    border-radius: 20px;
                    font-size: 12px;
                    margin: 2px;
                }
                .status-badge {
                    display: inline-block;
                    padding: 6px 15px;
                    border-radius: 50px;
                    font-size: 14px;
                    font-weight: 600;
                    background: #3b82f6;
                    color: white;
                }
                .summary-box {
                    background: #1e293b;
                    color: white;
                    border-radius: 12px;
                    padding: 15px;
                    margin: 20px 0;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <!-- Header -->
                <div class="header">
                    <div style="font-size: 48px;">🚗</div>
                    <h1>Journey Started</h1>
                    <p>Application #' . str_pad($row['id'], 4, '0', STR_PAD_LEFT) . '</p>
                </div>
                
                <!-- Content -->
                <div class="content">
                    
                    <!-- Journey Start Alert -->
                    <div class="alert-box">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div style="background: #3b82f6; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                <span style="color: white; font-size: 24px;">▶️</span>
                            </div>
                            <div>
                                <h3 style="color: #1e3a8a; margin: 0 0 5px;">Vehicle Departed</h3>
                                <p style="color: #2563eb; margin: 0;">Journey started at ' . $formatted_out . '</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Application Summary -->
                    <div class="summary-box">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span>Application #' . str_pad($row['id'], 4, '0', STR_PAD_LEFT) . '</span>
                            <span class="badge badge-primary">' . $row['status'] . '</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 12px;">
                            <span>Created: ' . $formatted_created . '</span>
                            <span>Staff Type: ' . $row['staff_type'] . '</span>
                        </div>
                    </div>
                    
                    <!-- DEPARTURE DETAILS SECTION -->
                    <div class="section-title">📤 DEPARTURE DETAILS</div>
                    <div class="info-card">
                        <div class="info-row">
                            <span class="info-label">Out Date & Time:</span>
                            <span class="info-value"><strong>' . $formatted_out . '</strong></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">KM Reading (Out):</span>
                            <span class="info-value"><strong>' . $km_out . ' km</strong></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Security Guard (Out):</span>
                            <span class="info-value">' . htmlspecialchars($guard_out) . '</span>
                        </div>
                    </div>
                    
                    <!-- APPLICANT DETAILS SECTION -->
                    <div class="section-title">👤 APPLICANT DETAILS</div>
                    <div class="info-card">
                        <div class="info-row">
                            <span class="info-label">Full Name:</span>
                            <span class="info-value"><strong>' . htmlspecialchars($row['applicant_name']) . '</strong></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Staff Type:</span>
                            <span class="info-value">
                                <span class="badge ' . ($row['staff_type'] == 'Permanent' ? 'badge-primary' : 'badge-warning') . '">
                                    ' . $row['staff_type'] . '
                                </span>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Mobile Number:</span>
                            <span class="info-value">' . htmlspecialchars($row['mobile']) . '</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email Address:</span>
                            <span class="info-value">' . htmlspecialchars($row['email']) . '</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Vehicle Required:</span>
                            <span class="info-value">' . htmlspecialchars($row['vehicle_required']) . '</span>
                        </div>
                    </div>
                    
                    <!-- JOURNEY DETAILS SECTION -->
                    <div class="section-title">🗺️ JOURNEY DETAILS</div>
                    <div class="info-card">
                        <div class="info-row">
                            <span class="info-label">From Date & Time:</span>
                            <span class="info-value">' . $formatted_from . '</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">To Date & Time:</span>
                            <span class="info-value">' . $formatted_to . '</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Place of Visit:</span>
                            <span class="info-value">' . htmlspecialchars($row['place_of_visit']) . '</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Purpose:</span>
                            <span class="info-value">' . htmlspecialchars($row['purpose']) . '</span>
                        </div>
                    </div>';
        
        // CO-TRAVELERS SECTION (if any)
        if (!empty($co_travelers)) {
            $htmlBody .= '
                    <div class="section-title">👥 CO-TRAVELERS</div>
                    <div class="info-card">
                        <div class="info-row">
                            <span class="info-label">Travelers:</span>
                            <span class="info-value">';
            foreach ($co_travelers as $traveler) {
                $htmlBody .= '<span class="traveler-tag">' . htmlspecialchars($traveler) . '</span> ';
            }
            $htmlBody .= '
                            </span>
                        </div>
                    </div>';
        }
        
        // DRIVER DETAILS SECTION
        $htmlBody .= '
                    <div class="section-title">🚛 DRIVER DETAILS</div>
                    <div class="info-card">
                        <div class="info-row">
                            <span class="info-label">Driver Name:</span>
                            <span class="info-value"><strong>' . htmlspecialchars($row['driver_name'] ?? 'Not Assigned') . '</strong></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Driver Mobile:</span>
                            <span class="info-value">' . htmlspecialchars($row['driver_mob'] ?? 'N/A') . '</span>
                        </div>
                    </div>
                    
                    <!-- VEHICLE DETAILS SECTION -->
                    <div class="section-title">🚌 VEHICLE DETAILS</div>
                    <div class="info-card">
                        <div class="info-row">
                            <span class="info-label">Vehicle Type:</span>
                            <span class="info-value">' . htmlspecialchars($row['vehicle_type'] ?? 'N/A') . '</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Vehicle Name:</span>
                            <span class="info-value">' . htmlspecialchars($row['vehicle_name'] ?? 'N/A') . '</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Vehicle Number:</span>
                            <span class="info-value"><strong>' . htmlspecialchars($row['vehicle_no'] ?? 'N/A') . '</strong></span>
                        </div>
                    </div>
                    
                    <!-- FORWARDED DETAILS -->
                    <div class="section-title">📨 FORWARDED DETAILS</div>
                    <div class="info-card">
                        <div class="info-row">
                            <span class="info-label">Forwarded To:</span>
                            <span class="info-value">' . htmlspecialchars($row['forwarded_admin_name'] ?? 'N/A') . '</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Forwarded On:</span>
                            <span class="info-value">' . $formatted_created . '</span>
                        </div>
                    </div>
                    
                    <!-- TOUR APPROVAL SECTION -->
                    <div class="section-title">📋 TOUR APPROVAL</div>
                    <div class="info-card">
                        <div class="info-row">
                            <span class="info-label">Tour Approved:</span>
                            <span class="info-value">' . ($row['tour_approved'] == 1 ? '✅ Yes' : '❌ No') . '</span>
                        </div>';
        
        if ($row['tour_approved'] == 1 && !empty($row['tour_order_pdf'])) {
            $htmlBody .= '
                        <div class="info-row">
                            <span class="info-label">Tour Order PDF:</span>
                            <span class="info-value">📎 Attached (View in system)</span>
                        </div>';
        }
        
        $htmlBody .= '
                    </div>
                    
                    <!-- REMARKS SECTION -->
                    <div class="section-title">💬 REMARKS</div>
                    <div class="info-card">
                        <div class="info-row">
                            <span class="info-label">Remarks:</span>
                            <span class="info-value">' . htmlspecialchars($row['remark'] ?? 'No remarks') . '</span>
                        </div>
                    </div>
                    
                    <!-- JOURNEY TIMELINE -->
                    <div class="section-title">⏱️ JOURNEY TIMELINE</div>
                    <div class="info-card" style="background: #f1f5f9;">
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0;">
                            <span>🟢 Departure</span>
                            <span><strong>' . $formatted_out . '</strong></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-top: 2px dashed #94a3b8;">
                            <span>⏳ In Progress</span>
                            <span>Journey Started</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-top: 2px dashed #94a3b8;">
                            <span>🔴 Return</span>
                            <span>Pending</span>
                        </div>
                    </div>
                    
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="http://' . $_SERVER['HTTP_HOST'] . '/admin/track_journey.php?id=' . $row['id'] . '" class="button">
                            🔍 Track Journey Live
                        </a>
                    </div>
                    
                    <p style="color: #64748b; font-size: 13px; text-align: center; background: #f8fafc; padding: 15px; border-radius: 10px;">
                        <strong>Next:</strong> You will receive a notification when the journey is completed with total KM, duration, and return details.
                    </p>
                </div>
                
                <!-- Footer -->
                <div class="footer">
                    <p style="margin-bottom: 5px;">© ' . date('Y') . ' Vehicle Management System</p>
                    <p style="margin-bottom: 5px;">Journey Started Notification</p>
                    <p style="font-size: 11px;">Application #' . str_pad($row['id'], 4, '0', STR_PAD_LEFT) . ' | ' . date('d/m/Y H:i') . '</p>
                </div>
            </div>
        </body>
        </html>';
        
        // Set HTML body
        $mail->isHTML(true);
        $mail->Body = $htmlBody;
        
        // Create plain text version with ALL details
        $plainText = "========================================\n";
        $plainText .= "JOURNEY STARTED - VEHICLE #" . str_pad($row['id'], 4, '0', STR_PAD_LEFT) . "\n";
        $plainText .= "========================================\n\n";
        
        $plainText .= "📤 DEPARTURE DETAILS\n";
        $plainText .= "-------------------\n";
        $plainText .= "Out Time: " . $formatted_out . "\n";
        $plainText .= "KM Out: " . $km_out . " km\n";
        $plainText .= "Security Guard: " . $guard_out . "\n\n";
        
        $plainText .= "👤 APPLICANT DETAILS\n";
        $plainText .= "-------------------\n";
        $plainText .= "Name: " . $row['applicant_name'] . "\n";
        $plainText .= "Staff Type: " . $row['staff_type'] . "\n";
        $plainText .= "Mobile: " . $row['mobile'] . "\n";
        $plainText .= "Email: " . $row['email'] . "\n";
        $plainText .= "Vehicle Required: " . $row['vehicle_required'] . "\n\n";
        
        $plainText .= "🗺️ JOURNEY DETAILS\n";
        $plainText .= "----------------\n";
        $plainText .= "From: " . $formatted_from . "\n";
        $plainText .= "To: " . $formatted_to . "\n";
        $plainText .= "Destination: " . $row['place_of_visit'] . "\n";
        $plainText .= "Purpose: " . $row['purpose'] . "\n\n";
        
        if (!empty($co_travelers)) {
            $plainText .= "👥 CO-TRAVELERS\n";
            $plainText .= "--------------\n";
            $plainText .= "Travelers: " . implode(", ", $co_travelers) . "\n\n";
        }
        
        $plainText .= "🚛 DRIVER DETAILS\n";
        $plainText .= "----------------\n";
        $plainText .= "Driver: " . ($row['driver_name'] ?? 'Not Assigned') . "\n";
        $plainText .= "Contact: " . ($row['driver_mob'] ?? 'N/A') . "\n\n";
        
        $plainText .= "🚌 VEHICLE DETAILS\n";
        $plainText .= "-----------------\n";
        $plainText .= "Type: " . ($row['vehicle_type'] ?? 'N/A') . "\n";
        $plainText .= "Name: " . ($row['vehicle_name'] ?? 'N/A') . "\n";
        $plainText .= "Number: " . ($row['vehicle_no'] ?? 'N/A') . "\n\n";
        
        $plainText .= "📨 FORWARDED DETAILS\n";
        $plainText .= "-------------------\n";
        $plainText .= "Forwarded To: " . ($row['forwarded_admin_name'] ?? 'N/A') . "\n";
        $plainText .= "Forwarded On: " . $formatted_created . "\n\n";
        
        $plainText .= "📋 TOUR APPROVAL\n";
        $plainText .= "---------------\n";
        $plainText .= "Tour Approved: " . ($row['tour_approved'] == 1 ? 'Yes' : 'No') . "\n";
        if ($row['tour_approved'] == 1 && !empty($row['tour_order_pdf'])) {
            $plainText .= "Tour Order: Attached (View in system)\n";
        }
        $plainText .= "\n";
        
        $plainText .= "💬 REMARKS\n";
        $plainText .= "----------\n";
        $plainText .= ($row['remark'] ?? 'No remarks') . "\n\n";
        
        $plainText .= "========================================\n";
        $plainText .= "Track Journey: http://" . $_SERVER['HTTP_HOST'] . "/admin/track_journey.php?id=" . $row['id'] . "\n";
        $plainText .= "========================================\n";
        
        $mail->AltBody = $plainText;
        
        $mail->send();
        error_log("Journey Start Email sent successfully for application #" . $row['id']);
        
    } catch (Exception $e) {
        error_log("Journey Start Email failed: " . $mail->ErrorInfo);
    }
}

// Function to send Journey End Email with ALL details
function sendJourneyEndEmail($row, $in_datetime, $km_in, $guard_in, $total_km, $journey_duration, $status, $super_admin_email, $super_admin_name) {
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
        $mail->addAddress($super_admin_email, $super_admin_name);
        $mail->addCC($row['email'], $row['applicant_name']);
        
        // Format all dates
        $formatted_in = date("d/m/Y H:i", strtotime($in_datetime));
        $formatted_out = date("d/m/Y H:i", strtotime($row['out_datetime']));
        $formatted_from = date("d/m/Y H:i", strtotime($row['datetime_from']));
        $formatted_to = date("d/m/Y H:i", strtotime($row['datetime_to']));
        $formatted_created = date("d/m/Y H:i", strtotime($row['created_at']));
        
        // Get co-travelers
        $co_travelers = array();
        for ($i = 1; $i <= 4; $i++) {
            if (!empty($row['cotraveler' . $i])) {
                $co_travelers[] = $row['cotraveler' . $i];
            }
        }
        
        // Subject
        $status_emoji = ($status == 'Completed') ? '✅' : '❌';
        $mail->Subject = $status_emoji . " Journey " . ucfirst($status) . " - Vehicle #" . str_pad($row['id'], 4, '0', STR_PAD_LEFT) . " - " . $row['applicant_name'];
        
        // Colors based on status
        $headerColor = ($status == 'Completed') ? '#10b981' : '#ef4444';
        $headerGradient = ($status == 'Completed') ? '#10b981, #059669' : '#ef4444, #dc2626';
        
        // HTML Email Body with ALL details
        $htmlBody = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Journey ' . ucfirst($status) . '</title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                    background-color: #f1f5f9;
                    margin: 0;
                    padding: 20px;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    background: #ffffff;
                    border-radius: 20px;
                    overflow: hidden;
                    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                }
                .header {
                    background: linear-gradient(135deg, ' . $headerGradient . ');
                    padding: 30px 20px;
                    text-align: center;
                    color: white;
                }
                .header h1 {
                    font-size: 24px;
                    margin: 10px 0 5px;
                }
                .header p {
                    font-size: 14px;
                    opacity: 0.9;
                }
                .content {
                    padding: 30px 25px;
                }
                .section-title {
                    color: ' . $headerColor . ';
                    font-size: 18px;
                    font-weight: 600;
                    margin: 25px 0 15px;
                    padding-bottom: 8px;
                    border-bottom: 2px solid #e2e8f0;
                }
                .status-badge {
                    text-align: center;
                    margin-bottom: 25px;
                }
                .status-badge span {
                    display: inline-block;
                    padding: 10px 30px;
                    border-radius: 50px;
                    font-size: 20px;
                    font-weight: 700;
                    color: white;
                    background: ' . $headerColor . ';
                }
                .stats-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 15px;
                    margin-bottom: 25px;
                }
                .stat-box {
                    background: #f8fafc;
                    border-radius: 12px;
                    padding: 15px;
                    text-align: center;
                    border: 1px solid #e2e8f0;
                }
                .stat-value {
                    font-size: 28px;
                    font-weight: 700;
                    color: ' . $headerColor . ';
                }
                .stat-label {
                    font-size: 12px;
                    color: #64748b;
                    text-transform: uppercase;
                }
                .info-card {
                    background: #f8fafc;
                    border-radius: 12px;
                    padding: 20px;
                    margin-bottom: 20px;
                    border-left: 4px solid ' . $headerColor . ';
                }
                .info-row {
                    display: flex;
                    padding: 8px 0;
                    border-bottom: 1px solid #e2e8f0;
                }
                .info-row:last-child {
                    border-bottom: none;
                }
                .info-label {
                    width: 140px;
                    color: #64748b;
                    font-weight: 600;
                    font-size: 13px;
                }
                .info-value {
                    flex: 1;
                    color: #1e293b;
                    font-weight: 500;
                    font-size: 13px;
                }
                .badge {
                    display: inline-block;
                    padding: 4px 12px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: 600;
                }
                .badge-success { background: #10b981; color: white; }
                .badge-danger { background: #ef4444; color: white; }
                .badge-primary { background: #3b82f6; color: white; }
                .traveler-tag {
                    display: inline-block;
                    background: #e0f2fe;
                    color: #0369a1;
                    padding: 3px 10px;
                    border-radius: 20px;
                    font-size: 12px;
                    margin: 2px;
                }
                .journey-timeline {
                    background: #f1f5f9;
                    border-radius: 12px;
                    padding: 20px;
                    margin: 20px 0;
                }
                .timeline-item {
                    display: flex;
                    justify-content: space-between;
                    padding: 10px 0;
                    border-bottom: 1px dashed #94a3b8;
                }
                .timeline-item:last-child {
                    border-bottom: none;
                }
                .footer {
                    text-align: center;
                    padding: 25px;
                    background: #f8fafc;
                    color: #64748b;
                    font-size: 12px;
                    border-top: 1px solid #e2e8f0;
                }
                .summary-box {
                    background: #1e293b;
                    color: white;
                    border-radius: 12px;
                    padding: 15px;
                    margin: 20px 0;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <!-- Header -->
                <div class="header">
                    <div style="font-size: 48px;">' . ($status == 'Completed' ? '✅' : '❌') . '</div>
                    <h1>Journey ' . ucfirst($status) . '</h1>
                    <p>Application #' . str_pad($row['id'], 4, '0', STR_PAD_LEFT) . '</p>
                </div>
                
                <!-- Content -->
                <div class="content">
                    
                    <!-- Status Badge -->
                    <div class="status-badge">
                        <span>' . strtoupper($status) . '</span>
                    </div>
                    
                    <!-- Application Summary -->
                    <div class="summary-box">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span>Application #' . str_pad($row['id'], 4, '0', STR_PAD_LEFT) . '</span>
                            <span class="badge ' . ($status == 'Completed' ? 'badge-success' : 'badge-danger') . '">' . $status . '</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 12px;">
                            <span>Created: ' . $formatted_created . '</span>
                            <span>Staff: ' . $row['staff_type'] . '</span>
                        </div>
                    </div>
                    
                    <!-- JOURNEY STATISTICS -->
                    <div class="section-title">📊 JOURNEY STATISTICS</div>
                    <div class="stats-grid">
                        <div class="stat-box">
                            <div class="stat-value">' . ($total_km ? $total_km . ' km' : 'N/A') . '</div>
                            <div class="stat-label">Total Distance</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value">' . $journey_duration . '</div>
                            <div class="stat-label">Total Duration</div>
                        </div>
                    </div>
                    
                    <!-- DEPARTURE DETAILS -->
                    <div class="section-title">📤 DEPARTURE DETAILS</div>
                    <div class="info-card">
                        <div class="info-row">
                            <span class="info-label">Out Date & Time:</span>
                            <span class="info-value"><strong>' . $formatted_out . '</strong></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">KM Out:</span>
                            <span class="info-value">' . $row['km_out'] . ' km</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Security Guard (Out):</span>
                            <span class="info-value">' . htmlspecialchars($row['security_guard_name']) . '</span>
                        </div>
                    </div>
                    
                    <!-- RETURN DETAILS -->
                    <div class="section-title">📥 RETURN DETAILS</div>
                    <div class="info-card">
                        <div class="info-row">
                            <span class="info-label">In Date & Time:</span>
                            <span class="info-value"><strong>' . $formatted_in . '</strong></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">KM In:</span>
                            <span class="info-value">' . $km_in . ' km</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Security Guard (In):</span>
                            <span class="info-value">' . htmlspecialchars($guard_in) . '</span>
                        </div>
                    </div>
                    
                    <!-- APPLICANT DETAILS -->
                    <div class="section-title">👤 APPLICANT DETAILS</div>
                    <div class="info-card">
                        <div class="info-row">
                            <span class="info-label">Name:</span>
                            <span class="info-value"><strong>' . htmlspecialchars($row['applicant_name']) . '</strong></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Staff Type:</span>
                            <span class="info-value">' . $row['staff_type'] . '</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Mobile:</span>
                            <span class="info-value">' . $row['mobile'] . '</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email:</span>
                            <span class="info-value">' . $row['email'] . '</span>
                        </div>
                    </div>
                    
                    <!-- JOURNEY DETAILS -->
                    <div class="section-title">🗺️ JOURNEY DETAILS</div>
                    <div class="info-card">
                        <div class="info-row">
                            <span class="info-label">From:</span>
                            <span class="info-value">' . $formatted_from . '</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">To:</span>
                            <span class="info-value">' . $formatted_to . '</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Destination:</span>
                            <span class="info-value">' . htmlspecialchars($row['place_of_visit']) . '</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Purpose:</span>
                            <span class="info-value">' . htmlspecialchars($row['purpose']) . '</span>
                        </div>
                    </div>';
        
        // CO-TRAVELERS SECTION
        if (!empty($co_travelers)) {
            $htmlBody .= '
                    <div class="section-title">👥 CO-TRAVELERS</div>
                    <div class="info-card">
                        <div class="info-row">
                            <span class="info-label">Travelers:</span>
                            <span class="info-value">';
            foreach ($co_travelers as $traveler) {
                $htmlBody .= '<span class="traveler-tag">' . htmlspecialchars($traveler) . '</span> ';
            }
            $htmlBody .= '
                            </span>
                        </div>
                    </div>';
        }
        
        // DRIVER DETAILS
        $htmlBody .= '
                    <div class="section-title">🚛 DRIVER DETAILS</div>
                    <div class="info-card">
                        <div class="info-row">
                            <span class="info-label">Driver Name:</span>
                            <span class="info-value"><strong>' . htmlspecialchars($row['driver_name'] ?? 'Not Assigned') . '</strong></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Driver Mobile:</span>
                            <span class="info-value">' . htmlspecialchars($row['driver_mob'] ?? 'N/A') . '</span>
                        </div>
                    </div>
                    
                    <!-- VEHICLE DETAILS -->
                    <div class="section-title">🚌 VEHICLE DETAILS</div>
                    <div class="info-card">
                        <div class="info-row">
                            <span class="info-label">Vehicle Type:</span>
                            <span class="info-value">' . htmlspecialchars($row['vehicle_type'] ?? 'N/A') . '</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Vehicle Name:</span>
                            <span class="info-value">' . htmlspecialchars($row['vehicle_name'] ?? 'N/A') . '</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Vehicle Number:</span>
                            <span class="info-value"><strong>' . htmlspecialchars($row['vehicle_no'] ?? 'N/A') . '</strong></span>
                        </div>
                    </div>
                    
                    <!-- SECURITY DETAILS -->
                    <div class="section-title">🛡️ SECURITY DETAILS</div>
                    <div class="info-card">
                        <div class="info-row">
                            <span class="info-label">Out Guard:</span>
                            <span class="info-value">' . htmlspecialchars($row['security_guard_name']) . '</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">In Guard:</span>
                            <span class="info-value">' . htmlspecialchars($guard_in) . '</span>
                        </div>
                    </div>
                    
                    <!-- FORWARDED DETAILS -->
                    <div class="section-title">📨 FORWARDED DETAILS</div>
                    <div class="info-card">
                        <div class="info-row">
                            <span class="info-label">Forwarded To:</span>
                            <span class="info-value">' . htmlspecialchars($row['forwarded_admin_name'] ?? 'N/A') . '</span>
                        </div>
                    </div>
                    
                    <!-- TOUR APPROVAL -->
                    <div class="section-title">📋 TOUR APPROVAL</div>
                    <div class="info-card">
                        <div class="info-row">
                            <span class="info-label">Tour Approved:</span>
                            <span class="info-value">' . ($row['tour_approved'] == 1 ? '✅ Yes' : '❌ No') . '</span>
                        </div>';
        
        if ($row['tour_approved'] == 1 && !empty($row['tour_order_pdf'])) {
            $htmlBody .= '
                        <div class="info-row">
                            <span class="info-label">Tour Order:</span>
                            <span class="info-value">📎 PDF Attached</span>
                        </div>';
        }
        
        $htmlBody .= '
                    </div>
                    
                    <!-- REMARKS -->
                    <div class="section-title">💬 REMARKS</div>
                    <div class="info-card">
                        <div class="info-row">
                            <span class="info-label">Remarks:</span>
                            <span class="info-value">' . htmlspecialchars($row['remark'] ?? 'No remarks') . '</span>
                        </div>
                    </div>
                    
                    <!-- COMPLETE JOURNEY TIMELINE -->
                    <div class="section-title">⏱️ COMPLETE JOURNEY TIMELINE</div>
                    <div class="journey-timeline">
                        <div class="timeline-item">
                            <span>🟢 Journey Start</span>
                            <span><strong>' . $formatted_out . '</strong></span>
                        </div>
                        <div class="timeline-item">
                            <span>📍 Destination</span>
                            <span>' . htmlspecialchars($row['place_of_visit']) . '</span>
                        </div>
                        <div class="timeline-item">
                            <span>🔴 Journey End</span>
                            <span><strong>' . $formatted_in . '</strong></span>
                        </div>
                        <div class="timeline-item" style="border-bottom: none; padding-bottom: 0;">
                            <span>📊 Total Distance</span>
                            <span><strong>' . ($total_km ? $total_km . ' km' : 'N/A') . '</strong></span>
                        </div>
                    </div>
                    
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="http://' . $_SERVER['HTTP_HOST'] . '/admin/view_journey_details.php?id=' . $row['id'] . '" class="button" style="background: ' . $headerColor . '; color: white; padding: 12px 30px; text-decoration: none; border-radius: 10px; font-weight: 600; display: inline-block;">
                            📋 View Complete Journey Report
                        </a>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="footer">
                    <p style="margin-bottom: 5px;">© ' . date('Y') . ' Vehicle Management System</p>
                    <p style="margin-bottom: 5px;">Journey ' . ucfirst($status) . ' Notification</p>
                    <p style="font-size: 11px;">Application #' . str_pad($row['id'], 4, '0', STR_PAD_LEFT) . ' | ' . date('d/m/Y H:i') . '</p>
                </div>
            </div>
        </body>
        </html>';
        
        // Set HTML body
        $mail->isHTML(true);
        $mail->Body = $htmlBody;
        
        // Plain text version with ALL details
        $plainText = "========================================\n";
        $plainText .= "JOURNEY " . strtoupper($status) . " - VEHICLE #" . str_pad($row['id'], 4, '0', STR_PAD_LEFT) . "\n";
        $plainText .= "========================================\n\n";
        
        $plainText .= "📊 JOURNEY STATISTICS\n";
        $plainText .= "--------------------\n";
        $plainText .= "Status: " . strtoupper($status) . "\n";
        $plainText .= "Total Distance: " . ($total_km ? $total_km . " km" : "N/A") . "\n";
        $plainText .= "Total Duration: " . $journey_duration . "\n\n";
        
        $plainText .= "📤 DEPARTURE DETAILS\n";
        $plainText .= "-------------------\n";
        $plainText .= "Out Time: " . $formatted_out . "\n";
        $plainText .= "KM Out: " . $row['km_out'] . " km\n";
        $plainText .= "Out Guard: " . $row['security_guard_name'] . "\n\n";
        
        $plainText .= "📥 RETURN DETAILS\n";
        $plainText .= "----------------\n";
        $plainText .= "In Time: " . $formatted_in . "\n";
        $plainText .= "KM In: " . $km_in . " km\n";
        $plainText .= "In Guard: " . $guard_in . "\n\n";
        
        $plainText .= "👤 APPLICANT DETAILS\n";
        $plainText .= "-------------------\n";
        $plainText .= "Name: " . $row['applicant_name'] . "\n";
        $plainText .= "Staff Type: " . $row['staff_type'] . "\n";
        $plainText .= "Mobile: " . $row['mobile'] . "\n";
        $plainText .= "Email: " . $row['email'] . "\n\n";
        
        $plainText .= "🗺️ JOURNEY DETAILS\n";
        $plainText .= "----------------\n";
        $plainText .= "From: " . $formatted_from . "\n";
        $plainText .= "To: " . $formatted_to . "\n";
        $plainText .= "Destination: " . $row['place_of_visit'] . "\n";
        $plainText .= "Purpose: " . $row['purpose'] . "\n\n";
        
        if (!empty($co_travelers)) {
            $plainText .= "👥 CO-TRAVELERS\n";
            $plainText .= "--------------\n";
            $plainText .= "Travelers: " . implode(", ", $co_travelers) . "\n\n";
        }
        
        $plainText .= "🚛 DRIVER DETAILS\n";
        $plainText .= "----------------\n";
        $plainText .= "Driver: " . ($row['driver_name'] ?? 'Not Assigned') . "\n";
        $plainText .= "Contact: " . ($row['driver_mob'] ?? 'N/A') . "\n\n";
        
        $plainText .= "🚌 VEHICLE DETAILS\n";
        $plainText .= "-----------------\n";
        $plainText .= "Type: " . ($row['vehicle_type'] ?? 'N/A') . "\n";
        $plainText .= "Name: " . ($row['vehicle_name'] ?? 'N/A') . "\n";
        $plainText .= "Number: " . ($row['vehicle_no'] ?? 'N/A') . "\n\n";
        
        $plainText .= "🛡️ SECURITY DETAILS\n";
        $plainText .= "------------------\n";
        $plainText .= "Out Guard: " . $row['security_guard_name'] . "\n";
        $plainText .= "In Guard: " . $guard_in . "\n\n";
        
        $plainText .= "📨 FORWARDED DETAILS\n";
        $plainText .= "-------------------\n";
        $plainText .= "Forwarded To: " . ($row['forwarded_admin_name'] ?? 'N/A') . "\n\n";
        
        $plainText .= "📋 TOUR APPROVAL\n";
        $plainText .= "---------------\n";
        $plainText .= "Tour Approved: " . ($row['tour_approved'] == 1 ? 'Yes' : 'No') . "\n";
        if ($row['tour_approved'] == 1 && !empty($row['tour_order_pdf'])) {
            $plainText .= "Tour Order: PDF Attached\n";
        }
        $plainText .= "\n";
        
        $plainText .= "💬 REMARKS\n";
        $plainText .= "----------\n";
        $plainText .= ($row['remark'] ?? 'No remarks') . "\n\n";
        
        $plainText .= "⏱️ JOURNEY TIMELINE\n";
        $plainText .= "------------------\n";
        $plainText .= "Start: " . $formatted_out . "\n";
        $plainText .= "End: " . $formatted_in . "\n";
        $plainText .= "Destination: " . $row['place_of_visit'] . "\n\n";
        
        $plainText .= "========================================\n";
        $plainText .= "View Full Report: http://" . $_SERVER['HTTP_HOST'] . "/admin/view_journey_details.php?id=" . $row['id'] . "\n";
        $plainText .= "========================================\n";
        
        $mail->AltBody = $plainText;
        
        $mail->send();
        error_log("Journey End Email sent successfully for application #" . $row['id']);
        
    } catch (Exception $e) {
        error_log("Journey End Email failed: " . $mail->ErrorInfo);
    }
}

$res = $conn->query("
    SELECT 
        va.*,
        u.name AS forwarded_admin_name,
        u.designation AS forwarded_designation,
        d.driver_name AS driver_name,
        d.driver_mob AS driver_mobile,
        v.vehicle_name AS vehicle_name,
        v.vehicle_no AS vehicle_no,
        v.vehicle_type AS vehicle_type
    FROM vehicle_applications va
    LEFT JOIN users u ON va.forwarded_to = u.id
    LEFT JOIN drivers d ON va.driver_id = d.id
    LEFT JOIN vehicles v ON va.vehicle_id = v.id
    WHERE va.status IN ('Approved', 'Departure', 'Completed', 'Cancelled')
    ORDER BY va.created_at DESC
");

// Status helper functions
function getStatusBadgeClass($status) {
    $badgeClasses = [
        'Approved'    => 'bg-info',
        'Pending'     => 'bg-warning',
        'In Progress' => 'bg-primary',
        'Started'     => 'bg-success',
        'Departure'   => 'bg-primary',
        'Completed'   => 'bg-success',
        'Cancelled'   => 'bg-danger',
        'Rejected'    => 'bg-danger',
        'Expired'     => 'bg-secondary',
        'On Hold'     => 'bg-secondary',
        'Draft'       => 'bg-secondary'
    ];
    return $badgeClasses[$status] ?? 'bg-secondary';
}

function getStatusDisplayText($status) {
    $displayText = [
        'Approved'    => 'Pending Start',
        'Pending'     => 'Ongoing',
        'In Progress' => 'In Progress',
        'Started'     => 'Started',
        'Departure'   => 'In Progress',
        'Completed'   => 'Completed',
        'Cancelled'   => 'Cancelled',
        'Rejected'    => 'Rejected',
        'Expired'     => 'Expired',
        'On Hold'     => 'On Hold',
        'Draft'       => 'Draft'
    ];
    return $displayText[$status] ?? $status;
}

function getButtonIcon($status) {
    $icons = [
        'Approved'    => 'bi-play-fill me-2',
        'Pending'     => 'bi-hourglass-split me-2',
        'In Progress' => 'bi-arrow-repeat me-2',
        'Departure'   => 'bi-arrow-repeat me-2',
        'Completed'   => 'bi-check-circle-fill me-2',
        'Cancelled'   => 'bi-x-circle-fill me-2',
        'Rejected'    => 'bi-exclamation-triangle-fill me-2'
    ];
    return $icons[$status] ?? 'bi-question-circle-fill me-2';
}

function getButtonConfig($status) {
    $disabledStatuses = ['Completed', 'Cancelled', 'Rejected', 'Expired'];
    $isDisabled = in_array($status, $disabledStatuses);

    if ($isDisabled) {
        return [
            'class' => 'btn btn-secondary disabled',
            'text' => $status,
            'disabled' => true
        ];
    } else {
        return [
            'class' => $status == 'Approved' ? 'btn btn-success' : 'btn btn-warning',
            'text' => $status == 'Approved' ? 'Start Journey' : 'End Journey',
            'disabled' => false
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Security Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="security.css">
</head>

<body>
    <?php include('../header.php'); ?>

    <div class="dashboard-container">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="container-fluid px-3">
                <div class="row align-items-center">
                    <div class="col-8 col-sm-9">
                        <div class="dashboard-title">
                            <i class="bi bi-speedometer2"></i>
                            <span>Vehicle Management</span>
                        </div>
                    </div>
                    <div class="col-4 col-sm-3 text-end">
                        <a href="logout.php" class="btn-logout">
                            <i class="bi bi-box-arrow-right"></i>
                            <span class="d-none d-sm-inline">Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="container-fluid px-3 py-3">
            <h3 class="page-title">
                <i class="bi bi-shield-check"></i> Vehicle Applications
            </h3>

            <div class="applications-grid">
                <?php if ($res->num_rows > 0): ?>
                <?php while ($row = $res->fetch_assoc()): 
                        $btnConfig = getButtonConfig($row['status']);
                    ?>
                <div class="application-card">
                    <!-- Card Header -->
                    <div class="card-header">
                        <div class="app-id">#<?= htmlspecialchars($row['id']) ?></div>
                        <span class="status-badge <?= getStatusBadgeClass($row['status']) ?>">
                            <?= getStatusDisplayText($row['status']) ?>
                        </span>
                    </div>

                    <div class="card-body">
                        <!-- Applicant Section -->
                        <div class="info-section">
                            <div class="section-title">
                                <i class="bi bi-person-badge"></i>
                                <span>Applicant</span>
                            </div>
                            <div class="info-grid">
                                <!-- Booking For -->
                                <div class="info-item">
                                    <span class="info-label">Booking For:</span>
                                    <span class="info-value">
                                        <?= htmlspecialchars($row['booking_for']) ?>
                                    </span>
                                </div>

                                <?php if($row['booking_for'] == "Someone" && !empty($row['other_person_name'])){ ?>

                                <!-- Other Person -->
                                <div class="info-item">
                                    <span class="info-label">Person Name:</span>
                                    <span class="info-value">
                                        <?= htmlspecialchars($row['other_person_name']) ?>
                                    </span>
                                </div>

                                <!-- Applicant -->
                                <div class="info-item">
                                    <span class="info-label">Applicant Name:</span>
                                    <span class="info-value">
                                        <?= htmlspecialchars($row['applicant_name']) ?>
                                    </span>
                                </div>

                                <?php } else { ?>

                                <!-- Applicant -->
                                <div class="info-item">
                                    <span class="info-label">Name:</span>
                                    <span class="info-value">
                                        <?= htmlspecialchars($row['applicant_name']) ?>
                                    </span>
                                </div>

                                <?php } ?>
                                <div class="info-item">
                                    <span class="info-label">Email:</span>
                                    <span class="info-value text-truncate"><?= htmlspecialchars($row['email']) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Mobile:</span>
                                    <span class="info-value"><?= htmlspecialchars($row['mobile']) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Staff Type:</span>
                                    <span class="info-value"><?= htmlspecialchars($row['staff_type']) ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Journey Section -->
                        <div class="info-section">
                            <div class="section-title">
                                <i class="bi bi-map"></i>
                                <span>Journey</span>
                            </div>
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">From:</span>
                                    <span
                                        class="info-value"><?= date("d M Y H:i", strtotime($row['datetime_from'])) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">To:</span>
                                    <span
                                        class="info-value"><?= date("d M Y H:i", strtotime($row['datetime_to'])) ?></span>
                                </div>
                                <div class="info-item full-width">
                                    <span class="info-label">Place:</span>
                                    <span class="info-value"><?= htmlspecialchars($row['place_of_visit']) ?></span>
                                </div>
                                <div class="info-item full-width">
                                    <span class="info-label">Purpose:</span>
                                    <span class="info-value"><?= htmlspecialchars($row['purpose']) ?></span>
                                </div>
                                <?php if (!empty($row['remark'])): ?>
                                <div class="info-item full-width">
                                    <span class="info-label">Remark:</span>
                                    <span class="info-value"><?= htmlspecialchars($row['remark']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Driver & Vehicle Section -->
                        <div class="info-section">
                            <div class="section-title">
                                <i class="bi bi-truck"></i>
                                <span>Vehicle & Driver</span>
                            </div>
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">Driver:</span>
                                    <span class="info-value"><?= htmlspecialchars($row['driver_name']) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Contact:</span>
                                    <span class="info-value"><?= htmlspecialchars($row['driver_mobile']) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Vehicle:</span>
                                    <span class="info-value"><?= htmlspecialchars($row['vehicle_name']) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Type:</span>
                                    <span class="info-value"><?= htmlspecialchars($row['vehicle_type']) ?></span>
                                </div>
                                <div class="info-item full-width">
                                    <span class="info-label">Reg No:</span>
                                    <span
                                        class="info-value vehicle-no"><?= htmlspecialchars($row['vehicle_no']) ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Co-Travelers Section -->
                        <?php if ($row['cotraveler1'] || $row['cotraveler2'] || $row['cotraveler3'] || $row['cotraveler4']): ?>
                        <div class="info-section">
                            <div class="section-title">
                                <i class="bi bi-people"></i>
                                <span>Co-Travelers</span>
                            </div>
                            <div class="travelers-list">
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                <?php if (!empty($row['cotraveler' . $i])): ?>
                                <div class="traveler-item">
                                    <i class="bi bi-person"></i>
                                    <?= htmlspecialchars($row['cotraveler' . $i]) ?>
                                </div>
                                <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Approval Section -->
                        <div class="info-section">
                            <div class="section-title">
                                <i class="bi bi-check-circle"></i>
                                <span>Approval</span>
                            </div>
                            <div class="info-grid">
                                <div class="info-item full-width">
                                    <span class="info-label">Forwarded To:</span>
                                    <span
                                        class="info-value"><?= htmlspecialchars($row['forwarded_admin_name']) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Tour Order:</span>
                                    <span class="info-value">
                                        <?= $row['tour_approved'] ? '✓ Attached' : '✗ Not Attached' ?>
                                    </span>
                                </div>
                                <?php if ($row['tour_order_pdf']): ?>
                                <div class="info-item full-width">
                                    <a href="../uploads/tour_orders/<?= htmlspecialchars($row['tour_order_pdf']) ?>" target="_blank"
                                        class="btn-view-pdf">
                                        <i class="bi bi-file-pdf"></i> View Tour Order
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Security Movement Section -->
                        <div class="info-section">
                            <div class="section-title">
                                <i class="bi bi-shield-lock"></i>
                                <span>Security Details</span>
                            </div>
                            <div class="security-grid">
                                <div class="security-item">
                                    <span class="security-label">Out:</span>
                                    <span class="security-value">
                                        <?= $row['out_datetime'] ? date('d M H:i', strtotime($row['out_datetime'])) : '--' ?>
                                    </span>
                                </div>
                                <div class="security-item">
                                    <span class="security-label">KM Out:</span>
                                    <span class="security-value"><?= $row['km_out'] ?: '--' ?></span>
                                </div>
                                <div class="security-item">
                                    <span class="security-label">In:</span>
                                    <span class="security-value">
                                        <?= $row['in_datetime'] ? date('d M H:i', strtotime($row['in_datetime'])) : '--' ?>
                                    </span>
                                </div>
                                <div class="security-item">
                                    <span class="security-label">KM In:</span>
                                    <span class="security-value"><?= $row['km_in'] ?: '--' ?></span>
                                </div>
                                <div class="security-item">
                                    <span class="security-label">Total KM:</span>
                                    <span class="security-value"><?= $row['total_km'] ?: '--' ?></span>
                                </div>
                                <div class="security-item">
                                    <span class="security-label">Hours:</span>
                                    <span class="security-value"><?= $row['total_hours'] ?: '--' ?></span>
                                </div>
                                <div class="security-item full-width">
                                    <span class="security-label">Guard:</span>
                                    <span
                                        class="security-value"><?= htmlspecialchars($row['security_guard_name'] ?: '--') ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Created At -->
                        <div class="created-at">
                            <i class="bi bi-clock"></i>
                            <?= date('d M Y H:i', strtotime($row['created_at'])) ?>
                        </div>

                        <!-- Action Button -->
                        <button class="action-btn <?= $btnConfig['class'] ?>"
                            data-id="<?= htmlspecialchars($row['id']) ?>"
                            data-status="<?= htmlspecialchars($row['status']) ?>"
                            <?= !$btnConfig['disabled'] ? 'data-bs-toggle="modal" data-bs-target="#modal' . $row['id'] . '"' : '' ?>
                            <?= $btnConfig['disabled'] ? 'disabled' : '' ?>>
                            <i class="bi <?= getButtonIcon($row['status']) ?>"></i>
                            <span><?= htmlspecialchars($btnConfig['text']) ?></span>
                        </button>
                    </div>
                </div>

                <!-- Modal -->
                <div class="modal fade" id="modal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form method="POST">
                                <div class="modal-header">
                                    <h5 class="modal-title">
                                        <i
                                            class="bi bi-<?= $row['status'] == 'Approved' ? 'play' : 'flag' ?>-circle"></i>
                                        <?= $row['status'] == 'Approved' ? 'Start Journey' : 'Complete Journey' ?>
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>

                                <div class="modal-body">
                                    <input type="hidden" name="app_id" value="<?= htmlspecialchars($row['id']) ?>">

                                    <?php if ($row['status'] == 'Approved'): ?>
                                    <!-- Out Journey Fields -->
                                    <div class="form-group">
                                        <label>Out Date & Time</label>
                                        <input type="datetime-local" name="out_datetime" class="form-control" required>
                                    </div>

                                    <div class="form-group">
                                        <label>KM Out</label>
                                        <input type="number" name="km_out" class="form-control" required>
                                    </div>

                                    <div class="form-group">
                                        <label>Security Guard Name</label>
                                        <input type="text" name="guard_out" class="form-control" required>
                                    </div>

                                    <input type="hidden" name="journey_status" value="Departure">

                                    <?php elseif ($row['status'] == 'Departure'): ?>
                                    <!-- Return Journey Fields -->
                                    <div class="form-group">
                                        <label>In Date & Time</label>
                                        <input type="datetime-local" name="in_datetime" class="form-control" required>
                                    </div>

                                    <div class="form-group">
                                        <label>KM In</label>
                                        <input type="number" name="km_in" class="form-control" required>
                                    </div>

                                    <div class="form-group">
                                        <label>Security Guard (Return)</label>
                                        <input type="text" name="guard_in" class="form-control" required>
                                    </div>

                                    <div class="form-group">
                                        <label>Journey Status</label>
                                        <select name="journey_status" class="form-control" required>
                                            <option value="Completed">Completed - Successful</option>
                                            <option value="Cancelled">Cancelled - Not Completed</option>
                                        </select>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="modal-footer">
                                    <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">
                                        <i class="bi bi-x-circle"></i> Cancel
                                    </button>
                                    <button type="submit" name="mark_departure" class="btn-modal-submit">
                                        <i class="bi bi-check-circle"></i>
                                        <?= $row['status'] == 'Approved' ? 'Start Journey' : 'Complete' ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
                <?php else: ?>
                <div class="no-applications">
                    <i class="bi bi-inbox"></i>
                    <p>No applications found</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
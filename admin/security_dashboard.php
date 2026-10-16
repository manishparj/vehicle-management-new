<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');
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
    $super_admin_email = $super_admin['email'] ?? 'bishnoi.bs@icmr.gov.in';
    $super_admin_name = $super_admin['name'] ?? 'Super Admin';

    if ($row['status'] == 'Approved') {
        // START JOURNEY - Update with departure details
        $out_datetime = $conn->real_escape_string($_POST['out_datetime']);
        $km_out = $conn->real_escape_string($_POST['km_out']);
        $guard_out = $conn->real_escape_string($_POST['guard_out']);
        
        $update_query = "UPDATE vehicle_applications SET
            out_datetime='$out_datetime',
            km_out='$km_out',
            security_guard_name='$guard_out',
            status='Departure'
            WHERE id='$id'";
        
        if ($conn->query($update_query)) {
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            die("SQL Error in Start Journey: " . $conn->error);
        }
        sendJourneyStartEmail($row, $out_datetime, $km_out, $guard_out, $super_admin_email, $super_admin_name);
        
    } elseif($row['status'] == 'Departure') {
        // Journey ending: save IN details
      // COMPLETE JOURNEY - Update with return details
        $in_datetime = $conn->real_escape_string($_POST['in_datetime']);
        $km_in = $conn->real_escape_string($_POST['km_in']);
        $guard_in = $conn->real_escape_string($_POST['guard_in']);
        $status = $conn->real_escape_string($_POST['journey_status']); // 'Completed'
    
        
        // Validate required fields
        if (empty($in_datetime) || empty($km_in) || empty($guard_in)) {
            die("Error: All return journey fields are required");
        }
        
        // Calculate total KM
        $total_km = null;
        if (!empty($row['km_out']) && !empty($km_in)) {
            $total_km = $km_in - $row['km_out'];
            $total_km = max(0, $total_km); // Ensure non-negative
        }
        
        // Calculate total hours
        $total_hours = null;
        $journey_duration = '';

         if (!empty($row['out_datetime']) && !empty($in_datetime)) {
            try {
                $start = new DateTime($row['out_datetime']);
                $end = new DateTime($in_datetime);
                $diff = $start->diff($end);
                
                $hours = $diff->h;
                $minutes = $diff->i;
                $total_hours = sprintf("%02d:%02d", $hours, $minutes);
                $journey_duration = "$hours hours $minutes minutes";
            } catch (Exception $e) {
                // Handle date parsing error
                error_log("Date parsing error: " . $e->getMessage());
            }
        }
    
        // Append return guard - FIXED: Using $guard_in variable
         // Format guard names
        $guard_names = $row['security_guard_name'] ?: '';
        if (!empty($guard_in)) {
            $guard_names = $guard_names . " (Out), " . $guard_in . " (In)";
        } else {
            $guard_names = $guard_names . " (Out)";
        }
    
        // Escape all values to prevent SQL injection
        $guard_names_esc = $conn->real_escape_string($guard_names);
          
         // Build update query
        $sql = "UPDATE vehicle_applications SET
                in_datetime='$in_datetime',
                km_in='$km_in',
                total_km='$total_km',
                total_hours='$total_hours',
                security_guard_name='$guard_names_esc',
                status='$status'
                WHERE id='$id'";
        
        if ($conn->query($sql)) {
            // Journey completed successfully
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            die("SQL Error in Complete Journey: " . $conn->error);
        }
        // Send Journey End Email (commented out as in your code)
        sendJourneyEndEmail($row, $in_datetime, $km_in, $guard_in, $total_km, $journey_duration, $status, $super_admin_email, $super_admin_name);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
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
        for ($i = 1; $i <= 6; $i++) {
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
        for ($i = 1; $i <= 6; $i++) {
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
    WHERE va.status IN ('Approved', 'Departure', 'Completed')
    ORDER BY va.created_at DESC
");

if (!$res) {
    die("Query Failed: " . $conn->error);
}

// Fetch all rows once so we can build filter option lists (e.g. vehicle numbers)
// and still loop through the data again to render the cards.
$applications = $res->fetch_all(MYSQLI_ASSOC);

// Build a unique, sorted list of vehicle numbers for the "Vehicle No" filter dropdown
$vehicle_list = [];
foreach ($applications as $app) {
    if (!empty($app['vehicle_no']) && !in_array($app['vehicle_no'], $vehicle_list, true)) {
        $vehicle_list[] = $app['vehicle_no'];
    }
}
sort($vehicle_list);

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

// English display text (unchanged logic)
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

// Hindi translation for the same status labels
function getStatusDisplayTextHi($status) {
    $displayTextHi = [
        'Approved'    => 'प्रारंभ लंबित',
        'Pending'     => 'जारी',
        'In Progress' => 'प्रगति में',
        'Started'     => 'शुरू',
        'Departure'   => 'प्रगति में',
        'Completed'   => 'पूर्ण',
        'Cancelled'   => 'रद्द',
        'Rejected'    => 'अस्वीकृत',
        'Expired'     => 'समाप्त',
        'On Hold'     => 'होल्ड पर',
        'Draft'       => 'ड्राफ्ट'
    ];
    return $displayTextHi[$status] ?? $status;
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
            'text_hi' => getStatusDisplayTextHi($status),
            'disabled' => true
        ];
    } else {
        return [
            'class' => $status == 'Approved' ? 'btn btn-success' : 'btn btn-warning',
            'text' => $status == 'Approved' ? 'Start Journey' : 'End Journey',
            'text_hi' => $status == 'Approved' ? 'यात्रा शुरू करें' : 'यात्रा समाप्त करें',
            'disabled' => false
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Security Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="security.css">
    <style>
        html {
            -webkit-text-size-adjust: 100%;
        }
        * {
            box-sizing: border-box;
        }
        img, svg {
            max-width: 100%;
        }
        .filters-bar {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 18px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .filter-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 4px;
        }
        .filter-label .hi {
            font-weight: 500;
            color: #94a3b8;
        }
        .filters-bar .form-control,
        .filters-bar .form-select {
            font-size: 14px;
        }
        .results-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 12px;
            font-size: 13px;
            color: #64748b;
        }
        .reset-filters-btn {
            font-size: 13px;
            color: #2563eb;
            background: none;
            border: none;
            text-decoration: underline;
            cursor: pointer;
            padding: 0;
        }
        .no-results {
            display: none;
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }
        .no-results i {
            font-size: 40px;
            color: #cbd5e1;
            margin-bottom: 10px;
            display: block;
        }
        .pagination-bar {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            margin: 24px 0 10px;
        }
        .page-btn {
            min-width: 36px;
            height: 36px;
            padding: 0 10px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: #334155;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
        }
        .page-btn:hover:not(:disabled) {
            background: #f1f5f9;
        }
        .page-btn.active {
            background: #2563eb;
            border-color: #2563eb;
            color: #ffffff;
            font-weight: 600;
        }
        .page-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        .btn-accordion-toggle {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            cursor: pointer;
            margin-bottom: 10px;
            text-align: left;
        }
        .btn-accordion-toggle:hover {
            background: #e2e8f0;
        }
        .btn-accordion-toggle .hi {
            font-weight: 500;
            color: #64748b;
            font-size: 12px;
            margin-left: 4px;
        }
        .btn-accordion-toggle .toggle-icon {
            transition: transform 0.2s ease;
            flex-shrink: 0;
            margin-left: 8px;
        }
        .btn-accordion-toggle[aria-expanded="true"] .toggle-icon {
            transform: rotate(180deg);
        }
        .accordion-body-content {
            padding: 2px 0 4px;
        }
        .label-hi {
            display: block;
            font-size: 11px;
            font-weight: 400;
            color: #94a3b8;
            line-height: 1.3;
        }
        .section-title .hi,
        .info-section .section-title .hi {
            font-weight: 500;
            font-size: 12px;
            color: #94a3b8;
        }
        .status-hi {
            display: block;
            font-size: 10px;
            font-weight: 500;
            opacity: 0.9;
        }
        .btn-hi {
            display: block;
            font-size: 11px;
            font-weight: 500;
            opacity: 0.9;
            line-height: 1.2;
        }

        /* ===================== MOBILE RESPONSIVE ===================== */
        @media (max-width: 991.98px) {
            .dashboard-container { padding: 0; }
            .applications-grid {
                grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)) !important;
            }
        }

        @media (max-width: 767.98px) {
            .container-fluid {
                padding-left: 12px !important;
                padding-right: 12px !important;
            }
            .dashboard-title {
                font-size: 16px !important;
                gap: 6px;
            }
            .dashboard-title span {
                white-space: normal;
            }
            .page-title {
                font-size: 17px !important;
                margin-bottom: 12px;
            }
            .filters-bar {
                padding: 12px !important;
            }
            .filters-bar .row.g-3 > div {
                margin-bottom: 2px;
            }
            .results-meta {
                font-size: 12px;
            }
            .applications-grid {
                grid-template-columns: 1fr !important;
                gap: 14px !important;
            }
            .application-card {
                border-radius: 14px !important;
            }
            .card-header {
                flex-wrap: wrap;
                gap: 6px;
            }
            .info-grid,
            .security-grid {
                grid-template-columns: 1fr !important;
            }
            .info-item.full-width,
            .security-item.full-width {
                grid-column: auto !important;
            }
            .action-btn {
                width: 100%;
                justify-content: center;
            }
            .btn-accordion-toggle {
                font-size: 13px;
                padding: 10px 12px;
                flex-wrap: wrap;
            }
            .modal-dialog {
                margin: 10px !important;
            }
            .modal-body {
                padding: 14px !important;
            }
            .modal-footer {
                flex-wrap: wrap;
                gap: 8px;
            }
            .modal-footer button {
                flex: 1 1 auto;
                justify-content: center;
            }
            .pagination-bar {
                gap: 4px;
            }
            .page-btn {
                min-width: 32px;
                height: 32px;
                font-size: 12px;
                padding: 0 8px;
            }
        }

        @media (max-width: 480px) {
            .dashboard-title {
                font-size: 14px !important;
            }
            .btn-logout span {
                display: none;
            }
            .page-title {
                font-size: 15px !important;
            }
            .filter-label {
                font-size: 11px;
            }
            .filters-bar .form-control,
            .filters-bar .form-select {
                font-size: 13px;
                padding: 8px 10px;
            }
            .app-id {
                font-size: 14px !important;
            }
            .status-badge {
                font-size: 11px !important;
                padding: 3px 8px !important;
            }
            .section-title {
                font-size: 13px !important;
            }
            .info-label, .security-label {
                font-size: 12px !important;
            }
            .info-value, .security-value {
                font-size: 12px !important;
            }
            .created-at {
                font-size: 11px !important;
            }
            .label-hi, .section-title .hi {
                font-size: 10px;
            }
        }

        @media (max-width: 380px) {
            .filters-bar .row.g-3 {
                row-gap: 10px !important;
            }
        }
    </style>
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
                            <span>Vehicle Management <span class="hi">/ वाहन प्रबंधन</span></span>
                        </div>
                    </div>
                    <div class="col-4 col-sm-3 text-end">
                        <a href="logout.php" class="btn-logout">
                            <i class="bi bi-box-arrow-right"></i>
                            <span class="d-none d-sm-inline">Logout / लॉग आउट</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="container-fluid px-3 py-3">
            <h3 class="page-title">
                <i class="bi bi-shield-check"></i> Vehicle Applications <span class="hi" style="font-size:14px; font-weight:500; color:#94a3b8;">/ वाहन आवेदन</span>
            </h3>

            <!-- Filters / Search / Sort -->
            <div class="filters-bar">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="filter-label" for="searchInput">
                            Search <span class="hi">/ खोजें</span>
                        </label>
                        <input type="text" id="searchInput" class="form-control"
                            placeholder="Name, mobile, vehicle no, place... / नाम, मोबाइल, वाहन नंबर, स्थान...">
                    </div>

                    <div class="col-6 col-sm-6 col-md-2">
                        <label class="filter-label" for="dateFilter">
                            Date <span class="hi">/ दिनांक</span>
                        </label>
                        <select id="dateFilter" class="form-select">
                            <option value="today">Today / आज</option>
                            <option value="yesterday">Yesterday / कल</option>
                            <option value="week">This Week / इस सप्ताह</option>
                            <option value="month">This Month / इस महीने</option>
                            <option value="all" selected>All Time / सभी समय</option>
                        </select>
                    </div>

                    <div class="col-6 col-sm-6 col-md-2">
                        <label class="filter-label" for="statusFilter">
                            Status <span class="hi">/ स्थिति</span>
                        </label>
                        <select id="statusFilter" class="form-select">
                            <option value="all">All / सभी</option>
                            <option value="Approved">Approved / स्वीकृत</option>
                            <option value="Departure">Departure / प्रस्थान (यात्रा जारी)</option>
                            <option value="Completed">Completed / पूर्ण</option>
                        </select>
                    </div>

                    <div class="col-6 col-sm-6 col-md-2">
                        <label class="filter-label" for="vehicleFilter">
                            Vehicle No <span class="hi">/ वाहन नंबर</span>
                        </label>
                        <select id="vehicleFilter" class="form-select">
                            <option value="all">All / सभी</option>
                            <?php foreach ($vehicle_list as $vno): ?>
                            <option value="<?= htmlspecialchars($vno) ?>"><?= htmlspecialchars($vno) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-6 col-sm-6 col-md-2">
                        <label class="filter-label" for="sortSelect">
                            Sort By <span class="hi">/ क्रमबद्ध करें</span>
                        </label>
                        <select id="sortSelect" class="form-select">
                            <option value="date_desc">Newest First / नवीनतम पहले</option>
                            <option value="date_asc">Oldest First / पुराने पहले</option>
                            <option value="status">Status / स्थिति अनुसार</option>
                            <option value="vehicle">Vehicle No / वाहन नंबर अनुसार</option>
                            <option value="name">Applicant Name / नाम अनुसार</option>
                        </select>
                    </div>

                    <div class="col-6 col-sm-6 col-md-2">
                        <label class="filter-label" for="perPageSelect">
                            Per Page <span class="hi">/ प्रति पृष्ठ</span>
                        </label>
                        <select id="perPageSelect" class="form-select">
                            <option value="6">6</option>
                            <option value="9" selected>9</option>
                            <option value="12">12</option>
                            <option value="24">24</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="results-meta">
                <span id="resultCount">0 applications found / आवेदन मिले</span>
                <button type="button" class="reset-filters-btn" id="resetFiltersBtn">
                    Reset Filters / फ़िल्टर रीसेट करें
                </button>
            </div>

            <div class="applications-grid" id="applicationsGrid">
                <?php if (count($applications) > 0): ?>
                <?php foreach ($applications as $row):
                        $btnConfig = getButtonConfig($row['status']);
                        $search_blob = strtolower(implode(' ', [
                            $row['applicant_name'] ?? '',
                            $row['mobile'] ?? '',
                            $row['place_of_visit'] ?? '',
                            $row['vehicle_no'] ?? '',
                            $row['vehicle_name'] ?? '',
                            $row['driver_name'] ?? '',
                            $row['id'] ?? '',
                        ]));

                        // Date used for the "Date" filter (Today/Yesterday/Week/Month):
                        // pulled from Security Details (out_datetime = departure, falling back
                        // to in_datetime = return) instead of the application submission date.
                        // Only falls back to created_at when neither security timestamp exists yet.
                        $filter_datetime_source = $row['out_datetime'] ?: ($row['in_datetime'] ?: $row['created_at']);
                    ?>
                <div class="app-item"
                    data-status="<?= htmlspecialchars($row['status']) ?>"
                    data-vehicle="<?= htmlspecialchars($row['vehicle_no'] ?? '') ?>"
                    data-name="<?= htmlspecialchars(strtolower($row['applicant_name'] ?? '')) ?>"
                    data-created="<?= (int) strtotime($filter_datetime_source) ?>"
                    data-date="<?= htmlspecialchars(date('Y-m-d', strtotime($filter_datetime_source))) ?>"
                    data-search="<?= htmlspecialchars($search_blob) ?>">

                    <div class="application-card">
                        <!-- Card Header -->
                        <div class="card-header">
                            <div class="app-id">#<?= htmlspecialchars($row['id']) ?></div>
                            <span class="status-badge <?= getStatusBadgeClass($row['status']) ?>">
                                <?= getStatusDisplayText($row['status']) ?>
                                <span class="status-hi">/ <?= getStatusDisplayTextHi($row['status']) ?></span>
                            </span>
                        </div>

                        <div class="card-body">
                            <!-- Merged Applicant + Journey + Co-Travelers Accordion -->
                            <button class="btn-accordion-toggle" type="button" data-bs-toggle="collapse"
                                data-bs-target="#details<?= $row['id'] ?>" aria-expanded="false"
                                aria-controls="details<?= $row['id'] ?>">
                                <span>
                                    <i class="bi bi-person-lines-fill"></i>
                                    Applicant, Journey & Co-Travelers
                                    <span class="hi">/ आवेदक, यात्रा और सह-यात्री विवरण</span>
                                </span>
                                <i class="bi bi-chevron-down toggle-icon"></i>
                            </button>

                            <div class="collapse" id="details<?= $row['id'] ?>">
                                <div class="accordion-body-content">

                                    <!-- Applicant Section -->
                                    <div class="info-section">
                                        <div class="section-title">
                                            <i class="bi bi-person-badge"></i>
                                            <span>Applicant <span class="hi">/ आवेदक</span></span>
                                        </div>
                                        <div class="info-grid">

                                            <?php if($row['booking_for'] == "Someone" && !empty($row['other_person_name'])){ ?>

                                            <!-- Other Person -->
                                            <div class="info-item">
                                                <span class="info-label">Person Name: <span class="label-hi">व्यक्ति का नाम</span></span>
                                                <span class="info-value">
                                                    <?= htmlspecialchars($row['other_person_name']) ?>
                                                </span>
                                            </div>

                                            <!-- Applicant -->
                                            <div class="info-item">
                                                <span class="info-label">Applicant Name: <span class="label-hi">आवेदक का नाम</span></span>
                                                <span class="info-value">
                                                    <?= htmlspecialchars($row['applicant_name']) ?>
                                                </span>
                                            </div>

                                            <?php } else { ?>

                                            <!-- Applicant -->
                                            <div class="info-item">
                                                <span class="info-label">Name: <span class="label-hi">नाम</span></span>
                                                <span class="info-value">
                                                    <?= htmlspecialchars($row['applicant_name']) ?>
                                                </span>
                                            </div>

                                            <?php } ?>
                                            <div class="info-item">
                                                <span class="info-label">Mobile: <span class="label-hi">मोबाइल</span></span>
                                                <span class="info-value"><?= htmlspecialchars($row['mobile']) ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Journey Section -->
                                    <div class="info-section">
                                        <div class="section-title">
                                            <i class="bi bi-map"></i>
                                            <span>Journey <span class="hi">/ यात्रा</span></span>
                                        </div>
                                        <div class="info-grid">
                                            <div class="info-item">
                                                <span class="info-label">From: <span class="label-hi">से</span></span>
                                                <span
                                                    class="info-value"><?= date("d M Y H:i", strtotime($row['datetime_from'])) ?></span>
                                            </div>
                                            <div class="info-item">
                                                <span class="info-label">To: <span class="label-hi">तक</span></span>
                                                <span
                                                    class="info-value"><?= date("d M Y H:i", strtotime($row['datetime_to'])) ?></span>
                                            </div>
                                            <div class="info-item full-width">
                                                <span class="info-label">Source: <span class="label-hi">स्रोत</span></span>
                                                <span class="info-value">
                                                    <?= !empty($row['source']) ? htmlspecialchars($row['source']) : 'N/A' ?>
                                                </span>
                                            </div>
                                            <div class="info-item full-width">
                                                <span class="info-label">Place/Destination: <span class="label-hi">स्थान/गंतव्य</span></span>
                                                <span class="info-value"><?= htmlspecialchars($row['place_of_visit']) ?></span>
                                            </div>
                                            <?php if (!empty($row['remark'])): ?>
                                            <div class="info-item full-width">
                                                <span class="info-label">Remark: <span class="label-hi">टिप्पणी</span></span>
                                                <span class="info-value"><?= htmlspecialchars($row['remark']) ?></span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Co-Travelers Section -->
                                    <?php if ($row['cotraveler1'] || $row['cotraveler2'] || $row['cotraveler3'] || $row['cotraveler4'] || $row['cotraveler5'] || $row['cotraveler6']): ?>
                                    <div class="info-section">
                                        <div class="section-title">
                                            <i class="bi bi-people"></i>
                                            <span>Co-Travelers <span class="hi">/ सह-यात्री</span></span>
                                        </div>
                                        <div class="travelers-list">
                                            <?php for ($i = 1; $i <= 6; $i++): ?>
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

                                </div>
                            </div>

                            <!-- Driver & Vehicle Section -->
                            <div class="info-section">
                                <div class="section-title">
                                    <i class="bi bi-truck"></i>
                                    <span>Vehicle & Driver <span class="hi">/ वाहन और चालक</span></span>
                                </div>
                                <div class="info-grid">
                                    <div class="info-item">
                                        <span class="info-label">Driver: <span class="label-hi">चालक</span></span>
                                        <span class="info-value"><?= htmlspecialchars($row['driver_name'] ?? 'Not Assigned') ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Contact: <span class="label-hi">संपर्क</span></span>
                                        <span class="info-value"><?= htmlspecialchars($row['driver_mobile'] ?? 'N/A') ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Vehicle: <span class="label-hi">वाहन</span></span>
                                        <span class="info-value"><?= htmlspecialchars($row['vehicle_name'] ?? 'Not Assigned') ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Type: <span class="label-hi">प्रकार</span></span>
                                        <span class="info-value"><?= htmlspecialchars($row['vehicle_type'] ?? 'N/A') ?></span>
                                    </div>
                                    <div class="info-item full-width">
                                        <span class="info-label">Reg No: <span class="label-hi">पंजीकरण संख्या</span></span>
                                        <span class="info-value vehicle-no"><?= htmlspecialchars($row['vehicle_no'] ?? 'N/A') ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Security Movement Section -->
                            <div class="info-section">
                                <div class="section-title">
                                    <i class="bi bi-shield-lock"></i>
                                    <span>Security Details <span class="hi">/ सुरक्षा विवरण</span></span>
                                </div>
                                <div class="security-grid">
                                    <div class="security-item">
                                        <span class="security-label">Out Date Time: <span class="label-hi">बाहर जाने का दिनांक समय</span></span>
                                        <span class="security-value">
                                            <?= $row['out_datetime'] ? date('d M H:i', strtotime($row['out_datetime'])) : '--' ?>
                                        </span>
                                    </div>
                                    <div class="security-item">
                                        <span class="security-label">KM Out: <span class="label-hi">किमी (बाहर)</span></span>
                                        <span class="security-value"><?= $row['km_out'] ?: '--' ?></span>
                                    </div>
                                    <div class="security-item">
                                        <span class="security-label">In Date Time: <span class="label-hi">वापसी दिनांक समय</span></span>
                                        <span class="security-value">
                                            <?= $row['in_datetime'] ? date('d M H:i', strtotime($row['in_datetime'])) : '--' ?>
                                        </span>
                                    </div>
                                    <div class="security-item">
                                        <span class="security-label">KM In: <span class="label-hi">किमी (अंदर)</span></span>
                                        <span class="security-value"><?= $row['km_in'] ?: '--' ?></span>
                                    </div>
                                    <div class="security-item">
                                        <span class="security-label">Total KM: <span class="label-hi">कुल किमी</span></span>
                                        <span class="security-value"><?= $row['total_km'] ?: '--' ?></span>
                                    </div>
                                    <div class="security-item">
                                        <span class="security-label">Total Hours: <span class="label-hi">कुल घंटे</span></span>
                                        <span class="security-value"><?= $row['total_hours'] ?: '--' ?></span>
                                    </div>
                                    <div class="security-item full-width">
                                        <span class="security-label">Guard Name: <span class="label-hi">गार्ड का नाम</span></span>
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
                                <span>
                                    <?= htmlspecialchars($btnConfig['text']) ?>
                                    <span class="btn-hi">/ <?= htmlspecialchars($btnConfig['text_hi']) ?></span>
                                </span>
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
                                            <?= $row['status'] == 'Approved' ? 'Start Journey / यात्रा शुरू करें' : 'Complete Journey / यात्रा पूर्ण करें' ?>
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>

                                    <div class="modal-body">
                                        <input type="hidden" name="app_id" value="<?= htmlspecialchars($row['id']) ?>">

                                        <?php if ($row['status'] == 'Approved'): ?>
                                        <!-- Out Journey Fields -->
                                        <div class="form-group">
                                            <label>Out Date & Time <span class="label-hi">/ बाहर जाने की तारीख और समय</span></label>
                                            <input type="datetime-local" name="out_datetime" class="form-control" required>
                                        </div>

                                        <div class="form-group">
                                            <label>KM Out <span class="label-hi">/ किमी आउट</span></label>
                                            <input type="number" name="km_out" class="form-control" required>
                                        </div>

                                        <div class="form-group">
                                            <label>Security Guard Name <span class="label-hi">/ सुरक्षा गार्ड का नाम</span></label>
                                            <input type="text" name="guard_out" class="form-control" required>
                                        </div>

                                        <input type="hidden" name="journey_status" value="Departure">

                                        <?php elseif ($row['status'] == 'Departure'): ?>
                                        <!-- Return Journey Fields -->
                                        <div class="form-group">
                                            <label>In Date & Time <span class="label-hi">/ अंदर आने की तारीख और समय</span></label>
                                            <input type="datetime-local" name="in_datetime" class="form-control" required>
                                        </div>

                                        <div class="form-group">
                                            <label>KM In <span class="label-hi">/ किमी इन</span></label>
                                            <input type="number" name="km_in" class="form-control" required>
                                        </div>

                                        <div class="form-group">
                                            <label>Security Guard (Return) <span class="label-hi">/ सुरक्षा गार्ड (वापसी)</span></label>
                                            <input type="text" name="guard_in" class="form-control" required>
                                        </div>

                                        <div class="form-group">
                                            <label>Journey Status <span class="label-hi">/ यात्रा स्थिति</span></label>
                                            <select name="journey_status" class="form-control" required>
                                                <option value="Completed">Completed - Successful / पूर्ण - सफल</option>
                                                <!-- <option value="Cancelled">Cancelled - Not Completed</option> -->
                                            </select>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="modal-footer">
                                        <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">
                                            <i class="bi bi-x-circle"></i> Cancel / रद्द करें
                                        </button>
                                        <button type="submit" name="mark_departure" class="btn-modal-submit">
                                            <i class="bi bi-check-circle"></i>
                                            <?= $row['status'] == 'Approved' ? 'Start Journey / यात्रा शुरू करें' : 'Complete / पूर्ण करें' ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <div class="no-applications">
                    <i class="bi bi-inbox"></i>
                    <p>No applications found / कोई आवेदन नहीं मिला</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- No results after filtering (client-side) -->
            <div class="no-results" id="noResultsMsg">
                <i class="bi bi-search"></i>
                <p>No matching applications / कोई मिलान आवेदन नहीं मिला</p>
            </div>

            <!-- Pagination -->
            <div class="pagination-bar" id="pagination"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var grid = document.getElementById('applicationsGrid');
        var items = Array.prototype.slice.call(grid.querySelectorAll('.app-item'));

        var searchInput = document.getElementById('searchInput');
        var dateFilter = document.getElementById('dateFilter');
        var statusFilter = document.getElementById('statusFilter');
        var vehicleFilter = document.getElementById('vehicleFilter');
        var sortSelect = document.getElementById('sortSelect');
        var perPageSelect = document.getElementById('perPageSelect');
        var paginationEl = document.getElementById('pagination');
        var resultCount = document.getElementById('resultCount');
        var noResultsMsg = document.getElementById('noResultsMsg');
        var resetBtn = document.getElementById('resetFiltersBtn');

        var currentPage = 1;

        if (items.length === 0) {
            paginationEl.style.display = 'none';
            return;
        }

        function pad2(n) { return n < 10 ? '0' + n : '' + n; }

        function toDateStr(d) {
            return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
        }

        function matchesDate(item, value) {
            if (value === 'all') return true;

            var now = new Date();

            if (value === 'today') {
                return item.dataset.date === toDateStr(now);
            }
            if (value === 'yesterday') {
                var y = new Date(now);
                y.setDate(y.getDate() - 1);
                return item.dataset.date === toDateStr(y);
            }
            if (value === 'week') {
                var start = new Date(now);
                var day = start.getDay(); // 0 = Sun ... 6 = Sat
                var diffToMonday = (day === 0 ? 6 : day - 1);
                start.setDate(start.getDate() - diffToMonday);
                start.setHours(0, 0, 0, 0);
                var itemTime = parseInt(item.dataset.created, 10) * 1000;
                return itemTime >= start.getTime();
            }
            if (value === 'month') {
                var monthStart = new Date(now.getFullYear(), now.getMonth(), 1).getTime();
                var itemTime2 = parseInt(item.dataset.created, 10) * 1000;
                return itemTime2 >= monthStart;
            }
            return true;
        }

        function getFiltered() {
            var q = searchInput.value.trim().toLowerCase();
            var status = statusFilter.value;
            var vehicle = vehicleFilter.value;
            var dateVal = dateFilter.value;

            return items.filter(function (item) {
                var matchesSearch = !q || item.dataset.search.indexOf(q) !== -1;
                var matchesStatus = status === 'all' || item.dataset.status === status;
                var matchesVehicle = vehicle === 'all' || item.dataset.vehicle === vehicle;
                return matchesSearch && matchesStatus && matchesVehicle && matchesDate(item, dateVal);
            });
        }

        function getSorted(list) {
            var sortBy = sortSelect.value;
            var sorted = list.slice();

            if (sortBy === 'date_desc') {
                sorted.sort(function (a, b) { return b.dataset.created - a.dataset.created; });
            } else if (sortBy === 'date_asc') {
                sorted.sort(function (a, b) { return a.dataset.created - b.dataset.created; });
            } else if (sortBy === 'status') {
                sorted.sort(function (a, b) { return a.dataset.status.localeCompare(b.dataset.status); });
            } else if (sortBy === 'vehicle') {
                sorted.sort(function (a, b) { return (a.dataset.vehicle || '').localeCompare(b.dataset.vehicle || ''); });
            } else if (sortBy === 'name') {
                sorted.sort(function (a, b) { return a.dataset.name.localeCompare(b.dataset.name); });
            }
            return sorted;
        }

        function renderPagination(totalPages) {
            paginationEl.innerHTML = '';
            if (totalPages <= 1) return;

            function createBtn(label, page, disabled, active) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = label;
                btn.className = 'page-btn' + (active ? ' active' : '');
                btn.disabled = disabled;
                btn.addEventListener('click', function () {
                    currentPage = page;
                    render();
                    window.scrollTo({ top: grid.offsetTop - 20, behavior: 'smooth' });
                });
                return btn;
            }

            paginationEl.appendChild(createBtn('«', currentPage - 1, currentPage === 1, false));
            for (var p = 1; p <= totalPages; p++) {
                paginationEl.appendChild(createBtn(String(p), p, false, p === currentPage));
            }
            paginationEl.appendChild(createBtn('»', currentPage + 1, currentPage === totalPages, false));
        }

        function render() {
            items.forEach(function (i) { i.style.display = 'none'; });

            var filtered = getSorted(getFiltered());
            var perPage = parseInt(perPageSelect.value, 10);
            var totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;

            var start = (currentPage - 1) * perPage;
            var pageItems = filtered.slice(start, start + perPage);

            pageItems.forEach(function (i) {
                grid.appendChild(i);
                i.style.display = '';
            });

            resultCount.textContent = filtered.length + ' applications found / आवेदन मिले';
            noResultsMsg.style.display = filtered.length === 0 ? 'block' : 'none';

            renderPagination(totalPages);
        }

        searchInput.addEventListener('input', function () { currentPage = 1; render(); });
        [dateFilter, statusFilter, vehicleFilter, sortSelect, perPageSelect].forEach(function (el) {
            el.addEventListener('change', function () { currentPage = 1; render(); });
        });

        resetBtn.addEventListener('click', function () {
            searchInput.value = '';
            dateFilter.value = 'today';
            statusFilter.value = 'all';
            vehicleFilter.value = 'all';
            sortSelect.value = 'date_desc';
            perPageSelect.value = '9';
            currentPage = 1;
            render();
        });

        render();
    });
    </script>
</body>

</html>
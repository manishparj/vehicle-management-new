<?php
include("../config/db.php");
include("../config/email_config.php"); // Include email configuration

// Include PHPMailer
require_once("../vendor/phpmailer/src/PHPMailer.php");
require_once("../vendor/phpmailer/src/SMTP.php");
require_once("../vendor/phpmailer/src/Exception.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if(!isset($_SESSION['user'])){
    header("location:login.php");
    exit();
}

$id = $_GET['id'];
$type = $_POST['type'];
$remark = $_POST['remark'];

// First, get the application details before updating
$app_query = $conn->query("
    SELECT va.*, 
           u.name as forwarded_admin_name, 
           u.email as forwarded_email,
           u.designation as forwarded_designation
    FROM vehicle_applications va
    LEFT JOIN users u ON va.forwarded_to = u.id
    WHERE va.id = '$id'
");
$application = $app_query->fetch_assoc();

// Update the application status
$conn->query("
    UPDATE vehicle_applications 
    SET status='$type', remark='$remark'
    WHERE id='$id'
");

// If status is 'Recommended', send email to super admin
if($type == 'Recommended' && $application) {
    
    // Super admin email (you can also fetch this from database)
    $super_admin_email = 'icmrdigicare@gmail.com'; // Change this to actual super admin email
    $super_admin_name = 'Manish Sharma';
    
    // Get current user who recommended
    $current_user = $_SESSION['user']['name'] ?? 'Admin User';
    $current_user_designation = $_SESSION['user']['designation'] ?? 'Admin';
    
    // Collect co-travelers
    $co_travelers = array_filter([
        $application['cotraveler1'] ?? null,
        $application['cotraveler2'] ?? null,
        $application['cotraveler3'] ?? null,
        $application['cotraveler4'] ?? null
    ]);
    
    // Format dates
    $formatted_from = date("d/m/Y H:i", strtotime($application['datetime_from']));
    $formatted_to = date("d/m/Y H:i", strtotime($application['datetime_to']));
    $formatted_created = date("d/m/Y H:i", strtotime($application['created_at']));
    
    // Send email using PHPMailer
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
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // // Add CC to the person who recommended (optional)
        // if(isset($_SESSION['user']['email'])) {
        //     $mail->addCC($_SESSION['user']['email'], $_SESSION['user']['name']);
        // }
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = "🚗 Vehicle Application Recommended - Action Required: " . $application['applicant_name'];
        
        // Build HTML email
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Vehicle Application Recommended</title>
            <style>
                body {
                    font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                    background-color: #f1f5f9;
                    margin: 0;
                    padding: 20px;
                }
                .email-container {
                    max-width: 650px;
                    margin: 0 auto;
                    background: white;
                    border-radius: 20px;
                    overflow: hidden;
                    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
                }
                .header {
                    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
                    padding: 30px 25px;
                    text-align: center;
                    color: white;
                }
                .header h1 {
                    font-size: 26px;
                    margin: 10px 0 5px;
                    font-weight: 700;
                }
                .header p {
                    font-size: 16px;
                    opacity: 0.95;
                    margin: 0;
                }
                .content {
                    padding: 30px 25px;
                }
                .badge {
                    display: inline-block;
                    padding: 6px 15px;
                    border-radius: 50px;
                    font-size: 14px;
                    font-weight: 600;
                }
                .badge-warning {
                    background: #f59e0b;
                    color: white;
                }
                .badge-info {
                    background: #3b82f6;
                    color: white;
                }
                .info-card {
                    background: #f8fafc;
                    border-radius: 15px;
                    padding: 20px;
                    margin: 20px 0;
                    border-left: 4px solid #f59e0b;
                }
                .info-row {
                    display: flex;
                    padding: 10px 0;
                    border-bottom: 1px solid #e2e8f0;
                }
                .info-row:last-child {
                    border-bottom: none;
                }
                .info-label {
                    width: 130px;
                    font-weight: 600;
                    color: #64748b;
                }
                .info-value {
                    flex: 1;
                    color: #1e293b;
                    font-weight: 500;
                }
                .traveler-tag {
                    display: inline-block;
                    background: #e0f2fe;
                    color: #0369a1;
                    padding: 3px 12px;
                    border-radius: 50px;
                    font-size: 13px;
                    margin: 2px 3px;
                }
                .button {
                    display: inline-block;
                    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
                    color: white;
                    padding: 14px 35px;
                    text-decoration: none;
                    border-radius: 12px;
                    font-weight: 600;
                    font-size: 16px;
                    margin: 20px 0;
                }
                .footer {
                    text-align: center;
                    padding: 25px;
                    background: #f8fafc;
                    color: #64748b;
                    font-size: 13px;
                    border-top: 1px solid #e2e8f0;
                }
                .highlight-box {
                    background: #fff7ed;
                    border-radius: 12px;
                    padding: 15px;
                    margin: 20px 0;
                }
                @media only screen and (max-width: 480px) {
                    .info-row {
                        flex-direction: column;
                    }
                    .info-label {
                        width: 100%;
                        margin-bottom: 5px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="email-container">
                <!-- Header -->
                <div class="header">
                    <div style="font-size: 48px; margin-bottom: 10px;">👍</div>
                    <h1>Vehicle Application Recommended</h1>
                    <p>Action Required - Super Admin Review</p>
                </div>
                
                <!-- Content -->
                <div class="content">
                    
                    <!-- Recommendation Alert -->
                    <div class="highlight-box">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div style="background: #f59e0b; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                <span style="color: white; font-size: 20px;">📋</span>
                            </div>
                            <div>
                                <h3 style="color: #7b341e; margin: 0 0 5px;">Application Recommended for Approval</h3>
                                <p style="color: #7b341e; margin: 0; font-size: 14px;">
                                    <strong>' . htmlspecialchars($current_user) . '</strong> (' . htmlspecialchars($current_user_designation) . ') has recommended this application.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Application ID -->
                    <div style="text-align: center; margin: 20px 0;">
                        <span class="badge badge-warning" style="font-size: 16px;">
                            Application #' . str_pad($id, 4, '0', STR_PAD_LEFT) . '
                        </span>
                    </div>
                    
                    <!-- Applicant Details -->
                    <div class="info-card">
                        <h3 style="color: #f59e0b; margin-top: 0; margin-bottom: 20px; font-size: 18px;">
                            👤 Applicant Information
                        </h3>
                        <div class="info-row">
                            <span class="info-label">Name:</span>
                            <span class="info-value"><strong>' . htmlspecialchars($application['applicant_name']) . '</strong></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Staff Type:</span>
                            <span class="info-value">
                                <span class="badge badge-info">' . htmlspecialchars($application['staff_type']) . '</span>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Mobile:</span>
                            <span class="info-value">' . htmlspecialchars($application['mobile']) . '</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email:</span>
                            <span class="info-value">' . htmlspecialchars($application['email']) . '</span>
                        </div>
                    </div>
                    
                    <!-- Journey Details -->
                    <div class="info-card">
                        <h3 style="color: #f59e0b; margin-top: 0; margin-bottom: 20px; font-size: 18px;">
                            🗺️ Journey Details
                        </h3>
                        <div class="info-row">
                            <span class="info-label">Vehicle:</span>
                            <span class="info-value">' . htmlspecialchars($application['vehicle_required']) . '</span>
                        </div>
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
                            <span class="info-value">' . htmlspecialchars($application['place_of_visit']) . '</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Purpose:</span>
                            <span class="info-value">' . htmlspecialchars($application['purpose']) . '</span>
                        </div>
                    </div>';
        
        // Co-Travelers Section (if any)
        if (!empty($co_travelers)) {
            $mail->Body .= '
                    <!-- Co-Travelers -->
                    <div class="info-card">
                        <h3 style="color: #f59e0b; margin-top: 0; margin-bottom: 20px; font-size: 18px;">
                            👥 Co-Travelers
                        </h3>
                        <div class="info-row">
                            <span class="info-label">Travelers:</span>
                            <span class="info-value">';
            foreach ($co_travelers as $traveler) {
                $mail->Body .= '<span class="traveler-tag">' . htmlspecialchars($traveler) . '</span> ';
            }
            $mail->Body .= '
                            </span>
                        </div>
                    </div>';
        }
        
        // Forwarded By Section
        $mail->Body .= '
                    <!-- Forwarded By -->
                    <div class="info-card">
                        <h3 style="color: #f59e0b; margin-top: 0; margin-bottom: 20px; font-size: 18px;">
                            📨 Forwarded By
                        </h3>
                        <div class="info-row">
                            <span class="info-label">Name:</span>
                            <span class="info-value">' . htmlspecialchars($application['forwarded_admin_name'] ?? 'N/A') . '</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Designation:</span>
                            <span class="info-value">' . htmlspecialchars($application['forwarded_designation'] ?? 'N/A') . '</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Forwarded On:</span>
                            <span class="info-value">' . $formatted_created . '</span>
                        </div>
                    </div>';
        
        // Tour Approval Section
        $mail->Body .= '
                    <!-- Tour Approval -->
                    <div class="info-card">
                        <h3 style="color: #f59e0b; margin-top: 0; margin-bottom: 20px; font-size: 18px;">
                            📋 Tour Order Attached
                        </h3>
                        <div class="info-row">
                            <span class="info-label">Status:</span>
                            <span class="info-value">' . ($application['tour_approved'] == 1 ? '✅ Attached' : '❌ Not Attached') . '</span>
                        </div>';
        
        $mail->Body .= '
                    </div>';
        
        // Remark Section (if any)
        if (!empty($remark)) {
            $mail->Body .= '
                    <div class="info-card" style="background: #fef3c7;">
                        <h3 style="color: #f59e0b; margin-top: 0; margin-bottom: 10px; font-size: 18px;">
                            💬 Recommendation Remark
                        </h3>
                        <p style="color: #7b341e; margin: 0; font-style: italic;">
                            "' . htmlspecialchars($remark) . '"
                        </p>
                        <p style="color: #7b341e; margin: 10px 0 0; font-size: 13px;">
                            - ' . htmlspecialchars($current_user) . '
                        </p>
                    </div>';
        }
        
        // Action Button
        $mail->Body .= '
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="http://' . $_SERVER['HTTP_HOST'] . '/admin/login.php" class="button">
                            🔐 Login to Review & Take Action
                        </a>
                        <p style="color: #64748b; font-size: 14px; margin-top: 15px;">
                            Click above to view full details and approve/reject this application
                        </p>
                    </div>
                    
                    <!-- Summary Box -->
                    <div style="background: #1e293b; color: white; border-radius: 12px; padding: 15px; margin: 20px 0;">
                        <h4 style="margin: 0 0 10px; color: #f59e0b;">📊 Quick Summary</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div>
                                <small style="color: #94a3b8;">Applicant</small>
                                <div style="font-weight: 600;">' . htmlspecialchars($application['applicant_name']) . '</div>
                            </div>
                            <div>
                                <small style="color: #94a3b8;">Journey Date</small>
                                <div style="font-weight: 600;">' . date('d/m/Y', strtotime($application['datetime_from'])) . '</div>
                            </div>
                            <div>
                                <small style="color: #94a3b8;">Destination</small>
                                <div style="font-weight: 600;">' . htmlspecialchars(substr($application['place_of_visit'], 0, 20)) . '...</div>
                            </div>
                            <div>
                                <small style="color: #94a3b8;">Status</small>
                                <div style="font-weight: 600; color: #f59e0b;">RECOMMENDED</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="footer">
                    <p style="margin-bottom: 5px;">© ' . date('Y') . ' Vehicle Management System</p>
                    <p style="margin-bottom: 5px;">This is an automated notification for recommended applications.</p>
                    <p style="font-size: 11px;">Application #' . str_pad($id, 4, '0', STR_PAD_LEFT) . ' | Recommended on: ' . date('d/m/Y H:i') . '</p>
                </div>
            </div>
        </body>
        </html>';
        
        // Plain text alternative
        $mail->AltBody = 'Vehicle Application Recommended' . "\n\n" .
                         'Application #' . str_pad($id, 4, '0', STR_PAD_LEFT) . ' has been recommended by ' . $current_user . "\n\n" .
                         'Applicant: ' . $application['applicant_name'] . "\n" .
                         'Staff Type: ' . $application['staff_type'] . "\n" .
                         'Mobile: ' . $application['mobile'] . "\n" .
                         'Journey: ' . $formatted_from . ' to ' . $formatted_to . "\n" .
                         'Destination: ' . $application['place_of_visit'] . "\n" .
                         'Purpose: ' . $application['purpose'] . "\n\n" .
                         'Remark: ' . $remark . "\n\n" .
                         'Please login to the system to review and take action.';
        
        // Send email
        $mail->send();
        
        // Optional: Log email sent
        error_log("Recommendation email sent to super admin for application #" . $id);
        
    } catch (Exception $e) {
        // Log error but don't stop execution
        error_log("Failed to send recommendation email: " . $mail->ErrorInfo);
    }
}

// Redirect back to dashboard
header("location:dashboard_forwarded.php");
exit();
?>
<!DOCTYPE html>
<html>
<head>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f1f5f9;
            line-height: 1.6;
        }
        .email-wrapper {
            max-width: 650px;
            margin: 30px auto;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }
        .email-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 700;
        }
        .email-header p {
            font-size: 16px;
            opacity: 0.95;
            margin: 0;
        }
        .email-body {
            padding: 40px 35px;
            background: white;
        }
        .greeting-box {
            background: #f8fafc;
            padding: 20px 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            border-left: 5px solid #667eea;
        }
        .greeting-box h3 {
            color: #1e293b;
            margin: 0 0 10px 0;
            font-size: 20px;
        }
        .greeting-box p {
            color: #475569;
            margin: 0;
            font-size: 15px;
        }
        .section-title {
            color: #4361ee;
            font-size: 18px;
            font-weight: 600;
            margin: 25px 0 15px 0;
            padding-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-title i {
            font-size: 20px;
        }
        .info-card {
            background: #f8fafc;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
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
        .badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
        }
        .badge-primary {
            background: #4361ee;
            color: white;
        }
        .badge-success {
            background: #10b981;
            color: white;
        }
        .badge-warning {
            background: #f59e0b;
            color: white;
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
        .action-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px 35px;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            margin: 25px 0;
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
        }
        .action-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.4);
        }
        .note-box {
            background: #fff7ed;
            border-radius: 12px;
            padding: 20px;
            margin: 25px 0;
            border-left: 4px solid #f97316;
        }
        .note-box p {
            color: #7b341e;
            margin: 0;
            font-size: 14px;
        }
        .footer {
            text-align: center;
            padding: 25px 35px;
            background: #f1f5f9;
            color: #64748b;
            font-size: 13px;
            border-top: 1px solid #e2e8f0;
        }
        .footer p {
            margin: 5px 0;
        }
        @media (max-width: 600px) {
            .email-body { padding: 20px; }
            .info-row { flex-direction: column; }
            .info-label { width: 100%; margin-bottom: 5px; }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-header">
            <h1>🚗 Vehicle Application Request</h1>
            <p>Action Required - Please Review and Approve</p>
        </div>
        
        <div class="email-body">
            <div class="greeting-box">
                <h3>Dear <?= htmlspecialchars($forwarded_name) ?>,</h3>
                <p>A new vehicle application has been submitted and requires your attention. Please review the details below and take appropriate action.</p>
            </div>
            
            <div class="section-title">
                <span>👤</span> Applicant Information
            </div>
            <div class="info-card">
                <div class="info-row">
                    <div class="info-label">Name:</div>
                    <div class="info-value"><strong><?= htmlspecialchars($applicant_name) ?></strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Staff Type:</div>
                    <div class="info-value">
                        <span class="badge badge-primary"><?= htmlspecialchars($staff_type) ?></span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Mobile:</div>
                    <div class="info-value"><?= htmlspecialchars($mobile) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Email:</div>
                    <div class="info-value"><?= htmlspecialchars($email) ?></div>
                </div>
            </div>
            
            <div class="section-title">
                <span>🗺️</span> Journey Details
            </div>
            <div class="info-card">
                <div class="info-row">
                    <div class="info-label">Vehicle Type:</div>
                    <div class="info-value"><?= htmlspecialchars($vehicle_required) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">From:</div>
                    <div class="info-value"><?= htmlspecialchars($formatted_from) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">To:</div>
                    <div class="info-value"><?= htmlspecialchars($formatted_to) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Destination:</div>
                    <div class="info-value"><?= htmlspecialchars($place_of_visit) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Purpose:</div>
                    <div class="info-value"><?= htmlspecialchars($purpose) ?></div>
                </div>
            </div>
            
            <?php if (!empty($co_travelers)): ?>
            <div class="section-title">
                <span>👥</span> Co-Travelers
            </div>
            <div class="info-card">
                <div class="info-row">
                    <div class="info-label">Travelers:</div>
                    <div class="info-value">
                        <?php foreach ($co_travelers as $traveler): ?>
                            <span class="traveler-tag"><?= htmlspecialchars($traveler) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="section-title">
                <span>📋</span> Tour Order Attached
            </div>
            <div class="info-card">
                <div class="info-row">
                    <div class="info-label">Status:</div>
                    <div class="info-value">
                        <?php if ($tour_approved): ?>
                            <span class="badge badge-success">✅ Attached</span>
                        <?php else: ?>
                            <span class="badge badge-warning">❌ Not Attached</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div style="text-align: center;">
                <a href="http://<?= $_SERVER['HTTP_HOST'] ?>/admin/login.php" class="action-button">
                    🔐 Login to Review & Take Action
                </a>
            </div>
            
            <div class="note-box">
                <p><strong>📝 Next Steps:</strong></p>
                <p style="margin-top: 10px;">1. Log in to the Vehicle Management System<br>
                2. Review the application details<br>
                3. Approve or reject the request<br>
                4. Assign appropriate driver and vehicle<br>
                5. Add remarks if necessary</p>
            </div>
        </div>
        
        <div class="footer">
            <p>© <?= date('Y') ?> Vehicle Management System. All rights reserved.</p>
            <p>This is an automated message. Please do not reply to this email.</p>
            <p style="font-size: 11px; margin-top: 10px;">For technical support, contact: support@vehiclemanagement.com</p>
        </div>
    </div>
</body>
</html>
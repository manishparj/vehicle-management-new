<?php
// config/email_config.php

// SMTP Server Settings (for Gmail)
define('SMTP_HOST', 'smtp.gmail.com');        // Keep as is for Gmail
define('SMTP_PORT', 587);                      // Keep as is
define('SMTP_ENCRYPTION', 'tls');              // Keep as is

// YOUR PERSONAL GMAIL CREDENTIALS (used to SEND emails)
define('SMTP_USERNAME', 'vehiclemgmtncd@gmail.com');  // 🔴 Your Gmail address that will send the emails
define('SMTP_PASSWORD', 'segn gjke yxoy rabz');        // 🔴 Your Gmail App Password (not regular password)

// SENDER INFORMATION (who the email appears to come from)
define('SMTP_FROM_EMAIL', 'noreply@vehiclemanagement.com'); // 🔵 This can be any email, appears in "From" field
define('SMTP_FROM_NAME', 'Vehicle Management System');      // 🔵 Sender name displayed in inbox
?>
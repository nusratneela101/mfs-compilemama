<?php
/**
 * Site Configuration
 * MFS Compilemama
 */

// Site Info
define('SITE_NAME', 'MFS Compilemama');
define('SITE_NAME_BN', 'এমএফএস কম্পাইলমামা');
define('SITE_URL', 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
define('SITE_VERSION', '1.0.0');

// Subscription
define('SUB_AMOUNT', 150);
define('SUB_DAYS', 30);
define('SUB_CURRENCY', 'BDT');

// OTP
define('OTP_EXPIRY_MINUTES', 5);
define('OTP_MAX_ATTEMPTS', 3);
define('OTP_RESEND_COOLDOWN', 60); // seconds

// Rate Limiting
define('RATE_LIMIT_OTP_PER_HOUR', 3);
define('RATE_LIMIT_LOGIN_PER_HOUR', 10);
define('RATE_LIMIT_API_PER_MINUTE', 30);

// Session
define('SESSION_LIFETIME', 3600 * 2); // 2 hours
define('SESSION_NAME', 'mfs_session');

// PIN
define('PIN_MIN_LENGTH', 4);
define('PIN_MAX_LENGTH', 6);

// Payment Numbers (admin configurable)
// IMPORTANT: Replace these with your actual bKash/Nagad merchant numbers before going live!
define('BKASH_NUMBER', '01XXXXXXXXX');
define('NAGAD_NUMBER', '01XXXXXXXXX');

// SMS Gateway: 'log', 'bulksmsbd', 'smsq', 'twilio'
define('SMS_GATEWAY', 'log');

// BulkSMSBD config
define('BULKSMSBD_API_KEY', '');
define('BULKSMSBD_SENDER_ID', 'MFSMama');

// SMSQ config
define('SMSQ_API_KEY', '');
define('SMSQ_SENDER_ID', 'MFSMama');

// Twilio config
define('TWILIO_SID', '');
define('TWILIO_TOKEN', '');
define('TWILIO_FROM', '');

// SMS Log file (when SMS_GATEWAY = 'log')
define('SMS_LOG_FILE', __DIR__ . '/../logs/sms.log');

// Admin path prefix
define('ADMIN_PREFIX', 'admin');

// Debug mode
define('DEBUG_MODE', false);

// Transaction simulation: failure rate percentage (0 = always success, 5 = 5% failures)
// Set to 0 for production
define('TRANSACTION_FAILURE_RATE', 0);

// Timezone
define('TIMEZONE', 'Asia/Dhaka');
date_default_timezone_set(TIMEZONE);

// Error reporting
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Create logs directory if not exists
$logsDir = __DIR__ . '/../logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0750, true);
}

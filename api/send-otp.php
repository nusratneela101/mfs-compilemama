<?php
/**
 * API: Send OTP — MFS Compilemama
 */

define('API_REQUEST', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/location.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$phone = trim($_POST['phone'] ?? '');

if (!validateBDPhone($phone)) {
    jsonResponse(false, 'সঠিক বাংলাদেশি ফোন নম্বর দিন।');
}

// Rate limit: 3 OTPs per hour per phone
if (!rateLimitCheck($phone, 'otp_send', RATE_LIMIT_OTP_PER_HOUR, 3600)) {
    jsonResponse(false, 'অনেকবার OTP পাঠানো হয়েছে। ১ ঘণ্টা পরে আবার চেষ্টা করুন।');
}

// Also rate limit by IP
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!rateLimitCheck($ip, 'otp_send_ip', RATE_LIMIT_OTP_PER_HOUR * 5, 3600)) {
    jsonResponse(false, 'Too many requests from this IP. Please try again later.');
}

$db  = getDB();
$otp = generateOTP();
$exp = date('Y-m-d H:i:s', time() + OTP_EXPIRY_MINUTES * 60);

$stmt = $db->prepare("INSERT INTO otp_codes (phone, code, expires_at) VALUES (?, ?, ?)");
$stmt->bind_param('sss', $phone, $otp, $exp);
if (!$stmt->execute()) {
    $stmt->close();
    jsonResponse(false, 'OTP পাঠানো সম্ভব হয়নি। পুনরায় চেষ্টা করুন।');
}
$stmt->close();

$sent = sendOTP($phone, $otp);

if ($sent) {
    jsonResponse(true, 'OTP সফলভাবে পাঠানো হয়েছে।', [
        'expires_in' => OTP_EXPIRY_MINUTES * 60,
    ]);
} else {
    jsonResponse(false, 'SMS পাঠাতে সমস্যা হয়েছে। পুনরায় চেষ্টা করুন।');
}

<?php
/**
 * API: Verify OTP — MFS Compilemama
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
$code  = preg_replace('/\D/', '', $_POST['code'] ?? '');

if (!validateBDPhone($phone)) {
    jsonResponse(false, 'সঠিক ফোন নম্বর দিন।');
}

if (strlen($code) !== 6) {
    jsonResponse(false, '৬ সংখ্যার OTP কোড দিন।');
}

// Rate limit to prevent brute force
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!rateLimitCheck($ip . ':' . $phone, 'otp_verify', 5, 300)) {
    jsonResponse(false, 'অনেকবার ভুল কোড দেওয়া হয়েছে। ৫ মিনিট পরে আবার চেষ্টা করুন।');
}

$db  = getDB();
$now = date('Y-m-d H:i:s');

$stmt = $db->prepare(
    "SELECT id FROM otp_codes WHERE phone=? AND code=? AND expires_at > ? AND verified=0 ORDER BY id DESC LIMIT 1"
);
$stmt->bind_param('sss', $phone, $code, $now);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    jsonResponse(false, 'OTP কোড ভুল অথবা মেয়াদ শেষ হয়ে গেছে।');
}

// Mark as verified
$stmt = $db->prepare("UPDATE otp_codes SET verified=1 WHERE id=?");
$stmt->bind_param('i', $row['id']);
$stmt->execute();
$stmt->close();

// Activate user account
$stmt = $db->prepare("UPDATE users SET status='active' WHERE phone=? AND status='inactive'");
$stmt->bind_param('s', $phone);
$stmt->execute();
$stmt->close();

jsonResponse(true, 'OTP সফলভাবে যাচাই হয়েছে।', ['verified' => true]);
